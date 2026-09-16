<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDb();
$user = getCurrentUser();

$sportFilter = hasRole('association_admin') ? (int)$_SESSION['user_association_id'] : null;
$groupFilter = isAdminRole() ? $_SESSION['user_group_label'] : null;

function buildCountSql($base, $extraCond, $sportFilter, $groupFilter) {
    $join = $groupFilter ? " JOIN counties c ON p.county_id = c.id" : "";
    $conds = [];
    if ($sportFilter) $conds[] = "p.sport_discipline_id = " . (int)$sportFilter;
    if ($groupFilter) $conds[] = "c.group_label = " . getDb()->getConnection()->quote($groupFilter);
    if ($extraCond) $conds[] = $extraCond;
    $where = $conds ? " WHERE " . implode(" AND ", $conds) : "";
    return $base . $join . $where;
}

$totalPlayers = $db->fetchOne(buildCountSql("SELECT COUNT(*) as count FROM players p", "", $sportFilter, $groupFilter))['count'];
$totalFemale = $db->fetchOne(buildCountSql("SELECT COUNT(*) as count FROM players p", "p.gender = 'female'", $sportFilter, $groupFilter))['count'];
$totalMale = $db->fetchOne(buildCountSql("SELECT COUNT(*) as count FROM players p", "p.gender = 'male'", $sportFilter, $groupFilter))['count'];
$totalApproved = $db->fetchOne(buildCountSql("SELECT COUNT(*) as count FROM players p", "p.status = 'approved'", $sportFilter, $groupFilter))['count'];
$totalRejected = $db->fetchOne(buildCountSql("SELECT COUNT(*) as count FROM players p", "p.status = 'rejected'", $sportFilter, $groupFilter))['count'];
$totalDrafts = $db->fetchOne(buildCountSql("SELECT COUNT(*) as count FROM players p", "p.status = 'draft'", $sportFilter, $groupFilter))['count'];

$recentPlayers = $db->fetchAll(
    "SELECT p.*, c.name as county_name, c.group_label, s.name as sport_name, s.association_name,
            u.full_name as registered_by_name
     FROM players p
     JOIN counties c ON p.county_id = c.id
     JOIN sports_disciplines s ON p.sport_discipline_id = s.id
     JOIN users u ON p.registered_by = u.id" .
     ($sportFilter || $groupFilter ? " WHERE" . ($sportFilter ? " p.sport_discipline_id = $sportFilter" : "") . ($sportFilter && $groupFilter ? " AND" : "") . ($groupFilter ? " c.group_label = '" . $groupFilter . "'" : "") : "") .
     " ORDER BY p.created_at DESC LIMIT 10"
);

$approvalQueue = $db->fetchAll(
    "SELECT p.id, p.full_name, p.status, p.created_at, c.name as county_name, c.group_label,
            s.name as sport_name, s.association_name
     FROM players p
     JOIN counties c ON p.county_id = c.id
     JOIN sports_disciplines s ON p.sport_discipline_id = s.id
     WHERE p.status = 'submitted'" .
     (hasRole('association_admin') ? " AND p.sport_discipline_id = " . (int)$_SESSION['user_association_id'] : "") .
     ($groupFilter ? " AND c.group_label = '" . $groupFilter . "'" : "") .
     " ORDER BY p.created_at ASC LIMIT 10"
);

$sportCounts = $db->fetchAll(
    "SELECT s.name, s.association_name, COUNT(p.id) as count
     FROM sports_disciplines s
     LEFT JOIN players p ON s.id = p.sport_discipline_id" .
     ($groupFilter ? " JOIN counties c ON p.county_id = c.id AND c.group_label = '" . $groupFilter . "'" : "") .
     " GROUP BY s.id, s.name, s.association_name
     ORDER BY count DESC"
);

$countyGroupCounts = $db->fetchAll(
    "SELECT c.group_label, COUNT(p.id) as count
     FROM counties c
     LEFT JOIN players p ON c.id = p.county_id" .
     ($groupFilter ? " AND c.group_label = '" . $groupFilter . "'" : "") .
     " GROUP BY c.group_label
     ORDER BY c.group_label"
);

$statusData = [];
$sportsData = [];
$countyData = [];

