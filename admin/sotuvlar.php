<?php
// ============================================================
//  IMezon — Admin: Barcha Sotuvlar Jurnali
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'bosh_kassir']);
$db = new Cyber();
$sale_cost = im_fifo_sale_unit_cost_sql('si');
$return_cost = im_fifo_sale_unit_cost_sql('rsi');

// ── Filtrlar ─────────────────────────────────────────────
$dan = $_GET['dan'] ?? date('Y-m-01');
$gacha = $_GET['gacha'] ?? date('Y-m-d');
$dan = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dan) ? $dan : date('Y-m-01');
$gacha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $gacha) ? $gacha : date('Y-m-d');
$ds = mysqli_real_escape_string($link, $dan);
$gs = mysqli_real_escape_string($link, $gacha);

$filial_f = (int) ($_GET['filial'] ?? 0);
$kassir_f = (int) ($_GET['kassir'] ?? 0);
$tolov_f = mysqli_real_escape_string($link, $_GET['tolov'] ?? '');

// WHERE qurish
$where = "DATE(s.sana) BETWEEN '$ds' AND '$gs'";
if ($filial_f)
  $where .= " AND s.filial_id=$filial_f";
if ($kassir_f)
  $where .= " AND s.kassir_id=$kassir_f";
if ($tolov_f === 'naqd')
  $where .= " AND s.naqd_summa > 0 AND s.karta_summa=0 AND s.nasiya_summa=0";
if ($tolov_f === 'karta')
  $where .= " AND s.karta_summa > 0";
if ($tolov_f === 'nasiya')
  $where .= " AND s.nasiya_summa > 0";
if ($tolov_f === 'usd')
  $where .= " AND s.usd_summa > 0";
if ($tolov_f === 'aralash')
  $where .= " AND (s.naqd_summa > 0 AND s.karta_summa > 0)";
$calc_where = "$where AND s.holat IN ('aktiv','qaytarilgan')";

