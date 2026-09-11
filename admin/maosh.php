<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

$pre_worker = (int)($_GET['worker_id'] ?? 0);
$workers    = $db->rows("SELECT w.*, f.nomi AS filial_nomi FROM im_workers w LEFT JOIN im_filiallar f ON f.id=w.filial_id WHERE w.status=1 ORDER BY w.ism");
$kassa      = $db->row("SELECT * FROM im_kassa WHERE filial_id=0 LIMIT 1") ?? ['naqd_balans'=>0,'karta_balans'=>0,'bank_balans'=>0,'usd_balans'=>0];
$bu_oy      = date('Y-m');
$usd_kurs   = im_usd_kurs();

// So'nggi maoshlar
$tarix = $db->rows(
    "SELECT mt.*, w.ism AS worker_ism, w.lavozim, f.nomi AS filial_nomi
     FROM im_maosh_tarixi mt
     LEFT JOIN im_workers w ON w.id=mt.worker_id
     LEFT JOIN im_filiallar f ON f.id=mt.filial_id
     ORDER BY mt.created_at DESC LIMIT 100"
);
$bu_oy_jami = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_maosh_tarixi WHERE oy='$bu_oy'");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Maosh Berish | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-cash-stack me-1"></i> Maosh Berish</div>
    <div class="im-topbar-actions">
      <span class="im-badge im-badge-warning me-2">Bu oy: <strong><?= im_money($bu_oy_jami) ?> so'm</strong></span>
    </div>
  </header>
  <main class="im-content">

    <div class="row g-4 mb-4">
      <!-- ── FORMA ─────────────────────────────────────────── -->
      <div class="col-md-5">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-cash-stack" style="color:#e67e22"></i>
            <span class="im-card-title">Maosh Berish</span>
          </div>
          <div class="im-card-body p-4">

            <div class="mb-3">
              <label class="im-label mb-1">Xodim <span class="text-danger">*</span></label>
              <select id="worker_id" class="im-input" onchange="onWorkerChange()">
                <option value="">— Xodim tanlang</option>
                <?php foreach ($workers as $w): ?>
                <option value="<?= $w['id'] ?>"
                  data-oylik="<?= $w['oylik_stavka'] ?>"
                  data-ism="<?= im_f($w['ism']) ?>"
                  data-lav="<?= $w['lavozim'] ?>"
                  <?= $pre_worker === $w['id'] ? 'selected' : '' ?>>
                  <?= im_f($w['ism']) ?> — <?= ucfirst($w['lavozim']) ?>
                  <?= $w['filial_nomi'] ? '('.$w['filial_nomi'].')' : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
              <div id="worker-info" class="text-muted fs-xs mt-1" style="display:none">
                <i class="bi bi-info-circle"></i> Oylik stavka: <strong id="oylik-val">0</strong> so'm
              </div>
            </div>

            <div class="mb-3">
              <label class="im-label mb-1">Oy <span class="text-danger">*</span></label>
              <input type="month" id="oy" class="im-input" value="<?= date('Y-m') ?>">
            </div>

            <div class="mb-3">
              <label class="im-label mb-1">To'lov usuli</label>
              <div class="d-flex gap-2 flex-wrap">
                <?php foreach (['naqd','karta','bank'] as $v): ?>
                <button type="button"
                  class="im-btn tt-btn <?= $v==='naqd'?'tt-active':'im-btn-outline' ?>"
                  data-val="<?= $v ?>"
                  style="<?= $v==='naqd'?'background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700':'' ?>">
                  <?= im_tt_label($v) ?>
                </button>
                <?php endforeach; ?>
              </div>
              <input type="hidden" id="tolov_turi" value="naqd">
            </div>

            <div class="mb-3">
              <label class="im-label mb-1">Summa (so'm) <span class="text-danger">*</span></label>
              <div class="d-flex gap-2">
                <input type="number" id="summa" class="im-input" placeholder="0" min="1" step="10000">
                <button class="im-btn im-btn-outline" onclick="fillOylik()" title="Oylik stavkani qo'yish">
                  <i class="bi bi-arrow-down-circle"></i>
                </button>
              </div>
            </div>

            <div class="mb-3">
              <label class="im-label mb-1">Sana</label>
              <input type="date" id="sana" class="im-input" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="mb-4">
              <label class="im-label mb-1">Izoh</label>
              <input type="text" id="izoh" class="im-input" placeholder="Oy maoshi, ustama...">
            </div>

            <div class="mb-3 p-3 rounded-3" style="background:rgba(230,126,34,.07);border:1px dashed #e67e22">
              <i class="bi bi-info-circle-fill me-1" style="color:#e67e22"></i>
              Maosh <strong>yagona kassadan</strong> chiqadi va <strong>foydadan ayiriladi</strong>.
            </div>

            <button class="im-btn w-100 fw-bold" id="maosh-btn"
              style="background:#e67e22;color:#fff;border-color:#e67e22;font-size:16px">
              <i class="bi bi-cash-stack me-1"></i> Maosh Berish
            </button>
          </div>
        </div>
      </div>

      <!-- ── KASSA + STATISTIKA ────────────────────────────── -->
      <div class="col-md-7">
        <div class="im-card mb-3">
          <div class="im-card-header">
            <i class="bi bi-safe2-fill" style="color:var(--success)"></i>
            <span class="im-card-title">Kassa holati</span>
          </div>
          <div class="row g-3 p-3">
            <?php foreach ([
              ['naqd',  $kassa['naqd_balans'],  'var(--success)'],
              ['karta', $kassa['karta_balans'], 'var(--primary)'],
              ['bank',  $kassa['bank_balans'],  '#0077b6'],
            ] as [$tv,$v,$c]): ?>
            <div class="col-4">
              <div class="im-stat-card" style="border-left:3px solid <?= $c ?>">
                <div><div class="im-stat-label"><?= im_tt_label($tv, true) ?></div>
                <div class="num fw-bold" style="color:<?= $c ?>;font-size:16px"><?= im_money($v) ?></div></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-list-ul"></i>
            <span class="im-card-title">Maosh tarixi</span>
            <span class="im-badge im-badge-warning ms-auto"><?= im_money($bu_oy_jami) ?> so'm bu oy</span>
          </div>
          <?php if ($tarix): ?>
          <div class="im-table-wrap">
            <table class="im-table">
              <thead><tr>
                <th>Xodim</th><th>Lavozim</th><th>Oy</th><th>Usul</th>
                <th class="text-right">Summa</th>
              </tr></thead>
              <tbody>
              <?php foreach ($tarix as $t): ?>
              <tr>
                <td><strong><?= im_f($t['worker_ism']) ?></strong></td>
                <td><span class="im-badge im-badge-muted"><?= ucfirst($t['lavozim']??'—') ?></span></td>
                <td class="text-muted fs-xs"><?= $t['oy'] ?? '—' ?></td>
                <td><span class="im-badge im-badge-muted"><?= $t['tolov_turi'] ?></span></td>
                <td class="text-right num fw-bold" style="color:#e67e22"><?= im_money($t['summa']) ?> so'm</td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="im-empty"><i class="bi bi-cash-stack"></i><h4>Hali maosh berilmagan</h4></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </main>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js?v=<?= rawurlencode(im_VERSION) ?>"></script>
<script>
if (typeof nhToast !== 'function') {
  window.nhToast = function(msg, type='info') {
    const colors={success:'#198754',error:'#dc3545',info:'#0d6efd',warning:'#fd7e14'};
    const c=document.createElement('div');
    c.style.cssText=`position:fixed;bottom:24px;right:24px;z-index:99999;background:${colors[type]||'#333'};color:#fff;padding:13px 20px;border-radius:10px;font-size:15px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.25);max-width:360px`;
    c.textContent=msg;document.body.appendChild(c);
    setTimeout(()=>{c.style.opacity='0';c.style.transition='opacity .3s';setTimeout(()=>c.remove(),300)},3500);
  };
}
// To'lov turi
document.querySelectorAll('.tt-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tt-btn').forEach(b=>{b.classList.add('im-btn-outline');b.style.cssText='';});
    this.classList.remove('im-btn-outline');
    this.style.cssText='background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700';
    document.getElementById('tolov_turi').value=this.dataset.val;
  });
});
function onWorkerChange() {
  const sel=document.getElementById('worker_id');
  const opt=sel.selectedOptions[0];
  const oylik=opt?.dataset.oylik||0;
  document.getElementById('worker-info').style.display=oylik>0?'':'none';
  document.getElementById('oylik-val').textContent=Number(oylik).toLocaleString();
}
function fillOylik() {
  const sel=document.getElementById('worker_id');
  const oylik=sel.selectedOptions[0]?.dataset.oylik||0;
  document.getElementById('summa').value=oylik;
}
// Sahifa yuklanganda xodim tanlangan bo'lsa
if (document.getElementById('worker_id').value) onWorkerChange();

