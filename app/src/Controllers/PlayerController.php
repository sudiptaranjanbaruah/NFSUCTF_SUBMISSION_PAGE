<?php
namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\DB;
use App\Core\Request;
use App\Services\Challenges;
use App\Services\Scoring;

final class PlayerController
{
    public function dashboard(Request $r): void
    {
        $user = current_user();
        $team = current_team();
        $data = [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'user' => $user,
            'team' => $team,
            'state' => event_state(),
            'announcements' => DB::all('SELECT * FROM announcements ORDER BY is_pinned DESC, created_at DESC LIMIT 4'),
            'feed' => $this->feed(12),
            'totalChallenges' => (int) DB::val('SELECT COUNT(*) FROM challenges c WHERE ' . Challenges::PLAYER_VISIBLE),
        ];
        if ($team) {
            $tid = (int) $team['id'];
            $data['score'] = Scoring::teamScore($tid);
            $data['rank'] = Scoring::teamRank($tid);
            $data['teamCount'] = (int) DB::val('SELECT COUNT(*) FROM teams WHERE is_hidden = 0 AND is_banned = 0');
            $data['solved'] = (int) DB::val('SELECT COUNT(*) FROM solves WHERE team_id = ?', [$tid]);
            $data['mySolves'] = (int) DB::val('SELECT COUNT(*) FROM solves WHERE user_id = ?', [$user['id']]);
            $data['recent'] = DB::all(
                'SELECT s.*, c.title, u.username FROM solves s JOIN challenges c ON c.id = s.challenge_id
                 LEFT JOIN users u ON u.id = s.user_id WHERE s.team_id = ? ORDER BY s.created_at DESC LIMIT 5',
                [$tid]
            );
            $data['next'] = DB::all(
                'SELECT c.id, c.title, c.difficulty, cat.name AS category,
                        (SELECT COUNT(*) FROM solves s WHERE s.challenge_id = c.id) AS solve_count
                 FROM challenges c JOIN categories cat ON cat.id = c.category_id
                 WHERE ' . Challenges::PLAYER_VISIBLE . ' AND c.requires_id IS NULL
                   AND NOT EXISTS (SELECT 1 FROM solves s WHERE s.challenge_id = c.id AND s.team_id = ?)
                 ORDER BY solve_count DESC, c.points ASC LIMIT 4',
                [$tid]
            );
        }
        view('player/dashboard', $data);
    }

