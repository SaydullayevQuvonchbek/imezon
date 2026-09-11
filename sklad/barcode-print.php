<?php
// ============================================================
//  IMezon — Barcode Chop (Xprinter XP-365B / 58mm termal)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

$mah_id_filter = (int)($_GET['id'] ?? 0);
$search        = trim($_GET['q'] ?? '');
$kat_filter    = (int)($_GET['kat'] ?? 0);

// Kategoriyalar
$kategoriyalar = $db->rows("SELECT id, nomi FROM im_kategoriyalar WHERE status=1 ORDER BY nomi");

// Mahsulotlar
$where = "WHERE m.status=1";
if ($mah_id_filter) $where .= " AND m.id=$mah_id_filter";
if ($kat_filter)    $where .= " AND m.kategoriya_id=$kat_filter";
if ($search) {
    $q_s = mysqli_real_escape_string($link, $search);
    $where .= " AND (m.nomi LIKE '%$q_s%' OR m.barcode LIKE '%$q_s%')";
}

$mahsulotlar = $db->rows(
    "SELECT m.id, m.nomi, m.barcode,
            k.nomi AS kat_nomi,
            COALESCE(n.sotish_narxi, 0) AS sotuv_narx
     FROM im_mahsulotlar m
     LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
     LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
     $where
     ORDER BY m.nomi ASC"
);

