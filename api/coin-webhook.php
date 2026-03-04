<?php
/**
 * CoinRemitter Webhook Endpoint
 * Receives payment notifications from CoinRemitter and sends Discord notifications
 */

require_once dirname(__DIR__) . '/includes/db.php';

// Discord Webhook URL
define('DISCORD_WEBHOOK_URL', 'https://discord.com/api/webhooks/1478752146806673491/joLkgboLZJW9NlFFJ9zkU4duxBOZJrDluHs2ZGEvfLeyEulGJWoriAnI1Y0TteNVToyQ');

// Log incoming webhook for debugging
$ipnLog = fopen(__DIR__ . '/coin_webhook_log.txt', 'a');
fwrite($ipnLog, date('Y-m-d H:i:s') . " - CoinRemitter Webhook received\n");
fwrite($ipnLog, "POST data: " . file_get_contents('php://input') . "\n");

// Get JSON payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    // Try POST array if JSON fails
    $data = $_POST;
    fwrite($ipnLog, "Using POST array: " . print_r($data, true) . "\n");
}

if (!$data) {
    fwrite($ipnLog, "Error: No data received\n");
    fclose($ipnLog);
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
    exit;
}

// Extract CoinRemitter webhook fields
$invoiceId = $data['invoice_id'] ?? null;
$amount    = floatval($data['amount'] ?? 0);
$status    = strtolower($data['status'] ?? $data['payment_status'] ?? '');
$address   = $data['address'] ?? null;
$currency  = strtoupper($data['coin'] ?? $data['currency'] ?? '');
$txid      = $data['txid'] ?? null;
$trxId     = $data['trx_id'] ?? null;
$createdAt = $data['created_at'] ?? null;

fwrite($ipnLog, "Invoice ID: $invoiceId, Status: $status, Currency: $currency, Amount: $amount, Address: $address, TXID: $txid, TRX_ID: $trxId\n");

// Send Discord notification for all activity first
sendDiscordNotification($data, 'received');

// Check if payment is confirmed or paid
if (!in_array($status, ['paid', 'confirmed', 'complete', 'finished'])) {
    fwrite($ipnLog, "Payment not confirmed/paid yet: $status\n");
    fclose($ipnLog);
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Payment pending']);
    exit;
}

if (!$address && !$invoiceId) {
    fwrite($ipnLog, "Error: Missing address or invoice_id\n");
    fclose($ipnLog);
    http_response_code(400);
    echo json_encode(['error' => 'Missing address or invoice_id']);
    exit;
}

$db = getDB();

// Find the transaction record by address
$stmt = $db->prepare('SELECT * FROM nowpayments_transactions WHERE pay_address = ? ORDER BY created_at DESC LIMIT 1');
$stmt->bindValue(1, $address, SQLITE3_TEXT);
$transaction = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$transaction) {
    // Try to find by payment_id if invoice_id exists
    if ($invoiceId) {
        $stmt = $db->prepare('SELECT * FROM nowpayments_transactions WHERE payment_id = ? OR np_payment_id = ?');
        $stmt->bindValue(1, $invoiceId, SQLITE3_TEXT);
        $stmt->bindValue(2, $invoiceId, SQLITE3_TEXT);
        $transaction = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    }

    if (!$transaction) {
        fwrite($ipnLog, "Error: Transaction not found for address: $address or invoice_id: $invoiceId\n");
        sendDiscordNotification($data, 'error', 'Transaction not found in database');
        fclose($ipnLog);
        http_response_code(404);
        echo json_encode(['error' => 'Transaction not found']);
        exit;
    }
}

// Check if already processed
$currentStatus = $transaction['status'];
if ($currentStatus === 'confirmed' || $currentStatus === 'finished') {
    fwrite($ipnLog, "Already processed: $currentStatus\n");
    fclose($ipnLog);
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Already processed']);
    exit;
}

$db->exec('BEGIN IMMEDIATE');

