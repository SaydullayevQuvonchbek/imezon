<?php
// ============================================================
//  IMezon — Do'kon Vitrinasi (Narxlarni tahrirlash)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$filial_id = $im_filial_id ?: 1;

// Qidiruv
$q = trim($_GET['q'] ?? '');
$where = "fq.filial_id = '$filial_id'";

if ($q !== '') {
    $q_db = mysqli_real_escape_string($link, $q);
    $where .= " AND (m.nomi LIKE '%$q_db%' OR m.barcode = '$q_db')";
}

$mahsulotlar = $db->rows(
    "SELECT fq.*, m.nomi, m.barcode, m.birlik, k.nomi AS kat_nomi 
     FROM im_filial_qoldiq fq 
     JOIN im_mahsulotlar m ON fq.mahsulot_id = m.id 
     LEFT JOIN im_kategoriyalar k ON m.kategoriya_id = k.id 
     WHERE $where 
     ORDER BY m.nomi ASC"
);

?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vitrina | IMezon Do'kon</title>
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
    <div class="im-page-title"><i class="bi bi-shop-window me-1"></i> Do'kon Vitrinasi</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">
    
    <div class="im-card mb-4 mt-2">
      <div class="im-card-body p-3">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" class="im-input" placeholder="Mahsulot nomi yoki shtrix kodi..." value="<?= im_f($q) ?>">
            <button type="submit" class="im-btn im-btn-primary"><i class="bi bi-search"></i></button>
            <?php if($q !== ''): ?>
            <a href="?" class="im-btn im-btn-outline"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Jadval -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-tags-fill"></i>
        <span class="im-card-title">Vitrinadagi mahsulotlar</span>
        <span class="im-badge im-badge-muted ms-auto"><?= count($mahsulotlar) ?> ta</span>
      </div>
      <?php if ($mahsulotlar): ?>
      <div class="im-table-wrap">
        <table class="im-table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Shtrix kod</th>
              <th>Nomlanishi</th>
              <th>Kategoriya</th>
              <th class="text-right">Vitrinada qolgan</th>
              <th class="text-right">Sotuv narxi</th>
              <th class="text-right">Taxrirlash</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mahsulotlar as $m): ?>
            <tr>
              <td><code>#<?= $m['id'] ?></code></td>
              <td class="fs-xs fw-bold"><?= im_f($m['barcode'] ? : '—') ?></td>
              <td class="fw-semibold px-2"><?= im_f($m['nomi']) ?></td>
              <td class="text-muted fs-xs"><?= im_f($m['kat_nomi'] ? : 'Tasniflanmagan') ?></td>
              <td class="text-right fw-bold" style="color:var(--primary)">
                <?= (float)$m['soni'] + 0 ?> <?= im_f($m['birlik']) ?>
              </td>
              <td class="text-right fw-bold num" style="color:var(--success)">
                <span id="narx-show-<?= $m['id'] ?>"><?= im_money($m['sotuv_narxi']) ?></span> so'm
              </td>
              <td class="text-right">
                <button class="im-btn im-btn-sm im-btn-outline btn-edit-narx"
                        data-id="<?= $m['id'] ?>"
                        data-nomi="<?= im_f($m['nomi']) ?>"
                        data-narx="<?= (float)$m['sotuv_narxi'] ?>">
                  <i class="bi bi-pencil-square"></i> Narx
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-box-seam"></i>
        <h4>Vitrina bo'sh</h4>
        <p class="text-muted">Do'konga hali mahsulotlar qabul qilinmagan yoki qidiruv natija bermadi.</p>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</div>

<!-- Modal -->
<div class="im-overlay" id="narx-modal">
  <div class="im-modal im-modal-sm">
    <div class="im-modal-header">
      <i class="bi bi-cash-coin" style="color:var(--success);font-size:20px"></i>
      <span class="im-modal-title">Sotuv narxini o'zgartirish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="edit-id">
      <div class="fw-semibold mb-3" id="edit-nomi" style="font-size:14px;color:var(--primary)"></div>
      <div class="im-form-group">
        <label class="im-label">Yangi sotuv narxi (so'm)</label>
        <input class="im-input num fw-bold text-success" type="number" id="edit-narx" min="0">
      </div>
      <div class="text-muted mt-2" style="font-size:11px">
         * Ushbu narx faqatgina sizning filialingizdagi (do'kon) xaridorlar sotib olayotganda ustuvor bo'ladi.
      </div>
    </div>
    <div class="im-modal-footer d-flex gap-2">
      <button class="im-btn im-btn-outline flex-fill" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-success flex-fill" id="btn-save-narx"><i class="bi bi-check2-all"></i> Saqlash</button>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
// Event delegation — data-attribute orqali (o' harfi va boshqa maxsus belgilar ham ishLaydi)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-edit-narx');
    if (!btn) return;
    const id   = btn.dataset.id;
    const nomi = btn.dataset.nomi;
    const narx = btn.dataset.narx;
    modalNarx(id, nomi, narx);
});

function modalNarx(id, nomi, narx) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-nomi').textContent = nomi;
    document.getElementById('edit-narx').value = narx;
    NHModal.open('narx-modal');
    setTimeout(() => {
        let inp = document.getElementById('edit-narx');
        inp.select();
    }, 200);
}

document.getElementById('btn-save-narx').addEventListener('click', async () => {
    let id = document.getElementById('edit-id').value;
    let narx = document.getElementById('edit-narx').value;
    
    if (narx === '' || isNaN(narx) || Number(narx) < 0) {
        NHToast.error("To'g'ri narx kiriting");
        return;
    }

    let btn = document.getElementById('btn-save-narx');
    btn.disabled = true;
    
    const res = await IMAjax.post(window.im_BASE + 'dukon/ajax/vitrina-narx.php', { id: id, narx: narx });
    btn.disabled = false;
    
    if (res.status === 'ok') {
        NHToast.success(res.msg);
        document.getElementById('narx-show-' + id).textContent = Number(narx).toLocaleString();
        NHModal.close('narx-modal');
    } else {
        NHToast.error(res.msg);
    }
});
</script>
</body>
</html>
