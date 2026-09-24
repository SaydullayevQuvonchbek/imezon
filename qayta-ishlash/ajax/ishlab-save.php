<?php
require_once __DIR__.'/../../ximoya.php';
require_once __DIR__.'/../../config.php';
require_once __DIR__.'/../ishlab_lib.php';
im_rol_check(['admin','kassir','sklad','oshpaz']);
$db=new Cyber();
$retsept_id=(int)($_POST['retsept_id']??0);
$soni=(float)($_POST['soni']??1);
$location=(int)($_POST['filial_id']??0);
if ($im_rol==='kassir' || $im_rol==='oshpaz') $location=(int)$im_filial_id;
if (!$retsept_id || !is_finite($soni) || $soni<=0 || $location<0) im_json('error','Retsept va miqdor noto‘g‘ri');
$db->begin();
try {
    $r=$db->row("SELECT * FROM im_retseptlar WHERE id=$retsept_id AND status=1");
    if (!$r || !in_array($r['tur'],['ishlab_chiqarish','maydalash'],true)) throw new Exception('Retsept topilmadi');
    if ($im_rol==='oshpaz' && $r['tur']!=='maydalash') throw new Exception('Ruxsat yo‘q');
    $items=$db->rows("SELECT ri.*,m.birlik AS stock_unit FROM im_retsept_items ri JOIN im_mahsulotlar m ON m.id=ri.mahsulot_id WHERE ri.retsept_id=$retsept_id ORDER BY ri.mahsulot_id");
    if (!$items) throw new Exception('Retsept bo‘sh');
    $tur=$r['tur']; $product=(int)$r['mahsulot_id']; $amount=round((float)$r['chiqish_soni']*$soni,3);
    if ($amount<=0) throw new Exception('Chiqish miqdori noto‘g‘ri');
    $inputs=[]; $outputs=[];
    if ($tur==='ishlab_chiqarish') {
        foreach ($items as $it) {
            $mid=(int)$it['mahsulot_id']; $need=(float)$it['soni']*$soni;
            if (!is_finite($need) || $need<=0) throw new Exception('Xomashyo miqdori noto‘g‘ri');
            $inputs[$mid]=($inputs[$mid]??0)+$need;
        }
        $outputs[$product]=['qty'=>$amount,'weight'=>1];
    } else {
        $inputs[$product]=$amount;
        // Maydalash chiqishi brauzerdan erkin kiritilmaydi. Retseptning o'zi
        // konversiyani belgilaydi: masalan 1 Non butun -> 4 Non chorak.
        // Bu klient so'rovini o'zgartirib, asossiz qoldiq yaratishni ham yopadi.
        foreach ($items as $it) {
            $mid=(int)$it['mahsulot_id'];
            $qty=round((float)$it['soni']*$soni,3);
            if ($mid===$product) throw new Exception('Asosiy mahsulot chiqish mahsuloti bo‘la olmaydi');
            if (!is_finite($qty) || $qty<=0 || isset($outputs[$mid])) {
                throw new Exception('Maydalash retseptidagi chiqish noto‘g‘ri');
            }
            $outputs[$mid]=['qty'=>$qty,'weight'=>$qty];
        }
        if (!$outputs) throw new Exception('Kamida bitta musbat chiqish kerak');
    }
    $note=mysqli_real_escape_string($link,mb_substr(trim($_POST['izoh']??''),0,500,'UTF-8'));
    $fil=$location ?: 'NULL';
    $id=$db->insert("INSERT INTO im_ishlab_chiqarish (retsept_id,tur,filial_id,soni,mahsulot_id,chiqish_soni,izoh,xodim_id) VALUES ($retsept_id,'$tur',$fil,$soni,$product,$amount,'$note',$im_user_id)");
    if (!$id) throw new Exception('Jarayon yozilmadi: '.$db->error());
    // Qulflar OLDINDAN, mahsulot id o'sishi bo'yicha: pastda avval barcha
    // kirishlar (im_fifo_take), keyin barcha chiqishlar (im_fifo_receive)
    // qulflanardi — kirish id'si chiqish id'sidan katta bo'lsa tartib buzilib,
    // parallel amallar bilan deadlock chiqishi mumkin edi.
    $lock_ids = array_map('intval', array_merge(array_keys($inputs), array_keys($outputs)));
    $lock_ids = array_values(array_unique($lock_ids));
    sort($lock_ids, SORT_NUMERIC);
    foreach ($lock_ids as $lid) im_fifo_lock($db, $location, $lid);

    $total=0;
    foreach ($inputs as $mid=>$need) {
        $need=round($need,3);
        $take=im_fifo_take($db,$location,$mid,$need,'ishlab',$id);
        $total+=$take['cost'];
        im_ishlab_audit_item($db,$id,'kirish',$mid,$need,$take['unit_cost']);
    }
    $weights=array_sum(array_column($outputs,'weight')); $allocated=0; $index=0;
    foreach ($outputs as $mid=>$out) {
        $cost=(++$index===count($outputs)) ? $total-$allocated : $total*$out['weight']/$weights;
        $unit=$cost/$out['qty']; $allocated+=$cost;
        im_fifo_receive($db,$location,$mid,$out['qty'],$unit,'ishlab',$id);
        im_ishlab_audit_item($db,$id,'chiqish',$mid,$out['qty'],$unit);
    }
    $tannarx=$tur==='ishlab_chiqarish' ? $total/$amount : $total;
    im_ishlab_query($db,"UPDATE im_ishlab_chiqarish SET tannarx=$tannarx WHERE id=$id");
    im_log('im_ishlab_chiqarish',$id,'insert',null,['tur'=>$tur,'retsept_id'=>$retsept_id,'soni'=>$soni],'FIFO ishlab chiqarish');
    $db->commit();
    im_json('ok','Jarayon muvaffaqiyatli bajarildi',['id'=>$id]);
} catch (Throwable $e) {
    $db->rollback(); im_json('error',$e->getMessage());
}
