<?php
// ============================================================
//  IMezon — Admin: AI Yordamchi (biznes tahlilchi chat)
// ------------------------------------------------------------
//  Suhbatlar im_ai_suhbat da saqlanadi — yon panelda ro'yxat,
//  sahifa ochilganda oxirgi suhbat tiklanadi. Har foydalanuvchi
//  faqat o'z suhbatlarini ko'radi.
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ai_lib.php';
im_rol_check(['admin']);
$db = new Cyber();

$soz       = im_ai_soz();
$sozlangan = im_ai_sozlangan();
$ishlaydi  = im_ai_ishlaydi();
$model_ogoh = im_ai_model_ogoh();
$filiallar = $db->rows("SELECT id, nomi FROM im_filiallar WHERE status=1 ORDER BY tartib, id");

// Bugungi sarf — AI qancha turayotgani ko'rinib tursin
$bugun_stat = $db->row(
    "SELECT COUNT(*) n, COALESCE(SUM(tokens_in),0) tin, COALESCE(SUM(tokens_out),0) tout
     FROM im_ai_log WHERE DATE(vaqt)=CURDATE()"
);

// Suhbatlar paneli + sahifa ochilganda tiklanadigan suhbat
$suhbatlar = im_ai_suhbatlar($db, $im_user_id, 60);
$joriy_id  = im_ai_suhbat_id_toza($_GET['s'] ?? '');
if ($joriy_id === '' && $suhbatlar) $joriy_id = $suhbatlar[0]['id'];
$joriy = $joriy_id !== '' ? im_ai_suhbat_ol($db, $joriy_id, $im_user_id) : null;

// Model tanlagichi: qisqa ro'yxat + standart + joriy suhbat modeli
$model_royxat = im_ai_model_royxat();
$model_bor = ['claude' => $soz['key_claude'] !== '', 'or' => $soz['key_or'] !== ''];
$mavjud_id = array_column($model_royxat, 'id');
foreach ([$soz['model'], $joriy['model'] ?? ''] as $qm) {
    if ($qm !== '' && !in_array($qm, $mavjud_id, true)) {
        $model_royxat[] = ['id' => $qm, 'nom' => $qm, 'narx' => '', 'izoh' => ''];
        $mavjud_id[] = $qm;
    }
}

