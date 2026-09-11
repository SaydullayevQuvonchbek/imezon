<?php
// ============================================================
//  IMezon — Do'kon: Mahsulot Qoldiqlari + Sotuvlar
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../fifo_reports.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();
$fifo_stock = im_fifo_report_stock_sql();

$filial_id = $im_filial_id ?: 1;
$filial = $db->row("SELECT nomi FROM im_filiallar WHERE id=$filial_id");

// Filter
$q       = trim($_GET['q'] ?? '');
$kat_id  = (int)($_GET['kat'] ?? 0);
$tab     = $_GET['tab'] ?? 'qoldiq'; // qoldiq | sotuvlar

$where = "fq.filial_id=$filial_id AND m.status=1";
if ($q) {
    $qs = mysqli_real_escape_string($link, $q);
    $where .= " AND (m.nomi LIKE '%$qs%' OR m.barcode LIKE '%$qs%')";
}
if ($kat_id) $where .= " AND m.kategoriya_id=$kat_id";

// ── Mahsulot qoldiqlari ───────────────────────────────────
$mahsulotlar = $db->rows(
    "SELECT fq.*, m.nomi, m.barcode, m.birlik, k.nomi AS kat_nomi,
            COALESCE(physical.kelish_narxi,0) AS kelish_narxi,
            COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0) AS sotuv_narxi,
            (fq.soni * COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0)) AS qoldiq_qiymati,
            (fq.soni * COALESCE(physical.kelish_narxi, 0)) AS tan_qiymati
     FROM im_filial_qoldiq fq
     LEFT JOIN ($fifo_stock) physical ON physical.filial_id=fq.filial_id AND physical.mahsulot_id=fq.mahsulot_id
     JOIN im_mahsulotlar m ON m.id = fq.mahsulot_id
     LEFT JOIN im_kategoriyalar k ON k.id = m.kategoriya_id
     LEFT JOIN im_narxlar n ON n.mahsulot_id = m.id
     WHERE $where
     ORDER BY m.nomi ASC"
);

// ── Bugungi sotuvlar ─────────────────────────────────────
$today = date('Y-m-d');
$dan = $_GET['dan'] ?? $today;
$gacha = $_GET['gacha'] ?? $today;
$ds = mysqli_real_escape_string($link, $dan);
$gs = mysqli_real_escape_string($link, $gacha);

$sotuvlar = $db->rows(
    "SELECT s.id, s.chek_nomer, s.sana, s.tolov_summa,
            s.naqd_summa, s.karta_summa, s.bank_summa, s.nasiya_summa,
            s.usd_summa, s.chegirma_summa,
            x.ism AS kassir_ism,
            m.ism AS mijoz_ism,
            COUNT(si.id) AS item_soni
     FROM im_sotuvlar s
     LEFT JOIN im_xodimlar x ON x.id = s.kassir_id
     LEFT JOIN im_mijozlar m ON m.id = s.mijoz_id
     LEFT JOIN im_sotuv_items si ON si.sotuv_id = s.id
     WHERE s.holat IN ('aktiv','qaytarilgan') AND s.filial_id = $filial_id AND DATE(s.sana) BETWEEN '$ds' AND '$gs'
     GROUP BY s.id
     ORDER BY s.id DESC"
);

// ── Bugungi statistika ───────────────────────────────────
$stats_today = $db->row(
    "SELECT COUNT(*) AS soni,
            COALESCE(SUM(tolov_summa),0) AS jami,
            COALESCE(SUM(naqd_summa),0) AS naqd,
            COALESCE(SUM(karta_summa),0) AS karta,
            COALESCE(SUM(bank_summa),0) AS bank,
            COALESCE(SUM(nasiya_summa),0) AS nasiya,
            COALESCE(SUM(usd_som_ekviv),0) AS usd_som,
            COALESCE(SUM(chegirma_summa),0) AS chegirma
     FROM im_sotuvlar WHERE holat IN ('aktiv','qaytarilgan') AND filial_id=$filial_id AND DATE(sana) BETWEEN '$ds' AND '$gs'"
);

