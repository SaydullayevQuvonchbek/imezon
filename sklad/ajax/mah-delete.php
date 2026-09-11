<?php
// ============================================================
//  IMezon — Mahsulot AJAX: Arxivlash (soft delete)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

$id = (int)($_POST['id'] ?? 0);
if (!$id) im_json('error', "Noto'g'ri ID");

// Sklad qoldiqlarini tekshirish
$qoldiq = (int)$db->val(
    "SELECT COALESCE(SUM(sklad_qoldi),0) FROM im_partiya_items WHERE mahsulot_id=$id AND sklad_qoldi>0"
);

if ($qoldiq > 0) {
    im_json('error', "Mahsulotda $qoldiq dona sklad qoldig'i bor. Avval ularni yozing yoki o'tkazing.");
}

$db->q("UPDATE im_mahsulotlar SET status=0 WHERE id=$id");

if ($db->affected() > 0) {
    im_json('ok', 'Mahsulot arxivlandi');
} else {
    im_json('error', "Topilmadi yoki allaqachon arxivlangan");
}
