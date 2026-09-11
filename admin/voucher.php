<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$mijozlar  = $db->rows("SELECT id, ism FROM im_mijozlar WHERE status=1 ORDER BY ism LIMIT 200");
$filiallar = $db->rows("SELECT * FROM im_filiallar WHERE status=1 ORDER BY tartib");

// so'nggi 100 voucher
$vouchers = $db->rows(
    "SELECT v.*, m.ism AS mijoz_ism
     FROM im_voucher v
     LEFT JOIN im_mijozlar m ON m.id = v.mijoz_id
     ORDER BY v.created_at DESC LIMIT 100"
);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Voucher | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-ticket-perforated-fill me-1"></i> Voucher tizimi</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-primary" onclick="openAdd()"><i class="bi bi-plus-lg me-1"></i> Yangi Voucher</button>
    </div>
  </header>
  <main class="im-content">

<style>
/* ── Voucher Cards ─────────────────────────────────── */
.vc-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
  gap: 20px;
  padding: 20px;
}
.vc-card {
  border-radius: 16px;
  overflow: hidden;
  position: relative;
  box-shadow: 0 8px 32px rgba(0,0,0,.35);
  background: #111827;
  transition: transform .2s ease, box-shadow .2s ease;
  cursor: default;
  user-select: none;
}
.vc-card:hover {
  transform: translateY(-4px) scale(1.01);
  box-shadow: 0 16px 48px rgba(0,0,0,.45);
}
.vc-card.vc-inactive { opacity: .55; }

/* Top blue wave */
.vc-top {
  background: linear-gradient(135deg, #1a3a6b 0%, #1e4d9e 50%, #2563eb 100%);
  padding: 16px 20px 48px;
  position: relative;
  overflow: hidden;
}
.vc-top::after {
  content: '';
  position: absolute;
  bottom: -1px; left: -5%; right: -5%;
  height: 40px;
  background: #111827;
  border-radius: 60% 60% 0 0 / 100% 100% 0 0;
}
.vc-top-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  position: relative;
  z-index: 1;
}
.vc-brand {
  font-size: 11px;
  font-weight: 800;
  color: #d4a017;
  letter-spacing: 2px;
  text-transform: uppercase;
}
.vc-amount {
  font-size: 13px;
  font-weight: 800;
  color: #fff;
  text-align: right;
  line-height: 1.3;
}
.vc-amount span {
  display: block;
  font-size: 10px;
  color: rgba(255,255,255,.6);
  font-weight: 500;
  letter-spacing: 1px;
}

