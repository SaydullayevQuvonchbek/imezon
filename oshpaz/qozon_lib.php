<?php
// ============================================================
//  IMezon — Osh qozoni / kunlik zagotovka: ESLATMALAR
//
//  qozon_rejim=1 mahsulot (osh, shashlik...) uchun xomashyo qozon/partiya
//  ochilganda bir marta yechiladi. Oshpaz yangi qozon quyib, uni yozishni
//  UNUTSA — sotuv davom etadi-yu, xomashyo hisobdan chiqmaydi. Bu funksiya
//  shu holatni aniqlab, oshpaz/kassir ekraniga eslatma qaytaradi.
//
//  Chaqiruvchilar: oshpaz/ajax/get-orders.php, oshpaz/ajax/qozon-holat.php
// ============================================================

/**
 * @return array<int,array{daraja:string,mahsulot:string,matn:string}>
 *         daraja: 'xato' | 'ogoh' | 'info'
 */
function im_qozon_eslatma($db, $filial_id) {
    $filial_id = (int)$filial_id;
    if ($filial_id <= 0) return [];

    $bugun = date('Y-m-d');
    $out   = [];

    $mahsulotlar = $db->rows(
        "SELECT id, nomi FROM im_mahsulotlar WHERE qozon_rejim = 1 AND status = 1"
    );

    foreach ($mahsulotlar as $m) {
        $mid  = (int)$m['id'];
        $nomi = (string)$m['nomi'];

        // Bugungi partiyalar (shu filial)
        $p = $db->row(
            "SELECT
                SUM(holat='ochiq')                              AS ochiq,
                SUM(holat='yopildi')                            AS yopilgan,
                COALESCE(SUM(CASE WHEN holat='ochiq' THEN moljal_porsiya END), 0) AS moljal
             FROM im_osh_qozon
             WHERE filial_id = $filial_id AND mahsulot_id = $mid AND sana = '$bugun'"
        );
        $ochiq    = (int)($p['ochiq']    ?? 0);
        $yopilgan = (int)($p['yopilgan'] ?? 0);
        $moljal   = (float)($p['moljal'] ?? 0);

        // Hali sotilmagan talab. Allaqachon sotilgan porsiyalarni bu yerga
        // qayta qo'shmaymiz: ular qozon FIFO qatlamidan yechilgan va yopilgan
        // qozonning haqiqiy chiqishida allaqachon hisobga olingan.
        $navbat = (float)$db->val(
            "SELECT COALESCE(SUM(i.soni), 0)
             FROM im_sotuvchi_order_item i
             JOIN im_sotuvchi_order o ON o.id = i.order_id
             WHERE o.filial_id = $filial_id AND i.mahsulot_id = $mid
                AND DATE(o.created_at) = '$bugun'
                AND o.status IN ('oshpazda', 'pishirilmoqda', 'stol_band')"
        );

        // Yopilgan qozondan qolgan, isrof qilinmagan tayyor porsiya ham yangi
        // buyurtmani qoplay oladi. Faqat undan ortgan qism yangi qozon talabi.
        $tayyor_qoldiq = (float)$db->val(
            "SELECT COALESCE(SUM(remaining_qty),0) FROM im_fifo_layers
             WHERE location_id=$filial_id AND mahsulot_id=$mid
               AND source='qozon' AND cancelled=0 AND remaining_qty>0"
        );

        // Bugun bu mahsulotga umuman tegishli narsa yo'q — jim
        if ($navbat <= 0 && $ochiq === 0 && $yopilgan === 0) continue;

        $t = fn($x) => rtrim(rtrim(number_format($x, 3, '.', ' '), '0'), '.');

        if ($ochiq === 0) {
            $yangi_talab = max(0, $navbat - $tayyor_qoldiq);
            if ($yangi_talab <= 0) continue;
            if ($yopilgan > 0) {
                $out[] = [
                    'daraja'   => 'xato',
                    'mahsulot' => $nomi,
                    'matn'     => "«{$nomi}» — bugungi partiya YOPILGAN, tayyor qoldiqdan tashqari yana {$t($yangi_talab)} porsiya yetishmayapti. "
                                . "Yangi qozon quygan bo'lsangiz «Yangi qozon ochish» bilan yozing — aks holda xomashyo hisobdan chiqmaydi.",
                ];
            } else {
                $out[] = [
                    'daraja'   => 'xato',
                    'mahsulot' => $nomi,
                    'matn'     => "«{$nomi}» — {$t($yangi_talab)} porsiya buyurtma bor, lekin hech qanday qozon/partiya ochilmagan. "
                                . "Xomashyo hisobga OLINMAYAPTI! «Osh qozoni» oynasida partiyani oching.",
                ];
            }
            continue;
        }

        // Ochiq qozonning band bo'lgan miqdori: aynan shu ochiq qozon(lar)dan
        // sotilgan porsiyalar + eski/yopilgan qozon qoldig'i qoplay olmaydigan
        // navbat. Avvalgi yopilgan qozon sotuvi yangi qozon mo'ljaliga qo'shilmaydi.
        $ochiq_ids = array_map(
            'intval',
            array_column($db->rows(
                "SELECT id FROM im_osh_qozon
                 WHERE filial_id=$filial_id AND mahsulot_id=$mid
                   AND sana='$bugun' AND holat='ochiq'"
            ), 'id')
        );
        $ids_sql = implode(',', $ochiq_ids);
        $ochiq_sotildi = $ids_sql === '' ? 0.0 : (float)$db->val(
            "SELECT COALESCE(SUM(fm.qty-fm.reversed_qty),0)
             FROM im_fifo_movements fm
             JOIN im_fifo_layers fl ON fl.id=fm.layer_id
             WHERE fl.location_id=$filial_id AND fl.mahsulot_id=$mid
               AND fl.source='qozon' AND fl.source_id IN ($ids_sql)
               AND fm.source='sotuv' AND fm.kind='take'"
        );
        $eski_qoldiq = $ids_sql === '' ? $tayyor_qoldiq : (float)$db->val(
            "SELECT COALESCE(SUM(remaining_qty),0) FROM im_fifo_layers
             WHERE location_id=$filial_id AND mahsulot_id=$mid
               AND source='qozon' AND cancelled=0 AND remaining_qty>0
               AND source_id NOT IN ($ids_sql)"
        );
        $talab = $ochiq_sotildi + max(0, $navbat - $eski_qoldiq);

        if ($moljal > 0 && $talab >= $moljal) {
            $out[] = [
                'daraja'   => 'ogoh',
                'mahsulot' => $nomi,
                'matn'     => "«{$nomi}» — ochiq partiya mo'ljali ({$t($moljal)}) to'ldi ({$t($talab)} porsiya chiqdi). "
                            . "Yana qozon quysangiz «Yangi qozon ochish» → «Qo'shimcha qozon» bilan yozing yoki mo'ljalni to'g'rilang.",
            ];
        } elseif ($moljal > 0 && $talab >= $moljal * 0.85) {
            $out[] = [
                'daraja'   => 'info',
                'mahsulot' => $nomi,
                'matn'     => "«{$nomi}» — mo'ljal tugayapti ({$t($talab)}/{$t($moljal)} porsiya).",
            ];
        }
    }

    return $out;
}

