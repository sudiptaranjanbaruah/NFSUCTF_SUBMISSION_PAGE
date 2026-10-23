// =====================================================================
// NFSU Guwahati CTF — shared client behaviour (no framework)
// =====================================================================
(function () {
  'use strict';

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  const iconHref = () => document.querySelector('use')?.getAttribute('href')?.split('#')[0] || '/assets/icons.svg';

  window.CTF = window.CTF || {};

  // ---------- fetch helper ----------
  CTF.api = async function (url, { method = 'GET', body } = {}) {
    const opts = {
      method,
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf() },
      credentials: 'same-origin',
    };
    if (body instanceof FormData) {
      opts.body = body;
    } else if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    const res = await fetch(url, opts);
    let data = {};
    try { data = await res.json(); } catch (_) { /* non-JSON */ }
    if (!res.ok) {
      const err = new Error(data.message || `Request failed (${res.status})`);
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  };

  CTF.icon = (name, cls = '') =>
    `<svg class="icon ${cls}" aria-hidden="true"><use href="${iconHref()}#${name}"></use></svg>`;

  CTF.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // ---------- toasts ----------
  CTF.toast = function (message, type = 'info', title = '') {
    const box = document.getElementById('toasts');
    if (!box) return;
    const icons = { success: 'check-circle', error: 'alert', warning: 'alert', blood: 'droplet', info: 'info' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `${CTF.icon(icons[type] || 'info')}<div class="grow">${title ? `<strong>${CTF.esc(title)}</strong>` : ''}${CTF.esc(message)}</div>`;
    box.appendChild(el);
    dismissLater(el);
  };
  function dismissLater(el, ms = 5000) {
    setTimeout(() => {
      el.classList.add('leaving');
      setTimeout(() => el.remove(), 250);
    }, ms);
  }
  document.querySelectorAll('[data-autodismiss]').forEach((el) => dismissLater(el, 6000));

  // ---------- theme ----------
  function syncThemeIcon() {
    const light = document.documentElement.getAttribute('data-theme') === 'light';
    document.querySelectorAll('[data-theme-toggle] use').forEach((u) => {
      u.setAttribute('href', `${iconHref()}#${light ? 'moon' : 'sun'}`);
    });
  }
  document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-theme-toggle]')) return;
    const next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('theme', next); } catch (_) {}
    syncThemeIcon();
    document.dispatchEvent(new CustomEvent('themechange'));
  });
  syncThemeIcon();

  // ---------- nav toggles ----------
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-toggle-nav]')) document.getElementById('main-nav')?.classList.toggle('is-open');
    if (e.target.closest('[data-toggle-side]')) document.getElementById('admin-side')?.classList.toggle('is-open');
    // Close open dropdowns on outside click
    document.querySelectorAll('details.menu[open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  // ---------- confirm before destructive submits ----------
  document.addEventListener('submit', (e) => {
    const form = e.target;
    const msg = form.getAttribute('data-confirm');
    if (msg && !confirm(msg)) { e.preventDefault(); return; }
    const btn = form.querySelector('button[type="submit"], button:not([type])');
    if (btn && !form.hasAttribute('data-no-busy')) {
      setTimeout(() => { btn.disabled = true; btn.classList.add('is-loading'); }, 0);
    }
  });

  // ---------- copy buttons ----------
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-copy]');
    if (!b) return;
    try {
      await navigator.clipboard.writeText(b.getAttribute('data-copy'));
      CTF.toast('Copied to clipboard', 'success');
    } catch (_) {
      CTF.toast('Copy failed. Select the text and copy it manually.', 'error');
    }
  });

  // ---------- password reveal + strength ----------
  document.addEventListener('click', (e) => {
    const b = e.target.closest('.btn-reveal');
    if (!b) return;
    const input = b.parentElement.querySelector('input');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    b.querySelector('use')?.setAttribute('href', `${iconHref()}#${show ? 'eye-off' : 'eye'}`);
  });
  document.querySelectorAll('[data-strength]').forEach((input) => {
    const meter = document.getElementById(input.getAttribute('data-strength'));
    const help = meter?.nextElementSibling;
    input.addEventListener('input', () => {
      const v = input.value;
      let s = 0;
      if (v.length >= 8) s++;
      if (v.length >= 12) s++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
      if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
      if (v.length < 8) s = Math.min(s, 1);
      meter.dataset.score = v ? Math.max(1, s) : 0;
      if (help) help.textContent = ['At least 8 characters.', 'Weak: add length or variety.', 'Fair: a few more characters would help.', 'Good.', 'Strong.'][v ? Math.max(1, s) : 0];
    });
  });

  // ---------- OTP boxes ----------
  document.querySelectorAll('[data-otp]').forEach((wrap) => {
    const boxes = [...wrap.querySelectorAll('input')];
    const hidden = document.getElementById(wrap.getAttribute('data-otp'));
    const sync = () => { hidden.value = boxes.map((b) => b.value).join(''); };
    boxes.forEach((box, i) => {
      box.addEventListener('input', () => {
        const digits = box.value.replace(/\D/g, '');
        // Autofill or fast typing can land several digits in one box: spread them out.
        digits.split('').slice(0, boxes.length - i).forEach((d, j) => { boxes[i + j].value = d; });
        if (!digits) box.value = '';
        const nextIdx = Math.min(i + Math.max(digits.length, 1), boxes.length - 1);
        if (digits && boxes[nextIdx]) boxes[nextIdx].focus();
        sync();
        if (hidden.value.length === boxes.length) wrap.closest('form')?.requestSubmit();
      });
      box.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !box.value && boxes[i - 1]) boxes[i - 1].focus();
      });
      box.addEventListener('paste', (e) => {
        const digits = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, boxes.length);
        if (!digits) return;
        e.preventDefault();
        digits.split('').forEach((d, j) => { boxes[j].value = d; });
        sync();
        boxes[Math.min(digits.length, boxes.length - 1)].focus();
        if (digits.length === boxes.length) wrap.closest('form')?.requestSubmit();
      });
    });
    boxes[0]?.focus();
  });

  // ---------- resend cooldown ----------
  document.querySelectorAll('[data-cooldown]').forEach((btn) => {
    let left = parseInt(btn.getAttribute('data-cooldown'), 10) || 0;
    const label = btn.textContent;
    const tick = () => {
      if (left <= 0) { btn.disabled = false; btn.textContent = label; return; }
      btn.disabled = true;
      btn.textContent = `${label} (${left}s)`;
      left--;
      setTimeout(tick, 1000);
    };
    tick();
  });

  // ---------- local times & countdowns ----------
  const fmtDate = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  const fmtFull = new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: 'short' });
  CTF.localizeTimes = (root = document) => {
    root.querySelectorAll('time[datetime]').forEach((t) => {
      const d = new Date(t.getAttribute('datetime'));
      if (isNaN(d)) return;
      t.title = fmtFull.format(d);
      const f = t.dataset.fmt;
      if (f === 'ago') {
        const s = (Date.now() - d) / 1000;
        t.textContent = s < 60 ? 'just now' : s < 3600 ? `${Math.floor(s / 60)}m ago` : s < 86400 ? `${Math.floor(s / 3600)}h ago` : fmtDate.format(d);
      } else if (f === 'full') {
        t.textContent = fmtFull.format(d);
      } else {
        t.textContent = fmtDate.format(d);
      }
    });
  };
  CTF.localizeTimes();

  const pad = (n) => String(n).padStart(2, '0');
  function runCountdowns() {
    document.querySelectorAll('[data-countdown]').forEach((el) => {
      const ms = new Date(el.getAttribute('data-countdown')) - Date.now();
      const s = Math.max(0, Math.floor(ms / 1000));
      const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
      if (el.hasAttribute('data-compact')) {
        el.textContent = d > 0 ? `${d}d ${pad(h)}h` : `${pad(h)}:${pad(m)}:${pad(sec)}`;
      } else {
        const parts = el.querySelectorAll('b');
        if (parts.length === 4) [d, h, m, sec].forEach((v, i) => { parts[i].textContent = pad(v); });
      }
      if (ms <= 0 && !el.dataset.reloaded && el.hasAttribute('data-reload')) {
        el.dataset.reloaded = '1';
        setTimeout(() => location.reload(), 1500);
      }
    });
  }
  if (document.querySelector('[data-countdown]')) { runCountdowns(); setInterval(runCountdowns, 1000); }

  // ---------- Markdown live preview (admin) ----------
  document.querySelectorAll('[data-md-preview]').forEach((ta) => {
    const out = document.getElementById(ta.getAttribute('data-md-preview'));
    let timer;
    const update = async () => {
      try {
        const r = await CTF.api('/admin/preview', { method: 'POST', body: { text: ta.value } });
        out.innerHTML = r.html || '<p class="subtle">Nothing to preview yet.</p>';
      } catch (_) { /* keep last preview */ }
    };
    ta.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(update, 350); });
    update();
  });

  // ---------- auto-submit filters ----------
  document.querySelectorAll('form[data-autosubmit] select').forEach((s) => s.addEventListener('change', () => s.form.requestSubmit()));
})();

// ---------- datetime-local inputs stored as UTC ----------
(function () {
  const pad = (n) => String(n).padStart(2, '0');
  document.querySelectorAll('input[data-utc]').forEach((input) => {
    const iso = input.getAttribute('data-utc');
    if (!iso) return;
    const d = new Date(iso);
    if (!isNaN(d)) input.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  });
  document.querySelectorAll('input[name="tz_offset"]').forEach((i) => { i.value = String(new Date().getTimezoneOffset()); });
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
  document.querySelectorAll('[data-tz-name]').forEach((el) => { el.textContent = tz; });
})();
