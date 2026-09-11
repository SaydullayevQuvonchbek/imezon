<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ai_lib.php';   // im_ai_key_mask(), im_ai_model_ogoh(), im_ai_model_royxat()
im_rol_check(['admin']);
$db = new Cyber();

// Barcha sozlamalarni yuklaymiz
$soz = im_sozlamalar_all();

// Default qiymatlar (agar DB da yo'q bo'lsa)
$defaults = [
    'dukon_nomi'          => 'IMezon',
    'dukon_manzil'        => 'Samarqand sh., Registon',
    'dukon_telefon'       => '+998 71 123-45-67',
    'dukon_url'           => 'imezon.uz',
    'dukon_valyuta'       => 'so\'m',
    'kassir_max_chegirma' => '10',
    'chek_izoh'           => 'Tovar sifatiga kafolat beriladi',
    'chek_rahmat'         => 'Xaridingiz uchun rahmat!',
    'chek_telegram'       => '',
    'chek_instagram'      => '',
    'nasiya_kun'          => '30',
    'ulg_min_soni'        => '5',
    'smena_avto_yopish'   => '0',
    'xizmat_foiz'         => '10',
    // Oshxona ovozli o'qish (TTS)
    'tts_yoniq'           => '0',
    'tts_provider'        => 'off',
    'tts_ovoz'            => '',
    'tts_region'          => '',
    'tts_endpoint'        => '',
    'tts_auth'            => 'raw',
    'tts_max_item'        => '4',
    // AI Yordamchi (biznes tahlilchi)
    'ai_yoniq'            => '0',
    'ai_provider'         => 'off',
    'ai_key_claude'       => '',
    'ai_key_or'           => '',
    'ai_model'            => 'google/gemini-3.7-flash',
    'ai_effort'           => 'medium',
    'ai_max_tokens'       => '16000',
    'ai_qadam'            => '8',
    'ai_kunlik'           => '0',
    'ai_tarix'            => '10',
];
foreach ($defaults as $k => $v) {
    if (!isset($soz[$k])) $soz[$k] = $v;   // faqat DB da butunlay yo'q bo'lsa — bo'shni tegmaymiz
}

// Sahifadagi bo'limlar — nav va scroll-spy shu ro'yxatdan quriladi
$bolimlar = [
    'dukon'  => ['Do\'kon',  'shop'],
    'chek'   => ['Chek',     'receipt'],
    'savdo'  => ['Savdo',    'bag-fill'],
    'ovoz'   => ['Ovoz',     'volume-up-fill'],
    'ai'     => ['AI',       'stars'],
    'tizim'  => ['Tizim',    'info-circle-fill'],
];

// AI bo'limi uchun oldindan hisoblab qo'yamiz
$k_claude = $soz['ai_key_claude'];
$k_or     = $soz['ai_key_or'];
$ai_ogoh  = im_ai_model_ogoh();
$ai_qisqa = im_ai_model_royxat();
$ai_qisqa_id = array_column($ai_qisqa, 'id');
$ai_mv    = $soz['ai_model'];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tizim sozlamalari | IMezon</title>
<script>/* FOUC oldini olish — CSS'dan oldin tema o'rnatiladi */
try{var t=localStorage.getItem('im_theme');document.documentElement.setAttribute('data-theme',t==='dark'?'dark':'light');}catch(e){document.documentElement.setAttribute('data-theme','light');}</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
<style>
/* ── Tizim sozlamalari sahifasi ── */
.soz-wrap{max-width:840px;margin:0 auto}

.soz-nav{position:sticky;top:var(--navbar-h);z-index:20;display:flex;gap:6px;
         flex-wrap:wrap;padding:10px 0;background:var(--bg);
         border-bottom:1px solid var(--border-light);margin-bottom:16px}
@media(max-width:600px){
  .soz-nav{flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch;
           scrollbar-width:none}
  .soz-nav::-webkit-scrollbar{display:none}
}
.soz-nav a{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;font-weight:600;
           padding:5px 12px;border-radius:20px;color:var(--text-2);text-decoration:none;
           border:1px solid var(--border);background:var(--card);white-space:nowrap;
           flex:0 0 auto;transition:var(--transition)}
.soz-nav a:hover{border-color:var(--accent);color:var(--text)}
.soz-nav a.aktiv{background:var(--accent);color:var(--primary);border-color:var(--accent)}

.soz-card{margin-bottom:16px;scroll-margin-top:calc(var(--navbar-h) + 58px)}
.soz-card .im-card-header i{color:var(--accent-dark)}

.im-hint{display:block;font-size:11.5px;line-height:1.5;color:var(--muted);margin-top:3px}
.im-hint a{color:var(--accent-dark)}
.im-hint code{background:var(--bg2);padding:1px 4px;border-radius:4px;font-size:11px}

.im-note{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);
         padding:9px 12px;font-size:12px;line-height:1.6;color:var(--text-2)}
.im-note code{background:var(--card);padding:1px 5px;border-radius:4px}
.im-note--ai{display:flex;flex-wrap:wrap;gap:4px 14px;align-items:baseline}

