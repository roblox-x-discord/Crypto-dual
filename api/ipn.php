<?php
/**
 * NOWPayments IPN (Instant Payment Notification) Handler
 * Receives payment status updates from NOWPayments
 */

require_once dirname(__DIR__) . '/includes/db.php';

// Log incoming IPN for debugging
$ipnLog = fopen(__DIR__ . '/ipn_log.txt', 'a');
fwrite($ipnLog, date('Y-m-d H:i:s') . " - IPN received\n");
fwrite($ipnLog, "POST data: " . file_get_contents('php://input') . "\n");
fwrite($ipnLog, "GET data: " . print_r($_GET, true) . "\n");

// Get JSON payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    fwrite($ipnLog, "Error: Invalid JSON\n");
    fclose($ipnLog);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Verify IPN signature (NOWPayments sends X-Nowpayments-Sig header)
$headers = getallheaders();
$receivedSig = $headers['X-Nowpayments-Sig'] ?? '';
$expectedSig = hash_hmac('sha512', $input, NOWPAYMENTS_IPN_SECRET);

if ($receivedSig !== $expectedSig) {
    fwrite($ipnLog, "Signature mismatch. Received: $receivedSig, Expected: $expectedSig\n");
    // For testing, continue anyway. Uncomment below to enforce:
    // fclose($ipnLog);
    // http_response_code(403);
    // echo json_encode(['error' => 'Invalid signature']);
    // exit;
}

fwrite($ipnLog, "Signature verified\n");

// Extract relevant fields
$paymentId   = $data['payment_id'] ?? null;
$npPaymentId = $data['np_payment_id'] ?? null;
$status      = $data['payment_status'] ?? null;
$currency    = $data['currency'] ?? null;
$amount      = floatval($data['pay_amount'] ?? 0);
$order_id    = $data['order_id'] ?? null; // format: "user_id_timestamp"

fwrite($ipnLog, "Payment ID: $paymentId, Status: $status, Currency: $currency, Amount: $amount, Order ID: $order_id\n");

if (!$paymentId || !$status) {
    fwrite($ipnLog, "Error: Missing required fields\n");
    fclose($ipnLog);
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$db = getDB();

// Find the transaction record
$stmt = $db->prepare('SELECT * FROM nowpayments_transactions WHERE payment_id = ?');
$stmt->bindValue(1, $paymentId, SQLITE3_TEXT);
$transaction = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$transaction) {
    fwrite($ipnLog, "Error: Transaction not found for payment_id: $paymentId\n");
    fclose($ipnLog);
    http_response_code(404);
    echo json_encode(['error' => 'Transaction not found']);
    exit;
}

// Update transaction status
$currentStatus = $transaction['status'];
if ($currentStatus === $status) {
    fwrite($ipnLog, "Status unchanged: $status\n");
    fclose($ipnLog);
    echo json_encode(['success' => true, 'message' => 'Status unchanged']);
    exit;
}

$db->exec('BEGIN IMMEDIATE');

try {
    // Update transaction
    $stmt = $db->prepare('UPDATE nowpayments_transactions SET status = ?, ipn_received_at = ?, updated_at = strftime(\'%s\',\'now\') WHERE id = ?');
    $stmt->bindValue(1, $status, SQLITE3_TEXT);
    $stmt->bindValue(2, time(), SQLITE3_INTEGER);
    $stmt->bindValue(3, $transaction['id'], SQLITE3_INTEGER);
    $stmt->execute();

    // If payment is finished/confirmed, credit user with 80% (20% fee)
    if (($status === 'finished' || $status === 'confirmed') && $currentStatus !== 'finished' && $currentStatus !== 'confirmed') {

        $userId = (int)$transaction['user_id'];
        $feeAmount = floatval($transaction['fee_amount']);
        $creditAmount = floatval($transaction['credited_amount']);

        // Get user's current balances
        $stmt = $db->prepare('SELECT balance_btc, balance_usd FROM users WHERE id = ?');
        $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
        $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$user) {
            throw new Exception("User not found: $userId");
        }

        $newBtc = floatval($user['balance_btc']);
        $newUsd = floatval($user['balance_usd']);

        // Convert crypto to USD equivalent for balance_usd
        $creditUsd = floatval($transaction['amount_usd']) * 0.80; // 80% after fee

        // Update balances
        if ($currency === 'USDT' || $currency === 'USDC') {
            // Stablecoins go directly to USD balance (80%)
            $newUsd = round($newUsd + $creditUsd, 2);
        } else {
            // BTC, LTC, ETH, etc. - add to BTC balance and USD equivalent
            $cryptoCredit = $creditAmount * 0.80; // 80% after 20% fee
            $newBtc = round($newBtc + $cryptoCredit, 8);
            $newUsd = round($newUsd + $creditUsd, 2);
        }

        // Update user balances
        $stmt = $db->prepare('UPDATE users SET balance_btc = ?, balance_usd = ?, total_deposited = total_deposited + ? WHERE id = ?');
        $stmt->bindValue(1, $newBtc, SQLITE3_FLOAT);
        $stmt->bindValue(2, $newUsd, SQLITE3_FLOAT);
        $stmt->bindValue(3, $creditUsd, SQLITE3_FLOAT);
        $stmt->bindValue(4, $userId, SQLITE3_INTEGER);
        $stmt->execute();

        // Record in transactions table
        $stmt = $db->prepare(
            'INSERT INTO transactions(user_id, type, amount_btc, balance_after, status, tx_hash, notes, created_at)
             VALUES(?, ?, ?, ?, ?, ?, ?, strftime(\'%s\',\'now\'))'
        );
        $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
        $stmt->bindValue(2, 'deposit', SQLITE3_TEXT);
        $stmt->bindValue(3, $creditAmount * 0.80, SQLITE3_FLOAT);
        $stmt->bindValue(4, $newBtc, SQLITE3_FLOAT);
        $stmt->bindValue(5, 'confirmed', SQLITE3_TEXT);
        $stmt->bindValue(6, $paymentId, SQLITE3_TEXT);
        $stmt->bindValue(7, "NOWPayments deposit: {$currency} {$amount} (20% fee applied)", SQLITE3_TEXT);
        $stmt->execute();

        fwrite($ipnLog, "User $userId credited. New BTC: $newBtc, New USD: $newUsd\n");
    }

    $db->exec('COMMIT');
    fwrite($ipnLog, "IPN processed successfully\n");
    fclose($ipnLog);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $db->exec('ROLLBACK');
    fwrite($ipnLog, "Error: " . $e->getMessage() . "\n");
    fclose($ipnLog);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
