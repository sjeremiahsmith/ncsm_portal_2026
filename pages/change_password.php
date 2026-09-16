<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        setFlash('error', 'All fields are required.');
    } elseif ($newPassword !== $confirmPassword) {
        setFlash('error', 'New password and confirm password do not match.');
    } elseif (strlen($newPassword) < 6) {
        setFlash('error', 'New password must be at least 6 characters.');
    } else {
        $user = $db->fetchOne("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);
        if (!password_verify($currentPassword, $user['password'])) {
            setFlash('error', 'Current password is incorrect.');
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->update("UPDATE users SET password = ? WHERE id = ?", [$hash, $_SESSION['user_id']]);
            logActivity('change_password', 'User changed their password');
            setFlash('success', 'Password changed successfully.');
        }
    }
    header('Location: ' . APP_URL . 'pages/change_password.php');
    exit;
}

$pageTitle = 'Change Password';
include __DIR__ . '/../templates/header.php';
?>

<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-key me-2"></i>Change Password</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Change Password</button>
                    <a href="<?= APP_URL ?>pages/dashboard.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
