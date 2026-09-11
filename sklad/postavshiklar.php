<?php
// ============================================================
//  IMezon — Postavshiklar CRUD
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);

$db = new Cyber();

// Postavshiklar ro'yxati
$postavshiklar = $db->rows(
    "SELECT ps.*,
            (SELECT COUNT(*) FROM im_partiyalar p WHERE p.postavshik_id=ps.id) AS partiya_soni,
            (SELECT COALESCE(SUM(pd.qoldiq),0)
             FROM im_postavshik_qarz pd
             WHERE pd.postavshik_id=ps.id AND pd.status='ochiq') AS qarz_summa
     FROM im_postavshiklar ps
     WHERE ps.status=1
     ORDER BY ps.nomi ASC"
);

$arxiv_soni = (int)$db->val("SELECT COUNT(*) FROM im_postavshiklar WHERE status=0");

$page_title = 'Postavshiklar';
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

    <header class="im-topbar">
      <button class="im-topbar-btn" id="im-sidebar-toggle">
        <i class="bi bi-list"></i>
      </button>
      <div class="im-page-title">
        <i class="bi bi-truck me-1"></i> Postavshiklar
      </div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle title="Rejim">
          <i class="bi bi-moon-fill"></i>
        </button>
        <button class="im-btn im-btn-primary im-btn-sm" id="btn-qosh-ps">
          <i class="bi bi-plus-lg"></i> Qo'shish
        </button>
      </div>
    </header>

    <main class="im-content">

      <div class="im-breadcrumb">
        <a href="<?= im_BASE ?>sklad/index.php"><i class="bi bi-house-fill"></i></a>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Postavshiklar</span>
      </div>

      <!-- Search + info -->
      <div class="d-flex align-center gap-3 mb-3 flex-wrap">
        <div class="im-search" style="max-width:340px">
          <i class="bi bi-search"></i>
          <input type="text" id="ps-search" placeholder="Nomi, telefon yoki shahar...">
        </div>
        <div class="ms-auto d-flex align-center gap-2">
          <?php if ($arxiv_soni > 0): ?>
          <span class="im-badge im-badge-muted">
            <i class="bi bi-archive"></i> <?= $arxiv_soni ?> arxivda
          </span>
          <?php endif; ?>
          <span class="im-badge im-badge-primary">Jami: <?= count($postavshiklar) ?></span>
        </div>
      </div>

      <!-- Table -->
      <div class="im-card im-slide-in">
        <div class="im-table-wrap">
          <table class="im-table" id="ps-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Nomi</th>
                <th>Telefon</th>
                <th>Shahar</th>
                <th>Kontakt</th>
                <th class="text-right">Qarz</th>
                <th class="text-center">Partiyalar</th>
                <th class="text-right">Amallar</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($postavshiklar): ?>
              <?php foreach ($postavshiklar as $i => $ps): ?>
              <tr id="ps-row-<?= $ps['id'] ?>">
                <td class="text-muted fs-sm"><?= $i + 1 ?></td>
                <td>
                  <div class="fw-semibold"><?= im_f($ps['nomi']) ?></div>
                  <?php if ($ps['email']): ?>
                  <div class="text-muted fs-xs"><?= im_f($ps['email']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($ps['telefon']): ?>
                  <a href="tel:<?= im_f($ps['telefon']) ?>" class="im-chip">
                    <i class="bi bi-telephone-fill"></i>
                    <?= im_f($ps['telefon']) ?>
                  </a>
                  <?php else: ?>
                  <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted fs-sm"><?= $ps['shahar'] ? im_f($ps['shahar']) : '—' ?></td>
                <td class="text-muted fs-sm"><?= $ps['kontakt_ism'] ? im_f($ps['kontakt_ism']) : '—' ?></td>
                <td class="text-right">
                  <?php $qarz = (float)$ps['qarz_summa']; ?>
                  <?php if ($qarz > 0): ?>
                  <span class="im-badge im-badge-danger num">
                    <?= im_money($qarz) ?> so'm
                  </span>
                  <?php else: ?>
                  <span class="text-muted fs-sm">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if ((int)$ps['partiya_soni'] > 0): ?>
                  <span class="im-badge im-badge-info"><?= $ps['partiya_soni'] ?> ta</span>
                  <?php else: ?>
                  <span class="text-muted fs-sm">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-right">
                  <div class="d-flex gap-1 justify-content-end">
                    <button class="im-btn im-btn-ghost im-btn-icon"
                            onclick="psEdit(<?= htmlspecialchars(json_encode($ps), ENT_QUOTES) ?>)"
                            title="Tahrirlash">
                      <i class="bi bi-pencil-fill" style="color:var(--info)"></i>
                    </button>
                    <button class="im-btn im-btn-ghost im-btn-icon"
                            onclick="psDelete(<?= $ps['id'] ?>, '<?= im_js($ps['nomi']) ?>', <?= (int)$ps['partiya_soni'] ?>)"
                            title="O'chirish">
                      <i class="bi bi-trash-fill" style="color:var(--danger)"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr>
                <td colspan="8">
                  <div class="im-empty">
                    <i class="bi bi-truck"></i>
                    <h4>Postavshiklar yo'q</h4>
                    <p>Birinchi postavshikni qo'shing</p>
                  </div>
                </td>
              </tr>
              <?php endif; ?>
              <tr class="im-empty-row" style="display:none">
                <td colspan="8">
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
<div class="im-overlay" id="ps-modal">
  <div class="im-modal">
    <div class="im-modal-header">
      <span class="im-modal-title" id="ps-modal-title">Postavshik qo'shish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <form id="ps-form" novalidate>
        <input type="hidden" id="ps-id" name="id" value="">
        <div class="im-form-row cols-2 mb-3">
          <div class="im-form-group">
            <label class="im-label" for="ps-nomi">
              Nomi <span style="color:var(--danger)">*</span>
            </label>
            <input class="im-input" type="text" id="ps-nomi" name="nomi"
                   placeholder="Kompaniya nomi" required maxlength="200">
          </div>
          <div class="im-form-group">
            <label class="im-label" for="ps-telefon">Telefon</label>
            <input class="im-input" type="tel" id="ps-telefon" name="telefon"
                   placeholder="+998 90 000 00 00">
          </div>
        </div>
        <div class="im-form-row cols-2 mb-3">
          <div class="im-form-group">
            <label class="im-label" for="ps-email">Email</label>
            <input class="im-input" type="email" id="ps-email" name="email"
                   placeholder="info@example.com">
          </div>
          <div class="im-form-group">
            <label class="im-label" for="ps-shahar">Shahar</label>
            <input class="im-input" type="text" id="ps-shahar" name="shahar"
                   placeholder="Toshkent, Samarqand...">
          </div>
        </div>
        <div class="im-form-row cols-2 mb-3">
          <div class="im-form-group">
            <label class="im-label" for="ps-kontakt">Kontakt ismi</label>
            <input class="im-input" type="text" id="ps-kontakt" name="kontakt_ism"
                   placeholder="Mas'ul shaxs ismi">
          </div>
          <div class="im-form-group">
            <label class="im-label" for="ps-kontakt-tel">Kontakt telefon</label>
            <input class="im-input" type="tel" id="ps-kontakt-tel" name="kontakt_telefon"
                   placeholder="+998 ...">
          </div>
        </div>
        <div class="im-form-group">
          <label class="im-label" for="ps-izoh">Izoh</label>
          <textarea class="im-textarea" id="ps-izoh" name="izoh"
                    placeholder="Qo'shimcha ma'lumot" rows="2"></textarea>
        </div>
        <button type="submit" style="display:none"></button>
      </form>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="ps-save-btn">
        <i class="bi bi-check-lg"></i> Saqlash
      </button>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
const PS_AJAX_SAVE   = (window.im_BASE || '/') + 'sklad/ajax/ps-save.php';
const PS_AJAX_DELETE = (window.im_BASE || '/') + 'sklad/ajax/ps-delete.php';

// Search
NHTableFilter.bind('ps-search', 'ps-table', [1, 2, 3, 4]);

// Yangi
document.getElementById('btn-qosh-ps').addEventListener('click', () => {
  document.getElementById('ps-modal-title').textContent = "Postavshik qo'shish";
  document.getElementById('ps-form').reset();
  document.getElementById('ps-id').value = '';
  NHModal.open('ps-modal');
  setTimeout(() => document.getElementById('ps-nomi').focus(), 100);
});

// Tahrirlash
function psEdit(ps) {
  document.getElementById('ps-modal-title').textContent = 'Postavshikni tahrirlash';
  document.getElementById('ps-id').value = ps.id;
  document.getElementById('ps-nomi').value = ps.nomi || '';
  document.getElementById('ps-telefon').value = ps.telefon || '';
  document.getElementById('ps-email').value = ps.email || '';
  document.getElementById('ps-shahar').value = ps.shahar || '';
  document.getElementById('ps-kontakt').value = ps.kontakt_ism || '';
  document.getElementById('ps-kontakt-tel').value = ps.kontakt_telefon || '';
  document.getElementById('ps-izoh').value = ps.izoh || '';
  NHModal.open('ps-modal');
  setTimeout(() => document.getElementById('ps-nomi').focus(), 100);
}

// Saqlash
document.getElementById('ps-save-btn').addEventListener('click', async () => {
  const nomi = document.getElementById('ps-nomi').value.trim();
  if (!nomi) {
    NHToast.error('Postavshik nomini kiriting');
    document.getElementById('ps-nomi').focus();
    return;
  }

  const btn = document.getElementById('ps-save-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="im-spinner"></span>';

  const fd = new FormData(document.getElementById('ps-form'));
  const res = await IMAjax.post(PS_AJAX_SAVE, fd);

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-lg"></i> Saqlash';

  if (res.status === 'ok') {
    NHToast.success(res.msg || 'Saqlandi!');
    NHModal.close('ps-modal');
    setTimeout(() => location.reload(), 600);
  } else {
    NHToast.error(res.msg || 'Xatolik!');
  }
});

// O'chirish
async function psDelete(id, nomi, partiyas) {
  if (partiyas > 0) {
    NHToast.warning(`Bu postavshikda ${partiyas} ta partiya mavjud. O'chirib bo'lmaydi.`);
    return;
  }
  const ok = await NHConfirm.delete(nomi);
  if (!ok) return;

  const res = await IMAjax.post(PS_AJAX_DELETE, { id });
  if (res.status === 'ok') {
    NHToast.success(res.msg || "O'chirildi!");
    const row = document.getElementById(`ps-row-${id}`);
    if (row) { row.style.opacity = '0'; setTimeout(() => row.remove(), 300); }
  } else {
    NHToast.error(res.msg || 'Xatolik!');
  }
}
document.getElementById('ps-form').addEventListener('submit', function(e) {
  e.preventDefault();
  document.getElementById('ps-save-btn').click();
});
</script>
</body>
</html>
