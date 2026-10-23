<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Services\Scoring;

final class ScoringController
{
    public const TYPES = [
        'unique_solve' => 'Unique solve',
        'writeup' => 'Write-up',
        'bug_report' => 'Bug report',
        'other' => 'Other award',
        'penalty' => 'Penalty',
    ];

    public function index(Request $r): void
    {
        view('admin/scoring', [
            'title' => 'Scoring & awards',
            'active' => 'scoring',
            'awards' => DB::all(
                'SELECT a.*, t.name AS team, u.username, c.title AS challenge, ad.username AS admin
                 FROM score_adjustments a JOIN teams t ON t.id = a.team_id
                 LEFT JOIN users u ON u.id = a.user_id LEFT JOIN challenges c ON c.id = a.challenge_id
                 LEFT JOIN users ad ON ad.id = a.admin_id
                 ORDER BY a.created_at DESC LIMIT 200'
            ),
            'teams' => DB::all('SELECT id, name FROM teams ORDER BY name'),
            'users' => DB::all('SELECT u.id, u.username, u.team_id, t.name AS team FROM users u LEFT JOIN teams t ON t.id = u.team_id WHERE u.team_id IS NOT NULL ORDER BY u.username'),
            'challenges' => DB::all('SELECT id, title FROM challenges ORDER BY title'),
            'bloods' => DB::all(
                'SELECT s.*, t.name AS team, c.title AS challenge FROM solves s JOIN teams t ON t.id = s.team_id JOIN challenges c ON c.id = s.challenge_id
                 WHERE s.bonus_points > 0 ORDER BY s.created_at DESC LIMIT 30'
            ),
            'prefill' => [
                'team_id' => (int) $r->query('team'),
                'user_id' => (int) $r->query('user'),
                'challenge_id' => (int) $r->query('challenge'),
                'type' => $r->query('type') ?: 'unique_solve',
            ],
        ], 'admin');
    }

    public function award(Request $r): void
    {
        $teamIds = array_filter(array_map('intval', (array) $r->input('team_ids', [])));
        $userId = (int) $r->input('user_id') ?: null;
        if ($userId) {
            $teamOfUser = (int) DB::val('SELECT team_id FROM users WHERE id = ?', [$userId]);
            if (!$teamOfUser) {
                flash('error', 'That player is not on a team.');
                redirect('/admin/scoring');
            }
            $teamIds = [$teamOfUser];
        }
        $type = array_key_exists((string) $r->input('type'), self::TYPES) ? (string) $r->input('type') : 'other';
        $points = abs((int) $r->input('points'));
        $delta = $type === 'penalty' ? -$points : $points;
        $reason = mb_substr((string) $r->input('reason'), 0, 255);

        if (!$teamIds || !$points || $reason === '') {
            flash('error', 'Pick at least one team or player, a point amount and a reason.');
            redirect('/admin/scoring');
        }
        $challengeId = (int) $r->input('challenge_id') ?: null;
        $public = $r->input('is_public') ? 1 : 0;
        foreach ($teamIds as $tid) {
            DB::insert('score_adjustments', [
                'team_id' => $tid,
                'user_id' => $userId,
                'challenge_id' => $challengeId,
                'type' => $type,
                'delta' => $delta,
                'reason' => $reason,
                'is_public' => $public,
                'admin_id' => current_user()['id'],
            ]);
        }
        audit('score.award', 'teams:' . implode(',', $teamIds), compact('type', 'delta', 'reason', 'challengeId', 'userId'));
        $n = count($teamIds);
        flash('success', sprintf('%s %+d points %s %d team%s.', self::TYPES[$type], $delta, $delta >= 0 ? 'to' : 'from', $n, $n > 1 ? 's' : ''));
        redirect('/admin/scoring');
    }

    public function revoke(Request $r, string $id): void
    {
        $a = DB::one('SELECT * FROM score_adjustments WHERE id = ?', [(int) $id]);
        if (!$a) {
            abort(404);
        }
        $restore = $a['revoked_at'] !== null;
        DB::query('UPDATE score_adjustments SET revoked_at = ' . ($restore ? 'NULL' : 'UTC_TIMESTAMP()') . ' WHERE id = ?', [$a['id']]);
        audit($restore ? 'score.award_restore' : 'score.award_revoke', 'adjustment:' . $a['id'], ['team' => $a['team_id'], 'delta' => $a['delta']]);
        flash('success', $restore ? 'Award restored.' : 'Award revoked. The team score no longer includes it.');
        redirect('/admin/scoring');
    }

