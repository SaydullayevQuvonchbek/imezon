<?php
// ============================================================
//  IMezon — Bosh kassir (xazina) paneli
// ------------------------------------------------------------
//  Bu rol markaz kassasini boshqaradi: do'konlardan pul qabul
//  qiladi, harajat va oylik to'laydi, qarzlarni yuritadi.
//
//  Sahifalar NUSXALANMAGAN: harajat, maosh, qarz, balans, sotuv
//  ekranlari admin bilan BIR XIL fayllar (admin/*.php) — ular
//  im_rol_check(['admin','bosh_kassir']) bilan ikkala rolni ham
//  qabul qiladi, navbar esa rolga qarab qisqaradi. Nusxa olish
//  vaqt o'tib ikki xil mantiqqa ajralib ketardi.
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

$bugun = date('Y-m-d');

// ─── Markaz (yagona) kassasi — filial_id = 0 ────────────────
$markaz = $db->row("SELECT * FROM im_kassa WHERE filial_id=0 LIMIT 1")
        ?: ['naqd_balans'=>0,'karta_balans'=>0,'bank_balans'=>0,'usd_balans'=>0];
$usd_kurs   = im_usd_kurs();
$markaz_som = (float)$markaz['naqd_balans'] + (float)$markaz['karta_balans']
            + (float)$markaz['bank_balans'] + (float)$markaz['usd_balans'] * $usd_kurs;

// ─── Do'konlardagi pul — filial_id > 0 ──────────────────────
$dukonlar = $db->rows(
    "SELECT k.*, COALESCE(f.nomi, CONCAT('Filial #', k.filial_id)) AS filial_nomi
     FROM im_kassa k LEFT JOIN im_filiallar f ON f.id = k.filial_id
     WHERE k.filial_id > 0 ORDER BY k.filial_id"
);
$dukon_jami = 0;
foreach ($dukonlar as $d) {
    $dukon_jami += (float)$d['naqd_balans'] + (float)$d['karta_balans']
                 + (float)$d['bank_balans'] + (float)$d['usd_balans'] * $usd_kurs;
}

// ─── Kutilayotgan inkasasiya — kassirning asosiy ishi ───────
$kutayotgan = $db->rows(
    "SELECT i.*, x.ism AS xodim_ism, COALESCE(f.nomi, CONCAT('Filial #', i.filial_id)) AS filial_nomi
     FROM im_inkasasiya i
     LEFT JOIN im_xodimlar x ON x.id = i.xodim_id
     LEFT JOIN im_filiallar f ON f.id = i.filial_id
     WHERE i.holat = 'kutilmoqda'
     ORDER BY i.sana ASC"
);
$kutayotgan_summa = 0;
foreach ($kutayotgan as $k) $kutayotgan_summa += (float)$k['summa'];

// ─── Bugungi ko'rsatkichlar ─────────────────────────────────
$bug_tushum = (float)$db->val(
    "SELECT COALESCE(SUM(tolov_summa),0) FROM im_sotuvlar
     WHERE DATE(sana)='$bugun' AND holat='aktiv'"
);
$bug_chek = (int)$db->val("SELECT COUNT(*) FROM im_sotuvlar WHERE DATE(sana)='$bugun' AND holat='aktiv'");
$bug_harajat = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_harajatlar WHERE sana='$bugun'");
$bug_maosh   = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_maosh_tarixi WHERE sana='$bugun'");
$bug_qabul   = (float)$db->val(
    "SELECT COALESCE(SUM(summa),0) FROM im_inkasasiya
     WHERE holat='qabul_qilindi' AND DATE(qabul_sana)='$bugun'"
);

// ─── Qarzlar ────────────────────────────────────────────────
$ps_qarz    = (float)$db->val("SELECT COALESCE(SUM(qoldiq),0) FROM im_postavshik_qarz WHERE status='ochiq'");
$mij_nasiya = (float)$db->val("SELECT COALESCE(SUM(qoldiq),0) FROM im_nasiya WHERE holat='aktiv'");
$muddati_otgan = (int)$db->val(
    "SELECT COUNT(*) FROM im_postavshik_qarz WHERE status='ochiq' AND muddat IS NOT NULL AND muddat < CURDATE()"
);

