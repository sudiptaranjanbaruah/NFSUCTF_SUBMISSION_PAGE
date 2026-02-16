// ============================================
// CHALLENGES PAGE
// ============================================
registerRoute('/challenges', renderChallengesPage);

let challengesData = [];
let activeCategory = 'all';

async function renderChallengesPage() {
    const app = document.getElementById('app');
    showLoading(app);

    try {
        const data = await apiGet('/challenges');
        challengesData = data.challenges;

        const categories = ['all', ...new Set(challengesData.map(c => c.category))];

        app.innerHTML = `
      <div class="page-header">
        <h1 class="page-title">Challenges</h1>
        <p class="page-subtitle">${challengesData.length} challenges available</p>
      </div>
      <div class="category-filters" id="category-filters">
        ${categories.map(cat => `
          <button class="category-btn ${cat === activeCategory ? 'active' : ''}" data-category="${cat}">
            ${cat === 'all' ? 'All' : escapeHtml(cat)}
            <span class="text-muted">(${cat === 'all' ? challengesData.length : challengesData.filter(c => c.category === cat).length})</span>
          </button>
        `).join('')}
      </div>
      <div class="challenges-grid" id="challenges-grid"></div>
      <div class="modal-overlay" id="challenge-modal">
        <div class="modal" id="challenge-modal-content"></div>
      </div>
    `;

        renderChallengeCards();

        document.getElementById('category-filters').addEventListener('click', (e) => {
            const btn = e.target.closest('.category-btn');
            if (!btn) return;
            activeCategory = btn.dataset.category;
            document.querySelectorAll('.category-btn').forEach(b => b.classList.toggle('active', b.dataset.category === activeCategory));
            renderChallengeCards();
        });

        document.getElementById('challenge-modal').addEventListener('click', (e) => {
            if (e.target.id === 'challenge-modal') closeChallengeModal();
        });
    } catch (err) {
        app.innerHTML = `<div class="error-page"><div class="error-message">${escapeHtml(err.message)}</div></div>`;
    }
}

function renderChallengeCards() {
    const grid = document.getElementById('challenges-grid');
    const filtered = activeCategory === 'all' ? challengesData : challengesData.filter(c => c.category === activeCategory);

    grid.innerHTML = filtered.length === 0 ? '<p class="text-muted text-center">No challenges in this category</p>' :
        filtered.map(c => `
      <div class="challenge-card ${c.is_solved ? 'solved' : ''}" onclick="openChallenge(${c.id})">
        <div class="challenge-card-header">
          <h3 class="challenge-card-title">${escapeHtml(c.title)}</h3>
          ${c.is_solved ? '<span class="badge badge-solved">✓ Solved</span>' : '<span class="badge badge-unsolved">Unsolved</span>'}
        </div>
        <div class="challenge-card-meta">
          <span class="badge badge-category">${escapeHtml(c.category)}</span>
          <span class="badge badge-points">${c.points} pts</span>
        </div>
        <div class="challenge-card-desc">${escapeHtml((c.description || '').substring(0, 200))}</div>
        <div class="challenge-card-footer">
          <span class="solve-count">🏴 ${c.solve_count} solve${c.solve_count !== 1 ? 's' : ''}</span>
          ${c.files && c.files.length > 0 ? '<span class="text-muted text-sm">📎 Files attached</span>' : ''}
        </div>
      </div>
    `).join('');
}

