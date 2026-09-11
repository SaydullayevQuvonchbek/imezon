<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin','sklad']);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new Cyber();

$mahsulot_id = (int)($_POST['mahsulot_id'] ?? 0);
// Kasrli miqdorlar (kg, litr) uchun (float) — ilgari (int) edi va
// masalan 2.5 kg jo'natilsa 2 kg ga aylanib qolardi.
$soni        = (float)($_POST['soni'] ?? 0);
$filial_id   = (int)($_POST['filial_id'] ?? 0);

if ($mahsulot_id <= 0 || !is_finite($soni) || $soni <= 0 || $filial_id <= 0) im_json('error', 'Filial, mahsulot va soni kiritish shart');

$mah = $db->row("SELECT id, nomi FROM im_mahsulotlar WHERE id=$mahsulot_id AND status=1");
if (!$mah) im_json('error', 'Mahsulot topilmadi');

$filial = $db->row("SELECT id, nomi FROM im_filiallar WHERE id=$filial_id AND status=1");
if (!$filial) im_json('error', 'Filial topilmadi yoki yopilgan');

// One transaction owns the take, allocation logs, legacy balances and receipt layers.
$db->begin();
try {
    // Reserve the operation identity; this placeholder never survives a failed take.
    $send_id = (int)$db->insert("INSERT INTO im_sklad_send
        (mahsulot_id, partiya_item_id, soni, xodim_id, filial_id)
        VALUES ($mahsulot_id, 0, 0, $im_user_id, $filial_id)");
    if (!$send_id) throw new RuntimeException("Jo'natish yozuvi yaratilmadi");

    // take + receive is used because transfer's total-only result cannot identify
    // individual source allocations for the existing shipment log.
    $taken = im_fifo_take($db, 0, $mahsulot_id, $soni, 'sklad_send', $send_id);
    if (empty($taken['allocations'])) throw new RuntimeException("FIFO ajratmalari topilmadi");
    $hasLayerColumn = (bool)$db->row("SHOW COLUMNS FROM im_sklad_send LIKE 'fifo_layer_id'");
    $allocated = 0.0;
    foreach ($taken['allocations'] as $index => $allocation) {
        $layer_id = (int)($allocation['layer_id'] ?? 0);
        $qty = (float)$allocation['qty'];
        $cost = (float)$allocation['unit_cost'];
        if ($layer_id <= 0 || !is_finite($qty) || $qty <= 0 || !is_finite($cost) || $cost < 0) {
            throw new RuntimeException("FIFO ajratmasi noto'g'ri");
        }
        // The FIFO API must expose receipt provenance, including an explicit null
        // for layers originating outside purchasing. Never guess a batch by product.
        if (!array_key_exists('partiya_item_id', $allocation)) {
            throw new RuntimeException("FIFO API: allocations.partiya_item_id kerak");
        }
        $item_id = (int)($allocation['partiya_item_id'] ?? 0);
        if (!$item_id && !$hasLayerColumn) {
            throw new RuntimeException("FIFO logi uchun im_sklad_send.fifo_layer_id ustuni kerak");
        }
        if ($item_id) {
            // FIFO is authoritative; keep only its corresponding legacy batch in sync.
            $db->q("UPDATE im_partiya_items
                SET sklad_qoldi=sklad_qoldi-$qty, dukon_qoldi=dukon_qoldi+$qty
                WHERE id=$item_id AND mahsulot_id=$mahsulot_id AND sklad_qoldi >= $qty");
            if ($db->affected() !== 1) {
                throw new RuntimeException("Partiya qoldig'i FIFO bilan mos emas");
            }
        }
        $layerSet = $hasLayerColumn ? ", fifo_layer_id=$layer_id" : '';
        if ($allocated == 0.0) {
            $log_id = $send_id;
            $db->q("UPDATE im_sklad_send SET partiya_item_id=$item_id, soni=$qty$layerSet WHERE id=$log_id");
        } else {
            $layerCol = $hasLayerColumn ? ', fifo_layer_id' : '';
            $layerValue = $hasLayerColumn ? ", $layer_id" : '';
            $log_id = (int)$db->insert("INSERT INTO im_sklad_send
                (mahsulot_id, partiya_item_id, soni, xodim_id, filial_id$layerCol)
                VALUES ($mahsulot_id, $item_id, $qty, $im_user_id, $filial_id$layerValue)");
        }
        im_fifo_receive($db, $filial_id, $mahsulot_id, $qty, $cost,
            'sklad_send', $log_id, $item_id ?: null);
        $allocated += $qty;
    }
    if (abs($allocated - $soni) > 0.000001) {
        throw new RuntimeException("FIFO ajratmalari miqdori mos emas");
    }

    $sotuv_n = (float)$db->val("SELECT COALESCE(
        (SELECT sotish_narxi FROM im_narxlar WHERE mahsulot_id=$mahsulot_id LIMIT 1),
        (SELECT pi.sotish_narxi FROM im_partiya_items pi
         JOIN im_partiyalar p ON p.id=pi.partiya_id
         WHERE pi.mahsulot_id=$mahsulot_id AND pi.sotish_narxi>0 AND p.holat='yopiq'
         ORDER BY pi.id DESC LIMIT 1), 0)");
    if ($sotuv_n > 0) {
        $db->q("UPDATE im_filial_qoldiq SET sotuv_narxi=$sotuv_n
            WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id");
    }
    $new_sklad = (float)im_fifo_balance($db, 0, $mahsulot_id)['qty'];
    $new_filial_q = (float)im_fifo_balance($db, $filial_id, $mahsulot_id)['qty'];
    $db->commit();

    im_json('ok', "{$mah['nomi']} — $soni dona «{$filial['nomi']}» ga jo'natildi!", [
        'sklad_q' => $new_sklad,
        'filial_q' => $new_filial_q,
        'filial_id' => $filial_id,
    ]);
} catch (Throwable $e) {
    $db->rollback();
    im_json('error', $e->getMessage());
}
