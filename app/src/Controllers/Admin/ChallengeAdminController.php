<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Services\Challenges;
use App\Services\Scoring;

final class ChallengeAdminController
{
    public function index(Request $r): void
    {
        $rows = DB::all(
            'SELECT c.*, cat.name AS category,
                    (SELECT COUNT(*) FROM solves s WHERE s.challenge_id = c.id) AS solves,
                    (SELECT COUNT(*) FROM submissions s WHERE s.challenge_id = c.id) AS attempts,
                    (SELECT COUNT(*) FROM flags f WHERE f.challenge_id = c.id) AS flag_count,
                    (SELECT COUNT(*) FROM challenge_files f WHERE f.challenge_id = c.id) AS file_count,
                    (SELECT COUNT(*) FROM hints h WHERE h.challenge_id = c.id) AS hint_count
             FROM challenges c JOIN categories cat ON cat.id = c.category_id
             ORDER BY cat.sort_order, cat.name, c.sort_order, c.points, c.id'
        );
        foreach ($rows as &$c) {
            $c['value'] = Scoring::currentValue($c, (int) $c['solves']);
            $c['code'] = Challenges::code($c);
        }
        view('admin/challenges', [
            'title' => 'Challenges',
            'active' => 'challenges',
            'rows' => $rows,
            'categories' => DB::all('SELECT cat.*, (SELECT COUNT(*) FROM challenges c WHERE c.category_id = cat.id) AS n FROM categories cat ORDER BY sort_order, name'),
        ], 'admin');
    }

    public function edit(Request $r, ?string $id = null): void
    {
        $ch = $id ? DB::one('SELECT * FROM challenges WHERE id = ?', [(int) $id]) : null;
        if ($id && !$ch) {
            abort(404);
        }
        view('admin/challenge_edit', [
            'title' => $ch ? 'Edit ' . $ch['title'] : 'New challenge',
            'active' => 'challenges',
            'ch' => $ch,
            'flags' => $ch ? DB::all('SELECT * FROM flags WHERE challenge_id = ? ORDER BY id', [$ch['id']]) : [],
            'hints' => $ch ? DB::all('SELECT * FROM hints WHERE challenge_id = ? ORDER BY sort_order, id', [$ch['id']]) : [],
            'files' => $ch ? DB::all('SELECT * FROM challenge_files WHERE challenge_id = ? ORDER BY id', [$ch['id']]) : [],
            'categories' => DB::all('SELECT * FROM categories ORDER BY sort_order, name'),
            'others' => DB::all('SELECT id, title FROM challenges WHERE id <> ? ORDER BY title', [$ch['id'] ?? 0]),
            'solves' => $ch ? (int) DB::val('SELECT COUNT(*) FROM solves WHERE challenge_id = ?', [$ch['id']]) : 0,
        ], 'admin');
    }

