<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();

// ── Filter ────────────────────────────────────────────────────
$status_f = $_GET['status'] ?? '';          // ''|'ochiq'|'yopiq'
$ps_f     = (int)($_GET['ps'] ?? 0);        // Postavshik ID filter

// ── Kontragentlar (Postavshiklar) ro'yxati ─────────────────────
$postavshiklar = $db->rows("SELECT * FROM im_postavshiklar WHERE status=1 ORDER BY nomi");

// ── Barcha partiyalar (To'liq hisobot) ─────────────────────────
$pwhere = "1=1";
if ($status_f) $pwhere .= " AND p.holat='" . mysqli_real_escape_string($link,$status_f) . "'";
if ($ps_f)     $pwhere .= " AND p.postavshik_id=$ps_f";

$partiyalar = $db->rows(
    "SELECT p.*,
            ps.nomi AS ps_nomi, ps.telefon AS ps_tel,
            (SELECT COUNT(*) FROM im_partiya_items WHERE partiya_id=p.id) AS item_soni,
            (SELECT SUM(soni) FROM im_partiya_items WHERE partiya_id=p.id) AS jami_soni,
            COALESCE((SELECT SUM(summa) FROM im_qarz_tolovlar qt
                      JOIN im_postavshik_qarz pq ON pq.id=qt.qarz_id
                      WHERE pq.partiya_id=p.id),0) AS tolangan_extra
     FROM im_partiyalar p
     LEFT JOIN im_postavshiklar ps ON ps.id=p.postavshik_id
     WHERE $pwhere
     ORDER BY p.id DESC"
);

// ── Postavshik Qarzlari (aktiv) ────────────────────────────────
$qarz_filter = "pq.status='ochiq'";
if ($ps_f) $qarz_filter .= " AND pq.postavshik_id=$ps_f";

$ps_qarzlar = $db->rows(
    "SELECT pq.*,
            ps.nomi AS ps_nomi, ps.telefon AS ps_tel,
            p.faktura_nomer, p.sana AS partiya_sana, p.tolov_turi AS partiya_tt, p.qabul_filial_id,
            f.nomi AS filial_nomi,
            (SELECT COUNT(*) FROM im_partiya_items WHERE partiya_id=p.id) AS item_soni,
            (SELECT COALESCE(SUM(summa),0) FROM im_qarz_tolovlar WHERE qarz_id=pq.id) AS qo_shimcha_tolandi
     FROM im_postavshik_qarz pq
     LEFT JOIN im_postavshiklar ps ON ps.id=pq.postavshik_id
     LEFT JOIN im_partiyalar p ON p.id=pq.partiya_id
     LEFT JOIN im_filiallar f ON f.id=p.qabul_filial_id
     WHERE $qarz_filter
     ORDER BY pq.muddat ASC"
);

// ── Mijoz nasiyalari ───────────────────────────────────────────
$mij_nasiya = $db->rows(
    "SELECT n.*, m.ism AS mijoz, m.telefon AS mij_tel, s.chek_nomer, s.filial_id,
            f.nomi AS filial_nomi
     FROM im_nasiya n
     LEFT JOIN im_mijozlar m ON m.id=n.mijoz_id
     LEFT JOIN im_sotuvlar s ON s.id=n.sotuv_id
     LEFT JOIN im_filiallar f ON f.id=s.filial_id
     WHERE n.holat='aktiv'
     ORDER BY n.qaytarish_sana ASC"
);

$postavshik_list = $db->rows("SELECT id, nomi FROM im_postavshiklar WHERE status=1 ORDER BY nomi ASC");

// ── Yig'indi ko'rsatkichlar ────────────────────────────────────
$ps_jami_qarz  = (float)$db->val("SELECT COALESCE(SUM(qoldiq),0) FROM im_postavshik_qarz WHERE status='ochiq'");
$mij_jami_nas  = (float)$db->val("SELECT COALESCE(SUM(qoldiq),0) FROM im_nasiya WHERE holat='aktiv'");
$jami_partiya  = (int)$db->val("SELECT COUNT(*) FROM im_partiyalar");
$ochiq_partiya = (int)$db->val("SELECT COUNT(*) FROM im_partiyalar WHERE holat='ochiq'");

// ── To'lovlar tarixi (oxirgi 50 ta) ──────────────────────────
$tolovlar = $db->rows(
    "SELECT qt.*, pq.postavshik_id, ps.nomi AS ps_nomi,
            p.faktura_nomer, p.id AS p_id,
            x.ism AS admin_ism
     FROM im_qarz_tolovlar qt
     LEFT JOIN im_postavshik_qarz pq ON pq.id = qt.qarz_id
     LEFT JOIN im_postavshiklar ps   ON ps.id  = pq.postavshik_id
     LEFT JOIN im_partiyalar p        ON p.id   = pq.partiya_id
     LEFT JOIN im_xodimlar x          ON x.id   = qt.admin_id
     ORDER BY qt.sana DESC LIMIT 50"
);

