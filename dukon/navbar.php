<?php
// To'g'ridan-to'g'ri HTTP so'rovdan himoya — bu fayl faqat
// sahifa ichidan require qilinadi (ai_lib.php / tts_lib.php dagidek).
if (!defined('im_VERSION')) { http_response_code(404); exit; }
// ============================================================
//  IMezon — Do'kon Navbar
// ============================================================
$base = defined('im_BASE') ? im_BASE : '/';
// Joriy sahifa aktivmi — YAGONA MANBA (config.php → im_nav_aktiv()).
$akt = function ($yol) use ($base) {
    return im_nav_aktiv($base . $yol) ? 'active' : '';
};

$smena_aktiv = false;
if (isset($db) && isset($im_user_id)) {
    $smena_aktiv = (bool)$db->val(
        "SELECT id FROM im_smena WHERE kassir_id=$im_user_id AND holat='ochiq' LIMIT 1"
    );
}
?>
<nav class="im-sidebar" id="im-sidebar">
  <div class="im-sidebar-header">
    <div class="im-brand">
      <div class="im-brand-icon">🛍️</div>
      <div class="im-brand-text">
        <div class="im-brand-name">IMezon</div>
        <div class="im-brand-sub">Do'kon moduli</div>
      </div>
    </div>
  </div>

  <div class="im-sidebar-body">

    <div class="im-nav-section">ASOSIY</div>
    <a href="<?= $base ?>dukon/index.php"
       class="im-nav-item <?= $akt('dukon/index.php') ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="<?= $base ?>dukon/pos.php"
       class="im-nav-item <?= $akt('dukon/pos.php') ?>">
      <i class="bi bi-bag-fill"></i> POS Sotuv
      <?php if ($smena_aktiv): ?>
      <span class="ms-auto im-badge im-badge-success" style="font-size:9px">aktiv</span>
      <?php endif; ?>
    </a>
    <a href="<?= $base ?>dukon/vitrina.php"
       class="im-nav-item <?= $akt('dukon/vitrina.php') ?>">
      <i class="bi bi-shop-window"></i> Vitrina (Narxlar)
    </a>
    <?php $setlar_aktiv = $akt('dukon/setlar.php'); ?>
    <a href="<?= $base ?>dukon/setlar.php"
       class="im-nav-item <?= $setlar_aktiv ?>">
      <i class="bi bi-gift-fill" style="color:#b8860b"></i>
      <span style="color:<?= $setlar_aktiv ? '' : '#b8860b' ?>;font-weight:600">Setlar</span>
    </a>
    <a href="<?= $base ?>dukon/qoldiq.php"
       class="im-nav-item <?= $akt('dukon/qoldiq.php') ?>">
      <i class="bi bi-boxes"></i> Qoldiqlar &amp; Sotuvlar
    </a>
    <a href="<?= $base ?>dukon/stollar.php"
       class="im-nav-item <?= $akt('dukon/stollar.php') ?>">
      <i class="bi bi-grid-3x3-gap-fill"></i> Stollar
    </a>
    <a href="<?= $base ?>oshpaz/qozon.php"
       class="im-nav-item <?= $akt('oshpaz/qozon.php') ?>">
      <i class="bi bi-fire" style="color:#f97316"></i> Osh qozoni
    </a>

    <div class="im-nav-section">MOLIYA</div>
    <a href="<?= $base ?>dukon/nasiya.php"
       class="im-nav-item <?= $akt('dukon/nasiya.php') ?>">
      <i class="bi bi-credit-card-2-back-fill"></i> Nasiya
    </a>
    <a href="<?= $base ?>dukon/vozvrat.php"
       class="im-nav-item <?= $akt('dukon/vozvrat.php') ?>">
      <i class="bi bi-arrow-return-left"></i> Qaytarish
    </a>
    <a href="<?= $base ?>dukon/harajat.php"
       class="im-nav-item <?= $akt('dukon/harajat.php') ?>">
      <i class="bi bi-cash-stack"></i> Harajatlar
    </a>
    <a href="<?= $base ?>dukon/qarzlar.php"
       class="im-nav-item <?= $akt('dukon/qarzlar.php') ?>">
      <i class="bi bi-truck"></i> Postavshik qarzlari
    </a>
    <a href="<?= $base ?>dukon/inkasasiya.php"
       class="im-nav-item <?= $akt('dukon/inkasasiya.php') ?>">
      <i class="bi bi-safe-fill"></i> Inkasasiya (Pul topshirish)
    </a>
    <a href="<?= $base ?>dukon/smenalar.php"
       class="im-nav-item <?= $akt('dukon/smenalar.php') ?>">
      <i class="bi bi-clock-history"></i> Smenalar tarixi
    </a>
    <a href="<?= $base ?>dukon/mijozlar.php"
       class="im-nav-item <?= $akt('dukon/mijozlar.php') ?>">
      <i class="bi bi-people-fill"></i> Mijozlar
    </a>
    <a href="<?= $base ?>dukon/sotuvlar.php"
       class="im-nav-item <?= $akt('dukon/sotuvlar.php') ?>">
      <i class="bi bi-receipt-cutoff"></i> Sotuvlar tarixi
    </a>
    <a href="<?= $base ?>dukon/hisobot.php"
       class="im-nav-item <?= $akt('dukon/hisobot.php') ?>">
      <i class="bi bi-bar-chart-fill"></i> Hisobot
    </a>

    <div class="im-nav-section">ADMIN</div>
    <a href="<?= $base ?>sklad/index.php" class="im-nav-item">
      <i class="bi bi-boxes"></i> Sklad
    </a>
    <a href="<?= $base ?>qayta-ishlash/index.php" class="im-nav-item"
       style="background:linear-gradient(90deg,rgba(124,58,237,.12),transparent);border-left:3px solid #7c3aed">
      <i class="bi bi-gear-wide-connected" style="color:#7c3aed"></i>
      <span style="color:#7c3aed;font-weight:600">Qayta Ishlash</span>
    </a>
    <?php if (isset($im_rol) && $im_rol === 'admin'): ?>
    <a href="<?= $base ?>admin/index.php" class="im-nav-item">
      <i class="bi bi-shield-fill"></i> Admin panel
    </a>
    <?php endif; ?>
  </div>

  <div class="im-sidebar-footer">
    <div class="im-user-card">
      <div class="im-user-avatar">
        <?= mb_strtoupper(mb_substr($im_ism ?? 'A', 0, 1, 'UTF-8'), 'UTF-8') ?>
      </div>
      <div class="im-user-info">
        <div class="im-user-name"><?= im_f($im_ism ?? '') ?></div>
        <div class="im-user-role"><?= im_f(im_rol_nomi($im_rol ?? '')) ?></div>
      </div>
      <a href="<?= $base ?>logout.php" class="im-topbar-btn ms-auto" title="Chiqish">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>
