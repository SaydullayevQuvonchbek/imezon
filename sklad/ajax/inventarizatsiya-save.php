<?php
// ============================================================
//  IMezon — INVENTARIZATSIYA (sanoq) ni qo'llash
//
//  FIFO daftarini real (jismoniy) qoldiq bilan moslashtirishning
//  YAGONA rasmiy yo'li. Qoldiqni qo'lda UPDATE qilish taqiqlangan —
//  farq har doim FIFO qatlami orqali yoziladi:
//    ortiqcha  → im_fifo_receive(..., 'korrektirovka', inv_id)
//    kamomad   → im_fifo_take(...,   'korrektirovka', inv_id)
//
//  Solishtirish JISMONIY qoldiq bilan (im_fifo_layers.remaining_qty),
//  im_filial_qoldiq.soni bilan EMAS: oxirgisi "mavjud" (jismoniy minus
//  band qilingan) va sanoqchi javonda band mahsulotni ham ko'radi.
//
//  POST: location_id, izoh, items = JSON [{mahsulot_id, real_soni}]
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

// DIQQAT: bu yerda mysqli_report(STRICT) ATAYLAB YOQILMAGAN — u mysqli_query
// darajasida exception otadi va Cyber::q() ichidagi 1213/1205 (deadlock /
// lock timeout) uchun tushunarli xabarga aylantirish ishlamay qoladi.
$db = new Cyber();

$location_id = (int)($_POST['location_id'] ?? -1);
$izoh        = trim((string)($_POST['izoh'] ?? ''));
$items_raw   = json_decode($_POST['items'] ?? '[]', true);

if ($location_id < 0)      im_json('error', 'Joy (ombor/filial) tanlanmadi');
if (!is_array($items_raw) || !$items_raw) im_json('error', 'Kamida bitta mahsulot kerak');
if ($location_id > 0 && !$db->val("SELECT id FROM im_filiallar WHERE id=$location_id AND status=1")) {
    im_json('error', 'Filial topilmadi yoki yopilgan');
}

// Mahsulot bo'yicha yig'ish + QULF TARTIBI: id o'sish bo'yicha (loyihaning
// yagona qoidasi — aks holda checkout bilan deadlock).
$sanoq = [];
foreach ($items_raw as $it) {
    $mid  = (int)($it['mahsulot_id'] ?? 0);
    $real = round((float)($it['real_soni'] ?? -1), 3);
    if ($mid <= 0) continue;
    if (!is_finite($real) || $real < 0) im_json('error', "Mahsulot #$mid uchun real miqdor noto'g'ri");
    $sanoq[$mid] = $real;   // takrorlansa oxirgisi qoladi
}
if (!$sanoq) im_json('error', 'Kamida bitta mahsulot kerak');
ksort($sanoq, SORT_NUMERIC);   // QULF TARTIBI: mahsulot id o'sishi bo'yicha

$izoh_s = mysqli_real_escape_string($link, mb_substr($izoh, 0, 500, 'UTF-8'));

