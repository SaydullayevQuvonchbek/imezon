<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();
$sale_cost = im_fifo_sale_unit_cost_sql('si');

// ── Filtrlar ──────────────────────────────────────────────────
$dan       = $_GET['dan']      ?? date('Y-m-01');
$gacha     = $_GET['gacha']    ?? date('Y-m-d');
$filial_f  = (int)($_GET['filial_id'] ?? 0);
$tur_f     = $_GET['tur']      ?? '';
$dan_s     = mysqli_real_escape_string($link, $dan);
$gacha_s   = mysqli_real_escape_string($link, $gacha);

// ── Joriy USD kurs ────────────────────────────────────────────
$usd_kurs  = im_usd_kurs();

// ── Barcha filiallar ─────────────────────────────────────────
$filiallar = $db->rows("SELECT * FROM im_filiallar WHERE status=1 ORDER BY tartib");

// ── Har bir filial uchun kassa holati ─────────────────────────
$kassa_all = $db->rows(
    "SELECT k.*, f.nomi AS filial_nomi, f.rang
     FROM im_kassa k
     LEFT JOIN im_filiallar f ON f.id = k.filial_id
     ORDER BY k.filial_id"
);
// Admin markaz kassasi (filial_id=0)
$admin_kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=0 LIMIT 1") ?? ['naqd_balans'=>0,'karta_balans'=>0,'bank_balans'=>0,'usd_balans'=>0];

// ── Davr yig'indilari (Sotuv = haqiqiy kirim) ────────────────
$fwhere_b = "DATE(sana) BETWEEN '$dan_s' AND '$gacha_s'";
if ($filial_f) $fwhere_b .= " AND filial_id=$filial_f";

