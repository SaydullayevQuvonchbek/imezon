<?php
// FIFO production helpers. The caller owns the transaction.
function im_ishlab_query($db, $sql) {
    if (!$db->q($sql)) throw new Exception('Ishlab chiqarish yozilmadi: ' . $db->error());
}
function im_ishlab_audit_item($db, $id, $type, $product, $qty, $cost) {
    $id=(int)$id; $product=(int)$product; $qty=(float)$qty; $cost=(float)$cost;
    im_ishlab_query($db, "INSERT INTO im_ishlab_chiqarish_items (ishlab_id,tur,mahsulot_id,partiya_item_id,soni,kelish_narxi) VALUES ($id,'$type',$product,NULL,$qty,$cost)");
}
function im_retsept_xomashyo_sarflash($db, $retsept_id, $buyurtma_soni, $filial_id, $xodim_id, $izoh = '', $order_item_id = null) {
    global $link;
    $retsept_id=(int)$retsept_id; $filial_id=(int)$filial_id; $xodim_id=(int)$xodim_id;
    $qty=(float)$buyurtma_soni;
    if (!is_finite($qty) || $qty<=0 || $filial_id<=0) throw new Exception('Miqdor yoki filial noto‘g‘ri');
    $r=$db->row("SELECT * FROM im_retseptlar WHERE id=$retsept_id AND tur='ishlab_chiqarish' AND status=1");
    if (!$r) throw new Exception('Retsept topilmadi');
    $base=(float)$r['chiqish_soni'];
    if ($base<=0) throw new Exception('Retsept chiqish miqdori noto‘g‘ri');
    $items=$db->rows("SELECT ri.mahsulot_id, SUM(ri.soni) AS soni, m.nomi
                      FROM im_retsept_items ri JOIN im_mahsulotlar m ON m.id=ri.mahsulot_id
                      WHERE ri.retsept_id=$retsept_id GROUP BY ri.mahsulot_id, m.nomi ORDER BY ri.mahsulot_id");
    if (!$items) throw new Exception('Retsept bo‘sh');
    $runs=$qty/$base; $product=(int)$r['mahsulot_id'];
    $order=$order_item_id ? (int)$order_item_id : 'NULL';
    $note=mysqli_real_escape_string($link,mb_substr($izoh,0,500,'UTF-8'));
    // chiqish_soni records original served quantity; returned_servings tracks returns. No stock receipt.
    $id=$db->insert("INSERT INTO im_ishlab_chiqarish (retsept_id,tur,filial_id,soni,mahsulot_id,chiqish_soni,izoh,xodim_id,order_item_id) VALUES ($retsept_id,'ishlab_chiqarish',$filial_id,$runs,$product,$qty,'$note',$xodim_id,$order)");
    if (!$id) throw new Exception('Audit yozilmadi: '.$db->error());
    $total=0;
    foreach ($items as $it) {
        $need=round((float)$it['soni']*$runs,3);
        // 3 kasrdan kichik qolgan qismni sarflab bo'lmaydi (FIFO miqdor aniqligi).
        if ($need<=0) throw new Exception("«{$it['nomi']}» retseptdagi miqdori juda kichik "
            . "({$it['soni']} × {$runs}) — 0.001 dan kam chiqdi. Retseptda birlikni maydaroq qiling.");
        $take=im_fifo_take($db,$filial_id,(int)$it['mahsulot_id'],$need,'retsept',$id);
        $total+=$take['cost'];
        im_ishlab_audit_item($db,$id,'kirish',(int)$it['mahsulot_id'],$need,$take['unit_cost']);
    }
    im_ishlab_query($db,"UPDATE im_ishlab_chiqarish SET tannarx=$total WHERE id=$id");
    return $id;
}
// Returns only original audited ingredients, even after recipe changes/deletion.
// $retsept_id is retained for old callers; order_item_id is the authoritative link.
function im_retsept_xomashyo_qaytarish($db, $retsept_id, $qaytariladigan_soni, $filial_id, $xodim_id, $izoh = '', $order_item_id = null) {
    $qty=(float)$qaytariladigan_soni; $filial_id=(int)$filial_id; $order=(int)$order_item_id;
    if (!is_finite($qty) || $qty<=0 || !$order) throw new Exception('Qaytarish uchun original buyurtma qatori kerak');
    // Only 'bajarildi' rows are restorable; 'bekor' and 'isrof' (cancelled cooked order) are final.
    $audits=$db->rows("SELECT * FROM im_ishlab_chiqarish WHERE order_item_id=$order AND filial_id=$filial_id AND holat='bajarildi' AND chiqish_soni>returned_servings ORDER BY id DESC FOR UPDATE");
    // No linked FIFO audit means no provable ingredients to restore (legacy/qozon).
    if (!$audits) return null;
    $available=0; foreach ($audits as $a) $available+=(float)$a['chiqish_soni']-(float)$a['returned_servings'];
    if ($qty>$available+0.000001) throw new Exception('Original ishlab chiqarish miqdori yetarli emas');
    $last=null;
    foreach ($audits as $a) {
        if ($qty<=0.000001) break;
        $id=(int)$a['id']; $original=(float)$a['chiqish_soni'];
        $returned=(float)$a['returned_servings']; $served=$original-$returned;
        $back=min($qty,$served); $fraction=$back/$original;
        $items=$db->rows("SELECT mahsulot_id,soni,kelish_narxi FROM im_ishlab_chiqarish_items WHERE ishlab_id=$id AND tur='kirish' ORDER BY id");
        if (!$items) throw new Exception('Original xomashyo auditi topilmadi');
        // Final return restores exact rounding residue from the original layers.
        $final=$served-$back<=0.000001;
        $cost=$final ? im_fifo_reverse($db,'retsept',$id) : im_fifo_reverse_fraction($db,'retsept',$id,$fraction);
        foreach ($items as $it) {
            im_ishlab_audit_item($db,$id,'chiqish',(int)$it['mahsulot_id'],(float)$it['soni']*$fraction,(float)$it['kelish_narxi']);
        }
        $returned+=$back;
        $status=$final ? ", holat='bekor'" : '';
        im_ishlab_query($db,"UPDATE im_ishlab_chiqarish SET returned_servings=$returned $status WHERE id=$id");
        $qty-=$back; $last=$id;
    }
    return $last;
}
function im_alacarte_tannarx($db, $mahsulot_id, $filial_id) {
    $mahsulot_id=(int)$mahsulot_id; $filial_id=(int)$filial_id;
    $r=$db->row("SELECT id,chiqish_soni FROM im_retseptlar WHERE mahsulot_id=$mahsulot_id AND tur='ishlab_chiqarish' AND status=1 ORDER BY id DESC LIMIT 1");
    if (!$r) return 0.0;
    $base=(float)$r['chiqish_soni'];
    if ($base<=0) throw new Exception('Retsept chiqish miqdori noto‘g‘ri');
    $items=$db->rows("SELECT mahsulot_id,SUM(soni) AS soni FROM im_retsept_items WHERE retsept_id=".(int)$r['id']." GROUP BY mahsulot_id ORDER BY mahsulot_id");
    $total=0;
    foreach ($items as $it) {
        $preview=im_fifo_preview($db,$filial_id,(int)$it['mahsulot_id'],(float)$it['soni']/$base);
        $total+=$preview['cost'];
    }
    return round($total,4);
}
