<?php
// ============================================================
//  IMezon — TTS sozlamasini sinash (diagnostika)
// ------------------------------------------------------------
//  Admin sozlamalar sahifasidagi "Sinab ko'rish" tugmasi shu
//  yerga uradi. Muvaffaqiyatli bo'lsa base64 audio, aks holda
//  provayderning XOM javobini qaytaradi — kalitni ulashda
//  nima noto'g'ri ketganini darhol ko'rsatadi.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../tts_lib.php';
im_rol_check(['admin']);

header('Content-Type: application/json; charset=utf-8');

// Apostrofli so'zlar TTS'ni ko'p sindiradi — sinov matniga ataylab
// eng qiyinlari kiritilgan.
$matn = isset($_POST['matn']) && trim($_POST['matn']) !== ''
    ? trim($_POST['matn'])
    : 'Yangi buyurtma. Sotuv o\'n ikki. Lag\'mon ikki ta, sho\'rva bir ta, somsa uch ta. Qadoqlansin.';

// DIQQAT: "Ovozli o'qish yoqilgan"mi — bu yerda tekshirilmaydi.
// Sinov aynan yoqishdan OLDIN kerak bo'ladi.
$s = im_tts_soz();
if ($s['provider'] === 'off') {
    echo json_encode(['status' => 'error', 'msg' => 'Provayder tanlanmagan'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($s['key'] === '') {
    echo json_encode(['status' => 'error',
        'msg' => 'API kalit hali saqlanmagan — kalitni maydonga kiriting va "Saqlash" ni bosing'],
        JSON_UNESCAPED_UNICODE);
    exit;
}

// Sinovda kesh chetlab o'tiladi — haqiqiy API javobi ko'rinsin
$t0  = microtime(true);
$res = im_tts_yasa($matn, false);
$ms  = round((microtime(true) - $t0) * 1000);

if (isset($res['xato'])) {
    echo json_encode([
        'status' => 'error',
        'msg'    => $res['xato'],
        'javob'  => isset($res['javob']) ? $res['javob'] : '',
        'type'   => isset($res['type']) ? $res['type'] : '',
        'ms'     => $ms,
        'matn'   => $matn,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'msg'    => 'Muvaffaqiyatli — ' . $ms . ' ms, ' . round(filesize($res['yol']) / 1024) . ' KB',
    'matn'   => $matn,
    'ms'     => $ms,
    'audio'  => 'data:' . im_tts_mime($res['yol']) . ';base64,'
                . base64_encode(file_get_contents($res['yol'])),
], JSON_UNESCAPED_UNICODE);
