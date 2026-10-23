<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\RateLimit;
use App\Core\Request;
use App\Services\Scoring;
use App\Services\Teams;

final class TeamController
{
    /** My team page, or the create/join choice when I have none. */
    public function mine(Request $r): void
    {
        $team = current_team();
        if (!$team) {
            view('player/team_choose', ['title' => 'Join a team', 'active' => 'team']);
            return;
        }
        $this->renderTeam($team, true);
    }

    /** Public team profile. */
    public function show(Request $r, string $id): void
    {
        $team = DB::one('SELECT * FROM teams WHERE id = ? AND is_banned = 0', [(int) $id]);
        if (!$team || ($team['is_hidden'] && !is_admin())) {
            abort(404, 'Team not found.');
        }
        $mine = current_team();
        $this->renderTeam($team, $mine && (int) $mine['id'] === (int) $team['id']);
    }

    private function renderTeam(array $team, bool $isMine): void
    {
        $tid = (int) $team['id'];
        // Other teams only see activity up to the freeze.
        $cutoff = ($isMine || is_admin()) ? null : freeze_cutoff();
        $c = $cutoff ? gmdate('Y-m-d H:i:s', $cutoff) : '9999-12-31 00:00:00';

        $members = DB::all(
            'SELECT u.id, u.username, u.full_name, u.affiliation,
                    (SELECT COUNT(*) FROM solves s WHERE s.user_id = u.id AND s.team_id = ? AND s.created_at <= ?) AS solves,
                    (SELECT COALESCE(SUM(points_awarded + bonus_points), 0) FROM solves s WHERE s.user_id = u.id AND s.team_id = ? AND s.created_at <= ?) AS points
             FROM users u WHERE u.team_id = ? ORDER BY points DESC, u.username',
            [$tid, $c, $tid, $c, $tid]
        );
        $solves = DB::all(
            'SELECT s.*, c.title, cat.name AS category, u.username FROM solves s
             JOIN challenges c ON c.id = s.challenge_id JOIN categories cat ON cat.id = c.category_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.team_id = ? AND s.created_at <= ? ORDER BY s.created_at DESC',
            [$tid, $c]
        );
        $awards = DB::all(
            'SELECT a.*, u.username, c.title AS challenge_title FROM score_adjustments a
             LEFT JOIN users u ON u.id = a.user_id LEFT JOIN challenges c ON c.id = a.challenge_id
             WHERE a.team_id = ? AND a.revoked_at IS NULL AND a.created_at <= ? ' . ($isMine || is_admin() ? '' : 'AND a.is_public = 1 ') . '
             ORDER BY a.created_at DESC',
            [$tid, $c]
        );
        $hintCost = (int) DB::val(
            'SELECT COALESCE(SUM(h.cost), 0) FROM hint_unlocks hu JOIN hints h ON h.id = hu.hint_id WHERE hu.team_id = ? AND hu.created_at <= ?',
            [$tid, $c]
        );
        $categories = DB::all(
            "SELECT cat.name,
                    (SELECT COUNT(*) FROM challenges c WHERE c.category_id = cat.id AND c.state = 'visible' AND (c.release_at IS NULL OR c.release_at <= UTC_TIMESTAMP())) AS total,
                    (SELECT COUNT(*) FROM solves s JOIN challenges c ON c.id = s.challenge_id WHERE c.category_id = cat.id AND s.team_id = ? AND s.created_at <= ?) AS solved
             FROM categories cat ORDER BY cat.sort_order",
            [$tid, $c]
        );
        $categories = array_values(array_filter($categories, fn($x) => $x['total'] > 0));

        $board = Scoring::scoreboard($cutoff, true);
        $row = null;
        foreach ($board as $b) {
            if ((int) $b['id'] === $tid) {
                $row = $b;
                break;
            }
        }

        view('player/team', [
            'title' => $team['name'],
            'active' => $isMine ? 'team' : 'scoreboard',
            'team' => $team,
            'isMine' => $isMine,
            'isCaptain' => $isMine && (int) $team['captain_id'] === (int) current_user()['id'],
            'members' => $members,
            'solves' => $solves,
            'awards' => $awards,
            'hintCost' => $hintCost,
            'categories' => $categories,
            'row' => $row,
            'timeline' => Scoring::timelines([$tid], $cutoff)[$tid] ?? [],
            'frozen' => (bool) $cutoff,
        ]);
    }

    public function create(Request $r): void
    {
        $user = current_user();
        if ($user['team_id']) {
            flash('error', 'Leave your current team first.');
            redirect('/team');
        }
        $solo = $r->input('solo') === '1';
        if ($solo && setting('allow_solo') !== '1') {
            flash('error', 'Solo entries are not allowed in this event.');
            redirect('/team');
        }
        $name = $solo ? $user['username'] : (string) $r->input('name');
        if ($solo && DB::val('SELECT id FROM teams WHERE name = ?', [$name])) {
            $name .= '-' . str_random(3);
        }
        if ($err = Teams::validateName($name)) {
            flash('error', $err);
            with_old($r->all());
            redirect('/team');
        }
        Teams::create($name, (int) $user['id'], (string) ($r->input('affiliation') ?: $user['affiliation']), $solo);
        audit('team.create', 'team:' . $name);
        flash('success', $solo ? 'You are registered as a solo player.' : "Team \"$name\" created. Share the invite code with your teammates.");
        redirect('/team');
    }

