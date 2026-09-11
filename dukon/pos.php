<?php
// ============================================================
//  IMezon — POS Sotuv Oynasi
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['kassir']);
header('Content-Type: text/html; charset=UTF-8');
$db = new Cyber();

// Joriy smena
$smena = $db->row(
    "SELECT s.*, x.ism AS kassir_ism
     FROM im_smena s
     LEFT JOIN im_xodimlar x ON x.id=s.kassir_id
     WHERE s.kassir_id=$im_user_id AND s.holat='ochiq'
     ORDER BY s.id DESC LIMIT 1"
);

// Kategoriyalar (filtrlash uchun)
$kategoriyalar = $db->rows(
    "SELECT k.id, k.nomi, k.rang,
            COUNT(m.id) AS mah_soni
     FROM im_kategoriyalar k
     LEFT JOIN im_mahsulotlar m ON m.kategoriya_id=k.id AND m.status=1
     WHERE k.status=1
     GROUP BY k.id HAVING mah_soni>0
     ORDER BY k.nomi ASC"
);

// USD kursi
$usd_kurs = im_usd_kurs() ?: 12900;
$max_ch   = (float)(im_sozlama('kassir_max_chegirma') ?: 10);
// Xizmat haqi (otsluga) — admin belgilaydi, kassir faqat yoqa/o'chira oladi
$xizmat_foiz = max(0, min(100, (float)(im_sozlama('xizmat_foiz') ?: 0)));
$dukon_nomi = im_sozlama('dukon_nomi') ?: 'IMezon';

// Bugungi sotuv statistikasi
$bugun_stats = ['sotuv' => 0, 'summa' => 0];
if ($smena) {
    $sid = (int)$smena['id'];
    $bugun_stats['sotuv'] = (int)$db->val("SELECT COUNT(*) FROM im_sotuvlar WHERE smena_id=$sid");
    $bugun_stats['summa'] = (float)$db->val("SELECT COALESCE(SUM(tolov_summa),0) FROM im_sotuvlar WHERE smena_id=$sid");
}
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>POS Sotuv | <?= im_f($dukon_nomi) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
<style>
/* ──── POS Layout ────────────────────────────────────────────────────────────────────── */
.pos-wrapper { display:flex; height:100vh; overflow:hidden; }
.pos-left {
  width:60%; display:flex; flex-direction:column;
  border-right:1.5px solid var(--border); overflow:hidden;
}
.pos-right {
  width:40%; display:flex; flex-direction:column;
  background:var(--card); overflow:hidden;
}
.pos-searchbar {
  padding:12px 16px; border-bottom:1px solid var(--border);
  background:var(--card); flex-shrink:0;
}
.pos-cats {
  display:flex; gap:6px; padding:10px 16px;
  overflow-x:auto; flex-shrink:0;
  border-bottom:1px solid var(--border);
}
.pos-cats::-webkit-scrollbar { height:3px; }
.pos-cat-btn {
  padding:5px 14px; border-radius:16px; font-size:12px;
  font-weight:600; cursor:pointer; white-space:nowrap;
  border:1.5px solid var(--border); background:var(--card);
  color:var(--muted); transition:all var(--transition);
}
.pos-cat-btn.active,
.pos-cat-btn:hover { background:var(--accent); border-color:var(--accent); color:var(--primary); }

