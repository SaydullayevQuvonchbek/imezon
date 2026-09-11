<?php
// ============================================================
//  IMezon — Sotuvchi: Mahsulotlar (kategoriya + rasm + qoldiq)
//
//  IKKI XIL MAHSULOT MODELI:
//   1) Oddiy (oshpaz_kerak=0) — vitrinada zaxira sifatida turadi,
//      im_filial_qoldiq.soni > 0 bo'lishi shart.
//   2) A-la-carte (oshpaz_kerak=1) — zaxirada TURMAYDI, buyurtma
//      bo'yicha pishiriladi. Xomashyo oshpaz "Qabul qildim" bosganda
//      sarflanadi (oshpaz/ajax/order-qabul.php). Shuning uchun bunday
//      mahsulot uchun "qoldiq" = retsept xomashyosidan nechta porsiya
//      chiqarish mumkinligi.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['sotuvchi', 'admin', 'kassir']);
header('Content-Type: application/json; charset=utf-8');

$db        = new Cyber();
$filial_id = (int)$_SESSION['im_filial_id'];
$q         = trim($_GET['q']   ?? '');
$kat       = (int)($_GET['kat'] ?? 0);

// A-la-carte mahsulot zaxirasiz ham ko'rinishi kerak — shuning uchun
// LEFT JOIN va "qoldiq > 0" sharti faqat oddiy mahsulotlarga.
$where = "m.status = 1 AND m.sotiladi = 1
          AND (m.oshpaz_kerak = 1 OR m.retsept_avto = 1 OR COALESCE(fq.soni, 0) > 0
               OR EXISTS (
                   SELECT 1 FROM im_retsept_items ari
                   JOIN im_retseptlar ar ON ar.id=ari.retsept_id
                   WHERE ari.mahsulot_id=m.id AND ari.soni>0
                     AND ar.tur='maydalash' AND ar.status=1
               ))";

if ($kat) {
    $where .= " AND m.kategoriya_id = $kat";
}
if ($q) {
    $qs = mysqli_real_escape_string($link, $q);
    $where .= " AND (m.nomi LIKE '%$qs%' OR m.barcode LIKE '%$qs%')";
}

$rows = $db->rows(
    "SELECT m.id, m.nomi, m.birlik, m.sotuv_qadami, m.rasm, m.oshpaz_kerak, m.retsept_avto,
            COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0) AS narx,
            COALESCE(fq.soni, 0)                        AS qoldiq,
            COALESCE(nu.min_soni, 0)                    AS ulg_min,
            COALESCE(nu.ulgurji_narxi, 0)               AS ulg_narx,
            k.nomi AS kat_nomi, m.kategoriya_id
     FROM im_mahsulotlar m
     LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id = m.id AND fq.filial_id = $filial_id
     LEFT JOIN im_kategoriyalar k  ON k.id  = m.kategoriya_id
     LEFT JOIN im_narxlar n        ON n.mahsulot_id = m.id
     LEFT JOIN im_narx_ulgurji nu  ON nu.mahsulot_id = m.id AND nu.aktiv = 1
     WHERE $where
     ORDER BY m.nomi ASC
     LIMIT 200"
);

// ── A-la-carte taomlar: XOMASHYO IMKONIYATI (cheklov emas!) ─────
// Bunday taom vitrinada turmaydi — oshpaz "Qabul qildim" bosganda
// ishlab chiqariladi. Shuning uchun qoldiq 0 bo'lsa ham ofitsant uni
// mijoz xohishiga qarab qo'sha oladi; hisoblangan imkoniyat faqat
// OGOHLANTIRISH sifatida ('imkon') qaytariladi.
// Xomashyo haqiqatan yetmasa — oshpaz qabul qilayotganda aniq xato
// beriladi (order-qabul.php), ya'ni nazorat o'sha yerda.
foreach ($rows as &$r) {
    $mid = (int)$r['id'];
    $auto = im_auto_maydalash_holati($db, $filial_id, $mid);
    $r['auto_maydalash'] = $auto ? 1 : 0;
    $r['tayyor_qoldiq']  = (float)$r['qoldiq'];
    $r['auto_imkon']     = $auto ? (float)$auto['auto_imkon'] : 0;
    if ($auto) $r['qoldiq'] = (float)$auto['jami_imkon'];

    // Ikkala tur ham vitrinada turmaydi:
    //   oshpaz_kerak — xomashyo oshpaz QABUL qilganda yechiladi
    //   retsept_avto — xomashyo SOTUV paytida yechiladi (choy, kofe...)
    $retseptli = (int)$r['oshpaz_kerak'] || (int)$r['retsept_avto'];
    if (!$retseptli) { $r['imkon'] = null; continue; }

    $r['qoldiq'] = 9999;   // sanoq cheklovi yo'q

    $retsept = $db->row(
        "SELECT id, chiqish_soni FROM im_retseptlar
         WHERE mahsulot_id=$mid AND tur='ishlab_chiqarish' AND status=1
         ORDER BY id DESC LIMIT 1"
    );

    if (!$retsept) {
        // Retseptsiz taom — xomashyo bilan bog'liq cheklov yo'q
        $r['imkon'] = null;
        continue;
    }

    $chiqish_1x = (float)$retsept['chiqish_soni'];
    if ($chiqish_1x <= 0) $chiqish_1x = 1;

    $items = $db->rows(
        "SELECT ri.mahsulot_id, ri.soni, COALESCE(fq.soni, 0) AS mavjud
         FROM im_retsept_items ri
         LEFT JOIN im_filial_qoldiq fq
                ON fq.mahsulot_id = ri.mahsulot_id AND fq.filial_id = $filial_id
         WHERE ri.retsept_id = {$retsept['id']}"
    );

    if (empty($items)) { $r['imkon'] = null; continue; }

    // Har bir xomashyo nechta "retsept bajarilishi" ga yetadi — eng kichigi cheklaydi
    $imkon = null;
    foreach ($items as $it) {
        $kerak = (float)$it['soni'];
        if ($kerak <= 0) continue;
        $marta = (float)$it['mavjud'] / $kerak;
        $imkon = ($imkon === null) ? $marta : min($imkon, $marta);
    }

    $r['imkon'] = ($imkon === null) ? null : (int)floor($imkon * $chiqish_1x);
}
unset($r);

// Faqat ODDIY (vitrinali) mahsulotda qoldiq sharti ishlaydi.
// Oshpaz tayyorlaydigan taom xomashyosi tugagan bo'lsa ham ro'yxatda
// qoladi — mijoz so'rasa ofitsant qo'sha olsin.
$rows = array_values(array_filter($rows, function ($r) {
    return (int)$r['oshpaz_kerak'] === 1 || (int)$r['retsept_avto'] === 1
        || (float)$r['qoldiq'] > 0;
}));

echo json_encode(['status' => 'ok', 'data' => $rows], JSON_UNESCAPED_UNICODE);
