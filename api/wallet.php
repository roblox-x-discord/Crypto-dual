<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'address';
$method = $_SERVER['REQUEST_METHOD'];
$u      = requireAuth();
$db     = getDB();

// ── GET: supported currencies ─────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'currencies') {
    $currencies = [];
    foreach (DEPOSIT_CURRENCIES as $curr) {
        $currencies[] = [
            'code' => $curr,
            'name' => $curr === 'BTC' ? 'Bitcoin' : ($curr === 'LTC' ? 'Litecoin' : 'Ethereum'),
            'icon' => $curr === 'BTC' ? '₿' : ($curr === 'LTC' ? 'Ł' : 'Ξ'),
        ];
    }
    jsonOk(['currencies' => $currencies, 'fee_percent' => DEPOSIT_FEE * 100]);
}

// ── GET: user balance with USD ─────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'balance') {
    $balanceUsd = (float)($u['balance_usd'] ?? 0);
    $balanceBtc = (float)($u['balance_btc'] ?? 0);

    // Get current BTC price for USD conversion
    $priceRow = $db->querySingle("SELECT data FROM price_cache WHERE id=1", true);
    $btcPrice = 67000; // fallback
    if ($priceRow && $priceRow['data']) {
        $priceData = json_decode($priceRow['data'], true);
        if (isset($priceData['prices']['BTC'])) {
            $btcPrice = $priceData['prices']['BTC']['price'] ?? 67000;
        }
    }

    $totalUsd = $balanceUsd + ($balanceBtc * $btcPrice);

    jsonOk([
        'balance_btc' => $balanceBtc,
        'balance_usd' => $balanceUsd,
        'total_usd'   => round($totalUsd, 2),
        'btc_price'   => $btcPrice,
    ]);
}

// ── GET: deposit address (legacy, keeping for compatibility) ───────────────────────
if ($method === 'GET' && $action === 'address') {
    if (!$u['wallet_address']) {
        $addr = walletAddress((int)$u['id']);
        $db->exec("UPDATE users SET wallet_address='$addr' WHERE id={$u['id']}");
    } else {
        $addr = $u['wallet_address'];
    }
    jsonOk(['address' => $addr]);
}

// ── GET: CoinRemitter deposit URL ───────────────────────────────────────────────────
if ($method === 'GET' && $action === 'create_deposit') {
    $currency = strtoupper($_GET['currency'] ?? 'BTC');
    $amount   = floatval($_GET['amount'] ?? 0);

    if (!in_array($currency, DEPOSIT_CURRENCIES)) {
        jsonError("Invalid currency. Supported: " . implode(', ', DEPOSIT_CURRENCIES));
    }

    if ($amount <= 0) {
        jsonError("Amount must be greater than 0");
    }

    $apiKey = COINREMITTER_API_KEY[$currency] ?? null;
    if (!$apiKey) {
        jsonError("API key not configured for $currency");
    }

    // Get wallet address from CoinRemitter
    $walletUrl = COINREMITTER_API_URL . '/' . strtolower($currency) . '/get_wallet_address';

    $ch = curl_init($walletUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'api_key' => $apiKey,
        ]),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        jsonError("Failed to get wallet address. Please try again.");
    }

    $crResponse = json_decode($response, true);

    if (!isset($crResponse['data']['address'])) {
        jsonError("Invalid response from payment provider");
    }

    $walletAddress = $crResponse['data']['address'];

    // Generate unique payment ID
    $paymentId = 'CD_' . $u['id'] . '_' . time() . '_' . bin2hex(random_bytes(4));
    $orderId   = $u['id'] . '_' . time();

    // Calculate amounts (20% fee)
    $feeAmount = $amount * DEPOSIT_FEE;
    $creditedAmount = $amount - $feeAmount;

    // Store transaction in database
    $stmt = $db->prepare(
        'INSERT INTO nowpayments_transactions(user_id, payment_id, np_payment_id, currency, amount, amount_usd, fee_amount, credited_amount, pay_address, status)
         VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bindValue(1, (int)$u['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $paymentId, SQLITE3_TEXT);
    $stmt->bindValue(3, $orderId, SQLITE3_TEXT);
    $stmt->bindValue(4, $currency, SQLITE3_TEXT);
    $stmt->bindValue(5, $amount, SQLITE3_FLOAT);
    $stmt->bindValue(6, $amount, SQLITE3_FLOAT); // For crypto, USD value is approx same
    $stmt->bindValue(7, $feeAmount, SQLITE3_FLOAT);
    $stmt->bindValue(8, $creditedAmount, SQLITE3_FLOAT);
    $stmt->bindValue(9, $walletAddress, SQLITE3_TEXT);
    $stmt->bindValue(10, 'pending', SQLITE3_TEXT);
    $stmt->execute();

    jsonOk([
        'payment_id'    => $paymentId,
        'np_payment_id' => $orderId,
        'pay_address'   => $walletAddress,
        'amount'        => $amount,
        'currency'      => $currency,
        'fee_percent'   => DEPOSIT_FEE * 100,
        'you_receive'   => round($creditedAmount, 8),
    ]);
}

// ── GET: active deposits ───────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'deposits') {
    $res = $db->query(
        "SELECT * FROM nowpayments_transactions
         WHERE user_id={$u['id']} AND status IN ('pending','waiting','confirming')
         ORDER BY created_at DESC LIMIT 10"
    );
    $deposits = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $deposits[] = [
            'id'         => (int)$r['id'],
            'payment_id' => $r['payment_id'],
            'currency'   => $r['currency'],
            'amount'     => (float)$r['amount'],
            'fee_amount' => (float)$r['fee_amount'],
            'receive'    => (float)$r['credited_amount'],
            'status'     => $r['status'],
            'address'    => $r['pay_address'],
            'created_at' => (int)$r['created_at'],
        ];
    }
    jsonOk(['deposits' => $deposits]);
}

// ── GET: transaction history ───────────────────────────────────────────────────
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

// ── Request withdrawal (simulated — queues for review) ────────────────────────
if ($action === 'withdraw') {
    $address = trim($body['address'] ?? '');
    $amount  = round((float)($body['amount_btc'] ?? 0), 8);

    if (!$address) jsonError('Withdrawal address required');
    if (strlen($address) < 26 || strlen($address) > 62)
        jsonError('Invalid address format');
    if ($amount < 0.001) jsonError('Minimum withdrawal: 0.001 BTC');
    if ((float)$u['balance_btc'] < $amount + 0.00002)
        jsonError('Insufficient balance (include 0.00002 BTC fee)');

    $fee    = 0.00002;
    $total  = $amount + $fee;
    $newBal = round((float)$u['balance_btc'] - $total, 8);
    $txHash = 'pending_' . bin2hex(random_bytes(8));

    $db->exec('BEGIN IMMEDIATE');
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
    $stmt->bindValue(8, 'Withdrawal queued (manual processing)', SQLITE3_TEXT);
    $stmt->execute();
    $db->exec('COMMIT');

    jsonOk(['balance_btc' => $newBal, 'balance_usd' => (float)($u['balance_usd'] ?? 0), 'status' => 'pending',
            'message' => 'Withdrawal queued — processed within 24h']);
}

jsonError('Unknown action');