/* Gold line */
.vc-gold-line {
  height: 2px;
  background: linear-gradient(90deg, transparent, #d4a017 20%, #f5d06a 50%, #d4a017 80%, transparent);
  margin: 8px 0 0;
  position: relative;
  z-index: 1;
}

/* Bottom dark body */
.vc-body {
  padding: 14px 20px 16px;
  position: relative;
}

/* Code row */
.vc-code {
  font-size: 22px;
  font-weight: 900;
  letter-spacing: 4px;
  color: #f1f5f9;
  font-family: 'Courier New', monospace;
  margin-bottom: 14px;
  text-shadow: 0 0 12px rgba(37,99,235,.4);
}

/* Info row */
.vc-info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}
.vc-label {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: rgba(255,255,255,.4);
  margin-bottom: 2px;
}
.vc-val {
  font-size: 12px;
  font-weight: 700;
  color: #d4a017;
}
.vc-val-white { color: #e2e8f0; }

/* Status badge */
.vc-status {
  position: absolute;
  top: 12px; right: 14px;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 99px;
}
.vc-status-aktiv  { background: rgba(16,185,129,.2); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
.vc-status-nofaol { background: rgba(156,163,175,.15); color: #9ca3af; border: 1px solid rgba(156,163,175,.2); }
.vc-status-tugagan{ background: rgba(239,68,68,.2); color: #f87171; border: 1px solid rgba(248,113,113,.25); }
.vc-status-cheksiz{ background: rgba(37,99,235,.2); color: #93c5fd; border: 1px solid rgba(147,197,253,.25); }

/* Chip icon */
.vc-chip {
  width: 36px; height: 28px;
  background: linear-gradient(135deg, #d4a017, #f5d06a, #b8860b);
  border-radius: 5px;
  margin-bottom: 10px;
  position: relative;
  overflow: hidden;
}
.vc-chip::before {
  content: '';
  position: absolute;
  top: 50%; left: 5px; right: 5px;
  height: 1px;
  background: rgba(0,0,0,.3);
  transform: translateY(-50%);
}
.vc-chip::after {
  content: '';
  position: absolute;
  left: 50%; top: 4px; bottom: 4px;
  width: 1px;
  background: rgba(0,0,0,.3);
  transform: translateX(-50%);
}

/* Action buttons */
.vc-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid rgba(255,255,255,.07);
}
.vc-btn {
  flex: 1;
  padding: 6px 0;
  border: none;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  transition: all .15s;
}
.vc-btn-copy {
  background: rgba(255,255,255,.08);
  color: #d4a017;
  border: 1px solid rgba(212,160,23,.25);
}
.vc-btn-copy:hover { background: rgba(212,160,23,.15); }
.vc-btn-del {
  background: rgba(239,68,68,.12);
  color: #f87171;
  border: 1px solid rgba(248,113,113,.2);
}
.vc-btn-del:hover { background: rgba(239,68,68,.25); }

/* Search */
.vc-search-wrap {
  padding: 16px 20px 0;
  display: flex;
  gap: 10px;
  align-items: center;
}
</style>

    <!-- ── VOUCHER KARTALAR ─────────────────────────────────── -->
    <div class="im-card">
      <div class="im-card-header">
        <i class="bi bi-credit-card-2-front-fill" style="color:#d4a017"></i>
        <span class="im-card-title">Voucher kartalar</span>
        <span class="im-badge ms-2" style="background:rgba(212,160,23,.15);color:#d4a017;border:1px solid rgba(212,160,23,.25)">
          <?= count($vouchers) ?> ta
        </span>
        <div class="ms-auto">
          <input type="text" id="search-voucher" class="im-input"
                 placeholder="🔍 Kod izlash..." style="max-width:200px;height:36px">
        </div>
      </div>

      <?php if ($vouchers): ?>
      <div class="vc-grid" id="vc-grid">
        <?php foreach ($vouchers as $v): ?>
        <?php
          $is_foiz    = $v['tur'] === 'foiz';
          $is_aktiv   = $v['status'] === 'aktiv';
          $is_tugagan = $v['status'] === 'tugagan' || ($v['tugash'] && $v['tugash'] < date('Y-m-d'));
          $cheksiz    = $v['umumiy_soni'] == 0;
          $qoldi      = $cheksiz ? PHP_INT_MAX : ($v['umumiy_soni'] - $v['ishlatilgan']);
          $holat = $is_tugagan || (!$cheksiz && $qoldi <= 0) ? 'tugagan'
            : ($v['status'] === 'nofaol' ? 'nofaol' : 'aktiv');
          $holat_txt = ['aktiv'=>'● Faol','nofaol'=>'◌ Nofaol','tugagan'=>'✕ Tugagan'][$holat];

          // Chegirma label
          $chegirma_txt = $is_foiz
            ? $v['qiymat'].'%'.($v['max_chegirma'] ? ' / max '.im_money($v['max_chegirma']).' so\'m' : '')
            : im_money($v['qiymat']).' so\'m';

          // Muddat
          $muddat_txt = $v['tugash'] ? im_date($v['boshlanish']).' – '.im_date($v['tugash']) : im_date($v['boshlanish']);

          // Soni
          $soni_txt = $cheksiz ? '∞ Cheksiz' : ($v['ishlatilgan'].'/'.$v['umumiy_soni'].' ('.$qoldi.' qoldi)');
        ?>
        <div class="vc-card <?= !$is_aktiv || $is_tugagan ? 'vc-inactive' : '' ?>"
             data-kod="<?= strtolower(im_f($v['kod'])) ?>" data-nomi="<?= strtolower(im_f($v['nomi'])) ?>">

          <!-- Yuqori ko'k qism -->
          <div class="vc-top">
            <div class="vc-top-row">
              <div>
                <div class="vc-brand">🎫 IMezon Voucher</div>
                <?php if ($v['nomi']): ?>
                <div style="color:rgba(255,255,255,.7);font-size:10px;margin-top:3px"><?= im_f($v['nomi']) ?></div>
                <?php endif; ?>
              </div>
              <div class="vc-amount">
                <?= $chegirma_txt ?>
                <span><?= $is_foiz ? 'CHEGIRMA FOIZI' : 'CHEGIRMA SUMMASI' ?></span>
              </div>
            </div>
            <div class="vc-gold-line"></div>
          </div>

          <!-- Pastki qora qism -->
          <div class="vc-body">
            <!-- Status -->
            <div class="vc-status vc-status-<?= $holat === 'aktiv' && $cheksiz ? 'cheksiz' : $holat ?>">
              <?= $holat_txt ?>
            </div>

            <!-- Chip -->
            <div class="vc-chip"></div>

            <!-- Kod -->
            <div class="vc-code"><?= im_f($v['kod']) ?></div>

            <!-- Info -->
            <div class="vc-info-row">
              <div>
                <div class="vc-label">Minimal xarid</div>
                <div class="vc-val">
                  <?= $v['min_summa'] > 0 ? '≥ '.im_money($v['min_summa']).' so\'m' : '— Shartsiz' ?>
                </div>
              </div>
              <div>
                <div class="vc-label">Muddat</div>
                <div class="vc-val-white" style="font-size:11px"><?= $muddat_txt ?></div>
              </div>
              <div style="text-align:right">
                <div class="vc-label">Ishlatilgan</div>
                <div class="vc-val-white"><?= $soni_txt ?></div>
              </div>
            </div>

            <?php if ($v['mijoz_ism']): ?>
            <div style="margin-top:10px;font-size:10px;color:#d4a017;font-weight:700;letter-spacing:1px;text-transform:uppercase">
              👤 <?= im_f($v['mijoz_ism']) ?>
            </div>
            <?php endif; ?>

            <!-- Tugmalar -->
            <div class="vc-actions">
              <button class="vc-btn vc-btn-copy" onclick="copyCode('<?= im_js($v['kod']) ?>')">
                <i class="bi bi-copy"></i> Nusxa
              </button>
              <?php if ($is_aktiv && !$is_tugagan): ?>
              <button class="vc-btn vc-btn-del" onclick="deactivate(<?= $v['id'] ?>)">
                <i class="bi bi-x-circle"></i> Nofaol
              </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-credit-card-2-front" style="font-size:48px;color:#d4a017;opacity:.4"></i>
        <h4 style="margin-top:12px">Hali voucher yo'q</h4>
        <button class="im-btn im-btn-primary" onclick="openAdd()">Birinchi voucherni yarating</button>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>
</div>

<!-- ── VOUCHER QO'SHISH MODAL ─────────────────────────────── -->
<div id="v-modal" class="im-overlay">
  <div class="im-modal" style="max-width:520px;width:96%;max-height:90vh;display:flex;flex-direction:column">
    <div class="im-modal-header">
      <span class="im-modal-title">🎫 Yangi Voucher</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body" style="overflow-y:auto;flex:1">

      <!-- Tur tanlash -->
      <div class="mb-3">
        <label class="im-label mb-2">Voucher turi</label>
        <div class="d-flex gap-2">
          <button type="button" class="im-btn vt-btn vt-active" data-val="foiz"
            style="background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700;flex:1">
            📊 Foiz (%)
          </button>
          <button type="button" class="im-btn vt-btn im-btn-outline" data-val="summa" style="flex:1">
            💰 Belgilangan summa
          </button>
        </div>
        <input type="hidden" id="v-tur" value="foiz">
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="im-label mb-1">Voucher kodi <span class="text-danger">*</span></label>
          <div class="d-flex gap-1">
            <input type="text" id="v-kod" class="im-input" placeholder="SALE20"
              style="text-transform:uppercase;font-weight:700;letter-spacing:1px">
            <button class="im-btn im-btn-outline" onclick="genCode()" title="Avtomatik">
              <i class="bi bi-arrow-clockwise"></i>
            </button>
          </div>
        </div>
        <div class="col-6">
          <label class="im-label mb-1">Nomi (ixtiyoriy)</label>
          <input type="text" id="v-nomi" class="im-input" placeholder="Bahor chegirmasi">
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="im-label mb-1" id="qiymat-label">Chegirma foizi (%) <span class="text-danger">*</span></label>
          <input type="number" id="v-qiymat" class="im-input" placeholder="10" min="0.01" step="0.5">
        </div>
        <div class="col-6" id="max-chegirma-wrap">
          <label class="im-label mb-1">Maksimal chegirma (so'm)</label>
          <input type="number" id="v-max" class="im-input" placeholder="Cheksiz" min="0" step="10000">
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="im-label mb-1">Minimal xarid summasi</label>
          <input type="number" id="v-min" class="im-input" placeholder="0 = shart yo'q" min="0" step="10000">
          <div class="text-muted fs-xs mt-1" id="min-hint">Foiz uchun shart (masalan 500,000 so'm)</div>
        </div>
        <div class="col-6">
          <label class="im-label mb-1">Necha marta ishlatish mumkin</label>
          <input type="number" id="v-soni" class="im-input" placeholder="1" min="1" value="1">
          <div class="text-muted fs-xs mt-1">0 = cheksiz</div>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="im-label mb-1">Boshlanish</label>
          <input type="date" id="v-bosh" class="im-input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-6">
          <label class="im-label mb-1">Muddat tugash</label>
          <input type="date" id="v-tugash" class="im-input">
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="im-label mb-1">Faqat shu mijozga</label>
          <select id="v-mijoz" class="im-input">
            <option value="">— Barcha mijozlarga</option>
            <?php foreach ($mijozlar as $m): ?>
            <option value="<?= $m['id'] ?>"><?= im_f($m['ism']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="im-label mb-1">Filial (ixtiyoriy)</label>
          <select id="v-filial" class="im-input">
            <option value="">— Barcha filiallar</option>
            <?php foreach ($filiallar as $f): ?>
            <option value="<?= $f['id'] ?>"><?= im_f($f['nomi']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary fw-bold" id="v-save-btn" onclick="saveVoucher()">
        <i class="bi bi-ticket-perforated me-1"></i> Voucher yaratish
      </button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js?v=<?= rawurlencode(im_VERSION) ?>"></script>
<script>
if (typeof nhToast !== 'function') {
  window.nhToast = function(msg, type='info') {
    const colors={success:'#198754',error:'#dc3545',info:'#0d6efd'};
    const c=document.createElement('div');
    c.style.cssText=`position:fixed;bottom:24px;right:24px;z-index:99999;background:${colors[type]||'#333'};color:#fff;padding:13px 20px;border-radius:10px;font-size:15px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.25);max-width:360px`;
    c.textContent=msg;document.body.appendChild(c);
    setTimeout(()=>{c.style.opacity='0';c.style.transition='opacity .3s';setTimeout(()=>c.remove(),300)},3500);
  };
}

// Tur toggle
let selTur = 'foiz';
document.querySelectorAll('.vt-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.vt-btn').forEach(b=>{b.classList.add('im-btn-outline');b.style.cssText='flex:1'});
    this.classList.remove('im-btn-outline');
    this.style.cssText='background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700;flex:1';
    selTur = this.dataset.val;
    document.getElementById('v-tur').value = selTur;
    document.getElementById('qiymat-label').textContent =
      selTur==='foiz' ? 'Chegirma foizi (%) *' : 'Chegirma summasi (so\'m) *';
    document.getElementById('max-chegirma-wrap').style.display = selTur==='foiz' ? '' : 'none';
    document.getElementById('min-hint').textContent =
      selTur==='foiz' ? 'Foiz uchun shart (masalan 500,000 so\'m)' : '0 = shart yo\'q';
  });
});

// Kod generate
function genCode() {
  const chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let kod='';for(let i=0;i<8;i++) kod+=chars[Math.floor(Math.random()*chars.length)];
  document.getElementById('v-kod').value=kod;
}

// Kod uppercase
document.getElementById('v-kod').addEventListener('input',function(){this.value=this.value.toUpperCase()});

// Search — kartalar bo'yicha
const searchInput = document.getElementById('search-voucher');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#vc-grid .vc-card').forEach(card => {
      const kod   = card.dataset.kod || '';
      const nomi  = card.dataset.nomi || '';
      card.style.display = (!q || kod.includes(q) || nomi.includes(q)) ? '' : 'none';
    });
  });
}

