<?php
// ============================================================
//  IMezon — Konfiguratsiya va DB ulanish
//  Versiya: 1.0 | 2026
date_default_timezone_set('Asia/Tashkent'); // UTC+5

//  SOZLASH: Bu fayldan PASTDA turgan konstantalarni
//  o'z serveringiz ma'lumotlariga moslashtiring.
// ============================================================

// ─── SERVER SOZLAMALARI ───────────────────────────────────────
// Faqat SHUBU qatorlarni server ma'lumotlariga o'zgartiring:
define('im_HOST', getenv('im_HOST') ?: 'localhost');
define('im_USER', getenv('im_USER') ?: 'root');        // ← Serverda o'zgartiring
define('im_PASS', getenv('im_PASS') ?: '');             // ← Serverda o'zgartiring
define('im_DB',   getenv('im_DB')   ?: 'u1782683_imezon');       // ← Serverda o'zgartiring

// Sayt manzili (oxirida "/" bo'lmasin)
// Misol: 'https://IMezon.uz' yoki 'https://yourdomain.com/IMezon'
define('im_SITE', getenv('im_SITE') ?: 'http://imezon.uz/login.php');

// Sayt base URL (subdirectory yoki root)
// OSPanel'da bu sayt "localhost" vhost ichidagi "imezon.uz" papkasida
// joylashgan (C:\OSPanel\domains\localhost\imezon.uz) — shuning uchun
// Host sarlavhasi "localhost" bo'lsa, manzil avtomatik /imezon.uz/ deb
// olinadi. Production serverda domen to'g'ridan-to'g'ri (masalan
// Host: imezon.uz) bo'lgani uchun bu shart ishga tushmaydi va '/'
// standart qiymati o'zgarishsiz qoladi. Kerak bo'lsa im_BASE env
// o'zgaruvchisi orqali ham qo'lda ustidan yozish mumkin.
$im_host_header   = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
$im_base_default  = ($im_host_header === 'localhost') ? '/imezon.uz/' : '/';
define('im_BASE', getenv('im_BASE') ?: $im_base_default);

define('im_VERSION', '1.0.1');

// ─── DB ULANISH ───────────────────────────────────────────────
$link = mysqli_connect(im_HOST, im_USER, im_PASS, im_DB);
if (!$link) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center">
        <h2 style="color:#dc2626">❌ Baza bilan ulanishda xatolik</h2>
        <p style="color:#6b7280">config.php dagi im_USER, im_PASS, im_DB ni to\'g\'rilang</p>
        <code style="color:#ef4444">' . mysqli_connect_error() . '</code>
    </div>');
}
mysqli_set_charset($link, 'utf8mb4');
mysqli_query($link, "SET time_zone = '+05:00'"); // O'zbekiston vaqt zonasi

// ─── YORDAMCHI FUNKSIYALAR ───────────────────────────────────

// To'lov turi label'lari — YAGONA MANBA. Bu yerdan boshqa hech qayerda
// 'naqd'/'karta'/'bank'/'usd'/'qarz' uchun matnni qo'lda yozmang —
// shu funksiyalarni chaqiring. Nomi (DB qiymati) o'zgarmaydi, faqat
// ko'rinadigan matn shu yerda boshqariladi.
function im_tt_label($tt, $short = false) {
    $map = [
        'naqd'  => ['💵', 'Naqd'],
        'karta' => ['💳', 'Kart-karta'],
        'bank'  => ['🏦', $short ? 'Bank' : "Bank (O'tkazma, Terminal)"],
        'usd'   => ['🪙', 'USD'],
        'qarz'  => ['📋', 'Qarzga'],
    ];
    [$icon, $label] = $map[$tt] ?? ['', ucfirst((string)$tt)];
    return "$icon $label";
}

// Faqat matn (icon'siz) kerak bo'lgan joylar uchun (masalan xato xabarlari)
function im_tt_nomi($tt, $short = false) {
    $map = [
        'naqd'  => 'Naqd',
        'karta' => 'Kart-karta',
        'bank'  => $short ? 'Bank' : "Bank (O'tkazma, Terminal)",
        'usd'   => 'USD',
        'qarz'  => 'Qarzga',
    ];
    return $map[$tt] ?? ucfirst((string)$tt);
}

// ─── ROLLAR — YAGONA MANBA ───────────────────────────────────
// Rol qo'shish/o'chirish FAQAT shu yerda. Ilgari rol ro'yxati uch
// joyda (login.php ikki marta, ximoya.php bir marta) qo'lda
// takrorlanardi va yangi rol qo'shilganda kirish yo'nalishi
// unutilib qolardi.
//
//  bosh_kassir — markaz kassiri (xazinachi): do'konlardan pul qabul
//  qiladi, harajat va oylik to'laydi, qarzlarni yuritadi. Do'kondagi
//  `kassir` dan FARQLI: u chek yopadi, bu esa pulni boshqaradi.
function im_rollar() {
    return [
        // MUHIM: kalitlar ('kassir', 'bosh_kassir'...) — o'zgarmaydi (73 fayl + DB
        // shularga tayanadi). Faqat 'nomi' — ko'rinadigan yorliq.
        //   kassir      = DO'KON kassiri (POS, chek yopish, smena, zal xaritasi)
        //   bosh_kassir = MARKAZ xazinachisi (inkasso, harajat, maosh, qarz)
        'admin'       => ['nomi' => '👑 Admin',           'sahifa' => 'admin/index.php',    'rang' => 'danger'],
        'bosh_kassir' => ['nomi' => '🏦 Bosh kassir',     'sahifa' => 'kassa/index.php',    'rang' => 'primary'],
        'sklad'       => ['nomi' => '📦 Sklad',           'sahifa' => 'sklad/index.php',    'rang' => 'primary'],
        'kassir'      => ['nomi' => "🛍️ Do'kon kassiri",  'sahifa' => 'dukon/index.php',    'rang' => 'success'],
        'sotuvchi'    => ['nomi' => '🧑‍🍳 Ofitsant',       'sahifa' => 'sotuvchi/index.php', 'rang' => 'warning'],
        'oshpaz'      => ['nomi' => '🔥 Oshpaz',          'sahifa' => 'oshpaz/index.php',   'rang' => 'info'],
    ];
}

