<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir']);
$db = new Cyber();
$filial_id = (int)$im_filial_id;
$q = trim((string)($_GET['q'] ?? ''));
if (!$q || $filial_id <= 0) im_json('error', 'Filial va qidiruv matni kerak');
$qs = mysqli_real_escape_string($link, $q);

// Receipt takes precedence. Barcode search exposes every exact candidate, never LIMIT 1.
$sale = $db->row("SELECT id, chek_nomer FROM im_sotuvlar WHERE filial_id=$filial_id AND chek_nomer='$qs'");
$filter = $sale ? 'si.sotuv_id=' . (int)$sale['id'] : "m.barcode='$qs'";
$rows = $db->rows("SELECT si.id AS sotuv_item_id, si.sotuv_id, si.mahsulot_id, si.set_id,
        si.soni AS sotilgan, si.chegirma_narxi AS narx, si.fifo_return_mode,
        m.nomi, s.chek_nomer,
        COALESCE((SELECT SUM(v.soni) FROM im_vozvratlar v WHERE v.sotuv_item_id=si.id),0) AS qaytarilgan,
        EXISTS(SELECT 1 FROM im_vozvratlar v WHERE v.sotuv_id=si.sotuv_id AND v.mahsulot_id=si.mahsulot_id
            AND (v.sotuv_item_id IS NULL OR v.sotuv_item_id=0)) AS legacy_return
    FROM im_sotuv_items si
    JOIN im_sotuvlar s ON s.id=si.sotuv_id
    JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
    WHERE s.filial_id=$filial_id AND ($filter)
    ORDER BY s.id DESC, si.id LIMIT 201");
if (count($rows) > 200) im_json('error', 'Natijalar ko‘p. Aniq chek raqamini kiriting');
$items = [];
foreach ($rows as $row) {
    $remaining = round((float)$row['sotilgan'] - (float)$row['qaytarilgan'], 3);
    if ($remaining <= 0) continue;
    $eligible = in_array($row['fifo_return_mode'], ['stock', 'waste'], true) && !$row['legacy_return'];
    $items[] = [
        'sotuv_item_id' => (int)$row['sotuv_item_id'],
        'sotuv_id' => (int)$row['sotuv_id'],
        'mahsulot_id' => (int)$row['mahsulot_id'],
        'set_id' => $row['set_id'] === null ? null : (int)$row['set_id'],
        'chek' => $row['chek_nomer'],
        'nomi' => $row['nomi'],
        'soni' => $remaining,
        'sotilgan' => (float)$row['sotilgan'],
        'qaytarilgan' => (float)$row['qaytarilgan'],
        'narx' => (float)$row['narx'],
        'classification' => $row['fifo_return_mode'],
        'eligible' => $eligible,
    ];
}
if (!$items) im_json('error', 'Qaytarish uchun sotuv qatori topilmadi');
im_json('ok', '', ['sotuv_id' => $sale ? (int)$sale['id'] : null, 'chek' => $sale['chek_nomer'] ?? null, 'items' => $items]);
