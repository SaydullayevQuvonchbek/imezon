<?php
// ============================================================
//  IMezon — Chek Chop etish
//  URL: /print/chek.php?id=SOTUV_ID
//  Brauzerda: Ctrl+P → PDF → Chek ko'rinadi
//  Xprinter X80: 80mm qog'oz uchun optimallashtirilgan
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir','sklad']);

$sotuv_id = (int)($_GET['id'] ?? 0);
if (!$sotuv_id) die('<div style="font-family:monospace;padding:20px">❌ Sotuv ID kerak (?id=...)</div>');


$db = new Cyber();

// Sotuv ma'lumotlari — minimal join (smena join olib tashlandi)
$s = $db->row(
    "SELECT s.*, m.ism AS mijoz_ism, m.telefon AS mijoz_tel,
            k.ism AS kassir_ism,
            o.ism AS ofitsant_ism
     FROM im_sotuvlar s
     LEFT JOIN im_mijozlar m ON m.id=s.mijoz_id
     LEFT JOIN im_xodimlar k ON k.id=s.kassir_id
     LEFT JOIN im_xodimlar o ON o.id=s.sotuvchi_id
     WHERE s.id=$sotuv_id"
);
if (!$s) {
    // Debug: list available sotuv IDs
    $ids = $db->rows("SELECT id, chek_nomer FROM im_sotuvlar ORDER BY id DESC LIMIT 5");
    $list = implode(', ', array_map(fn($r) => "#{$r['id']} ({$r['chek_nomer']})", $ids));
    die('<div style="font-family:monospace;padding:20px">❌ Sotuv topilmadi (id='.$sotuv_id.') | Mavjud: '.$list.'</div>');
}

// Sotuv itemlari — set_id va set nomi bilan
$items = $db->rows(
    "SELECT si.*, m.nomi, m.birlik,
            s.nomi AS set_nomi
     FROM im_sotuv_items si
     JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
     LEFT JOIN im_setlar s ON s.id=si.set_id
     WHERE si.sotuv_id=$sotuv_id
     ORDER BY si.set_id ASC, si.id ASC"
);

// Bu chekdan olingan vozvratlar
$vozvratlar = $db->rows(
    "SELECT v.*, m.nomi AS mahsulot_nomi, m.birlik, k.ism AS kassir_ism
     FROM im_vozvratlar v
     JOIN im_mahsulotlar m ON m.id=v.mahsulot_id
     LEFT JOIN im_xodimlar k ON k.id=v.kassir_id
     WHERE v.sotuv_id=$sotuv_id
     ORDER BY v.id ASC"
);
$vozvrat_jami_sum = array_sum(array_column($vozvratlar, 'qaytarish_summa'));

$usd_kurs = im_usd_kurs();
$jami_usd = $usd_kurs > 0 ? round((float)$s['tolov_summa'] / $usd_kurs, 2) : 0;

// Do'kon ma'lumotlari — DB dan o'qiladi
$shop_name = im_sozlama('dukon_nomi', 'IMezon');
$shop_addr = im_sozlama('dukon_manzil', 'Toshkent sh., Chilonzor t.');
$shop_tel  = im_sozlama('dukon_telefon', '+998 71 123-45-67');
$shop_url  = im_sozlama('dukon_url', 'IMezon.uz');
$chek_izoh    = im_sozlama('chek_izoh', 'Tovar sifatiga kafolat beriladi');
$chek_rahmat  = im_sozlama('chek_rahmat', 'Xaridingiz uchun rahmat! 🙏');
$chek_telegram  = im_sozlama('chek_telegram', '');
$chek_instagram = im_sozlama('chek_instagram', '');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title>Chek #<?= im_f($s['chek_nomer']) ?></title>
<style>
/* ── Reset ── */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Courier New', Courier, monospace !important; }
body {
  font-weight: bold;
  color: #000;
  font-size: 13px;
  background: #f5f5f5;
  display: flex;
  justify-content: center;
  padding: 20px;
}

