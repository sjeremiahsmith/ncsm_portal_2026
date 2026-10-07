<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['coach']);
ensureCoachLineupSchema();

$db = getDb();
$startingPositions = [
    'left_winger' => 'Left Winger',
    'striker' => 'Striker',
    'right_winger' => 'Right Winger',
    'left_midfielder' => 'Left Central Midfielder',
    'defensive_midfielder' => 'Defensive Midfielder',
    'right_midfielder' => 'Right Central Midfielder',
    'left_back' => 'Left Back',
    'left_center_back' => 'Left Centre-Back',
    'right_center_back' => 'Right Centre-Back',
    'right_back' => 'Right Back',
    'goalkeeper' => 'Goalkeeper',
];
$countyId = getAssignedCountyId();
$sportId = (int)($_SESSION['user_association_id'] ?? 0);
if (!$countyId || $sportId <= 0) {
    http_response_code(403);
    exit('This coach account needs an assigned county and sport.');
}

$error = '';
$submittedMatchId = 0;
$submittedByPlayer = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $action = $_POST['action'] ?? 'save_lineup';
    $matchId = (int)($_POST['match_id'] ?? 0);
    if ($action === 'substitute') {
        $match = $db->fetchOne(
            "SELECT * FROM matches WHERE id = ? AND status IN ('scheduled', 'live') AND sport_discipline_id = ? AND (home_county_id = ? OR away_county_id = ?)",
            [$matchId, $sportId, $countyId, $countyId]
        );
        $matchStart = $match ? strtotime($match['match_date']) : false;
        $substitutionAllowed = $match && ($match['status'] === 'live' || ($match['status'] === 'scheduled' && $matchStart > time() && $matchStart <= time() + 3600));
        $lineup = $match
            ? $db->fetchOne("SELECT id FROM coach_lineups WHERE match_id = ? AND county_id = ?", [$matchId, $countyId])
            : null;
        $outgoingPlayerId = (int)($_POST['outgoing_player_id'] ?? 0);
        $incomingPlayerId = (int)($_POST['incoming_player_id'] ?? 0);
        $outgoing = $lineup
            ? $db->fetchOne("SELECT id, player_name, jersey_number, position FROM coach_lineup_players WHERE lineup_id = ? AND player_id = ? AND player_type = 'starting'", [$lineup['id'], $outgoingPlayerId])
            : null;
        $incoming = $lineup
            ? $db->fetchOne("SELECT id, player_name, jersey_number FROM coach_lineup_players WHERE lineup_id = ? AND player_id = ? AND player_type = 'substitute'", [$lineup['id'], $incomingPlayerId])
            : null;

        if (!$substitutionAllowed || !$lineup || !$outgoing || !$incoming || $outgoingPlayerId === $incomingPlayerId) {
            setFlash('error', 'Substitutions are available during the lineup window or while the game is live. Choose one of your starters and a listed substitute.');
            redirect(APP_URL . 'pages/games/lineup.php');
        }
        if (!isset($startingPositions[$outgoing['position']])) {
            setFlash('error', 'The starter has no saved field position. Update the lineup before making a substitution.');
            redirect(APP_URL . 'pages/games/lineup.php');
        }

        $connection = $db->getConnection();
        try {
            $connection->beginTransaction();
            $promoted = $db->update(
                "UPDATE coach_lineup_players SET player_type = 'starting', position = ? WHERE id = ? AND player_type = 'substitute'",
                [$outgoing['position'], $incoming['id']]
            );
            $replaced = $db->update(
                "UPDATE coach_lineup_players SET player_type = 'substitute', position = '' WHERE id = ? AND player_type = 'starting'",
                [$outgoing['id']]
            );
            if ($promoted !== 1 || $replaced !== 1) {
                throw new RuntimeException('The lineup changed while the substitution was being saved.');
            }
            if ($match['status'] === 'live') {
                $db->insert(
                    "INSERT INTO coach_lineup_substitutions (lineup_id, outgoing_player_id, incoming_player_id, outgoing_player_name, incoming_player_name, outgoing_jersey_number, incoming_jersey_number, position, game_minute, substituted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$lineup['id'], $outgoingPlayerId, $incomingPlayerId, $outgoing['player_name'], $incoming['player_name'], $outgoing['jersey_number'], $incoming['jersey_number'], $outgoing['position'], getMatchMinuteLabel($match), $_SESSION['user_id']]
                );
            }
            $db->update("UPDATE coach_lineups SET coach_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$_SESSION['user_id'], $lineup['id']]);
            $connection->commit();
            logActivity('coach_substitution', "Substituted {$outgoing['player_name']} for {$incoming['player_name']} in match #{$matchId}");
            $successMessage = $match['status'] === 'live'
                ? sanitize($incoming['player_name']) . ' replaced ' . sanitize($outgoing['player_name']) . ' in the starting lineup.'
                : 'The pregame lineup has been updated.';
            setFlash('success', $successMessage);
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log('Coach substitution failed: ' . $exception->getMessage());
            setFlash('error', 'The substitution could not be completed. Refresh the lineup and try again.');
        }
        redirect(APP_URL . 'pages/games/lineup.php');
    }

    $submittedMatchId = $matchId;
    $match = $db->fetchOne(
        "SELECT * FROM matches WHERE id = ? AND status = 'scheduled' AND sport_discipline_id = ? AND (home_county_id = ? OR away_county_id = ?)",
        [$matchId, $sportId, $countyId, $countyId]
    );
    $matchStart = $match ? strtotime($match['match_date']) : false;
    if (!$match || !$matchStart || $matchStart <= time() || $matchStart > time() + 3600) {
        $error = "Lineups can only be saved for your county's scheduled games during the hour before kickoff.";
    } else {
        $playerIds = is_array($_POST['player_id'] ?? null) ? $_POST['player_id'] : [];
        $playerTypes = is_array($_POST['player_type'] ?? null) ? $_POST['player_type'] : [];
        $jerseyNumbers = is_array($_POST['jersey_number'] ?? null) ? $_POST['jersey_number'] : [];
        $positions = is_array($_POST['position'] ?? null) ? $_POST['position'] : [];
        $selectedPlayers = [];
        $usedNumbers = [];
        $usedPlayers = [];
        $usedPositions = [];
        $startingCount = 0;

        foreach ($playerIds as $index => $playerIdValue) {
            $playerId = (int)$playerIdValue;
            $playerType = $playerTypes[$index] ?? '';
            $submittedByPlayer[$playerId] = [
                'player_type' => $playerType,
                'jersey_number' => $jerseyNumbers[$index] ?? '',
                'position' => $positions[$index] ?? '',
            ];
            if ($playerType === '') {
                continue;
            }
            if (!in_array($playerType, ['starting', 'substitute'], true)) {
                $error = 'Invalid lineup selection.';
                break;
            }
            $position = $playerType === 'starting' ? (string)($positions[$index] ?? '') : '';
            if ($playerType === 'starting') {
                $startingCount++;
                if ($startingCount > 11 || !isset($startingPositions[$position]) || isset($usedPositions[$position])) {
                    $error = 'Choose up to 11 starters and assign each a different standard position.';
                    break;
                }
                $usedPositions[$position] = true;
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
                'position' => $position,
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
                        "INSERT INTO coach_lineup_players (lineup_id, player_id, player_type, jersey_number, player_name, position) VALUES (?, ?, ?, ?, ?, ?)",
                        [$lineupId, $player['id'], $player['type'], $player['number'], $player['name'], $player['position']]
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
     WHERE (m.home_county_id = ? OR m.away_county_id = ?)
       AND m.sport_discipline_id = ?
       AND ((m.status = 'scheduled' AND m.match_date >= ? AND m.match_date <= ?)
            OR EXISTS (SELECT 1 FROM coach_lineups cl WHERE cl.match_id = m.id AND cl.county_id = ?))
     ORDER BY m.match_date DESC",
    [$countyId, $countyId, $sportId, date('Y-m-d H:i:s', $now), date('Y-m-d H:i:s', $now + 3600), $countyId]
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
<div class="alert alert-info">There are no scheduled games or saved lineups for your county and sport.</div>
<?php endif; ?>

<style>
.coach-lineup-pitch { background: repeating-linear-gradient(0deg, #277d48, #277d48 48px, #2c8750 48px, #2c8750 96px); border: 2px solid #e5f4e9; border-radius: 8px; padding: 12px; color: #fff; }
.coach-formation-row { display: grid; gap: 8px; margin-bottom: 8px; }
.coach-formation-row.attack, .coach-formation-row.midfield { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.coach-formation-row.defense { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.coach-formation-row.goalkeeper { grid-template-columns: minmax(0, 1fr); max-width: 34%; margin: 0 auto; }
.coach-position-slot { min-width: 0; min-height: 70px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; text-align: center; padding: 5px; border: 1px solid rgba(255,255,255,.55); border-radius: 6px; background: rgba(9,55,29,.38); }
.coach-position-name { font-size: .68rem; font-weight: 700; }
.coach-position-player { font-size: .72rem; overflow-wrap: anywhere; }
.coach-substitute-list { display: flex; flex-wrap: wrap; gap: 6px; }
.coach-substitute-item { padding: 4px 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: .8rem; }
@media (max-width: 575px) { .coach-formation-row { gap: 4px; margin-bottom: 4px; } .coach-position-slot { min-height: 64px; padding: 3px; } .coach-position-name, .coach-position-player { font-size: .62rem; } }
</style>

<?php foreach ($matches as $match): ?>
    <?php
    $lineup = $db->fetchOne("SELECT id FROM coach_lineups WHERE match_id = ? AND county_id = ?", [$match['id'], $countyId]);
    $savedPlayers = $lineup
        ? $db->fetchAll("SELECT player_id, player_type, jersey_number, player_name, position FROM coach_lineup_players WHERE lineup_id = ? ORDER BY player_type, jersey_number", [$lineup['id']])
        : [];
    $savedByPlayer = [];
    $savedByPosition = [];
    $savedStartingPlayers = [];
    $savedSubstitutes = [];
    foreach ($savedPlayers as $savedPlayer) {
        $savedByPlayer[(int)$savedPlayer['player_id']] = $savedPlayer;
        if ($savedPlayer['player_type'] === 'starting') {
            $savedByPosition[$savedPlayer['position']] = $savedPlayer;
            $savedStartingPlayers[] = $savedPlayer;
        } else {
            $savedSubstitutes[] = $savedPlayer;
        }
    }
    $matchStart = strtotime($match['match_date']);
    $lineupEditable = $match['status'] === 'scheduled' && $matchStart > $now && $matchStart <= $now + 3600;
    $substitutionAllowed = $lineupEditable || $match['status'] === 'live';
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
            <?php if ((!$lineupEditable || !$roster) && $lineup): ?>
            <div class="small text-muted mb-2">
                <?= $match['status'] === 'scheduled' ? 'Lineup saved. Editing opens during the hour before kickoff.' : 'Saved lineup · ' . ucfirst($match['status']) ?>
            </div>
            <div class="coach-lineup-pitch mb-3">
                <div class="coach-formation-row attack">
                    <?php foreach (['left_winger', 'striker', 'right_winger'] as $position): $player = $savedByPosition[$position] ?? null; ?>
                    <div class="coach-position-slot"><span class="coach-position-name"><?= sanitize($startingPositions[$position]) ?></span><span class="coach-position-player"><?= $player ? '#' . (int)$player['jersey_number'] . ' ' . sanitize($player['player_name']) : 'Open position' ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div class="coach-formation-row midfield">
                    <?php foreach (['left_midfielder', 'defensive_midfielder', 'right_midfielder'] as $position): $player = $savedByPosition[$position] ?? null; ?>
                    <div class="coach-position-slot"><span class="coach-position-name"><?= sanitize($startingPositions[$position]) ?></span><span class="coach-position-player"><?= $player ? '#' . (int)$player['jersey_number'] . ' ' . sanitize($player['player_name']) : 'Open position' ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div class="coach-formation-row defense">
                    <?php foreach (['left_back', 'left_center_back', 'right_center_back', 'right_back'] as $position): $player = $savedByPosition[$position] ?? null; ?>
                    <div class="coach-position-slot"><span class="coach-position-name"><?= sanitize($startingPositions[$position]) ?></span><span class="coach-position-player"><?= $player ? '#' . (int)$player['jersey_number'] . ' ' . sanitize($player['player_name']) : 'Open position' ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div class="coach-formation-row goalkeeper">
                    <?php $player = $savedByPosition['goalkeeper'] ?? null; ?>
                    <div class="coach-position-slot"><span class="coach-position-name"><?= sanitize($startingPositions['goalkeeper']) ?></span><span class="coach-position-player"><?= $player ? '#' . (int)$player['jersey_number'] . ' ' . sanitize($player['player_name']) : 'Open position' ?></span></div>
                </div>
            </div>
            <?php if ($savedSubstitutes): ?>
            <h6 class="mb-2">Substitutes</h6>
            <div class="coach-substitute-list mb-3">
                <?php foreach ($savedSubstitutes as $substitute): ?>
                <span class="coach-substitute-item">#<?= (int)$substitute['jersey_number'] ?> <?= sanitize($substitute['player_name']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php elseif (!$roster): ?>
                <p class="text-muted mb-0">No approved players are registered for this county and sport.</p>
            <?php else: ?>
            <form method="POST" class="coach-lineup-form">
                <?= csrfField() ?>
                <input type="hidden" name="match_id" value="<?= (int)$match['id'] ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Starting lineup</h6>
                    <span class="badge bg-primary coach-starting-count">0 / 11</span>
                </div>
                <div class="coach-lineup-error alert alert-danger py-2 d-none" role="alert"></div>
                <div class="coach-lineup-pitch mb-3">
                    <div class="coach-formation-row attack">
                        <?php foreach (['left_winger', 'striker', 'right_winger'] as $position): ?>
                        <div class="coach-position-slot" data-position-slot="<?= $position ?>"><span class="coach-position-name"><?= sanitize($startingPositions[$position]) ?></span><span class="coach-position-player">Open position</span></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="coach-formation-row midfield">
                        <?php foreach (['left_midfielder', 'defensive_midfielder', 'right_midfielder'] as $position): ?>
                        <div class="coach-position-slot" data-position-slot="<?= $position ?>"><span class="coach-position-name"><?= sanitize($startingPositions[$position]) ?></span><span class="coach-position-player">Open position</span></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="coach-formation-row defense">
                        <?php foreach (['left_back', 'left_center_back', 'right_center_back', 'right_back'] as $position): ?>
                        <div class="coach-position-slot" data-position-slot="<?= $position ?>"><span class="coach-position-name"><?= sanitize($startingPositions[$position]) ?></span><span class="coach-position-player">Open position</span></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="coach-formation-row goalkeeper">
                        <div class="coach-position-slot" data-position-slot="goalkeeper"><span class="coach-position-name"><?= sanitize($startingPositions['goalkeeper']) ?></span><span class="coach-position-player">Open position</span></div>
                    </div>
                </div>
                <h6 class="mb-2">Substitutes</h6>
                <div class="coach-substitute-list mb-3"></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Player</th><th>Selection</th><th>Starting position</th><th>Jersey</th></tr></thead>
                        <tbody>
                        <?php foreach ($roster as $player): $saved = (int)$match['id'] === $submittedMatchId ? ($submittedByPlayer[(int)$player['id']] ?? null) : ($savedByPlayer[(int)$player['id']] ?? null); ?>
                            <tr class="coach-roster-row">
                                <td>
                                    <span class="coach-player-name"><?= sanitize($player['full_name']) ?></span>
                                    <input type="hidden" name="player_id[]" value="<?= (int)$player['id'] ?>">
                                </td>
                                <td>
                                    <select name="player_type[]" class="form-select form-select-sm">
                                        <option value="">Not selected</option>
                                        <option value="starting" <?= ($saved['player_type'] ?? '') === 'starting' ? 'selected' : '' ?>>Starting</option>
                                        <option value="substitute" <?= ($saved['player_type'] ?? '') === 'substitute' ? 'selected' : '' ?>>Substitute</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="position[]" class="form-select form-select-sm">
                                        <option value="">Choose position</option>
                                        <?php foreach ($startingPositions as $position => $positionLabel): ?>
                                            <option value="<?= sanitize($position) ?>" <?= ($saved['position'] ?? '') === $position ? 'selected' : '' ?>><?= sanitize($positionLabel) ?></option>
                                        <?php endforeach; ?>
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
            <?php if ($lineup && $savedStartingPlayers): ?>
            <form method="POST" class="border-top pt-3" onsubmit="return confirm('Make this substitution?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="substitute">
                <input type="hidden" name="match_id" value="<?= (int)$match['id'] ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small">Player leaving</label>
                        <select name="outgoing_player_id" class="form-select form-select-sm" required>
                            <option value="">Select a starter</option>
                            <?php foreach ($savedStartingPlayers as $starter): if (!$starter['player_id']) continue; ?>
                            <option value="<?= (int)$starter['player_id'] ?>">#<?= (int)$starter['jersey_number'] ?> <?= sanitize($starter['player_name']) ?> · <?= sanitize($startingPositions[$starter['position']] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small">Player entering</label>
                        <select name="incoming_player_id" class="form-select form-select-sm" required>
                            <option value=""><?= $savedSubstitutes ? 'Select a substitute' : 'No substitutes selected' ?></option>
                            <?php foreach ($savedSubstitutes as $substitute): if (!$substitute['player_id']) continue; ?>
                            <option value="<?= (int)$substitute['player_id'] ?>">#<?= (int)$substitute['jersey_number'] ?> <?= sanitize($substitute['player_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-outline-primary btn-sm" type="submit" <?= $substitutionAllowed && $savedSubstitutes ? '' : 'disabled' ?> title="<?= !$savedSubstitutes ? 'Add a substitute to the lineup first' : ($substitutionAllowed ? 'Replace the selected starter' : 'Substitutions open during the hour before kickoff or while live') ?>"><i class="bi bi-arrow-left-right me-1"></i>Substitute</button>
                    </div>
                </div>
                <?php if (!$savedSubstitutes): ?><small class="text-muted d-block mt-2">Add at least one substitute to enable substitutions.</small><?php endif; ?>
                <?php if (!$substitutionAllowed): ?><small class="text-muted d-block mt-2">Substitutions open during the hour before kickoff or while the game is live.</small><?php endif; ?>
            </form>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>

<script>
document.querySelectorAll('.coach-lineup-form').forEach(function(form) {
    var rows = Array.from(form.querySelectorAll('.coach-roster-row'));
    var errorBox = form.querySelector('.coach-lineup-error');

    function updateLineup() {
        var starting = [];
        var selectedStartingCount = 0;
        var substitutes = [];
        var positionCounts = {};

        rows.forEach(function(row) {
            var selection = row.querySelector('[name="player_type[]"]');
            var position = row.querySelector('[name="position[]"]');
            if (selection.value === 'starting') selectedStartingCount++;
            if (selection.value === 'starting' && position.value !== '') {
                positionCounts[position.value] = (positionCounts[position.value] || 0) + 1;
                starting.push(row);
            } else if (selection.value === 'substitute') {
                substitutes.push(row);
            }
        });

        rows.forEach(function(row) {
            var selection = row.querySelector('[name="player_type[]"]');
            var position = row.querySelector('[name="position[]"]');
            var jersey = row.querySelector('[name="jersey_number[]"]');
            var isStarter = selection.value === 'starting';
            position.disabled = !isStarter;
            position.required = isStarter;
            jersey.required = selection.value !== '';

            Array.from(position.options).forEach(function(option) {
                if (!option.value) return;
                var count = positionCounts[option.value] || 0;
                option.disabled = count > 0 && !(isStarter && position.value === option.value && count === 1);
            });
        });

        form.querySelector('.coach-starting-count').textContent = selectedStartingCount + ' / 11';
        form.querySelectorAll('[data-position-slot]').forEach(function(slot) {
            var playerRow = starting.find(function(row) {
                return row.querySelector('[name="position[]"]').value === slot.dataset.positionSlot;
            });
            var playerLabel = slot.querySelector('.coach-position-player');
            if (!playerRow) {
                playerLabel.textContent = 'Open position';
                return;
            }
            var name = playerRow.querySelector('.coach-player-name').textContent.trim();
            var jersey = playerRow.querySelector('[name="jersey_number[]"]').value;
            playerLabel.textContent = (jersey !== '' ? '#' + jersey + ' ' : '') + name;
        });

        var substituteList = form.querySelector('.coach-substitute-list');
        substituteList.replaceChildren();
        substitutes.forEach(function(row) {
            var name = row.querySelector('.coach-player-name').textContent.trim();
            var jersey = row.querySelector('[name="jersey_number[]"]').value;
            var item = document.createElement('span');
            item.className = 'coach-substitute-item';
            item.textContent = (jersey !== '' ? '#' + jersey + ' ' : '') + name;
            substituteList.appendChild(item);
        });

        var hasDuplicatePosition = Object.keys(positionCounts).some(function(position) {
            return positionCounts[position] > 1;
        });
        errorBox.textContent = selectedStartingCount > 11
            ? 'A starting lineup can contain no more than 11 players.'
            : (hasDuplicatePosition ? 'Each starting player needs a different position.' : '');
        errorBox.classList.toggle('d-none', errorBox.textContent === '');
    }

    form.addEventListener('change', updateLineup);
    form.addEventListener('input', updateLineup);
    form.addEventListener('submit', function(event) {
        var selectedStarters = rows.filter(function(row) {
            return row.querySelector('[name="player_type[]"]').value === 'starting';
        });
        var selectedPositions = selectedStarters.map(function(row) {
            return row.querySelector('[name="position[]"]').value;
        });
        if (selectedStarters.length > 11 || selectedStarters.some(function(row) {
            return row.querySelector('[name="position[]"]').value === '';
        }) || new Set(selectedPositions).size !== selectedPositions.length) {
            event.preventDefault();
            errorBox.textContent = 'Choose up to 11 starters and assign each a different standard position.';
            errorBox.classList.remove('d-none');
        }
    });
    updateLineup();
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>