<?php
// Kategoriya bo'yicha mahsulotlar (POS uchun)
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir']);
$db = new Cyber();

$kat = (int) ($_GET['kat'] ?? 0);
$q = trim($_GET['q'] ?? '');
$today = date('Y-m-d');

$where = "m.status=1 AND m.sotiladi=1";
if ($kat)
    $where .= " AND m.kategoriya_id=$kat";
if ($q) {
    $qs = mysqli_real_escape_string($link, $q);
    $where .= " AND (m.nomi LIKE '%$qs%' OR m.barcode LIKE '%$qs%')";
}

$filial_id = $im_filial_id ?: 1;

$rows = $db->rows(
    "SELECT m.id, m.nomi, m.barcode, m.birlik, m.sotuv_qadami,
            m.kategoriya_id, m.rasm, m.oshpaz_kerak, m.retsept_avto,
            k.nomi AS kat_nomi,
            COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0) AS narx,
            COALESCE(nu.min_soni,0)    AS ulg_min,
            COALESCE(nu.ulgurji_narxi,0) AS ulg_narx,
            CASE WHEN COALESCE(m.retsept_avto,0)=1
                 THEN 9999 ELSE COALESCE(fq.soni,0) END AS qoldiq,
            COALESCE(
              (SELECT SUM(pi.remaining_qty*pi.unit_cost)/NULLIF(SUM(pi.remaining_qty),0) FROM im_fifo_layers pi
               WHERE pi.mahsulot_id=m.id AND pi.location_id=$filial_id AND pi.cancelled=0 AND pi.remaining_qty>0),0
            ) AS tannarx
     FROM im_mahsulotlar m
     LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
     LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
     LEFT JOIN im_narx_ulgurji nu ON nu.mahsulot_id=m.id AND nu.aktiv=1
     LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id=m.id AND fq.filial_id=$filial_id
     WHERE $where
     ORDER BY m.nomi ASC LIMIT 60"
);

// Kampaniya chegirma
foreach ($rows as &$row) {
    $mid = (int) $row['id'];
    $kat_id = (int) $row['kategoriya_id'];
    $row['imkon'] = null;
    $auto = im_auto_maydalash_holati($db, $filial_id, $mid);
    $row['auto_maydalash'] = $auto ? 1 : 0;
    $row['tayyor_qoldiq']  = (float)$row['qoldiq'];
    $row['auto_imkon']     = $auto ? (float)$auto['auto_imkon'] : 0;
    if ($auto) $row['qoldiq'] = (float)$auto['jami_imkon'];

    // Choy/kofe kabi retsept_avto mahsulot tayyor qoldiqda turmaydi.
    // POS uni "TUGADI" demasligi kerak; xomashyo imkoniyati faqat
    // kassirga ma'lumot sifatida ko'rsatiladi, yakuniy nazorat sotuvda.
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

    $row['kampaniya_chegirma'] = (float) $db->val(
        "SELECT COALESCE(MAX(chegirma_foiz),0) FROM im_chegirmalar
         WHERE aktiv=1 AND (mahsulot_id=$mid OR (kategoriya_id>0 AND kategoriya_id=$kat_id))
         AND (bosh_sana IS NULL OR bosh_sana<='$today')
         AND (tug_sana IS NULL OR tug_sana>='$today')"
    ) ?: 0;
}
unset($row);

im_json('ok', '', ['list' => $rows]);
