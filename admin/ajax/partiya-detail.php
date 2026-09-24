<?php
// ============================================================
//  IMezon — Partiya tafsilotlari (modal uchun)
//  GET: partiya_id
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'bosh_kassir', 'sklad']);
$db = new Cyber();

$id = (int)($_GET['partiya_id'] ?? 0);
if (!$id) im_json('error', 'ID kiritilmadi');

// Partiya asosiy ma'lumotlari
$partiya = $db->row(
    "SELECT p.*,
            ps.nomi AS ps_nomi, ps.telefon AS ps_tel,
            f.nomi AS filial_nomi,
            x.ism AS qabul_qildi
     FROM im_partiyalar p
     LEFT JOIN im_postavshiklar ps ON ps.id = p.postavshik_id
     LEFT JOIN im_filiallar f      ON f.id  = p.qabul_filial_id
     LEFT JOIN im_xodimlar x       ON x.id  = p.xodim_id
     WHERE p.id = $id"
);
if (!$partiya) im_json('error', 'Partiya topilmadi');

// Mahsulotlar ro'yxati
$items = $db->rows(
    "SELECT pi.*,
            COALESCE((SELECT SUM(fl.remaining_qty) FROM im_fifo_layers fl WHERE fl.partiya_item_id=pi.id AND fl.location_id=0 AND fl.cancelled=0 AND fl.remaining_qty>0),0) AS sklad_qoldi,
            m.nomi AS mahsulot, m.barcode,
            k.nomi AS kategoriya
     FROM im_partiya_items pi
     LEFT JOIN im_mahsulotlar m    ON m.id = pi.mahsulot_id
     LEFT JOIN im_kategoriyalar k  ON k.id = m.kategoriya_id
     WHERE pi.partiya_id = $id
     ORDER BY pi.id ASC"
);

// Qarz ma'lumotlari
$qarz = $db->row(
    "SELECT * FROM im_postavshik_qarz WHERE partiya_id = $id LIMIT 1"
);

// To'lovlar tarixi
$tolovlar = [];
if ($qarz) {
    $qarz_id = (int)$qarz['id'];
    $tolovlar = $db->rows(
        "SELECT qt.*, x.ism AS admin_ism
         FROM im_qarz_tolovlar qt
         LEFT JOIN im_xodimlar x ON x.id = qt.admin_id
         WHERE qt.qarz_id = $qarz_id
         ORDER BY qt.sana ASC"
    );
}

$tolov_list = [];
foreach ($tolovlar as $t) {
    $tolov_list[] = [
        'sana'       => $t['sana'],
        'turi'       => im_tt_label($t['tolov_turi']),
        'summa'      => (float)$t['summa'],
        'summa_fmt'  => im_money($t['summa']),
        'usd_summa'  => (float)$t['usd_summa'],
        'usd_kurs'   => (float)$t['usd_kurs'],
        'izoh'       => $t['izoh'] ?: '',
        'admin'      => $t['admin_ism'] ?: 'Tizim',
    ];
}

$item_list = [];
foreach ($items as $it) {
    $item_list[] = [
        'mahsulot'     => $it['mahsulot'] ?: "Mahsulot #{$it['mahsulot_id']}",
        'barcode'      => $it['barcode'] ?: '',
        'kategoriya'   => $it['kategoriya'] ?: '—',
        'soni'         => (float)$it['soni'],
        'sklad_qoldi'  => (float)$it['sklad_qoldi'],
        'narx'         => (float)$it['kelish_narxi'],
        'narx_fmt'     => im_money($it['kelish_narxi']),
        'jami'         => (float)($it['soni'] * $it['kelish_narxi']),
        'jami_fmt'     => im_money($it['soni'] * $it['kelish_narxi']),
        'filial'       => $partiya['filial_nomi'] ?: '—',
        'sotish_narxi' => (float)($it['sotish_narxi'] ?? 0),
        'sotish_fmt'   => im_money($it['sotish_narxi'] ?? 0),
    ];
}

im_json('ok', '', [
    'partiya' => [
        'id'           => (int)$partiya['id'],
        'faktura'      => $partiya['faktura_nomer'] ?: "Partiya #{$partiya['id']}",
        'sana'         => $partiya['sana'],
        'ps_nomi'      => $partiya['ps_nomi'] ?: '—',
        'ps_tel'       => $partiya['ps_tel'] ?: '',
        'filial'       => $partiya['filial_nomi'] ?: '—',
        'qabul_qildi'  => $partiya['qabul_qildi'] ?: '—',
        'tolov_turi'   => im_tt_label($partiya['tolov_turi']),
        'jami_summa'   => (float)$partiya['jami_summa'],
        'jami_fmt'     => im_money($partiya['jami_summa']),
        'tolandi'      => $im_rol === 'sklad' ? null : (float)$partiya['tolandi'],
        'tolandi_fmt'  => $im_rol === 'sklad' ? '—' : im_money($partiya['tolandi']),
        'qarz_qoldi'   => $im_rol === 'sklad' ? null : (float)$partiya['qarz_qoldi'],
        'qoldi_fmt'    => $im_rol === 'sklad' ? '—' : im_money($partiya['qarz_qoldi']),
        'holat'        => $partiya['holat'],
        'izoh'         => $partiya['izoh'] ?: '',
    ],
    'items'   => $item_list,
    // Postavshik bilan hisob-kitob (to'lovlar tarixi, qarz qoldig'i) — moliyaviy
    // ma'lumot. Sklad roli bu oynani faqat qabul TARKIBINI ko'rish va yopilgan
    // qabulni bekor qilish uchun ochadi (sklad/hisobot.php), shuning uchun unga
    // moliyaviy blok berilmaydi.
    'tolovlar'=> $im_rol === 'sklad' ? [] : $tolov_list,
    'qarz'    => ($qarz && $im_rol !== 'sklad') ? [
        'id'          => (int)$qarz['id'],
        'qarz_summa'  => (float)$qarz['qarz_summa'],
        'tolandi'     => (float)$qarz['tolandi'],
        'qoldiq'      => (float)$qarz['qoldiq'],
        'muddat'      => $qarz['muddat'] ?: '',
        'status'      => $qarz['status'],
    ] : null,
]);