$dukon_nomi  = im_sozlama('dukon_nomi') ?: 'IMezon';
$page_title  = 'Barcode Chop';
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= im_f($page_title) ?> | IMezon Sklad</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
  <style>
    /* ── Mahsulot kartasi ── */
    .bc-card {
      background: var(--card);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      cursor: pointer;
      transition: all .18s;
      overflow: hidden;
      user-select: none;
    }
    .bc-card:hover { border-color: var(--accent); box-shadow: 0 4px 16px rgba(226,185,111,.18); }
    .bc-card.selected { border-color: var(--accent); background: rgba(226,185,111,.07); }

    .bc-body { padding: 10px 12px; }
    .bc-name { font-size: 12.5px; font-weight: 700; margin-bottom: 2px; line-height: 1.3; }
    .bc-code { font-size: 10px; font-family: monospace; color: var(--muted); }

    .bc-foot {
      padding: 6px 12px 8px;
      border-top: 1px solid var(--border-light);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .bc-check {
      width: 20px; height: 20px;
      border-radius: 50%;
      background: var(--accent);
      color: var(--primary);
      display: none;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 900;
      flex-shrink: 0;
      float: right;
    }
    .bc-card.selected .bc-check { display: flex; }

    .qty-input {
      width: 60px;
      padding: 3px 6px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      font-size: 13px;
      text-align: center;
      background: var(--card);
      color: var(--text);
      font-family: inherit;
    }

    /* ── Preview sticker ── */
    .pv-wrap {
      display: flex;
      justify-content: center;
      padding: 12px 0;
    }
    .pv-sticker {
      width: 58mm;
      background: #fff;
      border: 1px dashed #aaa;
      font-family: Arial, sans-serif;
      box-sizing: border-box;
      padding: 2mm 3mm;
      color: #000;
    }
    .pv-shop  { text-align:center; font-size:8pt; font-weight:900; border-bottom:1px solid #000; padding-bottom:1mm; margin-bottom:1.5mm; }
    .pv-bc    { text-align:center; margin:0; line-height:0; }
    .pv-bc svg{ max-width:100%; height:auto; display:block; margin:0 auto; }
    .pv-code  { text-align:center; font-size:7pt; font-family:monospace; font-weight:700; margin-top:1mm; letter-spacing:.5px; }
    .pv-name  { text-align:center; font-size:7.5pt; font-weight:700; margin-top:1.5mm; word-break:break-word; line-height:1.3; }

    /* ── Bosma sahifa ── */
    @media print {
      body * { visibility: hidden; }
      #print-zone, #print-zone * { visibility: visible; }
      #print-zone { position: fixed; top: 0; left: 0; }
    }
  </style>
</head>
<body>
<div class="im-wrapper">
  <?php require_once __DIR__ . '/navbar.php'; ?>

  <div class="im-main">
    <header class="im-topbar">
      <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
      <div class="im-page-title"><i class="bi bi-upc-scan me-1"></i> Barcode Chop</div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
        <button class="im-btn im-btn-primary" id="btn-print" disabled>
          <i class="bi bi-printer-fill"></i> Chop etish
          <span id="sel-count" class="ms-1" style="font-size:11px"></span>
        </button>
      </div>
    </header>

    <main class="im-content">
      <div class="im-breadcrumb">
        <a href="<?= im_BASE ?>sklad/index.php"><i class="bi bi-house-fill"></i></a>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Barcode Chop</span>
      </div>

      <!-- Filter -->
      <div class="im-card mb-3 im-slide-in">
        <div class="im-card-body" style="padding:12px 18px">
          <form method="GET" class="d-flex align-center gap-3 flex-wrap">
            <div class="im-search" style="max-width:260px">
              <i class="bi bi-search"></i>
              <input type="text" name="q" value="<?= im_f($search) ?>" placeholder="Nom yoki barcode...">
            </div>
            <select name="kat" class="im-select" style="max-width:170px">
              <option value="">Barcha kategoriya</option>
              <?php foreach ($kategoriyalar as $k): ?>
              <option value="<?= $k['id'] ?>" <?= $kat_filter == $k['id'] ? 'selected' : '' ?>>
                <?= im_f($k['nomi']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <button class="im-btn im-btn-dark im-btn-sm" type="submit">
              <i class="bi bi-funnel-fill"></i> Filtr
            </button>
            <?php if ($search || $kat_filter || $mah_id_filter): ?>
            <a href="<?= im_BASE ?>sklad/barcode-print.php" class="im-btn im-btn-ghost im-btn-sm">
              <i class="bi bi-x-circle"></i> Tozalash
            </a>
            <?php endif; ?>
            <div class="ms-auto d-flex gap-2">
              <button type="button" class="im-btn im-btn-outline im-btn-sm" id="btn-all">
                <i class="bi bi-check2-all"></i> Hammasini belgi
              </button>
              <button type="button" class="im-btn im-btn-ghost im-btn-sm" id="btn-none">
                <i class="bi bi-x"></i> Tozalash
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-3">
        <!-- Mahsulotlar -->
        <div class="col-lg-8">
          <?php if ($mahsulotlar): ?>
          <div class="row g-2" id="bc-grid">
            <?php foreach ($mahsulotlar as $m): ?>
            <div class="col-6 col-md-4 col-lg-3">
              <div class="bc-card <?= $mah_id_filter == $m['id'] ? 'selected' : '' ?>"
                   data-id="<?= $m['id'] ?>"
                   data-nomi="<?= im_f($m['nomi']) ?>"
                   data-barcode="<?= im_f($m['barcode'] ?: '') ?>"
                   onclick="toggleCard(this)">
                <div class="bc-body">
                  <span class="bc-check"><i class="bi bi-check-lg"></i></span>
                  <div class="bc-name"><?= im_f(mb_strimwidth($m['nomi'], 0, 38, '…', 'UTF-8')) ?></div>
                  <div class="bc-code"><?= im_f($m['barcode'] ?: '—') ?></div>
                </div>
                <div class="bc-foot">
                  <span class="text-muted fs-xs">Soni:</span>
                  <input type="number" class="qty-input" value="1" min="1" max="500"
                         data-mah-id="<?= $m['id'] ?>"
                         onclick="event.stopPropagation()"
                         onchange="updateQty(this)">
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="im-card">
            <div class="im-empty">
              <i class="bi bi-upc"></i>
              <h4>Mahsulotlar topilmadi</h4>
              <p>Filtrni o'zgartirib ko'ring</p>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Preview panel -->
        <div class="col-lg-4">
          <div class="im-card im-slide-in" style="position:sticky;top:80px">
            <div class="im-card-header">
              <i class="bi bi-eye-fill" style="color:var(--info)"></i>
              <span class="im-card-title">Ko'rinish (58×40mm)</span>
              <span class="im-badge im-badge-muted" id="preview-count">0 ta tanlangan</span>
            </div>
            <div class="im-card-body" style="min-height:180px">
              <div id="preview-box" class="text-center text-muted" style="padding:28px 0">
                <i class="bi bi-upc" style="font-size:38px;opacity:.3"></i>
                <p class="mt-2 fs-sm">Mahsulot tanlang</p>
              </div>
            </div>
            <div class="im-card-footer d-flex flex-column gap-2">
              <button class="im-btn im-btn-primary w-100" id="btn-print-2" disabled>
                <i class="bi bi-printer-fill"></i> Chop etish
              </button>
              <button class="im-btn im-btn-outline w-100" id="btn-assign-bc" type="button">
                <i class="bi bi-upc-scan"></i> Barcode berish (NH10-xxx)
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Hidden print zone (alternative approach) -->
<div id="print-zone" style="display:none"></div>
<div id="im-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>window.im_BASE = "<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js?v=<?= rawurlencode(im_VERSION) ?>"></script>
<script>
// ─── State ────────────────────────────────────────────────────
const selected   = new Map(); // id → { nomi, barcode, qty }
const DUKON_NOMI = <?= json_encode($dukon_nomi) ?>;

// ─── Barcode format ────────────────────────────────────────────
function bcFormat(code) {
  if (/^\d{13}$/.test(code)) return 'EAN13';
  if (/^\d{12}$/.test(code)) return 'UPC';
  if (/^\d{8}$/.test(code))  return 'EAN8';
  return 'CODE128';
}

// ─── Karta toggle ──────────────────────────────────────────────
function toggleCard(card) {
  const id      = card.dataset.id;
  const nomi    = card.dataset.nomi;
  const barcode = card.dataset.barcode;
  const qty     = parseInt(card.querySelector('.qty-input')?.value) || 1;

  if (selected.has(id)) {
    selected.delete(id);
    card.classList.remove('selected');
  } else {
    selected.set(id, { nomi, barcode, qty });
    card.classList.add('selected');
  }
  updateUI();
}

function updateQty(input) {
  const id = input.dataset.mahId;
  if (selected.has(id)) {
    const item = selected.get(id);
    item.qty = parseInt(input.value) || 1;
    updateUI();
  }
}

// ─── Hammasini belgilash ───────────────────────────────────────
document.getElementById('btn-all').addEventListener('click', () => {
  document.querySelectorAll('.bc-card').forEach(card => {
    const id = card.dataset.id;
    if (!selected.has(id)) {
      selected.set(id, {
        nomi:    card.dataset.nomi,
        barcode: card.dataset.barcode,
        qty:     parseInt(card.querySelector('.qty-input')?.value) || 1
      });
      card.classList.add('selected');
    }
  });
  updateUI();
});

document.getElementById('btn-none').addEventListener('click', () => {
  selected.clear();
  document.querySelectorAll('.bc-card').forEach(c => c.classList.remove('selected'));
  updateUI();
});

// ─── UI yangilash ──────────────────────────────────────────────
function updateUI() {
  const count    = selected.size;
  const totalQty = [...selected.values()].reduce((s, i) => s + i.qty, 0);

  document.getElementById('sel-count').textContent     = count > 0 ? `(${totalQty} ta)` : '';
  document.getElementById('preview-count').textContent = `${count} ta tanlangan`;
  document.getElementById('btn-print').disabled        = count === 0;
  document.getElementById('btn-print-2').disabled      = count === 0;

  const box = document.getElementById('preview-box');

  if (count === 0) {
    box.innerHTML = '<i class="bi bi-upc" style="font-size:38px;opacity:.3"></i><p class="mt-2 fs-sm text-muted">Mahsulot tanlang</p>';
    return;
  }

  const first = [...selected.values()][0];

  box.innerHTML = `
    <div class="pv-wrap">
      <div class="pv-sticker">
        <div class="pv-shop">${im_esc(DUKON_NOMI)}</div>
        <div class="pv-bc"><svg id="pv-svg"></svg></div>
        <div class="pv-code">${im_esc(first.barcode) || '—'}</div>
        <div class="pv-name">${im_esc(first.nomi)}</div>
      </div>
    </div>
    <div class="text-muted fs-xs text-center">Jami: ${totalQty} ta label · 58×40mm</div>`;

  if (first.barcode) {
    try {
      JsBarcode('#pv-svg', first.barcode, {
        format: bcFormat(first.barcode),
        width: 1.6, height: 35,
        displayValue: false, margin: 1, background: '#fff'
      });
    } catch(e) {}
  }
}

// ─── Chop etish ────────────────────────────────────────────────
function doPrint() {
  if (selected.size === 0) { NHToast.warning("Hech narsa tanlanmagan"); return; }

  const labels = [];
  selected.forEach(item => {
    for (let i = 0; i < item.qty; i++) labels.push({ ...item });
  });

  const labelsHtml = labels.map((item, idx) => `
    <div class="lbl">
      <div class="l-shop">${im_esc(DUKON_NOMI)}</div>
      ${item.barcode
        ? `<div class="l-bc"><svg id="bc${idx}"></svg></div>
           <div class="l-code">${im_esc(item.barcode)}</div>`
        : `<div class="l-code" style="color:#c00;font-size:7pt;padding:4px 0;">BARCODE YO'Q</div>`
      }
      <div class="l-name">${im_esc(item.nomi)}</div>
    </div>`
  ).join('');

  const itemsJson = JSON.stringify(labels.map((it, idx) => ({
    id: idx,
    barcode: it.barcode || '',
    format:  bcFormat(it.barcode || '')
  })));

  const htmlParts = [
    '<!DOCTYPE html>',
    '<html><head>',
    '<meta charset="UTF-8"><title>Barcode Chop</title>',
    '<scr', 'ipt src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></scr', 'ipt>',
    '<sty', 'le>',
    '*{margin:0;padding:0;box-sizing:border-box;}',
    'html,body{background:#fff;font-family:Arial,sans-serif;-webkit-print-color-adjust:exact;color-adjust:exact;}',
    '@page{ size:58mm 40mm; margin:0; }',
    'body{ width:58mm; margin:0; padding:0; }',
    '.lbl{ width:58mm; height:36mm; padding:1mm 2mm; overflow:hidden; display:flex; flex-direction:column; align-items:center; justify-content:center; }',
    '.lbl + .lbl{ page-break-before:always; break-before:page; }',
    '.l-shop{ text-align:center; font-size:7.5pt; font-weight:900; border-bottom:0.6px solid #000; padding-bottom:0.6mm; margin-bottom:0.6mm; width:100%; white-space:nowrap; overflow:hidden; letter-spacing:0.3px; }',
    '.l-bc{ text-align:center; line-height:0; width:100%; }',
    '.l-bc svg{ width:52mm; height:auto; display:block; margin:0 auto; }',
    '.l-code{ text-align:center; font-size:6pt; font-family:monospace; font-weight:700; margin-top:0.6mm; letter-spacing:0.4px; }',
    '.l-name{ text-align:center; font-size:7pt; font-weight:700; margin-top:0.6mm; word-break:break-word; line-height:1.2; width:100%; overflow:hidden; max-height:7mm; }',
    '</sty', 'le></head><body>',
    labelsHtml,
    '<scr', 'ipt>',
    'const items=' + itemsJson + ';',
    'items.forEach(it=>{',
    '  if(!it.barcode)return;',
    '  try{',
    '    JsBarcode("#bc"+it.id, it.barcode, {',
    '      format: it.format,',
    '      width: 1.4,',
    '      height: 20,',
    '      displayValue: false,',
    '      margin: 0,',
    '      background: "#fff",',
    '      lineColor: "#000"',
    '    });',
    '  }catch(e){}',
    '});',
    'setTimeout(()=>{ window.print(); window.close(); }, 700);',
    '</scr', 'ipt></body></html>'
  ];
  
  const html = htmlParts.join('');

  const win = window.open('', '_blank', 'width=800,height=600');
  if (!win) { NHToast.error("Popup bloklangan! Brauzer ruxsat bering."); return; }
  win.document.write(html);
  win.document.close();
  NHToast.success(`${labels.length} ta label yuborildi`);
}

document.getElementById('btn-print').addEventListener('click', doPrint);
document.getElementById('btn-print-2').addEventListener('click', doPrint);

// ─── NH barcode avtomatik berish ──────────────────────────────
document.getElementById('btn-assign-bc').addEventListener('click', async () => {
  const ok = await NHConfirm.show({
    variant: 'primary',
    label: 'Ommaviy amal',
    title: "Barcode yaratishni tasdiqlang",
    text: "Barcodesiz barcha mahsulotlarga avtomatik NH10-xxx kodi beriladi.",
    sub: "Mavjud barcodelar o'zgartirilmaydi.",
    confirmText: "Barcode berish",
    btnIcon: 'bi-upc-scan',
    icon: 'bi-upc-scan'
  });
  if (!ok) return;
  const BASE = (window.im_BASE || '/').replace(/\/$/, '');
  fetch(BASE + '/sklad/ajax/barcode-assign.php', { method: 'POST' })
    .then(r => r.json())
    .then(d => {
      if (d.status === 'ok') {
        NHToast.success(`${d.count} ta mahsulotga barcode berildi. Yangilanmoqda...`);
        setTimeout(() => location.reload(), 1500);
      } else {
        NHToast.error(d.msg || 'Xatolik');
      }
    })
    .catch(() => NHToast.error('Server bilan ulanishda xatolik'));
});

// ─── URL dan bitta mahsulot kelsa avtomatik tanlash ───────────
<?php if ($mah_id_filter && $mahsulotlar): ?>
window.addEventListener('load', () => {
  const card = document.querySelector('.bc-card[data-id="<?= $mah_id_filter ?>"]');
  if (card) {
    const m = <?= json_encode(reset($mahsulotlar)) ?>;
    selected.set('<?= $mah_id_filter ?>', {
      nomi: m.nomi, barcode: m.barcode, qty: 1
    });
    card.classList.add('selected');
    updateUI();
  }
});
<?php endif; ?>
</script>
</body>
</html>
