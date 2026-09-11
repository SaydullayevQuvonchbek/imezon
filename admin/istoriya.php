<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();

// ── Filtr parametrlari ────────────────────────────────────────
$filtr_jadval = $_GET['jadval'] ?? '';
$filtr_amal   = $_GET['amal']   ?? '';
$filtr_xodim  = (int)($_GET['xodim'] ?? 0);
$dan          = $_GET['dan']    ?? date('Y-m-d');
$gacha        = $_GET['gacha']  ?? date('Y-m-d');
// Sana WHERE ichiga XOM tushardi — SQL in'ektsiya. Boshqa hisobot
// sahifalaridagi bilan bir xil tekshiruv:
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dan))   $dan   = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $gacha)) $gacha = date('Y-m-d');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 50;
$offset       = ($page - 1) * $limit;

// WHERE
$where = ["DATE(h.sana) BETWEEN '$dan' AND '$gacha'"];
if ($filtr_jadval) $where[] = "h.jadval = '" . mysqli_real_escape_string($link, $filtr_jadval) . "'";
if ($filtr_amal)   $where[] = "h.amal   = '" . mysqli_real_escape_string($link, $filtr_amal) . "'";
if ($filtr_xodim)  $where[] = "h.xodim_id = $filtr_xodim";
$where_sql = 'WHERE ' . implode(' AND ', $where);