// ─── So'nggi 14 kun tushumi (mini Chart.js grafik) ──────────
$hafta_xom = [];
foreach ($db->rows(
    "SELECT DATE(sana) kun, COALESCE(SUM(tolov_summa),0) jami, COUNT(*) soni
     FROM im_sotuvlar WHERE sana >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND holat='aktiv'
     GROUP BY DATE(sana)"
) as $r) $hafta_xom[$r['kun']] = $r;
$hafta = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $hafta[] = ['kun' => $d, 'jami' => (float)($hafta_xom[$d]['jami'] ?? 0), 'soni' => (int)($hafta_xom[$d]['soni'] ?? 0)];
}
$hafta_max = 1;
foreach ($hafta as $h) $hafta_max = max($hafta_max, (float)$h['jami']);

// ─── Oxirgi to'lovlar (harajat + maosh birga) ───────────────
$oxirgi = $db->rows(
    "(SELECT 'harajat' tur, h.nomi, h.summa, h.tolov_turi, h.sana, x.ism xodim
      FROM im_harajatlar h LEFT JOIN im_xodimlar x ON x.id=h.xodim_id
      ORDER BY h.id DESC LIMIT 8)
     UNION ALL
     (SELECT 'maosh' tur, CONCAT('Oylik — ', COALESCE(w.ism,'?')) nomi, m.summa, m.tolov_turi, m.sana,
             x.ism xodim
      FROM im_maosh_tarixi m
      LEFT JOIN im_workers w ON w.id=m.worker_id
      LEFT JOIN im_xodimlar x ON x.id=m.beruvchi_id
      ORDER BY m.id DESC LIMIT 8)
     ORDER BY sana DESC LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Kassa holati | IMezon</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
