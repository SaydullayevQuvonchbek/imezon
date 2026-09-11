<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin','kassir']); // Kassir ham yangi mijoz qo'sha oladi (POS uchun)
$db = new Cyber();

$id     = (int)($_POST['id'] ?? 0);
$ism    = trim($_POST['ism'] ?? '');
$tel    = mysqli_real_escape_string($link, $_POST['tel'] ?? '');
$toifa  = (int)($_POST['toifa'] ?? 0);
$manzil = mysqli_real_escape_string($link, $_POST['manzil'] ?? '');
$izoh_  = mysqli_real_escape_string($link, $_POST['izoh'] ?? '');
$ism_   = mysqli_real_escape_string($link, $ism);

if (!$ism) im_json('error', 'Ism kiritish shart');

$toifa_val = $toifa ? $toifa : 'NULL';

if ($id) {
    $db->q("UPDATE im_mijozlar SET ism='$ism_', telefon='$tel', toifa_id=$toifa_val, manzil='$manzil', izoh='$izoh_' WHERE id=$id");
    im_json('ok', "«{$ism}» yangilandi");
} else {
    $new = $db->insert("INSERT INTO im_mijozlar (ism,telefon,toifa_id,manzil,izoh,status) VALUES ('$ism_','$tel',$toifa_val,'$manzil','$izoh_',1)");
    if (!$new) im_json('error', 'Saqlashda xatolik: ' . mysqli_error($link));
    im_json('ok', "«{$ism}» qo'shildi", ['id' => $new]);
}
