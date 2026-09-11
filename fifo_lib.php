<?php
// Physical FIFO ledger. Callers own the transaction, including document/payment writes.
// im_filial_qoldiq.soni remains AVAILABLE stock (physical less active reservations).
function im_fifo_number($n, $scale = 3) {
    if (!is_numeric($n) || !is_finite((float)$n)) throw new Exception('FIFO: noto‘g‘ri raqam');
    return number_format((float)$n, $scale, '.', '');
}
function im_fifo_source($source) {
    if (!preg_match('/^[a-zA-Z0-9_-]{1,40}$/D', $source)) throw new Exception('FIFO: manba noto‘g‘ri');
    return $source;
}
function im_fifo_exec($db, $sql) {
    $result = $db->q($sql);
    if ($result === false) throw new Exception('FIFO yozuvi saqlanmadi: ' . $db->error());
    return $result;
}
function im_fifo_lock($db, $loc, $product) {
    if (!$db->inTransaction()) throw new Exception('FIFO amali tranzaksiya ichida bajarilishi kerak');
    $loc = (int)$loc; $product = (int)$product;
    if ($loc < 0 || $product <= 0) throw new Exception('FIFO: joy yoki mahsulot noto‘g‘ri');
    // Global per-product/location serialization also protects an empty layer set.
    im_fifo_exec($db, "INSERT IGNORE INTO im_fifo_locks VALUES ($loc,$product)");
    $db->row("SELECT mahsulot_id FROM im_fifo_locks WHERE location_id=$loc AND mahsulot_id=$product FOR UPDATE");
    if ($loc > 0) {
        im_fifo_exec($db, "INSERT IGNORE INTO im_filial_qoldiq (filial_id,mahsulot_id,soni) VALUES ($loc,$product,0)");
        $db->row("SELECT id FROM im_filial_qoldiq WHERE filial_id=$loc AND mahsulot_id=$product FOR UPDATE");
    }
}
function im_fifo_balance($db, $loc, $product) {
    $loc = (int)$loc; $product = (int)$product;
    $r = $db->row("SELECT COALESCE(SUM(remaining_qty),0) qty, COALESCE(SUM(remaining_qty*unit_cost),0) value FROM im_fifo_layers WHERE location_id=$loc AND mahsulot_id=$product AND cancelled=0");
    if (!$r) throw new Exception('FIFO sxemasi mavjud emas; migratsiyani bajaring');
    $r['qty'] = (float)$r['qty']; $r['value'] = (float)$r['value'];
    $r['unit_cost'] = $r['qty'] > 0 ? $r['value'] / $r['qty'] : 0;
    return $r;
}
function im_fifo_cache_price($db, $loc, $product) {
    if ($loc <= 0) return;
    $balance = im_fifo_balance($db, $loc, $product);
    $cost = im_fifo_number($balance['unit_cost'], 6);
    im_fifo_exec($db, "UPDATE im_filial_qoldiq SET kelish_narxi=$cost WHERE filial_id=$loc AND mahsulot_id=$product");
}