$db->begin();
try {
    // ── Nomzodlarni ajratish (qulfsiz, TRANZAKSIYA ICHIDA) ─────
    // Har qulflangan mahsulot tranzaksiya oxirigacha 2 ta qator qulfini ushlaydi
    // (im_filial_qoldiq + im_fifo_locks). 300 qatorli sanoq 600 qulf demak — shu
    // paytda kassada chek yopilmay qoladi. Shuning uchun FAQAT farqi bor
    // mahsulotlar qulflanadi; qolganlari uchun hech qanday FIFO amali yo'q.
    // MUHIM: bu o'qish tranzaksiya ichida — pastdagi "farqsiz" qatorlar ham
    // AYNAN shu snapshotdan o'qiladi, shuning uchun varaqadagi farq va
    // qo'llangan o'zgarishlar bir-biriga mos bo'ladi.
    $nomzod = [];
    foreach ($sanoq as $mid => $real) {
        $taxmin = (float)$db->val("SELECT COALESCE(SUM(remaining_qty),0) FROM im_fifo_layers
                                   WHERE location_id=$location_id AND mahsulot_id=$mid AND cancelled=0");
        if (abs(round($real - $taxmin, 3)) > 0.0005) $nomzod[$mid] = true;
    }
    if (count($nomzod) > 120) {
        throw new RuntimeException('Bir sanoqda farqli mahsulotlar soni 120 tadan oshmasligi kerak '
            . '(hozir: ' . count($nomzod) . "). Sanoqni bir necha qismga bo'lib yuboring — "
            . 'aks holda kassa uzoq vaqt kutib qoladi.');
    }

    $inv_id = (int)$db->insert(
        "INSERT INTO im_inventarizatsiya (location_id, xodim_id, izoh)
         VALUES ($location_id, $im_user_id, " . ($izoh_s !== '' ? "'$izoh_s'" : 'NULL') . ")"
    );
    if (!$inv_id) throw new RuntimeException('Sanoq yozuvi yaratilmadi');

    $ortiqcha = 0.0;
    $kamomad  = 0.0;
    $ozgargan = 0;

    foreach ($sanoq as $mid => $real) {
        $nomi = (string)$db->val("SELECT nomi FROM im_mahsulotlar WHERE id=$mid");
        if ($nomi === '') throw new RuntimeException("Mahsulot #$mid topilmadi");

        if (empty($nomzod[$mid])) {
            // Farqsiz qator — qulflamaymiz, hech narsa o'zgartirmaymiz.
            $hisob = round((float)im_fifo_balance($db, $location_id, $mid)['qty'], 3);
            $db->q("INSERT INTO im_inventarizatsiya_items
                        (inv_id, mahsulot_id, hisob_soni, real_soni, farq, birlik_narx, summa)
                    VALUES ($inv_id, $mid, " . im_fifo_number($hisob) . ", " . im_fifo_number($real) . ",
                            " . im_fifo_number(round($real - $hisob, 3)) . ", 0, 0)");
            continue;
        }

        im_fifo_lock($db, $location_id, $mid);
        // QULFLAB o'qish SHART: REPEATABLE READ da oddiy SELECT tranzaksiyaning
        // eski snapshotini qaytaradi (qulf olingandan keyin ham). Eski qiymat
        // bilan hisoblangan farq mahsulotni kerakidan ortiq hisobdan chiqarardi.
        $balans = im_fifo_balance($db, $location_id, $mid, true);
        $hisob  = round((float)$balans['qty'], 3);
        $farq   = round($real - $hisob, 3);

        $birlik_narx = (float)$balans['unit_cost'];
        if ($birlik_narx <= 0) $birlik_narx = im_fifo_last_cost($db, $mid);
        $summa = 0.0;

        if ($farq > 0.0005 && $birlik_narx <= 0) {
            // Tannarxsiz "ortiqcha" tekin qoldiq yaratadi: keyin u COGS=0 bilan
            // sotilib, foydani soxta ko'taradi. Taxmin qilmaymiz — rad etamiz.
            throw new RuntimeException("«{$nomi}»: tannarxi noma'lum (bu mahsulot hech qachon "
                . "qabul qilinmagan). Ortiqchani sanoq orqali kiritib bo'lmaydi — avval "
                . "kirim partiyasi bilan qabul qiling.");
        }

        if ($farq > 0.0005) {
            // Ortiqcha: yangi qatlam. Narx — joriy o'rtacha FIFO narxi
            // (qatlam qolmagan bo'lsa oxirgi ma'lum narx).
            im_fifo_receive($db, $location_id, $mid, $farq, $birlik_narx, 'korrektirovka', $inv_id);
            $summa = round($farq * $birlik_narx, 2);
            $ortiqcha += $summa;
            $ozgargan++;
        } elseif ($farq < -0.0005) {
            // Kamomad: FIFO tartibida yechiladi (eng eski qatlamdan).
            try {
                $take = im_fifo_take($db, $location_id, $mid, -$farq, 'korrektirovka', $inv_id);
            } catch (Throwable $e) {
                throw new RuntimeException("«{$nomi}»: kamomadni yozib bo'lmadi — " . $e->getMessage()
                    . ". Mahsulot ochiq buyurtmaga band bo'lishi mumkin; avval o'sha buyurtmalarni yoping.");
            }
            $birlik_narx = (float)$take['unit_cost'];
            $summa = -round((float)$take['total'], 2);
            $kamomad += round((float)$take['total'], 2);
            $ozgargan++;
        }

        $db->q("INSERT INTO im_inventarizatsiya_items
                    (inv_id, mahsulot_id, hisob_soni, real_soni, farq, birlik_narx, summa)
                VALUES ($inv_id, $mid, " . im_fifo_number($hisob) . ", " . im_fifo_number($real) . ",
                        " . im_fifo_number($farq) . ", " . im_fifo_number($birlik_narx, 6) . ",
                        " . number_format($summa, 2, '.', '') . ")");
    }

    $db->q("UPDATE im_inventarizatsiya
            SET ortiqcha_summa=" . number_format($ortiqcha, 2, '.', '') . ",
                kamomad_summa=" . number_format($kamomad, 2, '.', '') . "
            WHERE id=$inv_id");

    $db->commit();

    $joy = $location_id === 0 ? 'Ombor'
         : ((string)$db->val("SELECT nomi FROM im_filiallar WHERE id=$location_id") ?: "Filial #$location_id");
    im_log('im_inventarizatsiya', $inv_id, 'insert', null,
        ['joy' => $joy, 'qatorlar' => count($sanoq), 'ortiqcha' => $ortiqcha, 'kamomad' => $kamomad],
        "Inventarizatsiya — {$joy}: " . count($sanoq) . " qator, {$ozgargan} tasida farq");

    im_json('ok', "Sanoq saqlandi — {$ozgargan} ta mahsulotda farq topildi. "
        . "Ortiqcha: " . im_money($ortiqcha) . " so'm, kamomad: " . im_money($kamomad) . " so'm.", [
        'id'       => $inv_id,
        'ortiqcha' => $ortiqcha,
        'kamomad'  => $kamomad,
        'ozgargan' => $ozgargan,
    ]);
} catch (Throwable $e) {
    $db->rollback();
    im_json('error', $e->getMessage());
}
