<?php
// ============================================================
//  IMezon — Admin: Pul Olish (Biznes kassasidan)
//  Ega yoki admin biznes kassasidan pul olganda ishlatiladi.
//  Bu foyda hisobiga ta'sir QILMAYDI (divident/shaxsiy pul)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$usd_kurs = im_usd_kurs();

// Yagona kassa holati
$kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=0 LIMIT 1")
         ?? ['naqd_balans'=>0,'karta_balans'=>0,'bank_balans'=>0,'usd_balans'=>0];

// Tarix (oxirgi 200 ta)
$tarix = $db->rows(
    "SELECT b.*, x.ism AS xodim_ism
     FROM im_balans b
     LEFT JOIN im_xodimlar x ON x.id = b.xodim_id
     WHERE b.kategoriya='admin_pul_olish'
     ORDER BY b.sana DESC LIMIT 200"
);

$jami_olish = (float)$db->val(
    "SELECT COALESCE(SUM(summa_som),0) FROM im_balans
     WHERE kategoriya='admin_pul_olish' AND DATE(sana) BETWEEN DATE_FORMAT(NOW(),'%Y-%m-01') AND CURDATE()"
);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pul Olish | IMezon Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title"><i class="bi bi-cash-coin me-1"></i> Pul Olish (Biznes Kassasidan)</div>
    <div class="im-topbar-actions">
      <span class="im-badge im-badge-muted me-2" title="USD kurs">💱 1$ = <?= im_money($usd_kurs) ?> so'm</span>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- ── KASSA HOLATI ──────────────────────────────────────── -->
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-safe2-fill" style="color:var(--primary)"></i>
        <span class="im-card-title">Yagona Biznes Kassasi — Joriy holat</span>
      </div>
      <div class="row g-3 p-3">
        <?php
        $k_items = [
            [$kassa['naqd_balans'],  'var(--success)', 'naqd'],
            [$kassa['karta_balans'], 'var(--primary)', 'karta'],
            [$kassa['bank_balans'],  'var(--accent-dark)', 'bank'],
        ];
        foreach ($k_items as [$val, $color, $turi]): ?>
        <div class="col-6 col-md-3">
          <div class="im-stat-card" style="border-left:4px solid <?= $color ?>">
            <div>
              <div class="im-stat-label"><?= im_tt_label($turi, true) ?></div>
              <div class="im-stat-value num" style="color:<?= $color ?>"><?= im_money($val) ?> so'm</div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <!-- USD — asosiy qiymat dollar, kichik yozuv so'm ekvivalenti -->
        <div class="col-6 col-md-3">
          <div class="im-stat-card" style="border-left:4px solid #b8860b">
            <div>
              <div class="im-stat-label">🪙 USD</div>
              <div class="im-stat-value num" style="color:#b8860b">$<?= number_format((float)$kassa['usd_balans'], 2) ?></div>
              <div class="text-muted fs-xs">≈ <?= im_money((float)$kassa['usd_balans'] * $usd_kurs) ?> so'm</div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- ── PUL OLISH FORMA ───────────────────────────────────── -->
    <div class="row g-4 mb-4">
      <div class="col-md-5">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-cash-coin" style="color:var(--danger)"></i>
            <span class="im-card-title">Yangi Pul Olish</span>
          </div>
          <div class="im-card-body p-4">
            <div class="mb-3">
              <label class="im-label mb-1">To'lov usuli</label>
              <div class="d-flex gap-2 flex-wrap" id="tt-group">
                <?php foreach (['naqd','karta','bank','usd'] as $v): ?>
                <button type="button"
                  class="im-btn tt-btn <?= $v==='naqd' ? 'tt-active' : 'im-btn-outline' ?>"
                  data-val="<?= $v ?>"
                  style="<?= $v==='naqd' ? 'background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700' : '' ?>">
                  <?= im_tt_label($v) ?>
                </button>
                <?php endforeach; ?>
              </div>
              <input type="hidden" id="tolov_turi" value="naqd">
            </div>
            <div class="mb-3">
              <label class="im-label mb-1" id="summa-label">Summa (so'm)</label>
              <input type="number" id="summa" class="im-input" placeholder="0" min="0" step="any">
              <div id="usd-hint" class="text-muted fs-xs mt-1" style="display:none"></div>
            </div>
            <div class="mb-3">
              <label class="im-label mb-1">Sana</label>
              <input type="date" id="sana" class="im-input" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="mb-4">
              <label class="im-label mb-1">Izoh <span class="text-danger">*</span></label>
              <textarea id="izoh" class="im-input" rows="3" placeholder="Maqsad, sabab... (majburiy)"></textarea>
            </div>
            <button class="im-btn im-btn-danger w-100 fw-bold" id="pul-olish-btn">
              <i class="bi bi-cash-coin me-1"></i> Pul Olish
            </button>
          </div>
        </div>
      </div>

      <!-- ── Bu oy statistika ─────────────────────────────────── -->
      <div class="col-md-7">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-graph-down-arrow" style="color:var(--danger)"></i>
            <span class="im-card-title">Bu oy olingan pul</span>
            <span class="im-badge im-badge-danger ms-auto fw-bold"><?= im_money($jami_olish) ?> so'm</span>
          </div>
          <div class="im-card-body p-3">
            <div class="alert p-3 rounded-3 mb-3" style="background:rgba(220,53,69,.07);border:1px dashed var(--danger)">
              <i class="bi bi-info-circle-fill me-2" style="color:var(--danger)"></i>
              <strong>Eslatma:</strong> Bu operatsiya <strong>foyda hisobiga ta'sir qilmaydi</strong>.
              Bu biznesdan chiqib ketayotgan pul (divident, shaxsiy xarajat yoki boshqa maqsad).
              Kassa balansidan ayiriladi.
            </div>
            <?php if ($tarix): ?>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead><tr>
                  <th>Sana</th><th>Usul</th><th>Izoh</th><th class="text-right">Summa</th><th>Kim</th>
                </tr></thead>
                <tbody>
                <?php foreach (array_slice($tarix, 0, 10) as $t): ?>
                <tr>
                  <td class="text-muted fs-xs"><?= im_datetime($t['sana']) ?></td>
                  <td><span class="im-badge im-badge-muted"><?= im_f($t['manba_tur'] ?? '—') ?></span></td>
                  <td class="text-muted fs-xs" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= im_f($t['izoh'] ?: '—') ?></td>
                  <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($t['summa_som']) ?> so'm</td>
                  <td class="text-muted fs-xs"><?= im_f($t['xodim_ism'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
            <div class="im-empty"><i class="bi bi-cash-coin"></i><h4>Hali pul olinmagan</h4></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── TO'LIQ TARIX ──────────────────────────────────────── -->
    <?php if (count($tarix) > 10): ?>
    <div class="im-card">
      <div class="im-card-header">
        <i class="bi bi-list-ul"></i>
        <span class="im-card-title">Barcha pul olishlar</span>
        <span class="im-badge im-badge-muted ms-auto"><?= count($tarix) ?> ta</span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr>
            <th>Sana</th><th>Usul</th><th>Izoh</th><th class="text-right">Summa</th><th>Kim</th>
          </tr></thead>
          <tbody>
          <?php foreach ($tarix as $t): ?>
          <tr>
            <td class="text-muted fs-xs"><?= im_datetime($t['sana']) ?></td>
            <td><span class="im-badge im-badge-muted"><?= im_f($t['manba_tur'] ?? '—') ?></span></td>
            <td class="text-muted fs-xs"><?= im_f($t['izoh'] ?: '—') ?></td>
            <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($t['summa_som']) ?> so'm</td>
            <td class="text-muted fs-xs"><?= im_f($t['xodim_ism'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>
</div>
<div id="im-toast-container"></div>

<!-- ── CUSTOM CONFIRM MODAL ──────────────────────────────── -->
<div id="pul-confirm-modal" style="
  display:none;position:fixed;inset:0;z-index:9999;
  background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);
  align-items:center;justify-content:center;
">
  <div style="
    background:var(--card-bg,#fff);border-radius:16px;
    box-shadow:0 8px 40px rgba(0,0,0,0.25);
    width:100%;max-width:420px;margin:16px;
    overflow:hidden;animation:slideUp .2s ease;
  ">
    <div style="background:var(--danger);padding:20px 24px;color:#fff">
      <div style="font-size:28px;margin-bottom:6px">💸</div>
      <div style="font-size:18px;font-weight:700">Pul olishni tasdiqlang</div>
    </div>
    <div style="padding:24px">
      <div id="pcm-body" style="font-size:15px;line-height:1.7;margin-bottom:20px"></div>
      <div style="display:flex;gap:10px">
        <button id="pcm-cancel" class="im-btn im-btn-outline w-100" style="font-size:15px">
          <i class="bi bi-x-lg me-1"></i> Bekor
        </button>
        <button id="pcm-ok" class="im-btn im-btn-danger w-100 fw-bold" style="font-size:15px">
          <i class="bi bi-check-lg me-1"></i> Ha, olish
        </button>
      </div>
    </div>
  </div>
</div>
<style>
@keyframes slideUp { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }
#pul-confirm-modal.show { display:flex!important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
// ── nhToast fallback (main.js ishlamasa ham ishlaydi) ─────
if (typeof nhToast !== 'function') {
  window.nhToast = function(msg, type='info') {
    const colors = {success:'#198754', error:'#dc3545', info:'#0d6efd', warning:'#fd7e14'};
    const icons  = {success:'✅', error:'❌', info:'ℹ️', warning:'⚠️'};
    const c = document.createElement('div');
    c.style.cssText = `
      position:fixed;bottom:24px;right:24px;z-index:99999;
      background:${colors[type]||colors.info};color:#fff;
      padding:13px 20px;border-radius:10px;font-size:15px;font-weight:600;
      box-shadow:0 4px 20px rgba(0,0,0,0.25);
      display:flex;align-items:center;gap:10px;
      animation:toastIn .3s ease;max-width:360px;
    `;
    c.innerHTML = `<span style="font-size:18px">${icons[type]||'•'}</span><span>${msg}</span>`;
    const style = document.createElement('style');
    style.textContent = '@keyframes toastIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}';
    document.head.appendChild(style);
    document.body.appendChild(c);
    setTimeout(() => { c.style.opacity='0'; c.style.transition='opacity .3s'; setTimeout(()=>c.remove(),300); }, 3500);
  };
}

const USD_KURS = <?= (float)$usd_kurs ?>;
let selTT = 'naqd';

// ── To'lov turi tanlanishi ─────────────────────────────────
document.querySelectorAll('.tt-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tt-btn').forEach(b => {
      b.classList.remove('tt-active');
      b.classList.add('im-btn-outline');
      b.style.cssText = '';
    });
    this.classList.remove('im-btn-outline');
    this.classList.add('tt-active');
    this.style.cssText = 'background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700';
    selTT = this.dataset.val;
    document.getElementById('tolov_turi').value = selTT;
    document.getElementById('summa-label').textContent = selTT==='usd' ? 'Summa (USD)' : "Summa (so'm)";
    document.getElementById('usd-hint').style.display = 'block';
    calcUSD();
  });
});

// ── USD hint ──────────────────────────────────────────────
document.getElementById('summa').addEventListener('input', calcUSD);
function calcUSD() {
  const s = parseFloat(document.getElementById('summa').value) || 0;
  if(selTT === 'usd') {
    document.getElementById('usd-hint').innerHTML = '≈ <span class="fw-bold">' + (s * USD_KURS).toLocaleString() + "</span> so'm";
  } else {
    document.getElementById('usd-hint').innerHTML = '≈ <span class="fw-bold">$' + (USD_KURS>0?(s/USD_KURS).toFixed(2):'0') + "</span>";
  }
}

// ── Custom confirm modal ───────────────────────────────────
const modal    = document.getElementById('pul-confirm-modal');
const pcmBody  = document.getElementById('pcm-body');
const pcmOk    = document.getElementById('pcm-ok');
const pcmCancel= document.getElementById('pcm-cancel');
let _resolve   = null;

function nhConfirm(html) {
  pcmBody.innerHTML = html;
  modal.classList.add('show');
  return new Promise(res => { _resolve = res; });
}
pcmOk.onclick     = () => { modal.classList.remove('show'); _resolve && _resolve(true); };
pcmCancel.onclick = () => { modal.classList.remove('show'); _resolve && _resolve(false); };
modal.addEventListener('click', e => { if(e.target===modal){ modal.classList.remove('show'); _resolve&&_resolve(false); }});

// ── PUL OLISH ─────────────────────────────────────────────
const pulBtn = document.getElementById('pul-olish-btn');
pulBtn.addEventListener('click', async function() {
  const summa = parseFloat(document.getElementById('summa').value) || 0;
  const izoh  = document.getElementById('izoh').value.trim();
  const sana  = document.getElementById('sana').value;
  const tolov = document.getElementById('tolov_turi').value;

  if (summa <= 0) return nhToast('Summa kiriting!', 'error');
  if (!izoh)      return nhToast('Izoh majburiy!', 'error');

  const ttLabel = IM_TT_LABELS;
  const ok = await nhConfirm(`
    <table style="width:100%;border-collapse:collapse">
      <tr><td style="color:var(--muted);padding:5px 0">Usul:</td>
          <td style="font-weight:700;text-align:right">${ttLabel[tolov]||tolov}</td></tr>
      <tr><td style="color:var(--muted);padding:5px 0">Summa:</td>
          <td style="font-weight:700;font-size:20px;color:var(--danger);text-align:right">
            ${Number(summa).toLocaleString()} ${tolov === 'usd' ? 'USD' : "so'm"}</td></tr>
      <tr><td style="color:var(--muted);padding:5px 0;vertical-align:top">Izoh:</td>
          <td style="text-align:right;font-style:italic">${izoh}</td></tr>
    </table>
    <div style="margin-top:12px;padding:10px;background:rgba(220,53,69,.08);border-radius:8px;font-size:13px;color:var(--danger)">
      ⚠️ Kassa balansidan ayiriladi. Bu amalni qaytarib bo'lmaydi!
    </div>
  `);
  if (!ok) return;

  pulBtn.disabled = true;
  pulBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Bajarilmoqda...';

  fetch(im_BASE + 'admin/ajax/pul-olish-save.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({summa, tolov_turi: tolov, izoh, sana})
  })
  .then(r => r.json())
  .then(d => {
    if (d.status === 'ok') {
      nhToast(d.msg || d.message || 'Muvaffaqiyatli!', 'success');
      setTimeout(() => location.reload(), 1400);
    } else {
      nhToast(d.msg || d.message || 'Xatolik yuz berdi!', 'error');
      pulBtn.disabled = false;
      pulBtn.innerHTML = '<i class="bi bi-cash-coin me-1"></i> Pul Olish';
    }
  })
  .catch(() => {
    nhToast('Server bilan aloqa xatosi!', 'error');
    pulBtn.disabled = false;
    pulBtn.innerHTML = '<i class="bi bi-cash-coin me-1"></i> Pul Olish';
  });
});
</script>
</body></html>

