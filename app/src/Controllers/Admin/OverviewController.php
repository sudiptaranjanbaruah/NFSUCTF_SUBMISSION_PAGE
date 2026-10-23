<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Services\Scoring;

final class OverviewController
{
    public function index(Request $r): void
    {
        $kpi = [
            'users' => (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'user'"),
            'verified' => (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'user' AND email_verified_at IS NOT NULL"),
            'teams' => (int) DB::val('SELECT COUNT(*) FROM teams WHERE is_banned = 0'),
            'teamsScoring' => (int) DB::val('SELECT COUNT(DISTINCT team_id) FROM solves'),
            'active15' => (int) DB::val('SELECT COUNT(*) FROM users WHERE last_seen_at > UTC_TIMESTAMP() - INTERVAL 15 MINUTE'),
            'submissions' => (int) DB::val('SELECT COUNT(*) FROM submissions'),
            'correct' => (int) DB::val('SELECT COUNT(*) FROM submissions WHERE correct = 1'),
            'solves' => (int) DB::val('SELECT COUNT(*) FROM solves'),
            'challenges' => (int) DB::val("SELECT COUNT(*) FROM challenges WHERE state = 'visible'"),
            'unsolved' => (int) DB::val("SELECT COUNT(*) FROM challenges c WHERE c.state = 'visible' AND NOT EXISTS (SELECT 1 FROM solves s WHERE s.challenge_id = c.id)"),
            'subsHour' => (int) DB::val('SELECT COUNT(*) FROM submissions WHERE created_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR'),
            'mailQueued' => (int) DB::val('SELECT COUNT(*) FROM mail_queue WHERE sent_at IS NULL AND attempts < 3'),
        ];

        // Signups per day (last 30 days)
        $signups = DB::all(
            "SELECT DATE(created_at) d, COUNT(*) n FROM users WHERE role = 'user' AND created_at > UTC_TIMESTAMP() - INTERVAL 30 DAY GROUP BY d ORDER BY d"
        );
        // Submissions per hour for the last 48h, split right/wrong
        $hourly = DB::all(
            "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00') h, SUM(correct) ok, SUM(correct = 0) bad
             FROM submissions WHERE created_at > UTC_TIMESTAMP() - INTERVAL 48 HOUR GROUP BY h ORDER BY h"
        );
        // Per-challenge solves vs wrong attempts
        $perChallenge = DB::all(
            "SELECT c.id, c.title, cat.name AS category,
                    (SELECT COUNT(*) FROM solves s WHERE s.challenge_id = c.id) AS solves,
                    (SELECT COUNT(*) FROM submissions s WHERE s.challenge_id = c.id AND s.correct = 0) AS wrong,
                    (SELECT COUNT(DISTINCT team_id) FROM submissions s WHERE s.challenge_id = c.id) AS tried
             FROM challenges c JOIN categories cat ON cat.id = c.category_id WHERE c.state = 'visible'
             ORDER BY cat.sort_order, c.points"
        );
        // Category completion: share of (team, challenge) pairs solved
        $teamCount = max(1, $kpi['teamsScoring']);
        $categories = DB::all(
            "SELECT cat.name, COUNT(DISTINCT c.id) AS total,
                    (SELECT COUNT(*) FROM solves s JOIN challenges c2 ON c2.id = s.challenge_id WHERE c2.category_id = cat.id) AS solves
             FROM categories cat JOIN challenges c ON c.category_id = cat.id AND c.state = 'visible'
             GROUP BY cat.id, cat.name, cat.sort_order ORDER BY cat.sort_order"
        );
        foreach ($categories as &$c) {
            $c['pct'] = $c['total'] ? round($c['solves'] / ($c['total'] * $teamCount) * 100, 1) : 0;
        }
        unset($c);
        // Heatmap: submissions by weekday x hour (UTC) over the event
        $heat = DB::all('SELECT WEEKDAY(created_at) wd, HOUR(created_at) hr, COUNT(*) n FROM submissions GROUP BY wd, hr');
        $top = array_slice(Scoring::scoreboard(null, false), 0, 8);
        $methods = DB::all("SELECT method, COUNT(*) n FROM login_events WHERE success = 1 AND created_at > UTC_TIMESTAMP() - INTERVAL 30 DAY GROUP BY method ORDER BY n DESC");
        $hintUse = DB::all('SELECT c.title, COUNT(hu.id) n, SUM(h.cost) cost FROM hint_unlocks hu JOIN hints h ON h.id = hu.hint_id JOIN challenges c ON c.id = h.challenge_id GROUP BY c.id, c.title ORDER BY n DESC LIMIT 8');
        $bloods = DB::all(
            'SELECT s.created_at, t.name AS team, c.title FROM solves s JOIN teams t ON t.id = s.team_id JOIN challenges c ON c.id = s.challenge_id
             WHERE s.solve_rank = 1 ORDER BY s.created_at DESC LIMIT 8'
        );

        view('admin/overview', [
            'title' => 'Overview',
            'active' => 'overview',
            'kpi' => $kpi,
            'signups' => $signups,
            'hourly' => $hourly,
            'perChallenge' => $perChallenge,
            'categories' => $categories,
            'heat' => $heat,
            'top' => $top,
            'series' => Scoring::timelines(array_map(fn($t) => (int) $t['id'], $top)),
            'methods' => $methods,
            'hintUse' => $hintUse,
            'bloods' => $bloods,
            'state' => event_state(),
        ], 'admin');
    }

    /** Small JSON endpoint for refreshing KPI tiles. */
    public function analytics(Request $r): void
    {
        json_out([
            'ok' => true,
            'active15' => (int) DB::val('SELECT COUNT(*) FROM users WHERE last_seen_at > UTC_TIMESTAMP() - INTERVAL 15 MINUTE'),
            'submissions' => (int) DB::val('SELECT COUNT(*) FROM submissions'),
            'solves' => (int) DB::val('SELECT COUNT(*) FROM solves'),
        ]);
    }

    public function security(Request $r): void
    {
        view('admin/security', [
            'title' => 'Anti-cheat',
            'active' => 'security',
            // Same wrong flag submitted by several teams: often a leaked or shared guess.
            'sharedWrong' => DB::all(
                'SELECT s.challenge_id, c.title, s.provided, COUNT(DISTINCT s.team_id) AS teams, GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ", ") AS team_names, MAX(s.created_at) AS last
                 FROM submissions s JOIN challenges c ON c.id = s.challenge_id JOIN teams t ON t.id = s.team_id
                 WHERE s.correct = 0 AND CHAR_LENGTH(s.provided) > 6
                 GROUP BY s.challenge_id, c.title, s.provided HAVING teams > 1 ORDER BY teams DESC, last DESC LIMIT 30'
            ),
            // IPs used by more than one team.
            'sharedIps' => DB::all(
                'SELECT s.ip, COUNT(DISTINCT s.team_id) AS teams, GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ", ") AS team_names, COUNT(*) AS n, MAX(s.created_at) AS last
                 FROM submissions s JOIN teams t ON t.id = s.team_id WHERE s.ip IS NOT NULL
                 GROUP BY s.ip HAVING teams > 1 ORDER BY teams DESC, n DESC LIMIT 30'
            ),
            // Two teams solving the same challenge within 60s of each other, repeatedly.
            'pairs' => DB::all(
                'SELECT a.team_id AS a_id, b.team_id AS b_id, ta.name AS a_name, tb.name AS b_name, COUNT(*) AS n
                 FROM solves a JOIN solves b ON a.challenge_id = b.challenge_id AND a.team_id < b.team_id
                   AND ABS(TIMESTAMPDIFF(SECOND, a.created_at, b.created_at)) <= 60
                 JOIN teams ta ON ta.id = a.team_id JOIN teams tb ON tb.id = b.team_id
                 GROUP BY a.team_id, b.team_id, ta.name, tb.name HAVING n >= 2 ORDER BY n DESC LIMIT 20'
            ),
            // Many wrong submissions in a short time (possible brute forcing).
            'bursts' => DB::all(
                "SELECT s.team_id, t.name, c.title, COUNT(*) AS n, MIN(s.created_at) AS first, MAX(s.created_at) AS last
                 FROM submissions s JOIN teams t ON t.id = s.team_id JOIN challenges c ON c.id = s.challenge_id
                 WHERE s.correct = 0
                 GROUP BY s.team_id, t.name, c.title, DATE_FORMAT(s.created_at, '%Y-%m-%d %H:%i')
                 HAVING n >= 8 ORDER BY last DESC LIMIT 20"
            ),
            // Failed sign-ins by IP in the last 24h.
            'failedLogins' => DB::all(
                'SELECT ip, COUNT(*) AS n, COUNT(DISTINCT email) AS emails, MAX(created_at) AS last FROM login_events
                 WHERE success = 0 AND created_at > UTC_TIMESTAMP() - INTERVAL 1 DAY GROUP BY ip HAVING n >= 5 ORDER BY n DESC LIMIT 20'
            ),
        ], 'admin');
    }

    public function audit(Request $r): void
    {
        $where = ['1=1'];
        $params = [];
        if ($a = $r->query('action')) {
            $where[] = 'l.action LIKE ?';
            $params[] = $a . '%';
        }
        if ($u = (int) $r->query('user')) {
            $where[] = 'l.user_id = ?';
            $params[] = $u;
        }
        $w = implode(' AND ', $where);
        $page = max(1, (int) $r->query('page', 1));
        $total = (int) DB::val("SELECT COUNT(*) FROM audit_logs l WHERE $w", $params);
        view('admin/audit', [
            'title' => 'Audit log',
            'active' => 'audit',
            'rows' => DB::all("SELECT l.*, u.username FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id WHERE $w ORDER BY l.id DESC LIMIT 100 OFFSET " . (($page - 1) * 100), $params),
            'actions' => array_column(DB::all("SELECT DISTINCT SUBSTRING_INDEX(action, '.', 1) a FROM audit_logs ORDER BY a"), 'a'),
            'page' => $page,
            'pages' => max(1, (int) ceil($total / 100)),
            'total' => $total,
            'filterAction' => $a,
        ], 'admin');
    }

    public function export(Request $r, string $type): void
    {
        [$rows, $cols] = match ($type) {
            'scoreboard' => [Scoring::scoreboard(null, true), ['rank', 'name', 'affiliation', 'country', 'score', 'solves', 'bloods', 'members', 'last_at']],
            'solves' => [DB::all('SELECT s.created_at, t.name AS team, u.username, c.title AS challenge, s.points_awarded, s.bonus_points, s.solve_rank FROM solves s JOIN teams t ON t.id = s.team_id LEFT JOIN users u ON u.id = s.user_id JOIN challenges c ON c.id = s.challenge_id ORDER BY s.created_at'), ['created_at', 'team', 'username', 'challenge', 'points_awarded', 'bonus_points', 'solve_rank']],
            'submissions' => [DB::all('SELECT s.created_at, t.name AS team, u.username, c.title AS challenge, s.correct, s.provided, s.ip FROM submissions s JOIN teams t ON t.id = s.team_id LEFT JOIN users u ON u.id = s.user_id JOIN challenges c ON c.id = s.challenge_id ORDER BY s.id'), ['created_at', 'team', 'username', 'challenge', 'correct', 'provided', 'ip']],
            'users' => [DB::all('SELECT u.id, u.username, u.email, u.full_name, u.affiliation, u.role, t.name AS team, u.email_verified_at, u.created_at FROM users u LEFT JOIN teams t ON t.id = u.team_id ORDER BY u.id'), ['id', 'username', 'email', 'full_name', 'affiliation', 'role', 'team', 'email_verified_at', 'created_at']],
            default => abort(404),
        };
        audit('export.' . $type);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $type . '-' . gmdate('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $cols, ',', '"', '');
        foreach ($rows as $row) {
            // Neutralise spreadsheet formulas in user-controlled text.
            fputcsv($out, array_map(fn($c) => is_string($row[$c] ?? null) && preg_match('/^[=+\-@]/', $row[$c]) ? "'" . $row[$c] : ($row[$c] ?? ''), $cols), ',', '"', '');
        }
        fclose($out);
    }
}
