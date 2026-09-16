<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['association_admin']);

$db = getDb();

$whereExtra = '';
$params = [];
if (!hasRole('super_admin')) {
    $whereExtra = "AND p.sport_discipline_id = ?";
    $params[] = $_SESSION['user_association_id'];
}

$page = max(1, (int)($_GET['page'] ?? 1));
$total = $db->fetchOne("SELECT COUNT(*) as c FROM players p WHERE p.status IN ('approved','rejected') $whereExtra", $params)['c'];
$pagination = paginate($total, $page, 20);

$history = $db->fetchAll(
    "SELECT p.*, c.name as county_name, c.group_label, s.name as sport_name, s.association_name,
            u.full_name as registered_by_name,
            (SELECT action FROM approval_workflow WHERE player_id = p.id ORDER BY created_at DESC LIMIT 1) as last_action,
            (SELECT comments FROM approval_workflow WHERE player_id = p.id ORDER BY created_at DESC LIMIT 1) as last_comment,
            (SELECT full_name FROM users JOIN approval_workflow ON users.id = approval_workflow.action_by WHERE approval_workflow.player_id = p.id ORDER BY approval_workflow.created_at DESC LIMIT 1) as reviewed_by
     FROM players p
     JOIN counties c ON p.county_id = c.id
     JOIN sports_disciplines s ON p.sport_discipline_id = s.id
     JOIN users u ON p.registered_by = u.id
     WHERE p.status IN ('approved','rejected') $whereExtra
     ORDER BY p.updated_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['perPage'], $pagination['offset']])
);

$pageTitle = 'Approval History';
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>County</th>
                        <th>Sport</th>
                        <th>Status</th>
                        <th>Reviewed By</th>
                        <th>Comments</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No processed approvals yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td><a href="<?= APP_URL ?>pages/players/view.php?id=<?= $h['id'] ?>"><?= sanitize($h['full_name']) ?></a></td>
                            <td><span class="group-badge group-<?= $h['group_label'] ?>" style="width:22px;height:22px;line-height:22px;font-size:0.65rem"><?= $h['group_label'] ?></span> <?= sanitize($h['county_name']) ?></td>
                            <td><small><?= sanitize($h['sport_name']) ?></small></td>
                            <td><?= getStatusBadge($h['status']) ?></td>
                            <td><small><?= sanitize($h['reviewed_by'] ?? 'N/A') ?></small></td>
                            <td><small class="text-muted"><?= sanitize($h['last_comment'] ?? '') ?></small></td>
                            <td><small class="text-muted"><?= timeAgo($h['updated_at']) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['totalPages'] > 1): ?>
    <div class="card-footer bg-white">
        <nav><ul class="pagination pagination-sm justify-content-center mb-0">
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
