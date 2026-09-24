<?php
// ============================================================
//  IMezon — INVENTARIZATSIYA (sanoq)
//  Tizim qoldig'i (FIFO qatlamlari) ↔ javondagi real miqdor.
//  Farq FAQAT FIFO orqali yoziladi — sklad/ajax/inventarizatsiya-save.php
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../fifo_reports.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();
$page_title = 'Inventarizatsiya';

$filiallar = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY id");

// Joy: -1 kelmasa ombor (0)
$joy = isset($_GET['joy']) ? (int)$_GET['joy'] : 0;
if ($joy > 0 && !$db->val("SELECT id FROM im_filiallar WHERE id=$joy AND status=1")) $joy = 0;
$joy_nomi = $joy === 0 ? 'Ombor'
          : ((string)$db->val("SELECT nomi FROM im_filiallar WHERE id=$joy") ?: "Filial #$joy");

// Sanaladigan mahsulotlar: shu joyda qatlami borlar + qoldiqsiz faol mahsulotlar
// (ortiqcha topilsa ham yozish mumkin bo'lsin).
$unit_cost_sql = im_fifo_report_unit_cost_sql((int)$joy, 'm.id');
$mahsulotlar = $db->rows(
    "SELECT m.id, m.nomi, m.barcode, m.birlik, k.nomi AS kat_nomi,
            COALESCE((SELECT SUM(fl.remaining_qty) FROM im_fifo_layers fl
                      WHERE fl.mahsulot_id=m.id AND fl.location_id=$joy
                        AND fl.cancelled=0 AND fl.remaining_qty>0), 0) AS hisob_soni,
            $unit_cost_sql AS birlik_narx
     FROM im_mahsulotlar m
     LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
     WHERE m.status=1
     ORDER BY (COALESCE((SELECT SUM(fl2.remaining_qty) FROM im_fifo_layers fl2
                         WHERE fl2.mahsulot_id=m.id AND fl2.location_id=$joy
                           AND fl2.cancelled=0 AND fl2.remaining_qty>0),0) > 0) DESC,
              k.nomi ASC, m.nomi ASC"
);

$jami_qiymat = 0.0;
foreach ($mahsulotlar as $m) $jami_qiymat += (float)$m['hisob_soni'] * (float)$m['birlik_narx'];

// Oxirgi sanoqlar
$tarix = $db->rows(
    "SELECT i.*, x.ism AS xodim_ism,
            (SELECT COUNT(*) FROM im_inventarizatsiya_items ii WHERE ii.inv_id=i.id) AS qatorlar,
            (SELECT COUNT(*) FROM im_inventarizatsiya_items ii WHERE ii.inv_id=i.id AND ii.farq<>0) AS farqlar
     FROM im_inventarizatsiya i
     LEFT JOIN im_xodimlar x ON x.id=i.xodim_id
     ORDER BY i.id DESC LIMIT 15"
);
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
    .inv-real{width:120px;text-align:right;font-weight:700}
    .inv-row.farq-plus  td{background:rgba(16,185,129,.07)}
    .inv-row.farq-minus td{background:rgba(239,68,68,.07)}
    .inv-farq{font-weight:800}
    .inv-sticky{position:sticky;bottom:0;z-index:5;background:var(--card);
                border-top:2px solid var(--border);padding:12px 16px;
                display:flex;align-items:center;gap:14px;flex-wrap:wrap}
  </style>
