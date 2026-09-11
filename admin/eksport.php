<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$xodimlar = $db->rows("SELECT id, ism FROM im_xodimlar WHERE status=1 ORDER BY ism");
$today = date('Y-m-d');
$month_start = date('Y-m-01');
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Eksport | IMezon Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
<style>
.eksport-card {
  border: 2px solid var(--border);
  border-radius: var(--radius);
  padding: 20px;
  background: var(--card);
  cursor: pointer;
  transition: all var(--transition);
  display: flex;
  align-items: flex-start;
  gap: 16px;
}
.eksport-card:hover { border-color: var(--accent); box-shadow: 0 4px 20px rgba(226,185,111,.15); }
.eksport-card.active { border-color: var(--accent); background: linear-gradient(135deg,rgba(226,185,111,.07),transparent); }
.eksport-icon { font-size: 32px; line-height: 1; flex-shrink: 0; }
.eksport-title { font-weight: 700; font-size: 15px; margin-bottom: 4px; }
.eksport-desc  { font-size: 12px; color: var(--muted); line-height: 1.5; }
.eksport-cols  { font-size: 11px; color: var(--accent-dark); margin-top: 6px; font-weight: 600; }
.preview-table { font-size: 11.5px; }
.preview-table th { background: var(--primary); color: #fff; font-weight: 600; padding: 7px 10px; }
.preview-table td { padding: 6px 10px; border-bottom: 1px solid var(--border-light); }
</style>
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title"><i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Hisobot &amp; Eksport</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <div class="row g-3">
      <!-- FILTRLAR -->
      <div class="col-12">
        <div class="im-card im-slide-in">
          <div class="im-card-header">
            <i class="bi bi-funnel-fill" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Eksport filtrlari</span>
          </div>
          <div class="im-card-body p-4">
            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <label class="im-label">Boshlanish sanasi</label>
                <input type="date" class="im-input" id="f-dan" value="<?= $month_start ?>">
              </div>
              <div class="col-md-3">
                <label class="im-label">Tugash sanasi</label>
                <input type="date" class="im-input" id="f-gacha" value="<?= $today ?>">
              </div>
              <div class="col-md-3">
                <label class="im-label">Kassir (ixtiyoriy)</label>
                <select class="im-select" id="f-kassir">
                  <option value="">— Barchasi —</option>
                  <?php foreach ($xodimlar as $x): ?>
                  <option value="<?= $x['id'] ?>"><?= im_f($x['ism']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="im-label">Tez tanlash</label>
                <div class="d-flex gap-2 flex-wrap">
                  <button class="im-btn im-btn-outline im-btn-sm quick-btn" data-days="7">7 kun</button>
                  <button class="im-btn im-btn-outline im-btn-sm quick-btn" data-days="30">30 kun</button>
                  <button class="im-btn im-btn-outline im-btn-sm quick-btn" data-month="1">Bu oy</button>
                  <button class="im-btn im-btn-outline im-btn-sm quick-btn" data-month="0">O'tgan oy</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- HISOBOT TURLARI -->
      <div class="col-12">
        <div class="im-card im-slide-in">
          <div class="im-card-header">
            <i class="bi bi-grid-fill" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Yuklab olish</span>
          </div>
          <div class="im-card-body p-4">
            <div class="row g-3">
              <?php
              $report_types = [
                [
                  'key' => 'sotuvlar',
                  'icon' => '🛍️',
                  'title' => 'Sotuvlar jurnali',
                  'desc' => 'Barcha sotuvlar: chek raqami, sana, kassir, mijoz, to\'lov turi va summasi',
                  'cols' => 'Chek, Sana, Kassir, Mijoz, Naqd, ' . im_tt_nomi('karta', true) . ', Nasiya, Jami',
                ],
                [
                  'key' => 'sotuv_items',
                  'icon' => '📦',
                  'title' => 'Sotuv itemlari',
                  'desc' => 'Har bir sotilgan mahsulot: nomi, soni, narxi, chegirma, chek raqami',
                  'cols' => 'Chek, Sana, Mahsulot, Soni, Narx, Chegirma%, Jami',
                ],
                [
                  'key' => 'nasiyalar',
                  'icon' => '📋',
                  'title' => 'Nasiyalar',
                  'desc' => 'Aktiv va to\'langan nasiyalar: mijoz, summa, qoldiq, qaytarish sanasi',
                  'cols' => 'Mijoz, Telefon, Summa, To\'langan, Qoldiq, Sana, Holat',
                ],
                [
                  'key' => 'harajatlar',
                  'icon' => '💸',
                  'title' => 'Harajatlar',
                  'desc' => 'Barcha xarajatlar: kategoriya, summa, izoh, sanasi',
                  'cols' => 'Sana, Kategoriya, Izoh, Summa, Kassir',
                ],
                [
                  'key' => 'mahsulotlar',
                  'icon' => '🏷️',
                  'title' => 'Mahsulotlar ro\'yxati',
                  'desc' => 'Barcha mahsulotlar: nomi, kategoriya, narx, ulgurji narx, sklad qoldig\'i',
                  'cols' => 'Nomi, Barcode, Kategoriya, Narx, Ulg.narx, Qoldiq, Birlik',
                ],
                [
                  'key' => 'mijozlar',
                  'icon' => '👥',
                  'title' => 'Mijozlar bazasi',
                  'desc' => 'Mijozlar: ism, telefon, toifa, jami xarid, nasiya qoldig\'i',
                  'cols' => 'Ism, Telefon, Toifa, Jami xarid, Nasiya, Manzil',
                ],
              ];
              foreach ($report_types as $rt): ?>
              <div class="col-md-4">
                <div class="h-100 d-flex flex-column">
                  <div class="eksport-card flex-grow-1 mb-2">
                    <div class="eksport-icon"><?= $rt['icon'] ?></div>
                    <div>
                      <div class="eksport-title"><?= im_f($rt['title']) ?></div>
                      <div class="eksport-desc"><?= im_f($rt['desc']) ?></div>
                      <div class="eksport-cols">Ustunlar: <?= im_f($rt['cols']) ?></div>
                    </div>
                  </div>
                  <button class="im-btn im-btn-success w-100 btn-eksport" data-type="<?= $rt['key'] ?>">
                    <i class="bi bi-download"></i> CSV yuklab olish
                  </button>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- STATISTIKA SUMMARY -->
      <div class="col-12">
        <div class="im-card im-slide-in">
          <div class="im-card-header">
            <i class="bi bi-bar-chart-fill" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Joriy davr statistikasi</span>
            <button class="im-btn im-btn-outline im-btn-sm ms-auto" id="btn-refresh-stats">
              <i class="bi bi-arrow-clockwise"></i> Yangilash
            </button>
          </div>
          <div class="im-card-body p-4" id="stats-body">
            <div class="text-center text-muted py-3">
              <span class="im-spinner"></span> Yuklanmoqda...
            </div>
          </div>
        </div>
      </div>
    </div>

  </main>
</div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
// ── Tez sana tanlash
document.querySelectorAll('.quick-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const now = new Date();
    let dan, gacha = now.toISOString().slice(0,10);
    if (btn.dataset.days) {
      const d = new Date(); d.setDate(d.getDate() - parseInt(btn.dataset.days));
      dan = d.toISOString().slice(0,10);
    } else if (btn.dataset.month === '1') {
      dan = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0,10);
    } else {
      const m = now.getMonth() - 1;
      const y = now.getFullYear() + (m < 0 ? -1 : 0);
      const mm = ((m + 12) % 12);
      dan   = new Date(y, mm, 1).toISOString().slice(0,10);
      gacha = new Date(y, mm + 1, 0).toISOString().slice(0,10);
    }
    document.getElementById('f-dan').value = dan;
    document.getElementById('f-gacha').value = gacha;
    loadStats();
  });
});

