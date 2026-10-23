<?php
/** @var App\Core\Router $router */

use App\Controllers\Admin;
use App\Controllers\AuthController;
use App\Controllers\ChallengeController;
use App\Controllers\OAuthController;
use App\Controllers\PlayerController;
use App\Controllers\PublicController;
use App\Controllers\ScoreboardController;
use App\Controllers\TeamController;

// ---------------- Public ----------------
$router->get('/', [PublicController::class, 'home']);
$router->get('/rules', [PublicController::class, 'rules']);
$router->get('/about', [PublicController::class, 'about']);
$router->get('/scoreboard', [ScoreboardController::class, 'index']);
$router->get('/api/scoreboard', [ScoreboardController::class, 'json']);
$router->get('/teams/{id}', [TeamController::class, 'show']);

// ---------------- Auth ----------------
$router->get('/register', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/register', [AuthController::class, 'register'], ['guest']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/login/code', [AuthController::class, 'sendLoginCode'], ['guest']);
$router->get('/verify', [AuthController::class, 'showVerify']);
$router->post('/verify', [AuthController::class, 'verify']);
$router->post('/verify/resend', [AuthController::class, 'resend']);
$router->get('/forgot', [AuthController::class, 'showForgot'], ['guest']);
$router->post('/forgot', [AuthController::class, 'forgot'], ['guest']);
$router->get('/reset', [AuthController::class, 'showReset']);
$router->post('/reset', [AuthController::class, 'reset']);
$router->get('/invite/{token}', [AuthController::class, 'showInvite']);
$router->post('/invite/{token}', [AuthController::class, 'acceptInvite']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/auth/{provider}', [OAuthController::class, 'redirect']);
$router->get('/auth/{provider}/callback', [OAuthController::class, 'callback']);
$router->get('/onboarding', [OAuthController::class, 'showOnboarding']);
$router->post('/onboarding', [OAuthController::class, 'completeOnboarding']);

// ---------------- Player ----------------
$router->get('/dashboard', [PlayerController::class, 'dashboard'], ['auth']);
$router->get('/profile', [PlayerController::class, 'profile'], ['auth']);
$router->post('/profile', [PlayerController::class, 'updateProfile'], ['auth']);
$router->post('/profile/password', [PlayerController::class, 'updatePassword'], ['auth']);
$router->post('/profile/unlink/{provider}', [PlayerController::class, 'unlink'], ['auth']);
$router->get('/notifications', [PlayerController::class, 'notifications'], ['auth']);
$router->get('/api/poll', [PlayerController::class, 'poll'], ['auth']);

$router->get('/team', [TeamController::class, 'mine'], ['auth']);
$router->post('/team/create', [TeamController::class, 'create'], ['auth']);
$router->post('/team/join', [TeamController::class, 'join'], ['auth']);
$router->post('/team/leave', [TeamController::class, 'leave'], ['auth', 'team']);
$router->post('/team/update', [TeamController::class, 'update'], ['auth', 'team']);
$router->post('/team/regenerate', [TeamController::class, 'regenerate'], ['auth', 'team']);
$router->post('/team/kick/{id}', [TeamController::class, 'kick'], ['auth', 'team']);
$router->post('/team/captain/{id}', [TeamController::class, 'transfer'], ['auth', 'team']);

$router->get('/challenges', [ChallengeController::class, 'index'], ['auth', 'team']);
$router->get('/api/challenges/{id}', [ChallengeController::class, 'show'], ['auth', 'team']);
$router->post('/api/challenges/{id}/submit', [ChallengeController::class, 'submit'], ['auth', 'team']);
$router->post('/api/hints/{id}/unlock', [ChallengeController::class, 'unlockHint'], ['auth', 'team']);
$router->get('/files/{id}', [ChallengeController::class, 'download'], ['auth']);

// ---------------- Admin ----------------
$a = ['auth', 'admin'];
$router->get('/admin', [Admin\OverviewController::class, 'index'], $a);
$router->get('/admin/api/analytics', [Admin\OverviewController::class, 'analytics'], $a);
$router->get('/admin/security', [Admin\OverviewController::class, 'security'], $a);
$router->get('/admin/audit', [Admin\OverviewController::class, 'audit'], $a);
$router->get('/admin/export/{type}', [Admin\OverviewController::class, 'export'], $a);

$router->get('/admin/challenges', [Admin\ChallengeAdminController::class, 'index'], $a);
$router->get('/admin/challenges/new', [Admin\ChallengeAdminController::class, 'edit'], $a);
$router->get('/admin/challenges/export', [Admin\ChallengeAdminController::class, 'export'], $a);
$router->post('/admin/challenges/import', [Admin\ChallengeAdminController::class, 'import'], $a);
$router->post('/admin/challenges/save', [Admin\ChallengeAdminController::class, 'save'], $a);
$router->post('/admin/categories', [Admin\ChallengeAdminController::class, 'saveCategory'], $a);
$router->post('/admin/preview', [Admin\ChallengeAdminController::class, 'preview'], $a);
$router->get('/admin/challenges/{id}', [Admin\ChallengeAdminController::class, 'edit'], $a);
$router->get('/admin/challenges/{id}/stats', [Admin\ChallengeAdminController::class, 'stats'], $a);
$router->get('/admin/challenges/{id}/preview', [Admin\ChallengeAdminController::class, 'playerPreview'], $a);
$router->post('/admin/challenges/{id}/delete', [Admin\ChallengeAdminController::class, 'delete'], $a);
$router->post('/admin/challenges/{id}/duplicate', [Admin\ChallengeAdminController::class, 'duplicate'], $a);
$router->post('/admin/challenges/{id}/files', [Admin\ChallengeAdminController::class, 'upload'], $a);
$router->post('/admin/files/{id}/delete', [Admin\ChallengeAdminController::class, 'deleteFile'], $a);

$router->get('/admin/users', [Admin\PeopleController::class, 'users'], $a);
$router->get('/admin/users/{id}', [Admin\PeopleController::class, 'user'], $a);
$router->post('/admin/users/{id}', [Admin\PeopleController::class, 'updateUser'], $a);
$router->get('/admin/teams', [Admin\PeopleController::class, 'teams'], $a);
$router->get('/admin/teams/{id}', [Admin\PeopleController::class, 'team'], $a);
$router->post('/admin/teams/{id}', [Admin\PeopleController::class, 'updateTeam'], $a);
$router->get('/admin/invites', [Admin\PeopleController::class, 'invites'], $a);
$router->post('/admin/invites/import', [Admin\PeopleController::class, 'importInvites'], $a);
$router->post('/admin/invites/drain', [Admin\PeopleController::class, 'drainMail'], $a);
$router->post('/admin/invites/{id}/resend', [Admin\PeopleController::class, 'resendInvite'], $a);

$router->get('/admin/scoring', [Admin\ScoringController::class, 'index'], $a);
$router->post('/admin/awards', [Admin\ScoringController::class, 'award'], $a);
$router->post('/admin/awards/{id}/revoke', [Admin\ScoringController::class, 'revoke'], $a);
$router->get('/admin/submissions', [Admin\ScoringController::class, 'submissions'], $a);
$router->post('/admin/solves/manual', [Admin\ScoringController::class, 'manualSolve'], $a);
$router->post('/admin/solves/{id}/delete', [Admin\ScoringController::class, 'deleteSolve'], $a);

$router->get('/admin/announcements', [Admin\ContentController::class, 'announcements'], $a);
$router->post('/admin/announcements', [Admin\ContentController::class, 'saveAnnouncement'], $a);
$router->post('/admin/announcements/{id}/delete', [Admin\ContentController::class, 'deleteAnnouncement'], $a);
$router->get('/admin/settings', [Admin\ContentController::class, 'settings'], $a);
$router->post('/admin/settings', [Admin\ContentController::class, 'saveSettings'], $a);
$router->post('/admin/settings/test-mail', [Admin\ContentController::class, 'testMail'], $a);
$router->get('/admin/pages/{slug}', [Admin\ContentController::class, 'page'], $a);
$router->post('/admin/pages/{slug}', [Admin\ContentController::class, 'savePage'], $a);
