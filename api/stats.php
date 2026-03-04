<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/db.php';

$action = $_GET['action'] ?? 'leaderboard';
$db     = getDB();

if ($action === 'leaderboard') {
    $res  = $db->query(
        'SELECT id, username, level, wins, losses, total_wagered, total_won, created_at
         FROM users ORDER BY total_won DESC LIMIT 20'
    );
    $rows = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = [
            'id'       => (int)$r['id'],
            'username' => $r['username'],
            'level'    => (int)$r['level'],
            'wins'     => (int)$r['wins'],
            'losses'   => (int)$r['losses'],
            'wagered'  => round((float)$r['total_wagered'], 4),
            'profit'   => round((float)$r['total_won'], 4),
            'joined'   => (int)$r['created_at'],
        ];
    }
    jsonOk(['leaderboard' => $rows]);
}

if ($action === 'global') {
    $totalUsers  = (int)$db->querySingle('SELECT COUNT(*) FROM users');
    $totalBets   = (int)$db->querySingle('SELECT COUNT(*) FROM bets WHERE status="completed"');
    $totalTTT    = (int)$db->querySingle('SELECT COUNT(*) FROM tictactoe WHERE status="completed"');
    $totalVolume = (float)$db->querySingle('SELECT COALESCE(SUM(amount_btc*2),0) FROM bets WHERE status="completed"');
    $online      = (int)$db->querySingle('SELECT COUNT(*) FROM users WHERE last_seen > ' . (time() - 300));
    jsonOk([
        'users'   => $totalUsers,
        'bets'    => $totalBets + $totalTTT,
        'volume'  => round($totalVolume, 4),
        'online'  => max(1, $online),
    ]);
}

jsonError('Unknown action');
