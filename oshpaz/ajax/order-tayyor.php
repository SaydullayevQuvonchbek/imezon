<?php
// ============================================================
//  IMezon — Oshpaz 2-BOSQICH: "Tayyor"
//
//  Taom pishib bo'lgach oshpaz shu tugmani bosadi:
//    - status 'pishirilmoqda' → 'stol_band'
//    - ofitsant ekranida signal chiqadi (sotuvchi/ajax/get-tayyor.php
//      shu statusni ko'radi) va u taomni mijozga olib boradi
//
//  Xomashyo sarfi bu yerda BAJARILMAYDI — u 1-bosqichda
//  (order-qabul.php) allaqachon bajarilgan.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../qozon_lib.php';
im_rol_check(['oshpaz', 'admin']);

header('Content-Type: application/json; charset=utf-8');

$db        = new Cyber();
$id        = (int)($_POST['id'] ?? 0);
$filial_id = (int)$_SESSION['im_filial_id'];
$oshpaz_id = (int)$_SESSION['im_user_id'];

if (!$id) im_json('error', 'ID yo\'q');

$db->begin();
try {
    $cond  = $filial_id > 0 ? "AND filial_id=$filial_id" : "";
    $order = $db->row("SELECT id, status, mijoz_ism, filial_id FROM im_sotuvchi_order WHERE id=$id $cond FOR UPDATE");
    if (!$order) throw new Exception('Buyurtma topilmadi');
    if ($order['status'] !== 'pishirilmoqda') {
        throw new Exception('Buyurtma pishirilmoqda holatida emas — avval "Qabul qildim" bosing');
    }

    $real_filial_id = (int)$order['filial_id'];
    $qozon_items = $db->rows(
        "SELECT i.mahsulot_id, i.tayyorlandi_soni, m.nomi
         FROM im_sotuvchi_order_item i
         JOIN im_mahsulotlar m ON m.id=i.mahsulot_id
         WHERE i.order_id=$id AND m.oshpaz_kerak=1 AND m.qozon_rejim=1
           AND i.tayyorlandi_soni>0
         ORDER BY i.id FOR UPDATE"
    );
    foreach ($qozon_items as $it) {
        im_qozon_buyurtmaga_tayyor(
            $db, $real_filial_id, (int)$it['mahsulot_id'],
            (float)$it['tayyorlandi_soni'], $it['nomi']
        );
    }

    $db->q("UPDATE im_sotuvchi_order SET status='stol_band', updated_at=NOW() WHERE id=$id $cond");

    $summa     = (float)$db->val("SELECT SUM(soni*narx) FROM im_sotuvchi_order_item WHERE order_id=$id");
    $mijoz_esc = $db->f($order['mijoz_ism'] ?? '');
    $db->q(
        "INSERT INTO im_xodim_log (xodim_id, filial_id, amal, order_id, mijoz_ism, summa, izoh)
         VALUES ($oshpaz_id, $real_filial_id, 'oshpaz_tayyor', $id, " . ($mijoz_esc ? "'$mijoz_esc'" : 'NULL') . ", $summa, 'Taom tayyor — ofitsant chaqirildi')"
    );

    $db->commit();
    im_json('ok', 'Tayyor! Ofitsantga signal yuborildi');
} catch (Throwable $e) {
    $db->rollback();
    im_json('error', 'Tayyor deb belgilab bo‘lmadi: ' . $e->getMessage());
}