// Rol uchun kirish sahifasi. Noma'lum rol — adminga tushadi
// (avvalgi xatti-harakat bilan bir xil).
function im_rol_sahifa($rol) {
    $r = im_rollar();
    $s = isset($r[$rol]) ? $r[$rol]['sahifa'] : 'admin/index.php';
    return (defined('im_BASE') ? im_BASE : '/') . $s;
}

function im_rol_nomi($rol) {
    $r = im_rollar();
    return isset($r[$rol]) ? $r[$rol]['nomi'] : ucfirst((string)$rol);
}

function im_rol_rang($rol) {
    $r = im_rollar();
    return isset($r[$rol]) ? $r[$rol]['rang'] : 'muted';
}

// ─── NAVBAR — menyu bandi joriy sahifaga mos keladimi ────────
// YAGONA MANBA: to'rtala navbar (admin/sklad/dukon/qayta-ishlash)
// shu funksiyani chaqiradi. Ilgari har biri o'zicha tekshirardi va
// admin navbar butunlay buzuq edi:
//   .htaccess URL'dan ".php" ni yashiradi → REQUEST_URI kengaytmasiz
//   keladi ("/admin/balans"), menyu havolalari esa ".php" bilan
//   yozilgan → eski `strpos($cur_uri, basename($href))` HECH QACHON
//   mos kelmasdi, ya'ni hech bir bo'lim sariq ("active") bo'lmasdi.
// SCRIPT_NAME esa rewrite'dan keyin ham haqiqiy .php faylni beradi
// (GET redirect, POST, papka-index — hammasida barqaror).
// To'liq yo'l solishtiriladi, basename EMAS — aks holda turli
// modullardagi "index.php" bandlari bir vaqtda yonardi.
function im_nav_aktiv($href) {
    $joriy  = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
    $nishon = (string)parse_url((string)$href, PHP_URL_PATH);
    if ($joriy === '' || $nishon === '') return false;
    return rtrim($joriy, '/') === rtrim($nishon, '/');
}

// ─── ORDER BIZNES INVARIANTLARI ───────────────────────────────
function im_order_tahrirlanadi($status) {
    return in_array((string)$status, ['stol_band', 'oshpazda', 'pishirilmoqda'], true);
}

function im_order_kontekst_mos($stol_a, $take_a, $stol_b, $take_b) {
    $a = $stol_a !== null ? (int)$stol_a : 0;
    $b = $stol_b !== null ? (int)$stol_b : 0;
    return $a === $b && (int)(bool)$take_a === (int)(bool)$take_b;
}

// null = yaratish mumkin; satr = kassir/sotuvchiga ko'rsatiladigan sabab.
function im_order_yaratish_xatosi($stol_id, $olib_ketish, $active_normal) {
    $stol_id = (int)$stol_id;
    $olib_ketish = (bool)$olib_ketish;
    $active_normal = (int)$active_normal;
    if ($olib_ketish && !$stol_id) return "Olib ketish buyurtmasi mavjud stolga biriktirilishi shart";
    if ($stol_id && $olib_ketish && !$active_normal) return "Olib ketish buyurtmasi faqat faol asosiy orderi bor stolga ochiladi";
    if ($stol_id && !$olib_ketish && $active_normal) return "Bu stolda asosiy buyurtma allaqachon mavjud";
    return null;
}

// ─── ZAL XARITASI — YAGONA MANBA ──────────────────────────────
// Sotuvchi va Dukon (kassir) modullari bir xil stol holatini
// ko'rishi kerak — shuning uchun ikkalasi ham shu funksiyani
// chaqiradi (sotuvchi/ajax/get-hall.php va dukon/ajax/get-hall.php).
//
// Har bir stol uchun:
//   band       — hozir faol buyurtma bormi (oshpazda/stol_band/tasdiqlandi/qabul)
//   holat      — 'bosh' | 'ochiq' (sotuvchi ustida ishlaydi) | 'kassada' (kassaga yuborilgan)
//   orders     — shu stolga bog'langan BARCHA faol buyurtmalar. Bitta stolga
//                asosiy buyurtma va bir yoki bir nechta mustaqil "Olib ketish"
//                buyurtmasi bir vaqtda bog'lanishi mumkin.
//   order_id   — eski frontendlar uchun asosiy (avval oddiy, so'ng eng eski) order
//   summa      — stolga bog'langan barcha faol orderlar yig'indisi
function im_hall_stollar($db, $filial_id) {
    $filial_id = (int)$filial_id;
    // Zona (Teraska / Podval / Zal ...) — LEFT JOIN, zona_id NULL bo'lsa "Boshqa".
    // Tartib: avval zona tartibi, keyin stol tartibi.
    $stollar = $db->rows(
        "SELECT s.id, s.nomi, s.tartib, s.zona_id,
                z.nomi AS zona_nomi, z.rang AS zona_rang
         FROM im_stollar s
         LEFT JOIN im_zonalar z ON z.id = s.zona_id AND z.status = 1
         WHERE s.filial_id=$filial_id AND s.status=1
         ORDER BY COALESCE(z.tartib, 9999) ASC, s.tartib ASC, s.id ASC"
    );
    foreach ($stollar as &$s) {
        $s['zona_id'] = $s['zona_id'] !== null ? (int)$s['zona_id'] : null;
        $sid = (int)$s['id'];
        $orders = $db->rows(
            "SELECT o.id, o.status, o.created_at, o.olib_ketish,
                    COALESCE((SELECT SUM(i.soni*i.narx)
                              FROM im_sotuvchi_order_item i WHERE i.order_id=o.id),0) AS summa
             FROM im_sotuvchi_order o
             WHERE o.filial_id=$filial_id AND o.stol_id=$sid
               AND o.status NOT IN ('bekor','tugallandi')
             ORDER BY o.olib_ketish ASC, o.id ASC"
        );
        if ($orders) {
            foreach ($orders as &$orderRow) {
                $orderRow['id']              = (int)$orderRow['id'];
                $orderRow['olib_ketish']     = (bool)$orderRow['olib_ketish'];
                $orderRow['summa']           = (float)$orderRow['summa'];
                $orderRow['holat']           = in_array($orderRow['status'], ['oshpazda', 'pishirilmoqda', 'stol_band'])
                                               ? 'ochiq' : 'kassada';
                $orderRow['ochilgan_daqiqa'] = max(0, (int)round((time() - strtotime($orderRow['created_at'])) / 60));
                $orderRow['turi']            = $orderRow['olib_ketish'] ? 'olib_ketish' : 'stol';
            }
            unset($orderRow);

            // ORDER BY sabab birinchi yozuv oddiy stol buyurtmasi; u bo'lmasa
            // eng eski Olib ketish buyurtmasi eski bitta-order interfeysiga beriladi.
            $order = $orders[0];
            $s['band']          = true;
            $s['order_id']      = (int)$order['id'];
            $s['order_status']  = $order['status'];
            $s['orders']        = $orders;
            $s['order_count']   = count($orders);
            $s['olib_ketish']   = count(array_filter($orders, fn($o) => $o['olib_ketish'])) > 0;
            // Kamida bitta tahrirlanadigan order bo'lsa stol ochiq. Barchasi
            // kassaga yuborilganidagina umumiy holat "kassada" bo'ladi.
            $s['holat']         = count(array_filter($orders, fn($o) => $o['holat'] === 'ochiq')) > 0
                                  ? 'ochiq' : 'kassada';
            $s['ochilgan_vaqt'] = $order['created_at'];
            // Daqiqa farqini SERVER tomonida hisoblaymiz — brauzer soat mintaqasiga
            // ishonib qolmaslik uchun (aks holda mijoz qurilmasi vaqti serverdan farq
            // qilsa, "necha daqiqa oldin" noto'g'ri chiqadi).
            $s['ochilgan_daqiqa'] = $order['ochilgan_daqiqa'];
            $s['summa']         = array_sum(array_column($orders, 'summa'));
        } else {
            $s['band']          = false;
            $s['order_id']      = null;
            $s['order_status']  = null;
            $s['olib_ketish']   = false;
            $s['orders']        = [];
            $s['order_count']   = 0;
            $s['holat']         = 'bosh';
            $s['ochilgan_vaqt'] = null;
            $s['ochilgan_daqiqa'] = null;
            $s['summa']         = 0;
        }
    }
    unset($s);
    return $stollar;
}

