<?php
/**
 * Football Betting API
 * Handle 1v1, 2v2, and Global betting on football matches
 */

session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    jsonError('Authentication required');
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bindValue(1, $userId, SQLITE3_INTEGER);
$user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    http_response_code(401);
    jsonError('User not found');
}

// ── GET: Get Featured Matches ───────────────────────────────────────────────────
if ($method === 'GET' && $action === 'featured_matches') {
    $res = $db->query("
        SELECT 
            sm.id, 
            sm.home_team, 
            sm.away_team, 
            sm.home_score, 
            sm.away_score,
            sm.match_time, 
            sm.status,
            COUNT(CASE WHEN fp.bet_type='1v1' THEN 1 END) as bets_1v1,
            COUNT(CASE WHEN fp.bet_type='2v2' THEN 1 END) as bets_2v2,
            COUNT(CASE WHEN fp.bet_type='global' THEN 1 END) as bets_global
        FROM sports_matches sm
        LEFT JOIN football_pools fp ON sm.id = fp.match_id AND fp.status IN ('open', 'matched')
        WHERE sm.status IN ('upcoming', 'live')
        GROUP BY sm.id
        ORDER BY sm.match_time ASC
        LIMIT 10
    ");
    
    $matches = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $matches[] = $row;
    }
    jsonOk(['matches' => $matches]);
}

// ── GET: Get Match Betting Pools ────────────────────────────────────────────────
if ($method === 'GET' && $action === 'match_pools') {
    $matchId = (int)($_GET['match_id'] ?? 0);
    if (!$matchId) jsonError('Match ID required');

    $res = $db->query("
        SELECT 
            id, 
            bet_type, 
            team_selection, 
            user1_id, 
            user2_id, 
            amount_btc, 
            pool_total, 
            status,
            (SELECT username FROM users WHERE id = user1_id) as user1_name,
            (SELECT username FROM users WHERE id = user2_id) as user2_name
        FROM football_pools
        WHERE match_id = $matchId AND status IN ('open', 'matched')
        ORDER BY created_at DESC
    ");
    
    $pools = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $pools[] = $row;
    }
    jsonOk(['pools' => $pools]);
}

