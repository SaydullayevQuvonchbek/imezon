<?php
// ============================================================
//  IMezon — Zal hisoboti: STOL va OFITSANT kesimida savdo
//
//  Manba im_sotuvlar dagi yangi ustunlar:
//    stol_id / manba     — savdo qayerdan (Stol 5, Dastavka, ...)
//    sotuvchi_id         — qaysi ofitsant buyurtmani olgan
//  Bular sotuv paytida server tomonidan im_sotuvchi_order dan
//  aniqlanadi (dukon/ajax/sotuv-save.php).
//
//  MUHIM: bu ustunlar joriy etilgunga qadar yopilgan sotuvlarda
//  manba yo'q — ular "Bog'lanmagan" qatoriga tushadi.
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);

$db = new Cyber();
$sale_cost = im_fifo_sale_unit_cost_sql('si');
$return_cost = im_fifo_sale_unit_cost_sql('rsi');
$return_revenue_sql = "(SELECT COALESCE(SUM(v.qaytarish_summa),0) FROM im_vozvratlar v WHERE v.sotuv_id=s.id)";
$return_cost_sql = "(SELECT COALESCE(SUM(v.soni*CASE WHEN rsi.id IS NOT NULL THEN ($return_cost) ELSE COALESCE(v.tannarx,0) END),0)
                     FROM im_vozvratlar v LEFT JOIN im_sotuv_items rsi ON rsi.id=v.sotuv_item_id WHERE v.sotuv_id=s.id)";

// ── Filtrlar ──────────────────────────────────────────────
$dan  = $_GET['dan'] ?? date('Y-m-d');
$gacha= $_GET['gacha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dan))   $dan   = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $gacha)) $gacha = date('Y-m-d');
if ($dan > $gacha) { [$dan, $gacha] = [$gacha, $dan]; }

$filial = (int)($_GET['filial'] ?? 0);
$filiallar = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY nomi");

$where = "s.holat IN ('aktiv','qaytarilgan') AND DATE(s.sana) BETWEEN '$dan' AND '$gacha'";
if ($filial) $where .= " AND s.filial_id=$filial";

// ── Umumiy ko'rsatkichlar ─────────────────────────────────
$umumiy = $db->row(
    "SELECT COUNT(*) AS cheklar,
            COALESCE(SUM(s.tolov_summa-$return_revenue_sql),0) AS jami,
            COALESCE(SUM(s.xizmat_summa),0) AS xizmat
     FROM im_sotuvlar s WHERE $where"
) ?: ['cheklar'=>0,'jami'=>0,'xizmat'=>0];

// Marja: sof yakuniy chek tushumi - sof dinamik FIFO tannarxi.
$marja_umumiy = (float)$db->val(
    "SELECT COALESCE(SUM(s.tolov_summa-$return_revenue_sql
              -(SELECT COALESCE(SUM(($sale_cost)*si.soni),0) FROM im_sotuv_items si WHERE si.sotuv_id=s.id)
              +$return_cost_sql),0)
     FROM im_sotuvlar s WHERE $where"
);

// ── STOL kesimi ───────────────────────────────────────────
// stol_id bo'lsa stol bo'yicha, aks holda manba matni bo'yicha
// (Dastavka / Olib ketish / To'g'ridan-to'g'ri) guruhlaymiz.
$stollar = $db->rows(
    "SELECT COALESCE(t.nomi, s.manba, 'Bog''lanmagan') AS nom,
            s.stol_id,
            COUNT(*)                            AS cheklar,
            COALESCE(SUM(s.tolov_summa-$return_revenue_sql),0) AS jami,
            COALESCE(SUM(s.xizmat_summa),0)     AS xizmat,
            COALESCE(SUM(s.tolov_summa-$return_revenue_sql -
              (SELECT COALESCE(SUM(($sale_cost) * si.soni),0)
               FROM im_sotuv_items si WHERE si.sotuv_id = s.id)
              +$return_cost_sql
            ),0)                                AS marja_items
     FROM im_sotuvlar s
     LEFT JOIN im_stollar t ON t.id = s.stol_id
     WHERE $where
     GROUP BY COALESCE(t.nomi, s.manba, 'Bog''lanmagan'), s.stol_id
     ORDER BY jami DESC"
);

