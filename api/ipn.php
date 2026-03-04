<?php
/**
 * CoinRemitter IPN (Instant Payment Notification) Handler
 * Receives payment status updates from CoinRemitter
 */

require_once dirname(__DIR__) . '/includes/db.php';

// Log incoming IPN for debugging
$ipnLog = fopen(__DIR__ . '/ipn_log.txt', 'a');
fwrite($ipnLog, date('Y-m-d H:i:s') . " - CoinRemitter IPN received\n");
fwrite($ipnLog, "POST data: " . file_get_contents('php://input') . "\n");
fwrite($ipnLog, "GET data: " . print_r($_GET, true) . "\n");

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
$status    = $data['status'] ?? null;
$address   = $data['address'] ?? null;
$currency  = strtoupper($data['coin'] ?? $data['currency'] ?? '');
$txid      = $data['txid'] ?? null;

// Map CoinRemitter statuses to our system
$statusMap = [
    'confirmed' => 'confirmed',
    'pending' => 'pending',
    'complete' => 'confirmed',
    'finished' => 'confirmed',
];

$mappedStatus = $statusMap[$status] ?? ($status === 'confirmed' || $status === 'complete' || $status === 'finished' ? 'confirmed' : 'pending');

fwrite($ipnLog, "Invoice ID: $invoiceId, Status: $status ($mappedStatus), Currency: $currency, Amount: $amount, Address: $address, TXID: $txid\n");

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
    fwrite($ipnLog, "Error: Transaction not found for address: $address\n");
    fclose($ipnLog);
    http_response_code(404);
    echo json_encode(['error' => 'Transaction not found']);
    exit;
}

// Update transaction status
$currentStatus = $transaction['status'];

// Only proceed if status changed to confirmed
if ($currentStatus === 'confirmed' || $currentStatus === 'finished') {
    fwrite($ipnLog, "Already processed: $currentStatus\n");
    fclose($ipnLog);
    echo json_encode(['success' => true, 'message' => 'Already processed']);
    exit;
}

if ($mappedStatus !== 'confirmed') {
    fwrite($ipnLog, "Payment not confirmed yet: $mappedStatus\n");
    fclose($ipnLog);
    echo json_encode(['success' => true, 'message' => 'Payment pending']);
    exit;
}

$db->exec('BEGIN IMMEDIATE');

try {
    // Update transaction
    $stmt = $db->prepare('UPDATE nowpayments_transactions SET status = ?, ipn_received_at = ?, updated_at = strftime(\'%s\',\'now\') WHERE id = ?');
    $stmt->bindValue(1, 'confirmed', SQLITE3_TEXT);
    $stmt->bindValue(2, time(), SQLITE3_INTEGER);
    $stmt->bindValue(3, $transaction['id'], SQLITE3_INTEGER);
    $stmt->execute();

    $userId = (int)$transaction['user_id'];
    $currency = $transaction['currency'];
    $creditAmount = floatval($transaction['credited_amount']); // Already calculated with 20% fee removed

    // Get user's current balances
    $stmt = $db->prepare('SELECT balance_btc, balance_usd FROM users WHERE id = ?');
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        throw new Exception("User not found: $userId");
    }

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
        // For LTC, we need to get LTC price or convert approximate
        // For simplicity, we'll add to USD balance based on estimated LTC price (~$70)
        $ltcPrice = 70; // Approximate LTC price
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
    $stmt->bindValue(6, $txid ?? $transaction['payment_id'], SQLITE3_TEXT);
    $stmt->bindValue(7, "CoinRemitter deposit: {$currency} {$amount} (20% fee applied)", SQLITE3_TEXT);
    $stmt->execute();

    fwrite($ipnLog, "User $userId credited. New BTC: $newBtc, New USD: $newUsd\n");

    $db->exec('COMMIT');
    fwrite($ipnLog, "CoinRemitter IPN processed successfully\n");
    fclose($ipnLog);

    echo json_encode(['success' => true, 'message' => 'Payment processed']);

} catch (Exception $e) {
    $db->exec('ROLLBACK');
    fwrite($ipnLog, "Error: " . $e->getMessage() . "\n");
    fwrite($ipnLog, "Stack trace: " . $e->getTraceAsString() . "\n");
    fclose($ipnLog);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
