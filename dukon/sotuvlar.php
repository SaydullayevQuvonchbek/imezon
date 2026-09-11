<?php
// ============================================================
//  IMezon — Dukon (kassir): Sotuvlar tarixi
//
//  admin/sotuvlar.php ning kassa uchun moslashtirilgan varianti:
//   - FAQAT o'z filiali (filial tanlash yo'q)
//   - Manba (stol/dastavka) + ofitsant + mijoz ko'rinadi
//   - Tannarx/marja KO'RSATILMAYDI — kassa moduli xarid narxini
//     ochmaydi (dukon/hisobot.php ham shunday)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['kassir', 'admin']);

$db        = new Cyber();
$filial_id = (int)$_SESSION['im_filial_id'];

// ── Filtrlar ──────────────────────────────────────────────
$dan   = $_GET['dan']   ?? date('Y-m-d');
$gacha = $_GET['gacha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dan))   $dan   = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $gacha)) $gacha = date('Y-m-d');
if ($dan > $gacha) { [$dan, $gacha] = [$gacha, $dan]; }

$q     = trim($_GET['q'] ?? '');
$where = "s.filial_id = $filial_id AND DATE(s.sana) BETWEEN '$dan' AND '$gacha'";
if ($q !== '') {
    $qs = mysqli_real_escape_string($link, $q);
    $where .= " AND (s.chek_nomer LIKE '%$qs%' OR s.manba LIKE '%$qs%')";
}

// ── Sahifalash ────────────────────────────────────────────
$per       = 50;
$page      = max(1, (int)($_GET['page'] ?? 1));
$jami_soni = (int)$db->val("SELECT COUNT(*) FROM im_sotuvlar s WHERE $where");
$pages     = max(1, (int)ceil($jami_soni / $per));
$page      = min($page, $pages);
$offset    = ($page - 1) * $per;

// ── Statistika ────────────────────────────────────────────
$st = $db->row(
    "SELECT COUNT(*) AS cheklar,
            COALESCE(SUM(s.tolov_summa),0)    AS jami,
            COALESCE(SUM(s.naqd_summa),0)     AS naqd,
            COALESCE(SUM(s.karta_summa),0)    AS karta,
            COALESCE(SUM(s.bank_summa),0)     AS bank,
            COALESCE(SUM(s.nasiya_summa),0)   AS nasiya,
            COALESCE(SUM(s.chegirma_summa),0) AS chegirma,
            COALESCE(SUM(s.xizmat_summa),0)   AS xizmat
     FROM im_sotuvlar s WHERE $where"
) ?: [];

// ── Ro'yxat ───────────────────────────────────────────────
$sotuvlar = $db->rows(
    "SELECT s.id, s.chek_nomer, s.sana, s.manba, s.stol_id, s.holat,
            s.tolov_summa, s.naqd_summa, s.karta_summa, s.bank_summa,
            s.usd_summa, s.nasiya_summa, s.chegirma_summa,
            s.xizmat_summa, s.xizmat_foiz,
            m.ism AS mijoz_ism,
            o.ism AS ofitsant_ism,
            (SELECT COUNT(*) FROM im_sotuv_items si WHERE si.sotuv_id = s.id) AS item_soni
     FROM im_sotuvlar s
     LEFT JOIN im_mijozlar m ON m.id = s.mijoz_id
     LEFT JOIN im_xodimlar o ON o.id = s.sotuvchi_id
     WHERE $where
     ORDER BY s.sana DESC, s.id DESC
     LIMIT $per OFFSET $offset"
);

function ds_link($p) {
    $g = $_GET;
    $g['page'] = $p;
    return '?' . http_build_query($g);
}
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Sotuvlar tarixi | IMezon Do'kon</title>
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
    <div class="im-page-title"><i class="bi bi-receipt-cutoff me-1"></i> Sotuvlar tarixi</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>

  <div class="im-content">

    <!-- Filtr -->
    <div class="im-card mb-3">
      <div class="im-card-body p-3">
        <form class="d-flex gap-2 flex-wrap align-items-end" method="get">
          <div>
            <label class="im-label">Dan</label>
            <input class="im-input im-input-sm" type="date" name="dan" value="<?= im_f($dan) ?>">
          </div>
          <div>
            <label class="im-label">Gacha</label>
            <input class="im-input im-input-sm" type="date" name="gacha" value="<?= im_f($gacha) ?>">
          </div>
          <div>
            <label class="im-label">Qidiruv</label>
            <input class="im-input im-input-sm" type="text" name="q" value="<?= im_f($q) ?>"
                   placeholder="Chek raqami yoki stol...">
          </div>
          <button class="im-btn im-btn-dark im-btn-sm" type="submit"><i class="bi bi-filter"></i> Ko'rsat</button>
          <a href="<?= im_BASE ?>dukon/sotuvlar.php" class="im-btn im-btn-ghost im-btn-sm">Bugun</a>
        </form>
      </div>
    </div>

    <!-- Statistika -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--accent-dark)">
          <div class="im-stat-icon" style="background:rgba(226,185,111,.15);color:var(--accent-dark)"><i class="bi bi-bag-fill"></i></div>
          <div>
            <div class="im-stat-label">Jami savdo</div>
            <div class="im-stat-value num"><?= im_money($st['jami'] ?? 0) ?> so'm</div>
            <div class="text-muted fs-xs"><?= (int)($st['cheklar'] ?? 0) ?> ta chek</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i class="bi bi-cash"></i></div>
          <div>
            <div class="im-stat-label">Naqd</div>
            <div class="im-stat-value num"><?= im_money($st['naqd'] ?? 0) ?> so'm</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--primary)">
          <div class="im-stat-icon" style="background:rgba(13,110,253,.12);color:var(--primary)"><i class="bi bi-credit-card"></i></div>
          <div>
            <div class="im-stat-label">Karta</div>
            <div class="im-stat-value num"><?= im_money($st['karta'] ?? 0) ?> so'm</div>
            <?php if ((float)($st['nasiya'] ?? 0) > 0): ?>
              <div class="text-muted fs-xs">Nasiya: <?= im_money($st['nasiya']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid #0d6efd">
          <div class="im-stat-icon" style="background:rgba(13,110,253,.12);color:#0d6efd"><i class="bi bi-people-fill"></i></div>
          <div>
            <div class="im-stat-label">Xizmat haqi</div>
            <div class="im-stat-value num"><?= im_money($st['xizmat'] ?? 0) ?> so'm</div>
            <?php if ((float)($st['chegirma'] ?? 0) > 0): ?>
              <div class="text-muted fs-xs">Chegirma: -<?= im_money($st['chegirma']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Ro'yxat -->
    <div class="im-card">
      <div class="im-card-header">
        <i class="bi bi-receipt"></i>
        <span class="im-card-title">Cheklar</span>
        <span class="im-badge im-badge-muted"><?= $jami_soni ?> ta</span>
      </div>
      <?php if (!$sotuvlar): ?>
        <div class="im-empty p-4"><i class="bi bi-inbox"></i><h4>Bu davrda sotuv yo'q</h4></div>
      <?php else: ?>
        <div class="im-table-wrap">
          <table class="im-table">
            <thead>
              <tr>
                <th>Chek</th>
                <th>Vaqt</th>
                <th>Manba / Ofitsant / Mijoz</th>
                <th class="text-center">Mahsulot</th>
                <th class="text-right">Jami</th>
                <th>To'lov</th>
                <th class="text-end">Amal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($sotuvlar as $s): ?>
                <tr<?= $s['holat'] !== 'aktiv' ? ' style="opacity:.55"' : '' ?>>
                  <td>
                    <code class="fs-xs"><?= im_f($s['chek_nomer']) ?></code>
                    <?php if ($s['holat'] !== 'aktiv'): ?>
                      <div><span class="im-badge im-badge-danger fs-xs"><?= im_f($s['holat']) ?></span></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted fs-xs"><?= date('d.m H:i', strtotime($s['sana'])) ?></td>
                  <td class="fs-xs">
                    <?php if ($s['manba']): ?>
                      <div class="fw-semibold">
                        <i class="bi bi-<?= $s['stol_id'] ? 'geo-alt-fill' : 'bag-fill' ?>" style="color:var(--accent-dark)"></i>
                        <?= im_f($s['manba']) ?>
                      </div>
                    <?php else: ?>
                      <div class="text-muted">—</div>
                    <?php endif; ?>
                    <?php if ($s['ofitsant_ism']): ?>
                      <div class="text-muted fs-xs">Ofitsant: <?= im_f($s['ofitsant_ism']) ?></div>
                    <?php endif; ?>
                    <?php if ($s['mijoz_ism']): ?>
                      <div style="color:#0d6efd">Mijoz: <?= im_f($s['mijoz_ism']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-center"><span class="im-badge im-badge-muted"><?= (int)$s['item_soni'] ?> xil</span></td>
                  <td class="text-right num fw-bold" style="color:var(--accent-dark)">
                    <?= im_money($s['tolov_summa']) ?> so'm
                    <?php if ((float)$s['chegirma_summa'] > 0): ?>
                      <div class="text-muted fs-xs">-<?= im_money($s['chegirma_summa']) ?></div>
                    <?php endif; ?>
                    <?php if ((float)$s['xizmat_summa'] > 0): ?>
                      <div class="fs-xs" style="color:#0d6efd">
                        +<?= im_money($s['xizmat_summa']) ?>
                        (<?= rtrim(rtrim(number_format((float)$s['xizmat_foiz'], 2, '.', ''), '0'), '.') ?>%)
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                    $pays = [];
                    if ($s['naqd_summa']   > 0) $pays[] = '<span class="im-badge im-badge-success fs-xs">naqd</span>';
                    if ($s['karta_summa']  > 0) $pays[] = '<span class="im-badge im-badge-primary fs-xs">karta</span>';
                    if ($s['bank_summa']   > 0) $pays[] = '<span class="im-badge im-badge-info fs-xs">bank</span>';
                    if ($s['usd_summa']    > 0) $pays[] = '<span class="im-badge fs-xs" style="background:#fff3cd;color:#856404">USD</span>';
                    if ($s['nasiya_summa'] > 0) $pays[] = '<span class="im-badge im-badge-danger fs-xs">nasiya</span>';
                    echo implode(' ', $pays);
                    ?>
                  </td>
                  <td class="text-end">
                    <button class="im-btn im-btn-outline im-btn-sm py-1 px-2" title="Chekni chop etish"
                      onclick="window.open('<?= im_BASE ?>print/chek.php?id=<?= (int)$s['id'] ?>&auto=1', '_blank', 'width=450,height=650,toolbar=0,menubar=0,scrollbars=1')">
                      <i class="bi bi-printer"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages > 1): ?>
          <div class="im-card-body p-2 d-flex justify-content-center gap-1 flex-wrap">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
              <a href="<?= im_f(ds_link($i)) ?>"
                 class="im-btn im-btn-sm <?= $i === $page ? 'im-btn-dark' : 'im-btn-ghost' ?>"><?= $i ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</div>
</div>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
</body>
</html>
