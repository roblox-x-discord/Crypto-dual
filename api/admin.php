<?php
/**
 * Admin Panel API
 * Only accessible to admin email: Viniemmanuel8@gmail.com
 */

session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    jsonError('Authentication required');
}

// Get current user
$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
$user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

// Check if user is admin
if (!$user || $user['email'] !== 'Viniemmanuel8@gmail.com') {
    http_response_code(403);
    jsonError('Access denied. Admin only.');
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: List Users ─────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list_users') {
    $res = $db->query('SELECT id, username, email, balance_btc, created_at FROM users ORDER BY created_at DESC');
    $users = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    jsonOk(['users' => $users]);
}

// ── GET: List Matches ───────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list_matches') {
    $res = $db->query('SELECT * FROM sports_matches ORDER BY match_time DESC LIMIT 50');
    $matches = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $matches[] = $row;
    }
    jsonOk(['matches' => $matches]);
}

// ── GET: List Bets ──────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list_bets') {
    $res = $db->query('SELECT * FROM sports_bets ORDER BY created_at DESC LIMIT 100');
    $bets = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $bets[] = $row;
    }
    jsonOk(['bets' => $bets]);
}

// ── GET: Lottery Status ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'lottery_status') {
    // Get lottery participants (users with balance >= 0.001)
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM users WHERE balance_btc >= 0.001');
    $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $participants = $res['count'] ?? 0;

    // Calculate pool (0.0001 BTC per participant)
    $pool = $participants * 0.0001;

    jsonOk([
        'participants' => (int)$participants,
        'pool_btc' => round($pool, 8)
    ]);
}

// ── POST: Draw Lottery Winner ───────────────────────────────────────────────────
if ($method === 'POST' && $action === 'draw_lottery') {
    // Get eligible participants
    $stmt = $db->prepare('SELECT id, username, balance_btc FROM users WHERE balance_btc >= 0.001');
    $res = $stmt->execute();
    $participants = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $participants[] = $row;
    }

    if (empty($participants)) {
        jsonError('No eligible participants');
    }

    // Calculate prize pool
    $pool = count($participants) * 0.0001;

    // Select random winner
    $winner = $participants[array_rand($participants)];
    $winnerId = (int)$winner['id'];

    // Credit winner
    $stmt = $db->prepare('UPDATE users SET balance_btc = balance_btc + ? WHERE id = ?');
    $stmt->bindValue(1, $pool, SQLITE3_FLOAT);
    $stmt->bindValue(2, $winnerId, SQLITE3_INTEGER);
    $stmt->execute();

    // Record transaction
    $stmt = $db->prepare(
        'INSERT INTO transactions(user_id, type, amount_btc, balance_after, status, tx_hash, notes, created_at)
         VALUES(?, ?, ?, ?, ?, ?, ?, strftime(\'%s\',\'now\'))'
    );
    $stmt->bindValue(1, $winnerId, SQLITE3_INTEGER);
    $stmt->bindValue(2, 'lottery_win', SQLITE3_TEXT);
    $stmt->bindValue(3, $pool, SQLITE3_FLOAT);
    $stmt->bindValue(4, (float)$winner['balance_btc'] + $pool, SQLITE3_FLOAT);
    $stmt->bindValue(5, 'confirmed', SQLITE3_TEXT);
    $stmt->bindValue(6, 'lottery_' . time(), SQLITE3_TEXT);
    $stmt->bindValue(7, 'Lottery winner!', SQLITE3_TEXT);
    $stmt->execute();

    // Send Discord notification
    $webhookUrl = DISCORD_WEBHOOK_URL;
    $data = [
        'embeds' => [[
            'title' => '🎰 Lottery Winner Drawn!',
            'color' => 15158532,
            'fields' => [
                ['name' => 'Winner', 'value' => $winner['username'], 'inline' => true],
                ['name' => 'Prize', 'value' => round($pool, 8) . ' BTC', 'inline' => true],
                ['name' => 'Participants', 'value' => count($participants), 'inline' => true],
            ],
            'timestamp' => date('c'),
        ]]
    ];

    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);

    jsonOk([
        'winner' => $winner['username'],
        'winner_id' => $winnerId,
        'prize' => round($pool, 8),
        'participants' => count($participants)
    ]);
}

// ── POST: Update User Balance ───────────────────────────────────────────────────
if ($method === 'POST' && $action === 'update_balance') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $userId = (int)($body['user_id'] ?? 0);
    $amount = (float)($body['amount'] ?? 0);

    if (!$userId) {
        jsonError('User ID required');
    }

    $stmt = $db->prepare('UPDATE users SET balance_btc = balance_btc + ? WHERE id = ?');
    $stmt->bindValue(1, $amount, SQLITE3_FLOAT);
    $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
    $stmt->execute();

    jsonOk(['message' => 'Balance updated']);
}

// ── POST: Settle Match ──────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'settle_match') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $matchId = (int)($body['match_id'] ?? 0);
    $homeScore = (int)($body['home_score'] ?? 0);
    $awayScore = (int)($body['away_score'] ?? 0);

    if (!$matchId) {
        jsonError('Match ID required');
    }

    // Determine winner
    $winner = 'draw';
    if ($homeScore > $awayScore) {
        $winner = 'home';
    } elseif ($awayScore > $homeScore) {
        $winner = 'away';
    }

    // Update match
    $stmt = $db->prepare(
        'UPDATE sports_matches
         SET home_score = ?, away_score = ?, status = ?, winner = ?
         WHERE id = ?'
    );
    $stmt->bindValue(1, $homeScore, SQLITE3_INTEGER);
    $stmt->bindValue(2, $awayScore, SQLITE3_INTEGER);
    $stmt->bindValue(3, 'finished', SQLITE3_TEXT);
    $stmt->bindValue(4, $winner, SQLITE3_TEXT);
    $stmt->bindValue(5, $matchId, SQLITE3_INTEGER);
    $stmt->execute();

    // Settle bets
    require_once dirname(__FILE__) . '/football.php';
    settleMatchBets($db, $matchId, $homeScore, $awayScore, $winner);

    jsonOk(['message' => 'Match settled', 'winner' => $winner]);
}

jsonError('Unknown action');
