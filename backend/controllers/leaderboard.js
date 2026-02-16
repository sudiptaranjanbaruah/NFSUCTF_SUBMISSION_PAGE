const db = require('../utils/db');

/**
 * GET /api/leaderboard
 */
async function getLeaderboard(req, res, next) {
    try {
        // Check if scoreboard is frozen
        const eventResult = await db.query('SELECT is_frozen, frozen_leaderboard FROM events ORDER BY id DESC LIMIT 1');
        const event = eventResult.rows[0];

        if (event?.is_frozen && event.frozen_leaderboard) {
            return res.json({
                leaderboard: event.frozen_leaderboard,
                frozen: true,
                message: 'Scoreboard is frozen. Rankings may not reflect latest solves.',
            });
        }

        const result = await db.query(`
      SELECT t.id, t.name, t.total_points, t.total_solves, t.last_solve_time
      FROM teams t
      WHERE t.role = 'team' AND t.is_active = true
      ORDER BY t.total_points DESC, t.last_solve_time ASC NULLS LAST, t.total_solves DESC
    `);

        const leaderboard = result.rows.map((team, index) => ({
            rank: index + 1,
            id: team.id,
            name: team.name,
            totalPoints: team.total_points,
            totalSolves: team.total_solves,
            lastSolveTime: team.last_solve_time,
        }));

        res.json({ leaderboard, frozen: false });
    } catch (err) {
        next(err);
    }
}

module.exports = { getLeaderboard };
