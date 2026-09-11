<?php
// ============================================================
//  IMezon — Stollar boshqaruvi (Do'kon/Choyxona)
//  Stollar zonaga bo'linadi: Teraska, Podval, Zal, VIP...
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir']);
$db = new Cyber();

$filial_id = (int)($im_filial_id ?: 1);

// ── Zonalar (stol soni bilan) ──────────────────────────────
$zonalar = $db->rows(
    "SELECT z.*,
            (SELECT COUNT(*) FROM im_stollar s WHERE s.zona_id = z.id) AS stol_soni
     FROM im_zonalar z
     WHERE z.filial_id = $filial_id
     ORDER BY z.tartib ASC, z.id ASC"
);

// ── Stollar (zona nomi/rangi bilan) ────────────────────────
$stollar = $db->rows(
    "SELECT s.*,
            z.nomi AS zona_nomi, z.rang AS zona_rang, z.tartib AS zona_tartib,
            (SELECT COUNT(*) FROM im_sotuvchi_order o
             WHERE o.stol_id=s.id AND o.status NOT IN ('bekor','tugallandi')) AS band_soni,
            (SELECT o.id FROM im_sotuvchi_order o
             WHERE o.stol_id=s.id AND o.status NOT IN ('bekor','tugallandi')
             ORDER BY o.id DESC LIMIT 1) AS band_order_id
     FROM im_stollar s
     LEFT JOIN im_zonalar z ON z.id = s.zona_id
     WHERE s.filial_id=$filial_id
     ORDER BY COALESCE(z.tartib, 9999) ASC, s.status DESC, s.tartib ASC, s.id ASC"
);

$jami = count($stollar);
$band = count(array_filter($stollar, fn($s) => (int)$s['band_soni'] > 0 && $s['status']));
$faol = count(array_filter($stollar, fn($s) => $s['status']));

// Stollarni zona bo'yicha guruhlash (NULL → "Boshqa").
// Guruhlar zona tartibida tayyorlanadi — bo'sh zonalar ham ko'rinadi.
$guruhlar = [];
foreach ($zonalar as $z) {
    $guruhlar[(int)$z['id']] = ['nomi' => $z['nomi'], 'rang' => $z['rang'], 'stollar' => []];
}
foreach ($stollar as $s) {
    $zid = $s['zona_id'] ? (int)$s['zona_id'] : 0;
    if (!isset($guruhlar[$zid])) {
        $guruhlar[$zid] = [
            'nomi' => $s['zona_nomi'] ?: 'Boshqa',
            'rang' => $s['zona_rang'] ?: '#94a3b8',
            'stollar' => [],
        ];
    }
    $guruhlar[$zid]['stollar'][] = $s;
}
// "Boshqa" (0) doim oxirida
if (isset($guruhlar[0])) { $b = $guruhlar[0]; unset($guruhlar[0]); $guruhlar[0] = $b; }

// Ikonka tanlovi (zona modali)
$ikonkalar = [
    'bi-grid-3x3-gap-fill' => 'Panjara',
    'bi-tree-fill'         => 'Daraxt (teraska)',
    'bi-box-fill'          => 'Quti (podval)',
    'bi-house-fill'        => 'Uy (zal)',
    'bi-gem'               => 'Olmos (VIP)',
    'bi-cup-hot-fill'      => 'Choy',
    'bi-sun-fill'          => 'Quyosh (ochiq havo)',
    'bi-moon-stars-fill'   => 'Oy (2-qavat)',
    'bi-people-fill'       => 'Odamlar',
    'bi-star-fill'         => 'Yulduz',
];
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stollar | IMezon Do'kon</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
<style>
.zona-list{ display:flex; flex-direction:column; gap:8px; }
.zona-row{
  display:flex; align-items:center; gap:12px; padding:10px 12px;
  border:1px solid var(--border); border-radius:var(--radius); background:var(--card);
}
.zona-swatch{ width:14px; height:14px; border-radius:4px; flex-shrink:0; }
.zona-row .z-nomi{ font-weight:700; }
.zona-row .z-soni{ font-size:12px; color:var(--muted); }
.zona-row .z-amal{ margin-left:auto; display:flex; gap:4px; }

