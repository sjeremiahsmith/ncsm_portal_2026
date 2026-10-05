<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
if (!canManageGames()) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to manage games']);
    exit;
}

header('Content-Type: application/json');

$db = getDb();
ensureMatchExtraTimeColumns();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$matchId = (int)($_POST['match_id'] ?? $_GET['match_id'] ?? 0);

if (!$matchId || !in_array($action, ['start', 'pause', 'resume', 'stop', 'enable_extra_time', 'disable_extra_time'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid action or match_id']);
    exit;
}

$match = $db->fetchOne("SELECT * FROM matches WHERE id = ?", [$matchId]);
if (!$match) {
    echo json_encode(['success' => false, 'error' => 'Match not found']);
    exit;
}

if (hasRole('group_admin') && $match['group_label'] !== getAssignedGroupLabel()) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to manage this match']);
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

    case 'enable_extra_time':
        if ($match['status'] !== 'live') {
            echo json_encode(['success' => false, 'error' => 'Extra time can only be enabled for a live game']);
            break;
        }
        $db->update(
            "UPDATE matches SET extra_time_enabled = TRUE, updated_at=NOW() WHERE id=?",
            [$matchId]
        );
        logActivity('enable_extra_time', "Enabled extra time for match #$matchId");
        echo json_encode(['success' => true, 'extra_time_enabled' => true, 'status' => 'live']);
        break;

    case 'disable_extra_time':
        if ($match['status'] !== 'live') {
            echo json_encode(['success' => false, 'error' => 'Extra time can only be changed for a live game']);
            break;
        }
        $db->update(
            "UPDATE matches SET extra_time_enabled = FALSE, updated_at=NOW() WHERE id=?",
            [$matchId]
        );
        logActivity('disable_extra_time', "Disabled extra time for match #$matchId");
        echo json_encode(['success' => true, 'extra_time_enabled' => false, 'status' => 'live']);
        break;
}
