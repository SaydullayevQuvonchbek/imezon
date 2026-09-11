<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

$worker_id = (int)($_POST['worker_id'] ?? 0);
$summa     = (float)($_POST['summa'] ?? 0);
$oy        = mysqli_real_escape_string($link, $_POST['oy'] ?? date('Y-m'));
$tt        = mysqli_real_escape_string($link, $_POST['tolov_turi'] ?? 'naqd');
$sana      = mysqli_real_escape_string($link, $_POST['sana'] ?? date('Y-m-d'));
$izoh      = mysqli_real_escape_string($link, trim($_POST['izoh'] ?? ''));

if ($worker_id <= 0) im_json('error', 'Xodim tanlanmagan');
if ($summa <= 0)     im_json('error', 'Summa 0 dan katta bo\'lishi kerak');
if (!in_array($tt, ['naqd','karta','bank','usd'])) $tt = 'naqd';

$worker = $db->row("SELECT * FROM im_workers WHERE id=$worker_id AND status=1");
if (!$worker) im_json('error', 'Xodim topilmadi');

// Kassa tekshirish
$col = $tt === 'naqd' ? 'naqd_balans' : ($tt === 'karta' ? 'karta_balans' : ($tt === 'bank' ? 'bank_balans' : 'usd_balans'));
$kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=0 LIMIT 1");
if (!$kassa) im_json('error', 'Biznes kassasi topilmadi');
if ((float)$kassa[$col] < $summa) im_json('error',
    sprintf('%s kassada yetarli pul yo\'q! Mavjud: %s so\'m, Kerak: %s so\'m',
        ucfirst($tt), im_money($kassa[$col]), im_money($summa)));

$filial_id = (int)($worker['filial_id'] ?? 0) ?: 'NULL';
$ism_s = mysqli_real_escape_string($link, $worker['ism']);
$izoh_full = $izoh ?: "{$worker['ism']} - $oy maoshi";
$izoh_full = mysqli_real_escape_string($link, $izoh_full);

$db->begin();
try {
    // Kassadan ayirish
    $db->q("UPDATE im_kassa SET $col=$col-$summa WHERE filial_id=0");

    // Maosh tarixi
    $db->q("INSERT INTO im_maosh_tarixi (worker_id,summa,tolov_turi,oy,filial_id,izoh,beruvchi_id,sana)
            VALUES ($worker_id,$summa,'$tt','$oy',$filial_id,'$izoh_full',$im_user_id,'$sana')");

    // Balans logi — kategoriya='maosh' (foydadan ayiriladi)
    $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,izoh,xodim_id,sana)
            VALUES ('chiqim','maosh',$summa,$worker_id,'worker',$filial_id,'$izoh_full',$im_user_id,'$sana')");

    $db->commit();
    im_json('ok', im_money($summa)." so'm maosh berildi ({$worker['ism']}, $oy)");
} catch (Exception $e) {
    $db->rollback();
    im_json('error', 'Xatolik: '.$e->getMessage());
}
