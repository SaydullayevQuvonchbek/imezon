<?php
// ============================================================
//  IMezon — Sklad Hisobot (Partiya hisoboti + Jo'natmalar)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'sklad']);

$db = new Cyber();

// Umumiy sklad qoldig'i
$jami_mah_soni = (int)$db->val("SELECT COUNT(DISTINCT mahsulot_id) FROM im_fifo_layers WHERE location_id=0 AND cancelled=0 AND remaining_qty>0");
$jami_dona = (float)$db->val("SELECT SUM(remaining_qty) FROM im_fifo_layers WHERE location_id=0 AND cancelled=0 AND remaining_qty>0");
$jami_summa = (float)$db->val("SELECT SUM(remaining_qty * unit_cost) FROM im_fifo_layers WHERE location_id=0 AND cancelled=0 AND remaining_qty>0");

// Eng ko'p qoldiq
$top_qoldiq = $db->rows("
    SELECT m.nomi, m.barcode, SUM(pi.remaining_qty) as soni, SUM(pi.remaining_qty*pi.unit_cost) as summa
    FROM im_fifo_layers pi
    JOIN im_mahsulotlar m ON m.id=pi.mahsulot_id
    WHERE pi.location_id=0 AND pi.cancelled=0 AND pi.remaining_qty > 0
    GROUP BY m.id
    ORDER BY soni DESC LIMIT 20
");

// Oxirgi jo'natmalar
$send_tarix = $db->rows("
    SELECT ss.*, m.nomi, m.barcode, f.nomi as filial_nomi, x.ism as kuser
    FROM im_sklad_send ss
    JOIN im_mahsulotlar m ON m.id=ss.mahsulot_id
    LEFT JOIN im_filiallar f ON f.id=ss.filial_id
    LEFT JOIN im_xodimlar x ON x.id=ss.xodim_id
    ORDER BY ss.sana DESC LIMIT 30
");

// ── PARTIYA HISOBOTI ──────────────────────────────────────
// Filtr
$filtr_ps = (int)($_GET['ps'] ?? 0);
$filtr_dan = $_GET['dan'] ?? date('Y-m-01');
$filtr_gacha = $_GET['gacha'] ?? date('Y-m-d');
$fd = mysqli_real_escape_string($link, $filtr_dan);
$fg = mysqli_real_escape_string($link, $filtr_gacha);

$ps_where = "p.sana BETWEEN '$fd' AND '$fg'";
if ($filtr_ps) $ps_where .= " AND p.postavshik_id=$filtr_ps";

// Partiyalar ro'yxati
$partiyalar = $db->rows("
    SELECT p.id, p.sana, p.holat, p.izoh, p.faktura_nomer,
           ps.nomi AS postavshik,
           COUNT(pi.id) AS mahsulot_tur,
           SUM(pi.soni) AS jami_soni,
           SUM(pi.kelish_narxi * pi.soni) AS jami_summa,
           SUM(COALESCE((SELECT SUM(fl.remaining_qty) FROM im_fifo_layers fl WHERE fl.partiya_item_id=pi.id AND fl.location_id=0 AND fl.cancelled=0 AND fl.remaining_qty>0),0)) AS qoldiq,
           x.ism AS sklad_xodim,
           f.nomi AS filial_nomi
    FROM im_partiyalar p
    LEFT JOIN im_postavshiklar ps ON ps.id=p.postavshik_id
    LEFT JOIN im_partiya_items pi ON pi.partiya_id=p.id
    LEFT JOIN im_xodimlar x ON x.id=p.xodim_id
    LEFT JOIN im_filiallar f ON f.id=p.qabul_filial_id
    WHERE $ps_where
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT 50
");

// Postavshiklar
$postavshiklar = $db->rows("SELECT id, nomi FROM im_postavshiklar ORDER BY nomi");

$page_title = 'Sklad Hisoboti';
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
      <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
      <div class="im-page-title"><i class="bi bi-bar-chart-fill me-1"></i> Hisobot</div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
      </div>
    </header>

    <main class="im-content">
      <div class="im-breadcrumb">
        <a href="<?= im_BASE ?>sklad/index.php"><i class="bi bi-house-fill"></i></a>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Hisobot</span>
      </div>

      <!-- Umumiy stat -->
      <div class="row g-3 mb-4 mt-1">
        <div class="col-md-4">
          <div class="im-card h-100" style="border-left:4px solid var(--primary)">
            <div class="im-card-body p-3">
              <div class="text-muted fs-sm mb-1">Markaziy Skladdagi Turlar</div>
              <div class="fw-bold" style="font-size:24px"><?= $jami_mah_soni ?> xil</div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="im-card h-100" style="border-left:4px solid var(--accent)">
            <div class="im-card-body p-3">
              <div class="text-muted fs-sm mb-1">Jami Qoldiq (Dona)</div>
              <div class="fw-bold num" style="color:var(--accent-dark);font-size:24px"><?= $jami_dona ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="im-card h-100" style="border-left:4px solid var(--success)">
            <div class="im-card-body p-3">
              <div class="text-muted fs-sm mb-1">Sklad Jami Summasi (Tannarx)</div>
              <div class="fw-bold num text-success" style="font-size:24px"><?= im_money($jami_summa) ?> so'm</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── PARTIYA HISOBOTI ─────────────────────────────────── -->
      <div class="im-card mb-4">
        <div class="im-card-header">
          <i class="bi bi-box-seam-fill" style="color:var(--accent-dark)"></i>
          <span class="im-card-title">Partiya Hisoboti</span>
          <span class="im-badge im-badge-muted ms-1">Nima keldi, kimdan, qayerga</span>
        </div>
        <div class="im-card-body p-3">
          <form method="GET" class="d-flex gap-2 flex-wrap align-items-end mb-3">
            <div>
              <label class="im-label fs-xs">Dan</label>
              <input type="date" name="dan" class="im-input im-input-sm" value="<?= $filtr_dan ?>" style="max-width:140px">
            </div>
            <div>
              <label class="im-label fs-xs">Gacha</label>
              <input type="date" name="gacha" class="im-input im-input-sm" value="<?= $filtr_gacha ?>" style="max-width:140px">
            </div>
            <div>
              <label class="im-label fs-xs">Postavshik</label>
              <select name="ps" class="im-select im-input-sm" style="max-width:160px">
                <option value="">— Hammasi —</option>
                <?php foreach ($postavshiklar as $ps): ?>
                <option value="<?= $ps['id'] ?>" <?= $filtr_ps==$ps['id']?'selected':'' ?>><?= im_f($ps['nomi']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="im-btn im-btn-primary im-btn-sm"><i class="bi bi-funnel"></i> Ko'rsat</button>
            <a href="?" class="im-btn im-btn-outline im-btn-sm">Tozalash</a>
          </form>

          <?php if ($partiyalar): ?>
          <div class="im-table-wrap">
            <table class="im-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Sana</th>
                  <th>Postavshik</th>
                  <th>Filial / Joy</th>
                  <th class="text-center">Mahsulot tur</th>
                  <th class="text-right">Jami dona</th>
                  <th class="text-right">Jami summa</th>
                  <th class="text-right">Sklad qoldi</th>
                  <th>Xodim</th>
                  <th>Holat</th>
                  <th>Izoh</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($partiyalar as $p): ?>
                <tr class="partiya-row" onclick="openPartiyaDetail(<?= $p['id'] ?>)"
                    style="cursor:pointer" title="Mahsulotlarni ko'rish uchun bosing">
                  <td>
                    <code class="fs-xs">#<?= $p['id'] ?></code>
                    <?php if ($p['faktura_nomer']): ?>
                    <div class="text-muted" style="font-size:10px"><?= im_f($p['faktura_nomer']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted fs-xs"><?= im_date($p['sana']) ?></td>
                  <td class="fw-semibold">
                    <?php if ($p['postavshik']): ?>
                      <span class="im-badge" style="background:#e3f2fd;color:#1565c0"><?= im_f($p['postavshik']) ?></span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($p['filial_nomi']): ?>
                      <span class="im-badge im-badge-primary"><?= im_f($p['filial_nomi']) ?></span>
                    <?php else: ?>
                      <span class="text-muted fs-xs">Markaziy sklad</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center"><span class="im-badge im-badge-muted"><?= (int)$p['mahsulot_tur'] ?> xil</span></td>
                  <td class="text-right fw-bold"><?= im_money($p['jami_soni']) ?> dona</td>
                  <td class="text-right num fw-bold" style="color:var(--accent-dark)"><?= im_money($p['jami_summa']) ?> so'm</td>
                  <td class="text-right">
                    <?php $q = (int)$p['qoldiq']; ?>
                    <span class="fw-bold <?= $q > 0 ? 'text-success' : 'text-muted' ?>"><?= $q ?> dona</span>
                  </td>
                  <td class="text-muted fs-xs"><?= im_f($p['sklad_xodim'] ?: '—') ?></td>
                  <td>
                    <?php if ($p['holat'] === 'yopiq' || $p['holat'] === 'qabul_qilindi'): ?>
                      <span class="im-badge im-badge-success fs-xs">✅ Qabul</span>
                    <?php elseif ($p['holat'] === 'ochiq'): ?>
                      <span class="im-badge im-badge-warning fs-xs">⏳ Ochiq</span>
                    <?php elseif ($p['holat'] === 'bekor'): ?>
                      <span class="im-badge im-badge-danger fs-xs">❌ Bekor</span>
                    <?php else: ?>
                      <span class="im-badge im-badge-muted fs-xs"><?= im_f($p['holat']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted fs-xs" style="max-width:120px;overflow:hidden;text-overflow:ellipsis"><?= im_f($p['izoh'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot style="background:var(--bg)">
                <tr>
                  <td colspan="5" class="fw-bold">Jami (<?= count($partiyalar) ?> ta partiya)</td>
                  <td class="text-right fw-bold"><?= im_money(array_sum(array_column($partiyalar,'jami_soni'))) ?> dona</td>
                  <td class="text-right fw-bold num" style="color:var(--accent-dark)"><?= im_money(array_sum(array_column($partiyalar,'jami_summa'))) ?> so'm</td>
                  <td colspan="4"></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <?php else: ?>
          <div class="im-empty"><i class="bi bi-box-seam"></i><h4>Partiya topilmadi</h4><p class="text-muted">Tanlangan davr uchun partiya yo'q</p></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Qoldiq va Jo'natmalar -->
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="im-card">
            <div class="im-card-header">
              <i class="bi bi-graph-up-arrow"></i>
              <span class="im-card-title">Top qoldiqlar (Markaziy sklad)</span>
            </div>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead><tr><th>Mahsulot</th><th class="text-center">Dona</th><th class="text-right">Summa</th></tr></thead>
                <tbody>
                  <?php foreach ($top_qoldiq as $t): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold fs-sm"><?= im_f($t['nomi']) ?></div>
                      <div class="text-muted" style="font-size:10px"><?= im_f($t['barcode']) ?></div>
                    </td>
                    <td class="text-center fw-bold text-primary"><?= (int)$t['soni'] ?></td>
                    <td class="text-right num fw-semibold"><?= im_money($t['summa']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <div class="col-lg-6">
          <div class="im-card">
            <div class="im-card-header">
              <i class="bi bi-truck"></i>
              <span class="im-card-title">Oxirgi jo'natmalar (Filiallarga)</span>
            </div>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead><tr><th>Vaqt</th><th>Mahsulot</th><th>Filial</th><th>Soni</th></tr></thead>
                <tbody>
                  <?php if ($send_tarix): ?>
                  <?php foreach ($send_tarix as $s): ?>
                  <tr>
                    <td class="text-muted fs-xs"><?= date('d.m H:i', strtotime($s['sana'])) ?></td>
                    <td>
                      <div class="fw-semibold fs-sm"><?= im_f($s['nomi']) ?></div>
                      <div class="text-muted" style="font-size:10px">Yuborgan: <?= im_f($s['kuser']?:'—') ?></div>
                    </td>
                    <td><span class="im-badge im-badge-primary"><?= im_f($s['filial_nomi']?:'—') ?></span></td>
                    <td class="fw-bold num text-success">+<?= (int)$s['soni'] ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">Hech qanday jo'natma yo'q</td></tr>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>

<!-- Partiya Detail Modal -->
<div id="partiya-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);align-items:center;justify-content:center">
  <div class="im-card" style="width:900px;max-width:97vw;max-height:92vh;overflow-y:auto">
    <div class="im-card-header" style="background:var(--primary);position:sticky;top:0;z-index:1">
      <i class="bi bi-box-seam-fill" style="color:var(--accent)"></i>
      <span class="im-card-title" style="color:#fff" id="pm-title">Partiya mahsulotlari</span>
      <button class="im-btn im-btn-danger im-btn-sm ms-auto" id="pm-bekor" style="display:none"
              onclick="partiyaBekor()">
        <i class="bi bi-x-octagon"></i> Qabulni bekor qilish
      </button>
      <button class="im-btn im-btn-icon im-btn-ghost" onclick="closePartiyaDetail()" style="color:#fff">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="im-card-body p-3" id="pm-body">
      <div class="text-center p-5 text-muted"><i class="bi bi-hourglass-split fs-3"></i><br>Yuklanmoqda...</div>
    </div>
  </div>
</div>

<script>
async function openPartiyaDetail(id) {
  document.getElementById('partiya-modal').style.display = 'flex';
  var body  = document.getElementById('pm-body');
  var title = document.getElementById('pm-title');
  body.innerHTML = '<div class="text-center p-5 text-muted"><i class="bi bi-hourglass-split fs-3"></i><br>Yuklanmoqda...</div>';

  try {
    var r = await fetch(window.im_BASE + 'admin/ajax/partiya-detail.php?partiya_id=' + id);
    var j = await r.json();
    if (j.status !== 'ok') {
      body.innerHTML = '<div class="im-empty"><p class="text-danger">' + (j.msg||'Xatolik') + '</p></div>';
      return;
    }
    var p = j.data.partiya, items = j.data.items;
    title.textContent = 'Partiya #' + id + (p.faktura ? ' — ' + p.faktura : '') + (p.ps_nomi ? ' (' + p.ps_nomi + ')' : '');
    // Bekor qilish faqat YOPILGAN partiya uchun. Server yana bir bor tekshiradi:
    // birorta qatlamdan sarflangan bo'lsa yoki qarz to'langan bo'lsa — rad etiladi.
    PM_ID = id;
    document.getElementById('pm-bekor').style.display = (p.holat === 'yopiq') ? '' : 'none';

    var html = '<div class="row g-2 mb-3 p-2" style="background:var(--bg);border-radius:8px;border:1px solid var(--border)">';
    html += '<div class="col-md-3"><div class="text-muted fs-xs">Postavshik</div><div class="fw-semibold">' + (p.ps_nomi||'—') + '</div></div>';
    html += '<div class="col-md-2"><div class="text-muted fs-xs">Sana</div><div>' + new Date(p.sana).toLocaleDateString('uz-UZ') + '</div></div>';
    html += '<div class="col-md-2"><div class="text-muted fs-xs">Filial/Joy</div><div>' + (p.filial||'Markaziy sklad') + '</div></div>';
    html += '<div class="col-md-2"><div class="text-muted fs-xs">Jami summa</div><div class="fw-bold num" style="color:var(--accent-dark)">' + p.jami_fmt + '</div></div>';
    html += '<div class="col-md-3"><div class="text-muted fs-xs">Holat</div><div><span class="im-badge im-badge-' + (p.holat==='yopiq'?'success':'warning') + '">' + (p.holat==='yopiq'?'✅ Qabul':'⏳ Ochiq') + '</span></div></div>';
    html += '</div>';

    if (!items || items.length === 0) {
      html += '<div class="im-empty"><i class="bi bi-box-seam"></i><h4>Mahsulot yo\'q</h4></div>';
    } else {
      html += '<div class="im-table-wrap"><table class="im-table" style="font-size:12.5px">';
      html += '<thead><tr><th>#</th><th>Mahsulot</th><th>Kategoriya</th><th class="text-right">Soni</th><th class="text-right">Kirim narx</th><th class="text-right">Sotish narx</th><th class="text-right">Sklad qoldi</th><th class="text-right">Jami (tan)</th></tr></thead><tbody>';
      var totalDona = 0, totalQoldi = 0;
      items.forEach(function(it, i) {
        totalDona  += Number(it.soni)||0;
        totalQoldi += Number(it.sklad_qoldi)||0;
        html += '<tr>';
        html += '<td class="text-muted fs-xs">' + (i+1) + '</td>';
        html += '<td><div class="fw-semibold">' + (it.mahsulot||'—') + '</div>' + (it.barcode ? '<div class="text-muted fs-xs">' + it.barcode + '</div>' : '') + '</td>';
        html += '<td class="text-muted fs-xs">' + (it.kategoriya||'—') + '</td>';
        html += '<td class="text-right num fw-bold">' + (it.soni||0) + '</td>';
        html += '<td class="text-right num">' + (it.narx_fmt||'—') + '</td>';
        html += '<td class="text-right num" style="color:var(--success)">' + (it.sotish_fmt||'—') + '</td>';
        html += '<td class="text-right num fw-bold" style="color:' + ((Number(it.sklad_qoldi||0)>0)?'var(--primary)':'#999') + '">' + (it.sklad_qoldi||0) + ' dona</td>';
        html += '<td class="text-right num fw-bold">' + (it.jami_fmt||'—') + '</td>';
        html += '</tr>';
      });
      html += '<tr style="background:var(--accent-light,#fdf8ee)">';
      html += '<td colspan="3" class="fw-bold">Jami (' + items.length + ' xil)</td>';
      html += '<td class="text-right num fw-bold">' + totalDona + ' dona</td>';
      html += '<td colspan="2"></td>';
      html += '<td class="text-right num fw-bold" style="color:var(--primary)">' + totalQoldi + ' dona</td>';
      html += '<td class="text-right num fw-bold" style="color:var(--accent-dark)">' + p.jami_fmt + '</td>';
      html += '</tr></tbody></table></div>';
    }
    body.innerHTML = html;
  } catch(e) {
    body.innerHTML = '<div class="im-empty"><p class="text-danger">Tarmoq xatosi. Qayta urinib ko\'ring.</p></div>';
  }
}

function closePartiyaDetail() {
  document.getElementById('partiya-modal').style.display = 'none';
}

var PM_ID = 0;
async function partiyaBekor() {
  if (!PM_ID) return;
  var ok = await NHConfirm.show({
    variant: 'danger',
    title: 'Qabulni bekor qilish',
    text: 'Partiya #' + PM_ID + ' qoldiqdan butunlay olib tashlanadi.',
    sub: 'Faqat hech bir mahsuloti sarflanmagan va postavshikka to‘lov qilinmagan bo‘lsa mumkin. '
       + 'Aks holda inventarizatsiya qiling.',
    confirmText: 'Ha, bekor qilinsin',
    btnIcon: 'bi-x-octagon'
  });
  if (!ok) return;
  var res = await IMAjax.post(window.im_BASE + 'sklad/ajax/partiya-bekor.php', { id: PM_ID });
  if (res.status === 'ok') {
    NHToast.show(res.msg, 'success', 5000);
    closePartiyaDetail();
    setTimeout(function(){ location.reload(); }, 1000);
  } else {
    NHToast.show(res.msg || 'Xatolik', 'error', 8000);
  }
}

document.getElementById('partiya-modal').addEventListener('click', function(e) {
  if (e.target === this) closePartiyaDetail();
});
</script>
</body>
</html>
