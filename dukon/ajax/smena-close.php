<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir']);
$db = new Cyber();
$sale_cost = im_fifo_sale_unit_cost_sql('si');

$smena_id = (int)($_POST['smena_id'] ?? 0);
if (!$smena_id) im_json('error', 'Smena ID kerak');

$smena = $db->row("SELECT * FROM im_smena WHERE id=$smena_id AND kassir_id=$im_user_id AND holat='ochiq'");
if (!$smena) im_json('error', 'Smena topilmadi yoki allaqachon yopilgan');

// Smena vaqt oralig'i
$smena_start = mysqli_real_escape_string($link, $smena['ochildi'] ?? $smena['boshlanish'] ?? date('Y-m-d H:i:s'));
$smena_end   = date('Y-m-d H:i:s');
$filial_id   = (int)($smena['filial_id'] ?? 0);
$kassir_id   = (int)$im_user_id;

// Filtri — JOIN bo'lmagan querylarda (im_sotuvlar birdan)
$filter_st = "kassir_id=$kassir_id AND filial_id=$filial_id
              AND sana BETWEEN '$smena_start' AND '$smena_end'";

// Filtri — JOIN bo'lgan querylarda (s. prefiksi bilan)
$filter_s = "s.kassir_id=$kassir_id AND s.filial_id=$filial_id
             AND s.sana BETWEEN '$smena_start' AND '$smena_end'";

// ── To'liq Smena hisoboti ────────────────────────────────
$stats = $db->row(
    "SELECT
        COUNT(*)                                AS sotuv_soni,
        COALESCE(SUM(tolov_summa),0)            AS jami_summa,
        COALESCE(SUM(naqd_summa),0)             AS naqd,
        COALESCE(SUM(karta_summa),0)            AS karta,
        COALESCE(SUM(bank_summa),0)             AS bank,
        COALESCE(SUM(usd_summa),0)              AS usd,
        COALESCE(SUM(usd_som_ekviv),0)          AS usd_som,
        COALESCE(SUM(nasiya_summa),0)           AS nasiya,
        COALESCE(SUM(chegirma_summa),0)         AS chegirma
     FROM im_sotuvlar WHERE $filter_st"
);

// Harajatlar (sana DATE field — DATE() konversiyasi bilan)
$harajat = (float)$db->val(
    "SELECT COALESCE(SUM(summa),0) FROM im_harajatlar
     WHERE xodim_id=$kassir_id AND filial_id=$filial_id
       AND sana BETWEEN DATE('$smena_start') AND DATE('$smena_end')"
);
// Faqat NAQD to'langan xarajatlar — naqd tortma hisobidan shungina ayiriladi
// (karta/bank xarajati naqd puldan chiqmagan, tortmaga ta'sir qilmasligi kerak)
$harajat_naqd = (float)$db->val(
    "SELECT COALESCE(SUM(summa),0) FROM im_harajatlar
     WHERE xodim_id=$kassir_id AND filial_id=$filial_id AND tolov_turi='naqd'
       AND sana BETWEEN DATE('$smena_start') AND DATE('$smena_end')"
);

// Inkasso
$inkasso_jami = (float)$db->val(
    "SELECT COALESCE(SUM(summa),0) FROM im_inkasasiya
     WHERE xodim_id=$kassir_id AND filial_id=$filial_id
       AND sana BETWEEN '$smena_start' AND '$smena_end'"
);
$inkasso_list = $db->rows(
    "SELECT i.*, x.ism AS xodim FROM im_inkasasiya i
     LEFT JOIN im_xodimlar x ON x.id = i.xodim_id
     WHERE i.xodim_id=$kassir_id AND i.filial_id=$filial_id
       AND i.sana BETWEEN '$smena_start' AND '$smena_end'
     ORDER BY i.sana ASC"
);

// Vozvratlar — JOIN da s.sana
$vozvrat_sum = (float)$db->val(
    "SELECT COALESCE(SUM(v.qaytarish_summa),0)
     FROM im_vozvratlar v
     JOIN im_sotuvlar s ON s.id = v.sotuv_id
     WHERE $filter_s"
);

// Tannarx — JOIN da s.sana (sotilgan tovarlar tannarxi)
$tannarx_jami = (float)$db->val(
    "SELECT COALESCE(SUM(($sale_cost) * si.soni), 0)
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id = si.sotuv_id
     WHERE $filter_s"
);