try {
    // Update transaction status
    $stmt = $db->prepare('UPDATE nowpayments_transactions SET status = ?, ipn_received_at = ?, updated_at = strftime(\'%s\',\'now\') WHERE id = ?');
    $stmt->bindValue(1, 'confirmed', SQLITE3_TEXT);
    $stmt->bindValue(2, time(), SQLITE3_INTEGER);
    $stmt->bindValue(3, $transaction['id'], SQLITE3_INTEGER);
    $stmt->execute();

    $userId = (int)$transaction['user_id'];
    $currency = $transaction['currency'];
    $originalAmount = floatval($transaction['amount']);
    $creditAmount = floatval($transaction['credited_amount']); // Already 80% (20% fee applied)

    // Get user info
    $stmt = $db->prepare('SELECT username, balance_btc, balance_usd FROM users WHERE id = ?');
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        throw new Exception("User not found: $userId");
    }

    $username = $user['username'];
    $newBtc = floatval($user['balance_btc']);
    $newUsd = floatval($user['balance_usd']);

    // Get current price for USD conversion
    $priceRow = $db->querySingle("SELECT data FROM price_cache WHERE id=1", true);
    $btcPrice = 67000;
    if ($priceRow && $priceRow['data']) {
        $priceData = json_decode($priceRow['data'], true);
        if (isset($priceData['prices']['BTC'])) {
            $btcPrice = $priceData['prices']['BTC']['price'] ?? 67000;
        }
    }

    // Credit based on currency
    if ($currency === 'BTC') {
        $newBtc = round($newBtc + $creditAmount, 8);
        $newUsd = round($newUsd + ($creditAmount * $btcPrice), 2);
    } elseif ($currency === 'LTC') {
        // For LTC, approximate price at $70
        $ltcPrice = 70;
        $usdValue = $creditAmount * $ltcPrice;
        $newUsd = round($newUsd + $usdValue, 2);
    } else {
        $newUsd = round($newUsd + ($creditAmount * $btcPrice), 2);
    }

    // Update user balances
    $stmt = $db->prepare('UPDATE users SET balance_btc = ?, balance_usd = ?, total_deposited = total_deposited + ? WHERE id = ?');
    $stmt->bindValue(1, $newBtc, SQLITE3_FLOAT);
    $stmt->bindValue(2, $newUsd, SQLITE3_FLOAT);
    $stmt->bindValue(3, $creditAmount, SQLITE3_FLOAT);
    $stmt->bindValue(4, $userId, SQLITE3_INTEGER);
    $stmt->execute();

    // Record in transactions table
    $stmt = $db->prepare(
        'INSERT INTO transactions(user_id, type, amount_btc, balance_after, status, tx_hash, notes, created_at)
         VALUES(?, ?, ?, ?, ?, ?, ?, strftime(\'%s\',\'now\'))'
    );
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(2, 'deposit', SQLITE3_TEXT);
    $stmt->bindValue(3, $creditAmount, SQLITE3_FLOAT);
    $stmt->bindValue(4, $newBtc, SQLITE3_FLOAT);
    $stmt->bindValue(5, 'confirmed', SQLITE3_TEXT);
    $stmt->bindValue(6, $txid ?? $trxId ?? $transaction['payment_id'], SQLITE3_TEXT);
    $stmt->bindValue(7, "CoinRemitter deposit: {$currency} {$originalAmount} (20% fee applied)", SQLITE3_TEXT);
    $stmt->execute();

    fwrite($ipnLog, "User $username ($userId) credited. Original: {$originalAmount} {$currency}, Credited: {$creditAmount} {$currency}, New BTC: $newBtc, New USD: $newUsd\n");

    $db->exec('COMMIT');

    // Send success notification to Discord
    sendDiscordNotification($data, 'success', [
        'username' => $username,
        'user_id' => $userId,
        'original_amount' => $originalAmount,
        'credited_amount' => $creditAmount,
        'new_balance_btc' => $newBtc,
        'new_balance_usd' => $newUsd,
    ]);

    fwrite($ipnLog, "CoinRemitter webhook processed successfully\n");
    fclose($ipnLog);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);

} catch (Exception $e) {
    $db->exec('ROLLBACK');
    fwrite($ipnLog, "Error: " . $e->getMessage() . "\n");
    fwrite($ipnLog, "Stack trace: " . $e->getTraceAsString() . "\n");
    sendDiscordNotification($data, 'error', ['error_message' => $e->getMessage()]);
    fclose($ipnLog);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Send notification to Discord webhook
 */
function sendDiscordNotification($data, $type, $extraData = []) {
    $invoiceId = $data['invoice_id'] ?? $data['trx_id'] ?? 'N/A';
    $amount = $data['amount'] ?? '0';
    $currency = strtoupper($data['coin'] ?? $data['currency'] ?? '???');
    $status = strtoupper($data['status'] ?? $data['payment_status'] ?? 'unknown');
    $address = $data['address'] ?? 'N/A';
    $txid = $data['txid'] ?? $data['trx_id'] ?? 'N/A';
    $createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');

    // Determine color and title based on type
    $color = 0x00ff00; // Default green
    $title = "💰 Payment Received!";
    $description = "A new payment has been processed successfully.";

    if ($type === 'error') {
        $color = 0xff0000; // Red
        $title = "❌ Payment Error";
        $description = "An error occurred while processing the payment.";
        if (isset($extraData['error_message'])) {
            $description .= "\n**Error:** " . $extraData['error_message'];
        }
    } elseif ($type === 'received') {
        $color = 0xffff00; // Yellow
        $title = "📥 Payment Webhook Received";
        $description = "A payment webhook has been received from CoinRemitter.";
    } elseif ($type === 'success') {
        $color = 0x00ff00; // Green
        $title = "✅ Payment Confirmed & Credited";
        $description = "Payment has been confirmed and user has been credited.";

        // Add user info for success
        if (isset($extraData['username'])) {
            $description .= sprintf(
                "\n\n**User:** %s (ID: %d)" .
                "\n**Original Amount:** %s %s" .
                "\n**Credited Amount (80%%):** %s %s" .
                "\n**New BTC Balance:** %s BTC" .
                "\n**New USD Balance:** $%s",
                $extraData['username'],
                $extraData['user_id'],
                number_format($extraData['original_amount'], 8),
                $currency,
                number_format($extraData['credited_amount'], 8),
                $currency,
                number_format($extraData['new_balance_btc'], 8),
                number_format($extraData['new_balance_usd'], 2)
            );
        }
    }

    // Build Discord embed
    $embed = [
        'title' => $title,
        'description' => $description,
        'color' => $color,
        'fields' => [
            [
                'name' => '💳 Payment ID',
                'value' => '`' . $invoiceId . '`',
                'inline' => true
            ],
            [
                'name' => '💰 Amount',
                'value' => sprintf('**%s** %s', number_format(floatval($amount), 8), $currency),
                'inline' => true
            ],
            [
                'name' => '✅ Status',
                'value' => '**' . $status . '**',
                'inline' => true
            ],
            [
                'name' => '📍 Deposit Address',
                'value' => '`' . substr($address, 0, 20) . '...`',
                'inline' => false
            ],
        ],
        'footer' => [
            'text' => 'CryptoDuel • CoinRemitter',
            'icon_url' => 'https://coinremitter.com/favicon.ico'
        ],
        'timestamp' => date('c'),
    ];

    // Add transaction ID if available
    if ($txid !== 'N/A' && $txid) {
        $embed['fields'][] = [
            'name' => '🔗 Transaction ID',
            'value' => '`' . $txid . '`',
            'inline' => false
        ];
    }

    // Add received time
    $embed['fields'][] = [
        'name' => '🕐 Received At',
        'value' => '`' . $createdAt . '`',
        'inline' => true
    ];

    $payload = [
        'embeds' => [$embed],
        'username' => 'CryptoDuel Payments',
        'avatar_url' => 'https://i.imgur.com/AfFp7pu.png' // Generic crypto icon
    ];

    // Send to Discord
    $ch = curl_init(DISCORD_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log Discord response
    global $ipnLog;
    if ($ipnLog) {
        fwrite($ipnLog, "Discord webhook response: HTTP $httpCode\n");
        if ($httpCode !== 200 && $httpCode !== 204) {
            fwrite($ipnLog, "Discord error: $response\n");
        }
    }

    return $httpCode === 200 || $httpCode === 204;
}
