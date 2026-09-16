<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

function sendError($msg) {
    header('Content-Type: text/plain', true, 500);
    echo $msg;
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) sendError('No player specified.');

$db = getDb();
$player = $db->fetchOne("SELECT p.*, c.group_label FROM players p JOIN counties c ON p.county_id = c.id WHERE p.id = ?", [$id]);
if (!$player) sendError('Player not found.');

$isSuperAdmin = hasRole('super_admin');
$isOwnCounty = isAdminRole() && isset($_SESSION['user_group_label']) && $_SESSION['user_group_label'] === $player['group_label'];
$isSportsCoord = hasRole('sports_coord');

if (!($isSuperAdmin || $isOwnCounty || $isSportsCoord)) sendError('Access denied.');

$format = $_GET['format'] ?? 'png';
if (!in_array($format, ['png', 'jpg', 'jpeg'])) $format = 'png';

$side = $_GET['side'] ?? 'front';
if ($side === 'back') {
    $data = generatePlayerCardBackImage($id, $format);
    $label = 'back';
} else {
    $data = generatePlayerCardImage($id, $format);
    $label = 'front';
}

if (!$data) sendError('Failed to generate card. Check that GD library is enabled on the server.');

if ($format === 'jpg' || $format === 'jpeg') {
    header('Content-Type: image/jpeg');
    header('Content-Disposition: inline; filename="player_card_' . $id . '_' . $label . '.jpg"');
} else {
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="player_card_' . $id . '_' . $label . '.png"');
}
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo $data;
