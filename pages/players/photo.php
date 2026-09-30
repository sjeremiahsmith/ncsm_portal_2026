<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$photoPath = $_GET['path'] ?? '';
$filePath = getPlayerPhotoFilePath($photoPath);

if (!$filePath) {
    $filePath = __DIR__ . '/../../assets/images/default-avatar.svg';
}

$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$contentType = 'image/jpeg';
if ($extension === 'png') {
    $contentType = 'image/png';
} elseif ($extension === 'gif') {
    $contentType = 'image/gif';
} elseif ($extension === 'webp') {
    $contentType = 'image/webp';
} elseif ($extension === 'svg') {
    $contentType = 'image/svg+xml';
}

header('Content-Type: ' . $contentType);
header('Cache-Control: private, max-age=300');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;