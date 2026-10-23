<?php
// Import a challenge export bundle (same format as Admin → Challenges → Import):
//   php app/bin/import-challenges.php bundle.json
// Everything lands hidden; review in the admin console, then make challenges visible.
require __DIR__ . '/../bootstrap.php';

use App\Services\Challenges;

$path = $argv[1] ?? null;
if (!$path || !is_file($path)) {
    fwrite(STDERR, "Usage: php app/bin/import-challenges.php <bundle.json>\n");
    exit(1);
}
$data = json_decode((string) file_get_contents($path), true);
if (!is_array($data) || ($data['format'] ?? '') !== 'nfsuctf-challenges' || !is_array($data['challenges'] ?? null)) {
    fwrite(STDERR, "$path is not a challenge export from this platform.\n");
    exit(1);
}
$made = Challenges::import($data);
audit('challenge.import', null, ['count' => $made, 'source' => 'cli']);
echo "Imported $made challenges as hidden.\n";
