<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Services\Scoring;

final class ScoreboardController
{
    private function guard(): void
    {
        $vis = setting('scoreboard_visibility');
        if (is_admin()) {
            return;
        }
        if ($vis === 'hidden') {
            abort(403, 'The organisers have hidden the scoreboard for now.');
        }
        if ($vis === 'private' && !current_user()) {
            abort(403, 'Sign in to see the scoreboard.');
        }
    }

    private function data(Request $r): array
    {
        $cutoff = is_admin() && $r->query('live') === '1' ? null : freeze_cutoff();
        $aff = $r->query('affiliation') ?: null;
        $rows = Scoring::scoreboard($cutoff, false, $aff);
        $distribution = Scoring::solveDistribution($cutoff);
        $feed = DB::all(
            'SELECT s.created_at, s.solve_rank, s.bonus_points, t.id AS team_id, t.name AS team, c.title AS challenge
             FROM solves s JOIN teams t ON t.id = s.team_id JOIN challenges c ON c.id = s.challenge_id
             WHERE t.is_hidden = 0 AND s.created_at <= ? AND s.solve_rank = 1
             ORDER BY s.created_at DESC LIMIT 8',
            [$cutoff ? gmdate('Y-m-d H:i:s', $cutoff) : '9999-12-31 00:00:00']
        );
        return compact('rows', 'distribution', 'cutoff', 'feed', 'aff');
    }

    public function index(Request $r): void
    {
        $this->guard();
        $d = $this->data($r);
        $affiliations = DB::all("SELECT DISTINCT affiliation FROM teams WHERE affiliation IS NOT NULL AND affiliation <> '' AND is_hidden = 0 ORDER BY affiliation");
        view('player/scoreboard', $d + [
            'title' => 'Scoreboard',
            'active' => 'scoreboard',
            'affiliations' => array_column($affiliations, 'affiliation'),
            'myTeam' => current_team()['id'] ?? null,
        ]);
    }

    public function json(Request $r): void
    {
        $this->guard();
        $d = $this->data($r);
        json_out([
            'ok' => true,
            'frozen' => (bool) $d['cutoff'],
            'rows' => array_map(fn($t) => [
                'id' => (int) $t['id'],
                'rank' => $t['rank'],
                'name' => $t['name'],
                'affiliation' => $t['affiliation'],
                'score' => $t['score'],
                'solves' => $t['solves'],
                'bloods' => $t['bloods'],
                'last' => $t['solves'] ? gmdate('c', strtotime($t['last_at'] . ' UTC')) : null,
            ], array_slice($d['rows'], 0, 500)),
            'distribution' => $d['distribution'],
        ]);
    }
}
