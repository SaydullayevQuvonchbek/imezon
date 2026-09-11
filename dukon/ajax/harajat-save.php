<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$nomi     = trim($_POST['nomi'] ?? '');
$summa    = (float)($_POST['summa'] ?? 0);
$tur      = mysqli_real_escape_string($link, $_POST['tur'] ?? 'boshqa');
$tolov    = mysqli_real_escape_string($link, $_POST['tolov_turi'] ?? 'naqd');
$sana_d   = mysqli_real_escape_string($link, $_POST['sana'] ?? date('Y-m-d'));
$nomi_    = mysqli_real_escape_string($link, $nomi);
// Balans log uchun datetime (soat bilan)
$sana_dt  = ($sana_d === date('Y-m-d'))
    ? date('Y-m-d H:i:s')
    : $sana_d . ' ' . date('H:i:s');

if (!$nomi || $summa <= 0) im_json('error', 'Nomi va summa kiritish shart');

$filial_id = $im_filial_id ?: 1;

// Joriy filial kassasi — asosiy balans tekshiruvi
$kassa_filial = $db->row("SELECT * FROM im_kassa WHERE filial_id=$filial_id LIMIT 1");
// Yagona biznes kassasi (filial_id=0) — fallback
// Olib tashlandi: filial o'z kassasidan ishlatadi


// Tekshiruv: avval filial, aks holda global kassa
$kassa = $kassa_filial;
if (!$kassa) im_json('error', 'Kassa topilmadi. Avval kassa yarating.');

// Balans tekshiruvi (joriy kassa asosida)
if ($tolov === 'naqd') {
    $mavjud = (float)$kassa['naqd_balans'];
    if ($summa > $mavjud) {
        im_json('error', sprintf(
            "Naqd kassa yetarli emas! Mavjud: %s so'm, Kerak: %s so'm",
            im_money($mavjud), im_money($summa)
        ));
    }
} elseif ($tolov === 'karta') {
    $mavjud = (float)$kassa['karta_balans'];
    if ($summa > $mavjud) {
        im_json('error', sprintf(
            "%s balans yetarli emas! Mavjud: %s so'm, Kerak: %s so'm",
            im_tt_nomi('karta'), im_money($mavjud), im_money($summa)
        ));
    }
} elseif ($tolov === 'bank') {
    $mavjud = (float)$kassa['bank_balans'];
    if ($summa > $mavjud) {
        im_json('error', sprintf(
            "%s balans yetarli emas! Mavjud: %s so'm, Kerak: %s so'm",
            im_tt_nomi('bank'), im_money($mavjud), im_money($summa)
        ));
    }
}

$db->begin();
try {
    $id = $db->insert(
        "INSERT INTO im_harajatlar (nomi, tur, summa, tolov_turi, sana, xodim_id, filial_id)
         VALUES ('$nomi_', '$tur', $summa, '$tolov', '$sana_d', $im_user_id, $filial_id)"
    );
    if (!$id) throw new Exception('Saqlashda xatolik');

    // Filial kassasidan chiqim
    if ($kassa_filial) {
        $fid = (int)$kassa_filial['id'];
        if ($tolov === 'naqd')        $db->q("UPDATE im_kassa SET naqd_balans  = naqd_balans  - $summa WHERE id=$fid");
        elseif ($tolov === 'karta')   $db->q("UPDATE im_kassa SET karta_balans = karta_balans - $summa WHERE id=$fid");
        elseif ($tolov === 'bank')    $db->q("UPDATE im_kassa SET bank_balans  = bank_balans  - $summa WHERE id=$fid");
    }

    // Global kassa (filial_id=0) dan ham chiqim
    // Olib tashlandi

    $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,xodim_id,sana)
            VALUES ('chiqim','harajat',$summa,$id,'harajat',$filial_id,$im_user_id,'$sana_dt')");

    $db->commit();

    // Yangi balans qaytarish (filial kassasi)
    $yangi_kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=$filial_id LIMIT 1");

    im_json('ok', "Harajat saqlandi: " . im_money($summa) . " so'm", [
        'id'           => $id,
        'naqd_balans'  => (float)$yangi_kassa['naqd_balans'],
        'karta_balans' => (float)$yangi_kassa['karta_balans'],
    ]);

} catch (Exception $e) {
    $db->rollback();
    im_json('error', 'Xatolik: ' . $e->getMessage());
}

