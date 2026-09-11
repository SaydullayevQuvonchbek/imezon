<?php
// ============================================================
//  IMezon — Kontragent Munosabatlari
//  Har bir yetkazib beruvchi bilan umumiy balans
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);
$db = new Cyber();

// Tanlangan kontragent
$ps_id = (int)($_GET['ps'] ?? 0);
$usd_kurs_now = im_usd_kurs() ?: 12700;

// ── Barcha kontragentlar — umumiy balans ────────────────────
$kontragentlar = $db->rows(
    "SELECT ps.*,
            (SELECT COUNT(*) FROM im_partiyalar p WHERE p.postavshik_id=ps.id AND p.holat='yopiq') AS partiya_soni,
            (SELECT COALESCE(SUM(p.jami_summa),0) FROM im_partiyalar p WHERE p.postavshik_id=ps.id AND p.holat='yopiq') AS jami_olindi,
            (SELECT COALESCE(SUM(p.tolandi),0) FROM im_partiyalar p WHERE p.postavshik_id=ps.id AND p.holat='yopiq') AS jami_tolandi,
            (SELECT COALESCE(SUM(pq.qoldiq),0) FROM im_postavshik_qarz pq WHERE pq.postavshik_id=ps.id AND pq.status='ochiq') AS ochiq_qarz,
            (SELECT MAX(p.sana) FROM im_partiyalar p WHERE p.postavshik_id=ps.id AND p.holat='yopiq') AS oxirgi_partiya
     FROM im_postavshiklar ps
     WHERE ps.status = 1
     ORDER BY ochiq_qarz DESC, jami_olindi DESC"
);

// Tanlangan kontragent ma'lumotlari
$ps_detail = null;
$ps_partiyalar = [];
$ps_tolovlar = [];
$ps_ochiq_qarzlar = [];

if ($ps_id) {
    $ps_detail = $db->row("SELECT * FROM im_postavshiklar WHERE id=$ps_id");

    // Barcha partiyalar
    $ps_partiyalar = $db->rows(
        "SELECT p.*,
                (SELECT COUNT(*) FROM im_partiya_items WHERE partiya_id=p.id) AS item_soni,
                COALESCE((SELECT SUM(qt.summa) FROM im_qarz_tolovlar qt
                          JOIN im_postavshik_qarz pq ON pq.id=qt.qarz_id
                          WHERE pq.partiya_id=p.id), 0) AS extra_tolandi
         FROM im_partiyalar p
         WHERE p.postavshik_id=$ps_id
         ORDER BY p.id DESC"
    );

    // Barcha to'lovlar (oxirgi 100)
    $ps_tolovlar = $db->rows(
        "SELECT qt.*, p.faktura_nomer, p.id AS p_id, pq.qarz_summa,
                x.ism AS admin_ism
         FROM im_qarz_tolovlar qt
         JOIN im_postavshik_qarz pq ON pq.id = qt.qarz_id
         JOIN im_partiyalar p       ON p.id  = pq.partiya_id
         LEFT JOIN im_xodimlar x   ON x.id  = qt.admin_id
         WHERE pq.postavshik_id = $ps_id
         ORDER BY qt.sana DESC
         LIMIT 100"
    );

    // Ochiq qarzlar
    $ps_ochiq_qarzlar = $db->rows(
        "SELECT pq.*, p.faktura_nomer, p.sana AS p_sana, p.jami_summa AS p_jami
         FROM im_postavshik_qarz pq
         JOIN im_partiyalar p ON p.id = pq.partiya_id
         WHERE pq.postavshik_id=$ps_id AND pq.status='ochiq'
         ORDER BY pq.muddat ASC"
    );
}

