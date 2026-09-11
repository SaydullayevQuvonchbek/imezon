<?php
// ============================================================
//  IMezon — Auth Guard (Sessiya tekshiruvi)
//  Har bir himoyalangan sahifa tepasiga:
//    require_once __DIR__ . '/../ximoya.php';
// ============================================================

// Output buffering — BOM yoki whitespace sabab chiqadigan
// "headers already sent" xatosini bartaraf etadi
if (!ob_get_level()) ob_start();

if (session_status() === PHP_SESSION_NONE) {
    // Cookie bayroqlari session_start() dan OLDIN o'rnatilishi shart.
    //  httponly — JavaScript cookie'ni o'qiy olmaydi (XSS bo'lsa ham
    //             sessiya o'g'irlanmaydi)
    //  samesite — begona saytdan yuborilgan so'rovga cookie ilashmaydi
    //             (CSRF ning katta qismini shu yerda to'sadi)
    //  secure   — faqat HTTPS da. Lokalda HTTP bo'lgani uchun shartli:
    //             qattiq true qo'ysak, localhost'da tizimga kirib bo'lmasdi.
    $im_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $im_https,
        'samesite' => 'Lax',
    ]);
    session_name('IMEZON_SESS');
    session_start();
}

if (empty($_SESSION['im_user_id'])) {
    $base = defined('im_BASE') ? im_BASE : '/';
    header('Location: ' . $base . 'login.php');
    exit;
}

// ─── CSRF himoyasi ───────────────────────────────────────────
// Tizimdagi BARCHA yozuv endpointlari shu faylni ulaydi, shuning
// uchun tekshiruv shu bitta joyda turadi — 80 ta faylni tahrirlash
// shart emas va yangi endpoint qo'shilganda himoya avtomatik keladi.
//
// Belgi brauzerga `window.im_csrf` orqali beriladi, qaytib esa
// `X-IM-CSRF` sarlavhasida keladi (assets/js/main.js dagi fetch
// o'ramchisi buni o'zi qo'shadi). Sarlavha tanlangani bejiz emas:
// loyihada POST tanasi uch xil (FormData, URLSearchParams, JSON) —
// sarlavha ularning hammasida bir xil ishlaydi.
if (empty($_SESSION['im_csrf'])) {
    $_SESSION['im_csrf'] = bin2hex(random_bytes(32));
}
define('IM_CSRF', $_SESSION['im_csrf']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $im_kelgan = $_SERVER['HTTP_X_IM_CSRF'] ?? ($_POST['im_csrf'] ?? '');
    if (!is_string($im_kelgan) || !hash_equals($_SESSION['im_csrf'], $im_kelgan)) {
        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'msg'    => "Sessiya belgisi eskirgan — sahifani yangilang (F5) va qaytadan urining",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Rol tekshiruvi — sahifa o'zi chaqiradi
// Misol: im_rol_check(['admin']);
// Misol: im_rol_check(['admin','sklad']);
function im_rol_check(array $ruxsat) {
    $joriy_rol = $_SESSION['im_rol'] ?? '';
    if (!in_array($joriy_rol, $ruxsat)) {
        // Rolga qarab qaytish manzili — ro'yxat config.php da (im_rollar()).
        // im_rol_check() har doim config.php dan KEYIN chaqiriladi, shuning
        // uchun funksiya mavjud; ehtiyot uchun zaxira ham qoldirilgan.
        $base = defined('im_BASE') ? im_BASE : '/';
        $back = function_exists('im_rol_sahifa') && $joriy_rol
              ? im_rol_sahifa($joriy_rol)
              : $base . 'login.php';
        http_response_code(403);
        echo '<!DOCTYPE html><html><head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>Ruxsat yo\'q | IMezon</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body{font-family:Inter,sans-serif;display:flex;align-items:center;
                 justify-content:center;min-height:100vh;margin:0;
                 background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%)}
            .box{text-align:center;padding:60px 48px;background:#fff;border-radius:20px;
                 box-shadow:0 20px 60px rgba(0,0,0,.3);max-width:420px;width:90%}
            .icon{font-size:64px;margin-bottom:20px;display:block}
            h2{color:#1e1e2e;margin:0 0 10px;font-size:22px}
            p{color:#6c757d;margin:0 0 8px;font-size:14px}
            .role{display:inline-block;background:#f0f2f5;border-radius:6px;
                  padding:4px 12px;font-size:12px;color:#495057;margin-bottom:24px}
            a{display:inline-block;padding:12px 28px;background:#e2b96f;color:#fff;
              border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;
              transition:.2s;box-shadow:0 4px 16px rgba(226,185,111,.4)}
            a:hover{background:#c9a058;transform:translateY(-1px)}
        </style></head><body>
        <div class="box">
            <span class="icon">🚫</span>
            <h2>Ruxsat yo\'q</h2>
            <p>Bu sahifaga kirish huquqingiz yo\'q.</p>
            <div class="role">Sizning rolingiz: <strong>' . htmlspecialchars($joriy_rol ?: 'Noma\'lum') . '</strong></div><br>
            <a href="' . $back . '">← Panelingizga qaytish</a>
        </div></body></html>';
        exit;
    }
}

// Global sessiya o'zgaruvchilari
$im_user_id  = (int)($_SESSION['im_user_id']  ?? 0);
$im_ism      = $_SESSION['im_ism']     ?? '';
$im_rol      = $_SESSION['im_rol']     ?? '';
$im_login    = $_SESSION['im_login']   ?? '';
$im_filial_id= (int)($_SESSION['im_filial_id'] ?? 1); // Ko'p filial uchun

// ─── Audit log (Istoriya) yozish ─────────────────────────────
// Misol: im_log('im_mahsulotlar', $id, 'update', $eski, $yangi, 'Narx o\'zgardi');
// Misol: im_log('login', 0, 'login', null, null, 'Tizimga kirdi');
function im_log(string $jadval, int $ob_id, string $amal, $eski = null, $yangi = null, string $izoh = '') {
    global $link, $im_user_id, $im_ism, $im_filial_id;
    $eski_j  = $eski  !== null ? mysqli_real_escape_string($link, is_string($eski)  ? $eski  : json_encode($eski,  JSON_UNESCAPED_UNICODE)) : 'NULL';
    $yangi_j = $yangi !== null ? mysqli_real_escape_string($link, is_string($yangi) ? $yangi : json_encode($yangi, JSON_UNESCAPED_UNICODE)) : 'NULL';
    $izoh_s  = mysqli_real_escape_string($link, mb_substr($izoh, 0, 255));
    $ism_s   = mysqli_real_escape_string($link, $im_ism  ?: 'Tizim');
    $jadval_s= mysqli_real_escape_string($link, $jadval);
    $amal_s  = mysqli_real_escape_string($link, $amal);
    $ip      = mysqli_real_escape_string($link, $_SERVER['REMOTE_ADDR'] ?? '');
    $ua      = mysqli_real_escape_string($link, mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255));
    $eski_val  = $eski  !== null ? "'$eski_j'"  : 'NULL';
    $yangi_val = $yangi !== null ? "'$yangi_j'" : 'NULL';
    @mysqli_query($link,
        "INSERT INTO im_istoriya (jadval,ob_id,amal,eski,yangi,izoh,xodim_id,xodim_ism,filial_id,ip_adres,user_agent)
         VALUES ('$jadval_s',$ob_id,'$amal_s',$eski_val,$yangi_val,'$izoh_s',$im_user_id,'$ism_s',$im_filial_id,'$ip','$ua')"
    );
}
