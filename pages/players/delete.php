<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin']);

$db = getDb();
$id = (int)($_POST['id'] ?? 0);
requireCsrfToken();

$player = $db->fetchOne("SELECT * FROM players WHERE id = ?", [$id]);
if (!$player) {
    setFlash('error', 'Player not found.');
    redirect(APP_URL . 'pages/dashboard.php');
}

if ($player['photo_path']) {
    if (!deleteManagedImage($player['photo_path'])) {
        error_log('Failed to remove player photo during delete for player ID ' . $player['id']);
    }
}

$db->delete("DELETE FROM players WHERE id = ?", [$id]);
logActivity('delete_player', 'Deleted player: ' . $player['full_name']);
setFlash('success', 'Player "' . $player['full_name'] . '" has been deleted successfully.');
redirect(APP_URL . 'pages/dashboard.php');
