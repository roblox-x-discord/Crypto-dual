<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

// ── Win-check helper ──────────────────────────────────────────────────────────
function tttWinner(string $b): ?string {
    static $lines = [[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];
    foreach ($lines as [$a,$c,$d]) {
        if ($b[$a] !== '-' && $b[$a] === $b[$c] && $b[$c] === $b[$d]) return $b[$a];
    }
    return strpos($b, '-') === false ? 'draw' : null;
}

// ── Resolve payout ────────────────────────────────────────────────────────────
function tttPayout(SQLite3 $db, array $game, int $winnerId, bool $isDraw = false): void {
    $pot    = $game['amount_btc'] * 2;
    $creatorId = (int)$game['creator_id'];
    $joinerId  = (int)$game['joiner_id'];

    if ($isDraw) {
        // Refund both minus tiny fee
        $each = round($game['amount_btc'], 8);
        $cBal = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id=$creatorId");
        $jBal = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id=$joinerId");
        $db->exec("UPDATE users SET balance_btc=round(" . ($cBal + $each) . ",8) WHERE id=$creatorId");
        $db->exec("UPDATE users SET balance_btc=round(" . ($jBal + $each) . ",8) WHERE id=$joinerId");
        recordTx($db, $creatorId, 'bet_draw', $each, $cBal + $each, 'confirmed', 'TTT Draw refund');
        recordTx($db, $joinerId,  'bet_draw', $each, $jBal + $each, 'confirmed', 'TTT Draw refund');
    } else {
        $payout  = round($pot * (1 - HOUSE_EDGE), 8);
        $loserId = $winnerId === $creatorId ? $joinerId : $creatorId;
        $wBal    = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id=$winnerId");
        $newBal  = $wBal + $payout;
        $db->exec("UPDATE users SET balance_btc=round($newBal,8), wins=wins+1, total_won=total_won+$payout WHERE id=$winnerId");
        $db->exec("UPDATE users SET losses=losses+1 WHERE id=$loserId");
        recordTx($db, $winnerId, 'bet_won', $payout, $newBal, 'confirmed', 'TTT win payout');
    }
    // Update wagered + levels
    foreach ([$creatorId, $joinerId] as $uid) {
        $wagered = (float)$db->querySingle("SELECT total_wagered FROM users WHERE id=$uid");
        $wagered += $game['amount_btc'];
        $lv = calculateLevel($wagered);
        $db->exec("UPDATE users SET total_wagered=$wagered, level=$lv WHERE id=$uid");
    }
}

// ── GET: list waiting games ───────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $db  = getDB();
    $res = $db->query(
        'SELECT t.*, u.username, u.level
         FROM tictactoe t JOIN users u ON t.creator_id=u.id
         WHERE t.status="waiting"
         ORDER BY t.created_at DESC LIMIT 20'
    );
    $games = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $games[] = [
            'id'         => (int)$r['id'],
            'creator'    => $r['username'],
            'creator_id' => (int)$r['creator_id'],
            'level'      => (int)$r['level'],
            'amount_btc' => (float)$r['amount_btc'],
            'created_at' => (int)$r['created_at'],
        ];
    }
    jsonOk(['games' => $games]);
}

