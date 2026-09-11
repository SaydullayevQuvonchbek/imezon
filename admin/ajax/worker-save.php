<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$id       = (int)($_POST['id'] ?? 0);
$ism      = mysqli_real_escape_string($link, trim($_POST['ism'] ?? ''));
$telefon  = mysqli_real_escape_string($link, trim($_POST['telefon'] ?? ''));
$lavozim  = mysqli_real_escape_string($link, $_POST['lavozim'] ?? 'boshqa');
$filial   = (int)($_POST['filial_id'] ?? 0) ?: 'NULL';
$oylik    = (float)($_POST['oylik_stavka'] ?? 0);
$izoh     = mysqli_real_escape_string($link, trim($_POST['izoh'] ?? ''));
$status   = (int)($_POST['status'] ?? 1);

if (!$ism) im_json('error', 'Ism majburiy!');

$lav_ok = ['kassir','sotuvchi','sklad','haydovchi','tozalovchi','boshqa'];
if (!in_array($lavozim, $lav_ok)) $lavozim = 'boshqa';

$filial_val = is_int($filial) && $filial > 0 ? $filial : 'NULL';

if ($id > 0) {
    $db->q("UPDATE im_workers SET
        ism='$ism', telefon='$telefon', lavozim='$lavozim',
        filial_id=$filial_val, oylik_stavka=$oylik,
        izoh='$izoh', status=$status
        WHERE id=$id");
    im_json('ok', 'Xodim yangilandi');
} else {
    $db->q("INSERT INTO im_workers (ism,telefon,lavozim,filial_id,oylik_stavka,izoh,status)
            VALUES ('$ism','$telefon','$lavozim',$filial_val,$oylik,'$izoh',$status)");
    im_json('ok', 'Xodim qo\'shildi');
}
