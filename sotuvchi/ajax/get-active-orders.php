<?php
// ============================================================
//  IMezon — Sotuvchi: Barcha faol ochiq stollarni olish
//  (Server-sinxron B variant)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
// Admin/kassir ham stol buyurtmasining ichini ko'ra olishi kerak — dukon POS
// zal xaritasidan stolga bosilganda (dukon/pos.php → openStolEdit) aynan shu
// endpoint chaqiriladi. Ilgari bu yerda faqat 'sotuvchi' turardi, shu sababli
// kassirga 403 qaytar va POS stolni BO'SH savat bilan ochar edi.
// order-save.php allaqachon shu uch rolga ochiq — ro'yxat u bilan bir xil.
im_rol_check(['sotuvchi', 'admin', 'kassir']);

header('Content-Type: application/json; charset=utf-8');

$db        = new Cyber();
$filial_id = (int)$_SESSION['im_filial_id'];

// Hamma ochiq stollar
$orders = $db->rows(
    "SELECT id, stol_id, olib_ketish, mijoz_ism, izoh, status, created_at, updated_at
     FROM im_sotuvchi_order
     WHERE filial_id = $filial_id
       AND status IN ('stol_band', 'oshpazda', 'pishirilmoqda')
     ORDER BY created_at ASC"
);

$result = [];
foreach ($orders as $o) {
    $oid = (int)$o['id'];

    // Har bir orderning mahsulotlarini olamiz
    $items = $db->rows(
        "SELECT i.mahsulot_id,
                i.set_id,
                i.soni,
                i.narx,
                i.olib_ketish_soni,
                i.rezerv_soni,
                i.tayyorlandi_soni  AS locked_soni,
                m.nomi,
                m.birlik, m.sotuv_qadami,
                m.oshpaz_kerak,
                m.retsept_avto,
                st.nomi AS set_nomi,
                COALESCE(fq.soni, 0) AS qoldiq,
                COALESCE(nu.min_soni, 0)       AS ulg_min,
                COALESCE(nu.ulgurji_narxi, 0)  AS ulg_narx
         FROM im_sotuvchi_order_item i
         JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
         LEFT JOIN im_setlar st ON st.id = i.set_id
         LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id = i.mahsulot_id AND fq.filial_id = $filial_id
         LEFT JOIN im_narx_ulgurji nu  ON nu.mahsulot_id = i.mahsulot_id AND nu.aktiv = 1
         WHERE i.order_id = $oid"
    );

    // cart = {kalit => item_obj}. Kalit: à la carte uchun "mahsulot_id",
    // setdan bo'lsa "mahsulot_id_sSETID" — shunda ular alohida qator bo'lib qoladi.
    $cart = [];
    foreach ($items as $it) {
        $mid    = (int)$it['mahsulot_id'];
        $set_id = $it['set_id'] !== null ? (int)$it['set_id'] : null;
        $key    = $set_id ? ($mid . '_s' . $set_id) : (string)$mid;
        $auto = im_auto_maydalash_holati($db, $filial_id, $mid);
        $oddiy_qoldiq = $auto ? (float)$auto['jami_imkon'] : (float)$it['qoldiq'];
        $cart[$key] = [
            'mahsulot_id' => $mid,
            'set_id'      => $set_id,
            'set_nomi'    => $it['set_nomi'],
            '_k'          => $key,
            'nomi'        => $it['nomi'],
            'narx'        => (float)$it['narx'],
            // Ulgurji narx FAQAT alohida (à la carte) qatorga tegishli.
            // Set qatorining narxi set narxidan proporsional bo'linadi
            // (addSetToCart ham ulg_min:0 qo'yadi) — bu yerda ham 0 bo'lmasa,
            // stol qayta ochilganda ekranda ulgurji narx ko'rinib, server esa
            // set narxini yozardi: mijozga aytilgan summa bilan chek mos
            // kelmay qolardi.
            'ulg_min'     => $set_id ? 0   : (int)$it['ulg_min'],
            'ulg_narx'    => $set_id ? 0.0 : (float)$it['ulg_narx'],
            'soni'        => (float)$it['soni'],
            'birlik'      => $it['birlik'],
            'sotuv_qadami'=> max(0.001, (float)$it['sotuv_qadami']),
            // Retseptli taom (oshpaz_kerak / retsept_avto) vitrinada
            // TURMAYDI — im_filial_qoldiq.soni u uchun doim 0. Agar shu 0 ni
            // "qoldiq" deb bersak, ofitsant mavjud buyurtmada "+/-" bosishi
            // bilan miqdor 0 ga tushib ketadi (savatdagi qoldiq cheklovi).
            // Shuning uchun get-products.php dagi kabi cheklovsiz beramiz.
            'qoldiq'      => ((int)$it['oshpaz_kerak'] || (int)$it['retsept_avto'])
                             ? 9999 : $oddiy_qoldiq,
            'auto_maydalash' => $auto ? 1 : 0,
            'tayyor_qoldiq'  => $auto ? (float)$auto['tayyor_qoldiq'] : (float)$it['qoldiq'],
            'auto_imkon'     => $auto ? (float)$auto['auto_imkon'] : 0,
            'locked_soni' => (float)$it['locked_soni'],
            // Shu qator filial qoldig'idan band qilib turgan miqdor.
            // fq.soni (yuqoridagi 'qoldiq') MAVJUD qoldiq — undan bu
            // miqdor allaqachon ayrilgan. Klient tepa chegarani
            // qoldiq + rezerv_soni deb hisoblaydi, aks holda saqlangan
            // qatorda "+/-" bosilishi bilan miqdor tushib ketardi.
            'rezerv_soni' => (float)$it['rezerv_soni'],
            'olib_ketish_soni' => (float)$it['olib_ketish_soni'],
        ];
    }

    $result[] = [
        'id'           => $oid,
        'order_id'     => $oid,
        'stol_id'      => $o['stol_id'] !== null ? (int)$o['stol_id'] : null,
        'olib_ketish'  => (bool)$o['olib_ketish'],
        'mijoz_ism'    => $o['mijoz_ism'],
        'izoh'         => $o['izoh'],
        'status'       => $o['status'],
        'created'      => $o['created_at'],
        'updated'      => $o['updated_at'],
        // Oshpaz tayyorladimi (stol_band), yoki hali navbatda/pishirilmoqda?
        'oshpaz_tayyor'=> $o['status'] === 'stol_band',
        'cart'         => $cart,
    ];
}

echo json_encode(['status' => 'ok', 'orders' => $result], JSON_UNESCAPED_UNICODE);
