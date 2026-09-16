<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin']);

$db = getDb();
$id = (int)($_POST['id'] ?? 0);
requireCsrfToken();

$player = $db->fetchOne("SELECT * FROM players WHERE id = ?", [$id]);
if (!$player) {
    setFlash('error', 'Player not found.');
    redirect(APP_URL . 'pages/dashboard.php');
}

if ($player['photo_path']) {
    $photoFile = __DIR__ . '/../../' . $player['photo_path'];
    if (file_exists($photoFile)) {
        unlink($photoFile);
    }
}

$db->delete("DELETE FROM players WHERE id = ?", [$id]);
logActivity('delete_player', 'Deleted player: ' . $player['full_name']);
setFlash('success', 'Player "' . $player['full_name'] . '" has been deleted successfully.');
redirect(APP_URL . 'pages/dashboard.php');
