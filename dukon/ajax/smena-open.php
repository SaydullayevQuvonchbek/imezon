<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir']);
$db = new Cyber();

// Smena ochish
$naqd = (float)($_POST['ochish_naqd'] ?? 0);
$sana = date('Y-m-d');
$filial_id = $im_filial_id ?: 1;

$ochiq = $db->val("SELECT id FROM im_smena WHERE kassir_id=$im_user_id AND filial_id=$filial_id AND holat='ochiq'");
if ($ochiq) im_json('error', "Allaqachon ochiq smena bor (ID: $ochiq)");

$id = $db->insert(
    "INSERT INTO im_smena (sana, kassir_id, filial_id, ochish_naqd, holat, ochildi)
     VALUES ('$sana', $im_user_id, $filial_id, $naqd, 'ochiq', NOW())"
);
if (!$id) im_json('error', 'Smena ochishda xatolik: ' . $db->error());

// Boshlang'ich naqd kassani ham yangilash
if ($naqd > 0) {
    // Filial kassasi mavjudligini kafolatlash — bo'lmasa yaratamiz (bo'lmasa UPDATE
    // sukut saqlab 0 qatorga ta'sir qiladi va boshlang'ich naqd yo'qolib qoladi).
    $filial_kassa = $db->row("SELECT id FROM im_kassa WHERE filial_id=$filial_id LIMIT 1");
    if (!$filial_kassa) {
        $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans) VALUES ($filial_id, 0, 0, 0, 0)");
    }
    $db->q("UPDATE im_kassa SET naqd_balans = naqd_balans + $naqd WHERE filial_id=$filial_id");
    if ($db->affected() < 1) {
        error_log("[IMezon] FILIAL KASSA YANGILANMADI (smena ochish)! filial_id=$filial_id naqd=$naqd smena_id=$id");
    }
    // Balans logi
    $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, manba_id, manba_tur, izoh, xodim_id, filial_id)
            VALUES ('kirim','smena_ochish',$naqd,$id,'smena','Smena ochishda boshlang\\'ich naqd',$im_user_id,$filial_id)");
}

im_json('ok', 'Smena ochildi', ['id' => $id, 'naqd' => $naqd]);

