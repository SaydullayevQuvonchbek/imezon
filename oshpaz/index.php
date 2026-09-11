<?php
// oshpaz/index.php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['oshpaz', 'admin']);

$filial_id   = (int)($_SESSION['im_filial_id'] ?? 0);
$oshpaz_id   = (int)($_SESSION['im_user_id']   ?? 0);
$filial_nomi = 'Oshxona';
if ($filial_id) {
    $db = new Cyber();
    $filial_nomi = (string)$db->val("SELECT nomi FROM im_filiallar WHERE id=$filial_id") ?: 'Oshxona';
}
?>
<!DOCTYPE html>
<html lang="uz" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Oshpaz — IMezon</title>
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/bi.min.css">
<link rel="icon" href="data:,">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f172a;--card:#1e293b;--card2:#273347;
  --border:#334155;--text:#f1f5f9;--muted:#94a3b8;
  --green:#22c55e;--green-d:#16a34a;
  --blue:#3b82f6;--blue-d:#2563eb;
  --orange:#f97316;--red:#ef4444;
  --accent:#e2b96f;--accent-d:#c9a058;
  --success:var(--green);--danger:var(--red);--warning:var(--orange);
  --info:var(--blue);--primary:var(--blue-d);
}
[data-theme="light"]{
  --bg:#f0f2f5;--card:#fff;--card2:#f8f9fa;
  --border:#e2e8f0;--text:#1e1e2e;--muted:#6c757d;
}
html,body{min-height:100%;background:var(--bg);color:var(--text);font-family:'Inter',sans-serif}

/* Topbar */
.topbar{
  background:#0f1729;border-bottom:2px solid var(--border);
  padding:0 16px;height:56px;display:flex;align-items:center;gap:12px;
  position:sticky;top:0;z-index:100;
}
.topbar-logo{font-size:18px;font-weight:900;color:var(--text)}
.topbar-logo span{color:var(--accent)}
.topbar-filial{
  background:var(--blue-d);color:#fff;border-radius:7px;
  padding:4px 11px;font-size:12px;font-weight:700;
  display:flex;align-items:center;gap:5px;
}
.ms-auto{margin-left:auto}
.clock{font-size:18px;font-weight:700;font-family:monospace;color:var(--accent)}
.theme-btn,.topbar-logout{
  background:rgba(255,255,255,.06);border:1px solid var(--border);
  color:var(--muted);border-radius:7px;padding:6px 10px;
  font-size:14px;cursor:pointer;transition:.15s;text-decoration:none;
  display:flex;align-items:center;gap:5px;
}
.theme-btn:hover,.topbar-logout:hover{color:var(--text);border-color:var(--accent)}

/* Grids */
.grid{
  display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));
  gap:14px;padding:16px;
}
.empty-state{
  grid-column:1/-1;text-align:center;padding:80px 20px;color:var(--muted);
}
.empty-state i{font-size:64px;opacity:.12;display:block;margin-bottom:14px}
.empty-state h3{font-size:16px;opacity:.5}

/* ── IKKIGA BO'LINGAN ISH EKRANI ────────────────────────────
   Oshpaz "Navbatdagi" va "Pishirilmoqda" ni bir vaqtda ko'radi —
   tab almashtirish kerak emas. Har bir ustun mustaqil scroll
   bo'ladi, shuning uchun biri uzun bo'lsa ikkinchisi qochmaydi. */
