<?php
// ============================================================
//  IMezon — Smena tafsiloti (AJAX)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$id = (int)($_GET['id'] ?? 0);
if (!$id) im_json('error', 'ID kerak');

$smena = $db->row(
    "SELECT s.*, x.ism AS kassir_ism
     FROM im_smena s
     LEFT JOIN im_xodimlar x ON x.id=s.kassir_id
     WHERE s.id=$id"
);
if (!$smena) im_json('error', 'Smena topilmadi');

// Kassir o'z smenasini ko'rishi mumkin, admin hammasini
if ($im_rol !== 'admin' && (int)$smena['kassir_id'] !== $im_user_id) {
    im_json('error', 'Ruxsat yo\'q');
}

$stats = $db->row(
    "SELECT COUNT(*) AS sotuv_soni,
            COALESCE(SUM(tolov_summa),0)    AS jami,
            COALESCE(SUM(naqd_summa),0)     AS naqd,
            COALESCE(SUM(karta_summa),0)    AS karta,
            COALESCE(SUM(bank_summa),0)     AS bank,
            COALESCE(SUM(usd_summa),0)      AS usd,
            COALESCE(SUM(usd_som_ekviv),0)  AS usd_som,
            COALESCE(SUM(nasiya_summa),0)   AS nasiya,
            COALESCE(SUM(chegirma_summa),0) AS chegirma
     FROM im_sotuvlar WHERE smena_id=$id"
);

$ochildi = $smena['ochildi'];
$yopildi = $smena['yopildi'] ?? date('Y-m-d H:i:s');

// Ochiq smena bo'lsa va kassir_id filter
$kassir_id = (int)$smena['kassir_id'];

// 1. Tushum (Sotuvlar)
$stats = $db->row(
    "SELECT COUNT(*) AS sotuv_soni,
            COALESCE(SUM(tolov_summa),0)    AS jami,
            COALESCE(SUM(naqd_summa),0)     AS naqd,
            COALESCE(SUM(karta_summa),0)    AS karta,
            COALESCE(SUM(bank_summa),0)     AS bank,
            COALESCE(SUM(usd_summa),0)      AS usd,
            COALESCE(SUM(usd_som_ekviv),0)  AS usd_som,
            COALESCE(SUM(nasiya_summa),0)   AS nasiya,
            COALESCE(SUM(chegirma_summa),0) AS chegirma
     FROM im_sotuvlar WHERE smena_id=$id"
);

// 2. Harajatlar (Xarajat)
$xarajat_raw = $db->rows("SELECT tolov_turi, COALESCE(SUM(summa),0) as summa, COALESCE(SUM(usd_summa),0) as usd_sum FROM im_harajatlar WHERE xodim_id=$kassir_id AND sana >= '$ochildi' AND sana <= '$yopildi' GROUP BY tolov_turi");
$xarajat = ['naqd'=>0, 'karta'=>0, 'bank'=>0, 'usd'=>0];
foreach($xarajat_raw as $r) {
    $t = $r['tolov_turi'] ?: 'naqd';
    if($t == 'usd') $xarajat['usd'] += $r['usd_sum'];
    else $xarajat[$t] += $r['summa'];
}

// 3. Nasiya Qaytimi (Nasiya to'lovlari)
$nasiya_tolov_raw = $db->rows("SELECT tolov_turi, COALESCE(SUM(summa),0) as summa, COALESCE(SUM(usd_summa),0) as usd_sum FROM im_nasiya_tolov WHERE kassir_id=$kassir_id AND sana >= '$ochildi' AND sana <= '$yopildi' GROUP BY tolov_turi");
$nasiya_tolov = ['naqd'=>0, 'karta'=>0, 'bank'=>0, 'usd'=>0];
foreach($nasiya_tolov_raw as $r) {
    $t = $r['tolov_turi'] ?: 'naqd';
    if($t == 'usd') $nasiya_tolov['usd'] += $r['usd_sum'];
    else $nasiya_tolov[$t] += $r['summa'];
}

