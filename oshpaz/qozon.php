<?php
// ============================================================
//  IMezon — Osh qozoni (kunlik reja-fakt)
//
//  Oshpaz/kassir: qozon ochish (real xomashyo) → kun davomida sotuv →
//  qozon yopish (qolgan porsiya). Tannarx har bir QOZON bo'yicha yakunlanadi.
//  To'liq hisobot: qayta-ishlash/osh-hisobot.php
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['oshpaz', 'kassir', 'admin']);

$db          = new Cyber();
$filial_id   = (int)($_SESSION['im_filial_id'] ?? 0);
$filial_nomi = $filial_id
    ? ((string)$db->val("SELECT nomi FROM im_filiallar WHERE id=$filial_id") ?: 'Filial')
    : 'Filial';

// Rolga qarab "orqaga" manzili
$orqaga = im_BASE . ($im_rol === 'oshpaz' ? 'oshpaz/index.php'
        : ($im_rol === 'kassir' ? 'dukon/index.php' : 'qayta-ishlash/index.php'));
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Osh qozoni | IMezon</title>
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/bi.min.css">
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
  <style>
    .qz-wrap{max-width:1080px;margin:0 auto;padding:18px}
    .qz-top{display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap}
    .qz-top h1{font-size:19px;font-weight:800;margin:0;display:flex;align-items:center;gap:8px}
    .qz-fil{background:var(--info-light);color:var(--info);border-radius:8px;padding:4px 10px;font-size:12px;font-weight:700}
    .qz-row{display:flex;gap:8px;align-items:center;margin-bottom:8px}
    .qz-row .nomi{flex:1;font-weight:600;font-size:13px}
    .qz-row .mav{font-size:11px;color:var(--muted);white-space:nowrap}
    .qz-row input{width:110px}
    .qz-card-open{border:2px solid var(--success);border-radius:14px;padding:14px 16px;margin-bottom:12px;background:var(--success-light)}
    .qz-card-open .h{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .qz-metrics{display:flex;gap:18px;flex-wrap:wrap;margin:10px 0}
    .qz-metric b{display:block;font-size:17px;font-weight:800}
    .qz-metric span{font-size:11px;color:var(--muted)}
    .qz-eslatma{border-radius:10px;padding:10px 14px;font-size:13.5px;font-weight:700;
      display:flex;align-items:flex-start;gap:9px;line-height:1.45;border:1px solid transparent}
    .qz-eslatma i{font-size:17px;flex-shrink:0;margin-top:1px}
    .qz-eslatma.xato{background:var(--danger-light);border-color:var(--danger);color:var(--danger)}
    .qz-eslatma.ogoh{background:var(--warning-light);border-color:var(--warning);color:var(--warning)}
    .qz-eslatma.info{background:var(--info-light);border-color:var(--info);color:var(--info)}
    .qz-qoldiq-options{display:grid;grid-template-columns:1fr;gap:8px}
    .qz-qoldiq-option{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;
      border:1px solid var(--border);border-radius:10px;background:var(--card);cursor:pointer}
    .qz-qoldiq-option:hover{border-color:var(--primary)}
    .qz-qoldiq-option input{margin-top:3px;accent-color:var(--primary)}
    .qz-qoldiq-option b{display:block;font-size:13px}
    .qz-qoldiq-option small{display:block;color:var(--muted);font-size:11px;margin-top:2px}
    .qz-qoldiq-options.is-disabled{opacity:.55}
    .qz-qoldiq-options.is-disabled .qz-qoldiq-option{cursor:not-allowed}
  </style>
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/oshpaz-qozon.css?v=2">
</head>
<body>
<div class="qz-wrap">

  <div class="qz-top">
    <a href="<?= $orqaga ?>" class="im-btn im-btn-outline im-btn-sm qz-back" aria-label="Orqaga"><i class="bi bi-arrow-left"></i></a>
    <div class="qz-heading">
      <span class="qz-heading-icon"><i class="bi bi-fire"></i></span>
      <div><h1>Osh qozoni</h1><div class="qz-subtitle">Kunlik ishlab chiqarish va tannarx nazorati</div></div>
    </div>
    <span class="qz-fil"><i class="bi bi-shop"></i> <?= im_f($filial_nomi) ?></span>
    <span style="flex:1"></span>
    <div class="qz-actions">
      <a href="<?= im_BASE ?>qayta-ishlash/osh-hisobot.php" class="im-btn im-btn-outline im-btn-sm">
        <i class="bi bi-bar-chart-fill"></i> <span class="action-copy">Hisobot</span>
      </a>
      <button class="im-btn im-btn-outline im-btn-sm" data-theme-toggle aria-label="Rang mavzusini almashtirish"><i class="bi bi-moon-fill"></i></button>
    </div>
  </div>

  <!-- ── Eslatmalar (partiya ochilmagan / mo'ljal tugadi) ── -->
  <div id="qzEslatma" style="display:none;flex-direction:column;gap:8px;margin-bottom:14px"></div>

  <div class="qz-main-grid">
  <!-- ── Ochiq qozonlar ── -->
  <div id="ochiqBlok" style="display:none">
    <div class="im-card mb-3">
      <div class="im-card-header">
        <span class="qz-section-icon green"><i class="bi bi-fire"></i></span>
        <span class="im-card-title">Ochiq qozonlar</span>
      </div>
      <div class="im-card-body" id="ochiqList"></div>
    </div>
  </div>

  <!-- ── Yangi qozon ── -->
  <div class="im-card mb-3">
    <div class="im-card-header">
      <span class="qz-section-icon"><i class="bi bi-plus-lg"></i></span>
      <span class="im-card-title">Yangi qozon ochish</span>
      <span id="yangiRejim" class="im-badge im-badge-warning ms-2" style="display:none">Qo'shimcha qozon</span>
    </div>
    <div class="im-card-body">
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="im-label">Mahsulot <span class="text-danger">*</span></label>
          <select id="q_mahsulot" class="im-input" onchange="onMahsulot()">
            <option value="">— tanlang —</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="im-label">Mo'ljal (necha porsiya)</label>
          <input type="number" id="q_moljal" class="im-input" min="0" step="1" value="90" oninput="onMoljal()">
          <div class="qz-form-help"><i class="bi bi-info-circle"></i><span>Xomashyo miqdori mo'ljalga ko'ra avtomatik hisoblanadi. Ochishdan oldin real o'lchovni tekshiring.</span></div>
        </div>
      </div>

      <div id="xomBlok" style="display:none">
        <label class="im-label">Qozonga solingan xomashyo (real o'lchov) <span class="text-danger">*</span></label>
        <div id="xomRows"></div>
        <div class="d-flex justify-content-between qz-total" style="border-top:1px dashed var(--border)">
          <span class="text-muted fs-sm">Xomashyo tannarxi (taxminiy):</span>
          <strong id="xomSumma">0 so'm</strong>
        </div>
        <button class="im-btn im-btn-primary w-100 mt-3" id="ochBtn" onclick="qozonOch(false)">
          <i class="bi bi-fire"></i> Qozon ochish
        </button>
      </div>
    </div>
  </div>
  </div>

  <!-- ── Bugun yopilganlar ── -->
  <div class="im-card" id="yopBlok" style="display:none">
    <div class="im-card-header">
      <span class="qz-section-icon green"><i class="bi bi-check2"></i></span>
      <span class="im-card-title">Bugun yopilgan qozonlar</span>
    </div>
    <div class="im-table-wrap">
      <table class="im-table">
        <thead><tr>
          <th>Mahsulot</th><th class="text-right">Mo'ljal</th>
          <th class="text-right">Haqiqiy chiqish</th><th class="text-right">Yakuniy tannarx</th>
          <th class="text-right">Qoldi</th>
          <th>Yopdi</th><th>Vaqt</th>
        </tr></thead>
        <tbody id="yopList"></tbody>
      </table>
    </div>
  </div>

  <div id="boshEmpty" class="im-empty" style="display:none">
    <i class="bi bi-fire"></i><h4>Bugun hali qozon ochilmagan</h4>
    <p class="text-muted">Yuqoridan yangi qozon oching.</p>
  </div>

</div>

<!-- ── Yopish modal ── -->
<div class="im-overlay" id="yopModal">
  <div class="im-modal" style="max-width:420px">
    <div class="im-modal-header">
      <span class="im-modal-title"><i class="bi bi-check2-circle"></i> Qozonni yopish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="yop_qozon_id">
      <p class="text-muted fs-sm mb-2" id="yopInfo"></p>
      <label class="im-label">Qozonda qolgan porsiya <span class="text-danger">*</span></label>
      <input type="number" id="yop_qoldi" class="im-input" min="0" step="1" value="0" oninput="yopQoldiSync()">

      <div id="yop_isrof_blok" class="mt-3">
        <label class="im-label">Qolgan porsiyaning holati</label>
        <div id="yop_isrof_options" class="qz-qoldiq-options is-disabled">
        <label class="qz-qoldiq-option">
          <input type="radio" name="yop_isrofmi" value="1" checked>
          <span><b>Isrof / xodim ovqati</b><small>Qoldiq zarar sifatida hisobdan chiqariladi</small></span>
        </label>
        <label class="qz-qoldiq-option">
          <input type="radio" name="yop_isrofmi" value="0">
          <span><b>Keyingi kunga qoldi</b><small>FIFO qoldiqda shu qozon tannarxi bilan saqlanadi</small></span>
        </label>
        </div>
        <div id="yop_isrof_hint" class="text-muted fs-xs mt-1">Qoldiq 0 — tanlash talab qilinmaydi.</div>
      </div>

      <label class="im-label mt-3">Izoh</label>
      <input type="text" id="yop_izoh" class="im-input" placeholder="Ixtiyoriy (masalan: xodim ovqati)">
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-success" id="yopBtn" onclick="qozonYop()">
        <i class="bi bi-check-lg"></i> Yopish
      </button>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
const BASE      = (window.im_BASE || '/').replace(/\/$/, '');
const U_HOLAT   = BASE + '/oshpaz/ajax/qozon-holat.php';
const U_OCH     = BASE + '/oshpaz/ajax/qozon-och.php';
const U_YOP     = BASE + '/oshpaz/ajax/qozon-yop.php';

let MAHSULOTLAR = [];
let OCHIQ       = [];

function money(n){ return Math.round(n).toLocaleString('uz-UZ'); }
function num(v){ const n = parseFloat(v); return isNaN(n) ? 0 : n; }
function clock(v){
  const d = new Date(String(v || '').replace(' ', 'T'));
  return isNaN(d.getTime()) ? '—' : d.toLocaleTimeString('uz-UZ',{hour:'2-digit',minute:'2-digit'});
}

async function yukla() {
  const res = await IMAjax.get(U_HOLAT);
  if (res.status !== 'ok') { NHToast.error(res.msg || 'Xatolik'); return; }
  MAHSULOTLAR = res.data.mahsulotlar || [];
  OCHIQ       = res.data.ochiq || [];
  const yopilgan = res.data.yopilgan || [];

  renderEslatma(res.data.eslatmalar || []);

  // Mahsulot dropdown
  const sel = document.getElementById('q_mahsulot');
  const cur = sel.value;
  sel.innerHTML = '<option value="">— tanlang —</option>' +
    MAHSULOTLAR.map(m => `<option value="${m.id}">${im_esc(m.nomi)}</option>`).join('');
  if (cur) sel.value = cur;

  renderOchiq();
  renderYopilgan(yopilgan);

  const bosh = !OCHIQ.length && !yopilgan.length;
  document.getElementById('boshEmpty').style.display = bosh ? '' : 'none';

  // "Qo'shimcha qozon" belgisini joriy tanlovga qarab qayta sinxronlash
  const m = currentMahsulot();
  document.getElementById('yangiRejim').style.display =
    (m && OCHIQ.some(q => q.mahsulot_id === m.id)) ? '' : 'none';
}

function renderEslatma(list) {
  const box = document.getElementById('qzEslatma');
  if (!box) return;
  if (!list.length) { box.style.display = 'none'; box.innerHTML = ''; return; }
  const ikon = { xato:'bi-exclamation-octagon-fill', ogoh:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
  box.innerHTML = list.map(e => {
    const d = ['xato','ogoh','info'].includes(e.daraja) ? e.daraja : 'info';
    return `<div class="qz-eslatma ${d}"><i class="bi ${ikon[d]}"></i><span>${im_esc(e.matn||'')}</span></div>`;
  }).join('');
  box.style.display = 'flex';
}

function renderOchiq() {
  const blok = document.getElementById('ochiqBlok');
  const list = document.getElementById('ochiqList');
  if (!OCHIQ.length) { blok.style.display = 'none'; return; }
  blok.style.display = '';
  list.innerHTML = OCHIQ.map(q => {
    const jami = num(q.sotildi_qozon);
    return `<div class="qz-card-open">
      <div class="h">
        <div>
          <div style="font-weight:800;font-size:15px">${im_esc(q.mahsulot_nomi)}</div>
          <div class="text-muted fs-xs">Ochildi: ${clock(q.ochildi_vaqt)}
            ${q.ochgan_ism ? '· ' + im_esc(q.ochgan_ism) : ''}</div>
        </div>
        <button class="im-btn im-btn-success im-btn-sm" onclick="yopModal(${q.id})">
          <i class="bi bi-check-lg"></i> Qozonni yopish
        </button>
      </div>
      <div class="qz-metrics">
        <div class="qz-metric"><b>${(+q.moljal_porsiya).toLocaleString('uz-UZ')}</b><span>mo'ljal porsiya</span></div>
        <div class="qz-metric"><b>${money(q.xomashyo_summa)}</b><span>xomashyo so'm</span></div>
        <div class="qz-metric"><b>${money(num(q.xomashyo_summa) / Math.max(1, num(q.moljal_porsiya)))}</b><span>vaqtinchalik tannarx</span></div>
        <div class="qz-metric"><b>${jami.toLocaleString('uz-UZ')}</b><span>shu qozondan sotildi</span></div>
      </div>
    </div>`;
  }).join('');
}

function renderYopilgan(list) {
  const blok = document.getElementById('yopBlok');
  if (!list.length) { blok.style.display = 'none'; return; }
  blok.style.display = '';
  document.getElementById('yopList').innerHTML = list.map(q => `
    <tr>
      <td class="fw-semibold">${im_esc(q.mahsulot_nomi)}</td>
      <td class="text-right num">${(+q.moljal_porsiya).toLocaleString('uz-UZ')}</td>
      <td class="text-right num fw-semibold">${q.haqiqiy_porsiya == null ? '—' : num(q.haqiqiy_porsiya).toLocaleString('uz-UZ')}</td>
      <td class="text-right num fw-semibold">${q.yakuniy_tannarx == null ? '—' : money(q.yakuniy_tannarx)}</td>
      <td class="text-right num ${num(q.qoldi_porsiya) > 0 ? 'text-danger fw-bold' : ''}">${(+q.qoldi_porsiya).toLocaleString('uz-UZ')}</td>
      <td class="text-muted fs-sm">${im_esc(q.yopgan_ism || '—')}</td>
      <td class="text-muted fs-sm">${clock(q.yopildi_vaqt)}</td>
    </tr>`).join('');
}

// ── Forma ──
function currentMahsulot() {
  const id = parseInt(document.getElementById('q_mahsulot').value || 0);
  return MAHSULOTLAR.find(m => m.id === id) || null;
}

function onMahsulot() {
  const m = currentMahsulot();
  const xomBlok = document.getElementById('xomBlok');
  const yangiRejim = document.getElementById('yangiRejim');
  if (!m) { xomBlok.style.display = 'none'; return; }

  // Bu mahsulotga ochiq qozon bormi?
  const ochiqBor = OCHIQ.some(q => q.mahsulot_id === m.id);
  yangiRejim.style.display = ochiqBor ? '' : 'none';

  xomBlok.style.display = '';
  renderXomRows();
  onMoljal();
}

function renderXomRows() {
  const m = currentMahsulot();
  if (!m) return;
  document.getElementById('xomRows').innerHTML = m.xomashyo.map((x, i) => `
    <div class="qz-row">
      <span class="nomi">${im_esc(x.mahsulot_nomi)}</span>
      <span class="mav">mavjud: ${(+x.qoldiq_bor).toLocaleString('uz-UZ',{maximumFractionDigits:3})} ${im_esc(x.birlik||'')}</span>
      <input type="number" class="im-input im-input-sm xom-inp" data-mid="${x.mahsulot_id}"
             data-narx="${x.kelish_narxi}" data-bir="${im_esc(x.birlik||'')}" data-per="${x.bir_porsiya}"
             min="0" step="0.001" oninput="hisobla()">
    </div>`).join('');
}

function onMoljal() {
  const m = currentMahsulot();
  if (!m) return;
  const mj = num(document.getElementById('q_moljal').value);
  document.querySelectorAll('#xomRows .xom-inp').forEach(inp => {
    const per = num(inp.dataset.per);
    inp.value = mj > 0 ? +(per * mj).toFixed(3) : '';
  });
  hisobla();
}

function hisobla() {
  let s = 0;
  document.querySelectorAll('#xomRows .xom-inp').forEach(inp => {
    s += num(inp.value) * num(inp.dataset.narx);
  });
  document.getElementById('xomSumma').textContent = money(s) + " so'm";
}

async function qozonOch(force) {
  const m = currentMahsulot();
  if (!m) { NHToast.error('Mahsulot tanlang'); return; }

  const items = [];
  document.querySelectorAll('#xomRows .xom-inp').forEach(inp => {
    const v = num(inp.value);
    if (v > 0) items.push({ mahsulot_id: +inp.dataset.mid, soni: v, birlik: inp.dataset.bir });
  });
  if (!items.length) { NHToast.error('Kamida bitta xomashyo miqdorini kiriting'); return; }

  const moljal = num(document.getElementById('q_moljal').value);
  // force=true bo'lsa "Ochiq qozon bor" tasdig'idan keyin keladi — qayta so'ramaymiz
  if (!force) {
    const ok = await NHConfirm.ask(
      `«${m.nomi}» qozoni ochilsinmi?`,
      `${items.length} xil xomashyo qoldiqdan yechiladi (${document.getElementById('xomSumma').textContent}).`
    );
    if (!ok) return;
  }

  const btn = document.getElementById('ochBtn');
  btn.disabled = true; btn.innerHTML = '<span class="im-spinner"></span>';
  const res = await IMAjax.post(U_OCH, {
    mahsulot_id: m.id, moljal, force_yangi: force ? 1 : 0,
    items: JSON.stringify(items),
  });
  btn.disabled = false; btn.innerHTML = '<i class="bi bi-fire"></i> Qozon ochish';

  if (res.status === 'ok') {
    NHToast.success(res.msg || 'Qozon ochildi');
    document.getElementById('q_mahsulot').value = '';
    document.getElementById('xomBlok').style.display = 'none';
    yukla();
  } else if (res.data && res.data.ochiq_qozon_id) {
    const y = await NHConfirm.ask('Ochiq qozon bor', res.msg + ' Yana qo\'shimcha qozon ochasizmi?');
    if (y) qozonOch(true);
  } else {
    NHToast.error(res.msg || 'Xatolik');
  }
}

// ── Yopish ──
function yopQoldiSync() {
  const qoldi = num(document.getElementById('yop_qoldi').value);
  const bor = qoldi > 0;
  const options = document.getElementById('yop_isrof_options');
  options.classList.toggle('is-disabled', !bor);
  document.querySelectorAll('input[name="yop_isrofmi"]').forEach(r => { r.disabled = !bor; });
  document.getElementById('yop_isrof_hint').textContent = bor
    ? `${qoldi.toLocaleString('uz-UZ')} porsiya uchun holatni tanlang.`
    : 'Qoldiq 0 — tanlash talab qilinmaydi.';
}

function yopModal(id) {
  const q = OCHIQ.find(x => x.id === id);
  if (!q) return;
  document.getElementById('yop_qozon_id').value = id;
  document.getElementById('yop_qoldi').value = 0;
  document.getElementById('yop_izoh').value = '';
  const r1 = document.querySelector('input[name="yop_isrofmi"][value="1"]');
  if (r1) r1.checked = true;
  yopQoldiSync();
  document.getElementById('yopInfo').textContent =
    `«${q.mahsulot_nomi}» — mo'ljal ${(+q.moljal_porsiya).toLocaleString('uz-UZ')}, shu qozondan sotildi ${num(q.sotildi_qozon).toLocaleString('uz-UZ')}`;
  NHModal.open('yopModal');
}

async function qozonYop() {
  const id    = parseInt(document.getElementById('yop_qozon_id').value || 0);
  const qoldi = num(document.getElementById('yop_qoldi').value);
  const izoh  = document.getElementById('yop_izoh').value;
  const isrofmi = (document.querySelector('input[name="yop_isrofmi"]:checked') || {}).value ?? '1';
  if (!id) return;

  const btn = document.getElementById('yopBtn');
  btn.disabled = true; btn.innerHTML = '<span class="im-spinner"></span>';
  const res = await IMAjax.post(U_YOP, { qozon_id: id, qoldi, izoh, qoldi_isrofmi: isrofmi });
  btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Yopish';

  if (res.status === 'ok') {
    const d = res.data || {};
    const yakun = d.haqiqiy_porsiya != null && d.yakuniy_tannarx != null
      ? ` Haqiqiy chiqish: ${num(d.haqiqiy_porsiya).toLocaleString('uz-UZ')} porsiya; tannarx: ${money(d.yakuniy_tannarx)}.`
      : '';
    NHToast.success((res.msg || 'Yopildi') + yakun);
    NHModal.close('yopModal');
    yukla();
  } else {
    NHToast.error(res.msg || 'Xatolik');
  }
}

yukla();
setInterval(yukla, 20000);
</script>
</body>
</html>
