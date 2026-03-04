<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET: me ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'me') {
        if (empty($_SESSION['user_id'])) { jsonOk(['user' => null]); }
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE id=?');
        $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
        $u = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$u) { session_destroy(); jsonOk(['user' => null]); }
        $db->exec("UPDATE users SET last_seen=strftime('%s','now') WHERE id={$u['id']}");
        jsonOk(['user' => publicUser($u)]);
    }
    jsonError('Unknown action');
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($action) {

    // ── REGISTER ──────────────────────────────────────────────────────────
    case 'register': {
        $username = trim($body['username'] ?? '');
        $email    = strtolower(trim($body['email'] ?? ''));
        $pass     = $body['password'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 24)
            jsonError('Username must be 3-24 characters');
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username))
            jsonError('Username may only contain letters, numbers, _ and -');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            jsonError('Invalid email address');
        if (strlen($pass) < 6)
            jsonError('Password must be at least 6 characters');

        $db   = getDB();
        $hash = password_hash($pass, PASSWORD_ARGON2ID);
        $addr = '';  // will be set after insert

        $db->exec('BEGIN');
        try {
            $stmt = $db->prepare(
                'INSERT INTO users(username,email,password_hash,balance_btc,last_demo_claim)
                 VALUES(?,?,?,?,0)'
            );
            $stmt->bindValue(1, $username, SQLITE3_TEXT);
            $stmt->bindValue(2, $email,    SQLITE3_TEXT);
            $stmt->bindValue(3, $hash,     SQLITE3_TEXT);
            $stmt->bindValue(4, (float)WELCOME_BTC, SQLITE3_FLOAT);
            $stmt->execute();

            $userId = $db->lastInsertRowID();
            $addr   = walletAddress($userId);

            $upd = $db->prepare('UPDATE users SET wallet_address=? WHERE id=?');
            $upd->bindValue(1, $addr,   SQLITE3_TEXT);
            $upd->bindValue(2, $userId, SQLITE3_INTEGER);
            $upd->execute();

            // Welcome bonus transaction
            recordTx($db, $userId, 'bonus', WELCOME_BTC, WELCOME_BTC, 'confirmed', 'Welcome bonus');

            $db->exec('COMMIT');
        } catch (Exception $e) {
            $db->exec('ROLLBACK');
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                jsonError('Username or email already taken');
            }
            jsonError('Registration failed. Please try again.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;

        $u = $db->querySingle("SELECT * FROM users WHERE id=$userId", true);
        jsonOk(['user' => publicUser($u), 'message' => 'Welcome to CryptoDuel!']);
    }

    // ── LOGIN ─────────────────────────────────────────────────────────────
    case 'login': {
        $ident = trim($body['username'] ?? '');
        $pass  = $body['password'] ?? '';

        if (!$ident || !$pass) jsonError('Username and password required');

        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT * FROM users WHERE username=? OR email=? LIMIT 1'
        );
        $stmt->bindValue(1, $ident, SQLITE3_TEXT);
        $stmt->bindValue(2, strtolower($ident), SQLITE3_TEXT);
        $u = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$u || !password_verify($pass, $u['password_hash']))
            jsonError('Invalid credentials');

        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id'];
        $db->exec("UPDATE users SET last_seen=strftime('%s','now') WHERE id={$u['id']}");

        jsonOk(['user' => publicUser($u)]);
    }

    // ── LOGOUT ────────────────────────────────────────────────────────────
    case 'logout': {
        $_SESSION = [];
        session_destroy();
        jsonOk(['message' => 'Logged out']);
    }

    default:
        jsonError('Unknown action');
}
