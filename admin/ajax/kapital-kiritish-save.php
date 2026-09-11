<?php
// ============================================================
//  IMezon — Kapital Kiritish AJAX Handler
//  POST: summa, tolov_turi, izoh, sana, maqsad
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$summa  = (float)($_POST['summa']     ?? 0);
$tt     = mysqli_real_escape_string($link, $_POST['tolov_turi'] ?? 'naqd');
$izoh   = mysqli_real_escape_string($link, trim($_POST['izoh']  ?? ''));
$sana_d = mysqli_real_escape_string($link, $_POST['sana']       ?? date('Y-m-d'));
$maqsad = mysqli_real_escape_string($link, $_POST['maqsad']     ?? 'boshqa');

if ($summa <= 0) im_json('error', 'Summa 0 dan katta bo\'lishi kerak');
if (!in_array($tt, ['naqd','karta','bank','usd'])) im_json('error', "To'lov turi noto'g'ri");
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sana_d)) $sana_d = date('Y-m-d');

// Bugun bo'lsa NOW() (timezone to'g'ri), boshqa kun bo'lsa sana + server vaqti
$sana_bugun = ($sana_d === date('Y-m-d'));
$sana_sql   = $sana_bugun ? 'NOW()' : "'" . $sana_d . ' ' . date('H:i:s') . "'";

$usd_kurs = (float)im_usd_kurs();
if ($tt === 'usd') {
    $summa_usd = $summa;
    $summa_som = $summa * $usd_kurs;
    $label_currency = "USD";
} else {
    $summa_som = $summa;
    $summa_usd = $usd_kurs > 0 ? $summa / $usd_kurs : 0;
    $label_currency = "so'm";
}

// YAGONA BIZNES KASSASI
$kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=0 LIMIT 1");
if (!$kassa) {
    $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans) VALUES (0, 0, 0, 0, 0)");
    $kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=0 LIMIT 1");
}

$db->begin();
try {
    $col = $tt === 'naqd' ? 'naqd_balans' : ($tt === 'karta' ? 'karta_balans' : ($tt === 'bank' ? 'bank_balans' : 'usd_balans'));

    // Kassaga QO'SHISH
    $db->q("UPDATE im_kassa SET $col = $col + $summa WHERE filial_id=0");

    // Balans log — kategoriya='boshqa_kirim', foydaga ta'sir qilmaydi
    $db->q("INSERT INTO im_balans
                (tur, kategoriya, summa_som, summa_usd, usd_kurs, manba_id, manba_tur, filial_id, izoh, xodim_id, sana)
            VALUES
                ('kirim', 'boshqa_kirim', $summa_som, $summa_usd, $usd_kurs, $im_user_id, '$tt', 0, '$izoh', $im_user_id, $sana_sql)");

    $db->commit();

    $yangi = (float)$db->val("SELECT $col FROM im_kassa WHERE filial_id=0");
    $sana_log = $sana_bugun ? date('Y-m-d H:i:s') : $sana_d . ' ' . date('H:i:s');

    im_log('im_kassa', 0, 'kapital_kiritish',
        [],
        ['summa' => $summa, 'tt' => $tt, 'yangi' => $yangi, 'maqsad' => $maqsad, 'sana' => $sana_log],
        "Kapital kiritish: $summa $label_currency ($tt) — $maqsad"
    );

    im_json('ok', im_money($summa) . " $label_currency kassaga muvaffaqiyatli kiritildi! Yangi balans: " . im_money($yangi) . " $label_currency", [
        'yangi_balans' => $yangi,
    ]);

} catch (Exception $e) {
    $db->rollback();
    im_json('error', 'Xatolik: ' . $e->getMessage());
}