// 4. Vozvrat
$vozvrat_raw = $db->rows("SELECT qaytarildi_tur, COALESCE(SUM(qaytarish_summa),0) as summa FROM im_vozvratlar WHERE kassir_id=$kassir_id AND sana >= '$ochildi' AND sana <= '$yopildi' GROUP BY qaytarildi_tur");
$vozvrat = ['naqd'=>0, 'karta'=>0, 'bank'=>0, 'usd'=>0];
foreach($vozvrat_raw as $r) {
    $t = $r['qaytarildi_tur'];
    if (isset($vozvrat[$t])) {
        $vozvrat[$t] += $r['summa'];
    }
}

// 5. Inkassatsiya
$inkasso_raw = $db->rows("SELECT summa, tolov_turi AS tur, usd_summa, sana, izoh FROM im_inkasasiya WHERE xodim_id=$kassir_id AND sana >= '$ochildi' AND sana <= '$yopildi' ORDER BY sana ASC");
$inkasso_types = ['naqd'=>0, 'karta'=>0, 'bank'=>0, 'usd'=>0];
$inkasso_jami = 0;
foreach($inkasso_raw as $r) {
    $t = $r['tur'] ?: 'naqd';
    if($t == 'usd') {
        $inkasso_types['usd'] += $r['usd_summa'];
        $inkasso_jami += $r['usd_summa']; // we keep sum for ui list
    } else {
        $inkasso_types[$t] += $r['summa'];
        $inkasso_jami += $r['summa'];
    }
}

// 6. USD Qaytim va Naqd qaytim (sotuvda qayd etilgan)
$usd_qaytim_naqd = (float)$db->val(
    "SELECT COALESCE(SUM(usd_qaytim_som),0) FROM im_sotuvlar WHERE smena_id=$id"
);
$naqd_qaytim_jami = (float)$db->val(
    "SELECT COALESCE(SUM(naqd_berildi),0) FROM im_sotuvlar WHERE smena_id=$id"
);

$top_mah = $db->rows(
    "SELECT m.nomi, SUM(si.soni) AS soni, SUM(si.chegirma_narxi*si.soni) AS summa
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id=si.sotuv_id
     JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
     WHERE s.smena_id=$id
     GROUP BY m.id ORDER BY summa DESC LIMIT 5"
);

// Yakuniy Kassa hisobi (Real o'zgarish = Tushum + Nasiya_Qaytim - Xarajat - Inkassa - Vozvrat)
// USD_qaytim -> bu naqd puldan chiqim.
$stats['naqd'] -= $usd_qaytim_naqd;
$yakuniy = [
    'naqd'  => (float)$stats['naqd'] + $nasiya_tolov['naqd'] - $xarajat['naqd'] - $inkasso_types['naqd'] - $vozvrat['naqd'],
    'karta' => (float)$stats['karta'] + $nasiya_tolov['karta'] - $xarajat['karta'] - $inkasso_types['karta'] - $vozvrat['karta'],
    'bank'  => (float)$stats['bank'] + $nasiya_tolov['bank'] - $xarajat['bank'] - $inkasso_types['bank'] - $vozvrat['bank'],
    'usd'   => (float)$stats['usd'] + $nasiya_tolov['usd'] - $xarajat['usd'] - $inkasso_types['usd'] - $vozvrat['usd']
];

im_json('ok', '', [
    'smena'         => $smena,
    'stats'         => $stats,
    'inkasso'       => $inkasso_raw,
    'inkasso_jami'  => $inkasso_jami,
    'inkasso_types' => $inkasso_types,
    'top_mah'       => $top_mah,
    'xarajat'       => $xarajat,
    'nasiya_tolov'  => $nasiya_tolov,
    'vozvrat'       => $vozvrat,
    'usd_qaytim'    => $usd_qaytim_naqd,
    'naqd_qaytim'   => $naqd_qaytim_jami,
    'yakuniy'       => $yakuniy
]);
