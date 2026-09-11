<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();
$filiallar = $db->rows("SELECT * FROM im_filiallar WHERE status=1 ORDER BY tartib");
$workers   = $db->rows(
    "SELECT w.*, f.nomi AS filial_nomi
     FROM im_workers w
     LEFT JOIN im_filiallar f ON f.id = w.filial_id
     ORDER BY w.status DESC, w.ism"
);
// Maosh tarixi — bu oy
$bu_oy = date('Y-m');
$maosh_bu_oy = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_maosh_tarixi WHERE oy='$bu_oy'");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Xodimlar | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-people-fill me-1"></i> Xodimlar (Workers)</div>
    <div class="im-topbar-actions">
      <span class="im-badge im-badge-warning me-2">Bu oy maosh: <strong><?= im_money($maosh_bu_oy) ?> so'm</strong></span>
      <button class="im-btn im-btn-primary" onclick="openAdd()"><i class="bi bi-plus-lg me-1"></i> Yangi xodim</button>
    </div>
  </header>
  <main class="im-content">

    <div class="im-card">
      <div class="im-card-header">
        <i class="bi bi-people" style="color:var(--primary)"></i>
        <span class="im-card-title">Barcha xodimlar</span>
        <span class="im-badge im-badge-muted ms-auto"><?= count($workers) ?> ta</span>
      </div>
      <?php if ($workers): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr>
            <th>#</th><th>Ism</th><th>Lavozim</th><th>Filial</th>
            <th class="text-right">Oylik stavka</th><th>Holat</th><th>Amal</th>
          </tr></thead>
          <tbody>
          <?php foreach ($workers as $i => $w): ?>
          <tr class="<?= $w['status'] ? '' : 'opacity-50' ?>">
            <td class="text-muted fs-xs"><?= $i+1 ?></td>
            <td>
              <strong><?= im_f($w['ism']) ?></strong>
              <?php if ($w['telefon']): ?>
              <div class="text-muted fs-xs"><i class="bi bi-telephone-fill"></i> <?= im_f($w['telefon']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php $lv = ['kassir'=>['💳','var(--primary)'],'sotuvchi'=>['🛍️','var(--accent-dark)'],
                            'sklad'=>['📦','#6c757d'],'haydovchi'=>['🚚','#e67e22'],
                            'tozalovchi'=>['🧹','#17a2b8'],'boshqa'=>['👤','#adb5bd']];
                    [$icon,$clr] = $lv[$w['lavozim']] ?? ['👤','#adb5bd']; ?>
              <span class="im-badge" style="background:<?= $clr ?>22;color:<?= $clr ?>;border:1px solid <?= $clr ?>44">
                <?= $icon ?> <?= ucfirst($w['lavozim']) ?>
              </span>
            </td>
            <td class="text-muted"><?= im_f($w['filial_nomi'] ?? '— Umumiy') ?></td>
            <td class="text-right num fw-bold"><?= im_money($w['oylik_stavka']) ?> so'm</td>
            <td>
              <?php if ($w['status']): ?>
              <span class="im-badge im-badge-success">Faol</span>
              <?php else: ?>
              <span class="im-badge im-badge-danger">Nofaol</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1">
                <button class="im-btn im-btn-outline im-btn-sm"
                  onclick="openEdit(<?= htmlspecialchars(json_encode($w)) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <a href="maosh.php?worker_id=<?= $w['id'] ?>" class="im-btn im-btn-outline im-btn-sm"
                  title="Maosh ber">
                  <i class="bi bi-cash"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-people"></i><h4>Xodimlar yo'q</h4><p>Birinchi xodimni qo'shing</p></div>
      <?php endif; ?>
    </div>

  </main>
</div>
</div>

<!-- ── MODAL ─────────────────────────────────────────── -->
<div id="worker-modal" class="im-overlay">
  <div class="im-modal" style="max-width:480px;width:96%">
    <div class="im-modal-header">
      <span class="im-modal-title" id="modal-title">Yangi xodim</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="w-id">
      <div class="mb-3">
        <label class="im-label mb-1">Ism <span class="text-danger">*</span></label>
        <input type="text" id="w-ism" class="im-input" placeholder="Xodim ismi">
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="im-label mb-1">Telefon</label>
          <input type="text" id="w-tel" class="im-input" placeholder="+998...">
        </div>
        <div class="col-6">
          <label class="im-label mb-1">Lavozim</label>
          <select id="w-lav" class="im-input">
            <option value="kassir">💳 Kassir</option>
            <option value="sotuvchi">🛒️ Sotuvchi</option>
            <option value="sklad">📦 Sklad</option>
            <option value="haydovchi">🚚 Haydovchi</option>
            <option value="tozalovchi">🧹 Tozalovchi</option>
            <option value="boshqa">👤 Boshqa</option>
          </select>
        </div>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="im-label mb-1">Filial</label>
          <select id="w-filial" class="im-input">
            <option value="">— Umumiy (barcha)</option>
            <?php foreach ($filiallar as $f): ?>
            <option value="<?= $f['id'] ?>"><?= im_f($f['nomi']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="im-label mb-1">Oylik stavka (so'm)</label>
          <input type="number" id="w-oylik" class="im-input" placeholder="0" min="0" step="50000">
        </div>
      </div>
      <div class="mb-3">
        <label class="im-label mb-1">Izoh</label>
        <textarea id="w-izoh" class="im-input" rows="2"></textarea>
      </div>
      <div class="form-check mb-3">
        <input type="checkbox" id="w-status" class="form-check-input" checked>
        <label class="form-check-label" for="w-status">Faol xodim</label>
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary fw-bold" id="w-save-btn" onclick="saveWorker()">
        <i class="bi bi-check-lg me-1"></i> Saqlash
      </button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
if (typeof nhToast !== 'function') {
  window.nhToast = function(msg, type='info') {
    const colors={success:'#198754',error:'#dc3545',info:'#0d6efd'};
    const c=document.createElement('div');
    c.style.cssText=`position:fixed;bottom:24px;right:24px;z-index:99999;background:${colors[type]||'#333'};color:#fff;padding:13px 20px;border-radius:10px;font-size:15px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.25);display:flex;align-items:center;gap:10px;max-width:360px`;
    c.innerHTML=`<span>${msg}</span>`;document.body.appendChild(c);
    setTimeout(()=>{c.style.opacity='0';c.style.transition='opacity .3s';setTimeout(()=>c.remove(),300)},3500);
  };
}
function openAdd() {
  document.getElementById('modal-title').textContent = 'Yangi xodim';
  document.getElementById('w-id').value = '';
  document.getElementById('w-ism').value = '';
  document.getElementById('w-tel').value = '';
  document.getElementById('w-lav').value = 'kassir';
  document.getElementById('w-filial').value = '';
  document.getElementById('w-oylik').value = '';
  document.getElementById('w-izoh').value = '';
  document.getElementById('w-status').checked = true;
  NHModal.open('worker-modal');
}
function openEdit(w) {
  document.getElementById('modal-title').textContent = 'Xodimni tahrirlash';
  document.getElementById('w-id').value        = w.id;
  document.getElementById('w-ism').value       = w.ism;
  document.getElementById('w-tel').value       = w.telefon || '';
  document.getElementById('w-lav').value       = w.lavozim;
  document.getElementById('w-filial').value    = w.filial_id || '';
  document.getElementById('w-oylik').value     = w.oylik_stavka;
  document.getElementById('w-izoh').value      = w.izoh || '';
  document.getElementById('w-status').checked  = w.status == 1;
  NHModal.open('worker-modal');
}
function closeModal() {
  NHModal.close('worker-modal');
}
function saveWorker() {
  const ism = document.getElementById('w-ism').value.trim();
  if (!ism) return nhToast('Ism kiritish majburiy!', 'error');
  const btn = document.getElementById('w-save-btn');
  btn.disabled = true;
  fetch(im_BASE + 'admin/ajax/worker-save.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      id:       document.getElementById('w-id').value,
      ism,
      telefon:  document.getElementById('w-tel').value,
      lavozim:  document.getElementById('w-lav').value,
      filial_id:document.getElementById('w-filial').value,
      oylik_stavka: document.getElementById('w-oylik').value||0,
      izoh:     document.getElementById('w-izoh').value,
      status:   document.getElementById('w-status').checked ? 1 : 0,
    })
  })
  .then(r=>r.json())
  .then(d=>{
    if (d.status==='ok') { nhToast(d.msg,'success'); setTimeout(()=>location.reload(),1000); }
    else { nhToast(d.msg||'Xatolik','error'); btn.disabled=false; }
  })
  .catch(()=>{ nhToast('Server xatosi','error'); btn.disabled=false; });
}
</script>
</body></html>
