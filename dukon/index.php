<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'kassir']);
$db = new Cyber();

$today = date('Y-m-d');
$sana = $_GET['sana'] ?? $today;
$sd = mysqli_real_escape_string($link, $sana);

// Sotuv statistikasi (joriy filial)
$filial_filter = $im_rol === 'admin' ? '' : "AND filial_id=$im_filial_id";

$sotuv_jami = (float) $db->val("SELECT COALESCE(SUM(tolov_summa),0) FROM im_sotuvlar WHERE DATE(sana)='$sd' $filial_filter");
$sotuv_naqd = (float) $db->val("SELECT COALESCE(SUM(naqd_summa),0) FROM im_sotuvlar WHERE DATE(sana)='$sd' $filial_filter");
$sotuv_karta = (float) $db->val("SELECT COALESCE(SUM(karta_summa),0) FROM im_sotuvlar WHERE DATE(sana)='$sd' $filial_filter");
$sotuv_bank = (float) $db->val("SELECT COALESCE(SUM(bank_summa),0) FROM im_sotuvlar WHERE DATE(sana)='$sd' $filial_filter");
$sotuv_usd_som = (float) $db->val("SELECT COALESCE(SUM(usd_som_ekviv),0) FROM im_sotuvlar WHERE DATE(sana)='$sd' $filial_filter");
$sotuv_usd = (float) $db->val("SELECT COALESCE(SUM(usd_summa),0) FROM im_sotuvlar WHERE DATE(sana)='$sd' $filial_filter");
$sotuv_nasiya = (float) $db->val("SELECT COALESCE(SUM(nasiya_summa),0) FROM im_sotuvlar WHERE DATE(sana)='$sd' $filial_filter");
$sotuv_chegirma = (float) $db->val("SELECT COALESCE(SUM(chegirma_summa),0) FROM im_sotuvlar WHERE DATE(sana)='$sd' $filial_filter");
$sotuv_count = (int) $db->val("SELECT COUNT(*) FROM im_sotuvlar WHERE DATE(sana)='$sd' $filial_filter");

// USD orqali qaytarilgan summa sotuvning o'zida saqlanadi. Balans jurnaliga
// bog'lanmaslik kerak: jurnal kategoriyasi buzilsa ham smena tushumi to'g'ri qoladi.
$sotuv_usd_qaytim = (float) $db->val(
    "SELECT COALESCE(SUM(usd_qaytim_som),0) FROM im_sotuvlar
     WHERE DATE(sana)='$sd' $filial_filter"
);
$sotuv_naqd -= $sotuv_usd_qaytim;


// Vozvrat (qaytarilgan mahsulotlar) — sotuvdan ayiriladi
$vozvrat_jami = (float) $db->val(
  "SELECT COALESCE(SUM(v.qaytarish_summa),0) FROM im_vozvratlar v
     JOIN im_sotuvlar s ON s.id=v.sotuv_id
     WHERE DATE(s.sana)='$sd' $filial_filter"
);

// Harajatlar (xarajatlar) — real chiqimlar
$harajat_filter = $im_rol === 'admin' ? '' : "AND filial_id=$im_filial_id";
$harajat_jami = (float) $db->val("SELECT COALESCE(SUM(summa),0) FROM im_harajatlar WHERE sana='$sd' $harajat_filter");

// ── SOF SOTUV = (Sotuv - Vozvrat) ──
$sotuv_netto = $sotuv_jami - $vozvrat_jami;

// Sotuvlar ro'yxati.
// MUHIM: bu yerda im_xodimlar (o) JOIN qilingan — unda ham filial_id bor,
// shuning uchun $filial_filter dagi alияsiz "filial_id" AMBIGUOUS bo'lib
// so'rov jimgina yiqilardi va kassirning ro'yxati DOIM bo'sh chiqardi.
$fil_s = $im_rol === 'admin' ? '' : "AND s.filial_id=" . (int)$im_filial_id;
$sotuvlar = $db->rows(
  "SELECT s.*, m.ism AS mijoz, o.ism AS ofitsant_ism,
            (SELECT COUNT(*) FROM im_sotuv_items WHERE sotuv_id=s.id) AS item_soni
     FROM im_sotuvlar s
     LEFT JOIN im_mijozlar m ON m.id=s.mijoz_id
     LEFT JOIN im_xodimlar o ON o.id=s.sotuvchi_id
     WHERE DATE(s.sana)='$sd' $fil_s
     ORDER BY s.sana DESC"
);

