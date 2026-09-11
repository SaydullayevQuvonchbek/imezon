<?php
// ============================================================
//  IMezon — Dukon: Mijozlar (faqat ism + telefon)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$q = trim($_GET['q'] ?? '');
$where = "m.status=1";
if ($q) {
    $qs = mysqli_real_escape_string($link, $q);
    $where .= " AND (m.ism LIKE '%$qs%' OR m.telefon LIKE '%$qs%')";
}

$mijozlar = $db->rows(
    "SELECT m.id, m.ism, m.telefon, m.manzil,
            COALESCE(m.nasiya_qoldiq,0) AS nasiya_q,
            (SELECT COUNT(*) FROM im_sotuvlar s WHERE s.mijoz_id=m.id) AS sotuvlar
     FROM im_mijozlar m
     WHERE $where ORDER BY m.ism ASC
     LIMIT 200"
);
$jami_mijoz  = (int)$db->val("SELECT COUNT(*) FROM im_mijozlar WHERE status=1");
$jami_nasiya = (float)$db->val("SELECT COALESCE(SUM(nasiya_qoldiq),0) FROM im_mijozlar WHERE status=1");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Mijozlar | IMezon Do'kon</title>
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
    <div class="im-page-title"><i class="bi bi-people-fill me-1"></i> Mijozlar</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-primary im-btn-sm" id="btn-add-mijoz">
        <i class="bi bi-person-plus-fill"></i> Yangi mijoz
      </button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>

  <main class="im-content">

    <!-- Stat kartalar -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--primary)">
          <div class="im-stat-icon" style="background:rgba(26,26,46,.08);color:var(--primary)">
            <i class="bi bi-people-fill"></i>
          </div>
          <div>
            <div class="im-stat-label">Jami mijozlar</div>
            <div class="im-stat-value"><?= $jami_mijoz ?> ta</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.12);color:var(--danger)">
            <i class="bi bi-credit-card-2-back-fill"></i>
          </div>
          <div>
            <div class="im-stat-label">Jami nasiya qoldig'i</div>
            <div class="im-stat-value num" style="color:var(--danger)"><?= im_money($jami_nasiya) ?> so'm</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Qidirish -->
    <div class="im-card mb-3">
      <div class="im-card-body p-3">
        <form method="GET" class="d-flex gap-2 align-items-center">
          <div class="im-search flex-grow-1" style="max-width:400px">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= im_f($q) ?>" placeholder="Ism yoki telefon raqam...">
          </div>
          <button type="submit" class="im-btn im-btn-primary im-btn-sm">
            <i class="bi bi-funnel"></i> Qidirish
          </button>
          <?php if ($q): ?>
          <a href="?" class="im-btn im-btn-outline im-btn-sm">Tozalash</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Jadval -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-people-fill" style="color:var(--primary)"></i>
        <span class="im-card-title">Mijozlar ro'yxati</span>
        <span class="im-badge im-badge-muted ms-1"><?= count($mijozlar) ?> ta</span>
      </div>
      <?php if ($mijozlar): ?>
      <div class="im-table-wrap">
        <table class="im-table" id="mij-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Ism / Familiya</th>
              <th>Telefon</th>
              <th>Manzil</th>
              <th class="text-center">Sotuvlar</th>
              <th class="text-right" style="color:var(--danger)">Nasiya qoldig'i</th>
              <th class="text-right">Amal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mijozlar as $i => $m): ?>
            <tr id="mij-row-<?= $m['id'] ?>">
              <td class="text-muted fs-xs"><?= $i + 1 ?></td>
              <td>
                <div class="fw-semibold"><?= im_f($m['ism']) ?></div>
              </td>
              <td>
                <?php if ($m['telefon']): ?>
                <a href="tel:<?= im_f($m['telefon']) ?>" class="im-chip">
                  <i class="bi bi-telephone-fill"></i> <?= im_f($m['telefon']) ?>
                </a>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-muted fs-xs"><?= $m['manzil'] ? im_f($m['manzil']) : '—' ?></td>
              <td class="text-center">
                <span class="im-badge im-badge-muted"><?= (int)$m['sotuvlar'] ?> ta</span>
              </td>
              <td class="text-right">
                <?php if ((float)$m['nasiya_q'] > 0): ?>
                <span class="im-badge im-badge-danger num"><?= im_money($m['nasiya_q']) ?> so'm</span>
                <?php else: ?>
                <span class="text-muted fs-xs">—</span>
                <?php endif; ?>
              </td>
              <td class="text-right">
                <button class="im-btn im-btn-ghost im-btn-icon"
                        onclick="mijEdit(<?= htmlspecialchars(json_encode([
                            'id'      => $m['id'],
                            'ism'     => $m['ism'],
                            'telefon' => $m['telefon'] ?? '',
                            'manzil'  => $m['manzil'] ?? '',
                        ]), ENT_QUOTES) ?>)"
                        title="Tahrirlash">
                  <i class="bi bi-pencil-fill" style="color:var(--info)"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-people"></i>
        <h4><?= $q ? 'Topilmadi' : "Mijozlar yo'q" ?></h4>
        <p class="text-muted"><?= $q ? '"' . im_f($q) . '" bo\'yicha natija topilmadi' : 'Birinchi mijozni qo\'shing' ?></p>
        <?php if (!$q): ?>
        <button class="im-btn im-btn-primary" id="btn-add-mijoz2">
          <i class="bi bi-person-plus-fill"></i> Mijoz qo'shish
        </button>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>
