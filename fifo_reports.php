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
    $from = im_fifo_report_dt($from); $to = im_fifo_report_dt($to); $filial = (int)$filial;
    $where = $filial > 0 ? " AND s.filial_id=$filial" : '';
    if ($kitchen) $where .= " AND v.mahsulot_id IN (SELECT id FROM im_mahsulotlar WHERE COALESCE(oshpaz_kerak,0)=1 OR COALESCE(retsept_avto,0)=1)";
    $saleCost = im_fifo_sale_unit_cost_sql('si');
    // im_vozvratlar.tannarx is the TOTAL returned cost (vozvrat-save.php), so the
    // legacy fallback (rows without a sale-item link) must divide by v.soni.
    $returnCost = "CASE WHEN si.id IS NOT NULL THEN ($saleCost) ELSE COALESCE(v.tannarx,0)/NULLIF(v.soni,0) END";
    return $db->row("SELECT COALESCE(SUM(v.qaytarish_summa),0) revenue,
        COALESCE(SUM(v.soni * ($returnCost)),0) cost,
        COALESCE(SUM(v.qaytarish_summa - v.soni * ($returnCost)),0) profit
        FROM im_vozvratlar v JOIN im_sotuvlar s ON s.id=v.sotuv_id
        LEFT JOIN im_sotuv_items si ON si.id=v.sotuv_item_id
        WHERE s.holat IN ('aktiv','qaytarilgan') AND v.sana BETWEEN '$from 00:00:00' AND '$to 23:59:59' $where");
}

// Strict date / datetime literal guard for the report helpers ($db->f() is an
// HTML escaper, not an SQL one). Throws on anything that is not YYYY-MM-DD[ HH:MM:SS].
function im_fifo_report_dt($v) {
    $v = (string)$v;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/D', $v)) {
        throw new InvalidArgumentException('Hisobot sanasi noto‘g‘ri formatda: ' . $v);
    }
    return $v;
}

// Same as im_fifo_report_returns() but with exact DATETIME bounds — shift close
// (smena) windows are not whole days. Returns are attributed to the RETURN
// time (v.sana), never to the original sale's time. $kassir > 0 narrows to
// returns processed by that cashier (v.kassir_id) so a shift's revenue and
// cost sides use the same population.
function im_fifo_report_returns_dt($db, $fromDt, $toDt, $filial = 0, $kitchen = false, $kassir = 0) {
    $fromDt = im_fifo_report_dt($fromDt); $toDt = im_fifo_report_dt($toDt); $filial = (int)$filial; $kassir = (int)$kassir;
    $where = $filial > 0 ? " AND s.filial_id=$filial" : '';
    if ($kassir > 0) $where .= " AND v.kassir_id=$kassir";
    if ($kitchen) $where .= " AND v.mahsulot_id IN (SELECT id FROM im_mahsulotlar WHERE COALESCE(oshpaz_kerak,0)=1 OR COALESCE(retsept_avto,0)=1)";
    $saleCost = im_fifo_sale_unit_cost_sql('si');
    $returnCost = "CASE WHEN si.id IS NOT NULL THEN ($saleCost) ELSE COALESCE(v.tannarx,0)/NULLIF(v.soni,0) END";
    return $db->row("SELECT COALESCE(SUM(v.qaytarish_summa),0) revenue,
        COALESCE(SUM(v.soni * ($returnCost)),0) cost,
        COALESCE(SUM(v.qaytarish_summa - v.soni * ($returnCost)),0) profit
        FROM im_vozvratlar v JOIN im_sotuvlar s ON s.id=v.sotuv_id
        LEFT JOIN im_sotuv_items si ON si.id=v.sotuv_item_id
        WHERE s.holat IN ('aktiv','qaytarilgan') AND v.sana BETWEEN '$fromDt' AND '$toDt' $where");
}

// Kitchen waste: dishes the kitchen already cooked (ingredients consumed via
// FIFO under source 'retsept') whose order was then cancelled. The ingredients
// stay consumed; pending-orders.php marks the production row holat='isrof'.
// Without this, that cost appears in no report at all (not COGS, not waste).
// Only the servings NOT already returned count: a waiter may have reduced the
// line earlier (returned_servings > 0, ingredients restored via reverse_fraction).
function im_fifo_report_kitchen_waste($db, $fromDt, $toDt, $filial = 0) {
    $fromDt = im_fifo_report_dt($fromDt); $toDt = im_fifo_report_dt($toDt); $filial = (int)$filial;
    $where = $filial > 0 ? " AND filial_id=$filial" : '';
    return (float)$db->val("SELECT COALESCE(SUM(tannarx * (chiqish_soni - returned_servings) / NULLIF(chiqish_soni,0)),0)
        FROM im_ishlab_chiqarish
        WHERE holat='isrof' AND isrof_vaqt BETWEEN '$fromDt' AND '$toDt' $where");
}

// Weighted FIFO unit cost of what is physically on hand at one location —
// the single expression POS screens (mah-cat / mah-pos / set-list) should
// share so the cashier never sees two different costs for one product.
// $productExpr / $locExpr are SQL fragments (e.g. 'm.id' and '3').
function im_fifo_report_unit_cost_sql($locExpr, $productExpr) {
    if (!preg_match('/^[a-zA-Z0-9_.]+$/D', $locExpr) || !preg_match('/^[a-zA-Z0-9_.]+$/D', $productExpr)) {
        throw new InvalidArgumentException('FIFO unit cost SQL: alias noto‘g‘ri');
    }
    return "COALESCE((SELECT SUM(fl.remaining_qty*fl.unit_cost)/NULLIF(SUM(fl.remaining_qty),0)
        FROM im_fifo_layers fl
        WHERE fl.mahsulot_id=$productExpr AND fl.location_id=$locExpr
          AND fl.cancelled=0 AND fl.remaining_qty>0),0)";
}

// Inventory count result for a period. Shortage ("kamomad") is stock written off
// through FIFO: it never reaches COGS, so profit reports must subtract it like
// waste. Surplus ("ortiqcha") is stock found, added back at FIFO cost.
// $filial: 0 = every location (warehouse + branches); >0 = that branch only.
function im_fifo_report_inventar($db, $fromDt, $toDt, $filial = 0) {
    $fromDt = im_fifo_report_dt($fromDt); $toDt = im_fifo_report_dt($toDt); $filial = (int)$filial;
    $where = $filial > 0 ? " AND location_id=$filial" : '';
    $r = $db->row("SELECT COALESCE(SUM(kamomad_summa),0) kamomad, COALESCE(SUM(ortiqcha_summa),0) ortiqcha
                   FROM im_inventarizatsiya WHERE sana BETWEEN '$fromDt' AND '$toDt' $where");
    return [
        'kamomad'  => (float)($r['kamomad'] ?? 0),
        'ortiqcha' => (float)($r['ortiqcha'] ?? 0),
        'sof'      => (float)($r['kamomad'] ?? 0) - (float)($r['ortiqcha'] ?? 0),
    ];
}