    public function save(Request $r): void
    {
        $id = (int) $r->input('id');
        $title = (string) $r->input('title');
        $errors = [];
        if ($title === '' || mb_strlen($title) > 120) {
            $errors[] = 'Give the challenge a title (up to 120 characters).';
        }
        $catId = (int) $r->input('category_id');
        if (!DB::val('SELECT id FROM categories WHERE id = ?', [$catId])) {
            $errors[] = 'Pick a category.';
        }
        $scoring = $r->input('scoring') === 'dynamic' ? 'dynamic' : 'static';
        $points = max(0, (int) $r->input('points'));
        $min = max(0, (int) $r->input('min_points'));
        if ($scoring === 'dynamic' && $min > $points) {
            $errors[] = 'Minimum points cannot be higher than the starting value.';
        }

        // Flags arrive as parallel arrays from repeatable rows.
        $flagRows = [];
        $types = (array) $r->input('flag_type', []);
        $contents = (array) ($_POST['flag_content'] ?? []);
        // Checkbox values carry their row index (unchecked boxes are not submitted).
        $ci = array_map('intval', (array) $r->input('flag_ci', []));
        foreach ($contents as $i => $content) {
            $content = trim((string) $content);
            if ($content === '') {
                continue;
            }
            $type = ($types[$i] ?? 'static') === 'regex' ? 'regex' : 'static';
            if ($type === 'regex' && @preg_match('~^(?:' . str_replace('~', '\~', $content) . ')$~u', '') === false) {
                $errors[] = 'This regex flag is not valid: ' . $content;
            }
            $flagRows[] = ['type' => $type, 'content' => mb_substr($content, 0, 512), 'case_insensitive' => in_array((int) $i, $ci, true) ? 1 : 0];
        }
        $state = in_array($r->input('state'), ['draft', 'hidden', 'visible'], true) ? $r->input('state') : 'draft';
        if (!$flagRows && $state === 'visible') {
            $errors[] = 'Add at least one flag before making the challenge visible.';
        }

        $hintRows = [];
        $hc = (array) ($_POST['hint_content'] ?? []);
        $hcost = (array) $r->input('hint_cost', []);
        foreach ($hc as $i => $content) {
            $content = trim((string) $content);
            if ($content !== '') {
                $hintRows[] = ['content' => $content, 'cost' => max(0, (int) ($hcost[$i] ?? 0)), 'sort_order' => count($hintRows)];
            }
        }

        $fb = trim((string) $r->input('first_blood_bonus'));
        if ($fb !== '' && $fb !== 'off' && !preg_match('/^\d+(\s*,\s*\d+){0,4}$/', $fb)) {
            $errors[] = 'First-blood bonus must be "off" or numbers separated by commas, e.g. 50,30,10.';
        }

        if ($errors) {
            foreach ($errors as $e) {
                flash('error', $e);
            }
            with_old($r->all());
            redirect($id ? "/admin/challenges/$id" : '/admin/challenges/new');
        }

        $release = dt_from_form($r->input('release_at'), $r->input('tz_offset'));
        $requires = (int) $r->input('requires_id') ?: null;
        if ($requires === $id) {
            $requires = null;
        }

        $data = [
            'title' => $title,
            'description' => (string) ($_POST['description'] ?? ''),
            'category_id' => $catId,
            'difficulty' => in_array($r->input('difficulty'), ['easy', 'medium', 'hard', 'insane'], true) ? $r->input('difficulty') : 'easy',
            'author' => mb_substr((string) $r->input('author'), 0, 64) ?: null,
            'state' => $state,
            'release_at' => $release,
            'scoring' => $scoring,
            'points' => $points,
            'min_points' => $scoring === 'dynamic' ? $min : $points,
            'decay' => max(1, (int) $r->input('decay') ?: 20),
            'max_attempts' => (int) $r->input('max_attempts') ?: null,
            'connection_info' => mb_substr((string) $r->input('connection_info'), 0, 255) ?: null,
            'tags' => mb_substr(implode(', ', array_filter(array_map('trim', explode(',', (string) $r->input('tags'))))), 0, 255) ?: null,
            'requires_id' => $requires,
            'first_blood_bonus' => $fb === '' ? null : $fb,
            'sort_order' => (int) $r->input('sort_order'),
        ];

        DB::tx(function () use (&$id, $data, $flagRows, $hintRows) {
            if ($id) {
                DB::update('challenges', $data, 'id = ?', [$id]);
            } else {
                $id = DB::insert('challenges', $data);
            }
            DB::query('DELETE FROM flags WHERE challenge_id = ?', [$id]);
            foreach ($flagRows as $f) {
                DB::insert('flags', $f + ['challenge_id' => $id]);
            }
            // Keep hint ids (and their unlocks) stable when content is unchanged.
            $existing = DB::all('SELECT * FROM hints WHERE challenge_id = ? ORDER BY sort_order, id', [$id]);
            foreach ($hintRows as $i => $h) {
                if (isset($existing[$i])) {
                    DB::update('hints', $h, 'id = ?', [$existing[$i]['id']]);
                } else {
                    DB::insert('hints', $h + ['challenge_id' => $id]);
                }
            }
            foreach (array_slice($existing, count($hintRows)) as $gone) {
                DB::query('DELETE FROM hints WHERE id = ?', [$gone['id']]);
            }
        });
        Scoring::recompute($id);
        audit('challenge.save', 'challenge:' . $id, ['title' => $title, 'state' => $state]);
        flash('success', 'Challenge saved.');
        redirect("/admin/challenges/$id");
    }

