<?php
// ============================================================
//  IMezon — Qayta Ishlash Hisoboti (Admin only)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);

$db = new Cyber();

// Filtr
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to   = $_GET['to']   ?? date('Y-m-d');
$tur_f     = $_GET['tur']  ?? '';

$from_s = $db->f($date_from);
$to_s   = $db->f($date_to);
$tur_where = $tur_f ? "AND ic.tur='{$db->f($tur_f)}'" : '';

// Umumiy statistika
$stat_ishlab = $db->row(
    "SELECT COUNT(*) AS soni
     FROM im_ishlab_chiqarish ic
     WHERE ic.holat='bajarildi' AND ic.tur='ishlab_chiqarish'
       AND DATE(ic.sana) BETWEEN '$from_s' AND '$to_s'"
);
$stat_mayda = $db->row(
    "SELECT COUNT(*) AS soni
     FROM im_ishlab_chiqarish ic
     WHERE ic.holat='bajarildi' AND ic.tur='maydalash'
       AND DATE(ic.sana) BETWEEN '$from_s' AND '$to_s'"
);

// Jami kirish vs chiqish qiymati (faqat bajarilgan)
$qiymat = $db->row(
    "SELECT
       COALESCE(SUM(CASE WHEN ii.tur='kirish' THEN ii.soni*ii.kelish_narxi ELSE 0 END),0) AS kirish_qiymati,
       COALESCE(SUM(CASE WHEN ii.tur='chiqish' THEN ii.soni*ii.kelish_narxi ELSE 0 END),0) AS chiqish_qiymati
     FROM im_ishlab_chiqarish_items ii
     JOIN im_ishlab_chiqarish ic ON ic.id=ii.ishlab_id
     WHERE ic.holat='bajarildi' $tur_where
       AND DATE(ic.sana) BETWEEN '$from_s' AND '$to_s'"
);

// Operatsiyalar jadvali
$operatsiyalar = $db->rows(
    "SELECT ic.*,
            r.nomi AS r_nomi,
            m.nomi AS m_nomi, m.birlik AS m_birlik,
            x.ism AS x_ism,
            f.nomi AS f_nomi,
            /* Kirish qiymati */
            (SELECT COALESCE(SUM(ii.soni*ii.kelish_narxi),0)
             FROM im_ishlab_chiqarish_items ii
             WHERE ii.ishlab_id=ic.id AND ii.tur='kirish') AS kirish_qiymati,
            /* Chiqish qiymati */
            (SELECT COALESCE(SUM(ii.soni*ii.kelish_narxi),0)
             FROM im_ishlab_chiqarish_items ii
             WHERE ii.ishlab_id=ic.id AND ii.tur='chiqish') AS chiqish_qiymati
     FROM im_ishlab_chiqarish ic
     LEFT JOIN im_retseptlar r ON r.id=ic.retsept_id
     LEFT JOIN im_mahsulotlar m ON m.id=ic.mahsulot_id
     LEFT JOIN im_xodimlar x ON x.id=ic.xodim_id
     LEFT JOIN im_filiallar f ON f.id=ic.filial_id
     WHERE ic.holat='bajarildi' $tur_where
       AND DATE(ic.sana) BETWEEN '$from_s' AND '$to_s'
     ORDER BY ic.sana DESC"
);

// Chiqish mahsulotlari tahlili (potensial zarar: omborda qolganlar)
$chiqish_tahlil = $db->rows(
    "SELECT m.id, m.nomi AS mah_nomi, m.birlik,
            COALESCE(SUM(ii.soni),0) AS jami_chiqdi,
            COALESCE((SELECT SUM(fl.remaining_qty) FROM im_fifo_layers fl WHERE fl.mahsulot_id=m.id AND fl.location_id=0 AND fl.cancelled=0 AND fl.remaining_qty>0),0) AS sklad_qoldi,
            COALESCE((SELECT SUM(fl.remaining_qty) FROM im_fifo_layers fl WHERE fl.mahsulot_id=m.id AND fl.location_id>0 AND fl.cancelled=0 AND fl.remaining_qty>0),0) AS dukon_qoldi,
            COALESCE(n.sotish_narxi,0) AS sotish_narxi
     FROM im_ishlab_chiqarish_items ii
     JOIN im_ishlab_chiqarish ic ON ic.id=ii.ishlab_id
     LEFT JOIN im_mahsulotlar m ON m.id=ii.mahsulot_id
     LEFT JOIN im_narxlar n ON n.mahsulot_id=ii.mahsulot_id
     WHERE ii.tur='chiqish' AND ic.holat='bajarildi' AND ic.tur='maydalash'
       AND DATE(ic.sana) BETWEEN '$from_s' AND '$to_s'
     GROUP BY m.id
     HAVING jami_chiqdi > 0
     ORDER BY (sklad_qoldi + dukon_qoldi) DESC"
);

