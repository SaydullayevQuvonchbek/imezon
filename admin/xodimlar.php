<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();
$xodimlar = $db->rows("SELECT x.*, f.nomi AS filial_nomi FROM im_xodimlar x LEFT JOIN im_filiallar f ON f.id=x.filial_id ORDER BY x.id ASC");
$filiallar = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY tartib");
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
    <div class="im-page-title"><i class="bi bi-people-fill me-1"></i> Xodimlar</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-primary im-btn-sm" id="btn-add"><i class="bi bi-plus-lg"></i> Qo'shish</button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-people-fill" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Xodimlar ro'yxati</span>
        <span class="im-badge im-badge-muted"><?= count($xodimlar) ?> ta</span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr><th>#</th><th>Ism</th><th>Login</th><th>Filial</th><th>Rol</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach($xodimlar as $i=>$x): ?>
          <tr id="xr-<?= $x['id'] ?>">
            <td class="text-muted fs-xs"><?= $i+1 ?></td>
            <td class="fw-semibold"><?= im_f($x['ism']) ?></td>
            <td><code><?= im_f($x['login']) ?></code></td>
            <td class="fs-xs text-muted"><?= im_f($x['filial_nomi'] ?? '—') ?></td>
            <td>
              <span class="im-badge im-badge-<?= im_rol_rang($x['rol']) ?>"><?= im_f(im_rol_nomi($x['rol'])) ?></span>
            </td>
            <td>
              <span class="im-badge im-badge-<?= $x['status']?'success':'muted' ?>">
                <?= $x['status'] ? 'Aktiv' : 'Bloklangan' ?>
              </span>
            </td>
            <td class="text-right">
              <button class="im-btn im-btn-outline im-btn-sm" onclick="editXodim(<?= htmlspecialchars(json_encode($x)) ?>)"><i class="bi bi-pencil-fill"></i></button>
              <?php if($x['id'] != $im_user_id): ?>
              <button class="im-btn im-btn-ghost im-btn-sm text-danger ms-1" onclick="deleteXodim(<?= $x['id'] ?>, '<?= im_js($x['ism']) ?>')"><i class="bi bi-trash-fill"></i></button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</div>

<!-- Modal -->
<div class="im-overlay" id="xodim-modal">
  <div class="im-modal">
    <div class="im-modal-header">
      <i class="bi bi-person-fill" style="color:var(--accent-dark);font-size:20px"></i>
      <span class="im-modal-title" id="modal-title">Xodim qo'shish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="x-id">
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="im-label">Ism *</label>
          <input class="im-input" type="text" id="x-ism" placeholder="To'liq ism">
        </div>
        <div class="col-6">
          <label class="im-label">Login *</label>
          <input class="im-input" type="text" id="x-login" placeholder="username" autocomplete="off">
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="im-label">Parol <span id="parol-hint" class="text-muted fs-xs">(o'zgartirmasangiz bo'sh qoldiring)</span></label>
          <input class="im-input" type="password" id="x-parol" placeholder="••••••••" autocomplete="new-password">
        </div>
        <div class="col-6">
          <label class="im-label">Rol *</label>
          <select class="im-select" id="x-rol">
            <?php foreach (im_rollar() as $kod => $r): ?>
            <option value="<?= im_f($kod) ?>"><?= im_f($r['nomi']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted fs-xs">
            <b>Bosh kassir</b> — markaz kassasi: do'konlardan pul qabul qiladi,
            harajat va oylik to'laydi. Do'kondagi <b>Kassir</b> chek yopadi.
          </small>
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-12">
          <label class="im-label">Filial <span class="text-muted fs-xs">(admin uchun shart emas)</span></label>
          <select class="im-select" id="x-filial">
            <option value="">— Barcha filiallar (admin/revizor) —</option>
            <?php foreach ($filiallar as $fl): ?>
            <option value="<?= $fl['id'] ?>"><?= im_f($fl['nomi']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-6">
          <label class="im-label">Telefon</label>
          <input class="im-input" type="text" id="x-tel" placeholder="+998901234567">
        </div>
        <div class="col-6">
          <label class="im-label">Status</label>
          <select class="im-select" id="x-status">
            <option value="1">✅ Aktiv</option>
            <option value="0">🚫 Bloklangan</option>
          </select>
        </div>
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="btn-save"><i class="bi bi-floppy-fill"></i> Saqlash</button>
    </div>
  </div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
function openModal(title) {
  document.getElementById('modal-title').textContent = title;
  NHModal.open('xodim-modal');
}
document.getElementById('btn-add').addEventListener('click', () => {
  document.getElementById('x-id').value='';
  document.getElementById('x-ism').value='';
  document.getElementById('x-login').value='';
  document.getElementById('x-parol').value='';
  document.getElementById('x-rol').value='kassir';
  document.getElementById('x-tel').value='';
  document.getElementById('x-status').value='1';
  document.getElementById('parol-hint').style.display='none';
  openModal("Xodim qo'shish");
  setTimeout(()=>document.getElementById('x-ism').focus(),200);
});
function editXodim(x) {
  document.getElementById('x-id').value=x.id;
  document.getElementById('x-ism').value=x.ism;
  document.getElementById('x-login').value=x.login;
  document.getElementById('x-parol').value='';
  document.getElementById('x-rol').value=x.rol;
  document.getElementById('x-tel').value=x.telefon||'';
  document.getElementById('x-status').value=x.status;
  document.getElementById('x-filial').value=x.filial_id||'';
  document.getElementById('parol-hint').style.display='';
  openModal("Xodimni tahrirlash");
}
document.getElementById('btn-save').addEventListener('click', async()=>{
  const id      = document.getElementById('x-id').value;
  const ism     = document.getElementById('x-ism').value.trim();
  const login   = document.getElementById('x-login').value.trim();
  const parol   = document.getElementById('x-parol').value;
  const rol     = document.getElementById('x-rol').value;
  const tel     = document.getElementById('x-tel').value.trim();
  const status  = document.getElementById('x-status').value;
  const filial_id = document.getElementById('x-filial').value;
  if(!ism||!login){NHToast.error('Ism va login kiritish shart');return;}
  if(!id&&!parol){NHToast.error('Yangi xodim uchun parol kiritish shart');return;}
  const res = await IMAjax.post(window.im_BASE + 'admin/ajax/xodim-save.php',{id,ism,login,parol,rol,tel,status,filial_id});
  if(res.status==='ok'){NHToast.success(res.msg);setTimeout(()=>location.reload(),700);}
  else NHToast.error(res.msg);
});
async function deleteXodim(id,ism){
  if(!await NHConfirm.ask(`«${ism}» xodimni o'chirilsinmi?`))return;
  const res=await IMAjax.post(window.im_BASE + 'admin/ajax/xodim-delete.php',{id});
  if(res.status==='ok'){document.getElementById('xr-'+id)?.remove();NHToast.success(res.msg);}
  else NHToast.error(res.msg);
}
</script>
</body></html>
