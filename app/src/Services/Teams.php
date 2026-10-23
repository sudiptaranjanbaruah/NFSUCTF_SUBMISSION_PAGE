<?php
namespace App\Services;

use App\Core\DB;

final class Teams
{
    public static function validateName(string $name): ?string
    {
        $len = mb_strlen($name);
        if ($len < 2 || $len > 40) {
            return 'Team names must be 2–40 characters.';
        }
        if (!preg_match('/^[\p{L}\p{N} _.\'&()-]+$/u', $name)) {
            return 'Team names can use letters, numbers, spaces and _ . \' & ( ) -';
        }
        if (DB::val('SELECT id FROM teams WHERE name = ?', [$name])) {
            return 'That team name is taken.';
        }
        return null;
    }

    public static function create(string $name, int $captainId, ?string $affiliation = null, bool $solo = false): int
    {
        do {
            $code = str_random(8);
        } while (DB::val('SELECT id FROM teams WHERE invite_code = ?', [$code]));

        $id = DB::insert('teams', [
            'name' => $name,
            'invite_code' => $code,
            'captain_id' => $captainId,
            'affiliation' => $affiliation ?: null,
            'is_solo' => $solo ? 1 : 0,
        ]);
        DB::query('UPDATE users SET team_id = ? WHERE id = ?', [$id, $captainId]);
        return $id;
    }

    public static function memberCount(int $teamId): int
    {
        return (int) DB::val('SELECT COUNT(*) FROM users WHERE team_id = ?', [$teamId]);
    }

    /** Returns an error message or null when the user joined. */
    public static function join(int $userId, array $team): ?string
    {
        if ($team['is_banned']) {
            return 'That team is not accepting members.';
        }
        if ($team['is_solo']) {
            return 'That is a solo entry and cannot take members.';
        }
        $max = (int) setting('team_size_max');
        if ($max > 0 && self::memberCount((int) $team['id']) >= $max) {
            return "That team is full ($max members max).";
        }
        DB::query('UPDATE users SET team_id = ? WHERE id = ?', [$team['id'], $userId]);
        if (!$team['captain_id']) {
            DB::query('UPDATE teams SET captain_id = ? WHERE id = ?', [$userId, $team['id']]);
        }
        return null;
    }

    /** Whether the team has any score history (then it is kept when emptied). */
    public static function hasActivity(int $teamId): bool
    {
        return (bool) DB::val('SELECT 1 FROM submissions WHERE team_id = ? LIMIT 1', [$teamId])
            || (bool) DB::val('SELECT 1 FROM score_adjustments WHERE team_id = ? LIMIT 1', [$teamId]);
    }
}
