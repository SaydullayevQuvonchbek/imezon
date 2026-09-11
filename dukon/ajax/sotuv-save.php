<?php
// ============================================================
//  IMezon — Sotuv Saqlash (To'liq POS checkout)
//  POST JSON yoki form-data
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../qayta-ishlash/ishlab_lib.php'; // im_alacarte_tannarx()
require_once __DIR__ . '/../../oshpaz/qozon_lib.php';
im_rol_check(['kassir']);
$db = new Cyber();

// ── Input ────────────────────────────────────────────────
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true) ?: $_POST;

$smena_id    = (int)($input['smena_id'] ?? 0);
$mijoz_id    = (int)($input['mijoz_id'] ?? 0);
$chegirma_qol= min(100, max(0, (float)($input['chegirma_foiz'] ?? 0)));
$savat       = $input['savat'] ?? [];
$filial_id   = (int)$im_filial_id; // FIFO location must be the current branch
$order_id    = (int)($input['order_id'] ?? 0); // Sotuvchi orderi (ixtiyoriy)


$naqd_summa  = (float)($input['naqd'] ?? 0);
$karta_summa = (float)($input['karta'] ?? 0);
$bank_summa  = (float)($input['bank'] ?? 0);
$usd_summa   = (float)($input['usd'] ?? 0);
$usd_kurs    = (float)($input['usd_kurs'] ?? 0);
$nasiya_summa= (float)($input['nasiya'] ?? 0);
$nasiya_muddat_raw = trim($input['nasiya_muddat'] ?? '');
$voucher_kod = strtoupper(trim($input['voucher_kod'] ?? ''));
$umumiy_chegirma = (float)($input['umumiy_chegirma'] ?? 0);
$usd_qaytim_som  = (float)($input['usd_qaytim_som'] ?? 0);

// ── Validatsiya ───────────────────────────────────────────
if ($filial_id <= 0) im_json('error', 'Filial tanlanmagan');
if (!$smena_id) im_json('error', 'Smena ochilmagan');
if (empty($savat)) im_json('error', 'Savat bo\'sh');

$smena = $db->row("SELECT * FROM im_smena WHERE id=$smena_id AND filial_id=$filial_id AND holat='ochiq'");
if (!$smena) im_json('error', 'Smena topilmadi yoki yopilgan');

// ── MANBA: savdo qayerdan keldi ───────────────────────────
// "Mijoz" (im_mijozlar) va "Manba" — ikki xil narsa:
//   manba  — Stol 5 / Dastavka / Olib ketish / To'g'ridan-to'g'ri (DOIM bor)
//   mijoz  — faqat chegirma toifasi, nasiya yoki vaucher kerak bo'lganda
// Manbani kassirdan OLMAYMIZ — ishonchli manba im_sotuvchi_order.
// Shu tufayli soxta stol yoki soxta xizmat haqi yuborib bo'lmaydi.
$manba_stol_id    = null;
$manba_nomi       = "To'g'ridan-to'g'ri";
$manba_sotuvchi   = null;   // ofitsant — hisobot uchun
$manba_stol_savdo = false;  // xizmat haqi faqat haqiqiy stol savdosiga

if ($order_id > 0) {
    $ord = $db->row(
        "SELECT id, stol_id, mijoz_ism, olib_ketish, sotuvchi_id
         FROM im_sotuvchi_order WHERE id=$order_id AND filial_id=$filial_id"
    );
    if ($ord) {
        $manba_stol_id  = $ord['stol_id'] !== null ? (int)$ord['stol_id'] : null;
        $manba_sotuvchi = (int)$ord['sotuvchi_id'] ?: null;
        if ($manba_stol_id) {
            $stol_nomi = (string)$db->val("SELECT nomi FROM im_stollar WHERE id=$manba_stol_id")
                         ?: (string)$ord['mijoz_ism'];
            // Mustaqil Olib ketish orderi stolga bog'langan bo'lsa ham chek
            // va hisobotda oddiy stol savdosidan aniq ajralib tursin.
            $manba_nomi = $ord['olib_ketish'] ? ($stol_nomi . ' — Olib ketish') : $stol_nomi;
            $manba_stol_savdo = !$ord['olib_ketish'];
        } else {
            $manba_nomi = (string)$ord['mijoz_ism']
                          ?: ($ord['olib_ketish'] ? 'Olib ketish' : 'Dastavka');
        }
    }
}

// Kassir max chegirma limiti
$max_ch = (float)(im_sozlama('kassir_max_chegirma') ?: 10);
if ($chegirma_qol > $max_ch) im_json('error', "Maksimal chegirma: $max_ch%");

// Mijoz chegirmasi
$mijoz_chegirma = 0;
if ($mijoz_id) {
    $mijoz = $db->row(
        "SELECT m.*, t.chegirma_foiz FROM im_mijozlar m
         LEFT JOIN im_mijoz_toifalari t ON t.id=m.toifa_id
         WHERE m.id=$mijoz_id AND m.status=1"
    );
    if ($mijoz) $mijoz_chegirma = (float)$mijoz['chegirma_foiz'];
}

