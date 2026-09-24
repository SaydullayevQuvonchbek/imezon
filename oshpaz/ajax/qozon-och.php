<?php
// ============================================================
//  IMezon — Osh qozoni: OCHISH (AJAX, POST)
//
//  Oshpaz (yoki kassir) kun boshida qozonga solingan REAL xomashyoni
//  kiritadi. Xomashyo filial qoldig'idan bir marta yechiladi, narx
//  muhrlanadi. Tayyor osh haqiqiy chiqish miqdori bilan FIFO ga kiradi
//  va tayyor mahsulot FIFO qatlamlari kuzatiladi.
//
//  POST:
//    mahsulot_id  — qozon_rejim=1 mahsulot
//    moljal       — haqiqiy tayyor chiqish miqdori (majburiy)
//    items        — JSON: [{mahsulot_id, soni, birlik}]  (real kg)
//    force_yangi  — 1 bo'lsa: ochiq qozon bo'lsa ham yana qo'shadi
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../qayta-ishlash/ishlab_lib.php';
im_rol_check(['oshpaz', 'kassir', 'admin']);

$db        = new Cyber();
$filial_id = (int)($_SESSION['im_filial_id'] ?? 0);
$xodim_id  = (int)($_SESSION['im_user_id'] ?? 0);
if (!$filial_id) im_json('error', 'Filial aniqlanmadi');

$mahsulot_id = (int)($_POST['mahsulot_id'] ?? 0);
$moljal      = round((float)($_POST['moljal'] ?? 0), 3);
$force_yangi = (int)($_POST['force_yangi'] ?? 0) === 1;
$items       = json_decode($_POST['items'] ?? '[]', true) ?: [];

if (!is_finite($moljal) || $moljal <= 0) im_json('error', 'Haqiqiy tayyor mahsulot miqdorini moljal maydonida kiriting (0 dan katta)');
if (!$mahsulot_id)  im_json('error', 'Mahsulot tanlanmadi');
if (!is_array($items) || empty($items))  im_json('error', 'Kamida bitta xomashyo qatori kerak');

