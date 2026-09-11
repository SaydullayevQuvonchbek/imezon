<?php
// ============================================================
//  IMezon — Dukon: Sotuv Hisoboti (Sana oralig'i)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

// ── Filtrlar ─────────────────────────────────────────────
$dan   = $_GET['dan']   ?? date('Y-m-01');
$gacha = $_GET['gacha'] ?? date('Y-m-d');
$dan   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dan)   ? $dan   : date('Y-m-01');
$gacha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $gacha) ? $gacha : date('Y-m-d');
$ds    = mysqli_real_escape_string($link, $dan);
$gs    = mysqli_real_escape_string($link, $gacha);

// Kassir filtri — kassir faqat o'zini ko'radi
$kfilter    = ($im_rol !== 'admin') ? " AND s.kassir_id=$im_user_id" : '';
$kfilter_nh = ($im_rol !== 'admin') ? " AND kassir_id=$im_user_id" : '';

// ── Asosiy statistika ─────────────────────────────────────
$stats = $db->row(
    "SELECT COUNT(*)                      AS sotuv_soni,
            COALESCE(SUM(tolov_summa),0)  AS jami_summa,
            COALESCE(SUM(naqd_summa),0)   AS naqd,
            COALESCE(SUM(karta_summa),0)  AS karta,
            COALESCE(SUM(bank_summa),0)   AS bank,
            COALESCE(SUM(usd_summa*usd_kurs),0) AS usd_som,
            COALESCE(SUM(nasiya_summa),0) AS nasiya,
            COALESCE(SUM(chegirma_summa),0) AS chegirma,
            COALESCE(SUM(xizmat_summa),0)   AS xizmat
     FROM im_sotuvlar
     WHERE DATE(sana) BETWEEN '$ds' AND '$gs' $kfilter_nh"
);

// USD qaytim — to'g'ridan-to'g'ri sotuvlardan
$usd_qaytim = (float)$db->val(
    "SELECT COALESCE(SUM(usd_qaytim_som),0) FROM im_sotuvlar
     WHERE DATE(sana) BETWEEN '$ds' AND '$gs' $kfilter_nh"
);
$naqd_qaytim = (float)$db->val(
    "SELECT COALESCE(SUM(naqd_berildi),0) FROM im_sotuvlar
     WHERE DATE(sana) BETWEEN '$ds' AND '$gs' $kfilter_nh"
);
$stats['naqd'] -= $usd_qaytim;

// Harajatlar
$harajat = (float)$db->val(
    "SELECT COALESCE(SUM(summa), 0) FROM im_harajatlar
     WHERE DATE(sana) BETWEEN '$ds' AND '$gs'"
    . ($im_rol !== 'admin' ? " AND xodim_id=$im_user_id" : '')
);

// Vozvrat
$vozvrat = (float)$db->val(
    "SELECT COALESCE(SUM(v.qaytarish_summa),0) FROM im_vozvratlar v
     JOIN im_sotuvlar s ON s.id=v.sotuv_id
     WHERE DATE(s.sana) BETWEEN '$ds' AND '$gs' $kfilter"
);

// ── Sof sotuv ──
$sotuv_netto = (float)$stats['jami_summa'] - $vozvrat;

// ── Kunlik sotuv dinamikasi ───────────────────────────────
$kunlik = $db->rows(
    "SELECT DATE(sana) AS kun,
            COUNT(*) AS soni,
            COALESCE(SUM(tolov_summa),0) AS summa,
            COALESCE(SUM(naqd_summa),0)  AS naqd,
            COALESCE(SUM(karta_summa),0) AS karta,
            COALESCE(SUM(bank_summa),0)  AS bank,
            COALESCE(SUM(usd_summa*usd_kurs),0) AS usd_som,
            COALESCE(SUM(nasiya_summa),0) AS nasiya
     FROM im_sotuvlar
     WHERE DATE(sana) BETWEEN '$ds' AND '$gs' $kfilter_nh
     GROUP BY DATE(sana)
     ORDER BY kun ASC"
);

$kunlik_qaytim_rows = $db->rows(
    "SELECT DATE(sana) AS kun,
            COALESCE(SUM(usd_qaytim_som),0) AS usd_qaytim,
            COALESCE(SUM(naqd_berildi),0) AS naqd_qaytim
     FROM im_sotuvlar
     WHERE DATE(sana) BETWEEN '$ds' AND '$gs' $kfilter_nh
     GROUP BY DATE(sana)"
);
$kun_qaytim = [];
foreach ($kunlik_qaytim_rows as $kr) {
    $kun_qaytim[$kr['kun']] = [
        'usd'  => (float)$kr['usd_qaytim'],
        'naqd' => (float)$kr['naqd_qaytim'],
    ];
}

