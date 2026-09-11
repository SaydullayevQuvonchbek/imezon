<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

// Barcha Inkasasiyalar yig'indisi va filiallar bo'yicha filter
$filial_id = (int)($_GET['filial_id'] ?? 0);
$where = "1=1";
if ($filial_id) $where .= " AND i.filial_id='$filial_id'";

$inkasasiyalar = $db->rows("SELECT i.*, x.ism AS xodim_ism, f.nomi AS filial_nomi FROM im_inkasasiya i 
    LEFT JOIN im_xodimlar x ON x.id = i.xodim_id
    LEFT JOIN im_filiallar f ON f.id = i.filial_id
    WHERE $where
    ORDER BY CASE WHEN i.holat = 'kutilmoqda' THEN 0 ELSE 1 END, i.sana DESC");

$filiallar = $db->rows("SELECT * FROM im_filiallar WHERE status=1");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inkasasiya qabuli | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-safe-fill me-1"></i> Filiallardan qabul (Inkasasiya)</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">
      
   <div class="d-flex align-items-center gap-2 mb-3">
       <select class="im-select" style="max-width:200px" onchange="window.location='?filial_id='+this.value">
           <option value="0">Barcha filiallar</option>
           <?php foreach($filiallar as $f): ?>
           <option value="<?= $f['id'] ?>" <?= $filial_id==$f['id'] ? 'selected' : '' ?>><?= im_f($f['nomi']) ?></option>
           <?php endforeach; ?>
       </select>
   </div>

    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-list"></i>
        <span class="im-card-title">Pul qabul qilish arizalari</span>
      </div>
      <?php if ($inkasasiyalar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr><th>Sana</th><th>Filial</th><th>Kassir</th><th>Tur</th><th class="text-right">Summa</th><th>Izoh</th><th>Holat</th><th style="text-align:right">Amal</th></tr>
          </thead>
          <tbody>
            <?php foreach ($inkasasiyalar as $i): ?>
            <tr>
              <td><?= im_datetime($i['sana']) ?></td>
              <td><b><?= im_f($i['filial_nomi'] ?: 'Asosiy do\'kon') ?></b></td>
              <td><?= im_f($i['xodim_ism']) ?></td>
              <td><span class="im-badge im-badge-muted"><?= im_f($i['tolov_turi']) ?></span></td>
              <td class="text-right fw-bold num" style="color:var(--danger)">
                 <?= $i['tolov_turi'] === 'usd' ? '$'.number_format($i['summa'],2) : im_money($i['summa'])." so'm" ?>
              </td>
              <td><?= im_f($i['izoh'] ? : '—') ?></td>
              <td>
                <?php if ($i['holat'] === 'kutilmoqda'): ?>
                   <span class="im-badge im-badge-warning text-dark"><i class="bi bi-hourglass-split"></i> Kutilmoqda</span>
                <?php elseif ($i['holat'] === 'qabul_qilindi'): ?>
                   <span class="im-badge im-badge-success"><i class="bi bi-check-circle"></i> Tasdiqlangan</span>
                <?php else: ?>
                   <span class="im-badge im-badge-danger"><i class="bi bi-x-circle"></i> Bekor qilingan</span>
                <?php endif; ?>
              </td>
              <td class="text-right">
                  <?php if ($i['holat'] === 'kutilmoqda'): ?>
                     <button class="im-btn im-btn-sm im-btn-success" onclick="actionInkasasiya(<?= $i['id'] ?>, 'accept')" title="Tasdiqlash"><i class="bi bi-check-lg"></i></button>
                     <button class="im-btn im-btn-sm im-btn-danger" onclick="actionInkasasiya(<?= $i['id'] ?>, 'reject')" title="Bekor qilish va pulni qaytarish"><i class="bi bi-x-lg"></i></button>
                  <?php else: ?>
                     <span>—</span>
                  <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-wallet2"></i>
        <h4>Ro'yxat bo'sh</h4>
        <p class="text-muted">Arizalar mavjud emas</p>
      </div>
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
async function actionInkasasiya(id, action) {
    let msg = action === 'accept' ? 'Pulni qabul qilganingizni tasdiqlaysizmi?' : "Arizani bekor qilib, pulni kassir kassasiga qaytarasizmi?";
    const ok = await NHConfirm.ask(msg);
    if (!ok) return;
    
    const res = await IMAjax.post(window.im_BASE + 'admin/ajax/inkasasiya-action.php', { id, action });
    if (res.status === 'ok') {
        NHToast.success(res.msg);
        setTimeout(() => location.reload(), 600);
    } else {
        NHToast.error(res.msg);
    }
}
</script>
</body>
</html>
