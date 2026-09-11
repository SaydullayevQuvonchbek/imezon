<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);

$db = new Cyber();

// Filtrlar
$xodim_id  = (int)($_GET['xodim_id'] ?? 0);
$filial_id = (int)($_GET['filial_id'] ?? 0);
$sana_dan  = $_GET['sana_dan']  ?? date('Y-m-d');
$sana_gach = $_GET['sana_gach'] ?? date('Y-m-d');
$amal_f    = trim($_GET['amal'] ?? '');

// Jadval mavjudligini tekshiramiz
$table_ok = (bool)$db->val("SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema=DATABASE() AND table_name='im_xodim_log'");

$xodimlar  = $db->rows("SELECT id, ism, rol FROM im_xodimlar ORDER BY ism");
$filiallar = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY tartib");

$logs = [];
$total = 0;

if ($table_ok) {
    $where = "l.vaqt BETWEEN '$sana_dan 00:00:00' AND '$sana_gach 23:59:59'";
    if ($xodim_id)  $where .= " AND l.xodim_id=$xodim_id";
    if ($filial_id) $where .= " AND l.filial_id=$filial_id";
    if ($amal_f)    $where .= " AND l.amal='" . mysqli_real_escape_string($link, $amal_f) . "'";

    $total = (int)$db->val("SELECT COUNT(*) FROM im_xodim_log l WHERE $where");
    $logs  = $db->rows(
        "SELECT l.*, x.ism AS xodim_ism, x.rol, f.nomi AS filial_nomi
         FROM im_xodim_log l
         JOIN im_xodimlar x ON x.id = l.xodim_id
         LEFT JOIN im_filiallar f ON f.id = l.filial_id
         WHERE $where
         ORDER BY l.vaqt DESC
         LIMIT 500"
    );
}

