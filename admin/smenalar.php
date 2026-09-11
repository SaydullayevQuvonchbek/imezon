<?php
// ============================================================
//  IMezon — Smenalar tarixi
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin','kassir','bosh_kassir']);
$db = new Cyber();

// Filter
$filter_holat = $_GET['holat'] ?? '';
$filter_sana  = $_GET['sana']  ?? '';
$page  = max(1, (int)($_GET['p'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = '1=1';
if ($filter_holat && in_array($filter_holat, ['ochiq','yopiq'])) {
    $where .= " AND s.holat='$filter_holat'";
}
if ($filter_sana) {
    $sd = mysqli_real_escape_string($link, $filter_sana);
    $where .= " AND s.sana='$sd'";
}

// Faqat kassir o'z smenalarini ko'radi, admin hammasini
if ($im_rol !== 'admin') {
    $where .= " AND s.kassir_id=$im_user_id";
}

$total = (int)$db->val("SELECT COUNT(*) FROM im_smena s WHERE $where");
$smenalar = $db->rows(
    "SELECT s.*,
            x.ism AS kassir_ism,
            (SELECT COUNT(*) FROM im_sotuvlar WHERE smena_id=s.id) AS sotuv_soni,
            (SELECT COALESCE(SUM(tolov_summa),0) FROM im_sotuvlar WHERE smena_id=s.id) AS jami_tushum,
            (SELECT COALESCE(SUM(naqd_summa),0) FROM im_sotuvlar WHERE smena_id=s.id) AS naqd_tushum,
            (SELECT COALESCE(SUM(karta_summa),0) FROM im_sotuvlar WHERE smena_id=s.id) AS karta_tushum,
            (SELECT COALESCE(SUM(bank_summa),0) FROM im_sotuvlar WHERE smena_id=s.id) AS bank_tushum,
            (SELECT COALESCE(SUM(usd_summa),0) FROM im_sotuvlar WHERE smena_id=s.id) AS usd_tushum,
            (SELECT COALESCE(SUM(nasiya_summa),0) FROM im_sotuvlar WHERE smena_id=s.id) AS nasiya_tushum,
            (SELECT COALESCE(SUM(usd_qaytim_som),0) FROM im_sotuvlar WHERE smena_id=s.id) AS usd_qaytim_jami,
            (SELECT COALESCE(SUM(naqd_berildi),0) FROM im_sotuvlar WHERE smena_id=s.id) AS naqd_qaytim_jami,
            (SELECT COALESCE(SUM(v.qaytarish_summa),0) FROM im_vozvratlar v JOIN im_sotuvlar sv ON sv.id=v.sotuv_id WHERE sv.smena_id=s.id) AS vozvrat_jami,
            (SELECT COALESCE(SUM(summa),0) FROM im_inkasasiya WHERE xodim_id=s.kassir_id AND sana >= s.ochildi AND (s.yopildi IS NULL OR sana <= s.yopildi)) AS inkasso_jami
     FROM im_smena s
     LEFT JOIN im_xodimlar x ON x.id=s.kassir_id
     WHERE $where
     ORDER BY s.id DESC
     LIMIT $limit OFFSET $offset"
);
$pages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smenalar tarixi | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-clock-history me-1"></i> Smenalar tarixi</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- Stat kartalar -->
    <?php
    $stat = $db->row(
        "SELECT COUNT(*) AS jami,
                SUM(holat='yopiq') AS yopiq,
                SUM(holat='ochiq') AS ochiq
         FROM im_smena"
        . ($im_rol !== 'admin' ? " WHERE kassir_id=$im_user_id" : "")
    );
    $jami_tushum_oy = (float)$db->val(
        "SELECT COALESCE(SUM(tolov_summa),0) FROM im_sotuvlar s
         JOIN im_smena sm ON sm.id=s.smena_id
         WHERE MONTH(sm.sana)=MONTH(CURDATE()) AND YEAR(sm.sana)=YEAR(CURDATE())"
        . ($im_rol !== 'admin' ? " AND sm.kassir_id=$im_user_id" : "")
    );
    ?>
    <div class="row g-3 mb-4">
      <div class="col-md-3 col-6">
        <div class="im-stat-card" style="border-left:4px solid var(--accent-dark)">
          <div class="im-stat-icon" style="background:rgba(226,185,111,.12);color:var(--accent-dark)"><i class="bi bi-clock-history"></i></div>
          <div>
            <div class="im-stat-label">Jami smenalar</div>
            <div class="im-stat-value"><?= (int)$stat['jami'] ?> ta</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i class="bi bi-check-circle-fill"></i></div>
          <div>
            <div class="im-stat-label">Yopilgan</div>
            <div class="im-stat-value"><?= (int)$stat['yopiq'] ?> ta</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="im-stat-card" style="border-left:4px solid var(--warning)">
          <div class="im-stat-icon" style="background:rgba(255,193,7,.12);color:var(--warning)"><i class="bi bi-play-circle-fill"></i></div>
          <div>
            <div class="im-stat-label">Ochiq</div>
            <div class="im-stat-value"><?= (int)$stat['ochiq'] ?> ta</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="im-stat-card" style="border-left:4px solid var(--primary)">
          <div class="im-stat-icon" style="background:rgba(26,26,46,.08);color:var(--primary)"><i class="bi bi-cash-stack"></i></div>
          <div>
            <div class="im-stat-label">Bu oy tushum</div>
            <div class="im-stat-value num"><?= im_money($jami_tushum_oy) ?> so'm</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter -->
    <div class="im-card mb-4">
      <div class="im-card-body p-3">
        <form method="GET" class="d-flex gap-3 flex-wrap align-items-end">
          <div>
            <label class="im-label fs-xs">Holat</label>
            <select name="holat" class="im-input im-input-sm" style="min-width:120px">
              <option value="">Hammasi</option>
              <option value="ochiq"  <?= $filter_holat==='ochiq'  ? 'selected':'' ?>>Ochiq</option>
              <option value="yopiq" <?= $filter_holat==='yopiq' ? 'selected':'' ?>>Yopilgan</option>
            </select>
          </div>
          <div>
            <label class="im-label fs-xs">Sana</label>
            <input type="date" name="sana" class="im-input im-input-sm" value="<?= im_f($filter_sana) ?>">
          </div>
          <button type="submit" class="im-btn im-btn-primary im-btn-sm"><i class="bi bi-funnel"></i> Filter</button>
          <a href="?" class="im-btn im-btn-outline im-btn-sm">Tozalash</a>
        </form>
      </div>
    </div>

    <!-- Smenalar jadvali -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-clock-history" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Smenalar ro'yxati</span>
        <span class="im-badge im-badge-muted ms-1"><?= $total ?> ta</span>
      </div>
      <?php if ($smenalar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Kassir</th>
              <th>Sana</th>
              <th>Boshlandi</th>
              <th>Yopildi</th>
              <th class="text-center">Sotuv</th>
              <th class="text-right">Naqd</th>
              <th class="text-right"><?= im_tt_label('karta', true) ?></th>
              <th class="text-right"><?= im_tt_label('bank', true) ?></th>
              <th class="text-right">USD</th>
              <th class="text-right" style="color:#dc3545">USD Qaytim</th>
              <th class="text-right" style="color:#e74c3c">↩ Vozvrat</th>
              <th class="text-right" style="color:var(--danger)">Inkasso</th>
              <th class="text-right">Jami</th>
              <th>Holat</th>
              <th>Amal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($smenalar as $sm): ?>
            <tr id="smena-row-<?= $sm['id'] ?>">
              <td><code class="fs-xs">#<?= $sm['id'] ?></code></td>
              <td class="fw-semibold fs-sm"><?= im_f($sm['kassir_ism'] ?? '-') ?></td>
              <td class="text-muted fs-xs"><?= im_date($sm['sana']) ?></td>
              <td class="fs-xs"><?= $sm['ochildi'] ? date('H:i', strtotime($sm['ochildi'])) : '—' ?></td>
              <td class="fs-xs"><?= $sm['yopildi'] ? date('H:i', strtotime($sm['yopildi'])) : '<span class="text-muted">—</span>' ?></td>
              <td class="text-center"><span class="im-badge im-badge-muted"><?= (int)$sm['sotuv_soni'] ?> ta</span></td>
              <td class="text-right num fw-semibold" style="color:var(--success)">
                <?= im_money($sm['naqd_tushum']) ?>
                <?php if ((float)($sm['naqd_qaytim_jami'] ?? 0) > 0): ?>
                  <div class="text-muted fs-xs">Qaytim: <?= im_money($sm['naqd_qaytim_jami']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-right num fw-semibold" style="color:var(--primary)"><?= im_money($sm['karta_tushum']) ?></td>
              <td class="text-right num fw-semibold" style="color:var(--info)"><?= im_money($sm['bank_tushum']) ?></td>
              <td class="text-right num fw-semibold" style="color:var(--secondary)"><?= im_money($sm['usd_tushum']) ?> $</td>
              <td class="text-right num fw-semibold" style="color:#dc3545">
                <?= (float)($sm['usd_qaytim_jami'] ?? 0) > 0 ? '-'.im_money($sm['usd_qaytim_jami']).' so\'m' : '—' ?>
              </td>
              <td class="text-right num fw-semibold" style="color:#e74c3c">
                <?= (float)($sm['vozvrat_jami'] ?? 0) > 0 ? '-'.im_money($sm['vozvrat_jami']).' so\'m' : '—' ?>
              </td>
              <td class="text-right num fw-semibold" style="color:var(--danger)">
                <?= (float)$sm['inkasso_jami'] > 0 ? '-'.im_money($sm['inkasso_jami']) : '—' ?>
              </td>
              <td class="text-right num fw-bold" style="color:var(--accent-dark)"><?= im_money($sm['jami_tushum']) ?></td>
              <td>
                <?php if ($sm['holat'] === 'ochiq'): ?>
                  <span class="im-badge im-badge-success" style="font-size:10px">🟢 Ochiq</span>
                <?php else: ?>
                  <span class="im-badge im-badge-muted" style="font-size:10px">✅ Yopiq</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <a href="<?= im_BASE ?>print/smena.php?id=<?= $sm['id'] ?>" target="_blank"
                     class="im-btn im-btn-outline im-btn-sm" title="Z-Hisobot">
                    <i class="bi bi-printer"></i>
                  </a>
                  <button class="im-btn im-btn-dark im-btn-sm" title="Tafsilot"
                          onclick="showDetail(<?= $sm['id'] ?>)">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <!-- Footer: Jami -->
          <?php
          $f_sotuv       = array_sum(array_column($smenalar, 'sotuv_soni'));
          $f_naqd        = array_sum(array_column($smenalar, 'naqd_tushum'));
          $f_karta       = array_sum(array_column($smenalar, 'karta_tushum'));
          $f_bank        = array_sum(array_column($smenalar, 'bank_tushum'));
          $f_usd         = array_sum(array_column($smenalar, 'usd_tushum'));
          $f_usd_qaytim  = array_sum(array_column($smenalar, 'usd_qaytim_jami'));
          $f_naqd_qaytim = array_sum(array_column($smenalar, 'naqd_qaytim_jami'));
          $f_vozvrat     = array_sum(array_column($smenalar, 'vozvrat_jami'));
          $f_ink         = array_sum(array_column($smenalar, 'inkasso_jami'));
          $f_jami        = array_sum(array_column($smenalar, 'jami_tushum'));
          ?>
          <tfoot style="background:var(--bg);font-weight:700">
            <tr>
              <td colspan="5" class="fw-bold">Jami (ushbu sahifa)</td>
              <td class="text-center"><?= $f_sotuv ?> ta</td>
              <td class="text-right num" style="color:var(--success)">
                <?= im_money($f_naqd) ?>
                <?php if ($f_naqd_qaytim > 0): ?>
                  <div class="text-muted fs-xs">Qaytim: <?= im_money($f_naqd_qaytim) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-right num" style="color:var(--primary)"><?= im_money($f_karta) ?></td>
              <td class="text-right num" style="color:var(--info)"><?= im_money($f_bank) ?></td>
              <td class="text-right num" style="color:var(--secondary)"><?= im_money($f_usd) ?> $</td>
              <td class="text-right num" style="color:#dc3545"><?= $f_usd_qaytim > 0 ? '-'.im_money($f_usd_qaytim).' so\'m' : '—' ?></td>
              <td class="text-right num" style="color:#e74c3c"><?= $f_vozvrat > 0 ? '-'.im_money($f_vozvrat).' so\'m' : '—' ?></td>
              <td class="text-right num" style="color:var(--danger)"><?= $f_ink > 0 ? '-'.im_money($f_ink) : '—' ?></td>
              <td class="text-right num" style="color:var(--accent-dark)"><?= im_money($f_jami) ?></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <div class="d-flex justify-content-center gap-1 p-3">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?p=<?= $i ?>&holat=<?= $filter_holat ?>&sana=<?= $filter_sana ?>"
             class="im-btn im-btn-sm <?= $i === $page ? 'im-btn-primary' : 'im-btn-outline' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-clock-history"></i>
        <h4>Smena topilmadi</h4>
        <p class="text-muted">Hali smena ochilmagan yoki filter bo'yicha natija yo'q</p>
        <a href="<?= im_BASE ?>dukon/pos.php" class="im-btn im-btn-primary">POS ga o'tish</a>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>
</div>
<div id="im-toast-container"></div>

<!-- Smena tafsilot modali -->
<div class="im-overlay" id="smena-detail-modal">
  <div class="im-modal" style="max-width:680px;width:96%">
    <div class="im-modal-header">
      <i class="bi bi-clock-history" style="color:var(--accent-dark);font-size:20px"></i>
      <span class="im-modal-title" id="detail-title">Smena tafsiloti</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body" id="smena-detail-body">
      <div class="text-center py-4"><span class="im-spinner"></span></div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Yopish</button>
      <a id="detail-print-link" href="#" target="_blank" class="im-btn im-btn-primary">
        <i class="bi bi-printer"></i> Z-Hisobot chop etish
      </a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
async function showDetail(smenaId) {
  document.getElementById('detail-title').textContent = `Smena #${smenaId} tafsiloti`;
  document.getElementById('detail-print-link').href = `${(window.im_BASE || '/')}print/smena.php?id=${smenaId}`;
  document.getElementById('smena-detail-body').innerHTML = '<div class="text-center py-4"><span class="im-spinner"></span></div>';
  NHModal.open('smena-detail-modal');

  const r = await fetch(`${im_BASE}dukon/ajax/smena-detail.php?id=${smenaId}`).then(r=>r.json()).catch(()=>({status:'error'}));
  if (r.status !== 'ok') {
    document.getElementById('smena-detail-body').innerHTML = '<div class="text-center text-danger py-3">Xatolik yuz berdi</div>';
    return;
  }
  const d = r.data;
  const fmt = n => Number(n).toLocaleString('uz-UZ');

  let inkassoHtml = '';
  if (d.inkasso && d.inkasso.length) {
    inkassoHtml = `<div class="mt-3"><div class="fw-bold fs-sm mb-2" style="color:var(--danger)">
      <i class="bi bi-wallet2"></i> Kassadan olingan (Inkasso Tarixi)</div>
      <div class="im-table-wrap"><table class="im-table">
        <thead><tr><th>Vaqt</th><th>Tur</th><th class="text-right">Summa</th><th>Izoh</th></tr></thead>
        <tbody>
        ${d.inkasso.map(i => `<tr>
          <td class="fs-xs text-muted">${i.sana.slice(11,16)}</td>
          <td><span class="im-badge im-badge-danger fs-xs">${i.tur}</span></td>
          <td class="text-right fw-bold num" style="color:var(--danger)">${fmt(i.tur === 'usd' ? i.usd_summa : i.summa)} ${i.tur === 'usd' ? '$' : "so'm"}</td>
          <td class="text-muted fs-xs">${im_esc(i.izoh)||'—'}</td>
        </tr>`).join('')}
        </tbody>
      </table></div></div>`;
  }

  document.getElementById('smena-detail-body').innerHTML = `
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="text-muted fs-xs">Kassir</div>
      <div class="fw-bold">${d.smena.kassir_ism||'—'}</div>
    </div>
    <div class="col-6 col-md-3">
      <div class="text-muted fs-xs">Holat</div>
      <div>${d.smena.holat==='ochiq'?'<span class="im-badge im-badge-success">Ochiq</span>':'<span class="im-badge im-badge-muted">Yopiq</span>'}</div>
    </div>
    <div class="col-6 col-md-3">
      <div class="text-muted fs-xs">Boshlandi</div>
      <div class="fw-semibold fs-sm">${(d.smena.ochildi||'').slice(0,16)}</div>
    </div>
    <div class="col-6 col-md-3">
      <div class="text-muted fs-xs">Yopildi</div>
      <div class="fw-semibold fs-sm">${d.smena.yopildi ? d.smena.yopildi.slice(0,16) : '—'}</div>
    </div>
  </div>
  
  <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-box-arrow-in-right text-success"></i> Tushum (Sotuv)</h6>
  <div class="row g-2 mb-3">
      <div class="col-6 col-md-3"><div class="im-stat-card p-2 border-start border-success border-3">
        <div class="text-muted fs-xs">Naqd</div><div class="fw-bold text-success num">${fmt(d.stats.naqd)} so'm</div>
      </div></div>
      <div class="col-6 col-md-3"><div class="im-stat-card p-2 border-start border-primary border-3">
        <div class="text-muted fs-xs">${IM_TT_LABELS_SHORT.karta}</div><div class="fw-bold text-primary num">${fmt(d.stats.karta)} so'm</div>
      </div></div>
      <div class="col-6 col-md-3"><div class="im-stat-card p-2 border-start border-info border-3">
        <div class="text-muted fs-xs">${IM_TT_LABELS_SHORT.bank}</div><div class="fw-bold text-info num">${fmt(d.stats.bank)} so'm</div>
      </div></div>
      <div class="col-6 col-md-3"><div class="im-stat-card p-2 border-start border-secondary border-3">
        <div class="text-muted fs-xs">USD</div><div class="fw-bold text-secondary num">${fmt(d.stats.usd)} $</div>
      </div></div>
  </div>

  <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-arrow-left-right text-warning"></i> Kirim-chiqimlar</h6>
  <div class="im-table-wrap mb-3">
    <table class="im-table">
       <thead>
         <tr>
           <th>Harakat</th>
           <th class="text-right">Naqd</th>
           <th class="text-right"><?= im_tt_label('karta', true) ?></th>
           <th class="text-right"><?= im_tt_label('bank', true) ?></th>
           <th class="text-right">USD</th>
         </tr>
       </thead>
       <tbody>
         <tr>
           <td><i class="bi bi-dash-circle text-danger"></i> Xarajatlar</td>
           <td class="text-right text-danger num">${d.xarajat.naqd > 0 ? '-' : ''}${fmt(d.xarajat.naqd)}</td>
           <td class="text-right text-danger num">${d.xarajat.karta > 0 ? '-' : ''}${fmt(d.xarajat.karta)}</td>
           <td class="text-right text-danger num">${d.xarajat.bank > 0 ? '-' : ''}${fmt(d.xarajat.bank)}</td>
           <td class="text-right text-danger num">${d.xarajat.usd > 0 ? '-' : ''}${fmt(d.xarajat.usd)}</td>
         </tr>
         <tr>
           <td><i class="bi bi-dash-circle text-danger"></i> Inkassa</td>
           <td class="text-right text-danger num">${d.inkasso_types.naqd > 0 ? '-' : ''}${fmt(d.inkasso_types.naqd)}</td>
           <td class="text-right text-danger num">${d.inkasso_types.karta > 0 ? '-' : ''}${fmt(d.inkasso_types.karta)}</td>
           <td class="text-right text-danger num">${d.inkasso_types.bank > 0 ? '-' : ''}${fmt(d.inkasso_types.bank)}</td>
           <td class="text-right text-danger num">${d.inkasso_types.usd > 0 ? '-' : ''}${fmt(d.inkasso_types.usd)}</td>
         </tr>
         <tr>
           <td><i class="bi bi-dash-circle text-danger"></i> Vozvrat (qaytarildi)</td>
           <td class="text-right text-danger num">${d.vozvrat.naqd > 0 ? '-' : ''}${fmt(d.vozvrat.naqd)}</td>
           <td class="text-right text-danger num">${d.vozvrat.karta > 0 ? '-' : ''}${fmt(d.vozvrat.karta)}</td>
           <td class="text-right text-danger num">${d.vozvrat.bank > 0 ? '-' : ''}${fmt(d.vozvrat.bank)}</td>
           <td class="text-right text-danger num">${d.vozvrat.usd > 0 ? '-' : ''}${fmt(d.vozvrat.usd)}</td>
         </tr>
         <tr>
           <td><i class="bi bi-plus-circle text-success"></i> Nasiya qaytimi</td>
           <td class="text-right text-success num">${d.nasiya_tolov.naqd > 0 ? '+' : ''}${fmt(d.nasiya_tolov.naqd)}</td>
           <td class="text-right text-success num">${d.nasiya_tolov.karta > 0 ? '+' : ''}${fmt(d.nasiya_tolov.karta)}</td>
           <td class="text-right text-success num">${d.nasiya_tolov.bank > 0 ? '+' : ''}${fmt(d.nasiya_tolov.bank)}</td>
           <td class="text-right text-success num">${d.nasiya_tolov.usd > 0 ? '+' : ''}${fmt(d.nasiya_tolov.usd)}</td>
         </tr>
         ${d.usd_qaytim > 0 ? `<tr>
            <td><i class="bi bi-arrow-return-left text-danger"></i> <strong>USD qaytimi</strong> (so'mda naqd)<br><small class="text-muted">USD bilan to'lov qildi, ortiqcha so'mda qaytarildi</small></td>
            <td class="text-right text-danger num fw-bold">-${fmt(d.usd_qaytim)} so'm</td>
            <td></td><td></td><td></td>
          </tr>` : ''}
          ${(d.naqd_qaytim || 0) > 0 ? `<tr>
            <td><i class="bi bi-arrow-return-left text-secondary"></i> Naqd qaytim<br><small class="text-muted">Naqd ortiqcha berilgan va qaytarilgan qism</small></td>
            <td class="text-right text-secondary num">-${fmt(d.naqd_qaytim)} so'm</td>
            <td></td><td></td><td></td>
          </tr>` : ''}
        </tbody>
    </table>
  </div>

  <div class="p-3 rounded mb-3" style="background:var(--accent-dark);color:#fff">
     <h6 class="fw-bold mb-3"><i class="bi bi-safe-fill"></i> YAKUNIY KASSA (Real O'zgarish)</h6>
     <div class="row g-2">
      <div class="col-6 col-md-3">
        <div class="text-white-50 fs-xs">Naqd</div><div class="fw-bold fs-md num">${fmt(d.yakuniy.naqd)} so'm</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-white-50 fs-xs">${IM_TT_LABELS_SHORT.karta}</div><div class="fw-bold fs-md num">${fmt(d.yakuniy.karta)} so'm</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-white-50 fs-xs">${IM_TT_LABELS_SHORT.bank}</div><div class="fw-bold fs-md num">${fmt(d.yakuniy.bank)} so'm</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-white-50 fs-xs">USD</div><div class="fw-bold fs-md num">${fmt(d.yakuniy.usd)} $</div>
      </div>
     </div>
  </div>

  ${inkassoHtml}
  ${d.top_mah.length ? `
  <div class="mt-3">
    <div class="fw-bold fs-sm mb-2"><i class="bi bi-trophy-fill" style="color:var(--accent-dark)"></i> Top mahsulotlar</div>
    ${d.top_mah.map((m,i)=>`<div class="d-flex justify-content-between align-items-center py-1 border-bottom">
      <span class="fs-sm"><strong>${i+1}.</strong> ${im_esc(m.nomi)}</span>
      <span class="text-muted fs-xs">${m.soni} dona · <strong>${fmt(m.summa)}</strong> so'm</span>
    </div>`).join('')}
  </div>` : ''}`;
}
</script>
</body>
</html>
