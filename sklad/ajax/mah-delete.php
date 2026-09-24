<?php
// ============================================================
//  IMezon — Mahsulot AJAX: Arxivlash (soft delete)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

$id = (int)($_POST['id'] ?? 0);
if (!$id) im_json('error', "Noto'g'ri ID");

// Qoldiqni FIFO qatlamlaridan tekshirish — OMBOR (location_id=0) va BARCHA
// filiallar. Ilgari faqat eski im_partiya_items.sklad_qoldi ustuni (int) ga
// qaralardi: filialdagi qoldiq umuman ko'rilmas, 0.7 kg esa 0 ga yaxlitlanib
// o'tib ketardi. Arxivlangan mahsulot qatlamlari inventar hisobotidan
// (m.status=1 filtri) g'oyib bo'lib, dashboard bilan farq chiqarardi.
$qoldiq = im_mahsulot_qoldiq_joylar($db, $id);
if ($qoldiq) {
    im_json('error', "Mahsulotda qoldiq bor (" . implode(', ', $qoldiq) . "). "
        . "Avval uni soting, boshqa joyga o'tkazing yoki korrektirovka qiling.");
}

$db->q("UPDATE im_mahsulotlar SET status=0 WHERE id=$id");

if ($db->affected() > 0) {
    im_json('ok', 'Mahsulot arxivlandi');
} else {
    im_json('error', "Topilmadi yoki allaqachon arxivlangan");
}
