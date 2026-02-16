const bcrypt = require('bcryptjs');
const multer = require('multer');
const db = require('../utils/db');
const logger = require('../utils/logger');
const { uploadFile, deleteFile } = require('../utils/supabase');
const { recalculateScores } = require('../services/scoring');
const { exportLeaderboard, exportSolves, exportSubmissions } = require('../services/export');
const { broadcastLeaderboard, broadcastAnnouncement } = require('../utils/websocket');
const {
    createTeamSchema, bonusPointsSchema, eventControlSchema, eventTimingSchema,
    announcementSchema, siteContentSchema, manualSolveSchema, editPointsSchema, paginationSchema,
} = require('../validators/admin');
const { createChallengeSchema, updateChallengeSchema } = require('../validators/challenge');

// Multer for file uploads (in-memory for forwarding to Supabase)
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 50 * 1024 * 1024 } });

// Helper: log admin action
async function logAction(adminId, action, metadata = {}) {
    try {
        await db.query(
            'INSERT INTO admin_logs (admin_id, action, metadata) VALUES ($1, $2, $3)',
            [adminId, action, JSON.stringify(metadata)]
        );
    } catch (err) {
        logger.error({ err }, 'Failed to log admin action');
    }
}

// ============================================
// TEAM MANAGEMENT
// ============================================
async function createTeam(req, res, next) {
    try {
        const data = createTeamSchema.parse(req.body);
        const hash = await bcrypt.hash(data.password, 12);
        const result = await db.query(
            `INSERT INTO teams (name, username, password_hash, role) VALUES ($1, $2, $3, $4) RETURNING id, name, username, role`,
            [data.name, data.username, hash, data.role]
        );
        await logAction(req.team.teamId, 'CREATE_TEAM', { teamName: data.name, role: data.role });
        res.status(201).json({ team: result.rows[0] });
    } catch (err) { next(err); }
}

async function listTeams(req, res, next) {
    try {
        const result = await db.query(
            `SELECT id, name, username, total_points, total_solves, last_solve_time, role, is_active, created_at
       FROM teams ORDER BY created_at DESC`
        );
        res.json({ teams: result.rows });
    } catch (err) { next(err); }
}

async function resetPassword(req, res, next) {
    try {
        const { teamId } = req.params;
        const { password } = req.body;
        if (!password || password.length < 6) {
            return res.status(400).json({ message: 'Password must be at least 6 characters' });
        }
        const hash = await bcrypt.hash(password, 12);
        await db.query('UPDATE teams SET password_hash = $1 WHERE id = $2', [hash, teamId]);
        await logAction(req.team.teamId, 'RESET_PASSWORD', { targetTeamId: teamId });
        res.json({ message: 'Password reset successfully' });
    } catch (err) { next(err); }
}

async function addBonusPoints(req, res, next) {
    try {
        const data = bonusPointsSchema.parse(req.body);
        await db.query('UPDATE teams SET total_points = total_points + $1 WHERE id = $2', [data.points, data.teamId]);
        await logAction(req.team.teamId, 'BONUS_POINTS', { targetTeamId: data.teamId, points: data.points, reason: data.reason });
        res.json({ message: `${data.points > 0 ? 'Added' : 'Deducted'} ${Math.abs(data.points)} points` });
    } catch (err) { next(err); }
}

async function toggleTeamActive(req, res, next) {
    try {
        const { teamId } = req.params;
        const result = await db.query(
            'UPDATE teams SET is_active = NOT is_active WHERE id = $1 RETURNING id, name, is_active',
            [teamId]
        );
        if (result.rows.length === 0) return res.status(404).json({ message: 'Team not found' });
        await logAction(req.team.teamId, 'TOGGLE_TEAM_ACTIVE', { targetTeamId: teamId, isActive: result.rows[0].is_active });
        res.json({ team: result.rows[0] });
    } catch (err) { next(err); }
}

// ============================================
// CHALLENGE MANAGEMENT
// ============================================
async function createChallenge(req, res, next) {
    try {
        const data = createChallengeSchema.parse(req.body);
        const flagHash = await bcrypt.hash(data.flag, 12);
        const result = await db.query(
            `INSERT INTO challenges (title, description, category, points, flag_hash, is_active, max_attempts)
       VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id, title, category, points, is_active`,
            [data.title, data.description, data.category, data.points, flagHash, data.isActive, data.maxAttempts]
        );
        await logAction(req.team.teamId, 'CREATE_CHALLENGE', { challengeId: result.rows[0].id, title: data.title });
        res.status(201).json({ challenge: result.rows[0] });
    } catch (err) { next(err); }
}

