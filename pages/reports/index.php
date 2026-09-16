<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = getDb();

$reportType = $_GET['type'] ?? 'overview';
$format = $_GET['format'] ?? 'html';

$sportFilter = hasRole('association_admin') ? " AND p.sport_discipline_id = " . (int)$_SESSION['user_association_id'] : "";
$sportFilterShort = hasRole('association_admin') ? " WHERE sport_discipline_id = " . (int)$_SESSION['user_association_id'] : "";
$groupFilter = isAdminRole() ? $_SESSION['user_group_label'] : "";
$groupFilterWhere = $groupFilter ? " WHERE c.group_label = '" . $groupFilter . "'" : "";

$countyStats = $db->fetchAll(
    "SELECT c.name, c.group_label, c.code,
            COUNT(p.id) as total_players,
            SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN p.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN p.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) as draft
     FROM counties c
     LEFT JOIN players p ON c.id = p.county_id" . ($sportFilter ? " $sportFilter" : "") .
     ($groupFilterWhere ? " $groupFilterWhere" : "") .
     " GROUP BY c.id, c.name, c.group_label, c.code
     ORDER BY c.group_label, c.name"
);

$sportStats = $db->fetchAll(
    "SELECT s.name, s.association_name, s.association_code,
            COUNT(p.id) as total_players,
            SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN p.status = 'submitted' THEN 1 ELSE 0 END) as submitted
     FROM sports_disciplines s
     LEFT JOIN players p ON s.id = p.sport_discipline_id" . ($sportFilter ? " $sportFilter" : "") .
     ($groupFilter ? " LEFT JOIN counties c ON p.county_id = c.id AND c.group_label = '" . $groupFilter . "'" : "") .
     " GROUP BY s.id, s.name, s.association_name, s.association_code
     ORDER BY s.name"
);

$groupStats = $db->fetchAll(
    "SELECT c.group_label,
            COUNT(DISTINCT c.id) as county_count,
            COUNT(p.id) as total_players,
            SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as approved
     FROM counties c
     LEFT JOIN players p ON c.id = p.county_id" . ($sportFilter ? " $sportFilter" : "") .
     ($groupFilterWhere ? " $groupFilterWhere" : "") .
     " GROUP BY c.group_label
     ORDER BY c.group_label"
);

$monthFilterAssoc = hasRole('association_admin') ? " WHERE p.sport_discipline_id = " . (int)$_SESSION['user_association_id'] : "";
$monthRegistrations = $db->fetchAll(
    "SELECT DATE_FORMAT(p.created_at, '%Y-%m') as month, COUNT(*) as count
     FROM players p" .
     ($groupFilter ? " JOIN counties c ON p.county_id = c.id AND c.group_label = '" . $groupFilter . "'" : "") .
     ($monthFilterAssoc ? " $monthFilterAssoc" : "") .
     " GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
     ORDER BY month DESC LIMIT 12"
);

$pageTitle = 'Reports';
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $reportType === 'overview' ? 'active' : '' ?>" href="?type=overview">Overview</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType === 'by_county' ? 'active' : '' ?>" href="?type=by_county">By County</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType === 'by_sport' ? 'active' : '' ?>" href="?type=by_sport">By Sport</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType === 'by_group' ? 'active' : '' ?>" href="?type=by_group">By Group</a>
    </li>
</ul>

