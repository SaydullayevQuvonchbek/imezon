<?php
// ============================================================
//  IMezon — AI Yordamchi: OpenRouter model ro'yxati
// ------------------------------------------------------------
//  Sozlamalar sahifasidagi "OpenRouter modellari" tugmasi shu
//  yerga uradi. openrouter.ai/api/v1/models — ommaviy GET, kalit
//  shart emas. Faqat kerakli maydonlar qaytariladi.
//  `tool` = model bizning vosita chaqiruvimizni qo'llaydimi
//  (qo'llamasa agent ishlamaydi — ro'yxatda belgilab qo'yamiz).
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../ai_lib.php';
im_rol_check(['admin']);

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('curl_init')) {
    echo json_encode(['status' => 'error', 'msg' => 'PHP cURL yoqilmagan'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ch = curl_init('https://openrouter.ai/api/v1/models');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => ['accept: application/json'],
]);
$body = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false || $code < 200 || $code >= 300) {
    echo json_encode(['status' => 'error',
        'msg' => 'Ro\'yxat kelmadi' . ($err ? ": $err" : " (HTTP $code)")], JSON_UNESCAPED_UNICODE);
    exit;
}

$j    = json_decode($body, true);
$data = (is_array($j) && isset($j['data']) && is_array($j['data'])) ? $j['data'] : [];
if (!$data) {
    echo json_encode(['status' => 'error', 'msg' => 'Ro\'yxat bo\'sh yoki tanib bo\'lmadi'], JSON_UNESCAPED_UNICODE);
    exit;
}

$modellar = [];
foreach ($data as $m) {
    if (empty($m['id'])) continue;
    $p   = isset($m['pricing']) && is_array($m['pricing']) ? $m['pricing'] : [];
    $sp  = isset($m['supported_parameters']) && is_array($m['supported_parameters']) ? $m['supported_parameters'] : [];
    $modellar[] = [
        'id'       => $m['id'],
        'nomi'     => isset($m['name']) ? $m['name'] : $m['id'],
        'kirish'   => isset($p['prompt'])     ? round((float)$p['prompt']     * 1000000, 2) : null,  // $ / 1M token
        'chiqish'  => isset($p['completion']) ? round((float)$p['completion'] * 1000000, 2) : null,
        'kontekst' => isset($m['context_length']) ? (int)$m['context_length'] : null,
        'tool'     => in_array('tools', $sp, true),
    ];
}

// Vosita chaqiruvni qo'llaydiganlar tepada, so'ng nom bo'yicha
usort($modellar, function ($a, $b) {
    if ($a['tool'] !== $b['tool']) return $a['tool'] ? -1 : 1;
    return strcasecmp($a['nomi'], $b['nomi']);
});

echo json_encode(['status' => 'ok', 'soni' => count($modellar), 'modellar' => $modellar],
                 JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
