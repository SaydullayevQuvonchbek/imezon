<?php
// ============================================================
//  IMezon — Ovozli buyurtma (TTS) kutubxonasi
// ------------------------------------------------------------
//  Provayderdan MUSTAQIL qatlam. API kaliti/xizmati almashsa ham
//  chaqiruvchi kod (oshpaz/ajax/tts.php) o'zgarmaydi.
//
//  Sozlamalar (im_sozlamalar jadvali):
//    tts_yoniq     0/1   — umumiy yoqish
//    tts_provider  mohir | azure | off
//    tts_key       API kaliti
//    tts_ovoz      ovoz nomi (azure: uz-UZ-MadinaNeural)
//    tts_region    faqat azure uchun (masalan: westeurope)
//    tts_endpoint  bo'sh bo'lsa — provayder default'i
//    tts_auth      bearer | raw   — Authorization sarlavha uslubi
//    tts_max_item  ovozda o'qiladigan taom soni (default 4)
//
//  Kesh: uploads/tts/{sha1}.mp3 — bir marta yasalgan matn
//  boshqa hech qachon API'ga chiqmaydi.
// ============================================================

// To'g'ridan-to'g'ri HTTP so'rovdan himoya (config.php orqali kiriladi)
if (!defined('im_VERSION')) return;

define('IM_TTS_DIR', __DIR__ . '/uploads/tts');

// ─── Sozlamalar ─────────────────────────────────────────────
function im_tts_soz() {
    static $s = null;
    if ($s !== null) return $s;
    $a = im_sozlamalar_all();
    $g = function ($k, $d = '') use ($a) { return isset($a[$k]) && $a[$k] !== '' ? $a[$k] : $d; };
    $s = [
        'yoniq'    => $g('tts_yoniq', '0') === '1',
        'provider' => $g('tts_provider', 'off'),
        'key'      => $g('tts_key'),
        'ovoz'     => $g('tts_ovoz'),
        'region'   => $g('tts_region'),
        'endpoint' => $g('tts_endpoint'),
        'auth'     => $g('tts_auth', 'raw'),   // Mohir kalitni xom holda kutadi
        'max_item' => max(1, (int)$g('tts_max_item', '4')),
    ];
    return $s;
}

// Provayder va kalit kiritilganmi (admin sinovi uchun shu yetarli)
function im_tts_sozlangan() {
    $s = im_tts_soz();
    return $s['provider'] !== 'off' && $s['key'] !== '';
}

// Oshxona ekrani ovoz chiqarsinmi — sozlangan VA yoqilgan bo'lsa.
// "Yoqilgan" faqat oshpaz ekranini boshqaradi; admin sinovi
// o'chirilgan holatda ham ishlashi kerak — aks holda sinamasdan
// yoqishga majbur bo'lasiz.
function im_tts_ishlaydi() {
    return im_tts_soz()['yoniq'] && im_tts_sozlangan();
}

// ─── Sonni o'zbekcha so'zga ────────────────────────────────
// TTS raqamni chet tilida o'qib yuborishi mumkin — shuning uchun
// sonni oldindan so'zga aylantiramiz.
function im_son_soz_butun($n) {
    $n = (int)$n;
    if ($n <= 0) return '';
    $bir = ['', 'bir', 'ikki', 'uch', 'to\'rt', 'besh', 'olti', 'yetti', 'sakkiz', 'to\'qqiz'];
    $on  = ['', 'o\'n', 'yigirma', 'o\'ttiz', 'qirq', 'ellik', 'oltmish', 'yetmish', 'sakson', 'to\'qson'];
    $out = [];
    if ($n >= 1000) {
        $m = (int)($n / 1000);
        $out[] = ($m > 1 ? im_son_soz_butun($m) . ' ' : '') . 'ming';
        $n %= 1000;
    }
    if ($n >= 100) {
        $y = (int)($n / 100);
        $out[] = ($y > 1 ? $bir[$y] . ' ' : '') . 'yuz';
        $n %= 100;
    }
    if ($n >= 10) { $out[] = $on[(int)($n / 10)]; $n %= 10; }
    if ($n > 0)   { $out[] = $bir[$n]; }
    return implode(' ', $out);
}