// ── Chek raqam generatsiya ────────────────────────────────
$today = date('Ymd');
$last_chek = $db->val(
    "SELECT chek_nomer FROM im_sotuvlar WHERE chek_nomer LIKE 'NHC-$today-%' ORDER BY id DESC LIMIT 1"
);
$chek_num = $last_chek ? (int)substr($last_chek, -4) + 1 : 1;
$chek_nomer = 'NHC-' . $today . '-' . str_pad($chek_num, 4, '0', STR_PAD_LEFT);

// ── Savat hisoblash ────────────────────────────────────────
$jami_summa     = 0;
$jami_chegirma  = 0;
$items_prepared = [];

// Nasiya tahlili: Variant 1 qoidasi bo'yicha agar qarzdorlik qolsa chegirmalar va vaucherlar nol bo'ladi.
$is_nasiya_sale = ($nasiya_summa > 0);
if ($is_nasiya_sale) {
    $chegirma_qol = 0;
    $mijoz_chegirma = 0;
    $voucher_kod = '';
    $umumiy_chegirma = 0; // Nasiyada umumiy chegirma bo'lmaydi
}

foreach ($savat as $item) {
    $mah_id  = (int)$item['id'];
    $soni    = round((float)$item['soni'], 3);
    if (!is_finite($soni) || $soni <= 0) im_json('error', 'Miqdor musbat bo‘lishi kerak');
    $base_narx = (float)$item['narx'];
    $item_ch_foiz = (float)($item['chegirma_foiz'] ?? 0); // mahsulot/kampaniya chegirmasi
    $is_ulg  = (bool)($item['ulg'] ?? false);
    $tannarx = 0; // Never trust a client cost snapshot.
    $item_set_id = isset($item['set_id']) && (int)$item['set_id'] > 0 ? (int)$item['set_id'] : null;
    $set_id_norm = $item_set_id ?: 0;   // 0 = à la carte qatori

    if (!$mah_id) continue;
    if ($base_narx <= 0) {
        im_json('error', "❌ Mahsulot ID=$mah_id uchun sotuv narxi belgilanmagan (0). Avval narx kiriting!");
    }

    if ($is_nasiya_sale) {
        $item_ch_foiz = 0;
        $is_ulg = false;
    }

    // Chegirma: max(mahsulot kampaniyasi, mijoz toifasi, kassir qo'l)
    $eff_ch = max($item_ch_foiz, $mijoz_chegirma, $chegirma_qol);
    $eff_ch = $is_nasiya_sale ? 0 : min($eff_ch, 100);

    $chegirma_narxi = round($base_narx * (1 - $eff_ch/100), 2);
    $line_total     = $chegirma_narxi * $soni;
    $line_chegirma  = ($base_narx - $chegirma_narxi) * $soni;

    $jami_summa    += $base_narx * $soni;
    $jami_chegirma += $line_chegirma;

    // A-la-carte taom (oshpaz_kerak=1, qozon_rejim=0) zaxirada turmaydi —
    // uning xomashyosi oshpaz "Qabul qildim" bosganda allaqachon sarflangan.
    // Qozon mahsuloti esa bundan farq qiladi: qozon ochilganda tayyor mahsulot
    // FIFO qatlami yaratiladi va sotuv aynan shu tayyor qoldiqdan yechilishi
    // kerak. Uni a-la-carte deb yuborish order_item_id bo'yicha mavjud
    // bo'lmaydigan ishlab-chiqarish yozuvini qidirishga olib keladi.
    // Mahsulot turi:
    //   oshpaz_kerak — oshxona tasdig'i talab qilinadi
    //   qozon_rejim  — tayyor mahsulot qozon FIFO qoldig'idan sotiladi (OSH)
    //   retsept_avto — xomashyo SHU YERDA, sotuv paytida yechiladi (choy)
    $tur = $db->row("SELECT nomi, COALESCE(sotuv_qadami,1) AS sq, COALESCE(oshpaz_kerak,0) AS ok, COALESCE(retsept_avto,0) AS ra, COALESCE(qozon_rejim,0) AS qr
                     FROM im_mahsulotlar WHERE id=$mah_id");
    $sotuv_qadami = max(0.001, (float)($tur['sq'] ?? 1));
    if (abs(($soni / $sotuv_qadami) - round($soni / $sotuv_qadami)) > 0.0001) {
        im_json('error', "«{$tur['nomi']}» miqdori {$sotuv_qadami} qadam bilan kiritilishi kerak");
    }
    $needs_kitchen  = (int)($tur['ok'] ?? 0) === 1;
    $is_qozon       = (int)($tur['qr'] ?? 0) === 1;
    $is_alacarte    = $needs_kitchen && !$is_qozon;
    $is_retsept_avto= (int)($tur['ra'] ?? 0) === 1;
    $zaxirasiz      = $is_alacarte || $is_retsept_avto;

    // Shu qatordan nechtasi saboyga olinadi? Miqdorni MIJOZ SAVATIDAN emas,
    // buyurtma yozuvidan olamiz — kassir uni o'zgartirib xizmat haqidan
    // qochib qololmasin. Buyurtmasiz (to'g'ridan-to'g'ri) savdoda xizmat
    // haqi umuman yo'q, shuning uchun 0.
    $oi = null;
    $item_olib_ketish = 0.0;
    $item_tayyorlandi = 0.0;
    if ($order_id > 0) {
        $oi = $db->row(
            "SELECT id, COALESCE(olib_ketish_soni,0) AS ok, COALESCE(tayyorlandi_soni,0) AS ty
             FROM im_sotuvchi_order_item
             WHERE order_id=$order_id AND mahsulot_id=$mah_id AND COALESCE(set_id,0)=$set_id_norm LIMIT 1"
        );
        // Kassir miqdorni kamaytirgan bo'lishi mumkin — saboy qismi
        // sotilayotgan miqdordan oshib ketmasin
        $item_olib_ketish = max(0, min((float)($oi['ok'] ?? 0), $soni));
        $item_tayyorlandi = (float)($oi['ty'] ?? 0);
    }

    // ── HIMOYA: pishirilmagan taomni sotib bo'lmaydi ───────────
    // oshpaz_kerak mahsulotning xomashyosi FAQAT oshpaz "Qabul qildim"
    // bosganda yechiladi. Agar u bosilmagan bo'lsa, sotuv ham qoldiqni
    // kamaytirmaydi (a-la-carte) — natijada taom hisobda umuman iz
    // qoldirmasdan sotilib ketardi. Shuning uchun sotish faqat oshpaz
    // TAYYORLAGAN miqdorgacha ruxsat etiladi.
    if ($needs_kitchen) {
        $mah_nomi = (string)$db->val("SELECT nomi FROM im_mahsulotlar WHERE id=$mah_id");
        if ($order_id <= 0) {
            im_json('error',
                "«{$mah_nomi}» — oshxona taomi. Uni to'g'ridan-to'g'ri sotib bo'lmaydi: "
                . "avval Dastavka/Olib ketish yoki stol buyurtmasi ochib, oshpaz tayyorlashi kerak.");
        }
        if ($item_tayyorlandi + 0.0001 < $soni) {
            im_json('error',
                "«{$mah_nomi}» hali oshxonada tayyorlanmagan (tayyor: {$item_tayyorlandi}, sotilmoqchi: {$soni}). "
                . 'Oshpaz "Qabul qildim" va "Tayyor" bosgandan keyin soting.');
        }
    }

    // ── REZERV hisobga olinadi ─────────────────────────────────
    // Ofitsant buyurtmani saqlaganda vitrinali mahsulot qoldiqdan
    // ALLAQACHON ayirilgan (band qilingan — config.php: im_rezerv).
    // Shuning uchun sotuvda faqat rezervdan ORTIQCHA qism yechiladi.
    // Masalan: buyurtmada 2 ta band, kassir 3 taga oshirgan bo'lsa —
    // qo'shimcha 1 ta yechiladi.
    $rezervlangan = 0.0;
    if ($order_id > 0 && !$zaxirasiz) {
        $rezervlangan = (float)$db->val(
            "SELECT COALESCE(rezerv_soni,0) FROM im_sotuvchi_order_item
             WHERE order_id=$order_id AND mahsulot_id=$mah_id AND COALESCE(set_id,0)=$set_id_norm LIMIT 1"
        );
    }
    $qoldiqdan_kerak = max(0, $soni - $rezervlangan);

    if (!$zaxirasiz && $qoldiqdan_kerak > 0) {
        $filial_mavjud = (float)$db->val(
            "SELECT COALESCE(soni,0) FROM im_filial_qoldiq WHERE filial_id=$filial_id AND mahsulot_id=$mah_id"
        );
        if ($filial_mavjud + 0.0001 < $qoldiqdan_kerak) {
            $auto = im_auto_maydalash_holati($db, $filial_id, $mah_id);
            if ($auto && (float)$auto['jami_imkon'] + 0.0001 >= $qoldiqdan_kerak) {
                // Tranzaksiya ichida haqiqiy maydalash bajariladi.
            } else {
            $mah_nomi2 = (string)$db->val("SELECT nomi FROM im_mahsulotlar WHERE id=$mah_id");
                $izoh = $auto
                    ? ", «{$auto['kirish_nomi']}» ham avtomatik maydalash uchun yetarli emas"
                    : '';
                im_json('error', "«{$mah_nomi2}» uchun filialda yetarli qoldiq yo'q "
                    . "(mavjud: $filial_mavjud, qo'shimcha kerak: $qoldiqdan_kerak{$izoh})");
            }
        }
    }

    $items_prepared[] = [
        'mah_id'         => $mah_id,
        'order_item_id'  => (int)($oi['id'] ?? 0),
        'qozon'          => $is_qozon,
        'soni'           => $soni,
        'sotish_narxi'   => $base_narx,
        'chegirma_foiz'  => $eff_ch,
        'chegirma_narxi' => $chegirma_narxi,
        'tannarx'        => $tannarx,
        'line_total'     => $line_total,
        'set_id'         => $item_set_id,
        'alacarte'       => $is_alacarte,
        'retsept_avto'   => $is_retsept_avto,
        'zaxirasiz'      => $zaxirasiz,
        'qoldiqdan_kerak'=> $qoldiqdan_kerak,   // rezervdan ortiqcha qism
        'olib_ketish'    => $item_olib_ketish,
    ];
}

if (empty($items_prepared)) im_json('error', "Savat bo'sh yoki mahsulotlar narxi belgilanmagan");

$tolov_summa  = $jami_summa - $jami_chegirma;

// ── Voucher tekshiruvi (amal qiladimi?) ────────────────────
// DIQQAT: bu qism ATAYLAB im_sotuv_yakun_hisobla() ICHIDA emas — DB
// so'rovi va im_json() bilan to'xtatib qo'yish bor, ya'ni "sof funksiya"
// emas. Haqiqiy chegirma HISOBI (foiz/summa, max_chegirma) esa pastdagi
// yagona-manba funksiyada — u yerda config.php dagi izohga qarang.
$voucher_row = null;
$today = date('Y-m-d');
if ($voucher_kod) {
    $vkod_s = mysqli_real_escape_string($link, $voucher_kod);
    $voucher_row = $db->row("SELECT * FROM im_voucher WHERE kod='$vkod_s' AND status='aktiv'");
    if (!$voucher_row) im_json('error', "Voucher topilmadi: $voucher_kod");
    if ($voucher_row['tugash'] && $voucher_row['tugash'] < $today)
        im_json('error', 'Voucher muddati tugagan');
    if ($voucher_row['boshlanish'] && $voucher_row['boshlanish'] > $today)
        im_json('error', 'Voucher hali faol emas');
    $qoldi = $voucher_row['umumiy_soni'] - $voucher_row['ishlatilgan'];
    if ($voucher_row['umumiy_soni'] > 0 && $qoldi <= 0)
        im_json('error', 'Voucher limiti tugagan');
    if ($voucher_row['min_summa'] > 0 && $tolov_summa < $voucher_row['min_summa'])
        im_json('error', sprintf('Voucher uchun minimal summa: %s so\'m', im_money($voucher_row['min_summa'])));
}

// ── Xizmat haqi (otsluga) — foiz aniqlanadi ────────────────
// Foizni ADMIN belgilaydi (im_sozlamalar.xizmat_foiz). Kassir uni faqat
// YOQA yoki O'CHIRA oladi — ixtiyoriy foiz yubora olmaydi, shuning uchun
// kelgan qiymatni sozlamadagi foizga "qisamiz". Xizmat haqi FAQAT
// haqiqiy stol savdosiga qo'llanadi — buni ham server o'zi hal qiladi
// ($manba_stol_savdo), kassirning so'roviga ishonmaydi.
$xizmat_sozlama  = max(0, min(100, (float)(im_sozlama('xizmat_foiz') ?: 0)));
$xizmat_yoqilgan = ((float)($input['xizmat_foiz'] ?? 0) > 0) && $manba_stol_savdo;
$xizmat_foiz     = $xizmat_yoqilgan ? $xizmat_sozlama : 0;

// Xizmat haqi ulushi uchun: qator summalaridan FAQAT stolda iste'mol
// qilingan (saboyga olinmagan) qism ajratiladi. Mijoz stolda o'tirib,
// ustiga 1 porsiyani saboyga olsa — o'sha porsiyaga stol xizmati
// ko'rsatilmagan, demak undan otsluga olinmaydi.
$qator_jami  = 0.0;
$qator_stol  = 0.0;
foreach ($items_prepared as $ip) {
    $qator_jami += $ip['line_total'];
    if ($ip['soni'] > 0) {
        $stol_soni = max(0, $ip['soni'] - (float)$ip['olib_ketish']);
        $qator_stol += $ip['line_total'] * ($stol_soni / $ip['soni']);
    }
}

// ── Yakuniy hisob: voucher → xizmat haqi → umumiy chegirma ─
// Tartib va formulalar config.php dagi im_sotuv_yakun_hisobla()da —
// dukon/pos.php dagi calcTotals() (JS) bilan bir xil bo'lishi shart.
$hisob = im_sotuv_yakun_hisobla(
    $jami_summa, $jami_chegirma, $tolov_summa,
    $voucher_row ? [
        'tur'          => $voucher_row['tur'],
        'qiymat'       => $voucher_row['qiymat'],
        'max_chegirma' => $voucher_row['max_chegirma'],
    ] : null,
    $xizmat_foiz, $qator_jami, $qator_stol,
    $umumiy_chegirma, $is_nasiya_sale
);
$voucher_chegirma = $hisob['voucher_chegirma'];
$jami_chegirma    = $hisob['jami_chegirma'];
$tolov_summa      = $hisob['tolov_summa'];
$eff_ch_foiz      = $hisob['eff_ch_foiz'];
$xizmat_summa     = $hisob['xizmat_summa'];
$umumiy_chegirma  = $hisob['umumiy_chegirma'];

// USD so'm ekvivalenti
$usd_som = round($usd_summa * $usd_kurs);

// To'lov tekshiruvi
// USD bo'lsa kurs kasr tufayli ±100 so'm farq normal hisoblanadi
$total_tolov = $naqd_summa + $karta_summa + $bank_summa + $usd_som + $nasiya_summa;
$tolerans = $usd_summa > 0 ? 200 : 1; // USD bo'lsa katta tolerans
$farq = $total_tolov - $tolov_summa;   // Manfiy = kam to'langan, musbat = ortiqcha

if ($farq < -$tolerans) {
    // Kam to'langan — bu xato (umumiy chegirma hisobga olinganidan keyin)
    im_json('error', "To'lov summasi yetarli emas (kam: " . im_money(abs($farq)) . " so'm)");
} elseif ($farq > $tolerans && $usd_summa <= 0) {
    // Ortiqcha to'langan — naqd qaytim
}
// USD ortiqcha bo'lsa — qaytim sumdagi: $usd_qaytim_som miqdori naqd kassadan ayriladi

// ── Tranzaksiya ───────────────────────────────────────────
$db->begin();
try {
    if (!function_exists('im_fifo_take')) throw new Exception('FIFO engine yuklanmagan');
    // Stol qatori order yaratish/tahrirlash/bekor qilish bilan umumiy mutex.
    // Uni qoldiq va orderdan oldin qulflash stol bo'shashi bilan yangi order
    // ochilishi orasidagi poygani yopadi.
    if ($manba_stol_id) {
        $locked_table = $db->row(
            "SELECT id FROM im_stollar
             WHERE id=$manba_stol_id AND filial_id=$filial_id FOR UPDATE"
        );
        if (!$locked_table) throw new Exception('Buyurtma stoli topilmadi');
    }
    // Available stock rows must be locked before any FIFO layer or source locks.
    $db->rows("SELECT mahsulot_id FROM im_filial_qoldiq WHERE filial_id=$filial_id ORDER BY mahsulot_id FOR UPDATE");
    // Serialize checkout with kitchen/order updates and prevent paying an order twice.
    if ($order_id > 0) {
        $locked_order = $db->row("SELECT * FROM im_sotuvchi_order WHERE id=$order_id AND filial_id=$filial_id FOR UPDATE");
        if (!$locked_order || !in_array($locked_order['status'], ['tasdiqlandi', 'qabul'], true)) {
            throw new Exception('Buyurtma kassaga tayyor emas, yopilgan yoki topilmadi');
        }
        $locked_stol = $locked_order['stol_id'] !== null ? (int)$locked_order['stol_id'] : null;
        if ($locked_stol !== $manba_stol_id
            || (int)$locked_order['olib_ketish'] !== (int)($ord['olib_ketish'] ?? 0)) {
            throw new Exception('Buyurtma turi yoki stoli boshqa qurilmada o‘zgargan');
        }
        if ($db->row("SELECT id FROM im_sotuvlar WHERE order_id=$order_id LIMIT 1 FOR UPDATE")) {
            throw new Exception('Buyurtma allaqachon sotilgan');
        }
    }
    $mj_s   = $mijoz_id ?: 'NULL';
    $chek_s = mysqli_real_escape_string($link, $chek_nomer);

    // Manba maydonlari
    $ord_s   = $order_id > 0        ? $order_id       : 'NULL';
    $stol_s  = $manba_stol_id       ? $manba_stol_id  : 'NULL';
    $sotch_s = $manba_sotuvchi      ? $manba_sotuvchi : 'NULL';
    $manba_s = mysqli_real_escape_string($link, mb_substr($manba_nomi, 0, 60, 'UTF-8'));

    // Naqd qaytim hisoblash (agar faqat naqd to'lov bo'lsa)
    $naqd_berildi_sum = 0;
    if ($naqd_summa > 0 && $karta_summa <= 0 && $bank_summa <= 0 && $usd_summa <= 0 && $nasiya_summa <= 0) {
        $naqd_berildi_sum = max(0, $naqd_summa - $tolov_summa);
    }

    // Sotuv yozuvi
    $sotuv_id = $db->insert(
        "INSERT INTO im_sotuvlar
            (chek_nomer, smena_id, mijoz_id, kassir_id, filial_id,
             sotuvchi_id, order_id, stol_id, manba,
             naqd_summa, karta_summa, bank_summa,
             usd_summa, usd_kurs, usd_som_ekviv, nasiya_summa,
             jami_summa, chegirma_foiz, chegirma_summa,
             xizmat_foiz, xizmat_summa, tolov_summa,
             naqd_berildi, usd_qaytim_som)
         VALUES
            ('$chek_s', $smena_id, $mj_s, $im_user_id, $filial_id,
             $sotch_s, $ord_s, $stol_s, '$manba_s',
             $naqd_summa, $karta_summa, $bank_summa,
             $usd_summa, $usd_kurs, $usd_som, $nasiya_summa,
             $jami_summa, $eff_ch_foiz, $jami_chegirma,
             $xizmat_foiz, $xizmat_summa, $tolov_summa,
             $naqd_berildi_sum, $usd_qaytim_som)"
    );
    if (!$sotuv_id) throw new Exception('Sotuv yozuvida xatolik: ' . $db->error());

    // Create the actual sale line BEFORE taking stock: allocation sourceId is its ID.
    $used_order_items = [];
    foreach ($items_prepared as $item) {
        $mah_id = (int)$item['mah_id'];
        $kerak = (float)$item['soni'];
        $oi = null;
        if ($order_id > 0) {
            $set_norm = (int)$item['set_id'];
            $matches = $db->rows("SELECT * FROM im_sotuvchi_order_item
                WHERE order_id=$order_id AND mahsulot_id=$mah_id AND COALESCE(set_id,0)=$set_norm FOR UPDATE");
            if (count($matches) !== 1) throw new Exception('Buyurtma qatori aniq emas');
            $oi = $matches[0];
            $oi_id = (int)$oi['id'];
            if (isset($used_order_items[$oi_id])) throw new Exception('Buyurtma qatori takrorlangan');
            $used_order_items[$oi_id] = true;
        }
        $set_id_val = $item['set_id'] ? (int)$item['set_id'] : 'NULL';
        $return_mode = !empty($item['zaxirasiz']) || !empty($item['qozon']) ? 'waste' : 'stock';
        $sale_item_id = $db->insert(
            "INSERT INTO im_sotuv_items
                (sotuv_id, set_id, mahsulot_id, partiya_item_id, soni,
                 sotish_narxi, chegirma_foiz, chegirma_narxi, tannarx, fifo_return_mode, olib_ketish_soni)
             VALUES ($sotuv_id, $set_id_val, $mah_id, NULL, $kerak,
                 {$item['sotish_narxi']}, {$item['chegirma_foiz']},
                 {$item['chegirma_narxi']}, 0, '$return_mode', " . (float)$item['olib_ketish'] . ")"
        );
        if (!$sale_item_id) throw new Exception('Sotuv qatori saqlanmadi: ' . $db->error());

        $cost_total = 0.0;
        if (!empty($item['alacarte'])) {
            $prepared = (float)($oi['tayyorlandi_soni'] ?? 0);
            // Selling only part would need an explicit production-cost allocation API.
            if (!$oi || abs($prepared - $kerak) > 0.000001 || abs((float)$oi['soni'] - $kerak) > 0.000001) {
                throw new Exception('Oshxona qatori tayyorlangan to‘liq miqdorda sotilishi kerak');
            }
            $production = $db->rows("SELECT id, tannarx FROM im_ishlab_chiqarish
                WHERE order_item_id=$oi_id AND filial_id=$filial_id AND mahsulot_id=$mah_id
                  AND holat='bajarildi' ORDER BY id FOR UPDATE");
            if (!$production) throw new Exception('Oshxona qatorining qayd etilgan FIFO tannarxi topilmadi');
            foreach ($production as $record) {
                if ($record['tannarx'] === null) throw new Exception('Ishlab chiqarish tannarxi yo‘q');
                $cost_total += (float)$record['tannarx'];
            }
            // Ingredients were already consumed under source retsept / production ID.
        } elseif (!empty($item['retsept_avto'])) {
            $recipe = $db->row("SELECT id, chiqish_soni FROM im_retseptlar
                WHERE mahsulot_id=$mah_id AND tur='ishlab_chiqarish' AND status=1
                ORDER BY id DESC LIMIT 1 FOR UPDATE");
            if (!$recipe || (float)$recipe['chiqish_soni'] <= 0) throw new Exception('Faol retsept yoki chiqish miqdori yo‘q');
            $recipe_id = (int)$recipe['id'];
            $inputs = $db->rows("SELECT mahsulot_id, SUM(soni) AS soni FROM im_retsept_items
                WHERE retsept_id=$recipe_id GROUP BY mahsulot_id ORDER BY mahsulot_id");
            if (!$inputs) throw new Exception('Retsept tarkibi bo‘sh');
            foreach ($inputs as $ingredient) {
                $qty = round((float)$ingredient['soni'] * $kerak / (float)$recipe['chiqish_soni'], 6);
                if ($qty <= 0) throw new Exception('Retsept xomashyo miqdori noto‘g‘ri');
                $take = im_fifo_take($db, $filial_id, (int)$ingredient['mahsulot_id'], $qty, 'sotuv', $sale_item_id);
                if (!isset($take['total'])) throw new Exception('FIFO total API javobi yo‘q');
                $cost_total += (float)$take['total'];
            }
        } else {
            // Release this order's legacy reservation before FIFO becomes stock authority.
            if ($oi && (float)$oi['rezerv_soni'] > 0) {
                if (!im_rezerv($db, $filial_id, $mah_id, -(float)$oi['rezerv_soni'])) {
                    throw new Exception('Rezervni bo‘shatib bo‘lmadi');
                }
                if (!$db->q("UPDATE im_sotuvchi_order_item SET rezerv_soni=0 WHERE id=$oi_id")) {
                    throw new Exception('Rezerv holati saqlanmadi');
                }
            }
            // A pot estimate may yield a few more physical servings. Extend the
            // open pot just enough for the observed sale; final cost is sealed
            // when the pot is closed from sold + physically remaining portions.
            if (!empty($item['qozon'])) {
                im_qozon_sotuvga_yetkaz($db, $filial_id, $mah_id, $kerak);
            }
            if (empty($item['qozon'])) {
                // Tayyor alohida SKU yetmasa, masalan Non chorak uchun
                // Non butun avtomatik maydalab qoldiq hosil qilinadi.
                im_auto_maydalash_yetkaz($db, $filial_id, $mah_id, $kerak, (int)$im_user_id);
            }
            $take = im_fifo_take($db, $filial_id, $mah_id, $kerak, 'sotuv', $sale_item_id);
            if (!isset($take['total'])) throw new Exception('FIFO total API javobi yo‘q');
            $cost_total = (float)$take['total'];
            if (!empty($item['qozon'])) {
                $qozon_ids = [];
                foreach ($take['allocations'] as $allocation) {
                    if (($allocation['source'] ?? '') === 'qozon') {
                        $qozon_ids[(int)$allocation['source_id']] = true;
                    }
                }
                // Direct link is convenient for the normal one-pot case. When
                // a line spans pots, FIFO movements retain the full allocation.
                if (count($qozon_ids) === 1) {
                    $qozon_id = (int)array_key_first($qozon_ids);
                    $db->q("UPDATE im_sotuv_items SET qozon_id=$qozon_id WHERE id=$sale_item_id");
                }
            }
        }
        if (!is_finite($cost_total) || $cost_total < 0) throw new Exception('FIFO tannarxi noto‘g‘ri');
        $unit_cost = number_format($cost_total / $kerak, 6, '.', '');
        if (!$db->q("UPDATE im_sotuv_items SET tannarx=$unit_cost WHERE id=$sale_item_id")) {
            throw new Exception('FIFO tannarxi saqlanmadi: ' . $db->error());
        }
    }

    // Nasiya yozuvi
    if ($nasiya_summa > 0 && $mijoz_id) {
        // Qaytarish sanasini tekshirish: jo'natilgan bo'lsa va kelajak sana bo'lsa ishlatish
        $today_str = date('Y-m-d');
        $qaytarish_sana = date('Y-m-d', strtotime('+30 days')); // default
        if ($nasiya_muddat_raw && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nasiya_muddat_raw)) {
            if ($nasiya_muddat_raw > $today_str) {
                $qaytarish_sana = $nasiya_muddat_raw;
            }
        }
        $db->insert(
            "INSERT INTO im_nasiya
                (sotuv_id, mijoz_id, qarz_summa, tolangan, qoldiq, qaytarish_sana, holat, kassir_id)
             VALUES
                ($sotuv_id, $mijoz_id, $nasiya_summa, 0, $nasiya_summa, '$qaytarish_sana', 'aktiv', $im_user_id)"
        );
        // Mijoz nasiya qoldig'ini yangilash
        $db->q("UPDATE im_mijozlar SET nasiya_qoldiq=nasiya_qoldiq+$nasiya_summa WHERE id=$mijoz_id");
    }

    // Mijoz jami xarid yangilash
    if ($mijoz_id) {
        $db->q("UPDATE im_mijozlar SET jami_xarid=jami_xarid+$tolov_summa WHERE id=$mijoz_id");
    }

    // Filial kassasini yangilash (har bir filial o'z kassasiga)
    // INSERT ON DUPLICATE KEY UPDATE — agar filial uchun kassa qatori yo'q bo'lsa, avtomatik yaratadi
    $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans)
            VALUES ($filial_id, $naqd_summa, $karta_summa, $bank_summa, $usd_summa)
            ON DUPLICATE KEY UPDATE
                naqd_balans  = naqd_balans  + $naqd_summa,
                karta_balans = karta_balans + $karta_summa,
                bank_balans  = bank_balans  + $bank_summa,
                usd_balans   = usd_balans   + $usd_summa");
    // Tekshiruv: kassa yangilangan bo'lishi shart
    if ($db->affected() < 1) {
        error_log("[IMezon] KASSA YANGILANMADI! filial_id=$filial_id sotuv_id=(yangi) naqd=$naqd_summa bank=$bank_summa karta=$karta_summa");
    }
    // Yagona biznes kassasi (filial_id=0) ham yangilanadi
    // Olib tashlandi: Faqat inkassatsiya orqali yagona kassaga tushadi

    // USD qaytim — sumdagi qaytim naqd kassadan ayriladi
    if ($usd_qaytim_som > 0) {
        $db->q("UPDATE im_kassa SET naqd_balans = naqd_balans - $usd_qaytim_som WHERE filial_id=$filial_id");
    }

    // Balans logi — har bir to'lov turi uchun (filial_id saqlanadi, qaysi filialdan kelgani uchun)
    if ($naqd_summa > 0) {
        $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,xodim_id)
                VALUES ('kirim','sotuv_naqd',$naqd_summa,$sotuv_id,'sotuv',$filial_id,$im_user_id)");
    }
    if ($karta_summa > 0) {
        $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,xodim_id)
                VALUES ('kirim','sotuv_karta',$karta_summa,$sotuv_id,'sotuv',$filial_id,$im_user_id)");
    }
    if ($bank_summa > 0) {
        $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,xodim_id)
                VALUES ('kirim','sotuv_bank',$bank_summa,$sotuv_id,'sotuv',$filial_id,$im_user_id)");
    }
    if ($usd_summa > 0) {
        $usd_som_log = $usd_summa * $usd_kurs;
        $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,summa_usd,usd_kurs,manba_id,manba_tur,filial_id,xodim_id)
                VALUES ('kirim','sotuv_usd',$usd_som_log,$usd_summa,$usd_kurs,$sotuv_id,'sotuv',$filial_id,$im_user_id)");
    }
    // USD qaytim logi
    if ($usd_qaytim_som > 0) {
        $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,xodim_id,izoh)
                VALUES ('chiqim','usd_qaytim',$usd_qaytim_som,$sotuv_id,'sotuv',$filial_id,$im_user_id,'USD qaytim (sumdagi)')");
    }
    // Nasiya — kassaga qo'shilmaydi, faqat log (nasiya_tolov kelinganda qo'shiladi)
    // nasiya_summa kassa ta'sir qilmaydi

    // Voucher - log va counter
    if ($voucher_row) {
        $v_id = $voucher_row['id'];
        $mj_v = $mijoz_id ?: 'NULL';
        $db->q("UPDATE im_voucher SET ishlatilgan=ishlatilgan+1 WHERE id=$v_id");
        
        if ($voucher_row['tur'] === 'summa') {
            $db->q("UPDATE im_voucher SET qiymat = qiymat - $voucher_chegirma WHERE id=$v_id");
            $db->q("UPDATE im_voucher SET status='tugagan' WHERE id=$v_id AND qiymat <= 0");
        }
        
        $db->q("UPDATE im_voucher SET status='tugagan' WHERE id=$v_id AND umumiy_soni>0 AND ishlatilgan>=umumiy_soni");
        $db->q("INSERT INTO im_voucher_ishlatish (voucher_id,sotuv_id,mijoz_id,chegirma_summa,asl_summa,tolov_summa,kassir_id,filial_id)
                VALUES ($v_id,$sotuv_id,$mj_v,$voucher_chegirma,$jami_summa,$tolov_summa,$im_user_id,$filial_id)");
    }

    // Sotuvchi orderini tugallandi deb belgilash
    if ($order_id > 0) {
        $db->q("UPDATE im_sotuvchi_order
                SET status='tugallandi', updated_at=NOW()
                WHERE id=$order_id AND filial_id=$filial_id
                  AND status NOT IN ('bekor')");
    }

    $db->commit();

    im_json('ok', 'Sotuv amalga oshirildi!', [
        'sotuv_id'    => $sotuv_id,
        'order_id'    => $order_id,
        'chek_nomer'  => $chek_nomer,
        'jami_summa'  => $jami_summa,
        'chegirma'    => $jami_chegirma,
        'xizmat_foiz'  => $xizmat_foiz,
        'xizmat_summa' => $xizmat_summa,
        'manba'        => $manba_nomi,
        'tolov_summa' => $tolov_summa,
        'qayta_pul'   => max(0, $naqd_summa - ($tolov_summa - $karta_summa - $bank_summa - $usd_som - $nasiya_summa)),
        'usd_qaytim_som' => $usd_qaytim_som,
    ]);

} catch (Throwable $e) {
    $db->rollback();
    im_json('error', 'Tranzaksiya xatosi: ' . $e->getMessage());
}
