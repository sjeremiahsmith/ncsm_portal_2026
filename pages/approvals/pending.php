<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['association_admin']);

$db = getDb();
$user = getCurrentUser();

$playerId = (int)($_GET['player_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $playerId > 0) {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    $comments = sanitize($_POST['comments'] ?? '');

    if (in_array($action, ['approve', 'reject', 'return_for_revision'])) {
        $player = $db->fetchOne("SELECT p.*, u.full_name as reg_name, u.id as reg_user_id, c.name as county_name, s.name as sport_name FROM players p JOIN users u ON p.registered_by = u.id JOIN counties c ON p.county_id = c.id JOIN sports_disciplines s ON p.sport_discipline_id = s.id WHERE p.id = ?", [$playerId]);

        if (!$player || $player['sport_discipline_id'] != $_SESSION['user_association_id']) {
            setFlash('error', 'You do not have access to this player.');
            redirect(APP_URL . 'pages/approvals/pending.php');
        }

        $db->update("UPDATE players SET status = ?, updated_at = NOW() WHERE id = ?", [$action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'draft'), $playerId]);

        $db->insert(
            "INSERT INTO approval_workflow (player_id, action, action_by, role_at_time, comments) VALUES (?, ?, ?, ?, ?)",
            [$playerId, $action, $_SESSION['user_id'], $_SESSION['user_role'], $comments]
        );

        if ($player) {
            createNotification(
                $player['reg_user_id'],
                'Registration ' . ucfirst($action),
                "Your registration for {$player['full_name']} ({$player['sport_name']}) has been <strong>$action</strong>." . ($comments ? " Reason: $comments" : ''),
                $action === 'approve' ? 'success' : 'danger',
                APP_URL . 'pages/players/view.php?id=' . $playerId
            );
        }

        logActivity('approval_action', "$action player #$playerId");
        setFlash('success', "Player registration <strong>$action</strong> successfully.");
        redirect(APP_URL . 'pages/approvals/pending.php');
    }
}

if ($playerId > 0) {
    $pendingPlayer = $db->fetchOne(
        "SELECT p.*, c.name as county_name, c.group_label, s.name as sport_name, s.association_name,
                u.full_name as registered_by_name, u.email as registered_by_email
         FROM players p
         JOIN counties c ON p.county_id = c.id
         JOIN sports_disciplines s ON p.sport_discipline_id = s.id
         JOIN users u ON p.registered_by = u.id
         WHERE p.id = ? AND p.status = 'submitted' AND p.sport_discipline_id = ?",
        [$playerId, $_SESSION['user_association_id']]
    );

    if (!$pendingPlayer) {
        setFlash('info', 'Player not found or already processed.');
        redirect(APP_URL . 'pages/approvals/pending.php');
    }
}

$whereExtra = '';
$params = [];
if (!hasRole('super_admin')) {
    $whereExtra = "AND p.sport_discipline_id = ?";
    $params[] = $_SESSION['user_association_id'];
}

$pendingPlayers = $db->fetchAll(
    "SELECT p.*, c.name as county_name, c.group_label, s.name as sport_name, s.association_name,
            u.full_name as registered_by_name
     FROM players p
     JOIN counties c ON p.county_id = c.id
     JOIN sports_disciplines s ON p.sport_discipline_id = s.id
     JOIN users u ON p.registered_by = u.id
     WHERE p.status = 'submitted' $whereExtra
     ORDER BY p.created_at ASC",
    $params
);

$pageTitle = 'Pending Approvals';
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<?php if ($playerId > 0 && isset($pendingPlayer)): ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Reviewing: <?= sanitize($pendingPlayer['full_name']) ?></h5>
                <a href="<?= APP_URL ?>pages/approvals/pending.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4 text-center">
                        <img src="<?= getPlayerPhotoUrl($pendingPlayer['photo_path']) ?>" class="player-photo-lg" alt="">
                    </div>
                    <div class="col-md-8">
                        <table class="table table-sm">
                            <tr><th>Full Name</th><td><?= sanitize($pendingPlayer['full_name']) ?></td></tr>
                            <tr><th>Date of Birth</th><td><?= formatDate($pendingPlayer['date_of_birth']) ?></td></tr>
                            <tr><th>Gender</th><td><?= ucfirst($pendingPlayer['gender']) ?></td></tr>
                            <tr><th>Nationality</th><td><?= sanitize($pendingPlayer['nationality'] ?? 'Liberian') ?></td></tr>
                            <tr><th>Year of NSCM</th><td><?= sanitize($pendingPlayer['year_of_nscm']) ?></td></tr>
                            <tr><th>Age / City</th><td><?= (int)($pendingPlayer['age'] ?? 0) ?> / <?= sanitize($pendingPlayer['city'] ?? '') ?></td></tr>
                            <tr><th>Last Club / Current Club</th><td><?= sanitize($pendingPlayer['last_club'] ?: '-') ?> / <?= sanitize($pendingPlayer['current_club'] ?: '-') ?></td></tr>
                            <tr><th>County</th><td><span class="group-badge group-<?= $pendingPlayer['group_label'] ?>"><?= $pendingPlayer['group_label'] ?></span> <?= sanitize($pendingPlayer['county_name']) ?></td></tr>
                            <tr><th>Sport</th><td><?= sanitize($pendingPlayer['sport_name']) ?> (<?= sanitize($pendingPlayer['association_name']) ?>)</td></tr>
                            <tr><th>Level</th><td><?= sanitize($pendingPlayer['primary_position']) ?></td></tr>
                            <tr><th>Medical Status</th><td><?= getStatusBadge($pendingPlayer['medical_fitness_status']) ?></td></tr>
                            <tr><th>Registered By</th><td><?= sanitize($pendingPlayer['registered_by_name']) ?></td></tr>
                        </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0">Approval Action</h5></div>
            <div class="card-body">
                <form method="POST" action="?player_id=<?= $playerId ?>">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Comments / Notes</label>
                        <textarea name="comments" class="form-control" rows="4" placeholder="Provide reason for approval or rejection..."></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                        <button type="submit" name="action" value="return_for_revision" class="btn btn-warning">
                            <i class="bi bi-arrow-counterclockwise"></i> Return for Revision
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this registration?')">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>County</th>
                        <th>Sport</th>
                        <th>Level</th>
                        <th>Registered By</th>
                        <th>Submitted</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingPlayers)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No pending approvals.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pendingPlayers as $p): ?>
                        <tr class="row-submitted">
                            <td><strong><?= sanitize($p['full_name']) ?></strong></td>
                            <td><span class="group-badge group-<?= $p['group_label'] ?>"><?= $p['group_label'] ?></span> <?= sanitize($p['county_name']) ?></td>
                            <td><?= sanitize($p['sport_name']) ?></td>
                            <td><small><?= sanitize($p['primary_position']) ?></small></td>
                            <td><small class="text-muted"><?= sanitize($p['registered_by_name']) ?></small></td>
                            <td><small class="text-muted"><?= timeAgo($p['created_at']) ?></small></td>
                            <td class="text-end">
                                <a href="?player_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-check2-square"></i> Review</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
