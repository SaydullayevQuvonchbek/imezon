<?php
// ============================================================
//  IMezon — Sotuvchi: Order saqlash
//  action=hold   => Stol ochib qoladi, oshpazga yangi qism boradi
//  action=create => Stol yopilib kassaga o'tadi
//  action=cancel => Bekor qilish
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
// Admin/kassir ham stol buyurtmalariga mahsulot qo'shib/o'chira olishi kerak
// (dukon POS zal xaritasi orqali) — bekor qilish esa hamon faqat
// dukon/ajax/pending-orders.php orqali (pastdagi action=cancel bloki
// hech kimga ruxsat bermaydi, rolidan qat'iy nazar).
im_rol_check(['sotuvchi', 'admin', 'kassir']);

header('Content-Type: application/json; charset=utf-8');

$db = new Cyber();
$action = $_POST['action'] ?? '';
$sotuvchi_id = (int) $_SESSION['im_user_id'];
$sotuvchi_ism = mysqli_real_escape_string($link, $_SESSION['im_ism'] ?? 'Sotuvchi');
$filial_id = (int) $_SESSION['im_filial_id'];

// ── Xodim log yozish (order darajasi) ─────────────────────
function write_log($db, $xodim_id, $filial_id, $amal, $order_id = 0, $mijoz = '', $summa = 0, $izoh = '')
{
    $amal = mysqli_real_escape_string($GLOBALS['link'], $amal);
    $mijoz = mysqli_real_escape_string($GLOBALS['link'], $mijoz);
    $izoh = mysqli_real_escape_string($GLOBALS['link'], $izoh);
    $summa = (float) $summa;
    $order_id = (int) $order_id;
    $db->q(
        "INSERT INTO im_xodim_log (xodim_id, filial_id, amal, order_id, mijoz_ism, summa, izoh)
         VALUES ($xodim_id, $filial_id, '$amal', " . ($order_id ?: 'NULL') . ", " . ($mijoz ? "'$mijoz'" : 'NULL') . ", $summa, " . ($izoh ? "'$izoh'" : 'NULL') . ")"
    );
}

// ── YANGI: Mahsulot darajasida log yozish ─────────────────
function write_item_log($db, $xodim_id, $filial_id, $order_id, $item_id, $mahsulot_id, $nomi, $amal, $eski_soni, $yangi_soni)
{
    $nomi = mysqli_real_escape_string($GLOBALS['link'], $nomi);
    $amal = mysqli_real_escape_string($GLOBALS['link'], $amal);
    $item_id_s = $item_id ? (int) $item_id : 'NULL';
    $db->q(
        "INSERT INTO im_order_item_log
            (order_id, item_id, mahsulot_id, xodim_id, filial_id, amal, eski_soni, yangi_soni, nomi)
         VALUES (
            $order_id, $item_id_s, $mahsulot_id,
            $xodim_id, $filial_id,
            '$amal',
            $eski_soni, $yangi_soni,
            '$nomi'
         )"
    );
}

// ─────────────────────────────────────────────────────────
// YORDAMCHI: mahsulot narxini SERVERDA aniqlash
// ---------------------------------------------------------
// Ilgari narx brauzerdan kelgani uchun hisob-fakturaga to'g'ridan-to'g'ri
// tushardi: devtools bilan "narx":1 yuborilsa 50 000 so'mlik taom 1 so'mga
// yozilardi (kassa POS aynan shu narxni oladi, im_order_item_log esa faqat
// miqdorni yozadi — ya'ni tekshiruvda ham bilinmasdi). Endi narx har doim
// bazadan olinadi:
//   - alohida qator → filial sotuv narxi (bo'lmasa umumiy narx), miqdor
//                     ulgurji chegarasidan oshsa — ulgurji narxi;
//   - set qatori    → set narxi komponentlar orasida PROPORSIONAL
//                     taqsimlanadi. Formula sotuvchi/index.php dagi
//                     addSetToCart() bilan bir xil, shuning uchun
//                     ofitsant ko'rgan jami server hisobiga mos tushadi.
// Qaytadi: ['asos' => bazadagi joriy narx, 'ulg' => ulgurji narxi yoki null]
//   yoki null — mahsulot/set topilmadi.
// DIQQAT: chaqiruvchi MAVJUD qatorda 'asos' o'rniga qatorda saqlangan
// narxni ishlatadi (mijozga berilgan oldindan chek o'zgarib ketmasin);
// 'ulg' esa har doim joriy — ofitsant ekranidagi effN() bilan bir xil.
// ─────────────────────────────────────────────────────────
function im_sotuvchi_narx($db, $filial_id, $mahsulot_id, $soni, $set_id = 0)
{
    $filial_id   = (int)$filial_id;
    $mahsulot_id = (int)$mahsulot_id;
    $set_id      = (int)$set_id;

    if ($set_id > 0) {
        $set = $db->row("SELECT narxi FROM im_setlar
                         WHERE id=$set_id AND filial_id=$filial_id AND aktiv=1");
        if (!$set) return null;
        $set_items = $db->rows(
            "SELECT si.mahsulot_id, si.soni,
                    COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0) AS narx
             FROM im_set_items si
             LEFT JOIN im_narxlar n         ON n.mahsulot_id  = si.mahsulot_id
             LEFT JOIN im_filial_qoldiq fq  ON fq.mahsulot_id = si.mahsulot_id
                                           AND fq.filial_id   = $filial_id
             WHERE si.set_id=$set_id"
        );
        if (!$set_items) return null;
        $asl = 0.0;
        foreach ($set_items as $si) $asl += (float)$si['narx'] * (float)$si['soni'];
        foreach ($set_items as $si) {
            if ((int)$si['mahsulot_id'] !== $mahsulot_id) continue;
            // Set qatoriga ulgurji narx QO'LLANILMAYDI (addSetToCart ham
            // ulg_min:0 qo'yadi) — aks holda set chegirmasi buziladi.
            return ['asos' => $asl > 0
                        ? round((float)$si['narx'] * ((float)$set['narxi'] / $asl))
                        : round((float)$set['narxi'] / count($set_items)),
                    'ulg'  => null];
        }
        return null;   // mahsulot bu setning tarkibida yo'q
    }

    $r = $db->row(
        "SELECT COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0) AS narx,
                COALESCE(nu.min_soni, 0)      AS ulg_min,
                COALESCE(nu.ulgurji_narxi, 0) AS ulg_narx
         FROM im_mahsulotlar m
         LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id=m.id AND fq.filial_id=$filial_id
         LEFT JOIN im_narxlar n        ON n.mahsulot_id=m.id
         LEFT JOIN im_narx_ulgurji nu  ON nu.mahsulot_id=m.id AND nu.aktiv=1
         WHERE m.id=$mahsulot_id LIMIT 1"
    );
    if (!$r) return null;

    $ulg = null;
    if ((float)$r['ulg_min'] > 0 && (float)$soni >= (float)$r['ulg_min'] && (float)$r['ulg_narx'] > 0) {
        $ulg = (float)$r['ulg_narx'];
    }
    return ['asos' => (float)$r['narx'], 'ulg' => $ulg];
}

