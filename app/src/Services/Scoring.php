<?php
namespace App\Services;

use App\Core\DB;

/**
 * Scores are always derived from the ledger:
 *   solves (points_awarded + bonus_points) + score_adjustments − hint costs.
 * Nothing caches a total, so recalculation can never drift.
 */
final class Scoring
{
    /** Value of a challenge for its $n-th solver (1-based); $n = 0 means "next solve right now". */
    public static function value(array $ch, int $n): int
    {
        if ($ch['scoring'] !== 'dynamic') {
            return (int) $ch['points'];
        }
        $initial = (int) $ch['points'];
        $min = (int) $ch['min_points'];
        $decay = max(1, (int) $ch['decay']);
        $k = max(0, $n - 1);
        // CTFd-style parabolic decay: reaches $min after $decay solves.
        $v = (int) ceil((($min - $initial) / ($decay ** 2)) * ($k ** 2) + $initial);
        return max($min, $v);
    }

    public static function currentValue(array $ch, int $solveCount): int
    {
        return self::value($ch, $solveCount + 1);
    }

    /** First-blood bonus for the solver with $rank (1 = first) given the challenge value at solve time. */
    public static function bonus(array $ch, int $rank, int $value): int
    {
        $mode = setting('first_blood_mode');
        $spec = $ch['first_blood_bonus'] ?? null;
        if ($spec === 'off' || $mode === 'off') {
            return 0;
        }
        $spec = $spec ?: setting('first_blood_values');
        $parts = array_values(array_filter(array_map('trim', explode(',', (string) $spec)), 'is_numeric'));
        $n = (int) ($parts[$rank - 1] ?? 0);
        if ($n <= 0) {
            return 0;
        }
        return $mode === 'percent' ? (int) round($value * $n / 100) : $n;
    }

    public static function flagMatches(array $flag, string $input): bool
    {
        $content = $flag['content'];
        if ($flag['type'] === 'regex') {
            $pattern = '~^(?:' . str_replace('~', '\~', $content) . ')$~' . ($flag['case_insensitive'] ? 'i' : '') . 'u';
            return @preg_match($pattern, $input) === 1;
        }
        return $flag['case_insensitive']
            ? hash_equals(mb_strtolower($content), mb_strtolower($input))
            : hash_equals($content, $input);
    }

    /**
     * Submit a flag. Locks the challenge row so concurrent correct submissions
     * get distinct solve ranks and only one solve per team is ever recorded.
     */
    public static function submit(array $user, array $team, int $challengeId, string $input, string $ip, string $ua): array
    {
        return DB::tx(function () use ($user, $team, $challengeId, $input, $ip, $ua) {
            $ch = DB::one('SELECT * FROM challenges WHERE id = ? FOR UPDATE', [$challengeId]);
            if (!$ch) {
                abort(404, 'Challenge not found.');
            }

            if (DB::val('SELECT id FROM solves WHERE team_id = ? AND challenge_id = ?', [$team['id'], $challengeId])) {
                return ['correct' => true, 'already' => true, 'message' => 'Your team already solved this one.'];
            }

            if ($ch['max_attempts']) {
                $wrong = (int) DB::val('SELECT COUNT(*) FROM submissions WHERE team_id = ? AND challenge_id = ? AND correct = 0', [$team['id'], $challengeId]);
                if ($wrong >= (int) $ch['max_attempts']) {
                    return ['correct' => false, 'locked' => true, 'message' => 'Your team has used all attempts for this challenge.'];
                }
            }

            $correct = false;
            foreach (DB::all('SELECT * FROM flags WHERE challenge_id = ?', [$challengeId]) as $flag) {
                if (self::flagMatches($flag, $input)) {
                    $correct = true;
                    break;
                }
            }

            DB::insert('submissions', [
                'team_id' => $team['id'],
                'user_id' => $user['id'],
                'challenge_id' => $challengeId,
                'provided' => mb_substr($correct ? '[correct]' : $input, 0, 512),
                'correct' => $correct ? 1 : 0,
                'ip' => $ip,
                'ua' => $ua,
            ]);

            if (!$correct) {
                $left = null;
                if ($ch['max_attempts']) {
                    $wrong = (int) DB::val('SELECT COUNT(*) FROM submissions WHERE team_id = ? AND challenge_id = ? AND correct = 0', [$team['id'], $challengeId]);
                    $left = max(0, (int) $ch['max_attempts'] - $wrong);
                }
                return ['correct' => false, 'message' => 'Incorrect flag.', 'attempts_left' => $left];
            }

            $rank = (int) DB::val('SELECT COUNT(*) FROM solves WHERE challenge_id = ?', [$challengeId]) + 1;
            $value = self::value($ch, $rank);
            $bonus = self::bonus($ch, $rank, $value);

            DB::insert('solves', [
                'team_id' => $team['id'],
                'user_id' => $user['id'],
                'challenge_id' => $challengeId,
                'points_awarded' => $value,
                'bonus_points' => $bonus,
                'solve_rank' => $rank,
            ]);
            if ($ch['scoring'] === 'dynamic') {
                // Every solver holds the current value; first-blood bonuses are fixed.
                DB::query('UPDATE solves SET points_awarded = ? WHERE challenge_id = ?', [$value, $challengeId]);
            }

            return [
                'correct' => true,
                'already' => false,
                'points' => $value,
                'bonus' => $bonus,
                'rank' => $rank,
                'message' => $rank === 1 ? 'First blood! Flag accepted.' : 'Flag accepted.',
            ];
        });
    }

