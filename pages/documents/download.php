<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$document = getDb()->fetchOne('SELECT * FROM documents WHERE id = ?', [$id]);
if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$fileName = basename((string)$document['file_path']);
$filePath = DOCUMENT_PATH . $fileName;
$realDocumentPath = realpath(DOCUMENT_PATH);
$realFilePath = realpath($filePath);
if (!$realDocumentPath || !$realFilePath || strpos($realFilePath, $realDocumentPath . DIRECTORY_SEPARATOR) !== 0 || !is_file($realFilePath)) {
    http_response_code(404);
    exit('Document not found.');
}

$downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$document['file_name']);
$mime = (string)$document['file_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realFilePath));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
readfile($realFilePath);
exit;