<?php
// ============================================================
//  IMezon — Partiya Ochish AJAX
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new Cyber();

$ps_id     = (int)($_POST['postavshik_id'] ?? 0);
$sana      = trim($_POST['sana'] ?? date('Y-m-d'));
$faktura   = trim($_POST['faktura_nomer'] ?? '');
$izoh      = trim($_POST['izoh'] ?? '');

if (!$ps_id) im_json('error', "Postavshikni tanlang");

// Validatsiya
if (!$db->val("SELECT id FROM im_postavshiklar WHERE id=$ps_id AND status=1")) {
    im_json('error', "Postavshik topilmadi");
}

// Sana tekshiruvi
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sana)) {
    $sana = date('Y-m-d');
}

// Ko'p ochiq partiyalarga ruxsat beramiz.

$faktura_s = mysqli_real_escape_string($link, mb_substr($faktura, 0, 50, 'UTF-8'));
$izoh_s    = mysqli_real_escape_string($link, mb_substr($izoh, 0, 500, 'UTF-8'));
$qabul_filial_id = (int)($_POST['qabul_filial_id'] ?? 0);

if ($qabul_filial_id < 0 || ($qabul_filial_id > 0 && !$db->val("SELECT id FROM im_filiallar WHERE id=$qabul_filial_id AND status=1"))) {
    im_json('error', "Qabul filiali topilmadi yoki yopilgan");
}

try {
$new_id = $db->insert(
    "INSERT INTO im_partiyalar
        (postavshik_id, sana, faktura_nomer, izoh, holat, xodim_id,
         jami_summa, tolov_turi, usd_summa, usd_kurs, tolandi, qarz_qoldi, qabul_filial_id)
     VALUES
        ($ps_id, '$sana', '$faktura_s', '$izoh_s', 'ochiq', $im_user_id,
         0, 'naqd', 0, 0, 0, 0, $qabul_filial_id)"
);

if (!$new_id) im_json('error', "Saqlashda xatolik: " . $db->error());

// Istoriyaga yozish
im_log('im_partiyalar', $new_id, 'insert', null,
    ['postavshik_id'=>$ps_id,'faktura'=>$faktura,'sana'=>$sana,'filial'=>$qabul_filial_id],
    "Yangi partiya ochildi"
);

im_json('ok', "Partiya ochildi. Mahsulotlarni qo'shishni boshlang.", ['id' => $new_id]);

} catch (Throwable $e) {
    im_json('error', "Xatolik: " . $e->getMessage());
}
