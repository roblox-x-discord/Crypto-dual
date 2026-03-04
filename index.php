<?php
// Mock data
$btcPrice = 67423.18;
$btcChange = 2.34;
$userBalance_btc = 0.04821;
$userBalance_usd = $userBalance_btc * $btcPrice;

$activeBets = [
    ['id'=>1,'user'=>'0xShadow','avatar'=>'SH','level'=>47,'amount_btc'=>0.005,'amount_usd'=>337.12,'game'=>'Coinflip','side'=>'heads','created'=>'12s ago','color'=>'purple'],
    ['id'=>2,'user'=>'NeonKing','avatar'=>'NK','level'=>92,'amount_btc'=>0.012,'amount_usd'=>809.08,'game'=>'Jackpot','side'=>null,'created'=>'34s ago','color'=>'cyan'],
    ['id'=>3,'user'=>'CryptoWolf','avatar'=>'CW','level'=>23,'amount_btc'=>0.002,'amount_usd'=>134.85,'game'=>'Coinflip','side'=>'tails','created'=>'1m ago','color'=>'green'],
    ['id'=>4,'user'=>'ViperX','avatar'=>'VX','level'=>61,'amount_btc'=>0.025,'amount_usd'=>1685.58,'game'=>'P2P Duel','side'=>null,'created'=>'2m ago','color'=>'orange'],
    ['id'=>5,'user'=>'GhostBet','avatar'=>'GB','level'=>8,'amount_btc'=>0.001,'amount_usd'=>67.42,'game'=>'Coinflip','side'=>'heads','created'=>'3m ago','color'=>'pink'],
    ['id'=>6,'user'=>'Rekt4Life','avatar'=>'RL','level'=>134,'amount_btc'=>0.050,'amount_usd'=>3371.16,'game'=>'Jackpot','side'=>null,'created'=>'4m ago','color'=>'yellow'],
    ['id'=>7,'user'=>'DarkPool','avatar'=>'DP','level'=>77,'amount_btc'=>0.008,'amount_usd'=>539.39,'game'=>'P2P Duel','side'=>null,'created'=>'5m ago','color'=>'red'],
    ['id'=>8,'user'=>'MoonShot','avatar'=>'MS','level'=>29,'amount_btc'=>0.003,'amount_usd'=>202.27,'game'=>'Coinflip','side'=>'tails','created'=>'7m ago','color'=>'blue'],
];

$transactions = [
    ['type'=>'Deposit','amount'=>'0.01000','status'=>'Confirmed','time'=>'2h ago','hash'=>'3a4b...f9c1'],
    ['type'=>'Withdraw','amount'=>'0.00500','status'=>'Confirmed','time'=>'1d ago','hash'=>'7e2a...b3d5'],
    ['type'=>'Deposit','amount'=>'0.02000','status'=>'Pending','time'=>'2d ago','hash'=>'9f1c...4a7b'],
    ['type'=>'Withdraw','amount'=>'0.00200','status'=>'Confirmed','time'=>'3d ago','hash'=>'2c8d...e6f0'],
];

