// Admin console helpers: repeatable rows, dropzone, scoring toggle.
(function () {
  'use strict';

  // ---------- repeatable rows (flags, hints) ----------
  document.addEventListener('click', (e) => {
    const add = e.target.closest('[data-add-row]');
    if (add) {
      const name = add.dataset.addRow;
      const tpl = document.getElementById(`tpl-${name}`);
      const list = document.querySelector(`[data-repeat="${name}"]`);
      list.appendChild(tpl.content.cloneNode(true));
      document.querySelector('[data-empty-hint]')?.remove();
      list.lastElementChild.querySelector('input:not([type=checkbox]), textarea')?.focus();
    }
    const rm = e.target.closest('[data-remove-row]');
    if (rm) rm.closest('[data-row]').remove();
  });

  // Checkbox values carry their row index so the server can line them up.
  document.getElementById('chal-form')?.addEventListener('submit', () => {
    document.querySelectorAll('[data-repeat="flags"] [data-row]').forEach((row, i) => {
      const cb = row.querySelector('input[type=checkbox]');
      if (cb) cb.value = String(i);
    });
  });

  // ---------- scoring toggle ----------
  const radios = document.querySelectorAll('[data-scoring]');
  function syncScoring() {
    const dyn = document.querySelector('[data-scoring]:checked')?.value === 'dynamic';
    document.querySelectorAll('[data-dynamic]').forEach((el) => { el.hidden = !dyn; });
    const lbl = document.querySelector('[data-points-label]');
    if (lbl) lbl.textContent = dyn ? 'Starting value' : 'Points';
    radios.forEach((r) => r.nextElementSibling.classList.toggle('is-active', r.checked));
  }
  radios.forEach((r) => r.addEventListener('change', syncScoring));
  if (radios.length) syncScoring();

  // ---------- dropzone ----------
  document.querySelectorAll('[data-dropzone]').forEach((zone) => {
    const input = zone.querySelector('input[type=file]');
    const names = zone.querySelector('[data-dropzone-names]');
    const show = () => { names.textContent = [...input.files].map((f) => f.name).join(', '); };
    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.remove('is-over'); }));
    zone.addEventListener('drop', (e) => { input.files = e.dataTransfer.files; show(); });
  });
})();

// Colour picker mirrors into its text input.
document.querySelectorAll('[data-sync-to]').forEach((el) => {
  el.addEventListener('input', () => { document.getElementById(el.dataset.syncTo).value = el.value; });
});
