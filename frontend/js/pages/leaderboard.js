// ============================================
// LEADERBOARD PAGE
// ============================================
registerRoute('/leaderboard', renderLeaderboardPage);

let leaderboardRefreshInterval = null;

async function renderLeaderboardPage() {
    const app = document.getElementById('app');
    showLoading(app);

    try {
        await loadLeaderboard(app);
        // Auto-refresh every 30 seconds
        if (leaderboardRefreshInterval) clearInterval(leaderboardRefreshInterval);
        leaderboardRefreshInterval = setInterval(() => {
            if (getCurrentHash() === '/leaderboard') refreshLeaderboard();
        }, 30000);
    } catch (err) {
        app.innerHTML = `<div class="error-page"><div class="error-message">${escapeHtml(err.message)}</div></div>`;
    }
}

async function loadLeaderboard(container) {
    const data = await apiGet('/leaderboard');
    const lb = data.leaderboard || [];
    const user = getUser();

    container = container || document.getElementById('app');

    container.innerHTML = `
    <div class="page-header">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="page-title">Leaderboard</h1>
          <p class="page-subtitle">${lb.length} teams competing</p>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="refreshLeaderboard()">↻ Refresh</button>
      </div>
    </div>
    ${data.frozen ? '<div class="leaderboard-frozen">🧊 Scoreboard is frozen. Rankings may not reflect latest solves.</div>' : ''}
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th style="width:60px;">Rank</th>
            <th>Team</th>
            <th style="width:120px;">Points</th>
            <th style="width:100px;">Solves</th>
            <th style="width:160px;">Last Solve</th>
          </tr>
        </thead>
        <tbody>
          ${lb.length === 0 ? '<tr><td colspan="5" class="text-center text-muted">No teams on the leaderboard yet</td></tr>' :
            lb.map((t, i) => {
                const isMe = user && (t.id === user.id || t.name === user.name);
                const rankClass = i < 3 ? `rank-${i + 1}` : '';
                const medals = ['🥇', '🥈', '🥉'];
                return `
                <tr class="${isMe ? 'highlight-row' : ''}">
                  <td class="rank-cell ${rankClass}">${i < 3 ? medals[i] : (t.rank || i + 1)}</td>
                  <td><strong>${escapeHtml(t.name)}</strong> ${isMe ? '<span class="badge badge-solved" style="margin-left:6px;">You</span>' : ''}</td>
                  <td class="font-mono" style="font-weight:700;color:var(--accent);">${t.totalPoints || t.total_points || 0}</td>
                  <td class="font-mono">${t.totalSolves || t.total_solves || 0}</td>
                  <td class="text-muted text-sm">${timeAgo(t.lastSolveTime || t.last_solve_time)}</td>
                </tr>
              `;
            }).join('')}
        </tbody>
      </table>
    </div>
  `;
}

async function refreshLeaderboard() {
    try {
        await loadLeaderboard();
    } catch (err) {
        console.error('Leaderboard refresh error:', err);
    }
}

function onLeaderboardUpdate(data) {
    // Real-time update from WebSocket
    if (getCurrentHash() === '/leaderboard') {
        refreshLeaderboard();
    }
}
