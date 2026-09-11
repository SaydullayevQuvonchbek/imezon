<?php
// To'g'ridan-to'g'ri HTTP so'rovdan himoya — bu fayl faqat
// sahifa ichidan require qilinadi (ai_lib.php / tts_lib.php dagidek).
if (!defined('im_VERSION')) { http_response_code(404); exit; }
// Admin navbar
$_nb = defined('im_BASE') ? im_BASE : '/';
$admin_menu = [
    // ── Asosiy ──
    ['href'=> $_nb.'admin/index.php',           'icon'=>'speedometer2',    'label'=>'Dashboard'],
    ['href'=> $_nb.'admin/ai.php',              'icon'=>'stars',           'label'=>'AI Yordamchi', 'color'=>'#7c3aed'],
    ['href'=> $_nb.'admin/analitika.php',       'icon'=>'graph-up-arrow',  'label'=>'Dinamika'],

    // ── Moliya ──
    ['separator'=>true, 'label'=>'Moliya'],
    ['href'=> $_nb.'admin/balans.php',           'icon'=>'wallet2',         'label'=>'Balans'],
    ['href'=> $_nb.'admin/kapital-kiritish.php', 'icon'=>'wallet-fill',     'label'=>'Kapital Kiritish'],
    ['href'=> $_nb.'admin/pul-olish.php',        'icon'=>'cash-coin',       'label'=>'Pul Olish'],
    ['href'=> $_nb.'admin/inkasasiya.php',       'icon'=>'safe-fill',       'label'=>'Inkasasiya'],
    ['href'=> $_nb.'admin/valyuta.php',          'icon'=>'currency-exchange','label'=>'Valyuta kursi'],
    ['href'=> $_nb.'admin/harajatlar.php',       'icon'=>'receipt-cutoff',  'label'=>'Harajatlar'],

    // ── Sotuvlar ──
    ['separator'=>true, 'label'=>'Sotuvlar'],
    ['href'=> $_nb.'admin/sotuvlar.php',         'icon'=>'bag-check-fill',  'label'=>'Sotuvlar tarixi'],
    ['href'=> $_nb.'admin/zal-hisobot.php',      'icon'=>'grid-3x3-gap-fill','label'=>'Stol / Ofitsant'],
    ['href'=> $_nb.'admin/smenalar.php',         'icon'=>'clock-fill',      'label'=>'Smenalar'],
    ['href'=> $_nb.'admin/qarzlar.php',          'icon'=>'truck-flatbed',   'label'=>'Kontragentlar'],
    ['href'=> $_nb.'admin/kontragent-munosabat.php','icon'=>'diagram-3-fill','label'=>'Munosabatlar'],
    ['href'=> $_nb.'admin/voucher.php',          'icon'=>'ticket-perforated-fill','label'=>'Voucher'],

    // ── Mijozlar ──
    ['separator'=>true, 'label'=>'Mijozlar'],
    ['href'=> $_nb.'admin/mijozlar.php',         'icon'=>'person-lines-fill','label'=>'Mijozlar'],
    ['href'=> $_nb.'admin/mijoz-toifalari.php',  'icon'=>'tags-fill',       'label'=>'Toifalar'],

    // ── Xodimlar ──
    ['separator'=>true, 'label'=>'Xodimlar'],
    ['href'=> $_nb.'admin/xodimlar.php',         'icon'=>'people-fill',     'label'=>'Xodimlar'],
    ['href'=> $_nb.'admin/workers.php',          'icon'=>'person-badge-fill','label'=>'Ishchilar'],
    ['href'=> $_nb.'admin/maosh.php',            'icon'=>'cash-stack',      'label'=>'Maosh berish'],
    ['href'=> $_nb.'admin/xodim-tarixi.php',     'icon'=>'journal-text',    'label'=>'Xodim tarixi'],

    // ── Tizim ──
    ['separator'=>true, 'label'=>'Tizim'],
    ['href'=> $_nb.'admin/filiallar.php',        'icon'=>'building',        'label'=>'Filiallar'],
    ['href'=> $_nb.'admin/inventar.php',         'icon'=>'boxes',           'label'=>'Inventar'],
    ['href'=> $_nb.'admin/eksport.php',          'icon'=>'file-earmark-spreadsheet-fill','label'=>'Eksport'],
    ['href'=> $_nb.'admin/istoriya.php',         'icon'=>'clock-history',   'label'=>'Istoriya'],
    ['href'=> $_nb.'admin/sozlamalar.php',       'icon'=>'gear-fill',       'label'=>'Sozlamalar'],

    // ── Modullar ──
    ['separator'=>true, 'label'=>"Modullar"],
    ['href'=> $_nb.'sklad/index.php',            'icon'=>'box-seam-fill',   'label'=>'Sklad'],
    ['href'=> $_nb.'dukon/index.php',            'icon'=>'bag-fill',        'label'=>"Do'kon"],
    ['href'=> $_nb.'oshpaz/index.php',           'icon'=>'fire',            'label'=>'Oshxona', 'color'=>'#ef4444'],
    ['href'=> $_nb.'qayta-ishlash/index.php',    'icon'=>'gear-wide-connected','label'=>'Qayta Ishlash', 'color'=>'#7c3aed'],
];
// ─── BOSH KASSIR menyusi ─────────────────────────────────────
// Xuddi shu navbar ikki rolga xizmat qiladi. Sahifalarni nusxalash
// O'RNIGA menyu qisqartiriladi — sahifalarning o'zi im_rol_check()
// bilan ikkala rolni ham qabul qiladi. (dukon/dukon2 tajribasi:
// nusxa olingan modul vaqt o'tib ajralib ketadi va tuzatilmay qoladi.)
$kassir_menu = [
    ['href'=> $_nb.'kassa/index.php',      'icon'=>'safe2-fill',      'label'=>'Kassa holati'],

    ['separator'=>true, 'label'=>'Pul harakati'],
    ['href'=> $_nb.'admin/inkasasiya.php', 'icon'=>'box-arrow-in-down','label'=>"Do'konlardan qabul", 'color'=>'#16a34a'],
    ['href'=> $_nb.'admin/balans.php',     'icon'=>'wallet2',         'label'=>'Balans'],
    ['href'=> $_nb.'admin/harajatlar.php', 'icon'=>'receipt-cutoff',  'label'=>'Harajatlar'],
    ['href'=> $_nb.'admin/maosh.php',      'icon'=>'cash-stack',      'label'=>'Oylik berish'],

    ['separator'=>true, 'label'=>'Qarzlar'],
    ['href'=> $_nb.'admin/qarzlar.php',    'icon'=>'truck-flatbed',   'label'=>'Kontragentlar'],

    ['separator'=>true, 'label'=>'Hisobot'],
    ['href'=> $_nb.'admin/analitika.php',  'icon'=>'graph-up-arrow',  'label'=>'Dinamika'],
    ['href'=> $_nb.'admin/sotuvlar.php',   'icon'=>'bag-check-fill',  'label'=>'Kunlik tushum'],
    ['href'=> $_nb.'admin/smenalar.php',   'icon'=>'clock-fill',      'label'=>'Smenalar'],
];

