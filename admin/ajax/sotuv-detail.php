<?php
// ============================================================
//  IMezon — Admin: Sotuv Detail (modal uchun AJAX)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();
$sale_cost = im_fifo_sale_unit_cost_sql('si');
$line_revenue = im_sotuv_qator_tushum_sql('s', 'si');
$returned_revenue = "(SELECT COALESCE(SUM(v.qaytarish_summa),0) FROM im_vozvratlar v WHERE v.sotuv_item_id=si.id)";
$returned_qty = "(SELECT COALESCE(SUM(v.soni),0) FROM im_vozvratlar v WHERE v.sotuv_item_id=si.id)";

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID kerak']); exit; }

// Sotuv asosiy ma'lumotlari
$s = $db->row(
    "SELECT s.*,
            m.ism AS mijoz_ism, m.telefon AS mijoz_tel,
            x.ism AS kassir_ism,
            f.nomi AS filial_nomi
     FROM im_sotuvlar s
     LEFT JOIN im_mijozlar m ON m.id = s.mijoz_id
     LEFT JOIN im_xodimlar x ON x.id = s.kassir_id
     LEFT JOIN im_filiallar f ON f.id = s.filial_id
     WHERE s.id = $id"
);
if (!$s) { http_response_code(404); echo json_encode(['error' => 'Sotuv topilmadi']); exit; }

// Mahsulotlar ro'yxati
$items = $db->rows(
    "SELECT si.*,
            $sale_cost AS tannarx,
            mah.nomi AS mahsulot_nomi,
            mah.birlik,
            ($line_revenue)-$returned_revenue AS hisob_tushum,
            $returned_revenue AS qaytarish_summa,
            $returned_qty AS qaytarilgan_soni,
            (($line_revenue)-$returned_revenue)-(($sale_cost)*(si.soni-$returned_qty)) AS marja_summa,
            CASE WHEN (($line_revenue)-$returned_revenue) > 0
                 THEN ROUND(((($line_revenue)-$returned_revenue)-(($sale_cost)*(si.soni-$returned_qty)))
                      /(($line_revenue)-$returned_revenue)*100, 1)
                 ELSE 0 END AS marja_foiz
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id=si.sotuv_id
     JOIN im_mahsulotlar mah ON mah.id = si.mahsulot_id
     WHERE si.sotuv_id = $id
     ORDER BY si.id ASC"
);

// Jami marja
$jami_marja = array_sum(array_column($items, 'marja_summa'));

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'sotuv'      => $s,
    'items'      => $items,
    'jami_marja' => $jami_marja,
], JSON_UNESCAPED_UNICODE);
