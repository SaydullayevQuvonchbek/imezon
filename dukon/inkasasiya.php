<?php
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'kassir']);
$db = new Cyber();

$filial_id = $im_filial_id ?: 1;

$kassa = $db->row("SELECT * FROM im_kassa WHERE filial_id=$filial_id");
$naqd_balans  = (float)($kassa['naqd_balans'] ?? 0);
$karta_balans = (float)($kassa['karta_balans'] ?? 0);
$bank_balans  = (float)($kassa['bank_balans'] ?? 0);
$usd_balans   = (float)($kassa['usd_balans'] ?? 0);



$inkasasiyalar = $db->rows("SELECT i.*, x.ism AS xodim_ism FROM im_inkasasiya i 
                            LEFT JOIN im_xodimlar x ON i.xodim_id = x.id 
                            WHERE i.filial_id = '$filial_id' 
                            ORDER BY i.sana DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inkasasiya (Pul topshirish) | IMezon Do'kon</title>
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
        <div class="im-page-title"><i class="bi bi-safe-fill me-1"></i> Inkasasiya (Adminga pul berish)</div>
        <div class="im-topbar-actions">
          <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
        </div>
      </header>
      <main class="im-content">

        <div class="row g-4 mb-4">
          <div class="col-md-3 col-6">
            <div class="im-card p-3" style="border-left:4px solid var(--success)">
              <div class="text-muted mb-1 fs-sm">💵 Naqd pul</div>
              <h5 class="fw-bold m-0 num" style="color:var(--success)"><?= im_money($naqd_balans) ?></h5>
            </div>
          </div>
          <div class="col-md-3 col-6">
            <div class="im-card p-3" style="border-left:4px solid var(--primary)">
              <div class="text-muted mb-1 fs-sm"><?= im_tt_label('karta', true) ?></div>
              <h5 class="fw-bold m-0 num" style="color:var(--primary)"><?= im_money($karta_balans) ?></h5>
            </div>
          </div>
          <div class="col-md-3 col-6">
            <div class="im-card p-3" style="border-left:4px solid var(--info)">
              <div class="text-muted mb-1 fs-sm"><?= im_tt_label('bank', true) ?></div>
              <h5 class="fw-bold m-0 num" style="color:var(--info)"><?= im_money($bank_balans) ?></h5>
            </div>
          </div>
          <div class="col-md-3 col-6">
            <div class="im-card p-3" style="border-left:4px solid #b8860b">
              <div class="text-muted mb-1 fs-sm">🪙 USD</div>
              <h5 class="fw-bold m-0 num" style="color:#b8860b">$<?= number_format($usd_balans, 2) ?></h5>
            </div>
          </div>
        </div>

        <div class="im-card mb-4" style="background:var(--card-bg)">
          <button class="im-btn im-btn-primary w-100 py-3" id="btn-topshirish" style="font-size:18px">
            <i class="bi bi-box-arrow-up"></i> Adminga pul (Inkasasiya) topshirish
          </button>
        </div>

        <div class="im-card im-slide-in">
          <div class="im-card-header">
            <i class="bi bi-list-ul"></i>
            <span class="im-card-title">Topshirilgan pullar tarixi</span>
          </div>
          <?php if ($inkasasiyalar): ?>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead>
                  <tr>
                    <th>Sana</th>
                    <th>Xodim</th>
                    <th>To'lov</th>
                    <th class="text-right">Summa</th>
                    <th>Izoh</th>
                    <th>Holat</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($inkasasiyalar as $i): ?>
                    <tr>
                      <td><?= im_datetime($i['sana']) ?></td>
                      <td><?= im_f($i['xodim_ism']) ?></td>
                      <td><span class="im-badge im-badge-muted"><?= im_f($i['tolov_turi']) ?></span></td>
                      <td class="text-right fw-bold num" style="color:var(--danger)">
                        <?= $i['tolov_turi'] === 'usd' ? '$'.number_format($i['summa'], 2) : im_money($i['summa'])." so'm" ?>
                      </td>
                      <td><?= im_f($i['izoh'] ?: '—') ?></td>
                      <td>
                        <?php if ($i['holat'] === 'kutilmoqda'): ?>
                          <span class="im-badge im-badge-warning text-dark"><i class="bi bi-hourglass-split"></i>
                            Kutilmoqda</span>
                        <?php elseif ($i['holat'] === 'qabul_qilindi'): ?>
                          <span class="im-badge im-badge-success"><i class="bi bi-check-circle"></i> Tasdiqlandi</span>
                        <?php else: ?>
                          <span class="im-badge im-badge-danger"><i class="bi bi-x-circle"></i> Bekor qilindi</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="im-empty">
              <i class="bi bi-safe"></i>
              <h4>Tarix bo'sh</h4>
              <p class="text-muted">Hali adminga pul topshirilmagan</p>
            </div>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>

  <!-- Modal -->
  <div class="im-overlay" id="topshirish-modal">
    <div class="im-modal">
      <div class="im-modal-header">
        <i class="bi bi-box-arrow-up" style="color:var(--accent-dark);font-size:20px"></i>
        <span class="im-modal-title">Adminga pul topshirish</span>
        <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="im-modal-body">
        <div class="row g-3 mb-3">
          <div class="col-12">
            <label class="im-label">Topshirilayotgan Summa *</label>
            <input class="im-input num fw-bold" type="number" id="i-summa" step="0.01" min="0" placeholder="0">
          </div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-12">
            <label class="im-label">To'lov shakli</label>
            <select class="im-select" id="i-tolov">
              <option value="naqd"><?= im_tt_label('naqd') ?></option>
              <option value="karta"><?= im_tt_label('karta') ?></option>
              <option value="bank"><?= im_tt_label('bank') ?></option>
              <option value="usd">🪙 USD (Dollar)</option>
            </select>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-12">
            <label class="im-label">Izoh (ixtiyoriy)</label>
            <input class="im-input" type="text" id="i-izoh" placeholder="Sabab yoki qo'shimcha ma'lumot">
          </div>
        </div>
      </div>
      <div class="im-modal-footer">
        <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
        <button class="im-btn im-btn-primary" id="btn-save"><i class="bi bi-check2-circle"></i> Topshirish</button>
      </div>
    </div>
  </div>
  <div id="im-toast-container"></div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>window.im_BASE = "<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
  <script src="<?= im_BASE ?>assets/js/main.js"></script>
  <script>
    document.getElementById('btn-topshirish').addEventListener('click', () => {
      document.getElementById('i-summa').value = '';
      document.getElementById('i-izoh').value = '';
      NHModal.open('topshirish-modal');
      setTimeout(() => document.getElementById('i-summa').focus(), 200);
    });

    document.getElementById('btn-save').addEventListener('click', async () => {
      const summa = parseFloat(document.getElementById('i-summa').value) || 0;
      const tolov = document.getElementById('i-tolov').value;
      const izoh = document.getElementById('i-izoh').value.trim();

      if (summa <= 0) { NHToast.error("Summa kiriting!"); return; }

      const valyuta = tolov === 'usd' ? '$' : " so'm";
      const ok = await NHConfirm.ask(summa + valyuta + " qabulga yuborilsinmi?");
      if (!ok) return;

      const res = await IMAjax.post(window.im_BASE + 'dukon/ajax/inkasasiya-save.php', { summa, tolov_turi: tolov, izoh });
      if (res.status === 'ok') {
        NHToast.success(res.msg);
        setTimeout(() => location.reload(), 800);
      } else {
        NHToast.error(res.msg);
      }
    });
  </script>
</body>

</html>