.zona-block{ margin-bottom:22px; }
.zona-head{
  display:flex; align-items:center; gap:8px; margin:0 0 10px;
  padding-bottom:6px; border-bottom:2px solid var(--border);
}
.zona-head .dot{ width:12px; height:12px; border-radius:4px; flex-shrink:0; }
.zona-head .t{ font-weight:800; font-size:15px; }
.zona-head .c{ font-size:12px; color:var(--muted); }

.stol-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; }
.stol-card{
  border:2px solid var(--border); border-radius:var(--radius-lg); padding:16px 14px;
  text-align:center; background:var(--card); transition:var(--transition);
  display:flex; flex-direction:column; gap:6px; position:relative;
}
.stol-card.band{ border-color:var(--danger); background:var(--danger-light); }
.stol-card.off{ opacity:.5; border-style:dashed; }
.stol-card .ic{ font-size:26px; }
.stol-card .nomi{ font-weight:700; font-size:15px; }
.stol-card .holat{ font-size:11px; font-weight:600; }
.stol-card .holat.bosh{ color:var(--success); }
.stol-card .holat.band{ color:var(--danger); }
.stol-card .amal{ display:flex; gap:4px; justify-content:center; margin-top:4px; }
.zona-empty{ font-size:13px; color:var(--muted); padding:6px 2px; }
.color-swatches{ display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.color-swatches button{
  width:26px; height:26px; border-radius:6px; border:2px solid transparent; cursor:pointer;
}
.color-swatches button.sel{ border-color:var(--text); }
</style>
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title"><i class="bi bi-grid-3x3-gap-fill me-1"></i> Stollar</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-outline im-btn-sm" id="btn-zona-add">
        <i class="bi bi-bounding-box-circles"></i> Zona qo'shish
      </button>
      <button class="im-btn im-btn-primary im-btn-sm" id="btn-stol-add">
        <i class="bi bi-plus-lg"></i> Stol qo'shish
      </button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="im-stat-card" style="border-left:4px solid var(--accent-dark)">
          <div class="im-stat-icon" style="background:rgba(226,185,111,.15);color:var(--accent-dark)"><i class="bi bi-grid-3x3-gap-fill"></i></div>
          <div><div class="im-stat-label">Jami faol stollar</div>
          <div class="im-stat-value"><?= $faol ?> ta</div></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.12);color:var(--danger)"><i class="bi bi-exclamation-circle-fill"></i></div>
          <div><div class="im-stat-label">Band</div>
          <div class="im-stat-value" style="color:var(--danger)"><?= $band ?> ta</div></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i class="bi bi-check-circle-fill"></i></div>
          <div><div class="im-stat-label">Bo'sh</div>
          <div class="im-stat-value" style="color:var(--success)"><?= $faol - $band ?> ta</div></div>
        </div>
      </div>
    </div>

    <!-- ZONALAR -->
    <div class="im-card im-slide-in mb-4">
      <div class="im-card-header">
        <i class="bi bi-bounding-box-circles"></i>
        <span class="im-card-title">Zonalar</span>
        <span class="im-badge im-badge-muted"><?= count($zonalar) ?> ta</span>
      </div>
      <div class="im-card-body p-3">
        <?php if ($zonalar): ?>
        <div class="zona-list">
          <?php foreach ($zonalar as $z): ?>
          <div class="zona-row" id="zona-<?= (int)$z['id'] ?>">
            <span class="zona-swatch" style="background:<?= im_f($z['rang']) ?>"></span>
            <i class="bi <?= im_f($z['ikonka']) ?>" style="color:<?= im_f($z['rang']) ?>"></i>
            <span class="z-nomi"><?= im_f($z['nomi']) ?></span>
            <span class="z-soni"><?= (int)$z['stol_soni'] ?> stol</span>
            <span class="z-amal">
              <button class="im-btn im-btn-icon im-btn-sm im-btn-ghost"
                      onclick="zonaEdit(<?= (int)$z['id'] ?>, '<?= im_js($z['nomi']) ?>', '<?= im_js($z['rang']) ?>', '<?= im_js($z['ikonka']) ?>')"
                      title="Tahrirlash"><i class="bi bi-pencil-fill"></i></button>
              <button class="im-btn im-btn-icon im-btn-sm im-btn-ghost text-danger"
                      onclick="zonaDelete(<?= (int)$z['id'] ?>, '<?= im_js($z['nomi']) ?>', <?= (int)$z['stol_soni'] ?>)"
                      <?= (int)$z['stol_soni'] > 0 ? 'style="opacity:.35" title="Stol biriktirilgan — o\'chirib bo\'lmaydi"' : 'title="O\'chirish"' ?>>
                <i class="bi bi-trash3-fill"></i></button>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="zona-empty">Hali zona yo'q. "Zona qo'shish" — masalan Teraska, Podval, Zal.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- STOLLAR (zona bo'yicha guruhlangan) -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-list-ul"></i>
        <span class="im-card-title">Stollar ro'yxati</span>
        <span class="im-badge im-badge-muted"><?= $jami ?> ta</span>
      </div>
      <div class="im-card-body p-3">
        <?php if ($stollar): ?>
          <?php foreach ($guruhlar as $zid => $g): ?>
          <div class="zona-block">
            <div class="zona-head">
              <span class="dot" style="background:<?= im_f($g['rang']) ?>"></span>
              <span class="t"><?= im_f($g['nomi']) ?></span>
              <span class="c"><?= count($g['stollar']) ?> ta</span>
            </div>
            <?php if ($g['stollar']): ?>
            <div class="stol-grid">
              <?php foreach ($g['stollar'] as $s):
                $is_band = (int)$s['band_soni'] > 0 && $s['status'];
                $is_off  = !$s['status'];
                $cls = $is_off ? 'off' : ($is_band ? 'band' : '');
              ?>
              <div class="stol-card <?= $cls ?>" id="stol-<?= $s['id'] ?>">
                <div class="ic"><?= $is_off ? '🚫' : ($is_band ? '🔴' : '🟢') ?></div>
                <div class="nomi"><?= im_f($s['nomi']) ?></div>
                <div class="holat <?= $is_band ? 'band' : 'bosh' ?>">
                  <?= $is_off ? "O'chirilgan" : ($is_band ? 'Band' : "Bo'sh") ?>
                </div>
                <div class="amal">
                  <button class="im-btn im-btn-icon im-btn-sm im-btn-ghost"
                          onclick="stolEdit(<?= $s['id'] ?>,'<?= im_js($s['nomi']) ?>',<?= $s['zona_id'] ? (int)$s['zona_id'] : 0 ?>)" title="Tahrirlash">
                    <i class="bi bi-pencil-fill"></i>
                  </button>
                  <button class="im-btn im-btn-icon im-btn-sm im-btn-ghost <?= $s['status'] ? 'text-danger' : 'text-success' ?>"
                          onclick="stolToggle(<?= $s['id'] ?>, <?= $is_band ? 1 : 0 ?>)"
                          title="<?= $s['status'] ? "O'chirish" : 'Faollashtirish' ?>">
                    <i class="bi <?= $s['status'] ? 'bi-x-circle-fill' : 'bi-arrow-counterclockwise' ?>"></i>
                  </button>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="zona-empty">Bu zonada stol yo'q — "Stol qo'shish" da zonani tanlang yoki mavjud stolni tahrirlang.</div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
        <div class="im-empty">
          <i class="bi bi-grid-3x3-gap"></i>
          <h4>Stol yo'q</h4>
          <p class="text-muted">Hali birorta stol qo'shilmagan — "Stol qo'shish" tugmasini bosing</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
