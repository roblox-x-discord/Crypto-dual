<?php
/**
 * API-Football Integration
 * Fetches real football matches and fixtures
 */

session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

$action = $_GET['action'] ?? 'fixtures';
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: Live and Upcoming Fixtures ───────────────────────────────────────────────────
if ($method === 'GET' && $action === 'fixtures') {
    $league = $_GET['league'] ?? '';
    $live = $_GET['live'] ?? 'false';
    $days = intval($_GET['days'] ?? '7');

    // API-Football endpoint
    $endpoint = $live === 'true' ? '/fixtures?live=all' : "/fixtures?league=39&season=2024&next={$days}";

    $apiUrl = API_FOOTBALL_URL . $endpoint;

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-rapidapi-key: ' . API_FOOTBALL_KEY,
            'x-rapidapi-host: v3.football.api-sports.io',
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        // Return mock data on API failure
        $mockFixtures = getMockFixtures();
        jsonOk(['fixtures' => $mockFixtures, 'source' => 'mock']);
    }

    $data = json_decode($response, true);

    if (!isset($data['response'])) {
        $mockFixtures = getMockFixtures();
        jsonOk(['fixtures' => $mockFixtures, 'source' => 'mock']);
    }

    $fixtures = [];
    $db = getDB();

    foreach ($data['response'] as $match) {
        $homeTeam = $match['teams']['home']['name'] ?? 'Home';
        $awayTeam = $match['teams']['away']['name'] ?? 'Away';
        $leagueName = $match['league']['name'] ?? 'Premier League';

        // Format match time
        $matchTime = isset($match['fixture']['timestamp'])
            ? intval($match['fixture']['timestamp'] / 1000)
            : time() + 3600;

        // Check if match exists in our database
        $fixtureId = $match['fixture']['id'] ?? null;
        $status = 'upcoming';

        if (isset($match['fixture']['status']['short'])) {
            $matchStatus = $match['fixture']['status']['short'];
            if ($matchStatus === 'LIVE' || $matchStatus === '1H' || $matchStatus === '2H' || $matchStatus === 'HT' || $matchStatus === 'ET') {
                $status = 'live';
            } elseif ($matchStatus === 'FT' || $matchStatus === 'AET' || $matchStatus === 'PEN') {
                $status = 'finished';
            }
        }

        // Get scores if available
        $homeScore = $match['goals']['home'] ?? 0;
        $awayScore = $match['goals']['away'] ?? 0;

        // Upsert match into database
        if ($fixtureId) {
            $stmt = $db->prepare(
                'INSERT INTO sports_matches(api_fixture_id, sport_type, home_team, away_team, league_name, match_time, status, home_score, away_score, created_at)
                 VALUES(?, \'FOOTBALL\', ?, ?, ?, ?, ?, ?, ?, strftime(\'%s\',\'now\'))
                 ON CONFLICT(api_fixture_id) DO UPDATE SET
                 status=excluded.status,
                 home_score=excluded.home_score,
                 away_score=excluded.away_score,
                 match_time=excluded.match_time'
            );
            $stmt->bindValue(1, $fixtureId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $homeTeam, SQLITE3_TEXT);
            $stmt->bindValue(3, $awayTeam, SQLITE3_TEXT);
            $stmt->bindValue(4, $leagueName, SQLITE3_TEXT);
            $stmt->bindValue(5, $matchTime, SQLITE3_INTEGER);
            $stmt->bindValue(6, $status, SQLITE3_TEXT);
            $stmt->bindValue(7, $homeScore, SQLITE3_INTEGER);
            $stmt->bindValue(8, $awayScore, SQLITE3_INTEGER);
            $stmt->execute();

            // Get the local match ID
            $localMatch = $db->querySingle("SELECT id FROM sports_matches WHERE api_fixture_id = $fixtureId");
        }

        $fixtures[] = [
            'id' => (int)($localMatch ?? 0),
            'api_fixture_id' => $fixtureId,
            'sport_type' => 'FOOTBALL',
            'home_team' => $homeTeam,
            'away_team' => $awayTeam,
            'league_name' => $leagueName,
            'match_time' => $matchTime,
            'status' => $status,
            'home_score' => (int)$homeScore,
            'away_score' => (int)$awayScore,
            'winner' => null,
        ];
    }

    jsonOk(['fixtures' => $fixtures, 'source' => 'api-football', 'count' => count($fixtures)]);
}

