<?php
// Create or promote a super admin: php app/bin/seed-admin.php email username [password]
require __DIR__ . '/../bootstrap.php';

use App\Auth\AuthService;
use App\Core\DB;

[$_, $email, $username, $password] = array_pad($argv, 4, null);
if (!$email || !$username) {
    fwrite(STDERR, "Usage: php app/bin/seed-admin.php <email> <username> [password]\n");
    exit(1);
}
$email = AuthService::normalizeEmail($email);
$password ??= bin2hex(random_bytes(8));

$existing = DB::one('SELECT id FROM users WHERE email = ?', [$email]);
if ($existing) {
    DB::query("UPDATE users SET role = 'super_admin', email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?", [$existing['id']]);
    echo "Promoted $email to super_admin.\n";
} else {
    DB::insert('users', [
        'email' => $email,
        'username' => $username,
        'password_hash' => AuthService::hash($password),
        'role' => 'super_admin',
        'email_verified_at' => utc_now(),
    ]);
    echo "Created super_admin $email\nTemporary password: $password\nSign-in also needs a code emailed to that address.\n";
}
