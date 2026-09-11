<?php
// ============================================================
//  IMezon — Mahsulotlar CRUD
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

// Filter parametrlari
$kat_filter = (int)($_GET['kat'] ?? 0);
$search     = trim($_GET['q'] ?? '');

// Kategoriyalar (filter uchun)
$kategoriyalar = $db->rows("SELECT id, nomi, rang FROM im_kategoriyalar WHERE status=1 ORDER BY nomi");

// Mahsulotlar
$where = "WHERE m.status=1";
if ($kat_filter) $where .= " AND m.kategoriya_id=$kat_filter";
if ($search) {
    $sq = mysqli_real_escape_string($link, $search);
    $where .= " AND (m.nomi LIKE '%$sq%' OR m.barcode LIKE '%$sq%')";
}

$mahsulotlar = $db->rows(
    "SELECT m.*,
            k.nomi AS kat_nomi, k.rang AS kat_rang,
            COALESCE(n.sotish_narxi, 0) AS sotuv_narx,
            COALESCE(
              (SELECT SUM(pi.remaining_qty) FROM im_fifo_layers pi WHERE pi.mahsulot_id=m.id AND pi.location_id=0 AND pi.cancelled=0 AND pi.remaining_qty>0),
              0
            ) AS sklad_qoldiq,
            COALESCE(
              (SELECT pi.kelish_narxi FROM im_partiya_items pi
               WHERE pi.mahsulot_id=m.id
               ORDER BY pi.id DESC LIMIT 1),
              0
            ) AS kelish_narx
     FROM im_mahsulotlar m
     LEFT JOIN im_kategoriyalar k ON k.id=m.kategoriya_id
     LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
     $where
     ORDER BY m.nomi ASC"
);

