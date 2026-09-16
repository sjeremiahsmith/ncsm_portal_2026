<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['super_admin']);

header('Content-Type: application/json');

$db = getDb();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$matchId = (int)($_POST['match_id'] ?? $_GET['match_id'] ?? 0);

if (!$matchId || !in_array($action, ['start', 'pause', 'resume', 'stop'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid action or match_id']);
    exit;
}

$match = $db->fetchOne("SELECT * FROM matches WHERE id = ?", [$matchId]);
if (!$match) {
    echo json_encode(['success' => false, 'error' => 'Match not found']);
    exit;
}

$now = date('Y-m-d H:i:s');

switch ($action) {
    case 'start':
        $db->update(
            "UPDATE matches SET status='live', timer_kickoff=?, timer_offset=0, updated_at=NOW() WHERE id=?",
            [$now, $matchId]
        );
        logActivity('timer_start', "Started timer for match #$matchId");
        echo json_encode(['success' => true, 'kickoff' => $now, 'offset' => 0, 'status' => 'live']);
        break;

    case 'pause':
        if ($match['timer_kickoff']) {
            $elapsed = strtotime($now) - strtotime($match['timer_kickoff']) + (int)$match['timer_offset'];
        } else {
            $elapsed = (int)$match['timer_offset'];
        }
        $db->update(
            "UPDATE matches SET timer_offset=?, timer_kickoff=NULL, updated_at=NOW() WHERE id=?",
            [$elapsed, $matchId]
        );
        logActivity('timer_pause', "Paused timer for match #$matchId at " . gmdate('H:i:s', $elapsed));
        echo json_encode(['success' => true, 'offset' => $elapsed, 'status' => 'live']);
        break;

    case 'resume':
        $newKickoff = date('Y-m-d H:i:s', time() - (int)$match['timer_offset']);
        $db->update(
            "UPDATE matches SET timer_kickoff=?, updated_at=NOW() WHERE id=?",
            [$newKickoff, $matchId]
        );
        logActivity('timer_resume', "Resumed timer for match #$matchId");
        echo json_encode(['success' => true, 'kickoff' => $newKickoff, 'offset' => $match['timer_offset'], 'status' => 'live']);
        break;

    case 'stop':
        if ($match['timer_kickoff']) {
            $elapsed = strtotime($now) - strtotime($match['timer_kickoff']) + (int)$match['timer_offset'];
        } else {
            $elapsed = (int)$match['timer_offset'];
        }
        $db->update(
            "UPDATE matches SET status='completed', timer_offset=?, timer_kickoff=NULL, updated_at=NOW() WHERE id=?",
            [$elapsed, $matchId]
        );
        logActivity('timer_stop', "Stopped timer for match #$matchId");
        echo json_encode(['success' => true, 'offset' => $elapsed, 'status' => 'completed']);
        break;
}
