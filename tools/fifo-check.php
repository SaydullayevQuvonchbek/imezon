<?php
// ============================================================
//  IMezon — FIFO INVARIANT TEKSHIRUVI  (faqat CLI)
//
//  Ishga tushirish:
//      php tools/fifo-check.php
//  Kuniga bir marta cron'ga qo'yish tavsiya etiladi:
//      0 5 * * *  php /home/imezon/.../tools/fifo-check.php >> /var/log/imezon-fifo.log 2>&1
//
//  Nimani tekshiradi (har (filial, mahsulot) juftligi uchun):
//    1) im_filial_qoldiq.soni + faol rezervlar  ==  SUM(im_fifo_layers.remaining_qty)
//       ya'ni "mavjud + band" = "jismoniy". Farq bo'lsa — qoldiq FIFO'dan
//       tashqarida o'zgartirilgan (qo'lda UPDATE, eski kod yoki xato).
//    2) Manfiy qoldiq (soni < 0 yoki remaining_qty < 0)
//    3) Qatlam yaxlitligi: remaining_qty > initial_qty, yoki bekor qilingan
//       qatlamda remaining_qty > 0
//    4) Harakat yaxlitligi: reversed_qty > qty
//
//  HECH NARSANI O'ZGARTIRMAYDI — faqat o'qiydi va hisobot beradi.
//  Chiqish kodi: 0 = toza, 1 = farq topildi (cron/monitoring uchun).
// ============================================================

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config.php';

$db = new Cyber();
$xato = 0;
$chiziq = str_repeat('=', 64);

echo "$chiziq\n  IMezon — FIFO invariant tekshiruvi\n  " . date('Y-m-d H:i:s') . "\n$chiziq\n";

// ── 1) soni + rezerv == SUM(remaining_qty) ──────────────────
// Faol buyurtmalar: rezerv shu statuslarda ushlab turiladi
// (config.php: im_rezerv / im_rezerv_bekor).
$faol = "'stol_band','oshpazda','pishirilmoqda','tasdiqlandi','qabul'";

$qatorlar = $db->rows(
    "SELECT j.filial_id, j.mahsulot_id, m.nomi,
            COALESCE(fq.soni,0)  AS mavjud,
            COALESCE(rz.band,0)  AS band,
            COALESCE(fl.fizik,0) AS fizik
     FROM (
        SELECT filial_id, mahsulot_id FROM im_filial_qoldiq
        UNION
        SELECT location_id AS filial_id, mahsulot_id FROM im_fifo_layers
         WHERE location_id > 0 AND cancelled=0 AND remaining_qty > 0
     ) j
     LEFT JOIN im_filial_qoldiq fq ON fq.filial_id=j.filial_id AND fq.mahsulot_id=j.mahsulot_id
     LEFT JOIN im_mahsulotlar   m  ON m.id=j.mahsulot_id
     LEFT JOIN (
        SELECT location_id, mahsulot_id, SUM(remaining_qty) AS fizik
          FROM im_fifo_layers WHERE cancelled=0 GROUP BY location_id, mahsulot_id
     ) fl ON fl.location_id=j.filial_id AND fl.mahsulot_id=j.mahsulot_id
     LEFT JOIN (
        SELECT o.filial_id, i.mahsulot_id, SUM(COALESCE(i.rezerv_soni,0)) AS band
          FROM im_sotuvchi_order_item i
          JOIN im_sotuvchi_order o ON o.id=i.order_id
         WHERE o.status IN ($faol)
         GROUP BY o.filial_id, i.mahsulot_id
     ) rz ON rz.filial_id=j.filial_id AND rz.mahsulot_id=j.mahsulot_id
     ORDER BY j.filial_id, j.mahsulot_id"
);

$buzuq = [];
foreach ($qatorlar as $r) {
    $farq = round((float)$r['mavjud'] + (float)$r['band'] - (float)$r['fizik'], 3);
    if (abs($farq) > 0.0005) $buzuq[] = $r + ['farq' => $farq];
}

