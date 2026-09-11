<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

// Ruxsat etilgan sozlamalar kalitlari (security whitelist)
$allowed = [
    'dukon_nomi', 'dukon_manzil', 'dukon_telefon', 'dukon_inn',
    'dukon_url', 'dukon_valyuta', 'chek_izoh', 'chek_rahmat',
    'chek_telegram', 'chek_instagram',
    'kassir_max_chegirma', 'nasiya_kun', 'ulg_min_soni', 'smena_avto_yopish',
    'xizmat_foiz',
    // Oshxona ovozli o'qish (TTS)
    'tts_yoniq', 'tts_provider', 'tts_key', 'tts_ovoz', 'tts_region',
    'tts_endpoint', 'tts_auth', 'tts_max_item',
    // AI Yordamchi (biznes tahlilchi)
    'ai_yoniq', 'ai_provider', 'ai_key', 'ai_key_claude', 'ai_key_or',
    'ai_model', 'ai_endpoint', 'ai_effort', 'ai_max_tokens', 'ai_qadam',
    'ai_kunlik', 'ai_tarix',
];

$saved = 0;
foreach ($allowed as $kalit) {
    if (!isset($_POST[$kalit])) continue;

    // API kalit maydoni bo'sh yuborilsa — eskisi saqlanib qoladi.
    // (Forma kalitni ochiq ko'rsatmaydi, bo'sh qoldirish = "tegmang")
    if (in_array($kalit, ['tts_key', 'ai_key', 'ai_key_claude', 'ai_key_or'], true)
        && trim($_POST[$kalit]) === '') continue;

    $k = mysqli_real_escape_string($link, $kalit);
    $v = mysqli_real_escape_string($link, trim($_POST[$kalit]));

    // INSERT yoki UPDATE (UPSERT)
    $exists = $db->val("SELECT id FROM im_sozlamalar WHERE kalit='$k' LIMIT 1");
    if ($exists) {
        $db->q("UPDATE im_sozlamalar SET qiymat='$v' WHERE kalit='$k'");
    } else {
        $db->insert("INSERT INTO im_sozlamalar (kalit, qiymat) VALUES ('$k', '$v')");
    }
    $saved++;
}

if (!$saved) im_json('error', 'Hech narsa saqlanmadi');
im_json('ok', "$saved ta sozlama saqlandi");