// ── POST: Place 1v1 Bet ─────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'bet_1v1') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $matchId = (int)($body['match_id'] ?? 0);
    $teamSelection = $body['team_selection'] ?? '';
    $amountBtc = (float)($body['amount_btc'] ?? 0);
    
    if (!$matchId) jsonError('Match ID required');
    if (!in_array($teamSelection, ['home', 'away', 'draw'])) jsonError('Invalid team selection');
    if ($amountBtc <= 0.0001) jsonError('Minimum bet is 0.0001 BTC');
    if ((float)$user['balance_btc'] < $amountBtc) jsonError('Insufficient balance');
    
    // Check if match exists
    $stmt = $db->prepare('SELECT * FROM sports_matches WHERE id = ?');
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $match = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$match) jsonError('Match not found');
    if ($match['status'] !== 'upcoming' && $match['status'] !== 'live') jsonError('Match is not open for betting');
    
    // Check if user already has an open 1v1 bet on this match for this team
    $stmt = $db->prepare("
        SELECT * FROM football_pools 
        WHERE match_id = ? AND user1_id = ? AND bet_type = '1v1' AND status = 'open'
    ");
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
    $existingBet = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($existingBet) jsonError('You already have an open 1v1 bet on this match');
    
    // Try to find an opponent with opposite selection
    $oppositeSelection = $teamSelection === 'home' ? 'away' : ($teamSelection === 'away' ? 'home' : 'draw');
    $stmt = $db->prepare("
        SELECT * FROM football_pools 
        WHERE match_id = ? AND user1_id != ? AND team_selection = ? AND amount_btc = ? AND bet_type = '1v1' AND status = 'open'
        LIMIT 1
    ");
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(3, $oppositeSelection, SQLITE3_TEXT);
    $stmt->bindValue(4, $amountBtc, SQLITE3_FLOAT);
    $opponent = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    // Deduct from user balance
    $newBalance = (float)$user['balance_btc'] - $amountBtc;
    $stmt = $db->prepare('UPDATE users SET balance_btc = ? WHERE id = ?');
    $stmt->bindValue(1, round($newBalance, 8), SQLITE3_FLOAT);
    $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
    $stmt->execute();
    
    if ($opponent) {
        // Match found - create matched pool
        $poolTotal = $amountBtc * 2;
        $stmt = $db->prepare("
            INSERT INTO football_pools(match_id, bet_type, team_selection, user1_id, user2_id, amount_btc, pool_total, status, created_at)
            VALUES(?, '1v1', ?, ?, ?, ?, ?, 'matched', strftime('%s','now'))
        ");
        $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
        $stmt->bindValue(2, $teamSelection, SQLITE3_TEXT);
        $stmt->bindValue(3, $userId, SQLITE3_INTEGER);
        $stmt->bindValue(4, $opponent['user1_id'], SQLITE3_INTEGER);
        $stmt->bindValue(5, $amountBtc, SQLITE3_FLOAT);
        $stmt->bindValue(6, $poolTotal, SQLITE3_FLOAT);
        $stmt->execute();
        $poolId = $db->lastInsertRowID();
        
        // Update opponent's pool status
        $stmt = $db->prepare('UPDATE football_pools SET status = ?, user2_id = ? WHERE id = ?');
        $stmt->bindValue(1, 'matched', SQLITE3_TEXT);
        $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
        $stmt->bindValue(3, $opponent['id'], SQLITE3_INTEGER);
        $stmt->execute();
        
        jsonOk(['message' => 'Matched with opponent!', 'pool_id' => $poolId, 'new_balance' => $newBalance, 'matched' => true]);
    } else {
        // No opponent found - create open pool
        $stmt = $db->prepare("
            INSERT INTO football_pools(match_id, bet_type, team_selection, user1_id, amount_btc, pool_total, status, created_at)
            VALUES(?, '1v1', ?, ?, ?, ?, 'open', strftime('%s','now'))
        ");
        $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
        $stmt->bindValue(2, $teamSelection, SQLITE3_TEXT);
        $stmt->bindValue(3, $userId, SQLITE3_INTEGER);
        $stmt->bindValue(4, $amountBtc, SQLITE3_FLOAT);
        $stmt->bindValue(5, $amountBtc, SQLITE3_FLOAT);
        $stmt->execute();
        $poolId = $db->lastInsertRowID();
        
        jsonOk(['message' => 'Waiting for opponent...', 'pool_id' => $poolId, 'new_balance' => $newBalance, 'matched' => false]);
    }
}

// ── POST: Place 2v2 Bet ─────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'bet_2v2') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $matchId = (int)($body['match_id'] ?? 0);
    $teamSelection = $body['team_selection'] ?? '';
    $amountBtc = (float)($body['amount_btc'] ?? 0);
    $teamName = $body['team_name'] ?? 'Team';
    
    if (!$matchId) jsonError('Match ID required');
    if (!in_array($teamSelection, ['home', 'away', 'draw'])) jsonError('Invalid team selection');
    if ($amountBtc <= 0.0001) jsonError('Minimum bet is 0.0001 BTC');
    if ((float)$user['balance_btc'] < $amountBtc) jsonError('Insufficient balance');
    
    // Check if match exists
    $stmt = $db->prepare('SELECT * FROM sports_matches WHERE id = ?');
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $match = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$match) jsonError('Match not found');
    
    // Deduct from user balance
    $newBalance = (float)$user['balance_btc'] - $amountBtc;
    $stmt = $db->prepare('UPDATE users SET balance_btc = ? WHERE id = ?');
    $stmt->bindValue(1, round($newBalance, 8), SQLITE3_FLOAT);
    $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Create new 2v2 pool
    $stmt = $db->prepare("
        INSERT INTO football_pools(match_id, bet_type, team_selection, user1_id, amount_btc, pool_total, status, created_at)
        VALUES(?, '2v2', ?, ?, ?, ?, 'open', strftime('%s','now'))
    ");
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $teamSelection, SQLITE3_TEXT);
    $stmt->bindValue(3, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(4, $amountBtc, SQLITE3_FLOAT);
    $stmt->bindValue(5, $amountBtc, SQLITE3_FLOAT);
    $stmt->execute();
    $poolId = $db->lastInsertRowID();
    
    // Create team entry
    $stmt = $db->prepare("
        INSERT INTO football_2v2_teams(pool_id, team_name, member1_id, total_amount, created_at)
        VALUES(?, ?, ?, ?, strftime('%s','now'))
    ");
    $stmt->bindValue(1, $poolId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $teamName, SQLITE3_TEXT);
    $stmt->bindValue(3, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(4, $amountBtc, SQLITE3_FLOAT);
    $stmt->execute();
    
    jsonOk(['message' => 'Waiting for team members...', 'pool_id' => $poolId, 'new_balance' => $newBalance]);
}

// ── POST: Place Global Bet ──────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'bet_global') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $matchId = (int)($body['match_id'] ?? 0);
    $teamSelection = $body['team_selection'] ?? '';
    $amountBtc = (float)($body['amount_btc'] ?? 0);
    
    if (!$matchId) jsonError('Match ID required');
    if (!in_array($teamSelection, ['home', 'away', 'draw'])) jsonError('Invalid team selection');
    if ($amountBtc <= 0.0001) jsonError('Minimum bet is 0.0001 BTC');
    if ((float)$user['balance_btc'] < $amountBtc) jsonError('Insufficient balance');
    
    // Check if match exists
    $stmt = $db->prepare('SELECT * FROM sports_matches WHERE id = ?');
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $match = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$match) jsonError('Match not found');
    
    // Deduct from user balance
    $newBalance = (float)$user['balance_btc'] - $amountBtc;
    $stmt = $db->prepare('UPDATE users SET balance_btc = ? WHERE id = ?');
    $stmt->bindValue(1, round($newBalance, 8), SQLITE3_FLOAT);
    $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Add to global pool
    $stmt = $db->prepare("
        INSERT INTO football_pools(match_id, bet_type, team_selection, user1_id, amount_btc, pool_total, status, created_at)
        VALUES(?, 'global', ?, ?, ?, ?, 'open', strftime('%s','now'))
    ");
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $teamSelection, SQLITE3_TEXT);
    $stmt->bindValue(3, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(4, $amountBtc, SQLITE3_FLOAT);
    $stmt->bindValue(5, $amountBtc, SQLITE3_FLOAT);
    $stmt->execute();
    $poolId = $db->lastInsertRowID();
    
    jsonOk(['message' => 'Global bet placed!', 'pool_id' => $poolId, 'new_balance' => $newBalance]);
}

// ── POST: Settle Bets (Admin) ───────────────────────────────────────────────────
if ($method === 'POST' && $action === 'settle_match') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $matchId = (int)($body['match_id'] ?? 0);
    $winner = $body['winner'] ?? ''; // 'home', 'away', 'draw'
    
    if (!$matchId || !in_array($winner, ['home', 'away', 'draw'])) {
        jsonError('Invalid match ID or winner');
    }
    
    // Get all pools for this match
    $res = $db->query("SELECT * FROM football_pools WHERE match_id = $matchId AND status != 'settled'");
    
    while ($pool = $res->fetchArray(SQLITE3_ASSOC)) {
        $isWinner = ($pool['team_selection'] === $winner);
        
        if ($pool['bet_type'] === '1v1') {
            if ($isWinner) {
                // Winner gets the entire pool
                $stmt = $db->prepare('UPDATE users SET balance_btc = balance_btc + ? WHERE id = ?');
                $stmt->bindValue(1, $pool['pool_total'], SQLITE3_FLOAT);
                $stmt->bindValue(2, $pool['user1_id'], SQLITE3_INTEGER);
                $stmt->execute();
                
                // Update pool status
                $stmt = $db->prepare('UPDATE football_pools SET status = ?, winner_id = ?, settled_at = strftime(\'%s\',\'now\') WHERE id = ?');
                $stmt->bindValue(1, 'settled', SQLITE3_TEXT);
                $stmt->bindValue(2, $pool['user1_id'], SQLITE3_INTEGER);
                $stmt->bindValue(3, $pool['id'], SQLITE3_INTEGER);
                $stmt->execute();
            } else {
                // Loser gets nothing
                $stmt = $db->prepare('UPDATE football_pools SET status = ?, settled_at = strftime(\'%s\',\'now\') WHERE id = ?');
                $stmt->bindValue(1, 'settled', SQLITE3_TEXT);
                $stmt->bindValue(2, $pool['id'], SQLITE3_INTEGER);
                $stmt->execute();
            }
        } elseif ($pool['bet_type'] === 'global') {
            // Get all winners for this pool
            $winnerRes = $db->query("
                SELECT user1_id, amount_btc 
                FROM football_pools 
                WHERE match_id = $matchId AND bet_type = 'global' AND team_selection = '$winner' AND status != 'settled'
            ");
            
            $winners = [];
            $totalWinAmount = 0;
            while ($row = $winnerRes->fetchArray(SQLITE3_ASSOC)) {
                $winners[] = $row;
                $totalWinAmount += $row['amount_btc'];
            }
            
            // Get total pool
            $totalRes = $db->querySingle("
                SELECT COALESCE(SUM(amount_btc), 0) as total 
                FROM football_pools 
                WHERE match_id = $matchId AND bet_type = 'global'
            ", true);
            $totalPool = (float)$totalRes['total'];
            
            // Calculate admin cut (10%)
            $adminCut = $totalPool * 0.10;
            $distributionPool = $totalPool - $adminCut;
            
            // Distribute to winners proportionally
            foreach ($winners as $win) {
                $proportion = $win['amount_btc'] / $totalWinAmount;
                $winnings = $distributionPool * $proportion;
                
                $stmt = $db->prepare('UPDATE users SET balance_btc = balance_btc + ? WHERE id = ?');
                $stmt->bindValue(1, $winnings, SQLITE3_FLOAT);
                $stmt->bindValue(2, $win['user1_id'], SQLITE3_INTEGER);
                $stmt->execute();
            }
            
            // Mark all pools as settled
            $stmt = $db->prepare('UPDATE football_pools SET status = ?, admin_cut = ?, settled_at = strftime(\'%s\',\'now\') WHERE match_id = ? AND bet_type = \'global\'');
            $stmt->bindValue(1, 'settled', SQLITE3_TEXT);
            $stmt->bindValue(2, $adminCut, SQLITE3_FLOAT);
            $stmt->bindValue(3, $matchId, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
    
    jsonOk(['message' => 'Match settled successfully']);
}

jsonError('Unknown action');
