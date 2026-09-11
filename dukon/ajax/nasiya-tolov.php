<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$nasiya_id   = (int)($_POST['id'] ?? 0);
$summa       = (float)($_POST['summa'] ?? 0);
$turi        = mysqli_real_escape_string($link, $_POST['turi'] ?? 'naqd');
$izoh        = mysqli_real_escape_string($link, $_POST['izoh'] ?? '');
$usd_kurs_in = (float)($_POST['usd_kurs'] ?? 0);

if (!$nasiya_id || $summa <= 0) im_json('error', 'Noto\'g\'ri ma\'lumot');

$tolov_ok = ['naqd', 'karta', 'bank', 'usd'];
if (!in_array($turi, $tolov_ok)) im_json('error', "To'lov turi noto'g'ri");

$nasiya = $db->row("SELECT * FROM im_nasiya WHERE id=$nasiya_id AND holat='aktiv'");
if (!$nasiya) im_json('error', 'Nasiya topilmadi yoki yopilgan');

$filial_id = $im_filial_id ?: 1;
$qoldi     = (float)$nasiya['qoldiq'];

// USD uchun so'm ekvivalenti
$summa_som     = $summa; // odatda so'mda kiritiladi
$usd_miqdor    = 0;
$usd_kurs_real = 0;
if ($turi === 'usd') {
    if ($usd_kurs_in <= 0) $usd_kurs_in = im_usd_kurs();
    $usd_kurs_real = $usd_kurs_in;
    $usd_miqdor    = $summa;                    // dollar miqdori
    $summa_som     = round($summa * $usd_kurs_in); // so'm ekvivalenti
}

if ($summa_som > $qoldi + 0.01) {
    im_json('error', "Summa qoldiqdan ko'p (qoldi: " . im_money($qoldi) . " so'm)");
}

$db->begin();
try {
    // To'lov yozuvi (nasiya_tolov jadvalida summa_som saqlanadi)
    $db->insert(
        "INSERT INTO im_nasiya_tolov (nasiya_id, summa, tolov_turi, usd_summa, usd_kurs, izoh, kassir_id)
         VALUES ($nasiya_id, $summa_som, '$turi', $usd_miqdor, $usd_kurs_real, '$izoh', $im_user_id)"
    );

    $yangi_tolangan = (float)$nasiya['tolangan'] + $summa_som;
    $yangi_qoldi    = $qoldi - $summa_som;
    $yangi_holat    = $yangi_qoldi <= 0.01 ? 'yopildi' : 'aktiv';

    $db->q("UPDATE im_nasiya SET tolangan=$yangi_tolangan, qoldiq=$yangi_qoldi, holat='$yangi_holat' WHERE id=$nasiya_id");

    // Mijoz nasiya qoldig'ini kamaytirish
    $db->q("UPDATE im_mijozlar SET nasiya_qoldiq=nasiya_qoldiq-$summa_som WHERE id={$nasiya['mijoz_id']}");

    // ── Kassaga qo'shish (har tur o'z joyiga) ──────────────────
    // Filial kassasi mavjudligini kafolatlash — bo'lmasa yaratamiz (bo'lmasa UPDATE
    // sukut saqlab 0 qatorga ta'sir qiladi va mijoz to'lovi kassaga qo'shilmay qoladi).
    $filial_kassa = $db->row("SELECT id FROM im_kassa WHERE filial_id=$filial_id LIMIT 1");
    if (!$filial_kassa) {
        $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans) VALUES ($filial_id, 0, 0, 0, 0)");
    }
    if ($turi === 'naqd') {
        $db->q("UPDATE im_kassa SET naqd_balans=naqd_balans+$summa_som WHERE filial_id=$filial_id");
    } elseif ($turi === 'karta') {
        $db->q("UPDATE im_kassa SET karta_balans=karta_balans+$summa_som WHERE filial_id=$filial_id");
    } elseif ($turi === 'bank') {
        $db->q("UPDATE im_kassa SET bank_balans=bank_balans+$summa_som WHERE filial_id=$filial_id");
    } elseif ($turi === 'usd') {
        // USD balansga dollar miqdorida qo'shish
        $db->q("UPDATE im_kassa SET usd_balans=usd_balans+$usd_miqdor WHERE filial_id=$filial_id");
    }
    if ($db->affected() < 1) {
        error_log("[IMezon] FILIAL KASSA YANGILANMADI (nasiya to'lov)! nasiya_id=$nasiya_id filial_id=$filial_id turi=$turi summa=$summa_som");
    }

    // ── Balans logi — manba va to'lov turi ko'rinsin ──────────
    $manba_izoh_s = mysqli_real_escape_string($link,
        "Nasiya to'lovi #{$nasiya_id}" . ($izoh ? ": $izoh" : '')
    );
    // im_balans jadvalida izoh ustuni bo'lmasa ham xato bermaydi (ignore)
    $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, manba_id, manba_tur, filial_id, xodim_id)
            VALUES ('kirim', 'nasiya_tolov', $summa_som, $nasiya_id, 'nasiya', $filial_id, $im_user_id)");

    $db->commit();

    $msg = $yangi_holat === 'yopildi'
        ? 'Nasiya to\'liq yopildi! ✅'
        : "To'lov qabul qilindi (" . strtoupper($turi) . "). Qoldi: " . im_money($yangi_qoldi) . ' so\'m';

    im_json('ok', $msg, [
        'tolov_turi' => $turi,
        'summa_som'  => $summa_som,
        'usd_miqdor' => $usd_miqdor,
    ]);

} catch (Exception $e) {
    $db->rollback();
    im_json('error', $e->getMessage());
}