async function listAllChallenges(req, res, next) {
    try {
        const result = await db.query(`
      SELECT c.id, c.title, c.description, c.category, c.points, c.is_active, c.max_attempts, c.created_at,
             (SELECT COUNT(*) FROM solves s WHERE s.challenge_id = c.id) as solve_count
      FROM challenges c ORDER BY c.created_at DESC
    `);
        res.json({ challenges: result.rows });
    } catch (err) { next(err); }
}

async function updateChallenge(req, res, next) {
    try {
        const { id } = req.params;
        const data = updateChallengeSchema.parse(req.body);
        const updates = [];
        const values = [];
        let paramCount = 0;

        if (data.title !== undefined) { paramCount++; updates.push(`title = $${paramCount}`); values.push(data.title); }
        if (data.description !== undefined) { paramCount++; updates.push(`description = $${paramCount}`); values.push(data.description); }
        if (data.category !== undefined) { paramCount++; updates.push(`category = $${paramCount}`); values.push(data.category); }
        if (data.points !== undefined) { paramCount++; updates.push(`points = $${paramCount}`); values.push(data.points); }
        if (data.isActive !== undefined) { paramCount++; updates.push(`is_active = $${paramCount}`); values.push(data.isActive); }
        if (data.maxAttempts !== undefined) { paramCount++; updates.push(`max_attempts = $${paramCount}`); values.push(data.maxAttempts); }
        if (data.flag) {
            const flagHash = await bcrypt.hash(data.flag, 12);
            paramCount++; updates.push(`flag_hash = $${paramCount}`); values.push(flagHash);
        }

        if (updates.length === 0) return res.status(400).json({ message: 'No fields to update' });

        paramCount++;
        values.push(id);
        const result = await db.query(
            `UPDATE challenges SET ${updates.join(', ')} WHERE id = $${paramCount} RETURNING id, title, category, points, is_active`,
            values
        );
        if (result.rows.length === 0) return res.status(404).json({ message: 'Challenge not found' });
        await logAction(req.team.teamId, 'UPDATE_CHALLENGE', { challengeId: id, updates: Object.keys(data) });
        res.json({ challenge: result.rows[0] });
    } catch (err) { next(err); }
}

async function deleteChallenge(req, res, next) {
    try {
        const { id } = req.params;
        await db.query('DELETE FROM challenges WHERE id = $1', [id]);
        await logAction(req.team.teamId, 'DELETE_CHALLENGE', { challengeId: id });
        res.json({ message: 'Challenge deleted' });
    } catch (err) { next(err); }
}

async function uploadChallengeFile(req, res, next) {
    try {
        const { id } = req.params;
        if (!req.file) return res.status(400).json({ message: 'No file provided' });

        const filePath = `challenges/${id}/${Date.now()}_${req.file.originalname}`;
        const fileUrl = await uploadFile(filePath, req.file.buffer, req.file.mimetype);

        const result = await db.query(
            'INSERT INTO challenge_files (challenge_id, file_name, file_url, file_size) VALUES ($1, $2, $3, $4) RETURNING *',
            [id, req.file.originalname, fileUrl, req.file.size]
        );
        await logAction(req.team.teamId, 'UPLOAD_FILE', { challengeId: id, fileName: req.file.originalname });
        res.status(201).json({ file: result.rows[0] });
    } catch (err) { next(err); }
}

async function deleteChallengeFile(req, res, next) {
    try {
        const { fileId } = req.params;
        const result = await db.query('SELECT * FROM challenge_files WHERE id = $1', [fileId]);
        if (result.rows.length === 0) return res.status(404).json({ message: 'File not found' });

        await db.query('DELETE FROM challenge_files WHERE id = $1', [fileId]);
        await logAction(req.team.teamId, 'DELETE_FILE', { fileId });
        res.json({ message: 'File deleted' });
    } catch (err) { next(err); }
}

