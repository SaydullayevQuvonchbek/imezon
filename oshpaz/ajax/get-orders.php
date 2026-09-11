<?php
// ============================================================
//  IMezon — Oshpaz: Navbatdagi va bugungi buyurtmalar
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../qozon_lib.php';
im_rol_check(['oshpaz', 'admin']);

header('Content-Type: application/json; charset=utf-8');

$db        = new Cyber();
$filial_id = (int)$_SESSION['im_filial_id'];
$cond      = $filial_id > 0 ? "o.filial_id = $filial_id" : "1=1";
$today     = date('Y-m-d');

// ── Yangi buyurtmalar (faqat 'oshpazda' status) ─────────────
$orders = $db->rows(
    "SELECT o.id, o.mijoz_ism, o.olib_ketish, o.stol_id, o.izoh, o.created_at, o.sotuvchi_id,
            x.ism AS sotuvchi_ism,
            (SELECT COUNT(*) FROM im_sotuvchi_order o2
             WHERE o2.filial_id = o.filial_id
               AND DATE(o2.created_at) = '$today'
               AND o2.id <= o.id) AS kun_raqam
     FROM im_sotuvchi_order o
     JOIN im_xodimlar x ON x.id = o.sotuvchi_id
     WHERE $cond AND o.status = 'oshpazda'
     ORDER BY o.created_at ASC"
);

foreach ($orders as &$o) {
    $oid = (int)$o['id'];

    // Oshpazga ko'rinishi kerak bo'lgan yangi mahsulotlar (faqat oshpaz_kerak=1, delta)
    $o['items'] = $db->rows(
        "SELECT i.mahsulot_id,
                (i.soni - i.tayyorlandi_soni) AS soni,
                i.soni AS soni_jami,
                i.olib_ketish_soni,
                m.nomi, m.birlik,
                1 AS oshpaz_kerak
         FROM im_sotuvchi_order_item i
         JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
         WHERE i.order_id = $oid
           AND m.oshpaz_kerak = 1
           AND i.soni > i.tayyorlandi_soni"
    );

    // Chek uchun BARCHA oshpazga tegishli mahsulotlar
    $o['all_items'] = $db->rows(
        "SELECT i.soni, i.olib_ketish_soni, m.nomi, m.birlik
         FROM im_sotuvchi_order_item i
         JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
         WHERE i.order_id = $oid
           AND m.oshpaz_kerak = 1"
    );
}
unset($o);

// MUHIM: MySQL "0" ni MATN sifatida qaytaradi, JS'da esa bo'sh bo'lmagan
// "0" matni ROST hisoblanadi — shu sababli oshpaz kartasida har doim
// "OLIB KETISH — QADOQLANSIN" chiqib turardi. Haqiqiy mantiqiy qiymatga
// aylantiramiz.
foreach ($orders as &$o) {
    $o['olib_ketish'] = (bool)$o['olib_ketish'];
    $o['stol_id']     = $o['stol_id'] !== null ? (int)$o['stol_id'] : null;
    // Aynan shu sabab: kechagi buyurtmada kun_raqam = 0 bo'ladi va
    // JS'dagi `kun_raqam || id` "0" MATNI rost bo'lgani uchun
    // "Sotuv #0" chiqarardi. Songa aylantirsak zaxira id ishlaydi.
    $o['kun_raqam']   = (int)$o['kun_raqam'];
}
unset($o);

// Barchasi tayyorlangan orderlarni olib tashlaymiz
// MUHIM: oshpaz_kerak mahsuloti yo'q bo'lsa ham 'oshpazda' qolgan orderlarni
// avtomatik 'stol_band' ga o'tkazamiz (deadlock oldini olish)
$orders_to_show = [];
foreach ($orders as $o) {
    if (count($o['items']) > 0) {
        $orders_to_show[] = $o;
    } else {
        // Bu order 'oshpazda' statusida lekin oshpazga tegishli mahsulot yo'q
        // Avtomatik stol_band ga o'tkazamiz
        $fix_id = (int)$o['id'];
        $db->q("UPDATE im_sotuvchi_order SET status='stol_band', updated_at=NOW() WHERE id=$fix_id");
    }
}
$orders = array_values($orders_to_show);