function openAdd() { NHModal.open('v-modal'); }
function closeModal() { NHModal.close('v-modal'); }

function copyCode(kod) {
  navigator.clipboard.writeText(kod).then(()=>nhToast(kod+' nusxalandi!','info'));
}

async function deactivate(id) {
  const ok = await NHConfirm.show({
    variant: 'warning',
    title: "Voucherni nofaol qilish",
    text: "Voucher bundan keyin yangi savdolarda ishlamaydi.",
    sub: "Avval foydalanilgan voucherlar tarixi saqlanib qoladi.",
    confirmText: "Nofaol qilish",
    btnIcon: 'bi-slash-circle'
  });
  if (!ok) return;
  fetch(im_BASE+'admin/ajax/voucher-save.php',{
    method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=deactivate&id='+id
  }).then(r=>r.json()).then(d=>{
    if(d.status==='ok'){nhToast('Nofaol qilindi','success');setTimeout(()=>location.reload(),800);}
    else nhToast(d.msg||'Xatolik','error');
  });
}

function saveVoucher() {
  const kod    = document.getElementById('v-kod').value.trim().toUpperCase();
  const qiymat = parseFloat(document.getElementById('v-qiymat').value)||0;
  if (!kod)          return nhToast('Kod majburiy!','error');
  if (qiymat <= 0)   return nhToast('Qiymat 0 dan katta bo\'lishi kerak!','error');
  if (selTur==='foiz' && qiymat>100) return nhToast('Foiz 100 dan katta bo\'lishi mumkin emas!','error');

  const btn = document.getElementById('v-save-btn');
  btn.disabled=true;
  fetch(im_BASE+'admin/ajax/voucher-save.php',{
    method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      action:    'create',
      kod, nomi: document.getElementById('v-nomi').value,
      tur:       selTur, qiymat,
      max_chegirma: document.getElementById('v-max').value||0,
      min_summa:    document.getElementById('v-min').value||0,
      umumiy_soni:  document.getElementById('v-soni').value||1,
      boshlanish:   document.getElementById('v-bosh').value,
      tugash:       document.getElementById('v-tugash').value,
      mijoz_id:     document.getElementById('v-mijoz').value,
      filial_id:    document.getElementById('v-filial').value,
    })
  })
  .then(r => {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  })
  .then(d => {
    btn.disabled = false;
    if (d.status === 'ok') {
      nhToast(d.msg, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      nhToast(d.msg || 'Xatolik!', 'error');
    }
  })
  .catch(err => {
    btn.disabled = false;
    nhToast('Server xatosi: ' + err.message + '. Voucher bazasi sozlamalarini tekshiring.', 'error');
  });
}

function copyCode(kod) {
  // HTTPS da navigator.clipboard ishlaydi, HTTP da fallback
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(kod)
      .then(() => nhToast('📋 ' + kod + ' nusxalandi!', 'info'))
      .catch(() => copyFallback(kod));
  } else {
    copyFallback(kod);
  }
}
function copyFallback(kod) {
  const el = document.createElement('textarea');
  el.value = kod;
  el.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0';
  document.body.appendChild(el);
  el.select();
  try {
    document.execCommand('copy');
    nhToast('📋 ' + kod + ' nusxalandi!', 'info');
  } catch(e) {
    nhToast('Nusxa ko\'chirish imkonsiz: ' + kod, 'error');
  }
  document.body.removeChild(el);
}
</script>
</body></html>