// ─────────────────────────────────────────────────────────
// YORDAMCHI: items massivini tekshirish va status aniqlash
// ---------------------------------------------------------
// DIQQAT: $it['locked_soni'] bu yerga BAZADAN to'ldirilgan holda keladi
// (pastdagi tranzaksiya blokiga qarang) — brauzer qiymatiga ishonilmaydi.
// Shuning uchun funksiya faqat tranzaksiya ichidan, order qatorlari
// qulflangandan keyin chaqiriladi va xatoni exception bilan beradi.
// ─────────────────────────────────────────────────────────
function check_items_kitchen($db, $items)
{
    foreach ($items as $it) {
        $mid = (int) ($it['mahsulot_id'] ?? 0);
        $soni = (float) ($it['soni'] ?? 0);
        $locked = (float) ($it['locked_soni'] ?? 0);
        // Orderda ALLAQACHON turgan miqdor (qulflab o'qilgan). Retsept
        // sharti faqat shundan ORTIQ qo'shilayotgan qism uchun tekshiriladi.
        $bor    = max($locked, (float) ($it['db_soni'] ?? 0));
        if ($mid && $soni > 0) {
            $mah = $db->row("SELECT nomi, COALESCE(sotuv_qadami,1) AS qadam,
                                    COALESCE(oshpaz_kerak,0) AS ok, COALESCE(qozon_rejim,0) AS qr
                             FROM im_mahsulotlar WHERE id=$mid");
            $qadam = max(0.001, (float)($mah['qadam'] ?? 1));
            if (abs(($soni / $qadam) - round($soni / $qadam)) > 0.0001) {
                throw new RuntimeException("«{$mah['nomi']}» miqdori {$qadam} qadam bilan kiritilishi kerak");
            }
            // Oshxona taomi (a-la-carte, qozon emas) faol retseptsiz bo'lsa —
            // oshpaz qabulida xomashyo yechilmaydi, kassada esa "FIFO tannarxi
            // topilmadi" bilan chek yopilmaydi (taom allaqachon yeb bo'lingan
            // bo'ladi). Xatoni ENG BOSHIDA — buyurtma saqlashda beramiz.
            // Orderda allaqachon turgan qism ($bor) uchun tekshirilmaydi: admin
            // retseptni keyin o'chirib qo'ysa ham ochiq stolni tahrirlash (hatto
            // shu taomni o'chirish yoki yoniga suv qo'shish) mumkin bo'lib qolsin.
            if ((int)($mah['ok'] ?? 0) === 1 && (int)($mah['qr'] ?? 0) === 0 && $soni > $bor) {
                $retsept_bor = $db->val(
                    "SELECT id FROM im_retseptlar
                     WHERE mahsulot_id=$mid AND tur='ishlab_chiqarish' AND status=1 LIMIT 1"
                );
                if (!$retsept_bor) {
                    throw new RuntimeException("«{$mah['nomi']}» — oshxona taomi, lekin faol retsepti yo'q. "
                        . "Admin «Retseptlar» bo'limida retsept kiritmaguncha buyurtmaga qo'shib bo'lmaydi.");
                }
            }
        }
        if ($soni < $locked) {
            throw new RuntimeException("Oshpazda pishirilayotgan miqdordan kamaytirish mumkin emas"
                . ($locked > 0 ? " (oshpazga {$locked} ta ketgan)" : '') . "!");
        }
    }
    foreach ($items as $it) {
        $mid = (int) ($it['mahsulot_id'] ?? 0);
        $soni = (float) ($it['soni'] ?? 0);
        $locked = (float) ($it['locked_soni'] ?? 0);
        if ($mid && $soni > $locked) {
            $ok = (int) $db->val("SELECT oshpaz_kerak FROM im_mahsulotlar WHERE id=$mid");
            if ($ok)
                return true;
        }
    }
    return false;
}

// ─────────────────────────────────────────────────────────
// 1. HOLD yoki CREATE
// ─────────────────────────────────────────────────────────
if ($action === 'hold' || $action === 'create') {

    $order_id = (int) ($_POST['order_id'] ?? 0);
    // DIQQAT: bu yerda ATAYLAB im_f() emas, mysqli_real_escape_string()
    // ishlatiladi — loyihadagi BOSHQA barcha yozuv joylari bilan bir xil
    // qoida: baza matnni XOM saqlaydi (faqat SQL uchun escape qilingan),
    // HTML-escape esa CHIQISHDA (im_f() PHP tarafida, im_esc() JS
    // tarafida) qo'llanadi. Ilgari bu yerda im_f() (htmlspecialchars)
    // yozishda ishlatilardi — bu ikki muammo keltirib chiqargan edi:
    //   1) htmlspecialchars backslash'ni ESKAPE QILMAYDI, ya'ni SQL
    //      satrida yagona himoya bo'lib qolgani uchun "\" bilan
    //      tugaydigan matn keyingi ustunlarga sizib chiqishi mumkin edi.
    //   2) bazada saqlangan qiymat allaqachon HTML-encode bo'lgani uchun
    //      chek/eksport kabi HTML BO'LMAGAN chiqishlarda "&#039;" kabi
    //      belgi harflari ko'rinardi.
    // Xom (escape'siz) nusxalar — write_log() o'zi ichida yana bir marta
    // escape qiladi, shuning uchun unga ALREADY-escaped qiymat berilsa
    // ikki marta escape bo'lib qoladi (masalan ism ichidagi "'" "\\'"
    // o'rniga "\\\\'" bo'lib yozilardi). Pastda ikkalasi ham kerak.
    $mijoz_ism_raw = mb_substr(trim($_POST['mijoz_ism'] ?? ''), 0, 100, 'UTF-8');
    $izoh_raw      = mb_substr(trim($_POST['izoh'] ?? ''), 0, 500, 'UTF-8');
    $mijoz_ism = mysqli_real_escape_string($link, $mijoz_ism_raw);
    $izoh      = mysqli_real_escape_string($link, $izoh_raw);
    $items = json_decode($_POST['items'] ?? '[]', true);
    $stol_id = (int) ($_POST['stol_id'] ?? 0);
    $olib_ketish = !empty($_POST['olib_ketish']) ? 1 : 0;

    // Bir martalik kalit: tarmoq uzilib javob yo'qolsa ofitsant "Pauza" ni
    // qayta bosadi va AYNAN shu token bilan keladi — yangi order ochilmaydi.
    // Faqat harf/raqam qoldiriladi, shuning uchun SQL ga xavfsiz tushadi.
    $client_token = substr(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['client_token'] ?? '')), 0, 40);
    // Ofitsant stolni ochgan paytdagi qatorlar "barmoq izi":
    //   "mahsulot_id:set_id:soni" lar saralangan holda, "|" bilan ulangan.
    // Faqat solishtirish uchun ishlatiladi, SQL ga tushmaydi. Bo'sh bo'lsa
    // tekshiruv o'tkazib yuboriladi (eski keshlangan JS bilan ham ishlasin).
    $base_sig = substr(preg_replace('/[^0-9:.|]/', '', (string)($_POST['base_sig'] ?? '')), 0, 20000);

    if (empty($items))
        im_json('error', 'Savatcha bo\'sh!');
    if (!$mijoz_ism)
        im_json('error', 'Stol/mijoz tanlanmagan!');

    // Mavjud orderning turi va stoli o'zgarmas biznes identifikatoridir.
    // Brauzer yuborgan olib_ketish/stol_id ga ko'r-ko'rona ishonilsa oddiy
    // stol orderini olib ketishga aylantirib xizmat haqini chetlab o'tish,
    // yoki tugallangan orderni qayta ochish mumkin bo'lib qoladi.
    $old_hint = null;
    if ($order_id > 0) {
        $old_hint = $db->row(
            "SELECT id, status, stol_id, olib_ketish
             FROM im_sotuvchi_order WHERE id=$order_id AND filial_id=$filial_id"
        );
        if (!$old_hint) im_json('error', 'Order topilmadi');

        $old_stol = $old_hint['stol_id'] !== null ? (int)$old_hint['stol_id'] : 0;
        $old_take = (int)$old_hint['olib_ketish'];
        if (!im_order_kontekst_mos($stol_id, $olib_ketish, $old_stol, $old_take)) {
            im_json('error', "Mavjud orderning stoli yoki turi o'zgartirilmaydi");
        }
        if (!im_order_tahrirlanadi($old_hint['status'])) {
            im_json('error', "Bu order kassaga yuborilgan yoki yopilgan — tahrirlash mumkin emas");
        }

        // #12 kabi tarixiy stolsiz olib-ketish orderlari yangi yaratilmaydi,
        // ammo mijoz hisobini yo'qotmaslik uchun mavjud yozuv yakunlanishi mumkin.
        $stol_id = $old_stol;
        $olib_ketish = $old_take;
    } elseif ($olib_ketish && !$stol_id) {
        // Stolga bog'liq qolgan ikki qoida active_normal qulflangach tekshiriladi.
        im_json('error', im_order_yaratish_xatosi($stol_id, $olib_ketish, 0));
    }

    $stol_id_sql = $stol_id ?: 'NULL';

    // ── IDEMPOTENTLIK ──────────────────────────────────────
    // Javob yo'qolgan bo'lsa ofitsant "Pauza" ni qayta bosadi: shu token
    // bilan yaratilgan order allaqachon bo'lsa, yangisini ochmay o'shani
    // tahrirlaymiz. Aks holda bitta stolda ikkita bir xil "olib ketish"
    // orderi paydo bo'lar va qoldiq ikki marta band qilinardi.
    // Qidiruv ataylab tranzaksiyadan OLDIN: REPEATABLE READ da tranzaksiya
    // ichidagi birinchi QULFSIZ o'qish snapshotni qotirib qo'yadi, keyingi
    // qulflar esa uni yangilamaydi. Haqiqiy poyga (ikki so'rov ayni paytda)
    // client_token ustidagi UNIQUE indeks bilan to'xtatiladi.
    // Token global unikal, shuning uchun filial sharti qo'yilmaydi — begona
    // filial orderi pastdagi "AND filial_id=$filial_id" da rad etiladi.
    $token_orderi = false;
    if ($order_id <= 0 && $client_token !== '') {
        $dup = $db->row("SELECT id FROM im_sotuvchi_order
                         WHERE client_token='$client_token' LIMIT 1");
        if ($dup) { $order_id = (int)$dup['id']; $token_orderi = true; }
    }

    // ══ TRANZAKSIYA ═══════════════════════════════════════
    // Butun blok try ichida (chekinish ataylab o'zgartirilmadi — diff
    // kichik qolsin). Deadlock (errno 1213) yoki lock timeout (1205)
    // Cyber::q() da EXCEPTION bo'lib otiladi; ilgari u tutilmagani uchun
    // PHP fatal berar, javob JSON emas HTML bo'lar va ofitsant "Tarmoq
    // xatosi" ni ko'rardi — "qaytadan bosing" degan aniq xabar esa yetib
    // bormasdi. Tranzaksiya ham faqat ulanish yopilganda qaytarilardi.
    $db->begin();
    try {

    // Bitta stolga oid barcha ochish/yopish amallari shu stol qatori orqali
    // ketma-ketlashtiriladi. Checkout va bekor qilish endpointlari ham aynan
    // shu qatorni birinchi bo'lib qulflaydi.
    if ($stol_id) {
        $stol_row = $db->row(
            "SELECT id FROM im_stollar
             WHERE id=$stol_id AND filial_id=$filial_id AND status=1 FOR UPDATE"
        );
        if (!$stol_row) {
            $db->rollback();
            im_json('error', 'Stol topilmadi yoki faol emas');
        }
    }
    $old      = null;
    $db_items = [];   // "mahsulot_id:set_id" => ['soni'=>..., 'tayyor'=>...]

    if ($order_id > 0) {
        $old = $db->row(
            "SELECT id, status, stol_id, olib_ketish, updated_at
             FROM im_sotuvchi_order WHERE id=$order_id AND filial_id=$filial_id FOR UPDATE"
        );
        if (!$old) {
            $db->rollback();
            im_json('error', 'Order topilmadi');
        }
        $locked_stol = $old['stol_id'] !== null ? (int)$old['stol_id'] : 0;
        if (!im_order_tahrirlanadi($old['status'])) {
            $db->rollback();
            if ($token_orderi) {
                // TAKRORIY YUBORISH: birinchi so'rov muvaffaqiyatli bo'lgan
                // (javob yo'lda yo'qolgan), order allaqachon kassada/oshpazda.
                // Xato berish noto'g'ri bo'lardi — ofitsant "yuborilmadi" deb
                // o'ylab, uchinchi marta bosaverardi.
                im_json('ok', 'Buyurtma allaqachon yuborilgan',
                        ['order_id' => $order_id, 'status' => $old['status']]);
            }
            im_json('error', "Bu order kassaga yuborilgan yoki yopilgan — tahrirlash mumkin emas");
        }
        if (!im_order_kontekst_mos($locked_stol, $old['olib_ketish'], $stol_id, $olib_ketish)) {
            $db->rollback();
            im_json('error', "Order boshqa stolga yoki boshqa turga ko'chirilmaydi");
        }

        // Order qatorlari — QULFLAB o'qiymiz. "Oshpazga ketgan miqdor"
        // (tayyorlandi_soni) ayni shu yerdan olinadi; brauzer yuborgan
        // locked_soni ga ishonib bo'lmaydi (pastga qarang).
        foreach ($db->rows(
            "SELECT id, mahsulot_id, COALESCE(set_id,0) AS sid, soni, rezerv_soni, tayyorlandi_soni
             FROM im_sotuvchi_order_item WHERE order_id=$order_id
             ORDER BY mahsulot_id, sid FOR UPDATE") as $lr) {
            $k_db = (int)$lr['mahsulot_id'] . ':' . (int)$lr['sid'];
            if (isset($db_items[$k_db])) {
                // Nazariy jihatdan bo'lmasligi kerak (upsert kaliti shu),
                // lekin bo'lib qolsa rezerv/tayyor YIG'ILADI — aks holda
                // qator o'chirilganda band qilingan qoldiq osilib qolardi.
                $db_items[$k_db]['rezerv'] += (float)$lr['rezerv_soni'];
                $db_items[$k_db]['tayyor'] += (float)$lr['tayyorlandi_soni'];
                $db_items[$k_db]['soni']    = (float)$lr['soni'];
                continue;
            }
            $db_items[$k_db] = [
                'id'     => (int)$lr['id'],
                'mid'    => (int)$lr['mahsulot_id'],
                'sid'    => (int)$lr['sid'],
                'soni'   => (float)$lr['soni'],
                'rezerv' => (float)$lr['rezerv_soni'],
                'tayyor' => (float)$lr['tayyorlandi_soni'],
            ];
        }
    }

    // ── QULF TARTIBI: stol qatori → order qatori → order qatorlari → MAHSULOTLAR ──
    // Mahsulot qulflari hujjat qulflaridan KEYIN olinadi (checkout ham shunday) —
    // aks holda kassa mahsulotni ushlab order qatorini, ofitsant esa order
    // qatorini ushlab mahsulotni kutib deadlock bo'lardi. Butun filial emas,
    // faqat tegiladigan mahsulotlar: yuborilgan qatorlar + orderda ALLAQACHON
    // bor qatorlar (ular o'chirilsa rezerv qaytariladi) + ularning
    // retsept/avto-maydalash bog'liqliklari.
    //
    // MUHIM: bu blok pastdagi HAR QANDAY qulfsiz o'qishdan OLDIN turishi shart.
    // REPEATABLE READ da tranzaksiyaning birinchi qulfsiz SELECT'i snapshotni
    // qotiradi va keyingi qulflar uni yangilamaydi — ya'ni qulf ro'yxati
    // (im_qulf_mahsulotlari ichidagi avto-maydalash o'qishlari) eski
    // ma'lumotdan tuzilib, kerakli mahsulot qulfsiz qolib ketishi mumkin edi.
    $lock_ids = [];
    foreach ($items as $it_lock) {
        $mid_lock = (int)($it_lock['mahsulot_id'] ?? 0);
        if ($mid_lock > 0) $lock_ids[] = $mid_lock;
    }
    // Orderda ALLAQACHON bor qatorlar yuqorida qulflab o'qilgan ($db_items).
    foreach ($db_items as $row_lock) $lock_ids[] = (int)$row_lock['mid'];
    im_qulfla_mahsulotlar($db, $filial_id, $lock_ids);

    // ── locked_soni ni BAZADAN to'ldiramiz ─────────────────
    // Ilgari u $_POST dan olinardi. Natijada: ofitsant stolni ochib turgan
    // paytda oshpaz "Qabul qildim" bossa (xomashyo 5 porsiyaga sarflanadi),
    // brauzerdagi eski locked_soni=0 bo'lgani uchun ofitsant miqdorni 1 ga
    // tushira olardi — xomashyo yo'qolar, mijozga 1 ta yozilardi.
    foreach ($items as &$it_srv) {
        $k_srv = (int)($it_srv['mahsulot_id'] ?? 0) . ':' . (int)($it_srv['set_id'] ?? 0);
        $it_srv['locked_soni'] = isset($db_items[$k_srv]) ? $db_items[$k_srv]['tayyor'] : 0.0;
        $it_srv['db_soni']     = isset($db_items[$k_srv]) ? $db_items[$k_srv]['soni']   : 0.0;
    }
    unset($it_srv);

    // ── Boshqa qurilma o'zgartirib qo'ygan bo'lsa ──────────
    // Kassa POS ham (dukon/pos.php → openStolEdit) shu orderni tahrirlaydi.
    // Ikkovi bir vaqtda ochib tursa, keyin saqlagani birinchisi qo'shgan
    // qatorlarni indamay o'chirib yuborardi.
    // Solishtirish ORDER QATORLARI bo'yicha (order.updated_at bo'yicha emas):
    // oshpaz "Qabul qildim" bosishi ham updated_at ni o'zgartiradi, lekin
    // qatorlarga tegmaydi — bunday holda ofitsantga to'sqinlik qilmaymiz.
    // Va HAR QANDAY farqni ham bloklamaymiz: faqat YO'QOTISH bo'ladiganini —
    // qator o'chirilsa yoki miqdori kamaysa. Qo'shish to'siqsiz o'tadi.
    $srv_parts = [];
    foreach ($db_items as $k_sig => $r_sig) {
        $srv_parts[] = $k_sig . ':' . number_format($r_sig['soni'], 3, '.', '');
    }
    sort($srv_parts);
    $srv_sig = implode('|', $srv_parts);

    if ($old !== null && $base_sig !== '' && $base_sig !== $srv_sig) {
        $yuborilgan = [];
        foreach ($items as $it_chk) {
            $yuborilgan[(int)($it_chk['mahsulot_id'] ?? 0) . ':' . (int)($it_chk['set_id'] ?? 0)]
                = (float)($it_chk['soni'] ?? 0);
        }
        foreach ($db_items as $k_db => $row_db) {
            if (!isset($yuborilgan[$k_db]) || $yuborilgan[$k_db] < $row_db['soni'] - 0.0001) {
                throw new RuntimeException("Bu buyurtmani boshqa qurilma (kassa yoki boshqa "
                    . "ofitsant) o'zgartirdi. Stolni yopib qaytadan oching — aks holda "
                    . "u qo'shgan qatorlar yo'qoladi.");
            }
        }
    }

    // Status — endi ISHONCHLI locked_soni asosida.
    $has_new_kitchen = check_items_kitchen($db, $items);
    if ($action === 'hold') {
        $status = $has_new_kitchen ? 'oshpazda' : 'stol_band';
    } else {
        $status = $has_new_kitchen ? 'oshpazda' : 'tasdiqlandi';
    }

    if ($old !== null) {

        // ── 'pishirilmoqda' — oshpaz qabul qilgan, taom tayyorlanmoqda ──
        if ($old['status'] === 'pishirilmoqda') {
            if ($action === 'create' && !$has_new_kitchen) {
                // Kassaga yuborilsa oshpazning "Pishirilmoqda" kartasi
                // yo'qolar va "Tayyor" tugmasi bosilmay qolar edi.
                $db->rollback();
                im_json('error', "Taom hali pishirilmoqda — oshpaz \"Tayyor\" bosgandan keyin kassaga yuboring.");
            }
            // Yangi oshpaz mahsuloti qo'shilsa → 'oshpazda' (oshpaz faqat
            // qo'shilgan qismini ko'radi). Aks holda holat o'zgarmaydi.
            $status = $has_new_kitchen ? 'oshpazda' : 'pishirilmoqda';
        }

        // Agar order oshpazda edi va yangi oshpaz mahsuloti qo'shilmasa
        // stol_band ga qaytarish (oshpaz o'z ishini tugatgan)
        elseif ($old['status'] === 'oshpazda' && !$has_new_kitchen) {
            $status = $action === 'create' ? 'tasdiqlandi' : 'stol_band';
        }

        // Agar stol_band edi va sotuvchi kassaga yubordi (create)
        elseif ($old['status'] === 'stol_band' && $action === 'create' && !$has_new_kitchen) {
            $status = 'tasdiqlandi';
        }

        $db->q("UPDATE im_sotuvchi_order SET mijoz_ism='$mijoz_ism', stol_id=$stol_id_sql, olib_ketish=$olib_ketish, izoh='$izoh', status='$status', updated_at=NOW()
                WHERE id=$order_id AND filial_id=$filial_id");
    } else {
        if ($stol_id) {
            $active_normal = (int)$db->val(
                "SELECT COUNT(*) FROM im_sotuvchi_order
                 WHERE filial_id=$filial_id AND stol_id=$stol_id AND olib_ketish=0
                   AND status NOT IN ('bekor','tugallandi')"
            );
            $create_error = im_order_yaratish_xatosi($stol_id, $olib_ketish, $active_normal);
            if ($create_error) {
                $db->rollback();
                im_json('error', $create_error);
            }
        }
        try {
            $order_id = $db->insert(
                "INSERT INTO im_sotuvchi_order
                    (sotuvchi_id, filial_id, mijoz_ism, stol_id, olib_ketish, izoh, status, client_token)
                 VALUES ($sotuvchi_id, $filial_id, '$mijoz_ism', $stol_id_sql, $olib_ketish, '$izoh', '$status',
                         " . ($client_token !== '' ? "'$client_token'" : 'NULL') . ")"
            );
        } catch (Throwable $e_ins) {
            // UNIQUE(client_token): AYNI paytda kelgan ikkinchi so'rov.
            // Birinchisi hali commit qilmagan bo'lishi mumkin, shuning uchun
            // uni qidirib o'tirmaymiz — ofitsantga tushunarli javob beramiz.
            if ($client_token !== '' && stripos($e_ins->getMessage(), 'client_token') !== false) {
                throw new RuntimeException("Buyurtma shu daqiqada saqlanmoqda — "
                    . "bir soniyadan keyin stolni qayta oching.");
            }
            throw $e_ins;
        }
        if (!$order_id) {
            $db->rollback();
            im_json('error', 'Order yaratishda xato');
        }
    }

    // ── Mahsulotlarni upsert + item-log yozish ─────────────
    // Kalit endi (order_id, mahsulot_id, set_id) — bitta mahsulot orderda
    // ikki marta bo'lishi mumkin: biri setda (set_id>0), biri alohida (0).
    $passed_keys = [];
    foreach ($items as $item) {
        $mid = (int) ($item['mahsulot_id'] ?? 0);
        $soni = (float) ($item['soni'] ?? 0);
        $set_id     = (int) ($item['set_id'] ?? 0);
        $set_id_sql = $set_id ?: 'NULL';
        // Olib ketish — alohida order: uning HAMMA qatori qadoqlanadi va
        // xizmat haqisiz. Oddiy stol orderida qisman "saboy" yo'q; mijoz
        // uyiga mahsulot buyursa yangi Olib ketish orderi ochiladi.
        $item_ok = $olib_ketish ? $soni : 0;
        if (!$mid || $soni <= 0)
            continue;

        $passed_keys[] = $mid . ':' . $set_id;

        // Mahsulot nomini olish
        $prod_nomi = (string) $db->val("SELECT nomi FROM im_mahsulotlar WHERE id=$mid");

        // Rezerv faqat tayyor vitrinali SKU uchun yuritiladi. Retseptli yoki
        // oshxona mahsulotining xomashyosi keyingi biznes bosqichida yechiladi.
        $rezervlanadi = im_vitrinali($db, $mid);
        $yangi_rezerv = $rezervlanadi ? $soni : 0.0;

        // FOR UPDATE — rezerv farqi shu qiymatdan hisoblanadi; REPEATABLE READ da
        // oddiy o'qish eski snapshotni berib, ikki qurilma bir order qatorini
        // tahrirlaganda rezervni ikki marta band qilib qo'yardi.
        $exist_row = $db->row("SELECT id, soni, narx, rezerv_soni, tayyorlandi_soni FROM im_sotuvchi_order_item
                               WHERE order_id=$order_id AND mahsulot_id=$mid AND COALESCE(set_id,0)=$set_id FOR UPDATE");

        // ── NARX: brauzerdan emas, bazadan ────────────────
        //  • MAVJUD qator → qatorda SAQLANGAN narx o'zgarmaydi. Kassir kun
        //    o'rtasida vitrina narxini ko'tarsa ham mijozga allaqachon
        //    aytilgan narx bo'yicha hisob chiqadi (oldindan chek bilan mos).
        //  • YANGI qator  → bazadagi joriy narx.
        //  • Ulgurji chegarasi kesib o'tilsa — ikkalasida ham ulgurji narxi.
        //    Ofitsant ekranidagi effN() ayni shunday hisoblaydi.
        //  • 0 narx ruxsat etiladi (bepul non/souz kabi qo'shimchalar).
        $narx_info = im_sotuvchi_narx($db, $filial_id, $mid, $soni, $set_id);
        $eski_narx = $exist_row ? (float) $exist_row['narx'] : 0.0;
        $narx      = $eski_narx > 0 ? $eski_narx : ($narx_info ? (float) $narx_info['asos'] : null);
        if ($narx_info && $narx_info['ulg'] !== null) $narx = (float) $narx_info['ulg'];
        if ($narx === null) {
            throw new RuntimeException("«{$prod_nomi}» narxini aniqlab bo'lmadi — "
                . "mahsulot yoki set sozlamasini tekshiring (Sklad → Mahsulotlar).");
        }
        $narx = max(0.0, (float) $narx);

        if ($exist_row) {
            $item_id = (int) $exist_row['id'];
            $eski_soni = (float) $exist_row['soni'];

            // Ikkinchi to'siq (check_items_kitchen dan keyin): qator qulfi
            // ostidagi ENG SO'NGGI qiymat. Oshpazga ketgan miqdordan pastga
            // tushirish xomashyoni yo'qotadi va oshpaz kartasini "o'lik"
            // qoldiradi (order-qabul.php i.soni > i.tayyorlandi_soni bo'yicha
            // ishlaydi), shuning uchun bu yerda ham qat'iy rad etamiz.
            $tayyor_db = (float) $exist_row['tayyorlandi_soni'];
            if ($soni < $tayyor_db - 0.0001) {
                throw new RuntimeException("«{$prod_nomi}»: oshpazga allaqachon {$tayyor_db} ta "
                    . "ketgan — undan kamaytirib bo'lmaydi.");
            }

            // REZERV: faqat FARQ qadar band qilamiz (yoki qaytaramiz).
            // Ofitsant 2 tadan 3 taga oshirsa — 1 ta band qilinadi.
            $eski_rezerv = (float) $exist_row['rezerv_soni'];
            if ($rezervlanadi && !im_rezerv($db, $filial_id, $mid, $yangi_rezerv - $eski_rezerv)) {
                $mavjud = (float)$db->val("SELECT COALESCE(soni,0) FROM im_filial_qoldiq
                                           WHERE filial_id=$filial_id AND mahsulot_id=$mid");
                $db->rollback();
                im_json('error', "«{$prod_nomi}» yetarli emas — vitrinada {$mavjud} ta qoldi "
                    . "(boshqa stollardagi buyurtmalar hisobga olingan).");
            }

            $db->q("UPDATE im_sotuvchi_order_item SET soni=$soni, narx=$narx,
                    olib_ketish_soni=$item_ok, rezerv_soni=$yangi_rezerv WHERE id=$item_id");

            // Delta log
            if (abs($soni - $eski_soni) > 0.0001) {
                $amal_type = $soni > $eski_soni ? 'oshirildi' : 'kamaytir';
                write_item_log($db, $sotuvchi_id, $filial_id, $order_id, $item_id, $mid, $prod_nomi, $amal_type, $eski_soni, $soni);
            }
        } else {
            // REZERV: yangi qator — butun miqdor band qilinadi
            if ($rezervlanadi && !im_rezerv($db, $filial_id, $mid, $yangi_rezerv)) {
                $mavjud = (float)$db->val("SELECT COALESCE(soni,0) FROM im_filial_qoldiq
                                           WHERE filial_id=$filial_id AND mahsulot_id=$mid");
                $db->rollback();
                im_json('error', "«{$prod_nomi}» yetarli emas — vitrinada {$mavjud} ta qoldi "
                    . "(boshqa stollardagi buyurtmalar hisobga olingan).");
            }

            $item_id = $db->insert(
                "INSERT INTO im_sotuvchi_order_item (order_id, mahsulot_id, set_id, soni, narx, tayyorlandi_soni, olib_ketish_soni, rezerv_soni)
                 VALUES ($order_id, $mid, $set_id_sql, $soni, $narx, 0, $item_ok, $yangi_rezerv)"
            );
            write_item_log($db, $sotuvchi_id, $filial_id, $order_id, $item_id, $mid, $prod_nomi, 'qoshildi', 0, $soni);
        }
    }

    // Hech bir qator o'tmagan bo'lsa (hammasining soni <= 0) — bu saqlash
    // "hech narsa qilmaydi", lekin order holatini o'zgartirib yuborardi va
    // ofitsantga "saqlandi" deb ko'rsatardi. Ochiq xato beramiz.
    if (empty($passed_keys)) {
        throw new RuntimeException("Savatchada haqiqiy mahsulot yo'q — miqdorlarni tekshiring.");
    }

    // O'chirilgan qatorlar — kalit (mahsulot_id:set_id) bo'yicha solishtiramiz,
    // shunda setdagi mahsulot olib tashlansa ham, o'sha mahsulotning
    // alohida (à la carte) qatori tegilmaydi va aksincha.
    // Manba — $db_items: u yuqorida FOR UPDATE bilan o'qilgan, ya'ni qulf
    // ostidagi JORIY holat. (REPEATABLE READ da shu joydagi oddiy SELECT
    // tranzaksiyaning eski snapshotini qaytarishi mumkin edi.)
    $qolgan = array_flip($passed_keys);
    foreach ($db_items as $k_old => $dr) {
        if (isset($qolgan[$k_old])) continue;
        if ($dr['tayyor'] > 0.0001) continue;   // oshpazga ketgan qator o'chmaydi

        // REZERVNI QAYTARISH: qator buyurtmadan olib tashlandi
        im_rezerv($db, $filial_id, $dr['mid'], -$dr['rezerv']);
        $nomi_del = (string) $db->val("SELECT nomi FROM im_mahsulotlar WHERE id={$dr['mid']}");
        write_item_log($db, $sotuvchi_id, $filial_id, $order_id,
                       $dr['id'], $dr['mid'], $nomi_del, 'ochirildi', $dr['soni'], 0);
        // Kalit bo'yicha o'chiramiz (id bo'yicha emas): bir kalitda
        // takroriy qator qolib ketgan bo'lsa ham hammasi ketadi.
        $db->q("DELETE FROM im_sotuvchi_order_item
                WHERE order_id=$order_id AND mahsulot_id={$dr['mid']}
                  AND COALESCE(set_id,0)={$dr['sid']} AND tayyorlandi_soni = 0");
    }

    $db->commit();

    } catch (Throwable $e) {
        // Deadlock/lock timeout — Cyber::q() tushunarli matn bilan otadi.
        $db->rollback();
        im_json('error', $e->getMessage());
    }

    // Summa hisob va order-log
    $summa = (float) $db->val("SELECT SUM(soni*narx) FROM im_sotuvchi_order_item WHERE order_id=$order_id");
    $amal_log = $action === 'hold'
        ? ($has_new_kitchen ? 'oshpazga_yuborildi' : 'stol_band')
        : ($has_new_kitchen ? 'oshpazga_yuborildi' : 'kassaga_yuborildi');
    write_log($db, $sotuvchi_id, $filial_id, $amal_log, $order_id, $mijoz_ism_raw, $summa, $izoh_raw);

    $msg = $action === 'hold'
        ? ($has_new_kitchen ? 'Oshxonaga yuborildi' : 'Stol saqlab qolindi')
        : ($has_new_kitchen ? 'Oshxonaga yuborildi, kassa kutmoqda' : 'Kassaga yuborildi');

    im_json('ok', $msg, ['order_id' => $order_id, 'status' => $status]);
}

// ─────────────────────────────────────────────────────────
// 2. BEKOR QILISH — SOTUVCHIGA TAQIQLANGAN
// ─────────────────────────────────────────────────────────
// Yuborilgan buyurtmani bekor qilish endi faqat kassa (dukon POS,
// dukon/ajax/pending-orders.php) orqali amalga oshiriladi. Sotuvchi
// yuborilmagan (hali order_id olmagan) qoralamani frontendning o'zida
// hech qanday server so'rovisiz o'chiradi — shu sababli bu yerga
// umuman kelmaydi. Himoya sifatida server ham rad etadi.
if ($action === 'cancel') {
    im_json('error', "Yuborilgan buyurtmani bekor qilish huquqi yo'q — buni faqat kassa (POS) amalga oshira oladi.");
}

im_json('error', 'Noto\'g\'ri so\'rov');
