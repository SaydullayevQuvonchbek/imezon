<?php
// ============================================================
//  IMezon — Oshpaz: buyurtmani ovozli o'qish (audio qaytaradi)
// ------------------------------------------------------------
//  GET ?id=<order_id>  →  audio/mpeg
//
//  Matn shu yerda, SERVERDA yasaladi — brauzerdan matn qabul
//  qilinmaydi, aks holda endpoint ochiq TTS proksisiga aylanib
//  API balansini begonalar sarflab yuborardi.
//
//  TTS o'chirilgan yoki xato bo'lsa 204 qaytadi — brauzer o'zining
//  speechSynthesis zaxirasiga o'tadi (oshxona ovozsiz qolmaydi).
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../tts_lib.php';
im_rol_check(['oshpaz', 'admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit; }

function im_tts_yoq($sabab) {
    http_response_code(204);
    header('X-TTS-Sabab: ' . preg_replace('/[^\x20-\x7E]/', ' ', $sabab));
    exit;
}

if (!im_tts_ishlaydi()) im_tts_yoq('TTS ochirilgan');

$db  = new Cyber();
$res = im_tts_order($db, $id, (int)$_SESSION['im_filial_id']);

if (isset($res['xato'])) {
    // Xatoni logga yozamiz — admin sozlamalardagi "Sinash" tugmasi
    // orqali batafsil sababni ko'radi.
    error_log('[IMezon TTS] order#' . $id . ': ' . $res['xato']);
    im_tts_yoq($res['xato']);
}

$yol = $res['yol'];
header('Content-Type: ' . im_tts_mime($yol));
header('Content-Length: ' . filesize($yol));
header('Cache-Control: private, max-age=86400');
header('X-TTS-Kesh: ' . (!empty($res['kesh']) ? 'hit' : 'miss'));
readfile($yol);