function im_son_soz($n) {
    $n = (float)$n;
    if ($n <= 0) return '';
    $but = (int)floor($n);
    $qol = $n - $but;

    // Bo'linadigan mahsulotlar (non va h.k.) oshxonada tabiiy aytiladi.
    if (abs($qol - 0.25) < 0.02) {
        return $but === 0 ? 'chorak' : im_son_soz_butun($but) . ' butun chorak';
    }
    if (abs($qol - 0.5) < 0.02) {
        return $but === 0 ? 'yarim' : im_son_soz_butun($but) . ' yarim';
    }
    if (abs($qol - 0.75) < 0.02) {
        return $but === 0 ? 'uch chorak' : im_son_soz_butun($but) . ' butun uch chorak';
    }
    // Boshqa kasrlar (1.7...) — raqam holicha, TTS o'zi o'qiydi
    if ($qol > 0.02) return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

    return im_son_soz_butun($but);
}

// ─── Birlikni o'qiladigan so'zga ───────────────────────────
function im_tts_birlik($b) {
    $b = mb_strtolower(trim((string)$b), 'UTF-8');
    $map = [
        ''        => 'ta',        'dona'   => 'ta',        'ta'  => 'ta',
        'sht'     => 'ta',        'шт'     => 'ta',        'pcs' => 'ta',
        'kg'      => 'kilogramm', 'кг'     => 'kilogramm',
        'g'       => 'gramm',     'gr'     => 'gramm',     'гр'  => 'gramm',
        'l'       => 'litr',      'litr'   => 'litr',      'л'   => 'litr',
        'ml'      => 'millilitr',
        'porsiya' => 'porsiya',   'porsia' => 'porsiya',
    ];
    return isset($map[$b]) ? $map[$b] : $b;
}

// ─── Taom nomini o'qishga tayyorlash ───────────────────────
// Menyudagi nomlar BOSH HARFLARDA yozilgan ("OSH", "LAG'MON").
// TTS bunday so'zni qisqartma deb bilib harflab o'qiydi —
// "O-S-H". Jumla o'rtasida turgani uchun kichik harfga tushiramiz.
function im_tts_nomi($nomi) {
    $nomi = trim(preg_replace('/\s+/u', ' ', (string)$nomi));
    if ($nomi === '') return '';

    $harflar = preg_replace('/[^\p{L}]+/u', '', $nomi);
    if ($harflar !== '' && mb_strlen($harflar, 'UTF-8') > 1
        && mb_strtoupper($nomi, 'UTF-8') === $nomi) {
        $nomi = mb_strtolower($nomi, 'UTF-8');
    }
    return $nomi;
}

// ─── Manzil: buyurtma qayerga ──────────────────────────────
// im_sotuvchi_order.mijoz_ism aslida MANZIL saqlaydi:
// "Stol 1", "VIP kabina", "Olib ketish", "Dastavka".
// Ekranda nima yozilgan bo'lsa, ovozda ham AYNAN shu aytiladi —
// oshpaz eshitgani bilan ko'rgani bir xil bo'lsin. Faqat raqam
// so'zga aylantiriladi: "Stol 1" → "Stol bir".
function im_tts_manzil(array $o) {
    $nom = trim((string)(isset($o['mijoz_ism']) ? $o['mijoz_ism'] : ''));
    if ($nom === '' && !empty($o['olib_ketish'])) $nom = 'Olib ketish';
    if ($nom === '') return '';

    // Stolga bog'langan mustaqil Olib ketish orderi oshxonada oddiy stol
    // orderi bilan adashmasin: "Stol besh, olib ketish" deb o'qiladi.
    if (!empty($o['olib_ketish']) && !empty($o['stol_id'])
        && mb_stripos($nom, 'olib ketish', 0, 'UTF-8') === false) {
        $nom .= ', olib ketish';
    }

    return preg_replace_callback('/\d+/', function ($m) {
        return im_son_soz_butun((int)$m[0]);
    }, $nom);
}