$page_title = 'Mahsulotlar';
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= im_f($page_title) ?> | IMezon Sklad</title>
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
      <button class="im-topbar-btn" id="im-sidebar-toggle">
        <i class="bi bi-list"></i>
      </button>
      <div class="im-page-title">
        <i class="bi bi-box-seam-fill me-1"></i> Mahsulotlar
        <span class="im-badge im-badge-primary ms-1"><?= count($mahsulotlar) ?></span>
      </div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle title="Rejim">
          <i class="bi bi-moon-fill"></i>
        </button>
        <button class="im-btn im-btn-primary im-btn-sm" id="btn-qosh-mah">
          <i class="bi bi-plus-lg"></i> Qo'shish
        </button>
      </div>
    </header>

    <main class="im-content">

      <div class="im-breadcrumb">
        <a href="<?= im_BASE ?>sklad/index.php"><i class="bi bi-house-fill"></i></a>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Mahsulotlar</span>
      </div>

      <!-- Filter bar -->
      <div class="im-card mb-3 im-slide-in">
        <div class="im-card-body" style="padding:14px 20px">
          <form method="GET" class="d-flex align-center gap-3 flex-wrap">
            <div class="im-search flex-grow-1" style="max-width:300px">
              <i class="bi bi-search"></i>
              <input type="text" name="q" value="<?= im_f($search) ?>"
                     placeholder="Nom yoki barcode...">
            </div>
            <select name="kat" class="im-select" style="max-width:200px">
              <option value="">Barcha kategoriyalar</option>
              <?php foreach ($kategoriyalar as $k): ?>
              <option value="<?= $k['id'] ?>" <?= $kat_filter == $k['id'] ? 'selected' : '' ?>>
                <?= im_f($k['nomi']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <button class="im-btn im-btn-dark im-btn-sm" type="submit">
              <i class="bi bi-funnel-fill"></i> Filtr
            </button>
            <?php if ($search || $kat_filter): ?>
            <a href="<?= im_BASE ?>sklad/mahsulotlar.php" class="im-btn im-btn-ghost im-btn-sm">
              <i class="bi bi-x-circle"></i> Tozalash
            </a>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <!-- Table -->
      <div class="im-card im-slide-in">
        <div class="im-table-wrap">
          <table class="im-table" id="mah-table">
            <thead>
              <tr>
                          <th>#</th>
                <th>Rasm</th>
                <th>Barcode</th>
                <th>Nomi</th>
                <th>Kategoriya</th>
                <th class="text-right">Kelish narxi</th>
                <th class="text-right">Sotuv narxi</th>
                <th class="text-center">Sklad qoldig'i</th>
                <th class="text-right">Amallar</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($mahsulotlar): ?>
              <?php foreach ($mahsulotlar as $i => $m): ?>
              <tr id="mah-row-<?= $m['id'] ?>">
                <td class="text-muted fs-sm"><?= $i + 1 ?></td>
                <td>
                  <?php if ($m['rasm']): ?>
                  <img src="<?= im_BASE . im_f($m['rasm']) ?>" alt="<?= im_f($m['nomi']) ?>"
                       style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
                  <?php else: ?>
                  <div style="width:40px;height:40px;border-radius:6px;border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--border)">
                    <i class="bi bi-image"></i>
                  </div>
                  <?php endif; ?>
                </td>
                <td>
                  <code class="fs-sm" style="background:var(--bg);padding:3px 8px;border-radius:4px;font-family:monospace">
                    <?= im_f($m['barcode'] ?: '—') ?>
                  </code>
                </td>
                <td>
                  <div class="fw-semibold"><?= im_f($m['nomi']) ?></div>
                  <?php if ($m['birlik']): ?>
                  <div class="text-muted fs-xs"><?= im_f($m['birlik']) ?></div>
                  <?php endif; ?>
                  <?php if ((float)($m['sotuv_qadami'] ?? 1) < 1): ?>
                  <span class="im-badge" style="background:#0ea5e922;color:#0369a1;font-size:10px">
                    <i class="bi bi-pie-chart-fill"></i> <?= im_f(((float)$m['sotuv_qadami'] === 0.25 ? 'Choraklab' : ((float)$m['sotuv_qadami'] === 0.5 ? 'Yarimtalab' : 'Qadam: '.((float)$m['sotuv_qadami'] + 0)))) ?>
                  </span>
                  <?php endif; ?>
                  <?php if (!$m['sotiladi']): ?>
                  <span class="im-badge" style="background:#f59e0b22;color:#b45309;font-size:10px">🧪 Xomashyo</span>
                  <?php endif; ?>
                  <?php if ($m['faqat_ishlab_chiqarish']): ?>
                  <span class="im-badge" style="background:#7c3aed22;color:#7c3aed;font-size:10px">⚙️ Faqat IC</span>
                  <?php endif; ?>
                  <?php if ($m['oshpaz_kerak']): ?>
                  <span class="im-badge" style="background:#dc262622;color:#dc2626;font-size:10px">🧑‍🍳 Oshpaz</span>
                  <?php endif; ?>
                  <?php if ($m['retsept_avto']): ?>
                  <span class="im-badge" style="background:#0d6efd22;color:#0d6efd;font-size:10px">🫖 Retsept avto</span>
                  <?php endif; ?>
                  <?php if (!empty($m['qozon_rejim'])): ?>
                  <span class="im-badge" style="background:#f9731622;color:#c2410c;font-size:10px">🔥 Qozon/zagotovka</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($m['kat_nomi']): ?>
                  <span class="im-chip">
                    <?php if ($m['kat_rang']): ?>
                    <span class="im-color-dot" style="background:<?= im_f($m['kat_rang']) ?>"></span>
                    <?php endif; ?>
                    <?= im_f($m['kat_nomi']) ?>
                  </span>
                  <?php else: ?>
                  <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-right num">
                  <?php if ((float)$m['kelish_narx'] > 0): ?>
                  <span style="color:var(--info);font-weight:600"><?= im_money($m['kelish_narx']) ?></span>
                  <span class="text-muted fs-xs"> so'm</span>
                  <?php else: ?>
                  <span class="text-muted fs-xs">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-right num">
                  <?php if ((float)$m['sotuv_narx'] > 0): ?>
                  <span class="fw-semibold"><?= im_money($m['sotuv_narx']) ?></span>
                  <span class="text-muted fs-xs"> so'm</span>
                  <?php else: ?>
                  <span class="im-badge im-badge-warning">Belgilanmagan</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php $qoldiq = (float)$m['sklad_qoldiq']; ?>
                  <span class="im-badge <?= $qoldiq <= 0 ? 'im-badge-muted' : ($qoldiq < 5 ? 'im-badge-danger' : 'im-badge-success') ?>">
                    <?= $qoldiq ?> dona
                  </span>
                </td>
                <td class="text-right">
                  <div class="d-flex gap-1 justify-content-end">
                    <a href="<?= im_BASE ?>sklad/barcode-print.php?id=<?= $m['id'] ?>"
                       class="im-btn im-btn-ghost im-btn-icon" title="Barcode chop">
                      <i class="bi bi-upc-scan" style="color:var(--muted)"></i>
                    </a>
                    <button class="im-btn im-btn-ghost im-btn-icon"
                            onclick="mahEdit(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)"
                            title="Tahrirlash">
                      <i class="bi bi-pencil-fill" style="color:var(--info)"></i>
                    </button>
                    <button class="im-btn im-btn-ghost im-btn-icon"
                            onclick="mahDelete(<?= $m['id'] ?>, '<?= im_js($m['nomi']) ?>')"
                            title="Arxivlash">
                      <i class="bi bi-archive-fill" style="color:var(--danger)"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr>
                <td colspan="9">
                  <div class="im-empty">
                    <i class="bi bi-box-seam"></i>
                    <h4>Mahsulotlar yo'q</h4>
                    <p><?= ($search || $kat_filter) ? 'Filtr bo\'yicha hech narsa topilmadi' : 'Birinchi mahsulotni qo\'shing' ?></p>
                  </div>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ─── Qo'shish / Tahrirlash modali ─── -->
<div class="im-overlay" id="mah-modal">
  <div class="im-modal im-modal-lg">
    <div class="im-modal-header">
      <span class="im-modal-title" id="mah-modal-title">Mahsulot qo'shish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <form id="mah-form" enctype="multipart/form-data" novalidate>
        <input type="hidden" id="mah-id" name="id" value="">

        <div class="im-form-row cols-2 mb-3">
          <div class="im-form-group">
            <label class="im-label" for="mah-nomi">
              Nomi <span style="color:var(--danger)">*</span>
            </label>
            <input class="im-input" type="text" id="mah-nomi" name="nomi"
                   placeholder="Mahsulot nomi" required maxlength="250">
          </div>
          <div class="im-form-group">
            <label class="im-label" for="mah-kat">Kategoriya</label>
            <select class="im-select" id="mah-kat" name="kategoriya_id">
              <option value="">— Tanlang —</option>
              <?php foreach ($kategoriyalar as $k): ?>
              <option value="<?= $k['id'] ?>"><?= im_f($k['nomi']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Rasm yuklash -->
        <div class="im-form-group mb-3">
          <label class="im-label">Mahsulot rasmi (ixtiyoriy)</label>
          <div style="display:flex;gap:12px;align-items:flex-start">
            <div id="rasm-preview-wrap" style="flex-shrink:0">
              <div id="rasm-preview" style="width:80px;height:80px;border-radius:10px;border:2px dashed var(--border);background:var(--bg);display:flex;align-items:center;justify-content:center;color:var(--muted);overflow:hidden;cursor:pointer" onclick="document.getElementById('mah-rasm').click()">
                <i class="bi bi-image" style="font-size:28px;opacity:.4" id="rasm-placeholder-icon"></i>
              </div>
            </div>
            <div style="flex:1">
              <input type="file" id="mah-rasm" name="rasm" accept="image/jpeg,image/png,image/webp,image/gif"
                     style="display:none" onchange="previewRasm(this)">
              <button type="button" class="im-btn im-btn-outline im-btn-sm" onclick="document.getElementById('mah-rasm').click()">
                <i class="bi bi-upload"></i> Rasm tanlash
              </button>
              <button type="button" class="im-btn im-btn-ghost im-btn-sm ms-1" id="btn-rasm-clear" style="display:none" onclick="clearRasm()">
                <i class="bi bi-x-circle text-danger"></i> O'chirish
              </button>
              <div class="fs-xs text-muted mt-1">JPG, PNG, WEBP · max 5 MB</div>
              <div id="rasm-hint" class="fs-xs" style="color:var(--success);margin-top:4px;display:none"></div>
            </div>
          </div>
        </div>

        <div class="im-form-row cols-2 mb-3">
          <div class="im-form-group">
            <label class="im-label" for="mah-barcode">Barcode</label>
            <div class="im-input-group">
              <input class="im-input" type="text" id="mah-barcode" name="barcode"
                     placeholder="im-YYYYMMDD-XXXX" maxlength="30">
              <button type="button" class="im-btn im-btn-dark" id="btn-gen-barcode" title="Avtomatik generatsiya">
                <i class="bi bi-arrow-clockwise"></i>
              </button>
            </div>
            <div class="fs-xs text-muted mt-1">Bo'sh qolsa avtomatik generatsiya qilinadi</div>
            <div id="mah-barcode-warning" style="display:none; color: var(--danger);" class="fs-xs mt-1 fw-semibold"></div>
          </div>
          <div class="im-form-group">
            <label class="im-label" for="mah-birlik">O'lchov birligi</label>
            <select class="im-select" id="mah-birlik" name="birlik">
              <option value="dona">dona</option>
              <option value="kg">kg</option>
              <option value="litr">litr</option>
              <option value="metr">metr</option>
              <option value="porsiya">porsiya</option>
              <option value="juft">juft</option>
              <option value="komplekt">komplekt</option>
              <option value="quti">quti</option>
            </select>
          </div>
          <div class="im-form-group">
            <label class="im-label" for="mah-sotuv-qadami">Sotuv qadami</label>
            <select class="im-select" id="mah-sotuv-qadami" name="sotuv_qadami">
              <option value="1">Butun — 1 dona</option>
              <option value="0.5">Yarim — ½ dona</option>
              <option value="0.25">Chorak — ¼ dona</option>
              <option value="0.1">O'ndan bir — 0.1</option>
              <option value="0.001">Erkin o'lchov — 0.001</option>
            </select>
            <div class="fs-xs text-muted mt-1">Non kabi bo'linadigan mahsulot uchun ¼ yoki ½ ni tanlang.</div>
          </div>
          <div class="im-form-group">
            <label class="im-label" for="mah-narx">1 butun birlik narxi (so'm)</label>
            <input class="im-input num" type="number" id="mah-narx" name="sotuv_narx"
                   placeholder="0" min="0" step="100">
          </div>
        </div>

        <div class="im-form-group mb-3">
          <label class="im-label" for="mah-tavsif">Tavsif</label>
          <textarea class="im-textarea" id="mah-tavsif" name="tavsif"
                    placeholder="Mahsulot haqida qisqacha" rows="2"></textarea>
        </div>

        <!-- Sotiladi toggle -->
        <div class="mb-3 p-3" style="background:var(--bg);border-radius:var(--radius);border:1px solid var(--border)">
          <label class="d-flex align-items-center gap-3 mb-2" style="cursor:pointer">
            <input type="checkbox" id="mah-sotiladi" name="sotiladi" value="1" checked
                   style="width:18px;height:18px;cursor:pointer">
            <div>
              <div class="fw-semibold" style="font-size:14px">Dukonda sotiladi</div>
              <div class="text-muted fs-xs">O'chirsangiz — bu mahsulot faqat qayta ishlash uchun (xomashyo)</div>
            </div>
          </label>
          <label class="d-flex align-items-center gap-3" style="cursor:pointer">
            <input type="checkbox" id="mah-faqat-ic" name="faqat_ishlab_chiqarish" value="1"
                   style="width:18px;height:18px;cursor:pointer">
            <div>
              <div class="fw-semibold" style="font-size:14px">Faqat Ishlab chiqarish orqali kiritiladi</div>
              <div class="text-muted fs-xs">Belgilanganda — yuk qabulda bu mahsulotni qo'shib bo'lmaydi (masalan: tayyor osh)</div>
            </div>
          </label>
          <label class="d-flex align-items-center gap-3 mt-2" style="cursor:pointer">
            <input type="checkbox" id="mah-oshpaz" name="oshpaz_kerak" value="1"
                   style="width:18px;height:18px;cursor:pointer">
            <div>
              <div class="fw-semibold" style="font-size:14px">Oshpaz tayyorlaydi (Kuxnya uchun)</div>
              <div class="text-muted fs-xs">Sotuvchi buyurtma berganda Oshpaz ekraniga tushadi. Xomashyo oshpaz "Qabul qildim" bosganda yechiladi</div>
            </div>
          </label>
          <label class="d-flex align-items-center gap-3 mt-2" style="cursor:pointer">
            <input type="checkbox" id="mah-retsept-avto" name="retsept_avto" value="1"
                   style="width:18px;height:18px;cursor:pointer">
            <div>
              <div class="fw-semibold" style="font-size:14px">Retsept avtomatik (choy, kofe...)</div>
              <div class="text-muted fs-xs">
                Retsepti bor, lekin oshpaz tasdig'i shart emas. Vitrinada zaxira
                turmaydi — xomashyo <b>sotuv paytida</b> avtomatik yechiladi.
              </div>
            </div>
          </label>
          <label class="d-flex align-items-center gap-3 mt-2" style="cursor:pointer">
            <input type="checkbox" id="mah-qozon" name="qozon_rejim" value="1"
                   style="width:18px;height:18px;cursor:pointer">
            <div>
              <div class="fw-semibold" style="font-size:14px">Kunlik qozon / zagotovka (osh, shashlik)</div>
              <div class="text-muted fs-xs">
                «Oshpaz tayyorlaydi» bilan birga ishlaydi. Xomashyo har buyurtmada emas,
                <b>«Osh qozoni» oynasida partiya ochilganda</b> real o'lchov bilan bir marta yechiladi.
                Har turga chiqish_soni=1 bo'lgan retsept (1 porsiya / 1 shampur) kerak.
              </div>
            </div>
          </label>
        </div>

        <!-- Ulgurji narx -->
        <div class="im-card" id="ulg-section" style="background:var(--bg);border:1px dashed var(--border)">
          <div class="im-card-header" style="background:transparent">
            <i class="bi bi-layers-fill" style="color:var(--info)"></i>
            <span class="im-card-title" style="font-size:13.5px">Ulgurji narx (ixtiyoriy)</span>
          </div>
          <div class="im-card-body" style="padding:14px">
            <div class="im-form-row cols-2">
              <div class="im-form-group">
                <label class="im-label" for="mah-ulg-min">Minimal miqdor (dona)</label>
                <input class="im-input" type="number" id="mah-ulg-min" name="ulg_min_soni"
                       placeholder="Masalan: 10" min="1">
              </div>
              <div class="im-form-group">
                <label class="im-label" for="mah-ulg-narx">Ulgurji narxi (so'm)</label>
                <input class="im-input num" type="number" id="mah-ulg-narx" name="ulg_narx"
                       placeholder="0" min="0">
              </div>
            </div>
          </div>
        </div>

        <button type="submit" style="display:none"></button>
      </form>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="mah-save-btn">
        <i class="bi bi-check-lg"></i> Saqlash
      </button>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
const MAH_SAVE   = window.im_BASE + 'sklad/ajax/mah-save.php';
const MAH_DELETE = window.im_BASE + 'sklad/ajax/mah-delete.php';
const BARCODE_GEN= window.im_BASE + 'sklad/ajax/barcode-gen.php';

// Rasm preview
function previewRasm(input) {
  const file = input.files[0];
  if (!file) return;
  const preview = document.getElementById('rasm-preview');
  const icon = document.getElementById('rasm-placeholder-icon');
  const clearBtn = document.getElementById('btn-rasm-clear');
  const hint = document.getElementById('rasm-hint');

  const reader = new FileReader();
  reader.onload = function(e) {
    preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
    clearBtn.style.display = '';
    hint.style.display = '';
    hint.textContent = `✓ ${file.name} (${(file.size/1024).toFixed(0)} KB)`;
  };
  reader.readAsDataURL(file);
}

function clearRasm() {
  document.getElementById('mah-rasm').value = '';
  const preview = document.getElementById('rasm-preview');
  preview.innerHTML = '<i class="bi bi-image" style="font-size:28px;opacity:.4" id="rasm-placeholder-icon"></i>';
  document.getElementById('btn-rasm-clear').style.display = 'none';
  document.getElementById('rasm-hint').style.display = 'none';
}

// Yangi mahsulot
document.getElementById('btn-qosh-mah').addEventListener('click', () => {
  document.getElementById('mah-modal-title').textContent = "Mahsulot qo'shish";
  document.getElementById('mah-form').reset();
  document.getElementById('mah-id').value = '';
  document.getElementById('mah-sotiladi').checked = true;
  document.getElementById('mah-sotuv-qadami').value = '1';
  document.getElementById('mah-faqat-ic').checked = false;
  document.getElementById('mah-oshpaz').checked = false;
  document.getElementById('mah-qozon').checked = false;
  syncQozonRejim();
  document.getElementById('mah-barcode-warning').style.display = 'none';
  clearRasm();
  toggleUlgSection();
  NHModal.open('mah-modal');
  setTimeout(() => document.getElementById('mah-nomi').focus(), 100);
});

function toggleUlgSection() {
  const sotiladi = document.getElementById('mah-sotiladi').checked;
  const ulg = document.getElementById('ulg-section');
  if (ulg) ulg.style.display = sotiladi ? '' : 'none';
}
document.getElementById('mah-sotiladi').addEventListener('change', toggleUlgSection);

// Qozon/zagotovka rejimi FAQAT "Oshpaz tayyorlaydi" bilan ma'noli
// (server ham shuni majburlaydi: mah-save.php). UI ni sinxron tutamiz.
function syncQozonRejim() {
  const oshpaz = document.getElementById('mah-oshpaz');
  const qozon  = document.getElementById('mah-qozon');
  if (!oshpaz || !qozon) return;
  if (qozon.checked && !oshpaz.checked) oshpaz.checked = true;
  qozon.disabled = !oshpaz.checked;
  if (!oshpaz.checked) qozon.checked = false;
}
document.getElementById('mah-oshpaz').addEventListener('change', syncQozonRejim);
document.getElementById('mah-qozon').addEventListener('change', syncQozonRejim);

// Barcode generatsiya
document.getElementById('btn-gen-barcode').addEventListener('click', async () => {
  const btn = document.getElementById('btn-gen-barcode');
  btn.disabled = true;
  btn.innerHTML = '<span class="im-spinner"></span>';

  const res = await IMAjax.get(BARCODE_GEN);
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';

  if (res.status === 'ok') {
    document.getElementById('mah-barcode').value = res.data.barcode;
    NHToast.info('Barcode generatsiya qilindi');
  } else {
    NHToast.error(res.msg || 'Xatolik');
  }
});

// Barcode tekshirish
let barcodeTimer = null;
document.getElementById('mah-barcode').addEventListener('input', (e) => {
  clearTimeout(barcodeTimer);
  const wrn = document.getElementById('mah-barcode-warning');
  wrn.style.display = 'none';
  const val = e.target.value.trim();
  if(!val) return;
  
  const currentId = document.getElementById('mah-id').value;
  
  barcodeTimer = setTimeout(async () => {
    const res = await IMAjax.get(window.im_BASE + 'sklad/ajax/mah-search.php', { b: val });
    if(res.status === 'ok' && res.data && res.data.topildi) {
      if(currentId != res.data.mahsulot.id) {
         wrn.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Bu shtrix-kod ro'yxatda mavjud:<br><b>${im_esc(res.data.mahsulot.nomi)}</b>`;
         wrn.style.display = 'block';
      }
    }
  }, 500);
});

// Tahrirlash
function mahEdit(m) {
  document.getElementById('mah-modal-title').textContent = 'Mahsulotni tahrirlash';
  document.getElementById('mah-id').value = m.id;
  document.getElementById('mah-nomi').value = m.nomi || '';
  document.getElementById('mah-kat').value = m.kategoriya_id || '';
  document.getElementById('mah-barcode').value = m.barcode || '';
  document.getElementById('mah-birlik').value = m.birlik || 'dona';
  document.getElementById('mah-sotuv-qadami').value = String(parseFloat(m.sotuv_qadami || 1));
  document.getElementById('mah-narx').value = m.sotuv_narx || '';
  document.getElementById('mah-tavsif').value = m.tavsif || '';
  document.getElementById('mah-sotiladi').checked = m.sotiladi == 1 || m.sotiladi === undefined;
  document.getElementById('mah-faqat-ic').checked  = m.faqat_ishlab_chiqarish == 1;
  document.getElementById('mah-oshpaz').checked  = m.oshpaz_kerak == 1;
  document.getElementById('mah-retsept-avto').checked = m.retsept_avto == 1;
  document.getElementById('mah-qozon').checked = m.qozon_rejim == 1;
  syncQozonRejim();
  toggleUlgSection();

  // Rasm preview (agar mavjud bo'lsa)
  clearRasm();
  if (m.rasm) {
    const preview = document.getElementById('rasm-preview');
    preview.innerHTML = `<img src="${window.im_BASE}${m.rasm}" style="width:100%;height:100%;object-fit:cover;">`;
    document.getElementById('btn-rasm-clear').style.display = '';
    document.getElementById('rasm-hint').style.display = '';
    document.getElementById('rasm-hint').textContent = '✓ Mavjud rasm';
  }

  // Ulgurji — fetch from server (simplistic: clear)
  document.getElementById('mah-ulg-min').value = '';
  document.getElementById('mah-ulg-narx').value = '';

  document.getElementById('mah-barcode-warning').style.display = 'none';

  NHModal.open('mah-modal');
  setTimeout(() => document.getElementById('mah-nomi').focus(), 100);

  // Load ulgurji narx
  IMAjax.get(window.im_BASE + 'sklad/ajax/mah-search.php', { id: m.id }).then(res => {
    if (res.status === 'ok' && res.data && res.data.ulg) {
      document.getElementById('mah-ulg-min').value  = res.data.ulg.min_soni  || '';
      document.getElementById('mah-ulg-narx').value = res.data.ulg.narx || '';
    }
  });
}

// Saqlash
document.getElementById('mah-save-btn').addEventListener('click', async () => {
  const nomi = document.getElementById('mah-nomi').value.trim();
  if (!nomi) {
    NHToast.error('Mahsulot nomini kiriting');
    document.getElementById('mah-nomi').focus();
    return;
  }

  const btn = document.getElementById('mah-save-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="im-spinner"></span>';

  const fd = new FormData(document.getElementById('mah-form'));
  const res = await IMAjax.post(MAH_SAVE, fd);

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-lg"></i> Saqlash';

  if (res.status === 'ok') {
    NHToast.success(res.msg || 'Saqlandi!');
    NHModal.close('mah-modal');
    setTimeout(() => location.reload(), 600);
  } else {
    NHToast.error(res.msg || 'Xatolik!');
  }
});
document.getElementById('mah-form').addEventListener('submit', function(e) {
  e.preventDefault();
  document.getElementById('mah-save-btn').click();
});

// Arxivlash
async function mahDelete(id, nomi) {
  const ok = await NHConfirm.show({
    title: 'Mahsulotni arxivlash',
    text: `"${nomi}" arxivlansinmi?`,
    sub: "Mahsulot o'chirilmaydi, faqat arxivga ko'chiriladi.",
    btnText: 'Arxivlash',
    btnClass: 'im-btn-warning',
    icon: 'bi-archive-fill',
    iconColor: 'var(--warning)'
  });
  if (!ok) return;

  const res = await IMAjax.post(MAH_DELETE, { id });
  if (res.status === 'ok') {
    NHToast.success(res.msg || 'Arxivlandi!');
    const row = document.getElementById(`mah-row-${id}`);
    if (row) { row.style.opacity = '0'; setTimeout(() => row.remove(), 300); }
  } else {
    NHToast.error(res.msg || 'Xatolik!');
  }
}
</script>
</body>
</html>
