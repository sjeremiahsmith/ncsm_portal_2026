<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$countyName = isset($_GET['county_name']) ? trim($_GET['county_name']) : '';
$sportId    = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;

if (empty($countyName) || !$sportId) {
    echo json_encode(['error' => 'county_name and sport_id required']);
    exit;
}

$db = getDb();
$county = $db->fetchOne("SELECT id FROM counties WHERE name = ?", [$countyName]);
if (!$county) {
    echo json_encode(['players' => []]);
    exit;
}

$countyId = $county['id'];
$players = $db->fetchAll(
    "SELECT id, full_name, primary_position
     FROM players
     WHERE county_id = ? AND sport_discipline_id = ? AND status = 'approved'
     ORDER BY full_name ASC",
    [$countyId, $sportId]
);

echo json_encode(['players' => $players, 'county_name' => $countyName]);
