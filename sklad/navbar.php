<?php
// To'g'ridan-to'g'ri HTTP so'rovdan himoya — bu fayl faqat
// sahifa ichidan require qilinadi (ai_lib.php / tts_lib.php dagidek).
if (!defined('im_VERSION')) { http_response_code(404); exit; }
// ============================================================
//  IMezon — Sklad Navbar (include)
//  require_once __DIR__ . '/navbar.php';
// ============================================================
$base = defined('im_BASE') ? im_BASE : '/';
// Joriy sahifa aktivmi — YAGONA MANBA (config.php → im_nav_aktiv()).
$akt = function ($yol) use ($base) {
    return im_nav_aktiv($base . $yol) ? 'active' : '';
};
?>
<div class="im-sidebar" id="im-sidebar">

  <!-- Brand -->
  <div class="im-brand">
    <div class="im-brand-logo">🏪</div>
    <div class="im-brand-text">
      <div class="im-brand-name">IMezon</div>
      <div class="im-brand-sub">Sklad moduli</div>
    </div>
  </div>

  <!-- Nav -->
  <nav class="im-nav">
    <div class="im-nav-label">Asosiy</div>

    <a href="<?= $base ?>sklad/index.php" class="im-nav-item <?= $akt('sklad/index.php') ?>">
      <i class="bi bi-speedometer2"></i>
      Dashboard
    </a>

    <div class="im-nav-label">Katalog</div>

    <a href="<?= $base ?>sklad/kategoriyalar.php" class="im-nav-item <?= $akt('sklad/kategoriyalar.php') ?>">
      <i class="bi bi-tags-fill"></i>
      Kategoriyalar
    </a>

    <a href="<?= $base ?>sklad/mahsulotlar.php" class="im-nav-item <?= $akt('sklad/mahsulotlar.php') ?>">
      <i class="bi bi-box-seam-fill"></i>
      Mahsulotlar
    </a>

    <div class="im-nav-label">Operatsiyalar</div>

    <a href="<?= $base ?>sklad/qabul.php" class="im-nav-item <?= $akt('sklad/qabul.php') ?>">
      <i class="bi bi-box-arrow-in-down"></i>
      Yuk qabul
    </a>

    <a href="<?= $base ?>sklad/send-dukon.php" class="im-nav-item <?= $akt('sklad/send-dukon.php') ?>">
      <i class="bi bi-send-fill"></i>
      Filialga jo'natish
    </a>

    <a href="<?= $base ?>sklad/inventarizatsiya.php" class="im-nav-item <?= $akt('sklad/inventarizatsiya.php') ?>">
      <i class="bi bi-clipboard-check-fill"></i>
      Inventarizatsiya
    </a>

    <a href="<?= $base ?>sklad/barcode-print.php" class="im-nav-item <?= $akt('sklad/barcode-print.php') ?>">
      <i class="bi bi-upc-scan"></i>
      Barcode chop
    </a>

    <div class="im-nav-label">Tahlil</div>

    <a href="<?= $base ?>sklad/hisobot.php" class="im-nav-item <?= $akt('sklad/hisobot.php') ?>">
      <i class="bi bi-bar-chart-fill"></i>
      Hisobot
    </a>

    <div class="im-nav-label">Ishlab chiqarish</div>
    <a href="<?= $base ?>qayta-ishlash/index.php"
       class="im-nav-item <?= $akt('qayta-ishlash/index.php') ?>"
       style="background:linear-gradient(90deg,rgba(124,58,237,.12),transparent);border-left:3px solid #7c3aed">
      <i class="bi bi-gear-wide-connected" style="color:#7c3aed"></i>
      <span style="color:#7c3aed;font-weight:600">Qayta Ishlash</span>
    </a>

    <?php if ($im_rol === 'admin'): ?>
      <div class="im-nav-label">Admin</div>
      <a href="<?= $base ?>admin/index.php" class="im-nav-item">
        <i class="bi bi-shield-fill"></i>
        Admin panel
      </a>
    <?php endif; ?>
  </nav>

  <!-- User footer -->
  <div class="im-sidebar-footer">
    <div class="im-user-card" title="Chiqish uchun bosing"
         onclick="window.location='<?= $base ?>logout.php'">
      <div class="im-user-avatar">
        <?= mb_strtoupper(mb_substr($im_ism, 0, 1, 'UTF-8')) ?>
      </div>
      <div>
        <div class="im-user-name"><?= im_f($im_ism) ?></div>
        <div class="im-user-role"><?= im_f(im_rol_nomi($im_rol)) ?></div>
      </div>
      <i class="bi bi-box-arrow-right" style="color:rgba(255,255,255,.4);margin-left:auto"></i>
    </div>
  </div>
</div>