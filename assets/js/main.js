/* ============================================================
   IMezon — Global JavaScript
   Versiya: 1.0 | 2026-04-02
   ============================================================ */

'use strict';

// ─── CSRF: fetch o'ramchisi ─────────────────────────────────
// Loyihada POST uch xil usulda yuboriladi: IMAjax.post (FormData),
// xom fetch (URLSearchParams) va JSON.stringify. Har birini alohida
// tahrirlash o'rniga fetch ning O'ZINI bir marta o'raymiz — shunda
// 85 ta chaqiruv joyi ham, kelajakda yoziladigani ham qamrab olinadi.
//
// Belgi sarlavhada ketadi (tanada emas), chunki tana turi har xil.
// Faqat SHU sayt ichidagi POST'ga qo'shiladi — begona domenga
// yuborilgan so'rovga belgi ilashib ketmasligi kerak.
//
// DIQQAT: bu o'ramchi faqat fetch() ni qamrab oladi. XMLHttpRequest
// o'ralmagan — hozir loyihada bitta ham XHR yo'q, lekin kelajakda
// yozilsa, so'rov 419 bilan qaytadi. U holda sarlavhani qo'lda
// qo'shing:  xhr.setRequestHeader('X-IM-CSRF', window.im_csrf)
(function () {
  const asl = window.fetch;
  if (!asl) return;

  window.fetch = function (kirish, sozlama) {
    sozlama = sozlama || {};
    const usul = String(sozlama.method || (kirish && kirish.method) || 'GET').toUpperCase();

    if (usul !== 'GET' && usul !== 'HEAD' && window.im_csrf) {
      let manzil = typeof kirish === 'string' ? kirish : (kirish && kirish.url) || '';
      let ozimizniki = true;
      try {
        // Nisbiy manzil — har doim o'zimizniki
        if (/^[a-z]+:\/\//i.test(manzil)) {
          ozimizniki = new URL(manzil).origin === window.location.origin;
        }
      } catch (e) { ozimizniki = false; }

      if (ozimizniki) {
        const h = new Headers(sozlama.headers || (kirish && kirish.headers) || {});
        if (!h.has('X-IM-CSRF')) h.set('X-IM-CSRF', window.im_csrf);
        sozlama = Object.assign({}, sozlama, { headers: h });
      }
    }
    return asl.call(this, kirish, sozlama);
  };
})();

// ─── XSS: bazadan kelgan matnni innerHTML ga qo'yishdan oldin ─
// escape qilish — YAGONA MANBA ──────────────────────────────
// Mahsulot nomi, mijoz ismi, izoh kabi maydonlar xodimlar tomonidan
// erkin matn sifatida kiritiladi va bazada XOM saqlanadi (SQL uchun
// escape qilingan, HTML uchun EMAS). Ular AJAX orqali JSON qilib
// qaytariladi (im_json() da HTML-safe emas) va ko'p sahifada
// template literal ichida to'g'ridan-to'g'ri innerHTML ga qo'yiladi.
// Shu tufayli mahsulot nomiga "<img src=x onerror=...>" kabi matn
// kiritilsa, uni ko'rgan BOSHQA xodimning brauzerida skript ishga
// tushadi (saqlanuvchi XSS). Har bir joyni alohida tuzatish o'rniga —
// loyihadagi boshqa "yagona manba" funksiyalari kabi — BITTA joyga
// qo'ydik: bazadan kelgan matnni innerHTML/template literal ichiga
// qo'yishdan OLDIN har doim shuni chaqiring: im_esc(qiymat)
function im_esc(s) {
  if (s === null || s === undefined) return '';
  return String(s).replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}
window.im_esc = im_esc;

