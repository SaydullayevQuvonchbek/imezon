<?php
// ============================================================
//  IMezon — Partiyaga Mahsulot Qo'shish AJAX
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new Cyber();

$partiya_id   = (int)($_POST['partiya_id'] ?? 0);
$mahsulot_id  = (int)($_POST['mahsulot_id'] ?? 0);
$soni         = (float)($_POST['soni'] ?? 0);
$kelish_narxi = (float)($_POST['kelish_narxi'] ?? 0);
$sotish_narxi = (float)($_POST['sotish_narxi'] ?? 0);

// Validatsiya
if (!$partiya_id)   im_json('error', "Partiya IDsi kerak");
if (!$mahsulot_id)  im_json('error', "Mahsulot tanlanmagan");
if (!is_finite($soni) || $soni <= 0)     im_json('error', "Soni 0 dan katta bo'lishi kerak");
if (!is_finite($kelish_narxi) || $kelish_narxi < 0) im_json('error', "Kelish narxini kiriting");

if (!is_finite($sotish_narxi) || $sotish_narxi < 0) im_json('error', "Sotish narxi noto'g'ri");

// Mahsulot mavjud?
$mah = $db->row("SELECT id, nomi, barcode, faqat_ishlab_chiqarish FROM im_mahsulotlar WHERE id=$mahsulot_id AND status=1");
if (!$mah) im_json('error', "Mahsulot topilmadi");

// Faqat ishlab chiqarish orqali kirishi kerak bo'lgan mahsulotni bloklash
if ($mah['faqat_ishlab_chiqarish']) {
    im_json('error', "⚙️ \"{$mah['nomi']}\" faqat Ishlab chiqarish moduli orqali omborga kiritilishi mumkin. Yuk qabul orqali qo'shib bo'lmaydi.");
}

$db->begin();
try {
    $partiya = $db->row("SELECT id, holat FROM im_partiyalar WHERE id=$partiya_id FOR UPDATE");
    if (!$partiya || $partiya['holat'] !== 'ochiq') {
        throw new RuntimeException("Partiya topilmadi yoki allaqachon yopilgan");
    }

    // Every draft line preserves its own price; drafts do not own stock.
    $db->insert("INSERT INTO im_partiya_items
        (partiya_id, mahsulot_id, soni, kelish_narxi, sotish_narxi, sklad_qoldi, dukon_qoldi)
        VALUES ($partiya_id, $mahsulot_id, $soni, $kelish_narxi, $sotish_narxi, 0, 0)");

    // Sotuv narxini yangilash (agar kiritilgan bo'lsa)
    if ($sotish_narxi > 0) {
        $narx_exists = $db->val("SELECT id FROM im_narxlar WHERE mahsulot_id=$mahsulot_id");
        if ($narx_exists) {
            $db->q("UPDATE im_narxlar SET sotish_narxi=$sotish_narxi WHERE mahsulot_id=$mahsulot_id");
        } else {
            $db->q("INSERT INTO im_narxlar (mahsulot_id, sotish_narxi) VALUES ($mahsulot_id, $sotish_narxi)");
        }
    }

    // Partiya jami summasini yangilash
    $db->q(
        "UPDATE im_partiyalar SET
             jami_summa = (
                 SELECT COALESCE(SUM(soni * kelish_narxi), 0)
                 FROM im_partiya_items
                 WHERE partiya_id=$partiya_id
             )
         WHERE id=$partiya_id"
    );

    $db->commit();
    im_json('ok', "«{$mah['nomi']}» qo'shildi ($soni dona)", [
        'item' => ['mah_id' => $mahsulot_id, 'soni' => $soni]
    ]);

} catch (Throwable $e) {
    $db->rollback();
    im_json('error', "Xatolik: " . $e->getMessage());
}