<style>
.kassa-big{font-size:30px;font-weight:800;font-variant-numeric:tabular-nums;line-height:1.15}
.kassa-row{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px dashed var(--border);font-size:13.5px}
.kassa-row:last-child{border-bottom:0}
.kassa-row .v{font-variant-numeric:tabular-nums;font-weight:600}
</style>
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/../admin/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title"><i class="bi bi-safe2-fill me-1"></i> Kassa holati</div>
    <div class="im-topbar-actions">
      <span class="im-badge im-badge-muted" style="font-size:12px">
        💱 1 USD = <?= im_money($usd_kurs) ?> so'm
      </span>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <?php if ($kutayotgan): ?>
    <!-- Kassirning asosiy ishi eng tepada: qabul qilinmagan pul -->
    <div class="im-card mb-3" style="border-left:4px solid var(--warning)">
      <div class="im-card-body d-flex flex-wrap align-items-center gap-3">
        <div style="font-size:30px">📥</div>
        <div style="flex:1;min-width:220px">
          <div style="font-weight:700;font-size:16px">
            <?= count($kutayotgan) ?> ta do'kon pulni topshirdi — qabul kutilmoqda
          </div>
          <div class="text-muted fs-xs">
            Jami <b><?= im_money($kutayotgan_summa) ?> so'm</b>.
            Qabul qilinmaguncha bu pul markaz kassasiga tushmaydi.
          </div>
        </div>
        <a href="<?= im_BASE ?>admin/inkasasiya.php" class="im-btn im-btn-warning">
          <i class="bi bi-box-arrow-in-down"></i> Qabul qilish
        </a>
      </div>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
      <!-- Yagona kassa -->
      <div class="col-md-4">
        <div class="im-card h-100" style="border-top:3px solid var(--success)">
          <div class="im-card-header">
            <i class="bi bi-safe2-fill" style="color:var(--success)"></i>
            <span class="im-card-title">Yagona kassa (markaz)</span>
          </div>
          <div class="im-card-body">
            <div class="kassa-big" style="color:var(--success)"><?= im_money($markaz_som) ?> <span style="font-size:15px">so'm</span></div>
            <div class="text-muted fs-xs mb-2">USD kursi bo'yicha jami</div>
            <div class="kassa-row"><span><?= im_tt_label('naqd') ?></span><span class="v"><?= im_money($markaz['naqd_balans']) ?></span></div>
            <div class="kassa-row"><span><?= im_tt_label('karta') ?></span><span class="v"><?= im_money($markaz['karta_balans']) ?></span></div>
            <div class="kassa-row"><span><?= im_tt_label('bank', true) ?></span><span class="v"><?= im_money($markaz['bank_balans']) ?></span></div>
            <div class="kassa-row"><span><?= im_tt_label('usd') ?></span><span class="v">$<?= number_format((float)$markaz['usd_balans'], 2) ?></span></div>
          </div>
        </div>
      </div>

      <!-- Do'konlardagi pul -->
      <div class="col-md-4">
        <div class="im-card h-100" style="border-top:3px solid var(--accent-dark)">
          <div class="im-card-header">
            <i class="bi bi-shop" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Do'konlardagi pul</span>
          </div>
          <div class="im-card-body">
            <div class="kassa-big"><?= im_money($dukon_jami) ?> <span style="font-size:15px">so'm</span></div>
            <div class="text-muted fs-xs mb-2">Hali markazga topshirilmagan</div>
            <?php if ($dukonlar): foreach ($dukonlar as $d):
              $d_jami = (float)$d['naqd_balans'] + (float)$d['karta_balans']
                      + (float)$d['bank_balans'] + (float)$d['usd_balans'] * $usd_kurs; ?>
            <div class="kassa-row">
              <span><?= im_f($d['filial_nomi']) ?></span>
              <span class="v"><?= im_money($d_jami) ?></span>
            </div>
            <?php endforeach; else: ?>
            <div class="text-muted fs-xs">Filial kassasi yo'q</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Bugun -->
      <div class="col-md-4">
        <div class="im-card h-100" style="border-top:3px solid var(--info)">
          <div class="im-card-header">
            <i class="bi bi-calendar-day" style="color:var(--info)"></i>
            <span class="im-card-title">Bugun — <?= im_date($bugun) ?></span>
          </div>
          <div class="im-card-body">
            <div class="kassa-big" style="color:var(--info)"><?= im_money($bug_tushum) ?> <span style="font-size:15px">so'm</span></div>
            <div class="text-muted fs-xs mb-2"><?= $bug_chek ?> ta chek<?= $bug_chek ? ' · o\'rtacha ' . im_money($bug_tushum / $bug_chek) : '' ?></div>
            <div class="kassa-row"><span>📥 Do'konlardan qabul</span><span class="v"><?= im_money($bug_qabul) ?></span></div>
            <div class="kassa-row"><span>🧾 Harajat</span><span class="v" style="color:var(--danger)">−<?= im_money($bug_harajat) ?></span></div>
            <div class="kassa-row"><span>💼 Oylik</span><span class="v" style="color:var(--danger)">−<?= im_money($bug_maosh) ?></span></div>
            <div class="kassa-row"><span><b>Sof harakat</b></span>
              <span class="v"><b><?= im_money($bug_qabul - $bug_harajat - $bug_maosh) ?></b></span></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <!-- Qarzlar -->
      <div class="col-md-5">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-truck-flatbed" style="color:var(--danger)"></i>
            <span class="im-card-title">Qarzlar</span>
            <a href="<?= im_BASE ?>admin/qarzlar.php" class="im-btn im-btn-ghost im-btn-sm ms-auto">
              Boshqarish <i class="bi bi-arrow-right"></i>
            </a>
          </div>
          <div class="im-card-body">
            <div class="kassa-row">
              <span>Postavshiklarga qarzimiz</span>
              <span class="v" style="color:var(--warning)"><?= im_money($ps_qarz) ?> so'm</span>
            </div>
            <div class="kassa-row">
              <span>Mijozlardan nasiya (olishimiz)</span>
              <span class="v" style="color:var(--danger)"><?= im_money($mij_nasiya) ?> so'm</span>
            </div>
            <div class="kassa-row">
              <span><b>Sof qarz holati</b></span>
              <span class="v"><b><?= im_money($mij_nasiya - $ps_qarz) ?> so'm</b></span>
            </div>
            <?php if ($muddati_otgan): ?>
            <div class="mt-2 p-2" style="background:rgba(220,53,69,.08);border-radius:8px;font-size:13px;color:var(--danger)">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <b><?= $muddati_otgan ?> ta</b> qarzning muddati o'tib ketgan
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- 14 kunlik tushum -->
      <div class="col-md-7">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-graph-up-arrow" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">So'nggi 14 kun tushumi</span>
            <a href="<?= im_BASE ?>admin/analitika.php" class="im-btn im-btn-ghost im-btn-sm ms-auto">
              To'liq dinamika <i class="bi bi-arrow-right"></i>
            </a>
          </div>
          <div class="im-card-body p-3">
            <div style="position:relative;height:210px"><canvas id="kassa-tushum-chart"></canvas></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Oxirgi to'lovlar -->
    <div class="im-card">
      <div class="im-card-header">
        <i class="bi bi-clock-history" style="color:var(--muted)"></i>
        <span class="im-card-title">Oxirgi to'lovlar</span>
        <div class="ms-auto d-flex gap-2">
          <a href="<?= im_BASE ?>admin/harajatlar.php" class="im-btn im-btn-outline im-btn-sm">
            <i class="bi bi-receipt-cutoff"></i> Harajat
          </a>
          <a href="<?= im_BASE ?>admin/maosh.php" class="im-btn im-btn-outline im-btn-sm">
            <i class="bi bi-cash-stack"></i> Oylik
          </a>
        </div>
      </div>
      <?php if ($oxirgi): ?>
      <div class="im-table-wrap">
        <table class="im-table" style="font-size:13.5px">
          <thead><tr>
            <th>Sana</th><th>Tur</th><th>Nomi</th><th>To'lov</th>
            <th class="text-end">Summa</th><th>Kim</th>
          </tr></thead>
          <tbody>
          <?php foreach ($oxirgi as $o): ?>
          <tr>
            <td class="text-muted fs-xs" style="white-space:nowrap"><?= im_date($o['sana']) ?></td>
            <td>
              <span class="im-badge im-badge-<?= $o['tur'] === 'maosh' ? 'info' : 'muted' ?>">
                <?= $o['tur'] === 'maosh' ? '💼 Oylik' : '🧾 Harajat' ?>
              </span>
            </td>
            <td><?= im_f($o['nomi']) ?></td>
            <td class="fs-xs"><?= im_tt_label($o['tolov_turi'], true) ?></td>
            <td class="text-end num" style="color:var(--danger)">−<?= im_money($o['summa']) ?></td>
            <td class="text-muted fs-xs"><?= im_f($o['xodim'] ?: '—') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-card-body"><div class="im-empty">Hali to'lov qilinmagan</div></div>
      <?php endif; ?>
    </div>

  </main>
</div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
// ── Kassa: 14 kunlik tushum mini-grafigi ──────────────────
(function(){
  const cv = document.getElementById('kassa-tushum-chart');
  if (!cv || typeof Chart === 'undefined') return;
  const H = <?= json_encode($hafta, JSON_UNESCAPED_UNICODE) ?>;
  const cssVar = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim() || '#888';
  const txt = cssVar('--muted'), grid = cssVar('--border') + '80';
  const labels = H.map(h => { const d = new Date(h.kun + 'T00:00'); return d.getDate() + '.' + (d.getMonth()+1); });
  const qisq = n => Math.abs(n) >= 1e6 ? (n/1e6).toFixed(1).replace(/\.0$/,'') + ' mln'
                  : Math.abs(n) >= 1e3 ? Math.round(n/1e3) + ' ming' : String(Math.round(n));
  new Chart(cv, {
    data: { labels, datasets: [
      { type:'line', label:'Tushum', data:H.map(h=>+h.jami), yAxisID:'y',
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
</body>
</html>
