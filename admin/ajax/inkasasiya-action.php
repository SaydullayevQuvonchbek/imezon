<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$admin_id = $im_user_id;

if (!$id || !in_array($action, ['accept', 'reject'])) im_json('error', "Noto'g'ri so'rov");

$ink = $db->row("SELECT * FROM im_inkasasiya WHERE id='$id'");
if (!$ink) im_json('error', "Topilmadi");
if ($ink['holat'] !== 'kutilmoqda') im_json('error', "Ushbu so'rov allaqachon ko'rib chiqilgan");

if ($action === 'accept') {
    $db->begin();
    try {
        $db->q("UPDATE im_inkasasiya SET holat='qabul_qilindi', admin_id='$admin_id', qabul_sana=NOW() WHERE id='$id'");
        
        $summa     = (float)$ink['summa'];
        $filial_id = (int)($ink['filial_id'] ?: 1);
        $tt        = $ink['tolov_turi'];

        // Yagona (admin markaz) kassasi mavjudligini kafolatlash — bo'lmasa yaratamiz.
        // Bunsiz, agar filial_id=0 qatori hali yo'q bo'lsa, quyidagi UPDATE 0 qatorga
        // ta'sir qilib, pul im_balans jurnaliga "kirim" bo'lib yozilib, lekin haqiqiy
        // kassa balansiga umuman qo'shilmay, sukut saqlagan holda yo'qolib qolar edi.
        $markaz_kassa = $db->row("SELECT id FROM im_kassa WHERE filial_id=0 LIMIT 1");
        if (!$markaz_kassa) {
            $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans) VALUES (0, 0, 0, 0, 0)");
        }

        // Admin markaz kassasiga pul kirimi (filial_id=0)
        if ($tt === 'naqd') {
            $db->q("UPDATE im_kassa SET naqd_balans = naqd_balans + $summa WHERE filial_id=0");
        } elseif ($tt === 'karta') {
            $db->q("UPDATE im_kassa SET karta_balans = karta_balans + $summa WHERE filial_id=0");
        } elseif ($tt === 'usd') {
            $db->q("UPDATE im_kassa SET usd_balans = usd_balans + $summa WHERE filial_id=0");
        } elseif ($tt === 'bank') {
            $db->q("UPDATE im_kassa SET bank_balans = bank_balans + $summa WHERE filial_id=0");
        }
        if ($db->affected() < 1) {
            error_log("[IMezon] YAGONA KASSA YANGILANMADI! inkasasiya_id=$id tt=$tt summa=$summa");
        }

        // Balans logi (Agar USD bo'lsa, joriy kurs bo'yicha summa_som hisoblaymiz)
        $usd_k = im_usd_kurs();
        if ($tt === 'usd') {
            $s_som = $summa * $usd_k;
            $s_usd = $summa;
        } else {
            $s_som = $summa;
            $s_usd = 0;
        }

        $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, summa_usd, manba_id, manba_tur, izoh, xodim_id, filial_id)
                VALUES('kirim','inkasasiya_kirim','$s_som','$s_usd','$id','inkasasiya',
                       'Filial #$filial_id dan inkasasiya qabul qilindi','$admin_id', 0)");

        $db->commit();
        im_json('ok', "Inkasasiya tasdiqlandi ✅");
    } catch (Exception $e) {
        $db->rollback();
        im_json('error', "Xatolik: " . $e->getMessage());
    }
} else if ($action === 'reject') {
    $db->begin();
    try {
        $db->q("UPDATE im_inkasasiya SET holat='bekor_qilindi', admin_id='$admin_id', qabul_sana=NOW() WHERE id='$id'");
        
        $summa = (float)$ink['summa'];
        $filial_id = (int)$ink['filial_id'];
        $tt = $ink['tolov_turi'];

        // Filial kassasi mavjudligini kafolatlash (bekor qilinganda pul qaytarilishi shart)
        $filial_kassa = $db->row("SELECT id FROM im_kassa WHERE filial_id='$filial_id' LIMIT 1");
        if (!$filial_kassa) {
            $db->q("INSERT INTO im_kassa (filial_id, naqd_balans, karta_balans, bank_balans, usd_balans) VALUES ('$filial_id', 0, 0, 0, 0)");
        }

        if ($tt === 'naqd') {
            $db->q("UPDATE im_kassa SET naqd_balans = naqd_balans + $summa WHERE filial_id='$filial_id'");
        } elseif ($tt === 'karta') {
            $db->q("UPDATE im_kassa SET karta_balans = karta_balans + $summa WHERE filial_id='$filial_id'");
        } elseif ($tt === 'usd') {
            $db->q("UPDATE im_kassa SET usd_balans = usd_balans + $summa WHERE filial_id='$filial_id'");
        } elseif ($tt === 'bank') {
            $db->q("UPDATE im_kassa SET bank_balans = bank_balans + $summa WHERE filial_id='$filial_id'");
        }
        if ($db->affected() < 1) {
            error_log("[IMezon] FILIAL KASSA YANGILANMADI! inkasasiya_id=$id tt=$tt summa=$summa filial_id=$filial_id");
        }
        // Balans logi
        $usd_k = im_usd_kurs();
        $s_som = ($tt === 'usd') ? $summa * $usd_k : $summa;
        $s_usd = ($tt === 'usd') ? $summa : 0;

        $db->q("INSERT INTO im_balans (tur, kategoriya, summa_som, summa_usd, manba_id, manba_tur, izoh, xodim_id, filial_id) 
                VALUES('kirim', 'boshqa_kirim', '$s_som', '$s_usd', '$id', 'inkasasiya_bekor', 'Adminga berish rad etildi qaytarildi', '$admin_id', '$filial_id')");
                
        $db->commit();
        im_json('ok', "So'rov bekor qilindi, pul do'konga qaytarildi");
    } catch(Exception $e) {
        $db->rollback();
        im_json('error', "Xatolik yuz berdi");
    }
}
