<?php
// ============================================================
//  IMezon — Kategoriyalar CRUD
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

// Kategoriyalar ro'yxati (mahsulot soni bilan)
$kategoriyalar = $db->rows(
    "SELECT k.*,
            (SELECT COUNT(*) FROM im_mahsulotlar m WHERE m.kategoriya_id=k.id AND m.status=1) AS mah_soni
     FROM im_kategoriyalar k
     WHERE k.status=1
     ORDER BY k.nomi ASC"
);

// Arxivlanganlar soni
$arxiv_soni = (int)$db->val("SELECT COUNT(*) FROM im_kategoriyalar WHERE status=0");

$page_title = 'Kategoriyalar';

// Predefined ranglar
$ranglar = [
  '#e74c3c','#e67e22','#f1c40f','#2ecc71','#1abc9c',
  '#3498db','#9b59b6','#e91e63','#795548','#607d8b',
  '#00bcd4','#ff5722','#8bc34a','#673ab7','#ff9800',
  '#2196f3','#4caf50','#f44336','#9c27b0','#009688'
];
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= im_f($page_title) ?> | IMezon Sklad</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
  <link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
</head>
<body>
<div class="im-wrapper">

  <?php require_once __DIR__ . '/navbar.php'; ?>

  <div class="im-main">

    <!-- Topbar -->
    <header class="im-topbar">
      <button class="im-topbar-btn" id="im-sidebar-toggle">
        <i class="bi bi-list"></i>
      </button>
      <div class="im-page-title">
        <i class="bi bi-tags-fill me-1"></i> Kategoriyalar
      </div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle title="Rejim">
          <i class="bi bi-moon-fill"></i>
        </button>
        <button class="im-btn im-btn-primary im-btn-sm" id="btn-qosh-kat">
          <i class="bi bi-plus-lg"></i> Qo'shish
        </button>
      </div>
    </header>

    <main class="im-content">

      <!-- Breadcrumb -->
      <div class="im-breadcrumb">
        <a href="<?= im_BASE ?>sklad/index.php"><i class="bi bi-house-fill"></i></a>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Kategoriyalar</span>
      </div>

      <!-- Search + info bar -->
      <div class="d-flex align-center gap-3 mb-3 flex-wrap">
        <div class="im-search" style="max-width:320px">
          <i class="bi bi-search"></i>
          <input type="text" id="kat-search" placeholder="Kategoriya qidirish...">
        </div>
        <div class="ms-auto d-flex align-center gap-2">
          <?php if ($arxiv_soni > 0): ?>
          <span class="im-badge im-badge-muted">
            <i class="bi bi-archive"></i> <?= $arxiv_soni ?> arxivda
          </span>
          <?php endif; ?>
          <span class="im-badge im-badge-primary">
            Jami: <?= count($kategoriyalar) ?>
          </span>
        </div>
      </div>

      <!-- Table card -->
      <div class="im-card im-slide-in">
        <div class="im-table-wrap">
          <table class="im-table" id="kat-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Rang</th>
                <th>Nomi</th>
                <th>Tavsif</th>
                <th class="text-center">Mahsulotlar</th>
                <th class="text-right">Amallar</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($kategoriyalar): ?>
              <?php foreach ($kategoriyalar as $i => $k): ?>
              <tr id="kat-row-<?= $k['id'] ?>">
                <td class="text-muted fs-sm"><?= $i + 1 ?></td>
                <td>
                  <span class="im-color-dot"
                        style="background:<?= im_f($k['rang'] ?: '#adb5bd') ?>;
                               width:20px;height:20px;border-radius:6px;display:inline-block"
                        title="<?= im_f($k['rang']) ?>"></span>
                </td>
                <td>
                  <span class="fw-semibold"><?= im_f($k['nomi']) ?></span>
                </td>
                <td class="text-muted fs-sm">
                  <?= $k['tavsif'] ? im_f(mb_strimwidth($k['tavsif'], 0, 60, '...', 'UTF-8')) : '—' ?>
                </td>
                <td class="text-center">
                  <?php if ((int)$k['mah_soni'] > 0): ?>
                  <a href="<?= im_BASE ?>sklad/mahsulotlar.php?kat=<?= $k['id'] ?>"
                     class="im-badge im-badge-info">
                    <?= $k['mah_soni'] ?> ta
                  </a>
                  <?php else: ?>
                  <span class="text-muted fs-sm">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-right">
                  <div class="d-flex gap-1 justify-content-end">
                    <button class="im-btn im-btn-ghost im-btn-icon"
                            onclick="katEdit(<?= $k['id'] ?>, '<?= im_js($k['nomi']) ?>', '<?= im_js($k['rang']) ?>', '<?= im_js($k['tavsif']) ?>')"
                            title="Tahrirlash">
                      <i class="bi bi-pencil-fill" style="color:var(--info)"></i>
                    </button>
                    <button class="im-btn im-btn-ghost im-btn-icon"
                            onclick="katDelete(<?= $k['id'] ?>, '<?= im_js($k['nomi']) ?>', <?= (int)$k['mah_soni'] ?>)"
                            title="O'chirish">
                      <i class="bi bi-trash-fill" style="color:var(--danger)"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr>
                <td colspan="6">
                  <div class="im-empty">
                    <i class="bi bi-tags"></i>
                    <h4>Kategoriyalar yo'q</h4>
                    <p>Birinchi kategoriyani qo'shing</p>
                  </div>
                </td>
              </tr>
              <?php endif; ?>
              <tr class="im-empty-row" style="display:none">
                <td colspan="6">
                  <div class="im-empty">
                    <i class="bi bi-search"></i>
                    <h4>Topilmadi</h4>
                    <p>Qidiruv natijalari bo'sh</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ─── Qo'shish / Tahrirlash modali ─── -->