// ── Qabul qilingan, hozir pishirilayotganlar ───────────────
// Oshpaz "Qabul qildim" bosgach buyurtma shu ro'yxatga o'tadi va
// "Tayyor" bosilgunga qadar shu yerda turadi (xomashyo allaqachon
// sarflangan — order-qabul.php).
$cooking = $db->rows(
    "SELECT o.id, o.mijoz_ism, o.olib_ketish, o.stol_id, o.izoh, o.created_at, o.updated_at,
            x.ism AS sotuvchi_ism,
            (SELECT COUNT(*) FROM im_sotuvchi_order o2
             WHERE o2.filial_id = o.filial_id
               AND DATE(o2.created_at) = '$today'
               AND o2.id <= o.id) AS kun_raqam
     FROM im_sotuvchi_order o
     JOIN im_xodimlar x ON x.id = o.sotuvchi_id
     WHERE $cond AND o.status = 'pishirilmoqda'
     ORDER BY o.updated_at ASC"
);
foreach ($cooking as &$c) {
    $cid = (int)$c['id'];
    $c['items'] = $db->rows(
        "SELECT i.tayyorlandi_soni AS soni, i.soni AS soni_jami, i.olib_ketish_soni, m.nomi, m.birlik
         FROM im_sotuvchi_order_item i
         JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
         WHERE i.order_id = $cid AND m.oshpaz_kerak = 1"
    );
    $c['all_items'] = $c['items'];
    $c['olib_ketish'] = (bool)$c['olib_ketish'];
    $c['stol_id']     = $c['stol_id'] !== null ? (int)$c['stol_id'] : null;
    $c['kun_raqam']   = (int)$c['kun_raqam'];
}
unset($c);

// ── Bugun tayyorlanganlar (tarix) ──────────────────────────
$history_raw = $db->rows(
    "SELECT DISTINCT o.id, o.mijoz_ism, o.updated_at AS tayyor_vaqti,
            x.ism AS sotuvchi_ism,
            (SELECT COUNT(*) FROM im_sotuvchi_order o2
             WHERE o2.filial_id = o.filial_id
               AND DATE(o2.created_at) = '$today'
               AND o2.id <= o.id) AS kun_raqam
     FROM im_sotuvchi_order o
     JOIN im_xodimlar x ON x.id = o.sotuvchi_id
     JOIN im_sotuvchi_order_item i ON i.order_id = o.id
     JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
     WHERE $cond
       AND o.status IN ('stol_band', 'kassada', 'tasdiqlandi', 'tugallandi')
       AND m.oshpaz_kerak = 1
       AND i.tayyorlandi_soni > 0
       AND DATE(o.updated_at) = '$today'
     ORDER BY o.updated_at DESC
     LIMIT 100"
);

$history = [];
foreach ($history_raw as $h) {
    $oid = (int)$h['id'];
    $h['kun_raqam'] = (int)$h['kun_raqam'];
    $h['items'] = $db->rows(
        "SELECT i.tayyorlandi_soni AS soni, m.nomi, m.birlik, i.narx,
                COALESCE(m.oshpaz_kerak, 0) AS oshpaz_kerak
         FROM im_sotuvchi_order_item i
         JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
         WHERE i.order_id = $oid
           AND m.oshpaz_kerak = 1
           AND i.tayyorlandi_soni > 0"
    );
    $h['summa'] = $db->val(
        "SELECT SUM(i.tayyorlandi_soni * i.narx)
         FROM im_sotuvchi_order_item i
         JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
         WHERE i.order_id=$oid AND m.oshpaz_kerak = 1 AND i.tayyorlandi_soni > 0"
    );
    if (!empty($h['items'])) $history[] = $h;
}

echo json_encode([
    'status'     => 'ok',
    'orders'     => $orders,
    'cooking'    => $cooking,
    'history'    => $history,
    'eslatmalar' => im_qozon_eslatma($db, $filial_id),
], JSON_UNESCAPED_UNICODE);
