<?php
// ============================================================
//  IMezon — Sotuvchi: Order saqlash
//  action=hold   => Stol ochib qoladi, oshpazga yangi qism boradi
//  action=create => Stol yopilib kassaga o'tadi
//  action=cancel => Bekor qilish
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
// Admin/kassir ham stol buyurtmalariga mahsulot qo'shib/o'chira olishi kerak
// (dukon POS zal xaritasi orqali) — bekor qilish esa hamon faqat
// dukon/ajax/pending-orders.php orqali (pastdagi action=cancel bloki
// hech kimga ruxsat bermaydi, rolidan qat'iy nazar).
im_rol_check(['sotuvchi', 'admin', 'kassir']);

header('Content-Type: application/json; charset=utf-8');

$db = new Cyber();
$action = $_POST['action'] ?? '';
$sotuvchi_id = (int) $_SESSION['im_user_id'];
$sotuvchi_ism = mysqli_real_escape_string($link, $_SESSION['im_ism'] ?? 'Sotuvchi');
$filial_id = (int) $_SESSION['im_filial_id'];

// ── Xodim log yozish (order darajasi) ─────────────────────
function write_log($db, $xodim_id, $filial_id, $amal, $order_id = 0, $mijoz = '', $summa = 0, $izoh = '')
{
    $amal = mysqli_real_escape_string($GLOBALS['link'], $amal);
    $mijoz = mysqli_real_escape_string($GLOBALS['link'], $mijoz);
    $izoh = mysqli_real_escape_string($GLOBALS['link'], $izoh);
    $summa = (float) $summa;
    $order_id = (int) $order_id;
    $db->q(
        "INSERT INTO im_xodim_log (xodim_id, filial_id, amal, order_id, mijoz_ism, summa, izoh)
         VALUES ($xodim_id, $filial_id, '$amal', " . ($order_id ?: 'NULL') . ", " . ($mijoz ? "'$mijoz'" : 'NULL') . ", $summa, " . ($izoh ? "'$izoh'" : 'NULL') . ")"
    );
}

// ── YANGI: Mahsulot darajasida log yozish ─────────────────
function write_item_log($db, $xodim_id, $filial_id, $order_id, $item_id, $mahsulot_id, $nomi, $amal, $eski_soni, $yangi_soni)
{
    $nomi = mysqli_real_escape_string($GLOBALS['link'], $nomi);
    $amal = mysqli_real_escape_string($GLOBALS['link'], $amal);
    $item_id_s = $item_id ? (int) $item_id : 'NULL';
    $db->q(
        "INSERT INTO im_order_item_log
            (order_id, item_id, mahsulot_id, xodim_id, filial_id, amal, eski_soni, yangi_soni, nomi)
         VALUES (
            $order_id, $item_id_s, $mahsulot_id,
            $xodim_id, $filial_id,
            '$amal',
            $eski_soni, $yangi_soni,
            '$nomi'
         )"
    );
}

// ─────────────────────────────────────────────────────────
// YORDAMCHI: items massivini tekshirish va status aniqlash
// ─────────────────────────────────────────────────────────
function check_items_kitchen($db, $items)
{
    foreach ($items as $it) {
        $mid = (int) ($it['mahsulot_id'] ?? 0);
        $soni = (float) ($it['soni'] ?? 0);
        $locked = (float) ($it['locked_soni'] ?? 0);
        if ($mid && $soni > 0) {
            $mah = $db->row("SELECT nomi, COALESCE(sotuv_qadami,1) AS qadam FROM im_mahsulotlar WHERE id=$mid");
            $qadam = max(0.001, (float)($mah['qadam'] ?? 1));
            if (abs(($soni / $qadam) - round($soni / $qadam)) > 0.0001) {
                im_json('error', "«{$mah['nomi']}» miqdori {$qadam} qadam bilan kiritilishi kerak");
            }
        }
        if ($soni < $locked) {
            im_json('error', "Oshpazda pishirilayotgan miqdordan kamaytirish mumkin emas!");
        }
    }
    foreach ($items as $it) {
        $mid = (int) ($it['mahsulot_id'] ?? 0);
        $soni = (float) ($it['soni'] ?? 0);
        $locked = (float) ($it['locked_soni'] ?? 0);
        if ($mid && $soni > $locked) {
            $ok = (int) $db->val("SELECT oshpaz_kerak FROM im_mahsulotlar WHERE id=$mid");
            if ($ok)
                return true;
        }
    }
    return false;
}

