<?php
// ============================================================
//  IMezon — Yuk Qabul (Partiya)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

// Postavshiklar ro'yxati
$postavshiklar = $db->rows(
    "SELECT id, nomi, telefon FROM im_postavshiklar WHERE status=1 ORDER BY nomi"
);
// Filiallar (do'konlar) qabul qilish mumkin bo'lgan joylar
$filiallar_list = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY tartib");

// Oxirgi 10 ta partiya (jadval uchun)
$partiyalar = $db->rows(
    "SELECT p.*,
            ps.nomi AS ps_nomi,
            (SELECT COUNT(*) FROM im_partiya_items WHERE partiya_id=p.id) AS items_soni
     FROM im_partiyalar p
     LEFT JOIN im_postavshiklar ps ON ps.id=p.postavshik_id
     ORDER BY p.created_at DESC
     LIMIT 10"
);

// Foydalanuvchida qaysi partiya faol ekanligi
$active_id = (int)($_GET['ochiq_id'] ?? 0);
$ochiq_q = $active_id ? " AND p.id=$active_id " : "";

$ochiq = $db->row(
    "SELECT p.*, ps.nomi AS ps_nomi,
            (SELECT nomi FROM im_filiallar WHERE id=p.qabul_filial_id) AS filial_nomi
     FROM im_partiyalar p
     LEFT JOIN im_postavshiklar ps ON ps.id=p.postavshik_id
     WHERE p.holat='ochiq' AND p.xodim_id=$im_user_id $ochiq_q
     ORDER BY p.created_at DESC LIMIT 1"
);

$ochiq_items = [];
if ($ochiq) {
    $ochiq_id = (int)$ochiq['id'];
    $ochiq_items = $db->rows(
        "SELECT pi.*, m.nomi AS mah_nomi, m.barcode,
                COALESCE(n.sotish_narxi,0) AS sotuv_narx
         FROM im_partiya_items pi
         JOIN im_mahsulotlar m ON m.id=pi.mahsulot_id
         LEFT JOIN im_narxlar n ON n.mahsulot_id=m.id
         WHERE pi.partiya_id=$ochiq_id
         ORDER BY pi.id DESC"
    );
}

