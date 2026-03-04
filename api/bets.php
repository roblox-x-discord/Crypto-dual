<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

// ── LIST open bets ────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $db   = getDB();
    $res  = $db->query(
        'SELECT b.*, u.username, u.level, u.wins, u.losses
         FROM bets b JOIN users u ON b.creator_id=u.id
         WHERE b.status="open"
         ORDER BY b.created_at DESC LIMIT ' . BETS_LIMIT
    );
    $bets = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $bets[] = [
            'id'          => (int)$r['id'],
            'creator'     => $r['username'],
            'creator_id'  => (int)$r['creator_id'],
            'level'       => (int)$r['level'],
            'wins'        => (int)$r['wins'],
            'losses'      => (int)$r['losses'],
            'amount_btc'  => (float)$r['amount_btc'],
            'game_type'   => $r['game_type'],
            'creator_side'=> $r['creator_side'],
            'seed_hash'   => $r['server_seed_hash'],
            'created_at'  => (int)$r['created_at'],
        ];
    }
    jsonOk(['bets' => $bets]);
}

// ── RECENT resolved bets (winners feed) ──────────────────────────────────────
if ($method === 'GET' && $action === 'recent') {
    $db  = getDB();
    $res = $db->query(
        'SELECT b.*,
                uc.username AS creator_name,
                uj.username AS joiner_name,
                uw.username AS winner_name
         FROM bets b
         JOIN users uc ON b.creator_id=uc.id
         LEFT JOIN users uj ON b.joiner_id=uj.id
         LEFT JOIN users uw ON b.winner_id=uw.id
         WHERE b.status="completed"
         ORDER BY b.resolved_at DESC LIMIT 20'
    );
    $bets = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $bets[] = [
            'id'          => (int)$r['id'],
            'creator'     => $r['creator_name'],
            'joiner'      => $r['joiner_name'],
            'winner'      => $r['winner_name'],
            'amount_btc'  => (float)$r['amount_btc'],
            'game_type'   => $r['game_type'],
            'outcome'     => $r['outcome'],
            'resolved_at' => (int)$r['resolved_at'],
        ];
    }
    jsonOk(['bets' => $bets]);
}

// ── POST actions require auth ─────────────────────────────────────────────────
if ($method !== 'POST') jsonError('Method not allowed', 405);
$u    = requireAuth();
$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── CREATE bet ────────────────────────────────────────────────────────────────
if ($action === 'create') {
    $gameType = $body['game_type'] ?? 'coinflip';
    $amount   = round((float)($body['amount_btc'] ?? 0), 8);
    $side     = $body['creator_side'] ?? null;

    if (!in_array($gameType, ['coinflip','jackpot','p2p_duel']))
        jsonError('Invalid game type');
    if ($amount < MIN_BET) jsonError('Minimum bet is ' . MIN_BET . ' BTC');
    if ($amount > MAX_BET) jsonError('Maximum bet is ' . MAX_BET . ' BTC');
    if ((float)$u['balance_btc'] < $amount) jsonError('Insufficient balance');
    if ($gameType === 'coinflip' && !in_array($side, ['heads','tails']))
        jsonError('Choose heads or tails for coinflip');

    $db   = getDB();
    $seed = generateServerSeed();
    $hash = hash('sha256', $seed);

    $db->exec('BEGIN IMMEDIATE');
    try {
        // Re-check balance inside transaction
        $bal = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id={$u['id']}");
        if ($bal < $amount) { $db->exec('ROLLBACK'); jsonError('Insufficient balance'); }

        $newBal = $bal - $amount;
        $db->exec("UPDATE users SET balance_btc=round($newBal,8) WHERE id={$u['id']}");

        $stmt = $db->prepare(
            'INSERT INTO bets(creator_id,amount_btc,game_type,creator_side,server_seed,server_seed_hash)
             VALUES(?,?,?,?,?,?)'
        );
        $stmt->bindValue(1, (int)$u['id'],  SQLITE3_INTEGER);
        $stmt->bindValue(2, $amount,         SQLITE3_FLOAT);
        $stmt->bindValue(3, $gameType,       SQLITE3_TEXT);
        $stmt->bindValue(4, $side,           SQLITE3_TEXT);
        $stmt->bindValue(5, $seed,           SQLITE3_TEXT);
        $stmt->bindValue(6, $hash,           SQLITE3_TEXT);
        $stmt->execute();

        $betId = $db->lastInsertRowID();
        recordTx($db, (int)$u['id'], 'bet_placed', -$amount, $newBal, 'confirmed', "Bet #$betId placed");

        $db->exec('COMMIT');
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        jsonError('Could not create bet');
    }

    jsonOk(['bet_id' => $betId, 'seed_hash' => $hash, 'balance' => $newBal]);
}

