<?php
// ============================================================
//  IMezon — Kassadan olish (Inkasso / Kassadan chiqim)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir']);
$db = new Cyber();

$smena_id  = (int)($_POST['smena_id'] ?? 0);
$summa     = (float)($_POST['summa'] ?? 0);
$tur       = mysqli_real_escape_string($link, $_POST['tur'] ?? 'naqd');
$izoh      = mysqli_real_escape_string($link, trim($_POST['izoh'] ?? ''));
$filial_id = $im_filial_id ?: 1;

if ($summa <= 0) im_json('error', "Summa noto'g'ri — 0 dan katta bo'lishi kerak");
if (!$smena_id) im_json('error', 'Smena ID kerak');

$smena = $db->row("SELECT * FROM im_smena WHERE id=$smena_id AND holat='ochiq'");
if (!$smena) im_json('error', 'Smena topilmadi yoki yopilgan');

// Filial kassasidagi balansni tekshirish
if ($tur === 'naqd') {
    $kassa_balans = (float)$db->val("SELECT naqd_balans FROM im_kassa WHERE filial_id=$filial_id LIMIT 1");
    if ($kassa_balans < $summa) {
        im_json('error', "Kassada yetarli mablag' yo'q (mavjud: " . im_money($kassa_balans) . " so'm)");
    }
} elseif ($tur === 'usd') {
    $usd_balans = (float)$db->val("SELECT usd_balans FROM im_kassa WHERE filial_id=$filial_id LIMIT 1");
    if ($usd_balans < $summa) {
        im_json('error', "Kassada yetarli USD yo'q (mavjud: " . number_format($usd_balans, 2) . " $)");
    }
}

$db->begin();
try {
    // im_inkasasiya jadvaliga yozish (tolov_turi field nomi)
    $id = $db->insert(
        "INSERT INTO im_inkasasiya (smena_id, xodim_id, filial_id, summa, tolov_turi, izoh, holat, sana)
         VALUES ($smena_id, $im_user_id, $filial_id, $summa, '$tur', '$izoh', 'kutilmoqda', NOW())"
    );
    if (!$id) throw new Exception('Yozishda xatolik: ' . $db->error());

    // Filial kassasidan chiqarish
    if ($tur === 'naqd') {
        $db->q("UPDATE im_kassa SET naqd_balans = naqd_balans - $summa WHERE filial_id=$filial_id");
    } elseif ($tur === 'usd') {
        $db->q("UPDATE im_kassa SET usd_balans = usd_balans - $summa WHERE filial_id=$filial_id");
    }

    // Balans log
    $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, manba_id, manba_tur, izoh, xodim_id, filial_id)
            VALUES ('chiqim','inkasasiya_chiqim',$summa,$id,'inkasasiya','$izoh',$im_user_id,$filial_id)");

    $db->commit();
    im_json('ok', "Kassadan " . im_money($summa) . " so'm olindi (inkasso)", ['id' => $id]);

} catch (Exception $e) {
    $db->rollback();
    im_json('error', $e->getMessage());
}