foreach ($kunlik as &$k) {
   $q = $kun_qaytim[$k['kun']] ?? ['usd'=>0,'naqd'=>0];
   $k['naqd']       -= $q['usd']; // USD qaytim naqd kassadan chiqadi
   $k['usd_qaytim']  = $q['usd'];
   $k['naqd_qaytim'] = $q['naqd'];
}
unset($k);

// ── Top mahsulotlar ───────────────────────────────────────
$top_mah = $db->rows(
    "SELECT m.nomi, SUM(si.soni) AS dona,
            SUM(si.chegirma_narxi * si.soni) AS summa
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id=si.sotuv_id
     JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
     WHERE DATE(s.sana) BETWEEN '$ds' AND '$gs' $kfilter
     GROUP BY si.mahsulot_id
     ORDER BY summa DESC
     LIMIT 10"
);

// ── Kassirlar bo'yicha (faqat admin) ─────────────────────
$kassir_stat = [];
if ($im_rol === 'admin') {
    $kassir_stat = $db->rows(
        "SELECT s.kassir_id, x.ism AS kassir,
                COUNT(*) AS soni,
                COALESCE(SUM(s.tolov_summa),0) AS summa
         FROM im_sotuvlar s
         LEFT JOIN im_xodimlar x ON x.id=s.kassir_id
         WHERE DATE(s.sana) BETWEEN '$ds' AND '$gs'
         GROUP BY s.kassir_id
         ORDER BY summa DESC"
    );
}

