<?php
// ============================================================
//  IMezon — DEMO MA'LUMOT GENERATORI (30 kunlik "haqiqiy" faoliyat)
//
//  Ishga tushirish (faqat CLI):
//    im_DB=imezon_db php tools/seed-demo.php --reset --days=30 --yes
//
//  Parametrlar:
//    --reset      tranzaksion jadvallarni TOZALAYDI (sozlamalar, xodimlar,
//                 filial, kassa qatorlari, zona/stollar SAQLANADI)
//    --days=N     necha kunlik faoliyat (standart 30, bugungacha)
//    --scale=X    chek soni ko'paytiruvchisi (standart 1.0 ≈ kuniga 80–120)
//    --seed=N     tasodifiy sonlar urug'i (standart 2026) — takrorlanuvchan
//    --yes        tasdiq (usiz faqat reja chiqariladi)
//
//  QOIDA: hech qanday daftar jadvaliga (im_fifo_*, im_filial_qoldiq,
//  rezerv) to'g'ridan-to'g'ri INSERT yo'q — hamma narsa ilovaning o'z
//  kutubxonalari (fifo_lib, ishlab_lib, qozon_lib, im_rezerv) orqali va
//  endpointlar qanday yozsa shunday hujjat qatorlari bilan yoziladi.
//  Vaqt: MariaDB `SET timestamp` orqali orqaga suriladi — NOW() /
//  CURRENT_TIMESTAMP ustunlari simulyatsiya vaqtini oladi.
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
set_time_limit(0);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/qayta-ishlash/ishlab_lib.php';
require_once $ROOT . '/oshpaz/qozon_lib.php';
require_once $ROOT . '/fifo_reports.php';

// ── Parametrlar ─────────────────────────────────────────────
$OPT = getopt('', ['reset', 'days::', 'scale::', 'seed::', 'yes', 'end::']);
$DAYS  = max(3, (int)($OPT['days'] ?? 30));
$SCALE = max(0.1, (float)($OPT['scale'] ?? 1.0));
$SEED  = (int)($OPT['seed'] ?? 2026);
$RESET = isset($OPT['reset']);
$YES   = isset($OPT['yes']);
$END_DATE = isset($OPT['end']) ? (string)$OPT['end'] : date('Y-m-d');   // oxirgi kun (bugun)
$REAL_NOW = time();
$LIVE = ($END_DATE === date('Y-m-d'));   // bugun — hozirgi soatgacha "jonli" holat (ochiq smena/qozon/stollar)
mt_srand($SEED);

$db = new Cyber();
$DBNAME = (string)mysqli_query($link, 'SELECT DATABASE()')->fetch_row()[0];

function say($s) { fwrite(STDOUT, $s . "\n"); }
function warn($s) { fwrite(STDERR, "  ! " . $s . "\n"); }
function esc($s) { global $link; return mysqli_real_escape_string($link, (string)$s); }
function rnd($a, $b) { return mt_rand($a, $b); }
function rndf($a, $b, $dec = 3) { return round($a + ($b - $a) * (mt_rand() / mt_getrandmax()), $dec); }
function chance($p) { return (mt_rand() / mt_getrandmax()) < $p; }
function pick(array $a) { return $a[array_rand($a)]; }
function wpick(array $weights) {   // ['key'=>w, ...] → key
    $sum = array_sum($weights); $r = (mt_rand() / mt_getrandmax()) * $sum;
    foreach ($weights as $k => $w) { $r -= $w; if ($r <= 0) return $k; }
    return array_key_last($weights);
}

// ── Simulyatsiya soati ──────────────────────────────────────
// Ikkala ulanish ($link — im_log/im_sozlama; $db — hamma narsa) uchun
// SET timestamp. Shundan keyin NOW(), CURDATE(), DEFAULT CURRENT_TIMESTAMP
// va ON UPDATE CURRENT_TIMESTAMP shu vaqtni qaytaradi.
$NOW = time();
function clock_set($ts) {
    global $NOW, $link, $db;
    $NOW = (int)$ts;
    mysqli_query($link, "SET timestamp=$NOW");
    $db->q("SET timestamp=$NOW");
}
function clock_reset() { global $link, $db; mysqli_query($link, "SET timestamp=DEFAULT"); $db->q("SET timestamp=DEFAULT"); }
function sd($fmt = 'Y-m-d') { global $NOW; return date($fmt, $NOW); }     // sim date
function sdt() { global $NOW; return date('Y-m-d H:i:s', $NOW); }

// ── "Sessiya" — kim ish qilyapti ─────────────────────────────
$im_user_id = 1; $im_ism = 'Admin'; $im_filial_id = 1; $im_rol = 'admin';
$XODIM = [];   // id => row
function actor($id) {
    global $im_user_id, $im_ism, $im_rol, $XODIM;
    $im_user_id = (int)$id; $im_ism = $XODIM[$id]['ism'] ?? ('#' . $id); $im_rol = $XODIM[$id]['rol'] ?? 'admin';
    $_SESSION['im_user_id'] = $im_user_id;
}
if (!function_exists('im_log')) {
    // ximoya.php dagi im_log ning CLI nusxasi — im_istoriya ga yozadi
    function im_log($jadval, $ob_id, $amal, $eski = null, $yangi = null, $izoh = '') {
        global $link, $im_user_id, $im_ism, $im_filial_id;
        $ok = ['insert','update','delete','login','login_xato','logout','export','other'];
        if (!in_array($amal, $ok, true)) $amal = 'other';
        $e = fn($v) => $v === null ? 'NULL' : "'" . mysqli_real_escape_string($link, is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE)) . "'";
        try { mysqli_query($link, "INSERT INTO im_istoriya (jadval,ob_id,amal,eski,yangi,izoh,xodim_id,xodim_ism,filial_id,ip_adres,user_agent)
            VALUES ('" . mysqli_real_escape_string($link, $jadval) . "'," . (int)$ob_id . ",'$amal'," . $e($eski) . "," . $e($yangi) . ",
                    '" . mysqli_real_escape_string($link, mb_substr($izoh, 0, 255)) . "'," . (int)$im_user_id . ",'" . mysqli_real_escape_string($link, $im_ism) . "',"
                    . (int)$im_filial_id . ",'127.0.0.1','seed-demo')"); } catch (Throwable $e) {}
    }
}

// ── Tranzaksiya o'rami ───────────────────────────────────────
$ERRORS = 0;
function tx(callable $fn, $label = '') {
    global $db, $ERRORS;
    $db->begin();
    try { $r = $fn(); $db->commit(); return $r; }
    catch (Throwable $e) {
        $db->rollback(); $ERRORS++;
        warn("[" . sdt() . "] $label: " . $e->getMessage());
        return null;
    }
}

// ═══════════════════════════════════════════════════════════
//  0. REJA / TASDIQ
// ═══════════════════════════════════════════════════════════
$mavjud_sotuv = (int)$db->val("SELECT COUNT(*) FROM im_sotuvlar");
$mavjud_mah   = (int)$db->val("SELECT COUNT(*) FROM im_mahsulotlar");
$END_TS   = strtotime($END_DATE . ' 23:59:59');
$START_TS = strtotime(date('Y-m-d', $END_TS - ($DAYS - 1) * 86400) . ' 00:00:00');
say("Baza: $DBNAME | mavjud: $mavjud_sotuv chek, $mavjud_mah mahsulot");
say("Davr: " . date('Y-m-d', $START_TS) . " → " . date('Y-m-d', $END_TS) . " ($DAYS kun), scale=$SCALE, seed=$SEED");
if (($mavjud_sotuv > 0 || $mavjud_mah > 0) && !$RESET) {
    say("Bazada ma'lumot bor. Toza boshlash uchun --reset bering."); exit(1);
}
if (!$YES) { say("Ishga tushirish uchun --yes qo'shing."); exit(0); }

// ═══════════════════════════════════════════════════════════
//  1. RESET — tranzaksion jadvallar tozalanadi
// ═══════════════════════════════════════════════════════════
if ($RESET) {
    $keep = ['im_sozlamalar', 'im_xodimlar', 'im_filiallar', 'im_kassa', 'im_zonalar', 'im_stollar',
             'im_fifo_meta', 'im_ai_suhbat', 'im_ai_log'];
    $tables = array_map(fn($r) => array_values($r)[0], $db->rows("SHOW TABLES"));
    $db->q("SET FOREIGN_KEY_CHECKS=0");
    foreach ($tables as $t) {
        if (in_array($t, $keep, true)) continue;
        $db->q("TRUNCATE TABLE `$t`");
    }
    $db->q("SET FOREIGN_KEY_CHECKS=1");
    $db->q("UPDATE im_kassa SET naqd_balans=0, karta_balans=0, bank_balans=0, usd_balans=0");
    // Demo xodimlar oldingi ishga tushirishdan qolgan bo'lsa
    $db->q("DELETE FROM im_xodimlar WHERE login IN ('sotuvchi2','oshpaz2','kassir2')");
    $db->q("DELETE FROM im_stollar WHERE id>4");
    $db->q("DELETE FROM im_zonalar WHERE id>2");
    say("Reset: " . (count($tables) - count($keep)) . " ta jadval tozalandi (sozlamalar/xodimlar/filial/kassa saqlandi)");
}

// ═══════════════════════════════════════════════════════════
//  2. MASTER MA'LUMOTLAR
// ═══════════════════════════════════════════════════════════
clock_set($START_TS - 3 * 86400 + 9 * 3600);   // hammasi 3 kun oldin, ertalab 9:00 da kiritilgan
actor(1);

