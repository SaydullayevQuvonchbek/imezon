<?php
// ============================================================
//  IMezon — Zona qo'shish / tahrirlash (Teraska, Podval, Zal...)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'kassir']);
$db = new Cyber();

$id        = (int)($_POST['id'] ?? 0);
$nomi      = trim($_POST['nomi'] ?? '');
$rang      = trim($_POST['rang'] ?? '#64748b');
$ikonka    = trim($_POST['ikonka'] ?? 'bi-grid-3x3-gap-fill');
$filial_id = (int)($im_filial_id ?: 1);

if ($nomi === '') im_json('error', "Zona nomini kiriting");
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $rang))        $rang   = '#64748b';
if (!preg_match('/^bi-[a-z0-9-]{1,38}$/', $ikonka))   $ikonka = 'bi-grid-3x3-gap-fill';

$nomi_s   = mysqli_real_escape_string($link, mb_substr($nomi, 0, 50, 'UTF-8'));
$rang_s   = mysqli_real_escape_string($link, $rang);
$ikonka_s = mysqli_real_escape_string($link, $ikonka);

// Shu filialda bir xil nomli boshqa zona bo'lmasin
$bor = $db->row(
    "SELECT id FROM im_zonalar WHERE filial_id=$filial_id AND nomi='$nomi_s'" . ($id ? " AND id!=$id" : "")
);
if ($bor) im_json('error', "Bu nomdagi zona allaqachon mavjud");

if ($id) {
    $zona = $db->row("SELECT id FROM im_zonalar WHERE id=$id AND filial_id=$filial_id");
    if (!$zona) im_json('error', "Zona topilmadi");
    $db->q("UPDATE im_zonalar SET nomi='$nomi_s', rang='$rang_s', ikonka='$ikonka_s' WHERE id=$id");
    im_log('im_zonalar', $id, 'update', null, ['nomi' => $nomi, 'rang' => $rang], "Zona yangilandi: $nomi");
    im_json('ok', "Zona yangilandi");
} else {
    $tartib = (int)$db->val("SELECT COALESCE(MAX(tartib),0)+1 FROM im_zonalar WHERE filial_id=$filial_id");
    $new_id = $db->insert(
        "INSERT INTO im_zonalar (filial_id, nomi, rang, ikonka, tartib, status)
         VALUES ($filial_id, '$nomi_s', '$rang_s', '$ikonka_s', $tartib, 1)"
    );
    if (!$new_id) im_json('error', 'Saqlashda xatolik: ' . $db->error());
    im_log('im_zonalar', $new_id, 'insert', null, ['nomi' => $nomi], "Yangi zona qo'shildi: $nomi");
    im_json('ok', "Zona qo'shildi", ['id' => $new_id]);
}
