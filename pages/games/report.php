<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['match_commissioner', 'super_admin']);

$db = getDb();
$msg = '';
$editId = (int)($_GET['edit'] ?? 0);
$editReport = null;
$editCards = [];
$editSquad = [];

if ($editId) {
    $editReport = $db->fetchOne("SELECT * FROM match_reports WHERE id = ?", [$editId]);
    if ($editReport) {
        $editCards = $db->fetchAll("SELECT * FROM match_report_cards WHERE report_id = ? ORDER BY team, card_type, jersey_number", [$editId]);
        $editSquad = $db->fetchAll("SELECT * FROM match_squad_players WHERE report_id = ? ORDER BY team, player_type, jersey_number", [$editId]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $match_id = (int)$_POST['match_id'];
    $notes = sanitize($_POST['notes'] ?? '');

    if (!$match_id) {
        $msg = '<div class="alert alert-danger">Please select a match.</div>';
    } else {
        $existing = $db->fetchOne("SELECT id FROM match_reports WHERE match_id = ? AND id != ?", [$match_id, $editId]);

        if ($existing) {
            $msg = '<div class="alert alert-warning">A report already exists for this match. <a href="?edit=' . $existing['id'] . '">Edit it</a>.</div>';
        } else {
            $home_yellow = (int)($_POST['home_yellow_cards'] ?? 0);
            $home_red = (int)($_POST['home_red_cards'] ?? 0);
            $away_yellow = (int)($_POST['away_yellow_cards'] ?? 0);
            $away_red = (int)($_POST['away_red_cards'] ?? 0);

            if ($editId && $editReport) {
                $db->update(
                    "UPDATE match_reports SET home_yellow_cards=?, home_red_cards=?, away_yellow_cards=?, away_red_cards=?, notes=? WHERE id=?",
                    [$home_yellow, $home_red, $away_yellow, $away_red, $notes, $editId]
                );
                $db->delete("DELETE FROM match_report_cards WHERE report_id = ?", [$editId]);
                $db->delete("DELETE FROM match_squad_players WHERE report_id = ?", [$editId]);
                logActivity('update_match_report', "Updated report for match #$match_id");
                $reportId = $editId;
                $msg = '<div class="alert alert-success">Report updated successfully.</div>';
            } else {
                $reportId = $db->insert(
                    "INSERT INTO match_reports (match_id, commissioner_id, home_yellow_cards, home_red_cards, away_yellow_cards, away_red_cards, notes) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$match_id, $_SESSION['user_id'], $home_yellow, $home_red, $away_yellow, $away_red, $notes]
                );
                logActivity('create_match_report', "Submitted report for match #$match_id");
                $msg = '<div class="alert alert-success">Report submitted successfully.</div>';
            }

            // Save squad players
            $sq_teams = $_POST['sq_team'] ?? [];
            $sq_types = $_POST['sq_type'] ?? [];
            $sq_jerseys = $_POST['sq_jersey'] ?? [];
            $sq_names = $_POST['sq_name'] ?? [];
            for ($i = 0; $i < count($sq_teams); $i++) {
                if (!empty($sq_names[$i]) && !empty($sq_jerseys[$i])) {
                    $db->insert(
                        "INSERT INTO match_squad_players (report_id, team, player_type, jersey_number, player_name) VALUES (?, ?, ?, ?, ?)",
                        [$reportId, $sq_teams[$i], $sq_types[$i], (int)$sq_jerseys[$i], sanitize($sq_names[$i])]
                    );
                }
            }

            // Save carded players
            $card_teams = $_POST['card_team'] ?? [];
            $card_types = $_POST['card_type'] ?? [];
            $card_jerseys = $_POST['card_jersey'] ?? [];
            $card_names = $_POST['card_name'] ?? [];
            for ($i = 0; $i < count($card_teams); $i++) {
                if (!empty($card_names[$i]) && !empty($card_jerseys[$i])) {
                    $db->insert(
                        "INSERT INTO match_report_cards (report_id, team, card_type, jersey_number, player_name) VALUES (?, ?, ?, ?, ?)",
                        [$reportId, $card_teams[$i], $card_types[$i], (int)$card_jerseys[$i], sanitize($card_names[$i])]
                    );
                }
            }
        }
    }
}

$matches = $db->fetchAll("
    SELECT m.*, s.name as sport_name, c1.name as home_name, c2.name as away_name
    FROM matches m
    JOIN sports_disciplines s ON m.sport_discipline_id = s.id
    JOIN counties c1 ON m.home_county_id = c1.id
    JOIN counties c2 ON m.away_county_id = c2.id
    WHERE m.status IN ('live', 'completed')
    ORDER BY FIELD(m.status, 'live', 'completed'), m.match_date DESC
");

$reports = $db->fetchAll("
    SELECT r.*, m.match_date, m.home_score, m.away_score,
           c1.name as home_name, c2.name as away_name, s.name as sport_name,
           u.full_name as commissioner_name
    FROM match_reports r
    JOIN matches m ON r.match_id = m.id
    JOIN sports_disciplines s ON m.sport_discipline_id = s.id
    JOIN counties c1 ON m.home_county_id = c1.id
    JOIN counties c2 ON m.away_county_id = c2.id
    JOIN users u ON r.commissioner_id = u.id
    ORDER BY r.created_at DESC
");

$pageTitle = 'Match Reports';
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<style>
.squad-table { font-size:0.82rem; }
.squad-table th { font-size:0.6rem; text-transform:uppercase; letter-spacing:0.05em; color:#6c757d; border-top:none; background:transparent; padding:0.3rem 0.5rem; }
.squad-table td { vertical-align:middle; padding:0.25rem 0.5rem; }
.squad-table tbody tr { transition:background 0.15s; }
.squad-table tbody tr:hover { background:#f5f5f5; }
.jersey-badge { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:50%; font-weight:800; font-size:0.7rem; color:#fff; margin-right:6px; flex-shrink:0; }
.jersey-home { background:#dc3545; }
.jersey-away { background:#0d6efd; }
.card-yellow { display:inline-block; width:12px; height:18px; background:#ffc107; border-radius:2px; vertical-align:middle; }
.card-red { display:inline-block; width:12px; height:18px; background:#dc3545; border-radius:2px; vertical-align:middle; }
.form-section { background:#fafbfc; border-radius:8px; padding:0.75rem; margin-bottom:0.75rem; border:1px solid #eee; }
.form-section h6 { font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:0.5rem; }
.section-badge { font-size:0.55rem; padding:0.2em 0.5em; vertical-align:middle; }
.remove-btn { opacity:0.3; transition:opacity 0.15s; cursor:pointer; }
.remove-btn:hover { opacity:1; color:#dc3545; }
.report-card { border:none; border-radius:12px; overflow:hidden; }
.report-card .report-header { background:linear-gradient(135deg,#1a1a1a,#2d2d2d); color:#fff; padding:0.75rem 1rem; }
.report-card .report-header small { color:rgba(255,255,255,0.6); }
.squad-col { padding:0.5rem; }
.squad-col .team-label { font-size:0.65rem; text-transform:uppercase; letter-spacing:0.05em; font-weight:700; padding:0.2rem 0.5rem; border-radius:4px; display:inline-block; margin-bottom:0.4rem; }
.player-row { display:flex; align-items:center; padding:0.2rem 0; border-bottom:1px solid #f0f0f0; }
.player-row:last-child { border-bottom:none; }
@media print {
    body * { visibility:hidden; }
    .report-area, .report-area * { visibility:visible; }
    .report-area { position:absolute; left:0; top:0; width:100%; }
    .report-area .card-footer, .report-area .btn-print-hide { display:none !important; }
    .report-card { break-inside:avoid; page-break-after:always; box-shadow:none !important; border:1px solid #ddd !important; }
    .report-card:last-child { page-break-after:auto; }
    .form-card, .toolbar-area { display:none !important; }
}
</style>

<script>
var matchData = { home: '', away: '', sport: 0 };
var playerCache = {};

function onMatchChange(sel) {
    var opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    matchData.home = opt.dataset.home || '';
    matchData.away = opt.dataset.away || '';
    matchData.sport = parseInt(opt.dataset.sport) || 0;
    playerCache = {};
    loadTeamPlayers('home');
    loadTeamPlayers('away');
}

function loadTeamPlayers(team) {
    var countyName = team === 'home' ? matchData.home : matchData.away;
    if (!countyName || !matchData.sport) return;
    var key = countyName + '_' + matchData.sport;
    if (playerCache[key]) return;
    fetch('api_players.php?county_name=' + encodeURIComponent(countyName) + '&sport_id=' + matchData.sport)
        .then(function(r) { return r.json(); })
        .then(function(data) { playerCache[key] = data.players || []; })
        .catch(function() { playerCache[key] = []; });
}

function getPlayerOptions(team) {
    var countyName = team === 'home' ? matchData.home : matchData.away;
    var key = countyName + '_' + matchData.sport;
    var players = playerCache[key] || [];
    var html = '<option value="">-- Select Player --</option>';
    players.forEach(function(p) {
        html += '<option value="' + p.full_name.replace(/"/g, '&quot;') + '">' + p.full_name + ' (' + (p.primary_position || '-') + ')</option>';
    });
    html += '<option value="__custom">Other (type manually)</option>';
    return html;
}

function addSquadRow(team, type) {
    var tbody = document.getElementById('squad_' + team + '_' + type);
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><input type="number" name="sq_jersey[]" class="form-control form-control-sm" placeholder="No." min="1" max="99" style="width:50px;" required></td>' +
        '<td><select class="form-select form-select-sm squad-select" data-team="' + team + '" data-type="' + type + '">' + getPlayerOptions(team) + '</select></td>' +
        '<td class="text-end"><span class="remove-btn" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x-circle"></i></span></td>';
    tbody.appendChild(tr);
    tr.querySelector('.squad-select').addEventListener('change', function() { pickSquadPlayer(this); });
}

function pickSquadPlayer(sel) {
    var team = sel.dataset.team;
    var type = sel.dataset.type;
    var row = sel.closest('tr');
    var jersey = row.querySelector('input[type="number"]').value;
    var name = sel.value;
    if (!name) return;
    if (name === '__custom') {
        row.innerHTML =
            '<td><input type="number" name="sq_jersey[]" class="form-control form-control-sm" value="' + jersey + '" placeholder="No." min="1" max="99" style="width:50px;" required></td>' +
            '<td><input type="text" name="sq_name[]" class="form-control form-control-sm" placeholder="Type player name" required></td>' +
            '<td class="text-end"><span class="remove-btn" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x-circle"></i></span></td>' +
            '<input type="hidden" name="sq_team[]" value="' + team + '">' +
            '<input type="hidden" name="sq_type[]" value="' + type + '">';
    } else {
        row.innerHTML =
            '<td><input type="number" name="sq_jersey[]" class="form-control form-control-sm" value="' + jersey + '" placeholder="No." min="1" max="99" style="width:50px;" required></td>' +
            '<td><input type="hidden" name="sq_name[]" value="' + name.replace(/"/g, '&quot;') + '"><div class="form-control form-control-sm" style="background:#f8f9fa;">' + name + ' <small class="text-muted">[<a href="#" onclick="resetSquadRow(this,\'' + team + '\',\'' + type + '\');return false;">change</a>]</small></div></td>' +
            '<td class="text-end"><span class="remove-btn" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x-circle"></i></span></td>' +
            '<input type="hidden" name="sq_team[]" value="' + team + '">' +
            '<input type="hidden" name="sq_type[]" value="' + type + '">';
    }
}

function resetSquadRow(el, team, type) {
    var row = el.closest('tr');
    row.innerHTML =
        '<td><input type="number" name="sq_jersey[]" class="form-control form-control-sm" placeholder="No." min="1" max="99" style="width:50px;" required></td>' +
        '<td><select class="form-select form-select-sm squad-select" data-team="' + team + '" data-type="' + type + '">' + getPlayerOptions(team) + '</select></td>' +
        '<td class="text-end"><span class="remove-btn" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x-circle"></i></span></td>';
    row.querySelector('.squad-select').addEventListener('change', function() { pickSquadPlayer(this); });
}

function addCardRow(team) {
    var tbody = document.getElementById('cards_' + team);
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><input type="number" name="card_jersey[]" class="form-control form-control-sm" placeholder="No." min="1" max="99" style="width:50px;" required></td>' +
        '<td><select class="form-select form-select-sm card-select" data-team="' + team + '">' + getPlayerOptions(team) + '</select></td>' +
        '<td><select name="card_type[]" class="form-select form-select-sm" style="width:80px;"><option value="yellow">Yellow</option><option value="red">Red</option></select></td>' +
        '<td class="text-end"><span class="remove-btn" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x-circle"></i></span></td>';
    tbody.appendChild(tr);
    tr.querySelector('.card-select').addEventListener('change', function() { pickCardPlayer(this); });
}

function pickCardPlayer(sel) {
    var team = sel.dataset.team;
    var row = sel.closest('tr');
    var jersey = row.querySelector('input[type="number"]').value;
    var cardType = row.querySelector('select[name="card_type[]"]').value;
    var name = sel.value;
    if (!name) return;
    if (name === '__custom') {
        row.innerHTML =
            '<td><input type="number" name="card_jersey[]" class="form-control form-control-sm" value="' + jersey + '" placeholder="No." min="1" max="99" style="width:50px;" required></td>' +
            '<td><input type="text" name="card_name[]" class="form-control form-control-sm" placeholder="Type player name" required></td>' +
            '<td><select name="card_type[]" class="form-select form-select-sm" style="width:80px;"><option value="yellow"' + (cardType === 'yellow' ? ' selected' : '') + '>Yellow</option><option value="red"' + (cardType === 'red' ? ' selected' : '') + '>Red</option></select></td>' +
            '<td class="text-end"><span class="remove-btn" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x-circle"></i></span></td>' +
            '<input type="hidden" name="card_team[]" value="' + team + '">';
    } else {
        row.innerHTML =
            '<td><input type="number" name="card_jersey[]" class="form-control form-control-sm" value="' + jersey + '" placeholder="No." min="1" max="99" style="width:50px;" required></td>' +
            '<td><input type="hidden" name="card_name[]" value="' + name.replace(/"/g, '&quot;') + '"><div class="form-control form-control-sm" style="background:#f8f9fa;">' + name + ' <small class="text-muted">[<a href="#" onclick="resetCardRow(this,\'' + team + '\');return false;">change</a>]</small></div></td>' +
            '<td><select name="card_type[]" class="form-select form-select-sm" style="width:80px;"><option value="yellow"' + (cardType === 'yellow' ? ' selected' : '') + '>Yellow</option><option value="red"' + (cardType === 'red' ? ' selected' : '') + '>Red</option></select></td>' +
            '<td class="text-end"><span class="remove-btn" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x-circle"></i></span></td>' +
            '<input type="hidden" name="card_team[]" value="' + team + '">';
    }
}

function resetCardRow(el, team) {
    var row = el.closest('tr');
    row.innerHTML =
        '<td><input type="number" name="card_jersey[]" class="form-control form-control-sm" placeholder="No." min="1" max="99" style="width:50px;" required></td>' +
        '<td><select class="form-select form-select-sm card-select" data-team="' + team + '">' + getPlayerOptions(team) + '</select></td>' +
        '<td><select name="card_type[]" class="form-select form-select-sm" style="width:80px;"><option value="yellow">Yellow</option><option value="red">Red</option></select></td>' +
        '<td class="text-end"><span class="remove-btn" onclick="this.closest(\'tr\').remove()"><i class="bi bi-x-circle"></i></span></td>';
    row.querySelector('.card-select').addEventListener('change', function() { pickCardPlayer(this); });
}

document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('matchSelect');
    if (sel && sel.value) onMatchChange(sel);
});
</script>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Match Reports</h4>
    <div class="d-flex gap-2">
        <a href="report_csv.php" class="btn btn-sm btn-outline-success"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

<?= $msg ?>

<div class="row g-3">
    <div class="col-lg-6 toolbar-area">
        <div class="card shadow-sm form-card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0" style="font-weight:700;"><?= $editReport ? 'Edit' : 'New' ?> Match Report</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Match</label>
                        <select name="match_id" class="form-select form-select-sm" id="matchSelect" required <?= $editReport ? 'disabled' : '' ?> onchange="onMatchChange(this)">
                            <option value="">-- Select Match --</option>
                            <?php foreach ($matches as $m): ?>
                            <option value="<?= $m['id'] ?>" data-home="<?= sanitize($m['home_name']) ?>" data-away="<?= sanitize($m['away_name']) ?>" data-sport="<?= $m['sport_discipline_id'] ?>" <?= ($editReport && $editReport['match_id'] === $m['id']) ? 'selected' : '' ?>>
                                <?= sanitize($m['home_name']) ?> vs <?= sanitize($m['away_name']) ?> (<?= sanitize($m['sport_name']) ?> - <?= formatDate($m['match_date'], 'M d') ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($editReport): ?>
                        <input type="hidden" name="match_id" value="<?= $editReport['match_id'] ?>">
                        <?php endif; ?>
                    </div>

                    <!-- HOME TEAM SQUAD -->
                    <div class="form-section">
                        <h6><span class="badge bg-danger section-badge me-1">HOME</span> Starting XI</h6>
                        <table class="table squad-table mb-2">
                            <thead><tr><th style="width:50px;">No.</th><th>Player</th><th style="width:20px;"></th></tr></thead>
                            <tbody id="squad_home_starting">
                            <?php if ($editReport): foreach ($editSquad as $s): if ($s['team'] !== 'home' || $s['player_type'] !== 'starting') continue; ?>
                                <tr>
                                    <td><input type="number" name="sq_jersey[]" class="form-control form-control-sm" value="<?= (int)$s['jersey_number'] ?>" min="1" max="99" style="width:50px;" required></td>
                                    <td><input type="text" name="sq_name[]" class="form-control form-control-sm" value="<?= sanitize($s['player_name']) ?>" required></td>
                                    <td class="text-end"><span class="remove-btn" onclick="this.closest('tr').remove()"><i class="bi bi-x-circle"></i></span></td>
                                    <input type="hidden" name="sq_team[]" value="home"><input type="hidden" name="sq_type[]" value="starting">
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="addSquadRow('home','starting')"><i class="bi bi-plus-circle me-1"></i>Add Starting Player</button>
                    </div>

                    <div class="form-section">
                        <h6><span class="badge bg-danger section-badge me-1">HOME</span> Substitutes</h6>
                        <table class="table squad-table mb-2">
                            <thead><tr><th style="width:50px;">No.</th><th>Player</th><th style="width:20px;"></th></tr></thead>
                            <tbody id="squad_home_substitute">
                            <?php if ($editReport): foreach ($editSquad as $s): if ($s['team'] !== 'home' || $s['player_type'] !== 'substitute') continue; ?>
                                <tr>
                                    <td><input type="number" name="sq_jersey[]" class="form-control form-control-sm" value="<?= (int)$s['jersey_number'] ?>" min="1" max="99" style="width:50px;" required></td>
                                    <td><input type="text" name="sq_name[]" class="form-control form-control-sm" value="<?= sanitize($s['player_name']) ?>" required></td>
                                    <td class="text-end"><span class="remove-btn" onclick="this.closest('tr').remove()"><i class="bi bi-x-circle"></i></span></td>
                                    <input type="hidden" name="sq_team[]" value="home"><input type="hidden" name="sq_type[]" value="substitute">
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="addSquadRow('home','substitute')"><i class="bi bi-plus-circle me-1"></i>Add Substitute</button>
                    </div>

                    <!-- AWAY TEAM SQUAD -->
                    <div class="form-section">
                        <h6><span class="badge bg-primary section-badge me-1">AWAY</span> Starting XI</h6>
                        <table class="table squad-table mb-2">
                            <thead><tr><th style="width:50px;">No.</th><th>Player</th><th style="width:20px;"></th></tr></thead>
                            <tbody id="squad_away_starting">
                            <?php if ($editReport): foreach ($editSquad as $s): if ($s['team'] !== 'away' || $s['player_type'] !== 'starting') continue; ?>
                                <tr>
                                    <td><input type="number" name="sq_jersey[]" class="form-control form-control-sm" value="<?= (int)$s['jersey_number'] ?>" min="1" max="99" style="width:50px;" required></td>
                                    <td><input type="text" name="sq_name[]" class="form-control form-control-sm" value="<?= sanitize($s['player_name']) ?>" required></td>
                                    <td class="text-end"><span class="remove-btn" onclick="this.closest('tr').remove()"><i class="bi bi-x-circle"></i></span></td>
                                    <input type="hidden" name="sq_team[]" value="away"><input type="hidden" name="sq_type[]" value="starting">
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSquadRow('away','starting')"><i class="bi bi-plus-circle me-1"></i>Add Starting Player</button>
                    </div>

                    <div class="form-section">
                        <h6><span class="badge bg-primary section-badge me-1">AWAY</span> Substitutes</h6>
                        <table class="table squad-table mb-2">
                            <thead><tr><th style="width:50px;">No.</th><th>Player</th><th style="width:20px;"></th></tr></thead>
                            <tbody id="squad_away_substitute">
                            <?php if ($editReport): foreach ($editSquad as $s): if ($s['team'] !== 'away' || $s['player_type'] !== 'substitute') continue; ?>
                                <tr>
                                    <td><input type="number" name="sq_jersey[]" class="form-control form-control-sm" value="<?= (int)$s['jersey_number'] ?>" min="1" max="99" style="width:50px;" required></td>
                                    <td><input type="text" name="sq_name[]" class="form-control form-control-sm" value="<?= sanitize($s['player_name']) ?>" required></td>
                                    <td class="text-end"><span class="remove-btn" onclick="this.closest('tr').remove()"><i class="bi bi-x-circle"></i></span></td>
                                    <input type="hidden" name="sq_team[]" value="away"><input type="hidden" name="sq_type[]" value="substitute">
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSquadRow('away','substitute')"><i class="bi bi-plus-circle me-1"></i>Add Substitute</button>
                    </div>

                    <!-- CARDS -->
                    <div class="form-section">
                        <h6><i class="bi bi-exclamation-triangle me-1"></i> Cards Issued</h6>
                        <div class="row g-2 mb-2">
                            <div class="col-3"><input type="number" name="home_yellow_cards" class="form-control form-control-sm" value="<?= $editReport ? (int)$editReport['home_yellow_cards'] : 0 ?>" min="0" placeholder="HY"></div>
                            <div class="col-3"><input type="number" name="home_red_cards" class="form-control form-control-sm" value="<?= $editReport ? (int)$editReport['home_red_cards'] : 0 ?>" min="0" placeholder="HR"></div>
                            <div class="col-3"><input type="number" name="away_yellow_cards" class="form-control form-control-sm" value="<?= $editReport ? (int)$editReport['away_yellow_cards'] : 0 ?>" min="0" placeholder="AY"></div>
                            <div class="col-3"><input type="number" name="away_red_cards" class="form-control form-control-sm" value="<?= $editReport ? (int)$editReport['away_red_cards'] : 0 ?>" min="0" placeholder="AR"></div>
                            <div class="col-12"><small class="text-muted" style="font-size:0.6rem;">HY=Home Yellow, HR=Home Red, AY=Away Yellow, AR=Away Red</small></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-danger">HOME Carded Players</span>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="addCardRow('home')"><i class="bi bi-plus-circle me-1"></i>Add</button>
                        </div>
                        <table class="table squad-table mb-2">
                            <thead><tr><th style="width:50px;">No.</th><th>Player</th><th style="width:65px;">Card</th><th style="width:20px;"></th></tr></thead>
                            <tbody id="cards_home">
                            <?php if ($editReport): foreach ($editCards as $c): if ($c['team'] !== 'home') continue; ?>
                                <tr>
                                    <td><input type="number" name="card_jersey[]" class="form-control form-control-sm" value="<?= (int)$c['jersey_number'] ?>" min="1" max="99" style="width:50px;" required></td>
                                    <td><input type="text" name="card_name[]" class="form-control form-control-sm" value="<?= sanitize($c['player_name']) ?>" required></td>
                                    <td><select name="card_type[]" class="form-select form-select-sm" style="width:80px;"><option value="yellow" <?= $c['card_type']==='yellow'?'selected':'' ?>>Yellow</option><option value="red" <?= $c['card_type']==='red'?'selected':'' ?>>Red</option></select></td>
                                    <td class="text-end"><span class="remove-btn" onclick="this.closest('tr').remove()"><i class="bi bi-x-circle"></i></span></td>
                                    <input type="hidden" name="card_team[]" value="home">
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>

                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-primary">AWAY Carded Players</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addCardRow('away')"><i class="bi bi-plus-circle me-1"></i>Add</button>
                        </div>
                        <table class="table squad-table mb-2">
                            <thead><tr><th style="width:50px;">No.</th><th>Player</th><th style="width:65px;">Card</th><th style="width:20px;"></th></tr></thead>
                            <tbody id="cards_away">
                            <?php if ($editReport): foreach ($editCards as $c): if ($c['team'] !== 'away') continue; ?>
                                <tr>
                                    <td><input type="number" name="card_jersey[]" class="form-control form-control-sm" value="<?= (int)$c['jersey_number'] ?>" min="1" max="99" style="width:50px;" required></td>
                                    <td><input type="text" name="card_name[]" class="form-control form-control-sm" value="<?= sanitize($c['player_name']) ?>" required></td>
                                    <td><select name="card_type[]" class="form-select form-select-sm" style="width:80px;"><option value="yellow" <?= $c['card_type']==='yellow'?'selected':'' ?>>Yellow</option><option value="red" <?= $c['card_type']==='red'?'selected':'' ?>>Red</option></select></td>
                                    <td class="text-end"><span class="remove-btn" onclick="this.closest('tr').remove()"><i class="bi bi-x-circle"></i></span></td>
                                    <input type="hidden" name="card_team[]" value="away">
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes / Observations</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"><?= $editReport ? sanitize($editReport['notes'] ?? '') : '' ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100 py-2 fw-semibold">
                        <i class="bi bi-send me-1"></i><?= $editReport ? 'Update Report' : 'Submit Report' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6 report-area">
        <?php if (empty($reports)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <div style="font-size:3rem;color:#dee2e6;"><i class="bi bi-clipboard-data"></i></div>
                <h5 class="mt-2">No reports yet</h5>
                <p class="small">Select a match and submit your first report.</p>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($reports as $r):
                $cards = $db->fetchAll("SELECT * FROM match_report_cards WHERE report_id = ? ORDER BY team, card_type, jersey_number", [$r['id']]);
                $squad = $db->fetchAll("SELECT * FROM match_squad_players WHERE report_id = ? ORDER BY team, FIELD(player_type,'starting','substitute'), jersey_number", [$r['id']]);
            ?>
            <div class="card report-card shadow-sm mb-3">
                <div class="report-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong style="font-size:1rem;"><?= sanitize($r['home_name']) ?> <span class="mx-1" style="opacity:0.5;">vs</span> <?= sanitize($r['away_name']) ?></strong>
                        <br><small><?= sanitize($r['sport_name']) ?> &middot; <?= formatDate($r['match_date'], 'M d, Y') ?>
                        <?php if ($r['home_score'] !== null): ?> &middot; Score: <strong><?= (int)$r['home_score'] ?>-<?= (int)$r['away_score'] ?></strong><?php endif; ?></small>
                    </div>
                    <div class="text-end" style="flex-shrink:0;">
                        <small class="d-block" style="line-height:1.2;"><?= sanitize($r['commissioner_name']) ?></small>
                        <small style="font-size:0.6rem;opacity:0.6;"><?= formatDate($r['created_at'], 'M d, h:i A') ?></small>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="row g-0">
                        <!-- HOME COLUMN -->
                        <div class="col-6 squad-col" style="border-right:1px solid #eee;">
                            <span class="team-label" style="background:#dc3545;color:#fff;">HOME</span>
                            <?php
                            $homeStart = array_filter($squad, fn($s) => $s['team']==='home' && $s['player_type']==='starting');
                            $homeSub = array_filter($squad, fn($s) => $s['team']==='home' && $s['player_type']==='substitute');
                            $homeCards = array_filter($cards, fn($c) => $c['team']==='home');
                            ?>
                            <?php if (!empty($homeStart)): ?>
                            <div style="font-size:0.6rem;text-transform:uppercase;color:#6c757d;letter-spacing:0.05em;margin:0.4rem 0 0.2rem;">Starting XI</div>
                            <?php foreach ($homeStart as $s): ?>
                            <div class="player-row">
                                <span class="jersey-badge jersey-home"><?= (int)$s['jersey_number'] ?></span>
                                <span style="font-size:0.82rem;font-weight:500;"><?= sanitize($s['player_name']) ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($homeSub)): ?>
                            <div style="font-size:0.6rem;text-transform:uppercase;color:#6c757d;letter-spacing:0.05em;margin:0.6rem 0 0.2rem;">Substitutes</div>
                            <?php foreach ($homeSub as $s): ?>
                            <div class="player-row">
                                <span class="jersey-badge jersey-home" style="background:#b03a3a;"><?= (int)$s['jersey_number'] ?></span>
                                <span style="font-size:0.82rem;font-weight:500;"><?= sanitize($s['player_name']) ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (empty($homeStart) && empty($homeSub)): ?>
                            <p class="text-muted small" style="font-style:italic;padding:0.5rem 0;">Squad not entered.</p>
                            <?php endif; ?>
                            <?php if (!empty($homeCards)): ?>
                            <div style="font-size:0.6rem;text-transform:uppercase;color:#6c757d;letter-spacing:0.05em;margin:0.6rem 0 0.2rem;border-top:1px solid #eee;padding-top:0.5rem;">Cards &mdash; Yellow: <?= (int)$r['home_yellow_cards'] ?>, Red: <?= (int)$r['home_red_cards'] ?></div>
                            <?php foreach ($homeCards as $c): ?>
                            <div class="player-row">
                                <span class="jersey-badge jersey-home" style="width:26px;height:26px;font-size:0.6rem;"><?= (int)$c['jersey_number'] ?></span>
                                <span style="font-size:0.78rem;"><?= sanitize($c['player_name']) ?></span>
                                <span class="ms-auto small fw-bold" style="color:<?= $c['card_type']==='yellow' ? '#b8860b' : '#dc3545' ?>;"><?= strtoupper($c['card_type']) ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- AWAY COLUMN -->
                        <div class="col-6 squad-col">
                            <span class="team-label" style="background:#0d6efd;color:#fff;">AWAY</span>
                            <?php
                            $awayStart = array_filter($squad, fn($s) => $s['team']==='away' && $s['player_type']==='starting');
                            $awaySub = array_filter($squad, fn($s) => $s['team']==='away' && $s['player_type']==='substitute');
                            $awayCards = array_filter($cards, fn($c) => $c['team']==='away');
                            ?>
                            <?php if (!empty($awayStart)): ?>
                            <div style="font-size:0.6rem;text-transform:uppercase;color:#6c757d;letter-spacing:0.05em;margin:0.4rem 0 0.2rem;">Starting XI</div>
                            <?php foreach ($awayStart as $s): ?>
                            <div class="player-row">
                                <span class="jersey-badge jersey-away"><?= (int)$s['jersey_number'] ?></span>
                                <span style="font-size:0.82rem;font-weight:500;"><?= sanitize($s['player_name']) ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($awaySub)): ?>
                            <div style="font-size:0.6rem;text-transform:uppercase;color:#6c757d;letter-spacing:0.05em;margin:0.6rem 0 0.2rem;">Substitutes</div>
                            <?php foreach ($awaySub as $s): ?>
                            <div class="player-row">
                                <span class="jersey-badge jersey-away" style="background:#5a6fba;"><?= (int)$s['jersey_number'] ?></span>
                                <span style="font-size:0.82rem;font-weight:500;"><?= sanitize($s['player_name']) ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (empty($awayStart) && empty($awaySub)): ?>
                            <p class="text-muted small" style="font-style:italic;padding:0.5rem 0;">Squad not entered.</p>
                            <?php endif; ?>
                            <?php if (!empty($awayCards)): ?>
                            <div style="font-size:0.6rem;text-transform:uppercase;color:#6c757d;letter-spacing:0.05em;margin:0.6rem 0 0.2rem;border-top:1px solid #eee;padding-top:0.5rem;">Cards &mdash; Yellow: <?= (int)$r['away_yellow_cards'] ?>, Red: <?= (int)$r['away_red_cards'] ?></div>
                            <?php foreach ($awayCards as $c): ?>
                            <div class="player-row">
                                <span class="jersey-badge jersey-away" style="width:26px;height:26px;font-size:0.6rem;"><?= (int)$c['jersey_number'] ?></span>
                                <span style="font-size:0.78rem;"><?= sanitize($c['player_name']) ?></span>
                                <span class="ms-auto small fw-bold" style="color:<?= $c['card_type']==='yellow' ? '#b8860b' : '#dc3545' ?>;"><?= strtoupper($c['card_type']) ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($r['notes'])): ?>
                    <div class="px-3 py-2 border-top">
                        <small class="text-muted fw-semibold">Notes:</small>
                        <p class="small mb-0 text-muted" style="white-space:pre-wrap;"><?= sanitize($r['notes']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer bg-white py-2 text-end btn-print-hide" style="border-top:1px solid #eee;">
                    <a href="report_pdf.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" target="_blank"><i class="bi bi-filetype-pdf me-1"></i>PDF</a>
                    <?php if ($_SESSION['user_id'] === $r['commissioner_id'] || hasRole('super_admin')): ?>
                    <a href="?edit=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-pencil me-1"></i>Edit</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
