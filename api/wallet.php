<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'address';
$method = $_SERVER['REQUEST_METHOD'];
$u      = requireAuth();
$db     = getDB();

// ── GET deposit address ───────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'address') {
    if (!$u['wallet_address']) {
        $addr = walletAddress((int)$u['id']);
        $db->exec("UPDATE users SET wallet_address='$addr' WHERE id={$u['id']}");
    } else {
        $addr = $u['wallet_address'];
    }
    jsonOk(['address' => $addr]);
}

// ── GET transaction history ───────────────────────────────────────────────────
if ($method === 'GET' && $action === 'history') {
    $res = $db->query(
        "SELECT id,type,amount_btc,balance_after,status,tx_hash,address,notes,created_at
         FROM transactions WHERE user_id={$u['id']}
         ORDER BY created_at DESC LIMIT 50"
    );
    $txs = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $txs[] = [
            'id'      => (int)$r['id'],
            'type'    => $r['type'],
            'amount'  => (float)$r['amount_btc'],
            'balance' => (float)$r['balance_after'],
            'status'  => $r['status'],
            'hash'    => $r['tx_hash'],
            'notes'   => $r['notes'],
            'ts'      => (int)$r['created_at'],
        ];
    }
    jsonOk(['transactions' => $txs]);
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($method !== 'POST') jsonError('Method not allowed', 405);
$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── Claim demo funds ──────────────────────────────────────────────────────────
if ($action === 'claim_demo') {
    $lastClaim = (int)$u['last_demo_claim'];
    $cooldown  = DEMO_COOLDOWN;
    if ((time() - $lastClaim) < $cooldown) {
        $wait = $cooldown - (time() - $lastClaim);
        jsonError("Next claim available in " . gmdate('H\h i\m', $wait));
    }
    $bonus  = 0.0005; // 0.0005 BTC per daily claim
    $newBal = round((float)$u['balance_btc'] + $bonus, 8);
    $db->exec("UPDATE users SET balance_btc=$newBal, last_demo_claim=" . time() . " WHERE id={$u['id']}");
    recordTx($db, (int)$u['id'], 'bonus', $bonus, $newBal, 'confirmed', 'Daily demo claim');
    jsonOk(['balance' => $newBal, 'claimed' => $bonus, 'message' => 'Demo funds added!']);
}

// ── Request withdrawal (simulated — queues for review) ────────────────────────
if ($action === 'withdraw') {
    $address = trim($body['address'] ?? '');
    $amount  = round((float)($body['amount_btc'] ?? 0), 8);

    if (!$address) jsonError('Withdrawal address required');
    if (strlen($address) < 26 || strlen($address) > 62)
        jsonError('Invalid BTC address');
    if ($amount < 0.001) jsonError('Minimum withdrawal: 0.001 BTC');
    if ((float)$u['balance_btc'] < $amount + 0.00002)
        jsonError('Insufficient balance (include 0.00002 BTC fee)');

    $fee    = 0.00002;
    $total  = $amount + $fee;
    $newBal = round((float)$u['balance_btc'] - $total, 8);
    $txHash = 'pending_' . bin2hex(random_bytes(8));

    $db->exec('BEGIN');
    $db->exec("UPDATE users SET balance_btc=round($newBal,8) WHERE id={$u['id']}");
    $stmt = $db->prepare(
        'INSERT INTO transactions(user_id,type,amount_btc,balance_after,status,tx_hash,address,notes)
         VALUES(?,?,?,?,?,?,?,?)'
    );
    $stmt->bindValue(1, (int)$u['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, 'withdrawal',  SQLITE3_TEXT);
    $stmt->bindValue(3, -$amount,      SQLITE3_FLOAT);
    $stmt->bindValue(4, $newBal,       SQLITE3_FLOAT);
    $stmt->bindValue(5, 'pending',     SQLITE3_TEXT);
    $stmt->bindValue(6, $txHash,       SQLITE3_TEXT);
    $stmt->bindValue(7, $address,      SQLITE3_TEXT);
    $stmt->bindValue(8, 'Withdrawal queued', SQLITE3_TEXT);
    $stmt->execute();
    $db->exec('COMMIT');

    jsonOk(['balance' => $newBal, 'status' => 'pending',
            'message' => 'Withdrawal queued — processed within 24h (demo mode)']);
}

jsonError('Unknown action');
