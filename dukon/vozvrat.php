<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

// Smena tekshirish (kerakli emas, lekin kassir bo'lishi mumkin)
$sana_d = date('Y-m-d');

// Vozvratlar
$vozvratlar = $db->rows(
    "SELECT v.*, m.nomi AS mah_nomi, m.barcode,
            mij.ism AS mijoz_ism, x.ism AS kassir_ism,
            s.chek_nomer
     FROM im_vozvratlar v
     LEFT JOIN im_mahsulotlar m ON m.id=v.mahsulot_id
     LEFT JOIN im_sotuvlar s ON s.id=v.sotuv_id
     LEFT JOIN im_mijozlar mij ON mij.id=s.mijoz_id
     LEFT JOIN im_xodimlar x ON x.id=v.kassir_id
     WHERE s.filial_id=" . (int)$im_filial_id . "
     ORDER BY v.sana DESC LIMIT 50"
);

$jami_vozvrat = (float)$db->val("SELECT COALESCE(SUM(qaytarish_summa),0) FROM im_vozvratlar v JOIN im_sotuvlar s ON s.id=v.sotuv_id WHERE s.filial_id=" . (int)$im_filial_id);
$bugun_vozvrat= (float)$db->val("SELECT COALESCE(SUM(qaytarish_summa),0) FROM im_vozvratlar v JOIN im_sotuvlar s ON s.id=v.sotuv_id WHERE DATE(v.sana)=CURDATE() AND s.filial_id=" . (int)$im_filial_id);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Qaytarish | IMezon Do'kon</title>
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
    <div class="im-page-title"><i class="bi bi-arrow-return-left me-1"></i> Qaytarish (Vozvrat)</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-primary im-btn-sm" id="btn-vozvrat-add">
        <i class="bi bi-plus-lg"></i> Yangi qaytarish
      </button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">
    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="im-stat-card" style="border-left:4px solid var(--warning)">
          <div class="im-stat-icon" style="background:rgba(255,193,7,.12);color:var(--warning)"><i class="bi bi-arrow-return-left"></i></div>
          <div><div class="im-stat-label">Bugungi vozvrat</div>
          <div class="im-stat-value num"><?= im_money($bugun_vozvrat) ?> so'm</div></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.12);color:var(--danger)"><i class="bi bi-graph-down-arrow"></i></div>
          <div><div class="im-stat-label">Jami vozvrat</div>
          <div class="im-stat-value num"><?= im_money($jami_vozvrat) ?> so'm</div></div>
        </div>
      </div>
    </div>

    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-list-ul"></i>
        <span class="im-card-title">Vozvratlar tarixi</span>
        <span class="im-badge im-badge-muted"><?= count($vozvratlar) ?> ta</span>
      </div>
      <?php if ($vozvratlar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr><th>Mahsulot</th><th>Chek</th><th>Sabab</th><th class="text-center">Soni</th><th class="text-right">Summa</th><th>To'lov</th><th>Vaqt</th></tr>
          </thead>
          <tbody>
            <?php foreach ($vozvratlar as $v): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= im_f($v['mah_nomi']) ?></div>
                <div class="text-muted fs-xs"><?= im_f($v['kassir_ism'] ?? '—') ?></div>
              </td>
              <td><code class="fs-xs"><?= im_f($v['chek_nomer'] ?: '—') ?></code></td>
              <td class="text-muted fs-sm"><?= im_f($v['sabab'] ?: '—') ?></td>
              <td class="text-center"><span class="im-badge im-badge-warning"><?= $v['soni'] ?> dona</span></td>
              <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($v['qaytarish_summa']) ?> so'm</td>
              <td><span class="im-badge im-badge-muted"><?= im_f($v['qaytarildi_tur']) ?></span></td>
              <td class="text-muted fs-xs"><?= im_datetime($v['sana']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-arrow-return-left"></i>
        <h4>Vozvrat yo'q</h4>
        <p class="text-muted">Hali qaytarilgan mahsulot yo'q</p>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</div>

<!-- Qaytarish modali -->
<div class="im-overlay" id="vozvrat-modal">
  <div class="im-modal">
    <div class="im-modal-header">
      <i class="bi bi-arrow-return-left" style="color:var(--warning);font-size:20px"></i>
      <span class="im-modal-title">Mahsulot qaytarish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <div class="im-form-group mb-3">
        <label class="im-label">Chek raqami yoki mahsulot barcode</label>
        <div class="im-input-group">
          <input class="im-input" type="text" id="v-chek" placeholder="NHC-20260402-0001 yoki barcode...">
          <button class="im-btn im-btn-dark" id="btn-v-qidir"><i class="bi bi-search"></i> Qidir</button>
        </div>
      </div>
      <div id="v-natija" style="display:none">
        <!-- Mahsulot tanlash -->
        <div class="im-form-group mb-3">
          <label class="im-label">Mahsulot</label>
          <select class="im-select" id="v-mah-id"></select>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="im-label">Qaytarish soni
            <span id="v-soni-hint" class="text-muted fs-xs"></span>
          </label>
          <input class="im-input num" type="number" id="v-soni" min="0.001" step="0.001" value="1">
          </div>
          <div class="col-6">
            <label class="im-label">Qaytarish turi</label>
            <select class="im-select" id="v-tolov-turi">
              <option value="naqd"><?= im_tt_label('naqd') ?></option>
              <option value="karta"><?= im_tt_label('karta') ?></option>
            </select>
          </div>
        </div>
        <div class="im-form-group mb-3">
          <label class="im-label">Qaytarish summasi (so'm)</label>
          <input class="im-input num" type="number" id="v-summa" min="0.01" step="0.01">
        </div>
        <div class="im-form-group">
          <label class="im-label">Sabab</label>
          <input class="im-input" type="text" id="v-sabab" placeholder="Nima sababdan...">
        </div>
        <input type="hidden" id="v-sotuv-id">
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-warning" id="btn-vozvrat-save" style="display:none">
        <i class="bi bi-check-circle-fill"></i> Qaytarishni saqlash
      </button>
    </div>
  </div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
document.getElementById('btn-vozvrat-add').addEventListener('click', () => {
  document.getElementById('v-chek').value = '';
  document.getElementById('v-natija').style.display = 'none';
  document.getElementById('btn-vozvrat-save').style.display = 'none';
  NHModal.open('vozvrat-modal');
  setTimeout(() => document.getElementById('v-chek').focus(), 200);
});

document.getElementById('btn-v-qidir').addEventListener('click', async () => {
  const q = document.getElementById('v-chek').value.trim();
  if (!q) { NHToast.error('Chek raqam yoki barcode kiriting'); return; }
  document.getElementById('v-natija').style.display = 'none';
  document.getElementById('btn-vozvrat-save').style.display = 'none';
  const res = await IMAjax.get(window.im_BASE + 'dukon/ajax/vozvrat-search.php', { q });
  if (res.status !== 'ok') { NHToast.error(res.msg); return; }
  const items = res.data.items;
  if (!items || !items.length) { NHToast.error('Topilmadi'); return; }

  const sel = document.getElementById('v-mah-id');
  sel.innerHTML = items.map(it => `<option value="${it.sotuv_item_id}" ${it.eligible ? '' : 'disabled'}
      data-mahsulot-id="${it.mahsulot_id}" data-sotuv-id="${it.sotuv_id}" data-classification="${it.classification}"
      data-narx="${it.narx}"
      data-soni="${it.soni}"
      data-sotilgan="${it.sotilgan}"
      data-qaytarilgan="${it.qaytarilgan}">
      ${im_esc(it.chek)} / qator #${it.sotuv_item_id} / ${it.set_id ? 'Set #' + it.set_id : 'A la carte'} — ${im_esc(it.nomi)} — maks. ${it.soni}${it.classification === 'waste' ? ' (chiqindi: zaxira tiklanmaydi)' : ''}${it.eligible ? '' : ' (eski yozuvni tekshirish kerak)'}
    </option>`).join('');
  document.getElementById('v-sotuv-id').value = res.data.sotuv_id;
  sel.onchange = updateSumma;
  updateSumma();
  document.getElementById('v-natija').style.display = 'block';
  document.getElementById('btn-vozvrat-save').style.display = items.some(it => it.eligible) ? 'inline-flex' : 'none';
});

function updateSumma() {
  const opt = document.getElementById('v-mah-id').selectedOptions[0];
  const narx       = parseFloat(opt?.dataset.narx || 0);
  const maxSoni    = parseFloat(opt?.dataset.soni || 1);
  const sotilgan   = parseFloat(opt?.dataset.sotilgan || 0);
  const qaytarilgan= parseFloat(opt?.dataset.qaytarilgan || 0);

  const soniInput = document.getElementById('v-soni');
  soniInput.max = maxSoni;
  if (parseFloat(soniInput.value) > maxSoni) soniInput.value = maxSoni;

  const hint = document.getElementById('v-soni-hint');
  if (hint) {
    hint.textContent = qaytarilgan > 0
      ? `(sotilgan: ${sotilgan}, avval qaytarilgan: ${qaytarilgan}, maks: ${maxSoni})`
      : `(maks: ${maxSoni} ta)`;
  }

  const soni = parseFloat(soniInput.value) || 1;
  document.getElementById('v-summa').value = (narx * soni).toFixed(2);
}
document.getElementById('v-soni')?.addEventListener('input', updateSumma);

document.getElementById('btn-vozvrat-save').addEventListener('click', async () => {
  const selected = document.getElementById('v-mah-id').selectedOptions[0];
  const sotuv_id = selected?.dataset.sotuvId;
  const sotuv_item_id = selected?.value;
  const mah_id = selected?.dataset.mahsulotId;
  const soni     = parseFloat(document.getElementById('v-soni').value) || 0;
  const summa    = parseFloat(document.getElementById('v-summa').value) || 0;
  const tolov    = document.getElementById('v-tolov-turi').value;
  const sabab    = document.getElementById('v-sabab').value;

  if (!mah_id || soni <= 0 || summa <= 0) { NHToast.error('Ma\'lumotlarni to\'ldiring'); return; }

  // Frontendda ham tekshiruv
  const opt = document.getElementById('v-mah-id').selectedOptions[0];
  const maxSoni = parseFloat(opt?.dataset.soni || 0);
  if (soni > maxSoni) {
    NHToast.error(`Maksimum ${maxSoni} ta qaytarish mumkin!`);
    return;
  }

  const button = document.getElementById('btn-vozvrat-save');
  if (button.disabled) return;
  button.disabled = true;
  try {
  const res = await IMAjax.post(window.im_BASE + 'dukon/ajax/vozvrat-save.php', {
    sotuv_id, sotuv_item_id, mahsulot_id: mah_id, soni, qaytarish_summa: summa, qaytarildi_tur: tolov, sabab
  });
  if (res.status === 'ok') {
    NHToast.success(res.msg);
    setTimeout(() => location.reload(), 800);
  } else { NHToast.error(res.msg); button.disabled = false; }
  } catch (error) {
    NHToast.error('Natija noma’lum. Chekni qayta qidirib, tarixni tekshiring.');
    document.getElementById('v-natija').style.display = 'none';
    button.style.display = 'none';
    button.disabled = false;
  }
});
</script>
</body>
</html>
