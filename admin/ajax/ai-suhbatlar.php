<?php
// ============================================================
//  IMezon — AI Yordamchi: suhbatlar paneli (ro'yxat / ochish / o'chirish)
// ------------------------------------------------------------
//  Barcha amallar POST (ximoya.php CSRF tekshiradi). Foydalanuvchi
//  faqat O'Z suhbatlari bilan ishlaydi — xodim_id sharti helperlarda.
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../ai_lib.php';
im_rol_check(['admin']);

header('Content-Type: application/json; charset=utf-8');
$db   = new Cyber();
$amal = $_POST['amal'] ?? 'royxat';

if ($amal === 'royxat') {
    $out = array_map(function ($s) {
        return [
            'id'       => $s['id'],
            'sarlavha' => $s['sarlavha'] !== '' ? $s['sarlavha'] : 'Suhbat',
            'soni'     => (int)$s['soni'],
            'model'    => $s['model'],
            'vaqt'     => $s['updated_at'],
        ];
    }, im_ai_suhbatlar($db, $im_user_id, 60));
    echo json_encode(['status' => 'ok', 'suhbatlar' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($amal === 'ochish') {
    $s = im_ai_suhbat_ol($db, $_POST['id'] ?? '', $im_user_id);
    if (!$s) {
        echo json_encode(['status' => 'error', 'msg' => 'Suhbat topilmadi'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'status'   => 'ok',
        'id'       => $s['id'],
        'sarlavha' => $s['sarlavha'],
        'model'    => $s['model'],
        'effort'   => $s['effort'],
        'xabarlar' => $s['xabarlar'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($amal === 'ochir') {
    im_ai_suhbat_ochir($db, $_POST['id'] ?? '', $im_user_id);
    echo json_encode(['status' => 'ok', 'msg' => 'Suhbat o\'chirildi'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Noma\'lum amal'], JSON_UNESCAPED_UNICODE);