// ─────────────────────────────────────────────────────────
// 1. HOLD yoki CREATE
// ─────────────────────────────────────────────────────────
if ($action === 'hold' || $action === 'create') {

    $order_id = (int) ($_POST['order_id'] ?? 0);
    // DIQQAT: bu yerda ATAYLAB im_f() emas, mysqli_real_escape_string()
    // ishlatiladi — loyihadagi BOSHQA barcha yozuv joylari bilan bir xil
    // qoida: baza matnni XOM saqlaydi (faqat SQL uchun escape qilingan),
    // HTML-escape esa CHIQISHDA (im_f() PHP tarafida, im_esc() JS
    // tarafida) qo'llanadi. Ilgari bu yerda im_f() (htmlspecialchars)
    // yozishda ishlatilardi — bu ikki muammo keltirib chiqargan edi:
    //   1) htmlspecialchars backslash'ni ESKAPE QILMAYDI, ya'ni SQL
    //      satrida yagona himoya bo'lib qolgani uchun "\" bilan
    //      tugaydigan matn keyingi ustunlarga sizib chiqishi mumkin edi.
    //   2) bazada saqlangan qiymat allaqachon HTML-encode bo'lgani uchun
    //      chek/eksport kabi HTML BO'LMAGAN chiqishlarda "&#039;" kabi
    //      belgi harflari ko'rinardi.
    // Xom (escape'siz) nusxalar — write_log() o'zi ichida yana bir marta
    // escape qiladi, shuning uchun unga ALREADY-escaped qiymat berilsa
    // ikki marta escape bo'lib qoladi (masalan ism ichidagi "'" "\\'"
    // o'rniga "\\\\'" bo'lib yozilardi). Pastda ikkalasi ham kerak.
    $mijoz_ism_raw = mb_substr(trim($_POST['mijoz_ism'] ?? ''), 0, 100, 'UTF-8');
    $izoh_raw      = mb_substr(trim($_POST['izoh'] ?? ''), 0, 500, 'UTF-8');
    $mijoz_ism = mysqli_real_escape_string($link, $mijoz_ism_raw);
    $izoh      = mysqli_real_escape_string($link, $izoh_raw);
    $items = json_decode($_POST['items'] ?? '[]', true);
    $stol_id = (int) ($_POST['stol_id'] ?? 0);
    $olib_ketish = !empty($_POST['olib_ketish']) ? 1 : 0;

    if (empty($items))
        im_json('error', 'Savatcha bo\'sh!');
    if (!$mijoz_ism)
        im_json('error', 'Stol/mijoz tanlanmagan!');

    // Mavjud orderning turi va stoli o'zgarmas biznes identifikatoridir.
    // Brauzer yuborgan olib_ketish/stol_id ga ko'r-ko'rona ishonilsa oddiy
    // stol orderini olib ketishga aylantirib xizmat haqini chetlab o'tish,
    // yoki tugallangan orderni qayta ochish mumkin bo'lib qoladi.
    $old_hint = null;
    if ($order_id > 0) {
        $old_hint = $db->row(
            "SELECT id, status, stol_id, olib_ketish
             FROM im_sotuvchi_order WHERE id=$order_id AND filial_id=$filial_id"
        );
        if (!$old_hint) im_json('error', 'Order topilmadi');

        $old_stol = $old_hint['stol_id'] !== null ? (int)$old_hint['stol_id'] : 0;
        $old_take = (int)$old_hint['olib_ketish'];
        if (!im_order_kontekst_mos($stol_id, $olib_ketish, $old_stol, $old_take)) {
            im_json('error', "Mavjud orderning stoli yoki turi o'zgartirilmaydi");
        }
        if (!im_order_tahrirlanadi($old_hint['status'])) {
            im_json('error', "Bu order kassaga yuborilgan yoki yopilgan — tahrirlash mumkin emas");
        }

        // #12 kabi tarixiy stolsiz olib-ketish orderlari yangi yaratilmaydi,
        // ammo mijoz hisobini yo'qotmaslik uchun mavjud yozuv yakunlanishi mumkin.
        $stol_id = $old_stol;
        $olib_ketish = $old_take;
    } elseif ($olib_ketish && !$stol_id) {
        // Stolga bog'liq qolgan ikki qoida active_normal qulflangach tekshiriladi.
        im_json('error', im_order_yaratish_xatosi($stol_id, $olib_ketish, 0));
    }

    $stol_id_sql = $stol_id ?: 'NULL';

    $has_new_kitchen = check_items_kitchen($db, $items);

    // Status aniqlash
    if ($action === 'hold') {
        $status = $has_new_kitchen ? 'oshpazda' : 'stol_band';
    } else {
        $status = $has_new_kitchen ? 'oshpazda' : 'tasdiqlandi';
    }

    $db->begin();

    // Bitta stolga oid barcha ochish/yopish amallari shu stol qatori orqali
    // ketma-ketlashtiriladi. Checkout va bekor qilish endpointlari ham aynan
    // shu qatorni birinchi bo'lib qulflaydi.
    if ($stol_id) {
        $stol_row = $db->row(
            "SELECT id FROM im_stollar
             WHERE id=$stol_id AND filial_id=$filial_id AND status=1 FOR UPDATE"
        );
        if (!$stol_row) {
            $db->rollback();
            im_json('error', 'Stol topilmadi yoki faol emas');
        }
    }
    // Checkout ham qoldiqni orderdan oldin qulflaydi. Bir xil lock tartibi
    // order-save ↔ checkout o'rtasidagi deadlock ehtimolini yopadi.
    $db->rows(
        "SELECT mahsulot_id FROM im_filial_qoldiq
         WHERE filial_id=$filial_id ORDER BY mahsulot_id FOR UPDATE"
    );

    if ($order_id > 0) {
        $old = $db->row(
            "SELECT id, status, stol_id, olib_ketish
             FROM im_sotuvchi_order WHERE id=$order_id AND filial_id=$filial_id FOR UPDATE"
        );
        if (!$old) {
            $db->rollback();
            im_json('error', 'Order topilmadi');
        }
        $locked_stol = $old['stol_id'] !== null ? (int)$old['stol_id'] : 0;
        if (!im_order_kontekst_mos($locked_stol, $old['olib_ketish'], $stol_id, $olib_ketish)
            || !im_order_tahrirlanadi($old['status'])) {
            $db->rollback();
            im_json('error', "Order boshqa qurilmada o'zgargan yoki kassaga yuborilgan");
        }

        // ── 'pishirilmoqda' — oshpaz qabul qilgan, taom tayyorlanmoqda ──
        if ($old['status'] === 'pishirilmoqda') {
            if ($action === 'create' && !$has_new_kitchen) {
                // Kassaga yuborilsa oshpazning "Pishirilmoqda" kartasi
                // yo'qolar va "Tayyor" tugmasi bosilmay qolar edi.
                $db->rollback();
                im_json('error', "Taom hali pishirilmoqda — oshpaz \"Tayyor\" bosgandan keyin kassaga yuboring.");
            }
            // Yangi oshpaz mahsuloti qo'shilsa → 'oshpazda' (oshpaz faqat
            // qo'shilgan qismini ko'radi). Aks holda holat o'zgarmaydi.
            $status = $has_new_kitchen ? 'oshpazda' : 'pishirilmoqda';
        }

        // Agar order oshpazda edi va yangi oshpaz mahsuloti qo'shilmasa
        // stol_band ga qaytarish (oshpaz o'z ishini tugatgan)
        elseif ($old['status'] === 'oshpazda' && !$has_new_kitchen) {
            $status = $action === 'create' ? 'tasdiqlandi' : 'stol_band';
        }

        // Agar stol_band edi va sotuvchi kassaga yubordi (create)
        elseif ($old['status'] === 'stol_band' && $action === 'create' && !$has_new_kitchen) {
            $status = 'tasdiqlandi';
        }

        $db->q("UPDATE im_sotuvchi_order SET mijoz_ism='$mijoz_ism', stol_id=$stol_id_sql, olib_ketish=$olib_ketish, izoh='$izoh', status='$status', updated_at=NOW()
                WHERE id=$order_id AND filial_id=$filial_id");
    } else {
        if ($stol_id) {
            $active_normal = (int)$db->val(
                "SELECT COUNT(*) FROM im_sotuvchi_order
                 WHERE filial_id=$filial_id AND stol_id=$stol_id AND olib_ketish=0
                   AND status NOT IN ('bekor','tugallandi')"
            );
            $create_error = im_order_yaratish_xatosi($stol_id, $olib_ketish, $active_normal);
            if ($create_error) {
                $db->rollback();
                im_json('error', $create_error);
            }
        }
        $order_id = $db->insert(
            "INSERT INTO im_sotuvchi_order (sotuvchi_id, filial_id, mijoz_ism, stol_id, olib_ketish, izoh, status)
             VALUES ($sotuvchi_id, $filial_id, '$mijoz_ism', $stol_id_sql, $olib_ketish, '$izoh', '$status')"
        );
        if (!$order_id) {
            $db->rollback();
            im_json('error', 'Order yaratishda xato');
        }
    }

    // ── Mahsulotlarni upsert + item-log yozish ─────────────
    // Kalit endi (order_id, mahsulot_id, set_id) — bitta mahsulot orderda
    // ikki marta bo'lishi mumkin: biri setda (set_id>0), biri alohida (0).
    $passed_keys = [];
    foreach ($items as $item) {
        $mid = (int) ($item['mahsulot_id'] ?? 0);
        $soni = (float) ($item['soni'] ?? 0);
        $narx = (float) ($item['narx'] ?? 0);
        $locked = (float) ($item['locked_soni'] ?? 0);
        $set_id     = (int) ($item['set_id'] ?? 0);
        $set_id_sql = $set_id ?: 'NULL';
        // Olib ketish — alohida order: uning HAMMA qatori qadoqlanadi va
        // xizmat haqisiz. Oddiy stol orderida qisman "saboy" yo'q; mijoz
        // uyiga mahsulot buyursa yangi Olib ketish orderi ochiladi.
        $item_ok = $olib_ketish ? $soni : 0;
        if (!$mid || $soni <= 0)
            continue;

        $passed_keys[] = $mid . ':' . $set_id;

        // Mahsulot nomini olish
        $prod_nomi = (string) $db->val("SELECT nomi FROM im_mahsulotlar WHERE id=$mid");
        // Rezerv faqat tayyor vitrinali SKU uchun yuritiladi. Retseptli yoki
        // oshxona mahsulotining xomashyosi keyingi biznes bosqichida yechiladi.
        $rezervlanadi = im_vitrinali($db, $mid);
        $yangi_rezerv = $rezervlanadi ? $soni : 0.0;

        $exist_row = $db->row("SELECT id, soni, rezerv_soni FROM im_sotuvchi_order_item
                               WHERE order_id=$order_id AND mahsulot_id=$mid AND COALESCE(set_id,0)=$set_id");

        if ($exist_row) {
            $item_id = (int) $exist_row['id'];
            $eski_soni = (float) $exist_row['soni'];

            // REZERV: faqat FARQ qadar band qilamiz (yoki qaytaramiz).
            // Ofitsant 2 tadan 3 taga oshirsa — 1 ta band qilinadi.
            $eski_rezerv = (float) $exist_row['rezerv_soni'];
            if ($rezervlanadi && !im_rezerv($db, $filial_id, $mid, $yangi_rezerv - $eski_rezerv)) {
                $mavjud = (float)$db->val("SELECT COALESCE(soni,0) FROM im_filial_qoldiq
                                           WHERE filial_id=$filial_id AND mahsulot_id=$mid");
                $db->rollback();
                im_json('error', "«{$prod_nomi}» yetarli emas — vitrinada {$mavjud} ta qoldi "
                    . "(boshqa stollardagi buyurtmalar hisobga olingan).");
            }

            $db->q("UPDATE im_sotuvchi_order_item SET soni=$soni, narx=$narx,
                    olib_ketish_soni=$item_ok, rezerv_soni=$yangi_rezerv WHERE id=$item_id");

            // Delta log
            if (abs($soni - $eski_soni) > 0.0001) {
                $amal_type = $soni > $eski_soni ? 'oshirildi' : 'kamaytir';
                write_item_log($db, $sotuvchi_id, $filial_id, $order_id, $item_id, $mid, $prod_nomi, $amal_type, $eski_soni, $soni);
            }
        } else {
            // REZERV: yangi qator — butun miqdor band qilinadi
            if ($rezervlanadi && !im_rezerv($db, $filial_id, $mid, $yangi_rezerv)) {
                $mavjud = (float)$db->val("SELECT COALESCE(soni,0) FROM im_filial_qoldiq
                                           WHERE filial_id=$filial_id AND mahsulot_id=$mid");
                $db->rollback();
                im_json('error', "«{$prod_nomi}» yetarli emas — vitrinada {$mavjud} ta qoldi "
                    . "(boshqa stollardagi buyurtmalar hisobga olingan).");
            }

            $item_id = $db->insert(
                "INSERT INTO im_sotuvchi_order_item (order_id, mahsulot_id, set_id, soni, narx, tayyorlandi_soni, olib_ketish_soni, rezerv_soni)
                 VALUES ($order_id, $mid, $set_id_sql, $soni, $narx, 0, $item_ok, $yangi_rezerv)"
            );
            write_item_log($db, $sotuvchi_id, $filial_id, $order_id, $item_id, $mid, $prod_nomi, 'qoshildi', 0, $soni);
        }
    }

    // O'chirilgan qatorlar — kalit (mahsulot_id:set_id) bo'yicha solishtiramiz,
    // shunda setdagi mahsulot olib tashlansa ham, o'sha mahsulotning
    // alohida (à la carte) qatori tegilmaydi va aksincha.
    if (!empty($passed_keys)) {
        $keys_str = implode(',', array_map(
            fn($k) => "'" . mysqli_real_escape_string($link, $k) . "'",
            $passed_keys
        ));
        $key_expr = "CONCAT(i.mahsulot_id, ':', COALESCE(i.set_id,0))";
        $deleted_rows = $db->rows(
            "SELECT i.id, i.mahsulot_id, i.soni, i.rezerv_soni, m.nomi
             FROM im_sotuvchi_order_item i
             JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
             WHERE i.order_id=$order_id
               AND $key_expr NOT IN ($keys_str)
               AND i.tayyorlandi_soni = 0"
        );
        foreach ($deleted_rows as $dr) {
            // REZERVNI QAYTARISH: qator buyurtmadan olib tashlandi
            im_rezerv($db, $filial_id, (int)$dr['mahsulot_id'], -(float)$dr['rezerv_soni']);
            write_item_log(
                $db,
                $sotuvchi_id,
                $filial_id,
                $order_id,
                (int) $dr['id'],
                (int) $dr['mahsulot_id'],
                $dr['nomi'],
                'ochirildi',
                (float) $dr['soni'],
                0
            );
        }
        $db->q("DELETE FROM im_sotuvchi_order_item
                WHERE order_id=$order_id
                  AND CONCAT(mahsulot_id, ':', COALESCE(set_id,0)) NOT IN ($keys_str)
                  AND tayyorlandi_soni = 0");
    }

    if ($db->error()) {
        $db->rollback();
        im_json('error', 'DB xatosi: ' . $db->error());
    }
    $db->commit();

    // Summa hisob va order-log
    $summa = (float) $db->val("SELECT SUM(soni*narx) FROM im_sotuvchi_order_item WHERE order_id=$order_id");
    $amal_log = $action === 'hold'
        ? ($has_new_kitchen ? 'oshpazga_yuborildi' : 'stol_band')
        : ($has_new_kitchen ? 'oshpazga_yuborildi' : 'kassaga_yuborildi');
    write_log($db, $sotuvchi_id, $filial_id, $amal_log, $order_id, $mijoz_ism_raw, $summa, $izoh_raw);

    $msg = $action === 'hold'
        ? ($has_new_kitchen ? 'Oshxonaga yuborildi' : 'Stol saqlab qolindi')
        : ($has_new_kitchen ? 'Oshxonaga yuborildi, kassa kutmoqda' : 'Kassaga yuborildi');

    im_json('ok', $msg, ['order_id' => $order_id, 'status' => $status]);
}

// ─────────────────────────────────────────────────────────
// 2. BEKOR QILISH — SOTUVCHIGA TAQIQLANGAN
// ─────────────────────────────────────────────────────────
// Yuborilgan buyurtmani bekor qilish endi faqat kassa (dukon POS,
// dukon/ajax/pending-orders.php) orqali amalga oshiriladi. Sotuvchi
// yuborilmagan (hali order_id olmagan) qoralamani frontendning o'zida
// hech qanday server so'rovisiz o'chiradi — shu sababli bu yerga
// umuman kelmaydi. Himoya sifatida server ham rad etadi.
if ($action === 'cancel') {
    im_json('error', "Yuborilgan buyurtmani bekor qilish huquqi yo'q — buni faqat kassa (POS) amalga oshira oladi.");
}

im_json('error', 'Noto\'g\'ri so\'rov');
