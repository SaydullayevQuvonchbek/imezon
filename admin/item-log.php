<?php
// ============================================================
//  IMezon — Admin: Taom qo'shish/o'chirish tarixi (Item log)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);

$db = new Cyber();

// Filterlar
$today     = date('Y-m-d');
$sana_dan  = $_GET['sana_dan']  ?? $today;
$sana_ga   = $_GET['sana_ga']   ?? $today;
$xodim_f   = (int)($_GET['xodim_id'] ?? 0);
$order_f   = (int)($_GET['order_id'] ?? 0);
$amal_f    = $_GET['amal']      ?? '';
$filial_f  = (int)($_GET['filial_id'] ?? 0);

// Xodimlar ro'yxati (filter uchun)
$xodimlar = $db->rows("SELECT id, ism, rol FROM im_xodimlar WHERE status=1 ORDER BY ism");
$filiallar = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY nomi");

// WHERE sharti
$where = ["DATE(l.created_at) BETWEEN '$sana_dan' AND '$sana_ga'"];
if ($xodim_f)              $where[] = "l.xodim_id = $xodim_f";
if ($order_f)              $where[] = "l.order_id = $order_f";
if ($amal_f)               $where[] = "l.amal = '" . mysqli_real_escape_string($link, $amal_f) . "'";
if ($filial_f)             $where[] = "l.filial_id = $filial_f";
$where_sql = implode(' AND ', $where);

$logs = $db->rows(
    "SELECT l.*,
            x.ism AS xodim_ism, x.rol AS xodim_rol,
            fl.nomi AS filial_nomi,
            o.mijoz_ism, o.status AS order_status
     FROM im_order_item_log l
     JOIN im_xodimlar x ON x.id = l.xodim_id
     LEFT JOIN im_filiallar fl ON fl.id = l.filial_id
     LEFT JOIN im_sotuvchi_order o ON o.id = l.order_id
     WHERE $where_sql
     ORDER BY l.created_at DESC
     LIMIT 500"
);

// Statistika
$bugun_total = (int)$db->val("SELECT COUNT(*) FROM im_order_item_log WHERE DATE(created_at)='$today'");
$bugun_qoshildi = (int)$db->val("SELECT COUNT(*) FROM im_order_item_log WHERE DATE(created_at)='$today' AND amal='qoshildi'");
$bugun_ochirildi = (int)$db->val("SELECT COUNT(*) FROM im_order_item_log WHERE DATE(created_at)='$today' AND amal='ochirildi'");
$bugun_oshpaz = (int)$db->val("SELECT COUNT(*) FROM im_order_item_log WHERE DATE(created_at)='$today' AND amal='oshpaz_tasdiqladi'");

// Amal ranglari
$amal_meta = [
    'qoshildi'          => ['Qo\'shildi',      '#22c55e', 'bi-plus-circle-fill'],
    'oshirildi'         => ['Oshirildi',        '#3b82f6', 'bi-arrow-up-circle-fill'],
    'kamaytir'          => ['Kamaytirildi',     '#f97316', 'bi-arrow-down-circle-fill'],
    'ochirildi'         => ['O\'chirildi',      '#ef4444', 'bi-trash3-fill'],
    'oshpaz_tasdiqladi' => ['Oshpaz tasdiq',   '#a855f7', 'bi-check-circle-fill'],
];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Taom tarixi — IMezon Admin</title>
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/bi.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f0f2f5;--card:#fff;--border:#e2e8f0;
  --text:#1e293b;--muted:#64748b;
  --primary:#1e293b;--accent:#e2b96f;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* Header */