    public function submissions(Request $r): void
    {
        $where = ['1=1'];
        $params = [];
        if ($t = (int) $r->query('team')) {
            $where[] = 's.team_id = ?';
            $params[] = $t;
        }
        if ($c = (int) $r->query('challenge')) {
            $where[] = 's.challenge_id = ?';
            $params[] = $c;
        }
        if (in_array($r->query('result'), ['0', '1'], true)) {
            $where[] = 's.correct = ?';
            $params[] = (int) $r->query('result');
        }
        if ($ip = $r->query('ip')) {
            $where[] = 's.ip = ?';
            $params[] = $ip;
        }
        if ($q = $r->query('q')) {
            $where[] = 's.provided LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $w = implode(' AND ', $where);
        $page = max(1, (int) $r->query('page', 1));
        $per = 50;
        $total = (int) DB::val("SELECT COUNT(*) FROM submissions s WHERE $w", $params);
        $rows = DB::all(
            "SELECT s.*, t.name AS team, u.username, c.title AS challenge
             FROM submissions s JOIN teams t ON t.id = s.team_id LEFT JOIN users u ON u.id = s.user_id JOIN challenges c ON c.id = s.challenge_id
             WHERE $w ORDER BY s.id DESC LIMIT $per OFFSET " . (($page - 1) * $per),
            $params
        );
        view('admin/submissions', [
            'title' => 'Submissions',
            'active' => 'submissions',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $per)),
            'teams' => DB::all('SELECT id, name FROM teams ORDER BY name'),
            'challenges' => DB::all('SELECT id, title FROM challenges ORDER BY title'),
            'filters' => $r->all(),
        ], 'admin');
    }

    public function manualSolve(Request $r): void
    {
        $teamId = (int) $r->input('team_id');
        $chId = (int) $r->input('challenge_id');
        $ch = DB::one('SELECT * FROM challenges WHERE id = ?', [$chId]);
        if (!$ch || !DB::val('SELECT id FROM teams WHERE id = ?', [$teamId])) {
            flash('error', 'Pick a team and a challenge.');
            back('/admin/submissions');
        }
        if (DB::val('SELECT id FROM solves WHERE team_id = ? AND challenge_id = ?', [$teamId, $chId])) {
            flash('error', 'That team already has this solve.');
            back('/admin/submissions');
        }
        $rank = (int) DB::val('SELECT COUNT(*) FROM solves WHERE challenge_id = ?', [$chId]) + 1;
        $value = Scoring::value($ch, $rank);
        DB::insert('solves', [
            'team_id' => $teamId,
            'challenge_id' => $chId,
            'points_awarded' => $value,
            'bonus_points' => $r->input('with_bonus') ? Scoring::bonus($ch, $rank, $value) : 0,
            'solve_rank' => $rank,
        ]);
        Scoring::recompute($chId);
        audit('score.manual_solve', 'team:' . $teamId, ['challenge' => $chId, 'reason' => (string) $r->input('reason')]);
        flash('success', 'Solve recorded.');
        back('/admin/submissions');
    }

    public function deleteSolve(Request $r, string $id): void
    {
        $s = DB::one('SELECT * FROM solves WHERE id = ?', [(int) $id]);
        if (!$s) {
            abort(404);
        }
        DB::tx(function () use ($s) {
            DB::query('DELETE FROM solves WHERE id = ?', [$s['id']]);
            // Later solvers move up one place; bonuses stay as originally awarded.
            DB::query('UPDATE solves SET solve_rank = solve_rank - 1 WHERE challenge_id = ? AND solve_rank > ?', [$s['challenge_id'], $s['solve_rank']]);
        });
        Scoring::recompute((int) $s['challenge_id']);
        audit('score.solve_delete', 'team:' . $s['team_id'], ['challenge' => $s['challenge_id'], 'points' => $s['points_awarded'], 'bonus' => $s['bonus_points']]);
        flash('success', 'Solve removed.');
        back('/admin/teams/' . $s['team_id']);
    }
}
