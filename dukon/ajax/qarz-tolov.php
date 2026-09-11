<?php
// ============================================================
//  IMezon — Filial kassasidan postavshikka qarz to'lovi (Dukon)
//  POST: qarz_id, summa, tolov_turi, usd_summa, usd_kurs, izoh
//
//  MUHIM: bu yerda faqat shu foydalanuvchining O'Z FILIALIGA tegishli
//  (ya'ni im_partiyalar.qabul_filial_id = joriy filial bo'lgan partiyaga
//  bog'langan) qarzlar to'lanadi, va pul FAQAT o'sha filialning o'z
//  im_kassa qatoridan yechiladi — markaz (Yagona) kassaga tegilmaydi.
//  "Partiyasiz" (qo'lda kiritilgan, filialga bog'lanmagan) qarzlar bu
//  yerda umuman ko'rinmaydi/to'lanmaydi — ular faqat admin/markaz orqali.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'kassir']);
$db = new Cyber();

$qarz_id   = (int)($_POST['qarz_id']   ?? 0);
$summa     = (float)($_POST['summa']   ?? 0);
$tt        = mysqli_real_escape_string($link, $_POST['tolov_turi'] ?? 'naqd');
$usd_summa = (float)($_POST['usd_summa'] ?? 0);
$usd_kurs  = (float)($_POST['usd_kurs']  ?? 0);
$izoh      = mysqli_real_escape_string($link, trim($_POST['izoh'] ?? ''));
$filial_id = (int)($im_filial_id ?: 1);

if (!$qarz_id) im_json('error', "Qarz IDsi kerak");

$valid_tt = ['naqd','karta','bank','usd'];
if (!in_array($tt, $valid_tt)) im_json('error', "To'lov turi noto'g'ri");

// Qarz — FAQAT shu filial qabul qilgan partiyaga bog'langan bo'lishi shart.
// Partiyasiz (qo'lda kiritilgan) yoki boshqa filialga tegishli qarzlar bu yerda topilmaydi.
$qarz = $db->row(
    "SELECT pq.*, p.qabul_filial_id, p.postavshik_id AS p_postavshik_id
     FROM im_postavshik_qarz pq
     JOIN im_partiyalar p ON p.id = pq.partiya_id
     WHERE pq.id=$qarz_id AND pq.status='ochiq' AND p.qabul_filial_id=$filial_id"
);
if (!$qarz) im_json('error', "Qarz topilmadi, allaqachon yopilgan yoki boshqa filialga tegishli");

$qoldiq = (float)$qarz['qoldiq'];

// USD hisob — avval summani hisoblaymiz
$som_ekviv = $summa;
if ($tt === 'usd') {
    if ($usd_summa <= 0) im_json('error', "USD summasini kiriting");
    if ($usd_kurs <= 0) $usd_kurs = im_usd_kurs();
    $som_ekviv = $usd_summa * $usd_kurs;
    $summa = $som_ekviv;
} else {
    if ($summa <= 0) im_json('error', "Summani kiriting");
}

// Qoldiqdan oshib ketmasin
if ($summa > $qoldiq + 0.01) {
    im_json('error', "Summa qoldiqdan ko'p (qoldi: " . im_money($qoldiq) . " so'm)");
}

// Filial kassasi mavjudligini kafolatlash
$filial_kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=$filial_id LIMIT 1");
if (!$filial_kassa) {
    $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans) VALUES ($filial_id, 0, 0, 0, 0)");
    $filial_kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=$filial_id LIMIT 1");
}

// Filial kassasida yetarli mablag' bormi — real pul o'sha tortmadan chiqadi
$mavjud_col    = $tt === 'naqd' ? 'naqd_balans' : ($tt === 'karta' ? 'karta_balans' : ($tt === 'bank' ? 'bank_balans' : 'usd_balans'));
$mavjud        = (float)$filial_kassa[$mavjud_col];
$tekshir_summa = $tt === 'usd' ? $usd_summa : $summa;
if ($tekshir_summa > $mavjud + 0.01) {
    im_json('error', sprintf(
        "Filial kassasida yetarli mablag' yo'q! Mavjud: %s, kerak: %s",
        im_money($mavjud) . ($tt === 'usd' ? ' $' : " so'm"),
        im_money($tekshir_summa) . ($tt === 'usd' ? ' $' : " so'm")
    ));
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

    // FILIALNING O'Z KASSASIDAN ayirish (markaz/Yagona kassa emas!)
    if ($tt === 'naqd') {
        $db->q("UPDATE im_kassa SET naqd_balans  = naqd_balans  - $summa     WHERE filial_id=$filial_id");
    } elseif ($tt === 'karta') {
        $db->q("UPDATE im_kassa SET karta_balans = karta_balans - $summa     WHERE filial_id=$filial_id");
    } elseif ($tt === 'bank') {
        $db->q("UPDATE im_kassa SET bank_balans  = bank_balans  - $summa     WHERE filial_id=$filial_id");
    } elseif ($tt === 'usd') {
        $db->q("UPDATE im_kassa SET usd_balans   = usd_balans   - $usd_summa WHERE filial_id=$filial_id");
    }
    if ($db->affected() < 1) {
        error_log("[IMezon] FILIAL KASSA YANGILANMADI (postavshik to'lov)! qarz_id=$qarz_id filial_id=$filial_id tt=$tt summa=$summa");
    }

    // Balans logi — filial_id saqlanadi (qaysi filial to'laganini bilish uchun)
    $ps_id = (int)$qarz['p_postavshik_id'];
    $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,summa_usd,usd_kurs,manba_id,manba_tur,filial_id,izoh,xodim_id,sana)
            VALUES ('chiqim','postavshik_tolov',$summa,$usd_summa,$usd_kurs,$qarz_id,'qarz_tolov',$filial_id,
                    'Filial kassasidan kontragentga to\\'lov: $izoh',$im_user_id,NOW())");

    $db->commit();

    // Istoriyaga yozish
    im_log('im_postavshik_qarz', $qarz_id, 'update',
        ['qoldi' => $qoldiq],
        ['summa' => $summa, 'tolov_turi' => $tt, 'yangi_qoldi' => $yangi_qoldi, 'status' => $yangi_status, 'filial_id' => $filial_id],
        "Filial #$filial_id kassasidan qarzga to'lov: $summa so'm ($tt), qoldi: $yangi_qoldi"
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