// Namuna savollar — foydalanuvchi nimadan boshlashni bilsin.
$namunalar = [
    ['ikon' => 'graph-up-arrow', 'matn' => "Bugun qanday o'tdi? Kecha bilan solishtir"],
    ['ikon' => 'cash-coin',      'matn' => "Shu oy sof foydamiz qancha? Nima yeb ketyapti?"],
    ['ikon' => 'fire',           'matn' => "Qaysi taomlar eng foydali, qaysilari zarar keltiryapti?"],
    ['ikon' => 'box-seam',       'matn' => "Nima tugayapti? Ertaga nima olishim kerak?"],
    ['ikon' => 'people',         'matn' => "Ofitsantlar va oshpazlar bu hafta qanday ishladi?"],
    ['ikon' => 'shield-exclamation', 'matn' => "Shu oyda g'ayrioddiy holatlar bormi?"],
    ['ikon' => 'clock-history',  'matn' => "Qaysi soatlarda gavjum? Xodim jadvalini qanday tuzay?"],
    ['ikon' => 'calculator',     'matn' => "Osh tannarxi qancha? Narxni oshirish kerakmi?"],
];
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>AI Yordamchi | IMezon</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/main.css">
<link rel="stylesheet" href="<?= im_BASE ?>assets/css/dark.css">
<style>
/* Layout: yon panel (suhbatlar) + chat oynasi */
.ai-layout{display:flex;gap:16px;height:calc(100vh - var(--navbar-h) - 32px);min-height:440px}
.ai-tarix{flex:0 0 248px;display:flex;flex-direction:column;background:var(--card);
          border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.ai-tarix-bosh{padding:10px;border-bottom:1px solid var(--border)}
.ai-tarix-royxat{flex:1;overflow-y:auto;padding:6px}
.ai-tarix-bosh-empty{padding:18px 12px;text-align:center;color:var(--muted);font-size:12.5px}
.ai-suhbat{display:flex;align-items:center;gap:4px;padding:8px 8px 8px 10px;border-radius:8px;
           cursor:pointer;font-size:13px;color:var(--text-2);margin-bottom:2px}
.ai-suhbat:hover{background:var(--bg2)}
.ai-suhbat.aktiv{background:var(--bg2);color:var(--text);font-weight:600}
.ai-suhbat .nom{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ai-suhbat .vq{font-size:10.5px;color:var(--muted);flex:0 0 auto}
.ai-suhbat .och{opacity:0;border:none;background:none;color:var(--muted);cursor:pointer;
                padding:1px 4px;font-size:13px;line-height:1;border-radius:5px;flex:0 0 auto}
.ai-suhbat:hover .och{opacity:.7}
.ai-suhbat .och:hover{opacity:1;color:var(--danger);background:var(--card)}

.ai-wrap{flex:1;display:flex;flex-direction:column;min-width:0}
.ai-oqim{flex:1;overflow-y:auto;padding:4px 2px 16px;scroll-behavior:smooth}
.ai-xabar{display:flex;gap:12px;margin-bottom:18px;animation:im-fade .25s ease}
@keyframes im-fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.ai-avatar{flex:0 0 34px;height:34px;border-radius:10px;display:flex;align-items:center;
           justify-content:center;font-size:16px;background:var(--bg2);color:var(--text-2)}
.ai-xabar.men .ai-avatar{background:var(--accent);color:var(--primary)}
.ai-xabar.ai  .ai-avatar{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff}
.ai-tana{flex:1;min-width:0}
.ai-nom{font-size:12px;font-weight:700;color:var(--text-2);margin-bottom:4px}
.ai-matn{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
         padding:12px 14px;font-size:14px;line-height:1.65;color:var(--text);overflow-wrap:anywhere}
.ai-xabar.men .ai-matn{background:var(--bg2);border-color:var(--border-light)}
.ai-matn p{margin:0 0 8px}.ai-matn p:last-child{margin:0}
.ai-matn ul,.ai-matn ol{margin:6px 0 8px;padding-left:20px}
.ai-matn li{margin-bottom:3px}
.ai-matn h3{font-size:14px;font-weight:700;margin:12px 0 6px}
.ai-matn code{background:var(--bg2);padding:1px 5px;border-radius:4px;font-size:12.5px}
.ai-matn table{width:100%;border-collapse:collapse;margin:8px 0;font-size:13px}
.ai-matn th,.ai-matn td{border:1px solid var(--border);padding:6px 9px;text-align:left}
.ai-matn th{background:var(--bg2);font-weight:700}
.ai-matn td:not(:first-child){text-align:right;font-variant-numeric:tabular-nums}
.ai-meta{margin-top:6px;font-size:11px;color:var(--muted);display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.ai-vosita{background:var(--bg2);border:1px solid var(--border);border-radius:20px;
           padding:2px 9px;font-size:11px;color:var(--text-2)}
.ai-ogoh{margin-top:10px;padding:7px 10px;border-radius:8px;font-size:12px;color:var(--text);
         background:rgba(234,179,8,.12);border:1px solid rgba(234,179,8,.4)}
.ai-manba{margin-top:10px;font-size:12px}
.ai-manba summary{cursor:pointer;color:var(--text-2);user-select:none;list-style:none}
.ai-manba summary:hover{color:var(--text)}
.ai-manba summary::-webkit-details-marker{display:none}
.ai-manba-ich{margin-top:8px;display:flex;flex-direction:column;gap:8px}
.ai-manba-item{background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:8px 10px}
.ai-manba-nom{font-size:11.5px;color:var(--text-2);margin-bottom:4px;overflow-wrap:anywhere}
.ai-manba-item pre{margin:0;font-size:11px;line-height:1.5;max-height:230px;overflow:auto;
                   white-space:pre-wrap;word-break:break-word;color:var(--text)}
.ai-kirish{border-top:1px solid var(--border);padding-top:12px;margin-top:4px}
.ai-textarea{width:100%;resize:none;min-height:52px;max-height:160px;font-size:14px}
.ai-chiplar{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.ai-chip{background:var(--card);border:1px solid var(--border);border-radius:22px;
         padding:7px 13px;font-size:13px;color:var(--text-2);cursor:pointer;
         transition:var(--transition);display:inline-flex;align-items:center;gap:6px}
.ai-chip:hover{border-color:var(--accent);color:var(--text);transform:translateY(-1px)}
.ai-bosh{text-align:center;padding:36px 20px;color:var(--muted)}
.ai-bosh .em{font-size:44px;display:block;margin-bottom:10px}
.ai-yozmoqda{display:inline-flex;gap:4px;align-items:center;padding:4px 0}
.ai-yozmoqda i{width:6px;height:6px;border-radius:50%;background:var(--muted);
               animation:ai-dot 1.2s infinite ease-in-out}
.ai-yozmoqda i:nth-child(2){animation-delay:.2s}.ai-yozmoqda i:nth-child(3){animation-delay:.4s}
@keyframes ai-dot{0%,60%,100%{opacity:.25;transform:translateY(0)}30%{opacity:1;transform:translateY(-3px)}}
@media(max-width:860px){
  .ai-layout{flex-direction:column;height:auto}
  .ai-tarix{flex:none;max-height:38vh}
  .ai-wrap{height:calc(100vh - var(--navbar-h) - 340px);min-height:360px}
}
</style>
</head>
<body>
<div class="im-wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="im-main">
  <header class="im-topbar">
    <button class="im-topbar-btn" id="im-sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="im-page-title"><i class="bi bi-stars me-1"></i> AI Yordamchi</div>
    <div class="im-topbar-actions">
      <?php if ($sozlangan): ?>
      <span class="im-badge im-badge-muted" title="Standart model (chatda o'zgartiriladi)">
        standart: <code><?= im_f($soz['model']) ?></code>
      </span>
      <span class="im-badge im-badge-muted" title="Bugungi sarf">
        <?= (int)$bugun_stat['n'] ?> savol ·
        <?= number_format((int)$bugun_stat['tin'] + (int)$bugun_stat['tout'], 0, '.', ' ') ?> token
      </span>
      <?php endif; ?>
      <button class="im-topbar-btn" data-theme-toggle><i class="bi bi-moon-fill"></i></button>
    </div>
  </header>

  <main class="im-content">
    <?php if (!$sozlangan): ?>
    <div class="im-card mb-3" style="border-left:4px solid var(--warning)">
      <div class="im-card-body">
        <h3 class="im-card-title mb-2"><i class="bi bi-key-fill me-1"></i> AI hali sozlanmagan</h3>
        <p class="mb-2" style="color:var(--text-2);font-size:14px">
          Yordamchi ishlashi uchun API kaliti kerak — Claude yoki OpenRouter.
          <a href="<?= im_BASE ?>admin/sozlamalar.php#ai">Sozlamalar → AI Yordamchi</a> bo'limiga kiriting.
        </p>
        <p class="mb-0" style="color:var(--muted);font-size:13px">
          Kalit kelguncha sahifa ishlaydi, lekin savolga javob bermaydi.
        </p>
      </div>
    </div>
    <?php elseif (!$ishlaydi): ?>
    <div class="im-card mb-3" style="border-left:4px solid var(--info)">
      <div class="im-card-body py-2">
        <span style="font-size:13px;color:var(--text-2)">
          <i class="bi bi-info-circle me-1"></i>
          Kalit saqlangan, lekin yordamchi <strong>o'chirilgan</strong>.
          Sinash mumkin — doimiy ishlashi uchun
          <a href="<?= im_BASE ?>admin/sozlamalar.php#ai">Sozlamalardan</a> yoqing.
        </span>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($model_ogoh): ?>
    <div class="im-card mb-3" style="border-left:4px solid var(--danger)">
      <div class="im-card-body py-2">
        <span style="font-size:13px;color:var(--text-2)">
          <i class="bi bi-exclamation-triangle-fill me-1" style="color:var(--danger)"></i>
          <?= im_f($model_ogoh) ?>
          <a href="<?= im_BASE ?>admin/sozlamalar.php#ai">Sozlamalarda tuzating →</a>
        </span>
      </div>
    </div>
    <?php endif; ?>

    <div class="ai-layout">
      <!-- YON PANEL: suhbatlar -->
      <aside class="ai-tarix">
        <div class="ai-tarix-bosh">
          <button class="im-btn im-btn-primary im-btn-sm w-100" id="btn-yangi-suhbat">
            <i class="bi bi-plus-lg"></i> Yangi suhbat
          </button>
        </div>
        <div class="ai-tarix-royxat" id="ai-suhbatlar"></div>
      </aside>

      <!-- CHAT -->
      <div class="im-card ai-wrap">
        <div class="im-card-body d-flex flex-column" style="flex:1;min-height:0">

          <div class="ai-oqim" id="ai-oqim">
            <div class="ai-bosh" id="ai-bosh">
              <span class="em">📊</span>
              <div style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:6px">
                Oshxonangiz haqida nimani bilmoqchisiz?
              </div>
              <div style="font-size:13.5px;max-width:520px;margin:0 auto 22px">
                Sotuv, xarajat, foyda, tannarx, qoldiq, xodimlar — savolni oddiy tilda yozing.
                Javob bazadagi haqiqiy raqamlar asosida beriladi.
              </div>
              <div class="ai-chiplar" style="justify-content:center;max-width:760px;margin:0 auto">
                <?php foreach ($namunalar as $n): ?>
                <button type="button" class="ai-chip" data-savol="<?= im_f($n['matn']) ?>">
                  <i class="bi bi-<?= $n['ikon'] ?>"></i> <?= im_f($n['matn']) ?>
                </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="ai-kirish">
            <form id="ai-form">
              <div class="d-flex gap-2 mb-2 flex-wrap align-items-center">
                <select class="im-select im-select-sm" name="model" id="ai-model" style="max-width:230px">
                  <?php foreach ($model_royxat as $m):
                    $or = strpos($m['id'], '/') !== false;
                    $kbor = $or ? $model_bor['or'] : $model_bor['claude'];
                  ?>
                  <option value="<?= im_f($m['id']) ?>"
                    <?= $m['id'] === ($joriy['model'] ?? $soz['model']) ? 'selected' : '' ?>
                    <?= $kbor ? '' : 'disabled' ?>>
                    <?= im_f($m['nom']) ?><?= $m['narx'] ? ' · '.im_f($m['narx']) : '' ?><?= $kbor ? '' : ' · kalit yo‘q' ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <select class="im-select im-select-sm" name="effort" id="ai-effort" style="max-width:150px">
                  <?php foreach (['low'=>'Past','medium'=>"O'rta",'high'=>'Yuqori','xhigh'=>'Juda yuqori','max'=>'Maksimal'] as $ev=>$el): ?>
                  <option value="<?= $ev ?>" <?= $ev === ($joriy['effort'] ?? $soz['effort']) ? 'selected' : '' ?>>Chuqurlik: <?= $el ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (count($filiallar) > 1): ?>
                <select class="im-select im-select-sm" name="filial_id" style="max-width:160px">
                  <option value="0">Barcha filiallar</option>
                  <?php foreach ($filiallar as $f): ?>
                  <option value="<?= (int)$f['id'] ?>" <?= $im_filial_id == $f['id'] ? 'selected' : '' ?>>
                    <?= im_f($f['nomi']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="hidden" name="filial_id" value="0">
                <?php endif; ?>
                <span id="ai-model-narx" class="fs-xs" style="color:var(--muted)"></span>
              </div>

              <div class="d-flex gap-2 align-items-end">
                <textarea class="im-textarea ai-textarea" name="savol" id="ai-savol"
                          placeholder="Masalan: shu hafta qaysi kun eng ko'p daromad keltirdi va nega?"
                          maxlength="2000"></textarea>

                <button type="submit" class="im-btn im-btn-primary" id="ai-yubor" style="height:52px">
                  <i class="bi bi-send-fill"></i>
                </button>
              </div>
              <div style="font-size:11.5px;color:var(--muted);margin-top:6px">
                <i class="bi bi-lightbulb"></i>
                Enter — yuborish, Shift+Enter — yangi qator.
                Model tanlovi shu suhbatga bog'lanadi.
                <?php if (!$sozlangan): ?>
                <span style="color:var(--danger)"> AI sozlanmagan — <a href="<?= im_BASE ?>admin/sozlamalar.php#ai">Sozlamalar</a>.</span>
                <?php endif; ?>
                Har savol-javob <code>im_ai_log</code> ga yoziladi.
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>
  </main>
</div>
</div>

<script>
window.im_BASE = "<?= defined('im_BASE') ? im_BASE : '/' ?>";
window.im_csrf = "<?= defined('IM_CSRF') ? IM_CSRF : '' ?>";
window.im_ai_suhbatlar = <?= json_encode(array_map(function ($s) {
    return ['id' => $s['id'], 'sarlavha' => $s['sarlavha'] !== '' ? $s['sarlavha'] : 'Suhbat',
            'soni' => (int)$s['soni'], 'model' => $s['model'], 'vaqt' => $s['updated_at']];
}, $suhbatlar), JSON_UNESCAPED_UNICODE) ?>;
window.im_ai_joriy = <?= $joriy
    ? json_encode(['id' => $joriy['id'], 'model' => $joriy['model'], 'effort' => $joriy['effort'],
                   'xabarlar' => $joriy['xabarlar']], JSON_UNESCAPED_UNICODE)
    : 'null' ?>;
window.im_ai_std = <?= json_encode(['model' => $soz['model'], 'effort' => $soz['effort']], JSON_UNESCAPED_UNICODE) ?>;
window.im_ai_model_narx = <?= json_encode(array_column($model_royxat, 'izoh', 'id'), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= im_BASE ?>assets/js/main.js?v=<?= rawurlencode(im_VERSION) ?>"></script>
<script>
(function(){
  const oqim  = document.getElementById('ai-oqim');
  const bosh  = document.getElementById('ai-bosh');
  const form  = document.getElementById('ai-form');
  const ta    = document.getElementById('ai-savol');
  const btn   = document.getElementById('ai-yubor');
  const panel = document.getElementById('ai-suhbatlar');
  const modelSel  = document.getElementById('ai-model');
  const effortSel = document.getElementById('ai-effort');
  const narxSpan  = document.getElementById('ai-model-narx');
  const std = window.im_ai_std || {model:'', effort:'medium'};
  const modelIzoh = window.im_ai_model_narx || {};
  let band = false;
  let suhbatId = '';
  let suhbatlar = Array.isArray(window.im_ai_suhbatlar) ? window.im_ai_suhbatlar : [];

  // Model ID → qisqa nom (ro'yxatdagi <option> matnidan)
  function modelNomi(id){
    if (!id) return '';
    const o = modelSel && [...modelSel.options].find(x => x.value === id);
    return o ? o.textContent.split(' · ')[0].trim() : id;
  }
  function narxKorsat(){
    if (!narxSpan || !modelSel) return;
    const iz = modelIzoh[modelSel.value] || '';
    narxSpan.textContent = iz ? '— ' + iz : '';
  }
  if (modelSel)  modelSel.addEventListener('change', narxKorsat);
  narxKorsat();

  // ─── Markdown → HTML (model javobi ISHONCHSIZ: avval escape) ──
  function esc(s){
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function inline(s){
    return s.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
            .replace(/`([^`]+?)`/g,'<code>$1</code>')
            .replace(/(^|[\s(])\*([^*\n]+?)\*/g,'$1<em>$2</em>');
  }
  function md(src){
    const qatorlar = esc(src).split('\n');
    let out = '', ro = null, jadval = null;
    const yopRo = () => { if(ro){ out += '</'+ro+'>'; ro = null; } };
    const yopJadval = () => {
      if(!jadval) return;
      out += '<table><thead><tr>' + jadval.bosh.map(c=>'<th>'+inline(c)+'</th>').join('')
           + '</tr></thead><tbody>'
           + jadval.tan.map(r=>'<tr>'+r.map(c=>'<td>'+inline(c)+'</td>').join('')+'</tr>').join('')
           + '</tbody></table>';
      jadval = null;
    };
    const katak = l => l.replace(/^\||\|$/g,'').split('|').map(c=>c.trim());
    for (let i=0;i<qatorlar.length;i++){
      const l = qatorlar[i];
      const t = l.trim();
      if (t.startsWith('|') && t.endsWith('|')) {
        const keyingi = (qatorlar[i+1]||'').trim();
        if (!jadval && /^\|[\s:|-]+\|$/.test(keyingi)) {
          yopRo(); jadval = {bosh: katak(t), tan: []}; i++; continue;
        }
        if (jadval) { jadval.tan.push(katak(t)); continue; }
      } else if (jadval) { yopJadval(); }
      if (t === '') { yopRo(); continue; }
      const h = t.match(/^#{1,6}\s+(.*)$/);
      if (h) { yopRo(); out += '<h3>' + inline(h[1]) + '</h3>'; continue; }
      const ul = t.match(/^[-*•]\s+(.*)$/);
      if (ul) {
        if (ro !== 'ul') { yopRo(); out += '<ul>'; ro = 'ul'; }
        out += '<li>' + inline(ul[1]) + '</li>'; continue;
      }
      const ol = t.match(/^\d+[.)]\s+(.*)$/);
      if (ol) {
        if (ro !== 'ol') { yopRo(); out += '<ol>'; ro = 'ol'; }
        out += '<li>' + inline(ol[1]) + '</li>'; continue;
      }
      yopRo();
      out += '<p>' + inline(t) + '</p>';
    }
    yopRo(); yopJadval();
    return out;
  }

  // ─── Xabar qo'shish ──────────────────────────────────────
  function xabar(kim, html, meta){
    if (bosh) bosh.style.display = 'none';
    const d = document.createElement('div');
    d.className = 'ai-xabar ' + kim;
    d.innerHTML =
      '<div class="ai-avatar">' + (kim === 'men' ? '<i class="bi bi-person-fill"></i>'
                                                 : '<i class="bi bi-stars"></i>') + '</div>' +
      '<div class="ai-tana">' +
        '<div class="ai-nom">' + (kim === 'men' ? 'Siz' : 'AI Yordamchi') + '</div>' +
        '<div class="ai-matn">' + html + '</div>' +
        (meta ? '<div class="ai-meta">' + meta + '</div>' : '') +
      '</div>';
    oqim.appendChild(d);
    oqim.scrollTop = oqim.scrollHeight;
    return d;
  }
  function metaHtml(vositalar, ms, tin, tout, model){
    const v = (vositalar || []).filter((x,i,a)=>a.indexOf(x)===i)
                .map(n => '<span class="ai-vosita">' + esc(n) + '</span>').join('');
    const tk = (tin||0) + (tout||0);
    const mm = model ? esc(modelNomi(model)) + ' · ' : '';
    return v + '<span>' + mm + (Math.round((ms||0)/100)/10) + ' s · ' + tk + ' token</span>';
  }
  // AI javob HTML: matn + (topilmagan raqamlar) + (manba paneli — faqat jonli)
  function aiJavobHtml(javob, natijalar, ogoh){
    let h = md(javob || '');
    if (ogoh && ogoh.length) {
      h += '<div class="ai-ogoh"><i class="bi bi-exclamation-triangle-fill"></i> ' +
           'Bu raqam(lar) vositada to\'g\'ridan-to\'g\'ri yo\'q — hisoblab chiqarilgan ' +
           'yoki xato bo\'lishi mumkin, tekshiring: <b>' +
           ogoh.map(esc).join('</b>, <b>') + '</b></div>';
    }
    if (natijalar && natijalar.length) {
      h += '<details class="ai-manba"><summary>🔍 Manba raqamlar — ' + natijalar.length +
           ' ta vosita chaqiruvi</summary><div class="ai-manba-ich">';
      natijalar.forEach(x => {
        h += '<div class="ai-manba-item"><div class="ai-manba-nom"><b>' + esc(x.nom || '') + '</b> ' +
             '<code>' + esc(JSON.stringify(x.args || {})) + '</code></div>' +
             '<pre>' + esc(JSON.stringify(x.natija, null, 1)) + '</pre></div>';
      });
      h += '</div></details>';
    }
    return h;
  }

  function ekranTozala(){ oqim.querySelectorAll('.ai-xabar').forEach(x => x.remove()); }
  function boshKorsat(k){ if (bosh) bosh.style.display = k ? '' : 'none'; }
  // Ba'zi proksi/rewrite holatlarida replaceState SecurityError beradi — yutamiz
  function urlYangila(q){
    try { if (history.replaceState) history.replaceState(null, '', q); } catch(e){}
  }

  // ─── Saqlangan suhbatni chizish ─────────────────────────
  function suhbatChiz(xabarlar){
    ekranTozala();
    (xabarlar || []).forEach(x => {
      if (x.rol === 'user') {
        xabar('men', md(x.matn || ''));
      } else if (x.xato) {
        xabar('ai', '<div style="color:var(--danger)"><i class="bi bi-exclamation-triangle-fill"></i> '
                    + esc(x.xato) + '</div>');
      } else {
        xabar('ai', aiJavobHtml(x.matn, null, x.ogoh),
              metaHtml(x.vositalar, x.ms, x.tin, x.tout, x.model));
      }
    });
    boshKorsat((xabarlar || []).length === 0);
  }
  // Tanlagichlarni suhbat qiymatiga (yoki standartga) qo'yish
  function tanlovQoy(model, effort){
    if (modelSel) {
      const m = model || std.model;
      if (m) {
        if (![...modelSel.options].some(o => o.value === m)) {
          // Ro'yxatda yo'q model (eski suhbat) — vaqtincha qo'shamiz
          const opt = document.createElement('option');
          opt.value = m; opt.textContent = m + ' (suhbat modeli)';
          modelSel.appendChild(opt);
        }
        modelSel.value = m;
      }
    }
    if (effortSel) effortSel.value = effort || std.effort || 'medium';
    narxKorsat();
  }

  // ─── Yon panel ──────────────────────────────────────────
  function nisbiyVaqt(s){
    if (!s) return '';
    const t = new Date(s.replace(' ','T'));
    const d = Math.floor((Date.now() - t.getTime())/1000);
    if (d < 60) return 'hozir';
    if (d < 3600) return Math.floor(d/60) + ' daq';
    if (d < 86400) return Math.floor(d/3600) + ' soat';
    if (d < 604800) return Math.floor(d/86400) + ' kun';
    return t.toLocaleDateString('uz');
  }
  function panelChiz(){
    if (!suhbatlar.length){
      panel.innerHTML = '<div class="ai-tarix-bosh-empty">Hali suhbat yo\'q.<br>Savol bering — bu yerda saqlanadi.</div>';
      return;
    }
    panel.innerHTML = suhbatlar.map(s => {
      const mn = s.model ? modelNomi(s.model) : '';
      return '<div class="ai-suhbat' + (s.id === suhbatId ? ' aktiv' : '') + '" data-id="' + s.id + '">' +
        '<span class="nom" title="' + esc(s.sarlavha) + (mn ? ' · ' + esc(mn) : '') + '">' + esc(s.sarlavha) + '</span>' +
        '<span class="vq">' + nisbiyVaqt(s.vaqt) + '</span>' +
        '<button class="och" data-och="' + s.id + '" title="O\'chirish">&times;</button>' +
      '</div>';
    }).join('');
  }
  async function panelYangila(){
    try {
      const r = await IMAjax.post(window.im_BASE + 'admin/ajax/ai-suhbatlar.php', {amal:'royxat'});
      if (r.status === 'ok'){ suhbatlar = r.suhbatlar || []; panelChiz(); }
    } catch(e){}
  }

  async function suhbatOch(id){
    if (band) return;
    try {
      const r = await IMAjax.post(window.im_BASE + 'admin/ajax/ai-suhbatlar.php', {amal:'ochish', id});
      if (r.status !== 'ok'){ NHToast.error(r.msg || 'Ochilmadi'); return; }
      suhbatId = r.id;
      suhbatChiz(r.xabarlar || []);
      tanlovQoy(r.model, r.effort);
      panelChiz();
      urlYangila('?s=' + id);
    } catch(e){ NHToast.error('Tarmoq xatosi'); }
  }
  async function suhbatOchir(id){
    const ok = await NHConfirm.show({
      variant: 'danger',
      title: "Suhbatni o'chirish",
      text: "Bu suhbat va uning barcha xabarlari o'chiriladi.",
      sub: "Bu amalni qaytarib bo'lmaydi.",
      confirmText: "Suhbatni o'chirish"
    });
    if (!ok) return;
    await IMAjax.post(window.im_BASE + 'admin/ajax/ai-suhbatlar.php', {amal:'ochir', id});
    suhbatlar = suhbatlar.filter(s => s.id !== id);
    if (id === suhbatId) yangiSuhbat();
    else panelChiz();
    NHToast.success('O\'chirildi');
  }
  function yangiSuhbat(){
    suhbatId = '';
    ekranTozala();
    boshKorsat(true);
    tanlovQoy(std.model, std.effort);
    panelChiz();
    urlYangila(location.pathname);
    ta.focus();
  }

  // ─── Yuborish ───────────────────────────────────────────
  async function yubor(savol){
    if (band || !savol.trim()) return;
    band = true;
    btn.disabled = true;
    btn.innerHTML = '<span class="im-spinner"></span>';

    xabar('men', md(savol));
    ta.value = '';
    ta.style.height = 'auto';

    const kutish = xabar('ai',
      '<span class="ai-yozmoqda"><i></i><i></i><i></i></span>' +
      '<span style="color:var(--muted);font-size:13px;margin-left:8px">raqamlarni yig\'yapman…</span>');

    const fd = new FormData();
    fd.append('savol', savol);
    fd.append('suhbat_id', suhbatId);
    fd.append('filial_id', form.filial_id ? form.filial_id.value : 0);
    fd.append('model', modelSel ? modelSel.value : '');
    fd.append('effort', effortSel ? effortSel.value : '');

    let res;
    try { res = await IMAjax.post(window.im_BASE + 'admin/ajax/ai-chat.php', fd); }
    catch(e){ res = {status:'error', msg:'Tarmoq xatosi'}; }
    kutish.remove();

    if (res.status === 'ok') {
      xabar('ai', aiJavobHtml(res.javob, res.natijalar, res.ogoh),
            metaHtml(res.vositalar, res.ms, (res.tokens||{}).in, (res.tokens||{}).out, res.model));
    } else {
      xabar('ai', '<div style="color:var(--danger)"><i class="bi bi-exclamation-triangle-fill"></i> ' +
                  esc(res.msg || 'Nomaʼlum xatolik') +
                  (res.model ? ' <span style="color:var(--muted)">(' + esc(modelNomi(res.model)) + ')</span>' : '') +
                  '</div>');
    }

    // Suhbat id — yangi bo'lsa qabul qilamiz va panelni yangilaymiz
    if (res.suhbat_id) {
      const avval = suhbatId;
      suhbatId = res.suhbat_id;
      if (res.yangi || avval !== suhbatId || !suhbatlar.some(s => s.id === suhbatId)) {
        await panelYangila();
      } else {
        // sarlavha/vaqtni yangilash uchun ham panelni tozalab qo'yamiz
        panelChiz();
      }
      urlYangila('?s=' + suhbatId);
    }

    band = false;
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill"></i>';
    ta.focus();
  }

  form.addEventListener('submit', e => { e.preventDefault(); yubor(ta.value); });
  ta.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); yubor(ta.value); }
  });
  ta.addEventListener('input', () => {
    ta.style.height = 'auto';
    ta.style.height = Math.min(160, ta.scrollHeight) + 'px';
  });
  document.querySelectorAll('.ai-chip').forEach(c => {
    c.addEventListener('click', () => yubor(c.dataset.savol));
  });

  panel.addEventListener('click', e => {
    const och = e.target.closest('[data-och]');
    if (och) { e.stopPropagation(); suhbatOchir(och.dataset.och); return; }
    const row = e.target.closest('.ai-suhbat');
    if (row && row.dataset.id !== suhbatId) suhbatOch(row.dataset.id);
  });
  document.getElementById('btn-yangi-suhbat').addEventListener('click', yangiSuhbat);

  // ─── Boshlang'ich holat ─────────────────────────────────
  panelChiz();
  if (window.im_ai_joriy && window.im_ai_joriy.id) {
    suhbatId = window.im_ai_joriy.id;
    suhbatChiz(window.im_ai_joriy.xabarlar || []);
    tanlovQoy(window.im_ai_joriy.model, window.im_ai_joriy.effort);
    panelChiz();
    if (!location.search) urlYangila('?s=' + suhbatId);
  }
  ta.focus();
})();
</script>
</body>
</html>
