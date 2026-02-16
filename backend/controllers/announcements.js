const db = require('../utils/db');

/**
 * GET /api/announcements
 */
async function getAnnouncements(req, res, next) {
    try {
        const result = await db.query(
            `SELECT id, title, content, is_pinned, created_at 
       FROM announcements WHERE is_active = true 
       ORDER BY is_pinned DESC, created_at DESC`
        );
        res.json({ announcements: result.rows });
    } catch (err) {
        next(err);
    }
}

/**
 * GET /api/info
 */
async function getInfo(req, res, next) {
    try {
        const result = await db.query(
            `SELECT content_key, content_value FROM site_content WHERE content_key IN ('rules', 'info')`
        );
        const content = {};
        result.rows.forEach(r => { content[r.content_key] = r.content_value; });
        res.json({ content });
    } catch (err) {
        next(err);
    }
}

/**
 * GET /api/event/status
 */
async function getEventStatus(req, res, next) {
    try {
        const result = await db.query('SELECT id, name, start_time, end_time, is_live, is_frozen, is_archived, maintenance_mode FROM events ORDER BY id DESC LIMIT 1');
        const event = result.rows[0] || null;
        res.json({ event });
    } catch (err) {
        next(err);
    }
}

module.exports = { getAnnouncements, getInfo, getEventStatus };
