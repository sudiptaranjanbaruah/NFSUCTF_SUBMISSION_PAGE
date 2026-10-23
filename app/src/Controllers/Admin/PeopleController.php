<?php
namespace App\Controllers\Admin;

use App\Auth\AuthService;
use App\Core\DB;
use App\Core\Request;
use App\Mail\Mailer;
use App\Services\Scoring;
use App\Services\Teams;

final class PeopleController
{
    // ---------------- Users ----------------

    public function users(Request $r): void
    {
        $where = ['1=1'];
        $params = [];
        if ($q = $r->query('q')) {
            $where[] = '(u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        $f = $r->query('filter');
        if ($f === 'unverified') {
            $where[] = 'u.email_verified_at IS NULL';
        } elseif ($f === 'no_team') {
            $where[] = 'u.team_id IS NULL AND u.role = "user"';
        } elseif ($f === 'admins') {
            $where[] = 'u.role <> "user"';
        } elseif ($f === 'banned') {
            $where[] = 'u.is_banned = 1';
        }
        $w = implode(' AND ', $where);
        $page = max(1, (int) $r->query('page', 1));
        $total = (int) DB::val("SELECT COUNT(*) FROM users u WHERE $w", $params);
        view('admin/users', [
            'title' => 'Users',
            'active' => 'users',
            'rows' => DB::all(
                "SELECT u.*, t.name AS team,
                        (SELECT GROUP_CONCAT(provider) FROM oauth_identities o WHERE o.user_id = u.id) AS providers,
                        (SELECT COUNT(*) FROM solves s WHERE s.user_id = u.id) AS solves
                 FROM users u LEFT JOIN teams t ON t.id = u.team_id WHERE $w ORDER BY u.created_at DESC LIMIT 50 OFFSET " . (($page - 1) * 50),
                $params
            ),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / 50)),
            'q' => $q,
            'filter' => $f,
        ], 'admin');
    }

    public function user(Request $r, string $id): void
    {
        $u = DB::one('SELECT u.*, t.name AS team FROM users u LEFT JOIN teams t ON t.id = u.team_id WHERE u.id = ?', [(int) $id]);
        if (!$u) {
            abort(404);
        }
        view('admin/user', [
            'title' => $u['username'],
            'active' => 'users',
            'u' => $u,
            'teams' => DB::all('SELECT id, name FROM teams ORDER BY name'),
            'logins' => DB::all('SELECT * FROM login_events WHERE user_id = ? OR email = ? ORDER BY id DESC LIMIT 20', [$u['id'], $u['email']]),
            'identities' => DB::all('SELECT * FROM oauth_identities WHERE user_id = ?', [$u['id']]),
            'solves' => DB::all('SELECT s.*, c.title FROM solves s JOIN challenges c ON c.id = s.challenge_id WHERE s.user_id = ? ORDER BY s.created_at DESC', [$u['id']]),
            'ips' => DB::all('SELECT ip, COUNT(*) AS n, MAX(created_at) AS last FROM submissions WHERE user_id = ? GROUP BY ip ORDER BY n DESC LIMIT 10', [$u['id']]),
        ], 'admin');
    }

    public function updateUser(Request $r, string $id): void
    {
        $u = DB::one('SELECT * FROM users WHERE id = ?', [(int) $id]);
        if (!$u) {
            abort(404);
        }
        $me = current_user();
        $action = (string) $r->input('action');

        switch ($action) {
            case 'ban':
            case 'unban':
                if ((int) $u['id'] === (int) $me['id']) {
                    flash('error', 'You cannot ban yourself.');
                    break;
                }
                DB::query('UPDATE users SET is_banned = ? WHERE id = ?', [$action === 'ban' ? 1 : 0, $u['id']]);
                flash('success', $action === 'ban' ? 'User banned. They are signed out on their next request.' : 'User unbanned.');
                break;
            case 'verify':
                DB::query('UPDATE users SET email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?', [$u['id']]);
                flash('success', 'Email marked as verified.');
                break;
            case 'role':
                $role = (string) $r->input('role');
                if (!in_array($role, ['user', 'admin', 'super_admin'], true)) {
                    break;
                }
                if ($me['role'] !== 'super_admin') {
                    flash('error', 'Only super admins can change roles.');
                    break;
                }
                if ((int) $u['id'] === (int) $me['id'] && $role !== 'super_admin') {
                    flash('error', 'You cannot demote yourself.');
                    break;
                }
                DB::query('UPDATE users SET role = ? WHERE id = ?', [$role, $u['id']]);
                flash('success', 'Role updated.');
                break;
            case 'team':
                $teamId = (int) $r->input('team_id') ?: null;
                DB::query('UPDATE users SET team_id = ? WHERE id = ?', [$teamId, $u['id']]);
                if ($teamId && !DB::val('SELECT captain_id FROM teams WHERE id = ?', [$teamId])) {
                    DB::query('UPDATE teams SET captain_id = ? WHERE id = ?', [$u['id'], $teamId]);
                }
                flash('success', $teamId ? 'Moved to the new team. Their past solves stay with the old team.' : 'Removed from team.');
                break;
            case 'profile':
                $username = (string) $r->input('username');
                $email = AuthService::normalizeEmail((string) $r->input('email'));
                if ($err = AuthService::validateUsername($username, (int) $u['id'])) {
                    flash('error', $err);
                    break;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || DB::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) {
                    flash('error', 'That email is invalid or used by another account.');
                    break;
                }
                DB::update('users', ['username' => $username, 'email' => $email, 'full_name' => (string) $r->input('full_name') ?: null, 'affiliation' => (string) $r->input('affiliation') ?: null], 'id = ?', [$u['id']]);
                flash('success', 'Profile updated.');
                break;
        }
        audit('admin.user_' . $action, 'user:' . $u['id'], array_intersect_key($r->all(), array_flip(['role', 'team_id', 'username', 'email'])));
        redirect('/admin/users/' . $u['id']);
    }

    // ---------------- Teams ----------------

    public function teams(Request $r): void
    {
        $q = $r->query('q');
        $board = [];
        foreach (Scoring::scoreboard(null, true) as $row) {
            $board[(int) $row['id']] = $row;
        }
        $rows = DB::all(
            'SELECT t.*, u.username AS captain, (SELECT COUNT(*) FROM users m WHERE m.team_id = t.id) AS members
             FROM teams t LEFT JOIN users u ON u.id = t.captain_id' . ($q ? ' WHERE t.name LIKE ? OR t.affiliation LIKE ?' : '') . ' ORDER BY t.created_at DESC',
            $q ? ["%$q%", "%$q%"] : []
        );
        foreach ($rows as &$t) {
            $t['rank'] = $board[(int) $t['id']]['rank'] ?? null;
            $t['score'] = $board[(int) $t['id']]['score'] ?? Scoring::teamScore((int) $t['id']);
            $t['solves'] = $board[(int) $t['id']]['solves'] ?? 0;
        }
        view('admin/teams', ['title' => 'Teams', 'active' => 'teams', 'rows' => $rows, 'q' => $q], 'admin');
    }

    public function team(Request $r, string $id): void
    {
        $t = DB::one('SELECT * FROM teams WHERE id = ?', [(int) $id]);
        if (!$t) {
            abort(404);
        }
        $tid = (int) $t['id'];
        view('admin/team', [
            'title' => $t['name'],
            'active' => 'teams',
            't' => $t,
            'score' => Scoring::teamScore($tid),
            'rank' => Scoring::teamRank($tid),
            'members' => DB::all(
                'SELECT u.*, (SELECT COUNT(*) FROM solves s WHERE s.user_id = u.id AND s.team_id = ?) AS solves,
                        (SELECT COUNT(*) FROM submissions s WHERE s.user_id = u.id AND s.team_id = ?) AS attempts
                 FROM users u WHERE u.team_id = ?',
                [$tid, $tid, $tid]
            ),
            'solves' => DB::all('SELECT s.*, c.title, u.username FROM solves s JOIN challenges c ON c.id = s.challenge_id LEFT JOIN users u ON u.id = s.user_id WHERE s.team_id = ? ORDER BY s.created_at DESC', [$tid]),
            'awards' => DB::all('SELECT * FROM score_adjustments WHERE team_id = ? ORDER BY created_at DESC', [$tid]),
            'hints' => DB::all('SELECT hu.created_at, h.cost, c.title FROM hint_unlocks hu JOIN hints h ON h.id = hu.hint_id JOIN challenges c ON c.id = h.challenge_id WHERE hu.team_id = ? ORDER BY hu.created_at DESC', [$tid]),
            'ips' => DB::all(
                'SELECT s.ip, COUNT(*) AS n, MAX(s.created_at) AS last,
                        (SELECT COUNT(DISTINCT x.team_id) FROM submissions x WHERE x.ip = s.ip) AS teams_on_ip
                 FROM submissions s WHERE s.team_id = ? GROUP BY s.ip ORDER BY n DESC LIMIT 15',
                [$tid]
            ),
            'timeline' => Scoring::timelines([$tid])[$tid] ?? [],
            'attempts' => (int) DB::val('SELECT COUNT(*) FROM submissions WHERE team_id = ?', [$tid]),
        ], 'admin');
    }

    public function updateTeam(Request $r, string $id): void
    {
        $t = DB::one('SELECT * FROM teams WHERE id = ?', [(int) $id]);
        if (!$t) {
            abort(404);
        }
        $action = (string) $r->input('action');
        switch ($action) {
            case 'ban':
            case 'unban':
                DB::query('UPDATE teams SET is_banned = ? WHERE id = ?', [$action === 'ban' ? 1 : 0, $t['id']]);
                flash('success', $action === 'ban' ? 'Team disqualified and removed from the scoreboard.' : 'Team reinstated.');
                break;
            case 'hide':
            case 'unhide':
                DB::query('UPDATE teams SET is_hidden = ? WHERE id = ?', [$action === 'hide' ? 1 : 0, $t['id']]);
                flash('success', $action === 'hide' ? 'Team hidden from the public scoreboard (e.g. organisers or testers).' : 'Team visible again.');
                break;
            case 'details':
                $name = (string) $r->input('name');
                if ($name !== $t['name'] && ($err = Teams::validateName($name))) {
                    flash('error', $err);
                    break;
                }
                DB::update('teams', [
                    'name' => $name,
                    'affiliation' => (string) $r->input('affiliation') ?: null,
                    'country' => (string) $r->input('country') ?: null,
                    'captain_id' => (int) $r->input('captain_id') ?: null,
                ], 'id = ?', [$t['id']]);
                flash('success', 'Team updated.');
                break;
            case 'delete':
                DB::query('UPDATE users SET team_id = NULL WHERE team_id = ?', [$t['id']]);
                DB::query('DELETE FROM teams WHERE id = ?', [$t['id']]);
                audit('admin.team_delete', 'team:' . $t['id'], ['name' => $t['name']]);
                flash('success', "Deleted {$t['name']}. Its members are now teamless.");
                redirect('/admin/teams');
        }
        audit('admin.team_' . $action, 'team:' . $t['id']);
        redirect('/admin/teams/' . $t['id']);
    }

    // ---------------- Invites / bulk import ----------------

    public function invites(Request $r): void
    {
        view('admin/invites', [
            'title' => 'Invites and bulk import',
            'active' => 'invites',
            'rows' => DB::all('SELECT * FROM invites ORDER BY created_at DESC LIMIT 300'),
            'queued' => (int) DB::val('SELECT COUNT(*) FROM mail_queue WHERE sent_at IS NULL AND attempts < 3'),
            'failed' => (int) DB::val('SELECT COUNT(*) FROM mail_queue WHERE sent_at IS NULL AND attempts >= 3'),
            'sentHour' => (int) DB::val('SELECT COUNT(*) FROM mail_queue WHERE sent_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR'),
        ], 'admin');
    }

    public function importInvites(Request $r): void
    {
        $text = (string) ($_POST['csv'] ?? '');
        $file = $r->file('csv_file');
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $text = (string) file_get_contents($file['tmp_name']);
        }
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $days = max(1, min(60, (int) $r->input('days', 14)));
        $sendNow = $r->input('send') === '1';
        $made = 0;
        $skipped = [];
        foreach ($lines as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = array_map('trim', str_getcsv($line, ',', '"', ''));
            $email = AuthService::normalizeEmail($cols[0] ?? '');
            if ($n === 0 && !filter_var($email, FILTER_VALIDATE_EMAIL) && stripos($line, 'email') !== false) {
                continue; // header row
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = 'line ' . ($n + 1) . ' (invalid email)';
                continue;
            }
            $name = mb_substr($cols[1] ?? '', 0, 120) ?: null;
            $team = mb_substr($cols[2] ?? '', 0, 40) ?: null;
            if (DB::val('SELECT id FROM users WHERE email = ? AND email_verified_at IS NOT NULL', [$email]) && !$team) {
                $skipped[] = "$email (already registered)";
                continue;
            }
            $token = bin2hex(random_bytes(24));
            DB::query('UPDATE invites SET expires_at = UTC_TIMESTAMP() WHERE email = ? AND used_at IS NULL', [$email]);
            DB::insert('invites', [
                'email' => $email,
                'full_name' => $name,
                'team_name' => $team,
                'token_hash' => hash('sha256', $token),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + $days * 86400),
                'created_by' => current_user()['id'],
            ]);
            if ($sendNow) {
                Mailer::queue($email, 'You\'re registered for ' . setting('event_name'), 'invite', [
                    'name' => $name, 'team' => $team, 'link' => url('/invite/' . $token), 'days' => $days,
                ]);
            }
            $made++;
        }
        audit('admin.invites_import', null, ['count' => $made, 'skipped' => count($skipped)]);
        flash($made ? 'success' : 'warning', "Created $made invites" . ($sendNow ? ' and queued their emails' : '') . '.' . ($skipped ? ' Skipped: ' . implode(', ', array_slice($skipped, 0, 8)) . (count($skipped) > 8 ? '…' : '') : ''));
        redirect('/admin/invites');
    }

    public function resendInvite(Request $r, string $id): void
    {
        $inv = DB::one('SELECT * FROM invites WHERE id = ?', [(int) $id]);
        if (!$inv || $inv['used_at']) {
            flash('error', 'That invite was already used.');
            redirect('/admin/invites');
        }
        // Tokens are stored hashed, so a resend issues a fresh link.
        $token = bin2hex(random_bytes(24));
        DB::update('invites', ['token_hash' => hash('sha256', $token), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 14 * 86400)], 'id = ?', [$inv['id']]);
        Mailer::queue($inv['email'], 'You\'re registered for ' . setting('event_name'), 'invite', [
            'name' => $inv['full_name'], 'team' => $inv['team_name'], 'link' => url('/invite/' . $token), 'days' => 14,
        ]);
        flash('success', 'A fresh invite link is queued for ' . $inv['email'] . '.');
        redirect('/admin/invites');
    }

    public function drainMail(Request $r): void
    {
        $sent = Mailer::drain(25);
        flash($sent ? 'success' : 'info', $sent ? "Sent $sent queued emails." : 'Nothing sent. The queue is empty or the hourly cap is reached.');
        redirect('/admin/invites');
    }
}
