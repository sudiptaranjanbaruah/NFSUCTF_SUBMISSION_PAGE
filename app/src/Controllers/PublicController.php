<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Services\Challenges;
use App\Services\Scoring;

final class PublicController
{
    public function home(Request $r): void
    {
        $cats = DB::all(
            'SELECT cat.name, COUNT(c.id) AS n FROM categories cat
             JOIN challenges c ON c.category_id = cat.id AND ' . Challenges::PLAYER_VISIBLE . '
             GROUP BY cat.id, cat.name, cat.sort_order ORDER BY cat.sort_order'
        );
        $stats = [
            'players' => (int) DB::val('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL AND role = "user"'),
            'teams' => (int) DB::val('SELECT COUNT(*) FROM teams WHERE is_hidden = 0 AND is_banned = 0'),
            'challenges' => (int) DB::val('SELECT COUNT(*) FROM challenges c WHERE ' . Challenges::PLAYER_VISIBLE),
        ];
        $top = setting('scoreboard_visibility') === 'public' ? array_slice(Scoring::scoreboard(freeze_cutoff()), 0, 5) : [];
        view('public/home', [
            'title' => '',
            'cats' => $cats,
            'stats' => $stats,
            'top' => $top,
            'state' => event_state(),
            'bare' => true,
            'landing' => DB::one('SELECT * FROM pages WHERE slug = ?', ['landing']),
        ]);
    }

    public function rules(Request $r): void
    {
        $this->page('rules');
    }

    public function about(Request $r): void
    {
        $this->page('about');
    }

    private function page(string $slug): void
    {
        $page = DB::one('SELECT * FROM pages WHERE slug = ?', [$slug]);
        if (!$page) {
            abort(404);
        }
        view('public/page', ['title' => $page['title'], 'page' => $page, 'active' => $slug]);
    }
}
