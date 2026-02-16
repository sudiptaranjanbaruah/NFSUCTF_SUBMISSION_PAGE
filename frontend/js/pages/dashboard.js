// ============================================
// DASHBOARD PAGE
// ============================================
registerRoute('/dashboard', renderDashboard);

async function renderDashboard() {
    const app = document.getElementById('app');
    showLoading(app);

    try {
        const [profileData, eventData, announcementsData, leaderboardData] = await Promise.all([
            apiGet('/team/profile'),
            apiGet('/event/status'),
            apiGet('/announcements'),
            apiGet('/leaderboard'),
        ]);

        const team = profileData.team;
        const event = eventData.event;
        const announcements = announcementsData.announcements;
        const lb = leaderboardData.leaderboard || [];

        let announcementHtml = '';
        if (announcements.length > 0) {
            announcementHtml = announcements.slice(0, 3).map(a => `
        <div class="announcement-bar">
          <span class="icon">${a.is_pinned ? '📌' : '📢'}</span>
          <div class="announcement-bar-content">
            <div class="announcement-bar-title">${escapeHtml(a.title)}</div>
            <div class="announcement-bar-text">${escapeHtml(a.content).substring(0, 150)}</div>
          </div>
          <span class="text-muted text-sm">${timeAgo(a.created_at)}</span>
        </div>
      `).join('');
        }

        let archiveBanner = '';
        if (event?.is_archived) {
            archiveBanner = '<div class="archive-banner">🏛️ This competition has been archived. Results are final.</div>';
        }

        let eventStatusHtml = '';
        if (event) {
            const status = event.maintenance_mode ? '🔧 Maintenance' :
                event.is_archived ? '🏛️ Archived' :
                    event.is_live ? '🟢 Live' : '🔴 Not Started';
            eventStatusHtml = `
        <div class="stat-card">
          <div class="stat-label">Event Status</div>
          <div class="stat-value" style="font-size:18px;">${status}</div>
          <div class="stat-change">${escapeHtml(event.name || 'CTF Event')}</div>
        </div>
      `;
        }

        app.innerHTML = `
      ${archiveBanner}
      ${announcementHtml}
      <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Welcome back, ${escapeHtml(team.name)}</p>
      </div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Your Rank</div>
          <div class="stat-value">#${team.rank}</div>
          <div class="stat-change">out of ${lb.length} teams</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Points</div>
          <div class="stat-value">${team.total_points}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Challenges Solved</div>
          <div class="stat-value">${team.total_solves}</div>
        </div>
        ${eventStatusHtml}
      </div>
      <div class="card mt-24">
        <div class="card-header">
          <h2 class="card-title">Recent Solves</h2>
        </div>
        <div id="recent-solves">
          ${profileData.solves.length === 0 ? '<p class="text-muted text-sm">No solves yet. Head to challenges!</p>' :
                profileData.solves.slice(0, 10).map(s => `
              <div class="solve-item">
                <div>
                  <span class="solve-team">${escapeHtml(s.challenge_name)}</span>
                  <span class="badge badge-category ml-8">${escapeHtml(s.category)}</span>
                </div>
                <div class="flex items-center gap-12">
                  <span class="badge badge-points">+${s.points_awarded}</span>
                  <span class="solve-time">${timeAgo(s.solved_at)}</span>
                </div>
              </div>
            `).join('')}
        </div>
      </div>
    `;
    } catch (err) {
        app.innerHTML = `<div class="error-page"><div class="error-code">!</div><div class="error-message">${escapeHtml(err.message)}</div></div>`;
    }
}
