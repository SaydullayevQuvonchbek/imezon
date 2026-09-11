<?php
// ============================================================
//  IMezon — Harajat qo'shish (Admin uchun kengaytirilgan)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

$nomi      = trim($_POST['nomi'] ?? '');
$summa     = (float)($_POST['summa'] ?? 0);
$tur       = mysqli_real_escape_string($link, $_POST['tur'] ?? 'boshqa');
$tolov     = mysqli_real_escape_string($link, $_POST['tolov_turi'] ?? 'naqd');
$sana_d    = mysqli_real_escape_string($link, $_POST['sana'] ?? date('Y-m-d'));
$filial_id_in = (int)($_POST['filial_id'] ?? 0);
$nomi_     = mysqli_real_escape_string($link, $nomi);
$izoh      = mysqli_real_escape_string($link, trim($_POST['izoh'] ?? ''));

$sana_dt   = ($sana_d === date('Y-m-d')) ? date('Y-m-d H:i:s') : $sana_d . ' ' . date('H:i:s');

if (!$nomi || $summa <= 0) im_json('error', 'Nomi va summa kiritish shart');

$kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=$filial_id_in LIMIT 1");
if (!$kassa) im_json('error', "Tanlangan filial uchun kassa topilmadi.");

// Balans tekshiruvi (joriy kassa asosida)
if ($tolov === 'naqd') {
    $mavjud = (float)$kassa['naqd_balans'];
    if ($summa > $mavjud + 0.01) {
        im_json('error', "Naqd kassa yetarli emas! Mavjud: ".im_money($mavjud)." so'm");
    }
} elseif ($tolov === 'karta') {
    $mavjud = (float)$kassa['karta_balans'];
    if ($summa > $mavjud + 0.01) {
        im_json('error', im_tt_nomi('karta')." balans yetarli emas! Mavjud: ".im_money($mavjud)." so'm");
    }
} elseif ($tolov === 'bank') {
    $mavjud = (float)$kassa['bank_balans'];
    if ($summa > $mavjud + 0.01) {
        im_json('error', im_tt_nomi('bank')." balans yetarli emas! Mavjud: ".im_money($mavjud)." so'm");
    }
}

$db->begin();
try {
    $f_q = $filial_id_in > 0 ? $filial_id_in : 'NULL'; // agar NULL qilish kerak bolsa, lekin biz 0 ni ishlatamiz.
    
    // Aslida harajat jadvalida filial_id saqlanadi
    $id = $db->insert(
        "INSERT INTO im_harajatlar (nomi, tur, summa, tolov_turi, sana, xodim_id, filial_id)
         VALUES ('$nomi_', '$tur', $summa, '$tolov', '$sana_d', $im_user_id, $filial_id_in)"
    );
    if (!$id) throw new Exception('Saqlashda xatolik yuz berdi');

    // Kassadan pulni echish
    $fid = (int)$kassa['id'];
    if ($tolov === 'naqd')        $db->q("UPDATE im_kassa SET naqd_balans  = naqd_balans  - $summa WHERE id=$fid");
    elseif ($tolov === 'karta')   $db->q("UPDATE im_kassa SET karta_balans = karta_balans - $summa WHERE id=$fid");
    elseif ($tolov === 'bank')    $db->q("UPDATE im_kassa SET bank_balans  = bank_balans  - $summa WHERE id=$fid");

    // Xodim roli admin bo'lsa 'admin_harajat' deb berish ham mumkin, lekin 'harajat' standart
    $kat = ($filial_id_in === 0) ? 'admin_harajat' : 'harajat';
    
    $full_izoh = $izoh ? "$nomi_ ($izoh)" : $nomi_;
    
    $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,izoh,xodim_id,sana)
            VALUES ('chiqim','$kat',$summa,$id,'harajat',$filial_id_in,'$full_izoh',$im_user_id,'$sana_dt')");

    $db->commit();
    im_json('ok', "Harajat muvaffaqiyatli saqlandi!");
} catch (Exception $e) {
    $db->rollback();
    im_json('error', 'Xatolik: ' . $e->getMessage());
}
