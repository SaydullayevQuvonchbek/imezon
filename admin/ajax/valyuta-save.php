<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$sana  = mysqli_real_escape_string($link, $_POST['sana'] ?? date('Y-m-d'));
$kurs  = (float)($_POST['kurs'] ?? 0);
$manba = mysqli_real_escape_string($link, $_POST['manba'] ?? 'qolda');

if ($kurs < 100) im_json('error', 'Noto\'g\'ri kurs qiymati');

// Eski kursni o'chirish/yangilash
$db->q("DELETE FROM im_valyuta_kurs WHERE sana='$sana'");
$id = $db->insert(
    "INSERT INTO im_valyuta_kurs (sana, usd_kurs, manba, tasdiqlandi, qo_shgan_id)
     VALUES ('$sana', $kurs, '$manba', 1, $im_user_id)"
);
if (!$id) im_json('error', 'Saqlashda xatolik');
im_json('ok', "Kurs saqlandi: 1 USD = " . im_money($kurs) . " so'm");
