<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('No player specified.');

// Check permissions
$db = getDb();
$player = $db->fetchOne("SELECT p.*, c.group_label FROM players p JOIN counties c ON p.county_id = c.id WHERE p.id = ?", [$id]);
if (!$player) die('Player not found.');

$isSuperAdmin = hasRole('super_admin');
$isOwnCounty = isAdminRole() && isset($_SESSION['user_group_label']) && $_SESSION['user_group_label'] === $player['group_label'];
$isOwnSport = hasRole('association_admin') && isset($_SESSION['user_association_id']) && $_SESSION['user_association_id'] == $player['sport_discipline_id'];
$isCommissioner = hasRole('match_commissioner');

if (!($isSuperAdmin || $isOwnCounty || $isOwnSport || $isCommissioner)) {
    die('Access denied.');
}

generatePlayerCard($id, 'stream');
