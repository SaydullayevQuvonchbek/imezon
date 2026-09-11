<?php
// ============================================================
//  IMezon — Barcodesiz mahsulotlarga NH10-xxx berish
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

header('Content-Type: application/json; charset=utf-8');

// 1) Hozirgi eng katta NH10-xxx ni topamiz
$res = mysqli_query($link,
    "SELECT barcode FROM im_mahsulotlar
     WHERE barcode REGEXP '^NH[0-9]+-[0-9]+$'
     ORDER BY CAST(SUBSTRING_INDEX(barcode, '-', -1) AS UNSIGNED) DESC
     LIMIT 1"
);
$last = $res ? mysqli_fetch_assoc($res) : null;

if ($last) {
    // NH10-001 → raqam qismini olish
    $parts  = explode('-', $last['barcode']);
    $prefix = implode('-', array_slice($parts, 0, -1)); // NH10
    $num    = (int)end($parts);
} else {
    $prefix = 'NH10';
    $num    = 0;
}

// 2) Barcodesiz mahsulotlarni olish
$mahsulotlar = [];
$r2 = mysqli_query($link,
    "SELECT id FROM im_mahsulotlar
     WHERE status=1 AND (barcode IS NULL OR barcode='' OR barcode='0')
     ORDER BY id ASC"
);
if ($r2) {
    while ($row = mysqli_fetch_assoc($r2)) {
        $mahsulotlar[] = (int)$row['id'];
    }
}

if (empty($mahsulotlar)) {
    echo json_encode(['status' => 'ok', 'count' => 0,
        'msg' => 'Barcodesiz mahsulotlar topilmadi']);
    exit;
}

// 3) Har biriga barcode berish
$count = 0;
foreach ($mahsulotlar as $id) {
    $num++;
    $barcode = $prefix . '-' . str_pad($num, 3, '0', STR_PAD_LEFT); // NH10-001
    $bc_safe = mysqli_real_escape_string($link, $barcode);
    mysqli_query($link,
        "UPDATE im_mahsulotlar SET barcode='$bc_safe' WHERE id=$id"
    );
    if (mysqli_affected_rows($link) > 0) $count++;
}

echo json_encode([
    'status' => 'ok',
    'count'  => $count,
    'last'   => $prefix . '-' . str_pad($num, 3, '0', STR_PAD_LEFT),
    'msg'    => "$count ta mahsulotga barcode berildi"
], JSON_UNESCAPED_UNICODE);