// ============================================
// EVENT CONTROL
// ============================================
async function controlEvent(req, res, next) {
    try {
        const { action } = eventControlSchema.parse(req.body);
        const eventResult = await db.query('SELECT * FROM events ORDER BY id DESC LIMIT 1');
        if (eventResult.rows.length === 0) return res.status(404).json({ message: 'No event found' });
        const event = eventResult.rows[0];

        switch (action) {
            case 'start':
                await db.query('UPDATE events SET is_live = true, is_archived = false WHERE id = $1', [event.id]);
                break;
            case 'stop':
                await db.query('UPDATE events SET is_live = false WHERE id = $1', [event.id]);
                break;
            case 'freeze': {
                // Snapshot current leaderboard
                const lb = await db.query(
                    `SELECT name, total_points, total_solves, last_solve_time FROM teams 
           WHERE role = 'team' AND is_active = true 
           ORDER BY total_points DESC, last_solve_time ASC NULLS LAST, total_solves DESC`
                );
                const snapshot = lb.rows.map((t, i) => ({ rank: i + 1, ...t }));
                await db.query('UPDATE events SET is_frozen = true, frozen_leaderboard = $1 WHERE id = $2', [JSON.stringify(snapshot), event.id]);
                break;
            }
            case 'unfreeze':
                await db.query('UPDATE events SET is_frozen = false, frozen_leaderboard = NULL WHERE id = $1', [event.id]);
                break;
            case 'archive':
                await db.query('UPDATE events SET is_archived = true, is_live = false WHERE id = $1', [event.id]);
                break;
            case 'unarchive':
                await db.query('UPDATE events SET is_archived = false WHERE id = $1', [event.id]);
                break;
            case 'maintenance_on':
                await db.query('UPDATE events SET maintenance_mode = true WHERE id = $1', [event.id]);
                break;
            case 'maintenance_off':
                await db.query('UPDATE events SET maintenance_mode = false WHERE id = $1', [event.id]);
                break;
            case 'reset':
                await db.withTransaction(async (client) => {
                    await client.query('DELETE FROM submissions');
                    await client.query('DELETE FROM solves');
                    await client.query("UPDATE teams SET total_points = 0, total_solves = 0, last_solve_time = NULL WHERE role = 'team'");
                    await client.query('UPDATE events SET is_frozen = false, frozen_leaderboard = NULL WHERE id = $1', [event.id]);
                });
                break;
        }

        await logAction(req.team.teamId, 'EVENT_CONTROL', { action });
        const updated = await db.query('SELECT id, name, start_time, end_time, is_live, is_frozen, is_archived, maintenance_mode FROM events WHERE id = $1', [event.id]);
        res.json({ message: `Event action '${action}' executed`, event: updated.rows[0] });
    } catch (err) { next(err); }
}

async function updateEventTiming(req, res, next) {
    try {
        const data = eventTimingSchema.parse(req.body);
        const eventResult = await db.query('SELECT id FROM events ORDER BY id DESC LIMIT 1');
        if (eventResult.rows.length === 0) return res.status(404).json({ message: 'No event found' });
        const eventId = eventResult.rows[0].id;

        const updates = [];
        const values = [];
        let pc = 0;
        if (data.name !== undefined) { pc++; updates.push(`name = $${pc}`); values.push(data.name); }
        if (data.startTime !== undefined) { pc++; updates.push(`start_time = $${pc}`); values.push(data.startTime); }
        if (data.endTime !== undefined) { pc++; updates.push(`end_time = $${pc}`); values.push(data.endTime); }

        if (updates.length === 0) return res.status(400).json({ message: 'No fields to update' });

        pc++;
        values.push(eventId);
        const result = await db.query(`UPDATE events SET ${updates.join(', ')} WHERE id = $${pc} RETURNING *`, values);
        await logAction(req.team.teamId, 'UPDATE_EVENT_TIMING', data);
        res.json({ event: result.rows[0] });
    } catch (err) { next(err); }
}

// ============================================
// ANNOUNCEMENTS
// ============================================
async function createAnnouncement(req, res, next) {
    try {
        const data = announcementSchema.parse(req.body);
        const result = await db.query(
            'INSERT INTO announcements (title, content, is_pinned) VALUES ($1, $2, $3) RETURNING *',
            [data.title, data.content, data.isPinned]
        );
        await logAction(req.team.teamId, 'CREATE_ANNOUNCEMENT', { title: data.title });
        broadcastAnnouncement(result.rows[0]);
        res.status(201).json({ announcement: result.rows[0] });
    } catch (err) { next(err); }
}

async function updateAnnouncement(req, res, next) {
    try {
        const { id } = req.params;
        const data = announcementSchema.parse(req.body);
        const result = await db.query(
            'UPDATE announcements SET title = $1, content = $2, is_pinned = $3 WHERE id = $4 RETURNING *',
            [data.title, data.content, data.isPinned, id]
        );
        if (result.rows.length === 0) return res.status(404).json({ message: 'Announcement not found' });
        res.json({ announcement: result.rows[0] });
    } catch (err) { next(err); }
}

