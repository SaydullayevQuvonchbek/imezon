<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $kod       = strtoupper(mysqli_real_escape_string($link, trim($_POST['kod'] ?? '')));
    $nomi      = mysqli_real_escape_string($link, trim($_POST['nomi'] ?? ''));
    $tur       = in_array($_POST['tur']??'', ['foiz','summa']) ? $_POST['tur'] : 'summa';
    $qiymat    = (float)($_POST['qiymat'] ?? 0);
    $max_ch    = (float)($_POST['max_chegirma'] ?? 0);
    $min_s     = (float)($_POST['min_summa'] ?? 0);
    $soni      = (int)($_POST['umumiy_soni'] ?? 1);
    $bosh      = mysqli_real_escape_string($link, $_POST['boshlanish'] ?? date('Y-m-d'));
    $tugash    = $_POST['tugash'] ? "'" . mysqli_real_escape_string($link,$_POST['tugash']) . "'" : 'NULL';
    $mijoz     = (int)($_POST['mijoz_id'] ?? 0) ?: 'NULL';
    $filial    = (int)($_POST['filial_id'] ?? 0) ?: 'NULL';

    if (!$kod)       im_json('error', 'Kod majburiy');
    if ($qiymat <= 0) im_json('error', 'Qiymat 0 dan katta bo\'lishi kerak');
    if ($tur === 'foiz' && $qiymat > 100) im_json('error', 'Foiz 100 dan oshmasin');

    // Dublikat tekshiruv
    $mavjud = $db->val("SELECT COUNT(*) FROM im_voucher WHERE kod='$kod'");
    if ($mavjud > 0) im_json('error', "Kod '$kod' allaqachon mavjud!");

    $max_val = $max_ch > 0 ? $max_ch : 'NULL';
    $db->q("INSERT INTO im_voucher
        (kod, nomi, tur, qiymat, max_chegirma, min_summa, umumiy_soni,
         boshlanish, tugash, mijoz_id, filial_id, admin_id, status)
        VALUES
        ('$kod','$nomi','$tur',$qiymat,$max_val,$min_s,$soni,
         '$bosh',$tugash,$mijoz,$filial,$im_user_id,'aktiv')");

    im_json('ok', "Voucher [$kod] muvaffaqiyatli yaratildi!");

} elseif ($action === 'deactivate') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) im_json('error', 'ID noto\'g\'ri');
    $db->q("UPDATE im_voucher SET status='nofaol' WHERE id=$id");
    im_json('ok', 'Voucher nofaol qilindi');

} else {
    im_json('error', 'Noto\'g\'ri so\'rov');
}