    public function join(Request $r): void
    {
        $user = current_user();
        if ($user['team_id']) {
            flash('error', 'Leave your current team first.');
            redirect('/team');
        }
        if (!RateLimit::hit('join:' . $user['id'], 10, 600)) {
            flash('error', 'Too many attempts. Wait a few minutes.');
            redirect('/team');
        }
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $r->input('code')));
        $team = $code ? DB::one('SELECT * FROM teams WHERE invite_code = ?', [$code]) : null;
        if (!$team) {
            flash('error', 'No team has that invite code. Check it with your captain.');
            redirect('/team');
        }
        if ($err = Teams::join((int) $user['id'], $team)) {
            flash('error', $err);
            redirect('/team');
        }
        audit('team.join', 'team:' . $team['id']);
        flash('success', "You joined {$team['name']}.");
        redirect('/team');
    }

    public function leave(Request $r): void
    {
        $user = current_user();
        $team = current_team();
        if (self::hasSolves((int) $team['id'], (int) $user['id']) && event_state() === 'live') {
            flash('error', 'You have solves for this team, so you cannot leave during the event. Ask an organiser if you need to move.');
            redirect('/team');
        }
        DB::query('UPDATE users SET team_id = NULL WHERE id = ?', [$user['id']]);
        $remaining = DB::all('SELECT id FROM users WHERE team_id = ? ORDER BY id', [$team['id']]);
        if (!$remaining) {
            if (!Teams::hasActivity((int) $team['id'])) {
                DB::query('DELETE FROM teams WHERE id = ?', [$team['id']]);
            }
        } elseif ((int) $team['captain_id'] === (int) $user['id']) {
            DB::query('UPDATE teams SET captain_id = ? WHERE id = ?', [$remaining[0]['id'], $team['id']]);
        }
        audit('team.leave', 'team:' . $team['id']);
        flash('info', "You left {$team['name']}.");
        redirect('/team');
    }

    private function requireCaptain(): array
    {
        $team = current_team();
        if ((int) $team['captain_id'] !== (int) current_user()['id']) {
            flash('error', 'Only the team captain can do that.');
            redirect('/team');
        }
        return $team;
    }

    public function update(Request $r): void
    {
        $team = $this->requireCaptain();
        $name = (string) $r->input('name');
        if ($name !== $team['name']) {
            if (DB::val('SELECT 1 FROM solves WHERE team_id = ? LIMIT 1', [$team['id']]) && event_state() !== 'upcoming') {
                flash('error', 'Team names are locked once you have scored.');
                redirect('/team');
            }
            if ($err = Teams::validateName($name)) {
                flash('error', $err);
                redirect('/team');
            }
        }
        DB::update('teams', [
            'name' => $name,
            'affiliation' => mb_substr((string) $r->input('affiliation'), 0, 120) ?: null,
            'country' => mb_substr((string) $r->input('country'), 0, 64) ?: null,
        ], 'id = ?', [$team['id']]);
        flash('success', 'Team details saved.');
        redirect('/team');
    }

    public function regenerate(Request $r): void
    {
        $team = $this->requireCaptain();
        do {
            $code = str_random(8);
        } while (DB::val('SELECT id FROM teams WHERE invite_code = ?', [$code]));
        DB::query('UPDATE teams SET invite_code = ? WHERE id = ?', [$code, $team['id']]);
        flash('success', 'New invite code created. The old one no longer works.');
        redirect('/team');
    }

    public function kick(Request $r, string $id): void
    {
        $team = $this->requireCaptain();
        $uid = (int) $id;
        if ($uid === (int) current_user()['id']) {
            redirect('/team');
        }
        if (self::hasSolves((int) $team['id'], $uid) && event_state() === 'live') {
            flash('error', 'That member has solves for your team. Ask an organiser to move them.');
            redirect('/team');
        }
        DB::query('UPDATE users SET team_id = NULL WHERE id = ? AND team_id = ?', [$uid, $team['id']]);
        audit('team.kick', 'user:' . $uid, ['team' => $team['id']]);
        flash('info', 'Member removed.');
        redirect('/team');
    }

    private static function hasSolves(int $teamId, int $userId): bool
    {
        return (bool) DB::val('SELECT 1 FROM solves WHERE team_id = ? AND user_id = ? LIMIT 1', [$teamId, $userId]);
    }

    public function transfer(Request $r, string $id): void
    {
        $team = $this->requireCaptain();
        if (DB::val('SELECT id FROM users WHERE id = ? AND team_id = ?', [(int) $id, $team['id']])) {
            DB::query('UPDATE teams SET captain_id = ? WHERE id = ?', [(int) $id, $team['id']]);
            flash('success', 'Captain role transferred.');
        }
        redirect('/team');
    }
}

