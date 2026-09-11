<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$id      = (int)($_POST['id'] ?? 0);
$nomi    = mysqli_real_escape_string($link, trim($_POST['nomi'] ?? ''));
$kod     = mysqli_real_escape_string($link, strtoupper(trim($_POST['kod'] ?? '')));
$manzil  = mysqli_real_escape_string($link, trim($_POST['manzil'] ?? ''));
$telefon = mysqli_real_escape_string($link, trim($_POST['telefon'] ?? ''));
$rang    = mysqli_real_escape_string($link, $_POST['rang'] ?? '#e2b96f');
$tartib  = (int)($_POST['tartib'] ?? 1);
$status  = (int)($_POST['status'] ?? 1);

if (!$nomi || !$kod) im_json('error', 'Nomi va kod kiritish shart');

if ($id) {
    // Yangilash
    $db->q("UPDATE im_filiallar SET nomi='$nomi', kod='$kod', manzil='$manzil',
            telefon='$telefon', rang='$rang', tartib=$tartib, status=$status
            WHERE id=$id");
    if ($db->error()) im_json('error', 'Xatolik: ' . $db->error());
    im_json('ok', "Filial yangilandi", ['id' => $id]);
} else {
    // Yangi filial qo'shish
    $new_id = $db->insert("INSERT INTO im_filiallar (kod, nomi, manzil, telefon, rang, tartib, status)
        VALUES ('$kod', '$nomi', '$manzil', '$telefon', '$rang', $tartib, $status)");
    if (!$new_id) im_json('error', 'Saqlashda xatolik: ' . $db->error());

    // Yangi filial uchun kassa qatori yaratish (id auto_increment - qo'lda berilmaydi)
    $db->q("INSERT IGNORE INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans)
            VALUES ($new_id, 0, 0, 0, 0)");

    im_json('ok', "Yangi filial qo'shildi!", ['id' => $new_id]);
}
