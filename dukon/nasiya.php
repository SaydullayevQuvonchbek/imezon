<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

// Filtr
$holat  = $_GET['holat'] ?? 'aktiv';
$sana_d = $_GET['sana'] ?? '';
$where  = "n.holat=" . ($holat === 'hammasi' ? "n.holat" : "'$holat'");
if ($sana_d) {
    $sd = mysqli_real_escape_string($link, $sana_d);
    $where .= " AND DATE(n.created_at)='$sd'";
}

$nasiyalar = $db->rows(
    "SELECT n.*, m.ism AS mijoz, m.telefon,
            k.ism AS kassir_ism,
            s.chek_nomer
     FROM im_nasiya n
     LEFT JOIN im_mijozlar m ON m.id=n.mijoz_id
     LEFT JOIN im_xodimlar k ON k.id=n.kassir_id
     LEFT JOIN im_sotuvlar s ON s.id=n.sotuv_id
     WHERE $where
     ORDER BY n.created_at DESC"
);

// Statistika
$jami_qarz   = (float)$db->val("SELECT COALESCE(SUM(qarz_summa),0) FROM im_nasiya");
$jami_tolgan = (float)$db->val("SELECT COALESCE(SUM(tolangan),0) FROM im_nasiya");
$jami_qoldi  = (float)$db->val("SELECT COALESCE(SUM(qoldiq),0) FROM im_nasiya WHERE holat='aktiv'");
$muddati_otdi = (int)$db->val("SELECT COUNT(*) FROM im_nasiya WHERE holat='aktiv' AND qaytarish_sana < CURDATE()");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nasiya | IMezon Do'kon</title>
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
    <div class="im-page-title"><i class="bi bi-credit-card-2-back-fill me-1"></i> Nasiya</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">
    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.12);color:var(--danger)"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div><div class="im-stat-label">Jami nasiya</div>
          <div class="im-stat-value num"><?= im_money($jami_qarz) ?> so'm</div></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i class="bi bi-check-circle-fill"></i></div>
          <div><div class="im-stat-label">To'langan</div>
          <div class="im-stat-value num"><?= im_money($jami_tolgan) ?> so'm</div></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="im-stat-card" style="border-left:4px solid <?= $muddati_otdi > 0 ? 'var(--danger)' : 'var(--accent-dark)' ?>">
          <div class="im-stat-icon" style="background:rgba(226,185,111,.15);color:var(--accent-dark)"><i class="bi bi-hourglass-split"></i></div>
          <div><div class="im-stat-label">Qoldi <?= $muddati_otdi > 0 ? "· <span style='color:var(--danger)'>$muddati_otdi ta muddati o'tgan</span>" : '' ?></div>
          <div class="im-stat-value num" style="color:var(--danger)"><?= im_money($jami_qoldi) ?> so'm</div></div>
        </div>
      </div>
    </div>

    <!-- Filtr -->
    <div class="im-card mb-3">
      <div class="im-card-body p-3">
        <form class="d-flex gap-2 flex-wrap align-items-center">
          <div class="d-flex gap-1">
            <?php foreach(['aktiv'=>'Aktiv','yopildi'=>'To\'langan','muddati_otdi'=>'Muddati o\'tgan','hammasi'=>'Hammasi'] as $v=>$t): ?>
            <a href="?holat=<?= $v ?>" class="im-btn im-btn-sm <?= $holat===$v?'im-btn-primary':'im-btn-outline' ?>"><?= $t ?></a>
            <?php endforeach; ?>
          </div>
          <input type="date" name="sana" class="im-input im-input-sm ms-auto" value="<?= im_f($sana_d) ?>"
                 onchange="this.form.submit()" style="max-width:160px">
        </form>
      </div>
    </div>

    <!-- Jadval -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-list-ul"></i>
        <span class="im-card-title">Nasiya ro'yxati</span>
        <span class="im-badge im-badge-muted"><?= count($nasiyalar) ?> ta</span>
      </div>
      <?php if ($nasiyalar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>Mijoz</th>
              <th class="text-right">Qarz</th>
              <th class="text-right">To'landi</th>
              <th class="text-right" style="color:var(--danger)">Qoldi</th>
              <th>Muddat</th>
              <th>Holat</th>
              <th>Kassir</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($nasiyalar as $n): ?>
            <?php
              $qoldi = $n['qoldi'] ?? ($n['qarz_summa'] - $n['tolangan']);
              $muddati_otgan = ($n['holat']==='aktiv' && $n['qaytarish_sana'] < date('Y-m-d'));
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= im_f($n['mijoz']) ?></div>
                <div class="text-muted fs-xs">
                  <?= im_f($n['telefon'] ?: '—') ?>
                  <?php if ($n['chek_nomer']): ?>
                  · <code><?= im_f($n['chek_nomer']) ?></code>
                  <?php endif; ?>
                </div>
              </td>
              <td class="text-right num fw-semibold"><?= im_money($n['qarz_summa']) ?></td>
              <td class="text-right num" style="color:var(--success)"><?= im_money($n['tolangan']) ?></td>
              <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($qoldi) ?></td>
              <td>
                <span class="<?= $muddati_otgan ? 'text-danger fw-semibold' : 'text-muted' ?> fs-sm">
                  <?= $muddati_otgan ? '⚠️ ' : '' ?><?= im_date($n['qaytarish_sana']) ?>
                </span>
              </td>
              <td>
                <?php $badge = ['aktiv'=>'warning','yopildi'=>'success','muddati_otdi'=>'danger'][$n['holat']] ?? 'muted'; ?>
                <span class="im-badge im-badge-<?= $badge ?>"><?= im_f($n['holat']) ?></span>
              </td>
              <td class="text-muted fs-xs"><?= im_f($n['kassir_ism'] ?? '—') ?></td>
              <td class="text-right">
                <?php if ($n['holat'] === 'aktiv'): ?>
                <button class="im-btn im-btn-success im-btn-sm"
                        onclick="tolovModal(<?= $n['id'] ?>,'<?= im_js($n['mijoz']) ?>',<?= $qoldi ?>)">
                  <i class="bi bi-cash-coin"></i> To'lov
                </button>
                <button class="im-btn im-btn-outline im-btn-sm ms-1"
                        onclick="uzaytirModal(<?= $n['id'] ?>,'<?= im_js(im_date($n['qaytarish_sana'])) ?>')">
                  <i class="bi bi-calendar-plus"></i>
                </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-credit-card-2-back"></i>
        <h4>Nasiya yo'q</h4>
        <p class="text-muted">Bu filtr bo'yicha nasiya topilmadi</p>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</div>

<!-- To'lov modali -->
<div class="im-overlay" id="tolov-modal">
  <div class="im-modal">
    <div class="im-modal-header">
      <i class="bi bi-cash-coin" style="color:var(--success);font-size:20px"></i>
      <span class="im-modal-title">Nasiya to'lovi</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="tolov-nasiya-id">
      <div class="im-card mb-3" style="background:var(--bg)">
        <div class="im-card-body p-3">
          <div class="fw-semibold" id="tolov-mijoz-ism"></div>
          <div class="text-muted fs-sm mt-1">Qoldi: <strong style="color:var(--danger)" id="tolov-qoldi-show" class="num"></strong></div>
        </div>
      </div>
      <div class="im-form-group mb-3">
        <label class="im-label">To'lov turi</label>
        <select class="im-select" id="tolov-turi" onchange="onTuriChange()">
          <option value="naqd"><?= im_tt_label('naqd') ?></option>
          <option value="karta"><?= im_tt_label('karta') ?></option>
          <option value="bank"><?= im_tt_label('bank') ?></option>
          <option value="usd">💲 USD (dollar)</option>
        </select>
      </div>
      <div class="im-form-group mb-3" id="tolov-summa-wrap">
        <label class="im-label" id="tolov-summa-label">To'lov summasi (so'm)</label>
        <input class="im-input num" type="number" id="tolov-summa" min="0.01" step="0.01" placeholder="0" oninput="calcUsdEkviv()">
      </div>
      <div class="im-form-group mb-3" id="tolov-kurs-wrap" style="display:none">
        <label class="im-label">USD kursi (so'mda)</label>
        <input class="im-input num" type="number" id="tolov-usd-kurs" min="1" placeholder="masalan: 12700" oninput="calcUsdEkviv()">
        <div class="text-muted fs-xs mt-1" id="usd-ekviv-info"></div>
      </div>
      <div class="im-form-group">
        <label class="im-label">Izoh (ixtiyoriy)</label>
        <input class="im-input" type="text" id="tolov-izoh" placeholder="...">
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-success" id="btn-tolov-save">
        <i class="bi bi-check-circle-fill"></i> Saqlash
      </button>
    </div>
  </div>
</div>

<!-- Muddat uzaytirish modali -->
<div class="im-overlay" id="uzaytir-modal">
  <div class="im-modal">
    <div class="im-modal-header">
      <i class="bi bi-calendar-plus" style="color:var(--accent-dark);font-size:20px"></i>
      <span class="im-modal-title">Muddat uzaytirish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="uzaytir-nasiya-id">
      <div class="im-form-group mb-3">
        <label class="im-label">Yangi qaytarish sanasi</label>
        <input class="im-input" type="date" id="uzaytir-sana"
               min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
      </div>
      <div class="im-form-group">
        <label class="im-label">Sabab (ixtiyoriy)</label>
        <input class="im-input" type="text" id="uzaytir-izoh" placeholder="...">
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="btn-uzaytir-save">
        <i class="bi bi-calendar-check-fill"></i> Uzaytirish
      </button>
    </div>
  </div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
function tolovModal(id, ism, qoldi) {
  document.getElementById('tolov-nasiya-id').value = id;
  document.getElementById('tolov-mijoz-ism').textContent = ism;
  document.getElementById('tolov-qoldi-show').textContent = Number(qoldi).toLocaleString() + ' so\'m';
  document.getElementById('tolov-qoldi-show').dataset.qoldi = qoldi;
  document.getElementById('tolov-summa').value = qoldi;
  document.getElementById('tolov-turi').value = 'naqd';
  onTuriChange();
  NHModal.open('tolov-modal');
  setTimeout(() => document.getElementById('tolov-summa').focus(), 200);
}

function onTuriChange() {
  const turi = document.getElementById('tolov-turi').value;
  const kursWrap = document.getElementById('tolov-kurs-wrap');
  const summaLabel = document.getElementById('tolov-summa-label');
  const qoldi = parseFloat(document.getElementById('tolov-qoldi-show').dataset.qoldi || 0);
  if (turi === 'usd') {
    kursWrap.style.display = '';
    summaLabel.textContent = 'Dollar miqdori ($)';
    document.getElementById('tolov-summa').value = '';
    document.getElementById('tolov-summa').placeholder = '0.00';
    calcUsdEkviv();
  } else {
    kursWrap.style.display = 'none';
    summaLabel.textContent = 'To\'lov summasi (so\'m)';
    document.getElementById('tolov-summa').value = qoldi;
    document.getElementById('tolov-summa').placeholder = '0';
  }
}

function calcUsdEkviv() {
  const usd = parseFloat(document.getElementById('tolov-summa').value) || 0;
  const kurs = parseFloat(document.getElementById('tolov-usd-kurs').value) || 0;
  const info = document.getElementById('usd-ekviv-info');
  if (usd > 0 && kurs > 0) {
    const som = Math.round(usd * kurs);
    info.textContent = '≈ ' + som.toLocaleString('uz-UZ') + ' so\'m';
  } else {
    info.textContent = '';
  }
}

document.getElementById('btn-tolov-save').addEventListener('click', async () => {
  const id    = document.getElementById('tolov-nasiya-id').value;
  const summa = parseFloat(document.getElementById('tolov-summa').value) || 0;
  const turi  = document.getElementById('tolov-turi').value;
  const izoh  = document.getElementById('tolov-izoh').value;
  const usd_kurs = parseFloat(document.getElementById('tolov-usd-kurs').value) || 0;

  if (summa <= 0) { NHToast.error('Summa kiriting'); return; }
  if (turi === 'usd' && usd_kurs <= 0) { NHToast.error('USD kursini kiriting'); return; }

  const payload = { id, summa, turi, izoh };
  if (turi === 'usd') payload.usd_kurs = usd_kurs;

  const res = await IMAjax.post(window.im_BASE + 'dukon/ajax/nasiya-tolov.php', payload);
  if (res.status === 'ok') {
    NHToast.success(res.msg);
    setTimeout(() => location.reload(), 800);
  } else NHToast.error(res.msg);
});

function uzaytirModal(id, eski_sana) {
  document.getElementById('uzaytir-nasiya-id').value = id;
  document.getElementById('uzaytir-sana').value = '';
  NHModal.open('uzaytir-modal');
}

document.getElementById('btn-uzaytir-save').addEventListener('click', async () => {
  const id   = document.getElementById('uzaytir-nasiya-id').value;
  const sana = document.getElementById('uzaytir-sana').value;
  const izoh = document.getElementById('uzaytir-izoh').value;
  if (!sana) { NHToast.error('Yangi sana tanlang'); return; }

  const res = await IMAjax.post(window.im_BASE + 'dukon/ajax/nasiya-uzaytir.php', { id, sana, izoh });
  if (res.status === 'ok') {
    NHToast.success(res.msg);
    setTimeout(() => location.reload(), 800);
  } else NHToast.error(res.msg);
});
</script>
</body>
</html>