    /** Re-apply the dynamic value to a challenge's solves (after edits or a removed solve). */
    public static function recompute(int $challengeId): void
    {
        $ch = DB::one('SELECT * FROM challenges WHERE id = ?', [$challengeId]);
        if (!$ch) {
            return;
        }
        $count = (int) DB::val('SELECT COUNT(*) FROM solves WHERE challenge_id = ?', [$challengeId]);
        DB::query('UPDATE solves SET points_awarded = ? WHERE challenge_id = ?', [self::value($ch, max(1, $count)), $challengeId]);
    }

    private static function cutoffSql(?int $cutoff): string
    {
        return $cutoff ? gmdate('Y-m-d H:i:s', $cutoff) : '9999-12-31 00:00:00';
    }

    /**
     * Ranked scoreboard. $cutoff (unix) hides activity after a freeze.
     * Ties: higher score first, then whoever reached it earlier.
     */
    public static function scoreboard(?int $cutoff = null, bool $includeHidden = false, ?string $affiliation = null): array
    {
        $c = self::cutoffSql($cutoff);
        $where = 't.is_banned = 0' . ($includeHidden ? '' : ' AND t.is_hidden = 0');
        $params = [$c, $c, $c];
        if ($affiliation) {
            $where .= ' AND t.affiliation = ?';
            $params[] = $affiliation;
        }
        $rows = DB::all(
            "SELECT t.id, t.name, t.affiliation, t.country, t.is_solo,
                    COALESCE(s.pts, 0) + COALESCE(a.pts, 0) - COALESCE(h.pts, 0) AS score,
                    COALESCE(s.cnt, 0) AS solves,
                    COALESCE(s.bloods, 0) AS bloods,
                    GREATEST(COALESCE(s.last_at, '1970-01-01'), COALESCE(a.last_at, '1970-01-01')) AS last_at,
                    (SELECT COUNT(*) FROM users u WHERE u.team_id = t.id) AS members
             FROM teams t
             LEFT JOIN (SELECT team_id, SUM(points_awarded + bonus_points) pts, COUNT(*) cnt,
                               SUM(solve_rank = 1) bloods, MAX(created_at) last_at
                        FROM solves WHERE created_at <= ? GROUP BY team_id) s ON s.team_id = t.id
             LEFT JOIN (SELECT team_id, SUM(delta) pts, MAX(created_at) last_at
                        FROM score_adjustments WHERE revoked_at IS NULL AND created_at <= ? GROUP BY team_id) a ON a.team_id = t.id
             LEFT JOIN (SELECT hu.team_id, SUM(hi.cost) pts
                        FROM hint_unlocks hu JOIN hints hi ON hi.id = hu.hint_id
                        WHERE hu.created_at <= ? GROUP BY hu.team_id) h ON h.team_id = t.id
             WHERE $where
             ORDER BY score DESC, last_at ASC, t.id ASC",
            $params
        );
        $rank = 0;
        foreach ($rows as &$r) {
            $r['rank'] = ++$rank;
            $r['score'] = (int) $r['score'];
            $r['solves'] = (int) $r['solves'];
            $r['bloods'] = (int) $r['bloods'];
            $r['members'] = (int) $r['members'];
            if (str_starts_with((string) $r['last_at'], '1970')) {
                $r['last_at'] = null;
            }
        }
        return $rows;
    }

