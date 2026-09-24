<?php
// ============================================================
//  IMezon — Mahsulot AJAX: Saqlash (rasm yuklash bilan)
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

$id          = (int)($_POST['id'] ?? 0);
$nomi        = trim($_POST['nomi'] ?? '');
$kat_id      = (int)($_POST['kategoriya_id'] ?? 0);
$barcode     = trim($_POST['barcode'] ?? '');
$birlik      = trim($_POST['birlik'] ?? 'dona');
$sotuv_qadami = round((float)($_POST['sotuv_qadami'] ?? 1), 3);
$sotuv_narx  = (float)($_POST['sotuv_narx'] ?? 0);
$tavsif      = trim($_POST['tavsif'] ?? '');
$ulg_min     = (int)($_POST['ulg_min_soni'] ?? 0);
$ulg_narx    = (float)($_POST['ulg_narx'] ?? 0);
$sotiladi             = isset($_POST['sotiladi']) ? 1 : 0;
$faqat_ishlab_chiqarish = isset($_POST['faqat_ishlab_chiqarish']) ? 1 : 0;
// J3(c): oshxona taomi (oshpaz_kerak=1, qozon_rejim=0) uchun faol
// ishlab-chiqarish retsepti bo'lishi SHART — aks holda oshpaz qabul qila
// olmaydi va kassada chek yopilmaydi (sotuv-save.php: "FIFO tannarxi
// topilmadi"). Bu yerda BLOKLAMAYMIZ (retsept keyin kiritilishi mumkin),
// faqat javobga ogohlantirish qo'shamiz.
function im_mah_retsept_ogoh($db, $mahsulot_id, $oshpaz_kerak, $qozon_rejim) {
    if ((int)$oshpaz_kerak !== 1 || (int)$qozon_rejim === 1) return '';
    $bor = $db->val("SELECT id FROM im_retseptlar
                     WHERE mahsulot_id=" . (int)$mahsulot_id . "
                       AND tur='ishlab_chiqarish' AND status=1 LIMIT 1");
    if ($bor) return '';
    return " ⚠️ Diqqat: bu oshxona taomi, lekin faol retsepti yo'q — "
         . "«Qayta ishlash → Retseptlar» bo'limida retsept kiriting, aks holda "
         . "buyurtmaga qo'shib bo'lmaydi.";
}

$oshpaz_kerak = isset($_POST['oshpaz_kerak']) ? 1 : 0;
// Retsepti bor, oshpaz tasdig'i shart emas — xomashyo sotuvda yechiladi.
// Oshpaz belgisi qo'yilgan bo'lsa bu belgi ma'nosiz (xomashyo ikki marta
// yechilib ketmasligi uchun) — shuning uchun o'zaro istisno qilamiz.
$retsept_avto = (!$oshpaz_kerak && isset($_POST['retsept_avto'])) ? 1 : 0;
// Kunlik qozon / zagotovka rejimi (osh, shashlik): xomashyo qozon
// ochilganda bir marta yechiladi, har buyurtmada emas. Faqat oshpaz
// tayyorlaydigan mahsulot uchun ma'noli — aks holda o'chiriladi.
$qozon_rejim = ($oshpaz_kerak && isset($_POST['qozon_rejim'])) ? 1 : 0;

if (!$nomi) im_json('error', 'Mahsulot nomini kiriting');

$birlik_ok = ['dona','kg','litr','metr','porsiya','juft','komplekt','quti'];
if (!in_array($birlik, $birlik_ok)) $birlik = 'dona';
$qadam_ok = [1.0, 0.5, 0.25, 0.1, 0.001];
if (!in_array($sotuv_qadami, $qadam_ok, true)) $sotuv_qadami = 1.0;

// Barcode validatsiya
if ($barcode && !preg_match('/^IM-\d{8}-\d{4}$/', $barcode)) {
    if (!preg_match('/^[A-Za-z0-9\-_.]+$/', $barcode)) {
        im_json('error', "Barcode faqat A-Z, 0-9, -, _ dan iborat bo'lishi kerak");
    }
}

// Barcode bo'sh bo'lsa generatsiya qil
if (!$barcode) {
    $today = date('Ymd');
    $last  = $db->val(
        "SELECT barcode FROM im_mahsulotlar
         WHERE barcode LIKE 'im-$today-%'
         ORDER BY id DESC LIMIT 1"
    );
    if ($last) {
        $num = (int)substr($last, -4) + 1;
    } else {
        $num = 1;
    }
    $barcode = 'im-' . $today . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

// ── Rasm yuklash ──────────────────────────────────────────
$rasm_path = null;
if (!empty($_FILES['rasm']['tmp_name']) && $_FILES['rasm']['error'] === UPLOAD_ERR_OK) {
    $file      = $_FILES['rasm'];
    $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed   = ['jpg','jpeg','png','webp','gif'];
    $max_size  = 5 * 1024 * 1024; // 5 MB

    if (!in_array($ext, $allowed)) {
        im_json('error', 'Rasm formati noto\'g\'ri. Faqat JPG, PNG, WEBP qabul qilinadi');
    }
    if ($file['size'] > $max_size) {
        im_json('error', 'Rasm hajmi 5 MB dan katta bo\'lmasin');
    }
    // Kengaytma — foydalanuvchi yozgan nom, u haqiqatni bildirmaydi.
    // Fayl TARKIBI ham rasm ekanini tekshiramiz: aks holda ichida
    // kod bo'lgan "rasm" serverda yotib qoladi.
    $olcham = @getimagesize($file['tmp_name']);
    if ($olcham === false || empty($olcham['mime']) || strpos($olcham['mime'], 'image/') !== 0) {
        im_json('error', 'Bu fayl rasm emas');
    }

    $upload_dir = __DIR__ . '/../../uploads/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $filename   = 'mah_' . time() . '_' . mt_rand(100,999) . '.' . $ext;
    $dest       = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        im_json('error', 'Rasmni saqlashda xatolik yuz berdi');
    }
    $rasm_path = 'uploads/products/' . $filename;
}

$n_s  = mysqli_real_escape_string($link, mb_substr($nomi, 0, 250, 'UTF-8'));
$b_s  = mysqli_real_escape_string($link, mb_substr($barcode, 0, 30, 'UTF-8'));
$br_s = mysqli_real_escape_string($link, $birlik);
$t_s  = mysqli_real_escape_string($link, mb_substr($tavsif, 0, 1000, 'UTF-8'));
$k_s  = $kat_id ?: 'NULL';

if ($id > 0) {
    // Barcode uniqueness (boshqa mahsulotda)
    $bc_exists = $db->val("SELECT id FROM im_mahsulotlar WHERE barcode='$b_s' AND id!=$id");
    if ($bc_exists) im_json('error', 'Bu barcode boshqa mahsulotda mavjud');

    $db->begin();
    try {
        // Rasm yangilash
        $rasm_sql = '';
        if ($rasm_path !== null) {
            // Eski rasmni o'chirish
            $old_rasm = $db->val("SELECT rasm FROM im_mahsulotlar WHERE id=$id");
            if ($old_rasm) {
                $old_file = __DIR__ . '/../../' . $old_rasm;
                if (file_exists($old_file)) @unlink($old_file);
            }
            $r_s = mysqli_real_escape_string($link, $rasm_path);
            $rasm_sql = ", rasm='$r_s'";
        }

        $db->q("UPDATE im_mahsulotlar SET
                    nomi='$n_s', kategoriya_id=$k_s, barcode='$b_s',
                    birlik='$br_s', sotuv_qadami=$sotuv_qadami, tavsif='$t_s', sotiladi=$sotiladi,
                    faqat_ishlab_chiqarish=$faqat_ishlab_chiqarish,
                    oshpaz_kerak=$oshpaz_kerak,
                    retsept_avto=$retsept_avto,
                    qozon_rejim=$qozon_rejim
                    $rasm_sql
                WHERE id=$id");

        // Sotuv narxi yangilash
        $narx_exists = $db->val("SELECT id FROM im_narxlar WHERE mahsulot_id=$id");
        if ($sotuv_narx > 0) {
            if ($narx_exists) {
                $db->q("UPDATE im_narxlar SET sotish_narxi=$sotuv_narx WHERE mahsulot_id=$id");
            } else {
                $db->q("INSERT INTO im_narxlar (mahsulot_id, sotish_narxi) VALUES ($id, $sotuv_narx)");
            }
        }

        // Ulgurji narx
        $ulg_exists = $db->val("SELECT id FROM im_narx_ulgurji WHERE mahsulot_id=$id");
        if ($ulg_min > 0 && $ulg_narx > 0) {
            if ($ulg_exists) {
                $db->q("UPDATE im_narx_ulgurji SET min_soni=$ulg_min, ulgurji_narxi=$ulg_narx WHERE mahsulot_id=$id");
            } else {
                $db->q("INSERT INTO im_narx_ulgurji (mahsulot_id, min_soni, ulgurji_narxi) VALUES ($id, $ulg_min, $ulg_narx)");
            }
        } elseif ($ulg_exists) {
            $db->q("DELETE FROM im_narx_ulgurji WHERE mahsulot_id=$id");
        }

        $db->commit();
        im_log('im_mahsulotlar', $id, 'update', null,
            ['nomi'=>$nomi,'barcode'=>$barcode,'birlik'=>$birlik,'sotuv_qadami'=>$sotuv_qadami,'sotuv_narx'=>$sotuv_narx],
            "Mahsulot tahrirlandi"
        );
        im_json('ok', 'Mahsulot yangilandi' . im_mah_retsept_ogoh($db, $id, $oshpaz_kerak, $qozon_rejim),
            ['id' => $id, 'barcode' => $barcode, 'rasm' => $rasm_path]);

    } catch (Exception $e) {
        $db->rollback();
        im_json('error', 'Xatolik: ' . $e->getMessage());
    }

} else {
    // Yangi mahsulot
    $bc_exists = $db->val("SELECT id FROM im_mahsulotlar WHERE barcode='$b_s'");
    if ($bc_exists) {
        im_json('error', 'Bu barcode boshqa mahsulotda mavjud');
    }

    $db->begin();
    try {
        $r_s = $rasm_path ? mysqli_real_escape_string($link, $rasm_path) : '';
        $rasm_col = $r_s ? ', rasm' : '';
        $rasm_val = $r_s ? ", '$r_s'" : '';

        $new_id = $db->insert(
            "INSERT INTO im_mahsulotlar (nomi, kategoriya_id, barcode, birlik, sotuv_qadami, tavsif, sotiladi, faqat_ishlab_chiqarish, oshpaz_kerak, retsept_avto, qozon_rejim, status $rasm_col)
             VALUES ('$n_s', $k_s, '$b_s', '$br_s', $sotuv_qadami, '$t_s', $sotiladi, $faqat_ishlab_chiqarish, $oshpaz_kerak, $retsept_avto, $qozon_rejim, 1 $rasm_val)"
        );

        if (!$new_id) throw new Exception($db->error());

        if ($sotuv_narx > 0) {
            $db->q("INSERT INTO im_narxlar (mahsulot_id, sotish_narxi) VALUES ($new_id, $sotuv_narx)");
        }

        if ($ulg_min > 0 && $ulg_narx > 0) {
            $db->q("INSERT INTO im_narx_ulgurji (mahsulot_id, min_soni, ulgurji_narxi) VALUES ($new_id, $ulg_min, $ulg_narx)");
        }

        $db->commit();
        im_log('im_mahsulotlar', $new_id, 'insert', null,
            ['nomi'=>$nomi,'barcode'=>$barcode,'birlik'=>$birlik,'sotuv_qadami'=>$sotuv_qadami,'sotuv_narx'=>$sotuv_narx],
            "Yangi mahsulot yaratildi"
        );
        im_json('ok', "Mahsulot qo'shildi" . im_mah_retsept_ogoh($db, $new_id, $oshpaz_kerak, $qozon_rejim),
            ['id' => $new_id, 'barcode' => $barcode, 'rasm' => $rasm_path]);

    } catch (Exception $e) {
        $db->rollback();
        im_json('error', 'Xatolik: ' . $e->getMessage());
    }
}
