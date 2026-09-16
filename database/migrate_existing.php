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
    'counties', 'sports_disciplines', 'users', 'players', 'approval_workflow',
    'matches', 'match_goals', 'match_reports', 'match_report_cards',
    'match_squad_players', 'documents', 'notifications', 'activity_logs',
    'contact_messages', 'gallery_photos', 'videos',
];
$batchSize = 250;

$booleanColumns = ['is_read'];
foreach ($tables as $table) {
        $columns = $source->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
        if (!$columns) {
            fwrite(STDERR, "Skipping missing source table: {$table}\n");
            continue;
        }

        $columnNames = array_map(static fn(array $column): string => $column['Field'], $columns);
        $sourceColumns = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $columnNames));
        $targetColumns = implode(', ', array_map(static fn(string $column): string => '"' . $column . '"', $columnNames));
        $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
        $insert = $target->prepare("INSERT INTO \"{$table}\" ({$targetColumns}) VALUES ({$placeholders}) ON CONFLICT DO NOTHING");
        $offset = 0;
        $copied = 0;

        do {
            $rows = $source->query("SELECT {$sourceColumns} FROM `{$table}` LIMIT {$batchSize} OFFSET {$offset}")->fetchAll();
            foreach ($rows as $row) {
                $values = array_values($row);
                foreach ($booleanColumns as $booleanColumn) {
                    $columnIndex = array_search($booleanColumn, $columnNames, true);
                    if ($columnIndex !== false && $values[$columnIndex] !== null) {
                        $values[$columnIndex] = (bool)$values[$columnIndex];
                    }
                }
                $insert->execute($values);
                $copied++;
            }
            $offset += $batchSize;
        } while (count($rows) === $batchSize);

        $target->query("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), GREATEST(COALESCE(MAX(id), 0) + 1, 1), false) FROM \"{$table}\"");

        echo "{$table}: {$copied} rows copied\n";
}

echo "Existing database migration complete.\n";
