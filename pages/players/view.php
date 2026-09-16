<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = getDb();
$id = (int)($_GET['id'] ?? 0);

$player = $db->fetchOne(
    "SELECT p.*, c.name as county_name, c.group_label, s.name as sport_name, s.association_name, s.association_code,
            u.full_name as registered_by_name, u.email as registered_by_email
     FROM players p
     JOIN counties c ON p.county_id = c.id
     JOIN sports_disciplines s ON p.sport_discipline_id = s.id
     JOIN users u ON p.registered_by = u.id
     WHERE p.id = ?",
    [$id]
);

if (!$player) {
    setFlash('error', 'Player not found.');
    redirect(APP_URL . 'pages/players/list.php');
}

if (hasRole('association_admin') && $player['sport_discipline_id'] != $_SESSION['user_association_id']) {
    setFlash('error', 'You do not have access to this player.');
    redirect(APP_URL . 'pages/players/list.php');
}

if (isAdminRole() && $player['group_label'] != $_SESSION['user_group_label']) {
    setFlash('error', 'You do not have access to this player.');
    redirect(APP_URL . 'pages/players/list.php');
}

$approvalHistory = $db->fetchAll(
    "SELECT a.*, u.full_name as action_by_name, u.role
     FROM approval_workflow a
     JOIN users u ON a.action_by = u.id
     WHERE a.player_id = ?
     ORDER BY a.created_at DESC",
    [$id]
);

$pageTitle = sanitize($player['full_name']);
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="position-relative d-inline-block">
                    <img src="<?= getPlayerPhotoUrl($player['photo_path']) ?>" class="player-photo-lg mb-3" alt="">
                    <?php if (!hasRole('association_admin') && (hasRole('super_admin') || ($player['status'] === 'draft' && $_SESSION['user_id'] === $player['registered_by']))): ?>
                    <button type="button" class="btn btn-sm btn-light rounded-circle position-absolute bottom-0 end-0 mb-2 me-1 shadow-sm" onclick="document.getElementById('photoInput').click()" title="Change Photo">
                        <i class="bi bi-camera"></i>
                    </button>
                    <form method="POST" action="<?= APP_URL ?>pages/players/update_photo.php" enctype="multipart/form-data" id="photoForm" style="display:none">
                        <?= csrfField() ?>
                        <input type="hidden" name="player_id" value="<?= $player['id'] ?>">
                        <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/gif" onchange="document.getElementById('photoForm').submit()">
                    </form>
                    <?php endif; ?>
                </div>
                <h4><?= sanitize($player['full_name']) ?></h4>
                <div class="mb-2"><?= getStatusBadge($player['status']) ?></div>
                <div class="mb-2">
                    <span class="group-badge group-<?= $player['group_label'] ?>"><?= $player['group_label'] ?></span>
                    <?= sanitize($player['county_name']) ?>
                </div>
                <hr>
                <div class="d-grid gap-2">
                    <?php if (!hasRole('association_admin') && (hasRole('super_admin') || ($player['status'] === 'draft' && $_SESSION['user_id'] === $player['registered_by']))): ?>
                        <a href="<?= APP_URL ?>pages/players/edit.php?id=<?= $player['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit</a>
                        <?php if ($player['status'] === 'draft'): ?>
                        <form method="POST" action="<?= APP_URL ?>pages/players/edit.php?id=<?= $player['id'] ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="submit">
                            <button type="submit" class="btn btn-info w-100"><i class="bi bi-send"></i> Submit for Approval</button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (hasRole('association_admin') && $player['status'] === 'submitted'): ?>
                        <a href="<?= APP_URL ?>pages/approvals/pending.php?player_id=<?= $player['id'] ?>" class="btn btn-warning"><i class="bi bi-check2-square"></i> Review & Approve</a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
                    <?php if (hasRole('super_admin') || isAdminRole() || hasRole('sports_coord')): ?>
                    <a href="<?= APP_URL ?>pages/players/card_preview.php?id=<?= $player['id'] ?>" class="btn btn-outline-dark"><i class="bi bi-credit-card"></i> Player Card</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white"><h5 class="mb-0">Personal Details</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><strong>Date of Birth</strong><br><?= formatDate($player['date_of_birth']) ?></div>
                    <div class="col-md-4"><strong>Gender</strong><br><?= ucfirst($player['gender']) ?></div>
                    <div class="col-md-4"><strong>Nationality</strong><br><?= sanitize($player['nationality'] ?? 'Liberian') ?></div>
                    <div class="col-md-4"><strong>Year of NSCM</strong><br><?= sanitize($player['year_of_nscm']) ?></div>
                    <div class="col-md-4"><strong>Age</strong><br><?= (int)($player['age'] ?? 0) ?></div>
                    <div class="col-md-4"><strong>City/Town</strong><br><?= sanitize($player['city'] ?? '') ?></div>
                    <div class="col-md-4"><strong>Last Club</strong><br><?= sanitize($player['last_club'] ?? '-') ?></div>
                    <div class="col-md-4"><strong>Current Club</strong><br><?= sanitize($player['current_club'] ?? '-') ?></div>
                    <div class="col-md-4"><strong>Sport</strong><br><?= sanitize($player['sport_name']) ?></div>
                    <div class="col-md-4"><strong>Association</strong><br><?= sanitize($player['association_name']) ?></div>
                    <div class="col-md-4"><strong>Primary Level</strong><br><?= sanitize($player['primary_position']) ?></div>
                    <div class="col-12"><strong>Medical Fitness</strong><br><?= getStatusBadge($player['medical_fitness_status']) ?> <?= sanitize($player['medical_notes'] ?? '') ?></div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><h5 class="mb-0">Registration Info</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><strong>Registered By</strong><br><?= sanitize($player['registered_by_name']) ?></div>
                    <div class="col-md-4"><strong>Date Registered</strong><br><?= formatDate($player['created_at']) ?></div>
                    <div class="col-md-4"><strong>Last Updated</strong><br><?= formatDate($player['updated_at']) ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0">Approval History</h5></div>
            <div class="card-body">
                <?php if (empty($approvalHistory)): ?>
                    <p class="text-muted">No approval history yet.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($approvalHistory as $h): ?>
                        <div class="timeline-item <?= $h['action'] ?>">
                            <strong><?= ucfirst($h['action']) ?></strong> by <?= sanitize($h['action_by_name']) ?>
                            <br><small class="text-muted"><?= formatDate($h['created_at'], 'M d, Y g:i A') ?></small>
                            <?php if ($h['comments']): ?>
                                <br><small><?= sanitize($h['comments']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
