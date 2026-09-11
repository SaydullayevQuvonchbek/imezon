<?php
// ============================================================
//  IMezon — Kategoriya AJAX: Saqlash (qo'shish + tahrirlash)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

$id    = (int)($_POST['id'] ?? 0);
$nomi  = trim($_POST['nomi'] ?? '');
$rang  = trim($_POST['rang'] ?? '');
$tavsif= trim($_POST['tavsif'] ?? '');

// Validatsiya
if (!$nomi) {
    im_json('error', 'Kategoriya nomini kiriting');
}

if (mb_strlen($nomi, 'UTF-8') > 120) {
    im_json('error', "Nom juda uzun (max 120 belgi)");
}

// Rang formati tekshiruvi
if ($rang && !preg_match('/^#[0-9a-fA-F]{3,6}$/', $rang)) {
    $rang = '';
}

$nomi_s  = mysqli_real_escape_string($link, $nomi);
$rang_s  = mysqli_real_escape_string($link, $rang);
$tavsif_s= mysqli_real_escape_string($link, mb_substr($tavsif, 0, 500, 'UTF-8'));

if ($id > 0) {
    // Tahrirlash
    // Nomi boshqa kategoriyada bormi?
    $exists = $db->val("SELECT id FROM im_kategoriyalar WHERE nomi='$nomi_s' AND id!=$id AND status=1");
    if ($exists) {
        im_json('error', 'Bu nom allaqachon mavjud');
    }

    $db->q("UPDATE im_kategoriyalar SET
                nomi='$nomi_s', rang='$rang_s', tavsif='$tavsif_s'
            WHERE id=$id");

    if ($db->affected() >= 0) {
        im_json('ok', "Kategoriya yangilandi");
    } else {
        im_json('error', "O'zgarish kiritilmadi");
    }
} else {
    // Yangi qo'shish
    $exists = $db->val("SELECT id FROM im_kategoriyalar WHERE nomi='$nomi_s' AND status=1");
    if ($exists) {
        im_json('error', 'Bu nom allaqachon mavjud');
    }

    $new_id = $db->insert(
        "INSERT INTO im_kategoriyalar (nomi, rang, tavsif, status)
         VALUES ('$nomi_s', '$rang_s', '$tavsif_s', 1)"
    );

    if ($new_id) {
        im_json('ok', "Kategoriya qo'shildi", ['id' => $new_id]);
    } else {
        im_json('error', 'Saqlashda xatolik: ' . $db->error());
    }
}
