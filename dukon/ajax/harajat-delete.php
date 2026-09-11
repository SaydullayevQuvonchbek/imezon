<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$id = (int)($_POST['id'] ?? 0);
if (!$id) im_json('error', 'ID kerak');

$h = $db->row("SELECT * FROM im_harajatlar WHERE id=$id");
if (!$h) im_json('error', 'Topilmadi');

$turi      = $h['tolov_turi'];
$s         = (float)$h['summa'];
$filial_id = (int)$h['filial_id'];

$db->begin();
try {
    // Filial kassasiga qaytarish
    $kassa_filial = $db->row("SELECT id FROM im_kassa WHERE filial_id=$filial_id LIMIT 1");
    if ($kassa_filial) {
        $fid = (int)$kassa_filial['id'];
        if ($turi === 'naqd')        $db->q("UPDATE im_kassa SET naqd_balans  = naqd_balans  + $s WHERE id=$fid");
        elseif ($turi === 'karta')   $db->q("UPDATE im_kassa SET karta_balans = karta_balans + $s WHERE id=$fid");
        elseif ($turi === 'bank')    $db->q("UPDATE im_kassa SET bank_balans  = bank_balans  + $s WHERE id=$fid");
    }

    // Global kassa (filial_id=0) ga ham qaytarish
    // Olib tashlandi

    // Balans logidan ham o'chirish (admin logda ko'rinmasin)
    $db->q("DELETE FROM im_balans WHERE manba_tur='harajat' AND manba_id=$id");

    // Harajatni o'chirish
    $db->q("DELETE FROM im_harajatlar WHERE id=$id");

    $db->commit();
    im_json('ok', "Harajat o'chirildi va balans tiklandi");

} catch (Exception $e) {
    $db->rollback();
    im_json('error', 'Xatolik: ' . $e->getMessage());
}