$countyDetails = $db->fetchAll(
    "SELECT c.name, c.group_label, COUNT(p.id) as count
     FROM counties c
     LEFT JOIN players p ON c.id = p.county_id" .
     ($groupFilter ? " AND c.group_label = '" . $groupFilter . "'" : "") .
     " GROUP BY c.id, c.name, c.group_label
     ORDER BY c.group_label, c.name"
);

foreach ($countyDetails as $row) {
    $countyData[$row['name']] = (int)$row['count'];
}

$genderData = [
    'Female' => $totalFemale,
    'Male' => $totalMale,
];

$totalSubmitted = getPlayerCountByStatus('submitted', $sportFilter);
$statusData = [
    'Draft' => $totalDrafts,
    'Submitted' => $totalSubmitted,
    'Approved' => $totalApproved,
    'Rejected' => $totalRejected,
];

foreach ($sportCounts as $row) {
    $sportsData[$row['name']] = (int)$row['count'];
}

$pageTitle = 'Dashboard';
$pageActions = isCoordViewer() ? '' : '<a href="' . APP_URL . 'pages/players/register.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Register Player</a>';
?>
<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="stat-icon"><i class="bi bi-people"></i></div>
                <div class="stat-value"><?= $totalPlayers ?></div>
                <div class="stat-label text-white-50">Total Players</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card stat-card bg-warning text-dark">
            <div class="card-body">
                <div class="stat-icon"><i class="bi bi-gender-female"></i></div>
                <div class="stat-value"><?= $totalFemale ?></div>
                <div class="stat-label text-dark-50">Total Female</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="stat-icon"><i class="bi bi-gender-male"></i></div>
                <div class="stat-value"><?= $totalMale ?></div>
                <div class="stat-label text-white-50">Total Male</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value"><?= $totalApproved ?></div>
                <div class="stat-label text-white-50">Approved</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card stat-card bg-danger text-white">
            <div class="card-body">
                <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                <div class="stat-value"><?= $totalRejected ?></div>
                <div class="stat-label text-white-50">Rejected</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card stat-card bg-dark text-white">
            <div class="card-body">
                <div class="stat-icon"><i class="bi bi-layers"></i></div>
                <div class="stat-value"><?= count($countyData) ?></div>
                <div class="stat-label text-white-50">Counties</div>
            </div>
        </div>
    </div>
</div>

<form method="GET" action="<?= APP_URL ?>pages/players/list.php" class="row g-2 mb-4">
    <div class="col-md-8 col-lg-9">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search players by name..." autocomplete="off">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
        </div>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="<?= APP_URL ?>pages/players/list.php" class="btn btn-outline-secondary w-100"><i class="bi bi-people me-1"></i>Browse All Players</a>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Registrations</h5>
                <a href="<?= APP_URL ?>pages/players/list.php" class="btn btn-outline-primary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px">Num#</th>
                                <th style="width:50px">Photo</th>
                                <th>Player</th>
                                <th>County</th>
                                <th>Sport</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th class="text-end" style="width:100px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentPlayers)): ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No players registered yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentPlayers as $index => $p): ?>
                                <tr class="row-<?= $p['status'] ?>">
                                    <td class="text-muted fw-semibold"><?= $index + 1 ?></td>
                                    <td>
                                        <a href="<?= APP_URL ?>pages/players/view.php?id=<?= $p['id'] ?>">
                                            <img src="<?= getPlayerPhotoUrl($p['photo_path']) ?>" class="player-photo" alt="" style="width:42px;height:42px;">
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?= APP_URL ?>pages/players/view.php?id=<?= $p['id'] ?>" class="text-decoration-none fw-medium">
                                            <?= sanitize($p['full_name']) ?>
                                        </a>
                                    </td>
                                    <td><span class="group-badge group-<?= $p['group_label'] ?>" style="width:20px;height:20px;line-height:20px;font-size:0.6rem;"><?= $p['group_label'] ?></span> <?= sanitize($p['county_name']) ?></td>
                                    <td><small><?= sanitize($p['sport_name']) ?></small></td>
                                    <td><?= getStatusBadge($p['status']) ?></td>
                                    <td><small class="text-muted" title="<?= formatDate($p['created_at'], 'M d, Y h:i A') ?>"><?= timeAgo($p['created_at']) ?></small></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= APP_URL ?>pages/players/view.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                            <?php if (!hasRole('association_admin') && (hasRole('super_admin') || ($p['status'] === 'draft' && $_SESSION['user_id'] === $p['registered_by']))): ?>
                                                <a href="<?= APP_URL ?>pages/players/edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <?php endif; ?>
                                            <?php if (hasRole('super_admin')): ?>
                                                <form method="POST" action="<?= APP_URL ?>pages/players/delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete <?= addslashes($p['full_name']) ?>? This action cannot be undone.')">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <?php if (hasRole(['association_admin'])): ?>
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0">Pending Approvals</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($approvalQueue)): ?>
                        <div class="list-group-item text-muted text-center py-3">No pending approvals.</div>
                    <?php else: ?>
                        <?php foreach ($approvalQueue as $a): ?>
                        <a href="<?= APP_URL ?>pages/approvals/pending.php?player_id=<?= $a['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= sanitize($a['full_name']) ?></strong>
                                    <br><small class="text-muted"><?= sanitize($a['county_name']) ?> - <?= sanitize($a['sport_name']) ?></small>
                                </div>
                                <small class="text-muted"><?= timeAgo($a['created_at']) ?></small>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (hasRole(['super_admin'])): ?>
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Sports Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="sportsChart"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (hasRole(['super_admin'])): ?>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Gender Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="genderChart" width="400" height="400" style="max-width:100%;height:auto;display:block;margin:0 auto;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Players by County</h5>
            </div>
            <div class="card-body">
                <canvas id="countyChart" width="400" height="400" style="max-width:100%;height:auto;display:block;margin:0 auto;"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const genderData = <?= json_encode($genderData) ?>;
