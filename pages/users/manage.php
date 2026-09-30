<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin']);

$db = getDb();
$counties = getCounties();
$sports = getSports();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_user') {
        $id = (int)($_POST['id'] ?? 0);
        $username = sanitize($_POST['username'] ?? '');
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? 'active';
        $countyId = (int)($_POST['county_id'] ?? 0);
        $associationId = (int)($_POST['association_id'] ?? 0);

        $validRoles = ['super_admin', 'county_coordinator', 'association_admin', 'match_commissioner'];
        $validStatuses = ['active', 'inactive'];

        if ($id <= 0) $errors[] = 'Invalid user selected.';
        if ($username === '') $errors[] = 'Username is required.';
        if ($fullName === '') $errors[] = 'Full name is required.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if (!in_array($role, $validRoles, true)) $errors[] = 'Invalid role selected.';
        if (!in_array($status, $validStatuses, true)) $errors[] = 'Invalid status selected.';
        if ($role === 'county_coordinator' && $countyId <= 0) $errors[] = 'County is required for county coordinators.';
        if ($role === 'association_admin' && $associationId <= 0) $errors[] = 'Association is required for association admins.';

        $existingUsername = $db->fetchOne("SELECT id FROM users WHERE username = ? AND id <> ?", [$username, $id]);
        if ($existingUsername) $errors[] = 'That username is already in use.';

        $existingEmail = $db->fetchOne("SELECT id FROM users WHERE email = ? AND id <> ?", [$email, $id]);
        if ($existingEmail) $errors[] = 'That email is already in use.';

        if (empty($errors)) {
            if ($role !== 'county_coordinator') {
                $countyId = null;
            }
            if ($role !== 'association_admin') {
                $associationId = null;
            }

            $db->update(
                "UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, role = ?, county_id = ?, association_id = ?, status = ?, updated_at = NOW() WHERE id = ?",
                [$username, $fullName, $email, $phone !== '' ? $phone : null, $role, $countyId, $associationId, $status, $id]
            );
            logActivity('update_user', "Updated user #{$id} ({$username})");
            setFlash('success', 'User updated successfully.');
            redirect(APP_URL . 'pages/users/manage.php');
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $user = $db->fetchOne("SELECT id, username, status FROM users WHERE id = ?", [$id]);
        if (!$user) {
            setFlash('error', 'User not found.');
            redirect(APP_URL . 'pages/users/manage.php');
        }
        if ($id === (int)$_SESSION['user_id']) {
            setFlash('error', 'You cannot disable your own account.');
            redirect(APP_URL . 'pages/users/manage.php');
        }

        $nextStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $db->update("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?", [$nextStatus, $id]);
        logActivity('toggle_user_status', "Changed user #{$id} to {$nextStatus}");
        setFlash('success', 'User status updated successfully.');
        redirect(APP_URL . 'pages/users/manage.php');
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            setFlash('error', 'You cannot delete your own account.');
            redirect(APP_URL . 'pages/users/manage.php');
        }

        $user = $db->fetchOne("SELECT id, username FROM users WHERE id = ?", [$id]);
        if (!$user) {
            setFlash('error', 'User not found.');
            redirect(APP_URL . 'pages/users/manage.php');
        }

        try {
            $db->delete("DELETE FROM users WHERE id = ?", [$id]);
            logActivity('delete_user', "Deleted user #{$id} ({$user['username']})");
            setFlash('success', 'User deleted successfully.');
        } catch (Throwable $e) {
            setFlash('error', 'This user cannot be deleted because it is linked to existing records. Disable the account instead.');
        }
        redirect(APP_URL . 'pages/users/manage.php');
    }
}

$users = $db->fetchAll(
    "SELECT u.*, c.name AS county_name, s.name AS association_name
     FROM users u
     LEFT JOIN counties c ON u.county_id = c.id
     LEFT JOIN sports_disciplines s ON u.association_id = s.id
     ORDER BY u.created_at DESC"
);

$editUser = null;
if (isset($_GET['edit'])) {
    $editUser = $db->fetchOne(
        "SELECT u.*, c.name AS county_name, s.name AS association_name
         FROM users u
         LEFT JOIN counties c ON u.county_id = c.id
         LEFT JOIN sports_disciplines s ON u.association_id = s.id
         WHERE u.id = ?",
        [(int)$_GET['edit']]
    );
}

$pageTitle = 'User Credentials';
$pageActions = '<a href="' . APP_URL . 'pages/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>';
include __DIR__ . '/../../templates/header.php';
?>

<?php if ($msg = getFlash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= sanitize($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($editUser): ?>
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit User</h5>
        <a href="<?= APP_URL ?>pages/users/manage.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>">

            <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= sanitize($editUser['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" value="<?= sanitize($editUser['username']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= sanitize($editUser['email']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= sanitize($editUser['phone'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <?php foreach (['super_admin', 'county_coordinator', 'association_admin', 'match_commissioner'] as $role): ?>
                        <option value="<?= $role ?>" <?= $editUser['role'] === $role ? 'selected' : '' ?>><?= getRoleLabel($role) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="active" <?= $editUser['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $editUser['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">County</label>
                <select name="county_id" class="form-select">
                    <option value="0">None</option>
                    <?php foreach ($counties as $county): ?>
                        <option value="<?= (int)$county['id'] ?>" <?= (int)($editUser['county_id'] ?? 0) === (int)$county['id'] ? 'selected' : '' ?>><?= sanitize($county['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Association</label>
                <select name="association_id" class="form-select">
                    <option value="0">None</option>
                    <?php foreach ($sports as $sport): ?>
                        <option value="<?= (int)$sport['id'] ?>" <?= (int)($editUser['association_id'] ?? 0) === (int)$sport['id'] ? 'selected' : '' ?>><?= sanitize($sport['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end justify-content-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-people me-2"></i>All User Credentials</h5>
        <span class="badge bg-primary"><?= count($users) ?> User(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="text-center py-4 text-muted">No users found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>County</th>
                            <th>Association</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $listedUser): ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($listedUser['full_name']) ?></strong>
                                <?php if ((int)$listedUser['id'] === (int)$_SESSION['user_id']): ?>
                                    <span class="badge bg-info ms-1">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($listedUser['username']) ?></td>
                            <td><?= sanitize($listedUser['email']) ?></td>
                            <td><?= sanitize($listedUser['phone'] ?? '—') ?></td>
                            <td><?= sanitize(getRoleLabel($listedUser['role'])) ?></td>
                            <td><?= sanitize($listedUser['county_name'] ?? '—') ?></td>
                            <td><?= sanitize($listedUser['association_name'] ?? '—') ?></td>
                            <td><span class="badge bg-<?= $listedUser['status'] === 'active' ? 'success' : 'secondary' ?>"><?= sanitize($listedUser['status']) ?></span></td>
                            <td><small class="text-muted"><?= formatDate($listedUser['created_at'], 'M d, Y') ?></small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= APP_URL ?>pages/users/manage.php?edit=<?= (int)$listedUser['id'] ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int)$listedUser['id'] ?>">
                                        <button type="submit" class="btn btn-outline-warning" title="<?= $listedUser['status'] === 'active' ? 'Disable' : 'Enable' ?>" <?= (int)$listedUser['id'] === (int)$_SESSION['user_id'] ? 'disabled' : '' ?>>
                                            <i class="bi <?= $listedUser['status'] === 'active' ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete <?= addslashes($listedUser['username']) ?>? This action cannot be undone.');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= (int)$listedUser['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Delete" <?= (int)$listedUser['id'] === (int)$_SESSION['user_id'] ? 'disabled' : '' ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>