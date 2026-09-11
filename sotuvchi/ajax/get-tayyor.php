<?php
// ============================================================
//  IMezon — Sotuvchi: "Taom tayyor" signali
//
//  Oshpaz "Tayyor" bosganda buyurtma 'pishirilmoqda' → 'stol_band'
//  holatiga o'tadi. Ofitsant ekrani shu ro'yxatni qisqa oraliqda
//  so'raydi va YANGI paydo bo'lganlari uchun qo'ng'iroq chaladi
//  (qaysi biri ko'rsatilganini brauzer o'zi localStorage da eslab
//  qoladi — shuning uchun bu yerda hech qanday bayroq ustuni kerak emas).
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['sotuvchi', 'admin', 'kassir']);

header('Content-Type: application/json; charset=utf-8');

$db        = new Cyber();
$filial_id = (int)$_SESSION['im_filial_id'];

$rows = $db->rows(
    "SELECT o.id, o.mijoz_ism, o.stol_id, o.olib_ketish, o.updated_at
     FROM im_sotuvchi_order o
     WHERE o.filial_id = $filial_id
       AND o.status = 'stol_band'
       AND EXISTS (
             SELECT 1 FROM im_sotuvchi_order_item i
             JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
             WHERE i.order_id = o.id AND m.oshpaz_kerak = 1 AND i.tayyorlandi_soni > 0
           )
     ORDER BY o.updated_at DESC
     LIMIT 50"
);

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'order_id'    => (int)$r['id'],
        'mijoz_ism'   => $r['mijoz_ism'],
        'stol_id'     => $r['stol_id'] !== null ? (int)$r['stol_id'] : null,
        'olib_ketish' => (bool)$r['olib_ketish'],
        'tayyor_vaqti'=> $r['updated_at'],
    ];
}

echo json_encode(['status' => 'ok', 'tayyor' => $out], JSON_UNESCAPED_UNICODE);
