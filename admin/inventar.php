<?php
// ============================================================
//  IMezon — Admin: Barcha Filiallar + Sklad Inventar
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../fifo_reports.php';
im_rol_check(['admin']);
$db = new Cyber();
$fifo_stock = im_fifo_report_stock_sql();

// Filter
$q        = trim($_GET['q'] ?? '');
$kat_id   = (int)($_GET['kat'] ?? 0);
$filtr_fl = (int)($_GET['filial'] ?? 0); // 0=hammasi, -1=sklad, N=filial_id

// Filiallar
$filiallar = $db->rows("SELECT * FROM im_filiallar WHERE status=1 ORDER BY id");
// Kategoriyalar
$kategoriyalar = $db->rows("SELECT * FROM im_kategoriyalar ORDER BY nomi");

// ── Mahsulot filter ────────────────────────────────────────
$mah_where = "m.status=1";
if ($q) {
    $qs = mysqli_real_escape_string($link, $q);
    $mah_where .= " AND (m.nomi LIKE '%$qs%' OR m.barcode LIKE '%$qs%')";
}
if ($kat_id) $mah_where .= " AND m.kategoriya_id=$kat_id";

// ── SKLAD qoldiqlari (im_fifo_layers, location_id=0) ────────
$sklad_rows = [];
if (!$filtr_fl || $filtr_fl == -1) {
    $sklad_rows = $db->rows(
        "SELECT m.id AS mahsulot_id,
                m.nomi, m.barcode, m.birlik,
                k.nomi AS kat_nomi,
                COALESCE(SUM(pi.remaining_qty*pi.unit_cost)/NULLIF(SUM(pi.remaining_qty),0),0) AS kelish_narxi,
                COALESCE(n.sotish_narxi,0)       AS sotuv_narxi,
                COALESCE(SUM(pi.remaining_qty),0)  AS soni
         FROM im_mahsulotlar m
         JOIN im_fifo_layers pi ON pi.mahsulot_id=m.id AND pi.location_id=0 AND pi.cancelled=0 AND pi.remaining_qty>0
         LEFT JOIN im_kategoriyalar k ON k.id = m.kategoriya_id
         LEFT JOIN im_narxlar n ON n.mahsulot_id = m.id
         WHERE $mah_where
         GROUP BY m.id
         ORDER BY m.nomi ASC"
    );
}

// ── FILIAL qoldiqlari (har bir filial uchun) ──────────────
$filial_data = [];
foreach ($filiallar as $fl) {
    if ($filtr_fl && $filtr_fl != $fl['id']) continue;
    $fid = (int)$fl['id'];
    $rows = $db->rows(
        "SELECT fq.mahsulot_id, fq.soni, prices.sotuv_narxi, fq.kelish_narxi,
                m.nomi, m.barcode, m.birlik,
                k.nomi AS kat_nomi,
                COALESCE(prices.sotuv_narxi, n.sotish_narxi, 0) AS sotuv_n
         FROM ($fifo_stock) fq
         LEFT JOIN im_filial_qoldiq prices ON prices.filial_id=fq.filial_id AND prices.mahsulot_id=fq.mahsulot_id
         JOIN im_mahsulotlar m ON m.id = fq.mahsulot_id AND $mah_where
         LEFT JOIN im_kategoriyalar k ON k.id = m.kategoriya_id
         LEFT JOIN im_narxlar n ON n.mahsulot_id = m.id
         WHERE fq.filial_id = $fid AND fq.soni > 0
         ORDER BY m.nomi ASC"
    );
    $filial_data[$fid] = [
        'nomi' => $fl['nomi'],
        'rows' => $rows,
        'jami_qiymat' => array_sum(array_column($rows, 'soni') && count($rows)
            ? array_map(fn($r) => $r['soni'] * $r['sotuv_n'], $rows) : []),
        'jami_soni' => array_sum(array_column($rows, 'soni')),
    ];
}

// ── Umumiy mahsulot jami (barcha filiallar) ───────────────
$filial_cols = "";
$filial_sums = "";
if (count($filiallar) > 0) {
    $filial_cols = implode(', ', array_map(fn($fl) =>
        "COALESCE((SELECT fq.soni FROM ($fifo_stock) fq WHERE fq.mahsulot_id=m.id AND fq.filial_id={$fl['id']}),0) AS f{$fl['id']}_soni",
        $filiallar)) . ", ";
    $filial_sums = " + " . implode(' + ', array_map(fn($fl) =>
        "COALESCE((SELECT fq.soni FROM ($fifo_stock) fq WHERE fq.mahsulot_id=m.id AND fq.filial_id={$fl['id']}),0)",
        $filiallar));
}

