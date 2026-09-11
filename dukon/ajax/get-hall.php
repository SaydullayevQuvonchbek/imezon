<?php
// ============================================================
//  IMezon — Dukon (kassir): Zal xaritasi (barcha stollar holati)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'kassir']);

$db        = new Cyber();
$filial_id = (int)$_SESSION['im_filial_id'];

im_json('ok', '', [
    'stollar' => im_hall_stollar($db, $filial_id),
    'stolsiz' => im_hall_stolsiz_orderlar($db, $filial_id),  // faqat Dastavka
    'zonalar' => im_zonalar($db, $filial_id),                // Zona tablari (filtr)
]);