// Paginatsiya
$page = max(1, (int) ($_GET['p'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$total = (int) $db->val("SELECT COUNT(*) FROM im_sotuvlar s WHERE $where");
$pages = max(1, ceil($total / $per_page));

// Statistika (filtrlangan)
$stats = $db->row(
  "SELECT COALESCE(SUM(s.tolov_summa),0)  AS jami_summa,
            COALESCE(SUM(s.naqd_summa),0)   AS naqd,
            COALESCE(SUM(s.karta_summa),0)  AS karta,
            COALESCE(SUM(s.nasiya_summa),0) AS nasiya,
            COALESCE(SUM(s.chegirma_summa),0) AS chegirma,
            COALESCE(SUM(s.xizmat_summa),0)  AS xizmat,
            COUNT(*)                         AS soni
     FROM im_sotuvlar s WHERE $calc_where"
);
// Haqiqiy marja: item marja - invoice darajasidagi chegirma
$marja_stat = $db->row(
  "SELECT
        COALESCE(SUM((si.chegirma_narxi - ($sale_cost)) * si.soni), 0) AS items_marja,
        COALESCE(SUM(($sale_cost) * si.soni), 0) AS items_tannarx,
        COALESCE(SUM((si.sotish_narxi - si.chegirma_narxi) * si.soni), 0) AS items_chegirma,
        COALESCE(SUM(si.chegirma_narxi * si.soni), 0) AS items_sotuv_jami
     FROM im_sotuv_items si
     JOIN im_sotuvlar s ON s.id = si.sotuv_id
     WHERE $calc_where"
);
$items_chegirma = (float) ($marja_stat['items_chegirma'] ?? 0);
$total_chegirma = (float) ($stats['chegirma'] ?? 0);
$invoice_chegirma = max(0, $total_chegirma - $items_chegirma);
$xizmat_jami = (float)($stats['xizmat'] ?? 0);
$return_stat = $db->row(
  "SELECT COALESCE(SUM(v.qaytarish_summa),0) revenue,
          COALESCE(SUM(v.soni*CASE WHEN rsi.id IS NOT NULL THEN ($return_cost) ELSE COALESCE(v.tannarx,0) END),0) cost
   FROM im_vozvratlar v
   JOIN im_sotuvlar s ON s.id=v.sotuv_id
   LEFT JOIN im_sotuv_items rsi ON rsi.id=v.sotuv_item_id
   WHERE $calc_where"
) ?: ['revenue'=>0,'cost'=>0];
$stats['jami_summa'] = (float)$stats['jami_summa'] - (float)$return_stat['revenue'];
$sof_tannarx = (float)($marja_stat['items_tannarx'] ?? 0) - (float)$return_stat['cost'];
$haqiqiy_marja = (float)$stats['jami_summa'] - $sof_tannarx;
$items_sotuv_jami = (float) ($marja_stat['items_sotuv_jami'] ?? 0);
$stats['jami_marja'] = $haqiqiy_marja;
$marja_foiz = ((float)$stats['jami_summa'] > 0)
  ? round($haqiqiy_marja / (float)$stats['jami_summa'] * 100, 1)
  : 0;

// Sotuvlar ro'yxati
$sotuvlar = $db->rows(
  "SELECT s.id, s.chek_nomer, s.sana,
            s.tolov_summa, s.naqd_summa, s.karta_summa,
            s.bank_summa, s.usd_summa, s.nasiya_summa,
            s.chegirma_summa, s.xizmat_foiz, s.xizmat_summa, s.holat,
            s.manba, s.stol_id,
            m.ism AS mijoz_ism,
            x.ism AS kassir_ism,
            (SELECT ism FROM im_xodimlar o WHERE o.id = s.sotuvchi_id) AS ofitsant_ism,
            f.nomi AS filial_nomi,
            (SELECT COUNT(*) FROM im_sotuv_items si WHERE si.sotuv_id=s.id) AS item_soni,
            COALESCE((SELECT SUM((si.chegirma_narxi - ($sale_cost)) * si.soni)
                      FROM im_sotuv_items si WHERE si.sotuv_id=s.id), 0) AS items_marja,
            COALESCE((SELECT SUM((si.sotish_narxi - si.chegirma_narxi) * si.soni)
                      FROM im_sotuv_items si WHERE si.sotuv_id=s.id), 0) AS items_chegirma,
            COALESCE((SELECT SUM(si.chegirma_narxi * si.soni)
                      FROM im_sotuv_items si WHERE si.sotuv_id=s.id), 0) AS items_sotuv_jami,
            COALESCE((SELECT SUM(v.qaytarish_summa) FROM im_vozvratlar v WHERE v.sotuv_id=s.id),0) AS return_revenue,
            COALESCE((SELECT SUM(v.soni*CASE WHEN rsi.id IS NOT NULL THEN ($return_cost) ELSE COALESCE(v.tannarx,0) END)
                      FROM im_vozvratlar v LEFT JOIN im_sotuv_items rsi ON rsi.id=v.sotuv_item_id
                      WHERE v.sotuv_id=s.id),0) AS return_cost
     FROM im_sotuvlar s
     LEFT JOIN im_mijozlar m ON m.id = s.mijoz_id
     LEFT JOIN im_xodimlar x ON x.id = s.kassir_id
     LEFT JOIN im_filiallar f ON f.id = s.filial_id
     WHERE $where
     ORDER BY s.sana DESC
     LIMIT $per_page OFFSET $offset"
);

// Filtr uchun
$filiallar = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY tartib");
$kassirlar = $db->rows("SELECT id, ism FROM im_xodimlar WHERE rol IN('kassir','admin') AND status=1 ORDER BY ism");

function pay_label($s)
{
  $parts = [];
  if ((float) $s['naqd_summa'] > 0)
    $parts[] = '💵';
  if ((float) $s['karta_summa'] > 0)
    $parts[] = '💳';
  if ((float) $s['bank_summa'] > 0)
    $parts[] = '🏦';
  if ((float) $s['usd_summa'] > 0)
    $parts[] = '🇺🇸';
  if ((float) $s['nasiya_summa'] > 0)
    $parts[] = '📋';
  return implode(' ', $parts) ?: '—';
}
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Sotuvlar | IMezon Admin</title>
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
        <div class="im-page-title"><i class="bi bi-bag-check-fill me-1"></i> Sotuvlar jurnali</div>
        <div class="im-topbar-actions">
          <a href="<?= im_BASE ?>admin/eksport.php" class="im-btn im-btn-outline im-btn-sm">
            <i class="bi bi-download"></i> Export
          </a>
          <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
        </div>
      </header>
      <main class="im-content">

        <!-- ── Filtr ──────────────────────────────────────────── -->
        <div class="im-card mb-3">
          <div class="im-card-body p-3">
            <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
              <div>
                <label class="im-label mb-1" style="font-size:11px">Dan</label>
                <input type="date" name="dan" class="im-input im-input-sm" value="<?= $dan ?>" style="max-width:140px">
              </div>
              <div>
                <label class="im-label mb-1" style="font-size:11px">Gacha</label>
                <input type="date" name="gacha" class="im-input im-input-sm" value="<?= $gacha ?>"
                  style="max-width:140px">
              </div>
              <div>
                <label class="im-label mb-1" style="font-size:11px">Filial</label>
                <select name="filial" class="im-select im-input-sm" style="min-width:130px">
                  <option value="">Barchasi</option>
                  <?php foreach ($filiallar as $fl): ?>
                    <option value="<?= $fl['id'] ?>" <?= $filial_f == $fl['id'] ? 'selected' : '' ?>><?= im_f($fl['nomi']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="im-label mb-1" style="font-size:11px">Kassir</label>
                <select name="kassir" class="im-select im-input-sm" style="min-width:130px">
                  <option value="">Barchasi</option>
                  <?php foreach ($kassirlar as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $kassir_f == $k['id'] ? 'selected' : '' ?>><?= im_f($k['ism']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="im-label mb-1" style="font-size:11px">To'lov turi</label>
                <select name="tolov" class="im-select im-input-sm" style="min-width:120px">
                  <option value="">Barchasi</option>
                  <option value="naqd" <?= $tolov_f === 'naqd' ? 'selected' : '' ?>>💵 Naqd</option>
                  <option value="karta" <?= $tolov_f === 'karta' ? 'selected' : '' ?>><?= im_tt_label('karta') ?></option>
                  <option value="nasiya" <?= $tolov_f === 'nasiya' ? 'selected' : '' ?>>📋 Nasiya</option>
                  <option value="usd" <?= $tolov_f === 'usd' ? 'selected' : '' ?>>🇺🇸 USD</option>
                  <option value="aralash" <?= $tolov_f === 'aralash' ? 'selected' : '' ?>>🔀 Aralash</option>
                </select>
              </div>
              <button type="submit" class="im-btn im-btn-dark im-btn-sm"><i class="bi bi-filter"></i> Ko'rsat</button>
              <a href="<?= im_BASE ?>admin/sotuvlar.php" class="im-btn im-btn-ghost im-btn-sm">Tozalash</a>
            </form>
          </div>
        </div>

        <!-- ── Stat kartalar ─────────────────────────────────── -->
        <div class="row g-3 mb-3">
          <div class="col-6 col-md-3">
            <div class="im-stat-card" style="border-left:4px solid var(--accent-dark)">
              <div class="im-stat-icon" style="background:rgba(226,185,111,.15);color:var(--accent-dark)"><i
                  class="bi bi-bag-fill"></i></div>
              <div>
                <div class="im-stat-label">Jami sotuv</div>
                <div class="im-stat-value num"><?= im_money($stats['jami_summa']) ?> so'm</div>
                <div class="text-muted fs-xs"><?= (int) $stats['soni'] ?> ta chek</div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="im-stat-card" style="border-left:4px solid var(--success)">
              <div class="im-stat-icon" style="background:rgba(40,167,69,.12);color:var(--success)"><i
                  class="bi bi-cash"></i></div>
              <div>
                <div class="im-stat-label">💵 Naqd</div>
                <div class="im-stat-value num"><?= im_money($stats['naqd']) ?> so'm</div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="im-stat-card" style="border-left:4px solid var(--primary)">
              <div class="im-stat-icon" style="background:rgba(13,110,253,.12);color:var(--primary)"><i
                  class="bi bi-credit-card"></i></div>
              <div>
                <div class="im-stat-label"><?= im_tt_label('karta', true) ?></div>
                <div class="im-stat-value num"><?= im_money($stats['karta']) ?> so'm</div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="im-stat-card" style="border-left:4px solid var(--warning)">
              <div class="im-stat-icon" style="background:rgba(255,193,7,.12);color:var(--warning)"><i
                  class="bi bi-clock-history"></i></div>
              <div>
                <div class="im-stat-label">📋 Nasiya</div>
                <div class="im-stat-value num"><?= im_money($stats['nasiya']) ?> so'm</div>
                <?php if ((float) $stats['chegirma'] > 0): ?>
                  <div class="text-muted fs-xs">Chegirma: -<?= im_money($stats['chegirma']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php if ($xizmat_jami > 0): ?>
            <div class="col-6 col-md-3">
              <div class="im-stat-card" style="border-left:4px solid #0d6efd">
                <div class="im-stat-icon" style="background:rgba(13,110,253,.12);color:#0d6efd"><i
                    class="bi bi-people-fill"></i></div>
                <div>
                  <div class="im-stat-label">Xizmat haqi (otsluga)</div>
                  <div class="im-stat-value num"><?= im_money($xizmat_jami) ?> so'm</div>
                  <div class="text-muted fs-xs">Marjaga to'liq qo'shilgan</div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <!-- ── Marja statistikasi ────────────────────────────────── -->
        <div class="im-card mb-3" style="border-left:4px solid #6f42c1">
          <div class="im-card-body p-3 d-flex align-items-center gap-4 flex-wrap">
            <div class="d-flex align-items-center gap-2">
              <div
                style="width:40px;height:40px;border-radius:10px;background:rgba(111,66,193,.12);color:#6f42c1;display:flex;align-items:center;justify-content:center;font-size:20px">
                <i class="bi bi-graph-up-arrow"></i>
              </div>
              <div>
                <div
                  style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                  Jami marja</div>
                <div class="fw-bold num" style="font-size:18px;color:#6f42c1"><?= im_money($stats['jami_marja']) ?> so'm
                </div>
              </div>
            </div>
            <div style="width:1px;height:40px;background:var(--border-color)"></div>
            <div>
              <div
                style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                O'rtacha marja %</div>
              <div class="fw-bold num"
                style="font-size:18px;color:<?= $marja_foiz >= 20 ? '#28a745' : ($marja_foiz >= 10 ? '#fd7e14' : '#dc3545') ?>">
                <?= $marja_foiz ?>%
              </div>
            </div>
            <div style="width:1px;height:40px;background:var(--border-color)"></div>
            <div>
              <div
                style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                1 chek o'rtacha marja</div>
              <div class="fw-bold num" style="font-size:18px;color:var(--text-main)">
                <?= $stats['soni'] > 0 ? im_money($stats['jami_marja'] / $stats['soni']) : 0 ?> so'm
              </div>
            </div>
          </div>
        </div>

        <!-- ── Jadval ─────────────────────────────────────────── -->
        <div class="im-card im-slide-in">
          <div class="im-card-header">
            <i class="bi bi-table" style="color:var(--accent-dark)"></i>
            <span class="im-card-title">Sotuvlar</span>
            <span class="im-badge im-badge-muted ms-2"><?= $total ?> ta</span>
            <span class="ms-auto text-muted fs-xs"><?= $dan ?> — <?= $gacha ?></span>
          </div>
          <?php if ($sotuvlar): ?>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Chek</th>
                    <th>Sana/Vaqt</th>
                    <th>Filial</th>
                    <th>Kassir</th>
                    <th>Manba / Ofitsant / Mijoz</th>
                    <th class="text-center">Tovar</th>
                    <th class="text-right">Summa</th>
                    <th class="text-right" style="color:#6f42c1">Marja</th>
                    <th class="text-center">To'lov</th>
                    <th class="text-center">Holat</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($sotuvlar as $i => $s): ?>
                    <tr class="sotuv-row" data-id="<?= $s['id'] ?>" style="cursor:pointer" title="Batafsil ko'rish">
                      <td class="text-muted fs-xs"><?= $offset + $i + 1 ?></td>
                      <td>
                        <span class="fw-semibold fs-xs" style="color:var(--accent-dark)">
                          <?= im_f($s['chek_nomer']) ?>
                        </span>
                      </td>
                      <td class="fs-xs text-muted"><?= date('d.m.Y H:i', strtotime($s['sana'])) ?></td>
                      <td class="fs-xs"><?= im_f($s['filial_nomi'] ?? '—') ?></td>
                      <td class="fs-xs"><?= im_f($s['kassir_ism'] ?? '—') ?></td>
                      <!-- Manba (qayerdan) + mijoz (kim) — ikki xil tushuncha -->
                      <td class="fs-xs">
                        <?php if ($s['manba']): ?>
                          <div class="fw-semibold">
                            <i class="bi bi-<?= $s['stol_id'] ? 'geo-alt-fill' : 'bag-fill' ?>"
                               style="color:var(--accent-dark)"></i>
                            <?= im_f($s['manba']) ?>
                          </div>
                        <?php else: ?>
                          <div class="text-muted">—</div>
                        <?php endif; ?>
                        <?php if ($s['ofitsant_ism']): ?>
                          <div class="text-muted fs-xs">👤 <?= im_f($s['ofitsant_ism']) ?></div>
                        <?php endif; ?>
                        <?php if ($s['mijoz_ism']): ?>
                          <div style="color:#0d6efd">🎫 <?= im_f($s['mijoz_ism']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <span class="im-badge im-badge-muted"><?= (int) $s['item_soni'] ?> ta</span>
                      </td>
                      <td class="text-right fw-bold num" style="color:var(--accent-dark)">
                        <?= im_money($s['tolov_summa']) ?> so'm
                        <?php if ((float) $s['chegirma_summa'] > 0): ?>
                          <div class="text-muted fs-xs" title="Chegirma">-<?= im_money($s['chegirma_summa']) ?></div>
                        <?php endif; ?>
                        <?php if ((float) $s['xizmat_summa'] > 0): ?>
                          <div class="fs-xs" style="color:#0d6efd"
                               title="Xizmat haqi (otsluga)">
                            +<?= im_money($s['xizmat_summa']) ?> (<?= rtrim(rtrim(number_format((float)$s['xizmat_foiz'], 2, '.', ''), '0'), '.') ?>%)
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="text-right">
                        <?php
                        // Haqiqiy marja = item marja - invoice-darajali chegirma + xizmat haqi
                        // (xizmat haqining tannarxi yo'q — sof daromad)
                        $row_invoice_ch = max(0, (float) $s['chegirma_summa'] - (float) $s['items_chegirma']);
                        $marja = $s['holat'] === 'bekor' ? 0 :
                          (float)$s['items_marja'] - $row_invoice_ch + (float)$s['xizmat_summa']
                          - (float)$s['return_revenue'] + (float)$s['return_cost'];
                        // % = haqiqiy marja / item-darajali sotuv narxi yig'indisi
                        $denom = max(0, (float)$s['tolov_summa'] - (float)$s['return_revenue']);
                        $marja_p = $denom > 0 ? round($marja / $denom * 100, 1) : 0;
                        $marja_color = $marja_p >= 20 ? '#28a745' : ($marja_p >= 10 ? '#fd7e14' : ($marja >= 0 ? '#dc3545' : '#6c757d'));
                        ?>
                        <span class="fw-semibold num" style="color:<?= $marja_color ?>">
                          <?= im_money($marja) ?>
                        </span>
                        <div style="font-size:10px;color:<?= $marja_color ?>"><?= $marja_p ?>%</div>
                      </td>
                      <td class="text-center fs-sm"><?= pay_label($s) ?></td>
                      <td class="text-center">
                        <?php if ($s['holat'] === 'vozvrat'): ?>
                          <span class="im-badge im-badge-danger">Vozvrat</span>
                        <?php else: ?>
                          <span class="im-badge im-badge-success">Aktiv</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Paginatsiya -->
            <?php if ($pages > 1): ?>
              <div class="d-flex justify-content-center gap-1 p-3">
                <?php
                $qs = http_build_query(array_merge($_GET, ['p' => 1]));
                if ($page > 1)
                  echo "<a href='?$qs' class='im-btn im-btn-outline im-btn-sm'>«</a>";
                for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++):
                  $qs = http_build_query(array_merge($_GET, ['p' => $i]));
                  $active = $i === $page ? 'im-btn-primary' : 'im-btn-outline';
                  echo "<a href='?$qs' class='im-btn im-btn-sm $active'>$i</a>";
                endfor;
                $qs = http_build_query(array_merge($_GET, ['p' => $pages]));
                if ($page < $pages)
                  echo "<a href='?$qs' class='im-btn im-btn-outline im-btn-sm'>»</a>";
                ?>
              </div>
            <?php endif; ?>

          <?php else: ?>
            <div class="im-empty">
              <i class="bi bi-bag-x"></i>
              <h4>Sotuv topilmadi</h4>
              <p class="text-muted">Tanlangan filtr bo'yicha sotuv yo'q</p>
            </div>
          <?php endif; ?>
        </div>

      </main>
    </div>
  </div>
  <div id="im-toast-container"></div>

  <!-- ── Sotuv Detail Modal ── -->
  <div id="sotuv-modal" style="
  display:none;position:fixed;inset:0;z-index:9999;
  background:rgba(0,0,0,.4);backdrop-filter:blur(4px);
  align-items:center;justify-content:center;padding:16px
" onclick="closeSotuvModal(event)">
    <div style="
    background:#ffffff;border-radius:16px;
    max-width:860px;width:100%;max-height:90vh;overflow:hidden;
    display:flex;flex-direction:column;
    box-shadow:0 8px 40px rgba(0,0,0,.15);
    border:1px solid #e0e3e8
  " onclick="event.stopPropagation()">
      <!-- Header -->
      <div style="
      padding:16px 24px;border-bottom:1px solid #e0e3e8;
      display:flex;align-items:center;gap:12px;background:#fff
    ">
        <div
          style="width:38px;height:38px;border-radius:10px;background:rgba(111,66,193,.1);color:#6f42c1;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
          <i class="bi bi-receipt"></i>
        </div>
        <div style="flex:1">
          <div id="modal-chek" class="fw-bold" style="font-size:16px;color:#c9a055"></div>
          <div id="modal-meta" style="font-size:12px;color:#6c757d"></div>
        </div>
        <button onclick="document.getElementById('sotuv-modal').style.display='none'" style="
        width:30px;height:30px;border-radius:6px;display:flex;align-items:center;justify-content:center;
        background:#f0f2f5;border:1px solid #e0e3e8;
        cursor:pointer;color:#6c757d;font-size:15px;transition:all .2s
      " onmouseover="this.style.background='#fdf0ef';this.style.color='#e74c3c';"
           onmouseout="this.style.background='#f0f2f5';this.style.color='#6c757d';">✕</button>
      </div>
      <!-- Jami panel -->
      <div id="modal-jami"
        style="padding:14px 24px;border-bottom:1px solid #e0e3e8;display:flex;gap:20px;flex-wrap:wrap;background:#f8f9fa">
      </div>
      <!-- Mahsulotlar -->
      <div style="overflow-y:auto;flex:1;padding:0 24px 20px;background:#fff">
        <table style="width:100%;border-collapse:collapse;margin-top:12px" id="modal-items">
          <thead>
            <tr
              style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;border-bottom:2px solid #e0e3e8;background:#f8f9fa">
              <th style="text-align:left;padding:9px 8px">#</th>
              <th style="text-align:left;padding:9px 8px">Mahsulot</th>
              <th style="text-align:center;padding:9px 8px">Soni</th>
              <th style="text-align:right;padding:9px 8px">Kelish narxi</th>
              <th style="text-align:right;padding:9px 8px">Sotuv narxi</th>
              <th style="text-align:right;padding:9px 8px">Chegirma</th>
              <th style="text-align:right;padding:9px 8px;color:#6f42c1">Marja</th>
            </tr>
          </thead>
          <tbody id="modal-tbody"></tbody>
          <tfoot id="modal-tfoot"></tfoot>
        </table>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>window.im_BASE = "<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
  <script src="<?= im_BASE ?>assets/js/main.js?v=<?= rawurlencode(im_VERSION) ?>"></script>
  <script>
    function fmt(n) {
      return Number(n).toLocaleString('uz-UZ', { maximumFractionDigits: 0 });
    }
    function marjaColor(p) {
      return p >= 20 ? '#28a745' : (p >= 10 ? '#fd7e14' : (p > 0 ? '#dc3545' : '#6c757d'));
    }

    document.querySelectorAll('.sotuv-row').forEach(function (tr) {
      tr.addEventListener('click', function () {
        openSotuvModal(this.dataset.id);
      });
      tr.addEventListener('mouseenter', function () { this.style.background = 'var(--hover-bg, rgba(111,66,193,.08))'; });
      tr.addEventListener('mouseleave', function () { this.style.background = ''; });
    });

    function openSotuvModal(id) {
      var modal = document.getElementById('sotuv-modal');
      modal.style.display = 'flex';
      document.getElementById('modal-chek').textContent = 'Yuklanmoqda...';
      document.getElementById('modal-meta').textContent = '';
      document.getElementById('modal-jami').innerHTML = '<span style="color:#6c757d;font-size:13px">⏳ Yuklanmoqda...</span>';
      document.getElementById('modal-tbody').innerHTML = '';
      document.getElementById('modal-tfoot').innerHTML = '';

      fetch(im_BASE + 'admin/ajax/sotuv-detail.php?id=' + id)
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.error) { NHToast.error(d.error, 6000); return; }
          var s = d.sotuv;
          var items = d.items;

          // Header
          document.getElementById('modal-chek').textContent = s.chek_nomer;
          var meta = [];
          meta.push(new Date(s.sana).toLocaleString('uz-UZ', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }));
          if (s.kassir_ism) meta.push('Kassir: ' + s.kassir_ism);
          if (s.filial_nomi) meta.push(s.filial_nomi);
          if (s.mijoz_ism) meta.push('Mijoz: ' + s.mijoz_ism);
          document.getElementById('modal-meta').textContent = meta.join(' · ');

          // Server chek chegirmasi va xizmat haqini qatorlarga taqsimlab berdi.
          // Shu sabab qator marjalari yig'indisi yakuniy tushum - tannarxga teng.
          var jami_kelish = 0, jami_sotuv = 0, jami_item_ch = 0, jami_item_marja = 0;
          var itemData = [];
          items.forEach(function (item) {
            var sotuvJami = parseFloat(item.hisob_tushum) || 0;
            var qaytganSoni = parseFloat(item.qaytarilgan_soni) || 0;
            var netSoni = Math.max(0, parseFloat(item.soni) - qaytganSoni);
            var kelishJami = parseFloat(item.tannarx) * netSoni;
            var chSum = (parseFloat(item.sotish_narxi) - parseFloat(item.chegirma_narxi)) * parseInt(item.soni);
            var msum = parseFloat(item.marja_summa) || 0;
            jami_kelish     += kelishJami;
            jami_sotuv      += sotuvJami;
            jami_item_ch    += chSum;
            jami_item_marja += msum;
            itemData.push({ item: item, sotuvJami: sotuvJami, kelishJami: kelishJami, chSum: chSum, msum: msum, netSoni: netSoni, qaytganSoni: qaytganSoni });
          });

          var total_chegirma_db = parseFloat(s.chegirma_summa) || 0;

          // Qatorlar allaqachon yakuniy chek tushumiga mos taqsimlangan.
          var rows = '';
          itemData.forEach(function (d, i) {
            var haqiqiy_msum  = d.msum;
            var haqiqiy_mfoiz = d.sotuvJami > 0 ? (haqiqiy_msum / d.sotuvJami * 100).toFixed(1) : 0;
            var mc2 = marjaColor(parseFloat(haqiqiy_mfoiz));
            rows += '<tr style="border-bottom:1px solid #e8eaed;font-size:13px">' +
              '<td style="padding:9px 8px;color:#adb5bd">' + (i + 1) + '</td>' +
              '<td style="padding:9px 8px;font-weight:600;color:#1e1e2e">' + d.item.mahsulot_nomi + '<span style="font-size:10px;color:#adb5bd;margin-left:4px">' + d.item.birlik + '</span></td>' +
              '<td style="padding:9px 8px;text-align:center;color:#3d3d5c">' + d.netSoni + (d.qaytganSoni > 0 ? '<div style="font-size:10px;color:#dc3545">-' + d.qaytganSoni + ' vozvrat</div>' : '') + '</td>' +
              '<td style="padding:9px 8px;text-align:right;color:#6c757d">' + fmt(d.item.tannarx) + '</td>' +
              '<td style="padding:9px 8px;text-align:right;color:#1e1e2e;font-weight:600">' + fmt(d.item.chegirma_narxi) + '</td>' +
              '<td style="padding:9px 8px;text-align:right;color:#fd7e14">' + (d.chSum > 0 ? '-' + fmt(d.chSum) : '—') + '</td>' +
              '<td style="padding:9px 8px;text-align:right">' +
                '<span style="font-weight:700;color:' + mc2 + '">' + fmt(Math.round(haqiqiy_msum)) + '</span>' +
                '<div style="font-size:10px;color:' + mc2 + '">' + haqiqiy_mfoiz + '%</div>' +
              '</td>' +
              '</tr>';
          });
          document.getElementById('modal-tbody').innerHTML = rows;

          var haqiqiy_marja = jami_item_marja;
          var yakuniy_tushum = parseFloat(s.tolov_summa) || 0;
          var marja_foiz = yakuniy_tushum > 0 ? (haqiqiy_marja / yakuniy_tushum * 100).toFixed(1) : 0;

          // Jami panel — hamma raqamlar tayyor bo'lgandan keyin chizamiz
          var mc = marjaColor(parseFloat(marja_foiz));
          var jamiHtml =
            stat('💰 Sotuv summasi', fmt(parseFloat(s.tolov_summa)) + " so'm", 'var(--accent-dark)');
          if (total_chegirma_db > 0) {
            jamiHtml += stat('🏷 Jami chegirma', '-' + fmt(total_chegirma_db) + " so'm", '#fd7e14');
          }
          // To'lov turlari
          var tolFiltered = [];
          if (parseFloat(s.naqd_summa) > 0) tolFiltered.push('💵 Naqd: ' + fmt(s.naqd_summa));
          if (parseFloat(s.karta_summa) > 0) tolFiltered.push(IM_TT_LABELS.karta + ': ' + fmt(s.karta_summa));
          if (parseFloat(s.bank_summa) > 0) tolFiltered.push(IM_TT_LABELS.bank + ': ' + fmt(s.bank_summa));
          if (parseFloat(s.usd_summa) > 0) tolFiltered.push('🇺🇸 USD: ' + fmt(s.usd_summa) + ' $ (kurs: ' + fmt(s.usd_kurs) + ')');
          if (parseFloat(s.nasiya_summa) > 0) tolFiltered.push('📋 Nasiya: ' + fmt(s.nasiya_summa));
          if (tolFiltered.length) {
            jamiHtml += stat('💳 To\'lov', tolFiltered.join('<br>'), '#1e1e2e');
          }
          // Qaytimlar
          var usdQaytim = parseFloat(s.usd_qaytim_som || 0);
          var naqdQaytim = parseFloat(s.naqd_berildi || 0);
          if (usdQaytim > 0) {
            jamiHtml += stat('🔄 USD qaytim (so\'mda)', '-' + fmt(usdQaytim) + " so'm", '#dc3545');
          }
          if (naqdQaytim > 0) {
            jamiHtml += stat('🔄 Naqd qaytim', fmt(naqdQaytim) + " so'm", '#6c757d');
          }
          jamiHtml +=
            stat('📈 Haqiqiy marja', fmt(haqiqiy_marja) + " so'm", mc) +
            stat('📊 Marja %', marja_foiz + '%', mc);
          document.getElementById('modal-jami').innerHTML = jamiHtml;

          // Footer
          var fmc = marjaColor(parseFloat(marja_foiz));
          document.getElementById('modal-tfoot').innerHTML =
            '<tr style="border-top:2px solid #e0e3e8;font-weight:700;font-size:13px;background:#f8f9fa">' +
            '<td colspan="2" style="padding:10px 8px;color:#1e1e2e">JAMI</td>' +
            '<td></td>' +
            '<td style="text-align:right;padding:10px 8px;color:#6c757d">' + fmt(jami_kelish) + '</td>' +
            '<td style="text-align:right;padding:10px 8px;color:#1e1e2e;font-weight:700">' + fmt(jami_sotuv) + '</td>' +
            '<td style="text-align:right;padding:10px 8px;color:#fd7e14">' + (total_chegirma_db > 0 ? '-' + fmt(total_chegirma_db) : '—') + '</td>' +
            '<td style="text-align:right;padding:10px 8px;color:' + fmc + '">' + fmt(haqiqiy_marja) + '<div style="font-size:10px">' + marja_foiz + '%</div></td>' +
            '</tr>';
        });
    }

    function stat(label, val, color) {
      return '<div style="display:flex;flex-direction:column;gap:3px;padding:2px 0">' +
        '<span style="font-size:10px;color:#6c757d;text-transform:uppercase;letter-spacing:.5px;font-weight:600">' + label + '</span>' +
        '<span style="font-size:15px;font-weight:700;color:' + color + ';line-height:1.2">' + val + '</span>' +
        '</div>';
    }

    function closeSotuvModal(e) {
      if (e.target === document.getElementById('sotuv-modal')) {
        e.target.style.display = 'none';
      }
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') document.getElementById('sotuv-modal').style.display = 'none';
    });
  </script>
</body>

</html>
