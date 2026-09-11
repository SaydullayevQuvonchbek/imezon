<?php
// ============================================================
//  IMezon — Filial: Postavshik qarzlari (o'z filiali doirasida)
//  Faqat shu filial qabul qilgan (im_partiyalar.qabul_filial_id)
//  partiyalarga bog'langan, hali yopilmagan qarzlar ko'rsatiladi.
//  To'lov shu filialning O'Z kassasidan amalga oshiriladi.
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$filial_id = (int)($im_filial_id ?: 1);

$qarzlar = $db->rows(
    "SELECT pq.*, ps.nomi AS ps_nomi, ps.telefon AS ps_tel,
            p.faktura_nomer, p.sana AS partiya_sana, p.qabul_filial_id,
            (SELECT COUNT(*) FROM im_partiya_items WHERE partiya_id=p.id) AS item_soni
     FROM im_postavshik_qarz pq
     JOIN im_partiyalar p ON p.id = pq.partiya_id
     LEFT JOIN im_postavshiklar ps ON ps.id = pq.postavshik_id
     WHERE pq.status='ochiq' AND p.qabul_filial_id=$filial_id
     ORDER BY pq.muddat ASC, pq.id ASC"
);

$jami_qoldi = array_sum(array_column($qarzlar, 'qoldiq'));
$muddati_otdi = 0;
foreach ($qarzlar as $q) {
    if ($q['muddat'] && $q['muddat'] < date('Y-m-d')) $muddati_otdi++;
}

// Joriy filial kassasi (kassirga qancha pul borligini eslatish uchun)
$kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=$filial_id LIMIT 1")
    ?? ['naqd_balans'=>0,'karta_balans'=>0,'bank_balans'=>0,'usd_balans'=>0];
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Postavshik qarzlari | IMezon Do'kon</title>
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
    <div class="im-page-title"><i class="bi bi-truck me-1"></i> Postavshik qarzlari</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">
    <div class="im-card mb-3" style="background:var(--bg)">
      <div class="im-card-body p-3">
        <div class="text-muted fs-xs mb-1">Bu ekrandagi qarzlar faqat SIZNING filialingiz qabul qilgan
          yetkazib berishlarga tegishli va to'lov shu filialning o'z kassasidan yechiladi.</div>
      </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="im-stat-card" style="border-left:4px solid <?= $muddati_otdi>0?'var(--danger)':'var(--warning)' ?>">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.12);color:var(--danger)"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div><div class="im-stat-label">Jami qarz qoldig'i <?= $muddati_otdi>0 ? "· <span style='color:var(--danger)'>$muddati_otdi ta muddati o'tgan</span>" : '' ?></div>
          <div class="im-stat-value num" style="color:var(--warning)"><?= im_money($jami_qoldi) ?> so'm</div></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i class="bi bi-cash-coin"></i></div>
          <div><div class="im-stat-label">Filial kassa — Naqd</div>
          <div class="im-stat-value num"><?= im_money($kassa['naqd_balans']) ?> so'm</div></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--accent-dark)">
          <div class="im-stat-icon" style="background:rgba(226,185,111,.15);color:var(--accent-dark)"><i class="bi bi-credit-card-fill"></i></div>
          <div><div class="im-stat-label">Filial kassa — <?= im_tt_nomi('karta', true) ?></div>
          <div class="im-stat-value num"><?= im_money($kassa['karta_balans']) ?> so'm</div></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--muted)">
          <div class="im-stat-icon" style="background:rgba(108,117,125,.12);color:var(--muted)"><i class="bi bi-bank"></i></div>
          <div><div class="im-stat-label">Filial kassa — <?= im_tt_nomi('bank', true) ?></div>
          <div class="im-stat-value num"><?= im_money($kassa['bank_balans']) ?> so'm</div></div>
        </div>
      </div>
    </div>

    <!-- Jadval -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-list-ul"></i>
        <span class="im-card-title">Ochiq qarzlar (mening filialim)</span>
        <span class="im-badge im-badge-warning"><?= count($qarzlar) ?> ta</span>
      </div>
      <?php if ($qarzlar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>Kontragent</th>
              <th>Partiya</th>
              <th class="text-right">Qarz summasi</th>
              <th class="text-right">To'landi</th>
              <th class="text-right">Qoldi</th>
              <th>Muddat</th>
              <th>Holat</th>
              <th style="width:110px">Amal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($qarzlar as $q):
              $qoldi_r = (float)$q['qoldiq'];
              $muddati_otgan = $q['muddat'] && $q['muddat'] < date('Y-m-d');
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= im_f($q['ps_nomi'] ?: 'Noma\'lum') ?></div>
                <div class="text-muted fs-xs"><?= im_f($q['ps_tel'] ?: '') ?></div>
              </td>
              <td class="fs-xs">
                <div>Partiya #<?= (int)$q['partiya_id'] ?></div>
                <?php if ($q['faktura_nomer']): ?>
                <div class="text-muted"><?= im_f($q['faktura_nomer']) ?></div>
                <?php endif; ?>
                <div class="text-muted"><?= im_date($q['partiya_sana'] ?? $q['created_at']) ?> · <?= (int)$q['item_soni'] ?> tur</div>
              </td>
              <td class="text-right num"><?= im_money($q['qarz_summa']) ?> so'm</td>
              <td class="text-right num" style="color:var(--success)"><?= im_money($q['tolandi']) ?> so'm</td>
              <td class="text-right num fw-bold" style="color:var(--warning)"><?= im_money($qoldi_r) ?> so'm</td>
              <td class="<?= $muddati_otgan?'text-danger fw-semibold':'' ?> fs-sm">
                <?= $muddati_otgan ? '⚠️ ' : '' ?><?= $q['muddat'] ? im_date($q['muddat']) : '—' ?>
              </td>
              <td>
                <span class="im-badge im-badge-<?= $muddati_otgan?'danger':'warning' ?>">
                  <?= $muddati_otgan ? "Muddati o'tgan" : 'Aktiv qarz' ?>
                </span>
              </td>
              <td class="text-right" style="white-space:nowrap">
                <button class="im-btn im-btn-sm im-btn-primary"
                  onclick="openTolovModal(<?= $q['id'] ?>,
                    '<?= im_js($q['ps_nomi'] ?: 'Noma\'lum') ?>',
                    '<?= im_js($q['faktura_nomer'] ?: "Partiya #{$q['partiya_id']}") ?>',
                    <?= $qoldi_r ?>)"
                  title="Filial kassasidan to'lash">
                  <i class="bi bi-credit-card-fill"></i> To'lash
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-check-circle"></i>
        <h4>Ochiq qarz yo'q</h4>
        <p class="text-muted">Sizning filialingizda hozircha yopilmagan postavshik qarzi yo'q</p>
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
      <span class="im-modal-title">Filial kassasidan to'lov</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="tolov-qarz-id">
      <div class="im-card mb-3" style="background:var(--bg)">
        <div class="im-card-body p-3">
          <div class="fw-semibold" id="tolov-ps-ism"></div>
          <div class="text-muted fs-sm" id="tolov-faktura"></div>
          <div class="text-muted fs-sm mt-1">Qoldi: <strong style="color:var(--warning)" id="tolov-qoldi-show" class="num"></strong></div>
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
        <i class="bi bi-check-circle-fill"></i> To'lash
      </button>
    </div>
  </div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
