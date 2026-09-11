<?php
// ============================================================
//  IMezon — Analitika / Dinamika: grafik + jadval ma'lumoti
//  GET: kun (7|30|60|90), filial_id (0 = barchasi)
//  Bitta chaqiruvda barcha grafik va jadvalni qaytaradi.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../ai_tools.php';   // im_ait_qoldiq_holat() qayta ishlatiladi
im_rol_check(['admin', 'bosh_kassir']);

header('Content-Type: application/json; charset=utf-8');
$db = new Cyber();
$fifo_stock = im_fifo_report_stock_sql();
$sale_cost = im_fifo_sale_unit_cost_sql('si');
$return_cost = im_fifo_sale_unit_cost_sql('rsi');

// ── Parametrlar (ISHONCHSIZ — oq ro'yxat + int) ────────────
$kun_ruxsat = [7, 30, 60, 90];
$kun = (int)($_GET['kun'] ?? 30);
if (!in_array($kun, $kun_ruxsat, true)) $kun = 30;

$filial_id = (int)($_GET['filial_id'] ?? 0);
$sfil = $filial_id > 0 ? " AND s.filial_id = $filial_id " : '';

$dan_ts  = strtotime("-" . ($kun - 1) . " days");
$dan_kun = date('Y-m-d', $dan_ts);

// ── 1. Kunlik teglar (nol kunlar ham to'ldiriladi) ─────────
$kunlar = [];
for ($i = 0; $i < $kun; $i++) $kunlar[date('Y-m-d', strtotime("-" . ($kun - 1 - $i) . " days"))] = $i;
$labels = array_keys($kunlar);
$N = count($labels);

$sotuv   = array_fill(0, $N, 0.0);
$foyda   = array_fill(0, $N, 0.0);
$cheklar = array_fill(0, $N, 0);
$kirim   = array_fill(0, $N, 0.0);
$chiqim  = array_fill(0, $N, 0.0);

// ── 2. Kunlik sotuv + chek soni ───────────────────────────
foreach ($db->rows(
    "SELECT DATE(s.sana) k, COALESCE(SUM(s.tolov_summa),0) summa, COUNT(*) n
     FROM im_sotuvlar s
     WHERE s.holat IN ('aktiv','qaytarilgan') AND s.sana >= '$dan_kun 00:00:00' $sfil
     GROUP BY DATE(s.sana)"
) as $r) {
    if (isset($kunlar[$r['k']])) {
        $sotuv[$kunlar[$r['k']]]   = (float)$r['summa'];
        $cheklar[$kunlar[$r['k']]] = (int)$r['n'];
    }
}

// ── 3. Kunlik brutto foyda + sotilgan tannarx (COGS) ───────
// Tushum yuqorida yakuniy chek summasi (tolov_summa)dan olindi; shu sabab
// chegirma va xizmat haqini yana alohida qo'shish/ayirish shart emas.
foreach ($db->rows(
    "SELECT DATE(s.sana) k,
            COALESCE(SUM(($sale_cost) * si.soni),0) cogs
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id = si.sotuv_id
     WHERE s.holat IN ('aktiv','qaytarilgan') AND s.sana >= '$dan_kun 00:00:00' $sfil
     GROUP BY DATE(s.sana)"
) as $r) {
    if (isset($kunlar[$r['k']])) {
        $idx = $kunlar[$r['k']];
        $chiqim[$idx] = (float)$r['cogs'];
        $foyda[$idx]  = $sotuv[$idx] - $chiqim[$idx];
    }
}

// ── 4. Kunlik partiya kirim (kompaniya bo'yicha — filial filtri tegmaydi) ──
foreach ($db->rows(
    "SELECT p.sana k, COALESCE(SUM(p.jami_summa),0) summa
     FROM im_partiyalar p
     WHERE p.sana >= '$dan_kun'
     GROUP BY p.sana"
) as $r) {
    if (isset($kunlar[$r['k']])) $kirim[$kunlar[$r['k']]] = (float)$r['summa'];
}

