<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['coach']);
ensureCoachLineupSchema();

$db = getDb();
$countyId = getAssignedCountyId();
if (!$countyId) {
    http_response_code(403);
    exit('This coach account has no assigned county.');
}

$error = '';
$submittedMatchId = 0;
$submittedByPlayer = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $matchId = (int)($_POST['match_id'] ?? 0);
    $submittedMatchId = $matchId;
    $match = $db->fetchOne(
        "SELECT * FROM matches WHERE id = ? AND status = 'scheduled' AND (home_county_id = ? OR away_county_id = ?)",
        [$matchId, $countyId, $countyId]
    );
    $matchStart = $match ? strtotime($match['match_date']) : false;
    if (!$match || !$matchStart || $matchStart <= time() || $matchStart > time() + 3600) {
        $error = "Lineups can only be saved for your county's scheduled games during the hour before kickoff.";
    } else {
        $playerIds = is_array($_POST['player_id'] ?? null) ? $_POST['player_id'] : [];
        $playerTypes = is_array($_POST['player_type'] ?? null) ? $_POST['player_type'] : [];
        $jerseyNumbers = is_array($_POST['jersey_number'] ?? null) ? $_POST['jersey_number'] : [];
        $selectedPlayers = [];
        $usedNumbers = [];
        $usedPlayers = [];

        foreach ($playerIds as $index => $playerIdValue) {
            $playerId = (int)$playerIdValue;
            $playerType = $playerTypes[$index] ?? '';
            $submittedByPlayer[$playerId] = [
                'player_type' => $playerType,
                'jersey_number' => $jerseyNumbers[$index] ?? '',
            ];
            if ($playerType === '') {
                continue;
            }
            if (!in_array($playerType, ['starting', 'substitute'], true)) {
                $error = 'Invalid lineup selection.';
                break;
            }
            $jerseyNumber = filter_var($jerseyNumbers[$index] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
            $player = $db->fetchOne(
                "SELECT id, full_name FROM players WHERE id = ? AND county_id = ? AND sport_discipline_id = ? AND status = 'approved'",
                [$playerId, $countyId, $match['sport_discipline_id']]
            );
            if (!$player || $jerseyNumber === false || isset($usedPlayers[$playerId])) {
                $error = 'Choose an eligible approved player and a jersey number from 0 to 99.';
                break;
            }
            if (isset($usedNumbers[$jerseyNumber])) {
                $error = 'Each selected player must have a different jersey number.';
                break;
            }
            $usedNumbers[$jerseyNumber] = true;
            $usedPlayers[$playerId] = true;
            $selectedPlayers[] = [
                'id' => $player['id'],
                'name' => $player['full_name'],
                'type' => $playerType,
                'number' => $jerseyNumber,
            ];
        }

        if ($error === '' && empty(array_filter($selectedPlayers, static function ($player) {
            return $player['type'] === 'starting';
        }))) {
            $error = 'Select at least one starting player before saving the lineup.';
        }

        if ($error === '') {
            $connection = $db->getConnection();
            try {
                $connection->beginTransaction();
                $lineupId = $db->insert(
                    "INSERT INTO coach_lineups (match_id, county_id, coach_id) VALUES (?, ?, ?) ON CONFLICT (match_id, county_id) DO UPDATE SET coach_id = EXCLUDED.coach_id, updated_at = CURRENT_TIMESTAMP RETURNING id",
                    [$matchId, $countyId, $_SESSION['user_id']]
                );
                $db->delete("DELETE FROM coach_lineup_players WHERE lineup_id = ?", [$lineupId]);
                foreach ($selectedPlayers as $player) {
                    $db->insert(
                        "INSERT INTO coach_lineup_players (lineup_id, player_id, player_type, jersey_number, player_name) VALUES (?, ?, ?, ?, ?)",
                        [$lineupId, $player['id'], $player['type'], $player['number'], $player['name']]
                    );
                }
                $connection->commit();
                logActivity('save_coach_lineup', "Saved county {$countyId} lineup for match #{$matchId}");
                setFlash('success', 'Lineup saved. It is now visible on the live-scores fixture.');
                redirect(APP_URL . 'pages/games/lineup.php');
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log('Coach lineup save failed: ' . $exception->getMessage());
                $error = 'The lineup could not be saved. Please try again.';
            }
        }
    }
}