.split{
  display:flex;gap:0;align-items:stretch;
  height:calc(100vh - 56px - 53px);   /* topbar + panel sarlavhalari */
}
.split-col{
  flex:1 1 50%;min-width:0;display:flex;flex-direction:column;overflow:hidden;
}
.split-col + .split-col{border-left:2px solid var(--border)}
.col-head{
  display:flex;align-items:center;gap:8px;flex-shrink:0;
  padding:11px 16px;font-size:13px;font-weight:800;letter-spacing:.3px;
  border-bottom:1px solid var(--border);background:rgba(0,0,0,.22);
}
.col-head.navbat{color:#fb923c}
.col-head.pishir{color:#38bdf8}
.col-head .cnt{
  background:rgba(255,255,255,.14);border-radius:20px;
  padding:1px 8px;font-size:11px;font-weight:800;color:var(--text);
}
.col-body{flex:1;overflow-y:auto;min-height:0}
.col-body .grid{grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
.col-body .empty-state{padding:50px 16px}
.col-body .empty-state i{font-size:44px}

@media (max-width: 900px){
  .split{flex-direction:column;height:auto}
  .split-col{flex:none}
  .split-col + .split-col{border-left:none;border-top:2px solid var(--border)}
  .col-body{overflow:visible}
  .col-head{position:sticky;top:56px;z-index:98}
}

/* Qozon / zagotovka eslatmasi */
#eslatma-panel{padding:10px 16px 0;display:flex;flex-direction:column;gap:8px}
#eslatma-panel[hidden]{display:none}
.eslatma{
  border-radius:10px;padding:10px 14px;font-size:13.5px;font-weight:700;
  display:flex;align-items:flex-start;gap:9px;line-height:1.4;
  border:1px solid transparent;
}
.eslatma i{font-size:17px;flex-shrink:0;margin-top:1px}
.eslatma.xato{background:rgba(239,68,68,.14);border-color:rgba(239,68,68,.5);color:#fecaca}
.eslatma.ogoh{background:rgba(249,115,22,.14);border-color:rgba(249,115,22,.5);color:#fed7aa}
.eslatma.info{background:rgba(59,130,246,.12);border-color:rgba(59,130,246,.4);color:#bfdbfe}
.eslatma a{color:inherit;text-decoration:underline;white-space:nowrap;margin-left:auto;font-weight:800}

/* Order Card */
.order-card{
  background:var(--card);border:2px solid var(--border);border-radius:14px;
  display:flex;flex-direction:column;overflow:hidden;
}
.order-card.new-pulse{animation:pulseGlow 1.8s infinite}
@keyframes pulseGlow{
  0%  {border-color:var(--blue);box-shadow:0 0 0 0 rgba(59,130,246,.6)}
  65% {border-color:var(--blue-d);box-shadow:0 0 0 10px rgba(59,130,246,0)}
  100%{border-color:var(--blue);box-shadow:0 0 0 0 rgba(59,130,246,0)}
}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

/* Card head */
.card-head{
  background:var(--blue-d);padding:12px 15px;
  display:flex;justify-content:space-between;align-items:center;
}
.card-head .stol{font-size:20px;font-weight:900;color:#fff;display:flex;align-items:center;gap:8px}
.card-head .meta-right{text-align:right}
.card-head .time{font-size:11px;color:rgba(255,255,255,.8)}
.card-head .sotuv-no{font-size:12px;font-weight:700;color:#bfdbfe;margin-bottom:2px}

.card-seller{
  padding:7px 14px;background:var(--card2);border-bottom:1px solid var(--border);
  font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px;
}
.card-seller b{color:var(--text)}
.card-izoh{
  padding:7px 14px;background:#2d1a02;border-bottom:1px solid #4a2a04;
  font-size:12px;color:#fb923c;display:flex;align-items:center;gap:5px;
}
.card-olib-ketish{
  padding:7px 14px;background:#fb923c;color:#1a1a2e;
  font-size:12px;font-weight:800;display:flex;align-items:center;gap:6px;
  letter-spacing:.3px;
}

/* Items */
.card-items{flex:1;padding:8px 14px}
.item-row{
  display:flex;align-items:center;justify-content:space-between;
  padding:8px 0;border-bottom:1px dashed var(--border);
}
.item-row:last-child{border-bottom:none}
.item-name{font-size:14px;font-weight:700;color:var(--text);line-height:1.2}
.item-tag{
  display:inline-block;font-size:9px;font-weight:700;
  background:#7c3aed;color:#fff;border-radius:4px;
  padding:1px 5px;margin-left:4px;vertical-align:middle;
}
/* Qator darajasidagi "saboyga" belgisi — aynan shu porsiya qadoqlanadi */
.item-ok{
  display:inline-block;font-size:9px;font-weight:800;
  background:#f97316;color:#fff;border-radius:4px;
  padding:1px 5px;margin-left:4px;vertical-align:middle;letter-spacing:.3px;
}
.item-qty{
  font-size:22px;font-weight:900;color:var(--orange);
  background:rgba(249,115,22,.12);border-radius:8px;
  padding:2px 12px;min-width:52px;text-align:center;flex-shrink:0;
}

/* Card footer — 2 tugma */
.card-foot{
  padding:12px 14px;border-top:1px solid var(--border);background:var(--card);
  display:flex;gap:8px;
}
.btn-print-ck{
  flex:1;height:48px;border:2px solid var(--blue);border-radius:10px;
  background:rgba(59,130,246,.1);color:var(--blue);
  font-size:13px;font-weight:700;font-family:inherit;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;
  transition:.15s;
}
.btn-print-ck:hover{background:var(--blue-d);color:#fff;border-color:var(--blue-d)}
/* Qayta o'qitish — eshitilmay qolganda bosiladi */
.btn-ovoz{
  width:48px;height:48px;flex:0 0 48px;
  border:2px solid var(--accent);border-radius:10px;
  background:rgba(226,185,111,.12);color:var(--accent);
  font-size:18px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:.15s;
}
.btn-ovoz:hover{background:var(--accent);color:#111}
.btn-ovoz.gapiryapti{
  background:var(--accent);color:#111;
  animation:ovozPulse 1s ease-in-out infinite;
}
@keyframes ovozPulse{0%,100%{opacity:1}50%{opacity:.55}}
.theme-btn.ovoz-ochiq{opacity:.45;text-decoration:line-through}
.btn-done{
  flex:1;height:48px;border:none;border-radius:10px;
  background:linear-gradient(135deg,var(--green),var(--green-d));
  color:#fff;font-size:14px;font-weight:800;font-family:inherit;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;
  box-shadow:0 4px 14px rgba(34,197,94,.3);transition:.15s;
}
.btn-done:hover{filter:brightness(1.08);transform:translateY(-1px)}
.btn-done:active{transform:translateY(0)}
.btn-done:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}

/* History Card */
.hist-card{
  background:var(--card);border:2px solid var(--border);border-left:5px solid var(--green);
  border-radius:14px;overflow:hidden;animation:fadeUp .3s ease;
}
.hist-head{
  background:var(--green-d);padding:11px 15px;
  display:flex;justify-content:space-between;align-items:center;
}
.hist-head .stol{font-size:15px;font-weight:800;color:#fff}
.hist-head .meta{text-align:right}
.hist-head .time{font-size:11px;color:rgba(255,255,255,.8)}
.hist-head .sotuv-no{font-size:11px;font-weight:700;color:#bbf7d0}
.hist-seller{
  padding:6px 14px;background:var(--card2);border-bottom:1px solid var(--border);
  font-size:11px;color:var(--muted);
}
.hist-seller b{color:var(--text)}
.hist-items{padding:8px 14px}
.hist-item{
  display:flex;justify-content:space-between;align-items:center;
  padding:5px 0;border-bottom:1px dashed var(--border);
  font-size:13px;
}
.hist-item:last-child{border-bottom:none}
.hist-item .name{color:var(--text)}
.hist-item .qty{color:var(--green);font-weight:700}
.hist-foot{
  padding:8px 14px;border-top:1px solid var(--border);
  display:flex;justify-content:space-between;align-items:center;
  font-size:12px;
}
.hist-foot .summa{font-weight:800;color:var(--accent-d);font-size:14px}

/* NHConfirm o'zining assets/css/confirm.css stilini mustaqil yuklaydi. */
#im-toast-container{
  position:fixed;top:72px;right:18px;z-index:9999;display:flex;flex-direction:column;
  gap:8px;pointer-events:none;
}
.im-toast{
  min-width:280px;max-width:min(440px,calc(100vw - 36px));padding:13px 16px;
  display:flex;align-items:flex-start;gap:10px;background:var(--card);color:var(--text);
  border:1px solid var(--border);border-left:5px solid;border-radius:11px;
  box-shadow:0 14px 38px rgba(0,0,0,.38);font-size:13px;font-weight:700;
  line-height:1.45;pointer-events:auto;animation:oshToastIn .25s ease;
}
.im-toast.success{border-left-color:var(--green)}
.im-toast.error{border-left-color:var(--red)}
.im-toast.warning{border-left-color:var(--orange)}
.im-toast.info{border-left-color:var(--blue)}
.im-toast-icon{font-size:18px;flex-shrink:0;margin-top:1px}
.im-toast-msg{flex:1;color:var(--text)}
.im-toast-out{animation:oshToastOut .25s ease forwards}
@keyframes oshToastIn{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:none}}
@keyframes oshToastOut{to{opacity:0;transform:translateX(30px)}}
@media(max-width:520px){
  #im-toast-container{left:12px;right:12px;top:66px}
  .im-toast{min-width:0;max-width:none;width:100%}
}
</style>
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/oshpaz.css?v=2">
</head>
<body>

<div class="topbar">
  <div class="brand-block">
    <div class="brand-mark" aria-hidden="true"><i class="bi bi-fire"></i></div>
    <div class="brand-copy">
      <div class="topbar-logo"><span>OSHXONA</span></div>
      <div class="brand-subtitle">Ish boshqaruvi</div>
    </div>
  </div>
  <div class="topbar-filial" title="<?= im_f($filial_nomi) ?>"><i class="bi bi-geo-alt-fill"></i> <?= im_f($filial_nomi) ?></div>
  <div class="live-status" id="live-status" aria-live="polite">Jonli</div>
  <span class="ms-auto"></span>
  <div class="clock-wrap"><div class="clock" id="clock">00:00:00</div><div class="clock-caption">Mahalliy vaqt</div></div>
  <nav class="topbar-actions" aria-label="Oshxona amallari">
    <a href="<?= im_BASE ?>qayta-ishlash/yangi.php?tur=maydalash" class="topbar-logout" title="Butun mahsulotni alohida qismlarga maydalash">
      <i class="bi bi-scissors"></i> <span class="action-label">Maydalash</span>
    </a>
    <a href="<?= im_BASE ?>oshpaz/qozon.php" class="topbar-logout" title="Osh qozoni — kunlik hisob">
      <i class="bi bi-fire"></i> <span class="action-label">Qozon</span>
    </a>
    <button class="theme-btn" id="tarix-btn" onclick="toggleTarix()" title="Bugun tayyorlanganlar" aria-label="Tarixni ochish">
      <i class="bi bi-check2-all"></i> <span class="action-label" id="tarix-btn-text">Tarix</span>
    </button>
    <button class="theme-btn icon-only" id="ovoz-btn" onclick="toggleOvoz()" title="Ovozli o'qish" aria-label="Ovozli o'qishni boshqarish"><i class="bi bi-volume-up-fill"></i></button>
    <button class="theme-btn icon-only" id="theme-btn" onclick="toggleTheme()" title="Rang mavzusi" aria-label="Rang mavzusini almashtirish"><i class="bi bi-sun-fill"></i></button>
    <a href="<?= im_BASE ?>logout.php" class="topbar-logout logout-action" title="Tizimdan chiqish"><i class="bi bi-box-arrow-right"></i> <span class="action-label">Chiqish</span></a>
  </nav>
</div>

<!-- ═══ QOZON / ZAGOTOVKA ESLATMASI ═══
     Qozon rejimidagi mahsulot (osh, shashlik) sotilyapti-yu, bugun
     ochiq partiya yo'q / mo'ljal tugadi bo'lsa shu yerda chiqadi. -->
<div id="eslatma-panel" hidden></div>

<!-- ═══ ISH EKRANI — ikkiga bo'lingan (tab almashtirish yo'q) ═══ -->
<div id="ish-view">
  <div class="split">
    <section class="split-col queue-lane" aria-labelledby="queue-title">
      <div class="col-head navbat">
        <span class="lane-icon"><i class="bi bi-inbox-fill"></i></span>
        <span><span class="lane-title" id="queue-title">Navbatdagi</span><span class="lane-subtitle">Qabul qilinmagan buyurtmalar</span></span>
        <span class="cnt" id="cnt-orders">0</span>
      </div>
      <div class="col-body"><div class="grid" id="grid-orders" aria-live="polite"></div></div>
    </section>
    <section class="split-col cooking-lane" aria-labelledby="cooking-title">
      <div class="col-head pishir">
        <span class="lane-icon"><i class="bi bi-hourglass-split"></i></span>
        <span><span class="lane-title" id="cooking-title">Pishirilmoqda</span><span class="lane-subtitle">Jarayondagi buyurtmalar</span></span>
        <span class="cnt" id="cnt-cooking">0</span>
      </div>
      <div class="col-body"><div class="grid" id="grid-cooking" aria-live="polite"></div></div>
    </section>
  </div>
</div>

<!-- ═══ TARIX (alohida ko'rinish) ═══ -->
<div id="tarix-view" style="display:none">
  <div class="col-head history-head">
    <span class="lane-icon"><i class="bi bi-check2-all"></i></span>
    <span><span class="lane-title">Bugun tayyorlanganlar</span><span class="lane-subtitle">Yakunlangan buyurtmalar tarixi</span></span>
    <span class="cnt" id="cnt-history">0</span>
  </div>
  <div class="grid" id="grid-history"></div>
</div>

<!-- Popup oyna orqali chek chiqariladi, iframe kerak emas -->

<audio id="beep" src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YVoGAAAA/v8CAAYA/v/6/wIA/v8EAAQAAAD6//r/AgAGAAAA/v8CAAIAAQD+/wQAAAACAAIA/v8AAAQAAQAAAAIACAACAAAA/v8CAAIA/v8AAAIA/v8CAAQAAAD8/wQABAACAAAAAQD6/wYABgAAAAIA/v8CAAIAAQD+/wIABAAAAAAAAP7/BAAEAP7//v8CAAIA/v8CAAIA/v8AAAQAAAD+/wIA" preload="auto"></audio>

<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js?v=<?= rawurlencode(im_VERSION) ?>"></script>
<script>
'use strict';
const IM_BASE    = '<?= im_BASE ?>';
// im_js: <script> ichida ham to'g'ri. im_f bu yerda noto'g'ri edi —
// skript bloki ichida HTML entity dekodlanmaydi, ya'ni apostrofli
// filial nomi ekranda "&#039;" bo'lib ko'rinardi.
const FILIAL_NOM = '<?= im_js($filial_nomi) ?>';

let _prevIds    = new Set();
let _firstLoad  = true;
let _allOrders  = [];
let _orderMap   = {};   // id → order object (chek uchun)

// ── Soat ─────────────────────────────────────────────────
function tick(){
  const t=new Date();
  document.getElementById('clock').textContent =
    String(t.getHours()).padStart(2,'0')+':'+
    String(t.getMinutes()).padStart(2,'0')+':'+
    String(t.getSeconds()).padStart(2,'0');
}
setInterval(tick,1000); tick();

// ── Tema ─────────────────────────────────────────────────
function applyTheme(dark){
  document.documentElement.setAttribute('data-theme', dark?'dark':'light');
  const b=document.getElementById('theme-btn');
  if(b) {
    b.innerHTML = dark ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-stars-fill"></i>';
    b.title = dark ? 'Yorug\' mavzuga o\'tish' : 'Tungi mavzuga o\'tish';
  }
}
function toggleTheme(){
  const isDark = document.documentElement.getAttribute('data-theme')!=='light';
  localStorage.setItem('oshpaz_theme', isDark?'light':'dark');
  applyTheme(!isDark);
}
(function(){
  const s=localStorage.getItem('oshpaz_theme')||'dark';
  applyTheme(s==='dark');
})();
// main.js dagi NHTheme (im_theme kaliti) DOMContentLoaded'da data-theme'ni
// bosib ketadi. Oshpaz o'z tizimini (oshpaz_theme, default 'dark') ishlatadi —
// shu listener keyin ro'yxatdan o'tgani uchun oxirida ishlaydi va temani tiklaydi.
document.addEventListener('DOMContentLoaded', function(){
  applyTheme((localStorage.getItem('oshpaz_theme')||'dark')==='dark');
});

// ── Ish ekrani / Tarix almashtirish ──────────────────────
// "Navbatdagi" va "Pishirilmoqda" bir ekranda yonma-yon turadi —
// ular orasida almashtirish kerak emas. Faqat tarix alohida.
let tarixOchiq = false;
function toggleTarix(){
  tarixOchiq = !tarixOchiq;
  document.getElementById('ish-view').style.display   = tarixOchiq ? 'none' : '';
  document.getElementById('tarix-view').style.display = tarixOchiq ? '' : 'none';
  document.getElementById('tarix-btn-text').textContent = tarixOchiq ? 'Oshxona' : 'Tarix';
  const b = document.getElementById('tarix-btn');
  if (b) {
    b.classList.toggle('is-active', tarixOchiq);
    b.setAttribute('aria-label', tarixOchiq ? 'Oshxona ish ekranini ochish' : 'Tarixni ochish');
  }
}

// ── Qo'ng'iroq ───────────────────────────────────────────
// Yangi buyurtma kelganda jiringlaydi. WebAudio ishlatamiz —
// tashqi fayl kerak emas va ovozi baland/aniq chiqadi. Brauzer
// foydalanuvchi bilan muloqotgacha ovozni bloklagani uchun
// birinchi bosishda "ochib" qo'yamiz.
let _actx = null;
function unlockAudio(){
  if (_actx) return;
  try { _actx = new (window.AudioContext||window.webkitAudioContext)(); } catch {}
}

// MP3 (<audio>) va speechSynthesis WebAudio'dan ALOHIDA bloklanadi —
// shuning uchun birinchi bosishda uchalasini ham "ochib" qo'yamiz.
// Aks holda planshet ertalab yoqilib, hech kim ekranga tegmasa,
// buyurtma kelganda ovoz chiqmay qoladi.
let _ovozOchildi = false;
function unlockAll(){
  unlockAudio();
  if (_ovozOchildi) return;
  _ovozOchildi = true;
  try {
    const b = document.getElementById('beep');
    if (b) { b.muted = true; b.play().then(()=>{ b.pause(); b.currentTime=0; b.muted=false; })
                              .catch(()=>{ b.muted = false; }); }
  } catch {}
  try {
    if ('speechSynthesis' in window) {
      speechSynthesis.speak(new SpeechSynthesisUtterance(' '));
      speechSynthesis.cancel();
    }
  } catch {}
}
document.addEventListener('click',   unlockAll, {once:true});
document.addEventListener('keydown', unlockAll, {once:true});
document.addEventListener('touchstart', unlockAll, {once:true});

function ringBell(times){
  times = times || 2;
  unlockAudio();
  if (!_actx) { document.getElementById('beep')?.play().catch(()=>{}); return; }
  if (_actx.state === 'suspended') _actx.resume().catch(()=>{});
  for (let n = 0; n < times; n++) {
    const t0 = _actx.currentTime + n * 0.42;
    // Ikkita ohang (jiringlash tuyg'usi uchun)
    [988, 1319].forEach((freq, k) => {
      const osc = _actx.createOscillator();
      const gn  = _actx.createGain();
      osc.type = 'sine';
      osc.frequency.value = freq;
      gn.gain.setValueAtTime(0.0001, t0);
      gn.gain.exponentialRampToValueAtTime(k ? 0.25 : 0.35, t0 + 0.01);
      gn.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.35);
      osc.connect(gn); gn.connect(_actx.destination);
      osc.start(t0); osc.stop(t0 + 0.36);
    });
  }
}

// ══ OVOZLI O'QISH ════════════════════════════════════════
// Yangi buyurtma kelganda: avval jiringlash, keyin ovoz.
// Ovoz serverdan (oshpaz/ajax/tts.php) MP3 bo'lib keladi —
// matn serverda yasaladi, API kaliti brauzerga chiqmaydi.
// Server 204 qaytarsa (TTS o'chirilgan / internet uzilgan)
// brauzerning o'z speechSynthesis'iga tushamiz — oshxona
// hech qachon butunlay ovozsiz qolmaydi.

let _ovozYoniq = localStorage.getItem('oshpaz_ovoz') !== '0';

function applyOvozBtn(){
  const b = document.getElementById('ovoz-btn');
  if (!b) return;
  b.innerHTML = _ovozYoniq ? '<i class="bi bi-volume-up-fill"></i>' : '<i class="bi bi-volume-mute-fill"></i>';
  b.classList.toggle('ovoz-ochiq', !_ovozYoniq);
  b.classList.toggle('is-active', _ovozYoniq);
  b.title = _ovozYoniq ? 'Ovozli o\'qish yoqilgan' : 'Ovozli o\'qish o\'chirilgan';
}
function toggleOvoz(){
  _ovozYoniq = !_ovozYoniq;
  localStorage.setItem('oshpaz_ovoz', _ovozYoniq ? '1' : '0');
  if (!_ovozYoniq) ttsToxtat();
  applyOvozBtn();
}
applyOvozBtn();

// ── Navbat ───────────────────────────────────────────────
// Bir vaqtda 3 ta buyurtma kelsa ustma-ust gapirmasin —
// birma-bir, orasida kichik tanaffus bilan o'qiladi.
const _ttsQ   = [];
let _ttsBand  = false;
let _ttsAudio = null;
let _ttsHozir = null;   // ayni damda o'qilayotgan buyurtma

function speakOrder(id, majburiy){
  if (!majburiy && !_ovozYoniq) return;
  // Takror navbatga qo'ymaymiz — navbatdagisi ham, hozir
  // o'qilayotgani ham hisobga olinadi.
  if (!majburiy && (id === _ttsHozir || _ttsQ.indexOf(id) !== -1)) return;
  _ttsQ.push(id);
  ttsNavbat();
}

function ttsToxtat(){
  _ttsQ.length = 0;
  if (_ttsAudio) { try { _ttsAudio.pause(); } catch {} _ttsAudio = null; }
  if ('speechSynthesis' in window) { try { speechSynthesis.cancel(); } catch {} }
  document.querySelectorAll('.btn-ovoz.gapiryapti')
          .forEach(b => b.classList.remove('gapiryapti'));
  _ttsHozir = null;
  _ttsBand  = false;
}

async function ttsNavbat(){
  if (_ttsBand || !_ttsQ.length) return;
  _ttsBand  = true;
  const id  = _ttsQ.shift();
  _ttsHozir = id;
  const btn = document.getElementById('ovz-' + id);
  if (btn) btn.classList.add('gapiryapti');
  try { await ttsAyt(id); } catch {}
  if (btn) btn.classList.remove('gapiryapti');
  _ttsHozir = null;
  _ttsBand  = false;
  // Buyurtmalar orasidagi kichik tanaffus — quloq ajrata olsin
  if (_ttsQ.length) setTimeout(ttsNavbat, 300);
}

// ── Bitta buyurtmani o'qish ──────────────────────────────
function ttsAyt(id){
  return new Promise(resolve => {
    let tugadi = false;
    const bitir = () => { if (!tugadi) { tugadi = true; resolve(); } };
    // Audio "osilib" qolsa navbat abadiy to'xtab qolmasin.
    // Osilgan ovozni ham to'xtatamiz — keyingisi ustiga chiqmasin.
    const qorovul = setTimeout(() => {
      if (_ttsAudio) { try { _ttsAudio.pause(); } catch {} _ttsAudio = null; }
      bitir();
    }, 30000);
    const yakun = () => { clearTimeout(qorovul); bitir(); };

    fetch(IM_BASE + 'oshpaz/ajax/tts.php?id=' + id, {cache:'no-store'})
      .then(res => (res.status === 200 ? res.blob() : null))
      .then(blob => {
        if (!blob || blob.size < 512) { ttsBrauzer(id, yakun); return; }

        const url = URL.createObjectURL(blob);
        const a   = new Audio(url);
        _ttsAudio = a;
        let boshlandi = false;

        // MUHIM: hodisalarni uzib qo'yamiz. Blob URL bekor qilinganda
        // ba'zi brauzerlar 'error' otadi — onerror ulangan qolsa,
        // buyurtma allaqachon o'qilgan bo'lsa ham brauzer zaxirasi
        // ishga tushib, IKKINCHI MARTA o'qilardi.
        const tozala = () => {
          a.onplaying = a.onended = a.onerror = null;
          URL.revokeObjectURL(url);
          if (_ttsAudio === a) _ttsAudio = null;
        };

        a.onplaying = () => { boshlandi = true; };
        a.onended   = () => { tozala(); yakun(); };
        a.onerror   = () => {
          const chalindi = boshlandi;
          tozala();
          // Ovoz chiqib bo'lgan bo'lsa takrorlamaymiz
          if (chalindi) yakun(); else ttsBrauzer(id, yakun);
        };
        a.play().catch(() => {
          const chalindi = boshlandi;
          tozala();
          if (chalindi) yakun(); else ttsBrauzer(id, yakun);
        });
      })
      .catch(() => ttsBrauzer(id, yakun));
  });
}

// ── Zaxira: brauzerning o'z sintezatori ──────────────────
function ttsOvozTanla(){
  const vs = (window.speechSynthesis && speechSynthesis.getVoices()) || [];
  return vs.find(v => /^uz/i.test(v.lang))
      || vs.find(v => /^ru/i.test(v.lang))
      || null;
}

function ttsBrauzer(id, done){
  const o = _orderMap[id];
  if (!o || !('speechSynthesis' in window)) { done(); return; }

  const v = ttsOvozTanla();
  // Ruscha ovoz lotin yozuvini butunlay boshqacha o'qiydi —
  // kirillga o'girib bersak tushunarli chiqadi.
  const ruscha = v && /^ru/i.test(v.lang);
  const matn   = ruscha ? uzKirill(orderMatn(o)) : orderMatn(o);

  const u = new SpeechSynthesisUtterance(matn);
  u.lang   = v ? v.lang : 'uz-UZ';
  u.rate   = 0.95;
  u.volume = 1;
  if (v) u.voice = v;
  u.onend = u.onerror = () => done();

  try { speechSynthesis.cancel(); speechSynthesis.speak(u); }
  catch { done(); }
}

// Lotin → kirill (faqat ruscha ovoz uchun, taxminiy talaffuz).
// Ruscha alifboda yo'q harflar (ў, ғ, қ, ҳ) eng yaqin ruschasiga
// o'giriladi: "lag'mon" → "лагмон", "sho'rva" → "шурва".
function uzKirill(s){
  const juft = [
    [/o['’‘ʻ`]/gi,'у'], [/g['’‘ʻ`]/gi,'г'],
    [/sh/gi,'ш'], [/ch/gi,'ч'], [/ts/gi,'ц'],
    [/yo/gi,'ё'], [/yu/gi,'ю'], [/ya/gi,'я'], [/ye/gi,'е'],
  ];
  juft.forEach(([re, ch]) => { s = s.replace(re, ch); });
  const bir = {a:'а',b:'б',d:'д',e:'е',f:'ф',g:'г',h:'х',i:'и',j:'ж',k:'к',
               l:'л',m:'м',n:'н',o:'о',p:'п',q:'к',r:'р',s:'с',t:'т',u:'у',
               v:'в',x:'х',y:'й',z:'з'};
  return s.replace(/[a-z]/gi, c => {
    const m = bir[c.toLowerCase()];
    return m === undefined ? c : (c === c.toUpperCase() ? m.toUpperCase() : m);
  }).replace(/['’‘ʻ`]/g, '');
}

// Menyudagi nomlar BOSH HARFDA ("OSH") — sintezator ularni
// harflab o'qiydi. Serverdagi im_tts_nomi bilan bir xil mantiq.
function ttsNomi(nomi){
  nomi = String(nomi || '').trim();
  const harflar = nomi.replace(/[^A-Za-zА-Яа-яЎўҚқҒғҲҳЁё]+/g, '');
  if (harflar.length > 1 && nomi === nomi.toUpperCase()) nomi = nomi.toLowerCase();
  return nomi;
}

// mijoz_ism aslida MANZIL: "Stol 1", "VIP kabina", "Olib ketish".
// Ekranda nima yozilgan bo'lsa — ovozda ham shu (im_tts_manzil kabi).
function ttsManzil(o){
  const nom = String(o.mijoz_ism || '').trim();
  if (nom) return nom;
  return o.olib_ketish ? 'Olib ketish' : '';
}

// ── Zaxira uchun matn (serverdagi im_tts_matn'ning ko'rinishi) ──
function orderMatn(o){
  const hammasi = o.items || [];
  const items   = hammasi.slice(0, 4);
  const qolgan  = hammasi.length - items.length;
  // Nuqta emas, VERGUL — model har nuqtada uzoq pauza qo'yadi
  // va xabar uzilib-uzilib eshitiladi (serverdagi izohga qarang).
  const p = ['Yangi buyurtma'];

  const manzil = ttsManzil(o);
  if (manzil) p.push(manzil);

  // "to'rt porsiya osh" — son, birlik, keyin taom nomi
  const taom = items.map(i => {
    const n = parseFloat(i.soni);
    const s = ttsQty(n);
    let   b = String(i.birlik || '').toLowerCase();
    if (b === '' || b === 'dona') b = 'ta';
    return (s + ' ' + b + ' ' + ttsNomi(i.nomi)).trim();
  });
  if (taom.length)  p.push(taom.join(', '));
  if (qolgan > 0)   p.push('va yana ' + qolgan + ' xil taom');
  if (o.izoh)       p.push('izoh bor, ekranga qarang');
  return p.join(', ') + '.';
}

// ── Raqam formatlash ─────────────────────────────────────
function fmt(n){ return Math.round(n).toLocaleString('uz-UZ'); }
function fmtQty(v){
  const n = Math.round((parseFloat(v) || 0) * 1000) / 1000;
  const whole = Math.floor(n + .0001), frac = Math.round((n - whole) * 1000) / 1000;
  const mark = Math.abs(frac-.25)<.001 ? '¼' : (Math.abs(frac-.5)<.001 ? '½' : (Math.abs(frac-.75)<.001 ? '¾' : ''));
  if (mark) return (whole ? whole : '') + mark;
  return Number.isInteger(n) ? String(n) : n.toLocaleString('uz-UZ',{maximumFractionDigits:3});
}
function ttsQty(v){
  const n = Math.round((parseFloat(v) || 0) * 1000) / 1000;
  if (Math.abs(n-.25)<.001) return 'chorak';
  if (Math.abs(n-.5)<.001) return 'yarim';
  if (Math.abs(n-.75)<.001) return 'uch chorak';
  const whole = Math.floor(n), frac = Math.round((n-whole)*100)/100;
  if (whole > 0 && Math.abs(frac-.5)<.001) return whole + ' yarim';
  return String(n);
}

// Buyurtma kartasida faqat soat emas, oshxona uchun muhim bo'lgan
// kutish davomiyligi ham ko'rinadi. 15 daqiqadan oshsa vaqt ajratib ko'rsatiladi.
function orderTime(raw){
  const date = new Date(String(raw || '').replace(' ', 'T'));
  if (isNaN(date.getTime())) return { clock: '—', age: '', late: false };
  const mins = Math.max(0, Math.floor((Date.now() - date.getTime()) / 60000));
  const clock = date.toLocaleTimeString('uz-UZ', {hour:'2-digit', minute:'2-digit'});
  const age = mins < 1 ? 'hozir' : (mins < 60 ? mins + ' daq' : Math.floor(mins / 60) + ' soat ' + (mins % 60) + ' daq');
  return { clock, age, late: mins >= 15 };
}

// ── Chek chiqarish (popup oyna orqali — ishonchli usul) ─────
function printKitchenById(id) {
  const o = _orderMap[id];
  if (!o) { NHToast.warning('Buyurtma topilmadi'); return; }
  printKitchen(o);
}

function printKitchen(o) {
  const ts  = new Date(o.created_at).toLocaleString('uz-UZ');
  const now = new Date().toLocaleTimeString('uz-UZ');
  const items = (o.all_items || o.items || []);

  let rows = '';
  items.forEach(i => {
    const soni = parseFloat(i.soni);
    const s = fmtQty(soni);
    // Saboyga olinadigan qator chekda ham aniq ko'rinsin
    const oks = parseFloat(i.olib_ketish_soni || 0);
    const okt = oks > 0 ? `<div class="pack">🛍️ ${oks} ta QADOQLANSIN</div>` : '';
    rows += `<div class="row">
      <span class="name">${im_esc(i.nomi)}${okt}</span>
      <span class="qty">${s} ${im_esc(i.birlik||'')}</span>
    </div>`;
  });

  const html = `<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Chek</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Courier New',monospace}
@page{size:80mm auto;margin:2mm}
body{font-weight:bold;color:#000;font-size:13px;background:#fff;display:flex;justify-content:center}
.chek{width:80mm;padding:2mm}
.h{text-align:center;font-size:20px;font-weight:900;border-bottom:3px solid #000;padding-bottom:2mm;margin-bottom:2mm}
.stol{text-align:center;font-size:30px;font-weight:900;margin:2mm 0;letter-spacing:1px}
.sotuv{text-align:center;font-size:14px;color:#555;margin-bottom:2mm}
.info{display:flex;justify-content:space-between;font-size:12px;margin:1mm 0}
.dash{border-top:2px dashed #000;margin:2mm 0}
.solid{border-top:3px solid #000;margin:2mm 0}
.cols{display:grid;grid-template-columns:1fr 22mm;font-size:12px;font-weight:900;padding:1mm 0}
.row{display:grid;grid-template-columns:1fr 22mm;font-size:17px;font-weight:900;
     padding:2.5mm 0;border-bottom:2px dotted #000}
.name{word-break:break-word;text-transform:uppercase;line-height:1.2;padding-right:2mm}
.pack{display:inline-block;border:2px solid #000;padding:0.5mm 1.5mm;font-size:12px;margin-top:1mm}
.qty{text-align:right;font-size:20px}
.foot{text-align:center;font-size:11px;margin-top:3mm;color:#333}
</style>
</head>
<body>
<div class="chek">
  <div class="h">OSHXONA</div>
  <div class="stol">&#127829; ${im_esc(o.mijoz_ism)||'STOL ?'}</div>
  <div class="sotuv">Sotuv #${o.kun_raqam||o.id}</div>
  ${o.olib_ketish ? `<div style="border:3px solid #000;background:#000;color:#fff;padding:2.5mm;margin:2mm 0;font-size:16px;font-weight:900;text-align:center">&#128717; OLIB KETISH — QADOQLANSIN</div>` : ''}
  <hr class="solid">
  <div class="info"><span>Sotuvchi:</span><span>${im_esc(o.sotuvchi_ism)}</span></div>
  <div class="info"><span>Vaqt:</span><span>${ts}</span></div>
  ${o.izoh ? `<div style="border:2px solid #000;padding:2mm;margin:2mm 0;font-size:13px;text-align:center">&#9888; IZOH: ${im_esc(o.izoh)}</div>` : ''}
  <hr class="dash">
  <div class="cols"><span>TAOM</span><span style="text-align:right">SONI</span></div>
  <hr class="dash">
  ${rows}
  <hr class="solid">
  <div class="foot">Chop vaqti: ${now}</div>
</div>
<script>window.onload=function(){window.print();setTimeout(function(){window.close();},300);};window.onafterprint=function(){window.close();};<\/script>
</body></html>`;

  // Yangi popup oyna orqali chop etish
  const w = window.open('', '_blank', 'width=500,height=700,toolbar=0,menubar=0,scrollbars=1');
  if (!w) {
    NHToast.warning('Chek oynasi bloklandi. Brauzer sozlamalarida bu sayt uchun popupga ruxsat bering.', 6000);
    return;
  }
  w.document.open();
  w.document.write(html);
  w.document.close();
}

// ── Umumiy: oshpaz amalini yuborish ──────────────────────
async function oshpazAmal(url, id, btn, tasdiq, cardPrefix) {
  if (tasdiq) {
    const opts = typeof tasdiq === 'string' ? { text: tasdiq } : tasdiq;
    const rozimi = await NHConfirm.show(opts);
    if (!rozimi) return;
  }
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saqlanmoqda...';

  const fd = new FormData();
  fd.append('id', id);
  try {
    const res  = await fetch(IM_BASE + url, {method:'POST',body:fd});
    const data = await res.json();
    if (data.status === 'ok') {
      const msg = data.msg || 'Amal bajarildi';
      if (msg.includes('⚠️')) NHToast.warning(msg, 7000);
      else NHToast.success(msg);
      const card = document.getElementById(cardPrefix + id);
      if (card) {
        card.style.transition='.3s';
        card.style.opacity='0';
        card.style.transform='scale(.93)';
        setTimeout(()=>{ card.remove(); }, 300);
      }
      loadOrders();
    } else {
      NHToast.error(data.msg || 'Amalni bajarib bo\'lmadi', 6000);
      btn.innerHTML = orig; btn.disabled = false;
    }
  } catch {
    NHToast.error('Tarmoq xatosi. Internet yoki server ulanishini tekshiring.', 6000);
    btn.innerHTML = orig; btn.disabled = false;
  }
}

// 1-BOSQICH: Qabul qildim — xomashyo shu yerda sarflanadi
function acceptOrder(id, btn) {
  oshpazAmal('oshpaz/ajax/order-qabul.php', id, btn,
    {
      title: 'Buyurtmani qabul qilish',
      text: 'Buyurtmani qabul qilib, pishirishni boshlaysizmi?',
      sub: 'Retseptli taomlarning xomashyosi qoldiqdan yechiladi. Qozon mahsulotlari ochilgan qozon hisobidan olinadi.',
      btnText: 'Qabul qilish',
      btnClass: 'im-btn-success',
      icon: 'bi-check-circle-fill',
      iconColor: 'var(--green)'
    },
    'ocard-');
}

// 2-BOSQICH: Tayyor — ofitsantga signal boradi.
// Tasdiqlash so'ralmaydi: oshpaz uchun bu tez-tez bosiladigan amal va
// xatosi qaytariladigan (ofitsant ko'radi). Xomashyoga ta'sir qilmaydi.
function readyOrder(id, btn) {
  oshpazAmal('oshpaz/ajax/order-tayyor.php', id, btn, null, 'ccard-');
}

let _lastOrdersHtml = '';
let _lastHistoryHtml = '';

// ── Render: Navbatdagi buyurtmalar ───────────────────────
function renderOrders(orders) {
  _allOrders = orders;
  document.getElementById('cnt-orders').textContent = orders.length;
  const grid = document.getElementById('grid-orders');

  if (!orders.length) {
    const emptyHtml = `<div class="empty-state">
      <i class="bi bi-cup-hot"></i><h3>Navbatdagi buyurtmalar yo'q</h3>
    </div>`;
    if (_lastOrdersHtml !== emptyHtml) {
      grid.innerHTML = emptyHtml;
      _lastOrdersHtml = emptyHtml;
    }
    _prevIds.clear(); _firstLoad = false; return;
  }

  const curIds   = new Set(orders.map(o => o.id));
  const yangiIds = [];
  let   hasNew   = false;

  // orderMap ni yangilaymiz — chek uchun
  _orderMap = {};
  orders.forEach(o => { _orderMap[o.id] = o; });

  const newHtml = orders.map(o => {
    const isNew = !_prevIds.has(o.id) && !_firstLoad;
    if (isNew) { hasNew = true; yangiIds.push(o.id); }

    const tm = orderTime(o.created_at);

    const itemsHtml = o.items.map(i => {
      const tag = parseInt(i.oshpaz_kerak)===1
        ? '<span class="item-tag">&#9832;</span>' : '';
      // Qator darajasidagi "saboyga" belgisi — aynan shu porsiya qadoqlanadi
      const oks = parseFloat(i.olib_ketish_soni || 0);
      const okt = oks > 0 ? `<span class="item-ok">🛍️ ${fmtQty(oks)} QADOQ</span>` : '';
      const soni = parseFloat(i.soni);
      const s = fmtQty(soni);
      return `<div class="item-row">
        <span class="item-name">${im_esc(i.nomi)}${tag}${okt}</span>
        <span class="item-qty">${s} ${im_esc(i.birlik||'')}</span>
      </div>`;
    }).join('');

    return `<div class="order-card ${isNew?'new-pulse':''}" id="ocard-${o.id}" style="animation:fadeUp .35s ease">
      <div class="card-head">
        <span class="stol"><i class="bi bi-table"></i> ${im_esc(o.mijoz_ism)||'?'}</span>
        <div class="meta-right">
          <div class="sotuv-no">Buyurtma #${o.kun_raqam||o.id}</div>
          <div class="time ${tm.late?'is-late':''}" title="Qabul vaqti: ${tm.clock}"><i class="bi bi-clock"></i> ${tm.clock} · ${tm.age}</div>
        </div>
      </div>
      ${(o.olib_ketish || !o.stol_id) ? `<div class="card-olib-ketish"><i class="bi bi-bag-check-fill"></i> ${im_esc(o.mijoz_ism) || 'OLIB KETISH'} — QADOQLANSIN</div>` : ''}
      <div class="card-seller">
        <i class="bi bi-person"></i> Sotuvchi: <b>${im_esc(o.sotuvchi_ism)}</b>
      </div>
      ${o.izoh ? `<div class="card-izoh"><i class="bi bi-chat-left-text"></i> ${im_esc(o.izoh)}</div>` : ''}
      <div class="card-items">${itemsHtml}</div>
      <div class="card-foot">
        <button class="btn-ovoz" id="ovz-${o.id}" title="Qayta o'qish"
                onclick="speakOrder(${o.id}, true)">🔊</button>
        <button class="btn-print-ck" onclick="printKitchenById(${o.id})">
          <i class="bi bi-printer-fill"></i> Chek chiqarish
        </button>
        <button class="btn-done" id="done-${o.id}" onclick="acceptOrder(${o.id}, this)">
          <i class="bi bi-check-lg"></i> Qabul qildim
        </button>
      </div>
    </div>`;
  }).join('');

  if (_lastOrdersHtml !== newHtml) {
    grid.innerHTML = newHtml;
    _lastOrdersHtml = newHtml;
  }

  _prevIds   = curIds;
  _firstLoad = false;

  if (hasNew) {
    ringBell(2);
    // Jiringlash ~1.2s davom etadi — ovoz shundan keyin boshlansin,
    // aks holda birinchi so'z qo'ng'iroq ostida qolib ketadi.
    setTimeout(() => yangiIds.forEach(id => speakOrder(id)), 1100);
  }
}

// ── Render: Pishirilmoqda (qabul qilingan) ───────────────
let _lastCookingHtml = '';
function renderCooking(list) {
  document.getElementById('cnt-cooking').textContent = list.length;
  const grid = document.getElementById('grid-cooking');

  if (!list.length) {
    const emptyHtml = `<div class="empty-state">
      <i class="bi bi-hourglass"></i><h3>Pishirilayotgan buyurtma yo'q</h3>
    </div>`;
    if (_lastCookingHtml !== emptyHtml) { grid.innerHTML = emptyHtml; _lastCookingHtml = emptyHtml; }
    return;
  }

  // Chek chiqarish uchun bu kartalar ham xaritaga tushsin
  list.forEach(o => { _orderMap[o.id] = o; });

  const newHtml = list.map(o => {
    const tm = orderTime(o.updated_at || o.created_at);
    const itemsHtml = (o.items||[]).map(i => {
      const oks = parseFloat(i.olib_ketish_soni || 0);
      const okt = oks > 0 ? `<span class="item-ok">🛍️ ${fmtQty(oks)} QADOQ</span>` : '';
      const soni = parseFloat(i.soni);
      const s = fmtQty(soni);
      return `<div class="item-row">
        <span class="item-name">${im_esc(i.nomi)}${okt}</span>
        <span class="item-qty">${s} ${im_esc(i.birlik||'')}</span>
      </div>`;
    }).join('');

    return `<div class="order-card" id="ccard-${o.id}" style="animation:fadeUp .35s ease">
      <div class="card-head">
        <span class="stol"><i class="bi bi-table"></i> ${im_esc(o.mijoz_ism)||'?'}</span>
        <div class="meta-right">
          <div class="sotuv-no">Buyurtma #${o.kun_raqam||o.id}</div>
          <div class="time ${tm.late?'is-late':''}" title="Boshlangan vaqt: ${tm.clock}"><i class="bi bi-stopwatch"></i> ${tm.age}</div>
        </div>
      </div>
      ${(o.olib_ketish || !o.stol_id) ? `<div class="card-olib-ketish"><i class="bi bi-bag-check-fill"></i> ${im_esc(o.mijoz_ism) || 'OLIB KETISH'} — QADOQLANSIN</div>` : ''}
      <div class="card-seller"><i class="bi bi-person"></i> Sotuvchi: <b>${im_esc(o.sotuvchi_ism)}</b></div>
      ${o.izoh ? `<div class="card-izoh"><i class="bi bi-chat-left-text"></i> ${im_esc(o.izoh)}</div>` : ''}
      <div class="card-items">${itemsHtml}</div>
      <div class="card-foot">
        <button class="btn-print-ck" onclick="printKitchenById(${o.id})">
          <i class="bi bi-printer-fill"></i> Chek chiqarish
        </button>
        <button class="btn-done" onclick="readyOrder(${o.id}, this)">
          <i class="bi bi-bell-fill"></i> Tayyor
        </button>
      </div>
    </div>`;
  }).join('');

  if (_lastCookingHtml !== newHtml) { grid.innerHTML = newHtml; _lastCookingHtml = newHtml; }
}

// ── Render: Tarix ────────────────────────────────────────
function renderHistory(history) {
  document.getElementById('cnt-history').textContent = history.length;
  const grid = document.getElementById('grid-history');

  if (!history.length) {
    const emptyHtml = `<div class="empty-state">
      <i class="bi bi-clock-history"></i><h3>Bugun hali hech narsa tayyorlamadingiz</h3>
    </div>`;
    if (_lastHistoryHtml !== emptyHtml) {
      grid.innerHTML = emptyHtml;
      _lastHistoryHtml = emptyHtml;
    }
    return;
  }

  const newHtml = history.map(o => {
    const tStr = new Date(o.tayyor_vaqti).toLocaleTimeString('uz-UZ',{hour:'2-digit',minute:'2-digit'});
    const summa = parseFloat(o.summa||0);

    const itemsHtml = o.items.map(i =>
      `<div class="hist-item">
        <span class="name">${im_esc(i.nomi)}</span>
        <span class="qty">${fmtQty(i.soni)} ${im_esc(i.birlik||'')}</span>
      </div>`
    ).join('');

    return `<div class="hist-card">
      <div class="hist-head">
        <span class="stol"><i class="bi bi-check-circle-fill"></i> ${im_esc(o.mijoz_ism)||'Nomsiz'}</span>
        <div class="meta">
          <div class="sotuv-no">📋 Sotuv #${o.kun_raqam||o.id}</div>
          <div class="time"><i class="bi bi-clock"></i> ${tStr}</div>
        </div>
      </div>
      <div class="hist-seller">
        <i class="bi bi-person"></i> Sotuvchi: <b>${im_esc(o.sotuvchi_ism)}</b>
      </div>
      <div class="hist-items">${itemsHtml}</div>
      ${summa>0 ? `<div class="hist-foot">
        <span style="color:var(--muted)">Jami:</span>
        <span class="summa">${fmt(summa)} so'm</span>
      </div>` : ''}
    </div>`;
  }).join('');

  if (_lastHistoryHtml !== newHtml) {
    grid.innerHTML = newHtml;
    _lastHistoryHtml = newHtml;
  }
}

// ── Qozon / zagotovka eslatmasi ──────────────────────────
let _lastEslatmaHtml = '';
function renderEslatma(list) {
  const panel = document.getElementById('eslatma-panel');
  if (!panel) return;
  if (!list.length) {
    if (_lastEslatmaHtml !== '') { panel.innerHTML = ''; panel.hidden = true; _lastEslatmaHtml = ''; }
    return;
  }
  const ikon = { xato:'bi-exclamation-octagon-fill', ogoh:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
  const html = list.map(e => {
    const d = ['xato','ogoh','info'].includes(e.daraja) ? e.daraja : 'info';
    return `<div class="eslatma ${d}">
      <i class="bi ${ikon[d]}"></i>
      <span>${im_esc(e.matn || '')}</span>
      <a href="${IM_BASE}oshpaz/qozon.php">Qozon oynasi →</a>
    </div>`;
  }).join('');
  if (_lastEslatmaHtml !== html) {
    panel.innerHTML = html;
    panel.hidden = false;
    _lastEslatmaHtml = html;
  }
}

// ── Polling ──────────────────────────────────────────────
function setConnection(online){
  const el = document.getElementById('live-status');
  if (!el) return;
  el.classList.toggle('offline', !online);
  el.textContent = online ? 'Jonli' : 'Ulanish yo\'q';
}

async function loadOrders() {
  try {
    const res  = await fetch(IM_BASE + 'oshpaz/ajax/get-orders.php?_='+Date.now());
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (data.status === 'ok') {
      // Tartib muhim: renderOrders _orderMap ni tozalab qayta to'ldiradi,
      // renderCooking esa ustiga qo'shadi (chek chiqarish uchun kerak).
      renderOrders(data.orders || []);
      renderCooking(data.cooking || []);
      renderHistory(data.history || []);
      renderEslatma(data.eslatmalar || []);
      setConnection(true);
    } else {
      setConnection(false);
    }
  } catch { setConnection(false); }
}

setInterval(loadOrders, 5000);
loadOrders();
</script>
</body>
</html>
