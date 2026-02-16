const db = require('../utils/db');

/**
 * GET /api/team/profile
 */
async function getProfile(req, res, next) {
    try {
        const teamId = req.team.teamId;

        // Team info
        const teamResult = await db.query(
            `SELECT id, name, username, total_points, total_solves, last_solve_time, created_at
       FROM teams WHERE id = $1`,
            [teamId]
        );
        const team = teamResult.rows[0];
        if (!team) {
            return res.status(404).json({ message: 'Team not found' });
        }

        // Solve history
        const solvesResult = await db.query(`
      SELECT c.title as challenge_name, c.category, s.points_awarded, s.solved_at
      FROM solves s
      JOIN challenges c ON s.challenge_id = c.id
      WHERE s.team_id = $1
      ORDER BY s.solved_at DESC
    `, [teamId]);

        // Rank
        const rankResult = await db.query(`
      SELECT COUNT(*) + 1 as rank
      FROM teams
      WHERE role = 'team' AND is_active = true
        AND (total_points > $1 
          OR (total_points = $1 AND last_solve_time < $2)
          OR (total_points = $1 AND last_solve_time = $2 AND total_solves > $3))
    `, [team.total_points, team.last_solve_time, team.total_solves]);

        res.json({
            team: {
                ...team,
                rank: parseInt(rankResult.rows[0].rank),
            },
            solves: solvesResult.rows,
        });
    } catch (err) {
        next(err);
    }
}

module.exports = { getProfile };
