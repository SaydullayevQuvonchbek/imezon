<?php
// ============================================================
//  IMezon — Filiallar Boshqaruvi
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$filiallar = $db->rows("SELECT f.*,
    (SELECT COUNT(*) FROM im_xodimlar WHERE filial_id=f.id AND status=1) AS xodim_soni,
    (SELECT COUNT(*) FROM im_smena WHERE filial_id=f.id AND holat='ochiq') AS ochiq_smena,
    (SELECT COALESCE(SUM(tolov_summa),0) FROM im_sotuvlar WHERE filial_id=f.id AND DATE(sana)=CURDATE()) AS bugun_sotuv,
    (SELECT naqd_balans FROM im_kassa WHERE filial_id=f.id LIMIT 1) AS kassa_naqd
    FROM im_filiallar f ORDER BY f.tartib ASC");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Filiallar | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-building me-1"></i> Filiallar</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-primary im-btn-sm" id="btn-add">
        <i class="bi bi-plus-lg"></i> Yangi filial
      </button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- Filial kartalar -->
    <div class="row g-3 mb-4">
      <?php foreach ($filiallar as $f): ?>
      <div class="col-md-4">
        <div class="im-card h-100" style="border-top: 4px solid <?= im_f($f['rang']) ?>">
          <div class="im-card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <div class="fw-bold fs-5"><?= im_f($f['nomi']) ?></div>
                <div class="text-muted fs-xs"><code><?= im_f($f['kod']) ?></code>
                  <?= $f['manzil'] ? ' · ' . im_f($f['manzil']) : '' ?></div>
              </div>
              <span class="im-badge im-badge-<?= $f['status'] ? 'success' : 'muted' ?>">
                <?= $f['status'] ? 'Aktiv' : 'Yopiq' ?>
              </span>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <div class="text-muted fs-xs">Bugun sotuv</div>
                <div class="fw-bold num" style="color:var(--accent-dark)"><?= im_money($f['bugun_sotuv']) ?> so'm</div>
              </div>
              <div class="col-6">
                <div class="text-muted fs-xs">Kassa (naqd)</div>
                <div class="fw-bold num" style="color:var(--success)"><?= im_money($f['kassa_naqd'] ?? 0) ?> so'm</div>
              </div>
              <div class="col-6">
                <div class="text-muted fs-xs">Xodimlar</div>
                <div class="fw-bold"><?= (int)$f['xodim_soni'] ?> ta</div>
              </div>
              <div class="col-6">
                <div class="text-muted fs-xs">Ochiq smena</div>
                <div class="fw-bold">
                  <?php if ($f['ochiq_smena']): ?>
                    <span class="im-badge im-badge-success">🟢 Ochiq</span>
                  <?php else: ?>
                    <span class="im-badge im-badge-muted">—</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <button class="im-btn im-btn-outline im-btn-sm" onclick="editFilial(<?= htmlspecialchars(json_encode($f)) ?>)">
                <i class="bi bi-pencil-fill"></i> Tahrirlash
              </button>
              <a href="<?= im_BASE ?>admin/index.php?filial=<?= $f['id'] ?>" class="im-btn im-btn-dark im-btn-sm">
                <i class="bi bi-bar-chart-fill"></i> Hisobot
              </a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Xodimlar bo'yicha filial joriy holati -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-people-fill" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Xodimlar filiallarda</span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr><th>Filial</th><th>Xodim</th><th>Login</th><th>Rol</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php
          $xodimlar = $db->rows(
              "SELECT x.*, f.nomi AS filial_nomi, f.rang AS filial_rang
               FROM im_xodimlar x
               LEFT JOIN im_filiallar f ON f.id=x.filial_id
               ORDER BY x.filial_id ASC, x.id ASC"
          );
          foreach ($xodimlar as $x):
          ?>
          <tr>
            <td>
              <?php if ($x['filial_id']): ?>
                <span class="im-badge" style="background:<?= im_f($x['filial_rang'] ?? '#6c757d') ?>22;color:<?= im_f($x['filial_rang'] ?? '#6c757d') ?>;border:1px solid <?= im_f($x['filial_rang'] ?? '#6c757d') ?>44">
                  <?= im_f($x['filial_nomi'] ?? 'Filial '.$x['filial_id']) ?>
                </span>
              <?php else: ?>
                <span class="text-muted fs-xs">Barcha</span>
              <?php endif; ?>
            </td>
            <td class="fw-semibold"><?= im_f($x['ism']) ?></td>
            <td><code class="fs-xs"><?= im_f($x['login']) ?></code></td>
            <td><span class="im-badge im-badge-<?= ['admin'=>'danger','sklad'=>'primary','kassir'=>'success'][$x['rol']] ?? 'muted' ?>"><?= im_f($x['rol']) ?></span></td>
            <td><span class="im-badge im-badge-<?= $x['status'] ? 'success' : 'muted' ?>"><?= $x['status'] ? 'Aktiv' : 'Bloq' ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>
</div>
<div id="im-toast-container"></div>

<!-- Filial modal -->
<div class="im-overlay" id="filial-modal">
  <div class="im-modal" style="max-width:520px;width:96%">
    <div class="im-modal-header">
      <i class="bi bi-building" style="color:var(--accent-dark);font-size:20px"></i>
      <span class="im-modal-title" id="f-modal-title">Yangi filial</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="f-id">
      <div class="row g-3 mb-3">
        <div class="col-7">
          <label class="im-label">Filial nomi *</label>
          <input class="im-input" type="text" id="f-nomi" placeholder="Masalan: Chilonzor filiali">
        </div>
        <div class="col-5">
          <label class="im-label">Kod *</label>
          <input class="im-input" type="text" id="f-kod" placeholder="F01, F02...">
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-7">
          <label class="im-label">Manzil</label>
          <input class="im-input" type="text" id="f-manzil" placeholder="Ko'cha, shahar">
        </div>
        <div class="col-5">
          <label class="im-label">Telefon</label>
          <input class="im-input" type="text" id="f-telefon" placeholder="+998901234567">
        </div>
      </div>
      <div class="row g-3">
        <div class="col-4">
          <label class="im-label">Rang</label>
          <input class="im-input" type="color" id="f-rang" value="#e2b96f" style="height:42px;padding:4px">
        </div>
        <div class="col-4">
          <label class="im-label">Tartib</label>
          <input class="im-input num" type="number" id="f-tartib" value="1" min="1">
        </div>
        <div class="col-4">
          <label class="im-label">Status</label>
          <select class="im-select" id="f-status">
            <option value="1">✅ Aktiv</option>
            <option value="0">🚫 Yopiq</option>
          </select>
        </div>
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="btn-f-save"><i class="bi bi-floppy-fill"></i> Saqlash</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
document.getElementById('btn-add').addEventListener('click', () => {
  document.getElementById('f-id').value='';
  document.getElementById('f-nomi').value='';
  document.getElementById('f-kod').value='';
  document.getElementById('f-manzil').value='';
  document.getElementById('f-telefon').value='';
  document.getElementById('f-rang').value='#e2b96f';
  document.getElementById('f-tartib').value='1';
  document.getElementById('f-status').value='1';
  document.getElementById('f-modal-title').textContent="Yangi filial";
  NHModal.open('filial-modal');
  setTimeout(()=>document.getElementById('f-nomi').focus(),200);
});

function editFilial(f) {
  document.getElementById('f-id').value=f.id;
  document.getElementById('f-nomi').value=f.nomi;
  document.getElementById('f-kod').value=f.kod;
  document.getElementById('f-manzil').value=f.manzil||'';
  document.getElementById('f-telefon').value=f.telefon||'';
  document.getElementById('f-rang').value=f.rang||'#e2b96f';
  document.getElementById('f-tartib').value=f.tartib||1;
  document.getElementById('f-status').value=f.status;
  document.getElementById('f-modal-title').textContent="Filialni tahrirlash";
  NHModal.open('filial-modal');
}

document.getElementById('btn-f-save').addEventListener('click', async () => {
  const data = {
    id:     document.getElementById('f-id').value,
    nomi:   document.getElementById('f-nomi').value.trim(),
    kod:    document.getElementById('f-kod').value.trim(),
    manzil: document.getElementById('f-manzil').value.trim(),
    telefon:document.getElementById('f-telefon').value.trim(),
    rang:   document.getElementById('f-rang').value,
    tartib: document.getElementById('f-tartib').value,
    status: document.getElementById('f-status').value,
  };
  if (!data.nomi || !data.kod) { NHToast.error('Nomi va kod kiritish shart'); return; }
  const res = await IMAjax.post(window.im_BASE + 'admin/ajax/filial-save.php', data);
  if (res.status==='ok') { NHToast.success(res.msg); setTimeout(()=>location.reload(),700); }
  else NHToast.error(res.msg);
});
</script>
</body>
</html>