$kirish_q = (float)($qiymat['kirish_qiymati'] ?? 0);
$chiqish_q = (float)($qiymat['chiqish_qiymati'] ?? 0);
$foyda_zarar = $chiqish_q - $kirish_q;

$page_title = 'Qayta Ishlash Hisoboti';
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= im_f($page_title) ?> | IMezon</title>
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
        <a href="<?= im_BASE ?>qayta-ishlash/index.php">Qayta Ishlash</a>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active">Hisobot</span>
      </div>

      <!-- Filtr -->
      <div class="im-card im-slide-in mb-4">
        <div class="im-card-body">
          <form method="get" class="d-flex flex-wrap gap-3 align-items-end">
            <div>
              <label class="im-label">Dan</label>
              <input type="date" name="from" class="im-input" value="<?= im_f($date_from) ?>">
            </div>
            <div>
              <label class="im-label">Gacha</label>
              <input type="date" name="to" class="im-input" value="<?= im_f($date_to) ?>">
            </div>
            <div>
              <label class="im-label">Tur</label>
              <select name="tur" class="im-input">
                <option value="">Hammasi</option>
                <option value="ishlab_chiqarish" <?= $tur_f==='ishlab_chiqarish'?'selected':'' ?>>🍳 Ishlab chiqarish</option>
                <option value="maydalash" <?= $tur_f==='maydalash'?'selected':'' ?>>✂️ Maydalash</option>
              </select>
            </div>
            <button type="submit" class="im-btn im-btn-primary">
              <i class="bi bi-search"></i> Filtrlash
            </button>
          </form>
        </div>
      </div>

      <!-- Stat cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="im-stat primary im-slide-in">
            <div class="im-stat-icon primary"><i class="bi bi-fire"></i></div>
            <div class="im-stat-body">
              <div class="im-stat-label">Ishlab chiqarish</div>
              <div class="im-stat-value num"><?= (int)($stat_ishlab['soni']??0) ?></div>
              <div class="im-stat-sub">operatsiya</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="im-stat warning im-slide-in">
            <div class="im-stat-icon warning"><i class="bi bi-scissors"></i></div>
            <div class="im-stat-body">
              <div class="im-stat-label">Maydalash</div>
              <div class="im-stat-value num"><?= (int)($stat_mayda['soni']??0) ?></div>
              <div class="im-stat-sub">operatsiya</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="im-stat info im-slide-in">
            <div class="im-stat-icon info"><i class="bi bi-box-arrow-in-down"></i></div>
            <div class="im-stat-body">
              <div class="im-stat-label">Kirish qiymati</div>
              <div class="im-stat-value num" style="font-size:17px"><?= im_money($kirish_q) ?></div>
              <div class="im-stat-sub">so'm</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <?php $zarar = $foyda_zarar < 0; ?>
          <div class="im-stat <?= $zarar ? 'danger' : 'success' ?> im-slide-in">
            <div class="im-stat-icon <?= $zarar ? 'danger' : 'success' ?>">
              <i class="bi bi-<?= $zarar ? 'graph-down' : 'graph-up' ?>"></i>
            </div>
            <div class="im-stat-body">
              <div class="im-stat-label">Foyda/Zarar</div>
              <div class="im-stat-value num" style="font-size:17px">
                <?= ($foyda_zarar >= 0 ? '+' : '') . im_money($foyda_zarar) ?>
              </div>
              <div class="im-stat-sub"><?= $zarar ? 'zarar' : 'foyda' ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Operatsiyalar jadvali -->
      <div class="im-card im-slide-in mb-4">
        <div class="im-card-header">
          <i class="bi bi-table"></i>
          <span class="im-card-title">Operatsiyalar</span>
          <span class="im-badge im-badge-info ms-2"><?= count($operatsiyalar) ?> ta</span>
        </div>
        <div class="im-table-wrap">
          <table class="im-table">
            <thead>
              <tr>
                <th>#</th><th>Tur</th><th>Retsept</th><th>Mahsulot</th>
                <th class="text-right">Soni</th>
                <th class="text-right">Kirish qiymati</th>
                <th class="text-right">Chiqish qiymati</th>
                <th class="text-right">Zarar/Foyda</th>
                <th>Filial</th><th>Xodim</th><th>Sana</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($operatsiyalar): foreach ($operatsiyalar as $op):
                $diff = (float)$op['chiqish_qiymati'] - (float)$op['kirish_qiymati'];
              ?>
              <tr>
                <td class="text-muted fs-sm"><?= $op['id'] ?></td>
                <td>
                  <?php if ($op['tur'] === 'ishlab_chiqarish'): ?>
                  <span class="im-badge im-badge-info">🍳</span>
                  <?php else: ?>
                  <span class="im-badge im-badge-warning">✂️</span>
                  <?php endif; ?>
                </td>
                <td class="fw-semibold"><?= im_f($op['r_nomi'] ?: '—') ?></td>
                <td><?= im_f($op['m_nomi'] ?: '—') ?></td>
                <td class="text-right num"><?= (float)$op['soni'] ?> → <?= (float)$op['chiqish_soni'] ?></td>
                <td class="text-right num"><?= im_money($op['kirish_qiymati']) ?></td>
                <td class="text-right num"><?= im_money($op['chiqish_qiymati']) ?></td>
                <td class="text-right num <?= $diff >= 0 ? 'text-success' : 'text-danger' ?> fw-bold">
                  <?= ($diff >= 0 ? '+' : '') . im_money($diff) ?>
                </td>
                <td><?= im_f($op['f_nomi'] ?: 'Sklad') ?></td>
                <td class="text-muted fs-sm"><?= im_f($op['x_ism'] ?: '—') ?></td>
                <td class="text-muted fs-sm"><?= im_datetime($op['sana']) ?></td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="11">
                <div class="im-empty"><i class="bi bi-inbox"></i><h4>Ma'lumot yo'q</h4></div>
              </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Maydalash chiqish tahlili (potensial zarar) -->
      <?php if ($chiqish_tahlil && (!$tur_f || $tur_f === 'maydalash')): ?>
      <div class="im-card im-slide-in">
        <div class="im-card-header">
          <i class="bi bi-exclamation-triangle-fill" style="color:var(--warning)"></i>
          <span class="im-card-title">Maydalash chiqish tahlili (omborda qolganlar)</span>
          <small class="text-muted ms-2">— past sotilyadigan qismlar</small>
        </div>
        <div class="im-table-wrap">
          <table class="im-table">
            <thead>
              <tr>
                <th>Mahsulot (chiqish)</th>
                <th class="text-right">Jami chiqdi</th>
                <th class="text-right">Sklad qoldi</th>
                <th class="text-right">Do'kon qoldi</th>
                <th class="text-right">Sotish narxi</th>
                <th class="text-right">Ombordagi qiymati</th>
                <th>Baholash</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($chiqish_tahlil as $ct):
                $qoldiq_soni = (float)$ct['sklad_qoldi'] + (float)$ct['dukon_qoldi'];
                $qoldiq_qiym = $qoldiq_soni * (float)$ct['sotish_narxi'];
                $foiz = $ct['jami_chiqdi'] > 0 ? round(($qoldiq_soni / $ct['jami_chiqdi']) * 100, 1) : 0;
              ?>
              <tr>
                <td class="fw-semibold"><?= im_f($ct['mah_nomi']) ?></td>
                <td class="text-right num"><?= (float)$ct['jami_chiqdi'] ?> <?= im_f($ct['birlik']) ?></td>
                <td class="text-right num"><?= (float)$ct['sklad_qoldi'] ?></td>
                <td class="text-right num"><?= (float)$ct['dukon_qoldi'] ?></td>
                <td class="text-right num"><?= im_money($ct['sotish_narxi']) ?></td>
                <td class="text-right num fw-bold"><?= im_money($qoldiq_qiym) ?></td>
                <td>
                  <?php if ($foiz >= 50): ?>
                  <span class="im-badge im-badge-danger">⚠️ <?= $foiz ?>% qoldi</span>
                  <?php elseif ($foiz >= 20): ?>
                  <span class="im-badge im-badge-warning"><?= $foiz ?>% qoldi</span>
                  <?php elseif ($foiz > 0): ?>
                  <span class="im-badge im-badge-info"><?= $foiz ?>% qoldi</span>
                  <?php else: ?>
                  <span class="im-badge im-badge-success">✓ Sotildi</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </main>
  </div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
</body>
</html>
