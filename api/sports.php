<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'matches';
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: List matches ─────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'matches') {
    $db = getDB();
    $sportFilter = $_GET['sport'] ?? '';
    $statusFilter = $_GET['status'] ?? 'upcoming';

    $sql = "SELECT * FROM sports_matches WHERE status='$statusFilter'";
    if ($sportFilter) {
        $sql .= " AND sport_type='" . strtoupper($sportFilter) . "'";
    }
    $sql .= " ORDER BY match_time ASC LIMIT 50";

    $res = $db->query($sql);
    $matches = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $matches[] = [
            'id' => (int)$r['id'],
            'sport_type' => $r['sport_type'],
            'home_team' => $r['home_team'],
            'away_team' => $r['away_team'],
            'league_name' => $r['league_name'],
            'match_time' => (int)$r['match_time'],
            'status' => $r['status'],
            'home_score' => (int)$r['home_score'],
            'away_score' => (int)$r['away_score'],
            'winner' => $r['winner'],
        ];
    }

    jsonOk(['matches' => $matches]);
}

// ── POST: Create match (admin only - simplified) ───────────────────────────────────
if ($method === 'POST' && $action === 'create_match') {
    $u = requireAuth();
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $sport = strtoupper($body['sport_type'] ?? 'FOOTBALL');
    $homeTeam = trim($body['home_team'] ?? '');
    $awayTeam = trim($body['away_team'] ?? '');
    $league = trim($body['league_name'] ?? '');
    $matchTime = (int)($body['match_time'] ?? 0);

    if (!in_array($sport, ['FOOTBALL', 'BASKETBALL'])) {
        jsonError('Invalid sport type. Use FOOTBALL or BASKETBALL');
    }
    if (!$homeTeam || !$awayTeam) {
        jsonError('Home and away team names are required');
    }
    if ($matchTime <= time()) {
        jsonError('Match time must be in the future');
    }

    $stmt = $db->prepare(
        'INSERT INTO sports_matches(sport_type, home_team, away_team, league_name, match_time, status)
         VALUES(?,?,?,?,?,"upcoming")'
    );
    $stmt->bindValue(1, $sport, SQLITE3_TEXT);
    $stmt->bindValue(2, $homeTeam, SQLITE3_TEXT);
    $stmt->bindValue(3, $awayTeam, SQLITE3_TEXT);
    $stmt->bindValue(4, $league, SQLITE3_TEXT);
    $stmt->bindValue(5, $matchTime, SQLITE3_INTEGER);
    $stmt->execute();

    jsonOk(['match_id' => $db->lastInsertRowID(), 'message' => 'Match created']);
}

// ── GET: Match details with bets ────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'match') {
    $db = getDB();
    $matchId = (int)($_GET['match_id'] ?? 0);

    if (!$matchId) jsonError('Match ID required');

    $stmt = $db->prepare('SELECT * FROM sports_matches WHERE id=?');
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $match = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$match) jsonError('Match not found');

    // Get bets for this match
    $res = $db->query("SELECT * FROM sports_bets WHERE match_id=$matchId AND status='pending'");
    $bets = [];
    $totalPool = 0;
    $homeBets = 0;
    $awayBets = 0;
    $drawBets = 0;

    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $amt = (float)$r['amount_btc'];
        $totalPool += $amt;
        if ($r['team_selection'] === 'home') $homeBets += $amt;
        elseif ($r['team_selection'] === 'away') $awayBets += $amt;
        else $drawBets += $amt;

        $bets[] = [
            'id' => (int)$r['id'],
            'user_id' => (int)$r['user_id'],
            'bet_type' => $r['bet_type'],
            'team_selection' => $r['team_selection'],
            'amount' => $amt,
            'potential_win' => (float)$r['potential_win'],
            'status' => $r['status'],
        ];
    }

    jsonOk([
        'match' => [
            'id' => (int)$match['id'],
            'sport_type' => $match['sport_type'],
            'home_team' => $match['home_team'],
            'away_team' => $match['away_team'],
            'league_name' => $match['league_name'],
            'match_time' => (int)$match['match_time'],
            'status' => $match['status'],
            'home_score' => (int)$match['home_score'],
            'away_score' => (int)$match['away_score'],
            'winner' => $match['winner'],
        ],
        'bets' => $bets,
        'total_pool' => round($totalPool, 8),
        'home_bets' => round($homeBets, 8),
        'away_bets' => round($awayBets, 8),
        'draw_bets' => round($drawBets, 8),
    ]);
}