<div class="im-overlay" id="kat-modal">
  <div class="im-modal im-modal-sm">
    <div class="im-modal-header">
      <span class="im-modal-title" id="kat-modal-title">Kategoriya qo'shish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <form id="kat-form" novalidate>
        <input type="hidden" id="kat-id" name="id" value="">
        <div class="im-form-group mb-3">
          <label class="im-label" for="kat-nomi">Nomi <span style="color:var(--danger)">*</span></label>
          <input class="im-input" type="text" id="kat-nomi" name="nomi"
                 placeholder="Masalan: Sovg'a qutilar" required maxlength="120">
        </div>
        <div class="im-form-group mb-3">
          <label class="im-label">Rang</label>
          <div class="im-color-picker" id="kat-color-picker">
            <?php foreach ($ranglar as $r): ?>
            <button type="button" class="im-color-opt" style="background:<?= $r ?>"
                    data-color="<?= $r ?>" title="<?= $r ?>"></button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" id="kat-rang" name="rang" value="">
          <div class="mt-2 d-flex align-center gap-2">
            <label class="im-label mb-0">Yoki tanlang:</label>
            <input type="color" id="kat-color-custom"
                   style="width:36px;height:30px;padding:2px;border:1.5px solid var(--border);
                          border-radius:var(--radius-sm);cursor:pointer;background:var(--card)">
          </div>
        </div>
        <div class="im-form-group">
          <label class="im-label" for="kat-tavsif">Tavsif</label>
          <textarea class="im-textarea" id="kat-tavsif" name="tavsif"
                    placeholder="Qisqacha tavsif (ixtiyoriy)" rows="2"></textarea>
        </div>
      </form>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="kat-save-btn">
        <i class="bi bi-check-lg"></i> Saqlash
      </button>
    </div>
  </div>
</div>

<!-- Toast container -->
<div id="im-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
// ─── Kategoriya sahifasi JS ───────────────────────────────
const AJAX_SAVE   = (window.im_BASE || '/') + 'sklad/ajax/kat-save.php';
const AJAX_DELETE = (window.im_BASE || '/') + 'sklad/ajax/kat-delete.php';