const sportsData = <?= json_encode($sportsData) ?>;
const countyData = <?= json_encode($countyData) ?>;
</script>

<?php if (hasRole(['super_admin'])): ?>
<?php
$docErrors = [];
$docSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    requireCsrfToken();
    $title = sanitize($_POST['doc_title'] ?? '');
    $description = sanitize($_POST['doc_description'] ?? '');
    if (empty($title)) $docErrors[] = 'Title is required.';
    if (empty($_FILES['doc_file']['name'])) $docErrors[] = 'File is required.';
    if (empty($docErrors)) {
        $result = uploadDocument($_FILES['doc_file']);
        if ($result['success']) {
            $db->insert(
                "INSERT INTO documents (title, description, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$title, $description, $result['original_name'], $result['filename'], $result['mime'], $result['size'], $_SESSION['user_id']]
            );
            logActivity('upload_document', "Uploaded document: {$title}");
            $docSuccess = true;
        } else {
            $docErrors[] = $result['error'];
        }
    }
}
$recentDocs = $db->fetchAll(
    "SELECT d.*, u.full_name as uploaded_by_name FROM documents d JOIN users u ON d.uploaded_by = u.id ORDER BY d.created_at DESC LIMIT 5"
);
?>
<div class="row g-3 mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-arrow-up me-2"></i>Document Upload</h5>
                <a href="<?= APP_URL ?>pages/documents/list.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder me-1"></i>View All Documents</a>
            </div>
            <div class="card-body">
                <?php if ($docSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show">Document uploaded successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php foreach ($docErrors as $e): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><?= $e ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endforeach; ?>
                <form method="POST" enctype="multipart/form-data" class="row g-2">
                    <?= csrfField() ?>
                    <div class="col-md-4">
                        <input type="text" name="doc_title" class="form-control" placeholder="Document title" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="doc_description" class="form-control" placeholder="Brief description (optional)">
                    </div>
                    <div class="col-md-2">
                        <input type="file" name="doc_file" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="upload_document" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Upload</button>
                    </div>
                </form>
                <?php if (!empty($recentDocs)): ?>
                <hr>
                <h6 class="text-muted mb-2">Recent Uploads</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0">
                        <thead><tr><th>Title</th><th>File</th><th>Uploaded By</th><th>Date</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($recentDocs as $d): ?>
                            <tr>
                                <td><strong><?= sanitize($d['title']) ?></strong><br><small class="text-muted"><?= sanitize($d['description']) ?></small></td>
                                <td><?= sanitize($d['file_name']) ?> <small class="text-muted">(<?= round($d['file_size'] / 1024) ?> KB)</small></td>
                                <td><?= sanitize($d['uploaded_by_name']) ?></td>
                                <td><small class="text-muted"><?= timeAgo($d['created_at']) ?></small></td>
                                <td><a href="<?= APP_URL ?>pages/documents/download.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i></a></td>
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
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