$chatMessages = [
    ['user'=>'Rekt4Life','level'=>134,'msg'=>'just won 0.05 BTC on jackpot lets goooo','color'=>'yellow','time'=>'12:01'],
    ['user'=>'NeonKing','level'=>92,'msg'=>'gg wp, who wants a duel?','color'=>'cyan','time'=>'12:01'],
    ['user'=>'0xShadow','level'=>47,'msg'=>'coinflip anyone? 0.005 bet','color'=>'purple','time'=>'12:02'],
    ['user'=>'GhostBet','level'=>8,'msg'=>'how do i deposit?','color'=>'pink','time'=>'12:02'],
    ['user'=>'CryptoWolf','level'=>23,'msg'=>'click the deposit button up top','color'=>'green','time'=>'12:03'],
    ['user'=>'ViperX','level'=>61,'msg'=>'provably fair algo is solid here, checked it','color'=>'orange','time'=>'12:03'],
    ['user'=>'DarkPool','level'=>77,'msg'=>'🌧️ RAIN 0.0001 BTC TO 10 USERS!','color'=>'red','time'=>'12:04','rain'=>true],
    ['user'=>'MoonShot','level'=>29,'msg'=>'LFG! Thanks for the rain!','color'=>'blue','time'=>'12:04'],
    ['user'=>'NeonKing','level'=>92,'msg'=>'duel me @ 0.01 BTC, P2P straight up','color'=>'cyan','time'=>'12:05'],
    ['user'=>'Rekt4Life','level'=>134,'msg'=>'jackpot rolling at 70% odds rn insane','color'=>'yellow','time'=>'12:05'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>CryptoDuel — P2P Crypto Betting</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        slate: { 950:'#020617', 900:'#0f172a', 850:'#111827', 800:'#1e293b', 700:'#334155', 600:'#475569' },
        purple: { 400:'#c084fc', 500:'#a855f7', 600:'#9333ea', 700:'#7e22ce' },
        cyan:   { 400:'#22d3ee', 500:'#06b6d4' },
        neon:   { purple:'#a855f7', cyan:'#22d3ee', green:'#22c55e', orange:'#f97316', pink:'#ec4899', yellow:'#eab308', red:'#ef4444', blue:'#3b82f6' }
      },
      fontFamily: { sans: ['Inter','system-ui','sans-serif'] },
      animation: {
        'pulse-slow':'pulse 3s cubic-bezier(0.4,0,0.6,1) infinite',
        'ticker':'ticker 30s linear infinite',
        'glow':'glow 2s ease-in-out infinite alternate',
        'slide-in':'slideIn 0.3s ease-out',
        'bounce-in':'bounceIn 0.5s ease-out',
      },
      keyframes: {
        ticker:{ '0%':{transform:'translateX(100%)'},'100%':{transform:'translateX(-100%)'} },
        glow:{ '0%':{boxShadow:'0 0 5px #a855f7, 0 0 10px #a855f7'},'100%':{boxShadow:'0 0 20px #a855f7, 0 0 40px #a855f7, 0 0 60px #a855f7'} },
        slideIn:{ '0%':{transform:'translateY(-10px)',opacity:0},'100%':{transform:'translateY(0)',opacity:1} },
        bounceIn:{ '0%':{transform:'scale(0.95)',opacity:0},'60%':{transform:'scale(1.02)'},'100%':{transform:'scale(1)',opacity:1} },
      }
    }
  }
}
</script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
  * { box-sizing: border-box; }
  body { background:#0f172a; color:#e2e8f0; font-family:'Inter',sans-serif; overflow-x:hidden; }
  ::-webkit-scrollbar { width:4px; height:4px; }
  ::-webkit-scrollbar-track { background:#1e293b; }
  ::-webkit-scrollbar-thumb { background:#a855f7; border-radius:2px; }

  .neon-border { border:1px solid rgba(168,85,247,0.3); }
  .neon-border:hover { border-color:rgba(168,85,247,0.7); box-shadow:0 0 15px rgba(168,85,247,0.2); }
  .neon-glow { box-shadow:0 0 20px rgba(168,85,247,0.4), 0 0 40px rgba(168,85,247,0.1); }
  .card-hover { transition:all 0.25s ease; }
  .card-hover:hover { transform:translateY(-2px); box-shadow:0 8px 32px rgba(168,85,247,0.25); }
  .glass { background:rgba(30,41,59,0.8); backdrop-filter:blur(12px); }
  .glass-dark { background:rgba(15,23,42,0.9); backdrop-filter:blur(16px); }
  .gradient-purple { background:linear-gradient(135deg,#7e22ce,#a855f7); }
  .gradient-text { background:linear-gradient(135deg,#a855f7,#22d3ee); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
  .ticker-wrap { overflow:hidden; white-space:nowrap; }
  .ticker-move { display:inline-block; animation:ticker 40s linear infinite; }

  /* Sidebar */
  .sidebar { width:72px; transition:width 0.3s ease; }
  .sidebar:hover { width:200px; }
  .sidebar:hover .nav-label { opacity:1; width:auto; }
  .nav-label { opacity:0; width:0; overflow:hidden; transition:all 0.3s ease; white-space:nowrap; }

  /* Chat */
  .chat-bar { width:280px; }
  .chat-messages { height:calc(100vh - 280px); overflow-y:auto; }

  /* Bet card accent colors */
  .accent-purple { border-left:3px solid #a855f7; }
  .accent-cyan    { border-left:3px solid #22d3ee; }
  .accent-green   { border-left:3px solid #22c55e; }
  .accent-orange  { border-left:3px solid #f97316; }
  .accent-pink    { border-left:3px solid #ec4899; }
  .accent-yellow  { border-left:3px solid #eab308; }
  .accent-red     { border-left:3px solid #ef4444; }
  .accent-blue    { border-left:3px solid #3b82f6; }

  /* Modal */
  .modal-overlay { position:fixed; inset:0; background:rgba(2,6,23,0.85); z-index:100; display:none; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
  .modal-overlay.active { display:flex; }

  /* Responsive */
  @media(max-width:768px) {
    .sidebar { display:none; }
    .chat-bar { display:none; }
    .mobile-nav { display:flex !important; }
    .main-content { margin-left:0 !important; margin-right:0 !important; }
  }
  @media(min-width:769px) {
    .mobile-nav { display:none !important; }
  }

  /* Level badge */
  .level-badge { font-size:9px; padding:1px 5px; border-radius:4px; font-weight:700; }

  /* Coin animation */
  @keyframes flip { 0%,100%{transform:scaleX(1)} 50%{transform:scaleX(0)} }
  .coin-flip { animation:flip 0.6s ease-in-out infinite; }

  /* Join button pulse */
  .btn-join { position:relative; overflow:hidden; }
  .btn-join::after { content:''; position:absolute; inset:0; background:linear-gradient(135deg,rgba(255,255,255,0.1),transparent); opacity:0; transition:opacity 0.2s; }
  .btn-join:hover::after { opacity:1; }

  /* Stats row glow */
  .stat-glow { text-shadow:0 0 10px currentColor; }

  /* QR placeholder */
  .qr-grid { display:grid; grid-template-columns:repeat(20,1fr); gap:2px; }
  .qr-cell { aspect-ratio:1; border-radius:1px; }

  /* Provably fair */
  .pf-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); border-radius:20px; font-size:12px; color:#22c55e; }

  /* Mobile menu */
  .mobile-menu-overlay { position:fixed; inset:0; background:rgba(2,6,23,0.9); z-index:90; display:none; }
  .mobile-menu-overlay.active { display:block; }
  .mobile-sidebar { position:fixed; left:0; top:0; bottom:0; width:240px; background:#1e293b; z-index:95; transform:translateX(-100%); transition:transform 0.3s ease; padding:20px 0; }
  .mobile-sidebar.active { transform:translateX(0); }
</style>
</head>
<body class="min-h-screen">

<!-- ═══════════════════════════════════════════
     WALLET MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="walletModal">
  <div class="bg-slate-900 rounded-2xl w-full max-w-lg mx-4 overflow-hidden neon-border animate-bounce-in" style="max-height:90vh;overflow-y:auto;">
    <!-- Modal Header -->
    <div class="flex items-center justify-between p-5 border-b border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg gradient-purple flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
        </div>
        <div>
          <h2 class="text-white font-bold text-lg">Crypto Wallet</h2>
          <p class="text-slate-400 text-xs">Manage your funds securely</p>
        </div>
      </div>
      <button onclick="closeModal('walletModal')" class="text-slate-400 hover:text-white transition-colors p-2 rounded-lg hover:bg-slate-800">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Modal Tabs -->
    <div class="flex border-b border-slate-800">
      <button onclick="switchTab('deposit')" id="tab-deposit" class="flex-1 py-3 text-sm font-semibold text-purple-400 border-b-2 border-purple-500 transition-all">Deposit</button>
      <button onclick="switchTab('withdraw')" id="tab-withdraw" class="flex-1 py-3 text-sm font-semibold text-slate-400 border-b-2 border-transparent hover:text-white transition-all">Withdraw</button>
      <button onclick="switchTab('history')" id="tab-history" class="flex-1 py-3 text-sm font-semibold text-slate-400 border-b-2 border-transparent hover:text-white transition-all">History</button>
    </div>

    <!-- Deposit Tab -->
    <div id="panel-deposit" class="p-5">
      <div class="text-center mb-5">
        <p class="text-slate-400 text-sm mb-4">Send Bitcoin to the address below</p>
        <!-- QR Code Placeholder -->
        <div class="inline-block p-4 bg-white rounded-xl mb-4">
          <div style="width:140px;height:140px;position:relative;">
            <svg viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg" width="140" height="140">
              <!-- QR decorative pattern -->
              <rect width="140" height="140" fill="white"/>
              <!-- Top-left finder -->
              <rect x="8" y="8" width="38" height="38" rx="3" fill="#0f172a"/>
              <rect x="13" y="13" width="28" height="28" rx="2" fill="white"/>
              <rect x="18" y="18" width="18" height="18" rx="1" fill="#0f172a"/>
              <!-- Top-right finder -->
              <rect x="94" y="8" width="38" height="38" rx="3" fill="#0f172a"/>
              <rect x="99" y="13" width="28" height="28" rx="2" fill="white"/>
              <rect x="104" y="18" width="18" height="18" rx="1" fill="#0f172a"/>
              <!-- Bottom-left finder -->
              <rect x="8" y="94" width="38" height="38" rx="3" fill="#0f172a"/>
              <rect x="13" y="99" width="28" height="28" rx="2" fill="white"/>
              <rect x="18" y="104" width="18" height="18" rx="1" fill="#0f172a"/>
              <!-- Data modules -->
              <rect x="54" y="8" width="6" height="6" fill="#0f172a"/><rect x="64" y="8" width="6" height="6" fill="#0f172a"/>
              <rect x="54" y="18" width="6" height="6" fill="#0f172a"/><rect x="74" y="18" width="6" height="6" fill="#0f172a"/>
              <rect x="54" y="28" width="6" height="6" fill="#0f172a"/><rect x="64" y="28" width="6" height="6" fill="#0f172a"/><rect x="74" y="28" width="6" height="6" fill="#0f172a"/>
              <rect x="8" y="54" width="6" height="6" fill="#0f172a"/><rect x="18" y="54" width="6" height="6" fill="#0f172a"/>
              <rect x="28" y="54" width="6" height="6" fill="#0f172a"/><rect x="44" y="54" width="6" height="6" fill="#0f172a"/>
              <rect x="54" y="54" width="6" height="6" fill="#0f172a"/><rect x="64" y="54" width="6" height="6" fill="#0f172a"/>
              <rect x="74" y="54" width="6" height="6" fill="#0f172a"/><rect x="84" y="54" width="6" height="6" fill="#0f172a"/>
              <rect x="104" y="54" width="6" height="6" fill="#0f172a"/><rect x="114" y="54" width="6" height="6" fill="#0f172a"/>
              <rect x="124" y="54" width="6" height="6" fill="#0f172a"/>
              <rect x="8" y="64" width="6" height="6" fill="#0f172a"/><rect x="28" y="64" width="6" height="6" fill="#0f172a"/>
              <rect x="44" y="64" width="6" height="6" fill="#0f172a"/><rect x="64" y="64" width="6" height="6" fill="#0f172a"/>
              <rect x="84" y="64" width="6" height="6" fill="#0f172a"/><rect x="94" y="64" width="6" height="6" fill="#0f172a"/>
              <rect x="114" y="64" width="6" height="6" fill="#0f172a"/>
              <rect x="8" y="74" width="6" height="6" fill="#0f172a"/><rect x="18" y="74" width="6" height="6" fill="#0f172a"/>
              <rect x="38" y="74" width="6" height="6" fill="#0f172a"/><rect x="54" y="74" width="6" height="6" fill="#0f172a"/>
              <rect x="64" y="74" width="6" height="6" fill="#0f172a"/><rect x="74" y="74" width="6" height="6" fill="#0f172a"/>
              <rect x="94" y="74" width="6" height="6" fill="#0f172a"/><rect x="104" y="74" width="6" height="6" fill="#0f172a"/>
              <rect x="124" y="74" width="6" height="6" fill="#0f172a"/>
              <rect x="18" y="84" width="6" height="6" fill="#0f172a"/><rect x="38" y="84" width="6" height="6" fill="#0f172a"/>
              <rect x="54" y="84" width="6" height="6" fill="#0f172a"/><rect x="74" y="84" width="6" height="6" fill="#0f172a"/>
              <rect x="94" y="84" width="6" height="6" fill="#0f172a"/><rect x="114" y="84" width="6" height="6" fill="#0f172a"/>
              <rect x="54" y="104" width="6" height="6" fill="#0f172a"/><rect x="64" y="104" width="6" height="6" fill="#0f172a"/>
              <rect x="84" y="104" width="6" height="6" fill="#0f172a"/><rect x="104" y="104" width="6" height="6" fill="#0f172a"/>
              <rect x="124" y="104" width="6" height="6" fill="#0f172a"/>
              <rect x="54" y="114" width="6" height="6" fill="#0f172a"/><rect x="74" y="114" width="6" height="6" fill="#0f172a"/>
              <rect x="94" y="114" width="6" height="6" fill="#0f172a"/><rect x="114" y="114" width="6" height="6" fill="#0f172a"/>
              <rect x="54" y="124" width="6" height="6" fill="#0f172a"/><rect x="64" y="124" width="6" height="6" fill="#0f172a"/>
              <rect x="84" y="124" width="6" height="6" fill="#0f172a"/><rect x="104" y="124" width="6" height="6" fill="#0f172a"/>
            </svg>
          </div>
        </div>
        <p class="text-slate-400 text-xs mb-2">Your BTC Deposit Address</p>
        <div class="flex items-center gap-2 bg-slate-800 rounded-lg p-3 text-left">
          <span class="text-purple-300 text-xs font-mono flex-1 truncate" id="btcAddress">bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh</span>
          <button onclick="copyAddress()" class="text-slate-400 hover:text-purple-400 transition-colors shrink-0" title="Copy">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          </button>
        </div>
      </div>
      <div class="bg-slate-800 rounded-xl p-4 border border-slate-700">
        <div class="flex items-start gap-3">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" class="shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div>
            <p class="text-yellow-400 text-xs font-semibold mb-1">Important Notice</p>
            <p class="text-slate-400 text-xs leading-relaxed">Minimum deposit: <span class="text-white">0.001 BTC</span>. Send only Bitcoin to this address. Transactions require <span class="text-white">2 confirmations</span> (~20 min).</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Withdraw Tab -->
    <div id="panel-withdraw" class="p-5 hidden">
      <div class="mb-5">
        <div class="flex items-center justify-between mb-4 p-3 bg-slate-800 rounded-xl">
          <span class="text-slate-400 text-sm">Available Balance</span>
          <div class="text-right">
            <p class="text-white font-bold"><?php echo number_format($userBalance_btc, 5); ?> BTC</p>
            <p class="text-slate-400 text-xs">≈ $<?php echo number_format($userBalance_usd, 2); ?></p>
          </div>
        </div>
        <label class="text-slate-400 text-xs font-medium mb-2 block">Withdrawal Address</label>
        <input type="text" placeholder="Enter your BTC address..." class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 mb-4 transition-all"/>
        <label class="text-slate-400 text-xs font-medium mb-2 block">Amount (BTC)</label>
        <div class="relative mb-4">
          <input type="number" placeholder="0.00000" step="0.00001" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition-all"/>
          <button class="absolute right-3 top-1/2 -translate-y-1/2 text-purple-400 text-xs font-bold hover:text-purple-300 transition-colors">MAX</button>
        </div>
        <div class="flex gap-2 mb-5">
          <?php foreach([0.25,0.5,0.75,1.0] as $pct): ?>
          <button class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-purple-500 rounded-lg text-slate-400 hover:text-purple-400 text-xs font-semibold transition-all"><?php echo ($pct*100); ?>%</button>
          <?php endforeach; ?>
        </div>
        <button class="w-full py-3 gradient-purple rounded-xl text-white font-bold text-sm hover:opacity-90 transition-opacity neon-glow">
          Withdraw BTC
        </button>
        <p class="text-center text-slate-500 text-xs mt-3">Network fee: ~0.00002 BTC · Min: 0.001 BTC</p>
      </div>
    </div>

    <!-- History Tab -->
    <div id="panel-history" class="p-5 hidden">
      <div class="space-y-3">
        <?php foreach($transactions as $tx): ?>
        <div class="flex items-center gap-3 p-3 bg-slate-800 rounded-xl hover:bg-slate-750 transition-colors">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center <?php echo $tx['type']==='Deposit' ? 'bg-green-500/10' : 'bg-red-500/10'; ?>">
            <?php if($tx['type']==='Deposit'): ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
            <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
            <?php endif; ?>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-white text-sm font-semibold"><?php echo $tx['type']; ?></p>
            <p class="text-slate-500 text-xs font-mono truncate"><?php echo $tx['hash']; ?></p>
          </div>
          <div class="text-right shrink-0">
            <p class="text-white text-sm font-bold"><?php echo $tx['amount']; ?> BTC</p>
            <div class="flex items-center gap-1 justify-end">
              <div class="w-1.5 h-1.5 rounded-full <?php echo $tx['status']==='Confirmed' ? 'bg-green-400' : 'bg-yellow-400 animate-pulse'; ?>"></div>
              <span class="text-xs <?php echo $tx['status']==='Confirmed' ? 'text-green-400' : 'text-yellow-400'; ?>"><?php echo $tx['status']; ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     MAIN LAYOUT
═══════════════════════════════════════════ -->
<div class="flex min-h-screen">

  <!-- ─── SIDEBAR ─── -->
  <aside class="sidebar fixed left-0 top-0 h-full bg-slate-900 border-r border-slate-800 z-50 flex flex-col py-4 overflow-hidden">
    <!-- Logo -->
    <div class="flex items-center gap-3 px-4 mb-6">
      <div class="w-9 h-9 shrink-0 rounded-xl gradient-purple flex items-center justify-center neon-glow">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      </div>
      <span class="nav-label text-white font-black text-lg tracking-tight">Crypto<span class="text-purple-400">Duel</span></span>
    </div>

    <!-- Nav Links -->
    <nav class="flex-1 space-y-1 px-2">
      <?php
      $navItems = [
        ['icon'=>'<circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/>','label'=>'Coinflip','active'=>true,'badge'=>null],
        ['icon'=>'<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>','label'=>'Jackpot','active'=>false,'badge'=>'HOT'],
        ['icon'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>','label'=>'P2P Duels','active'=>false,'badge'=>'8'],
        ['icon'=>'<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>','label'=>'Marketplace','active'=>false,'badge'=>null],
        ['icon'=>'<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>','label'=>'Leaderboard','active'=>false,'badge'=>null],
        ['icon'=>'<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>','label'=>'Live Bets','active'=>false,'badge'=>'LIVE'],
      ];
      foreach($navItems as $item):
      ?>
      <a href="#" class="flex items-center gap-3 px-3 py-3 rounded-xl transition-all <?php echo $item['active'] ? 'bg-purple-600/20 text-purple-400' : 'text-slate-400 hover:text-white hover:bg-slate-800'; ?> group relative">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><?php echo $item['icon']; ?></svg>
        <span class="nav-label text-sm font-medium"><?php echo $item['label']; ?></span>
        <?php if($item['badge']): ?>
        <span class="nav-label level-badge <?php echo $item['badge']==='LIVE' ? 'bg-red-500/20 text-red-400' : ($item['badge']==='HOT' ? 'bg-orange-500/20 text-orange-400' : 'bg-purple-500/20 text-purple-400'); ?>">
          <?php echo $item['badge']; ?>
        </span>
        <?php endif; ?>
        <?php if($item['active']): ?>
        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-purple-500 rounded-r-full"></div>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <!-- Bottom -->
    <div class="px-2 space-y-1 mt-4 border-t border-slate-800 pt-4">
      <a href="#" class="flex items-center gap-3 px-3 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
        <span class="nav-label text-sm font-medium">Settings</span>
      </a>
    </div>
  </aside>

  <!-- ─── MAIN CONTENT ─── -->
  <div class="flex-1 flex flex-col" style="margin-left:72px;margin-right:280px;" id="mainContent">

    <!-- ═══ HEADER ═══ -->
    <header class="sticky top-0 z-40 bg-slate-900/95 border-b border-slate-800 backdrop-blur-xl">
      <!-- Ticker Bar -->
      <div class="bg-slate-950 border-b border-slate-800/50 py-1.5 overflow-hidden">
        <div class="ticker-wrap">
          <div class="ticker-move text-xs text-slate-400 flex items-center gap-8">
            <?php
            $tickers = [
              ['sym'=>'BTC','price'=>$btcPrice,'chg'=>$btcChange,'color'=>'orange'],
              ['sym'=>'ETH','price'=>3421.55,'chg'=>-0.87,'color'=>'blue'],
              ['sym'=>'SOL','price'=>182.34,'chg'=>5.21,'color'=>'purple'],
              ['sym'=>'BNB','price'=>421.18,'chg'=>1.42,'color'=>'yellow'],
              ['sym'=>'XRP','price'=>0.5821,'chg'=>-2.14,'color'=>'cyan'],
              ['sym'=>'DOGE','price'=>0.1234,'chg'=>8.92,'color'=>'yellow'],
              ['sym'=>'ADA','price'=>0.4521,'chg'=>-1.23,'color'=>'blue'],
              ['sym'=>'AVAX','price'=>35.82,'chg'=>3.76,'color'=>'red'],
              ['sym'=>'MATIC','price'=>0.8234,'chg'=>-0.54,'color'=>'purple'],
              ['sym'=>'LINK','price'=>14.28,'chg'=>2.89,'color'=>'blue'],
            ];
            foreach($tickers as $t):
            ?>
            <span class="flex items-center gap-2 whitespace-nowrap">
              <span class="font-bold text-white"><?php echo $t['sym']; ?></span>
              <span class="text-slate-300">$<?php echo number_format($t['price'],2); ?></span>
              <span class="<?php echo $t['chg']>=0 ? 'text-green-400' : 'text-red-400'; ?> font-semibold">
                <?php echo $t['chg']>=0 ? '+' : ''; ?><?php echo $t['chg']; ?>%
              </span>
              <span class="text-slate-700">|</span>
            </span>
            <?php endforeach; ?>
            <!-- Duplicate for seamless loop -->
            <?php foreach($tickers as $t): ?>
            <span class="flex items-center gap-2 whitespace-nowrap">
              <span class="font-bold text-white"><?php echo $t['sym']; ?></span>
              <span class="text-slate-300">$<?php echo number_format($t['price'],2); ?></span>
              <span class="<?php echo $t['chg']>=0 ? 'text-green-400' : 'text-red-400'; ?> font-semibold">
                <?php echo $t['chg']>=0 ? '+' : ''; ?><?php echo $t['chg']; ?>%
              </span>
              <span class="text-slate-700">|</span>
            </span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Header Main Row -->
      <div class="flex items-center gap-3 px-4 py-3">
        <!-- Mobile menu button -->
        <button onclick="toggleMobileMenu()" class="mobile-nav text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <!-- Logo (mobile) -->
        <div class="mobile-nav items-center gap-2">
          <span class="text-white font-black text-base">Crypto<span class="text-purple-400">Duel</span></span>
        </div>

        <!-- BTC Price Widget -->
        <div class="hidden sm:flex items-center gap-3 bg-slate-800 rounded-xl px-4 py-2 border border-slate-700">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-full bg-orange-500/20 flex items-center justify-center">
              <span class="text-orange-400 font-black text-xs">₿</span>
            </div>
            <div>
              <p class="text-white font-bold text-sm leading-none">$<?php echo number_format($btcPrice, 2); ?></p>
              <p class="text-green-400 text-xs font-semibold">+<?php echo $btcChange; ?>%</p>
            </div>
          </div>
          <div class="w-px h-8 bg-slate-700"></div>
          <div class="text-xs">
            <div class="flex items-center gap-1">
              <div class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></div>
              <span class="text-green-400 font-semibold">LIVE</span>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="hidden lg:flex items-center gap-4 ml-2">
          <div class="text-center">
            <p class="text-slate-400 text-xs">Online</p>
            <p class="text-green-400 font-bold text-sm">2,847</p>
          </div>
          <div class="text-center">
            <p class="text-slate-400 text-xs">Active Bets</p>
            <p class="text-purple-400 font-bold text-sm">412</p>
          </div>
          <div class="text-center">
            <p class="text-slate-400 text-xs">24h Volume</p>
            <p class="text-cyan-400 font-bold text-sm">128.4 BTC</p>
          </div>
        </div>

        <div class="flex-1"></div>

        <!-- Deposit Button -->
        <button onclick="openModal('walletModal')" class="btn-join flex items-center gap-2 gradient-purple text-white px-4 py-2.5 rounded-xl font-bold text-sm transition-all hover:opacity-90 neon-glow">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span class="hidden sm:inline">Deposit</span>
          <span class="sm:hidden">+</span>
        </button>

        <!-- User Profile -->
        <div class="flex items-center gap-2 bg-slate-800 rounded-xl px-3 py-2 border border-slate-700 cursor-pointer hover:border-slate-600 transition-all">
          <div class="relative">
            <div class="w-8 h-8 rounded-lg gradient-purple flex items-center justify-center font-bold text-white text-xs">YO</div>
            <div class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-400 rounded-full border border-slate-800"></div>
          </div>
          <div class="hidden sm:block">
            <p class="text-white font-bold text-xs leading-tight">You</p>
            <p class="text-slate-400 text-xs leading-tight font-mono"><?php echo number_format($userBalance_btc, 5); ?> BTC</p>
          </div>
          <div class="hidden sm:block">
            <p class="text-slate-500 text-xs">≈</p>
            <p class="text-slate-300 text-xs font-semibold">$<?php echo number_format($userBalance_usd, 0); ?></p>
          </div>
        </div>

        <!-- Notification Bell -->
        <button class="relative p-2.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-400 hover:text-white hover:border-slate-600 transition-all">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <div class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></div>
        </button>
      </div>
    </header>

    <!-- ═══ ARENA - MAIN FEED ═══ -->
    <main class="flex-1 p-4 lg:p-6">

      <!-- Arena Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-black text-white tracking-tight">Live <span class="gradient-text">Arena</span></h1>
          <p class="text-slate-400 text-sm mt-0.5">Active P2P bets — Join to challenge</p>
        </div>
        <div class="flex items-center gap-3">
          <!-- Filter Tabs -->
          <div class="flex bg-slate-800 rounded-xl p-1 border border-slate-700">
            <?php foreach(['All','Coinflip','Jackpot','P2P Duel'] as $i=>$tab): ?>
            <button onclick="filterBets(this,'<?php echo $tab; ?>')" class="bet-filter px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?php echo $i===0 ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white'; ?>">
              <?php echo $tab; ?>
            </button>
            <?php endforeach; ?>
          </div>
          <!-- Create Bet -->
          <button class="btn-join flex items-center gap-2 bg-slate-800 border border-purple-500/50 hover:border-purple-500 text-purple-400 hover:text-purple-300 px-4 py-2 rounded-xl font-bold text-sm transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span class="hidden sm:inline">Create Bet</span>
          </button>
        </div>
      </div>

      <!-- Stats Bar -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <?php
        $stats = [
          ['label'=>'Total Wagered Today','value'=>'48.2 BTC','sub'=>'≈ $3.2M','icon'=>'<path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>','color'=>'purple'],
          ['label'=>'Biggest Win Today','value'=>'2.5 BTC','sub'=>'by Rekt4Life','icon'=>'<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>','color'=>'yellow'],
          ['label'=>'Active Players','value'=>'2,847','sub'=>'+124 in 1h','icon'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>','color'=>'green'],
          ['label'=>'Jackpot Pool','value'=>'0.85 BTC','sub'=>'≈ $57,309','icon'=>'<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>','color'=>'cyan'],
        ];
        foreach($stats as $s):
        ?>
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700 hover:border-slate-600 transition-all">
          <div class="flex items-start justify-between mb-2">
            <p class="text-slate-400 text-xs font-medium leading-tight"><?php echo $s['label']; ?></p>
            <div class="w-7 h-7 rounded-lg bg-<?php echo $s['color']; ?>-500/10 flex items-center justify-center shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="<?php
                $colors=['purple'=>'#a855f7','yellow'=>'#eab308','green'=>'#22c55e','cyan'=>'#22d3ee'];
                echo $colors[$s['color']];
              ?>" stroke-width="2"><?php echo $s['icon']; ?></svg>
            </div>
          </div>
          <p class="text-white font-black text-lg leading-none"><?php echo $s['value']; ?></p>
          <p class="text-slate-500 text-xs mt-1"><?php echo $s['sub']; ?></p>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Bet Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4" id="betGrid">
        <?php foreach($activeBets as $bet): ?>
        <?php
        $gameColors = [
          'Coinflip'=>'purple',
          'Jackpot'=>'yellow',
          'P2P Duel'=>'cyan',
        ];
        $gc = $gameColors[$bet['game']] ?? 'purple';
        $gameIcons = [
          'Coinflip'=>'<circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/>',
          'Jackpot'=>'<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
          'P2P Duel'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        ];
        $avatarColors = [
          'purple'=>'from-purple-600 to-purple-800',
          'cyan'=>'from-cyan-600 to-cyan-800',
          'green'=>'from-green-600 to-green-800',
          'orange'=>'from-orange-600 to-orange-800',
          'pink'=>'from-pink-600 to-pink-800',
          'yellow'=>'from-yellow-600 to-yellow-800',
          'red'=>'from-red-600 to-red-800',
          'blue'=>'from-blue-600 to-blue-800',
        ];
        $ac = $avatarColors[$bet['color']] ?? 'from-purple-600 to-purple-800';
        ?>
        <div class="bet-card bg-slate-800 rounded-2xl overflow-hidden border border-slate-700 card-hover accent-<?php echo $bet['color']; ?> group" data-game="<?php echo $bet['game']; ?>">
          <!-- Card Top -->
          <div class="p-4 pb-3">
            <div class="flex items-start justify-between mb-3">
              <!-- Creator Info -->
              <div class="flex items-center gap-3">
                <div class="relative">
                  <div class="w-11 h-11 rounded-xl bg-gradient-to-br <?php echo $ac; ?> flex items-center justify-center font-black text-white text-sm shadow-lg">
                    <?php echo $bet['avatar']; ?>
                  </div>
                  <!-- Online indicator -->
                  <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-800"></div>
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <p class="text-white font-bold text-sm"><?php echo $bet['user']; ?></p>
                    <span class="level-badge bg-slate-700 text-slate-300">LVL <?php echo $bet['level']; ?></span>
                  </div>
                  <p class="text-slate-500 text-xs mt-0.5"><?php echo $bet['created']; ?></p>
                </div>
              </div>
              <!-- Game Badge -->
              <div class="flex items-center gap-1.5 px-2.5 py-1 bg-<?php echo $gc; ?>-500/10 border border-<?php echo $gc; ?>-500/30 rounded-lg">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-<?php echo $gc; ?>-400" style="color:<?php
                  $hexes=['purple'=>'#a855f7','yellow'=>'#eab308','cyan'=>'#22d3ee'];
                  echo $hexes[$gc] ?? '#a855f7';
                ?>"><?php echo $gameIcons[$bet['game']]; ?></svg>
                <span class="text-xs font-bold" style="color:<?php echo $hexes[$gc] ?? '#a855f7'; ?>"><?php echo $bet['game']; ?></span>
              </div>
            </div>

            <!-- Amount -->
            <div class="bg-slate-900 rounded-xl p-3 mb-3">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-slate-400 text-xs mb-1">Bet Amount</p>
                  <div class="flex items-baseline gap-2">
                    <span class="text-white font-black text-xl"><?php echo number_format($bet['amount_btc'],4); ?></span>
                    <span class="text-orange-400 font-bold text-sm">BTC</span>
                  </div>
                  <p class="text-slate-500 text-xs mt-0.5">≈ $<?php echo number_format($bet['amount_usd'],2); ?></p>
                </div>
                <div class="text-right">
                  <p class="text-slate-400 text-xs mb-1">Win</p>
                  <p class="text-green-400 font-black text-xl"><?php echo number_format($bet['amount_btc']*2,4); ?></p>
                  <p class="text-slate-500 text-xs mt-0.5">BTC</p>
                </div>
              </div>
              <?php if($bet['game']==='Coinflip' && $bet['side']): ?>
              <div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-800">
                <span class="text-slate-400 text-xs">Side:</span>
                <div class="flex items-center gap-1 px-2 py-0.5 bg-slate-800 rounded-md">
                  <span class="text-xs"><?php echo $bet['side']==='heads' ? '🌕' : '🌑'; ?></span>
                  <span class="text-white text-xs font-semibold capitalize"><?php echo $bet['side']; ?></span>
                </div>
                <span class="text-slate-500 text-xs ml-auto">50% win chance</span>
              </div>
              <?php elseif($bet['game']==='Jackpot'): ?>
              <div class="mt-2 pt-2 border-t border-slate-800">
                <div class="flex items-center justify-between text-xs mb-1">
                  <span class="text-slate-400">Your odds</span>
                  <span class="text-yellow-400 font-semibold"><?php echo rand(15,65); ?>%</span>
                </div>
                <div class="h-1.5 bg-slate-700 rounded-full overflow-hidden">
                  <div class="h-full bg-gradient-to-r from-yellow-500 to-orange-500 rounded-full" style="width:<?php echo rand(15,65); ?>%"></div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Card Footer -->
          <div class="px-4 pb-4 flex items-center gap-2">
            <button onclick="joinBet(<?php echo $bet['id']; ?>)" class="btn-join flex-1 py-2.5 gradient-purple rounded-xl text-white font-bold text-sm transition-all hover:opacity-90 flex items-center justify-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              Join
            </button>
            <button class="p-2.5 bg-slate-700 hover:bg-slate-600 rounded-xl text-slate-400 hover:text-white transition-all" title="Spectate">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="p-2.5 bg-slate-700 hover:bg-slate-600 rounded-xl text-slate-400 hover:text-white transition-all" title="Share">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </button>
          </div>
        </div>
        <?php endforeach; ?>

        <!-- Create Your Own Bet Card -->
        <div class="bg-slate-800/50 rounded-2xl overflow-hidden border-2 border-dashed border-slate-700 hover:border-purple-500/50 transition-all card-hover cursor-pointer group flex flex-col items-center justify-center py-10 px-6" onclick="openModal('walletModal')">
          <div class="w-14 h-14 rounded-2xl bg-purple-600/10 border-2 border-dashed border-purple-500/30 group-hover:border-purple-500/60 flex items-center justify-center mb-4 transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </div>
          <p class="text-white font-bold text-sm mb-1">Create a Bet</p>
          <p class="text-slate-500 text-xs text-center">Challenge any player to a duel. Set your terms and wait for a challenger.</p>
        </div>
      </div>

      <!-- Recent Winners -->
      <div class="mt-8">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold text-white">Recent <span class="text-yellow-400">Winners</span></h2>
          <button class="text-slate-400 hover:text-white text-sm transition-colors flex items-center gap-1">
            View All
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-slate-700">
                  <th class="text-left text-slate-400 text-xs font-semibold px-4 py-3">Player</th>
                  <th class="text-left text-slate-400 text-xs font-semibold px-4 py-3">Game</th>
                  <th class="text-right text-slate-400 text-xs font-semibold px-4 py-3">Amount</th>
                  <th class="text-right text-slate-400 text-xs font-semibold px-4 py-3">Profit</th>
                  <th class="text-right text-slate-400 text-xs font-semibold px-4 py-3">Time</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-700/50">
                <?php
                $winners=[
                  ['user'=>'Rekt4Life','level'=>134,'game'=>'Jackpot','bet'=>'0.05','profit'=>'+0.0478','time'=>'2m','color'=>'yellow'],
                  ['user'=>'NeonKing','level'=>92,'game'=>'Coinflip','bet'=>'0.012','profit'=>'+0.0108','time'=>'5m','color'=>'cyan'],
                  ['user'=>'ViperX','level'=>61,'game'=>'P2P Duel','bet'=>'0.025','profit'=>'+0.0225','time'=>'8m','color'=>'orange'],
                  ['user'=>'DarkPool','level'=>77,'game'=>'Coinflip','bet'=>'0.008','profit'=>'+0.0072','time'=>'12m','color'=>'red'],
                  ['user'=>'0xShadow','level'=>47,'game'=>'Jackpot','bet'=>'0.005','profit'=>'+0.0312','time'=>'15m','color'=>'purple'],
                ];
                foreach($winners as $w):
                $acl=$avatarColors[$w['color']];
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-lg bg-gradient-to-br <?php echo $acl; ?> flex items-center justify-center font-bold text-white text-xs shrink-0">
                        <?php echo strtoupper(substr($w['user'],0,2)); ?>
                      </div>
                      <div>
                        <p class="text-white text-sm font-semibold"><?php echo $w['user']; ?></p>
                        <span class="level-badge bg-slate-700 text-slate-400">LVL <?php echo $w['level']; ?></span>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3"><span class="text-slate-300 text-sm"><?php echo $w['game']; ?></span></td>
                  <td class="px-4 py-3 text-right"><span class="text-white text-sm font-semibold"><?php echo $w['bet']; ?> BTC</span></td>
                  <td class="px-4 py-3 text-right"><span class="text-green-400 text-sm font-bold"><?php echo $w['profit']; ?> BTC</span></td>
                  <td class="px-4 py-3 text-right"><span class="text-slate-400 text-sm"><?php echo $w['time']; ?> ago</span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ═══ PROVABLY FAIR FOOTER ═══ -->
      <footer class="mt-8 pb-8">
        <!-- Provably Fair Section -->
        <div class="bg-slate-800 rounded-2xl p-6 border border-slate-700 mb-6">
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-green-500/10 border border-green-500/20 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </div>
              <div>
                <h3 class="text-white font-bold text-base">Provably Fair Gaming</h3>
                <p class="text-slate-400 text-xs mt-0.5">Every outcome is cryptographically verifiable</p>
              </div>
            </div>
            <div class="flex flex-wrap gap-2">
              <span class="pf-chip">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                SHA-256 Verified
              </span>
              <span class="pf-chip">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Audited 2024
              </span>
              <span class="pf-chip">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                Open Source RNG
              </span>
            </div>
          </div>

          <div class="mt-4 pt-4 border-t border-slate-700">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div class="bg-slate-900 rounded-xl p-4">
                <p class="text-slate-400 text-xs mb-2 flex items-center gap-1">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22d3ee" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  Server Seed Hash
                </p>
                <p class="text-cyan-400 font-mono text-xs truncate">a3f8c9d2e1b4...7f3a</p>
              </div>
              <div class="bg-slate-900 rounded-xl p-4">
                <p class="text-slate-400 text-xs mb-2 flex items-center gap-1">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                  Client Seed
                </p>
                <p class="text-purple-400 font-mono text-xs truncate">Your seed: b7e2...4c1d</p>
              </div>
              <div class="bg-slate-900 rounded-xl p-4">
                <p class="text-slate-400 text-xs mb-2 flex items-center gap-1">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                  Verify Last Bet
                </p>
                <button class="text-green-400 font-semibold text-xs hover:text-green-300 transition-colors">Verify on chain →</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Links -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-lg gradient-purple flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            </div>
            <span class="text-white font-black text-sm">Crypto<span class="text-purple-400">Duel</span></span>
          </div>
          <div class="flex flex-wrap gap-4 justify-center">
            <?php foreach(['Terms of Service','Privacy Policy','Responsible Gaming','Support','API'] as $link): ?>
            <a href="#" class="text-slate-500 hover:text-slate-300 text-xs transition-colors"><?php echo $link; ?></a>
            <?php endforeach; ?>
          </div>
          <p class="text-slate-600 text-xs">© 2024 CryptoDuel. 18+ only.</p>
        </div>
      </footer>
    </main>
  </div>

  <!-- ─── LIVE CHAT ─── -->
  <aside class="chat-bar fixed right-0 top-0 h-full bg-slate-900 border-l border-slate-800 flex flex-col z-40">
    <!-- Chat Header -->
    <div class="p-4 border-b border-slate-800">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
          <h3 class="text-white font-bold text-sm">Live Chat</h3>
        </div>
        <span class="text-slate-400 text-xs bg-slate-800 px-2 py-0.5 rounded-full">2,847 online</span>
      </div>
      <!-- Chat Room Tabs -->
      <div class="flex bg-slate-800 rounded-lg p-0.5 text-xs">
        <button class="flex-1 py-1.5 rounded-md bg-purple-600 text-white font-semibold transition-all">English</button>
        <button class="flex-1 py-1.5 text-slate-400 hover:text-white transition-all">Global</button>
        <button class="flex-1 py-1.5 text-slate-400 hover:text-white transition-all">VIP</button>
      </div>
    </div>

    <!-- Chat Messages -->
    <div class="chat-messages flex-1 p-3 space-y-2.5 overflow-y-auto" id="chatMessages">
      <?php foreach($chatMessages as $msg): ?>
      <?php
      $cHexes=['yellow'=>'#eab308','cyan'=>'#22d3ee','purple'=>'#a855f7','pink'=>'#ec4899','green'=>'#22c55e','orange'=>'#f97316','red'=>'#ef4444','blue'=>'#3b82f6'];
      $ch = $cHexes[$msg['color']] ?? '#a855f7';
      ?>
      <?php if(!empty($msg['rain'])): ?>
      <div class="bg-blue-500/10 border border-blue-500/30 rounded-xl p-2.5 text-center">
        <div class="text-blue-400 text-xs font-bold mb-0.5">🌧️ RAIN EVENT</div>
        <p class="text-white text-xs font-semibold"><?php echo $msg['user']; ?> rained 0.0001 BTC on 10 users!</p>
      </div>
      <?php else: ?>
      <div class="flex items-start gap-2 group">
        <div class="w-6 h-6 rounded-lg flex items-center justify-center font-bold text-white text-xs shrink-0 mt-0.5" style="background:<?php echo $ch; ?>30; color:<?php echo $ch; ?>">
          <?php echo strtoupper(substr($msg['user'],0,1)); ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span class="font-bold text-xs" style="color:<?php echo $ch; ?>"><?php echo $msg['user']; ?></span>
            <span class="level-badge bg-slate-800 text-slate-400"><?php echo $msg['level']; ?></span>
            <span class="text-slate-600 text-xs ml-auto"><?php echo $msg['time']; ?></span>
          </div>
          <p class="text-slate-300 text-xs leading-relaxed"><?php echo htmlspecialchars($msg['msg']); ?></p>
        </div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>

      <!-- Animated "typing" indicator -->
      <div class="flex items-center gap-2 opacity-60">
        <div class="w-6 h-6 rounded-lg bg-purple-500/20 flex items-center justify-center">
          <div class="flex gap-0.5">
            <div class="w-1 h-1 bg-purple-400 rounded-full animate-bounce" style="animation-delay:0s"></div>
            <div class="w-1 h-1 bg-purple-400 rounded-full animate-bounce" style="animation-delay:0.15s"></div>
            <div class="w-1 h-1 bg-purple-400 rounded-full animate-bounce" style="animation-delay:0.3s"></div>
          </div>
        </div>
        <span class="text-slate-500 text-xs">Someone is typing...</span>
      </div>
    </div>

    <!-- Chat Input -->
    <div class="p-3 border-t border-slate-800">
      <!-- Rain Button -->
      <button class="w-full flex items-center justify-center gap-2 py-2 mb-2 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 hover:border-blue-500/50 rounded-xl text-blue-400 font-semibold text-xs transition-all">
        <span>🌧️</span> Send Rain
      </button>
      <!-- Input Row -->
      <div class="flex gap-2">
        <div class="flex-1 relative">
          <input type="text" placeholder="Type a message..." maxlength="140"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition-all pr-8"/>
          <button class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-yellow-400 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
          </button>
        </div>
        <button class="px-3 py-2.5 gradient-purple rounded-xl text-white transition-all hover:opacity-90">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
      </div>
      <p class="text-center text-slate-600 text-xs mt-2">Sign in to chat</p>
    </div>
  </aside>
</div>

<!-- ─── MOBILE NAV BAR ─── -->
<nav class="mobile-nav fixed bottom-0 left-0 right-0 bg-slate-900 border-t border-slate-800 z-50 px-2 py-2">
  <div class="flex items-center justify-around">
    <?php
    $mobileNav=[
      ['icon'=>'<circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/>','label'=>'Coinflip','active'=>true],
      ['icon'=>'<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>','label'=>'Jackpot','active'=>false],
      ['icon'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>','label'=>'Duels','active'=>false],
      ['icon'=>'<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>','label'=>'Market','active'=>false],
      ['icon'=>'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>','label'=>'Chat','active'=>false],
    ];
    foreach($mobileNav as $mn):
    ?>
    <a href="#" class="flex flex-col items-center gap-1 px-3 py-1 rounded-xl transition-all <?php echo $mn['active'] ? 'text-purple-400' : 'text-slate-500'; ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?php echo $mn['icon']; ?></svg>
      <span class="text-xs font-medium"><?php echo $mn['label']; ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</nav>

<!-- Mobile Sidebar Overlay -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="toggleMobileMenu()"></div>
<div class="mobile-sidebar" id="mobileSidebar">
  <div class="flex items-center gap-3 px-4 mb-6">
    <div class="w-8 h-8 rounded-xl gradient-purple flex items-center justify-center neon-glow">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
    </div>
    <span class="text-white font-black text-lg">Crypto<span class="text-purple-400">Duel</span></span>
  </div>
  <nav class="px-3 space-y-1">
    <?php foreach($navItems as $item): ?>
    <a href="#" class="flex items-center gap-3 px-3 py-3 rounded-xl transition-all <?php echo $item['active'] ? 'bg-purple-600/20 text-purple-400' : 'text-slate-400 hover:text-white hover:bg-slate-800'; ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $item['icon']; ?></svg>
      <span class="text-sm font-medium"><?php echo $item['label']; ?></span>
      <?php if($item['badge']): ?>
      <span class="ml-auto level-badge <?php echo $item['badge']==='LIVE' ? 'bg-red-500/20 text-red-400' : ($item['badge']==='HOT' ? 'bg-orange-500/20 text-orange-400' : 'bg-purple-500/20 text-purple-400'); ?>"><?php echo $item['badge']; ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>
</div>

<script>
// ─── MODAL CONTROLS ───
function openModal(id) {
  document.getElementById(id).classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('active');
  document.body.style.overflow = '';
}
document.getElementById('walletModal').addEventListener('click', function(e) {
  if(e.target === this) closeModal('walletModal');
});

// ─── WALLET TABS ───
function switchTab(tab) {
  ['deposit','withdraw','history'].forEach(t => {
    document.getElementById('panel-'+t).classList.add('hidden');
    const btn = document.getElementById('tab-'+t);
    btn.classList.remove('text-purple-400','border-purple-500');
    btn.classList.add('text-slate-400','border-transparent');
  });
  document.getElementById('panel-'+tab).classList.remove('hidden');
  const active = document.getElementById('tab-'+tab);
  active.classList.remove('text-slate-400','border-transparent');
  active.classList.add('text-purple-400','border-purple-500');
}

// ─── COPY ADDRESS ───
function copyAddress() {
  const addr = document.getElementById('btcAddress').textContent.trim();
  navigator.clipboard.writeText(addr).then(() => {
    const btn = event.currentTarget;
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
    setTimeout(() => {
      btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    }, 2000);
  });
}

// ─── FILTER BETS ───
function filterBets(btn, game) {
  document.querySelectorAll('.bet-filter').forEach(b => {
    b.classList.remove('bg-purple-600','text-white');
    b.classList.add('text-slate-400');
  });
  btn.classList.add('bg-purple-600','text-white');
  btn.classList.remove('text-slate-400');

  document.querySelectorAll('.bet-card').forEach(card => {
    if(game === 'All' || card.dataset.game === game) {
      card.style.display = '';
      card.style.animation = 'none';
      requestAnimationFrame(() => { card.style.animation = ''; });
    } else {
      card.style.display = 'none';
    }
  });
}

// ─── JOIN BET ───
function joinBet(id) {
  const notification = document.createElement('div');
  notification.className = 'fixed top-20 right-4 z-50 bg-purple-600 text-white px-5 py-3 rounded-xl shadow-lg font-semibold text-sm flex items-center gap-2 animate-slide-in';
  notification.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
    Joining bet #${id}... Confirm in wallet
  `;
  document.body.appendChild(notification);
  setTimeout(() => notification.remove(), 3500);
}

// ─── MOBILE MENU ───
function toggleMobileMenu() {
  document.getElementById('mobileSidebar').classList.toggle('active');
  document.getElementById('mobileMenuOverlay').classList.toggle('active');
}

// ─── LIVE PRICE UPDATES ───
(function() {
  const prices = {
    'BTC': <?php echo $btcPrice; ?>,
    'ETH': 3421.55,
    'SOL': 182.34
  };
  setInterval(() => {
    Object.keys(prices).forEach(sym => {
      prices[sym] *= (1 + (Math.random() - 0.5) * 0.0008);
    });
  }, 3000);
})();

// ─── LIVE CHAT SCROLL ───
const chatEl = document.getElementById('chatMessages');
if(chatEl) chatEl.scrollTop = chatEl.scrollHeight;

// ─── SIMULATE LIVE BET ARRIVAL ───
setTimeout(() => {
  const grid = document.getElementById('betGrid');
  const newCard = document.createElement('div');
  newCard.className = 'bet-card bg-slate-800 rounded-2xl overflow-hidden border border-green-500/50 card-hover accent-green group';
  newCard.dataset.game = 'Coinflip';
  newCard.style.animation = 'bounceIn 0.5s ease-out';
  newCard.innerHTML = `
    <div class="p-4">
      <div class="flex items-center gap-2 mb-3">
        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
        <span class="text-green-400 text-xs font-bold">NEW</span>
      </div>
      <div class="flex items-center gap-3 mb-3">
        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-green-600 to-green-800 flex items-center justify-center font-black text-white text-sm">LM</div>
        <div>
          <p class="text-white font-bold text-sm">LuckyMoon</p>
          <p class="text-slate-500 text-xs">Just now</p>
        </div>
        <div class="ml-auto px-2.5 py-1 bg-purple-500/10 border border-purple-500/30 rounded-lg">
          <span class="text-xs font-bold text-purple-400">Coinflip</span>
        </div>
      </div>
      <div class="bg-slate-900 rounded-xl p-3 mb-3">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-400 text-xs mb-1">Bet Amount</p>
            <p class="text-white font-black text-xl">0.0075 <span class="text-orange-400 text-sm font-bold">BTC</span></p>
          </div>
          <div class="text-right">
            <p class="text-slate-400 text-xs mb-1">Win</p>
            <p class="text-green-400 font-black text-xl">0.0150</p>
          </div>
        </div>
      </div>
    </div>
    <div class="px-4 pb-4 flex gap-2">
      <button onclick="joinBet(99)" class="flex-1 py-2.5 gradient-purple rounded-xl text-white font-bold text-sm flex items-center justify-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg> Join
      </button>
    </div>
  `;
  grid.insertBefore(newCard, grid.children[3]);
}, 8000);
</script>
</body>
</html>