// ─── Buyurtmadan o'qiladigan matn yasash ───────────────────
// Uzun ro'yxat oshxonada eshitilmaydi — birinchi N ta taom
// o'qiladi, qolgani "va yana N xil taom" bo'lib qisqartiriladi.
// Shakl — qisqa, oshxona shovqinida eshitiladigan:
//   "Yangi buyurtma, Stol bir, to'rt porsiya osh."
//
// MUHIM — NUQTA ISHLATMANG. Jonli o'lchov (bir xil so'zlar):
//   nuqta bilan  → 4.14 s, ichida 1.35 s bo'shliq (uzilib eshitiladi)
//   vergul bilan → 1.86 s, bo'shliq 0.35 s (ravon)
// Model har nuqtada uzoq pauza qo'yadi. Shu bois butun xabar —
// vergul bilan bog'langan BITTA jumla, oxirida bitta nuqta.
function im_tts_matn(array $o, array $items) {
    $s    = im_tts_soz();
    $qism = ['Yangi buyurtma'];

    $manzil = im_tts_manzil($o);
    if ($manzil !== '') $qism[] = $manzil;

    $ozi = array_slice($items, 0, $s['max_item']);
    $qol = count($items) - count($ozi);

    // "to'rt porsiya osh" — son, birlik, keyin taom nomi
    $taom = [];
    foreach ($ozi as $i) {
        $son    = im_son_soz($i['soni']);
        $brl    = im_tts_birlik(isset($i['birlik']) ? $i['birlik'] : '');
        $nomi   = im_tts_nomi($i['nomi']);
        $taom[] = trim($son . ' ' . $brl . ' ' . $nomi);
    }
    if ($taom) $qism[] = implode(', ', $taom);

    if ($qol > 0)           $qism[] = 'va yana ' . im_son_soz_butun($qol) . ' xil taom';
    if (!empty($o['izoh'])) $qism[] = 'izoh bor, ekranga qarang';

    return implode(', ', $qism) . '.';
}

// ─── Kesh ──────────────────────────────────────────────────
// Provayderlar turli format qaytaradi (Mohir — WAV, Azure — MP3),
// shuning uchun kengaytma audioning o'zidan aniqlanadi.
function im_tts_kesh_baza($matn) {
    $s = im_tts_soz();
    return IM_TTS_DIR . '/' . sha1($s['provider'] . '|' . $s['ovoz'] . '|' . $matn);
}

function im_tts_kengaytma($audio) {
    if (substr($audio, 0, 4) === 'RIFF') return 'wav';
    if (substr($audio, 0, 4) === 'OggS') return 'ogg';
    return 'mp3';   // ID3 / xom MPEG freym
}

// Keshda shu matn uchun tayyor fayl bormi
function im_tts_keshda($matn) {
    $baza = im_tts_kesh_baza($matn);
    foreach (['mp3', 'wav', 'ogg'] as $e) {
        $f = $baza . '.' . $e;
        if (is_file($f) && filesize($f) > 512) return $f;
    }
    return null;
}

// Fayl yo'lidan HTTP Content-Type
function im_tts_mime($yol) {
    $e = strtolower(pathinfo($yol, PATHINFO_EXTENSION));
    if ($e === 'wav') return 'audio/wav';
    if ($e === 'ogg') return 'audio/ogg';
    return 'audio/mpeg';
}

// ─── HTTP yordamchi ────────────────────────────────────────
function im_tts_http($url, array $headers, $body) {
    if (!function_exists('curl_init')) {
        return ['code' => 0, 'type' => '', 'body' => '', 'xato' => 'PHP curl kengaytmasi yoqilmagan'];
    }
    $ct = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => $body !== null,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT        => 20,
        // Ba'zi provayderlar audioni CDN'ga yo'naltiradi
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$ct) {
            if (stripos($h, 'content-type:') === 0) $ct = trim(substr($h, 13));
            return strlen($h);
        },
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

    $out  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return ['code' => $code, 'type' => $ct, 'body' => $out === false ? '' : $out, 'xato' => $err];
}

// ─── Javobdagi havoladan audioni yuklab olish ──────────────
// Ba'zi xizmatlar audio faylni ham kalit bilan so'rashni talab
// qiladi. Ammo kalit FAQAT provayderning o'z domeniga yuboriladi —
// javobda begona domen ko'rsatilsa, kalit u yoqqa ketmaydi.
function im_tts_yuklab_ol($url) {
    $s   = im_tts_soz();
    $hdr = [];

    $etalon = $s['endpoint'] !== '' ? $s['endpoint']
            : ($s['provider'] === 'mohir' ? 'https://uzbekvoice.ai/api/v1/tts' : '');
    $host_a = parse_url($url, PHP_URL_HOST);
    $host_b = $etalon !== '' ? parse_url($etalon, PHP_URL_HOST) : '';

    if ($host_a && $host_b && strcasecmp($host_a, $host_b) === 0) {
        if ($s['provider'] === 'mohir') {
            $hdr[] = 'Authorization: '
                   . ($s['auth'] === 'bearer' ? 'Bearer ' . $s['key'] : $s['key']);
        } elseif ($s['provider'] === 'azure') {
            $hdr[] = 'Ocp-Apim-Subscription-Key: ' . $s['key'];
        }
    }
    return im_tts_http($url, $hdr, null);
}

