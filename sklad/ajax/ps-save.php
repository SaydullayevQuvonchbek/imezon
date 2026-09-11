<?php
// ============================================================
//  IMezon — Postavshik AJAX: Saqlash
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);

$db = new Cyber();

$id             = (int)($_POST['id'] ?? 0);
$nomi           = trim($_POST['nomi'] ?? '');
$telefon        = trim($_POST['telefon'] ?? '');
$email          = trim($_POST['email'] ?? '');
$shahar         = trim($_POST['shahar'] ?? '');
$kontakt_ism    = trim($_POST['kontakt_ism'] ?? '');
$kontakt_telefon= trim($_POST['kontakt_telefon'] ?? '');
$izoh           = trim($_POST['izoh'] ?? '');

if (!$nomi) im_json('error', 'Postavshik nomini kiriting');

if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    im_json('error', "Email noto'g'ri formatda");
}

$n_s  = mysqli_real_escape_string($link, mb_substr($nomi, 0, 200, 'UTF-8'));
$t_s  = mysqli_real_escape_string($link, mb_substr($telefon, 0, 30, 'UTF-8'));
$e_s  = mysqli_real_escape_string($link, mb_substr($email, 0, 120, 'UTF-8'));
$sh_s = mysqli_real_escape_string($link, mb_substr($shahar, 0, 100, 'UTF-8'));
$ki_s = mysqli_real_escape_string($link, mb_substr($kontakt_ism, 0, 100, 'UTF-8'));
$kt_s = mysqli_real_escape_string($link, mb_substr($kontakt_telefon, 0, 30, 'UTF-8'));
$iz_s = mysqli_real_escape_string($link, mb_substr($izoh, 0, 1000, 'UTF-8'));

if ($id > 0) {
    // Tahrirlash
    $db->q("UPDATE im_postavshiklar SET
                nomi='$n_s', telefon='$t_s', email='$e_s',
                shahar='$sh_s', kontakt_ism='$ki_s',
                kontakt_telefon='$kt_s', izoh='$iz_s'
            WHERE id=$id");
    im_json('ok', 'Postavshik yangilandi');
} else {
    // Yangi
    $new_id = $db->insert(
        "INSERT INTO im_postavshiklar
            (nomi, telefon, email, shahar, kontakt_ism, kontakt_telefon, izoh, status)
         VALUES
            ('$n_s','$t_s','$e_s','$sh_s','$ki_s','$kt_s','$iz_s',1)"
    );

    if ($new_id) {
        im_json('ok', "Postavshik qo'shildi", ['id' => $new_id]);
    } else {
        im_json('error', 'Saqlashda xatolik: ' . $db->error());
    }
}
