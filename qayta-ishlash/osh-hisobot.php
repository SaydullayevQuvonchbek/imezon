<?php
// ============================================================
//  IMezon — Osh qozoni hisoboti (kunlik reja-fakt)
//
//  Kun × filial × mahsulot bo'yicha:
//    Mo'ljal · Xomashyo puli · Sotildi (kassa) · Qoldi (isrof) ·
//    Jami chiqdi · Real tannarx · Retsept tannarx · Aniqlik %
//
//  Admin — barcha filial. Oshpaz/kassir — faqat o'z filiali.
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../fifo_reports.php';
require_once __DIR__ . '/ishlab_lib.php';
im_rol_check(['admin', 'oshpaz', 'kassir']);

$db  = new Cyber();

// Sana — faqat YYYY-MM-DD qabul qilamiz, aks holda standart oraliq
$sana_ok = fn($s) => is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
$dan   = $sana_ok($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-d', strtotime('-6 days'));
$gacha = $sana_ok($_GET['to']   ?? null) ? $_GET['to']   : date('Y-m-d');
$dan_s   = $db->f($dan);
$gacha_s = $db->f($gacha);

// Filial doirasi
$is_admin  = ($im_rol === 'admin');
$filial_id = (int)($_SESSION['im_filial_id'] ?? 0);
// Admin bo'lmagan xodimda filial aniqlanmasa — hech narsa ko'rsatmaymiz
if (!$is_admin && !$filial_id) {
    header('Location: ' . im_BASE . 'login.php');
    exit;
}
$fil_get   = $is_admin ? (int)($_GET['filial'] ?? 0) : $filial_id;
$fil_where = $fil_get ? " AND q.filial_id = $fil_get " : '';

$filiallar = $is_admin ? $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY nomi") : [];

// ── Kunlik jamlanma ─────────────────────────────────────────
$rows = $db->rows(
    "SELECT q.sana, q.filial_id, q.mahsulot_id,
            f.nomi AS filial_nomi, m.nomi AS mahsulot_nomi, m.birlik,
            COUNT(*)                 AS qozon_soni,
            SUM(q.holat='ochiq')     AS ochiq_soni,
            SUM(q.moljal_porsiya)    AS moljal,
            SUM(COALESCE(q.haqiqiy_porsiya,q.moljal_porsiya)) AS hisob_chiqdi,
            SUM(q.xomashyo_summa)    AS xomashyo,
            SUM(q.qoldi_porsiya)     AS qoldi,
            SUM(CASE WHEN q.qoldi_isrofmi=1 THEN q.qoldi_porsiya ELSE 0 END) AS qoldi_isrof,
            SUM(CASE WHEN q.qoldi_isrofmi=0 THEN q.qoldi_porsiya ELSE 0 END) AS qoldi_keyingi
     FROM im_osh_qozon q
     LEFT JOIN im_filiallar f   ON f.id = q.filial_id
     LEFT JOIN im_mahsulotlar m ON m.id = q.mahsulot_id
     WHERE q.holat IN ('ochiq','yopildi') AND q.sana BETWEEN '$dan_s' AND '$gacha_s' $fil_where
     GROUP BY q.sana, q.filial_id, q.mahsulot_id
     ORDER BY q.sana DESC, f.nomi, m.nomi"
);

// Retsept (a-la-carte) tannarxi — mahsulot+filial bo'yicha keshlanadi
$rt_cache_data = [];
$rt_cache = function ($mid, $fid) use ($db, &$rt_cache_data) {
    $k = "$mid:$fid";
    if (!isset($rt_cache_data[$k])) $rt_cache_data[$k] = im_alacarte_tannarx($db, $mid, $fid);
    return $rt_cache_data[$k];
};

$jami = ['xomashyo' => 0, 'sotildi' => 0, 'qoldi' => 0, 'isrof_q' => 0, 'moljal' => 0];
foreach ($rows as &$r) {
    $mid = (int)$r['mahsulot_id'];
    $fid = (int)$r['filial_id'];
    $sana_s = $db->f($r['sana']);

    $sotildi = (float)$db->val(
        "SELECT COALESCE(SUM(si.soni),0)
         FROM im_sotuv_items si
         JOIN im_sotuvlar s ON s.id = si.sotuv_id
         WHERE s.filial_id = $fid AND si.mahsulot_id = $mid
           AND DATE(s.sana) = '$sana_s' AND s.holat IN ('aktiv','qaytarilgan')"
    );

    $sotildi -= (float)$db->val("SELECT COALESCE(SUM(v.soni),0) FROM im_vozvratlar v
        JOIN im_sotuvlar s ON s.id=v.sotuv_id
        WHERE s.holat IN ('aktiv','qaytarilgan') AND s.filial_id=$fid AND v.mahsulot_id=$mid AND DATE(v.sana)='$sana_s'");

    $xomashyo    = (float)$r['xomashyo'];
    $qoldi       = (float)$r['qoldi'];
    $qoldi_isrof = (float)$r['qoldi_isrof'];     // haqiqiy isrof / xodim ovqati
    $qoldi_keyin = (float)$r['qoldi_keyingi'];   // xom marinovka — keyingi kunga o'tdi
    $moljal      = (float)$r['moljal'];
    // Ochiq qozonda tannarx mo'ljal bo'yicha vaqtinchalik; yopilganda esa
    // sotilgan + fizik qoldiqdan muhrlangan haqiqiy chiqish ishlatiladi.
    $jami_chiqdi = (float)$r['hisob_chiqdi'];

    $r['sotildi']      = $sotildi;
    $r['jami_chiqdi']  = $jami_chiqdi;
    $r['real_tannarx'] = $jami_chiqdi > 0 ? $xomashyo / $jami_chiqdi : 0;
    try { $r['ret_tannarx'] = $rt_cache($mid, $fid); }
    catch (Throwable $e) { $r['ret_tannarx'] = null; }
    $r['aniqlik']      = $moljal > 0 ? ($jami_chiqdi / $moljal * 100) : null;
    // Zarar faqat HAQIQATDA isrof bo'lgan qismdan — keyingi kunga o'tgani emas.
    $r['isrof_qiymati']= $qoldi_isrof * $r['real_tannarx'];

    $jami['xomashyo'] += $xomashyo;
    $jami['sotildi']  += $sotildi;
    $jami['qoldi']    += $qoldi_isrof;
    $jami['isrof_q']  += $r['isrof_qiymati'];
    $jami['moljal']   += $moljal;
}
unset($r);

// ── Qozonlar ro'yxati (detal) ───────────────────────────────
$qozonlar = $db->rows(
    "SELECT q.*, f.nomi AS filial_nomi, m.nomi AS mahsulot_nomi,
            xo.ism AS ochgan_ism, xy.ism AS yopgan_ism
     FROM im_osh_qozon q
     LEFT JOIN im_filiallar f   ON f.id = q.filial_id
     LEFT JOIN im_mahsulotlar m ON m.id = q.mahsulot_id
     LEFT JOIN im_xodimlar xo   ON xo.id = q.ochgan_xodim_id
     LEFT JOIN im_xodimlar xy   ON xy.id = q.yopgan_xodim_id
     WHERE q.holat IN ('ochiq','yopildi') AND q.sana BETWEEN '$dan_s' AND '$gacha_s' $fil_where
     ORDER BY q.id DESC
     LIMIT 200"
);

$orqaga = im_BASE . ($im_rol === 'oshpaz' ? 'oshpaz/qozon.php'
        : ($im_rol === 'kassir' ? 'dukon/index.php' : 'qayta-ishlash/index.php'));
$jami_chiqdi_all = $jami['sotildi'] + $jami['qoldi'];
$real_all = $jami_chiqdi_all > 0 ? $jami['xomashyo'] / $jami_chiqdi_all : 0;
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Osh hisoboti | IMezon</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
  <style>
    .oh-wrap{max-width:1200px;margin:0 auto;padding:18px}
    .oh-top{display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}
    .oh-top h1{font-size:19px;font-weight:800;margin:0;display:flex;align-items:center;gap:8px}
    .oh-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}
  </style>
</head>
<body>
<div class="oh-wrap">

  <div class="oh-top">
    <a href="<?= $orqaga ?>" class="im-btn im-btn-outline im-btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h1><i class="bi bi-fire" style="color:var(--warning)"></i> Osh qozoni hisoboti</h1>
    <span style="flex:1"></span>
    <button class="im-btn im-btn-outline im-btn-sm" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
  </div>

  <div class="im-card mb-3">
    <div class="im-card-body">
      <form method="get" class="d-flex flex-wrap gap-3 align-items-end">
        <div>
          <label class="im-label">Dan</label>
          <input type="date" name="from" class="im-input" value="<?= im_f($dan) ?>">
        </div>
        <div>
          <label class="im-label">Gacha</label>
          <input type="date" name="to" class="im-input" value="<?= im_f($gacha) ?>">
        </div>
        <?php if ($is_admin): ?>
        <div>
          <label class="im-label">Filial</label>
          <select name="filial" class="im-input">
            <option value="0">Hammasi</option>
            <?php foreach ($filiallar as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $fil_get == $f['id'] ? 'selected' : '' ?>><?= im_f($f['nomi']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <button class="im-btn im-btn-primary"><i class="bi bi-search"></i> Ko'rish</button>
      </form>
    </div>
  </div>

  <div class="oh-stats">
    <div class="im-stat info">
      <div class="im-stat-icon info"><i class="bi bi-box-arrow-in-down"></i></div>
      <div class="im-stat-body"><div class="im-stat-label">Xomashyo puli</div>
        <div class="im-stat-value num" style="font-size:17px"><?= im_money($jami['xomashyo']) ?></div>
        <div class="im-stat-sub">so'm</div></div>
    </div>
    <div class="im-stat success">
      <div class="im-stat-icon success"><i class="bi bi-cup-hot-fill"></i></div>
      <div class="im-stat-body"><div class="im-stat-label">Sotildi</div>
        <div class="im-stat-value num"><?= number_format($jami['sotildi']) ?></div>
        <div class="im-stat-sub">porsiya</div></div>
    </div>
    <div class="im-stat danger">
      <div class="im-stat-icon danger"><i class="bi bi-trash3-fill"></i></div>
      <div class="im-stat-body"><div class="im-stat-label">Isrof (qoldi)</div>
        <div class="im-stat-value num"><?= number_format($jami['qoldi']) ?></div>
        <div class="im-stat-sub"><?= im_money($jami['isrof_q']) ?> so'm</div></div>
    </div>
    <div class="im-stat primary">
      <div class="im-stat-icon primary"><i class="bi bi-calculator-fill"></i></div>
      <div class="im-stat-body"><div class="im-stat-label">O'rtacha real tannarx</div>
        <div class="im-stat-value num" style="font-size:17px"><?= im_money($real_all) ?></div>
        <div class="im-stat-sub">so'm / porsiya</div></div>
    </div>
  </div>

  <div class="im-card mb-3">
    <div class="im-card-header">
      <i class="bi bi-table"></i><span class="im-card-title">Kunlik jamlanma</span>
      <span class="im-badge im-badge-info ms-2"><?= count($rows) ?></span>
    </div>
    <div class="im-table-wrap">
      <table class="im-table">
        <thead><tr>
          <th>Sana</th><?php if (!$fil_get): ?><th>Filial</th><?php endif; ?><th>Mahsulot</th>
          <th class="text-right">Qozon</th>
          <th class="text-right">Mo'ljal</th>
          <th class="text-right">Xomashyo</th>
          <th class="text-right">Sotildi</th>
          <th class="text-right">Qoldi</th>
          <th class="text-right">Jami chiqdi</th>
          <th class="text-right">Real tannarx</th>
          <th class="text-right">Retsept</th>
          <th class="text-right">Aniqlik</th>
        </tr></thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="12"><div class="im-empty"><i class="bi bi-fire"></i><h4>Ma'lumot yo'q</h4></div></td></tr>
          <?php else: foreach ($rows as $r):
            $rt = (float)$r['real_tannarx']; $et = (float)$r['ret_tannarx'];
            $qimmat = ($et > 0 && $rt > $et * 1.02);
            $an = $r['aniqlik'];
          ?>
          <tr>
            <td class="fw-semibold"><?= im_date($r['sana']) ?></td>
            <?php if (!$fil_get): ?><td class="text-muted fs-sm"><?= im_f($r['filial_nomi'] ?: '—') ?></td><?php endif; ?>
            <td><?= im_f($r['mahsulot_nomi']) ?>
              <?php if ((int)$r['ochiq_soni'] > 0): ?><span class="im-badge im-badge-warning ms-1" style="font-size:9px">ochiq</span><?php endif; ?>
            </td>
            <td class="text-right num"><?= (int)$r['qozon_soni'] ?></td>
            <td class="text-right num"><?= number_format((float)$r['moljal']) ?></td>
            <td class="text-right num"><?= im_money($r['xomashyo']) ?></td>
            <td class="text-right num"><?= number_format((float)$r['sotildi']) ?></td>
            <td class="text-right num">
              <span class="<?= (float)$r['qoldi_isrof'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format((float)$r['qoldi_isrof']) ?></span>
              <?php if ((float)$r['qoldi_keyingi'] > 0): ?>
              <span class="text-muted fs-xs d-block" title="Xom marinovka — keyingi kunga o'tdi, isrof emas">+<?= number_format((float)$r['qoldi_keyingi']) ?> →ertaga</span>
              <?php endif; ?>
            </td>
            <td class="text-right num fw-bold"><?= number_format((float)$r['jami_chiqdi']) ?></td>
            <td class="text-right num <?= $qimmat ? 'text-danger fw-bold' : '' ?>"><?= im_money($rt) ?></td>
            <td class="text-right num text-muted"><?= $et > 0 ? $r['ret_tannarx'] === null ? '—' : im_money($et) : '—' ?></td>
            <td class="text-right num">
              <?php if ($an === null): ?><span class="text-muted">—</span>
              <?php else: ?>
                <span class="<?= $an < 90 ? 'text-danger' : ($an > 110 ? 'text-success' : '') ?>"><?= round($an) ?>%</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="im-card">
    <div class="im-card-header">
      <i class="bi bi-list-ul"></i><span class="im-card-title">Qozonlar ro'yxati</span>
      <span class="im-badge im-badge-muted ms-2"><?= count($qozonlar) ?></span>
    </div>
    <div class="im-table-wrap">
      <table class="im-table">
        <thead><tr>
          <th>#</th><th>Sana</th><?php if (!$fil_get): ?><th>Filial</th><?php endif; ?><th>Mahsulot</th>
          <th class="text-right">Mo'ljal</th><th class="text-right">Xomashyo</th>
          <th class="text-right">Qoldi</th><th>Holat</th><th>Ochdi / Yopdi</th><th>Izoh</th>
        </tr></thead>
        <tbody>
          <?php if (!$qozonlar): ?>
          <tr><td colspan="11"><div class="im-empty"><i class="bi bi-inbox"></i><h4>Qozon yo'q</h4></div></td></tr>
          <?php else: foreach ($qozonlar as $q): ?>
          <tr>
            <td class="text-muted fs-xs"><?= $q['id'] ?></td>
            <td class="fs-sm"><?= im_date($q['sana']) ?></td>
            <?php if (!$fil_get): ?><td class="text-muted fs-sm"><?= im_f($q['filial_nomi'] ?: '—') ?></td><?php endif; ?>
            <td class="fw-semibold"><?= im_f($q['mahsulot_nomi']) ?></td>
            <td class="text-right num"><?= number_format((float)$q['moljal_porsiya']) ?></td>
            <td class="text-right num"><?= im_money($q['xomashyo_summa']) ?></td>
            <td class="text-right num <?= ((float)$q['qoldi_porsiya'] > 0 && (int)$q['qoldi_isrofmi'] === 1) ? 'text-danger' : 'text-muted' ?>">
              <?= number_format((float)$q['qoldi_porsiya']) ?>
              <?php if ((float)$q['qoldi_porsiya'] > 0 && (int)$q['qoldi_isrofmi'] === 0): ?>
              <span class="fs-xs">→ertaga</span>
              <?php endif; ?>
            </td>
            <td>
              <?= $q['holat'] === 'ochiq'
                ? '<span class="im-badge im-badge-warning">ochiq</span>'
                : '<span class="im-badge im-badge-success">yopildi</span>' ?>
            </td>
            <td class="text-muted fs-xs">
              <?= im_f($q['ochgan_ism'] ?: '—') ?>
              <?php if ($q['yopgan_ism']): ?> / <?= im_f($q['yopgan_ism']) ?><?php endif; ?>
            </td>
            <td class="text-muted fs-xs"><?= im_f($q['izoh'] ?: '') ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
</body>
</html>