// Ba'zi joylarda bazadan kelgan matn onclick="foo('${nomi}')" kabi —
// ya'ni HTML atributi ICHIDAGI bir tirnoqli JS satri ichiga qo'yiladi.
// Bunday joyda YOLG'IZ im_esc() YETARLI EMAS: HTML entity dekodlash JS
// parser ishlashidan OLDIN bo'ladi, ya'ni im_esc("'") -> "&#39;" baribir
// JS satrini yopib qo'yadigan haqiqiy tirnoqqa qaytadi. Shu context uchun
// ALOHIDA helper: avval JS-satr chegarasini (backslash, bir tirnoq),
// so'ng HTML-atribut chegarasini (qo'sh tirnoq) tozalaydi.
function im_esc_attr_js(s) {
  if (s === null || s === undefined) return '';
  return String(s)
    .replace(/\\/g, '\\\\')
    .replace(/'/g, "\\'")
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
window.im_esc_attr_js = im_esc_attr_js;

// ─── To'lov turi label'lari — YAGONA MANBA (JS tomoni) ──────
// PHP tomonida im_tt_label()/im_tt_nomi() ga mos keladi (config.php).
// Yangi to'lov turi label matnini o'zgartirish kerak bo'lsa — FAQAT shu yerda.
const IM_TT_LABELS = {
  naqd:  '💵 Naqd',
  karta: '💳 Kart-karta',
  bank:  "🏦 Bank (O'tkazma, Terminal)",
  usd:   '🪙 USD',
  qarz:  '📋 Qarzga',
};
const IM_TT_LABELS_SHORT = {
  naqd:  '💵 Naqd',
  karta: '💳 Kart-karta',
  bank:  '🏦 Bank',
  usd:   '🪙 USD',
  qarz:  '📋 Qarzga',
};

// ─── Dark mode ─────────────────────────────────────────────
const NHTheme = {
  KEY: 'im_theme',

  init() {
    const saved = localStorage.getItem(this.KEY) || 'light';
    this.set(saved, false);
  },

  toggle() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    this.set(current === 'dark' ? 'light' : 'dark');
  },

  set(theme, save = true) {
    document.documentElement.setAttribute('data-theme', theme);
    if (save) localStorage.setItem(this.KEY, theme);

    // Icon yangilash
    const btns = document.querySelectorAll('[data-theme-toggle]');
    btns.forEach(btn => {
      const icon = btn.querySelector('.bi');
      if (icon) {
        icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
      }
    });
  }
};

// ─── Toast ─────────────────────────────────────────────────
const NHToast = {
  container: null,

  init() {
    this.container = document.getElementById('im-toast-container');
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.id = 'im-toast-container';
      document.body.appendChild(this.container);
    }
  },

  show(message, type = 'success', duration = 3500) {
    if (!this.container) this.init();

    const icons = {
      success: 'bi-check-circle-fill',
      error:   'bi-x-circle-fill',
      warning: 'bi-exclamation-circle-fill',
      info:    'bi-info-circle-fill'
    };

    // DIQQAT: message ko'p joyda bazadan kelgan matnni o'z ichiga oladi
    // (mahsulot nomi, mijoz ismi...) — shuning uchun innerHTML EMAS,
    // DOM API + textContent ishlatiladi. Xato xabarlarida HTML kerak
    // bo'lgan joy yo'q (tekshirildi), shuning uchun bu almashtirish
    // hech qanday chaqiruvni buzmaydi.
    const toast = document.createElement('div');
    toast.className = `im-toast ${type}`;

    const iconSpan = document.createElement('span');
    iconSpan.className = `im-toast-icon bi ${icons[type] || icons.info}`;
    iconSpan.style.color = `var(--${type === 'error' ? 'danger' : type})`;

    const msgSpan = document.createElement('span');
    msgSpan.className = 'im-toast-msg';
    msgSpan.textContent = message;

    toast.appendChild(iconSpan);
    toast.appendChild(msgSpan);

    this.container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('im-toast-out');
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  success(msg, d) { this.show(msg, 'success', d); },
  error(msg, d)   { this.show(msg, 'error', d); },
  warning(msg, d) { this.show(msg, 'warning', d); },
  info(msg, d)    { this.show(msg, 'info', d); }
};

// ─── Modal ─────────────────────────────────────────────────
const NHModal = {
  stack: [],

  open(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.add('show');
    this.stack.push(id);
    document.body.style.overflow = 'hidden';

    // ESC bilan yopish
    this._escHandler = (e) => { if (e.key === 'Escape') this.close(id); };
    document.addEventListener('keydown', this._escHandler);
    return overlay;
  },

  close(id) {
    const overlay = id
      ? document.getElementById(id)
      : (this.stack.length ? document.getElementById(this.stack[this.stack.length - 1]) : null);

    if (!overlay) return;
    overlay.classList.remove('show');

    const idx = this.stack.indexOf(overlay.id);
    if (idx > -1) this.stack.splice(idx, 1);

    if (this.stack.length === 0) document.body.style.overflow = '';
    document.removeEventListener('keydown', this._escHandler);
  },

  closeAll() {
    document.querySelectorAll('.im-overlay.show').forEach(o => {
      o.classList.remove('show');
    });
    this.stack = [];
    document.body.style.overflow = '';
  },

  // Click outside to close
  interceptOutside(overlayId) {
    const overlay = document.getElementById(overlayId);
    if (!overlay) return;
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) this.close(overlayId);
    });
  }
};

