<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['super_admin']);

$db = getDb();
$msg = '';
$error = '';

try {
    ensureContactMessagesTable();
} catch (Exception $e) {
    $error = 'Database setup error: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'mark_read') {
            $id = (int)($_POST['id'] ?? 0);
            $db->update("UPDATE contact_messages SET is_read = 1 WHERE id = ?", [$id]);
            $msg = '<div class="alert alert-success alert-dismissible fade show">Message marked as read.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }

        if ($action === 'mark_unread') {
            $id = (int)($_POST['id'] ?? 0);
            $db->update("UPDATE contact_messages SET is_read = 0 WHERE id = ?", [$id]);
            $msg = '<div class="alert alert-success alert-dismissible fade show">Message marked as unread.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $db->delete("DELETE FROM contact_messages WHERE id = ?", [$id]);
            $msg = '<div class="alert alert-success alert-dismissible fade show">Message deleted.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger alert-dismissible fade show">Action failed: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

$filter = $_GET['filter'] ?? 'all';
$where = '';
if ($filter === 'unread') $where = ' WHERE is_read = 0';
elseif ($filter === 'read') $where = ' WHERE is_read = 1';

$messages = [];
$unreadCount = 0;
$totalCount = 0;

if (empty($error)) {
    try {
        $messages = $db->fetchAll("SELECT * FROM contact_messages{$where} ORDER BY created_at DESC");
        $unreadCount = $db->fetchOne("SELECT COUNT(*) as c FROM contact_messages WHERE is_read = 0")['c'];
        $totalCount = $db->fetchOne("SELECT COUNT(*) as c FROM contact_messages")['c'];
    } catch (Exception $e) {
        $error = 'Could not load messages: ' . $e->getMessage();
    }
}

$pageActions = '<a href="' . APP_URL . 'pages/dashboard.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>';
$pageTitle = 'Contact Messages';
include __DIR__ . '/../../templates/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <hr>
        <p class="mb-0 small">The <code>contact_messages</code> table may not exist in your database. Make sure your database is fully imported, or contact your administrator.</p>
    </div>
<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-envelope me-2"></i>Contact Messages <span class="badge bg-danger"><?= $unreadCount ?></span></h4>
    <div class="d-flex gap-2">
        <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All (<?= $totalCount ?>)</a>
        <a href="?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-warning' : 'btn-outline-warning' ?>">Unread (<?= $unreadCount ?>)</a>
        <a href="?filter=read" class="btn btn-sm <?= $filter === 'read' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Read</a>
    </div>
</div>

<?= $msg ?>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($messages)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox" style="font-size:3rem;"></i>
            <p class="mt-2 mb-0">No messages found.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;"></th>
                        <th>From</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th class="text-end" style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $m): ?>
                    <tr class="<?= !$m['is_read'] ? 'table-light' : '' ?>">
                        <td>
                            <?php if (!$m['is_read']): ?>
                            <span class="badge bg-primary rounded-pill">New</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= sanitize($m['full_name']) ?></strong>
                            <br><small class="text-muted"><?= sanitize($m['email']) ?></small>
                            <?php if ($m['phone']): ?>
                            <br><small class="text-muted"><i class="bi bi-telephone me-1"></i><?= sanitize($m['phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><small><?= sanitize($m['subject'] ?: 'N/A') ?></small></td>
                        <td>
                            <small class="text-muted d-inline-block" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= sanitize($m['message']) ?>
                            </small>
                        </td>
                        <td><small class="text-muted"><?= formatDate($m['created_at'], 'M d, Y h:i A') ?></small></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#messageModal<?= $m['id'] ?>" title="View"><i class="bi bi-eye"></i></button>
                                <?php if (!$m['is_read']): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-outline-success" title="Mark as Read"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="mark_unread">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-outline-warning" title="Mark as Unread"><i class="bi bi-envelope"></i></button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this message?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <div class="modal fade" id="messageModal<?= $m['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Message from <?= sanitize($m['full_name']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Name</small>
                                            <strong><?= sanitize($m['full_name']) ?></strong>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Email</small>
                                            <strong><?= sanitize($m['email']) ?></strong>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Phone</small>
                                            <strong><?= sanitize($m['phone'] ?: 'N/A') ?></strong>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Subject</small>
                                            <strong><?= sanitize($m['subject'] ?: 'N/A') ?></strong>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Date</small>
                                            <strong><?= formatDate($m['created_at'], 'M d, Y h:i A') ?></strong>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Status</small>
                                            <span class="badge bg-<?= $m['is_read'] ? 'secondary' : 'primary' ?>"><?= $m['is_read'] ? 'Read' : 'Unread' ?></span>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="bg-light p-3 rounded">
                                        <p class="mb-0" style="white-space:pre-wrap;"><?= sanitize($m['message']) ?></p>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <?php if (!$m['is_read']): ?>
                                    <form method="POST" class="me-auto">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Mark as Read</button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="mailto:<?= sanitize($m['email']) ?>?subject=Re: <?= urlencode($m['subject'] ?: 'Your message to NCSM') ?>" class="btn btn-primary btn-sm"><i class="bi bi-reply me-1"></i>Reply via Email</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
