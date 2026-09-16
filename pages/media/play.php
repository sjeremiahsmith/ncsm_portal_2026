<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['super_admin']);

$id = (int)($_GET['id'] ?? 0);
$db = getDb();
$video = $db->fetchOne("SELECT v.*, u.full_name AS uploader FROM videos v LEFT JOIN users u ON v.uploaded_by = u.id WHERE v.id = ?", [$id]);
if (!$video) {
    header('Location: ' . APP_URL . 'pages/media/index.php');
    exit;
}

$playerUrl = $video['video_type'] === 'url'
    ? videoEmbedUrl($video['embed_url'])
    : mediaUrl('uploads/videos/', $video['video_path']);

$pageTitle = 'Play Video - ' . $video['title'];
include __DIR__ . '/../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-play-circle me-2"></i><?= sanitize($video['title']) ?></h4>
        <p class="text-muted mb-0">
            <?php if ($video['video_type'] === 'url'): ?>External link video<?php else: ?>Uploaded file<?php endif; ?>
            &middot; <?= $video['uploader'] ? 'Uploaded by ' . sanitize($video['uploader']) : '' ?> <?= timeAgo($video['created_at']) ?>
        </p>
    </div>
    <a href="<?= APP_URL ?>pages/media/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Media</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="ratio ratio-16x9 bg-dark rounded overflow-hidden">
            <?php if ($video['video_type'] === 'url'): ?>
                <iframe src="<?= sanitize($playerUrl) ?>" title="<?= sanitize($video['title']) ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <?php else: ?>
                <video class="w-100 h-100" controls autoplay preload="metadata" style="object-fit:contain;background:#000">
                    <source src="<?= sanitize($playerUrl) ?>" type="video/mp4">
                    Your browser does not support video playback.
                </video>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body border-top">
        <?php if ($video['description']): ?>
        <p class="mb-1"><?= nl2br(sanitize($video['description'])) ?></p>
        <?php endif; ?>
        <?php if ($video['video_type'] !== 'url'): ?>
        <div class="mt-3">
            <a href="<?= sanitize($playerUrl) ?>" target="_blank" class="btn btn-sm btn-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Open in new tab</a>
            <a href="<?= sanitize($playerUrl) ?>" download class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Download</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>