// ── OFITSANT kesimi ───────────────────────────────────────
$ofitsantlar = $db->rows(
    "SELECT COALESCE(x.ism, 'Bog''lanmagan') AS nom,
            s.sotuvchi_id,
            COUNT(*)                        AS cheklar,
            COUNT(DISTINCT s.stol_id)       AS stollar_soni,
            COALESCE(SUM(s.tolov_summa-$return_revenue_sql),0) AS jami,
            COALESCE(SUM(s.xizmat_summa),0) AS xizmat,
            COALESCE(SUM(s.tolov_summa-$return_revenue_sql -
              (SELECT COALESCE(SUM(($sale_cost) * si.soni),0)
               FROM im_sotuv_items si WHERE si.sotuv_id = s.id)
              +$return_cost_sql
            ),0)                            AS marja_items
     FROM im_sotuvlar s
     LEFT JOIN im_xodimlar x ON x.id = s.sotuvchi_id
     WHERE $where
     GROUP BY COALESCE(x.ism, 'Bog''lanmagan'), s.sotuvchi_id
     ORDER BY jami DESC"
);

// Har bir ofitsant nechta buyurtma yubordi (sotilmaganlari ham)
$ord_stat = [];
foreach ($db->rows(
    "SELECT sotuvchi_id, COUNT(*) AS soni
     FROM im_sotuvchi_order
     WHERE DATE(created_at) BETWEEN '$dan' AND '$gacha'
       AND status <> 'bekor'" . ($filial ? " AND filial_id=$filial" : '') . "
     GROUP BY sotuvchi_id"
) as $r) { $ord_stat[(int)$r['sotuvchi_id']] = (int)$r['soni']; }

