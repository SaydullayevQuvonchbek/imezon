<?php
// ============================================================
//  IMezon — Oshpaz 1-BOSQICH: "Qabul qildim"
//
//  Oshpaz buyurtmani qabul qilganda:
//    - Retsept bog'langan bo'lsa, xomashyo filial qoldig'idan
//      AVTOMATIK sarflanadi (a-la-carte model: tayyor mahsulot
//      zaxirada turmaydi, buyurtma bo'yicha pishiriladi).
//    - tayyorlandi_soni = soni  (sotuvchi endi kamaytira olmaydi)
//    - status = 'pishirilmoqda'
//
//  Xomashyo yetmasa — butun qabul bekor qilinadi (rollback),
//  hech narsa yarim bajarilmaydi.
//
//  2-bosqich (taom pishib bo'lgach) — order-tayyor.php.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../qayta-ishlash/ishlab_lib.php';
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
$order = $db->row("SELECT id, status, filial_id FROM im_sotuvchi_order WHERE id=$id $cond FOR UPDATE");
if (!$order) throw new Exception('Buyurtma topilmadi');

$filial_id = (int)$order['filial_id'];
if ($order['status'] !== 'oshpazda') {
    throw new Exception('Buyurtma oshpazda emas (allaqachon qabul qilingan)');
}

// MUHIM: faqat HALI TAYYORLANMAGAN qism (delta) sarflanadi.
// Ilgari bu yerda item'ning to'liq `soni` si olinardi — natijada
// ofitsant keyinroq mahsulot qo'shsa, oshpaz ikkinchi marta qabul
// qilganda butun miqdor uchun xomashyo QAYTA sarflanardi.
$tayyorlanadigan = $db->rows(
    "SELECT i.id AS item_id, i.mahsulot_id, i.soni, i.tayyorlandi_soni, m.nomi,
            COALESCE(m.qozon_rejim, 0) AS qozon_rejim
     FROM im_sotuvchi_order_item i
     JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
     WHERE i.order_id = $id AND m.oshpaz_kerak = 1 AND i.soni > i.tayyorlandi_soni ORDER BY i.id FOR UPDATE"
);