// ─── Javobdan audio ajratib olish ──────────────────────────
// Provayderlar uch xil javob qaytaradi: (1) to'g'ridan-to'g'ri audio
// bayt, (2) JSON ichida havola, (3) JSON ichida base64. Uchalasi ham
// qo'llanadi — Mohir API'sining aniq formati kalit kelganda ma'lum
// bo'ladi va kodni o'zgartirish kerak bo'lmaydi.
function im_tts_audio_ajrat(array $r) {
    if ($r['code'] < 200 || $r['code'] >= 300 || $r['body'] === '') return null;

    if (stripos($r['type'], 'audio') !== false || stripos($r['type'], 'octet-stream') !== false) {
        return $r['body'];
    }

    $j = json_decode($r['body'], true);
    if (!is_array($j)) {
        // Content-Type noto'g'ri bo'lsa ham MP3/WAV imzosini tekshiramiz
        if (substr($r['body'], 0, 3) === 'ID3'
            || substr($r['body'], 0, 4) === 'RIFF'
            || substr($r['body'], 0, 4) === 'OggS'
            || substr($r['body'], 0, 2) === "\xFF\xFB") {
            return $r['body'];
        }
        return null;
    }

    $tekis = [];
    array_walk_recursive($j, function ($v, $k) use (&$tekis) { $tekis[strtolower($k)] = $v; });

    foreach (['url', 'audio_url', 'audiourl', 'file', 'file_url', 'path', 'result', 'link'] as $k) {
        if (!empty($tekis[$k]) && is_string($tekis[$k]) && preg_match('~^https?://~i', $tekis[$k])) {
            $f = im_tts_yuklab_ol($tekis[$k]);
            if ($f['code'] >= 200 && $f['code'] < 300 && $f['body'] !== '') return $f['body'];
        }
    }
    foreach (['audio', 'audio_base64', 'audiocontent', 'content', 'data', 'result'] as $k) {
        if (!empty($tekis[$k]) && is_string($tekis[$k]) && strlen($tekis[$k]) > 256) {
            $b = base64_decode($tekis[$k], true);
            if ($b !== false && strlen($b) > 256) return $b;
        }
    }
    return null;
}

