<?php
// ============================================================
//  IMezon — Dukon: Set saqlash/tahrirlash
//  POST JSON: { id?, nomi, narxi, rang, filial_id, items:[{mahsulot_id,soni}] }
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir', 'admin']);
$db = new Cyber();

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

// Agar JSON decode muvaffaqiyatsiz bo'lsa yoki bo'sh — $_POST dan olish
if (!is_array($input)) {
    $input = $_POST;
}

$set_id   = (int)($input['id'] ?? 0);
$nomi     = trim($input['nomi'] ?? '');
$narxi    = (float)($input['narxi'] ?? 0);
$rang     = preg_match('/^#[0-9a-fA-F]{3,7}$/', $input['rang'] ?? '') ? $input['rang'] : '#e2b96f';
$filial   = (int)($input['filial_id'] ?? $im_filial_id ?? 1);
$tartib   = (int)($input['tartib'] ?? 1);

// items: JSON body dan array, form-data dan JSON string bo'lishi mumkin
$items_raw = $input['items'] ?? [];
if (is_string($items_raw)) {
    $items = json_decode($items_raw, true) ?: [];
} elseif (is_array($items_raw)) {
    $items = $items_raw;
} else {
    $items = [];
}

// Validatsiya
if (!$nomi) im_json('error', 'Set nomini kiriting');
if ($narxi <= 0) im_json('error', 'Sotuv narxini kiriting');
if (empty($items)) im_json('error', 'Kamida 1 ta mahsulot qo\'shing');
if (!$filial) im_json('error', 'Filial aniqlanmadi');

// Filial huquqini tekshirish: kassir faqat o'z filialiga yoza oladi
if ($im_rol !== 'admin' && $filial !== (int)$im_filial_id) {
    im_json('error', 'Siz faqat o\'z filialingizga set qo\'sha olasiz');
}

$nomi_s = mysqli_real_escape_string($link, $nomi);
$rang_s  = mysqli_real_escape_string($link, $rang);

$db->begin();
try {
    if ($set_id) {
        // UPDATE
        $old = $db->row("SELECT * FROM im_setlar WHERE id=$set_id AND filial_id=$filial");
        if (!$old) throw new Exception('Set topilmadi yoki sizniki emas');
        $db->q("UPDATE im_setlar SET nomi='$nomi_s', narxi=$narxi, rang='$rang_s',
                tartib=$tartib WHERE id=$set_id");
    } else {
        // INSERT
        $set_id = $db->insert(
            "INSERT INTO im_setlar (nomi, narxi, rang, filial_id, tartib, aktiv)
             VALUES ('$nomi_s', $narxi, '$rang_s', $filial, $tartib, 1)"
        );
        if (!$set_id) throw new Exception('Set yaratishda xatolik');
    }

    // Items ni tozalab qayta yozamiz
    $db->q("DELETE FROM im_set_items WHERE set_id=$set_id");

    foreach ($items as $idx => $item) {
        $mah_id = (int)($item['mahsulot_id'] ?? 0);
        $soni   = max(0.001, (float)($item['soni'] ?? 1));
        if (!$mah_id) continue;

        // Mahsulot mavjudligini tekshirish
        $mah = $db->row("SELECT id FROM im_mahsulotlar WHERE id=$mah_id AND status=1");
        if (!$mah) continue;

        $tar = (int)($idx + 1);
        $db->insert("INSERT INTO im_set_items (set_id, mahsulot_id, soni, tartib)
                     VALUES ($set_id, $mah_id, $soni, $tar)");
    }

    $db->commit();
    im_json('ok', 'Set saqlandi!', ['set_id' => $set_id]);

} catch (Exception $e) {
    $db->rollback();
    im_json('error', 'Xatolik: ' . $e->getMessage());
}
