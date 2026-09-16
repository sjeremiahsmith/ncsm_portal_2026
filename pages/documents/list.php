<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = getDb();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$total = $db->fetchOne("SELECT COUNT(*) as count FROM documents")['count'];
$pagination = paginate($total, $page, $perPage);

$documents = $db->fetchAll(
    "SELECT d.*, u.full_name as uploaded_by_name
     FROM documents d
     JOIN users u ON d.uploaded_by = u.id
     ORDER BY d.created_at DESC
     LIMIT ? OFFSET ?",
    [$pagination['perPage'], $pagination['offset']]
);

$pageTitle = 'Documents';
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-folder me-2"></i>All Documents</h4>
    <?php if (hasRole(['super_admin'])): ?>
    <a href="<?= APP_URL ?>pages/dashboard.php" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Upload New</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($documents)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark" style="font-size:3rem;"></i>
                <p class="mt-2">No documents uploaded yet.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>File</th>
                        <th>Uploaded By</th>
                        <th>Date</th>
                        <th class="text-end">Download</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $d): ?>
                    <tr>
                        <td><strong><?= sanitize($d['title']) ?></strong></td>
                        <td><small class="text-muted"><?= sanitize($d['description'] ?: '-') ?></small></td>
                        <td><small><?= sanitize($d['file_name']) ?> (<?= round($d['file_size'] / 1024) ?> KB)</small></td>
                        <td><small><?= sanitize($d['uploaded_by_name']) ?></small></td>
                        <td><small class="text-muted"><?= formatDate($d['created_at']) ?></small></td>
                        <td class="text-end">
                            <a href="<?= APP_URL ?>pages/documents/download.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-success">
                                <i class="bi bi-download"></i> Download
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($pagination['totalPages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
        <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
