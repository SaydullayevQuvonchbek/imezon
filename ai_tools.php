<?php
// ============================================================
//  IMezon — AI Yordamchi: TAHLIL VOSITALARI (tools)
// ------------------------------------------------------------
//  Bu fayl AI agentga "qo'l" beradi. Har bir vosita — bazadan
//  TAYYOR, YIG'ILGAN raqam qaytaradigan PHP funksiyasi.
//
//  NIMA UCHUN AI'ga SQL yozdirilmaydi:
//  Modelga "SELECT yoz" desak, u bir kun kelib DELETE yoki
//  boshqa bazadagi jadvalni yozadi, yoki 50 000 qatorni tortib
//  kontekstni to'ldiradi. Shuning uchun model faqat shu yerdagi
//  ANIQ vositalarni, ANIQ parametrlar bilan chaqira oladi.
//  Parametrlar modeldan keladi — ya'ni ISHONCHSIZ ma'lumot:
//  har bir son (int), har bir matn escape, har bir tanlov esa
//  oq ro'yxat orqali o'tkaziladi.
//
//  Foyda hisobi admin/index.php dagi formula bilan BIR XIL
//  bo'lishi shart — aks holda AI dashboard'dan boshqa raqam
//  aytadi va ikkalasiga ham ishonch qolmaydi.
// ============================================================

if (!defined('im_VERSION')) return;
require_once __DIR__ . '/fifo_reports.php';

// ─── Umumiy yordamchilar ────────────────────────────────────

// Modeldan kelgan sanani xavfsiz holga keltiradi.
// "bugun", "kecha", "hafta", "oy", "oldingi_oy", "yil" kabi
// so'zlarni ham tushunadi — model har safar aniq sana hisoblab
// o'tirmasin (u serverning bugungi sanasini bilmaydi).
function im_ait_sana($qiymat, $default) {
    $q = strtolower(trim((string)$qiymat));
    if ($q === '') return $default;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $q)) return $q;

    switch ($q) {
        case 'bugun':       return date('Y-m-d');
        case 'kecha':       return date('Y-m-d', strtotime('-1 day'));
        case 'hafta':       return date('Y-m-d', strtotime('-6 days'));
        case 'oy':          return date('Y-m-01');
        case 'oy_boshi':    return date('Y-m-01');
        case 'oldingi_oy':  return date('Y-m-01', strtotime('first day of last month'));
        case 'yil':         return date('Y-01-01');
        case '30kun':       return date('Y-m-d', strtotime('-29 days'));
        case '90kun':       return date('Y-m-d', strtotime('-89 days'));
    }
    return $default;
}

// [dan, gacha] — har doim to'g'ri tartibda va haqiqiy sana
function im_ait_oraliq(array $a) {
    $dan   = im_ait_sana(isset($a['dan'])   ? $a['dan']   : '', date('Y-m-01'));
    $gacha = im_ait_sana(isset($a['gacha']) ? $a['gacha'] : '', date('Y-m-d'));
    if ($dan > $gacha) { $t = $dan; $dan = $gacha; $gacha = $t; }
    return [$dan, $gacha];
}

function im_ait_int($a, $k, $def = 0) {
    return isset($a[$k]) ? (int)$a[$k] : $def;
}

// Oq ro'yxat — modeldan kelgan tanlov ro'yxatda bo'lmasa default
function im_ait_tanlov($a, $k, array $ruxsat, $def) {
    $v = isset($a[$k]) ? strtolower(trim((string)$a[$k])) : '';
    return in_array($v, $ruxsat, true) ? $v : $def;
}

function im_ait_esc($db, $s) {
    // Cyber klasida escape yo'q — PDO emas, mysqli. Global $link
    // ustidan escape qilamiz (ikkalasi ham bir bazaga ulangan).
    global $link;
    return mysqli_real_escape_string($link, (string)$s);
}

// Filial sharti. 0 yoki bo'sh = barcha filiallar.
function im_ait_filial($a, $ustun = 's.filial_id') {
    $f = im_ait_int($a, 'filial_id', 0);
    return $f > 0 ? " AND $ustun = $f " : '';
}

// "Faqat oshxona taomlari" filtri — oshpaz tayyorlaydigan yoki
// retsept bo'yicha avtomatik yechiladigan mahsulotlar. Vitrinada
// turadigan tayyor tovar (suv, gazak) bundan chiqarib tashlanadi.
function im_ait_oshxona($a, $ustun = 'si.mahsulot_id') {
    if (empty($a['faqat_oshxona'])) return '';
    return " AND $ustun IN (SELECT id FROM im_mahsulotlar
                            WHERE COALESCE(oshpaz_kerak,0)=1 OR COALESCE(retsept_avto,0)=1) ";
}

function im_ait_f2($n) { return round((float)$n, 2); }

// ─── 1. SOTUV XULOSASI ──────────────────────────────────────
function im_ait_sotuv_xulosa($db, array $a) {
    list($dan, $gacha) = im_ait_oraliq($a);
    $fil   = im_ait_filial($a);
    $guruh = im_ait_tanlov($a, 'guruh', ['yoq','kun','hafta_kuni','soat','oy','filial','manba'], 'kun');
    $w     = "s.holat IN ('aktiv','qaytarilgan') AND s.sana BETWEEN '$dan 00:00:00' AND '$gacha 23:59:59' $fil";

    $j = $db->row(
        "SELECT COUNT(*) chek,
                COALESCE(SUM(s.tolov_summa),0)    tushum,
                COALESCE(SUM(s.naqd_summa),0)     naqd,
                COALESCE(SUM(s.karta_summa),0)    karta,
                COALESCE(SUM(s.bank_summa),0)     bank,
                COALESCE(SUM(s.usd_som_ekviv),0)  usd_som,
                COALESCE(SUM(s.nasiya_summa),0)   nasiya,
                COALESCE(SUM(s.chegirma_summa),0) chegirma,
                COALESCE(SUM(s.xizmat_summa),0)   xizmat
         FROM im_sotuvlar s WHERE $w"
    );

    $chek = (int)$j['chek'];
    $out = [
        'oraliq'          => "$dan .. $gacha",
        'chek_soni'       => $chek,
        'tushum'          => im_ait_f2($j['tushum']),
        'ortacha_chek'    => $chek ? im_ait_f2($j['tushum'] / $chek) : 0,
        'tolov_turlari'   => [
            'naqd'   => im_ait_f2($j['naqd']),
            'karta'  => im_ait_f2($j['karta']),
            'bank'   => im_ait_f2($j['bank']),
            'usd'    => im_ait_f2($j['usd_som']),
            'nasiya' => im_ait_f2($j['nasiya']),
        ],
        'chegirma'        => im_ait_f2($j['chegirma']),
        'xizmat_haqi'     => im_ait_f2($j['xizmat']),
    ];

    // Bekor qilingan va qaytarilgan cheklar — tushumga kirmaydi,
    // lekin ular haqida jim turish noto'g'ri xulosaga olib keladi.
    $out['bekor_chek'] = (int)$db->val(
        "SELECT COUNT(*) FROM im_sotuvlar s
         WHERE s.holat IN ('bekor','qaytarilgan')
           AND s.sana BETWEEN '$dan 00:00:00' AND '$gacha 23:59:59' $fil"
    );
    $returns = im_fifo_report_returns($db, $dan, $gacha, im_ait_int($a, 'filial_id'));
    $out['qaytarish_summa'] = im_ait_f2($returns['revenue']);
    $out['sof_tushum'] = im_ait_f2($j['tushum'] - $returns['revenue']);

    // ── Dinamika ──
    if ($guruh !== 'yoq') {
        $ifoda = [
            'kun'        => "DATE(s.sana)",
            'oy'         => "DATE_FORMAT(s.sana,'%Y-%m')",
            'soat'       => "LPAD(HOUR(s.sana),2,'0')",
            'hafta_kuni' => "DAYNAME(s.sana)",
            'filial'     => "COALESCE((SELECT nomi FROM im_filiallar f WHERE f.id=s.filial_id),'Asosiy')",
            'manba'      => "COALESCE(NULLIF(s.manba,''),'—')",
        ];
        $e = $ifoda[$guruh];
        $out['guruh']    = $guruh;
        $out['dinamika'] = array_map(function ($r) {
            return [
                'kalit'  => $r['k'],
                'chek'   => (int)$r['n'],
                'tushum' => im_ait_f2($r['s']),
            ];
        }, $db->rows(
            "SELECT $e AS k, COUNT(*) n, COALESCE(SUM(s.tolov_summa),0) s
             FROM im_sotuvlar s WHERE $w GROUP BY k ORDER BY k ASC LIMIT 100"
        ));
    }
    return $out;
}