.page-header{
  background:var(--primary);padding:0 24px;height:56px;
  display:flex;align-items:center;gap:12px;
  box-shadow:0 2px 12px rgba(0,0,0,.2);
}
.page-header .logo{font-size:18px;font-weight:800;color:#fff}
.page-header .logo span{color:var(--accent)}
.page-header .back-btn{
  margin-left:auto;color:rgba(255,255,255,.7);text-decoration:none;
  font-size:13px;display:flex;align-items:center;gap:5px;
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);
  border-radius:7px;padding:5px 11px;transition:.15s;
}
.page-header .back-btn:hover{color:#fff;background:rgba(255,255,255,.15)}

.container{max-width:1400px;margin:0 auto;padding:20px}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.stat-card{
  background:var(--card);border-radius:12px;padding:16px 20px;
  border:1px solid var(--border);
  display:flex;align-items:center;gap:14px;
}
.stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.stat-val{font-size:28px;font-weight:800;line-height:1}
.stat-lbl{font-size:12px;color:var(--muted);margin-top:3px}

/* Filter */
.filter-card{
  background:var(--card);border-radius:12px;padding:16px 20px;
  border:1px solid var(--border);margin-bottom:20px;
}
.filter-row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:4px}
.filter-group label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.filter-group select,
.filter-group input{
  height:36px;border:2px solid var(--border);border-radius:8px;
  padding:0 10px;font-size:13px;font-family:inherit;outline:none;
  background:#fff;transition:.2s;color:var(--text);
}
.filter-group select:focus,
.filter-group input:focus{border-color:var(--accent)}
.btn-filter{
  height:36px;padding:0 18px;border-radius:8px;border:none;
  background:var(--primary);color:#fff;font-size:13px;font-weight:700;
  cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px;
  transition:.15s;
}
.btn-filter:hover{opacity:.85}
.btn-reset{
  height:36px;padding:0 14px;border-radius:8px;border:2px solid var(--border);
  background:#fff;color:var(--muted);font-size:13px;
  cursor:pointer;font-family:inherit;text-decoration:none;
  display:flex;align-items:center;gap:5px;transition:.15s;
}
.btn-reset:hover{border-color:var(--text);color:var(--text)}

/* Table */
.table-card{
  background:var(--card);border-radius:12px;
  border:1px solid var(--border);overflow:hidden;
}
.table-head{
  padding:14px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
}
.table-head h2{font-size:15px;font-weight:700}
.table-count{font-size:12px;color:var(--muted);background:var(--bg);border-radius:20px;padding:3px 10px}
table{width:100%;border-collapse:collapse}
th{
  font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;
  letter-spacing:.5px;padding:10px 14px;border-bottom:2px solid var(--border);
  text-align:left;background:#f8fafc;
}
td{padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafbfc}

/* Amal badge */
.amal-badge{
  display:inline-flex;align-items:center;gap:5px;
  border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;
}

/* Delta */
.delta{
  display:flex;align-items:center;gap:6px;
  font-size:13px;font-weight:600;
}
.delta .arrow{font-size:16px}

