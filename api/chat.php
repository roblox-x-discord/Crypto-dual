<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';
$method = $_SERVER['REQUEST_METHOD'];

// ── GET messages ──────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'get') {
    $since = (int)($_GET['since_id'] ?? 0);
    $db    = getDB();
    $res   = $db->query(
        "SELECT m.id, m.message, m.msg_type, m.created_at,
                u.username, u.level, u.id AS user_id
         FROM chat_messages m JOIN users u ON m.user_id=u.id
         WHERE m.id > $since
         ORDER BY m.created_at DESC LIMIT " . CHAT_LIMIT
    );
    $msgs = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $msgs[] = [
            'id'       => (int)$r['id'],
            'user_id'  => (int)$r['user_id'],
            'username' => $r['username'],
            'level'    => (int)$r['level'],
            'message'  => $r['message'],
            'type'     => $r['msg_type'],
            'ts'       => (int)$r['created_at'],
        ];
    }
    // Return in ascending order for display
    jsonOk(['messages' => array_reverse($msgs)]);
}

// ── GET online count ──────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'online') {
    $db      = getDB();
    $cutoff  = time() - 300; // last 5 min
    $count   = (int)$db->querySingle("SELECT COUNT(*) FROM users WHERE last_seen > $cutoff");
    jsonOk(['online' => max(1, $count)]);
}

// ── POST: send message ────────────────────────────────────────────────────────
if ($method !== 'POST') jsonError('Method not allowed', 405);
$u    = requireAuth();
$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($action === 'send') {
    $msg  = trim($body['message'] ?? '');
    $type = 'normal';

    if (strlen($msg) < 1)   jsonError('Message cannot be empty');
    if (strlen($msg) > 200) jsonError('Message too long (200 chars max)');

    // Basic rate limit: 1 message per 2 seconds per user (check last message time)
    $db      = getDB();
    $lastTs  = (int)$db->querySingle(
        "SELECT created_at FROM chat_messages WHERE user_id={$u['id']} ORDER BY id DESC LIMIT 1"
    );
    if ($lastTs && (time() - $lastTs) < 2) jsonError('Slow down — 1 message per 2 seconds');

    // Sanitize
    $msg = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $stmt = $db->prepare(
        'INSERT INTO chat_messages(user_id,message,msg_type) VALUES(?,?,?)'
    );
    $stmt->bindValue(1, (int)$u['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $msg,          SQLITE3_TEXT);
    $stmt->bindValue(3, $type,         SQLITE3_TEXT);
    $stmt->execute();

    $msgId = $db->lastInsertRowID();
    jsonOk([
        'message' => [
            'id'       => $msgId,
            'user_id'  => (int)$u['id'],
            'username' => $u['username'],
            'level'    => (int)$u['level'],
            'message'  => $msg,
            'type'     => $type,
            'ts'       => time(),
        ]
    ]);
}

// ── Rain ──────────────────────────────────────────────────────────────────────
if ($action === 'rain') {
    $db      = getDB();
    $balance = (float)$u['balance_btc'];
    $total   = RAIN_AMOUNT * 10; // rain on 10 users

    if ($balance < $total) jsonError('Insufficient balance for rain (need ' . $total . ' BTC)');

    // Get 10 random recently active users (excluding self)
    $cutoff   = time() - 3600;
    $res      = $db->query(
        "SELECT id FROM users WHERE id!={$u['id']} AND last_seen>$cutoff ORDER BY RANDOM() LIMIT 10"
    );
    $targets = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $targets[] = (int)$r['id'];

    if (count($targets) === 0) jsonError('No active users to rain on');

    $perUser = round($total / count($targets), 8);
    $db->exec('BEGIN');
    $db->exec("UPDATE users SET balance_btc=round(balance_btc-$total,8) WHERE id={$u['id']}");
    foreach ($targets as $tid) {
        $db->exec("UPDATE users SET balance_btc=round(balance_btc+$perUser,8) WHERE id=$tid");
    }

    $rainMsg = "🌧️ " . $u['username'] . " rained " . number_format($total, 4) . " BTC on " . count($targets) . " users!";
    $rMsg = htmlspecialchars($rainMsg, ENT_QUOTES, 'UTF-8');
    $stmt = $db->prepare('INSERT INTO chat_messages(user_id,message,msg_type) VALUES(?,?,?)');
    $stmt->bindValue(1, (int)$u['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $rMsg,         SQLITE3_TEXT);
    $stmt->bindValue(3, 'rain',        SQLITE3_TEXT);
    $stmt->execute();
    $db->exec('COMMIT');

    $newBal = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id={$u['id']}");
    jsonOk(['balance' => $newBal, 'recipients' => count($targets)]);
}

jsonError('Unknown action');