async function openChallenge(id) {
    const modal = document.getElementById('challenge-modal');
    const content = document.getElementById('challenge-modal-content');
    modal.classList.add('active');

    content.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    try {
        const [chalData, statsData] = await Promise.all([
            apiGet(`/challenges/${id}`),
            apiGet(`/challenges/${id}/stats`),
        ]);

        const c = chalData.challenge;
        const stats = statsData;

        const filesHtml = c.files && c.files.length > 0 ? `
      <div class="mt-16">
        <h4 style="margin-bottom:8px;">📎 Attachments</h4>
        ${c.files.map(f => `
          <a href="${f.file_url}" target="_blank" class="btn btn-secondary btn-sm" style="margin:4px;">
            ⬇ ${escapeHtml(f.file_name)}
          </a>
        `).join('')}
      </div>
    ` : '';

        const solvedTeamsHtml = stats.solvedTeams.length > 0 ? `
      <div class="mt-16">
        <h4 style="margin-bottom:8px;">🏴 Solves (${stats.solveCount})</h4>
        <div class="solve-list">
          ${stats.solvedTeams.map((t, i) => `
            <div class="solve-item">
              <span class="solve-team">
                ${i === 0 ? '<span class="badge badge-first-blood" style="margin-right:6px;">🩸 First Blood</span>' : ''}
                ${escapeHtml(t.name)}
              </span>
              <span class="solve-time">${formatTime(t.solved_at)}</span>
            </div>
          `).join('')}
        </div>
      </div>
    ` : '<p class="text-muted text-sm mt-16">No solves yet — be the first! 🏆</p>';

        const attemptsHtml = c.attempts_remaining !== null ? `
      <p class="text-muted text-sm mt-8">Attempts remaining: ${c.attempts_remaining}</p>
    ` : '';

        content.innerHTML = `
      <div class="modal-header">
        <div>
          <h2 class="modal-title">${escapeHtml(c.title)}</h2>
          <div class="flex gap-8 mt-8">
            <span class="badge badge-category">${escapeHtml(c.category)}</span>
            <span class="badge badge-points">${c.points} pts</span>
            ${c.is_solved ? '<span class="badge badge-solved">✓ Solved</span>' : ''}
          </div>
        </div>
        <button class="modal-close" onclick="closeChallengeModal()">✕</button>
      </div>
      <div class="markdown-content">${renderMarkdown(c.description || 'No description')}</div>
      ${filesHtml}
      ${!c.is_solved ? `
        <div class="mt-24">
          <form id="flag-form" class="flex gap-8">
            <input type="text" class="form-input form-input-flag" id="flag-input" 
                   placeholder="NFSUCTF{your_flag_here}" autocomplete="off" style="flex:1;">
            <button type="submit" class="btn btn-primary" id="flag-submit-btn">Submit</button>
          </form>
          ${attemptsHtml}
          <p class="text-muted text-sm mt-8" id="flag-hint"></p>
        </div>
      ` : '<div class="mt-24"><p style="color:var(--green);font-weight:600;">✓ You have already solved this challenge!</p></div>'}
      ${solvedTeamsHtml}
    `;

        if (!c.is_solved) {
            document.getElementById('flag-form').addEventListener('submit', (e) => handleFlagSubmit(e, c.id));
            document.getElementById('flag-input').addEventListener('input', (e) => {
                const hint = document.getElementById('flag-hint');
                const val = e.target.value.trim();
                if (val && !validateFlagFormat(val)) {
                    hint.textContent = '⚠ Flag must match format: NFSUCTF{...}';
                    hint.style.color = 'var(--yellow)';
                } else {
                    hint.textContent = '';
                }
            });
        }
    } catch (err) {
        content.innerHTML = `<p class="text-center text-muted">${escapeHtml(err.message)}</p>`;
    }
}

async function handleFlagSubmit(e, challengeId) {
    e.preventDefault();
    const input = document.getElementById('flag-input');
    const btn = document.getElementById('flag-submit-btn');
    const flag = input.value.trim();

    if (!flag) return showToast('Please enter a flag', 'warning');
    if (!validateFlagFormat(flag)) return showToast('Invalid flag format. Must match NFSUCTF{...}', 'warning');

    btn.disabled = true;
    btn.textContent = 'Checking...';

    try {
        const data = await apiPost('/submit', { challengeId, flag });
        if (data.correct) {
            showToast(`🎉 ${data.message} (+${data.points || 0} pts)`, 'success');
            closeChallengeModal();
            renderChallengesPage(); // Refresh
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Submit';
        input.value = '';
    }
}

function closeChallengeModal() {
    document.getElementById('challenge-modal')?.classList.remove('active');
}