.soz-field{display:flex;flex-direction:column;gap:5px;margin-bottom:15px}
.soz-field:last-child{margin-bottom:0}
.soz-row{display:grid;gap:14px;margin-bottom:15px}
.soz-row:last-child{margin-bottom:0}
.soz-row.c2{grid-template-columns:1fr 1fr}
.soz-row.c3{grid-template-columns:1fr 1fr 1fr}
@media(max-width:600px){.soz-row.c2,.soz-row.c3{grid-template-columns:1fr}}

.soz-chek-preview{font-family:'Courier New',ui-monospace,monospace;font-size:12px;
                  line-height:1.6;text-align:center;background:var(--card);
                  border:1px dashed var(--border);border-radius:8px;padding:14px 12px;color:var(--text)}
.soz-chek-preview hr{border:0;border-top:1px dashed var(--border);margin:6px 0}

.soz-stat{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
@media(max-width:600px){.soz-stat{grid-template-columns:repeat(2,1fr)}}
.soz-stat > div{background:var(--bg2);border-radius:var(--radius-sm);padding:11px 8px;text-align:center}
.soz-stat .k{font-size:10.5px;color:var(--muted);display:block;margin-bottom:2px}
.soz-stat .v{font-size:14px;font-weight:700;color:var(--text)}

/* Saqlash tugmasi — saqlanmagan o'zgarish bo'lsa yonadi */
#btn-save-all{transition:box-shadow .2s}
#btn-save-all.iflos{box-shadow:0 0 0 3px rgba(226,185,111,.45)}
.soz-diff{font-size:11.5px;color:var(--accent-dark);margin-right:2px;align-self:center;white-space:nowrap}

.soz-test-natija pre{white-space:pre-wrap;word-break:break-word;max-height:170px;overflow:auto;
                     background:var(--bg2);padding:8px 10px;border-radius:6px;margin-top:6px;font-size:11px}
</style>
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title"><i class="bi bi-gear-fill me-1"></i> Tizim sozlamalari</div>
    <div class="im-topbar-actions">
      <span class="soz-diff" id="soz-diff" hidden></span>
      <button class="im-btn im-btn-primary im-btn-sm" id="btn-save-all">
        <i class="bi bi-floppy-fill"></i> Saqlash
      </button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>

  <main class="im-content">
    <div class="soz-wrap">

      <nav class="soz-nav" id="soz-nav">
        <?php foreach ($bolimlar as $id => [$nom, $ikon]): ?>
        <a href="#<?= $id ?>" data-bolim="<?= $id ?>"><i class="bi bi-<?= $ikon ?>"></i> <?= $nom ?></a>
        <?php endforeach; ?>
      </nav>

      <form id="soz-form" autocomplete="off">

      <!-- ═══ DO'KON MA'LUMOTLARI ═══ -->
      <section class="soz-card" id="dukon">
        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-shop"></i>
            <span class="im-card-title">Do'kon ma'lumotlari</span>
          </div>
          <div class="im-card-body">
            <div class="soz-field">
              <label class="im-label" for="dukon_nomi">Do'kon nomi *</label>
              <input class="im-input" type="text" id="dukon_nomi" name="dukon_nomi"
                     value="<?= im_f($soz['dukon_nomi']) ?>" placeholder="IMezon" required>
              <span class="im-hint">POS ekrani va barcha cheklarda ko'rinadi</span>
            </div>
            <div class="soz-field">
              <label class="im-label" for="dukon_manzil">Manzil</label>
              <input class="im-input" type="text" id="dukon_manzil" name="dukon_manzil"
                     value="<?= im_f($soz['dukon_manzil']) ?>" placeholder="Shahar, ko'cha...">
            </div>
            <div class="soz-row c3">
              <div class="soz-field">
                <label class="im-label" for="dukon_telefon">Telefon</label>
                <input class="im-input" type="text" id="dukon_telefon" name="dukon_telefon"
                       value="<?= im_f($soz['dukon_telefon']) ?>" placeholder="+998 71 123-45-67">
              </div>
              <div class="soz-field">
                <label class="im-label" for="dukon_url">Web sayt</label>
                <input class="im-input" type="text" id="dukon_url" name="dukon_url"
                       value="<?= im_f($soz['dukon_url']) ?>" placeholder="imezon.uz">
              </div>
              <div class="soz-field">
                <label class="im-label" for="dukon_valyuta">Valyuta belgisi</label>
                <input class="im-input" type="text" id="dukon_valyuta" name="dukon_valyuta"
                       value="<?= im_f($soz['dukon_valyuta']) ?>" placeholder="so'm" maxlength="8">
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ═══ CHEK SOZLAMALARI ═══ -->
      <section class="soz-card" id="chek">
        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-receipt"></i>
            <span class="im-card-title">Chek sozlamalari</span>
          </div>
          <div class="im-card-body">
            <div class="soz-row c2">
              <div class="soz-field">
                <label class="im-label" for="chek_izoh">Chek pastki matn (izoh)</label>
                <input class="im-input" type="text" id="chek_izoh" name="chek_izoh"
                       value="<?= im_f($soz['chek_izoh']) ?>" placeholder="Tovar sifatiga kafolat beriladi">
                <span class="im-hint">Chek pastida "rahmat" dan oldin chiqadi</span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="chek_rahmat">Rahmat matni</label>
                <input class="im-input" type="text" id="chek_rahmat" name="chek_rahmat"
                       value="<?= im_f($soz['chek_rahmat']) ?>" placeholder="Xaridingiz uchun rahmat!">
              </div>
            </div>
            <div class="soz-row c2">
              <div class="soz-field">
                <label class="im-label" for="chek_telegram"><i class="bi bi-telegram" style="color:#229ED9"></i> Telegram</label>
                <input class="im-input" type="text" id="chek_telegram" name="chek_telegram"
                       value="<?= im_f($soz['chek_telegram']) ?>" placeholder="@imezon_uz">
              </div>
              <div class="soz-field">
                <label class="im-label" for="chek_instagram"><i class="bi bi-instagram" style="color:#E1306C"></i> Instagram</label>
                <input class="im-input" type="text" id="chek_instagram" name="chek_instagram"
                       value="<?= im_f($soz['chek_instagram']) ?>" placeholder="@imezon.uz">
              </div>
            </div>

            <div class="soz-field">
              <span class="im-label">Chek ko'rinishi (jonli)</span>
              <div class="soz-chek-preview" id="chek-preview">
                <strong data-prev="dukon_nomi"><?= im_f($soz['dukon_nomi']) ?></strong><br>
                <span data-prev="dukon_manzil"><?= im_f($soz['dukon_manzil']) ?></span><br>
                Tel: <span data-prev="dukon_telefon"><?= im_f($soz['dukon_telefon']) ?></span>
                <hr>
                <span data-prev="chek_izoh"><?= im_f($soz['chek_izoh']) ?></span><br>
                <span data-prev="chek_rahmat"><?= im_f($soz['chek_rahmat']) ?></span>
                <div data-prev-wrap="chek_telegram" style="color:#229ED9<?= $soz['chek_telegram']==='' ? ';display:none' : '' ?>">✈ <span data-prev="chek_telegram"><?= im_f($soz['chek_telegram']) ?></span></div>
                <div data-prev-wrap="chek_instagram" style="color:#E1306C<?= $soz['chek_instagram']==='' ? ';display:none' : '' ?>">📸 <span data-prev="chek_instagram"><?= im_f($soz['chek_instagram']) ?></span></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ═══ SAVDO SOZLAMALARI ═══ -->
      <section class="soz-card" id="savdo">
        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-bag-fill"></i>
            <span class="im-card-title">Savdo sozlamalari</span>
          </div>
          <div class="im-card-body">
            <div class="soz-row c2">
              <div class="soz-field">
                <label class="im-label" for="kassir_max_chegirma">Kassir max chegirma (%)</label>
                <input class="im-input num" type="number" id="kassir_max_chegirma" name="kassir_max_chegirma"
                       value="<?= im_f($soz['kassir_max_chegirma']) ?>" min="0" max="50">
                <span class="im-hint">Kassir berishi mumkin bo'lgan eng yuqori chegirma</span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="nasiya_kun">Nasiya muddati (kun)</label>
                <input class="im-input num" type="number" id="nasiya_kun" name="nasiya_kun"
                       value="<?= im_f($soz['nasiya_kun']) ?>" min="1" max="365">
                <span class="im-hint">Default qaytarish sanasi (28, 30...)</span>
              </div>
            </div>
            <div class="soz-row c2">
              <div class="soz-field">
                <label class="im-label" for="ulg_min_soni">Ulgurji sotuv minimal soni</label>
                <input class="im-input num" type="number" id="ulg_min_soni" name="ulg_min_soni"
                       value="<?= im_f($soz['ulg_min_soni']) ?>" min="1">
                <span class="im-hint">Bu sondan ko'p bo'lsa ulgurji narx qo'llanadi</span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="smena_avto_yopish">Smena avtomatik yopilsin</label>
                <select class="im-select" id="smena_avto_yopish" name="smena_avto_yopish">
                  <option value="0" <?= $soz['smena_avto_yopish']=='0'?'selected':'' ?>>Yo'q (qo'lda)</option>
                  <option value="1" <?= $soz['smena_avto_yopish']=='1'?'selected':'' ?>>Ha (tungi 00:00)</option>
                </select>
              </div>
            </div>
            <div class="soz-field">
              <label class="im-label" for="xizmat_foiz">Xizmat haqi — otsluga (%)</label>
              <input class="im-input num" type="number" id="xizmat_foiz" name="xizmat_foiz"
                     value="<?= im_f($soz['xizmat_foiz']) ?>" min="0" max="100" step="0.5" style="max-width:140px">
              <span class="im-hint">
                Stol buyurtmalariga kassada avtomatik qo'shiladi (kassir o'chira oladi).
                Olib ketish va dastavkaga qo'shilmaydi. <code>0</code> — o'chirilgan.
              </span>
            </div>
          </div>
        </div>
      </section>

      <!-- ═══ OSHXONA — OVOZLI O'QISH ═══ -->
      <section class="soz-card" id="ovoz">
        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-volume-up-fill"></i>
            <span class="im-card-title">Oshxona — ovozli o'qish</span>
          </div>
          <div class="im-card-body">
            <div class="soz-row c2">
              <div class="soz-field">
                <label class="im-label" for="tts_yoniq">Ovozli o'qish</label>
                <select class="im-select" id="tts_yoniq" name="tts_yoniq">
                  <option value="0" <?= $soz['tts_yoniq']=='0'?'selected':'' ?>>O'chirilgan</option>
                  <option value="1" <?= $soz['tts_yoniq']=='1'?'selected':'' ?>>Yoqilgan</option>
                </select>
                <span class="im-hint">Yangi buyurtma kelganda oshpaz ekrani ovoz chiqarib o'qiydi</span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="tts_provider">Provayder</label>
                <select class="im-select" id="tts_provider" name="tts_provider">
                  <option value="off"   <?= $soz['tts_provider']=='off'  ?'selected':'' ?>>— tanlanmagan —</option>
                  <option value="mohir" <?= $soz['tts_provider']=='mohir'?'selected':'' ?>>Mohir.ai (uzbekvoice)</option>
                  <option value="azure" <?= $soz['tts_provider']=='azure'?'selected':'' ?>>Azure Speech</option>
                </select>
              </div>
            </div>

            <div class="soz-field">
              <label class="im-label" for="tts_key">API kalit</label>
              <input class="im-input" type="password" id="tts_key" name="tts_key" autocomplete="new-password"
                     placeholder="<?= !empty($soz['tts_key']) ? '•••• saqlangan (tegmang = bo\'sh)' : 'Kalitni kiriting' ?>">
              <span class="im-hint">Kalit faqat serverda saqlanadi, brauzerga yuborilmaydi.</span>
            </div>

            <div class="soz-row c2">
              <div class="soz-field">
                <label class="im-label" for="tts_ovoz">Ovoz nomi</label>
                <input class="im-input" type="text" id="tts_ovoz" name="tts_ovoz" list="tts-ovozlar"
                       value="<?= im_f($soz['tts_ovoz']) ?>" placeholder="jasur">
                <datalist id="tts-ovozlar">
                  <option value="jasur">Jasur — ishlaydi</option>
                  <option value="sevinch">Sevinch — ishlaydi</option>
                  <option value="kamola">Kamola</option>
                  <option value="shoira">Shoira — hozir ishlamayapti</option>
                  <option value="lola">Lola — hozir ishlamayapti</option>
                  <option value="uz-UZ-MadinaNeural">Azure — Madina</option>
                  <option value="uz-UZ-SardorNeural">Azure — Sardor</option>
                </datalist>
                <span class="im-hint">Sinovda <code>jasur</code> va <code>sevinch</code> ishladi; <code>shoira</code>, <code>lola</code> — Mohir tomonida xato.</span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="tts_region">Region <span class="text-muted">(Azure)</span></label>
                <input class="im-input" type="text" id="tts_region" name="tts_region"
                       value="<?= im_f($soz['tts_region']) ?>" placeholder="westeurope">
              </div>
            </div>

            <div class="soz-row c2">
              <div class="soz-field">
                <label class="im-label" for="tts_auth">Authorization uslubi</label>
                <select class="im-select" id="tts_auth" name="tts_auth">
                  <option value="raw"    <?= $soz['tts_auth']!='bearer'?'selected':'' ?>>Kalitning o'zi (Mohir)</option>
                  <option value="bearer" <?= $soz['tts_auth']=='bearer'?'selected':'' ?>>Bearer &lt;kalit&gt;</option>
                </select>
                <span class="im-hint">401 xato bersa — ikkinchisini sinang</span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="tts_max_item">Nechta taom o'qilsin</label>
                <input class="im-input num" type="number" id="tts_max_item" name="tts_max_item"
                       value="<?= im_f($soz['tts_max_item']) ?>" min="1" max="10">
                <span class="im-hint">Qolgani "va yana N xil taom" bo'lib qisqaradi</span>
              </div>
            </div>

            <div class="soz-field">
              <label class="im-label" for="tts_endpoint">Endpoint <span class="text-muted">(ixtiyoriy)</span></label>
              <input class="im-input" type="text" id="tts_endpoint" name="tts_endpoint"
                     value="<?= im_f($soz['tts_endpoint']) ?>" placeholder="Bo'sh qoldiring — hujjatdagi URL boshqacha bo'lsagina">
            </div>

            <button type="button" class="im-btn im-btn-outline w-100" id="btn-tts-test">
              <i class="bi bi-play-circle-fill"></i> Sinab ko'rish
            </button>
            <div id="tts-natija" class="soz-test-natija mt-2 fs-xs"></div>
            <span class="im-hint mt-2">
              <i class="bi bi-info-circle"></i>
              Tugma avval sozlamani saqlaydi, keyin keshni chetlab API'ga uriladi.
              Sinash uchun "Ovozli o'qish" ni yoqish shart emas.
            </span>
          </div>
        </div>
      </section>

      <!-- ═══ AI YORDAMCHI ═══ -->
      <section class="soz-card" id="ai">
        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-stars"></i>
            <span class="im-card-title">AI Yordamchi (biznes tahlil)</span>
          </div>
          <div class="im-card-body">

            <div class="im-note im-note--ai mb-3">
              <span><b>Holat:</b>
                <?= $soz['ai_provider']!=='off'
                    ? '<span style="color:var(--success)">yoqilgan</span>'
                    : '<span style="color:var(--muted)">o\'chirilgan</span>' ?></span>
              <span>Claude kaliti:
                <?= $k_claude!=='' ? '<code>'.im_f(im_ai_key_mask($k_claude)).'</code>' : '<span style="color:var(--muted)">yo\'q</span>' ?></span>
              <span>OpenRouter kaliti:
                <?= $k_or!=='' ? '<code>'.im_f(im_ai_key_mask($k_or)).'</code>' : '<span style="color:var(--muted)">yo\'q</span>' ?></span>
              <span style="flex-basis:100%">Standart model: <code><?= im_f($ai_mv) ?></code>
                → <?= im_f(im_ai_provayder_nomi($ai_mv)) ?></span>
              <?php if ($ai_ogoh): ?>
              <span style="flex-basis:100%;color:var(--danger)">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= im_f($ai_ogoh) ?></span>
              <?php endif; ?>
            </div>

            <div class="soz-row c2">
              <div class="soz-field">
                <label class="im-label" for="ai_yoniq">Chat sahifasi</label>
                <select class="im-select" id="ai_yoniq" name="ai_yoniq">
                  <option value="0" <?= $soz['ai_yoniq']=='0'?'selected':'' ?>>O'chirilgan</option>
                  <option value="1" <?= $soz['ai_yoniq']=='1'?'selected':'' ?>>Yoqilgan</option>
                </select>
                <span class="im-hint">Admin paneldagi "AI Yordamchi" sahifasi foydalanuvchilar uchun</span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="ai_provider">Bosh kalit</label>
                <select class="im-select" id="ai_provider" name="ai_provider">
                  <option value="off" <?= $soz['ai_provider']=='off'?'selected':'' ?>>O'chirilgan</option>
                  <option value="on"  <?= $soz['ai_provider']!=='off'?'selected':'' ?>>Yoqilgan</option>
                </select>
                <span class="im-hint">O'chirilsa — hech narsa ishlamaydi</span>
              </div>
            </div>

            <div class="soz-row c2">
              <div class="soz-field">
                <label class="im-label" for="ai_key_claude">Claude (Anthropic) kaliti</label>
                <input class="im-input" type="password" id="ai_key_claude" name="ai_key_claude" autocomplete="new-password"
                       placeholder="<?= $k_claude!=='' ? '•••• saqlangan (tegmang = bo\'sh)' : 'sk-ant-...' ?>">
                <span class="im-hint">
                  <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a> —
                  sof ID modellari (<code>claude-sonnet-5</code>). Kesh ishlaydi.
                </span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="ai_key_or">OpenRouter kaliti</label>
                <input class="im-input" type="password" id="ai_key_or" name="ai_key_or" autocomplete="new-password"
                       placeholder="<?= $k_or!=='' ? '•••• saqlangan (tegmang = bo\'sh)' : 'sk-or-v1-...' ?>">
                <span class="im-hint">
                  <a href="https://openrouter.ai/keys" target="_blank" rel="noopener">openrouter.ai/keys</a> —
                  <code>provayder/model</code> (Gemini, DeepSeek, GPT…).
                </span>
              </div>
            </div>
            <div class="im-hint mb-3">
              <i class="bi bi-info-circle"></i> Kalit qaysi model bilan ishlatilishi <b>model qatoridan</b> aniqlanadi:
              <code>/</code> bo'lsa → OpenRouter, bo'lmasa → Claude.
            </div>

            <div class="soz-row c2">
              <div class="soz-field">
                <label class="im-label" for="ai-model-sel">Standart model</label>
                <input type="hidden" name="ai_model" id="ai-model-val" value="<?= im_f($ai_mv) ?>">
                <select class="im-select" id="ai-model-sel">
                  <?php foreach ($ai_qisqa as $m): ?>
                  <option value="<?= im_f($m['id']) ?>" <?= $ai_mv === $m['id'] ? 'selected' : '' ?>>
                    <?= im_f($m['nom']) ?><?= $m['narx'] ? ' · '.im_f($m['narx']) : '' ?>
                  </option>
                  <?php endforeach; ?>
                  <?php if ($ai_mv !== '' && !in_array($ai_mv, $ai_qisqa_id, true)): ?>
                  <option value="<?= im_f($ai_mv) ?>" selected><?= im_f($ai_mv) ?> (joriy)</option>
                  <?php endif; ?>
                  <optgroup label="OpenRouter — to'liq ro'yxat" id="ai-model-or"></optgroup>
                </select>
                <input class="im-input mt-1" type="text" id="ai-model-inp" hidden
                       value="<?= im_f($ai_mv) ?>" placeholder="masalan: anthropic/claude-opus-5">
                <div class="d-flex gap-2 mt-1 align-items-center flex-wrap">
                  <button type="button" class="im-btn im-btn-ghost im-btn-sm" id="ai-model-yukla">
                    <i class="bi bi-arrow-repeat"></i> OpenRouter modellari
                  </button>
                  <a href="#" class="fs-xs" id="ai-model-qolda">✎ qo'lda kiritish</a>
                </div>
                <span id="ai-model-ogoh" class="fs-xs" style="color:var(--danger)"></span>
                <span class="im-hint">
                  Bitta OpenRouter kaliti bilan hammasi: <code>anthropic/claude-opus-5</code>,
                  <code>google/gemini-3.7-flash</code> … yoki
                  <a href="https://openrouter.ai/models" target="_blank" rel="noopener">openrouter.ai/models</a>.
                </span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="ai_effort">Standart chuqurlik</label>
                <select class="im-select" id="ai_effort" name="ai_effort">
                  <option value="low"    <?= $soz['ai_effort']=='low'   ?'selected':'' ?>>Past — tez va arzon</option>
                  <option value="medium" <?= $soz['ai_effort']=='medium'?'selected':'' ?>>O'rta — tavsiya etiladi</option>
                  <option value="high"   <?= $soz['ai_effort']=='high'  ?'selected':'' ?>>Yuqori — chuqurroq tahlil</option>
                  <option value="xhigh"  <?= $soz['ai_effort']=='xhigh' ?'selected':'' ?>>Juda yuqori</option>
                  <option value="max"    <?= $soz['ai_effort']=='max'   ?'selected':'' ?>>Maksimal — sekin, qimmat</option>
                </select>
                <span class="im-hint">Chatda har suhbat uchun alohida o'zgartiriladi</span>
              </div>
            </div>

            <div class="soz-row c3">
              <div class="soz-field">
                <label class="im-label" for="ai_max_tokens">Javob limiti</label>
                <input class="im-input num" type="number" id="ai_max_tokens" name="ai_max_tokens"
                       value="<?= im_f($soz['ai_max_tokens']) ?>" min="2000" max="64000" step="1000">
                <span class="im-hint">token</span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="ai_qadam">Qadam chegarasi</label>
                <input class="im-input num" type="number" id="ai_qadam" name="ai_qadam"
                       value="<?= im_f($soz['ai_qadam']) ?>" min="1" max="15">
                <span class="im-hint">Bitta savolda necha marta bazadan ma'lumot olsin</span>
              </div>
              <div class="soz-field">
                <label class="im-label" for="ai_tarix">Suhbat xotirasi</label>
                <input class="im-input num" type="number" id="ai_tarix" name="ai_tarix"
                       value="<?= im_f($soz['ai_tarix']) ?>" min="0" max="40" step="2">
                <span class="im-hint">0 — har savol mustaqil</span>
              </div>
            </div>

            <div class="soz-field">
              <label class="im-label" for="ai_kunlik">Kunlik xulosa</label>
              <select class="im-select" id="ai_kunlik" name="ai_kunlik" style="max-width:320px">
                <option value="0" <?= $soz['ai_kunlik']=='0'?'selected':'' ?>>O'chirilgan</option>
                <option value="1" <?= $soz['ai_kunlik']=='1'?'selected':'' ?>>Dashboardda ko'rsatilsin</option>
              </select>
              <span class="im-hint">Kun yakuni xulosasi. Kuniga BIR marta yasaladi va saqlanadi — dashboard har ochilganda pul ketmaydi.</span>
            </div>

            <button type="button" class="im-btn im-btn-outline w-100 mt-3" id="btn-ai-test">
              <i class="bi bi-plug-fill"></i> Ulanishni tekshirish (standart model)
            </button>
            <div id="ai-natija" class="soz-test-natija mt-2 fs-xs"></div>
            <span class="im-hint mt-2">
              <i class="bi bi-info-circle"></i>
              Tugma avval sozlamani saqlaydi, keyin API'ga uriladi. Yoqish shart emas.
            </span>
          </div>
        </div>
      </section>

      <!-- ═══ TIZIM HAQIDA ═══ -->
      <section class="soz-card" id="tizim">
        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-info-circle-fill"></i>
            <span class="im-card-title">Tizim haqida</span>
          </div>
          <div class="im-card-body">
            <?php
            $php_v = PHP_VERSION;
            $db_v  = (string)$db->val("SELECT VERSION()");
            $c_mah = (int)$db->val("SELECT COUNT(*) FROM im_mahsulotlar WHERE status=1");
            $c_sot = (int)$db->val("SELECT COUNT(*) FROM im_sotuvlar");
            $c_xod = (int)$db->val("SELECT COUNT(*) FROM im_xodimlar WHERE status=1");
            $stat = [
                ['IMezon POS', 'v' . im_VERSION],
                ['PHP',        $php_v],
                ['MySQL',      preg_replace('/-.*/', '', $db_v)],
                ['Mahsulot',   number_format($c_mah, 0, '.', ' ')],
                ['Sotuv',      number_format($c_sot, 0, '.', ' ')],
                ['Xodim',      number_format($c_xod, 0, '.', ' ')],
            ];
            ?>
            <div class="soz-stat">
              <?php foreach ($stat as [$k, $v]): ?>
              <div><span class="k"><?= im_f($k) ?></span><span class="v"><?= im_f($v) ?></span></div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

      </form>
    </div>
  </main>