/**
 * Qozon mahsulotini oshxona bosqichidan o'tkazish mumkinligini tekshiradi.
 * Yopilgan qozondan qolgan tayyor FIFO porsiya yetarli bo'lsa yangi qozon
 * shart emas. Qoldiq yetmasa esa mahsulot uchun ochiq qozon bo'lishi shart.
 */
function im_qozon_buyurtmaga_tayyor($db, $filial_id, $mahsulot_id, $kerak, $nomi = '') {
    $filial_id   = (int)$filial_id;
    $mahsulot_id = (int)$mahsulot_id;
    $kerak        = max(0, (float)$kerak);

    $mavjud = (float)im_fifo_balance($db, $filial_id, $mahsulot_id)['qty'];
    if ($mavjud + 0.000001 >= $kerak) return true;

    $ochiq = (int)$db->val(
        "SELECT COUNT(*) FROM im_osh_qozon
         WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id AND holat='ochiq'"
    );
    if ($ochiq > 0) return true;

    $label = trim((string)$nomi) !== '' ? "«" . trim((string)$nomi) . "»" : 'Mahsulot';
    throw new Exception(
        "$label uchun ochiq qozon yo‘q va tayyor porsiya yetarli emas. "
        . "Avval «Osh qozoni» oynasida qozonni oching."
    );
}