// ─── 2. FOYDA XULOSASI ──────────────────────────────────────
// Brutto foyda = yakuniy chek tushumi − dinamik FIFO tannarxi,
// qaytarishlarning tushumi va tannarxi ayrilgan.
// Sof foyda = brutto − harajatlar − maoshlar − qozon isrofi.
function im_ait_foyda_xulosa($db, array $a) {
    $sale_cost = im_fifo_sale_unit_cost_sql('si');
    $line_revenue = im_sotuv_qator_tushum_sql('s', 'si');
    list($dan, $gacha) = im_ait_oraliq($a);
    $fil  = im_ait_filial($a);
    $osh  = im_ait_oshxona($a);
    $w    = "s.holat IN ('aktiv','qaytarilgan') AND s.sana BETWEEN '$dan 00:00:00' AND '$gacha 23:59:59' $fil";

    // Tushum: oddiy holatda chek summasi (xizmat haqi va chegirma bilan).
    // faqat_oshxona=true bo'lganda esa qatorlardan yig'iladi — aks holda
    // marja "oshxona foydasi / BUTUN tushum" bo'lib, ataylab past chiqadi.
    if (empty($a['faqat_oshxona'])) {
        $tushum = (float)$db->val("SELECT COALESCE(SUM(s.tolov_summa),0) FROM im_sotuvlar s WHERE $w");
    } else {
        $tushum = (float)$db->val(
            "SELECT COALESCE(SUM($line_revenue),0)
             FROM im_sotuv_items si JOIN im_sotuvlar s ON s.id=si.sotuv_id
             WHERE $w $osh"
        );
    }

    $tannarx = (float)$db->val(
        "SELECT COALESCE(SUM(($sale_cost) * si.soni),0)
         FROM im_sotuv_items si JOIN im_sotuvlar s ON s.id=si.sotuv_id
         WHERE $w $osh"
    );
    $returns = im_fifo_report_returns($db, $dan, $gacha, im_ait_int($a, 'filial_id'), !empty($a['faqat_oshxona']));
    $tannarx -= (float)$returns['cost'];
    $tushum -= (float)$returns['revenue'];
    $brutto = $tushum - $tannarx;

    // Harajatlar (sana — DATE ustuni, TIMESTAMP emas)
    $hfil = im_ait_int($a, 'filial_id', 0) > 0 ? ' AND filial_id=' . im_ait_int($a, 'filial_id', 0) : '';
    $harajat = (float)$db->val(
        "SELECT COALESCE(SUM(summa),0) FROM im_harajatlar
         WHERE sana BETWEEN '$dan' AND '$gacha' $hfil"
    );
    $maosh = (float)$db->val(
        "SELECT COALESCE(SUM(summa),0) FROM im_maosh_tarixi
         WHERE sana BETWEEN '$dan' AND '$gacha' $hfil"
    );
    $isrof = (float)$db->val(
        "SELECT COALESCE(SUM(qoldi_porsiya*yakuniy_tannarx),0) FROM im_osh_qozon
         WHERE holat='yopildi' AND qoldi_isrofmi=1 AND sana BETWEEN '$dan' AND '$gacha' $hfil"
    );

    $sof = $brutto - $harajat - $maosh - $isrof;
    return [
        'oraliq'          => "$dan .. $gacha",
        'faqat_oshxona'   => !empty($a['faqat_oshxona']),
        'tushum'          => im_ait_f2($tushum),
        'tannarx'         => im_ait_f2($tannarx),
        'brutto_foyda'    => im_ait_f2($brutto),
        'brutto_margin_f' => $tushum > 0 ? im_ait_f2($brutto / $tushum * 100) : 0,
        'harajat'         => im_ait_f2($harajat),
        'maosh'           => im_ait_f2($maosh),
        'qozon_isrofi'    => im_ait_f2($isrof),
        'sof_foyda'       => im_ait_f2($sof),
        'sof_margin_f'    => $tushum > 0 ? im_ait_f2($sof / $tushum * 100) : 0,
        'izoh'            => 'brutto_foyda = yakuniy tushum - dinamik FIFO tannarxi; '
                           . 'sof_foyda = brutto_foyda - harajat - maosh - qozon isrofi. '
                           . 'Dashboard ham xuddi shu formuladan foydalanadi — raqamlar mos kelishi kerak.'
                           . (!empty($a['faqat_oshxona'])
                              ? ' faqat_oshxona=true: chek chegirmasi va xizmat haqi oshxona qatorlariga mutanosib taqsimlandi.'
                              : ''),
    ];
}