// Top mahsulotlar
$top_mah = $db->rows(
  "SELECT m.nomi, SUM(si.soni) AS sotilgan,
            SUM(si.chegirma_narxi * si.soni) AS summa
     FROM im_sotuv_items si
     JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
     JOIN im_sotuvlar s ON s.id=si.sotuv_id
     WHERE DATE(s.sana)='$sd'
     GROUP BY si.mahsulot_id ORDER BY summa DESC LIMIT 5"
);

// Eng oxirgi Smena ma'lumotlari (Dashboard uchun maxsus)
$joriy_smena = $db->row("SELECT * FROM im_smena ORDER BY id DESC LIMIT 1");
$sm_data = [];
$sm_nasiya_qabul = ['naqd' => 0, 'karta' => 0, 'bank' => 0, 'usd' => 0, 'jami' => 0];
if ($joriy_smena) {
  $sm_data = $db->row("SELECT 
        COUNT(id) as cnt, 
        COALESCE(SUM(tolov_summa),0) as jami, 
        COALESCE(SUM(naqd_summa),0) as naqd, 
        COALESCE(SUM(karta_summa),0) as karta,
        COALESCE(SUM(bank_summa),0) as bank,
        COALESCE(SUM(usd_som_ekviv),0) as usd_som,
        COALESCE(SUM(usd_summa),0) as usd_dollar,
        COALESCE(SUM(nasiya_summa),0) as nasiya
        FROM im_sotuvlar WHERE smena_id={$joriy_smena['id']}");
        
  $sm_usd_qaytim = (float) $db->val(
      "SELECT COALESCE(SUM(usd_qaytim_som),0) FROM im_sotuvlar
       WHERE smena_id={$joriy_smena['id']}"
  );
  $sm_data['naqd'] -= $sm_usd_qaytim;

  // Haqiqiy kassa tushumi = Naqd + Kart-karta + Bank + USD so'm ekvivalenti (sotuvdan)
  $sm_data['kassa_tushum'] = ($sm_data['naqd'] ?? 0) + ($sm_data['karta'] ?? 0) + ($sm_data['bank'] ?? 0) + ($sm_data['usd_som'] ?? 0);

  // ── Smena davomidagi nasiya to'lovlari (qarz qaytarimlari — kassaga haqiqiy tushum) ──
  $sm_start_e = mysqli_real_escape_string($link, $joriy_smena['ochildi'] ?? $joriy_smena['boshlanish'] ?? date('Y-m-d'));
  $sm_end_e = $joriy_smena['yopildi'] ? mysqli_real_escape_string($link, $joriy_smena['yopildi']) : date('Y-m-d H:i:s');
  $sm_nq = $db->row(
    "SELECT COALESCE(SUM(CASE WHEN tolov_turi='naqd' THEN summa ELSE 0 END),0) AS naqd,
              COALESCE(SUM(CASE WHEN tolov_turi='karta' THEN summa ELSE 0 END),0) AS karta,
              COALESCE(SUM(CASE WHEN tolov_turi='bank' THEN summa ELSE 0 END),0) AS bank,
              COALESCE(SUM(CASE WHEN tolov_turi='usd' THEN summa ELSE 0 END),0) AS usd,
              COALESCE(SUM(summa),0) AS jami
       FROM im_nasiya_tolov
       WHERE sana BETWEEN '$sm_start_e' AND '$sm_end_e'"
  );
  if ($sm_nq)
    $sm_nasiya_qabul = $sm_nq;
  // Jami kassa tushumi = sotuvdan + nasiya qabulidan
  $sm_data['kassa_tushum_total'] = $sm_data['kassa_tushum'] + (float) $sm_nasiya_qabul['jami'];
}

// ── Bugungi nasiya to'lovlari (naqd/karta/bank/usd bo'yicha) ──
$nasiya_tolovlar = $db->rows(
  "SELECT nt.tolov_turi,
          COUNT(nt.id) AS soni,
          COALESCE(SUM(nt.summa), 0) AS jami,
          m.ism AS mijoz
   FROM im_nasiya_tolov nt
   JOIN im_nasiya n ON n.id=nt.nasiya_id
   JOIN im_mijozlar m ON m.id=n.mijoz_id
   WHERE DATE(nt.sana)='$sd'
   GROUP BY nt.tolov_turi"
);
// Kunlik nasiya to'lov summasi (turi bo'yicha)
$nas_naqd = 0;
$nas_karta = 0;
$nas_bank = 0;
$nas_usd_som = 0;
foreach ($nasiya_tolovlar as $nt) {
  if ($nt['tolov_turi'] === 'naqd')
    $nas_naqd += (float) $nt['jami'];
  if ($nt['tolov_turi'] === 'karta')
    $nas_karta += (float) $nt['jami'];
  if ($nt['tolov_turi'] === 'bank')
    $nas_bank += (float) $nt['jami'];
  if ($nt['tolov_turi'] === 'usd')
    $nas_usd_som += (float) $nt['jami'];
}
$nas_jami = $nas_naqd + $nas_karta + $nas_bank + $nas_usd_som;

?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | IMezon Do'kon</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSI+PHJlY3Qgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0IiByeD0iMTQiIGZpbGw9IiMwNzA5MGYiLz48cmFkaWFsR3JhZGllbnQgaWQ9ImciIGN4PSI1MCUiIGN5PSIzMCUiIHI9IjYwJSI+PHN0b3Agb2Zmc2V0PSIwJSIgc3RvcC1jb2xvcj0iI2Q0YTg1MyIgc3RvcC1vcGFjaXR5PSIwLjMiLz48c3RvcCBvZmZzZXQ9IjEwMCUiIHN0b3AtY29sb3I9IiMwNzA5MGYiIHN0b3Atb3BhY2l0eT0iMCIvPjwvcmFkaWFsR3JhZGllbnQ+PHJlY3Qgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0IiByeD0iMTQiIGZpbGw9InVybCgjZykiLz48dGV4dCB4PSIzMiIgeT0iNDAiIGZvbnQtZmFtaWx5PSJBcmlhbCBCbGFjayxzYW5zLXNlcmlmIiBmb250LXNpemU9IjI0IiBmb250LXdlaWdodD0iOTAwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjZDRhODUzIj5JTTwvdGV4dD48cmVjdCB4PSIxMyIgeT0iNDYiIHdpZHRoPSIzOCIgaGVpZ2h0PSIzIiByeD0iMS41IiBmaWxsPSIjZDRhODUzIiBvcGFjaXR5PSIwLjgiLz48Y2lyY2xlIGN4PSI1MCIgY3k9IjEzIiByPSI1IiBmaWxsPSIjZDRhODUzIi8+PGNpcmNsZSBjeD0iNTMiIGN5PSIxMSIgcj0iMy41IiBmaWxsPSIjMDcwOTBmIi8+PC9zdmc+">
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
        <div class="im-page-title"><i class="bi bi-speedometer2 me-1"></i> Dashboard</div>
        <div class="im-topbar-actions">
          <button class="im-btn im-btn-outline im-btn-sm" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Chop etish
          </button>
          <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
        </div>
      </header>
      <main class="im-content">

        <!-- Sana nav -->
        <div class="d-flex align-items-center gap-2 mb-4">
          <a href="?sana=<?= date('Y-m-d', strtotime($sana . ' -1 day')) ?>"
            class="im-btn im-btn-outline im-btn-icon"><i class="bi bi-chevron-left"></i></a>
          <input type="date" class="im-input im-input-sm" value="<?= $sana ?>"
            onchange="window.location='?sana='+this.value" style="max-width:160px">
          <a href="?sana=<?= date('Y-m-d', strtotime($sana . ' +1 day')) ?>"
            class="im-btn im-btn-outline im-btn-icon"><i class="bi bi-chevron-right"></i></a>
          <?php if ($sana !== $today): ?><a href="?" class="im-btn im-btn-sm im-btn-outline">Bugun</a><?php endif; ?>
          <div class="ms-auto text-muted fs-sm"><?= im_date($sana) ?></div>
        </div>

        <!-- Smena bo'yicha hisobot (Joriy yoki Oxirgi) -->
        <?php if (!empty($joriy_smena)): ?>
          <div class="im-card mb-4"
            style="border-left: 4px solid <?= $joriy_smena['yopildi'] ? 'var(--secondary)' : 'var(--success)' ?>; overflow-x: auto;">
            <div class="im-card-body p-3 d-flex justify-content-between align-items-center flex-nowrap"
              style="min-width: 700px;">
              <div class="me-4">
                <h5 class="mb-1 d-flex align-items-center gap-2">
                  Smena #<?= $joriy_smena['id'] ?>
                  <?php if (!$joriy_smena['yopildi']): ?>
                    <span class="badge bg-success" style="font-size:11px;padding:4px 8px">🟢 Aktiv to'lovlar qabuli</span>
                  <?php else: ?>
                    <span class="badge bg-secondary" style="font-size:11px;padding:4px 8px">Yopilgan</span>
                  <?php endif; ?>
                </h5>
                <div class="text-muted fs-sm">
                  <i class="bi bi-clock"></i>
                  <?= substr($joriy_smena['ochildi'] ?? $joriy_smena['boshlanish'] ?? '', 0, 16) ?>
                  <?= !empty($joriy_smena['yopildi']) ? ' → ' . substr($joriy_smena['yopildi'], 0, 16) : 'dan boshlab' ?>
                </div>
              </div>
              <div class="d-flex gap-4">
                <div>
                  <div class="text-muted fs-xs mb-1">Asosiy Checklar</div>
                  <div class="fw-bold" style="font-size:18px"><?= $sm_data['cnt'] ?> ta</div>
                </div>
                <div>
                  <div class="text-muted fs-xs mb-1">Smena Naqd</div>
                  <div class="fw-bold num text-success" style="font-size:18px"><?= im_money($sm_data['naqd']) ?> s</div>
                </div>
                <div>
                  <div class="text-muted fs-xs mb-1">Smena <?= im_tt_nomi('karta', true) ?></div>
                  <div class="fw-bold num text-primary" style="font-size:18px"><?= im_money($sm_data['karta']) ?> s</div>
                </div>
                <?php if (($sm_data['bank'] ?? 0) > 0): ?>
                  <div>
                    <div class="text-muted fs-xs mb-1">Smena <?= im_tt_nomi('bank', true) ?></div>
                    <div class="fw-bold num" style="font-size:18px;color:#17a2b8"><?= im_money($sm_data['bank']) ?> s</div>
                  </div>
                <?php endif; ?>
                <?php if (($sm_data['usd_som'] ?? 0) > 0): ?>
                  <div>
                    <div class="text-muted fs-xs mb-1">Smena USD</div>
                    <div class="fw-bold num" style="font-size:18px;color:#e67e22">
                      $<?= number_format($sm_data['usd_dollar'], 2) ?>
                      <div style="font-size:11px;font-weight:500;color:#999"><?= im_money($sm_data['usd_som']) ?> s</div>
                    </div>
                  </div>
                <?php endif; ?>
                <div style="border-left:1px dashed var(--border); padding-left:15px">
                  <div class="text-muted fs-xs mb-1">Smena Kassa Tushumi</div>
                  <div class="fw-bold num text-accent-dark" style="font-size:22px">
                    <?= im_money($sm_data['kassa_tushum_total']) ?> so'm
                  </div>
                  <div class="text-muted" style="font-size:10px">
                    📦 Sotuvdan: <?= im_money($sm_data['kassa_tushum']) ?> so'm
                  </div>
                  <?php if ((float) ($sm_nasiya_qabul['jami'] ?? 0) > 0): ?>
                    <div style="font-size:10px;color:var(--success);font-weight:600">
                      💰 Nasiya qabuli: +<?= im_money($sm_nasiya_qabul['jami']) ?> so'm
                    </div>
                  <?php endif; ?>
                  <?php if (($sm_data['nasiya'] ?? 0) > 0): ?>
                    <div class="text-muted" style="font-size:10px">+ <?= im_money($sm_data['nasiya']) ?> so'm nasiya sotuv
                      (kassa
                      emas)</div>
                  <?php endif; ?>
                </div>
                <div class="ms-2 align-self-center">
                  <a href="<?= im_BASE ?>print/smena.php?id=<?= $joriy_smena['id'] ?>" target="_blank"
                    class="im-btn im-btn-outline im-btn-sm" style="white-space:nowrap">
                    <i class="bi bi-printer"></i> Z-Hisobot
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Kunlik ko'rsatkichlar ajratgich -->
        <div class="d-flex align-items-center gap-2 mb-3 mt-1">
          <div style="flex:1;height:1px;background:var(--border)"></div>
          <span class="text-muted" style="font-size:11px;font-weight:600;white-space:nowrap">
            <i class="bi bi-calendar-day"></i> Kunlik ko'rsatkichlar — <?= im_date($sana) ?>
          </span>
          <div style="flex:1;height:1px;background:var(--border)"></div>
        </div>

        <!-- Asosiy stat kartalar -->
        <div class="row g-3 mb-4">
          <div class="col-md-4 col-12">
            <div class="im-stat-card" style="border-left:4px solid var(--accent-dark)">
              <div class="im-stat-icon" style="background:rgba(226,185,111,.15);color:var(--accent-dark)"><i
                  class="bi bi-bag-fill"></i></div>
              <div>
                <div class="im-stat-label">Jami sotuv</div>
                <div class="im-stat-value num"><?= im_money($sotuv_jami) ?> so'm</div>
                <div class="text-muted fs-xs"><?= $sotuv_count ?> ta chek /
                  <?= $sotuv_chegirma > 0 ? '-' . im_money($sotuv_chegirma) . ' chegirma' : '' ?>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4 col-12">
            <div class="im-stat-card" style="border-left:4px solid var(--success)">
              <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i
                  class="bi bi-graph-up-arrow"></i></div>
              <div>
                <div class="im-stat-label">Sof sotuv</div>
                <div class="im-stat-value num" style="color:var(--success)"><?= im_money($sotuv_netto) ?> so'm</div>
                <div class="text-muted fs-xs">
                  <?= $vozvrat_jami > 0 ? '↩ Vozvrat: -' . im_money($vozvrat_jami) : 'Vozvrat yo\'q' ?>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4 col-12">
            <div class="im-stat-card" style="border-left:4px solid var(--danger)">
              <div class="im-stat-icon" style="background:rgba(220,53,69,.12);color:var(--danger)"><i
                  class="bi bi-cash-stack"></i></div>
              <div>
                <div class="im-stat-label">Harajatlar</div>
                <div class="im-stat-value num" style="color:var(--danger)"><?= im_money($harajat_jami) ?> so'm</div>
                <div class="text-muted fs-xs">Kunlik chiqimlar</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Nasiya to'lovlari bo'limi (bugun) -->
        <?php if ($nas_jami > 0): ?>
          <div class="im-card mb-4 im-slide-in" style="border-left:4px solid var(--success)">
            <div class="im-card-header">
              <i class="bi bi-cash-coin" style="color:var(--success)"></i>
              <span class="im-card-title">Nasiyadan qaytgan pullar (bugun)</span>
              <span class="im-badge im-badge-success"><?= im_money($nas_jami) ?> so'm</span>
            </div>
            <div class="im-card-body p-3">
              <div class="d-flex flex-wrap gap-3">
                <?php if ($nas_naqd > 0): ?>
                  <div class="im-stat-card p-3" style="border-left:3px solid var(--success);flex:1;min-width:140px">
                    <div class="text-muted fs-xs"><i class="bi bi-cash"></i> Naqd (nasiyadan)</div>
                    <div class="fw-bold num fs-5" style="color:var(--success)"><?= im_money($nas_naqd) ?> so'm</div>
                  </div>
                <?php endif; ?>
                <?php if ($nas_karta > 0): ?>
                  <div class="im-stat-card p-3" style="border-left:3px solid var(--primary);flex:1;min-width:140px">
                    <div class="text-muted fs-xs"><i class="bi bi-credit-card-fill"></i> <?= im_tt_nomi('karta', true) ?> (nasiyadan)</div>
                    <div class="fw-bold num fs-5" style="color:var(--primary)"><?= im_money($nas_karta) ?> so'm</div>
                  </div>
                <?php endif; ?>
                <?php if ($nas_bank > 0): ?>
                  <div class="im-stat-card p-3" style="border-left:3px solid var(--info);flex:1;min-width:140px">
                    <div class="text-muted fs-xs"><i class="bi bi-bank"></i> <?= im_tt_nomi('bank', true) ?> (nasiyadan)</div>
                    <div class="fw-bold num fs-5" style="color:var(--info)"><?= im_money($nas_bank) ?> so'm</div>
                  </div>
                <?php endif; ?>
                <?php if ($nas_usd_som > 0): ?>
                  <div class="im-stat-card p-3" style="border-left:3px solid #e67e22;flex:1;min-width:140px">
                    <div class="text-muted fs-xs"><i class="bi bi-currency-dollar"></i> USD (nasiyadan)</div>
                    <div class="fw-bold num fs-5" style="color:#e67e22"><?= im_money($nas_usd_som) ?> so'm (USD)</div>
                  </div>
                <?php endif; ?>
              </div>
              <div class="mt-2 text-muted fs-xs">
                <i class="bi bi-info-circle"></i>
                Yuqoridagi summalar kassadagi tegishli balansga qo'shilgan
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- To'lov bo'yicha -->
        <div class="row g-3 mb-4">
          <div class="col-md-8">
            <div class="im-card h-100">
              <div class="im-card-header">
                <i class="bi bi-pie-chart-fill" style="color:var(--accent-dark)"></i>
                <span class="im-card-title">To'lov turlari</span>
              </div>
              <div class="im-card-body p-4">
                <?php
                $pay_types = [
                  ['Naqd', $sotuv_naqd, 'success', 'cash'],
                  [im_tt_nomi('karta', true), $sotuv_karta, 'primary', 'credit-card-fill'],
                  [im_tt_nomi('bank', true), $sotuv_bank, 'info', 'bank'],
                  ['USD', $sotuv_usd_som, 'warning', 'currency-dollar'],
                  ['Nasiya', $sotuv_nasiya, 'danger', 'credit-card-2-back-fill'],
                ];
                $max = max($sotuv_naqd, $sotuv_karta, $sotuv_bank, $sotuv_usd_som, $sotuv_nasiya, 1);
                foreach ($pay_types as [$lbl, $sum, $color, $icon]):
                  if ($sum <= 0)
                    continue;
                  $pct = round($sum / max($sotuv_jami, 1) * 100);
                  ?>
                  <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                      <span class="fw-semibold fs-sm"><i class="bi bi-<?= $icon ?>"></i> <?= $lbl ?></span>
                      <span class="fw-bold num"><?= im_money($sum) ?> so'm <span
                          class="text-muted fs-xs">(<?= $pct ?>%)</span></span>
                    </div>
                    <div style="background:var(--border);border-radius:4px;height:8px">
                      <div
                        style="width:<?= $pct ?>%;background:var(--<?= $color ?>);border-radius:4px;height:8px;transition:width .5s">
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="im-card h-100">
              <div class="im-card-header">
                <i class="bi bi-trophy-fill" style="color:var(--accent-dark)"></i>
                <span class="im-card-title">Top mahsulotlar</span>
              </div>
              <div class="im-card-body p-3">
                <?php if ($top_mah): ?>
                  <?php foreach ($top_mah as $i => $tm): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <div
                        style="width:22px;height:22px;border-radius:50%;background:var(--accent);color:var(--primary);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center">
                        <?= $i + 1 ?>
                      </div>
                      <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size:12px;line-height:1.2"><?= im_f($tm['nomi']) ?></div>
                        <div class="text-muted" style="font-size:10px"><?= $tm['sotilgan'] ?> dona ·
                          <?= im_money($tm['summa']) ?> so'm
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="text-muted fs-sm text-center py-3">Sotuv yo'q</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Sotuvlar jadvali -->
        <div class="im-card im-slide-in">
          <div class="im-card-header">
            <i class="bi bi-receipt"></i>
            <span class="im-card-title">Sotuvlar ro'yxati</span>
            <span class="im-badge im-badge-muted"><?= count($sotuvlar) ?> ta</span>
          </div>
          <?php if ($sotuvlar): ?>
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
                    <tr>
                      <td><code class="fs-xs"><?= im_f($s['chek_nomer']) ?></code></td>
                      <td class="text-muted fs-xs"><?= date('H:i', strtotime($s['sana'])) ?></td>
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
                        <?php if ($s['mijoz']): ?>
                          <div style="color:#0d6efd">Mijoz: <?= im_f($s['mijoz']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-center"><span class="im-badge im-badge-muted"><?= $s['item_soni'] ?> xil</span></td>
                      <td class="text-right num fw-bold"><?= im_money($s['tolov_summa']) ?> so'm</td>
                      <td>
                        <?php
                        $pays = [];
                        if ($s['naqd_summa'] > 0)
                          $pays[] = '<span class="im-badge im-badge-success fs-xs">naqd</span>';
                        if ($s['karta_summa'] > 0)
                          $pays[] = '<span class="im-badge im-badge-primary fs-xs">karta</span>';
                        if ($s['bank_summa'] > 0)
                          $pays[] = '<span class="im-badge im-badge-info fs-xs">bank</span>';
                        if ($s['usd_summa'] > 0)
                          $pays[] = '<span class="im-badge fs-xs" style="background:#fff3cd;color:#856404">$' . number_format($s['usd_summa'], 2) . ' USD</span>';
                        if ($s['nasiya_summa'] > 0)
                          $pays[] = '<span class="im-badge im-badge-danger fs-xs">nasiya</span>';
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
                <tfoot>
                  <tr style="background:var(--bg)">
                    <td colspan="4" class="fw-bold">Jami</td>
                    <td class="text-right fw-bold num" style="color:var(--accent-dark)"><?= im_money($sotuv_jami) ?> so'm
                    </td>
                    <td colspan="2"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          <?php else: ?>
            <div class="im-empty">
              <i class="bi bi-receipt"></i>
              <h4>Sotuv yo'q</h4>
              <p class="text-muted">Bu kun uchun sotuv amalga oshirilmagan</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Quick actions -->
        <div class="row g-3 mt-2">
          <div class="col-12">
            <div class="im-card im-slide-in">
              <div class="im-card-header">
                <i class="bi bi-lightning-fill" style="color:var(--warning)"></i>
                <span class="im-card-title">Tezkor amallar</span>
              </div>
              <div class="im-card-body">
                <div class="d-flex flex-wrap gap-3">
                  <a href="<?= im_BASE ?>dukon/pos.php" class="im-btn im-btn-primary im-btn-lg">
                    <i class="bi bi-cart-fill"></i>
                    POS Sotuv
                  </a>
                  <button onclick="window.location.href='<?= im_BASE ?>dukon/harajat.php?add=1'"
                    class="im-btn im-btn-danger im-btn-lg">
                    <i class="bi bi-cash-stack"></i>
                    Harajat kiritish
                  </button>
                  <a href="<?= im_BASE ?>dukon/vozvrat.php" class="im-btn im-btn-outline im-btn-lg">
                    <i class="bi bi-arrow-return-left"></i>
                    Qaytarish (Vozvrat)
                  </a>
                  <a href="<?= im_BASE ?>dukon/nasiya.php" class="im-btn im-btn-outline im-btn-lg">
                    <i class="bi bi-credit-card-2-back"></i>
                    Nasiya to'lovlari
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

      </main>

    </div>
  </div>
  <div id="im-toast-container"></div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>window.im_BASE = "<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
  <script src="<?= im_BASE ?>assets/js/main.js"></script>
  <style>
    @media print {

      .im-sidebar,
      .im-topbar,
      .im-breadcrumb,
      .im-topbar-actions {
        display: none !important;
      }

      .im-main {
        margin-left: 0 !important;
      }

      .im-content {
        padding: 0 !important;
      }
    }
  </style>
</body>

</html>
