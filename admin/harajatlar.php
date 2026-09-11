<?php
// ============================================================
//  IMezon — Admin: Harajatlar (Ko'rish + Filter + Statistika)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

// ── Filtrlar ─────────────────────────────────────────────
$dan    = $_GET['dan']    ?? date('Y-m-01');
$gacha  = $_GET['gacha']  ?? date('Y-m-d');
$tur_f  = $_GET['tur']    ?? '';
$fil_f  = (int)($_GET['filial'] ?? 0);
$xod_f  = (int)($_GET['xodim']  ?? 0);

$dan   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dan)   ? $dan   : date('Y-m-01');
$gacha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $gacha) ? $gacha : date('Y-m-d');
$ds    = mysqli_real_escape_string($link, $dan);
$gs    = mysqli_real_escape_string($link, $gacha);

$where = "h.sana BETWEEN '$ds 00:00:00' AND '$gs 23:59:59'";
if ($tur_f) $where .= " AND h.tur='" . mysqli_real_escape_string($link, $tur_f) . "'";
if ($fil_f) $where .= " AND h.filial_id=$fil_f";
if ($xod_f) $where .= " AND h.xodim_id=$xod_f";

// ── Harajatlar ro'yxati ────────────────────────────────
$harajatlar = $db->rows(
    "SELECT h.*,
            x.ism AS xodim_ism,
            f.nomi AS filial_nomi
     FROM im_harajatlar h
     LEFT JOIN im_xodimlar x ON x.id = h.xodim_id
     LEFT JOIN im_filiallar f ON f.id = h.filial_id
     WHERE $where
     ORDER BY h.sana DESC, h.created_at DESC
     LIMIT 500"
);

// ── Statistika ────────────────────────────────────────
$jami_summa = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_harajatlar h WHERE $where");

// Tur bo'yicha
$by_tur = $db->rows("SELECT tur, SUM(summa) AS s, COUNT(*) AS n FROM im_harajatlar h WHERE $where GROUP BY tur ORDER BY s DESC");

// Kunlik dinamika
$kunlik = $db->rows("SELECT sana, SUM(summa) AS s, COUNT(*) AS n FROM im_harajatlar h WHERE $where GROUP BY sana ORDER BY sana ASC");

// To'lov turi bo'yicha
$by_tolov = $db->rows("SELECT tolov_turi, SUM(summa) AS s FROM im_harajatlar h WHERE $where GROUP BY tolov_turi ORDER BY s DESC");

// Xodim bo'yicha
$by_xodim = $db->rows(
    "SELECT x.ism, SUM(h.summa) AS s, COUNT(*) AS n
     FROM im_harajatlar h
     LEFT JOIN im_xodimlar x ON x.id=h.xodim_id
     WHERE $where GROUP BY h.xodim_id ORDER BY s DESC"
);

// Filtrlar uchun
$filiallar  = $db->rows("SELECT id, nomi FROM im_filiallar ORDER BY nomi");
$xodimlar   = $db->rows("SELECT id, ism FROM im_xodimlar WHERE status=1 ORDER BY ism");

$tur_icons  = ['ijara'=>'🏠','maosh'=>'👤','yuk'=>'🚛','kommunal'=>'💡','reklama'=>'📢','boshqa'=>'📋'];
$tt_icons   = ['naqd'=>'💵','karta'=>'💳','bank'=>'🏦'];

