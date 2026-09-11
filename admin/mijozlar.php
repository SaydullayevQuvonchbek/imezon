<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$q     = trim($_GET['q'] ?? '');
$toifa = (int)($_GET['toifa'] ?? 0);
$where = "m.status=1";
if ($q)     $where .= " AND (m.ism LIKE '%".mysqli_real_escape_string($link,$q)."%' OR m.telefon LIKE '%".mysqli_real_escape_string($link,$q)."%')";
if ($toifa) $where .= " AND m.toifa_id=$toifa";

$mijozlar = $db->rows(
    "SELECT m.*, t.nomi AS toifa_nomi, t.chegirma_foiz, t.rang,
            COALESCE(m.nasiya_qoldiq,0) AS nasiya_q,
            (SELECT COUNT(*) FROM im_sotuvlar s WHERE s.mijoz_id=m.id) AS sotuvlar
     FROM im_mijozlar m
     LEFT JOIN im_mijoz_toifalari t ON t.id=m.toifa_id
     WHERE $where ORDER BY m.ism ASC"
);
$toifalar = $db->rows("SELECT * FROM im_mijoz_toifalari ORDER BY id");
$jami_nasiya = (float)$db->val("SELECT COALESCE(SUM(nasiya_qoldiq),0) FROM im_mijozlar WHERE status=1");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Mijozlar | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-person-lines-fill me-1"></i> Mijozlar</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-primary im-btn-sm" id="btn-add"><i class="bi bi-plus-lg"></i> Mijoz qo'shish</button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">
    <!-- Filtr -->
    <div class="im-card mb-3">
      <div class="im-card-body p-3">
        <form class="d-flex gap-2 flex-wrap align-items-center" method="get">
          <div class="im-input-group" style="flex:1;min-width:220px">
            <i class="bi bi-search" style="padding-left:12px;color:var(--text-muted)"></i>
            <input class="im-input" type="text" name="q" value="<?= im_f($q) ?>" placeholder="Ism yoki telefon...">
          </div>
          <select class="im-select im-input-sm" name="toifa" onchange="this.form.submit()" style="max-width:160px">
            <option value="">Barcha toifalar</option>
            <?php foreach($toifalar as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $toifa==$t['id']?'selected':'' ?>><?= im_f($t['nomi']) ?> (<?= $t['chegirma_foiz'] ?>%)</option>
            <?php endforeach; ?>
          </select>
          <button class="im-btn im-btn-primary im-btn-sm" type="submit"><i class="bi bi-search"></i></button>
          <?php if($q||$toifa): ?><a href="?" class="im-btn im-btn-outline im-btn-sm">✕ Tozalash</a><?php endif; ?>
          <div class="ms-auto im-card px-3 py-2 d-flex gap-3 align-items-center">
            <span class="text-muted fs-xs">Jami nasiya qoldi:</span>
            <span class="fw-bold num" style="color:var(--danger)"><?= im_money($jami_nasiya) ?> so'm</span>
          </div>
        </form>
      </div>
    </div>

    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-person-lines-fill" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Mijozlar ro'yxati</span>
        <span class="im-badge im-badge-muted"><?= count($mijozlar) ?> ta</span>
      </div>
      <?php if($mijozlar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr><th>Ism</th><th>Telefon</th><th>Toifa</th><th class="text-center">Sotuvlar</th><th class="text-right">Nasiya qoldi</th><th></th></tr></thead>
          <tbody>
          <?php foreach($mijozlar as $m): ?>
          <tr id="mr-<?= $m['id'] ?>">
            <td class="fw-semibold"><?= im_f($m['ism']) ?></td>
            <td class="text-muted"><?= im_f($m['telefon'] ?: '—') ?></td>
            <td>
              <?php if($m['toifa_nomi']): ?>
              <span class="im-badge" style="background:<?= im_f($m['rang']) ?>22;color:<?= im_f($m['rang']) ?>;border:1px solid <?= im_f($m['rang']) ?>44">
                <?= im_f($m['toifa_nomi']) ?> · <?= $m['chegirma_foiz'] ?>%
              </span>
              <?php else: ?><span class="text-muted fs-xs">—</span><?php endif; ?>
            </td>
            <td class="text-center"><span class="im-badge im-badge-muted"><?= $m['sotuvlar'] ?> ta</span></td>
            <td class="text-right num <?= $m['nasiya_q']>0?'fw-bold':'text-muted' ?>" style="<?= $m['nasiya_q']>0?'color:var(--danger)':'' ?>">
              <?= $m['nasiya_q'] > 0 ? im_money($m['nasiya_q']).' so\'m' : '—' ?>
            </td>
            <td class="text-right">
              <button class="im-btn im-btn-outline im-btn-sm" onclick="editMijoz(<?= htmlspecialchars(json_encode($m)) ?>)"><i class="bi bi-pencil-fill"></i></button>
              <button class="im-btn im-btn-ghost im-btn-sm text-danger ms-1" onclick="deleteMijoz(<?= $m['id'] ?>,'<?= im_js($m['ism']) ?>')"><i class="bi bi-trash-fill"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-person-lines-fill"></i><h4>Mijoz topilmadi</h4></div>
      <?php endif; ?>
    </div>
  </main>
</div>
</div>

<!-- Modal -->
<div class="im-overlay" id="mijoz-modal">
  <div class="im-modal">
    <div class="im-modal-header">
      <i class="bi bi-person-fill" style="color:var(--accent-dark);font-size:20px"></i>
      <span class="im-modal-title" id="m-modal-title">Mijoz qo'shish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="m-id">
      <div class="row g-3 mb-3">
        <div class="col-7"><label class="im-label">Ism *</label><input class="im-input" id="m-ism" type="text" placeholder="To'liq ism"></div>
        <div class="col-5"><label class="im-label">Telefon</label><input class="im-input" id="m-tel" type="text" placeholder="+998..."></div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="im-label">Toifa</label>
          <select class="im-select" id="m-toifa">
            <option value="">— Toifasiz —</option>
            <?php foreach($toifalar as $t): ?>
            <option value="<?= $t['id'] ?>"><?= im_f($t['nomi']) ?> (<?= $t['chegirma_foiz'] ?>%)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6"><label class="im-label">Manzil</label><input class="im-input" id="m-manzil" type="text" placeholder="Shahar, ko'cha..."></div>
      </div>
      <div class="im-form-group"><label class="im-label">Izoh</label><input class="im-input" id="m-izoh" type="text" placeholder="..."></div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="btn-m-save"><i class="bi bi-floppy-fill"></i> Saqlash</button>
    </div>
  </div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
document.getElementById('btn-add').addEventListener('click',()=>{
  ['m-id','m-ism','m-tel','m-manzil','m-izoh'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('m-toifa').value='';
  document.getElementById('m-modal-title').textContent="Mijoz qo'shish";
  NHModal.open('mijoz-modal');
  setTimeout(()=>document.getElementById('m-ism').focus(),200);
});
function editMijoz(m){
  document.getElementById('m-id').value=m.id;
  document.getElementById('m-ism').value=m.ism;
  document.getElementById('m-tel').value=m.telefon||'';
  document.getElementById('m-toifa').value=m.toifa_id||'';
  document.getElementById('m-manzil').value=m.manzil||'';
  document.getElementById('m-izoh').value=m.izoh||'';
  document.getElementById('m-modal-title').textContent="Mijozni tahrirlash";
  NHModal.open('mijoz-modal');
}
document.getElementById('btn-m-save').addEventListener('click',async()=>{
  const id=document.getElementById('m-id').value;
  const ism=document.getElementById('m-ism').value.trim();
  const tel=document.getElementById('m-tel').value.trim();
  const toifa=document.getElementById('m-toifa').value;
  const manzil=document.getElementById('m-manzil').value.trim();
  const izoh=document.getElementById('m-izoh').value.trim();
  if(!ism){NHToast.error('Ism kiritish shart');return;}
  const res=await IMAjax.post(window.im_BASE + 'admin/ajax/mijoz-save.php',{id,ism,tel,toifa,manzil,izoh});
  if(res.status==='ok'){NHToast.success(res.msg);setTimeout(()=>location.reload(),700);}
  else NHToast.error(res.msg);
});
async function deleteMijoz(id,ism){
  if(!await NHConfirm.ask(`«${ism}» mijozni o'chirilsinmi?`))return;
  const res=await IMAjax.post(window.im_BASE + 'admin/ajax/mijoz-delete.php',{id});
  if(res.status==='ok'){document.getElementById('mr-'+id)?.remove();NHToast.success(res.msg);}
  else NHToast.error(res.msg);
}
</script>
</body></html>