// ─── STOLSIZ BUYURTMALAR (faqat Dastavka) ──────────────────────
// im_hall_stollar() faqat stolga bog'langan buyurtmalarni ko'rsatadi.
// Faqat Dastavka stol_id=NULL bo'ladi. Olib ketish esa mavjud stolga
// bog'langan alohida order; eski noto'g'ri stolsiz Olib ketish yozuvlari
// zal interfeysiga qaytarilmaydi.
function im_hall_stolsiz_orderlar($db, $filial_id) {
    $filial_id = (int)$filial_id;
    $rows = $db->rows(
        "SELECT id, mijoz_ism, status, olib_ketish, created_at
         FROM im_sotuvchi_order
         WHERE filial_id = $filial_id
           AND stol_id IS NULL
           AND olib_ketish = 0
           AND status NOT IN ('bekor', 'tugallandi')
         ORDER BY id ASC"
    );
    foreach ($rows as &$o) {
        $oid = (int)$o['id'];
        $o['order_id']        = $oid;
        $o['olib_ketish']     = (bool)$o['olib_ketish'];
        $o['order_status']    = $o['status'];
        $o['holat']           = in_array($o['status'], ['oshpazda', 'pishirilmoqda', 'stol_band'])
                                ? 'ochiq' : 'kassada';
        $o['ochilgan_vaqt']   = $o['created_at'];
        $o['ochilgan_daqiqa'] = max(0, (int)round((time() - strtotime($o['created_at'])) / 60));
        $o['summa']           = (float)$db->val(
            "SELECT COALESCE(SUM(soni*narx),0) FROM im_sotuvchi_order_item WHERE order_id=$oid"
        );
    }
    unset($o);
    return $rows;
}

// ─── ZONALAR — stol guruhlari (Teraska / Podval / Zal ...) ────
// Zal xaritasida ustki qatordagi tab (filtr) bo'lib chiqadi.
// Faqat faol zonalar, tartib bo'yicha. "Boshqa" (zona_id=NULL)
// alohida yozuv emas — frontend uni o'zi qo'shadi.
function im_zonalar($db, $filial_id) {
    $filial_id = (int)$filial_id;
    $rows = $db->rows(
        "SELECT id, nomi, rang, ikonka, tartib
         FROM im_zonalar
         WHERE filial_id=$filial_id AND status=1
         ORDER BY tartib ASC, id ASC"
    );
    foreach ($rows as &$z) { $z['id'] = (int)$z['id']; }
    unset($z);
    return $rows;
}

