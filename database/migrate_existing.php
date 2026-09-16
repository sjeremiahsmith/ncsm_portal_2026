<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

function requiredEnv(string $name): string
{
    $value = getenv($name);
    if ($value === false || trim($value) === '') {
        fwrite(STDERR, "Missing required environment variable: {$name}\n");
        exit(1);
    }
    return trim($value);
}

$sourceHost = requiredEnv('NCSM_SOURCE_DB_HOST');
$sourcePort = getenv('NCSM_SOURCE_DB_PORT') ?: '3306';
$sourceName = requiredEnv('NCSM_SOURCE_DB_NAME');
$sourceUser = requiredEnv('NCSM_SOURCE_DB_USER');
$sourcePass = getenv('NCSM_SOURCE_DB_PASS') ?: '';

$source = new PDO(
    "mysql:host={$sourceHost};port={$sourcePort};dbname={$sourceName};charset=utf8mb4",
    $sourceUser,
    $sourcePass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
$target = getDb()->getConnection();
$tables = [
    'users', 'counties', 'sports_disciplines', 'players', 'approval_workflow',
    'matches', 'match_goals', 'match_reports', 'match_report_cards',
    'match_squad_players', 'documents', 'notifications', 'activity_logs',
    'contact_messages', 'gallery_photos', 'videos',
];
$batchSize = 250;

$target->exec('SET FOREIGN_KEY_CHECKS = 0');
try {
    foreach ($tables as $table) {
        $columns = $source->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
        if (!$columns) {
            fwrite(STDERR, "Skipping missing source table: {$table}\n");
            continue;
        }

        $columnNames = array_map(static fn(array $column): string => $column['Field'], $columns);
        $quotedColumns = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $columnNames));
        $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
        $insert = $target->prepare("INSERT IGNORE INTO `{$table}` ({$quotedColumns}) VALUES ({$placeholders})");
        $offset = 0;
        $copied = 0;

        do {
            $rows = $source->query("SELECT {$quotedColumns} FROM `{$table}` LIMIT {$batchSize} OFFSET {$offset}")->fetchAll();
            foreach ($rows as $row) {
                $insert->execute(array_values($row));
                $copied++;
            }
            $offset += $batchSize;
        } while (count($rows) === $batchSize);

        echo "{$table}: {$copied} rows copied\n";
    }
} finally {
    $target->exec('SET FOREIGN_KEY_CHECKS = 1');
}

echo "Existing database migration complete.\n";
