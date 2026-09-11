<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$nasiya_id = (int)($_POST['id'] ?? 0);
$sana      = mysqli_real_escape_string($link, $_POST['sana'] ?? '');
$izoh      = mysqli_real_escape_string($link, $_POST['izoh'] ?? '');

if (!$nasiya_id || !$sana) im_json('error', 'Noto\'g\'ri ma\'lumot');
if ($sana <= date('Y-m-d')) im_json('error', 'Sana bugundan keyin bo\'lishi kerak');

$nasiya = $db->row("SELECT * FROM im_nasiya WHERE id=$nasiya_id AND holat='aktiv'");
if (!$nasiya) im_json('error', 'Nasiya topilmadi');

$eski = $nasiya['qaytarish_sana'];
$db->q("UPDATE im_nasiya SET qaytarish_sana='$sana', uzaytirilgan=uzaytirilgan+1 WHERE id=$nasiya_id");
$db->q("INSERT INTO im_nasiya_tarix (nasiya_id,amal,eski_muddat,yangi_muddat,izoh,admin_id)
        VALUES ($nasiya_id,'muddat_uzaytirish','$eski','$sana','$izoh',$im_user_id)");

im_json('ok', "Muddat uzaytirildi: " . im_date($sana));
