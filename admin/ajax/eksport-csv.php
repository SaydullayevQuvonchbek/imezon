<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$type   = $_GET['type']   ?? 'sotuvlar';
$dan    = $_GET['dan']    ?? date('Y-m-01');
$gacha  = $_GET['gacha']  ?? date('Y-m-d');
$kassir = (int)($_GET['kassir'] ?? 0);

// Sana validatsiya
$dan   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dan)   ? $dan   : date('Y-m-01');
$gacha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $gacha) ? $gacha : date('Y-m-d');

// Fayl nomi
$filename = "IMezon_{$type}_{$dan}_{$gacha}.csv";

// CSV header
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: no-cache, no-store');

// UTF-8 BOM (Excel uchun)
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

$k_filter = $kassir ? "AND s.kassir_id=$kassir" : '';
$k_filter2 = $kassir ? "AND kassir_id=$kassir" : '';

switch ($type) {

  // ── 1. SOTUVLAR JURNALI ────────────────────────────────
  case 'sotuvlar':
    fputcsv($out, ['Chek raqami', 'Sana', 'Kassir', 'Mijoz', 'Naqd (so\'m)', im_tt_nomi('karta', true) . ' (so\'m)',
                   im_tt_nomi('bank', true) . ' (so\'m)', 'USD ($)', 'Nasiya (so\'m)', 'Chegirma (so\'m)', 'JAMI (so\'m)'], ';');
    $rows = $db->rows(
        "SELECT s.chek_nomer, s.sana, x.ism AS kassir, m.ism AS mijoz,
                s.naqd_summa, s.karta_summa, s.bank_summa, s.usd_summa, s.nasiya_summa,
                s.chegirma_summa, s.tolov_summa
         FROM im_sotuvlar s
         LEFT JOIN im_xodimlar x ON x.id=s.kassir_id
         LEFT JOIN im_mijozlar m ON m.id=s.mijoz_id
         WHERE DATE(s.sana) BETWEEN '$dan' AND '$gacha' $k_filter
         ORDER BY s.id DESC"
    );
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['chek_nomer'],
            date('d.m.Y H:i', strtotime($r['sana'])),
            $r['kassir'] ?? '-',
            $r['mijoz']  ?? '-',
            number_format((float)$r['naqd_summa'],   0, '.', ''),
            number_format((float)$r['karta_summa'],  0, '.', ''),
            number_format((float)$r['bank_summa'],   0, '.', ''),
            number_format((float)$r['usd_summa'],    2, '.', ''),
            number_format((float)$r['nasiya_summa'], 0, '.', ''),
            number_format((float)$r['chegirma_summa'],0,'.',''),
            number_format((float)$r['tolov_summa'],  0, '.', ''),
        ], ';');
    }
    // Jami qator
    fputcsv($out, [], ';');
    $tot = $db->row(
        "SELECT SUM(naqd_summa) n, SUM(karta_summa) k, SUM(nasiya_summa) ns,
                SUM(chegirma_summa) ch, SUM(tolov_summa) t, COUNT(*) cnt
         FROM im_sotuvlar s
         WHERE DATE(sana) BETWEEN '$dan' AND '$gacha' $k_filter"
    );
    fputcsv($out, ['', "JAMI: {$tot['cnt']} ta sotuv", '', '', $tot['n']??0, $tot['k']??0,
                   '', '', $tot['ns']??0, $tot['ch']??0, $tot['t']??0], ';');
    break;

  // ── 2. SOTUV ITEMLARI ────────────────────────────────────
  case 'sotuv_items':
    fputcsv($out, ['Chek raqami', 'Sana', 'Mahsulot', 'Birlik', 'Soni',
                   'Sotish narxi', 'Chegirma%', 'Chegirma narxi', 'Jami'], ';');
    $rows = $db->rows(
        "SELECT s.chek_nomer, s.sana, m.nomi, m.birlik,
                si.soni, si.sotish_narxi, si.chegirma_foiz, si.chegirma_narxi,
                ROUND(si.chegirma_narxi * si.soni, 0) AS line_total
         FROM im_sotuv_items si
         JOIN im_sotuvlar s ON s.id = si.sotuv_id
         JOIN im_mahsulotlar m ON m.id = si.mahsulot_id
         WHERE DATE(s.sana) BETWEEN '$dan' AND '$gacha' $k_filter
         ORDER BY s.id DESC, si.id ASC"
    );
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['chek_nomer'],
            date('d.m.Y H:i', strtotime($r['sana'])),
            $r['nomi'],
            $r['birlik'],
            $r['soni'],
            $r['sotish_narxi'],
            $r['chegirma_foiz'].'%',
            $r['chegirma_narxi'],
            $r['line_total'],
        ], ';');
    }
    break;

  // ── 3. NASIYALAR ─────────────────────────────────────────
  case 'nasiyalar':
    fputcsv($out, ['Mijoz', 'Telefon', 'Chek', 'Sana', 'Qarz summa',
                   'To\'langan', 'Qoldiq', 'Qaytarish sanasi', 'Holat'], ';');
    $rows = $db->rows(
        "SELECT m.ism, m.telefon, s.chek_nomer, n.yaratilgan,
                n.qarz_summa, n.tolangan, n.qoldiq, n.qaytarish_sana, n.holat
         FROM im_nasiya n
         JOIN im_mijozlar m ON m.id=n.mijoz_id
         LEFT JOIN im_sotuvlar s ON s.id=n.sotuv_id
         WHERE DATE(n.yaratilgan) BETWEEN '$dan' AND '$gacha'
         ORDER BY n.id DESC"
    );
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['ism'],
            $r['telefon'] ?? '',
            $r['chek_nomer'] ?? '',
            date('d.m.Y', strtotime($r['yaratilgan'])),
            $r['qarz_summa'],
            $r['tolangan'],
            $r['qoldiq'],
            date('d.m.Y', strtotime($r['qaytarish_sana'])),
            $r['holat'],
        ], ';');
    }
    break;

  // ── 4. HARAJATLAR ────────────────────────────────────────
  case 'harajatlar':
    fputcsv($out, ['Sana', 'Kategoriya', 'Izoh', 'Summa (so\'m)', 'Kassir'], ';');
    $rows = $db->rows(
        "SELECT h.sana, h.kategoriya, h.izoh, h.summa, x.ism AS kassir
         FROM im_harajatlar h
         LEFT JOIN im_xodimlar x ON x.id=h.xodim_id
         WHERE DATE(h.sana) BETWEEN '$dan' AND '$gacha' $k_filter2
         ORDER BY h.id DESC"
    );
    foreach ($rows as $r) {
        fputcsv($out, [
            date('d.m.Y', strtotime($r['sana'])),
            $r['kategoriya'],
            $r['izoh'] ?? '',
            $r['summa'],
            $r['kassir'] ?? '',
        ], ';');
    }
    $total = $db->val("SELECT SUM(summa) FROM im_harajatlar WHERE DATE(sana) BETWEEN '$dan' AND '$gacha' $k_filter2");
    fputcsv($out, ['', 'JAMI:', '', $total ?? 0, ''], ';');
    break;

  // ── 5. MAHSULOTLAR ───────────────────────────────────────
  case 'mahsulotlar':
    fputcsv($out, ['Nomi', 'Barcode', 'Kategoriya', 'Narx (so\'m)', 'Ulgurji narx',
                   'Ulgurji min', 'Sklad qoldig\'i', 'Birlik', 'Tannarx', 'Status'], ';');
    $rows = $db->rows(
        "SELECT m.nomi, m.barcode, k.nomi AS kat, m.narx, m.ulg_narx, m.ulg_min,
                COALESCE((SELECT SUM(remaining_qty) FROM im_fifo_layers WHERE mahsulot_id=m.id AND location_id=0 AND cancelled=0 AND remaining_qty>0),0) AS qoldiq,
                m.birlik, m.tannarx, m.status
         FROM im_mahsulotlar m
         LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
         ORDER BY k.nomi, m.nomi"
    );
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['nomi'],
            $r['barcode'] ?? '',
            $r['kat'] ?? '',
            $r['narx'],
            $r['ulg_narx'] ?? '',
            $r['ulg_min'] ?? '',
            $r['qoldiq'],
            $r['birlik'],
            $r['tannarx'] ?? '',
            $r['status'] ? 'Aktiv' : 'Arxiv',
        ], ';');
    }
    break;

  // ── 6. MIJOZLAR ──────────────────────────────────────────
  case 'mijozlar':
    fputcsv($out, ['Ism', 'Telefon', 'Toifa', 'Chegirma%', 'Jami xarid',
                   'Nasiya qoldig\'i', 'Manzil', 'Izoh', 'Qo\'shilgan'], ';');
    $rows = $db->rows(
        "SELECT m.ism, m.telefon, t.nomi AS toifa, t.chegirma_foiz,
                COALESCE(m.jami_xarid,0) AS jami_xarid,
                COALESCE(m.nasiya_qoldiq,0) AS nasiya,
                m.manzil, m.izoh, m.yaratilgan
         FROM im_mijozlar m
         LEFT JOIN im_mijoz_toifalari t ON t.id=m.toifa_id
         WHERE m.status=1
         ORDER BY m.ism ASC"
    );
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['ism'],
            $r['telefon'] ?? '',
            $r['toifa']   ?? 'Toifasiz',
            ($r['chegirma_foiz'] ?? 0).'%',
            $r['jami_xarid'],
            $r['nasiya'],
            $r['manzil'] ?? '',
            $r['izoh']   ?? '',
            date('d.m.Y', strtotime($r['yaratilgan'])),
        ], ';');
    }
    break;

  default:
    fputcsv($out, ['Xato', 'Noto\'g\'ri tur: '.$type], ';');
}

fclose($out);
exit;
