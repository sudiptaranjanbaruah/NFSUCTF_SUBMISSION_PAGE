const WebSocket = require('ws');
const logger = require('./logger');

let wss = null;

function initWebSocket(server) {
    wss = new WebSocket.Server({ server, path: '/ws' });

    wss.on('connection', (ws, req) => {
        logger.info({ ip: req.socket.remoteAddress }, 'WebSocket client connected');

        ws.isAlive = true;
        ws.on('pong', () => { ws.isAlive = true; });

        ws.on('close', () => {
            logger.debug('WebSocket client disconnected');
        });

        ws.on('error', (err) => {
            logger.error({ err }, 'WebSocket error');
        });
    });

    // Heartbeat interval to detect stale connections
    const interval = setInterval(() => {
        wss.clients.forEach((ws) => {
            if (!ws.isAlive) return ws.terminate();
            ws.isAlive = false;
            ws.ping();
        });
    }, 30000);

    wss.on('close', () => clearInterval(interval));

    logger.info('WebSocket server initialized at /ws');
    return wss;
}

/**
 * Broadcast a message to all connected clients
 */
function broadcast(type, data) {
    if (!wss) return;
    const message = JSON.stringify({ type, data, timestamp: new Date().toISOString() });
    wss.clients.forEach((client) => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(message);
        }
    });
}

/**
 * Broadcast leaderboard update
 */
function broadcastLeaderboard(leaderboard) {
    broadcast('LEADERBOARD_UPDATE', leaderboard);
}

/**
 * Broadcast new solve notification
 */
function broadcastSolve(solveData) {
    broadcast('NEW_SOLVE', solveData);
}

/**
 * Broadcast announcement
 */
function broadcastAnnouncement(announcement) {
    broadcast('NEW_ANNOUNCEMENT', announcement);
}

module.exports = {
    initWebSocket,
    broadcast,
    broadcastLeaderboard,
    broadcastSolve,
    broadcastAnnouncement,
};
