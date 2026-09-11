<?php
require_once __DIR__.'/../../ximoya.php';
require_once __DIR__.'/../../config.php';
require_once __DIR__.'/../ishlab_lib.php';
im_rol_check(['admin','kassir','sklad','oshpaz']);
$db=new Cyber(); $id=(int)($_POST['id']??0);
if (!$id) im_json('error','ID kiritilmadi');
$db->begin();
try {
    $ic=$db->row("SELECT * FROM im_ishlab_chiqarish WHERE id=$id FOR UPDATE");
    if (!$ic || $ic['holat']==='bekor') throw new Exception('Topilmadi yoki allaqachon bekor qilingan');
    if (!empty($ic['order_item_id'])) throw new Exception('Buyurtma ishlab chiqarishini buyurtma qatoridan kamaytiring');
    if (time()-strtotime($ic['sana'])>86400) throw new Exception('24 soatdan o‘tgan, bekor qilib bo‘lmaydi');
    if (($im_rol==='kassir' || $im_rol==='oshpaz') && (int)$ic['filial_id']!==(int)$im_filial_id) throw new Exception('Ruxsat yo‘q');
    if ($im_rol==='oshpaz' && $ic['tur']!=='maydalash') throw new Exception('Ruxsat yo‘q');
    // Fails for missing legacy receipts and any consumed output layer.
    im_fifo_cancel_receipt($db,'ishlab',$id);
    im_fifo_reverse($db,'ishlab',$id);
    im_ishlab_query($db,"UPDATE im_ishlab_chiqarish SET holat='bekor' WHERE id=$id");
    im_log('im_ishlab_chiqarish',$id,'update',['holat'=>$ic['holat']],['holat'=>'bekor'],'FIFO bekor qilindi');
    $db->commit(); im_json('ok','Bekor qilindi, original qoldiqlar qaytarildi');
} catch (Throwable $e) {
    $db->rollback(); im_json('error',$e->getMessage());
}
