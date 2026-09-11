<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();

// Valyuta kurslari tarixi
$kurslar = $db->rows(
    "SELECT vk.*, x.ism AS xodim FROM im_valyuta_kurs vk
     LEFT JOIN im_xodimlar x ON x.id=vk.qo_shgan_id
     ORDER BY vk.sana DESC, vk.id DESC LIMIT 30"
);
$joriy = im_usd_kurs();

// Bugungi kurs
$bugun_bor = $db->val("SELECT id FROM im_valyuta_kurs WHERE sana=CURDATE() AND tasdiqlandi=1 LIMIT 1");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Valyuta | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-currency-exchange me-1"></i> Valyuta kurslari</div>
    <div class="im-topbar-actions"><button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button></div>
  </header>
  <main class="im-content">
    <div class="row g-3 mb-4">
      <!-- Joriy kurs -->
      <div class="col-md-4">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-currency-dollar" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Joriy USD kursi</span>
          </div>
          <div class="im-card-body p-4 text-center">
            <div style="font-size:48px;font-weight:800;color:var(--accent-dark)" class="num"><?= im_money($joriy) ?></div>
            <div class="text-muted mt-1">1 USD = <strong><?= im_money($joriy) ?></strong> so'm</div>
            <?php if (!$bugun_bor): ?>
            <div class="im-badge im-badge-warning mt-2">⚠️ Bugun kurs kiritilmagan</div>
            <?php else: ?>
            <div class="im-badge im-badge-success mt-2">✅ Bugun tasdiqlangan</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <!-- Yangi kurs kiritish -->
      <div class="col-md-8">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-plus-circle-fill" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Yangi kurs kiritish</span>
          </div>
          <div class="im-card-body p-4">
            <div class="row g-3">
              <div class="col-4">
                <label class="im-label">Sana</label>
                <input class="im-input" type="date" id="vk-sana" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-4">
                <label class="im-label">1 USD = (so'm) *</label>
                <input class="im-input num" type="number" id="vk-kurs" value="<?= $joriy ?>" min="1000" step="10">
              </div>
              <div class="col-4">
                <label class="im-label">Manba</label>
                <select class="im-select" id="vk-manba">
                  <option value="qolda">Qo'lda</option>
                  <option value="cbr">Markaziy Bank</option>
                  <option value="bozor">Bozor</option>
                </select>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button class="im-btn im-btn-primary" id="btn-kurs-save">
                <i class="bi bi-check-circle-fill"></i> Tasdiqlash va saqlash
              </button>
              <div class="text-muted fs-sm d-flex align-items-center ms-2">
                💡 Barcha POS savdo operatsiyalarida bu kurs ishlatiladi
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Kurslar tarixi -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-clock-history"></i>
        <span class="im-card-title">Kurslar tarixi</span>
        <span class="im-badge im-badge-muted">So'nggi 30 ta</span>
      </div>
      <?php if($kurslar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr><th>Sana</th><th class="text-right">1 USD</th><th>Manba</th><th>Holat</th><th>Kiritdi</th></tr></thead>
          <tbody>
          <?php foreach($kurslar as $k): ?>
          <tr>
            <td class="fw-semibold"><?= im_date($k['sana']) ?></td>
            <td class="text-right num fw-bold" style="color:var(--accent-dark)"><?= im_money($k['usd_kurs']) ?> so'm</td>
            <td class="text-muted fs-sm"><?= im_f($k['manba'] ?? '—') ?></td>
            <td>
              <?php if($k['tasdiqlandi']): ?>
              <span class="im-badge im-badge-success">✅ Tasdiqlangan</span>
              <?php else: ?>
              <span class="im-badge im-badge-muted">Kutmoqda</span>
              <?php endif; ?>
            </td>
            <td class="text-muted fs-xs"><?= im_f($k['xodim'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-currency-exchange"></i><h4>Kurs tarixi yo'q</h4></div>
      <?php endif; ?>
    </div>
  </main>
</div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
document.getElementById('btn-kurs-save').addEventListener('click', async () => {
  const sana  = document.getElementById('vk-sana').value;
  const kurs  = parseFloat(document.getElementById('vk-kurs').value) || 0;
  const manba = document.getElementById('vk-manba').value;
  if (!sana || kurs < 100) { NHToast.error('Sana va to\'g\'ri kurs kiriting'); return; }
  const res = await IMAjax.post(window.im_BASE + 'admin/ajax/valyuta-save.php', { sana, kurs, manba });
  if (res.status === 'ok') { NHToast.success(res.msg); setTimeout(() => location.reload(), 700); }
  else NHToast.error(res.msg);
});
</script>
</body></html>