// Shortcutlar
$shortcuts = [
    'Bugun'      => [date('Y-m-d'), date('Y-m-d')],
    'Kecha'      => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
    'Bu hafta'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
    'Bu oy'      => [date('Y-m-01'), date('Y-m-d')],
    "O'tgan oy"  => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
];
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Sotuv Hisoboti | IMezon Do'kon</title>
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
    <div class="im-page-title"><i class="bi bi-bar-chart-fill me-1"></i> Sotuv Hisoboti</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>

  <main class="im-content">

    <!-- ── Sana filtri ─────────────────────────────────── -->
    <div class="im-card mb-4">
      <div class="im-card-body p-3">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
          <div>
            <label class="im-label mb-1" style="font-size:11px">Dan</label>
            <input type="date" name="dan" class="im-input im-input-sm" value="<?= $dan ?>" style="max-width:145px">
          </div>
          <div>
            <label class="im-label mb-1" style="font-size:11px">Gacha</label>
            <input type="date" name="gacha" class="im-input im-input-sm" value="<?= $gacha ?>" style="max-width:145px">
          </div>
          <button type="submit" class="im-btn im-btn-dark im-btn-sm"><i class="bi bi-filter"></i> Ko'rsat</button>
          <div class="d-flex gap-1 flex-wrap ms-2">
            <?php foreach ($shortcuts as $lbl => [$d1, $d2]):
                $active = ($dan === $d1 && $gacha === $d2) ? 'im-btn-primary' : 'im-btn-outline'; ?>
            <a href="?dan=<?= $d1 ?>&gacha=<?= $d2 ?>" class="im-btn im-btn-sm <?= $active ?>"><?= $lbl ?></a>
            <?php endforeach; ?>
          </div>
        </form>
        <div class="mt-2 text-muted fs-xs">
          <i class="bi bi-calendar3"></i>
          <?= $dan === $gacha ? im_date($dan) : im_date($dan) . ' — ' . im_date($gacha) ?>
        </div>
      </div>
    </div>

    <!-- ── Joriy Kassa Holati ─────────────────────────────────── -->
    <?php 
      $filial_id_db = $im_filial_id ?: 1;
      $kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=$filial_id_db"); 
    ?>
    <div class="im-card mb-4">
      <div class="im-card-header" style="background:var(--success);color:#fff;border-radius:10px 10px 0 0">
         <i class="bi bi-safe-fill text-white"></i> 
         <span class="im-card-title text-white">Kassa (Joriy qoldiqlar)</span>
      </div>
      <div class="im-card-body p-3">
         <div class="row g-3">
            <div class="col-6 col-md-3">
               <div class="text-muted fs-xs mb-1">💵 Naqd pul</div>
               <div class="fw-bold num text-success" style="font-size:18px"><?= im_money((float)($kassa['naqd_balans'] ?? 0)) ?></div>
            </div>
            <div class="col-6 col-md-3">
               <div class="text-muted fs-xs mb-1"><?= im_tt_label('karta', true) ?></div>
               <div class="fw-bold num text-primary" style="font-size:18px"><?= im_money((float)($kassa['karta_balans'] ?? 0)) ?></div>
            </div>
            <div class="col-6 col-md-3">
               <div class="text-muted fs-xs mb-1"><?= im_tt_label('bank', true) ?></div>
               <div class="fw-bold num text-info" style="font-size:18px"><?= im_money((float)($kassa['bank_balans'] ?? 0)) ?></div>
            </div>
            <div class="col-6 col-md-3">
               <div class="text-muted fs-xs mb-1">🪙 USD</div>
               <div class="fw-bold num" style="color:#b8860b;font-size:18px">$<?= number_format((float)($kassa['usd_balans'] ?? 0), 2) ?></div>
            </div>
         </div>
      </div>
    </div>

    <!-- ── Asosiy stat kartalar ────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-4">
        <div class="im-stat-card" style="border-left:4px solid var(--accent-dark)">
          <div class="im-stat-icon" style="background:rgba(226,185,111,.15);color:var(--accent-dark)"><i class="bi bi-bag-fill"></i></div>
          <div>
            <div class="im-stat-label">Jami sotuv</div>
            <div class="im-stat-value num"><?= im_money($stats['jami_summa']) ?> so'm</div>
            <div class="text-muted fs-xs"><?= (int)$stats['sotuv_soni'] ?> ta chek</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i class="bi bi-cash-stack"></i></div>
          <div>
            <div class="im-stat-label">Sof sotuv</div>
            <div class="im-stat-value num" style="color:var(--success)"><?= im_money($sotuv_netto) ?> so'm</div>
            <div class="text-muted fs-xs">Chegirma: -<?= im_money($stats['chegirma']) ?></div>
            <?php if ((float)$stats['xizmat'] > 0): ?>
              <div class="fs-xs" style="color:#0d6efd">Xizmat haqi: +<?= im_money($stats['xizmat']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.12);color:var(--danger)"><i class="bi bi-receipt"></i></div>
          <div>
            <div class="im-stat-label">Harajatlar</div>
            <div class="im-stat-value num" style="color:var(--danger)"><?= im_money($harajat) ?> so'm</div>
            <div class="text-muted fs-xs">Kunlik chiqimlar</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── To'lov turlari ─────────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-md-5">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-pie-chart-fill" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">To'lov turlari</span>
          </div>
          <div class="im-card-body p-4">
            <?php
            $pay_types = [
                ['💵 Naqd',   $stats['naqd'],   'success'],
                [im_tt_label('karta', true),  $stats['karta'],  'primary'],
                [im_tt_label('bank', true),   $stats['bank'],   'info'],
                ['🪙 USD',    $stats['usd_som'],'accent-dark'],
                ['📋 Nasiya', $stats['nasiya'], 'warning'],
            ];
            $max_sum = max((float)$stats['jami_summa'], 1);
            foreach ($pay_types as [$lbl, $sum, $color]):
                if ((float)$sum <= 0) continue;
                $pct = round($sum / $max_sum * 100);
            ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between mb-1">
                <span class="fw-semibold fs-sm"><?= $lbl ?></span>
                <span class="fw-bold num"><?= im_money($sum) ?> <span class="text-muted fs-xs">(<?= $pct ?>%)</span></span>
              </div>
              <div style="background:var(--border);border-radius:4px;height:8px">
                <div style="width:<?= $pct ?>%;background:var(--<?= $color ?>);border-radius:4px;height:8px;transition:width .5s"></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if ($vozvrat > 0): ?>
            <div class="mt-2 p-2 rounded" style="background:rgba(220,53,69,.06);border:1px dashed var(--danger)">
              <span class="text-danger fs-sm"><i class="bi bi-arrow-return-left"></i> Vozvrat: <strong><?= im_money($vozvrat) ?> so'm</strong></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Top mahsulotlar -->
      <div class="col-md-7">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-trophy-fill" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Top 10 mahsulot</span>
          </div>
          <?php if ($top_mah): ?>
          <div class="im-table-wrap">
            <table class="im-table">
              <thead><tr><th>#</th><th>Mahsulot</th><th class="text-right">Dona</th><th class="text-right">Summa</th></tr></thead>
              <tbody>
                <?php foreach ($top_mah as $i => $tm): ?>
                <tr>
                  <td class="text-muted fs-xs"><?= $i + 1 ?></td>
                  <td class="fw-semibold fs-sm"><?= im_f($tm['nomi']) ?></td>
                  <td class="text-right"><?= (int)$tm['dona'] ?></td>
                  <td class="text-right num fw-bold" style="color:var(--primary)"><?= im_money($tm['summa']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="im-empty py-3"><i class="bi bi-bag"></i><p class="text-muted fs-sm">Bu davr uchun sotuv yo'q</p></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($im_rol === 'admin' && $kassir_stat): ?>
    <!-- ── Kassirlar bo'yicha (admin) ─────────────────── -->
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-person-badge-fill" style="color:var(--primary)"></i>
        <span class="im-card-title">Kassirlar bo'yicha sotuv</span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr><th>Kassir</th><th class="text-right">Sotuv soni</th><th class="text-right">Jami summa</th></tr></thead>
          <tbody>
            <?php foreach ($kassir_stat as $ks): ?>
            <tr>
              <td class="fw-semibold"><?= im_f($ks['kassir'] ?: "Kassir #{$ks['kassir_id']}") ?></td>
              <td class="text-right"><?= (int)$ks['soni'] ?> ta</td>
              <td class="text-right num fw-bold" style="color:var(--primary)"><?= im_money($ks['summa']) ?> so'm</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Kunlik dinamika ────────────────────────────── -->
    <?php if ($kunlik): ?>
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-calendar-week-fill" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Kunlik sotuv dinamikasi</span>
        <span class="im-badge im-badge-muted ms-1"><?= count($kunlik) ?> kun</span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>Sana</th>
              <th class="text-center">Cheklar</th>
              <th class="text-right">💵 Naqd</th>
              <th class="text-right"><?= im_tt_label('karta', true) ?></th>
              <th class="text-right"><?= im_tt_label('bank', true) ?></th>
              <th class="text-right">🪙 USD</th>
              <th class="text-right" style="color:#dc3545">🔄 USD Qaytim</th>
              <th class="text-right">📋 Nasiya</th>
              <th class="text-right fw-bold">Jami</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $k_jami_soni = $k_jami_naqd = $k_jami_karta = $k_jami_bank = $k_jami_usd = $k_jami_nasiya = $k_jami = 0;
            foreach ($kunlik as $k):
                $k_jami_soni   += (int)$k['soni'];
                $k_jami_naqd   += (float)$k['naqd'];
                $k_jami_karta  += (float)$k['karta'];
                $k_jami_bank   += (float)$k['bank'];
                $k_jami_usd    += (float)$k['usd_som'];
                $k_jami_nasiya += (float)$k['nasiya'];
                $k_jami        += (float)$k['summa'];
            ?>
            <tr>
              <td class="fw-semibold"><?= im_date($k['kun']) ?></td>
              <td class="text-center"><span class="im-badge im-badge-muted"><?= (int)$k['soni'] ?> ta</span></td>
              <td class="text-right num" style="color:var(--success)"><?= im_money($k['naqd']) ?></td>
              <td class="text-right num" style="color:var(--primary)"><?= im_money($k['karta']) ?></td>
              <td class="text-right num" style="color:var(--info)"><?= im_money($k['bank']) ?></td>
              <td class="text-right num" style="color:#b8860b"><?= im_money($k['usd_som']) ?></td>
              <td class="text-right num" style="color:#dc3545">
                <?= (float)($k['usd_qaytim'] ?? 0) > 0 ? '-'.im_money($k['usd_qaytim']).' so\'m' : '—' ?>
              </td>
              <td class="text-right num" style="color:var(--warning)"><?= (float)$k['nasiya'] > 0 ? im_money($k['nasiya']) : '—' ?></td>
              <td class="text-right num fw-bold" style="color:var(--accent-dark)"><?= im_money($k['summa']) ?> so'm</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot style="background:var(--bg);font-weight:700">
            <tr>
              <td class="fw-bold">Jami</td>
              <td class="text-center"><?= $k_jami_soni ?> ta</td>
              <td class="text-right num"><?= im_money($k_jami_naqd) ?></td>
              <td class="text-right num"><?= im_money($k_jami_karta) ?></td>
              <td class="text-right num"><?= im_money($k_jami_bank) ?></td>
              <td class="text-right num"><?= im_money($k_jami_usd) ?></td>
              <td class="text-right num" style="color:#dc3545">
                <?php
                  $f_usd_q = array_sum(array_column($kunlik, 'usd_qaytim'));
                  echo $f_usd_q > 0 ? '-'.im_money($f_usd_q).' so\'m' : '—';
                ?>
              </td>
              <td class="text-right num"><?= im_money($k_jami_nasiya) ?></td>
              <td class="text-right num fw-bold fs-6" style="color:var(--accent-dark)"><?= im_money($k_jami) ?> so'm</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="im-empty"><i class="bi bi-bar-chart"></i><h4>Sotuv yo'q</h4><p class="text-muted">Tanlangan davr uchun sotuv amalga oshirilmagan</p></div>
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
