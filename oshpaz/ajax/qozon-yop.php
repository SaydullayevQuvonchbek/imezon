<?php
// ============================================================
//  IMezon — Osh qozoni: YOPISH (AJAX, POST)
//
//  Oshpaz qozon tugagach (yoki kun oxirida) qozonda qolgan real
//  porsiyani kiritadi. Qoldiq isrofga yozilishi yoki keyingi kunga
//  FIFO qoldiq sifatida qoldirilishi mumkin.
//
//  Xomashyoga TEGILMAYDI — u qozon ochilganda allaqachon yechilgan.
//
//  POST:  qozon_id, qoldi (porsiya), izoh (ixtiyoriy)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../qozon_lib.php';
im_rol_check(['oshpaz', 'kassir', 'admin']);

$db        = new Cyber();
$filial_id = (int)($_SESSION['im_filial_id'] ?? 0);
$xodim_id  = (int)($_SESSION['im_user_id'] ?? 0);
if (!$filial_id) im_json('error', 'Filial aniqlanmadi');

$qozon_id = (int)($_POST['qozon_id'] ?? 0);
$qoldi    = max(0, (float)($_POST['qoldi'] ?? 0));
$izoh     = trim((string)($_POST['izoh'] ?? ''));
// 1 = isrof / xodim ovqati (default), 0 = xom marinovka keyingi kunga o'tdi
$isrofmi  = (int)($_POST['qoldi_isrofmi'] ?? 1) === 0 ? 0 : 1;

if (!is_finite($qoldi)) im_json('error', 'Qoldiq noto‘g‘ri');
if (!$qozon_id) im_json('error', 'Qozon ko\'rsatilmadi');

$q = $db->row(
    "SELECT q.*, m.nomi AS mahsulot_nomi
     FROM im_osh_qozon q
     LEFT JOIN im_mahsulotlar m ON m.id = q.mahsulot_id
     WHERE q.id = $qozon_id AND q.filial_id = $filial_id"
);
if (!$q)                        im_json('error', 'Qozon topilmadi');
if ($q['holat'] === 'yopildi')  im_json('error', 'Bu qozon allaqachon yopilgan');

// Aniq xato: qoldiq mo'ljalning 2 barobaridan ko'p bo'lsa — bu terish
// xatosi. Aks holda mayda ortiqcha (rejadan ko'p pishirilgan) o'taveradi.
$moljal = (float)$q['moljal_porsiya'];
if ($moljal > 0 && $qoldi > $moljal * 2) {
    im_json('error', "Qoldiq ($qoldi) mo'ljaldan ($moljal) juda katta — raqamni tekshiring.");
}

$izoh_s = mysqli_real_escape_string($link, mb_substr($izoh, 0, 255, 'UTF-8'));

$db->begin();
try {
    // Sold portions + physical remainder is the actual batch yield. The helper
    // reconciles quantity and seals one final unit cost on the pot/FIFO layer.
    $yakun = im_qozon_yakuniy_tannarx($db, $qozon_id, $filial_id, $qoldi, $isrofmi);
    $ok = $db->q(
        "UPDATE im_osh_qozon
         SET holat='yopildi', qoldi_porsiya=$qoldi, qoldi_isrofmi=$isrofmi, izoh='$izoh_s',
             yopgan_xodim_id=$xodim_id, yopildi_vaqt=NOW()
         WHERE id=$qozon_id AND holat='ochiq'"
    );
    if (!$ok || $db->affected() < 1) throw new Exception('Yopilmadi (holati o\'zgargan bo\'lishi mumkin)');

    im_log('im_osh_qozon', $qozon_id, 'update',
           ['holat' => 'ochiq'],
           ['holat' => 'yopildi', 'qoldi_porsiya' => $qoldi, 'qoldi_isrofmi' => $isrofmi,
            'haqiqiy_porsiya' => $yakun['haqiqiy'], 'yakuniy_tannarx' => $yakun['tannarx']],
           "Osh qozoni yopildi — «{$q['mahsulot_nomi']}», qozonda qoldi: $qoldi porsiya"
           . ($qoldi > 0 ? ($isrofmi ? ' (isrof)' : ' (keyingi kunga)') : ''));

    $db->commit();
    $qoldi_msg = '';
    if ($qoldi > 0) {
        $qoldi_msg = $isrofmi
            ? " — $qoldi porsiya isrofga yozildi"
            : " — $qoldi porsiya keyingi kunga qoldirildi (isrof emas)";
    }
    im_json('ok', 'Qozon yopildi' . $qoldi_msg, [
        'qozon_id'          => $qozon_id,
        'haqiqiy_porsiya'   => $yakun['haqiqiy'],
        'yakuniy_tannarx'   => $yakun['tannarx'],
    ]);

} catch (Throwable $e) {
    $db->rollback();
    im_json('error', $e->getMessage());
}
