<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','sklad']);
$db = new Cyber();

$filiallar = $db->rows("SELECT id, nomi, rang FROM im_filiallar WHERE status=1 ORDER BY tartib");

// Tanlangan filial (GET yoki default 1)
$sel_filial = (int)($_GET['filial'] ?? ($filiallar[0]['id'] ?? 1));

$mahsulotlar = $db->rows(
    "SELECT m.id, m.nomi, m.barcode, k.nomi AS kat,
            COALESCE(SUM(pi.remaining_qty),0) AS sklad_q,
            COALESCE(fq.soni, 0) AS filial_q
     FROM im_mahsulotlar m
     LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
     LEFT JOIN im_fifo_layers pi ON pi.mahsulot_id=m.id AND pi.location_id=0 AND pi.cancelled=0
     LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id=m.id AND fq.filial_id=$sel_filial
     WHERE m.status=1
     GROUP BY m.id HAVING sklad_q>0
     ORDER BY m.nomi ASC"
);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Filiallarga Jo'natish | IMezon Sklad</title>
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
      <div class="im-page-title"><i class="bi bi-send-fill me-1"></i> Filiallarga Jo'natish</div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
      </div>
    </header>
    <main class="im-content">
      <div class="im-breadcrumb">
        <a href="<?= im_BASE ?>sklad/index.php"><i class="bi bi-house-fill"></i></a>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Filiallarga Jo'natish</span>
      </div>

      <!-- Filial tanlash tab-lar -->
      <div class="mb-4 d-flex gap-2 flex-wrap">
        <?php foreach ($filiallar as $fl): ?>
        <a href="?filial=<?= $fl['id'] ?>"
           class="im-btn im-btn-sm <?= $fl['id']==$sel_filial ? 'im-btn-primary' : 'im-btn-outline' ?>"
           style="<?= $fl['id']==$sel_filial ? "background:{$fl['rang']};border-color:{$fl['rang']}" : "border-color:{$fl['rang']};color:{$fl['rang']}" ?>">
          <i class="bi bi-building"></i> <?= im_f($fl['nomi']) ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Tanlangan filial info -->
      <?php $cur_f = array_values(array_filter($filiallar, fn($f)=>$f['id']==$sel_filial))[0] ?? null; ?>
      <?php if ($cur_f): ?>
      <div class="mb-3 p-3 rounded-3" style="background:<?= $cur_f['rang'] ?>18;border:1px solid <?= $cur_f['rang'] ?>44">
        <strong><i class="bi bi-building"></i> Jo'natish manzili:</strong> <?= im_f($cur_f['nomi']) ?>
        — quyidagi mahsulotlarni ushbu filialga jo'natasiz
      </div>
      <?php endif; ?>

      <div class="im-card im-slide-in">
        <div class="im-card-header">
          <i class="bi bi-send-fill" style="color:var(--accent-dark)"></i>
          <span class="im-card-title">Skladdagi mahsulotlar</span>
          <span class="im-badge im-badge-muted"><?= count($mahsulotlar) ?> ta</span>
        </div>
        <?php if ($mahsulotlar): ?>
        <div class="im-table-wrap">
          <table class="im-table">
            <thead>
              <tr>
                <th>Mahsulot</th>
                <th class="text-center">Sklad qoldig'i</th>
                <th class="text-center">Filialda mavjud</th>
                <th class="text-center" style="width:180px">Jo'natish soni</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($mahsulotlar as $m): ?>
              <tr id="row-<?= $m['id'] ?>">
                <td>
                  <div class="fw-semibold"><?= im_f($m['nomi']) ?></div>
                  <div class="text-muted fs-xs"><code><?= im_f($m['barcode']) ?></code> · <?= im_f($m['kat'] ?: '—') ?></div>
                </td>
                <td class="text-center">
                  <span class="im-badge im-badge-warning fw-bold num" id="sklad-<?= $m['id'] ?>"><?= $m['sklad_q'] ?></span>
                </td>
                <td class="text-center">
                  <span class="im-badge im-badge-success num" id="filial-<?= $m['id'] ?>"><?= $m['filial_q'] ?></span>
                </td>
                <td class="text-center">
                  <div class="d-flex align-items-center gap-2 justify-content-center">
                    <!-- step=0.001: kg/litr kabi kasrli miqdorlarni ham kiritish mumkin -->
                    <input type="number" class="im-input num" style="width:90px;text-align:center"
                           id="qty-<?= $m['id'] ?>" min="0.001" step="0.001" max="<?= $m['sklad_q'] ?>"
                           value="<?= min(1,$m['sklad_q']) ?>" placeholder="0">
                    <span class="text-muted fs-xs">/ <?= $m['sklad_q'] ?></span>
                  </div>
                </td>
                <td class="text-right">
                  <button class="im-btn im-btn-primary im-btn-sm"
                          onclick="sendToFilial(<?= $m['id'] ?>,'<?= im_js($m['nomi']) ?>')">
                    <i class="bi bi-send-fill"></i> Jo'nat
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="im-empty">
          <i class="bi bi-inbox"></i>
          <h4>Sladda tovar yo'q</h4>
          <p>Avval yuk qabul qiling</p>
          <a href="<?= im_BASE ?>sklad/qabul.php" class="im-btn im-btn-primary">Yuk qabul</a>
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
const SEL_FILIAL = <?= $sel_filial ?>;

async function sendToFilial(mahId, nomi) {
  // parseFloat — kg/litr kabi kasrli miqdorlar kesilib qolmasligi uchun
  const soni = parseFloat(document.getElementById('qty-'+mahId)?.value) || 0;
  if (soni <= 0) { NHToast.error("Sonini kiriting"); return; }
  if (!SEL_FILIAL) { NHToast.error("Filial tanlanmagan"); return; }

  const filialNomi = document.querySelector('.im-btn-primary .bi-building')?.closest('a')?.textContent?.trim() || 'filial';
  const ok = await NHConfirm.ask(`«${nomi}» dan ${soni} jo'natilsinmi?`);
  if (!ok) return;

  const res = await IMAjax.post(window.im_BASE + 'sklad/ajax/send-save.php', {
    mahsulot_id: mahId, soni, filial_id: SEL_FILIAL
  });

  if (res.status === 'ok') {
    NHToast.success(res.msg);
    const sEl = document.getElementById('sklad-'+mahId);
    const fEl = document.getElementById('filial-'+mahId);
    if (res.data) {
      if (sEl) sEl.textContent = res.data.sklad_q;
      if (fEl) fEl.textContent = res.data.filial_q;
      const inp = document.getElementById('qty-'+mahId);
      if (inp) inp.max = res.data.sklad_q;
      if (res.data.sklad_q <= 0) {
        const row = document.getElementById('row-'+mahId);
        if (row) { row.style.opacity=0; setTimeout(()=>row.remove(),400); }
      }
    }
  } else {
    NHToast.error(res.msg);
  }
}
</script>
</body>
</html>
