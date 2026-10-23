<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Mail\Mailer;

final class ContentController
{
    public function announcements(Request $r): void
    {
        $edit = $r->query('edit') ? DB::one('SELECT * FROM announcements WHERE id = ?', [(int) $r->query('edit')]) : null;
        view('admin/announcements', [
            'title' => 'Announcements',
            'active' => 'announcements',
            'rows' => DB::all('SELECT a.*, u.username FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.is_pinned DESC, a.created_at DESC'),
            'edit' => $edit,
            'recipients' => (int) DB::val('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL AND is_banned = 0'),
        ], 'admin');
    }

    public function saveAnnouncement(Request $r): void
    {
        $title = mb_substr((string) $r->input('title'), 0, 160);
        $body = (string) ($_POST['body'] ?? '');
        if ($title === '' || trim($body) === '') {
            flash('error', 'An announcement needs a title and a message.');
            redirect('/admin/announcements');
        }
        $id = (int) $r->input('id');
        $data = ['title' => $title, 'body' => $body, 'is_pinned' => $r->input('is_pinned') ? 1 : 0];
        if ($id) {
            DB::update('announcements', $data, 'id = ?', [$id]);
        } else {
            $id = DB::insert('announcements', $data + ['created_by' => current_user()['id']]);
        }
        $emailed = 0;
        if ($r->input('email_all')) {
            foreach (DB::all('SELECT email FROM users WHERE email_verified_at IS NOT NULL AND is_banned = 0') as $u) {
                Mailer::queue($u['email'], $title, 'announcement', ['title' => $title, 'body_md' => $body]);
                $emailed++;
            }
        }
        audit('content.announcement', 'announcement:' . $id, ['title' => $title, 'emailed' => $emailed]);
        flash('success', 'Announcement published.' . ($emailed ? " Emails to $emailed players are queued." : ''));
        redirect('/admin/announcements');
    }

    public function deleteAnnouncement(Request $r, string $id): void
    {
        DB::query('DELETE FROM announcements WHERE id = ?', [(int) $id]);
        audit('content.announcement_delete', 'announcement:' . $id);
        flash('success', 'Announcement deleted.');
        redirect('/admin/announcements');
    }

    public function settings(Request $r): void
    {
        view('admin/settings', [
            'title' => 'Settings',
            'active' => 'settings',
            'mail' => [
                'driver' => env('MAIL_DRIVER', 'smtp'),
                'host' => env('MAIL_HOST'),
                'from' => env('MAIL_FROM'),
                'cap' => env('MAIL_HOURLY_CAP', 90),
            ],
            'oauth' => ['google' => (bool) env('GOOGLE_CLIENT_ID'), 'github' => (bool) env('GITHUB_CLIENT_ID')],
        ], 'admin');
    }

    public function saveSettings(Request $r): void
    {
        $section = (string) $r->input('section');
        $offset = $r->input('tz_offset');
        $errors = [];

        $text = fn(string $k, int $max = 255) => set_setting($k, mb_substr((string) $r->input($k), 0, $max));
        $bool = fn(string $k) => set_setting($k, $r->input($k) ? '1' : '0');

        switch ($section) {
            case 'event':
                $text('event_name', 80);
                $text('event_tagline', 160);
                $text('org_name', 160);
                $text('contact_email', 190);
                $text('flag_format', 60);
                foreach (['starts_at', 'ends_at', 'freeze_at'] as $k) {
                    set_setting($k, dt_from_form($r->input($k), $offset));
                }
                $s = parse_dt(setting('starts_at'));
                $e = parse_dt(setting('ends_at'));
                if ($s && $e && $e <= $s) {
                    $errors[] = 'The end time must be after the start time.';
                }
                break;
            case 'state':
                $bool('paused');
                $bool('maintenance');
                $bool('registration_open');
                $bool('allow_solo');
                set_setting('scoreboard_visibility', in_array($r->input('scoreboard_visibility'), ['public', 'private', 'hidden'], true) ? $r->input('scoreboard_visibility') : 'public');
                set_setting('team_size_max', (string) max(1, min(20, (int) $r->input('team_size_max'))));
                $text('signup_domain_allowlist', 500);
                break;
            case 'scoring':
                set_setting('first_blood_mode', in_array($r->input('first_blood_mode'), ['off', 'fixed', 'percent'], true) ? $r->input('first_blood_mode') : 'fixed');
                $vals = preg_replace('/\s+/', '', (string) $r->input('first_blood_values'));
                if (!preg_match('/^\d+(,\d+){0,4}$/', $vals)) {
                    $errors[] = 'First-blood values must be up to five numbers separated by commas, e.g. 50,30,10.';
                } else {
                    set_setting('first_blood_values', $vals);
                }
                break;
            case 'brand':
                $accent = (string) $r->input('brand_accent');
                set_setting('brand_accent', preg_match('/^#[0-9A-Fa-f]{6}$/', $accent) ? $accent : '');
                $logo = (string) $r->input('logo_url');
                set_setting('logo_url', ($logo === '' || preg_match('#^(https://|/)#', $logo)) ? $logo : '');
                if ($file = $r->file('logo_file')) {
                    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                    if ($file['error'] === UPLOAD_ERR_OK && in_array($ext, ['png', 'svg', 'webp', 'jpg', 'jpeg'], true) && $file['size'] < 1024 * 1024) {
                        $name = 'brand-logo-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
                        move_uploaded_file($file['tmp_name'], PUBLIC_ROOT . '/assets/img/' . $name);
                        set_setting('logo_url', '/assets/img/' . $name);
                    } else {
                        $errors[] = 'Logo must be a PNG, SVG, WebP or JPG under 1 MB.';
                    }
                }
                break;
        }
        foreach ($errors as $e) {
            flash('error', $e);
        }
        if (!$errors) {
            flash('success', 'Settings saved.');
        }
        audit('settings.' . $section, null, array_diff_key($r->all(), ['_csrf' => 1]));
        redirect('/admin/settings#' . $section);
    }

    public function testMail(Request $r): void
    {
        $to = current_user()['email'];
        $ok = Mailer::sendNow($to, 'Test email from ' . setting('event_name'), 'test');
        $err = $ok ? null : DB::val('SELECT last_error FROM mail_queue ORDER BY id DESC LIMIT 1');
        flash($ok ? 'success' : 'error', $ok ? "Test email sent to $to." : "Sending failed: $err");
        redirect('/admin/settings#mail');
    }

    public function page(Request $r, string $slug): void
    {
        $page = DB::one('SELECT * FROM pages WHERE slug = ?', [$slug]);
        if (!$page && !in_array($slug, ['rules', 'about', 'landing'], true)) {
            abort(404);
        }
        $page ??= ['slug' => $slug, 'title' => ucfirst($slug), 'body' => '', 'updated_at' => null];
        view('admin/page', ['title' => 'Pages', 'active' => 'pages', 'page' => $page], 'admin');
    }

    public function savePage(Request $r, string $slug): void
    {
        if (!in_array($slug, ['rules', 'about', 'landing'], true)) {
            abort(404);
        }
        DB::query(
            'INSERT INTO pages (slug, title, body) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body)',
            [$slug, mb_substr((string) $r->input('title'), 0, 120) ?: ucfirst($slug), (string) ($_POST['body'] ?? '')]
        );
        audit('content.page', 'page:' . $slug);
        flash('success', 'Page saved.');
        redirect('/admin/pages/' . $slug);
    }
}
