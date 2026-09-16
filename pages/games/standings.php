<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = getDb();

$sportFilterId = hasRole('association_admin') ? (int)$_SESSION['user_association_id'] : null;

$selectedSport = isset($_GET['sport']) ? (int)$_GET['sport'] : $sportFilterId;
$selectedGroup = isset($_GET['group']) ? strtoupper($_GET['group']) : null;
if ($selectedGroup && !in_array($selectedGroup, ['A','B','C','D'])) $selectedGroup = null;

$isKickball = ($selectedSport == 2);

function getStandingsData($db, $sportId = null, $groupLabel = null) {
    $where = ["m.status = 'completed'"];
    $params = [];
    if ($sportId) { $where[] = "m.sport_discipline_id = ?"; $params[] = $sportId; }
    if ($groupLabel) { $where[] = "m.group_label = ?"; $params[] = $groupLabel; }
    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    $rows = $db->fetchAll(
        "SELECT m.home_county_id, m.away_county_id, m.home_score, m.away_score, m.group_label, m.sport_discipline_id,
                c1.name as home_name, c2.name as away_name
         FROM matches m
         JOIN counties c1 ON m.home_county_id = c1.id
         JOIN counties c2 ON m.away_county_id = c2.id
         $whereSql
         ORDER BY m.group_label, m.match_date",
        $params
    );

    $isKickball = ($sportId == 2);

    $teams = [];
    foreach ($rows as $r) {
        foreach ([
            ['id' => $r['home_county_id'], 'name' => $r['home_name'], 'group' => $r['group_label'], 'gf' => (int)$r['home_score'], 'ga' => (int)$r['away_score']],
            ['id' => $r['away_county_id'], 'name' => $r['away_name'], 'group' => $r['group_label'], 'gf' => (int)$r['away_score'], 'ga' => (int)$r['home_score']]
        ] as $t) {
            $tid = $t['id'];
            if (!isset($teams[$tid])) {
                $teams[$tid] = ['name' => $t['name'], 'group' => $t['group'], 'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'gf' => 0, 'ga' => 0, 'gd' => 0, 'pts' => 0];
            }
            $teams[$tid]['played']++;
            $teams[$tid]['gf'] += $t['gf'];
            $teams[$tid]['ga'] += $t['ga'];
            $teams[$tid]['gd'] = $teams[$tid]['gf'] - $teams[$tid]['ga'];
            if ($t['gf'] > $t['ga']) { $teams[$tid]['wins']++; $teams[$tid]['pts'] += 3; }
            elseif ($t['gf'] === $t['ga']) { $teams[$tid]['draws']++; $teams[$tid]['pts'] += 1; }
            else { $teams[$tid]['losses']++; }
        }
    }

    uksort($teams, function($a, $b) use ($teams) {
        if ($teams[$a]['pts'] !== $teams[$b]['pts']) return $teams[$b]['pts'] - $teams[$a]['pts'];
        if ($teams[$a]['gd'] !== $teams[$b]['gd']) return $teams[$b]['gd'] - $teams[$a]['gd'];
        return $teams[$b]['gf'] - $teams[$a]['gf'];
    });

    return $teams;
}

$sports = getSports();

$groupsToShow = $selectedGroup ? [$selectedGroup] : ['A','B','C','D'];

$pageTitle = $selectedSport ? 'Standings' : 'All Standings';
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<style>
.standing-wrapper {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 16px;
    padding: 1.5rem;
}
.standing-table {
    border-collapse: separate;
    border-spacing: 0 2px;
}
.standing-table thead th {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6c757d;
    border: none;
    padding: 0.75rem 0.5rem;
    background: transparent;
}
.standing-table tbody td {
    vertical-align: middle;
    padding: 0.6rem 0.5rem;
    border: none;
    background: #fff;
}
.standing-table tbody tr td:first-child {
    border-radius: 8px 0 0 8px;
}
.standing-table tbody tr td:last-child {
    border-radius: 0 8px 8px 0;
}
.standing-table tbody tr {
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: transform 0.15s, box-shadow 0.15s;
}
.standing-table tbody tr:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
}
.standing-table .pos-1 td { background: linear-gradient(90deg, #fff7e6, #fff); }
.standing-table .pos-2 td { background: linear-gradient(90deg, #f0f4ff, #fff); }
.standing-table .pos-3 td { background: linear-gradient(90deg, #fff0f0, #fff); }
.standing-table .pos-bottom td { background: #f8f9fa; color: #adb5bd; }
.standing-table .team-col { min-width: 180px; font-weight: 600; font-size: 0.9rem; }
.standing-table .stat-col { width: 36px; font-size: 0.8rem; color: #495057; }
.pts-cell {
    font-size: 1.15rem;
    font-weight: 800;
}
.pts-cell-default { color: #1a237e; }
.pts-cell-kickball { color: #dc3545; }
.gd-positive { color: #28a745; font-weight: 600; }
.gd-negative { color: #dc3545; font-weight: 600; }
.gd-zero { color: #6c757d; }
.standing-header {
    color: #fff;
    border-radius: 12px 12px 0 0;
    padding: 1rem 1.25rem;
}
.standing-header-default { background: linear-gradient(135deg, #1a237e, #283593); }
.standing-header-kickball { background: linear-gradient(135deg, #dc3545, #c82333); }
.standing-header h5 { margin: 0; font-weight: 700; letter-spacing: 0.03em; }
.standing-header small { opacity: 0.8; }
.position-indicator {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 700;
}
.pos-1 .position-indicator { background: linear-gradient(135deg, #ffd700, #ffb300); color: #5c4100; }
.pos-2 .position-indicator { background: linear-gradient(135deg, #e0e0e0, #bdbdbd); color: #424242; }
.pos-3 .position-indicator { background: linear-gradient(135deg, #cd7f32, #b8712a); color: #fff; }
.pos-default .position-indicator { background: #e9ecef; color: #6c757d; }
.team-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-size: 0.6rem;
    font-weight: 700;
    color: #fff;
    margin-right: 8px;
    flex-shrink: 0;
}
.badge-A { background: #dc3545; }
.badge-B { background: #0d6efd; }
.badge-C { background: #198754; }
.badge-D { background: #ffc107; color: #5c4100; }
.form-filter {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    font-size: 0.85rem;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.15s;
}
.form-filter:focus { border-color: #1a237e; outline: none; box-shadow: 0 0 0 2px rgba(26,35,126,0.15); }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0" style="font-weight:700;color:<?= $isKickball ? '#dc3545' : '#1a237e' ?>;"><i class="bi bi-trophy me-2" style="color:#ffc107;"></i><?= $isKickball ? 'Kickball Standings' : 'Standings' ?></h4>
        <small class="text-muted">League tables across all groups</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="<?= APP_URL ?>pages/games/index.php" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="bi bi-broadcast me-1"></i>Live Scores</a>
        <?php if (hasRole(['super_admin'])): ?>
        <a href="<?= APP_URL ?>pages/games/manage.php" class="btn btn-primary btn-sm rounded-pill px-3"><i class="bi bi-gear me-1"></i>Manage</a>
        <?php endif; ?>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <?php if (!hasRole('association_admin')): ?>
    <div class="col-auto">
        <select name="sport" class="form-filter" onchange="this.form.submit()">
            <option value="">All Sports</option>
            <?php foreach ($sports as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $selectedSport === $s['id'] ? 'selected' : '' ?>><?= sanitize($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php else: ?>
    <div class="col-auto">
        <span class="badge rounded-pill px-3 py-2" style="background:#1a237e;font-size:0.85rem;"><?= sanitize($sports[0]['name'] ?? '') ?></span>
    </div>
    <?php endif; ?>
    <div class="col-auto">
        <select name="group" class="form-filter" onchange="this.form.submit()">
            <option value="">All Groups</option>
            <?php foreach (['A','B','C','D'] as $g): ?>
            <option value="<?= $g ?>" <?= $selectedGroup === $g ? 'selected' : '' ?>>Group <?= $g ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="standing-wrapper">
<?php 
$anyStandings = false;

$sportsToShow = $selectedSport ? [$selectedSport] : array_column($sports, 'id');

foreach ($sportsToShow as $sportId):
    $sportRow = $db->fetchOne("SELECT name FROM sports_disciplines WHERE id = ?", [$sportId]);
    $sportName = $sportRow['name'] ?? 'Sport';
    $sportIsKickball = ($sportId == 2);

    $hasSportStandings = false;
    foreach ($groupsToShow as $g):
        $standings = getStandingsData($db, $sportId, $g);
        if (!empty($standings)) $hasSportStandings = true;
    endforeach;

    if (!$hasSportStandings) continue;
    $anyStandings = true;
?>
<div class="mb-4">
    <div class="d-flex align-items-center mb-3">
        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $sportIsKickball ? '#dc3545' : '#1a237e' ?>;margin-right:8px;"></span>
        <h4 class="mb-0 fw-bold" style="color:<?= $sportIsKickball ? '#dc3545' : '#1a237e' ?>;"><?= sanitize($sportName) ?> Standings</h4>
    </div>
<?php 
    foreach ($groupsToShow as $g): 
        $standings = getStandingsData($db, $sportId, $g);
        if (empty($standings)) continue;
        $totalTeams = count($standings);
        $rank = 0;
?>
<div class="card mb-4 border-0 shadow-sm">
    <div class="standing-header <?= $sportIsKickball ? 'standing-header-kickball' : 'standing-header-default' ?> d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><span class="badge rounded-pill bg-white text-dark me-2" style="font-size:0.7rem;">Group</span> Group <?= $g ?></h5>
        <small><?= $totalTeams ?> teams</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table standing-table mb-0">
                <thead>
                    <tr>
                        <th style="width:36px;">#</th>
                        <th class="team-col">Team</th>
                        <th class="text-center stat-col"><?= $sportIsKickball ? 'GP' : 'P' ?></th>
                        <th class="text-center stat-col">W</th>
                        <th class="text-center stat-col">D</th>
                        <th class="text-center stat-col">L</th>
                        <th class="text-center stat-col"><?= $sportIsKickball ? 'HRF' : 'F' ?></th>
                        <th class="text-center stat-col"><?= $sportIsKickball ? 'HRA' : 'A' ?></th>
                        <th class="text-center" style="width:44px;"><?= $sportIsKickball ? 'HRD' : 'GD' ?></th>
                        <th class="text-center" style="width:56px;">PTS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($standings as $tid => $s): $rank++; ?>
                    <tr class="<?= $rank <= 3 ? 'pos-'.$rank : ($rank === $totalTeams ? 'pos-bottom' : '') ?>">
                        <td class="text-center">
                            <span class="position-indicator <?= $rank <= 3 ? '' : 'pos-default' ?>"><?= $rank ?></span>
                        </td>
                        <td class="team-col">
                            <span class="team-badge badge-<?= $s['group'] ?>"><?= $s['group'] ?></span>
                            <?php $flagUrl = getCountyFlagUrl($s['name']); ?>
                            <?php if ($flagUrl): ?>
                            <img src="<?= $flagUrl ?>" alt="" style="width:20px;height:20px;object-fit:contain;border-radius:50%;margin-right:6px;vertical-align:middle;">
                            <?php endif; ?>
                            <?= sanitize($s['name']) ?>
                        </td>
                        <td class="text-center stat-col"><?= $s['played'] ?></td>
                        <td class="text-center stat-col"><?= $s['wins'] ?></td>
                        <td class="text-center stat-col"><?= $s['draws'] ?></td>
                        <td class="text-center stat-col"><?= $s['losses'] ?></td>
                        <td class="text-center stat-col"><strong><?= $s['gf'] ?></strong></td>
                        <td class="text-center stat-col"><?= $s['ga'] ?></td>
                        <td class="text-center <?= $s['gd'] > 0 ? 'gd-positive' : ($s['gd'] < 0 ? 'gd-negative' : 'gd-zero') ?>"><?= $s['gd'] > 0 ? '+' : '' ?><?= $s['gd'] ?></td>
                        <td class="text-center pts-cell <?= $sportIsKickball ? 'pts-cell-kickball' : 'pts-cell-default' ?>"><?= $s['pts'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (!$anyStandings): ?>
<div class="text-center py-5">
    <div style="font-size:4rem;color:#dee2e6;"><i class="bi bi-trophy"></i></div>
    <h5 class="mt-3 text-muted">No standings yet</h5>
    <p class="text-muted small">Standings will appear once match scores are entered and completed.</p>
</div>
<?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
