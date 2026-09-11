<?php
// ============================================================
//  IMezon — Admin: Dinamika / Analitika
//  Sotuv, foyda, ombor qiymati grafiklari + kam qolgan /
//  uxlab yotgan mahsulotlar. Ma'lumot: admin/ajax/analitika-data.php
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

$filiallar = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY tartib, id");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dinamika | IMezon</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
<style>
.din-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:16px}
.din-range{display:flex;gap:4px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:3px}
.din-range button{
  border:none;background:none;padding:6px 14px;border-radius:7px;font-size:13px;font-weight:700;
  color:var(--muted);cursor:pointer;transition:.15s;font-family:inherit;
}
.din-range button.active{background:var(--accent);color:var(--primary)}
.din-chart-wrap{position:relative;height:320px}
.din-kpi{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:6px}
.din-kpi .k{flex:1;min-width:140px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:10px 14px}
.din-kpi .k .lbl{font-size:11px;color:var(--muted);font-weight:600}
.din-kpi .k .val{font-size:17px;font-weight:800;margin-top:2px}
.din-table{width:100%;border-collapse:collapse;font-size:13px}
.din-table th,.din-table td{padding:8px 10px;border-bottom:1px solid var(--border);text-align:left}
.din-table th{background:var(--bg);font-weight:700;font-size:12px;color:var(--text-2)}
.din-table td.num,.din-table th.num{text-align:right;font-variant-numeric:tabular-nums}
.din-table tbody tr:hover{background:var(--border-light)}
.din-bar{display:inline-block;height:6px;border-radius:3px;background:var(--danger);vertical-align:middle}
.din-empty{padding:28px;text-align:center;color:var(--muted);font-size:13px}
.din-loading{padding:40px;text-align:center;color:var(--muted)}
.pill{display:inline-block;padding:1px 8px;border-radius:20px;font-size:11px;font-weight:700}
.pill-danger{background:var(--danger-light);color:var(--danger)}
.pill-warn{background:#fff7ed;color:#c2410c}
.pill-muted{background:var(--bg);color:var(--muted)}
</style>
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title"><i class="bi bi-graph-up-arrow me-1"></i> Dinamika</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-ghost im-btn-sm" id="btn-refresh"><i class="bi bi-arrow-clockwise"></i></button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>

  <main class="im-content">
    <div class="din-toolbar">
      <div class="din-range" id="din-range">
        <button data-kun="7">7 kun</button>
        <button data-kun="30" class="active">30 kun</button>
        <button data-kun="60">60 kun</button>
        <button data-kun="90">90 kun</button>
      </div>
      <?php if (count($filiallar) > 1): ?>
      <select class="im-select" id="din-filial" style="max-width:200px">
        <option value="0">Barcha filiallar</option>
        <?php foreach ($filiallar as $f): ?>
        <option value="<?= (int)$f['id'] ?>"><?= im_f($f['nomi']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <span class="text-muted" style="font-size:12px" id="din-info"></span>
    </div>

    <!-- KPI -->
    <div class="din-kpi" id="din-kpi"></div>

    <!-- 1. Sotuv dinamikasi -->
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-bar-chart-line-fill" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Sotuv dinamikasi</span>
        <span class="text-muted ms-auto" style="font-size:11.5px">daromad · brutto foyda · cheklar soni</span>
      </div>
      <div class="im-card-body p-3">
        <div class="din-chart-wrap"><canvas id="chart-sotuv"></canvas></div>
      </div>
    </div>

    <!-- 2. Ombor qiymati -->
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-box-seam-fill" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Ombor qiymati dinamikasi</span>
        <span class="text-muted ms-auto" style="font-size:11.5px">kompaniya bo'yicha · kunlik kirim/chiqim (so'm)</span>
      </div>
      <div class="im-card-body p-3">
        <div class="din-chart-wrap"><canvas id="chart-ombor"></canvas></div>
        <div class="text-muted mt-2" style="font-size:11px">
          <i class="bi bi-info-circle"></i>
          Ombor qiymati bugungi holatdan orqaga tiklanadi (+ sotilgan tannarx − kelgan partiya). Filial filtri bu grafikka ta'sir qilmaydi.
        </div>
      </div>
    </div>

    <!-- 3. Kam qolgan mahsulotlar -->
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-exclamation-triangle-fill" style="color:var(--danger)"></i>
        <span class="im-card-title">Kam qolgan mahsulotlar</span>
        <span class="im-badge im-badge-danger ms-2" id="kam-count">0</span>
        <span class="text-muted ms-auto" style="font-size:11.5px">7 kundan kam yetadi</span>
      </div>
      <div class="im-card-body p-0">
        <div id="kam-body"><div class="din-loading">Yuklanmoqda…</div></div>
      </div>
    </div>

    <!-- 4. Uxlab yotgan zaxira -->
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-hourglass-bottom" style="color:#c2410c"></i>
        <span class="im-card-title">Uxlab yotgan zaxira</span>
        <span class="pill pill-warn ms-2" id="uxlab-summa">—</span>
        <span class="text-muted ms-auto" style="font-size:11.5px">21 kundan beri sotilmagan · muzlagan kapital</span>
      </div>
      <div class="im-card-body p-0">
        <div id="uxlab-body"><div class="din-loading">Yuklanmoqda…</div></div>
      </div>
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
(function(){
  let kun = 30, filialId = 0;
  let chSotuv = null, chOmbor = null;

  // ── Yordamchilar ─────────────────────────────────────────
  const cssVar = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim() || '#888';
  const fmtSom  = n => Math.round(n).toLocaleString('uz-UZ');
  const fmtQisq = n => {
    n = Math.round(n);
    if (Math.abs(n) >= 1e6) return (n/1e6).toFixed(1).replace(/\.0$/,'') + ' mln';
    if (Math.abs(n) >= 1e3) return Math.round(n/1e3) + ' ming';
    return String(n);
  };
  const fmtSana = s => { const d = new Date(s + 'T00:00'); return d.getDate() + '.' + (d.getMonth()+1); };

  function chartTheme() {
    return {
      text: cssVar('--muted') || '#6c757d',
      grid: (cssVar('--border') || '#e0e3e8') + '80',
    };
  }

  // ── Grafiklarni chizish ──────────────────────────────────
  function renderSotuv(d) {
    const t = chartTheme();
    const labels = d.labels.map(fmtSana);
    if (chSotuv) chSotuv.destroy();
    chSotuv = new Chart(document.getElementById('chart-sotuv'), {
      data: {
        labels,
        datasets: [
          { type:'line', label:'Daromad', data:d.sotuv, yAxisID:'y',
            borderColor:'#c9a055', backgroundColor:'rgba(201,160,85,.12)', fill:true,
            tension:.3, borderWidth:2, pointRadius:0, pointHoverRadius:4 },
          { type:'line', label:'Brutto foyda', data:d.foyda, yAxisID:'y',
            borderColor:'#27ae60', backgroundColor:'transparent',
            tension:.3, borderWidth:2, pointRadius:0, pointHoverRadius:4 },
          { type:'bar', label:'Cheklar', data:d.cheklar, yAxisID:'y1',
            backgroundColor:'rgba(41,128,185,.18)', borderColor:'rgba(41,128,185,.5)',
            borderWidth:1, borderRadius:3, barPercentage:.6 },
        ]
      },
      options: baseOpts(t, {
        y:  { position:'left',  ticks:{ callback:fmtQisq, color:t.text }, grid:{ color:t.grid } },
        y1: { position:'right', ticks:{ precision:0, color:t.text }, grid:{ drawOnChartArea:false }, title:{ display:true, text:'cheklar', color:t.text } },
      }, (ctx) => ctx.dataset.type === 'bar'
        ? ctx.dataset.label + ': ' + ctx.parsed.y + ' ta'
        : ctx.dataset.label + ': ' + fmtSom(ctx.parsed.y) + " so'm")
    });
  }

  function renderOmbor(d) {
    const t = chartTheme();
    const labels = d.labels.map(fmtSana);
    if (chOmbor) chOmbor.destroy();
    chOmbor = new Chart(document.getElementById('chart-ombor'), {
      data: {
        labels,
        datasets: [
          { type:'line', label:'Ombor qiymati', data:d.ombor, yAxisID:'y',
            borderColor:'#1a1a2e', backgroundColor:'rgba(26,26,46,.06)', fill:true,
            tension:.25, borderWidth:2, pointRadius:0, pointHoverRadius:4 },
          { type:'bar', label:'Kirim (partiya)', data:d.kirim, yAxisID:'y1',
            backgroundColor:'rgba(39,174,96,.55)', borderRadius:3, barPercentage:.7 },
          { type:'bar', label:'Chiqim (tannarx)', data:d.chiqim, yAxisID:'y1',
            backgroundColor:'rgba(231,76,60,.55)', borderRadius:3, barPercentage:.7 },
        ]
      },
      options: baseOpts(t, {
        y:  { position:'left',  ticks:{ callback:fmtQisq, color:t.text }, grid:{ color:t.grid } },
        y1: { position:'right', ticks:{ callback:fmtQisq, color:t.text }, grid:{ drawOnChartArea:false }, title:{ display:true, text:'kunlik oqim', color:t.text } },
      }, (ctx) => ctx.dataset.label + ': ' + fmtSom(ctx.parsed.y) + " so'm")
    });
  }

  function baseOpts(t, scales, tipLabel) {
    return {
      responsive:true, maintainAspectRatio:false,
      interaction:{ mode:'index', intersect:false },
      plugins:{
        legend:{ labels:{ color:t.text, boxWidth:12, font:{size:11} } },
        tooltip:{ callbacks:{ label:tipLabel } },
      },
      scales: Object.assign({ x:{ ticks:{ color:t.text, maxRotation:0, autoSkipPadding:16 }, grid:{ display:false } } }, scales),
    };
  }

  // ── Jadvallar ────────────────────────────────────────────
  function renderKam(rows, jami) {
    document.getElementById('kam-count').textContent = jami;
    const el = document.getElementById('kam-body');
    if (!rows.length) { el.innerHTML = '<div class="din-empty">✅ Tez tugaydigan mahsulot yo\'q</div>'; return; }
    const maxY = Math.max(...rows.map(r => r.yetadi_kun || 0), 1);
    el.innerHTML = `<div class="im-table-wrap"><table class="din-table">
      <thead><tr><th>Mahsulot</th><th>Kategoriya</th><th class="num">Qoldiq</th><th class="num">Kunlik sarf</th><th class="num">Yetadi</th></tr></thead>
      <tbody>${rows.map(r => {
        const yk = r.yetadi_kun;
        const w = yk != null ? Math.max(4, Math.round(yk / maxY * 60)) : 0;
        const pill = yk == null ? '<span class="pill pill-muted">—</span>'
          : yk <= 2 ? `<span class="pill pill-danger">${yk} kun</span>`
          : `<span class="pill pill-warn">${yk} kun</span>`;
        return `<tr>
          <td><strong>${esc(r.nomi)}</strong></td>
          <td class="text-muted">${esc(r.kategoriya || '—')}</td>
          <td class="num">${fmtSom(r.qoldiq)} ${esc(r.birlik||'')}</td>
          <td class="num text-muted">${(+r.kunlik_sarf).toFixed(1)}</td>
          <td class="num"><span class="din-bar" style="width:${w}px"></span> ${pill}</td>
        </tr>`;
      }).join('')}</tbody></table></div>`;
  }

  function renderUxlab(rows, jami) {
    document.getElementById('uxlab-summa').textContent = fmtSom(jami) + " so'm muzlagan";
    const el = document.getElementById('uxlab-body');
    if (!rows.length) { el.innerHTML = '<div class="din-empty">✅ Uzoq turgan zaxira yo\'q</div>'; return; }
    el.innerHTML = `<div class="im-table-wrap"><table class="din-table">
      <thead><tr><th>Mahsulot</th><th>Kategoriya</th><th class="num">Qoldiq</th><th class="num">Qiymat</th><th class="num">Oxirgi sotuv</th></tr></thead>
      <tbody>${rows.map(r => {
        const hk = r.harakatsiz_kun;
        const oxirgi = r.oxirgi_sotuv
          ? `${fmtSana(r.oxirgi_sotuv)} · <span class="text-muted">${hk} kun oldin</span>`
          : '<span class="pill pill-danger">hech qachon</span>';
        return `<tr>
          <td><strong>${esc(r.nomi)}</strong></td>
          <td class="text-muted">${esc(r.kategoriya || '—')}</td>
          <td class="num">${fmtSom(r.qoldiq)} ${esc(r.birlik||'')}</td>
          <td class="num"><strong>${fmtSom(r.qiymat)}</strong> so'm</td>
          <td class="num">${oxirgi}</td>
        </tr>`;
      }).join('')}</tbody></table></div>`;
  }

  function renderKpi(d) {
    const sum = a => a.reduce((s,x) => s + x, 0);
    const kpi = [
      ['Daromad (' + d.kun + ' kun)', fmtSom(sum(d.sotuv)) + " so'm", 'var(--accent-dark)'],
      ['Brutto foyda',                fmtSom(sum(d.foyda)) + " so'm", 'var(--success)'],
      ['Cheklar',                     sum(d.cheklar) + ' ta',        'var(--info)'],
      ['Ombor qiymati (hozir)',       fmtSom(d.ombor_hozir) + " so'm", 'var(--text)'],
    ];
    document.getElementById('din-kpi').innerHTML = kpi.map(([l,v,c]) =>
      `<div class="k"><div class="lbl">${l}</div><div class="val" style="color:${c}">${v}</div></div>`).join('');
  }

  function esc(s){ return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

  // ── Yuklash ──────────────────────────────────────────────
  async function yukla() {
    document.getElementById('din-info').textContent = 'yuklanmoqda…';
    try {
      const res = await fetch(`${window.im_BASE}admin/ajax/analitika-data.php?kun=${kun}&filial_id=${filialId}`);
      const j = await res.json();
      if (j.status !== 'ok') { NHToast.error(j.msg || 'Xatolik'); return; }
      const d = j.data;
      renderKpi(d);
      renderSotuv(d);
      renderOmbor(d);
      renderKam(d.kam_qolgan, d.kam_jami);
      renderUxlab(d.uxlab, d.uxlab_jami_qiymat);
      document.getElementById('din-info').textContent =
        `${d.labels[0]} — ${d.labels[d.labels.length-1]}`;
    } catch (e) {
      NHToast.error('Yuklab bo\'lmadi');
      document.getElementById('din-info').textContent = '';
    }
  }

  // ── Hodisalar ────────────────────────────────────────────
  document.getElementById('din-range').addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    kun = +b.dataset.kun;
    document.querySelectorAll('#din-range button').forEach(x => x.classList.toggle('active', x === b));
    yukla();
  });
  document.getElementById('din-filial')?.addEventListener('change', e => { filialId = +e.target.value; yukla(); });
  document.getElementById('btn-refresh').addEventListener('click', yukla);

  // Mavzu almashsa grafik ranglari yangilansin (main.js data-theme atributini o'zgartiradi)
  let _themeT;
  new MutationObserver(() => { clearTimeout(_themeT); _themeT = setTimeout(yukla, 120); })
    .observe(document.documentElement, { attributes:true, attributeFilter:['data-theme'] });

  yukla();
})();
</script>
</body>
</html>
