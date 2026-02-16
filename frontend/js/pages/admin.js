// ============================================
// ADMIN DASHBOARD
// ============================================
registerRoute('/admin/dashboard', renderAdminDashboard);

let adminTab = 'teams';

async function renderAdminDashboard() {
  const user = getUser();
  if (!user || !['admin', 'super_admin'].includes(user.role)) {
    navigateTo('/admin/login');
    return;
  }

  const app = document.getElementById('app');
  app.innerHTML = `
    <div class="page-header">
      <h1 class="page-title">Admin Dashboard</h1>
      <p class="page-subtitle">Full competition control panel</p>
    </div>
    <div class="tabs" id="admin-tabs">
      <button class="tab-btn active" data-tab="teams">👥 Teams</button>
      <button class="tab-btn" data-tab="challenges">🏴 Challenges</button>
      <button class="tab-btn" data-tab="event">⚡ Event</button>
      <button class="tab-btn" data-tab="announcements">📢 Announce</button>
      <button class="tab-btn" data-tab="analytics">📊 Analytics</button>
      <button class="tab-btn" data-tab="submissions">📝 Submissions</button>
      <button class="tab-btn" data-tab="logs">📋 Logs</button>
      <button class="tab-btn" data-tab="tools">🔧 Tools</button>
    </div>
    <div id="admin-content"></div>
    <div class="modal-overlay" id="admin-modal"><div class="modal" id="admin-modal-content"></div></div>
  `;

  document.getElementById('admin-tabs').addEventListener('click', (e) => {
    const btn = e.target.closest('.tab-btn');
    if (!btn) return;
    adminTab = btn.dataset.tab;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === adminTab));
    renderAdminTab();
  });

  document.getElementById('admin-modal').addEventListener('click', (e) => {
    if (e.target.id === 'admin-modal') closeAdminModal();
  });

  renderAdminTab();
}

function closeAdminModal() {
  document.getElementById('admin-modal')?.classList.remove('active');
}

function openAdminModal(html) {
  const modal = document.getElementById('admin-modal');
  document.getElementById('admin-modal-content').innerHTML = html;
  modal.classList.add('active');
}

async function renderAdminTab() {
  const content = document.getElementById('admin-content');
  showLoading(content);

  try {
    switch (adminTab) {
      case 'teams': await renderAdminTeams(content); break;
      case 'challenges': await renderAdminChallenges(content); break;
      case 'event': await renderAdminEvent(content); break;
      case 'announcements': await renderAdminAnnouncements(content); break;
      case 'analytics': await renderAdminAnalytics(content); break;
      case 'submissions': await renderAdminSubmissions(content); break;
      case 'logs': await renderAdminLogs(content); break;
      case 'tools': await renderAdminTools(content); break;
    }
  } catch (err) {
    content.innerHTML = `<p class="text-center" style="color:var(--red);">${escapeHtml(err.message)}</p>`;
  }
}

