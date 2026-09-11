<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$today = date('Y-m-d');
$sana  = $_GET['sana'] ?? $today;
$sd    = mysqli_real_escape_string($link, $sana);

// Harajatlar
$harajatlar = $db->rows(
    "SELECT h.*, x.ism AS xodim_ism FROM im_harajatlar h
     LEFT JOIN im_xodimlar x ON x.id=h.xodim_id
     WHERE h.sana='$sd' ORDER BY h.created_at DESC"
);

// Kun jami
$jami_harajat = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_harajatlar WHERE sana='$sd'");
$by_tur = $db->rows("SELECT tur, SUM(summa) AS s FROM im_harajatlar WHERE sana='$sd' GROUP BY tur");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Harajatlar | IMezon Do'kon</title>
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
    <div class="im-page-title"><i class="bi bi-cash-stack me-1"></i> Harajatlar</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-primary im-btn-sm" id="btn-harajat-add">
        <i class="bi bi-plus-lg"></i> Harajat qo'shish
      </button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">
    <!-- Sana filtr -->
    <div class="d-flex align-items-center gap-2 mb-3">
      <a href="?sana=<?= date('Y-m-d', strtotime($sana . ' -1 day')) ?>" class="im-btn im-btn-outline im-btn-icon"><i class="bi bi-chevron-left"></i></a>
      <input type="date" class="im-input im-input-sm" id="sana-picker" value="<?= $sana ?>"
             onchange="window.location='?sana='+this.value" style="max-width:160px">
      <a href="?sana=<?= date('Y-m-d', strtotime($sana . ' +1 day')) ?>" class="im-btn im-btn-outline im-btn-icon"><i class="bi bi-chevron-right"></i></a>
      <?php if ($sana !== $today): ?>
      <a href="?sana=<?= $today ?>" class="im-btn im-btn-outline im-btn-sm">Bugun</a>
      <?php endif; ?>
      <div class="ms-auto im-card px-4 py-2 d-flex align-items-center gap-3">
        <span class="text-muted fs-sm">Jami harajat:</span>
        <span class="fw-bold num fs-lg" style="color:var(--danger)"><?= im_money($jami_harajat) ?> so'm</span>
      </div>
    </div>

    <!-- Tur bo'yicha -->
    <?php if ($by_tur): ?>
    <div class="row g-2 mb-3">
      <?php foreach ($by_tur as $bt): ?>
      <?php $icons = ['ijara'=>'🏠','maosh'=>'👤','yuk'=>'🚛','kommunal'=>'💡','reklama'=>'📢','boshqa'=>'📋']; ?>
      <div class="col-auto">
        <div class="im-card px-3 py-2">
          <div class="text-muted fs-xs"><?= $icons[$bt['tur']] ?? '📋' ?> <?= im_f($bt['tur']) ?></div>
          <div class="fw-bold num"><?= im_money($bt['s']) ?> so'm</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-list-ul"></i>
        <span class="im-card-title"><?= im_date($sana) ?> — harajatlar</span>
        <span class="im-badge im-badge-muted"><?= count($harajatlar) ?> ta</span>
      </div>
      <?php if ($harajatlar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr><th>Nomi</th><th>Tur</th><th>To'lov</th><th class="text-right">Summa</th><th>Xodim</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($harajatlar as $h): ?>
            <tr id="hr-<?= $h['id'] ?>">
              <td class="fw-semibold"><?= im_f($h['nomi']) ?></td>
              <td><span class="im-badge im-badge-muted"><?= im_f($h['tur']) ?></span></td>
              <td class="text-muted fs-sm"><?= im_f($h['tolov_turi']) ?></td>
              <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($h['summa']) ?> so'm</td>
              <td class="text-muted fs-xs"><?= im_f($h['xodim_ism'] ?? '—') ?></td>
              <td>
                <button class="im-btn im-btn-icon im-btn-sm im-btn-ghost text-danger"
                        onclick="deleteHarajat(<?= $h['id'] ?>)" title="O'chirish">
                  <i class="bi bi-trash-fill"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-cash-stack"></i>
        <h4>Harajat yo'q</h4>
        <p class="text-muted">Bu kun uchun harajat kiritilmagan</p>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</div>

<!-- Harajat qo'shish modali -->
<div class="im-overlay" id="harajat-modal">
  <div class="im-modal">
    <div class="im-modal-header">
      <i class="bi bi-plus-circle-fill" style="color:var(--accent-dark);font-size:20px"></i>
      <span class="im-modal-title">Harajat qo'shish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <div class="im-form-group mb-3">
        <label class="im-label">Nomi *</label>
        <input class="im-input" type="text" id="h-nomi" placeholder="Harajat nomi..." required>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="im-label">Turi</label>
          <select class="im-select" id="h-tur">
            <option value="ijara">🏠 Ijara</option>
            <option value="maosh">👤 Maosh</option>
            <option value="yuk">🚛 Yuk</option>
            <option value="kommunal">💡 Kommunal</option>
            <option value="reklama">📢 Reklama</option>
            <option value="boshqa" selected>📋 Boshqa</option>
          </select>
        </div>
        <div class="col-6">
          <label class="im-label">To'lov turi</label>
          <select class="im-select" id="h-tolov">
            <option value="naqd"><?= im_tt_label('naqd') ?></option>
            <option value="karta"><?= im_tt_label('karta') ?></option>
            <option value="bank"><?= im_tt_label('bank') ?></option>
          </select>
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="im-label">Summa (so'm) *</label>
          <input class="im-input num" type="number" id="h-summa" min="1" placeholder="0">
        </div>
        <div class="col-6">
          <label class="im-label">Sana</label>
          <input class="im-input" type="date" id="h-sana" value="<?= $sana ?>">
        </div>
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="btn-harajat-save">
        <i class="bi bi-floppy-fill"></i> Saqlash
      </button>
    </div>
  </div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
document.getElementById('btn-harajat-add').addEventListener('click', () => {
  document.getElementById('h-nomi').value='';
  document.getElementById('h-summa').value='';
  NHModal.open('harajat-modal');
  setTimeout(()=>document.getElementById('h-nomi').focus(),200);
});

// Auto open modal on load
if (new URLSearchParams(location.search).get('add') === '1') {
    document.getElementById('btn-harajat-add').click();
    window.history.replaceState({}, document.title, window.location.pathname);
}

document.getElementById('btn-harajat-save').addEventListener('click', async () => {
  const nomi  = document.getElementById('h-nomi').value.trim();
  const summa = parseFloat(document.getElementById('h-summa').value) || 0;
  const tur   = document.getElementById('h-tur').value;
  const tolov = document.getElementById('h-tolov').value;
  const sana  = document.getElementById('h-sana').value;
  if (!nomi) { NHToast.error('Nomi kiriting'); return; }
  if (summa <= 0) { NHToast.error('Summa kiriting'); return; }

  const res = await IMAjax.post(window.im_BASE + 'dukon/ajax/harajat-save.php', { nomi, summa, tur, tolov_turi: tolov, sana });
  if (res.status === 'ok') { NHToast.success(res.msg); setTimeout(()=>location.reload(), 600); }
  else NHToast.error(res.msg);
});

async function deleteHarajat(id) {
  const ok = await NHConfirm.ask("Bu harajatni o'chirishni tasdiqlaysizmi?");
  if (!ok) return;
  const res = await IMAjax.post(window.im_BASE + 'dukon/ajax/harajat-delete.php', { id });
  if (res.status === 'ok') {
    const row = document.getElementById('hr-' + id);
    if (row) { row.style.opacity=0; setTimeout(()=>row.remove(), 300); }
    NHToast.success(res.msg);
  } else NHToast.error(res.msg);
}
</script>
</body>
</html>
