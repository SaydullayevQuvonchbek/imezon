<?php
// ============================================================
//  IMezon — Postavshik qarzini to'lash (Admin)
//  POST: qarz_id, summa, tolov_turi, usd_summa, usd_kurs, izoh
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

$qarz_id   = (int)($_POST['qarz_id']   ?? 0);
$summa     = (float)($_POST['summa']   ?? 0);
$tt        = mysqli_real_escape_string($link, $_POST['tolov_turi'] ?? 'naqd');
$usd_summa = (float)($_POST['usd_summa'] ?? 0);
$usd_kurs  = (float)($_POST['usd_kurs']  ?? 0);
$izoh      = mysqli_real_escape_string($link, trim($_POST['izoh'] ?? ''));

// USD holati tekshiruvi
if ($tt === 'usd') {
    if ($usd_summa <= 0) im_json('error', "Qarz ID va USD summasi kiritilishi shart");
} else {
    if (!$qarz_id || $summa <= 0) im_json('error', "Qarz ID va summa kiritilishi shart");
}

$valid_tt = ['naqd','karta','bank','usd'];
if (!in_array($tt, $valid_tt)) im_json('error', "To'lov turi noto'g'ri");

$qarz = $db->row("SELECT * FROM im_postavshik_qarz WHERE id=$qarz_id AND status='ochiq'");
if (!$qarz) im_json('error', "Qarz topilmadi yoki allaqachon yopilgan");

$qoldiq = (float)$qarz['qoldiq'];

// USD hisob — avval summani hisoblaymiz
$som_ekviv = $summa;
if ($tt === 'usd') {
    if ($usd_kurs <= 0) $usd_kurs = im_usd_kurs();
    $som_ekviv = $usd_summa * $usd_kurs;
    $summa = $som_ekviv;
}

// Qoldiqdan oshib ketmasin
if ($summa > $qoldiq + 0.01) {
    im_json('error', "Summa qoldiqdan ko'p (qoldi: " . im_money($qoldiq) . " so'm)");
}

$db->begin();
try {
    // To'lov yozuvi
    $db->q("INSERT INTO im_qarz_tolovlar
                (qarz_id, summa, tolov_turi, usd_summa, usd_kurs, izoh, admin_id, sana)
            VALUES
                ($qarz_id, $summa, '$tt', $usd_summa, $usd_kurs, '$izoh', $im_user_id, NOW())");

    // Qarz qoldiqni yangilash
    $yangi_tolandi = (float)$qarz['tolandi'] + $summa;
    $yangi_qoldi   = $qoldiq - $summa;
    $yangi_status  = $yangi_qoldi <= 0.01 ? 'yopildi' : 'ochiq';

    $db->q("UPDATE im_postavshik_qarz SET
                tolandi  = $yangi_tolandi,
                qoldiq   = $yangi_qoldi,
                status   = '$yangi_status'
            WHERE id=$qarz_id");

    // Partiyani ham yangilash
    if ($qarz['partiya_id']) {
        $db->q("UPDATE im_partiyalar SET
                    tolandi    = tolandi + $summa,
                    qarz_qoldi = qarz_qoldi - $summa
                WHERE id={$qarz['partiya_id']}");
    }

    // YAGONA BIZNES KASSASIDAN ayirish (postavshik to'lov)
    // Yagona kassa qatori mavjudligini kafolatlash — bo'lmasa yaratamiz (sukut saqlab
    // pul yo'qolib qolishining oldini olish uchun).
    $markaz_kassa = $db->row("SELECT id FROM im_kassa WHERE filial_id=0 LIMIT 1");
    if (!$markaz_kassa) {
        $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans) VALUES (0, 0, 0, 0, 0)");
    }
    if ($tt === 'naqd') {
        $db->q("UPDATE im_kassa SET naqd_balans  = naqd_balans  - $summa    WHERE filial_id=0");
    } elseif ($tt === 'karta') {
        $db->q("UPDATE im_kassa SET karta_balans = karta_balans - $summa    WHERE filial_id=0");
    } elseif ($tt === 'bank') {
        $db->q("UPDATE im_kassa SET bank_balans  = bank_balans  - $summa    WHERE filial_id=0");
    } elseif ($tt === 'usd') {
        $db->q("UPDATE im_kassa SET usd_balans   = usd_balans   - $usd_summa WHERE filial_id=0");
    }
    if ($db->affected() < 1) {
        error_log("[IMezon] YAGONA KASSA YANGILANMADI (qarz to'lov)! qarz_id=$qarz_id tt=$tt summa=$summa");
    }

    // Balans logi — filial_id=0 (yagona kassa chiqimi)
    $ps_id = (int)$qarz['postavshik_id'];
    $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,summa_usd,usd_kurs,manba_id,manba_tur,filial_id,izoh,xodim_id,sana)
            VALUES ('chiqim','postavshik_tolov',$summa,$usd_summa,$usd_kurs,$qarz_id,'qarz_tolov',0,
                    'Kontragentga to\'lov: $izoh',$im_user_id,NOW())");

    $db->commit();

    // Istoriyaga yozish
    im_log('im_qarz_tolovlar', $qarz_id, 'insert',
        ['qoldi' => $qoldiq],
        ['summa' => $summa, 'tolov_turi' => $tt, 'yangi_qoldi' => $yangi_qoldi, 'status' => $yangi_status],
        "Qarzga to'lov: $summa so'm ($tt), qoldi: $yangi_qoldi"
    );

    $msg = $yangi_status === 'yopildi'
        ? "Qarz to'liq yopildi! ✅"
        : "To'lov qabul qilindi. Qoldi: " . im_money($yangi_qoldi) . " so'm";

    im_json('ok', $msg, [
        'yangi_qoldi'  => $yangi_qoldi,
        'yangi_status' => $yangi_status,
    ]);

} catch (Exception $e) {
    $db->rollback();
    im_json('error', "Xatolik: " . $e->getMessage());
}
