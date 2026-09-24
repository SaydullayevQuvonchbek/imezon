<?php
// ============================================================
//  IMezon — Qoldiq tekshirish (AJAX, real-time)
//  GET: ?retsept_id=N&soni=N&filial_id=N
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'kassir', 'sklad', 'oshpaz']);

$db = new Cyber();
$retsept_id = (int)($_GET['retsept_id'] ?? 0);
$soni       = max(0.001, (float)($_GET['soni'] ?? 1));
$filial_id  = (isset($_GET['filial_id']) && $_GET['filial_id'] !== '') ? (int)$_GET['filial_id'] : null;
if ($im_rol === 'oshpaz') $filial_id = (int)$im_filial_id;

if (!$retsept_id) im_json('error', 'Retsept tanlanmadi');

$r = $db->row("SELECT r.*, m.nomi AS mahsulot_nomi, m.birlik AS mahsulot_birlik
               FROM im_retseptlar r
               LEFT JOIN im_mahsulotlar m ON m.id=r.mahsulot_id
               WHERE r.id=$retsept_id AND r.status=1");
if (!$r) im_json('error', 'Retsept topilmadi');
if ($im_rol === 'oshpaz' && $r['tur'] !== 'maydalash') im_json('error', 'Ruxsat yo‘q');

// Qoldiq DOIM FIFO qatlamlaridan (im_fifo_layers) o'qiladi: filial bo'lsa
// im_filial_qoldiq.soni (mavjud = jismoniy - rezerv), aks holda ombor qatlamlari.
$result        = [];
$all_ok        = true;
$chiqish_info  = null;

// ── ISHLAB CHIQARISH: xomashyolarni tekshirish ───────────────
if ($r['tur'] === 'ishlab_chiqarish') {
    $items = $db->rows("SELECT ri.*, m.nomi AS mahsulot_nomi, m.birlik
                        FROM im_retsept_items ri
                        LEFT JOIN im_mahsulotlar m ON m.id=ri.mahsulot_id
                        WHERE ri.retsept_id=$retsept_id");

    foreach ($items as $it) {
        $mah_id  = (int)$it['mahsulot_id'];
        $kerakli = round($it['soni'] * $soni, 3);

        if ($filial_id) {
            // Filial: im_filial_qoldiq.soni dan tekshirish
            $mavjud = (float)$db->val(
                "SELECT COALESCE(soni,0) FROM im_filial_qoldiq WHERE filial_id=$filial_id AND mahsulot_id=$mah_id"
            );
        } else {
            // Ombor: FIFO qatlamlari (location_id=0)
            $mavjud = (float)$db->val(
                "SELECT COALESCE(SUM(remaining_qty),0) FROM im_fifo_layers WHERE mahsulot_id=$mah_id AND location_id=0 AND cancelled=0 AND remaining_qty>0"
            );
        }

        $yetarli = $mavjud >= $kerakli;
        if (!$yetarli) $all_ok = false;
        $result[] = [
            'mahsulot_id'   => $mah_id,
            'mahsulot_nomi' => $it['mahsulot_nomi'],
            'kerakli'       => $kerakli,
            'mavjud'        => $mavjud,
            'birlik'        => $it['birlik'],
            'yetarli'       => $yetarli,
        ];
    }

    // Chiqish info
    $chiqish_info = [
        'soni'         => round($r['chiqish_soni'] * $soni, 3),
        'birlik'       => $r['birlik'],
        'mahsulot_nomi'=> $r['mahsulot_nomi'],
    ];

// ── MAYDALASH: FAQAT asosiy mahsulotni tekshirish ────────────
} else {
    // Maydalashda retsept itemlari = chiqish mahsulotlari
    // Tekshirilishi kerak: asosiy mahsulot ($r['mahsulot_id']) qancha soni bor
    $mah_id  = (int)$r['mahsulot_id'];
    // Maydalashda: 1 marta = 1 ta asosiy mahsulot sarflanadi (chiqish_soni maydoni)
    $kerakli = round($r['chiqish_soni'] * $soni, 3);

    if ($filial_id) {
        $mavjud = (float)$db->val(
            "SELECT COALESCE(soni,0) FROM im_filial_qoldiq WHERE filial_id=$filial_id AND mahsulot_id=$mah_id"
        );
    } else {
        $mavjud = (float)$db->val(
            "SELECT COALESCE(SUM(remaining_qty),0) FROM im_fifo_layers WHERE mahsulot_id=$mah_id AND location_id=0 AND cancelled=0 AND remaining_qty>0"
        );
    }
    $yetarli = $mavjud >= $kerakli;
    if (!$yetarli) $all_ok = false;

    $result[] = [
        'mahsulot_id'   => $mah_id,
        'mahsulot_nomi' => $r['mahsulot_nomi'] . ' (asosiy)',
        'kerakli'       => $kerakli,
        'mavjud'        => $mavjud,
        'birlik'        => $r['mahsulot_birlik'] ?: $r['birlik'],
        'yetarli'       => $yetarli,
    ];

    // Qanday mahsulotlar hosil bo'ladi
    $chiqish_list = $db->rows("SELECT ri.mahsulot_id, ri.soni, ri.birlik, m.nomi AS mahsulot_nomi
                               FROM im_retsept_items ri
                               LEFT JOIN im_mahsulotlar m ON m.id=ri.mahsulot_id
                               WHERE ri.retsept_id=$retsept_id");
    $chiqish_text = array_map(function($c) use ($soni) {
        return round($c['soni'] * $soni, 3) . ' ' . $c['birlik'] . ' ' . $c['mahsulot_nomi'];
    }, $chiqish_list);

    $chiqish_info = [
        'soni'         => $soni,
        'birlik'       => $r['mahsulot_birlik'] ?: 'dona',
        'mahsulot_nomi'=> $r['mahsulot_nomi'],
        'chiqish_list' => $chiqish_list,
        'chiqish_text' => implode(', ', $chiqish_text),
    ];
}

im_json('ok', '', [
    'items'        => $result,
    'all_ok'       => $all_ok,
    'chiqish_info' => $chiqish_info,
    'tur'          => $r['tur'],
]);