// ── GET: game state ───────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'state') {
    $gameId = (int)($_GET['game_id'] ?? 0);
    if (!$gameId) jsonError('Invalid game ID');
    $db   = getDB();
    $game = $db->querySingle("SELECT * FROM tictactoe WHERE id=$gameId", true);
    if (!$game) jsonError('Game not found');

    // Auto-forfeit on timeout (2 min per turn)
    if ($game['status'] === 'active') {
        $elapsed = time() - (int)$game['last_move_at'];
        if ($elapsed > 120) {
            $otherId = (int)$game['current_turn_id'] === (int)$game['creator_id']
                ? (int)$game['joiner_id']
                : (int)$game['creator_id'];
            $db->exec('BEGIN IMMEDIATE');
            tttPayout($db, $game, $otherId);
            $db->exec("UPDATE tictactoe SET status='completed', winner_id=$otherId WHERE id=$gameId");
            $db->exec('COMMIT');
            $game['status']    = 'completed';
            $game['winner_id'] = $otherId;
        }
    }

    // Fetch usernames
    $creatorName = $db->querySingle("SELECT username FROM users WHERE id={$game['creator_id']}");
    $joinerName  = $game['joiner_id']
        ? $db->querySingle("SELECT username FROM users WHERE id={$game['joiner_id']}")
        : null;
    $winnerName  = $game['winner_id']
        ? $db->querySingle("SELECT username FROM users WHERE id={$game['winner_id']}")
        : null;

    jsonOk([
        'game' => [
            'id'             => (int)$game['id'],
            'creator_id'     => (int)$game['creator_id'],
            'creator'        => $creatorName,
            'creator_sym'    => $game['creator_sym'],
            'joiner_id'      => $game['joiner_id'] ? (int)$game['joiner_id'] : null,
            'joiner'         => $joinerName,
            'amount_btc'     => (float)$game['amount_btc'],
            'board'          => $game['board'],
            'current_turn_id'=> $game['current_turn_id'] ? (int)$game['current_turn_id'] : null,
            'status'         => $game['status'],
            'winner_id'      => $game['winner_id'] ? (int)$game['winner_id'] : null,
            'winner'         => $winnerName,
        ]
    ]);
}

// ── POST actions require auth ─────────────────────────────────────────────────
if ($method !== 'POST') jsonError('Method not allowed', 405);
$u    = requireAuth();
$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── CREATE game ───────────────────────────────────────────────────────────────
if ($action === 'create') {
    $amount = round((float)($body['amount_btc'] ?? 0), 8);
    if ($amount < MIN_BET) jsonError('Minimum bet: ' . MIN_BET . ' BTC');
    if ($amount > MAX_BET) jsonError('Maximum bet: ' . MAX_BET . ' BTC');
    if ((float)$u['balance_btc'] < $amount) jsonError('Insufficient balance');

    $db   = getDB();
    $seed = generateServerSeed();

    $db->exec('BEGIN IMMEDIATE');
    try {
        $bal = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id={$u['id']}");
        if ($bal < $amount) { $db->exec('ROLLBACK'); jsonError('Insufficient balance'); }
        $newBal = round($bal - $amount, 8);
        $db->exec("UPDATE users SET balance_btc=$newBal WHERE id={$u['id']}");

        $stmt = $db->prepare(
            'INSERT INTO tictactoe(creator_id, amount_btc, server_seed) VALUES(?,?,?)'
        );
        $stmt->bindValue(1, (int)$u['id'], SQLITE3_INTEGER);
        $stmt->bindValue(2, $amount,        SQLITE3_FLOAT);
        $stmt->bindValue(3, $seed,          SQLITE3_TEXT);
        $stmt->execute();
        $gameId = $db->lastInsertRowID();

        recordTx($db, (int)$u['id'], 'bet_placed', -$amount, $newBal, 'confirmed', "TTT #$gameId");
        $db->exec('COMMIT');
        jsonOk(['game_id' => $gameId, 'balance' => $newBal]);
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        jsonError('Failed to create game');
    }
}

// ── JOIN game ─────────────────────────────────────────────────────────────────
if ($action === 'join') {
    $gameId = (int)($body['game_id'] ?? 0);
    $db     = getDB();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $game = $db->querySingle("SELECT * FROM tictactoe WHERE id=$gameId", true);
        if (!$game || $game['status'] !== 'waiting') { $db->exec('ROLLBACK'); jsonError('Game not available'); }
        if ((int)$game['creator_id'] === (int)$u['id']) { $db->exec('ROLLBACK'); jsonError('Cannot join your own game'); }

        $amount = (float)$game['amount_btc'];
        $bal    = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id={$u['id']}");
        if ($bal < $amount) { $db->exec('ROLLBACK'); jsonError('Insufficient balance'); }

        $newBal = round($bal - $amount, 8);
        $db->exec("UPDATE users SET balance_btc=$newBal WHERE id={$u['id']}");
        recordTx($db, (int)$u['id'], 'bet_placed', -$amount, $newBal, 'confirmed', "Joined TTT #$gameId");

        $creatorId = (int)$game['creator_id'];
        $stmt = $db->prepare(
            'UPDATE tictactoe SET joiner_id=?, current_turn_id=?, status="active",
             last_move_at=strftime(\'%s\',\'now\') WHERE id=?'
        );
        $stmt->bindValue(1, (int)$u['id'], SQLITE3_INTEGER);
        $stmt->bindValue(2, $creatorId,    SQLITE3_INTEGER);
        $stmt->bindValue(3, $gameId,       SQLITE3_INTEGER);
        $stmt->execute();
        $db->exec('COMMIT');
        jsonOk(['game_id' => $gameId, 'balance' => $newBal, 'your_sym' => 'O']);
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        jsonError('Failed to join game');
    }
}

