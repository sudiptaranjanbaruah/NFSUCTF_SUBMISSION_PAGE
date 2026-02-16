const bcrypt = require('bcryptjs');
const db = require('../utils/db');
const logger = require('../utils/logger');
const { broadcastLeaderboard, broadcastSolve } = require('../utils/websocket');

/**
 * Transaction-safe flag submission and scoring
 * Prevents race conditions via UNIQUE(team_id, challenge_id) constraint
 */
async function submitFlag(teamId, challengeId, flag, ipAddress, userAgent) {
    return db.withTransaction(async (client) => {
        // 1. Get challenge (exclude flag_hash from external access, but needed here)
        const challengeResult = await client.query(
            'SELECT id, title, points, flag_hash, is_active, max_attempts FROM challenges WHERE id = $1',
            [challengeId]
        );
        const challenge = challengeResult.rows[0];
        if (!challenge) {
            throw Object.assign(new Error('Challenge not found'), { statusCode: 404 });
        }
        if (!challenge.is_active) {
            throw Object.assign(new Error('Challenge is not active'), { statusCode: 403 });
        }

        // 2. Check if already solved
        const solveCheck = await client.query(
            'SELECT id FROM solves WHERE team_id = $1 AND challenge_id = $2',
            [teamId, challengeId]
        );
        if (solveCheck.rows.length > 0) {
            // Log the submission anyway
            await client.query(
                `INSERT INTO submissions (team_id, challenge_id, submitted_flag, is_correct, ip_address, user_agent)
         VALUES ($1, $2, $3, true, $4, $5)`,
                [teamId, challengeId, '[REDACTED-ALREADY-SOLVED]', ipAddress, userAgent]
            );
            return { correct: true, alreadySolved: true, message: 'Challenge already solved' };
        }

        // 3. Check max attempts if set
        if (challenge.max_attempts) {
            const attemptCount = await client.query(
                'SELECT COUNT(*) as count FROM submissions WHERE team_id = $1 AND challenge_id = $2 AND is_correct = false',
                [teamId, challengeId]
            );
            if (parseInt(attemptCount.rows[0].count) >= challenge.max_attempts) {
                throw Object.assign(new Error('Maximum attempts reached for this challenge'), { statusCode: 429 });
            }
        }

        // 4. Compare flag with bcrypt hash
        const isCorrect = await bcrypt.compare(flag, challenge.flag_hash);

        // 5. Log submission
        await client.query(
            `INSERT INTO submissions (team_id, challenge_id, submitted_flag, is_correct, ip_address, user_agent)
       VALUES ($1, $2, $3, $4, $5, $6)`,
            [teamId, challengeId, isCorrect ? '[REDACTED]' : flag, isCorrect, ipAddress, userAgent]
        );

        if (!isCorrect) {
            return { correct: false, message: 'Incorrect flag' };
        }

        // 6. Insert solve record (UNIQUE constraint prevents duplicates from race conditions)
        try {
            await client.query(
                `INSERT INTO solves (team_id, challenge_id, points_awarded) VALUES ($1, $2, $3)`,
                [teamId, challengeId, challenge.points]
            );
        } catch (err) {
            if (err.code === '23505') {
                // Unique constraint violation = race condition caught
                logger.warn({ teamId, challengeId }, 'Race condition caught: duplicate solve prevented');
                return { correct: true, alreadySolved: true, message: 'Challenge already solved' };
            }
            throw err;
        }

        // 7. Update team total_points, last_solve_time, total_solves
        const now = new Date();
        await client.query(
            `UPDATE teams SET 
        total_points = total_points + $1, 
        last_solve_time = $2,
        total_solves = total_solves + 1
       WHERE id = $3`,
            [challenge.points, now, teamId]
        );

        logger.info({ teamId, challengeId, points: challenge.points }, 'Flag solved successfully');

        // 8. Broadcast events (async, non-blocking)
        setImmediate(async () => {
            try {
                // Get team name for broadcast
                const teamResult = await db.query('SELECT name FROM teams WHERE id = $1', [teamId]);
                const teamName = teamResult.rows[0]?.name || 'Unknown';

                broadcastSolve({
                    teamName,
                    challengeTitle: challenge.title,
                    points: challenge.points,
                    solvedAt: now.toISOString(),
                });

                // Fetch and broadcast updated leaderboard
                const leaderboardResult = await db.query(
                    `SELECT name, total_points, total_solves, last_solve_time
           FROM teams WHERE role = 'team' AND is_active = true
           ORDER BY total_points DESC, last_solve_time ASC NULLS LAST, total_solves DESC`
                );
                broadcastLeaderboard(leaderboardResult.rows);
            } catch (err) {
                logger.error({ err }, 'Error broadcasting solve');
            }
        });

        return {
            correct: true,
            alreadySolved: false,
            message: 'Flag correct!',
            points: challenge.points,
        };
    });
}

/**
 * Recalculate all team scores from solves table
 */
async function recalculateScores() {
    return db.withTransaction(async (client) => {
        // Reset all team scores
        await client.query(`UPDATE teams SET total_points = 0, total_solves = 0, last_solve_time = NULL WHERE role = 'team'`);

        // Recalculate from solves
        await client.query(`
      UPDATE teams SET 
        total_points = COALESCE(sub.total, 0),
        total_solves = COALESCE(sub.solve_count, 0),
        last_solve_time = sub.last_solve
      FROM (
        SELECT team_id, 
               SUM(points_awarded) as total, 
               COUNT(*) as solve_count,
               MAX(solved_at) as last_solve
        FROM solves 
        GROUP BY team_id
      ) sub
      WHERE teams.id = sub.team_id
    `);

        logger.info('All team scores recalculated from solves table');
    });
}

module.exports = { submitFlag, recalculateScores };
