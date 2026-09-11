<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$dan    = $_GET['dan']    ?? date('Y-m-01');
$gacha  = $_GET['gacha']  ?? date('Y-m-d');
$kassir = (int)($_GET['kassir'] ?? 0);

$dan   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dan)   ? $dan   : date('Y-m-01');
$gacha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $gacha) ? $gacha : date('Y-m-d');
$kf    = $kassir ? "AND kassir_id=$kassir" : '';

// Asosiy statistika
$stats = $db->row(
    "SELECT COUNT(*) AS sotuv_soni,
            COALESCE(SUM(tolov_summa),0)   AS jami_summa,
            COALESCE(SUM(chegirma_summa),0) AS chegirma,
            COALESCE(SUM(naqd_summa),0)    AS naqd,
            COALESCE(SUM(karta_summa),0)   AS karta,
            COALESCE(SUM(bank_summa),0)    AS bank,
            COALESCE(SUM(nasiya_summa),0)  AS nasiya,
            COALESCE(SUM(usd_summa*usd_kurs),0) AS usd_som
     FROM im_sotuvlar
     WHERE DATE(sana) BETWEEN '$dan' AND '$gacha' $kf"
);

// Harajatlar
$harajat = $db->val(
    "SELECT COALESCE(SUM(summa),0) FROM im_harajatlar
     WHERE DATE(sana) BETWEEN '$dan' AND '$gacha'" . ($kassir ? " AND xodim_id=$kassir" : '')
) ?? 0;

// Top mahsulotlar
$top = $db->rows(
    "SELECT m.nomi, SUM(si.soni) AS jami_soni, SUM(si.chegirma_narxi*si.soni) AS jami_summa
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id=si.sotuv_id
     JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
     WHERE DATE(s.sana) BETWEEN '$dan' AND '$gacha' $kf
     GROUP BY m.id
     ORDER BY jami_summa DESC
     LIMIT 5"
);

im_json('ok', '', array_merge($stats ?: [], [
    'harajat'        => $harajat,
    'top_mahsulotlar'=> $top,
]));
