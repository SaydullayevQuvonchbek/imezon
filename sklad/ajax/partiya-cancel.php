<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new Cyber();
$partiya_id = (int)($_POST['id'] ?? 0);

if (!$partiya_id) im_json('error', "Partiya IDsi kerak");

$db->begin();
try {
    $ochiq = $db->row("SELECT * FROM im_partiyalar WHERE id=$partiya_id FOR UPDATE");
    if (!$ochiq || $ochiq['holat'] !== 'ochiq' || (int)$ochiq['xodim_id'] !== (int)$im_user_id) {
        throw new RuntimeException("Bunday ochiq partiya topilmadi");
    }
    // Drafts have no receipt layers. Closed receipts retain supplier obligations
    // and cannot be deleted through this draft-only endpoint.
    // Delete items
    $db->q("DELETE FROM im_partiya_items WHERE partiya_id=$partiya_id");
    // Delete partiya
    $db->q("DELETE FROM im_partiyalar WHERE id=$partiya_id");
    $db->commit();
    im_json('ok', "Ochiq partiya bekor qilindi (o'chirildi)");
} catch (Throwable $e) {
    $db->rollback();
    im_json('error', "Xatolik: " . $e->getMessage());
}