$now = time();
$matches = $db->fetchAll(
    "SELECT m.*, s.name AS sport_name, c1.name AS home_name, c2.name AS away_name
     FROM matches m
     JOIN sports_disciplines s ON s.id = m.sport_discipline_id
     JOIN counties c1 ON c1.id = m.home_county_id
     JOIN counties c2 ON c2.id = m.away_county_id
     WHERE m.status = 'scheduled' AND (m.home_county_id = ? OR m.away_county_id = ?) AND m.match_date >= ? AND m.match_date <= ?
     ORDER BY m.match_date ASC",
    [$countyId, $countyId, date('Y-m-d H:i:s', $now), date('Y-m-d H:i:s', $now + 3600)]
);

$pageTitle = 'Team Lineups';
include __DIR__ . '/../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h4 mb-1">Team Lineups</h2>
        <p class="text-muted mb-0"><?= sanitize($_SESSION['user_county_name'] ?? 'Assigned county') ?></p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= APP_URL ?>pages/games/index.php"><i class="bi bi-broadcast me-1"></i>Live Scores</a>
</div>

<?php if ($error !== ''): ?>
<div class="alert alert-danger"><?= sanitize($error) ?></div>
<?php endif; ?>

<?php if (!$matches): ?>
<div class="alert alert-info">There are no scheduled games for your county in the one-hour lineup window.</div>
<?php endif; ?>

<?php foreach ($matches as $match): ?>
    <?php
    $lineup = $db->fetchOne("SELECT id FROM coach_lineups WHERE match_id = ? AND county_id = ?", [$match['id'], $countyId]);
    $savedPlayers = $lineup
        ? $db->fetchAll("SELECT player_id, player_type, jersey_number FROM coach_lineup_players WHERE lineup_id = ?", [$lineup['id']])
        : [];
    $savedByPlayer = [];
    foreach ($savedPlayers as $savedPlayer) {
        $savedByPlayer[(int)$savedPlayer['player_id']] = $savedPlayer;
    }
    $roster = $db->fetchAll(
        "SELECT id, full_name, primary_position FROM players WHERE county_id = ? AND sport_discipline_id = ? AND status = 'approved' ORDER BY full_name",
        [$countyId, $match['sport_discipline_id']]
    );
    ?>
    <section class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <strong><?= sanitize($match['home_name']) ?> vs <?= sanitize($match['away_name']) ?></strong>
                <div class="small text-muted"><?= sanitize($match['sport_name']) ?> · <?= formatDate($match['match_date'], 'M d, Y g:i A') ?></div>
            </div>
            <?php if ($lineup): ?><span class="badge bg-success">Lineup saved</span><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!$roster): ?>
                <p class="text-muted mb-0">No approved players are registered for this county and sport.</p>
            <?php else: ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="match_id" value="<?= (int)$match['id'] ?>">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Player</th><th>Position</th><th>Lineup</th><th>Jersey</th></tr></thead>
                        <tbody>
                        <?php foreach ($roster as $player): $saved = (int)$match['id'] === $submittedMatchId ? ($submittedByPlayer[(int)$player['id']] ?? null) : ($savedByPlayer[(int)$player['id']] ?? null); ?>
                            <tr>
                                <td>
                                    <?= sanitize($player['full_name']) ?>
                                    <input type="hidden" name="player_id[]" value="<?= (int)$player['id'] ?>">
                                </td>
                                <td><?= sanitize($player['primary_position']) ?></td>
                                <td>
                                    <select name="player_type[]" class="form-select form-select-sm">
                                        <option value="">Not selected</option>
                                        <option value="starting" <?= ($saved['player_type'] ?? '') === 'starting' ? 'selected' : '' ?>>Starting</option>
                                        <option value="substitute" <?= ($saved['player_type'] ?? '') === 'substitute' ? 'selected' : '' ?>>Substitute</option>
                                    </select>
                                </td>
                                <td><input type="number" name="jersey_number[]" class="form-control form-control-sm" min="0" max="99" value="<?= $saved ? (int)$saved['jersey_number'] : '' ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Lineup</button>
            </form>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>