    public function delete(Request $r, string $id): void
    {
        $ch = DB::one('SELECT * FROM challenges WHERE id = ?', [(int) $id]);
        if (!$ch) {
            abort(404);
        }
        foreach (DB::all('SELECT stored_name FROM challenge_files WHERE challenge_id = ?', [$ch['id']]) as $f) {
            @unlink(APP_ROOT . '/storage/uploads/' . basename($f['stored_name']));
        }
        DB::query('DELETE FROM challenges WHERE id = ?', [$ch['id']]);
        audit('challenge.delete', 'challenge:' . $ch['id'], ['title' => $ch['title']]);
        flash('success', "Deleted \"{$ch['title']}\" and its solves.");
        redirect('/admin/challenges');
    }

    public function duplicate(Request $r, string $id): void
    {
        $ch = DB::one('SELECT * FROM challenges WHERE id = ?', [(int) $id]);
        if (!$ch) {
            abort(404);
        }
        $copy = $ch;
        unset($copy['id'], $copy['created_at'], $copy['updated_at']);
        $copy['title'] = mb_substr($ch['title'] . ' (copy)', 0, 120);
        $copy['state'] = 'draft';
        $newId = DB::insert('challenges', $copy);
        DB::query('INSERT INTO flags (challenge_id, type, content, case_insensitive) SELECT ?, type, content, case_insensitive FROM flags WHERE challenge_id = ?', [$newId, $ch['id']]);
        DB::query('INSERT INTO hints (challenge_id, content, cost, sort_order) SELECT ?, content, cost, sort_order FROM hints WHERE challenge_id = ?', [$newId, $ch['id']]);
        audit('challenge.duplicate', 'challenge:' . $newId, ['from' => $ch['id']]);
        flash('success', 'Duplicated as a draft. Files are not copied.');
        redirect("/admin/challenges/$newId");
    }