$im_menu_rol = $_SESSION['im_rol'] ?? '';
if ($im_menu_rol === 'bosh_kassir') {
    $admin_menu   = $kassir_menu;
    $im_panel_nom = 'Kassa paneli';
    $im_panel_bel = '🏦';
} else {
    $im_panel_nom = 'Admin panel';
    $im_panel_bel = '🏠';
}

?>
<aside class="im-sidebar" id="im-sidebar">
  <div class="im-brand">
    <div class="im-brand-logo"><?= $im_panel_bel ?></div>
    <div class="im-brand-text">
      <div class="im-brand-name">IMezon</div>
      <div class="im-brand-sub"><?= im_f($im_panel_nom) ?></div>
    </div>
  </div>
  <nav class="im-nav">
    <?php foreach ($admin_menu as $item): ?>
    <?php if (!empty($item['separator'])): ?>
    <?php if (!empty($item['label'])): ?>
    <div class="im-nav-label"><?= $item['label'] ?></div>
    <?php else: ?>
    <div style="border-top:1px solid rgba(255,255,255,.07);margin:6px 16px;"></div>
    <?php endif; ?>
    <?php continue; endif; ?>
    <?php $active = im_nav_aktiv($item['href']) ? ' active' : ''; ?>
    <?php $clr = $item['color'] ?? ''; ?>
    <?php $clr_bg = $active ? "{$clr}33" : 'rgba(124,58,237,.12)'; ?>
    <a href="<?= $item['href'] ?>" class="im-nav-item<?= $active ?>"
       <?= $clr ? "style=\"background:linear-gradient(90deg,{$clr_bg},transparent);border-left:3px solid {$clr}\"" : '' ?>>
      <i class="bi bi-<?= $item['icon'] ?>" <?= $clr ? "style=\"color:{$clr}\"" : '' ?>></i>
      <span <?= $clr ? "style=\"color:{$clr};font-weight:600\"" : '' ?>><?= $item['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="im-sidebar-footer">
    <div class="im-user-card">
      <div class="im-user-avatar"><?= mb_strtoupper(mb_substr($im_ism ?? 'A', 0, 1)) ?></div>
      <div class="im-user-info">
        <div class="im-user-name"><?= im_f($im_ism ?? 'Admin') ?></div>
        <div class="im-user-role"><?= im_f(im_rol_nomi($im_menu_rol)) ?></div>
      </div>
      <a href="<?= defined('im_BASE') ? im_BASE : '/' ?>logout.php" class="im-btn im-btn-icon im-btn-ghost" title="Chiqish">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</aside>