// USD qaytimni ayirib tashlaymiz
$usd_qaytim_sotuv = (float)$db->val(
    "SELECT COALESCE(SUM(usd_qaytim_som),0) FROM im_sotuvlar
     WHERE filial_id=$filial_id AND DATE(sana) BETWEEN '$ds' AND '$gs'"
);
$stats_today['naqd'] -= $usd_qaytim_sotuv;

// Kategoriyalar (filter uchun)
$kategoriyalar = $db->rows("SELECT * FROM im_kategoriyalar ORDER BY nomi");

// Umumiy qoldiq qiymati
$jami_qoldiq_qiymat = array_sum(array_column($mahsulotlar, 'qoldiq_qiymati'));
$jami_tan_qiymat    = array_sum(array_column($mahsulotlar, 'tan_qiymati'));
$jami_soni          = array_sum(array_column($mahsulotlar, 'soni'));
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Qoldiqlar & Sotuvlar | IMezon Do'kon</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title">
      <i class="bi bi-boxes me-1"></i>
      Qoldiqlar & Sotuvlar — <span style="color:var(--accent)"><?= im_f($filial['nomi'] ?? 'Filial') ?></span>
    </div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- ── Tab tugmalari ──────────────────────────────────── -->
    <div class="d-flex gap-2 mb-4">
      <a href="?tab=qoldiq<?= $q ? '&q='.urlencode($q) : '' ?>"
         class="im-btn im-btn-sm <?= $tab==='qoldiq' ? 'im-btn-primary' : 'im-btn-outline' ?>">
        <i class="bi bi-boxes"></i> Mahsulot qoldiqlari
      </a>
      <a href="?tab=sotuvlar&dan=<?= $dan ?>&gacha=<?= $gacha ?>"
         class="im-btn im-btn-sm <?= $tab==='sotuvlar' ? 'im-btn-primary' : 'im-btn-outline' ?>">
        <i class="bi bi-receipt"></i> Sotuvlar
      </a>
    </div>

    <?php if ($tab === 'qoldiq'): ?>
    <!-- ══════════════════════════════════════════════════════
         TAB 1: MAHSULOT QOLDIQLARI
    ══════════════════════════════════════════════════════════ -->

    <!-- Statistika kartalar -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-6">
        <div class="im-stat-card" style="border-left:4px solid var(--primary)">
          <div class="im-stat-icon" style="background:rgba(79,70,229,.1);color:var(--primary)">
            <i class="bi bi-boxes"></i>
          </div>
          <div>
            <div class="im-stat-label">Mahsulot xillari</div>
            <div class="im-stat-value"><?= count($mahsulotlar) ?> xil</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-6">
        <div class="im-stat-card" style="border-left:4px solid var(--info,#17a2b8)">
          <div class="im-stat-icon" style="background:rgba(23,162,184,.1);color:#17a2b8">
            <i class="bi bi-stack"></i>
          </div>
          <div>
            <div class="im-stat-label">Jami miqdor</div>
            <div class="im-stat-value"><?= ($jami_soni + 0) ?> dona</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter -->
    <div class="im-card mb-3">
      <div class="im-card-body p-3">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
          <input type="hidden" name="tab" value="qoldiq">
          <div class="d-flex gap-2 flex-wrap" style="flex:1">
            <input type="text" name="q" class="im-input im-input-sm" style="max-width:220px"
                   placeholder="Nomi yoki barcode..." value="<?= im_f($q) ?>">
            <select name="kat" class="im-select im-input-sm" style="max-width:180px">
              <option value="0">Barcha kategoriyalar</option>
              <?php foreach ($kategoriyalar as $k): ?>
              <option value="<?= $k['id'] ?>" <?= $kat_id == $k['id'] ? 'selected' : '' ?>>
                <?= im_f($k['nomi']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="im-btn im-btn-primary im-btn-sm"><i class="bi bi-search"></i> Filter</button>
          <?php if ($q || $kat_id): ?>
          <a href="?tab=qoldiq" class="im-btn im-btn-outline im-btn-sm"><i class="bi bi-x-lg"></i></a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Jadval -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-boxes" style="color:var(--primary)"></i>
        <span class="im-card-title">Mahsulot qoldiqlari</span>
        <span class="im-badge im-badge-muted"><?= count($mahsulotlar) ?> ta</span>
      </div>
      <?php if ($mahsulotlar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr>
            <th>#</th>
            <th>Mahsulot</th>
            <th>Kategoriya</th>
            <th class="text-right">Qoldiq</th>
            <th class="text-right">Sotuv narxi</th>
          </tr></thead>
          <tbody>
          <?php foreach ($mahsulotlar as $i => $m):
            $sotuv_n = (float)$m['sotuv_narxi'];
            $soni    = (float)$m['soni'];
            $low     = $soni <= 5;
          ?>
          <tr <?= $low ? 'style="background:rgba(239,68,68,.04)"' : '' ?>>
            <td class="text-muted fs-xs"><?= $i+1 ?></td>
            <td>
              <div class="fw-semibold"><?= im_f($m['nomi']) ?></div>
              <?php if ($m['barcode']): ?>
              <div class="text-muted fs-xs"><?= im_f($m['barcode']) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-muted fs-xs"><?= im_f($m['kat_nomi'] ?? '—') ?></td>
            <td class="text-right fw-bold <?= $low ? 'text-danger' : '' ?>">
              <?= ($soni + 0) ?> <?= im_f($m['birlik'] ?? 'dona') ?>
              <?php if ($low): ?><i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Kam qoldi!"></i><?php endif; ?>
            </td>
            <td class="text-right num" style="color:var(--success)"><?= im_money($sotuv_n) ?> so'm</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot style="background:var(--bg);font-weight:700">
            <tr>
              <td colspan="3" class="fw-bold">Jami</td>
              <td class="text-right fw-bold"><?= ($jami_soni + 0) ?> dona</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-boxes"></i><h4>Qoldiq yo'q</h4><p class="text-muted">Hali mahsulot qabul qilinmagan</p></div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ══════════════════════════════════════════════════════
         TAB 2: SOTUVLAR
    ══════════════════════════════════════════════════════════ -->

    <!-- Sana filter -->
    <div class="im-card mb-3">
      <div class="im-card-body p-3">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
          <input type="hidden" name="tab" value="sotuvlar">
          <label class="im-label mb-0 fs-sm">Dan:</label>
          <input type="date" name="dan" class="im-input im-input-sm" style="max-width:145px"
                 value="<?= im_f($dan) ?>">
          <label class="im-label mb-0 fs-sm ms-2">Gacha:</label>
          <input type="date" name="gacha" class="im-input im-input-sm" style="max-width:145px"
                 value="<?= im_f($gacha) ?>">
          <button type="submit" class="im-btn im-btn-dark im-btn-sm ms-2"><i class="bi bi-filter"></i> Ko'rsat</button>
          <a href="?tab=sotuvlar&dan=<?= date('Y-m-d') ?>&gacha=<?= date('Y-m-d') ?>" class="im-btn im-btn-outline im-btn-sm ms-2">Bugun</a>
        </form>
      </div>
    </div>

    <!-- Statistika -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-2">
        <div class="im-stat-card" style="border-left:4px solid var(--primary)">
          <div><div class="im-stat-label">Sotuv soni</div>
          <div class="im-stat-value"><?= (int)$stats_today['soni'] ?> ta</div></div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div><div class="im-stat-label">Jami summa</div>
          <div class="im-stat-value num" style="font-size:13px"><?= im_money($stats_today['jami']) ?></div></div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="im-stat-card" style="border-left:4px solid #17a2b8">
          <div><div class="im-stat-label">💵 Naqd</div>
          <div class="im-stat-value num" style="font-size:13px"><?= im_money($stats_today['naqd']) ?></div></div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="im-stat-card" style="border-left:4px solid var(--primary)">
          <div><div class="im-stat-label"><?= im_tt_label('karta', true) ?></div>
          <div class="im-stat-value num" style="font-size:13px"><?= im_money($stats_today['karta']) ?></div></div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div><div class="im-stat-label">Nasiya</div>
          <div class="im-stat-value num" style="font-size:13px;color:var(--danger)"><?= im_money($stats_today['nasiya']) ?></div></div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="im-stat-card" style="border-left:4px solid var(--warning)">
          <div><div class="im-stat-label">Chegirma</div>
          <div class="im-stat-value num" style="font-size:13px;color:var(--warning)"><?= im_money($stats_today['chegirma']) ?></div></div>
        </div>
      </div>
    </div>

    <!-- Sotuvlar jadvali -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-receipt" style="color:var(--success)"></i>
        <span class="im-card-title">Sotuvlar</span>
        <span class="im-badge im-badge-muted"><?= count($sotuvlar) ?> ta</span>
      </div>
      <?php if ($sotuvlar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr>
            <th>Chek</th>
            <th>Vaqt</th>
            <th>Kassir</th>
            <th>Mijoz</th>
            <th class="text-center">Mahsulot</th>
            <th class="text-right">Jami</th>
            <th>To'lov turi</th>
            <th class="text-end">Amal</th>
          </tr></thead>
          <tbody>
          <?php foreach ($sotuvlar as $s): ?>
          <tr>
            <td><code class="fs-xs"><?= im_f($s['chek_nomer']) ?></code></td>
            <td class="text-muted fs-xs"><?= date('d.m.Y H:i', strtotime($s['sana'])) ?></td>
            <td class="fs-sm"><?= im_f($s['kassir_ism'] ?? '—') ?></td>
            <td class="text-muted fs-xs"><?= im_f($s['mijoz_ism'] ?? '—') ?></td>
            <td class="text-center"><span class="im-badge im-badge-muted"><?= (int)$s['item_soni'] ?> xil</span></td>
            <td class="text-right num fw-bold"><?= im_money($s['tolov_summa']) ?> so'm</td>
            <td>
              <?php
              $pays = [];
              if ($s['naqd_summa'] > 0) $pays[] = '<span class="im-badge im-badge-success fs-xs">naqd</span>';
              if ($s['karta_summa'] > 0) $pays[] = '<span class="im-badge im-badge-primary fs-xs">karta</span>';
              if (!empty($s['bank_summa']) && $s['bank_summa'] > 0) $pays[] = '<span class="im-badge im-badge-info fs-xs">bank</span>';
              if ($s['usd_summa'] > 0) $pays[] = '<span class="im-badge fs-xs" style="background:#fff3cd;color:#856404">$' . number_format($s['usd_summa'], 2) . ' USD</span>';
              if ($s['nasiya_summa'] > 0) $pays[] = '<span class="im-badge im-badge-danger fs-xs">nasiya</span>';
              echo implode(' ', $pays);
              ?>
            </td>
            <td class="text-end">
              <button class="im-btn im-btn-outline im-btn-sm py-1 px-2" title="Chekni chop etish"
                onclick="window.open('<?= im_BASE ?>print/chek.php?id=<?= $s['id'] ?>&auto=1', '_blank', 'width=450,height=650,toolbar=0,menubar=0,scrollbars=1')">
                <i class="bi bi-printer"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot style="background:var(--bg)">
            <tr>
              <td colspan="5" class="fw-bold">Jami</td>
              <td class="text-right num fw-bold" style="color:var(--success)"><?= im_money($stats_today['jami']) ?> so'm</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-receipt"></i><h4>Sotuv yo'q</h4><p class="text-muted">Bu kunda sotuv amalga oshirilmagan</p></div>
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