function openTolovModal(id, ism, faktura, qoldi) {
  document.getElementById('tolov-qarz-id').value = id;
  document.getElementById('tolov-ps-ism').textContent = ism;
  document.getElementById('tolov-faktura').textContent = faktura;
  document.getElementById('tolov-qoldi-show').textContent = Number(qoldi).toLocaleString('uz-UZ') + ' so\'m';
  document.getElementById('tolov-qoldi-show').dataset.qoldi = qoldi;
  document.getElementById('tolov-summa').value = qoldi;
  document.getElementById('tolov-turi').value = 'naqd';
  document.getElementById('tolov-izoh').value = '';
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
  const btn = document.getElementById('btn-tolov-save');
  const qarz_id  = document.getElementById('tolov-qarz-id').value;
  const summa    = parseFloat(document.getElementById('tolov-summa').value) || 0;
  const tolov_turi = document.getElementById('tolov-turi').value;
  const izoh     = document.getElementById('tolov-izoh').value;
  const usd_kurs = parseFloat(document.getElementById('tolov-usd-kurs').value) || 0;

  if (summa <= 0) { NHToast.error('Summa kiriting'); return; }
  if (tolov_turi === 'usd' && usd_kurs <= 0) { NHToast.error('USD kursini kiriting'); return; }

  const payload = { qarz_id, tolov_turi, izoh };
  if (tolov_turi === 'usd') { payload.usd_summa = summa; payload.usd_kurs = usd_kurs; }
  else { payload.summa = summa; }

  btn.disabled = true;
  const res = await IMAjax.post(window.im_BASE + 'dukon/ajax/qarz-tolov.php', payload);
  btn.disabled = false;
  if (res.status === 'ok') {
    NHToast.success(res.msg);
    setTimeout(() => location.reload(), 800);
  } else NHToast.error(res.msg);
});
</script>
</body>
</html>