/**
 * If an open pot physically yields more than its estimate, make the observed
 * extra servings sellable. The total ingredient cost is unchanged; only the
 * provisional unit cost is spread over the larger observed minimum yield.
 */
function im_qozon_sotuvga_yetkaz($db, $filial_id, $mahsulot_id, $kerak) {
    if (!$db->inTransaction()) throw new Exception('Qozon tuzatishi tranzaksiya ichida bajarilishi kerak');
    $filial_id=(int)$filial_id; $mahsulot_id=(int)$mahsulot_id;
    $kerak=(float)im_fifo_number($kerak);
    im_fifo_lock($db,$filial_id,$mahsulot_id);
    $mavjud=im_fifo_balance($db,$filial_id,$mahsulot_id)['qty'];
    if ($mavjud+0.000001 >= $kerak) return 0.0;

    $yetishmaydi=round($kerak-$mavjud,3);
    $q=$db->row("SELECT * FROM im_osh_qozon
        WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id AND holat='ochiq'
        ORDER BY id DESC LIMIT 1 FOR UPDATE");
    if (!$q) throw new Exception('Ochiq qozonda sotishga yetarli porsiya yo‘q');
    $qid=(int)$q['id'];
    $layer=$db->row("SELECT * FROM im_fifo_layers
        WHERE location_id=$filial_id AND mahsulot_id=$mahsulot_id
          AND source='qozon' AND source_id=$qid AND cancelled=0
        ORDER BY id DESC LIMIT 1 FOR UPDATE");
    if (!$layer) throw new Exception('Ochiq qozonning FIFO qatlami topilmadi');

    $yangi_initial=round((float)$layer['initial_qty']+$yetishmaydi,3);
    $limit=max(1,(float)$q['moljal_porsiya'])*2;
    if ($yangi_initial>$limit+0.000001) {
        throw new Exception('Qozon chiqishi mo‘ljaldan ikki baravardan oshdi; haqiqiy miqdorni tekshiring');
    }
    $yangi_qoldiq=round((float)$layer['remaining_qty']+$yetishmaydi,3);
    $yangi_cost=(float)$q['xomashyo_summa']/$yangi_initial;
    $initial_sql=im_fifo_number($yangi_initial);
    $remaining_sql=im_fifo_number($yangi_qoldiq);
    $cost_sql=im_fifo_number($yangi_cost,6);
    $short_sql=im_fifo_number($yetishmaydi);
    im_fifo_exec($db,"UPDATE im_fifo_layers SET initial_qty=$initial_sql,
        remaining_qty=$remaining_sql,unit_cost=$cost_sql WHERE id=".(int)$layer['id']);
    im_fifo_exec($db,"UPDATE im_filial_qoldiq SET soni=soni+$short_sql
        WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id");
    im_fifo_cache_price($db,$filial_id,$mahsulot_id);
    return $yetishmaydi;
}

/**
 * Finalize one pot from observed output: sold portions plus physical remainder.
 * Historic sale rows stay immutable; reports resolve the finalized cost through
 * their FIFO movement -> pot layer relationship.
 */
function im_qozon_yakuniy_tannarx($db, $qozon_id, $filial_id, $qoldi, $isrofmi) {
    if (!$db->inTransaction()) throw new Exception('Qozon yakuni tranzaksiya ichida bajarilishi kerak');
    $qozon_id=(int)$qozon_id; $filial_id=(int)$filial_id;
    $qoldi=(float)im_fifo_number($qoldi); $isrofmi=(int)(bool)$isrofmi;
    $basic=$db->row("SELECT mahsulot_id FROM im_osh_qozon WHERE id=$qozon_id AND filial_id=$filial_id");
    if (!$basic) throw new Exception('Qozon topilmadi');
    $mahsulot_id=(int)$basic['mahsulot_id'];

    // Keep checkout and pot close on the same lock order: stock, then pot/layer.
    im_fifo_lock($db,$filial_id,$mahsulot_id);
    $q=$db->row("SELECT * FROM im_osh_qozon WHERE id=$qozon_id AND filial_id=$filial_id FOR UPDATE");
    if (!$q || $q['holat']!=='ochiq') throw new Exception('Qozon ochiq emas');
    $layer=$db->row("SELECT * FROM im_fifo_layers
        WHERE location_id=$filial_id AND mahsulot_id=$mahsulot_id
          AND source='qozon' AND source_id=$qozon_id AND cancelled=0
        ORDER BY id DESC LIMIT 1 FOR UPDATE");
    if (!$layer) throw new Exception('Qozonning FIFO qatlami topilmadi');
    $layer_id=(int)$layer['id'];

    // Qaytarilgan sotuv porsiyasi haqiqiy iste'molga kirmaydi.
    $sotildi=(float)$db->val("SELECT COALESCE(SUM(qty-reversed_qty),0) FROM im_fifo_movements
        WHERE layer_id=$layer_id AND source='sotuv' AND kind='take'");
    $haqiqiy=round($sotildi+$qoldi,3);
    if ($haqiqiy<=0) throw new Exception('Haqiqiy chiqish nol bo‘lishi mumkin emas');
    $moljal=(float)$q['moljal_porsiya'];
    if ($moljal>0 && $haqiqiy>$moljal*2+0.000001) {
        throw new Exception("Haqiqiy chiqish ($haqiqiy) mo‘ljaldan ($moljal) juda katta");
    }
    $yakuniy=(float)$q['xomashyo_summa']/$haqiqiy;
    $adjust=round($qoldi-(float)$layer['remaining_qty'],3);
    $adjust_sql=im_fifo_number($adjust);
    if ($adjust<0) {
        im_fifo_exec($db,"UPDATE im_filial_qoldiq SET soni=soni+$adjust_sql
            WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id AND soni+$adjust_sql>=0");
        if ($db->affected()!==1) throw new Exception('Haqiqiy qozon qoldig‘iga moslashtirish uchun qoldiq yetarli emas');
    } elseif ($adjust>0) {
        im_fifo_exec($db,"UPDATE im_filial_qoldiq SET soni=soni+$adjust_sql
            WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id");
    }

    $actual_sql=im_fifo_number($haqiqiy);
    $remain_sql=im_fifo_number($qoldi);
    $cost_sql=im_fifo_number($yakuniy,6);
    im_fifo_exec($db,"UPDATE im_fifo_layers SET initial_qty=$actual_sql,
        remaining_qty=$remain_sql,unit_cost=$cost_sql WHERE id=$layer_id");

    if ($isrofmi && $qoldi>0) {
        im_fifo_exec($db,"UPDATE im_fifo_layers SET remaining_qty=0 WHERE id=$layer_id");
        im_fifo_exec($db,"UPDATE im_filial_qoldiq SET soni=soni-$remain_sql
            WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id AND soni>=$remain_sql");
        if ($db->affected()!==1) throw new Exception('Qozon qoldig‘ini isrofga yozish uchun qoldiq yetarli emas');
        im_fifo_exec($db,"INSERT INTO im_fifo_movements
            (layer_id,source,source_id,kind,qty,unit_cost,created_at)
            VALUES($layer_id,'qozon',$qozon_id,'take',$remain_sql,$cost_sql,NOW())");
    }

    im_fifo_exec($db,"UPDATE im_osh_qozon SET haqiqiy_porsiya=$actual_sql,
        yakuniy_tannarx=$cost_sql WHERE id=$qozon_id");
    im_fifo_cache_price($db,$filial_id,$mahsulot_id);
    return ['sotildi'=>$sotildi,'haqiqiy'=>$haqiqiy,'tannarx'=>$yakuniy,'farq'=>$adjust];
}