    public function upload(Request $r, string $id): void
    {
        $ch = DB::one('SELECT id FROM challenges WHERE id = ?', [(int) $id]);
        if (!$ch) {
            abort(404);
        }
        $max = (int) env('UPLOAD_MAX_MB', 50) * 1024 * 1024;
        $files = $_FILES['files'] ?? null;
        $count = 0;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                        flash('error', "Upload of $name failed (the server's upload limit may be too low).");
                    }
                    continue;
                }
                if ($files['size'][$i] > $max) {
                    flash('error', "$name is larger than " . env('UPLOAD_MAX_MB', 50) . ' MB.');
                    continue;
                }
                $stored = bin2hex(random_bytes(16)) . '.bin';
                $dest = APP_ROOT . '/storage/uploads/' . $stored;
                if (!move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    flash('error', "Could not store $name.");
                    continue;
                }
                DB::insert('challenge_files', [
                    'challenge_id' => $ch['id'],
                    'original_name' => mb_substr(basename((string) $name), 0, 255),
                    'stored_name' => $stored,
                    'size' => (int) $files['size'][$i],
                    'sha256' => hash_file('sha256', $dest),
                ]);
                $count++;
            }
        }
        if ($count) {
            audit('challenge.upload', 'challenge:' . $ch['id'], ['files' => $count]);
            flash('success', $count === 1 ? 'File uploaded.' : "$count files uploaded.");
        } elseif (empty(array_filter($_SESSION['_flash'] ?? [], fn($f) => $f['type'] === 'error'))) {
            flash('error', 'Choose at least one file to upload.');
        }
        redirect("/admin/challenges/{$ch['id']}#files");
    }

    public function deleteFile(Request $r, string $id): void
    {
        $f = DB::one('SELECT * FROM challenge_files WHERE id = ?', [(int) $id]);
        if (!$f) {
            abort(404);
        }
        @unlink(APP_ROOT . '/storage/uploads/' . basename($f['stored_name']));
        DB::query('DELETE FROM challenge_files WHERE id = ?', [$f['id']]);
        audit('challenge.file_delete', 'challenge:' . $f['challenge_id'], ['file' => $f['original_name']]);
        flash('success', 'File removed.');
        redirect("/admin/challenges/{$f['challenge_id']}#files");
    }

    public function preview(Request $r): void
    {
        json_out(['ok' => true, 'html' => md((string) $r->raw('text', ''))]);
    }

    public function saveCategory(Request $r): void
    {
        $id = (int) $r->input('id');
        $name = mb_substr((string) $r->input('name'), 0, 48);
        if ($r->input('delete') && $id) {
            if (DB::val('SELECT 1 FROM challenges WHERE category_id = ? LIMIT 1', [$id])) {
                flash('error', 'Move or delete the challenges in this category first.');
            } else {
                DB::query('DELETE FROM categories WHERE id = ?', [$id]);
                flash('success', 'Category deleted.');
            }
            redirect('/admin/challenges#categories');
        }
        if ($name === '') {
            flash('error', 'Category name is required.');
            redirect('/admin/challenges#categories');
        }
        try {
            if ($id) {
                DB::update('categories', ['name' => $name, 'sort_order' => (int) $r->input('sort_order')], 'id = ?', [$id]);
            } else {
                DB::insert('categories', ['name' => $name, 'sort_order' => (int) $r->input('sort_order') ?: 99]);
            }
            flash('success', 'Category saved.');
        } catch (\PDOException) {
            flash('error', 'A category with that name already exists.');
        }
        redirect('/admin/challenges#categories');
    }

    public function stats(Request $r, string $id): void
    {
        $ch = DB::one('SELECT c.*, cat.name AS category FROM challenges c JOIN categories cat ON cat.id = c.category_id WHERE c.id = ?', [(int) $id]);
        if (!$ch) {
            abort(404);
        }
        $cid = (int) $ch['id'];
        view('admin/challenge_stats', [
            'title' => $ch['title'] . ' stats',
            'active' => 'challenges',
            'ch' => $ch,
            'solves' => DB::all(
                'SELECT s.*, t.name AS team, u.username,
                        (SELECT COUNT(*) FROM submissions x WHERE x.team_id = s.team_id AND x.challenge_id = s.challenge_id AND x.correct = 0) AS wrong
                 FROM solves s JOIN teams t ON t.id = s.team_id LEFT JOIN users u ON u.id = s.user_id
                 WHERE s.challenge_id = ? ORDER BY s.created_at',
                [$cid]
            ),
            'wrongFlags' => DB::all(
                'SELECT provided, COUNT(*) AS n, COUNT(DISTINCT team_id) AS teams FROM submissions
                 WHERE challenge_id = ? AND correct = 0 GROUP BY provided ORDER BY n DESC LIMIT 15',
                [$cid]
            ),
            'attempts' => (int) DB::val('SELECT COUNT(*) FROM submissions WHERE challenge_id = ?', [$cid]),
            'teamsTried' => (int) DB::val('SELECT COUNT(DISTINCT team_id) FROM submissions WHERE challenge_id = ?', [$cid]),
            'hintUnlocks' => DB::all(
                'SELECT h.id, h.cost, COUNT(hu.id) AS n FROM hints h LEFT JOIN hint_unlocks hu ON hu.hint_id = h.id
                 WHERE h.challenge_id = ? GROUP BY h.id, h.cost ORDER BY h.sort_order',
                [$cid]
            ),
            'value' => Scoring::currentValue($ch, (int) DB::val('SELECT COUNT(*) FROM solves WHERE challenge_id = ?', [$cid])),
        ], 'admin');
    }

    public function playerPreview(Request $r, string $id): void
    {
        $ch = DB::one('SELECT c.*, cat.name AS category FROM challenges c JOIN categories cat ON cat.id = c.category_id WHERE c.id = ?', [(int) $id]);
        if (!$ch) {
            abort(404);
        }
        view('admin/challenge_preview', [
            'title' => 'Preview: ' . $ch['title'],
            'ch' => $ch,
            'files' => DB::all('SELECT * FROM challenge_files WHERE challenge_id = ?', [$ch['id']]),
            'hints' => DB::all('SELECT * FROM hints WHERE challenge_id = ? ORDER BY sort_order, id', [$ch['id']]),
            'value' => Scoring::currentValue($ch, (int) DB::val('SELECT COUNT(*) FROM solves WHERE challenge_id = ?', [$ch['id']])),
        ], 'app');
    }

    // ---------------- Import / export ----------------

    public function export(Request $r): void
    {
        $withFiles = $r->query('files') === '1';
        $out = ['format' => 'nfsuctf-challenges', 'version' => 1, 'exported_at' => gmdate('c'), 'challenges' => []];
        $titles = array_column(DB::all('SELECT id, title FROM challenges'), 'title', 'id');
        foreach (DB::all('SELECT c.*, cat.name AS category FROM challenges c JOIN categories cat ON cat.id = c.category_id ORDER BY c.id') as $c) {
            $item = [
                'title' => $c['title'],
                'category' => $c['category'],
                'description' => $c['description'],
                'difficulty' => $c['difficulty'],
                'author' => $c['author'],
                'state' => $c['state'],
                'release_at' => $c['release_at'],
                'scoring' => $c['scoring'],
                'points' => (int) $c['points'],
                'min_points' => (int) $c['min_points'],
                'decay' => (int) $c['decay'],
                'max_attempts' => $c['max_attempts'] ? (int) $c['max_attempts'] : null,
                'connection_info' => $c['connection_info'],
                'tags' => $c['tags'],
                'requires' => $c['requires_id'] ? ($titles[$c['requires_id']] ?? null) : null,
                'first_blood_bonus' => $c['first_blood_bonus'],
                'flags' => DB::all('SELECT type, content, case_insensitive FROM flags WHERE challenge_id = ?', [$c['id']]),
                'hints' => DB::all('SELECT content, cost FROM hints WHERE challenge_id = ? ORDER BY sort_order', [$c['id']]),
                'files' => [],
            ];
            foreach (DB::all('SELECT * FROM challenge_files WHERE challenge_id = ?', [$c['id']]) as $f) {
                $file = ['name' => $f['original_name'], 'sha256' => $f['sha256'], 'size' => (int) $f['size']];
                $path = APP_ROOT . '/storage/uploads/' . basename($f['stored_name']);
                if ($withFiles && is_file($path)) {
                    $file['base64'] = base64_encode((string) file_get_contents($path));
                }
                $item['files'][] = $file;
            }
            $out['challenges'][] = $item;
        }
        audit('challenge.export', null, ['count' => count($out['challenges']), 'files' => $withFiles]);
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="challenges-' . gmdate('Ymd-His') . '.json"');
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function import(Request $r): void
    {
        $file = $r->file('bundle');
        $data = $file && $file['error'] === UPLOAD_ERR_OK ? json_decode((string) file_get_contents($file['tmp_name']), true) : null;
        if (!is_array($data) || ($data['format'] ?? '') !== 'nfsuctf-challenges' || !is_array($data['challenges'] ?? null)) {
            flash('error', 'That file is not a challenge export from this platform.');
            redirect('/admin/challenges');
        }
        $made = Challenges::import($data);
        audit('challenge.import', null, ['count' => $made]);
        flash('success', "Imported $made challenges as hidden. Review them, then make them visible.");
        redirect('/admin/challenges');
    }
}
