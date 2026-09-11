<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$toifalar = $db->rows("SELECT t.*, COUNT(m.id) AS mijozlar_soni
    FROM im_mijoz_toifalari t
    LEFT JOIN im_mijozlar m ON m.toifa_id=t.id AND m.status=1
    GROUP BY t.id ORDER BY t.chegirma_foiz DESC");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Mijoz toifalari | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-tags-fill me-1"></i> Mijoz toifalari</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-primary im-btn-sm" data-open-modal="toifa-add-modal">
        <i class="bi bi-plus-lg"></i> Yangi toifa
      </button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- Toifalar jadvali -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-tags" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Barcha toifalar (<?= count($toifalar) ?> ta)</span>
      </div>
      <div class="im-card-body">
        <?php if (!$toifalar): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-tags" style="font-size:40px;opacity:.3"></i>
          <p class="mt-2">Hali toifa qo'shilmagan</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="im-table" id="toifa-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Toifa nomi</th>
                <th>Chegirma</th>
                <th>Rang</th>
                <th>Tavsif</th>
                <th>Mijozlar</th>
                <th>Amallar</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($toifalar as $i => $t): ?>
            <tr id="toifa-row-<?= $t['id'] ?>">
              <td><?= $i+1 ?></td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:8px">
                  <span style="width:12px;height:12px;border-radius:50%;background:<?= im_f($t['rang'] ?? '#888') ?>;display:inline-block;flex-shrink:0"></span>
                  <strong><?= im_f($t['nomi']) ?></strong>
                </span>
              </td>
              <td>
                <span class="im-badge" style="background:color-mix(in srgb,var(--accent) 15%,transparent);color:var(--accent-dark);font-weight:700">
                  <?= (float)$t['chegirma_foiz'] ?>%
                </span>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:6px">
                  <div style="width:24px;height:24px;border-radius:6px;background:<?= im_f($t['rang'] ?? '#888') ?>;border:1px solid var(--border)"></div>
                  <code style="font-size:11px"><?= im_f($t['rang'] ?? '#888') ?></code>
                </div>
              </td>
              <td class="text-muted fs-xs"><?= im_f($t['tavsif'] ?? '—') ?></td>
              <td>
                <span class="im-badge">
                  <i class="bi bi-people-fill me-1"></i><?= (int)$t['mijozlar_soni'] ?> ta
                </span>
              </td>
              <td>
                <button class="im-btn im-btn-outline im-btn-sm btn-toifa-edit"
                  data-id="<?= $t['id'] ?>"
                  data-nomi="<?= im_f($t['nomi']) ?>"
                  data-chegirma="<?= (float)$t['chegirma_foiz'] ?>"
                  data-rang="<?= im_f($t['rang'] ?? '#e2b96f') ?>"
                  data-tavsif="<?= im_f($t['tavsif'] ?? '') ?>">
                  <i class="bi bi-pencil-fill"></i>
                </button>
                <?php if ((int)$t['mijozlar_soni'] === 0): ?>
                <button class="im-btn im-btn-danger im-btn-sm btn-toifa-del" data-id="<?= $t['id'] ?>" data-nomi="<?= im_f($t['nomi']) ?>">
                  <i class="bi bi-trash3-fill"></i>
                </button>
                <?php else: ?>
                <button class="im-btn im-btn-outline im-btn-sm" disabled title="Bog'liq mijozlar bor">
                  <i class="bi bi-trash3" style="opacity:.4"></i>
                </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Toifalar haqida ma'lumot -->
    <div class="row g-3 mt-1">
      <div class="col-12">
        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-info-circle" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Toifalar qanday ishlaydi?</span>
          </div>
          <div class="im-card-body p-4">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="im-card p-3" style="border-left:3px solid var(--accent)">
                  <div class="fw-bold mb-1">🏷️ Chegirma</div>
                  <div class="text-muted fs-xs">Toifa chegirmasi POS ekranida avtomatik qo'llanadi. Kassir qo'shimcha chegirma bera olmaydi (0% toifadagi) yoki summasi qo'shiladi.</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="im-card p-3" style="border-left:3px solid #5dade2">
                  <div class="fw-bold mb-1">👤 Mijoz bog'lash</div>
                  <div class="text-muted fs-xs">Har bir mijozga toifa belgilanadi. POS da mijoz tanlanganda toifa chegirmasi avtomatik faollashadi.</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="im-card p-3" style="border-left:3px solid #58d68d">
                  <div class="fw-bold mb-1">📊 Eksport</div>
                  <div class="text-muted fs-xs">Mijozlar bazasi eksportida har bir mijozning toifasi va chegirma foizi ko'rsatiladi.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </main>
</div>
</div>

<!-- Qo'shish / Tahrirlash modali -->
<div class="im-overlay" id="toifa-add-modal">
  <div class="im-modal" style="max-width:440px">
    <div class="im-modal-header">
      <i class="bi bi-tag-fill" style="color:var(--accent-dark);font-size:20px"></i>
      <span class="im-modal-title" id="modal-toifa-title">Yangi toifa</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="toifa-id" value="">
      <div class="im-form-group mb-3">
        <label class="im-label">Toifa nomi *</label>
        <input class="im-input" type="text" id="toifa-nomi" placeholder="Masalan: VIP, Ulgurji..." maxlength="50">
      </div>
      <div class="row g-3 mb-3">
        <div class="col-7">
          <label class="im-label">Chegirma foizi (%)</label>
          <input class="im-input num" type="number" id="toifa-chegirma" value="0" min="0" max="50" step="0.5">
          <small class="text-muted fs-xs">0 = chegirma yo'q</small>
        </div>
        <div class="col-5">
          <label class="im-label">Rang</label>
          <input class="im-input" type="color" id="toifa-rang" value="#e2b96f" style="height:42px;padding:4px 8px;cursor:pointer">
        </div>
      </div>
      <div class="im-form-group mb-3">
        <label class="im-label">Tavsif (ixtiyoriy)</label>
        <input class="im-input" type="text" id="toifa-tavsif" placeholder="Toifa haqida qisqa ma'lumot...">
      </div>
      <!-- Preview -->
      <div style="background:var(--bg);border-radius:var(--radius);padding:12px;text-align:center;margin-top:4px">
        <span id="toifa-preview-badge" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-weight:700;font-size:13px">
          <span id="preview-dot" style="width:10px;height:10px;border-radius:50%;background:#e2b96f"></span>
          <span id="preview-name">VIP</span>
          <span style="font-weight:400;opacity:.7">| <span id="preview-ch">0</span>% chegirma</span>
        </span>
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="btn-toifa-save">
        <i class="bi bi-floppy-fill"></i> Saqlash
      </button>
    </div>
  </div>
</div>

<!-- O'chirish modali -->
<div class="im-overlay" id="toifa-del-modal">
  <div class="im-modal" style="max-width:380px">
    <div class="im-modal-header">
      <i class="bi bi-exclamation-triangle-fill" style="color:var(--danger)"></i>
      <span class="im-modal-title">Toifani o'chirish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body text-center">
      <div style="font-size:36px;margin-bottom:8px">🗑️</div>
      <p>"<strong id="del-toifa-nomi"></strong>" toifasini o'chirishni tasdiqlaysizmi?</p>
      <input type="hidden" id="del-toifa-id">
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-danger" id="btn-toifa-del-confirm">
        <i class="bi bi-trash3-fill"></i> O'chirish
      </button>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
// ── Live preview yangilash
function updatePreview() {
  const n = document.getElementById('toifa-nomi').value || 'Toifa';
  const c = document.getElementById('toifa-chegirma').value || '0';
  const r = document.getElementById('toifa-rang').value;
  document.getElementById('preview-dot').style.background = r;
  document.getElementById('preview-name').textContent = n;
  document.getElementById('preview-ch').textContent = c;
  const bg = r + '22';
  document.getElementById('toifa-preview-badge').style.background = bg;
  document.getElementById('toifa-preview-badge').style.color = r;
}
['toifa-nomi','toifa-chegirma','toifa-rang'].forEach(id =>
  document.getElementById(id)?.addEventListener('input', updatePreview)
);

// ── Qo'shish modal
document.querySelectorAll('[data-open-modal="toifa-add-modal"]').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('toifa-id').value = '';
    document.getElementById('toifa-nomi').value = '';
    document.getElementById('toifa-chegirma').value = '0';
    document.getElementById('toifa-rang').value = '#e2b96f';
    document.getElementById('toifa-tavsif').value = '';
    document.getElementById('modal-toifa-title').textContent = 'Yangi toifa';
    updatePreview();
    NHModal.open('toifa-add-modal');
  });
});

