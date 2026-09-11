<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'kassir']);
$db = new Cyber();

$summa = (float) ($_POST['summa'] ?? 0);
$izoh = trim($_POST['izoh'] ?? '');
$tolov_turi = $_POST['tolov_turi'] ?? 'naqd';
// Oq ro'yxat IKKI muammoni birdan yopadi:
//  1) qiymat SQL ga xom holda tushardi (in'ektsiya);
//  2) noma'lum qiymat kelsa pastdagi if/elseif zanjiridan HECH BIRI
//     bajarilmasdi — inkasasiya yozuvi yaratilib, kassadan pul
//     yechilmay qolardi, ya'ni kassada "yo'q" pul paydo bo'lardi.
if (!in_array($tolov_turi, ['naqd', 'karta', 'bank', 'usd'], true)) {
    im_json('error', "Noto'g'ri to'lov turi");
}

$filial_id = $im_filial_id ?: 1;
$xodim_id = $im_user_id;

if ($summa <= 0)
    im_json('error', 'Summani to\'g\'ri kiriting');

$kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=$filial_id");
if (!$kassa) im_json('error', 'Kassa topilmadi');

if ($tolov_turi === 'naqd' && $summa > $kassa['naqd_balans']) {
    im_json('error', "Kassada yetarli naqd pul yo'q (Mavjud: " . im_money($kassa['naqd_balans']) . " so'm)");
} elseif ($tolov_turi === 'karta' && $summa > $kassa['karta_balans']) {
    im_json('error', "Kassada yetarli " . im_tt_nomi('karta') . " puli yo'q (Mavjud: " . im_money($kassa['karta_balans']) . " so'm)");
} elseif ($tolov_turi === 'bank' && $summa > $kassa['bank_balans']) {
    im_json('error', "Kassada yetarli " . im_tt_nomi('bank') . " qoldig'i yo'q (Mavjud: " . im_money($kassa['bank_balans']) . " so'm)");
} elseif ($tolov_turi === 'usd' && $summa > $kassa['usd_balans']) {
    im_json('error', "Kassada yetarli USD yo'q (Mavjud: $" . number_format($kassa['usd_balans'], 2) . ")");
}

$db->begin();
try {
    // Inkasasiya yozuvini saqlaymiz

    $izoh_safe = mysqli_real_escape_string($link, $izoh);
    $ink_id = $db->insert("INSERT INTO im_inkasasiya(filial_id, xodim_id, summa, tolov_turi, izoh) 
                           VALUES('$filial_id', '$xodim_id', '$summa', '$tolov_turi', '$izoh_safe')");

    // Filial kassasidan pul chiqimi
    if ($tolov_turi === 'naqd') {
        $db->q("UPDATE im_kassa SET naqd_balans = naqd_balans - $summa WHERE filial_id=$filial_id");
    } elseif ($tolov_turi === 'karta') {
        $db->q("UPDATE im_kassa SET karta_balans = karta_balans - $summa WHERE filial_id=$filial_id");
    } elseif ($tolov_turi === 'usd') {
        $db->q("UPDATE im_kassa SET usd_balans = usd_balans - $summa WHERE filial_id=$filial_id");
    } elseif ($tolov_turi === 'bank') {
        $db->q("UPDATE im_kassa SET bank_balans = bank_balans - $summa WHERE filial_id=$filial_id");
    }

    // Balans logi (Agar USD bo'lsa, joriy kurs bo'yicha summa_som hisoblaymiz)
    $usd_k = im_usd_kurs();
    if ($tolov_turi === 'usd') {
        $s_som = $summa * $usd_k;
        $s_usd = $summa;
    } else {
        $s_som = $summa;
        $s_usd = 0;
    }

    $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, summa_usd, manba_id, manba_tur, izoh, xodim_id, filial_id)
            VALUES('chiqim','inkasasiya_chiqim','$s_som','$s_usd','$ink_id','inkasasiya',
                   'Adminga pul topshirildi: $izoh_safe','$xodim_id','$filial_id')");

    $db->commit();
    im_json('ok', 'Pul muvaffaqiyatli topshirildi, admin tasdiqlashi kutilmoqda');
} catch (Exception $e) {
    $db->rollback();
    im_json('error', 'Xatolik yuz berdi');
}