    public static function teamScore(int $teamId): int
    {
        return (int) DB::val(
            'SELECT
               COALESCE((SELECT SUM(points_awarded + bonus_points) FROM solves WHERE team_id = ?), 0)
             + COALESCE((SELECT SUM(delta) FROM score_adjustments WHERE team_id = ? AND revoked_at IS NULL), 0)
             - COALESCE((SELECT SUM(hi.cost) FROM hint_unlocks hu JOIN hints hi ON hi.id = hu.hint_id WHERE hu.team_id = ?), 0)',
            [$teamId, $teamId, $teamId]
        );
    }

    public static function teamRank(int $teamId, ?int $cutoff = null): ?int
    {
        foreach (self::scoreboard($cutoff, true) as $r) {
            if ((int) $r['id'] === $teamId) {
                return $r['rank'];
            }
        }
        return null;
    }

    /**
     * Solve distribution: for each threshold of 10%, 20% … 100% of tasks, the
     * share of participating teams (teams with at least one solve) that solved
     * at least that many. One series for all challenges plus one per category
     * with 2+ released challenges.
     *
     * @return array{labels: int[], series: array<int, array{name: string, values: float[], teams: int, tasks: int}>}
     */
    public static function solveDistribution(?int $cutoff = null): array
    {
        $c = self::cutoffSql($cutoff);
        $tasks = DB::all(
            'SELECT c.id, cat.name AS category FROM challenges c JOIN categories cat ON cat.id = c.category_id
             WHERE ' . Challenges::PLAYER_VISIBLE . ' ORDER BY cat.sort_order, cat.name'
        );
        $solves = DB::all(
            'SELECT s.team_id, s.challenge_id FROM solves s JOIN teams t ON t.id = s.team_id
             WHERE t.is_hidden = 0 AND t.is_banned = 0 AND s.created_at <= ?',
            [$c]
        );
        $labels = range(10, 100, 10);

        $groups = ['All challenges' => array_column($tasks, 'id')];
        foreach ($tasks as $t) {
            $groups[$t['category']][] = $t['id'];
        }
        $series = [];
        foreach ($groups as $name => $ids) {
            if ($name !== 'All challenges' && count($ids) < 2) {
                continue;
            }
            $set = array_flip($ids);
            $perTeam = [];
            foreach ($solves as $s) {
                if (isset($set[$s['challenge_id']])) {
                    $perTeam[$s['team_id']] = ($perTeam[$s['team_id']] ?? 0) + 1;
                }
            }
            $teams = count($perTeam);
            $total = count($ids);
            $values = [];
            foreach ($labels as $pct) {
                $need = $total * $pct / 100;
                $n = 0;
                foreach ($perTeam as $solved) {
                    // Small epsilon so 3/10 counts as reaching 30%.
                    if ($solved + 1e-9 >= $need) {
                        $n++;
                    }
                }
                $values[] = $teams ? round($n / $teams * 100, 1) : 0;
            }
            $series[] = ['name' => $name, 'values' => $values, 'teams' => $teams, 'tasks' => $total];
            if (count($series) >= 6) {
                break;
            }
        }
        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * Cumulative score series per team for charts: [teamId => [[ts_ms, score], ...]].
     */
    public static function timelines(array $teamIds, ?int $cutoff = null): array
    {
        if (!$teamIds) {
            return [];
        }
        $c = self::cutoffSql($cutoff);
        $in = implode(',', array_fill(0, count($teamIds), '?'));
        $events = DB::all(
            "SELECT team_id, created_at, (points_awarded + bonus_points) AS d FROM solves WHERE team_id IN ($in) AND created_at <= ?
             UNION ALL
             SELECT team_id, created_at, delta FROM score_adjustments WHERE team_id IN ($in) AND revoked_at IS NULL AND created_at <= ?
             UNION ALL
             SELECT hu.team_id, hu.created_at, -hi.cost FROM hint_unlocks hu JOIN hints hi ON hi.id = hu.hint_id
               WHERE hu.team_id IN ($in) AND hi.cost > 0 AND hu.created_at <= ?
             ORDER BY created_at",
            [...$teamIds, $c, ...$teamIds, $c, ...$teamIds, $c]
        );
        $series = [];
        $running = [];
        $start = parse_dt(setting('starts_at'));
        foreach ($teamIds as $id) {
            $series[$id] = $start ? [[$start * 1000, 0]] : [];
            $running[$id] = 0;
        }
        foreach ($events as $e) {
            $id = (int) $e['team_id'];
            $running[$id] += (int) $e['d'];
            $series[$id][] = [strtotime($e['created_at'] . ' UTC') * 1000, $running[$id]];
        }
        return $series;
    }
}
