<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDb();
$user = getCurrentUser();

$pageTitle = 'My Profile';
include __DIR__ . '/../templates/header.php';
?>

<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-person me-2"></i>My Profile</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width:200px;">Full Name</th>
                        <td><?= sanitize($user['full_name']) ?></td>
                    </tr>
                    <tr>
                        <th>Username</th>
                        <td><?= sanitize($user['username']) ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= sanitize($user['email']) ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?= sanitize($user['phone'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <th>Role</th>
                        <td><?= getRoleLabel($user['role']) ?></td>
                    </tr>
                    <tr>
                        <th>County</th>
                        <td><?= sanitize($user['county_name'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <th>Association</th>
                        <td><?= sanitize($user['association_name'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $user['status'] ?></span></td>
                    </tr>
                    <tr>
                        <th>Member Since</th>
                        <td><?= date('F j, Y', strtotime($user['created_at'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
