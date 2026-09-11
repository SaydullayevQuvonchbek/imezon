<?php
// ============================================================
//  IMezon — Retseptlar boshqaruvi (Admin only)
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);

$db = new Cyber();

$mahsulotlar = $db->rows(
  "SELECT m.id, m.nomi, m.birlik, m.sotiladi, m.faqat_ishlab_chiqarish, k.nomi AS kat_nomi
     FROM im_mahsulotlar m
     LEFT JOIN im_kategoriyalar k ON k.id = m.kategoriya_id
     WHERE m.status=1 ORDER BY m.nomi ASC"
);

$retseptlar = $db->rows(
  "SELECT r.*, m.nomi AS mahsulot_nomi, m.birlik AS mahsulot_birlik,
            (SELECT COUNT(*) FROM im_retsept_items WHERE retsept_id=r.id) AS items_soni,
            (SELECT COUNT(*) FROM im_ishlab_chiqarish WHERE retsept_id=r.id AND holat='bajarildi') AS ishlat_soni
     FROM im_retseptlar r
     LEFT JOIN im_mahsulotlar m ON m.id = r.mahsulot_id
     ORDER BY r.tur, r.nomi"
);

$page_title = 'Retseptlar';
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
        <div class="im-page-title"><i class="bi bi-journal-text me-1"></i> Retseptlar</div>
        <div class="im-topbar-actions">
          <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
          <button class="im-btn im-btn-primary im-btn-sm" onclick="openRetseptModal()">
            <i class="bi bi-plus-lg"></i> Yangi retsept
          </button>
        </div>
      </header>

      <main class="im-content">
        <div class="im-breadcrumb">
          <a href="<?= im_BASE ?>qayta-ishlash/index.php">Qayta Ishlash</a>
          <i class="bi bi-chevron-right fs-xs"></i>
          <span class="active">Retseptlar</span>
        </div>

        <!-- Tabs -->
        <div class="mb-3 d-flex gap-2">
          <button class="im-btn im-btn-primary im-btn-sm tab-btn active" data-tab="ishlab">
            <i class="bi bi-fire"></i> Ishlab chiqarish
          </button>
          <button class="im-btn im-btn-outline im-btn-sm tab-btn" data-tab="maydalash">
            <i class="bi bi-scissors"></i> Maydalash
          </button>
        </div>

        <!-- Ishlab chiqarish -->
        <div id="tab-ishlab">
          <div class="im-card im-slide-in">
            <div class="im-card-header">
              <i class="bi bi-fire" style="color:var(--primary)"></i>
              <span class="im-card-title">Ishlab chiqarish retseptlari</span>
              <span class="im-badge im-badge-info ms-2">
                <?= count(array_filter($retseptlar, fn($r) => $r['tur'] === 'ishlab_chiqarish')) ?> ta
              </span>
              <button class="im-btn im-btn-primary im-btn-sm ms-auto" onclick="openRetseptModal('ishlab_chiqarish')">
                <i class="bi bi-plus-lg"></i> Qo'shish
              </button>
            </div>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Nomi</th>
                    <th>Tayyor mahsulot</th>
                    <th>Chiqish</th>
                    <th>Xomashyo</th>
                    <th>Ishlatilgan</th>
                    <th>Amallar</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $ic_list = array_values(array_filter($retseptlar, fn($r) => $r['tur'] === 'ishlab_chiqarish')); ?>
                  <?php if ($ic_list):
                    foreach ($ic_list as $r): ?>
                      <tr>
                        <td class="text-muted fs-sm"><?= $r['id'] ?></td>
                        <td class="fw-semibold"><?= im_f($r['nomi']) ?></td>
                        <td><?= im_f($r['mahsulot_nomi'] ?: '—') ?></td>
                        <td class="num"><?= (float) $r['chiqish_soni'] ?>     <?= im_f($r['birlik']) ?></td>
                        <td><span class="im-badge im-badge-info"><?= (int) $r['items_soni'] ?> xil</span></td>
                        <td class="num text-muted"><?= (int) $r['ishlat_soni'] ?> marta</td>
                        <td>
                          <button class="im-btn im-btn-outline im-btn-sm" onclick="editRetsept(<?= $r['id'] ?>)"
                            title="Tahrirlash">
                            <i class="bi bi-pencil-fill"></i>
                          </button>
                          <button class="im-btn im-btn-outline im-btn-sm ms-1"
                            onclick="viewItems(<?= $r['id'] ?>, '<?= im_js($r['nomi']) ?>')" title="Ko'rish">
                            <i class="bi bi-eye-fill"></i>
                          </button>
                          <?php if ((int) $r['ishlat_soni'] === 0): ?>
                            <button class="im-btn im-btn-danger im-btn-sm ms-1"
                              onclick="deleteRetsept(<?= $r['id'] ?>, '<?= im_js($r['nomi']) ?>')" title="O'chirish">
                              <i class="bi bi-trash-fill"></i>
                            </button>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; else: ?>
                    <tr>
                      <td colspan="7">
                        <div class="im-empty">
                          <i class="bi bi-journal-x"></i>
                          <h4>Retseptlar yo'q</h4>
                          <button class="im-btn im-btn-primary" onclick="openRetseptModal('ishlab_chiqarish')">
                            <i class="bi bi-plus-lg"></i> Birinchi retseptni qo'shing
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Maydalash -->
        <div id="tab-maydalash" style="display:none">
          <div class="im-card im-slide-in">
            <div class="im-card-header">
              <i class="bi bi-scissors" style="color:var(--warning)"></i>
              <span class="im-card-title">Maydalash retseptlari</span>
              <span class="im-badge im-badge-warning ms-2">
                <?= count(array_filter($retseptlar, fn($r) => $r['tur'] === 'maydalash')) ?> ta
              </span>
              <button class="im-btn im-btn-warning im-btn-sm ms-auto" onclick="openRetseptModal('maydalash')">
                <i class="bi bi-plus-lg"></i> Qo'shish
              </button>
            </div>
            <div class="im-table-wrap">
              <table class="im-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Nomi</th>
                    <th>Asosiy mahsulot</th>
                    <th>Chiqish mahsulotlar</th>
                    <th>Ishlatilgan</th>
                    <th>Amallar</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $m_list = array_values(array_filter($retseptlar, fn($r) => $r['tur'] === 'maydalash')); ?>
                  <?php if ($m_list):
                    foreach ($m_list as $r): ?>
                      <tr>
                        <td class="text-muted fs-sm"><?= $r['id'] ?></td>
                        <td class="fw-semibold"><?= im_f($r['nomi']) ?></td>
                        <td><?= im_f($r['mahsulot_nomi'] ?: '—') ?></td>
                        <td><span class="im-badge im-badge-warning"><?= (int) $r['items_soni'] ?> xil</span></td>
                        <td class="num text-muted"><?= (int) $r['ishlat_soni'] ?> marta</td>
                        <td>
                          <button class="im-btn im-btn-outline im-btn-sm" onclick="editRetsept(<?= $r['id'] ?>)">
                            <i class="bi bi-pencil-fill"></i>
                          </button>
                          <button class="im-btn im-btn-outline im-btn-sm ms-1"
                            onclick="viewItems(<?= $r['id'] ?>, '<?= im_js($r['nomi']) ?>')">
                            <i class="bi bi-eye-fill"></i>
                          </button>
                          <?php if ((int) $r['ishlat_soni'] === 0): ?>
                            <button class="im-btn im-btn-danger im-btn-sm ms-1"
                              onclick="deleteRetsept(<?= $r['id'] ?>, '<?= im_js($r['nomi']) ?>')">
                              <i class="bi bi-trash-fill"></i>
                            </button>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; else: ?>
                    <tr>
                      <td colspan="6">
                        <div class="im-empty">
                          <i class="bi bi-scissors"></i>
                          <h4>Maydalash retseptlari yo'q</h4>
                          <button class="im-btn im-btn-warning" onclick="openRetseptModal('maydalash')">
                            <i class="bi bi-plus-lg"></i> Qo'shing
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- ═══ RETSEPT MODALI ═══ -->
  <div class="im-overlay" id="retseptModal">
    <div class="im-modal" style="max-width:680px">
      <div class="im-modal-header">
        <span class="im-modal-title" id="retseptModalTitle">
          <i class="bi bi-journal-plus"></i> Yangi retsept
        </span>
        <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="im-modal-body">
        <input type="hidden" id="retsept_id" value="">

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="im-label">Retsept nomi <span class="text-danger">*</span></label>
            <input type="text" id="r_nomi" class="im-input" placeholder="Masalan: Osh pishirish">
          </div>
          <div class="col-md-6">
            <label class="im-label">Turi <span class="text-danger">*</span></label>
            <select id="r_tur" class="im-input" onchange="onTurChange()">
              <option value="ishlab_chiqarish">🍳 Ishlab chiqarish</option>
              <option value="maydalash">✂️ Maydalash</option>
            </select>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <label class="im-label" id="mahsulot_label">Tayyor mahsulot <span class="text-danger">*</span></label>
            <select id="r_mahsulot_id" class="im-input">
              <option value="">— Mahsulot tanlang —</option>
            </select>
          </div>
          <div class="col-md-4" id="chiqish_wrap">
            <label class="im-label">Bir bajarilishda chiqishi</label>
            <div class="d-flex gap-2">
              <input type="number" id="r_chiqish_soni" class="im-input" value="1" min="0.001" step="0.001"
                     oninput="hisobYangila()">
              <select id="r_birlik" class="im-input" style="max-width:85px" onchange="hisobYangila()">
                <option value="dona">dona</option>
                <option value="kg">kg</option>
                <option value="litr">litr</option>
                <option value="metr">metr</option>
              </select>
            </div>
            <small class="text-muted fs-xs d-block mt-1" id="chiqish_hint">
              Ishlab chiqarishda <b>doim 1</b> — xomashyolar <b>1 porsiya / 1 dona / 1 shampur</b> tayyor
              mahsulotga yoziladi. Kerakli son keyin ko'paytiriladi.
            </small>
          </div>
        </div>

        <!-- Qanday to'ldirish kerakligi haqida qisqa izoh -->
        <div class="mb-3 p-3" id="retsept_yordam"
             style="background:rgba(13,110,253,.06);border:1px solid rgba(13,110,253,.18);border-radius:10px">
          <div class="fw-bold mb-1" style="font-size:13px;color:#0d6efd">
            <i class="bi bi-info-circle-fill"></i> Qanday to'ldiriladi
          </div>
          <div class="fs-xs text-muted" style="line-height:1.55">
            Xomashyo miqdorlarini <b>1 porsiya</b> (yoki 1 dona) tayyor mahsulotga yozing.<br>
            Masalan 1 porsiya oshga: guruch <b>0.15 kg</b>, go'sht <b>0.15 kg</b>, sabzi <b>0.12 kg</b>.
            Ishlab chiqarishda kerakli porsiya sonini kiritsangiz, tizim hammasini o'sha songa ko'paytiradi.
          </div>
        </div>

        <div class="mb-2">
          <label class="im-label" id="items_label">Xomashyolar <span class="text-danger">*</span></label>
          <div id="items_container"></div>
          <button type="button" class="im-btn im-btn-outline im-btn-sm mt-2" onclick="addItemRow()">
            <i class="bi bi-plus-circle-fill"></i> Qator qo'shish
          </button>

          <!-- Jonli hisob: kiritilgan xomashyolar 1 tayyor birlikka
               (odatda 1 porsiya) to'g'ri kelishini darrov ko'rsatadi.
               "Bir bajarilishda chiqishi" 1 dan farq qilsa — ogohlantiradi. -->
          <div id="birlik_hisob" class="mt-3 p-3" style="display:none;
               background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.22);border-radius:10px">
            <div class="fw-bold mb-1" style="font-size:13px;color:#047857">
              <i class="bi bi-calculator-fill"></i> Hisob tekshiruvi
            </div>
            <div id="birlik_hisob_matn" class="fs-xs" style="color:#065f46;line-height:1.6"></div>
          </div>
        </div>

        <div class="mt-3">
          <label class="im-label">Izoh</label>
          <textarea id="r_izoh" class="im-input" rows="2" placeholder="Ixtiyoriy..."></textarea>
        </div>
      </div>
      <div class="im-modal-footer">
        <button class="im-btn im-btn-outline" data-modal-close>Bekor</button>
        <button class="im-btn im-btn-primary" onclick="saveRetsept()" id="saveRetseptBtn">
          <i class="bi bi-check-lg"></i> Saqlash
        </button>
      </div>
    </div>
  </div>

  <!-- ═══ KO'RISH MODALI ═══ -->
  <div class="im-overlay" id="viewModal">
    <div class="im-modal" style="max-width:500px">
      <div class="im-modal-header">
        <span class="im-modal-title" id="viewModalTitle"><i class="bi bi-eye-fill"></i> Tarkib</span>
        <button class="im-modal-close" data-modal-close><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="im-modal-body" id="viewModalBody">Yuklanmoqda...</div>
    </div>
  </div>

  <div id="im-toast-container"></div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>window.im_BASE = "<?= defined('im_BASE') ? im_BASE : '/' ?>"; window.im_csrf="<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";</script>
  <script src="<?= im_BASE ?>assets/js/main.js"></script>
  <script>
    const MAHSULOTLAR      = <?= json_encode($mahsulotlar, JSON_UNESCAPED_UNICODE) ?>;
    // Faqat ishlab chiqarish (tayyor mahsulot) — faqat_ishlab_chiqarish=1
    const MAHSULOTLAR_TAYYOR   = MAHSULOTLAR.filter(function(m){ return m.faqat_ishlab_chiqarish == 1; });
    // Faqat xomashyo (sotiladi=0) — retsept xomashyolari uchun
    const MAHSULOTLAR_XOMASHYO = MAHSULOTLAR.filter(function(m){ return m.sotiladi == 0; });
    // Barcha mahsulotlar — maydalash uchun
    const MAHSULOTLAR_ALL      = MAHSULOTLAR;

    // URL konstantalar (im_BASE bilan mutlaq yo'l)
    const BASE          = (window.im_BASE || '/').replace(/\/$/, '');
    const URL_RET_SAVE  = BASE + '/qayta-ishlash/ajax/retsept-save.php';
    const URL_RET_DEL   = BASE + '/qayta-ishlash/ajax/retsept-delete.php';

    function fillMahsulotDropdown(turVal, selectedId) {
      const list = (turVal === 'ishlab_chiqarish') ? MAHSULOTLAR_TAYYOR : MAHSULOTLAR_ALL;
      const sel  = document.getElementById('r_mahsulot_id');
      sel.innerHTML = '<option value="">— Mahsulot tanlang —</option>';
      list.forEach(function(m) {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.nomi + (m.kat_nomi ? ' (' + m.kat_nomi + ')' : '');
        if (selectedId && m.id == selectedId) opt.selected = true;
        sel.appendChild(opt);
      });
      if (list.length === 0) {
        const opt = document.createElement('option');
        opt.disabled = true;
        opt.textContent = turVal === 'ishlab_chiqarish'
          ? '⚠️ Mahsulotlar yo‘q — avval "Faqat IC" belgilang'
          : 'Mahsulotlar yo‘q';
        sel.appendChild(opt);
      }
    }

    // Tab
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.tab-btn').forEach(function(b) {
          b.classList.remove('active', 'im-btn-primary'); b.classList.add('im-btn-outline');
        });
        this.classList.add('active', 'im-btn-primary'); this.classList.remove('im-btn-outline');
        document.getElementById('tab-ishlab').style.display   = this.dataset.tab === 'ishlab'    ? '' : 'none';
        document.getElementById('tab-maydalash').style.display = this.dataset.tab === 'maydalash' ? '' : 'none';
      });
    });

    function openRetseptModal(tur) {
      tur = tur || 'ishlab_chiqarish';
      document.getElementById('retsept_id').value = '';
      document.getElementById('r_nomi').value = '';
      document.getElementById('r_tur').value = tur;
      document.getElementById('r_chiqish_soni').value = 1;
      document.getElementById('r_birlik').value = 'dona';
      document.getElementById('r_izoh').value = '';
      document.getElementById('items_container').innerHTML = '';
      document.getElementById('retseptModalTitle').innerHTML = '<i class="bi bi-journal-plus"></i> Yangi retsept';
      fillMahsulotDropdown(tur, '');
      onTurChange();
      addItemRow();
      NHModal.open('retseptModal');
    }

    function closeRetseptModal() { NHModal.close('retseptModal'); }

    // 1 tayyor birlikka (odatda 1 porsiya) qancha xomashyo ketishini
    // ko'rsatadi. "Bir bajarilishda chiqishi" 1 dan farq qilsa — retsept
    // porsiyaga emas, qozonga yozilgan degani, shu yerda ogohlantiramiz.
    function hisobYangila() {
      const blok = document.getElementById('birlik_hisob');
      const matn = document.getElementById('birlik_hisob_matn');
      if (!blok || !matn) return;

      const tur = document.getElementById('r_tur').value;
      if (tur !== 'ishlab_chiqarish') { blok.style.display = 'none'; return; }

      const chiqish = parseFloat(document.getElementById('r_chiqish_soni').value) || 0;
      const birlik  = document.getElementById('r_birlik').value || 'dona';

      const qatorlar = [];
      document.querySelectorAll('#items_container > div').forEach(function (row) {
        const sel  = row.querySelector('select[name="item_mahsulot[]"]');
        const soni = row.querySelector('input[name="item_soni[]"]');
        const bir  = row.querySelector('select[name="item_birlik[]"]');
        if (!sel || !sel.value) return;
        const n = parseFloat(soni ? soni.value : 0) || 0;
        if (n <= 0) return;
        qatorlar.push({
          nomi: sel.options[sel.selectedIndex].textContent.replace(/\s*\([^)]*\)\s*$/, ''),
          soni: n,
          birlik: bir ? bir.value : ''
        });
      });

      if (chiqish <= 0 || qatorlar.length === 0) { blok.style.display = 'none'; return; }

      const ogoh = (Math.abs(chiqish - 1) > 0.0001)
        ? '<br><span style="color:#b45309">⚠️ «Bir bajarilishda chiqishi» odatda <b>1</b> bo\'ladi (hozir '
          + parseFloat(chiqish.toFixed(3)) + '). Xomashyoni 1 ' + birlik + ' ga yozing.</span>'
        : '';

      matn.innerHTML =
        '<b>1 ' + birlik + '</b> uchun: ' +
        qatorlar.map(q => '<b>' + q.nomi + '</b> ' + parseFloat((q.soni / chiqish).toFixed(4)) + ' ' + q.birlik)
                .join(' &nbsp;·&nbsp; ') +
        ogoh;
      blok.style.display = '';
    }

    function onTurChange() {
      const tur      = document.getElementById('r_tur').value;
      const isIshlab = tur === 'ishlab_chiqarish';
      document.getElementById('mahsulot_label').innerHTML = isIshlab
        ? 'Tayyor mahsulot <span class="text-danger">*</span>'
        : 'Asosiy mahsulot (kirish) <span class="text-danger">*</span>';
      document.getElementById('items_label').textContent = isIshlab ? 'Xomashyolar *' : 'Chiqish mahsulotlari *';
      document.getElementById('chiqish_wrap').style.display = isIshlab ? '' : 'none';
      const yordam = document.getElementById('retsept_yordam');
      if (yordam) {
        yordam.querySelector('.fw-bold').innerHTML = isIshlab
          ? '<i class="bi bi-info-circle-fill"></i> Qanday to\'ldiriladi'
          : '<i class="bi bi-scissors"></i> Maydalash konversiyasi';
        yordam.querySelector('.fs-xs').innerHTML = isIshlab
          ? 'Xomashyo miqdorlarini <b>1 porsiya</b> (yoki 1 dona) tayyor mahsulotga yozing.<br>Masalan 1 porsiya oshga: guruch <b>0.15 kg</b>, go\'sht <b>0.15 kg</b>, sabzi <b>0.12 kg</b>.'
          : 'Asosiy mahsulotni tanlang, keyin undan chiqadigan <b>alohida mahsulotlar</b> va sonini yozing.<br>Masalan: <b>1 Non butun → 2 Non yarimta</b> yoki <b>1 Non butun → 4 Non chorak</b>.';
      }

      // Ishlab chiqarish retsepti DOIM 1 tayyor birlikka — server ham
      // shuni majburlaydi (retsept-save.php). Maydan qulflab qo'yamiz.
      const chiqInp = document.getElementById('r_chiqish_soni');
      if (isIshlab) { chiqInp.value = 1; chiqInp.readOnly = true; chiqInp.style.opacity = .6; }
      else          { chiqInp.readOnly = false; chiqInp.style.opacity = 1; }

      // Tayyor/Asosiy mahsulot dropdownni yangilash.
      // MUHIM: joriy tanlovni SAQLAB qolamiz. Ilgari bu yerda bo'sh qiymat
      // berilardi va tahrirlash oynasi ochilganda editRetsept() qo'ygan
      // mahsulot darrov o'chib, "— Mahsulot tanlang —" ko'rinib qolardi.
      const joriy_mah = document.getElementById('r_mahsulot_id').value;
      fillMahsulotDropdown(tur, joriy_mah);

      // Qatorlar turga qarab filtrlangani uchun qayta yaratish kerak
      const container = document.getElementById('items_container');
      if (container.children.length > 0) {
        container.innerHTML = '';
        rowCnt = 0;
        addItemRow();
      }
      hisobYangila();
    }

    let rowCnt = 0;
    function addItemRow(data = {}) {
      rowCnt++;
      const id = rowCnt;
      const tur = document.getElementById('r_tur').value;
      const isM = (tur === 'maydalash');

      // Xomashyolar uchun faqat sotiladi=0, maydalash chiqishi uchun hammasi
      const list = (tur === 'ishlab_chiqarish') ? MAHSULOTLAR_XOMASHYO : MAHSULOTLAR_ALL;

      const opts = list.map(m =>
        `<option value="${m.id}" ${m.id == (data.mahsulot_id || '') ? 'selected' : ''}>${im_esc(m.nomi)}${m.birlik ? ' (' + im_esc(m.birlik) + ')' : ''}</option>`
      ).join('');

      const html = `
    <div class="d-flex gap-2 mb-2 align-items-center" id="item-row-${id}">
      <select class="im-input" name="item_mahsulot[]" style="flex:1" onchange="hisobYangila()">
        <option value="">— Mahsulot —</option>${opts}
      </select>
      ` + (isM ? 
        `<input type="number" class="im-input" name="item_soni[]" placeholder="Chiqish soni"
             value="${data.soni || ''}" min="0.001" step="0.001" style="width:120px">` 
        : 
        `<input type="number" class="im-input" name="item_soni[]" placeholder="Soni"
             value="${data.soni || ''}" min="0.001" step="0.001" style="width:100px"
             oninput="hisobYangila()">`
      ) + `
      <select class="im-input" name="item_birlik[]" style="width:80px" onchange="hisobYangila()">
        <option value="dona" ${(data.birlik || 'dona') === 'dona' ? 'selected' : ''}>dona</option>
        <option value="kg"   ${(data.birlik || '') === 'kg' ? 'selected' : ''}>kg</option>
        <option value="litr" ${(data.birlik || '') === 'litr' ? 'selected' : ''}>litr</option>
        <option value="metr" ${(data.birlik || '') === 'metr' ? 'selected' : ''}>metr</option>
      </select>
      <button type="button" class="im-btn im-btn-danger im-btn-sm im-btn-icon"
              onclick="document.getElementById('item-row-${id}').remove(); hisobYangila()">
        <i class="bi bi-trash"></i>
      </button>
    </div>`;
      document.getElementById('items_container').insertAdjacentHTML('beforeend', html);
      hisobYangila();
    }

    async function saveRetsept() {
      const rid = document.getElementById('retsept_id').value;
      const nomi = document.getElementById('r_nomi').value.trim();
      const tur = document.getElementById('r_tur').value;
      const mah_id = document.getElementById('r_mahsulot_id').value;
      const chiqish = document.getElementById('r_chiqish_soni').value;
      const birlik = document.getElementById('r_birlik').value;
      const izoh = document.getElementById('r_izoh').value;

      if (!nomi) { NHToast.error('Retsept nomini kiriting!'); return; }
      if (!mah_id) { NHToast.error('Mahsulot tanlang!'); return; }

      const items = [];
      document.querySelectorAll('#items_container > div').forEach(row => {
        const mId = row.querySelector('[name="item_mahsulot[]"]').value;
        const soni = row.querySelector('[name="item_soni[]"]').value;
        const bir = row.querySelector('[name="item_birlik[]"]').value;
        if (tur === 'maydalash' && mId) {
            items.push({ mahsulot_id: mId, soni: 0, birlik: bir });
        } else if (mId && soni) {
            items.push({ mahsulot_id: mId, soni, birlik: bir });
        }
      });
      if (!items.length) { NHToast.error("Kamida bitta mahsulot qo'shing!"); return; }

      const btn = document.getElementById('saveRetseptBtn');
      btn.disabled = true; btn.innerHTML = '<span class="im-spinner"></span>';

      const fd = new FormData();
      fd.append('retsept_id', rid); fd.append('nomi', nomi); fd.append('tur', tur);
      fd.append('mahsulot_id', mah_id); fd.append('chiqish_soni', chiqish);
      fd.append('birlik', birlik); fd.append('izoh', izoh);
      fd.append('items', JSON.stringify(items));

      const res = await IMAjax.post(URL_RET_SAVE, fd);
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Saqlash';

      if (res.status === 'ok') {
        NHToast.success(res.msg || 'Saqlandi!');
        NHModal.close('retseptModal');
        setTimeout(() => location.reload(), 900);
      } else {
        NHToast.error(res.msg || 'Xatolik!');
      }
    }

    async function editRetsept(id) {
      const res = await IMAjax.get(URL_RET_SAVE, { get_id: id });
      if (res.status !== 'ok') { NHToast.error('Yuklab bo\'lmadi!'); return; }
      const r = res.data;
      document.getElementById('retsept_id').value     = r.id;
      document.getElementById('r_nomi').value         = r.nomi;
      document.getElementById('r_tur').value          = r.tur;
      document.getElementById('r_chiqish_soni').value = r.chiqish_soni;
      document.getElementById('r_birlik').value       = r.birlik;
      document.getElementById('r_izoh').value         = r.izoh || '';
      document.getElementById('items_container').innerHTML = '';
      document.getElementById('retseptModalTitle').innerHTML = '<i class="bi bi-pencil-fill"></i> Retseptni tahrirlash';
      // Avval tur bo'yicha ko'rinishni sozlaymiz, SO'NG dropdownni
      // tanlangan mahsulot bilan to'ldiramiz (tartib muhim).
      onTurChange();
      fillMahsulotDropdown(r.tur, r.mahsulot_id);
      (r.items || []).forEach(function(it) { addItemRow(it); });
      hisobYangila();
      NHModal.open('retseptModal');
    }

    async function viewItems(id, nomi) {
      document.getElementById('viewModalTitle').innerHTML = `<i class="bi bi-eye-fill"></i> ${nomi}`;
      document.getElementById('viewModalBody').innerHTML = 'Yuklanmoqda...';
      NHModal.open('viewModal');
      const res = await IMAjax.get(URL_RET_SAVE, { get_id: id });
      if (res.status !== 'ok') { document.getElementById('viewModalBody').innerHTML = 'Xatolik!'; return; }
      const r = res.data;
      const isI = r.tur === 'ishlab_chiqarish';
      let html = `<p class="text-muted mb-3">${isI ? '🍳 Ishlab chiqarish' : '✂️ Maydalash'} | <strong>${r.mahsulot_nomi}</strong></p>`;
      html += `<p class="fw-semibold mb-2">${isI ? 'Xomashyolar:' : 'Chiqish mahsulotlari:'}</p>`;
      html += '<ul class="list-group mb-3">';
      (r.items || []).forEach(it => {
        const showVal = isI ? `<strong>${it.soni} ${it.birlik}</strong>` : `<strong class="text-warning fw-normal fs-sm">jarayonda kiritiladi</strong>`;
        html += `<li class="list-group-item d-flex justify-content-between">
      <span>${it.mahsulot_nomi}</span>${showVal}</li>`;
      });
      html += '</ul>';
      if (isI) html += `<div class="im-badge im-badge-success">Chiqish: ${r.chiqish_soni} ${r.birlik}</div>`;
      document.getElementById('viewModalBody').innerHTML = html;
    }

    async function deleteRetsept(id, nomi) {
      const ok = await NHConfirm.delete(nomi);
      if (!ok) return;
      const res = await IMAjax.post(URL_RET_DEL, { id: id });
      if (res.status === 'ok') {
        NHToast.success("O'chirildi!");
        setTimeout(() => location.reload(), 700);
      } else {
        NHToast.error(res.msg || 'Xatolik!');
      }
    }
  </script>
</body>

</html>