// ─── QOLDIQ REZERVI ──────────────────────────────────────────
// Ofitsant buyurtmani SAQLAGANDA (Pauza/Yuborish) vitrinali mahsulot
// filial qoldig'idan darrov ayiriladi — ya'ni band qilinadi. Shu tufayli
// ikkinchi ofitsant o'sha oxirgi mahsulotni qayta sota olmaydi va
// "yetarli qoldiq yo'q" xatosi kassada emas, buyurtma paytida chiqadi.
//
// Rezerv FAQAT vitrinali mahsulotga tegishli. Retseptli taomlar
// (oshpaz_kerak / retsept_avto) vitrinada turmaydi — ularning xomashyosi
// mos bosqichda (oshpaz qabuli yoki sotuv) yechiladi.
function im_vitrinali($db, $mahsulot_id) {
    $mahsulot_id = (int)$mahsulot_id;
    $r = $db->row("SELECT COALESCE(oshpaz_kerak,0) ok, COALESCE(retsept_avto,0) ra
                   FROM im_mahsulotlar WHERE id=$mahsulot_id");
    if (!$r) return false;
    return !((int)$r['ok'] || (int)$r['ra']);
}

// $delta > 0 → qoldiqdan ayiradi (band qiladi)
// $delta < 0 → qoldiqqa qaytaradi (bandlikni bo'shatadi)
// Yetarli qoldiq bo'lmasa false qaytaradi va hech narsani o'zgartirmaydi.
function im_rezerv($db, $filial_id, $mahsulot_id, $delta) {
    $filial_id   = (int)$filial_id;
    $mahsulot_id = (int)$mahsulot_id;
    $delta       = (float)$delta;
    // Qoldiq ustuni decimal(10,3) — 0.0005 dan kichik o'zgarish bazada
    // umuman aks etmaydi. Uni shu yerda to'xtatamiz, aks holda pastdagi
    // affected() tekshiruvi "yetarli emas" deb yolg'on javob berardi.
    if (abs($delta) < 0.0005)              return true;
    if (!im_vitrinali($db, $mahsulot_id))  return true;   // retseptli — rezerv yo'q

    if ($delta > 0) {
        // ATOMAR: yetarlilik sharti UPDATE ning O'ZIDA turadi.
        // Avval SELECT qilib, keyin UPDATE qilinsa — ikki ofitsant oxirgi
        // porsiyani bir vaqtda saqlaganda IKKALASI ham tekshiruvdan o'tadi
        // va qoldiq manfiyga tushadi. Bitta amalda esa InnoDB qatorni
        // qulflaydi va ikkinchisiga 0 qator qaytadi.
        $db->q("UPDATE im_filial_qoldiq SET soni = soni - $delta
                WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id
                  AND soni >= $delta");
        if ($db->affected() < 1) {
            // Alohida SKU tayyor qoldig'i yetmasa, faol maydalash retsepti
            // orqali butun mahsulotdan avtomatik hosil qilib yana uriniladi.
            try {
                if (!function_exists('im_auto_maydalash_yetkaz')
                    || !im_auto_maydalash_yetkaz(
                        $db, $filial_id, $mahsulot_id, $delta,
                        (int)($_SESSION['im_user_id'] ?? 0)
                    )) return false;
            } catch (Throwable $e) {
                return false;
            }
            $db->q("UPDATE im_filial_qoldiq SET soni = soni - $delta
                    WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id
                      AND soni >= $delta");
            if ($db->affected() < 1) return false;
        }
    } else {
        $qaytar = -$delta;
        $db->q("UPDATE im_filial_qoldiq SET soni = soni + $qaytar
                WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id");
    }
    return true;
}

// Buyurtmadagi BARCHA vitrinali mahsulotlarni qoldiqqa qaytaradi
// (buyurtma bekor qilinganda).
function im_rezerv_bekor($db, $order_id, $filial_id) {
    $order_id = (int)$order_id;
    // MUHIM: qatorning butun `soni` emas, HAQIQATDA band qilingan
    // `rezerv_soni` qaytariladi. Rezerv joriy etilishidan oldin yaratilgan
    // buyurtmalarda u 0 — ular uchun qaytariladigan narsa yo'q.
    // ORDER BY — im_rezerv ichidagi UPDATE im_filial_qoldiq qatorni qulflaydi;
    // tartibsiz bo'lsa parallel bekor qilishlar deadlock berishi mumkin.
    foreach ($db->rows("SELECT mahsulot_id, rezerv_soni FROM im_sotuvchi_order_item
                        WHERE order_id=$order_id ORDER BY mahsulot_id") as $it) {
        im_rezerv($db, $filial_id, (int)$it['mahsulot_id'], -(float)$it['rezerv_soni']);
    }
    $db->q("UPDATE im_sotuvchi_order_item SET rezerv_soni=0 WHERE order_id=$order_id");
}

// ─── FILIAL QOLDIG'IDAN XOMASHYO YECHISH — BEKOR QILINGAN ─────
// Ilgari retsept xomashyosini shu yerdan yechardik. FIFO daftari
// (fifo_lib.php) joriy etilgach barcha chaqiruvchilar im_fifo_take() ga
// o'tdi va bu funksiya o'lik qoldi. Lekin u xavfli edi: qatlam bo'lmasa
// FIFO'siz soni'ni ayirar, qatlam bo'lsa source_id=0 bilan yechardi
// (keyin aniq qaytarib bo'lmaydi). Tasodifiy chaqiruv darhol ko'rinsin
// deb nom saqlanadi, tana esa xato otadi. Bir necha hafta muammosiz
// ishlagach butunlay olib tashlash mumkin.
function im_filial_qoldiq_yech($db, $filial_id, $mahsulot_id, $soni, $nomi = null) {
    throw new Exception('im_filial_qoldiq_yech() bekor qilingan — im_fifo_take($db, $loc, $mahsulot, $soni, $manba, $manba_id) ishlating');
}

// ─── SOTUV YAKUNIY HISOB-KITOBI — YAGONA MANBA ───────────────
// Voucher → xizmat haqi (otsluga) → umumiy chegirma — shu TARTIB va shu
// FORMULALAR dukon/pos.php dagi calcTotals() (JS) bilan AYNAN BIR XIL
// bo'lishi SHART: aks holda kassir ekranida ko'rsatilgan summa
// serverning haqiqiy hisobidan farq qilib, to'lov tekshiruvi
// ("kam to'landi") noto'g'ri xato beradi.
//
// Bu funksiya ATAYLAB "sof" (DB so'rovi yo'q, im_json() bilan to'xtatib
// qo'ymaydi) — voucher amal qilish-qilmasligini (muddat/limit/min summa)
// tekshirish CHAQIRUVCHIDA, bu funksiyaga kelgunga qadar bo'lishi kerak
// (dukon/ajax/sotuv-save.php ga qarang). Sof bo'lgani uchun DB'siz test
// qilish mumkin — tests/run.php shu funksiyani bir nechta vaziyat bilan
// tekshiradi. PHP va JS bir xil manbadan generatsiya qilinmagani uchun
// (loyihada build-tool yo'q) ikkalasini QO'LDA sinxron tuting — birini
// o'zgartirsangiz ikkinchisini ham yangilang va testlarni qayta ishga
// tushiring.
//
// Kirish:
//   $jami_summa      — barcha qatorlar YIG'INDISI, chegirmasiz
//   $jami_chegirma   — item-darajasidagi chegirmalar yig'indisi (voucher/umumiygacha)
//   $tolov_summa     — $jami_summa - $jami_chegirma (voucherdan OLDINGI)
//   $voucher         — ['tur'=>'foiz'|'summa','qiymat'=>float,'max_chegirma'=>float] yoki null
//   $xizmat_foiz     — 0..100, "yoqilganmi" tekshiruvi allaqachon chaqiruvchida hal qilingan
//   $qator_jami      — xizmat haqi ulushi uchun: qatorlar summasi (item-chegirma bilan)
//   $qator_stol      — shundan FAQAT stolda iste'mol qilingan (saboyga olinmagan) qism
//   $umumiy_chegirma — kassir kiritgan qo'shimcha summa (so'mda)
//   $is_nasiya_sale  — true bo'lsa umumiy chegirma qo'llanmaydi
//
// Chiqish: ['voucher_chegirma','jami_chegirma','tolov_summa','eff_ch_foiz','xizmat_summa','umumiy_chegirma']
function im_sotuv_yakun_hisobla(
    float $jami_summa, float $jami_chegirma, float $tolov_summa,
    ?array $voucher, float $xizmat_foiz, float $qator_jami, float $qator_stol,
    float $umumiy_chegirma, bool $is_nasiya_sale
): array {
    // 1) Voucher
    $voucher_chegirma = 0.0;
    if ($voucher) {
        if (($voucher['tur'] ?? '') === 'foiz') {
            $voucher_chegirma = round($tolov_summa * ((float)($voucher['qiymat'] ?? 0) / 100));
            $max = (float)($voucher['max_chegirma'] ?? 0);
            if ($max > 0 && $voucher_chegirma > $max) $voucher_chegirma = $max;
        } else {
            $voucher_chegirma = min((float)($voucher['qiymat'] ?? 0), $tolov_summa);
        }
        $voucher_chegirma = round($voucher_chegirma);
        $tolov_summa   -= $voucher_chegirma;
        $jami_chegirma += $voucher_chegirma;
    }

    $eff_ch_foiz = $jami_summa > 0 ? round($jami_chegirma / $jami_summa * 100, 2) : 0;

    // 2) Xizmat haqi — faqat stolda iste'mol qilingan ulushga
    $xizmat_ulush = $qator_jami > 0 ? ($qator_stol / $qator_jami) : 0;
    $xizmat_summa = $xizmat_foiz > 0
        ? round($tolov_summa * $xizmat_ulush * $xizmat_foiz / 100)
        : 0.0;
    $tolov_summa += $xizmat_summa;

    // 3) Umumiy chegirma
    $umumiy_qollangan = 0.0;
    if ($umumiy_chegirma > 0 && !$is_nasiya_sale) {
        $umumiy_qollangan = min($umumiy_chegirma, $tolov_summa);
        $jami_chegirma += $umumiy_qollangan;
        $tolov_summa   -= $umumiy_qollangan;
        $eff_ch_foiz = $jami_summa > 0 ? round($jami_chegirma / $jami_summa * 100, 2) : 0;
    }

    return [
        'voucher_chegirma' => $voucher_chegirma,
        'jami_chegirma'    => $jami_chegirma,
        'tolov_summa'      => $tolov_summa,
        'eff_ch_foiz'      => $eff_ch_foiz,
        'xizmat_summa'     => $xizmat_summa,
        'umumiy_chegirma'  => $umumiy_qollangan,
    ];
}

// Chek darajasidagi voucher/umumiy chegirma va xizmat haqini sotuv
// qatorlariga taqsimlangan holda qaytaradigan SQL ifoda. Shu ifoda mahsulot,
// ofitsant va boshqa kesimlardagi tushumlar yig'indisini s.tolov_summa bilan
// aynan tenglashtiradi. Xizmat haqi faqat zalda iste'mol qilingan ulushga,
// invoice chegirmasi esa barcha qatorlarga sotuv summasi bo'yicha taqsimlanadi.
function im_sotuv_qator_tushum_sql($saleAlias = 's', $itemAlias = 'si') {
    foreach ([$saleAlias, $itemAlias] as $alias) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $alias)) {
            throw new InvalidArgumentException('Sotuv SQL aliasi noto‘g‘ri');
        }
    }

    $all = 'im_rev_all';
    $line = "(COALESCE($itemAlias.chegirma_narxi,$itemAlias.sotish_narxi,0)*$itemAlias.soni)";
    $allTotal = "(SELECT COALESCE(SUM(COALESCE($all.chegirma_narxi,$all.sotish_narxi,0)*$all.soni),0)
                  FROM im_sotuv_items $all WHERE $all.sotuv_id=$saleAlias.id)";
    $allItemDiscount = "(SELECT COALESCE(SUM((COALESCE($all.sotish_narxi,0)-COALESCE($all.chegirma_narxi,$all.sotish_narxi,0))*$all.soni),0)
                         FROM im_sotuv_items $all WHERE $all.sotuv_id=$saleAlias.id)";
    $invoiceDiscount = "GREATEST(0,COALESCE($saleAlias.chegirma_summa,0)-$allItemDiscount)";

    $dineLine = "(COALESCE($itemAlias.chegirma_narxi,$itemAlias.sotish_narxi,0)
                  *GREATEST(0,$itemAlias.soni-COALESCE($itemAlias.olib_ketish_soni,0)))";
    $allDineTotal = "(SELECT COALESCE(SUM(COALESCE($all.chegirma_narxi,$all.sotish_narxi,0)
                          *GREATEST(0,$all.soni-COALESCE($all.olib_ketish_soni,0))),0)
                      FROM im_sotuv_items $all WHERE $all.sotuv_id=$saleAlias.id)";

    return "($line
        - CASE WHEN $allTotal>0 THEN ($invoiceDiscount)*$line/$allTotal ELSE 0 END
        + CASE WHEN $allDineTotal>0 THEN COALESCE($saleAlias.xizmat_summa,0)*$dineLine/$allDineTotal ELSE 0 END)";
}

