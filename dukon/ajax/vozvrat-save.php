<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir']);
$db = new Cyber();

$sotuv_id = (int)($_POST['sotuv_id'] ?? 0);
$sotuv_item_id = (int)($_POST['sotuv_item_id'] ?? 0);
$mah_id = (int)($_POST['mahsulot_id'] ?? 0);
$soni = round((float)($_POST['soni'] ?? 0), 3);
$summa = round((float)($_POST['qaytarish_summa'] ?? 0), 2);
$tolov = (string)($_POST['qaytarildi_tur'] ?? 'naqd');
$sabab_raw = trim((string)($_POST['sabab'] ?? ''));
$im_filial_id = (int)$im_filial_id;
if ($im_filial_id <= 0 || !$sotuv_item_id || !is_finite($soni) || !is_finite($summa) || $soni <= 0 || $summa <= 0) {
    im_json('error', 'Aniq sotuv qatori va musbat miqdor/summa kerak');
}
if (!in_array($tolov, ['naqd', 'karta', 'bank'], true)) im_json('error', 'Qaytarish to‘lov turi noto‘g‘ri');

// ── Nasiya va naqd hisoblash ──────────────────────────────────────
// $qarzdan_uziladigan — mijozning qarz hisobiga yopiladigan summa (kassa ta'sir qilmaydi)
// $naqd_qaytariladigan — kassadan chiqadigan haqiqiy pul
$qarzdan_uziladigan  = 0.0;
$naqd_qaytariladigan = $summa; // default: hammasi naqd qaytariladi (nasiya bo'lmasa)