$usd_kurs = im_usd_kurs();
$page_title = 'Yuk Qabul';
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
  <style>
    .qabul-step {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      overflow: hidden;
    }
    .step-header {
      background: var(--primary);
      color: #fff;
      padding: 14px 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .step-num {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: var(--accent);
      color: var(--primary);
      font-weight: 800;
      font-size: 13px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .step-title { font-size: 14px; font-weight: 700; }

    .item-card {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 11px 16px;
      border-bottom: 1px solid var(--border-light);
      transition: background var(--transition);
    }
    .item-card:last-child { border-bottom: none; }
    .item-card:hover { background: var(--bg); }

    .mah-search-result {
      position: absolute;
      top: 100%;
      left: 0; right: 0;
      background: var(--card);
      border: 1.5px solid var(--accent);
      border-top: none;
      border-radius: 0 0 var(--radius-sm) var(--radius-sm);
      max-height: 260px;
      overflow-y: auto;
      z-index: 100;
      box-shadow: var(--shadow);
    }
    .mah-search-result .item {
      padding: 10px 14px;
      cursor: pointer;
      border-bottom: 1px solid var(--border-light);
      transition: background var(--transition);
    }
    .mah-search-result .item:hover { background: var(--bg); }
    .mah-search-result .item:last-child { border-bottom: none; }

    #ochiq-banner {
      background: linear-gradient(135deg, var(--accent) 0%, #d4a85a 100%);
      color: var(--primary);
      border-radius: var(--radius-lg);
      padding: 16px 22px;
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
<div class="im-wrapper">
  <?php require_once __DIR__ . '/navbar.php'; ?>

  <div class="im-main">
    <header class="im-topbar">
      <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
      <div class="im-page-title"><i class="bi bi-box-arrow-in-down me-1"></i> Yuk Qabul</div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle title="Rejim">
          <i class="bi bi-moon-fill"></i>
        </button>
      </div>
    </header>

    <main class="im-content">
      <div class="im-breadcrumb">
        <a href="<?= im_BASE ?>sklad/index.php"><i class="bi bi-house-fill"></i></a>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Yuk Qabul</span>
      </div>

      <!-- Ochiq partiya banneri -->
      <?php if ($ochiq): ?>
      <div id="ochiq-banner">
        <i class="bi bi-box-arrow-in-down" style="font-size:28px"></i>
        <div class="flex-grow-1">
          <div class="fw-bold" style="font-size:15px">
            Ochiq partiya — <?= im_f($ochiq['ps_nomi'] ?: 'Postavshiksiz') ?> 
            <span class="badge bg-primary ms-2"><?= $ochiq['qabul_filial_id'] ? $ochiq['filial_nomi'] : 'Asosiy Sklad' ?></span>
          </div>
          <div style="font-size:12px;opacity:.7">
            <?= im_date($ochiq['sana']) ?> · <?= count($ochiq_items) ?> ta mahsulot ·
            Jami: <strong><?= im_money($ochiq['jami_summa']) ?> so'm</strong>
          </div>
        </div>
        <button class="im-btn im-btn-danger" onclick="bekorQilish(<?= $ochiq['id'] ?>)">
          <i class="bi bi-trash"></i> Bekor qilish
        </button>
        <button class="im-btn im-btn-dark" onclick="yopishModali()">
          <i class="bi bi-check-circle-fill"></i> Yopish
        </button>
      </div>
      <?php endif; ?>

      <div class="row g-3">

        <!-- CHAP: Yangi partiya + Mahsulot qo'shish -->
        <div class="col-lg-7">

          <!-- 1. Yangi partiya ochish -->
          <div class="qabul-step mb-3 im-slide-in">
            <div class="step-header" style="cursor:pointer" onclick="document.getElementById('yangi-partiya-body').classList.toggle('d-none')">
              <div class="step-num">1</div>
              <div class="step-title flex-grow-1">Yangi partiya ochish</div>
              <i class="bi bi-chevron-down"></i>
            </div>
            <div class="im-card-body p-3 <?= $ochiq ? 'd-none' : '' ?>" id="yangi-partiya-body">
              <form id="partiya-form">
                <div class="im-form-row cols-2 mb-3">
                  <div class="im-form-group">
                    <label class="im-label">Postavshik <span style="color:var(--danger)">*</span></label>
                    <select class="im-select" id="ps-id" name="postavshik_id" required>
                      <option value="">— Tanlang —</option>
                      <?php foreach ($postavshiklar as $ps): ?>
                      <option value="<?= $ps['id'] ?>"><?= im_f($ps['nomi']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="im-form-group">
                    <label class="im-label">Qabul sanasi</label>
                    <input class="im-input" type="date" id="p-sana" name="sana"
                           value="<?= date('Y-m-d') ?>">
                  </div>
                </div>
                <div class="im-form-group mb-3">
                  <label class="im-label">Qabul qilinadigan joy (Sklad yoki Do'kon)</label>
                  <select class="im-select" name="qabul_filial_id">
                    <option value="0">Asosiy Skladga</option>
                    <?php foreach ($filiallar_list as $f): ?>
                    <option value="<?= $f['id'] ?>"><?= im_f($f['nomi']) ?> (To'g'ridan-to'g'ri jo'natish)</option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="im-form-group mb-3">
                  <label class="im-label">Faktura / Hujjat raqami</label>
                  <input class="im-input" type="text" id="p-faktura" name="faktura_nomer"
                         placeholder="Ixtiyoriy">
                </div>
                <div class="im-form-group">
                  <label class="im-label">Izoh</label>
                  <textarea class="im-textarea" id="p-izoh" name="izoh"
                            placeholder="Qo'shimcha" rows="2"></textarea>
                </div>
                <div class="mt-3">
                   <button type="button" class="im-btn im-btn-primary im-btn-lg w-100"
                           id="btn-ochish">
                     <i class="bi bi-plus-circle-fill"></i> Partiyani ochish
                   </button>
                </div>
              </form>
          </div><!-- /yangi-partiya-body -->
          </div><!-- /qabul-step 1 -->

          <!-- 2. Mahsulot qo'shish (ochiq partiya uchun) -->
          <?php if ($ochiq): ?>
          <div class="qabul-step mb-3 im-slide-in">
            <div class="step-header">
              <div class="step-num">2</div>
              <div class="step-title">Mahsulot qo'shish</div>
            </div>
            <div class="im-card-body p-3">
              <input type="hidden" id="ochiq-partiya-id" value="<?= $ochiq['id'] ?>">

              <!-- Mahsulot qidirish -->
              <div class="im-form-group mb-3" style="position:relative">
                <label class="im-label">Mahsulot (nom yoki barcode bilan qidiring)</label>
                <div class="im-input-group">
                  <input class="im-input" type="text" id="mah-q" autocomplete="off"
                         placeholder="Mahsulot nomini yoki barcode kiriting...">
                  <button class="im-btn im-btn-dark" id="btn-scan" title="Barcode skaner">
                    <i class="bi bi-upc-scan"></i>
                  </button>
                </div>
                <div id="mah-results" class="mah-search-result" style="display:none"></div>
              </div>

              <!-- Tanlangan mahsulot -->
              <div id="mah-tanlangan" style="display:none" class="mb-3">
                <div class="im-card" style="background:var(--bg)">
                  <div class="im-card-body p-3">
                    <div class="d-flex align-center gap-3 mb-3">
                      <div class="flex-grow-1">
                        <div class="fw-bold" id="t-nomi">—</div>
                        <code class="fs-xs text-muted" id="t-barcode">—</code>
                      </div>
                      <button class="im-btn im-btn-ghost im-btn-icon" id="btn-clear-mah" title="Tozalash">
                        <i class="bi bi-x-lg text-muted"></i>
                      </button>
                    </div>
                    <div class="im-form-row cols-3">
                      <div class="im-form-group">
                        <label class="im-label">Soni *</label>
                        <input class="im-input num" type="number" id="item-soni"
                               min="0.001" step="0.001" value="1" placeholder="0">
                      </div>
                      <div class="im-form-group">
                        <label class="im-label">Kelish narxi (so'm) *</label>
                        <input class="im-input num" type="number" id="item-kelish"
                               min="0" placeholder="0" step="100">
                      </div>
                      <div class="im-form-group">
                        <label class="im-label">Sotish narxi (so'm)</label>
                        <input class="im-input num" type="number" id="item-sotuv"
                               min="0" placeholder="0" step="100">
                      </div>
                    </div>
                    <input type="hidden" id="item-mah-id" value="">
                    <button type="button" class="im-btn im-btn-success w-100 mt-2"
                            id="btn-item-add">
                      <i class="bi bi-plus-circle-fill"></i> Ro'yxatga qo'shish
                    </button>
                  </div>
                </div>
              </div>

              <!-- Mavjud mahsulotlar ro'yxati -->
              <?php if ($ochiq_items): ?>
              <div class="im-card">
                <?php
                $jami = 0;
                foreach ($ochiq_items as $oi) {
                    $jami += $oi['soni'] * $oi['kelish_narxi'];
                }
                ?>
                <div class="im-card-header">
                  <i class="bi bi-list-check" style="color:var(--success)"></i>
                  <span class="im-card-title">Qo'shilgan mahsulotlar</span>
                  <span class="im-badge im-badge-success"><?= count($ochiq_items) ?> ta</span>
                </div>
                <div style="max-height:320px;overflow-y:auto">
                  <?php foreach ($ochiq_items as $oi): ?>
                  <div class="item-card" id="item-row-<?= $oi['id'] ?>">
                    <div class="flex-grow-1">
                      <div class="fw-semibold" style="font-size:13px"><?= im_f($oi['mah_nomi']) ?></div>
                      <div class="text-muted fs-xs">
                        <code><?= im_f($oi['barcode']) ?></code> ·
                        <?= $oi['soni'] ?> <?= 'dona' ?> ×
                        <?= im_money($oi['kelish_narxi']) ?> so'm
                      </div>
                    </div>
                    <div class="text-right">
                      <div class="fw-bold num" style="font-size:13px">
                        <?= im_money($oi['soni'] * $oi['kelish_narxi']) ?> so'm
                      </div>
                      <?php if ($oi['sotish_narxi'] > 0): ?>
                      <div class="text-muted fs-xs">sotuv: <?= im_money($oi['sotish_narxi']) ?></div>
                      <?php endif; ?>
                    </div>
                    <button class="im-btn im-btn-ghost im-btn-icon"
                            onclick="itemDelete(<?= $oi['id'] ?>)" title="O'chirish">
                      <i class="bi bi-trash-fill" style="color:var(--danger)"></i>
                    </button>
                  </div>
                  <?php endforeach; ?>
                </div>
                <div class="im-card-footer d-flex justify-content-between align-items-center">
                  <span class="text-muted fs-sm">Jami (hisoblangan):</span>
                  <span class="fw-bold num" style="font-size:16px" id="jami-summa-display">
                    <?= im_money($jami) ?> so'm
                  </span>
                </div>
              </div>
              <?php else: ?>
              <div class="im-empty">
                <i class="bi bi-box-seam"></i>
                <h4>Hali mahsulot qo'shilmagan</h4>
                <p>Yuqoridagi qidiruvdan mahsulot tanlang</p>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

        </div><!-- /col-lg-7 -->

        <!-- O'NG: Oxirgi partiyalar -->
        <div class="col-lg-5">
          <div class="im-card im-slide-in">
            <div class="im-card-header">
              <i class="bi bi-clock-history" style="color:var(--accent-dark)"></i>
              <span class="im-card-title">Oxirgi partiyalar</span>
            </div>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead>
                  <tr>
                    <th>Asosiy</th>
                    <th class="text-right">Summa</th>
                    <th>Holat</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($partiyalar): ?>
                  <?php foreach ($partiyalar as $p): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold fs-sm"><?= im_f($p['ps_nomi'] ?: '—') ?></div>
                      <div class="text-muted" style="font-size:11px">
                        <?= im_date($p['sana']) ?> · <?= $p['items_soni'] ?> xil
                      </div>
                    </td>
                    <td class="text-right num fw-semibold" style="font-size:12px">
                      <?= im_money($p['jami_summa']) ?>
                    </td>
                    <td>
                      <?php if ($p['holat'] === 'ochiq'): ?>
                        <a href="?ochiq_id=<?= $p['id'] ?>" class="im-btn im-btn-sm im-btn-warning py-1 px-2" style="font-size:11px">
                          Davom etish <i class="bi bi-arrow-right"></i>
                        </a>
                      <?php else: ?>
                        <span class="im-badge im-badge-success">Yopiq</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php else: ?>
                  <tr><td colspan="4">
                    <div class="im-empty" style="padding:30px">
                      <i class="bi bi-inbox"></i>
                      <h4>Bo'sh</h4>
                    </div>
                  </td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- ─── Partiyani yopish modali ─── -->
<div class="im-overlay" id="yopish-modal">
  <div class="im-modal im-modal-lg">
    <div class="im-modal-header">
      <i class="bi bi-check-circle-fill" style="color:var(--success);font-size:20px"></i>
      <span class="im-modal-title">Partiyani yopish va to'lovni belgilash</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <?php if ($ochiq): ?>
      <!-- Xulosa -->
      <div class="im-card mb-3" style="background:var(--bg)">
        <div class="im-card-body p-3">
          <div class="row g-3 text-center">
            <div class="col-4">
              <div class="text-muted fs-xs mb-1">Mahsulot turlari</div>
              <div class="fw-bold" style="font-size:20px"><?= count($ochiq_items) ?></div>
            </div>
            <div class="col-4">
              <div class="text-muted fs-xs mb-1">Jami dona</div>
              <div class="fw-bold" style="font-size:20px">
                <?= array_sum(array_column($ochiq_items, 'soni')) ?>
              </div>
            </div>
            <div class="col-4">
              <div class="text-muted fs-xs mb-1">Jami summa</div>
              <div class="fw-bold num" style="font-size:18px">
                <?= im_money(array_sum(array_map(fn($i)=>$i['soni']*$i['kelish_narxi'], $ochiq_items))) ?> so'm
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Nasiya (qarz) haqida ma'lumot — sklad/admin real kassaga ega emas,
           shuning uchun to'lov turi bu yerda belgilanmaydi -->
      <div class="im-card mb-3" style="background:rgba(226,185,111,.10);border:1px solid rgba(226,185,111,.35)">
        <div class="im-card-body p-3 d-flex gap-2">
          <i class="bi bi-info-circle-fill" style="color:var(--accent-dark);font-size:18px;flex-shrink:0"></i>
          <div class="fs-sm">
            Partiya har doim <strong>to'liq nasiyaga (qarzga)</strong> yopiladi — bu yerda pul to'lanmaydi.
            Haqiqiy to'lovni filial o'z kassasidan (<em>Do'kon → Postavshik qarzlari</em>) yoki
            admin markaz kassasidan (<em>Admin → Qarzlar</em>) amalga oshiradi.
          </div>
        </div>
      </div>

      <div class="im-form-group mb-3">
        <label class="im-label">To'lov muddati (ixtiyoriy)</label>
        <input class="im-input" type="date" id="y-muddat"
               value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
      </div>

      <div class="im-form-group">
        <label class="im-label">Izoh (ixtiyoriy)</label>
        <textarea class="im-textarea" id="y-izoh" rows="2"
                  placeholder="Qo'shimcha izoh..."></textarea>
      </div>
      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-inbox"></i>
        <h4>Ochiq partiya yo'q</h4>
      </div>
      <?php endif; ?>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-success" id="btn-yopish-confirm">
        <i class="bi bi-check-lg"></i> Partiyani yopish
      </button>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
// ─── Partiya ochish ───────────────────────────────────────
const BASE = (window.im_BASE || '/').replace(/\/$/, '');
const AJAX = {
  qabulSave:   BASE + '/sklad/ajax/qabul-save.php',
  itemAdd:     BASE + '/sklad/ajax/qabul-item-add.php',
  itemDelete:  BASE + '/sklad/ajax/qabul-item-delete.php',
  qabulClose:  BASE + '/sklad/ajax/qabul-close.php',
  mahSearch:   BASE + '/sklad/ajax/mah-search.php',
  cancelPart:  BASE + '/sklad/ajax/partiya-cancel.php'
};

// Partiyani ochish
const btnOchish = document.getElementById('btn-ochish');
if (btnOchish) {
  btnOchish.addEventListener('click', async () => {
    const psId = document.getElementById('ps-id')?.value;
    if (!psId) { NHToast.error("Postavshikni tanlang"); return; }

    btnOchish.disabled = true;
    btnOchish.innerHTML = '<span class="im-spinner"></span>';

    const fd = new FormData(document.getElementById('partiya-form'));
    const res = await IMAjax.post(AJAX.qabulSave, fd);

    btnOchish.disabled = false;
    btnOchish.innerHTML = '<i class="bi bi-plus-circle-fill"></i> Partiyani ochish';

    if (res.status === 'ok') {
      NHToast.success(res.msg);
      // Yangi partiya ID bilan redirect — ochiq_id aniq ko'rsatilsin
      setTimeout(() => location.href = (window.im_BASE || '/') + 'sklad/qabul.php?ochiq_id=' + res.data?.id, 500);
    } else {
      NHToast.error(res.msg);
    }
  });
  // Enter tugmasini ham qo'llab-quvvatlash
  document.getElementById('partiya-form')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); btnOchish.click(); }
  });
}

// ─── Mahsulot qidirish ────────────────────────────────────
let selectedMah = null;
const mahQ    = document.getElementById('mah-q');
const mahRes  = document.getElementById('mah-results');
const mahTan  = document.getElementById('mah-tanlangan');
let searchTimer;

if (mahQ) {
  mahQ.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = mahQ.value.trim();
    if (q.length < 2) { mahRes.style.display = 'none'; return; }
    searchTimer = setTimeout(() => doSearch(q), 280);
  });

  mahQ.addEventListener('keydown', e => {
    if (e.key === 'Escape') { mahRes.style.display = 'none'; }
  });
}

async function doSearch(q) {
  // kirim:1 — oraliq (ishlab chiqariladigan) mahsulotlarni ro'yxatdan
  // chiqarib tashlaydi, ular postavshikdan sotib olinmaydi.
  const res = await IMAjax.get(AJAX.mahSearch, { q, kirim: 1 });
  if (res.status !== 'ok' || !res.data.list.length) {
    mahRes.innerHTML = '<div class="item text-muted">Topilmadi</div>';
  } else {
    mahRes.innerHTML = res.data.list.map(m => `
      <div class="item" onclick="selectMah(${JSON.stringify(m).replace(/"/g, '&quot;')})">
        <div class="fw-semibold">${im_esc(m.nomi)}</div>
        <div class="text-muted" style="font-size:11px">
          <code>${im_esc(m.barcode)}</code> · ${im_esc(m.kat_nomi) || '—'} ·
          Sklad: <strong>${m.sklad_qoldiq}</strong> dona
        </div>
      </div>`).join('');
  }
  mahRes.style.display = 'block';
}

function selectMah(m) {
  selectedMah = m;
  mahRes.style.display = 'none';
  mahQ.value = m.nomi;
  document.getElementById('t-nomi').textContent = m.nomi;
  document.getElementById('t-barcode').textContent = m.barcode;
  document.getElementById('item-mah-id').value = m.id;
  document.getElementById('item-sotuv').value = m.sotuv_narx || '';
  mahTan.style.display = 'block';
  document.getElementById('item-soni').focus();
}

document.getElementById('btn-clear-mah')?.addEventListener('click', () => {
  selectedMah = null;
  mahQ.value = '';
  mahTan.style.display = 'none';
  document.getElementById('item-mah-id').value = '';
});

// ─── Item qo'shish ────────────────────────────────────────
document.getElementById('btn-item-add')?.addEventListener('click', async () => {
  const mahId   = document.getElementById('item-mah-id').value;
  const soni    = parseFloat(document.getElementById('item-soni').value) || 0;
  const kelish  = parseFloat(document.getElementById('item-kelish').value) || 0;
  const sotuv   = parseFloat(document.getElementById('item-sotuv').value) || 0;
  const partId  = document.getElementById('ochiq-partiya-id').value;

  if (!mahId) { NHToast.error("Mahsulot tanlanmagan"); return; }
  if (soni <= 0) { NHToast.error("Sonini kiriting"); return; }
  if (kelish < 0) { NHToast.error("Kelish narxini kiriting"); return; }

  const btn = document.getElementById('btn-item-add');
  btn.disabled = true;
  btn.innerHTML = '<span class="im-spinner"></span>';

  const res = await IMAjax.post(AJAX.itemAdd, {
    partiya_id: partId,
    mahsulot_id: mahId,
    soni, kelish_narxi: kelish, sotish_narxi: sotuv
  });

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-plus-circle-fill"></i> Ro\'yxatga qo\'shish';

  if (res.status === 'ok') {
    NHToast.success(res.msg);
    setTimeout(() => location.reload(), 400);
  } else {
    NHToast.error(res.msg);
  }
});

// ─── Item o'chirish ───────────────────────────────────────
async function itemDelete(id) {
  const ok = await NHConfirm.delete("bu qatorni");
  if (!ok) return;
  const res = await IMAjax.post(AJAX.itemDelete, { id });
  if (res.status === 'ok') {
    NHToast.success("O'chirildi");
    const row = document.getElementById(`item-row-${id}`);
    if (row) { row.style.opacity=0; setTimeout(()=>row.remove(),300); }
  } else {
    NHToast.error(res.msg);
  }
}

// ─── Yopish ───────────────────────────────────────────────
function yopishModali() {
  NHModal.open('yopish-modal');
}

async function bekorQilish(id) {
  const ok = await NHConfirm.delete("haqiqatan bu ochiq partiyani bekor qilasizmi? Barcha qo'shilganlar o'chadi");
  if (!ok) return;
  const res = await IMAjax.post(AJAX.cancelPart, { id });
  if (res.status === 'ok') {
    NHToast.success(res.msg);
    setTimeout(() => location.reload(), 400);
  } else {
    NHToast.error(res.msg);
  }
}

document.getElementById('btn-yopish-confirm')?.addEventListener('click', async () => {
  const partId  = document.getElementById('ochiq-partiya-id')?.value;
  const izoh    = document.getElementById('y-izoh')?.value || '';
  const muddat  = document.getElementById('y-muddat')?.value || '';

  if (!partId) { NHToast.error("Ochiq partiya topilmadi"); return; }

  const btn = document.getElementById('btn-yopish-confirm');
  btn.disabled = true;
  btn.innerHTML = '<span class="im-spinner"></span>';

  const res = await IMAjax.post(AJAX.qabulClose, {
    partiya_id: partId,
    muddat,
    izoh
  });

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-lg"></i> Partiyani yopish';

  if (res.status === 'ok') {
    NHToast.success(res.msg);
    NHModal.closeAll();
    setTimeout(() => location.reload(), 600);
  } else {
    NHToast.error(res.msg);
  }
});

// Tashqi click bilan qidiruv yopish
document.addEventListener('click', e => {
  if (!mahQ?.contains(e.target) && !mahRes?.contains(e.target)) {
    mahRes && (mahRes.style.display = 'none');
  }
});
</script>
</body>
</html>
