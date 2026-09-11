<?php
// ============================================================
//  IMezon — AI Yordamchi kutubxonasi (Claude API)
// ------------------------------------------------------------
//  Sozlamalar (im_sozlamalar jadvali):
//    ai_yoniq       0/1  — chat sahifasi foydalanuvchilar uchun ishlaydimi
//    ai_provider    off | on — bosh kalit (o'chirilgan bo'lsa hech narsa ishlamaydi)
//    ai_key_claude  console.anthropic.com kaliti — sof ID modellari uchun
//    ai_key_or      openrouter.ai/keys kaliti   — "provayder/model" uchun
//    ai_model       STANDART model. Model qatoriga qarab HAMMA narsa aniqlanadi:
//                     "provayder/model" (google/gemini-2.5-pro) → OpenRouter (openai sheva)
//                     sof ID (claude-sonnet-5)                  → native Anthropic
//    ai_effort      STANDART chuqurlik: low | medium | high | xhigh | max
//    ai_max_tokens  javob + fikrlash chegarasi
//    ai_qadam       agent bir savolda necha marta ma'lumot so'ray oladi
//    ai_kunlik      0/1 — dashboardda kunlik xulosa
//    ai_tarix       chatda eslab qolinadigan xabarlar soni
//
//  Chat sahifasida har SUHBAT uchun model + chuqurlik alohida tanlanadi
//  (im_ai_suhbat.model / .effort). Tanlanmagan bo'lsa — standart qiymatlar.
//
//  NIMA UCHUN rasmiy PHP SDK ishlatilmadi:
//  anthropic-ai/sdk PHP 8.1+ va Composer talab qiladi. Bu loyiha
//  PHP 7.4 da ishlaydi va composer.json umuman yo'q. Shuning uchun
//  xuddi tts_lib.php dagidek — xom cURL. Boshqa bog'liqlik yo'q.
//
//  IKKI PROVAYDER, IKKI API "SHEVASI" (im_ai_dialekt()):
//    anthropic — /v1/messages, x-api-key, `system` alohida maydon,
//                `thinking`+`output_config`, javob `content[]` bloklarida,
//                `tool_use` / `tool_result`.
//    openai    — /chat/completions (OpenRouter), Bearer, `system` oddiy
//                xabar, `reasoning`, javob `choices[0].message`,
//                `tool_calls[]` / `role:tool`.
//  Vositalar (ai_tools.php) va agent mantiqi ikkalasida ham bir xil —
//  faqat so'rov qurish va javob o'qish shevaga qarab bo'linadi.
// ============================================================

if (!defined('im_VERSION')) return;

require_once __DIR__ . '/ai_tools.php';

define('IM_AI_ENDPOINT',    'https://api.anthropic.com/v1/messages');
define('IM_AI_ENDPOINT_OR', 'https://openrouter.ai/api/v1/chat/completions');
define('IM_AI_VERSIYA',     '2023-06-01');

// ─── Sozlamalar ─────────────────────────────────────────────
function im_ai_soz() {
    static $s = null;
    if ($s !== null) return $s;
    $a = im_sozlamalar_all();
    $g = function ($k, $d = '') use ($a) { return isset($a[$k]) && $a[$k] !== '' ? $a[$k] : $d; };
    $s = [
        'yoniq'      => $g('ai_yoniq', '0') === '1',
        'on'         => $g('ai_provider', 'off') !== 'off',
        'key_claude' => $g('ai_key_claude'),
        'key_or'     => $g('ai_key_or', $g('ai_key')),   // eski yagona ai_key — migratsiyagacha zaxira
        'model'      => $g('ai_model', 'claude-sonnet-5'),
        'effort'     => $g('ai_effort', 'medium'),
        'max_tokens' => max(1024, min(64000, (int)$g('ai_max_tokens', '16000'))),
        'qadam'      => max(1, min(15, (int)$g('ai_qadam', '8'))),
        'kunlik'     => $g('ai_kunlik', '0') === '1',
        'tarix'      => max(0, min(40, (int)$g('ai_tarix', '10'))),
    ];
    return $s;
}

// Kamida bitta kalit bor va bosh kalit yoqilgan (admin sinovi uchun shu yetarli)
function im_ai_sozlangan() {
    $s = im_ai_soz();
    return $s['on'] && ($s['key_claude'] !== '' || $s['key_or'] !== '');
}

// Chat sahifasi foydalanuvchilar uchun ishlaydimi (admin sinovi o'chiq holatda ham ishlaydi)
function im_ai_ishlaydi() {
    return im_ai_soz()['yoniq'] && im_ai_sozlangan();
}

// ─── Model → sheva / kalit / provayder ──────────────────────
// BUTUN farq shu yerda: "provayder/model" ko'rinishi → OpenRouter,
// sof ID (claude-sonnet-5) → native Anthropic.
function im_ai_dialekt($model = null) {
    $m = $model !== null ? $model : im_ai_soz()['model'];
    return strpos((string)$m, '/') !== false ? 'openai' : 'anthropic';
}

// Shu model uchun API kaliti ('' — kiritilmagan)
function im_ai_kalit($model = null) {
    $s = im_ai_soz();
    return im_ai_dialekt($model) === 'openai' ? $s['key_or'] : $s['key_claude'];
}

// Model qaysi provayderning ko'rinadigan nomi
function im_ai_provayder_nomi($model = null) {
    return im_ai_dialekt($model) === 'openai' ? 'OpenRouter' : 'Claude (Anthropic)';
}

// Kalitni ekranda ko'rsatish uchun niqoblaydi: sk-or-v1-2b••••4a9f
function im_ai_key_mask($k) {
    $k = (string)$k;
    $n = strlen($k);
    if ($n === 0)  return '—';
    if ($n <= 12)  return substr($k, 0, 2) . str_repeat('•', max(1, $n - 4)) . substr($k, -2);
    return substr($k, 0, 10) . '••••' . substr($k, -4);
}

// Standart model uchun kalit yo'q bo'lsa — yumshoq ogohlantirish
function im_ai_model_ogoh() {
    $s = im_ai_soz();
    if (!$s['on']) return '';
    if (im_ai_kalit($s['model']) === '') {
        return im_ai_dialekt($s['model']) === 'openai'
            ? "Standart model \"{$s['model']}\" OpenRouter'niki, lekin OpenRouter kaliti kiritilmagan."
            : "Standart model \"{$s['model']}\" Anthropic'niki, lekin Claude kaliti kiritilmagan.";
    }
    return '';
}

// Modelni/chuqurlikni tozalash (klientdan keladi — ishonchsiz)
function im_ai_model_toza($m) {
    $m = trim((string)$m);
    return preg_match('~^[A-Za-z0-9._:@/-]{1,60}$~', $m) ? $m : '';
}
function im_ai_effort_toza($e) {
    return in_array($e, ['low', 'medium', 'high', 'xhigh', 'max'], true) ? $e : '';
}

