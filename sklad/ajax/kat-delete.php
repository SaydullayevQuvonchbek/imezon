<?php
// ============================================================
//  IMezon — Kategoriya AJAX: O'chirish (arxivlash)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    im_json('error', 'Noto\'g\'ri ID');
}

// Mahsulotlar bormi?
$mah_soni = (int)$db->val("SELECT COUNT(*) FROM im_mahsulotlar WHERE kategoriya_id=$id AND status=1");
if ($mah_soni > 0) {
    im_json('error', "Bu kategoriyada $mah_soni ta mahsulot bor. Avval ularni ko'chirish kerak.");
}

// Soft delete (arxivlash)
$db->q("UPDATE im_kategoriyalar SET status=0 WHERE id=$id");

if ($db->affected() > 0) {
    im_json('ok', 'Kategoriya arxivlandi');
} else {
    im_json('error', 'Topilmadi yoki allaqachon o\'chirilgan');
}
