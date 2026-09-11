<?php
// ============================================================
//  IMezon — AI sozlamasini sinash (diagnostika)
// ------------------------------------------------------------
//  Sozlamalar sahifasidagi "Ulanishni tekshirish" tugmasi shu
//  yerga uradi. Xato bo'lsa API ning XOM javobi qaytariladi —
//  kalit noto'g'rimi, balans tugaganmi, model nomi xatomi:
//  darrov ko'rinadi.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../ai_lib.php';
im_rol_check(['admin']);

header('Content-Type: application/json; charset=utf-8');

// DIQQAT: "AI yoqilgan"mi — bu yerda tekshirilmaydi. Sinov aynan
// yoqishdan OLDIN kerak bo'ladi. Standart modelni sinaymiz.
$s      = im_ai_soz();
$model  = $s['model'];
$dl     = im_ai_dialekt($model);

if (im_ai_kalit($model) === '') {
    $p = $dl === 'openai' ? 'OpenRouter' : 'Claude (Anthropic)';
    echo json_encode(['status' => 'error',
        'msg' => "$p kaliti hali saqlanmagan (standart model: $model) — kalitni kiriting va \"Saqlash\" ni bosing"],
        JSON_UNESCAPED_UNICODE);
    exit;
}

$t0 = microtime(true);
$r  = im_ai_oddiy_call(
    "Sen IMezon tizimining yordamchisisan. Qisqa javob ber.",
    "Ulanish sinovi. Faqat shu jumlani yoz: Ulanish ishlayapti.",
    4000, 'low', $model
);
$ms = (int)round((microtime(true) - $t0) * 1000);

if (!$r['ok']) {
    echo json_encode([
        'status' => 'error',
        'msg'    => $r['xato'],
        'javob'  => $r['xom'],
        'model'  => $s['model'],
        'ms'     => $ms,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$matn = im_ai_javob_matn($r['javob'], $dl);
list($tin, $tout) = im_ai_javob_usage($r['javob'], $dl);
$u = isset($r['javob']['usage']) ? $r['javob']['usage'] : [];

echo json_encode([
    'status' => 'ok',
    'msg'    => 'Ulanish muvaffaqiyatli — ' . $ms . ' ms',
    'matn'   => trim($matn),
    'model'  => isset($r['javob']['model']) ? $r['javob']['model'] : $s['model'],
    'ms'     => $ms,
    'tokens' => [
        'in'         => $tin,
        'out'        => $tout,
        // Kesh hisobi faqat Anthropic javobida bo'ladi
        'kesh_yozdi' => isset($u['cache_creation_input_tokens']) ? (int)$u['cache_creation_input_tokens'] : 0,
        'kesh_oqidi' => isset($u['cache_read_input_tokens'])     ? (int)$u['cache_read_input_tokens']     : 0,
    ],
], JSON_UNESCAPED_UNICODE);
