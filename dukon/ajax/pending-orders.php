<?php
// ============================================================
//  IMezon — POS: Kutilayotgan sotuvchi orderlari
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir']);
header('Content-Type: application/json; charset=utf-8');

$db = new Cyber();
$filial_id = (int)$_SESSION['im_filial_id'];

// ── Orderni bekor qilish ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $oid = (int)($_POST['order_id'] ?? 0);
    if (!$oid) { echo json_encode(['status'=>'error','msg'=>'ID yo\'q']); exit; }
    // Faqat kassaga kelgan (tasdiqlandi/qabul) orderlarni bekor qilish mumkin
    // Bekor qilishdan OLDIN band qilingan qoldiqni qaytaramiz —
    // aks holda mahsulot hech kimga sotilmasdan yo'qolib qolardi.
    $hint = $db->row(
        "SELECT stol_id FROM im_sotuvchi_order
         WHERE id=$oid AND filial_id=$filial_id AND status IN ('tasdiqlandi','qabul')"
    );
    if (!$hint) {
        echo json_encode(['status'=>'error','msg'=>'Order topilmadi yoki bekor qilib bo‘lmaydi'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db->begin();
    $stol_id = $hint['stol_id'] !== null ? (int)$hint['stol_id'] : 0;
    if ($stol_id) {
        $db->row("SELECT id FROM im_stollar WHERE id=$stol_id AND filial_id=$filial_id FOR UPDATE");
    }
    $locked = $db->row(
        "SELECT id FROM im_sotuvchi_order
         WHERE id=$oid AND filial_id=$filial_id AND status IN ('tasdiqlandi','qabul') FOR UPDATE"
    );
    if (!$locked) {
        $db->rollback();
        echo json_encode(['status'=>'error','msg'=>'Order boshqa qurilmada o‘zgargan'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    im_rezerv_bekor($db, $oid, $filial_id);
    // Oshpaz allaqachon pishirgan taomlar: xomashyo FIFO'dan yechilgan holda
    // qoladi (jismonan to'g'ri — ovqat tayyor bo'lgan), lekin unga sotuv
    // bog'lanmaydi. Yorliqsiz qolsa bu tannarx na COGS'da, na isrofda
    // ko'rinmasdi. Endi 'isrof' — balans/smena/dashboard uni oshxona isrofi
    // sifatida chegiradi (fifo_reports.php: im_fifo_report_kitchen_waste).
    $db->q("UPDATE im_ishlab_chiqarish ic
            JOIN im_sotuvchi_order_item i ON i.id=ic.order_item_id
            SET ic.holat='isrof', ic.isrof_vaqt=NOW()
            WHERE i.order_id=$oid AND ic.filial_id=$filial_id AND ic.holat='bajarildi'");
    $db->q("UPDATE im_sotuvchi_order SET status='bekor', updated_at=NOW()
            WHERE id=$oid AND filial_id=$filial_id");
    if ($db->error()) {
        $db->rollback();
        echo json_encode(['status'=>'error','msg'=>'Orderni bekor qilishda baza xatosi'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $db->commit();
    echo json_encode(['status'=>'ok','msg'=>'Order bekor qilindi'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Bitta order itemlarini yuklash ─────────────────────────
if (isset($_GET['order_id'])) {
    $oid = (int)$_GET['order_id'];
    $ord = $db->row(
        "SELECT stol_id, olib_ketish, mijoz_ism, status
         FROM im_sotuvchi_order
         WHERE id=$oid AND filial_id=$filial_id
           AND status IN ('tasdiqlandi','qabul')"
    );
    if (!$ord) {
        echo json_encode(['status'=>'error','msg'=>'Buyurtma topilmadi'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $items = $db->rows(
        "SELECT i.*, m.nomi, m.birlik, i.olib_ketish_soni,
                st.nomi AS set_nomi,
                COALESCE(nu.min_soni, 0)      AS ulg_min,
                COALESCE(nu.ulgurji_narxi, 0) AS ulg_narx,
                i.narx AS chegirma_narxi
         FROM im_sotuvchi_order_item i
         JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
         LEFT JOIN im_setlar st ON st.id = i.set_id
         LEFT JOIN im_narx_ulgurji nu ON nu.mahsulot_id = i.mahsulot_id AND nu.aktiv = 1
         WHERE i.order_id = $oid"
    );
    // Orderni qabul qilish (faqat tasdiqlandi statusida)
    $db->q("UPDATE im_sotuvchi_order SET status='qabul', updated_at=NOW()
            WHERE id=$oid AND filial_id=$filial_id
              AND status = 'tasdiqlandi'");
    echo json_encode([
        'status'      => 'ok',
        'items'       => $items,
        'olib_ketish' => (bool)($ord['olib_ketish'] ?? false),
        'stol_id'     => isset($ord['stol_id']) ? (int)$ord['stol_id'] : 0,
        'mijoz_ism'   => $ord['mijoz_ism'] ?? '',
        'order_status'=> $ord['status'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Barcha kutilayotgan orderlar ───────────────────────────
$orders = $db->rows(
    "SELECT o.id, o.mijoz_ism, o.stol_id, o.olib_ketish, o.created_at,
            x.ism AS sotuvchi_ism,
            COUNT(i.id) AS item_soni,
            SUM(i.soni * i.narx) AS jami_summa
     FROM im_sotuvchi_order o
     JOIN im_xodimlar x ON x.id = o.sotuvchi_id
     LEFT JOIN im_sotuvchi_order_item i ON i.order_id = o.id
     WHERE o.filial_id = $filial_id
       AND o.status IN ('tasdiqlandi', 'qabul')
     GROUP BY o.id
     ORDER BY o.created_at DESC"
);
foreach ($orders as &$order) {
    $order['id']           = (int)$order['id'];
    $order['stol_id']      = $order['stol_id'] !== null ? (int)$order['stol_id'] : null;
    $order['olib_ketish']  = (bool)$order['olib_ketish'];
    $order['item_soni']    = (int)$order['item_soni'];
    $order['jami_summa']   = (float)$order['jami_summa'];
}
unset($order);

echo json_encode(['status'=>'ok','orders'=>$orders,'count'=>count($orders)], JSON_UNESCAPED_UNICODE);
