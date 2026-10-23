<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\RateLimit;
use App\Core\Request;
use App\Services\Challenges;
use App\Services\Scoring;

final class ChallengeController
{
    public function index(Request $r): void
    {
        $team = current_team();
        $state = event_state();
        if (!Challenges::boardOpen()) {
            view('player/challenges_closed', ['title' => 'Challenges', 'active' => 'challenges', 'state' => $state]);
            return;
        }
        $groups = Challenges::board((int) $team['id'], is_admin() ? null : freeze_cutoff());
        $total = 0;
        $solved = 0;
        foreach ($groups as $list) {
            $total += count($list);
            $solved += count(array_filter($list, fn($c) => $c['solved_at']));
        }
        view('player/challenges', [
            'title' => 'Challenges',
            'active' => 'challenges',
            'groups' => $groups,
            'total' => $total,
            'solved' => $solved,
            'state' => $state,
            'score' => Scoring::teamScore((int) $team['id']),
        ]);
    }

    public function show(Request $r, string $id): void
    {
        $team = current_team();
        if (!Challenges::boardOpen()) {
            abort(403, 'Challenges open when the event starts.');
        }
        $ch = Challenges::findForPlayer((int) $id);
        if (!$ch) {
            abort(404, 'Challenge not found.');
        }
        $solvedByUs = DB::one('SELECT s.*, u.username FROM solves s LEFT JOIN users u ON u.id = s.user_id WHERE s.team_id = ? AND s.challenge_id = ?', [$team['id'], $ch['id']]);
        if ($ch['requires_id'] && !DB::val('SELECT 1 FROM solves WHERE team_id = ? AND challenge_id = ?', [$team['id'], $ch['requires_id']])) {
            $req = DB::val('SELECT title FROM challenges WHERE id = ?', [$ch['requires_id']]);
            abort(403, 'Solve "' . $req . '" first to unlock this challenge.');
        }

        $cutoff = freeze_cutoff();
        $c = $cutoff ? gmdate('Y-m-d H:i:s', $cutoff) : '9999-12-31 00:00:00';
        $solveCount = (int) DB::val('SELECT COUNT(*) FROM solves WHERE challenge_id = ?', [$ch['id']]);
        $solvers = DB::all(
            'SELECT t.id, t.name, s.created_at, s.solve_rank FROM solves s JOIN teams t ON t.id = s.team_id
             WHERE s.challenge_id = ? AND t.is_hidden = 0 AND (s.created_at <= ? OR s.team_id = ?)
             ORDER BY s.created_at LIMIT 50',
            [$ch['id'], $c, $team['id']]
        );
        $files = DB::all('SELECT id, original_name, size, sha256 FROM challenge_files WHERE challenge_id = ? ORDER BY id', [$ch['id']]);
        $hints = DB::all(
            'SELECT h.id, h.cost, h.content, hu.id AS unlocked FROM hints h
             LEFT JOIN hint_unlocks hu ON hu.hint_id = h.id AND hu.team_id = ?
             WHERE h.challenge_id = ? ORDER BY h.sort_order, h.id',
            [$team['id'], $ch['id']]
        );
        $wrong = (int) DB::val('SELECT COUNT(*) FROM submissions WHERE team_id = ? AND challenge_id = ? AND correct = 0', [$team['id'], $ch['id']]);

        json_out([
            'ok' => true,
            'challenge' => [
                'id' => (int) $ch['id'],
                'code' => Challenges::code($ch),
                'title' => $ch['title'],
                'category' => $ch['category'],
                'difficulty' => $ch['difficulty'],
                'author' => $ch['author'],
                'tags' => array_values(array_filter(array_map('trim', explode(',', (string) $ch['tags'])))),
                'description_html' => md($ch['description']),
                'connection' => $ch['connection_info'],
                'scoring' => $ch['scoring'],
                'value' => Scoring::currentValue($ch, $solveCount),
                'min_points' => (int) $ch['min_points'],
                'solve_count' => count($solvers),
                'first_blood' => [
                    Scoring::bonus($ch, 1, (int) $ch['points']),
                    Scoring::bonus($ch, 2, (int) $ch['points']),
                    Scoring::bonus($ch, 3, (int) $ch['points']),
                ],
                'files' => array_map(fn($f) => [
                    'url' => '/files/' . $f['id'],
                    'name' => $f['original_name'],
                    'size' => human_bytes((int) $f['size']),
                    'sha256' => $f['sha256'],
                ], $files),
                'hints' => array_map(fn($h) => [
                    'id' => (int) $h['id'],
                    'cost' => (int) $h['cost'],
                    'unlocked' => (bool) $h['unlocked'],
                    'html' => $h['unlocked'] ? md($h['content']) : null,
                ], $hints),
                'solvers' => array_map(fn($s) => [
                    'team' => $s['name'],
                    'url' => '/teams/' . $s['id'],
                    'at' => gmdate('c', strtotime($s['created_at'] . ' UTC')),
                    'rank' => (int) $s['solve_rank'],
                ], $solvers),
                'solved' => $solvedByUs ? [
                    'by' => $solvedByUs['username'],
                    'at' => gmdate('c', strtotime($solvedByUs['created_at'] . ' UTC')),
                    'points' => (int) $solvedByUs['points_awarded'],
                    'bonus' => (int) $solvedByUs['bonus_points'],
                    'rank' => (int) $solvedByUs['solve_rank'],
                ] : null,
                'attempts_left' => $ch['max_attempts'] ? max(0, (int) $ch['max_attempts'] - $wrong) : null,
                'can_submit' => Challenges::submissionsOpen(),
                'state' => event_state(),
            ],
        ]);
    }

