<?php
// ============================================================
//  IMezon — Mahsulot AJAX: Qidirish + Detail
//  GET parametrlari:
//    ?q=<nom_yoki_barcode>   → ro'yxat qaytaradi
//    ?id=<ID>                → bitta mahsulot detallari
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad', 'kassir']);

$db = new Cyber();

// Bitta mahsulot detallari
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $mah = $db->row(
        "SELECT m.*, k.nomi AS kat_nomi,
                COALESCE(n.sotish_narxi, 0) AS sotuv_narx
         FROM im_mahsulotlar m
         LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
         LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
         WHERE m.id=$id AND m.status=1"
    );

    if (!$mah) im_json('error', 'Mahsulot topilmadi');

    // Ulgurji narx
    $ulg = $db->row("SELECT * FROM im_narx_ulgurji WHERE mahsulot_id=$id LIMIT 1");

    // Sklad qoldig'i
    $qoldiq = (float)$db->val(
        "SELECT COALESCE(SUM(remaining_qty),0) FROM im_fifo_layers WHERE mahsulot_id=$id AND location_id=0 AND cancelled=0 AND remaining_qty>0"
    );

    im_json('ok', '', [
        'mahsulot' => $mah,
        'ulg'      => $ulg,
        'qoldiq'   => $qoldiq
    ]);
}

// Bitta mahsulot by barcode (Exact match)
if (isset($_GET['b'])) {
    $b = trim($_GET['b']);
    if (!$b) im_json('error', 'Barcode berilmadi');
    $b_s = mysqli_real_escape_string($link, $b);

    $mah = $db->row(
        "SELECT m.*, k.nomi AS kat_nomi,
                COALESCE(n.sotish_narxi, 0) AS sotuv_narx
         FROM im_mahsulotlar m
         LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
         LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
         WHERE m.barcode='$b_s' AND m.status=1
         LIMIT 1"
    );

    if (!$mah) {
        im_json('ok', 'Not found', ['topildi' => false]);
    }

    im_json('ok', '', [
        'topildi' => true,
        'mahsulot' => $mah
    ]);
}

// Qidirish
$q = trim($_GET['q'] ?? '');
if (!$q) im_json('error', 'Qidiruv matni kerak');

$q_s = mysqli_real_escape_string($link, $q);

// ─── KIRIM REJIMI ────────────────────────────────────────────
// ?kirim=1 — Yuk qabul ekrani (sklad/qabul.php) shu bayroq bilan
// qidiradi. Oraliq (ishlab chiqariladigan) mahsulotlar postavshikdan
// SOTIB OLINMAYDI — ular Qayta ishlash moduli orqali kiritiladi.
// Ilgari ular ro'yxatda chiqib turardi va faqat "Qo'shish" bosilgandan
// keyin qabul-item-add.php xato berardi — ya'ni xato juda kech
// bilinardi. Endi ular kirim qidiruvida umuman ko'rinmaydi.
//
// Bayroq OPT-IN: bu endpoint admin/sklad/kassir uchun umumiy, shuning
// uchun boshqa chaqiruvchilar (masalan sotuv ekranlari) tayyor
// mahsulotlarni ko'rishda davom etadi.
$kirim_where = !empty($_GET['kirim']) ? ' AND m.faqat_ishlab_chiqarish = 0' : '';

$mahsulotlar = $db->rows(
    "SELECT m.id, m.nomi, m.barcode, m.birlik,
            k.nomi AS kat_nomi,
            COALESCE(n.sotish_narxi, 0) AS sotuv_narx,
            COALESCE(
              (SELECT SUM(pi.remaining_qty) FROM im_fifo_layers pi WHERE pi.mahsulot_id=m.id AND pi.location_id=0 AND pi.cancelled=0 AND pi.remaining_qty>0),
              0
            ) AS sklad_qoldiq
     FROM im_mahsulotlar m
     LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
     LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
     WHERE m.status=1 AND (m.nomi LIKE '%$q_s%' OR m.barcode LIKE '%$q_s%')
           $kirim_where
     ORDER BY m.nomi ASC
     LIMIT 20"
);

im_json('ok', '', ['list' => $mahsulotlar]);
