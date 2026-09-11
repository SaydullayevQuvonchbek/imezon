<?php
// ─── Login jarayoni ────────────────────────────────────
if (!ob_get_level()) ob_start();
require_once __DIR__ . '/config.php';

// Cookie bayroqlari ximoya.php dagi bilan AYNAN BIR XIL bo'lishi shart —
// sessiya cookie'si aynan shu yerda yaratiladi. Bu yerda qo'yilmasa,
// ichkaridagi sozlama kech qoladi va cookie himoyasiz tug'iladi.
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
if (!empty($_SESSION['im_user_id'])) {
    // Yo'nalish ro'yxati config.php dagi im_rollar() da — yangi rol
    // qo'shilganda bu yerni tahrirlash shart emas.
    header('Location: ' . im_rol_sahifa($_SESSION['im_rol']));
    exit;
}

const MAX_ATTEMPTS = 7;
const LOCKOUT_TIME = 600;

$ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip_s = mysqli_real_escape_string($link, $ip);

// ─── Brute-force hisoblagichi BAZADA ────────────────────────
// Ilgari u $_SESSION da edi — hujumchi cookie'ni tashlab yuborsa
// hisoblagich nolga qaytardi, ya'ni 7 urinish chegarasi amalda
// yo'q edi. Endi urinishlar im_istoriya ga yoziladi va IP bo'yicha
// sanaladi: cookie o'chirish yordam bermaydi.
$bf = mysqli_fetch_assoc(mysqli_query($link,
    "SELECT COUNT(*) n, COALESCE(UNIX_TIMESTAMP(MAX(sana)),0) oxirgi
     FROM im_istoriya
     WHERE amal='login_xato' AND ip_adres='$ip_s'
       AND sana > DATE_SUB(NOW(), INTERVAL " . LOCKOUT_TIME . " SECOND)"
)) ?: ['n' => 0, 'oxirgi' => 0];

$urinish           = (int)$bf['n'];
$blocked           = $urinish >= MAX_ATTEMPTS;
$remaining_seconds = $blocked ? max(1, (int)$bf['oxirgi'] + LOCKOUT_TIME - time()) : 0;
$attempts_left     = max(0, MAX_ATTEMPTS - $urinish);