// ── POST: Place bet ────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'place_bet') {
    $u = requireAuth();
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $matchId = (int)($body['match_id'] ?? 0);
    $betType = $body['bet_type'] ?? ''; // 'p2p' or 'house'
    $teamSelection = $body['team_selection'] ?? ''; // 'home', 'away', or 'draw'
    $amount = (float)($body['amount_btc'] ?? 0);

    if (!$matchId) jsonError('Match ID required');
    if (!in_array($betType, ['p2p', 'house'])) jsonError('Invalid bet type');
    if (!in_array($teamSelection, ['home', 'away', 'draw'])) jsonError('Invalid team selection');
    if ($amount < 0.00001) jsonError('Minimum bet: 0.00001 BTC');

    // Check match exists and is upcoming
    $stmt = $db->prepare('SELECT * FROM sports_matches WHERE id=?');
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $match = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$match) jsonError('Match not found');
    if ($match['status'] !== 'upcoming') jsonError('Match is not open for betting');
    if ($match['match_time'] < time()) jsonError('Match has already started');

    // Check balance
    if ((float)$u['balance_btc'] < $amount) jsonError('Insufficient balance');

    // Calculate potential win (75% of total pot goes to winners)
    // For house bets: user gets 1.5x their bet if they win
    $potentialWin = $betType === 'house' ? $amount * 1.5 : $amount * 1.5;

    $db->exec('BEGIN IMMEDIATE');

    // Deduct balance
    $newBal = (float)$u['balance_btc'] - $amount;
    $db->exec("UPDATE users SET balance_btc=round($newBal,8) WHERE id={$u['id']}");

    // Insert bet
    $stmt = $db->prepare(
        'INSERT INTO sports_bets(user_id, match_id, bet_type, team_selection, amount_btc, potential_win, status)
         VALUES(?,?,?,?,?,"pending")'
    );
    $stmt->bindValue(1, (int)$u['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $matchId, SQLITE3_INTEGER);
    $stmt->bindValue(3, $betType, SQLITE3_TEXT);
    $stmt->bindValue(4, $teamSelection, SQLITE3_TEXT);
    $stmt->bindValue(5, $amount, SQLITE3_FLOAT);
    $stmt->bindValue(6, $potentialWin, SQLITE3_FLOAT);
    $stmt->execute();

    // Record transaction
    recordTx($db, (int)$u['id'], 'sports_bet', -$amount, $newBal, 'confirmed', "Sports bet on {$match['home_team']} vs {$match['away_team']}");

    $db->exec('COMMIT');

    jsonOk([
        'balance_btc' => $newBal,
        'bet_id' => $db->lastInsertRowID(),
        'potential_win' => round($potentialWin, 8),
        'message' => 'Bet placed successfully!'
    ]);
}

// ── POST: Settle match (determine winner) ────────────────────────────────────────────
if ($method === 'POST' && $action === 'settle_match') {
    $u = requireAuth();
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $matchId = (int)($body['match_id'] ?? 0);
    $homeScore = (int)($body['home_score'] ?? 0);
    $awayScore = (int)($body['away_score'] ?? 0);

    if (!$matchId) jsonError('Match ID required');

    $stmt = $db->prepare('SELECT * FROM sports_matches WHERE id=?');
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $match = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$match) jsonError('Match not found');
    if ($match['status'] === 'finished') jsonError('Match already settled');

    // Determine winner
    $winner = $homeScore > $awayScore ? 'home' : ($awayScore > $homeScore ? 'away' : 'draw');

    $db->exec('BEGIN IMMEDIATE');

    // Update match
    $stmt = $db->prepare(
        'UPDATE sports_matches SET home_score=?, away_score=?, status="finished", winner=? WHERE id=?'
    );
    $stmt->bindValue(1, $homeScore, SQLITE3_INTEGER);
    $stmt->bindValue(2, $awayScore, SQLITE3_INTEGER);
    $stmt->bindValue(3, $winner, SQLITE3_TEXT);
    $stmt->bindValue(4, $matchId, SQLITE3_INTEGER);
    $stmt->execute();

    // Get all pending bets for this match
    $res = $db->query("SELECT * FROM sports_bets WHERE match_id=$matchId AND status='pending'");
    $bets = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $bets[] = $r;
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

    // P2P bets: winners split 75% of pool proportionally
    // House bets: winners get 1.5x their bet (house keeps the loss)
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

        $stmt = $db->prepare('UPDATE sports_bets SET win_amount=?, status=?, settled_at=? WHERE id=?');
        $stmt->bindValue(1, $winAmount, SQLITE3_FLOAT);
        $stmt->bindValue(2, $isWinner ? 'won' : 'lost', SQLITE3_TEXT);
        $stmt->bindValue(3, time(), SQLITE3_INTEGER);
        $stmt->bindValue(4, (int)$bet['id'], SQLITE3_INTEGER);
        $stmt->execute();

        // Credit winners
        if ($winAmount > 0) {
            $userStmt = $db->prepare('SELECT balance_btc FROM users WHERE id=?');
            $userStmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $userData = $userStmt->execute()->fetchArray(SQLITE3_ASSOC);
            $currentBal = (float)$userData['balance_btc'];
            $newBal = $currentBal + $winAmount;

            $db->exec("UPDATE users SET balance_btc=round($newBal,8) WHERE id=$userId");
            recordTx($db, $userId, 'sports_win', $winAmount, $newBal, 'confirmed', "Won bet on {$match['home_team']} vs {$match['away_team']}");
        }
    }

    $db->exec('COMMIT');

    jsonOk([
        'message' => 'Match settled',
        'winner' => $winner,
        'p2p_pool' => round($p2pPool, 8),
        'house_pool' => round($housePool, 8),
        'total_winners' => count($winningSelections),
    ]);
}

