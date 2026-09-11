<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$id       = (int)($_POST['id'] ?? 0);
$ism      = trim($_POST['ism'] ?? '');
$login    = trim($_POST['login'] ?? '');
$parol    = $_POST['parol'] ?? '';
// Rol OQ RO'YXATDAN o'tadi (yagona manba: config.php im_rollar()).
// Ilgari faqat escape qilinardi — ya'ni xato yozilgan rol bilan xodim
// saqlanib, hech qaysi sahifaga kira olmay qolardi.
$rol_xom  = $_POST['rol'] ?? 'kassir';
if (!isset(im_rollar()[$rol_xom])) im_json('error', "Noma'lum rol: " . $rol_xom);
$rol      = mysqli_real_escape_string($link, $rol_xom);
$tel      = mysqli_real_escape_string($link, $_POST['tel'] ?? '');
$status   = (int)($_POST['status'] ?? 1);
$filial_id= (int)($_POST['filial_id'] ?? 0) ?: 'NULL';
$ism_     = mysqli_real_escape_string($link, $ism);
$login_   = mysqli_real_escape_string($link, $login);

if (!$ism || !$login) im_json('error', 'Ism va login shart');

// Login unikal tekshiruv
$exist = $db->val("SELECT id FROM im_xodimlar WHERE login='$login_' AND id!=$id");
if ($exist) im_json('error', "«{$login}» login allaqachon band");

if ($id) {
    $sql = "UPDATE im_xodimlar SET ism='$ism_', login='$login_', rol='$rol', telefon='$tel', status=$status, filial_id=$filial_id";
    if ($parol) {
        // MD5 emas: tuzli, sekin va kelajakda algoritm almashsa avtomatik
    // yangilanadigan hash. login.php eski MD5 yozuvlarni kirish paytida
    // shu formatga o'zi ko'chiradi.
    $hash = password_hash($parol, PASSWORD_DEFAULT);
        $hash_ = mysqli_real_escape_string($link, $hash);
        $sql .= ", parol='$hash_'";
    }
    $sql .= " WHERE id=$id";
    $db->q($sql);
    im_json('ok', 'Xodim yangilandi');
} else {
    if (!$parol) im_json('error', 'Parol kiritish shart');
    // MD5 emas: tuzli, sekin va kelajakda algoritm almashsa avtomatik
    // yangilanadigan hash. login.php eski MD5 yozuvlarni kirish paytida
    // shu formatga o'zi ko'chiradi.
    $hash = password_hash($parol, PASSWORD_DEFAULT);
    $hash_ = mysqli_real_escape_string($link, $hash);
    $new_id = $db->insert("INSERT INTO im_xodimlar (ism,login,parol,rol,telefon,status,filial_id) VALUES ('$ism_','$login_','$hash_','$rol','$tel',$status,$filial_id)");
    if (!$new_id) im_json('error', 'Saqlashda xatolik: ' . mysqli_error($link));
    im_json('ok', "«{$ism}» xodim qo'shildi", ['id' => $new_id]);
}