</div>

<!-- ─── Mijoz qo'shish / tahrirlash modali ─── -->
<div class="im-overlay" id="mij-modal">
  <div class="im-modal" style="max-width:420px;width:96%">
    <div class="im-modal-header" style="border-left:4px solid var(--primary)">
      <i class="bi bi-person-plus-fill" style="color:var(--primary);font-size:20px"></i>
      <span class="im-modal-title" id="mij-modal-title">Yangi mijoz qo'shish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="mij-id" value="">
      <div class="im-form-group mb-3">
        <label class="im-label">Ism / Familiya <span style="color:var(--danger)">*</span></label>
        <input class="im-input" type="text" id="mij-ism" placeholder="Masalan: Karimov Jasur" maxlength="120">
      </div>
      <div class="im-form-group mb-3">
        <label class="im-label">Telefon raqam</label>
        <input class="im-input" type="text" id="mij-tel" placeholder="+998 90 123 45 67" maxlength="30">
      </div>
      <div class="im-form-group">
        <label class="im-label">Manzil (ixtiyoriy)</label>
        <input class="im-input" type="text" id="mij-manzil" placeholder="Shahar, ko'cha..." maxlength="200">
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="mij-save-btn">
        <i class="bi bi-check-circle-fill"></i> Saqlash
      </button>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
// Yangi mijoz
function openAddModal() {
    document.getElementById('mij-modal-title').textContent = "Yangi mijoz qo'shish";
    document.getElementById('mij-id').value = '';
    document.getElementById('mij-ism').value = '';
    document.getElementById('mij-tel').value = '';
    document.getElementById('mij-manzil').value = '';
    NHModal.open('mij-modal');
    setTimeout(() => document.getElementById('mij-ism').focus(), 150);
}

document.getElementById('btn-add-mijoz').addEventListener('click', openAddModal);
document.getElementById('btn-add-mijoz2')?.addEventListener('click', openAddModal);

// Tahrirlash
function mijEdit(m) {
    document.getElementById('mij-modal-title').textContent = 'Mijozni tahrirlash';
    document.getElementById('mij-id').value    = m.id;
    document.getElementById('mij-ism').value   = m.ism || '';
    document.getElementById('mij-tel').value   = m.telefon || '';
    document.getElementById('mij-manzil').value= m.manzil || '';
    NHModal.open('mij-modal');
    setTimeout(() => document.getElementById('mij-ism').focus(), 150);
}

// Saqlash
document.getElementById('mij-save-btn').addEventListener('click', async () => {
    const id     = document.getElementById('mij-id').value;
    const ism    = document.getElementById('mij-ism').value.trim();
    const tel    = document.getElementById('mij-tel').value.trim();
    const manzil = document.getElementById('mij-manzil').value.trim();

    if (!ism) {
        NHToast.error('Ism kiritish shart!');
        document.getElementById('mij-ism').focus();
        return;
    }

    const btn = document.getElementById('mij-save-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="im-spinner"></span>';

    const res = await IMAjax.post(window.im_BASE + 'admin/ajax/mijoz-save.php', {
        id, ism, tel, manzil, toifa: '', izoh: ''
    });

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Saqlash';

    if (res.status === 'ok') {
        NHToast.success(res.msg || 'Saqlandi!');
        NHModal.close('mij-modal');
        setTimeout(() => location.reload(), 700);
    } else {
        NHToast.error(res.msg || 'Xatolik!');
    }
});
</script>
</body>
</html>
