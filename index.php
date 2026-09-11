<?php
?>
<!DOCTYPE html>
<html lang="uz">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IMezon — Biznes Boshqaruv Tizimi</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --gold: #e2b96f;
      --gold-dark: #c9a058;
      --gold-light: #f5d99a;
      --navy: #1a1a2e;
      --navy2: #16213e;
      --navy3: #0f3460;
      --white: #ffffff;
      --gray: #94a3b8;
      --success: #10b981;
      --danger: #ef4444;
      --info: #3b82f6;
      --purple: #8b5cf6;
    }

    html,
    body {
      width: 100%;
      height: 100%;
      font-family: 'Inter', sans-serif;
      background: var(--navy);
      overflow: hidden;
      color: var(--white);
    }

    /* ── SLIDESHOW ── */
    .slides {
      width: 100%;
      height: 100vh;
      position: relative;
    }

    .slide {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 60px;
      opacity: 0;
      pointer-events: none;
      transition: opacity .6s ease, transform .6s ease;
      transform: translateX(60px);
    }

    .slide.active {
      opacity: 1;
      pointer-events: all;
      transform: translateX(0);
    }

    .slide.prev {
      opacity: 0;
      transform: translateX(-60px);
    }

    /* ── NAVIGATION ── */
    .nav {
      position: fixed;
      bottom: 32px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      align-items: center;
      gap: 16px;
      background: rgba(255, 255, 255, .07);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, .12);
      padding: 12px 24px;
      border-radius: 50px;
      z-index: 100;
    }

    .nav-btn {
      background: rgba(226, 185, 111, .15);
      border: 1px solid rgba(226, 185, 111, .3);
      color: var(--gold);
      padding: 8px 20px;
      border-radius: 30px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      transition: all .2s;
      font-family: 'Inter', sans-serif;
    }

    .nav-btn:hover {
      background: var(--gold);
      color: var(--navy);
    }

    .nav-dots {
      display: flex;
      gap: 6px;
    }

    .dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: rgba(255, 255, 255, .2);
      cursor: pointer;
      transition: all .3s;
    }

    .dot.active {
      background: var(--gold);
      width: 24px;
      border-radius: 4px;
    }

    .slide-counter {
      font-size: 13px;
      color: var(--gray);
      min-width: 44px;
      text-align: center;
    }

    /* ── PROGRESS BAR ── */
    .progress {
      position: fixed;
      top: 0;
      left: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--gold), var(--gold-light));
      transition: width .4s ease;
      z-index: 100;
    }

    /* ── BACKGROUNDS ── */
    .bg-dark {
      background: linear-gradient(135deg, var(--navy) 0%, var(--navy2) 50%, var(--navy3) 100%);
    }

    .bg-dark::before {
      content: '';
      position: absolute;
      top: -30%;
      right: -20%;
      width: 60vw;
      height: 60vw;
      background: radial-gradient(circle, rgba(226, 185, 111, .08) 0%, transparent 60%);
      border-radius: 50%;
      pointer-events: none;
    }

    .bg-dark::after {
      content: '';
      position: absolute;
      bottom: -20%;
      left: -10%;
      width: 40vw;
      height: 40vw;
      background: radial-gradient(circle, rgba(15, 52, 96, .4) 0%, transparent 60%);
      border-radius: 50%;
      pointer-events: none;
    }

    .bg-gold {
      background: linear-gradient(135deg, #c9a058 0%, #e2b96f 40%, #f5d99a 100%);
    }

    .bg-navy3 {
      background: linear-gradient(135deg, #0f3460 0%, #16213e 100%);
    }

    /* ── COMPONENTS ── */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 16px;
      border-radius: 30px;
      font-size: 13px;
      font-weight: 600;
      border: 1px solid currentColor;
    }

    .badge-gold {
      color: var(--gold);
      border-color: rgba(226, 185, 111, .4);
      background: rgba(226, 185, 111, .1);
    }

    .badge-white {
      color: rgba(255, 255, 255, .8);
      border-color: rgba(255, 255, 255, .2);
      background: rgba(255, 255, 255, .08);
    }

    .tag {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .5px;
      text-transform: uppercase;
    }

    .tag-gold {
      background: rgba(226, 185, 111, .2);
      color: var(--gold);
    }

    .tag-green {
      background: rgba(16, 185, 129, .15);
      color: var(--success);
    }

    .tag-blue {
      background: rgba(59, 130, 246, .15);
      color: var(--info);
    }

    .tag-purple {
      background: rgba(139, 92, 246, .15);
      color: var(--purple);
    }

    .tag-red {
      background: rgba(239, 68, 68, .15);
      color: var(--danger);
    }

    .card {
      background: rgba(255, 255, 255, .06);
      border: 1px solid rgba(255, 255, 255, .1);
      border-radius: 20px;
      padding: 32px;
      backdrop-filter: blur(10px);
      transition: transform .2s, border-color .2s;
    }

    .card:hover {
      transform: translateY(-4px);
      border-color: rgba(226, 185, 111, .3);
    }

    .glow-line {
      width: 80px;
      height: 4px;
      border-radius: 2px;
      background: linear-gradient(90deg, var(--gold), transparent);
      margin-bottom: 24px;
    }

    /* ── TYPOGRAPHY ── */
    .title-xl {
      font-size: clamp(40px, 7vw, 80px);
      font-weight: 900;
      line-height: 1.05;
      letter-spacing: -2px;
    }

    .title-lg {
      font-size: clamp(28px, 4vw, 48px);
      font-weight: 800;
      line-height: 1.1;
      letter-spacing: -1px;
    }

    .title-md {
      font-size: clamp(20px, 2.5vw, 32px);
      font-weight: 700;
      line-height: 1.2;
    }

    .gold-text {
      background: linear-gradient(135deg, var(--gold-light), var(--gold));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .muted {
      color: var(--gray);
    }

    .text-sm {
      font-size: 14px;
    }

    .text-lg {
      font-size: 18px;
      line-height: 1.7;
    }

    /* ── GRID ── */
    .grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      width: 100%;
    }

    .grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      width: 100%;
    }

    .grid-4 {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      width: 100%;
    }

    /* ── ICON BOX ── */
    .icon-box {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      flex-shrink: 0;
      background: rgba(226, 185, 111, .12);
      border: 1px solid rgba(226, 185, 111, .2);
    }

    /* ── STAT ── */
    .stat-num {
      font-size: clamp(28px, 4vw, 52px);
      font-weight: 900;
      line-height: 1;
    }

    .stat-lbl {
      font-size: 13px;
      color: var(--gray);
      margin-top: 4px;
    }

    /* ── LIST ── */
    .check-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .check-list li {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      font-size: 15px;
      line-height: 1.5;
    }

    .check-list li::before {
      content: '✓';
      color: var(--gold);
      font-weight: 700;
      flex-shrink: 0;
      margin-top: 1px;
    }

    /* ── MODULE CARD ── */
    .mod-card {
      background: rgba(255, 255, 255, .05);
      border: 1px solid rgba(255, 255, 255, .08);
      border-radius: 16px;
      padding: 24px;
      display: flex;
      gap: 16px;
      align-items: flex-start;
      transition: all .25s;
    }

    .mod-card:hover {
      background: rgba(226, 185, 111, .08);
      border-color: rgba(226, 185, 111, .25);
      transform: translateX(4px);
    }

    /* ── FLOW ── */
    .flow {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .flow-step {
      background: rgba(255, 255, 255, .07);
      border: 1px solid rgba(255, 255, 255, .12);
      border-radius: 12px;
      padding: 14px 20px;
      font-size: 14px;
      font-weight: 600;
      text-align: center;
      min-width: 110px;
    }

    .flow-arrow {
      color: var(--gold);
      font-size: 20px;
    }

    /* ── TABLE ── */
    .feat-table {
      width: 100%;
      border-collapse: collapse;
    }

    .feat-table th {
      text-align: left;
      padding: 12px 16px;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--gray);
      border-bottom: 1px solid rgba(255, 255, 255, .08);
    }

    .feat-table td {
      padding: 14px 16px;
      font-size: 14px;
      border-bottom: 1px solid rgba(255, 255, 255, .05);
    }

    .feat-table tr:last-child td {
      border-bottom: none;
    }

    /* ── QUOTE ── */
    .quote {
      font-size: clamp(18px, 2.5vw, 28px);
      font-weight: 600;
      line-height: 1.5;
      color: rgba(255, 255, 255, .9);
      text-align: center;
      max-width: 800px;
      position: relative;
      padding: 0 40px;
    }

    .quote::before {
      content: '"';
      position: absolute;
      left: 0;
      top: -20px;
      font-size: 80px;
      color: var(--gold);
      opacity: .3;
      line-height: 1;
    }

    /* ── KEYBOARD HINT ── */
    .kbd-hint {
      position: fixed;
      top: 24px;
      right: 24px;
      font-size: 12px;
      color: rgba(255, 255, 255, .25);
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .kbd {
      border: 1px solid rgba(255, 255, 255, .15);
      border-radius: 4px;
      padding: 2px 8px;
    }
  </style>
</head>

<body>

  <div class="progress" id="progressBar"></div>

  <div class="kbd-hint">
    <span class="kbd">←</span> <span class="kbd">→</span> navigatsiya
  </div>

  <div class="slides" id="slides">

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 1 — COVER
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark active" style="text-align:center; gap:24px;">
      <div style="font-size:80px; animation: float 3s ease-in-out infinite;">🌙</div>
      <div class="title-xl"><span class="gold-text">IMezon</span></div>
      <p style="font-size:22px; font-weight:300; color:rgba(255,255,255,.6); max-width:600px; line-height:1.6;">
        Zamonaviy do'kon va restoran boshqaruv tizimi
      </p>
      <div style="display:flex; gap:12px; flex-wrap:wrap; justify-content:center; margin-top:8px;">
        <span class="badge badge-gold">🛒 POS Kassa</span>
        <span class="badge badge-white">👥 CRM</span>
        <span class="badge badge-white">📦 Sklad</span>
        <span class="badge badge-white">🍳 Restoran</span>
        <span class="badge badge-white">📊 Hisobot</span>
      </div>
      <p class="muted text-sm" style="margin-top:16px;">2026 yil · PHP + MySQL · Web-asosida</p>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 2 — MUAMMO
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="gap:40px; max-width:1100px; margin:0 auto; width:100%;">
      <div style="text-align:center;">
        <span class="tag tag-red" style="margin-bottom:16px; display:inline-block;">Muammo</span>
        <div class="title-lg">Kichik biznes nima bilan kurashadi?</div>
      </div>

      <div class="grid-2" style="gap:20px;">
        <div class="card" style="display:flex; gap:16px; align-items:flex-start;">
          <div style="font-size:36px;">😰</div>
          <div>
            <div style="font-weight:700; margin-bottom:6px;">Qo'lda hisob-kitob</div>
            <p class="muted text-sm">Daftar, Excel yoki telefon — ma'lumotlar yo'qoladi, xato ko'p</p>
          </div>
        </div>
        <div class="card" style="display:flex; gap:16px; align-items:flex-start;">
          <div style="font-size:36px;">📉</div>
          <div>
            <div style="font-weight:700; margin-bottom:6px;">Nazorat yo'qligi</div>
            <p class="muted text-sm">Kassir nima sotdi? Sklad qancha? Foyda qanchaga tushdi?</p>
          </div>
        </div>
        <div class="card" style="display:flex; gap:16px; align-items:flex-start;">
          <div style="font-size:36px;">⏰</div>
          <div>
            <div style="font-weight:700; margin-bottom:6px;">Vaqt yo'qotish</div>
            <p class="muted text-sm">Hisobotni yig'ish, inventarni sanash — soatlar ketadi</p>
          </div>
        </div>
        <div class="card" style="display:flex; gap:16px; align-items:flex-start;">
          <div style="font-size:36px;">💸</div>
          <div>
            <div style="font-weight:700; margin-bottom:6px;">Yo'qotishlar</div>
            <p class="muted text-sm">Qarzlar unutiladi, mahsulot oʻgʻirlanadi, ortiqcha xarajatlar</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 3 — YECHIM
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="text-align:center; gap:32px; max-width:1000px; margin:0 auto; width:100%;">
      <div>
        <span class="tag tag-green" style="margin-bottom:16px; display:inline-block;">Yechim</span>
        <div class="title-lg">IMezon — <span class="gold-text">hamma narsa bir joyda</span></div>
        <p class="muted text-lg" style="max-width:640px; margin:16px auto 0;">
          Bitta tizimda sotuvdan tortib hisobotgacha — barcha jarayonlar avtomatik va shaffof
        </p>
      </div>

      <div class="grid-3" style="gap:16px; text-align:left;">
        <div
          style="background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.2); border-radius:16px; padding:24px;">
          <div style="font-size:32px; margin-bottom:12px;">⚡</div>
          <div style="font-weight:700; margin-bottom:6px; color:var(--success);">Real vaqt</div>
          <p class="muted text-sm">Har bir sotuv, qoldiq, balans — darhol yangilanadi</p>
        </div>
        <div
          style="background:rgba(226,185,111,.08); border:1px solid rgba(226,185,111,.2); border-radius:16px; padding:24px;">
          <div style="font-size:32px; margin-bottom:12px;">🌐</div>
          <div style="font-weight:700; margin-bottom:6px; color:var(--gold);">Istalgan joydan</div>
          <p class="muted text-sm">Telefon, planshet, kompyuter — brauzer orqali ishlaydi</p>
        </div>
        <div
          style="background:rgba(59,130,246,.1); border:1px solid rgba(59,130,246,.2); border-radius:16px; padding:24px;">
          <div style="font-size:32px; margin-bottom:12px;">🔒</div>
          <div style="font-weight:700; margin-bottom:6px; color:var(--info);">Xavfsiz</div>
          <p class="muted text-sm">Rol asosida kirish, har bir amal loglarga yoziladi</p>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 4 — RAQAMLAR
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="gap:40px; max-width:1000px; margin:0 auto; width:100%; text-align:center;">
      <div>
        <div class="glow-line" style="margin:0 auto 16px;"></div>
        <div class="title-lg">Tizim imkoniyatlari</div>
      </div>

      <div class="grid-4">
        <div>
          <div class="stat-num gold-text">6</div>
          <div class="stat-lbl">Modul</div>
        </div>
        <div>
          <div class="stat-num gold-text">40+</div>
          <div class="stat-lbl">Sahifa va funksiya</div>
        </div>
        <div>
          <div class="stat-num gold-text">∞</div>
          <div class="stat-lbl">Mahsulotlar</div>
        </div>
        <div>
          <div class="stat-num gold-text">24/7</div>
          <div class="stat-lbl">Ishlash vaqti</div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card" style="text-align:left;">
          <div style="font-weight:700; margin-bottom:12px; color:var(--gold);">🏪 Do'kon uchun:</div>
          <ul class="check-list text-sm">
            <li>Barkod bilan tezkor sotuv</li>
            <li>Ko'p valyuta (so'm + USD)</li>
            <li>Nasiya va vozvrat tizimi</li>
            <li>Voucher va chegirmalar</li>
          </ul>
        </div>
        <div class="card" style="text-align:left;">
          <div style="font-weight:700; margin-bottom:12px; color:var(--gold);">🍽️ Restoran uchun:</div>
          <ul class="check-list text-sm">
            <li>Stol bo'yicha orderlar</li>
            <li>Oshpaz ekrani (real vaqt)</li>
            <li>Retsept va ishlab chiqarish</li>
            <li>Dastavka va olib ketish</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 5 — MODULLAR
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="gap:32px; max-width:1100px; margin:0 auto; width:100%;">
      <div style="text-align:center;">
        <span class="tag tag-gold" style="margin-bottom:16px; display:inline-block;">Modullar</span>
        <div class="title-lg">6 ta asosiy modul</div>
      </div>

      <div class="grid-2" style="gap:16px;">
        <div class="mod-card">
          <div class="icon-box" style="font-size:28px;">👑</div>
          <div>
            <div style="font-weight:700; margin-bottom:4px;">Admin paneli</div>
            <p class="muted text-sm">Dashboard · Xodimlar · Moliya · Hisobot · Sozlamalar</p>
          </div>
        </div>
        <div class="mod-card">
          <div class="icon-box" style="font-size:28px;">🛒</div>
          <div>
            <div style="font-weight:700; margin-bottom:4px;">Kassa (POS)</div>
            <p class="muted text-sm">Sotuv · Smena · Nasiya · Vozvrat · Chek chop etish</p>
          </div>
        </div>
        <div class="mod-card">
          <div class="icon-box" style="font-size:28px;">🍽️</div>
          <div>
            <div style="font-weight:700; margin-bottom:4px;">Sotuvchi paneli</div>
            <p class="muted text-sm">Stol orderlar · Menyu tanlash · Holat kuzatish</p>
          </div>
        </div>
        <div class="mod-card">
          <div class="icon-box" style="font-size:28px;">🍳</div>
          <div>
            <div style="font-weight:700; margin-bottom:4px;">Oshpaz ekrani</div>
            <p class="muted text-sm">Real vaqt orderlar · Tayyorlash belgilash · Avtomatik yangilanish</p>
          </div>
        </div>
        <div class="mod-card">
          <div class="icon-box" style="font-size:28px;">📦</div>
          <div>
            <div style="font-weight:700; margin-bottom:4px;">Sklad boshqaruvi</div>
            <p class="muted text-sm">Qabul qilish · Postavshiklar · Barkod · Filialga jo'natish</p>
          </div>
        </div>
        <div class="mod-card">
          <div class="icon-box" style="font-size:28px;">🏭</div>
          <div>
            <div style="font-weight:700; margin-bottom:4px;">Ishlab chiqarish</div>
            <p class="muted text-sm">Retseptlar · Ishlab chiqarish · Maydalash · FIFO hisob</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 6 — ADMIN PANEL
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="gap:32px; max-width:1100px; margin:0 auto; width:100%;">
      <div style="text-align:center;">
        <span class="tag tag-gold" style="margin-bottom:12px; display:inline-block;">Admin paneli</span>
        <div class="title-md">To'liq nazorat — bir sahifada</div>
      </div>

      <div class="grid-3" style="gap:14px;">
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">📊</div>
          <div style="font-weight:700; font-size:15px; margin-bottom:6px;">Dashboard</div>
          <p class="muted text-sm">Oylik/bugungi sotuv, foyda, qarzlar, kassa holati, 7 kunlik grafik — barchasi bir
            ko'rinishda</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">💰</div>
          <div style="font-weight:700; font-size:15px; margin-bottom:6px;">Moliya</div>
          <p class="muted text-sm">Kapital kiritish · Harajatlar · Inkasasiya · Valyuta kursi · Balans tarixi</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">🎟️</div>
          <div style="font-weight:700; font-size:15px; margin-bottom:6px;">Voucher</div>
          <p class="muted text-sm">Kupon kodlar yaratish · Foiz yoki summa chegirma · Foydalanish cheklovi</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">🧑‍🤝‍🧑</div>
          <div style="font-weight:700; font-size:15px; margin-bottom:6px;">Mijozlar (CRM)</div>
          <p class="muted text-sm">Mijoz bazasi · Toifalar · Chegirmalar · Nasiya tarixi</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">📋</div>
          <div style="font-weight:700; font-size:15px; margin-bottom:6px;">Hisobot va eksport</div>
          <p class="muted text-sm">Davr bo'yicha filtr · Mahsulot tahlili · CSV yuklab olish</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">🕐</div>
          <div style="font-weight:700; font-size:15px; margin-bottom:6px;">Faollik tarixi</div>
          <p class="muted text-sm">Kim, qachon, nima qilganini ko'rish · IP manzil · Brauzer ma'lumoti</p>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 7 — POS KASSA
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="gap:32px; max-width:1100px; margin:0 auto; width:100%;">
      <div style="text-align:center;">
        <span class="tag tag-blue" style="margin-bottom:12px; display:inline-block;">Kassa · POS tizimi</span>
        <div class="title-md">Tez, aniq, qulay sotuv</div>
      </div>

      <div class="grid-2" style="gap:24px; align-items:start;">
        <div>
          <div style="font-weight:700; margin-bottom:16px; color:var(--gold);">To'lov usullari:</div>
          <div style="display:flex; flex-direction:column; gap:10px;">
            <div
              style="display:flex; align-items:center; gap:12px; background:rgba(255,255,255,.05); border-radius:10px; padding:12px 16px;">
              <span style="font-size:22px;">💵</span>
              <div>
                <div style="font-weight:600; font-size:14px;">Naqd pul</div>
                <div class="muted" style="font-size:12px;">Qaytim avtomatik hisoblanadi</div>
              </div>
            </div>
            <div
              style="display:flex; align-items:center; gap:12px; background:rgba(255,255,255,.05); border-radius:10px; padding:12px 16px;">
              <span style="font-size:22px;">💳</span>
              <div>
                <div style="font-weight:600; font-size:14px;">Kart-karta / Bank</div>
                <div class="muted" style="font-size:12px;">O'tkazma, terminal yoki karta-kartadan-kartaga</div>
              </div>
            </div>
            <div
              style="display:flex; align-items:center; gap:12px; background:rgba(255,255,255,.05); border-radius:10px; padding:12px 16px;">
              <span style="font-size:22px;">🪙</span>
              <div>
                <div style="font-weight:600; font-size:14px;">USD dollarda</div>
                <div class="muted" style="font-size:12px;">Kurs bo'yicha konvertatsiya</div>
              </div>
            </div>
            <div
              style="display:flex; align-items:center; gap:12px; background:rgba(255,255,255,.05); border-radius:10px; padding:12px 16px;">
              <span style="font-size:22px;">🔀</span>
              <div>
                <div style="font-weight:600; font-size:14px;">Aralash</div>
                <div class="muted" style="font-size:12px;">Bir vaqtda bir necha usul</div>
              </div>
            </div>
            <div
              style="display:flex; align-items:center; gap:12px; background:rgba(255,255,255,.05); border-radius:10px; padding:12px 16px;">
              <span style="font-size:22px;">📋</span>
              <div>
                <div style="font-weight:600; font-size:14px;">Nasiya (qarz)</div>
                <div class="muted" style="font-size:12px;">Mijozga muddat bilan</div>
              </div>
            </div>
          </div>
        </div>
        <div>
          <div style="font-weight:700; margin-bottom:16px; color:var(--gold);">Ilg'or funksiyalar:</div>
          <ul class="check-list">
            <li>📷 Barkod skanerlash orqali tovar qo'shish</li>
            <li>🏷️ Voucher (kupon) kod bilan chegirma</li>
            <li>📊 Smena ochish va yopish hisoboti</li>
            <li>🧾 Chek chop etish (termal printer)</li>
            <li>↩️ Vozvrat (qaytarish) va kassa tuzatish</li>
            <li>🔢 Kasr miqdor (0.5 kg, 1.3 metr)</li>
            <li>👤 Kassir uchun max chegirma cheklovi</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 8 — RESTORAN JARAYONI
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="gap:32px; max-width:1100px; margin:0 auto; width:100%; text-align:center;">
      <div>
        <span class="tag tag-purple" style="margin-bottom:12px; display:inline-block;">Restoran moduli</span>
        <div class="title-md">Orderdan tarelkagacha — to'liq jarayon</div>
      </div>

      <div class="flow" style="gap:8px;">
        <div class="flow-step">🍽️<br><span style="font-size:12px; color:var(--gray);">Sotuvchi</span><br>Order beradi
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-step" style="background:rgba(226,185,111,.1); border-color:rgba(226,185,111,.3);">📡<br><span
            style="font-size:12px; color:var(--gold);">Tizim</span><br>Avtomatik</div>
        <div class="flow-arrow">→</div>
        <div class="flow-step">🍳<br><span style="font-size:12px; color:var(--gray);">Oshpaz</span><br>Ko'radi</div>
        <div class="flow-arrow">→</div>
        <div class="flow-step">✅<br><span style="font-size:12px; color:var(--gray);">Oshpaz</span><br>Tayyor belgisi
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-step">💳<br><span style="font-size:12px; color:var(--gray);">Kassir</span><br>To'lov qabul
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-step"
          style="background:rgba(16,185,129,.1); border-color:rgba(16,185,129,.3); color:var(--success);">🎉<br><span
            style="font-size:12px;">Yakunlandi</span></div>
      </div>

      <div class="grid-3" style="gap:16px; text-align:left; margin-top:8px;">
        <div class="card" style="padding:20px;">
          <div style="font-size:24px; margin-bottom:8px;">🍽️</div>
          <div style="font-weight:700; font-size:14px; margin-bottom:6px;">Sotuvchi paneli</div>
          <p class="muted text-sm">Stol / Dastavka / Olib ketish bo'yicha orderlar · Menyu kategoriyalar bo'yicha</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:24px; margin-bottom:8px;">🍳</div>
          <div style="font-weight:700; font-size:14px; margin-bottom:6px;">Oshpaz ekrani</div>
          <p class="muted text-sm">TV yoki planshetga qo'yiladi · Har 5 soniyada yangilanadi · Flicker yo'q</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:24px; margin-bottom:8px;">🏭</div>
          <div style="font-weight:700; font-size:14px; margin-bottom:6px;">Ishlab chiqarish</div>
          <p class="muted text-sm">Tayyorlash paytida xom ashyo avtomatik kamayadi · FIFO tannarx hisob</p>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 9 — SKLAD
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="gap:32px; max-width:1100px; margin:0 auto; width:100%;">
      <div style="text-align:center;">
        <span class="tag tag-green" style="margin-bottom:12px; display:inline-block;">Sklad tizimi</span>
        <div class="title-md">Inventarni to'liq nazorat qilish</div>
      </div>

      <div class="grid-2" style="gap:24px;">
        <div>
          <ul class="check-list">
            <li>📥 Tovar qabul qilish (partiya ochish)</li>
            <li>🏷️ Barkod avtomatik generatsiya</li>
            <li>📤 Filialga tovar jo'natish</li>
            <li>👥 Postavshik boshqaruvi va qarzlar</li>
            <li>📊 Real vaqt qoldiqlar</li>
            <li>🔢 Kasr o'lchovlar (kg, metr, litr)</li>
            <li>🏭 Ko'p filial qoldiqlari alohida</li>
            <li>📋 Inventar eksport (CSV)</li>
          </ul>
        </div>
        <div style="display:flex; flex-direction:column; gap:12px;">
          <div
            style="background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.2); border-radius:14px; padding:20px;">
            <div style="font-weight:700; color:var(--success); margin-bottom:8px;">FIFO hisoblash</div>
            <p class="muted text-sm">Birinchi kelgan tovar birinchi ketadi — tannarx avtomatik va aniq hisoblanadi</p>
          </div>
          <div
            style="background:rgba(226,185,111,.08); border:1px solid rgba(226,185,111,.2); border-radius:14px; padding:20px;">
            <div style="font-weight:700; color:var(--gold); margin-bottom:8px;">Ko'p narx tizimlari</div>
            <p class="muted text-sm">Oddiy narx · Ulgurji narx · Mijoz toifasi chegirmasi</p>
          </div>
          <div
            style="background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); border-radius:14px; padding:20px;">
            <div style="font-weight:700; color:var(--info); margin-bottom:8px;">Avtomatik qoldiq</div>
            <p class="muted text-sm">Har bir sotuv va qabul sezonsida qoldiq darhol yangilanadi</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 10 — MOLIYA VA HISOBOT
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="gap:32px; max-width:1100px; margin:0 auto; width:100%;">
      <div style="text-align:center;">
        <span class="tag tag-gold" style="margin-bottom:12px; display:inline-block;">Moliya moduli</span>
        <div class="title-md">Pul oqimini to'liq ko'rish</div>
      </div>

      <div class="grid-2" style="gap:20px; align-items:start;">
        <div>
          <table class="feat-table">
            <thead>
              <tr>
                <th>Funksiya</th>
                <th>Tavsif</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>💳 Ko'p kassa</td>
                <td class="muted text-sm">Naqd, karta, bank, USD — alohida balanslarda</td>
              </tr>
              <tr>
                <td>📥 Kapital kiritish</td>
                <td class="muted text-sm">Investor pulini, qarzni qayd qilish</td>
              </tr>
              <tr>
                <td>💸 Harajatlar</td>
                <td class="muted text-sm">Ijara, maosh, kommunal — kategoriyalar bo'yicha</td>
              </tr>
              <tr>
                <td>🔄 Inkasasiya</td>
                <td class="muted text-sm">Filialdan bosh kassaga pul o'tkazish</td>
              </tr>
              <tr>
                <td>💹 Valyuta kursi</td>
                <td class="muted text-sm">USD kursini kiriting — hamma joy yangilanadi</td>
              </tr>
              <tr>
                <td>📊 Foyda hisobi</td>
                <td class="muted text-sm">Brutto + Sof foyda = Sotuv - Tannarx - Xarajat</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div style="display:flex; flex-direction:column; gap:16px;">
          <div style="font-weight:700; color:var(--gold); margin-bottom:4px;">Hisobotlar:</div>
          <div class="card" style="padding:16px; display:flex; gap:12px;">
            <span style="font-size:22px;">📈</span>
            <div>
              <div style="font-size:14px; font-weight:600;">Sotuv hisoboti</div>
              <p class="muted" style="font-size:12px; margin-top:2px;">Davr · Mahsulot · Kassir bo'yicha</p>
            </div>
          </div>
          <div class="card" style="padding:16px; display:flex; gap:12px;">
            <span style="font-size:22px;">💼</span>
            <div>
              <div style="font-size:14px; font-weight:600;">Foyda tahlili</div>
              <p class="muted" style="font-size:12px; margin-top:2px;">Brutto · Sof · Tannarx taqqoslama</p>
            </div>
          </div>
          <div class="card" style="padding:16px; display:flex; gap:12px;">
            <span style="font-size:22px;">👥</span>
            <div>
              <div style="font-size:14px; font-weight:600;">Qarz holati</div>
              <p class="muted" style="font-size:12px; margin-top:2px;">Mijoz nasiyalari + Postavshik qarzlari</p>
            </div>
          </div>
          <div class="card" style="padding:16px; display:flex; gap:12px;">
            <span style="font-size:22px;">📥</span>
            <div>
              <div style="font-size:14px; font-weight:600;">CSV eksport</div>
              <p class="muted" style="font-size:12px; margin-top:2px;">Excel da tahlil qilish mumkin</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 11 — XAVFSIZLIK
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="gap:32px; max-width:1000px; margin:0 auto; width:100%; text-align:center;">
      <div>
        <span class="tag tag-red" style="margin-bottom:12px; display:inline-block;">Xavfsizlik</span>
        <div class="title-md">Ko'p qatlamli himoya tizimi</div>
      </div>

      <div class="grid-3" style="gap:16px; text-align:left;">
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">🔑</div>
          <div style="font-weight:700; font-size:14px; margin-bottom:6px;">Rol asosida kirish</div>
          <p class="muted text-sm">Admin · Kassir · Sotuvchi · Oshpaz · Sklad — har biri faqat o'z sahifasini ko'radi
          </p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">🛡️</div>
          <div style="font-weight:700; font-size:14px; margin-bottom:6px;">Brute-force himoya</div>
          <p class="muted text-sm">7 marta noto'g'ri urinishdan keyin hisob vaqtincha bloklanadi</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">📝</div>
          <div style="font-weight:700; font-size:14px; margin-bottom:6px;">Audit log</div>
          <p class="muted text-sm">Har bir amal — kim, qachon, qanday IP dan bajargani yoziladi</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">🔐</div>
          <div style="font-weight:700; font-size:14px; margin-bottom:6px;">Sessiya nazorati</div>
          <p class="muted text-sm">Brauzer yopilsa avtomatik chiqish · Sessiya muddati cheklangan</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">💾</div>
          <div style="font-weight:700; font-size:14px; margin-bottom:6px;">Ma'lumot himoyasi</div>
          <p class="muted text-sm">SQL injection himoyasi · Barcha kiritishlar tekshiriladi</p>
        </div>
        <div class="card" style="padding:20px;">
          <div style="font-size:28px; margin-bottom:10px;">🌐</div>
          <div style="font-weight:700; font-size:14px; margin-bottom:6px;">HTTPS tayyor</div>
          <p class="muted text-sm">SSL sertifikat bilan ishlaydi · Ma'lumotlar shifrlangan kanal orqali</p>
        </div>
      </div>
    </div>

    - ═══════════════════════════════════════════════════════
    SLIDE 13 — FOYDA
    ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="gap:36px; max-width:1000px; margin:0 auto; width:100%; text-align:center;">
      <div>
        <span class="tag tag-green" style="margin-bottom:12px; display:inline-block;">Natija</span>
        <div class="title-lg">IMezon bilan <span class="gold-text">qancha tejaysiz?</span></div>
      </div>

      <div class="grid-3" style="gap:20px;">
        <div
          style="background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.2); border-radius:20px; padding:28px;">
          <div style="font-size:40px; margin-bottom:8px;">⏱️</div>
          <div style="font-size:36px; font-weight:900; color:var(--success);">3×</div>
          <div style="font-weight:600; margin-bottom:6px;">Tezroq sotuv</div>
          <p class="muted text-sm">Barkod va kassir paneli bilan</p>
        </div>
        <div
          style="background:rgba(226,185,111,.08); border:1px solid rgba(226,185,111,.2); border-radius:20px; padding:28px;">
          <div style="font-size:40px; margin-bottom:8px;">📉</div>
          <div style="font-size:36px; font-weight:900; color:var(--gold);">0</div>
          <div style="font-weight:600; margin-bottom:6px;">Hisob xatolari</div>
          <p class="muted text-sm">Avtomatik hisob-kitob</p>
        </div>
        <div
          style="background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); border-radius:20px; padding:28px;">
          <div style="font-size:40px; margin-bottom:8px;">👁️</div>
          <div style="font-size:36px; font-weight:900; color:var(--info);">100%</div>
          <div style="font-weight:600; margin-bottom:6px;">Shaffoflik</div>
          <p class="muted text-sm">Hamma narsani ko'rish</p>
        </div>
      </div>

      <div class="quote">
        Biznesingizni boshqarish uchun <span class="gold-text">kuchli asbob</span> —<br>
        o'rnatish va ishlatish <span class="gold-text">juda oson.</span>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
       SLIDE 14 — CTA / XULOSA
  ════════════════════════════════════════════════════════ -->
    <div class="slide bg-dark" style="text-align:center; gap:32px;">
      <div style="font-size:72px; animation: float 3s ease-in-out infinite;">🚀</div>
      <div class="title-xl">Tayyor. <span class="gold-text">Ishga tushiring.</span></div>
      <p style="font-size:20px; color:rgba(255,255,255,.6); max-width:560px; line-height:1.7;">
        Bugun o'rnating — ertaga biznesingizni to'liq nazorat qiling
      </p>

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; max-width:700px; width:100%;">
        <div style="border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:20px 16px;">
          <div style="font-size:28px;">⚡</div>
          <div style="font-weight:700; font-size:14px; margin-top:8px;">1 kun</div>
          <p class="muted text-sm">O'rnatish vaqti</p>
        </div>
        <div
          style="border:1px solid rgba(226,185,111,.3); border-radius:14px; padding:20px 16px; background:rgba(226,185,111,.06);">
          <div style="font-size:28px;">📱</div>
          <div style="font-weight:700; font-size:14px; margin-top:8px; color:var(--gold);">Istalgan qurilma</div>
          <p class="muted text-sm">Telefon yoki PC</p>
        </div>
        <div style="border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:20px 16px;">
          <div style="font-size:28px;">🔧</div>
          <div style="font-weight:700; font-size:14px; margin-top:8px;">Texnik yordam</div>
          <p class="muted text-sm">O'rnatib beriladi</p>
        </div>
      </div>

      <div style="display:flex; gap:16px; flex-wrap:wrap; justify-content:center; margin-top:8px;">
        <span class="badge badge-gold" style="font-size:15px; padding:10px 24px;">✅ Do'kon uchun</span>
        <span class="badge badge-gold" style="font-size:15px; padding:10px 24px;">✅ Restoran uchun</span>
        <span class="badge badge-gold" style="font-size:15px; padding:10px 24px;">✅ Ko'p filial uchun</span>
      </div>

      <p class="muted text-sm" style="margin-top:8px;">IMezon v1.0 · imezon.uz</p>
    </div>

  </div><!-- /slides -->

  <!-- NAVIGATION -->
  <nav class="nav" id="nav">
    <button class="nav-btn" id="prevBtn" onclick="changeSlide(-1)">← Oldingi</button>
    <div class="nav-dots" id="dots"></div>
    <span class="slide-counter" id="counter">1 / 14</span>
    <button class="nav-btn" id="nextBtn" onclick="changeSlide(1)">Keyingi →</button>
  </nav>

  <script>
    const slides = document.querySelectorAll('.slide');
    const dots = document.getElementById('dots');
    const counter = document.getElementById('counter');
    const progress = document.getElementById('progressBar');
    let current = 0;
    const total = slides.length;

    // Build dots
    for (let i = 0; i < total; i++) {
      const d = document.createElement('div');
      d.className = 'dot' + (i === 0 ? ' active' : '');
      d.onclick = () => goTo(i);
      dots.appendChild(d);
    }

    function goTo(n) {
      slides[current].classList.remove('active');
      slides[current].classList.add('prev');
      setTimeout(() => slides[current].classList.remove('prev'), 600);

      current = (n + total) % total;
      slides[current].classList.add('active');

      // Update dots
      document.querySelectorAll('.dot').forEach((d, i) => {
        d.classList.toggle('active', i === current);
      });

      // Update counter
      counter.textContent = (current + 1) + ' / ' + total;

      // Update progress
      progress.style.width = ((current + 1) / total * 100) + '%';
    }

    function changeSlide(dir) { goTo(current + dir); }

    // Keyboard navigation
    document.addEventListener('keydown', e => {
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === ' ') {
        e.preventDefault(); changeSlide(1);
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        e.preventDefault(); changeSlide(-1);
      }
    });

    // Touch/swipe support
    let touchStartX = 0;
    document.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; });
    document.addEventListener('touchend', e => {
      const dx = e.changedTouches[0].clientX - touchStartX;
      if (Math.abs(dx) > 50) changeSlide(dx < 0 ? 1 : -1);
    });

    // Init progress
    progress.style.width = (1 / total * 100) + '%';

    // CSS animation keyframe
    const style = document.createElement('style');
    style.textContent = `@keyframes float {
    0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)}
  }`;
    document.head.appendChild(style);
  </script>
</body>

</html>
?>