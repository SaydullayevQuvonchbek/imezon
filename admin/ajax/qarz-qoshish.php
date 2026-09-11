<?php
// ============================================================
//  IMezon — Alohida (Partiyasiz) Qarz Kiritish (Admin)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

$postavshik_id = (int)($_POST['postavshik_id'] ?? 0);
$summa         = (float)($_POST['summa'] ?? 0);
$sana          = trim($_POST['sana'] ?? '');
$muddat        = trim($_POST['muddat'] ?? '');
$izoh          = mysqli_real_escape_string($link, trim($_POST['izoh'] ?? ''));

if (!$postavshik_id) im_json('error', "Kontragentni tanlang!");
if ($summa <= 0) im_json('error', "Qarz summasini to'g'ri kiriting!");

$muddat_val = ($muddat && preg_match('/^\d{4}-\d{2}-\d{2}$/', $muddat)) ? "'$muddat'" : 'NULL';
$sana_val   = ($sana && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sana)) ? "$sana 12:00:00" : date('Y-m-d H:i:s');

$db->begin();
try {
    $inserted_id = $db->insert("INSERT INTO im_postavshik_qarz
                (postavshik_id, partiya_id, qarz_summa, tolandi, qoldiq, muddat, status, created_at)
            VALUES
                ($postavshik_id, NULL, $summa, 0, $summa, $muddat_val, 'ochiq', '$sana_val')");
    
    // Add fake partiya record just to avoid breaking the UI where a partiya ID is absolutely needed for some reason?
    // No, standard `partiya_id` can be NULL according to SQL schema. We will fix the UI directly.
    
    $ps_nomi = $db->val("SELECT nomi FROM im_postavshiklar WHERE id=$postavshik_id");
    
    im_log("im_postavshik_qarz", $inserted_id, 'insert', null, 
        ['postavshik_id' => $postavshik_id, 'summa' => $summa, 'izoh' => $izoh], 
        "$ps_nomi uchun alohida $summa so'm boshlang'ich qarz kiritildi"
    );
    
    // Yig'indi ma'lumot (agar qarz izohi bo'lsa)
    if ($izoh) {
        $db->q("UPDATE im_postavshik_qarz SET partiya_id = NULL WHERE id=$inserted_id"); // ensure it's null
    }

    $db->commit();
    im_json('ok', "Qarz muvaffaqiyatli saqlandi!");
} catch (Exception $e) {
    $db->rollback();
    im_json('error', "Xatolik: " . $e->getMessage());
}
