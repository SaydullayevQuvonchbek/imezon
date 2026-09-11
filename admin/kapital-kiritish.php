<?php
// ============================================================
//  IMezon — Admin: Kapital Kiritish (Biznes kassasiga)
//  Ega yoki investor biznesga pul kiritganda ishlatiladi.
//  Bu foyda hisobiga ta'sir QILMAYDI (kapital, qarz emas)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$usd_kurs = im_usd_kurs();

$kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=0 LIMIT 1")
         ?? ['naqd_balans'=>0,'karta_balans'=>0,'bank_balans'=>0,'usd_balans'=>0];

$tarix = $db->rows(
    "SELECT b.*, x.ism AS xodim_ism
     FROM im_balans b
     LEFT JOIN im_xodimlar x ON x.id = b.xodim_id
     WHERE b.kategoriya='boshqa_kirim'
     ORDER BY b.sana DESC LIMIT 200"
);

$jami_kiritish = (float)$db->val(
    "SELECT COALESCE(SUM(summa_som),0) FROM im_balans
     WHERE kategoriya='boshqa_kirim'"
);

$bu_oy = (float)$db->val(
    "SELECT COALESCE(SUM(summa_som),0) FROM im_balans
     WHERE kategoriya='boshqa_kirim'
     AND DATE(sana) BETWEEN DATE_FORMAT(NOW(),'%Y-%m-01') AND CURDATE()"
);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Kapital Kiritish | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-wallet-fill me-1"></i> Kapital Kiritish</div>
    <div class="im-topbar-actions">
      <span class="im-badge im-badge-muted me-2">💱 1$ = <?= im_money($usd_kurs) ?> so'm</span>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- ── KASSA HOLATI ──────────────────────────────────────── -->
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-safe2-fill" style="color:var(--success)"></i>
        <span class="im-card-title">Yagona Biznes Kassasi — Joriy holat</span>
        <span class="im-badge im-badge-muted ms-auto">Bu oy kiritildi: <strong><?= im_money($bu_oy) ?> so'm</strong></span>
      </div>
      <div class="row g-3 p-3">
        <?php
        $k_items = [
          [(float)$kassa['naqd_balans'],  'var(--success)', 'naqd'],
          [(float)$kassa['karta_balans'], 'var(--primary)', 'karta'],
          [(float)$kassa['bank_balans'],  '#0077b6',        'bank'],
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

    <div class="row g-4 mb-4">
      <!-- ── FORMA ─────────────────────────────────────────── -->
      <div class="col-md-5">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-plus-circle-fill" style="color:var(--success)"></i>
            <span class="im-card-title">Yangi Kapital Kiritish</span>
          </div>
          <div class="im-card-body p-4">

            <div class="alert p-3 rounded-3 mb-4" style="background:rgba(25,135,84,.07);border:1px dashed var(--success)">
              <i class="bi bi-info-circle-fill me-2" style="color:var(--success)"></i>
              <strong>Eslatma:</strong> Bu operatsiya <strong>foyda hisobiga ta'sir qilmaydi</strong>.
              Bu — biznesga kiritilayotgan kapital (boshlang'ich pul, investitsiya, qarz).
            </div>

            <div class="mb-3">
              <label class="im-label mb-1">Kiritish maqsadi</label>
              <div class="d-flex gap-2 flex-wrap" id="maqsad-group">
                <?php foreach ([
                  'boshlangich'  => '🏁 Boshlang\'ich',
                  'investitsiya' => '📈 Investitsiya',
                  'qarz_kirim'   => '💼 Qarz (boshqadan)',
                  'boshqa'       => '📦 Boshqa',
                ] as $v => $l): ?>
                <button type="button"
                  class="im-btn maqsad-btn <?= $v==='boshlangich' ? 'mq-active' : 'im-btn-outline' ?>"
                  data-val="<?= $v ?>"
                  style="<?= $v==='boshlangich' ? 'background:var(--success);color:#fff;border-color:var(--success);font-weight:700' : '' ?>">
                  <?= $l ?>
                </button>
                <?php endforeach; ?>
              </div>
              <input type="hidden" id="maqsad" value="boshlangich">
            </div>

            <div class="mb-3">
              <label class="im-label mb-1">To'lov usuli</label>
              <div class="d-flex gap-2 flex-wrap">
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
              <label class="im-label mb-1">Kim tomonidan</label>
              <input type="text" id="kim" class="im-input" placeholder="Ega ismi yoki tashkilot nomi">
            </div>

            <div class="mb-3">
              <label class="im-label mb-1">Sana</label>
              <input type="date" id="sana" class="im-input" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="mb-4">
              <label class="im-label mb-1">Izoh</label>
              <textarea id="izoh" class="im-input" rows="2" placeholder="Qo'shimcha ma'lumot..."></textarea>
            </div>

            <button class="im-btn im-btn-success w-100 fw-bold" id="kiritish-btn" style="font-size:16px">
              <i class="bi bi-plus-circle me-1"></i> Kassaga Kiritish
            </button>
          </div>
        </div>
      </div>

      <!-- ── STATISTIKA ────────────────────────────────────── -->
      <div class="col-md-7">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-graph-up-arrow" style="color:var(--success)"></i>
            <span class="im-card-title">Kiritilgan kapital tarixi</span>
            <span class="im-badge im-badge-success ms-auto fw-bold"><?= im_money($jami_kiritish) ?> so'm</span>
          </div>
          <div class="im-card-body p-3">
            <?php if ($tarix): ?>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead><tr>
                  <th>Sana</th><th>Maqsad</th><th>Usul</th><th>Izoh</th>
                  <th class="text-right">Summa</th>
                </tr></thead>
                <tbody>
                <?php foreach ($tarix as $t):
                  $izoh_parts = explode(' | ', $t['izoh'] ?? '');
                  $mq_label = $izoh_parts[0] ?? '—';
                  $kim_val  = isset($izoh_parts[1]) ? str_replace('Kim: ','', $izoh_parts[1]) : '';
                  $izoh_val = isset($izoh_parts[2]) ? str_replace('Izoh: ','', $izoh_parts[2]) : '';
                ?>
                <tr>
                  <td class="text-muted fs-xs"><?= im_datetime($t['sana']) ?></td>
                  <td><span class="im-badge im-badge-success"><?= im_f($mq_label) ?></span></td>
                  <td><span class="im-badge im-badge-muted"><?= im_f($t['manba_tur'] ?? '—') ?></span></td>
                  <td class="text-muted fs-xs">
                    <?php if ($kim_val): ?><span class="fw-bold"><?= im_f($kim_val) ?></span><?php endif; ?>
                    <?php if ($izoh_val): ?> · <?= im_f($izoh_val) ?><?php endif; ?>
                  </td>
                  <td class="text-right num fw-bold" style="color:var(--success)"><?= im_money($t['summa_som']) ?> so'm</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
            <div class="im-empty">
              <i class="bi bi-wallet2"></i>
              <h4>Hali kapital kiritilmagan</h4>
              <p class="text-muted">Biznesga birinchi pul kiritilganda bu yerda ko'rinadi</p>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </main>
</div>
</div>

<!-- ── CUSTOM CONFIRM MODAL ─────────────────────────────── -->
<div id="kk-modal" style="display:none;position:fixed;inset:0;z-index:9999;
  background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);
  align-items:center;justify-content:center;">
  <div style="background:var(--card-bg,#fff);border-radius:16px;
    box-shadow:0 8px 40px rgba(0,0,0,0.25);width:100%;max-width:420px;
    margin:16px;overflow:hidden;animation:slideUp .2s ease;">
    <div style="background:var(--success,#198754);padding:20px 24px;color:#fff">
      <div style="font-size:28px;margin-bottom:6px">💰</div>
      <div style="font-size:18px;font-weight:700">Kapital kiritishni tasdiqlang</div>
    </div>
    <div style="padding:24px">
      <div id="kk-body" style="font-size:15px;line-height:1.7;margin-bottom:20px"></div>
      <div style="display:flex;gap:10px">
        <button id="kk-cancel" class="im-btn im-btn-outline w-100"><i class="bi bi-x-lg me-1"></i> Bekor</button>
        <button id="kk-ok" class="im-btn im-btn-success w-100 fw-bold"><i class="bi bi-check-lg me-1"></i> Ha, kiritish</button>
      </div>
    </div>
  </div>
</div>
<style>
@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
#kk-modal.show{display:flex!important}
.im-btn-success{background:var(--success,#198754);color:#fff;border:1px solid var(--success,#198754)}
.im-btn-success:hover{opacity:.88}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
const USD_KURS = <?= (float)$usd_kurs ?>;
if (typeof nhToast !== 'function') {
  window.nhToast = function(msg, type='info') {
    const colors={success:'#198754',error:'#dc3545',info:'#0d6efd',warning:'#fd7e14'};
    const icons={success:'✅',error:'❌',info:'ℹ️',warning:'⚠️'};
    const c=document.createElement('div');
    c.style.cssText=`position:fixed;bottom:24px;right:24px;z-index:99999;background:${colors[type]||colors.info};color:#fff;padding:13px 20px;border-radius:10px;font-size:15px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.25);display:flex;align-items:center;gap:10px;animation:toastIn .3s ease;max-width:360px`;
    c.innerHTML=`<span style="font-size:18px">${icons[type]||'•'}</span><span>${msg}</span>`;
    const s=document.createElement('style');s.textContent='@keyframes toastIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}';
    document.head.appendChild(s);document.body.appendChild(c);
    setTimeout(()=>{c.style.opacity='0';c.style.transition='opacity .3s';setTimeout(()=>c.remove(),300)},3500);
  };
}

// ── Maqsad tugmalari ──────────────────────────────────────
document.querySelectorAll('.maqsad-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.maqsad-btn').forEach(b => {
      b.classList.remove('mq-active'); b.classList.add('im-btn-outline'); b.style.cssText='';
    });
    this.classList.remove('im-btn-outline'); this.classList.add('mq-active');
    this.style.cssText='background:var(--success);color:#fff;border-color:var(--success);font-weight:700';
    document.getElementById('maqsad').value = this.dataset.val;
  });
});