// ── Eksport tugmasi
document.querySelectorAll('.btn-eksport').forEach(btn => {
  btn.addEventListener('click', () => {
    const type   = btn.dataset.type;
    const dan    = document.getElementById('f-dan').value;
    const gacha  = document.getElementById('f-gacha').value;
    const kassir = document.getElementById('f-kassir').value;

    if (!dan || !gacha) { NHToast.warning('Sanani tanlang'); return; }

    const url = `${(window.im_BASE || '/')}admin/ajax/eksport-csv.php?type=${type}&dan=${dan}&gacha=${gacha}&kassir=${kassir}`;
    // CSV ni yuklab olish
    const a = document.createElement('a');
    a.href = url;
    a.download = '';
    document.body.appendChild(a);
    a.click();
    a.remove();
    NHToast.success(`"${btn.closest('.col-md-4').querySelector('.eksport-title').textContent}" yuklanmoqda...`);
  });
});

// ── Statistika yuklash
async function loadStats() {
  const dan   = document.getElementById('f-dan').value;
  const gacha = document.getElementById('f-gacha').value;
  const kassir= document.getElementById('f-kassir').value;
  const body  = document.getElementById('stats-body');
  body.innerHTML = '<div class="text-center py-3"><span class="im-spinner"></span></div>';

  const res = await IMAjax.get(window.im_BASE + 'admin/ajax/eksport-stats.php', { dan, gacha, kassir });
  if (res.status !== 'ok') { body.innerHTML = '<p class="text-muted text-center">Ma\'lumot topilmadi</p>'; return; }
  const d = res.data;

  body.innerHTML = `
    <div class="row g-3 mb-4">
      <div class="col-md-3 col-6">
        <div class="im-card p-3 text-center">
          <div class="text-muted fs-xs">Jami sotuv</div>
          <div class="fw-bold fs-5 num">${d.sotuv_soni} ta</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="im-card p-3 text-center">
          <div class="text-muted fs-xs">Jami summa</div>
          <div class="fw-bold fs-5 num" style="color:var(--accent-dark)">${Number(d.jami_summa).toLocaleString()} so'm</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="im-card p-3 text-center">
          <div class="text-muted fs-xs">Chegirma</div>
          <div class="fw-bold fs-5 num" style="color:var(--danger)">-${Number(d.chegirma).toLocaleString()} so'm</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="im-card p-3 text-center">
          <div class="text-muted fs-xs">Harajatlar</div>
          <div class="fw-bold fs-5 num" style="color:var(--muted)">${Number(d.harajat).toLocaleString()} so'm</div>
        </div>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-md-4 col-6">
        <div class="im-card p-3 text-center">
          <div class="text-muted fs-xs">💵 Naqd</div>
          <div class="fw-bold num">${Number(d.naqd).toLocaleString()} so'm</div>
        </div>
      </div>
      <div class="col-md-4 col-6">
        <div class="im-card p-3 text-center">
          <div class="text-muted fs-xs">${IM_TT_LABELS_SHORT.karta}</div>
          <div class="fw-bold num">${Number(d.karta).toLocaleString()} so'm</div>
        </div>
      </div>
      <div class="col-md-4 col-12">
        <div class="im-card p-3 text-center">
          <div class="text-muted fs-xs">📋 Nasiya</div>
          <div class="fw-bold num">${Number(d.nasiya).toLocaleString()} so'm</div>
        </div>
      </div>
    </div>
    ${d.top_mahsulotlar?.length ? `
    <div class="mt-4">
      <div class="fw-bold mb-2 fs-sm">🏆 Top 5 sotilgan mahsulot</div>
      <table class="preview-table w-100" style="border-collapse:collapse;border-radius:8px;overflow:hidden">
        <thead><tr><th>#</th><th>Mahsulot</th><th>Soni</th><th>Summa</th></tr></thead>
        <tbody>
          ${d.top_mahsulotlar.map((m,i) => `<tr>
            <td>${i+1}</td>
            <td>${im_esc(m.nomi)}</td>
            <td class="num">${m.jami_soni} dona</td>
            <td class="num">${Number(m.jami_summa).toLocaleString()} so'm</td>
          </tr>`).join('')}
        </tbody>
      </table>
    </div>` : ''}
  `;
}

document.getElementById('btn-refresh-stats').addEventListener('click', loadStats);
document.getElementById('f-dan').addEventListener('change', loadStats);
document.getElementById('f-gacha').addEventListener('change', loadStats);
document.getElementById('f-kassir').addEventListener('change', loadStats);

// Sahifa ochilganda yuklash
loadStats();
</script>
</body>
</html>