// ── JOIN bet ──────────────────────────────────────────────────────────────────
if ($action === 'join') {
    $betId      = (int)($body['bet_id'] ?? 0);
    $clientSeed = trim($body['client_seed'] ?? bin2hex(random_bytes(8)));
    if (!$betId) jsonError('Invalid bet ID');
    if (strlen($clientSeed) > 64) $clientSeed = substr($clientSeed, 0, 64);

    $db  = getDB();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $bet = $db->querySingle("SELECT * FROM bets WHERE id=$betId", true);
        if (!$bet || $bet['status'] !== 'open')  { $db->exec('ROLLBACK'); jsonError('Bet not available'); }
        if ((int)$bet['creator_id'] === (int)$u['id']) { $db->exec('ROLLBACK'); jsonError('Cannot join your own bet'); }

        $amount = (float)$bet['amount_btc'];
        $bal    = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id={$u['id']}");
        if ($bal < $amount) { $db->exec('ROLLBACK'); jsonError('Insufficient balance'); }

        // Deduct joiner
        $joinerBal = $bal - $amount;
        $db->exec("UPDATE users SET balance_btc=round($joinerBal,8) WHERE id={$u['id']}");
        recordTx($db, (int)$u['id'], 'bet_placed', -$amount, $joinerBal, 'confirmed', "Joined bet #$betId");

        // Resolve game
        $bet['joiner_id']  = (int)$u['id'];
        $bet['client_seed'] = $clientSeed;
        $winnerId = resolveBet($bet);
        $outcome  = resolveOutcome($bet['server_seed'], $clientSeed, (int)$bet['nonce']);

        $pot     = $amount * 2;
        $payout  = round($pot * (1 - HOUSE_EDGE), 8);
        $creatorBal = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id={$bet['creator_id']}");

        if ($winnerId === (int)$u['id']) {
            // Joiner wins
            $newJoinerBal = $joinerBal + $payout;
            $db->exec("UPDATE users SET balance_btc=round($newJoinerBal,8), wins=wins+1, total_won=total_won+$payout WHERE id={$u['id']}");
            $db->exec("UPDATE users SET losses=losses+1 WHERE id={$bet['creator_id']}");
            recordTx($db, (int)$u['id'], 'bet_won', $payout, $newJoinerBal, 'confirmed', "Won bet #$betId");
        } else {
            // Creator wins
            $newCreatorBal = $creatorBal + $payout;
            $db->exec("UPDATE users SET balance_btc=round($newCreatorBal,8), wins=wins+1, total_won=total_won+$payout WHERE id={$bet['creator_id']}");
            $db->exec("UPDATE users SET losses=losses+1 WHERE id={$u['id']}");
            recordTx($db, (int)$bet['creator_id'], 'bet_won', $payout, $newCreatorBal, 'confirmed', "Won bet #$betId");
        }

        // Update wagered + level for both
        foreach ([(int)$u['id'], (int)$bet['creator_id']] as $uid) {
            $wagered = (float)$db->querySingle("SELECT total_wagered FROM users WHERE id=$uid");
            $wagered += $amount;
            $lv = calculateLevel($wagered);
            $db->exec("UPDATE users SET total_wagered=$wagered, level=$lv WHERE id=$uid");
        }

        // Close bet
        $stmt = $db->prepare(
            'UPDATE bets SET joiner_id=?,client_seed=?,winner_id=?,outcome=?,status="completed",
             resolved_at=strftime(\'%s\',\'now\') WHERE id=?'
        );
        $stmt->bindValue(1, (int)$u['id'],  SQLITE3_INTEGER);
        $stmt->bindValue(2, $clientSeed,     SQLITE3_TEXT);
        $stmt->bindValue(3, $winnerId,       SQLITE3_INTEGER);
        $stmt->bindValue(4, $outcome,        SQLITE3_TEXT);
        $stmt->bindValue(5, $betId,          SQLITE3_INTEGER);
        $stmt->execute();

        $db->exec('COMMIT');

        $myBal   = (float)$db->querySingle("SELECT balance_btc FROM users WHERE id={$u['id']}");
        $iWon    = $winnerId === (int)$u['id'];
        jsonOk([
            'winner'       => $winnerId,
            'i_won'        => $iWon,
            'outcome'      => $outcome,
            'payout'       => $payout,
            'server_seed'  => $bet['server_seed'],
            'client_seed'  => $clientSeed,
            'balance'      => $myBal,
        ]);
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        jsonError('Failed to resolve bet: ' . $e->getMessage());
    }
}

// ── CANCEL bet ────────────────────────────────────────────────────────────────
if ($action === 'cancel') {
    $betId = (int)($body['bet_id'] ?? 0);
    $db    = getDB();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $bet = $db->querySingle("SELECT * FROM bets WHERE id=$betId", true);
        if (!$bet || $bet['status'] !== 'open') { $db->exec('ROLLBACK'); jsonError('Cannot cancel this bet'); }
        if ((int)$bet['creator_id'] !== (int)$u['id']) { $db->exec('ROLLBACK'); jsonError('Not your bet'); }

        $refund = (float)$bet['amount_btc'];
        $newBal = (float)$u['balance_btc'] + $refund;
        $db->exec("UPDATE users SET balance_btc=round($newBal,8) WHERE id={$u['id']}");
        $db->exec("UPDATE bets SET status='cancelled' WHERE id=$betId");
        recordTx($db, (int)$u['id'], 'refund', $refund, $newBal, 'confirmed', "Cancelled bet #$betId");
        $db->exec('COMMIT');
        jsonOk(['balance' => $newBal]);
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        jsonError('Cancel failed');
    }
}

jsonError('Unknown action');
