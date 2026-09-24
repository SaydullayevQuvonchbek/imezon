<?php
// ============================================================
//  IMezon — Kassa: stol buyurtmasidagi mahsulotni kamaytirish
//
//  Kassir POS'da "−" bosganda yoki qatorni o'chirganda chaqiriladi.
//  Faqat savatdan olib tashlash yetarli EMAS: agar oshpaz taomni
//  allaqachon qabul qilgan bo'lsa (a-la-carte), uning xomashyosi
//  filial qoldig'idan yechilgan — kamaytirilgan miqdorga to'g'ri
//  keladigan xomashyo QOLDIQQA QAYTARILISHI kerak.
//
//  POST: order_id, mahsulot_id, yangi_soni
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../qayta-ishlash/ishlab_lib.php';
im_rol_check(['kassir', 'admin']);

header('Content-Type: application/json; charset=utf-8');

$db         = new Cyber();
$filial_id  = (int)$_SESSION['im_filial_id'];
$xodim_id   = (int)$_SESSION['im_user_id'];

$order_id   = (int)($_POST['order_id']   ?? 0);
$mahsulot_id= (int)($_POST['mahsulot_id']?? 0);
$set_id     = (int)($_POST['set_id']      ?? 0); // 0 = à la carte qatori
$yangi_soni = (float)($_POST['yangi_soni'] ?? -1);

if (!$order_id || !$mahsulot_id) im_json('error', 'Order yoki mahsulot ko\'rsatilmagan');
if (!is_finite($yangi_soni) || $yangi_soni < 0)             im_json('error', 'Yangi miqdor noto\'g\'ri');