// Mahsulot qozon rejimidami?
$m = $db->row("SELECT id, nomi, birlik FROM im_mahsulotlar
               WHERE id=$mahsulot_id AND qozon_rejim=1 AND status=1");
if (!$m) im_json('error', 'Bu mahsulot qozon rejimida emas');

$bugun = date('Y-m-d');

// Ochiq qozon bormi?
$ochiq_bor = $db->row(
    "SELECT id FROM im_osh_qozon
     WHERE filial_id=$filial_id AND mahsulot_id=$mahsulot_id
       AND sana='$bugun' AND holat='ochiq' LIMIT 1"
);
if ($ochiq_bor && !$force_yangi) {
    im_json('error', '«' . $m['nomi'] . '» uchun bugun ochiq qozon bor. Avval uni yoping yoki "Qo\'shimcha qozon" tugmasini bosing.', [
        'ochiq_qozon_id' => (int)$ochiq_bor['id'],
    ]);
}

// Xomashyo qatorlarini tozalash
$satrlar = [];
foreach ($items as $it) {
    $mid  = (int)($it['mahsulot_id'] ?? 0);
    $soni = round((float)($it['soni'] ?? 0), 3);
    $bir  = trim((string)($it['birlik'] ?? ''));
    if (!is_finite($soni) || $soni <= 0 || $mid <= 0) im_json('error', 'Xomashyo miqdori noto‘g‘ri');
    // Bitta mahsulot bir necha marta kelsa — yig'amiz
    if (isset($satrlar[$mid])) $satrlar[$mid]['soni'] += $soni;
    else $satrlar[$mid] = ['mahsulot_id' => $mid, 'soni' => $soni, 'birlik' => $bir];
}
if (empty($satrlar)) im_json('error', 'Xomashyo miqdorlari noto\'g\'ri');
// Qulf tartibi: xomashyo id bo'yicha o'sib (loyihaning yagona qoidasi) —
// brauzerdan kelgan tartib ixtiyoriy, parallel amallar bilan deadlock bo'lmasin.
ksort($satrlar, SORT_NUMERIC);

$db->begin();
try {
    // ── QULF TARTIBI: xomashyolar VA tayyor mahsulot — id o'sishi bo'yicha,
    // OLDINDAN. Pastda avval xomashyo yechiladi (im_fifo_take), eng oxirida
    // tayyor mahsulot qatlami yaratiladi (im_fifo_receive) — agar tayyor
    // mahsulot id'si xomashyonikidan kichik bo'lsa, tartib teskari bo'lardi.
    $lock_ids = array_map('intval', array_keys($satrlar));
    $lock_ids[] = (int)$mahsulot_id;
    $lock_ids = array_values(array_unique($lock_ids));
    sort($lock_ids, SORT_NUMERIC);
    foreach ($lock_ids as $lid) im_fifo_lock($db, $filial_id, $lid);

    // Validate against FIFO; the actual take locks and checks stock again.
    foreach ($satrlar as $mid => $s) {
        $product = $db->row("SELECT nomi,birlik FROM im_mahsulotlar WHERE id=$mid AND status=1");
        if (!$product) throw new Exception('Xomashyo topilmadi');
        $satrlar[$mid]['birlik'] = $product['birlik'];
        im_fifo_preview($db, $filial_id, $mid, $s['soni']);
    }

    // 2. Qozon yozuvi
    $moljal_s = $moljal > 0 ? $moljal : 0;
    $qozon_id = $db->insert(
        "INSERT INTO im_osh_qozon (filial_id, mahsulot_id, sana, moljal_porsiya, holat, ochgan_xodim_id)
         VALUES ($filial_id, $mahsulot_id, '$bugun', $moljal_s, 'ochiq', $xodim_id)"
    );
    if (!$qozon_id) throw new Exception('Qozon yozilmadi: ' . $db->error());

    // 3. Xomashyoni FIFO qatlamlaridan yechish + narx muhrlash.
    //    im_fifo_take() qatlamlarni qulflaydi; yetmasa Exception (butun qozon rollback).
    $jami_summa = 0;
    foreach ($satrlar as $s) {
        $mid   = (int)$s['mahsulot_id'];
        $need  = (float)$s['soni'];
        $bir_s = mysqli_real_escape_string($link, mb_substr((string)$s['birlik'], 0, 16, 'UTF-8'));

        $take = im_fifo_take($db, $filial_id, $mid, $need, 'qozon', $qozon_id);
        $narx = $take['unit_cost'];
        $summa = $take['cost'];
        $jami_summa += $summa;

        im_ishlab_query($db, "INSERT INTO im_osh_qozon_items (qozon_id, mahsulot_id, soni, birlik, kelish_narxi, summa)
                VALUES ($qozon_id, $mid, $need, '$bir_s', $narx, $summa)");
    }

    // Actual finished yield gets its own FIFO layer; sales consume this stock.
    im_fifo_receive($db, $filial_id, $mahsulot_id, $moljal, $jami_summa / $moljal, 'qozon', $qozon_id);
    $jami_summa = round($jami_summa, 2);
    im_ishlab_query($db, "UPDATE im_osh_qozon SET xomashyo_summa = $jami_summa WHERE id = $qozon_id");

    im_log('im_osh_qozon', $qozon_id, 'insert', null,
           ['mahsulot_id' => $mahsulot_id, 'moljal' => $moljal, 'xomashyo_summa' => $jami_summa],
           "Osh qozoni ochildi — «{$m['nomi']}», xomashyo " . im_money($jami_summa) . " so'm");

    $db->commit();
    im_json('ok', "Qozon ochildi — xomashyo " . im_money($jami_summa) . " so'm yechildi", [
        'qozon_id'       => $qozon_id,
        'xomashyo_summa' => $jami_summa,
    ]);

} catch (Throwable $e) {
    $db->rollback();
    im_json('error', $e->getMessage());
}
