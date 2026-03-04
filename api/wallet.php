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
            'name' => $curr === 'BTC' ? 'Bitcoin' : 'Litecoin',
            'icon' => $curr === 'BTC' ? '₿' : 'Ł',
            'address' => $curr === 'BTC' ? DEPOSIT_ADDRESS_BTC : DEPOSIT_ADDRESS_LTC,
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

// ── GET: deposit address ─────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'address') {
    $currency = strtoupper($_GET['currency'] ?? 'BTC');

    if ($currency === 'BTC') {
        jsonOk(['address' => DEPOSIT_ADDRESS_BTC]);
    } elseif ($currency === 'LTC') {
        jsonOk(['address' => DEPOSIT_ADDRESS_LTC]);
    } else {
        jsonError('Invalid currency');
    }
}

// ── POST: Verify transaction from BlockCypher ───────────────────────────────────────
if ($method === 'POST' && $action === 'verify_transaction') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $txid = trim($body['txid'] ?? '');
    $currency = strtoupper($body['currency'] ?? 'BTC');

    if (!$txid) {
        jsonError('Transaction ID is required');
    }

    if (!in_array($currency, ['BTC', 'LTC'])) {
        jsonError('Invalid currency');
    }

    $depositAddress = $currency === 'BTC' ? DEPOSIT_ADDRESS_BTC : DEPOSIT_ADDRESS_LTC;
    $blockCypherCurrency = strtolower($currency); // btc or ltc

    // Call BlockCypher API to get transaction details
    $apiUrl = "https://api.blockcypher.com/v1/{$blockCypherCurrency}/main/txs/{$txid}?token=" . BLOCKCYPHER_API_TOKEN;

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        jsonError('Failed to fetch transaction details. Please verify the TXID.');
    }

    $txData = json_decode($response, true);

    if (!isset($txData['outputs'])) {
        jsonError('Invalid transaction data from BlockCypher');
    }

    // Check if any output matches our deposit address
    $matchedOutput = null;
    $totalValue = 0;

    foreach ($txData['outputs'] as $output) {
        if (isset($output['addresses']) && in_array($depositAddress, $output['addresses'])) {
            $matchedOutput = $output;
            $totalValue += (int)($output['value'] ?? 0); // Value in satoshis
        }
    }

    if ($matchedOutput === null) {
        jsonError('This transaction did not send funds to the deposit address.');
    }

    // Check if transaction is already confirmed
    $confirmations = (int)($txData['confirmations'] ?? 0);
    if ($confirmations < 1) {
        jsonError('Transaction needs at least 1 confirmation. Current: ' . $confirmations);
    }

    // Convert satoshis to BTC/LTC
    $cryptoAmount = $totalValue / 100000000; // 100 million satoshis in 1 BTC/LTC

    // Calculate amounts (80% to user, 20% fee)
    $feeAmount = $cryptoAmount * DEPOSIT_FEE;
    $creditedAmount = $cryptoAmount - $feeAmount;

    // Check if this TXID was already processed
    $stmt = $db->prepare('SELECT id FROM transactions WHERE tx_hash = ?');
    $stmt->bindValue(1, $txid, SQLITE3_TEXT);
    $existing = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($existing) {
        jsonError('This transaction has already been processed.');
    }

    $db->exec('BEGIN IMMEDIATE');

    // Credit user
    $newBtc = (float)$u['balance_btc'];
    $newUsd = (float)$u['balance_usd'];

    // Get current price for USD conversion
    $priceRow = $db->querySingle("SELECT data FROM price_cache WHERE id=1", true);
    $btcPrice = 67000;
    if ($priceRow && $priceRow['data']) {
        $priceData = json_decode($priceRow['data'], true);
        if (isset($priceData['prices']['BTC'])) {
            $btcPrice = $priceData['prices']['BTC']['price'] ?? 67000;
        }
    }

    if ($currency === 'BTC') {
        $newBtc = round($newBtc + $creditedAmount, 8);
        $newUsd = round($newUsd + ($creditedAmount * $btcPrice), 2);
    } else {
        // For LTC, approximate price
        $ltcPrice = 70;
        $newUsd = round($newUsd + ($creditedAmount * $ltcPrice), 2);
    }

    // Update user balance
    $stmt = $db->prepare('UPDATE users SET balance_btc = ?, balance_usd = ?, total_deposited = total_deposited + ? WHERE id = ?');
    $stmt->bindValue(1, $newBtc, SQLITE3_FLOAT);
    $stmt->bindValue(2, $newUsd, SQLITE3_FLOAT);
    $stmt->bindValue(3, $cryptoAmount, SQLITE3_FLOAT);
    $stmt->bindValue(4, (int)$u['id'], SQLITE3_INTEGER);
    $stmt->execute();

    // Record transaction
    $stmt = $db->prepare(
        'INSERT INTO transactions(user_id, type, amount_btc, balance_after, status, tx_hash, notes, created_at)
         VALUES(?, ?, ?, ?, ?, ?, ?, strftime(\'%s\',\'now\'))'
    );
    $stmt->bindValue(1, (int)$u['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, 'deposit', SQLITE3_TEXT);
    $stmt->bindValue(3, $creditedAmount, SQLITE3_FLOAT);
    $stmt->bindValue(4, $newBtc, SQLITE3_FLOAT);
    $stmt->bindValue(5, 'confirmed', SQLITE3_TEXT);
    $stmt->bindValue(6, $txid, SQLITE3_TEXT);
    $stmt->bindValue(7, "Direct deposit: {$currency} {$cryptoAmount} (20% fee applied via TX {$txid})", SQLITE3_TEXT);
    $stmt->execute();

    $db->exec('COMMIT');

    // Send Discord notification
    sendDiscordDepositNotification($u['username'], $currency, $cryptoAmount, $creditedAmount, $txid, $confirmations);

    jsonOk([
        'balance_btc' => $newBtc,
        'balance_usd' => $newUsd,
        'original_amount' => round($cryptoAmount, 8),
        'credited_amount' => round($creditedAmount, 8),
        'fee_amount' => round($feeAmount, 8),
        'message' => "Deposit verified and credited! You received {$creditedAmount} {$currency} (80% after 20% fee)."
    ]);
}

// ── GET: transaction history ───────────────────────────────────────────────────────
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

// ── POST ──────────────────────────────────────────────────────────────────────────
if ($method !== 'POST') jsonError('Method not allowed', 405);
$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── Request withdrawal (simulated — queues for review) ────────────────────────────
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

/**
 * Send Discord notification for successful deposit
 */
function sendDiscordDepositNotification($username, $currency, $originalAmount, $creditedAmount, $txid, $confirmations) {
    $embed = [
        'title' => '💰 New Deposit Confirmed!',
        'description' => sprintf(
            "User **%s** has deposited **%s**!\n\n" .
            "**Original Amount:** %s %s\n" .
            "**Credited (80%%):** %s %s\n" .
            "**Platform Fee (20%%):** %s %s\n" .
            "**Confirmations:** %d",
            $username,
            $currency,
            number_format($originalAmount, 8),
            $currency,
            number_format($creditedAmount, 8),
            $currency,
            number_format($originalAmount - $creditedAmount, 8),
            $currency,
            $confirmations
        ),
        'color' => 0x00ff00, // Green
        'fields' => [
            [
                'name' => '🔗 Transaction ID',
                'value' => sprintf('[View on Block Explorer](https://blockstream.info/tx/%s)', $txid),
                'inline' => false
            ],
            [
                'name' => '🕐 Confirmed At',
                'value' => date('Y-m-d H:i:s'),
                'inline' => true
            ],
        ],
        'footer' => [
            'text' => 'CryptoDuel Direct Deposit',
        ],
        'timestamp' => date('c'),
    ];

    $payload = [
        'embeds' => [$embed],
        'username' => 'CryptoDuel Deposits',
    ];

    $ch = curl_init(DISCORD_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    curl_exec($ch);
    curl_close($ch);
}
