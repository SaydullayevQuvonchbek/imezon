<?php
// ============================================================
//  IMezon — Qayta Ishlash Dashboard
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'kassir', 'sklad']);

$db = new Cyber();

// Statistika
$bugun_ishlab  = (int)$db->val("SELECT COUNT(*) FROM im_ishlab_chiqarish WHERE DATE(sana)=CURDATE() AND tur='ishlab_chiqarish' AND holat='bajarildi'");
$bugun_mayda   = (int)$db->val("SELECT COUNT(*) FROM im_ishlab_chiqarish WHERE DATE(sana)=CURDATE() AND tur='maydalash' AND holat='bajarildi'");
$jami_retsept  = (int)$db->val("SELECT COUNT(*) FROM im_retseptlar WHERE status=1");
$oy_operatsiya = (int)$db->val("SELECT COUNT(*) FROM im_ishlab_chiqarish WHERE MONTH(sana)=MONTH(CURDATE()) AND holat='bajarildi'");

// Oxirgi 15 ta operatsiya
$oxirgi = $db->rows(
    "SELECT ic.*, r.nomi AS retsept_nomi, r.tur AS r_tur,
            m.nomi AS mahsulot_nomi,
            x.ism AS xodim_ism,
            f.nomi AS filial_nomi
     FROM im_ishlab_chiqarish ic
     LEFT JOIN im_retseptlar r ON r.id = ic.retsept_id
     LEFT JOIN im_mahsulotlar m ON m.id = ic.mahsulot_id
     LEFT JOIN im_xodimlar x ON x.id = ic.xodim_id
     LEFT JOIN im_filiallar f ON f.id = ic.filial_id
     ORDER BY ic.sana DESC LIMIT 15"
);

$page_title = 'Qayta Ishlash';
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

  <?php require_once __DIR__ . '/navbar.php'; ?>

  <div class="im-main">
    <header class="im-topbar">
      <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
      <div class="im-page-title"><i class="bi bi-gear-fill me-1"></i> Qayta Ishlash</div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
        <a class="im-btn im-btn-primary im-btn-sm" href="<?= im_BASE ?>qayta-ishlash/yangi.php">
          <i class="bi bi-plus-lg"></i> Yangi
        </a>
      </div>
    </header>

    <main class="im-content">
      <div class="im-breadcrumb">
        <i class="bi bi-house-fill"></i>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Qayta Ishlash</span>
      </div>

      <!-- Stat cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="im-stat primary im-slide-in">
            <div class="im-stat-icon primary"><i class="bi bi-fire"></i></div>
            <div class="im-stat-body">
              <div class="im-stat-label">Bugun ishlab chiqarish</div>
              <div class="im-stat-value num"><?= $bugun_ishlab ?></div>
              <div class="im-stat-sub">operatsiya</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="im-stat warning im-slide-in">
            <div class="im-stat-icon warning"><i class="bi bi-scissors"></i></div>
            <div class="im-stat-body">
              <div class="im-stat-label">Bugun maydalash</div>
              <div class="im-stat-value num"><?= $bugun_mayda ?></div>
              <div class="im-stat-sub">operatsiya</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="im-stat success im-slide-in">
            <div class="im-stat-icon success"><i class="bi bi-journal-text"></i></div>
            <div class="im-stat-body">
              <div class="im-stat-label">Retseptlar</div>
              <div class="im-stat-value num"><?= $jami_retsept ?></div>
              <div class="im-stat-sub">aktiv</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="im-stat info im-slide-in">
            <div class="im-stat-icon info"><i class="bi bi-calendar-month-fill"></i></div>
            <div class="im-stat-body">
              <div class="im-stat-label">Bu oy</div>
              <div class="im-stat-value num"><?= $oy_operatsiya ?></div>
              <div class="im-stat-sub">jami operatsiya</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tezkor amallar -->
      <div class="im-card im-slide-in mb-4">
        <div class="im-card-header">
          <i class="bi bi-lightning-fill" style="color:var(--warning)"></i>
          <span class="im-card-title">Tezkor amallar</span>
        </div>
        <div class="im-card-body">
          <div class="d-flex flex-wrap gap-3">
            <a href="<?= im_BASE ?>qayta-ishlash/yangi.php?tur=ishlab_chiqarish" class="im-btn im-btn-primary im-btn-lg">
              <i class="bi bi-fire"></i> Ishlab chiqarish
            </a>
            <a href="<?= im_BASE ?>qayta-ishlash/yangi.php?tur=maydalash" class="im-btn im-btn-warning im-btn-lg">
              <i class="bi bi-scissors"></i> Maydalash
            </a>
            <a href="<?= im_BASE ?>qayta-ishlash/retseptlar.php" class="im-btn im-btn-outline im-btn-lg">
              <i class="bi bi-journal-text"></i> Retseptlar
            </a>
            <?php if ($im_rol === 'admin'): ?>
            <a href="<?= im_BASE ?>qayta-ishlash/hisobot.php" class="im-btn im-btn-dark im-btn-lg">
              <i class="bi bi-bar-chart-fill"></i> Hisobot
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Oxirgi operatsiyalar -->
      <div class="im-card im-slide-in">
        <div class="im-card-header">
          <i class="bi bi-clock-history" style="color:var(--primary)"></i>
          <span class="im-card-title">Oxirgi operatsiyalar</span>
        </div>
        <div class="im-table-wrap">
          <table class="im-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Tur</th>
                <th>Retsept</th>
                <th>Mahsulot</th>
                <th>Soni</th>
                <th>Filial/Sklad</th>
                <th>Xodim</th>
                <th>Sana</th>
                <th>Holat</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($oxirgi): ?>
              <?php foreach ($oxirgi as $op): ?>
              <tr>
                <td class="text-muted fs-sm"><?= $op['id'] ?></td>
                <td>
                  <?php if ($op['tur'] === 'ishlab_chiqarish'): ?>
                  <span class="im-badge im-badge-info"><i class="bi bi-fire"></i> Ishlab chiqarish</span>
                  <?php else: ?>
                  <span class="im-badge im-badge-warning"><i class="bi bi-scissors"></i> Maydalash</span>
                  <?php endif; ?>
                </td>
                <td class="fw-semibold"><?= im_f($op['retsept_nomi'] ?: '—') ?></td>
                <td><?= im_f($op['mahsulot_nomi'] ?: '—') ?></td>
                <td class="num"><?= (float)$op['soni'] ?> × → <?= (float)$op['chiqish_soni'] ?></td>
                <td><?= im_f($op['filial_nomi'] ?: 'Sklad') ?></td>
                <td class="text-muted fs-sm"><?= im_f($op['xodim_ism'] ?: '—') ?></td>
                <td class="text-muted fs-sm"><?= im_datetime($op['sana']) ?></td>
                <td>
                  <?php if ($op['holat'] === 'bajarildi'): ?>
                  <span class="im-badge im-badge-success">✓ Bajarildi</span>
                  <?php else: ?>
                  <span class="im-badge im-badge-danger">✗ Bekor</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr><td colspan="9">
                <div class="im-empty">
                  <i class="bi bi-gear"></i>
                  <h4>Operatsiyalar yo'q</h4>
                  <p>Hali birorta jarayon bajarilmagan</p>
                  <a href="<?= im_BASE ?>qayta-ishlash/yangi.php" class="im-btn im-btn-primary">
                    <i class="bi bi-plus-lg"></i> Yangi jarayon
                  </a>
                </div>
              </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
</body>
</html>