// Search
NHTableFilter.bind('kat-search', 'kat-table', [2, 3]);

// Color picker
document.querySelectorAll('.im-color-opt').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.im-color-opt').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('kat-rang').value = btn.dataset.color;
    document.getElementById('kat-color-custom').value = btn.dataset.color;
  });
});

document.getElementById('kat-color-custom').addEventListener('input', function() {
  document.getElementById('kat-rang').value = this.value;
  document.querySelectorAll('.im-color-opt').forEach(b => b.classList.remove('selected'));
});

function setColor(rang) {
  document.getElementById('kat-rang').value = rang || '';
  document.querySelectorAll('.im-color-opt').forEach(b => {
    b.classList.toggle('selected', b.dataset.color === rang);
  });
  if (rang) document.getElementById('kat-color-custom').value = rang;
}

// Yangi qo'shish
document.getElementById('btn-qosh-kat').addEventListener('click', () => {
  document.getElementById('kat-modal-title').textContent = "Kategoriya qo'shish";
  document.getElementById('kat-form').reset();
  document.getElementById('kat-id').value = '';
  document.querySelectorAll('.im-color-opt').forEach(b => b.classList.remove('selected'));
  document.getElementById('kat-rang').value = '';
  NHModal.open('kat-modal');
  setTimeout(() => document.getElementById('kat-nomi').focus(), 100);
});

// Tahrirlash
function katEdit(id, nomi, rang, tavsif) {
  document.getElementById('kat-modal-title').textContent = 'Kategoriyani tahrirlash';
  document.getElementById('kat-id').value = id;
  document.getElementById('kat-nomi').value = nomi;
  document.getElementById('kat-tavsif').value = tavsif;
  setColor(rang);
  NHModal.open('kat-modal');
  setTimeout(() => document.getElementById('kat-nomi').focus(), 100);
}

// Saqlash
document.getElementById('kat-save-btn').addEventListener('click', async () => {
  const nomi = document.getElementById('kat-nomi').value.trim();
  if (!nomi) {
    NHToast.error('Kategoriya nomini kiriting');
    document.getElementById('kat-nomi').focus();
    return;
  }

  const btn = document.getElementById('kat-save-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="im-spinner"></span>';

  const fd = new FormData(document.getElementById('kat-form'));
  const res = await IMAjax.post(AJAX_SAVE, fd);

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-lg"></i> Saqlash';

  if (res.status === 'ok') {
    NHToast.success(res.msg || 'Saqlandi!');
    NHModal.close('kat-modal');
    setTimeout(() => location.reload(), 600);
  } else {
    NHToast.error(res.msg || 'Xatolik yuz berdi');
  }
});

// O'chirish
async function katDelete(id, nomi, mahSoni) {
  if (mahSoni > 0) {
    NHToast.warning(`Bu kategoriyada ${mahSoni} ta mahsulot bor. Avval mahsulotlarni o'chirish yoki ko'chirish kerak.`);
    return;
  }
  const ok = await NHConfirm.delete(nomi);
  if (!ok) return;

  const res = await IMAjax.post(AJAX_DELETE, { id });
  if (res.status === 'ok') {
    NHToast.success(res.msg || "O'chirildi!");
    const row = document.getElementById(`kat-row-${id}`);
    if (row) {
      row.style.transition = 'opacity .3s';
      row.style.opacity = '0';
      setTimeout(() => row.remove(), 300);
    }
  } else {
    NHToast.error(res.msg || 'Xatolik!');
  }
}

// Enter bilan saqlash
document.getElementById('kat-nomi').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    document.getElementById('kat-save-btn').click();
  }
});
document.getElementById('kat-form').addEventListener('submit', e => {
  e.preventDefault();
});
</script>
</body>
</html>