async function deleteAnnouncement(req, res, next) {
    try {
        const { id } = req.params;
        await db.query('UPDATE announcements SET is_active = false WHERE id = $1', [id]);
        res.json({ message: 'Announcement deleted' });
    } catch (err) { next(err); }
}

// ============================================
// SITE CONTENT
// ============================================
async function updateSiteContent(req, res, next) {
    try {
        const data = siteContentSchema.parse(req.body);
        await db.query(
            `INSERT INTO site_content (content_key, content_value, updated_at) VALUES ($1, $2, NOW())
       ON CONFLICT (content_key) DO UPDATE SET content_value = $2, updated_at = NOW()`,
            [data.contentKey, data.contentValue]
        );
        await logAction(req.team.teamId, 'UPDATE_SITE_CONTENT', { contentKey: data.contentKey });
        res.json({ message: 'Content updated' });
    } catch (err) { next(err); }
}

// ============================================
// ANALYTICS
// ============================================
async function getAnalytics(req, res, next) {
    try {
        const [submissionCount, solveStats, teamStats, failedChallenges] = await Promise.all([
            db.query('SELECT COUNT(*) as count FROM submissions'),
            db.query(`SELECT 
        COUNT(*) as total_solves,
        COUNT(DISTINCT team_id) as teams_with_solves,
        COUNT(DISTINCT challenge_id) as challenges_solved
        FROM solves`),
            db.query(`SELECT COUNT(*) as total_teams FROM teams WHERE role = 'team'`),
            db.query(`
        SELECT c.title, c.category, COUNT(sub.id) as wrong_attempts
        FROM submissions sub
        JOIN challenges c ON sub.challenge_id = c.id
        WHERE sub.is_correct = false
        GROUP BY c.id, c.title, c.category
        ORDER BY wrong_attempts DESC
        LIMIT 10
      `),
        ]);

        res.json({
            totalSubmissions: parseInt(submissionCount.rows[0].count),
            totalSolves: parseInt(solveStats.rows[0].total_solves),
            teamsWithSolves: parseInt(solveStats.rows[0].teams_with_solves),
            challengesSolved: parseInt(solveStats.rows[0].challenges_solved),
            totalTeams: parseInt(teamStats.rows[0].total_teams),
            mostFailedChallenges: failedChallenges.rows,
        });
    } catch (err) { next(err); }
}

async function getSubmissions(req, res, next) {
    try {
        const { page, limit } = paginationSchema.parse(req.query);
        const offset = (page - 1) * limit;

        const [result, countResult] = await Promise.all([
            db.query(`
        SELECT sub.id, t.name as team_name, c.title as challenge_title, 
               sub.is_correct, sub.ip_address, sub.submitted_at
        FROM submissions sub
        JOIN teams t ON sub.team_id = t.id
        JOIN challenges c ON sub.challenge_id = c.id
        ORDER BY sub.submitted_at DESC
        LIMIT $1 OFFSET $2
      `, [limit, offset]),
            db.query('SELECT COUNT(*) as count FROM submissions'),
        ]);

        res.json({
            submissions: result.rows,
            pagination: {
                page, limit,
                total: parseInt(countResult.rows[0].count),
                totalPages: Math.ceil(parseInt(countResult.rows[0].count) / limit),
            },
        });
    } catch (err) { next(err); }
}

async function getAdminLogs(req, res, next) {
    try {
        const { page, limit } = paginationSchema.parse(req.query);
        const offset = (page - 1) * limit;

        const [result, countResult] = await Promise.all([
            db.query(`
        SELECT al.id, al.action, al.metadata, al.created_at, t.name as admin_name
        FROM admin_logs al
        LEFT JOIN teams t ON al.admin_id = t.id
        ORDER BY al.created_at DESC
        LIMIT $1 OFFSET $2
      `, [limit, offset]),
            db.query('SELECT COUNT(*) as count FROM admin_logs'),
        ]);

        res.json({
            logs: result.rows,
            pagination: {
                page, limit,
                total: parseInt(countResult.rows[0].count),
                totalPages: Math.ceil(parseInt(countResult.rows[0].count) / limit),
            },
        });
    } catch (err) { next(err); }
}