// ── POST: Sync/Update Match from API ───────────────────────────────────────────────────
if ($method === 'POST' && $action === 'sync_match') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $fixtureId = (int)($body['fixture_id'] ?? 0);

    if (!$fixtureId) {
        jsonError('Fixture ID required');
    }

    $apiUrl = API_FOOTBALL_URL . "/fixtures?id={$fixtureId}";

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-rapidapi-key: ' . API_FOOTBALL_KEY,
            'x-rapidapi-host: v3.football.api-sports.io',
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        jsonError('Failed to fetch match data from API-Football');
    }

    $data = json_decode($response, true);

    if (!isset($data['response'][0])) {
        jsonError('Match not found in API-Football');
    }

    $match = $data['response'][0];
    $db = getDB();

    // Get existing match
    $stmt = $db->prepare('SELECT * FROM sports_matches WHERE api_fixture_id = ?');
    $stmt->bindValue(1, $fixtureId, SQLITE3_INTEGER);
    $existingMatch = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$existingMatch) {
        jsonError('Match not found in local database');
    }

    // Update match with latest data
    $homeScore = $match['goals']['home'] ?? 0;
    $awayScore = $match['goals']['away'] ?? 0;
    $status = 'upcoming';

    if (isset($match['fixture']['status']['short'])) {
        $matchStatus = $match['fixture']['status']['short'];
        if ($matchStatus === 'LIVE' || $matchStatus === '1H' || $matchStatus === '2H' || $matchStatus === 'HT' || $matchStatus === 'ET') {
            $status = 'live';
        } elseif ($matchStatus === 'FT' || $matchStatus === 'AET' || $matchStatus === 'PEN') {
            $status = 'finished';
        }
    }

    $winner = null;
    if ($status === 'finished') {
        if ($homeScore > $awayScore) {
            $winner = 'home';
        } elseif ($awayScore > $homeScore) {
            $winner = 'away';
        } else {
            $winner = 'draw';
        }
    }

    // Update match
    $stmt = $db->prepare(
        'UPDATE sports_matches
         SET home_score = ?, away_score = ?, status = ?, winner = ?
         WHERE id = ?'
    );
    $stmt->bindValue(1, $homeScore, SQLITE3_INTEGER);
    $stmt->bindValue(2, $awayScore, SQLITE3_INTEGER);
    $stmt->bindValue(3, $status, SQLITE3_TEXT);
    $stmt->bindValue(4, $winner, SQLITE3_TEXT);
    $stmt->bindValue(5, (int)$existingMatch['id'], SQLITE3_INTEGER);
    $stmt->execute();

    // If match is finished, settle bets
    if ($status === 'finished' && $existingMatch['status'] !== 'finished') {
        // Trigger bet settlement
        $settleData = [
            'match_id' => (int)$existingMatch['id'],
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ];

        // Call settlement logic
        settleMatchBets($db, (int)$existingMatch['id'], $homeScore, $awayScore, $winner);
    }

    jsonOk([
        'match_id' => (int)$existingMatch['id'],
        'home_score' => $homeScore,
        'away_score' => $awayScore,
        'status' => $status,
        'winner' => $winner,
        'message' => 'Match synced successfully'
    ]);
}

// ── GET: Available Leagues ─────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'leagues') {
    // Return popular leagues
    jsonOk([
        'leagues' => [
            ['id' => 39, 'name' => 'Premier League', 'country' => 'England', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿'],
            ['id' => 140, 'name' => 'La Liga', 'country' => 'Spain', 'flag' => '🇪🇸'],
            ['id' => 78, 'name' => 'Bundesliga', 'country' => 'Germany', 'flag' => '🇩🇪'],
            ['id' => 61, 'name' => 'Ligue 1', 'country' => 'France', 'flag' => '🇫🇷'],
            ['id' => 135, 'name' => 'Serie A', 'country' => 'Italy', 'flag' => '🇮🇹'],
            ['id' => 88, 'name' => 'Eredivisie', 'country' => 'Netherlands', 'flag' => '🇳🇱'],
        ]
    ]);
}

/**
 * Settle bets for a finished match
 */
