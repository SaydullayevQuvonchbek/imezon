<?php
// ============================================================
//  IMezon — Sklad Dashboard
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

// Statistika
$jami_mahsulot   = (int)$db->val("SELECT COUNT(*) FROM im_mahsulotlar WHERE status=1");
$jami_partiya    = (int)$db->val("SELECT COUNT(*) FROM im_partiyalar WHERE holat='yopiq'");
$bugun_qabul     = (int)$db->val("SELECT COUNT(*) FROM im_partiyalar WHERE DATE(sana)=CURDATE()");
$kam_qoldiq      = (int)$db->val(
    "SELECT COUNT(*) FROM im_mahsulotlar m
     WHERE m.status=1
       AND (SELECT COALESCE(SUM(pi.remaining_qty),0) 
            FROM im_fifo_layers pi 
            WHERE pi.mahsulot_id=m.id AND pi.location_id=0 AND pi.cancelled=0) < 5
       AND (SELECT COALESCE(SUM(pi.remaining_qty),0) 
            FROM im_fifo_layers pi 
            WHERE pi.mahsulot_id=m.id AND pi.location_id=0 AND pi.cancelled=0) > 0"
);

$jami_kategoriya = (int)$db->val("SELECT COUNT(*) FROM im_kategoriyalar WHERE status=1");
$jami_postavshik = (int)$db->val("SELECT COUNT(*) FROM im_postavshiklar WHERE status=1");

// Oxirgi 8 ta partiya
$oxirgi_partiyalar = $db->rows(
    "SELECT p.*, ps.nomi AS postavshik_nomi,
            (SELECT COUNT(*) FROM im_partiya_items WHERE partiya_id=p.id) AS items_soni
     FROM im_partiyalar p
     LEFT JOIN im_postavshiklar ps ON ps.id=p.postavshik_id
     WHERE p.holat='yopiq'
     ORDER BY p.sana DESC LIMIT 8"
);

// Top 8 mahsulot (qoldiq bo'yicha)
$top_mahsulotlar = $db->rows(
    "SELECT m.id, m.nomi, m.barcode,
            k.nomi AS kategoriya_nomi, k.rang AS kategoriya_rang,
            COALESCE(SUM(pi.remaining_qty),0) AS jami_qoldiq,
            COALESCE(n.sotish_narxi,0) AS sotuv_narx
     FROM im_mahsulotlar m
     LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
     LEFT JOIN im_fifo_layers pi ON pi.mahsulot_id=m.id AND pi.location_id=0 AND pi.cancelled=0 AND pi.remaining_qty>0
     LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
     WHERE m.status=1
     GROUP BY m.id
     ORDER BY jami_qoldiq DESC
     LIMIT 8"
);

// Kunlik harakatlar (so'nggi 7 kun)
$haftalik = $db->rows(
    "SELECT DATE(sana) AS kun, COUNT(*) AS soni, SUM(jami_summa) AS summa
     FROM im_partiyalar
     WHERE holat='yopiq' AND sana >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(sana)
     ORDER BY kun ASC"
);

