<?php
// ============================================================
//  IMezon — Partiyani Yopish AJAX
//  Partiya HAR DOIM to'liq NASIYA (qarz) sifatida yopiladi.
//
//  Nega: sklad (va odatda admin ham) real naqd/karta/bank kassasiga
//  ega emas — avval bu yerda "naqd/karta/bank to'landi" deb belgilash
//  mumkin edi, lekin bu faqat YORLIQ edi: hech qanday im_kassa yoki
//  im_balans yozuvi yaratilmasdi. Natijada hisobotda "to'landi"
//  ko'rinar, lekin real pul hech qayerdan chiqmagan bo'lardi.
//
//  Endi: partiya yopilganda doim to'liq summaga im_postavshik_qarz
//  yaratiladi (filialga bog'langan holda). Haqiqiy to'lov FAQAT
//  quyidagi real-kassa ekranlaridan amalga oshiriladi:
//    - dukon/qarzlar.php        (filialning o'z kassasidan)
//    - admin/qarzlar.php        (markaz/Yagona kassadan)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new Cyber();

$partiya_id = (int)($_POST['partiya_id'] ?? 0);
$muddat     = trim($_POST['muddat'] ?? '');
$izoh       = trim($_POST['izoh'] ?? '');

if (!$partiya_id) im_json('error', "Partiya IDsi kerak");

$izoh_s   = mysqli_real_escape_string($link, mb_substr($izoh, 0, 500, 'UTF-8'));
$muddat_s = ($muddat && preg_match('/^\d{4}-\d{2}-\d{2}$/', $muddat)) ? "'$muddat'" : 'NULL';

$db->begin();
try {
    $partiya = $db->row("SELECT * FROM im_partiyalar WHERE id=$partiya_id FOR UPDATE");
    if (!$partiya || $partiya['holat'] !== 'ochiq') {
        throw new RuntimeException("Partiya topilmadi yoki allaqachon yopilgan");
    }
    $items = $db->rows("SELECT * FROM im_partiya_items WHERE partiya_id=$partiya_id ORDER BY mahsulot_id, id FOR UPDATE");
    $items_soni = count($items);
    if (!$items_soni) throw new RuntimeException("Partiyaga hech qanday mahsulot qo'shilmagan");
    $jami_summa = 0.0;
    foreach ($items as $it) {
        $qty = (float)$it['soni'];
        $cost = (float)$it['kelish_narxi'];
        if (!is_finite($qty) || $qty <= 0 || !is_finite($cost) || $cost < 0) {
            throw new RuntimeException("Partiya miqdori yoki kelish narxi noto'g'ri");
        }
        $jami_summa += $qty * $cost;
    }
    if (!is_finite($jami_summa)) throw new RuntimeException("Partiya summasi noto'g'ri");
    $tolandi = 0;
    $qarz_qoldi = max(0, $jami_summa);
    $q_filial = (int)($partiya['qabul_filial_id'] ?? 0);
    if ($q_filial < 0 || ($q_filial > 0 && !$db->val("SELECT id FROM im_filiallar WHERE id=$q_filial AND status=1"))) {
        throw new RuntimeException("Qabul filiali topilmadi yoki yopilgan");
    }

    // Partiyani yopish
    $db->q("UPDATE im_partiyalar SET
                holat      = 'yopiq',
                jami_summa = $jami_summa,
                tolov_turi = 'qarz',
                usd_summa  = 0,
                usd_kurs   = 0,
                tolandi    = $tolandi,
                qarz_qoldi = $qarz_qoldi,
                izoh       = '$izoh_s'
            WHERE id=$partiya_id");

    // Qarz yaratish (filialga bog'langan holda — im_partiyalar.qabul_filial_id orqali)
    if ($qarz_qoldi > 0) {
        $ps_id = (int)$partiya['postavshik_id'];
        $db->q("INSERT INTO im_postavshik_qarz
                    (postavshik_id, partiya_id, qarz_summa, tolandi, qoldiq, muddat, status)
                VALUES
                    ($ps_id, $partiya_id, $jami_summa, $tolandi, $qarz_qoldi, $muddat_s, 'ochiq')");
    }

    // Receipt identity is shared by all document lines; each line has its own layer.
    foreach ($items as $it) {
        $itm_id = (int)$it['id'];
        $mah_id = (int)$it['mahsulot_id'];
        $qty = (float)$it['soni'];
        $cost = (float)$it['kelish_narxi'];
        im_fifo_receive($db, $q_filial, $mah_id, $qty, $cost, 'partiya', $partiya_id, $itm_id);
        $warehouseQty = $q_filial === 0 ? $qty : 0;
        $branchQty = $q_filial > 0 ? $qty : 0;
        $db->q("UPDATE im_partiya_items SET sklad_qoldi=$warehouseQty, dukon_qoldi=$branchQty WHERE id=$itm_id");
        if ($q_filial > 0) {
            // FIFO receive owns quantity/cost aggregation. Only selling price is legacy.
            $sotuv_n = (float)$db->val("SELECT COALESCE(
                (SELECT sotish_narxi FROM im_narxlar WHERE mahsulot_id=$mah_id LIMIT 1),
                (SELECT sotish_narxi FROM im_partiya_items WHERE id=$itm_id AND sotish_narxi>0), 0)");
            if ($sotuv_n > 0) {
                $db->q("UPDATE im_filial_qoldiq SET sotuv_narxi=$sotuv_n WHERE filial_id=$q_filial AND mahsulot_id=$mah_id");
            }
            $db->q("INSERT INTO im_sklad_send (mahsulot_id, partiya_item_id, soni, xodim_id, filial_id)
                VALUES ($mah_id, $itm_id, $qty, $im_user_id, $q_filial)");
        }
    }

    $db->commit();

    // Istoriyaga yozish — partiya yopildi
    im_log('im_partiyalar', $partiya_id, 'update',
        ['holat' => 'ochiq'],
        ['holat' => 'yopiq', 'jami_summa' => $jami_summa, 'qarz_qoldi' => $qarz_qoldi, 'items' => $items_soni],
        "Partiya yopildi — {$items_soni} ta mahsulot, nasiyaga: " . im_money($qarz_qoldi) . " so'm"
    );
    if ($qarz_qoldi > 0) {
        im_log('im_postavshik_qarz', 0, 'insert', null,
            ['partiya_id' => $partiya_id, 'qarz' => $qarz_qoldi, 'filial_id' => $q_filial],
            "Partiya #{$partiya_id} uchun qarz yozuvi yaratildi (Filial #$q_filial)"
        );
    }

    im_json('ok', "Partiya muvaffaqiyatli yopildi! {$items_soni} ta mahsulot sklad qoldiqlariga qo'shildi. "
        . ($qarz_qoldi > 0 ? "Postavshikka " . im_money($qarz_qoldi) . " so'm qarz yozildi — to'lovni \"Postavshik qarzlari\" bo'limidan amalga oshiring." : ''), [
        'jami' => $jami_summa,
        'items' => $items_soni,
        'qarz' => $qarz_qoldi,
    ]);

} catch (Throwable $e) {
    $db->rollback();
    im_json('error', "Xatolik: " . $e->getMessage());
}