// Current physical inventory is exact. Layer balances alone cannot reconstruct
// historical balances (transfers, production, waste and cancellations are missing).
$hozir_qiymat = (float)$db->val("SELECT COALESCE(SUM(remaining_qty*unit_cost),0)
    FROM im_fifo_layers WHERE cancelled=0 AND remaining_qty>0");
$ombor = array_fill(0, $N, null);
$ombor[$N - 1] = round($hozir_qiymat, 2);

$vfil = $filial_id > 0 ? " AND s.filial_id=$filial_id" : '';
foreach ($db->rows("SELECT DATE(v.sana) k, SUM(v.qaytarish_summa) revenue,
    SUM(v.soni*CASE WHEN rsi.id IS NOT NULL THEN ($return_cost) ELSE COALESCE(v.tannarx,0) END) cost FROM im_vozvratlar v
    JOIN im_sotuvlar s ON s.id=v.sotuv_id
    LEFT JOIN im_sotuv_items rsi ON rsi.id=v.sotuv_item_id
    WHERE s.holat IN ('aktiv','qaytarilgan') AND v.sana >= '$dan_kun 00:00:00' $vfil
    GROUP BY DATE(v.sana)") as $r) {
    if (!isset($kunlar[$r['k']])) continue;
    $i = $kunlar[$r['k']];
    $sotuv[$i] -= (float)$r['revenue'];
    $foyda[$i] -= (float)$r['revenue'] - (float)$r['cost'];
    $chiqim[$i] -= (float)$r['cost'];
}

// ── 6. Kam qolgan mahsulotlar (AI vositasidan) ─────────────
$kam = im_ait_qoldiq_holat($db, [
    'filial_id' => $filial_id,
    'tur'       => 'kam',
    'kun'       => max(14, $kun),
    'limit'     => 40,
]);

// ── 7. Uxlab yotgan zaxira (muzlagan kapital) ──────────────
// FAQAT sotiladigan tayyor mahsulot: Non, Cola, Ayron, taomlar...
// Xomashyo (faqat_ishlab_chiqarish=1) bu ro'yxatga kirmaydi —
// u sotilmaydi, retsept orqali sarflanadi (AI: qoldiq_holat).
$ffil_q = $filial_id > 0 ? " AND fq.filial_id = $filial_id " : '';
$harakatsiz_kun = 21;   // shu kundan beri sotilmagan bo'lsa "uxlab yotgan"
$uxlab_rows = $db->rows(
    "SELECT m.id, m.nomi, m.birlik, COALESCE(k.nomi,'—') kategoriya,
            q.qoldiq, q.qiymat,
            (SELECT MAX(DATE(s.sana)) FROM im_sotuv_items si
             JOIN im_sotuvlar s ON s.id = si.sotuv_id
             WHERE si.mahsulot_id = m.id AND s.holat IN ('aktiv','qaytarilgan') $sfil) oxirgi_sotuv
     FROM (
        SELECT fq.mahsulot_id,
               SUM(fq.soni) qoldiq,
               SUM(fq.qiymat) qiymat
        FROM ($fifo_stock) fq
        WHERE fq.filial_id>0 AND fq.soni > 0 $ffil_q
        GROUP BY fq.mahsulot_id
        HAVING SUM(fq.soni) > 0
     ) q
     JOIN im_mahsulotlar m ON m.id = q.mahsulot_id
     LEFT JOIN im_kategoriyalar k ON k.id = m.kategoriya_id
     WHERE m.status = 1 AND m.sotiladi = 1 AND m.faqat_ishlab_chiqarish = 0
     HAVING oxirgi_sotuv IS NULL
         OR oxirgi_sotuv < DATE_SUB(CURDATE(), INTERVAL $harakatsiz_kun DAY)
     ORDER BY qiymat DESC
     LIMIT 40"
);
$uxlab = [];
$uxlab_jami_qiymat = 0.0;
foreach ($uxlab_rows as $r) {
    $hk = $r['oxirgi_sotuv'] ? (int)((time() - strtotime($r['oxirgi_sotuv'])) / 86400) : null;
    $uxlab_jami_qiymat += (float)$r['qiymat'];
    $uxlab[] = [
        'nomi'          => $r['nomi'],
        'kategoriya'    => $r['kategoriya'],
        'qoldiq'        => round((float)$r['qoldiq'], 2),
        'birlik'        => $r['birlik'],
        'qiymat'        => round((float)$r['qiymat'], 2),
        'oxirgi_sotuv'  => $r['oxirgi_sotuv'],           // null = hech qachon
        'harakatsiz_kun'=> $hk,
    ];
}

// ── Javob ─────────────────────────────────────────────────
im_json('ok', '', [
    'kun'            => $kun,
    'filial_id'      => $filial_id,
    'labels'         => $labels,
    'sotuv'          => $sotuv,
    'foyda'          => $foyda,
    'cheklar'        => $cheklar,
    'ombor'          => $ombor,
    'ombor_tarix_mavjud' => false,
    'ombor_izoh' => 'Faqat joriy FIFO qiymati aniq; tarixiy harakatlar jurnali kerak.',
    'kirim'          => $kirim,
    'chiqim'         => $chiqim,
    'ombor_hozir'    => round($hozir_qiymat, 2),
    'kam_qolgan'     => $kam['qatorlar'] ?? [],
    'kam_jami'       => $kam['jami_topildi'] ?? 0,
    'uxlab'          => $uxlab,
    'uxlab_jami_qiymat' => round($uxlab_jami_qiymat, 2),
    'harakatsiz_kun' => $harakatsiz_kun,
]);