function im_f($s) {
    return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8');
}

// ─── HTML ATRIBUTI ICHIDAGI JS SATRI UCHUN ───────────────────
// onclick="foo('...')" kabi joylarda im_f() YETARLI EMAS va bu
// ko'rinmaydigan tuzoq:
//   im_f("');alert(1);//")  →  &#039;);alert(1);//
// HTML parser atribut qiymatini JS ga uzatishdan OLDIN dekodlaydi,
// ya'ni &#039; qaytib apostrofga aylanadi va qo'shtirnoqdan chiqib
// ketish baribir ishlaydi.
//
// Bu yerda json_encode HEX bayroqlari bilan ishlatiladi: apostrof,
// qo'shtirnoq, kichik/katta belgi va ampersand \uXXXX ko'rinishiga
// o'tadi — ular HTML dekodlashdan keyin ham qo'shtirnoq bo'lib
// qolmaydi. Tashqi qo'shtirnoqlar olib tashlanadi, chunki shablonda
// ular allaqachon yozilgan.
//
// DIQQAT: bu izohda PHP yopuvchi tegi YOZILMAYDI — u satr izohi
// ichida ham PHP rejimini tugatadi va butun faylni buzadi.
function im_js($s) {
    $j = json_encode((string)$s,
        JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    return $j === false ? '' : substr($j, 1, -1);
}

function im_money($sum) {
    return number_format((float)$sum, 0, '.', ' ');
}

function im_date($dt = null) {
    return $dt ? date('d.m.Y', strtotime($dt)) : date('d.m.Y');
}

function im_datetime($dt = null) {
    return $dt ? date('d.m.Y H:i', strtotime($dt)) : date('d.m.Y H:i');
}

function im_usd_kurs() {
    global $link;
    $r = mysqli_fetch_assoc(mysqli_query($link,
        "SELECT usd_kurs FROM im_valyuta_kurs WHERE tasdiqlandi=1 ORDER BY sana DESC, id DESC LIMIT 1"
    ));
    return $r ? (float)$r['usd_kurs'] : 12700;
}

function im_sozlama($kalit, $default = '') {
    global $link;
    $k   = mysqli_real_escape_string($link, $kalit);
    $res = mysqli_query($link, "SELECT qiymat FROM im_sozlamalar WHERE kalit='$k' LIMIT 1");
    if (!$res) return $default;
    $r = mysqli_fetch_assoc($res);
    return $r ? $r['qiymat'] : $default;
}

function im_sozlamalar_all() {
    global $link;
    $res = mysqli_query($link, "SELECT kalit, qiymat FROM im_sozlamalar");
    if (!$res) return [];
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) $out[$r['kalit']] = $r['qiymat'];
    return $out;
}