// ─── Confirm Dialog ─────────────────────────────────────────
const NHConfirm = {
  _resolve: null,
  _restoreFocus: null,
  _closeOnBackdrop: true,
  _stylesPromise: null,

  _variants: {
    primary: {
      label: 'Tasdiqlash',
      icon: 'bi-question-lg',
      iconColor: 'var(--info)',
      btnClass: 'im-btn-primary',
      btnIcon: 'bi-check-lg',
      confirmText: 'Tasdiqlash',
      focus: 'confirm'
    },
    warning: {
      label: 'Diqqat',
      icon: 'bi-exclamation-triangle-fill',
      iconColor: 'var(--warning)',
      btnClass: 'im-btn-warning',
      btnIcon: 'bi-arrow-right',
      confirmText: 'Davom etish',
      focus: 'cancel'
    },
    danger: {
      label: 'Xavfli amal',
      icon: 'bi-trash3-fill',
      iconColor: 'var(--danger)',
      btnClass: 'im-btn-danger',
      btnIcon: 'bi-trash3-fill',
      confirmText: "O'chirish",
      focus: 'cancel'
    },
    success: {
      label: 'Amalni yakunlash',
      icon: 'bi-check-circle-fill',
      iconColor: 'var(--success)',
      btnClass: 'im-btn-success',
      btnIcon: 'bi-check-lg',
      confirmText: 'Tasdiqlash',
      focus: 'confirm'
    }
  },

  _finish(value) {
    if (!this._resolve) return;
    NHModal.close('im-confirm-overlay');
    const resolve = this._resolve;
    this._resolve = null;
    const restoreFocus = this._restoreFocus;
    this._restoreFocus = null;
    resolve(value);
    if (restoreFocus && typeof restoreFocus.focus === 'function' && document.contains(restoreFocus)) {
      setTimeout(() => restoreFocus.focus({ preventScroll: true }), 0);
    }
  },

  _ensureStyles() {
    if (this._stylesPromise) return this._stylesPromise;

    const existing = document.getElementById('im-confirm-styles');
    if (existing && existing.sheet) {
      this._stylesPromise = Promise.resolve();
      return this._stylesPromise;
    }

    this._stylesPromise = new Promise(resolve => {
      const link = existing || document.createElement('link');
      let settled = false;
      const done = () => {
        if (settled) return;
        settled = true;
        resolve();
      };

      link.id = 'im-confirm-styles';
      link.rel = 'stylesheet';
      link.addEventListener('load', done, { once: true });
      link.addEventListener('error', done, { once: true });

      if (!existing) {
        const mainScript = document.querySelector('script[src*="assets/js/main.js"]');
        if (mainScript?.src) {
          link.href = new URL('../css/confirm.css?v=20260909-2', mainScript.src).href;
        } else {
          const base = String(window.im_BASE || '/').replace(/\/?$/, '/');
          link.href = `${base}assets/css/confirm.css?v=20260909-2`;
        }
        document.head.appendChild(link);
      }

      // Lokal CSS javobi buzilgan taqdirda dialog umuman ochilmay qolmasin.
      setTimeout(done, 2000);
    });
    return this._stylesPromise;
  },

  init() {
    if (document.getElementById('im-confirm-overlay')) return;
    const tpl = `
      <div class="im-overlay" id="im-confirm-overlay">
        <div class="im-modal im-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="im-confirm-title" aria-describedby="im-confirm-text im-confirm-sub" tabindex="-1">
          <span class="im-confirm-accent" aria-hidden="true"></span>
          <div class="im-confirm-content">
            <div class="im-confirm-icon-wrap" aria-hidden="true">
              <i class="bi bi-question-lg" id="im-confirm-icon"></i>
            </div>
            <div class="im-confirm-copy">
              <span class="im-confirm-label" id="im-confirm-label">Tasdiqlash</span>
              <h3 id="im-confirm-title">Amalni tasdiqlang</h3>
              <p id="im-confirm-text">Davom etishni xohlaysizmi?</p>
              <p id="im-confirm-sub" hidden></p>
            </div>
          </div>
          <div class="im-confirm-footer">
            <button class="im-btn im-btn-outline im-confirm-btn" id="im-confirm-no" type="button">
              <i class="bi bi-x-lg" aria-hidden="true"></i><span>Bekor qilish</span>
            </button>
            <button class="im-btn im-btn-primary im-confirm-btn" id="im-confirm-yes" type="button">
              <i class="bi bi-check-lg" id="im-confirm-btn-icon" aria-hidden="true"></i><span id="im-confirm-btn-text">Tasdiqlash</span>
            </button>
          </div>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', tpl);

    const overlay = document.getElementById('im-confirm-overlay');
    document.getElementById('im-confirm-no').addEventListener('click', () => this._finish(false));
    document.getElementById('im-confirm-yes').addEventListener('click', () => this._finish(true));
    overlay.addEventListener('click', e => {
      if (e.target === overlay && this._closeOnBackdrop) this._finish(false);
    });
    // Capture fazasi boshqa sahifalardagi umumiy Escape handlerlaridan oldin
    // ishlaydi; aks holda modal yopilib, Promise javobsiz qolishi mumkin.
    document.addEventListener('keydown', e => {
      if (!overlay.classList.contains('show')) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopImmediatePropagation();
        this._finish(false);
        return;
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        e.stopImmediatePropagation();
        // Fokus bekor qilish tugmasida bo'lsa Enter ham xavfsiz ravishda
        // bekor qiladi; boshqa holatda asosiy amalni tasdiqlaydi.
        this._finish(document.activeElement !== document.getElementById('im-confirm-no'));
        return;
      }
      if (e.key === 'Tab') {
        const buttons = [
          document.getElementById('im-confirm-no'),
          document.getElementById('im-confirm-yes')
        ].filter(button => !button.disabled);
        const current = buttons.indexOf(document.activeElement);
        const next = e.shiftKey
          ? (current <= 0 ? buttons.length - 1 : current - 1)
          : (current === buttons.length - 1 ? 0 : current + 1);
        e.preventDefault();
        buttons[next].focus();
      }
    }, true);
  },

  async show(opts = {}) {
    await this._ensureStyles();
    this.init();

    let variantName = this._variants[opts.variant] ? opts.variant : '';
    // Eski NHConfirm.show chaqiruvlari btnClass bergan bo'lsa ham yangi
    // vizual tilga avtomatik moslansin.
    if (!variantName && typeof opts.btnClass === 'string') {
      if (opts.btnClass.includes('danger')) variantName = 'danger';
      else if (opts.btnClass.includes('warning')) variantName = 'warning';
      else if (opts.btnClass.includes('success')) variantName = 'success';
    }
    if (!variantName) variantName = 'primary';
    const variant = this._variants[variantName];
    const overlay = document.getElementById('im-confirm-overlay');
    const dialog = overlay.querySelector('.im-confirm-dialog');
    dialog.dataset.variant = variantName;
    document.getElementById('im-confirm-label').textContent = opts.label || variant.label;
    document.getElementById('im-confirm-title').textContent = opts.title || 'Amalni tasdiqlang';
    document.getElementById('im-confirm-text').textContent = opts.text || 'Davom etishni xohlaysizmi?';

    const sub = document.getElementById('im-confirm-sub');
    sub.textContent = opts.sub || '';
    sub.hidden = !opts.sub;

    const yesBtn = document.getElementById('im-confirm-yes');
    const noBtn = document.getElementById('im-confirm-no');
    document.getElementById('im-confirm-btn-text').textContent = opts.confirmText || opts.btnText || variant.confirmText;
    document.getElementById('im-confirm-btn-icon').className = `bi ${opts.btnIcon || variant.btnIcon}`;
    noBtn.querySelector('span').textContent = opts.cancelText || 'Bekor qilish';
    yesBtn.className = `im-btn ${opts.btnClass || variant.btnClass} im-confirm-btn`;

    const icon = document.getElementById('im-confirm-icon');
    icon.className = `bi ${opts.icon || variant.icon}`;
    icon.style.color = opts.iconColor || variant.iconColor;
    this._closeOnBackdrop = opts.closeOnBackdrop !== false;

    // Oldingi dialog hali javobsiz qolgan bo'lsa, uning Promise'ini osilib
    // qolishiga yo'l qo'ymaymiz.
    if (this._resolve) {
      const oldResolve = this._resolve;
      this._resolve = null;
      oldResolve(false);
    }

    return new Promise(resolve => {
      this._resolve = resolve;
      this._restoreFocus = document.activeElement;
      NHModal.open('im-confirm-overlay');
      const focusTarget = (opts.focus || variant.focus) === 'cancel' ? noBtn : yesBtn;
      setTimeout(() => focusTarget.focus({ preventScroll: true }), 0);
    });
  },

  ask(text, sub = '') {
    return this.show({
      variant: 'primary',
      title: 'Amalni tasdiqlang',
      text: text,
      sub: sub
    });
  },

  delete(name = '') {
    return this.show({
      variant: 'danger',
      title: "O'chirishni tasdiqlang",
      text:  name ? `"${name}" o'chirilsinmi?` : "Bu elementi o'chirishni tasdiqlaysizmi?",
      sub:   "Bu amalni qaytarib bo'lmaydi."
    });
  },

  warning(title, text, sub = '') {
    return this.show({ variant: 'warning', title, text, sub });
  }
};

