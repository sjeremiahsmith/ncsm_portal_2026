<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCsrfToken();

$db = getDb();
$playerId = (int)($_POST['player_id'] ?? 0);

$player = $db->fetchOne("SELECT * FROM players WHERE id = ?", [$playerId]);
if (!$player) {
    setFlash('error', 'Player not found.');
    redirect(APP_URL . 'pages/dashboard.php');
}

if ($player['status'] !== 'draft') {
    setFlash('error', 'Only draft players can have their photo updated.');
    redirect(APP_URL . 'pages/players/view.php?id=' . $playerId);
}

if (!hasRole('super_admin') && $_SESSION['user_id'] !== $player['registered_by']) {
    setFlash('error', 'You do not have permission to update this player\'s photo.');
    redirect(APP_URL . 'pages/players/view.php?id=' . $playerId);
}

if (hasRole('association_admin') && $player['sport_discipline_id'] != $_SESSION['user_association_id']) {
    setFlash('error', 'You do not have access to this player.');
    redirect(APP_URL . 'pages/players/list.php');
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    setFlash('error', 'No photo was uploaded or an upload error occurred.');
    redirect(APP_URL . 'pages/players/view.php?id=' . $playerId);
}

$result = uploadPhoto($_FILES['photo']);
if (!$result['success']) {
    setFlash('error', $result['error']);
    redirect(APP_URL . 'pages/players/view.php?id=' . $playerId);
}

if ($player['photo_path']) {
    $oldFile = __DIR__ . '/../../' . $player['photo_path'];
    if (file_exists($oldFile)) {
        unlink($oldFile);
    }
}

$relativePath = 'uploads/photos/' . $result['filename'];
$db->update("UPDATE players SET photo_path = ? WHERE id = ?", [$relativePath, $playerId]);
logActivity('update_photo', 'Updated photo for player: ' . $player['full_name']);
setFlash('success', 'Player photo updated successfully.');
redirect(APP_URL . 'pages/players/view.php?id=' . $playerId);