$db->begin();
try {
    if (!function_exists('im_fifo_reverse')) throw new Exception('FIFO engine yuklanmagan');
    // Match checkout lock order: branch stock, sale header, exact sale item, layers.
    $db->rows("SELECT mahsulot_id FROM im_filial_qoldiq WHERE filial_id=$im_filial_id ORDER BY mahsulot_id FOR UPDATE");
    $candidate = $db->row("SELECT sotuv_id FROM im_sotuv_items WHERE id=$sotuv_item_id");
    if (!$candidate) throw new Exception('Sotuv qatori topilmadi');
    $actual_sale_id = (int)$candidate['sotuv_id'];
    if ($sotuv_id > 0 && $sotuv_id !== $actual_sale_id) throw new Exception('Chek va qator mos emas');
    $sotuv_id = $actual_sale_id;
    $sale = $db->row("SELECT * FROM im_sotuvlar WHERE id=$sotuv_id AND filial_id=$im_filial_id FOR UPDATE");
    if (!$sale) throw new Exception('Ushbu filialda sotuv topilmadi');
    $line = $db->row("SELECT * FROM im_sotuv_items WHERE id=$sotuv_item_id AND sotuv_id=$sotuv_id FOR UPDATE");
    if (!$line || ($mah_id > 0 && $mah_id !== (int)$line['mahsulot_id'])) throw new Exception('Mahsulot va sotuv qatori mos emas');
    $mah_id = (int)$line['mahsulot_id'];
    $mode = $line['fifo_return_mode'] ?? null;
    if (!in_array($mode, ['stock', 'waste'], true)) {
        throw new Exception('Eski sotuv: qaytarish turi va asl FIFO manbasini tekshirish kerak');
    }
    // Unbound legacy returns cannot safely be apportioned between repeated/set lines.
    $legacy = $db->row("SELECT id FROM im_vozvratlar WHERE sotuv_id=$sotuv_id AND mahsulot_id=$mah_id
        AND (sotuv_item_id IS NULL OR sotuv_item_id=0) LIMIT 1 FOR UPDATE");
    if ($legacy) throw new Exception('Avvalgi qaytarishni aniq sotuv qatoriga bog‘lash kerak');
    $previous = $db->rows("SELECT soni, qaytarish_summa FROM im_vozvratlar WHERE sotuv_item_id=$sotuv_item_id FOR UPDATE");
    $returned = 0.0;
    $refunded_line = 0.0;
    foreach ($previous as $r) {
        $returned += (float)$r['soni'];
        $refunded_line += (float)$r['qaytarish_summa'];
    }
    $remaining = round((float)$line['soni'] - $returned, 3);
    if ($soni > $remaining + 0.000001) throw new Exception("Qaytarish miqdori oshib ketdi. Maksimum: $remaining");
    // Refunds may be reduced by the cashier, but cannot exceed this line or receipt.
    $line_value = (float)$line['chegirma_narxi'] * (float)$line['soni'];
    $max_refund = min((float)$line['chegirma_narxi'] * $soni, $line_value - $refunded_line);
    $all_returns = $db->rows("SELECT qaytarish_summa FROM im_vozvratlar WHERE sotuv_id=$sotuv_id FOR UPDATE");
    $receipt_refunded = 0.0;
    foreach ($all_returns as $r) $receipt_refunded += (float)$r['qaytarish_summa'];
    $max_refund = min($max_refund, (float)$sale['tolov_summa'] - $receipt_refunded);
    if ($summa > round($max_refund, 2) + 0.000001) throw new Exception('Qaytarish summasi sotuv qiymatidan oshdi');

    $is_waste = $mode === 'waste';
    $sale_cost_sql = im_fifo_sale_unit_cost_sql('cost_si');
    $effective_unit_cost = (float)$db->val("SELECT $sale_cost_sql
        FROM im_sotuv_items cost_si WHERE cost_si.id=$sotuv_item_id");
    // tannarx is TOTAL returned cost (unit snapshot = tannarx / soni).
    // Cooked food remains consumed; its refund is waste, never raw-stock manufacture.
    $cost_total = $is_waste
        ? $effective_unit_cost * $soni
        : im_fifo_reverse($db, 'sotuv', $sotuv_item_id, $soni);
    if (!is_numeric($cost_total) || !is_finite((float)$cost_total) || $cost_total < 0) {
        throw new Exception('FIFO reverse tannarxi noto‘g‘ri');
    }
    $cost_sql = number_format((float)$cost_total, 6, '.', '');
    $sabab = mysqli_real_escape_string($link, mb_substr(($is_waste ? '[waste] ' : '[stock] ') . $sabab_raw, 0, 200, 'UTF-8'));
    $id = $db->insert(
        "INSERT INTO im_vozvratlar (sotuv_id, sotuv_item_id, mahsulot_id, soni, tannarx, sabab, qaytarildi_tur, qaytarish_summa, kassir_id)
         VALUES ($sotuv_id, $sotuv_item_id, $mah_id, $soni, $cost_sql, '$sabab', '$tolov', $summa, $im_user_id)"
    );
    if (!$id) throw new Exception('Qaytarish yozuvi saqlanmadi');

    // ── Nasiya logikasi: agar sotuv nasiya bilan bo'lgan bo'lsa ──
    if ($sotuv_id) {
        $nasiya = $db->row("SELECT * FROM im_nasiya WHERE sotuv_id=$sotuv_id AND holat IN ('aktiv','muddati_otdi') LIMIT 1 FOR UPDATE");

        if ($nasiya) {
            // Qarzdan qancha uzish mumkin (qolib qolgan qarzdan ortiq bo'lolmaydi)
            $qarzdan_uziladigan = min($summa, (float)$nasiya['qoldiq']);

            if ($qarzdan_uziladigan > 0) {
                $yangi_qoldiq = max(0, (float)$nasiya['qoldiq'] - $qarzdan_uziladigan);
                $yangi_holat  = $yangi_qoldiq <= 0.01 ? 'yopildi' : $nasiya['holat'];

                // Nasiya qoldiqini yangilash
                $db->q("UPDATE im_nasiya
                        SET qoldiq=$yangi_qoldiq, holat='$yangi_holat'
                        WHERE id={$nasiya['id']}");

                // Mijoz umumiy qarzini kamaytirish
                $db->q("UPDATE im_mijozlar
                        SET nasiya_qoldiq=GREATEST(0, nasiya_qoldiq-$qarzdan_uziladigan)
                        WHERE id={$nasiya['mijoz_id']}");

                // Nasiya to'lovlari tarixiga vozvrat orqali yopilganligi yoziladi
                $izoh_vozvrat = "Mahsulot qaytarilishi (Vozvrat #{$id}) hisobidan";
                $db->q("INSERT INTO im_nasiya_tolovlar (nasiya_id, summa, tolov_turi, izoh, xodim_id)
                        VALUES ({$nasiya['id']}, $qarzdan_uziladigan, 'naqd', '$izoh_vozvrat', $im_user_id)");
            }

            // Naqd qaytariladigan summa = umumiy summadan qarzga ketgan qismni ayirish
            $naqd_qaytariladigan = max(0, $summa - $qarzdan_uziladigan);
        }

        // Agar barcha mahsulotlar qaytarilgan bo'lsa — sotuvni "qaytarilgan" qil
        $jami_sotilgan    = (float)$db->val("SELECT COALESCE(SUM(soni),0) FROM im_sotuv_items WHERE sotuv_id=$sotuv_id");
        $jami_qaytarilgan = (float)$db->val("SELECT COALESCE(SUM(soni),0) FROM im_vozvratlar WHERE sotuv_id=$sotuv_id");
        if ($jami_qaytarilgan >= $jami_sotilgan) {
            $db->q("UPDATE im_sotuvlar SET holat='qaytarilgan' WHERE id=$sotuv_id");
        }
    }

    // ── Balans logi va Kassa chiqimi ──────────────────────────────────
    // Kassadan FAQAT haqiqiy naqd/karta qaytarilgan qism chiqim qilinadi.
    // Qarzdan uzilgan qisim kassadan chiqmaydi (u faqat nasiya qoldiqdan ayirildi).
    if ($naqd_qaytariladigan > 0) {
        // Kassadan yechib qolish (Tolov turiga mos qilib)
        if ($tolov === 'karta') $col = 'karta_balans';
        elseif ($tolov === 'bank') $col = 'bank_balans';
        elseif ($tolov === 'usd') $col = 'usd_balans';
        else $col = 'naqd_balans';

        // Filial kassasi mavjudligini kafolatlash — bo'lmasa yaratamiz (bo'lmasa UPDATE
        // sukut saqlab 0 qatorga ta'sir qiladi va mijozga qaytarilgan pul kassa balansidan
        // ayirilmay qoladi, natijada kassa haqiqiy qoldiqdan ko'p ko'rsatadi).
        $filial_kassa = $db->row("SELECT id FROM im_kassa WHERE filial_id=$im_filial_id LIMIT 1");
        if (!$filial_kassa) {
            $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans) VALUES ($im_filial_id, 0, 0, 0, 0)");
        }
        // Filial kassadan yechish
        $db->q("UPDATE im_kassa SET $col = $col - $naqd_qaytariladigan WHERE filial_id=$im_filial_id");
        if ($db->affected() < 1) {
            error_log("[IMezon] FILIAL KASSA YANGILANMADI (vozvrat)! vozvrat_id=$id filial_id=$im_filial_id tolov=$tolov summa=$naqd_qaytariladigan");
        }

        $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, manba_id, manba_tur, filial_id, xodim_id)
                VALUES ('chiqim', 'vozvrat_chiqim', $naqd_qaytariladigan, $id, 'vozvrat', $im_filial_id, $im_user_id)");
    }

    $db->commit();

    // ── Javob xabari ─────────────────────────────────────────────
    $xabar_qismlar = [$is_waste ? "$soni dona taom qaytarildi: chiqindi (waste), xomashyo tiklanmadi." : "$soni dona mahsulot asl FIFO qatlamlariga qaytarildi."];
    if ($qarzdan_uziladigan > 0) {
        $xabar_qismlar[] = number_format($qarzdan_uziladigan, 0, '.', ' ') . " so'm nasiya qarzidan chegirildi.";
    }
    if ($naqd_qaytariladigan > 0) {
        $xabar_qismlar[] = number_format($naqd_qaytariladigan, 0, '.', ' ') . " so'm kassadan mijozga qaytarildi.";
    }

    im_json('ok', implode(' ', $xabar_qismlar), ['id' => $id, 'sotuv_item_id' => $sotuv_item_id, 'classification' => $mode, 'tannarx' => (float)$cost_total, 'unit_cost' => (float)$cost_total / $soni]);

} catch (Throwable $e) {
    $db->rollback();
    im_json('error', $e->getMessage());
}
