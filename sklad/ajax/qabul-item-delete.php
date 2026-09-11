<?php
// ============================================================
//  IMezon — Partiya itemini o'chirish AJAX
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new Cyber();

$id = (int)($_POST['id'] ?? 0);
if (!$id) im_json('error', "Noto'g'ri ID");

$db->begin();
try {
    // Resolve the parent, then lock in the same document-first order as close/add.
    $partiya_id = (int)$db->val("SELECT partiya_id FROM im_partiya_items WHERE id=$id");
    $partiya = $db->row("SELECT id, holat FROM im_partiyalar WHERE id=$partiya_id FOR UPDATE");
    if (!$partiya || $partiya['holat'] !== 'ochiq') {
        throw new RuntimeException("Ochiq partiya topilmadi");
    }
    $item = $db->row("SELECT id FROM im_partiya_items WHERE id=$id AND partiya_id=$partiya_id FOR UPDATE");
    if (!$item) throw new RuntimeException("Topilmadi");
    $db->q("DELETE FROM im_partiya_items WHERE id=$id");

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
    im_json('ok', "Mahsulot o'chirildi");
} catch (Throwable $e) {
    $db->rollback();
    im_json('error', $e->getMessage());
}
