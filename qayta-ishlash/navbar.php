<?php
// To'g'ridan-to'g'ri HTTP so'rovdan himoya — bu fayl faqat
// sahifa ichidan require qilinadi (ai_lib.php / tts_lib.php dagidek).
if (!defined('im_VERSION')) { http_response_code(404); exit; }
// ============================================================
//  IMezon — Qayta Ishlash Navbar
// ============================================================
$base = defined('im_BASE') ? im_BASE : '/';
// Joriy sahifa aktivmi — YAGONA MANBA (config.php → im_nav_aktiv()).
$akt = function ($yol) use ($base) {
    return im_nav_aktiv($base . $yol) ? 'active' : '';
};
$qi_tur = $_GET['tur'] ?? '';
?>
<div class="im-sidebar" id="im-sidebar">

  <!-- Brand -->
  <div class="im-brand">
    <div class="im-brand-logo">⚙️</div>
    <div class="im-brand-text">
      <div class="im-brand-name">IMezon</div>
      <div class="im-brand-sub">Qayta ishlash</div>
    </div>
  </div>

  <!-- Nav -->
  <nav class="im-nav">
    <div class="im-nav-label">Asosiy</div>

    <?php if (($im_rol ?? '') !== 'oshpaz'): ?>
    <a href="<?= $base ?>qayta-ishlash/index.php"
       class="im-nav-item <?= $akt('qayta-ishlash/index.php') ?>">
      <i class="bi bi-speedometer2"></i>
      Dashboard
    </a>

    <div class="im-nav-label">Operatsiyalar</div>

    <a href="<?= $base ?>qayta-ishlash/yangi.php?tur=ishlab_chiqarish"
       class="im-nav-item <?= ($akt('qayta-ishlash/yangi.php') && $qi_tur === 'ishlab_chiqarish') ? 'active' : '' ?>">
      <i class="bi bi-fire"></i>
      Ishlab chiqarish
    </a>
    <?php endif; ?>

    <a href="<?= $base ?>qayta-ishlash/yangi.php?tur=maydalash"
       class="im-nav-item <?= ($akt('qayta-ishlash/yangi.php') && $qi_tur === 'maydalash') ? 'active' : '' ?>">
      <i class="bi bi-scissors"></i>
      Maydalash
    </a>

    <div class="im-nav-label">Boshqaruv</div>
    <?php if (($im_rol ?? '') === 'admin'): ?>
    <a href="<?= $base ?>qayta-ishlash/retseptlar.php"
       class="im-nav-item <?= $akt('qayta-ishlash/retseptlar.php') ?>">
      <i class="bi bi-journal-text"></i>
      Retseptlar
    </a>
    <?php endif; ?>

    <a href="<?= $base ?>oshpaz/qozon.php"
       class="im-nav-item <?= $akt('oshpaz/qozon.php') ?>">
      <i class="bi bi-fire" style="color:#f97316"></i>
      Osh qozoni
    </a>

    <?php if (($im_rol ?? '') === 'admin'): ?>
    <a href="<?= $base ?>qayta-ishlash/hisobot.php"
       class="im-nav-item <?= $akt('qayta-ishlash/hisobot.php') ?>">
      <i class="bi bi-bar-chart-fill"></i>
      Hisobot
    </a>
    <a href="<?= $base ?>qayta-ishlash/osh-hisobot.php"
       class="im-nav-item <?= $akt('qayta-ishlash/osh-hisobot.php') ?>">
      <i class="bi bi-fire"></i>
      Osh hisoboti
    </a>
    <?php endif; ?>

    <div class="im-nav-label">Modullar</div>
    <?php if (($im_rol ?? '') === 'oshpaz'): ?>
    <a href="<?= $base ?>oshpaz/index.php" class="im-nav-item">
      <i class="bi bi-display-fill"></i> Oshxona
    </a>
    <?php else: ?>
    <a href="<?= $base ?>sklad/index.php" class="im-nav-item">
      <i class="bi bi-box-seam-fill"></i> Sklad
    </a>
    <a href="<?= $base ?>dukon/index.php" class="im-nav-item">
      <i class="bi bi-bag-fill"></i> Do'kon
    </a>
    <?php endif; ?>
    <?php if (($im_rol ?? '') === 'admin'): ?>
    <a href="<?= $base ?>admin/index.php" class="im-nav-item">
      <i class="bi bi-shield-fill"></i> Admin
    </a>
    <?php endif; ?>
  </nav>

  <!-- User footer -->
  <div class="im-sidebar-footer">
    <div class="im-user-card" title="Chiqish uchun bosing"
         onclick="window.location='<?= $base ?>logout.php'">
      <div class="im-user-avatar">
        <?= mb_strtoupper(mb_substr($im_ism ?? 'A', 0, 1, 'UTF-8')) ?>
      </div>
      <div>
        <div class="im-user-name"><?= im_f($im_ism ?? '') ?></div>
        <div class="im-user-role"><?= im_f(im_rol_nomi($im_rol ?? '')) ?></div>
      </div>
      <i class="bi bi-box-arrow-right" style="color:rgba(255,255,255,.4);margin-left:auto"></i>
    </div>
  </div>
</div>
