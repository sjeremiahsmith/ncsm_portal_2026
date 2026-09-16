<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/../includes/config.php';

$source = $argv[1] ?? '';
if ($source === '' || !is_dir($source)) {
    fwrite(STDERR, "Usage: php scripts/migrate_uploads.php /path/to/old/uploads\n");
    exit(1);
}

$source = realpath($source);
$target = realpath(UPLOAD_PATH) ?: UPLOAD_PATH;
if (!is_dir($target) && !mkdir($target, 0755, true)) {
    throw new RuntimeException("Unable to create target upload directory: {$target}");
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
$copied = 0;
foreach ($iterator as $item) {
    $relative = substr($item->getPathname(), strlen($source) + 1);
    $destination = $target . DIRECTORY_SEPARATOR . $relative;
    if ($item->isDir()) {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        continue;
    }

    $destinationDir = dirname($destination);
    if (!is_dir($destinationDir)) {
        mkdir($destinationDir, 0755, true);
    }
    if (!copy($item->getPathname(), $destination)) {
        throw new RuntimeException("Unable to copy {$item->getPathname()}");
    }
    $copied++;
}

echo "Copied {$copied} upload files to {$target}.\n";
