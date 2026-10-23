<?php
namespace App\Services;

use App\Core\DB;

final class Challenges
{
    /** SQL condition for challenges players may see right now. */
    public const PLAYER_VISIBLE = "c.state = 'visible' AND (c.release_at IS NULL OR c.release_at <= UTC_TIMESTAMP())";

    public static function code(array $ch): string
    {
        $cat = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $ch['category'] ?? 'CH'), 0, 3)) ?: 'CH';
        return $cat . '-' . str_pad((string) $ch['id'], 2, '0', STR_PAD_LEFT);
    }

    /**
     * Board for a team: challenges grouped by category with solve state,
     * current value and lock status.
     */
    public static function board(int $teamId, ?int $cutoff = null, bool $preview = false): array
    {
        $c = $cutoff ? gmdate('Y-m-d H:i:s', $cutoff) : '9999-12-31 00:00:00';
        $cond = $preview ? "c.state <> 'draft'" : self::PLAYER_VISIBLE;
        $rows = DB::all(
            "SELECT c.id, c.title, c.difficulty, c.author, c.scoring, c.points, c.min_points, c.decay, c.tags, c.requires_id,
                    c.max_attempts, c.first_blood_bonus, cat.name AS category, cat.sort_order AS cat_sort,
                    (SELECT COUNT(*) FROM solves s WHERE s.challenge_id = c.id) AS solve_count,
                    (SELECT COUNT(*) FROM solves s WHERE s.challenge_id = c.id AND s.created_at <= ?) AS public_solves,
                    my.created_at AS solved_at, my.solve_rank AS my_rank
             FROM challenges c
             JOIN categories cat ON cat.id = c.category_id
             LEFT JOIN solves my ON my.challenge_id = c.id AND my.team_id = ?
             WHERE $cond
             ORDER BY cat.sort_order, cat.name, c.sort_order, c.points, c.id",
            [$c, $teamId]
        );
        $solvedIds = array_column(array_filter($rows, fn($r) => $r['solved_at']), 'id');
        $titles = array_column($rows, 'title', 'id');
        $groups = [];
        foreach ($rows as $r) {
            $r['code'] = self::code($r);
            $r['value'] = Scoring::currentValue($r, (int) $r['solve_count']);
            $r['locked'] = $r['requires_id'] && !in_array($r['requires_id'], $solvedIds);
            $r['requires_title'] = $r['requires_id'] ? ($titles[$r['requires_id']] ?? 'another challenge') : null;
            $groups[$r['category']][] = $r;
        }
        return $groups;
    }

    /** Fetch a challenge a player is allowed to open. */
    public static function findForPlayer(int $id, bool $preview = false): ?array
    {
        $cond = $preview ? "c.state <> 'draft'" : self::PLAYER_VISIBLE;
        return DB::one(
            "SELECT c.*, cat.name AS category FROM challenges c JOIN categories cat ON cat.id = c.category_id WHERE c.id = ? AND $cond",
            [$id]
        );
    }

    /** Can players currently see challenges and submit flags? */
    public static function boardOpen(): bool
    {
        return in_array(event_state(), ['live', 'paused', 'ended'], true) || is_admin();
    }

    public static function submissionsOpen(): bool
    {
        return event_state() === 'live';
    }
}
