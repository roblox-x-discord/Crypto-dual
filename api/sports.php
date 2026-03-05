<?php
/**
 * Sports Betting API
 * Handle placing and managing sports bets
 */

session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    jsonError('Authentication required');
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Get current user
$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bindValue(1, $userId, SQLITE3_INTEGER);
$user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    http_response_code(401);
    jsonError('User not found');
}

// ── POST: Place Bet ─────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'place_bet') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $matchId = (int)($body['match_id'] ?? 0);
    $teamSelection = $body['team_selection'] ?? '';
    $amountBtc = (float)($body['amount_btc'] ?? 0);
    $betType = $body['bet_type'] ?? 'p2p'; // p2p or house

    // Validate input
    if (!$matchId) {
        jsonError('Match ID required');
    }

    if (!in_array($teamSelection, ['home', 'away', 'draw'])) {
        jsonError('Invalid team selection');
    }

    if ($amountBtc <= 0) {
        jsonError('Invalid bet amount');
    }

    // Check minimum bet
    if ($amountBtc < 0.0001) {
        jsonError('Minimum bet is 0.0001 BTC');
    }

    // Check if user has enough balance
    if ((float)$user['balance_btc'] < $amountBtc) {
        jsonError('Insufficient balance');
    }

    // Get match details
    $stmt = $db->prepare('SELECT * FROM sports_matches WHERE id = ?');
    $stmt->bindValue(1, $matchId, SQLITE3_INTEGER);
    $match = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$match) {
        jsonError('Match not found');
    }

    if ($match['status'] !== 'upcoming') {
        jsonError('Match is not open for betting');
    }

    // Check if user already has a bet on this match
    $stmt = $db->prepare('SELECT * FROM sports_bets WHERE user_id = ? AND match_id = ?');
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $matchId, SQLITE3_INTEGER);
    $existingBet = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($existingBet) {
        jsonError('You already have a bet on this match');
    }

    // Calculate potential win
    $winAmount = 0;
    if ($betType === 'house') {
        $winAmount = $amountBtc * 1.5;
    } else {
        // P2P - actual amount depends on pool
        $winAmount = $amountBtc; // Will be calculated on settlement
    }

    // Deduct from user balance
    $newBalance = (float)$user['balance_btc'] - $amountBtc;
    $db->exec("UPDATE users SET balance_btc = round($newBalance,8) WHERE id = $userId");

    // Place bet
    $stmt = $db->prepare(
        'INSERT INTO sports_bets(user_id, match_id, team_selection, amount_btc, win_amount, bet_type, status, created_at)
         VALUES(?, ?, ?, ?, ?, ?, ?, strftime(\'%s\',\'now\'))'
    );
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $matchId, SQLITE3_INTEGER);
    $stmt->bindValue(3, $teamSelection, SQLITE3_TEXT);
    $stmt->bindValue(4, $amountBtc, SQLITE3_FLOAT);
    $stmt->bindValue(5, $winAmount, SQLITE3_FLOAT);
    $stmt->bindValue(6, $betType, SQLITE3_TEXT);
    $stmt->bindValue(7, 'pending', SQLITE3_TEXT);
    $stmt->execute();

    // Record transaction
    $stmt = $db->prepare(
        'INSERT INTO transactions(user_id, type, amount_btc, balance_after, status, tx_hash, notes, created_at)
         VALUES(?, ?, ?, ?, ?, ?, ?, strftime(\'%s\',\'now\'))'
    );
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(2, 'sports_bet', SQLITE3_TEXT);
    $stmt->bindValue(3, -$amountBtc, SQLITE3_FLOAT);
    $stmt->bindValue(4, $newBalance, SQLITE3_FLOAT);
    $stmt->bindValue(5, 'confirmed', SQLITE3_TEXT);
    $stmt->bindValue(6, 'match_' . $matchId, SQLITE3_TEXT);
    $stmt->bindValue(7, 'Bet on ' . $match['home_team'] . ' vs ' . $match['away_team'] . ' (' . $teamSelection . ')', SQLITE3_TEXT);
    $stmt->execute();

    // Send Discord notification
    $webhookUrl = DISCORD_WEBHOOK_URL;
    $data = [
        'embeds' => [[
            'title' => '⚽ New Sports Bet Placed',
            'color' => $betType === 'house' ? 15105570 : 3447003,
            'fields' => [
                ['name' => 'User', 'value' => $user['username'], 'inline' => true],
                ['name' => 'Match', 'value' => $match['home_team'] . ' vs ' . $match['away_team'], 'inline' => true],
                ['name' => 'Selection', 'value' => strtoupper($teamSelection), 'inline' => true],
                ['name' => 'Amount', 'value' => round($amountBtc, 8) . ' BTC', 'inline' => true],
                ['name' => 'Type', 'value' => strtoupper($betType), 'inline' => true],
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
        'message' => 'Bet placed successfully',
        'new_balance' => $newBalance,
        'bet_id' => $db->lastInsertRowID()
    ]);
}

// ── GET: List User Bets ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'my_bets') {
    $res = $db->query(
        "SELECT sb.*, sm.home_team, sm.away_team, sm.home_score, sm.away_score, sm.status as match_status, sm.winner
         FROM sports_bets sb
         JOIN sports_matches sm ON sb.match_id = sm.id
         WHERE sb.user_id = $userId
         ORDER BY sb.created_at DESC LIMIT 20"
    );
    $bets = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $bets[] = $row;
    }
    jsonOk(['bets' => $bets]);
}

jsonError('Unknown action');
