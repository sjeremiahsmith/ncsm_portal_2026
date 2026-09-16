<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = getDb();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$search = trim($_GET['search'] ?? '');
$group = $_GET['group'] ?? '';
$sport_id = (int)($_GET['sport_id'] ?? 0);
$status = $_GET['status'] ?? '';
$county_id = (int)($_GET['county_id'] ?? 0);

$where = [];
$params = [];

if ($search) {
    $where[] = "p.full_name LIKE ?";
    $params[] = "%$search%";
}
if ($group) {
    $where[] = "c.group_label = ?";
    $params[] = $group;
}
if ($sport_id > 0) {
    $where[] = "p.sport_discipline_id = ?";
    $params[] = $sport_id;
}
if ($status) {
    $where[] = "p.status = ?";
    $params[] = $status;
}
if ($county_id > 0) {
    $where[] = "p.county_id = ?";
    $params[] = $county_id;
}

if (isAdminRole()) {
    $where[] = "c.group_label = ?";
    $params[] = $_SESSION['user_group_label'];
}

if (hasRole(['association_admin'])) {
    $where[] = "p.sport_discipline_id = ?";
    $params[] = $_SESSION['user_association_id'];
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) as total FROM players p JOIN counties c ON p.county_id = c.id $whereClause";
$total = $db->fetchOne($countSql, $params)['total'];
$pagination = paginate($total, $page, $perPage);

$players = $db->fetchAll(
    "SELECT p.*, c.name as county_name, c.group_label, s.name as sport_name, s.association_name,
            u.full_name as registered_by_name
     FROM players p
     JOIN counties c ON p.county_id = c.id
     JOIN sports_disciplines s ON p.sport_discipline_id = s.id
     JOIN users u ON p.registered_by = u.id
     $whereClause
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['perPage'], $pagination['offset']])
);

$counties = getCounties();
$sports = getSports();

$pageTitle = 'All Players';
$pageActions = '<a href="' . APP_URL . 'pages/players/register.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Register Player</a>';
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<div class="filter-section">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-4">
            <label class="form-label small">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Name..." value="<?= sanitize($search) ?>">
            </div>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label small">Group</label>
            <select name="group" class="form-select form-select-sm" data-auto-submit>
                <option value="">All Groups</option>
                <?php foreach (['A', 'B', 'C', 'D'] as $g): ?>
                    <option value="<?= $g ?>" <?= $group === $g ? 'selected' : '' ?>>Group <?= $g ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label small">Sport</label>
            <select name="sport_id" class="form-select form-select-sm" data-auto-submit>
                <option value="">All Sports</option>
                <?php foreach ($sports as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $sport_id === $s['id'] ? 'selected' : '' ?>><?= sanitize($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm" data-auto-submit>
                <option value="">All Status</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label small">County</label>
            <select name="county_id" class="form-select form-select-sm" data-auto-submit>
                <option value="">All Counties</option>
                <?php foreach ($counties as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $county_id === $c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-1 col-md-2">
            <a href="<?= APP_URL ?>pages/players/list.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:50px">Photo</th>
                        <th>Full Name</th>
                        <th>County</th>
                        <th>Sport</th>
                        <th>Level</th>
                        <th>Status</th>
                        <th>Registered By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($players)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No players found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($players as $p): ?>
                        <tr class="row-<?= $p['status'] ?>">
                            <td>
                                <img src="<?= getPlayerPhotoUrl($p['photo_path']) ?>" class="player-photo" alt="">
                            </td>
                            <td><a href="<?= APP_URL ?>pages/players/view.php?id=<?= $p['id'] ?>" class="text-decoration-none fw-medium"><?= sanitize($p['full_name']) ?></a></td>
                            <td><span class="group-badge group-<?= $p['group_label'] ?>"><?= $p['group_label'] ?></span> <?= sanitize($p['county_name']) ?></td>
                            <td><small><?= sanitize($p['sport_name']) ?></small></td>
                            <td><small><?= sanitize($p['primary_position']) ?></small></td>
                            <td><?= getStatusBadge($p['status']) ?></td>
                            <td><small class="text-muted"><?= sanitize($p['registered_by_name']) ?></small></td>
                            <td class="text-end">
                                <a href="<?= APP_URL ?>pages/players/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                <?php if (!hasRole('association_admin') && (hasRole('super_admin') || ($p['status'] === 'draft' && $_SESSION['user_id'] === $p['registered_by']))): ?>
                                    <a href="<?= APP_URL ?>pages/players/edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['totalPages'] > 1): ?>
    <div class="card-footer bg-white">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $pagination['page'] - 1 ?>&search=<?= urlencode($search) ?>&group=<?= $group ?>&sport_id=<?= $sport_id ?>&status=<?= $status ?>&county_id=<?= $county_id ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                    <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&group=<?= $group ?>&sport_id=<?= $sport_id ?>&status=<?= $status ?>&county_id=<?= $county_id ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $pagination['page'] + 1 ?>&search=<?= urlencode($search) ?>&group=<?= $group ?>&sport_id=<?= $sport_id ?>&status=<?= $status ?>&county_id=<?= $county_id ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
