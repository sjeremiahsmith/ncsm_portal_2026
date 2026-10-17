<?php
// Startup check + idempotent schema bootstrap. Run from docker-entrypoint.sh.
// Exits non-zero with a clear message if the database is not configured or reachable.
function bootFail($message) {
    fwrite(STDERR, "[ncsm-bootstrap] ERROR: $message\n");
    exit(1);
}
function bootLog($message) {
    fwrite(STDOUT, "[ncsm-bootstrap] $message\n");
}

$url = trim((string)(getenv('NCSM_DATABASE_URL') ?: getenv('DATABASE_URL') ?: ''));
if ($url === '') {
    bootFail('No database configured. Set DATABASE_URL (or NCSM_DATABASE_URL) to a PostgreSQL URL, e.g. ******HOST:5432/DBNAME');
}
$parts = parse_url($url);
if ($parts === false || empty($parts['host']) || empty($parts['path']) || trim($parts['path'], '/') === ''
    || !in_array(strtolower($parts['scheme'] ?? ''), ['postgres', 'postgresql'], true)) {
    bootFail('DATABASE_URL / NCSM_DATABASE_URL is malformed. Expected ******HOST:5432/DBNAME');
}

$query = [];
if (!empty($parts['query'])) {
    parse_str($parts['query'], $query);
}
$dsn = 'pgsql:host=' . $parts['host'] . ';port=' . ($parts['port'] ?? 5432) . ';dbname=' . rawurldecode(trim($parts['path'], '/'));
if (!empty($query['sslmode'])) {
    $dsn .= ';sslmode=' . $query['sslmode'];
}

$pdo = null;
$lastError = '';
$attempts = max(1, (int)(getenv('NCSM_DB_WAIT_ATTEMPTS') ?: 10));
for ($i = 1; $i <= $attempts; $i++) {
    try {
        $pdo = new PDO($dsn, isset($parts['user']) ? urldecode($parts['user']) : null, isset($parts['pass']) ? urldecode($parts['pass']) : null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (Throwable $e) {
        $lastError = $e->getMessage();
        bootLog("Database not reachable (attempt $i/$attempts): $lastError");
        if ($i < $attempts) {
            sleep(3);
        }
    }
}
if (!$pdo) {
    bootFail("Could not connect to database at {$parts['host']}: $lastError");
}

$required = ['users', 'players', 'matches', 'counties', 'sports_disciplines'];
$missingTables = function () use ($pdo, $required) {
    $in = implode(',', array_map([$pdo, 'quote'], $required));
    $found = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_name IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_diff($required, $found));
};

if (!$missingTables()) {
    bootLog('Database schema present; nothing to do.');
    exit(0);
}

$schemaFile = dirname(__DIR__) . '/database/schema.sql';
$sql = is_readable($schemaFile) ? file_get_contents($schemaFile) : false;
if ($sql === false) {
    bootFail("Schema file not readable: $schemaFile");
}

try {
    // Advisory lock prevents concurrent instances from bootstrapping at once.
    $pdo->query('SELECT pg_advisory_lock(7263001)');
    if ($missingTables()) {
        bootLog('Required tables missing; applying database/schema.sql');
        $pdo->exec($sql);
        bootLog('Schema applied.');
    }
    $pdo->query('SELECT pg_advisory_unlock(7263001)');
} catch (Throwable $e) {
    bootFail('Schema bootstrap failed: ' . $e->getMessage());
}
$still = $missingTables();
if ($still) {
    bootFail('Tables still missing after bootstrap: ' . implode(', ', $still));
}
