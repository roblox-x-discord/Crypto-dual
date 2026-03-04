<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once dirname(__DIR__) . '/includes/db.php';

$db  = getDB();
$row = $db->querySingle("SELECT data, updated_at FROM price_cache WHERE id=1", true);
$now = time();

if ($row && $row['data'] && ($now - (int)$row['updated_at']) < PRICE_TTL) {
    // Return cached data
    echo $row['data'];
    exit;
}

// Fetch from CoinGecko
$raw  = null;
$maps = json_decode(COIN_MAP, true);

$ctx = stream_context_create(['http' => [
    'timeout'       => 8,
    'ignore_errors' => true,
    'header'        => "User-Agent: CryptoDuel/1.0\r\nAccept: application/json\r\n",
]]);

if (function_exists('curl_init')) {
    $ch = curl_init(COINGECKO_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'CryptoDuel/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $body) $raw = json_decode($body, true);
}

if (!$raw && ini_get('allow_url_fopen')) {
    $body = @file_get_contents(COINGECKO_URL, false, $ctx);
    if ($body) $raw = json_decode($body, true);
}

// Build price data
$prices = [];
if ($raw) {
    foreach ($maps as $id => $info) {
        if (!isset($raw[$id])) continue;
        $prices[$info['sym']] = [
            'id'     => $id,
            'name'   => $info['name'],
            'color'  => $info['color'],
            'price'  => round((float)($raw[$id]['usd'] ?? 0), 8),
            'change' => round((float)($raw[$id]['usd_24h_change'] ?? 0), 2),
        ];
    }
} elseif ($row && $row['data']) {
    // Return stale cached data on API failure
    echo $row['data'];
    exit;
} else {
    // Hard fallback
    $prices = [
        'BTC'  => ['name'=>'Bitcoin',   'color'=>'orange', 'price'=>67000,  'change'=> 1.2],
        'ETH'  => ['name'=>'Ethereum',  'color'=>'blue',   'price'=>3400,   'change'=>-0.8],
        'SOL'  => ['name'=>'Solana',    'color'=>'purple', 'price'=>182,    'change'=> 3.1],
        'BNB'  => ['name'=>'BNB',       'color'=>'yellow', 'price'=>421,    'change'=> 0.5],
        'XRP'  => ['name'=>'XRP',       'color'=>'cyan',   'price'=>0.58,   'change'=>-1.2],
        'DOGE' => ['name'=>'Dogecoin',  'color'=>'yellow', 'price'=>0.12,   'change'=> 5.4],
        'ADA'  => ['name'=>'Cardano',   'color'=>'blue',   'price'=>0.45,   'change'=>-0.9],
        'AVAX' => ['name'=>'Avalanche', 'color'=>'red',    'price'=>35,     'change'=> 2.1],
        'MATIC'=> ['name'=>'Polygon',   'color'=>'purple', 'price'=>0.82,   'change'=>-0.4],
        'LINK' => ['name'=>'Chainlink', 'color'=>'blue',   'price'=>14.2,   'change'=> 1.8],
    ];
}

$out = json_encode(['success' => true, 'prices' => $prices, 'ts' => $now]);

// Cache in DB
$stmt = $db->prepare('UPDATE price_cache SET data=?, updated_at=? WHERE id=1');
$stmt->bindValue(1, $out, SQLITE3_TEXT);
$stmt->bindValue(2, $now, SQLITE3_INTEGER);
$stmt->execute();

echo $out;