// ── MOVE ─────────────────────────────────────────────────────────────────────
if ($action === 'move') {
    $gameId = (int)($body['game_id'] ?? 0);
    $cell   = (int)($body['cell'] ?? -1);
    if ($cell < 0 || $cell > 8) jsonError('Invalid cell');

    $db   = getDB();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $game = $db->querySingle("SELECT * FROM tictactoe WHERE id=$gameId", true);
        if (!$game || $game['status'] !== 'active') { $db->exec('ROLLBACK'); jsonError('Game not active'); }
        if ((int)$game['current_turn_id'] !== (int)$u['id']) { $db->exec('ROLLBACK'); jsonError('Not your turn'); }
        if ($game['board'][$cell] !== '-') { $db->exec('ROLLBACK'); jsonError('Cell already taken'); }

        // Determine symbol
        $sym   = (int)$u['id'] === (int)$game['creator_id'] ? $game['creator_sym'] : ($game['creator_sym'] === 'X' ? 'O' : 'X');
        $board = $game['board'];
        $board[$cell] = $sym;

        $result  = tttWinner($board);
        $isDraw  = $result === 'draw';
        $hasWon  = $result && !$isDraw;
        $nextTurn = (int)$game['current_turn_id'] === (int)$game['creator_id']
            ? (int)$game['joiner_id']
            : (int)$game['creator_id'];

        if ($hasWon || $isDraw) {
            $winnerId = $hasWon ? (int)$u['id'] : 0;
            tttPayout($db, $game, $winnerId, $isDraw);
            $winnerSql = $hasWon ? "winner_id=$winnerId," : '';
            $db->exec("UPDATE tictactoe SET board='$board', status='completed', $winnerSql
                        last_move_at=strftime('%s','now') WHERE id=$gameId");
            $db->exec('COMMIT');
            $myBal = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id={$u['id']}");
            jsonOk([
                'board'    => $board,
                'status'   => $isDraw ? 'draw' : 'completed',
                'winner_id'=> $hasWon ? (int)$u['id'] : null,
                'i_won'    => $hasWon,
                'is_draw'  => $isDraw,
                'balance'  => $myBal,
                'payout'   => $hasWon ? round($game['amount_btc'] * 2 * (1 - HOUSE_EDGE), 8) : 0,
            ]);
        } else {
            $db->exec("UPDATE tictactoe SET board='$board', current_turn_id=$nextTurn,
                        last_move_at=strftime('%s','now') WHERE id=$gameId");
            $db->exec('COMMIT');
            jsonOk(['board' => $board, 'status' => 'active', 'next_turn_id' => $nextTurn]);
        }
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        jsonError('Move failed: ' . $e->getMessage());
    }
}

// ── CANCEL waiting game ───────────────────────────────────────────────────────
if ($action === 'cancel') {
    $gameId = (int)($body['game_id'] ?? 0);
    $db     = getDB();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $game = $db->querySingle("SELECT * FROM tictactoe WHERE id=$gameId", true);
        if (!$game || $game['status'] !== 'waiting') { $db->exec('ROLLBACK'); jsonError('Cannot cancel'); }
        if ((int)$game['creator_id'] !== (int)$u['id']) { $db->exec('ROLLBACK'); jsonError('Not your game'); }
        $refund = (float)$game['amount_btc'];
        $newBal = round((float)$u['balance_btc'] + $refund, 8);
        $db->exec("UPDATE users SET balance_btc=$newBal WHERE id={$u['id']}");
        $db->exec("UPDATE tictactoe SET status='cancelled' WHERE id=$gameId");
        recordTx($db, (int)$u['id'], 'refund', $refund, $newBal, 'confirmed', "Cancelled TTT #$gameId");
        $db->exec('COMMIT');
        jsonOk(['balance' => $newBal]);
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        jsonError('Cancel failed');
    }
}

jsonError('Unknown action');