/* ── Chek wrapper ── */
.chek {
  background: #fff;
  width: 80mm;
  padding: 4mm 5mm;
  box-shadow: 0 2px 20px rgba(0,0,0,.12);
  border-radius: 4px;
}

/* ── Header ── */
.chek-header { text-align: center; margin-bottom: 4mm; }
.chek-header .shop-name { font-size: 18px; font-weight: 900; letter-spacing: 1px; color: #000; }
.chek-header .shop-sub  { font-size: 12px; color: #000; margin-top: 1mm; font-weight: bold; }

/* ── Divider ── */
.div  { border: none; border-top: 2px dashed #000; margin: 2.5mm 0; }
.div2 { border: none; border-top: 3px solid #000; margin: 2.5mm 0; }

/* ── Info satırlar ── */
.info { display: flex; justify-content: space-between; font-size: 12px; margin: 1mm 0; }
.info .lbl { color: #000; font-weight: 900; }
.info .val { font-weight: 900; text-align: right; color: #000; }

/* ── Mahsulotlar ── */
.items-header {
  display: grid;
  grid-template-columns: 1fr 20mm 22mm;
  font-size: 12px; font-weight: 900;
  padding: 1mm 0; color: #000; border-bottom: 2px solid #000;
}
.item {
  display: grid;
  grid-template-columns: 1fr 20mm 22mm;
  font-size: 13px;
  padding: 1.5mm 0;
  border-bottom: 2px dotted #000;
  align-items: start;
}
.item:last-child { border-bottom: none; }
.item .name  { font-weight: 900; word-break: break-word; line-height: 1.3; color: #000; }
.item .qty   { text-align: center; color: #000; font-weight: 900; }
.item .price { text-align: right; font-weight: 900; color: #000; }
.item .sub   { grid-column: 1/4; font-size: 11px; color: #000; margin-top: 0.5mm; font-weight: bold; }

/* ── Jami ── */
.total-section { margin: 2mm 0; }
.total-row { display: flex; justify-content: space-between; font-size: 13px; margin: 1.5mm 0; }
.total-row.main { font-size: 16px; font-weight: 900; border-top: 2px solid #000; padding-top: 2mm; margin-top: 2mm; }
.total-row .lbl { color: #000; font-weight: 900; }
.total-row .val { color: #000; font-weight: 900; }
.total-row.chegirma .val { color: #000; }
.total-row.naqd .val { color: #000; }
.total-row.karta .val { color: #000; }
.total-row.nasiya .val { color: #000; }

/* ── QR / Barcode zone ── */
.qr-zone { text-align: center; margin: 3mm 0; }
.chek-num { font-size: 13px; letter-spacing: 2px; font-weight: 900; color: #000; }

/* ── Footer ── */
.chek-footer { text-align: center; font-size: 12px; color: #000; margin-top: 2mm; font-weight: bold; }
.chek-footer .thank { font-size: 14px; font-weight: 900; color: #000; margin-bottom: 1mm; }

/* ── Nasiya eslatma ── */
.nasiya-box {
  border: 2px solid #000;
  border-radius: 3px;
  padding: 2mm;
  margin: 2mm 0;
}
.nasiya-box .title { font-weight: 900; font-size: 13px; color: #000; margin-bottom: 1mm; text-align: center; }

/* ── Print tugmasi (print vaqtida yashiriladi) ── */
.no-print {
  text-align: center;
  margin-top: 16px;
}
.btn-print {
  background: #1a1a2e;
  color: #e2b96f;
  border: none;
  padding: 10px 28px;
  font-size: 14px;
  font-weight: bold;
  border-radius: 8px;
  cursor: pointer;
  letter-spacing: .5px;
}
.btn-print:hover { background: #2d2d54; }
.btn-close {
  background: #f0f2f5;
  color: #333;
  border: none;
  padding: 10px 20px;
  font-size: 14px;
  border-radius: 8px;
  cursor: pointer;
  margin-left: 8px;
}

/* ── @media print ── */
@media print {
  body { background: none; padding: 0; }
  .chek { box-shadow: none; border-radius: 0; width: 80mm; padding: 0 2mm; margin: 0; }
  .no-print { display: none !important; }
  @page {
    size: 80mm auto;
    margin: 4mm 2mm;
  }
}
</style>
</head>
<body>

<div class="chek" id="chek-content">
  <!-- HEADER -->
  <div class="chek-header">
    <div class="shop-name"><?= im_f($shop_name) ?></div>
    <div class="shop-sub"><?= im_f($shop_addr) ?></div>
    <div class="shop-sub">Tel: <?= im_f($shop_tel) ?> | <?= im_f($shop_url) ?></div>
  </div>

  <hr class="div2">

  <!-- CHEK INFO -->
  <div class="info"><span class="lbl">Chek:</span><span class="val"><?= im_f($s['chek_nomer']) ?></span></div>
  <div class="info"><span class="lbl">Sana:</span><span class="val"><?= date('d.m.Y H:i', strtotime($s['sana'])) ?></span></div>
  <div class="info"><span class="lbl">Kassir:</span><span class="val"><?= im_f($s['kassir_ism'] ?? '—') ?></span></div>
  <!-- Manba (Stol / Dastavka / To'g'ridan-to'g'ri) DOIM chiqadi.
       Mijoz esa faqat tanlangan bo'lsa — u alohida tushuncha. -->
  <div class="info"><span class="lbl">Manba:</span><span class="val"><?= im_f($s['manba'] ?: "To'g'ridan-to'g'ri") ?></span></div>
  <?php if (mb_stripos((string)$s['manba'], 'Olib ketish', 0, 'UTF-8') !== false): ?>
  <div class="nasiya-box" style="text-align:center;font-size:14px;font-weight:900">🛍️ OLIB KETISH — ALOHIDA CHEK</div>
  <?php endif; ?>
  <?php if ($s['ofitsant_ism']): ?>
  <div class="info"><span class="lbl">Ofitsant:</span><span class="val"><?= im_f($s['ofitsant_ism']) ?></span></div>
  <?php endif;?>
  <?php if ($s['mijoz_ism']): ?>
  <div class="info"><span class="lbl">Mijoz:</span><span class="val"><?= im_f($s['mijoz_ism']) ?><?= $s['mijoz_tel'] ? " ({$s['mijoz_tel']})" : '' ?></span></div>
  <?php endif;?>

  <hr class="div">

  <!-- MAHSULOTLAR -->
  <div class="items-header">
    <span>Nomi</span><span style="text-align:center">Soni</span><span style="text-align:right">Narx</span>
  </div>
  <hr class="div">

  <?php
  // Itemlarni guruhlash: set_id bo'lganlar birlashtiriladi
  $groups = []; // [['type'=>'set','set_id'=>...,'set_nomi'=>...,'items'=>[...],'jami'=>...], ['type'=>'item','item'=>...]]
  foreach ($items as $item) {
      if ($item['set_id']) {
          $sid = (int)$item['set_id'];
          if (!isset($groups['set_'.$sid])) {
              $groups['set_'.$sid] = [
                  'type'     => 'set',
                  'set_id'   => $sid,
                  'set_nomi' => $item['set_nomi'] ?: ('Set #'.$sid),
                  'items'    => [],
                  'jami'     => 0,
              ];
          }
          $groups['set_'.$sid]['items'][] = $item;
          $groups['set_'.$sid]['jami'] += (float)$item['chegirma_narxi'] * (float)$item['soni'];
      } else {
          $groups[] = ['type' => 'item', 'item' => $item];
      }
  }

  foreach ($groups as $g):
    if ($g['type'] === 'set'):
      // Set tarkib qisqacha ko'rinishi
      $tarkib = implode(', ', array_map(fn($it) =>
          im_f($it['nomi']).' '.((float)$it['soni']+0).' '.$it['birlik'],
          $g['items']
      ));
  ?>
  <div class="item">
    <span class="name">&#127873; <?= im_f($g['set_nomi']) ?></span>
    <span class="qty">1 set</span>
    <span class="price"><?= im_money($g['jami']) ?></span>
    <span class="sub"><?= $tarkib ?></span>
  </div>
  <?php else:
      $item     = $g['item'];
      $chegirma = (float)$item['sotish_narxi'] - (float)$item['chegirma_narxi'];
  ?>
  <div class="item">
    <span class="name"><?= im_f($item['nomi']) ?></span>
    <span class="qty"><?= (float)$item['soni']+0 ?> <?= im_f($item['birlik'] ?? 'dona') ?></span>
    <span class="price"><?= im_money($item['chegirma_narxi']) ?></span>
    <?php if ($chegirma > 0 || (float)$item['soni'] > 1): ?>
    <span class="sub">
      <?= (float)$item['soni'] > 1 ? im_money($item['chegirma_narxi']).' &times; '.(float)$item['soni'].' = '.im_money((float)$item['chegirma_narxi']*(float)$item['soni'])." so'm" : '' ?>
      <?= $chegirma > 0 ? ' | -'.im_money($chegirma).' chegirma' : '' ?>
    </span>
    <?php endif; ?>
  </div>
  <?php endif; endforeach; ?>

  <hr class="div2">

  <!-- JAMI -->
  <div class="total-section">
    <?php if ((float)$s['chegirma_summa'] > 0): ?>
    <div class="total-row">
      <span class="lbl">Jami (chegirmadan oldin):</span>
      <span class="val"><?= im_money((float)$s['tolov_summa'] + (float)$s['chegirma_summa']) ?> so'm</span>
    </div>
    <div class="total-row chegirma">
      <span class="lbl">Chegirma:</span>
      <span class="val">-<?= im_money($s['chegirma_summa']) ?> so'm</span>
    </div>
    <?php endif; ?>
    <?php if ((float)$s['xizmat_summa'] > 0): ?>
    <!-- Xizmat haqi ALOHIDA qator bo'lishi shart — mijoz nega ortiqcha
         to'layotganini chekdan ko'rishi kerak. Ustidagi qator esa
         mahsulotlar summasi (xizmat haqisiz). -->
    <div class="total-row">
      <span class="lbl">Mahsulotlar:</span>
      <span class="val"><?= im_money((float)$s['tolov_summa'] - (float)$s['xizmat_summa']) ?> so'm</span>
    </div>
    <div class="total-row">
      <span class="lbl">Xizmat haqi (<?= rtrim(rtrim(number_format((float)$s['xizmat_foiz'], 2, '.', ''), '0'), '.') ?>%):</span>
      <span class="val">+<?= im_money($s['xizmat_summa']) ?> so'm</span>
    </div>
    <?php endif; ?>
    <div class="total-row main">
      <span class="lbl">JAMI TO'LOV:</span>
      <span class="val"><?= im_money($s['tolov_summa']) ?> so'm</span>
    </div>
  </div>

  <hr class="div">

  <!-- TO'LOV TURLARI -->
  <?php if ((float)$s['naqd_summa'] > 0): ?>
  <div class="total-row naqd"><span class="lbl">💵 Naqd:</span><span class="val"><?= im_money($s['naqd_summa']) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ((float)$s['karta_summa'] > 0): ?>
  <div class="total-row karta"><span class="lbl"><?= im_tt_nomi("karta", true) ?>:</span><span class="val"><?= im_money($s['karta_summa']) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ((float)$s['bank_summa'] > 0): ?>
  <div class="total-row"><span class="lbl"><?= im_tt_nomi("bank", true) ?>:</span><span class="val"><?= im_money($s['bank_summa']) ?> so'm</span></div>
  <?php endif; ?>
  <?php if ((float)$s['usd_summa'] > 0): ?>
  <div class="total-row">
    <span>🪙 USD (<span style="font-size:9px"><?= im_money($usd_kurs) ?> so'm/USD</span>):</span>
    <span>$ <?= number_format((float)$s['usd_summa'], 2) ?> = <?= im_money((float)$s['usd_summa'] * $usd_kurs) ?> so'm</span>
  </div>
  <?php endif; ?>

  <!-- NASIYA -->
  <?php if ((float)$s['nasiya_summa'] > 0): ?>
  <hr class="div">
  <div class="nasiya-box">
    <div class="title">⚠️ NASIYA QISMI</div>
    <div class="info"><span class="lbl">Nasiya summa:</span><span class="val"><?= im_money($s['nasiya_summa']) ?> so'm</span></div>
    <?php
    $nasiya = $db->row("SELECT * FROM im_nasiya WHERE sotuv_id=$sotuv_id LIMIT 1");
    if ($nasiya): ?>
    <div class="info"><span class="lbl">Qaytarish sanasi:</span><span class="val"><?= im_date($nasiya['qaytarish_sana']) ?></span></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <hr class="div">

  <!-- QAYTIM (agar naqd ortiqcha berilgan bo'lsa) -->
  <?php
  $berildi = (float)$s['naqd_berildi'];
  $qaytim  = $berildi - (float)$s['naqd_summa'];
  if ($berildi > 0 && $qaytim >= 0):
  ?>
  <div class="info"><span class="lbl">Berildi:</span><span class="val"><?= im_money($berildi) ?> so'm</span></div>
  <div class="info"><span class="lbl">Qaytim:</span><span class="val"><?= im_money($qaytim) ?> so'm</span></div>
  <hr class="div">
  <?php endif; ?>

  <!-- USD QAYTIM (agar USD ortiqcha to'langan bo'lsa) -->
  <?php
  // USD qaytim: USD so'mdagi ekvivalenti - (tolov minus boshqa to'lovlar)
  $usd_som_ekv = (float)$s['usd_som_ekviv'];
  if ($usd_som_ekv <= 0 && (float)$s['usd_summa'] > 0) {
      $usd_som_ekv = (float)$s['usd_summa'] * $usd_kurs;
  }
  $boshqa_tolov = (float)$s['naqd_summa'] + (float)$s['karta_summa'] + (float)$s['bank_summa'] + (float)$s['nasiya_summa'];
  $usd_kerak_som = (float)$s['tolov_summa'] - $boshqa_tolov;
  $usd_qaytim_som = ($usd_som_ekv > 0 && $usd_kerak_som > 0) ? ($usd_som_ekv - $usd_kerak_som) : 0;
  if ($usd_qaytim_som > 100): // 100 so'mdan kam bo'lsa kurs farqi (ko'rsatmaymiz)
  ?>
  <div class="info" style="font-weight:900">
    <span class="lbl">💲 Berildi (USD):</span>
    <span class="val">$<?= number_format((float)$s['usd_summa'], 2) ?> = <?= im_money($usd_som_ekv) ?> so'm</span>
  </div>
  <div class="info" style="font-weight:900">
    <span class="lbl">💵 Qaytim (so'mda):</span>
    <span class="val"><?= im_money($usd_qaytim_som) ?> so'm</span>
  </div>
  <hr class="div">
  <?php endif; ?>

  <!-- VOZVRAT ESLATMASI (agar vozvrat bo'lgan bo'lsa) -->
  <?php if (!empty($vozvratlar)): ?>
  <hr class="div2">
  <div style="border:2px solid #000;border-radius:3px;padding:2mm;margin:2mm 0;background:#fff">
    <div style="font-weight:900;font-size:13px;text-align:center;margin-bottom:1.5mm">&#9888;&#xFE0F; BU CHEKDAN QAYTARILGAN</div>
    <?php foreach ($vozvratlar as $vz): ?>
    <div style="display:flex;justify-content:space-between;font-size:11px;margin:1mm 0;border-bottom:1px dotted #000;padding-bottom:1mm">
      <span><?= im_f($vz['mahsulot_nomi']) ?> &times; <?= (int)$vz['soni'] ?> <?= im_f($vz['birlik'] ?? 'dona') ?></span>
      <span style="font-weight:900"><?= im_money($vz['qaytarish_summa']) ?> so'm</span>
    </div>
    <?php if ($vz['sabab']): ?>
    <div style="font-size:10px;color:#555;margin-top:0.5mm">Sabab: <?= im_f($vz['sabab']) ?></div>
    <?php endif; ?>
    <?php endforeach; ?>
    <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:900;margin-top:1.5mm;border-top:2px solid #000;padding-top:1mm">
      <span>Jami qaytarilgan:</span>
      <span><?= im_money($vozvrat_jami_sum) ?> so'm</span>
    </div>
  </div>
  <?php endif; ?>

  <!-- QR / CHEK RAQAM -->
  <div class="qr-zone">
    <div style="font-size:9px;color:#000;margin-bottom:1mm">CHEK RAQAMI</div>
    <div class="chek-num"><?= im_f($s['chek_nomer']) ?></div>
    <div style="font-size:9px;color:#000;letter-spacing:1px;margin-top:1mm">
      <?php echo str_repeat('|', 32); // Barcode imitation ?>
    </div>
  </div>

  <hr class="div">

  <!-- FOOTER -->
  <div class="chek-footer">
    <div class="thank"><?= im_f($chek_rahmat) ?></div>
    <div><?= im_f($chek_izoh) ?></div>
    <?php if ($chek_telegram || $chek_instagram || $shop_url): ?>
    <div style="margin-top:2mm;font-size:11px">
      <?php if ($chek_telegram): ?>✈ <?= im_f($chek_telegram) ?> &nbsp;<?php endif; ?>
      <?php if ($chek_instagram): ?>📸 <?= im_f($chek_instagram) ?><?php endif; ?>
    </div>
    <?php if ($shop_url): ?>
    <div style="margin-top:1mm;font-size:10px"><?= im_f($shop_url) ?></div>
    <?php endif; ?>
    <?php endif; ?>
    <div style="margin-top:2mm;font-size:9px;color:#000">
      IMezon POS v1.0 · <?= date('d.m.Y H:i:s') ?>
    </div>
  </div>
</div>

<!-- Print tugmasi (ekranda ko'rinadi, print vaqtida yashiriladi) -->
<div class="no-print">
  <button class="btn-print" onclick="window.print()">
    🖨️ Chop etish (Ctrl+P)
  </button>
  <button class="btn-close" onclick="window.close()">✕ Yopish</button>
  <div style="margin-top:8px;font-size:12px;color:#888">
    💡 Chek apparatsiz test: <strong>Chop et → PDF saqla</strong>
  </div>
</div>

<script>
// Chek apparati bor bo'lsa, avtomatik print + chop etilgach oynani yopish
const autoPrint = <?= isset($_GET['auto']) ? 'true' : 'false' ?>;
if (autoPrint) {
  let _yopildi = false;
  const yopish = () => { if (_yopildi) return; _yopildi = true; window.close(); };
  // afterprint — print dialogi yopilgach (chop etilsa ham, bekor qilinsa ham)
  window.addEventListener('afterprint', yopish);
  window.addEventListener('load', () => {
    setTimeout(() => {
      window.print();            // ko'p brauzerda bu qator bloklaydi
      setTimeout(yopish, 300);   // afterprint ishlamagan brauzerlar uchun zaxira
    }, 500);
  });
}
</script>
</body>
</html>
