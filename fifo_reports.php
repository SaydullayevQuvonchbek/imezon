<?php
// Read-only FIFO reporting projections. Load explicitly; config.php is parent-owned.
// Physical stock includes reserved units. Availability remains im_filial_qoldiq.soni.
function im_fifo_report_stock_sql() {
    return "SELECT location_id AS filial_id, mahsulot_id, SUM(remaining_qty) AS soni,
                   SUM(remaining_qty * unit_cost) AS qiymat,
                   SUM(remaining_qty * unit_cost) / NULLIF(SUM(remaining_qty),0) AS kelish_narxi
            FROM im_fifo_layers WHERE cancelled=0 AND remaining_qty>0
            GROUP BY location_id, mahsulot_id";
}

// Refund amount and recorded FIFO cost belong to the return date/location.
// No product-to-sale-item join: one product may occur on multiple receipt lines.
function im_fifo_report_returns($db, $from, $to, $filial = 0, $kitchen = false) {
    $from = $db->f($from); $to = $db->f($to); $filial = (int)$filial;
    $where = $filial > 0 ? " AND s.filial_id=$filial" : '';
    if ($kitchen) $where .= " AND v.mahsulot_id IN (SELECT id FROM im_mahsulotlar WHERE COALESCE(oshpaz_kerak,0)=1 OR COALESCE(retsept_avto,0)=1)";
    $saleCost = im_fifo_sale_unit_cost_sql('si');
    $returnCost = "CASE WHEN si.id IS NOT NULL THEN ($saleCost) ELSE COALESCE(v.tannarx,0) END";
    return $db->row("SELECT COALESCE(SUM(v.qaytarish_summa),0) revenue,
        COALESCE(SUM(v.soni * ($returnCost)),0) cost,
        COALESCE(SUM(v.qaytarish_summa - v.soni * ($returnCost)),0) profit
        FROM im_vozvratlar v JOIN im_sotuvlar s ON s.id=v.sotuv_id
        LEFT JOIN im_sotuv_items si ON si.id=v.sotuv_item_id
        WHERE s.holat IN ('aktiv','qaytarilgan') AND v.sana BETWEEN '$from 00:00:00' AND '$to 23:59:59' $where");
}
