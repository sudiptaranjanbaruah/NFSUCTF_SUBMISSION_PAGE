// Challenge board: filters, detail drawer, flag submission, hints.
(function () {
  'use strict';
  const { api, icon, esc, toast, localizeTimes } = window.CTF;
  const drawer = document.getElementById('chal-drawer');
  if (!drawer) return;
  const $ = (k) => drawer.querySelector(`[data-d="${k}"]`);
  let current = null;

  // ---------- filters ----------
  const search = document.querySelector('[data-board-search]');
  const diffSel = document.querySelector('[data-board-diff]');
  let status = 'all';
  function applyFilters() {
    const q = (search?.value || '').trim().toLowerCase();
    const d = diffSel?.value || '';
    let any = false;
    document.querySelectorAll('[data-cat-block]').forEach((block) => {
      let shown = 0;
      block.querySelectorAll('[data-chal]').forEach((card) => {
        const ok = (!q || card.dataset.title.includes(q))
          && (!d || card.dataset.diff === d)
          && (status === 'all' || (status === 'solved') === (card.dataset.solved === '1'));
        card.hidden = !ok;
        if (ok) shown++;
      });
      block.hidden = shown === 0;
      if (shown) any = true;
    });
    document.querySelector('[data-board-empty]')?.classList.toggle('hidden', any);
  }
  search?.addEventListener('input', applyFilters);
  diffSel?.addEventListener('change', applyFilters);
  document.querySelectorAll('[data-board-filter]').forEach((b) => b.addEventListener('click', () => {
    document.querySelectorAll('[data-board-filter]').forEach((x) => x.classList.toggle('is-active', x === b));
    status = b.dataset.boardFilter;
    applyFilters();
  }));

  // ---------- drawer ----------
  document.addEventListener('click', (e) => {
    const card = e.target.closest('[data-chal]');
    if (card) {
      if (card.getAttribute('aria-disabled') === 'true') {
        toast(card.title || 'This challenge is locked.', 'warning');
        return;
      }
      open(card.dataset.chal);
    }
    if (e.target.closest('[data-close]')) close();
  });
  drawer.addEventListener('click', (e) => { if (e.target === drawer) close(); });
  drawer.addEventListener('close', () => {
    if (location.hash.startsWith('#c')) history.replaceState(null, '', location.pathname + location.search);
  });

  function close() { drawer.close(); }

  async function open(id) {
    current = id;
    history.replaceState(null, '', `#c${id}`);
    $('title').textContent = 'Loading…';
    $('meta').innerHTML = '';
    $('body').innerHTML = '<div class="skeleton" style="height:18px;width:60%"></div><div class="skeleton" style="height:120px;margin-top:16px"></div>';
    $('foot').innerHTML = '';
    if (!drawer.open) drawer.showModal();
    try {
      const { challenge } = await api(`/api/challenges/${id}`);
      if (current === id) render(challenge);
    } catch (err) {
      $('title').textContent = 'Could not open challenge';
      $('body').innerHTML = `<div class="alert alert-error">${icon('alert', 'sm')}<div>${esc(err.message)}</div></div>`;
    }
  }

  function render(c) {
    $('title').textContent = c.title;
    $('meta').innerHTML = `<span class="mono">${esc(c.code)}</span><span>${esc(c.category)}</span><span class="diff diff-${esc(c.difficulty)}">${esc(c.difficulty[0].toUpperCase() + c.difficulty.slice(1))}</span>`;

    const facts = [
      `<span class="badge badge-accent">${icon('flag')}${c.value} points${c.scoring === 'dynamic' ? ' (dynamic)' : ''}</span>`,
      `<span class="badge">${icon('users')}${c.solve_count} ${c.solve_count === 1 ? 'solve' : 'solves'}</span>`,
    ];
    if (c.first_blood[0] && !c.solve_count) facts.push(`<span class="badge badge-blood">${icon('droplet')}First blood +${c.first_blood[0]}</span>`);
    if (c.author) facts.push(`<span class="badge">${icon('user')}${esc(c.author)}</span>`);
    if (c.attempts_left !== null && !c.solved) facts.push(`<span class="badge ${c.attempts_left <= 2 ? 'badge-warning' : ''}">${c.attempts_left} attempts left</span>`);
    c.tags.forEach((t) => facts.push(`<span class="badge">#${esc(t)}</span>`));

    let html = `<div class="chal-detail"><div class="chal-facts">${facts.join('')}</div>`;
    html += `<div class="prose chal-desc">${c.description_html || '<p class="subtle">No description.</p>'}</div>`;

    if (c.connection) {
      html += `<div class="detail-section"><h3>Connect</h3><div class="conn">${icon('terminal', 'sm')}<code>${esc(c.connection)}</code>
        <button class="btn btn-ghost btn-sm btn-icon" type="button" data-copy="${esc(c.connection)}" aria-label="Copy">${icon('copy', 'sm')}</button></div></div>`;
    }
    if (c.files.length) {
      html += `<div class="detail-section"><h3>Files</h3><div class="file-list">${c.files.map((f) => `
        <a class="file" href="${esc(f.url)}" download>${icon('file')}<div class="grow"><div>${esc(f.name)}</div><div class="hash" title="SHA-256">sha256 ${esc(f.sha256.slice(0, 16))}…</div></div><span class="subtle xs">${esc(f.size)}</span>${icon('download', 'sm')}</a>`).join('')}</div></div>`;
    }
    if (c.hints.length) {
      html += `<div class="detail-section"><h3>Hints</h3><div class="stack-sm">${c.hints.map((h, i) => hintHtml(h, i)).join('')}</div></div>`;
    }
    html += `<div class="detail-section"><h3>Solved by</h3>${c.solvers.length ? `<ul class="solver-list">${c.solvers.map((s) => `
        <li><span>${s.rank === 1 ? `<span class="badge badge-blood">${icon('droplet')}1st</span> ` : ''}<a href="${esc(s.url)}">${esc(s.team)}</a></span><time datetime="${esc(s.at)}" data-fmt="ago"></time></li>`).join('')}</ul>`
      : '<p class="subtle small">Nobody yet. First blood is up for grabs.</p>'}</div>`;
    html += '</div>';
    $('body').innerHTML = html;
    localizeTimes($('body'));
    renderFoot(c);
  }

  function hintHtml(h, i) {
    if (h.unlocked) return `<div class="hint is-open"><div class="row-between xs subtle" style="margin-bottom:6px"><span>Hint ${i + 1}</span>${h.cost ? `<span>−${h.cost} pts</span>` : ''}</div><div class="prose small">${h.html}</div></div>`;
    return `<div class="hint row-between"><span class="small">Hint ${i + 1}${h.cost ? ` <span class="subtle">costs ${h.cost} points</span>` : ' <span class="subtle">free</span>'}</span>
      <button class="btn btn-sm" type="button" data-hint="${h.id}" data-cost="${h.cost}">${icon('lightbulb', 'sm')}Reveal</button></div>`;
  }

  function renderFoot(c) {
    const foot = $('foot');
    if (c.solved) {
      foot.innerHTML = `<div class="row">${icon('check-circle')}<div class="grow small"><strong>Solved${c.solved.rank === 1 ? ' with first blood' : ''}</strong> by ${esc(c.solved.by || 'your team')}, <time datetime="${esc(c.solved.at)}" data-fmt="ago"></time>
        <div class="subtle xs">+${c.solved.points} points${c.solved.bonus ? ` and +${c.solved.bonus} bonus` : ''}</div></div></div>`;
      localizeTimes(foot);
      return;
    }
    if (!c.can_submit) {
      const msg = { upcoming: 'The event has not started yet.', paused: 'Submissions are paused by the organisers.', ended: 'The event has ended. Submissions are closed.' }[c.state] || 'Submissions are closed.';
      foot.innerHTML = `<div class="row small muted">${icon('lock', 'sm')}${msg}</div>`;
      return;
    }
    if (c.attempts_left === 0) {
      foot.innerHTML = `<div class="row small muted">${icon('ban', 'sm')}Your team has used all attempts for this challenge.</div>`;
      return;
    }
    foot.innerHTML = `<form class="flag-form" data-flag-form autocomplete="off">
      <label class="sr-only" for="flag-input">Flag</label>
      <input class="input" id="flag-input" name="flag" placeholder="${esc(document.body.dataset.flagFormat || 'NFSUCTF{...}')}" required spellcheck="false" autocapitalize="off">
      <button class="btn btn-primary" type="submit">${icon('flag', 'sm')}Submit</button></form>
      <div class="help" data-flag-msg style="margin-top:6px"></div>`;
    foot.querySelector('input').focus();
  }

  // ---------- submit ----------
  drawer.addEventListener('submit', async (e) => {
    const form = e.target.closest('[data-flag-form]');
    if (!form) return;
    e.preventDefault();
    const input = form.querySelector('input');
    const btn = form.querySelector('button');
    const msg = drawer.querySelector('[data-flag-msg]');
    form.classList.remove('is-wrong', 'is-right');
    btn.disabled = true;
    try {
      const r = await api(`/api/challenges/${current}/submit`, { method: 'POST', body: { flag: input.value } });
      if (r.correct) {
        form.classList.add('is-right');
        const card = document.querySelector(`[data-chal="${current}"]`);
        if (card && !r.already) {
          card.classList.add('is-solved');
          card.dataset.solved = '1';
          card.insertAdjacentHTML('beforeend', `<span class="chal-status badge ${r.rank === 1 ? 'badge-blood' : 'badge-success'}">${icon(r.rank === 1 ? 'droplet' : 'check')}${r.rank === 1 ? 'First blood' : 'Solved'}</span>`);
          const sc = document.querySelector('[data-solved-count]');
          if (sc) sc.textContent = String(parseInt(sc.textContent, 10) + 1);
        }
        if (r.score !== undefined) document.querySelectorAll('[data-team-score]').forEach((el) => { el.textContent = r.score.toLocaleString(); });
        if (r.rank === 1) toast(`+${r.points} points and +${r.bonus} first-blood bonus.`, 'blood', 'First blood!');
        else toast(r.already ? r.message : `+${r.points} points${r.bonus ? ` and +${r.bonus} bonus` : ''}.`, 'success', r.already ? '' : 'Flag accepted');
        setTimeout(() => open(current), 600);
      } else {
        form.classList.add('is-wrong');
        msg.textContent = r.message + (r.attempts_left !== null && r.attempts_left !== undefined ? ` ${r.attempts_left} attempts left.` : '');
        msg.style.color = 'var(--danger)';
        input.select();
        if (r.locked) setTimeout(() => open(current), 800);
      }
    } catch (err) {
      msg.textContent = err.message;
      msg.style.color = 'var(--danger)';
    } finally {
      btn.disabled = false;
    }
  });

  // ---------- hints ----------
  drawer.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-hint]');
    if (!b) return;
    const cost = parseInt(b.dataset.cost, 10);
    if (cost && !confirm(`Reveal this hint for ${cost} points? Your team's score drops by ${cost}.`)) return;
    b.disabled = true;
    try {
      const r = await api(`/api/hints/${b.dataset.hint}/unlock`, { method: 'POST', body: {} });
      if (r.score !== undefined) document.querySelectorAll('[data-team-score]').forEach((el) => { el.textContent = r.score.toLocaleString(); });
      open(current);
    } catch (err) {
      toast(err.message, 'error');
      b.disabled = false;
    }
  });

  // Deep link: /challenges#c12
  const m = location.hash.match(/^#c(\d+)$/);
  if (m) open(m[1]);
})();