// ── GET: My sports bets ────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'my_bets') {
    $u = requireAuth();
    $db = getDB();

    $res = $db->query(
        "SELECT sb.*, sm.home_team, sm.away_team, sm.sport_type, sm.home_score, sm.away_score, sm.winner, sm.status as match_status
         FROM sports_bets sb
         JOIN sports_matches sm ON sb.match_id = sm.id
         WHERE sb.user_id={$u['id']}
         ORDER BY sb.created_at DESC LIMIT 50"
    );

    $bets = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $bets[] = [
            'id' => (int)$r['id'],
            'match_id' => (int)$r['match_id'],
            'sport_type' => $r['sport_type'],
            'home_team' => $r['home_team'],
            'away_team' => $r['away_team'],
            'bet_type' => $r['bet_type'],
            'team_selection' => $r['team_selection'],
            'amount' => (float)$r['amount_btc'],
            'potential_win' => (float)$r['potential_win'],
            'win_amount' => (float)$r['win_amount'],
            'status' => $r['status'],
            'match_status' => $r['match_status'],
            'home_score' => (int)$r['home_score'],
            'away_score' => (int)$r['away_score'],
            'winner' => $r['winner'],
            'created_at' => (int)$r['created_at'],
        ];
    }

    jsonOk(['bets' => $bets]);
}

// ── GET: Sports leaderboard ────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'leaderboard') {
    $db = getDB();

    $res = $db->query(
        "SELECT u.username, u.id,
         COUNT(CASE WHEN sb.status='won' THEN 1 END) as wins,
         COUNT(CASE WHEN sb.status='lost' THEN 1 END) as losses,
         COALESCE(SUM(CASE WHEN sb.status='won' THEN sb.win_amount - sb.amount_btc ELSE 0 END), 0) as profit
         FROM users u
         LEFT JOIN sports_bets sb ON u.id = sb.user_id
         GROUP BY u.id
         HAVING wins > 0 OR losses > 0
         ORDER BY profit DESC
         LIMIT 20"
    );

    $leaderboard = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $leaderboard[] = [
            'id' => (int)$r['id'],
            'username' => $r['username'],
            'wins' => (int)$r['wins'],
            'losses' => (int)$r['losses'],
            'profit' => round((float)$r['profit'], 8),
        ];
    }

    jsonOk(['leaderboard' => $leaderboard]);
}

jsonError('Unknown action');