function zh_ortacha($jami, $cheklar) {
    return $cheklar > 0 ? $jami / $cheklar : 0;
}
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Stol / Ofitsant hisoboti | IMezon Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
<style>
.zh-bar{height:6px;border-radius:4px;background:var(--accent-dark);min-width:2px}
.zh-bar-wrap{background:rgba(0,0,0,.06);border-radius:4px;overflow:hidden;margin-top:4px}
.zh-rank{
  width:26px;height:26px;border-radius:8px;display:inline-flex;
  align-items:center;justify-content:center;font-weight:800;font-size:12px;
  background:rgba(226,185,111,.18);color:var(--accent-dark);flex-shrink:0;
}
.zh-rank.top{background:#fef3c7;color:#92400e}
</style>
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title"><i class="bi bi-grid-3x3-gap-fill me-1"></i> Stol / Ofitsant hisoboti</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>

  <div class="im-content">

    <!-- ── Filtr ─────────────────────────────────────────── -->
    <div class="im-card mb-3">
      <div class="im-card-body p-3">
        <form class="d-flex gap-2 flex-wrap align-items-end" method="get">
          <div>
            <label class="im-label">Dan</label>
            <input class="im-input im-input-sm" type="date" name="dan" value="<?= im_f($dan) ?>">
          </div>
          <div>
            <label class="im-label">Gacha</label>
            <input class="im-input im-input-sm" type="date" name="gacha" value="<?= im_f($gacha) ?>">
          </div>
          <div>
            <label class="im-label">Filial</label>
            <select class="im-select im-select-sm" name="filial">
              <option value="0">Barchasi</option>
              <?php foreach ($filiallar as $f): ?>
                <option value="<?= (int)$f['id'] ?>" <?= $filial == $f['id'] ? 'selected' : '' ?>>
                  <?= im_f($f['nomi']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="im-btn im-btn-dark im-btn-sm" type="submit"><i class="bi bi-filter"></i> Ko'rsat</button>
          <a href="<?= im_BASE ?>admin/zal-hisobot.php" class="im-btn im-btn-ghost im-btn-sm">Bugun</a>
        </form>
      </div>
    </div>

    <!-- ── Umumiy ────────────────────────────────────────── -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--accent-dark)">
          <div class="im-stat-icon" style="background:rgba(226,185,111,.15);color:var(--accent-dark)"><i class="bi bi-bag-fill"></i></div>
          <div>
            <div class="im-stat-label">Jami savdo</div>
            <div class="im-stat-value num"><?= im_money($umumiy['jami']) ?> so'm</div>
            <div class="text-muted fs-xs"><?= (int)$umumiy['cheklar'] ?> ta chek</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i class="bi bi-receipt"></i></div>
          <div>
            <div class="im-stat-label">O'rtacha chek</div>
            <div class="im-stat-value num"><?= im_money(zh_ortacha($umumiy['jami'], $umumiy['cheklar'])) ?> so'm</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid #0d6efd">
          <div class="im-stat-icon" style="background:rgba(13,110,253,.12);color:#0d6efd"><i class="bi bi-people-fill"></i></div>
          <div>
            <div class="im-stat-label">Xizmat haqi</div>
            <div class="im-stat-value num"><?= im_money($umumiy['xizmat']) ?> so'm</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid #6f42c1">
          <div class="im-stat-icon" style="background:rgba(111,66,193,.12);color:#6f42c1"><i class="bi bi-graph-up-arrow"></i></div>
          <div>
            <div class="im-stat-label">Marja</div>
            <div class="im-stat-value num" style="color:#6f42c1"><?= im_money($marja_umumiy) ?> so'm</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">

      <!-- ── STOL kesimi ─────────────────────────────────── -->
      <div class="col-lg-6">
        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-geo-alt-fill" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Stol bo'yicha</span>
          </div>
          <div class="im-card-body p-0">
            <?php if (!$stollar): ?>
              <div class="im-empty p-4"><i class="bi bi-inbox"></i><h4>Bu davrda savdo yo'q</h4></div>
            <?php else: ?>
              <?php $max_j = max(array_map(function ($r) { return (float)$r['jami']; }, $stollar)) ?: 1; ?>
              <div class="table-responsive">
                <table class="im-table mb-0">
                  <thead>
                    <tr>
                      <th style="width:40px">#</th>
                      <th>Stol / Manba</th>
                      <th class="text-center">Chek</th>
                      <th class="text-right">Savdo</th>
                      <th class="text-right">O'rtacha</th>
                      <th class="text-right">Marja</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($stollar as $i => $r):
                      $marja = (float)$r['marja_items']; ?>
                    <tr>
                      <td><span class="zh-rank <?= $i === 0 ? 'top' : '' ?>"><?= $i + 1 ?></span></td>
                      <td>
                        <div class="fw-semibold fs-xs"><?= im_f($r['nom']) ?></div>
                        <div class="zh-bar-wrap">
                          <div class="zh-bar" style="width:<?= round($r['jami'] / $max_j * 100) ?>%"></div>
                        </div>
                      </td>
                      <td class="text-center"><span class="im-badge im-badge-muted"><?= (int)$r['cheklar'] ?></span></td>
                      <td class="text-right fw-bold num" style="color:var(--accent-dark)"><?= im_money($r['jami']) ?></td>
                      <td class="text-right num fs-xs text-muted"><?= im_money(zh_ortacha($r['jami'], $r['cheklar'])) ?></td>
                      <td class="text-right num fs-xs" style="color:#6f42c1"><?= im_money($marja) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ── OFITSANT kesimi ─────────────────────────────── -->
      <div class="col-lg-6">
        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-person-badge-fill" style="color:#0d6efd"></i>
            <span class="im-card-title">Ofitsant bo'yicha</span>
          </div>
          <div class="im-card-body p-0">
            <?php if (!$ofitsantlar): ?>
              <div class="im-empty p-4"><i class="bi bi-inbox"></i><h4>Bu davrda savdo yo'q</h4></div>
            <?php else: ?>
              <?php $max_o = max(array_map(function ($r) { return (float)$r['jami']; }, $ofitsantlar)) ?: 1; ?>
              <div class="table-responsive">
                <table class="im-table mb-0">
                  <thead>
                    <tr>
                      <th style="width:40px">#</th>
                      <th>Ofitsant</th>
                      <th class="text-center">Chek</th>
                      <th class="text-right">Savdo</th>
                      <th class="text-right">O'rtacha</th>
                      <th class="text-right">Marja</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($ofitsantlar as $i => $r):
                      $marja  = (float)$r['marja_items'];
                      $ord_n  = $ord_stat[(int)$r['sotuvchi_id']] ?? null; ?>
                    <tr>
                      <td><span class="zh-rank <?= $i === 0 ? 'top' : '' ?>"><?= $i + 1 ?></span></td>
                      <td>
                        <div class="fw-semibold fs-xs"><?= im_f($r['nom']) ?></div>
                        <?php if ($ord_n !== null): ?>
                          <div class="text-muted fs-xs"><?= $ord_n ?> ta buyurtma ochgan</div>
                        <?php endif; ?>
                        <div class="zh-bar-wrap">
                          <div class="zh-bar" style="width:<?= round($r['jami'] / $max_o * 100) ?>%;background:#0d6efd"></div>
                        </div>
                      </td>
                      <td class="text-center"><span class="im-badge im-badge-muted"><?= (int)$r['cheklar'] ?></span></td>
                      <td class="text-right fw-bold num" style="color:#0d6efd"><?= im_money($r['jami']) ?></td>
                      <td class="text-right num fs-xs text-muted"><?= im_money(zh_ortacha($r['jami'], $r['cheklar'])) ?></td>
                      <td class="text-right num fs-xs" style="color:#6f42c1"><?= im_money($marja) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>

    <div class="text-muted fs-xs mt-3">
      <i class="bi bi-info-circle"></i>
      "Bog'lanmagan" — stol/ofitsant maydonlari joriy etilgunga qadar yopilgan
      yoki to'g'ridan-to'g'ri kassada qilingan savdolar.
    </div>

  </div>
</div>
</div>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
</body>
</html>