.pos-products {
  flex:1; overflow-y:auto; padding:10px;
  display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr));
  gap:8px; align-content:start;
}
.pos-product-card {
  background:var(--card); border:1.5px solid var(--border);
  border-radius:var(--radius); padding:0; text-align:center;
  cursor:pointer; transition:all var(--transition); user-select:none;
  position:relative; overflow:hidden;
}
.pos-product-card:hover { border-color:var(--accent); transform:translateY(-2px); box-shadow:0 4px 16px rgba(226,185,111,.15); }
.pos-product-card:active { transform:scale(.96); }
.pos-product-card.sold-out { opacity:.45; cursor:not-allowed; }
.pos-product-card .p-img {
  width:100%; height:90px; object-fit:cover; display:block;
  background:var(--bg);
}
.pos-product-card .p-img-placeholder {
  width:100%; height:90px; display:flex; align-items:center;
  justify-content:center; background:var(--bg); color:var(--border);
  font-size:32px;
}
.pos-product-card .p-body { padding:8px 6px 6px; }
.pos-product-card .p-name { font-size:11.5px; font-weight:700; margin-bottom:3px; line-height:1.3; }
.pos-product-card .p-price { font-size:13px; font-weight:800; color:var(--accent-dark); }
.pos-product-card .p-stock { font-size:10px; color:var(--muted); margin-top:2px; }
.pos-product-card .p-discount-badge {
  position:absolute; top:5px; right:5px;
  background:var(--danger); color:#fff;
  font-size:9px; font-weight:700; padding:1px 5px;
  border-radius:8px;
}
/* ──── Cart ────────────────────────────────────────────────────────────────────────────────────── */
.cart-header {
  background:var(--primary); color:#fff; padding:12px 18px;
  display:flex; align-items:center; justify-content:space-between;
  flex-shrink:0;
}
.cart-header-title { font-size:15px; font-weight:700; }
.cart-body { flex:1; overflow-y:auto; }
.cart-item {
  display:flex; align-items:center; gap:10px;
  padding:10px 14px; border-bottom:1px solid var(--border-light);
  transition:background .15s;
}
.cart-item:hover { background:var(--bg); }
.cart-item-thumb {
  width:44px; height:44px; border-radius:8px; object-fit:cover;
  border:1px solid var(--border); flex-shrink:0; background:var(--bg);
}
.cart-item-thumb-placeholder {
  width:44px; height:44px; border-radius:8px;
  border:1px solid var(--border); flex-shrink:0; background:var(--bg);
  display:flex; align-items:center; justify-content:center;
  color:var(--border); font-size:20px;
}
.cart-item-name { flex:1; font-size:12.5px; font-weight:600; }
.cart-item-price { font-size:12px; color:var(--muted); }
.qty-ctrl {
  display:flex; align-items:center; gap:4px;
}
.qty-btn {
  width:26px; height:26px; border-radius:6px;
  background:var(--bg); border:1px solid var(--border);
  font-size:14px; font-weight:700; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  color:var(--text); line-height:1; transition:all .15s;
}
.qty-btn:hover { background:var(--accent); border-color:var(--accent); color:var(--primary); }
.qty-input {
  width:52px; text-align:center; font-size:13px; font-weight:700;
  border:1.5px solid var(--accent); border-radius:6px;
  background:var(--card); color:var(--text); padding:2px 4px;
  height:26px; outline:none;
}
.qty-input:focus { border-color:var(--primary); box-shadow:0 0 0 2px rgba(79,70,229,.15); }
.p-fractions{display:flex;gap:4px;padding:0 8px 8px}
.p-fraction-btn{flex:1;height:27px;border:1px solid var(--border);border-radius:7px;background:var(--card);color:var(--primary);font-size:11px;font-weight:800;cursor:pointer}
.p-fraction-btn:hover{background:var(--primary);border-color:var(--primary);color:#fff}
.cart-empty { padding:40px 20px; text-align:center; color:var(--muted); }
/* ──── Checkout panel ────────────────────────────────────────────────────────────────── */
.checkout-panel {
  padding:14px 16px; flex:1; background:var(--card);
}
.total-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
.total-main { font-size:22px; font-weight:900; color:var(--primary); }
.total-main-dark { color:#e2b96f; }
/* Payment tabs */
.pay-tabs { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:10px; }
.pay-tab {
  flex:1; min-width:60px; padding:7px 4px; text-align:center;
  border-radius:8px; border:1.5px solid var(--border);
  font-size:11px; font-weight:700; cursor:pointer;
  transition:all .15s; background:var(--card); color:var(--muted);
}
.pay-tab.active { background:var(--accent); border-color:var(--accent); color:var(--primary); }
.pay-input { display:none; }
.pay-input.visible { display:block; }

/* ──── Smena ochish ────────────────────────────────────────────────────────────────────── */
.smena-open-screen {
  display:flex; flex-direction:column; align-items:center;
  justify-content:center; height:100vh; gap:20px;
  background:var(--bg);
}
/* ──── Mijoz qidirish ──────────────────────────────────────────────────────────────────*/
.mijoz-search-wrap { position:relative; }
.mijoz-results {
  position:absolute; top:100%; left:0; right:0;
  background:var(--card); border:1.5px solid var(--accent);
  border-top:none; border-radius:0 0 8px 8px;
  max-height:200px; overflow-y:auto; z-index:200;
  box-shadow:var(--shadow);
}
.mijoz-item {
  padding:9px 12px; cursor:pointer; font-size:12.5px;
  border-bottom:1px solid var(--border-light);
  transition:background .12s;
}
.mijoz-item:hover { background:var(--bg); }
/* ──── Mah search result ──────────────────────────────────────────────────────────── */
.search-results {
  position:absolute; top:100%; left:0; right:0; z-index:150;
  background:var(--card); border:1.5px solid var(--accent);
  border-top:none; border-radius:0 0 8px 8px;
  max-height:280px; overflow-y:auto; box-shadow:var(--shadow);
}
.search-item {
  padding:10px 14px; cursor:pointer; font-size:12.5px;
  border-bottom:1px solid var(--border-light); transition:background .12s;
}
.search-item:hover { background:var(--bg); }
/* ──── Chek modal ────────────────────────────────────────────────────────────────────────── */
.chek-body { font-family:monospace; font-size:13px; }
.chek-row { display:flex; justify-content:space-between; margin:2px 0; }
.chek-divider { border:none; border-top:1px dashed #999; margin:8px 0; }
@media (max-width:768px) {
  .pos-left { width:100%; }
  .pos-right { display:none; }
}

/* ──── Zal xaritasi (hall map) ─────────────────────────────────────────────────────────── */
#hall-view { height:100vh; overflow-y:auto; background:var(--bg); display:flex; flex-direction:column; }
.hall-topbar {
  background:var(--primary); padding:10px 16px; display:flex; align-items:center; gap:12px; flex-shrink:0;
}
.hall-topbar .brand { color:var(--accent); font-weight:800; font-size:16px; }
.hall-toolbar { padding:14px 16px 6px; display:flex; align-items:center; gap:8px; flex-shrink:0; }
.hall-toolbar-title { font-size:15px; font-weight:800; color:var(--text); }
.hall-live { margin-left:auto; font-size:11px; color:var(--muted); display:flex; align-items:center; gap:5px; }
.hall-live .dot { width:7px; height:7px; border-radius:99px; background:var(--success); animation:hallPulse 1.6s infinite; }
@keyframes hallPulse { 0%,100%{opacity:1} 50%{opacity:.25} }

.hall-quick-row { display:flex; gap:8px; padding:8px 16px; flex-shrink:0; flex-wrap:wrap; }
.hall-quick-card {
  flex:1; min-width:150px; display:flex; align-items:center; justify-content:center; gap:7px;
  background:var(--card); border:2px solid var(--border); border-radius:12px;
  padding:12px; font-size:13px; font-weight:700; color:var(--text); cursor:pointer; transition:.15s;
}
.hall-quick-card:hover { border-color:var(--accent); color:var(--accent-dark); }
.hall-quick-card.primary { background:var(--accent); border-color:var(--accent); color:#fff; }

/* Zona tablari (filtr) */
.hall-zona-tabs { display:flex; gap:6px; padding:6px 16px 2px; flex-shrink:0; overflow-x:auto; scrollbar-width:none; }
.hall-zona-tabs::-webkit-scrollbar { display:none; }
.hall-zona-tab {
  flex-shrink:0; padding:7px 13px; border-radius:99px; font-size:12px; font-weight:700;
  background:var(--card); border:2px solid var(--border); color:var(--text); cursor:pointer;
  white-space:nowrap; transition:.15s; display:flex; align-items:center; gap:5px;
}
.hall-zona-tab .cnt { font-size:11px; opacity:.65; font-weight:600; }
.hall-zona-tab.active { background:var(--accent); border-color:var(--accent); color:var(--primary); }
.hall-zona-tab.active .cnt { opacity:.9; }

.hall-stol-grid {
  display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr));
  gap:10px; padding:8px 16px 24px;
}
.hall-stol-card {
  background:var(--card); border:2px solid var(--border); border-radius:14px;
  padding:14px 10px; text-align:center; cursor:pointer; transition:.15s;
  display:flex; flex-direction:column; gap:4px; align-items:center; min-height:126px;
}
.hall-stol-card:active { transform:scale(.97); }
.hall-stol-ic { font-size:22px; }
.hall-stol-nomi { font-size:13px; font-weight:800; color:var(--text); }
.hall-stol-holat { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
.hall-stol-vaqt { font-size:10px; opacity:.85; }
.hall-stol-summa { font-size:12px; font-weight:800; margin-top:1px; }
.hall-stol-ok-tag { font-size:9px; font-weight:700; background:#ffedd5; color:#9a3412; border-radius:99px; padding:2px 7px; margin-top:2px; }
.hall-order-summary { display:flex; align-items:center; justify-content:center; gap:5px; flex-wrap:wrap; margin-top:3px; }
.hall-order-chip {
  display:inline-flex; align-items:center; gap:4px; padding:3px 7px; border-radius:999px;
  background:rgba(255,255,255,.78); border:1px solid rgba(148,163,184,.32);
  color:var(--text); font-size:9px; font-weight:800; text-transform:none; letter-spacing:0;
}
.hall-order-chip.takeaway { color:#c2410c; border-color:#fed7aa; background:#fff7ed; }
.hall-card-open { margin-top:auto; padding-top:5px; color:var(--muted); font-size:9px; font-weight:700; }
.hall-card-open i { margin-left:3px; }
.hall-order-picker-list { display:flex; flex-direction:column; gap:9px; }
.hall-order-picker-row {
  width:100%; border:1.5px solid var(--border); border-radius:13px; background:var(--card);
  padding:12px 13px; display:flex; align-items:center; gap:11px; text-align:left; cursor:pointer;
  color:var(--text); transition:.15s;
}
.hall-order-picker-row:hover { border-color:var(--accent); transform:translateY(-1px); box-shadow:0 6px 18px rgba(15,23,42,.07); }
.hall-order-picker-row.takeaway { border-color:#fed7aa; background:#fffaf5; }
.hall-order-picker-icon {
  width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center;
  flex-shrink:0; background:#f1f5f9; color:#475569; font-size:17px;
}
.hall-order-picker-row.takeaway .hall-order-picker-icon { background:#ffedd5; color:#c2410c; }
.hall-order-picker-copy { min-width:0; flex:1; display:flex; flex-direction:column; }
.hall-order-picker-name { font-size:13px; font-weight:800; }
.hall-order-picker-meta { color:var(--muted); font-size:11px; margin-top:2px; }
.hall-order-picker-total { font-size:13px; font-weight:800; white-space:nowrap; }
.hall-order-picker-actions { display:grid; grid-template-columns:1fr; gap:8px; margin-top:12px; }
.hall-order-picker-actions .im-btn { justify-content:center; }

.hall-stol-card.bosh { border-color:#a7f3d0; background:#f0fdf4; }
.hall-stol-card.bosh .hall-stol-holat, .hall-stol-card.bosh .hall-stol-ic { color:var(--success); }

.hall-stol-card.ochiq { border-color:#fed7aa; background:#fff7ed; }
.hall-stol-card.ochiq .hall-stol-holat, .hall-stol-card.ochiq .hall-stol-summa, .hall-stol-card.ochiq .hall-stol-ic { color:#c2410c; }
.hall-stol-card.ochiq .hall-stol-vaqt { color:#9a3412; }

.hall-stol-card.kassada { border-color:#bfdbfe; background:#eff6ff; }
.hall-stol-card.kassada .hall-stol-holat, .hall-stol-card.kassada .hall-stol-summa, .hall-stol-card.kassada .hall-stol-ic { color:#1d4ed8; }

/* ──── Stol tahrirlash (kassir mahsulot qo'shish/o'chirish) ───────────────────────────────── */
#stol-edit-view { height:100vh; display:none; flex-direction:column; background:var(--bg); overflow:hidden; }
#stol-edit-view.visible { display:flex; }
.se-topbar {
  background:var(--primary); padding:10px 16px; display:flex; align-items:center; gap:10px; flex-shrink:0;
}
.se-back-btn {
  background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2); color:#fff;
  border-radius:8px; width:34px; height:34px; display:flex; align-items:center; justify-content:center;
  cursor:pointer; flex-shrink:0;
}
.se-stol-nomi { color:#fff; font-weight:800; font-size:15px; }
.se-body { flex:1; display:flex; overflow:hidden; }
.se-products { width:55%; display:flex; flex-direction:column; border-right:1.5px solid var(--border); overflow:hidden; background:var(--card); }
.se-cats { display:flex; gap:6px; padding:7px 8px; overflow-x:auto; flex-shrink:0; border-bottom:1px solid var(--border); }
.se-cat-btn {
  flex-shrink:0; padding:5px 11px; border-radius:16px; font-size:11px; font-weight:700;
  cursor:pointer; white-space:nowrap; border:1.5px solid var(--border); background:var(--card);
  color:var(--muted); transition:.15s;
}
.se-cat-btn:hover, .se-cat-btn.active { background:var(--accent); border-color:var(--accent); color:var(--primary); }
.se-cat-btn.se-cat-set { border-color:#e2b96f; color:#b8860b; }
.se-cat-btn.se-cat-set.active { background:#b8860b; border-color:#b8860b; color:#fff; }
.se-prod-grid { flex:1; overflow-y:auto; display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:8px; padding:10px; align-content:start; }
.se-prod-card { border:2px solid var(--border); border-radius:10px; cursor:pointer; padding:8px; background:var(--card); transition:.15s; }
.se-prod-card:hover { border-color:var(--accent); }
.se-prod-card.in-cart { border-color:var(--success); background:var(--success-light); }
.se-prod-card.sold-out { opacity:.5; cursor:not-allowed; }
.se-prod-name { font-size:12px; font-weight:600; color:var(--text); }
.se-prod-narx { font-size:12px; font-weight:800; color:var(--accent-dark); margin-top:3px; }
.se-set-items { font-size:10px; color:var(--muted); line-height:1.5; margin:5px 0; }
.se-cart { flex:1; display:flex; flex-direction:column; background:var(--card); }
.se-cart-body { flex:1; overflow-y:auto; padding:8px; }
.se-cart-item { display:flex; align-items:center; gap:8px; padding:8px; border:1px solid var(--border); border-radius:10px; margin-bottom:6px; background:var(--bg); }
.se-foot { border-top:1.5px solid var(--border); padding:12px 14px; flex-shrink:0; }
</style>
</head>
<body>
<?php if (!$smena): ?>
<!-- ──── SMENA YOPIQ EKRAN ────────────────────────────────────────────────────── -->
<div class="smena-open-screen">
  <div style="font-size:60px">✔️</div>
  <h2 style="font-size:22px;font-weight:800;color:var(--primary)"><?= im_f($dukon_nomi) ?></h2>
  <p class="text-muted">Sotuvni boshlash uchun smenani oching</p>
  <div class="im-card" style="width:340px">
    <div class="im-card-body p-4">
      <button class="im-btn im-btn-primary im-btn-lg w-100" id="btn-smena-open">
        <i class="bi bi-play-circle-fill"></i> Smenani ochish
      </button>
      <div class="text-muted fs-xs mt-2 text-center">
        <i class="bi bi-info-circle"></i> Boshlang'ich pul admin tomonidan kiritiladi
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ──── ZAL XARITASI (kirish ekrani) ────────────────────────────────────────────── -->
<div id="hall-view">
  <div class="hall-topbar">
    <span class="brand">✔️ <?= im_f($dukon_nomi) ?></span>
    <span class="ms-auto" style="color:rgba(255,255,255,.7);font-size:12px">
      <i class="bi bi-person-fill"></i> <?= im_f($smena['kassir_ism']) ?>
    </span>
    <a href="<?= im_BASE ?>dukon/index.php" class="im-btn im-btn-dark im-btn-sm">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
  </div>
  <div class="hall-toolbar">
    <div class="hall-toolbar-title"><i class="bi bi-grid-3x3-gap-fill"></i> Zal xaritasi</div>
    <div class="hall-live"><span class="dot"></span> jonli</div>
  </div>
  <div class="hall-quick-row">
    <div class="hall-quick-card primary" onclick="dukonDirectSale()">
      <i class="bi bi-cart-plus-fill"></i> To'g'ridan-to'g'ri sotuv
    </div>
    <div class="hall-quick-card" onclick="dukonOpenQuick('Dastavka')">
      <i class="bi bi-scooter"></i> Dastavka
    </div>
  </div>
  <!-- Stolga bog'lanmaydigan faol buyurtmalar — faqat Dastavka -->
  <div id="hall-stolsiz-wrap" style="display:none">
    <div class="px-3 pt-2 pb-1 fw-bold" style="font-size:13px;color:var(--text-muted)">
      <i class="bi bi-scooter"></i> Dastavka buyurtmalari
    </div>
    <div class="hall-stol-grid" id="hall-stolsiz-grid"></div>
  </div>

  <!-- Zona tablari (Teraska / Podval / Zal ...) — 1 tadan ko'p zona bo'lsa ko'rinadi -->
  <div class="hall-zona-tabs" id="hall-zona-tabs" style="display:none"></div>

  <div class="hall-stol-grid" id="hall-stol-grid">
    <div class="text-center text-muted p-4" style="grid-column:1/-1"><i class="bi bi-hourglass-split"></i> Yuklanmoqda...</div>
  </div>
</div>

<!-- ──── STOL TAHRIRLASH (mahsulot qo'shish/o'chirish) ───────────────────────────── -->
<div id="stol-edit-view">
  <div class="se-topbar">
    <button class="se-back-btn" onclick="seBackToHall()" title="Zal xaritasiga qaytish">
      <i class="bi bi-arrow-left"></i>
    </button>
    <span class="se-stol-nomi" id="se-stol-nomi">—</span>
    <button class="im-btn im-btn-sm ms-auto" id="se-takeaway-btn" onclick="dukonStartTakeawayFromCurrent()"
            style="background:#fff7ed;color:#c2410c;border-color:#fb923c;display:none">
      <i class="bi bi-bag-plus-fill"></i> Yangi olib ketish
    </button>
  </div>
  <div class="se-body">
    <div class="se-products">
      <div style="padding:8px" class="pos-searchbar">
        <input class="im-input" type="text" id="se-search" placeholder="Mahsulot qidirish..." autocomplete="off">
      </div>
      <div class="se-cats" id="se-cats">
        <button type="button" class="se-cat-btn active" data-kat="0" onclick="seFilterKat(0,this)">
          <i class="bi bi-grid-3x3-gap-fill"></i> Barchasi
        </button>
        <button type="button" class="se-cat-btn se-cat-set" data-kat="__sets__" onclick="seFilterKat('__sets__',this)">
          🎁 Setlar <span id="se-set-count"></span>
        </button>
        <?php foreach ($kategoriyalar as $k): ?>
        <button type="button" class="se-cat-btn" data-kat="<?= (int)$k['id'] ?>" onclick="seFilterKat(<?= (int)$k['id'] ?>,this)">
          <?= im_f($k['nomi']) ?> <span style="opacity:.65">(<?= (int)$k['mah_soni'] ?>)</span>
        </button>
        <?php endforeach; ?>
      </div>
      <div class="se-prod-grid" id="se-prod-grid"></div>
    </div>
    <div class="se-cart">
      <div class="se-cart-body" id="se-cart-body">
        <div class="text-center text-muted p-4"><i class="bi bi-cart-x" style="font-size:32px;opacity:.3"></i><p class="mt-2">Savatcha bo'sh</p></div>
      </div>
      <div class="se-foot">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted fs-sm">Jami:</span>
          <span style="font-size:18px;font-weight:800;color:var(--accent-dark)" id="se-total">0 so'm</span>
        </div>
        <div class="d-flex gap-2">
          <button class="im-btn im-btn-outline im-btn-sm" style="flex:1" id="se-save-btn" onclick="seSave()" disabled>
            <i class="bi bi-floppy-fill"></i> Saqlash
          </button>
          <button class="im-btn im-btn-primary" style="flex:1" id="se-checkout-btn" onclick="seCheckout()" disabled>
            <i class="bi bi-credit-card-fill"></i> Hisobni yopish
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ──── POS INTERFACE (to'g'ridan-to'g'ri sotuv / hisob yopish) ─────────────────── -->
<div class="pos-wrapper" id="pos-wrapper" style="display:none">

  <!-- CHAP: Mahsulotlar -->
  <div class="pos-left">

    <!-- Top bar -->
    <div style="background:var(--primary);padding:10px 16px;display:flex;align-items:center;gap:12px;flex-shrink:0">
      <button class="im-btn im-btn-sm" style="background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.2)"
              onclick="posBackToHall()" title="Zal xaritasiga qaytish">
        <i class="bi bi-arrow-left"></i>
      </button>
      <span style="color:var(--accent);font-weight:800;font-size:16px">✔️ <?= im_f($dukon_nomi) ?></span>
      <span class="ms-auto" style="color:rgba(255,255,255,.6);font-size:12px">
        <i class="bi bi-clock"></i> <span id="clock" style="display:inline-block;min-width:45px;text-align:left;font-variant-numeric:tabular-nums;letter-spacing:.5px"></span>
      </span>
      <span class="im-badge im-badge-success" style="font-size:11px">
        <?= im_f($smena['kassir_ism']) ?> · smena #{<?= $smena['id'] ?>}
      </span>
      <span style="color:rgba(255,255,255,.7);font-size:12px">
        Sotuv: <strong style="color:var(--accent)" id="stat-sotuv-soni"><?= $bugun_stats['sotuv'] ?></strong> ta /
        <strong style="color:var(--accent)" id="stat-sotuv-summa"><?= im_money($bugun_stats['summa']) ?></strong> so'm
      </span>
      <!-- Dashboard tugmasi -->
      <a href="<?= im_BASE ?>dukon/index.php" class="im-btn im-btn-dark im-btn-sm" title="Dashboard va boshqa operatsiyalar">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <button class="im-btn im-btn-danger im-btn-sm" id="btn-smena-close" title="Smenani yopish">
        <i class="bi bi-stop-circle"></i>
      </button>
      <button class="im-btn im-btn-sm" id="btn-inkasso" title="Kassadan olish"
              style="background:#7c3aed;color:#fff;border-color:#7c3aed">
        <i class="bi bi-wallet2"></i> Kassadan olish
      </button>
      <button class="im-topbar-btn" data-theme-toggle title="Rejim" style="color:#fff">
        <i class="bi bi-moon-fill"></i>
      </button>
    </div>

    <!-- Qidiruv -->
    <div class="pos-searchbar" style="position:relative">
      <div class="im-input-group">
        <input class="im-input" type="text" id="pos-search" autocomplete="off"
               placeholder="✔️ Mahsulot nomi yoki barcode... (F2)">
        <button class="im-btn im-btn-dark" id="btn-scan-pos" title="Barcode skaner">
          <i class="bi bi-upc-scan"></i>
        </button>
      </div>
      <div id="search-results" class="search-results" style="display:none"></div>
    </div>

    <!-- Kategoriya filtri -->
    <div class="pos-cats">
      <div class="pos-cat-btn active" data-kat="0">✔️ Hammasi</div>
      <?php foreach ($kategoriyalar as $k): ?>
      <div class="pos-cat-btn" data-kat="<?= $k['id'] ?>"
           style="<?= $k['rang'] ? "--cat-color:{$k['rang']}" : '' ?>">
        <?= im_f($k['nomi']) ?>
        <span style="opacity:.6;font-size:10px">(<?= $k['mah_soni'] ?>)</span>
      </div>
      <?php endforeach; ?>
      <!-- 🎁 Setlar tab -->
      <div class="pos-cat-btn" data-kat="__sets__" id="cat-btn-sets"
           style="border-color:#e2b96f;color:#b8860b;font-weight:700">
        🎁 Setlar
        <span id="sets-tab-count" style="opacity:.6;font-size:10px"></span>
      </div>
    </div>

    <!-- Mahsulotlar grid -->
    <div class="pos-products" id="pos-products">
      <div class="text-center text-muted" style="grid-column:1/-1;padding:40px 0">
        <i class="bi bi-search" style="font-size:32px;opacity:.3"></i>
        <p class="mt-2 fs-sm">Mahsulot qidiring yoki kategoriya tanlang</p>
      </div>
    </div>

    <!-- 🎁 Setlar grid (yashirin, Sets tab bosilganda ko'rinadi) -->
    <div class="pos-products" id="pos-sets-grid" style="display:none;align-content:start">
      <div class="text-center text-muted" style="grid-column:1/-1;padding:40px 0" id="sets-loading">
        <span class="im-spinner"></span>
        <p class="mt-2 fs-sm">Setlar yuklanmoqda...</p>
      </div>
    </div>
  </div>

  <!-- O'NG: Savat -->
  <div class="pos-right">
    <!-- Cart header -->
    <div class="cart-header">
      <div>
        <div class="cart-header-title"><i class="bi bi-cart3"></i> Savat</div>
        <div style="font-size:11px;opacity:.6" id="cart-count">Bo'sh</div>
      </div>
      <!-- Kutilgan orderlar -->
      <button class="im-btn im-btn-sm" id="btn-pending-orders"
              style="background:#f59e0b;color:#fff;border-color:#f59e0b;position:relative"
              onclick="openPendingOrders()" title="Sotuvchi orderlari">
        <i class="bi bi-basket3-fill"></i>
        <span id="pending-badge" style="display:none;position:absolute;top:-6px;right:-6px;
              background:#ef4444;color:#fff;border-radius:50%;width:18px;height:18px;
              font-size:10px;font-weight:800;align-items:center;justify-content:center">0</span>
        Orderlar
      </button>
      <button class="im-btn im-btn-ghost im-btn-sm" id="btn-cart-clear"
              style="color:#fff;border-color:rgba(255,255,255,.2)">
        <i class="bi bi-trash3"></i> Tozalash
      </button>
    </div>

    <!-- Cart items -->
    <div class="cart-body" id="cart-body">
      <div class="cart-empty">
        <i class="bi bi-cart3" style="font-size:40px;opacity:.2"></i>
        <p class="mt-2 fs-sm">Mahsulot qo'shing</p>
      </div>
    </div>

    <!-- Savat footer (To'lov tugmasi) -->
    <div style="padding:16px; border-top:2px solid var(--border); background:var(--card); flex-shrink:0;">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="text-muted fs-6">Jami summa:</span>
        <span class="fw-bold fs-4 num" id="cart-jami-text" style="color:var(--primary)">0 so'm</span>
      </div>
      <div class="d-flex gap-2">
        <button class="im-btn im-btn-secondary im-btn-lg" id="btn-pre-chek" onclick="printPreliminaryReceipt()" style="width: 55px; padding: 12px; background: #64748b; color: white; border: none;" title="Dastlabki chek">
          <i class="bi bi-printer"></i>
        </button>
        <button class="im-btn im-btn-success im-btn-lg w-100" onclick="openCheckoutModal()" style="font-size:18px; padding:12px">
          <i class="bi bi-wallet2"></i> To'lovga o'tish
          <kbd style="background:rgba(0,0,0,.15);padding:2px 6px;border-radius:4px;font-size:12px;margin-left:8px;vertical-align:middle">F12</kbd>
        </button>
      </div>
    </div>
  </div>

  <!-- ──── Checkout Modali ────────────────────────────────────────────────────────── -->
  <div class="im-overlay" id="checkout-modal">
    <div class="im-modal" style="max-width:480px;width:96%">
      <div class="im-modal-header border-bottom">
        <i class="bi bi-credit-card" style="color:var(--success);font-size:20px"></i>
        <span class="im-modal-title">To'lovni amalga oshirish</span>
        <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="im-modal-body" style="background:var(--bg); padding-top: 15px;">
        <div class="checkout-panel" style="padding:0; background:transparent;">
          <!-- MANBA: savdo qayerdan (stol / dastavka / to'g'ridan-to'g'ri).
               Mijozdan alohida — bu doim to'ldiriladi va o'zgartirilmaydi. -->
      <div id="manba-chip" class="mb-2 d-flex align-items-center gap-2"
           style="padding:8px 12px;border-radius:9px;background:rgba(226,185,111,.13);
                  border:1px solid rgba(226,185,111,.35)">
        <i class="bi bi-geo-alt-fill" style="color:var(--accent-dark);font-size:15px"></i>
        <span class="fw-bold" style="font-size:14px" id="manba-chip-text">To'g'ridan-to'g'ri savdo</span>
      </div>

          <!-- Mijoz — faqat chegirma toifasi / nasiya / vaucher uchun -->
      <div class="mijoz-search-wrap mb-2">
        <div class="d-flex gap-2 align-items-center">
          <div class="flex-grow-1" style="position:relative">
            <input class="im-input im-input-sm" type="text" id="mijoz-q"
                   placeholder="👤 Chegirmali mijoz / nasiya uchun (ixtiyoriy)..." autocomplete="off">
            <div id="mijoz-results" class="mijoz-results" style="display:none"></div>
          </div>
          <button class="im-btn im-btn-sm" id="btn-new-mijoz" title="Yangi mijoz qo'shish"
                  style="background:#10b981;color:#fff;border-color:#10b981;flex-shrink:0">
            <i class="bi bi-person-plus-fill"></i>
          </button>
          <button class="im-btn im-btn-ghost im-btn-icon im-btn-sm" id="btn-mijoz-clear" title="Tozalash">
            <i class="bi bi-x-circle-fill text-muted"></i>
          </button>
        </div>
        <input type="hidden" id="mijoz-id" value="">
        <div id="mijoz-info" style="display:none;margin-top:4px"
             class="im-badge im-badge-success fs-xs"></div>
      </div>

      <!-- Chegirma -->
      <div class="d-flex align-items-center gap-2 mb-2">
        <label class="text-muted fs-xs fw-semibold" style="white-space:nowrap">
          Chegirma (max <?= $max_ch ?>%):
        </label>
        <div class="d-flex align-items-center gap-1 flex-grow-1">
          <input class="im-input im-input-sm num" type="number" id="chegirma-foiz"
                 min="0" max="<?= $max_ch ?>" value="0" step="1">
          <span class="text-muted fs-xs">%</span>
        </div>
      </div>

      <!-- Voucher -->
      <div class="d-flex align-items-center gap-2 mb-2">
        <label class="text-muted fs-xs fw-semibold" style="white-space:nowrap">🎟️ Voucher:</label>
        <div class="d-flex gap-1 flex-grow-1">
          <input class="im-input im-input-sm" type="text" id="voucher-input"
                 placeholder="Kod kiriting..." autocomplete="off"
                 style="text-transform:uppercase;letter-spacing:1px;font-weight:700"
                 oninput="this.value=this.value.toUpperCase()">
          <button class="im-btn im-btn-sm" id="btn-voucher-check"
                  style="background:#f59e0b;color:#fff;border-color:#f59e0b;white-space:nowrap">
            Tekshir
          </button>
          <button class="im-btn im-btn-ghost im-btn-sm" id="btn-voucher-clear" style="display:none" title="Bekor">
            <i class="bi bi-x-circle-fill text-danger"></i>
          </button>
        </div>
      </div>
      <div id="voucher-info" style="display:none;margin-bottom:6px;padding:6px 10px;border-radius:8px;font-size:12px;background:#fef3c774;border:1px solid #f59e0b44;color:#92400e">
        <span id="voucher-info-text"></span>
      </div>

      <!-- To'lov turlari -->
      <div class="pay-tabs mb-2">
        <div class="pay-tab active" data-pay="naqd">💵 Naqd</div>
        <div class="pay-tab" data-pay="karta">💳 Kart-karta</div>
        <div class="pay-tab" data-pay="bank">🏦 Bank</div>
        <div class="pay-tab" data-pay="usd">🇺🇸 USD</div>
        <div class="pay-tab" data-pay="aralash">🔀 Aralash</div>
        <div class="pay-tab" data-pay="nasiya">📋 Nasiya</div>
      </div>

      <!-- To'lov inputlari -->
      <div id="pay-simple" class="pay-input visible">
        <input class="im-input num mb-2" type="number" id="pay-naqd-input"
               placeholder="Naqd summa (so'm)" min="0">
      </div>
      <div id="pay-karta-wrap" class="pay-input">
        <input class="im-input num mb-2" type="number" id="pay-karta-input"
               placeholder="Kart-karta summa (so'm)" min="0">
      </div>
      <div id="pay-bank-wrap" class="pay-input">
        <input class="im-input num mb-2" type="number" id="pay-bank-input"
               placeholder="Bank: o'tkazma yoki terminal (so'm)" min="0">
      </div>
      <div id="pay-usd-wrap" class="pay-input">
        <div class="d-flex gap-2 mb-1 align-items-center" style="background:rgba(230,126,34,.08);border:1px solid rgba(230,126,34,.3);border-radius:8px;padding:8px 10px">
          <span style="font-size:12px;color:#e67e22;font-weight:600">💲 1 USD =</span>
          <span style="font-size:15px;font-weight:700;color:#e67e22" id="usd-kurs-show"><?= number_format($usd_kurs,0,'.',',') ?> so'm</span>
          <span style="font-size:11px;color:#999;margin-left:auto">Admin belgilagan kurs</span>
        </div>
        <div class="d-flex gap-2 mb-2 align-items-center">
          <div style="flex:1">
            <label class="fs-xs text-muted mb-1">USD miqdori ($)</label>
            <input class="im-input num" type="number" id="pay-usd-input"
                   placeholder="0.00" step="0.01" min="0"
                   oninput="calcUsdSom()">
          </div>
          <div style="opacity:.6;font-size:20px;padding-top:18px">≈</div>
          <div style="flex:1">
            <label class="fs-xs text-muted mb-1">So'm ekvivalenti</label>
            <div class="im-input num fw-bold" id="usd-som-display"
                 style="background:var(--bg);display:flex;align-items:center;height:42px">0 so'm</div>
          </div>
        </div>
        <input type="hidden" id="pay-usd-kurs" value="<?= $usd_kurs ?>">
      </div>
      <div id="pay-nasiya-wrap" class="pay-input">
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:8px 10px;font-size:12px;margin-bottom:8px;color:#856404">
          ⚠️ Nasiya uchun mijoz tanlash shart!
        </div>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:4px">
          <label style="font-size:11px;font-weight:600;color:var(--muted);display:block;margin-bottom:4px">
            <i class="bi bi-calendar-date"></i> Qaytarish muddati *
          </label>
          <input type="date" id="nasiya-muddat"
                 class="im-input im-input-sm"
                 min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                 value="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                 style="max-width:160px">
          <div style="font-size:10px;color:var(--muted);margin-top:3px">
            Default: 30 kun (o'zgartirishingiz mumkin)
          </div>
        </div>
      </div>
      <div id="pay-aralash-wrap" class="pay-input">
        <div class="d-flex gap-2 mb-1">
          <div><label class="fs-xs text-muted">Naqd</label>
            <input class="im-input im-input-sm num" type="number" id="a-naqd" placeholder="0" min="0"></div>
          <div><label class="fs-xs text-muted">Kart-karta</label>
            <input class="im-input im-input-sm num" type="number" id="a-karta" placeholder="0" min="0"></div>
          <div><label class="fs-xs text-muted">Bank</label>
            <input class="im-input im-input-sm num" type="number" id="a-bank" placeholder="0" min="0"></div>
        </div>
      </div>

      <!-- Totals -->
      <hr style="border-color:var(--border);margin:10px 0 8px">
      <div class="total-row">
        <span class="text-muted fs-xs">Jami:</span>
        <span class="fw-bold num" id="total-jami">0 so'm</span>
      </div>
      <div class="total-row" id="chegirma-row" style="display:none">
        <span class="text-muted fs-xs" style="color:var(--danger)">Chegirma:</span>
        <span class="fw-bold num" style="color:var(--danger)" id="total-chegirma">-0 so'm</span>
      </div>
      <div class="total-row" id="umumiy-chegirma-row" style="display:none">
        <span class="fw-semibold fs-xs" style="color:#e67e22">
          <i class="bi bi-gift-fill"></i> Umumiy chegirma:
        </span>
        <span class="fw-bold num" style="color:#e67e22" id="total-umumiy-chegirma">-0 so'm</span>
      </div>
      <!-- Xizmat haqi (otsluga) — faqat stol buyurtmasida ko'rinadi -->
      <div class="total-row" id="xizmat-row" style="display:none">
        <label class="fw-semibold fs-xs d-flex align-items-center gap-1"
               style="color:#0d6efd;cursor:pointer;user-select:none" title="Xizmat haqini o'chirish/yoqish">
          <input type="checkbox" id="xizmat-toggle" checked style="cursor:pointer">
          <i class="bi bi-people-fill"></i> Xizmat haqi (<span id="xizmat-foiz-text">0</span>%):
        </label>
        <span class="fw-bold num" style="color:#0d6efd" id="total-xizmat">0 so'm</span>
      </div>
      <div class="total-row">
        <span class="fw-bold">To'lov:</span>
        <span class="total-main num" id="total-tolov">0 so'm</span>
      </div>
      <div class="total-row" id="qayta-pul-row" style="display:none;color:var(--success)">
        <span class="fw-semibold fs-sm">Qayta pul:</span>
        <span class="fw-bold num" id="total-qayta">0 so'm</span>
      </div>

        </div>
      </div>
      <div class="im-modal-footer">
        <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
        <button class="im-btn im-btn-success" id="btn-checkout" disabled>
          <i class="bi bi-check-circle-fill"></i> Sotishni tasdiqlash (Enter/F12)
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; // smena ?>

<!-- ──── Kassadan olish (Inkasso) modali ──────────────────────── -->
<div class="im-overlay" id="inkasso-modal">
  <div class="im-modal" style="max-width:420px;width:96%">
    <div class="im-modal-header" style="border-left:4px solid #7c3aed">
      <i class="bi bi-wallet2" style="color:#7c3aed;font-size:20px"></i>
      <span class="im-modal-title">Kassadan olish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <div class="im-form-group mb-3">
        <label class="im-label">To'lov turi</label>
        <select class="im-input" id="inkasso-tur">
          <option value="naqd">💵 Naqd so'm</option>
          <option value="usd">🇺🇸 USD</option>
        </select>
      </div>
      <div class="im-form-group mb-3">
        <label class="im-label">Summa (so'm)</label>
        <input class="im-input num" type="number" id="inkasso-summa"
               placeholder="Masalan: 500 000" min="0" step="1000" autofocus>
      </div>
      <div class="im-form-group mb-3">
        <label class="im-label">Izoh (ixtiyoriy)</label>
        <input class="im-input" type="text" id="inkasso-izoh"
               placeholder="Masalan: Boshliq oldi, oylik, ...">
      </div>
      <div style="background:rgba(124,58,237,.08);border:1px solid rgba(124,58,237,.25);border-radius:8px;padding:10px 12px;font-size:12.5px;color:#6d28d9">
        <i class="bi bi-info-circle"></i>
        Bu amal <b>harajat yoki zarar emas</b> — faqat kassadan pul chiqimi (inkasso).
        Smena hisobotida alohida ko'rsatiladi.
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-sm" id="btn-inkasso-save"
              style="background:#7c3aed;color:#fff;border-color:#7c3aed">
        <i class="bi bi-check-circle"></i> Saqlash
      </button>
    </div>
  </div>
</div>

<!-- ──── Chek modali ────────────────────────────────────────────────────────────── -->
<div class="im-overlay" id="chek-modal">
  <div class="im-modal">
    <div class="im-modal-header">
      <i class="bi bi-receipt" style="color:var(--success);font-size:20px"></i>
      <span class="im-modal-title">Sotuv amalga oshirildi!</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <div id="chek-content" class="chek-body"></div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Yopish (Enter)</button>
      <button class="im-btn im-btn-primary" id="btn-chek-print">
        <i class="bi bi-printer"></i> Chek chop etish
      </button>
    </div>
  </div>
</div>

<!-- ──── Smena yopish — TO'LIQ HISOBOT modali ────────────────────── -->
<div class="im-overlay" id="smena-close-modal">
  <div class="im-modal" style="max-width:600px;width:96%">
    <div class="im-modal-header">
      <i class="bi bi-stop-circle" style="color:var(--danger);font-size:20px"></i>
      <span class="im-modal-title">Smenani yopish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body" id="smena-close-body">
      <!-- Tasdiq bosqichi -->
      <div id="smena-confirm-step">
        <div class="im-card mb-3" style="background:var(--bg)">
          <div class="im-card-body p-3 text-center">
            <div class="row g-3">
              <div class="col-6">
                <div class="text-muted fs-xs">Jami sotuv</div>
                <div class="fw-bold" style="font-size:18px"><?= $bugun_stats['sotuv'] ?> ta</div>
              </div>
              <div class="col-6">
                <div class="text-muted fs-xs">Jami summa</div>
                <div class="fw-bold num" style="font-size:16px"><?= im_money($bugun_stats['summa']) ?> so'm</div>
              </div>
            </div>
          </div>
        </div>
        <p class="text-muted fs-sm text-center">Smenani yopishni tasdiqlaysizmi?</p>
      </div>
      <!-- Hisobot bosqichi (yopilgandan keyin) -->
      <div id="smena-hisobot-step" style="display:none">
        <div id="smena-hisobot-content"></div>
      </div>
    </div>
    <div class="im-modal-footer" id="smena-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-danger" id="btn-smena-close-confirm">
        <i class="bi bi-stop-circle-fill"></i> Smenani yopish
      </button>
    </div>
  </div>
</div>
<!-- ──── Yangi Mijoz qo'shish modali (POS) ──────────────────────────────── -->
<div class="im-overlay" id="new-mijoz-modal">
  <div class="im-modal" style="max-width:380px;width:96%">
    <div class="im-modal-header" style="border-left:4px solid #10b981">
      <i class="bi bi-person-plus-fill" style="color:#10b981;font-size:20px"></i>
      <span class="im-modal-title">Yangi Mijoz Qo'shish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <div class="im-form-group mb-3">
        <label class="im-label">Ism / Familiya *</label>
        <input class="im-input" type="text" id="nm-ism" placeholder="Masalan: Aliyev Jasur">
      </div>
      <div class="im-form-group">
        <label class="im-label">Telefon raqam</label>
        <input class="im-input" type="text" id="nm-tel" placeholder="+998 90 123 45 67">
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn" id="btn-nm-save" style="background:#10b981;color:#fff;border-color:#10b981">
        <i class="bi bi-check-circle-fill"></i> Qo'shish
      </button>
    </div>
  </div>
</div>

<!-- Stol ichidagi buyurtmalar — zal kartasini gavjum qilmaydigan ixcham tanlov -->
<div class="im-overlay" id="hall-order-picker-modal">
  <div class="im-modal" style="max-width:470px;width:96%">
    <div class="im-modal-header">
      <div class="hall-order-picker-icon" style="width:34px;height:34px"><i class="bi bi-grid-1x2-fill"></i></div>
      <div>
        <div class="im-modal-title" id="hall-order-picker-title">Stol buyurtmalari</div>
        <div class="text-muted" id="hall-order-picker-subtitle" style="font-size:11px"></div>
      </div>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body" style="background:var(--bg)">
      <div class="hall-order-picker-list" id="hall-order-picker-list"></div>
      <div class="hall-order-picker-actions" id="hall-order-picker-actions"></div>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js?v=<?= rawurlencode(im_VERSION) ?>"></script>
<script>
// ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
//  POS JavaScript
// ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
const SMENA_ID  = <?= $smena ? (int)$smena['id'] : 0 ?>;
const USD_KURS  = <?= $usd_kurs ?>;
const MAX_CH    = <?= $max_ch ?>;
const XIZMAT_FOIZ = <?= $xizmat_foiz ?>;   // otsluga foizi (admin sozlamasi)
const DUKON_NOMI= <?= json_encode($dukon_nomi) ?>;

// ════════════════════════════════════════════════════════════════════════
//  ZAL XARITASI — kirish ekrani (Poster/R-Keeper naqshi)
//  bosh/ochiq  -> stol tahrirlash (mahsulot qo'shish/o'chirish)
//  kassada     -> to'g'ridan-to'g'ri mavjud "Orderlar" mexanizmi orqali
//                 (loadPendingOrder) — to'lov oynasi o'zgarishsiz qoladi
// ════════════════════════════════════════════════════════════════════════
function fmtSom(n) { return Math.round(n).toLocaleString('uz-UZ'); }
function hallElapsed(mins) {
  if (mins === null || mins === undefined) return '';
  mins = parseInt(mins) || 0;
  if (mins < 1) return 'hozirgina';
  if (mins < 60) return mins + ' daq oldin';
  return Math.floor(mins/60) + ' soat ' + (mins%60) + ' daq oldin';
}

let hallStollarDukon = [], hallZonalarDukon = [];
let hallActiveZonaDukon = 'all';
try { hallActiveZonaDukon = localStorage.getItem('im_dukon_hall_zona') || 'all'; } catch {}

async function loadHallDukon() {
  const grid = document.getElementById('hall-stol-grid');
  if (!grid) return;
  try {
    const res = await fetch(im_BASE + 'dukon/ajax/get-hall.php');
    const d   = await res.json();
    if (d.status !== 'ok') return;
    hallStollarDukon = d.data.stollar || [];
    hallZonalarDukon = d.data.zonalar || [];
    renderHallZonaTabs();
    renderHallDukon(hallStollarDukon);
    renderStolsizDukon(d.data.stolsiz || []);
  } catch {}
}

function renderHallZonaTabs() {
  const wrap = document.getElementById('hall-zona-tabs');
  if (!wrap) return;
  const sanoq = {};
  let boshqa = 0;
  hallStollarDukon.forEach(s => { s.zona_id ? (sanoq[s.zona_id] = (sanoq[s.zona_id]||0)+1) : boshqa++; });

  const tabs = [{ key:'all', nomi:'Hammasi', rang:'', cnt:hallStollarDukon.length }];
  hallZonalarDukon.forEach(z => { if (sanoq[z.id]) tabs.push({ key:String(z.id), nomi:z.nomi, rang:z.rang, cnt:sanoq[z.id] }); });
  if (boshqa) tabs.push({ key:'none', nomi:'Boshqa', rang:'#94a3b8', cnt:boshqa });

  if (tabs.length <= 2) { wrap.style.display = 'none'; wrap.innerHTML = ''; return; }
  if (hallActiveZonaDukon !== 'all' && !tabs.some(t => t.key === hallActiveZonaDukon)) hallActiveZonaDukon = 'all';
  wrap.style.display = 'flex';
  wrap.innerHTML = tabs.map(t => {
    const on = t.key === hallActiveZonaDukon;
    const st = (on && t.rang) ? ` style="background:${im_esc(t.rang)};border-color:${im_esc(t.rang)};color:#fff"` : '';
    return `<button class="hall-zona-tab${on ? ' active' : ''}"${st} onclick="setHallZonaDukon('${im_esc_attr_js(t.key)}')">${im_esc(t.nomi)} <span class="cnt">${t.cnt}</span></button>`;
  }).join('');
}

function setHallZonaDukon(key) {
  hallActiveZonaDukon = key;
  try { localStorage.setItem('im_dukon_hall_zona', key); } catch {}
  renderHallZonaTabs();
  renderHallDukon(hallStollarDukon);
}

function renderHallDukon(stollar) {
  const grid = document.getElementById('hall-stol-grid');
  if (!stollar.length) {
    grid.innerHTML = '<div class="text-center text-muted p-4" style="grid-column:1/-1">Stol yo\'q — Dukon → Stollar bo\'limidan qo\'shing</div>';
    return;
  }
  let list = stollar;
  if (hallActiveZonaDukon === 'none')     list = stollar.filter(s => !s.zona_id);
  else if (hallActiveZonaDukon !== 'all') list = stollar.filter(s => String(s.zona_id) === hallActiveZonaDukon);
  if (!list.length) {
    grid.innerHTML = '<div class="text-center text-muted p-4" style="grid-column:1/-1"><i class="bi bi-funnel"></i> Bu zonada stol yo\'q</div>';
    return;
  }
  grid.innerHTML = list.map(s => {
    const nomiEsc = im_esc_attr_js(s.nomi || '');
    if (s.holat === 'bosh') {
      return `<div class="hall-stol-card bosh" onclick="openStolEdit(${s.id},'${nomiEsc}',null)">
        <div class="hall-stol-ic"><i class="bi bi-check-circle-fill"></i></div>
        <div class="hall-stol-nomi">${im_esc(s.nomi)}</div>
        <div class="hall-stol-holat">Bo'sh</div>
      </div>`;
    }
    const orders = Array.isArray(s.orders) && s.orders.length
      ? s.orders
      : [{id:s.order_id,status:s.order_status,olib_ketish:!!s.olib_ketish,holat:s.holat,summa:s.summa}];
    const mainCount = orders.filter(o => !o.olib_ketish).length;
    const takeCount = orders.filter(o => !!o.olib_ketish).length;
    const kassadaCount = orders.filter(o => o.holat === 'kassada').length;
    const chips = `${mainCount ? `<span class="hall-order-chip"><i class="bi bi-cup-hot-fill"></i> Stol</span>` : ''}
      ${takeCount ? `<span class="hall-order-chip takeaway"><i class="bi bi-bag-check-fill"></i> ${takeCount} olib ketish</span>` : ''}`;
    const cardClass = s.holat === 'ochiq' ? 'ochiq' : 'kassada';
    const holat = kassadaCount === orders.length ? 'To‘lovga tayyor' : (kassadaCount ? `${kassadaCount} ta kassada` : 'Ochiq');
    return `<div class="hall-stol-card ${cardClass}" onclick="openHallOrderPickerDukon(${s.id})">
      <div class="hall-stol-ic"><i class="bi bi-receipt"></i></div>
      <div class="hall-stol-nomi">${im_esc(s.nomi)}</div>
      <div class="hall-order-summary">${chips}</div>
      <div class="hall-stol-holat">${holat}</div>
      <div class="hall-stol-summa">${fmtSom(s.summa)} so'm</div>
      <div class="hall-card-open">Buyurtmalar <i class="bi bi-chevron-right"></i></div>
    </div>`;
  }).join('');
}

function openHallOrderPickerDukon(stolId) {
  const stol = hallStollarDukon.find(s => Number(s.id) === Number(stolId));
  if (!stol) return;
  const orders = Array.isArray(stol.orders) && stol.orders.length
    ? stol.orders
    : [{id:stol.order_id,status:stol.order_status,olib_ketish:!!stol.olib_ketish,holat:stol.holat,summa:stol.summa}];
  const nomiEsc = im_esc_attr_js(stol.nomi || '');
  // Yagona order ham "kassada" bo'lsa, to'g'ridan-to'g'ri o'tib ketmaymiz —
  // aks holda to'lov oynasi ochiladi va mijoz hisobni to'lagandan keyin
  // ustiga "Olib ketish" qo'shishning iloji qolmaydi. Bunday holatda ham
  // tanlash oynasi ochiladi, undagi "Yangi olib ketish" tugmasi orqali
  // qo'shish mumkin bo'lib qoladi.
  if (orders.length === 1 && orders[0].holat !== 'kassada') {
    const only = orders[0];
    dukonOpenPickedOrder(stol.id, stol.nomi || '', Number(only.id), false);
    return;
  }
  document.getElementById('hall-order-picker-title').textContent = stol.nomi || 'Stol';
  document.getElementById('hall-order-picker-subtitle').textContent = `${orders.length} ta faol buyurtma · ${fmtSom(stol.summa)} so'm`;
  document.getElementById('hall-order-picker-list').innerHTML = orders.map(o => {
    const take = !!o.olib_ketish;
    const kassada = o.holat === 'kassada';
    const state = kassada ? 'To‘lovga tayyor' : (o.status === 'oshpazda' || o.status === 'pishirilmoqda' ? 'Oshxonada' : 'Ochiq');
    return `<button class="hall-order-picker-row${take ? ' takeaway' : ''}" onclick="dukonOpenPickedOrder(${stol.id},'${nomiEsc}',${Number(o.id)},${kassada ? 'true' : 'false'})">
      <span class="hall-order-picker-icon"><i class="bi ${take ? 'bi-bag-check-fill' : 'bi-cup-hot-fill'}"></i></span>
      <span class="hall-order-picker-copy">
        <span class="hall-order-picker-name">${take ? 'Olib ketish' : 'Stol buyurtmasi'} #${Number(o.id)}</span>
        <span class="hall-order-picker-meta">${state}</span>
      </span>
      <span class="hall-order-picker-total">${fmtSom(o.summa)} so'm</span>
      <i class="bi bi-chevron-right text-muted"></i>
    </button>`;
  }).join('');
  const payableIds = orders.filter(o => o.holat === 'kassada').map(o => Number(o.id)).filter(Boolean);
  const hasMainOrder = orders.some(o => !o.olib_ketish);
  document.getElementById('hall-order-picker-actions').innerHTML = `
    ${hasMainOrder ? `<button class="im-btn im-btn-outline" style="color:#c2410c;border-color:#fdba74" onclick="NHModal.close('hall-order-picker-modal');dukonStartTakeaway(${stol.id},'${nomiEsc}')"><i class="bi bi-bag-plus-fill"></i> Yangi olib ketish</button>` : ''}
    ${payableIds.length > 1 ? `<button class="im-btn im-btn-success" onclick="NHModal.close('hall-order-picker-modal');dukonPayTogether(${stol.id},'${nomiEsc}',[${payableIds.join(',')}])"><i class="bi bi-receipt-cutoff"></i> Birga to‘lash · alohida cheklar</button>` : ''}`;
  NHModal.open('hall-order-picker-modal');
}

function dukonOpenPickedOrder(stolId, nomi, orderId, kassada) {
  NHModal.close('hall-order-picker-modal');
  if (kassada) openKassadaTable(orderId, nomi);
  else openStolEdit(stolId, nomi, orderId);
}

// Stolga bog'lanmaydigan faol buyurtmalar — faqat Dastavka.
function renderStolsizDukon(list) {
  const wrap = document.getElementById('hall-stolsiz-wrap');
  const grid = document.getElementById('hall-stolsiz-grid');
  list = (list || []).filter(o => !o.olib_ketish);
  if (!list.length) { wrap.style.display = 'none'; grid.innerHTML = ''; return; }
  wrap.style.display = '';
  grid.innerHTML = list.map(o => {
    const nomiEsc = im_esc_attr_js(o.mijoz_ism || '');
    const ic = o.olib_ketish ? 'bi-bag-check-fill' : 'bi-scooter';
    if (o.holat === 'kassada') {
      return `<div class="hall-stol-card kassada" onclick="openKassadaTable(${o.order_id},'${nomiEsc}')">
        <div class="hall-stol-ic"><i class="bi bi-receipt"></i></div>
        <div class="hall-stol-nomi">${im_esc(o.mijoz_ism)}</div>
        <div class="hall-stol-holat">Kassada</div>
        <div class="hall-stol-summa">${fmtSom(o.summa)} so'm</div>
      </div>`;
    }
    return `<div class="hall-stol-card ochiq" onclick="openStolEdit(null,'${nomiEsc}',${o.order_id})">
      <div class="hall-stol-ic"><i class="bi ${ic}"></i></div>
      <div class="hall-stol-nomi">${im_esc(o.mijoz_ism)}</div>
      <div class="hall-stol-holat">${o.order_status === 'pishirilmoqda' ? 'Pishirilmoqda' : 'Ochiq'}</div>
      <div class="hall-stol-vaqt">${hallElapsed(o.ochilgan_daqiqa)}</div>
      <div class="hall-stol-summa">${fmtSom(o.summa)} so'm</div>
    </div>`;
  }).join('');
}

function showHallDukon() {
  seDirty = false;
  document.getElementById('hall-view').style.display = 'flex';
  document.getElementById('stol-edit-view').classList.remove('visible');
  document.getElementById('pos-wrapper').style.display = 'none';
  loadHallDukon();
}

async function dukonDirectSale() {
  if (cart.length && (currentOrderId > 0 || currentBatchOrders.length)) {
    const ok = await NHConfirm.show({
      variant: 'warning',
      title: "Order savatidan chiqish",
      text: "Bu savat stol buyurtmasiga bog‘langan. To‘g‘ridan-to‘g‘ri savdoga o‘tishda uning mahalliy nusxasi tozalanadi.",
      sub: "Asl order va rezerv serverda saqlanib qoladi; uni zal xaritasidan qayta ochish mumkin.",
      confirmText: "Savatni yopib davom etish",
      cancelText: "Orderda qolish",
      btnIcon: 'bi-arrow-left-right'
    });
    if (!ok) return;
    resetCart();
  }
  // To'g'ridan-to'g'ri savdo — stol buyurtmasi emas
  resetOrderContext();
  saveCart();
  renderManbaChip();
  document.getElementById('hall-view').style.display = 'none';
  document.getElementById('pos-wrapper').style.display = 'flex';
}

function posBackToHall() {
  showHallDukon();
}

function openKassadaTable(orderId, nomi) {
  document.getElementById('hall-view').style.display = 'none';
  document.getElementById('pos-wrapper').style.display = 'flex';
  // Mavjud mexanizm — savatga yuklaydi, tolov oynasi o'zgarishsiz ishlaydi
  loadPendingOrder(orderId);
}

async function dukonPayTogether(stolId, nomi, orderIds) {
  const ids = [...new Set((orderIds || []).map(Number).filter(id => id > 0))];
  if (ids.length < 2) { NHToast.warning('Birga to‘lash uchun kamida 2 ta order kerak'); return; }
  if (cart.length) {
    const ok = await NHConfirm.show({
      variant: 'warning',
      title: 'Savat almashtiriladi',
      text: `Joriy savatda ${cart.length} ta qator bor. Tanlangan orderlar alohida cheklar bilan yuklanadi.`,
      sub: 'Joriy savatdagi saqlanmagan mahsulotlar olib tashlanadi.',
      confirmText: 'Orderlarni yuklash',
      cancelText: 'Bekor qilish',
      btnIcon: 'bi-receipt-cutoff'
    });
    if (!ok) return;
  }

  try {
    const details = [];
    for (const id of ids) {
      const r = await fetch(im_BASE + 'dukon/ajax/pending-orders?order_id=' + id);
      const d = await r.json();
      if (d.status !== 'ok' || !d.items?.length) throw new Error(`Order #${id} kassaga tayyor emas`);
      if ((parseInt(d.stol_id)||0) !== (parseInt(stolId)||0)) throw new Error('Orderlar bitta stolga tegishli emas');
      details.push({order_id:id, stol_id:parseInt(d.stol_id)||0, olib_ketish:!!d.olib_ketish, manba:d.mijoz_ism||nomi, items:d.items});
    }

    cart = [];
    details.forEach(meta => meta.items.forEach(item => {
      cart.push({
        id: parseInt(item.mahsulot_id), nomi:item.nomi, birlik:item.birlik||'dona',
        narx:parseFloat(item.narx)||0, soni:parseFloat(item.soni)||0, qoldiq:999,
        kampaniya_ch:0, ulg_min:parseInt(item.ulg_min)||0, ulg_narx:parseFloat(item.ulg_narx)||0,
        tannarx:0, individual_ch:0,
        set_id:item.set_id ? parseInt(item.set_id) : null, set_nomi:item.set_nomi||null,
        olib_ketish_soni:parseFloat(item.olib_ketish_soni)||0,
        source_order_id:meta.order_id,
        source_stol_id:meta.stol_id,
        source_olib_ketish:meta.olib_ketish
      });
    }));

    currentOrderId = 0;
    currentOrderStolId = parseInt(stolId)||0;
    currentOrderOlibKetish = false;
    currentBatchOrders = details.map(d => ({
      order_id:d.order_id, stol_id:d.stol_id, olib_ketish:d.olib_ketish, manba:d.manba
    }));
    currentOrderManba = `${nomi} — ${currentBatchOrders.length} ta alohida chek`;
    posContextReady = true;
    const xcb = document.getElementById('xizmat-toggle');
    if (xcb) xcb.checked = true;
    document.getElementById('hall-view').style.display = 'none';
    document.getElementById('pos-wrapper').style.display = 'flex';
    renderManbaChip();
    renderCart();
    NHToast.success(`${ids.length} ta order birga to‘lash uchun yuklandi`);
  } catch(e) {
    NHToast.error(e.message || 'Orderlarni birga yuklab bo‘lmadi');
  }
}

// ──── STOL TAHRIRLASH (kassir mahsulot qo'shib/o'chira oladi) ───────────
let seStolId = null, seOrderId = 0, seStolNomi = '', seCart = {}, seDirty = false, seProducts = [], seOlibKetish = false;
let seActiveKat = 0, seSetsCache = [];

function seEffN(it) { return (it.ulg_min > 0 && it.soni >= it.ulg_min && it.ulg_narx > 0) ? it.ulg_narx : it.narx; }

function seResetCatalog() {
  seActiveKat = 0;
  document.querySelectorAll('.se-cat-btn').forEach(b => b.classList.toggle('active', b.dataset.kat === '0'));
}

async function openStolEdit(stolId, nomi, orderId) {
  seStolId = stolId; seStolNomi = nomi; seOrderId = 0; seCart = {}; seDirty = false; seOlibKetish = false;
  if (orderId) {
    try {
      const res = await fetch(im_BASE + 'sotuvchi/ajax/get-active-orders.php');
      const d   = await res.json();
      const found = (d.orders||[]).find(o => o.order_id === orderId);
      // Mavjud olib_ketish bayrog'ini saqlab qolamiz — aks holda kassir
      // mahsulot qo'shib saqlaganda bu belgi bilinmasdan o'chib qolar edi.
      if (found) {
        seOrderId = found.order_id; seCart = found.cart || {}; seOlibKetish = !!found.olib_ketish;
        if (!seOlibKetish) Object.values(seCart).forEach(i => { i.olib_ketish_soni = 0; });
      }
      else NHToast.warning('Buyurtma topilmadi — stol bo\'sh ochildi');
    } catch {
      // MUHIM: bu xatoni jim yutmaymiz. Aks holda stol BO'SH savat bilan
      // ochiladi va kassir buyurtma yo'qdek o'ylaydi (aslida yuklanmagan).
      NHToast.error("Stol buyurtmasini yuklab bo'lmadi — sahifani yangilang");
      return;
    }
  }
  document.getElementById('hall-view').style.display = 'none';
  document.getElementById('stol-edit-view').classList.add('visible');
  document.getElementById('se-stol-nomi').textContent = seOlibKetish ? nomi + ' — Olib ketish' : nomi;
  const takeBtn = document.getElementById('se-takeaway-btn');
  if (takeBtn) takeBtn.style.display = (seStolId && seOrderId && !seOlibKetish) ? '' : 'none';
  document.getElementById('se-search').value = '';
  seResetCatalog();
  seLoadProducts();
  seRenderCart();
}

// Stolsiz buyurtma ochish — faqat Dastavka.
// "To'g'ridan-to'g'ri sotuv" dan farqi: bu HAQIQIY buyurtma — oshxonaga
// boradi, pauza qilinadi va "Dastavka buyurtmalari" ro'yxatida turadi.
// Oshpaz tayyorlaydigan taomlar (a-la-carte) faqat shu yo'l bilan
// sotilishi mumkin, chunki ularning vitrinada qoldig'i yo'q.
function dukonOpenQuick(label) {
  seStolId = null; seStolNomi = label; seOrderId = 0;
  seCart = {}; seDirty = false;
  seOlibKetish = false;
  document.getElementById('hall-view').style.display = 'none';
  document.getElementById('stol-edit-view').classList.add('visible');
  document.getElementById('se-stol-nomi').textContent = label;
  document.getElementById('se-search').value = '';
  const takeBtn = document.getElementById('se-takeaway-btn');
  if (takeBtn) takeBtn.style.display = 'none';
  seResetCatalog();
  seLoadProducts();
  seRenderCart();
}

async function seBackToHall() {
  if (seDirty && Object.keys(seCart).length) {
    const ok = await NHConfirm.show({
      variant: 'warning',
      title: "Saqlanmagan o'zgarishlar",
      text: "Zal xaritasiga qaytsangiz, savatdagi yangi o'zgarishlar yo'qolishi mumkin.",
      sub: "Buyurtmani saqlab qolish uchun avval yuboring yoki pauzaga qo'ying.",
      confirmText: "Zalga qaytish",
      cancelText: "Savatda qolish",
      btnIcon: 'bi-grid-3x3-gap-fill'
    });
    if (!ok) return;
  }
  showHallDukon();
}

async function seLoadProducts(q) {
  q = q || '';
  if (seActiveKat === '__sets__') { seLoadSets(q); return; }
  const grid = document.getElementById('se-prod-grid');
  grid.innerHTML = '<div class="text-center text-muted p-3" style="grid-column:1/-1">Yuklanmoqda...</div>';
  try {
    const res = await fetch(im_BASE + `sotuvchi/ajax/get-products.php?q=${encodeURIComponent(q)}&kat=${seActiveKat}`);
    const d   = await res.json();
    seProducts = (d.status === 'ok') ? (d.data||[]) : [];
    seRenderProducts();
  } catch { grid.innerHTML = '<div class="text-center text-muted p-3" style="grid-column:1/-1">Yuklab bo\'lmadi</div>'; }
}

function seFilterKat(kat, btn) {
  seActiveKat = kat;
  document.querySelectorAll('.se-cat-btn').forEach(b => b.classList.remove('active'));
  btn?.classList.add('active');
  seLoadProducts(document.getElementById('se-search').value.trim());
}

function seRenderProducts() {
  const grid = document.getElementById('se-prod-grid');
  if (!seProducts.length) { grid.innerHTML = '<div class="text-center text-muted p-3" style="grid-column:1/-1">Mahsulot topilmadi</div>'; return; }
  grid.innerHTML = seProducts.map(p => {
    const inCart = !!seCart[p.id];
    const qadam = qtyStep(p);
    return `<div class="se-prod-card ${inCart?'in-cart':''}" onclick="seToggleCart(${p.id})">
      <div class="se-prod-name">${im_esc(p.nomi)}${inCart?' <b>×'+qtyLabel(seCart[p.id].soni)+'</b>':''}</div>
      <div class="se-prod-narx">${fmtSom(p.narx)} so'm</div>
      ${qadam < 1 ? `<div class="p-fractions" onclick="event.stopPropagation()" style="padding:5px 0 0">
        ${qadam <= .25 ? `<button class="p-fraction-btn" onclick="seToggleCart(${p.id},.25)">+¼</button>` : ''}
        ${qadam <= .5 ? `<button class="p-fraction-btn" onclick="seToggleCart(${p.id},.5)">+½</button>` : ''}
        <button class="p-fraction-btn" onclick="seToggleCart(${p.id},1)">+1</button>
      </div>` : ''}
    </div>`;
  }).join('');
}

function seToggleCart(pid, amount) {
  const p = seProducts.find(x => x.id == pid); if (!p) return;
  const k = String(pid);
  const baseQadam = qtyStep(p);
  const qadam = amount > 0 ? roundQty(amount) : baseQadam;
  if (seCart[k]) {
    seCart[k].soni = Math.min(roundQty(seCart[k].soni + qadam), parseFloat(p.qoldiq));
    seCart[k].olib_ketish_soni = seOlibKetish ? seCart[k].soni : 0;
  } else {
    seCart[k] = {
      mahsulot_id: pid, set_id: null, set_nomi: null, _k: k,
      nomi: p.nomi, narx: parseFloat(p.narx),
      ulg_min: parseInt(p.ulg_min)||0, ulg_narx: parseFloat(p.ulg_narx)||0,
      soni: qadam, birlik: p.birlik||'', sotuv_qadami:baseQadam, qoldiq: parseFloat(p.qoldiq), locked_soni: 0,
      olib_ketish_soni: seOlibKetish ? qadam : 0
    };
  }
  seDirty = true;
  seRenderCart(); seRenderProducts();
}

async function seLoadSets(q = '') {
  const grid = document.getElementById('se-prod-grid');
  grid.innerHTML = '<div class="text-center text-muted p-3" style="grid-column:1/-1">Setlar yuklanmoqda...</div>';
  try {
    const res = await fetch(im_BASE + 'dukon/ajax/set-list.php');
    const d = await res.json();
    seSetsCache = d.status === 'ok' ? (d.data?.list || []) : [];
  } catch { seSetsCache = []; }

  const badge = document.getElementById('se-set-count');
  if (badge) badge.textContent = seSetsCache.length ? `(${seSetsCache.length})` : '';
  const ql = q.trim().toLowerCase();
  seRenderSets(ql ? seSetsCache.filter(s => s.nomi.toLowerCase().includes(ql)) : seSetsCache);
}

function seRenderSets(list) {
  const grid = document.getElementById('se-prod-grid');
  if (!list.length) {
    grid.innerHTML = '<div class="text-center text-muted p-3" style="grid-column:1/-1"><i class="bi bi-gift"></i><br>Set topilmadi</div>';
    return;
  }
  grid.innerHTML = list.map(set => {
    const items = set.items.map(it =>
      `<div>· ${im_esc(it.nomi)} <b>×${qtyLabel(it.soni)}</b></div>`
    ).join('');
    const unavail = !set.available;
    return `<div class="se-prod-card ${unavail ? 'sold-out' : ''}"
      style="border-color:${im_esc(set.rang)}" onclick="${unavail ? '' : `seAddSetToCart(${set.id})`}">
      <div class="se-prod-name" style="color:${im_esc(set.rang)}">🎁 ${im_esc(set.nomi)}</div>
      <div class="se-set-items">${items}</div>
      <div class="se-prod-narx" style="color:${im_esc(set.rang)}">${fmtSom(set.narxi)} so'm</div>
      ${unavail ? '<div style="font-size:10px;color:var(--danger);font-weight:700">⚠️ Qoldiq yetarli emas</div>' : ''}
    </div>`;
  }).join('');
}

function seAddSetToCart(setId) {
  const set = seSetsCache.find(s => s.id === setId);
  if (!set || !set.available) { NHToast.warning('Qoldiq yetarli emas'); return; }

  const aslYigindi = set.items.reduce((sum, it) => sum + it.sotuv_narxi * it.soni, 0);
  const koeff = aslYigindi > 0 ? set.narxi / aslYigindi : 1;
  set.items.forEach(it => {
    const k = it.mahsulot_id + '_s' + set.id;
    const propNarx = aslYigindi > 0
      ? Math.round(it.sotuv_narxi * koeff)
      : Math.round(set.narxi / Math.max(1, set.items.length));
    if (seCart[k]) {
      seCart[k].soni += it.soni;
      if (seOlibKetish) seCart[k].olib_ketish_soni = seCart[k].soni;
    } else {
      seCart[k] = {
        mahsulot_id: it.mahsulot_id, set_id: set.id, set_nomi: set.nomi, _k: k,
        nomi: it.nomi, narx: propNarx, ulg_min: 0, ulg_narx: 0,
        soni: it.soni, birlik: it.birlik || '', sotuv_qadami:parseFloat(it.sotuv_qadami)||1, qoldiq: it.qoldiq || 9999,
        locked_soni: 0, olib_ketish_soni: seOlibKetish ? it.soni : 0
      };
    }
  });
  seDirty = true;
  NHToast.success(`🎁 «${set.nomi}» savatga qo'shildi`);
  seRenderCart();
}

function dukonStartTakeaway(stolId, nomi) {
  if (!stolId) { NHToast.warning("Olib ketish buyurtmasi stolga biriktirilishi shart"); return; }
  seStolId = stolId; seStolNomi = nomi; seOrderId = 0;
  seCart = {}; seDirty = false; seOlibKetish = true;
  document.getElementById('hall-view').style.display = 'none';
  document.getElementById('stol-edit-view').classList.add('visible');
  document.getElementById('se-stol-nomi').textContent = nomi + ' — Olib ketish';
  document.getElementById('se-search').value = '';
  const takeBtn = document.getElementById('se-takeaway-btn');
  if (takeBtn) takeBtn.style.display = 'none';
  seResetCatalog();
  seLoadProducts();
  seRenderCart();
}

function dukonStartTakeawayFromCurrent() {
  if (!seStolId || seOlibKetish) return;
  if (!seOrderId || seDirty) {
    NHToast.warning("Avval asosiy stol buyurtmasini saqlang");
    return;
  }
  dukonStartTakeaway(seStolId, seStolNomi);
}

// ──── Stol tahrirlash ekranida kamaytirish ────────────────────────
// POS savatidagi kamaytirish bilan BIR XIL ishlaydi: avval tasdiqlash,
// so'ng server. Ilgari bu yerda tasdiqlash ham, xomashyo qaytarish ham
// yo'q edi — kassir beixtiyor bosib qo'ysa buyurtma jimgina kamayardi.
async function seKamaytir(it, newQty) {
  const savol = newQty <= 0
    ? `«${it.nomi}» buyurtmadan butunlay olib tashlansinmi?`
    : `«${it.nomi}»: ${qtyLabel(it.soni)} → ${qtyLabel(newQty)} ga kamaytirilsinmi?`;
  const ok = await NHConfirm.show({
    variant: 'warning',
    title: newQty <= 0 ? "Mahsulotni olib tashlash" : "Miqdorni kamaytirish",
    text: savol,
    sub: "Oshpaz tayyorlagan bo'lsa, tegishli xomashyo qoldiqqa qaytariladi.",
    confirmText: newQty <= 0 ? "Olib tashlash" : "Kamaytirish",
    btnIcon: newQty <= 0 ? 'bi-cart-dash-fill' : 'bi-dash-circle-fill'
  });
  if (!ok) return false;

  // Hali saqlanmagan (serverda yo'q) buyurtma — faqat mahalliy o'zgarish
  if (!seOrderId) return true;

  const fd = new URLSearchParams({
    order_id: seOrderId, mahsulot_id: it.mahsulot_id, set_id: it.set_id || 0,
    yangi_soni: Math.max(0, newQty),
  });
  try {
    const r = await fetch(im_BASE + 'dukon/ajax/order-item-kamaytir.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.status !== 'ok') { NHToast.error(d.msg || 'Xatolik'); return false; }
    NHToast.success(d.msg);
    return true;
  } catch {
    NHToast.error('Tarmoq xatosi — kamaytirilmadi');
    return false;
  }
}

async function seChangeQty(k, delta) {
  const it = seCart[k]; if (!it) return;
  const change = delta * qtyStep(it);
  const newQty = roundQty(it.soni + change);

  if (change < 0) {
    // Oshpaz tayyorlagan qism ham kamayishi mumkin — xomashyo qaytariladi
    if (!await seKamaytir(it, Math.max(0, newQty))) return;
    if (newQty <= 0) delete seCart[k];
    else {
      it.soni = newQty;
      if (it.locked_soni > newQty) it.locked_soni = newQty;
      it.olib_ketish_soni = seOlibKetish ? newQty : 0;
    }
  } else {
    it.soni = newQty;
    it.olib_ketish_soni = seOlibKetish ? newQty : 0;
  }
  seDirty = true;
  seRenderCart(); seRenderProducts();
}

async function seRemoveItem(k) {
  const it = seCart[k]; if (!it) return;
  if (!await seKamaytir(it, 0)) return;
  delete seCart[k];
  seDirty = true;
  seRenderCart(); seRenderProducts();
}

function seRenderCart() {
  const items = Object.values(seCart);
  const total = items.reduce((s,i) => s + seEffN(i)*i.soni, 0);
  const body  = document.getElementById('se-cart-body');
  document.getElementById('se-total').textContent = fmtSom(total) + " so'm";
  document.getElementById('se-save-btn').disabled = !items.length;
  document.getElementById('se-checkout-btn').disabled = !items.length;
  if (!items.length) {
    body.innerHTML = '<div class="text-center text-muted p-4"><i class="bi bi-cart-x" style="font-size:32px;opacity:.3"></i><p class="mt-2">Savatcha bo\'sh</p></div>';
    return;
  }
  body.innerHTML = items.map(i => {
    const k = i._k;
    const en = seEffN(i), lock = parseFloat(i.locked_soni||0);
    const lb = lock>0 ? `<span class="im-badge im-badge-warning" style="font-size:9px">🍳 ${qtyLabel(lock)} oshpazda</span>` : '';
    const okb = seOlibKetish ? `<span class="im-badge" style="background:#ffedd5;color:#9a3412;font-size:9px">🛍️ Qadoqlanadi</span>` : '';
    const sb = i.set_id ? `<span class="im-badge" style="background:#fef9c3;color:#b8860b;font-size:9px">🎁 ${im_esc(i.set_nomi)||'Set'}</span>` : '';
    return `<div class="se-cart-item">
      <div style="flex:1;min-width:0">
        <div style="font-size:12px;font-weight:600">${im_esc(i.nomi)} ${sb}${lb}${okb}</div>
        <div class="text-muted" style="font-size:11px">${fmtSom(en)}×${qtyLabel(i.soni)} = <strong>${fmtSom(en*i.soni)}</strong> so'm</div>
      </div>
      <div class="d-flex align-items-center gap-1">
        <button class="im-btn im-btn-icon im-btn-sm im-btn-outline" onclick="seChangeQty('${k}',-1)">−</button>
        <span style="min-width:22px;text-align:center;font-weight:700">${qtyLabel(i.soni)}</span>
        <button class="im-btn im-btn-icon im-btn-sm im-btn-outline" onclick="seChangeQty('${k}',1)">+</button>
      </div>
      <button class="im-btn im-btn-icon im-btn-sm im-btn-ghost text-danger" onclick="seRemoveItem('${k}')"><i class="bi bi-trash3"></i></button>
    </div>`;
  }).join('');
}

async function seSaveInternal(action) {
  const items = Object.values(seCart);
  if (!items.length) return null;
  const fd = new FormData();
  fd.append('action', action); fd.append('order_id', seOrderId || 0);
  fd.append('mijoz_ism', seStolNomi); fd.append('stol_id', seStolId || ''); fd.append('olib_ketish', seOlibKetish?1:0); fd.append('izoh', '');
  // Olib ketish alohida order; undagi barcha qatorlar qadoqlanadi.
  fd.append('items', JSON.stringify(items.map(i => ({
    mahsulot_id: i.mahsulot_id, set_id: i.set_id||0, soni: i.soni, narx: seEffN(i),
    locked_soni: i.locked_soni||0, olib_ketish_soni: seOlibKetish ? i.soni : 0
  }))));
  const res = await fetch(im_BASE + 'sotuvchi/ajax/order-save.php', {method:'POST', body:fd});
  return await res.json();
}

async function seSave() {
  const btn = document.getElementById('se-save-btn'); btn.disabled = true;
  const d = await seSaveInternal('hold');
  btn.disabled = false;
  if (!d || d.status !== 'ok') { NHToast.error(d?.msg || 'Xatolik'); return; }
  NHToast.success(d.msg);
  seDirty = false;
  showHallDukon();
}

async function seCheckout() {
  const btn = document.getElementById('se-checkout-btn'); btn.disabled = true;
  const d = await seSaveInternal('create');
  btn.disabled = false;
  if (!d || d.status !== 'ok') { NHToast.error(d?.msg || 'Xatolik'); return; }
  seDirty = false;
  const oid = d.data?.order_id;

  // Buyurtmada oshpaz tayyorlaydigan taom bo'lsa u oshxonaga ketadi.
  // Bunday holatda to'lovga O'TKAZMAYMIZ: taom hali pishirilmagan, sotuv
  // ham serverda rad etiladi (sotuv-save.php). Kassirga aniq aytamiz.
  const st = d.data?.status;
  if (st === 'oshpazda' || st === 'pishirilmoqda') {
    NHToast.warning("Buyurtma oshxonaga yuborildi. Oshpaz tayyorlagach to'lovni qabul qiling.", 4000);
    showHallDukon();
    return;
  }

  // Hisob-kitobga o'tamiz — mavjud to'lov mexanizmi orqali
  document.getElementById('stol-edit-view').classList.remove('visible');
  document.getElementById('pos-wrapper').style.display = 'flex';
  if (oid) loadPendingOrder(oid);
}

document.getElementById('se-search')?.addEventListener('input', function(){
  clearTimeout(window._seSearchT);
  window._seSearchT = setTimeout(() => seLoadProducts(this.value.trim()), 350);
});

loadHallDukon();
setInterval(() => {
  const hv = document.getElementById('hall-view');
  if (hv && hv.style.display !== 'none') loadHallDukon();
}, 8000);

// USD ↔ So'm hisoblash (real vaqtda)
// USD ↔ So'm hisoblash (real vaqtda)
function calcUsdSom() {
  const usd = parseFloat(document.getElementById('pay-usd-input')?.value || 0);
  const som = usd * USD_KURS;
  const el  = document.getElementById('usd-som-display');
  if (el) {
    el.textContent = som > 0 ? som.toLocaleString('uz-UZ') + ' so\'m' : '0 so\'m';
    el.style.color = som > 0 ? 'var(--success)' : '';
  }
}
// USD tavsiya: chek yig'indisi o'zgarganda kerakli USD ni ko'rsat
function updateUsdHint(tolovSumma) {
  const tavsiya = (tolovSumma / USD_KURS).toFixed(2);
  const inp = document.getElementById('pay-usd-input');
  if (inp && document.getElementById('pay-usd-wrap')?.classList.contains('visible')) {
    inp.placeholder = `≈ $${tavsiya} kerak`;
  }
}

const AJAX = {
  smenaOpen:  window.im_BASE + 'dukon/ajax/smena-open',
  smenaClose: window.im_BASE + 'dukon/ajax/smena-close',
  mahPos:     window.im_BASE + 'dukon/ajax/mah-pos',
  mijozSearch:window.im_BASE + 'dukon/ajax/mijoz-search',
  sotuvSave:  window.im_BASE + 'dukon/ajax/sotuv-save',
  inkasso:    window.im_BASE + 'dukon/ajax/inkasso-save',
  setList:    window.im_BASE + 'dukon/ajax/set-list.php',
};

// ──── Smena ochish ──────────────────────────────────────────────────────────────────────────────────
document.getElementById('btn-smena-open')?.addEventListener('click', async () => {
  const res = await IMAjax.post(AJAX.smenaOpen, { ochish_naqd: 0 });
  if (res.status === 'ok') { NHToast.success(res.msg); setTimeout(() => location.reload(), 500); }
  else NHToast.error(res.msg);
});

// ──── Soat ──────────────────────────────────────────────────────────────────────────────────────────────────
function updateClock() {
  const el = document.getElementById('clock');
  if (el) el.textContent = new Date().toLocaleTimeString('uz-UZ',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
setInterval(updateClock, 1000); updateClock();

// ╔══════════════════════════════════════════════════════════════════════════════════╗
// ║  BARCODE SCANNER — TO'LIQ QAYTA YOZILGAN                                       ║
// ║  Ikkita holat qo'llab-quvvatlanadi:                                             ║
// ║  1. pos-search fokusda → skaner to'g'ridan yozadi → Enter → handleBarcode()    ║
// ║  2. pos-search fokusda emas → global listener ushlab qoladi → handleBarcode()   ║
// ║  Ikki yo'l ham bir xil handleBarcode() chaqiradi — race condition YO'Q          ║
// ╚══════════════════════════════════════════════════════════════════════════════════╝
let _scanBuf   = '';
let _scanTimer = null;
let _searching = false; // bir vaqtda faqat bitta AJAX

function _clearBuf() { _scanBuf = ''; clearTimeout(_scanTimer); }

// ─── Asosiy qidiruv & qo'shish funksiyasi ───────────────────────────────────────────────
async function handleBarcode(code) {
  code = (code || '').trim();
  if (!code)      { if (posSearch) { posSearch.value = ''; } return; }
  if (_searching) { NHToast.warning('Iltimos kuting...', 600); return; }

  _searching = true;
  clearTimeout(searchTimer);       // debounce ni bekor qil
  searchBox.style.display = 'none'; // eski dropdown ni yashir

  try {
    const res = await IMAjax.get(AJAX.mahPos, { q: code });
    if (res.status !== 'ok' || !res.data?.list?.length) {
      NHToast.warning('Mahsulot topilmadi: ' + code, 1800);
      if (posSearch) { posSearch.value = code; posSearch.select(); }
      searchBox.innerHTML = '<div class="search-item text-muted">Topilmadi</div>';
      searchBox.style.display = 'block';
      return;
    }
    const list = res.data.list;
    const exact = list.find(m => m.barcode && String(m.barcode).toLowerCase() === code.toLowerCase());
    const pick  = exact ?? (list.length === 1 ? list[0] : null);
    if (pick) {
      addToCart(pick);
      if (posSearch) { posSearch.value = ''; posSearch.focus(); }
      searchBox.style.display = 'none';
    } else {
      // Bir nechta natija — dropdown
      if (posSearch) { posSearch.value = code; posSearch.focus(); }
      renderSearchResults(list);
      searchBox.style.display = 'block';
    }
  } catch(err) {
    NHToast.error('Tarmoq xatosi');
  } finally {
    _searching = false;
  }
}

// ─── Global keydown — FAQAT pos-search FOKUSDA EMAS holatda ─────────────────────────────
// pos-search fokusda bo'lsa, u o'zi ishlaydi (posSearch keydown listener orqali)
document.addEventListener('keydown', function(e) {
  const active = document.activeElement;

  // Boshqa input/textarea/select (modal, vozvrat, h.k.) → tegmaymiz
  if (active && active !== posSearch &&
      (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT')) {
    _clearBuf(); return;
  }
  // Modifier kombinatsiyalari → tegmaymiz
  if (e.ctrlKey || e.altKey || e.metaKey) return;

  // pos-search o'zi fokusda → uning keydown listener qabul qiladi, biz aralashmaymiz
  if (active === posSearch) { _clearBuf(); return; }

  // ────── pos-search FOKUSDA EMAS ──────
  if (e.key === 'Escape') {
    _clearBuf();
    if (posSearch) posSearch.value = '';
    searchBox && (searchBox.style.display = 'none');
    return;
  }
  if (e.key === 'Enter') {
    const code = _scanBuf;
    _clearBuf();
    if (code.length >= 3) { e.preventDefault(); handleBarcode(code); }
    return;
  }
  if (e.key === 'Backspace') { _scanBuf = _scanBuf.slice(0, -1); return; }
  if (e.key.length === 1) {
    _scanBuf += e.key;
    clearTimeout(_scanTimer);
    _scanTimer = setTimeout(_clearBuf, 100); // 100ms = skaner tezligi chegarasi
  }
});


// ──── Savat state ────────────────────────────────────────────────────────────────────────────────────
// localStorage kaliti smena ID ga bog'liq — yangi smena ochilsa eski savat o'chiriladi.
// Savat bilan uning order manbasi BIRGA saqlanadi; aks holda refreshdan keyin
// rezervlangan stol orderi to'g'ridan-to'g'ri savdo sifatida sotilib ketishi mumkin.
const CART_KEY = `pos_cart_smena_${SMENA_ID}`;
const POS_STATE_VERSION = 2;

function saveCart() {
  try {
    localStorage.setItem(CART_KEY, JSON.stringify({
      version: POS_STATE_VERSION,
      cart,
      context: {
        orderId: currentOrderId,
        stolId: currentOrderStolId,
        olibKetish: currentOrderOlibKetish,
        manba: currentOrderManba,
        batchOrders: currentBatchOrders
      }
    }));
  } catch(e) {}
}
function loadPosState() {
  try {
    const raw = localStorage.getItem(CART_KEY);
    if (!raw) return {cart:[], context:null, legacyDiscarded:false};
    const parsed = JSON.parse(raw);
    // Eski format faqat massiv edi va uning qaysi orderdan kelgani noma'lum.
    // Uni direct sale deb taxmin qilish qoldiqni ikki marta yechishi mumkin.
    if (Array.isArray(parsed)) {
      localStorage.removeItem(CART_KEY);
      return {cart:[], context:null, legacyDiscarded:parsed.length > 0};
    }
    if (!parsed || parsed.version !== POS_STATE_VERSION || !Array.isArray(parsed.cart)) {
      localStorage.removeItem(CART_KEY);
      return {cart:[], context:null, legacyDiscarded:true};
    }
    return {cart:parsed.cart, context:parsed.context || null, legacyDiscarded:false};
  } catch(e) {
    try { localStorage.removeItem(CART_KEY); } catch(_) {}
    return {cart:[], context:null, legacyDiscarded:true};
  }
}
function clearCartStorage() {
  try { localStorage.removeItem(CART_KEY); } catch(e) {}
}

const restoredPosState = loadPosState();
let cart = restoredPosState.cart;
let selectedMijoz = null;
let currentKat = 0;
let currentPayMode = 'naqd';
let selectedVoucher = null; // {kod, chegirma, yangi_summa, xabar}
let currentOrderId = parseInt(restoredPosState.context?.orderId) || 0;
let currentOrderStolId = parseInt(restoredPosState.context?.stolId) || 0;
let currentOrderOlibKetish = !!restoredPosState.context?.olibKetish;
let currentOrderManba = String(restoredPosState.context?.manba || '');
let currentBatchOrders = Array.isArray(restoredPosState.context?.batchOrders)
  ? restoredPosState.context.batchOrders.filter(o => parseInt(o.order_id) > 0)
  : [];
let posContextReady = !(currentOrderId > 0 || currentBatchOrders.length);

function resetOrderContext() {
  currentOrderId = 0;
  currentOrderStolId = 0;
  currentOrderOlibKetish = false;
  currentOrderManba = '';
  currentBatchOrders = [];
  posContextReady = true;
}

async function validateRestoredPosContext() {
  const metas = currentBatchOrders.length
    ? currentBatchOrders
    : (currentOrderId ? [{order_id:currentOrderId, stol_id:currentOrderStolId, olib_ketish:currentOrderOlibKetish}] : []);
  if (!metas.length) { posContextReady = true; return true; }
  try {
    const freshCart = [];
    for (const meta of metas) {
      const r = await fetch(im_BASE + 'dukon/ajax/pending-orders?order_id=' + parseInt(meta.order_id));
      const d = await r.json();
      if (d.status !== 'ok'
          || (parseInt(d.stol_id)||0) !== (parseInt(meta.stol_id)||0)
          || !!d.olib_ketish !== !!meta.olib_ketish) {
        throw new Error('Order konteksti eskirgan');
      }
      (d.items || []).forEach(item => freshCart.push({
        id:parseInt(item.mahsulot_id), nomi:item.nomi, birlik:item.birlik||'dona',
        narx:parseFloat(item.narx)||0, soni:parseFloat(item.soni)||0, qoldiq:999,
        kampaniya_ch:0, ulg_min:parseInt(item.ulg_min)||0, ulg_narx:parseFloat(item.ulg_narx)||0,
        tannarx:0, individual_ch:0,
        set_id:item.set_id ? parseInt(item.set_id) : null, set_nomi:item.set_nomi||null,
        olib_ketish_soni:parseFloat(item.olib_ketish_soni)||0,
        ...(currentBatchOrders.length ? {
          source_order_id:parseInt(meta.order_id), source_stol_id:parseInt(meta.stol_id)||0,
          source_olib_ketish:!!meta.olib_ketish
        } : {})
      }));
    }
    if (!freshCart.length) throw new Error('Order savati bo‘sh');
    cart = freshCart;
    posContextReady = true;
    renderManbaChip();
    renderCart();
    return true;
  } catch(e) {
    resetCart();
    renderManbaChip();
    NHToast.error("Saqlangan order boshqa kassada yopilgan yoki o‘zgargan. Xavfsizlik uchun mahalliy savat tozalandi.", 5000);
    return false;
  }
}

// To'lov oynasidagi manba chipini yangilash
function renderManbaChip() {
  const el = document.getElementById('manba-chip-text');
  if (!el) return;
  el.textContent = currentOrderManba || "To'g'ridan-to'g'ri savdo";
  const box = document.getElementById('manba-chip');
  if (box) box.querySelector('i').className = currentOrderManba
    ? (currentOrderOlibKetish ? 'bi bi-bag-check-fill' : 'bi bi-geo-alt-fill')
    : 'bi bi-cart-fill';
}


// ──── Mahsulot qidirish ──────────────────────────────────────────────────────────────────────────
let searchTimer;
const posSearch   = document.getElementById('pos-search');
const searchBox   = document.getElementById('search-results');
const posProducts = document.getElementById('pos-products');

// ─── posSearch: real-vaqt qidiruv (dropdown uchun) ────────────────────────────────────────────────
posSearch?.addEventListener('input', () => {
  clearTimeout(searchTimer);
  const q = posSearch.value.trim();
  if (!q) { searchBox.style.display = 'none'; return; }
  searchTimer = setTimeout(() => searchProducts(q), 300);
});

// ─── posSearch: Enter → handleBarcode() ──────────────────────────────────────────────────────────
// Bu listener FAQAT pos-search fokusda bo'lganda ishlaydi.
// Global keydown listener posSearch fokusda bo'lsa returnlaydi — double-call YO'Q.
posSearch?.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    e.preventDefault();
    searchBox.style.display = 'none';
    posSearch.value = '';
    return;
  }
  if (e.key === 'Enter') {
    e.preventDefault();
    clearTimeout(searchTimer); // debounce ni bekor qil — double-request YO'Q
    searchBox.style.display = 'none';
    const val = posSearch.value.trim();
    if (val) handleBarcode(val);
  }
});


function renderSearchResults(list) {
  searchBox.innerHTML = list.map(m => `
    <div class="search-item" onclick="addToCart(${JSON.stringify(m).replace(/"/g,'&quot;')})">
      <div class="fw-semibold">${im_esc(m.nomi)}</div>
      <div class="text-muted" style="font-size:11px">
        <code>${im_esc(m.barcode)||'—'}</code> · ${m.narx.toLocaleString()} so'm ·
        ${Number(m.auto_maydalash) === 1
          ? `Tayyor: <strong>${m.tayyor_qoldiq||0}</strong> · avtomatik: <strong>${m.auto_imkon||0}</strong>`
          : Number(m.retsept_avto) === 1
          ? `Retsept bo'yicha${m.imkon == null ? '' : ` · imkon: <strong>${m.imkon} ta</strong>`}`
          : `Qoldiq: <strong>${m.qoldiq}</strong>`}
        ${m.kampaniya_chegirma > 0 ? `· <span style="color:var(--danger)">-${m.kampaniya_chegirma}%</span>` : ''}
      </div>
    </div>`).join('');
}

async function searchProducts(q) {
  const res = await IMAjax.get(AJAX.mahPos, { q });
  if (res.status !== 'ok' || !res.data.list.length) {
    searchBox.innerHTML = '<div class="search-item text-muted">Topilmadi</div>';
  } else {
    renderSearchResults(res.data.list);
  }
  searchBox.style.display = 'block';
}

document.addEventListener('click', e => {
  if (!posSearch?.contains(e.target) && !searchBox?.contains(e.target))
    searchBox && (searchBox.style.display='none');
});

// ──── Kategoriya filtri ──────────────────────────────────────────────────────────────────────────
async function loadCategory(katId) {
  currentKat = katId;
  document.querySelectorAll('.pos-cat-btn').forEach(b => {
    b.classList.toggle('active', parseInt(b.dataset.kat) === katId);
  });
  if (!katId) {
    posProducts.innerHTML = `<div class="text-center text-muted" style="grid-column:1/-1;padding:40px 0">
      <i class="bi bi-search" style="font-size:32px;opacity:.3"></i>
      <p class="mt-2 fs-sm">Mahsulot qidiring yoki kategoriya tanlang</p></div>`;
    return;
  }
  posProducts.innerHTML = '<div class="text-center text-muted" style="grid-column:1/-1;padding:40px 0"><span class="im-spinner"></span></div>';
  const res = await IMAjax.get(AJAX.mahPos, { q: '' }).catch(() => null);
  // Actually load via category filter
  const r = await fetch(`${AJAX.mahPos}?kat=${katId}`).then(r=>r.json()).catch(()=>({status:'error'}));
  renderProducts(r.data?.list || []);
}

async function loadCategoryProducts(katId) {
  posProducts.innerHTML = '<div style="grid-column:1/-1;padding:30px;text-align:center"><span class="im-spinner"></span></div>';
  const r = await fetch(`${AJAX.mahPos}?kat_id=${katId}&q=.`).then(r=>r.json()).catch(()=>({data:{list:[]}}));
  renderProducts(r.data?.list || []);
}

function qtyStep(item) {
  const q = parseFloat(item?.sotuv_qadami || 1);
  return q > 0 ? q : 1;
}
function roundQty(v) { return parseFloat((Math.round((parseFloat(v) || 0) * 1000) / 1000).toFixed(3)); }
function normalizeQty(v, step) { return roundQty(Math.round((parseFloat(v) || 0) / step) * step); }
function qtyLabel(v) {
  const n = roundQty(v), whole = Math.floor(n + 0.0001), frac = roundQty(n - whole);
  const mark = Math.abs(frac-.25)<.001 ? '¼' : (Math.abs(frac-.5)<.001 ? '½' : (Math.abs(frac-.75)<.001 ? '¾' : ''));
  if (mark) return (whole ? whole : '') + mark;
  return Number.isInteger(n) ? String(n) : n.toLocaleString('uz-UZ',{maximumFractionDigits:3});
}
function addToCartAmount(m, amount) { addToCart(Object.assign({}, m, {soni: amount})); }

function renderProducts(list) {
  if (!list.length) {
    posProducts.innerHTML = `<div class="text-center text-muted" style="grid-column:1/-1;padding:40px 0">
      <i class="bi bi-inbox" style="font-size:32px;opacity:.3"></i>
      <p class="mt-2 fs-sm">Bu kategoriyada mahsulot yo'q</p></div>`;
    return;
  }
  posProducts.innerHTML = list.map(m => {
    const qadam = qtyStep(m);
    const payload = JSON.stringify(m).replace(/"/g,'&quot;');
    const rasmHtml = m.rasm
      ? `<img class="p-img" src="${window.im_BASE}${m.rasm}" alt="${im_esc(m.nomi)}" onerror="this.parentNode.innerHTML='<div class=&quot;p-img-placeholder&quot;><i class=&quot;bi bi-image&quot;></i></div>'">`
      : `<div class="p-img-placeholder"><i class="bi bi-image"></i></div>`;
    return `
    <div class="pos-product-card ${m.qoldiq<=0?'sold-out':''}"
         onclick="${m.qoldiq>0?`addToCart(${payload})`:''}"
         title="${im_esc(m.nomi)}">
      ${m.kampaniya_chegirma > 0 ? `<div class="p-discount-badge">-${m.kampaniya_chegirma}%</div>` : ''}
      ${rasmHtml}
      <div class="p-body">
        <div class="p-name">${im_esc(m.nomi)}</div>
        <div class="p-price">${Number(m.narx).toLocaleString()} so'm</div>
        <div class="p-stock">${Number(m.auto_maydalash) === 1
          ? `✂️ TAYYOR ${qtyLabel(m.tayyor_qoldiq||0)} · AVTO ${qtyLabel(m.auto_imkon||0)}`
          : Number(m.retsept_avto) === 1
          ? `RETSEPT BO'YICHA${m.imkon == null ? '' : ' · '+m.imkon+' ta'}`
          : (m.qoldiq>0 ? qtyLabel(m.qoldiq)+' '+m.birlik : 'TUGADI')}</div>
      </div>
      ${qadam < 1 && m.qoldiq > 0 ? `<div class="p-fractions" onclick="event.stopPropagation()">
        ${qadam <= .25 ? `<button class="p-fraction-btn" onclick="addToCartAmount(${payload},.25)">+¼</button>` : ''}
        ${qadam <= .5 ? `<button class="p-fraction-btn" onclick="addToCartAmount(${payload},.5)">+½</button>` : ''}
        <button class="p-fraction-btn" onclick="addToCartAmount(${payload},1)">+1</button>
      </div>` : ''}
    </div>`;
  }).join('');
}

document.querySelectorAll('.pos-cat-btn').forEach(b => {
  b.addEventListener('click', () => {
    const kat = b.dataset.kat;
    document.querySelectorAll('.pos-cat-btn').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');

    if (kat === '__sets__') {
      // Setlar panelini ko'rsat
      posProducts.style.display    = 'none';
      document.getElementById('pos-sets-grid').style.display = 'grid';
      loadSets();
    } else {
      // Oddiy kategoriya
      document.getElementById('pos-sets-grid').style.display = 'none';
      posProducts.style.display = 'grid';
      currentKat = parseInt(kat) || 0;
      loadCategoryProductsFixed(currentKat);
    }
  });
});

// sahifa ochilganda barcha mahsulotlarni olib kelish + saqlangan savatchani qayta ko'rsat
// DIQQAT: smena ochilmagan bo'lsa (!$smena — PHP tarafda) #pos-products,
// #pos-sets-grid kabi elementlar UMUMAN render qilinmaydi ("Smenani ochish"
// ekrani ko'rsatiladi). posProducts o'shanda null bo'ladi — shart bilan
// tekshirmasak, "Cannot read properties of null (reading 'style')" xatosi
// (loadCategoryProductsFixed ichida) konsolga tushadi. Foydalanuvchiga
// ko'rinmaydi, lekin sof bazada (birinchi marta ishga tushirilganda,
// hali bironta smena ochilmagan holatda) darhol yuz beradi.
window.addEventListener('DOMContentLoaded', async () => {
  if (!posProducts) return; // smena yopiq — POS ekrani render qilinmagan
  loadCategoryProductsFixed(0);
  if (cart.length) renderCart(); // localStorage dan yuklangan savatchani ko'rsat
  renderManbaChip();
  if (restoredPosState.legacyDiscarded) {
    NHToast.warning("Eski formatdagi savatning order manbasi aniqlanmagani uchun xavfsizlik maqsadida tozalandi.", 5000);
  }
  await validateRestoredPosContext();
  // Skaner tayyor — kursorni olib bormasdan ham ishlaydi
  setTimeout(() => posSearch?.focus(), 200);
});

async function loadCategoryProductsFixed(katId) {
  posProducts.style.display = 'grid';
  document.getElementById('pos-sets-grid').style.display = 'none';
  posProducts.innerHTML = '<div style="grid-column:1/-1;padding:30px;text-align:center"><span class="im-spinner"></span></div>';
  const r = await fetch(`${window.im_BASE}dukon/ajax/mah-cat?kat=${katId}`).then(r=>r.json()).catch(()=>({data:{list:[]}}));
  renderProducts(r.data?.list || []);
}

// ──────────────────────────────────────────────────────────────
// 🎁 SETLAR — yuklaш va savatga qo'shish
// ──────────────────────────────────────────────────────────────
let setsCache = null; // bir marta yuklanadi

async function loadSets(bgMode = false) {
  const grid = document.getElementById('pos-sets-grid');
  if (!bgMode && !setsCache) {
    grid.innerHTML = '<div style="grid-column:1/-1;padding:40px;text-align:center"><span class="im-spinner"></span><p class="mt-2 fs-sm text-muted">Setlar yuklanmoqda...</p></div>';
  }
  try {
    const res = await fetch(AJAX.setList).then(r=>r.json());
    if (res.status !== 'ok') { setsCache = []; }
    else { setsCache = res.data?.list || []; }
  } catch(e) { setsCache = []; }

  // Tab da sonini ko'rsatish
  const badge = document.getElementById('sets-tab-count');
  if (badge) badge.textContent = setsCache.length ? `(${setsCache.length})` : '';

  if (!bgMode) renderSetsGrid(setsCache);
}

function renderSetsGrid(list) {
  const grid = document.getElementById('pos-sets-grid');
  if (!list.length) {
    grid.innerHTML = `<div style="grid-column:1/-1;padding:40px;text-align:center">
      <i class="bi bi-gift" style="font-size:36px;opacity:.25"></i>
      <p class="mt-2 fs-sm text-muted">Bu filial uchun set yaratilmagan</p>
      <a href="${window.im_BASE}dukon/setlar.php" class="im-btn im-btn-sm im-btn-outline mt-2">Set yaratish</a>
    </div>`;
    return;
  }
  grid.innerHTML = list.map(set => {
    const items_html = set.items.map(it =>
      `<div style="font-size:11px;color:var(--text-muted);line-height:1.6">
        · ${im_esc(it.nomi)} <strong>×${qtyLabel(it.soni)}</strong>
      </div>`
    ).join('');
    const unavail = !set.available;
    return `
    <div class="pos-product-card ${unavail ? 'sold-out' : ''}"
         onclick="${unavail ? '' : `addSetToCart(${JSON.stringify(set).replace(/"/g,'&quot;')})`}"
         title="${im_esc(set.nomi)}"
         style="border:2px solid ${im_esc(set.rang)};position:relative;cursor:${unavail?'not-allowed':'pointer'}">
      <div style="position:absolute;top:6px;right:6px;font-size:18px">🎁</div>
      <div class="p-body" style="padding:10px 8px">
        <div class="p-name" style="font-weight:800;font-size:13px;color:${im_esc(set.rang)}">${im_esc(set.nomi)}</div>
        <div style="margin:6px 0">${items_html}</div>
        <div style="border-top:1px solid var(--border);padding-top:6px;margin-top:4px">
          <div class="p-price" style="color:${im_esc(set.rang)};font-size:14px">${Math.round(set.narxi).toLocaleString()} so'm</div>
          ${unavail ? '<div style="font-size:10px;color:var(--danger);font-weight:700">⚠️ Qoldiq yetarli emas</div>' : ''}
        </div>
      </div>
    </div>`;
  }).join('');
}

function addSetToCart(set) {
  if (currentBatchOrders.length) { NHToast.warning('Birga to‘lash savatiga mahsulot qo‘shilmaydi'); return; }
  if (!set.available) { NHToast.error('Qoldiq yetarli emas'); return; }

  // Proporsional narxni hisoblash
  const aslYigindi = set.items.reduce((s, i) => s + i.sotuv_narxi * i.soni, 0);
  const koeff = aslYigindi > 0 ? set.narxi / aslYigindi : 1;

  let added = 0;
  set.items.forEach(item => {
    const propNarx = aslYigindi > 0 ? Math.round(item.sotuv_narxi * koeff) : Math.round(set.narxi / set.items.length);
    addToCart({
      id:       item.mahsulot_id,
      nomi:     item.nomi,
      narx:     propNarx,
      tannarx:  item.tannarx,
      soni:     item.soni,
      qoldiq:   item.qoldiq,
      set_id:   set.id,
      birlik:   item.birlik,
      sotuv_qadami: item.sotuv_qadami || 1,
      barcode:  '',
      kampaniya_chegirma: 0,
      ulg_min:  0,
      ulg_narx: 0,
    }, true); // silent=true — har bir mahsulot uchun alohida toast chiqmasin
    added++;
  });
  if (added > 0) NHToast.success(`🎁 «${set.nomi}» savatga qo'shildi`);
}

// ──── Savatga qo'shish ────────────────────────────────────────────────────────────────────────────
function addToCart(m, silent = false) {
  if (currentBatchOrders.length) { if (!silent) NHToast.warning('Birga to‘lash savatiga mahsulot qo‘shilmaydi'); return false; }
  // posSearch.value ni bu yerda tozalamaymiz — processBarcodeSearch o'zi boshqaradi
  searchBox && (searchBox.style.display = 'none');

  // Barcha cart dagi shu mahsulotning jami sonini hisoblash (set_id dan qat'i nazar)
  const totalInCart = cart.filter(c => c.id == m.id).reduce((s, c) => s + c.soni, 0);
  const addSoni = parseFloat(m.soni) > 0 ? roundQty(m.soni) : qtyStep(m);

  // Merge: faqat bir xil id VA bir xil set_id bo'lsa qo'shiladi
  const mSetId = m.set_id || null;
  const existing = cart.find(c => c.id == m.id && (c.set_id || null) == mSetId);

  if (existing) {
    // Jami qoldiq tekshiruvi: savatdagi barcha + qo'shilmoqchi bo'lgan
    if (totalInCart + addSoni > m.qoldiq) {
      if (!silent) NHToast.warning(`«${m.nomi}» — qoldiq yetarli emas (qoldi: ${m.qoldiq}, savatda: ${Math.round(totalInCart)})`);
      return false;
    }
    existing.soni = parseFloat((existing.soni + addSoni).toFixed(3));
  } else {
    // Yangi qator — jami qoldiq tekshiruvi
    if (totalInCart + addSoni > m.qoldiq) {
      if (!silent) NHToast.warning(`«${m.nomi}» — qoldiq yetarli emas (qoldi: ${m.qoldiq}, savatda: ${Math.round(totalInCart)})`);
      return false;
    }
    cart.push({
      id: m.id, nomi: m.nomi, narx: parseFloat(m.narx),
      soni: addSoni,
      birlik: m.birlik || 'dona',
      sotuv_qadami: qtyStep(m),
      qoldiq: parseFloat(m.qoldiq),
      rasm: m.rasm || null,
      ulg_min: parseFloat(m.ulg_min)||0,
      ulg_narx: parseFloat(m.ulg_narx)||0,
      kampaniya_ch: parseFloat(m.kampaniya_chegirma)||0,
      tannarx: parseFloat(m.tannarx)||0,
      individual_ch: 0,
      set_id: m.set_id || null,
    });
  }
  renderCart();
  saveCart();
  if (!silent) NHToast.info(`«${m.nomi}» savatga qo'shildi`, 1200);
  return true;
}

// ──── Savat render ──────────────────────────────────────────────────────────────────────────────────
function renderCart() {
  const body = document.getElementById('cart-body');
  if (!cart.length) {
    body.innerHTML = `<div class="cart-empty"><i class="bi bi-cart3" style="font-size:40px;opacity:.2"></i><p class="mt-2 fs-sm">Mahsulot qo'shing</p></div>`;
    document.getElementById('cart-count').textContent = 'Bo\'sh';
    updateTotals();
    return;
  }
  const total_items = cart.reduce((s,c)=>s+c.soni,0);
  document.getElementById('cart-count').textContent = `${cart.length} xil · ${qtyLabel(total_items)} birlik`;

  const isNasiyaMode = (currentPayMode === 'nasiya');
  body.innerHTML = cart.map((item, idx) => {
    const global_ch = isNasiyaMode ? 0 : Math.min(MAX_CH, parseFloat(document.getElementById('chegirma-foiz')?.value||0));
    const mij_ch = isNasiyaMode ? 0 : (selectedMijoz?.chegirma_foiz || 0);
    const item_ch = isNasiyaMode ? 0 : (item.individual_ch || 0);
    // Nasiya rejimida chegirma yo'q, boshqalarda eng katta chegirma qo'llanadi
    const eff_ch = isNasiyaMode ? 0 : Math.max(item.kampaniya_ch, mij_ch, global_ch, item_ch);
    // Ulgurji tekshirish — nasiyada yo'q
    const use_ulg = isNasiyaMode ? false : (item.ulg_min > 0 && item.soni >= item.ulg_min && item.ulg_narx > 0);
    const base = use_ulg ? item.ulg_narx : item.narx;
    const real_narx = base * (1 - eff_ch/100);
    const line = real_narx * item.soni;

    const ch_badges = [];
    if (!isNasiyaMode) {
      if (item.kampaniya_ch > 0) ch_badges.push(`<span class="im-badge" style="background:#fee;color:var(--danger);font-size:9px">Aksiya -${item.kampaniya_ch}%</span>`);
      if (mij_ch > 0) ch_badges.push(`<span class="im-badge" style="background:#e8f5e9;color:var(--success);font-size:9px">Mijoz -${mij_ch}%</span>`);
      if (item_ch > 0 && item_ch > item.kampaniya_ch && item_ch > mij_ch && item_ch > global_ch) ch_badges.push(`<span class="im-badge" style="background:#fff3e0;color:#e65100;font-size:9px">Alohida -${item_ch}%</span>`);
    }
    if (isNasiyaMode) ch_badges.push(`<span class="im-badge" style="background:#fff3cd;color:#856404;font-size:9px">⚠️ Nasiya — chegirmasiz</span>`);

    // Mustaqil Olib ketish orderining barcha qatori qadoqlanadi va xizmat haqisiz.
    if (currentOrderOlibKetish || item.source_olib_ketish) ch_badges.push(`<span class="im-badge" style="background:#ffedd5;color:#9a3412;font-size:9px">🛍️ Qadoqlanadi — xizmat haqisiz</span>`);
    if (item.source_order_id) ch_badges.push(`<span class="im-badge im-badge-info" style="font-size:9px">Chek #${Number(item.source_order_id)}</span>`);

    // Setdan kelgan qator
    if (item.set_id) ch_badges.push(`<span class="im-badge" style="background:#fef9c3;color:#b8860b;font-size:9px">🎁 ${im_esc(item.set_nomi) || 'Set'}</span>`);

    // Rasm HTML
    const thumbHtml = item.rasm
      ? `<img class="cart-item-thumb" src="${window.im_BASE}${item.rasm}" alt="${im_esc(item.nomi)}" onerror="this.style.display='none'">`
      : `<div class="cart-item-thumb-placeholder"><i class="bi bi-box-seam"></i></div>`;

    return `<div class="cart-item" id="ci-${idx}">
      ${thumbHtml}
      <div class="flex-grow-1">
        <div class="cart-item-name">${im_esc(item.nomi)}</div>
        <div class="cart-item-price">
          ${use_ulg ? '<span class="im-badge im-badge-info fs-xs me-1">Ulgurji</span>':''}
          ${eff_ch > 0 ? `<span style="text-decoration:line-through;color:var(--muted);font-size:10px">${(base).toLocaleString()}</span> ` : ''}
          <strong>${real_narx.toLocaleString(undefined,{maximumFractionDigits:3})}</strong> so'm
        </div>
        <div style="margin-top:3px;display:flex;align-items:center;gap:4px;flex-wrap:wrap">
          ${ch_badges.join('')}
          ${!isNasiyaMode && !currentBatchOrders.length ? `<div style="display:flex;align-items:center;gap:2px">
            <input type="number" min="0" max="100" step="1" value="${item.individual_ch||0}"
              placeholder="%" style="width:50px;height:20px;font-size:10px;padding:1px 4px;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text)"
              title="Bu tovar uchun alohida chegirma"
              oninput="setItemChegirma(${idx}, this.value)">
            <span style="font-size:9px;color:var(--muted)">%</span>
          </div>` : ''}
        </div>
      </div>
      <div style="text-align:right;min-width:90px">
        <div class="fw-bold num" style="font-size:12.5px;color:${eff_ch>0?'var(--success)':'inherit'}">${line.toLocaleString(undefined,{maximumFractionDigits:0})} so'm</div>
        ${currentBatchOrders.length ? '<div class="text-muted mt-1" style="font-size:10px">Miqdor orderdan olingan</div>' : `<div class="qty-ctrl mt-1">
          <button class="qty-btn" onclick="changeQty(${idx},-1)">−</button>
          <input class="qty-input" type="number" min="${qtyStep(item)}" step="${qtyStep(item)}"
            value="${roundQty(item.soni)}"
            onchange="setQty(${idx}, this.value)"
            onclick="this.select()"
            title="Sonni qo'lda kiriting (float qabul qilinadi)">
          <button class="qty-btn" onclick="changeQty(${idx},1)">+</button>
          <button class="qty-btn" onclick="removeItem(${idx})" style="color:var(--danger);font-size:12px">
            <i class="bi bi-trash-fill"></i>
          </button>
        </div>`}
      </div>
    </div>`;
  }).join('');
  updateTotals();
  saveCart(); // Har o'zgarishda localStorage ga saqla
}


// ──── Stol buyurtmasidan kamaytirish (server bilan sinxron) ────────
// Savatdan olib tashlashning o'zi yetarli emas: oshpaz taomni qabul
// qilgan bo'lsa uning xomashyosi qoldiqdan yechilgan — kamaytirilgan
// miqdorga to'g'ri keladigan xomashyo qoldiqqa qaytarilishi kerak.
async function kamaytirStolItem(item, newQty) {
  const savol = newQty <= 0
    ? `«${item.nomi}» buyurtmadan butunlay olib tashlansinmi?`
    : `«${item.nomi}»: ${qtyLabel(item.soni)} → ${qtyLabel(newQty)} ga kamaytirilsinmi?`;
  const ok = await NHConfirm.show({
    variant: 'warning',
    title: newQty <= 0 ? "Mahsulotni olib tashlash" : "Miqdorni kamaytirish",
    text: savol,
    sub: "Oshpaz tayyorlagan bo'lsa, tegishli xomashyo qoldiqqa qaytariladi.",
    confirmText: newQty <= 0 ? "Olib tashlash" : "Kamaytirish",
    btnIcon: newQty <= 0 ? 'bi-cart-dash-fill' : 'bi-dash-circle-fill'
  });
  if (!ok) return false;

  const fd = new URLSearchParams({
    order_id:    currentOrderId,
    mahsulot_id: item.id,
    set_id:      item.set_id || 0,
    yangi_soni:  Math.max(0, newQty),
  });
  try {
    const r = await fetch(im_BASE + 'dukon/ajax/order-item-kamaytir.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.status !== 'ok') { NHToast.error(d.msg || 'Xatolik'); return false; }
    NHToast.success(d.msg);
    return true;
  } catch {
    NHToast.error('Tarmoq xatosi — kamaytirilmadi');
    return false;
  }
}

async function changeQty(idx, delta) {
  if (currentBatchOrders.length) { NHToast.warning('Birga to‘lashda order miqdori o‘zgartirilmaydi'); return; }
  const item = cart[idx];
  if (!item) return;
  const change = delta * qtyStep(item);
  const newQty = roundQty(item.soni + change);
  if (delta > 0 && newQty > item.qoldiq) { NHToast.warning('Sklad qoldig\'i yetarli emas'); return; }

  // Stol buyurtmasi kamayishi — avval tasdiqlash, keyin serverga yozish
  if (currentOrderId > 0 && change < 0) {
    if (!await kamaytirStolItem(item, Math.max(0, newQty))) return;
    if (newQty <= 0) cart.splice(idx, 1); else item.soni = newQty;
    renderCart();
    return;
  }

  if (newQty <= 0) { removeItem(idx); return; }
  item.soni = newQty;
  renderCart();
}

// Qo'lda soni kiritish (float qabul qilinadi)
async function setQty(idx, val) {
  if (currentBatchOrders.length) { NHToast.warning('Birga to‘lashda order miqdori o‘zgartirilmaydi'); renderCart(); return; }
  const item = cart[idx];
  if (!item) return;
  if (val === '' || isNaN(parseFloat(val))) { renderCart(); return; }
  const rawQty = roundQty(val);
  const newQty = rawQty > 0 ? Math.max(qtyStep(item), normalizeQty(rawQty, qtyStep(item))) : 0;
  if (Math.abs(rawQty - newQty) > .0001) NHToast.info(`Miqdor ${qtyLabel(qtyStep(item))} qadamga moslandi`);

  // Stol buyurtmasida kamaytirish — tasdiqlash + xomashyo qaytarish
  if (currentOrderId > 0 && newQty < item.soni) {
    if (!await kamaytirStolItem(item, Math.max(0, newQty))) { renderCart(); return; }
    if (newQty <= 0) cart.splice(idx, 1); else item.soni = newQty;
    renderCart();
    return;
  }

  if (newQty <= 0) { removeItem(idx); return; }
  if (newQty > item.qoldiq) {
    NHToast.warning('Sklad qoldig\'i yetarli emas (max: ' + item.qoldiq + ')');
    item.soni = item.qoldiq;
  } else {
    item.soni = newQty;
  }
  updateTotals(); // renderCart qilmaymiz — input fokus yo'qoladi
}

async function removeItem(idx) {
  if (currentBatchOrders.length) { NHToast.warning('Birga to‘lashda order qatori o‘chirilmaydi'); return; }
  const item = cart[idx];
  // Stol buyurtmasi bo'lsa serverga ham yozamiz (xomashyo qaytarish uchun)
  if (currentOrderId > 0 && item) {
    if (!await kamaytirStolItem(item, 0)) return;
  }
  cart.splice(idx, 1);
  renderCart();
}

// Alohida individual chegirma o'rnatish
function setItemChegirma(idx, val) {
  if (currentBatchOrders.length) return;
  if (!cart[idx]) return;
  const ch = Math.min(100, Math.max(0, parseFloat(val)||0));
  cart[idx].individual_ch = ch;
  updateTotals(); // Jami qayta hisoblanadi (renderCart qilmaymiz — input fokus yo'qoladi)
}

document.getElementById('btn-cart-clear')?.addEventListener('click', () => {
  if (!cart.length) return;
  NHConfirm.show({
    title: 'Savatni tozalash',
    text: 'Savatni tozalashni tasdiqlaysizmi?',
    btnText: 'Tozalash',
    btnClass: 'im-btn-danger',
    icon: 'bi-trash3-fill'
  }).then(ok => {
    if (ok) { resetCart(); renderManbaChip(); }
  });
});

// ──── To'lov hisoblash ──────────────────────────────────────────────────────────────────────────
function calcTotals() {
  const isNasiya = (currentPayMode === 'nasiya');
  const chegirma_foiz = isNasiya ? 0 : Math.min(MAX_CH, parseFloat(document.getElementById('chegirma-foiz')?.value||0));
  const mij_ch = isNasiya ? 0 : (selectedMijoz?.chegirma_foiz || 0);
  let jami = 0, chegirma_sum = 0;
  for (const item of cart) {
    const item_ch = isNasiya ? 0 : (item.individual_ch || 0);
    const eff = isNasiya ? 0 : Math.max(item.kampaniya_ch, mij_ch, chegirma_foiz, item_ch);
    const use_ulg = isNasiya ? false : (item.ulg_min > 0 && item.soni >= item.ulg_min && item.ulg_narx > 0);
    const base = use_ulg ? item.ulg_narx : item.narx;
    const real = base * (1 - eff/100);
    jami += base * item.soni;
    chegirma_sum += (base - real) * item.soni;
  }
  let tolov = Math.round(jami - chegirma_sum);

  // Voucher chegirmasi — nasiyada ishlamaydi
  let voucher_chegirma = 0;
  if (!isNasiya && selectedVoucher) {
    if (selectedVoucher.min_summa > 0 && tolov < selectedVoucher.min_summa) {
      voucher_chegirma = 0;
    } else {
      if (selectedVoucher.tur === 'foiz') {
        voucher_chegirma = tolov * (parseFloat(selectedVoucher.qiymat) / 100);
        if (selectedVoucher.max_chegirma > 0 && voucher_chegirma > selectedVoucher.max_chegirma) {
          voucher_chegirma = selectedVoucher.max_chegirma;
        }
      } else {
        voucher_chegirma = Math.min(parseFloat(selectedVoucher.qiymat) || 0, tolov);
      }
    }
    voucher_chegirma = Math.round(voucher_chegirma);
    tolov = Math.max(0, Math.round(tolov - voucher_chegirma));
    chegirma_sum += voucher_chegirma;
  }

  // ──── Xizmat haqi (otsluga) ────────────────────────────────
  // Faqat sotuvchidan yuklangan STOL buyurtmasiga. Kassir belgini
  // olib tashlab o'chira oladi. Tartib server bilan bir xil: chegirma
  // va voucherdan keyin, umumiy chegirmadan oldin.
  // Xizmat haqi FAQAT stolda iste'mol qilingan qatorlardan. Mijoz stolda
  // o'tirib 1 porsiyani saboyga olsa — o'sha qatordan otsluga olinmaydi.
  // MUHIM: bu blok (voucher → xizmat haqi → umumiy chegirma) serverdagi
  // config.php: im_sotuv_yakun_hisobla() bilan AYNAN BIR XIL formulani
  // qo'lda takrorlaydi (build-tool yo'q, PHP<->JS kod bo'lishmaydi).
  // O'zgartirsangiz ikkalasini ham yangilang va tests/run.php ni qayta
  // ishga tushiring — u aynan shu funksiyani bir nechta vaziyat bilan
  // tekshiradi.
  const xizmat_foiz = xizmatQollanadimi() ? XIZMAT_FOIZ : 0;
  let q_jami = 0, q_stol = 0;
  for (const item of cart) {
    const item_ch = isNasiya ? 0 : (item.individual_ch || 0);
    const eff  = isNasiya ? 0 : Math.max(item.kampaniya_ch, mij_ch, chegirma_foiz, item_ch);
    const use_u= isNasiya ? false : (item.ulg_min > 0 && item.soni >= item.ulg_min && item.ulg_narx > 0);
    const bir  = (use_u ? item.ulg_narx : item.narx) * (1 - eff/100);
    const oks  = Math.min(item.olib_ketish_soni || 0, item.soni);
    q_jami += bir * item.soni;
    q_stol += bir * (item.soni - oks);   // faqat stolda iste'mol qilingan qism
  }
  const xizmat_ulush = q_jami > 0 ? (q_stol / q_jami) : 0;
  const xizmat = xizmat_foiz > 0 ? Math.round(tolov * xizmat_ulush * xizmat_foiz / 100) : 0;
  tolov += xizmat;

  // ──── Umumiy chegirma: kiritilgan summa < tolov bo'lsa, farq = chegirma (nasiyadan boshqa) ────
  let umumiy_ch = 0;
  let qayta = 0;
  if (tolov <= 0) {
    qayta = 0;
  } else if (!isNasiya && currentPayMode !== 'aralash') {
    // naqd, karta, bank, usd rejimlarida
    let kiritilgan = 0;
    if (currentPayMode === 'naqd') {
      const v = document.getElementById('pay-naqd-input')?.value;
      kiritilgan = (v !== '' && v != null) ? parseFloat(v || 0) : -1; // -1 = hali kiritilmagan
    } else if (currentPayMode === 'karta') {
      const v = document.getElementById('pay-karta-input')?.value;
      kiritilgan = (v !== '' && v != null && parseFloat(v) > 0) ? parseFloat(v) : -1;
    } else if (currentPayMode === 'bank') {
      const v = document.getElementById('pay-bank-input')?.value;
      kiritilgan = (v !== '' && v != null && parseFloat(v) > 0) ? parseFloat(v) : -1;
    } else if (currentPayMode === 'usd') {
      const v = document.getElementById('pay-usd-input')?.value;
      kiritilgan = (v !== '' && v != null && parseFloat(v) > 0) ? Math.round(parseFloat(v) * USD_KURS) : -1;
    }

    if (kiritilgan >= 0 && kiritilgan < tolov) {
      // Farq = umumiy chegirma
      umumiy_ch = tolov - kiritilgan;
      tolov = kiritilgan;
      chegirma_sum += umumiy_ch;
    } else if (kiritilgan >= tolov) {
      // Ortiqcha = qayta pul (naqd va usd uchun — sumdagi qaytim)
      if (currentPayMode === 'naqd' || currentPayMode === 'usd') qayta = kiritilgan - tolov;
    }
  } else if (currentPayMode === 'aralash') {
    const paid = (parseFloat(document.getElementById('a-naqd')?.value||0) +
                  parseFloat(document.getElementById('a-karta')?.value||0) +
                  parseFloat(document.getElementById('a-bank')?.value||0));
    if (paid > 0 && paid < tolov) {
      // Aralashda ham umumiy chegirma (nasiya bo'lmasa)
      umumiy_ch = tolov - paid;
      tolov = paid;
      chegirma_sum += umumiy_ch;
    } else if (paid >= tolov) {
      qayta = paid - tolov;
    }
  }

  return { jami, chegirma: chegirma_sum, tolov, qayta, kam: 0, umumiy_ch, xizmat, xizmat_foiz };
}

// Xizmat haqi shu savdoga qo'llanadimi?
// Shartlar: admin foiz belgilagan + bu sotuvchidan yuklangan STOL buyurtmasi
// (olib ketish/dastavka va to'g'ridan-to'g'ri savdoga qo'shilmaydi) +
// kassir belgini olib tashlamagan.
function xizmatQollanadimi() {
  if (!(XIZMAT_FOIZ > 0)) return false;
  if (currentBatchOrders.length) {
    if (!currentBatchOrders.some(o => !o.olib_ketish && parseInt(o.stol_id) > 0)) return false;
  } else {
    if (!currentOrderId || !currentOrderStolId) return false; // dastavka stol emas
    if (currentOrderOlibKetish) return false;
  }
  const cb = document.getElementById('xizmat-toggle');
  return cb ? cb.checked : true;
}

function updateTotals() {
  const { jami, chegirma, tolov, qayta, umumiy_ch, xizmat } = calcTotals();
  if (document.getElementById('cart-jami-text')) {
    document.getElementById('cart-jami-text').textContent = jami.toLocaleString(undefined,{maximumFractionDigits:0}) + ' so\'m';
  }
  if (!document.getElementById('total-jami')) return;
  document.getElementById('total-jami').textContent = jami.toLocaleString(undefined,{maximumFractionDigits:0}) + ' so\'m';
  document.getElementById('total-tolov').textContent = tolov.toLocaleString(undefined,{maximumFractionDigits:0}) + ' so\'m';
  document.getElementById('chegirma-row').style.display = (chegirma - umumiy_ch) > 0 ? 'flex' : 'none';
  document.getElementById('total-chegirma').textContent = '-' + (chegirma - umumiy_ch).toLocaleString(undefined,{maximumFractionDigits:0}) + ' so\'m';

  // Umumiy chegirma row
  const umChRow = document.getElementById('umumiy-chegirma-row');
  if (umChRow) {
    umChRow.style.display = umumiy_ch > 0 ? 'flex' : 'none';
    document.getElementById('total-umumiy-chegirma').textContent = '-' + umumiy_ch.toLocaleString(undefined,{maximumFractionDigits:0}) + ' so\'m';
  }

  // Xizmat haqi (otsluga) qatori — stol buyurtmasi bo'lsa ko'rinadi.
  // Belgi olib tashlansa qator qoladi (qayta yoqish uchun), summa 0 bo'ladi.
  const xizRow = document.getElementById('xizmat-row');
  if (xizRow) {
    const korinsin = XIZMAT_FOIZ > 0 && cart.length > 0 && (
      currentBatchOrders.some(o => !o.olib_ketish && parseInt(o.stol_id) > 0)
      || (currentOrderId > 0 && currentOrderStolId > 0 && !currentOrderOlibKetish)
    );
    xizRow.style.display = korinsin ? 'flex' : 'none';
    document.getElementById('xizmat-foiz-text').textContent = XIZMAT_FOIZ;
    document.getElementById('total-xizmat').textContent =
      '+' + xizmat.toLocaleString(undefined,{maximumFractionDigits:0}) + ' so\'m';
  }

  document.getElementById('qayta-pul-row').style.display = qayta > 0 ? 'flex' : 'none';
  document.getElementById('total-qayta').textContent = qayta.toLocaleString(undefined,{maximumFractionDigits:0}) + ' so\'m';

  // Checkout button
  const btn = document.getElementById('btn-checkout');
  if (btn) btn.disabled = cart.length === 0 || tolov < 0;
  const preBtn = document.getElementById('btn-pre-chek');
  if (preBtn) preBtn.disabled = cart.length === 0;

  // USD tab yoqilgan bo'lsa — kerakli dollarni tavsiya et
  updateUsdHint(tolov);
}

// Kassir summani qayta yozib o'tirmasligi uchun faol so'm inputiga
// ayni paytdagi yakuniy to'lov summasini joylaydi.
function prefillActivePaymentAmount() {
  const inputId = {
    naqd: 'pay-naqd-input',
    karta: 'pay-karta-input',
    bank: 'pay-bank-input'
  }[currentPayMode];
  if (!inputId) { updateTotals(); return; }

  const input = document.getElementById(inputId);
  if (!input) { updateTotals(); return; }

  // Eski qiymat umumiy chegirma bo'lib hisoblanmasligi uchun avval tozalaymiz.
  input.value = '';
  const { tolov } = calcTotals();
  input.value = Math.max(0, Math.round(tolov));
  updateTotals();
}

document.getElementById('chegirma-foiz')?.addEventListener('input', () => {
  renderCart();
  if (document.getElementById('checkout-modal')?.classList.contains('show')) prefillActivePaymentAmount();
});
document.getElementById('xizmat-toggle')?.addEventListener('change', prefillActivePaymentAmount);
['pay-naqd-input','pay-karta-input','pay-bank-input','pay-usd-input','a-naqd','a-karta','a-bank'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', updateTotals);
});

// ──── To'lov rejimi ──────────────────────────────────────────────────────────────────────────────────
document.querySelectorAll('.pay-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.pay-tab').forEach(t=>t.classList.remove('active'));
    tab.classList.add('active');
    currentPayMode = tab.dataset.pay;

    // Inputlarni ko'rsat/yashir
    document.getElementById('pay-simple').classList.toggle('visible', currentPayMode==='naqd');
    document.getElementById('pay-karta-wrap').classList.toggle('visible', currentPayMode==='karta');
    document.getElementById('pay-bank-wrap').classList.toggle('visible', currentPayMode==='bank');
    document.getElementById('pay-usd-wrap').classList.toggle('visible', currentPayMode==='usd');
    if (currentPayMode==='usd') {
      const { tolov } = calcTotals();
      updateUsdHint(tolov);
      setTimeout(() => document.getElementById('pay-usd-input')?.focus(), 100);
    }
    document.getElementById('pay-nasiya-wrap').classList.toggle('visible', currentPayMode==='nasiya');
    document.getElementById('pay-aralash-wrap').classList.toggle('visible', currentPayMode==='aralash');

    // Chegirma va voucher qutisilarini nasiya rejimida o'chirish
    const chegirmaWrap = document.getElementById('chegirma-foiz')?.closest('.d-flex');
    const voucherWrap  = document.getElementById('voucher-input')?.closest('.d-flex.align-items-center.gap-2.mb-2');
    if (chegirmaWrap) chegirmaWrap.style.opacity = currentPayMode==='nasiya' ? '0.35' : '1';
    if (voucherWrap)  voucherWrap.style.opacity  = currentPayMode==='nasiya' ? '0.35' : '1';
    if (chegirmaWrap) chegirmaWrap.style.pointerEvents = currentPayMode==='nasiya' ? 'none' : '';
    if (voucherWrap)  voucherWrap.style.pointerEvents  = currentPayMode==='nasiya' ? 'none' : '';

    // Savat ko'rinishini va jami summani yangilash (nasiyada chegirmasiz ko'rsatiladi)
    renderCart();
    prefillActivePaymentAmount();
  });
});

// ──── Mijoz qidirish ──────────────────────────────────────────────────────────────────────────────
let mijozTimer;
const mijozQ       = document.getElementById('mijoz-q');
const mijozResults = document.getElementById('mijoz-results');

mijozQ?.addEventListener('input', () => {
  clearTimeout(mijozTimer);
  const q = mijozQ.value.trim();
  if (!q) { mijozResults.style.display='none'; return; }
  mijozTimer = setTimeout(async () => {
    const res = await IMAjax.get(AJAX.mijozSearch, { q });
    if (res.status!=='ok'||!res.data.list.length) {
      mijozResults.innerHTML='<div class="mijoz-item text-muted">Topilmadi</div>';
    } else {
      mijozResults.innerHTML = res.data.list.map(m=>`
        <div class="mijoz-item" onclick='selectMijoz(${JSON.stringify(m).replace(/'/g,"&#39;")})'>
          <div class="fw-semibold">${im_esc(m.ism)}</div>
          <div class="text-muted" style="font-size:11px">
            ${im_esc(m.telefon)||''} · ${im_esc(m.toifa_nomi)||''} ${m.chegirma_foiz>0?'(-'+m.chegirma_foiz+'%)':''}
            ${m.nasiya_qoldiq>0?`· <span style="color:var(--danger)">Nasiya: ${Number(m.nasiya_qoldiq).toLocaleString()} so'm</span>`:''}
          </div>
        </div>`).join('');
    }
    mijozResults.style.display='block';
  }, 300);
});

function selectMijoz(m) {
  selectedMijoz = m;
  mijozQ.value = m.ism;
  mijozResults.style.display='none';
  document.getElementById('mijoz-id').value = m.id;
  const info = document.getElementById('mijoz-info');
  info.textContent = `✓ ${m.toifa_nomi} ${m.chegirma_foiz>0?'· -'+m.chegirma_foiz+'%':''}`;
  info.style.display = 'inline';
  renderCart();
  if (document.getElementById('checkout-modal')?.classList.contains('show')) prefillActivePaymentAmount();
}

document.getElementById('btn-mijoz-clear')?.addEventListener('click', () => {
  selectedMijoz = null;
  mijozQ && (mijozQ.value='');
  document.getElementById('mijoz-id').value='';
  document.getElementById('mijoz-info').style.display='none';
  renderCart();
  if (document.getElementById('checkout-modal')?.classList.contains('show')) prefillActivePaymentAmount();
});

document.addEventListener('click', e => {
  if (!mijozQ?.contains(e.target) && !mijozResults?.contains(e.target))
    mijozResults && (mijozResults.style.display='none');
});

// ──── Dastlabki Chek ────────────────────────────────────────────────────────────────────────────────
function printPreliminaryReceipt() {
  if (!cart.length) { NHToast.warning("Savat bo'sh"); return; }
  
  let jami = 0;
  let ts = new Date().toLocaleString('uz-UZ');
  
  let html = `<!DOCTYPE html>
<html><head><title>Dastlabki Chek</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Courier New', Courier, monospace !important; }
@page { size: 80mm auto; margin: 2mm; }
body { font-weight: bold; color: #000; font-size: 13px; background: #fff; display: flex; justify-content: center; padding: 0; }
.chek { width: 80mm; padding: 2mm; }
.chek-header { text-align: center; margin-bottom: 3mm; }
.shop-name { font-size: 18px; font-weight: 900; letter-spacing: 1px; color: #000; }
.shop-sub  { font-size: 12px; color: #000; font-weight: bold; margin-top: 1mm; }
.div  { border: none; border-top: 2px dashed #000; margin: 2mm 0; }
.div2 { border: none; border-top: 3px solid #000; margin: 2mm 0; }
.info { display: flex; justify-content: space-between; font-size: 12px; margin: 1mm 0; }
.items-header { display: grid; grid-template-columns: 1fr 20mm 22mm; font-size: 12px; font-weight: 900; padding: 1mm 0; margin-top:1mm; color: #000; border-bottom: 2px solid #000; }
.item { display: grid; grid-template-columns: 1fr 20mm 22mm; font-size: 13px; padding: 1.5mm 0; border-bottom: 2px dotted #000; align-items: start; }
.item:last-child { border-bottom: none; }
.name  { font-weight: 900; word-break: break-word; line-height: 1.3; color: #000; }
.qty   { text-align: center; color: #000; font-weight: 900; }
.price { text-align: right; font-weight: 900; color: #000; }
.total-section { margin: 2mm 0; }
.total-row { display: flex; justify-content: space-between; font-size: 13px; margin: 1.5mm 0; font-weight: 900;}
.total-row.main { font-size: 16px; font-weight: 900; border-top: 2px solid #000; padding-top: 2mm; margin-top: 2mm; }
.chek-footer { text-align: center; font-size: 12px; color: #000; margin-top: 4mm; font-weight: bold; }
.nasiya-box { border: 2px solid #000; border-radius: 3px; padding: 2mm; margin: 2mm 0; text-align:center; font-size:14px; font-weight:900; }
</style>
</head>
<body onload="setTimeout(()=>{window.print();window.close();},500)">
<div class="chek">
  <div class="chek-header">
    <div class="shop-name">${DUKON_NOMI || 'IMezon'}</div>
    <div class="shop-sub">DASTLABKI CHEK (Ma'lumot uchun)</div>
  </div>
  <div class="nasiya-box" style="font-size:16px;">
    ⚠️ PULI TO'LANMAGAN
  </div>
  <hr class="div2">
  <div class="info"><span>Sana:</span><span>${ts}</span></div>
  <div class="info"><span>Manba:</span><span>${im_esc(currentOrderManba || "To'g'ridan-to'g'ri")}</span></div>
  ${currentOrderOlibKetish ? '<div class="nasiya-box">🛍️ OLIB KETISH — QADOQLANSIN</div>' : ''}
  <hr class="div">
  <div class="items-header">
    <span>Nomi</span><span style="text-align:center">Soni</span><span style="text-align:right">Summa</span>
  </div>
  <hr class="div">
`;

  cart.forEach(i => {
    let narx = i.narx;
    if (i.ulg_min > 0 && i.soni >= i.ulg_min && i.ulg_narx > 0) narx = i.ulg_narx;
    
    let sum = narx * i.soni;
    jami += sum;
    html += `
  <div class="item">
    <span class="name">${im_esc(i.nomi)}${currentOrderOlibKetish ? '<br><small>🛍️ qadoqlanadi</small>' : ''}</span>
    <span class="qty">${qtyLabel(i.soni)}</span>
    <span class="price">${sum.toLocaleString('uz-UZ')}</span>
  </div>`;
  });
  
  html += `
  <hr class="div2">
  <div class="total-section">
    <div class="total-row main">
      <span>JAMI (To'lanmagan):</span>
      <span>${jami.toLocaleString('uz-UZ')} so'm</span>
    </div>
  </div>
  <hr class="div">
  <div class="chek-footer">
    <div class="nasiya-box">
      DIQQAT! BU CHEK BILAN MAHSULOT BERILMAYDI! TO'LOV QILINGANIDAN KEYIN ASLI BERILADI.
    </div>
  </div>
</div>
</body></html>`;

  let w = window.open('', '_blank', 'width=400,height=600');
  w.document.write(html);
  w.document.close();
}

// ──── Checkout ──────────────────────────────────────────────────────────────────────────────────────────
document.getElementById('btn-checkout')?.addEventListener('click', doCheckout);
document.addEventListener('keydown', e => {
  if (e.key === 'F12') { 
    e.preventDefault(); 
    if (document.getElementById('checkout-modal')?.classList.contains('show')) {
      doCheckout();
    } else {
      openCheckoutModal();
    }
  }
  if (e.key === 'F2' && posSearch)  { e.preventDefault(); posSearch.focus(); }
  if (e.key === 'Escape') NHModal.closeAll();
  if (e.key === 'Enter' && document.getElementById('chek-modal')?.classList.contains('show')) {
    // Skaner yoki posSearch Enter bosganida savatni tozalamaslik!
    if (document.activeElement === posSearch || _searching) return;
    NHModal.closeAll(); resetCart();
  }
});

function openCheckoutModal() {
  if (!cart.length) { NHToast.warning('Savat bo\'sh'); return; }
  if (!posContextReady) { NHToast.warning('Order serverda tekshirilmoqda, bir oz kuting'); return; }
  NHModal.open('checkout-modal');
  prefillActivePaymentAmount();
}

function batchOrderTotal(items, meta, isNasiya, chegirmaFoiz) {
  const mijCh = isNasiya ? 0 : (selectedMijoz?.chegirma_foiz || 0);
  let tolov = 0;
  items.forEach(item => {
    const eff = isNasiya ? 0 : Math.max(item.kampaniya_ch||0, mijCh, chegirmaFoiz, item.individual_ch||0);
    const useUlg = !isNasiya && item.ulg_min>0 && item.soni>=item.ulg_min && item.ulg_narx>0;
    const base = useUlg ? item.ulg_narx : item.narx;
    const real = Math.round((base * (1-eff/100) + Number.EPSILON) * 100) / 100;
    tolov += real * item.soni;
  });
  tolov = Math.round(tolov);
  if (!meta.olib_ketish && parseInt(meta.stol_id)>0 && xizmatQollanadimi()) {
    tolov += Math.round(tolov * XIZMAT_FOIZ / 100);
  }
  return Math.max(0, tolov);
}

function buildCheckoutSavat(items, isNasiya, chegirmaFoiz) {
  return items.map(item => {
    const mijCh = selectedMijoz?.chegirma_foiz||0;
    const eff = isNasiya ? 0 : Math.max(item.kampaniya_ch||0, mijCh, chegirmaFoiz, item.individual_ch||0);
    const useUlg = !isNasiya && item.ulg_min>0 && item.soni>=item.ulg_min && item.ulg_narx>0;
    return {
      id:item.id, soni:item.soni, narx:useUlg ? item.ulg_narx : item.narx,
      chegirma_foiz:eff, ulg:useUlg, tannarx:0, set_id:item.set_id||null
    };
  });
}

function allocateBatchPayments(requirements, pools) {
  const channels = ['naqd','karta','bank','nasiya'];
  const left = Object.fromEntries(channels.map(k => [k, Math.max(0, Number(pools[k])||0)]));
  const result = [];
  for (const required of requirements) {
    let need = required;
    const row = {naqd:0,karta:0,bank:0,nasiya:0};
    for (const key of channels) {
      const take = Math.min(left[key], need);
      row[key] = take;
      left[key] -= take;
      need -= take;
    }
    if (need > 1) throw new Error(`To‘lov yetarli emas: ${Math.round(need).toLocaleString()} so‘m`);
    result.push(row);
  }
  return result;
}

async function doBatchCheckout() {
  if (selectedVoucher) {
    NHToast.warning('Voucher bir nechta alohida chek orasida bo‘linmaydi. Voucher uchun orderlarni alohida to‘lang.');
    return;
  }
  if (currentPayMode === 'usd') {
    NHToast.warning('USD qaytimini aniq yuritish uchun orderlarni alohida to‘lang. Birga to‘lashda naqd, karta, bank, aralash yoki nasiya ishlaydi.');
    return;
  }

  const isNasiya = currentPayMode === 'nasiya';
  if (isNasiya && !selectedMijoz) { NHToast.error('Nasiya uchun mijoz tanlang'); return; }
  const chegirmaFoiz = isNasiya ? 0 : Math.min(MAX_CH, parseFloat(document.getElementById('chegirma-foiz')?.value||0));
  const groups = currentBatchOrders.map(meta => ({
    meta,
    items:cart.filter(i => Number(i.source_order_id) === Number(meta.order_id))
  }));
  if (groups.some(g => !g.items.length)) { NHToast.error('Birga to‘lash savati to‘liq emas. Orderlarni qayta yuklang.'); return; }

  const requirements = groups.map(g => batchOrderTotal(g.items, g.meta, isNasiya, chegirmaFoiz));
  const expected = requirements.reduce((s,n)=>s+n,0);
  let pools = {naqd:0,karta:0,bank:0,nasiya:0};
  let entered = 0;
  if (currentPayMode === 'naqd') {
    entered = parseFloat(document.getElementById('pay-naqd-input')?.value||'');
    if (!Number.isFinite(entered) || entered < expected) { NHToast.error(`Naqd summa kam. Kerak: ${expected.toLocaleString()} so‘m`); return; }
    pools.naqd = expected;
  } else if (currentPayMode === 'karta') {
    entered = parseFloat(document.getElementById('pay-karta-input')?.value||0) || expected;
    if (entered < expected) { NHToast.error(`Karta summasi kam. Kerak: ${expected.toLocaleString()} so‘m`); return; }
    pools.karta = expected;
  } else if (currentPayMode === 'bank') {
    entered = parseFloat(document.getElementById('pay-bank-input')?.value||0) || expected;
    if (entered < expected) { NHToast.error(`Bank summasi kam. Kerak: ${expected.toLocaleString()} so‘m`); return; }
    pools.bank = expected;
  } else if (currentPayMode === 'nasiya') {
    pools.nasiya = expected;
  } else if (currentPayMode === 'aralash') {
    pools.naqd = parseFloat(document.getElementById('a-naqd')?.value||0);
    pools.karta = parseFloat(document.getElementById('a-karta')?.value||0);
    pools.bank = parseFloat(document.getElementById('a-bank')?.value||0);
    entered = pools.naqd + pools.karta + pools.bank;
    if (entered < expected) {
      NHToast.error(`Aralash to‘lov kam: ${(expected-entered).toLocaleString()} so‘m. Qisman nasiya uchun orderlarni alohida to‘lang.`);
      return;
    }
    // Ortiqcha qism daromadga yozilmaydi; avval naqd, keyin bank/kartadan qisamiz.
    let extra = Math.max(0, entered - expected);
    for (const key of ['naqd','bank','karta']) {
      const cut = Math.min(extra, pools[key]); pools[key] -= cut; extra -= cut;
    }
  }

  const allocations = allocateBatchPayments(requirements, pools);
  const ok = await NHConfirm.show({
    variant:'primary', label:'Birga to‘lash', title:`${groups.length} ta alohida chek yaratiladi`,
    text:`Umumiy to‘lov: ${expected.toLocaleString()} so‘m`,
    sub:'Har bir order o‘z manbasi, xizmat haqqi va chek raqami bilan alohida saqlanadi.',
    confirmText:'To‘lovni yakunlash', btnIcon:'bi-check2-all'
  });
  if (!ok) return;

  const btn = document.getElementById('btn-checkout');
  btn.disabled = true; btn.innerHTML = '<span class="im-spinner"></span> Cheklar yaratilmoqda...';
  const completed = [];
  try {
    for (let idx=0; idx<groups.length; idx++) {
      const {meta,items} = groups[idx];
      const pay = allocations[idx];
      const res = await IMAjax.postJSON(AJAX.sotuvSave, {
        smena_id:SMENA_ID, mijoz_id:selectedMijoz?.id||0, chegirma_foiz:chegirmaFoiz,
        umumiy_chegirma:0, savat:buildCheckoutSavat(items,isNasiya,chegirmaFoiz),
        naqd:pay.naqd, karta:pay.karta, bank:pay.bank, usd:0, usd_kurs:USD_KURS,
        nasiya:pay.nasiya, nasiya_muddat:document.getElementById('nasiya-muddat')?.value||'',
        usd_qaytim_som:0, voucher_kod:'',
        xizmat_foiz:(!meta.olib_ketish && parseInt(meta.stol_id)>0 && xizmatQollanadimi()) ? XIZMAT_FOIZ : 0,
        order_id:meta.order_id
      });
      if (res.status !== 'ok') throw new Error(`Order #${meta.order_id}: ${res.msg||'sotuv xatosi'}`);
      completed.push({data:res.data, items, order_id:Number(meta.order_id)});
    }
  } catch(e) {
    // Oldingi muvaffaqiyatli cheklar qayta yuborilmaydi; qolgan orderlar savatda qoladi.
    const doneIds = new Set(completed.map(x => x.order_id).filter(Boolean));
    if (completed.length) {
      completed.forEach(x => updateTopbarStats(x.data.tolov_summa||0));
      cart = cart.filter(i => !doneIds.has(Number(i.source_order_id)));
      currentBatchOrders = currentBatchOrders.filter(o => !doneIds.has(Number(o.order_id)));
      currentOrderManba = `${currentOrderManba.split(' — ')[0]} — ${currentBatchOrders.length} ta alohida chek`;
      document.getElementById('checkout-modal')?.classList.remove('show');
      renderManbaChip();
      renderCart();
      showBatchCheklar(completed, `Qolgan orderlarda xato: ${e.message}`);
    } else NHToast.error(e.message || 'Birga to‘lash bajarilmadi');
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Sotishni tasdiqlash (Enter/F12)';
    return;
  }

  document.getElementById('checkout-modal')?.classList.remove('show');
  completed.forEach(x => updateTopbarStats(x.data.tolov_summa||0));
  showBatchCheklar(completed);
  resetCart();
  renderManbaChip();
  btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Sotishni tasdiqlash (Enter/F12)';
}

async function doCheckout() {
  if (!cart.length) { NHToast.warning('Savat bo\'sh'); return; }
  if (!posContextReady) { NHToast.warning('Order serverda tekshirilmoqda, bir oz kuting'); return; }
  if (currentBatchOrders.length) { await doBatchCheckout(); return; }
  const { tolov, chegirma, jami, umumiy_ch, qayta } = calcTotals();

  // Voucher to'liq qoplagan bo'lsa (tolov=0) — hech narsa talab etilmaydi
  let naqd=0, karta=0, bank=0, usd=0, usd_k=USD_KURS, nasiya=0;

  if (tolov <= 0) {
    // Voucher yoki chegirma to'liq qopladi — naqd=0 bilan o'tkaziladi
    naqd = 0;
  } else if (currentPayMode==='naqd') { 
    const naqdInput = document.getElementById('pay-naqd-input');
    const kiritilgan = parseFloat(naqdInput?.value||'');
    if (isNaN(kiritilgan) || naqdInput?.value === '') {
      NHToast.error("Naqd summasini kiriting!"); 
      naqdInput?.focus();
      return;
    }
    // Kiritilgan kam bo'lsa — umumiy chegirma (calcTotals hisoblab beradi)
    naqd = Math.min(kiritilgan, tolov); 
  }
  else if (currentPayMode==='karta') { 
    const kiritilgan = parseFloat(document.getElementById('pay-karta-input')?.value||0);
    karta = kiritilgan > 0 ? Math.min(kiritilgan, tolov) : tolov; 
  }
  else if (currentPayMode==='bank') { 
    const kiritilgan = parseFloat(document.getElementById('pay-bank-input')?.value||0);
    bank = kiritilgan > 0 ? Math.min(kiritilgan, tolov) : tolov; 
  }
  else if (currentPayMode==='usd') {
    usd   = parseFloat(document.getElementById('pay-usd-input')?.value||0);
    usd_k = USD_KURS;
    // tolov allaqachon umumiy chegirmadan keyin hisoblangan
    // Qaytim sumdagi bo'lsa — naqd kassadan ayiriladi
  }
  else if (currentPayMode==='nasiya') {
    if (!selectedMijoz) { NHToast.error("Nasiya uchun mijoz tanlang!"); return; }
    nasiya = tolov;
  }
  else if (currentPayMode==='aralash') {
    naqd  = parseFloat(document.getElementById('a-naqd')?.value||0);
    karta = parseFloat(document.getElementById('a-karta')?.value||0);
    bank  = parseFloat(document.getElementById('a-bank')?.value||0);
    const aralash_total = naqd+karta+bank;
    // Umumiy chegirma allaqachon calcTotals da hisoblanib, tolov kamaytilgan
    const qolgan = tolov - aralash_total;
    if (qolgan > 1 && selectedMijoz) nasiya = qolgan;
    else if (qolgan > 1) { NHToast.error(`Qolaydi ${qolgan.toLocaleString()} so'm (nasiya uchun mijoz tanlang)`); return; }
  }

  // Umumiy chegirma bo'lsa — foydalanuvchiga tasdiqlash
  if (umumiy_ch > 0) {
    const ok = await NHConfirm.show({
      variant: 'primary',
      label: 'Sotuv nazorati',
      title: "Chegirmani tasdiqlang",
      text: `${umumiy_ch.toLocaleString('uz-UZ')} so'm umumiy chegirma qo'llanadi.`,
      sub: `Mijoz to'lovi: ${tolov.toLocaleString('uz-UZ')} so'm`,
      confirmText: "Chegirma bilan davom etish",
      btnIcon: 'bi-percent',
      icon: 'bi-percent'
    });
    if (!ok) return;
  }

  const isNasiyaActive = (currentPayMode === 'nasiya' || nasiya > 0);
  if (isNasiyaActive) {
      const ok = await NHConfirm.show({
        variant: 'warning',
        label: 'Nasiya savdosi',
        title: "Narxlar qayta hisoblanadi",
        text: "Barcha chegirmalar va ulgurji narxlar bekor qilinadi.",
        sub: "Nasiya savdosidagi barcha mahsulotlar chakana narxda hisoblanadi.",
        confirmText: "Chakana narxda davom etish",
        icon: 'bi-credit-card-2-front-fill',
        btnIcon: 'bi-arrow-repeat'
      });
      if (!ok) return;
      let true_jami = 0;
      for (const item of cart) true_jami += item.narx * item.soni;
      
      // Nasiya qismini haqiqiy discountsiz summadan qoldiramiz
      if (currentPayMode === 'aralash') {
          nasiya = true_jami - (naqd + karta + bank);
      } else {
          nasiya = true_jami;
      }
  }

  let chegirma_foiz = Math.min(MAX_CH, parseFloat(document.getElementById('chegirma-foiz')?.value||0));
  if (isNasiyaActive) chegirma_foiz = 0;

  const savat = cart.map(item => {
    const mij_ch = selectedMijoz?.chegirma_foiz||0;
    const item_ch = item.individual_ch||0;
    const eff = isNasiyaActive ? 0 : Math.max(item.kampaniya_ch||0, mij_ch, chegirma_foiz, item_ch);
    const use_ulg = isNasiyaActive ? false : (item.ulg_min>0 && item.soni>=item.ulg_min && item.ulg_narx>0);
    return {
      id: item.id,
      soni: item.soni,
      narx: use_ulg ? item.ulg_narx : item.narx,
      chegirma_foiz: eff,
      ulg: use_ulg,
      tannarx: item.tannarx||0,
      set_id: item.set_id || null,  // Set dan qo'shilgan bo'lsa
    };
  });

  const btn = document.getElementById('btn-checkout');
  btn.disabled = true;
  btn.innerHTML = '<span class="im-spinner"></span> Yuklanmoqda...';

  const nasiya_muddat = document.getElementById('nasiya-muddat')?.value || '';

  // USD qaytim (sumdagi qaytim — kassadan ayriladi)
  const usd_qaytim_som = (currentPayMode === 'usd' && qayta > 0) ? qayta : 0;

  const res = await IMAjax.postJSON(AJAX.sotuvSave, {
    smena_id: SMENA_ID,
    mijoz_id: selectedMijoz?.id||0,
    chegirma_foiz: chegirma_foiz,
    umumiy_chegirma: umumiy_ch,
    savat, naqd, karta, bank,
    usd, usd_kurs: usd_k, nasiya,
    nasiya_muddat,
    usd_qaytim_som,
    voucher_kod: isNasiyaActive ? '' : (selectedVoucher?.kod || ''),
    // Xizmat haqi: server foizni o'zi sozlamadan oladi, bu yerdan faqat
    // "yoqilganmi" signali ketadi (kassir ixtiyoriy foiz yubora olmaydi).
    xizmat_foiz: xizmatQollanadimi() ? XIZMAT_FOIZ : 0,
    order_id: currentOrderId  // Sotuvchi orderini tugallandi qilish uchun
  });

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Sotishni tasdiqlash (Enter/F12)';

  if (res.status==='ok') {
    document.getElementById('checkout-modal')?.classList.remove('show');
    showChek(res.data);
    updateTopbarStats(res.data.tolov_summa || 0);
    resetCart();
    renderManbaChip();
  } else {
    NHToast.error(res.msg);
  }
}

// Topbar statistikasini yangilash
let _statSoni = <?= (int)$bugun_stats['sotuv'] ?>;
let _statSumma = <?= (float)$bugun_stats['summa'] ?>;
function updateTopbarStats(qoshilgan_summa) {
  _statSoni++;
  _statSumma += parseFloat(qoshilgan_summa) || 0;
  const soniEl  = document.getElementById('stat-sotuv-soni');
  const summaEl = document.getElementById('stat-sotuv-summa');
  if (soniEl)  soniEl.textContent  = _statSoni;
  if (summaEl) summaEl.textContent = Math.round(_statSumma).toLocaleString('uz-UZ');
}

let lastSotuvId = 0;
let lastSotuvIds = [];
function showChek(data) {
  lastSotuvId = data.sotuv_id || 0;
  lastSotuvIds = lastSotuvId ? [lastSotuvId] : [];
  const lines = cart.map(item => {
    const mij_ch = selectedMijoz?.chegirma_foiz||0;
    const ch_foiz = parseInt(document.getElementById('chegirma-foiz')?.value||0);
    const eff = Math.max(item.kampaniya_ch||0, mij_ch, ch_foiz);
    const use_ulg = item.ulg_min>0 && item.soni>=item.ulg_min && item.ulg_narx>0;
    const base = use_ulg ? item.ulg_narx : item.narx;
    const real = base*(1-eff/100);
    return `<div class="chek-row"><span>${im_esc(item.nomi)} x${qtyLabel(item.soni)}</span><span>${(real*item.soni).toLocaleString()} so'm</span></div>`;
  }).join('');

  document.getElementById('chek-content').innerHTML = `
    <div class="text-center mb-2">
      <strong>${im_esc(DUKON_NOMI)}</strong><br>
      <small>${new Date().toLocaleString('uz-UZ', {timeZone: 'Asia/Tashkent'})}</small>
    </div>
    <hr class="chek-divider">
    <div class="chek-row fw-bold"><span>Chek:</span><span>${data.chek_nomer}</span></div>
    <div class="chek-row"><span>Manba:</span><span>${im_esc(data.manba || "To'g'ridan-to'g'ri")}</span></div>
    ${(data.manba || '').includes('Olib ketish') ? '<div class="text-center fw-bold my-2" style="color:#c2410c">🛍️ OLIB KETISH — ALOHIDA CHEK</div>' : ''}
    <hr class="chek-divider">
    ${lines}
    <hr class="chek-divider">
    <div class="chek-row"><span>Jami:</span><span>${Number(data.jami_summa).toLocaleString()} so'm</span></div>
    ${data.chegirma>0?`<div class="chek-row" style="color:var(--danger)"><span>Chegirma:</span><span>-${Number(data.chegirma).toLocaleString()} so'm</span></div>`:''}
    ${data.xizmat_summa>0?`<div class="chek-row" style="color:#0d6efd"><span>Xizmat haqi (${data.xizmat_foiz}%):</span><span>+${Number(data.xizmat_summa).toLocaleString()} so'm</span></div>`:''}
    <div class="chek-row fw-bold" style="font-size:15px"><span>TO'LOV:</span><span>${Number(data.tolov_summa).toLocaleString()} so'm</span></div>
    ${data.qayta_pul>0?`<div class="chek-row" style="color:var(--success)"><span>Qayta pul:</span><span>${Number(data.qayta_pul).toLocaleString()} so'm</span></div>`:''}
    ${data.usd_qaytim_som>0?`<div class="chek-row" style="color:var(--success);font-weight:700"><span>💵 USD qaytim (so'mda):</span><span>${Number(data.usd_qaytim_som).toLocaleString()} so'm</span></div>`:''}
    <hr class="chek-divider">
    <div class="text-center text-muted" style="font-size:11px">Xarid uchun rahmat! ✔️</div>`;

  NHModal.open('chek-modal');
}

function showBatchCheklar(completed, warning = '') {
  lastSotuvIds = completed.map(x => Number(x.data?.sotuv_id)||0).filter(Boolean);
  lastSotuvId = lastSotuvIds[0] || 0;
  const total = completed.reduce((s,x)=>s+(Number(x.data?.tolov_summa)||0),0);
  document.getElementById('chek-content').innerHTML = `
    <div class="text-center mb-3">
      <div style="font-size:30px;color:var(--success)"><i class="bi bi-check2-circle"></i></div>
      <strong>${completed.length} ta alohida chek yaratildi</strong>
      <div class="text-muted" style="font-size:12px">Umumiy: ${total.toLocaleString()} so‘m</div>
    </div>
    ${warning ? `<div class="im-alert im-alert-warning mb-2">${im_esc(warning)}</div>` : ''}
    ${completed.map((x,idx) => `
      <div class="im-card p-2 mb-2" style="border-left:4px solid ${String(x.data?.manba||'').includes('Olib ketish')?'#f97316':'#22c55e'}">
        <div class="d-flex justify-content-between gap-2"><strong>${idx+1}. ${im_esc(x.data?.manba||'Order')}</strong><strong>${Number(x.data?.tolov_summa||0).toLocaleString()} so‘m</strong></div>
        <div class="d-flex justify-content-between align-items-center mt-1"><span class="text-muted fs-xs">${im_esc(x.data?.chek_nomer||'')}</span>
          <button class="im-btn im-btn-sm im-btn-outline" onclick="printSaleReceipt(${Number(x.data?.sotuv_id)||0})"><i class="bi bi-printer"></i> Chop etish</button>
        </div>
      </div>`).join('')}`;
  NHModal.open('chek-modal');
}

function printSaleReceipt(sotuvId) {
  if (!sotuvId) return;
  window.open(`${im_BASE}print/chek.php?id=${sotuvId}&auto=1`, '_blank',
    'width=460,height=750,toolbar=0,menubar=0,location=0,status=0,scrollbars=1');
}

// ──── Smena yopish ────────────────────────────────────────────────────────────────────────────
const fmt = n => Number(n||0).toLocaleString('uz-UZ',{maximumFractionDigits:0});

document.getElementById('btn-smena-close')?.addEventListener('click', () => {
  // Har safar modalni tozalab ochish
  document.getElementById('smena-confirm-step').style.display = 'block';
  document.getElementById('smena-hisobot-step').style.display = 'none';
  document.getElementById('smena-modal-footer').innerHTML = `
    <button class="im-btn im-btn-outline" data-modal-close onclick="NHModal.closeAll()">Bekor</button>
    <button class="im-btn im-btn-danger" id="btn-smena-close-confirm">
      <i class="bi bi-stop-circle-fill"></i> Smenani yopish
    </button>`;
  attachSmenaConfirm();
  NHModal.open('smena-close-modal');
});

function attachSmenaConfirm() {
  document.getElementById('btn-smena-close-confirm')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-smena-close-confirm');
    btn.disabled = true;
    btn.innerHTML = '<span class="im-spinner"></span> Yopilmoqda...';

    const res = await IMAjax.post(AJAX.smenaClose, { smena_id: SMENA_ID });

    if (res.status === 'ok') {
      const d = res.data;
      // Hisobot qadamiga o'tish
      document.getElementById('smena-confirm-step').style.display = 'none';
      document.getElementById('smena-hisobot-step').style.display = 'block';

      const topRows = (d.top_mah||[]).map((m,i) =>
        `<tr><td>${i+1}</td><td>${im_esc(m.nomi)}</td><td>${qtyLabel(m.soni)} birlik</td><td>${fmt(m.summa)} so'm</td></tr>`
      ).join('');

      document.getElementById('smena-hisobot-content').innerHTML = `
        <div style="text-align:center;margin-bottom:12px">
          <div style="font-size:32px">✅</div>
          <div style="font-weight:800;font-size:16px;color:var(--success)">Smena #${SMENA_ID} yopildi!</div>
          <div style="font-size:11px;color:var(--muted)">${d.boshlanish} → ${d.yopildi}</div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <div class="im-card p-2 text-center">
              <div class="text-muted fs-xs">Jami sotuv</div>
              <div class="fw-bold" style="font-size:20px">${d.sotuv_soni} ta</div>
            </div>
          </div>
          <div class="col-6">
            <div class="im-card p-2 text-center">
              <div class="text-muted fs-xs">Jami tushum</div>
              <div class="fw-bold num" style="font-size:18px;color:var(--accent-dark)">${fmt(d.jami_summa)} so'm</div>
            </div>
          </div>
        </div>
        <table style="width:100%;font-size:12.5px;border-collapse:collapse">
          <tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">💵 Boshlang'ich naqd</td>
            <td style="padding:5px 4px;text-align:right;font-weight:600;color:var(--success)">${fmt(d.ochish_naqd)} so'm</td>
          </tr>
          <tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">💵 Naqd (sotuv)</td>
            <td style="padding:5px 4px;text-align:right;font-weight:600">${fmt(d.naqd)} so'm</td>
          </tr>
          ${d.nasiya_tolov_naqd > 0 ? `<tr style="border-bottom:1px solid var(--border-light);background:#f0fdf4">
            <td style="padding:5px 4px;color:#16a34a;font-weight:600">📈 Nasiya to'lovi (naqd)</td>
            <td style="padding:5px 4px;text-align:right;color:#16a34a;font-weight:700">+${fmt(d.nasiya_tolov_naqd)} so'm</td>
          </tr>` : ''}
          <tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">${IM_TT_LABELS_SHORT.karta}</td>
            <td style="padding:5px 4px;text-align:right;font-weight:600">${fmt(d.karta)} so'm</td>
          </tr>
          ${d.bank > 0 ? `<tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">${IM_TT_LABELS_SHORT.bank}</td>
            <td style="padding:5px 4px;text-align:right;font-weight:600">${fmt(d.bank)} so'm</td>
          </tr>` : ''}
          ${d.usd > 0 ? `<tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">💲 USD ($${d.usd})</td>
            <td style="padding:5px 4px;text-align:right;font-weight:600">${fmt(d.usd_som)} so'm</td>
          </tr>` : ''}
          ${d.usd_qaytim > 0 ? `<tr style="border-bottom:1px solid var(--border-light);background:#fff7ed">
            <td style="padding:5px 4px;color:#ea580c;font-weight:600">💵 USD qaytim (so'mda)</td>
            <td style="padding:5px 4px;text-align:right;color:#ea580c;font-weight:700">-${fmt(d.usd_qaytim)} so'm</td>
          </tr>` : ''}
          ${d.nasiya > 0 ? `<tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">📋 Nasiya</td>
            <td style="padding:5px 4px;text-align:right;color:var(--warning);font-weight:600">${fmt(d.nasiya)} so'm</td>
          </tr>` : ''}
          ${d.chegirma > 0 ? `<tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">✔️ Chegirma</td>
            <td style="padding:5px 4px;text-align:right;color:var(--danger);font-weight:600">-${fmt(d.chegirma)} so'm</td>
          </tr>` : ''}
          ${d.vozvrat > 0 ? `<tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">↩️ Vozvrat (qaytarilgan)</td>
            <td style="padding:5px 4px;text-align:right;color:var(--danger);font-weight:600">-${fmt(d.vozvrat)} so'm</td>
          </tr>` : ''}
          ${d.tannarx > 0 ? `<tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">📦 Tannarx</td>
            <td style="padding:5px 4px;text-align:right;color:var(--danger);font-weight:600">-${fmt(d.tannarx)} so'm</td>
          </tr>` : ''}
          <tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">💸 Harajatlar</td>
            <td style="padding:5px 4px;text-align:right;color:var(--danger);font-weight:600">-${fmt(d.harajat)} so'm</td>
          </tr>
          ${d.qozon_isrofi > 0 ? `<tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:5px 4px;color:var(--muted)">🗑️ Qozon isrofi</td>
            <td style="padding:5px 4px;text-align:right;color:var(--danger);font-weight:600">-${fmt(d.qozon_isrofi)} so'm</td>
          </tr>` : ''}
          ${d.inkasso > 0 ? `<tr style="border-bottom:1px dashed #c4b5fd;background:#faf5ff">
            <td style="padding:5px 4px;color:#7c3aed;font-weight:700">💸 Kassadan olindi</td>
            <td style="padding:5px 4px;text-align:right;color:#7c3aed;font-weight:700">${fmt(d.inkasso)} so'm</td>
          </tr>
          <tr style="background:#faf5ff">
            <td colspan="2" style="padding:2px 4px;font-size:10.5px;color:#9ca3af">⚠ Inkasso foyda hisobiga kirmaydi — faqat kassa chiqimi</td>
          </tr>` : ''}
          <tr style="background:var(--bg);font-weight:800;border-top:2px solid var(--border)">
            <td style="padding:7px 4px">📈 Sof foyda</td>
            <td style="padding:7px 4px;text-align:right;font-size:15px;color:${d.sof_foyda>=0?'var(--success)':'var(--danger)'}">${fmt(d.sof_foyda)} so'm</td>
          </tr>
        </table>
        ${topRows ? `
        <div style="margin-top:12px">
          <div style="font-weight:700;font-size:12px;margin-bottom:6px">✔️ Top mahsulotlar:</div>
          <table style="width:100%;font-size:11.5px;border-collapse:collapse">
            <thead><tr style="background:var(--primary);color:#fff">
              <th style="padding:5px 6px">#</th><th style="padding:5px 6px">Mahsulot</th>
              <th style="padding:5px 6px">Soni</th><th style="padding:5px 6px;text-align:right">Summa</th>
            </tr></thead>
            <tbody>${topRows}</tbody>
          </table>
        </div>` : ''}
      `;

      // Footer: chiqish tugmasi
      document.getElementById('smena-modal-footer').innerHTML = `
        <button class="im-btn im-btn-outline im-btn-sm" onclick="printSmenaHisobot(${SMENA_ID})">
          <i class="bi bi-printer"></i> Chop etish
        </button>
        <button class="im-btn im-btn-primary" onclick="location.replace('/login.php')">
          <i class="bi bi-box-arrow-right"></i> Chiqish
        </button>`;

      NHToast.success(res.msg);
    } else {
      NHToast.error(res.msg);
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-stop-circle-fill"></i> Smenani yopish';
    }
  });
}

function printSmenaHisobot(smenaId) {
  window.open(`${im_BASE}print/smena.php?id=${smenaId}`, '_blank',
    'width=500,height=700,toolbar=0,menubar=0,scrollbars=1');
}

// Birinchi attach
attachSmenaConfirm();

function resetCart() {
  cart = [];
  resetOrderContext();
  clearCartStorage(); // Sotuv bo'ldi — localStorage dan ham tozalaymiz
  selectedMijoz = null;
  if (mijozQ) mijozQ.value='';
  document.getElementById('mijoz-id').value='';
  document.getElementById('mijoz-info').style.display='none';
  document.getElementById('chegirma-foiz').value=0;
  ['pay-naqd-input','pay-karta-input','pay-bank-input','pay-usd-input','a-naqd','a-karta','a-bank'].forEach(id=>{
    const el=document.getElementById(id); if(el) el.value='';
  });
  renderCart();
  
  // Sotuvdan keyin mahsulotlar qoldig'ini sahifani yangilamasdan yangilash
  if (typeof loadCategoryProductsFixed === 'function') {
      loadCategoryProductsFixed(currentKat);
  }
}

// Chek print — yangi chek.php oynasini ochish
document.getElementById('btn-chek-print')?.addEventListener('click', () => {
  if (lastSotuvIds.length > 1) {
    lastSotuvIds.forEach((id,idx) => setTimeout(() => printSaleReceipt(id), idx*250));
  } else if (lastSotuvId) {
    // To'liq 80mm chek (Ctrl+P → PDF yoki Xprinter)
    printSaleReceipt(lastSotuvId);
  } else {
    // Fallback: eski usul
    const content = document.getElementById('chek-content').innerHTML;
    const w = window.open('', '_blank', 'width=400,height=600');
    w.document.write(`<!DOCTYPE html><html><head><title>Chek</title>
      <style>body{font-family:monospace;font-size:12px;padding:10px;width:72mm}
      .chek-row{display:flex;justify-content:space-between;margin:2px 0}
      .chek-divider{border:none;border-top:1px dashed #999;margin:6px 0}
      @media print{@page{size:72mm auto;margin:2mm}}</style></head>
      <body>${content}</body></html>`);
    w.document.close();
    setTimeout(()=>{ w.print(); w.close(); }, 200);
  }
});

// ──── Smena yopish ──────────────────────────────────────────────────────────────────────────────────
document.getElementById('btn-smena-close')?.addEventListener('click', () => {
  NHModal.open('smena-close-modal');
});

document.getElementById('btn-smena-close-confirm')?.addEventListener('click', async () => {
  const res = await IMAjax.post(AJAX.smenaClose, { smena_id: SMENA_ID });
  if (res.status==='ok') {
    NHToast.success(res.msg);
    setTimeout(()=>location.replace('/login.php'),1000);
  } else NHToast.error(res.msg);
});

// ──── Inkasso (Kassadan olish) ──────────────────────────────────────────────────────────
document.getElementById('btn-inkasso')?.addEventListener('click', () => {
  NHModal.open('inkasso-modal');
  document.getElementById('inkasso-summa').value = '';
  document.getElementById('inkasso-izoh').value = '';
  document.getElementById('inkasso-tur').value = 'naqd';
});

document.getElementById('btn-inkasso-save')?.addEventListener('click', async () => {
  const summa = parseFloat(document.getElementById('inkasso-summa').value || 0);
  const izoh  = document.getElementById('inkasso-izoh').value.trim();
  const tur   = document.getElementById('inkasso-tur').value;
  if (summa <= 0) { NHToast.error('Summa noto\'g\'ri'); return; }

  const btn = document.getElementById('btn-inkasso-save');
  btn.disabled = true;
  btn.textContent = '...';

  const res = await IMAjax.post(AJAX.inkasso, {
    smena_id: SMENA_ID, summa, tur, izoh
  });
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-circle"></i> Saqlash';

  if (res.status === 'ok') {
    NHToast.success(`Kassadan ${summa.toLocaleString()} so'm olindi!`);
    NHModal.close('inkasso-modal');
  } else {
    NHToast.error(res.msg);
  }
});

// ──── IMAjax.postJSON qo'shish ──────────────────────────────────────────────────────────
IMAjax.postJSON = async function(url, data) {
  try {
    const r = await fetch(url, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(data)
    });
    return await r.json();
  } catch(e) { return {status:'error',msg:'Tarmoq xatosi'}; }
};

// ──── Yangi Mijoz qo'shish (POS) ────────────────────────────────────────────────────
document.getElementById('btn-new-mijoz')?.addEventListener('click', () => {
  document.getElementById('nm-ism').value = '';
  document.getElementById('nm-tel').value = '';
  NHModal.open('new-mijoz-modal');
  setTimeout(() => document.getElementById('nm-ism').focus(), 200);
});

document.getElementById('btn-nm-save')?.addEventListener('click', async () => {
  const ism = document.getElementById('nm-ism').value.trim();
  const tel = document.getElementById('nm-tel').value.trim();
  if (!ism) { NHToast.error('Ism kiritish shart!'); return; }

  const btn = document.getElementById('btn-nm-save');
  btn.disabled = true;

  const res = await IMAjax.post(window.im_BASE + 'admin/ajax/mijoz-save', {
    id: '', ism, tel, toifa: '', manzil: '', izoh: ''
  });
  btn.disabled = false;

  if (res.status === 'ok') {
    NHToast.success(`✅ "${ism}" mijoz qo'shildi!`);
    NHModal.close('new-mijoz-modal');
    // Engi qo'shilgan mijozni tanlash
    if (res.data && res.data.id) {
      selectMijoz({ id: res.data.id, ism, telefon: tel, toifa_nomi: '', chegirma_foiz: 0, nasiya_qoldiq: 0 });
    } else {
      // Telefon yoki ism orqali qidirish
      document.getElementById('mijoz-q').value = ism;
      setTimeout(() => document.getElementById('mijoz-q').dispatchEvent(new Event('input')), 300);
    }
  } else {
    NHToast.error(res.msg);
  }
});
// ──── Voucher tekshiruv ────────────────────────────────────────────────────────────────────────────
document.getElementById('btn-voucher-check')?.addEventListener('click', async () => {
  const kod = document.getElementById('voucher-input')?.value.trim();
  if (!kod) return NHToast.warning('Voucher kodi kiriting');
  const totals = calcTotals();
  const res = await fetch(`${im_BASE}admin/ajax/voucher-check?kod=${encodeURIComponent(kod)}&summa=${totals.tolov}`)
                    .then(r=>r.json()).catch(()=>({status:'error',msg:'Xatolik'}));
  const infoEl  = document.getElementById('voucher-info');
  const infoTxt = document.getElementById('voucher-info-text');
  if (res.status === 'ok') {
    selectedVoucher = { 
       kod, 
       tur: res.data.tur, 
       qiymat: parseFloat(res.data.qiymat)||0,
       max_chegirma: parseFloat(res.data.max_chegirma)||0,
       min_summa: parseFloat(res.data.min_summa)||0,
       xabar: res.data.xabar 
    };
    infoEl.style.cssText = 'display:block;margin-bottom:6px;padding:6px 10px;border-radius:8px;font-size:12px;background:#d1fae588;border:1px solid #10b98144;color:#065f46';
    infoTxt.textContent = '✅ ' + res.data.xabar;
    document.getElementById('btn-voucher-clear').style.display = '';
    renderCart();
    if (document.getElementById('checkout-modal')?.classList.contains('show')) prefillActivePaymentAmount();
    NHToast.success(res.data.xabar, 2000);
  } else {
    selectedVoucher = null;
    infoEl.style.cssText = 'display:block;margin-bottom:6px;padding:6px 10px;border-radius:8px;font-size:12px;background:#fee2e288;border:1px solid #ef444444;color:#991b1b';
    infoTxt.textContent = '❌ ' + (res.msg || 'Voucher topilmadi');
    document.getElementById('btn-voucher-clear').style.display = 'none';
    renderCart();
    if (document.getElementById('checkout-modal')?.classList.contains('show')) prefillActivePaymentAmount();
  }
});

document.getElementById('btn-voucher-clear')?.addEventListener('click', () => {
  selectedVoucher = null;
  document.getElementById('voucher-input').value = '';
  document.getElementById('voucher-info').style.display = 'none';
  document.getElementById('btn-voucher-clear').style.display = 'none';
  renderCart();
  if (document.getElementById('checkout-modal')?.classList.contains('show')) prefillActivePaymentAmount();
  NHToast.info('Voucher bekor qilindi');
});
</script>

<!-- ──── KUTILGAN ORDERLAR MODAL ──────────────────────────────────────────────────────── -->
<div class="im-overlay" id="pending-modal">
  <div class="im-modal" style="max-width:620px;width:96%">
    <div class="im-modal-header">
      <i class="bi bi-basket3-fill" style="color:#f59e0b;font-size:20px"></i>
      <span class="im-modal-title">Sotuvchi orderlari</span>
      <span class="im-badge ms-2" id="pm-count"
            style="background:rgba(245,158,11,.15);color:#b45309;border:1px solid rgba(245,158,11,.3)">0 ta</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body" style="padding:0">
      <div id="pending-list" style="min-height:120px">
        <div class="text-center text-muted p-4">
          <i class="bi bi-hourglass-split" style="font-size:32px;opacity:.3"></i>
          <p class="mt-2">Yuklanmoqda...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// ──── PENDING ORDERS ──────────────────────────────────────────────────────────────────────────────────
async function openPendingOrders() {
  NHModal.open('pending-modal');
  const list = document.getElementById('pending-list');
  list.innerHTML = '<div class="text-center text-muted p-4"><i class="bi bi-hourglass-split" style="font-size:32px;opacity:.3"></i><p class="mt-2">Yuklanmoqda...</p></div>';

  const r = await fetch(im_BASE + 'dukon/ajax/pending-orders');
  const d = await r.json();
  const orders = d.orders || [];

  document.getElementById('pm-count').textContent = orders.length + ' ta';

  if (!orders.length) {
    list.innerHTML = '<div class="text-center text-muted p-5"><i class="bi bi-basket-x" style="font-size:48px;opacity:.25"></i><p class="mt-2">Hozircha kutilayotgan order yo\'q</p></div>';
    updatePendingBadge(0); return;
  }

  updatePendingBadge(orders.length);

  list.innerHTML = `
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="background:#f8fafc;font-size:11px;color:#6c757d;text-transform:uppercase;letter-spacing:.5px">
          <th style="padding:10px 16px;text-align:left">Sotuvchi</th>
          <th style="padding:10px 16px;text-align:left">Manba</th>
          <th style="padding:10px 16px;text-align:center">Tovar</th>
          <th style="padding:10px 16px;text-align:right">Summa</th>
          <th style="padding:10px 16px;text-align:center">Vaqt</th>
          <th style="padding:10px 8px;text-align:center" colspan="2"></th>
        </tr>
      </thead>
      <tbody>
        ${orders.map(o => `
          <tr id="po-row-${o.id}" style="border-top:1px solid #f1f5f9;transition:.15s" onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background=''">
            <td style="padding:12px 16px;font-weight:600;font-size:13px">
              <i class="bi bi-person-fill" style="color:#f59e0b"></i> ${im_esc(o.sotuvchi_ism)}
            </td>
            <td style="padding:12px 16px;font-size:12px;color:#6c757d">
              ${o.olib_ketish
                ? '🛍️ ' + im_esc(o.mijoz_ism) + ' — <b>Olib ketish</b>'
                : (o.mijoz_ism ? '📍 ' + im_esc(o.mijoz_ism) : '<span style="opacity:.5">Noma\'lum</span>')}
            </td>
            <td style="padding:12px 16px;text-align:center">
              <span style="background:#f59e0b22;color:#b45309;border:1px solid #f59e0b44;
                           border-radius:20px;padding:2px 10px;font-size:12px;font-weight:700">
                ${o.item_soni} ta
              </span>
            </td>
            <td style="padding:12px 16px;text-align:right;font-weight:700;font-size:13px">
              ${Math.round(o.jami_summa || 0).toLocaleString('uz-UZ')} so'm
            </td>
            <td style="padding:12px 16px;text-align:center;font-size:11px;color:#6c757d">
              ${o.created_at ? o.created_at.slice(11,16) : '—'}
            </td>
            <td style="padding:12px 4px;text-align:center">
              <button onclick="loadPendingOrder(${o.id})"
                      style="background:#10b981;color:#fff;border:none;border-radius:8px;
                             padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;
                             transition:.15s" title="Savatchaga yuklash">
                <i class="bi bi-box-arrow-in-down"></i> Yuklash
              </button>
            </td>
            <td style="padding:12px 4px;text-align:center">
              <button onclick="cancelPendingOrder(${o.id}, ${o.stol_id ? parseInt(o.stol_id) : 'null'}, ${o.olib_ketish ? 'true' : 'false'})"
                      style="background:#ef4444;color:#fff;border:none;border-radius:8px;
                             padding:6px 10px;font-size:12px;cursor:pointer;transition:.15s"
                      title="Orderni bekor qilish">
                <i class="bi bi-x-circle-fill"></i>
              </button>
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

// Stolda, shu orderdan tashqari, hali kassaga yuborilmagan (ofitsant/oshxona
// tarafida turgan) Olib ketish buyurtmalari sonini qaytaradi. Asosiy order
// to'langanda/bekor qilinganda bunday "yetim" qolib ketishi mumkin bo'lgan
// olib ketishlar haqida kassirni ogohlantirish uchun ishlatiladi.
async function dukonOchiqOlibKetishSoni(stolId, excludeOrderId) {
  if (!stolId) return 0;
  try {
    const r = await fetch(im_BASE + 'dukon/ajax/get-hall.php');
    const d = await r.json();
    if (d.status !== 'ok') return 0;
    const stol = (d.data.stollar || []).find(s => Number(s.id) === Number(stolId));
    if (!stol || !Array.isArray(stol.orders)) return 0;
    return stol.orders.filter(o => o.olib_ketish && o.holat === 'ochiq'
      && Number(o.id) !== Number(excludeOrderId)).length;
  } catch { return 0; }
}

async function loadPendingOrder(orderId) {
  const r = await fetch(im_BASE + 'dukon/ajax/pending-orders?order_id=' + orderId);
  const d = await r.json();

  if (d.status !== 'ok' || !d.items?.length) {
    NHToast.error('Order bo\'sh yoki topilmadi'); return;
  }

  // MUHIM: stol buyurtmasi savatga QO'SHILMAYDI, savatni ALMASHTIRADI.
  // Ilgari mavjud qatorlarga `existing.soni += soni` qilinardi — natijada
  // kassirning tugallanmagan to'g'ridan-to'g'ri savdosi stol buyurtmasiga
  // qo'shilib ketib, mijozdan ortiqcha pul olinishi mumkin edi.
  if (cart.length) {
    const n = cart.length;
    const ok = await NHConfirm.show({
      variant: 'warning',
      title: "Savat almashtiriladi",
      text: `Joriy savatda ${n} ta mahsulot bor. Stol buyurtmasi yuklanganda ular olib tashlanadi.`,
      sub: "Bu mahsulotlar stol buyurtmasiga qo'shilmaydi.",
      confirmText: "Savatni almashtirish",
      cancelText: "Joriy savatda qolish",
      btnIcon: 'bi-arrow-left-right'
    });
    if (!ok) return;
    cart.length = 0;
  }

  // Savatga yuklash
  d.items.forEach(item => {
    const ulg_min  = parseInt(item.ulg_min)      || 0;
    const ulg_narx = parseFloat(item.ulg_narx)   || 0;
    const soni     = parseFloat(item.soni);
    const narx     = parseFloat(item.narx) || 0;

    cart.push({
      id:           parseInt(item.mahsulot_id),
      nomi:         item.nomi,
      birlik:       item.birlik || 'dona',
      narx:         narx,
      soni:         soni,
      qoldiq:       999,
      kampaniya_ch: 0,
      ulg_min:      ulg_min,
      ulg_narx:     ulg_narx,
      tannarx:      0,
      individual_ch:0,
      // Ofitsant setdan qo'shgan qator — chekda set bo'lib guruhlanadi
      set_id:       item.set_id ? parseInt(item.set_id) : null,
      set_nomi:     item.set_nomi || null,
      // Shu qatordan nechtasi saboyga olinadi — o'sha ulushdan
      // xizmat haqi olinmaydi (server ham buni o'zi tekshiradi)
      olib_ketish_soni: parseFloat(item.olib_ketish_soni) || 0,
    });
  });

  // Agar sotuvchi nasiya muddat yuborganligini tekshirish
  if (d.nasiya_muddat) {
    const nasMuddat = document.getElementById('nasiya-muddat');
    if (nasMuddat) nasMuddat.value = d.nasiya_muddat;
    // Nasiya tabni faollashtirish
    currentPayMode = 'nasiya';
    document.querySelectorAll('.pay-tab').forEach(t => t.classList.toggle('active', t.dataset.pay === 'nasiya'));
    document.querySelectorAll('.pay-input').forEach(p => p.classList.remove('visible'));
    document.getElementById('pay-nasiya-wrap')?.classList.add('visible');
    NHToast.info(`📅 Nasiya muddati: ${d.nasiya_muddat}`, 3000);
  }

  currentOrderId = orderId; // Yuklangan order ID ni saqlash
  currentOrderStolId = parseInt(d.stol_id) || 0;
  currentOrderOlibKetish = !!d.olib_ketish;
  currentBatchOrders = [];
  posContextReady = true;
  currentOrderManba = (d.olib_ketish && d.stol_id)
    ? (d.mijoz_ism || 'Stol') + ' — Olib ketish'
    : (d.mijoz_ism || '');
  renderManbaChip();

  // Xizmat haqi belgisini tiklaymiz (oldingi savdodan o'chirilgan bo'lishi mumkin)
  const xcb = document.getElementById('xizmat-toggle');
  if (xcb) xcb.checked = true;

  renderCart();
  NHModal.close('pending-modal');
  NHToast.success('✅ Order savatchaga yuklandi!');
  checkPendingCount();

  // Asosiy (olib_ketish=false) orderni to'lashga yuklayapmiz — shu stolda
  // hali kassaga yuborilmagan Olib ketish qolib ketmasligi uchun ogohlantirish.
  if (d.stol_id && !d.olib_ketish) {
    const ok_soni = await dukonOchiqOlibKetishSoni(d.stol_id, orderId);
    if (ok_soni > 0) {
      NHToast.warning(`⚠️ Diqqat: shu stolda hali kassaga yuborilmagan ${ok_soni} ta Olib ketish buyurtmasi bor`, 6000);
    }
  }
}


async function cancelPendingOrder(orderId, stolId, isOlibKetish) {
  let ogohSub = "Order kassadagi kutilayotgan buyurtmalar ro'yxatidan olib tashlanadi.";
  if (stolId && !isOlibKetish) {
    const ok_soni = await dukonOchiqOlibKetishSoni(stolId, orderId);
    if (ok_soni > 0) {
      ogohSub = `⚠️ Diqqat: shu stolda hali kassaga yuborilmagan ${ok_soni} ta Olib ketish buyurtmasi bor — bekor qilishdan oldin ofitsant bilan tekshiring.`;
    }
  }
  const ok = await NHConfirm.show({
    variant: 'danger',
    title: "Orderni bekor qilish",
    text: "Tanlangan order bekor qilinadi.",
    sub: ogohSub,
    confirmText: "Orderni bekor qilish",
    icon: 'bi-receipt-cutoff',
    btnIcon: 'bi-x-octagon-fill'
  });
  if (!ok) return;
  const fd = new URLSearchParams({ action: 'cancel', order_id: orderId });
  const r = await fetch(im_BASE + 'dukon/ajax/pending-orders', { method: 'POST', body: fd });
  const d = await r.json();
  if (d.status === 'ok') {
    document.getElementById('po-row-' + orderId)?.remove();
    NHToast.success('Order bekor qilindi');
    checkPendingCount();
  } else {
    NHToast.error(d.msg || 'Xatolik');
  }
}

function updatePendingBadge(count) {
  const badge = document.getElementById('pending-badge');
  if (count > 0) {
    badge.textContent = count;
    badge.style.display = 'flex';
  } else {
    badge.style.display = 'none';
  }
}

// Har 30 soniyada pending orderlarni tekshir
async function checkPendingCount() {
  try {
    const r = await fetch(im_BASE + 'dukon/ajax/pending-orders');
    const d = await r.json();
    updatePendingBadge((d.orders || []).length);
  } catch(e){}
}
checkPendingCount();
setInterval(checkPendingCount, 30000);
</script>
</body>
</html>
