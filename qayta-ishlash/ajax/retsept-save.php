<?php
// ============================================================
//  IMezon — Retsept saqlash / olish
//  POST: saqlash, GET: ?get_id=N
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);

$db = new Cyber();

// ── GET: Retseptni olish ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_id'])) {
    $id = (int)$_GET['get_id'];
    $r  = $db->row("SELECT r.*, m.nomi AS mahsulot_nomi FROM im_retseptlar r
                    LEFT JOIN im_mahsulotlar m ON m.id=r.mahsulot_id WHERE r.id=$id");
    if (!$r) im_json('error', 'Topilmadi');
    $r['items'] = $db->rows(
        "SELECT ri.*, m.nomi AS mahsulot_nomi FROM im_retsept_items ri
         LEFT JOIN im_mahsulotlar m ON m.id=ri.mahsulot_id
         WHERE ri.retsept_id=$id ORDER BY ri.id"
    );
    im_json('ok', '', $r);
}

// ── POST: Saqlash / Yangilash ─────────────────────────────────
$retsept_id   = (int)($_POST['retsept_id'] ?? 0);
$nomi         = trim($_POST['nomi']        ?? '');
$tur          = $_POST['tur']              ?? 'ishlab_chiqarish';
$mahsulot_id  = (int)($_POST['mahsulot_id'] ?? 0);
$chiqish_soni = (float)($_POST['chiqish_soni'] ?? 1);
// im_retseptlar.birlik ustuni ENUM('dona','kg','litr','metr') — 'porsiya'
// yo'q. "1 porsiya" ma'nosini chiqish_soni=1 + mahsulot birligi beradi.
$birlik_ok    = ['dona','kg','litr','metr'];
$birlik       = in_array($_POST['birlik'] ?? '', $birlik_ok) ? $_POST['birlik'] : 'dona';
$izoh         = trim($_POST['izoh']        ?? '');
$items_json   = $_POST['items']            ?? '[]';

if (!$nomi || !$mahsulot_id) im_json('error', 'Nomi va mahsulot majburiy!');
if (!in_array($tur, ['ishlab_chiqarish', 'maydalash'])) im_json('error', 'Noto\'g\'ri tur!');

// KONVENSIYA: ishlab chiqarish retsepti DOIM 1 tayyor birlik (1 porsiya /
// 1 dona / 1 shampur) uchun yoziladi — xomashyo shu 1 birlikka. Kerakli
// son "Boshlash" / oshpaz qabuli / qozon ochishda ko'paytiriladi. Boshqa
// qiymat kiritilsa qozon prefill va a-la-carte tannarx jimgina buziladi.
if ($tur === 'ishlab_chiqarish') $chiqish_soni = 1.0;
if ($chiqish_soni <= 0) $chiqish_soni = 1.0;

$items = json_decode($items_json, true) ?: [];
if (empty($items)) im_json('error', 'Kamida bitta item kerak!');

$nomi_s = mysqli_real_escape_string($link, mb_substr($nomi, 0, 200, 'UTF-8'));
$izoh_s = mysqli_real_escape_string($link, mb_substr($izoh, 0, 1000, 'UTF-8'));

$db->begin();
try {
    if ($retsept_id) {
        $ok = $db->q("UPDATE im_retseptlar SET
                nomi='$nomi_s', tur='$tur', mahsulot_id=$mahsulot_id,
                chiqish_soni=$chiqish_soni, birlik='$birlik', izoh='$izoh_s'
                WHERE id=$retsept_id");
        if (!$ok) throw new Exception("Yangilanmadi: " . $db->error());
        $db->q("DELETE FROM im_retsept_items WHERE retsept_id=$retsept_id");
        $save_id = $retsept_id;
        $msg = 'Retsept yangilandi!';
    } else {
        $save_id = $db->insert("INSERT INTO im_retseptlar
            (nomi,tur,mahsulot_id,chiqish_soni,birlik,izoh,xodim_id)
            VALUES ('$nomi_s','$tur',$mahsulot_id,$chiqish_soni,'$birlik','$izoh_s',$im_user_id)");
        if (!$save_id) throw new Exception("Qo'shilmadi: " . $db->error());
        $msg = 'Retsept qo\'shildi!';
    }

    $inserted = 0;
    $seen_items = [];
    foreach ($items as $it) {
        $it_mah  = (int)($it['mahsulot_id'] ?? 0);
        $it_soni = (float)($it['soni'] ?? 0);
        $it_bir  = in_array($it['birlik'] ?? '', $birlik_ok) ? $it['birlik'] : 'dona';
        if (!$it_mah) continue;
        if ($it_soni <= 0) continue;
        // FIFO daftari miqdorni 3 kasr bilan saqlaydi (im_fifo_number).
        // 0.001 dan kichik miqdor sarflash paytida 0 ga aylanib, butun
        // buyurtmani "Xomashyo miqdori noto‘g‘ri" bilan to'xtatib qo'yardi.
        // Shuning uchun uni RETSEPT SAQLASHDA bloklaymiz.
        if ($it_soni < 0.001) {
            $it_nomi = (string)$db->val("SELECT nomi FROM im_mahsulotlar WHERE id=$it_mah");
            throw new Exception("«" . ($it_nomi ?: "#$it_mah") . "» miqdori juda kichik ($it_soni). "
                . "Eng kami 0.001. Birlikni maydaroq o'lchovga o'zgartiring (masalan kg → gramm).");
        }
        if ($tur === 'maydalash' && $it_mah === $mahsulot_id) {
            throw new Exception('Asosiy mahsulot chiqish mahsuloti bo‘la olmaydi');
        }
        if (isset($seen_items[$it_mah])) throw new Exception('Bir mahsulot ikki marta kiritilgan');
        $seen_items[$it_mah] = true;
        $db->q("INSERT INTO im_retsept_items (retsept_id,mahsulot_id,soni,birlik)
                VALUES ($save_id,$it_mah,$it_soni,'$it_bir')");
        $inserted++;
    }
    if ($inserted < 1) throw new Exception('Kamida bitta musbat miqdorli item kerak');

    $db->commit();
    im_json('ok', $msg, ['id' => $save_id]);
} catch (Exception $e) {
    $db->rollback();
    im_json('error', $e->getMessage());
}
