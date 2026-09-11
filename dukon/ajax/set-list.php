<?php
// ============================================================
//  IMezon — Dukon: Set-list (POS + ofitsant paneli uchun)
//  GET: Joriy filialning aktiv setlari + mahsulotlar
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir', 'admin', 'sotuvchi']);
$db = new Cyber();

$filial_id = $im_filial_id ?: 1;

// Aktiv setlar (joriy filial uchun)
$setlar = $db->rows(
    "SELECT id, nomi, narxi, rang, tartib
     FROM im_setlar
     WHERE filial_id=$filial_id AND aktiv=1
     ORDER BY tartib ASC, nomi ASC"
);

if (empty($setlar)) {
    im_json('ok', '', ['list' => []]);
}

// Har bir set uchun mahsulotlarni yuklaymiz
$result = [];
foreach ($setlar as $set) {
    $sid = (int)$set['id'];

    $items = $db->rows(
        "SELECT si.mahsulot_id, si.soni, si.tartib,
                m.nomi, m.birlik, m.sotuv_qadami, m.oshpaz_kerak, m.retsept_avto,
                COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0) AS sotuv_narxi,
                COALESCE(fq.soni, 0) AS qoldiq,
                COALESCE(
                  fq.kelish_narxi,
                  (SELECT pi.kelish_narxi FROM im_partiya_items pi
                   WHERE pi.mahsulot_id=m.id AND pi.kelish_narxi>0
                   ORDER BY pi.id DESC LIMIT 1), 0
                ) AS tannarx
         FROM im_set_items si
         JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
         LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
         LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id=m.id AND fq.filial_id=$filial_id
         WHERE si.set_id=$sid
         ORDER BY si.tartib ASC, si.id ASC"
    );

    // Qoldiq yetarlimi tekshirish.
    // Retseptli taom (oshpaz_kerak / retsept_avto) vitrinada TURMAYDI —
    // qoldiq 0 bo'lsa ham set sotilaveradi. Xomashyo mos bosqichda
    // (oshpaz qabuli yoki sotuv) yechiladi, nazorat o'sha yerda.
    // Shu sabab bunday item set mavjudligini bloklamaydi.
    // (Xuddi sotuvchi/ajax/get-products.php dagidek printsip.)
    $available = true;
    foreach ($items as &$item) {
        $retseptli = (int)$item['oshpaz_kerak'] || (int)$item['retsept_avto'];
        if ($retseptli) continue;
        $auto = im_auto_maydalash_holati($db, $filial_id, (int)$item['mahsulot_id']);
        $item['auto_maydalash'] = $auto ? 1 : 0;
        $item['tayyor_qoldiq'] = (float)$item['qoldiq'];
        $item['auto_imkon'] = $auto ? (float)$auto['auto_imkon'] : 0;
        if ($auto) $item['qoldiq'] = (float)$auto['jami_imkon'];
        if ((float)$item['qoldiq'] < (float)$item['soni']) {
            $available = false;
            break;
        }
    }
    unset($item);

    // items ni float ga o'tkazish
    $items_out = [];
    foreach ($items as $it) {
        $retseptli = (int)$it['oshpaz_kerak'] || (int)$it['retsept_avto'];
        $items_out[] = [
            'mahsulot_id'  => (int)$it['mahsulot_id'],
            'nomi'         => $it['nomi'],
            'birlik'       => $it['birlik'],
            'sotuv_qadami' => max(0.001, (float)$it['sotuv_qadami']),
            'soni'         => (float)$it['soni'],
            'sotuv_narxi'  => (float)$it['sotuv_narxi'],
            'tannarx'      => (float)$it['tannarx'],
            // Retseptli item — savatga qo'shishda sanoq cheklovi bo'lmasin
            'qoldiq'       => $retseptli ? 9999 : (float)$it['qoldiq'],
            'auto_maydalash'=> (int)($it['auto_maydalash'] ?? 0),
            'tayyor_qoldiq' => (float)($it['tayyor_qoldiq'] ?? $it['qoldiq']),
            'auto_imkon'    => (float)($it['auto_imkon'] ?? 0),
        ];
    }

    $result[] = [
        'id'        => $sid,
        'nomi'      => $set['nomi'],
        'narxi'     => (float)$set['narxi'],
        'rang'      => $set['rang'] ?: '#e2b96f',
        'items'     => $items_out,
        'available' => $available,
    ];
}

im_json('ok', '', ['list' => $result]);
