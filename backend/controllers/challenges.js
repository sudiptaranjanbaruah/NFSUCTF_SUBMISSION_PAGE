const db = require('../utils/db');
const logger = require('../utils/logger');
const { submitFlagSchema } = require('../validators/challenge');
const scoringService = require('../services/scoring');

/**
 * GET /api/challenges - List active challenges with solve status
 */
async function listChallenges(req, res, next) {
    try {
        const teamId = req.team.teamId;

        const result = await db.query(`
      SELECT c.id, c.title, c.description, c.category, c.points, c.is_active, c.created_at,
             (SELECT COUNT(*) FROM solves s WHERE s.challenge_id = c.id) as solve_count,
             EXISTS(SELECT 1 FROM solves s WHERE s.challenge_id = c.id AND s.team_id = $1) as is_solved
      FROM challenges c
      WHERE c.is_active = true
      ORDER BY c.category, c.points ASC
    `, [teamId]);

        // Get files for each challenge
        const challengeIds = result.rows.map(c => c.id);
        let filesMap = {};
        if (challengeIds.length > 0) {
            const filesResult = await db.query(
                `SELECT challenge_id, id, file_name, file_url, file_size FROM challenge_files WHERE challenge_id = ANY($1)`,
                [challengeIds]
            );
            filesResult.rows.forEach(f => {
                if (!filesMap[f.challenge_id]) filesMap[f.challenge_id] = [];
                filesMap[f.challenge_id].push(f);
            });
        }

        const challenges = result.rows.map(c => ({
            ...c,
            solve_count: parseInt(c.solve_count),
            is_solved: c.is_solved,
            files: filesMap[c.id] || [],
        }));

        res.json({ challenges });
    } catch (err) {
        next(err);
    }
}

/**
 * GET /api/challenges/:id - Challenge detail
 */
async function getChallenge(req, res, next) {
    try {
        const { id } = req.params;
        const teamId = req.team.teamId;

        const result = await db.query(`
      SELECT c.id, c.title, c.description, c.category, c.points, c.is_active, c.max_attempts, c.created_at,
             (SELECT COUNT(*) FROM solves s WHERE s.challenge_id = c.id) as solve_count,
             EXISTS(SELECT 1 FROM solves s WHERE s.challenge_id = c.id AND s.team_id = $2) as is_solved
      FROM challenges c
      WHERE c.id = $1
    `, [id, teamId]);

        const challenge = result.rows[0];
        if (!challenge) {
            return res.status(404).json({ message: 'Challenge not found' });
        }

        // Get files
        const filesResult = await db.query(
            'SELECT id, file_name, file_url, file_size FROM challenge_files WHERE challenge_id = $1',
            [id]
        );

        // Get wrong attempt count for this team
        let wrongAttempts = 0;
        if (challenge.max_attempts) {
            const attResult = await db.query(
                'SELECT COUNT(*) as count FROM submissions WHERE team_id = $1 AND challenge_id = $2 AND is_correct = false',
                [teamId, id]
            );
            wrongAttempts = parseInt(attResult.rows[0].count);
        }

        res.json({
            challenge: {
                ...challenge,
                solve_count: parseInt(challenge.solve_count),
                files: filesResult.rows,
                wrong_attempts: wrongAttempts,
                attempts_remaining: challenge.max_attempts ? Math.max(0, challenge.max_attempts - wrongAttempts) : null,
            }
        });
    } catch (err) {
        next(err);
    }
}

/**
 * GET /api/challenges/:id/stats - Solve visibility
 */
async function getChallengeStats(req, res, next) {
    try {
        const { id } = req.params;

        const result = await db.query(`
      SELECT s.solved_at, t.name as team_name
      FROM solves s
      JOIN teams t ON s.team_id = t.id
      WHERE s.challenge_id = $1
      ORDER BY s.solved_at ASC
    `, [id]);

        const solvedTeams = result.rows.map(r => ({
            name: r.team_name,
            solved_at: r.solved_at,
        }));

        res.json({
            solveCount: solvedTeams.length,
            solvedTeams,
            firstBlood: solvedTeams.length > 0 ? solvedTeams[0].name : null,
        });
    } catch (err) {
        next(err);
    }
}

/**
 * POST /api/submit - Flag submission
 */
async function submitFlag(req, res, next) {
    try {
        const { challengeId, flag } = submitFlagSchema.parse(req.body);
        const teamId = req.team.teamId;
        const ipAddress = req.ip || req.connection?.remoteAddress;
        const userAgent = req.headers['user-agent'] || '';

        const result = await scoringService.submitFlag(teamId, challengeId, flag, ipAddress, userAgent);

        if (result.correct) {
            res.json({ message: result.message, correct: true, points: result.points || 0 });
        } else {
            res.json({ message: result.message, correct: false });
        }
    } catch (err) {
        next(err);
    }
}

module.exports = { listChallenges, getChallenge, getChallengeStats, submitFlag };