// ============================================
// TEAMS TAB
// ============================================
async function renderAdminTeams(container) {
  const data = await apiGet('/admin/teams');
  const teams = data.teams.filter(t => t.role === 'team');

  container.innerHTML = `
    <div class="flex justify-between items-center mb-16">
      <h3>Teams (${teams.length})</h3>
      <button class="btn btn-primary btn-sm" onclick="showCreateTeamModal()">+ Create Team</button>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Points</th>
            <th>Solves</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          ${teams.map(t => `
            <tr>
              <td><strong>${escapeHtml(t.name)}</strong></td>
              <td class="font-mono text-sm">${escapeHtml(t.username)}</td>
              <td class="font-mono text-accent">${t.total_points}</td>
              <td class="font-mono">${t.total_solves}</td>
              <td>${t.is_active ? '<span class="badge badge-solved">Active</span>' : '<span class="badge badge-disabled">Inactive</span>'}</td>
              <td>
                <div class="flex gap-8">
                  <button class="btn btn-secondary btn-sm" onclick="showResetPasswordModal('${t.id}','${escapeHtml(t.name)}')">🔑</button>
                  <button class="btn btn-secondary btn-sm" onclick="showBonusModal('${t.id}','${escapeHtml(t.name)}')">💰</button>
                  <button class="btn btn-secondary btn-sm" onclick="toggleTeam('${t.id}')">${t.is_active ? '🚫' : '✅'}</button>
                </div>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

window.showCreateTeamModal = function () {
  openAdminModal(`
    <div class="modal-header"><h2 class="modal-title">Create Team</h2><button class="modal-close" onclick="closeAdminModal()">✕</button></div>
    <form onsubmit="createTeam(event)">
      <div class="form-group"><label class="form-label">Team Name</label><input class="form-input" id="new-team-name" required></div>
      <div class="form-group"><label class="form-label">Username</label><input class="form-input" id="new-team-username" required></div>
      <div class="form-group"><label class="form-label">Password</label><input type="password" class="form-input" id="new-team-password" required minlength="6"></div>
      <button type="submit" class="btn btn-primary w-full">Create Team</button>
    </form>
  `);
};

window.createTeam = async function (e) {
  e.preventDefault();
  try {
    await apiPost('/admin/teams', {
      name: document.getElementById('new-team-name').value,
      username: document.getElementById('new-team-username').value,
      password: document.getElementById('new-team-password').value,
      role: 'team',
    });
    showToast('Team created!', 'success');
    closeAdminModal();
    renderAdminTab();
  } catch (err) { showToast(err.message, 'error'); }
};

window.showResetPasswordModal = function (teamId, teamName) {
  openAdminModal(`
    <div class="modal-header"><h2 class="modal-title">Reset Password — ${teamName}</h2><button class="modal-close" onclick="closeAdminModal()">✕</button></div>
    <form onsubmit="resetTeamPassword(event, '${teamId}')">
      <div class="form-group"><label class="form-label">New Password</label><input type="password" class="form-input" id="reset-password" required minlength="6"></div>
      <button type="submit" class="btn btn-primary w-full">Reset Password</button>
    </form>
  `);
};

window.resetTeamPassword = async function (e, teamId) {
  e.preventDefault();
  try {
    await apiPut(`/admin/teams/${teamId}/password`, { password: document.getElementById('reset-password').value });
    showToast('Password reset!', 'success');
    closeAdminModal();
  } catch (err) { showToast(err.message, 'error'); }
};

window.showBonusModal = function (teamId, teamName) {
  openAdminModal(`
    <div class="modal-header"><h2 class="modal-title">Bonus/Deduct — ${teamName}</h2><button class="modal-close" onclick="closeAdminModal()">✕</button></div>
    <form onsubmit="addBonus(event, '${teamId}')">
      <div class="form-group"><label class="form-label">Points (negative to deduct)</label><input type="number" class="form-input" id="bonus-points" required></div>
      <div class="form-group"><label class="form-label">Reason</label><input class="form-input" id="bonus-reason" required></div>
      <button type="submit" class="btn btn-primary w-full">Apply</button>
    </form>
  `);
};

window.addBonus = async function (e, teamId) {
  e.preventDefault();
  try {
    await apiPost('/admin/teams/bonus', {
      teamId, points: parseInt(document.getElementById('bonus-points').value),
      reason: document.getElementById('bonus-reason').value,
    });
    showToast('Points updated!', 'success');
    closeAdminModal(); renderAdminTab();
  } catch (err) { showToast(err.message, 'error'); }
};

window.toggleTeam = async function (teamId) {
  try {
    const data = await apiPut(`/admin/teams/${teamId}/toggle`);
    showToast(`Team ${data.team.is_active ? 'activated' : 'deactivated'}`, 'success');
    renderAdminTab();
  } catch (err) { showToast(err.message, 'error'); }
};

// ============================================
// CHALLENGES TAB
// ============================================
async function renderAdminChallenges(container) {
  const data = await apiGet('/admin/challenges');

  container.innerHTML = `
    <div class="flex justify-between items-center mb-16">
      <h3>Challenges (${data.challenges.length})</h3>
      <button class="btn btn-primary btn-sm" onclick="showCreateChallengeModal()">+ Create Challenge</button>
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Title</th><th>Category</th><th>Points</th><th>Solves</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          ${data.challenges.map(c => `
            <tr>
              <td><strong>${escapeHtml(c.title)}</strong></td>
              <td><span class="badge badge-category">${escapeHtml(c.category)}</span></td>
              <td class="font-mono text-accent">${c.points}</td>
              <td class="font-mono">${c.solve_count}</td>
              <td>${c.is_active ? '<span class="badge badge-solved">Active</span>' : '<span class="badge badge-disabled">Inactive</span>'}</td>
              <td>
                <div class="flex gap-8">
                  <button class="btn btn-secondary btn-sm" onclick="showEditChallengeModal(${c.id})">✏️</button>
                  <button class="btn btn-secondary btn-sm" onclick="showUploadFileModal(${c.id})">📎</button>
                  <button class="btn btn-danger btn-sm" onclick="deleteChallenge(${c.id})">🗑</button>
                </div>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

window.showCreateChallengeModal = function () {
  openAdminModal(`
    <div class="modal-header"><h2 class="modal-title">Create Challenge</h2><button class="modal-close" onclick="closeAdminModal()">✕</button></div>
    <form onsubmit="createChallenge(event)">
      <div class="form-group"><label class="form-label">Title</label><input class="form-input" id="chal-title" required></div>
      <div class="form-group"><label class="form-label">Category</label>
        <select class="form-input" id="chal-category">
          <option value="Web">Web</option><option value="Crypto">Crypto</option><option value="Forensics">Forensics</option>
          <option value="Reverse">Reverse</option><option value="Pwn">Pwn</option><option value="OSINT">OSINT</option><option value="Misc">Misc</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Points</label><input type="number" class="form-input" id="chal-points" value="100" required></div>
      <div class="form-group"><label class="form-label">Description (Markdown)</label><textarea class="form-input" id="chal-desc" rows="4"></textarea></div>
      <div class="form-group"><label class="form-label">Flag (plaintext — will be hashed)</label><input class="form-input form-input-flag" id="chal-flag" placeholder="NFSUCTF{...}" required></div>
      <button type="submit" class="btn btn-primary w-full">Create Challenge</button>
    </form>
  `);
};

window.createChallenge = async function (e) {
  e.preventDefault();
  try {
    await apiPost('/admin/challenges', {
      title: document.getElementById('chal-title').value,
      category: document.getElementById('chal-category').value,
      points: parseInt(document.getElementById('chal-points').value),
      description: document.getElementById('chal-desc').value,
      flag: document.getElementById('chal-flag').value,
    });
    showToast('Challenge created!', 'success');
    closeAdminModal(); renderAdminTab();
  } catch (err) { showToast(err.message, 'error'); }
};

window.showEditChallengeModal = async function (id) {
  openAdminModal('<div class="loading"><div class="spinner"></div></div>');
  try {
    const data = await apiGet('/admin/challenges');
    const c = data.challenges.find(ch => ch.id === id);
    if (!c) return showToast('Challenge not found', 'error');

    document.getElementById('admin-modal-content').innerHTML = `
      <div class="modal-header"><h2 class="modal-title">Edit: ${escapeHtml(c.title)}</h2><button class="modal-close" onclick="closeAdminModal()">✕</button></div>
      <form onsubmit="updateChallenge(event, ${id})">
        <div class="form-group"><label class="form-label">Title</label><input class="form-input" id="edit-chal-title" value="${escapeHtml(c.title)}"></div>
        <div class="form-group"><label class="form-label">Category</label><input class="form-input" id="edit-chal-category" value="${escapeHtml(c.category)}"></div>
        <div class="form-group"><label class="form-label">Points</label><input type="number" class="form-input" id="edit-chal-points" value="${c.points}"></div>
        <div class="form-group"><label class="form-label">Description</label><textarea class="form-input" id="edit-chal-desc" rows="4">${escapeHtml(c.description || '')}</textarea></div>
        <div class="form-group"><label class="form-label">New Flag (leave blank to keep current)</label><input class="form-input form-input-flag" id="edit-chal-flag" placeholder="NFSUCTF{...}"></div>
        <div class="form-group"><label class="form-label"><input type="checkbox" id="edit-chal-active" ${c.is_active ? 'checked' : ''}> Active</label></div>
        <button type="submit" class="btn btn-primary w-full">Update Challenge</button>
      </form>
    `;
  } catch (err) { showToast(err.message, 'error'); }
};

window.updateChallenge = async function (e, id) {
  e.preventDefault();
  const body = {
    title: document.getElementById('edit-chal-title').value,
    category: document.getElementById('edit-chal-category').value,
    points: parseInt(document.getElementById('edit-chal-points').value),
    description: document.getElementById('edit-chal-desc').value,
    isActive: document.getElementById('edit-chal-active').checked,
  };
  const flag = document.getElementById('edit-chal-flag').value;
  if (flag) body.flag = flag;

  try {
    await apiPut(`/admin/challenges/${id}`, body);
    showToast('Challenge updated!', 'success');
    closeAdminModal(); renderAdminTab();
  } catch (err) { showToast(err.message, 'error'); }
};

window.showUploadFileModal = function (id) {
  openAdminModal(`
    <div class="modal-header"><h2 class="modal-title">Upload File</h2><button class="modal-close" onclick="closeAdminModal()">✕</button></div>
    <form onsubmit="uploadFile(event, ${id})" enctype="multipart/form-data">
      <div class="form-group"><label class="form-label">File</label><input type="file" class="form-input" id="upload-file" required></div>
      <button type="submit" class="btn btn-primary w-full">Upload</button>
    </form>
  `);
};

window.uploadFile = async function (e, id) {
  e.preventDefault();
  const fileInput = document.getElementById('upload-file');
  const formData = new FormData();
  formData.append('file', fileInput.files[0]);
  try {
    await apiUpload(`/admin/challenges/${id}/files`, formData);
    showToast('File uploaded!', 'success');
    closeAdminModal();
  } catch (err) { showToast(err.message, 'error'); }
};

window.deleteChallenge = async function (id) {
  if (!confirm('Delete this challenge? This cannot be undone.')) return;
  try {
    await apiDelete(`/admin/challenges/${id}`);
    showToast('Challenge deleted', 'success');
    renderAdminTab();
  } catch (err) { showToast(err.message, 'error'); }
};

// ============================================
// EVENT TAB
// ============================================
async function renderAdminEvent(container) {
  const data = await apiGet('/event/status');
  const event = data.event;

  container.innerHTML = `
    <div class="card mb-24">
      <h3 class="card-title mb-16">Event Status: ${escapeHtml(event?.name || 'No Event')}</h3>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-label">Live</div><div class="stat-value" style="font-size:20px;">${event?.is_live ? '🟢 Yes' : '🔴 No'}</div></div>
        <div class="stat-card"><div class="stat-label">Frozen</div><div class="stat-value" style="font-size:20px;">${event?.is_frozen ? '🧊 Yes' : 'No'}</div></div>
        <div class="stat-card"><div class="stat-label">Archived</div><div class="stat-value" style="font-size:20px;">${event?.is_archived ? '🏛️ Yes' : 'No'}</div></div>
        <div class="stat-card"><div class="stat-label">Maintenance</div><div class="stat-value" style="font-size:20px;">${event?.maintenance_mode ? '🔧 On' : 'Off'}</div></div>
      </div>
      <div class="flex gap-8 mt-16" style="flex-wrap:wrap;">
        <button class="btn btn-primary btn-sm" onclick="eventAction('start')">▶ Start</button>
        <button class="btn btn-danger btn-sm" onclick="eventAction('stop')">⏹ Stop</button>
        <button class="btn btn-secondary btn-sm" onclick="eventAction('freeze')">🧊 Freeze</button>
        <button class="btn btn-secondary btn-sm" onclick="eventAction('unfreeze')">☀ Unfreeze</button>
        <button class="btn btn-secondary btn-sm" onclick="eventAction('archive')">🏛️ Archive</button>
        <button class="btn btn-secondary btn-sm" onclick="eventAction('unarchive')">📂 Unarchive</button>
        <button class="btn btn-secondary btn-sm" onclick="eventAction('maintenance_on')">🔧 Maint. On</button>
        <button class="btn btn-secondary btn-sm" onclick="eventAction('maintenance_off')">✅ Maint. Off</button>
        <button class="btn btn-danger btn-sm" onclick="if(confirm('RESET all scores and submissions?')) eventAction('reset')">💥 Reset All</button>
      </div>
    </div>
    <div class="card">
      <h3 class="card-title mb-16">Event Timing</h3>
      <form onsubmit="updateTiming(event)">
        <div class="form-group"><label class="form-label">Event Name</label><input class="form-input" id="event-name" value="${escapeHtml(event?.name || '')}"></div>
        <div class="form-group"><label class="form-label">Start Time</label><input type="datetime-local" class="form-input" id="event-start" value="${event?.start_time ? new Date(event.start_time).toISOString().slice(0, 16) : ''}"></div>
        <div class="form-group"><label class="form-label">End Time</label><input type="datetime-local" class="form-input" id="event-end" value="${event?.end_time ? new Date(event.end_time).toISOString().slice(0, 16) : ''}"></div>
        <button type="submit" class="btn btn-primary">Update Timing</button>
      </form>
    </div>
  `;
}

window.eventAction = async function (action) {
  try {
    const data = await apiPost('/admin/event/control', { action });
    showToast(data.message, 'success');
    renderAdminTab();
  } catch (err) { showToast(err.message, 'error'); }
};

window.updateTiming = async function (e) {
  e.preventDefault();
  try {
    const body = { name: document.getElementById('event-name').value };
    const start = document.getElementById('event-start').value;
    const end = document.getElementById('event-end').value;
    if (start) body.startTime = new Date(start).toISOString();
    if (end) body.endTime = new Date(end).toISOString();
    await apiPut('/admin/event/timing', body);
    showToast('Timing updated!', 'success');
    renderAdminTab();
  } catch (err) { showToast(err.message, 'error'); }
};

// ============================================
// ANNOUNCEMENTS TAB
// ============================================
async function renderAdminAnnouncements(container) {
  const data = await apiGet('/announcements');

  container.innerHTML = `
    <div class="flex justify-between items-center mb-16">
      <h3>Announcements</h3>
      <button class="btn btn-primary btn-sm" onclick="showCreateAnnouncementModal()">+ New Announcement</button>
    </div>
    ${data.announcements.map(a => `
      <div class="card mb-16">
        <div class="flex justify-between items-center">
          <div>
            <strong>${a.is_pinned ? '📌 ' : ''}${escapeHtml(a.title)}</strong>
            <p class="text-muted text-sm mt-8">${escapeHtml(a.content).substring(0, 200)}</p>
            <span class="text-muted text-sm">${formatTime(a.created_at)}</span>
          </div>
          <button class="btn btn-danger btn-sm" onclick="deleteAnnouncement(${a.id})">🗑</button>
        </div>
      </div>
    `).join('')}
  `;
}

window.showCreateAnnouncementModal = function () {
  openAdminModal(`
    <div class="modal-header"><h2 class="modal-title">Create Announcement</h2><button class="modal-close" onclick="closeAdminModal()">✕</button></div>
    <form onsubmit="createAnnouncement(event)">
      <div class="form-group"><label class="form-label">Title</label><input class="form-input" id="ann-title" required></div>
      <div class="form-group"><label class="form-label">Content</label><textarea class="form-input" id="ann-content" rows="4" required></textarea></div>
      <div class="form-group"><label class="form-label"><input type="checkbox" id="ann-pinned"> Pin announcement</label></div>
      <button type="submit" class="btn btn-primary w-full">Post Announcement</button>
    </form>
  `);
};

window.createAnnouncement = async function (e) {
  e.preventDefault();
  try {
    await apiPost('/admin/announcements', {
      title: document.getElementById('ann-title').value,
      content: document.getElementById('ann-content').value,
      isPinned: document.getElementById('ann-pinned').checked,
    });
    showToast('Announcement posted!', 'success');
    closeAdminModal(); renderAdminTab();
  } catch (err) { showToast(err.message, 'error'); }
};

window.deleteAnnouncement = async function (id) {
  if (!confirm('Delete this announcement?')) return;
  try {
    await apiDelete(`/admin/announcements/${id}`);
    showToast('Announcement deleted', 'success');
    renderAdminTab();
  } catch (err) { showToast(err.message, 'error'); }
};

// ============================================
// ANALYTICS TAB
// ============================================
async function renderAdminAnalytics(container) {
  const data = await apiGet('/admin/analytics');

  container.innerHTML = `
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Total Submissions</div><div class="stat-value">${data.totalSubmissions}</div></div>
      <div class="stat-card"><div class="stat-label">Total Solves</div><div class="stat-value">${data.totalSolves}</div></div>
      <div class="stat-card"><div class="stat-label">Teams with Solves</div><div class="stat-value">${data.teamsWithSolves}</div></div>
      <div class="stat-card"><div class="stat-label">Challenges Solved</div><div class="stat-value">${data.challengesSolved}</div></div>
      <div class="stat-card"><div class="stat-label">Total Teams</div><div class="stat-value">${data.totalTeams}</div></div>
    </div>
    <div class="card mt-24">
      <h3 class="card-title mb-16">Most Failed Challenges</h3>
      ${data.mostFailedChallenges.length === 0 ? '<p class="text-muted">No data yet</p>' : `
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Challenge</th><th>Category</th><th>Wrong Attempts</th></tr></thead>
            <tbody>${data.mostFailedChallenges.map(c => `<tr><td>${escapeHtml(c.title)}</td><td><span class="badge badge-category">${escapeHtml(c.category)}</span></td><td class="font-mono" style="color:var(--red);">${c.wrong_attempts}</td></tr>`).join('')}</tbody>
          </table>
        </div>
      `}
    </div>
    <div class="flex gap-8 mt-24">
      <button class="btn btn-secondary" onclick="exportData('leaderboard')">📥 Export Leaderboard</button>
      <button class="btn btn-secondary" onclick="exportData('solves')">📥 Export Solves</button>
      <button class="btn btn-secondary" onclick="exportData('submissions')">📥 Export Submissions</button>
    </div>
  `;
}

window.exportData = async function (type) {
  try {
    const res = await fetch(`/api/admin/export/${type}`, { credentials: 'same-origin' });
    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      throw new Error(data.message || `Export failed (${res.status})`);
    }
    const text = await res.text();
    downloadCSV(text, `${type}_export.csv`);
    showToast(`${type} exported!`, 'success');
  } catch (err) { showToast(err.message, 'error'); }
};

// ============================================
// SUBMISSIONS TAB
// ============================================
let submissionsPage = 1;

async function renderAdminSubmissions(container) {
  const data = await apiGet(`/admin/submissions?page=${submissionsPage}&limit=20`);

  container.innerHTML = `
    <h3 class="mb-16">Submissions</h3>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Team</th><th>Challenge</th><th>Correct</th><th>IP</th><th>Time</th></tr></thead>
        <tbody>
          ${data.submissions.map(s => `
            <tr>
              <td>${escapeHtml(s.team_name)}</td>
              <td>${escapeHtml(s.challenge_title)}</td>
              <td>${s.is_correct ? '<span class="badge badge-solved">✓</span>' : '<span class="badge badge-disabled">✗</span>'}</td>
              <td class="font-mono text-sm">${escapeHtml(s.ip_address || '—')}</td>
              <td class="text-sm text-muted">${formatTime(s.submitted_at)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
    <div class="pagination">
      <button class="pagination-btn" ${submissionsPage <= 1 ? 'disabled' : ''} onclick="submissionsPage--;renderAdminTab()">← Prev</button>
      <span class="pagination-info">Page ${data.pagination.page} of ${data.pagination.totalPages}</span>
      <button class="pagination-btn" ${submissionsPage >= data.pagination.totalPages ? 'disabled' : ''} onclick="submissionsPage++;renderAdminTab()">Next →</button>
    </div>
  `;
}

// ============================================
// LOGS TAB
// ============================================
let logsPage = 1;

async function renderAdminLogs(container) {
  const data = await apiGet(`/admin/logs?page=${logsPage}&limit=20`);

  container.innerHTML = `
    <h3 class="mb-16">Admin Logs</h3>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Admin</th><th>Action</th><th>Details</th><th>Time</th></tr></thead>
        <tbody>
          ${data.logs.map(l => `
            <tr>
              <td>${escapeHtml(l.admin_name || 'System')}</td>
              <td><span class="badge badge-category">${escapeHtml(l.action)}</span></td>
              <td class="text-sm text-muted">${escapeHtml(JSON.stringify(l.metadata || {})).substring(0, 100)}</td>
              <td class="text-sm text-muted">${formatTime(l.created_at)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
    <div class="pagination">
      <button class="pagination-btn" ${logsPage <= 1 ? 'disabled' : ''} onclick="logsPage--;renderAdminTab()">← Prev</button>
      <span class="pagination-info">Page ${data.pagination.page} of ${data.pagination.totalPages}</span>
      <button class="pagination-btn" ${logsPage >= data.pagination.totalPages ? 'disabled' : ''} onclick="logsPage++;renderAdminTab()">Next →</button>
    </div>
  `;
}

// ============================================
// TOOLS TAB
// ============================================
async function renderAdminTools(container) {
  const susData = await apiGet('/admin/suspicious');

  container.innerHTML = `
    <div class="card mb-24">
      <h3 class="card-title mb-16">🔧 Emergency Override Tools</h3>
      <div class="flex gap-8" style="flex-wrap:wrap;">
        <button class="btn btn-secondary" onclick="showManualSolveModal()">✅ Manual Solve</button>
        <button class="btn btn-secondary" onclick="showRemoveSolveModal()">❌ Remove Solve</button>
        <button class="btn btn-secondary" onclick="showEditPointsModal()">📝 Edit Points</button>
        <button class="btn btn-danger" onclick="recalculateAll()">🔄 Recalculate All Scores</button>
      </div>
    </div>
    <div class="card mb-24">
      <h3 class="card-title mb-16">📝 Edit Rules Content</h3>
      <form onsubmit="updateRulesContent(event)">
        <div class="form-group"><label class="form-label">Content Key</label>
          <select class="form-input" id="content-key"><option value="rules">Rules</option><option value="info">Info</option></select>
        </div>
        <div class="form-group"><label class="form-label">Content (Markdown)</label><textarea class="form-input" id="content-value" rows="6"></textarea></div>
        <button type="submit" class="btn btn-primary">Update Content</button>
      </form>
    </div>
    <div class="card">
      <h3 class="card-title mb-16">🕵️ Suspicious Activity</h3>
      ${susData.suspicious.length === 0 ? '<p class="text-muted">No suspicious activity detected</p>' : `
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Team</th><th>Challenge</th><th>Wrong Attempts</th><th>IPs</th></tr></thead>
            <tbody>
              ${susData.suspicious.map(s => `
                <tr>
                  <td>${escapeHtml(s.team_name)}</td>
                  <td>${escapeHtml(s.challenge_title)}</td>
                  <td class="font-mono" style="color:var(--red);">${s.wrong_attempts}</td>
                  <td class="font-mono text-sm">${(s.ip_addresses || []).join(', ')}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `}
    </div>
  `;
}

window.showManualSolveModal = function () {
  openAdminModal(`
    <div class="modal-header"><h2 class="modal-title">Manual Solve</h2><button class="modal-close" onclick="closeAdminModal()">✕</button></div>
    <form onsubmit="manualSolve(event)">
      <div class="form-group"><label class="form-label">Team ID (UUID)</label><input class="form-input font-mono" id="ms-team" required></div>
      <div class="form-group"><label class="form-label">Challenge ID</label><input type="number" class="form-input" id="ms-challenge" required></div>
      <div class="form-group"><label class="form-label">Points (optional, defaults to challenge points)</label><input type="number" class="form-input" id="ms-points"></div>
      <button type="submit" class="btn btn-primary w-full">Add Solve</button>
    </form>
  `);
};

window.manualSolve = async function (e) {
  e.preventDefault();
  try {
    const body = { teamId: document.getElementById('ms-team').value, challengeId: parseInt(document.getElementById('ms-challenge').value) };
    const pts = document.getElementById('ms-points').value;
    if (pts) body.points = parseInt(pts);
    await apiPost('/admin/solve/manual', body);
    showToast('Solve added!', 'success');
    closeAdminModal();
  } catch (err) { showToast(err.message, 'error'); }
};

window.showRemoveSolveModal = function () {
  openAdminModal(`
    <div class="modal-header"><h2 class="modal-title">Remove Solve</h2><button class="modal-close" onclick="closeAdminModal()">✕</button></div>
    <form onsubmit="removeSolve(event)">
      <div class="form-group"><label class="form-label">Team ID (UUID)</label><input class="form-input font-mono" id="rs-team" required></div>
      <div class="form-group"><label class="form-label">Challenge ID</label><input type="number" class="form-input" id="rs-challenge" required></div>
      <button type="submit" class="btn btn-danger w-full">Remove Solve</button>
    </form>
  `);
};

window.removeSolve = async function (e) {
  e.preventDefault();
  try {
    await apiPost('/admin/solve/remove', {
      teamId: document.getElementById('rs-team').value,
      challengeId: parseInt(document.getElementById('rs-challenge').value),
    });
    showToast('Solve removed!', 'success');
    closeAdminModal();
  } catch (err) { showToast(err.message, 'error'); }
};

window.showEditPointsModal = function () {
  openAdminModal(`
    <div class="modal-header"><h2 class="modal-title">Edit Team Points</h2><button class="modal-close" onclick="closeAdminModal()">✕</button></div>
    <form onsubmit="editPoints(event)">
      <div class="form-group"><label class="form-label">Team ID (UUID)</label><input class="form-input font-mono" id="ep-team" required></div>
      <div class="form-group"><label class="form-label">New Total Points</label><input type="number" class="form-input" id="ep-points" required></div>
      <div class="form-group"><label class="form-label">Reason</label><input class="form-input" id="ep-reason" required></div>
      <button type="submit" class="btn btn-primary w-full">Update Points</button>
    </form>
  `);
};

window.editPoints = async function (e) {
  e.preventDefault();
  try {
    await apiPost('/admin/points/edit', {
      teamId: document.getElementById('ep-team').value,
      points: parseInt(document.getElementById('ep-points').value),
      reason: document.getElementById('ep-reason').value,
    });
    showToast('Points updated!', 'success');
    closeAdminModal();
  } catch (err) { showToast(err.message, 'error'); }
};

window.recalculateAll = async function () {
  if (!confirm('Recalculate ALL team scores from solves table? This will reset points to match solve records.')) return;
  try {
    await apiPost('/admin/recalculate', {});
    showToast('All scores recalculated!', 'success');
  } catch (err) { showToast(err.message, 'error'); }
};

window.updateRulesContent = async function (e) {
  e.preventDefault();
  try {
    await apiPut('/admin/content', {
      contentKey: document.getElementById('content-key').value,
      contentValue: document.getElementById('content-value').value,
    });
    showToast('Content updated!', 'success');
  } catch (err) { showToast(err.message, 'error'); }
};
