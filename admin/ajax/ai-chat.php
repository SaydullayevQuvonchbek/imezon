<?php
// ============================================================
//  IMezon — AI Yordamchi: savol → javob
// ------------------------------------------------------------
//  Suhbat oqimi im_ai_suhbat da saqlanadi (bitta JSON qator) —
//  sahifa yangilansa tiklanadi, yon panelda ro'yxat chiqadi.
//  Savol-javobning O'ZI esa im_ai_log ga ham yoziladi (audit,
//  token hisobi). Har foydalanuvchi faqat o'z suhbatini ko'radi.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../ai_lib.php';
im_rol_check(['admin']);

header('Content-Type: application/json; charset=utf-8');
$db = new Cyber();

$savol     = isset($_POST['savol']) ? trim($_POST['savol']) : '';
$yangi     = !empty($_POST['yangi']);                         // "Yangi suhbat" tugmasi
$fil       = (int)($_POST['filial_id'] ?? 0);
$suhbat_id = im_ai_suhbat_id_toza($_POST['suhbat_id'] ?? '');
$yangi_edi = ($suhbat_id === '');                             // klient panelni yangilashi uchun
$model     = im_ai_model_toza($_POST['model'] ?? '');         // suhbat tanlovi (bo'sh = standart)
$effort    = im_ai_effort_toza($_POST['effort'] ?? '');

// "Yangi suhbat" + savolsiz — shunchaki bo'sh id qaytaramiz
if ($yangi && $savol === '') {
    echo json_encode(['status' => 'ok', 'msg' => 'Yangi suhbat', 'suhbat_id' => ''],
                     JSON_UNESCAPED_UNICODE);
    exit;
}
if ($yangi) $suhbat_id = '';   // eski suhbatdan uzilib, yangisini boshlaymiz

if ($savol === '') {
    echo json_encode(['status' => 'error', 'msg' => 'Savol bo\'sh'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (mb_strlen($savol) > 2000) {
    echo json_encode(['status' => 'error', 'msg' => 'Savol juda uzun (2000 belgidan kam bo\'lsin)'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!im_ai_sozlangan()) {
    echo json_encode(['status' => 'error',
        'msg' => 'AI sozlanmagan — Sozlamalar → AI Yordamchi bo\'limida API kalitni kiriting'],
        JSON_UNESCAPED_UNICODE);
    exit;
}

// Tarix — saqlangan suhbatdan (oxirgi N xabar). Cheklamasak har savol
// qimmatlashib boradi va model eskirgan raqamlarga suyanib qoladi.
$soz   = im_ai_soz();
$tarix = [];
if ($suhbat_id !== '' && $soz['tarix'] > 0) {
    $s = im_ai_suhbat_ol($db, $suhbat_id, $im_user_id);
    if ($s) $tarix = im_ai_suhbat_tarix($s['xabarlar'], $soz['tarix']);
    else    $suhbat_id = '';   // begona/o'chirilgan id — yangisini boshlaymiz
}

// ── Mavzu chegarasi: API'ga bormasdan rad etish ────────────
// Tizim ko'rsatmasidagi "Doiran" bo'limi ham buni taqiqlaydi, lekin model
// (ayniqsa arzon modellar) ba'zan ko'rsatmadan chetga chiqadi. Shuning uchun
// ANIQ mavzudan tashqari so'rov (kod yozish, tarjima, she'r) shu yerda
// to'xtatiladi: token sarflanmaydi va javob har doim bir xil bo'ladi.
// Savol tarixga ham, jurnal (im_ai_log) ga ham odatdagidek yoziladi.
if (im_ai_mavzudan_tashqari($savol)) {
    $r = [
        'ok'            => true,
        'javob'         => im_ai_rad_matni(),
        'xato'          => null,
        'model'         => $model !== '' ? $model : $soz['model'],
        'effort'        => $effort !== '' ? $effort : $soz['effort'],
        'vositalar'     => [],
        'natijalar'     => [],
        'ogohlantirish' => [],
        'tokens_in'     => 0,
        'tokens_out'    => 0,
        'ms'            => 0,
    ];
    im_ai_jurnal($db, $r, $savol, $im_user_id, $fil);
    $suhbat_id = im_ai_suhbat_yoz($db, $suhbat_id, $im_user_id, $fil, $savol, $r);

    echo json_encode([
        'status'    => 'ok',
        'javob'     => $r['javob'],
        'vositalar' => [],
        'natijalar' => [],
        'ogoh'      => [],
        'tokens'    => ['in' => 0, 'out' => 0],
        'ms'        => 0,
        'model'     => $r['model'],
        'effort'    => $r['effort'],
        'suhbat_id' => $suhbat_id,
        'yangi'     => $yangi_edi,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// DIQQAT: $fil = 0 — "tanlanmagan" emas, "BARCHA filiallar". Shuning uchun
// $im_filial_id ga tushib ketmaydi.
$r = im_ai_agent($db, $savol, $tarix, [
    'filial_id' => $fil,
    'model'     => $model,
    'effort'    => $effort,
]);

im_ai_jurnal($db, $r, $savol, $im_user_id, $fil);

// Suhbatga yozamiz. Yangi suhbatni XATO bilan boshlamaymiz — aks holda
// panelda "bo'sh" suhbat paydo bo'ladi. Mavjud suhbatga esa xatoni ham
// yozamiz (foydalanuvchi keyin ko'rsin).
if (!$r['ok']) {
    if ($suhbat_id !== '') {
        $suhbat_id = im_ai_suhbat_yoz($db, $suhbat_id, $im_user_id, $fil, $savol, $r);
    }
    echo json_encode([
        'status'    => 'error',
        'msg'       => $r['xato'],
        'vositalar' => array_map(function ($v) { return $v['nom']; }, $r['vositalar']),
        'ms'        => $r['ms'],
        'model'     => $r['model'],
        'suhbat_id' => $suhbat_id,
        'yangi'     => $yangi_edi && $suhbat_id !== '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$suhbat_id = im_ai_suhbat_yoz($db, $suhbat_id, $im_user_id, $fil, $savol, $r);

echo json_encode([
    'status'    => 'ok',
    'javob'     => $r['javob'],
    'vositalar' => array_map(function ($v) { return $v['nom']; }, $r['vositalar']),
    'natijalar' => $r['natijalar'],       // MANBA paneli (jonli — persist qilinmaydi)
    'ogoh'      => $r['ogohlantirish'],    // topilmagan raqamlar
    'tokens'    => ['in' => $r['tokens_in'], 'out' => $r['tokens_out']],
    'ms'        => $r['ms'],
    'model'     => $r['model'],
    'effort'    => $r['effort'],
    'suhbat_id' => $suhbat_id,
    'yangi'     => $yangi_edi,   // true bo'lsa klient yon panelni yangilaydi
], JSON_UNESCAPED_UNICODE);
