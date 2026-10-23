<?php
// Apply pending SQL migrations: php app/bin/migrate.php
require __DIR__ . '/../bootstrap.php';

use App\Core\DB;

DB::pdo()->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(190) PRIMARY KEY, ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$done = array_column(DB::all('SELECT name FROM migrations'), 'name');

$files = glob(APP_ROOT . '/migrations/*.sql');
sort($files);
$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        continue;
    }
    echo "Migrating $name ... ";
    $sql = file_get_contents($file);
    // Split on semicolons at line ends; our migrations keep one statement per block.
    foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
        $stmt = trim(preg_replace('/^--.*$/m', '', $stmt));
        if ($stmt !== '') {
            DB::pdo()->exec($stmt);
        }
    }
    DB::insert('migrations', ['name' => $name]);
    echo "done\n";
    $ran++;
}
echo $ran ? "$ran migration(s) applied.\n" : "Nothing to migrate.\n";
