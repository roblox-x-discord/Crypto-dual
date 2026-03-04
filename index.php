<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$user       = null;
$isLoggedIn = false;

if (!empty($_SESSION['user_id'])) {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE id=?');
    $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($user) {
        $isLoggedIn = true;
        $db->exec("UPDATE users SET last_seen=strftime('%s','now') WHERE id={$user['id']}");
    } else {
        session_destroy();
        $user = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>CryptoDuel — Live P2P Crypto Betting</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: { extend: {
    colors: { neon: { purple:'#a855f7',cyan:'#22d3ee',green:'#22c55e',orange:'#f97316',pink:'#ec4899',yellow:'#eab308',red:'#ef4444',blue:'#3b82f6' } },
    fontFamily: { sans: ['Inter','system-ui','sans-serif'] },
    keyframes: {
      ticker:   {'0%':{transform:'translateX(100%)'},'100%':{transform:'translateX(-100%)'}},
      slideUp:  {'0%':{transform:'translateY(20px)',opacity:0},'100%':{transform:'translateY(0)',opacity:1}},
      bounceIn: {'0%':{transform:'scale(0.9)',opacity:0},'60%':{transform:'scale(1.03)'},'100%':{transform:'scale(1)',opacity:1}},
      fadeIn:   {'0%':{opacity:0},'100%':{opacity:1}},
      coinSpin: {'0%':{transform:'rotateY(0deg)'},'100%':{transform:'rotateY(1800deg)'}},
      pulse2:   {'0%,100%':{opacity:1},'50%':{opacity:0.4}},
    },
    animation: {
      ticker:   'ticker 45s linear infinite',
      slideUp:  'slideUp 0.3s ease-out',
      bounceIn: 'bounceIn 0.45s ease-out',
      fadeIn:   'fadeIn 0.3s ease-out',
      coinSpin: 'coinSpin 1.4s ease-out forwards',
      pulse2:   'pulse2 1.5s ease-in-out infinite',
    }
  }}
}
</script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
  *{box-sizing:border-box}
  body{background:#0f172a;color:#e2e8f0;font-family:'Inter',sans-serif;overflow-x:hidden}
  ::-webkit-scrollbar{width:4px;height:4px}
  ::-webkit-scrollbar-track{background:#1e293b}
  ::-webkit-scrollbar-thumb{background:#a855f7;border-radius:2px}
  .glass{background:rgba(30,41,59,0.85);backdrop-filter:blur(12px)}
  .neon-glow{box-shadow:0 0 20px rgba(168,85,247,0.35),0 0 40px rgba(168,85,247,0.1)}
  .neon-border{border:1px solid rgba(168,85,247,0.3)}
  .neon-border:hover{border-color:rgba(168,85,247,0.65);box-shadow:0 0 12px rgba(168,85,247,0.2)}
  .card-hover{transition:all .22s ease}
  .card-hover:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(168,85,247,0.22)}
  .grad-purple{background:linear-gradient(135deg,#7e22ce,#a855f7)}
  .grad-text{background:linear-gradient(135deg,#a855f7,#22d3ee);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
  .ticker-wrap{overflow:hidden;white-space:nowrap}
  .ticker-move{display:inline-block;animation:ticker 45s linear infinite}
  .sidebar{width:72px;transition:width .3s ease}
  .sidebar:hover{width:200px}
  .sidebar:hover .nav-label{opacity:1;width:auto}
  .nav-label{opacity:0;width:0;overflow:hidden;transition:all .3s ease;white-space:nowrap}
  .chat-bar{width:280px}
  .chat-msgs{height:calc(100vh - 240px);overflow-y:auto}
  .level-badge{font-size:9px;padding:1px 5px;border-radius:4px;font-weight:700}
  .modal-overlay{position:fixed;inset:0;background:rgba(2,6,23,.88);z-index:100;display:none;align-items:center;justify-content:center;backdrop-filter:blur(6px)}
  .modal-overlay.active{display:flex}
  .pf-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);border-radius:20px;font-size:11px;color:#22c55e}
  @media(max-width:768px){.sidebar,.chat-bar{display:none!important}.mobile-nav-bar{display:flex!important}.main-content{margin-left:0!important;margin-right:0!important}}
  @media(min-width:769px){.mobile-nav-bar{display:none!important}}
  .bet-card{transition:all .22s ease}
  .bet-card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(168,85,247,.2)}
  .btn-pulse::after{content:'';position:absolute;inset:0;border-radius:inherit;background:rgba(255,255,255,.08);opacity:0;transition:opacity .2s}
  .btn-pulse:hover::after{opacity:1}
  .tab-btn.active{color:#a855f7;border-bottom-color:#a855f7}
  #resultOverlay{position:fixed;inset:0;z-index:200;display:none;align-items:center;justify-content:center;background:rgba(2,6,23,.9);backdrop-filter:blur(8px)}
  #resultOverlay.active{display:flex}
  .coin-3d{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;perspective:400px}
  .spin-active{animation:coinSpin 1.4s ease-out forwards}
  .mobile-menu{position:fixed;inset:0;z-index:90;display:none}
  .mobile-menu.active{display:block}
  .mobile-sidebar{position:fixed;left:0;top:0;bottom:0;width:240px;background:#1e293b;z-index:95;transform:translateX(-100%);transition:transform .3s ease;padding:20px 0;border-right:1px solid rgba(168,85,247,.2)}
  .mobile-sidebar.active{transform:translateX(0)}
  .toast-container{position:fixed;top:80px;right:16px;z-index:150;display:flex;flex-direction:column;gap:8px;pointer-events:none}
  .toast{padding:10px 16px;border-radius:12px;font-size:13px;font-weight:600;animation:slideUp .3s ease-out;pointer-events:auto;display:flex;align-items:center;gap:8px}
  .acl-purple{border-left:3px solid #a855f7}.acl-cyan{border-left:3px solid #22d3ee}.acl-green{border-left:3px solid #22c55e}
  .acl-orange{border-left:3px solid #f97316}.acl-yellow{border-left:3px solid #eab308}.acl-red{border-left:3px solid #ef4444}
  .acl-blue{border-left:3px solid #3b82f6}.acl-pink{border-left:3px solid #ec4899}
</style>
</head>
<body class="min-h-screen">

<!-- ════════════════════════════ TOAST CONTAINER ═══════════════════════════ -->
<div class="toast-container" id="toastContainer"></div>

<!-- ════════════════════════════ AUTH MODAL ════════════════════════════════ -->
<div class="modal-overlay" id="authModal">
  <div class="bg-slate-900 rounded-2xl w-full max-w-md mx-4 overflow-hidden neon-border animate-bounceIn">
    <div class="flex border-b border-slate-800">
      <button onclick="authTab('login')" id="atab-login" class="flex-1 py-4 text-sm font-bold text-purple-400 border-b-2 border-purple-500 transition-all">Sign In</button>
      <button onclick="authTab('register')" id="atab-register" class="flex-1 py-4 text-sm font-bold text-slate-400 border-b-2 border-transparent hover:text-white transition-all">Create Account</button>
    </div>
    <!-- Login -->
    <div id="apanel-login" class="p-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl grad-purple flex items-center justify-center neon-glow">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        </div>
        <div><h2 class="text-white font-black text-xl">Welcome Back</h2><p class="text-slate-400 text-xs">Sign in to start betting</p></div>
      </div>
      <div id="loginError" class="hidden mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"></div>
      <div class="space-y-3">
        <input id="loginIdent" type="text" placeholder="Username or Email" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-all"/>
        <input id="loginPass" type="password" placeholder="Password" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-all"/>
        <button onclick="doLogin()" id="loginBtn" class="w-full py-3 grad-purple rounded-xl text-white font-bold text-sm hover:opacity-90 transition-all neon-glow relative btn-pulse">Sign In</button>
      </div>
      <p class="text-center text-slate-500 text-xs mt-4">Don't have an account? <button onclick="authTab('register')" class="text-purple-400 hover:text-purple-300 transition-colors">Register free</button></p>
    </div>
    <!-- Register -->
    <div id="apanel-register" class="p-6 hidden">
      <div class="flex items-center gap-3 mb-5">
        <div class="w-10 h-10 rounded-xl grad-purple flex items-center justify-center neon-glow">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        </div>
        <div><h2 class="text-white font-black text-xl">Join CryptoDuel</h2><p class="text-green-400 text-xs font-semibold">Get 0.001 BTC welcome bonus!</p></div>
      </div>
      <div id="regError" class="hidden mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"></div>
      <div class="space-y-3">
        <input id="regUser" type="text" placeholder="Username (3-24 chars)" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-all"/>
        <input id="regEmail" type="email" placeholder="Email address" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-all"/>
        <input id="regPass" type="password" placeholder="Password (min 6 chars)" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-all"/>
        <button onclick="doRegister()" id="regBtn" class="w-full py-3 grad-purple rounded-xl text-white font-bold text-sm hover:opacity-90 transition-all neon-glow relative btn-pulse">Create Account &amp; Claim Bonus</button>
      </div>
      <p class="text-center text-slate-500 text-xs mt-4">Already have an account? <button onclick="authTab('login')" class="text-purple-400 hover:text-purple-300 transition-colors">Sign In</button></p>
    </div>
  </div>
</div>

<!-- ════════════════════════════ WALLET MODAL ══════════════════════════════ -->
<div class="modal-overlay" id="walletModal">
  <div class="bg-slate-900 rounded-2xl w-full max-w-lg mx-4 overflow-hidden neon-border animate-bounceIn" style="max-height:90vh;overflow-y:auto">
    <div class="flex items-center justify-between p-5 border-b border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg grad-purple flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        </div>
        <div><h2 class="text-white font-bold text-lg">Crypto Wallet</h2><p class="text-slate-400 text-xs">Balance: <span class="text-purple-300 font-bold" id="wModalBalance">—</span> BTC</p></div>
      </div>
      <button onclick="closeModal('walletModal')" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="flex border-b border-slate-800">
      <button onclick="walletTab('deposit')" id="wtab-deposit" class="flex-1 py-3 text-xs font-bold text-purple-400 border-b-2 border-purple-500 transition-all">Deposit</button>
      <button onclick="walletTab('withdraw')" id="wtab-withdraw" class="flex-1 py-3 text-xs font-bold text-slate-400 border-b-2 border-transparent hover:text-white transition-all">Withdraw</button>
      <button onclick="walletTab('history')" id="wtab-history" class="flex-1 py-3 text-xs font-bold text-slate-400 border-b-2 border-transparent hover:text-white transition-all">History</button>
      <button onclick="walletTab('demo')" id="wtab-demo" class="flex-1 py-3 text-xs font-bold text-slate-400 border-b-2 border-transparent hover:text-white transition-all">Demo</button>
    </div>
    <!-- Deposit -->
    <div id="wpanel-deposit" class="p-5">
      <p class="text-slate-400 text-sm text-center mb-4">Send BTC to your deposit address</p>
      <div class="flex justify-center mb-4">
        <div class="p-3 bg-white rounded-xl">
          <svg viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg" width="140" height="140">
            <rect width="140" height="140" fill="white"/>
            <rect x="8" y="8" width="38" height="38" rx="3" fill="#0f172a"/><rect x="13" y="13" width="28" height="28" rx="2" fill="white"/><rect x="18" y="18" width="18" height="18" rx="1" fill="#0f172a"/>
            <rect x="94" y="8" width="38" height="38" rx="3" fill="#0f172a"/><rect x="99" y="13" width="28" height="28" rx="2" fill="white"/><rect x="104" y="18" width="18" height="18" rx="1" fill="#0f172a"/>
            <rect x="8" y="94" width="38" height="38" rx="3" fill="#0f172a"/><rect x="13" y="99" width="28" height="28" rx="2" fill="white"/><rect x="18" y="104" width="18" height="18" rx="1" fill="#0f172a"/>
            <rect x="54" y="8" width="6" height="6" fill="#0f172a"/><rect x="64" y="8" width="6" height="6" fill="#0f172a"/><rect x="74" y="18" width="6" height="6" fill="#0f172a"/>
            <rect x="54" y="28" width="6" height="6" fill="#0f172a"/><rect x="64" y="28" width="6" height="6" fill="#0f172a"/><rect x="74" y="28" width="6" height="6" fill="#0f172a"/>
            <rect x="54" y="54" width="6" height="6" fill="#0f172a"/><rect x="74" y="54" width="6" height="6" fill="#0f172a"/><rect x="94" y="54" width="6" height="6" fill="#0f172a"/>
            <rect x="114" y="54" width="6" height="6" fill="#0f172a"/><rect x="124" y="54" width="6" height="6" fill="#0f172a"/>
            <rect x="8" y="64" width="6" height="6" fill="#0f172a"/><rect x="28" y="64" width="6" height="6" fill="#0f172a"/>
            <rect x="44" y="64" width="6" height="6" fill="#0f172a"/><rect x="64" y="64" width="6" height="6" fill="#0f172a"/>
            <rect x="84" y="64" width="6" height="6" fill="#0f172a"/><rect x="104" y="74" width="6" height="6" fill="#0f172a"/>
            <rect x="54" y="104" width="6" height="6" fill="#0f172a"/><rect x="74" y="104" width="6" height="6" fill="#0f172a"/><rect x="94" y="104" width="6" height="6" fill="#0f172a"/>
            <rect x="54" y="124" width="6" height="6" fill="#0f172a"/><rect x="74" y="124" width="6" height="6" fill="#0f172a"/><rect x="104" y="124" width="6" height="6" fill="#0f172a"/>
          </svg>
        </div>
      </div>
      <p class="text-slate-400 text-xs text-center mb-2">Your unique BTC address</p>
      <div class="flex items-center gap-2 bg-slate-800 rounded-lg p-3 mb-4">
        <span class="text-purple-300 text-xs font-mono flex-1 truncate" id="walletAddr">Loading...</span>
        <button onclick="copyAddr()" class="text-slate-400 hover:text-purple-400 transition-colors shrink-0">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        </button>
      </div>
      <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-3 text-xs text-yellow-400 leading-relaxed">
        <strong>Demo Mode:</strong> This is a simulated address. No real BTC is processed. Use the Demo tab for free play funds.
      </div>
    </div>
    <!-- Withdraw -->
    <div id="wpanel-withdraw" class="p-5 hidden">
      <div class="bg-slate-800 rounded-xl p-3 flex justify-between items-center mb-4">
        <span class="text-slate-400 text-sm">Available</span>
        <span class="text-white font-bold" id="wdrawBal">— BTC</span>
      </div>
      <div id="wdrawError" class="hidden mb-3 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-xs"></div>
      <div class="space-y-3">
        <input id="wdrawAddr" type="text" placeholder="Your BTC address" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-all"/>
        <div class="relative">
          <input id="wdrawAmt" type="number" step="0.00001" min="0.001" placeholder="Amount (BTC)" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-all"/>
          <button onclick="setMaxWithdraw()" class="absolute right-3 top-1/2 -translate-y-1/2 text-purple-400 text-xs font-bold hover:text-purple-300">MAX</button>
        </div>
        <button onclick="doWithdraw()" class="w-full py-3 grad-purple rounded-xl text-white font-bold text-sm hover:opacity-90 transition-all neon-glow">Withdraw BTC</button>
        <p class="text-slate-500 text-xs text-center">Min: 0.001 BTC · Fee: 0.00002 BTC · Demo mode</p>
      </div>
    </div>
    <!-- History -->
    <div id="wpanel-history" class="p-5 hidden">
      <div id="txList" class="space-y-2 min-h-20">
        <div class="text-slate-500 text-sm text-center py-4">Loading...</div>
      </div>
    </div>
    <!-- Demo -->
    <div id="wpanel-demo" class="p-5 hidden">
      <div class="text-center mb-5">
        <div class="w-16 h-16 rounded-2xl bg-green-500/10 border border-green-500/20 flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h3 class="text-white font-bold text-lg mb-1">Daily Demo Funds</h3>
        <p class="text-slate-400 text-sm">Claim 0.0005 BTC every 24 hours for free play</p>
      </div>
      <div id="demoMsg" class="hidden mb-4 p-3 rounded-xl text-sm text-center"></div>
      <button onclick="claimDemo()" id="claimBtn" class="w-full py-3 bg-green-600 hover:bg-green-500 rounded-xl text-white font-bold text-sm transition-all">Claim 0.0005 BTC</button>
      <p class="text-slate-500 text-xs text-center mt-3">Refreshes every 24 hours</p>
    </div>
  </div>
</div>

<!-- ════════════════════════════ CREATE BET MODAL ══════════════════════════ -->
<div class="modal-overlay" id="createBetModal">
  <div class="bg-slate-900 rounded-2xl w-full max-w-md mx-4 overflow-hidden neon-border animate-bounceIn">
    <div class="flex items-center justify-between p-5 border-b border-slate-800">
      <div><h2 class="text-white font-bold text-lg">Create a Bet</h2><p class="text-slate-400 text-xs">Challenge the arena</p></div>
      <button onclick="closeModal('createBetModal')" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="p-5 space-y-4">
      <div id="cbError" class="hidden p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"></div>
      <div>
        <label class="text-slate-400 text-xs font-medium mb-2 block">Game Type</label>
        <div class="grid grid-cols-3 gap-2" id="gameTypePicker">
          <button onclick="pickGame('coinflip',this)" class="game-pick py-2.5 bg-purple-600/20 border border-purple-500 rounded-xl text-purple-400 text-xs font-bold transition-all">🪙 Coinflip</button>
          <button onclick="pickGame('jackpot',this)" class="game-pick py-2.5 bg-slate-800 border border-slate-700 hover:border-slate-600 rounded-xl text-slate-400 text-xs font-bold transition-all">⭐ Jackpot</button>
          <button onclick="pickGame('p2p_duel',this)" class="game-pick py-2.5 bg-slate-800 border border-slate-700 hover:border-slate-600 rounded-xl text-slate-400 text-xs font-bold transition-all">⚔️ P2P Duel</button>
        </div>
      </div>
      <div id="sidePickerRow" class="">
        <label class="text-slate-400 text-xs font-medium mb-2 block">Pick Your Side</label>
        <div class="grid grid-cols-2 gap-2">
          <button onclick="pickSide('heads',this)" id="sideHeads" class="side-pick py-3 bg-slate-800 border border-slate-700 hover:border-yellow-500 rounded-xl text-sm font-bold transition-all text-slate-300">🌕 Heads</button>
          <button onclick="pickSide('tails',this)" id="sideTails" class="side-pick py-3 bg-slate-800 border border-slate-700 hover:border-slate-400 rounded-xl text-sm font-bold transition-all text-slate-300">🌑 Tails</button>
        </div>
      </div>
      <div>
        <label class="text-slate-400 text-xs font-medium mb-2 block">Bet Amount (BTC)</label>
        <div class="relative">
          <input id="cbAmount" type="number" step="0.0001" min="0.0001" max="0.5" placeholder="0.0000" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-all pr-16"/>
          <div class="absolute right-3 top-1/2 -translate-y-1/2 flex flex-col items-end gap-0">
            <span class="text-orange-400 text-xs font-bold">BTC</span>
            <span class="text-slate-500 text-xs" id="cbUsdEst">≈ $0</span>
          </div>
        </div>
        <div class="flex gap-2 mt-2">
          <?php foreach([0.0001,0.0005,0.001,0.005] as $q): ?>
          <button onclick="document.getElementById('cbAmount').value='<?= $q ?>'; updateUsdEst()" class="flex-1 py-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-purple-500 rounded-lg text-slate-400 hover:text-purple-400 text-xs font-semibold transition-all"><?= $q ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="bg-slate-800 rounded-xl p-3 flex items-center justify-between">
        <span class="text-slate-400 text-xs">Potential Win</span>
        <span class="text-green-400 font-bold text-sm" id="cbPotentialWin">0.0000 BTC</span>
      </div>
      <button onclick="doCreateBet()" id="cbBtn" class="w-full py-3 grad-purple rounded-xl text-white font-bold text-sm hover:opacity-90 transition-all neon-glow relative btn-pulse">Place Bet</button>
    </div>
  </div>
</div>

<!-- ════════════════════════════ JOIN CONFIRM MODAL ════════════════════════ -->
<div class="modal-overlay" id="joinModal">
  <div class="bg-slate-900 rounded-2xl w-full max-w-sm mx-4 neon-border animate-bounceIn p-6">
    <h3 class="text-white font-bold text-lg mb-1">Join This Bet?</h3>
    <p class="text-slate-400 text-sm mb-4" id="joinDetails">Details loading...</p>
    <div class="bg-slate-800 rounded-xl p-3 mb-4 text-sm space-y-2">
      <div class="flex justify-between"><span class="text-slate-400">Your bet</span><span class="text-white font-bold" id="joinAmt">—</span></div>
      <div class="flex justify-between"><span class="text-slate-400">Win</span><span class="text-green-400 font-bold" id="joinWin">—</span></div>
      <div class="flex justify-between"><span class="text-slate-400">House edge</span><span class="text-slate-400">5%</span></div>
      <div class="flex justify-between"><span class="text-slate-400">Your balance after</span><span class="text-white" id="joinBalAfter">—</span></div>
    </div>
    <div class="mb-4">
      <label class="text-slate-400 text-xs mb-1 block">Your Client Seed (optional)</label>
      <input id="joinClientSeed" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs font-mono focus:outline-none focus:border-purple-500 transition-all"/>
    </div>
    <div class="flex gap-3">
      <button onclick="closeModal('joinModal')" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 rounded-xl text-slate-300 font-semibold text-sm transition-all">Cancel</button>
      <button onclick="confirmJoin()" id="joinConfirmBtn" class="flex-1 py-2.5 grad-purple rounded-xl text-white font-bold text-sm hover:opacity-90 transition-all">Join &amp; Bet</button>
    </div>
  </div>
</div>

<!-- ════════════════════════════ RESULT OVERLAY ════════════════════════════ -->
<div id="resultOverlay">
  <div class="text-center animate-bounceIn">
    <div id="resultCoin" class="coin-3d mx-auto mb-6"></div>
    <div id="resultTitle" class="text-4xl font-black mb-2"></div>
    <div id="resultSub" class="text-slate-300 text-lg mb-2"></div>
    <div id="resultVerify" class="text-slate-500 text-xs mb-6 font-mono max-w-xs mx-auto break-all"></div>
    <button onclick="closeResult()" class="px-8 py-3 grad-purple rounded-xl text-white font-bold text-base hover:opacity-90 transition-all neon-glow">Continue</button>
  </div>
</div>

<!-- ════════════════════════════ MAIN LAYOUT ═══════════════════════════════ -->
<div class="flex min-h-screen">

  <!-- ── SIDEBAR ──────────────────────────────────────────────────────────── -->
  <aside class="sidebar fixed left-0 top-0 h-full bg-slate-900 border-r border-slate-800 z-50 flex flex-col py-4 overflow-hidden">
    <div class="flex items-center gap-3 px-4 mb-6">
      <div class="w-9 h-9 shrink-0 rounded-xl grad-purple flex items-center justify-center neon-glow">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      </div>
      <span class="nav-label text-white font-black text-lg">Crypto<span class="text-purple-400">Duel</span></span>
    </div>
    <nav class="flex-1 space-y-1 px-2">
      <?php
      $navItems=[
        ['icon'=>'<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>','label'=>'Coinflip','active'=>true,'badge'=>null],
        ['icon'=>'<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>','label'=>'Jackpot','active'=>false,'badge'=>'HOT'],
        ['icon'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>','label'=>'P2P Duels','active'=>false,'badge'=>null],
        ['icon'=>'<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>','label'=>'Marketplace','active'=>false,'badge'=>null],
        ['icon'=>'<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>','label'=>'Leaderboard','active'=>false,'badge'=>null],
      ];
      foreach($navItems as $it):
      ?>
      <a href="#" class="flex items-center gap-3 px-3 py-3 rounded-xl transition-all <?= $it['active']?'bg-purple-600/20 text-purple-400':'text-slate-400 hover:text-white hover:bg-slate-800' ?> relative">
        <?php if($it['active']): ?><div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-purple-500 rounded-r-full"></div><?php endif; ?>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="shrink-0"><?= $it['icon'] ?></svg>
        <span class="nav-label text-sm font-medium"><?= $it['label'] ?></span>
        <?php if($it['badge']): ?><span class="nav-label level-badge bg-orange-500/20 text-orange-400"><?= $it['badge'] ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="px-2 border-t border-slate-800 pt-4 space-y-1">
      <?php if($isLoggedIn): ?>
      <button onclick="doLogout()" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span class="nav-label text-sm font-medium">Sign Out</span>
      </button>
      <?php endif; ?>
    </div>
  </aside>

  <!-- ── MAIN CONTENT ─────────────────────────────────────────────────────── -->
  <div class="flex-1 flex flex-col main-content" style="margin-left:72px;margin-right:280px" id="mainContent">

    <!-- HEADER -->
    <header class="sticky top-0 z-40 bg-slate-900/95 border-b border-slate-800 backdrop-blur-xl">
      <!-- Live Ticker -->
      <div class="bg-slate-950 border-b border-slate-800/50 py-1.5 overflow-hidden">
        <div class="ticker-wrap"><div class="ticker-move text-xs text-slate-400 flex items-center gap-6" id="tickerContent">
          <span class="text-slate-600">Loading live prices...</span>
        </div></div>
      </div>
      <!-- Header Row -->
      <div class="flex items-center gap-3 px-4 py-3">
        <button onclick="toggleMobileMenu()" class="mobile-nav-bar text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <span class="mobile-nav-bar text-white font-black text-base">Crypto<span class="text-purple-400">Duel</span></span>
        <!-- BTC Price Widget -->
        <div class="hidden sm:flex items-center gap-3 bg-slate-800 rounded-xl px-4 py-2 border border-slate-700">
          <div class="w-6 h-6 rounded-full bg-orange-500/20 flex items-center justify-center"><span class="text-orange-400 font-black text-xs">₿</span></div>
          <div>
            <p class="text-white font-bold text-sm leading-none" id="hdrBtcPrice">—</p>
            <p class="text-xs font-semibold leading-none mt-0.5" id="hdrBtcChange">—</p>
          </div>
          <div class="w-px h-6 bg-slate-700"></div>
          <div class="flex items-center gap-1"><div class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse2"></div><span class="text-green-400 text-xs font-semibold">LIVE</span></div>
        </div>
        <!-- Stats -->
        <div class="hidden lg:flex items-center gap-5 ml-2">
          <div class="text-center"><p class="text-slate-400 text-xs">Online</p><p class="text-green-400 font-bold text-sm" id="hdrOnline">—</p></div>
          <div class="text-center"><p class="text-slate-400 text-xs">Open Bets</p><p class="text-purple-400 font-bold text-sm" id="hdrOpenBets">—</p></div>
        </div>
        <div class="flex-1"></div>
        <!-- Deposit -->
        <button onclick="requireLoginOr(()=>{ loadWalletData(); openModal('walletModal') })" class="flex items-center gap-2 grad-purple text-white px-4 py-2.5 rounded-xl font-bold text-sm hover:opacity-90 transition-all neon-glow relative btn-pulse">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span class="hidden sm:inline">Deposit</span>
        </button>
        <!-- Profile / Login -->
        <?php if($isLoggedIn): ?>
        <div class="flex items-center gap-2 bg-slate-800 rounded-xl px-3 py-2 border border-slate-700 hover:border-slate-600 transition-all cursor-pointer" onclick="requireLoginOr(()=>{ loadWalletData(); openModal('walletModal') })">
          <div class="relative">
            <div class="w-8 h-8 rounded-lg grad-purple flex items-center justify-center font-bold text-white text-xs"><?= strtoupper(substr(htmlspecialchars($user['username']),0,2)) ?></div>
            <div class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-400 rounded-full border border-slate-800"></div>
          </div>
          <div class="hidden sm:block">
            <p class="text-white font-bold text-xs leading-tight"><?= htmlspecialchars($user['username']) ?></p>
            <p class="text-slate-400 text-xs font-mono leading-tight" id="hdrBalance"><?= number_format((float)$user['balance_btc'],5) ?> BTC</p>
          </div>
        </div>
        <?php else: ?>
        <button onclick="openModal('authModal')" class="flex items-center gap-2 bg-slate-800 border border-slate-700 text-slate-300 hover:text-white hover:border-slate-600 px-4 py-2.5 rounded-xl font-bold text-sm transition-all">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Sign In
        </button>
        <?php endif; ?>
        <!-- Notifications -->
        <button class="relative p-2.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-400 hover:text-white transition-all">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <div class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></div>
        </button>
      </div>
    </header>

    <!-- ARENA MAIN -->
    <main class="flex-1 p-4 lg:p-6 pb-20">

      <!-- Arena Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-black text-white">Live <span class="grad-text">Arena</span></h1>
          <p class="text-slate-400 text-sm">Active P2P bets — join to challenge real players</p>
        </div>
        <div class="flex items-center gap-3">
          <div class="flex bg-slate-800 rounded-xl p-1 border border-slate-700">
            <button onclick="filterBets(this,'all')"        class="bet-filter px-3 py-1.5 rounded-lg text-xs font-semibold transition-all bg-purple-600 text-white">All</button>
            <button onclick="filterBets(this,'coinflip')"   class="bet-filter px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-400 hover:text-white">Coinflip</button>
            <button onclick="filterBets(this,'jackpot')"    class="bet-filter px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-400 hover:text-white">Jackpot</button>
            <button onclick="filterBets(this,'p2p_duel')"   class="bet-filter px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-400 hover:text-white">P2P Duel</button>
          </div>
          <button onclick="requireLoginOr(()=>openModal('createBetModal'))" class="flex items-center gap-2 bg-slate-800 border border-purple-500/50 hover:border-purple-500 text-purple-400 hover:text-purple-300 px-4 py-2 rounded-xl font-bold text-sm transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span class="hidden sm:inline">Create Bet</span>
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700">
          <p class="text-slate-400 text-xs mb-1">Open Bets</p>
          <p class="text-white font-black text-xl" id="statOpenBets">—</p>
          <p class="text-slate-500 text-xs">Live right now</p>
        </div>
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700">
          <p class="text-slate-400 text-xs mb-1">Players Online</p>
          <p class="text-green-400 font-black text-xl" id="statOnline">—</p>
          <p class="text-slate-500 text-xs">Active users</p>
        </div>
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700">
          <p class="text-slate-400 text-xs mb-1">Last Winner</p>
          <p class="text-yellow-400 font-black text-lg truncate" id="statLastWinner">—</p>
          <p class="text-slate-500 text-xs" id="statLastWinAmt">—</p>
        </div>
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700">
          <p class="text-slate-400 text-xs mb-1">Games Played</p>
          <p class="text-purple-400 font-black text-xl" id="statGamesPlayed">—</p>
          <p class="text-slate-500 text-xs">All time</p>
        </div>
      </div>

      <!-- Bet Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4" id="betGrid">
        <div class="col-span-full flex flex-col items-center justify-center py-16 text-slate-500">
          <div class="w-12 h-12 border-2 border-purple-500/50 border-t-purple-500 rounded-full animate-spin mb-4"></div>
          Loading live bets...
        </div>
      </div>

      <!-- Recent Winners -->
      <div class="mt-8">
        <h2 class="text-lg font-bold text-white mb-4">Recent <span class="text-yellow-400">Winners</span></h2>
        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead><tr class="border-b border-slate-700">
                <th class="text-left text-slate-400 text-xs font-semibold px-4 py-3">Player</th>
                <th class="text-left text-slate-400 text-xs font-semibold px-4 py-3">Game</th>
                <th class="text-right text-slate-400 text-xs font-semibold px-4 py-3">Bet</th>
                <th class="text-right text-slate-400 text-xs font-semibold px-4 py-3">Won</th>
                <th class="text-right text-slate-400 text-xs font-semibold px-4 py-3">When</th>
              </tr></thead>
              <tbody id="winnersTable" class="divide-y divide-slate-700/50">
                <tr><td colspan="5" class="text-center text-slate-500 text-sm py-6">Loading recent winners...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Provably Fair Footer -->
      <footer class="mt-8 pb-4">
        <div class="bg-slate-800 rounded-2xl p-5 border border-slate-700 mb-6">
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 mb-4">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 rounded-xl bg-green-500/10 border border-green-500/20 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </div>
              <div><h3 class="text-white font-bold">Provably Fair</h3><p class="text-slate-400 text-xs">Every outcome cryptographically verified</p></div>
            </div>
            <div class="flex flex-wrap gap-2 sm:ml-auto">
              <span class="pf-chip"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>SHA-256</span>
              <span class="pf-chip"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>HMAC Verified</span>
              <span class="pf-chip"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>Open Algorithm</span>
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-slate-900 rounded-xl p-3"><p class="text-slate-400 text-xs mb-1">How it works</p><p class="text-slate-300 text-xs leading-relaxed">Server seed hashed before bet. Revealed after. HMAC-SHA256(server+client+nonce) = outcome.</p></div>
            <div class="bg-slate-900 rounded-xl p-3"><p class="text-slate-400 text-xs mb-1">Server Seed</p><p class="text-cyan-400 font-mono text-xs">Revealed after each bet resolves</p></div>
            <div class="bg-slate-900 rounded-xl p-3"><p class="text-slate-400 text-xs mb-1">House Edge</p><p class="text-white font-bold text-lg">5% <span class="text-slate-400 font-normal text-xs">per winning pot</span></p></div>
          </div>
        </div>
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-2"><div class="w-6 h-6 rounded-lg grad-purple flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div><span class="text-white font-black text-sm">Crypto<span class="text-purple-400">Duel</span></span></div>
          <div class="flex gap-4"><a href="#" class="text-slate-500 hover:text-slate-300 text-xs transition-colors">Terms</a><a href="#" class="text-slate-500 hover:text-slate-300 text-xs transition-colors">Privacy</a><a href="#" class="text-slate-500 hover:text-slate-300 text-xs transition-colors">Support</a></div>
          <p class="text-slate-600 text-xs">© 2024 CryptoDuel · 18+ · Gamble responsibly</p>
        </div>
      </footer>
    </main>
  </div>

  <!-- ── LIVE CHAT ─────────────────────────────────────────────────────────── -->
  <aside class="chat-bar fixed right-0 top-0 h-full bg-slate-900 border-l border-slate-800 flex flex-col z-40">
    <div class="p-4 border-b border-slate-800">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2"><div class="w-2 h-2 bg-green-400 rounded-full animate-pulse2"></div><h3 class="text-white font-bold text-sm">Live Chat</h3></div>
        <span class="text-slate-400 text-xs bg-slate-800 px-2 py-0.5 rounded-full" id="chatOnlineCount">— online</span>
      </div>
      <div class="flex bg-slate-800 rounded-lg p-0.5 text-xs">
        <button class="flex-1 py-1.5 rounded-md bg-purple-600 text-white font-semibold">English</button>
        <button class="flex-1 py-1.5 text-slate-400 hover:text-white transition-all">Global</button>
        <button class="flex-1 py-1.5 text-slate-400 hover:text-white transition-all">VIP</button>
      </div>
    </div>
    <div class="chat-msgs flex-1 p-3 space-y-2" id="chatMessages"><div class="text-slate-500 text-xs text-center py-4">Loading chat...</div></div>
    <div class="p-3 border-t border-slate-800">
      <button onclick="requireLoginOr(doRain)" class="w-full flex items-center justify-center gap-2 py-1.5 mb-2 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 hover:border-blue-500/50 rounded-xl text-blue-400 font-semibold text-xs transition-all">🌧️ Send Rain (0.001 BTC)</button>
      <div class="flex gap-2">
        <input id="chatInput" type="text" maxlength="200" placeholder="<?= $isLoggedIn ? 'Type a message...' : 'Sign in to chat' ?>" <?= !$isLoggedIn ? 'readonly' : '' ?>
          class="flex-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-all"
          onkeydown="if(event.key==='Enter') sendChat()"/>
        <button onclick="<?= $isLoggedIn ? 'sendChat()' : 'openModal(\'authModal\')' ?>" class="px-3 py-2.5 grad-purple rounded-xl text-white hover:opacity-90 transition-all">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
      </div>
    </div>
  </aside>
</div>

<!-- Mobile Nav Bar -->
<nav class="mobile-nav-bar fixed bottom-0 left-0 right-0 bg-slate-900 border-t border-slate-800 z-50 px-2 py-2">
  <div class="flex items-center justify-around">
    <?php foreach([['🪙','Coinflip',true],['⭐','Jackpot',false],['⚔️','Duels',false],['💬','Chat',false],['👤','Profile',false]] as [$ic,$lb,$ac]): ?>
    <a href="#" class="flex flex-col items-center gap-1 px-3 py-1 rounded-xl <?= $ac?'text-purple-400':'text-slate-500' ?>">
      <span class="text-lg"><?= $ic ?></span>
      <span class="text-xs font-medium"><?= $lb ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</nav>

<!-- Mobile sidebar overlay -->
<div class="mobile-menu" id="mobileMenu" onclick="toggleMobileMenu()"></div>
<div class="mobile-sidebar" id="mobileSidebar">
  <div class="flex items-center gap-3 px-4 mb-6">
    <div class="w-8 h-8 rounded-xl grad-purple flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
    <span class="text-white font-black text-lg">Crypto<span class="text-purple-400">Duel</span></span>
  </div>
  <nav class="px-3 space-y-1">
    <?php foreach($navItems as $it): ?>
    <a href="#" class="flex items-center gap-3 px-3 py-3 rounded-xl <?= $it['active']?'bg-purple-600/20 text-purple-400':'text-slate-400 hover:text-white hover:bg-slate-800' ?> transition-all">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $it['icon'] ?></svg>
      <span class="text-sm font-medium"><?= $it['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
</div>

<!-- ══════════════════════════════ JAVASCRIPT ══════════════════════════════ -->
<script>
// ── App State ────────────────────────────────────────────────────────────────
const APP = {
  isLoggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
  user:       <?= $user ? json_encode(publicUser($user)) : 'null' ?>,
  prices:     {},
  bets:       [],
  lastChatId: 0,
  betFilter:  'all',
  pendingJoin:null,
  selectedGame:'coinflip',
  selectedSide:'heads',
};

// ── User color palette ───────────────────────────────────────────────────────
const COLORS = ['purple','cyan','green','orange','pink','yellow','red','blue'];
const COLOR_HEX = {purple:'#a855f7',cyan:'#22d3ee',green:'#22c55e',orange:'#f97316',pink:'#ec4899',yellow:'#eab308',red:'#ef4444',blue:'#3b82f6'};
function userColor(id) { return COLORS[id % COLORS.length]; }
function userHex(id)   { return COLOR_HEX[userColor(id)]; }

// ── API helpers ──────────────────────────────────────────────────────────────
async function api(url, opts={}) {
  try {
    const res = await fetch(url, { headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, ...opts });
    return await res.json();
  } catch(e) { return {success:false, error:'Network error'}; }
}

// ── Modals ───────────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('active'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('active'); document.body.style.overflow=''; }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) closeModal(m.id); }));

function requireLoginOr(fn) { if(!APP.isLoggedIn) { openModal('authModal'); } else { fn(); } }

// ── Toast ────────────────────────────────────────────────────────────────────
function toast(msg, type='purple', duration=3500) {
  const t = document.createElement('div');
  const bg = {purple:'bg-purple-600',green:'bg-green-600',red:'bg-red-600',blue:'bg-blue-600',yellow:'bg-yellow-600'}[type]||'bg-purple-600';
  t.className = `toast ${bg} text-white`;
  t.textContent = msg;
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(() => t.remove(), duration);
}

// ── Auth ─────────────────────────────────────────────────────────────────────
function authTab(tab) {
  ['login','register'].forEach(t => {
    document.getElementById('apanel-'+t).classList.toggle('hidden', t!==tab);
    const b = document.getElementById('atab-'+t);
    b.classList.toggle('text-purple-400', t===tab);
    b.classList.toggle('border-purple-500', t===tab);
    b.classList.toggle('text-slate-400', t!==tab);
    b.classList.toggle('border-transparent', t!==tab);
  });
}
async function doLogin() {
  const btn = document.getElementById('loginBtn');
  const err = document.getElementById('loginError');
  btn.textContent = 'Signing in...'; btn.disabled=true;
  const r = await api('/api/auth.php?action=login', {method:'POST', body: JSON.stringify({
    username: document.getElementById('loginIdent').value,
    password: document.getElementById('loginPass').value,
  })});
  btn.disabled=false; btn.textContent='Sign In';
  if(!r.success) { err.textContent=r.error; err.classList.remove('hidden'); return; }
  APP.user=r.user; APP.isLoggedIn=true;
  closeModal('authModal');
  location.reload();
}
async function doRegister() {
  const btn = document.getElementById('regBtn');
  const err = document.getElementById('regError');
  btn.textContent='Creating account...'; btn.disabled=true;
  const r = await api('/api/auth.php?action=register', {method:'POST', body: JSON.stringify({
    username: document.getElementById('regUser').value,
    email:    document.getElementById('regEmail').value,
    password: document.getElementById('regPass').value,
  })});
  btn.disabled=false; btn.textContent='Create Account & Claim Bonus';
  if(!r.success) { err.textContent=r.error; err.classList.remove('hidden'); return; }
  APP.user=r.user; APP.isLoggedIn=true;
  closeModal('authModal');
  toast('Welcome to CryptoDuel! 0.001 BTC bonus added!','green',5000);
  location.reload();
}
async function doLogout() {
  await api('/api/auth.php?action=logout', {method:'POST'});
  location.reload();
}
document.getElementById('loginPass').addEventListener('keydown', e=>{ if(e.key==='Enter') doLogin(); });

// ── Prices ───────────────────────────────────────────────────────────────────
async function loadPrices() {
  const r = await api('/api/prices.php');
  if(!r.success) return;
  APP.prices = r.prices;
  renderTicker(r.prices);
  const btc = r.prices['BTC'];
  if(btc) {
    document.getElementById('hdrBtcPrice').textContent = '$' + btc.price.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    const ce = document.getElementById('hdrBtcChange');
    ce.textContent = (btc.change >= 0 ? '+' : '') + btc.change + '%';
    ce.className = 'text-xs font-semibold leading-none mt-0.5 ' + (btc.change >= 0 ? 'text-green-400' : 'text-red-400');
  }
  updateUsdEst();
}
function renderTicker(prices) {
  const items = Object.entries(prices).map(([sym,d]) => {
    const chg = d.change >= 0 ? `<span class="text-green-400 font-semibold">+${d.change}%</span>` : `<span class="text-red-400 font-semibold">${d.change}%</span>`;
    return `<span class="flex items-center gap-2 whitespace-nowrap"><span class="font-bold text-white">${sym}</span><span class="text-slate-300">$${d.price.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</span>${chg}<span class="text-slate-700">|</span></span>`;
  }).join('');
  const tc = document.getElementById('tickerContent');
  tc.innerHTML = items + items; // double for seamless loop
}

// ── Bets ─────────────────────────────────────────────────────────────────────
async function loadBets() {
  const r = await api('/api/bets.php?action=list');
  if(!r.success) return;
  APP.bets = r.bets;
  renderBets(r.bets);
  document.getElementById('hdrOpenBets').textContent   = r.bets.length;
  document.getElementById('statOpenBets').textContent  = r.bets.length;
}
async function loadRecentBets() {
  const r = await api('/api/bets.php?action=recent');
  if(!r.success) return;
  renderWinners(r.bets);
  if(r.bets.length > 0) {
    const w = r.bets[0];
    document.getElementById('statLastWinner').textContent = w.winner;
    document.getElementById('statLastWinAmt').textContent = '+' + (w.amount_btc * 2 * 0.95).toFixed(4) + ' BTC';
    document.getElementById('statGamesPlayed').textContent = r.bets.length + '+';
  }
}

function gameLabel(g) { return {coinflip:'Coinflip',jackpot:'Jackpot',p2p_duel:'P2P Duel'}[g] || g; }
function gameColor(g) { return {coinflip:'purple',jackpot:'yellow',p2p_duel:'cyan'}[g] || 'purple'; }
function gameIcon(g)  {
  const icons = {
    coinflip: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    jackpot:  '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
    p2p_duel: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  };
  return icons[g] || icons.coinflip;
}
function timeAgo(ts) {
  const s = Math.floor(Date.now()/1000 - ts);
  if(s < 60) return s + 's ago';
  if(s < 3600) return Math.floor(s/60) + 'm ago';
  return Math.floor(s/3600) + 'h ago';
}
function btcToUsd(btc) {
  const price = APP.prices['BTC']?.price || 67000;
  return '$' + (btc * price).toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:0});
}

function renderBets(bets) {
  const grid    = document.getElementById('betGrid');
  const filter  = APP.betFilter;
  const visible = bets.filter(b => filter==='all' || b.game_type===filter);

  if(visible.length === 0) {
    grid.innerHTML = `<div class="col-span-full flex flex-col items-center justify-center py-16 text-slate-500">
      <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.5" class="mb-4"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <p class="font-semibold mb-1">No open bets right now</p>
      <p class="text-sm">Be the first to create one!</p>
    </div>
    <div class="bg-slate-800/50 rounded-2xl border-2 border-dashed border-slate-700 hover:border-purple-500/50 transition-all card-hover cursor-pointer flex flex-col items-center justify-center py-10 px-6" onclick="requireLoginOr(()=>openModal('createBetModal'))">
      <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-dashed border-purple-500/30 flex items-center justify-center mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>
      <p class="text-white font-bold text-sm mb-1">Create a Bet</p>
      <p class="text-slate-500 text-xs text-center">Set your amount and challenge any player</p>
    </div>`;
    return;
  }

  grid.innerHTML = visible.map(b => betCard(b)).join('') +
    `<div class="bg-slate-800/50 rounded-2xl border-2 border-dashed border-slate-700 hover:border-purple-500/50 transition-all card-hover cursor-pointer flex flex-col items-center justify-center py-10 px-6" onclick="requireLoginOr(()=>openModal('createBetModal'))">
      <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-dashed border-purple-500/30 flex items-center justify-center mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>
      <p class="text-white font-bold text-sm mb-1">Create a Bet</p>
      <p class="text-slate-500 text-xs text-center">Set your amount and challenge any player</p>
    </div>`;
}

function betCard(b) {
  const gc  = gameColor(b.game_type);
  const ch  = userHex(b.creator_id);
  const av  = b.creator.substring(0,2).toUpperCase();
  const payout = (b.amount_btc * 2 * 0.95).toFixed(4);
  const isOwnBet = APP.user && APP.user.id === b.creator_id;
  const sideInfo = b.game_type === 'coinflip' && b.creator_side
    ? `<div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-700"><span class="text-slate-400 text-xs">Creator chose:</span><span class="text-white text-xs font-bold px-2 py-0.5 bg-slate-700 rounded">${b.creator_side==='heads'?'🌕 Heads':'🌑 Tails'}</span><span class="text-slate-500 text-xs ml-auto">50/50</span></div>`
    : `<div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-700"><span class="text-slate-500 text-xs">50/50 · Winner takes all</span></div>`;

  return `<div class="bet-card bg-slate-800 rounded-2xl overflow-hidden border border-slate-700 acl-${gc}">
    <div class="p-4">
      <div class="flex items-start justify-between mb-3">
        <div class="flex items-center gap-3">
          <div class="relative">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center font-black text-white text-sm shadow-lg" style="background:${ch}30;border:1px solid ${ch}40;color:${ch}">${av}</div>
            <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-800"></div>
          </div>
          <div>
            <div class="flex items-center gap-1.5">
              <p class="text-white font-bold text-sm">${b.creator}</p>
              <span class="level-badge bg-slate-700 text-slate-300">LVL ${b.level}</span>
            </div>
            <p class="text-slate-500 text-xs mt-0.5">${b.wins}W / ${b.losses}L · ${timeAgo(b.created_at)}</p>
          </div>
        </div>
        <div class="flex items-center gap-1 px-2.5 py-1 rounded-lg" style="background:${COLOR_HEX[gc]}15;border:1px solid ${COLOR_HEX[gc]}40">
          <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="${COLOR_HEX[gc]}" stroke-width="2">${gameIcon(b.game_type)}</svg>
          <span class="text-xs font-bold" style="color:${COLOR_HEX[gc]}">${gameLabel(b.game_type)}</span>
        </div>
      </div>
      <div class="bg-slate-900 rounded-xl p-3 mb-3">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-400 text-xs mb-1">Bet</p>
            <p class="text-white font-black text-xl">${b.amount_btc.toFixed(4)} <span class="text-orange-400 text-sm">BTC</span></p>
            <p class="text-slate-500 text-xs">${btcToUsd(b.amount_btc)}</p>
          </div>
          <div class="text-right">
            <p class="text-slate-400 text-xs mb-1">Win</p>
            <p class="text-green-400 font-black text-xl">${payout}</p>
            <p class="text-slate-500 text-xs">BTC</p>
          </div>
        </div>
        ${sideInfo}
      </div>
    </div>
    <div class="px-4 pb-4 flex gap-2">
      ${isOwnBet
        ? `<button onclick="cancelBet(${b.id})" class="flex-1 py-2.5 bg-slate-700 hover:bg-red-900/30 border border-slate-600 hover:border-red-500/50 rounded-xl text-slate-300 hover:text-red-400 font-bold text-sm transition-all">Cancel</button>`
        : `<button onclick="startJoin(${b.id})" class="flex-1 py-2.5 grad-purple rounded-xl text-white font-bold text-sm hover:opacity-90 flex items-center justify-center gap-2 neon-glow relative btn-pulse">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>Join</button>`}
      <button class="p-2.5 bg-slate-700 hover:bg-slate-600 rounded-xl text-slate-400 hover:text-white transition-all" title="Provably Fair: ${b.seed_hash.substring(0,16)}...">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </button>
    </div>
  </div>`;
}

function renderWinners(bets) {
  const tbody = document.getElementById('winnersTable');
  if(!bets.length) { tbody.innerHTML='<tr><td colspan="5" class="text-center text-slate-500 text-sm py-6">No games played yet — be the first!</td></tr>'; return; }
  tbody.innerHTML = bets.map(b => {
    const profit = (b.amount_btc * 2 * 0.95).toFixed(4);
    const gameC  = gameColor(b.game_type);
    return `<tr class="hover:bg-slate-700/30 transition-colors">
      <td class="px-4 py-3">
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs" style="background:${userHex(0)}20;color:${userHex(0)}">${b.winner.substring(0,2).toUpperCase()}</div>
          <span class="text-white text-sm font-semibold">${b.winner}</span>
        </div>
      </td>
      <td class="px-4 py-3"><span class="text-sm" style="color:${COLOR_HEX[gameC]}">${gameLabel(b.game_type)}</span></td>
      <td class="px-4 py-3 text-right"><span class="text-white text-sm font-semibold">${b.amount_btc.toFixed(4)} BTC</span></td>
      <td class="px-4 py-3 text-right"><span class="text-green-400 text-sm font-bold">+${profit} BTC</span></td>
      <td class="px-4 py-3 text-right"><span class="text-slate-400 text-xs">${timeAgo(b.resolved_at)}</span></td>
    </tr>`;
  }).join('');
}

function filterBets(btn, game) {
  document.querySelectorAll('.bet-filter').forEach(b => { b.classList.remove('bg-purple-600','text-white'); b.classList.add('text-slate-400'); });
  btn.classList.add('bg-purple-600','text-white'); btn.classList.remove('text-slate-400');
  APP.betFilter = game;
  renderBets(APP.bets);
}

// ── Join Bet ─────────────────────────────────────────────────────────────────
function startJoin(betId) {
  if(!APP.isLoggedIn) { openModal('authModal'); return; }
  const bet = APP.bets.find(b => b.id === betId);
  if(!bet) { toast('Bet no longer available','red'); loadBets(); return; }
  APP.pendingJoin = bet;

  const joinerSide = bet.game_type === 'coinflip' && bet.creator_side
    ? (bet.creator_side === 'heads' ? '🌑 Tails' : '🌕 Heads')
    : 'Random';
  document.getElementById('joinDetails').textContent = `${bet.creator} challenged the arena — ${gameLabel(bet.game_type)}. You get: ${joinerSide}`;
  document.getElementById('joinAmt').textContent    = bet.amount_btc.toFixed(5) + ' BTC';
  document.getElementById('joinWin').textContent    = (bet.amount_btc * 2 * 0.95).toFixed(5) + ' BTC';
  document.getElementById('joinBalAfter').textContent = APP.user
    ? (APP.user.balance - bet.amount_btc).toFixed(5) + ' BTC' : '—';
  document.getElementById('joinClientSeed').value   = Math.random().toString(36).substring(2,10);
  openModal('joinModal');
}

async function confirmJoin() {
  const bet = APP.pendingJoin;
  if(!bet) return;
  const btn = document.getElementById('joinConfirmBtn');
  btn.textContent='Resolving...'; btn.disabled=true;

  const clientSeed = document.getElementById('joinClientSeed').value || '';
  const r = await api('/api/bets.php?action=join', {method:'POST', body: JSON.stringify({bet_id: bet.id, client_seed: clientSeed})});
  btn.textContent='Join & Bet'; btn.disabled=false;

  if(!r.success) { toast(r.error, 'red'); closeModal('joinModal'); loadBets(); return; }

  closeModal('joinModal');
  showResult(r);

  if(APP.user) { APP.user.balance = r.balance; updateBalanceDisplay(r.balance); }
  loadBets();
  loadRecentBets();
}

// ── Create Bet ────────────────────────────────────────────────────────────────
function pickGame(g, btn) {
  APP.selectedGame = g;
  document.querySelectorAll('.game-pick').forEach(b => { b.className='game-pick py-2.5 bg-slate-800 border border-slate-700 hover:border-slate-600 rounded-xl text-slate-400 text-xs font-bold transition-all'; });
  btn.className='game-pick py-2.5 bg-purple-600/20 border border-purple-500 rounded-xl text-purple-400 text-xs font-bold transition-all';
  document.getElementById('sidePickerRow').style.display = g==='coinflip' ? '' : 'none';
}
function pickSide(s, btn) {
  APP.selectedSide = s;
  document.querySelectorAll('.side-pick').forEach(b => { b.classList.remove('bg-purple-600/20','border-purple-500','text-purple-300'); b.classList.add('bg-slate-800','border-slate-700','text-slate-300'); });
  btn.classList.add('bg-purple-600/20','border-purple-500','text-purple-300');
  btn.classList.remove('bg-slate-800','border-slate-700','text-slate-300');
}
function updateUsdEst() {
  const amt = parseFloat(document.getElementById('cbAmount')?.value||0);
  const price = APP.prices['BTC']?.price || 67000;
  const est   = (amt * price).toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:0});
  const win   = (amt * 2 * 0.95).toFixed(5);
  const el = document.getElementById('cbUsdEst'); if(el) el.textContent = `≈ $${est}`;
  const pw = document.getElementById('cbPotentialWin'); if(pw) pw.textContent = win + ' BTC';
}
document.getElementById('cbAmount').addEventListener('input', updateUsdEst);
async function doCreateBet() {
  const btn = document.getElementById('cbBtn');
  const err = document.getElementById('cbError');
  err.classList.add('hidden');
  const amount = parseFloat(document.getElementById('cbAmount').value||0);
  if(!amount || amount < 0.0001) { err.textContent='Min bet: 0.0001 BTC'; err.classList.remove('hidden'); return; }
  btn.textContent='Placing bet...'; btn.disabled=true;

  const payload = {game_type: APP.selectedGame, amount_btc: amount};
  if(APP.selectedGame==='coinflip') payload.creator_side = APP.selectedSide;

  const r = await api('/api/bets.php?action=create', {method:'POST', body: JSON.stringify(payload)});
  btn.textContent='Place Bet'; btn.disabled=false;

  if(!r.success) { err.textContent=r.error; err.classList.remove('hidden'); return; }
  closeModal('createBetModal');
  toast('Bet created! Waiting for a challenger...','purple');
  if(APP.user) { APP.user.balance = r.balance; updateBalanceDisplay(r.balance); }
  loadBets();
}
async function cancelBet(betId) {
  const r = await api('/api/bets.php?action=cancel', {method:'POST', body: JSON.stringify({bet_id: betId})});
  if(!r.success) { toast(r.error,'red'); return; }
  toast('Bet cancelled — funds returned','green');
  if(APP.user) { APP.user.balance = r.balance; updateBalanceDisplay(r.balance); }
  loadBets();
}

// ── Result Overlay ────────────────────────────────────────────────────────────
function showResult(r) {
  const ov    = document.getElementById('resultOverlay');
  const coin  = document.getElementById('resultCoin');
  const title = document.getElementById('resultTitle');
  const sub   = document.getElementById('resultSub');
  const verify= document.getElementById('resultVerify');

  const isHeads = r.outcome === 'heads';
  coin.innerHTML = `<div class="w-20 h-20 rounded-full ${r.i_won?'bg-green-500/20 border-2 border-green-500':'bg-red-500/20 border-2 border-red-500'} flex items-center justify-center text-4xl spin-active">${isHeads ? '🌕' : '🌑'}</div>`;

  if(r.i_won) {
    title.innerHTML = '<span class="text-green-400">YOU WON!</span>';
    sub.innerHTML   = `+${r.payout.toFixed(5)} BTC added to your balance`;
    document.body.style.background = '';
  } else {
    title.innerHTML = '<span class="text-red-400">BETTER LUCK!</span>';
    sub.innerHTML   = `Outcome: ${r.outcome} · Try again!`;
  }
  verify.innerHTML = `Seed: ${r.server_seed.substring(0,20)}... | Client: ${r.client_seed}`;
  ov.classList.add('active');
}
function closeResult() {
  document.getElementById('resultOverlay').classList.remove('active');
}

// ── Chat ─────────────────────────────────────────────────────────────────────
async function loadChat() {
  const r = await api(`/api/chat.php?action=get&since_id=${APP.lastChatId}`);
  if(!r.success || !r.messages.length) return;

  const box = document.getElementById('chatMessages');
  const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 50;

  if(APP.lastChatId === 0) box.innerHTML = '';

  r.messages.forEach(m => {
    if(m.id <= APP.lastChatId) return;
    APP.lastChatId = Math.max(APP.lastChatId, m.id);
    const ch = userHex(m.user_id);
    const av = m.username.substring(0,1).toUpperCase();
    const time= new Date(m.ts*1000).toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',hour12:false});

    let html;
    if(m.type === 'rain') {
      html = `<div class="bg-blue-500/10 border border-blue-500/30 rounded-xl p-2.5 text-center"><p class="text-blue-400 text-xs font-bold">🌧️ RAIN</p><p class="text-white text-xs">${m.message}</p></div>`;
    } else {
      html = `<div class="flex items-start gap-2 group animate-fadeIn">
        <div class="w-6 h-6 rounded-lg flex items-center justify-center font-bold text-white text-xs shrink-0 mt-0.5" style="background:${ch}25;color:${ch}">${av}</div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span class="font-bold text-xs" style="color:${ch}">${m.username}</span>
            <span class="level-badge bg-slate-800 text-slate-400">${m.level}</span>
            <span class="text-slate-600 text-xs ml-auto">${time}</span>
          </div>
          <p class="text-slate-300 text-xs leading-relaxed break-words">${m.message}</p>
        </div>
      </div>`;
    }
    box.insertAdjacentHTML('beforeend', html);
  });

  // Trim old messages (keep last 100)
  while(box.children.length > 100) box.removeChild(box.firstChild);
  if(atBottom || APP.lastChatId === r.messages[r.messages.length-1]?.id) box.scrollTop = box.scrollHeight;
}

async function sendChat() {
  if(!APP.isLoggedIn) { openModal('authModal'); return; }
  const inp = document.getElementById('chatInput');
  const msg = inp.value.trim();
  if(!msg) return;
  inp.value = '';
  const r = await api('/api/chat.php?action=send', {method:'POST', body: JSON.stringify({message: msg})});
  if(!r.success) { toast(r.error,'red'); inp.value=msg; }
  else loadChat();
}

async function doRain() {
  const r = await api('/api/chat.php?action=rain', {method:'POST'});
  if(!r.success) { toast(r.error,'red'); return; }
  toast(`Rain sent to ${r.recipients} players!`,'blue');
  if(APP.user) { APP.user.balance=r.balance; updateBalanceDisplay(r.balance); }
  loadChat();
}

// ── Wallet ────────────────────────────────────────────────────────────────────
function walletTab(tab) {
  ['deposit','withdraw','history','demo'].forEach(t => {
    document.getElementById('wpanel-'+t).classList.toggle('hidden', t!==tab);
    const b = document.getElementById('wtab-'+t);
    b.classList.toggle('text-purple-400', t===tab);
    b.classList.toggle('border-purple-500', t===tab);
    b.classList.toggle('text-slate-400', t!==tab);
    b.classList.toggle('border-transparent', t!==tab);
  });
  if(tab==='history') loadTxHistory();
}
async function loadWalletData() {
  if(!APP.isLoggedIn) return;
  document.getElementById('wModalBalance').textContent = APP.user ? APP.user.balance.toFixed(5) : '—';
  document.getElementById('wdrawBal').textContent = APP.user ? APP.user.balance.toFixed(5) + ' BTC' : '—';

  const r = await api('/api/wallet.php?action=address');
  if(r.success) document.getElementById('walletAddr').textContent = r.address;
}
async function loadTxHistory() {
  const r = await api('/api/wallet.php?action=history');
  const el = document.getElementById('txList');
  if(!r.success) { el.innerHTML='<p class="text-red-400 text-xs">Failed to load</p>'; return; }
  if(!r.transactions.length) { el.innerHTML='<p class="text-slate-500 text-sm text-center py-4">No transactions yet</p>'; return; }
  el.innerHTML = r.transactions.map(t => {
    const isPos = t.amount > 0;
    const ico   = isPos ? '#22c55e' : '#ef4444';
    const arrow = isPos
      ? '<polyline points="5 12 12 5 19 12"/>'
      : '<polyline points="19 12 12 19 5 12"/>';
    const statusC = t.status==='confirmed' ? 'text-green-400' : 'text-yellow-400';
    const dot     = t.status==='confirmed' ? 'bg-green-400' : 'bg-yellow-400 animate-pulse2';
    const d = new Date(t.ts*1000).toLocaleDateString();
    return `<div class="flex items-center gap-3 p-3 bg-slate-800 rounded-xl">
      <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:${ico}15">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="${ico}" stroke-width="2"><line x1="12" y1="${isPos?19:5}" x2="12" y2="${isPos?5:19}"/>${arrow}</svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-white text-sm font-semibold capitalize">${t.type.replace('_',' ')}</p>
        <p class="text-slate-500 text-xs truncate">${t.notes||t.hash||'—'}</p>
      </div>
      <div class="text-right shrink-0">
        <p class="text-sm font-bold ${isPos?'text-green-400':'text-red-400'}">${isPos?'+':''}${t.amount.toFixed(5)} BTC</p>
        <div class="flex items-center gap-1 justify-end"><div class="w-1.5 h-1.5 rounded-full ${dot}"></div><span class="text-xs ${statusC}">${t.status}</span></div>
      </div>
    </div>`;
  }).join('');
}
function setMaxWithdraw() {
  if(!APP.user) return;
  const max = Math.max(0, APP.user.balance - 0.00002);
  document.getElementById('wdrawAmt').value = max.toFixed(5);
}
async function doWithdraw() {
  const addr = document.getElementById('wdrawAddr').value.trim();
  const amt  = parseFloat(document.getElementById('wdrawAmt').value||0);
  const err  = document.getElementById('wdrawError');
  err.classList.add('hidden');
  const r = await api('/api/wallet.php?action=withdraw', {method:'POST', body: JSON.stringify({address:addr, amount_btc:amt})});
  if(!r.success) { err.textContent=r.error; err.classList.remove('hidden'); return; }
  toast(r.message,'green');
  if(APP.user) { APP.user.balance=r.balance; updateBalanceDisplay(r.balance); }
  walletTab('history');
}
async function claimDemo() {
  const btn = document.getElementById('claimBtn');
  const msg = document.getElementById('demoMsg');
  btn.textContent='Claiming...'; btn.disabled=true;
  const r = await api('/api/wallet.php?action=claim_demo', {method:'POST'});
  btn.textContent='Claim 0.0005 BTC'; btn.disabled=false;
  msg.classList.remove('hidden');
  if(r.success) {
    msg.className='mb-4 p-3 rounded-xl text-sm text-center bg-green-500/10 border border-green-500/30 text-green-400';
    msg.textContent=r.message+' Balance: '+r.balance.toFixed(5)+' BTC';
    if(APP.user) { APP.user.balance=r.balance; updateBalanceDisplay(r.balance); }
    document.getElementById('wModalBalance').textContent=r.balance.toFixed(5);
  } else {
    msg.className='mb-4 p-3 rounded-xl text-sm text-center bg-red-500/10 border border-red-500/30 text-red-400';
    msg.textContent=r.error;
  }
}
function copyAddr() {
  const addr = document.getElementById('walletAddr').textContent;
  navigator.clipboard.writeText(addr).then(()=>toast('Address copied!','green'));
}

// ── Online Count ──────────────────────────────────────────────────────────────
async function loadOnline() {
  const r = await api('/api/chat.php?action=online');
  if(!r.success) return;
  const c = r.online;
  document.getElementById('hdrOnline').textContent      = c.toLocaleString();
  document.getElementById('statOnline').textContent     = c.toLocaleString();
  document.getElementById('chatOnlineCount').textContent= c.toLocaleString() + ' online';
}

// ── Balance display ───────────────────────────────────────────────────────────
function updateBalanceDisplay(bal) {
  const el = document.getElementById('hdrBalance');
  if(el) el.textContent = bal.toFixed(5) + ' BTC';
  const wm = document.getElementById('wModalBalance');
  if(wm) wm.textContent = bal.toFixed(5);
  const wd = document.getElementById('wdrawBal');
  if(wd) wd.textContent = bal.toFixed(5) + ' BTC';
}

// ── Mobile menu ───────────────────────────────────────────────────────────────
function toggleMobileMenu() {
  document.getElementById('mobileSidebar').classList.toggle('active');
  document.getElementById('mobileMenu').classList.toggle('active');
}

// ── Init ──────────────────────────────────────────────────────────────────────
async function init() {
  await loadPrices();
  await Promise.all([loadBets(), loadChat(), loadOnline(), loadRecentBets()]);

  // Set intervals
  setInterval(loadPrices,    60000);
  setInterval(loadBets,       5000);
  setInterval(loadChat,       3000);
  setInterval(loadOnline,    30000);
  setInterval(loadRecentBets,15000);
}

init();
</script>
</body>
</html>
