<?php
// ============================================================
//  IMezon — Barcode Generatsiya AJAX (GET)
//  Format: NHxx-xxx (Masalan: NH10-001)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

// Barcha qisqa formatdagi 'NH...-...' shtrix-kodlarni olib chiqamiz
$all = $db->rows("SELECT barcode FROM im_mahsulotlar WHERE barcode LIKE 'NH%-%' AND LENGTH(barcode) <= 10");
$max_num = 10000; // Boshlang'ich raqam, 1 qiymat qo'shilganda 10001 (ya'ni NH10-001) bo'ladi

foreach ($all as $b) {
    // Masalan, NH10-001 yozuvidan 'NH' va '-' ni olib tashlasak 10001 raqami qoladi
    $clean = str_replace(['IM', '-'], '', $b['barcode']);
    if (is_numeric($clean)) {
        $val = (int)$clean;
        if ($val > $max_num) {
            $max_num = $val;
        }
    }
}

$num = $max_num + 1;

function formatBarcode($n) {
    if ($n < 10000) $n = 10000;
    $prefix = floor($n / 1000);
    $suffix = $n % 1000;
    return 'IM' . $prefix . '-' . str_pad($suffix, 3, '0', STR_PAD_LEFT);
}

$barcode = formatBarcode($num);

// Uniqueness tekshiruvi (band bo'lsa keyingisiga o'tadi)
$tries = 0;
while ($db->val("SELECT id FROM im_mahsulotlar WHERE barcode='$barcode'") && $tries < 9999) {
    $num++;
    $tries++;
    $barcode = formatBarcode($num);
}

im_json('ok', 'Barcode tayyor', ['barcode' => $barcode]);
