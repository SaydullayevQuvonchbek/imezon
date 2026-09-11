<?php
// ============================================================
//  IMezon — Retsept o'chirish
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);

$db = new Cyber();
$id = (int)($_POST['id'] ?? 0);
if (!$id) im_json('error', 'ID kiritilmadi!');

// Ishlatilgan retseptni o'chirib bo'lmaydi
$ishlat = (int)$db->val("SELECT COUNT(*) FROM im_ishlab_chiqarish WHERE retsept_id=$id AND holat='bajarildi'");
if ($ishlat > 0) im_json('error', "Bu retsept $ishlat marta ishlatilgan, o'chirib bo'lmaydi!");

$db->q("DELETE FROM im_retseptlar WHERE id=$id");
if ($db->affected() > 0) {
    im_json('ok', 'Retsept o\'chirildi!');
} else {
    im_json('error', 'O\'chirishda xatolik!');
}
