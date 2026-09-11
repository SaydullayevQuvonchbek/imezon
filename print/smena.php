<?php
// Smena hisoboti — chop etish
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$smena_id = (int)($_GET['id'] ?? 0);
if (!$smena_id) die('<div style="font-family:monospace;padding:20px">❌ Smena ID kerak</div>');

$smena = $db->row(
    "SELECT s.*, x.ism AS kassir_ism
     FROM im_smena s
     LEFT JOIN im_xodimlar x ON x.id=s.kassir_id
     WHERE s.id=$smena_id"
);
if (!$smena) die('<div style="font-family:monospace;padding:20px">❌ Smena topilmadi</div>');

$stats = $db->row(
    "SELECT COUNT(*) AS sotuv_soni,
            COALESCE(SUM(tolov_summa),0)    AS jami,
            COALESCE(SUM(naqd_summa),0)     AS naqd,
            COALESCE(SUM(karta_summa),0)    AS karta,
            COALESCE(SUM(bank_summa),0)     AS bank,
            COALESCE(SUM(usd_summa),0)      AS usd,
            COALESCE(SUM(usd_som_ekviv),0)  AS usd_som,
            COALESCE(SUM(usd_qaytim_som),0) AS usd_qaytim,
            COALESCE(SUM(nasiya_summa),0)   AS nasiya,
            COALESCE(SUM(chegirma_summa),0) AS chegirma
     FROM im_sotuvlar WHERE smena_id=$smena_id"
);

$harajat = (float)$db->val(
    "SELECT COALESCE(SUM(summa),0) FROM im_harajatlar
     WHERE filial_id={$smena['filial_id']} AND created_at >= '{$smena['ochildi']}'"
) ?? 0;

// Vozvratlar — shu smena davomida qaytarilgan mahsulotlar summasi
$vozvrat_sum = (float)$db->val(
    "SELECT COALESCE(SUM(v.qaytarish_summa),0) FROM im_vozvratlar v
     JOIN im_sotuvlar s ON s.id=v.sotuv_id
     WHERE s.smena_id=$smena_id"
);

$top = $db->rows(
    "SELECT m.nomi, SUM(si.soni) AS soni,
            SUM(si.chegirma_narxi*si.soni) AS summa
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id=si.sotuv_id
     JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
     WHERE s.smena_id=$smena_id
     GROUP BY m.id ORDER BY summa DESC LIMIT 10"
);

// Inkasso (kassadan olingan) — foyda hisobiga KIRMAYDI
$ochildi = $smena['ochildi'];
$yopildi = $smena['yopildi'] ?? date('Y-m-d H:i:s');
$inkasso_jami = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_inkasasiya WHERE xodim_id={$smena['kassir_id']} AND sana >= '$ochildi' AND sana <= '$yopildi'");
$inkasso_list = $db->rows("SELECT summa, tolov_turi AS tur, sana, izoh FROM im_inkasasiya WHERE xodim_id={$smena['kassir_id']} AND sana >= '$ochildi' AND sana <= '$yopildi' ORDER BY sana ASC");

// ── Sof sotuv = Sotuv - Vozvrat ──
$sotuv_netto = (float)$stats['jami'] - $vozvrat_sum;

