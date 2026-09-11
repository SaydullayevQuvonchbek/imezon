<?php
// ============================================================
//  IMezon — Do'kon vitrinasi narxini yangilash
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$id   = (int)($_POST['id'] ?? 0);
$narx = (float)($_POST['narx'] ?? 0);

if (!$id || $narx < 0) {
    im_json('error', 'Noto\'g\'ri ma\'lumot kiritildi');
}

$filial_id = $im_filial_id ?: 1;

// Qoldiq filialga tegishlimi?
$qoldiq = $db->row("SELECT id, mahsulot_id FROM im_filial_qoldiq WHERE id=$id AND filial_id='$filial_id'");
if (!$qoldiq) {
    im_json('error', 'Bu mahsulot sizning do\'koningizga tegishli emas yoki topilmadi');
}

// Tannarxni aniqlash
$tannarx = (float)$db->val("SELECT MAX(kelish_narxi) FROM im_partiya_items WHERE mahsulot_id={$qoldiq['mahsulot_id']}");
if ($tannarx > 0 && $narx < $tannarx) {
    im_json('error', 'Sotuv narxi kirim narxidan (tannarxidan) past bo\'lishi aslo mumkin emas!');
}

// Yangilash
$db->q("UPDATE im_filial_qoldiq SET sotuv_narxi = $narx WHERE id=$id AND filial_id='$filial_id'");

im_json('ok', 'Yangi narx saqlandi!');
