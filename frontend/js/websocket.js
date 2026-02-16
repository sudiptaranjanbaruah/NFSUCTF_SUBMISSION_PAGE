// ============================================
// WebSocket Client
// ============================================
let ws = null;
let wsReconnectTimer = null;

function initWebSocket() {
    if (ws && ws.readyState === WebSocket.OPEN) return;

    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${window.location.host}/ws`;

    try {
        ws = new WebSocket(wsUrl);

        ws.onopen = () => {
            console.log('WebSocket connected');
            if (wsReconnectTimer) {
                clearTimeout(wsReconnectTimer);
                wsReconnectTimer = null;
            }
        };

        ws.onmessage = (event) => {
            try {
                const msg = JSON.parse(event.data);
                handleWSMessage(msg);
            } catch (err) {
                console.error('WS parse error:', err);
            }
        };

        ws.onclose = () => {
            console.log('WebSocket disconnected, reconnecting in 5s...');
            wsReconnectTimer = setTimeout(initWebSocket, 5000);
        };

        ws.onerror = (err) => {
            console.error('WebSocket error:', err);
        };
    } catch (err) {
        console.error('Failed to init WebSocket:', err);
    }
}

function handleWSMessage(msg) {
    switch (msg.type) {
        case 'NEW_SOLVE':
            showToast(`🏴 ${msg.data.teamName} solved "${msg.data.challengeTitle}" (+${msg.data.points}pts)`, 'info');
            // Refresh leaderboard if on that page
            if (typeof refreshLeaderboard === 'function') refreshLeaderboard();
            break;

        case 'LEADERBOARD_UPDATE':
            if (typeof onLeaderboardUpdate === 'function') onLeaderboardUpdate(msg.data);
            break;

        case 'NEW_ANNOUNCEMENT':
            showToast(`📢 ${msg.data.title}`, 'info');
            if (typeof refreshAnnouncements === 'function') refreshAnnouncements();
            break;
    }
}
