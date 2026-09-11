<?php
// IMezon — Qarz to'lov tarixini qaytarish (Admin)
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

$qarz_id = (int)($_GET['qarz_id'] ?? 0);
if (!$qarz_id) im_json('error', 'Qarz ID kerak');

// Qarz asosiy ma'lumoti
$qarz = $db->row(
    "SELECT pq.*, ps.nomi AS ps_nomi, p.faktura_nomer, p.id AS p_id
     FROM im_postavshik_qarz pq
     LEFT JOIN im_postavshiklar ps ON ps.id = pq.postavshik_id
     LEFT JOIN im_partiyalar p     ON p.id  = pq.partiya_id
     WHERE pq.id = $qarz_id"
);
if (!$qarz) im_json('error', 'Qarz topilmadi');

// To'lovlar ro'yxati
$tolovlar = $db->rows(
    "SELECT qt.*, x.ism AS admin_ism
     FROM im_qarz_tolovlar qt
     LEFT JOIN im_xodimlar x ON x.id = qt.admin_id
     WHERE qt.qarz_id = $qarz_id
     ORDER BY qt.sana ASC"
);

// Har bir to'lovdan keyin qoldiqni hisoblash
$boshlangich = (float)$qarz['qarz_summa'];
$qolgan = $boshlangich;
$tarix = [];
foreach ($tolovlar as $t) {
    $qolgan -= (float)$t['summa'];
    $tarix[] = [
        'id'         => $t['id'],
        'summa'      => (float)$t['summa'],
        'summa_fmt'  => number_format($t['summa'], 0, '.', ' '),
        'tolov_turi' => $t['tolov_turi'],
        'tolov_nomi' => im_tt_nomi($t['tolov_turi']),
        'usd_summa'  => (float)$t['usd_summa'],
        'usd_kurs'   => (float)$t['usd_kurs'],
        'izoh'       => $t['izoh'] ?: '',
        'admin'      => $t['admin_ism'] ?: 'Admin',
        'sana'       => $t['sana'],
        'qoldi'      => max(0, $qolgan),
        'qoldi_fmt'  => number_format(max(0, $qolgan), 0, '.', ' '),
    ];
}

im_json('ok', '', [
    'qarz'      => [
        'id'         => $qarz['id'],
        'ps_nomi'    => $qarz['ps_nomi'],
        'faktura'    => $qarz['faktura_nomer'] ?: "Partiya #{$qarz['p_id']}",
        'qarz_summa' => (float)$qarz['qarz_summa'],
        'tolandi'    => (float)$qarz['tolandi'],
        'qoldiq'     => (float)$qarz['qoldiq'],
        'status'     => $qarz['status'],
    ],
    'tolovlar'  => $tarix,
    'jami_tolov'=> count($tarix),
]);
