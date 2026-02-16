const jwt = require('jsonwebtoken');
const db = require('../utils/db');
const logger = require('../utils/logger');

/**
 * Verify JWT token from cookie or Authorization header
 */
function verifyToken(req, res, next) {
    const token = req.cookies?.token || req.headers.authorization?.replace('Bearer ', '');

    if (!token) {
        return res.status(401).json({ message: 'Authentication required' });
    }

    try {
        const decoded = jwt.verify(token, process.env.JWT_SECRET);
        req.team = decoded;
        next();
    } catch (err) {
        if (err.name === 'TokenExpiredError') {
            return res.status(401).json({ message: 'Session expired. Please login again.' });
        }
        logger.warn({ err }, 'Invalid token');
        return res.status(401).json({ message: 'Invalid token' });
    }
}

/**
 * Verify user has admin or super_admin role
 */
function verifyAdmin(req, res, next) {
    if (!req.team || !['admin', 'super_admin'].includes(req.team.role)) {
        return res.status(403).json({ message: 'Admin access required' });
    }
    next();
}

/**
 * Verify event is live and within time window
 */
async function verifyEventLive(req, res, next) {
    try {
        const result = await db.query('SELECT * FROM events ORDER BY id DESC LIMIT 1');
        const event = result.rows[0];

        if (!event) {
            return res.status(503).json({ message: 'No event configured' });
        }

        if (event.maintenance_mode) {
            return res.status(503).json({ message: 'System is under maintenance' });
        }

        if (!event.is_live) {
            return res.status(403).json({ message: 'Competition is not currently active' });
        }

        if (event.is_archived) {
            return res.status(403).json({ message: 'Competition has been archived. Flag validation is disabled.' });
        }

        const now = new Date();
        if (event.start_time && now < new Date(event.start_time)) {
            return res.status(403).json({ message: 'Competition has not started yet' });
        }

        if (event.end_time && now > new Date(event.end_time)) {
            return res.status(403).json({ message: 'Competition has ended' });
        }

        req.event = event;
        next();
    } catch (err) {
        logger.error({ err }, 'Error checking event state');
        next(err);
    }
}

module.exports = { verifyToken, verifyAdmin, verifyEventLive };
