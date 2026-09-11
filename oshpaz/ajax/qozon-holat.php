<?php
// ============================================================
//  IMezon — Osh qozoni: joriy holat (AJAX, GET)
//
//  Qaytaradi:
//    - qozon_rejim=1 mahsulotlar (dropdown uchun) + har biri uchun
//      retsept (1 porsiyaga xomashyo — forma oldindan to'ldirish uchun)
//    - bugungi ochiq qozonlar (filial bo'yicha)
//    - bugungi yopilgan qozonlar
//    - har qozonning FIFO qatlamidan sof sotilgan porsiya
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../qozon_lib.php';
im_rol_check(['oshpaz', 'kassir', 'admin']);

$db        = new Cyber();
$filial_id = (int)($_SESSION['im_filial_id'] ?? 0);
if (!$filial_id) im_json('error', 'Filial aniqlanmadi');

$bugun = date('Y-m-d');

// ── qozon rejimidagi mahsulotlar + retsept (1 porsiyaga) ─────
// Bir mahsulotga bir nechta faol retsept bo'lsa eng so'nggisi olinadi
// (aks holda mahsulot dropdown'da takrorlanib chiqardi).
$mahsulotlar = $db->rows(
    "SELECT m.id, m.nomi, m.birlik,
            (SELECT r.id FROM im_retseptlar r
             WHERE r.mahsulot_id = m.id AND r.tur='ishlab_chiqarish' AND r.status=1
             ORDER BY r.id DESC LIMIT 1) AS retsept_id,
            (SELECT r.chiqish_soni FROM im_retseptlar r
             WHERE r.mahsulot_id = m.id AND r.tur='ishlab_chiqarish' AND r.status=1
             ORDER BY r.id DESC LIMIT 1) AS chiqish_soni
     FROM im_mahsulotlar m
     WHERE m.qozon_rejim = 1 AND m.status = 1
     ORDER BY m.nomi"
);
// Retsepti yo'q qozon-mahsulotlarini chiqarib tashlaymiz (forma to'ldirib bo'lmaydi)
$mahsulotlar = array_values(array_filter($mahsulotlar, fn($m) => !empty($m['retsept_id'])));
foreach ($mahsulotlar as &$m) {
    $rid = (int)$m['retsept_id'];
    $chiqish = (float)$m['chiqish_soni'] ?: 1;
    $items = $db->rows(
        "SELECT ri.mahsulot_id, ri.soni, ri.birlik, x.nomi AS mahsulot_nomi,
                COALESCE(fq.soni, 0)         AS qoldiq_bor,
                COALESCE(fq.kelish_narxi, 0) AS kelish_narxi
         FROM im_retsept_items ri
         JOIN im_mahsulotlar x ON x.id = ri.mahsulot_id
         LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id = ri.mahsulot_id AND fq.filial_id = $filial_id
         WHERE ri.retsept_id = $rid
         ORDER BY x.nomi"
    );
    // 1 porsiyaga xomashyo (chiqish_soni ga normalizatsiya)
    foreach ($items as &$it) {
        $it['bir_porsiya']  = round((float)$it['soni'] / $chiqish, 4);
        $it['qoldiq_bor']   = (float)$it['qoldiq_bor'];
        $it['kelish_narxi'] = (float)$it['kelish_narxi'];
        $it['mahsulot_id']  = (int)$it['mahsulot_id'];
        unset($it['soni']);
    }
    unset($it);
    $m['id']          = (int)$m['id'];
    $m['retsept_id']  = $rid;
    $m['xomashyo']    = $items;
    unset($m['chiqish_soni']);
}
unset($m);

// ── Bugungi qozonlar (shu filial) ───────────────────────────
$qozonlar = $db->rows(
    "SELECT q.*, m.nomi AS mahsulot_nomi, m.birlik AS mahsulot_birlik,
            xo.ism AS ochgan_ism, xy.ism AS yopgan_ism,
            (SELECT COALESCE(SUM(fm.qty-fm.reversed_qty),0)
             FROM im_fifo_layers fl
             JOIN im_fifo_movements fm ON fm.layer_id=fl.id
             WHERE fl.location_id=q.filial_id AND fl.mahsulot_id=q.mahsulot_id
               AND fl.source='qozon' AND fl.source_id=q.id AND fl.cancelled=0
               AND fm.source='sotuv' AND fm.kind='take') AS sotildi_qozon
     FROM im_osh_qozon q
     LEFT JOIN im_mahsulotlar m  ON m.id  = q.mahsulot_id
     LEFT JOIN im_xodimlar   xo ON xo.id = q.ochgan_xodim_id
     LEFT JOIN im_xodimlar   xy ON xy.id = q.yopgan_xodim_id
     WHERE q.filial_id = $filial_id AND q.sana = '$bugun'
     ORDER BY q.id DESC"
);

// ── Bugun sotilgan porsiya (mahsulot bo'yicha) ───────────────
$sotildi_map = [];
foreach ($db->rows(
    "SELECT si.mahsulot_id, COALESCE(SUM(si.soni),0) AS jami
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id = si.sotuv_id
     WHERE s.filial_id = $filial_id
       AND DATE(s.sana) = '$bugun'
       AND s.holat = 'aktiv'
     GROUP BY si.mahsulot_id"
) as $r) {
    $sotildi_map[(int)$r['mahsulot_id']] = (float)$r['jami'];
}

$ochiq   = [];
$yopilgan = [];
foreach ($qozonlar as $q) {
    $q['id']             = (int)$q['id'];
    $q['mahsulot_id']    = (int)$q['mahsulot_id'];
    $q['moljal_porsiya'] = (float)$q['moljal_porsiya'];
    $q['xomashyo_summa'] = (float)$q['xomashyo_summa'];
    $q['qoldi_porsiya']  = (float)$q['qoldi_porsiya'];
    $q['sotildi_qozon']   = (float)$q['sotildi_qozon'];
    $q['haqiqiy_porsiya'] = $q['haqiqiy_porsiya'] === null ? null : (float)$q['haqiqiy_porsiya'];
    $q['yakuniy_tannarx'] = $q['yakuniy_tannarx'] === null ? null : (float)$q['yakuniy_tannarx'];
    $q['sotildi_bugun']  = $sotildi_map[$q['mahsulot_id']] ?? 0;
    if ($q['holat'] === 'ochiq') $ochiq[] = $q;
    else                          $yopilgan[] = $q;
}

im_json('ok', '', [
    'filial_id'   => $filial_id,
    'sana'        => $bugun,
    'mahsulotlar' => $mahsulotlar,
    'ochiq'       => $ochiq,
    'yopilgan'    => $yopilgan,
    'sotildi_map' => $sotildi_map,
    'eslatmalar'  => im_qozon_eslatma($db, $filial_id),
]);
