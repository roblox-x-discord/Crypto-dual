<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$user = null; $isLoggedIn = false;
if (!empty($_SESSION['user_id'])) {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE id=?');
    $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($user) { $isLoggedIn = true; $db->exec("UPDATE users SET last_seen=strftime('%s','now') WHERE id={$user['id']}"); }
    else { session_destroy(); $user = null; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover"/>
<title>CryptoDuel — Tic Tac Toe</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%;margin:0}
body{background:#0f172a;color:#e2e8f0;font-family:'Inter',sans-serif;overflow-x:hidden}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:#1e293b}
::-webkit-scrollbar-thumb{background:#a855f7;border-radius:2px}

/* ── Layout ── */
.layout{display:flex;min-height:100vh}
.sidebar-panel{width:72px;flex-shrink:0;position:fixed;left:0;top:0;height:100%;background:#0f172a;border-right:1px solid #1e293b;z-index:50;display:flex;flex-direction:column;transition:width .25s ease;overflow:hidden}
.sidebar-panel:hover{width:200px}
.sidebar-panel:hover .nav-lbl{opacity:1;max-width:140px}
.nav-lbl{opacity:0;max-width:0;overflow:hidden;white-space:nowrap;transition:all .25s ease;font-size:.875rem;font-weight:600}
.main-col{flex:1;display:flex;flex-direction:column;margin-left:352px;margin-right:0;min-height:100vh}
.chat-col{width:280px;flex-shrink:0;position:fixed;left:72px;top:0;height:100%;background:#0f172a;border-right:1px solid #1e293b;z-index:45;display:flex;flex-direction:column}

/* ── Tablet & Mobile ── */
@media(max-width:1023px){
  .sidebar-panel{display:none}
  .chat-col{display:none}
  .main-col{margin:0}
  .bottom-nav{display:none}
  .show-on-desktop{display:none !important}
  .show-on-mobile{display:block !important}
}
@media(min-width:1024px){
  .bottom-nav{display:none !important}
  .show-on-mobile{display:none !important}
}
.show-on-mobile{display:none}
.bottom-nav{display:none}

/* ── Ticker ── */
.ticker-wrap{overflow:hidden;white-space:nowrap}
.ticker-inner{display:inline-flex;gap:24px;animation:ticker 50s linear infinite}
@keyframes ticker{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}

/* ── Glows & cards ── */
.neon{box-shadow:0 0 18px rgba(168,85,247,.35),0 0 40px rgba(168,85,247,.1)}
.card{background:#1e293b;border:1px solid #334155;border-radius:16px;transition:border-color .2s,transform .2s,box-shadow .2s}
.card:hover{border-color:rgba(168,85,247,.5);transform:translateY(-2px);box-shadow:0 8px 24px rgba(168,85,247,.15)}
.g-purple{background:linear-gradient(135deg,#7e22ce,#a855f7)}
.g-text{background:linear-gradient(135deg,#a855f7,#22d3ee);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

/* ── Modals ── */
.overlay{position:fixed;inset:0;background:rgba(2,6,23,.9);z-index:100;display:none;align-items:center;justify-content:center;backdrop-filter:blur(6px);padding:16px}
.overlay.open{display:flex}
.modal-box{background:#0f172a;border:1px solid rgba(168,85,247,.3);border-radius:20px;width:100%;max-width:460px;max-height:90vh;overflow-y:auto;overflow-x:hidden;animation:popIn .3s ease-out}
@media(max-width:640px){.modal-box{max-width:90%;border-radius:16px;max-height:85vh}}
@keyframes popIn{0%{transform:scale(.94) translateY(12px);opacity:0}100%{transform:scale(1) translateY(0);opacity:1}}

/* ── TTT Board ── */
.ttt-board{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;width:100%;max-width:300px;margin:0 auto}
@media(max-width:640px){.ttt-board{max-width:280px}}
.ttt-cell{aspect-ratio:1;background:#1e293b;border:2px solid #334155;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:2.2rem;font-weight:900;cursor:default;transition:all .15s ease;user-select:none}
.ttt-cell.clickable{cursor:pointer}
.ttt-cell.clickable:hover{border-color:#a855f7;background:rgba(168,85,247,.12)}
.ttt-cell.clickable:active{transform:scale(.93)}
.ttt-cell.x{color:#a855f7;border-color:rgba(168,85,247,.5);background:rgba(168,85,247,.08)}
.ttt-cell.o{color:#22d3ee;border-color:rgba(34,211,238,.5);background:rgba(34,211,238,.08)}
.ttt-cell.win{background:rgba(34,197,94,.15);border-color:#22c55e;animation:winPulse .5s ease}
@keyframes winPulse{0%{transform:scale(1)}50%{transform:scale(1.08)}100%{transform:scale(1)}}
.ttt-cell .sym{display:block;line-height:1;transition:transform .1s}
.ttt-cell.x .sym{text-shadow:0 0 12px rgba(168,85,247,.7)}
.ttt-cell.o .sym{text-shadow:0 0 12px rgba(34,211,238,.7)}

/* ── Toasts ── */
.toast-stack{position:fixed;top:72px;right:16px;z-index:200;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.toast{padding:10px 16px;border-radius:12px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;pointer-events:auto;animation:slideDown .3s ease;max-width:300px}
@keyframes slideDown{0%{transform:translateY(-12px);opacity:0}100%{transform:translateY(0);opacity:1}}
@media(max-width:640px){.toast-stack{top:auto;bottom:80px;right:8px;left:8px}.toast{max-width:100%}}

/* ── Mobile chat drawer ── */
.chat-drawer{position:fixed;inset:0;z-index:150;display:none}
.chat-drawer.open{display:flex;flex-direction:column;justify-content:flex-end}
.chat-drawer-bg{position:absolute;inset:0;background:rgba(2,6,23,.8)}
.chat-drawer-box{position:relative;background:#0f172a;border-top:1px solid #334155;border-radius:20px 20px 0 0;height:75vh;display:flex;flex-direction:column;animation:slideUp .3s ease}
@keyframes slideUp{0%{transform:translateY(100%)}100%{transform:translateY(0)}}

/* ── Misc ── */
.badge{font-size:9px;padding:2px 6px;border-radius:4px;font-weight:700}
.pf-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);border-radius:20px;font-size:11px;color:#22c55e}
.spin{animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.fade-in{animation:fadeIn .3s ease}
@keyframes fadeIn{0%{opacity:0}100%{opacity:1}}
input:focus,textarea:focus{outline:none;border-color:#a855f7 !important;box-shadow:0 0 0 3px rgba(168,85,247,.15)}
.tab-btn{padding:10px;font-size:12px;font-weight:700;border-bottom:2px solid transparent;color:#64748b;transition:all .2s}
.tab-btn.active{color:#a855f7;border-bottom-color:#a855f7}
.section{display:none}.section.active{display:block}
.nav-item{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;cursor:pointer;transition:all .2s;color:#64748b;position:relative;text-decoration:none}
.nav-item:hover{background:rgba(168,85,247,.1);color:#c4b5fd}
.nav-item.active{background:rgba(168,85,247,.15);color:#a855f7}
.nav-item.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:28px;background:#a855f7;border-radius:0 4px 4px 0}

/* ── Mobile centering ── */
@media(max-width:1023px){
  .main-col{margin:0}
  .layout{flex-direction:column}
  .toast-stack{top:auto;bottom:20px;right:8px;left:8px}
  .toast{max-width:calc(100% - 16px)}
  /* Center main content on tablet/mobile */
  main{max-width:640px;margin:0 auto;padding-left:16px;padding-right:16px}
}
@media(max-width:768px){
  .section{padding:12px 8px}
  .card{border-radius:12px}
  /* Center modal on mobile */
  .modal-box{margin:auto;max-width:90%;border-radius:16px}
  .modal-box .p-5 {padding:16px}
  /* Center content on mobile with tighter spacing */
  main{padding:12px;max-width:100%}
  /* Smaller gaps on mobile */
  .grid{gap:8px !important}
  .grid.grid-cols-2{display:grid;grid-template-columns:repeat(2,1fr) !important;gap:8px}
  .grid.grid-cols-4{display:grid;grid-template-columns:repeat(2,1fr) !important;gap:8px}
  /* Table horizontal scroll on mobile */
  table{min-width:500px}
  /* Tighter card spacing on mobile */
  .card p, .card div{line-height:1.4}
}
</style>
</head>
<body>

<!-- ════ TOAST STACK ════ -->
<div class="toast-stack" id="toasts"></div>

<!-- ════════════════════════ MODALS ════════════════════════ -->

<!-- AUTH -->
<div class="overlay" id="authModal">
 <div class="modal-box" style="max-width:420px">
  <div class="flex border-b border-slate-800">
   <button class="tab-btn active flex-1" id="atab-login" onclick="authTab('login')">Sign In</button>
   <button class="tab-btn flex-1" id="atab-reg" onclick="authTab('reg')">Create Account</button>
  </div>
  <!-- Login -->
  <div id="apanel-login" class="p-6">
   <div class="flex items-center gap-3 mb-5">
    <div class="w-10 h-10 rounded-xl g-purple flex items-center justify-center neon shrink-0">
     <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
    </div>
    <div><p class="text-white font-black text-xl">Welcome Back</p><p class="text-slate-400 text-xs">Sign in to play</p></div>
   </div>
   <div id="loginErr" class="hidden mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"></div>
   <div class="space-y-3">
    <input id="loginIdent" type="text" placeholder="Username or email" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500"/>
    <input id="loginPass" type="password" placeholder="Password" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500"
      onkeydown="if(event.key==='Enter')doLogin()"/>
    <button onclick="doLogin()" id="loginBtn" class="w-full py-3 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon">Sign In</button>
   </div>
   <p class="text-center text-slate-500 text-xs mt-4">No account? <button onclick="authTab('reg')" class="text-purple-400">Register free</button></p>
  </div>
  <!-- Register -->
  <div id="apanel-reg" class="p-6 hidden">
   <div class="flex items-center gap-3 mb-5">
    <div class="w-10 h-10 rounded-xl g-purple flex items-center justify-center neon shrink-0">
     <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
    </div>
    <div><p class="text-white font-black text-xl">Create Account</p><p class="text-green-400 text-xs font-semibold">Get 0.001 BTC welcome bonus!</p></div>
   </div>
   <div id="regErr" class="hidden mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"></div>
   <div class="space-y-3">
    <input id="regUser" type="text" placeholder="Username (3–24 chars, unique)" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500"/>
    <input id="regEmail" type="email" placeholder="Email address" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500"/>
    <input id="regPass" type="password" placeholder="Password (min 6 chars)" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500"
      onkeydown="if(event.key==='Enter')doRegister()"/>
    <button onclick="doRegister()" id="regBtn" class="w-full py-3 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon">Create Account &amp; Claim Bonus</button>
   </div>
   <p class="text-center text-slate-500 text-xs mt-4">Have account? <button onclick="authTab('login')" class="text-purple-400">Sign In</button></p>
  </div>
 </div>
</div>

<!-- WALLET -->
<div class="overlay" id="walletModal">
 <div class="modal-box" style="max-width:500px;max-height:92vh;overflow-y:auto">
  <div class="flex items-center justify-between p-5 border-b border-slate-800 sticky top-0 bg-slate-950 z-10">
   <div class="flex items-center gap-3">
    <div class="w-8 h-8 rounded-lg g-purple flex items-center justify-center">
     <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    </div>
    <div>
     <p class="text-white font-bold text-base">Wallet</p>
     <p class="text-slate-400 text-xs">Balance: <span class="text-purple-300 font-bold" id="wBal">—</span> BTC</p>
    </div>
   </div>
   <button onclick="closeModal('walletModal')" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
   </button>
  </div>
  <div class="flex border-b border-slate-800">
   <button onclick="wTab('deposit')"  class="tab-btn active flex-1" id="wtab-deposit">Deposit</button>
   <button onclick="wTab('withdraw')" class="tab-btn flex-1" id="wtab-withdraw">Withdraw</button>
   <button onclick="wTab('history')"  class="tab-btn flex-1" id="wtab-history">History</button>
  </div>
  <!-- Deposit -->
  <div id="wpanel-deposit" class="p-5">
   <p class="text-slate-400 text-sm text-center mb-4">Direct Deposit — Send BTC/LTC to receive 80% after 20% platform fee</p>

   <!-- Currency Selector -->
   <div class="grid grid-cols-2 gap-2 mb-4">
    <button onclick="selectDepositCurrency('BTC')" id="currBtnBTC" class="flex items-center justify-center gap-2 p-3 bg-purple-500/20 border-2 border-purple-500 rounded-xl transition-all">
     <span class="text-orange-400 font-bold text-xl">₿</span>
     <div class="text-left">
      <p class="text-white font-bold text-sm">Bitcoin</p>
      <p class="text-slate-400 text-xs">BTC</p>
     </div>
    </button>
    <button onclick="selectDepositCurrency('LTC')" id="currBtnLTC" class="flex items-center justify-center gap-2 p-3 bg-slate-800 border-2 border-slate-700 rounded-xl transition-all hover:border-slate-600">
     <span class="text-slate-300 font-bold text-xl">Ł</span>
     <div class="text-left">
      <p class="text-white font-bold text-sm">Litecoin</p>
      <p class="text-slate-400 text-xs">LTC</p>
     </div>
    </button>
   </div>

   <!-- Deposit Address Display -->
   <div class="bg-slate-800 rounded-xl p-4 mb-4">
    <label class="text-slate-400 text-xs font-medium mb-2 block">Send your <span id="depositCurrencyName">Bitcoin</span> to this address:</label>
    <div class="flex items-center gap-2">
     <input id="depositAddress" type="text" readonly value="bc1qy0cma0nhur3kggfg8uh8tmsu4kn2mces2gvp9h" class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
     <button onclick="copyDepositAddress()" class="px-3 py-2 bg-purple-500/20 border border-purple-500/40 rounded-lg text-purple-400 text-xs font-semibold hover:bg-purple-500/30">
      Copy
     </button>
    </div>
    <p class="text-slate-500 text-xs mt-2">Minimum deposit: 0.0001 BTC / 0.001 LTC • 1+ confirmation required</p>
   </div>

   <!-- Verify Transaction Section -->
   <div class="border-t border-slate-700 pt-4 mt-4">
    <p class="text-slate-400 text-xs font-semibold mb-3">After sending, verify your transaction to receive credits:</p>
    <div class="space-y-3">
     <div>
      <label class="text-slate-400 text-xs font-medium mb-2 block">Transaction ID (TXID)</label>
      <input id="txidInput" type="text" placeholder="Paste your transaction hash here..." class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 font-mono text-xs">
     </div>
     <button onclick="verifyTransaction()" id="verifyBtn" class="w-full py-3 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon">
      Verify & Credit Deposit
     </button>
     <div id="verifyResult" class="hidden p-3 rounded-xl text-sm"></div>
    </div>
   </div>

   <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-3 text-xs text-blue-300 leading-relaxed mt-4">
    <strong>How to deposit:</strong>
    <ol class="list-decimal list-inside mt-2 space-y-1">
     <li>Send BTC/LTC to the address above</li>
     <li>Wait for 1+ blockchain confirmation</li>
     <li>Copy your Transaction ID (TXID) from your wallet or block explorer</li>
     <li>Paste the TXID above and click "Verify & Credit"</li>
     <li>You'll receive 80% of your deposit (20% platform fee)</li>
    </ol>
   </div>
  </div>
  <!-- Withdraw -->
  <div id="wpanel-withdraw" class="p-5 hidden">
   <div class="flex items-center justify-between bg-slate-800 rounded-xl p-3 mb-4">
    <span class="text-slate-400 text-sm">Your balance</span>
    <span class="text-white font-bold" id="wdrawBal">— BTC</span>
   </div>
   <div id="wdrawErr" class="hidden mb-3 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"></div>
   <div class="space-y-3">
    <input id="wdrawAddr" type="text" placeholder="Your BTC address (bc1q...)" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500"/>
    <div class="relative">
     <input id="wdrawAmt" type="number" step="0.00001" min="0.001" placeholder="Amount (BTC)" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 pr-14"/>
     <button onclick="maxWd()" class="absolute right-3 top-1/2 -translate-y-1/2 text-purple-400 text-xs font-bold hover:text-purple-300">MAX</button>
    </div>
    <button onclick="doWithdraw()" class="w-full py-3 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon">Request Withdrawal</button>
    <p class="text-slate-500 text-xs text-center">Min 0.001 BTC · Fee 0.00002 BTC · Demo mode — 24h processing</p>
   </div>
  </div>
  <!-- History -->
  <div id="wpanel-history" class="p-4 hidden">
   <div id="txList" class="space-y-2"><div class="text-slate-500 text-sm text-center py-8">Loading...</div></div>
  </div>
 </div>
</div>

<!-- FOOTBALL BETTING MODAL -->
<div class="overlay" id="footballBettingModal">
 <div class="modal-box">
  <div class="flex items-center justify-between p-5 border-b border-slate-800 sticky top-0 bg-slate-950 z-10">
   <div class="flex items-center gap-3">
    <div class="w-8 h-8 rounded-lg bg-green-500/20 flex items-center justify-center text-lg">⚽</div>
    <div>
     <p class="text-white font-bold text-base">Football Betting</p>
     <p class="text-slate-400 text-xs" id="fbMatchInfo">Select a match</p>
    </div>
   </div>
   <button onclick="closeModal('footballBettingModal')" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
   </button>
  </div>

  <div class="p-5">
   <div id="fbErr" class="hidden mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"></div>

   <!-- Match Teams Display -->
   <div class="card p-4 mb-4 bg-slate-800/50">
    <div class="flex items-center justify-between text-center">
     <div class="flex-1">
      <p class="text-white font-bold text-lg" id="fbTeam1">—</p>
      <p class="text-slate-400 text-xs mt-1">Home</p>
     </div>
     <div class="text-slate-500 font-bold text-sm mx-3">vs</div>
     <div class="flex-1">
      <p class="text-white font-bold text-lg" id="fbTeam2">—</p>
      <p class="text-slate-400 text-xs mt-1">Away</p>
     </div>
    </div>
   </div>

   <!-- Bet Type Selector -->
   <div class="mb-4">
    <label class="text-slate-400 text-xs font-semibold mb-2 block">Betting Type</label>
    <div class="grid grid-cols-3 gap-2">
     <button onclick="selectBetType('1v1')" id="btn-bet-1v1" class="py-2.5 px-3 bg-purple-500/20 border-2 border-purple-500 rounded-xl text-purple-400 text-xs font-bold transition-all">
      1v1
      <div class="text-[10px] text-purple-300 mt-0.5">Head to Head</div>
     </button>
     <button onclick="selectBetType('2v2')" id="btn-bet-2v2" class="py-2.5 px-3 bg-slate-800 border-2 border-slate-700 rounded-xl text-slate-400 text-xs font-bold hover:border-slate-600 transition-all">
      2v2
      <div class="text-[10px] text-slate-400 mt-0.5">Team Play</div>
     </button>
     <button onclick="selectBetType('global')" id="btn-bet-global" class="py-2.5 px-3 bg-slate-800 border-2 border-slate-700 rounded-xl text-slate-400 text-xs font-bold hover:border-slate-600 transition-all">
      Global
      <div class="text-[10px] text-slate-400 mt-0.5">Pool Bet</div>
     </button>
    </div>
   </div>

   <!-- Team Selection -->
   <div class="mb-4">
    <label class="text-slate-400 text-xs font-semibold mb-2 block">Pick Your Team</label>
    <div class="grid grid-cols-3 gap-2">
     <button onclick="selectTeam('home')" id="btn-team-home" class="py-3 px-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm font-bold hover:border-slate-600 transition-all">
      <span class="text-white" id="fbTeam1Short">—</span>
     </button>
     <button onclick="selectTeam('draw')" id="btn-team-draw" class="py-3 px-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm font-bold hover:border-slate-600 transition-all">
      Draw
     </button>
     <button onclick="selectTeam('away')" id="btn-team-away" class="py-3 px-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm font-bold hover:border-slate-600 transition-all">
      <span class="text-white" id="fbTeam2Short">—</span>
     </button>
    </div>
   </div>

   <!-- Amount Input -->
   <div class="mb-4">
    <label class="text-slate-400 text-xs font-semibold mb-2 block">Bet Amount (BTC)</label>
    <div class="relative">
     <input id="fbAmount" type="number" step="0.00001" min="0.0001" placeholder="0.0001" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500"/>
     <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-semibold">BTC</div>
    </div>
    <p class="text-slate-500 text-xs mt-2">Balance: <span class="text-purple-300 font-bold" id="fbBalance">—</span> BTC</p>
   </div>

   <!-- Info Box -->
   <div id="fbInfoBox" class="bg-slate-800/50 rounded-xl p-3 mb-4 text-xs text-slate-400">
    <p id="fbInfo">Select bet type and team to see details</p>
   </div>

   <!-- Place Bet Button -->
   <button onclick="placeFBet()" id="fbPlaceBtn" class="w-full py-3 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon">
    Place Bet
   </button>
  </div>
 </div>
</div>

<!-- MOBILE CHAT DRAWER -->
<div class="chat-drawer" id="chatDrawer">
 <div class="chat-drawer-bg" onclick="closeChatDrawer()"></div>
 <div class="chat-drawer-box">
  <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800">
   <div class="flex items-center gap-2">
    <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
    <span class="text-white font-bold text-sm">Live Chat</span>
    <span class="text-slate-400 text-xs" id="mobOnline">—</span>
   </div>
   <button onclick="closeChatDrawer()" class="text-slate-400 hover:text-white p-1">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
   </button>
  </div>
  <div class="flex-1 overflow-y-auto p-3 space-y-2" id="mobChatMsgs"></div>
  <div class="p-3 border-t border-slate-800">
   <div class="flex gap-2">
    <input id="mobChatInput" type="text" maxlength="200" placeholder="<?= $isLoggedIn ? 'Type a message...' : 'Sign in to chat' ?>" <?= !$isLoggedIn ? 'readonly' : '' ?>
     class="flex-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-white text-sm placeholder-slate-500"
     onkeydown="if(event.key==='Enter')sendChat('mob')"/>
    <button onclick="<?= $isLoggedIn ? "sendChat('mob')" : "openModal('authModal')" ?>" class="px-3 py-2.5 g-purple rounded-xl text-white">
     <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
   </div>
  </div>
 </div>
</div>

<!-- ════════════════════════ MAIN LAYOUT ════════════════════════ -->
<div class="layout">

 <!-- SIDEBAR (desktop only) -->
 <div class="sidebar-panel show-on-desktop">
  <div class="flex items-center gap-3 px-4 py-5 border-b border-slate-800">
   <div class="w-9 h-9 rounded-xl g-purple flex items-center justify-center neon shrink-0">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/><line x1="12" y1="2" x2="12" y2="22"/></svg>
   </div>
   <span class="nav-lbl text-white font-black text-base tracking-tight">Crypto<span class="text-purple-400">Duel</span></span>
  </div>
  <nav class="flex-1 p-2 space-y-1 mt-2">
   <a class="nav-item active" data-nav="lobby" onclick="nav('lobby')">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="shrink-0"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    <span class="nav-lbl">Lobby</span>
   </a>
   <a class="nav-item" data-nav="sports" onclick="nav('sports')">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="shrink-0"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><path d="M2 12h20"/></svg>
    <span class="nav-lbl">Sports Betting</span>
   </a>
   <a class="nav-item" data-nav="leaderboard" onclick="nav('leaderboard')">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="shrink-0"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    <span class="nav-lbl">Leaderboard</span>
   </a>
   <a class="nav-item" data-nav="history" onclick="nav('history')">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="shrink-0"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <span class="nav-lbl">My Games</span>
   </a>
   <?php if($isLoggedIn && $user['email'] === 'Viniemmanuel8@gmail.com'): ?>
   <a class="nav-item text-yellow-400" data-nav="admin" onclick="nav('admin')">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="shrink-0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><rect x="8" y="11" width="8" height="11"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
    <span class="nav-lbl">Admin Panel</span>
   </a>
   <?php endif; ?>
  </nav>
  <div class="p-2 border-t border-slate-800">
   <?php if($isLoggedIn): ?>
   <button onclick="doLogout()" class="nav-item w-full">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="shrink-0"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    <span class="nav-lbl">Sign Out</span>
   </button>
   <?php else: ?>
   <button onclick="openModal('authModal')" class="nav-item w-full">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="shrink-0"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
    <span class="nav-lbl">Sign In</span>
   </button>
   <?php endif; ?>
  </div>
 </div>

 <!-- MAIN COLUMN -->
 <div class="main-col">

  <!-- ── HEADER ── -->
  <header class="sticky top-0 z-40 bg-slate-950/95 backdrop-blur-xl border-b border-slate-800">
   <!-- Ticker -->
   <div class="bg-slate-950 border-b border-slate-800/50 py-1.5 overflow-hidden">
    <div class="ticker-wrap">
     <div class="ticker-inner text-xs" id="tickerRow">
      <span class="text-slate-500 px-4">Loading live prices...</span>
     </div>
    </div>
   </div>
   <!-- Header Bar -->
   <div class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-3">
    <!-- Hamburger (mobile) -->
    <button class="show-on-mobile p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800" onclick="toggleMobileMenu()">
     <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <!-- Logo mobile -->
    <span class="show-on-mobile text-white font-black text-base">Crypto<span class="text-purple-400">Duel</span></span>
    <!-- BTC Price -->
    <div class="hidden sm:flex items-center gap-2.5 bg-slate-800 rounded-xl px-3 py-2 border border-slate-700">
     <span class="text-orange-400 font-black text-sm">₿</span>
     <div>
      <p class="text-white font-bold text-sm leading-none" id="hBtcP">—</p>
      <p class="text-xs font-semibold leading-none mt-0.5" id="hBtcC">—</p>
     </div>
     <div class="w-px h-6 bg-slate-700 mx-1"></div>
     <div class="flex items-center gap-1"><div class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></div><span class="text-green-400 text-xs font-bold">LIVE</span></div>
    </div>
    <!-- Stats (desktop) -->
    <div class="hidden lg:flex gap-4 ml-1">
     <div class="text-center"><p class="text-slate-400 text-xs">Online</p><p class="text-green-400 font-bold text-sm" id="hOnline">—</p></div>
     <div class="text-center"><p class="text-slate-400 text-xs">Open Games</p><p class="text-purple-400 font-bold text-sm" id="hOpenGames">—</p></div>
    </div>
    <div class="flex-1"></div>
    <!-- Wallet/Deposit btn -->
    <button onclick="requireAuth(()=>{loadWalletData();openModal('walletModal')})" class="flex items-center gap-1.5 g-purple text-white px-3 sm:px-4 py-2 rounded-xl font-bold text-sm hover:opacity-90 neon">
     <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
     <span class="hidden sm:inline">Wallet</span>
    </button>
    <!-- Discord -->
    <a href="https://discord.gg/UUC7BqCJ8s" target="_blank" rel="noopener" class="flex items-center gap-1.5 bg-[#5865F2]/20 hover:bg-[#5865F2]/30 border border-[#5865F2]/40 text-white px-3 sm:px-4 py-2 rounded-xl font-bold text-sm transition-all">
     <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
     <span class="hidden sm:inline">Discord</span>
    </a>
    <!-- Profile / Sign In -->
    <?php if($isLoggedIn): ?>
    <button onclick="requireAuth(()=>{loadWalletData();openModal('walletModal')})" class="flex items-center gap-2 bg-slate-800 border border-slate-700 rounded-xl px-2.5 py-2 hover:border-slate-600 transition-all">
     <div class="relative">
      <div class="w-8 h-8 rounded-lg g-purple flex items-center justify-center font-bold text-white text-xs"><?= strtoupper(substr(htmlspecialchars($user['username']),0,2)) ?></div>
      <div class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-400 rounded-full border border-slate-800"></div>
     </div>
     <div class="hidden sm:block text-left">
      <p class="text-white font-bold text-xs leading-tight"><?= htmlspecialchars($user['username']) ?></p>
      <p class="text-slate-400 text-xs font-mono leading-tight" id="hBalance"><?= number_format((float)$user['balance_btc'],5) ?> BTC</p>
     </div>
    </button>
    <?php else: ?>
    <button onclick="openModal('authModal')" class="flex items-center gap-2 bg-slate-800 border border-slate-700 text-slate-300 hover:text-white hover:border-purple-500/50 px-3 py-2 rounded-xl font-bold text-sm transition-all">
     <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
     Sign In
    </button>
    <?php endif; ?>
   </div>
  </header>

  <!-- ── SECTIONS ── -->
  <main class="flex-1 p-3 sm:p-4 lg:p-6 max-w-5xl mx-auto w-full">

   <!-- LOBBY -->
   <div class="section active" id="section-lobby">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
     <div>
      <h1 class="text-xl sm:text-2xl font-black text-white">Tic Tac Toe <span class="g-text">Arena</span></h1>
      <p class="text-slate-400 text-sm">Bet BTC against real players — best of one!</p>
     </div>
     <button onclick="requireAuth(showCreateModal)" class="flex items-center justify-center gap-2 g-purple text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:opacity-90 neon w-full sm:w-auto">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Create Game
     </button>
    </div>
    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-4">
     <div class="card p-4"><p class="text-slate-400 text-xs mb-1">Open Games</p><p class="text-white font-black text-2xl" id="sOpen">—</p></div>
     <div class="card p-4"><p class="text-slate-400 text-xs mb-1">Players Online</p><p class="text-green-400 font-black text-2xl" id="sOnline">—</p></div>
     <div class="card p-4"><p class="text-slate-400 text-xs mb-1">Total Games</p><p class="text-purple-400 font-black text-2xl" id="sTotal">—</p></div>
     <div class="card p-4"><p class="text-slate-400 text-xs mb-1">Your Balance</p><p class="text-cyan-400 font-black text-xl truncate" id="sBal"><?= $isLoggedIn ? number_format((float)$user['balance_btc'],4) : '—' ?></p></div>
    </div>
    
    <!-- Featured Football Matches -->
    <h2 class="text-base font-bold text-white mb-3">⚽ Featured Football Matches</h2>
    <div id="featuredMatchesGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-8">
     <div class="col-span-full flex flex-col items-center py-12 text-slate-500">
      <div class="w-10 h-10 border-2 border-green-500/40 border-t-green-500 rounded-full spin mb-3"></div>
      Loading football matches...
     </div>
    </div>
    
    <!-- Open Games -->
    <h2 class="text-base font-bold text-white mb-3">Waiting for Challengers</h2>
    <div id="lobbyGrid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2 sm:gap-3">
     <div class="col-span-full flex flex-col items-center py-12 text-slate-500">
      <div class="w-10 h-10 border-2 border-purple-500/40 border-t-purple-500 rounded-full spin mb-3"></div>
      Loading games...
     </div>
    </div>
    <!-- Mobile Navigation -->
    <div class="show-on-mobile mt-6 pt-4 border-t border-slate-800">
     <div class="grid grid-cols-3 gap-2">
      <button onclick="nav('lobby')" class="flex flex-col items-center gap-1 py-3 bg-purple-500/20 border border-purple-500/40 rounded-xl text-purple-400">
       <span class="text-lg">🏠</span><span class="text-xs font-semibold">Lobby</span>
      </button>
      <button onclick="nav('leaderboard')" class="flex flex-col items-center gap-1 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-400">
       <span class="text-lg">🏆</span><span class="text-xs font-semibold">Ranks</span>
      </button>
      <button onclick="nav('history')" class="flex flex-col items-center gap-1 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-400">
       <span class="text-lg">📋</span><span class="text-xs font-semibold">Games</span>
      </button>
     </div>
     <div class="grid grid-cols-2 gap-2 mt-2">
      <button onclick="requireAuth(()=>{loadWalletData();openModal('walletModal')})" class="flex items-center justify-center gap-2 py-2.5 bg-green-500/20 border border-green-500/40 rounded-xl text-green-400 font-semibold text-sm">
       <span>💰</span> Wallet
      </button>
      <button onclick="openChatDrawer()" class="flex items-center justify-center gap-2 py-2.5 bg-cyan-500/20 border border-cyan-500/40 rounded-xl text-cyan-400 font-semibold text-sm">
       <span>💬</span> Chat
      </button>
     </div>
    </div>
   </div>

   <!-- ACTIVE GAME -->
   <div class="section" id="section-game">
    <button onclick="nav('lobby')" class="flex items-center gap-2 text-slate-400 hover:text-white mb-4 text-sm font-semibold transition-colors">
     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
     Back to Lobby
    </button>
    <div id="gameContainer" class="max-w-md mx-auto">
     <div class="flex items-center justify-center py-12 text-slate-500">
      <div class="w-10 h-10 border-2 border-purple-500/40 border-t-purple-500 rounded-full spin"></div>
     </div>
    </div>
   </div>

   <!-- LEADERBOARD -->
   <div class="section" id="section-leaderboard">
    <div class="mb-5">
     <h1 class="text-xl sm:text-2xl font-black text-white">🏆 Leaderboard</h1>
     <p class="text-slate-400 text-sm">Top players by total BTC won</p>
    </div>
    <div class="card overflow-hidden">
     <div class="overflow-x-auto">
      <table class="w-full">
       <thead><tr class="border-b border-slate-700">
        <th class="text-left text-slate-400 text-xs font-semibold px-4 py-3 w-10">#</th>
        <th class="text-left text-slate-400 text-xs font-semibold px-4 py-3">Player</th>
        <th class="text-right text-slate-400 text-xs font-semibold px-4 py-3">Wins</th>
        <th class="text-right text-slate-400 text-xs font-semibold px-4 py-3">Losses</th>
        <th class="text-right text-slate-400 text-xs font-semibold px-4 py-3">BTC Won</th>
       </tr></thead>
       <tbody id="lbTable" class="divide-y divide-slate-800">
        <tr><td colspan="5" class="text-center text-slate-500 py-8 text-sm">Loading leaderboard...</td></tr>
       </tbody>
      </table>
     </div>
    </div>
    <!-- Mobile Navigation -->
    <div class="show-on-mobile mt-6 pt-4 border-t border-slate-800">
     <div class="grid grid-cols-3 gap-2">
      <button onclick="nav('lobby')" class="flex flex-col items-center gap-1 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-400">
       <span class="text-lg">🏠</span><span class="text-xs font-semibold">Lobby</span>
      </button>
      <button onclick="nav('leaderboard')" class="flex flex-col items-center gap-1 py-3 bg-purple-500/20 border border-purple-500/40 rounded-xl text-purple-400">
       <span class="text-lg">🏆</span><span class="text-xs font-semibold">Ranks</span>
      </button>
      <button onclick="nav('history')" class="flex flex-col items-center gap-1 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-400">
       <span class="text-lg">📋</span><span class="text-xs font-semibold">Games</span>
      </button>
     </div>
     <div class="grid grid-cols-2 gap-2 mt-2">
      <button onclick="requireAuth(()=>{loadWalletData();openModal('walletModal')})" class="flex items-center justify-center gap-2 py-2.5 bg-green-500/20 border border-green-500/40 rounded-xl text-green-400 font-semibold text-sm">
       <span>💰</span> Wallet
      </button>
      <button onclick="openChatDrawer()" class="flex items-center justify-center gap-2 py-2.5 bg-cyan-500/20 border border-cyan-500/40 rounded-xl text-cyan-400 font-semibold text-sm">
       <span>💬</span> Chat
      </button>
     </div>
    </div>
   </div>

   <!-- MY GAMES HISTORY -->
   <div class="section" id="section-history">
    <div class="mb-5">
     <h1 class="text-xl sm:text-2xl font-black text-white">My Games</h1>
     <p class="text-slate-400 text-sm">Your Tic Tac Toe history</p>
    </div>
    <?php if(!$isLoggedIn): ?>
    <div class="card p-10 text-center">
     <p class="text-slate-400 mb-4">Sign in to see your game history</p>
     <button onclick="openModal('authModal')" class="g-purple text-white px-6 py-2.5 rounded-xl font-bold text-sm">Sign In</button>
    </div>
    <?php else: ?>
    <div id="myGamesGrid" class="space-y-3">
     <div class="text-center text-slate-500 py-8 text-sm">Loading your games...</div>
    </div>
    <?php endif; ?>
    <!-- Mobile Navigation -->
    <div class="show-on-mobile mt-6 pt-4 border-t border-slate-800">
     <div class="grid grid-cols-3 gap-2">
      <button onclick="nav('lobby')" class="flex flex-col items-center gap-1 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-400">
       <span class="text-lg">🏠</span><span class="text-xs font-semibold">Lobby</span>
      </button>
      <button onclick="nav('leaderboard')" class="flex flex-col items-center gap-1 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-400">
       <span class="text-lg">🏆</span><span class="text-xs font-semibold">Ranks</span>
      </button>
      <button onclick="nav('history')" class="flex flex-col items-center gap-1 py-3 bg-purple-500/20 border border-purple-500/40 rounded-xl text-purple-400">
       <span class="text-lg">📋</span><span class="text-xs font-semibold">Games</span>
      </button>
     </div>
     <div class="grid grid-cols-2 gap-2 mt-2">
      <button onclick="requireAuth(()=>{loadWalletData();openModal('walletModal')})" class="flex items-center justify-center gap-2 py-2.5 bg-green-500/20 border border-green-500/40 rounded-xl text-green-400 font-semibold text-sm">
       <span>💰</span> Wallet
      </button>
      <button onclick="openChatDrawer()" class="flex items-center justify-center gap-2 py-2.5 bg-cyan-500/20 border border-cyan-500/40 rounded-xl text-cyan-400 font-semibold text-sm">
       <span>💬</span> Chat
      </button>
     </div>
    </div>
   </div>

   <!-- SPORTS BETTING -->
   <div class="section" id="section-sports">
    <div class="mb-5">
     <h1 class="text-xl sm:text-2xl font-black text-white">⚽ Sports Betting</h1>
     <p class="text-slate-400 text-sm">Bet on Football & Basketball matches — P2P or vs House!</p>
    </div>

    <!-- Sport Filter -->
    <div class="flex gap-2 mb-4">
     <button onclick="filterSports('all')" class="sport-filter-btn active px-4 py-2 bg-purple-500/20 border border-purple-500/40 rounded-xl text-purple-400 text-sm font-semibold" data-sport="all">All</button>
     <button onclick="filterSports('FOOTBALL')" class="sport-filter-btn px-4 py-2 bg-slate-800 border border-slate-700 rounded-xl text-slate-400 text-sm font-semibold" data-sport="FOOTBALL">⚽ Football</button>
     <button onclick="filterSports('BASKETBALL')" class="sport-filter-btn px-4 py-2 bg-slate-800 border border-slate-700 rounded-xl text-slate-400 text-sm font-semibold" data-sport="BASKETBALL">🏀 Basketball</button>
    </div>

    <!-- Matches Grid -->
    <div id="matchesGrid" class="grid grid-cols-1 md:grid-cols-2 gap-3">
     <div class="col-span-full flex flex-col items-center py-12 text-slate-500">
      <div class="w-10 h-10 border-2 border-purple-500/40 border-t-purple-500 rounded-full spin mb-3"></div>
      Loading matches...
     </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="show-on-mobile mt-6 pt-4 border-t border-slate-800">
     <div class="grid grid-cols-3 gap-2">
      <button onclick="nav('lobby')" class="flex flex-col items-center gap-1 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-400">
       <span class="text-lg">🏠</span><span class="text-xs font-semibold">Lobby</span>
      </button>
      <button onclick="nav('sports')" class="flex flex-col items-center gap-1 py-3 bg-purple-500/20 border border-purple-500/40 rounded-xl text-purple-400">
       <span class="text-lg">⚽</span><span class="text-xs font-semibold">Sports</span>
      </button>
      <button onclick="nav('history')" class="flex flex-col items-center gap-1 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-400">
       <span class="text-lg">📋</span><span class="text-xs font-semibold">Games</span>
      </button>
     </div>
     <div class="grid grid-cols-2 gap-2 mt-2">
      <button onclick="requireAuth(()=>{loadWalletData();openModal('walletModal')})" class="flex items-center justify-center gap-2 py-2.5 bg-green-500/20 border border-green-500/40 rounded-xl text-green-400 font-semibold text-sm">
       <span>💰</span> Wallet
      </button>
      <button onclick="openChatDrawer()" class="flex items-center justify-center gap-2 py-2.5 bg-cyan-500/20 border border-cyan-500/40 rounded-xl text-cyan-400 font-semibold text-sm">
       <span>💬</span> Chat
      </button>
     </div>
    </div>
   </div>

   <!-- ADMIN PANEL -->
   <?php if($isLoggedIn && $user['email'] === 'Viniemmanuel8@gmail.com'): ?>
   <div class="section" id="section-admin">
    <div class="mb-5">
     <h1 class="text-xl sm:text-2xl font-black text-white">🛡️ Admin Panel</h1>
     <p class="text-slate-400 text-sm">Manage users, matches, and lottery</p>
    </div>

    <!-- Admin Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-5">
     <div class="card p-4"><p class="text-slate-400 text-xs mb-1">Total Users</p><p class="text-white font-black text-2xl" id="adminUserCount">—</p></div>
     <div class="card p-4"><p class="text-slate-400 text-xs mb-1">Matches</p><p class="text-purple-400 font-black text-2xl" id="adminMatchCount">—</p></div>
     <div class="card p-4"><p class="text-slate-400 text-xs mb-1">Total Bets</p><p class="text-cyan-400 font-black text-2xl" id="adminBetCount">—</p></div>
     <div class="card p-4"><p class="text-slate-400 text-xs mb-1">Lottery Pool</p><p class="text-yellow-400 font-black text-2xl" id="lotteryPool">— BTC</p></div>
    </div>

    <!-- Admin Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
     <div class="card p-4">
      <h3 class="text-white font-bold text-base mb-3">🎰 Lottery System</h3>
      <div class="space-y-2">
       <div class="flex justify-between text-sm">
        <span class="text-slate-400">Participants</span>
        <span class="text-white font-semibold" id="lotteryParticipants">—</span>
       </div>
       <div class="flex justify-between text-sm">
        <span class="text-slate-400">Pool Size</span>
        <span class="text-green-400 font-semibold" id="lotteryPool2">— BTC</span>
       </div>
       <button onclick="drawLottery()" id="drawLotteryBtn" class="w-full py-2.5 bg-yellow-500/20 border border-yellow-500/40 rounded-xl text-yellow-400 font-bold text-sm hover:bg-yellow-500/30">
        🎲 Draw Winner
       </button>
      </div>
     </div>

     <div class="card p-4">
      <h3 class="text-white font-bold text-base mb-3">⚽ Match Management</h3>
      <div class="space-y-2">
       <p class="text-slate-400 text-sm">Sync matches from API-Football</p>
       <button onclick="syncFootballMatches()" id="syncMatchesBtn" class="w-full py-2.5 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90">
        🔄 Sync Matches
       </button>
      </div>
     </div>
    </div>

    <!-- Users List -->
    <div class="card p-4">
     <h3 class="text-white font-bold text-base mb-3">👥 Users</h3>
     <div class="overflow-x-auto">
      <table class="w-full text-left">
       <thead>
        <tr class="border-b border-slate-700">
         <th class="px-3 py-2 text-xs text-slate-400 font-semibold">Username</th>
         <th class="px-3 py-2 text-xs text-slate-400 font-semibold">Email</th>
         <th class="px-3 py-2 text-xs text-slate-400 font-semibold">Balance</th>
         <th class="px-3 py-2 text-xs text-slate-400 font-semibold">Joined</th>
        </tr>
       </thead>
       <tbody id="adminUserList">
        <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500 text-sm">Loading...</td></tr>
       </tbody>
      </table>
     </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="show-on-mobile mt-6 pt-4 border-t border-slate-800">
     <div class="grid grid-cols-3 gap-2">
      <button onclick="nav('lobby')" class="flex flex-col items-center gap-1 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-400">
       <span class="text-lg">🏠</span><span class="text-xs font-semibold">Lobby</span>
      </button>
      <button onclick="nav('sports')" class="flex flex-col items-center gap-1 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-400">
       <span class="text-lg">⚽</span><span class="text-xs font-semibold">Sports</span>
      </button>
      <button onclick="nav('admin')" class="flex flex-col items-center gap-1 py-3 bg-yellow-500/20 border border-yellow-500/40 rounded-xl text-yellow-400">
       <span class="text-lg">🛡️</span><span class="text-xs font-semibold">Admin</span>
      </button>
     </div>
    </div>
   </div>
   <?php endif; ?>

  </main>

  <!-- PROVABLY FAIR FOOTER -->
  <footer class="hidden lg:block border-t border-slate-800 p-5 bg-slate-950/50">
   <div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
     <div class="w-8 h-8 rounded-lg g-purple flex items-center justify-center">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
     </div>
     <div>
      <p class="text-white font-bold text-sm">Provably Fair</p>
      <p class="text-slate-500 text-xs">HMAC-SHA256 · 5% house edge · Outcomes verifiable</p>
     </div>
    </div>
    <div class="flex gap-3">
     <span class="pf-chip">SHA-256 Seeds</span>
     <span class="pf-chip">Open Algorithm</span>
     <span class="pf-chip">5% House Edge</span>
    </div>
    <p class="text-slate-600 text-xs">© 2024 CryptoDuel · 18+ · Demo mode</p>
   </div>
  </footer>
 </div>

 <!-- CHAT PANEL (desktop only) -->
 <div class="chat-col show-on-desktop">
  <div class="flex items-center justify-between px-4 py-4 border-b border-slate-800">
   <div class="flex items-center gap-2">
    <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
    <span class="text-white font-bold text-sm">Live Chat</span>
   </div>
   <span class="text-slate-400 text-xs bg-slate-800 px-2 py-0.5 rounded-full" id="deskOnline">—</span>
  </div>
  <div class="flex-1 overflow-y-auto p-3 space-y-2" id="deskChatMsgs"></div>
  <div class="p-3 border-t border-slate-800">
   <button onclick="requireAuth(doRain)" class="w-full flex items-center justify-center gap-2 py-1.5 mb-2 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 rounded-xl text-blue-400 font-semibold text-xs transition-colors">🌧️ Rain 0.001 BTC</button>
   <div class="flex gap-2">
    <input id="deskChatInput" type="text" maxlength="200" placeholder="<?= $isLoggedIn ? 'Message...' : 'Sign in to chat' ?>" <?= !$isLoggedIn ? 'readonly' : '' ?>
     class="flex-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-white text-xs placeholder-slate-500"
     onkeydown="if(event.key==='Enter')sendChat('desk')"/>
    <button onclick="<?= $isLoggedIn ? "sendChat('desk')" : "openModal('authModal')" ?>" class="px-3 py-2.5 g-purple rounded-xl text-white">
     <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
   </div>
   <?php if(!$isLoggedIn): ?><p class="text-slate-600 text-xs text-center mt-2">Sign in to send messages</p><?php endif; ?>
  </div>
 </div>
</div>

<!-- MOBILE SIDEBAR DRAWER -->
<div id="mobMenuBg" class="fixed inset-0 bg-black/70 z-50 hidden" onclick="toggleMobileMenu()"></div>
<div id="mobSidebar" class="fixed left-0 top-0 h-full w-64 bg-slate-950 border-r border-slate-800 z-50 transform -translate-x-full transition-transform duration-300 flex flex-col">
 <div class="flex items-center gap-3 px-4 py-5 border-b border-slate-800">
  <div class="w-9 h-9 rounded-xl g-purple flex items-center justify-center neon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/><line x1="12" y1="2" x2="12" y2="22"/></svg></div>
  <span class="text-white font-black text-lg">Crypto<span class="text-purple-400">Duel</span></span>
 </div>
 <?php if($isLoggedIn): ?>
 <div class="mx-4 my-3 p-3 bg-slate-800 rounded-xl flex items-center gap-3">
  <div class="w-10 h-10 rounded-xl g-purple flex items-center justify-center font-bold text-white text-sm"><?= strtoupper(substr(htmlspecialchars($user['username']),0,2)) ?></div>
  <div>
   <p class="text-white font-bold text-sm"><?= htmlspecialchars($user['username']) ?></p>
   <p class="text-purple-300 text-xs font-mono" id="mobBalance"><?= number_format((float)$user['balance_btc'],5) ?> BTC</p>
  </div>
 </div>
 <?php endif; ?>
 <nav class="flex-1 p-3 space-y-1">
  <?php foreach([['lobby','Lobby','<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],['leaderboard','Leaderboard','<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>'],['history','My Games','<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>']] as [$s,$l,$ic]): ?>
  <a class="nav-item text-sm" onclick="nav('<?= $s ?>');toggleMobileMenu()">
   <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $ic ?></svg>
   <?= $l ?>
  </a>
  <?php endforeach; ?>
  <button onclick="requireAuth(()=>{loadWalletData();openModal('walletModal')});toggleMobileMenu()" class="nav-item text-sm w-full">
   <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
   Wallet / Deposit
  </button>
 </nav>
 <div class="p-3 border-t border-slate-800">
  <?php if($isLoggedIn): ?>
  <button onclick="doLogout()" class="nav-item w-full text-sm text-red-400">
   <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
   Sign Out
  </button>
  <?php else: ?>
  <button onclick="openModal('authModal');toggleMobileMenu()" class="nav-item w-full text-sm">
   <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
   Sign In
  </button>
  <?php endif; ?>
 </div>
</div>

<!-- CREATE GAME MODAL -->
<div class="overlay" id="createModal">
 <div class="modal-box" style="max-width:380px">
  <div class="flex items-center justify-between p-5 border-b border-slate-800">
   <div><p class="text-white font-bold text-lg">Create TTT Game</p><p class="text-slate-400 text-xs">Set your bet — winner takes all</p></div>
   <button onclick="closeModal('createModal')" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
   </button>
  </div>
  <div class="p-5 space-y-4">
   <div id="createErr" class="hidden p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"></div>
   <div>
    <label class="text-slate-400 text-xs font-medium mb-2 block">Bet Amount (BTC)</label>
    <div class="relative">
     <input id="createAmt" type="number" step="0.0001" min="0.0001" max="0.5" placeholder="0.0000"
      class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 pr-16"
      oninput="updateCreateCalc()"/>
     <span class="absolute right-4 top-1/2 -translate-y-1/2 text-orange-400 text-xs font-bold">BTC</span>
    </div>
    <div class="grid grid-cols-4 gap-2 mt-2">
     <?php foreach([0.0001,0.0005,0.001,0.005] as $q): ?>
     <button onclick="document.getElementById('createAmt').value='<?= $q ?>';updateCreateCalc()" class="py-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-purple-500 rounded-lg text-slate-400 hover:text-purple-400 text-xs font-semibold transition-all"><?= $q ?></button>
     <?php endforeach; ?>
    </div>
   </div>
   <div class="bg-slate-800 rounded-xl p-4 space-y-2 text-sm">
    <div class="flex justify-between"><span class="text-slate-400">You play as</span><span class="text-purple-400 font-bold">✕ (X)</span></div>
    <div class="flex justify-between"><span class="text-slate-400">Opponent plays</span><span class="text-cyan-400 font-bold">◯ (O)</span></div>
    <div class="flex justify-between"><span class="text-slate-400">USD value</span><span class="text-white" id="createUsd">—</span></div>
    <div class="flex justify-between border-t border-slate-700 pt-2"><span class="text-slate-400">You win</span><span class="text-green-400 font-bold" id="createWin">—</span></div>
   </div>
   <button onclick="doCreateGame()" id="createBtn" class="w-full py-3 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon">Place Bet &amp; Wait for Opponent</button>
   <p class="text-slate-500 text-xs text-center">Balance: <span class="text-white font-mono" id="createBal"><?= $isLoggedIn ? number_format((float)$user['balance_btc'],5) : '—' ?></span> BTC</p>
  </div>
 </div>
</div>

<!-- SPORTS BETTING MODAL -->
<div class="overlay" id="betModal">
 <div class="modal-box" style="max-width:420px">
  <div class="flex items-center justify-between p-5 border-b border-slate-800">
   <div><p class="text-white font-bold text-lg">Place Sports Bet</p><p class="text-slate-400 text-xs">Choose your winner</p></div>
   <button onclick="closeModal('betModal')" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
   </button>
  </div>
  <div class="p-5 space-y-4">
   <input type="hidden" id="betMatchId">
   <input type="hidden" id="selectedTeam">
   <div id="betError" class="hidden p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"></div>

   <!-- Match Info -->
   <div class="bg-slate-800 rounded-xl p-3 text-center">
    <p class="text-white font-bold text-sm"><span id="betHomeTeam">Home</span> vs <span id="betAwayTeam">Away</span></p>
   </div>

   <!-- Team Selection -->
   <div class="grid grid-cols-3 gap-2">
    <button id="btn-home" onclick="selectBetTeam('home')" class="bet-team-btn py-3 bg-slate-800 border-2 border-slate-700 rounded-xl text-center transition-all">
     <p class="text-white font-bold text-sm">Home</p>
     <p class="text-xs text-slate-400">Win</p>
    </button>
    <button id="btn-draw" onclick="selectBetTeam('draw')" class="bet-team-btn py-3 bg-slate-800 border-2 border-slate-700 rounded-xl text-center transition-all">
     <p class="text-white font-bold text-sm">Draw</p>
     <p class="text-xs text-slate-400">Tie</p>
    </button>
    <button id="btn-away" onclick="selectBetTeam('away')" class="bet-team-btn py-3 bg-slate-800 border-2 border-slate-700 rounded-xl text-center transition-all">
     <p class="text-white font-bold text-sm">Away</p>
     <p class="text-xs text-slate-400">Win</p>
    </button>
   </div>

   <!-- Bet Type -->
   <div class="space-y-2">
    <p class="text-slate-400 text-xs font-medium">Bet Type</p>
    <div class="grid grid-cols-2 gap-2">
     <label class="flex items-center gap-2 p-3 bg-purple-500/10 border border-purple-500/40 rounded-xl cursor-pointer">
      <input type="radio" name="betType" value="p2p" checked class="accent-purple-500" onchange="updateBetOdds()">
      <span class="text-white text-sm font-semibold">P2P Pool</span>
     </label>
     <label class="flex items-center gap-2 p-3 bg-slate-800 border border-slate-700 rounded-xl cursor-pointer">
      <input type="radio" name="betType" value="house" class="accent-purple-500" onchange="updateBetOdds()">
      <span class="text-white text-sm font-semibold">vs House (1.5x)</span>
     </label>
    </div>
   </div>

   <!-- Amount -->
   <div>
    <label class="text-slate-400 text-xs font-medium mb-2 block">Bet Amount (BTC)</label>
    <input id="betAmount" type="number" step="0.0001" min="0.0001" placeholder="0.0000"
     class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500"
     oninput="updateBetOdds()"/>
   </div>

   <!-- Odds Display -->
   <div class="bg-slate-800 rounded-xl p-3 text-center">
    <p class="text-slate-400 text-xs">Potential Winnings</p>
    <p class="text-green-400 font-bold text-lg" id="betOdds">—</p>
   </div>

   <button onclick="placeBet()" id="placeBetBtn" class="w-full py-3 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon">Place Bet</button>
  </div>
 </div>
</div>

<!-- ════════════════════════ JAVASCRIPT ════════════════════════ -->
<script>
// ── State ───────────────────────────────────────────────────────────────────
const APP = {
  loggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
  user:     <?= $user ? json_encode(publicUser($user)) : 'null' ?>,
  rawUser:   <?= $user ? json_encode([
    'id' => (int)$user['id'],
    'balance_btc' => (float)$user['balance_btc'],
    'balance_usd' => (float)($user['balance_usd'] ?? 0),
    'username' => $user['username'],
    'email' => $user['email'] ?? ''
  ]) : 'null' ?>,
  prices:   {},
  lastChatId: 0,
  activeGameId: null,
  gameInterval: null,
  lobbyInterval: null,
  section: 'lobby',
  depositCurrency: 'BTC',
};

// Win lines
const WIN_LINES = [[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];

// ── Utils ────────────────────────────────────────────────────────────────────
async function apiFetch(url, opts = {}) {
  try {
    const res = await fetch(url, {
      headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      ...opts
    });
    return res.json();
  } catch { return {success: false, error: 'Network error'}; }
}

function toast(msg, type = 'purple', duration = 3800) {
  const el = document.createElement('div');
  const colors = {purple:'#7e22ce',green:'#15803d',red:'#b91c1c',blue:'#1d4ed8',yellow:'#a16207'};
  el.className = 'toast text-white';
  el.style.background = colors[type] || colors.purple;
  el.textContent = msg;
  document.getElementById('toasts').appendChild(el);
  setTimeout(() => el.style.opacity = '0', duration - 400);
  setTimeout(() => el.remove(), duration);
}

function openModal(id)  { document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }
document.querySelectorAll('.overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) { closeModal(o.id); } }));

function requireAuth(fn) { if (!APP.loggedIn) { openModal('authModal'); } else { fn(); } }

function balanceStr(b) { return parseFloat(b).toFixed(5); }
function updateAllBalances(btc, usd = null) {
  if (usd === null && APP.rawUser) usd = APP.rawUser.balance_usd;
  if (usd === null) usd = 0;

  const s = balanceStr(btc);
  const u = usd.toFixed(2);

  if (APP.user) APP.user.balance = btc;
  if (APP.rawUser) { APP.rawUser.balance_btc = btc; APP.rawUser.balance_usd = usd; }

  // Update all balance displays
  ['hBalance','mobBalance','sBal','createBal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      if (id === 'sBal') {
        el.textContent = parseFloat(btc).toFixed(4) + ' BTC (~$' + u + ')';
      } else {
        el.textContent = s + ' BTC';
      }
    }
  });

  // Wallet specific
  const wEl = document.getElementById('wBal');
  if (wEl) wEl.textContent = s + ' BTC (~$' + u + ')';

  const wdEl = document.getElementById('wdrawBal');
  if (wdEl) wdEl.textContent = s + ' BTC (~$' + u + ')';

  // Stats display
  const sbEl = document.getElementById('sBal');
  if (sbEl) sbEl.textContent = parseFloat(btc).toFixed(4) + ' BTC / ~$' + u;
}

function timeAgo(ts) {
  const s = Math.floor(Date.now()/1000 - ts);
  if (s < 60)   return s + 's ago';
  if (s < 3600) return Math.floor(s/60) + 'm ago';
  return Math.floor(s/3600) + 'h ago';
}
function btcToUsd(btc) {
  const p = APP.prices['BTC']?.price || 67000;
  return '$' + (btc * p).toLocaleString('en-US', {maximumFractionDigits: 0});
}
const userColors = ['#a855f7','#22d3ee','#22c55e','#f97316','#ec4899','#eab308','#ef4444','#3b82f6'];
function uColor(id) { return userColors[id % userColors.length]; }
function avatar(name, id, size = 32) {
  const c = uColor(id);
  const av = name.substring(0, 2).toUpperCase();
  return `<div style="width:${size}px;height:${size}px;border-radius:${Math.round(size*0.3)}px;background:${c}20;border:1px solid ${c}50;color:${c};display:flex;align-items:center;justify-content:center;font-weight:900;font-size:${Math.round(size*0.4)}px;flex-shrink:0">${av}</div>`;
}

// ── Navigation ───────────────────────────────────────────────────────────────
function nav(name) {
  APP.section = name;
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  const el = document.getElementById('section-' + name);
  if (el) el.classList.add('active');

  document.querySelectorAll('[data-nav],[data-bnav]').forEach(el => {
    const k = el.dataset.nav || el.dataset.bnav;
    el.classList.toggle('active', k === name);
    if (el.dataset.bnav) el.style.color = k === name ? '#a855f7' : '';
  });

  clearInterval(APP.gameInterval);
  if (name === 'lobby')       { loadLobby(); startLobbyPoll(); }
  if (name === 'sports')      { loadSportsMatches(); }
  if (name === 'leaderboard') { loadLeaderboard(); }
  if (name === 'history')     { loadMyGames(); }
  if (name === 'admin')       { loadAdminPanel(); }
  if (name === 'game' && APP.activeGameId) { pollGame(); startGamePoll(); }
}

function toggleMobileMenu() {
  const sb = document.getElementById('mobSidebar');
  const bg = document.getElementById('mobMenuBg');
  const open = sb.classList.toggle('translate-x-0');
  sb.classList.toggle('-translate-x-full', !open);
  bg.classList.toggle('hidden', !open);
}

// ── Auth ─────────────────────────────────────────────────────────────────────
function authTab(tab) {
  const isLogin = tab === 'login';
  document.getElementById('apanel-login').classList.toggle('hidden', !isLogin);
  document.getElementById('apanel-reg').classList.toggle('hidden', isLogin);
  document.getElementById('atab-login').classList.toggle('active', isLogin);
  document.getElementById('atab-reg').classList.toggle('active', !isLogin);
}
async function doLogin() {
  const btn = document.getElementById('loginBtn');
  const err = document.getElementById('loginErr');
  btn.textContent = 'Signing in...'; btn.disabled = true;
  const r = await apiFetch('/api/auth.php?action=login', {method:'POST', body: JSON.stringify({
    username: document.getElementById('loginIdent').value,
    password: document.getElementById('loginPass').value,
  })});
  btn.disabled = false; btn.textContent = 'Sign In';
  if (!r.success) { err.textContent = r.error; err.classList.remove('hidden'); return; }
  location.reload();
}
async function doRegister() {
  const btn = document.getElementById('regBtn');
  const err = document.getElementById('regErr');
  btn.textContent = 'Creating...'; btn.disabled = true;
  const r = await apiFetch('/api/auth.php?action=register', {method:'POST', body: JSON.stringify({
    username: document.getElementById('regUser').value,
    email:    document.getElementById('regEmail').value,
    password: document.getElementById('regPass').value,
  })});
  btn.disabled = false; btn.textContent = 'Create Account & Claim Bonus';
  if (!r.success) { err.textContent = r.error; err.classList.remove('hidden'); return; }
  toast('Welcome! 0.001 BTC bonus added to your account!', 'green', 5000);
  location.reload();
}
async function doLogout() {
  await apiFetch('/api/auth.php?action=logout', {method:'POST'});
  location.reload();
}

// ── Prices ───────────────────────────────────────────────────────────────────
async function loadPrices() {
  const r = await apiFetch('/api/prices.php');
  if (!r.success) return;
  APP.prices = r.prices;
  const btc = r.prices['BTC'];
  if (btc) {
    document.getElementById('hBtcP').textContent = '$' + btc.price.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    const ce = document.getElementById('hBtcC');
    ce.textContent = (btc.change >= 0 ? '+' : '') + btc.change + '%';
    ce.className = 'text-xs font-semibold leading-none mt-0.5 ' + (btc.change >= 0 ? 'text-green-400' : 'text-red-400');
  }
  // Build ticker HTML (doubled for seamless loop)
  const items = Object.entries(r.prices).map(([s,d]) =>
    `<span style="white-space:nowrap" class="px-3 text-xs flex items-center gap-2">
      <span class="font-bold text-white">${s}</span>
      <span class="text-slate-300">$${d.price.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</span>
      <span class="${d.change>=0?'text-green-400':'text-red-400'} font-semibold">${d.change>=0?'+':''}${d.change}%</span>
      <span class="text-slate-700">|</span>
    </span>`
  ).join('');
  document.getElementById('tickerRow').innerHTML = items + items;
  updateCreateCalc();
}

// ── Lobby ────────────────────────────────────────────────────────────────────
async function loadLobby() {
  const [gR, sR] = await Promise.all([
    apiFetch('/api/tictactoe.php?action=list'),
    apiFetch('/api/stats.php?action=global'),
  ]);
  if (sR.success) {
    document.getElementById('sOpen').textContent       = gR.success ? gR.games.length : '—';
    document.getElementById('sOnline').textContent     = sR.online;
    document.getElementById('sTotal').textContent      = sR.bets;
    document.getElementById('hOnline').textContent     = sR.online;
    document.getElementById('hOpenGames').textContent  = gR.success ? gR.games.length : '—';
    document.getElementById('deskOnline').textContent  = sR.online + ' online';
    document.getElementById('mobOnline').textContent   = sR.online + ' online';
  }
  if (!gR.success) return;
  renderLobby(gR.games);
}

function startLobbyPoll() {
  clearInterval(APP.lobbyInterval);
  APP.lobbyInterval = setInterval(loadLobby, 6000);
}

function renderLobby(games) {
  const grid = document.getElementById('lobbyGrid');
  if (!games.length) {
    grid.innerHTML = `
      <div class="col-span-full flex flex-col items-center py-14 text-slate-500">
        <div style="font-size:3rem" class="mb-3">❌</div>
        <p class="font-semibold text-base mb-1">No games waiting</p>
        <p class="text-sm mb-5">Create one and challenge the arena!</p>
        <button onclick="requireAuth(showCreateModal)" class="g-purple text-white px-6 py-2.5 rounded-xl font-bold text-sm neon">Create Game</button>
      </div>`;
    return;
  }
  grid.innerHTML = games.map(g => {
    const isOwn = APP.user && APP.user.id === g.creator_id;
    const usd   = btcToUsd(g.amount_btc);
    const win   = (g.amount_btc * 2 * 0.95).toFixed(4);
    return `<div class="card p-4 fade-in">
      <div class="flex items-center gap-3 mb-4">
        ${avatar(g.creator, g.creator_id, 44)}
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <p class="text-white font-bold text-sm truncate">${g.creator}</p>
            <span class="badge bg-slate-700 text-slate-300">LVL ${g.level}</span>
          </div>
          <p class="text-slate-500 text-xs mt-0.5">${timeAgo(g.created_at)}</p>
        </div>
        <div class="text-right shrink-0">
          <p class="text-white font-black text-lg">${g.amount_btc.toFixed(4)}</p>
          <p class="text-orange-400 text-xs font-bold">BTC</p>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-1 mb-4 p-3 bg-slate-900 rounded-xl text-center text-xs">
        <div><p class="text-slate-500">Bet</p><p class="text-white font-bold">${g.amount_btc.toFixed(4)}</p></div>
        <div><p class="text-slate-500">≈ USD</p><p class="text-white font-bold">${usd}</p></div>
        <div><p class="text-slate-500">Win</p><p class="text-green-400 font-bold">${win}</p></div>
      </div>
      <div class="flex gap-2">
        ${isOwn
          ? `<button onclick="cancelGame(${g.id})" class="flex-1 py-2.5 bg-slate-700 hover:bg-red-900/30 border border-slate-600 hover:border-red-500/50 rounded-xl text-slate-300 hover:text-red-400 font-bold text-sm transition-colors">Cancel</button>
             <button onclick="enterGame(${g.id})" class="flex-1 py-2.5 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon">Watch</button>`
          : `<button onclick="requireAuth(()=>joinGame(${g.id}))" class="flex-1 py-2.5 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon flex items-center justify-center gap-2">
               <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
               Join &amp; Play
             </button>`}
      </div>
    </div>`;
  }).join('');
}

function showCreateModal() { openModal('createModal'); updateCreateCalc(); }
function updateCreateCalc() {
  const amt = parseFloat(document.getElementById('createAmt')?.value || 0);
  const usd = btcToUsd(amt);
  const win = (amt * 2 * 0.95).toFixed(5);
  const uel = document.getElementById('createUsd'); if (uel) uel.textContent = usd;
  const wel = document.getElementById('createWin'); if (wel) wel.textContent = win + ' BTC';
}

async function doCreateGame() {
  const btn = document.getElementById('createBtn');
  const err = document.getElementById('createErr');
  err.classList.add('hidden');
  const amt = parseFloat(document.getElementById('createAmt').value || 0);
  if (!amt || amt < 0.0001) { err.textContent = 'Minimum bet: 0.0001 BTC'; err.classList.remove('hidden'); return; }
  btn.textContent = 'Creating...'; btn.disabled = true;
  const r = await apiFetch('/api/tictactoe.php?action=create', {method:'POST', body: JSON.stringify({amount_btc: amt})});
  btn.textContent = 'Place Bet & Wait for Opponent'; btn.disabled = false;
  if (!r.success) { err.textContent = r.error; err.classList.remove('hidden'); return; }
  updateAllBalances(r.balance);
  closeModal('createModal');
  toast('Game created! Waiting for a challenger...', 'purple');
  APP.activeGameId = r.game_id;
  enterGame(r.game_id);
}

async function joinGame(gameId) {
  const r = await apiFetch('/api/tictactoe.php?action=join', {method:'POST', body: JSON.stringify({game_id: gameId})});
  if (!r.success) { toast(r.error, 'red'); loadLobby(); return; }
  updateAllBalances(r.balance);
  toast('Joined! You play as ◯', 'cyan');
  APP.activeGameId = gameId;
  enterGame(gameId);
}

async function cancelGame(gameId) {
  const r = await apiFetch('/api/tictactoe.php?action=cancel', {method:'POST', body: JSON.stringify({game_id: gameId})});
  if (!r.success) { toast(r.error, 'red'); return; }
  updateAllBalances(r.balance);
  toast('Game cancelled — refunded!', 'green');
  if (APP.activeGameId === gameId) APP.activeGameId = null;
  loadLobby();
}

// ── TTT Game ─────────────────────────────────────────────────────────────────
function enterGame(gameId) {
  APP.activeGameId = gameId;
  clearInterval(APP.lobbyInterval);
  nav('game');
}

function startGamePoll() {
  clearInterval(APP.gameInterval);
  APP.gameInterval = setInterval(pollGame, 2000);
}

async function pollGame() {
  if (!APP.activeGameId) return;
  const r = await apiFetch(`/api/tictactoe.php?action=state&game_id=${APP.activeGameId}`);
  if (!r.success) return;
  renderGame(r.game);
}

function getWinLine(board) {
  for (const [a,b,c] of WIN_LINES) {
    if (board[a] !== '-' && board[a] === board[b] && board[b] === board[c]) return [a,b,c];
  }
  return null;
}

function renderGame(g) {
  const me       = APP.user;
  const myId     = me?.id;
  const isCreator= myId === g.creator_id;
  const mySym    = isCreator ? g.creator_sym : (g.creator_sym === 'X' ? 'O' : 'X');
  const oppSym   = mySym === 'X' ? 'O' : 'X';
  const oppName  = isCreator ? (g.joiner || '?') : g.creator;
  const oppId    = isCreator ? g.joiner_id : g.creator_id;
  const isMyTurn = myId && g.current_turn_id === myId;
  const waiting  = g.status === 'waiting';
  const active   = g.status === 'active';
  const done     = g.status === 'completed' || g.status === 'draw';
  const isDraw   = g.status === 'draw' || (done && !g.winner_id);
  const iWon     = done && g.winner_id === myId;
  const board    = g.board || '---------';
  const winLine  = getWinLine(board);

  // Stop polling if done
  if (done) { clearInterval(APP.gameInterval); loadLobby(); }

  const symColor = {X:'#a855f7', O:'#22d3ee'};
  const symLabel = {X:'✕', O:'◯'};

  // Board cells
  const cells = board.split('').map((c, i) => {
    const isWin    = winLine && winLine.includes(i);
    const canClick = c === '-' && active && isMyTurn && myId;
    const cls      = [
      'ttt-cell',
      c !== '-' ? c.toLowerCase() : '',
      isWin ? 'win' : '',
      canClick ? 'clickable' : '',
    ].filter(Boolean).join(' ');
    const sym = c === 'X' ? '✕' : c === 'O' ? '◯' : '';
    return `<button class="${cls}" ${canClick ? `onclick="makeMove(${i})"` : ''}>
      <span class="sym">${sym}</span>
    </button>`;
  }).join('');

  // Status bar
  let statusHtml;
  if (waiting) {
    statusHtml = `<div class="flex items-center justify-center gap-2 py-2 bg-yellow-500/10 border border-yellow-500/20 rounded-xl text-yellow-400 text-sm font-semibold">
      <div class="w-3 h-3 border-2 border-yellow-400 border-t-transparent rounded-full spin"></div>
      Waiting for an opponent to join...
    </div>`;
  } else if (active) {
    statusHtml = isMyTurn
      ? `<div class="py-2 bg-green-500/10 border border-green-500/30 rounded-xl text-green-400 text-sm font-bold text-center">Your turn — click a cell!</div>`
      : `<div class="flex items-center justify-center gap-2 py-2 bg-slate-800 rounded-xl text-slate-400 text-sm">
          <div class="w-3 h-3 border-2 border-slate-400 border-t-transparent rounded-full spin"></div>
          Waiting for ${oppName}...
        </div>`;
  } else if (isDraw) {
    statusHtml = `<div class="py-2 bg-blue-500/10 border border-blue-500/30 rounded-xl text-blue-400 text-base font-black text-center">🤝 Draw! Stakes refunded.</div>`;
  } else if (iWon) {
    statusHtml = `<div class="py-2 bg-green-500/10 border border-green-500/30 rounded-xl text-green-400 text-base font-black text-center">🎉 You Won! +${(g.amount_btc*2*0.95).toFixed(5)} BTC</div>`;
  } else {
    statusHtml = `<div class="py-2 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-base font-black text-center">😔 You lost. Better luck next time!</div>`;
  }

  document.getElementById('gameContainer').innerHTML = `
    <div class="fade-in space-y-4">
      <!-- Players row -->
      <div class="card p-4">
        <div class="flex items-center gap-2 sm:gap-3">
          <!-- Creator (X) -->
          <div class="flex-1 flex flex-col items-center gap-2 p-3 rounded-xl ${isCreator && active && isMyTurn ? 'bg-purple-500/10 border border-purple-500/30' : 'bg-slate-900'}">
            ${avatar(g.creator, g.creator_id, 40)}
            <p class="text-white font-bold text-xs text-center truncate max-w-full">${g.creator}</p>
            <span class="text-2xl" style="color:${symColor[g.creator_sym]}">${symLabel[g.creator_sym]}</span>
          </div>
          <!-- VS + bet -->
          <div class="text-center shrink-0">
            <p class="text-slate-500 text-xs font-bold">VS</p>
            <p class="text-white font-black text-base mt-1">${g.amount_btc.toFixed(4)}</p>
            <p class="text-orange-400 text-xs">BTC</p>
          </div>
          <!-- Joiner (O) -->
          <div class="flex-1 flex flex-col items-center gap-2 p-3 rounded-xl ${!isCreator && active && isMyTurn ? 'bg-cyan-500/10 border border-cyan-500/30' : 'bg-slate-900'}">
            ${g.joiner ? avatar(g.joiner, g.joiner_id, 40) : `<div style="width:40px;height:40px;border-radius:12px;background:#1e293b;border:2px dashed #475569;display:flex;align-items:center;justify-content:center;font-size:1.2rem">?</div>`}
            <p class="text-white font-bold text-xs text-center truncate max-w-full">${g.joiner || 'Waiting...'}</p>
            <span class="text-2xl" style="color:${symColor[g.creator_sym==='X'?'O':'X']}">${symLabel[g.creator_sym==='X'?'O':'X']}</span>
          </div>
        </div>
      </div>

      <!-- Status -->
      ${statusHtml}

      <!-- Board -->
      <div class="card p-5">
        <div class="ttt-board">${cells}</div>
      </div>

      <!-- Provably Fair info -->
      <div class="bg-slate-800/60 rounded-xl p-3 text-xs space-y-1">
        <p class="text-slate-500">Game #${g.id} · House edge 5% · Provably Fair</p>
        ${done ? `<p class="text-green-400/70 font-mono break-all">Winner: ${g.winner || (isDraw ? 'Draw' : '—')}</p>` : ''}
      </div>

      ${done ? `<button onclick="nav('lobby')" class="w-full py-3 g-purple rounded-xl text-white font-bold text-sm neon">Back to Lobby</button>` : ''}
      ${waiting && APP.user?.id === g.creator_id ? `<button onclick="cancelGame(${g.id})" class="w-full py-2.5 bg-red-900/20 border border-red-500/30 rounded-xl text-red-400 font-semibold text-sm hover:bg-red-900/40 transition-colors">Cancel &amp; Refund</button>` : ''}
    </div>`;
}

async function makeMove(cell) {
  if (!APP.activeGameId) return;
  const r = await apiFetch('/api/tictactoe.php?action=move', {method:'POST', body: JSON.stringify({game_id: APP.activeGameId, cell})});
  if (!r.success) { toast(r.error, 'red'); return; }
  if (r.balance !== undefined) updateAllBalances(r.balance);
  // Re-render immediately
  const gr = await apiFetch(`/api/tictactoe.php?action=state&game_id=${APP.activeGameId}`);
  if (gr.success) renderGame(gr.game);
}

// ── Leaderboard ──────────────────────────────────────────────────────────────
async function loadLeaderboard() {
  const r = await apiFetch('/api/stats.php?action=leaderboard');
  const tb = document.getElementById('lbTable');
  if (!r.success) { tb.innerHTML = '<tr><td colspan="5" class="text-center text-red-400 py-4 text-sm">Failed to load</td></tr>'; return; }
  if (!r.leaderboard.length) {
    tb.innerHTML = '<tr><td colspan="5" class="text-center text-slate-500 py-8 text-sm">No players yet — be the first!</td></tr>';
    return;
  }
  const medals = ['🥇','🥈','🥉'];
  tb.innerHTML = r.leaderboard.map((p, i) => `
    <tr class="hover:bg-slate-800/40 transition-colors">
      <td class="px-4 py-3 text-center">
        <span class="${i < 3 ? 'text-lg' : 'text-slate-500 text-sm font-bold'}">${i < 3 ? medals[i] : '#'+(i+1)}</span>
      </td>
      <td class="px-4 py-3">
        <div class="flex items-center gap-2.5">
          ${avatar(p.username, p.id, 32)}
          <div>
            <p class="text-white font-bold text-sm">${p.username}</p>
            <span class="badge bg-slate-700 text-slate-400">LVL ${p.level}</span>
          </div>
        </div>
      </td>
      <td class="px-4 py-3 text-right"><span class="text-green-400 font-bold text-sm">${p.wins}</span></td>
      <td class="px-4 py-3 text-right"><span class="text-red-400 text-sm">${p.losses}</span></td>
      <td class="px-4 py-3 text-right"><span class="text-yellow-400 font-bold text-sm">+${p.profit.toFixed(4)}</span></td>
    </tr>`).join('');
}

// ── Sports Betting ─────────────────────────────────────────────────────────────
let currentSportFilter = 'all';

function filterSports(sport) {
  currentSportFilter = sport;
  document.querySelectorAll('.sport-filter-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.sport === sport);
    btn.classList.toggle('bg-purple-500/20', btn.dataset.sport === sport);
    btn.classList.toggle('border-purple-500/40', btn.dataset.sport === sport);
    btn.classList.toggle('text-purple-400', btn.dataset.sport === sport);
    btn.classList.toggle('bg-slate-800', btn.dataset.sport !== sport);
    btn.classList.toggle('border-slate-700', btn.dataset.sport !== sport);
    btn.classList.toggle('text-slate-400', btn.dataset.sport !== sport);
  });
  loadSportsMatches();
}

async function loadSportsMatches() {
  const grid = document.getElementById('matchesGrid');
  grid.innerHTML = `
    <div class="col-span-full flex flex-col items-center py-14 text-slate-500">
      <div class="w-10 h-10 border-2 border-purple-500/40 border-t-purple-500 rounded-full spin mb-3"></div>
      <p class="font-semibold text-base mb-1">Loading live matches...</p>
    </div>`;

  // Fetch from API-Football
  const r = await apiFetch('/api/football.php?action=fixtures&days=7');

  if (!r.success || !r.fixtures.length) {
    grid.innerHTML = `
      <div class="col-span-full flex flex-col items-center py-14 text-slate-500">
        <div style="font-size:3rem" class="mb-3">⚽</div>
        <p class="font-semibold text-base mb-1">No matches available</p>
        <p class="text-sm">Matches will appear here when scheduled</p>
      </div>`;
    return;
  }

  grid.innerHTML = r.fixtures.map(m => {
    const isUpcoming = m.status === 'upcoming';
    const isLive = m.status === 'live';
    const isFinished = m.status === 'finished';
    const sportIcon = '⚽';
    const matchTime = new Date(m.match_time * 1000);
    const timeStr = matchTime.toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

    return `<div class="card p-4 fade-in">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <span class="text-xl">${sportIcon}</span>
          <span class="text-xs font-semibold text-green-400">FOOTBALL</span>
          ${m.league_name ? `<span class="text-slate-500 text-xs">• ${m.league_name}</span>` : ''}
        </div>
        <span class="text-xs font-semibold ${isUpcoming ? 'text-yellow-400' : isLive ? 'text-green-400' : 'text-slate-400'}">${isUpcoming ? 'UPCOMING' : isLive ? '🔴 LIVE' : 'FINISHED'}</span>
      </div>
      <div class="flex items-center justify-between mb-3">
        <div class="text-center flex-1">
          <p class="text-white font-bold text-sm">${m.home_team}</p>
          ${isFinished || isLive ? `<p class="text-2xl font-black text-white mt-1">${m.home_score}</p>` : ''}
        </div>
        <div class="text-center px-3">
          <p class="text-slate-500 text-xs font-bold">VS</p>
        </div>
        <div class="text-center flex-1">
          <p class="text-white font-bold text-sm">${m.away_team}</p>
          ${isFinished || isLive ? `<p class="text-2xl font-black text-white mt-1">${m.away_score}</p>` : ''}
        </div>
      </div>
      ${isUpcoming ? `
        <div class="text-center text-xs text-slate-400 mb-3">
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline mr-1"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          ${timeStr}
        </div>
        <button onclick="openBetModal(${m.id}, '${escapeHtml(m.home_team)}', '${escapeHtml(m.away_team)}')" class="w-full py-2.5 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon">
          Place Bet
        </button>
      ` : isFinished ? `
        <div class="text-center text-sm font-semibold ${m.winner === 'draw' ? 'text-blue-400' : 'text-green-400'}">
          ${m.winner === 'draw' ? '🤝 It\'s a Draw!' : `🏆 ${m.winner === 'home' ? m.home_team : m.away_team} Wins!`}
        </div>
      ` : `
        <div class="text-center text-xs text-red-400 font-semibold">🔴 Live • ${m.home_score} - ${m.away_score}</div>
      `}
    </div>`;
  }).join('');

  // Auto-refresh live matches every 30 seconds
  setTimeout(() => {
    if (APP.section === 'sports') loadSportsMatches();
  }, 30000);
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function openBetModal(matchId, homeTeam, awayTeam) {
  if (!APP.loggedIn) { openModal('authModal'); return; }

  const modal = document.createElement('div');
  modal.className = 'overlay open';
  modal.id = 'betModal';
  modal.innerHTML = `
    <div class="modal-box" style="max-width:400px">
      <div class="flex items-center justify-between p-4 border-b border-slate-800">
        <div><p class="text-white font-bold">Place Your Bet</p><p class="text-slate-400 text-xs">${homeTeam} vs ${awayTeam}</p></div>
        <button onclick="closeBetModal()" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="p-4 space-y-3">
        <div id="betError" class="hidden p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"></div>
        <div>
          <label class="text-slate-400 text-xs font-medium mb-2 block">Bet Type</label>
          <div class="grid grid-cols-2 gap-2">
            <button onclick="selectBetType('p2p')" id="betTypeP2P" class="bet-type-btn py-2 bg-purple-500/20 border-2 border-purple-500 rounded-xl text-purple-400 text-xs font-semibold">vs Player (P2P)</button>
            <button onclick="selectBetType('house')" id="betTypeHouse" class="bet-type-btn py-2 bg-slate-800 border-2 border-slate-700 rounded-xl text-slate-400 text-xs font-semibold">vs House</button>
          </div>
        </div>
        <div>
          <label class="text-slate-400 text-xs font-medium mb-2 block">Who Will Win?</label>
          <div class="grid grid-cols-3 gap-2">
            <button onclick="selectTeam('home')" id="teamHome" class="team-btn py-2 bg-slate-800 border-2 border-slate-700 rounded-xl text-slate-400 text-xs font-semibold">${homeTeam}</button>
            <button onclick="selectTeam('draw')" id="teamDraw" class="team-btn py-2 bg-slate-800 border-2 border-slate-700 rounded-xl text-slate-400 text-xs font-semibold">Draw</button>
            <button onclick="selectTeam('away')" id="teamAway" class="team-btn py-2 bg-slate-800 border-2 border-slate-700 rounded-xl text-slate-400 text-xs font-semibold">${awayTeam}</button>
          </div>
        </div>
        <div>
          <label class="text-slate-400 text-xs font-medium mb-2 block">Amount (BTC)</label>
          <input id="betAmount" type="number" step="0.00001" min="0.00001" placeholder="0.00000" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500" oninput="updateBetPreview()"/>
        </div>
        <div class="bg-slate-800 rounded-xl p-3 text-xs space-y-2">
          <div class="flex justify-between"><span class="text-slate-400">You bet</span><span class="text-white font-bold" id="betYouBet">— BTC</span></div>
          <div class="flex justify-between"><span class="text-green-400">Potential win</span><span class="text-green-400 font-bold" id="betPotentialWin">— BTC</span></div>
          <p class="text-slate-500 text-xs mt-2 text-center">If you win, you get 1.5x your bet!</p>
        </div>
        <button onclick="placeBet(${matchId})" id="placeBetBtn" class="w-full py-3 g-purple rounded-xl text-white font-bold text-sm hover:opacity-90 neon">Place Bet</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  document.body.style.overflow = 'hidden';
}

function closeBetModal() {
  const modal = document.getElementById('betModal');
  if (modal) { modal.remove(); }
  document.body.style.overflow = '';
}

let selectedBetType = 'p2p';
let selectedTeam = '';

function selectBetType(type) {
  selectedBetType = type;
  document.querySelectorAll('.bet-type-btn').forEach(btn => {
    btn.classList.remove('bg-purple-500/20', 'border-purple-500', 'text-purple-400');
    btn.classList.add('bg-slate-800', 'border-slate-700', 'text-slate-400');
  });
  const activeBtn = document.getElementById(type === 'p2p' ? 'betTypeP2P' : 'betTypeHouse');
  activeBtn.classList.remove('bg-slate-800', 'border-slate-700', 'text-slate-400');
  activeBtn.classList.add('bg-purple-500/20', 'border-purple-500', 'text-purple-400');
  updateBetPreview();
}

function selectTeam(team) {
  selectedTeam = team;
  document.querySelectorAll('.team-btn').forEach(btn => {
    btn.classList.remove('bg-purple-500/20', 'border-purple-500', 'text-purple-400');
    btn.classList.add('bg-slate-800', 'border-slate-700', 'text-slate-400');
  });
  const activeBtn = document.getElementById(team === 'home' ? 'teamHome' : team === 'away' ? 'teamAway' : 'teamDraw');
  activeBtn.classList.remove('bg-slate-800', 'border-slate-700', 'text-slate-400');
  activeBtn.classList.add('bg-purple-500/20', 'border-purple-500', 'text-purple-400');
}

function updateBetPreview() {
  const amt = parseFloat(document.getElementById('betAmount')?.value || 0);
  const youBet = document.getElementById('betYouBet');
  const potentialWin = document.getElementById('betPotentialWin');
  if (youBet) youBet.textContent = amt.toFixed(8) + ' BTC';
  if (potentialWin) potentialWin.textContent = (amt * 1.5).toFixed(8) + ' BTC';
}

async function placeBet(matchId) {
  const amt = parseFloat(document.getElementById('betAmount').value || 0);
  const err = document.getElementById('betError');

  if (!selectedTeam) { err.textContent = 'Please select a team'; err.classList.remove('hidden'); return; }
  if (amt < 0.00001) { err.textContent = 'Minimum bet: 0.00001 BTC'; err.classList.remove('hidden'); return; }

  const btn = document.getElementById('placeBetBtn');
  btn.textContent = 'Placing...';
  btn.disabled = true;
  err.classList.add('hidden');

  const r = await apiFetch('/api/sports.php?action=place_bet', {
    method: 'POST',
    body: JSON.stringify({
      match_id: matchId,
      bet_type: selectedBetType,
      team_selection: selectedTeam,
      amount_btc: amt,
    }),
  });

  btn.textContent = 'Place Bet';
  btn.disabled = false;

  if (!r.success) { err.textContent = r.error; err.classList.remove('hidden'); return; }

  updateAllBalances(r.balance_btc);
  closeBetModal();
  toast('Bet placed successfully! Good luck! 🍀', 'green', 4000);
  loadSportsMatches();
}

// ── My Games History ──────────────────────────────────────────────────────────
async function loadMyGames() {
  if (!APP.loggedIn) return;
  const r = await apiFetch('/api/stats.php?action=leaderboard'); // placeholder — use ttt recent
  const g2 = await apiFetch('/api/tictactoe.php?action=list');
  const el = document.getElementById('myGamesGrid');

  // Show user's open games first
  const open = g2.success ? g2.games.filter(g => APP.user && g.creator_id === APP.user.id) : [];
  if (!open.length) {
    el.innerHTML = `<div class="card p-8 text-center">
      <div style="font-size:3rem" class="mb-3">🎮</div>
      <p class="text-slate-400 mb-4">You have no active games right now</p>
      <button onclick="requireAuth(showCreateModal)" class="g-purple text-white px-6 py-2.5 rounded-xl font-bold text-sm neon">Create New Game</button>
    </div>`;
  } else {
    el.innerHTML = '<h3 class="text-slate-300 font-semibold text-sm mb-2">Your Open Games</h3>' +
    open.map(g => `
      <div class="card p-4 flex items-center justify-between gap-4">
        <div>
          <p class="text-white font-bold text-sm">Waiting for opponent</p>
          <p class="text-slate-400 text-xs">${g.amount_btc.toFixed(5)} BTC · ${timeAgo(g.created_at)}</p>
        </div>
        <div class="flex gap-2">
          <button onclick="enterGame(${g.id})" class="px-3 py-2 g-purple rounded-xl text-white text-xs font-bold">View</button>
          <button onclick="cancelGame(${g.id})" class="px-3 py-2 bg-red-900/20 border border-red-500/20 rounded-xl text-red-400 text-xs font-bold">Cancel</button>
        </div>
      </div>`).join('');
  }
}

// ── Chat ─────────────────────────────────────────────────────────────────────
function chatMsgHtml(m) {
  const c   = uColor(m.user_id);
  const av  = m.username.substring(0,1).toUpperCase();
  const t   = new Date(m.ts * 1000).toLocaleTimeString('en', {hour:'2-digit', minute:'2-digit', hour12:false});
  if (m.type === 'rain') {
    return `<div style="background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);border-radius:12px;padding:8px 12px;text-align:center;font-size:11px;color:#60a5fa;font-weight:600">${m.message}</div>`;
  }
  return `<div class="flex items-start gap-2">
    <div style="width:22px;height:22px;border-radius:6px;background:${c}20;color:${c};display:flex;align-items:center;justify-content:center;font-weight:900;font-size:10px;flex-shrink:0;margin-top:2px">${av}</div>
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px">
        <span style="font-weight:700;font-size:11px;color:${c}">${m.username}</span>
        <span style="font-size:9px;padding:1px 5px;border-radius:4px;background:#1e293b;color:#94a3b8;font-weight:700">${m.level}</span>
        <span style="font-size:10px;color:#475569;margin-left:auto">${t}</span>
      </div>
      <p style="font-size:12px;color:#cbd5e1;line-height:1.4;word-break:break-word">${m.message}</p>
    </div>
  </div>`;
}

async function loadChat() {
  const r = await apiFetch(`/api/chat.php?action=get&since_id=${APP.lastChatId}`);
  if (!r.success || !r.messages.length) return;
  const deskBox = document.getElementById('deskChatMsgs');
  const mobBox  = document.getElementById('mobChatMsgs');
  const atBottom = deskBox ? (deskBox.scrollHeight - deskBox.scrollTop - deskBox.clientHeight < 50) : true;
  if (APP.lastChatId === 0) { if (deskBox) deskBox.innerHTML=''; if (mobBox) mobBox.innerHTML=''; }
  r.messages.forEach(m => {
    if (m.id <= APP.lastChatId) return;
    APP.lastChatId = Math.max(APP.lastChatId, m.id);
    const html = chatMsgHtml(m);
    if (deskBox) deskBox.insertAdjacentHTML('beforeend', html);
    if (mobBox)  mobBox.insertAdjacentHTML('beforeend', html);
  });
  while (deskBox && deskBox.children.length > 80) deskBox.removeChild(deskBox.firstChild);
  while (mobBox  && mobBox.children.length  > 80) mobBox.removeChild(mobBox.firstChild);
  if (atBottom && deskBox) deskBox.scrollTop = deskBox.scrollHeight;
  if (mobBox) mobBox.scrollTop = mobBox.scrollHeight;
}

async function sendChat(from) {
  if (!APP.loggedIn) { openModal('authModal'); return; }
  const inp = document.getElementById(from === 'desk' ? 'deskChatInput' : 'mobChatInput');
  const msg = inp.value.trim();
  if (!msg) return;
  inp.value = '';
  const r = await apiFetch('/api/chat.php?action=send', {method:'POST', body: JSON.stringify({message: msg})});
  if (!r.success) { toast(r.error, 'red'); inp.value = msg; return; }
  loadChat();
}

async function doRain() {
  const r = await apiFetch('/api/chat.php?action=rain', {method:'POST'});
  if (!r.success) { toast(r.error, 'red'); return; }
  if (r.balance_btc !== undefined) {
    updateAllBalances(r.balance_btc, r.balance_usd);
  }
  toast(`🌧️ Rained on ${r.recipients} players!`, 'blue');
  loadChat();
}

function openChatDrawer()  { document.getElementById('chatDrawer').classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeChatDrawer() { document.getElementById('chatDrawer').classList.remove('open'); document.body.style.overflow = ''; }

// ── Wallet ───────────────────────────────────────────────────────────────────
function selectDepositCurrency(currency) {
  APP.depositCurrency = currency;

  // Update button styles
  const btcBtn = document.getElementById('currBtnBTC');
  const ltcBtn = document.getElementById('currBtnLTC');

  if (currency === 'BTC') {
    btcBtn.className = 'flex items-center justify-center gap-2 p-3 bg-purple-500/20 border-2 border-purple-500 rounded-xl transition-all';
    ltcBtn.className = 'flex items-center justify-center gap-2 p-3 bg-slate-800 border-2 border-slate-700 rounded-xl transition-all hover:border-slate-600';
  } else {
    ltcBtn.className = 'flex items-center justify-center gap-2 p-3 bg-purple-500/20 border-2 border-purple-500 rounded-xl transition-all';
    btcBtn.className = 'flex items-center justify-center gap-2 p-3 bg-slate-800 border-2 border-slate-700 rounded-xl transition-all hover:border-slate-600';
  }

  // Update currency name and address
  const nameEl = document.getElementById('depositCurrencyName');
  const addrEl = document.getElementById('depositAddress');
  if (nameEl) nameEl.textContent = currency === 'BTC' ? 'Bitcoin' : 'Litecoin';
  if (addrEl) {
    if (currency === 'BTC') {
      addrEl.value = 'bc1qy0cma0nhur3kggfg8uh8tmsu4kn2mces2gvp9h';
    } else if (currency === 'LTC') {
      addrEl.value = 'LagW6oTkbG1aBLjwnzPVZEPoWWPhW2HRFn';
    }
  }
}

function copyDepositAddress() {
  const addrEl = document.getElementById('depositAddress');
  if (addrEl) {
    navigator.clipboard.writeText(addrEl.value);
    toast('Address copied to clipboard!', 'green');
  }
}

async function verifyTransaction() {
  if (!APP.loggedIn) {
    openModal('authModal');
    return;
  }

  const txid = document.getElementById('txidInput').value.trim();
  const resultEl = document.getElementById('verifyResult');
  const btn = document.getElementById('verifyBtn');

  if (!txid) {
    resultEl.className = 'p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm';
    resultEl.textContent = 'Please enter a Transaction ID';
    resultEl.classList.remove('hidden');
    return;
  }

  btn.textContent = 'Verifying...';
  btn.disabled = true;
  resultEl.classList.add('hidden');

  try {
    const r = await apiFetch('/api/wallet.php?action=verify_transaction', {
      method: 'POST',
      body: JSON.stringify({
        txid: txid,
        currency: APP.depositCurrency || 'BTC',
      }),
    });

    if (!r.success) {
      resultEl.className = 'p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm';
      resultEl.innerHTML = `<strong>Error:</strong> ${r.error}`;
      resultEl.classList.remove('hidden');
    } else {
      resultEl.className = 'p-3 bg-green-500/10 border border-green-500/30 rounded-xl text-green-400 text-sm';
      resultEl.innerHTML = `
        <strong>✅ Deposit Verified!</strong><br>
        <span class="text-xs">
        Deposited: ${r.original_amount} ${APP.depositCurrency || 'BTC'}<br>
        You received: ${r.credited_amount} ${APP.depositCurrency || 'BTC'} (80% after 20% fee)
        </span>
      `;
      resultEl.classList.remove('hidden');

      // Update balance
      updateAllBalances(r.balance_btc, r.balance_usd);

      // Clear input
      document.getElementById('txidInput').value = '';

      toast(`Successfully deposited ${r.credited_amount} ${APP.depositCurrency || 'BTC'}!`, 'green', 5000);
    }
  } catch (e) {
    resultEl.className = 'p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm';
    resultEl.textContent = 'Failed to verify transaction. Please try again.';
    resultEl.classList.remove('hidden');
  }

  btn.textContent = 'Verify & Credit Deposit';
  btn.disabled = false;
}

function wTab(tab) {
  ['deposit','withdraw','history'].forEach(t => {
    document.getElementById('wpanel-' + t).classList.toggle('hidden', t !== tab);
    document.getElementById('wtab-'   + t).classList.toggle('active', t === tab);
  });
  if (tab === 'history') loadTxHistory();
}

async function loadWalletData() {
  if (!APP.loggedIn) return;
  // Load fresh balance from server
  const br = await apiFetch('/api/wallet.php?action=balance');
  if (br.success) {
    updateAllBalances(br.balance_btc, br.balance_usd);
    APP.prices = APP.prices || {};
    APP.prices['BTC'] = { price: br.btc_price };
  }
}

async function loadTxHistory() {
  const r  = await apiFetch('/api/wallet.php?action=history');
  const el = document.getElementById('txList');
  if (!r.success) { el.innerHTML = '<p class="text-red-400 text-xs text-center py-4">Failed to load</p>'; return; }
  if (!r.transactions.length) { el.innerHTML = '<p class="text-slate-500 text-sm text-center py-6">No transactions yet</p>'; return; }
  el.innerHTML = r.transactions.map(t => {
    const pos = t.amount > 0;
    const ic  = pos ? '#22c55e' : '#ef4444';
    const arrow = pos ? 'M12 19V5 M5 12l7-7 7 7' : 'M12 5v14 M19 12l-7 7-7-7';
    const sc  = t.status === 'confirmed' ? 'color:#22c55e' : 'color:#eab308';
    const dot = t.status === 'confirmed' ? 'background:#22c55e' : 'background:#eab308';
    const d   = new Date(t.ts * 1000).toLocaleDateString();
    return `<div style="display:flex;align-items:center;gap:12px;padding:12px;background:#1e293b;border-radius:14px">
      <div style="width:36px;height:36px;border-radius:10px;background:${ic}15;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="${ic}" stroke-width="2"><path d="${arrow}"/></svg>
      </div>
      <div style="flex:1;min-width:0">
        <p style="color:white;font-size:13px;font-weight:600;text-transform:capitalize">${t.type.replace('_',' ')}</p>
        <p style="color:#64748b;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${t.notes || t.hash || d}</p>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <p style="font-size:13px;font-weight:700;color:${pos?'#22c55e':'#ef4444'}">${pos?'+':''}${t.amount.toFixed(5)} BTC</p>
        <div style="display:flex;align-items:center;gap:4px;justify-content:flex-end">
          <div style="width:6px;height:6px;border-radius:50%;${dot}"></div>
          <span style="font-size:11px;${sc}">${t.status}</span>
        </div>
      </div>
    </div>`;
  }).join('');
}

function maxWd() {
  if (APP.user) document.getElementById('wdrawAmt').value = Math.max(0, APP.user.balance - 0.00002).toFixed(5);
}
async function doWithdraw() {
  const addr = document.getElementById('wdrawAddr').value.trim();
  const amt  = parseFloat(document.getElementById('wdrawAmt').value || 0);
  const err  = document.getElementById('wdrawErr');
  err.classList.add('hidden');
  const r = await apiFetch('/api/wallet.php?action=withdraw', {method:'POST', body: JSON.stringify({address:addr, amount_btc:amt})});
  if (!r.success) { err.textContent = r.error; err.classList.remove('hidden'); return; }
  toast(r.message, 'green');
  updateAllBalances(r.balance_btc, r.balance_usd);
  wTab('history');
}
function copyAddr() {
  const addr = document.getElementById('walletAddr').textContent;
  navigator.clipboard.writeText(addr).then(() => toast('Address copied!', 'green'));
}

// ── Sports Betting ─────────────────────────────────────────────────────────────
async function loadSportsMatches() {
  const grid = document.getElementById('matchesGrid');
  if (!grid) return;

  grid.innerHTML = '<div class="col-span-full flex flex-col items-center py-12 text-slate-500"><div class="w-10 h-10 border-2 border-purple-500/40 border-t-purple-500 rounded-full spin mb-3"></div>Loading matches...</div>';

  const r = await apiFetch('/api/football.php?action=fixtures&days=7');

  if (!r.success || !r.fixtures || r.fixtures.length === 0) {
    grid.innerHTML = '<div class="col-span-full text-center py-12 text-slate-500"><p>No matches available at the moment.</p><p class="text-xs mt-2">Please check back later.</p></div>';
    return;
  }

  grid.innerHTML = r.fixtures.map(m => renderMatchCard(m)).join('');

  // Update online count
  document.getElementById('sOpen').textContent = r.fixtures.filter(m => m.status === 'upcoming').length;
}

function renderMatchCard(m) {
  const matchTime = new Date(m.match_time * 1000);
  const timeStr = matchTime.toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

  const statusColors = {
    'upcoming': 'bg-blue-500/20 text-blue-400 border-blue-500/30',
    'live': 'bg-green-500/20 text-green-400 border-green-500/30',
    'finished': 'bg-slate-500/20 text-slate-400 border-slate-500/30'
  };

  const statusLabels = {
    'upcoming': 'Upcoming',
    'live': '🔴 LIVE',
    'finished': 'Finished'
  };

  const canBet = m.status === 'upcoming' && APP.loggedIn;

  return `
    <div class="card p-4">
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs text-slate-400">${m.league_name || 'Football'}</span>
        <span class="px-2 py-1 rounded-lg text-xs font-bold ${statusColors[m.status]}">${statusLabels[m.status]}</span>
      </div>
      <div class="flex items-center justify-between mb-4">
        <div class="text-center flex-1">
          <p class="text-white font-bold text-sm">${m.home_team}</p>
        </div>
        <div class="text-center px-3">
          ${m.status === 'live' ? `<span class="text-green-400 font-black text-lg">${m.home_score} - ${m.away_score}</span>` : '<span class="text-slate-500 text-xs">vs</span>'}
        </div>
        <div class="text-center flex-1">
          <p class="text-white font-bold text-sm">${m.away_team}</p>
        </div>
      </div>
      <div class="text-xs text-slate-400 text-center mb-3">${timeStr}</div>
      ${canBet ? `
        <button onclick="openBetModal(${m.id}, '${m.home_team}', '${m.away_team}')" class="w-full py-2 g-purple text-white rounded-xl font-bold text-sm hover:opacity-90 neon">
          Place Bet
        </button>
      ` : !APP.loggedIn ? `
        <button onclick="openModal('authModal')" class="w-full py-2 bg-slate-800 border border-slate-700 text-slate-400 rounded-xl font-bold text-sm">
          Sign In to Bet
        </button>
      ` : ''}
    </div>
  `;
}

function filterSports(sport) {
  document.querySelectorAll('.sport-filter-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.sport === sport);
    btn.classList.toggle('bg-purple-500/20', btn.dataset.sport === sport);
    btn.classList.toggle('border-purple-500/40', btn.dataset.sport === sport);
    btn.classList.toggle('text-purple-400', btn.dataset.sport === sport);
    btn.classList.toggle('bg-slate-800', btn.dataset.sport !== sport);
    btn.classList.toggle('border-slate-700', btn.dataset.sport !== sport);
    btn.classList.toggle('text-slate-400', btn.dataset.sport !== sport);
  });
  loadSportsMatches();
}

function openBetModal(matchId, homeTeam, awayTeam) {
  const modal = document.getElementById('betModal');
  document.getElementById('betMatchId').value = matchId;
  document.getElementById('betHomeTeam').textContent = homeTeam;
  document.getElementById('betAwayTeam').textContent = awayTeam;
  document.getElementById('betError').classList.add('hidden');
  document.getElementById('betAmount').value = '';
  document.getElementById('betOdds').textContent = '—';
  openModal('betModal');
}

function selectBetTeam(team) {
  document.querySelectorAll('.bet-team-btn').forEach(btn => {
    btn.classList.remove('border-purple-500', 'bg-purple-500/20');
    btn.classList.add('border-slate-700', 'bg-slate-800');
  });
  document.getElementById('btn-' + team).classList.remove('border-slate-700', 'bg-slate-800');
  document.getElementById('btn-' + team).classList.add('border-purple-500', 'bg-purple-500/20');
  document.getElementById('selectedTeam').value = team;
  updateBetOdds();
}

function updateBetOdds() {
  const amount = parseFloat(document.getElementById('betAmount').value) || 0;
  const betType = document.querySelector('input[name="betType"]:checked').value;

  if (amount <= 0) {
    document.getElementById('betOdds').textContent = '—';
    return;
  }

  if (betType === 'house') {
    const potentialWin = (amount * 1.5).toFixed(5);
    document.getElementById('betOdds').textContent = `Win: ${potentialWin} BTC (1.5x)`;
  } else {
    document.getElementById('betOdds').textContent = 'Win: Share of 75% P2P pool';
  }
}

async function placeBet() {
  const btn = document.getElementById('placeBetBtn');
  const err = document.getElementById('betError');

  const matchId = parseInt(document.getElementById('betMatchId').value);
  const team = document.getElementById('selectedTeam').value;
  const amount = parseFloat(document.getElementById('betAmount').value);
  const betType = document.querySelector('input[name="betType"]:checked').value;

  if (!team) { err.textContent = 'Please select a team to bet on'; err.classList.remove('hidden'); return; }
  if (!amount || amount <= 0) { err.textContent = 'Please enter a valid bet amount'; err.classList.remove('hidden'); return; }

  btn.textContent = 'Placing bet...'; btn.disabled = true;

  const r = await apiFetch('/api/sports.php?action=place_bet', {
    method: 'POST',
    body: JSON.stringify({ match_id: matchId, team_selection: team, amount_btc: amount, bet_type: betType })
  });

  btn.disabled = false; btn.textContent = 'Place Bet';

  if (!r.success) {
    err.textContent = r.error; err.classList.remove('hidden');
    return;
  }

  closeModal('betModal');
  toast('Bet placed successfully! Good luck!', 'green');
  updateAllBalances(r.new_balance);
}

// ── Admin Panel ───────────────────────────────────────────────────────────────
async function loadAdminPanel() {
  if (!APP.user || APP.user.email !== 'Viniemmanuel8@gmail.com') {
    toast('Access denied. Admin only.', 'red');
    return;
  }

  const [usersR, matchesR, betsR, lotteryR] = await Promise.all([
    apiFetch('/api/admin.php?action=list_users'),
    apiFetch('/api/admin.php?action=list_matches'),
    apiFetch('/api/admin.php?action=list_bets'),
    apiFetch('/api/admin.php?action=lottery_status')
  ]);

  if (usersR.success) {
    document.getElementById('adminUserCount').textContent = usersR.users?.length || 0;
    document.getElementById('adminUserList').innerHTML = (usersR.users || []).map(u => `
      <tr class="border-b border-slate-800">
        <td class="px-3 py-2 text-sm text-white">${u.username}</td>
        <td class="px-3 py-2 text-sm text-slate-400">${u.email || '—'}</td>
        <td class="px-3 py-2 text-sm text-green-400">${u.balance_btc || 0} BTC</td>
        <td class="px-3 py-2 text-sm text-slate-400">${u.created_at ? new Date(u.created_at * 1000).toLocaleDateString() : '—'}</td>
      </tr>
    `).join('');
  }

  if (matchesR.success) {
    document.getElementById('adminMatchCount').textContent = matchesR.matches?.length || 0;
  }

  if (betsR.success) {
    document.getElementById('adminBetCount').textContent = betsR.bets?.length || 0;
  }

  if (lotteryR.success) {
    document.getElementById('lotteryPool').textContent = lotteryR.pool_btc || 0;
    document.getElementById('lotteryParticipants').textContent = lotteryR.participants || 0;
  }
}

async function drawLottery() {
  if (!confirm('Are you sure you want to draw the lottery winner? This will credit the prize pool to the winner.')) return;

  const btn = document.getElementById('drawLotteryBtn');
  btn.textContent = 'Drawing...'; btn.disabled = true;

  const r = await apiFetch('/api/admin.php?action=draw_lottery', { method: 'POST' });

  btn.disabled = false; btn.textContent = 'Draw Winner';

  if (r.success) {
    toast(`Lottery winner: ${r.winner} wins ${r.prize} BTC!`, 'green', 8000);
    loadAdminPanel();
  } else {
    toast(r.error || 'Failed to draw lottery', 'red');
  }
}

async function syncFootballMatches() {
  const btn = document.getElementById('syncMatchesBtn');
  btn.textContent = 'Syncing...'; btn.disabled = true;

  const r = await apiFetch('/api/football.php?action=fixtures&days=7');

  btn.disabled = false; btn.textContent = 'Sync Matches';

  if (r.success) {
    toast(`Synced ${r.count} matches from API-Football`, 'green');
    loadAdminPanel();
  } else {
    toast('Failed to sync matches', 'red');
  }
}

// ── Init ──────────────────────────────────────────────────────────────────────
async function init() {
  await loadPrices();
  await Promise.all([loadLobby(), loadChat(), loadFeaturedMatches()]);
  startLobbyPoll();
  setInterval(loadPrices, 60000);
  setInterval(loadChat,   3000);
  setInterval(loadFeaturedMatches, 30000);
  // Check if user has an active game
  if (APP.loggedIn) {
    const r = await apiFetch('/api/tictactoe.php?action=list');
    if (r.success) {
      const myGame = r.games.find(g => APP.user && g.creator_id === APP.user.id);
      if (myGame) {
        APP.activeGameId = myGame.id;
        // Show banner
        toast('You have an open game waiting for a challenger!', 'purple', 6000);
      }
    }
  }
}

// ── Football Betting ──────────────────────────────────────────────────────────
let APP_FOOTBALL = {
  currentMatchId: null,
  selectedBetType: '1v1',
  selectedTeam: null,
};

async function loadFeaturedMatches() {
  const r = await apiFetch('/api/football-betting.php?action=featured_matches');
  if (!r.success) {
    document.getElementById('featuredMatchesGrid').innerHTML = '<div class="col-span-full text-center text-slate-500 py-8">Failed to load matches</div>';
    return;
  }
  
  const matches = r.matches || [];
  if (!matches.length) {
    document.getElementById('featuredMatchesGrid').innerHTML = '<div class="col-span-full text-center text-slate-500 py-8">No matches available</div>';
    return;
  }
  
  document.getElementById('featuredMatchesGrid').innerHTML = matches.map(m => `
    <div class="card p-4 hover:border-purple-500/50 cursor-pointer" onclick="openFootballBetting(${m.id}, '${m.home_team}', '${m.away_team}')">
      <div class="mb-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-slate-400">⚽ Football</span>
          <span class="text-xs px-2 py-1 rounded-full ${m.status === 'live' ? 'bg-red-500/20 text-red-400' : 'bg-green-500/20 text-green-400'}">
            ${m.status === 'live' ? '🔴 Live' : '⏱️ Upcoming'}
          </span>
        </div>
        <div class="flex items-center justify-between text-center gap-2">
          <div class="flex-1">
            <p class="text-white font-bold text-base truncate">${m.home_team}</p>
            <p class="text-slate-400 text-xs mt-1">${m.home_score || '—'}</p>
          </div>
          <div class="text-slate-500 font-bold text-xs px-2">vs</div>
          <div class="flex-1">
            <p class="text-white font-bold text-base truncate">${m.away_team}</p>
            <p class="text-slate-400 text-xs mt-1">${m.away_score || '—'}</p>
          </div>
        </div>
      </div>
      
      <div class="border-t border-slate-700 pt-3 mt-3">
        <div class="grid grid-cols-3 gap-2 text-center">
          <div class="bg-slate-800/50 rounded-lg p-2">
            <p class="text-xs text-slate-400 mb-1">1v1</p>
            <p class="text-sm font-bold text-purple-400">${m.bets_1v1 || 0}</p>
          </div>
          <div class="bg-slate-800/50 rounded-lg p-2">
            <p class="text-xs text-slate-400 mb-1">2v2</p>
            <p class="text-sm font-bold text-blue-400">${m.bets_2v2 || 0}</p>
          </div>
          <div class="bg-slate-800/50 rounded-lg p-2">
            <p class="text-xs text-slate-400 mb-1">Global</p>
            <p class="text-sm font-bold text-green-400">${m.bets_global || 0}</p>
          </div>
        </div>
      </div>
      
      <button onclick="openFootballBetting(${m.id}, '${m.home_team}', '${m.away_team}'); event.stopPropagation();" class="w-full mt-3 py-2 bg-purple-500/20 border border-purple-500/40 rounded-lg text-purple-400 font-bold text-xs hover:bg-purple-500/30 transition-all">
        Place Bet
      </button>
    </div>
  `).join('');
}

function openFootballBetting(matchId, homeTeam, awayTeam) {
  if (!APP.loggedIn) { openModal('authModal'); return; }
  
  APP_FOOTBALL.currentMatchId = matchId;
  APP_FOOTBALL.selectedBetType = '1v1';
  APP_FOOTBALL.selectedTeam = null;
  
  document.getElementById('fbMatchInfo').textContent = `${homeTeam} vs ${awayTeam}`;
  document.getElementById('fbTeam1').textContent = homeTeam;
  document.getElementById('fbTeam2').textContent = awayTeam;
  document.getElementById('fbTeam1Short').textContent = homeTeam.substring(0, 3).toUpperCase();
  document.getElementById('fbTeam2Short').textContent = awayTeam.substring(0, 3).toUpperCase();
  document.getElementById('fbBalance').textContent = APP.rawUser.balance_btc.toFixed(5);
  document.getElementById('fbAmount').value = '0.0001';
  document.getElementById('fbErr').classList.add('hidden');
  
  resetFBettingUI();
  updateFBettingInfo();
  
  openModal('footballBettingModal');
}

function resetFBettingUI() {
  ['1v1', '2v2', 'global'].forEach(type => {
    const btn = document.getElementById(`btn-bet-${type}`);
    btn.classList.remove('border-purple-500', 'bg-purple-500/20');
    btn.classList.add('border-slate-700', 'bg-slate-800');
  });
  
  ['home', 'draw', 'away'].forEach(team => {
    const btn = document.getElementById(`btn-team-${team}`);
    btn.classList.remove('border-purple-500', 'bg-purple-500/20');
    btn.classList.add('border-slate-700', 'bg-slate-800');
  });
  
  document.getElementById('btn-bet-1v1').classList.remove('border-slate-700', 'bg-slate-800');
  document.getElementById('btn-bet-1v1').classList.add('border-purple-500', 'bg-purple-500/20');
}

function selectBetType(type) {
  APP_FOOTBALL.selectedBetType = type;
  resetFBettingUI();
  document.getElementById(`btn-bet-${type}`).classList.remove('border-slate-700', 'bg-slate-800');
  document.getElementById(`btn-bet-${type}`).classList.add('border-purple-500', 'bg-purple-500/20');
  updateFBettingInfo();
}

function selectTeam(team) {
  APP_FOOTBALL.selectedTeam = team;
  document.querySelectorAll('[id^="btn-team-"]').forEach(btn => {
    btn.classList.remove('border-purple-500', 'bg-purple-500/20');
    btn.classList.add('border-slate-700', 'bg-slate-800');
  });
  document.getElementById(`btn-team-${team}`).classList.remove('border-slate-700', 'bg-slate-800');
  document.getElementById(`btn-team-${team}`).classList.add('border-purple-500', 'bg-purple-500/20');
  updateFBettingInfo();
}

function updateFBettingInfo() {
  const type = APP_FOOTBALL.selectedBetType;
  const team = APP_FOOTBALL.selectedTeam;
  const amount = parseFloat(document.getElementById('fbAmount').value) || 0;
  
  let info = '';
  if (!team) {
    info = 'Select your team to see bet details';
  } else if (type === '1v1') {
    info = `<strong>1v1 Head-to-Head:</strong> Find an opponent betting on the opposite team with the same amount. Winner takes all ${(amount * 2).toFixed(5)} BTC!`;
  } else if (type === '2v2') {
    info = `<strong>2v2 Team Play:</strong> Create a team with one teammate. Teams compete for the prize pool. Winnings split among team members.`;
  } else if (type === 'global') {
    info = `<strong>Global Pool:</strong> Everyone betting on the winning team shares ${(amount * 1.8).toFixed(5)} BTC (after 10% admin fee).`;
  }
  
  document.getElementById('fbInfo').innerHTML = info;
}

async function placeFBet() {
  const btn = document.getElementById('fbPlaceBtn');
  const err = document.getElementById('fbErr');
  
  const matchId = APP_FOOTBALL.currentMatchId;
  const betType = APP_FOOTBALL.selectedBetType;
  const team = APP_FOOTBALL.selectedTeam;
  const amount = parseFloat(document.getElementById('fbAmount').value);
  
  if (!team) { err.textContent = 'Please select a team'; err.classList.remove('hidden'); return; }
  if (!amount || amount < 0.0001) { err.textContent = 'Minimum bet is 0.0001 BTC'; err.classList.remove('hidden'); return; }
  if (amount > APP.rawUser.balance_btc) { err.textContent = 'Insufficient balance'; err.classList.remove('hidden'); return; }
  
  btn.textContent = 'Placing bet...'; btn.disabled = true;
  
  const endpoint = betType === '1v1' ? 'bet_1v1' : betType === '2v2' ? 'bet_2v2' : 'bet_global';
  const r = await apiFetch(`/api/football-betting.php?action=${endpoint}`, {
    method: 'POST',
    body: JSON.stringify({ match_id: matchId, team_selection: team, amount_btc: amount, team_name: APP.user.username + "'s Team" })
  });
  
  btn.textContent = 'Place Bet'; btn.disabled = false;
  
  if (!r.success) {
    err.textContent = r.error; err.classList.remove('hidden');
    return;
  }
  
  closeModal('footballBettingModal');
  const matched = r.matched ? ' Matched with opponent!' : ' Waiting for participants...';
  toast(`${betType.toUpperCase()} bet placed!${matched}`, 'green');
  updateAllBalances(r.new_balance);
  loadFeaturedMatches();
}

init();
</script>
</body>
</html>
