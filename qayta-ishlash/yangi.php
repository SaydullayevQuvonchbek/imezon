<?php
// ============================================================
//  IMezon — Yangi Ishlab chiqarish / Maydalash
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin', 'kassir', 'sklad', 'oshpaz']);

$db = new Cyber();

$tur_get = in_array($_GET['tur'] ?? '', ['ishlab_chiqarish', 'maydalash'])
    ? $_GET['tur'] : 'ishlab_chiqarish';
if ($im_rol === 'oshpaz') $tur_get = 'maydalash';

$retseptlar = $db->rows(
    "SELECT r.*, m.nomi AS mah_nomi, m.birlik AS mah_birlik
     FROM im_retseptlar r
     LEFT JOIN im_mahsulotlar m ON m.id=r.mahsulot_id
     WHERE r.status=1 AND r.tur='{$db->f($tur_get)}'
     ORDER BY r.nomi"
);

$filiallar = [];
if ($im_rol === 'admin') {
    $filiallar = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY nomi");
}

$page_title = $tur_get === 'ishlab_chiqarish' ? 'Yangi Ishlab chiqarish' : 'Yangi Maydalash';
$page_icon  = $tur_get === 'ishlab_chiqarish' ? 'fire' : 'scissors';
$page_color = $tur_get === 'ishlab_chiqarish' ? 'var(--primary)' : 'var(--warning)';
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
      <div class="im-page-title">
        <i class="bi bi-<?= $page_icon ?> me-1" style="color:<?= $page_color ?>"></i>
        <?= im_f($page_title) ?>
      </div>
      <div class="im-topbar-actions">
        <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
      </div>
    </header>

    <main class="im-content">
      <div class="im-breadcrumb">
        <a href="<?= im_BASE ?>qayta-ishlash/index.php">Qayta Ishlash</a>
        <i class="bi bi-chevron-right fs-xs"></i>
        <span class="active"><?= im_f($page_title) ?></span>
      </div>

      <!-- Tur tugmalari -->
      <div class="d-flex gap-2 mb-4">
        <a href="?tur=ishlab_chiqarish"
           class="im-btn <?= $tur_get==='ishlab_chiqarish' ? 'im-btn-primary' : 'im-btn-outline' ?>">
          <i class="bi bi-fire"></i> Ishlab chiqarish
        </a>
        <a href="?tur=maydalash"
           class="im-btn <?= $tur_get==='maydalash' ? 'im-btn-warning' : 'im-btn-outline' ?>">
          <i class="bi bi-scissors"></i> Maydalash
        </a>
      </div>

      <div class="row g-4">
        <!-- Chap: Forma -->
        <div class="col-lg-5">
          <div class="im-card im-slide-in">
            <div class="im-card-header">
              <i class="bi bi-<?= $page_icon ?>" style="color:<?= $page_color ?>"></i>
              <span class="im-card-title"><?= im_f($page_title) ?></span>
            </div>
            <div class="im-card-body">

              <?php if (empty($retseptlar)): ?>
              <div class="im-empty">
                <i class="bi bi-journal-x"></i><h4>Retseptlar yo'q</h4>
                <?php if ($im_rol === 'admin'): ?>
                <a href="<?= im_BASE ?>qayta-ishlash/retseptlar.php" class="im-btn im-btn-primary">Retsept qo'shish</a>
                <?php else: ?>
                <p class="text-muted">Admin yangi retsept qo'shishi kerak</p>
                <?php endif; ?>
              </div>
              <?php else: ?>

              <div class="mb-3">
                <label class="im-label">Retsept <span class="text-danger">*</span></label>
                <select id="retsept_id" class="im-input" onchange="onRetseptChange()">
                  <option value="">— Retsept tanlang —</option>
                  <?php foreach ($retseptlar as $r): ?>
                  <option value="<?= $r['id'] ?>"
                          data-chiqish="<?= (float)$r['chiqish_soni'] ?>"
                          data-birlik="<?= im_f($r['mah_birlik']) ?>">
                    <?= im_f($r['nomi']) ?> → <?= im_f($r['mah_nomi']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="im-label">
                  <?= $tur_get==='ishlab_chiqarish' ? 'Qancha tayyorlanadi (porsiya / dona)' : 'Qancha birlik maydalash' ?>
                  <span class="text-danger">*</span>
                </label>
                <input type="number" id="ishlab_soni" class="im-input" value="1" min="0.001" step="0.001" oninput="onSoniChange()">
                <div class="text-muted fs-xs mt-1" id="soni_hint"></div>
              </div>

              <?php if ($im_rol === 'admin'): ?>
              <div class="mb-3">
                <label class="im-label">Joylashuv</label>
                <select id="filial_id" class="im-input" onchange="onSoniChange()">
                  <option value="">Sklad (asosiy ombor)</option>
                  <?php foreach ($filiallar as $f): ?>
                  <option value="<?= $f['id'] ?>"><?= im_f($f['nomi']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php else: ?>
              <input type="hidden" id="filial_id" value="<?= $im_filial_id ?>">
              <?php endif; ?>

              <div class="mb-4">
                <label class="im-label">Izoh</label>
                <textarea id="ishlab_izoh" class="im-input" rows="2" placeholder="Ixtiyoriy..."></textarea>
              </div>

              <button class="im-btn im-btn-<?= $tur_get==='ishlab_chiqarish'?'primary':'warning' ?> w-100"
                      id="boshlaBtn" onclick="boshlaIshlab()" disabled>
                <i class="bi bi-play-fill"></i> Boshlash
              </button>
              <?php endif; ?>

            </div>
          </div>
        </div>

        <!-- O'ng: Preview & Ma'lumot -->
        <div class="col-lg-7">

          <div class="im-card im-slide-in" id="previewCard" style="display:none">
            <div class="im-card-header">
              <i class="bi bi-list-check" style="color:var(--success)"></i>
              <span class="im-card-title">Qoldiq tekshiruvi</span>
              <span id="all_ok_badge" class="ms-auto"></span>
            </div>
            <div class="im-card-body" id="previewBody"></div>
          </div>

          <div class="im-card im-slide-in <?= empty($retseptlar) ? '' : 'mt-3' ?>">
            <div class="im-card-body">
              <?php if ($tur_get === 'ishlab_chiqarish'): ?>
              <p class="fw-semibold mb-2 text-muted">🍳 Ishlab chiqarish qanday ishlaydi:</p>
              <ol class="text-muted" style="font-size:13px;padding-left:18px">
                <li>Retsept va necha porsiya tayyorlashni tanlaysiz (1 = 1 porsiya)</li>
                <li>Tizim xomashyolar yetarliligini ko'rsatadi</li>
                <li>Yetarliligi tasdiqlangach "Boshlash" bosiladi</li>
                <li>Xomashyolar FIFO tartibida kamaytiriladi</li>
                <li>Tayyor mahsulot omborga qo'shiladi, yangi tannarx hisoblanadi</li>
              </ol>
              <?php else: ?>
              <p class="fw-semibold mb-2 text-muted">✂️ Maydalash qanday ishlaydi:</p>
              <ol class="text-muted" style="font-size:13px;padding-left:18px">
                <li>Retsept va marta sonini tanlaysiz (1 = 1 butun mahsulot)</li>
                <li>Tizim asosiy mahsulot yetarliligini tekshiradi</li>
                <li>Boshlanganda asosiy mahsulot kamayadi</li>
                <li>Chiqishlar alohida mahsulot sifatida filial qoldig'iga qo'shiladi</li>
                <li>Kirish tannarxi chiqish donalariga taqsimlanadi; sotuv narxlari alohida qoladi</li>
              </ol>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>

      <!-- Oxirgi operatsiyalar -->
      <div class="im-card im-slide-in mt-4">
        <div class="im-card-header">
          <i class="bi bi-clock-history"></i>
          <span class="im-card-title">Oxirgi <?= $tur_get==='ishlab_chiqarish'?'ishlab chiqarishlar':'maydalashlar' ?></span>
        </div>
        <div class="im-table-wrap">
          <table class="im-table">
            <thead>
              <tr><th>#</th><th>Retsept</th><th>Mahsulot</th><th>Soni</th><th>Joylashuv</th><th>Xodim</th><th>Sana</th><th>Holat</th><th></th></tr>
            </thead>
            <tbody>
              <?php
              $t_q = $db->f($tur_get);
              $oxirgi_cond = $im_rol === 'oshpaz' ? ' AND ic.filial_id='.(int)$im_filial_id : '';
              $oxirgi = $db->rows(
                  "SELECT ic.*, r.nomi AS r_nomi, m.nomi AS m_nomi,
                          x.ism AS x_ism, f.nomi AS f_nomi
                   FROM im_ishlab_chiqarish ic
                   LEFT JOIN im_retseptlar r ON r.id=ic.retsept_id
                   LEFT JOIN im_mahsulotlar m ON m.id=ic.mahsulot_id
                   LEFT JOIN im_xodimlar x ON x.id=ic.xodim_id
                   LEFT JOIN im_filiallar f ON f.id=ic.filial_id
                   WHERE ic.tur='$t_q' $oxirgi_cond
                   ORDER BY ic.sana DESC LIMIT 10"
              );
              if ($oxirgi): foreach ($oxirgi as $op): ?>
              <tr>
                <td class="text-muted fs-sm"><?= $op['id'] ?></td>
                <td class="fw-semibold"><?= im_f($op['r_nomi']?:'—') ?></td>
                <td><?= im_f($op['m_nomi']?:'—') ?></td>
                <td class="num"><?= (float)$op['soni'] ?> × → <strong><?= (float)$op['chiqish_soni'] ?></strong></td>
                <td><?= im_f($op['f_nomi']?:'Sklad') ?></td>
                <td class="text-muted fs-sm"><?= im_f($op['x_ism']?:'—') ?></td>
                <td class="text-muted fs-sm"><?= im_datetime($op['sana']) ?></td>
                <td>
                  <?= $op['holat']==='bajarildi'
                    ? '<span class="im-badge im-badge-success">✓</span>'
                    : '<span class="im-badge im-badge-danger">✗ Bekor</span>' ?>
                </td>
                <td>
                  <?php if ($op['holat']==='bajarildi' && (time()-strtotime($op['sana']))<86400): ?>
                  <button class="im-btn im-btn-danger im-btn-sm" onclick="bekorQilish(<?= $op['id'] ?>)">
                    <i class="bi bi-x-circle"></i>
                  </button>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="9">
                <div class="im-empty"><i class="bi bi-clock-history"></i><h4>Tarix yo'q</h4></div>
              </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<div id="im-toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.im_BASE="<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
<script src="<?= im_BASE ?>assets/js/main.js"></script>
<script>
const BASE          = (window.im_BASE || '/').replace(/\/$/, '');
const URL_QOLDIQ    = BASE + '/qayta-ishlash/ajax/qoldiq-check.php';
const URL_ISHLAB    = BASE + '/qayta-ishlash/ajax/ishlab-save.php';
const URL_BEKOR     = BASE + '/qayta-ishlash/ajax/ishlab-bekor.php';

let checkTimer = null;

function onRetseptChange() { onSoniChange(); }

function onSoniChange() {
  clearTimeout(checkTimer);
  const rid  = document.getElementById('retsept_id')?.value;
  const soni = document.getElementById('ishlab_soni')?.value;
  document.getElementById('boshlaBtn').disabled = true;
  if (!rid || !soni || parseFloat(soni) <= 0) {
    document.getElementById('previewCard').style.display = 'none';
    return;
  }
  checkTimer = setTimeout(checkQoldiq, 400);
}

async function checkQoldiq() {
  const rid    = document.getElementById('retsept_id').value;
  const soni   = document.getElementById('ishlab_soni').value;
  const filial = document.getElementById('filial_id')?.value || '';
  if (!rid || !soni) return;

  const res = await IMAjax.get(URL_QOLDIQ, {
    retsept_id: rid, soni, filial_id: filial
  });

  const card  = document.getElementById('previewCard');
  const body  = document.getElementById('previewBody');
  const badge = document.getElementById('all_ok_badge');
  card.style.display = '';

  if (res.status !== 'ok') {
    body.innerHTML = `<div class="text-danger">${res.msg}</div>`;
    return;
  }

  const allOk = res.data.all_ok;
  badge.innerHTML = allOk
    ? '<span class="im-badge im-badge-success">✅ Yetarli</span>'
    : '<span class="im-badge im-badge-danger">❌ Yetmaydi</span>';

  let html = '<div class="im-table-wrap"><table class="im-table">';
  html += '<thead><tr><th>Mahsulot</th><th class="text-right">Kerak</th><th class="text-right">Mavjud</th><th>Holat</th></tr></thead><tbody>';
  res.data.items.forEach(it => {
    html += `<tr>
      <td class="fw-semibold">${it.mahsulot_nomi}</td>
      <td class="text-right num">${it.kerakli} ${it.birlik}</td>
      <td class="text-right num ${it.yetarli ? '' : 'text-danger fw-bold'}">${it.mavjud} ${it.birlik}</td>
      <td>${it.yetarli
        ? '<span class="im-badge im-badge-success">✓ Yetarli</span>'
        : '<span class="im-badge im-badge-danger">✗ Yetmaydi</span>'}</td>
    </tr>`;
  });
  html += '</tbody></table></div>';

  if (res.data.chiqish_info) {
    const ch = res.data.chiqish_info;
    let chiqishHtml = `<div class="mt-3 p-3" style="background:var(--bg-soft);border-radius:8px">`;
    if (ch.chiqish_list && ch.chiqish_list.length > 0) {
      // Maydalash: natija retseptda qat'iy belgilangan.
      const bajarishSoni = parseFloat(document.getElementById('ishlab_soni').value || 1);
      chiqishHtml += `<strong>✂️ Retsept bo'yicha hosil bo'ladi:</strong>
        <div class="mt-2" id="chiqish_inputs_container">`;
      ch.chiqish_list.forEach(c => {
        const natija = (parseFloat(c.soni || 0) * bajarishSoni).toFixed(3);
        chiqishHtml += `
         <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="fs-sm">${c.mahsulot_nomi} (${c.birlik})</span>
            <input type="number" class="im-input chiqish-input" 
                   data-id="${c.mahsulot_id}" 
                   value="${natija}" readonly
                   style="width:120px;background:var(--bg);font-weight:700">
         </div>`;
      });
      chiqishHtml += `</div>`;
    } else {
      // Ishlab chiqarish
      chiqishHtml += `<strong>🎯 Hosil bo'ladi:</strong>
        <span class="num ms-2 fw-bold">${ch.soni} ${ch.birlik}</span>
        ${ch.mahsulot_nomi ? `<span class="text-muted ms-1">${ch.mahsulot_nomi}</span>` : ''}`;
    }
    chiqishHtml += `</div>`;
    html += chiqishHtml;
  }
  body.innerHTML = html;

  document.getElementById('boshlaBtn').disabled = !allOk;

  // Hint
  const opt = document.getElementById('retsept_id').selectedOptions[0];
  const ch  = parseFloat(opt?.dataset.chiqish || 0);
  const bir = opt?.dataset.birlik || '';
  const sn  = parseFloat(document.getElementById('ishlab_soni').value || 1);
  document.getElementById('soni_hint').textContent = ch
    ? `Natija: ${(ch * sn).toFixed(3)} ${bir}` : '';

}

async function boshlaIshlab() {
  const rid    = document.getElementById('retsept_id').value;
  const soni   = document.getElementById('ishlab_soni').value;
  const filial = document.getElementById('filial_id')?.value || '';
  const izoh   = document.getElementById('ishlab_izoh').value;
  if (!rid || !soni) return;

  // Maydalash uchun dinamik input qilingan qiymatlarni olamiz
  let chiqishItems = [];
  const inputs = document.querySelectorAll('.chiqish-input');
  if (inputs.length > 0) {
      inputs.forEach(inp => {
          const val = parseFloat(inp.value || 0);
          chiqishItems.push({ mahsulot_id: inp.dataset.id, soni: val });
      });
  }

  const ok = await NHConfirm.ask('Jarayonni boshlashni tasdiqlaysizmi?');
  if (!ok) return;

  const btn = document.getElementById('boshlaBtn');
  btn.disabled = true; btn.innerHTML = '<span class="im-spinner"></span>';

  const res = await IMAjax.post(URL_ISHLAB, {
    retsept_id: rid, 
    soni, 
    filial_id: filial, 
    izoh,
    chiqish_items: JSON.stringify(chiqishItems)
  });

  btn.disabled = false; btn.innerHTML = '<i class="bi bi-play-fill"></i> Boshlash';

  if (res.status === 'ok') {
    NHToast.success(res.msg || 'Muvaffaqiyatli!');
    setTimeout(() => location.reload(), 1000);
  } else {
    NHToast.error(res.msg || 'Xatolik!');
  }
}

async function bekorQilish(id) {
  const ok = await NHConfirm.delete('ushbu operatsiyani');
  if (!ok) return;
  const res = await IMAjax.post(URL_BEKOR, { id: id });
  if (res.status === 'ok') {
    NHToast.success(res.msg);
    setTimeout(() => location.reload(), 800);
  } else {
    NHToast.error(res.msg || 'Xatolik!');
  }
}
</script>
</body>
</html>