document.getElementById('maosh-btn').addEventListener('click', async function() {
  const worker_id=document.getElementById('worker_id').value;
  const summa=parseFloat(document.getElementById('summa').value)||0;
  const oy=document.getElementById('oy').value;
  const tolov=document.getElementById('tolov_turi').value;
  const sana=document.getElementById('sana').value;
  const izoh=document.getElementById('izoh').value.trim();

  if (!worker_id) return nhToast('Xodim tanlang!','error');
  if (summa<=0)   return nhToast('Summa kiriting!','error');
  if (!oy)        return nhToast('Oy kiriting!','error');

  const ism=document.getElementById('worker_id').selectedOptions[0]?.dataset.ism||'Xodim';
  const ok = await NHConfirm.show({
    variant: 'success',
    label: "Moliyaviy operatsiya",
    title: "Maosh to'lovini tasdiqlang",
    text: `${ism} uchun ${Number(summa).toLocaleString('uz-UZ')} so'm maosh beriladi.`,
    sub: `Hisob-kitob oyi: ${oy}\nTo'lov turi: ${tolov}`,
    confirmText: "Maoshni berish",
    btnIcon: 'bi-cash-stack',
    icon: 'bi-wallet2'
  });
  if (!ok) return;

  this.disabled=true;
  this.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Bajarilmoqda...';
  fetch(im_BASE+'admin/ajax/maosh-save.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({worker_id,summa,oy,tolov_turi:tolov,sana,izoh})
  })
  .then(r=>r.json())
  .then(d=>{
    if(d.status==='ok'){nhToast(d.msg,'success');setTimeout(()=>location.reload(),1200);}
    else{nhToast(d.msg||'Xatolik','error');this.disabled=false;this.innerHTML='<i class="bi bi-cash-stack me-1"></i> Maosh Berish';}
  })
  .catch(()=>{nhToast('Server xatosi','error');this.disabled=false;this.innerHTML='<i class="bi bi-cash-stack me-1"></i> Maosh Berish';});
});
</script>
</body></html>