// Haqiqiy kirimlar: sotuv + nasiya to'lovi
$kirim_sotuv   = (float)$db->val("SELECT COALESCE(SUM(summa_som),0) FROM im_balans
    WHERE tur='kirim' AND kategoriya IN ('sotuv_naqd','sotuv_karta','sotuv_bank','sotuv_usd')
    AND $fwhere_b");
// USD to'lovidan so'mda berilgan qaytim savdo kirimi emas. Sotuv yozuvi
// asosiy manba bo'lgani uchun balans jurnalidan qat'i nazar shu yerdan ayriladi.
$usd_qaytim_sotuv = (float)$db->val("SELECT COALESCE(SUM(usd_qaytim_som),0) FROM im_sotuvlar
    WHERE $fwhere_b");
$kirim_sotuv -= $usd_qaytim_sotuv;
$kirim_nasiya  = (float)$db->val("SELECT COALESCE(SUM(summa_som),0) FROM im_balans
    WHERE tur='kirim' AND kategoriya='nasiya_tolov' AND $fwhere_b");
$kirim_boshqa  = (float)$db->val("SELECT COALESCE(SUM(summa_som),0) FROM im_balans
    WHERE tur='kirim' AND kategoriya IN ('vozvrat_kirim','boshqa_kirim') AND $fwhere_b");

// Haqiqiy chiqimlar: harajat + vozvrat + maosh (inkasasiya va postavshik ALOHIDA)
$chiqim_harajat   = (float)$db->val("SELECT COALESCE(SUM(summa_som),0) FROM im_balans
    WHERE tur='chiqim' AND kategoriya IN ('harajat','admin_harajat') AND $fwhere_b");
$chiqim_maosh     = (float)$db->val("SELECT COALESCE(SUM(summa_som),0) FROM im_balans
    WHERE tur='chiqim' AND kategoriya='maosh' AND $fwhere_b");
$chiqim_vozvrat   = (float)$db->val("SELECT COALESCE(SUM(summa_som),0) FROM im_balans
    WHERE tur='chiqim' AND kategoriya IN ('vozvrat_chiqim','boshqa_chiqim') AND $fwhere_b");

// Postavshikka to'lov (xarid narxi — foydaga tasir qiladi, lekin alohida ko'rsatiladi)
$chiqim_postavshik = (float)$db->val("SELECT COALESCE(SUM(summa_som),0) FROM im_balans
    WHERE tur='chiqim' AND kategoriya='postavshik_tolov' AND $fwhere_b");

// Inkasasiya (pul harakati — foydaga tasir QILMAYDI)
$inkasasiya_chiqim = (float)$db->val("SELECT COALESCE(SUM(summa_som),0) FROM im_balans
    WHERE tur='chiqim' AND kategoriya='inkasasiya_chiqim' AND $fwhere_b");
$inkasasiya_kirim  = (float)$db->val("SELECT COALESCE(SUM(summa_som),0) FROM im_balans
    WHERE tur='kirim' AND kategoriya='inkasasiya_kirim'
    AND DATE(sana) BETWEEN '$dan_s' AND '$gacha_s'");

// Foyda hisobi:
// Sof foyda = Sotuv tushumi - Harajatlar - Vozvrat
// Tannarx (sotilgan mahsulot xarid narxi) ALLAQACHON hisobda:
//   Foyda = (sotish_narxi - tannarx) * soni → sotuv_items da hisoblanadi
// Postavshikka to'lov = KASSA HARAKATI (pul jo'natish), foydaga tasir QILMAYDI
// chunki tannarx allaqachon foydadan ayirilgan!
$jami_kirim  = $kirim_sotuv + $kirim_nasiya; // $kirim_boshqa foyda hisobiga kirmaydi
$jami_chiqim = $chiqim_harajat + $chiqim_vozvrat; // faqat operatsion chiqim (maoshsiz)

// Tannarx (sotilgan mahsulotlar FIFO xarid narxi) — im_sotuv_items dan
$tannarx_fwhere = "DATE(s.sana) BETWEEN '$dan_s' AND '$gacha_s'";
if ($filial_f) $tannarx_fwhere .= " AND s.filial_id=$filial_f";
$tannarx = (float)$db->val(
    "SELECT COALESCE(SUM(($sale_cost) * si.soni), 0)
     FROM im_sotuv_items si
     LEFT JOIN im_sotuvlar s ON s.id = si.sotuv_id
     WHERE $tannarx_fwhere"
);

$vozvrat_tannarx = (float)$db->val(
    "SELECT COALESCE(SUM(($sale_cost) * v.soni), 0)
     FROM im_vozvratlar v
     JOIN im_sotuvlar s ON s.id = v.sotuv_id
     JOIN im_sotuv_items si ON si.id = v.sotuv_item_id
     WHERE $tannarx_fwhere"
);
$tannarx -= $vozvrat_tannarx;

// Yopilgan qozondagi haqiqiy isrof sotilgan mahsulot tannarxi emas, lekin
// biznesning ishlab chiqarish zarari. Shu sabab sof foydadan alohida ayriladi.
$qozon_isrofi = (float)$db->val(
    "SELECT COALESCE(SUM(qoldi_porsiya*yakuniy_tannarx),0) FROM im_osh_qozon
     WHERE holat='yopildi' AND qoldi_isrofmi=1 AND sana BETWEEN '$dan_s' AND '$gacha_s'"
     . ($filial_f ? " AND filial_id=$filial_f" : '')
);

// MAOSHSIZ Sof Foyda = Sotuv - Tannarx - Harajat - Vozvrat
$maoshsiz_foyda = $jami_kirim - $tannarx - $jami_chiqim - $qozon_isrofi;

// MAOSHLI Sof Foyda = Sotuv - Tannarx - Harajat - MAOSH - Vozvrat
$sof_foyda = $maoshsiz_foyda - $chiqim_maosh;

// ── Filiallar bo'yicha sotuv ──────────────────────────────────
$fwhere_s = "DATE(s.sana) BETWEEN '$dan_s' AND '$gacha_s'";
if ($filial_f) $fwhere_s .= " AND s.filial_id=$filial_f";
$sotuv_by_filial = $db->rows(
    "SELECT s.filial_id, f.nomi AS filial_nomi, f.rang,
            COUNT(*) AS sotuv_soni,
            COALESCE(SUM(naqd_summa),0)   AS naqd,
            COALESCE(SUM(karta_summa),0)  AS karta,
            COALESCE(SUM(bank_summa),0)   AS bank,
            COALESCE(SUM(nasiya_summa),0) AS nasiya,
            COALESCE(SUM(usd_summa*usd_kurs),0) AS usd_som,
            COALESCE(SUM(tolov_summa),0)  AS jami
     FROM im_sotuvlar s
     LEFT JOIN im_filiallar f ON f.id = s.filial_id
     WHERE $fwhere_s
     GROUP BY s.filial_id
     ORDER BY jami DESC"
);

// ── Inkasasiya filial bo'yicha ────────────────────────────────
$ink_where = "DATE(i.sana) BETWEEN '$dan_s' AND '$gacha_s'";
if ($filial_f) $ink_where .= " AND i.filial_id=$filial_f";
$inkasasiya_by_filial = $db->rows(
    "SELECT i.filial_id, f.nomi AS filial_nomi,
            COUNT(i.id) AS soni,
            COALESCE(SUM(CASE WHEN i.holat='qabul_qilindi'  THEN i.summa ELSE 0 END),0) AS tasdiqlangan,
            COALESCE(SUM(CASE WHEN i.holat='kutilmoqda'     THEN i.summa ELSE 0 END),0) AS kutilmoqda,
            COALESCE(SUM(CASE WHEN i.holat='bekor_qilindi'  THEN i.summa ELSE 0 END),0) AS bekor
     FROM im_inkasasiya i
     LEFT JOIN im_filiallar f ON f.id = i.filial_id
     WHERE $ink_where
     GROUP BY i.filial_id"
);

// ── Balans logi ───────────────────────────────────────────────
$bwhere = "DATE(b.sana) BETWEEN '$dan_s' AND '$gacha_s'";
if ($filial_f) $bwhere .= " AND b.filial_id=$filial_f";
if ($tur_f)    $bwhere .= " AND b.tur='" . mysqli_real_escape_string($link, $tur_f) . "'";
$balanslar = $db->rows(
    "SELECT b.*, x.ism AS xodim_ism, f.nomi AS filial_nomi
     FROM im_balans b
     LEFT JOIN im_xodimlar x  ON x.id = b.xodim_id
     LEFT JOIN im_filiallar f ON f.id = b.filial_id
     WHERE $bwhere ORDER BY b.sana DESC LIMIT 300"
);

// Kategoriya yorliqlari
$kat_labels = [
    'sotuv_naqd'        => '💵 Sotuv naqd',
    'sotuv_karta'       => '💳 Sotuv karta',
    'sotuv_bank'        => '🏦 Sotuv bank',
    'sotuv_usd'         => '🪙 Sotuv USD',
    'usd_qaytim'        => '↩️ USD qaytim (naqd)',
    'nasiya_tolov'      => '📋 Nasiya to\'lovi',
    'postavshik_tolov'  => '🚚 Postavshikka to\'lov',
    'harajat'           => '📦 Harajat',
    'inkasasiya_chiqim' => '🏛️ Inkasasiya (filialdan)',
    'inkasasiya_kirim'  => '🏛️ Inkasasiya (qabul)',
    'inkasso'           => '🏛️ Inkasso (kassadan)',
    'vozvrat_chiqim'    => '↩️ Qaytarish chiqim',
    'vozvrat_kirim'     => '↩️ Qaytarish kirim',
    'boshqa_kirim'      => '➕ Boshqa kirim',
    'boshqa_chiqim'     => '➖ Boshqa chiqim',
    'smena_ochish'      => '🔓 Smena ochish (boshlang\'ich naqd)',
];

$shortcuts = [
    'Bugun'      => [date('Y-m-d'), date('Y-m-d')],
    'Bu hafta'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
    'Bu oy'      => [date('Y-m-01'), date('Y-m-d')],
    "O'tgan oy"  => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
];
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Balans & Kassa | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-wallet2 me-1"></i> Balans & Kassa</div>
    <div class="im-topbar-actions">
      <span class="im-badge im-badge-muted me-2" title="Joriy USD kurs">
        💱 1$ = <?= im_money($usd_kurs) ?> so'm
      </span>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- ── FILTER ─────────────────────────────────────────────── -->
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
          <div>
            <label class="im-label mb-1" style="font-size:11px">Filial</label>
            <select name="filial_id" class="im-select im-input-sm" style="max-width:140px">
              <option value="0">Barcha filiallar</option>
              <?php foreach ($filiallar as $f): ?>
              <option value="<?= $f['id'] ?>" <?= $filial_f==$f['id']?'selected':'' ?>><?= im_f($f['nomi']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="im-label mb-1" style="font-size:11px">Tur</label>
            <select name="tur" class="im-select im-input-sm" style="max-width:120px">
              <option value="">Hammasi</option>
              <option value="kirim"  <?= $tur_f==='kirim' ?'selected':''?>>Kirim</option>
              <option value="chiqim" <?= $tur_f==='chiqim'?'selected':''?>>Chiqim</option>
            </select>
          </div>
          <div class="d-flex gap-1 ms-auto align-items-end flex-wrap">
            <?php foreach ($shortcuts as $lbl => [$d1, $d2]):
                $act = ($dan===$d1 && $gacha===$d2) ? 'im-btn-primary' : 'im-btn-outline'; ?>
            <a href="?dan=<?= $d1 ?>&gacha=<?= $d2 ?>&filial_id=<?= $filial_f ?>&tur=<?= urlencode($tur_f) ?>"
               class="im-btn im-btn-sm <?= $act ?>"><?= $lbl ?></a>
            <?php endforeach; ?>
            <button type="submit" class="im-btn im-btn-dark im-btn-sm"><i class="bi bi-filter"></i> Ko'rsat</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── KASSALAR (Joriy holat) ──────────────────────────────── -->
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-safe2-fill" style="color:var(--primary)"></i>
        <span class="im-card-title">Kassalar — Joriy holat (real vaqt)</span>
        <span class="im-badge im-badge-muted ms-auto">1$ = <?= im_money($usd_kurs) ?> so'm</span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>Filial / Kassa</th>
              <th class="text-right">💵 Naqd</th>
              <th class="text-right"><?= im_tt_label('karta', true) ?></th>
              <th class="text-right"><?= im_tt_label('bank', true) ?></th>
              <th class="text-right">🪙 USD ($)</th>
              <th class="text-right fw-bold">Jami (so'm)</th>
              <th class="text-right text-muted">≈ USD</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $total_naqd = $total_karta = $total_bank = $total_usd_som = 0;
            foreach ($kassa_all as $k):
                $fid = (int)$k['filial_id'];
                $n   = (float)$k['naqd_balans'];
                $ka  = (float)$k['karta_balans'];
                $ba  = (float)$k['bank_balans'];
                $us  = (float)$k['usd_balans'];
                $us_som = $us * $usd_kurs;
                $jami   = $n + $ka + $ba + $us_som;
                $total_naqd    += $n;
                $total_karta   += $ka;
                $total_bank    += $ba;
                $total_usd_som += $us_som;
                $filial_nomi = $k['filial_nomi'] ?: ($fid === 0 ? '🏛️ Admin markaz' : "Filial #$fid");
            ?>
            <tr>
              <td>
                <span class="fw-bold" style="color:<?= $k['rang'] ?? 'var(--primary)' ?>">
                  <i class="bi bi-<?= $fid===0?'building-fill':'shop' ?> me-1"></i><?= im_f($filial_nomi) ?>
                </span>
              </td>
              <td class="text-right num" style="color:var(--success)"><?= im_money($n) ?></td>
              <td class="text-right num"><?= im_money($ka) ?></td>
              <td class="text-right num"><?= im_money($ba) ?></td>
              <td class="text-right num" style="color:var(--accent-dark)">
                <?php if ($us > 0): ?>
                  <span class="fw-bold">$<?= number_format($us, 2) ?></span>
                  <div class="text-muted fs-xs"><?= im_money($us_som) ?> so'm</div>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="text-right num fw-bold" style="color:var(--primary)"><?= im_money($jami) ?> so'm</td>
              <td class="text-right num text-muted fs-xs">
                $<?= $usd_kurs > 0 ? number_format($jami / $usd_kurs, 0) : '—' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <?php
            $grand_total     = $total_naqd + $total_karta + $total_bank + $total_usd_som;
            $grand_total_usd = $usd_kurs > 0 ? $grand_total / $usd_kurs : 0;
            ?>
            <tr style="background:var(--accent-light)">
              <td class="fw-bold">Jami barcha kassalar</td>
              <td class="text-right num fw-bold" style="color:var(--success)"><?= im_money($total_naqd) ?></td>
              <td class="text-right num fw-bold"><?= im_money($total_karta) ?></td>
              <td class="text-right num fw-bold"><?= im_money($total_bank) ?></td>
              <td class="text-right num fw-bold" style="color:var(--accent-dark)">
                $<?= number_format($total_usd_som / max(1, $usd_kurs), 2) ?>
              </td>
              <td class="text-right num fw-bold fs-5" style="color:var(--primary)"><?= im_money($grand_total) ?> so'm</td>
              <td class="text-right num fw-bold text-muted">$<?= number_format($grand_total_usd, 0) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- ── DAVR STATISTIKASI ─────────────────────────────────────── -->
    <div class="im-card-header mb-2 px-0">
      <i class="bi bi-bar-chart-fill" style="color:var(--accent-dark)"></i>
      <span class="fw-bold"><?= $dan===$gacha ? im_date($dan) : im_date($dan).' – '.im_date($gacha) ?> — Moliyaviy hisobot</span>
    </div>
    <div class="row g-3 mb-4">
      <!-- Sotuv -->
      <div class="col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i class="bi bi-arrow-down-circle-fill"></i></div>
          <div>
            <div class="im-stat-label">Sotuv tushumi</div>
            <div class="im-stat-value num" style="color:var(--success)"><?= im_money($kirim_sotuv) ?> so'm</div>
            <div class="text-muted fs-xs">+ Nasiya: <?= im_money($kirim_nasiya) ?></div>
          </div>
        </div>
      </div>
      <!-- Tannarx -->
      <div class="col-md-3">
        <div class="im-stat-card" style="border-left:4px solid #6c757d">
          <div class="im-stat-icon" style="background:rgba(108,117,125,.1);color:#6c757d"><i class="bi bi-boxes"></i></div>
          <div>
            <div class="im-stat-label">Tannarx (xarid narxi)</div>
            <div class="im-stat-value num" style="color:#6c757d"><?= im_money($tannarx) ?> so'm</div>
            <div class="text-muted fs-xs">≈ $<?= number_format($usd_kurs > 0 ? $tannarx / $usd_kurs : 0, 0) ?></div>
          </div>
        </div>
      </div>
      <!-- Harajatlar -->
      <div class="col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.12);color:var(--danger)"><i class="bi bi-arrow-up-circle-fill"></i></div>
          <div>
            <div class="im-stat-label">Harajatlar</div>
            <div class="im-stat-value num" style="color:var(--danger)"><?= im_money($chiqim_harajat) ?> so'm</div>
            <div class="text-muted fs-xs">Vozvrat: <?= im_money($chiqim_vozvrat) ?> · Qozon isrofi: <?= im_money($qozon_isrofi) ?></div>
          </div>
        </div>
      </div>
      <!-- Postavshik to'lov — KASSA harakati, foydaga tasir QILMAYDI -->
      <div class="col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--primary);opacity:.85">
          <div class="im-stat-icon" style="background:rgba(26,26,46,.07);color:var(--primary)"><i class="bi bi-truck"></i></div>
          <div>
            <div class="im-stat-label">Postavshikka to'lov</div>
            <div class="im-stat-value num" style="color:var(--primary)"><?= im_money($chiqim_postavshik) ?> so'm</div>
            <div class="text-muted fs-xs">
              <i class="bi bi-info-circle"></i> Kassa harakati — foydaga ta'sir qilmaydi
            </div>
          </div>
        </div>
      </div>
      <!-- Maosh -->
      <?php if ($chiqim_maosh > 0): ?>
      <div class="col-md-3">
        <div class="im-stat-card" style="border-left:4px solid #e67e22">
          <div class="im-stat-icon" style="background:rgba(230,126,34,.1);color:#e67e22"><i class="bi bi-person-workspace"></i></div>
          <div>
            <div class="im-stat-label">Maoshlar</div>
            <div class="im-stat-value num" style="color:#e67e22"><?= im_money($chiqim_maosh) ?> so'm</div>
            <div class="text-muted fs-xs">Foydadan ayiriladi</div>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <!-- Sof foyda — toggle bilan -->
      <div class="col-md-3">
        <?php
          $ko_foyda    = $sof_foyda;       // maoshli (haqiqiy)
          $nonsof      = $maoshsiz_foyda;   // maoshsiz
          $rang_ko     = $ko_foyda  >= 0 ? 'var(--success)' : 'var(--danger)';
          $rang_nonsof = $nonsof    >= 0 ? 'var(--success)' : 'var(--danger)';
        ?>
        <div class="im-stat-card" id="foyda-karta" style="border-left:4px solid <?= $rang_ko ?>;cursor:default">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:<?= $rang_ko ?>">
            <i class="bi bi-graph-up-arrow"></i>
          </div>
          <div style="flex:1">
            <!-- Maoshli foyda (aktiv) -->
            <div id="foyda-maoshli">
              <div class="im-stat-label">Sof foyda <span class="im-badge im-badge-muted" style="font-size:10px">maosh bilan</span></div>
              <div class="im-stat-value num" style="color:<?= $rang_ko ?>">
                <?= ($ko_foyda >= 0 ? '+' : '') . im_money($ko_foyda) ?> so'm
              </div>
              <div class="text-muted fs-xs">≈ $<?= number_format($usd_kurs > 0 ? $ko_foyda/$usd_kurs : 0, 0) ?></div>
            </div>
            <!-- Maoshsiz foyda (yashirin) -->
            <div id="foyda-maoshsiz" style="display:none">
              <div class="im-stat-label">Sof foyda <span class="im-badge im-badge-warning" style="font-size:10px">maoshsiz</span></div>
              <div class="im-stat-value num" style="color:<?= $rang_nonsof ?>">
                <?= ($nonsof >= 0 ? '+' : '') . im_money($nonsof) ?> so'm
              </div>
              <div class="text-muted fs-xs">≈ $<?= number_format($usd_kurs > 0 ? $nonsof/$usd_kurs : 0, 0) ?></div>
            </div>
            <!-- Toggle tugma -->
            <button id="foyda-toggle" onclick="toggleFoyda()" class="im-btn im-btn-outline mt-2"
              style="font-size:11px;padding:3px 10px;border-radius:20px">
              <i class="bi bi-arrow-repeat me-1"></i> Maoshsiz ko'rsatish
            </button>
          </div>
        </div>
      </div>
    </div>

    <script>
    let _maoshli = true;
    function toggleFoyda() {
      _maoshli = !_maoshli;
      document.getElementById('foyda-maoshli').style.display  = _maoshli ? '' : 'none';
      document.getElementById('foyda-maoshsiz').style.display = _maoshli ? 'none' : '';
      document.getElementById('foyda-toggle').innerHTML =
        '<i class="bi bi-arrow-repeat me-1"></i>' + (_maoshli ? 'Maoshsiz ko\'rsatish' : 'Maosh bilan ko\'rsatish');
    }
    </script>


    <!-- Inkasasiya alohida satri -->
    <?php if ($inkasasiya_chiqim > 0 || $inkasasiya_kirim > 0): ?>
    <div class="alert d-flex align-items-center gap-2 mb-4 p-3 rounded-3" style="background:rgba(var(--primary-rgb),.08);border:1px dashed var(--primary)">
      <i class="bi bi-info-circle-fill fs-5" style="color:var(--primary)"></i>
      <div>
        <strong>Inkasasiya (pul harakati — foydaga tasir qilmaydi):</strong>
        Filiallardagi <span style="color:var(--danger)">chiqim: <?= im_money($inkasasiya_chiqim) ?> so'm</span>
        | Admin qabul qildi: <span style="color:var(--success)"><?= im_money($inkasasiya_kirim) ?> so'm</span>
        <span class="text-muted fs-xs ms-2">— Bu pul kompaniya ichida ko'chmoqda, real xarajat emas</span>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── FILIALLAR BO'YICHA SOTUV ──────────────────────────────── -->
    <?php if ($sotuv_by_filial): ?>
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-bar-chart-fill" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Filiallar bo'yicha Sotuv — <?= $dan===$gacha ? im_date($dan) : im_date($dan).' – '.im_date($gacha) ?></span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>Filial</th>
              <th class="text-right">Soni</th>
              <th class="text-right">💵 Naqd</th>
              <th class="text-right"><?= im_tt_label('karta', true) ?></th>
              <th class="text-right"><?= im_tt_label('bank', true) ?></th>
              <th class="text-right">🪙 USD</th>
              <th class="text-right">📋 Nasiya</th>
              <th class="text-right fw-bold">Jami tushum</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $ts_naqd = $ts_karta = $ts_bank = $ts_usd = $ts_nasiya = $ts_jami = 0;
            foreach ($sotuv_by_filial as $s):
                $ts_naqd   += (float)$s['naqd'];
                $ts_karta  += (float)$s['karta'];
                $ts_bank   += (float)$s['bank'];
                $ts_usd    += (float)$s['usd_som'];
                $ts_nasiya += (float)$s['nasiya'];
                $ts_jami   += (float)$s['jami'];
            ?>
            <tr>
              <td class="fw-semibold" style="color:<?= $s['rang'] ?? 'inherit' ?>"><?= im_f($s['filial_nomi'] ?: "Filial #{$s['filial_id']}") ?></td>
              <td class="text-right"><?= (int)$s['sotuv_soni'] ?> ta</td>
              <td class="text-right num" style="color:var(--success)"><?= im_money($s['naqd']) ?></td>
              <td class="text-right num"><?= im_money($s['karta']) ?></td>
              <td class="text-right num"><?= im_money($s['bank']) ?></td>
              <td class="text-right num" style="color:var(--accent-dark)"><?= im_money($s['usd_som']) ?></td>
              <td class="text-right num" style="color:var(--warning)"><?= im_money($s['nasiya']) ?></td>
              <td class="text-right num fw-bold" style="color:var(--primary)"><?= im_money($s['jami']) ?> so'm</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--accent-light)">
              <td class="fw-bold">Jami</td>
              <td></td>
              <td class="text-right num fw-bold"><?= im_money($ts_naqd) ?></td>
              <td class="text-right num fw-bold"><?= im_money($ts_karta) ?></td>
              <td class="text-right num fw-bold"><?= im_money($ts_bank) ?></td>
              <td class="text-right num fw-bold"><?= im_money($ts_usd) ?></td>
              <td class="text-right num fw-bold"><?= im_money($ts_nasiya) ?></td>
              <td class="text-right num fw-bold fs-6" style="color:var(--primary)"><?= im_money($ts_jami) ?> so'm</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── INKASASIYA (Kassadan olingan pul — ALOHIDA) ──────────── -->
    <?php if ($inkasasiya_by_filial): ?>
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-safe-fill" style="color:var(--primary)"></i>
        <span class="im-card-title">Kassalardan olingan pul (Inkasasiya) — <?= $dan===$gacha ? im_date($dan) : im_date($dan).' – '.im_date($gacha) ?></span>
        <span class="im-badge im-badge-muted ms-2">Foydaga tasir qilmaydi</span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>Filial</th>
              <th class="text-right">Arizalar</th>
              <th class="text-right text-success">✅ Tasdiqlangan</th>
              <th class="text-right text-warning">⏳ Kutilmoqda</th>
              <th class="text-right text-danger">❌ Bekor</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($inkasasiya_by_filial as $ink): ?>
            <tr>
              <td class="fw-semibold"><?= im_f($ink['filial_nomi'] ?: "Filial #{$ink['filial_id']}") ?></td>
              <td class="text-right"><?= (int)$ink['soni'] ?> ta</td>
              <td class="text-right num" style="color:var(--success)"><?= im_money($ink['tasdiqlangan']) ?> so'm</td>
              <td class="text-right num" style="color:var(--warning)">
                <?php if ((float)$ink['kutilmoqda'] > 0): ?>
                  <a href="<?= im_BASE ?>admin/inkasasiya.php" class="im-badge im-badge-warning text-dark">
                    <?= im_money($ink['kutilmoqda']) ?> so'm — Tasdiqlash →
                  </a>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="text-right num" style="color:var(--danger)"><?= im_money($ink['bekor']) ?> so'm</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── BALANS LOGI ────────────────────────────────────────────── -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-list-ul"></i>
        <span class="im-card-title">Balans logi — <?= $dan===$gacha ? im_date($dan) : im_date($dan).' – '.im_date($gacha) ?></span>
        <span class="im-badge im-badge-muted ms-auto"><?= count($balanslar) ?> ta</span>
      </div>
      <?php if ($balanslar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr>
            <th>Tur</th>
            <th>Kategoriya</th>
            <th>Filial</th>
            <th>Izoh</th>
            <th class="text-right">Summa</th>
            <th>Xodim</th>
            <th>Vaqt</th>
          </tr></thead>
          <tbody>
          <?php foreach ($balanslar as $b):
              $is_ink = in_array($b['kategoriya'], ['inkasasiya_chiqim','inkasasiya_kirim']);
          ?>
          <tr style="<?= $is_ink ? 'opacity:0.7;background:rgba(0,0,0,0.02)' : '' ?>">
            <td>
              <span class="im-badge im-badge-<?= $b['tur']==='kirim'?'success':'danger' ?>">
                <?= $b['tur']==='kirim'?'↓ Kirim':'↑ Chiqim' ?>
              </span>
              <?php if ($is_ink): ?>
                <span class="im-badge im-badge-muted ms-1" title="Foydaga tasir qilmaydi">🏛</span>
              <?php endif; ?>
            </td>
            <td class="text-muted fs-xs"><?= $kat_labels[$b['kategoriya']] ?? im_f($b['kategoriya']) ?></td>
            <td class="text-muted fs-xs"><?= im_f($b['filial_nomi'] ?: '—') ?></td>
            <td class="text-muted fs-xs" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= im_f($b['izoh'] ?: '—') ?></td>
            <td class="text-right num fw-bold" style="color:var(--<?= $b['tur']==='kirim'?'success':'danger' ?>)">
              <?= im_money($b['summa_som']) ?> so'm
              <?php if ((float)($b['summa_usd'] ?? 0) > 0): ?>
                <div class="text-muted fs-xs">$<?= number_format($b['summa_usd'], 2) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-muted fs-xs"><?= im_f($b['xodim_ism'] ?? '—') ?></td>
            <td class="text-muted fs-xs"><?= im_datetime($b['sana']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-wallet2"></i><h4>Log yo'q</h4><p class="text-muted">Bu davr uchun balans yozuvi topilmadi</p></div>
      <?php endif; ?>
    </div>

  </main>
</div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
</body></html>
