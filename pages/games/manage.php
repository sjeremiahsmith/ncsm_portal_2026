<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['super_admin']);

$db = getDb();

// Handle form actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $sport_discipline_id = (int)($_POST['sport_discipline_id']);
        $home_county_id = (int)($_POST['home_county_id']);
        $away_county_id = (int)($_POST['away_county_id']);
        $match_date = $_POST['match_date'] ?? '';
        $group_label = $_POST['group_label'] ?? '';
        $round = sanitize($_POST['round'] ?? 'Group Stage');
        $status = $_POST['status'] ?? 'scheduled';
        $home_score = $_POST['home_score'] !== '' ? (int)$_POST['home_score'] : null;
        $away_score = $_POST['away_score'] !== '' ? (int)$_POST['away_score'] : null;
        $notes = sanitize($_POST['notes'] ?? '');

        if ($home_county_id === $away_county_id) {
            $msg = '<div class="alert alert-danger">Home and Away teams cannot be the same.</div>';
        } elseif (empty($match_date)) {
            $msg = '<div class="alert alert-danger">Match date is required.</div>';
        } else {
            if ($action === 'create') {
                $db->insert(
                    "INSERT INTO matches (sport_discipline_id, home_county_id, away_county_id, home_score, away_score, match_date, status, group_label, round, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$sport_discipline_id, $home_county_id, $away_county_id, $home_score, $away_score, $match_date, $status, $group_label, $round, $notes, $_SESSION['user_id']]
                );
                logActivity('create_match', "Created match: $home_county_id vs $away_county_id");
                $msg = '<div class="alert alert-success">Match created successfully.</div>';
            } else {
                $db->update(
                    "UPDATE matches SET sport_discipline_id=?, home_county_id=?, away_county_id=?, home_score=?, away_score=?, match_date=?, status=?, group_label=?, round=?, notes=?, updated_at=NOW() WHERE id=?",
                    [$sport_discipline_id, $home_county_id, $away_county_id, $home_score, $away_score, $match_date, $status, $group_label, $round, $notes, $id]
                );
                logActivity('update_match', "Updated match #$id");
                $msg = '<div class="alert alert-success">Match updated successfully.</div>';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->delete("DELETE FROM matches WHERE id = ?", [$id]);
        logActivity('delete_match', "Deleted match #$id");
        $msg = '<div class="alert alert-success">Match deleted.</div>';
    }

    if ($action === 'update_score') {
        $id = (int)($_POST['id'] ?? 0);
        $home_score = $_POST['home_score'] !== '' ? (int)$_POST['home_score'] : null;
        $away_score = $_POST['away_score'] !== '' ? (int)$_POST['away_score'] : null;
        $status = $_POST['status'] ?? 'completed';
        $db->update(
            "UPDATE matches SET home_score=?, away_score=?, status=?, updated_at=NOW() WHERE id=?",
            [$home_score, $away_score, $status, $id]
        );
        logActivity('update_score', "Updated score match #$id: $home_score - $away_score");
        $msg = '<div class="alert alert-success">Score updated.</div>';
    }

    if ($action === 'update_stats') {
        $matchId = (int)($_POST['match_id'] ?? 0);
        $homeYellow = (int)($_POST['home_yellow_cards'] ?? 0);
        $homeRed = (int)($_POST['home_red_cards'] ?? 0);
        $awayYellow = (int)($_POST['away_yellow_cards'] ?? 0);
        $awayRed = (int)($_POST['away_red_cards'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');

        $existing = $db->fetchOne("SELECT id FROM match_reports WHERE match_id = ?", [$matchId]);
        if ($existing) {
            $db->update(
                "UPDATE match_reports SET home_yellow_cards=?, home_red_cards=?, away_yellow_cards=?, away_red_cards=?, notes=? WHERE id=?",
                [$homeYellow, $homeRed, $awayYellow, $awayRed, $notes, $existing['id']]
            );
            $reportId = $existing['id'];
        } else {
            $reportId = $db->insert(
                "INSERT INTO match_reports (match_id, commissioner_id, home_yellow_cards, home_red_cards, away_yellow_cards, away_red_cards, notes) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$matchId, $_SESSION['user_id'], $homeYellow, $homeRed, $awayYellow, $awayRed, $notes]
            );
        }

        // Save carded players
        $db->delete("DELETE FROM match_report_cards WHERE report_id = ?", [$reportId]);
        $cardTeams = $_POST['card_team'] ?? [];
        $cardTypes = $_POST['card_type'] ?? [];
        $cardJerseys = $_POST['card_jersey'] ?? [];
        $cardNames = $_POST['card_name'] ?? [];
        for ($i = 0; $i < count($cardTeams); $i++) {
            if (!empty($cardNames[$i]) && !empty($cardJerseys[$i])) {
                $db->insert(
                    "INSERT INTO match_report_cards (report_id, team, card_type, jersey_number, player_name) VALUES (?, ?, ?, ?, ?)",
                    [$reportId, $cardTeams[$i], $cardTypes[$i], (int)$cardJerseys[$i], sanitize($cardNames[$i])]
                );
            }
        }

        // Save goals
        $db->delete("DELETE FROM match_goals WHERE match_id = ?", [$matchId]);
        $goalTeams = $_POST['goal_team'] ?? [];
        $goalTypes = $_POST['goal_type'] ?? [];
        $goalJerseys = $_POST['goal_jersey'] ?? [];
        $goalNames = $_POST['goal_name'] ?? [];
        $goalMinutes = $_POST['goal_minute'] ?? [];
        for ($i = 0; $i < count($goalTeams); $i++) {
            if (!empty($goalNames[$i]) && !empty($goalJerseys[$i])) {
                $db->insert(
                    "INSERT INTO match_goals (match_id, team, player_name, jersey_number, minute, goal_type) VALUES (?, ?, ?, ?, ?, ?)",
                    [$matchId, $goalTeams[$i], sanitize($goalNames[$i]), (int)$goalJerseys[$i], $goalMinutes[$i] !== '' ? (int)$goalMinutes[$i] : null, $goalTypes[$i]]
                );
            }
        }

        logActivity('update_stats', "Updated stats for match #$matchId");
        $msg = '<div class="alert alert-success">Statistics updated.</div>';
    }
}

// Get all matches
$matches = $db->fetchAll("
    SELECT m.*, s.name as sport_name, c1.name as home_name, c2.name as away_name
    FROM matches m
    JOIN sports_disciplines s ON m.sport_discipline_id = s.id
    JOIN counties c1 ON m.home_county_id = c1.id
    JOIN counties c2 ON m.away_county_id = c2.id
    ORDER BY m.match_date DESC
    LIMIT 100
");

// Kickball standings helper
function getManageKickballStandings($db, $groupLabel = null) {
    $where = ["m.status = 'completed'", "m.sport_discipline_id = 2"];
    $params = [];
    if ($groupLabel) { $where[] = "m.group_label = ?"; $params[] = $groupLabel; }
    $whereSql = "WHERE " . implode(" AND ", $where);

    $rows = $db->fetchAll(
        "SELECT m.home_county_id, m.away_county_id, m.home_score, m.away_score, m.group_label,
                c1.name as home_name, c2.name as away_name
         FROM matches m
         JOIN counties c1 ON m.home_county_id = c1.id
         JOIN counties c2 ON m.away_county_id = c2.id
         $whereSql
         ORDER BY m.group_label, m.match_date",
        $params
    );

    $teams = [];
    foreach ($rows as $r) {
        foreach ([
            ['id' => $r['home_county_id'], 'name' => $r['home_name'], 'group' => $r['group_label'], 'hrf' => (int)$r['home_score'], 'hra' => (int)$r['away_score']],
            ['id' => $r['away_county_id'], 'name' => $r['away_name'], 'group' => $r['group_label'], 'hrf' => (int)$r['away_score'], 'hra' => (int)$r['home_score']]
        ] as $t) {
            $tid = $t['id'];
            if (!isset($teams[$tid])) {
                $teams[$tid] = ['name' => $t['name'], 'group' => $t['group'], 'gp' => 0, 'w' => 0, 'l' => 0, 'd' => 0, 'hrf' => 0, 'hra' => 0, 'hrd' => 0, 'pts' => 0];
            }
            $teams[$tid]['gp']++;
            $teams[$tid]['hrf'] += $t['hrf'];
            $teams[$tid]['hra'] += $t['hra'];
            $teams[$tid]['hrd'] = $teams[$tid]['hrf'] - $teams[$tid]['hra'];
            if ($t['hrf'] > $t['hra']) { $teams[$tid]['w']++; $teams[$tid]['pts'] += 3; }
            elseif ($t['hrf'] === $t['hra']) { $teams[$tid]['d']++; $teams[$tid]['pts'] += 1; }
            else { $teams[$tid]['l']++; }
        }
    }

    uksort($teams, function($a, $b) use ($teams) {
        if ($teams[$a]['pts'] !== $teams[$b]['pts']) return $teams[$b]['pts'] - $teams[$a]['pts'];
        if ($teams[$a]['hrd'] !== $teams[$b]['hrd']) return $teams[$b]['hrd'] - $teams[$a]['hrd'];
        return $teams[$b]['hrf'] - $teams[$a]['hrf'];
    });

    return $teams;
}

$sports = getSports();
$counties = getCounties();
$editMatch = null;
if (isset($_GET['edit'])) {
    $editMatch = $db->fetchOne("SELECT * FROM matches WHERE id = ?", [(int)$_GET['edit']]);
}

$pageTitle = 'Manage Games';
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<style>
.kickball-badge {
    background: #dc3545;
    color: #fff;
    font-size: 0.6rem;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 4px;
    vertical-align: middle;
    font-weight: 600;
}
.ks-table { font-size: 0.8rem; }
.ks-table th { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6c757d; border-top: none; }
.ks-table .ks-pts { font-weight: 800; color: #dc3545; font-size: 0.95rem; }
.ks-table .ks-hrd-pos { color: #28a745; font-weight: 600; }
.ks-table .ks-hrd-neg { color: #dc3545; font-weight: 600; }
.ks-table .ks-hrd-zero { color: #6c757d; }
</style>

<script>
function toggleKickballLabels() {
    var sport = document.querySelector('select[name="sport_discipline_id"]');
    var homeLabel = document.getElementById('homeScoreLabel');
    var awayLabel = document.getElementById('awayScoreLabel');
    if (sport && homeLabel && awayLabel) {
        var isKickball = parseInt(sport.value) === 2;
        homeLabel.innerHTML = isKickball ? 'Home HRF <span class="kickball-badge">HRF</span>' : 'Home Score';
        awayLabel.innerHTML = isKickball ? 'Away HRF <span class="kickball-badge">HRF</span>' : 'Away Score';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    toggleKickballLabels();
    var sportSelect = document.querySelector('select[name="sport_discipline_id"]');
    if (sportSelect) {
        sportSelect.addEventListener('change', toggleKickballLabels);
    }
});
</script>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Manage Games</h4>
    <a href="<?= APP_URL ?>pages/games/index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye me-1"></i>View Games</a>
</div>

<?= $msg ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><?= $editMatch ? 'Edit Match' : 'Create New Match' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $editMatch ? 'update' : 'create' ?>">
                    <?php if ($editMatch): ?>
                    <input type="hidden" name="id" value="<?= $editMatch['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Sport</label>
                            <select name="sport_discipline_id" class="form-select form-select-sm" required>
                                <option value="">Select</option>
                                <?php foreach ($sports as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($editMatch && $editMatch['sport_discipline_id'] === $s['id']) ? 'selected' : '' ?>><?= sanitize($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label small">Group</label>
                            <select name="group_label" class="form-select form-select-sm">
                                <option value="">Select Group</option>
                                <?php foreach (['A','B','C','D'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($editMatch && $editMatch['group_label'] === $g) ? 'selected' : '' ?>>Group <?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="scheduled" <?= ($editMatch && $editMatch['status'] === 'scheduled') ? 'selected' : '' ?>>Scheduled</option>
                                <option value="live" <?= ($editMatch && $editMatch['status'] === 'live') ? 'selected' : '' ?>>Live</option>
                                <option value="completed" <?= ($editMatch && $editMatch['status'] === 'completed') ? 'selected' : '' ?>>Completed</option>
                                <option value="prospond" <?= ($editMatch && $editMatch['status'] === 'prospond') ? 'selected' : '' ?>>Prospond</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-5">
                            <label class="form-label small">Home Team (County)</label>
                            <select name="home_county_id" class="form-select form-select-sm" required>
                                <option value="">Select</option>
                                <?php foreach ($counties as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($editMatch && $editMatch['home_county_id'] === $c['id']) ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-2 text-center pt-4">
                            <span class="text-muted">VS</span>
                        </div>
                        <div class="col-5">
                            <label class="form-label small">Away Team (County)</label>
                            <select name="away_county_id" class="form-select form-select-sm" required>
                                <option value="">Select</option>
                                <?php foreach ($counties as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($editMatch && $editMatch['away_county_id'] === $c['id']) ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small" id="homeScoreLabel">Home Score</label>
                            <input type="number" name="home_score" class="form-control form-control-sm" placeholder="-" value="<?= $editMatch && $editMatch['home_score'] !== null ? (int)$editMatch['home_score'] : '' ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small" id="awayScoreLabel">Away Score</label>
                            <input type="number" name="away_score" class="form-control form-control-sm" placeholder="-" value="<?= $editMatch && $editMatch['away_score'] !== null ? (int)$editMatch['away_score'] : '' ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Round</label>
                            <select name="round" class="form-select form-select-sm">
                                <?php foreach (['Group Stage', 'Big Eight', 'Quarter Final', 'Semi-Final', 'Final'] as $r): ?>
                                <option value="<?= $r ?>" <?= ($editMatch && $editMatch['round'] === $r) ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-8">
                            <label class="form-label small">Match Date/Time</label>
                            <input type="datetime-local" name="match_date" class="form-control form-control-sm" value="<?= $editMatch ? date('Y-m-d\TH:i', strtotime($editMatch['match_date'])) : '' ?>" required>
                        </div>
                        <div class="col-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-<?= $editMatch ? 'check' : 'plus' ?> me-1"></i><?= $editMatch ? 'Update' : 'Create' ?>
                            </button>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="1"><?= $editMatch ? sanitize($editMatch['notes'] ?? '') : '' ?></textarea>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">All Matches</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($matches)): ?>
                <div class="text-center py-4 text-muted">No matches created yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Sport</th>
                                <th>Group</th>
                                <th>Home</th>
                                <th class="text-center">Score</th>
                                <th>Away</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $m): ?>
                            <tr class="row-<?= $m['status'] ?>">
                                <td><small><?= sanitize($m['sport_name']) ?></small></td>
                                <td><span class="group-badge group-<?= $m['group_label'] ?>" style="font-size:0.65rem;"><?= $m['group_label'] ?></span></td>
                                <td><small><?= sanitize($m['home_name']) ?></small></td>
                                <td class="text-center">
                                    <?php if ($m['home_score'] !== null && $m['away_score'] !== null): ?>
                                    <strong><?= (int)$m['home_score'] ?> - <?= (int)$m['away_score'] ?></strong>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= sanitize($m['away_name']) ?></small></td>
                                <td><small class="text-muted"><?= formatDate($m['match_date'], 'M d, h:i A') ?></small></td>
                                <td>
                                    <?php 
                                    $badge = ['scheduled' => 'secondary', 'live' => 'danger', 'completed' => 'success'];
                                    $label = ['scheduled' => 'Scheduled', 'live' => 'LIVE', 'completed' => 'Done'];
                                    ?>
                                    <span class="badge bg-<?= $badge[$m['status']] ?>"><?= $label[$m['status']] ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="?edit=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this match?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Quick Score Entry & Timer Control</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Update scores, control match timer on scheduled/live matches:</p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Match</th>
                                <th class="text-center" style="width:70px;">Home</th>
                                <th class="text-center" style="width:30px;">-</th>
                                <th class="text-center" style="width:70px;">Away</th>
                                <th class="text-center" style="width:90px;">Status</th>
                                <th class="text-center" style="width:200px;">Timer</th>
                                <th style="width:80px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $quickMatches = array_filter($matches, fn($m) => $m['status'] !== 'completed'); ?>
                            <?php if (empty($quickMatches)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-2">All matches completed.</td></tr>
                            <?php else: ?>
                            <?php foreach ($quickMatches as $m): ?>
                            <tr id="quick-row-<?= $m['id'] ?>">
                                <form method="POST" class="score-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_score">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <td><small><?= sanitize($m['home_name']) ?> vs <?= sanitize($m['away_name']) ?></small></td>
                                    <td><input type="number" name="home_score" class="form-control form-control-sm text-center" value="<?= $m['home_score'] !== null ? (int)$m['home_score'] : '' ?>" style="width:60px;"></td>
                                    <td class="text-center text-muted">-</td>
                                    <td><input type="number" name="away_score" class="form-control form-control-sm text-center" value="<?= $m['away_score'] !== null ? (int)$m['away_score'] : '' ?>" style="width:60px;"></td>
                                    <td>
                                        <select name="status" class="form-select form-select-sm">
                                            <option value="scheduled" <?= $m['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                            <option value="live" <?= $m['status'] === 'live' ? 'selected' : '' ?>>Live</option>
                                            <option value="completed" <?= $m['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($m['status'] === 'scheduled'): ?>
                                        <button type="button" class="btn btn-sm btn-success timer-btn" data-match="<?= $m['id'] ?>" data-action="start" title="Start Match Timer">
                                            <i class="bi bi-play-fill"></i> Start
                                        </button>
                                        <?php elseif ($m['status'] === 'live'): ?>
                                            <?php if ($m['timer_kickoff']): ?>
                                            <button type="button" class="btn btn-sm btn-warning timer-btn" data-match="<?= $m['id'] ?>" data-action="pause" title="Pause Timer">
                                                <i class="bi bi-pause-fill"></i> Pause
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-info timer-btn" data-match="<?= $m['id'] ?>" data-action="resume" title="Resume Timer">
                                                <i class="bi bi-play-fill"></i> Resume
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-danger timer-btn" data-match="<?= $m['id'] ?>" data-action="stop" title="End Match">
                                                <i class="bi bi-stop-fill"></i> End
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td><button type="submit" class="btn btn-sm btn-outline-success w-100"><i class="bi bi-check me-1"></i>Update</button></td>
                                </form>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.timer-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var matchId = this.dataset.match;
        var action = this.dataset.action;
        var row = document.getElementById('quick-row-' + matchId);
        var statusSelect = row.querySelector('select[name="status"]');

        if (action === 'stop' && !confirm('End this match? The timer will stop.')) return;
        if (action === 'start' && !confirm('Start the match timer now?')) return;

        var formData = new FormData();
        formData.append('action', action);
        formData.append('match_id', matchId);

        fetch('<?= APP_URL ?>pages/games/timer_control.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (data.status === 'completed') {
                    statusSelect.value = 'completed';
                } else if (data.status === 'live') {
                    statusSelect.value = 'live';
                }
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function(e) {
            alert('Request failed: ' + e.message);
        });
    });
});
</script>

<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Quick Statistics Entry</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Update yellow/red cards, carded players, and notes for live or recent matches:</p>
                <?php
                $statMatches = array_filter($matches, fn($m) => in_array($m['status'], ['live', 'completed']));
                if (empty($statMatches)): ?>
                <div class="text-center text-muted py-3">No live or completed matches yet.</div>
                <?php else: ?>
                <?php foreach ($statMatches as $m):
                    $report = $db->fetchOne("SELECT * FROM match_reports WHERE match_id = ?", [$m['id']]);
                    $cards = $report ? $db->fetchAll("SELECT * FROM match_report_cards WHERE report_id = ?", [$report['id']]) : [];
                    $goals = $db->fetchAll("SELECT * FROM match_goals WHERE match_id = ? ORDER BY minute, team", [$m['id']]);
                ?>
                <div class="card mb-3 border" id="stats-match-<?= $m['id'] ?>" data-home="<?= sanitize($m['home_name']) ?>" data-away="<?= sanitize($m['away_name']) ?>" data-sport="<?= $m['sport_discipline_id'] ?>">
                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= sanitize($m['home_name']) ?> vs <?= sanitize($m['away_name']) ?></strong>
                            <small class="text-muted ms-2"><?= sanitize($m['sport_name']) ?> &middot; <?= $m['group_label'] ?></small>
                            <?php if ($m['home_score'] !== null): ?>
                            <span class="badge bg-dark ms-2"><?= (int)$m['home_score'] ?> - <?= (int)$m['away_score'] ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="badge bg-<?= $m['status'] === 'live' ? 'danger' : 'success' ?>"><?= ucfirst($m['status']) ?></span>
                    </div>
                    <div class="card-body py-2">
                        <form method="POST" class="stats-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_stats">
                            <input type="hidden" name="match_id" value="<?= $m['id'] ?>">

                            <div class="row g-2 mb-2">
                                <div class="col-md-1 text-center">
                                    <label class="form-label small fw-bold text-muted">Home<br>Yellow</label>
                                    <input type="number" name="home_yellow_cards" class="form-control form-control-sm text-center border-warning" min="0" value="<?= $report ? (int)$report['home_yellow_cards'] : 0 ?>" style="font-weight:700;">
                                </div>
                                <div class="col-md-1 text-center">
                                    <label class="form-label small fw-bold text-muted">Home<br>Red</label>
                                    <input type="number" name="home_red_cards" class="form-control form-control-sm text-center border-danger" min="0" value="<?= $report ? (int)$report['home_red_cards'] : 0 ?>" style="font-weight:700;">
                                </div>
                                <div class="col-md-1 text-center pt-4">
                                    <span class="text-muted fw-bold">vs</span>
                                </div>
                                <div class="col-md-1 text-center">
                                    <label class="form-label small fw-bold text-muted">Away<br>Yellow</label>
                                    <input type="number" name="away_yellow_cards" class="form-control form-control-sm text-center border-warning" min="0" value="<?= $report ? (int)$report['away_yellow_cards'] : 0 ?>" style="font-weight:700;">
                                </div>
                                <div class="col-md-1 text-center">
                                    <label class="form-label small fw-bold text-muted">Away<br>Red</label>
                                    <input type="number" name="away_red_cards" class="form-control form-control-sm text-center border-danger" min="0" value="<?= $report ? (int)$report['away_red_cards'] : 0 ?>" style="font-weight:700;">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Notes</label>
                                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Match notes..." value="<?= $report ? sanitize($report['notes'] ?? '') : '' ?>">
                                </div>
                            </div>

                            <!-- Goal Scorers -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="fw-bold text-muted">GOAL SCORERS</small>
                                    <button type="button" class="btn btn-sm btn-outline-success py-0" onclick="addGoalRow(<?= $m['id'] ?>)"><i class="bi bi-plus"></i> Add</button>
                                </div>
                                <div id="goal-rows-<?= $m['id'] ?>">
                                    <?php if (!empty($goals)): ?>
                                    <?php foreach ($goals as $g): ?>
                                    <div class="row g-1 mb-1 goal-row align-items-center">
                                        <div class="col-auto">
                                            <select name="goal_team[]" class="form-select form-select-sm" style="width:70px;">
                                                <option value="home" <?= $g['team'] === 'home' ? 'selected' : '' ?>>Home</option>
                                                <option value="away" <?= $g['team'] === 'away' ? 'selected' : '' ?>>Away</option>
                                            </select>
                                        </div>
                                        <div class="col-auto" style="width:55px;">
                                            <input type="number" name="goal_jersey[]" class="form-control form-control-sm text-center" placeholder="#" min="1" max="99" value="<?= (int)$g['jersey_number'] ?>">
                                        </div>
                                        <div class="col">
                                            <input type="text" name="goal_name[]" class="form-control form-control-sm" placeholder="Scorer name" value="<?= sanitize($g['player_name']) ?>">
                                        </div>
                                        <div class="col-auto" style="width:55px;">
                                            <input type="number" name="goal_minute[]" class="form-control form-control-sm text-center" placeholder="'" min="0" max="120" value="<?= $g['minute'] !== null ? (int)$g['minute'] : '' ?>">
                                        </div>
                                        <div class="col-auto">
                                            <select name="goal_type[]" class="form-select form-select-sm" style="width:80px;">
                                                <option value="normal" <?= $g['goal_type'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                                                <option value="penalty" <?= $g['goal_type'] === 'penalty' ? 'selected' : '' ?>>Penalty</option>
                                                <option value="own_goal" <?= $g['goal_type'] === 'own_goal' ? 'selected' : '' ?>>Own Goal</option>
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="this.closest('.goal-row').remove()"><i class="bi bi-x"></i></button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Carded Players -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="fw-bold text-muted">CARDED PLAYERS</small>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="addCardRow(<?= $m['id'] ?>)"><i class="bi bi-plus"></i> Add</button>
                                </div>
                                <div id="card-rows-<?= $m['id'] ?>">
                                    <?php if (!empty($cards)): ?>
                                    <?php foreach ($cards as $c): ?>
                                    <div class="row g-1 mb-1 card-row align-items-center">
                                        <div class="col-auto">
                                            <select name="card_team[]" class="form-select form-select-sm" style="width:70px;">
                                                <option value="home" <?= $c['team'] === 'home' ? 'selected' : '' ?>>Home</option>
                                                <option value="away" <?= $c['team'] === 'away' ? 'selected' : '' ?>>Away</option>
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <select name="card_type[]" class="form-select form-select-sm" style="width:75px;">
                                                <option value="yellow" <?= $c['card_type'] === 'yellow' ? 'selected' : '' ?>>Yellow</option>
                                                <option value="red" <?= $c['card_type'] === 'red' ? 'selected' : '' ?>>Red</option>
                                            </select>
                                        </div>
                                        <div class="col-auto" style="width:55px;">
                                            <input type="number" name="card_jersey[]" class="form-control form-control-sm text-center" placeholder="#" min="1" max="99" value="<?= (int)$c['jersey_number'] ?>">
                                        </div>
                                        <div class="col">
                                            <input type="text" name="card_name[]" class="form-control form-control-sm" placeholder="Player name" value="<?= sanitize($c['player_name']) ?>">
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="this.closest('.card-row').remove()"><i class="bi bi-x"></i></button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="text-end">
                                <a href="<?= APP_URL ?>pages/games/report.php?edit=<?= $report ? $report['id'] : '' ?>" class="btn btn-sm btn-outline-primary me-1" title="Full Report Editor"><i class="bi bi-pencil-square me-1"></i>Full Editor</a>
                                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Save Stats</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var managePlayerCache = {};

function loadMatchPlayers(matchId, team) {
    var card = document.getElementById('stats-match-' + matchId);
    if (!card) return [];
    var countyName = team === 'home' ? card.dataset.home : card.dataset.away;
    var sportId = parseInt(card.dataset.sport);
    if (!countyName || !sportId) return [];
    var key = countyName + '_' + sportId;
    if (managePlayerCache[key]) return managePlayerCache[key];
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'api_players.php?county_name=' + encodeURIComponent(countyName) + '&sport_id=' + sportId, false);
    xhr.send();
    if (xhr.status === 200) {
        var data = JSON.parse(xhr.responseText);
        managePlayerCache[key] = data.players || [];
    } else {
        managePlayerCache[key] = [];
    }
    return managePlayerCache[key];
}

function getManagePlayerOptions(matchId, team) {
    var players = loadMatchPlayers(matchId, team);
    var html = '<option value="">-- Select --</option>';
    players.forEach(function(p) {
        html += '<option value="' + p.full_name.replace(/"/g, '&quot;') + '">' + p.full_name + '</option>';
    });
    html += '<option value="__other">Other (type)</option>';
    return html;
}

function onManagePlayerChange(sel, matchId, team, type) {
    var row = sel.closest('.row');
    if (sel.value === '__other') {
        var inputHtml;
        if (type === 'card') {
            inputHtml = '<input type="text" name="card_name[]" class="form-control form-control-sm" placeholder="Player name">';
        } else {
            inputHtml = '<input type="text" name="goal_name[]" class="form-control form-control-sm" placeholder="Scorer name">';
        }
        sel.parentElement.innerHTML = inputHtml;
    } else if (sel.value) {
        var nameInput;
        if (type === 'card') {
            nameInput = row.querySelector('input[name="card_name[]"]');
            if (nameInput) nameInput.value = sel.value;
        } else {
            nameInput = row.querySelector('input[name="goal_name[]"]');
            if (nameInput) nameInput.value = sel.value;
        }
    }
}

function addCardRow(matchId) {
    var container = document.getElementById('card-rows-' + matchId);
    var html = '<div class="row g-1 mb-1 card-row align-items-center">';
    html += '<div class="col-auto"><select name="card_team[]" class="form-select form-select-sm" style="width:70px;" onchange="onCardTeamChange(this, ' + matchId + ')"><option value="home">Home</option><option value="away">Away</option></select></div>';
    html += '<div class="col-auto"><select name="card_type[]" class="form-select form-select-sm" style="width:75px;"><option value="yellow">Yellow</option><option value="red">Red</option></select></div>';
    html += '<div class="col-auto" style="width:55px;"><input type="number" name="card_jersey[]" class="form-control form-control-sm text-center" placeholder="#" min="1" max="99"></div>';
    html += '<div class="col"><select name="card_name[]" class="form-select form-select-sm" onchange="onManagePlayerChange(this,' + matchId + ',\'home\',\'card\')">' + getManagePlayerOptions(matchId, 'home') + '</select></div>';
    html += '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="this.closest(\'.card-row\').remove()"><i class="bi bi-x"></i></button></div>';
    html += '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function onCardTeamChange(sel, matchId) {
    var row = sel.closest('.card-row');
    var nameCell = row.querySelectorAll('.col')[0] || row.children[3];
    var team = sel.value;
    var html = '<select name="card_name[]" class="form-select form-select-sm" onchange="onManagePlayerChange(this,' + matchId + ',\'' + team + '\',\'card\')">' + getManagePlayerOptions(matchId, team) + '</select>';
    if (nameCell) nameCell.innerHTML = html;
}

function addGoalRow(matchId) {
    var container = document.getElementById('goal-rows-' + matchId);
    var html = '<div class="row g-1 mb-1 goal-row align-items-center">';
    html += '<div class="col-auto"><select name="goal_team[]" class="form-select form-select-sm" style="width:70px;" onchange="onGoalTeamChange(this, ' + matchId + ')"><option value="home">Home</option><option value="away">Away</option></select></div>';
    html += '<div class="col-auto" style="width:55px;"><input type="number" name="goal_jersey[]" class="form-control form-control-sm text-center" placeholder="#" min="1" max="99"></div>';
    html += '<div class="col"><select name="goal_name[]" class="form-select form-select-sm" onchange="onManagePlayerChange(this,' + matchId + ',\'home\',\'goal\')">' + getManagePlayerOptions(matchId, 'home') + '</select></div>';
    html += '<div class="col-auto" style="width:55px;"><input type="number" name="goal_minute[]" class="form-control form-control-sm text-center" placeholder="\'" min="0" max="120"></div>';
    html += '<div class="col-auto"><select name="goal_type[]" class="form-select form-select-sm" style="width:80px;"><option value="normal">Normal</option><option value="penalty">Penalty</option><option value="own_goal">Own Goal</option></select></div>';
    html += '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="this.closest(\'.goal-row\').remove()"><i class="bi bi-x"></i></button></div>';
    html += '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function onGoalTeamChange(sel, matchId) {
    var row = sel.closest('.goal-row');
    var nameCell = row.querySelectorAll('.col')[0] || row.children[2];
    var team = sel.value;
    var html = '<select name="goal_name[]" class="form-select form-select-sm" onchange="onManagePlayerChange(this,' + matchId + ',\'' + team + '\',\'goal\')">' + getManagePlayerOptions(matchId, team) + '</select>';
    if (nameCell) nameCell.innerHTML = html;
}
</script>

<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-trophy me-2 text-danger"></i>Kickball Standings <span class="kickball-badge" style="font-size:0.65rem;">GP / W / L / D / HRF / HRA / HRD / PTS</span></h5>
                <a href="<?= APP_URL ?>pages/games/standings.php?sport=2" class="btn btn-outline-danger btn-sm">Full Standings</a>
            </div>
            <div class="card-body p-0">
                <?php
                $kgroups = ['A','B','C','D'];
                $anyKs = false;
                foreach ($kgroups as $kg):
                    $kStands = getManageKickballStandings($db, $kg);
                    if (empty($kStands)) continue;
                    $anyKs = true;
                ?>
                <div class="p-3 border-bottom">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-danger me-2">Group <?= $kg ?></span>
                        <small class="text-muted"><?= count($kStands) ?> teams</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm ks-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:30px;">#</th>
                                    <th>Team</th>
                                    <th class="text-center">GP</th>
                                    <th class="text-center">W</th>
                                    <th class="text-center">L</th>
                                    <th class="text-center">D</th>
                                    <th class="text-center">HRF</th>
                                    <th class="text-center">HRA</th>
                                    <th class="text-center">HRD</th>
                                    <th class="text-center">PTS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rk = 0; foreach ($kStands as $s): $rk++; ?>
                                <tr>
                                    <td class="text-muted"><?= $rk ?></td>
                                    <td><?= sanitize($s['name']) ?></td>
                                    <td class="text-center"><?= $s['gp'] ?></td>
                                    <td class="text-center"><?= $s['w'] ?></td>
                                    <td class="text-center"><?= $s['l'] ?></td>
                                    <td class="text-center"><?= $s['d'] ?></td>
                                    <td class="text-center"><strong><?= $s['hrf'] ?></strong></td>
                                    <td class="text-center"><?= $s['hra'] ?></td>
                                    <td class="text-center <?= $s['hrd'] > 0 ? 'ks-hrd-pos' : ($s['hrd'] < 0 ? 'ks-hrd-neg' : 'ks-hrd-zero') ?>"><?= $s['hrd'] > 0 ? '+' : '' ?><?= $s['hrd'] ?></td>
                                    <td class="text-center ks-pts"><?= $s['pts'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$anyKs): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-trophy me-1"></i> No kickball standings yet — complete matches will appear here.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