// Yig'indi
$jami_qarz    = array_sum(array_column($kontragentlar, 'ochiq_qarz'));
$jami_olindi  = array_sum(array_column($kontragentlar, 'jami_olindi'));
$jami_tolandi = array_sum(array_column($kontragentlar, 'jami_tolandi'));
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Kontragent Munosabatlari | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-people-fill me-1"></i> Kontragent Munosabatlari</div>
    <div class="im-topbar-actions">
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- ── Yig'indi kartalar ──────────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--primary)">
          <div class="im-stat-icon" style="background:rgba(79,70,229,.1);color:var(--primary)"><i class="bi bi-people-fill"></i></div>
          <div>
            <div class="im-stat-label">Kontragentlar</div>
            <div class="im-stat-value"><?= count($kontragentlar) ?> ta</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--info,#17a2b8)">
          <div class="im-stat-icon" style="background:rgba(23,162,184,.1);color:#17a2b8"><i class="bi bi-box-seam-fill"></i></div>
          <div>
            <div class="im-stat-label">Jami olindi</div>
            <div class="im-stat-value num"><?= im_money($jami_olindi) ?> so'm</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--success)">
          <div class="im-stat-icon" style="background:rgba(40,167,69,.1);color:var(--success)"><i class="bi bi-cash-stack"></i></div>
          <div>
            <div class="im-stat-label">Jami to'landi</div>
            <div class="im-stat-value num"><?= im_money($jami_tolandi) ?> so'm</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.1);color:var(--danger)"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div>
            <div class="im-stat-label">Ochiq qarz</div>
            <div class="im-stat-value num" style="color:var(--danger)"><?= im_money($jami_qarz) ?> so'm</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">

      <!-- ── Chap: Kontragentlar ro'yxati ──────────────────── -->
      <div class="col-md-4">
        <div class="im-card h-100">
          <div class="im-card-header">
            <i class="bi bi-people-fill" style="color:var(--primary)"></i>
            <span class="im-card-title">Kontragentlar</span>
            <a href="<?= im_BASE ?>admin/qarzlar.php" class="im-btn im-btn-outline im-btn-sm ms-auto">
              + Yangi partiya
            </a>
          </div>
          <div style="overflow-y:auto;max-height:70vh">
            <?php foreach ($kontragentlar as $ps):
              $has_qarz = (float)$ps['ochiq_qarz'] > 0;
              $active = $ps_id == $ps['id'];
            ?>
            <a href="?ps=<?= $ps['id'] ?>"
               style="display:flex;align-items:center;gap:10px;padding:12px 16px;
                      border-bottom:1px solid var(--border);text-decoration:none;
                      background:<?= $active ? 'var(--accent-light,#fdf8ee)' : 'transparent' ?>;
                      border-left:<?= $active ? '3px solid var(--accent-dark)' : '3px solid transparent' ?>;
                      transition:.15s;"
               class="<?= $active ? 'active' : '' ?>">
              <div style="width:38px;height:38px;border-radius:10px;
                          background:<?= $has_qarz ? 'rgba(220,53,69,.12)' : 'rgba(40,167,69,.12)' ?>;
                          display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">
                <?= $has_qarz ? '⚠️' : '✅' ?>
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:13px;color:var(--text);
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                  <?= im_f($ps['nomi']) ?>
                </div>
                <div style="font-size:11px;color:var(--muted)">
                  <?= (int)$ps['partiya_soni'] ?> partiya
                  <?php if ($ps['oxirgi_partiya']): ?>
                  · <?= im_date($ps['oxirgi_partiya']) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div style="text-align:right;flex-shrink:0">
                <?php if ($has_qarz): ?>
                <div style="font-size:12px;font-weight:700;color:var(--danger)">
                  -<?= im_money($ps['ochiq_qarz']) ?>
                </div>
                <div style="font-size:10px;color:var(--muted)">qarz</div>
                <?php else: ?>
                <span class="im-badge im-badge-success" style="font-size:10px">✓ Toza</span>
                <?php endif; ?>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- ── O'ng: Tanlangan kontragent ─────────────────────── -->
      <div class="col-md-8">
        <?php if ($ps_detail): ?>

        <!-- Kontragent sarlavha -->
        <div class="im-card mb-3">
          <div class="im-card-body p-3">
            <div class="d-flex align-items-start gap-3 flex-wrap">
              <div style="width:52px;height:52px;border-radius:14px;background:var(--primary);
                          display:flex;align-items:center;justify-content:center;
                          font-size:22px;color:var(--accent);flex-shrink:0">
                🏭
              </div>
              <div style="flex:1">
                <div style="font-size:18px;font-weight:800;color:var(--text)"><?= im_f($ps_detail['nomi']) ?></div>
                <div style="font-size:13px;color:var(--muted)">
                  <?php if ($ps_detail['telefon']): ?>
                  <i class="bi bi-telephone"></i> <?= im_f($ps_detail['telefon']) ?>
                  <?php endif; ?>
                  <?php if ($ps_detail['shahar']): ?>
                  · <i class="bi bi-geo-alt"></i> <?= im_f($ps_detail['shahar']) ?>
                  <?php endif; ?>
                </div>
              </div>
              <!-- Umumiy statistika -->
              <?php
              $ps_row = current(array_filter($kontragentlar, fn($k) => $k['id'] == $ps_id));
              $qarz_total = (float)($ps_row['ochiq_qarz'] ?? 0);
              ?>
              <div class="d-flex gap-3 flex-wrap">
                <div class="text-center">
                  <div style="font-size:11px;color:var(--muted)">Jami olindi</div>
                  <div style="font-size:15px;font-weight:700;color:var(--text)" class="num"><?= im_money($ps_row['jami_olindi'] ?? 0) ?></div>
                </div>
                <div class="text-center">
                  <div style="font-size:11px;color:var(--muted)">To'landi</div>
                  <div style="font-size:15px;font-weight:700;color:var(--success)" class="num"><?= im_money($ps_row['jami_tolandi'] ?? 0) ?></div>
                </div>
                <div class="text-center">
                  <div style="font-size:11px;color:var(--muted)">Ochiq qarz</div>
                  <div style="font-size:15px;font-weight:700;color:<?= $qarz_total > 0 ? 'var(--danger)' : 'var(--success)' ?>" class="num">
                    <?= $qarz_total > 0 ? im_money($qarz_total) : '✓ 0' ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Progress bar -->
            <?php if (($ps_row['jami_olindi'] ?? 0) > 0): ?>
            <?php $pct = min(100, round(($ps_row['jami_tolandi'] ?? 0) / ($ps_row['jami_olindi'] ?? 1) * 100)); ?>
            <div class="mt-3">
              <div class="d-flex justify-content-between mb-1">
                <span style="font-size:11px;color:var(--muted)">To'lov holati</span>
                <span style="font-size:11px;font-weight:700"><?= $pct ?>% to'langan</span>
              </div>
              <div style="height:6px;background:var(--border);border-radius:9px;overflow:hidden">
                <div style="width:<?= $pct ?>%;height:100%;border-radius:9px;transition:.5s;
                            background:<?= $pct >= 100 ? 'var(--success)' : ($pct > 50 ? 'var(--warning)' : 'var(--danger)') ?>">
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── Ochiq qarzlar ────────────────────────────────── -->
        <?php if ($ps_ochiq_qarzlar): ?>
        <div class="im-card mb-3">
          <div class="im-card-header">
            <i class="bi bi-exclamation-triangle-fill" style="color:var(--warning)"></i>
            <span class="im-card-title">Ochiq qarzlar</span>
            <span class="im-badge im-badge-warning"><?= count($ps_ochiq_qarzlar) ?> ta</span>
          </div>
          <div class="im-table-wrap">
            <table class="im-table">
              <thead><tr>
                <th>Partiya / Faktura</th>
                <th class="text-right">Qarz</th>
                <th class="text-right">To'landi</th>
                <th class="text-right">Qoldi</th>
                <th>Muddat</th>
                <th>Amal</th>
              </tr></thead>
              <tbody>
              <?php foreach ($ps_ochiq_qarzlar as $pq):
                $muddati_otdi = $pq['muddat'] && $pq['muddat'] < date('Y-m-d');
              ?>
              <tr>
                <td>
                  <?php if ($pq['partiya_id']): ?>
                    <div class="fw-semibold fs-sm">Partiya #<?= $pq['partiya_id'] ?></div>
                    <?php if ($pq['faktura_nomer']): ?>
                    <div class="text-muted fs-xs"><?= im_f($pq['faktura_nomer']) ?></div>
                    <?php endif; ?>
                    <div class="text-muted fs-xs"><?= im_date($pq['p_sana'] ?? $pq['created_at']) ?></div>
                  <?php else: ?>
                    <div class="fw-semibold fs-sm text-warning">Alohida qarz</div>
                    <div class="text-muted fs-xs"><?= im_date($pq['created_at']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-right num"><?= im_money($pq['qarz_summa']) ?></td>
                <td class="text-right num" style="color:var(--success)"><?= im_money($pq['tolandi']) ?></td>
                <td class="text-right">
                  <span class="im-badge im-badge-<?= $muddati_otdi ? 'danger' : 'warning' ?>">
                    <?= im_money($pq['qoldiq']) ?> so'm
                  </span>
                </td>
                <td class="<?= $muddati_otdi ? 'text-danger fw-semibold' : '' ?> fs-sm">
                  <?= $muddati_otdi ? '⚠️ ' : '' ?><?= $pq['muddat'] ? im_date($pq['muddat']) : '—' ?>
                </td>
                <td>
                  <button class="im-btn im-btn-sm im-btn-primary"
                    onclick="openTolovModal(<?= $pq['id'] ?>,'<?= im_js($ps_detail['nomi']) ?>','<?= im_js($pq['faktura_nomer'] ?: "Partiya #{$pq['partiya_id']}") ?>',<?= (float)$pq['qoldiq'] ?>)">
                    <i class="bi bi-credit-card-fill"></i> To'lov
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <!-- ── To'lovlar tarixi ────────────────────────────── -->
        <div class="im-card mb-3">
          <div class="im-card-header">
            <i class="bi bi-receipt-cutoff" style="color:var(--success)"></i>
            <span class="im-card-title">To'lovlar tarixi</span>
            <span class="im-badge im-badge-muted"><?= count($ps_tolovlar) ?> ta</span>
            <span class="ms-auto fw-bold num" style="color:var(--success)">
              Jami: <?= im_money(array_sum(array_column($ps_tolovlar, 'summa'))) ?> so'm
            </span>
          </div>
          <?php if ($ps_tolovlar): ?>
          <div class="im-table-wrap">
            <table class="im-table">
              <thead><tr>
                <th>Sana</th>
                <th>Partiya</th>
                <th>To'lov turi</th>
                <th class="text-right">Summa</th>
                <th>Admin</th>
                <th>Izoh</th>
              </tr></thead>
              <tbody>
              <?php
              $tt_icons = ['naqd'=>'💵','karta'=>'💳','bank'=>'🏦','usd'=>'🪙'];
              foreach ($ps_tolovlar as $qt): ?>
              <tr>
                <td class="text-muted fs-xs"><?= date('d.m.Y H:i', strtotime($qt['sana'])) ?></td>
                <td class="fs-xs">
                  <div>Partiya #<?= $qt['p_id'] ?></div>
                  <?php if ($qt['faktura_nomer']): ?>
                  <div class="text-muted"><?= im_f($qt['faktura_nomer']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="im-badge im-badge-success">
                    <?= $tt_icons[$qt['tolov_turi']] ?? '' ?> <?= im_f($qt['tolov_turi']) ?>
                  </span>
                  <?php if ((float)$qt['usd_summa'] > 0): ?>
                  <div class="text-muted fs-xs"><?= im_money($qt['usd_summa']) ?>$ × <?= im_money($qt['usd_kurs']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-right fw-bold num" style="color:var(--success)"><?= im_money($qt['summa']) ?> so'm</td>
                <td class="text-muted fs-xs"><?= im_f($qt['admin_ism'] ?? '—') ?></td>
                <td class="text-muted fs-xs"><?= im_f($qt['izoh'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="im-empty py-3"><i class="bi bi-receipt"></i><p class="text-muted fs-sm">Hali to'lov qilinmagan</p></div>
          <?php endif; ?>
        </div>

        <!-- ── Partiyalar tarixi ───────────────────────────── -->
        <div class="im-card">
          <div class="im-card-header">
            <i class="bi bi-box-seam-fill" style="color:var(--primary)"></i>
            <span class="im-card-title">Barcha partiyalar</span>
            <span class="im-badge im-badge-muted"><?= count($ps_partiyalar) ?> ta</span>
            <a href="<?= im_BASE ?>sklad/qabul.php" class="im-btn im-btn-primary im-btn-sm ms-auto">
              <i class="bi bi-plus-lg"></i> Yangi partiya
            </a>
          </div>
          <?php if ($ps_partiyalar): ?>
          <div class="im-table-wrap">
            <table class="im-table">
              <thead><tr>
                <th>Faktura / Sana</th>
                <th class="text-center">Mahsulot</th>
                <th class="text-right">Jami qiymat</th>
                <th>To'lov</th>
                <th class="text-right">Qoldi</th>
                <th>Holat</th>
              </tr></thead>
              <tbody>
              <?php foreach ($ps_partiyalar as $p): ?>
              <tr>
                <td>
                  <div class="fw-semibold fs-sm"><?= im_f($p['faktura_nomer'] ?: "Partiya #{$p['id']}") ?></div>
                  <div class="text-muted fs-xs"><?= im_date($p['sana']) ?></div>
                </td>
                <td class="text-center">
                  <span class="im-badge im-badge-muted"><?= (int)$p['item_soni'] ?> xil</span>
                </td>
                <td class="text-right num fw-bold"><?= im_money($p['jami_summa']) ?> so'm</td>
                <td>
                  <span class="im-badge im-badge-<?= $p['tolov_turi']==='qarz'?'warning':'success' ?>">
                    <?= im_tt_label($p['tolov_turi'], true) ?>
                  </span>
                </td>
                <td class="text-right">
                  <?php if ((float)$p['qarz_qoldi'] > 0): ?>
                  <span class="im-badge im-badge-warning"><?= im_money($p['qarz_qoldi']) ?> so'm</span>
                  <?php else: ?>
                  <span class="im-badge im-badge-success">✓ To'liq</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="im-badge im-badge-<?= $p['holat']==='yopiq'?'success':'warning' ?>">
                    <?= $p['holat']==='yopiq' ? '✓ Yopiq' : '⏳ Ochiq' ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
              <tfoot style="background:var(--bg);font-weight:700">
                <tr>
                  <td colspan="2" class="fw-bold">Jami</td>
                  <td class="text-right num"><?= im_money(array_sum(array_column($ps_partiyalar,'jami_summa'))) ?> so'm</td>
                  <td colspan="2" class="text-right num" style="color:var(--warning)">
                    <?= im_money(array_sum(array_column($ps_partiyalar,'qarz_qoldi'))) ?> so'm qoldi
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <?php else: ?>
          <div class="im-empty py-3"><i class="bi bi-box-seam"></i><p class="text-muted fs-sm">Hali partiya yo'q</p></div>
          <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- Hech narsa tanlanmagan -->
        <div class="im-card h-100 d-flex align-items-center justify-content-center" style="min-height:300px">
          <div class="im-empty">
            <i class="bi bi-person-lines-fill"></i>
            <h4>Kontragentni tanlang</h4>
            <p class="text-muted">Chap tarafdan kontragentni bosing</p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div>
</div>

<!-- ── TO'LOV MODAL ──────────────────────────────────────── -->
<div id="tolov-modal" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:12px">
  <div class="im-card" style="width:460px;max-width:95vw;max-height:92vh;overflow-y:auto">
    <div class="im-card-header" style="background:var(--primary)">
      <i class="bi bi-credit-card-fill" style="color:var(--accent)"></i>
      <span class="im-card-title" style="color:#fff">Kontragentga to'lov</span>
      <button class="im-btn im-btn-icon im-btn-ghost ms-auto" onclick="closeTolovModal()" style="color:#fff">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="im-card-body p-4">
      <div class="mb-3 p-3 rounded-3" style="background:var(--bg)">
        <div class="fw-bold" id="tm-nomi"></div>
        <div class="text-muted fs-xs" id="tm-faktura"></div>
        <div class="mt-1">
          <span class="text-muted fs-sm">Qoldi:</span>
          <span class="fw-bold num ms-1" style="color:var(--danger)" id="tm-qoldi"></span>
        </div>
      </div>

      <!-- To'lov turi -->
      <div class="im-form-group mb-3">
        <label class="im-label">To'lov turi</label>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px">
          <?php foreach (['naqd','karta','bank','usd'] as $v): ?>
          <button type="button" class="im-btn im-btn-outline im-btn-sm" id="tm-tt-<?= $v ?>"
                  data-tt="<?= $v ?>" onclick="tmTtChange('<?= im_js($v) ?>')">
            <?= im_tt_label($v, true) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div id="tm-som-block">
        <div class="im-form-group mb-3">
          <label class="im-label">Summa (so'm)</label>
          <input type="number" class="im-input num" id="tm-summa" min="1" placeholder="0">
          <div class="text-muted fs-xs mt-1">Yoki <a href="#" onclick="tmMaxSumma();return false">to'liq qoldiqni to'lash</a></div>
        </div>
      </div>
      <div id="tm-usd-block" style="display:none">
        <div class="row g-2 mb-3">
          <div class="col-7">
            <label class="im-label">USD summasi ($)</label>
            <input type="number" class="im-input num" id="tm-usd-summa" step="0.01" placeholder="0.00" oninput="tmCalc()">
          </div>
          <div class="col-5">
            <label class="im-label">Kurs (so'm)</label>
            <input type="number" class="im-input num" id="tm-usd-kurs" value="<?= $usd_kurs_now ?>" oninput="tmCalc()">
          </div>
        </div>
        <div class="im-form-group mb-3">
          <label class="im-label">So'm ekvivalenti</label>
          <input type="text" class="im-input num" id="tm-usd-ekviv" readonly style="background:var(--bg);font-weight:700">
        </div>
      </div>
      <div class="im-form-group mb-4">
        <label class="im-label">Izoh (ixtiyoriy)</label>
        <input type="text" class="im-input" id="tm-izoh" placeholder="Qo'shimcha ma'lumot...">
      </div>
      <div class="d-flex gap-2">
        <button class="im-btn im-btn-primary im-btn-lg w-100" id="tm-submit" onclick="doTolov()">
          <i class="bi bi-credit-card-fill"></i> Tasdiqlash
        </button>
        <button class="im-btn im-btn-outline im-btn-lg" onclick="closeTolovModal()">Bekor</button>
      </div>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
var tmQarzId=0, tmQoldi=0, tmTt='naqd';

function openTolovModal(qarzId, psNomi, faktura, qoldi) {
  tmQarzId=qarzId; tmQoldi=qoldi; tmTt='naqd';
  document.getElementById('tm-nomi').textContent    = psNomi;
  document.getElementById('tm-faktura').textContent = faktura;
  document.getElementById('tm-qoldi').textContent   = Number(qoldi).toLocaleString('uz') + " so'm";
  document.getElementById('tm-summa').value         = qoldi;
  document.getElementById('tm-usd-summa').value     = '';
  document.getElementById('tm-izoh').value          = '';
  document.getElementById('tm-usd-ekviv').value     = '';
  tmTtChange('naqd');
  document.getElementById('tolov-modal').style.display='flex';
}

function closeTolovModal() {
  document.getElementById('tolov-modal').style.display='none';
}

function tmTtChange(v) {
  tmTt=v;
  ['naqd','karta','bank','usd'].forEach(t => {
    const el = document.getElementById('tm-tt-'+t);
    if(el) el.className='im-btn im-btn-sm '+(t===v?'im-btn-primary':'im-btn-outline');
  });
  document.getElementById('tm-som-block').style.display = v==='usd'?'none':'block';
  document.getElementById('tm-usd-block').style.display = v==='usd'?'block':'none';
}

function tmCalc() {
  const usd  = parseFloat(document.getElementById('tm-usd-summa').value)||0;
  const kurs = parseFloat(document.getElementById('tm-usd-kurs').value)||0;
  document.getElementById('tm-usd-ekviv').value = (usd*kurs).toLocaleString('uz')+" so'm";
}

function tmMaxSumma() {
  document.getElementById('tm-summa').value = tmQoldi;
}

async function doTolov() {
  const btn = document.getElementById('tm-submit');
  btn.disabled=true;
  const data = {
    qarz_id:    tmQarzId,
    tolov_turi: tmTt,
    summa:      tmTt==='usd'?0:(parseFloat(document.getElementById('tm-summa').value)||0),
    usd_summa:  parseFloat(document.getElementById('tm-usd-summa').value)||0,
    usd_kurs:   parseFloat(document.getElementById('tm-usd-kurs').value)||0,
    izoh:       document.getElementById('tm-izoh').value
  };
  if(tmTt!=='usd'&&data.summa<=0){NHToast.error('Summani kiriting');btn.disabled=false;return;}
  if(tmTt==='usd'&&data.usd_summa<=0){NHToast.error('USD summani kiriting');btn.disabled=false;return;}

  const res = await IMAjax.post(window.im_BASE+'admin/ajax/qarz-tolov.php', data);
  btn.disabled=false;
  if(res.status==='ok'){
    NHToast.success(res.msg);
    closeTolovModal();
    setTimeout(()=>location.reload(),800);
  } else {
    NHToast.error(res.msg);
  }
}

document.getElementById('tolov-modal').addEventListener('click', function(e){
  if(e.target===this) closeTolovModal();
});
</script>
</body>
</html>