$total = (int)$db->val("SELECT COUNT(*) FROM im_istoriya h $where_sql");
$rows  = $db->rows("
    SELECT h.*, x.ism AS x_ism
    FROM im_istoriya h
    LEFT JOIN im_xodimlar x ON x.id = h.xodim_id
    $where_sql
    ORDER BY h.sana DESC
    LIMIT $limit OFFSET $offset
");

// Filtr uchun ro'yxatlar
$jadvallar = $db->rows("SELECT DISTINCT jadval FROM im_istoriya ORDER BY jadval");
$xodimlar  = $db->rows("SELECT DISTINCT h.xodim_id, h.xodim_ism FROM im_istoriya h WHERE h.xodim_id > 0 ORDER BY h.xodim_ism");

$amal_colors = [
    'insert' => 'success', 'update' => 'warning', 'delete' => 'danger',
    'login'  => 'primary',  'login_xato' => 'danger',
    'logout' => 'muted',   'export' => 'info', 'other' => 'muted'
];
$amal_labels = [
    'insert'=>'➕ Yaratildi','update'=>'✏️ O\'zgardi','delete'=>'🗑 O\'chirildi',
    'login'=>'🔐 Kirdi','login_xato'=>'⛔ Kirish xatosi',
    'logout'=>'🚪 Chiqdi','export'=>'📤 Export','other'=>'• Boshqa'
];
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<title>Istoriya — IMezon Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="im-layout">
  <?php include __DIR__ . '/navbar.php'; ?>
  <main class="im-main">

    <div class="im-page-header">
      <div>
        <h1 class="im-page-title"><i class="bi bi-clock-history"></i> Tizim Tarixi (Istoriya)</h1>
        <p class="im-page-sub">Barcha amallar: insert / update / delete / login</p>
      </div>
      <span class="im-badge im-badge-primary" style="font-size:15px">
        Jami: <?= number_format($total) ?> ta yozuv
      </span>
    </div>

    <!-- ── Filtr panel ───────────────────────────────────────── -->
    <form method="GET" class="im-card mb-4">
      <div class="im-card-body p-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-2">
            <label class="im-label">Dan</label>
            <input type="date" class="im-input" name="dan" value="<?= $dan ?>">
          </div>
          <div class="col-md-2">
            <label class="im-label">Gacha</label>
            <input type="date" class="im-input" name="gacha" value="<?= $gacha ?>">
          </div>
          <div class="col-md-2">
            <label class="im-label">Jadval</label>
            <select class="im-input" name="jadval">
              <option value="">— Barchasi —</option>
              <?php foreach ($jadvallar as $j): ?>
              <option value="<?= im_f($j['jadval']) ?>" <?= $filtr_jadval===$j['jadval']?'selected':'' ?>>
                <?= im_f($j['jadval']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="im-label">Amal</label>
            <select class="im-input" name="amal">
              <option value="">— Barchasi —</option>
              <?php foreach ($amal_labels as $v=>$lbl): ?>
              <option value="<?= $v ?>" <?= $filtr_amal===$v?'selected':'' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="im-label">Xodim</label>
            <select class="im-input" name="xodim">
              <option value="">— Barchasi —</option>
              <?php foreach ($xodimlar as $x): ?>
              <option value="<?= $x['xodim_id'] ?>" <?= $filtr_xodim==$x['xodim_id']?'selected':'' ?>>
                <?= im_f($x['xodim_ism']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="im-btn im-btn-primary w-100">
              <i class="bi bi-funnel-fill"></i> Filtr
            </button>
            <a href="istoriya.php" class="im-btn im-btn-outline">✕</a>
          </div>
        </div>
      </div>
    </form>

    <!-- ── Jadval ───────────────────────────────────────────── -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-clock-history" style="color:var(--primary)"></i>
        <span class="im-card-title">Tarix logi</span>
        <span class="im-badge im-badge-muted"><?= $total ?> ta / sahifa <?= $page ?></span>
      </div>

      <?php if ($rows): ?>
      <div class="im-table-wrap">
        <table class="im-table" style="font-size:12.5px">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Sana / Vaqt</th>
              <th>Xodim</th>
              <th>Jadval</th>
              <th>ID</th>
              <th>Amal</th>
              <th>Izoh</th>
              <th style="width:80px">Tafsilot</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $r):
            $aclr = $amal_colors[$r['amal']] ?? 'muted';
          ?>
          <tr>
            <td class="text-muted fs-xs"><?= $offset + $i + 1 ?></td>
            <td class="text-muted fs-xs" style="white-space:nowrap">
              <?= date('d.m.Y', strtotime($r['sana'])) ?><br>
              <span style="color:var(--primary)"><?= date('H:i:s', strtotime($r['sana'])) ?></span>
            </td>
            <td>
              <span class="fw-semibold"><?= im_f($r['xodim_ism'] ?: ($r['x_ism'] ?: 'Tizim')) ?></span><br>
              <span class="text-muted fs-xs"><?= im_f($r['ip_adres'] ?: '') ?></span>
            </td>
            <td>
              <code style="background:var(--bg);padding:2px 6px;border-radius:4px;font-size:11px">
                <?= im_f($r['jadval']) ?>
              </code>
            </td>
            <td class="text-muted fs-xs num"><?= $r['ob_id'] ?: '—' ?></td>
            <td>
              <span class="im-badge im-badge-<?= $aclr ?>">
                <?= $amal_labels[$r['amal']] ?? $r['amal'] ?>
              </span>
            </td>
            <td class="text-muted fs-xs"><?= im_f($r['izoh'] ?: '—') ?></td>
            <td>
              <?php if ($r['eski'] || $r['yangi']): ?>
              <button class="im-btn im-btn-outline im-btn-sm"
                onclick="showDetail(<?= $r['id'] ?>,'<?= im_js($r['eski'] ?: 'null') ?>','<?= im_js($r['yangi'] ?: 'null') ?>')"
                title="Tafsilotni ko'rish">
                <i class="bi bi-eye"></i>
              </button>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total > $limit):
        $pages = ceil($total / $limit);
        $base = '?' . http_build_query(array_merge($_GET, ['page' => '']));
      ?>
      <div class="p-3 d-flex gap-2 align-items-center" style="border-top:1px solid var(--border)">
        <?php if ($page > 1): ?>
        <a href="<?= $base . ($page-1) ?>" class="im-btn im-btn-outline im-btn-sm">← Oldingi</a>
        <?php endif; ?>
        <span class="text-muted fs-sm"><?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?>
        <a href="<?= $base . ($page+1) ?>" class="im-btn im-btn-outline im-btn-sm">Keyingi →</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="im-empty">
        <i class="bi bi-clock-history"></i>
        <h4>Tarix yozuvlari yo'q</h4>
        <p class="text-muted">Tanlangan sana va filtr bo'yicha hech narsa topilmadi</p>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>
</div>

<!-- Detail Modal -->
<div id="detail-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);align-items:center;justify-content:center">
  <div class="im-card" style="width:640px;max-width:95vw;max-height:88vh;overflow-y:auto">
    <div class="im-card-header" style="background:var(--primary)">
      <i class="bi bi-code-slash" style="color:var(--accent)"></i>
      <span class="im-card-title" style="color:#fff">O'zgarish tafsiloti</span>
      <button class="im-btn im-btn-icon im-btn-ghost ms-auto" onclick="document.getElementById('detail-modal').style.display='none'" style="color:#fff">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="im-card-body p-4">
      <div class="row g-3">
        <div class="col-6">
          <div class="im-label mb-1" style="color:var(--danger)">🔴 Oldin (Eski)</div>
          <pre id="detail-eski" style="background:var(--bg);padding:12px;border-radius:8px;font-size:12px;overflow-x:auto;max-height:300px;margin:0;white-space:pre-wrap;border:1px solid var(--border)"></pre>
        </div>
        <div class="col-6">
          <div class="im-label mb-1" style="color:var(--success)">🟢 Keyin (Yangi)</div>
          <pre id="detail-yangi" style="background:var(--bg);padding:12px;border-radius:8px;font-size:12px;overflow-x:auto;max-height:300px;margin:0;white-space:pre-wrap;border:1px solid var(--border)"></pre>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
function showDetail(id, eski, yangi) {
  function fmt(str) {
    try { return JSON.stringify(JSON.parse(str), null, 2); }
    catch(e) { return str === 'null' || !str ? '(bo\'sh)' : str; }
  }
  document.getElementById('detail-eski').textContent  = fmt(eski);
  document.getElementById('detail-yangi').textContent = fmt(yangi);
  document.getElementById('detail-modal').style.display = 'flex';
}
document.getElementById('detail-modal').addEventListener('click', function(e){
  if (e.target === this) this.style.display = 'none';
});
</script>
</body></html>