</div>
</div>
<div id="im-toast-container"></div>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
(function(){
  const form = document.getElementById('soz-form');
  const btn  = document.getElementById('btn-save-all');
  const diff = document.getElementById('soz-diff');

  // ── Yorliqlarni inputlarga bog'lash (a11y) — har ehtimolga qarshi ──
  form.querySelectorAll('label.im-label:not([for])').forEach(lbl => {
    let el = lbl.nextElementSibling;
    while (el && !/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) el = el.nextElementSibling;
    if (el) { if (!el.id) el.id = 'f_' + (el.name || Math.random().toString(36).slice(2)); lbl.htmlFor = el.id; }
  });

  // ── Maydonlarni yig'ish (parol maydonlari bo'sh bo'lsa yubormaymiz) ──
  function yigib(){
    const d = {};
    form.querySelectorAll('[name]').forEach(el => {
      if (el.type === 'password' && el.value === '') return;   // "tegmang" = eskisi qoladi
      d[el.name] = el.value;
    });
    return d;
  }

  // ── O'zgargan-o'zgarmagan holati ──
  let boshlangich = JSON.stringify(yigib());
  function snapshot(){ boshlangich = JSON.stringify(yigib()); holatYangila(); }
  function iflosmi(){ return JSON.stringify(yigib()) !== boshlangich; }
  function holatYangila(){
    const x = iflosmi();
    btn.classList.toggle('iflos', x);
    diff.hidden = !x;
    diff.textContent = x ? 'saqlanmagan' : '';
  }
  form.addEventListener('input', holatYangila);
  form.addEventListener('change', holatYangila);

  let ketyapmiz = false;   // saqlashdan keyingi reload — ogohlantirishni o'tkazamiz
  window.addEventListener('beforeunload', e => {
    if (!ketyapmiz && iflosmi()) { e.preventDefault(); e.returnValue = ''; }
  });

  // ── Chek preview (jonli) ──
  document.querySelectorAll('#chek-preview [data-prev]').forEach(node => {
    const nom = node.dataset.prev;
    const src = form.querySelector('[name="' + nom + '"]');
    if (!src) return;
    src.addEventListener('input', () => {
      node.textContent = src.value || node.getAttribute('data-fallback') || '';
      const wrap = document.querySelector('#chek-preview [data-prev-wrap="' + nom + '"]');
      if (wrap) wrap.style.display = src.value.trim() === '' ? 'none' : '';
    });
  });

  // ── Saqlash ──
  async function saqla(){
    if (btn.disabled) return;
    if (!form.reportValidity()) return;
    btn.disabled = true;
    const o = btn.innerHTML;
    btn.innerHTML = '<span class="im-spinner"></span> Saqlanmoqda...';
    try {
      const res = await IMAjax.post(window.im_BASE + 'admin/ajax/sozlama-save.php', yigib());
      if (res.status === 'ok') {
        ketyapmiz = true;
        NHToast.success(res.msg || 'Saqlandi');
        setTimeout(() => location.reload(), 700);
        return;   // reload — tugmani tiklamaymiz
      }
      NHToast.error(res.msg || 'Saqlanmadi');
    } catch (e) {
      NHToast.error('Tarmoq xatosi');
    }
    btn.disabled = false;
    btn.innerHTML = o;
  }
  btn.addEventListener('click', saqla);
  form.addEventListener('submit', e => { e.preventDefault(); saqla(); });
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saqla(); }
  });

  holatYangila();

  // ── Bo'lim navigatsiyasi + scroll-spy ──
  const nav = document.getElementById('soz-nav');
  const links = [...nav.querySelectorAll('a')];
  const seksiyalar = links.map(a => document.getElementById(a.dataset.bolim)).filter(Boolean);
  links.forEach(a => a.addEventListener('click', e => {
    e.preventDefault();
    const sek = document.getElementById(a.dataset.bolim);
    if (!sek) return;
    sek.scrollIntoView({behavior:'smooth', block:'start'});
    try { history.replaceState(null, '', '#' + a.dataset.bolim); } catch (err) {}
  }));
  const spy = new IntersectionObserver(ents => {
    ents.forEach(en => {
      if (!en.isIntersecting) return;
      links.forEach(a => a.classList.toggle('aktiv', a.dataset.bolim === en.target.id));
    });
  }, {rootMargin: '-45% 0px -50% 0px'});
  seksiyalar.forEach(s => spy.observe(s));

  // ── TTS sinovi ──
  document.getElementById('btn-tts-test')?.addEventListener('click', async () => {
    const b = document.getElementById('btn-tts-test');
    const box = document.getElementById('tts-natija');
    const orig = b.innerHTML;
    b.disabled = true; b.innerHTML = '<span class="im-spinner"></span> Sinalmoqda...'; box.innerHTML = '';
    try {
      await IMAjax.post(window.im_BASE + 'admin/ajax/sozlama-save.php', yigib());
      snapshot();   // saqlandi — "o'zgargan" belgisini tozalaymiz
      const res = await IMAjax.post(window.im_BASE + 'admin/ajax/tts-test.php', {});
      if (res.status === 'ok') {
        box.innerHTML = '<div class="text-success"><i class="bi bi-check-circle-fill"></i> ' + esc(res.msg) + '</div>'
          + '<div class="text-muted mt-1">"' + esc(res.matn || '') + '"</div>'
          + '<audio controls autoplay src="' + esc(res.audio) + '" style="width:100%;margin-top:6px"></audio>';
        NHToast.success('Ovoz keldi — eshiting');
      } else {
        box.innerHTML = '<div class="text-danger"><i class="bi bi-x-circle-fill"></i> ' + esc(res.msg || 'Xato') + '</div>'
          + (res.javob ? '<pre>' + esc(res.javob) + '</pre>' : '');
        NHToast.error(res.msg || 'Muvaffaqiyatsiz');
      }
    } catch (e) { box.innerHTML = '<div class="text-danger">Tarmoq xatosi</div>'; }
    b.disabled = false; b.innerHTML = orig;
  });

  // ── AI ulanish sinovi ──
  document.getElementById('btn-ai-test')?.addEventListener('click', async () => {
    const b = document.getElementById('btn-ai-test');
    const box = document.getElementById('ai-natija');
    const orig = b.innerHTML;
    b.disabled = true; b.innerHTML = '<span class="im-spinner"></span> Tekshirilmoqda...'; box.innerHTML = '';
    try {
      await IMAjax.post(window.im_BASE + 'admin/ajax/sozlama-save.php', yigib());
      snapshot();   // saqlandi — "o'zgargan" belgisini tozalaymiz
      const res = await IMAjax.post(window.im_BASE + 'admin/ajax/ai-test.php', {});
      if (res.status === 'ok') {
        const t = res.tokens || {};
        box.innerHTML = '<div class="text-success"><i class="bi bi-check-circle-fill"></i> ' + esc(res.msg) + '</div>'
          + '<div class="text-muted mt-1">Model: <code>' + esc(res.model) + '</code> · "' + esc(res.matn || '') + '"</div>'
          + '<div class="text-muted">Token: ' + (t.in || 0) + ' / ' + (t.out || 0) + '</div>'
          + '<div class="mt-1"><a href="' + window.im_BASE + 'admin/ai.php">AI Yordamchi sahifasiga →</a></div>';
        NHToast.success('Ulanish ishlayapti');
      } else {
        box.innerHTML = '<div class="text-danger"><i class="bi bi-x-circle-fill"></i> ' + esc(res.msg || 'Xato') + '</div>'
          + (res.javob ? '<pre>' + esc(res.javob) + '</pre>' : '');
        NHToast.error('Ulanmadi');
      }
    } catch (e) { box.innerHTML = '<div class="text-danger">Tarmoq xatosi</div>'; }
    b.disabled = false; b.innerHTML = orig;
  });

  function esc(s){
    return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── AI standart model tanlash ──
  (function(){
    const val = document.getElementById('ai-model-val');
    const sel = document.getElementById('ai-model-sel');
    const inp = document.getElementById('ai-model-inp');
    const orGrp = document.getElementById('ai-model-or');
    const ldBtn = document.getElementById('ai-model-yukla');
    const qolda = document.getElementById('ai-model-qolda');
    const ogoh  = document.getElementById('ai-model-ogoh');
    const kClaude = form.querySelector('[name=ai_key_claude]');
    const kOr     = form.querySelector('[name=ai_key_or]');
    if (!val || !sel) return;

    const inSel = v => [...sel.options].some(o => o.value === v);
    const bor = el => !!el && (el.value.trim() !== '' || /••••|saqlangan/.test(el.placeholder || ''));

    function tekshir(){
      const v = (val.value || '').trim();
      if (!v) { ogoh.textContent = ''; return; }
      const or = v.includes('/');
      if (or && !bor(kOr))          ogoh.textContent = '⚠ Bu model OpenRouter\'niki — OpenRouter kaliti kiritilmagan';
      else if (!or && !bor(kClaude)) ogoh.textContent = '⚠ Bu model Anthropic\'niki — Claude kaliti kiritilmagan';
      else ogoh.textContent = '';
    }

    sel.addEventListener('change', () => { val.value = sel.value; inp.value = sel.value; tekshir(); holatYangila(); });
    inp.addEventListener('input',  () => { val.value = inp.value.trim(); tekshir(); holatYangila(); });
    if (kClaude) kClaude.addEventListener('input', tekshir);
    if (kOr)     kOr.addEventListener('input', tekshir);
    qolda.addEventListener('click', e => {
      e.preventDefault();
      const selKor = !sel.hidden;
      sel.hidden = selKor; inp.hidden = !selKor;
      qolda.textContent = selKor ? '⌄ ro\'yxatdan tanlash' : '✎ qo\'lda kiritish';
      if (!selKor && inSel(val.value)) sel.value = val.value;
    });

    ldBtn.addEventListener('click', async () => {
      ldBtn.disabled = true; const o = ldBtn.innerHTML;
      ldBtn.innerHTML = '<span class="im-spinner"></span> yuklanmoqda...';
      try {
        const r = await IMAjax.post(window.im_BASE + 'admin/ajax/ai-modellar.php', {});
        if (r.status === 'ok') {
          orGrp.innerHTML = '';
          (r.modellar || []).forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            const narx = (m.kirish != null) ? '  $' + m.kirish + '/$' + m.chiqish : '';
            opt.textContent = m.id + narx + (m.tool ? '' : '  ⚠ tools yo\'q');
            if (!m.tool) opt.style.color = '#b91c1c';
            orGrp.appendChild(opt);
          });
          if (inSel(val.value)) sel.value = val.value;
          NHToast.success((r.soni || 0) + ' ta model yuklandi');
        } else NHToast.error(r.msg || 'Xato');
      } catch (e) { NHToast.error('Ulanmadi'); }
      ldBtn.disabled = false; ldBtn.innerHTML = o;
    });

    tekshir();
  })();

})();
</script>
</body>
</html>