    /** Public activity: first bloods and public awards (respecting the freeze). */
    private function feed(int $limit, ?string $since = null): array
    {
        $cutoff = freeze_cutoff();
        $c = $cutoff ? gmdate('Y-m-d H:i:s', $cutoff) : '9999-12-31 00:00:00';
        $s = $since ?: '1970-01-01';
        return DB::all(
            "(SELECT 'blood' AS kind, s.created_at, t.id AS team_id, t.name AS team, c.title AS subject, s.bonus_points AS amount, NULL AS reason
              FROM solves s JOIN teams t ON t.id = s.team_id JOIN challenges c ON c.id = s.challenge_id
              WHERE s.solve_rank = 1 AND t.is_hidden = 0 AND s.created_at <= ? AND s.created_at > ?)
             UNION ALL
             (SELECT 'award', a.created_at, t.id, t.name, COALESCE(c.title, ''), a.delta, a.reason
              FROM score_adjustments a JOIN teams t ON t.id = a.team_id LEFT JOIN challenges c ON c.id = a.challenge_id
              WHERE a.is_public = 1 AND a.revoked_at IS NULL AND a.delta > 0 AND t.is_hidden = 0 AND a.created_at <= ? AND a.created_at > ?)
             ORDER BY created_at DESC LIMIT $limit",
            [$c, $s, $c, $s]
        );
    }

    /** Lightweight poll for live toasts (first bloods, announcements). */
    public function poll(Request $r): void
    {
        $since = $r->query('since');
        $sinceTs = $since && ctype_digit($since) ? gmdate('Y-m-d H:i:s', (int) $since) : gmdate('Y-m-d H:i:s', time() - 30);
        $user = current_user();
        $events = array_map(fn($e) => [
            'kind' => $e['kind'],
            'team' => $e['team'],
            'subject' => $e['subject'],
            'amount' => (int) $e['amount'],
            'reason' => $e['reason'],
        ], array_reverse($this->feed(10, $sinceTs)));
        $ann = DB::all('SELECT id, title FROM announcements WHERE created_at > ? ORDER BY created_at', [$sinceTs]);
        $unread = (int) DB::val('SELECT COUNT(*) FROM announcements WHERE created_at > COALESCE(?, "1970-01-01")', [$user['announcements_seen_at']]);
        json_out(['ok' => true, 'now' => time(), 'events' => $events, 'announcements' => $ann, 'unread' => $unread]);
    }

    public function notifications(Request $r): void
    {
        $user = current_user();
        DB::query('UPDATE users SET announcements_seen_at = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
        view('player/notifications', [
            'title' => 'Announcements',
            'active' => '',
            'items' => DB::all('SELECT * FROM announcements ORDER BY is_pinned DESC, created_at DESC'),
            'seenAt' => $user['announcements_seen_at'],
        ]);
    }

    public function profile(Request $r): void
    {
        $user = current_user();
        view('player/profile', [
            'title' => 'Profile',
            'active' => '',
            'user' => $user,
            'team' => current_team(),
            'identities' => DB::all('SELECT provider, email, created_at FROM oauth_identities WHERE user_id = ?', [$user['id']]),
            'solves' => DB::all(
                'SELECT s.*, c.title, cat.name AS category FROM solves s JOIN challenges c ON c.id = s.challenge_id
                 JOIN categories cat ON cat.id = c.category_id WHERE s.user_id = ? ORDER BY s.created_at DESC',
                [$user['id']]
            ),
        ]);
    }

    public function updateProfile(Request $r): void
    {
        $user = current_user();
        $username = (string) $r->input('username');
        if ($username !== $user['username']) {
            if ($err = AuthService::validateUsername($username, (int) $user['id'])) {
                flash('error', $err);
                redirect('/profile');
            }
        }
        DB::update('users', [
            'username' => $username,
            'full_name' => mb_substr((string) $r->input('full_name'), 0, 120) ?: null,
            'affiliation' => mb_substr((string) $r->input('affiliation'), 0, 120) ?: null,
            'country' => mb_substr((string) $r->input('country'), 0, 64) ?: null,
        ], 'id = ?', [$user['id']]);
        flash('success', 'Profile saved.');
        redirect('/profile');
    }

    public function updatePassword(Request $r): void
    {
        $user = current_user();
        $current = (string) $r->raw('current_password');
        $new = (string) $r->raw('password');
        if ($user['password_hash'] && !password_verify($current, $user['password_hash'])) {
            flash('error', 'Your current password is incorrect.');
            redirect('/profile#security');
        }
        if ($err = AuthService::validatePassword($new)) {
            flash('error', $err);
            redirect('/profile#security');
        }
        DB::query('UPDATE users SET password_hash = ? WHERE id = ?', [AuthService::hash($new), $user['id']]);
        audit('user.password_change', 'user:' . $user['id']);
        flash('success', $user['password_hash'] ? 'Password changed.' : 'Password set. You can now sign in with it.');
        redirect('/profile#security');
    }

    public function unlink(Request $r, string $provider): void
    {
        $user = current_user();
        $count = (int) DB::val('SELECT COUNT(*) FROM oauth_identities WHERE user_id = ?', [$user['id']]);
        // Email codes always work, so unlinking never locks anyone out.
        DB::query('DELETE FROM oauth_identities WHERE user_id = ? AND provider = ?', [$user['id'], $provider]);
        flash('success', ucfirst($provider) . ' disconnected.' . ($count <= 1 && !$user['password_hash'] ? ' You can still sign in with an email code.' : ''));
        redirect('/profile#security');
    }
}