$global = $db->rows(
    "SELECT m.nomi, m.barcode, m.birlik, k.nomi AS kat_nomi,
            COALESCE(n.sotish_narxi,0) AS sotuv_narxi,
            COALESCE((SELECT SUM(fl.remaining_qty*fl.unit_cost)/NULLIF(SUM(fl.remaining_qty),0) FROM im_fifo_layers fl WHERE fl.mahsulot_id=m.id AND fl.cancelled=0 AND fl.remaining_qty>0 AND (fl.location_id=0 OR fl.location_id IN (SELECT id FROM im_filiallar WHERE status=1))), 0) AS kelish_narxi,
            COALESCE((SELECT SUM(pi.remaining_qty) FROM im_fifo_layers pi WHERE pi.mahsulot_id=m.id AND pi.location_id=0 AND pi.cancelled=0 AND pi.remaining_qty>0),0) AS sklad_soni,
            {$filial_cols}
            COALESCE((SELECT SUM(pi.remaining_qty) FROM im_fifo_layers pi WHERE pi.mahsulot_id=m.id AND pi.location_id=0 AND pi.cancelled=0 AND pi.remaining_qty>0),0)
            {$filial_sums} AS umumiy_soni
     FROM im_mahsulotlar m
     LEFT JOIN im_kategoriyalar k ON k.id = m.kategoriya_id
     LEFT JOIN im_narxlar n ON n.mahsulot_id = m.id
     WHERE $mah_where
     HAVING umumiy_soni > 0
     ORDER BY m.nomi ASC"
);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Inventar | IMezon Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
<style>
.inv-tab { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px; }
.inv-tab a { padding:8px 16px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;
             border:1.5px solid var(--border);color:var(--text);transition:.15s; }