if (empty($tayyorlanadigan)) {
    throw new Exception('Bu buyurtmada tayyorlanadigan yangi mahsulot yo\'q');
}

    // ── Barcha xomashyo qulflarini OLDINDAN, id o'sish tartibida olish ──
    // Ko'p taomli buyurtmada har retsept o'z ichida tartiblangan, lekin
    // retseptlar orasida emas (1-taom {5,10}, 2-taom {3,7}). Checkout esa
    // qatorlarni id bo'yicha ketma-ket qulflaydi — 5 ni ushlab 3 ni so'rasak,
    // aylana chiqadi. Birlashgan ro'yxatni oldindan o'sish tartibida qulflash
    // aylanani yopadi; keyingi im_fifo_take() qayta-kirish tarzida o'tadi.
    $xomashyo_ids = [];
    foreach ($tayyorlanadigan as $it) {
        if ((int)$it['qozon_rejim'] === 1) continue;
        $mid_pre = (int)$it['mahsulot_id'];
        $rid_pre = (int)$db->val(
            "SELECT id FROM im_retseptlar WHERE mahsulot_id=$mid_pre AND tur='ishlab_chiqarish' AND status=1
             ORDER BY id DESC LIMIT 1"
        );
        if (!$rid_pre) continue; // pastda aniq xato beriladi
        foreach ($db->rows("SELECT DISTINCT mahsulot_id FROM im_retsept_items WHERE retsept_id=$rid_pre") as $ri) {
            $xomashyo_ids[(int)$ri['mahsulot_id']] = true;
        }
    }
    $xomashyo_ids = array_keys($xomashyo_ids);
    sort($xomashyo_ids, SORT_NUMERIC);
    foreach ($xomashyo_ids as $lock_pid) im_fifo_lock($db, $filial_id, $lock_pid);

    // ── Har bir item uchun retsept xomashyosini sarflash ───────
    foreach ($tayyorlanadigan as $it) {
        $mid   = (int)$it['mahsulot_id'];
        $delta = (float)$it['soni'] - (float)$it['tayyorlandi_soni'];
        if ($delta <= 0) continue;

        // Qozon rejimidagi mahsulot (osh, shashlik) — xomashyo HAR
        // BUYURTMADA emas, kuniga qozon/partiya ochilganda yechiladi
        // (oshpaz/qozon.php). Bu yerda qayta yechilsa ikki marta
        // hisoblanardi. Tayyor FIFO qoldig'i yetmasa ochiq qozon bo'lishi
        // shart; aks holda buyurtma oshxona bosqichidan o'tkazilmaydi.
        if ((int)$it['qozon_rejim'] === 1) {
            im_qozon_buyurtmaga_tayyor($db, $filial_id, $mid, $delta, $it['nomi']);
            continue;
        }

        $retsept_id = (int)$db->val(
            "SELECT id FROM im_retseptlar WHERE mahsulot_id=$mid AND tur='ishlab_chiqarish' AND status=1
             ORDER BY id DESC LIMIT 1"
        );
        // Retseptsiz o'tkazib yuborilsa xomashyo yechilmaydi va kassa bu qatorni
        // "FIFO tannarxi topilmadi" bilan sotolmaydi (sotuv-save.php). Shuning
        // uchun jim o'tmaymiz — butun qabul rollback bo'ladi, oshpaz aniq sababni ko'radi.
        if (!$retsept_id) {
            throw new Exception("«{$it['nomi']}» uchun faol retsept yo'q — qabul qilib bo'lmaydi. "
                . "Admin «Retseptlar» bo'limida retsept kiritishi kerak.");
        }
        im_retsept_xomashyo_sarflash(
            $db, $retsept_id, $delta, $filial_id, $oshpaz_id,
            "Buyurtma #{$id} — {$it['nomi']} ({$delta} dona) uchun oshpaz qabuli", (int)$it['item_id']
        );
    }

    // tayyorlandi_soni = soni → sotuvchi endi kamaytira olmaydi
    im_ishlab_query($db, 
        "UPDATE im_sotuvchi_order_item i
         JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
         SET i.tayyorlandi_soni = i.soni
         WHERE i.order_id = $id AND m.oshpaz_kerak = 1"
    );

    im_ishlab_query($db, "UPDATE im_sotuvchi_order SET status='pishirilmoqda', updated_at=NOW() WHERE id=$id $cond");

    // ── Item darajasida log ────────────────────────────────────
    foreach ($tayyorlanadigan as $it) {
        $item_id   = (int)$it['item_id'];
        $mid       = (int)$it['mahsulot_id'];
        $soni      = (float)$it['soni'];
        $eski_soni = (float)$it['tayyorlandi_soni'];
        $nomi_e    = $db->f($it['nomi']);
        im_ishlab_query($db, 
            "INSERT INTO im_order_item_log
                (order_id, item_id, mahsulot_id, xodim_id, filial_id, amal, eski_soni, yangi_soni, nomi)
             VALUES ($id, $item_id, $mid, $oshpaz_id, $filial_id, 'oshpaz_qabul', $eski_soni, $soni, '$nomi_e')"
        );
    }

    // Xodim tarixi (order darajasi)
    $o_info    = $db->row("SELECT mijoz_ism FROM im_sotuvchi_order WHERE id=$id");
    $summa     = (float)$db->val("SELECT SUM(soni*narx) FROM im_sotuvchi_order_item WHERE order_id=$id");
    $mijoz_esc = $db->f($o_info['mijoz_ism'] ?? '');
    im_ishlab_query($db, 
        "INSERT INTO im_xodim_log (xodim_id, filial_id, amal, order_id, mijoz_ism, summa, izoh)
        VALUES ($oshpaz_id, $filial_id, 'oshpaz_qabul', $id, " . ($mijoz_esc ? "'$mijoz_esc'" : 'NULL') . ", $summa, 'Qabul qilindi, pishirish boshlandi')"
    );

    $db->commit();
    im_json('ok', 'Qabul qilindi! Buyurtma pishirishga o‘tkazildi');

} catch (Throwable $e) {
    $db->rollback();
    im_json('error', 'Qabul qilib bo\'lmadi: ' . $e->getMessage());
}