// Chat model tanlagichi uchun qisqa ro'yxat (to'liq ro'yxat: ai-modellar.php).
// OpenRouter-birinchi — hammasi bitta OpenRouter kaliti bilan ishlaydi.
// Oxirgisi — to'g'ridan Anthropic (alohida kalit + kesh, arzonroq).
function im_ai_model_royxat() {
    return [
        ['id' => 'anthropic/claude-sonnet-5',       'nom' => 'Claude Sonnet 5',   'narx' => '$2 / $10',     'izoh' => "tavsiya — eng aniq maslahat"],
        ['id' => 'anthropic/claude-opus-5',         'nom' => 'Claude Opus 5',     'narx' => '$5 / $25',     'izoh' => "eng chuqur, sekinroq"],
        ['id' => 'google/gemini-3.8-flash',         'nom' => 'Gemini 3.8 Flash',  'narx' => '$0.75 / $3.75', 'izoh' => "tez, kuchli"],
        ['id' => 'google/gemini-3.7-flash',         'nom' => 'Gemini 3.7 Flash',  'narx' => '$0.75 / $3.75', 'izoh' => "tez"],
        ['id' => 'z-ai/glm-5.3-flash',              'nom' => 'GLM 5.3 Flash',     'narx' => '$0.08 / $0.25', 'izoh' => "juda arzon, tez"],
        ['id' => 'deepseek/deepseek-v4-flash-0731', 'nom' => 'DeepSeek V4 Flash', 'narx' => '$0.07 / $0.18', 'izoh' => "eng arzon"],
        ['id' => 'claude-sonnet-5',                 'nom' => "Claude Sonnet 5 · to'g'ridan", 'narx' => '$2 / $10', 'izoh' => "Anthropic kaliti + kesh — savol arzonroq"],
    ];
}

// effort → OpenRouter `reasoning.effort` (u faqat low/medium/high biladi)
function im_ai_effort_or($e) {
    return in_array($e, ['low', 'medium', 'high'], true) ? $e : 'high';
}

// Javobdan matn — shevaga qarab
function im_ai_javob_matn($j, $dialekt) {
    if ($dialekt === 'openai') {
        return isset($j['choices'][0]['message']['content'])
             ? (string)$j['choices'][0]['message']['content'] : '';
    }
    $t = '';
    foreach ((isset($j['content']) && is_array($j['content']) ? $j['content'] : []) as $b) {
        if (isset($b['type'], $b['text']) && $b['type'] === 'text') $t .= $b['text'];
    }
    return $t;
}

// Javobdan [kirish_token, chiqish_token]
function im_ai_javob_usage($j, $dialekt) {
    $u = isset($j['usage']) && is_array($j['usage']) ? $j['usage'] : [];
    if ($dialekt === 'openai') {
        return [(int)($u['prompt_tokens'] ?? 0), (int)($u['completion_tokens'] ?? 0)];
    }
    return [(int)($u['input_tokens'] ?? 0), (int)($u['output_tokens'] ?? 0)];
}

// ─── Bitta oddiy chaqiruv (vositasiz) ───────────────────────
// ai-test.php va kunlik xulosa shundan foydalanadi — shevaga mos
// so'rov quriladi.
function im_ai_oddiy_call($system, $user, $max_tokens = 4000, $effort = 'low', $model = null) {
    $s     = im_ai_soz();
    $model = $model !== null && $model !== '' ? $model : $s['model'];
    if (im_ai_dialekt($model) === 'openai') {
        return im_ai_call([
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'messages'   => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'reasoning'  => ['effort' => im_ai_effort_or($effort)],
        ]);
    }
    return im_ai_call([
        'model'      => $model,
        'max_tokens' => $max_tokens,
        'system'     => [['type' => 'text', 'text' => $system,
                          'cache_control' => ['type' => 'ephemeral']]],
        'messages'   => [['role' => 'user', 'content' => $user]],
        'thinking'   => ['type' => 'adaptive'],
        'output_config' => ['effort' => $effort],
    ]);
}