$page_title = 'Sklad Dashboard';
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= im_f($page_title) ?> | IMezon</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
</head>
<body>
<div class="im-wrapper">

  <!-- Sidebar -->
  <?php require_once __DIR__ . '/navbar.php'; ?>

  <!-- Main -->
  <div class="im-main">

    <!-- Topbar -->
    <header class="im-topbar">
      <button class="im-topbar-btn" id="im-sidebar-toggle" title="Menyu">
        <i class="bi bi-list"></i>
      </button>
      <div class="im-page-title">
        <i class="bi bi-speedometer2 me-1"></i> Dashboard
      </div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle title="Rejim">
          <i class="bi bi-moon-fill"></i>
        </button>
        <a class="im-topbar-btn" href="<?= im_BASE ?>sklad/qabul.php" title="Yangi partiya">
          <i class="bi bi-plus-lg"></i>
        </a>
      </div>
    </header>

    <!-- Content -->
    <main class="im-content">

      <!-- Breadcrumb -->
      <div class="im-breadcrumb">
        <i class="bi bi-house-fill"></i>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Sklad</span>
      </div>

      <!-- Stat cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
          <div class="im-stat primary im-slide-in">
            <div class="im-stat-icon primary">
              <i class="bi bi-box-seam-fill"></i>
            </div>
            <div class="im-stat-body">
              <div class="im-stat-label">Mahsulotlar</div>
              <div class="im-stat-value num"><?= $jami_mahsulot ?></div>
              <div class="im-stat-sub">aktiv</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="im-stat success im-slide-in">
            <div class="im-stat-icon success">
              <i class="bi bi-box-arrow-in-down"></i>
            </div>
            <div class="im-stat-body">
              <div class="im-stat-label">Jami partiya</div>
              <div class="im-stat-value num"><?= $jami_partiya ?></div>
              <div class="im-stat-sub">qabul qilingan</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="im-stat info im-slide-in">
            <div class="im-stat-icon info">
              <i class="bi bi-calendar-day-fill"></i>
            </div>
            <div class="im-stat-body">
              <div class="im-stat-label">Bugun qabul</div>
              <div class="im-stat-value num"><?= $bugun_qabul ?></div>
              <div class="im-stat-sub">partiya</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="im-stat <?= $kam_qoldiq > 0 ? 'danger' : 'success' ?> im-slide-in">
            <div class="im-stat-icon <?= $kam_qoldiq > 0 ? 'danger' : 'success' ?>">
              <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="im-stat-body">
              <div class="im-stat-label">Kam qoldiq</div>
              <div class="im-stat-value num"><?= $kam_qoldiq ?></div>
              <div class="im-stat-sub">&lt;5 dona</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="im-stat primary im-slide-in">
            <div class="im-stat-icon primary">
              <i class="bi bi-tags-fill"></i>
            </div>
            <div class="im-stat-body">
              <div class="im-stat-label">Kategoriyalar</div>
              <div class="im-stat-value num"><?= $jami_kategoriya ?></div>
              <div class="im-stat-sub">aktiv</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="im-stat warning im-slide-in">
            <div class="im-stat-icon warning">
              <i class="bi bi-truck"></i>
            </div>
            <div class="im-stat-body">
              <div class="im-stat-label">Postavshiklar</div>
              <div class="im-stat-value num"><?= $jami_postavshik ?></div>
              <div class="im-stat-sub">aktiv</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Two columns -->
      <div class="row g-3 mb-4">

        <!-- Oxirgi partiyalar -->
        <div class="col-lg-7">
          <div class="im-card im-slide-in">
            <div class="im-card-header">
              <i class="bi bi-box-arrow-in-down" style="color:var(--success)"></i>
              <span class="im-card-title">Oxirgi partiyalar</span>
              <a href="<?= im_BASE ?>sklad/qabul.php" class="im-btn im-btn-primary im-btn-sm">
                <i class="bi bi-plus-lg"></i> Yangi
              </a>
            </div>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Postavshik</th>
                    <th>To'lov</th>
                    <th class="text-right">Summa</th>
                    <th>Sana</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($oxirgi_partiyalar): ?>
                  <?php foreach ($oxirgi_partiyalar as $i => $p): ?>
                  <tr>
                    <td class="text-muted fs-sm"><?= $p['id'] ?></td>
                    <td>
                      <div class="fw-semibold"><?= im_f($p['postavshik_nomi'] ?: '—') ?></div>
                      <div class="text-muted fs-xs"><?= $p['items_soni'] ?> xil</div>
                    </td>
                    <td>
                      <span class="im-badge <?= $p['tolov_turi'] === 'qarz' ? 'im-badge-danger' : 'im-badge-success' ?>">
                        <?= im_f($p['tolov_turi']) ?>
                      </span>
                    </td>
                    <td class="text-right num fw-semibold"><?= im_money($p['jami_summa']) ?></td>
                    <td class="text-muted fs-sm"><?= im_date($p['sana']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php else: ?>
                  <tr>
                    <td colspan="5">
                      <div class="im-empty">
                        <i class="bi bi-inbox"></i>
                        <h4>Partiyalar yo'q</h4>
                        <p>Hali birorta yuk qabul qilinmagan</p>
                      </div>
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Top mahsulotlar -->
        <div class="col-lg-5">
          <div class="im-card im-slide-in">
            <div class="im-card-header">
              <i class="bi bi-bar-chart-fill" style="color:var(--accent-dark)"></i>
              <span class="im-card-title">Qoldiq bo'yicha top</span>
              <a href="<?= im_BASE ?>sklad/mahsulotlar.php" class="im-btn im-btn-outline im-btn-sm">
                Hammasi
              </a>
            </div>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead>
                  <tr>
                    <th>Mahsulot</th>
                    <th class="text-right">Qoldiq</th>
                    <th class="text-right">Narx</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($top_mahsulotlar): ?>
                  <?php foreach ($top_mahsulotlar as $m): ?>
                  <tr>
                    <td>
                      <div class="d-flex align-center gap-2">
                        <?php if ($m['kategoriya_rang']): ?>
                        <span class="im-color-dot" style="background:<?= im_f($m['kategoriya_rang']) ?>"></span>
                        <?php endif; ?>
                        <div>
                          <div class="fw-semibold" style="font-size:12.5px"><?= im_f($m['nomi']) ?></div>
                          <div class="text-muted" style="font-size:11px"><?= im_f($m['kategoriya_nomi'] ?: '—') ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="text-right">
                      <span class="im-badge <?= (int)$m['jami_qoldiq'] < 5 ? 'im-badge-danger' : 'im-badge-success' ?>">
                        <?= (int)$m['jami_qoldiq'] ?>
                      </span>
                    </td>
                    <td class="text-right num fw-semibold" style="font-size:12px">
                      <?= im_money($m['sotuv_narx']) ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php else: ?>
                  <tr>
                    <td colspan="3">
                      <div class="im-empty">
                        <i class="bi bi-box-seam"></i>
                        <h4>Mahsulotlar yo'q</h4>
                      </div>
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

      <!-- Quick actions -->
      <div class="row g-3">
        <div class="col-12">
          <div class="im-card im-slide-in">
            <div class="im-card-header">
              <i class="bi bi-lightning-fill" style="color:var(--warning)"></i>
              <span class="im-card-title">Tezkor amallar</span>
            </div>
            <div class="im-card-body">
              <div class="d-flex flex-wrap gap-3">
                <a href="<?= im_BASE ?>sklad/qabul.php" class="im-btn im-btn-primary im-btn-lg">
                  <i class="bi bi-box-arrow-in-down"></i>
                  Yuk qabul qilish
                </a>
                <a href="<?= im_BASE ?>sklad/mahsulotlar.php" class="im-btn im-btn-dark im-btn-lg">
                  <i class="bi bi-plus-circle-fill"></i>
                  Mahsulot qo'shish
                </a>
                <a href="<?= im_BASE ?>sklad/barcode-print.php" class="im-btn im-btn-outline im-btn-lg">
                  <i class="bi bi-upc-scan"></i>
                  Barcode chop
                </a>
                <!-- Do'konga jo'natish olib tashlandi -->
                <a href="<?= im_BASE ?>sklad/hisobot.php" class="im-btn im-btn-outline im-btn-lg">
                  <i class="bi bi-file-earmark-excel-fill"></i>
                  Hisobot
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Toast -->
<div id="im-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
</body>
</html>