$jami_tolangan_ps = (float)$db->val("SELECT COALESCE(SUM(summa),0) FROM im_qarz_tolovlar");
$usd_kurs_now = im_usd_kurs() ?: 12700;
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Kontragentlar & Partiyalar | IMezon Admin</title>
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
    <div class="im-page-title"><i class="bi bi-truck-flatbed me-1"></i> Kontragentlar & Partiyalar</div>
    <div class="im-topbar-actions">
      <button class="im-btn im-btn-warning im-btn-sm" onclick="window.openEskiQarzModal()">
        <i class="bi bi-wallet2"></i> Alohida qarz kiritish
      </button>
      <button class="im-btn im-btn-primary im-btn-sm" id="btn-add-kontragent">
        <i class="bi bi-plus-lg"></i> Kontragent qo'shish
      </button>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>
  <main class="im-content">

    <!-- ── UMUMIY STATISTIKA ───────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--primary)">
          <div class="im-stat-icon" style="background:rgba(79,70,229,.1);color:var(--primary)"><i class="bi bi-box-seam-fill"></i></div>
          <div>
            <div class="im-stat-label">Jami partiya</div>
            <div class="im-stat-value"><?= $jami_partiya ?> ta</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid #f59e0b">
          <div class="im-stat-icon" style="background:rgba(245,158,11,.1);color:#f59e0b"><i class="bi bi-hourglass-split"></i></div>
          <div>
            <div class="im-stat-label">Ochiq partiya</div>
            <div class="im-stat-value" style="color:#f59e0b"><?= $ochiq_partiya ?> ta</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--warning)">
          <div class="im-stat-icon" style="background:rgba(255,193,7,.1);color:var(--warning)"><i class="bi bi-truck"></i></div>
          <div>
            <div class="im-stat-label">Postavshiklarga qarz</div>
            <div class="im-stat-value num" style="color:var(--warning)"><?= im_money($ps_jami_qarz) ?> so'm</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="im-stat-card" style="border-left:4px solid var(--danger)">
          <div class="im-stat-icon" style="background:rgba(220,53,69,.1);color:var(--danger)"><i class="bi bi-people-fill"></i></div>
          <div>
            <div class="im-stat-label">Mijozlardan nasiya</div>
            <div class="im-stat-value num" style="color:var(--danger)"><?= im_money($mij_jami_nas) ?> so'm</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── FILTER ─────────────────────────────────────────────── -->
    <div class="im-card mb-4">
      <div class="im-card-body p-3">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
          <select name="ps" class="im-select im-input-sm" style="max-width:200px" onchange="this.form.submit()">
            <option value="0">Barcha kontragentlar</option>
            <?php foreach ($postavshiklar as $ps): ?>
            <option value="<?= $ps['id'] ?>" <?= $ps_f==$ps['id']?'selected':'' ?>><?= im_f($ps['nomi']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="d-flex gap-1">
            <?php foreach ([''=> 'Hammasi','ochiq'=>'Ochiq partiyalar','yopiq'=>'Yopiq partiyalar'] as $v=>$t): ?>
            <a href="?ps=<?= $ps_f ?>&status=<?= $v ?>" class="im-btn im-btn-sm <?= $status_f===$v?'im-btn-primary':'im-btn-outline' ?>"><?= $t ?></a>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- ── PARTIYALAR (Kirib kelgan yuklar) ──────────────────── -->
    <div class="im-card mb-4 im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-box-seam-fill" style="color:var(--primary)"></i>
        <span class="im-card-title">Barcha partiyalar (kirib kelgan yuklar)</span>
        <span class="im-badge im-badge-muted"><?= count($partiyalar) ?> ta</span>
        <a href="<?= im_BASE ?>sklad/qabul.php" class="im-btn im-btn-primary im-btn-sm ms-auto">
          <i class="bi bi-plus-lg"></i> Yangi partiya
        </a>
      </div>
      <?php if ($partiyalar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Kontragent</th>
              <th>Faktura</th>
              <th class="text-center">Mahsulot</th>
              <th class="text-right">Umumiy qiymat</th>
              <th>To'lov turi</th>
              <th class="text-right">To'landi</th>
              <th class="text-right">Qoldi (qarz)</th>
              <th>Holat</th>
              <th>Sana</th>
              <th style="width:60px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($partiyalar as $p):
              $qoldi = (float)$p['qarz_qoldi'];
              $tolangan = (float)$p['tolandi'];
              $jami = (float)$p['jami_summa'];
              $is_qarz = $qoldi > 0;
            ?>
            <tr>
              <td><code class="fs-xs">#<?= $p['id'] ?></code></td>
              <td>
                <div class="fw-semibold"><?= im_f($p['ps_nomi'] ?: '—') ?></div>
                <div class="text-muted fs-xs"><?= im_f($p['ps_tel'] ?: '') ?></div>
              </td>
              <td class="fs-xs text-muted"><?= im_f($p['faktura_nomer'] ?: '—') ?></td>
              <td class="text-center">
                <span class="im-badge im-badge-muted"><?= (int)$p['item_soni'] ?> xil</span>
                <span class="text-muted fs-xs"><?= (int)$p['jami_soni'] ?> dona</span>
              </td>
              <td class="text-right num fw-bold"><?= im_money($jami) ?> so'm</td>
              <td>
                <span class="im-badge im-badge-<?= $p['tolov_turi']==='qarz'?'warning':'success' ?>">
                <?php
                  echo im_tt_label($p['tolov_turi'], true);
                ?>
                </span>
              </td>
              <td class="text-right num" style="color:var(--success)"><?= im_money($tolangan) ?> so'm</td>
              <td class="text-right num fw-bold" style="color:<?= $is_qarz?'var(--warning)':'var(--success)' ?>">
                <?php if ($is_qarz): ?>
                  <span class="im-badge im-badge-warning"><?= im_money($qoldi) ?> so'm</span>
                <?php else: ?>
                  <span class="im-badge im-badge-success">✓ To'liq</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="im-badge im-badge-<?= $p['holat']==='yopiq'?'success':'warning' ?>">
                  <?= $p['holat']==='yopiq' ? '✓ Yopiq' : '⏳ Ochiq' ?>
                </span>
              </td>
              <td class="text-muted fs-xs"><?= im_date($p['sana']) ?></td>
              <td>
                <button class="im-btn im-btn-sm im-btn-outline"
                  onclick="window.openPartiyaModal(<?= $p['id'] ?>)"
                  title="Tafsilotlarni ko'rish">
                  <i class="bi bi-eye-fill"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--accent-light,#fdf8ee)">
              <td colspan="4" class="fw-bold">Jami</td>
              <td class="text-right num fw-bold"><?= im_money(array_sum(array_column($partiyalar,'jami_summa'))) ?> so'm</td>
              <td></td>
              <td class="text-right num fw-bold" style="color:var(--success)"><?= im_money(array_sum(array_column($partiyalar,'tolandi'))) ?> so'm</td>
              <td class="text-right num fw-bold" style="color:var(--warning)"><?= im_money(array_sum(array_column($partiyalar,'qarz_qoldi'))) ?> so'm</td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-box-seam"></i><h4>Partiya yo'q</h4><p>Hali yuk qabul qilinmagan</p></div>
      <?php endif; ?>
    </div>

    <!-- ── AKTIV QARZLAR (Postavshiklarga) ───────────────────── -->
    <?php if ($ps_qarzlar): ?>
    <div class="im-card mb-4 im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-exclamation-triangle-fill" style="color:var(--warning)"></i>
        <span class="im-card-title">Postavshiklarga aktiv qarzlar</span>
        <span class="im-badge im-badge-warning"><?= count($ps_qarzlar) ?> ta</span>
      </div>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>Kontragent</th>
              <th>Partiya</th>
              <th>Filial</th>
              <th class="text-right">Qarz summasi</th>
              <th class="text-right">To'landi</th>
              <th class="text-right">Qoldi</th>
              <th>Muddat</th>
              <th>Holat</th>
              <th style="width:110px">Amal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ps_qarzlar as $pq):
              $qoldi_r = (float)($pq['qoldiq'] ?? ((float)$pq['qarz_summa'] - (float)$pq['tolandi']));
              $muddati_otdi = $pq['muddat'] && $pq['muddat'] < date('Y-m-d');
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= im_f($pq['ps_nomi']) ?></div>
                <div class="text-muted fs-xs"><?= im_f($pq['ps_tel'] ?: '') ?></div>
              </td>
              <td class="fs-xs">
                <?php if ($pq['partiya_id']): ?>
                  <div>Partiya #<?= $pq['partiya_id'] ?></div>
                  <?php if ($pq['faktura_nomer']): ?>
                  <div class="text-muted"><?= im_f($pq['faktura_nomer']) ?></div>
                  <?php endif; ?>
                  <div class="text-muted"><?= im_date($pq['partiya_sana'] ?? $pq['created_at']) ?></div>
                <?php else: ?>
                  <div class="text-warning fw-semibold">Alohida qarz</div>
                  <div class="text-muted"><?= im_date($pq['created_at']) ?></div>
                <?php endif; ?>
              </td>
              <td class="fs-xs">
                <?php if ($pq['partiya_id'] && $pq['filial_nomi']): ?>
                  <span class="im-badge im-badge-muted"><i class="bi bi-shop"></i> <?= im_f($pq['filial_nomi']) ?></span>
                <?php elseif ($pq['partiya_id']): ?>
                  <span class="text-muted">Filial #<?= (int)$pq['qabul_filial_id'] ?></span>
                <?php else: ?>
                  <span class="text-muted">— (markaz)</span>
                <?php endif; ?>
              </td>
              <td class="text-right num"><?= im_money($pq['qarz_summa']) ?> so'm</td>
              <td class="text-right num" style="color:var(--success)"><?= im_money($pq['tolandi']) ?> so'm</td>
              <td class="text-right num fw-bold" style="color:var(--warning)"><?= im_money($qoldi_r) ?> so'm</td>
              <td class="<?= $muddati_otdi?'text-danger fw-semibold':'' ?> fs-sm">
                <?= $muddati_otdi ? '⚠️ ' : '' ?><?= $pq['muddat'] ? im_date($pq['muddat']) : '—' ?>
              </td>
              <td>
                <span class="im-badge im-badge-<?= $muddati_otdi?'danger':'warning' ?>">
                  <?= $muddati_otdi ? "Muddati o'tgan" : 'Aktiv qarz' ?>
                </span>
              </td>
              <td class="text-right" style="white-space:nowrap">
                <button class="im-btn im-btn-sm im-btn-primary"
                  onclick="window.openTolovModal(<?= $pq['id'] ?>,
                    '<?= im_js($pq['ps_nomi']) ?>',
                    '<?= im_js($pq['faktura_nomer'] ?: "Partiya #{$pq['partiya_id']}") ?>',
                    <?= (float)$pq['qoldiq'] ?>)"
                  title="To'lov qilish">
                  <i class="bi bi-credit-card-fill"></i> To'lov
                </button>
                <button class="im-btn im-btn-sm im-btn-outline" style="margin-left:4px"
                  onclick="window.openTarixModal(<?= $pq['id'] ?>)"
                  title="To'lov tarixini ko'rish">
                  <i class="bi bi-clock-history"></i> Tarix
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── MIJOZ NASIYALARI ──────────────────────────────────── -->
    <div class="im-card im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-people-fill" style="color:var(--danger)"></i>
        <span class="im-card-title">Mijozlardan nasiyalar (aktiv)</span>
        <span class="im-badge im-badge-danger"><?= count($mij_nasiya) ?> ta</span>
        <a href="<?= im_BASE ?>dukon/nasiya.php" class="im-btn im-btn-outline im-btn-sm ms-auto">To'lov qabul →</a>
      </div>
      <?php if ($mij_nasiya): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>Mijoz</th>
              <th>Chek</th>
              <th>Filial</th>
              <th class="text-right">Qarz</th>
              <th class="text-right">To'landi</th>
              <th class="text-right">Qoldi</th>
              <th>Muddat</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mij_nasiya as $n):
              $qoldi = (float)($n['qoldiq'] ?? ((float)$n['qarz_summa'] - (float)$n['tolangan']));
              $muddati = $n['qaytarish_sana'] < date('Y-m-d');
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= im_f($n['mijoz']) ?></div>
                <div class="text-muted fs-xs"><?= im_f($n['mij_tel'] ?: '—') ?></div>
              </td>
              <td><code class="fs-xs"><?= im_f($n['chek_nomer'] ?: '—') ?></code></td>
              <td class="text-muted fs-xs"><?= im_f($n['filial_nomi'] ?: '—') ?></td>
              <td class="text-right num"><?= im_money($n['qarz_summa']) ?> so'm</td>
              <td class="text-right num" style="color:var(--success)"><?= im_money($n['tolangan']) ?> so'm</td>
              <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($qoldi) ?> so'm</td>
              <td class="<?= $muddati?'text-danger fw-semibold':'' ?> fs-sm">
                <?= $muddati ? '⚠️ ' : '' ?><?= im_date($n['qaytarish_sana']) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--accent-light,#fdf8ee)">
              <td colspan="5" class="fw-bold">Jami qoldi</td>
              <td class="text-right num fw-bold" style="color:var(--danger)"><?= im_money($mij_jami_nas) ?> so'm</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-people"></i><h4>Aktiv nasiya yo'q</h4><p class="text-muted">Barcha nasiyalar to'liq to'langan</p></div>
      <?php endif; ?>
    </div>

    <!-- ── TO'LOVLAR TARIXI ──────────────────────────────────── -->
    <div class="im-card mt-4 im-slide-in">
      <div class="im-card-header">
        <i class="bi bi-receipt-cutoff" style="color:var(--success)"></i>
        <span class="im-card-title">Kontragentlarga to'lovlar tarixi</span>
        <span class="im-badge im-badge-muted"><?= count($tolovlar) ?> ta</span>
        <span class="ms-auto fw-bold num" style="color:var(--success)">Jami to'landi: <?= im_money($jami_tolangan_ps) ?> so'm</span>
      </div>
      <?php if ($tolovlar): ?>
      <div class="im-table-wrap">
        <table class="im-table">
          <thead>
            <tr>
              <th>Kontragent</th>
              <th>Partiya / Faktura</th>
              <th>To'lov turi</th>
              <th class="text-right">Summa</th>
              <th class="text-right">USD</th>
              <th>Admin</th>
              <th>Izoh</th>
              <th>Sana</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($tolovlar as $qt): ?>
          <tr>
            <td class="fw-semibold"><?= im_f($qt['ps_nomi'] ?: '—') ?></td>
            <td class="text-muted fs-xs">
              <?= im_f($qt['faktura_nomer'] ?: "Partiya #{$qt['p_id']}") ?>
            </td>
            <td>
              <span class="im-badge im-badge-success"><?= im_tt_label($qt['tolov_turi'], true) ?></span>
            </td>
            <td class="text-right num fw-bold" style="color:var(--success)"><?= im_money($qt['summa']) ?> so'm</td>
            <td class="text-right num text-muted fs-xs">
              <?= (float)$qt['usd_summa'] > 0 ? im_money($qt['usd_summa']).'$' : '—' ?>
            </td>
            <td class="text-muted fs-xs"><?= im_f($qt['admin_ism'] ?: '—') ?></td>
            <td class="text-muted fs-xs"><?= im_f($qt['izoh'] ?: '—') ?></td>
            <td class="text-muted fs-xs"><?= im_datetime($qt['sana']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="im-empty"><i class="bi bi-receipt"></i><h4>To'lov yo'q</h4><p class="text-muted">Hali kontragentga to'lov amalga oshirilmagan</p></div>
      <?php endif; ?>
    </div>

  </main>
</div>
</div>

<!-- ── PARTIYA DETAIL MODAL ──────────────────────────────── -->
<div id="partiya-modal" style="display:none;position:fixed;inset:0;z-index:9997;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
  <div class="im-card" style="width:860px;max-width:97vw;max-height:92vh;overflow-y:auto">
    <div class="im-card-header" style="background:var(--primary);position:sticky;top:0;z-index:1">
      <i class="bi bi-box-seam-fill" style="color:var(--accent)"></i>
      <span class="im-card-title" style="color:#fff" id="pm-title">Partiya tafsilotlari</span>
      <button class="im-btn im-btn-icon im-btn-ghost ms-auto" onclick="window.closePartiyaModal()" style="color:#fff">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="im-card-body p-3" id="pm-body">
      <div class="text-center p-5 text-muted"><i class="bi bi-hourglass-split fs-3"></i><br>Yuklanmoqda...</div>
    </div>
  </div>
</div>

<!-- ── TO'LOV MODAL ────────────────────────────────────────── -->
<div id="tolov-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:12px">
  <div class="im-card" style="width:460px;max-width:95vw;max-height:92vh;overflow-y:auto">
    <div class="im-card-header" style="background:var(--primary)">
      <i class="bi bi-credit-card-fill" style="color:var(--accent)"></i>
      <span class="im-card-title" style="color:#fff">Kontragentga to'lov</span>
      <button class="im-btn im-btn-icon im-btn-ghost ms-auto" onclick="window.closeTolovModal()" style="color:#fff">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="im-card-body p-4">
      <div class="mb-3 p-3 rounded-3" style="background:var(--bg)">
        <div class="fw-bold" id="tm-ps-nomi"></div>
        <div class="text-muted fs-xs" id="tm-faktura"></div>
        <div class="mt-1">
          <span class="text-muted fs-sm">Qoldi:</span>
          <span class="fw-bold num ms-1" style="color:var(--warning)" id="tm-qoldi"></span>
        </div>
      </div>

      <!-- To'lov turi -->
      <div class="im-form-group mb-3">
        <label class="im-label">To'lov turi</label>
        <div id="tm-tt-wrap" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px">
          <?php foreach (['naqd','karta','bank','usd'] as $v): ?>
          <button type="button" class="im-btn im-btn-outline im-btn-sm" id="tm-tt-<?= $v ?>"
                  data-tt="<?= $v ?>" onclick="window.tmTtChange(this.dataset.tt)">
            <?= im_tt_label($v, true) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- So'm summasi -->
      <div class="im-form-group mb-3" id="tm-som-block">
        <label class="im-label">Summa (so'm)</label>
        <input type="number" class="im-input num" id="tm-summa" min="1" placeholder="0" oninput="tmCalc()">
        <div class="text-muted fs-xs mt-1">Yoki <a href="#" onclick="window.tmMaxSumma();return false">to'liq qoldiqni to'lash</a></div>
      </div>

      <!-- USD blok -->
      <div id="tm-usd-block" style="display:none">
        <div class="row g-2 mb-3">
          <div class="col-7">
            <label class="im-label">USD summasi ($)</label>
            <input type="number" class="im-input num" id="tm-usd-summa" min="0" step="0.01" placeholder="0.00" oninput="tmCalc()">
          </div>
          <div class="col-5">
            <label class="im-label">Kurs (so'm)</label>
            <input type="number" class="im-input num" id="tm-usd-kurs" value="<?= $usd_kurs_now ?>" min="1" oninput="tmCalc()">
          </div>
        </div>
        <div class="im-form-group mb-3">
          <label class="im-label">So'm ekvivalenti</label>
          <input type="text" class="im-input num" id="tm-usd-ekviv" readonly style="background:var(--bg);font-weight:700">
        </div>
      </div>

      <!-- Izoh -->
      <div class="im-form-group mb-4">
        <label class="im-label">Izoh (ixtiyoriy)</label>
        <input type="text" class="im-input" id="tm-izoh" placeholder="Qo'shimcha ma'lumot...">
      </div>

      <div class="d-flex gap-2">
        <button class="im-btn im-btn-primary im-btn-lg w-100" id="tm-submit-btn" onclick="window.doTolov()">
          <i class="bi bi-credit-card-fill"></i> To'lovni tasdiqlash
        </button>
        <button class="im-btn im-btn-outline im-btn-lg" onclick="window.closeTolovModal()">Bekor</button>
      </div>
    </div>
  </div>
</div>

<!-- ── TARIX MODAL ───────────────────────────────── -->
<div id="tarix-modal" style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
  <div class="im-card" style="width:720px;max-width:96vw;max-height:88vh;overflow-y:auto">
    <div class="im-card-header" style="background:var(--primary)">
      <i class="bi bi-clock-history" style="color:var(--accent)"></i>
      <span class="im-card-title" style="color:#fff" id="tarix-title">To'lov tarixi</span>
      <button class="im-btn im-btn-icon im-btn-ghost ms-auto" onclick="window.closeTarixModal()" style="color:#fff">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="im-card-body p-3" id="tarix-body">
      <div class="text-center p-4 text-muted"><i class="bi bi-hourglass-split fs-3"></i><br>Yuklanmoqda...</div>
    </div>
  </div>
</div>

<!-- ── Kontragent qo'shish modali ──────────────────────── -->
<div class="im-overlay" id="kontragent-modal">
  <div class="im-modal" style="max-width:500px;width:96%">
    <div class="im-modal-header" style="border-left:4px solid var(--primary)">
      <i class="bi bi-truck-flatbed" style="color:var(--primary);font-size:20px"></i>
      <span class="im-modal-title" id="km-title">Yangi Kontragent</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="im-modal-body">
      <input type="hidden" id="km-id" value="">
      <div class="row g-3">
        <div class="col-12">
          <label class="im-label">Kompaniya nomi <span style="color:var(--danger)">*</span></label>
          <input class="im-input" type="text" id="km-nomi" placeholder="Masalan: Alfa Trading" maxlength="200">
        </div>
        <div class="col-md-6">
          <label class="im-label">Telefon</label>
          <input class="im-input" type="text" id="km-tel" placeholder="+998 90 000 00 00">
        </div>
        <div class="col-md-6">
          <label class="im-label">Email</label>
          <input class="im-input" type="email" id="km-email" placeholder="info@example.com">
        </div>
        <div class="col-md-6">
          <label class="im-label">Shahar</label>
          <input class="im-input" type="text" id="km-shahar" placeholder="Toshkent, Samarqand...">
        </div>
        <div class="col-md-6">
          <label class="im-label">Kontakt ismi</label>
          <input class="im-input" type="text" id="km-kontakt" placeholder="Mas'ul shaxs">
        </div>
        <div class="col-12">
          <label class="im-label">Izoh</label>
          <textarea class="im-textarea" id="km-izoh" rows="2" placeholder="Qo'shimcha ma'lumot..."></textarea>
        </div>
      </div>
    </div>
    <div class="im-modal-footer">
      <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
      <button class="im-btn im-btn-primary" id="km-save-btn">
        <i class="bi bi-check-circle-fill"></i> Saqlash
      </button>
    </div>
  </div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
var tmQarzId = 0, tmQoldi = 0, tmTt = 'naqd';

function openTolovModal(qarzId, psNomi, faktura, qoldi) {
  tmQarzId = qarzId;
  tmQoldi  = qoldi;
  tmTt     = 'naqd';
  document.getElementById('tm-ps-nomi').textContent = psNomi;
  document.getElementById('tm-faktura').textContent = faktura;
  document.getElementById('tm-qoldi').textContent    = Number(qoldi).toLocaleString('uz') + " so'm";
  document.getElementById('tm-summa').value = qoldi;
  document.getElementById('tm-usd-summa').value = '';
  document.getElementById('tm-izoh').value = '';
  document.getElementById('tm-usd-ekviv').value = '';
  tmTtChange('naqd');
  const modal = document.getElementById('tolov-modal');
  modal.style.display = 'flex';
}

function closeTolovModal() {
  document.getElementById('tolov-modal').style.display = 'none';
}

function tmTtChange(v) {
  tmTt = v;
  ['naqd','karta','bank','usd'].forEach(t => {
    const el = document.getElementById('tm-tt-'+t);
    if (el) el.className = 'im-btn im-btn-sm ' + (t===v ? 'im-btn-primary' : 'im-btn-outline');
  });
  document.getElementById('tm-som-block').style.display = v==='usd' ? 'none' : 'block';
  document.getElementById('tm-usd-block').style.display = v==='usd' ? 'block' : 'none';
}

function tmCalc() {
  if (tmTt !== 'usd') return;
  const usd  = parseFloat(document.getElementById('tm-usd-summa').value) || 0;
  const kurs = parseFloat(document.getElementById('tm-usd-kurs').value)  || 0;
  const ekviv = usd * kurs;
  document.getElementById('tm-usd-ekviv').value = ekviv.toLocaleString('uz') + " so'm";
}

function tmMaxSumma() {
  document.getElementById('tm-summa').value = tmQoldi;
}

async function doTolov() {
  const btn = document.getElementById('tm-submit-btn');
  btn.disabled = true;
  const data = {
    qarz_id:    tmQarzId,
    tolov_turi: tmTt,
    summa:      tmTt==='usd' ? 0 : (parseFloat(document.getElementById('tm-summa').value)||0),
    usd_summa:  parseFloat(document.getElementById('tm-usd-summa').value)||0,
    usd_kurs:   parseFloat(document.getElementById('tm-usd-kurs').value)||0,
    izoh:       document.getElementById('tm-izoh').value
  };
  if (tmTt!=='usd' && data.summa<=0) { NHToast.error('Summani kiriting'); btn.disabled=false; return; }
  if (tmTt==='usd' && data.usd_summa<=0) { NHToast.error('USD summani kiriting'); btn.disabled=false; return; }

  const res = await IMAjax.post(window.im_BASE + 'admin/ajax/qarz-tolov.php', data);
  btn.disabled = false;
  if (res.status==='ok') {
    NHToast.success(res.msg);
    closeTolovModal();
    setTimeout(() => location.reload(), 800);
  } else {
    NHToast.error(res.msg);
  }
}

// Tashqariga bossanda yopish
var _qTm  = document.getElementById('tolov-modal');
var _qTrm = document.getElementById('tarix-modal');
if (_qTm)  _qTm.addEventListener('click',  function(e){ if (e.target===this) window.closeTolovModal(); });
if (_qTrm) _qTrm.addEventListener('click', function(e){ if (e.target===this) window.closeTarixModal(); });

// ── TARIX MODAL ────────────────────────────────────────────────
async function openTarixModal(qarzId) {
  const modal = document.getElementById('tarix-modal');
  const body  = document.getElementById('tarix-body');
  modal.style.display = 'flex';
  body.innerHTML = '<div class="text-center p-4"><i class="bi bi-hourglass-split text-muted fs-3"></i><br>Yuklanmoqda...</div>';

  const res = await fetch(window.im_BASE + 'admin/ajax/qarz-tarix.php?qarz_id=' + qarzId);
  const json = await res.json();
  if (json.status !== 'ok') { body.innerHTML = '<div class="im-empty"><p>Xatolik</p></div>'; return; }

  const q = json.data.qarz;
  const tolovlar = json.data.tolovlar;

  const ttIcon = {naqd:'💵',karta:'💳',bank:'🏦',usd:'🪙'};
  const ttColor = {naqd:'var(--success)',karta:'var(--primary)',bank:'#17a2b8',usd:'var(--accent-dark)'};

  // Sarlavha
  document.getElementById('tarix-title').textContent = q.ps_nomi + ' — ' + q.faktura;

  // Yig'indi
  const pct = q.qarz_summa > 0 ? Math.min(100, Math.round((q.tolandi / q.qarz_summa) * 100)) : 0;
  let html = `
  <div class="mb-3 p-3 rounded-3" style="background:var(--bg)">
    <div class="row g-2">
      <div class="col-4 text-center">
        <div class="text-muted fs-xs">Umumiy qarz</div>
        <div class="fw-bold num">${Number(q.qarz_summa).toLocaleString('uz')} so'm</div>
      </div>
      <div class="col-4 text-center">
        <div class="text-muted fs-xs">To'landi</div>
        <div class="fw-bold num" style="color:var(--success)">${Number(q.tolandi).toLocaleString('uz')} so'm</div>
      </div>
      <div class="col-4 text-center">
        <div class="text-muted fs-xs">Qoldi</div>
        <div class="fw-bold num" style="color:${q.qoldiq>0?'var(--warning)':'var(--success)'}">${Number(q.qoldiq).toLocaleString('uz')} so'm</div>
      </div>
    </div>
    <div class="mt-2">
      <div style="height:6px;background:var(--border);border-radius:9px;overflow:hidden">
        <div style="width:${pct}%;height:100%;background:var(--success);border-radius:9px;transition:.4s"></div>
      </div>
      <div class="fs-xs text-muted mt-1 text-right">${pct}% to'langan</div>
    </div>
  </div>`;

  if (tolovlar.length === 0) {
    html += `<div class="im-empty"><i class="bi bi-receipt"></i><h4>Hali to'lov qilinmagan</h4></div>`;
  } else {
    html += `<table class="im-table" style="font-size:12.5px">
      <thead>
        <tr>
          <th>#</th>
          <th>Sana</th>
          <th>To'lov usuli</th>
          <th class="text-right">Summa</th>
          <th class="text-right">Qoldi</th>
          <th>Kim</th>
          <th>Izoh</th>
        </tr>
      </thead>
      <tbody>`;
    tolovlar.forEach((t, i) => {
      html += `<tr>
        <td class="text-muted fs-xs">${i+1}</td>
        <td class="text-muted fs-xs">${new Date(t.sana).toLocaleDateString('uz-UZ', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'})}</td>
        <td>
          <span class="im-badge im-badge-success" style="background:${ttColor[t.tolov_turi]||'gray'}">
            ${ttIcon[t.tolov_turi]||''} ${t.tolov_nomi}
          </span>
          ${t.usd_summa > 0 ? '<span class="text-muted fs-xs ms-1">('+Number(t.usd_summa).toFixed(2)+'$ × '+Number(t.usd_kurs).toLocaleString()+')</span>' : ''}
        </td>
        <td class="text-right num fw-bold" style="color:var(--success)">${t.summa_fmt} so'm</td>
        <td class="text-right num fw-bold" style="color:${t.qoldi>0?'var(--warning)':'var(--success)'}">
          ${t.qoldi > 0 ? t.qoldi_fmt+" so'm" : '<span class="im-badge im-badge-success">✓ Yopildi</span>'}
        </td>
        <td class="text-muted fs-xs">${im_esc(t.admin)}</td>
        <td class="text-muted fs-xs">${im_esc(t.izoh) || '—'}</td>
      </tr>`;
    });
    html += `</tbody></table>`;
  }

  body.innerHTML = html;
}

function closeTarixModal() {
  document.getElementById('tarix-modal').style.display = 'none';
}

// ── PARTIYA DETAIL MODAL ────────────────────────────────────
window.openPartiyaModal = async function(partiyaId) {
  var modal = document.getElementById('partiya-modal');
  var body  = document.getElementById('pm-body');
  var title = document.getElementById('pm-title');
  modal.style.display = 'flex';
  body.innerHTML = '<div class="text-center p-5 text-muted"><i class="bi bi-hourglass-split fs-3"></i><br>Yuklanmoqda...</div>';

  try {
    var res  = await fetch(window.im_BASE + 'admin/ajax/partiya-detail.php?partiya_id=' + partiyaId);
    var json = await res.json();
    if (json.status !== 'ok') { body.innerHTML = '<div class="im-empty"><p>Xatolik: ' + json.msg + '</p></div>'; return; }

    var p  = json.data.partiya;
    var items    = json.data.items;
    var tolovlar = json.data.tolovlar;
    var qarz     = json.data.qarz;

    title.textContent = p.faktura + ' — ' + p.ps_nomi;

    var pct = qarz && qarz.qarz_summa > 0
      ? Math.min(100, Math.round((qarz.tolandi / qarz.qarz_summa) * 100))
      : (p.jami_summa > 0 ? Math.min(100, Math.round((p.tolandi / p.jami_summa) * 100)) : 0);

    // ─── Asosiy ma'lumotlar ───────────────────────────────
    var html = '<div class="row g-3 mb-3">';
    html += '<div class="col-md-6">';
    html += '<div class="p-3 rounded-3" style="background:var(--bg);border:1px solid var(--border)">';
    html += '<div class="fw-bold mb-2"><i class="bi bi-info-circle-fill" style="color:var(--primary)"></i> Asosiy ma\'lumotlar</div>';
    html += '<table style="width:100%;font-size:13px;border-collapse:collapse">';
    html += '<tr><td class="text-muted py-1" style="width:45%">Kontragent</td><td class="fw-semibold">' + p.ps_nomi + '</td></tr>';
    if (p.ps_tel)  html += '<tr><td class="text-muted py-1">Telefon</td><td>' + p.ps_tel + '</td></tr>';
    html += '<tr><td class="text-muted py-1">Filial</td><td>' + p.filial + '</td></tr>';
    html += '<tr><td class="text-muted py-1">Sana</td><td>' + new Date(p.sana).toLocaleDateString("uz-UZ") + '</td></tr>';
    html += '<tr><td class="text-muted py-1">Qabul qildi</td><td>' + p.qabul_qildi + '</td></tr>';
    html += '<tr><td class="text-muted py-1">To\'lov turi</td><td>' + p.tolov_turi + '</td></tr>';
    html += '<tr><td class="text-muted py-1">Holat</td><td><span class="im-badge im-badge-' + (p.holat==="yopiq"?"success":"warning") + '">' + (p.holat==="yopiq"?"✓ Yopiq":"⏳ Ochiq") + '</span></td></tr>';
    if (p.izoh) html += '<tr><td class="text-muted py-1">Izoh</td><td class="text-muted fs-xs">' + p.izoh + '</td></tr>';
    html += '</table></div></div>';

    // ─── Moliyaviy holat ──────────────────────────────────
    html += '<div class="col-md-6">';
    html += '<div class="p-3 rounded-3" style="background:var(--bg);border:1px solid var(--border)">';
    html += '<div class="fw-bold mb-2"><i class="bi bi-wallet2" style="color:var(--success)"></i> Moliyaviy holat</div>';
    html += '<div class="row g-2 text-center mb-2">';
    html += '<div class="col-4"><div class="text-muted fs-xs">Jami summa</div><div class="fw-bold num">' + p.jami_fmt + "</div></div>";
    html += '<div class="col-4"><div class="text-muted fs-xs">To\'landi</div><div class="fw-bold num" style="color:var(--success)">' + p.tolandi_fmt + "</div></div>";
    html += '<div class="col-4"><div class="text-muted fs-xs">Qoldi (qarz)</div><div class="fw-bold num" style="color:' + (p.qarz_qoldi > 0 ? 'var(--warning)' : 'var(--success)') + '">' + p.qoldi_fmt + "</div></div>";
    html += '</div>';
    html += '<div style="height:8px;background:var(--border);border-radius:9px;overflow:hidden">';
    html += '<div style="width:' + pct + '%;height:100%;background:var(--success);border-radius:9px;transition:.4s"></div></div>';
    html += '<div class="fs-xs text-muted mt-1 text-right">' + pct + "% to'langan</div>";
    html += '</div></div></div></div>';

    // ─── Mahsulotlar ro'yxati ─────────────────────────────
    html += '<div class="fw-bold mb-2 mt-1"><i class="bi bi-boxes" style="color:var(--primary)"></i> Mahsulotlar ro\'yxati <span class="im-badge im-badge-muted">' + items.length + ' xil</span></div>';
    if (items.length === 0) {
      html += '<div class="im-empty"><p>Mahsulot ma\'lumoti yo\'q</p></div>';
    } else {
      html += '<div class="im-table-wrap"><table class="im-table" style="font-size:12.5px">';
      html += '<thead><tr><th>#</th><th>Mahsulot</th><th>Kategoriya</th><th>Filial</th><th class="text-right">Soni</th><th class="text-right">Kirish narx</th><th class="text-right">Sotish narx</th><th class="text-right">Jami</th></tr></thead><tbody>';
      var totalItems = 0;
      items.forEach(function(it, i) {
        totalItems += it.soni;
        html += '<tr>';
        html += '<td class="text-muted fs-xs">' + (i+1) + '</td>';
        html += '<td><div class="fw-semibold">' + it.mahsulot + '</div>';
        if (it.barcode) html += '<div class="text-muted fs-xs">' + it.barcode + '</div>';
        html += '</td>';
        html += '<td class="text-muted fs-xs">' + it.kategoriya + '</td>';
        html += '<td class="fs-xs">' + it.filial + '</td>';
        html += '<td class="text-right num">' + it.soni + '</td>';
        html += '<td class="text-right num">' + it.narx_fmt + "</td>";
        html += '<td class="text-right num" style="color:var(--success)">' + it.sotish_fmt + '</td>';
        html += '<td class="text-right num fw-bold">' + it.jami_fmt + "</td>";
        html += '</tr>';
      });
      html += '<tr style="background:var(--accent-light)"><td colspan="4" class="fw-bold">Jami</td>';
      html += '<td class="text-right num fw-bold">' + totalItems + ' dona</td>';
      html += '<td colspan="2"></td>';
      html += '<td class="text-right num fw-bold">' + p.jami_fmt + '</td></tr>';
      html += '</tbody></table></div>';
    }

    // ─── To'lovlar tarixi ─────────────────────────────────
    html += '<div class="fw-bold mb-2 mt-3"><i class="bi bi-receipt" style="color:var(--success)"></i> To\'lov tarixi <span class="im-badge im-badge-muted">' + tolovlar.length + ' ta</span></div>';
    if (tolovlar.length === 0) {
      html += '<div class="im-empty"><p>To\'lov amalga oshirilmagan</p></div>';
    } else {
      html += '<div class="im-table-wrap"><table class="im-table" style="font-size:12.5px">';
      html += '<thead><tr><th>#</th><th>Sana</th><th>To\'lov turi</th><th class="text-right">Summa</th><th>Izoh</th><th>Kim</th></tr></thead><tbody>';
      tolovlar.forEach(function(t, i) {
        html += '<tr>';
        html += '<td class="text-muted fs-xs">' + (i+1) + '</td>';
        html += '<td class="text-muted fs-xs">' + new Date(t.sana).toLocaleDateString("uz-UZ", {day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"}) + '</td>';
        html += '<td>' + t.turi + (t.usd_summa > 0 ? ' <span class="text-muted fs-xs ms-1">(' + Number(t.usd_summa).toFixed(2) + '$ × ' + Number(t.usd_kurs).toLocaleString() + ')</span>' : '') + '</td>';
        html += '<td class="text-right num fw-bold" style="color:var(--success)">' + t.summa_fmt + " so'm</td>";
        html += '<td class="text-muted fs-xs">' + (t.izoh || '—') + '</td>';
        html += '<td class="text-muted fs-xs">' + t.admin + '</td>';
        html += '</tr>';
      });
      // Jami
      var jamSumma = tolovlar.reduce(function(s, t) { return s + t.summa; }, 0);
      html += '<tr style="background:var(--accent-light)"><td colspan="3" class="fw-bold">Jami to\'langan</td>';
      html += '<td class="text-right num fw-bold" style="color:var(--success)">' + Number(jamSumma).toLocaleString("uz") + " so'm</td><td colspan=\"2\"></td></tr>";
      html += '</tbody></table></div>';
    }

    body.innerHTML = html;
  } catch(e) {
    body.innerHTML = '<div class="im-empty"><p>Tarmoq xatoligi: ' + e.message + '</p></div>';
  }
};

window.closePartiyaModal = function() {
  document.getElementById('partiya-modal').style.display = 'none';
};

// Tashqariga bossanda yopish
var _pmEl = document.getElementById('partiya-modal');
if (_pmEl) _pmEl.addEventListener('click', function(e){ if (e.target===this) window.closePartiyaModal(); });

// ── Funksiyalarni global window ga eksport qil (inline onclick uchun zarur) ──
window.openTolovModal  = openTolovModal;
window.closeTolovModal = closeTolovModal;
window.openTarixModal  = openTarixModal;
window.closeTarixModal = closeTarixModal;
window.tmTtChange      = tmTtChange;
window.tmCalc          = tmCalc;
window.tmMaxSumma      = tmMaxSumma;
window.doTolov         = doTolov;

// ── Kontragent qo'shish ─────────────────────────────────
document.getElementById('btn-add-kontragent').addEventListener('click', () => {
  document.getElementById('km-title').textContent = "Yangi Kontragent qo'shish";
  document.getElementById('km-id').value    = '';
  document.getElementById('km-nomi').value  = '';
  document.getElementById('km-tel').value   = '';
  document.getElementById('km-email').value = '';
  document.getElementById('km-shahar').value= '';
  document.getElementById('km-kontakt').value='';
  document.getElementById('km-izoh').value  = '';
  NHModal.open('kontragent-modal');
  setTimeout(() => document.getElementById('km-nomi').focus(), 150);
});

document.getElementById('km-save-btn').addEventListener('click', async () => {
  const nomi    = document.getElementById('km-nomi').value.trim();
  const telefon = document.getElementById('km-tel').value.trim();
  const email   = document.getElementById('km-email').value.trim();
  const shahar  = document.getElementById('km-shahar').value.trim();
  const kontakt_ism = document.getElementById('km-kontakt').value.trim();
  const izoh    = document.getElementById('km-izoh').value.trim();

  if (!nomi) { NHToast.error('Kontragent nomini kiriting!'); return; }

  const btn = document.getElementById('km-save-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="im-spinner"></span>';

  const fd = new FormData();
  fd.append('id', document.getElementById('km-id').value || '');
  fd.append('nomi', nomi);
  fd.append('telefon', telefon);
  fd.append('email', email);
  fd.append('shahar', shahar);
  fd.append('kontakt_ism', kontakt_ism);
  fd.append('kontakt_telefon', '');
  fd.append('izoh', izoh);

  const res = await IMAjax.post(window.im_BASE + 'sklad/ajax/ps-save.php', fd);
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Saqlash';

  if (res.status === 'ok') {
    NHToast.success(res.msg || "Kontragent qo'shildi!");
    NHModal.close('kontragent-modal');
    setTimeout(() => location.reload(), 700);
  } else {
    NHToast.error(res.msg || 'Xatolik!');
  }
});
</script>

<!-- ── ESKI QARZ KIRITISH MODAL ───────────────────────── -->
<div class="im-overlay" id="eski-qarz-modal">
  <div class="im-modal" style="width:400px;max-width:96%">
    <div class="im-modal-header" style="background:var(--warning);color:#000">
      <i class="bi bi-wallet2"></i>
      <span class="im-modal-title fw-bold">Alohida qarz kiritish</span>
      <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
    </div>
    <form id="form-eski-qarz">
      <div class="im-modal-body row g-3">
        <div class="col-12">
          <label class="im-label">Kontragent (Postavshik) <span class="text-danger">*</span></label>
          <select name="postavshik_id" class="im-select" required>
            <option value="">-- Tanlang --</option>
            <?php foreach ($postavshik_list as $ps): ?>
            <option value="<?= $ps['id'] ?>"><?= im_f($ps['nomi']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="im-label">Qarz summasi (so'm) <span class="text-danger">*</span></label>
          <input type="number" class="im-input num" name="summa" min="1" required placeholder="0">
        </div>
        <div class="col-6">
          <label class="im-label">Qarz sanasi</label>
          <input type="date" class="im-input" name="sana" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-6">
          <label class="im-label">To'lash muddati (ixtiyoriy)</label>
          <input type="date" class="im-input" name="muddat">
        </div>
        <div class="col-12">
          <label class="im-label">Izoh</label>
          <input type="text" class="im-input" name="izoh" placeholder="Masalan: Tizimgacha bo'lgan qarz">
        </div>
      </div>
      <div class="im-modal-footer">
        <button type="button" class="im-btn im-btn-outline" data-modal-close>Bekor</button>
        <button type="submit" class="im-btn im-btn-warning" id="btn-save-eq">
          <i class="bi bi-check2"></i> Saqlash
        </button>
      </div>
    </form>
  </div>
</div>
<script>
window.openEskiQarzModal = function() {
  document.getElementById('form-eski-qarz').reset();
  NHModal.open('eski-qarz-modal');
};
document.getElementById('form-eski-qarz').addEventListener('submit', async function(e){
  e.preventDefault();
  const btn = document.getElementById('btn-save-eq');
  btn.disabled = true;
  btn.innerHTML = '<span class="im-spinner"></span>';
  
  const fd = new FormData(this);
  const res = await IMAjax.post(window.im_BASE + 'admin/ajax/qarz-qoshish.php', fd);
  
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check2"></i> Saqlash';
  
  if (res.status === 'ok') {
    NHToast.success(res.msg);
    NHModal.close('eski-qarz-modal');
    setTimeout(() => location.reload(), 600);
  } else {
    NHToast.error(res.msg);
  }
});
</script>
</body></html>