// Faol buyurtma bo'lishi kifoya. Kassir mahsulotni IKKI joyda kamaytira
// oladi — POS savatida (kassaga kelgan order) va zal xaritasidagi "stol
// tahrirlash" ekranida (hali stolda ochiq order). Ikkalasi ham shu
// endpoint orqali o'tadi, shuning uchun ro'yxat kengroq.
$db->begin();
try {
$order = $db->row(
    "SELECT id, status, mijoz_ism FROM im_sotuvchi_order
     WHERE id=$order_id AND filial_id=$filial_id
       AND status IN ('tasdiqlandi','qabul','stol_band','oshpazda','pishirilmoqda') FOR UPDATE"
);
if (!$order) throw new Exception('Faol buyurtma topilmadi');

// ── QULF TARTIBI: order qatori (yuqorida) → MAHSULOTLAR ────────
// To'plamga taomning o'zi VA uning TARIXIY xomashyolari kiradi:
// im_retsept_xomashyo_qaytarish() → im_fifo_reverse() aynan o'sha paytdagi
// harakatlar bo'yicha qatlamlarni qulflaydi (joriy retsept bo'yicha emas).
// Diqqat: oshxona taomida oshpaz_kerak=1, ya'ni retsept_avto=0 (ular
// mah-save.php da o'zaro istisno) — shuning uchun im_qulf_mahsulotlari()
// bu yerda retseptni kengaytirmaydi va xomashyo ro'yxati QO'LDA yig'iladi.
$item_id_pre = (int)$db->val(
    "SELECT id FROM im_sotuvchi_order_item
     WHERE order_id=$order_id AND mahsulot_id=$mahsulot_id AND COALESCE(set_id,0)=$set_id LIMIT 1"
);
$lock_ids = [$mahsulot_id];
if ($item_id_pre) {
    foreach ($db->rows(
        "SELECT DISTINCT l.mahsulot_id FROM im_fifo_movements m
         JOIN im_fifo_layers l ON l.id=m.layer_id
         WHERE m.source='retsept' AND m.kind='take' AND m.source_id IN
               (SELECT id FROM im_ishlab_chiqarish WHERE order_item_id=$item_id_pre)") as $h) {
        $lock_ids[] = (int)$h['mahsulot_id'];
    }
}
im_qulfla_mahsulotlar($db, $filial_id, $lock_ids);

$item = $db->row(
    "SELECT i.id, i.soni, i.tayyorlandi_soni, i.olib_ketish_soni, i.rezerv_soni,
            m.nomi, m.sotuv_qadami, m.oshpaz_kerak, COALESCE(m.qozon_rejim,0) AS qozon_rejim
     FROM im_sotuvchi_order_item i
     JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
     WHERE i.order_id=$order_id AND i.mahsulot_id=$mahsulot_id AND COALESCE(i.set_id,0)=$set_id FOR UPDATE"
);
if (!$item) throw new Exception('Bu mahsulot buyurtmada yo\'q');

$qadam = max(0.001, (float)($item['sotuv_qadami'] ?? 1));
if ($yangi_soni > 0 && abs(($yangi_soni / $qadam) - round($yangi_soni / $qadam)) > 0.0001) {
    throw new Exception("Miqdor {$qadam} qadam bilan kiritilishi kerak");
}

$eski_soni  = (float)$item['soni'];
$tayyorlandi= (float)$item['tayyorlandi_soni'];
$delta      = $eski_soni - $yangi_soni;

if ($delta <= 0) throw new Exception('Kamaytirish uchun yangi miqdor kichikroq bo\'lishi kerak');

    // ── Oshpaz tayyorlagan qism kamaysa — xomashyoni qaytaramiz ──
    // MUHIM: qozon_rejim mahsulotida (osh, shashlik) xomashyo PER-ORDER
    // yechilmagan — u qozon/partiya ochilganda bir marta yechilgan.
    // order-qabul.php baribir tayyorlandi_soni=soni qo'yadi (ofitsant
    // kamaytira olmasin uchun), shuning uchun bu yerda tayyorlandi>0 ni
    // "xomashyo yechilgan" deb bo'lmaydi — aks holda guruch/go'sht
    // qoldiqqa QAYTA qo'shilib, filial zaxirasi asossiz shishardi.
    $qaytarildi = 0;
    // Restore only the prepared quantity actually removed. The original audit
    // decides whether ingredients were taken, regardless of current recipe/mode.
    $prepared_removed = max(0, $tayyorlandi - $yangi_soni);
    if ($prepared_removed > 0) {
        $audit = im_retsept_xomashyo_qaytarish(
            $db, 0, $prepared_removed, $filial_id, $xodim_id,
            "Buyurtma #{$order_id} kamaytirildi", (int)$item['id']
        );
        if ($audit !== null) $qaytarildi = $prepared_removed;
    }

    // REZERVNI QAYTARISH: vitrinali mahsulot buyurtma saqlanganda band
    // qilingan edi — kamaytirilgan qism qoldiqqa qaytadi.
    // Faqat band qilingan qismdan ortiqchasi qaytariladi
    $eski_rezerv  = (float)$item['rezerv_soni'];
    $yangi_rezerv = min($eski_rezerv, $yangi_soni);
    im_rezerv($db, $filial_id, $mahsulot_id, -($eski_rezerv - $yangi_rezerv));

    $yangi_tayyorlandi = min($tayyorlandi, $yangi_soni);
    // Saboyga belgilangan miqdor ham yangi sondan oshib ketmasin
    $yangi_ok = min((float)$item['olib_ketish_soni'], $yangi_soni);

    if ($yangi_soni <= 0) {
        im_ishlab_query($db, "DELETE FROM im_sotuvchi_order_item WHERE id={$item['id']}");
    } else {
        im_ishlab_query($db, "UPDATE im_sotuvchi_order_item
                SET soni=$yangi_soni, tayyorlandi_soni=$yangi_tayyorlandi,
                    olib_ketish_soni=$yangi_ok, rezerv_soni=$yangi_rezerv
                WHERE id={$item['id']}");
    }

    // Item darajasida log
    $nomi_e = $db->f($item['nomi']);
    im_ishlab_query($db, 
        "INSERT INTO im_order_item_log
            (order_id, item_id, mahsulot_id, xodim_id, filial_id, amal, eski_soni, yangi_soni, nomi)
         VALUES ($order_id, {$item['id']}, $mahsulot_id, $xodim_id, $filial_id,
                 'kassa_kamaytirdi', $eski_soni, $yangi_soni, '$nomi_e')"
    );

    $db->commit();

    im_json('ok',
        $qaytarildi > 0
            ? "«{$item['nomi']}» kamaytirildi — {$qaytarildi} ta uchun xomashyo qoldiqqa qaytarildi"
            : "«{$item['nomi']}» kamaytirildi",
        ['qaytarildi' => $qaytarildi]
    );

} catch (Throwable $e) {
    $db->rollback();
    im_json('error', 'Kamaytirib bo\'lmadi: ' . $e->getMessage());
}