$shortcuts = [
    'Bugun'     => [date('Y-m-d'), date('Y-m-d')],
    'Kecha'     => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
    'Bu hafta'  => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
    'Bu oy'     => [date('Y-m-01'), date('Y-m-d')],
    "O'tgan oy" => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
];
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Harajatlar | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-cash-stack me-1"></i> Harajatlar</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-primary im-btn-sm" onclick="window.openHarajatModal()">
        <i class="bi bi-plus-lg"></i> Harajat kiritish
      </button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>

  <main class="im-content">

    <!-- ── Sana filtri ─────────────────────────────────── -->
    <div class="im-card mb-4">
      <div class="im-card-body p-3">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
          <div>
            <label class="im-label" style="font-size:11px">Dan</label>
            <input type="date" name="dan" class="im-input im-input-sm" value="<?= $dan ?>" style="max-width:145px">
          </div>
          <div>
            <label class="im-label" style="font-size:11px">Gacha</label>
            <input type="date" name="gacha" class="im-input im-input-sm" value="<?= $gacha ?>" style="max-width:145px">
          </div>
          <div>
            <label class="im-label" style="font-size:11px">Tur</label>
            <select name="tur" class="im-select im-input-sm" style="max-width:140px">
              <option value="">Barchasi</option>
              <?php foreach (['ijara','maosh','yuk','kommunal','reklama','boshqa'] as $t): ?>
              <option value="<?= $t ?>" <?= $tur_f===$t?'selected':'' ?>><?= ($tur_icons[$t]??'📋').' '.ucfirst($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($filiallar): ?>
          <div>
            <label class="im-label" style="font-size:11px">Filial</label>
            <select name="filial" class="im-select im-input-sm" style="max-width:150px">
              <option value="">Barchasi</option>
              <?php foreach ($filiallar as $f): ?>
              <option value="<?= $f['id'] ?>" <?= $fil_f==$f['id']?'selected':'' ?>><?= im_f($f['nomi']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <?php if ($xodimlar): ?>
          <div>
            <label class="im-label" style="font-size:11px">Xodim</label>
            <select name="xodim" class="im-select im-input-sm" style="max-width:150px">
              <option value="">Barchasi</option>
              <?php foreach ($xodimlar as $x): ?>
              <option value="<?= $x['id'] ?>" <?= $xod_f==$x['id']?'selected':'' ?>><?= im_f($x['ism']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <button type="submit" class="im-btn im-btn-dark im-btn-sm"><i class="bi bi-funnel"></i> Ko'rsat</button>
          <a href="?" class="im-btn im-btn-outline im-btn-sm">Tozalash</a>
          <div class="d-flex gap-1 flex-wrap ms-1">
            <?php foreach ($shortcuts as $lbl => [$d1, $d2]):
              $active = ($dan===$d1 && $gacha===$d2) ? 'im-btn-primary' : 'im-btn-outline'; ?>
            <a href="?dan=<?= $d1 ?>&gacha=<?= $d2 ?>&tur=<?= urlencode($tur_f) ?>"
               class="im-btn im-btn-sm <?= $active ?>"><?= $lbl ?></a>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Jami stat ──────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.1);color:var(--danger)">
            <i class="bi bi-cash-stack"></i>
          </div>
          <div>
            <div class="im-stat-label">Jami harajat</div>
            <div class="im-stat-value num" style="color:var(--danger)"><?= im_money($jami_summa) ?> so'm</div>
            <div class="text-muted fs-xs"><?= count($harajatlar) ?> ta yozuv</div>
          </div>
        </div>
      </div>
      <?php if ($by_tur): foreach (array_slice($by_tur, 0, 3) as $bt): ?>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--primary)">
          <div class="im-stat-icon" style="background:rgba(26,26,46,.07);color:var(--primary)">
            <span style="font-size:22px"><?= $tur_icons[$bt['tur']] ?? '📋' ?></span>
          </div>
          <div>
            <div class="im-stat-label"><?= ucfirst(im_f($bt['tur'])) ?></div>
            <div class="im-stat-value num" style="font-size:16px"><?= im_money($bt['s']) ?> so'm</div>
            <div class="text-muted fs-xs"><?= (int)$bt['n'] ?> ta</div>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="row g-3 mb-4">
      <!-- Tur bo'yicha taqsimot -->
      <div class="col-md-5">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-pie-chart-fill" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Tur bo'yicha</span>
          </div>
          <div class="im-card-body p-3">
            <?php if ($by_tur): ?>
            <?php $max_t = max(array_column($by_tur, 's')); ?>
            <?php foreach ($by_tur as $bt):
              $pct = $jami_summa > 0 ? round($bt['s'] / $jami_summa * 100) : 0; ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold fs-sm"><?= ($tur_icons[$bt['tur']]??'📋') ?> <?= im_f($bt['tur']) ?></span>
                <span class="fw-bold num fs-sm"><?= im_money($bt['s']) ?> <span class="text-muted fs-xs">(<?= $pct ?>%)</span></span>
              </div>
              <div style="background:var(--border);border-radius:4px;height:7px">
                <div style="width:<?= $pct ?>%;background:var(--danger);border-radius:4px;height:7px;opacity:.75"></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="text-center text-muted py-3 fs-sm">Ma'lumot yo'q</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- To'lov turi + Xodim -->
      <div class="col-md-7">
        <div class="row g-3 h-100">
          <div class="col-12">
            <div class="im-card">
              <div class="im-card-header">
                <i class="bi bi-credit-card-fill" style="color:var(--primary)"></i>
                <span class="im-card-title">To'lov turi bo'yicha</span>
              </div>
              <div class="im-table-wrap">
                <table class="im-table">
                  <thead><tr><th>To'lov turi</th><th class="text-right">Summa</th></tr></thead>
                  <tbody>
                    <?php foreach ($by_tolov as $bt): ?>
                    <tr>
                      <td><?= ($tt_icons[$bt['tolov_turi']]??'💰') ?> <?= im_f($bt['tolov_turi']) ?></td>
                      <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($bt['s']) ?> so'm</td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <?php if ($by_xodim && count($by_xodim) > 1): ?>
          <div class="col-12">
            <div class="im-card">
              <div class="im-card-header">
                <i class="bi bi-person-badge-fill" style="color:var(--primary)"></i>
                <span class="im-card-title">Xodim bo'yicha</span>
              </div>
              <div class="im-table-wrap">
                <table class="im-table">
                  <thead><tr><th>Xodim</th><th class="text-center">Soni</th><th class="text-right">Summa</th></tr></thead>
                  <tbody>
                    <?php foreach ($by_xodim as $bx): ?>
                    <tr>
                      <td class="fw-semibold"><?= im_f($bx['ism']?:'—') ?></td>
                      <td class="text-center"><span class="im-badge im-badge-muted"><?= (int)$bx['n'] ?> ta</span></td>
                      <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($bx['s']) ?> so'm</td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── Kunlik dinamika ────────────────────────────── -->
    <?php if (count($kunlik) > 1): ?>
    <div class="im-card mb-4">
      <div class="im-card-header">
        <i class="bi bi-calendar-week-fill" style="color:var(--danger)"></i>
        <span class="im-card-title">Kunlik dinamika</span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead><tr><th>Sana</th><th class="text-center">Yozuvlar</th><th class="text-right">Summa</th></tr></thead>
          <tbody>
            <?php foreach ($kunlik as $kk): ?>
            <tr>
              <td class="fw-semibold"><?= im_date($kk['sana']) ?></td>
              <td class="text-center"><span class="im-badge im-badge-muted"><?= (int)$kk['n'] ?> ta</span></td>
              <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($kk['s']) ?> so'm</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot style="background:var(--bg)">
            <tr>
              <td class="fw-bold">Jami</td>
              <td class="text-center fw-bold"><?= count($harajatlar) ?> ta</td>
              <td class="text-right fw-bold num" style="color:var(--danger)"><?= im_money($jami_summa) ?> so'm</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Batafsil ro'yxat ───────────────────────────── -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-list-ul" style="color:var(--danger)"></i>
        <span class="im-card-title">Harajatlar ro'yxati</span>
        <span class="im-badge im-badge-muted ms-1"><?= count($harajatlar) ?> ta</span>
        <span class="ms-auto fw-bold num" style="color:var(--danger)"><?= im_money($jami_summa) ?> so'm</span>
      </div>
      <?php if ($harajatlar): ?>
      <div class="im-table-wrap">
        <table class="im-table" id="harajat-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nomi</th>
              <th>Tur</th>
              <th>To'lov</th>
              <th>Filial</th>
              <th>Xodim</th>
              <th>Sana</th>
              <th class="text-right">Summa</th>
              <th>Izoh</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($harajatlar as $i => $h): ?>
            <tr>
              <td class="text-muted fs-xs"><?= $i + 1 ?></td>
              <td class="fw-semibold"><?= im_f($h['nomi']) ?></td>
              <td>
                <span class="im-badge im-badge-muted">
                  <?= ($tur_icons[$h['tur']] ?? '📋') ?> <?= im_f($h['tur']) ?>
                </span>
              </td>
              <td>
                <span class="im-badge im-badge-primary fs-xs">
                  <?= $tt_icons[$h['tolov_turi']] ?? '💰' ?> <?= im_f($h['tolov_turi']) ?>
                </span>
              </td>
              <td class="text-muted fs-xs"><?= im_f($h['filial_nomi'] ?: '—') ?></td>
              <td class="text-muted fs-xs"><?= im_f($h['xodim_ism'] ?: '—') ?></td>
              <td class="text-muted fs-xs"><?= im_date($h['sana']) ?></td>
              <td class="text-right num fw-bold" style="color:var(--danger)">
                <?= im_money($h['summa']) ?> so'm
              </td>
              <td class="text-muted fs-xs"><?= im_f($h['izoh'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot style="background:var(--bg);font-weight:700">
            <tr>
              <td colspan="7" class="fw-bold">Jami</td>
              <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($jami_summa) ?> so'm</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-cash-stack"></i>
        <h4>Harajat yo'q</h4>
        <p class="text-muted">Tanlangan davr uchun harajat topilmadi</p>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>
</div>
<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- ── HARAJAT KIRITISH MODAL ───────────────────────── -->
<div class="im-overlay" id="harajat-modal">
  <div class="im-modal" style="width:500px;max-width:96%">
    <div class="im-modal-header" style="background:var(--primary);color:#fff">
      <i class="bi bi-cash-stack"></i>
      <span class="im-modal-title">Yangi harajat</span>
      <button class="im-modal-close" data-modal-close style="color:#fff"><i class="bi bi-x-lg"></i></button>
    </div>
    <form id="form-harajat">
      <div class="im-modal-body row g-3">
        <div class="col-12">
          <label class="im-label">Harajat nomi <span class="text-danger">*</span></label>
          <input type="text" class="im-input" name="nomi" required placeholder="Masalan: Tushlik, Arenda...">
        </div>
        <div class="col-md-6">
          <label class="im-label">Summa (so'm) <span class="text-danger">*</span></label>
          <input type="number" class="im-input num" name="summa" min="1" required placeholder="0">
        </div>
        <div class="col-md-6">
          <label class="im-label">To'lov turi</label>
          <select name="tolov_turi" class="im-select">
            <option value="naqd"><?= im_tt_label('naqd') ?></option>
            <option value="karta"><?= im_tt_label('karta') ?></option>
            <option value="bank"><?= im_tt_label('bank') ?></option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="im-label">Filial (Kassa)</label>
          <select name="filial_id" class="im-select">
            <option value="0">Yagona Markaz kassasi</option>
            <?php foreach ($filiallar as $f): ?>
            <option value="<?= $f['id'] ?>"><?= im_f($f['nomi']) ?> kassasi</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="im-label">Tur / Toifa</label>
          <select name="tur" class="im-select">
            <option value="boshqa">📋 Boshqa</option>
            <option value="ijara">🏠 Ijara (Arenda)</option>
            <option value="maosh">👤 Maosh / Ish xaqi</option>
            <option value="yuk">🚛 Yuk / Yo'lkira</option>
            <option value="kommunal">💡 Kommunal / Internet</option>
            <option value="reklama">📢 Reklama</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="im-label">Sana</label>
          <input type="date" class="im-input" name="sana" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-12">
          <label class="im-label">Izoh</label>
          <input type="text" class="im-input" name="izoh" placeholder="Qo'shimcha izoh">
        </div>
      </div>
      <div class="im-modal-footer">
        <button type="button" class="im-btn im-btn-outline" data-modal-close>Bekor</button>
        <button type="submit" class="im-btn im-btn-primary" id="btn-save-h">
          <i class="bi bi-check2"></i> Saqlash
        </button>
      </div>
    </form>
  </div>
</div>

<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
window.openHarajatModal = function() {
  document.getElementById('form-harajat').reset();
  document.querySelector('[name=sana]').value = new Date().toISOString().split('T')[0];
  NHModal.open('harajat-modal');
};
document.getElementById('form-harajat').addEventListener('submit', async function(e){
  e.preventDefault();
  const btn = document.getElementById('btn-save-h');
  btn.disabled = true;
  btn.innerHTML = '<span class="im-spinner"></span>';
  
  const fd = new FormData(this);
  const res = await IMAjax.post(window.im_BASE + 'admin/ajax/harajat-save.php', fd);
  
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check2"></i> Saqlash';
  
  if (res.status === 'ok') {
    NHToast.success(res.msg);
    NHModal.close('harajat-modal');
    setTimeout(() => location.reload(), 600);
  } else {
    NHToast.error(res.msg);
  }
});
</script>
</body>
</html>
