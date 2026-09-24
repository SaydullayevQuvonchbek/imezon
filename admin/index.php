<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../fifo_reports.php';
im_rol_check(['admin']);
$db = new Cyber();
$sale_cost = im_fifo_sale_unit_cost_sql('si');

// Dashboard statistikalar
$xodim_soni  = (int)$db->val("SELECT COUNT(*) FROM im_xodimlar WHERE status=1");
$mijoz_soni  = (int)$db->val("SELECT COUNT(*) FROM im_mijozlar WHERE status=1");
$ps_qarz     = (float)$db->val("SELECT COALESCE(SUM(qoldiq),0) FROM im_postavshik_qarz WHERE status='ochiq'");
$mij_nasiya  = (float)$db->val("SELECT COALESCE(SUM(qoldiq),0) FROM im_nasiya WHERE holat='ochiq'");
$usd_kurs    = im_usd_kurs();

// Oylik sotuv (joriy oy). Qaytarilgan chek ham avval tushum bo'lgan;
// uning faqat qaytarilgan qismi quyida vozvrat orqali ayriladi.
$oy_sotuv    = (float)$db->val("SELECT COALESCE(SUM(tolov_summa),0) FROM im_sotuvlar WHERE MONTH(sana)=MONTH(CURDATE()) AND YEAR(sana)=YEAR(CURDATE()) AND holat IN ('aktiv','qaytarilgan')");
$oy_harajat  = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_harajatlar WHERE MONTH(sana)=MONTH(CURDATE()) AND YEAR(sana)=YEAR(CURDATE())");
// Brutto foyda = yakuniy chek tushumi - dinamik FIFO tannarxi.
// tolov_summa ichida voucher/umumiy chegirma va xizmat haqi allaqachon bor.
$oy_tannarx = (float)($db->val(
    "SELECT COALESCE(SUM(($sale_cost) * si.soni), 0)
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id = si.sotuv_id
     WHERE s.holat IN ('aktiv','qaytarilgan') AND MONTH(s.sana)=MONTH(CURDATE()) AND YEAR(s.sana)=YEAR(CURDATE())"
) ?? 0);
$oy_return = im_fifo_report_returns($db, date('Y-m-01'), date('Y-m-t'));
$oy_sotuv -= (float)$oy_return['revenue'];
$oy_tannarx -= (float)$oy_return['cost'];
$oy_foyda = $oy_sotuv - $oy_tannarx;
// Maosh im_harajatlar ga YOZILMAYDI (maosh-save.php faqat im_maosh_tarixi
// va im_balans ga yozadi). Uni qo'shmasak, sof foyda haqiqiydan katta
// ko'rinadi — shu sabab AI Yordamchining raqami dashboarddan farq qilardi.
$oy_maosh    = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_maosh_tarixi WHERE MONTH(sana)=MONTH(CURDATE()) AND YEAR(sana)=YEAR(CURDATE())");
$oy_isrof    = (float)$db->val("SELECT COALESCE(SUM(qoldi_porsiya*yakuniy_tannarx),0) FROM im_osh_qozon WHERE holat='yopildi' AND qoldi_isrofmi=1 AND MONTH(sana)=MONTH(CURDATE()) AND YEAR(sana)=YEAR(CURDATE())");
// Bekor qilingan buyurtmadagi pishirilgan taomlar (xomashyo sarflangan, sotuv yo'q)
$oy_isrof   += im_fifo_report_kitchen_waste($db, date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59'));
// Sanoq (inventarizatsiya) sof kamomadi ham chiqim — FIFO'dan hisobdan chiqarilgan
$oy_isrof   += im_fifo_report_inventar($db, date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59'))['sof'];
$oy_chiqim   = $oy_harajat + $oy_maosh + $oy_isrof;
$sof_foyda   = $oy_foyda - $oy_chiqim;

// Bugungi ko'rsatkichlar
$bug_sotuv   = (float)$db->val("SELECT COALESCE(SUM(tolov_summa),0) FROM im_sotuvlar WHERE holat IN ('aktiv','qaytarilgan') AND DATE(sana)=CURDATE()");
$bug_harajat = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_harajatlar WHERE DATE(sana)=CURDATE()");
$bug_tannarx = (float)($db->val(
    "SELECT COALESCE(SUM(($sale_cost) * si.soni), 0)
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id = si.sotuv_id
     WHERE s.holat IN ('aktiv','qaytarilgan') AND DATE(s.sana)=CURDATE()"
) ?? 0);
$bug_return = im_fifo_report_returns($db, date('Y-m-d'), date('Y-m-d'));
$bug_sotuv -= (float)$bug_return['revenue'];
$bug_tannarx -= (float)$bug_return['cost'];
$bug_foyda = $bug_sotuv - $bug_tannarx;
$bug_maosh   = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_maosh_tarixi WHERE sana=CURDATE()");
$bug_isrof   = (float)$db->val("SELECT COALESCE(SUM(qoldi_porsiya*yakuniy_tannarx),0) FROM im_osh_qozon WHERE holat='yopildi' AND qoldi_isrofmi=1 AND sana=CURDATE()");
$bug_isrof  += im_fifo_report_kitchen_waste($db, date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59'));
$bug_isrof  += im_fifo_report_inventar($db, date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59'))['sof'];
$bug_chiqim  = $bug_harajat + $bug_maosh + $bug_isrof;
$bug_sof     = $bug_foyda - $bug_chiqim;

// Kassa holati (Yagona kassa = filial_id 0, Filiallar > 0)
$kassalar = $db->rows("SELECT k.*, COALESCE(f.nomi, 'Yagona Kassa (Samarqand)') AS filial_nomi FROM im_kassa k LEFT JOIN im_filiallar f ON f.id=k.filial_id ORDER BY k.filial_id ASC");

// So'nggi 14 kun sotuv grafigi (mini line-chart) — bo'sh kunlar ham to'ldiriladi
$hafta_xom = [];
foreach ($db->rows("SELECT DATE(sana) AS kun, COALESCE(SUM(tolov_summa),0) AS jami, COUNT(*) AS soni
                    FROM im_sotuvlar WHERE holat IN ('aktiv','qaytarilgan') AND sana>=DATE_SUB(CURDATE(),INTERVAL 13 DAY)
                    GROUP BY DATE(sana)") as $r) {
    $hafta_xom[$r['kun']] = $r;
}
$hafta = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $hafta[] = [
        'kun'  => $d,
        'jami' => (float)($hafta_xom[$d]['jami'] ?? 0),
        'soni' => (int)($hafta_xom[$d]['soni'] ?? 0),
    ];
}

// So'nggi sotuvlar
$oxirgi_sotuvlar = $db->rows("SELECT s.*,m.ism AS mijoz FROM im_sotuvlar s LEFT JOIN im_mijozlar m ON m.id=s.mijoz_id ORDER BY s.sana DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Dashboard | IMezon</title>
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
    <div class="im-page-title"><i class="bi bi-speedometer2 me-1"></i> Admin Dashboard</div>
    <div class="im-topbar-actions">
      <span class="im-badge" style="background:var(--accent);color:var(--primary);font-size:12px;padding:6px 12px">
        💱 1 USD = <?= im_money($usd_kurs) ?> so'm
      </span>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">
    <!-- Asosiy statistikalar -->
    <div class="row g-3 mb-3">
      <!-- Bugungi sof foyda -->
      <div class="col-md-3 col-6">
        <div class="im-stat-card" style="border-left:4px solid var(--accent-dark)">
          <div class="im-stat-icon" style="background:rgba(226,185,111,.15);color:var(--accent-dark)"><i class="bi bi-bag-fill"></i></div>
          <div><div class="im-stat-label">Oylik sotuv</div>
          <div class="im-stat-value num"><?= im_money($oy_sotuv) ?> so'm</div></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i class="bi bi-graph-up-arrow"></i></div>
          <div><div class="im-stat-label">Oylik foyda (brutto)</div>
          <div class="im-stat-value num" style="color:var(--success)"><?= im_money($oy_foyda) ?> so'm</div></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="im-stat-card" style="border-left:4px solid <?= $sof_foyda >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
          <div class="im-stat-icon" style="background:rgba(16,185,129,.12);color:<?= $sof_foyda >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><i class="bi bi-cash-coin"></i></div>
          <div>
            <div class="im-stat-label">Oylik SOF foyda</div>
            <div class="im-stat-value num" style="color:<?= $sof_foyda >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= im_money($sof_foyda) ?> so'm</div>
            <div class="text-muted fs-xs">
              Xarajat: <?= im_money($oy_harajat) ?> · Maosh: <?= im_money($oy_maosh) ?> · Isrof: <?= im_money($oy_isrof) ?> so'm
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.12);color:var(--danger)"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div><div class="im-stat-label">Jami qarzlar</div>
          <div class="im-stat-value num" style="color:var(--danger)"><?= im_money($ps_qarz + $mij_nasiya) ?> so'm</div>
          <div class="text-muted fs-xs">PS: <?= im_money($ps_qarz) ?> · Mij: <?= im_money($mij_nasiya) ?></div></div>
        </div>
      </div>
    </div>

    <!-- Bugungi ko'rsatkichlar -->
    <div class="row g-3 mb-4">
      <div class="col-md-4 col-6">
        <div class="im-card p-3" style="border-top:3px solid var(--accent-dark)">
          <div class="text-muted fs-xs mb-1"><i class="bi bi-calendar-day"></i> Bugungi sotuv</div>
          <div class="fw-bold num fs-5"><?= im_money($bug_sotuv) ?> so'm</div>
        </div>
      </div>
      <div class="col-md-4 col-6">
        <div class="im-card p-3" style="border-top:3px solid #6366f1">
          <div class="text-muted fs-xs mb-1"><i class="bi bi-cash-stack"></i> Bugungi brutto foyda</div>
          <div class="fw-bold num fs-5" style="color:#6366f1"><?= im_money($bug_foyda) ?> so'm</div>
        </div>
      </div>
      <div class="col-md-4 col-12">
        <div class="im-card p-3" style="border-top:3px solid <?= $bug_sof >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
          <div class="text-muted fs-xs mb-1"><i class="bi bi-coin"></i> Bugungi SOF foyda</div>
          <div class="fw-bold num fs-5" style="color:<?= $bug_sof >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= im_money($bug_sof) ?> so'm</div>
          <div class="text-muted fs-xs">Xarajat + maosh + isrof: <?= im_money($bug_chiqim) ?> so'm</div>
        </div>
      </div>
    </div>

    <?php
    // AI kunlik xulosasi — faqat yoqilgan va kalit saqlangan bo'lsa.
    // Bu yerda ai_lib.php ATAYLAB ulanmaydi: dashboard eng ko'p
    // ochiladigan sahifa, xulosa esa keshdan AJAX bilan keladi.
    $ai_kunlik = im_sozlama('ai_kunlik', '0') === '1'
        && (im_sozlama('ai_key_claude', '') !== '' || im_sozlama('ai_key_or', '') !== '' || im_sozlama('ai_key', '') !== '');
    if ($ai_kunlik):
    ?>
    <!-- AI kun yakuni -->
    <div class="im-card mb-4" style="border-left:4px solid #7c3aed">
      <div class="im-card-header">
        <i class="bi bi-stars" style="color:#7c3aed"></i>
        <span class="im-card-title">AI — kun yakuni</span>
        <div class="ms-auto d-flex gap-2 align-items-center">
          <span id="ai-kun-vaqt" class="text-muted" style="font-size:11.5px"></span>
          <button class="im-btn im-btn-ghost im-btn-sm" id="btn-ai-kun">
            <i class="bi bi-arrow-clockwise"></i> Yangilash
          </button>
          <a class="im-btn im-btn-ghost im-btn-sm" href="<?= im_BASE ?>admin/ai.php">
            <i class="bi bi-chat-dots"></i> Savol berish
          </a>
        </div>
      </div>
      <div class="im-card-body" id="ai-kun-tana" style="font-size:14px;line-height:1.65">
        <span class="text-muted" style="font-size:13px">Yuklanmoqda…</span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Kassa va sotuv row -->
    <div class="row g-3 mb-4">
      <!-- Kassa holati -->
      <div class="col-md-4">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-safe2-fill" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Kassa holati</span>
          </div>
          <div class="im-card-body p-4" style="max-height:400px;overflow-y:auto">
            <?php if ($kassalar): ?>
              <?php foreach ($kassalar as $k): 
                 $is_global = ($k['filial_id'] == 0);
              ?>
              <div class="mb-4">
                <h6 class="fw-bold mb-3" style="<?= $is_global ? 'color:var(--accent-dark);font-size:16px;' : 'color:#555;font-size:14px;' ?>">
                  <i class="<?= $is_global ? 'bi bi-building-fill' : 'bi bi-shop' ?>"></i> <?= im_f($k['filial_nomi']) ?>
                </h6>
                <div class="ms-2">
                  <?php $items=[[im_tt_label('naqd',true),'naqd_balans','success'],[im_tt_label('karta',true),'karta_balans','primary'],[im_tt_label('bank',true),'bank_balans','info']]; ?>
                  <?php foreach($items as [$lbl,$f,$c]):
                    $val = (float)($k[$f] ?? 0); 
                  ?>
                  <div class="d-flex justify-content-between align-items-center mb-2 pb-1" style="border-bottom:1px solid var(--border)">
                    <span class="fs-sm"><?= $lbl ?></span>
                    <span class="fw-bold num" style="color:var(--<?= $c ?>)">
                      <?= im_money($val) ?> so'm
                    </span>
                  </div>
                  <?php endforeach; ?>
                  
                  <?php $usd_val = (float)($k['usd_balans'] ?? 0); $usd_som_eq = $usd_val * $usd_kurs; ?>
                  <div class="d-flex justify-content-between align-items-center mb-2 pb-1">
                    <span class="fs-sm">🪙 USD</span>
                    <span class="fw-bold num" style="color:#b8860b">
                      $<?= number_format($usd_val, 2) ?>
                      <div class="text-muted" style="font-size:11px;font-weight:400">≈ <?= im_money($usd_som_eq) ?> so'm</div>
                    </span>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
            <div class="text-muted text-center py-3">Kassa ma'lumoti yo'q</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- 14 kunlik sotuv grafigi -->
      <div class="col-md-8">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-graph-up-arrow" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">So'nggi 14 kun sotuvi</span>
            <a href="<?= im_BASE ?>admin/analitika.php" class="im-btn im-btn-ghost im-btn-sm ms-auto">
              To'liq dinamika <i class="bi bi-arrow-right"></i>
            </a>
          </div>
          <div class="im-card-body p-3">
            <div style="position:relative;height:230px"><canvas id="dash-sotuv-chart"></canvas></div>
          </div>
        </div>
      </div>
    </div>

    <!-- So'nggi sotuvlar -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-receipt"></i>
        <span class="im-card-title">So'nggi sotuvlar</span>
        <a href="<?= im_BASE ?>admin/sotuvlar.php" class="im-btn im-btn-outline im-btn-sm ms-auto">Barchasi →</a>
      </div>
      <?php if ($oxirgi_sotuvlar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr><th>Chek</th><th>Vaqt</th><th>Mijoz</th><th class="text-right">Jami</th><th>To'lov</th></tr></thead>
          <tbody>
            <?php foreach($oxirgi_sotuvlar as $s): ?>
            <tr>
              <td><code class="fs-xs"><?= im_f($s['chek_nomer']) ?></code></td>
              <td class="text-muted fs-xs"><?= im_datetime($s['sana']) ?></td>
              <td class="fs-sm"><?= im_f($s['mijoz'] ?: 'Anonim') ?></td>
              <td class="text-right num fw-bold"><?= im_money($s['tolov_summa']) ?> so'm</td>
              <td><?php
                $t=[];
                if($s['naqd_summa']>0) $t[]='<span class="im-badge im-badge-success fs-xs">naqd</span>';
                if($s['karta_summa']>0) $t[]='<span class="im-badge im-badge-primary fs-xs">karta</span>';
                if($s['bank_summa']>0) $t[]='<span class="im-badge im-badge-info fs-xs">bank</span>';
                if($s['usd_summa']>0) $t[]='<span class="im-badge fs-xs" style="background:#fef3c7;color:#92400e">usd</span>';
                if($s['nasiya_summa']>0) $t[]='<span class="im-badge im-badge-danger fs-xs">nasiya</span>';
                echo implode(' ',$t);
              ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-receipt"></i><h4>Sotuv yo'q</h4></div>
      <?php endif; ?>
    </div>
  </main>
</div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
// ── Dashboard: 14 kunlik sotuv mini-grafigi ───────────────
(function(){
  const cv = document.getElementById('dash-sotuv-chart');
  if (!cv || typeof Chart === 'undefined') return;
  const H = <?= json_encode($hafta, JSON_UNESCAPED_UNICODE) ?>;
  const cssVar = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim() || '#888';
  const txt = cssVar('--muted'), grid = cssVar('--border') + '80';
  const labels = H.map(h => { const d = new Date(h.kun + 'T00:00'); return d.getDate() + '.' + (d.getMonth()+1); });
  const qisq = n => Math.abs(n) >= 1e6 ? (n/1e6).toFixed(1).replace(/\.0$/,'') + ' mln'
                  : Math.abs(n) >= 1e3 ? Math.round(n/1e3) + ' ming' : String(Math.round(n));
  new Chart(cv, {
    data: { labels, datasets: [
      { type:'line', label:'Daromad', data:H.map(h=>+h.jami), yAxisID:'y',
        borderColor:'#c9a055', backgroundColor:'rgba(201,160,85,.13)', fill:true,
        tension:.3, borderWidth:2, pointRadius:0, pointHoverRadius:4 },
      { type:'bar', label:'Cheklar', data:H.map(h=>+h.soni), yAxisID:'y1',
        backgroundColor:'rgba(41,128,185,.18)', borderRadius:3, barPercentage:.55 },
    ]},
    options: {
      responsive:true, maintainAspectRatio:false,
      interaction:{ mode:'index', intersect:false },
      plugins:{
        legend:{ labels:{ color:txt, boxWidth:12, font:{size:11} } },
        tooltip:{ callbacks:{ label: c => c.dataset.type === 'bar'
          ? c.dataset.label + ': ' + c.parsed.y + ' ta'
          : c.dataset.label + ': ' + Math.round(c.parsed.y).toLocaleString('uz-UZ') + " so'm" } },
      },
      scales:{
        x:{ ticks:{ color:txt, maxRotation:0, autoSkipPadding:14 }, grid:{ display:false } },
        y:{ position:'left', ticks:{ callback:qisq, color:txt }, grid:{ color:grid } },
        y1:{ position:'right', ticks:{ precision:0, color:txt }, grid:{ drawOnChartArea:false } },
      },
    }
  });
})();
</script>
<?php if (!empty($ai_kunlik)): ?>
<script>
// ── AI kun yakuni ─────────────────────────────────────────
// Sahifa ochilganda faqat KESHDAGI xulosa so'raladi (API'ga
// chiqmaydi). Haqiqiy so'rov faqat "Yangilash" bosilganda —
// aks holda dashboardni har ochganda pul ketardi.
(function(){
  const tana = document.getElementById('ai-kun-tana');
  const vaqt = document.getElementById('ai-kun-vaqt');
  const btn  = document.getElementById('btn-ai-kun');
  if (!tana) return;

  // Model javobi ishonchsiz matn: avval escape, keyin teglar.
  function md(s){
    const e = String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    let out = '', ro = false;
    e.split('\n').forEach(l => {
      const t = l.trim();
      const b = x => x.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');
      if (t === '') { if(ro){out += '</ul>'; ro=false;} return; }
      const li = t.match(/^(?:[-*•]|\d+[.)])\s+(.*)$/);
      if (li) { if(!ro){out += '<ul style="margin:4px 0 8px;padding-left:20px">'; ro=true;}
                out += '<li>' + b(li[1]) + '</li>'; return; }
      if (ro) { out += '</ul>'; ro = false; }
      out += '<p style="margin:0 0 8px">' + b(t.replace(/^#{1,6}\s+/,'')) + '</p>';
    });
    if (ro) out += '</ul>';
    return out;
  }

  async function yukla(yangila){
    if (yangila) {
      btn.disabled = true;
      btn.innerHTML = '<span class="im-spinner"></span>';
      tana.innerHTML = '<span class="text-muted" style="font-size:13px">Kun tahlil qilinmoqda… (20-40 soniya)</span>';
    }
    const fd = new FormData();
    if (yangila) fd.append('yangila', '1');
    const res = await IMAjax.post(window.im_BASE + 'admin/ajax/ai-kunlik.php', fd);

    if (res.status === 'ok') {
      tana.innerHTML = md(res.matn || '');
      vaqt.textContent = res.keshdan ? (res.vaqt || '') : 'hozir yangilandi';
    } else if (res.status === 'bosh') {
      tana.innerHTML = '<span class="text-muted" style="font-size:13px">'
        + 'Bugungi xulosa hali yasalmagan — "Yangilash" ni bosing.</span>';
      vaqt.textContent = '';
    } else {
      tana.innerHTML = '<span class="text-danger" style="font-size:13px">'
        + String(res.msg||'Xatolik').replace(/</g,'&lt;') + '</span>';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Yangilash';
  }

  btn.addEventListener('click', () => yukla(true));
  yukla(false);
})();
</script>
<?php endif; ?>
</body>
</html>