// ── To'lov turi ──────────────────────────────────────────
let selTT = 'naqd';
document.querySelectorAll('.tt-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tt-btn').forEach(b => {
      b.classList.remove('tt-active'); b.classList.add('im-btn-outline'); b.style.cssText='';
    });
    this.classList.remove('im-btn-outline'); this.classList.add('tt-active');
    this.style.cssText='background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700';
    selTT = this.dataset.val;
    document.getElementById('tolov_turi').value = selTT;
    document.getElementById('summa-label').textContent = selTT==='usd' ? 'Summa (USD)' : "Summa (so'm)";
    document.getElementById('usd-hint').style.display = 'block';
    calcUSD();
  });
});
document.getElementById('summa').addEventListener('input', calcUSD);
function calcUSD() {
  const s = parseFloat(document.getElementById('summa').value)||0;
  if(selTT === 'usd') {
    document.getElementById('usd-hint').innerHTML = '≈ <span class="fw-bold">' + (s * USD_KURS).toLocaleString() + "</span> so'm";
  } else {
    document.getElementById('usd-hint').innerHTML = '≈ <span class="fw-bold">$' + (USD_KURS>0?(s/USD_KURS).toFixed(2):'0') + "</span>";
  }
}

// ── Custom confirm ───────────────────────────────────────
const modal=document.getElementById('kk-modal');
const body=document.getElementById('kk-body');
let _res=null;
function nhConfirm(html){body.innerHTML=html;modal.classList.add('show');return new Promise(r=>{_res=r});}
document.getElementById('kk-ok').onclick=()=>{modal.classList.remove('show');_res&&_res(true)};
document.getElementById('kk-cancel').onclick=()=>{modal.classList.remove('show');_res&&_res(false)};
modal.addEventListener('click',e=>{if(e.target===modal){modal.classList.remove('show');_res&&_res(false)}});

