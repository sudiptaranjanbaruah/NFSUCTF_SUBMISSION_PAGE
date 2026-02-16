// ============================================
// TEAM PROFILE PAGE
// ============================================
registerRoute('/profile', renderProfilePage);

async function renderProfilePage() {
    const app = document.getElementById('app');
    showLoading(app);

    try {
        const data = await apiGet('/team/profile');
        const team = data.team;
        const solves = data.solves;

        app.innerHTML = `
      <div class="page-header">
        <h1 class="page-title">Team Profile</h1>
      </div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Team Name</div>
          <div class="stat-value" style="font-size:20px;">${escapeHtml(team.name)}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Rank</div>
          <div class="stat-value">#${team.rank}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Points</div>
          <div class="stat-value">${team.total_points}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Challenges Solved</div>
          <div class="stat-value">${team.total_solves}</div>
        </div>
      </div>
      <div class="card mt-24">
        <div class="card-header">
          <h2 class="card-title">Solve History</h2>
        </div>
        ${solves.length === 0 ? '<p class="text-muted">No solves yet</p>' : `
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Challenge</th>
                  <th>Category</th>
                  <th>Points</th>
                  <th>Solved At</th>
                </tr>
              </thead>
              <tbody>
                ${solves.map(s => `
                  <tr>
                    <td><strong>${escapeHtml(s.challenge_name)}</strong></td>
                    <td><span class="badge badge-category">${escapeHtml(s.category)}</span></td>
                    <td class="font-mono text-accent">+${s.points_awarded}</td>
                    <td class="text-muted text-sm">${formatTime(s.solved_at)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `}
      </div>
    `;
    } catch (err) {
        app.innerHTML = `<div class="error-page"><div class="error-message">${escapeHtml(err.message)}</div></div>`;
    }
}