// Effective unit cost for a sale line. FIFO movements are the durable link
// between a sale item and every source layer it consumed. For pot products the
// layer's final cost can change when the real yield is recorded at pot close;
// reports therefore resolve cost here instead of rewriting historic sale rows.
function im_fifo_sale_unit_cost_sql($saleAlias = 'si') {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $saleAlias)) {
        throw new InvalidArgumentException('Sotuv qatori aliasi noto‘g‘ri');
    }
    return "COALESCE((
        SELECT SUM(fm.qty * CASE
                    WHEN fl.source='qozon' THEN COALESCE(q.yakuniy_tannarx, fl.unit_cost)
                    ELSE fl.unit_cost
                END) / NULLIF($saleAlias.soni,0)
        FROM im_fifo_movements fm
        JOIN im_fifo_layers fl ON fl.id=fm.layer_id
        LEFT JOIN im_osh_qozon q ON fl.source='qozon' AND q.id=fl.source_id
        WHERE fm.source='sotuv' AND fm.source_id=$saleAlias.id AND fm.kind='take'
    ), COALESCE($saleAlias.tannarx,0))";
}
function im_fifo_receive($db, $loc, $product, $qty, $unitCost, $source, $sourceId, $partiyaItemId = null) {
    $loc = (int)$loc; $product = (int)$product; $sourceId = (int)$sourceId;
    $source = im_fifo_source($source); $qty = im_fifo_number($qty); $unitCost = im_fifo_number($unitCost, 6);
    if ($qty <= 0 || $unitCost < 0) throw new Exception('FIFO kirim miqdori yoki tannarxi noto‘g‘ri');
    im_fifo_lock($db, $loc, $product);
    $pi = $partiyaItemId ? (int)$partiyaItemId : 'NULL';
    im_fifo_exec($db, "INSERT INTO im_fifo_layers (location_id,mahsulot_id,partiya_item_id,source,source_id,initial_qty,remaining_qty,unit_cost,created_at) VALUES ($loc,$product,$pi,'$source',$sourceId,$qty,$qty,$unitCost,NOW())");
    $id = (int)$db->val('SELECT LAST_INSERT_ID()');
    if ($loc > 0) im_fifo_exec($db, "UPDATE im_filial_qoldiq SET soni=soni+$qty WHERE filial_id=$loc AND mahsulot_id=$product");
    im_fifo_cache_price($db, $loc, $product);
    return $id;
}
function im_fifo_preview($db, $loc, $product, $qty) {
    $loc = (int)$loc; $product = (int)$product; $qty = (float)im_fifo_number($qty);
    if ($qty <= 0) throw new Exception('FIFO sarf miqdori musbat bo‘lishi kerak');
    $left = $qty; $cost = 0; $allocations = [];
    $locking = $db->inTransaction() ? ' FOR UPDATE' : '';
    foreach ($db->rows("SELECT * FROM im_fifo_layers WHERE location_id=$loc AND mahsulot_id=$product AND cancelled=0 AND remaining_qty>0 ORDER BY id" . $locking) as $r) {
        $take = min($left, (float)$r['remaining_qty']);
        $cost += $take * (float)$r['unit_cost'];
        $allocations[] = [
            'layer_id'=>(int)$r['id'],
            'qty'=>$take,
            'unit_cost'=>(float)$r['unit_cost'],
            'partiya_item_id'=>$r['partiya_item_id'],
            'source'=>$r['source'],
            'source_id'=>(int)$r['source_id'],
        ];
        $left = round($left-$take,3);
        if ($left <= 0) break;
    }
    if ($left > 0) throw new Exception("FIFO qoldiq yetarli emas (#$product): kerak $qty, yetishmaydi $left");
    return ['cost'=>$cost,'total'=>$cost,'unit_cost'=>$qty>0?$cost/$qty:0,'allocations'=>$allocations];
}
function im_fifo_take($db, $loc, $product, $qty, $source, $sourceId) {
    $loc = (int)$loc; $product = (int)$product; $sourceId = (int)$sourceId;
    $source = im_fifo_source($source); $qty = im_fifo_number($qty);
    im_fifo_lock($db, $loc, $product);
    // Locking reads see current committed state even under REPEATABLE READ.
    $db->rows("SELECT id FROM im_fifo_layers WHERE location_id=$loc AND mahsulot_id=$product AND cancelled=0 ORDER BY id FOR UPDATE");
    $result = im_fifo_preview($db, $loc, $product, $qty);
    if ($loc > 0) {
        im_fifo_exec($db, "UPDATE im_filial_qoldiq SET soni=soni-$qty WHERE filial_id=$loc AND mahsulot_id=$product AND soni>=$qty");
        if ($db->affected() !== 1) throw new Exception("Mahsulot #$product yetarli emas yoki boshqa buyurtmaga band");
    }
    foreach ($result['allocations'] as &$a) {
        $id = $a['layer_id']; $q = im_fifo_number($a['qty']); $c = im_fifo_number($a['unit_cost'],6);
        im_fifo_exec($db, "UPDATE im_fifo_layers SET remaining_qty=remaining_qty-$q WHERE id=$id AND remaining_qty>=$q AND cancelled=0");
        if ($db->affected() !== 1) throw new Exception('FIFO qoldiq o‘zgargan; amalni qaytaring');
        im_fifo_exec($db, "INSERT INTO im_fifo_movements(layer_id,source,source_id,kind,qty,unit_cost,created_at) VALUES($id,'$source',$sourceId,'take',$q,$c,NOW())");
        $a['id'] = (int)$db->val('SELECT LAST_INSERT_ID()');
    }
    unset($a);
    im_fifo_cache_price($db, $loc, $product);
    return $result;
}
function im_fifo_transfer($db, $from, $to, $product, $qty, $source, $sourceId) {
    if ((int)$from === (int)$to) throw new Exception('Bir joyga ko‘chirish mumkin emas');
    foreach ([min($from,$to),max($from,$to)] as $loc) im_fifo_lock($db,$loc,$product);
    $r = im_fifo_take($db,$from,$product,$qty,$source,$sourceId);
    foreach ($r['allocations'] as &$a) $a['destination_layer_id'] = im_fifo_receive($db,$to,$product,$a['qty'],$a['unit_cost'],$source,$sourceId,$a['partiya_item_id']);
    unset($a);
    return $r;
}
function im_fifo_restore_movement($db, $m, $qty) {
    $qty = im_fifo_number($qty); $mid = (int)$m['id']; $lid = (int)$m['layer_id'];
    $l = $db->row("SELECT * FROM im_fifo_layers WHERE id=$lid FOR UPDATE");
    if (!$l || $l['cancelled']) throw new Exception('Asl FIFO partiyasi bekor qilingan');
    im_fifo_exec($db,"UPDATE im_fifo_movements SET reversed_qty=reversed_qty+$qty WHERE id=$mid AND qty-reversed_qty>=$qty");
    if ($db->affected() !== 1) throw new Exception('FIFO qaytarish sarfdan oshib ketdi');
    im_fifo_exec($db,"UPDATE im_fifo_layers SET remaining_qty=remaining_qty+$qty WHERE id=$lid AND remaining_qty+$qty<=initial_qty");
    if ($db->affected() !== 1) throw new Exception('FIFO qaytarish kirimdan oshib ketdi');
    $cost = im_fifo_number($m['unit_cost'],6); $source = im_fifo_source($m['source']); $sid = (int)$m['source_id'];
    im_fifo_exec($db,"INSERT INTO im_fifo_movements(layer_id,source,source_id,kind,qty,unit_cost,original_id,created_at) VALUES($lid,'$source',$sid,'return',$qty,$cost,$mid,NOW())");
    if ($l['location_id'] > 0) im_fifo_exec($db,"UPDATE im_filial_qoldiq SET soni=soni+$qty WHERE filial_id={$l['location_id']} AND mahsulot_id={$l['mahsulot_id']}");
    im_fifo_cache_price($db,(int)$l['location_id'],(int)$l['mahsulot_id']);
    return (float)$qty*(float)$cost;
}
function im_fifo_reverse($db, $source, $sourceId, $qty = null) {
    $source = im_fifo_source($source); $sourceId = (int)$sourceId;
    $rows = $db->rows("SELECT m.*,l.location_id,l.mahsulot_id FROM im_fifo_movements m JOIN im_fifo_layers l ON l.id=m.layer_id WHERE m.source='$source' AND m.source_id=$sourceId AND m.kind='take' ORDER BY l.location_id,l.mahsulot_id,m.id");
    if (!$rows) throw new Exception('Asl FIFO sarfi topilmadi; tarixiy hujjatni avtomatik qaytarib bo‘lmaydi');
    foreach ($rows as $m) im_fifo_lock($db,$m['location_id'],$m['mahsulot_id']);
    $rows = $db->rows("SELECT * FROM im_fifo_movements WHERE source='$source' AND source_id=$sourceId AND kind='take' ORDER BY id FOR UPDATE");
    $total = 0; foreach ($rows as $m) $total += (float)$m['qty']-(float)$m['reversed_qty'];
    $left = $qty === null ? round($total,3) : (float)im_fifo_number($qty);
    if ($left <= 0 || $left > round($total,3)) throw new Exception('Qaytarish miqdori asl sarfdan oshdi yoki allaqachon qaytarilgan');
    $cost = 0;
    foreach ($rows as $m) {
        $q = min($left,round((float)$m['qty']-(float)$m['reversed_qty'],3));
        if ($q > 0) $cost += im_fifo_restore_movement($db,$m,$q);
        $left = round($left-$q,3); if ($left <= 0) break;
    }
    return $cost;
}
// Fraction of REMAINING recipe quantities; callers track remaining servings.
function im_fifo_reverse_fraction($db,$source,$sourceId,$fraction) {
    $source=im_fifo_source($source); $sourceId=(int)$sourceId;
    if ($fraction<=0 || $fraction>1) throw new Exception('Qaytarish ulushi noto‘g‘ri');
    $rows=$db->rows("SELECT m.*,l.location_id,l.mahsulot_id FROM im_fifo_movements m JOIN im_fifo_layers l ON l.id=m.layer_id WHERE m.source='$source' AND m.source_id=$sourceId AND m.kind='take' ORDER BY l.location_id,l.mahsulot_id,m.id");
    if (!$rows) throw new Exception('Asl FIFO sarfi topilmadi');
    foreach($rows as $m) im_fifo_lock($db,$m['location_id'],$m['mahsulot_id']);
    $rows=$db->rows("SELECT * FROM im_fifo_movements WHERE source='$source' AND source_id=$sourceId AND kind='take' ORDER BY id FOR UPDATE");
    $cost=0;
    foreach($rows as $m) { $q=round(((float)$m['qty']-(float)$m['reversed_qty'])*$fraction,3); if($q>0) $cost+=im_fifo_restore_movement($db,$m,$q); }
    return $cost;
}
function im_fifo_cancel_receipt($db,$source,$sourceId) {
    $source=im_fifo_source($source); $sourceId=(int)$sourceId;
    $rows=$db->rows("SELECT * FROM im_fifo_layers WHERE source='$source' AND source_id=$sourceId ORDER BY location_id,mahsulot_id,id");
    if(!$rows) throw new Exception('FIFO kirim partiyasi topilmadi');
    foreach($rows as $r) im_fifo_lock($db,$r['location_id'],$r['mahsulot_id']);
    $rows=$db->rows("SELECT * FROM im_fifo_layers WHERE source='$source' AND source_id=$sourceId ORDER BY id FOR UPDATE");
    foreach($rows as $r) {
        if($r['cancelled'] || (float)$r['remaining_qty']!==(float)$r['initial_qty']) throw new Exception('Partiya ishlatilgan yoki bekor qilingan; kirimni bekor qilib bo‘lmaydi');
        $loc=(int)$r['location_id']; $pid=(int)$r['mahsulot_id']; $q=im_fifo_number($r['remaining_qty']);
        if($loc>0) { im_fifo_exec($db,"UPDATE im_filial_qoldiq SET soni=soni-$q WHERE filial_id=$loc AND mahsulot_id=$pid AND soni>=$q"); if($db->affected()!==1) throw new Exception('Mahsulot buyurtmaga band'); }
        im_fifo_exec($db,"UPDATE im_fifo_layers SET remaining_qty=0,cancelled=1 WHERE id={$r['id']}");
        im_fifo_exec($db,"INSERT INTO im_fifo_movements(layer_id,source,source_id,kind,qty,unit_cost,created_at) VALUES({$r['id']},'$source',$sourceId,'cancel',$q,{$r['unit_cost']},NOW())");
        im_fifo_cache_price($db,$loc,$pid);
    }
}