// ── KIRITISH ─────────────────────────────────────────────
const btn = document.getElementById('kiritish-btn');
const maqsadLabels = {
  'boshlangich':'🏁 Boshlang\'ich kapital',
  'investitsiya':'📈 Investitsiya',
  'qarz_kirim'  :'💼 Qarz (boshqadan)',
  'boshqa'      :'📦 Boshqa'
};
const ttLabels = IM_TT_LABELS;

btn.addEventListener('click', async function() {
  const summa = parseFloat(document.getElementById('summa').value)||0;
  const izoh  = document.getElementById('izoh').value.trim();
  const kim   = document.getElementById('kim').value.trim();
  const sana  = document.getElementById('sana').value;
  const tolov = document.getElementById('tolov_turi').value;
  const maqsad= document.getElementById('maqsad').value;

  if (summa <= 0) return nhToast('Summa kiriting!', 'error');

  const ok = await nhConfirm(`
    <table style="width:100%;border-collapse:collapse">
      <tr><td style="color:var(--muted);padding:5px 0">Maqsad:</td>
          <td style="font-weight:700;text-align:right">${maqsadLabels[maqsad]||maqsad}</td></tr>
      <tr><td style="color:var(--muted);padding:5px 0">Usul:</td>
          <td style="text-align:right">${ttLabels[tolov]||tolov}</td></tr>
      ${kim?`<tr><td style="color:var(--muted);padding:5px 0">Kim:</td><td style="text-align:right">${kim}</td></tr>`:''}
      <tr><td style="color:var(--muted);padding:5px 0">Summa:</td>
          <td style="font-weight:700;font-size:20px;color:var(--success);text-align:right">
            ${Number(summa).toLocaleString()} ${tolov === 'usd' ? 'USD' : "so'm"}</td></tr>
    </table>
    <div style="margin-top:12px;padding:10px;background:rgba(25,135,84,.08);border-radius:8px;font-size:13px;color:var(--success)">
      ✅ Yagona biznes kassasiga qo'shiladi. Foyda hisobiga ta'sir qilmaydi.
    </div>
  `);
  if (!ok) return;

  btn.disabled=true;
  btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Bajarilmoqda...';

  const izohFull = [maqsadLabels[maqsad]||maqsad, kim?`Kim: ${kim}`:'', izoh?`Izoh: ${izoh}`:'']
                   .filter(Boolean).join(' | ');

  fetch(im_BASE+'admin/ajax/kapital-kiritish-save.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({summa, tolov_turi:tolov, izoh:izohFull, sana, maqsad})
  })
  .then(r=>r.json())
  .then(d=>{
    if (d.status==='ok') {
      nhToast(d.msg||'Muvaffaqiyatli kiritildi! ✅','success');
      setTimeout(()=>location.reload(),1400);
    } else {
      nhToast(d.msg||d.message||'Xatolik!','error');
      btn.disabled=false;
      btn.innerHTML='<i class="bi bi-plus-circle me-1"></i> Kassaga Kiritish';
    }
  })
  .catch(()=>{
    nhToast('Server xatosi!','error');
    btn.disabled=false;
    btn.innerHTML='<i class="bi bi-plus-circle me-1"></i> Kassaga Kiritish';
  });
});
</script>
</body></html>
