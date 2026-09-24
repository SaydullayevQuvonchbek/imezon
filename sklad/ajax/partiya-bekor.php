<?php
// ============================================================
//  IMezon — YOPILGAN partiyani bekor qilish
//
//  Ochiq (qoralama) partiya uchun partiya-cancel.php ishlatiladi — u
//  hech qanday FIFO qatlami yaratmagan qatorlarni shunchaki o'chiradi.
//  Bu fayl esa YOPILGAN (qoldiqqa kirgan) partiya uchun:
//    - im_fifo_cancel_receipt() shu partiyaning qatlamlarini bekor qiladi.
//      Agar biror qatlamdan bir dona bo'lsa ham sarflangan bo'lsa — XATO
//      (tarixni buzmaymiz; bunday holda inventarizatsiya qilinadi).
//    - Postavshik qarzi bekor qilinadi (agar unga to'lov qilingan bo'lsa —
//      XATO: avval to'lovni qaytarish kerak).
//    - im_sklad_send yozuvlari (filialga qabul jurnali) o'chiriladi.
//    - Partiya holati 'bekor' bo'ladi (terminal).
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

// mysqli_report(STRICT) ataylab yoqilmagan — Cyber::q() deadlock xabarini
// o'zi tushunarli matnga aylantiradi (config.php).
$db = new Cyber();

$partiya_id = (int)($_POST['id'] ?? 0);
if (!$partiya_id) im_json('error', 'Partiya IDsi kerak');

$db->begin();
try {
    $p = $db->row("SELECT * FROM im_partiyalar WHERE id=$partiya_id FOR UPDATE");
    if (!$p) throw new RuntimeException('Partiya topilmadi');
    if ($p['holat'] === 'bekor') throw new RuntimeException('Bu partiya allaqachon bekor qilingan');
    if ($p['holat'] !== 'yopiq') throw new RuntimeException("Faqat YOPILGAN partiya uchun. Ochiq qoralamani \"Bekor qilish\" tugmasi o'chiradi.");

    // 1) Postavshik qarzi — to'lov qilingan bo'lsa to'xtaymiz
    $qarz = $db->row("SELECT * FROM im_postavshik_qarz WHERE partiya_id=$partiya_id FOR UPDATE");
    if ($qarz && (float)$qarz['tolandi'] > 0.009) {
        throw new RuntimeException('Bu partiya bo‘yicha postavshikka allaqachon '
            . im_money($qarz['tolandi']) . " so'm to'langan. Avval to'lovni qaytaring, keyin bekor qiling.");
    }

    // 2) FIFO qatlamlarini bekor qilish (ishlatilgan bo'lsa Exception otadi)
    im_fifo_cancel_receipt($db, 'partiya', $partiya_id);

    // 3) Qarz yozuvini olib tashlash (hech narsa to'lanmagan)
    if ($qarz) $db->q("DELETE FROM im_postavshik_qarz WHERE id=" . (int)$qarz['id']);

    // 4) Filialga qabul jurnali (faqat shu partiya qatorlari).
    //    Bu yozuvlar "shu qabul qatori filialga o'tdi" degan jurnal; partiya
    //    bekor bo'lgach ma'nosini yo'qotadi. Ular chegaralangan: agar biror
    //    qatlam sarflangan bo'lsa yuqoridagi im_fifo_cancel_receipt() allaqachon
    //    xato bergan bo'lardi. im_filial_qoldiq.sotuv_narxi esa tegilmaydi —
    //    u narx, qoldiq emas (keyingi qabul yoki vitrina uni yangilaydi).
    $db->q("DELETE FROM im_sklad_send WHERE partiya_item_id IN
                (SELECT id FROM im_partiya_items WHERE partiya_id=$partiya_id)");

    // 5) Hujjat ustunlarini nolga qaytarish + holat
    $db->q("UPDATE im_partiya_items SET sklad_qoldi=0, dukon_qoldi=0 WHERE partiya_id=$partiya_id");
    // Kim va qachon bekor qilgani im_log / im_istoriya da qoladi (pastda).
    $izoh_yangi = mysqli_real_escape_string($link, mb_substr(
        trim(($p['izoh'] ?? '') . ' | BEKOR QILINDI ' . date('Y-m-d H:i')), 0, 500, 'UTF-8'));
    $db->q("UPDATE im_partiyalar SET holat='bekor', qarz_qoldi=0, izoh='$izoh_yangi' WHERE id=$partiya_id");

    $db->commit();

    im_log('im_partiyalar', $partiya_id, 'update',
        ['holat' => 'yopiq', 'jami_summa' => $p['jami_summa']],
        ['holat' => 'bekor'],
        "Yopilgan partiya bekor qilindi — qoldiq qatlamlari va qarz olib tashlandi");

    im_json('ok', 'Partiya bekor qilindi: qoldiq qatlamlari va postavshik qarzi olib tashlandi');
} catch (Throwable $e) {
    $db->rollback();
    im_json('error', $e->getMessage());
}