// ─── 3. HARAJAT XULOSASI ────────────────────────────────────
function im_ait_harajat_xulosa($db, array $a) {
    list($dan, $gacha) = im_ait_oraliq($a);
    $f     = im_ait_int($a, 'filial_id', 0);
    $fil   = $f > 0 ? " AND h.filial_id=$f " : '';
    $guruh = im_ait_tanlov($a, 'guruh', ['tur','kun','oy','xodim','tolov'], 'tur');
    $w     = "h.sana BETWEEN '$dan' AND '$gacha' $fil";

    $ifoda = [
        'tur'    => "h.tur",
        'kun'    => "h.sana",
        'oy'     => "DATE_FORMAT(h.sana,'%Y-%m')",
        'xodim'  => "COALESCE((SELECT ism FROM im_xodimlar x WHERE x.id=h.xodim_id),'—')",
        'tolov'  => "h.tolov_turi",
    ];
    $e = $ifoda[$guruh];

    $qatorlar = array_map(function ($r) {
        return ['kalit' => $r['k'], 'soni' => (int)$r['n'], 'summa' => im_ait_f2($r['s'])];
    }, $db->rows("SELECT $e AS k, COUNT(*) n, COALESCE(SUM(h.summa),0) s
                  FROM im_harajatlar h WHERE $w GROUP BY k ORDER BY s DESC LIMIT 60"));

    $jami  = (float)$db->val("SELECT COALESCE(SUM(h.summa),0) FROM im_harajatlar h WHERE $w");
    $maosh = (float)$db->val(
        "SELECT COALESCE(SUM(summa),0) FROM im_maosh_tarixi
         WHERE sana BETWEEN '$dan' AND '$gacha' "
        . ($f > 0 ? " AND filial_id=$f" : '')
    );

    // Eng yirik alohida harajatlar — "pul qayerga ketdi" savoliga
    // guruh jadvali emas, aynan shu ro'yxat javob beradi.
    $yiriklar = array_map(function ($r) {
        return [
            'sana'  => $r['sana'], 'nomi' => $r['nomi'], 'tur' => $r['tur'],
            'summa' => im_ait_f2($r['summa']), 'xodim' => $r['xodim'],
        ];
    }, $db->rows(
        "SELECT h.sana, h.nomi, h.tur, h.summa,
                COALESCE((SELECT ism FROM im_xodimlar x WHERE x.id=h.xodim_id),'—') xodim
         FROM im_harajatlar h WHERE $w ORDER BY h.summa DESC LIMIT 15"
    ));

    return [
        'oraliq'          => "$dan .. $gacha",
        'guruh'           => $guruh,
        'harajat_jami'    => im_ait_f2($jami),
        'maosh_jami'      => im_ait_f2($maosh),
        'jami'            => im_ait_f2($jami + $maosh),
        'taqsimot'        => $qatorlar,
        'eng_yiriklari'   => $yiriklar,
        'izoh'            => 'maosh_jami im_maosh_tarixi dan olindi — u im_harajatlar ichida YO\'Q, ikki marta qo\'shilmaydi.',
    ];
}

// ─── 4. MAHSULOT REYTINGI (ABC tahlil) ──────────────────────
function im_ait_mahsulot_reyting($db, array $a) {
    $sale_cost = im_fifo_sale_unit_cost_sql('si');
    $line_revenue = im_sotuv_qator_tushum_sql('s', 'si');
    list($dan, $gacha) = im_ait_oraliq($a);
    $fil    = im_ait_filial($a);
    $osh    = im_ait_oshxona($a);
    $tartib = im_ait_tanlov($a, 'tartib', ['soni','tushum','foyda','margin'], 'foyda');
    $yon    = im_ait_tanlov($a, 'yonalish', ['top','past'], 'top');
    $limit  = max(1, min(50, im_ait_int($a, 'limit', 15)));
    $kat    = im_ait_int($a, 'kategoriya_id', 0);
    $katw   = $kat > 0 ? " AND m.kategoriya_id=$kat " : '';

    $w = "s.holat IN ('aktiv','qaytarilgan') AND s.sana BETWEEN '$dan 00:00:00' AND '$gacha 23:59:59' $fil";

    $ustun = [
        'soni'   => 'soni',
        'tushum' => 'tushum',
        'foyda'  => 'foyda',
        'margin' => 'margin_f',
    ];
    $yonalish = $yon === 'top' ? 'DESC' : 'ASC';

    $rows = $db->rows(
        "SELECT m.id, m.nomi, m.birlik,
                COALESCE(k.nomi,'—') kategoriya,
                SUM(si.soni) soni,
                SUM($line_revenue) tushum,
                SUM(($line_revenue)-(($sale_cost)*si.soni)) foyda,
                CASE WHEN SUM($line_revenue) > 0
                     THEN SUM(($line_revenue)-(($sale_cost)*si.soni))
                          / SUM($line_revenue) * 100
                     ELSE 0 END margin_f
         FROM im_sotuv_items si
         JOIN im_sotuvlar s   ON s.id = si.sotuv_id
         JOIN im_mahsulotlar m ON m.id = si.mahsulot_id
         LEFT JOIN im_kategoriyalar k ON k.id = m.kategoriya_id
         WHERE $w $osh $katw
         GROUP BY m.id
         ORDER BY {$ustun[$tartib]} $yonalish
         LIMIT $limit"
    );

    $jami_tushum = (float)$db->val(
        "SELECT COALESCE(SUM($line_revenue),0)
         FROM im_sotuv_items si JOIN im_sotuvlar s ON s.id=si.sotuv_id
         JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
         WHERE $w $osh $katw"
    );

    return [
        'oraliq'      => "$dan .. $gacha",
        'tartib'      => $tartib,
        'yonalish'    => $yon,
        'jami_tushum' => im_ait_f2($jami_tushum),
        'qatorlar'    => array_map(function ($r) use ($jami_tushum) {
            return [
                'id'         => (int)$r['id'],
                'nomi'       => $r['nomi'],
                'kategoriya' => $r['kategoriya'],
                'soni'       => im_ait_f2($r['soni']),
                'birlik'     => $r['birlik'],
                'tushum'     => im_ait_f2($r['tushum']),
                'foyda'      => im_ait_f2($r['foyda']),
                'margin_f'   => im_ait_f2($r['margin_f']),
                'ulush_f'    => $jami_tushum > 0 ? im_ait_f2($r['tushum'] / $jami_tushum * 100) : 0,
            ];
        }, $rows),
    ];
}

// ─── 5. TAOM TANNARXI (retsept bo'yicha) ────────────────────
// Sotuvdagi `tannarx` — sotuv paytidagi yozib qo'yilgan qiymat.
// Bu vosita esa BUGUNGI xomashyo narxi bo'yicha qayta hisoblaydi —
// "narx oshdi, taom hali ham foydalimi?" savoliga shu javob beradi.
function im_ait_taom_tannarx($db, array $a) {
    $mid = im_ait_int($a, 'mahsulot_id', 0);
    if ($mid <= 0) return ['xato' => 'mahsulot_id kerak — avval mahsulot_qidir bilan toping'];

    $m = $db->row("SELECT id, nomi, birlik FROM im_mahsulotlar WHERE id=$mid");
    if (!$m) return ['xato' => "mahsulot_id=$mid topilmadi"];

    $r = $db->row(
        "SELECT id, nomi, chiqish_soni, birlik FROM im_retseptlar
         WHERE mahsulot_id=$mid AND status=1 ORDER BY id DESC LIMIT 1"
    );

    // Joriy sotish narxi
    $sotish = (float)$db->val("SELECT sotish_narxi FROM im_narxlar WHERE mahsulot_id=$mid ORDER BY updated_at DESC, id DESC LIMIT 1");
    if ($sotish <= 0) {
        $sotish = (float)$db->val("SELECT COALESCE(MAX(sotuv_narxi),0) FROM im_filial_qoldiq WHERE mahsulot_id=$mid");
    }

    if (!$r) {
        // Retseptsiz tovar — tannarxi kelish narxining o'zi
        try {
            $preview = im_fifo_preview($db, max(0, im_ait_int($a, 'filial_id')), $mid, 1);
            $kelish = (float)$preview['unit_cost'];
        } catch (Throwable $e) {
            return ['mahsulot' => $m['nomi'], 'tannarx' => null, 'xato' => 'FIFO qoldiq yetarli emas'];
        }
        return [
            'mahsulot'     => $m['nomi'],
            'retsept'      => null,
            'tannarx'      => im_ait_f2($kelish),
            'sotish_narxi' => im_ait_f2($sotish),
            'foyda'        => im_ait_f2($sotish - $kelish),
            'margin_f'     => $sotish > 0 ? im_ait_f2(($sotish - $kelish) / $sotish * 100) : 0,
            'izoh'         => 'Retsept yo\'q — tannarx 1 birlik FIFO sarfi bo‘yicha olindi.',
        ];
    }

    $chiqish = max(0.001, (float)$r['chiqish_soni']);
    $items   = $db->rows(
        "SELECT ri.mahsulot_id, ri.soni, ri.birlik, m.nomi
         FROM im_retsept_items ri
         JOIN im_mahsulotlar m ON m.id = ri.mahsulot_id
         WHERE ri.retsept_id = {$r['id']}"
    );

    $jami = 0;
    $tark = [];
    foreach ($items as $it) {
        $bir = (float)$it['soni'] / $chiqish;
        try {
            $preview = $bir > 0 ? im_fifo_preview($db, max(0, im_ait_int($a, 'filial_id')), (int)$it['mahsulot_id'], $bir) : ['cost' => 0, 'unit_cost' => 0];
        } catch (Throwable $e) {
            return ['mahsulot' => $m['nomi'], 'tannarx' => null, 'xato' => 'FIFO xomashyo qoldiq yetarli emas: ' . $it['nomi']];
        }
        $narx = (float)$preview['unit_cost'];
        $sum = (float)$preview['cost'];
        $jami += $sum;
        $tark[] = [
            'xomashyo'    => $it['nomi'],
            'soni'        => im_ait_f2($bir),
            'birlik'      => $it['birlik'],
            'narx'        => im_ait_f2($narx),
            'summa'       => im_ait_f2($sum),
        ];
    }
    // Eng qimmat tarkib birinchi — nimani almashtirish kerakligi darrov ko'rinsin
    usort($tark, function ($x, $y) { return $y['summa'] <=> $x['summa']; });

    return [
        'mahsulot'      => $m['nomi'],
        'retsept'       => $r['nomi'],
        'chiqish_soni'  => im_ait_f2($chiqish),
        'tarkib'        => $tark,
        'tannarx'       => im_ait_f2($jami),
        'sotish_narxi'  => im_ait_f2($sotish),
        'foyda'         => im_ait_f2($sotish - $jami),
        'margin_f'      => $sotish > 0 ? im_ait_f2(($sotish - $jami) / $sotish * 100) : 0,
        'izoh'          => 'Tannarx BUGUNGI xomashyo narxlari bo\'yicha qayta hisoblandi.',
    ];
}

// ─── 6. QOLDIQ HOLATI ───────────────────────────────────────
// Qoldiqning o'zi ma'no bermaydi — "necha kunga yetadi" ma'no
// beradi. Shuning uchun har bir mahsulot uchun oxirgi N kundagi
// o'rtacha kunlik sarf hisoblanadi va qoldiq shunga bo'linadi.
function im_ait_qoldiq_holat($db, array $a) {
    $f     = im_ait_int($a, 'filial_id', 0);
    $fil   = $f > 0 ? " AND fq.filial_id=$f " : '';
    $tur   = im_ait_tanlov($a, 'tur', ['kam','kop','hammasi'], 'kam');
    $kun   = max(3, min(180, im_ait_int($a, 'kun', 30)));
    $limit = max(1, min(60, im_ait_int($a, 'limit', 25)));
    $dan   = date('Y-m-d', strtotime("-" . ($kun - 1) . " days"));

    // Kunlik sarf: sotuvdan (vitrinali tovar) + ishlab chiqarishdan
    // (retseptga kirgan xomashyo). Ikkalasi ham qoldiqni kamaytiradi.
    $sfil = $f > 0 ? " AND s.filial_id=$f "  : '';
    $ifil = $f > 0 ? " AND ic.filial_id=$f " : '';
    $rows = $db->rows(
        "SELECT m.id, m.nomi, m.birlik,
                COALESCE(k.nomi,'—') kategoriya,
                SUM(fq.soni) qoldiq,
                COALESCE((
                    SELECT SUM(si.soni) FROM im_sotuv_items si
                    JOIN im_sotuvlar s ON s.id=si.sotuv_id
                    WHERE si.mahsulot_id=m.id AND s.holat IN ('aktiv','qaytarilgan')
                      AND s.sana >= '$dan 00:00:00' $sfil
                ),0)
                + COALESCE((
                    SELECT SUM(ii.soni) FROM im_ishlab_chiqarish_items ii
                    JOIN im_ishlab_chiqarish ic ON ic.id=ii.ishlab_id
                    WHERE ii.mahsulot_id=m.id AND ii.tur='kirish'
                      AND ic.holat='bajarildi' AND ic.sana >= '$dan 00:00:00' $ifil
                ),0) sarf
         FROM im_filial_qoldiq fq
         JOIN im_mahsulotlar m ON m.id = fq.mahsulot_id
         LEFT JOIN im_kategoriyalar k ON k.id = m.kategoriya_id
         WHERE 1 $fil
         GROUP BY m.id"
    );

    $out = [];
    foreach ($rows as $r) {
        $qoldiq  = (float)$r['qoldiq'];
        $kunlik  = (float)$r['sarf'] / $kun;
        $yetadi  = $kunlik > 0.0001 ? round($qoldiq / $kunlik, 1) : null;
        $out[] = [
            'id'          => (int)$r['id'],
            'nomi'        => $r['nomi'],
            'kategoriya'  => $r['kategoriya'],
            'qoldiq'      => im_ait_f2($qoldiq),
            'birlik'      => $r['birlik'],
            'kunlik_sarf' => im_ait_f2($kunlik),
            'yetadi_kun'  => $yetadi,   // null = sarf yo'q (qotib qolgan)
        ];
    }

    if ($tur === 'kam') {
        // Faqat harakatdagi va tez tugaydiganlar
        $out = array_values(array_filter($out, function ($x) {
            return $x['yetadi_kun'] !== null && $x['yetadi_kun'] <= 7;
        }));
        usort($out, function ($x, $y) { return $x['yetadi_kun'] <=> $y['yetadi_kun']; });
    } elseif ($tur === 'kop') {
        // Qotib qolgan zaxira: sarfi yo'q yoki 60 kundan ortiqqa yetadi
        $out = array_values(array_filter($out, function ($x) {
            return $x['qoldiq'] > 0 && ($x['yetadi_kun'] === null || $x['yetadi_kun'] > 60);
        }));
        usort($out, function ($x, $y) { return $y['qoldiq'] <=> $x['qoldiq']; });
    } else {
        usort($out, function ($x, $y) { return $y['qoldiq'] <=> $x['qoldiq']; });
    }

    return [
        'tur'          => $tur,
        'sarf_oynasi'  => "oxirgi $kun kun",
        'jami_topildi' => count($out),
        'qatorlar'     => array_slice($out, 0, $limit),
        'izoh'         => 'yetadi_kun = qoldiq / kunlik_sarf. null bo\'lsa — bu oraliqda umuman sarflanmagan.',
    ];
}

// ─── 7. XOMASHYO EHTIYOJI (prognoz) ─────────────────────────
// Oxirgi N kun sotuvidan kunlik o'rtacha olinadi, kelgusi K kunga
// ko'paytiriladi, retsept bo'yicha xomashyoga yoyiladi va qoldiq
// bilan solishtiriladi. Natija — "nima, qancha yetishmaydi".
function im_ait_xomashyo_ehtiyoj($db, array $a) {
    $f      = im_ait_int($a, 'filial_id', 0);
    $tarix  = max(7, min(120, im_ait_int($a, 'tarix_kun', 30)));
    $oldi   = max(1, min(30,  im_ait_int($a, 'kun', 3)));
    $dan    = date('Y-m-d', strtotime("-" . ($tarix - 1) . " days"));
    $fil    = $f > 0 ? " AND s.filial_id=$f " : '';

    // 1) Taomlar bo'yicha kunlik o'rtacha sotuv
    $taomlar = $db->rows(
        "SELECT si.mahsulot_id, m.nomi, SUM(si.soni) soni
         FROM im_sotuv_items si
         JOIN im_sotuvlar s    ON s.id = si.sotuv_id
         JOIN im_mahsulotlar m ON m.id = si.mahsulot_id
         WHERE s.holat IN ('aktiv','qaytarilgan') AND s.sana >= '$dan 00:00:00' $fil
         GROUP BY si.mahsulot_id
         HAVING soni > 0
         ORDER BY soni DESC
         LIMIT 200"
    );

    $ehtiyoj = [];   // mahsulot_id => soni
    $taom_pr = [];
    foreach ($taomlar as $t) {
        $mid   = (int)$t['mahsulot_id'];
        $kunl  = (float)$t['soni'] / $tarix;
        $kerak = $kunl * $oldi;
        $taom_pr[] = [
            'taom'        => $t['nomi'],
            'kunlik'      => im_ait_f2($kunl),
            'prognoz'     => im_ait_f2($kerak),
        ];

        $r = $db->row("SELECT id, chiqish_soni FROM im_retseptlar
                       WHERE mahsulot_id=$mid AND status=1 ORDER BY id DESC LIMIT 1");
        if (!$r) {
            // Retseptsiz — o'zi xomashyo sifatida kerak bo'ladi
            $ehtiyoj[$mid] = (isset($ehtiyoj[$mid]) ? $ehtiyoj[$mid] : 0) + $kerak;
            continue;
        }
        $chiqish = max(0.001, (float)$r['chiqish_soni']);
        foreach ($db->rows("SELECT mahsulot_id, soni FROM im_retsept_items WHERE retsept_id={$r['id']}") as $ri) {
            $x = (int)$ri['mahsulot_id'];
            $ehtiyoj[$x] = (isset($ehtiyoj[$x]) ? $ehtiyoj[$x] : 0)
                         + ((float)$ri['soni'] / $chiqish) * $kerak;
        }
    }

    if (!$ehtiyoj) {
        return ['oraliq' => "oxirgi $tarix kun", 'kun' => $oldi,
                'xato' => 'Bu oraliqda sotuv yo\'q — prognoz qurib bo\'lmadi.'];
    }

    // 2) Qoldiq bilan solishtirish
    $ids  = implode(',', array_map('intval', array_keys($ehtiyoj)));
    $qfil = $f > 0 ? " AND fq.filial_id=$f " : '';
    $qold = [];
    foreach ($db->rows("SELECT fq.mahsulot_id, SUM(fq.soni) s FROM im_filial_qoldiq fq
                        WHERE fq.mahsulot_id IN ($ids) $qfil GROUP BY fq.mahsulot_id") as $q) {
        $qold[(int)$q['mahsulot_id']] = (float)$q['s'];
    }
    $nomlar = [];
    foreach ($db->rows("SELECT id, nomi, birlik FROM im_mahsulotlar WHERE id IN ($ids)") as $n) {
        $nomlar[(int)$n['id']] = $n;
    }

    $royxat = [];
    foreach ($ehtiyoj as $mid => $kerak) {
        $bor  = isset($qold[$mid]) ? $qold[$mid] : 0;
        $nomi = isset($nomlar[$mid]) ? $nomlar[$mid]['nomi'] : "#$mid";
        $bir  = isset($nomlar[$mid]) ? $nomlar[$mid]['birlik'] : '';
        $royxat[] = [
            'xomashyo'    => $nomi,
            'birlik'      => $bir,
            'kerak'       => im_ait_f2($kerak),
            'qoldiq'      => im_ait_f2($bor),
            'yetishmaydi' => im_ait_f2(max(0, $kerak - $bor)),
        ];
    }
    // Eng katta taqchillik tepada
    usort($royxat, function ($x, $y) { return $y['yetishmaydi'] <=> $x['yetishmaydi']; });

    return [
        'tarix_oynasi' => "oxirgi $tarix kun",
        'prognoz_kun'  => $oldi,
        'taomlar'      => array_slice($taom_pr, 0, 20),
        'xomashyo'     => array_slice($royxat, 0, 40),
        'izoh'         => 'Prognoz = oxirgi ' . $tarix . ' kun o\'rtachasi × ' . $oldi
                        . ' kun. Bayram/dam olish kuni hisobga olinmaydi — vaqt_tahlil bilan tekshiring.',
    ];
}

// ─── 8. XODIM SAMARADORLIGI ─────────────────────────────────
function im_ait_xodim_samaradorlik($db, array $a) {
    $sale_cost = im_fifo_sale_unit_cost_sql('si');
    $line_revenue = im_sotuv_qator_tushum_sql('s', 'si');
    list($dan, $gacha) = im_ait_oraliq($a);
    $f    = im_ait_int($a, 'filial_id', 0);
    $fil  = $f > 0 ? " AND s.filial_id=$f " : '';
    $rol  = im_ait_tanlov($a, 'rol', ['sotuvchi','kassir','oshpaz','hammasi'], 'hammasi');
    $out  = ['oraliq' => "$dan .. $gacha"];
    $w    = "s.holat IN ('aktiv','qaytarilgan') AND s.sana BETWEEN '$dan 00:00:00' AND '$gacha 23:59:59' $fil";

    // ── Ofitsant (sotuvchi) ──
    // Chek/tushum va foyda ALOHIDA so'raladi: bittasi im_sotuvlar
    // qatorlarini, ikkinchisi im_sotuv_items qatorlarini sanaydi.
    // Bitta JOIN'da yig'ilsa chek soni qator soniga ko'payib ketadi.
    if ($rol === 'sotuvchi' || $rol === 'hammasi') {
        $foyda = [];
        foreach ($db->rows(
            "SELECT s.sotuvchi_id sid,
                    SUM(($line_revenue)-(($sale_cost)*si.soni)) f
             FROM im_sotuv_items si JOIN im_sotuvlar s ON s.id=si.sotuv_id
             WHERE $w AND s.sotuvchi_id > 0 GROUP BY s.sotuvchi_id"
        ) as $r) { $foyda[(int)$r['sid']] = (float)$r['f']; }

        $out['ofitsantlar'] = array_map(function ($r) use ($foyda) {
            $n   = max(1, (int)$r['chek']);
            $sid = (int)$r['sid'];
            return [
                'xodim'        => $r['ism'],
                'chek'         => (int)$r['chek'],
                'tushum'       => im_ait_f2($r['tushum']),
                'ortacha_chek' => im_ait_f2($r['tushum'] / $n),
                'foyda'        => im_ait_f2(isset($foyda[$sid]) ? $foyda[$sid] : 0),
            ];
        }, $db->rows(
            "SELECT s.sotuvchi_id sid,
                    COALESCE(x.ism, CONCAT('#', s.sotuvchi_id)) ism,
                    COUNT(*) chek,
                    COALESCE(SUM(s.tolov_summa),0) tushum
             FROM im_sotuvlar s
             LEFT JOIN im_xodimlar x ON x.id = s.sotuvchi_id
             WHERE $w AND s.sotuvchi_id > 0
             GROUP BY s.sotuvchi_id ORDER BY tushum DESC LIMIT 30"
        ));
    }

    // ── Kassir ──
    if ($rol === 'kassir' || $rol === 'hammasi') {
        $out['kassirlar'] = array_map(function ($r) {
            $n = max(1, (int)$r['chek']);
            return [
                'xodim'        => $r['ism'],
                'chek'         => (int)$r['chek'],
                'tushum'       => im_ait_f2($r['tushum']),
                'ortacha_chek' => im_ait_f2($r['tushum'] / $n),
                'chegirma'     => im_ait_f2($r['chegirma']),
            ];
        }, $db->rows(
            "SELECT COALESCE(x.ism, CONCAT('#', s.kassir_id)) ism, COUNT(*) chek,
                    COALESCE(SUM(s.tolov_summa),0) tushum,
                    COALESCE(SUM(s.chegirma_summa),0) chegirma
             FROM im_sotuvlar s
             LEFT JOIN im_xodimlar x ON x.id = s.kassir_id
             WHERE $w AND s.kassir_id > 0
             GROUP BY s.kassir_id ORDER BY tushum DESC LIMIT 30"
        ));
    }

    // ── Oshpaz: qabuldan tayyorgacha ketgan vaqt ──
    // im_xodim_log da har bosqich vaqti bor: oshpaz_qabul → oshpaz_tayyor.
    if ($rol === 'oshpaz' || $rol === 'hammasi') {
        $lfil = $f > 0 ? " AND q.filial_id=$f " : '';
        $out['oshpazlar'] = array_map(function ($r) {
            return [
                'xodim'          => $r['ism'],
                'buyurtma'       => (int)$r['n'],
                'ort_daqiqa'     => im_ait_f2($r['ort']),
                'eng_uzun_daqiqa'=> im_ait_f2($r['maks']),
            ];
        }, $db->rows(
            "SELECT COALESCE(x.ism, CONCAT('#', q.xodim_id)) ism,
                    COUNT(*) n,
                    AVG(TIMESTAMPDIFF(SECOND, q.vaqt, t.vaqt))/60 ort,
                    MAX(TIMESTAMPDIFF(SECOND, q.vaqt, t.vaqt))/60 maks
             FROM im_xodim_log q
             JOIN im_xodim_log t
               ON t.order_id = q.order_id AND t.amal='oshpaz_tayyor' AND t.vaqt >= q.vaqt
             LEFT JOIN im_xodimlar x ON x.id = q.xodim_id
             WHERE q.amal='oshpaz_qabul'
               AND q.vaqt BETWEEN '$dan 00:00:00' AND '$gacha 23:59:59' $lfil
             GROUP BY q.xodim_id ORDER BY n DESC LIMIT 20"
        ));
        $out['oshpaz_izoh'] = 'ort_daqiqa — buyurtmani QABUL qilgandan TAYYOR deb belgilagangacha o\'tgan vaqt.';
    }

    return $out;
}

// ─── 9. ANOMALIYA / SUIISTE'MOL ─────────────────────────────
// Har bir tekshiruv alohida — "hech narsa topilmadi" ham javob.
// Model o'zi ayblov qo'ymaydi, faqat faktni ko'rsatadi.
function im_ait_anomaliya($db, array $a) {
    $sale_cost = im_fifo_sale_unit_cost_sql('si');
    $line_revenue = im_sotuv_qator_tushum_sql('s', 'si');
    list($dan, $gacha) = im_ait_oraliq($a);
    $f   = im_ait_int($a, 'filial_id', 0);
    $fil = $f > 0 ? " AND s.filial_id=$f " : '';
    $w   = "s.sana BETWEEN '$dan 00:00:00' AND '$gacha 23:59:59' $fil";
    $lim = (float)im_sozlama('kassir_max_chegirma', '10');

    $out = ['oraliq' => "$dan .. $gacha", 'chegirma_limiti_f' => $lim];

    // 1) Ruxsat etilgandan katta chegirma
    $out['katta_chegirma'] = array_map(function ($r) {
        return ['chek' => $r['chek_nomer'], 'sana' => $r['sana'], 'kassir' => $r['kassir'],
                'foiz' => im_ait_f2($r['chegirma_foiz']), 'summa' => im_ait_f2($r['chegirma_summa'])];
    }, $db->rows(
        "SELECT s.chek_nomer, s.sana, s.chegirma_foiz, s.chegirma_summa,
                COALESCE(x.ism,'—') kassir
         FROM im_sotuvlar s LEFT JOIN im_xodimlar x ON x.id=s.kassir_id
         WHERE $w AND s.holat IN ('aktiv','qaytarilgan') AND s.chegirma_foiz > $lim
         ORDER BY s.chegirma_foiz DESC LIMIT 20"
    ));

    // 2) Bekor qilingan / qaytarilgan cheklar
    $out['bekor_cheklar'] = array_map(function ($r) {
        return ['chek' => $r['chek_nomer'], 'sana' => $r['sana'], 'holat' => $r['holat'],
                'summa' => im_ait_f2($r['jami_summa']), 'kassir' => $r['kassir']];
    }, $db->rows(
        "SELECT s.chek_nomer, s.sana, s.holat, s.jami_summa, COALESCE(x.ism,'—') kassir
         FROM im_sotuvlar s LEFT JOIN im_xodimlar x ON x.id=s.kassir_id
         WHERE $w AND s.holat IN ('bekor','qaytarilgan')
         ORDER BY s.jami_summa DESC LIMIT 20"
    ));

    // 3) Zarariga sotilgan qatorlar (narx < tannarx)
    $out['zarariga_sotuv'] = array_map(function ($r) {
        return ['mahsulot' => $r['nomi'], 'soni' => im_ait_f2($r['soni']),
                'narx' => im_ait_f2($r['narx']), 'tannarx' => im_ait_f2($r['tannarx']),
                'zarar' => im_ait_f2($r['zarar'])];
    }, $db->rows(
        "SELECT m.nomi, SUM(si.soni) soni,
                SUM($line_revenue)/NULLIF(SUM(si.soni),0) narx,
                SUM(($sale_cost)*si.soni)/NULLIF(SUM(si.soni),0) tannarx,
                SUM((($sale_cost)*si.soni)-($line_revenue)) zarar
         FROM im_sotuv_items si JOIN im_sotuvlar s ON s.id=si.sotuv_id
         JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
         WHERE $w AND s.holat IN ('aktiv','qaytarilgan')
           AND (($sale_cost)*si.soni) > ($line_revenue)
           AND ($sale_cost) > 0
         GROUP BY m.id ORDER BY zarar DESC LIMIT 15"
    ));

    // 4) Tunda (00:00–06:00) qilingan sotuvlar
    $out['tungi_sotuv'] = array_map(function ($r) {
        return ['sana' => $r['sana'], 'chek' => $r['chek_nomer'],
                'summa' => im_ait_f2($r['tolov_summa']), 'kassir' => $r['kassir']];
    }, $db->rows(
        "SELECT s.sana, s.chek_nomer, s.tolov_summa, COALESCE(x.ism,'—') kassir
         FROM im_sotuvlar s LEFT JOIN im_xodimlar x ON x.id=s.kassir_id
         WHERE $w AND s.holat IN ('aktiv','qaytarilgan') AND HOUR(s.sana) < 6
         ORDER BY s.sana DESC LIMIT 20"
    ));

    // 5) Buyurtmadan olib tashlangan / kamaytirilgan qatorlar
    $lfil = $f > 0 ? " AND l.filial_id=$f " : '';
    $out['buyurtmadan_olindi'] = array_map(function ($r) {
        return ['xodim' => $r['ism'], 'holat' => $r['amal'], 'soni' => (int)$r['n'],
                'mahsulotlar' => $r['nomlar']];
    }, $db->rows(
        "SELECT COALESCE(x.ism, CONCAT('#', l.xodim_id)) ism, l.amal, COUNT(*) n,
                GROUP_CONCAT(DISTINCT l.nomi SEPARATOR ', ') nomlar
         FROM im_order_item_log l LEFT JOIN im_xodimlar x ON x.id=l.xodim_id
         WHERE l.vaqt BETWEEN '$dan 00:00:00' AND '$gacha 23:59:59' $lfil
           AND (l.yangi_soni < l.eski_soni OR l.amal LIKE '%chir%' OR l.amal LIKE '%kamay%')
         GROUP BY l.xodim_id, l.amal ORDER BY n DESC LIMIT 20"
    ));

    // 6) Smena kassa farqi (yopishda sanalgan naqd vs hisoblangan)
    $sfil = $f > 0 ? " AND sm.filial_id=$f " : '';
    $out['smena_kassa_farqi'] = array_map(function ($r) {
        return ['sana' => $r['sana'], 'kassir' => $r['kassir'],
                'kutilgan' => im_ait_f2($r['kutilgan']), 'sanalgan' => im_ait_f2($r['yopish_naqd']),
                'farq' => im_ait_f2($r['farq'])];
    }, $db->rows(
        // Hisoblangan ustunni WHERE'da ishlatib bo'lmaydi, HAVING esa
        // GROUP BY'siz noaniq — shuning uchun tashqi SELECT bilan filtrlaymiz.
        "SELECT * FROM (
            SELECT sm.sana, COALESCE(x.ism,'—') kassir, sm.yopish_naqd,
                   (COALESCE(sm.boshlanish_naqd,sm.ochish_naqd,0) + COALESCE(sm.naqd_kirim,0)) kutilgan,
                   sm.yopish_naqd - (COALESCE(sm.boshlanish_naqd,sm.ochish_naqd,0) + COALESCE(sm.naqd_kirim,0)) farq
            FROM im_smena sm LEFT JOIN im_xodimlar x ON x.id=sm.kassir_id
            WHERE sm.holat='yopiq' AND sm.sana BETWEEN '$dan' AND '$gacha' $sfil
         ) t WHERE ABS(t.farq) > 1000 ORDER BY ABS(t.farq) DESC LIMIT 20"
    ));

    $out['izoh'] = 'Bu ro\'yxatlar SHUBHA emas, FAKT. Sabab bo\'lishi mumkin '
                 . '(mijoz qaytardi, aksiya, tunda ishlaydigan smena). Ayblov qo\'ymang — tekshirishni tavsiya qiling.';
    return $out;
}

// ─── 10. VAQT TAHLILI (soat × hafta kuni) ───────────────────
function im_ait_vaqt_tahlil($db, array $a) {
    list($dan, $gacha) = im_ait_oraliq($a);
    $fil = im_ait_filial($a);
    $w   = "s.holat IN ('aktiv','qaytarilgan') AND s.sana BETWEEN '$dan 00:00:00' AND '$gacha 23:59:59' $fil";

    $soat = array_map(function ($r) {
        return ['soat' => (int)$r['h'], 'chek' => (int)$r['n'], 'tushum' => im_ait_f2($r['s'])];
    }, $db->rows("SELECT HOUR(s.sana) h, COUNT(*) n, COALESCE(SUM(s.tolov_summa),0) s
                  FROM im_sotuvlar s WHERE $w GROUP BY h ORDER BY h"));

    $kun = array_map(function ($r) {
        return ['kun' => $r['d'], 'kun_raqam' => (int)$r['dw'], 'chek' => (int)$r['n'],
                'tushum' => im_ait_f2($r['s'])];
    }, $db->rows("SELECT DAYNAME(s.sana) d, DAYOFWEEK(s.sana) dw, COUNT(*) n,
                         COALESCE(SUM(s.tolov_summa),0) s
                  FROM im_sotuvlar s WHERE $w GROUP BY dw ORDER BY dw"));

    return [
        'oraliq'      => "$dan .. $gacha",
        'soat_boyicha'=> $soat,
        'kun_boyicha' => $kun,
        'izoh'        => 'Xodim jadvali va tayyorgarlik shu taqsimotga qarab tuziladi.',
    ];
}

// ─── 11. MAHSULOT QIDIRISH ──────────────────────────────────
function im_ait_mahsulot_qidir($db, array $a) {
    $q = trim((string)(isset($a['nomi']) ? $a['nomi'] : ''));
    if ($q === '') return ['xato' => 'nomi kerak'];
    $s = im_ait_esc($db, $q);

    return ['topildi' => array_map(function ($r) {
        return [
            'id'          => (int)$r['id'],
            'nomi'        => $r['nomi'],
            'kategoriya'  => $r['kategoriya'],
            'birlik'      => $r['birlik'],
            'oshxona'     => ((int)$r['oshpaz_kerak'] || (int)$r['retsept_avto']) ? true : false,
            'retsept_bor' => (int)$r['retsept'] > 0,
        ];
    }, $db->rows(
        "SELECT m.id, m.nomi, m.birlik, m.oshpaz_kerak, m.retsept_avto,
                COALESCE(k.nomi,'—') kategoriya,
                (SELECT COUNT(*) FROM im_retseptlar r WHERE r.mahsulot_id=m.id AND r.status=1) retsept
         FROM im_mahsulotlar m
         LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
         WHERE m.nomi LIKE '%$s%' AND m.status=1
         ORDER BY m.nomi LIMIT 20"
    ))];
}

// ============================================================
//  VOSITALAR RO'YXATI — Claude uchun JSON Schema
// ------------------------------------------------------------
//  Bu ro'yxat prompt keshining BOSHIDA turadi (tools → system →
//  messages tartibida yuboriladi), shuning uchun u BARQAROR
//  bo'lishi shart: tartibni o'zgartirsangiz kesh buziladi.
// ============================================================

function im_ai_oraliq_schema() {
    return [
        'dan'   => ['type' => 'string', 'description' => "Boshlanish sanasi YYYY-MM-DD, yoki kalit so'z: bugun, kecha, hafta, oy, oldingi_oy, yil, 30kun, 90kun. Standart: joriy oy boshi."],
        'gacha' => ['type' => 'string', 'description' => "Tugash sanasi YYYY-MM-DD yoki 'bugun'. Standart: bugun."],
        'filial_id' => ['type' => 'integer', 'description' => "Filial ID. 0 yoki bo'sh = barcha filiallar."],
    ];
}

function im_ai_vositalar() {
    $or = im_ai_oraliq_schema();

    return [
        [
            'name' => 'sotuv_xulosa',
            'description' => "Berilgan davr uchun sotuv xulosasi: tushum, chek soni, o'rtacha chek, to'lov turlari bo'yicha taqsimot, chegirma, xizmat haqi, bekor qilingan cheklar. Dinamikani kun/oy/soat/hafta kuni/filial/manba bo'yicha guruhlaydi. Pul haqidagi HAR QANDAY savolda birinchi shu chaqiriladi.",
            'input_schema' => [
                'type' => 'object',
                'properties' => $or + [
                    'guruh' => ['type' => 'string', 'enum' => ['yoq','kun','hafta_kuni','soat','oy','filial','manba'],
                                'description' => "Dinamika qanday guruhlansin. Standart: kun."],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'foyda_xulosa',
            'description' => "Foyda hisoboti: tushum, tannarx, brutto foyda va marja, harajatlar, maoshlar, SOF foyda. 'Foyda qancha', 'ishlayapmizmi', 'zarardamizmi' savollariga shu javob beradi.",
            'input_schema' => [
                'type' => 'object',
                'properties' => $or + [
                    'faqat_oshxona' => ['type' => 'boolean', 'description' => "true bo'lsa faqat oshpaz tayyorlaydigan taomlar hisoblanadi (suv, gazak kabi tayyor tovar chiqarib tashlanadi)."],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'harajat_xulosa',
            'description' => "Harajatlar tahlili: tur/kun/oy/xodim/to'lov turi bo'yicha taqsimot, eng yirik 15 ta harajat, alohida maosh summasi. 'Pul qayerga ketdi' savoliga shu javob beradi.",
            'input_schema' => [
                'type' => 'object',
                'properties' => $or + [
                    'guruh' => ['type' => 'string', 'enum' => ['tur','kun','oy','xodim','tolov'],
                                'description' => "Standart: tur."],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'mahsulot_reyting',
            'description' => "Taom/mahsulot reytingi (ABC tahlil): soni, tushumi, foydasi, marjasi va umumiy tushumdagi ulushi. yonalish=past bo'lsa eng yomonlarini beradi — 'qaysi taom zarar keltiryapti' savoli uchun.",
            'input_schema' => [
                'type' => 'object',
                'properties' => $or + [
                    'tartib'        => ['type' => 'string', 'enum' => ['soni','tushum','foyda','margin'], 'description' => "Nima bo'yicha saralansin. Standart: foyda."],
                    'yonalish'      => ['type' => 'string', 'enum' => ['top','past'], 'description' => "top = eng yaxshi, past = eng yomon. Standart: top."],
                    'limit'         => ['type' => 'integer', 'description' => "Nechta qator (1-50). Standart: 15."],
                    'kategoriya_id' => ['type' => 'integer', 'description' => "Faqat shu kategoriya."],
                    'faqat_oshxona' => ['type' => 'boolean', 'description' => "Faqat oshxona taomlari."],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'taom_tannarx',
            'description' => "Bitta taomning retsept bo'yicha BUGUNGI tannarxi: har bir xomashyoning ulushi (eng qimmati birinchi), sotish narxi, foyda va marja. Narx oshirish yoki retsept o'zgartirish tavsiyasi uchun.",
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'mahsulot_id' => ['type' => 'integer', 'description' => "Taom ID — avval mahsulot_qidir bilan toping."],
                ],
                'required' => ['mahsulot_id'],
            ],
        ],
        [
            'name' => 'qoldiq_holat',
            'description' => "Ombor qoldig'i va uning necha kunga yetishi. tur=kam — 7 kundan kam qolganlar (tugash arafasida); tur=kop — qotib qolgan zaxira (60 kundan ortiq yoki umuman sarflanmaydi); tur=hammasi — barchasi.",
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'filial_id' => ['type' => 'integer'],
                    'tur'       => ['type' => 'string', 'enum' => ['kam','kop','hammasi'], 'description' => "Standart: kam."],
                    'kun'       => ['type' => 'integer', 'description' => "Kunlik sarf necha kun tarixidan hisoblansin (3-180). Standart: 30."],
                    'limit'     => ['type' => 'integer', 'description' => "Nechta qator (1-60). Standart: 25."],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'xomashyo_ehtiyoj',
            'description' => "Kelgusi kunlar uchun xomashyo prognozi: oxirgi kunlar sotuvidan taom talabini hisoblab, retseptlar bo'yicha xomashyoga yoyadi va qoldiq bilan solishtiradi. 'Ertaga nima olish kerak' savoliga shu javob beradi.",
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'filial_id' => ['type' => 'integer'],
                    'kun'       => ['type' => 'integer', 'description' => "Necha kunga prognoz (1-30). Standart: 3."],
                    'tarix_kun' => ['type' => 'integer', 'description' => "Necha kunlik tarixdan o'rtacha olinsin (7-120). Standart: 30."],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'xodim_samaradorlik',
            'description' => "Xodimlar samaradorligi: ofitsantlar (chek soni, tushum, o'rtacha chek, foyda), kassirlar (chek, tushum, bergan chegirmasi), oshpazlar (buyurtma soni, qabuldan tayyorgacha o'rtacha daqiqa).",
            'input_schema' => [
                'type' => 'object',
                'properties' => $or + [
                    'rol' => ['type' => 'string', 'enum' => ['sotuvchi','kassir','oshpaz','hammasi'], 'description' => "Standart: hammasi."],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'anomaliya_top',
            'description' => "G'ayrioddiy holatlarni topadi: limitdan katta chegirmalar, bekor/qaytarilgan cheklar, zarariga sotilgan taomlar, tungi (00:00-06:00) sotuvlar, buyurtmadan olib tashlangan qatorlar, smena kassa farqi. Natija ayblov emas — tekshirish uchun ro'yxat.",
            'input_schema' => ['type' => 'object', 'properties' => $or, 'required' => []],
        ],
        [
            'name' => 'vaqt_tahlil',
            'description' => "Sotuvning soat va hafta kuni bo'yicha taqsimoti. Xodim jadvali, tayyorgarlik hajmi va aksiya vaqtini rejalash uchun.",
            'input_schema' => ['type' => 'object', 'properties' => $or, 'required' => []],
        ],
        [
            'name' => 'mahsulot_qidir',
            'description' => "Mahsulot/taomni nomi bo'yicha qidiradi va ID sini qaytaradi. taom_tannarx yoki kategoriya bo'yicha filtrlashdan OLDIN ishlatiladi.",
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'nomi' => ['type' => 'string', 'description' => "Nomning bir qismi, masalan: lag'mon, osh, somsa."],
                ],
                'required' => ['nomi'],
            ],
        ],
    ];
}

// ─── OpenAI/OpenRouter shakli ───────────────────────────────
// Sxema AYNAN bir xil — faqat `function` o'ramiga solinadi.
// (Anthropic: {name, description, input_schema};
//  OpenAI:    {type:function, function:{name, description, parameters}})
function im_ai_vositalar_openai() {
    return array_map(function ($t) {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => $t['name'],
                'description' => $t['description'],
                'parameters'  => $t['input_schema'],
            ],
        ];
    }, im_ai_vositalar());
}

// ─── Vositani bajarish ──────────────────────────────────────
// Model faqat shu ro'yxatdagi nomni chaqira oladi. Boshqa nom
// kelsa — xato matni qaytadi va model o'zini to'g'irlaydi.
function im_ai_vosita_bajar($db, $nom, array $args) {
    $xarita = [
        'sotuv_xulosa'       => 'im_ait_sotuv_xulosa',
        'foyda_xulosa'       => 'im_ait_foyda_xulosa',
        'harajat_xulosa'     => 'im_ait_harajat_xulosa',
        'mahsulot_reyting'   => 'im_ait_mahsulot_reyting',
        'taom_tannarx'       => 'im_ait_taom_tannarx',
        'qoldiq_holat'       => 'im_ait_qoldiq_holat',
        'xomashyo_ehtiyoj'   => 'im_ait_xomashyo_ehtiyoj',
        'xodim_samaradorlik' => 'im_ait_xodim_samaradorlik',
        'anomaliya_top'      => 'im_ait_anomaliya',
        'vaqt_tahlil'        => 'im_ait_vaqt_tahlil',
        'mahsulot_qidir'     => 'im_ait_mahsulot_qidir',
    ];
    if (!isset($xarita[$nom])) {
        return ['xato' => "Noma'lum vosita: $nom"];
    }
    try {
        return call_user_func($xarita[$nom], $db, $args);
    } catch (Throwable $e) {
        error_log('[IMezon AI] vosita xatosi ' . $nom . ': ' . $e->getMessage());
        return ['xato' => 'Vosita bajarilmadi: ' . $e->getMessage()];
    }
}
