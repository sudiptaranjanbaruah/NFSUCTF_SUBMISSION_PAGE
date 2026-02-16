const { Parser } = require('json2csv');
const db = require('../utils/db');

/**
 * Export leaderboard as CSV
 */
async function exportLeaderboard() {
    const result = await db.query(
        `SELECT name as "Team Name", total_points as "Total Points", 
            total_solves as "Total Solves", last_solve_time as "Last Solve"
     FROM teams WHERE role = 'team' AND is_active = true
     ORDER BY total_points DESC, last_solve_time ASC NULLS LAST, total_solves DESC`
    );
    const parser = new Parser();
    return parser.parse(result.rows);
}

/**
 * Export solves as CSV
 */
async function exportSolves() {
    const result = await db.query(
        `SELECT t.name as "Team", c.title as "Challenge", c.category as "Category",
            s.points_awarded as "Points", s.solved_at as "Solved At"
     FROM solves s
     JOIN teams t ON s.team_id = t.id
     JOIN challenges c ON s.challenge_id = c.id
     ORDER BY s.solved_at DESC`
    );
    const parser = new Parser();
    return parser.parse(result.rows);
}

/**
 * Export submissions as CSV
 */
async function exportSubmissions() {
    const result = await db.query(
        `SELECT t.name as "Team", c.title as "Challenge", 
            sub.is_correct as "Correct", sub.ip_address as "IP",
            sub.submitted_at as "Submitted At"
     FROM submissions sub
     JOIN teams t ON sub.team_id = t.id
     JOIN challenges c ON sub.challenge_id = c.id
     ORDER BY sub.submitted_at DESC
     LIMIT 10000`
    );
    const parser = new Parser();
    return parser.parse(result.rows);
}

module.exports = { exportLeaderboard, exportSolves, exportSubmissions };