function im_json($status, $msg = '', $data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $status, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── CYBER DB KLASI ──────────────────────────────────────────
class Cyber {
    private $link;
    private $transaction = false;

    public function __construct() {
        $this->link = mysqli_connect(im_HOST, im_USER, im_PASS, im_DB);
        if (!$this->link) die('DB xatosi: ' . mysqli_connect_error());
        mysqli_set_charset($this->link, 'utf8mb4');
        // MUHIM: bu ulanish yuqoridagi $link'dan MUSTAQIL — soat mintaqasi
        // shu yerda ham alohida o'rnatilishi shart, aks holda NOW()/TIMESTAMP
        // ustunlari MySQL standart (boshqa) mintaqasida hisoblanadi va butun
        // tizimda vaqt hisob-kitoblari (masalan "necha daqiqa oldin") chalkashadi.
        mysqli_query($this->link, "SET time_zone = '+05:00'");
    }

    public function f($s)   { return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8'); }
    public function error() { return mysqli_error($this->link); }
    public function affected() { return mysqli_affected_rows($this->link); }
    public function inTransaction() { return $this->transaction; }
    public function begin() {
        if ($this->transaction) throw new Exception('Ichma-ich tranzaksiya mumkin emas');
        if (!mysqli_begin_transaction($this->link)) throw new Exception($this->error());
        $this->transaction = true;
    }
    public function commit() {
        if (!mysqli_commit($this->link)) throw new Exception($this->error());
        $this->transaction = false;
    }
    public function rollback() { mysqli_rollback($this->link); $this->transaction = false; }

    public function q($sql) {
        $res = mysqli_query($this->link, $sql);
        if (!$res) error_log('[IMezon SQL] ' . mysqli_error($this->link) . ' | ' . substr($sql,0,200));
        if (!$res && $this->transaction) {
            // 1213 = deadlock (InnoDB tranzaksiyani o'zi qaytargan), 1205 = lock wait
            // timeout (faqat so'rov qaytarilgan) — ikkalasida ham chaqiruvchining catch
            // bloki rollback qiladi. Xom "Deadlock found" o'rniga tushunarli xabar.
            $errno = mysqli_errno($this->link);
            if ($errno === 1213 || $errno === 1205) {
                throw new Exception('Tizim shu mahsulot ustida band edi (boshqa kassa/oshxona amali). Qaytadan bosing.');
            }
            throw new Exception('DB yozuvi bajarilmadi: ' . $this->error());
        }
        return $res;
    }

    public function row($sql)  {
        $res = $this->q($sql);
        return ($res && is_object($res)) ? (mysqli_fetch_assoc($res) ?: null) : null;
    }

    public function rows($sql) {
        $res = $this->q($sql); $out = [];
        if ($res && is_object($res)) while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
        return $out;
    }

    public function val($sql)  {
        $res  = $this->q($sql);
        $r    = ($res && is_object($res)) ? mysqli_fetch_row($res) : null;
        return $r ? $r[0] : null;
    }

    public function insert($sql) { return $this->q($sql) ? mysqli_insert_id($this->link) : 0; }

    public function __destruct() { if ($this->link) mysqli_close($this->link); }
}

require_once __DIR__ . '/fifo_lib.php';

// ─── AVTOMATIK MAYDALASH ────────────────────────────────────
// Chiqish mahsuloti (masalan Non chorak) tayyor qoldiqda yetmasa,
// faol maydalash retseptidan uning asosiy mahsuloti va konversiyasi topiladi.
function im_auto_maydalash_holati($db, $filial_id, $chiqish_id) {
    $filial_id=(int)$filial_id; $chiqish_id=(int)$chiqish_id;
    if ($filial_id<=0 || $chiqish_id<=0) return null;
    $r=$db->row(
        "SELECT r.id AS retsept_id, r.nomi AS retsept_nomi,
                r.mahsulot_id AS kirish_id, r.chiqish_soni AS kirish_soni,
                ri.soni AS bir_martadagi_chiqish, km.nomi AS kirish_nomi
         FROM im_retseptlar r
         JOIN im_retsept_items ri ON ri.retsept_id=r.id
         JOIN im_mahsulotlar km ON km.id=r.mahsulot_id
         WHERE r.tur='maydalash' AND r.status=1
           AND ri.mahsulot_id=$chiqish_id AND ri.soni>0 AND r.chiqish_soni>0
         ORDER BY r.id DESC LIMIT 1"
    );
    if (!$r) return null;
    $kirish_id=(int)$r['kirish_id'];
    $kirish=(float)$db->val("SELECT COALESCE(soni,0) FROM im_filial_qoldiq WHERE filial_id=$filial_id AND mahsulot_id=$kirish_id");
    $tayyor=(float)$db->val("SELECT COALESCE(soni,0) FROM im_filial_qoldiq WHERE filial_id=$filial_id AND mahsulot_id=$chiqish_id");
    $kirish_soni=(float)$r['kirish_soni'];
    $chiqish_soni=(float)$r['bir_martadagi_chiqish'];
    $marta=$kirish_soni>0 ? (int)floor(($kirish+0.000001)/$kirish_soni) : 0;
    $r['tayyor_qoldiq']=$tayyor;
    $r['auto_imkon']=round($marta*$chiqish_soni,3);
    $r['jami_imkon']=round($tayyor+$r['auto_imkon'],3);
    return $r;
}

// Kerakli chiqish qoldig'ini retsept bo'yicha avtomatik yetkazadi.
// Chaqiruvchi tranzaksiya ichida bo'lishi shart; barcha FIFO va audit
// yozuvlari buyurtma/sotuv bilan birga commit yoki rollback bo'ladi.
function im_auto_maydalash_yetkaz($db, $filial_id, $chiqish_id, $kerak, $xodim_id=0) {
    global $link;
    $filial_id=(int)$filial_id; $chiqish_id=(int)$chiqish_id;
    $kerak=round((float)$kerak,3); $xodim_id=(int)$xodim_id;
    if ($kerak<=0) return true;
    if (!$db->inTransaction()) throw new Exception('Avtomatik maydalash tranzaksiya ichida bajarilishi kerak');

    $r=im_auto_maydalash_holati($db,$filial_id,$chiqish_id);
    if (!$r) return false;
    $rid=(int)$r['retsept_id']; $kirish_id=(int)$r['kirish_id'];
    $kirish_1=round((float)$r['kirish_soni'],3);
    $chiqish_1=round((float)$r['bir_martadagi_chiqish'],3);
    if ($kirish_1<=0 || $chiqish_1<=0 || $kirish_id===$chiqish_id) {
        throw new Exception('Avtomatik maydalash retsepti noto‘g‘ri');
    }

    // Qulf tartibi — mahsulot id bo'yicha o'sib (butun loyihada bitta qoida:
    // ishlab-save/order-qabul ham ORDER BY mahsulot_id). Aks holda kirish>chiqish
    // bo'lgan retseptda parallel maydalash bilan deadlock ehtimoli bor edi.
    foreach ([min($kirish_id,$chiqish_id), max($kirish_id,$chiqish_id)] as $lock_pid) {
        im_fifo_lock($db,$filial_id,$lock_pid);
    }
    // FOR UPDATE — qatorlar yuqorida allaqachon qulflangan, lekin REPEATABLE READ
    // da ODDIY o'qish tranzaksiyaning eski snapshotini qaytaradi. Qulflab o'qish
    // esa doim joriy commit qilingan holatni beradi.
    $tayyor_row=$db->row("SELECT COALESCE(soni,0) AS s FROM im_filial_qoldiq
                          WHERE filial_id=$filial_id AND mahsulot_id=$chiqish_id FOR UPDATE");
    $tayyor=(float)($tayyor_row['s'] ?? 0);
    if ($tayyor+0.000001 >= $kerak) return true;

    $yetishmaydi=$kerak-$tayyor;
    $marta=(int)ceil(($yetishmaydi-0.000001)/$chiqish_1);
    $kirish_kerak=round($marta*$kirish_1,3);
    $kirish_row=$db->row("SELECT COALESCE(soni,0) AS s FROM im_filial_qoldiq
                          WHERE filial_id=$filial_id AND mahsulot_id=$kirish_id FOR UPDATE");
    $kirish_mavjud=(float)($kirish_row['s'] ?? 0);
    if ($marta<1 || $kirish_mavjud+0.000001<$kirish_kerak) {
        throw new Exception("«{$r['kirish_nomi']}» avtomatik maydalash uchun yetarli emas");
    }

    $nom=mysqli_real_escape_string($link,(string)$r['retsept_nomi']);
    $izoh=mysqli_real_escape_string($link,"Avtomatik maydalash: {$nom}");
    $xodim_sql=$xodim_id>0 ? $xodim_id : 'NULL';
    $id=$db->insert(
        "INSERT INTO im_ishlab_chiqarish
          (retsept_id,tur,filial_id,soni,mahsulot_id,chiqish_soni,izoh,xodim_id)
         VALUES ($rid,'maydalash',$filial_id,$marta,$kirish_id,$kirish_kerak,'$izoh',$xodim_sql)"
    );
    if (!$id) throw new Exception('Avtomatik maydalash auditi yozilmadi');

    $take=im_fifo_take($db,$filial_id,$kirish_id,$kirish_kerak,'ishlab',$id);
    $total=(float)$take['total'];
    $items=$db->rows(
        "SELECT ri.mahsulot_id,ri.soni,m.birlik
         FROM im_retsept_items ri JOIN im_mahsulotlar m ON m.id=ri.mahsulot_id
         WHERE ri.retsept_id=$rid AND ri.soni>0 ORDER BY ri.mahsulot_id"
    );
    if (!$items) throw new Exception('Maydalash chiqishlari topilmadi');
    $weights=0.0;
    foreach($items as $it) $weights+=round((float)$it['soni']*$marta,3);
    if ($weights<=0) throw new Exception('Maydalash chiqish miqdori noto‘g‘ri');

    $allocated=0.0; $i=0; $count=count($items);
    foreach($items as $it) {
        $mid=(int)$it['mahsulot_id']; $qty=round((float)$it['soni']*$marta,3);
        if ($mid===$kirish_id || $qty<=0) throw new Exception('Maydalash chiqishi noto‘g‘ri');
        $cost=(++$i===$count) ? $total-$allocated : $total*$qty/$weights;
        $unit=$cost/$qty; $allocated+=$cost;
        im_fifo_receive($db,$filial_id,$mid,$qty,$unit,'ishlab',$id);
        if (!$db->q("INSERT INTO im_ishlab_chiqarish_items (ishlab_id,tur,mahsulot_id,partiya_item_id,soni,kelish_narxi) VALUES ($id,'chiqish',$mid,NULL,$qty,$unit)")) {
            throw new Exception('Maydalash chiqish auditi yozilmadi');
        }
    }
    $input_unit=$kirish_kerak>0 ? $total/$kirish_kerak : 0;
    if (!$db->q("INSERT INTO im_ishlab_chiqarish_items (ishlab_id,tur,mahsulot_id,partiya_item_id,soni,kelish_narxi) VALUES ($id,'kirish',$kirish_id,NULL,$kirish_kerak,$input_unit)")) {
        throw new Exception('Maydalash kirish auditi yozilmadi');
    }
    if (!$db->q("UPDATE im_ishlab_chiqarish SET tannarx=$total WHERE id=$id")) {
        throw new Exception('Maydalash tannarxi yozilmadi');
    }
    return true;
}

// ─── MAHSULOT QOLDIG'I QAYERLARDA BOR (FIFO qatlamlaridan) ───
// Arxivlash/o'chirishdan oldingi tekshiruv. Ombor (location_id=0) va barcha
// filiallar bo'yicha remaining_qty>0 qatlamlarni joy nomi bilan qaytaradi:
//   ['Ombor: 12', 'Chilonzor filiali: 0.7']   — bo'sh massiv = qoldiq yo'q.
function im_mahsulot_qoldiq_joylar($db, $mahsulot_id) {
    $mahsulot_id = (int)$mahsulot_id;
    if ($mahsulot_id <= 0) return [];
    $rows = $db->rows(
        "SELECT location_id, SUM(remaining_qty) AS q FROM im_fifo_layers
         WHERE mahsulot_id=$mahsulot_id AND cancelled=0 AND remaining_qty>0
         GROUP BY location_id HAVING q>0.0005 ORDER BY location_id"
    );
    $out = [];
    foreach ($rows as $r) {
        $loc = (int)$r['location_id'];
        $joy = $loc === 0 ? 'Ombor'
             : ((string)$db->val("SELECT nomi FROM im_filiallar WHERE id=$loc") ?: "Filial #$loc");
        $out[] = $joy . ': ' . rtrim(rtrim(number_format((float)$r['q'], 3, '.', ''), '0'), '.');
    }
    return $out;
}

// ─── QULFLANADIGAN MAHSULOTLAR TO'PLAMI (deadlock tartibi) ───
// Loyihada YAGONA qoida: bir tranzaksiya FIFO qulflarini mahsulot id
// bo'yicha O'SISH tartibida oladi (im_fifo_lock ichida esa avval
// im_filial_qoldiq qatori, keyin im_fifo_locks qatori).
//
// Checkout / order-save ilgari filialning BARCHA qoldiq qatorlarini
// qulflardi — deadlock'dan himoya sifatida to'g'ri, lekin butun filialni
// ketma-ketlashtirar edi (bitta kassa yopilmaguncha ofitsantlar buyurtma
// saqlay olmasdi). Endi faqat haqiqatda tegiladigan mahsulotlar qulflanadi.
//
// To'plam = berilgan mahsulotlar
//         + retsept_avto mahsulotning xomashyolari (sotuvda shular yechiladi)
//         + avto-maydalash kirish mahsuloti (tayyor SKU yetmasa ishlatiladi).
// Tranzaksiya ICHIDA chaqirilishi kerak — retseptlar shu tranzaksiyada o'qiladi.
function im_qulf_mahsulotlari($db, $filial_id, array $mahsulot_ids) {
    $filial_id = (int)$filial_id;
    $kerak = [];
    foreach ($mahsulot_ids as $mid) {
        $mid = (int)$mid;
        if ($mid > 0) $kerak[$mid] = true;
    }
    if (!$kerak) return [];

    foreach (array_keys($kerak) as $mid) {
        $tur = $db->row("SELECT COALESCE(retsept_avto,0) ra, COALESCE(qozon_rejim,0) qr
                         FROM im_mahsulotlar WHERE id=$mid");
        if (!$tur) continue;

        // 1) retsept_avto — xomashyo sotuv paytida yechiladi
        if ((int)$tur['ra'] === 1) {
            // FOR UPDATE — sotuv-save tanasi ham aynan shunday tanlaydi. Oddiy
            // o'qish snapshotdan kelib chiqib BOSHQA retseptni tanlashi va
            // natijada qulflanmagan xomashyo ishlatilishi mumkin edi.
            $rid_row = $db->row("SELECT id FROM im_retseptlar
                                 WHERE mahsulot_id=$mid AND tur='ishlab_chiqarish' AND status=1
                                 ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $rid = (int)($rid_row['id'] ?? 0);
            if ($rid) {
                foreach ($db->rows("SELECT DISTINCT mahsulot_id FROM im_retsept_items WHERE retsept_id=$rid") as $ri) {
                    $x = (int)$ri['mahsulot_id'];
                    if ($x > 0) $kerak[$x] = true;
                }
            }
        }

        // 2) avto-maydalash: kirish mahsuloti VA retseptning BARCHA chiqishlari.
        //    im_auto_maydalash_yetkaz() har bir chiqish mahsulotiga
        //    im_fifo_receive() qiladi (config.php: maydalash chiqish tsikli) —
        //    ya'ni faqat savatdagi chiqishni qulflash yetarli emas. Masalan
        //    "Non butun → Non yarim + Non chorak" retseptida yarimni sotgan
        //    kassa chorakni ham qulflaydi; aks holda ikki kassa bir-birini
        //    teskari tartibda kutib deadlock bo'lardi.
        if ((int)$tur['qr'] !== 1 && $filial_id > 0 && function_exists('im_auto_maydalash_holati')) {
            $auto = im_auto_maydalash_holati($db, $filial_id, $mid);
            if ($auto && (int)$auto['kirish_id'] > 0) {
                $kerak[(int)$auto['kirish_id']] = true;
                $arid = (int)$auto['retsept_id'];
                if ($arid) {
                    foreach ($db->rows("SELECT DISTINCT mahsulot_id FROM im_retsept_items WHERE retsept_id=$arid") as $ro) {
                        $x = (int)$ro['mahsulot_id'];
                        if ($x > 0) $kerak[$x] = true;
                    }
                }
            }
        }
    }

    $ids = array_keys($kerak);
    sort($ids, SORT_NUMERIC);   // TARTIB — deadlock himoyasining o'zagi
    return $ids;
}

// To'plamni o'sish tartibida qulflaydi (im_fifo_lock: qoldiq qatori → lock qatori).
function im_qulfla_mahsulotlar($db, $filial_id, array $mahsulot_ids) {
    foreach (im_qulf_mahsulotlari($db, $filial_id, $mahsulot_ids) as $pid) {
        im_fifo_lock($db, (int)$filial_id, (int)$pid);
    }
}