// Qaytarilgan tovarlar tannarxi (zarar bo'lmasligi uchun chegirilishi shart)
$vozvrat_tannarx = (float)$db->val(
    "SELECT COALESCE(SUM(($sale_cost) * v.soni), 0)
     FROM im_vozvratlar v
     JOIN im_sotuvlar s ON s.id = v.sotuv_id
     JOIN im_sotuv_items si ON si.id = v.sotuv_item_id
     WHERE $filter_s"
);

// Toza tannarx
$tannarx_jami -= $vozvrat_tannarx;

// Top 5 mahsulot — JOIN da s.sana
$top_mah = $db->rows(
    "SELECT m.nomi, SUM(si.soni) AS soni, SUM(si.chegirma_narxi * si.soni) AS summa
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id = si.sotuv_id
     JOIN im_mahsulotlar m ON m.id = si.mahsulot_id
     WHERE $filter_s
     GROUP BY m.id ORDER BY summa DESC LIMIT 5"
);

// USD qaytim (sumdagi qaytim — naqd kassadan chiqqan)
$usd_qaytim_jami = (float)$db->val(
    "SELECT COALESCE(SUM(usd_qaytim_som),0) FROM im_sotuvlar
     WHERE $filter_st"
);

// Nasiya to'lovlari (naqd) — smena davomida kassaga kelgan
$nasiya_tolov_naqd = (float)$db->val(
    "SELECT COALESCE(SUM(nt.summa),0) FROM im_nasiya_tolov nt
     WHERE nt.tolov_turi='naqd' AND nt.kassir_id=$kassir_id
     AND nt.sana BETWEEN '$smena_start' AND '$smena_end'"
);

// Shu smena vaqtida yopilib, isrof deb belgilangan qozon qoldig'i.
$qozon_isrofi = (float)$db->val(
    "SELECT COALESCE(SUM(qoldi_porsiya*yakuniy_tannarx),0) FROM im_osh_qozon
     WHERE filial_id=$filial_id AND holat='yopildi' AND qoldi_isrofmi=1
       AND yopildi_vaqt BETWEEN '$smena_start' AND '$smena_end'"
);

// ── Sof foyda ────────────────────────────────────────────
$sotuv_netto = (float)$stats['jami_summa'] - $vozvrat_sum;
// Naqd tortmadagi HAQIQIY qoldiq — smena davomida naqd chiqqan xarajatlar (masalan,
// yetkazib beruvchiga qo'lma-qo'l to'lov) ham ayirilishi shart, aks holda "kutilayotgan
// naqd" haqiqiy tortmadagi puldan xarajat summasiga teng miqdorda ko'p ko'rsatiladi.
$naqd_jami   = (float)$stats['naqd'] + (float)($smena['ochish_naqd'] ?? 0) + $nasiya_tolov_naqd - $usd_qaytim_jami - $harajat_naqd;
$sof_foyda   = $sotuv_netto - $tannarx_jami - $harajat - $qozon_isrofi;

// Smenani yopish
$db->q("UPDATE im_smena SET holat='yopiq', yopish_naqd=$naqd_jami, yopildi=NOW() WHERE id=$smena_id");

im_json('ok', 'Smena muvaffaqiyatli yopildi!', [
    'smena_id'     => $smena_id,
    'sotuv_soni'   => (int)$stats['sotuv_soni'],
    'jami_summa'   => (float)$stats['jami_summa'],
    'naqd'         => (float)$stats['naqd'] - $usd_qaytim_jami,
    'karta'        => (float)$stats['karta'],
    'bank'         => (float)$stats['bank'],
    'usd'          => (float)$stats['usd'],
    'usd_som'      => (float)$stats['usd_som'],
    'nasiya'       => (float)$stats['nasiya'],
    'chegirma'     => (float)$stats['chegirma'],
    'harajat'      => $harajat,
    'qozon_isrofi' => $qozon_isrofi,
    'vozvrat'      => $vozvrat_sum,
    'tannarx'      => $tannarx_jami,
    'inkasso'      => $inkasso_jami,
    'inkasso_list' => $inkasso_list,
    'usd_qaytim'   => $usd_qaytim_jami,
    'nasiya_tolov_naqd' => $nasiya_tolov_naqd,
    'sotuv_netto'  => $sotuv_netto,
    'ochish_naqd'  => (float)($smena['ochish_naqd'] ?? 0),
    'yopish_naqd'  => $naqd_jami,
    'sof_foyda'    => $sof_foyda,
    'top_mah'      => $top_mah,
    'boshlanish'   => $smena_start,
    'yopildi'      => $smena_end,
]);
