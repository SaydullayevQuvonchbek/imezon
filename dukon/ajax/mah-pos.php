<?php
// Mahsulot POS qidirish: barcode yoki nom, narx + qoldiq + ulgurji
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../fifo_reports.php';
im_rol_check(['kassir']);
$db = new Cyber();

$q  = trim($_GET['q'] ?? '');
$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $sql_where = "m.id=$id";
} elseif ($q) {
    $qs = mysqli_real_escape_string($link, $q);
    $sql_where = "(m.nomi LIKE '%$qs%' OR m.barcode='$qs')";
} else {
    im_json('error', 'Qidiruv matni kerak');
}

$filial_id = $im_filial_id ?: 1;

$rows = $db->rows(
    "SELECT m.id, m.nomi, m.barcode, m.birlik, m.sotuv_qadami, m.rasm,
            m.oshpaz_kerak, m.retsept_avto,
            k.nomi AS kat_nomi,
            COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0) AS narx,
            COALESCE(nu.min_soni,0)    AS ulg_min,
            COALESCE(nu.ulgurji_narxi,0) AS ulg_narx,
            CASE WHEN COALESCE(m.retsept_avto,0)=1
                 THEN 9999 ELSE COALESCE(fq.soni,0) END AS qoldiq,
            " . im_fifo_report_unit_cost_sql((int)$filial_id, 'm.id') . " AS tannarx
     FROM im_mahsulotlar m
     LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
     LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
     LEFT JOIN im_narx_ulgurji nu ON nu.mahsulot_id=m.id AND nu.aktiv=1
     LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id=m.id AND fq.filial_id=$filial_id
     WHERE m.status=1 AND m.sotiladi=1 AND $sql_where
     ORDER BY m.nomi ASC LIMIT 20"
);

// Chegirma kampaniyalarini tekshirish (bugungi aktiv)
$today = date('Y-m-d');
foreach ($rows as &$row) {
    $mid = (int)$row['id'];
    $row['imkon'] = null;
    $auto = im_auto_maydalash_holati($db, $filial_id, $mid);
    $row['auto_maydalash'] = $auto ? 1 : 0;
    $row['tayyor_qoldiq']  = (float)$row['qoldiq'];
    $row['auto_imkon']     = $auto ? (float)$auto['auto_imkon'] : 0;
    if ($auto) $row['qoldiq'] = (float)$auto['jami_imkon'];

    // Retsept avtomatik bajariladigan mahsulotning o'z tayyor qoldig'i
    // bo'lmaydi. Mavjud porsiya xomashyo qoldig'idan hisoblanadi.
    if ((int)$row['retsept_avto'] === 1) {
        $retsept = $db->row(
            "SELECT id, chiqish_soni FROM im_retseptlar
             WHERE mahsulot_id=$mid AND tur='ishlab_chiqarish' AND status=1
             ORDER BY id DESC LIMIT 1"
        );
        if ($retsept) {
            $chiqish = max(0.001, (float)$retsept['chiqish_soni']);
            $items = $db->rows(
                "SELECT ri.soni, COALESCE(fq2.soni,0) AS mavjud
                 FROM im_retsept_items ri
                 LEFT JOIN im_filial_qoldiq fq2
                   ON fq2.mahsulot_id=ri.mahsulot_id AND fq2.filial_id=$filial_id
                 WHERE ri.retsept_id=" . (int)$retsept['id']
            );
            $marta = null;
            foreach ($items as $it) {
                $kerak = (float)$it['soni'];
                if ($kerak <= 0) continue;
                $nisbat = (float)$it['mavjud'] / $kerak;
                $marta = $marta === null ? $nisbat : min($marta, $nisbat);
            }
            if ($marta !== null) $row['imkon'] = (int)floor($marta * $chiqish);
        }
    }

    $ch = $db->row(
        "SELECT GREATEST(COALESCE(
            (SELECT chegirma_foiz FROM im_chegirmalar
             WHERE aktiv=1 AND mahsulot_id=$mid
             AND (bosh_sana IS NULL OR bosh_sana<='$today')
             AND (tug_sana IS NULL OR tug_sana>='$today')
             LIMIT 1), 0),
            COALESCE(
            (SELECT ch2.chegirma_foiz FROM im_chegirmalar ch2
             JOIN im_mahsulotlar m2 ON m2.kategoriya_id=ch2.kategoriya_id
             WHERE ch2.aktiv=1 AND m2.id=$mid
             AND (ch2.bosh_sana IS NULL OR ch2.bosh_sana<='$today')
             AND (ch2.tug_sana IS NULL OR ch2.tug_sana>='$today')
             LIMIT 1), 0)
        ) AS chegirma_foiz"
    );
    $row['kampaniya_chegirma'] = $ch ? (float)$ch['chegirma_foiz'] : 0;
}
unset($row);

im_json('ok', '', ['list' => $rows]);