</head>
<body>
<div class="im-wrapper">

  <?php require_once __DIR__ . '/navbar.php'; ?>

  <div class="im-main">

    <header class="im-topbar">
      <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
      <div class="im-page-title"><i class="bi bi-clipboard-check-fill me-1"></i> Inventarizatsiya</div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle title="Rejim"><i class="bi bi-moon-fill"></i></button>
      </div>
    </header>

    <main class="im-content">

      <div class="im-breadcrumb">
        <a href="<?= im_BASE ?>sklad/index.php"><i class="bi bi-house-fill"></i></a>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Inventarizatsiya</span>
      </div>

      <div class="im-card mb-3">
        <div class="im-card-header">
          <i class="bi bi-geo-alt-fill" style="color:var(--primary)"></i>
          <span class="im-card-title">Sanoq joyi</span>
          <span class="ms-auto text-muted fs-xs">
            Tizim qiymati: <b class="num"><?= im_money($jami_qiymat) ?></b> so'm
          </span>
        </div>
        <div class="im-card-body">
          <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
            <select name="joy" class="im-input" style="max-width:280px" onchange="this.form.submit()">
              <option value="0" <?= $joy === 0 ? 'selected' : '' ?>>🏬 Ombor (markaz)</option>
              <?php foreach ($filiallar as $f): ?>
                <option value="<?= (int)$f['id'] ?>" <?= $joy === (int)$f['id'] ? 'selected' : '' ?>>
                  🏪 <?= im_f($f['nomi']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="search" id="inv-search" class="im-input" style="max-width:280px"
                   placeholder="Mahsulot yoki barcode bo'yicha qidirish...">
            <label class="d-flex align-items-center gap-2 text-muted fs-sm ms-auto">
              <input type="checkbox" id="only-stock" checked> Faqat qoldig'i borlar
            </label>
          </form>
          <div class="text-muted fs-xs mt-2">
            <i class="bi bi-info-circle"></i>
            "Real" ustuniga javonda SANALGAN miqdorni yozing. Bo'sh qoldirilgan qatorlar
            sanoqqa umuman kirmaydi. Farq FIFO qatlamlari orqali yoziladi:
            ortiqcha — joriy o'rtacha tannarxda yangi qatlam, kamomad — eng eski qatlamdan yechiladi.
          </div>
        </div>
      </div>

      <div class="im-card">
        <div class="im-card-header">
          <i class="bi bi-list-check" style="color:var(--info)"></i>
          <span class="im-card-title"><?= im_f($joy_nomi) ?> — sanoq varaqasi</span>
          <span class="im-badge im-badge-muted ms-2" id="inv-count">0 qator to'ldirildi</span>
        </div>
        <div class="im-table-wrap">
          <table class="im-table" id="inv-table">
            <thead><tr>
              <th>#</th><th>Mahsulot</th><th>Kategoriya</th>
              <th class="text-right">Tizimda</th>
              <th class="text-right">Real (sanoq)</th>
              <th class="text-right">Farq</th>
              <th class="text-right">Summa</th>
            </tr></thead>
            <tbody>
            <?php foreach ($mahsulotlar as $i => $m):
              $qoldiq = (float)$m['hisob_soni'];
              $narx   = (float)$m['birlik_narx']; ?>
              <tr class="inv-row" data-id="<?= (int)$m['id'] ?>" data-hisob="<?= $qoldiq ?>"
                  data-narx="<?= $narx ?>" data-stock="<?= $qoldiq > 0 ? 1 : 0 ?>"
                  data-search="<?= im_f(mb_strtolower($m['nomi'] . ' ' . ($m['barcode'] ?? ''))) ?>">
                <td class="text-muted fs-xs"><?= $i + 1 ?></td>
                <td>
                  <div class="fw-semibold"><?= im_f($m['nomi']) ?></div>
                  <?php if ($m['barcode']): ?>
                    <div class="text-muted fs-xs"><?= im_f($m['barcode']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-muted fs-xs"><?= im_f($m['kat_nomi'] ?? '—') ?></td>
                <td class="text-right num fw-bold"><?= rtrim(rtrim(number_format($qoldiq, 3, '.', ' '), '0'), '.') ?>
                    <span class="text-muted fs-xs"><?= im_f($m['birlik']) ?></span></td>
                <td class="text-right">
                  <input type="number" step="0.001" min="0" class="im-input inv-real"
                         placeholder="—" aria-label="Real miqdor">
                </td>
                <td class="text-right num inv-farq">—</td>
                <td class="text-right num inv-summa">—</td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="inv-sticky">
          <div>
            <div class="text-muted fs-xs">Ortiqcha</div>
            <div class="fw-bold num" style="color:var(--success)" id="sum-plus">0 so'm</div>
          </div>
          <div>
            <div class="text-muted fs-xs">Kamomad</div>
            <div class="fw-bold num" style="color:var(--danger)" id="sum-minus">0 so'm</div>
          </div>
          <input type="text" id="inv-izoh" class="im-input" style="max-width:320px"
                 placeholder="Izoh (masalan: oylik sanoq)">
          <button class="im-btn im-btn-primary ms-auto" id="btn-saqlash" disabled>
            <i class="bi bi-check2-circle"></i> Sanoqni qo'llash
          </button>
        </div>
      </div>

      <?php if ($tarix): ?>
      <div class="im-card mt-3">
        <div class="im-card-header">
          <i class="bi bi-clock-history" style="color:var(--muted)"></i>
          <span class="im-card-title">Oxirgi sanoqlar</span>
        </div>
        <div class="im-table-wrap">
          <table class="im-table">
            <thead><tr>
              <th>#</th><th>Sana</th><th>Joy</th><th>Xodim</th>
              <th class="text-right">Qator</th><th class="text-right">Farqli</th>
              <th class="text-right">Ortiqcha</th><th class="text-right">Kamomad</th><th>Izoh</th>
            </tr></thead>
            <tbody>
            <?php foreach ($tarix as $t):
              $tj = (int)$t['location_id'] === 0 ? 'Ombor'
                  : ((string)$db->val("SELECT nomi FROM im_filiallar WHERE id=" . (int)$t['location_id']) ?: '—'); ?>
              <tr>
                <td class="text-muted fs-xs"><?= (int)$t['id'] ?></td>
                <td class="fs-sm"><?= im_f(substr((string)$t['sana'], 0, 16)) ?></td>
                <td class="fs-sm"><?= im_f($tj) ?></td>
                <td class="fs-sm"><?= im_f($t['xodim_ism'] ?? '—') ?></td>
                <td class="text-right num"><?= (int)$t['qatorlar'] ?></td>
                <td class="text-right num fw-bold"><?= (int)$t['farqlar'] ?></td>
                <td class="text-right num" style="color:var(--success)"><?= im_money($t['ortiqcha_summa']) ?></td>
                <td class="text-right num" style="color:var(--danger)"><?= im_money($t['kamomad_summa']) ?></td>
                <td class="text-muted fs-xs"><?= im_f($t['izoh'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
'use strict';
const JOY  = <?= (int)$joy ?>;
const SAVE = (window.im_BASE || '/') + 'sklad/ajax/inventarizatsiya-save.php';
const fmt  = n => Number(n || 0).toLocaleString('uz-UZ');
const rows = Array.from(document.querySelectorAll('.inv-row'));

function qatorHisobla(tr) {
  const inp   = tr.querySelector('.inv-real');
  const hisob = Number(tr.dataset.hisob) || 0;
  const narx  = Number(tr.dataset.narx) || 0;
  const cF = tr.querySelector('.inv-farq'), cS = tr.querySelector('.inv-summa');
  tr.classList.remove('farq-plus', 'farq-minus');
  if (inp.value === '') { cF.textContent = '—'; cS.textContent = '—'; return null; }
  const real = Number(inp.value);
  if (!isFinite(real) || real < 0) { cF.textContent = '?'; cS.textContent = '—'; return null; }
  const farq  = Math.round((real - hisob) * 1000) / 1000;
  const summa = Math.round(farq * narx);
  cF.textContent = (farq > 0 ? '+' : '') + farq;
  cS.textContent = farq === 0 ? '—' : (summa > 0 ? '+' : '') + fmt(summa);
  if (farq > 0) tr.classList.add('farq-plus');
  if (farq < 0) tr.classList.add('farq-minus');
  return { mahsulot_id: Number(tr.dataset.id), real_soni: real, summa: summa };
}

function yangila() {
  let plus = 0, minus = 0, n = 0;
  rows.forEach(tr => {
    const r = qatorHisobla(tr);
    if (!r) return;
    n++;
    if (r.summa > 0) plus += r.summa;
    if (r.summa < 0) minus += -r.summa;
  });
  document.getElementById('sum-plus').textContent  = fmt(plus) + " so'm";
  document.getElementById('sum-minus').textContent = fmt(minus) + " so'm";
  document.getElementById('inv-count').textContent = n + " qator to'ldirildi";
  document.getElementById('btn-saqlash').disabled  = n === 0;
}

rows.forEach(tr => tr.querySelector('.inv-real').addEventListener('input', yangila));

// Filtrlar
function filtrla() {
  const q = (document.getElementById('inv-search').value || '').trim().toLowerCase();
  const onlyStock = document.getElementById('only-stock').checked;
  rows.forEach(tr => {
    const mos = !q || (tr.dataset.search || '').includes(q);
    const bor = !onlyStock || tr.dataset.stock === '1' || tr.querySelector('.inv-real').value !== '';
    tr.hidden = !(mos && bor);
  });
}
document.getElementById('inv-search').addEventListener('input', filtrla);
document.getElementById('only-stock').addEventListener('change', filtrla);
filtrla();

document.getElementById('btn-saqlash').addEventListener('click', async () => {
  const items = [];
  rows.forEach(tr => { const r = qatorHisobla(tr); if (r) items.push({ mahsulot_id: r.mahsulot_id, real_soni: r.real_soni }); });
  if (!items.length) return;

  const farqli = rows.filter(tr => tr.classList.contains('farq-plus') || tr.classList.contains('farq-minus')).length;
  const ok = await NHConfirm.show({
    variant: 'warning',
    title: 'Sanoqni qo‘llash',
    text: items.length + ' ta qator yuboriladi, ' + farqli + ' tasida farq bor.',
    sub: 'Farqlar qoldiqqa DARHOL yoziladi (FIFO qatlamlari orqali) va orqaga qaytarilmaydi. '
       + 'Ekrandagi farq sahifa ochilgandagi qoldiqqa asoslangan — server saqlash payti '
       + 'joriy qoldiqni qayta hisoblaydi, shuning uchun yakuniy summa biroz farq qilishi mumkin.',
    confirmText: 'Ha, qo‘llansin',
    btnIcon: 'bi-check2-circle'
  });
  if (!ok) return;

  const btn = document.getElementById('btn-saqlash');
  btn.disabled = true;
  const res = await IMAjax.post(SAVE, {
    location_id: JOY,
    izoh: document.getElementById('inv-izoh').value || '',
    items: JSON.stringify(items)
  });
  if (res.status === 'ok') {
    NHToast.show(res.msg, 'success', 6000);
    setTimeout(() => location.reload(), 1200);
  } else {
    NHToast.show(res.msg || 'Xatolik', 'error', 8000);
    btn.disabled = false;
  }
});
</script>
</body>
</html>
