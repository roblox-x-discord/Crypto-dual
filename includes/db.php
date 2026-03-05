<?php
require_once __DIR__ . '/config.php';

function getDB(): SQLite3 {
    static $db = null;
    if ($db !== null) return $db;
    $db = new SQLite3(DB_PATH);
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');
    $db->exec('PRAGMA synchronous=NORMAL');
    initTables($db);
    return $db;
}

function initTables(SQLite3 $db): void {
    $db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        username        TEXT    UNIQUE NOT NULL COLLATE NOCASE,
        email           TEXT    UNIQUE NOT NULL COLLATE NOCASE,
        password_hash   TEXT    NOT NULL,
        balance_btc     REAL    DEFAULT 0.0,
        balance_usd     REAL    DEFAULT 0.0,
        wallet_address  TEXT,
        level           INTEGER DEFAULT 1,
        xp              REAL    DEFAULT 0,
        total_wagered   REAL    DEFAULT 0,
        total_won       REAL    DEFAULT 0,
        total_deposited REAL    DEFAULT 0.0,
        wins            INTEGER DEFAULT 0,
        losses          INTEGER DEFAULT 0,
        last_demo_claim INTEGER DEFAULT 0,
        created_at      INTEGER DEFAULT (strftime('%s','now')),
        last_seen       INTEGER DEFAULT (strftime('%s','now'))
    );

    CREATE TABLE IF NOT EXISTS bets (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        creator_id      INTEGER NOT NULL REFERENCES users(id),
        joiner_id       INTEGER REFERENCES users(id),
        amount_btc      REAL    NOT NULL,
        game_type       TEXT    NOT NULL,
        creator_side    TEXT,
        status          TEXT    DEFAULT 'open',
        winner_id       INTEGER REFERENCES users(id),
        server_seed     TEXT    NOT NULL,
        server_seed_hash TEXT   NOT NULL,
        client_seed     TEXT,
        nonce           INTEGER DEFAULT 0,
        outcome         TEXT,
        created_at      INTEGER DEFAULT (strftime('%s','now')),
        resolved_at     INTEGER
    );

    CREATE TABLE IF NOT EXISTS transactions (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id),
        type        TEXT    NOT NULL,
        amount_btc  REAL    NOT NULL,
        balance_after REAL,
        status      TEXT    DEFAULT 'confirmed',
        tx_hash     TEXT,
        address     TEXT,
        notes       TEXT,
        created_at  INTEGER DEFAULT (strftime('%s','now'))
    );

    CREATE TABLE IF NOT EXISTS chat_messages (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL REFERENCES users(id),
        message     TEXT    NOT NULL,
        msg_type    TEXT    DEFAULT 'normal',
        created_at  INTEGER DEFAULT (strftime('%s','now'))
    );

    CREATE TABLE IF NOT EXISTS price_cache (
        id          INTEGER PRIMARY KEY DEFAULT 1,
        data        TEXT,
        updated_at  INTEGER DEFAULT 0
    );

    INSERT OR IGNORE INTO price_cache (id) VALUES (1);

    CREATE TABLE IF NOT EXISTS tictactoe (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        creator_id      INTEGER NOT NULL REFERENCES users(id),
        joiner_id       INTEGER REFERENCES users(id),
        amount_btc      REAL    NOT NULL,
        board           TEXT    DEFAULT '---------',
        current_turn_id INTEGER REFERENCES users(id),
        creator_sym     TEXT    DEFAULT 'X',
        status          TEXT    DEFAULT 'waiting',
        winner_id       INTEGER REFERENCES users(id),
        server_seed     TEXT    NOT NULL DEFAULT '',
        last_move_at    INTEGER DEFAULT (strftime('%s','now')),
        created_at      INTEGER DEFAULT (strftime('%s','now'))
    );

    CREATE TABLE IF NOT EXISTS nowpayments_transactions (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id         INTEGER NOT NULL REFERENCES users(id),
        payment_id      TEXT,
        np_payment_id   TEXT,
        currency        TEXT    NOT NULL,
        amount          REAL    NOT NULL,
        amount_usd      REAL    NOT NULL,
        fee_amount      REAL    DEFAULT 0.0,
        credited_amount REAL    DEFAULT 0.0,
        pay_address     TEXT,
        status          TEXT    DEFAULT 'pending',
        ipn_received_at INTEGER,
        created_at      INTEGER DEFAULT (strftime('%s','now')),
        updated_at      INTEGER DEFAULT (strftime('%s','now'))
    );

    CREATE INDEX IF NOT EXISTS idx_bets_status  ON bets(status);
    CREATE INDEX IF NOT EXISTS idx_bets_creator ON bets(creator_id);
    CREATE INDEX IF NOT EXISTS idx_chat_created ON chat_messages(created_at);
    CREATE INDEX IF NOT EXISTS idx_tx_user      ON transactions(user_id);
    CREATE INDEX IF NOT EXISTS idx_ttt_status   ON tictactoe(status);
    CREATE INDEX IF NOT EXISTS idx_np_user      ON nowpayments_transactions(user_id);
    CREATE INDEX IF NOT EXISTS idx_np_status    ON nowpayments_transactions(status);
    CREATE INDEX IF NOT EXISTS idx_np_payment   ON nowpayments_transactions(payment_id);

    CREATE TABLE IF NOT EXISTS sports_matches (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        api_fixture_id  INTEGER UNIQUE,
        sport_type      TEXT    NOT NULL,
        home_team       TEXT    NOT NULL,
        away_team       TEXT    NOT NULL,
        league_name     TEXT,
        match_time      INTEGER NOT NULL,
        status          TEXT    DEFAULT 'upcoming',
        home_score      INTEGER DEFAULT 0,
        away_score      INTEGER DEFAULT 0,
        winner          TEXT,
        created_at      INTEGER DEFAULT (strftime('%s','now'))
    );

    CREATE TABLE IF NOT EXISTS sports_bets (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id         INTEGER NOT NULL REFERENCES users(id),
        match_id        INTEGER NOT NULL REFERENCES sports_matches(id),
        bet_type        TEXT    NOT NULL,
        team_selection  TEXT    NOT NULL,
        amount_btc      REAL    NOT NULL,
        potential_win   REAL    NOT NULL,
        status          TEXT    DEFAULT 'pending',
        win_amount      REAL    DEFAULT 0,
        created_at      INTEGER DEFAULT (strftime('%s','now')),
        settled_at      INTEGER
    );

    CREATE INDEX IF NOT EXISTS idx_sports_status   ON sports_matches(status);
    CREATE INDEX IF NOT EXISTS idx_sports_fixture   ON sports_matches(api_fixture_id);
    CREATE INDEX IF NOT EXISTS idx_sports_bets_user ON sports_bets(user_id);
    CREATE INDEX IF NOT EXISTS idx_sports_bets_match ON sports_bets(match_id);

    CREATE TABLE IF NOT EXISTS football_pools (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        match_id        INTEGER NOT NULL REFERENCES sports_matches(id),
        bet_type        TEXT    NOT NULL,
        team_selection  TEXT    NOT NULL,
        user1_id        INTEGER NOT NULL REFERENCES users(id),
        user2_id        INTEGER REFERENCES users(id),
        amount_btc      REAL    NOT NULL,
        pool_total      REAL    DEFAULT 0,
        status          TEXT    DEFAULT 'open',
        winner_id       INTEGER REFERENCES users(id),
        admin_cut       REAL    DEFAULT 0,
        created_at      INTEGER DEFAULT (strftime('%s','now')),
        settled_at      INTEGER
    );

    CREATE TABLE IF NOT EXISTS football_2v2_teams (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        pool_id         INTEGER NOT NULL REFERENCES football_pools(id),
        team_name       TEXT    NOT NULL,
        member1_id      INTEGER NOT NULL REFERENCES users(id),
        member2_id      INTEGER REFERENCES users(id),
        total_amount    REAL    DEFAULT 0,
        created_at      INTEGER DEFAULT (strftime('%s','now')')
    );

    CREATE INDEX IF NOT EXISTS idx_football_pools_match   ON football_pools(match_id);
    CREATE INDEX IF NOT EXISTS idx_football_pools_status  ON football_pools(status);
    CREATE INDEX IF NOT EXISTS idx_football_pools_user1   ON football_pools(user1_id);
    CREATE INDEX IF NOT EXISTS idx_football_pools_type    ON football_pools(bet_type);
    CREATE INDEX IF NOT EXISTS idx_football_2v2_pool      ON football_2v2_teams(pool_id);
    ");
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function jsonOk(array $data): never {
    header('Content-Type: application/json');
    echo json_encode(['success' => true] + $data);
    exit;
}

function jsonError(string $msg, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function requireAuth(): array {
    if (empty($_SESSION['user_id'])) jsonError('Not authenticated', 401);
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE id=?');
    $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
    $row  = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) jsonError('User not found', 401);
    return $row;
}

function calculateLevel(float $wagered): int {
    if ($wagered <= 0) return 1;
    return min(500, 1 + (int)floor($wagered / 0.001));
}

function walletAddress(int $userId): string {
    $h = hash('sha256', SECRET_KEY . 'addr' . $userId);
    $s = '1';
    for ($i = 0; $i < 32; $i += 2) {
        $v = hexdec(substr($h, $i, 2)) % 58;
        $s .= substr('123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz', $v, 1);
    }
    return substr($s, 0, 34);
}

function generateServerSeed(): string {
    return bin2hex(random_bytes(32));
}

function resolveOutcome(string $serverSeed, string $clientSeed, int $nonce): string {
    $hash  = hash_hmac('sha256', $clientSeed . ':' . $nonce, $serverSeed);
    $value = hexdec(substr($hash, 0, 8)) % 2;
    return $value === 0 ? 'heads' : 'tails';
}

function resolveBet(array $bet): int {
    // returns winner user_id
    $outcome = resolveOutcome($bet['server_seed'], $bet['client_seed'], $bet['nonce']);
    // For coinflip: creator chose a side
    if ($bet['game_type'] === 'coinflip' && $bet['creator_side']) {
        return $outcome === $bet['creator_side'] ? $bet['creator_id'] : $bet['joiner_id'];
    }
    // For p2p_duel / jackpot: heads = creator wins
    return $outcome === 'heads' ? $bet['creator_id'] : $bet['joiner_id'];
}

function recordTx(SQLite3 $db, int $userId, string $type, float $amount, float $balanceAfter, string $status='confirmed', ?string $notes=null): void {
    $hash = 'tx' . bin2hex(random_bytes(8));
    $stmt = $db->prepare('INSERT INTO transactions(user_id,type,amount_btc,balance_after,status,tx_hash,notes) VALUES(?,?,?,?,?,?,?)');
    $stmt->bindValue(1, $userId,      SQLITE3_INTEGER);
    $stmt->bindValue(2, $type,        SQLITE3_TEXT);
    $stmt->bindValue(3, $amount,      SQLITE3_FLOAT);
    $stmt->bindValue(4, $balanceAfter,SQLITE3_FLOAT);
    $stmt->bindValue(5, $status,      SQLITE3_TEXT);
    $stmt->bindValue(6, $hash,        SQLITE3_TEXT);
    $stmt->bindValue(7, $notes,       SQLITE3_TEXT);
    $stmt->execute();
}

function publicUser(array $u): array {
    return [
        'id'        => (int)$u['id'],
        'username'  => $u['username'],
        'balance'   => round((float)$u['balance_btc'], 8),
        'level'     => (int)$u['level'],
        'wins'      => (int)$u['wins'],
        'losses'    => (int)$u['losses'],
        'wallet'    => $u['wallet_address'],
    ];
}