<?php if ($reportType === 'overview'): ?>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between">
                <h5 class="mb-0">County Registration Summary</h5>
                <a href="?type=by_county&format=csv" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> CSV</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>County</th>
                                <th>Group</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Approved</th>
                                <th class="text-center">Pending</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($countyStats as $row): ?>
                            <tr>
                                <td><?= sanitize($row['name']) ?></td>
                                <td><span class="group-badge group-<?= $row['group_label'] ?>" style="width:24px;height:24px;line-height:24px;font-size:0.7rem"><?= $row['group_label'] ?></span></td>
                                <td class="text-center"><?= $row['total_players'] ?></td>
                                <td class="text-center text-success"><?= $row['approved'] ?></td>
                                <td class="text-center text-warning"><?= $row['submitted'] + $row['draft'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between">
                <h5 class="mb-0">Sport Registration Summary</h5>
                <a href="?type=by_sport&format=csv" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> CSV</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Sport</th>
                                <th>Association</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Approved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sportStats as $row): ?>
                            <tr>
                                <td><strong><?= sanitize($row['name']) ?></strong></td>
                                <td><small class="text-muted"><?= sanitize($row['association_name']) ?></small></td>
                                <td class="text-center"><?= $row['total_players'] ?></td>
                                <td class="text-center text-success"><?= $row['approved'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($reportType === 'by_county'): ?>
    <?php if ($format === 'csv'): ?>
        <?php
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="county_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['County', 'Group', 'Total Players', 'Approved', 'Submitted', 'Rejected', 'Draft']);
        foreach ($countyStats as $row) {
            fputcsv($output, [$row['name'], $row['group_label'], $row['total_players'], $row['approved'], $row['submitted'], $row['rejected'], $row['draft']]);
        }
        fclose($output);
        exit;
        ?>
    <?php endif; ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>County</th><th>Group</th><th>Code</th><th>Total</th><th>Approved</th><th>Submitted</th><th>Rejected</th><th>Draft</th></tr></thead>
                    <tbody>
                        <?php foreach ($countyStats as $row): ?>
                        <tr>
                            <td><strong><?= sanitize($row['name']) ?></strong></td>
                            <td><span class="group-badge group-<?= $row['group_label'] ?>" style="width:24px;height:24px;line-height:24px;font-size:0.7rem"><?= $row['group_label'] ?></span></td>
                            <td><code><?= $row['code'] ?></code></td>
                            <td><?= $row['total_players'] ?></td>
                            <td class="text-success"><?= $row['approved'] ?></td>
                            <td class="text-info"><?= $row['submitted'] ?></td>
                            <td class="text-danger"><?= $row['rejected'] ?></td>
                            <td class="text-muted"><?= $row['draft'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($reportType === 'by_sport'): ?>
    <?php if ($format === 'csv'): ?>
        <?php
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sport_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Sport', 'Association', 'Code', 'Total Players', 'Approved', 'Submitted']);
        foreach ($sportStats as $row) {
            fputcsv($output, [$row['name'], $row['association_name'], $row['association_code'], $row['total_players'], $row['approved'], $row['submitted']]);
        }
        fclose($output);
        exit;
        ?>
    <?php endif; ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Sport</th><th>Association</th><th>Code</th><th>Total</th><th>Approved</th><th>Submitted</th></tr></thead>
                    <tbody>
                        <?php foreach ($sportStats as $row): ?>
                        <tr>
                            <td><strong><?= sanitize($row['name']) ?></strong></td>
                            <td><?= sanitize($row['association_name']) ?></td>
                            <td><code><?= $row['association_code'] ?></code></td>
                            <td><?= $row['total_players'] ?></td>
                            <td class="text-success"><?= $row['approved'] ?></td>
                            <td class="text-info"><?= $row['submitted'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($reportType === 'by_group'): ?>
    <div class="row g-3">
        <?php foreach ($groupStats as $row): ?>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <span class="group-badge group-<?= $row['group_label'] ?> mb-2" style="width:48px;height:48px;line-height:48px;font-size:1.2rem"><?= $row['group_label'] ?></span>
                    <h5>Group <?= $row['group_label'] ?></h5>
                    <p class="mb-1"><strong><?= $row['county_count'] ?></strong> Counties</p>
                    <p class="mb-1"><strong><?= $row['total_players'] ?></strong> Total Players</p>
                    <p class="mb-0 text-success"><strong><?= $row['approved'] ?></strong> Approved</p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
