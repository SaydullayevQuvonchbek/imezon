<?php
// ============================================================
//  IMezon — Stol qo'shish / tahrirlash
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'kassir']);
$db = new Cyber();

$id        = (int)($_POST['id'] ?? 0);
$nomi      = trim($_POST['nomi'] ?? '');
$zona_id   = (int)($_POST['zona_id'] ?? 0);
$filial_id = (int)($im_filial_id ?: 1);

if (!$nomi) im_json('error', "Stol nomini kiriting");
$nomi_s = mysqli_real_escape_string($link, mb_substr($nomi, 0, 50, 'UTF-8'));

// Zona shu filialga tegishli va faol bo'lsagina qabul qilinadi, aks holda NULL
if ($zona_id) {
    $zona_ok = $db->val("SELECT id FROM im_zonalar WHERE id=$zona_id AND filial_id=$filial_id AND status=1");
    if (!$zona_ok) $zona_id = 0;
}
$zona_sql  = $zona_id ?: 'NULL';
$zona_norm = $zona_id ?: 0;   // NULL ni 0 deb solishtiramiz

// Nom endi ZONA ichida noyob — "Teraska"da ham, "Zal"da ham "Stol 1" bo'lishi mumkin.
$bor = $db->row(
    "SELECT id FROM im_stollar
     WHERE filial_id=$filial_id AND nomi='$nomi_s' AND COALESCE(zona_id,0)=$zona_norm"
    . ($id ? " AND id!=$id" : "")
);
if ($bor) im_json('error', "Bu zonada shu nomdagi stol allaqachon bor");

if ($id) {
    $stol = $db->row("SELECT id FROM im_stollar WHERE id=$id AND filial_id=$filial_id");
    if (!$stol) im_json('error', "Stol topilmadi");
    $db->q("UPDATE im_stollar SET nomi='$nomi_s', zona_id=$zona_sql WHERE id=$id");
    im_log('im_stollar', $id, 'update', null, ['nomi' => $nomi, 'zona_id' => $zona_id ?: null], "Stol yangilandi: $nomi");
    im_json('ok', "Stol yangilandi");
} else {
    $tartib = (int)$db->val("SELECT COALESCE(MAX(tartib),0)+1 FROM im_stollar WHERE filial_id=$filial_id");
    $new_id = $db->insert(
        "INSERT INTO im_stollar (filial_id, zona_id, nomi, tartib, status) VALUES ($filial_id, $zona_sql, '$nomi_s', $tartib, 1)"
    );
    if (!$new_id) im_json('error', 'Saqlashda xatolik: ' . $db->error());
    im_log('im_stollar', $new_id, 'insert', null, ['nomi' => $nomi], "Yangi stol qo'shildi: $nomi");
    im_json('ok', "Stol qo'shildi", ['id' => $new_id]);
}
