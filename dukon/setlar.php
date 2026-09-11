<?php
// ============================================================
//  IMezon — Dukon: Setlar boshqaruvi
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'kassir']);
$db = new Cyber();

$filial_id = $im_filial_id ?: 1;

// Filiallar (admin boshqasini ham ko'rsin)
$filiallar = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY tartib ASC");

// Agar admin boshqa filialni ko'rmoqchi bo'lsa
$fil_filter = $im_rol === 'admin' ? (int)($_GET['filial'] ?? $filial_id) : $filial_id;

// Setlar ro'yxati
$setlar = $db->rows(
    "SELECT s.*,
            f.nomi AS filial_nomi,
            (SELECT COUNT(*) FROM im_set_items WHERE set_id=s.id) AS item_soni
     FROM im_setlar s
     LEFT JOIN im_filiallar f ON f.id=s.filial_id
     WHERE s.filial_id=$fil_filter
     ORDER BY s.aktiv DESC, s.tartib ASC, s.nomi ASC"
);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setlar | IMezon Do'kon</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
<style>
.set-color-dot {
  width: 14px; height: 14px; border-radius: 50%;
  display: inline-block; border: 1.5px solid rgba(0,0,0,.12);
  flex-shrink: 0;
}
.item-row {
  display: flex; align-items: center; gap: 8px;
  padding: 7px 0; border-bottom: 1px dashed var(--border);
}
.item-row:last-child { border-bottom: none; }
.item-row .item-num {
  width: 20px; height: 20px; border-radius: 50%;
  background: var(--accent); color: var(--primary);
  font-size: 10px; font-weight: 800;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
/* Mahsulot qidiruv dropdown */
.mah-search-wrap { position: relative; }
.mah-search-dd {
  position: absolute; top: 100%; left: 0; right: 0; z-index: 500;
  background: var(--card); border: 1.5px solid var(--accent);
  border-top: none; border-radius: 0 0 8px 8px;
  max-height: 200px; overflow-y: auto; box-shadow: var(--shadow);
  display: none;
}
.mah-dd-item {
  padding: 8px 12px; cursor: pointer; font-size: 12.5px;
  border-bottom: 1px solid var(--border-light); transition: background .12s;
}
.mah-dd-item:hover { background: var(--bg); }
/* Set preview karta */
.set-preview-badge {
  display: inline-flex; align-items: center; gap: 4px;
  background: var(--bg); border: 1px solid var(--border);
  border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: 600;
}
</style>
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title"><i class="bi bi-gift-fill me-1" style="color:var(--accent-dark)"></i> Setlar (Combo)</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-primary" id="btn-add-set">
        <i class="bi bi-plus-circle-fill"></i> Yangi Set
      </button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- Filial filter (faqat admin) -->
    <?php if ($im_rol === 'admin' && count($filiallar) > 1): ?>
    <div class="d-flex gap-2 mb-4 flex-wrap">
      <?php foreach ($filiallar as $f): ?>
      <a href="?filial=<?= $f['id'] ?>"
         class="im-btn im-btn-sm <?= $fil_filter == $f['id'] ? 'im-btn-primary' : 'im-btn-outline' ?>">
        <?= im_f($f['nomi']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Setlar jadvali -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-gift-fill" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Setlar ro'yxati</span>
        <span class="im-badge im-badge-muted"><?= count($setlar) ?> ta</span>
        <a href="<?= im_BASE ?>dukon/pos.php" class="im-btn im-btn-sm im-btn-outline ms-auto">
          <i class="bi bi-bag-fill"></i> POS ga o'tish
        </a>
      </div>
      <?php if ($setlar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nomi</th>
              <th>Tarkib</th>
              <th class="text-right">Taklif narxi</th>
              <th class="text-right">Sotuv narxi</th>
              <th>Holat</th>
              <th class="text-end">Amal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($setlar as $s):
              // Tarkibni olish
              $s_items = $db->rows(
                "SELECT si.soni, m.nomi AS mah_nomi,
                        COALESCE(fq.sotuv_narxi, n.sotish_narxi, 0) AS narxi
                 FROM im_set_items si
                 JOIN im_mahsulotlar m ON m.id=si.mahsulot_id
                 LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
                 LEFT JOIN im_filial_qoldiq fq ON fq.mahsulot_id=m.id AND fq.filial_id=$fil_filter
                 WHERE si.set_id={$s['id']}
                 ORDER BY si.tartib ASC"
              );
              $taklif = array_sum(array_map(fn($i) => $i['narxi'] * $i['soni'], $s_items));
            ?>
            <tr class="<?= !$s['aktiv'] ? 'opacity-50' : '' ?>">
              <td><code class="fs-xs">#<?= $s['id'] ?></code></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="set-color-dot" style="background:<?= im_f($s['rang']) ?>"></span>
                  <span class="fw-semibold"><?= im_f($s['nomi']) ?></span>
                </div>
              </td>
              <td>
                <?php if ($s_items): ?>
                <div class="d-flex flex-wrap gap-1">
                  <?php foreach ($s_items as $si): ?>
                  <span class="set-preview-badge">
                    <?= im_f($si['mah_nomi']) ?>
                    <strong>×<?= (float)$si['soni'] + 0 ?></strong>
                  </span>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <span class="text-muted fs-xs">Mahsulot yo'q</span>
                <?php endif; ?>
              </td>
              <td class="text-right text-muted fs-sm num">
                <?= $taklif > 0 ? im_money($taklif) . ' so\'m' : '—' ?>
              </td>
              <td class="text-right fw-bold num" style="color:var(--accent-dark)">
                <?= im_money($s['narxi']) ?> so'm
              </td>
              <td>
                <?php if ($s['aktiv']): ?>
                <span class="im-badge im-badge-success">Aktiv</span>
                <?php else: ?>
                <span class="im-badge im-badge-muted">O'chirilgan</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="d-flex gap-1 justify-content-end">
                  <?php if ($s['aktiv']): ?>
                  <button class="im-btn im-btn-outline im-btn-sm btn-edit-set"
                          data-id="<?= $s['id'] ?>"
                          data-nomi="<?= im_f($s['nomi']) ?>"
                          data-narxi="<?= (float)$s['narxi'] ?>"
                          data-rang="<?= im_f($s['rang']) ?>"
                          data-filial="<?= (int)$s['filial_id'] ?>"
                          title="Tahrirlash">
                    <i class="bi bi-pencil-fill"></i>
                  </button>
                  <button class="im-btn im-btn-danger im-btn-sm btn-del-set"
                          data-id="<?= $s['id'] ?>"
                          data-nomi="<?= im_f($s['nomi']) ?>"
                          title="O'chirish">
                    <i class="bi bi-trash3-fill"></i>
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-gift"></i>
        <h4>Set yo'q</h4>
        <p class="text-muted">Bu filial uchun hali set yaratilmagan</p>
        <button class="im-btn im-btn-primary" id="btn-add-set-empty">
          <i class="bi bi-plus-circle-fill"></i> Birinchi Setni Yaratish
        </button>
      </div>
      <?php endif; ?>
    </div>

    <!-- Ma'lumot -->
    <div class="im-card mt-3" style="border-left:4px solid var(--info)">
      <div class="im-card-body p-3">
        <div class="text-muted fs-sm">
          <i class="bi bi-info-circle" style="color:var(--info)"></i>
          <strong>Set qanday ishlaydi:</strong> POS da kassir "🎁 Setlar" tabini tanlaydi → Set kartochkasini bosadi →
          setdagi barcha mahsulotlar savatga qo'shiladi, jami summa Set sotuv narxiga to'g'rilangan holda.
          <br>
          <i class="bi bi-lightbulb" style="color:var(--warning)"></i>
          <strong>Taklif narxi</strong> — setdagi mahsulotlarning sotuv narxlari yig'indisi.
          Siz buni o'zgartirib <strong>Sotuv narxi</strong>ni belgilaysiz.
        </div>
      </div>
    </div>

  </main>
</div>
</div>

<!-- ──── Offcanvas: Yangi / Tahrirlash ──────────────────────────── -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="set-offcanvas" style="width:min(500px,100vw)">
  <div class="offcanvas-header" style="background:var(--primary);color:#fff">
    <h5 class="offcanvas-title" id="offcanvas-title">
      <i class="bi bi-gift-fill" style="color:var(--accent)"></i>
      <span id="oc-title-text">Yangi Set</span>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <input type="hidden" id="oc-set-id">
    <input type="hidden" id="oc-filial-id" value="<?= $fil_filter ?>">

    <!-- Nomi -->
    <div class="im-form-group mb-3">
      <label class="im-label">Set nomi *</label>
      <input class="im-input" type="text" id="oc-nomi" placeholder="Masalan: Combo1, Oilaviy set...">
    </div>

    <!-- Rang -->
    <div class="im-form-group mb-3">
      <label class="im-label">Rang (ixtiyoriy)</label>
      <input type="hidden" id="oc-rang" value="#e2b96f">
      <div class="d-flex gap-2 flex-wrap align-items-center">
        <?php foreach (['#e2b96f'=>'Oltin','#10b981'=>'Yashil','#3b82f6'=>'Ko\'k','#ef4444'=>'Qizil','#8b5cf6'=>'Binafsha','#f59e0b'=>'Sariq','#06b6d4'=>'Moviy','#ec4899'=>'Pushti','#64748b'=>'Kulrang','#1a1a2e'=>'To\'q'] as $c => $lbl): ?>
        <button type="button" class="btn-rang-circle"
                data-color="<?= $c ?>"
                title="<?= $lbl ?>"
                style="background:<?= $c ?>"
                onclick="selectRang('<?= im_js($c) ?>', this)"></button>
        <?php endforeach; ?>
      </div>
      <div class="mt-1" id="rang-preview" style="font-size:11px;color:var(--text-muted)">
        Tanlangan: <span id="rang-preview-dot" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#e2b96f;border:1px solid rgba(0,0,0,.2);margin-right:3px"></span>
        <span id="rang-preview-text">#e2b96f</span>
      </div>
    </div>

    <!-- Mahsulot qidirish -->
    <div class="im-form-group mb-2">
      <label class="im-label">Mahsulotlar *</label>
      <div class="mah-search-wrap">
        <input class="im-input" type="text" id="oc-mah-q"
               placeholder="Mahsulot nomi yoki barcode..." autocomplete="off">
        <div class="mah-search-dd" id="oc-mah-dd"></div>
      </div>
    </div>

    <!-- Qo'shilgan mahsulotlar -->
    <div id="oc-items-list" class="mb-3" style="min-height:40px"></div>

    <!-- Narxlar -->
    <div class="im-card mb-3" style="background:var(--bg)">
      <div class="im-card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted fs-sm">💡 Taklif narxi (mahsulotlar yig'indisi):</span>
          <span class="fw-bold num" id="oc-taklif-narx">0 so'm</span>
        </div>
        <hr style="border-color:var(--border);margin:8px 0">
        <label class="im-label">Sotuv narxi (so'm) *</label>
        <input class="im-input num fw-bold" type="number" id="oc-narxi" min="0" step="100"
               placeholder="0" style="font-size:18px;color:var(--accent-dark)">
        <div class="text-muted fs-xs mt-1" id="oc-foyda-info"></div>
      </div>
    </div>

  </div>
  <div class="offcanvas-footer p-3 border-top d-flex gap-2">
    <button class="im-btn im-btn-outline flex-fill" data-bs-dismiss="offcanvas">Bekor</button>
    <button class="im-btn im-btn-primary flex-fill" id="btn-oc-save">
      <i class="bi bi-check-circle-fill"></i> Saqlash
    </button>
  </div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE = "<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js?v=<?= rawurlencode(im_VERSION) ?>"></script>
<script>
const AJAX_MAH   = window.im_BASE + 'dukon/ajax/mah-pos.php';
const AJAX_SAVE  = window.im_BASE + 'dukon/ajax/set-save.php';
const AJAX_DEL   = window.im_BASE + 'dukon/ajax/set-delete.php';

const ocBS = new bootstrap.Offcanvas(document.getElementById('set-offcanvas'));

// Rang tanlash funksiyasi
function selectRang(color, btn) {
  document.getElementById('oc-rang').value = color;
  document.getElementById('rang-preview-dot').style.background = color;
  document.getElementById('rang-preview-text').textContent = color;
  // Active state
  document.querySelectorAll('.btn-rang-circle').forEach(b => b.classList.remove('active'));
  if (btn) {
    btn.classList.add('active');
  } else {
    // rang string orqali topish
    const found = document.querySelector(`.btn-rang-circle[data-color="${color}"]`);
    if (found) found.classList.add('active');
  }
}
// Sahifa yuklanganda birinchi rang (oltin) tanlab qo'yish
document.addEventListener('DOMContentLoaded', () => selectRang('#e2b96f'));

// Offcanvas ochish
function openOC(mode = 'new') {
  document.getElementById('oc-set-id').value   = '';
  document.getElementById('oc-nomi').value      = '';
  document.getElementById('oc-narxi').value     = '';
  document.getElementById('oc-narxi').dataset.userEdited = '';
  selectRang('#e2b96f');
  document.getElementById('oc-items-list').innerHTML = '';
  document.getElementById('oc-taklif-narx').textContent = '0 so\'m';
  document.getElementById('oc-foyda-info').textContent = '';
  ocItems = [];
  document.getElementById('oc-title-text').textContent = mode === 'new' ? 'Yangi Set' : 'Setni tahrirlash';
  ocBS.show();
  setTimeout(() => document.getElementById('oc-nomi').focus(), 300);
}

document.getElementById('btn-add-set')?.addEventListener('click', () => openOC('new'));
document.getElementById('btn-add-set-empty')?.addEventListener('click', () => openOC('new'));

// Tahrirlash tugmasi
document.addEventListener('click', async function(e) {
  const editBtn = e.target.closest('.btn-edit-set');
  if (editBtn) {
    const id     = editBtn.dataset.id;
    const nomi   = editBtn.dataset.nomi;
    const narxi  = editBtn.dataset.narxi;
    const rang   = editBtn.dataset.rang;
    const filial = editBtn.dataset.filial;

    openOC('edit');
    document.getElementById('oc-set-id').value    = id;
    document.getElementById('oc-filial-id').value = filial;
    document.getElementById('oc-nomi').value      = nomi;
    document.getElementById('oc-narxi').value     = narxi;
    document.getElementById('oc-narxi').dataset.userEdited = narxi ? '1' : '';
    selectRang(rang);

    // Set itemlarini yuklaymiz
    try {
      const res = await IMAjax.get(window.im_BASE + 'dukon/ajax/set-list.php');
      const found = res.data?.list?.find(s => s.id == id);
      if (found) {
        ocItems = [];
        found.items.forEach(it => addOCItem({
          id: it.mahsulot_id, nomi: it.nomi, birlik: it.birlik,
          sotuv_narxi: it.sotuv_narxi, tannarx: it.tannarx
        }, it.soni));
      }
    } catch(e) {}
    return;
  }

  // O'chirish
  const delBtn = e.target.closest('.btn-del-set');
  if (delBtn) {
    const id   = delBtn.dataset.id;
    const nomi = delBtn.dataset.nomi;
    const ok = await NHConfirm.show({
      variant: 'danger',
      title: "Setni o'chirish",
      text: `«${nomi}» seti o'chiriladi.`,
      sub: "Set tarkibi va sozlamalari qayta tiklanmaydi.",
      confirmText: "Setni o'chirish"
    });
    if (!ok) return;
    const res = await IMAjax.post(AJAX_DEL, { id });
    if (res.status === 'ok') {
      NHToast.success(res.msg);
      setTimeout(() => location.reload(), 700);
    } else NHToast.error(res.msg);
  }
});

// ──── Mahsulot qidirish ──────────────────────────────
let searchTimer;
const qInput = document.getElementById('oc-mah-q');
const dd = document.getElementById('oc-mah-dd');

qInput?.addEventListener('input', function() {
  clearTimeout(searchTimer);
  const q = this.value.trim();
  if (!q) { dd.style.display = 'none'; return; }
  searchTimer = setTimeout(() => searchMah(q), 280);
});

async function searchMah(q) {
  try {
    const res = await IMAjax.get(AJAX_MAH, { q });
    if (res.status !== 'ok' || !res.data?.list?.length) {
      dd.innerHTML = '<div class="mah-dd-item text-muted">Topilmadi</div>';
      dd.style.display = 'block';
      return;
    }
    dd.innerHTML = res.data.list.map(m =>
      `<div class="mah-dd-item" data-id="${m.id}"
            data-nomi="${m.nomi.replace(/"/g,'&quot;')}"
            data-birlik="${m.birlik||'dona'}"
            data-narx="${m.narx}"
            data-tannarx="${m.tannarx||0}">
        <strong>${im_esc(m.nomi)}</strong>
        <span class="text-muted ms-1 fs-xs">${Number(m.narx).toLocaleString()} so'm · qoldiq: ${m.qoldiq}</span>
      </div>`
    ).join('');
    dd.style.display = 'block';
  } catch(e) {}
}

dd?.addEventListener('click', function(e) {
  const item = e.target.closest('.mah-dd-item');
  if (!item || !item.dataset.id) return;
  addOCItem({
    id: item.dataset.id,
    nomi: item.dataset.nomi,
    birlik: item.dataset.birlik,
    sotuv_narxi: parseFloat(item.dataset.narx) || 0,
    tannarx: parseFloat(item.dataset.tannarx) || 0,
  }, 1);
  qInput.value = '';
  dd.style.display = 'none';
});

document.addEventListener('click', e => {
  if (!e.target.closest('.mah-search-wrap')) dd.style.display = 'none';
});

// ──── Items boshqaruvi ──────────────────────────────
let ocItems = []; // [{id, nomi, birlik, sotuv_narxi, tannarx, soni}]

function addOCItem(mah, soni = 1) {
  const existing = ocItems.findIndex(i => i.id == mah.id);
  if (existing >= 0) {
    ocItems[existing].soni += soni;
    renderOCItems();
    return;
  }
  ocItems.push({
    id: mah.id, nomi: mah.nomi, birlik: mah.birlik || 'dona',
    sotuv_narxi: parseFloat(mah.sotuv_narxi) || 0,
    tannarx: parseFloat(mah.tannarx) || 0,
    soni: parseFloat(soni) || 1,
  });
  renderOCItems();
}

function removeOCItem(idx) {
  ocItems.splice(idx, 1);
  renderOCItems();
}

function renderOCItems() {
  const cont = document.getElementById('oc-items-list');
  if (!ocItems.length) {
    cont.innerHTML = '<div class="text-muted fs-xs py-2 text-center">Mahsulot qo\'shing</div>';
    updateNarxCalc();
    return;
  }
  cont.innerHTML = ocItems.map((it, idx) => `
    <div class="item-row">
      <div class="item-num">${idx+1}</div>
      <div class="flex-grow-1">
        <div class="fw-semibold fs-sm">${im_esc(it.nomi)}</div>
        <div class="text-muted" style="font-size:11px">
          Narx: ${Number(it.sotuv_narxi).toLocaleString()} so'm
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:6px">
        <button type="button" class="qty-btn" onclick="changeQty(${idx},-1)">–</button>
        <input type="number" class="qty-input" style="width:60px"
               value="${it.soni}" min="0.1" step="0.1"
               onchange="ocItems[${idx}].soni=parseFloat(this.value)||1;updateNarxCalc()">
        <button type="button" class="qty-btn" onclick="changeQty(${idx},1)">+</button>
        <span class="text-muted fs-xs">${it.birlik}</span>
        <button type="button" class="qty-btn" onclick="removeOCItem(${idx})"
                style="color:var(--danger);border-color:var(--danger)">
          <i class="bi bi-x"></i>
        </button>
      </div>
    </div>
  `).join('');
  updateNarxCalc();
}

function changeQty(idx, delta) {
  ocItems[idx].soni = Math.max(0.1, (parseFloat(ocItems[idx].soni) || 1) + delta);
  renderOCItems();
}

function updateNarxCalc() {
  const taklif  = ocItems.reduce((s, i) => s + i.sotuv_narxi * i.soni, 0);
  const tannarx = ocItems.reduce((s, i) => s + i.tannarx * i.soni, 0);
  document.getElementById('oc-taklif-narx').textContent = Math.round(taklif).toLocaleString() + ' so\'m';
  // Tannarx faqat ichki validatsiya uchun — UI da ko'rinmaydi
  document.getElementById('oc-narxi').dataset.tannarx = Math.round(tannarx);

  // Sotuv narxini HAR DOIM taklif bilan sinxronlashtirish
  // (foydalanuvchi qo'lda o'zgartirmagan bo'lsa)
  const narxInp = document.getElementById('oc-narxi');
  if (!narxInp.dataset.userEdited) {
    narxInp.value = Math.round(taklif) || '';
  }

  updateFoydaInfo();
}

document.getElementById('oc-narxi')?.addEventListener('input', function() {
  this.dataset.userEdited = this.value ? '1' : '';
  updateFoydaInfo();
});

function updateFoydaInfo() {
  // Foyda info yo'q — kassir narxini erkin belgilaydi
  // Faqat narxi tannarxdan kam bo'lsa ogohlantirish
  const narxi   = parseFloat(document.getElementById('oc-narxi').value) || 0;
  const tannarx = parseFloat(document.getElementById('oc-narxi').dataset.tannarx) || 0;
  const foydaEl = document.getElementById('oc-foyda-info');
  if (narxi > 0 && tannarx > 0 && narxi < tannarx) {
    foydaEl.innerHTML = `<span style="color:var(--danger)"><i class="bi bi-exclamation-triangle-fill"></i> Sotuv narxi tannarxdan kam! (min: ${Math.round(tannarx).toLocaleString()} so'm)</span>`;
  } else {
    foydaEl.textContent = '';
  }
}

// ──── Saqlash ──────────────────────────────────────
document.getElementById('btn-oc-save')?.addEventListener('click', async function() {
  const nomi    = document.getElementById('oc-nomi').value.trim();
  const narxi   = parseFloat(document.getElementById('oc-narxi').value) || 0;
  const rang    = document.getElementById('oc-rang').value;
  const set_id  = document.getElementById('oc-set-id').value;
  const filial  = document.getElementById('oc-filial-id').value;

  if (!nomi)       { NHToast.error('Nom kiriting'); return; }
  if (narxi <= 0)  { NHToast.error('Sotuv narxi kiriting'); return; }
  if (!ocItems.length) { NHToast.error('Kamida 1 ta mahsulot qo\'shing'); return; }

  // Tannarx validatsiyasi — sotuv narxi tannarxdan kam bo'lmasin
  const tannarxSum = parseFloat(document.getElementById('oc-narxi').dataset.tannarx) || 0;
  if (tannarxSum > 0 && narxi < tannarxSum) {
    NHToast.error(`❌ Sotuv narxi tannarxdan kam bo'lishi mumkin emas! (Minimal: ${Math.round(tannarxSum).toLocaleString()} so'm)`);
    document.getElementById('oc-narxi').focus();
    return;
  }

  const items = ocItems.map(i => ({ mahsulot_id: i.id, soni: i.soni }));
  const payload = { nomi, narxi, rang, items, filial_id: filial };
  if (set_id) payload.id = set_id;

  this.disabled = true;
  let res;
  try {
    res = await fetch(AJAX_SAVE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(r => r.json());
  } catch(e) {
    NHToast.error('Server bilan bog\'liqda xato');
    this.disabled = false;
    return;
  }
  this.disabled = false;

  if (res.status === 'ok') {
    NHToast.success(res.msg);
    ocBS.hide();
    setTimeout(() => location.reload(), 700);
  } else {
    NHToast.error(res.msg);
  }
});
</script>
<style>
.qty-btn { width:26px;height:26px;border-radius:6px;background:var(--bg);border:1px solid var(--border);font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text);line-height:1;transition:all .15s; }
.qty-btn:hover { background:var(--accent);border-color:var(--accent);color:var(--primary); }
.qty-input { width:52px;text-align:center;font-size:13px;font-weight:700;border:1.5px solid var(--accent);border-radius:6px;background:var(--card);color:var(--text);padding:2px 4px;height:26px;outline:none; }
.offcanvas-footer { background: var(--card); }
/* Rang doirachalari */
.btn-rang-circle {
  width:28px;height:28px;border-radius:50%;border:3px solid transparent;
  cursor:pointer;transition:all .15s;flex-shrink:0;
  box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.btn-rang-circle:hover { transform:scale(1.15); }
.btn-rang-circle.active { border-color:#fff;box-shadow:0 0 0 2px var(--primary),0 2px 6px rgba(0,0,0,.3); transform:scale(1.1); }
</style>
</body>
</html>
