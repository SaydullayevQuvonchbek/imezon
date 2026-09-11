<?php
// ============================================================
//  IMezon — Postavshik AJAX: O'chirish (arxivlash)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);

$db = new Cyber();

$id = (int)($_POST['id'] ?? 0);
if (!$id) im_json('error', "Noto'g'ri ID");

// Partiyalar bormi?
$p_soni = (int)$db->val("SELECT COUNT(*) FROM im_partiyalar WHERE postavshik_id=$id");
if ($p_soni > 0) {
    im_json('error', "Bu postavshikda $p_soni ta partiya mavjud. O'chirib bo'lmaydi.");
}

// Qarzlar bormi?
$q_soni = (int)$db->val("SELECT COUNT(*) FROM im_postavshik_qarz WHERE postavshik_id=$id AND holat='ochiq'");
if ($q_soni > 0) {
    im_json('error', "Bu postavshikda $q_soni ta ochiq qarz mavjud. Avval yoping.");
}

$db->q("UPDATE im_postavshiklar SET status=0 WHERE id=$id");

if ($db->affected() > 0) {
    im_json('ok', 'Postavshik arxivlandi');
} else {
    im_json('error', "Topilmadi yoki allaqachon o'chirilgan");
}