$shop_name = im_sozlama('dukon_nomi','IMezon');
$shop_tel  = im_sozlama('dukon_telefon','+998 71 123-45-67');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<title>Smena #<?= $smena_id ?> Hisoboti</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Courier New',monospace; font-size:12px; background:#f5f5f5; display:flex; justify-content:center; padding:20px; }
.wrap { background:#fff; width:80mm; padding:4mm 5mm; box-shadow:0 2px 20px rgba(0,0,0,.1); border-radius:4px; }
.center { text-align:center; }
.shop-name { font-size:15px; font-weight:bold; }
.div  { border:none; border-top:1px dashed #aaa; margin:2.5mm 0; }
.div2 { border:none; border-top:2px solid #333; margin:2.5mm 0; }
.row  { display:flex; justify-content:space-between; margin:1.5mm 0; font-size:11px; }
.row.big { font-size:13px; font-weight:bold; }
.row.profit { font-size:14px; font-weight:900; }
.muted { color:#777; }
.val   { font-weight:bold; }
.green { color:#27ae60; }
.red   { color:#e74c3c; }
.orange { color:#e67e22; }
table { width:100%; border-collapse:collapse; font-size:10.5px; margin-top:2mm; }
th { background:#1a1a2e; color:#fff; padding:4px 5px; text-align:left; }
td { padding:3px 5px; border-bottom:1px dotted #eee; }
.no-print { text-align:center; margin-top:16px; }
.btn { background:#1a1a2e; color:#e2b96f; border:none; padding:9px 22px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:bold; margin:3px; }
@media print {
  body { background:none; padding:0; }
  .wrap { box-shadow:none; width:80mm; }
  .no-print { display:none; }
  @page { size:80mm auto; margin:3mm 2mm; }
}
</style>
</head>
<body>
<div class="wrap">
  <div class="center" style="margin-bottom:3mm">
    <div class="shop-name"><?= im_f($shop_name) ?></div>
    <div class="muted" style="font-size:10px"><?= im_f($shop_tel) ?></div>
  </div>
  <hr class="div2">

  <div class="center" style="margin-bottom:2mm">
    <div style="font-size:13px;font-weight:bold">SMENA HISOBOTI</div>
    <div class="muted" style="font-size:10px">#<?= $smena_id ?></div>
  </div>
  <hr class="div">

  <div class="row"><span class="muted">Kassir:</span><span class="val"><?= im_f($smena['kassir_ism'] ?? '-') ?></span></div>
  <div class="row"><span class="muted">Boshlandi:</span><span class="val"><?= $smena['ochildi'] ? date('d.m.Y H:i', strtotime($smena['ochildi'])) : '—' ?></span></div>
  <div class="row"><span class="muted">Yopildi:</span><span class="val"><?= $smena['yopildi'] ? date('d.m.Y H:i', strtotime($smena['yopildi'])) : 'Ochiq' ?></span></div>
  <?php if ((float)$smena['ochish_naqd'] > 0): ?>
  <div class="row"><span class="muted">Boshlang'ich naqd:</span><span class="val"><?= im_money($smena['ochish_naqd']) ?> so'm</span></div>
  <?php endif; ?>

  <hr class="div">

  <div class="row big"><span>Jami sotuv:</span><span><?= (int)$stats['sotuv_soni'] ?> ta</span></div>
  <div class="row big"><span>Jami tushum:</span><span class="green"><?= im_money($stats['jami']) ?> so'm</span></div>

  <hr class="div">

  <?php if ((float)$stats['naqd'] > 0): ?>
  <div class="row"><span class="muted">💵 Naqd savdo:</span><span><?= im_money($stats['naqd']) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ((float)$stats['karta'] > 0): ?>
  <div class="row"><span class="muted"><?= im_tt_nomi("karta", true) ?>:</span><span><?= im_money($stats['karta']) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ((float)$stats['bank'] > 0): ?>
  <div class="row"><span class="muted"><?= im_tt_nomi("bank", true) ?>:</span><span><?= im_money($stats['bank']) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ((float)$stats['usd'] > 0): ?>
  <div class="row"><span class="muted">🪙 USD ($<?= number_format($stats['usd'],2) ?>):</span><span><?= im_money($stats['usd_som']) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ((float)$stats['usd_qaytim'] > 0): ?>
  <div class="row"><span class="muted">↩️ USD qaytim (naqd):</span><span class="red">-<?= im_money($stats['usd_qaytim']) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ((float)$stats['nasiya'] > 0): ?>
  <div class="row"><span class="muted">📋 Nasiya:</span><span class="orange"><?= im_money($stats['nasiya']) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ((float)$stats['chegirma'] > 0): ?>
  <div class="row"><span class="muted">🏷️ Chegirma:</span><span class="red">-<?= im_money($stats['chegirma']) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ($vozvrat_sum > 0): ?>
  <div class="row"><span class="muted">↩️ Vozvrat:</span><span class="red">-<?= im_money($vozvrat_sum) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ($harajat > 0): ?>
  <div class="row"><span class="muted">💸 Harajatlar:</span><span class="red">-<?= im_money($harajat) ?> so'm</span></div>
  <?php endif; ?>

  <?php if (count($inkasso_list) > 0): ?>
  <hr class="div">
  <div class="row" style="margin-top:1mm"><span style="font-weight:bold">💼 Kassadan olindi (Topshirildi):</span><span style="color:#7c3aed;font-weight:bold"></span></div>
  <div class="row"><span class="muted" style="font-size:9.5px">⚠ Bu inkassatsiya tariqasida pul topshirish</span><span></span></div>
  <?php foreach ($inkasso_list as $ink): ?>
  <div class="row" style="padding-left:3mm">
     <span class="muted"><?= date('H:i', strtotime($ink['sana'])) ?> · <?= im_f($ink['izoh']?:$ink['tur']) ?></span>
     <span class="muted">
       <?= $ink['tur'] === 'usd' ? '$'.number_format($ink['summa'], 2) : im_money($ink['summa'])." so'm" ?>
     </span>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <hr class="div2">

  <div class="row" style="font-size:11px;margin-bottom:1mm;font-weight:bold;">
    <span class="">Sotuv netto (vozvratsiz):</span>
    <span><?= im_money($sotuv_netto) ?> so'm</span>
  </div>

  <?php if ($top): ?>
  <hr class="div">
  <div style="font-weight:bold;font-size:11px;margin-bottom:1mm">🏆 Top mahsulotlar:</div>
  <table>
    <thead><tr><th>#</th><th>Nomi</th><th>Soni</th><th>Summa</th></tr></thead>
    <tbody>
    <?php foreach ($top as $i => $t): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><?= im_f($t['nomi']) ?></td>
      <td><?= (int)$t['soni'] ?> dona</td>
      <td><?= im_money($t['summa']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <hr class="div">
  <div class="center muted" style="font-size:9px">
    IMezon POS v1.0 · <?= date('d.m.Y H:i:s') ?>
  </div>
</div>

<div class="no-print">
  <button class="btn" onclick="window.print()">🖨️ Chop etish</button>
  <button class="btn" style="background:#f0f2f5;color:#333" onclick="window.close()">✕ Yopish</button>
</div>
<script>
let _yopildi = false;
const yopish = () => { if (_yopildi) return; _yopildi = true; window.close(); };
window.addEventListener('afterprint', yopish);
window.addEventListener('load', () => setTimeout(() => {
  window.print();
  setTimeout(yopish, 300);
}, 400));
</script>
</body>
</html>