// ── Xodimlar (mavjud 6 ta + 3 ta yangi) ──────────────────────
if ($RESET) {   // demo uchun realroq ismlar (login/parol o'zgarmaydi)
    foreach (['dukon1' => 'Aziza Karimova', 'sotuvchi1' => 'Dilshod Rahimov', 'sklad1' => 'Jamshid Xolmatov', 'oshpaz1' => 'Rustam Qosimov'] as $l => $ism)
        $db->q("UPDATE im_xodimlar SET ism='" . esc($ism) . "' WHERE login='$l'");
}
foreach ($db->rows("SELECT id, ism, login, rol FROM im_xodimlar") as $x) $XODIM[(int)$x['id']] = $x;
$DEMO_PAROL = 'demo2026';
$hash = password_hash($DEMO_PAROL, PASSWORD_DEFAULT);
$yangi_xodim = [
    ['Sardor Alimov',   'sotuvchi2', 'sotuvchi', '+998901234512'],
    ['Bahodir Yusupov', 'oshpaz2',   'oshpaz',   '+998901234513'],
    ['Nilufar Rashidova','kassir2',  'kassir',   '+998901234514'],
];
foreach ($yangi_xodim as [$ism, $login, $rol, $tel]) {
    $id = (int)$db->insert("INSERT INTO im_xodimlar (ism, login, parol, rol, telefon, status, filial_id)
        VALUES ('" . esc($ism) . "','$login','" . esc($hash) . "','$rol','$tel',1,1)");
    $XODIM[$id] = ['id' => $id, 'ism' => $ism, 'login' => $login, 'rol' => $rol];
}
$byLogin = fn($l) => (int)array_values(array_filter($XODIM, fn($x) => $x['login'] === $l))[0]['id'];
$ADMIN = $byLogin('admin1'); $KASSIR1 = $byLogin('dukon1'); $KASSIR2 = $byLogin('kassir2');
$SOTUVCHI1 = $byLogin('sotuvchi1'); $SOTUVCHI2 = $byLogin('sotuvchi2');
$SKLAD = $byLogin('sklad1'); $OSHPAZ1 = $byLogin('oshpaz1'); $OSHPAZ2 = $byLogin('oshpaz2');
$BOSH_KASSIR = $byLogin('kassa');

// ── Zona va stollar (4 ta mavjud + 7 ta yangi) ───────────────
$db->q("INSERT INTO im_zonalar (id, filial_id, nomi, rang, ikonka, tartib, status) VALUES (3, 1, 'Teraska', '#0ea5e9', 'bi-tree', 3, 1)");
$stol_yangi = [['Stol 4', 2], ['Stol 5', 2], ['Stol 6', 2], ['T-1', 3], ['T-2', 3], ['T-3', 3], ['T-4', 3]];
$t = 5;
foreach ($stol_yangi as [$n, $z]) { $db->q("INSERT INTO im_stollar (filial_id, zona_id, nomi, tartib, status) VALUES (1, $z, '$n', " . ($t++) . ", 1)"); }
$STOLLAR = $db->rows("SELECT id, nomi, zona_id FROM im_stollar WHERE filial_id=1 AND status=1 ORDER BY id");
$STOL_NOMI = []; foreach ($STOLLAR as $s_) $STOL_NOMI[(int)$s_['id']] = $s_['nomi'];

// ── Kategoriyalar ────────────────────────────────────────────
$KAT = [];
foreach ([['Milliy taomlar', '#e2b96f'], ['Ichimliklar', '#0ea5e9'], ['Non va salatlar', '#22c55e'],
          ['Shirinliklar', '#ec4899'], ['Xomashyo', '#64748b']] as $i => [$n, $r]) {
    $KAT[$i + 1] = (int)$db->insert("INSERT INTO im_kategoriyalar (nomi, rang, tavsif, status) VALUES ('" . esc($n) . "','$r','',1)");
}

// ── Mahsulotlar ──────────────────────────────────────────────
// [kalit, nomi, kat, birlik, qadam, sotiladi, oshpaz, retsept_avto, qozon, faqat_ishlab, sotuv_narx, ulg_min, ulg_narx, kelish(xomashyo uchun)]
$MAH_DEF = [
    // — Oshxona (oshpaz_kerak=1) —
    ['osh',      'Osh (palov)',           1, 'porsiya', 1, 1, 1, 0, 1, 0, 45000, 0, 0],
    ['lagmon',   "Lag'mon",               1, 'porsiya', 1, 1, 1, 0, 0, 0, 38000, 0, 0],
    ['shashlik', 'Shashlik (mol)',        1, 'porsiya', 1, 1, 1, 0, 0, 0, 25000, 0, 0],
    ['tshashlik','Tovuq shashlik',        1, 'porsiya', 1, 1, 1, 0, 0, 0, 20000, 0, 0],
    ['manti',    'Manti (4 dona)',        1, 'porsiya', 1, 1, 1, 0, 0, 0, 32000, 0, 0],
    ['somsa',    "Somsa (go'shtli)",      1, 'dona',    1, 1, 1, 0, 0, 0, 12000, 10, 10000],
    ['mastava',  'Mastava',               1, 'porsiya', 1, 1, 1, 0, 0, 0, 28000, 0, 0],
    ['shorva',   "Sho'rva",               1, 'porsiya', 1, 1, 1, 0, 0, 0, 30000, 0, 0],
    // — Retsept bilan sotuvda yechiladi (retsept_avto) —
    ['choy',     'Choy (choynak)',        2, 'dona', 1, 1, 0, 1, 0, 0,  5000, 0, 0],
    ['lchoy',    'Limonli choy',          2, 'dona', 1, 1, 0, 1, 0, 0,  8000, 0, 0],
    ['kofe',     'Kofe',                  2, 'dona', 1, 1, 0, 1, 0, 0, 15000, 0, 0],
    ['achchiq',  'Achchiq-chuchuk salat', 3, 'porsiya', 1, 1, 0, 1, 0, 0, 12000, 0, 0],
    // — Vitrina (tayyor tovar) —
    ['cola05',   'Coca-Cola 0.5',         2, 'dona', 1, 1, 0, 0, 0, 0, 10000, 6, 9000],
    ['cola15',   'Coca-Cola 1.5',         2, 'dona', 1, 1, 0, 0, 0, 0, 18000, 0, 0],
    ['fanta',    'Fanta 0.5',             2, 'dona', 1, 1, 0, 0, 0, 0, 10000, 0, 0],
    ['suv',      'Suv 0.5 (gazsiz)',      2, 'dona', 1, 1, 0, 0, 0, 0,  5000, 0, 0],
    ['ayron',    'Ayron 0.5',             2, 'dona', 1, 1, 0, 0, 0, 0,  8000, 0, 0],
    ['kompot',   'Kompot 0.5',            2, 'dona', 1, 1, 0, 0, 0, 0,  7000, 0, 0],
    ['non',      'Non (obi non)',         3, 'dona', 1, 1, 0, 0, 0, 0,  4000, 0, 0],
    ['patir',    'Patir non',             3, 'dona', 1, 1, 0, 0, 0, 0,  6500, 0, 0],
    ['nonyarim', 'Non yarim',             3, 'dona', 1, 1, 0, 0, 0, 0,  2500, 0, 0],
    ['chakchak', 'Chak-chak (150g)',      4, 'dona', 1, 1, 0, 0, 0, 0, 15000, 0, 0],
    // — Xomashyo (sotilmaydi) — oxirgi ustun: kelish narxi —
    ['guruch',   'Guruch (lazer)',        5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0, 14000],
    ['gosht',    "Mol go'shti",           5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0, 95000],
    ['tovuq',    "Tovuq go'shti",         5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0, 38000],
    ['sabzi',    'Sabzi',                 5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0,  6000],
    ['piyoz',    'Piyoz',                 5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0,  4000],
    ['pomidor',  'Pomidor',               5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0,  9000],
    ['bodring',  'Bodring',               5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0,  8000],
    ['kartoshka','Kartoshka',             5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0,  5000],
    ['qalampir', "Bulg'or qalampiri",     5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0, 12000],
    ['yog',      "Paxta yog'i",           5, 'litr', 0.001, 0, 0, 0, 0, 0, 0, 0, 0, 18000],
    ['un',       'Un (oliy nav)',         5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0,  7000],
    ['tuxum',    'Tuxum',                 5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0, 22000],
    ['tuz',      'Tuz',                   5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0,  3000],
    ['ziravor',  'Ziravorlar (zira, murch)',5,'kg',  0.001, 0, 0, 0, 0, 0, 0, 0, 0, 60000],
    ['noxat',    "No'xat",                5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0, 12000],
    ['choyb',    'Choy bargi (qora)',     5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0,120000],
    ['kofeb',    'Kofe (maydalangan)',    5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0,180000],
    ['shakar',   'Shakar',                5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0, 11000],
    ['limon',    'Limon',                 5, 'kg',   0.001, 0, 0, 0, 0, 0, 0, 0, 0, 25000],
    ['xamir',    "Lag'mon xamiri (tayyor)",5,'kg',   0.001, 0, 0, 0, 0, 1, 0, 0, 0,     0],   // faqat ishlab chiqariladi
];
$P = []; $KELISH = []; $NARX = []; $MINFO = [];
$bc = 1000;
foreach ($MAH_DEF as $d) {
    [$k, $nomi, $kat, $birlik, $qadam, $sotiladi, $oshpaz, $ra, $qozon, $fi, $narx, $ulg_min, $ulg_narx] = $d;
    $kelish = $d[13] ?? 0;
    $barcode = 'im-' . sd('Ymd') . '-' . str_pad((string)($bc++), 4, '0', STR_PAD_LEFT);
    $id = (int)$db->insert("INSERT INTO im_mahsulotlar
        (nomi, kategoriya_id, barcode, birlik, sotuv_qadami, tavsif, sotiladi, faqat_ishlab_chiqarish, oshpaz_kerak, retsept_avto, qozon_rejim, status)
        VALUES ('" . esc($nomi) . "', {$KAT[$kat]}, '$barcode', '$birlik', $qadam, '', $sotiladi, $fi, $oshpaz, $ra, $qozon, 1)");
    if ($narx > 0) $db->q("INSERT INTO im_narxlar (mahsulot_id, sotish_narxi) VALUES ($id, $narx)");
    if ($ulg_min > 0 && $ulg_narx > 0) $db->q("INSERT INTO im_narx_ulgurji (mahsulot_id, min_soni, ulgurji_narxi) VALUES ($id, $ulg_min, $ulg_narx)");
    $P[$k] = $id; $KELISH[$k] = $kelish; $NARX[$k] = $narx;
    $MINFO[$k] = ['id' => $id, 'nomi' => $nomi, 'birlik' => $birlik, 'oshpaz' => $oshpaz, 'ra' => $ra, 'qozon' => $qozon, 'sotiladi' => $sotiladi];
}
$BYID = []; foreach ($MINFO as $k => $m) $BYID[$m['id']] = $k;

// ── Retseptlar ───────────────────────────────────────────────
// ishlab_chiqarish: mahsulot = tayyor taom, items = 1 porsiya uchun xomashyo (kg/litr)
$RETSEPT_DEF = [
    'osh'      => ['guruch' => 0.11, 'gosht' => 0.09, 'sabzi' => 0.11, 'piyoz' => 0.035, 'yog' => 0.035, 'noxat' => 0.01, 'tuz' => 0.004, 'ziravor' => 0.002],
    'lagmon'   => ['xamir' => 0.22, 'gosht' => 0.07, 'sabzi' => 0.05, 'piyoz' => 0.05, 'pomidor' => 0.06, 'qalampir' => 0.04, 'yog' => 0.02, 'ziravor' => 0.003],
    'shashlik' => ['gosht' => 0.11, 'piyoz' => 0.03, 'ziravor' => 0.002],
    'tshashlik'=> ['tovuq' => 0.14, 'piyoz' => 0.03, 'ziravor' => 0.002],
    'manti'    => ['un' => 0.14, 'gosht' => 0.12, 'piyoz' => 0.08, 'yog' => 0.01, 'tuz' => 0.002],
    'somsa'    => ['un' => 0.07, 'gosht' => 0.045, 'piyoz' => 0.03, 'yog' => 0.012, 'tuz' => 0.001],
    'mastava'  => ['guruch' => 0.04, 'gosht' => 0.06, 'kartoshka' => 0.08, 'sabzi' => 0.04, 'piyoz' => 0.04, 'pomidor' => 0.04, 'yog' => 0.015, 'tuz' => 0.003],
    'shorva'   => ['gosht' => 0.09, 'kartoshka' => 0.12, 'sabzi' => 0.05, 'piyoz' => 0.04, 'pomidor' => 0.04, 'tuz' => 0.003],
    'choy'     => ['choyb' => 0.006],
    'lchoy'    => ['choyb' => 0.006, 'limon' => 0.03, 'shakar' => 0.01],
    'kofe'     => ['kofeb' => 0.014, 'shakar' => 0.01],
    'achchiq'  => ['pomidor' => 0.15, 'bodring' => 0.1, 'piyoz' => 0.03, 'tuz' => 0.001],
    'xamir'    => ['un' => 0.75, 'tuxum' => 0.08, 'tuz' => 0.005],   // 1 kg xamir
];
$RETSEPT = [];
foreach ($RETSEPT_DEF as $k => $items) {
    $birlik_r = in_array($MINFO[$k]['birlik'], ['kg', 'litr'], true) ? $MINFO[$k]['birlik'] : 'dona';
    $rid = (int)$db->insert("INSERT INTO im_retseptlar (nomi, tur, mahsulot_id, chiqish_soni, birlik, izoh, xodim_id)
        VALUES ('" . esc($MINFO[$k]['nomi'] . ' — retsept') . "', 'ishlab_chiqarish', {$P[$k]}, 1.000, '$birlik_r', '', $ADMIN)");
    foreach ($items as $ing => $soni) {
        $b = $MINFO[$ing]['birlik'] === 'litr' ? 'litr' : 'kg';
        $db->q("INSERT INTO im_retsept_items (retsept_id, mahsulot_id, soni, birlik) VALUES ($rid, {$P[$ing]}, $soni, '$b')");
    }
    $RETSEPT[$k] = $rid;
}
// maydalash: Non (butun) → 2 × Non yarim (avto-maydalash sotuvda ishlaydi)
$rid = (int)$db->insert("INSERT INTO im_retseptlar (nomi, tur, mahsulot_id, chiqish_soni, birlik, izoh, xodim_id)
    VALUES ('Non → 2 ta yarim', 'maydalash', {$P['non']}, 1.000, 'dona', 'Avtomatik: yarim non yetmasa butun non bo''linadi', $ADMIN)");
$db->q("INSERT INTO im_retsept_items (retsept_id, mahsulot_id, soni, birlik) VALUES ($rid, {$P['nonyarim']}, 2, 'dona')");
$RETSEPT['non_maydalash'] = $rid;

// ── Setlar ───────────────────────────────────────────────────
$SETLAR = [];
$set_def = [
    ['Biznes lanch', 52000, '#e2b96f', ['osh' => 1, 'achchiq' => 1, 'choy' => 1]],
    ['Oilaviy set',  118000, '#8b5cf6', ['osh' => 2, 'somsa' => 2, 'cola15' => 1]],
];
foreach ($set_def as $i => [$n, $narx, $rang, $items]) {
    $sid = (int)$db->insert("INSERT INTO im_setlar (nomi, narxi, rang, filial_id, tartib, aktiv) VALUES ('" . esc($n) . "', $narx, '$rang', 1, " . ($i + 1) . ", 1)");
    $t = 1;
    foreach ($items as $k => $soni) $db->q("INSERT INTO im_set_items (set_id, mahsulot_id, soni, tartib) VALUES ($sid, {$P[$k]}, $soni, " . ($t++) . ")");
    $SETLAR[$sid] = ['id' => $sid, 'nomi' => $n, 'narxi' => $narx, 'items' => $items];
}

// ── Postavshiklar ────────────────────────────────────────────
$PS = [];
$ps_def = [
    'gosht'   => ["Chorsu go'sht bozori (Anvar aka)", '+998901112233', 'Toshkent', 'Anvar Ergashev'],
    'don'     => ['Toshkent guruch-don savdo', '+998933334455', 'Toshkent', 'Sherzod Karimov'],
    'sabzavot'=> ["Qo'yliq sabzavot ombori", '+998977776655', 'Toshkent', 'Rustam Toshmatov'],
    'ichimlik'=> ['Ichimliklar distribyutori "Sharq"', '+998712001020', 'Toshkent', 'Madina Yusupova'],
    'non'     => ['Nonvoyxona "Hazrati Xizr"', '+998909998877', 'Toshkent', 'Bobur aka'],
];
foreach ($ps_def as $k => [$n, $tel, $sh, $kont]) {
    $PS[$k] = (int)$db->insert("INSERT INTO im_postavshiklar (nomi, telefon, email, shahar, kontakt_ism, kontakt_telefon, izoh, status)
        VALUES ('" . esc($n) . "','$tel','','$sh','" . esc($kont) . "','$tel','',1)");
}
$PS_OF = ['gosht' => 'gosht', 'tovuq' => 'gosht',
    'guruch' => 'don', 'un' => 'don', 'tuz' => 'don', 'ziravor' => 'don', 'noxat' => 'don', 'shakar' => 'don', 'choyb' => 'don', 'kofeb' => 'don',
    'sabzi' => 'sabzavot', 'piyoz' => 'sabzavot', 'pomidor' => 'sabzavot', 'bodring' => 'sabzavot', 'kartoshka' => 'sabzavot', 'qalampir' => 'sabzavot', 'limon' => 'sabzavot', 'tuxum' => 'sabzavot', 'yog' => 'don',
    'cola05' => 'ichimlik', 'cola15' => 'ichimlik', 'fanta' => 'ichimlik', 'suv' => 'ichimlik', 'ayron' => 'ichimlik', 'kompot' => 'ichimlik', 'chakchak' => 'ichimlik',
    'non' => 'non', 'patir' => 'non'];
// vitrina tovarlarining kelish narxi
foreach (['cola05' => 6500, 'cola15' => 12000, 'fanta' => 6500, 'suv' => 2800, 'ayron' => 5000, 'kompot' => 4000, 'non' => 2500, 'patir' => 4200, 'chakchak' => 9000] as $k => $v) $KELISH[$k] = $v;

// ── Mijoz toifalari va mijozlar ──────────────────────────────
$TOIFA = [];
foreach ([['Oddiy', 0, '#6c757d'], ['Doimiy', 5, '#0ea5e9'], ['VIP', 10, '#e2b96f']] as [$n, $ch, $r]) {
    $TOIFA[$n] = (int)$db->insert("INSERT INTO im_mijoz_toifalari (nomi, chegirma_foiz, rang, tavsif, status) VALUES ('$n', $ch, '$r', '', 1)");
}
$MIJOZ = [];   // id => ['toifa'=>..., 'nasiya'=>bool]
$ismlar = ['Aziz Rahimov', 'Dilnoza Karimova', 'Jasur Tursunov', 'Malika Yusupova', 'Otabek Saidov', 'Nigora Abdullayeva',
    'Sherzod Mirzayev', 'Kamola Ergasheva', 'Bekzod Nazarov', 'Feruza Xolmatova', 'Ulug\'bek Qodirov', 'Zarina Tosheva',
    'Farrux Ismoilov', 'Gulnora Sattorova', 'Rustam Yoqubov', 'Madina Olimova', 'Sanjar Xasanov', 'Dildora Nurmatova',
    'Botir Alimov', 'Sevara Mahmudova', 'Javlon Rashidov', 'Nodira Umarova', 'Temur Boboyev', 'Laylo Hamidova'];
foreach ($ismlar as $i => $ism) {
    $toifa = $i < 4 ? 'VIP' : ($i < 12 ? 'Doimiy' : 'Oddiy');
    $tel = '+9989' . rnd(0, 9) . str_pad((string)rnd(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    $id = (int)$db->insert("INSERT INTO im_mijozlar (ism, telefon, toifa_id, manzil, izoh, status)
        VALUES ('" . esc($ism) . "', '$tel', {$TOIFA[$toifa]}, '" . esc(pick(['Chilonzor', 'Yunusobod', 'Sergeli', 'Mirzo Ulug\'bek', 'Yakkasaroy', 'Olmazor'])) . " tumani', '', 1)");
    $MIJOZ[$id] = ['toifa' => $toifa, 'nasiya' => $i < 10];
}

// ── Ishchilar (maosh oluvchilar) ─────────────────────────────
$WORKERS = [];
foreach ([['Rustam Qosimov (oshpaz)', 'boshqa', 4500000], ['Bahodir Yusupov (oshpaz)', 'boshqa', 4000000],
          ['Dilshod Rahimov (ofitsant)', 'sotuvchi', 3500000], ['Sardor Alimov (ofitsant)', 'sotuvchi', 3500000],
          ['Nilufar Rashidova (kassir)', 'kassir', 3800000], ['Gulnora Ergasheva (farrosh)', 'tozalovchi', 2000000],
          ['Akmal Toshev (haydovchi)', 'haydovchi', 3000000]] as [$n, $l, $st]) {
    $WORKERS[] = ['id' => (int)$db->insert("INSERT INTO im_workers (ism, telefon, lavozim, filial_id, oylik_stavka, izoh, status)
        VALUES ('" . esc($n) . "', '+99890" . rnd(1000000, 9999999) . "', '$l', 1, $st, '', 1)"), 'ism' => $n, 'stavka' => $st];
}
say("Master: " . count($P) . " mahsulot, " . count($RETSEPT) . " retsept, " . count($SETLAR) . " set, " . count($PS) . " postavshik, " . count($MIJOZ) . " mijoz, " . count($STOLLAR) . " stol");

// ═══════════════════════════════════════════════════════════
//  3. OQIMLAR — endpointlarning CLI nusxalari
// ═══════════════════════════════════════════════════════════
$FILIAL = 1;

// ── Kirim partiyasi: ochish + qatorlar + yopish (qabul-save / item-add / qabul-close) ──
function f_partiya($ps_key, array $lines, $qabul_filial = 0, $muddat_kun = 14, $faktura = '') {
    global $db, $PS, $P, $NARX, $im_user_id;
    return tx(function () use ($db, $PS, $P, $NARX, $im_user_id, $ps_key, $lines, $qabul_filial, $muddat_kun, $faktura) {
        $ps_id = $PS[$ps_key]; $sana = sd();
        $fk = $faktura !== '' ? $faktura : ('F-' . sd('ymd') . '-' . rnd(100, 999));
        $pid = (int)$db->insert("INSERT INTO im_partiyalar (postavshik_id, sana, faktura_nomer, izoh, holat, xodim_id, jami_summa, tolov_turi, usd_summa, usd_kurs, tolandi, qarz_qoldi, qabul_filial_id)
            VALUES ($ps_id, '$sana', '$fk', '', 'ochiq', $im_user_id, 0, 'naqd', 0, 0, 0, 0, $qabul_filial)");
        foreach ($lines as $k => [$qty, $cost]) {
            if ($cost <= 0 || $qty <= 0) throw new RuntimeException("partiya $k: narx/miqdor 0");
            $sotish = $NARX[$k] ?? 0;
            $db->q("INSERT INTO im_partiya_items (partiya_id, mahsulot_id, soni, kelish_narxi, sotish_narxi, sklad_qoldi, dukon_qoldi)
                    VALUES ($pid, {$P[$k]}, $qty, $cost, $sotish, 0, 0)");
        }
        // ── qabul-close ──
        $items = $db->rows("SELECT * FROM im_partiya_items WHERE partiya_id=$pid ORDER BY mahsulot_id, id FOR UPDATE");
        $jami = 0.0; foreach ($items as $it) $jami += (float)$it['soni'] * (float)$it['kelish_narxi'];
        $muddat = date('Y-m-d', strtotime(sd() . " +$muddat_kun days"));
        $db->q("UPDATE im_partiyalar SET holat='yopiq', jami_summa=$jami, tolov_turi='qarz', usd_summa=0, usd_kurs=0, tolandi=0, qarz_qoldi=$jami, izoh='' WHERE id=$pid");
        $db->q("INSERT INTO im_postavshik_qarz (postavshik_id, partiya_id, qarz_summa, tolandi, qoldiq, muddat, status)
                VALUES ($ps_id, $pid, $jami, 0, $jami, '$muddat', 'ochiq')");
        foreach ($items as $it) {
            $itm_id = (int)$it['id']; $mid = (int)$it['mahsulot_id']; $qty = (float)$it['soni']; $cost = (float)$it['kelish_narxi'];
            im_fifo_receive($db, $qabul_filial, $mid, $qty, $cost, 'partiya', $pid, $itm_id);
            $db->q("UPDATE im_partiya_items SET sklad_qoldi=" . ($qabul_filial === 0 ? $qty : 0) . ", dukon_qoldi=" . ($qabul_filial > 0 ? $qty : 0) . " WHERE id=$itm_id");
            if ($qabul_filial > 0) {
                $sn = (float)$db->val("SELECT COALESCE((SELECT sotish_narxi FROM im_narxlar WHERE mahsulot_id=$mid LIMIT 1),
                                       (SELECT sotish_narxi FROM im_partiya_items WHERE id=$itm_id AND sotish_narxi>0), 0)");
                if ($sn > 0) $db->q("UPDATE im_filial_qoldiq SET sotuv_narxi=$sn WHERE filial_id=$qabul_filial AND mahsulot_id=$mid");
                $db->q("INSERT INTO im_sklad_send (mahsulot_id, partiya_item_id, soni, xodim_id, filial_id) VALUES ($mid, $itm_id, $qty, $im_user_id, $qabul_filial)");
            }
        }
        im_log('im_partiyalar', $pid, 'update', ['holat' => 'ochiq'], ['holat' => 'yopiq', 'jami_summa' => $jami], "Partiya yopildi — " . count($items) . " ta mahsulot");
        return $pid;
    }, "partiya $ps_key");
}

// ── Ombor → filial jo'natish (send-save) ─────────────────────
function f_send($key, $soni) {
    global $db, $P, $FILIAL, $im_user_id;
    $mid = $P[$key]; $filial_id = $FILIAL;
    return tx(function () use ($db, $mid, $soni, $filial_id, $im_user_id) {
        $send_id = (int)$db->insert("INSERT INTO im_sklad_send (mahsulot_id, partiya_item_id, soni, xodim_id, filial_id) VALUES ($mid, 0, 0, $im_user_id, $filial_id)");
        $taken = im_fifo_take($db, 0, $mid, $soni, 'sklad_send', $send_id);
        $allocated = 0.0;
        foreach ($taken['allocations'] as $a) {
            $item_id = (int)($a['partiya_item_id'] ?? 0); $qty = (float)$a['qty']; $cost = (float)$a['unit_cost'];
            if (!$item_id) throw new RuntimeException("Ombor qatlami partiyasiz (mahsulot $mid)");
            if ($allocated == 0.0) { $log_id = $send_id; $db->q("UPDATE im_sklad_send SET partiya_item_id=$item_id, soni=$qty WHERE id=$log_id"); }
            else $log_id = (int)$db->insert("INSERT INTO im_sklad_send (mahsulot_id, partiya_item_id, soni, xodim_id, filial_id) VALUES ($mid, $item_id, $qty, $im_user_id, $filial_id)");
            im_fifo_receive($db, $filial_id, $mid, $qty, $cost, 'sklad_send', $log_id, $item_id);
            $allocated += $qty;
        }
        $sn = (float)$db->val("SELECT COALESCE((SELECT sotish_narxi FROM im_narxlar WHERE mahsulot_id=$mid LIMIT 1),
            (SELECT pi.sotish_narxi FROM im_partiya_items pi JOIN im_partiyalar p ON p.id=pi.partiya_id
             WHERE pi.mahsulot_id=$mid AND pi.sotish_narxi>0 AND p.holat='yopiq' ORDER BY pi.id DESC LIMIT 1), 0)");
        if ($sn > 0) $db->q("UPDATE im_filial_qoldiq SET sotuv_narxi=$sn WHERE filial_id=$filial_id AND mahsulot_id=$mid");
        return $send_id;
    }, "send $key $soni");
}

// ── Ishlab chiqarish / maydalash (ishlab-save) ───────────────
function f_ishlab($retsept_id, $runs, $loc, $xodim, $izoh = '') {
    global $db;
    return tx(function () use ($db, $retsept_id, $runs, $loc, $xodim, $izoh) {
        $r = $db->row("SELECT * FROM im_retseptlar WHERE id=$retsept_id AND status=1");
        $items = $db->rows("SELECT ri.* FROM im_retsept_items ri WHERE ri.retsept_id=$retsept_id ORDER BY ri.mahsulot_id");
        $tur = $r['tur']; $product = (int)$r['mahsulot_id']; $amount = round((float)$r['chiqish_soni'] * $runs, 3);
        $inputs = []; $outputs = [];
        if ($tur === 'ishlab_chiqarish') {
            foreach ($items as $it) { $mid = (int)$it['mahsulot_id']; $inputs[$mid] = ($inputs[$mid] ?? 0) + (float)$it['soni'] * $runs; }
            $outputs[$product] = ['qty' => $amount, 'weight' => 1];
        } else {
            $inputs[$product] = $amount;
            foreach ($items as $it) { $q = round((float)$it['soni'] * $runs, 3); $outputs[(int)$it['mahsulot_id']] = ['qty' => $q, 'weight' => $q]; }
        }
        $fil = $loc ?: 'NULL';
        $id = (int)$db->insert("INSERT INTO im_ishlab_chiqarish (retsept_id,tur,filial_id,soni,mahsulot_id,chiqish_soni,izoh,xodim_id)
            VALUES ($retsept_id,'$tur',$fil,$runs,$product,$amount,'" . esc($izoh) . "',$xodim)");
        $lock_ids = array_values(array_unique(array_map('intval', array_merge(array_keys($inputs), array_keys($outputs)))));
        sort($lock_ids, SORT_NUMERIC);
        foreach ($lock_ids as $lid) im_fifo_lock($db, $loc, $lid);
        $total = 0;
        foreach ($inputs as $mid => $need) {
            $need = round($need, 3);
            $take = im_fifo_take($db, $loc, $mid, $need, 'ishlab', $id);
            $total += $take['cost'];
            im_ishlab_audit_item($db, $id, 'kirish', $mid, $need, $take['unit_cost']);
        }
        $weights = array_sum(array_column($outputs, 'weight')); $allocated = 0; $index = 0;
        foreach ($outputs as $mid => $out) {
            $cost = (++$index === count($outputs)) ? $total - $allocated : $total * $out['weight'] / $weights;
            $unit = $cost / $out['qty']; $allocated += $cost;
            im_fifo_receive($db, $loc, $mid, $out['qty'], $unit, 'ishlab', $id);
            im_ishlab_audit_item($db, $id, 'chiqish', $mid, $out['qty'], $unit);
        }
        $tannarx = $tur === 'ishlab_chiqarish' ? $total / $amount : $total;
        $db->q("UPDATE im_ishlab_chiqarish SET tannarx=$tannarx WHERE id=$id");
        im_log('im_ishlab_chiqarish', $id, 'insert', null, ['tur' => $tur, 'retsept_id' => $retsept_id, 'soni' => $runs], 'FIFO ishlab chiqarish');
        return $id;
    }, "ishlab $retsept_id");
}

// ── Qozon ochish (qozon-och) ─────────────────────────────────
function f_qozon_och($key, $moljal, array $xomashyo /*key=>soni*/, $oshpaz) {
    global $db, $P, $MINFO, $FILIAL;
    $filial_id = $FILIAL; $mahsulot_id = $P[$key];
    return tx(function () use ($db, $P, $MINFO, $filial_id, $mahsulot_id, $moljal, $xomashyo, $oshpaz) {
        $bugun = sd();
        $satrlar = [];
        foreach ($xomashyo as $k => $soni) { $satrlar[$P[$k]] = ['mahsulot_id' => $P[$k], 'soni' => round($soni, 3), 'birlik' => $MINFO[$k]['birlik']]; }
        ksort($satrlar, SORT_NUMERIC);
        $lock_ids = array_map('intval', array_keys($satrlar)); $lock_ids[] = $mahsulot_id;
        $lock_ids = array_values(array_unique($lock_ids)); sort($lock_ids, SORT_NUMERIC);
        foreach ($lock_ids as $lid) im_fifo_lock($db, $filial_id, $lid);
        foreach ($satrlar as $mid => $s) im_fifo_preview($db, $filial_id, $mid, $s['soni']);
        $qozon_id = (int)$db->insert("INSERT INTO im_osh_qozon (filial_id, mahsulot_id, sana, moljal_porsiya, holat, ochgan_xodim_id)
            VALUES ($filial_id, $mahsulot_id, '$bugun', $moljal, 'ochiq', $oshpaz)");
        $jami = 0;
        foreach ($satrlar as $s) {
            $take = im_fifo_take($db, $filial_id, (int)$s['mahsulot_id'], (float)$s['soni'], 'qozon', $qozon_id);
            $jami += $take['cost'];
            $db->q("INSERT INTO im_osh_qozon_items (qozon_id, mahsulot_id, soni, birlik, kelish_narxi, summa)
                    VALUES ($qozon_id, {$s['mahsulot_id']}, {$s['soni']}, '{$s['birlik']}', {$take['unit_cost']}, {$take['cost']})");
        }
        im_fifo_receive($db, $filial_id, $mahsulot_id, $moljal, $jami / $moljal, 'qozon', $qozon_id);
        $jami = round($jami, 2);
        $db->q("UPDATE im_osh_qozon SET xomashyo_summa=$jami WHERE id=$qozon_id");
        im_log('im_osh_qozon', $qozon_id, 'insert', null, ['mahsulot_id' => $mahsulot_id, 'moljal' => $moljal, 'xomashyo_summa' => $jami], "Osh qozoni ochildi — xomashyo $jami so'm");
        return $qozon_id;
    }, "qozon-och");
}

// ── Qozon yopish (qozon-yop) ─────────────────────────────────
function f_qozon_yop($qozon_id, $qoldi, $isrofmi, $oshpaz, $izoh = '') {
    global $db, $FILIAL;
    $filial_id = $FILIAL;
    return tx(function () use ($db, $filial_id, $qozon_id, $qoldi, $isrofmi, $oshpaz, $izoh) {
        $yakun = im_qozon_yakuniy_tannarx($db, $qozon_id, $filial_id, $qoldi, $isrofmi);
        $db->q("UPDATE im_osh_qozon SET holat='yopildi', qoldi_porsiya=$qoldi, qoldi_isrofmi=$isrofmi, izoh='" . esc($izoh) . "',
                yopgan_xodim_id=$oshpaz, yopildi_vaqt=NOW() WHERE id=$qozon_id AND holat='ochiq'");
        if ($db->affected() < 1) throw new Exception('Qozon yopilmadi');
        im_log('im_osh_qozon', $qozon_id, 'update', ['holat' => 'ochiq'], ['holat' => 'yopildi', 'qoldi_porsiya' => $qoldi, 'haqiqiy_porsiya' => $yakun['haqiqiy'], 'yakuniy_tannarx' => $yakun['tannarx']], "Osh qozoni yopildi — qoldi $qoldi porsiya");
        return $yakun;
    }, "qozon-yop $qozon_id");
}

// ── Narx (order-save.php: im_sotuvchi_narx ning nusxasi) ─────
function seed_narx($mahsulot_id, $soni, $set_id = 0) {
    global $db, $FILIAL;
    $filial_id = $FILIAL; $mahsulot_id = (int)$mahsulot_id; $set_id = (int)$set_id;
    if ($set_id > 0) {
        $set = $db->row("SELECT narxi FROM im_setlar WHERE id=$set_id AND filial_id=$filial_id AND aktiv=1");
        if (!$set) return null;
        $set_items = $db->rows("SELECT si.mahsulot_id, si.soni, COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0) AS narx
             FROM im_set_items si LEFT JOIN im_narxlar n ON n.mahsulot_id = si.mahsulot_id
             LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id = si.mahsulot_id AND fq.filial_id = $filial_id WHERE si.set_id=$set_id");
        $asl = 0.0; foreach ($set_items as $si) $asl += (float)$si['narx'] * (float)$si['soni'];
        foreach ($set_items as $si) {
            if ((int)$si['mahsulot_id'] !== $mahsulot_id) continue;
            return ['asos' => $asl > 0 ? round((float)$si['narx'] * ((float)$set['narxi'] / $asl)) : round((float)$set['narxi'] / count($set_items)), 'ulg' => null];
        }
        return null;
    }
    $r = $db->row("SELECT COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0) AS narx, COALESCE(nu.min_soni, 0) AS ulg_min, COALESCE(nu.ulgurji_narxi, 0) AS ulg_narx
         FROM im_mahsulotlar m LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id=m.id AND fq.filial_id=$filial_id
         LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id LEFT JOIN im_narx_ulgurji nu ON nu.mahsulot_id=m.id AND nu.aktiv=1 WHERE m.id=$mahsulot_id LIMIT 1");
    if (!$r) return null;
    $ulg = null;
    if ((float)$r['ulg_min'] > 0 && (float)$soni >= (float)$r['ulg_min'] && (float)$r['ulg_narx'] > 0) $ulg = (float)$r['ulg_narx'];
    return ['asos' => (float)$r['narx'], 'ulg' => $ulg];
}

function seed_item_log($order_id, $item_id, $mid, $xodim, $amal, $eski, $yangi, $nomi) {
    global $db, $FILIAL;
    $db->q("INSERT INTO im_order_item_log (order_id, item_id, mahsulot_id, xodim_id, filial_id, amal, eski_soni, yangi_soni, nomi)
            VALUES ($order_id, " . ($item_id ?: 'NULL') . ", $mid, $xodim, $FILIAL, '$amal', $eski, $yangi, '" . esc($nomi) . "')");
}
function seed_xodim_log($xodim, $amal, $order_id, $mijoz, $summa, $izoh = '') {
    global $db, $FILIAL;
    $db->q("INSERT INTO im_xodim_log (xodim_id, filial_id, amal, order_id, mijoz_ism, summa, izoh)
            VALUES ($xodim, $FILIAL, '$amal', " . ($order_id ?: 'NULL') . ", " . ($mijoz !== '' ? "'" . esc($mijoz) . "'" : 'NULL') . ", " . (float)$summa . ", " . ($izoh !== '' ? "'" . esc($izoh) . "'" : 'NULL') . ")");
}

// ── Ofitsant buyurtmasi (order-save.php: action=hold|create) ─
//  $items: [['mid'=>..., 'soni'=>..., 'set_id'=>0], ...] — orderning TO'LIQ savati
function f_order_save($action, $order_id, $stol_id, $olib_ketish, $mijoz_ism, array $items, $sotuvchi) {
    global $db, $FILIAL;
    $filial_id = $FILIAL;
    return tx(function () use ($db, $filial_id, $action, $order_id, $stol_id, $olib_ketish, $mijoz_ism, $items, $sotuvchi) {
        $order_id = (int)$order_id; $stol_id = (int)$stol_id; $olib_ketish = $olib_ketish ? 1 : 0;
        $stol_id_sql = $stol_id ?: 'NULL';
        if ($stol_id) { if (!$db->row("SELECT id FROM im_stollar WHERE id=$stol_id AND filial_id=$filial_id AND status=1 FOR UPDATE")) throw new Exception('Stol topilmadi'); }
        $old = null; $db_items = [];
        if ($order_id > 0) {
            $old = $db->row("SELECT id, status, stol_id, olib_ketish FROM im_sotuvchi_order WHERE id=$order_id AND filial_id=$filial_id FOR UPDATE");
            if (!$old) throw new Exception('Order topilmadi');
            if (!im_order_tahrirlanadi($old['status'])) throw new Exception("Order tahrirlanmaydi ({$old['status']})");
            foreach ($db->rows("SELECT id, mahsulot_id, COALESCE(set_id,0) AS sid, soni, rezerv_soni, tayyorlandi_soni
                                FROM im_sotuvchi_order_item WHERE order_id=$order_id ORDER BY mahsulot_id, sid FOR UPDATE") as $lr) {
                $db_items[(int)$lr['mahsulot_id'] . ':' . (int)$lr['sid']] = ['id' => (int)$lr['id'], 'mid' => (int)$lr['mahsulot_id'], 'sid' => (int)$lr['sid'],
                    'soni' => (float)$lr['soni'], 'rezerv' => (float)$lr['rezerv_soni'], 'tayyor' => (float)$lr['tayyorlandi_soni']];
            }
        }
        $lock_ids = array_map(fn($i) => (int)$i['mid'], $items);
        foreach ($db_items as $r) $lock_ids[] = $r['mid'];
        im_qulfla_mahsulotlar($db, $filial_id, $lock_ids);

        // check_items_kitchen — locked_soni bazadan
        $has_new_kitchen = false;
        foreach ($items as $it) {
            $k = (int)$it['mid'] . ':' . (int)($it['set_id'] ?? 0);
            $locked = $db_items[$k]['tayyor'] ?? 0.0; $bor = max($locked, $db_items[$k]['soni'] ?? 0.0);
            $mah = $db->row("SELECT nomi, COALESCE(oshpaz_kerak,0) ok, COALESCE(qozon_rejim,0) qr FROM im_mahsulotlar WHERE id=" . (int)$it['mid']);
            if ((int)$mah['ok'] === 1 && (int)$mah['qr'] === 0 && $it['soni'] > $bor) {
                if (!$db->val("SELECT id FROM im_retseptlar WHERE mahsulot_id=" . (int)$it['mid'] . " AND tur='ishlab_chiqarish' AND status=1 LIMIT 1")) throw new Exception("«{$mah['nomi']}» retsepti yo'q");
            }
            if ($it['soni'] < $locked) throw new Exception('Oshpazga ketgan miqdordan kamaytirish mumkin emas');
            if ((int)$mah['ok'] === 1 && $it['soni'] > $locked) $has_new_kitchen = true;
        }
        $status = $action === 'hold' ? ($has_new_kitchen ? 'oshpazda' : 'stol_band') : ($has_new_kitchen ? 'oshpazda' : 'tasdiqlandi');
        $mijoz_s = esc(mb_substr($mijoz_ism, 0, 100));
        if ($old !== null) {
            if ($old['status'] === 'pishirilmoqda') {
                if ($action === 'create' && !$has_new_kitchen) throw new Exception('Taom hali pishirilmoqda');
                $status = $has_new_kitchen ? 'oshpazda' : 'pishirilmoqda';
            } elseif ($old['status'] === 'oshpazda' && !$has_new_kitchen) {
                $status = $action === 'create' ? 'tasdiqlandi' : 'stol_band';
            } elseif ($old['status'] === 'stol_band' && $action === 'create' && !$has_new_kitchen) {
                $status = 'tasdiqlandi';
            }
            $db->q("UPDATE im_sotuvchi_order SET mijoz_ism='$mijoz_s', stol_id=$stol_id_sql, olib_ketish=$olib_ketish, izoh='', status='$status', updated_at=NOW()
                    WHERE id=$order_id AND filial_id=$filial_id");
        } else {
            if ($stol_id) {
                $active_normal = (int)$db->val("SELECT COUNT(*) FROM im_sotuvchi_order WHERE filial_id=$filial_id AND stol_id=$stol_id AND olib_ketish=0 AND status NOT IN ('bekor','tugallandi')");
                $err = im_order_yaratish_xatosi($stol_id, $olib_ketish, $active_normal);
                if ($err) throw new Exception($err);
            }
            $token = 'seed' . bin2hex(random_bytes(6));
            $order_id = (int)$db->insert("INSERT INTO im_sotuvchi_order (sotuvchi_id, filial_id, mijoz_ism, stol_id, olib_ketish, izoh, status, client_token)
                VALUES ($sotuvchi, $filial_id, '$mijoz_s', $stol_id_sql, $olib_ketish, '', '$status', '$token')");
        }
        $passed = [];
        foreach ($items as $it) {
            $mid = (int)$it['mid']; $soni = round((float)$it['soni'], 3); $set_id = (int)($it['set_id'] ?? 0); $set_sql = $set_id ?: 'NULL';
            $item_ok = $olib_ketish ? $soni : 0;
            $passed[] = "$mid:$set_id";
            $prod_nomi = (string)$db->val("SELECT nomi FROM im_mahsulotlar WHERE id=$mid");
            $rezervlanadi = im_vitrinali($db, $mid); $yangi_rezerv = $rezervlanadi ? $soni : 0.0;
            $exist = $db->row("SELECT id, soni, narx, rezerv_soni, tayyorlandi_soni FROM im_sotuvchi_order_item WHERE order_id=$order_id AND mahsulot_id=$mid AND COALESCE(set_id,0)=$set_id FOR UPDATE");
            $ni = seed_narx($mid, $soni, $set_id);
            $eski_narx = $exist ? (float)$exist['narx'] : 0.0;
            $narx = $eski_narx > 0 ? $eski_narx : ($ni ? (float)$ni['asos'] : null);
            if ($ni && $ni['ulg'] !== null) $narx = (float)$ni['ulg'];
            if ($narx === null) throw new Exception("«$prod_nomi» narxi aniqlanmadi");
            $narx = max(0.0, (float)$narx);
            if ($exist) {
                $item_id = (int)$exist['id']; $eski_soni = (float)$exist['soni']; $eski_rezerv = (float)$exist['rezerv_soni'];
                if ($rezervlanadi && !im_rezerv($db, $filial_id, $mid, $yangi_rezerv - $eski_rezerv)) throw new Exception("«$prod_nomi» yetarli emas (rezerv)");
                $db->q("UPDATE im_sotuvchi_order_item SET soni=$soni, narx=$narx, olib_ketish_soni=$item_ok, rezerv_soni=$yangi_rezerv WHERE id=$item_id");
                if (abs($soni - $eski_soni) > 0.0001) seed_item_log($order_id, $item_id, $mid, $sotuvchi, $soni > $eski_soni ? 'oshirildi' : 'kamaytir', $eski_soni, $soni, $prod_nomi);
            } else {
                if ($rezervlanadi && !im_rezerv($db, $filial_id, $mid, $yangi_rezerv)) throw new Exception("«$prod_nomi» yetarli emas (rezerv)");
                $item_id = (int)$db->insert("INSERT INTO im_sotuvchi_order_item (order_id, mahsulot_id, set_id, soni, narx, tayyorlandi_soni, olib_ketish_soni, rezerv_soni)
                    VALUES ($order_id, $mid, $set_sql, $soni, $narx, 0, $item_ok, $yangi_rezerv)");
                seed_item_log($order_id, $item_id, $mid, $sotuvchi, 'qoshildi', 0, $soni, $prod_nomi);
            }
        }
        $qolgan = array_flip($passed);
        foreach ($db_items as $k => $dr) {
            if (isset($qolgan[$k]) || $dr['tayyor'] > 0.0001) continue;
            im_rezerv($db, $filial_id, $dr['mid'], -$dr['rezerv']);
            seed_item_log($order_id, $dr['id'], $dr['mid'], $sotuvchi, 'ochirildi', $dr['soni'], 0, (string)$db->val("SELECT nomi FROM im_mahsulotlar WHERE id={$dr['mid']}"));
            $db->q("DELETE FROM im_sotuvchi_order_item WHERE order_id=$order_id AND mahsulot_id={$dr['mid']} AND COALESCE(set_id,0)={$dr['sid']} AND tayyorlandi_soni=0");
        }
        $summa = (float)$db->val("SELECT SUM(soni*narx) FROM im_sotuvchi_order_item WHERE order_id=$order_id");
        $amal = $action === 'hold' ? ($has_new_kitchen ? 'oshpazga_yuborildi' : 'stol_band') : ($has_new_kitchen ? 'oshpazga_yuborildi' : 'kassaga_yuborildi');
        seed_xodim_log($sotuvchi, $amal, $order_id, $mijoz_ism, $summa);
        return ['order_id' => $order_id, 'status' => $status];
    }, "order-save $action #$order_id");
}

// ── Oshpaz: qabul (order-qabul) ──────────────────────────────
function f_oshpaz_qabul($id, $oshpaz) {
    global $db, $FILIAL;
    $filial_id = $FILIAL;
    return tx(function () use ($db, $filial_id, $id, $oshpaz) {
        $order = $db->row("SELECT id, status, mijoz_ism FROM im_sotuvchi_order WHERE id=$id AND filial_id=$filial_id FOR UPDATE");
        if (!$order || $order['status'] !== 'oshpazda') throw new Exception('Buyurtma oshpazda emas');
        $rows = $db->rows("SELECT i.id AS item_id, i.mahsulot_id, i.soni, i.tayyorlandi_soni, m.nomi, COALESCE(m.qozon_rejim,0) AS qozon_rejim
             FROM im_sotuvchi_order_item i JOIN im_mahsulotlar m ON m.id=i.mahsulot_id
             WHERE i.order_id=$id AND m.oshpaz_kerak=1 AND i.soni > i.tayyorlandi_soni ORDER BY i.id FOR UPDATE");
        if (!$rows) throw new Exception('Tayyorlanadigan mahsulot yo\'q');
        $xom = [];
        foreach ($rows as $it) {
            if ((int)$it['qozon_rejim'] === 1) continue;
            $rid = (int)$db->val("SELECT id FROM im_retseptlar WHERE mahsulot_id=" . (int)$it['mahsulot_id'] . " AND tur='ishlab_chiqarish' AND status=1 ORDER BY id DESC LIMIT 1");
            foreach ($db->rows("SELECT DISTINCT mahsulot_id FROM im_retsept_items WHERE retsept_id=$rid") as $ri) $xom[(int)$ri['mahsulot_id']] = true;
        }
        $xom = array_keys($xom); sort($xom, SORT_NUMERIC);
        foreach ($xom as $pid) im_fifo_lock($db, $filial_id, $pid);
        foreach ($rows as $it) {
            $mid = (int)$it['mahsulot_id']; $delta = (float)$it['soni'] - (float)$it['tayyorlandi_soni'];
            if ($delta <= 0) continue;
            if ((int)$it['qozon_rejim'] === 1) { im_qozon_buyurtmaga_tayyor($db, $filial_id, $mid, $delta, $it['nomi']); continue; }
            $rid = (int)$db->val("SELECT id FROM im_retseptlar WHERE mahsulot_id=$mid AND tur='ishlab_chiqarish' AND status=1 ORDER BY id DESC LIMIT 1");
            im_retsept_xomashyo_sarflash($db, $rid, $delta, $filial_id, $oshpaz, "Buyurtma #{$id} — {$it['nomi']} ({$delta} dona) uchun oshpaz qabuli", (int)$it['item_id']);
        }
        $db->q("UPDATE im_sotuvchi_order_item i JOIN im_mahsulotlar m ON m.id=i.mahsulot_id SET i.tayyorlandi_soni=i.soni WHERE i.order_id=$id AND m.oshpaz_kerak=1");
        $db->q("UPDATE im_sotuvchi_order SET status='pishirilmoqda', updated_at=NOW() WHERE id=$id AND filial_id=$filial_id");
        foreach ($rows as $it) seed_item_log($id, (int)$it['item_id'], (int)$it['mahsulot_id'], $oshpaz, 'oshpaz_qabul', (float)$it['tayyorlandi_soni'], (float)$it['soni'], $it['nomi']);
        $summa = (float)$db->val("SELECT SUM(soni*narx) FROM im_sotuvchi_order_item WHERE order_id=$id");
        seed_xodim_log($oshpaz, 'oshpaz_qabul', $id, (string)$order['mijoz_ism'], $summa, 'Qabul qilindi, pishirish boshlandi');
        return true;
    }, "oshpaz-qabul #$id");
}

// ── Oshpaz: tayyor (order-tayyor) ────────────────────────────
function f_oshpaz_tayyor($id, $oshpaz) {
    global $db, $FILIAL;
    $filial_id = $FILIAL;
    return tx(function () use ($db, $filial_id, $id, $oshpaz) {
        $order = $db->row("SELECT id, status, mijoz_ism FROM im_sotuvchi_order WHERE id=$id AND filial_id=$filial_id FOR UPDATE");
        if (!$order || $order['status'] !== 'pishirilmoqda') throw new Exception('pishirilmoqda emas');
        foreach ($db->rows("SELECT i.mahsulot_id, i.tayyorlandi_soni, m.nomi FROM im_sotuvchi_order_item i JOIN im_mahsulotlar m ON m.id=i.mahsulot_id
                 WHERE i.order_id=$id AND m.oshpaz_kerak=1 AND m.qozon_rejim=1 AND i.tayyorlandi_soni>0 ORDER BY i.id FOR UPDATE") as $it) {
            im_qozon_buyurtmaga_tayyor($db, $filial_id, (int)$it['mahsulot_id'], (float)$it['tayyorlandi_soni'], $it['nomi']);
        }
        $db->q("UPDATE im_sotuvchi_order SET status='stol_band', updated_at=NOW() WHERE id=$id AND filial_id=$filial_id");
        $summa = (float)$db->val("SELECT SUM(soni*narx) FROM im_sotuvchi_order_item WHERE order_id=$id");
        seed_xodim_log($oshpaz, 'oshpaz_tayyor', $id, (string)$order['mijoz_ism'], $summa, 'Taom tayyor — ofitsant chaqirildi');
        return true;
    }, "oshpaz-tayyor #$id");
}

// ── Kassa: chek yopish (sotuv-save.php) ──────────────────────
//  $savat: [['mid','soni','narx','set_id'=>0,'chegirma_foiz'=>0]]
//  $payfn: fn(float $tolov_summa) => ['naqd'=>..,'karta'=>..,'bank'=>..,'usd'=>..,'usd_kurs'=>..,'nasiya'=>..]
//  $opts : ['mijoz_id'=>0, 'chegirma_foiz'=>0 (kassir), 'xizmat'=>true/false]
function f_checkout($order_id, $smena_id, $kassir, array $savat, callable $payfn, array $opts = []) {
    global $db, $FILIAL;
    $filial_id = $FILIAL;
    $order_id = (int)$order_id;
    $mijoz_id = (int)($opts['mijoz_id'] ?? 0);
    $chegirma_qol = min(100, max(0, (float)($opts['chegirma_foiz'] ?? 0)));
    $xizmat_flag = !empty($opts['xizmat']);

    // ── Manba ──
    $manba_stol_id = null; $manba_nomi = "To'g'ridan-to'g'ri"; $manba_sotuvchi = null; $manba_stol_savdo = false; $ord = null;
    if ($order_id > 0) {
        $ord = $db->row("SELECT id, stol_id, mijoz_ism, olib_ketish, sotuvchi_id FROM im_sotuvchi_order WHERE id=$order_id AND filial_id=$filial_id");
        if ($ord) {
            $manba_stol_id = $ord['stol_id'] !== null ? (int)$ord['stol_id'] : null;
            $manba_sotuvchi = (int)$ord['sotuvchi_id'] ?: null;
            if ($manba_stol_id) {
                $stol_nomi = (string)$db->val("SELECT nomi FROM im_stollar WHERE id=$manba_stol_id") ?: (string)$ord['mijoz_ism'];
                $manba_nomi = $ord['olib_ketish'] ? ($stol_nomi . ' — Olib ketish') : $stol_nomi;
                $manba_stol_savdo = !$ord['olib_ketish'];
            } else {
                $manba_nomi = (string)$ord['mijoz_ism'] ?: ($ord['olib_ketish'] ? 'Olib ketish' : 'Dastavka');
            }
        }
    }
    $mijoz_chegirma = 0;
    if ($mijoz_id) {
        $mj = $db->row("SELECT m.*, t.chegirma_foiz FROM im_mijozlar m LEFT JOIN im_mijoz_toifalari t ON t.id=m.toifa_id WHERE m.id=$mijoz_id AND m.status=1");
        if ($mj) $mijoz_chegirma = (float)$mj['chegirma_foiz'];
    }
    // ── Savat hisob ──
    $jami_summa = 0; $jami_chegirma = 0; $items_prepared = [];
    $is_nasiya_sale = !empty($opts['nasiya']);   // nasiya rejimi oldindan ma'lum bo'lishi kerak
    if ($is_nasiya_sale) { $chegirma_qol = 0; $mijoz_chegirma = 0; }
    foreach ($savat as $item) {
        $mah_id = (int)$item['mid']; $soni = round((float)$item['soni'], 3); $base_narx = (float)$item['narx'];
        $item_ch_foiz = (float)($item['chegirma_foiz'] ?? 0);
        $item_set_id = (int)($item['set_id'] ?? 0) ?: null; $set_norm = $item_set_id ?: 0;
        if ($base_narx <= 0) throw new Exception("Mahsulot $mah_id narxi 0");
        if ($is_nasiya_sale) $item_ch_foiz = 0;
        $eff_ch = $is_nasiya_sale ? 0 : min(max($item_ch_foiz, $mijoz_chegirma, $chegirma_qol), 100);
        $chegirma_narxi = round($base_narx * (1 - $eff_ch / 100), 2);
        $line_total = $chegirma_narxi * $soni; $line_chegirma = ($base_narx - $chegirma_narxi) * $soni;
        $jami_summa += $base_narx * $soni; $jami_chegirma += $line_chegirma;
        $tur = $db->row("SELECT COALESCE(oshpaz_kerak,0) ok, COALESCE(retsept_avto,0) ra, COALESCE(qozon_rejim,0) qr FROM im_mahsulotlar WHERE id=$mah_id");
        $needs_kitchen = (int)$tur['ok'] === 1; $is_qozon = (int)$tur['qr'] === 1;
        $is_alacarte = $needs_kitchen && !$is_qozon; $is_ra = (int)$tur['ra'] === 1; $zaxirasiz = $is_alacarte || $is_ra;
        $oi = null; $item_olib_ketish = 0.0;
        if ($order_id > 0) {
            $oi = $db->row("SELECT id, COALESCE(olib_ketish_soni,0) AS ok, COALESCE(tayyorlandi_soni,0) AS ty FROM im_sotuvchi_order_item
                            WHERE order_id=$order_id AND mahsulot_id=$mah_id AND COALESCE(set_id,0)=$set_norm LIMIT 1");
            $item_olib_ketish = max(0, min((float)($oi['ok'] ?? 0), $soni));
            if ($needs_kitchen && (float)($oi['ty'] ?? 0) + 0.0001 < $soni) throw new Exception("Mahsulot $mah_id hali tayyorlanmagan");
        } elseif ($needs_kitchen) throw new Exception("Oshxona taomi to'g'ridan-to'g'ri sotilmaydi");
        $rezervlangan = 0.0;
        if ($order_id > 0 && !$zaxirasiz) $rezervlangan = (float)$db->val("SELECT COALESCE(rezerv_soni,0) FROM im_sotuvchi_order_item WHERE order_id=$order_id AND mahsulot_id=$mah_id AND COALESCE(set_id,0)=$set_norm LIMIT 1");
        $qoldiqdan_kerak = max(0, $soni - $rezervlangan);
        if (!$zaxirasiz && $qoldiqdan_kerak > 0) {
            $mavjud = (float)$db->val("SELECT COALESCE(soni,0) FROM im_filial_qoldiq WHERE filial_id=$filial_id AND mahsulot_id=$mah_id");
            if ($mavjud + 0.0001 < $qoldiqdan_kerak) {
                $auto = im_auto_maydalash_holati($db, $filial_id, $mah_id);
                if (!($auto && (float)$auto['jami_imkon'] + 0.0001 >= $qoldiqdan_kerak)) throw new Exception("Mahsulot $mah_id qoldiq yetarli emas (mavjud $mavjud, kerak $qoldiqdan_kerak)");
            }
        }
        $items_prepared[] = ['mah_id' => $mah_id, 'qozon' => $is_qozon, 'soni' => $soni, 'sotish_narxi' => $base_narx, 'chegirma_foiz' => $eff_ch,
            'chegirma_narxi' => $chegirma_narxi, 'line_total' => $line_total, 'set_id' => $item_set_id, 'alacarte' => $is_alacarte,
            'retsept_avto' => $is_ra, 'zaxirasiz' => $zaxirasiz, 'olib_ketish' => $item_olib_ketish];
    }
    $tolov_summa = $jami_summa - $jami_chegirma;
    $xizmat_sozlama = max(0, min(100, (float)(im_sozlama('xizmat_foiz') ?: 0)));
    $xizmat_foiz = ($xizmat_flag && $manba_stol_savdo) ? $xizmat_sozlama : 0;
    $qator_jami = 0.0; $qator_stol = 0.0;
    foreach ($items_prepared as $ip) {
        $qator_jami += $ip['line_total'];
        if ($ip['soni'] > 0) { $stol_soni = max(0, $ip['soni'] - (float)$ip['olib_ketish']); $qator_stol += $ip['line_total'] * ($stol_soni / $ip['soni']); }
    }
    $hisob = im_sotuv_yakun_hisobla($jami_summa, $jami_chegirma, $tolov_summa, null, $xizmat_foiz, $qator_jami, $qator_stol, 0, $is_nasiya_sale);
    $jami_chegirma = $hisob['jami_chegirma']; $tolov_summa = $hisob['tolov_summa']; $eff_ch_foiz = $hisob['eff_ch_foiz'];
    $xizmat_summa = $hisob['xizmat_summa'];
    // ── To'lov ──
    $pay = $payfn($tolov_summa);
    $naqd_summa = (float)($pay['naqd'] ?? 0); $karta_summa = (float)($pay['karta'] ?? 0); $bank_summa = (float)($pay['bank'] ?? 0);
    $usd_summa = (float)($pay['usd'] ?? 0); $usd_kurs = (float)($pay['usd_kurs'] ?? 0); $nasiya_summa = (float)($pay['nasiya'] ?? 0);
    $usd_som = round($usd_summa * $usd_kurs); $usd_qaytim_som = 0;
    $total_tolov = $naqd_summa + $karta_summa + $bank_summa + $usd_som + $nasiya_summa;
    $tolerans = $usd_summa > 0 ? 200 : 1;
    if ($total_tolov - $tolov_summa < -$tolerans) throw new Exception("To'lov yetarli emas ($total_tolov < $tolov_summa)");
    $chek_nomer = 'NHC-' . sd('Ymd') . '-' . str_pad((string)((int)substr((string)$db->val("SELECT chek_nomer FROM im_sotuvlar WHERE chek_nomer LIKE 'NHC-" . sd('Ymd') . "-%' ORDER BY id DESC LIMIT 1"), -4) + 1), 4, '0', STR_PAD_LEFT);

    return tx(function () use ($db, $filial_id, $kassir, $order_id, $ord, $smena_id, $mijoz_id, $manba_stol_id, $manba_nomi, $manba_sotuvchi, $items_prepared,
        $chek_nomer, $naqd_summa, $karta_summa, $bank_summa, $usd_summa, $usd_kurs, $usd_som, $nasiya_summa, $jami_summa, $eff_ch_foiz, $jami_chegirma,
        $xizmat_foiz, $xizmat_summa, $tolov_summa, $usd_qaytim_som) {
        if ($manba_stol_id) { if (!$db->row("SELECT id FROM im_stollar WHERE id=$manba_stol_id AND filial_id=$filial_id FOR UPDATE")) throw new Exception('Stol topilmadi'); }
        if ($order_id > 0) {
            $lo = $db->row("SELECT * FROM im_sotuvchi_order WHERE id=$order_id AND filial_id=$filial_id FOR UPDATE");
            if (!$lo || !in_array($lo['status'], ['tasdiqlandi', 'qabul'], true)) throw new Exception('Buyurtma kassaga tayyor emas (' . ($lo['status'] ?? '?') . ')');
            if ($db->row("SELECT id FROM im_sotuvlar WHERE order_id=$order_id LIMIT 1 FOR UPDATE")) throw new Exception('Allaqachon sotilgan');
        }
        im_qulfla_mahsulotlar($db, $filial_id, array_column($items_prepared, 'mah_id'));
        $mj_s = $mijoz_id ?: 'NULL'; $ord_s = $order_id > 0 ? $order_id : 'NULL'; $stol_s = $manba_stol_id ?: 'NULL'; $sotch_s = $manba_sotuvchi ?: 'NULL';
        $naqd_berildi = 0;
        $sotuv_id = (int)$db->insert("INSERT INTO im_sotuvlar (chek_nomer, smena_id, mijoz_id, kassir_id, filial_id, sotuvchi_id, order_id, stol_id, manba,
             naqd_summa, karta_summa, bank_summa, usd_summa, usd_kurs, usd_som_ekviv, nasiya_summa, jami_summa, chegirma_foiz, chegirma_summa,
             xizmat_foiz, xizmat_summa, tolov_summa, naqd_berildi, usd_qaytim_som)
            VALUES ('" . esc($chek_nomer) . "', $smena_id, $mj_s, $kassir, $filial_id, $sotch_s, $ord_s, $stol_s, '" . esc(mb_substr($manba_nomi, 0, 60)) . "',
             $naqd_summa, $karta_summa, $bank_summa, $usd_summa, $usd_kurs, $usd_som, $nasiya_summa, $jami_summa, $eff_ch_foiz, $jami_chegirma,
             $xizmat_foiz, $xizmat_summa, $tolov_summa, $naqd_berildi, $usd_qaytim_som)");
        $used = [];
        foreach ($items_prepared as $item) {
            $mah_id = (int)$item['mah_id']; $kerak = (float)$item['soni']; $oi = null; $oi_id = 0;
            if ($order_id > 0) {
                $set_norm = (int)$item['set_id'];
                $matches = $db->rows("SELECT * FROM im_sotuvchi_order_item WHERE order_id=$order_id AND mahsulot_id=$mah_id AND COALESCE(set_id,0)=$set_norm FOR UPDATE");
                if (count($matches) !== 1) throw new Exception('Buyurtma qatori aniq emas');
                $oi = $matches[0]; $oi_id = (int)$oi['id'];
                if (isset($used[$oi_id])) throw new Exception('Takror qator'); $used[$oi_id] = true;
            }
            $set_val = $item['set_id'] ? (int)$item['set_id'] : 'NULL';
            $return_mode = (!empty($item['zaxirasiz']) || !empty($item['qozon'])) ? 'waste' : 'stock';
            $sale_item_id = (int)$db->insert("INSERT INTO im_sotuv_items (sotuv_id, set_id, mahsulot_id, partiya_item_id, soni, sotish_narxi, chegirma_foiz, chegirma_narxi, tannarx, fifo_return_mode, olib_ketish_soni)
                VALUES ($sotuv_id, $set_val, $mah_id, NULL, $kerak, {$item['sotish_narxi']}, {$item['chegirma_foiz']}, {$item['chegirma_narxi']}, 0, '$return_mode', " . (float)$item['olib_ketish'] . ")");
            $cost_total = 0.0;
            if (!empty($item['alacarte'])) {
                $prepared = (float)($oi['tayyorlandi_soni'] ?? 0);
                if (!$oi || abs($prepared - $kerak) > 0.000001 || abs((float)$oi['soni'] - $kerak) > 0.000001) throw new Exception('Oshxona qatori to‘liq sotilishi kerak');
                $production = $db->rows("SELECT id, tannarx FROM im_ishlab_chiqarish WHERE order_item_id=$oi_id AND filial_id=$filial_id AND mahsulot_id=$mah_id AND holat='bajarildi' ORDER BY id FOR UPDATE");
                if (!$production) throw new Exception('Oshxona qatorining FIFO tannarxi topilmadi');
                foreach ($production as $rec) $cost_total += (float)$rec['tannarx'];
            } elseif (!empty($item['retsept_avto'])) {
                $recipe = $db->row("SELECT id, chiqish_soni FROM im_retseptlar WHERE mahsulot_id=$mah_id AND tur='ishlab_chiqarish' AND status=1 ORDER BY id DESC LIMIT 1 FOR UPDATE");
                $inputs = $db->rows("SELECT ri.mahsulot_id, SUM(ri.soni) AS soni FROM im_retsept_items ri WHERE ri.retsept_id={$recipe['id']} GROUP BY ri.mahsulot_id ORDER BY ri.mahsulot_id");
                foreach ($inputs as $ing) {
                    $qty = round((float)$ing['soni'] * $kerak / (float)$recipe['chiqish_soni'], 3);
                    if ($qty <= 0) throw new Exception('Xomashyo miqdori juda kichik');
                    $take = im_fifo_take($db, $filial_id, (int)$ing['mahsulot_id'], $qty, 'sotuv', $sale_item_id);
                    $cost_total += (float)$take['total'];
                }
            } else {
                if ($oi && (float)$oi['rezerv_soni'] > 0) {
                    if (!im_rezerv($db, $filial_id, $mah_id, -(float)$oi['rezerv_soni'])) throw new Exception('Rezerv bo‘shatilmadi');
                    $db->q("UPDATE im_sotuvchi_order_item SET rezerv_soni=0 WHERE id=$oi_id");
                }
                if (!empty($item['qozon'])) im_qozon_sotuvga_yetkaz($db, $filial_id, $mah_id, $kerak);
                else im_auto_maydalash_yetkaz($db, $filial_id, $mah_id, $kerak, (int)$kassir);
                $take = im_fifo_take($db, $filial_id, $mah_id, $kerak, 'sotuv', $sale_item_id);
                $cost_total = (float)$take['total'];
                if (!empty($item['qozon'])) {
                    $qids = [];
                    foreach ($take['allocations'] as $a) if (($a['source'] ?? '') === 'qozon') $qids[(int)$a['source_id']] = true;
                    if (count($qids) === 1) $db->q("UPDATE im_sotuv_items SET qozon_id=" . (int)array_key_first($qids) . " WHERE id=$sale_item_id");
                }
            }
            $unit_cost = number_format($cost_total / $kerak, 6, '.', '');
            $db->q("UPDATE im_sotuv_items SET tannarx=$unit_cost WHERE id=$sale_item_id");
        }
        if ($nasiya_summa > 0 && $mijoz_id) {
            $qs = date('Y-m-d', strtotime(sd() . ' +' . rnd(14, 30) . ' days'));
            $db->insert("INSERT INTO im_nasiya (sotuv_id, mijoz_id, qarz_summa, tolangan, qoldiq, qaytarish_sana, holat, kassir_id)
                         VALUES ($sotuv_id, $mijoz_id, $nasiya_summa, 0, $nasiya_summa, '$qs', 'aktiv', $kassir)");
            $db->q("UPDATE im_mijozlar SET nasiya_qoldiq=nasiya_qoldiq+$nasiya_summa WHERE id=$mijoz_id");
        }
        if ($mijoz_id) $db->q("UPDATE im_mijozlar SET jami_xarid=jami_xarid+$tolov_summa WHERE id=$mijoz_id");
        $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans) VALUES ($filial_id, $naqd_summa, $karta_summa, $bank_summa, $usd_summa)
                ON DUPLICATE KEY UPDATE naqd_balans=naqd_balans+$naqd_summa, karta_balans=karta_balans+$karta_summa, bank_balans=bank_balans+$bank_summa, usd_balans=usd_balans+$usd_summa");
        if ($naqd_summa > 0)  $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,xodim_id) VALUES ('kirim','sotuv_naqd',$naqd_summa,$sotuv_id,'sotuv',$filial_id,$kassir)");
        if ($karta_summa > 0) $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,xodim_id) VALUES ('kirim','sotuv_karta',$karta_summa,$sotuv_id,'sotuv',$filial_id,$kassir)");
        if ($bank_summa > 0)  $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,xodim_id) VALUES ('kirim','sotuv_bank',$bank_summa,$sotuv_id,'sotuv',$filial_id,$kassir)");
        if ($usd_summa > 0)   $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,summa_usd,usd_kurs,manba_id,manba_tur,filial_id,xodim_id) VALUES ('kirim','sotuv_usd'," . ($usd_summa * $usd_kurs) . ",$usd_summa,$usd_kurs,$sotuv_id,'sotuv',$filial_id,$kassir)");
        if ($order_id > 0) $db->q("UPDATE im_sotuvchi_order SET status='tugallandi', updated_at=NOW() WHERE id=$order_id AND filial_id=$filial_id AND status NOT IN ('bekor')");
        return ['sotuv_id' => $sotuv_id, 'tolov_summa' => $tolov_summa, 'chek' => $chek_nomer];
    }, "checkout order#$order_id");
}

// ── Vozvrat (vozvrat-save.php) ───────────────────────────────
function f_vozvrat($sotuv_item_id, $soni, $summa, $tt, $sabab, $kassir) {
    global $db, $FILIAL;
    $filial_id = $FILIAL;
    return tx(function () use ($db, $filial_id, $sotuv_item_id, $soni, $summa, $tt, $sabab, $kassir) {
        $cand = $db->row("SELECT sotuv_id, mahsulot_id FROM im_sotuv_items WHERE id=$sotuv_item_id");
        $sotuv_id = (int)$cand['sotuv_id'];
        $sale = $db->row("SELECT * FROM im_sotuvlar WHERE id=$sotuv_id AND filial_id=$filial_id FOR UPDATE");
        $line = $db->row("SELECT * FROM im_sotuv_items WHERE id=$sotuv_item_id AND sotuv_id=$sotuv_id FOR UPDATE");
        $mah_id = (int)$line['mahsulot_id'];
        $lock_ids = [$mah_id];
        foreach ($db->rows("SELECT DISTINCT l.mahsulot_id FROM im_fifo_movements m JOIN im_fifo_layers l ON l.id=m.layer_id WHERE m.source='sotuv' AND m.source_id=$sotuv_item_id AND m.kind='take'") as $h) $lock_ids[] = (int)$h['mahsulot_id'];
        im_qulfla_mahsulotlar($db, $filial_id, $lock_ids);
        $mode = $line['fifo_return_mode'];
        $prev = $db->rows("SELECT soni, qaytarish_summa FROM im_vozvratlar WHERE sotuv_item_id=$sotuv_item_id FOR UPDATE");
        $returned = 0.0; $refunded_line = 0.0; foreach ($prev as $r) { $returned += (float)$r['soni']; $refunded_line += (float)$r['qaytarish_summa']; }
        if ($soni > round((float)$line['soni'] - $returned, 3) + 0.000001) throw new Exception('Qaytarish miqdori oshdi');
        $max_refund = min((float)$line['chegirma_narxi'] * $soni, (float)$line['chegirma_narxi'] * (float)$line['soni'] - $refunded_line);
        $rr = 0.0; foreach ($db->rows("SELECT qaytarish_summa FROM im_vozvratlar WHERE sotuv_id=$sotuv_id FOR UPDATE") as $r) $rr += (float)$r['qaytarish_summa'];
        $max_refund = min($max_refund, (float)$sale['tolov_summa'] - $rr);
        if ($summa > round($max_refund, 2) + 0.000001) $summa = round($max_refund, 2);
        if ($summa <= 0) throw new Exception('Qaytarish summasi 0');
        $is_waste = $mode === 'waste';
        $sale_cost_sql = im_fifo_sale_unit_cost_sql('cost_si');
        $eff_unit = (float)$db->val("SELECT $sale_cost_sql FROM im_sotuv_items cost_si WHERE cost_si.id=$sotuv_item_id");
        $cost_total = $is_waste ? $eff_unit * $soni : im_fifo_reverse($db, 'sotuv', $sotuv_item_id, $soni);
        $cost_sql = number_format((float)$cost_total, 6, '.', '');
        $sabab_s = esc(mb_substr(($is_waste ? '[waste] ' : '[stock] ') . $sabab, 0, 200));
        $id = (int)$db->insert("INSERT INTO im_vozvratlar (sotuv_id, sotuv_item_id, mahsulot_id, soni, tannarx, sabab, qaytarildi_tur, qaytarish_summa, kassir_id)
            VALUES ($sotuv_id, $sotuv_item_id, $mah_id, $soni, $cost_sql, '$sabab_s', '$tt', $summa, $kassir)");
        $naqd_q = $summa;
        $nasiya = $db->row("SELECT * FROM im_nasiya WHERE sotuv_id=$sotuv_id AND holat IN ('aktiv','muddati_otdi') LIMIT 1 FOR UPDATE");
        if ($nasiya) {
            $uz = min($summa, (float)$nasiya['qoldiq']);
            if ($uz > 0) {
                $yq = max(0, (float)$nasiya['qoldiq'] - $uz); $yh = $yq <= 0.01 ? 'yopildi' : $nasiya['holat'];
                $db->q("UPDATE im_nasiya SET qoldiq=$yq, holat='$yh' WHERE id={$nasiya['id']}");
                $db->q("UPDATE im_mijozlar SET nasiya_qoldiq=GREATEST(0, nasiya_qoldiq-$uz) WHERE id={$nasiya['mijoz_id']}");
                $db->q("INSERT INTO im_nasiya_tolovlar (nasiya_id, summa, tolov_turi, izoh, xodim_id) VALUES ({$nasiya['id']}, $uz, 'naqd', 'Mahsulot qaytarilishi (Vozvrat #{$id}) hisobidan', $kassir)");
            }
            $naqd_q = max(0, $summa - $uz);
        }
        $js = (float)$db->val("SELECT COALESCE(SUM(soni),0) FROM im_sotuv_items WHERE sotuv_id=$sotuv_id");
        $jq = (float)$db->val("SELECT COALESCE(SUM(soni),0) FROM im_vozvratlar WHERE sotuv_id=$sotuv_id");
        if ($jq >= $js) $db->q("UPDATE im_sotuvlar SET holat='qaytarilgan' WHERE id=$sotuv_id");
        if ($naqd_q > 0) {
            $col = $tt === 'karta' ? 'karta_balans' : ($tt === 'bank' ? 'bank_balans' : 'naqd_balans');
            $db->q("UPDATE im_kassa SET $col=$col-$naqd_q WHERE filial_id=$filial_id");
            $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, manba_id, manba_tur, filial_id, xodim_id) VALUES ('chiqim','vozvrat_chiqim',$naqd_q,$id,'vozvrat',$filial_id,$kassir)");
        }
        return $id;
    }, "vozvrat item#$sotuv_item_id");
}

// ── Smena ochish / yopish ────────────────────────────────────
function f_smena_open($kassir, $naqd = 0) {
    global $db, $FILIAL;
    $filial_id = $FILIAL;
    if ($db->val("SELECT id FROM im_smena WHERE kassir_id=$kassir AND filial_id=$filial_id AND holat='ochiq'")) throw new Exception("Ochiq smena bor ($kassir)");
    return (int)$db->insert("INSERT INTO im_smena (sana, kassir_id, filial_id, ochish_naqd, holat, ochildi) VALUES ('" . sd() . "', $kassir, $filial_id, $naqd, 'ochiq', NOW())");
}
function f_smena_close($smena_id, $kassir) {
    global $db, $FILIAL, $link;
    $sale_cost = im_fifo_sale_unit_cost_sql('si');
    $smena = $db->row("SELECT * FROM im_smena WHERE id=$smena_id AND kassir_id=$kassir AND holat='ochiq'");
    if (!$smena) { warn("smena $smena_id topilmadi"); return null; }
    $start = esc($smena['ochildi']); $end = sdt(); $filial_id = (int)$smena['filial_id'];
    $fst = "kassir_id=$kassir AND filial_id=$filial_id AND sana BETWEEN '$start' AND '$end'";
    $fs  = "s.kassir_id=$kassir AND s.filial_id=$filial_id AND s.holat IN ('aktiv','qaytarilgan') AND s.sana BETWEEN '$start' AND '$end'";
    $stats = $db->row("SELECT COUNT(*) n, COALESCE(SUM(tolov_summa),0) jami, COALESCE(SUM(naqd_summa),0) naqd FROM im_sotuvlar WHERE $fst");
    $harajat = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_harajatlar WHERE xodim_id=$kassir AND filial_id=$filial_id AND sana BETWEEN DATE('$start') AND DATE('$end')");
    $harajat_naqd = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_harajatlar WHERE xodim_id=$kassir AND filial_id=$filial_id AND tolov_turi='naqd' AND sana BETWEEN DATE('$start') AND DATE('$end')");
    $vr = im_fifo_report_returns_dt($db, $start, $end, $filial_id, false, $kassir);
    $tannarx = (float)$db->val("SELECT COALESCE(SUM(($sale_cost) * si.soni), 0) FROM im_sotuv_items si JOIN im_sotuvlar s ON s.id=si.sotuv_id WHERE $fs") - (float)$vr['cost'];
    $usd_qaytim = (float)$db->val("SELECT COALESCE(SUM(usd_qaytim_som),0) FROM im_sotuvlar WHERE $fst");
    $nasiya_naqd = (float)$db->val("SELECT COALESCE(SUM(nt.summa),0) FROM im_nasiya_tolov nt WHERE nt.tolov_turi='naqd' AND nt.kassir_id=$kassir AND nt.sana BETWEEN '$start' AND '$end'");
    $qozon_isrofi = (float)$db->val("SELECT COALESCE(SUM(qoldi_porsiya*yakuniy_tannarx),0) FROM im_osh_qozon WHERE filial_id=$filial_id AND holat='yopildi' AND qoldi_isrofmi=1 AND yopildi_vaqt BETWEEN '$start' AND '$end'");
    $sotuv_netto = (float)$stats['jami'] - (float)$vr['revenue'];
    $naqd_jami = (float)$stats['naqd'] + (float)$smena['ochish_naqd'] + $nasiya_naqd - $usd_qaytim - $harajat_naqd;
    $oshxona_isrofi = im_fifo_report_kitchen_waste($db, $start, $end, $filial_id);
    $isrof = $qozon_isrofi + $oshxona_isrofi;
    $sof = $sotuv_netto - $tannarx - $harajat - $isrof;
    $db->q("UPDATE im_smena SET holat='yopiq', yopish_naqd=$naqd_jami, yopildi=NOW(), tannarx=" . number_format($tannarx, 2, '.', '') . ", isrof=" . number_format($isrof, 2, '.', '') . ", sof_foyda=" . number_format($sof, 2, '.', '') . " WHERE id=$smena_id");
    return ['n' => (int)$stats['n'], 'jami' => (float)$stats['jami'], 'sof' => $sof];
}

// ── Kassa harakatlari ────────────────────────────────────────
function kassa_col($tt) { return $tt === 'karta' ? 'karta_balans' : ($tt === 'bank' ? 'bank_balans' : ($tt === 'usd' ? 'usd_balans' : 'naqd_balans')); }
function kassa_bal($filial, $tt) { global $db; return (float)$db->val("SELECT " . kassa_col($tt) . " FROM im_kassa WHERE filial_id=$filial LIMIT 1"); }

// dukon/ajax/harajat-save.php (filial kassasidan) va admin/ajax/harajat-save.php (filial 0 = markaz)
function f_harajat($filial, $nomi, $tur, $summa, $tt, $xodim, $izoh = '') {
    global $db;
    if (kassa_bal($filial, $tt) < $summa) { warn("harajat '$nomi': kassada yetarli emas"); return null; }
    return tx(function () use ($db, $filial, $nomi, $tur, $summa, $tt, $xodim) {
        $id = (int)$db->insert("INSERT INTO im_harajatlar (nomi, tur, summa, tolov_turi, sana, xodim_id, filial_id) VALUES ('" . esc($nomi) . "','$tur',$summa,'$tt','" . sd() . "',$xodim,$filial)");
        $col = kassa_col($tt);
        $db->q("UPDATE im_kassa SET $col=$col-$summa WHERE filial_id=$filial");
        $kat = $filial === 0 ? 'admin_harajat' : 'harajat';
        $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,xodim_id,sana) VALUES ('chiqim','$kat',$summa,$id,'harajat',$filial,$xodim,'" . sdt() . "')");
        return $id;
    }, "harajat $nomi");
}
// admin/ajax/maosh-save.php — markaziy kassadan
function f_maosh($worker, $summa, $oy, $tt, $admin) {
    global $db;
    if (kassa_bal(0, $tt) < $summa) { warn("maosh {$worker['ism']}: markaziy kassada yetarli emas"); return null; }
    return tx(function () use ($db, $worker, $summa, $oy, $tt, $admin) {
        $col = kassa_col($tt); $izoh = esc("{$worker['ism']} - $oy maoshi");
        $db->q("UPDATE im_kassa SET $col=$col-$summa WHERE filial_id=0");
        $db->q("INSERT INTO im_maosh_tarixi (worker_id,summa,tolov_turi,oy,filial_id,izoh,beruvchi_id,sana) VALUES ({$worker['id']},$summa,'$tt','$oy',1,'$izoh',$admin,'" . sd() . "')");
        $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,manba_id,manba_tur,filial_id,izoh,xodim_id,sana) VALUES ('chiqim','maosh',$summa,{$worker['id']},'worker',1,'$izoh',$admin,'" . sd() . "')");
        return true;
    }, "maosh {$worker['ism']}");
}
// dukon/ajax/inkasasiya-save.php
function f_inkasasiya($summa, $tt, $kassir, $izoh = 'Kunlik tushum') {
    global $db, $FILIAL;
    $filial_id = $FILIAL;
    if (kassa_bal($filial_id, $tt) < $summa) return null;
    return tx(function () use ($db, $filial_id, $summa, $tt, $kassir, $izoh) {
        $id = (int)$db->insert("INSERT INTO im_inkasasiya (filial_id, xodim_id, summa, tolov_turi, izoh) VALUES ($filial_id, $kassir, $summa, '$tt', '" . esc($izoh) . "')");
        $col = kassa_col($tt);
        $db->q("UPDATE im_kassa SET $col=$col-$summa WHERE filial_id=$filial_id");
        $usd_k = im_usd_kurs(); $s_som = $tt === 'usd' ? $summa * $usd_k : $summa; $s_usd = $tt === 'usd' ? $summa : 0;
        $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, summa_usd, manba_id, manba_tur, izoh, xodim_id, filial_id)
                VALUES ('chiqim','inkasasiya_chiqim',$s_som,$s_usd,$id,'inkasasiya','Adminga pul topshirildi: " . esc($izoh) . "',$kassir,$filial_id)");
        return $id;
    }, "inkasasiya $tt $summa");
}
// admin/ajax/inkasasiya-action.php (accept)
function f_inkasasiya_accept($id, $admin) {
    global $db;
    return tx(function () use ($db, $id, $admin) {
        $ink = $db->row("SELECT * FROM im_inkasasiya WHERE id=$id");
        if (!$ink || $ink['holat'] !== 'kutilmoqda') throw new Exception('inkasasiya holati');
        $db->q("UPDATE im_inkasasiya SET holat='qabul_qilindi', admin_id=$admin, qabul_sana=NOW() WHERE id=$id");
        $summa = (float)$ink['summa']; $tt = $ink['tolov_turi']; $col = kassa_col($tt);
        $db->q("UPDATE im_kassa SET $col=$col+$summa WHERE filial_id=0");
        $usd_k = im_usd_kurs(); $s_som = $tt === 'usd' ? $summa * $usd_k : $summa; $s_usd = $tt === 'usd' ? $summa : 0;
        $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, summa_usd, manba_id, manba_tur, izoh, xodim_id, filial_id)
                VALUES ('kirim','inkasasiya_kirim',$s_som,$s_usd,$id,'inkasasiya','Filial #{$ink['filial_id']} dan inkasasiya qabul qilindi',$admin,0)");
        return true;
    }, "inkasasiya-accept $id");
}
// admin/ajax/qarz-tolov.php — markaziy kassadan postavshikka
function f_qarz_tolov($qarz_id, $summa, $tt, $admin, $izoh = '') {
    global $db;
    return tx(function () use ($db, $qarz_id, $summa, $tt, $admin, $izoh) {
        $qarz = $db->row("SELECT * FROM im_postavshik_qarz WHERE id=$qarz_id AND status='ochiq' FOR UPDATE");
        if (!$qarz) throw new Exception('qarz yopiq');
        $summa = min($summa, (float)$qarz['qoldiq']);
        if ($summa <= 0) throw new Exception('summa 0');
        $db->q("INSERT INTO im_qarz_tolovlar (qarz_id, summa, tolov_turi, usd_summa, usd_kurs, izoh, admin_id, sana) VALUES ($qarz_id, $summa, '$tt', 0, 0, '" . esc($izoh) . "', $admin, NOW())");
        $yt = (float)$qarz['tolandi'] + $summa; $yq = (float)$qarz['qoldiq'] - $summa; $ys = $yq <= 0.01 ? 'yopildi' : 'ochiq';
        $db->q("UPDATE im_postavshik_qarz SET tolandi=$yt, qoldiq=$yq, status='$ys' WHERE id=$qarz_id");
        if ($qarz['partiya_id']) $db->q("UPDATE im_partiyalar SET tolandi=tolandi+$summa, qarz_qoldi=qarz_qoldi-$summa WHERE id={$qarz['partiya_id']}");
        $col = kassa_col($tt);
        $db->q("UPDATE im_kassa SET $col=$col-$summa WHERE filial_id=0");
        $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,summa_usd,usd_kurs,manba_id,manba_tur,filial_id,izoh,xodim_id,sana)
                VALUES ('chiqim','postavshik_tolov',$summa,0,0,$qarz_id,'qarz_tolov',0,'Kontragentga to\\'lov: " . esc($izoh) . "',$admin,NOW())");
        im_log('im_qarz_tolovlar', $qarz_id, 'insert', ['qoldi' => (float)$qarz['qoldiq']], ['summa' => $summa, 'tolov_turi' => $tt, 'yangi_qoldi' => $yq], "Qarzga to'lov: $summa so'm ($tt)");
        return $summa;
    }, "qarz-tolov $qarz_id");
}
// dukon/ajax/nasiya-tolov.php — filial kassasiga
function f_nasiya_tolov($nasiya_id, $summa, $tt, $kassir) {
    global $db, $FILIAL;
    $filial_id = $FILIAL;
    return tx(function () use ($db, $filial_id, $nasiya_id, $summa, $tt, $kassir) {
        $n = $db->row("SELECT * FROM im_nasiya WHERE id=$nasiya_id AND holat='aktiv' FOR UPDATE");
        if (!$n) throw new Exception('nasiya aktiv emas');
        $summa = min($summa, (float)$n['qoldiq']);
        $db->insert("INSERT INTO im_nasiya_tolov (nasiya_id, summa, tolov_turi, usd_summa, usd_kurs, izoh, kassir_id) VALUES ($nasiya_id, $summa, '$tt', 0, 0, '', $kassir)");
        $yt = (float)$n['tolangan'] + $summa; $yq = (float)$n['qoldiq'] - $summa; $yh = $yq <= 0.01 ? 'yopildi' : 'aktiv';
        $db->q("UPDATE im_nasiya SET tolangan=$yt, qoldiq=$yq, holat='$yh' WHERE id=$nasiya_id");
        $db->q("UPDATE im_mijozlar SET nasiya_qoldiq=nasiya_qoldiq-$summa WHERE id={$n['mijoz_id']}");
        $col = kassa_col($tt);
        $db->q("UPDATE im_kassa SET $col=$col+$summa WHERE filial_id=$filial_id");
        $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, manba_id, manba_tur, filial_id, xodim_id) VALUES ('kirim','nasiya_tolov',$summa,$nasiya_id,'nasiya',$filial_id,$kassir)");
        return $summa;
    }, "nasiya-tolov $nasiya_id");
}
// admin/ajax/kapital-kiritish-save.php / pul-olish-save.php — markaziy kassa
function f_kapital($summa, $tt, $admin, $izoh) {
    global $db;
    return tx(function () use ($db, $summa, $tt, $admin, $izoh) {
        $col = kassa_col($tt);
        $db->q("UPDATE im_kassa SET $col=$col+$summa WHERE filial_id=0");
        $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,summa_usd,usd_kurs,manba_id,manba_tur,filial_id,izoh,xodim_id,sana)
                VALUES ('kirim','boshqa_kirim',$summa,0,0,$admin,'$tt',0,'" . esc($izoh) . "',$admin,NOW())");
        im_log('im_kassa', 0, 'insert', null, ['summa' => $summa, 'tt' => $tt], $izoh);
        return true;
    }, "kapital");
}
function f_pul_olish($summa, $tt, $admin, $izoh) {
    global $db;
    if (kassa_bal(0, $tt) < $summa) { warn("pul olish: markazda yetarli emas"); return null; }
    return tx(function () use ($db, $summa, $tt, $admin, $izoh) {
        $col = kassa_col($tt);
        $db->q("UPDATE im_kassa SET $col=$col-$summa WHERE filial_id=0");
        $db->q("INSERT INTO im_balans (tur,kategoriya,summa_som,summa_usd,usd_kurs,manba_id,manba_tur,filial_id,izoh,xodim_id,sana)
                VALUES ('chiqim','admin_pul_olish',$summa,0,0,$admin,'$tt',0,'" . esc($izoh) . "',$admin,NOW())");
        im_log('im_kassa', 0, 'update', null, ['summa' => $summa, 'tt' => $tt], $izoh);
        return true;
    }, "pul-olish");
}
// admin/ajax/valyuta-save.php
function f_valyuta($sana, $kurs, $admin) {
    global $db;
    $db->q("DELETE FROM im_valyuta_kurs WHERE sana='$sana'");
    $db->q("INSERT INTO im_valyuta_kurs (sana, usd_kurs, manba, tasdiqlandi, qo_shgan_id) VALUES ('$sana', $kurs, 'qolda', 1, $admin)");
}
// sklad/ajax/inventarizatsiya-save.php — sanoq (farq FIFO orqali korrektirovka)
function f_inventarizatsiya($location_id, array $sanoq /*mid=>real*/, $xodim, $izoh = '') {
    global $db;
    ksort($sanoq, SORT_NUMERIC);
    return tx(function () use ($db, $location_id, $sanoq, $xodim, $izoh) {
        $nomzod = [];
        foreach ($sanoq as $mid => $real) {
            $taxmin = (float)$db->val("SELECT COALESCE(SUM(remaining_qty),0) FROM im_fifo_layers WHERE location_id=$location_id AND mahsulot_id=$mid AND cancelled=0");
            if (abs(round($real - $taxmin, 3)) > 0.0005) $nomzod[$mid] = true;
        }
        $inv_id = (int)$db->insert("INSERT INTO im_inventarizatsiya (location_id, xodim_id, izoh) VALUES ($location_id, $xodim, '" . esc($izoh) . "')");
        $ortiqcha = 0.0; $kamomad = 0.0;
        foreach ($sanoq as $mid => $real) {
            if (empty($nomzod[$mid])) {
                $hisob = round((float)im_fifo_balance($db, $location_id, $mid)['qty'], 3);
                $db->q("INSERT INTO im_inventarizatsiya_items (inv_id, mahsulot_id, hisob_soni, real_soni, farq, birlik_narx, summa) VALUES ($inv_id, $mid, $hisob, $real, " . round($real - $hisob, 3) . ", 0, 0)");
                continue;
            }
            im_fifo_lock($db, $location_id, $mid);
            $bal = im_fifo_balance($db, $location_id, $mid, true);
            $hisob = round((float)$bal['qty'], 3); $farq = round($real - $hisob, 3);
            $bn = (float)$bal['unit_cost']; if ($bn <= 0) $bn = im_fifo_last_cost($db, $mid);
            $summa = 0.0;
            if ($farq > 0.0005) { im_fifo_receive($db, $location_id, $mid, $farq, $bn, 'korrektirovka', $inv_id); $summa = round($farq * $bn, 2); $ortiqcha += $summa; }
            elseif ($farq < -0.0005) { $take = im_fifo_take($db, $location_id, $mid, -$farq, 'korrektirovka', $inv_id); $bn = (float)$take['unit_cost']; $summa = -round((float)$take['total'], 2); $kamomad += round((float)$take['total'], 2); }
            $db->q("INSERT INTO im_inventarizatsiya_items (inv_id, mahsulot_id, hisob_soni, real_soni, farq, birlik_narx, summa) VALUES ($inv_id, $mid, $hisob, $real, $farq, " . number_format($bn, 6, '.', '') . ", " . number_format($summa, 2, '.', '') . ")");
        }
        $db->q("UPDATE im_inventarizatsiya SET ortiqcha_summa=" . number_format($ortiqcha, 2, '.', '') . ", kamomad_summa=" . number_format($kamomad, 2, '.', '') . " WHERE id=$inv_id");
        im_log('im_inventarizatsiya', $inv_id, 'insert', null, ['qatorlar' => count($sanoq), 'ortiqcha' => $ortiqcha, 'kamomad' => $kamomad], "Inventarizatsiya — " . count($sanoq) . " qator");
        return $inv_id;
    }, "inventarizatsiya");
}

// ═══════════════════════════════════════════════════════════
//  4. SIMULYATSIYA YORDAMCHILARI
// ═══════════════════════════════════════════════════════════
function branch_avail($key) { global $db, $P, $FILIAL; return (float)$db->val("SELECT COALESCE(soni,0) FROM im_filial_qoldiq WHERE filial_id=$FILIAL AND mahsulot_id={$P[$key]}"); }
function wh_qty($key) { global $db, $P; return (float)im_fifo_balance($db, 0, $P[$key])['qty']; }

// Xarid paketlari (kalit => [miqdor, kelish narxi tebranishi %])
$PACK = [
    'gosht' => 40, 'tovuq' => 12, 'guruch' => 80, 'un' => 50, 'tuz' => 5, 'ziravor' => 1.5, 'noxat' => 6, 'shakar' => 10, 'choyb' => 2, 'kofeb' => 1.2, 'yog' => 40,
    'sabzi' => 40, 'piyoz' => 30, 'pomidor' => 20, 'bodring' => 12, 'kartoshka' => 25, 'qalampir' => 6, 'limon' => 3, 'tuxum' => 6,
    'cola05' => 300, 'cola15' => 96, 'fanta' => 120, 'suv' => 240, 'ayron' => 120, 'kompot' => 96, 'chakchak' => 40,
    'non' => 70, 'patir' => 24,
];
function kelish_narx($key) { global $KELISH; return round($KELISH[$key] * rndf(0.96, 1.06, 2)); }

// Filialda $need bo'lishini ta'minlash: ombordan jo'natish, kerak bo'lsa avval xarid
function ensure_branch($key, $need) {
    global $PACK, $PS_OF, $RETSEPT, $OSHPAZ1, $SKLAD, $FILIAL, $im_user_id;
    global $MINFO, $RETSEPT_DEF;
    $need = round($need, 3);
    if ($key === 'nonyarim') { ensure_branch('non', ceil($need / 2)); return; }
    if ($MINFO[$key]['qozon']) return;                                   // qozon — alohida ochiladi
    if ($MINFO[$key]['oshpaz'] || $MINFO[$key]['ra']) {                  // taom → xomashyosi
        foreach ($RETSEPT_DEF[$key] as $ing => $s) ensure_branch($ing, round($s * $need, 3) + 0.05);
        return;
    }
    $avail = branch_avail($key);
    if ($avail + 0.0005 >= $need) return;
    $short = round($need - $avail, 3);
    if ($key === 'xamir') {   // ishlab chiqariladi
        $runs = max(10, ceil($short / 1) + 4);
        ensure_branch('un', 0.75 * $runs); ensure_branch('tuxum', 0.08 * $runs); ensure_branch('tuz', 0.005 * $runs + 0.5);
        $prev = $im_user_id; actor($OSHPAZ1);
        f_ishlab($RETSEPT['xamir'], $runs, $FILIAL, $OSHPAZ1, "Lag'mon xamiri — $runs kg");
        actor($prev);
        return;
    }
    $pack = $PACK[$key] ?? 10;
    $topup = max($short, round($pack * 0.6, 3));
    $prev = $im_user_id; actor($SKLAD);
    if (($PS_OF[$key] ?? '') === 'non') {   // nonvoyxona to'g'ridan-to'g'ri filialga
        f_partiya('non', [$key => [max($short, $pack), kelish_narx($key)]], $FILIAL, 3, 'shoshilinch');
        actor($prev); return;
    }
    $wh = wh_qty($key);
    if ($wh + 0.0005 < $topup) {
        f_partiya($PS_OF[$key], [$key => [round(max($topup - $wh, $pack), 3), kelish_narx($key)]], 0, 14, 'shoshilinch');
        $wh = wh_qty($key);
    }
    f_send($key, round(min($topup, $wh), 3));
    actor($prev);
}

// Rejalashtirilgan qatorlar bo'yicha kunlik xomashyo/tovar ehtiyoji
function kun_ehtiyoji(array $lines_all, array $pot_xom) {
    global $RETSEPT_DEF, $MINFO, $SETLAR;
    $need = [];
    $add = function ($key, $qty) use (&$need, $RETSEPT_DEF, $MINFO) {
        $m = $MINFO[$key];
        if ($m['qozon']) return;                       // qozon — alohida
        if ($m['oshpaz'] || $m['ra']) { foreach ($RETSEPT_DEF[$key] as $ing => $s) $need[$ing] = ($need[$ing] ?? 0) + $s * $qty; }
        else $need[$key] = ($need[$key] ?? 0) + $qty;
    };
    foreach ($lines_all as [$key, $qty]) $add($key, $qty);
    foreach ($pot_xom as $ing => $s) $need[$ing] = ($need[$ing] ?? 0) + $s;
    return $need;
}

// Menyu ehtimolliklari (chek turi bo'yicha)
$MENU_W = ['osh' => 30, 'somsa' => 14, 'lagmon' => 9, 'shashlik' => 8, 'manti' => 7, 'mastava' => 4, 'shorva' => 4, 'tshashlik' => 5,
    'choy' => 26, 'lchoy' => 6, 'kofe' => 5, 'achchiq' => 14, 'cola05' => 12, 'cola15' => 6, 'fanta' => 5, 'suv' => 10, 'ayron' => 7, 'kompot' => 4,
    'non' => 18, 'patir' => 5, 'nonyarim' => 6, 'chakchak' => 2, 'set:1' => 5, 'set:2' => 2];
$DIRECT_W = ['somsa' => 0, 'choy' => 8, 'lchoy' => 2, 'kofe' => 4, 'achchiq' => 4, 'cola05' => 14, 'cola15' => 6, 'fanta' => 6, 'suv' => 12, 'ayron' => 7, 'kompot' => 4, 'non' => 12, 'patir' => 4, 'nonyarim' => 5, 'chakchak' => 3];
function qty_for($key) {
    switch ($key) {
        case 'osh':
            return wpick([1 => 55, 2 => 30, 3 => 12, 4 => 3]);
        case 'somsa':
            return chance(0.08) ? rnd(10, 14) : wpick([1 => 20, 2 => 40, 3 => 20, 4 => 15, 6 => 5]);
        case 'choy':
            return wpick([1 => 70, 2 => 30]);
        case 'non':
            return wpick([1 => 40, 2 => 40, 3 => 15, 4 => 5]);
        case 'nonyarim':
            return wpick([1 => 50, 2 => 40, 3 => 10]);
        case 'cola05':
        case 'suv':
        case 'fanta':
        case 'ayron':
        case 'kompot':
            return wpick([1 => 50, 2 => 30, 3 => 12, 4 => 8]);
        case 'achchiq':
            return wpick([1 => 60, 2 => 30, 3 => 10]);
        default:
            return wpick([1 => 65, 2 => 28, 3 => 7]);
    }
}
// Savat yaratish (kalit=>[soni,set_id]) — set tarkibi alohida qatorlarga yoyiladi
function make_cart($type, $nlines) {
    global $MENU_W, $DIRECT_W, $SETLAR, $MINFO;
    $w = $type === 'direct' ? $DIRECT_W : $MENU_W;
    if ($type === 'delivery') { $w = $MENU_W; $w['choy'] = 4; $w['lchoy'] = 1; $w['kofe'] = 1; }
    $cart = [];
    for ($i = 0; $i < $nlines; $i++) {
        $k = wpick($w);
        if (strpos($k, 'set:') === 0) {
            $sid = (int)substr($k, 4);
            foreach ($SETLAR[$sid]['items'] as $ik => $s) { $kk = "$ik|$sid"; $cart[$kk] = ['key' => $ik, 'soni' => ($cart[$kk]['soni'] ?? 0) + $s, 'set_id' => $sid]; }
            continue;
        }
        $kk = "$k|0";
        if (isset($cart[$kk])) continue;
        $cart[$kk] = ['key' => $k, 'soni' => qty_for($k), 'set_id' => 0];
    }
    return array_values($cart);
}
function cart_has_kitchen(array $cart) { global $MINFO; foreach ($cart as $l) if ($MINFO[$l['key']]['oshpaz']) return true; return false; }
function cart_to_items(array $cart) { global $P; return array_map(fn($l) => ['mid' => $P[$l['key']], 'soni' => $l['soni'], 'set_id' => $l['set_id']], $cart); }

// To'lov strategiyasi
function pay_strategy($mode, $kurs) {
    return function (float $T) use ($mode, $kurs) {
        $T = round($T, 2);
        switch ($mode) {
            case 'karta': return ['karta' => $T];
            case 'bank':  return ['bank' => $T];
            case 'split': $k = round($T * rndf(0.3, 0.7, 2), 2); return ['karta' => $k, 'naqd' => round($T - $k, 2)];
            case 'usd':   $usd = floor($T / $kurs); if ($usd < 1) return ['naqd' => $T]; return ['usd' => $usd, 'usd_kurs' => $kurs, 'naqd' => round($T - $usd * $kurs, 2)];
            case 'nasiya': return ['nasiya' => $T];
            default: return ['naqd' => $T];
        }
    };
}

// ═══════════════════════════════════════════════════════════
//  5. SIMULYATSIYA — kun-bakun
// ═══════════════════════════════════════════════════════════
$HOUR_W = [10 => 0.4, 11 => 0.7, 12 => 1.6, 13 => 1.8, 14 => 1.1, 15 => 0.6, 16 => 0.5, 17 => 0.8, 18 => 1.3, 19 => 1.6, 20 => 1.4, 21 => 0.9, 22 => 0.35];
$DOW_BASE = [1 => 84, 2 => 80, 3 => 86, 4 => 92, 5 => 104, 6 => 118, 0 => 112];   // N = date('w')
$STAT = ['chek' => 0, 'order' => 0, 'vozvrat' => 0, 'bekor' => 0, 'qozon' => 0, 'partiya' => 0];
$KURS = 12850;
$KURS_SANA = null;

// ── Kun -3 .. -1: kapital, kurs, boshlang'ich xaridlar ───────
clock_set($START_TS - 3 * 86400 + 9 * 3600 + 1800);
actor($ADMIN);
f_kapital(40000000, 'naqd', $ADMIN, "Boshlang'ich kapital (egadan)");
f_valyuta(sd(), $KURS, $ADMIN);
$STAT['partiya']++;
clock_set($START_TS - 2 * 86400 + 10 * 3600);
actor($SKLAD);
$first = fn($keys, $mult) => array_combine($keys, array_map(fn($k) => [round($PACK[$k] * $mult, 3), kelish_narx($k)], $keys));
f_partiya('gosht',   $first(['gosht', 'tovuq'], 1.6), 0, 14, 'F-0001');
f_partiya('don',     $first(['guruch', 'un', 'tuz', 'ziravor', 'noxat', 'shakar', 'choyb', 'kofeb', 'yog'], 1.5), 0, 21, 'F-0002');
f_partiya('sabzavot',$first(['sabzi', 'piyoz', 'pomidor', 'bodring', 'kartoshka', 'qalampir', 'limon', 'tuxum'], 1.6), 0, 10, 'F-0003');
f_partiya('ichimlik',$first(['cola05', 'cola15', 'fanta', 'suv', 'ayron', 'kompot', 'chakchak'], 1.5), 0, 21, 'F-0004');
$STAT['partiya'] += 4;
clock_set($START_TS - 1 * 86400 + 9 * 3600);
foreach (['gosht', 'tovuq', 'guruch', 'un', 'tuz', 'ziravor', 'noxat', 'shakar', 'choyb', 'kofeb', 'yog', 'sabzi', 'piyoz', 'pomidor', 'bodring', 'kartoshka', 'qalampir', 'limon', 'tuxum',
          'cola05', 'cola15', 'fanta', 'suv', 'ayron', 'kompot', 'chakchak'] as $k) {
    f_send($k, round(wh_qty($k) * 0.6, 3));
}
f_partiya('non', ['non' => [$PACK['non'], kelish_narx('non')], 'patir' => [$PACK['patir'], kelish_narx('patir')]], $FILIAL, 3, 'F-0005');
$STAT['partiya']++;
clock_set($START_TS - 1 * 86400 + 11 * 3600);
actor($OSHPAZ1);
f_ishlab($RETSEPT['xamir'], 14, $FILIAL, $OSHPAZ1, "Lag'mon xamiri — haftalik");
say("Boshlang'ich xaridlar, jo'natmalar va xamir tayyor");

$OPEN_NASIYA_PAY_DAYS = [];
$last_salary_month = null;

for ($day = 0; $day < $DAYS; $day++) {
    $D0 = $START_TS + $day * 86400;                 // kun boshi 00:00
    $dow = (int)date('w', $D0); $dnum = (int)date('j', $D0);
    $T = fn($h, $m = 0) => $D0 + $h * 3600 + $m * 60;
    $cutoff = ($LIVE && $day === $DAYS - 1) ? $REAL_NOW : PHP_INT_MAX;   // bugun: hozirgi vaqtgacha
    $at = fn($h, $m = 0) => $T($h, $m) <= $cutoff;
    if (!$at(8, 5)) { say(sd() . ": hali erta — bugun uchun voqea yo'q"); break; }
    $kassir = in_array($dow, [5, 6, 0], true) ? $KASSIR2 : $KASSIR1;
    $waiters = array_keys(array_filter([$SOTUVCHI1 => $dow !== 2, $SOTUVCHI2 => $dow !== 5]));
    $cook = fn($ts) => ((int)date('G', $ts) < 17) ? $OSHPAZ1 : $OSHPAZ2;

    // ── 08:00 admin: kechagi inkasasiyalarni qabul qilish, kurs ──
    clock_set($T(8, 5)); actor($ADMIN);
    foreach ($db->rows("SELECT id FROM im_inkasasiya WHERE holat='kutilmoqda' ORDER BY id") as $ink) f_inkasasiya_accept((int)$ink['id'], $ADMIN);
    if ($day % 3 === 0) { $KURS = round($KURS * rndf(0.997, 1.006, 4) / 10) * 10; f_valyuta(sd(), $KURS, $ADMIN); }
    // Postavshik to'lovlari — dushanba/payshanba
    if (in_array($dow, [1, 4], true) && $at(8, 20)) {
        clock_set($T(8, 20));
        foreach ($db->rows("SELECT q.id, q.qoldiq, p.nomi FROM im_postavshik_qarz q JOIN im_postavshiklar p ON p.id=q.postavshik_id
                            WHERE q.status='ochiq' AND q.created_at < '" . date('Y-m-d', $D0 - 2 * 86400) . "' ORDER BY q.muddat, q.id") as $q) {
            // Katta to'lovlar o'tkazma (karta/bank), mayda — naqd
            $tt = 'naqd';
            foreach (['karta', 'bank', 'naqd'] as $c) { if (kassa_bal(0, $c) - ($c === 'naqd' ? 3000000 : 500000) >= (float)$q['qoldiq']) { $tt = $c; break; } }
            $bal = kassa_bal(0, $tt) - ($tt === 'naqd' ? 3000000 : 500000);
            if ($bal <= 500000) continue;
            $pay = min((float)$q['qoldiq'], $bal);
            if ($pay < 100000) continue;
            f_qarz_tolov((int)$q['id'], round($pay, 2), $tt, $ADMIN, $q['nomi']);
        }
    }
    // Maosh — oyning 5-kuni (o'tgan oy uchun) va 20-kuni avans
    if (($dnum === 5 || $dnum === 20) && $at(11, 0)) {
        clock_set($T(11, 0));
        $oy = $dnum === 5 ? date('Y-m', $D0 - 10 * 86400) : date('Y-m', $D0);
        foreach ($WORKERS as $w) { $sm = $dnum === 5 ? $w['stavka'] * 0.6 : $w['stavka'] * 0.4; f_maosh($w, $sm, $oy, kassa_bal(0, 'karta') >= $sm ? 'karta' : 'naqd', $ADMIN); }
    }
    if ($dnum === 1 && $at(11, 30)) { clock_set($T(11, 30)); f_harajat(0, 'Ijara (oylik)', 'ijara', 12000000, 'naqd', $ADMIN, ''); }
    if (($day === 16 || $day === 27) && $at(12, 0)) { clock_set($T(12, 0)); $tt_ = kassa_bal(0, 'naqd') >= 8000000 ? 'naqd' : 'karta'; if (kassa_bal(0, $tt_) >= 5000000) f_pul_olish(5000000, $tt_, $ADMIN, "Ega — shaxsiy ehtiyoj"); }

    // ── Kunni rejalashtirish ──
    $N = (int)round($DOW_BASE[$dow] * $SCALE * rndf(0.93, 1.07, 3));
    $tables = []; foreach ($STOLLAR as $s) $tables[(int)$s['id']] = ['nomi' => $s['nomi'], 'free' => 0, 'busy' => 0];
    $orders = [];
    $t0s = []; for ($i = 0; $i < $N; $i++) { $h = (int)wpick($HOUR_W); $t0s[] = $T($h, rnd(0, 59)) + rnd(0, 59); }
    sort($t0s);
    foreach ($t0s as $t0) {
        $r = mt_rand() / mt_getrandmax();
        $type = $r < 0.55 ? 'table' : ($r < 0.75 ? 'delivery' : ($r < 0.90 ? 'direct' : 'takeaway'));
        $o = ['t0' => $t0, 'type' => $type, 'stol' => 0, 'cart' => [], 'waiter' => pick($waiters), 'mijoz' => 0, 'pay' => 'naqd', 'chegirma' => 0];
        if ($type === 'takeaway') {
            $cands = array_filter($tables, fn($tb) => $tb['busy'] > $t0 + 300);   // asosiy order hali kassaga o'tmagan
            if (!$cands) $type = $o['type'] = 'delivery'; else $o['stol'] = array_rand($cands);
        }
        if ($type === 'table') {
            $free = array_filter($tables, fn($tb) => $tb['free'] <= $t0);
            if (!$free) $type = $o['type'] = 'delivery';
        }
        $o['cart'] = make_cart($type, $type === 'direct' ? rnd(1, 3) : ($type === 'table' ? rnd(2, 5) : rnd(1, 4)));
        $kitchen = cart_has_kitchen($o['cart']);
        // vaqtlar
        if ($type === 'direct') { $o['t_checkout'] = $t0; }
        else {
            $o['t_hold'] = $t0;
            $t = $t0;
            if ($kitchen) { $o['t_qabul'] = $t += rnd(60, 360); $o['t_tayyor'] = $t += rnd(480, 1500); }
            $o['t_create'] = $t += ($type === 'table' ? rnd(900, 3600) : rnd(120, 600));
            $o['t_checkout'] = $t += rnd(60, 480);
            if ($type === 'table' && $kitchen && chance(0.15)) $o['t_second'] = $o['t_hold'] + rnd(600, 1200);
        }
        if ($o['t_checkout'] > $T(22, 52)) continue;   // smena yopilishidan oldin
        if ($type === 'table') { $stol = array_rand($free); $o['stol'] = $stol; $tables[$stol]['busy'] = $o['t_checkout']; $tables[$stol]['free'] = $o['t_checkout'] + rnd(120, 600); }
        // mijoz / to'lov / chegirma
        if ($type !== 'direct' && chance(0.22)) $o['mijoz'] = array_rand($MIJOZ);
        $pr = mt_rand() / mt_getrandmax();
        if ($o['mijoz'] && $MIJOZ[$o['mijoz']]['nasiya'] && chance(0.12)) $o['pay'] = 'nasiya';
        elseif ($pr < 0.04) $o['pay'] = 'usd'; elseif ($pr < 0.39) $o['pay'] = 'karta'; elseif ($pr < 0.44) $o['pay'] = 'bank'; elseif ($pr < 0.52) $o['pay'] = 'split';
        if ($o['pay'] !== 'nasiya' && chance(0.06)) $o['chegirma'] = pick([5, 5, 10]);
        $orders[] = $o;
    }
    // Osh sig'imi: oshpaz kunlik talabni taxmin qilib qozon ochadi; gavjum kunda 13:30 da ikkinchi qozon
    usort($orders, fn($a, $b) => $a['t_checkout'] <=> $b['t_checkout']);
    $osh_total = 0; foreach ($orders as $o) foreach ($o['cart'] as $l) if ($l['key'] === 'osh') $osh_total += $l['soni'];
    if ($osh_total > 95 * $SCALE) { $moljal1 = (int)ceil($osh_total * 0.62) + 4; $moljal2 = (int)ceil($osh_total - $moljal1) + 8; }
    else { $moljal1 = max(15, (int)ceil($osh_total * 1.05) + 4); $moljal2 = 0; }
    $cap1 = $moljal1 - 1; $cap2 = $moljal2 > 0 ? $moljal2 - 1 : 0; $t_pot2 = $T(13, 30);
    foreach ($orders as &$o) {
        foreach ($o['cart'] as &$l) {
            if ($l['key'] !== 'osh') continue;
            if ($cap1 >= $l['soni']) { $cap1 -= $l['soni']; continue; }
            if ($o['t_checkout'] > $t_pot2 && $cap2 >= $l['soni']) { $cap2 -= $l['soni']; continue; }
            $l['key'] = pick(['lagmon', 'shashlik', 'manti']);   // osh tugadi
        }
        unset($l);
    }
    unset($o);
    // Xomashyo ehtiyoji va ertalabki jo'natma
    $lines_all = []; foreach ($orders as $o) foreach ($o['cart'] as $l) $lines_all[] = [$l['key'], $l['soni']];
    $pot_xom = []; foreach ($RETSEPT_DEF['osh'] as $ing => $s) $pot_xom[$ing] = round($s * ($moljal1 + $moljal2) * 1.02, 3);
    $need = kun_ehtiyoji($lines_all, $pot_xom);
    clock_set($T(8, 30)); actor($SKLAD);
    foreach ($need as $k => $q) ensure_branch($k, round($q * 1.25 + 0.5, 3));
    // Rejali xaridlar (ombor) — jadval bo'yicha
    if ($day % 2 === 1) { f_partiya('gosht', $first(['gosht', 'tovuq'], 1.0)); f_partiya('sabzavot', $first(['sabzi', 'piyoz', 'pomidor', 'bodring', 'kartoshka', 'qalampir', 'limon', 'tuxum'], 1.0)); $STAT['partiya'] += 2; }
    if ($dow === 1) { f_partiya('don', $first(['guruch', 'un', 'tuz', 'ziravor', 'noxat', 'shakar', 'choyb', 'kofeb', 'yog'], 1.0)); $STAT['partiya']++; }
    if ($dow === 2) { f_partiya('ichimlik', $first(['cola05', 'cola15', 'fanta', 'suv', 'ayron', 'kompot', 'chakchak'], 1.0)); $STAT['partiya']++; }
    if ($dow !== 0) { f_partiya('non', ['non' => [$PACK['non'], kelish_narx('non')], 'patir' => [$PACK['patir'], kelish_narx('patir')]], $FILIAL, 3); $STAT['partiya']++; }
    if (in_array($dow, [1, 4], true)) { clock_set($T(8, 40)); actor($OSHPAZ1); ensure_branch('un', 11); ensure_branch('tuxum', 1.2); f_ishlab($RETSEPT['xamir'], 12, $FILIAL, $OSHPAZ1, "Lag'mon xamiri"); }
    if ($day === 18 && $at(8, 45)) { clock_set($T(8, 45)); actor($SKLAD); $sanoq = [];
        foreach (['guruch', 'gosht', 'sabzi', 'piyoz', 'un', 'yog', 'kartoshka', 'pomidor'] as $k) { $h = round((float)im_fifo_balance($db, $FILIAL, $P[$k])['qty'], 3); $sanoq[$P[$k]] = max(0, round($h + rndf(-0.6, 0.3, 3), 3)); }
        f_inventarizatsiya($FILIAL, $sanoq, $SKLAD, 'Haftalik sanoq — oshxona ombori'); }

    // ── 09:00 smena, 08:50 qozon ──
    $smena_id = 0;
    if ($at(9, 0)) {
        clock_set($T(8, 50)); actor($OSHPAZ1);
        $pot_items = []; foreach ($RETSEPT_DEF['osh'] as $ing => $s) $pot_items[$ing] = round($s * $moljal1 * rndf(0.98, 1.04, 3), 3);
        foreach ($pot_items as $ing => $q) ensure_branch($ing, $q);
        $qozon1 = f_qozon_och('osh', $moljal1, $pot_items, $OSHPAZ1); $STAT['qozon']++;
        clock_set($T(9, 0)); actor($kassir);
        $smena_id = f_smena_open($kassir, 0);
    }

    // ── Voqealar navbati ──
    $ev = [];
    $push = function ($ts, $fn) use (&$ev) { $ev[] = [$ts, count($ev), $fn]; };
    if ($moljal2 > 0) $push($t_pot2, function () use (&$moljal2, $OSHPAZ1, $RETSEPT_DEF, &$STAT) {
        actor($OSHPAZ1); $it = []; foreach ($RETSEPT_DEF['osh'] as $ing => $s) $it[$ing] = round($s * $moljal2 * rndf(0.98, 1.04, 3), 3);
        foreach ($it as $ing => $q) ensure_branch($ing, $q);
        f_qozon_och('osh', $moljal2, $it, $OSHPAZ1); $STAT['qozon']++;
    });
    foreach ($orders as $idx => $o) {
        $st = new stdClass(); $st->o = $o; $st->order_id = 0; $st->ok = true; $st->sotuv = null;
        $stol_nomi = $o['stol'] ? $tables[$o['stol']]['nomi'] : 'Dastavka';
        if ($o['type'] === 'direct') {
            $push($o['t_checkout'], function () use ($st, $kassir, $smena_id, &$STAT, $KURS) {
                actor($kassir); $o = $st->o;
                foreach ($o['cart'] as $l) ensure_branch($l['key'], $l['soni']);
                $savat = [];
                foreach ($o['cart'] as $l) { $ni = seed_narx($GLOBALS['P'][$l['key']], $l['soni'], 0); $savat[] = ['mid' => $GLOBALS['P'][$l['key']], 'soni' => $l['soni'], 'narx' => $ni['ulg'] ?? $ni['asos'], 'set_id' => 0]; }
                $pay = $o['pay'] === 'nasiya' ? 'naqd' : $o['pay'];
                try { $st->sotuv = f_checkout(0, $smena_id, $kassir, $savat, pay_strategy($pay, $KURS), ['chegirma_foiz' => $o['chegirma']]); if ($st->sotuv) $STAT['chek']++; }
                catch (Throwable $e) { warn('direct: ' . $e->getMessage()); }
            });
            continue;
        }
        $push($o['t_hold'], function () use ($st, $stol_nomi, &$STAT) {
            $o = $st->o; actor($o['waiter']);
            foreach ($o['cart'] as $l) if (!$GLOBALS['MINFO'][$l['key']]['oshpaz']) ensure_branch($l['key'], $l['soni']);
            $r = f_order_save('hold', 0, $o['stol'], $o['type'] === 'takeaway', $stol_nomi, cart_to_items($o['cart']), $o['waiter']);
            if (!$r) { $st->ok = false; return; }
            $st->order_id = $r['order_id']; $st->status = $r['status']; $STAT['order']++;
        });
        if (!empty($o['t_second'])) $push($o['t_second'], function () use ($st) {
            if (!$st->ok) return; $o = $st->o; actor($o['waiter']);
            $extra = make_cart('direct', 1)[0]; $extra['soni'] = 1;
            foreach ($o['cart'] as $l) if ($l['key'] === $extra['key'] && $l['set_id'] === 0) return;
            ensure_branch($extra['key'], 1);
            $st->o['cart'][] = $extra;
            $r = f_order_save('hold', $st->order_id, $o['stol'], $o['type'] === 'takeaway', $o['stol'] ? $GLOBALS['STOL_NOMI'][$o['stol']] : 'Dastavka', cart_to_items($st->o['cart']), $o['waiter']);
            if ($r) $st->status = $r['status']; else array_pop($st->o['cart']);
        });
        if (!empty($o['t_qabul'])) {
            $push($o['t_qabul'], function () use ($st, $cook) {
                if (!$st->ok) return; $c = $cook($GLOBALS['NOW']); actor($c);
                foreach ($st->o['cart'] as $l) if ($GLOBALS['MINFO'][$l['key']]['oshpaz']) ensure_branch($l['key'], $l['soni']);
                if (!f_oshpaz_qabul($st->order_id, $c)) $st->ok = false;
            });
            $push($o['t_tayyor'], function () use ($st, $cook) {
                if (!$st->ok) return; $c = $cook($GLOBALS['NOW']); actor($c);
                if (!f_oshpaz_tayyor($st->order_id, $c)) $st->ok = false;
            });
        }
        $push($o['t_create'], function () use ($st, $stol_nomi) {
            if (!$st->ok) return; $o = $st->o; actor($o['waiter']);
            $r = f_order_save('create', $st->order_id, $o['stol'], $o['type'] === 'takeaway', $o['stol'] ? $GLOBALS['STOL_NOMI'][$o['stol']] : 'Dastavka', cart_to_items($st->o['cart']), $o['waiter']);
            if (!$r || $r['status'] !== 'tasdiqlandi') { $st->ok = false; if ($r) warn("create → {$r['status']} (#{$st->order_id})"); }
        });
        $push($o['t_checkout'], function () use ($st, $kassir, $smena_id, &$STAT, $KURS) {
            if (!$st->ok) return; $o = $st->o; actor($kassir);
            // 0.6% — kassir buyurtmani bekor qiladi (pending-orders: cancel)
            if (chance(0.006)) { f_order_bekor($st->order_id, $kassir); $STAT['bekor']++; $st->ok = false; return; }
            foreach ($st->o['cart'] as $l) if ($GLOBALS['MINFO'][$l['key']]['ra']) ensure_branch($l['key'], $l['soni']);
            $lines = $GLOBALS['db']->rows("SELECT mahsulot_id, soni, narx, COALESCE(set_id,0) set_id FROM im_sotuvchi_order_item WHERE order_id={$st->order_id}");
            $savat = array_map(fn($l) => ['mid' => (int)$l['mahsulot_id'], 'soni' => (float)$l['soni'], 'narx' => (float)$l['narx'], 'set_id' => (int)$l['set_id']], $lines);
            try {
                $st->sotuv = f_checkout($st->order_id, $smena_id, $kassir, $savat, pay_strategy($o['pay'], $KURS),
                    ['mijoz_id' => $o['mijoz'], 'chegirma_foiz' => $o['chegirma'], 'xizmat' => $o['type'] === 'table', 'nasiya' => $o['pay'] === 'nasiya']);
                if ($st->sotuv) $STAT['chek']++;
            } catch (Throwable $e) { warn("checkout #{$st->order_id}: " . $e->getMessage()); }
        });
        // Vozvrat — 1.2%
        if (chance(0.012) && $o['pay'] !== 'nasiya' && $o['t_checkout'] + 1800 < $T(22, 50)) {
            $push($o['t_checkout'] + rnd(1800, 7200), function () use ($st, $kassir, &$STAT) {
                if (!$st->sotuv) return; actor($kassir);
                $line = $GLOBALS['db']->row("SELECT id, soni, chegirma_narxi, fifo_return_mode FROM im_sotuv_items WHERE sotuv_id={$st->sotuv['sotuv_id']} ORDER BY (fifo_return_mode='stock') DESC, id LIMIT 1");
                if (!$line) return;
                $q = min(1, (float)$line['soni']);
                if (f_vozvrat((int)$line['id'], $q, round((float)$line['chegirma_narxi'] * $q, 2), 'naqd', pick(['Mijoz yoqtirmadi', 'Noto\'g\'ri buyurtma', 'Sovuq kelgan', 'Ortiqcha olingan']), $kassir)) $STAT['vozvrat']++;
            });
        }
    }
    // Harajatlar (filial kassasidan, kunduzi)
    if (chance(0.8)) $push($T(16, rnd(0, 50)), fn() => (actor($kassir) ?? f_harajat($FILIAL, 'Yetkazib berish (taksi)', 'yuk', rnd(6, 14) * 10000, 'naqd', $kassir)));
    if (chance(0.3))  $push($T(17, rnd(0, 50)), fn() => (actor($kassir) ?? f_harajat($FILIAL, pick(['Idish-tovoq', 'Tozalash vositalari', 'Salfetka, paket']), 'boshqa', rnd(4, 12) * 10000, 'naqd', $kassir)));
    if (chance(0.15)) $push($T(15, rnd(0, 50)), fn() => (actor($kassir) ?? f_harajat($FILIAL, 'Gaz ballon', 'boshqa', 250000, 'naqd', $kassir)));
    if ($dow === 1) $push($T(15, 10), fn() => (actor($kassir) ?? f_harajat($FILIAL, 'Kommunal (gaz, suv, svet)', 'kommunal', 900000, 'naqd', $kassir)));
    if ($dow === 3) $push($T(15, 20), fn() => (actor($kassir) ?? f_harajat($FILIAL, 'Instagram reklama', 'reklama', rnd(3, 6) * 100000, 'karta', $kassir)));
    // Nasiya to'lovlari — kunduzi
    $push($T(15, rnd(0, 59)), function () use ($kassir, $D0) {
        actor($kassir);
        foreach ($GLOBALS['db']->rows("SELECT id, qoldiq FROM im_nasiya WHERE holat='aktiv' AND created_at < '" . date('Y-m-d', $D0 - 4 * 86400) . "' ORDER BY id") as $n) {
            if (!chance(0.35)) continue;
            $q = (float)$n['qoldiq'];
            f_nasiya_tolov((int)$n['id'], chance(0.6) ? $q : round($q / 2, 2), chance(0.7) ? 'naqd' : 'karta', $kassir);
        }
    });

    // ── Voqealarni vaqt tartibida bajarish ──
    usort($ev, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
    foreach ($ev as [$ts, , $fn]) { if ($ts > $cutoff || !$smena_id) break; clock_set($ts); $fn(); }
    if (!$at(22, 45)) {   // bugun — smena va qozon OCHIQ qoladi, stollar band
        say(sd() . ": jonli holat — smena/qozon ochiq, " . (int)$db->val("SELECT COUNT(*) FROM im_sotuvchi_order WHERE status NOT IN ('tugallandi','bekor')") . " ta faol buyurtma");
        break;
    }

    // ── 22:45 qozonlarni yopish, 23:00 smena, 23:05 inkasasiya ──
    clock_set($T(22, 45)); actor($OSHPAZ2);
    foreach ($db->rows("SELECT q.id, l.remaining_qty FROM im_osh_qozon q LEFT JOIN im_fifo_layers l ON l.source='qozon' AND l.source_id=q.id AND l.cancelled=0
                        WHERE q.holat='ochiq' AND q.filial_id=$FILIAL ORDER BY q.id") as $q) {
        $rem = (float)$q['remaining_qty']; $sold = (float)$db->val("SELECT COALESCE(SUM(m.qty - m.reversed_qty),0) FROM im_fifo_movements m JOIN im_fifo_layers l ON l.id=m.layer_id WHERE l.source='qozon' AND l.source_id={$q['id']} AND m.kind='take' AND m.source='sotuv'");
        $qoldi = $rem > 0 ? min($rem, rnd(0, 6)) : 0;
        if ($sold + $qoldi <= 0) $qoldi = 1;
        f_qozon_yop((int)$q['id'], $qoldi, 1, $OSHPAZ2, $qoldi > 0 ? 'Kun oxiri qoldiq — isrof' : '');
    }
    clock_set($T(23, 0)); actor($kassir);
    $res = f_smena_close($smena_id, $kassir);
    clock_set($T(23, 5));
    $naqd = kassa_bal($FILIAL, 'naqd');
    if ($naqd > 2500000) f_inkasasiya(floor(($naqd - 1500000) / 10000) * 10000, 'naqd', $kassir);
    if ($dow === 0) {
        $k = kassa_bal($FILIAL, 'karta'); if ($k > 100000) f_inkasasiya(floor($k), 'karta', $kassir, 'Haftalik karta tushumi');
        $b = kassa_bal($FILIAL, 'bank');  if ($b > 100000) f_inkasasiya(floor($b), 'bank', $kassir, 'Haftalik bank tushumi');
        $u = kassa_bal($FILIAL, 'usd');   if ($u >= 50) f_inkasasiya(floor($u), 'usd', $kassir, 'Haftalik USD tushumi');
    }
    say(sprintf("%s (%s): %3d chek, tushum %11s, sof %10s | orders=%d qozon=%d vozvrat=%d xato=%d",
        sd(), ['Ya', 'Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh'][$dow], $res['n'] ?? 0, number_format($res['jami'] ?? 0, 0, '.', ' '), number_format($res['sof'] ?? 0, 0, '.', ' '),
        $STAT['order'], $STAT['qozon'], $STAT['vozvrat'], $ERRORS));
}

// ── Kassir bekor qilishi (dukon/ajax/pending-orders.php: cancel) ──
function f_order_bekor($order_id, $kassir) {
    global $db, $FILIAL;
    $filial_id = $FILIAL;
    return tx(function () use ($db, $filial_id, $order_id, $kassir) {
        $o = $db->row("SELECT * FROM im_sotuvchi_order WHERE id=$order_id AND filial_id=$filial_id FOR UPDATE");
        if (!$o || !in_array($o['status'], ['tasdiqlandi', 'qabul'], true)) throw new Exception('bekor: holat mos emas');
        im_rezerv_bekor($db, $order_id, $filial_id);
        $db->q("UPDATE im_ishlab_chiqarish ic JOIN im_sotuvchi_order_item i ON i.id=ic.order_item_id SET ic.holat='isrof', ic.isrof_vaqt=NOW() WHERE i.order_id=$order_id AND ic.holat='bajarildi'");
        $db->q("UPDATE im_sotuvchi_order SET status='bekor', updated_at=NOW() WHERE id=$order_id");
        seed_xodim_log($kassir, 'bekor', $order_id, (string)$o['mijoz_ism'], 0, 'Kassir bekor qildi');
        return true;
    }, "bekor #$order_id");
}

// ═══════════════════════════════════════════════════════════
//  6. YAKUN
// ═══════════════════════════════════════════════════════════
clock_reset();
$db->q("TRUNCATE TABLE im_ai_hisobot");
say("\nTayyor: {$STAT['chek']} chek, {$STAT['order']} buyurtma, {$STAT['qozon']} qozon, {$STAT['vozvrat']} vozvrat, {$STAT['bekor']} bekor, {$STAT['partiya']} partiya; xatolar: $ERRORS");
say("Yangi loginlar: sotuvchi2 / oshpaz2 / kassir2 — parol: $DEMO_PAROL");
say("Tekshirish: php tools/fifo-check.php");