// ── Tahrirlash
document.querySelectorAll('.btn-toifa-edit').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('toifa-id').value      = btn.dataset.id;
    document.getElementById('toifa-nomi').value    = btn.dataset.nomi;
    document.getElementById('toifa-chegirma').value= btn.dataset.chegirma;
    document.getElementById('toifa-rang').value    = btn.dataset.rang;
    document.getElementById('toifa-tavsif').value  = btn.dataset.tavsif;
    document.getElementById('modal-toifa-title').textContent = 'Toifani tahrirlash';
    updatePreview();
    NHModal.open('toifa-add-modal');
  });
});

// ── Saqlash
document.getElementById('btn-toifa-save').addEventListener('click', async () => {
  const nomi = document.getElementById('toifa-nomi').value.trim();
  if (!nomi) { NHToast.warning('Toifa nomini kiriting'); return; }

  const payload = {
    id:       document.getElementById('toifa-id').value,
    nomi,
    chegirma: document.getElementById('toifa-chegirma').value,
    rang:     document.getElementById('toifa-rang').value,
    tavsif:   document.getElementById('toifa-tavsif').value.trim(),
  };

  const btn = document.getElementById('btn-toifa-save');
  btn.disabled = true; btn.innerHTML = '<span class="im-spinner"></span>';
  const res = await IMAjax.post(window.im_BASE + 'admin/ajax/toifa-save.php', payload);
  btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy-fill"></i> Saqlash';

  if (res.status === 'ok') {
    NHToast.success(res.msg);
    NHModal.closeAll();
    setTimeout(() => location.reload(), 600);
  } else NHToast.error(res.msg);
});

// ── O'chirish modal
document.querySelectorAll('.btn-toifa-del').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('del-toifa-id').value = btn.dataset.id;
    document.getElementById('del-toifa-nomi').textContent = btn.dataset.nomi;
    NHModal.open('toifa-del-modal');
  });
});

document.getElementById('btn-toifa-del-confirm').addEventListener('click', async () => {
  const id = document.getElementById('del-toifa-id').value;
  const res = await IMAjax.post(window.im_BASE + 'admin/ajax/toifa-delete.php', { id });
  if (res.status === 'ok') {
    NHToast.success(res.msg);
    NHModal.closeAll();
    setTimeout(() => location.reload(), 600);
  } else NHToast.error(res.msg);
});
</script>
</body>
</html>