// ─── HTTP ───────────────────────────────────────────────────
// Timeout uzun: fikrlaydigan model + bir necha vosita chaqiruvi
// bir daqiqadan oshishi mumkin. Qisqa timeout qo'ysak, javob
// tayyor bo'lgan payt uzilib ketadi va pul bekorga ketadi.
function im_ai_http($url, array $headers, $body, $timeout = 180) {
    if (!function_exists('curl_init')) {
        return ['code' => 0, 'body' => '', 'xato' => 'PHP cURL kengaytmasi yoqilmagan'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $out  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($out === false) {
        return ['code' => 0, 'body' => '', 'xato' => 'Tarmoq xatosi: ' . $err];
    }
    return ['code' => $code, 'body' => $out, 'xato' => null];
}

// ─── Bitta API chaqiruvi ────────────────────────────────────
// Qaytaradi: ['ok'=>bool, 'javob'=>array|null, 'xato'=>string|null, 'xom'=>string]
function im_ai_call(array $payload, $fallback = true) {
    // Sheva/kalit/URL — payload'dagi model qatoridan aniqlanadi
    $model   = isset($payload['model']) ? $payload['model'] : im_ai_soz()['model'];
    $dialekt = im_ai_dialekt($model);
    $key     = im_ai_kalit($model);

    if ($key === '') {
        $p = $dialekt === 'openai' ? 'OpenRouter' : 'Claude (Anthropic)';
        return ['ok' => false, 'javob' => null, 'xom' => '',
                'xato' => "$p kaliti Sozlamalarda kiritilmagan (model: $model)"];
    }

    if ($dialekt === 'openai') {
        // OpenRouter — OpenAI-mos. Bearer + ixtiyoriy reyting sarlavhalari.
        $url = IM_AI_ENDPOINT_OR;
        $hdr = [
            'authorization: Bearer ' . $key,
            'content-type: application/json',
            'http-referer: https://imezon.uz',
            'x-title: IMezon',
        ];
        $fallback = false;   // server-side fallback faqat Anthropic'da bor
    } else {
        $url = IM_AI_ENDPOINT;
        $hdr = [
            'x-api-key: ' . $key,
            'anthropic-version: ' . IM_AI_VERSIYA,
            'content-type: application/json',
        ];
        if ($fallback) {
            // Model xavfsizlik sababli javob bermay qolsa (stop_reason=refusal),
            // server o'zi mos modelga o'tkazadi. Biznes tahlilida deyarli
            // uchramaydi, lekin bo'lsa — savol javobsiz qolmaydi.
            $hdr[] = 'anthropic-beta: server-side-fallback-2026-07-01';
            $payload['fallbacks'] = 'default';
        }
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $r    = im_ai_http($url, $hdr, $body);

    if ($r['xato']) return ['ok' => false, 'javob' => null, 'xato' => $r['xato'], 'xom' => ''];

    $j = json_decode($r['body'], true);

    if ($r['code'] < 200 || $r['code'] >= 300) {
        $msg = is_array($j) && isset($j['error']['message'])
             ? $j['error']['message'] : substr($r['body'], 0, 400);

        // Beta bayrog'i bu hisobda mavjud bo'lmasa — bir marta
        // usiz qayta urinamiz. Aks holda butun funksiya ishlamay
        // qolardi, holbuki fallback shunchaki qo'shimcha qulaylik.
        if ($fallback && $r['code'] === 400
            && (stripos($msg, 'fallback') !== false || stripos($msg, 'beta') !== false)) {
            // Sarlavhani olib tashlash yetmaydi — tanadagi "fallbacks"
            // maydonini ham OLIB TASHLASH shart, aks holda qayta urinish
            // xuddi shu 400 ni oladi va funksiya umuman ishlamay qoladi.
            unset($payload['fallbacks']);
            return im_ai_call($payload, false);
        }

        return ['ok' => false, 'javob' => null,
                'xato' => 'API xatosi (' . $r['code'] . '): ' . $msg,
                'xom'  => substr($r['body'], 0, 1000)];
    }

    if (!is_array($j)) {
        return ['ok' => false, 'javob' => null, 'xato' => 'Javob JSON emas',
                'xom' => substr($r['body'], 0, 500)];
    }
    return ['ok' => true, 'javob' => $j, 'xato' => null, 'xom' => ''];
}

// ─── Tizim ko'rsatmasi ──────────────────────────────────────
// IKKI blok: birinchisi O'ZGARMAS (kesh shu yerda tugaydi),
// ikkinchisi kunlik o'zgaradigan kontekst. Agar sana birinchi
// blokka yozilsa, kesh har kuni buzilardi va har savol qimmat
// tushardi (tools + ko'rsatma ~2000 token).
function im_ai_system_static() {
    return <<<TXT
Sen — IMezon oshxona/restoran boshqaruv tizimining biznes tahlilchisisan.
Egasi (admin) senga savol beradi, sen esa BAZADAGI HAQIQIY raqamlar asosida javob berasan.

## Asosiy qoidalar

1. HECH QACHON raqam o'ylab topma. Har bir son vosita (tool) javobidan olinishi shart.
   Vosita ma'lumot bermasa yoki xato qaytarsa — "bu ma'lumot bazada yo'q" deb ayt,
   taxmin qilma, o'zingdan raqam qo'shma. Yaxlitlaganда ham asl raqamга yaqin bo'lsin.
2. Vositalarni O'ZING tanlab chaqir. Savol tor bo'lsa ham javobni BOYIT — tegishli
   boshqa vositalarni ham chaqir. "Tushum qancha?" so'ralsa: sotuv + foyda; tushum
   past bo'lsa sabab uchun mahsulot_reyting yoki vaqt_tahlil. Tekshirganingni AYT —
   hech nima topilmasa ham ("Anomaliya tekshirdim — shubhali chek yo'q").
   SOLISHTIRSANG — ikkala davr (yoki taom) raqamini ALOHIDA vosita bilan ol. "O'tgan
   haftadan 12% kam" deyishdan oldin o'tgan hafta raqamini chaqirgan bo'l.
3. Har doim QAYSI DAVR haqida gapirayotganingni ayt ("1-28 avgust", "bugun").
4. Pul — so'mda. Yirik sonlarni bo'sh joy bilan ajrat: 12 450 000 so'm.
   Foizni bir xonagacha yaxlitla: 23.4%.
5. Ma'lumotni takrorlama — undan MA'NO chiqar. Bo'sh gap yo'q: har jumla yangi fakt
   yoki aniq harakat. "Ehtiyot bo'ling", "nazorat qiling" kabi umumiy gaplar YO'Q.
6. Markdown ishlat: **qalin**, jadval, ro'yxat. Sarlavhalarni (#) kam ishlat.
7. O'zbek tilida, lotin alifbosida yoz. Foydalanuvchi rus/ingliz tilida yozsa —
   o'sha tilda javob ber.

## Javob tuzilishi (shu tartibda; bo'sh bo'limni butunlay tashla)

**Javob** — savolga to'g'ridan, 1-2 jumla, aniq raqam va davr bilan.
**Yaxshi** — nima to'g'ri ketyapti (bor bo'lsa). 1-2 punkt, raqam bilan.
**Muammo** — nima yomon yoki xavfli. Eng muhimi birinchi, har biri raqam bilan.
**Nima qilish** — 1-3 ta ANIQ harakat. Umumiy emas: "sotuvni oshiring" emas —
  "Payshanba oqshom osh 45% kam ketyapti, o'sha vaqtga 10% chegirma qo'ying".
  Iloji bo'lsa: kim, nima, qachon, qancha. Tavsiya shu oshxonaning REAL holatiga mos bo'lsin.
**Keyingi** — foydalanuvchi keyin nimani so'rashi mumkinligini TAXMIN qil va o'zing TAKLIF et:
  "Xohlasangiz qaysi ofitsant kam sotayotganini ko'ray" / "Osh retsepti tannarxini
  qayta hisoblab, narx oshirish kerakmi — tekshiraymi?". Foydalanuvchi "ha" desa — o'shani bajar.

Har bo'lim 1-3 punkt, ortiqchasi yo'q. Jadval — faqat ko'p qatorli taqqoslash uchun.

## Tahlil qilishda

- Bitta raqam ma'no bermaydi — SOLISHTIR: o'tgan davr, o'rtacha yoki boshqa taom bilan.
- Sabab ko'rsat: tushum tushgan bo'lsa, qaysi taom yoki qaysi kun tushirganini top.
- Foyda haqida gapirganda TANNARX va HARAJATNI ham ko'rsat. "Sotuv ko'p" ≠ "foyda ko'p".
- Anomaliyalarni AYBLOV sifatida taqdim etma. "Kassir o'g'irlagan" emas —
  "Bu cheklarni tekshirib ko'ring, sababi bo'lishi mumkin".
- Ma'lumot kam bo'lsa (bir necha kunlik sotuv), xulosaning ishonchsizligini ochiq ayt —
  lekin baribir bor ma'lumotdan foydali xulosa chiqar.

## Bilishing kerak bo'lgan tizim tuzilishi

- **Oshxona taomlari** — `oshpaz_kerak=1` yoki `retsept_avto=1` mahsulotlar. Ular
  vitrinada zaxira sifatida turmaydi, xomashyosi retsept bo'yicha yechiladi.
  Tayyor tovar (suv, gazak) esa oddiy qoldiqdan sotiladi.
- **Buyurtma yo'li**: ofitsant buyurtma yaratadi → oshpaz qabul qiladi
  (`pishirilmoqda`) → tayyor deb belgilaydi → kassir chek yopadi (`im_sotuvlar`).
  Faqat kassir yopgan chek TUSHUM hisoblanadi.
- **Sof foyda** = brutto foyda − harajatlar − maoshlar. Maosh alohida jadvalda
  (`im_maosh_tarixi`) saqlanadi, lekin hisobga olinadi. Dashboard ham xuddi shu
  formuladan foydalanadi — sening raqamlaring u bilan mos kelishi kerak.
- **Tannarx** ikki xil: sotuvda yozib qo'yilgani (tarixiy) va retsept bo'yicha
  bugungi narxda qayta hisoblangani (`taom_tannarx` vositasi). Ikkinchisi
  "narx oshdi, hali ham foydalimi?" savoli uchun.
TXT;
}

function im_ai_system_dinamik($db, $filial_id = 0) {
    $bugun = date('Y-m-d');
    $kun   = ['Sunday'=>'Yakshanba','Monday'=>'Dushanba','Tuesday'=>'Seshanba',
              'Wednesday'=>'Chorshanba','Thursday'=>'Payshanba','Friday'=>'Juma',
              'Saturday'=>'Shanba'];
    $bugun_kun = isset($kun[date('l')]) ? $kun[date('l')] : date('l');

    $fil = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY tartib, id");
    $kat = $db->rows("SELECT id, nomi FROM im_kategoriyalar WHERE status=1 ORDER BY nomi");
    $chegara = $db->row("SELECT MIN(DATE(sana)) dan, MAX(DATE(sana)) gacha, COUNT(*) n
                         FROM im_sotuvlar WHERE holat='aktiv'");

    $t = "## Joriy kontekst\n\n";
    $t .= "- Bugun: $bugun ($bugun_kun), vaqt zonasi Asia/Tashkent (UTC+5)\n";
    $t .= "- Do'kon: " . im_sozlama('dukon_nomi', 'IMezon') . "\n";
    $t .= "- USD kursi: " . number_format(im_usd_kurs(), 0, '.', ' ') . " so'm\n";
    $t .= "- Xizmat haqi: " . im_sozlama('xizmat_foiz', '0') . "%, kassir chegirma limiti: "
        . im_sozlama('kassir_max_chegirma', '0') . "%\n";

    if ($chegara && (int)$chegara['n'] > 0) {
        $t .= "- Bazadagi sotuvlar: {$chegara['dan']} dan {$chegara['gacha']} gacha, "
            . "jami {$chegara['n']} ta chek\n";
        // Ma'lumot juda kam bo'lsa modelni ogohlantiramiz — aks holda
        // 6 ta chekdan "tendensiya" yasab yuboradi.
        if ((int)$chegara['n'] < 100) {
            $t .= "- OGOHLANTIRISH: cheklar juda kam. Tendensiya yoki prognoz haqida "
                . "gapirganda buni ochiq ayt.\n";
        }
    } else {
        $t .= "- Bazada hali sotuv yo'q.\n";
    }

    if ($fil) {
        $t .= "- Filiallar: " . implode(', ', array_map(function ($f) {
            return "{$f['nomi']} (id={$f['id']})";
        }, $fil)) . "\n";
    } else {
        $t .= "- Filial yagona (filial_id=0 barchasini bildiradi).\n";
    }
    if ($filial_id > 0) {
        $t .= "- Foydalanuvchi filiali: $filial_id. Boshqacha so'ralmasa shu filialni ishlat.\n";
    }
    if ($kat) {
        $t .= "- Kategoriyalar: " . implode(', ', array_map(function ($k) {
            return "{$k['nomi']} (id={$k['id']})";
        }, array_slice($kat, 0, 40))) . "\n";
    }
    return $t;
}

// ─── Raqam solishtiruvi (gallyutsinatsiyaga qarshi) ────────
// FAKT sifatida aytilgan yirik pul raqami vosita natijalarida bormi?
// Qaytaradi: topilmagan raqamlar (ko'pi bilan 3 ta). Bo'sh = hammasi joyida.
// BloklAMAYDI — belgilaydi. Proyeksiya/hisob JUMLALARI (~, "oyiga", "o'rtacha",
// "prognoz", "=") BUTUNLAY o'tkaziladi — modeldan hisoblash kutiladi.
function im_ai_raqam_tekshir($matn, array $natijalar) {
    if (!$natijalar || $matn === '') return [];

    // Haystack: vosita natijalaridagi sonlar (>= 1000) + xavfsiz derivatsiya
    // (juft yig'indi/ayirma — "sof = brutto − harajat − maosh").
    preg_match_all('/\d+(?:\.\d+)?/', json_encode($natijalar, JSON_UNESCAPED_UNICODE), $hm);
    $sonlar = [];
    foreach ($hm[0] as $x) { $f = (float)$x; if ($f >= 1000) $sonlar[(string)$f] = $f; }
    $sonlar = array_values($sonlar);
    if (!$sonlar) return [];

    $barcha = $sonlar;
    $n = min(count($sonlar), 40);
    for ($i = 0; $i < $n; $i++) {
        for ($k = $i + 1; $k < $n; $k++) {
            $barcha[] = $sonlar[$i] + $sonlar[$k];
            $barcha[] = abs($sonlar[$i] - $sonlar[$k]);
        }
    }
    $mos = function ($a) use ($barcha) {
        foreach ($barcha as $h) {
            if ($h > 0 && abs($a - $h) / max($a, $h) < 0.02) return true;
        }
        return false;
    };

    // Proyeksiya/hisob belgisi bo'lgan jumla butunlay tekshirilmaydi
    $proy = '/[~≈=]|taxmin|prognoz|kutil|bashorat|sur\S?at|bo\S?lishi mumkin|'
          . 'oyiga|yiliga|kuniga|haftaga|o\S?rtacha|agar\s|deylik|farazan/ui';

    $topilmadi = [];
    foreach (preg_split('/(?<=[.!?:\n])\s+/u', $matn) as $jumla) {
        if (trim($jumla) === '' || preg_match($proy, $jumla)) continue;

        $nomzod = [];
        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(mlrd|milliard|mln|million)/ui', $jumla, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $v = (float)str_replace(',', '.', $x[1]);
                $mult = (stripos($x[2], 'lrd') !== false || stripos($x[2], 'illiard') !== false) ? 1e9 : 1e6;
                $nomzod[trim($x[0])] = $v * $mult;
            }
        }
        if (preg_match_all('/(?<![.\d])(\d{1,3}(?:[ \x{00A0},]\d{3})+|\d{6,})(?!\s*%)(?![.\d])/u', $jumla, $rm, PREG_SET_ORDER)) {
            foreach ($rm as $x) {
                $v = (float)preg_replace('/\D/', '', $x[1]);
                if ($v >= 100000) $nomzod[trim($x[1])] = $v;
            }
        }
        foreach ($nomzod as $xom => $qiymat) {
            if (!$mos($qiymat) && !in_array($xom, $topilmadi, true)) {
                $topilmadi[] = $xom;
                if (count($topilmadi) >= 3) return $topilmadi;
            }
        }
    }
    return $topilmadi;
}

// ─── AGENT: savol → vositalar → javob ───────────────────────
// $opt: filial_id, model, effort (oxirgi ikkitasi — suhbat tanlovi;
//       bo'sh bo'lsa standart qiymatlar).
// Qaytaradi:
//   ['ok','javob','model','effort','vositalar','tokens_in','tokens_out','ms','xato','tarix']
function im_ai_agent($db, $savol, array $tarix = [], array $opt = []) {
    $s  = im_ai_soz();
    $t0 = microtime(true);

    $model  = im_ai_model_toza(isset($opt['model'])  ? $opt['model']  : '');
    $model  = $model  !== '' ? $model  : $s['model'];
    $effort = im_ai_effort_toza(isset($opt['effort']) ? $opt['effort'] : '');
    $effort = $effort !== '' ? $effort : $s['effort'];
    $dialekt = im_ai_dialekt($model);

    // Bo'sh javob — muvaffaqiyatli javob bilan BIR XIL tuzilishda bo'lishi shart
    // (chaqiruvchi $r['tokens_in'] kabi maydonlarni tekshirmasdan o'qiydi).
    $bosh = ['ok' => false, 'javob' => '', 'model' => $model, 'effort' => $effort,
             'vositalar' => [], 'tokens_in' => 0, 'tokens_out' => 0, 'ms' => 0,
             'tarix' => $tarix, 'xato' => null];

    if (!$s['on']) {
        return ['xato' => "AI o'chirilgan — Sozlamalar → AI Yordamchi"] + $bosh;
    }
    if (im_ai_kalit($model) === '') {
        $p = $dialekt === 'openai' ? 'OpenRouter' : 'Claude (Anthropic)';
        return ['xato' => "$p kaliti Sozlamalarda kiritilmagan (model: $model)"] + $bosh;
    }

    $filial_id = isset($opt['filial_id']) ? (int)$opt['filial_id'] : 0;

    $messages = $tarix;
    $messages[] = ['role' => 'user', 'content' => (string)$savol];

    if ($dialekt === 'openai') {
        // OpenRouter: system — bitta oddiy xabar; tools — `function`
        // o'ramida; fikrlash — `reasoning.effort`. Prompt keshini bu
        // yerda biz boshqarmaymiz (OpenRouter o'zi qo'yadi yoki yo'q).
        $payload = [
            'model'      => $model,
            'max_tokens' => $s['max_tokens'],
            'messages'   => array_merge(
                [['role' => 'system',
                  'content' => im_ai_system_static() . "\n\n" . im_ai_system_dinamik($db, $filial_id)]],
                $messages
            ),
            'tools'       => im_ai_vositalar_openai(),
            'tool_choice' => 'auto',
            'reasoning'   => ['effort' => im_ai_effort_or($effort)],
            // Faktik ish — ijodkorlik kerak emas. (Model qo'llamasa OpenRouter tashlaydi.)
            'temperature' => 0,
        ];
    } else {
        $payload = [
            'model'      => $model,
            'max_tokens' => $s['max_tokens'],
            'system'     => [
                // Kesh shu blokdan keyin tugaydi: tools + o'zgarmas ko'rsatma.
                ['type' => 'text', 'text' => im_ai_system_static(),
                 'cache_control' => ['type' => 'ephemeral']],
                ['type' => 'text', 'text' => im_ai_system_dinamik($db, $filial_id)],
            ],
            'messages'   => $messages,
            'tools'      => im_ai_vositalar(),
            'thinking'   => ['type' => 'adaptive'],
            'output_config' => ['effort' => $effort],
        ];
    }

    $vositalar  = [];   // [{nom, args}] — meta / chiplar uchun
    $natijalar  = [];   // [{nom, args, natija}] — MANBA paneli + raqam tekshiruvi uchun
    $tin = 0; $tout = 0;
    $matn = '';
    $xato = null;

    for ($qadam = 0; $qadam < $s['qadam']; $qadam++) {
        $r = im_ai_call($payload);
        if (!$r['ok']) { $xato = $r['xato']; break; }

        $j = $r['javob'];
        list($qi, $qo) = im_ai_javob_usage($j, $dialekt);
        $tin += $qi; $tout += $qo;

        // ── Javobni YAGONA ichki shaklga keltiramiz ──
        //   $stop:    'tool' | 'end' | 'refusal' | 'max' | 'other'
        //   $tool_qs: [ ['id'=>, 'nom'=>, 'args'=>[]] ]
        //   $xom:     messages'ga qaytariladigan assistant navbati
        $bu_matn = '';
        $tool_qs = [];
        $refusal_izoh = '';

        if ($dialekt === 'openai') {
            $m   = isset($j['choices'][0]['message']) ? $j['choices'][0]['message'] : [];
            $fin = isset($j['choices'][0]['finish_reason']) ? $j['choices'][0]['finish_reason'] : '';
            $bu_matn = isset($m['content']) ? (string)$m['content'] : '';
            $stop = $fin === 'tool_calls'     ? 'tool'
                  : ($fin === 'length'         ? 'max'
                  : ($fin === 'content_filter' ? 'refusal'
                  : ($fin === 'stop'           ? 'end' : 'other')));

            if (!empty($m['tool_calls']) && is_array($m['tool_calls'])) {
                foreach ($m['tool_calls'] as $tc) {
                    $tool_qs[] = [
                        'id'   => isset($tc['id']) ? $tc['id'] : '',
                        'nom'  => isset($tc['function']['name']) ? $tc['function']['name'] : '',
                        'args' => isset($tc['function']['arguments'])
                                ? (json_decode($tc['function']['arguments'], true) ?: [])
                                : [],
                    ];
                }
            }
            // Assistant navbatini toza qaytaramiz. `reasoning_details`
            // bo'lsa saqlaymiz — Claude-via-OpenRouter uni keyingi
            // qadamda kutishi mumkin.
            $xom = ['role' => 'assistant', 'content' => isset($m['content']) ? $m['content'] : ''];
            if (!empty($m['tool_calls']))        $xom['tool_calls'] = $m['tool_calls'];
            if (!empty($m['reasoning_details'])) $xom['reasoning_details'] = $m['reasoning_details'];
            if (isset($m['refusal'])) $refusal_izoh = (string)$m['refusal'];
        } else {
            $content = isset($j['content']) && is_array($j['content']) ? $j['content'] : [];
            foreach ($content as $b) {
                if (isset($b['type'], $b['text']) && $b['type'] === 'text') $bu_matn .= $b['text'];
            }
            $sr   = isset($j['stop_reason']) ? $j['stop_reason'] : '';
            $stop = $sr === 'tool_use'   ? 'tool'
                  : ($sr === 'max_tokens' ? 'max'
                  : ($sr === 'refusal'    ? 'refusal' : 'end'));

            foreach ($content as $b) {
                if (!isset($b['type']) || $b['type'] !== 'tool_use') continue;
                $tool_qs[] = [
                    'id'   => isset($b['id']) ? $b['id'] : '',
                    'nom'  => isset($b['name']) ? $b['name'] : '',
                    'args' => (isset($b['input']) && is_array($b['input'])) ? $b['input'] : [],
                ];
            }
            // MUHIM: kontent O'ZGARTIRILMASDAN qaytadi — fikrlash (thinking)
            // bloklari va imzosi ham, aks holda keyingi qadam imzoni topmaydi.
            $xom = ['role' => 'assistant', 'content' => $content];
            if (isset($j['stop_details']['explanation'])) $refusal_izoh = $j['stop_details']['explanation'];
        }

        if (trim($bu_matn) !== '') $matn = $bu_matn;

        if ($stop === 'refusal') {
            $xato = 'Model bu savolga javob bermadi' . ($refusal_izoh !== '' ? ': ' . $refusal_izoh : '');
            break;
        }

        $payload['messages'][] = $xom;

        if ($stop !== 'tool') {
            if ($stop === 'max') {
                $matn .= "\n\n_(Javob uzunlik chegarasiga yetdi — savolni toraytiring "
                       . "yoki Sozlamalarda `ai_max_tokens` ni oshiring.)_";
            }
            break;
        }
        if (!$tool_qs) break;   // tool dedi-yu, blok yo'q — cheksiz aylanmaslik uchun

        // ── Vositalarni bajaramiz ──
        //  openai:   har natija ALOHIDA {role:tool} xabari
        //  anthropic: BARCHA natijalar BITTA user xabarida (parallel
        //             chaqiruv shu tarzda saqlanadi)
        $an_natija = [];
        foreach ($tool_qs as $tc) {
            $out  = im_ai_vosita_bajar($db, $tc['nom'], $tc['args']);
            $vositalar[] = ['nom' => $tc['nom'], 'args' => $tc['args']];
            $natijalar[] = ['nom' => $tc['nom'], 'args' => $tc['args'], 'natija' => $out];
            $ojs  = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($dialekt === 'openai') {
                $payload['messages'][] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'],
                    'content'      => $ojs,
                ];
            } else {
                $an_natija[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $tc['id'],
                    'content'     => $ojs,
                    'is_error'    => isset($out['xato']),
                ];
            }
        }
        if ($dialekt !== 'openai' && $an_natija) {
            $payload['messages'][] = ['role' => 'user', 'content' => $an_natija];
        }
    }

    $ms = (int)round((microtime(true) - $t0) * 1000);

    if ($xato === null && trim($matn) === '') {
        $xato = "Model matnli javob qaytarmadi (qadam chegarasi: {$s['qadam']}). "
              . "Savolni soddalashtiring yoki Sozlamalarda `ai_qadam` ni oshiring.";
    }

    // ── Raqam solishtiruvi: javobdagi yirik pul raqamlari vosita
    //    natijalarida bormi? Yo'q bo'lsa — belgilaymiz (bloklamaymiz).
    $ogohlantirish = ($xato === null && $matn !== '')
        ? im_ai_raqam_tekshir($matn, $natijalar) : [];

    return [
        'ok'            => $xato === null,
        'javob'         => $matn,
        'model'         => $model,
        'effort'        => $effort,
        'vositalar'     => $vositalar,
        'natijalar'     => $natijalar,      // MANBA paneli uchun (persist QILINMAYDI)
        'ogohlantirish' => $ogohlantirish,  // topilmagan raqamlar
        'tokens_in'     => $tin,
        'tokens_out'    => $tout,
        'ms'            => $ms,
        'xato'          => $xato,
        // Keyingi savolda yuboriladigan suhbat tarixi (vosita
        // natijalarisiz — ular kontekstni tez to'ldiradi va
        // eskirgan raqamlar yangi savolga aralashib ketadi).
        'tarix'      => array_merge($tarix, [
            ['role' => 'user',      'content' => (string)$savol],
            ['role' => 'assistant', 'content' => $matn !== '' ? $matn : '—'],
        ]),
    ];
}

// ─── Jurnalga yozish ────────────────────────────────────────
// `vositalar` ustuniga endi TO'LIQ natijalar yoziladi ({nom,args,natija}) —
// "bu javob qaysi raqamga asoslangan" savoliga to'liq javob (audit).
function im_ai_jurnal($db, array $r, $savol, $xodim_id, $filial_id) {
    global $link;
    $e = function ($v) use ($link) { return mysqli_real_escape_string($link, (string)$v); };

    $vos = !empty($r['natijalar']) ? $r['natijalar']
         : (isset($r['vositalar']) ? $r['vositalar'] : []);
    $vos_j = json_encode($vos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen($vos_j) > 55000) $vos_j = substr($vos_j, 0, 55000) . '…]';   // TEXT chegarasi

    $db->q(
        "INSERT INTO im_ai_log (xodim_id, filial_id, savol, javob, vositalar, model,
                                tokens_in, tokens_out, ms, xato)
         VALUES (" . (int)$xodim_id . ", " . (int)$filial_id . ",
                 '" . $e(mb_substr($savol, 0, 2000)) . "',
                 '" . $e(mb_substr(isset($r['javob']) ? $r['javob'] : '', 0, 60000)) . "',
                 '" . $e($vos_j) . "',
                 '" . $e(isset($r['model']) && $r['model'] !== '' ? $r['model'] : im_ai_soz()['model']) . "',
                 " . (int)$r['tokens_in'] . ", " . (int)$r['tokens_out'] . ", " . (int)$r['ms'] . ",
                 " . ($r['xato'] ? "'" . $e(mb_substr($r['xato'], 0, 500)) . "'" : 'NULL') . ")"
    );
}

// ============================================================
//  CHAT SUHBATLARI — tarix paneli
// ------------------------------------------------------------
//  im_ai_log audit uchun (per-savol, o'zgarmaydi). im_ai_suhbat
//  esa butun oqimni bitta JSON qatorida saqlaydi — sahifa tiklanadi
//  va yon panelda ro'yxat chiqadi. Foydalanuvchi faqat o'zinikini
//  ko'radi (xodim_id sharti HAR so'rovda).
// ============================================================

function im_ai_suhbat_id_yasa() {
    return bin2hex(random_bytes(16));   // 32 hex belgi
}

// Modeldan/URL'dan kelgan id — faqat hex, aks holda bo'sh
function im_ai_suhbat_id_toza($id) {
    $id = strtolower((string)$id);
    return preg_match('/^[a-f0-9]{8,40}$/', $id) ? $id : '';
}

// Foydalanuvchining suhbatlari (yangisi tepada)
function im_ai_suhbatlar($db, $xodim_id, $limit = 50) {
    $xodim_id = (int)$xodim_id;
    $limit    = max(1, min(200, (int)$limit));
    return $db->rows(
        "SELECT id, sarlavha, soni, model, effort, updated_at
         FROM im_ai_suhbat WHERE xodim_id=$xodim_id
         ORDER BY updated_at DESC LIMIT $limit"
    );
}

// Bitta suhbat — xabarlari bilan (dekodlangan). Egasi bo'lmasa null.
function im_ai_suhbat_ol($db, $id, $xodim_id) {
    $id = im_ai_suhbat_id_toza($id);
    if ($id === '') return null;
    $r = $db->row("SELECT * FROM im_ai_suhbat
                   WHERE id='$id' AND xodim_id=" . (int)$xodim_id . " LIMIT 1");
    if (!$r) return null;
    $r['xabarlar'] = $r['xabarlar'] ? (json_decode($r['xabarlar'], true) ?: []) : [];
    return $r;
}

// Saqlangan xabarlardan modelga yuboriladigan tarix.
// Talablar: rollar NAVBATMA-NAVBAT, 'user' bilan boshlanadi, oxirgi N ta.
function im_ai_suhbat_tarix(array $xabarlar, $limit) {
    $limit = (int)$limit;
    if ($limit <= 0) return [];

    $msgs = [];
    foreach ($xabarlar as $x) {
        $rol = (isset($x['rol']) && $x['rol'] === 'assistant') ? 'assistant' : 'user';
        // Xato javob tarixga kirmasin (model uni "fakt" deb oladi) — u bilan
        // birga muvaffaqiyatsiz savolni ham olib tashlaymiz, aks holda ikkita
        // 'user' ketma-ket qolib Anthropic 400 beradi.
        if ($rol === 'assistant' && !empty($x['xato'])) {
            $oxirgi = $msgs ? $msgs[count($msgs) - 1] : null;
            if ($oxirgi && $oxirgi['role'] === 'user') array_pop($msgs);
            continue;
        }
        $matn = isset($x['matn']) ? (string)$x['matn'] : '';
        $yangi = ['role' => $rol, 'content' => $matn !== '' ? $matn : '—'];
        // Ketma-ket bir xil rol — oxirgisini qoldiramiz (navbatlashuv shart)
        if ($msgs && $msgs[count($msgs) - 1]['role'] === $rol) {
            $msgs[count($msgs) - 1] = $yangi;
        } else {
            $msgs[] = $yangi;
        }
    }

    if (count($msgs) > $limit) $msgs = array_slice($msgs, -$limit);
    while ($msgs && $msgs[0]['role'] !== 'user') array_shift($msgs);
    return $msgs;
}

// Savol+javobni suhbatga qo'shadi (yo'q bo'lsa yaratadi). Suhbat_id qaytadi.
function im_ai_suhbat_yoz($db, $id, $xodim_id, $filial_id, $savol, array $r) {
    global $link;
    $xodim_id  = (int)$xodim_id;
    $filial_id = (int)$filial_id;
    $e = function ($v) use ($link) { return mysqli_real_escape_string($link, (string)$v); };

    $id     = im_ai_suhbat_id_toza($id);
    $mavjud = $id !== '' ? $db->row(
        "SELECT id, xabarlar, soni FROM im_ai_suhbat
         WHERE id='$id' AND xodim_id=$xodim_id LIMIT 1") : null;

    $xabarlar = ($mavjud && $mavjud['xabarlar'])
              ? (json_decode($mavjud['xabarlar'], true) ?: []) : [];

    $vos = [];
    foreach ((isset($r['vositalar']) && is_array($r['vositalar']) ? $r['vositalar'] : []) as $v) {
        $vos[] = is_array($v) ? (isset($v['nom']) ? $v['nom'] : '') : (string)$v;
    }

    // Shu javob qaysi model/chuqurlik bilan yasalgan
    $model  = im_ai_model_toza(isset($r['model'])  ? $r['model']  : '');
    $model  = $model  !== '' ? $model  : im_ai_soz()['model'];
    $effort = im_ai_effort_toza(isset($r['effort']) ? $r['effort'] : '');
    $effort = $effort !== '' ? $effort : im_ai_soz()['effort'];

    $xabarlar[] = ['rol' => 'user', 'matn' => (string)$savol];
    $xabarlar[] = [
        'rol'       => 'assistant',
        'matn'      => isset($r['javob']) ? (string)$r['javob'] : '',
        'model'     => $model,
        'vositalar' => array_values(array_unique(array_filter($vos))),
        'ogoh'      => (isset($r['ogohlantirish']) && is_array($r['ogohlantirish'])) ? $r['ogohlantirish'] : [],
        'ms'        => (int)(isset($r['ms']) ? $r['ms'] : 0),
        'tin'       => (int)(isset($r['tokens_in'])  ? $r['tokens_in']  : 0),
        'tout'      => (int)(isset($r['tokens_out']) ? $r['tokens_out'] : 0),
        'xato'      => (!empty($r['xato'])) ? (string)$r['xato'] : null,
    ];
    // Juda uzun suhbat — JSON qatorini cheklaymiz
    if (count($xabarlar) > 80) $xabarlar = array_slice($xabarlar, -80);

    $xj = $e(json_encode($xabarlar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $me = $e($model);  $ee = $e($effort);
    $soni = (int)($mavjud ? $mavjud['soni'] : 0) + 1;

    if ($mavjud) {
        $db->q("UPDATE im_ai_suhbat SET xabarlar='$xj', soni=$soni,
                       model='$me', effort='$ee', updated_at=CURRENT_TIMESTAMP
                WHERE id='$id' AND xodim_id=$xodim_id");
        return $id;
    }

    $yangi = $id !== '' ? $id : im_ai_suhbat_id_yasa();
    $sarlavha = $e(mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$savol)), 0, 160));
    $db->q("INSERT INTO im_ai_suhbat (id, xodim_id, filial_id, model, effort, sarlavha, xabarlar, soni)
            VALUES ('" . $e($yangi) . "', $xodim_id, $filial_id, '$me', '$ee', '$sarlavha', '$xj', $soni)");
    return $yangi;
}

function im_ai_suhbat_ochir($db, $id, $xodim_id) {
    $id = im_ai_suhbat_id_toza($id);
    if ($id === '') return false;
    $db->q("DELETE FROM im_ai_suhbat WHERE id='$id' AND xodim_id=" . (int)$xodim_id);
    return true;
}

// ============================================================
//  KUNLIK XULOSA
// ------------------------------------------------------------
//  Bu yerda agent aylanmasi ISHLATILMAYDI. Kunlik xulosaga
//  kerakli raqamlar har doim bir xil — ularni o'zimiz yig'ib,
//  modelga BITTA chaqiruvda beramiz. Sabab: arzon (bitta so'rov),
//  tez, va har kuni bir xil tuzilishda chiqadi.
// ============================================================

function im_ai_kunlik_kontekst($db, $sana, $filial_id = 0) {
    $kecha = date('Y-m-d', strtotime($sana . ' -1 day'));
    $hafta = date('Y-m-d', strtotime($sana . ' -6 days'));
    $f     = ['filial_id' => $filial_id];

    return [
        'sana'            => $sana,
        'bugun'           => im_ait_sotuv_xulosa($db, $f + ['dan' => $sana,  'gacha' => $sana, 'guruh' => 'yoq']),
        'kecha'           => im_ait_sotuv_xulosa($db, $f + ['dan' => $kecha, 'gacha' => $kecha, 'guruh' => 'yoq']),
        'hafta_dinamika'  => im_ait_sotuv_xulosa($db, $f + ['dan' => $hafta, 'gacha' => $sana, 'guruh' => 'kun']),
        'foyda_bugun'     => im_ait_foyda_xulosa($db, $f + ['dan' => $sana,  'gacha' => $sana]),
        'harajat_bugun'   => im_ait_harajat_xulosa($db, $f + ['dan' => $sana, 'gacha' => $sana, 'guruh' => 'tur']),
        'top_taomlar'     => im_ait_mahsulot_reyting($db, $f + ['dan' => $sana, 'gacha' => $sana, 'tartib' => 'foyda', 'limit' => 8]),
        'yomon_taomlar'   => im_ait_mahsulot_reyting($db, $f + ['dan' => $sana, 'gacha' => $sana, 'tartib' => 'margin', 'yonalish' => 'past', 'limit' => 5]),
        'qoldiq_kam'      => im_ait_qoldiq_holat($db, $f + ['tur' => 'kam', 'limit' => 10]),
        'anomaliya'       => im_ait_anomaliya($db, $f + ['dan' => $sana, 'gacha' => $sana]),
        'xodimlar'        => im_ait_xodim_samaradorlik($db, $f + ['dan' => $sana, 'gacha' => $sana]),
    ];
}

// $majburiy = true bo'lsa keshdagi xulosa qayta yasaladi.
function im_ai_kunlik_xulosa($db, $sana = null, $filial_id = 0, $majburiy = false) {
    global $link;
    $sana = $sana && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sana) ? $sana : date('Y-m-d');
    $fid  = (int)$filial_id;

    $bor = $db->row("SELECT * FROM im_ai_hisobot
                     WHERE tur='kunlik' AND sana='$sana' AND filial_id=$fid LIMIT 1");
    if ($bor && !$majburiy && $bor['matn'] !== null && $bor['matn'] !== '') {
        return ['ok' => true, 'matn' => $bor['matn'], 'keshdan' => true,
                'sana' => $sana, 'created_at' => $bor['created_at']];
    }

    if (!im_ai_sozlangan()) {
        return ['ok' => false, 'xato' => 'AI sozlanmagan'];
    }

    $s   = im_ai_soz();
    $ktx = im_ai_kunlik_kontekst($db, $sana, $fid);

    $sorov = "Quyida " . $sana . " sanasidagi oshxona ko'rsatkichlari JSON ko'rinishida "
           . "berilgan. Egasi uchun KUN YAKUNI xulosasini yoz.\n\n"
           . "Tuzilishi (aynan shu tartibda, sarlavhalarsiz):\n"
           . "1. Bir jumlada kun qanday o'tgani (kecha bilan solishtirib, foizda).\n"
           . "2. **Raqamlar:** tushum, chek soni, o'rtacha chek, brutto foyda, harajat, sof foyda — "
           . "qisqa ro'yxat, har biri bitta qatorda.\n"
           . "3. **E'tibor bering:** 1-3 ta muhim narsa (tugayotgan xomashyo, zarariga sotuv, "
           . "anomaliya, sekin ishlagan oshpaz). Hech narsa bo'lmasa bu bo'limni tashla.\n"
           . "4. **Ertaga:** 1-2 ta aniq harakat.\n\n"
           . "Jami 150 so'zdan oshmasin. JSON dagi raqamlarni AYNAN ishlat, o'zingdan qo'shma.\n"
           . "Agar tushum 0 bo'lsa — buni ochiq ayt va ortiqcha tahlil yozma.\n\n"
           . "```json\n" . json_encode($ktx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n```";

    // Kontekst tayyor — chuqur fikrlash shart emas (effort=low). Standart model.
    $r  = im_ai_oddiy_call(im_ai_system_static(), $sorov, 4000, 'low', $s['model']);
    $dl = im_ai_dialekt($s['model']);

    $e = function ($v) use ($link) { return mysqli_real_escape_string($link, (string)$v); };

    if (!$r['ok']) {
        $db->q("INSERT INTO im_ai_hisobot (tur, sana, filial_id, xato, model)
                VALUES ('kunlik','$sana',$fid,'" . $e(mb_substr($r['xato'], 0, 500)) . "','" . $e($s['model']) . "')
                ON DUPLICATE KEY UPDATE xato=VALUES(xato)");
        return ['ok' => false, 'xato' => $r['xato']];
    }

    $matn = im_ai_javob_matn($r['javob'], $dl);
    list($tin, $tout) = im_ai_javob_usage($r['javob'], $dl);

    $db->q(
        "INSERT INTO im_ai_hisobot (tur, sana, filial_id, matn, kontekst, model, tokens_in, tokens_out)
         VALUES ('kunlik','$sana',$fid,'" . $e($matn) . "',
                 '" . $e(json_encode($ktx, JSON_UNESCAPED_UNICODE)) . "',
                 '" . $e($s['model']) . "', $tin, $tout)
         ON DUPLICATE KEY UPDATE matn=VALUES(matn), kontekst=VALUES(kontekst),
                                 model=VALUES(model), tokens_in=VALUES(tokens_in),
                                 tokens_out=VALUES(tokens_out), xato=NULL,
                                 created_at=CURRENT_TIMESTAMP"
    );

    return ['ok' => true, 'matn' => $matn, 'keshdan' => false, 'sana' => $sana,
            'tokens_in' => $tin, 'tokens_out' => $tout];
}
