<?php
// ============================================================
//  IMezon — Zona o'chirish
//  Zonada stol bo'lsa O'CHIRIB BO'LMAYDI — avval stollarni
//  boshqa zonaga o'tkazish yoki o'chirish kerak.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'kassir']);
$db = new Cyber();

$id        = (int)($_POST['id'] ?? 0);
$filial_id = (int)($im_filial_id ?: 1);
if (!$id) im_json('error', "Zona ID kerak");

$zona = $db->row("SELECT id, nomi FROM im_zonalar WHERE id=$id AND filial_id=$filial_id");
if (!$zona) im_json('error', "Zona topilmadi");

$stol_soni = (int)$db->val("SELECT COUNT(*) FROM im_stollar WHERE zona_id=$id");
if ($stol_soni > 0) {
    im_json('error', "Bu zonada $stol_soni ta stol bor. Avval ularni boshqa zonaga o'tkazing yoki o'chiring.");
}

if (!$db->q("DELETE FROM im_zonalar WHERE id=$id")) {
    im_json('error', "O'chirishda xatolik: " . $db->error());
}

im_log('im_zonalar', $id, 'delete', ['nomi' => $zona['nomi']], null, "Zona o'chirildi: {$zona['nomi']}");
im_json('ok', "Zona o'chirildi");