function settleMatchBets($db, $matchId, $homeScore, $awayScore, $winner) {
    // Get all pending bets for this match
    $res = $db->query("SELECT * FROM sports_bets WHERE match_id = {$matchId} AND status = 'pending'");
    $bets = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $bets[] = $r;
    }

    if (empty($bets)) {
        return;
    }

    // Calculate prize pools
    $p2pPool = 0;
    $housePool = 0;
    $winningSelections = [];

    foreach ($bets as $bet) {
        if ($bet['bet_type'] === 'p2p') {
            $p2pPool += (float)$bet['amount_btc'];
        } else {
            $housePool += (float)$bet['amount_btc'];
        }

        if ($bet['team_selection'] === $winner) {
            $winningSelections[] = $bet;
        }
    }

    // Process each bet
    foreach ($bets as $bet) {
        $userId = (int)$bet['user_id'];
        $winAmount = 0;
        $isWinner = $bet['team_selection'] === $winner;

        if ($bet['bet_type'] === 'p2p' && $isWinner && count($winningSelections) > 0) {
            // Split 75% of P2P pool among winners
            $userShare = (float)$bet['amount_btc'] / array_sum(array_map(fn($b) => (float)$b['amount_btc'], $winningSelections));
            $winAmount = ($p2pPool * 0.75) * $userShare;
        } elseif ($bet['bet_type'] === 'house' && $isWinner) {
            // Winner gets 1.5x their bet
            $winAmount = (float)$bet['amount_btc'] * 1.5;
        }

        $stmt = $db->prepare('UPDATE sports_bets SET win_amount = ?, status = ?, settled_at = strftime(\'%s\',\'now\') WHERE id = ?');
        $stmt->bindValue(1, $winAmount, SQLITE3_FLOAT);
        $stmt->bindValue(2, $isWinner ? 'won' : 'lost', SQLITE3_TEXT);
        $stmt->bindValue(3, (int)$bet['id'], SQLITE3_INTEGER);
        $stmt->execute();

        // Credit winners
        if ($winAmount > 0) {
            $userStmt = $db->prepare('SELECT balance_btc FROM users WHERE id = ?');
            $userStmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $userData = $userStmt->execute()->fetchArray(SQLITE3_ASSOC);
            $currentBal = (float)$userData['balance_btc'];
            $newBal = $currentBal + $winAmount;

            $db->exec("UPDATE users SET balance_btc = round($newBal,8) WHERE id = $userId");

            // Record transaction
            $stmt = $db->prepare(
                'INSERT INTO transactions(user_id, type, amount_btc, balance_after, status, tx_hash, notes, created_at)
                 VALUES(?, ?, ?, ?, ?, ?, ?, strftime(\'%s\',\'now\'))'
            );
            $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $stmt->bindValue(2, 'sports_win', SQLITE3_TEXT);
            $stmt->bindValue(3, $winAmount, SQLITE3_FLOAT);
            $stmt->bindValue(4, $newBal, SQLITE3_FLOAT);
            $stmt->bindValue(5, 'confirmed', SQLITE3_TEXT);
            $stmt->bindValue(6, 'match_' . $matchId, SQLITE3_TEXT);
            $stmt->bindValue(7, 'Won sports bet on match ID ' . $matchId, SQLITE3_TEXT);
            $stmt->execute();
        }
    }
}

/**
 * Get mock fixtures for when API fails
 */
function getMockFixtures() {
    return [
        [
            'id' => 0,
            'api_fixture_id' => null,
            'sport_type' => 'FOOTBALL',
            'home_team' => 'Manchester United',
            'away_team' => 'Arsenal',
            'league_name' => 'Premier League',
            'match_time' => time() + 3600,
            'status' => 'upcoming',
            'home_score' => 0,
            'away_score' => 0,
            'winner' => null,
        ],
        [
            'id' => 0,
            'api_fixture_id' => null,
            'sport_type' => 'FOOTBALL',
            'home_team' => 'Chelsea',
            'away_team' => 'Liverpool',
            'league_name' => 'Premier League',
            'match_time' => time() + 7200,
            'status' => 'upcoming',
            'home_score' => 0,
            'away_score' => 0,
            'winner' => null,
        ],
        [
            'id' => 0,
            'api_fixture_id' => null,
            'sport_type' => 'FOOTBALL',
            'home_team' => 'Real Madrid',
            'away_team' => 'Barcelona',
            'league_name' => 'La Liga',
            'match_time' => time() + 10800,
            'status' => 'upcoming',
            'home_score' => 0,
            'away_score' => 0,
            'winner' => null,
        ],
    ];
}

jsonError('Unknown action');