echo "\n[1] Qoldiq invarianti (mavjud + band == jismoniy)\n";
echo "    Tekshirildi: " . count($qatorlar) . " ta (filial, mahsulot) juftligi\n";
if (!$buzuq) {
    echo "    ✔ Farq yo'q\n";
} else {
    $xato = 1;
    echo "    ✖ " . count($buzuq) . " ta juftlikda FARQ bor:\n";
    printf("      %-7s %-28s %10s %8s %10s %9s\n", 'Filial', 'Mahsulot', 'Mavjud', 'Band', 'Jismoniy', 'Farq');
    foreach (array_slice($buzuq, 0, 50) as $r) {
        printf("      %-7s %-28s %10s %8s %10s %9s\n",
            (int)$r['filial_id'],
            mb_substr((string)($r['nomi'] ?? "#{$r['mahsulot_id']}"), 0, 28),
            rtrim(rtrim(number_format((float)$r['mavjud'], 3, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format((float)$r['band'], 3, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format((float)$r['fizik'], 3, '.', ''), '0'), '.'),
            ($r['farq'] > 0 ? '+' : '') . $r['farq']
        );
    }
    if (count($buzuq) > 50) echo "      ... yana " . (count($buzuq) - 50) . " ta\n";
    echo "\n      Tuzatish: sklad/inventarizatsiya.php orqali sanoq qiling —\n"
       . "      farq FIFO qatlami sifatida rasmiy yoziladi (qo'lda UPDATE QILMANG).\n";
}

// ── 2) Manfiy qoldiq ────────────────────────────────────────
echo "\n[2] Manfiy qoldiq\n";
$manfiy_fq = $db->rows("SELECT filial_id, mahsulot_id, soni FROM im_filial_qoldiq WHERE soni < 0");
$manfiy_fl = $db->rows("SELECT id, location_id, mahsulot_id, remaining_qty FROM im_fifo_layers WHERE remaining_qty < 0");
if (!$manfiy_fq && !$manfiy_fl) {
    echo "    ✔ Yo'q\n";
} else {
    $xato = 1;
    foreach ($manfiy_fq as $r) echo "    ✖ im_filial_qoldiq: filial {$r['filial_id']}, mahsulot {$r['mahsulot_id']} → {$r['soni']}\n";
    foreach ($manfiy_fl as $r) echo "    ✖ im_fifo_layers #{$r['id']}: {$r['remaining_qty']}\n";
}

// ── 3) Qatlam yaxlitligi ────────────────────────────────────
echo "\n[3] Qatlam yaxlitligi\n";
$q3 = $db->rows(
    "SELECT id, location_id, mahsulot_id, initial_qty, remaining_qty, cancelled
     FROM im_fifo_layers
     WHERE remaining_qty > initial_qty + 0.0005
        OR (cancelled = 1 AND remaining_qty > 0.0005)"
);
if (!$q3) {
    echo "    ✔ Toza\n";
} else {
    $xato = 1;
    foreach ($q3 as $r) {
        echo "    ✖ qatlam #{$r['id']} (joy {$r['location_id']}, mahsulot {$r['mahsulot_id']}): "
           . "boshlang'ich {$r['initial_qty']}, qoldi {$r['remaining_qty']}, bekor={$r['cancelled']}\n";
    }
}

// ── 4) Harakat yaxlitligi ───────────────────────────────────
echo "\n[4] Harakat (movement) yaxlitligi\n";
$q4 = $db->rows(
    "SELECT id, layer_id, source, source_id, kind, qty, reversed_qty
     FROM im_fifo_movements
     WHERE kind IN ('take','return','cancel') AND reversed_qty > qty + 0.0005"
);
if (!$q4) {
    echo "    ✔ Toza\n";
} else {
    $xato = 1;
    foreach ($q4 as $r) {
        echo "    ✖ harakat #{$r['id']} ({$r['source']}/{$r['source_id']}, {$r['kind']}): "
           . "sarf {$r['qty']}, qaytarilgan {$r['reversed_qty']}\n";
    }
}

echo "\n$chiziq\n";
echo $xato ? "  NATIJA: FARQ TOPILDI — yuqoridagi ro'yxatni ko'ring\n" : "  NATIJA: hammasi joyida ✔\n";
echo "$chiziq\n";
exit($xato);
