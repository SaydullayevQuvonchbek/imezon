<?php
// ============================================================
//  IMezon — Stolni faollashtirish/o'chirish (soft-delete)
//  Tarixiy buyurtmalar bilan bog'liqligi buzilmasligi uchun
//  stollar hech qachon jismonan o'chirilmaydi — faqat status
//  o'zgaradi (0 = ro'yxatdan/tanlovdan yashiriladi).
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'kassir']);
$db = new Cyber();

$id        = (int)($_POST['id'] ?? 0);
$filial_id = (int)($im_filial_id ?: 1);

if (!$id) im_json('error', "Stol IDsi kerak");

$stol = $db->row("SELECT * FROM im_stollar WHERE id=$id AND filial_id=$filial_id");
if (!$stol) im_json('error', "Stol topilmadi");

$yangi_status = $stol['status'] ? 0 : 1;

if ($yangi_status === 0) {
    // Band (faol buyurtmasi bor) stolni o'chirib bo'lmaydi
    $band = (int)$db->val(
        "SELECT COUNT(*) FROM im_sotuvchi_order
         WHERE stol_id=$id AND status NOT IN ('bekor','tugallandi')"
    );
    if ($band > 0) {
        im_json('error', "Bu stolda hozir faol buyurtma bor — avval uni yakunlang");
    }
}

$db->q("UPDATE im_stollar SET status=$yangi_status WHERE id=$id");
im_log('im_stollar', $id, 'update', ['status' => $stol['status']], ['status' => $yangi_status],
    $yangi_status ? "Stol faollashtirildi: {$stol['nomi']}" : "Stol o'chirildi: {$stol['nomi']}");

im_json('ok', $yangi_status ? "Stol faollashtirildi" : "Stol o'chirildi", ['status' => $yangi_status]);