// ─── Ajax helper ────────────────────────────────────────────
const IMAjax = {
  /**
   * POST so'rov yuborish
   * @param {string} url
   * @param {Object|FormData} data
   * @returns {Promise<{status, msg, data}>}
   */
  post(url, data = {}) {
    let body;
    if (data instanceof FormData) {
      body = data;
    } else {
      body = new FormData();
      Object.entries(data).forEach(([k, v]) => body.append(k, v));
    }

    return fetch(url, {
      method: 'POST',
      body,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .catch(err => {
      console.error('[IMAjax] Error:', err);
      return { status: 'error', msg: "Server bilan bog'liq xatolik" };
    });
  },

  /**
   * GET so'rov yuborish
   */
  get(url, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const fullUrl = qs ? `${url}?${qs}` : url;

    return fetch(fullUrl, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .catch(err => {
      console.error('[IMAjax] Error:', err);
      return { status: 'error', msg: "Server bilan bog'liq xatolik" };
    });
  }
};

// ─── Form helpers ────────────────────────────────────────────
const NHForm = {
  /**
   * Formni serialise qilish
   */
  serialize(formEl) {
    const fd = new FormData(formEl);
    const obj = {};
    fd.forEach((v, k) => { obj[k] = v; });
    return obj;
  },

  /**
   * Formni tozalash
   */
  reset(formEl) {
    if (formEl) formEl.reset();
  },

  /**
   * Xatoliklarni ko'rsatish
   */
  showErrors(errors, prefix = '') {
    Object.entries(errors).forEach(([field, msg]) => {
      const el = document.getElementById(`${prefix}${field}`);
      if (el) {
        el.classList.add('is-invalid');
        const errEl = el.nextElementSibling;
        if (errEl && errEl.classList.contains('invalid-feedback')) {
          errEl.textContent = msg;
        }
      }
    });
  },

  /**
   * Xatoliklarni tozalash
   */
  clearErrors(formEl) {
    if (!formEl) return;
    formEl.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  }
};

// ─── Number formatting ───────────────────────────────────────
const NHNum = {
  money(n, cur = '') {
    const formatted = parseFloat(n || 0).toLocaleString('uz-UZ', {
      maximumFractionDigits: 0
    });
    return cur ? `${formatted} ${cur}` : formatted;
  },

  moneySum(n) { return this.money(n, "so'm"); },

  usd(n) {
    return `$${parseFloat(n || 0).toFixed(2)}`;
  },

  percent(n) {
    return `${parseFloat(n || 0).toFixed(1)}%`;
  }
};

// ─── Sidebar toggle (mobile) ─────────────────────────────────
const NHSidebar = {
  init() {
    const sidebar = document.querySelector('.im-sidebar');
    const toggleBtn = document.getElementById('im-sidebar-toggle');
    if (!sidebar || !toggleBtn) return;

    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });

    // Outside click
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('open') &&
          !sidebar.contains(e.target) &&
          e.target !== toggleBtn &&
          !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }
};

// ─── Table search filter ─────────────────────────────────────
const NHTableFilter = {
  /**
   * Real-time jadval filtri
   * @param {string} inputId - qidiruv input ID
   * @param {string} tableId - jadval ID
   * @param {number[]} cols   - qidiriladigan ustun indekslari (0-based)
   */
  bind(inputId, tableId, cols = []) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', () => {
      const q = input.value.toLowerCase().trim();
      const rows = table.querySelectorAll('tbody tr');
      let visible = 0;

      rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const searchCols = cols.length ? cols : [...cells].map((_, i) => i);
        const match = searchCols.some(i => {
          const cell = cells[i];
          return cell && cell.textContent.toLowerCase().includes(q);
        });
        row.style.display = match ? '' : 'none';
        if (match) visible++;
      });

      // Empty state
      const emptyRow = table.querySelector('.im-empty-row');
      if (emptyRow) {
        emptyRow.style.display = (visible === 0 && q) ? '' : 'none';
      }
    });
  }
};

// ─── Initialization ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Theme
  NHTheme.init();

  // Toast container
  NHToast.init();

  // Theme toggle buttons
  document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
    btn.addEventListener('click', () => NHTheme.toggle());
  });

  // Modal — close on overlay click
  document.querySelectorAll('.im-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) NHModal.close(overlay.id);
    });
  });

  // Modal — close button
  document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.closest('.im-overlay');
      if (target) NHModal.close(target.id);
    });
  });

  // Sidebar
  NHSidebar.init();
});

// ─── Global exports ──────────────────────────────────────────
window.NHTheme    = NHTheme;
window.NHToast    = NHToast;
window.NHModal    = NHModal;
window.NHConfirm  = NHConfirm;
window.IMAjax     = IMAjax;
window.NHForm     = NHForm;
window.NHNum      = NHNum;
window.NHSidebar  = NHSidebar;
window.NHTableFilter = NHTableFilter;
