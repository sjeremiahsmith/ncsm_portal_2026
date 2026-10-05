<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$configError = getSupabaseConfigError();
if ($configError !== '') {
    fwrite(STDERR, $configError . PHP_EOL);
    exit(1);
}

if (!hasCompleteSupabaseConfig()) {
    fwrite(STDERR, "Supabase Storage configuration is required.\n");
    exit(1);
}

$db = getDb();
$migrated = 0;

$players = $db->fetchAll("SELECT id, photo_path FROM players WHERE photo_path IS NOT NULL AND photo_path <> ''");
foreach ($players as $player) {
    $storedPath = trim((string)$player['photo_path']);
    if ($storedPath === '' || isRemoteUrl($storedPath)) {
        continue;
    }

    $filePath = getPlayerPhotoFilePath($storedPath);
    if ($filePath === '') {
        fwrite(STDERR, "Skipping missing player photo for player ID {$player['id']}: {$storedPath}\n");
        continue;
    }

    $upload = uploadLocalImageToSupabase($filePath, basename($storedPath), 'players', 'photo_');
    if (!$upload['success']) {
        fwrite(STDERR, "Failed migrating player photo for player ID {$player['id']}: {$upload['error']}\n");
        continue;
    }

    $db->update("UPDATE players SET photo_path = ? WHERE id = ?", [$upload['path'], $player['id']]);
    $migrated++;
}

$photos = $db->fetchAll("SELECT id, photo_path FROM gallery_photos WHERE photo_path IS NOT NULL AND photo_path <> ''");
foreach ($photos as $photo) {
    $storedPath = trim((string)$photo['photo_path']);
    if ($storedPath === '' || isRemoteUrl($storedPath)) {
        continue;
    }

    $filePath = '';
    $candidates = [];
    if (strpos($storedPath, 'uploads/') === 0) {
        $candidates[] = $storedPath;
    } else {
        $basename = basename($storedPath);
        $candidates[] = 'uploads/gallery/' . $basename;
        $candidates[] = $storedPath;
    }

    foreach ($candidates as $candidate) {
        $filePath = resolveUploadCandidateFilePath($candidate);
        if ($filePath !== '') {
            break;
        }
    }

    if ($filePath === '') {
        fwrite(STDERR, "Skipping missing gallery photo ID {$photo['id']}: {$storedPath}\n");
        continue;
    }

    $upload = uploadLocalImageToSupabase($filePath, basename($storedPath), 'gallery', 'gallery_');
    if (!$upload['success']) {
        fwrite(STDERR, "Failed migrating gallery photo ID {$photo['id']}: {$upload['error']}\n");
        continue;
    }

    $db->update("UPDATE gallery_photos SET photo_path = ? WHERE id = ?", [$upload['path'], $photo['id']]);
    $migrated++;
}

echo "Migrated {$migrated} images to Supabase Storage.\n";