/* Xodim chip */
.xodim-chip{
  display:inline-flex;align-items:center;gap:5px;
  background:#f1f5f9;border-radius:20px;padding:3px 9px;
  font-size:12px;font-weight:600;
}
.rol-sotuvchi{color:#2563eb}
.rol-oshpaz{color:#7c3aed}
.rol-admin{color:#dc2626}

/* Order link */
.order-link{
  font-weight:700;color:#2563eb;text-decoration:none;
  font-size:12px;background:#eff6ff;border-radius:5px;
  padding:2px 7px;
}
.order-link:hover{background:#dbeafe}

/* Status badge */
.status-badge{
  font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;
}

/* Empty */
.empty-state{
  text-align:center;padding:60px 20px;color:var(--muted);
}
.empty-state i{font-size:48px;opacity:.2;display:block;margin-bottom:12px}

/* Responsive */
@media(max-width:900px){
  .stats{grid-template-columns:repeat(2,1fr)}
  .filter-row{flex-direction:column}
  th:nth-child(5),td:nth-child(5){display:none}
}
</style>
</head>
<body>

<div class="page-header">
  <div class="logo">iMEZON<span> ADMIN</span></div>
  <span style="color:rgba(255,255,255,.5);font-size:13px">/ Taom tarixi</span>
  <a href="../admin/" class="back-btn"><i class="bi bi-arrow-left"></i> Orqaga</a>
</div>

<div class="container">

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-list-check"></i></div>
      <div><div class="stat-val"><?= $bugun_total ?></div><div class="stat-lbl">Bugun jami</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-plus-circle-fill"></i></div>
      <div><div class="stat-val" style="color:#16a34a"><?= $bugun_qoshildi ?></div><div class="stat-lbl">Qo'shildi</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="bi bi-trash3-fill"></i></div>
      <div><div class="stat-val" style="color:#dc2626"><?= $bugun_ochirildi ?></div><div class="stat-lbl">O'chirildi</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#f3e8ff;color:#7c3aed"><i class="bi bi-check-circle-fill"></i></div>
      <div><div class="stat-val" style="color:#7c3aed"><?= $bugun_oshpaz ?></div><div class="stat-lbl">Oshpaz tasdiqladi</div></div>
    </div>
  </div>

  <!-- Filter -->
  <div class="filter-card">
    <form method="GET" action="">
      <div class="filter-row">
        <div class="filter-group">
          <label>Dan</label>
          <input type="date" name="sana_dan" value="<?= im_f($sana_dan) ?>">
        </div>
        <div class="filter-group">
          <label>Gacha</label>
          <input type="date" name="sana_ga" value="<?= im_f($sana_ga) ?>">
        </div>
        <div class="filter-group">
          <label>Xodim</label>
          <select name="xodim_id">
            <option value="">— Barchasi —</option>
            <?php foreach ($xodimlar as $x): ?>
            <option value="<?= $x['id'] ?>" <?= $xodim_f==$x['id']?'selected':'' ?>>
              <?= im_f($x['ism']) ?> (<?= $x['rol'] ?>)
            </option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Amal</label>
          <select name="amal">
            <option value="">— Barchasi —</option>
            <?php foreach ($amal_meta as $k => $v): ?>
            <option value="<?= $k ?>" <?= $amal_f===$k?'selected':'' ?>><?= $v[0] ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Filial</label>
          <select name="filial_id">
            <option value="">— Barchasi —</option>
            <?php foreach ($filiallar as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filial_f==$f['id']?'selected':'' ?>>
              <?= im_f($f['nomi']) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Order #</label>
          <input type="number" name="order_id" value="<?= $order_f ?: '' ?>" placeholder="ID..." style="width:90px">
        </div>
        <button type="submit" class="btn-filter"><i class="bi bi-search"></i> Filter</button>
        <a href="?" class="btn-reset"><i class="bi bi-x-circle"></i> Reset</a>
      </div>
    </form>
  </div>

  <!-- Jadval -->
  <div class="table-card">
    <div class="table-head">
      <h2><i class="bi bi-journal-text"></i> Taom tarixi</h2>
      <span class="table-count"><?= count($logs) ?> ta yozuv</span>
    </div>

    <?php if (empty($logs)): ?>
    <div class="empty-state">
      <i class="bi bi-inbox"></i>
      <div>Hech qanday yozuv topilmadi</div>
      <small>Filter shartlarini o'zgartiring</small>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Vaqt</th>
          <th>Xodim</th>
          <th>Stol / Order</th>
          <th>Mahsulot</th>
          <th>Amal</th>
          <th>O'zgarish</th>
          <th>Filial</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $l):
          $meta  = $amal_meta[$l['amal']] ?? [$l['amal'], '#888', 'bi-circle'];
          $rolcls = 'rol-' . ($l['xodim_rol'] ?? 'sotuvchi');
        ?>
        <tr>
          <td style="white-space:nowrap;color:var(--muted);font-size:12px">
            <?= date('d.m H:i:s', strtotime($l['created_at'])) ?>
          </td>
          <td>
            <span class="xodim-chip <?= $rolcls ?>">
              <i class="bi bi-person-fill"></i>
              <?= im_f($l['xodim_ism']) ?>
            </span>
            <div style="font-size:10px;color:var(--muted);margin-top:2px"><?= $l['xodim_rol'] ?></div>
          </td>
          <td>
            <a href="?order_id=<?= $l['order_id'] ?>&sana_dan=<?= $sana_dan ?>&sana_ga=<?= $sana_ga ?>"
               class="order-link">#<?= $l['order_id'] ?></a>
            <?php if ($l['mijoz_ism']): ?>
            <div style="font-size:11px;color:var(--muted);margin-top:2px">
              <i class="bi bi-table" style="font-size:10px"></i> <?= im_f($l['mijoz_ism']) ?>
            </div>
            <?php endif ?>
          </td>
          <td style="font-weight:600"><?= im_f($l['nomi'] ?? '—') ?></td>
          <td>
            <span class="amal-badge" style="background:<?= $meta[1] ?>22;color:<?= $meta[1] ?>">
              <i class="bi <?= $meta[2] ?>"></i>
              <?= $meta[0] ?>
            </span>
          </td>
          <td>
            <?php
              $es = $l['eski_soni'] + 0;
              $yn = $l['yangi_soni'] + 0;
              $col = $l['amal'] === 'ochirildi' ? '#ef4444'
                   : ($l['amal'] === 'kamaytir' ? '#f97316'
                   : ($l['amal'] === 'qoshildi' || $l['amal'] === 'oshirildi' ? '#22c55e'
                   : '#a855f7'));
            ?>
            <div class="delta">
              <span style="color:var(--muted)"><?= $es*1 ?></span>
              <span class="arrow" style="color:<?= $col ?>">→</span>
              <span style="color:<?= $col ?>;font-size:15px;font-weight:800"><?= $yn*1 ?></span>
            </div>
          </td>
          <td style="font-size:12px;color:var(--muted)"><?= im_f($l['filial_nomi'] ?? '—') ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    </div>
    <?php endif ?>
  </div>

</div>
</body>
</html>