</div>

<!-- Stol qo'shish/tahrirlash modali -->
<div class="im-overlay" id="stol-modal">
  <div class="im-modal">
    <div class="im-modal-header">
      <i class="bi bi-grid-3x3-gap-fill" style="color:var(--accent-dark);font-size:20px"></i>
      <span class="im-modal-title" id="stol-modal-title">Stol qo'shish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="stol-id">
      <div class="im-form-group">
        <label class="im-label">Stol nomi *</label>
        <input class="im-input" type="text" id="stol-nomi" placeholder="Masalan: Stol 11, VIP-1, Ayvon 2..." maxlength="50">
      </div>
      <div class="im-form-group">
        <label class="im-label">Zona</label>
        <select class="im-input" id="stol-zona">
          <option value="0">— Zonasiz (Boshqa) —</option>
          <?php foreach ($zonalar as $z): ?>
          <option value="<?= (int)$z['id'] ?>"><?= im_f($z['nomi']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="btn-stol-save">
        <i class="bi bi-floppy-fill"></i> Saqlash
      </button>
    </div>
  </div>
</div>

<!-- Zona qo'shish/tahrirlash modali -->
<div class="im-overlay" id="zona-modal">
  <div class="im-modal">
    <div class="im-modal-header">
      <i class="bi bi-bounding-box-circles" style="color:var(--accent-dark);font-size:20px"></i>
      <span class="im-modal-title" id="zona-modal-title">Zona qo'shish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="zona-id">
      <div class="im-form-group">
        <label class="im-label">Zona nomi *</label>
        <input class="im-input" type="text" id="zona-nomi" placeholder="Masalan: Teraska, Podval, Zal, VIP..." maxlength="50">
      </div>
      <div class="im-form-group">
        <label class="im-label">Rang</label>
        <input type="hidden" id="zona-rang" value="#64748b">
        <div class="color-swatches" id="zona-rang-swatches"></div>
      </div>
      <div class="im-form-group">
        <label class="im-label">Ikonka</label>
        <select class="im-input" id="zona-ikonka">
          <?php foreach ($ikonkalar as $key => $label): ?>
          <option value="<?= $key ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="btn-zona-save">
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
const AJAX = {
  stolSave:   window.im_BASE + 'dukon/ajax/stol-save.php',
  stolToggle: window.im_BASE + 'dukon/ajax/stol-toggle.php',
  zonaSave:   window.im_BASE + 'dukon/ajax/zona-save.php',
  zonaDelete: window.im_BASE + 'dukon/ajax/zona-delete.php',
};
const ZONA_RANGLAR = ['#0ea5e9','#22c55e','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#64748b','#0f766e','#b45309'];

// ──── STOL ────────────────────────────────────────────────
document.getElementById('btn-stol-add').addEventListener('click', () => {
  document.getElementById('stol-modal-title').textContent = 'Stol qo\'shish';
  document.getElementById('stol-id').value = '';
  document.getElementById('stol-nomi').value = '';
  document.getElementById('stol-zona').value = '0';
  NHModal.open('stol-modal');
  setTimeout(()=>document.getElementById('stol-nomi').focus(),200);
});

function stolEdit(id, nomi, zonaId) {
  document.getElementById('stol-modal-title').textContent = 'Stol tahrirlash';
  document.getElementById('stol-id').value = id;
  document.getElementById('stol-nomi').value = nomi;
  document.getElementById('stol-zona').value = String(zonaId || 0);
  NHModal.open('stol-modal');
  setTimeout(()=>document.getElementById('stol-nomi').focus(),200);
}

document.getElementById('btn-stol-save').addEventListener('click', async () => {
  const id      = document.getElementById('stol-id').value;
  const nomi    = document.getElementById('stol-nomi').value.trim();
  const zona_id = document.getElementById('stol-zona').value;
  if (!nomi) { NHToast.error('Stol nomini kiriting'); return; }

  const res = await IMAjax.post(AJAX.stolSave, { id, nomi, zona_id });
  if (res.status === 'ok') { NHToast.success(res.msg); setTimeout(()=>location.reload(), 500); }
  else NHToast.error(res.msg);
});

async function stolToggle(id, isBand) {
  if (isBand) { NHToast.error("Band stolni o'chirib bo'lmaydi — avval buyurtmani yakunlang"); return; }
  const ok = await NHConfirm.ask("Holatini o'zgartirishni tasdiqlaysizmi?");
  if (!ok) return;
  const res = await IMAjax.post(AJAX.stolToggle, { id });
  if (res.status === 'ok') { NHToast.success(res.msg); setTimeout(()=>location.reload(), 400); }
  else NHToast.error(res.msg);
}

// ──── ZONA ────────────────────────────────────────────────
function buildRangSwatches(selected) {
  const wrap = document.getElementById('zona-rang-swatches');
  wrap.innerHTML = '';
  ZONA_RANGLAR.forEach(c => {
    const b = document.createElement('button');
    b.type = 'button';
    b.style.background = c;
    if (c.toLowerCase() === (selected||'').toLowerCase()) b.classList.add('sel');
    b.onclick = () => {
      document.getElementById('zona-rang').value = c;
      wrap.querySelectorAll('button').forEach(x => x.classList.remove('sel'));
      b.classList.add('sel');
    };
    wrap.appendChild(b);
  });
}

document.getElementById('btn-zona-add').addEventListener('click', () => {
  document.getElementById('zona-modal-title').textContent = 'Zona qo\'shish';
  document.getElementById('zona-id').value = '';
  document.getElementById('zona-nomi').value = '';
  document.getElementById('zona-rang').value = '#64748b';
  document.getElementById('zona-ikonka').value = 'bi-grid-3x3-gap-fill';
  buildRangSwatches('#64748b');
  NHModal.open('zona-modal');
  setTimeout(()=>document.getElementById('zona-nomi').focus(),200);
});

function zonaEdit(id, nomi, rang, ikonka) {
  document.getElementById('zona-modal-title').textContent = 'Zona tahrirlash';
  document.getElementById('zona-id').value = id;
  document.getElementById('zona-nomi').value = nomi;
  document.getElementById('zona-rang').value = rang || '#64748b';
  document.getElementById('zona-ikonka').value = ikonka || 'bi-grid-3x3-gap-fill';
  buildRangSwatches(rang || '#64748b');
  NHModal.open('zona-modal');
  setTimeout(()=>document.getElementById('zona-nomi').focus(),200);
}

document.getElementById('btn-zona-save').addEventListener('click', async () => {
  const id     = document.getElementById('zona-id').value;
  const nomi   = document.getElementById('zona-nomi').value.trim();
  const rang   = document.getElementById('zona-rang').value;
  const ikonka = document.getElementById('zona-ikonka').value;
  if (!nomi) { NHToast.error('Zona nomini kiriting'); return; }

  const res = await IMAjax.post(AJAX.zonaSave, { id, nomi, rang, ikonka });
  if (res.status === 'ok') { NHToast.success(res.msg); setTimeout(()=>location.reload(), 500); }
  else NHToast.error(res.msg);
});

async function zonaDelete(id, nomi, stolSoni) {
  if (stolSoni > 0) {
    NHToast.error(`"${nomi}" zonasida ${stolSoni} ta stol bor — avval ularni boshqa zonaga o'tkazing yoki o'chiring`);
    return;
  }
  const ok = await NHConfirm.ask(`"${nomi}" zonasi o'chirilsinmi?`);
  if (!ok) return;
  const res = await IMAjax.post(AJAX.zonaDelete, { id });
  if (res.status === 'ok') { NHToast.success(res.msg); setTimeout(()=>location.reload(), 500); }
  else NHToast.error(res.msg);
}
</script>
</body>
</html>
