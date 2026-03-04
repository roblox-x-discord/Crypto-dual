<?php
// ── CryptoDuel Configuration ──────────────────────────────────────────────────
// DB stored outside public_html: domain_root/cryptoduel.db
define('DB_PATH',      dirname(dirname(dirname(__FILE__))) . '/cryptoduel.db');
define('SECRET_KEY',   sha1(__FILE__ . 'cryptoduel_secret_v1'));
define('HOUSE_EDGE',   0.05);          // 5% house cut
define('WELCOME_BTC',  0.001);         // 0.001 BTC welcome bonus
define('MIN_BET',      0.0001);        // minimum bet
define('MAX_BET',      0.5);           // maximum bet
define('PRICE_TTL',    60);            // price cache seconds
define('CHAT_LIMIT',   60);            // messages to fetch
define('BETS_LIMIT',   50);            // open bets to show
define('RAIN_AMOUNT',  0.0001);        // rain per user
define('DEMO_COOLDOWN',86400);         // 24h between demo claims

define('COINGECKO_URL',
    'https://api.coingecko.com/api/v3/simple/price?' .
    'ids=bitcoin,ethereum,solana,binancecoin,ripple,dogecoin,cardano,avalanche-2,matic-network,chainlink' .
    '&vs_currencies=usd&include_24hr_change=true&include_market_cap=true'
);

// Coin ID → display
define('COIN_MAP', json_encode([
    'bitcoin'     => ['sym'=>'BTC','name'=>'Bitcoin',   'color'=>'orange'],
    'ethereum'    => ['sym'=>'ETH','name'=>'Ethereum',  'color'=>'blue'],
    'solana'      => ['sym'=>'SOL','name'=>'Solana',    'color'=>'purple'],
    'binancecoin' => ['sym'=>'BNB','name'=>'BNB',       'color'=>'yellow'],
    'ripple'      => ['sym'=>'XRP','name'=>'XRP',       'color'=>'cyan'],
    'dogecoin'    => ['sym'=>'DOGE','name'=>'Dogecoin', 'color'=>'yellow'],
    'cardano'     => ['sym'=>'ADA','name'=>'Cardano',   'color'=>'blue'],
    'avalanche-2' => ['sym'=>'AVAX','name'=>'Avalanche','color'=>'red'],
    'matic-network'=>['sym'=>'MATIC','name'=>'Polygon', 'color'=>'purple'],
    'chainlink'   => ['sym'=>'LINK','name'=>'Chainlink','color'=>'blue'],
]));
