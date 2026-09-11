<?php
// ============================================================
//  IMezon — Admin Pul Olish AJAX Handler
//  POST: summa, tolov_turi, izoh, sana
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$summa  = (float)($_POST['summa']     ?? 0);
$tt     = mysqli_real_escape_string($link, $_POST['tolov_turi'] ?? 'naqd');
$izoh   = mysqli_real_escape_string($link, trim($_POST['izoh']  ?? ''));
$sana_d = mysqli_real_escape_string($link, $_POST['sana']       ?? date('Y-m-d'));

if ($summa <= 0)  im_json('error', 'Summa 0 dan katta bo\'lishi kerak');
if (!$izoh)       im_json('error', 'Izoh majburiy');
if (!in_array($tt, ['naqd','karta','bank','usd'])) im_json('error', "To'lov turi noto'g'ri");

// Sana validatsiya
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sana_d)) $sana_d = date('Y-m-d');
// Bugun bo'lsa NOW() ishlatiladi (timezone xatosini oldini olish uchun),
// boshqa kun bo'lsa sana + joriy server vaqti
$sana_bugun = ($sana_d === date('Y-m-d'));
$sana_sql   = $sana_bugun ? 'NOW()' : "'" . $sana_d . ' ' . date('H:i:s') . "'";

// YAGONA BIZNES KASSASI ni olish
$kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=0 LIMIT 1");
if (!$kassa) {
    $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans) VALUES (0, 0, 0, 0, 0)");
    $kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=0 LIMIT 1");
}

// Balans tekshirish
$mavjud = $tt === 'naqd' ? (float)$kassa['naqd_balans'] : ($tt === 'karta' ? (float)$kassa['karta_balans'] : ($tt === 'bank' ? (float)$kassa['bank_balans'] : (float)$kassa['usd_balans']));
$label = im_tt_nomi($tt, true);

$label_currency = $tt === 'usd' ? 'USD' : "so'm";

if ($summa > $mavjud + 0.01) {
    $mavjud_f = $tt === 'usd' ? number_format($mavjud, 2) : im_money($mavjud);
    $summa_f  = $tt === 'usd' ? number_format($summa, 2) : im_money($summa);
    im_json('error', "$label kassada yetarli pul yo'q! Mavjud: $mavjud_f $label_currency, Kerak: $summa_f $label_currency");
}

$db->begin();
try {
    // Kassadan ayirish
    $col = $tt === 'naqd' ? 'naqd_balans' : ($tt === 'karta' ? 'karta_balans' : ($tt === 'bank' ? 'bank_balans' : 'usd_balans'));
    $db->q("UPDATE im_kassa SET $col = $col - $summa WHERE filial_id=0");

    // Balans log — kategoriya='admin_pul_olish', foydaga ta'sir qilmaydi
    $usd_kurs = (float)im_usd_kurs();
    if ($tt === 'usd') {
        $summa_usd = $summa;
        $summa_som = $summa * $usd_kurs;
    } else {
        $summa_som = $summa;
        $summa_usd = $usd_kurs > 0 ? $summa / $usd_kurs : 0;
    }

    $db->q("INSERT INTO im_balans
                (tur, kategoriya, summa_som, summa_usd, usd_kurs, manba_id, manba_tur, filial_id, izoh, xodim_id, sana)
            VALUES
                ('chiqim','admin_pul_olish', $summa_som, $summa_usd, $usd_kurs, $im_user_id, '$tt', 0, '$izoh', $im_user_id, $sana_sql)");

    $db->commit();

    $yangi = (float)$db->val("SELECT $col FROM im_kassa WHERE filial_id=0");

    im_log('im_kassa', 0, 'pul_olish',
        ['oldin' => $mavjud],
        ['summa' => $summa, 'tt' => $tt, 'keyin' => $yangi, 'izoh' => $izoh],
        "Admin pul olish: $summa $label_currency ($tt)"
    );

    im_json('ok', im_money($summa) . " $label_currency $label kassasidan muvaffaqiyatli olindi. Qoldi: " . im_money($yangi) . " $label_currency", [
        'yangi_balans' => $yangi,
    ]);

} catch (Exception $e) {
    $db->rollback();
    im_json('error', 'Xatolik: ' . $e->getMessage());
}
