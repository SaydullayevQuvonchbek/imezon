<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);

$id       = (int)($_POST['id'] ?? 0);
$nomi     = trim($_POST['nomi'] ?? '');
$chegirma = (float)($_POST['chegirma'] ?? 0);
$rang     = trim($_POST['rang'] ?? '#e2b96f');
$tavsif   = trim($_POST['tavsif'] ?? '');

if (!$nomi) im_json('error', 'Toifa nomi kerak');
if ($chegirma < 0 || $chegirma > 50) im_json('error', "Chegirma 0-50% orasida bo'lishi kerak");
if (!preg_match('/^#[0-9a-fA-F]{6}$/i', $rang)) $rang = '#e2b96f';

$n = mysqli_real_escape_string($link, $nomi);
$r = mysqli_real_escape_string($link, $rang);
$t = mysqli_real_escape_string($link, $tavsif);

if ($id) {
    // Yangilash
    $check = mysqli_query($link, "SELECT id FROM im_mijoz_toifalari WHERE id=$id");
    if (!$check || mysqli_num_rows($check) === 0) im_json('error', 'Toifa topilmadi');

    $res = mysqli_query($link, "UPDATE im_mijoz_toifalari SET nomi='$n', chegirma_foiz=$chegirma, rang='$r', tavsif='$t' WHERE id=$id");
    if (!$res) im_json('error', 'Yangilashda xatolik: ' . mysqli_error($link));
    im_json('ok', "\"$nomi\" toifasi yangilandi");

} else {
    // Yangi qo'shish
    $dup = mysqli_fetch_assoc(mysqli_query($link, "SELECT id FROM im_mijoz_toifalari WHERE nomi='$n' LIMIT 1"));
    if ($dup) im_json('error', 'Bu nomli toifa allaqachon mavjud');

    $res = mysqli_query($link, "INSERT INTO im_mijoz_toifalari (nomi, chegirma_foiz, rang, tavsif) VALUES ('$n', $chegirma, '$r', '$t')");
    if (!$res) im_json('error', 'Saqlashda xatolik: ' . mysqli_error($link));

    $newid = mysqli_insert_id($link);
    im_json('ok', "\"$nomi\" toifasi qo'shildi", ['id' => $newid]);
}
