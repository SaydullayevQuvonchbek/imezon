<?php
// POS dan real-time voucher tekshirish
// GET: ?kod=SALE20&summa=600000
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
// Rol tekshiruvi yetishmayotgan yagona endpoint edi: kirgan har qanday
// xodim (oshpaz, ofitsant) voucher kodlarini bittalab sinab, amal
// qiluvchilarini topib olishi mumkin edi.
im_rol_check(['admin', 'kassir']);
$db = new Cyber();

$kod   = strtoupper(mysqli_real_escape_string($link, trim($_GET['kod'] ?? '')));
$summa = (float)($_GET['summa'] ?? 0);
$today = date('Y-m-d');

if (!$kod) im_json('error', 'Kod kiritilmagan');

$v = $db->row("SELECT * FROM im_voucher WHERE kod='$kod' AND status='aktiv'");

if (!$v) im_json('error', "Voucher topilmadi yoki nofaol: $kod");

// Muddatni tekshirish
if ($v['tugash'] && $v['tugash'] < $today)
    im_json('error', "Voucher muddati tugagan ({$v['tugash']})");
if ($v['boshlanish'] && $v['boshlanish'] > $today)
    im_json('error', "Voucher hali faol emas (boshlanish: {$v['boshlanish']})");

// Sonini tekshirish
$qoldi = $v['umumiy_soni'] - $v['ishlatilgan'];
if ($v['umumiy_soni'] > 0 && $qoldi <= 0)
    im_json('error', "Voucher limitiga yetildi ($v[ishlatilgan]/$v[umumiy_soni])");

// Minimal summa tekshirish
if ($v['min_summa'] > 0 && $summa < $v['min_summa'])
    im_json('error', sprintf(
        "Voucher uchun minimal xarid: %s so'm. Hozir: %s so'm",
        number_format($v['min_summa'], 0, '.', ' '),
        number_format($summa, 0, '.', ' ')
    ));

// Chegirmani hisoblash
$chegirma = 0;
if ($v['tur'] === 'foiz') {
    $chegirma = $summa * ($v['qiymat'] / 100);
    if ($v['max_chegirma'] > 0 && $chegirma > $v['max_chegirma'])
        $chegirma = $v['max_chegirma'];
} else {
    $chegirma = $v['qiymat'];
    if ($chegirma > $summa) $chegirma = $summa; // summadan ko'p bo'lmasin
}

$chegirma = round($chegirma);
$yangi_summa = $summa - $chegirma;

im_json('ok', '', [
    'voucher_id'   => $v['id'],
    'kod'          => $v['kod'],
    'tur'          => $v['tur'],
    'qiymat'       => $v['qiymat'],
    'max_chegirma' => $v['max_chegirma'],
    'chegirma'     => $chegirma,
    'yangi_summa'  => $yangi_summa,
    'qoldi'        => $qoldi,
    'min_summa'    => $v['min_summa'],
    'xabar'        => sprintf('%s chegirma: -%s so\'m',
        $v['tur']==='foiz' ? $v['qiymat'].'%' : number_format($v['qiymat'],0,'.',' ').' so\'m',
        number_format($chegirma, 0, '.', ' ')
    ),
]);
