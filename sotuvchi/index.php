<?php
// ============================================================
//  IMezon — Sotuvchi paneli (Zal xaritasi asosida)
//  Oqim: Zal xaritasi (stollar) → stolga bosish → mahsulot/savatcha
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['sotuvchi']);

$filial_id   = (int)$_SESSION['im_filial_id'];
$sotuvchi    = $_SESSION['im_ism'] ?? 'Sotuvchi';
$db          = new Cyber();
$filial      = $db->row("SELECT nomi FROM im_filiallar WHERE id=$filial_id LIMIT 1");
$filial_nomi = $filial['nomi'] ?? 'Filial';

// Kategoriyalar
$kategoriyalar = $db->rows(
    "SELECT k.id, k.nomi, k.rang
     FROM im_kategoriyalar k
     JOIN im_mahsulotlar m ON m.kategoriya_id=k.id AND m.status=1
     JOIN im_filial_qoldiq fq ON fq.mahsulot_id=m.id AND fq.filial_id=$filial_id AND fq.soni>0
     WHERE k.status=1
     GROUP BY k.id
     ORDER BY k.nomi ASC"
);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,viewport-fit=cover">
<title>Sotuvchi — IMezon</title>
<!-- .im-overlay / .im-modal* / .im-btn qoidalari SHU FAYLDA. Ilgari
     ulanmagani uchun "Stol buyurtmalari" modali display:none ololmay,
     sahifaning pastida uslubsiz holda doim ko'rinib turardi. -->
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css?v=<?= rawurlencode(im_VERSION) ?>">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/bi.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── Reset & Root ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --primary:#1a1a2e;--accent:#e2b96f;--accent-d:#c9a058;
  --bg:#f0f2f5;--card:#fff;--text:#1e1e2e;--muted:#6c757d;
  --border:#e2e8f0;--success:#10b981;--danger:#ef4444;
  --hold:#7c3aed;--hold-light:#ede9fe;--warn:#f59e0b;
}
html,body{height:100%;overflow:hidden;overscroll-behavior-y:none}
/* min-height:0 — main.css dagi body{min-height:100vh} ni bekor qiladi:
   mobil brauzerda 100vh > 100dvh, aks holda tag panel ekrandan chiqib ketardi. */
body{font-family:'Inter',sans-serif;background:var(--bg);display:flex;flex-direction:column;
     height:100dvh;min-height:0;font-size:16px;line-height:normal}
button,[onclick],.stol-card,.prod-card,.kat-btn,.zona-tab,.quick-card{-webkit-tap-highlight-color:transparent}

/* ── Topbar ── */
.topbar{
  min-height:52px;background:var(--primary);display:flex;align-items:center;
  padding-top:env(safe-area-inset-top,0px);
  padding-left:calc(12px + env(safe-area-inset-left,0px));
  padding-right:calc(12px + env(safe-area-inset-right,0px));
  gap:8px;flex-shrink:0;z-index:100;
  box-shadow:0 2px 12px rgba(0,0,0,.25);
}
.topbar-logo{font-size:16px;font-weight:800;color:#fff;letter-spacing:.5px;flex-shrink:0}
.topbar-logo span{color:var(--accent)}
.topbar-filial{
  background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);
  border-radius:6px;padding:3px 8px;font-size:11px;color:rgba(255,255,255,.8);
  display:flex;align-items:center;gap:4px;flex-shrink:1;min-width:0;
  max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.topbar-filial i{flex-shrink:0}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:6px;flex-shrink:0}
.topbar-name{font-size:12px;color:rgba(255,255,255,.75);font-weight:500}
.topbar-logout{
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);
  color:rgba(255,255,255,.7);border-radius:6px;padding:4px 8px;
  font-size:11px;cursor:pointer;text-decoration:none;display:flex;align-items:center;gap:4px;
  transition:.15s;
}
.topbar-logout:hover{background:rgba(239,68,68,.25);color:#f87171}

/* ── Cart toggle (mobile) ── */
.cart-toggle-btn{
  display:none;position:relative;
  background:var(--accent);border:none;border-radius:8px;
  padding:5px 10px;color:#fff;font-size:13px;font-weight:700;
  cursor:pointer;align-items:center;gap:5px;
}
.cart-toggle-btn .badge{
  background:#ef4444;color:#fff;border-radius:99px;
  font-size:10px;font-weight:800;min-width:16px;height:16px;
  display:flex;align-items:center;justify-content:center;padding:0 4px;
}

/* ══════════════════════════════════════════════════════════
   ZAL XARITASI
   ══════════════════════════════════════════════════════════ */
#hall-view{flex:1;overflow-y:auto;overscroll-behavior-y:contain;background:var(--bg);display:flex;flex-direction:column}
.hall-toolbar{
  padding:12px 14px 4px;display:flex;align-items:center;gap:8px;flex-shrink:0;
}
.hall-title{font-size:15px;font-weight:800;color:var(--text)}
.hall-refresh{margin-left:auto;font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px}
.hall-refresh .dot{width:7px;height:7px;border-radius:99px;background:var(--success);animation:pulse 1.6s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.25}}
/* Aloqa uzilganda "jonli" yozuvi yolg'on bo'lib qolmasligi uchun */
.hall-refresh.offline{color:var(--danger);font-weight:700}
.hall-refresh .dot.off{background:var(--danger);animation:none}

.quick-row{display:flex;gap:8px;padding:8px 14px;flex-shrink:0}
.quick-card{
  flex:1;display:flex;align-items:center;justify-content:center;gap:7px;
  background:#fff;border:2px solid var(--border);border-radius:12px;
  padding:12px;font-size:13px;font-weight:700;color:var(--text);
  cursor:pointer;transition:.15s;
}
.quick-card:hover{border-color:var(--accent);color:var(--accent-d)}
.quick-card i{font-size:17px}