    public function submit(Request $r, string $id): void
    {
        $user = current_user();
        $team = current_team();
        if (!Challenges::submissionsOpen()) {
            json_out(['ok' => false, 'message' => match (event_state()) {
                'upcoming' => 'The event has not started yet.',
                'paused' => 'Submissions are paused by the organisers.',
                default => 'The event has ended. Submissions are closed.',
            }], 403);
            return;
        }
        if (!RateLimit::hit("submit:{$team['id']}:{$id}", 10, 60) || !RateLimit::hit("submit-user:{$user['id']}", 30, 60)) {
            json_out(['ok' => false, 'message' => 'Too many attempts. Wait a minute before trying again.'], 429);
            return;
        }
        $ch = Challenges::findForPlayer((int) $id);
        if (!$ch) {
            abort(404, 'Challenge not found.');
        }
        if ($ch['requires_id'] && !DB::val('SELECT 1 FROM solves WHERE team_id = ? AND challenge_id = ?', [$team['id'], $ch['requires_id']])) {
            abort(403, 'This challenge is locked.');
        }
        $flag = trim((string) $r->raw('flag'));
        if ($flag === '' || mb_strlen($flag) > 500) {
            json_out(['ok' => false, 'message' => 'Enter a flag.'], 422);
            return;
        }

        $res = Scoring::submit($user, $team, (int) $ch['id'], $flag, $r->ip(), $r->userAgent());
        if (!empty($res['correct']) && empty($res['already'])) {
            audit('flag.solve', 'challenge:' . $ch['id'], ['team' => $team['id'], 'rank' => $res['rank'], 'bonus' => $res['bonus']]);
        }
        json_out(['ok' => true] + $res + ['score' => Scoring::teamScore((int) $team['id'])]);
    }

    public function unlockHint(Request $r, string $id): void
    {
        $team = current_team();
        $hint = DB::one(
            'SELECT h.*, c.id AS cid FROM hints h JOIN challenges c ON c.id = h.challenge_id WHERE h.id = ? AND ' . Challenges::PLAYER_VISIBLE,
            [(int) $id]
        );
        if (!$hint) {
            abort(404, 'Hint not found.');
        }
        if (!Challenges::boardOpen()) {
            abort(403, 'Hints open when the event starts.');
        }
        DB::query(
            'INSERT IGNORE INTO hint_unlocks (hint_id, team_id, user_id) VALUES (?, ?, ?)',
            [$hint['id'], $team['id'], current_user()['id']]
        );
        json_out(['ok' => true, 'html' => md($hint['content']), 'cost' => (int) $hint['cost'], 'score' => Scoring::teamScore((int) $team['id'])]);
    }

    public function download(Request $r, string $id): void
    {
        $file = DB::one(
            'SELECT f.*, c.state, c.release_at FROM challenge_files f JOIN challenges c ON c.id = f.challenge_id WHERE f.id = ?',
            [(int) $id]
        );
        $visible = $file && $file['state'] === 'visible' && (!$file['release_at'] || strtotime($file['release_at'] . ' UTC') <= time());
        if (!$file || (!is_admin() && (!$visible || !Challenges::boardOpen() || !current_team()))) {
            abort(404);
        }
        $path = APP_ROOT . '/storage/uploads/' . basename($file['stored_name']);
        if (!is_file($path)) {
            abort(404, 'The file is missing on the server. Tell the organisers.');
        }
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"; filename*=UTF-8\'\'' . rawurlencode($file['original_name']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0');
        readfile($path);
    }
}