.inv-tab a.active,
.inv-tab a:hover { background:var(--primary);color:#fff;border-color:var(--primary); }
.soni-chip { display:inline-block;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:700; }
</style>
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title"><i class="bi bi-boxes me-1"></i> Inventar Nazorati</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- Filter + Tablar -->
    <div class="im-card mb-3">
      <div class="im-card-body p-3">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
          <input type="text" name="q" class="im-input im-input-sm" style="max-width:200px"
                 placeholder="Mahsulot..." value="<?= im_f($q) ?>">
          <select name="kat" class="im-input im-input-sm" style="max-width:180px">
            <option value="0">Barcha kategoriyalar</option>
            <?php foreach ($kategoriyalar as $k): ?>
            <option value="<?= $k['id'] ?>" <?= $kat_id==$k['id']?'selected':'' ?>><?= im_f($k['nomi']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="im-btn im-btn-primary im-btn-sm"><i class="bi bi-search"></i> Filter</button>
          <?php if ($q || $kat_id): ?>
          <a href="?" class="im-btn im-btn-outline im-btn-sm"><i class="bi bi-x-lg"></i></a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Filial tablar -->
    <div class="inv-tab">
      <a href="?q=<?= urlencode($q) ?>&kat=<?= $kat_id ?>&filial=0" class="<?= !$filtr_fl ? 'active' : '' ?>">
        <i class="bi bi-layers"></i> Jami ko'rinish
      </a>
      <a href="?q=<?= urlencode($q) ?>&kat=<?= $kat_id ?>&filial=-1" class="<?= $filtr_fl==-1 ? 'active' : '' ?>">
        <i class="bi bi-building"></i> Sklad
      </a>
      <?php foreach ($filiallar as $fl): ?>
      <a href="?q=<?= urlencode($q) ?>&kat=<?= $kat_id ?>&filial=<?= $fl['id'] ?>"
         class="<?= $filtr_fl==$fl['id'] ? 'active' : '' ?>">
        <i class="bi bi-shop"></i> <?= im_f($fl['nomi']) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (!$filtr_fl): ?>
    <!-- ══════════════════════════════════════════════════════
         JAMI KO'RINISH — Barcha joylarda mahsulot
    ══════════════════════════════════════════════════════════ -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-layers-fill" style="color:var(--primary)"></i>
        <span class="im-card-title">Barcha joylardagi qoldiq</span>
        <span class="im-badge im-badge-muted"><?= count($global) ?> xil mahsulot</span>
        <span class="ms-auto num fw-bold" style="color:var(--success)">
          Jami (Tan narxda): <?= im_money(array_sum(array_map(fn($r) => $r['umumiy_soni'] * $r['kelish_narxi'], $global))) ?> so'm
        </span>
      </div>
      <?php if ($global): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Mahsulot</th>
              <th>Kategoriya</th>
              <th class="text-center" style="background:rgba(100,100,200,.08)">🏭 Sklad</th>
              <?php foreach ($filiallar as $fl): ?>
              <th class="text-center" style="background:rgba(40,167,69,.06)">🏪 <?= im_f($fl['nomi']) ?></th>
              <?php endforeach; ?>
              <th class="text-right" style="background:rgba(79,70,229,.08)">Jami Qoldiq</th>
              <th class="text-right">Tan narxi</th>
              <th class="text-right">Sotuv narxi</th>
              <th class="text-right">Umumiy Qiymat</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($global as $i => $m):
            $umumiy = (float)$m['umumiy_soni'];
            $low = $umumiy <= 10;
          ?>
          <tr <?= $low ? 'style="background:rgba(239,68,68,.03)"' : '' ?>>
            <td class="text-muted fs-xs"><?= $i+1 ?></td>
            <td>
              <div class="fw-semibold"><?= im_f($m['nomi']) ?></div>
              <?php if ($m['barcode']): ?><div class="text-muted fs-xs"><?= im_f($m['barcode']) ?></div><?php endif; ?>
            </td>
            <td class="text-muted fs-xs"><?= im_f($m['kat_nomi'] ?? '—') ?></td>
            <!-- Sklad -->
            <td class="text-center">
              <?php $sk = (float)$m['sklad_soni']; ?>
              <?php if ($sk > 0): ?>
              <span class="soni-chip" style="background:rgba(100,100,200,.12);color:#5c5ccc"><?= number_format($sk) ?></span>
              <?php else: ?>
              <span class="text-muted fs-xs">—</span>
              <?php endif; ?>
            </td>
            <!-- Filiallar -->
            <?php foreach ($filiallar as $fl): ?>
            <td class="text-center">
              <?php $fn = (float)$m['f'.$fl['id'].'_soni']; ?>
              <?php if ($fn > 0): ?>
              <span class="soni-chip" style="background:rgba(40,167,69,.1);color:var(--success)"><?= number_format($fn) ?></span>
              <?php else: ?>
              <span class="text-muted fs-xs">—</span>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td class="text-right fw-bold">
              <span class="<?= $low ? 'text-danger' : '' ?>">
                <?= number_format($umumiy) ?> <?= im_f($m['birlik']) ?>
                <?php if ($low): ?><i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Kam!"></i><?php endif; ?>
              </span>
            </td>
            <td class="text-right num text-muted">
              <?= (float)$m['kelish_narxi'] > 0 ? im_money($m['kelish_narxi']).' so\'m' : '—' ?>
            </td>
            <td class="text-right num" style="color:var(--success)">
              <?= (float)$m['sotuv_narxi'] > 0 ? im_money($m['sotuv_narxi']).' so\'m' : '—' ?>
            </td>
            <td class="text-right num fw-bold">
              <?= im_money($umumiy * $m['kelish_narxi']) ?> so'm
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-boxes"></i><h4>Qoldiq topilmadi</h4></div>
      <?php endif; ?>
    </div>

    <?php elseif ($filtr_fl == -1): ?>
    <!-- ══════════════════════════════════════════════════════
         SKLAD qoldiqlari
    ══════════════════════════════════════════════════════════ -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-building" style="color:var(--primary)"></i>
        <span class="im-card-title">Sklad qoldiqlari</span>
        <span class="im-badge im-badge-muted"><?= count($sklad_rows) ?> xil</span>
        <span class="ms-auto num fw-bold" style="color:var(--success)">
          Jami (Tan narxda): <?= im_money(array_sum(array_map(fn($r) => $r['soni'] * $r['kelish_narxi'], $sklad_rows))) ?> so'm
        </span>
      </div>
      <?php if ($sklad_rows): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr>
            <th>#</th><th>Mahsulot</th><th>Kategoriya</th>
            <th class="text-right">Qoldiq</th>
            <th class="text-right">Kelish narxi</th>
            <th class="text-right">Sotuv narxi</th>
            <th class="text-right">Qiymat</th>
          </tr></thead>
          <tbody>
          <?php foreach ($sklad_rows as $i => $r): ?>
          <tr>
            <td class="text-muted fs-xs"><?= $i+1 ?></td>
            <td>
              <div class="fw-semibold"><?= im_f($r['nomi']) ?></div>
              <?php if ($r['barcode']): ?><div class="text-muted fs-xs"><?= im_f($r['barcode']) ?></div><?php endif; ?>
            </td>
            <td class="text-muted fs-xs"><?= im_f($r['kat_nomi'] ?? '—') ?></td>
            <td class="text-right fw-bold"><?= number_format($r['soni']) ?> <?= im_f($r['birlik']) ?></td>
            <td class="text-right num text-muted"><?= (float)$r['kelish_narxi'] > 0 ? im_money($r['kelish_narxi']).' so\'m' : '—' ?></td>
            <td class="text-right num" style="color:var(--success)"><?= im_money($r['sotuv_narxi']) ?> so'm</td>
            <td class="text-right num fw-bold"><?= im_money($r['soni'] * $r['kelish_narxi']) ?> so'm</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-building"></i><h4>Sklad bo'sh</h4></div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ══════════════════════════════════════════════════════
         BITTA FILIAL qoldiqlari
    ══════════════════════════════════════════════════════════ -->
    <?php $fd = $filial_data[$filtr_fl] ?? ['nomi'=>'Filial','rows'=>[],'jami_soni'=>0,'jami_qiymat'=>0]; ?>
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-shop" style="color:var(--success)"></i>
        <span class="im-card-title"><?= im_f($fd['nomi']) ?> — Qoldiqlar</span>
        <span class="im-badge im-badge-muted"><?= count($fd['rows']) ?> xil</span>
        <span class="ms-auto num fw-bold" style="color:var(--success)">
          Jami (Tan narxda): <?= im_money(array_sum(array_map(fn($r) => $r['soni'] * $r['kelish_narxi'], $fd['rows']))) ?> so'm
        </span>
      </div>
      <?php if ($fd['rows']): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr>
            <th>#</th><th>Mahsulot</th><th>Kategoriya</th>
            <th class="text-right">Qoldiq</th>
            <th class="text-right">Tan narxi</th>
            <th class="text-right">Sotuv narxi</th>
            <th class="text-right">Qiymat (tan narxda)</th>
          </tr></thead>
          <tbody>
          <?php foreach ($fd['rows'] as $i => $r):
            $low = (float)$r['soni'] <= 5;
          ?>
          <tr <?= $low ? 'style="background:rgba(239,68,68,.04)"' : '' ?>>
            <td class="text-muted fs-xs"><?= $i+1 ?></td>
            <td>
              <div class="fw-semibold"><?= im_f($r['nomi']) ?></div>
              <?php if ($r['barcode']): ?><div class="text-muted fs-xs"><?= im_f($r['barcode']) ?></div><?php endif; ?>
            </td>
            <td class="text-muted fs-xs"><?= im_f($r['kat_nomi'] ?? '—') ?></td>
            <td class="text-right fw-bold <?= $low ? 'text-danger' : '' ?>">
              <?= number_format($r['soni']) ?> <?= im_f($r['birlik']) ?>
              <?php if ($low): ?><i class="bi bi-exclamation-triangle-fill text-danger ms-1"></i><?php endif; ?>
            </td>
            <td class="text-right num text-muted"><?= (float)$r['kelish_narxi'] > 0 ? im_money($r['kelish_narxi']).' so\'m' : '—' ?></td>
            <td class="text-right num" style="color:var(--success)"><?= im_money($r['sotuv_n']) ?> so'm</td>
            <?php /* Qiymat ustuni sarlavhadagi "Tan narxda" bilan bir xil asosda — FIFO tannarx.
                     Ilgari bu yerda sotuv narxi ko'paytirilardi va bitta jadvalda ikki xil jami chiqardi. */ ?>
            <td class="text-right num fw-bold"><?= im_money($r['soni'] * $r['kelish_narxi']) ?> so'm</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot style="background:var(--bg)">
            <tr>
              <td colspan="3" class="fw-bold">Jami</td>
              <td class="text-right fw-bold"><?= number_format(array_sum(array_column($fd['rows'],'soni'))) ?></td>
              <td colspan="2" class="text-right text-muted fs-xs">
                Sotuv narxida: <?= im_money(array_sum(array_map(fn($r) => $r['soni'] * $r['sotuv_n'], $fd['rows']))) ?> so'm
              </td>
              <td class="text-right num fw-bold" style="color:var(--success)">
                <?= im_money(array_sum(array_map(fn($r) => $r['soni'] * $r['kelish_narxi'], $fd['rows']))) ?> so'm
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-shop"></i><h4>Qoldiq yo'q</h4></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </main>
</div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
</body>
</html>