/* Zona tablari (filtr) */
.zona-tabs{
  display:flex;gap:6px;padding:6px 14px 2px;flex-shrink:0;overflow-x:auto;
  -webkit-overflow-scrolling:touch;scrollbar-width:none;
}
.zona-tabs::-webkit-scrollbar{display:none}
.zona-tab{
  flex-shrink:0;padding:7px 13px;border-radius:99px;font-size:12px;font-weight:700;
  background:#fff;border:2px solid var(--border);color:var(--text);cursor:pointer;
  white-space:nowrap;transition:.15s;display:flex;align-items:center;gap:5px;
}
.zona-tab .cnt{font-size:11px;opacity:.65;font-weight:600}
.zona-tab.active{background:var(--accent);border-color:var(--accent);color:#fff}
.zona-tab.active .cnt{opacity:.9}

.stol-grid{
  display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));
  gap:10px;padding:6px 14px 20px;
}
.stol-card{
  background:#fff;border:2px solid var(--border);border-radius:14px;
  padding:14px 10px;text-align:center;cursor:pointer;transition:.15s;
  display:flex;flex-direction:column;gap:4px;align-items:center;position:relative;min-height:126px;
}
.stol-card:active{transform:scale(.97)}
.stol-ic{font-size:22px}
.stol-nomi{font-size:13px;font-weight:800;color:var(--text)}
.stol-holat{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.stol-vaqt{font-size:10px;color:inherit;opacity:.85}
.stol-summa{font-size:12px;font-weight:800;margin-top:1px}
.stol-ok-tag{
  font-size:9px;font-weight:700;background:#ffedd5;color:#9a3412;
  border-radius:99px;padding:2px 7px;margin-top:2px;
}
.stol-order-summary{display:flex;align-items:center;justify-content:center;gap:5px;flex-wrap:wrap;margin-top:3px}
.stol-order-chip{
  display:inline-flex;align-items:center;gap:4px;padding:3px 7px;border-radius:999px;
  background:rgba(255,255,255,.78);border:1px solid rgba(148,163,184,.32);
  color:var(--text);font-size:9px;font-weight:800;text-transform:none;letter-spacing:0;
}
.stol-order-chip.takeaway{color:#c2410c;border-color:#fed7aa;background:#fff7ed}
.stol-card-open{margin-top:auto;padding-top:5px;color:var(--muted);font-size:9px;font-weight:700}
.stol-card-open i{margin-left:3px}
.stol-picker-list{display:flex;flex-direction:column;gap:9px}
.stol-picker-row{
  width:100%;border:1.5px solid var(--border);border-radius:13px;background:#fff;
  padding:12px 13px;display:flex;align-items:center;gap:11px;text-align:left;cursor:pointer;
  color:var(--text);transition:.15s;font-family:inherit;
}
.stol-picker-row:hover{border-color:var(--accent);transform:translateY(-1px);box-shadow:0 6px 18px rgba(15,23,42,.07)}
.stol-picker-row.takeaway{border-color:#fed7aa;background:#fffaf5}
.stol-picker-icon{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:#f1f5f9;color:#475569;font-size:17px}
.stol-picker-row.takeaway .stol-picker-icon{background:#ffedd5;color:#c2410c}
.stol-picker-copy{min-width:0;flex:1;display:flex;flex-direction:column}
.stol-picker-name{font-size:13px;font-weight:800}
.stol-picker-meta{color:var(--muted);font-size:11px;margin-top:2px}
.stol-picker-total{font-size:13px;font-weight:800;white-space:nowrap}
.stol-picker-actions{margin-top:12px}
.stol-picker-actions .im-btn{width:100%;justify-content:center}

.stol-card.bosh{border-color:#a7f3d0;background:#f0fdf4}
.stol-card.bosh .stol-holat{color:var(--success)}
.stol-card.bosh .stol-ic{color:var(--success)}

.stol-card.band{border-color:#fed7aa;background:#fff7ed}
.stol-card.band .stol-holat,.stol-card.band .stol-summa{color:#c2410c}
.stol-card.band .stol-vaqt{color:#9a3412}
.stol-card.band .stol-ic{color:#c2410c}

.stol-card.kassada{border-color:#bfdbfe;background:#eff6ff;cursor:pointer}
.stol-card.kassada .stol-holat,.stol-card.kassada .stol-summa{color:#1d4ed8}
.stol-card.kassada .stol-ic{color:#1d4ed8}

.hall-empty{
  grid-column:1/-1;text-align:center;padding:50px 20px;color:var(--muted);
}
.hall-empty i{font-size:40px;opacity:.3;display:block;margin-bottom:8px}

/* ══════════════════════════════════════════════════════════
   BUYURTMA EKRANI (mahsulot + savatcha)
   ══════════════════════════════════════════════════════════ */
#order-view{flex:1;display:none;flex-direction:column;overflow:hidden}
#order-view.visible{display:flex}
.order-subheader{
  display:flex;align-items:center;gap:9px;padding:9px 12px;
  background:#fff;border-bottom:1px solid var(--border);flex-shrink:0;
}
.back-hall-btn{
  background:rgba(0,0,0,.06);border:none;border-radius:8px;
  width:32px;height:32px;display:flex;align-items:center;justify-content:center;
  cursor:pointer;color:var(--text);font-size:15px;flex-shrink:0;
}
.back-hall-btn:hover{background:rgba(0,0,0,.1)}
.order-stol-nomi{
  font-size:14px;font-weight:800;color:var(--text);
  min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.order-stol-badge{
  font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;
  background:var(--hold-light);color:var(--hold);
}
.olib-ketish-btn{
  flex-shrink:0;display:flex;align-items:center;gap:5px;
  border:2px solid var(--border);background:#fff;color:var(--muted);
  border-radius:8px;padding:6px 11px;font-size:12px;font-weight:700;
  font-family:inherit;cursor:pointer;transition:.15s;
}
.olib-ketish-btn:hover{border-color:var(--accent)}
.olib-ketish-btn.active{
  background:#fff7ed;border-color:#fb923c;color:#c2410c;
}

/* ── Layout ── */
.layout{display:flex;flex:1;overflow:hidden}

/* ── LEFT: Mahsulotlar ── */
.left-panel{
  width:58%;display:flex;flex-direction:column;background:#fff;
  border-right:1px solid var(--border);flex-shrink:0;
}

/* Qidiruv */
.search-bar{
  padding:10px 12px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:8px;flex-shrink:0;background:#fff;
}
.search-wrap{position:relative;flex:1}
.search-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px}
.search-input{
  width:100%;height:38px;border:2px solid var(--border);border-radius:9px;
  padding:0 10px 0 34px;font-size:14px;font-family:inherit;
  background:var(--bg);outline:none;transition:.2s;
}
.search-input:focus{border-color:var(--accent);background:#fff}

/* Kategoriyalar */
.kat-bar{
  display:flex;gap:6px;padding:8px 12px;overflow-x:auto;flex-shrink:0;
  border-bottom:1px solid var(--border);background:#fafafa;
  scrollbar-width:none;
}
.kat-bar::-webkit-scrollbar{display:none}
.kat-btn{
  flex-shrink:0;padding:5px 12px;border-radius:20px;
  border:2px solid var(--border);background:#fff;
  font-size:12px;font-weight:600;color:var(--text);
  cursor:pointer;transition:.15s;white-space:nowrap;font-family:inherit;
}
.kat-btn:hover{border-color:var(--accent);color:var(--accent-d)}
.kat-btn.active{background:var(--accent);border-color:var(--accent);color:#fff}

/* Mahsulotlar grid */
.prod-grid{
  display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));
  gap:8px;padding:10px;overflow-y:auto;overscroll-behavior-y:contain;flex:1;align-content:start;
}
.prod-card{
  border:2px solid var(--border);border-radius:12px;
  cursor:pointer;transition:.15s;position:relative;background:#fff;
  user-select:none;overflow:hidden;
  display:flex;flex-direction:column;
}
.prod-card:hover{border-color:var(--accent);transform:translateY(-1px);box-shadow:0 4px 12px rgba(226,185,111,.2)}
.prod-card.in-cart{border-color:var(--success);background:#f0fdf4}
.prod-img{
  width:100%;aspect-ratio:1;object-fit:cover;background:#f3f4f6;
  display:flex;align-items:center;justify-content:center;font-size:28px;
}
.prod-img img{width:100%;height:100%;object-fit:cover}
.prod-info{padding:7px 8px}
.prod-name{font-size:12px;font-weight:600;color:var(--text);line-height:1.3;margin-bottom:3px}
.prod-narx{font-size:13px;font-weight:800;color:var(--accent-d)}
.prod-qoldiq{font-size:10px;color:var(--muted);margin-top:2px}
.prod-badge{
  position:absolute;top:6px;right:6px;
  background:var(--success);color:#fff;
  border-radius:50%;width:20px;height:20px;
  font-size:10px;font-weight:700;display:none;
  align-items:center;justify-content:center;
  box-shadow:0 2px 6px rgba(0,0,0,.2);
}
.prod-card.in-cart .prod-badge{display:flex}
.prod-empty{
  grid-column:1/-1;text-align:center;padding:40px 20px;
  color:var(--muted);font-size:14px;display:flex;flex-direction:column;
  align-items:center;gap:8px;
}
.prod-empty i{font-size:40px;opacity:.3}

/* Kartadagi miqdor boshqaruvi — savatni ochmasdan sozlash uchun.
   Mahsulot savatda bo'lsagina ko'rinadi. */
.prod-step{
  display:flex;align-items:center;justify-content:space-between;gap:4px;
  padding:5px 6px;border-top:1px solid #d1fae5;background:#ecfdf5;
}
.ps-btn{
  /* 34px — barmoq bilan bosish uchun qulay eng kichik o'lcham */
  width:34px;height:34px;flex-shrink:0;border-radius:8px;
  border:1px solid var(--success);background:#fff;color:var(--success);
  font-size:17px;font-weight:800;line-height:1;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  -webkit-tap-highlight-color:transparent;
}
.ps-btn:active{background:var(--success);color:#fff;transform:scale(.92)}
.ps-num{font-size:14px;font-weight:800;color:#047857;min-width:24px;text-align:center}

/* 🎁 Setlar */
.kat-btn.kat-btn-set{border-color:#e2b96f;color:#b8860b}
.kat-btn.kat-btn-set.active{background:#b8860b;border-color:#b8860b;color:#fff}
.set-card{overflow:hidden}
.set-card .set-head{
  padding:8px 9px;font-size:12px;font-weight:800;color:#fff;line-height:1.25;
}
.set-card .set-items{font-size:10px;color:var(--muted);line-height:1.6;margin-bottom:5px}
.set-card .set-items b{color:var(--text)}
.set-card.sold-out{opacity:.55;cursor:not-allowed}
.set-card.sold-out:hover{transform:none;box-shadow:none}

/* Mobil: pastdagi doimiy savat paneli. Mahsulot qo'shilganda savat
   OCHILMAYDI — bu yerda jami ko'rinadi, ofitsant terishda davom etadi. */
.mob-bar{
  display:none;position:fixed;left:0;right:0;bottom:0;z-index:150;
  padding:9px calc(12px + env(safe-area-inset-right,0px))
          calc(9px + env(safe-area-inset-bottom,0px))
          calc(12px + env(safe-area-inset-left,0px));
  background:var(--primary);
  align-items:center;gap:10px;
  box-shadow:0 -3px 16px rgba(0,0,0,.25);
}
.mob-bar-info{flex:1;min-width:0;line-height:1.25}
.mob-bar-count{font-size:11px;color:rgba(255,255,255,.7);display:block}
.mob-bar-total{font-size:17px;font-weight:800;color:#fff}
.mob-bar-btn{
  flex-shrink:0;border:none;border-radius:10px;padding:10px 16px;
  background:linear-gradient(135deg,#e2b96f,#c9a058);color:#fff;
  font-size:14px;font-weight:800;font-family:inherit;cursor:pointer;
  display:flex;align-items:center;gap:6px;
}

/* ── RIGHT: Savatcha ── */
.right-panel{
  flex:1;display:flex;flex-direction:column;background:#fff;min-width:0;
}
.cart-head{
  padding:10px 14px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:8px;flex-shrink:0;
}
.cart-title{font-size:14px;font-weight:700;color:var(--text)}
.cart-badge{
  background:var(--accent);color:#fff;border-radius:99px;
  padding:2px 8px;font-size:11px;font-weight:700;min-width:24px;text-align:center;
}
.ms-auto{margin-left:auto}
.clear-btn{
  background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
  color:#ef4444;border-radius:7px;padding:4px 8px;
  font-size:11px;cursor:pointer;transition:.15s;display:flex;align-items:center;gap:4px;
}
.clear-btn:hover{background:var(--danger);color:#fff}

/* Cart body */
.cart-body{flex:1;overflow-y:auto;overscroll-behavior-y:contain;padding:8px}
.cart-empty{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  height:100%;color:var(--muted);gap:6px;
}
.cart-empty i{font-size:40px;opacity:.25}
.cart-empty small{font-size:12px}

/* Cart item */
.cart-item{
  display:flex;align-items:center;gap:8px;
  padding:8px;border:1px solid var(--border);border-radius:10px;
  margin-bottom:7px;background:#fafafa;
}
.ci-name{font-size:12px;font-weight:600;color:var(--text);line-height:1.3}
.ci-narx{font-size:11px;color:var(--muted);margin-top:2px}
.ci-qty{display:flex;align-items:center;gap:4px;flex-shrink:0}
.ci-qty-btn{
  width:24px;height:24px;border-radius:7px;border:1px solid var(--border);
  background:#fff;cursor:pointer;font-size:14px;font-weight:700;
  display:flex;align-items:center;justify-content:center;transition:.15s;color:var(--text);
}
.ci-qty-btn:hover{background:var(--accent);border-color:var(--accent);color:#fff}
.ci-qty-num{
  min-width:28px;text-align:center;font-size:13px;font-weight:700;
  background:#fff;border:1px solid var(--border);border-radius:5px;padding:1px 3px;
}
.ci-del{
  width:24px;height:24px;border-radius:7px;border:none;flex-shrink:0;
  background:rgba(239,68,68,.1);cursor:pointer;color:var(--danger);
  font-size:13px;display:flex;align-items:center;justify-content:center;transition:.15s;
}
.ci-del:hover{background:var(--danger);color:#fff}
/* Eski qator-darajasidagi qadoqlash uslubi uchun stil (tarixiy yozuvlar) */
.ci-ok{
  width:24px;height:24px;border-radius:7px;flex-shrink:0;cursor:pointer;
  border:1px solid var(--border);background:#fff;font-size:12px;line-height:1;
  display:flex;align-items:center;justify-content:center;transition:.15s;
  filter:grayscale(1);opacity:.45;
}
.ci-ok:hover{opacity:.9;filter:grayscale(.3)}
.ci-ok.active{filter:none;opacity:1;background:#ffedd5;border-color:#fdba74}

/* Savat bo'lim sarlavhasi — eski va yangi buyurtmani ajratish uchun.
   Stolga qayta kelinganda ofitsant nima avval yuborilganini va nima
   hozir qo'shilganini bir qarashda ko'rishi kerak. */
.cart-sec{
  display:flex;align-items:center;gap:6px;
  padding:6px 8px;margin:2px 0 6px;border-radius:7px;
  font-size:10px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;
}
.cart-sec .num{margin-left:auto;font-weight:800;opacity:.75}
.cart-sec.eski { background:#f1f5f9;color:#64748b; }
.cart-sec.yangi{ background:#dcfce7;color:#15803d; }
.cart-item.eski{ opacity:.72; background:#fafafa; }
.cart-item.yangi{ border-color:#86efac; background:#f0fdf4; }
.ci-split{
  font-size:10px;color:#15803d;font-weight:700;margin-top:2px;
}
.ci-split b{color:#64748b;font-weight:700}

/* Cart footer */
.cart-foot{border-top:1px solid var(--border);padding:12px 14px;flex-shrink:0}
.total-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.total-label{font-size:12px;color:var(--muted)}
.total-val{font-size:20px;font-weight:800;color:var(--accent-d)}
.total-val span{font-size:11px;font-weight:500;color:var(--muted)}

.btn-row{display:flex;gap:7px}
.btn-print{
  flex-shrink:0;height:46px;border:2px solid #c4b5fd;border-radius:10px;
  background:var(--hold-light);color:var(--hold);font-size:14px;
  cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0 11px;
  transition:.15s;
}
.btn-print:hover{background:#ddd6fe;border-color:var(--hold)}
.btn-print:disabled{opacity:.4;cursor:not-allowed}
.btn-hold{
  flex-shrink:0;height:46px;border:2px solid #c4b5fd;border-radius:10px;
  background:var(--hold-light);color:var(--hold);font-size:13px;font-weight:700;
  font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;
  padding:0 12px;transition:.15s;white-space:nowrap;
}
.btn-hold:hover{background:#ddd6fe;border-color:var(--hold)}
.btn-hold:disabled{opacity:.4;cursor:not-allowed}
.btn-send{
  flex:1;height:46px;border:none;border-radius:10px;
  background:linear-gradient(135deg,#e2b96f,#c9a058);
  color:#fff;font-size:14px;font-weight:800;font-family:inherit;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;
  box-shadow:0 4px 14px rgba(226,185,111,.35);transition:.15s;
}
.btn-send:hover{filter:brightness(1.06);transform:translateY(-1px)}
.btn-send:active{transform:translateY(0)}
.btn-send:disabled{opacity:.5;cursor:not-allowed;transform:none}

/* ── Yuborishdan oldingi TASDIQLASH oynasi ──────────────────
   Savatga qo'shish qaytariladigan amal (savatning o'zi ko'rib
   chiqish bosqichi), shuning uchun har bir mahsulotda so'ralmaydi.
   Tasdiqlash FAQAT shu yerda — "Yuborish" qaytarib bo'lmaydigan
   chegara: buyurtma oshpaz/kassaga ketadi va sotuvchi uni boshqa
   tahrirlay olmaydi (config.php: im_hall_stollar → 'kassada'). */
.cnf-overlay{
  display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
  z-index:9998;align-items:center;justify-content:center;padding:16px;
}
.cnf-overlay.show{display:flex}
.cnf-modal{
  background:#fff;border-radius:16px;width:420px;max-width:100%;
  max-height:88vh;display:flex;flex-direction:column;overflow:hidden;
  box-shadow:0 20px 60px rgba(0,0,0,.25);animation:popIn .22s ease;
}
.cnf-head{
  padding:14px 16px;border-bottom:1px solid var(--border);flex-shrink:0;
}
.cnf-head-top{display:flex;align-items:center;gap:8px}
.cnf-head-top i{font-size:18px;color:var(--accent-d)}
.cnf-title{font-size:15px;font-weight:800;color:var(--text)}
.cnf-stol{
  margin-top:6px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;
  font-size:12px;color:var(--muted);
}
.cnf-stol strong{color:var(--text);font-size:13px}
.cnf-tag{
  background:rgba(226,185,111,.18);color:#8a6d2f;border-radius:5px;
  padding:2px 7px;font-size:10px;font-weight:700;
}
.cnf-body{flex:1;overflow-y:auto;padding:10px 16px}
.cnf-row{
  display:flex;align-items:flex-start;gap:10px;
  padding:8px 0;border-bottom:1px dashed var(--border);
}
.cnf-row:last-child{border-bottom:none}
.cnf-nomi{flex:1;min-width:0;font-size:13px;font-weight:600;color:var(--text);line-height:1.35}
.cnf-hisob{font-size:11px;color:var(--muted);margin-top:2px}
.cnf-summa{font-size:13px;font-weight:700;color:var(--text);white-space:nowrap}
.cnf-lock{
  font-size:9px;background:#fef3c7;color:#92400e;border-radius:4px;
  padding:1px 4px;margin-left:3px;font-weight:700;
}
.cnf-foot{
  border-top:1px solid var(--border);padding:12px 16px;flex-shrink:0;background:#fafafa;
}
.cnf-total{
  display:flex;justify-content:space-between;align-items:center;margin-bottom:11px;
}
.cnf-total-label{font-size:12px;color:var(--muted)}
.cnf-total-val{font-size:21px;font-weight:800;color:var(--accent-d)}
.cnf-total-val span{font-size:11px;font-weight:500;color:var(--muted)}
.cnf-btns{display:flex;gap:8px}
.cnf-back{
  flex-shrink:0;height:46px;padding:0 18px;border:1px solid var(--border);
  border-radius:10px;background:#fff;color:var(--muted);
  font-size:13px;font-weight:700;font-family:inherit;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:6px;transition:.15s;
}
.cnf-back:hover{background:#f1f5f9;color:var(--text)}
.cnf-ok{
  flex:1;height:46px;border:none;border-radius:10px;
  background:linear-gradient(135deg,#e2b96f,#c9a058);
  color:#fff;font-size:14px;font-weight:800;font-family:inherit;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;
  box-shadow:0 4px 14px rgba(226,185,111,.35);transition:.15s;
}
.cnf-ok:hover{filter:brightness(1.06)}
.cnf-ok:disabled{opacity:.5;cursor:not-allowed}

/* Muvaffaqiyat overlay */
.sent-overlay{
  display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
  z-index:9999;align-items:center;justify-content:center;
}
.sent-overlay.show{display:flex}
.sent-modal{
  background:#fff;border-radius:18px;padding:36px 44px;text-align:center;
  box-shadow:0 20px 60px rgba(0,0,0,.2);animation:popIn .3s ease;
  max-width:90vw;
}
@keyframes popIn{from{opacity:0;transform:scale(.8)}to{opacity:1;transform:scale(1)}}
.sent-icon{font-size:56px;display:block;margin-bottom:12px}
.sent-title{font-size:22px;font-weight:800;color:var(--success);margin-bottom:6px}
.sent-sub{font-size:13px;color:var(--muted);margin-bottom:20px}
.sent-close{
  background:var(--success);color:#fff;border:none;border-radius:10px;
  padding:11px 28px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;
}

/* Scrollbar */
::-webkit-scrollbar{width:3px}::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:rgba(0,0,0,.12);border-radius:4px}

/* ══════════════════════════════════════════════════════════
   MOBILE RESPONSIVE
   ══════════════════════════════════════════════════════════ */
@media (max-width: 768px) {
  html,body{overflow:hidden}

  .topbar-name{display:none}
  .cart-toggle-btn{display:flex}

  .layout{position:relative}

  .left-panel{
    width:100%;border-right:none;
  }

  .right-panel{
    position:fixed;bottom:0;left:0;right:0;top:0;
    z-index:200;
    transform:translateX(100%);
    transition:transform .25s ease;
    box-shadow:-4px 0 20px rgba(0,0,0,.15);
  }
  .right-panel.cart-open{
    transform:translateX(0);
  }

  .prod-grid{
    grid-template-columns:repeat(auto-fill,minmax(110px,1fr));
    gap:6px;padding:8px;
    /* pastdagi savat paneli ustini yopmasin (notchli telefonlarda ham) */
    padding-bottom:calc(76px + env(safe-area-inset-bottom,0px));
  }
  /* Pastki panel faqat savatda mahsulot bo'lsa ko'rinadi (JS boshqaradi) */
  .mob-bar.show{display:flex}

  .kat-bar{padding:7px 8px;gap:5px}
  .kat-btn{padding:5px 10px;font-size:11px}

  /* To'liq ekranli savat paneli — tepasi notch, tagi home-indicator ostida qolmasin */
  .cart-head{padding-top:calc(10px + env(safe-area-inset-top,0px))}
  .cart-foot{padding:10px 12px calc(10px + env(safe-area-inset-bottom,0px))}
  .total-val{font-size:18px}

  /* Barmoq bilan bosish uchun qulay o'lcham (Apple HIG/Material tavsiyasi ~40-44px) */
  .back-hall-btn{width:40px;height:40px;font-size:16px}
  .cart-close-btn{
    display:flex;
    width:40px;height:40px;border-radius:50%;background:rgba(0,0,0,.08);
    border:none;cursor:pointer;align-items:center;justify-content:center;
    font-size:16px;color:var(--text);margin-right:4px;flex-shrink:0;
  }

  .btn-hold span{display:none}

  /* iOS Safari qidiruv maydoniga bosganda avtomatik zoom qilmasligi uchun */
  .search-input{font-size:16px}

  .stol-grid{grid-template-columns:repeat(auto-fill,minmax(100px,1fr))}
}

@media (min-width: 769px) {
  .cart-close-btn{display:none}
}

/* ── Juda tor ekranlar (kichik Android telefonlar, iPhone SE va h.k.) ── */
@media (max-width: 380px) {
  .topbar{gap:6px}
  .topbar-logo{font-size:14px}
  .topbar-filial{max-width:88px;padding:3px 6px;font-size:10px}
  .olib-ketish-btn span{display:none}
  .olib-ketish-btn{padding:6px 9px}

  .quick-row{padding:7px 10px}
  .hall-toolbar{padding:10px 10px 4px}
  .stol-grid{grid-template-columns:repeat(auto-fill,minmax(86px,1fr));gap:7px;padding:6px 10px 18px}
  .stol-card{padding:10px 6px;min-height:110px}

  .prod-grid{grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:5px;padding:6px;padding-bottom:calc(72px + env(safe-area-inset-bottom,0px))}
  .prod-info{padding:6px 6px}

  #stol-order-picker-modal .im-modal-body{padding:12px}
}

/* ── Kesmali (notch) ekranlar, landshaft rejimi ──────────────
   .topbar va .mob-bar o'z insetini allaqachon hisobga oladi. Qolganlari
   uchun insetni ILDIZ konteynerlarga beramiz — ichkaridagi paddinglar
   tegilmaydi, shuning uchun tor ekran sozlamalari buzilmaydi. Aks holda
   iPhone landshaftda birinchi ustundagi stollar va savat tugmalari
   kesma ostida qolib, bosib bo'lmasdi. */
@media (orientation: landscape) {
  #hall-view,#order-view{
    padding-left:env(safe-area-inset-left,0px);
    padding-right:env(safe-area-inset-right,0px);
  }
}
@media (orientation: landscape) and (max-width: 768px) {
  /* Mobil savat paneli position:fixed — ota konteyner insetini olmaydi */
  .right-panel{
    padding-left:env(safe-area-inset-left,0px);
    padding-right:env(safe-area-inset-right,0px);
  }
}

/* ── Past bo'yli ekranlar (telefon landshaft rejimi) — vertikal joy tejash ── */
@media (max-height: 430px) {
  .topbar{min-height:40px}
  .hall-toolbar{padding:6px 14px 2px}
  .quick-row{padding:5px 14px}
  .zona-tabs{padding:4px 14px 2px}
  .order-subheader{padding:6px 12px}
  .cart-foot{padding:6px 12px calc(6px + env(safe-area-inset-bottom,0px))}
  .cnf-modal{max-height:94vh}
  .cnf-body{padding:6px 16px}
  .sent-modal{padding:20px 30px}
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="topbar-logo">iMEZON<span>POS</span></div>
  <div class="topbar-filial"><i class="bi bi-building"></i> <?= im_f($filial_nomi) ?></div>

  <!-- Mobile: savat ochish tugmasi (faqat buyurtma ekranida foydali) -->
  <button class="cart-toggle-btn" id="cart-toggle-btn" onclick="openCartPanel()" style="display:none">
    <i class="bi bi-cart3"></i>
    <span class="badge" id="cart-toggle-badge" style="display:none">0</span>
  </button>

  <div class="topbar-right">
    <span class="topbar-name"><i class="bi bi-person-circle"></i> <?= im_f($sotuvchi) ?></span>
    <a href="<?= im_BASE ?>logout.php" class="topbar-logout"><i class="bi bi-box-arrow-right"></i> Chiqish</a>
  </div>
</div>

<!-- ═══ ZAL XARITASI (kirish ekrani) ═══ -->
<div id="hall-view">
  <div class="hall-toolbar">
    <div class="hall-title"><i class="bi bi-grid-3x3-gap-fill"></i> Zal xaritasi</div>
    <div class="hall-refresh"><span class="dot"></span> jonli</div>
  </div>
  <div class="quick-row">
    <div class="quick-card" onclick="openQuick('Dastavka')"><i class="bi bi-scooter"></i> Dastavka</div>
  </div>

  <!-- Stolga bog'lanmaydigan faol buyurtmalar — faqat Dastavka -->
  <div id="stolsiz-wrap" style="display:none">
    <div class="hall-toolbar" style="border-top:1px solid var(--border)">
      <div class="hall-title"><i class="bi bi-scooter"></i> Dastavka buyurtmalari</div>
    </div>
    <div class="stol-grid" id="stolsiz-grid"></div>
  </div>

  <!-- Zona tablari (Teraska / Podval / Zal ...) — 1 tadan ko'p zona bo'lsa ko'rinadi -->
  <div class="zona-tabs" id="zona-tabs" style="display:none"></div>

  <div class="stol-grid" id="stol-grid">
    <div class="hall-empty"><i class="bi bi-hourglass-split"></i><br>Yuklanmoqda...</div>
  </div>
</div>

<!-- ═══ BUYURTMA EKRANI (mahsulot + savatcha) ═══ -->
<div id="order-view">

  <div class="order-subheader">
    <button class="back-hall-btn" onclick="backToHall()" title="Zal xaritasiga qaytish">
      <i class="bi bi-arrow-left"></i>
    </button>
    <span class="order-stol-nomi" id="order-stol-nomi">—</span>
    <span class="order-stol-badge" id="order-stol-badge" style="display:none">tahrirlanmoqda</span>
    <button class="olib-ketish-btn" id="olib-ketish-btn" onclick="startTakeawayFromCurrent()"
            style="margin-left:auto;flex-shrink:0" title="Shu stolga alohida olib ketish buyurtmasi ochish">
      <i class="bi bi-bag-plus-fill"></i> <span>Yangi olib ketish</span>
    </button>
  </div>

  <div class="layout">

    <!-- CHAP: Mahsulotlar -->
    <div class="left-panel">

      <div class="search-bar">
        <div class="search-wrap">
          <i class="bi bi-search"></i>
          <input type="text" class="search-input" id="prod-search"
                 placeholder="Mahsulot qidirish..." autocomplete="off">
        </div>
      </div>

      <div class="kat-bar" id="kat-bar">
        <button class="kat-btn active" data-kat="0" onclick="filterKat(0,this)">
          <i class="bi bi-grid-3x3-gap-fill"></i> Barchasi
        </button>
        <button class="kat-btn kat-btn-set" data-kat="__sets__" onclick="filterKat('__sets__',this)">
          🎁 Setlar <span id="set-count"></span>
        </button>
        <?php foreach ($kategoriyalar as $k): ?>
        <button class="kat-btn" data-kat="<?= (int)$k['id'] ?>" onclick="filterKat(<?= (int)$k['id'] ?>,this)">
          <?= im_f($k['nomi']) ?>
        </button>
        <?php endforeach; ?>
      </div>

      <div class="prod-grid" id="prod-grid">
        <div class="prod-empty"><i class="bi bi-hourglass-split"></i> Yuklanmoqda...</div>
      </div>
    </div>

    <!-- O'NG: Savatcha -->
    <div class="right-panel" id="right-panel">
      <div class="cart-head">
        <button class="cart-close-btn" onclick="closeCartPanel()" title="Yopish">
          <i class="bi bi-arrow-left"></i>
        </button>
        <i class="bi bi-cart3" style="font-size:18px;color:var(--accent-d)"></i>
        <span class="cart-title">Savatcha</span>
        <span class="cart-badge" id="cart-count">0</span>
        <button class="clear-btn ms-auto" id="clear-btn" onclick="clearCart()" style="display:none">
          <i class="bi bi-trash3"></i> Tozalash
        </button>
      </div>

      <div class="cart-body" id="cart-body">
        <div class="cart-empty">
          <i class="bi bi-cart-x"></i>
          <div>Savatcha bo'sh</div>
          <small>Chapdan mahsulot tanlang</small>
        </div>
      </div>

      <div class="cart-foot">
        <div class="total-row">
          <span class="total-label">Jami summa:</span>
          <span class="total-val" id="total-val">0 <span>so'm</span></span>
        </div>
        <div class="btn-row">
          <button class="btn-print" id="print-pre-btn" onclick="printPreReceipt()" disabled title="Dastlabki chek">
            <i class="bi bi-printer-fill"></i>
          </button>
          <button class="btn-hold" id="hold-btn" onclick="openConfirm('hold')" disabled>
            <i class="bi bi-pause-fill"></i> <span>Pauza</span>
          </button>
          <button class="btn-send" id="send-btn" onclick="openConfirm('send')" disabled>
            <i class="bi bi-send-fill"></i> Yuborish
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Mobil: pastdagi doimiy savat paneli -->
<div class="mob-bar" id="mob-bar">
  <div class="mob-bar-info">
    <span class="mob-bar-count" id="mob-bar-count">0 ta mahsulot</span>
    <span class="mob-bar-total" id="mob-bar-total">0 so'm</span>
  </div>
  <button class="mob-bar-btn" onclick="openCartPanel()">
    <i class="bi bi-cart3"></i> Savatcha
  </button>
</div>

<!-- Yuborishdan oldingi tasdiqlash oynasi -->
<div class="cnf-overlay" id="cnf-overlay" onclick="if(event.target===this) closeConfirm()">
  <div class="cnf-modal">
    <div class="cnf-head">
      <div class="cnf-head-top">
        <i class="bi bi-clipboard-check"></i>
        <span class="cnf-title" id="cnf-title-text">Buyurtmani tasdiqlang</span>
      </div>
      <div class="cnf-stol">
        <i class="bi bi-geo-alt-fill"></i>
        <strong id="cnf-stol-nomi">—</strong>
        <span id="cnf-olib-ketish" class="cnf-tag" style="display:none">🛍️ Olib ketadi</span>
        <span id="cnf-order-id" class="cnf-tag" style="display:none"></span>
      </div>
    </div>

    <div class="cnf-body" id="cnf-body"></div>

    <div class="cnf-foot">
      <div class="cnf-total">
        <span class="cnf-total-label">Jami summa:</span>
        <span class="cnf-total-val" id="cnf-total">0 <span>so'm</span></span>
      </div>
      <div class="cnf-btns">
        <button class="cnf-back" onclick="closeConfirm()">
          <i class="bi bi-arrow-left"></i> Ortga
        </button>
        <button class="cnf-ok" id="cnf-ok-btn" onclick="confirmOk()">
          <i class="bi bi-send-fill"></i> Tasdiqlash va yuborish
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Muvaffaqiyat overlay -->
<div class="sent-overlay" id="sent-overlay">
  <div class="sent-modal">
    <span class="sent-icon">🎉</span>
    <div class="sent-title">Jo'natildi!</div>
    <div class="sent-sub" id="sent-msg">Muvaffaqiyatli yuborildi</div>
    <button class="sent-close" onclick="afterSend()">OK</button>
  </div>
</div>

<!-- Stol ichidagi buyurtmalar — zal kartasidan alohida, tartibli tanlov -->
<div class="im-overlay" id="stol-order-picker-modal">
  <div class="im-modal" style="max-width:470px;width:96%">
    <div class="im-modal-header">
      <div class="stol-picker-icon" style="width:34px;height:34px"><i class="bi bi-grid-1x2-fill"></i></div>
      <div>
        <div class="im-modal-title" id="stol-picker-title">Stol buyurtmalari</div>
        <div class="text-muted" id="stol-picker-subtitle" style="font-size:11px"></div>
      </div>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body" style="background:var(--bg)">
      <div class="stol-picker-list" id="stol-picker-list"></div>
      <div class="stol-picker-actions" id="stol-picker-actions"></div>
    </div>
  </div>
</div>

<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js?v=<?= rawurlencode(im_VERSION) ?>"></script>
<script>
'use strict';
const im_BASE = '<?= im_BASE ?>';
// im_mahsulotlar.rasm ustuni "uploads/products/xxx.jpg" ko'rinishida
// SAQLANADI (sklad/ajax/mah-save.php), shuning uchun bu yerda faqat sayt
// ildizi qo'shiladi. Ilgari yo'l ikki marta qo'shilib ketib, ofitsant
// panelida BIRORTA mahsulot rasmi ochilmasdi.
const RASM_BASE = '<?= im_BASE ?>';

// ══════════════════════════════════════════════════════════
//  STATE
// ══════════════════════════════════════════════════════════
// Bir vaqtning o'zida faqat BITTA buyurtma tahrirlanadi — u ham
// zal xaritasidan tanlangan stolga bog'langan. Boshqa stolga
// o'tish uchun avval zalga qaytiladi (Poster/R-Keeper naqshi).
let currentOrder = null;   // { order_id, stol_id, mijoz_ism, cart:{} } yoki null (zal ekranida)
let orderDirty   = false;  // Oxirgi saqlashdan beri o'zgarish bo'ldimi
let allProducts = [], activeKat = 0;

// Zona filtri — oxirgi yuklangan stollar/zonalar + tanlangan tab
let hallStollar = [], hallZonalar = [];
let hallActiveZona = 'all';
try { hallActiveZona = localStorage.getItem('im_sotuvchi_hall_zona') || 'all'; } catch {}

function fmt(n)   { return Math.round(n).toLocaleString('uz-UZ'); }

// Savat qatori uchun ruxsat etilgan ENG KATTA miqdor.
// im_filial_qoldiq.soni = MAVJUD qoldiq (jismoniy − band qilingan), ya'ni
// shu buyurtmaning O'Z rezervi undan allaqachon ayrilgan. Shuning uchun
// chegara = mavjud + shu qator band qilib turgan miqdor. Aks holda 7 ta
// saqlangan qatorda "+" bosilsa miqdor 3 taga tushib ketardi.
function qtyCeil(it, qoldiq) {
  const q = parseFloat(qoldiq !== undefined ? qoldiq : (it && it.qoldiq)) || 0;
  const r = parseFloat((it && it.rezerv_soni) || 0) || 0;
  return q + r;
}

// ── Tarmoq / sessiya holati ───────────────────────────────
let netFail = 0, sessionDead = false;
let liveHolat = null;
function setLive(ok, matn) {
  const el = document.querySelector('.hall-refresh');
  if (!el) return;
  const kalit = ok ? 'ok' : ('x' + (matn || ''));
  if (liveHolat === kalit) return;   // har pollingda qayta chizilsa dot animatsiyasi uzilardi
  liveHolat = kalit;
  el.classList.toggle('offline', !ok);
  el.innerHTML = ok ? '<span class="dot"></span> jonli'
                    : '<span class="dot off"></span> ' + (matn || "aloqa yo'q");
}
function netUp()   { netFail = 0; if (!sessionDead) setLive(true); }
function netDown() { if (++netFail >= 2) setLive(false); }
// Sessiya tugaganda so'rov login.php ga yo'naltiriladi va JSON o'rniga HTML
// qaytadi. Ilgari bu catch{} ichida yutilar, panel esa eski rasmni ko'rsatib
// turaverardi — "Taom tayyor" qo'ng'irog'i ham butunlay jim bo'lib qolardi.
function sessionLost() {
  if (sessionDead) return;
  sessionDead = true;
  setLive(false, 'sessiya tugadi');
  showToast('Sessiya tugadi — qaytadan kiring', 'error');
  setTimeout(() => { location.href = im_BASE + 'login.php'; }, 2500);
}
// Sessiya tugagani FAQAT login.php ga yo'naltirish (yoki 401/403) bilan
// bilinadi. 502/504 kabi vaqtinchalik HTML xatolar sessiya tugadi DEGANI EMAS:
// ularda ham chiqarib yuborilsa, ofitsantning bir necha daqiqada terilgan
// savati yo'qolardi. Shuning uchun ikkita alohida tekshiruv.
function sessiyaTugadi(res) {
  return res.redirected || res.status === 401 || res.status === 403
      || /login\.php/i.test(res.url || '');
}
function jsonEmas(res) {
  return !String(res.headers.get('content-type') || '').includes('json');
}
// Har bir YANGI (hali saqlanmagan) buyurtma uchun bir martalik kalit: tarmoq
// uzilib qayta yuborilsa server ikkinchi order ochmaydi, borini tahrirlaydi.
function newToken() {
  return 'w' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
}

// Stol ochilgan paytdagi qatorlar "barmoq izi". Saqlashda serverga
// yuboriladi: agar shu orasda BOSHQA qurilma (kassa POS yoki ikkinchi
// ofitsant) qatorlarni o'zgartirgan bo'lsa, server yo'qotish bo'ladigan
// yozuvni rad etadi. Buyurtma holati (oshpaz qabuli) o'zgarishi bu izga
// ta'sir qilmaydi — ofitsant bekorga to'xtatilmaydi.
function cartSig(cart) {
  return Object.values(cart || {})
    .map(i => (i.mahsulot_id|0) + ':' + (i.set_id|0) + ':' + (parseFloat(i.soni)||0).toFixed(3))
    .sort().join('|');
}
function effN(it) {
  return (it.ulg_min > 0 && it.soni >= it.ulg_min && it.ulg_narx > 0) ? it.ulg_narx : it.narx;
}

function showToast(msg, type = 'info') {
  const C = { info:'#0d6efd', error:'#ef4444', warn:'#f59e0b', success:'#10b981' };
  const el = document.createElement('div');
  el.style.cssText = `position:fixed;bottom:20px;right:20px;z-index:99999;
    background:${C[type]||C.info};color:#fff;padding:10px 18px;border-radius:10px;
    font-size:13px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.2);
    animation:popIn .2s ease;max-width:280px;`;
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity='0'; el.style.transition='.3s'; setTimeout(() => el.remove(),300); }, 3000);
}

// ══════════════════════════════════════════════════════════
//  MOBILE: Cart panel ochish/yopish
// ══════════════════════════════════════════════════════════
function openCartPanel()  { document.getElementById('right-panel').classList.add('cart-open'); }
function closeCartPanel() { document.getElementById('right-panel').classList.remove('cart-open'); }

// ══════════════════════════════════════════════════════════
//  ZAL XARITASI
// ══════════════════════════════════════════════════════════
function elapsed(mins) {
  // Daqiqa soni SERVERDA hisoblangan (im_hall_stollar) — brauzer soat
  // mintaqasiga bog'liq emas, shuning uchun bu yerda faqat formatlaymiz.
  if (mins === null || mins === undefined) return '';
  mins = parseInt(mins) || 0;
  if (mins < 1) return 'hozirgina';
  if (mins < 60) return mins + ' daq oldin';
  return Math.floor(mins/60) + ' soat ' + (mins%60) + ' daq oldin';
}

async function loadHall() {
  if (sessionDead) return;
  try {
    const res = await fetch(im_BASE + 'sotuvchi/ajax/get-hall.php');
    if (sessiyaTugadi(res)) { sessionLost(); return; }
    if (jsonEmas(res))      { netDown();    return; }
    const d   = await res.json();
    if (d.status !== 'ok') { netDown(); return; }
    hallStollar = d.data.stollar || [];
    hallZonalar = d.data.zonalar || [];
    renderZonaTabs();
    renderHall(hallStollar);
    renderStolsiz(d.data.stolsiz || []);
    netUp();
  } catch { netDown(); }
}

// Zona tablari: "Hammasi" + stol bor zonalar (+ "Boshqa"). 1 tadan kam guruh — yashiriladi.
function renderZonaTabs() {
  const wrap = document.getElementById('zona-tabs');
  if (!wrap) return;
  const sanoq = {};
  let boshqa = 0;
  hallStollar.forEach(s => { s.zona_id ? (sanoq[s.zona_id] = (sanoq[s.zona_id]||0)+1) : boshqa++; });

  const tabs = [{ key:'all', nomi:'Hammasi', rang:'', cnt:hallStollar.length }];
  hallZonalar.forEach(z => { if (sanoq[z.id]) tabs.push({ key:String(z.id), nomi:z.nomi, rang:z.rang, cnt:sanoq[z.id] }); });
  if (boshqa) tabs.push({ key:'none', nomi:'Boshqa', rang:'#94a3b8', cnt:boshqa });

  if (tabs.length <= 2) { wrap.style.display = 'none'; wrap.innerHTML = ''; return; }
  if (hallActiveZona !== 'all' && !tabs.some(t => t.key === hallActiveZona)) hallActiveZona = 'all';
  wrap.style.display = 'flex';
  wrap.innerHTML = tabs.map(t => {
    const on = t.key === hallActiveZona;
    const st = (on && t.rang) ? ` style="background:${im_esc(t.rang)};border-color:${im_esc(t.rang)}"` : '';
    return `<button class="zona-tab${on ? ' active' : ''}"${st} onclick="setHallZona('${im_esc_attr_js(t.key)}')">${im_esc(t.nomi)} <span class="cnt">${t.cnt}</span></button>`;
  }).join('');
}

function setHallZona(key) {
  hallActiveZona = key;
  try { localStorage.setItem('im_sotuvchi_hall_zona', key); } catch {}
  renderZonaTabs();
  renderHall(hallStollar);
}

// Stolga bog'lanmaydigan faol buyurtmalar — faqat Dastavka.
function renderStolsiz(list) {
  const wrap = document.getElementById('stolsiz-wrap');
  const grid = document.getElementById('stolsiz-grid');
  list = (list || []).filter(o => !o.olib_ketish);
  if (!list.length) { wrap.style.display = 'none'; grid.innerHTML = ''; return; }
  wrap.style.display = '';
  grid.innerHTML = list.map(o => {
    const nomi = im_esc_attr_js(o.mijoz_ism || '');
    const ic   = o.olib_ketish ? 'bi-bag-check-fill' : 'bi-scooter';
    if (o.holat === 'kassada') {
      return `<div class="stol-card kassada" onclick="showToast('Bu buyurtma kassaga yuborilgan — tahrirlash mumkin emas','warn')">
        <div class="stol-ic"><i class="bi bi-receipt"></i></div>
        <div class="stol-nomi">${im_esc(o.mijoz_ism)}</div>
        <div class="stol-holat">Kassada</div>
        <div class="stol-summa">${fmt(o.summa)} so'm</div>
      </div>`;
    }
    return `<div class="stol-card band" onclick="openStolsiz(${o.order_id},'${nomi}')">
      <div class="stol-ic"><i class="bi ${ic}"></i></div>
      <div class="stol-nomi">${im_esc(o.mijoz_ism)}</div>
      <div class="stol-holat">${o.order_status === 'pishirilmoqda' ? 'Pishirilmoqda' : 'Ochiq'}</div>
      <div class="stol-vaqt">${elapsed(o.ochilgan_daqiqa)}</div>
      <div class="stol-summa">${fmt(o.summa)} so'm</div>
    </div>`;
  }).join('');
}

async function openStolsiz(orderId, nomi) {
  try {
    const res = await fetch(im_BASE + 'sotuvchi/ajax/get-active-orders.php');
    const d   = await res.json();
    const found = (d.orders || []).find(o => o.order_id === orderId);
    if (!found) { showToast('Buyurtma topilmadi, yangilanmoqda...', 'warn'); loadHall(); return; }
    // stol_id bo'sh — bu Dastavka (yoki eski stolsiz Olib ketish yozuvi)
    currentOrder = { order_id: found.order_id, stol_id: '', mijoz_ism: found.mijoz_ism,
                     olib_ketish: !!found.olib_ketish, cart: found.cart || {},
                     base_sig: cartSig(found.cart || {}), client_token: newToken() };
  } catch { showToast("Yuklab bo'lmadi", 'error'); return; }
  orderDirty = false;
  enterOrderView(false);
}

function renderHall(stollar) {
  const grid = document.getElementById('stol-grid');
  if (!stollar.length) {
    grid.innerHTML = '<div class="hall-empty"><i class="bi bi-grid-3x3-gap"></i><br>Stol yo\'q — admin/dukon panelidan qo\'shing</div>';
    return;
  }
  // Zona filtri (tanlangan tab)
  let list = stollar;
  if (hallActiveZona === 'none')     list = stollar.filter(s => !s.zona_id);
  else if (hallActiveZona !== 'all') list = stollar.filter(s => String(s.zona_id) === hallActiveZona);
  if (!list.length) {
    grid.innerHTML = '<div class="hall-empty"><i class="bi bi-funnel"></i><br>Bu zonada stol yo\'q</div>';
    return;
  }
  grid.innerHTML = list.map(s => {
    const nomi = im_esc_attr_js(s.nomi || '');
    if (s.holat === 'bosh') {
      return `<div class="stol-card bosh" onclick="openTable(${s.id},'${nomi}',false,null)">
        <div class="stol-ic"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stol-nomi">${im_esc(s.nomi)}</div>
        <div class="stol-holat">Bo'sh</div>
      </div>`;
    }
    const orders = Array.isArray(s.orders) && s.orders.length
      ? s.orders
      : [{id:s.order_id,status:s.order_status,olib_ketish:!!s.olib_ketish,holat:s.holat,summa:s.summa,ochilgan_daqiqa:s.ochilgan_daqiqa}];
    const mainCount = orders.filter(o => !o.olib_ketish).length;
    const takeCount = orders.filter(o => !!o.olib_ketish).length;
    const kassadaCount = orders.filter(o => o.holat === 'kassada').length;
    const chips = `${mainCount ? `<span class="stol-order-chip"><i class="bi bi-cup-hot-fill"></i> Stol</span>` : ''}
      ${takeCount ? `<span class="stol-order-chip takeaway"><i class="bi bi-bag-check-fill"></i> ${takeCount} olib ketish</span>` : ''}`;
    const cardClass = s.holat === 'ochiq' ? 'band' : 'kassada';
    const holat = kassadaCount === orders.length ? 'Kassada' : (kassadaCount ? `${kassadaCount} ta kassada` : 'Ochiq');
    return `<div class="stol-card ${cardClass}" onclick="openStolOrderPicker(${s.id})">
      <div class="stol-ic"><i class="bi bi-receipt"></i></div>
      <div class="stol-nomi">${im_esc(s.nomi)}</div>
      <div class="stol-order-summary">${chips}</div>
      <div class="stol-holat">${holat}</div>
      <div class="stol-summa">${fmt(s.summa)} so'm</div>
      <div class="stol-card-open">Buyurtmalar <i class="bi bi-chevron-right"></i></div>
    </div>`;
  }).join('');
}

function openStolOrderPicker(stolId) {
  const stol = hallStollar.find(s => Number(s.id) === Number(stolId));
  if (!stol) return;
  const orders = Array.isArray(stol.orders) && stol.orders.length
    ? stol.orders
    : [{id:stol.order_id,status:stol.order_status,olib_ketish:!!stol.olib_ketish,holat:stol.holat,summa:stol.summa}];
  const nomiEsc = im_esc_attr_js(stol.nomi || '');
  // Yagona order ham "kassada" bo'lsa, to'g'ridan-to'g'ri o'tib ketmaymiz —
  // aks holda faqat "tahrirlash mumkin emas" toast'i chiqadi va mijoz
  // hisobni to'lagandan keyin ustiga "Olib ketish" qo'shishning iloji
  // qolmaydi. Bunday holatda ham tanlash oynasi ochiladi, u yerdagi
  // "Yangi olib ketish" tugmasi orqali qo'shish mumkin bo'lib qoladi.
  if (orders.length === 1 && orders[0].holat !== 'kassada') {
    const only = orders[0];
    openPickedSellerOrder(stol.id, stol.nomi || '', Number(only.id), false);
    return;
  }
  document.getElementById('stol-picker-title').textContent = stol.nomi || 'Stol';
  document.getElementById('stol-picker-subtitle').textContent = `${orders.length} ta faol buyurtma · ${fmt(stol.summa)} so'm`;
  document.getElementById('stol-picker-list').innerHTML = orders.map(o => {
    const take = !!o.olib_ketish;
    const kassada = o.holat === 'kassada';
    const state = kassada ? 'Kassaga yuborilgan' : (o.status === 'oshpazda' || o.status === 'pishirilmoqda' ? 'Oshxonada' : 'Ochiq');
    return `<button class="stol-picker-row${take ? ' takeaway' : ''}" onclick="openPickedSellerOrder(${stol.id},'${nomiEsc}',${Number(o.id)},${kassada ? 'true' : 'false'})">
      <span class="stol-picker-icon"><i class="bi ${take ? 'bi-bag-check-fill' : 'bi-cup-hot-fill'}"></i></span>
      <span class="stol-picker-copy">
        <span class="stol-picker-name">${take ? 'Olib ketish' : 'Stol buyurtmasi'} #${Number(o.id)}</span>
        <span class="stol-picker-meta">${state}</span>
      </span>
      <span class="stol-picker-total">${fmt(o.summa)} so'm</span>
      <i class="bi bi-chevron-right" style="color:var(--muted)"></i>
    </button>`;
  }).join('');
  const hasMainOrder = orders.some(o => !o.olib_ketish);
  document.getElementById('stol-picker-actions').innerHTML = hasMainOrder
    ? `<button class="im-btn im-btn-outline" style="color:#c2410c;border-color:#fdba74" onclick="NHModal.close('stol-order-picker-modal');startTakeawayOrder(${stol.id},'${nomiEsc}')"><i class="bi bi-bag-plus-fill"></i> Yangi olib ketish</button>`
    : '';
  NHModal.open('stol-order-picker-modal');
}

function openPickedSellerOrder(stolId, nomi, orderId, kassada) {
  NHModal.close('stol-order-picker-modal');
  if (kassada) {
    showToast('Bu buyurtma kassaga yuborilgan — tahrirlash mumkin emas', 'warn');
    return;
  }
  openTable(stolId, nomi, true, orderId);
}

function showHall() {
  currentOrder = null; orderDirty = false;
  // Zal ekranida pastki savat paneli turmasin
  document.getElementById('mob-bar')?.classList.remove('show');
  document.getElementById('order-view').classList.remove('visible');
  document.getElementById('hall-view').style.display = 'flex';
  // Savat tugmasi #right-panel ni ochadi, u esa zal ekranida yashirin —
  // shuning uchun bu yerda u umuman ko'rinmasligi kerak.
  const ctb = document.getElementById('cart-toggle-btn');
  if (ctb) ctb.style.display = 'none';
  closeCartPanel();
  loadHall();
}

async function backToHall() {
  if (orderDirty && Object.keys(currentOrder?.cart||{}).length) {
    const ok = await NHConfirm.show({
      variant: 'warning',
      title: "Saqlanmagan o'zgarishlar",
      text: "Zal xaritasiga qaytsangiz, yangi o'zgarishlar yo'qolishi mumkin.",
      sub: "Buyurtmani saqlash uchun avval «Pauza» yoki «Yuborish» tugmasini bosing.",
      confirmText: "Zalga qaytish",
      cancelText: "Buyurtmada qolish",
      btnIcon: 'bi-grid-3x3-gap-fill'
    });
    if (!ok) return;
  }
  showHall();
}

async function openTable(stolId, nomi, isBand, orderId) {
  if (isBand && orderId) {
    // Mavjud buyurtmani serverdan yuklaymiz (tahrirlash uchun)
    try {
      const res = await fetch(im_BASE + 'sotuvchi/ajax/get-active-orders.php');
      const d   = await res.json();
      const found = (d.orders||[]).find(o => o.order_id === orderId);
      if (!found) { showToast('Buyurtma topilmadi, yangilanmoqda...', 'warn'); loadHall(); return; }
      const cart = found.cart || {};
      if (!found.olib_ketish) Object.values(cart).forEach(i => { i.olib_ketish_soni = 0; });
      currentOrder = { order_id: found.order_id, stol_id: stolId, mijoz_ism: found.mijoz_ism,
                       olib_ketish: !!found.olib_ketish, cart,
                       base_sig: cartSig(cart), client_token: newToken() };
    } catch { showToast('Yuklab bo\'lmadi', 'error'); return; }
  } else {
    currentOrder = { order_id: 0, stol_id: stolId, mijoz_ism: nomi, olib_ketish: false, cart: {},
                     base_sig: '', client_token: newToken() };
  }
  orderDirty = false;
  enterOrderView(true);
}

function openQuick(label) {
  // Alohida stolsiz buyurtma sifatida faqat Dastavka ochiladi.
  currentOrder = { order_id: 0, stol_id: '', mijoz_ism: label, olib_ketish: false, cart: {},
                   base_sig: '', client_token: newToken() };
  orderDirty = false;
  enterOrderView(false);
}

function startTakeawayOrder(stolId, nomi) {
  if (!stolId) { showToast("Olib ketish buyurtmasi stolga biriktirilishi shart", 'warn'); return; }
  if (orderDirty && Object.keys(currentOrder?.cart || {}).length) {
    showToast("Avval joriy buyurtmani «Pauza» bilan saqlang", 'warn');
    return;
  }
  currentOrder = { order_id:0, stol_id:stolId, mijoz_ism:nomi, olib_ketish:true, cart:{},
                   base_sig:'', client_token:newToken() };
  orderDirty = false;
  enterOrderView(false);
}

function startTakeawayFromCurrent() {
  const ac = currentOrder;
  if (!ac?.stol_id || ac.olib_ketish) return;
  if (!ac.order_id || orderDirty) {
    showToast("Avval asosiy stol buyurtmasini «Pauza» bilan saqlang", 'warn');
    return;
  }
  startTakeawayOrder(ac.stol_id, ac.mijoz_ism);
}

function renderOlibKetishBtn() {
  const btn = document.getElementById('olib-ketish-btn');
  if (!btn) return;
  btn.classList.remove('active');
  btn.innerHTML = '<i class="bi bi-bag-plus-fill"></i> Yangi olib ketish';
}

function enterOrderView(showOlibKetishBtn) {
  document.getElementById('hall-view').style.display = 'none';
  document.getElementById('order-view').classList.add('visible');
  const ctb = document.getElementById('cart-toggle-btn');
  if (ctb) ctb.style.display = '';   // CSS o'zi hal qiladi (mobil: flex)
  document.getElementById('order-stol-nomi').textContent = currentOrder.olib_ketish
    ? currentOrder.mijoz_ism + ' — Olib ketish' : currentOrder.mijoz_ism;
  const okBtn = document.getElementById('olib-ketish-btn');
  // order_id > 0 sharti ataylab qo'shilgan: aks holda tugma stol hali
  // saqlanmasdan turib ham ko'rinardi, bosilganda esa "Avval Pauza bilan
  // saqlang" degan kutilmagan ogohlantirish chiqardi (dukon/pos.php dagi
  // se-takeaway-btn bilan bir xil qoida — bu yerda ham shu qo'llanadi).
  if (okBtn) okBtn.style.display = (showOlibKetishBtn && currentOrder.stol_id && currentOrder.order_id > 0 && !currentOrder.olib_ketish) ? '' : 'none';
  renderOlibKetishBtn();
  const badge = document.getElementById('order-stol-badge');
  if (currentOrder.order_id > 0) { badge.style.display=''; badge.textContent = 'Order #'+currentOrder.order_id; }
  else badge.style.display = 'none';
  document.getElementById('prod-search').value = '';
  snapshotOrder();   // "Pauza" da faqat shundan keyin qo'shilgani tasdiqlanadi
  refreshUI();
  loadProducts();
}

// ══════════════════════════════════════════════════════════
//  HOLD + SEND
// ══════════════════════════════════════════════════════════
async function holdCurrentCart() {
  const ac = currentOrder; if (!ac) return;
  const items = Object.values(ac.cart);
  if (!items.length) { showToast("Savatcha bo'sh!",'warn'); return; }
  const btn = document.getElementById('hold-btn'); btn.disabled = true;
  const cnf = document.getElementById('cnf-ok-btn');
  if (cnf) { cnf.disabled = true; cnf.innerHTML = '<i class="bi bi-hourglass-split"></i> Saqlanmoqda...'; }
  const fd = new FormData();
  fd.append('action','hold'); fd.append('order_id',ac.order_id||0);
  // client_token — takroriy yuborishdan himoya; base_sig — boshqa qurilma
  // qatorlarni o'zgartirgan-o'zgartirmaganini server tekshirishi uchun.
  fd.append('client_token', ac.client_token||''); fd.append('base_sig', ac.base_sig||'');
  fd.append('mijoz_ism',ac.mijoz_ism); fd.append('stol_id',ac.stol_id||''); fd.append('olib_ketish',ac.olib_ketish?1:0); fd.append('izoh','');
  fd.append('items',JSON.stringify(items.map(i=>({mahsulot_id:i.mahsulot_id,set_id:i.set_id||0,soni:i.soni,narx:effN(i),locked_soni:i.locked_soni||0,olib_ketish_soni:ac.olib_ketish?i.soni:0}))));
  try {
    const res = await fetch(im_BASE+'sotuvchi/ajax/order-save.php',{method:'POST',body:fd});
    if (sessiyaTugadi(res)) { closeConfirm(); sessionLost(); return; }
    if (jsonEmas(res)) {
      // Server vaqtincha javob bermadi (502/504). Savat saqlanib qoladi,
      // client_token o'zgarmagani uchun qayta bosish xavfsiz.
      closeConfirm(); showToast("Server javob bermadi — qayta urinib ko'ring",'error');
      btn.disabled=false; return;
    }
    const d   = await res.json();
    if (d.status!=='ok') { closeConfirm(); showToast('❌ '+d.msg,'error'); btn.disabled=false; return; }
    closeConfirm();
    showToast('✅ '+d.msg,'success');
    orderDirty = false;
    // Server bergan id ni eslab qolamiz; yangi "baza" — hozirgi savat.
    if (d.data && d.data.order_id) ac.order_id = parseInt(d.data.order_id) || ac.order_id;
    ac.base_sig = cartSig(ac.cart);
    snapshotOrder();   // saqlangan holat endi yangi "baza" bo'ladi
    showHall();
  } catch {
    closeConfirm();
    // client_token o'zgarmaydi — qayta bosilsa server ikkinchi order ochmaydi.
    showToast("Tarmoq xatosi — qayta urinib ko'ring",'error');
    btn.disabled=false;
  }
}

// ── Yuborishdan oldingi tasdiqlash ────────────────────────
// "Yuborish" bosilganda darrov jo'natilmaydi — avval sotuvchi
// buyurtmani BIR MARTA ko'rib chiqadi. Sabab: yuborilgandan keyin
// buyurtma oshpaz/kassaga o'tadi va sotuvchi uni tahrirlay olmaydi
// (zalda "Kassada" holati), oshpazga ketgan qatorni o'chirib ham
// bo'lmaydi — ya'ni bu qaytarib bo'lmaydigan yagona chegara.
// Oxirgi saqlashdagi holat: {mahsulot_id: soni}. "Pauza" da faqat
// SHUNDAN KEYIN qo'shilgan qismni tasdiqlatish uchun kerak.
let orderBaseline = {};

function snapshotOrder() {
  orderBaseline = {};
  Object.values(currentOrder?.cart || {}).forEach(i => {
    orderBaseline[i._k] = i.soni;
  });
}

// Oxirgi saqlashdan beri qo'shilgan (yoki ko'paytirilgan) qatorlar
function yangiQoshilganlar() {
  return Object.values(currentOrder?.cart || {})
    .map(i => {
      const eski = orderBaseline[i._k] || 0;
      return (i.soni > eski) ? Object.assign({}, i, { soni: i.soni - eski, _eski: eski }) : null;
    })
    .filter(Boolean);
}

// mode: 'send' — kassaga yuborish (butun buyurtma)
//       'hold' — pauza (faqat yangi qo'shilgan qism)
let confirmMode = 'send';

function openConfirm(mode) {
  confirmMode = mode || 'send';
  const ac = currentOrder; if (!ac) return;

  const items = (confirmMode === 'hold') ? yangiQoshilganlar() : Object.values(ac.cart);

  // Pauzada yangi narsa bo'lmasa tasdiqlash so'ralmaydi — to'g'ridan-to'g'ri saqlaymiz
  if (confirmMode === 'hold' && !items.length) { holdCurrentCart(); return; }
  if (!items.length) return;

  document.getElementById('cnf-title-text').textContent =
    confirmMode === 'hold' ? "Qo'shilganlarni tasdiqlang" : 'Buyurtmani tasdiqlang';

  document.getElementById('cnf-stol-nomi').textContent = ac.mijoz_ism || '—';

  const ok = document.getElementById('cnf-olib-ketish');
  ok.style.display = ac.olib_ketish ? '' : 'none';

  const oid = document.getElementById('cnf-order-id');
  if (ac.order_id > 0) { oid.style.display=''; oid.textContent = 'Order #' + ac.order_id; }
  else oid.style.display = 'none';

  let total = 0;
  document.getElementById('cnf-body').innerHTML = items.map(i => {
    const en = effN(i), summa = en * i.soni;
    total += summa;
    const isU  = i.ulg_min > 0 && i.soni >= i.ulg_min && i.ulg_narx > 0;
    const lock = parseFloat(i.locked_soni || 0);
    const lb   = lock > 0 ? `<span class="cnf-lock">🍳 ${lock} ta oshpazda</span>` : '';
    const ub   = isU ? '<span class="cnf-lock" style="background:#d1fae5;color:#047857">Ulgurji</span>' : '';
    const oks  = parseFloat(i.olib_ketish_soni || 0);
    const okb  = oks > 0 ? `<span class="cnf-lock" style="background:#ffedd5;color:#9a3412">🛍️ ${oks} ta qadoqlanadi</span>` : '';
    const sb   = i.set_id ? `<span class="cnf-lock" style="background:#fef9c3;color:#b8860b">🎁 ${im_esc(i.set_nomi)||'Set'}</span>` : '';
    return `<div class="cnf-row">
      <div class="cnf-nomi">${im_esc(i.nomi)}${sb}${lb}${ub}${okb}
        <div class="cnf-hisob">${fmt(en)} × ${qtyLabel(i.soni)}${i.birlik ? ' ' + im_esc(i.birlik) : ''}</div>
      </div>
      <div class="cnf-summa">${fmt(summa)} so'm</div>
    </div>`;
  }).join('');

  document.getElementById('cnf-total').innerHTML = fmt(total) + ' <span>so\'m</span>';

  const okBtn = document.getElementById('cnf-ok-btn');
  okBtn.disabled = false;
  okBtn.innerHTML = (confirmMode === 'hold')
    ? '<i class="bi bi-check-lg"></i> Tasdiqlash'
    : '<i class="bi bi-send-fill"></i> Tasdiqlash va yuborish';
  document.getElementById('cnf-overlay').classList.add('show');
}

// Modal tugmasi rejimga qarab tegishli amalni bajaradi
function confirmOk() {
  if (confirmMode === 'hold') holdCurrentCart();
  else sendOrder();
}

function closeConfirm() {
  document.getElementById('cnf-overlay').classList.remove('show');
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && document.getElementById('cnf-overlay').classList.contains('show')) closeConfirm();
});

async function sendOrder() {
  const ac = currentOrder; if (!ac) return;
  const items = Object.values(ac.cart); if (!items.length) return;
  const btn = document.getElementById('send-btn');
  const cnf = document.getElementById('cnf-ok-btn');
  cnf.disabled=true; cnf.innerHTML='<i class="bi bi-hourglass-split"></i> Yuborilmoqda...';
  btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split"></i> Yuborilmoqda...';
  const fd = new FormData();
  fd.append('action','create'); fd.append('order_id',ac.order_id||0);
  fd.append('client_token', ac.client_token||''); fd.append('base_sig', ac.base_sig||'');
  fd.append('mijoz_ism',ac.mijoz_ism); fd.append('stol_id',ac.stol_id||''); fd.append('olib_ketish',ac.olib_ketish?1:0); fd.append('izoh','');
  fd.append('items',JSON.stringify(items.map(i=>({mahsulot_id:i.mahsulot_id,set_id:i.set_id||0,soni:i.soni,narx:effN(i),locked_soni:i.locked_soni||0,olib_ketish_soni:ac.olib_ketish?i.soni:0}))));
  try {
    const res = await fetch(im_BASE+'sotuvchi/ajax/order-save.php',{method:'POST',body:fd});
    if (sessiyaTugadi(res)) { closeConfirm(); sessionLost(); return; }
    if (jsonEmas(res)) {
      closeConfirm(); showToast("Server javob bermadi — qayta urinib ko'ring",'error');
      btn.disabled=false; btn.innerHTML='<i class="bi bi-send-fill"></i> Yuborish';
      return;
    }
    const d   = await res.json();
    if (d.status!=='ok') {
      // Xato — tasdiqlash oynasini yopamiz, sotuvchi savatni tuzatsin
      closeConfirm();
      showToast('❌ '+d.msg,'error');
      btn.disabled=false; btn.innerHTML='<i class="bi bi-send-fill"></i> Yuborish';
      return;
    }
    orderDirty = false;
    closeConfirm();
    if (d.data && d.data.order_id) ac.order_id = parseInt(d.data.order_id) || ac.order_id;
    const oid = d.data?.order_id||'';
    document.getElementById('sent-msg').textContent = `Order #${oid} — ${ac.mijoz_ism} — kassaga muvaffaqiyatli yuborildi`;
    document.getElementById('sent-overlay').classList.add('show');
  } catch {
    closeConfirm();
    showToast("Tarmoq xatosi — qayta urinib ko'ring",'error');
    btn.disabled=false; btn.innerHTML='<i class="bi bi-send-fill"></i> Yuborish';
  }
}

function afterSend() {
  document.getElementById('sent-overlay').classList.remove('show');
  document.getElementById('send-btn').innerHTML = '<i class="bi bi-send-fill"></i> Yuborish';
  document.getElementById('send-btn').disabled = false;
  showHall();
}

// ══════════════════════════════════════════════════════════
//  SAVATCHA AMALLAR
// ══════════════════════════════════════════════════════════
// Savat kaliti: à la carte uchun "pid", setdan bo'lsa "pid_sSETID".
// Mahsulot karta bosilsa DOIM à la carte qatoriga tushadi.
function qtyStep(item) {
  const q = parseFloat(item?.sotuv_qadami || 1);
  return q > 0 ? q : 1;
}
function roundQty(v) { return parseFloat((Math.round((parseFloat(v) || 0) * 1000) / 1000).toFixed(3)); }
function qtyLabel(v) {
  const n = roundQty(v), whole = Math.floor(n + 0.0001), frac = roundQty(n - whole);
  const mark = Math.abs(frac-.25)<.001 ? '¼' : (Math.abs(frac-.5)<.001 ? '½' : (Math.abs(frac-.75)<.001 ? '¾' : ''));
  if (mark) return (whole ? whole : '') + mark;
  return Number.isInteger(n) ? String(n) : n.toLocaleString('uz-UZ',{maximumFractionDigits:3});
}

function toggleCart(pid, amount) {
  const ac = currentOrder; if (!ac) return;
  const p = allProducts.find(x => x.id==pid); if (!p) return;
  const k = String(pid);
  const baseQadam = qtyStep(p);
  const qadam = amount > 0 ? roundQty(amount) : baseQadam;
  if (ac.cart[k]) {
    ac.cart[k].soni = Math.min(roundQty(ac.cart[k].soni + qadam), qtyCeil(ac.cart[k], p.qoldiq));
    ac.cart[k].olib_ketish_soni = ac.olib_ketish ? ac.cart[k].soni : 0;
  } else {
    ac.cart[k] = {
      mahsulot_id:pid, set_id:null, set_nomi:null, _k:k,
      nomi:p.nomi, narx:parseFloat(p.narx),
      ulg_min:parseInt(p.ulg_min)||0, ulg_narx:parseFloat(p.ulg_narx)||0,
      soni:qadam, birlik:p.birlik||'', sotuv_qadami:baseQadam, qoldiq:parseFloat(p.qoldiq),
      locked_soni:0, rezerv_soni:0,
      // Mustaqil Olib ketish orderida barcha miqdor qadoqlanadi.
      olib_ketish_soni: ac.olib_ketish ? qadam : 0
    };
  }
  orderDirty = true;
  // MUHIM: savat AVTOMATIK ochilmaydi. Ilgari har bir qo'shishda mobil
  // savat panelini ochib yuborardi va ofitsant har safar orqaga qaytishga
  // majbur bo'lardi. Endi jami pastdagi panelda ko'rinadi, terish uzilmaydi.
  refreshCart();
}

function changeQty(k, delta) {
  const ac = currentOrder; if (!ac?.cart[k]) return;
  const it = ac.cart[k];
  const change = delta * qtyStep(it);
  const locked = parseFloat(it.locked_soni||0);
  if (change<0 && roundQty(it.soni+change)<locked) {
    showToast(`Oshpazga ketgan ${qtyLabel(locked)} miqdordan kamaytirish mumkin emas!`,'warn'); return;
  }
  it.soni = roundQty(it.soni + change);
  // Olib ketish alohida order: undagi barcha miqdor qadoqlanadi.
  it.olib_ketish_soni = ac.olib_ketish ? Math.max(0, it.soni) : 0;
  const tepa = qtyCeil(it);
  if (it.soni<=0 && locked===0) delete ac.cart[k];
  else if (it.soni>tepa) { it.soni=tepa; showToast("Qoldiq yetarli emas!",'warn'); }
  orderDirty = true;
  refreshCart();
}

function removeItem(k) {
  const ac = currentOrder; if (!ac) return;
  if ((ac.cart[k]?.locked_soni||0)>0) { showToast("Oshpazga ketgan mahsulotni o'chira olmaysiz!",'warn'); return; }
  delete ac.cart[k]; orderDirty = true; refreshCart();
}

async function clearCart() {
  const ac = currentOrder; if (!ac||!Object.keys(ac.cart).length) return;
  if (Object.values(ac.cart).some(i=>(i.locked_soni||0)>0)) { showToast("Oshpazga ketgan buyurtma bor!",'error'); return; }
  const ok = await NHConfirm.show({
    variant: 'danger',
    title: "Savatchani tozalash",
    text: "Savatchadagi barcha mahsulotlar olib tashlanadi.",
    sub: "Hali oshpazga yuborilmagan o'zgarishlar saqlanmaydi.",
    confirmText: "Savatni tozalash",
    icon: 'bi-cart-x-fill',
    btnIcon: 'bi-cart-x-fill'
  });
  if (!ok) return;
  ac.cart = {}; orderDirty = true; refreshCart();
}

// ══════════════════════════════════════════════════════════
//  RENDER
// ══════════════════════════════════════════════════════════
// Kartadagi qoldiq matni.
// Oshpaz tayyorlaydigan taomda vitrina qoldig'i yo'q — uning o'rniga
// xomashyodan nechta chiqishi ko'rsatiladi. Bu FAQAT ma'lumot: xomashyo
// tugagan bo'lsa ham ofitsant taomni qo'sha oladi (mijoz xohishi).
function prodQoldiqMatni(p) {
  if (parseInt(p.auto_maydalash)) {
    return `<span style="color:#b45309">✂️ Tayyor: ${qtyLabel(p.tayyor_qoldiq||0)} · butundan: ${qtyLabel(p.auto_imkon||0)}</span>`;
  }
  if (!parseInt(p.oshpaz_kerak)) {
    return `Qoldiq: ${qtyLabel(p.qoldiq)} ${p.birlik||''}`;
  }
  if (p.imkon === null || p.imkon === undefined) {
    return `<span style="color:#7c3aed">🍳 Oshxonada tayyorlanadi</span>`;
  }
  if (p.imkon <= 0) {
    return `<span style="color:#dc2626;font-weight:700">⚠️ Xomashyo tugagan</span>`;
  }
  return `<span style="color:#7c3aed">🍳 Xomashyodan: ${p.imkon} ${p.birlik||''}</span>`;
}

function renderProducts() {
  // "🎁 Setlar" tab ochiq bo'lsa — grid setlar bilan qoladi
  // (refreshCart() renderProducts() ni chaqiradi, aks holda setlar yo'qolardi)
  if (activeKat === '__sets__') { renderSetsGrid(setsCache); return; }
  const cart = currentOrder?.cart || {};
  if (!allProducts.length) {
    document.getElementById('prod-grid').innerHTML = '<div class="prod-empty"><i class="bi bi-inbox"></i><br>Mahsulot topilmadi</div>';
    return;
  }
  document.getElementById('prod-grid').innerHTML = allProducts.map(p => {
    const inCart = !!cart[p.id];
    const qadam = qtyStep(p);
    const img = p.rasm
      ? `<img src="${RASM_BASE}${p.rasm}" loading="lazy" onerror="this.parentElement.innerHTML='🛒'">`
      : '🛒';
    // Savatda bo'lsa — kartaning o'zida miqdorni sozlash mumkin.
    // Shu tufayli oddiy holatda savatni ochish umuman kerak emas.
    const step = inCart ? `<div class="prod-step" onclick="event.stopPropagation()">
        <button class="ps-btn" onclick="event.stopPropagation();changeQty(${p.id},-1)">−</button>
        <span class="ps-num">${qtyLabel(cart[p.id].soni)}</span>
        <button class="ps-btn" onclick="event.stopPropagation();changeQty(${p.id},1)">+</button>
      </div>` : '';
    const fractions = qadam < 1 ? `<div onclick="event.stopPropagation()" style="display:flex;gap:4px;padding:0 8px 7px">
        ${qadam <= .25 ? `<button class="ps-btn" style="width:auto;padding:0 7px" onclick="toggleCart(${p.id},.25)">+¼</button>` : ''}
        ${qadam <= .5 ? `<button class="ps-btn" style="width:auto;padding:0 7px" onclick="toggleCart(${p.id},.5)">+½</button>` : ''}
        <button class="ps-btn" style="width:auto;padding:0 7px" onclick="toggleCart(${p.id},1)">+1</button>
      </div>` : '';
    return `<div class="prod-card ${inCart?'in-cart':''}" id="pc-${p.id}" onclick="toggleCart(${p.id})">
      <div class="prod-badge" id="pb-${p.id}">${cart[p.id] ? qtyLabel(cart[p.id].soni) : ''}</div>
      <div class="prod-img">${img}</div>
      <div class="prod-info">
        <div class="prod-name">${im_esc(p.nomi)}</div>
        <div class="prod-narx">${fmt(p.narx)} <span style="font-size:9px;color:var(--muted)">so'm</span></div>
        <div class="prod-qoldiq">${prodQoldiqMatni(p)}</div>
      </div>
      ${fractions}
      ${step}
    </div>`;
  }).join('');
}

function refreshCart() {
  const ac    = currentOrder;
  const items = Object.values(ac?.cart||{});
  const total = items.reduce((s,i)=>s+effN(i)*i.soni,0);
  const has   = items.length>0;

  document.getElementById('cart-count').textContent = items.length;
  document.getElementById('total-val').innerHTML    = fmt(total)+' <span>so\'m</span>';
  ['send-btn','hold-btn'].forEach(id=>document.getElementById(id).disabled=!has);
  const pp=document.getElementById('print-pre-btn'); if(pp) pp.disabled=!has;
  document.getElementById('clear-btn').style.display = has?'':'none';

  const tb = document.getElementById('cart-toggle-badge');
  if (tb) { tb.textContent=items.length; tb.style.display=has?'flex':'none'; }

  // Mobil pastki panel — savat ochilmasdan jami ko'rinib turadi
  const mb = document.getElementById('mob-bar');
  if (mb) {
    mb.classList.toggle('show', has);
    const jamiDona = items.reduce((s,i)=>s+i.soni,0);
    document.getElementById('mob-bar-count').textContent =
      `${items.length} xil · ${qtyLabel(jamiDona)} birlik`;
    document.getElementById('mob-bar-total').textContent = fmt(total) + " so'm";
  }

  const body = document.getElementById('cart-body');
  if (!has) {
    body.innerHTML=`<div class="cart-empty"><i class="bi bi-cart-x"></i><div>Savatcha bo'sh</div><small>Chapdan mahsulot tanlang</small></div>`;
    renderProducts(); return;
  }
  // ── Qatorni chizish (guruh: 'eski' yoki 'yangi') ──────────
  const qatorHtml = (i, guruh) => {
    const k = i._k;
    const en=effN(i), isU=i.ulg_min>0&&i.soni>=i.ulg_min&&i.ulg_narx>0, lock=parseFloat(i.locked_soni||0);
    const lb=lock>0?`<span style="font-size:9px;background:#fef3c7;color:#92400e;border-radius:4px;padding:1px 4px;margin-left:3px">🍳 ${qtyLabel(lock)} oshpazda</span>`:'';
    const ub=isU?'<span style="font-size:9px;background:#d1fae5;color:#047857;border-radius:4px;padding:1px 4px;margin-left:3px">Ulgurji</span>':'';
    const sb=i.set_id?`<span style="font-size:9px;background:#fef9c3;color:#b8860b;border-radius:4px;padding:1px 4px;margin-left:3px">🎁 ${im_esc(i.set_nomi)||'Set'}</span>`:'';
    const sp=isU&&i.narx!==en?`<span style="text-decoration:line-through;color:#aaa;font-size:9px">${fmt(i.narx)}</span> `:'';
    const ok  = !!ac.olib_ketish;
    const okb = ok ? `<span class="cnf-lock" style="background:#ffedd5;color:#9a3412">🛍️ Qadoqlanadi</span>` : '';

    // Qisman yangi: "2 ta avval + 1 ta yangi" deb yozib beramiz
    const baza = orderBaseline[k] || 0;
    const yangiSoni = Math.max(0, i.soni - baza);
    const split = (guruh === 'yangi' && baza > 0)
      ? `<div class="ci-split"><b>${qtyLabel(baza)} avval</b> + ${qtyLabel(yangiSoni)} yangi</div>` : '';

    return `<div class="cart-item ${guruh}"${ok ? ' style="background:#fff7ed;border-color:#fed7aa"' : ''}>
      <div style="flex:1;min-width:0">
        <div class="ci-name">${im_esc(i.nomi)}${sb}${lb}${ub}${okb}</div>
        <div class="ci-narx">${sp}${fmt(en)}×${qtyLabel(i.soni)} = <strong>${fmt(en*i.soni)}</strong> so'm</div>
        ${split}
      </div>
      <div class="ci-qty">
        <button class="ci-qty-btn" onclick="changeQty('${k}',-1)">−</button>
        <span class="ci-qty-num">${qtyLabel(i.soni)}</span>
        <button class="ci-qty-btn" onclick="changeQty('${k}',1)">+</button>
      </div>
      <button class="ci-del" onclick="removeItem('${k}')"><i class="bi bi-trash3"></i></button>
    </div>`;
  };

  // Oxirgi saqlashdagi holat (orderBaseline) bo'yicha ikkiga ajratamiz
  const baza    = (i) => orderBaseline[i._k] || 0;
  const yangilar = items.filter(i => i.soni > baza(i));
  const eskilar  = items.filter(i => i.soni <= baza(i));

  let html = '';
  if (eskilar.length) {
    html += `<div class="cart-sec eski"><i class="bi bi-check2-circle"></i> Avval yuborilgan
             <span class="num">${eskilar.length} xil</span></div>`
          + eskilar.map(i => qatorHtml(i, 'eski')).join('');
  }
  if (yangilar.length) {
    html += `<div class="cart-sec yangi"><i class="bi bi-plus-circle-fill"></i> Yangi qo'shilgan
             <span class="num">${eskilar.length ? fmt(yangiSumma(yangilar)) + " so'm" : yangilar.length + ' xil'}</span></div>`
          + yangilar.map(i => qatorHtml(i, 'yangi')).join('');
  }
  body.innerHTML = html;
  renderProducts();
}

// Yangi qo'shilgan qismning summasi (faqat farq, butun qator emas)
function yangiSumma(yangilar) {
  return yangilar.reduce((s, i) => s + effN(i) * (i.soni - (orderBaseline[i._k] || 0)), 0);
}

function refreshUI() { refreshCart(); }

// ══════════════════════════════════════════════════════════
//  MAHSULOTLAR
// ══════════════════════════════════════════════════════════
function filterKat(kat, btn) {
  activeKat = kat;
  document.querySelectorAll('.kat-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  loadProducts(document.getElementById('prod-search').value.trim());
}

async function loadProducts(q = '') {
  // "🎁 Setlar" tab — mahsulot emas, setlar ro'yxati chiqadi
  if (activeKat === '__sets__') { loadSets(q); return; }
  const grid = document.getElementById('prod-grid');
  grid.innerHTML='<div class="prod-empty"><i class="bi bi-hourglass-split"></i><br>Yuklanmoqda...</div>';
  try {
    const url = `${im_BASE}sotuvchi/ajax/get-products.php?q=${encodeURIComponent(q)}&kat=${activeKat}`;
    const res = await fetch(url);
    const d   = await res.json();
    if (d.status!=='ok'||!d.data?.length) {
      allProducts=[];
      grid.innerHTML='<div class="prod-empty"><i class="bi bi-inbox"></i><br>Mahsulot topilmadi</div>';
      return;
    }
    allProducts = d.data;
    renderProducts();
  } catch { grid.innerHTML="<div class='prod-empty'>Yuklab bo'lmadi</div>"; }
}

// ══════════════════════════════════════════════════════════
//  🎁 SETLAR — ofitsant paneli
//  Set bosilganda uning tarkibiy mahsulotlari savatga alohida
//  qatorlar bo'lib tushadi (proporsional narx bilan). im_sotuvchi_order
//  bitta mahsulot = bitta qator modelida ishlaydi, shuning uchun
//  set_id saqlanmaydi — oshxona baribir taomlarni alohida ko'radi.
// ══════════════════════════════════════════════════════════
let setsCache = [];

async function loadSets(q = '') {
  const grid = document.getElementById('prod-grid');
  grid.innerHTML = '<div class="prod-empty"><i class="bi bi-hourglass-split"></i><br>Setlar yuklanmoqda...</div>';
  try {
    const res = await fetch(im_BASE + 'dukon/ajax/set-list.php');
    const d   = await res.json();
    setsCache = (d.status === 'ok') ? (d.data?.list || []) : [];
  } catch { setsCache = []; }

  const badge = document.getElementById('set-count');
  if (badge) badge.textContent = setsCache.length ? `(${setsCache.length})` : '';

  const ql = q.trim().toLowerCase();
  renderSetsGrid(ql ? setsCache.filter(s => s.nomi.toLowerCase().includes(ql)) : setsCache);
}

function renderSetsGrid(list) {
  const grid = document.getElementById('prod-grid');
  if (!list.length) {
    grid.innerHTML = `<div class="prod-empty"><i class="bi bi-gift"></i><br>Set yo'q
      <small>Setlar kassa (Dukon) panelidan yaratiladi</small></div>`;
    return;
  }
  grid.innerHTML = list.map(set => {
    const items_html = set.items.map(it =>
      `<div>· ${im_esc(it.nomi)} <b>×${qtyLabel(it.soni)}</b></div>`
    ).join('');
    const unavail = !set.available;
    return `<div class="prod-card set-card ${unavail ? 'sold-out' : ''}" style="border-color:${im_esc(set.rang)}"
         onclick="${unavail ? '' : `addSetToCart(${set.id})`}">
      <div class="set-head" style="background:${im_esc(set.rang)}">🎁 ${im_esc(set.nomi)}</div>
      <div class="prod-info">
        <div class="set-items">${items_html}</div>
        <div class="prod-narx" style="color:${im_esc(set.rang)}">${fmt(set.narxi)} <span style="font-size:9px;color:var(--muted)">so'm</span></div>
        ${unavail ? '<div class="prod-qoldiq" style="color:#dc2626;font-weight:700">⚠️ Qoldiq yetarli emas</div>' : ''}
      </div>
    </div>`;
  }).join('');
}

function addSetToCart(setId) {
  const ac = currentOrder; if (!ac) return;
  const set = setsCache.find(s => s.id === setId);
  if (!set) return;
  if (!set.available) { showToast('Qoldiq yetarli emas', 'warn'); return; }

  // Proporsional narx: set narxini komponentlar orasida taqsimlaymiz
  const aslYigindi = set.items.reduce((s, i) => s + i.sotuv_narxi * i.soni, 0);
  const koeff = aslYigindi > 0 ? set.narxi / aslYigindi : 1;

  set.items.forEach(it => {
    const pid = it.mahsulot_id;
    const k = pid + '_s' + set.id;   // set qatori — à la carte'dan alohida
    const propNarx = aslYigindi > 0
      ? Math.round(it.sotuv_narxi * koeff)
      : Math.round(set.narxi / set.items.length);
    if (ac.cart[k]) {
      ac.cart[k].soni += it.soni;
      if (ac.olib_ketish) ac.cart[k].olib_ketish_soni = ac.cart[k].soni;
    } else {
      ac.cart[k] = {
        mahsulot_id: pid, set_id: set.id, set_nomi: set.nomi, _k: k,
        nomi: it.nomi, narx: propNarx,
        ulg_min: 0, ulg_narx: 0,
        soni: it.soni, birlik: it.birlik || '', sotuv_qadami: parseFloat(it.sotuv_qadami)||1,
        qoldiq: it.qoldiq || 9999, locked_soni: 0, rezerv_soni: 0,
        olib_ketish_soni: ac.olib_ketish ? it.soni : 0,
      };
    }
  });
  orderDirty = true;
  showToast(`🎁 «${set.nomi}» savatga qo'shildi`, 'success');
  refreshCart();
}

// ══════════════════════════════════════════════════════════
//  DASTLABKI CHEK
// ══════════════════════════════════════════════════════════
function printPreReceipt() {
  const ac = currentOrder; if (!ac) return;
  const items = Object.values(ac.cart); if (!items.length) return;
  let jami=0, rows=''; const ts=new Date().toLocaleString('uz-UZ');
  items.forEach(i=>{const s=effN(i)*i.soni;jami+=s;rows+=`<div class="row"><span>${im_esc(i.nomi)}</span><span>${qtyLabel(i.soni)}</span><span>${fmt(s)}</span></div>`;});
  const html=`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Dastlabki Chek</title>
<style>*{margin:0;padding:0;box-sizing:border-box;font-family:'Courier New',monospace}@page{size:80mm auto;margin:2mm}
.chek{width:80mm;padding:2mm}.h{text-align:center;font-size:18px;font-weight:900}.sub{text-align:center;font-size:12px}
.info{display:flex;justify-content:space-between;font-size:11px;margin:1mm 0}
.row{display:grid;grid-template-columns:1fr 14mm 22mm;font-size:12px;padding:1mm 0;border-top:1px dotted #000}
.dash{border-top:2px dashed #000;margin:2mm 0}.solid{border-top:3px solid #000;margin:2mm 0}
.total{display:flex;justify-content:space-between;font-size:16px;font-weight:900;padding-top:2mm}
.warn{border:2px solid #000;padding:2mm;text-align:center;font-size:12px;font-weight:900;margin:3mm 0}
</style></head><body onload="setTimeout(()=>{window.print();window.close();},400)">
<div class="chek"><div class="h"><?= im_f($filial_nomi) ?></div><div class="sub">DASTLABKI CHEK</div>
<div class="warn">⚠️ PULI TO'LANMAGAN</div><hr class="solid">
<div class="info"><span>Sana:</span><span>${ts}</span></div>
<div class="info"><span>Stol:</span><span>${im_esc(ac.mijoz_ism)||'—'}</span></div><hr class="dash">
<div class="row" style="font-weight:900"><span>Nomi</span><span>Soni</span><span>Summa</span></div>
<hr class="dash">${rows}<hr class="solid">
<div class="total"><span>JAMI:</span><span>${fmt(jami)} so'm</span></div>
<div class="warn" style="margin-top:4mm">BU CHEK BILAN MAHSULOT BERILMAYDI!</div>
</div></body></html>`;
  const w=window.open('','_blank','width=400,height=600'); w.document.write(html); w.document.close();
}

// ══════════════════════════════════════════════════════════
//  QIDIRUV
// ══════════════════════════════════════════════════════════
let _sT;
document.getElementById('prod-search').addEventListener('input',function(){
  clearTimeout(_sT); _sT=setTimeout(()=>loadProducts(this.value.trim()),350);
});

// ══════════════════════════════════════════════════════════
//  "TAOM TAYYOR" SIGNALI
//  Oshpaz "Tayyor" bosganda ofitsantda qo'ng'iroq + xabar chiqadi.
//  Zal so'rovidan MUSTAQIL ishlaydi — ofitsant boshqa stol ustida
//  ishlayotgan bo'lsa ham signalni o'tkazib yubormaydi.
// ══════════════════════════════════════════════════════════
let _actx = null;
function unlockAudio(){
  if (_actx) return;
  try { _actx = new (window.AudioContext||window.webkitAudioContext)(); } catch {}
}
document.addEventListener('click', unlockAudio, {once:true});
document.addEventListener('keydown', unlockAudio, {once:true});

function ringBell(times){
  times = times || 2;
  unlockAudio();
  if (!_actx) return;
  if (_actx.state === 'suspended') _actx.resume().catch(()=>{});
  for (let n = 0; n < times; n++) {
    const t0 = _actx.currentTime + n * 0.42;
    [988, 1319].forEach((freq, k) => {
      const osc = _actx.createOscillator(), gn = _actx.createGain();
      osc.type = 'sine'; osc.frequency.value = freq;
      gn.gain.setValueAtTime(0.0001, t0);
      gn.gain.exponentialRampToValueAtTime(k ? 0.25 : 0.35, t0 + 0.01);
      gn.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.35);
      osc.connect(gn); gn.connect(_actx.destination);
      osc.start(t0); osc.stop(t0 + 0.36);
    });
  }
}

// Ko'rsatilgan signallar — sahifa yangilanganda takror jiringlamasligi uchun
const TAYYOR_KEY = 'sotuvchi_tayyor_korilgan';
function korilganlar() {
  try { return new Set(JSON.parse(localStorage.getItem(TAYYOR_KEY) || '[]')); }
  catch { return new Set(); }
}
function korilganSaqla(set) {
  try { localStorage.setItem(TAYYOR_KEY, JSON.stringify([...set].slice(-200))); } catch {}
}

function tayyorBanner(list) {
  const el = document.createElement('div');
  // im_esc() — XSS'dan himoya (mijoz_ism erkin matn). Olib ketish
  // orderlari ham stol nomi bilan kelgani uchun (masalan "Stol 2, Stol 2")
  // ofitsant ikkalasini bir-biridan ajrata olmasdi — shuning uchun
  // olib_ketish bo'lganlariga 🛍️ belgisi qo'shiladi.
  const nomlar = list.map(t => im_esc(t.mijoz_ism) + (t.olib_ketish ? ' 🛍️ olib ketish' : '')).join(', ');
  el.style.cssText = `position:fixed;top:60px;left:50%;transform:translateX(-50%);
    z-index:99999;background:linear-gradient(135deg,#10b981,#059669);color:#fff;
    padding:14px 22px;border-radius:14px;font-size:15px;font-weight:800;
    box-shadow:0 8px 30px rgba(16,185,129,.45);animation:popIn .25s ease;
    display:flex;align-items:center;gap:10px;max-width:92vw;cursor:pointer;`;
  el.innerHTML = `<i class="bi bi-bell-fill" style="font-size:20px"></i>
    <span>🍽️ Taom tayyor — ${nomlar}</span>`;
  el.onclick = () => el.remove();
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity='0'; el.style.transition='.4s'; setTimeout(()=>el.remove(),400); }, 8000);
}

async function checkTayyor() {
  if (sessionDead) return;
  try {
    const res = await fetch(im_BASE + 'sotuvchi/ajax/get-tayyor.php');
    if (sessiyaTugadi(res)) { sessionLost(); return; }
    if (jsonEmas(res))      { netDown();    return; }
    const d   = await res.json();
    if (d.status !== 'ok') { netDown(); return; }

    const seen  = korilganlar();
    const faol  = new Set((d.tayyor || []).map(t => t.order_id));
    const yangi = (d.tayyor || []).filter(t => !seen.has(t.order_id));

    if (yangi.length) {
      ringBell(3);
      tayyorBanner(yangi);
      yangi.forEach(t => seen.add(t.order_id));
    }
    // Ro'yxatdan chiqqanlarini unutamiz (keyingi safar qaytsa yana jiringlaydi)
    [...seen].forEach(id => { if (!faol.has(id)) seen.delete(id); });
    korilganSaqla(seen);
    netUp();
  } catch { netDown(); }
}

// ══════════════════════════════════════════════════════════
//  BOSHLASH
// ══════════════════════════════════════════════════════════
loadHall();
setInterval(() => { if (!currentOrder) loadHall(); }, 8000);
checkTayyor();
setInterval(checkTayyor, 10000);
</script>
</body>
</html>
