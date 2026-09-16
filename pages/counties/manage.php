<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin']);

$db = getDb();
$counties = getCounties();
$groups = getAllGroups();

$groupTotals = [];
foreach (['A', 'B', 'C', 'D'] as $g) {
    $groupTotals[$g] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM players p JOIN counties c ON p.county_id = c.id WHERE c.group_label = ?",
        [$g]
    )['count'];
}

$pageTitle = 'County Management';
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<?php foreach (['A', 'B', 'C', 'D'] as $g): ?>
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <span class="group-badge group-<?= $g ?> me-2"><?= $g ?></span>
            Group <?= $g ?>
        </h5>
        <span class="badge bg-primary"><?= $groupTotals[$g] ?? 0 ?> Player(s)</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Flag</th>
                    <th>County</th>
                    <th>Code</th>
                    <th>Players Registered</th>
                    <th>Approved</th>
                    <th>Pending</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups[$g] as $county): ?>
                <?php
                    $stats = $db->fetchOne(
                        "SELECT
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                            SUM(CASE WHEN status IN ('submitted','draft') THEN 1 ELSE 0 END) as pending
                         FROM players WHERE county_id = ?",
                        [$county['id']]
                    );
                    $flagUrl = getCountyFlagUrl($county['name']);
                ?>
                <tr>
                    <td><?php if ($flagUrl): ?><img src="<?= $flagUrl ?>" alt="<?= sanitize($county['name']) ?>" style="width:40px;height:28px;object-fit:cover;border-radius:3px;border:1px solid #ddd;"><?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
                    <td><strong><?= sanitize($county['name']) ?></strong></td>
                    <td><code><?= sanitize($county['code']) ?></code></td>
                    <td><?= (int)$stats['total'] ?></td>
                    <td><?= (int)$stats['approved'] ?></td>
                    <td><?= (int)$stats['pending'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<div class="card">
    <div class="card-header bg-white"><h5 class="mb-0">County Groupings Reference</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="p-3 rounded bg-danger bg-opacity-10">
                    <h6 class="text-danger">Group A</h6>
                    <small>Gbarpolu, <strong>Grand Gedeh (Host)</strong>, Nimba, River Gee</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 rounded bg-primary bg-opacity-10">
                    <h6 class="text-primary">Group B</h6>
                    <small><strong>Bong (Host)</strong>, Grand Cape Mount, Maryland, River Cess</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 rounded" style="background:rgba(200,160,50,0.1);">
                    <h6 style="color:#C8A032;">Group C</h6>
                    <small>Grand Bassa, <strong>Lofa (Host)</strong>, Montserrado, Sinoe</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 rounded" style="background:rgba(27,94,32,0.1);">
                    <h6 style="color:#1B5E20;">Group D</h6>
                    <small>Margibi, <strong>Grand Kru (Host)</strong>, Bomi</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