// ============================================
// EMERGENCY OVERRIDES
// ============================================
async function manualSolve(req, res, next) {
    try {
        const data = manualSolveSchema.parse(req.body);
        const challenge = await db.query('SELECT points FROM challenges WHERE id = $1', [data.challengeId]);
        if (challenge.rows.length === 0) return res.status(404).json({ message: 'Challenge not found' });

        const points = data.points || challenge.rows[0].points;

        await db.withTransaction(async (client) => {
            await client.query(
                'INSERT INTO solves (team_id, challenge_id, points_awarded) VALUES ($1, $2, $3) ON CONFLICT (team_id, challenge_id) DO NOTHING',
                [data.teamId, data.challengeId, points]
            );
            await client.query(
                'UPDATE teams SET total_points = total_points + $1, total_solves = total_solves + 1, last_solve_time = NOW() WHERE id = $2',
                [points, data.teamId]
            );
        });

        await logAction(req.team.teamId, 'MANUAL_SOLVE', data);
        res.json({ message: 'Solve added manually' });
    } catch (err) { next(err); }
}

async function removeSolve(req, res, next) {
    try {
        const data = manualSolveSchema.parse(req.body);

        const solve = await db.query('SELECT points_awarded FROM solves WHERE team_id = $1 AND challenge_id = $2', [data.teamId, data.challengeId]);
        if (solve.rows.length === 0) return res.status(404).json({ message: 'Solve not found' });

        await db.withTransaction(async (client) => {
            await client.query('DELETE FROM solves WHERE team_id = $1 AND challenge_id = $2', [data.teamId, data.challengeId]);
            await client.query(
                'UPDATE teams SET total_points = total_points - $1, total_solves = GREATEST(total_solves - 1, 0) WHERE id = $2',
                [solve.rows[0].points_awarded, data.teamId]
            );
        });

        await logAction(req.team.teamId, 'REMOVE_SOLVE', data);
        res.json({ message: 'Solve removed' });
    } catch (err) { next(err); }
}

async function editTeamPoints(req, res, next) {
    try {
        const data = editPointsSchema.parse(req.body);
        await db.query('UPDATE teams SET total_points = $1 WHERE id = $2', [data.points, data.teamId]);
        await logAction(req.team.teamId, 'EDIT_TEAM_POINTS', data);
        res.json({ message: 'Team points updated' });
    } catch (err) { next(err); }
}

async function recalculate(req, res, next) {
    try {
        await recalculateScores();
        await logAction(req.team.teamId, 'RECALCULATE_SCORES', {});
        res.json({ message: 'All scores recalculated from solves table' });
    } catch (err) { next(err); }
}

// ============================================
// EXPORTS
// ============================================
async function exportCSV(req, res, next) {
    try {
        const { type } = req.params;
        let csv, filename;
        switch (type) {
            case 'leaderboard': csv = await exportLeaderboard(); filename = 'leaderboard.csv'; break;
            case 'solves': csv = await exportSolves(); filename = 'solves.csv'; break;
            case 'submissions': csv = await exportSubmissions(); filename = 'submissions.csv'; break;
            default: return res.status(400).json({ message: 'Invalid export type' });
        }
        res.setHeader('Content-Type', 'text/csv');
        res.setHeader('Content-Disposition', `attachment; filename="${filename}"`);
        res.send(csv);
    } catch (err) { next(err); }
}

// ============================================
// SUSPICIOUS ACTIVITY
// ============================================
async function getSuspiciousActivity(req, res, next) {
    try {
        const result = await db.query(`
      SELECT t.name as team_name, c.title as challenge_title, 
             COUNT(*) as wrong_attempts, 
             array_agg(DISTINCT sub.ip_address) as ip_addresses
      FROM submissions sub
      JOIN teams t ON sub.team_id = t.id
      JOIN challenges c ON sub.challenge_id = c.id
      WHERE sub.is_correct = false
      GROUP BY t.id, t.name, c.id, c.title
      HAVING COUNT(*) >= 20
      ORDER BY wrong_attempts DESC
    `);
        res.json({ suspicious: result.rows });
    } catch (err) { next(err); }
}

module.exports = {
    createTeam, listTeams, resetPassword, addBonusPoints, toggleTeamActive,
    createChallenge, listAllChallenges, updateChallenge, deleteChallenge,
    uploadChallengeFile, deleteChallengeFile, upload,
    controlEvent, updateEventTiming,
    createAnnouncement, updateAnnouncement, deleteAnnouncement,
    updateSiteContent,
    getAnalytics, getSubmissions, getAdminLogs,
    manualSolve, removeSolve, editTeamPoints, recalculate,
    exportCSV, getSuspiciousActivity,
};