// Amal nomi va rangi
$amallar = [
    'stol_band'          => ['🟡', 'warn',    'Stol band qilindi'],
    'oshpazga_yuborildi' => ['🔵', 'primary', 'Oshpazga yuborildi'],
    'kassaga_yuborildi'  => ['🟢', 'success', 'Kassaga yuborildi'],
    'bekor_qilindi'      => ['🔴', 'danger',  'Bekor qilindi'],
    'oshpaz_tasdiqladi'  => ['🍳', 'info',    'Oshpaz tasdiqlladi'],
];
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Xodim Tarixi | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-journal-text me-1"></i> Xodimlar Tarixi</div>
    <div class="im-topbar-actions">
      <?php if (!$table_ok): ?>
      <a href="ajax/create-xodim-log.php" class="im-btn im-btn-primary im-btn-sm" target="_blank">
        <i class="bi bi-database-add"></i> Jadvalni yaratish
      </a>
      <?php endif; ?>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <?php if (!$table_ok): ?>
    <div class="im-card" style="text-align:center;padding:40px">
      <i class="bi bi-database-x" style="font-size:48px;color:var(--danger);opacity:.6"></i>
      <p style="margin-top:12px;color:var(--muted)">
        <code>im_xodim_log</code> jadvali yaratilmagan.<br>
        <a href="ajax/create-xodim-log.php" target="_blank" class="im-btn im-btn-primary mt-3" style="display:inline-flex">
          <i class="bi bi-database-add me-2"></i> Jadvalni yaratish
        </a>
      </p>
    </div>
    <?php else: ?>

    <!-- FILTR -->
    <div class="im-card im-slide-in" style="margin-bottom:14px">
      <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:14px">
        <div>
          <label class="im-label">Xodim</label>
          <select name="xodim_id" class="im-input" style="min-width:160px">
            <option value="0">Barcha xodimlar</option>
            <?php foreach($xodimlar as $x): ?>
            <option value="<?= $x['id'] ?>" <?= $xodim_id==$x['id']?'selected':'' ?>>
              <?= im_f($x['ism']) ?> (<?= $x['rol'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="im-label">Filial</label>
          <select name="filial_id" class="im-input" style="min-width:140px">
            <option value="0">Barcha filiallar</option>
            <?php foreach($filiallar as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filial_id==$f['id']?'selected':'' ?>>
              <?= im_f($f['nomi']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="im-label">Amal</label>
          <select name="amal" class="im-input" style="min-width:160px">
            <option value="">Barcha amallar</option>
            <?php foreach($amallar as $key=>[$icon,$cls,$label]): ?>
            <option value="<?= $key ?>" <?= $amal_f===$key?'selected':'' ?>>
              <?= $icon ?> <?= $label ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="im-label">Dan</label>
          <input type="date" name="sana_dan" class="im-input" value="<?= $sana_dan ?>">
        </div>
        <div>
          <label class="im-label">Gacha</label>
          <input type="date" name="sana_gach" class="im-input" value="<?= $sana_gach ?>">
        </div>
        <button type="submit" class="im-btn im-btn-primary">
          <i class="bi bi-search"></i> Qidirish
        </button>
        <a href="xodim-tarixi.php" class="im-btn im-btn-outline">Reset</a>
      </form>
    </div>

    <!-- STATISTIKA -->
    <?php if ($logs): ?>
    <?php
    $by_xodim = [];
    foreach ($logs as $l) {
        $key = $l['xodim_ism'] . ' ('.$l['rol'].')';
        if (!isset($by_xodim[$key])) $by_xodim[$key] = ['count'=>0,'summa'=>0,'amallar'=>[]];
        $by_xodim[$key]['count']++;
        $by_xodim[$key]['summa'] += (float)$l['summa'];
        $am = $l['amal'];
        $by_xodim[$key]['amallar'][$am] = ($by_xodim[$key]['amallar'][$am] ?? 0) + 1;
    }
    ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin-bottom:14px">
      <?php foreach($by_xodim as $name => $stat): ?>
      <div class="im-card" style="padding:14px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:6px">
          <i class="bi bi-person-circle" style="color:var(--accent-dark)"></i> <?= htmlspecialchars($name) ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted)">
          <span><?= $stat['count'] ?> ta amal</span>
          <span style="font-weight:700;color:var(--success)"><?= number_format($stat['summa'],0,',',' ') ?> so'm</span>
        </div>
        <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px">
          <?php foreach($stat['amallar'] as $a=>$cnt):
            [$icon,$cls] = $amallar[$a] ?? ['⚪','muted'];
          ?>
          <span class="im-badge im-badge-<?= $cls ?>"><?= $icon ?> <?= $cnt ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- JADVAL -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-journal-text" style="color:var(--accent-dark)"></i>
        <span class="im-card-title">Amallar tarixi</span>
        <span class="im-badge im-badge-muted"><?= $total ?> ta</span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Vaqt</th>
              <th>Xodim</th>
              <th>Filial</th>
              <th>Amal</th>
              <th>Stol / Mijoz</th>
              <th>Summa</th>
              <th>Izoh</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($logs)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:32px">
            <i class="bi bi-inbox" style="font-size:28px;opacity:.3"></i><br>Ma'lumot yo'q
          </td></tr>
          <?php else: ?>
          <?php foreach($logs as $i=>$l):
            [$icon,$cls,$label] = $amallar[$l['amal']] ?? ['⚪','muted',$l['amal']];
          ?>
          <tr>
            <td class="text-muted fs-xs"><?= $i+1 ?></td>
            <td style="white-space:nowrap;font-size:12px">
              <?= date('d.m.y', strtotime($l['vaqt'])) ?><br>
              <small class="text-muted"><?= date('H:i:s', strtotime($l['vaqt'])) ?></small>
            </td>
            <td>
              <b><?= im_f($l['xodim_ism']) ?></b>
              <div class="fs-xs text-muted"><?= $l['rol'] ?></div>
            </td>
            <td class="fs-xs text-muted"><?= im_f($l['filial_nomi'] ?? '—') ?></td>
            <td>
              <span class="im-badge im-badge-<?= $cls ?>" style="font-size:11px">
                <?= $icon ?> <?= $label ?>
              </span>
            </td>
            <td>
              <?php if ($l['order_id']): ?>
              <span style="font-size:11px;color:var(--muted)">#<?= $l['order_id'] ?></span>
              <?php endif; ?>
              <b><?= im_f($l['mijoz_ism'] ?? '') ?></b>
            </td>
            <td style="text-align:right;font-weight:700;color:var(--success);white-space:nowrap">
              <?= $l['summa'] > 0 ? number_format($l['summa'],0,',',' ')." so'm" : '—' ?>
            </td>
            <td class="fs-xs text-muted"><?= im_f($l['izoh'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; // table_ok ?>
  </main>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
</body>
</html>
