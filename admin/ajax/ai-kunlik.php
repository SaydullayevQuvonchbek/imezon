<?php
// ============================================================
//  IMezon — Kunlik AI xulosasi (dashboard kartasi)
// ------------------------------------------------------------
//  Xulosa kuniga BIR marta yasaladi va im_ai_hisobot da yotadi.
//  Dashboard har ochilganda shu yerga uradi, lekin API'ga
//  chiqmaydi — keshdagisini qaytaradi. "Yangilash" bosilgandagina
//  qayta yasaladi.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../ai_lib.php';
im_rol_check(['admin']);

header('Content-Type: application/json; charset=utf-8');
$db = new Cyber();

$sana     = isset($_POST['sana']) ? $_POST['sana'] : (isset($_GET['sana']) ? $_GET['sana'] : date('Y-m-d'));
$fil      = (int)($_POST['filial_id'] ?? $_GET['filial_id'] ?? 0);
$majburiy = !empty($_POST['yangila']);

// Kesh so'ralayotgan bo'lsa (sahifa ochilishi) va kalit yo'q bo'lsa —
// jim qaytamiz. Dashboard xato ko'rsatib turishi shart emas.
if (!im_ai_sozlangan()) {
    echo json_encode(['status' => 'off', 'msg' => 'AI sozlanmagan'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Faqat keshdagisini so'rash — sahifa ochilganda pul ketmasin
if (!$majburiy) {
    $sana_s = preg_match('/^\d{4}-\d{2}-\d{2}$/', $sana) ? $sana : date('Y-m-d');
    $bor = $db->row("SELECT matn, created_at FROM im_ai_hisobot
                     WHERE tur='kunlik' AND sana='$sana_s' AND filial_id=" . (int)$fil . " LIMIT 1");
    if (!$bor || $bor['matn'] === null || $bor['matn'] === '') {
        echo json_encode(['status' => 'bosh', 'msg' => 'Xulosa hali yasalmagan',
                          'sana' => $sana_s], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['status' => 'ok', 'matn' => $bor['matn'], 'keshdan' => true,
                      'sana' => $sana_s, 'vaqt' => $bor['created_at']], JSON_UNESCAPED_UNICODE);
    exit;
}

$r = im_ai_kunlik_xulosa($db, $sana, $fil, true);

if (!$r['ok']) {
    echo json_encode(['status' => 'error', 'msg' => $r['xato']], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status'  => 'ok',
    'matn'    => $r['matn'],
    'keshdan' => false,
    'sana'    => $r['sana'],
    'tokens'  => ['in' => $r['tokens_in'], 'out' => $r['tokens_out']],
], JSON_UNESCAPED_UNICODE);