// ─── Provayder: Mohir.ai (uzbekvoice.ai) ───────────────────
// Xizmat ASINXRON ishlaydi (jonli sinovda aniqlangan):
//   1) POST /api/v1/tts        → {"id": "...", "status": "PENDING"}
//   2) GET  /api/v1/tasks?id=  → PENDING/PROGRESS ... → SUCCESS
//   3) result.url              → CDN'dagi audio (ochiq havola, WAV)
//
// TUZOQLAR (hujjatda yozilmagan, sinovda chiqqan):
//  • "blocking" HAQIQIY boolean bo'lishi shart. Hujjatdagi curl
//    namunasi "true" MATNINI ko'rsatadi — u holda server 500 beradi.
//    Maydonni butunlay tashlab ketsak ham 500. Jadvaldagi "boolean"
//    turi to'g'ri, namuna esa xato.
//  • "blocking": true bo'lsa ham javob bloklanmaydi — baribir
//    PENDING qaytadi va vazifani so'rab turish kerak.
//  • Barcha modellar ishlamaydi: shoira va lola FAILURE beradi,
//    jasur va sevinch ishlaydi. Shu bois default — jasur.
//
// webhook_notification_url ataylab yuborilmaydi: natijani o'zimiz
// so'rab olamiz, buyurtma matnini begona manzilga jo'natish shart emas.
function im_tts_mohir($matn) {
    $s    = im_tts_soz();
    $url  = $s['endpoint'] !== '' ? $s['endpoint'] : 'https://uzbekvoice.ai/api/v1/tts';
    $ovoz = $s['ovoz'] !== '' ? $s['ovoz'] : 'jasur';

    // Mohir kalitni sarlavhaga xom holda kutadi (Bearer'siz).
    $auth = $s['auth'] === 'bearer' ? 'Bearer ' . $s['key'] : $s['key'];
    $hdr  = ['Authorization: ' . $auth, 'Content-Type: application/json'];

    // ── 1) Vazifa yaratamiz ────────────────────────────────
    $r = im_tts_http($url, $hdr, json_encode([
        'text'     => $matn,
        'model'    => $ovoz,
        'blocking' => true,   // MATN emas, boolean!
    ], JSON_UNESCAPED_UNICODE));

    $j = json_decode($r['body'], true);
    if (!is_array($j) || empty($j['id'])) return $r;   // xato yuqori qatlamda ko'rsatiladi

    // ── 2) Tayyor bo'lguncha so'rab turamiz ────────────────
    $tasks  = preg_replace('~/tts/?$~', '/tasks', $url);
    $davom  = ['PENDING', 'STARTED', 'PROGRESS', 'RECEIVED', 'RETRY'];
    $muddat = microtime(true) + 20;
    $holat  = 'PENDING';
    $oxirgi = null;

    // 300 ms — sintez odatda 1–4 soniya oladi, tez-tez so'rasak
    // tayyor bo'lgan zahoti ilib olamiz (so'rovlar bepul).
    while (microtime(true) < $muddat) {
        usleep(300000);
        $p  = im_tts_http($tasks . '?id=' . urlencode($j['id']), $hdr, null);
        $pj = json_decode($p['body'], true);
        if (!is_array($pj)) continue;
        $oxirgi = $pj;
        $xom    = isset($pj['status']) ? $pj['status'] : (isset($pj['state']) ? $pj['state'] : '');
        $holat  = strtoupper((string)$xom);
        if (!in_array($holat, $davom, true)) break;
    }

    $natija = (is_array($oxirgi) && isset($oxirgi['result'])) ? $oxirgi['result'] : null;
    if (!is_array($natija) || empty($natija['url'])) {
        $izoh = 'vazifa holati: ' . ($holat !== '' ? $holat : 'noma\'lum')
              . ' (model "' . $ovoz . '")';
        if ($holat === 'FAILURE') {
            $izoh .= ' — bu model ishlamayapti, "jasur" yoki "sevinch" ni tanlang';
        } elseif (in_array($holat, $davom, true)) {
            $izoh .= ' — xizmat 20 soniyada ulgurmadi';
        }
        return ['code' => 0, 'type' => 'application/json',
                'body' => json_encode($oxirgi, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'xato' => $izoh];
    }

    // ── 3) CDN'dan audioni olamiz (havola ochiq, kalit kerak emas) ──
    return im_tts_http($natija['url'], [], null);
}

// ─── Provayder: Azure Speech ───────────────────────────────
function im_tts_azure($matn) {
    $s      = im_tts_soz();
    $region = $s['region'] !== '' ? $s['region'] : 'westeurope';
    $ovoz   = $s['ovoz']   !== '' ? $s['ovoz']   : 'uz-UZ-MadinaNeural';
    $url    = $s['endpoint'] !== ''
        ? $s['endpoint']
        : 'https://' . $region . '.tts.speech.microsoft.com/cognitiveservices/v1';

    $ssml = '<speak version="1.0" xml:lang="uz-UZ">'
          . '<voice name="' . htmlspecialchars($ovoz, ENT_QUOTES) . '">'
          . '<prosody rate="-5%">' . htmlspecialchars($matn, ENT_QUOTES) . '</prosody>'
          . '</voice></speak>';

    $hdr = [
        'Ocp-Apim-Subscription-Key: ' . $s['key'],
        'Content-Type: application/ssml+xml',
        'X-Microsoft-OutputFormat: audio-24khz-48kbitrate-mono-mp3',
        'User-Agent: IMezon',
    ];
    return im_tts_http($url, $hdr, $ssml);
}

// ─── Asosiy: matndan audio fayl ────────────────────────────
// Qaytaradi: ['yol'=>fayl, 'kesh'=>bool] yoki ['xato'=>sabab, 'javob'=>xom]
function im_tts_yasa($matn, $keshdan = true) {
    $matn = trim(preg_replace('/\s+/u', ' ', (string)$matn));
    if ($matn === '')          return ['xato' => 'Matn bo\'sh'];
    // Bu yerda "yoqilgan"lik tekshirilmaydi — oshxona yo'li uchun
    // uni oshpaz/ajax/tts.php im_tts_ishlaydi() bilan tekshiradi.
    if (!im_tts_sozlangan())   return ['xato' => 'Provayder yoki API kalit kiritilmagan'];

    if ($keshdan) {
        $bor = im_tts_keshda($matn);
        if ($bor !== null) return ['yol' => $bor, 'kesh' => true];
    }

    if (!is_dir(IM_TTS_DIR)) @mkdir(IM_TTS_DIR, 0775, true);
    if (!is_dir(IM_TTS_DIR) || !is_writable(IM_TTS_DIR)) {
        return ['xato' => 'uploads/tts papkasiga yozib bo\'lmadi'];
    }

    $s = im_tts_soz();
    if      ($s['provider'] === 'azure') $r = im_tts_azure($matn);
    elseif  ($s['provider'] === 'mohir') $r = im_tts_mohir($matn);
    else    return ['xato' => 'Noma\'lum provayder: ' . $s['provider']];

    $audio = im_tts_audio_ajrat($r);
    if ($audio === null) {
        return [
            'xato'  => 'Provayder audio qaytarmadi (HTTP ' . $r['code'] . ')'
                       . ($r['xato'] !== '' ? ' — ' . $r['xato'] : ''),
            'javob' => mb_substr((string)$r['body'], 0, 600),
            'type'  => $r['type'],
        ];
    }

    $yol = im_tts_kesh_baza($matn) . '.' . im_tts_kengaytma($audio);

    // Yarim yozilgan fayl keshda qolib ketmasligi uchun — atomik almashtirish
    $vaqt = $yol . '.tmp';
    if (@file_put_contents($vaqt, $audio) === false) return ['xato' => 'Faylga yozib bo\'lmadi'];
    @rename($vaqt, $yol);

    return ['yol' => $yol, 'kesh' => false];
}

// ─── Buyurtma bo'yicha audio (oshpaz uchun asosiy kirish) ──
// MUHIM: matn SERVERDA yasaladi. Brauzerdan matn qabul qilinmaydi —
// aks holda bu ochiq TTS proksisiga aylanib, API balansini
// begonalar sarflab yuborishi mumkin.
function im_tts_order(Cyber $db, $order_id, $filial_id) {
    $order_id  = (int)$order_id;
    $filial_id = (int)$filial_id;
    $shart     = $filial_id > 0 ? "AND o.filial_id = $filial_id" : '';
    $today     = date('Y-m-d');

    $o = $db->row(
        "SELECT o.id, o.mijoz_ism, o.olib_ketish, o.stol_id, o.izoh, o.status,
                (SELECT COUNT(*) FROM im_sotuvchi_order o2
                  WHERE o2.filial_id = o.filial_id
                    AND DATE(o2.created_at) = '$today'
                    AND o2.id <= o.id) AS kun_raqam
           FROM im_sotuvchi_order o
          WHERE o.id = $order_id $shart
            AND o.status IN ('oshpazda','pishirilmoqda')
          LIMIT 1"
    );
    if (!$o) return ['xato' => 'Buyurtma topilmadi'];

    $items = $db->rows(
        "SELECT (i.soni - i.tayyorlandi_soni) AS soni, m.nomi, m.birlik
           FROM im_sotuvchi_order_item i
           JOIN im_mahsulotlar m ON m.id = i.mahsulot_id
          WHERE i.order_id = $order_id
            AND m.oshpaz_kerak = 1
            AND i.soni > i.tayyorlandi_soni"
    );
    if (!$items) return ['xato' => 'O\'qiladigan taom yo\'q'];

    return im_tts_yasa(im_tts_matn($o, $items));
}

// ─── Keshni tozalash (menyu nomi o'zgarganda kerak bo'ladi) ─
function im_tts_kesh_tozala() {
    if (!is_dir(IM_TTS_DIR)) return 0;
    $n = 0;
    foreach (glob(IM_TTS_DIR . '/*.{mp3,wav,ogg}', GLOB_BRACE) as $f) { if (@unlink($f)) $n++; }
    return $n;
}