$xato = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    // DIQQAT: qidiruv uchun XOM login ishlatiladi. Ilgari bu yerda
    // im_f() bor edi — u htmlspecialchars qiladi, xodim-save.php esa
    // loginni XOM saqlaydi. Ya'ni tarkibida & yoki ' bo'lgan login
    // hech qachon mos kelmasdi. Ekranga qaytarishda im_f baribir
    // qo'llanadi (formadagi value).
    $login = trim($_POST['login'] ?? '');
    $parol = $_POST['parol'] ?? '';

    if ($login && $parol) {
        $k   = mysqli_real_escape_string($link, $login);
        $row = mysqli_fetch_assoc(mysqli_query(
            $link,
            "SELECT * FROM im_xodimlar WHERE login='$k' AND status=1 LIMIT 1"
        ));

        // ─── Parol tekshiruvi ───────────────────────────────
        // Baza ikki formatni saqlaydi: eski MD5 (32 ta hex belgi) va
        // yangi password_hash. Eski format bilan kirgan xodimning
        // paroli SHU ZAHOTI yangisiga ko'chiriladi — hech kimga parol
        // almashtirishni buyurish shart emas, migratsiya o'zi ketadi.
        $ok = false;
        if ($row) {
            $saqlangan = (string)$row['parol'];
            if (strlen($saqlangan) === 32 && ctype_xdigit($saqlangan)) {
                if (hash_equals($saqlangan, md5($parol))) {
                    $ok    = true;
                    $yangi = mysqli_real_escape_string($link, password_hash($parol, PASSWORD_DEFAULT));
                    mysqli_query($link, "UPDATE im_xodimlar SET parol='$yangi' WHERE id={$row['id']}");
                }
            } else {
                $ok = password_verify($parol, $saqlangan);
            }
        }

        if ($ok) {
            // Sessiya ID ni almashtiramiz: hujumchi qurbonga oldindan
            // o'z cookie'sini o'rnatgan bo'lsa (session fixation), u
            // shu qatordan keyin admin sessiyasiga kira olmaydi.
            session_regenerate_id(true);
            $_SESSION['im_user_id']   = $row['id'];
            $_SESSION['im_ism']       = $row['ism'];
            $_SESSION['im_rol']       = $row['rol'];
            $_SESSION['im_login']     = $row['login'];
            $_SESSION['im_filial_id'] = (int)($row['filial_id'] ?? 1);

            mysqli_query($link, "UPDATE im_xodimlar SET last_login=NOW() WHERE id={$row['id']}");
            $ip_s  = mysqli_real_escape_string($link, $ip);
            $ism_s = mysqli_real_escape_string($link, $row['ism']);
            $ua_s  = mysqli_real_escape_string($link, mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255));
            mysqli_query($link,
                "INSERT INTO im_istoriya (jadval,ob_id,amal,yangi,izoh,xodim_id,xodim_ism,filial_id,ip_adres,user_agent)
                 VALUES ('login',{$row['id']},'login',NULL,'Tizimga kirdi',{$row['id']},'$ism_s',{$_SESSION['im_filial_id']},'$ip_s','$ua_s')"
            );

            header('Location: ' . im_rol_sahifa($row['rol']));
            exit;

        } else {
            // Muvaffaqiyatsiz urinishni BAZAGA yozamiz — hisoblagich
            // shu yozuvlardan hisoblanadi (yuqoriga qarang).
            $ua_s = mysqli_real_escape_string($link, mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255));
            $lg_s = mysqli_real_escape_string($link, mb_substr($login, 0, 100));
            mysqli_query($link,
                "INSERT INTO im_istoriya (jadval,ob_id,amal,izoh,xodim_id,xodim_ism,filial_id,ip_adres,user_agent)
                 VALUES ('login',0,'login_xato','Login: $lg_s',0,'—',0,'$ip_s','$ua_s')"
            );

            $urinish++;
            $attempts_left = max(0, MAX_ATTEMPTS - $urinish);
            if ($urinish >= MAX_ATTEMPTS) {
                $blocked = true; $remaining_seconds = LOCKOUT_TIME;
                $xato = "Ko'p urinish! Kirish " . round(LOCKOUT_TIME / 60) . " daqiqaga bloklandi.";
            } else {
                $xato = "Login yoki parol noto'g'ri! Qolgan urinish: $attempts_left ta.";
            }
        }
    } else {
        $xato = "Iltimos, login va parolni kiriting.";
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kirish — IMezon</title>
<link rel="icon" type="image/svg+xml" href="https://imezon.uz/assets/favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:'Inter',sans-serif;
  background:#0f1117;
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:20px;
  color:#e2e8f0;
}
.card{
  width:100%;
  max-width:380px;
  background:#1a1d27;
  border:1px solid #2d3148;
  border-radius:16px;
  padding:40px 36px;
}
.logo{
  display:flex;
  align-items:center;
  gap:10px;
  margin-bottom:32px;
}
.logo-icon{
  width:36px;height:36px;
  background:linear-gradient(135deg,#7c6ef0,#5043c3);
  border-radius:9px;
  display:flex;align-items:center;justify-content:center;
}
.logo-icon svg{width:18px;height:18px}
.logo-name{font-size:16px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase}
.logo-name span{color:#a99cff}
h1{font-size:22px;font-weight:700;margin-bottom:6px}
.sub{font-size:13px;color:#64748b;margin-bottom:28px}

/* alert */
.alert{
  background:rgba(252,129,129,.08);
  border:1px solid rgba(252,129,129,.2);
  color:#fc8181;
  border-radius:10px;
  padding:11px 14px;
  font-size:13px;
  margin-bottom:20px;
}
.blocked-box{
  background:rgba(252,129,129,.08);
  border:1px solid rgba(252,129,129,.2);
  border-radius:10px;
  padding:24px;
  text-align:center;
  margin-bottom:20px;
}
.blocked-h{font-size:14px;font-weight:600;color:#fc8181;margin-bottom:4px}
.blocked-p{font-size:12px;color:#64748b;margin-bottom:12px}
.blocked-t{font-size:36px;font-weight:700;color:#fc8181;letter-spacing:4px;font-variant-numeric:tabular-nums}

/* fields */
.field{margin-bottom:16px}
label{display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:6px;text-transform:uppercase;letter-spacing:.6px}
.wrap{position:relative}
.fi{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#475569;pointer-events:none}
.fi svg{width:15px;height:15px}
input[type=text],input[type=password]{
  width:100%;
  padding:11px 40px;
  font-size:14px;font-family:'Inter',sans-serif;
  background:#0f1117;
  border:1px solid #2d3148;
  border-radius:10px;
  color:#e2e8f0;
  outline:none;
  transition:border-color .15s,box-shadow .15s;
}
input::placeholder{color:#334155}
input:focus{border-color:#7c6ef0;box-shadow:0 0 0 3px rgba(124,110,240,.12)}
input:disabled{opacity:.4;cursor:not-allowed}
.eye{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;
  color:#475569;display:flex;padding:4px;
  transition:color .15s;
}
.eye:hover{color:#94a3b8}
.eye svg{width:15px;height:15px}

/* button */
.btn{
  width:100%;
  padding:12px;
  border:none;border-radius:10px;
  font-size:14px;font-family:'Inter',sans-serif;font-weight:600;
  cursor:pointer;
  background:linear-gradient(135deg,#7c6ef0,#5043c3);
  color:#fff;
  margin-top:8px;
  transition:opacity .15s,transform .1s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn:hover:not(:disabled){opacity:.9}
.btn:active:not(:disabled){transform:scale(.99)}
.btn:disabled{opacity:.4;cursor:not-allowed}
.spin{
  display:none;width:14px;height:14px;
  border:2px solid rgba(255,255,255,.3);
  border-top-color:#fff;border-radius:50%;
  animation:rot .6s linear infinite;
}
@keyframes rot{to{transform:rotate(360deg)}}

.footer{margin-top:28px;text-align:center;font-size:11.5px;color:#334155}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
      </svg>
    </div>
    <div class="logo-name">IME<span>ZON</span></div>
  </div>

  <h1>Tizimga kirish</h1>
  <p class="sub">Login va parolingizni kiriting</p>

  <?php if ($blocked): ?>
  <div class="blocked-box">
    <div class="blocked-h">Hisob vaqtincha bloklandi</div>
    <div class="blocked-p">Juda ko'p noto'g'ri urinish. Iltimos kuting.</div>
    <div class="blocked-t" id="countdown"><?= gmdate('i:s', $remaining_seconds) ?></div>
  </div>
  <?php elseif ($xato): ?>
  <div class="alert"><?= htmlspecialchars($xato) ?></div>
  <?php endif; ?>

  <form method="POST" action="" autocomplete="off" id="loginForm">
    <div class="field">
      <label for="login">Login</label>
      <div class="wrap">
        <div class="fi">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
        </div>
        <input type="text" id="login" name="login" placeholder="Foydalanuvchi nomi"
          value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required autofocus <?= $blocked ? 'disabled' : '' ?>>
      </div>
    </div>

    <div class="field">
      <label for="parol">Parol</label>
      <div class="wrap">
        <div class="fi">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
        </div>
        <input type="password" id="parol" name="parol" placeholder="Parol" required <?= $blocked ? 'disabled' : '' ?>>
        <button type="button" class="eye" id="eyeBtn" onclick="togglePass()" tabindex="-1">
          <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
    </div>

    <button type="submit" class="btn" id="loginBtn" <?= $blocked ? 'disabled' : '' ?>>
      <?php if ($blocked): ?>
        Bloklangan
      <?php else: ?>
        <span id="btnText">Kirish</span>
        <span id="spinner" class="spin"></span>
      <?php endif; ?>
    </button>
  </form>

  <div class="footer">IMezon © 2026</div>
</div>

<script>
const EYE_OPEN  = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
const EYE_CLOSE = `<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;

function togglePass() {
  const inp  = document.getElementById('parol');
  const icon = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text'; icon.innerHTML = EYE_CLOSE;
  } else {
    inp.type = 'password'; icon.innerHTML = EYE_OPEN;
  }
}

document.getElementById('loginForm')?.addEventListener('submit', function () {
  const btn     = document.getElementById('loginBtn');
  const txt     = document.getElementById('btnText');
  const spinner = document.getElementById('spinner');
  if (!btn.disabled) {
    btn.disabled = true;
    if (txt)     txt.textContent = 'Tekshirilmoqda...';
    if (spinner) spinner.style.display = 'inline-block';
  }
});

const cdEl = document.getElementById('countdown');
if (cdEl) {
  let s = <?= (int)$remaining_seconds ?>;
  const tick = () => {
    if (s <= 0) { location.reload(); return; }
    const mm = String(Math.floor(s / 60)).padStart(2, '0');
    const ss = String(s % 60).padStart(2, '0');
    cdEl.textContent = mm + ':' + ss;
    s--; setTimeout(tick, 1000);
  };
  tick();
}
</script>
</body>
</html>