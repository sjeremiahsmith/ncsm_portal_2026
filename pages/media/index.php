<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['super_admin']);

$db = getDb();
$userId = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_video') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $videoType = $_POST['video_type'] === 'url' ? 'url' : 'file';

        if (empty($title)) {
            $error = 'Video title is required.';
        } elseif ($videoType === 'url') {
            $embedUrl = sanitize(trim($_POST['embed_url'] ?? ''));
            if (empty($embedUrl)) {
                $error = 'Video URL is required.';
            } else {
                $db->insert(
                    "INSERT INTO videos (title, video_type, embed_url, description, uploaded_by) VALUES (?, 'url', ?, ?, ?)",
                    [$title, $embedUrl, $description, $userId]
                );
                logActivity('video_added', "Added video link: $title");
                $message = 'Video link added successfully.';
            }
        } else {
            if (empty($_FILES['video_file']['name'])) {
                $error = 'Please choose a video file to upload.';
            } elseif ($_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Video upload failed.';
            } elseif ($_FILES['video_file']['size'] > MAX_VIDEO_SIZE) {
                $error = 'Video file too large. Maximum size is 200MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['video_file']['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, ALLOWED_VIDEO_TYPES)) {
                    $error = 'Invalid video type. MP4, WebM, Ogg, MOV, AVI, MKV allowed.';
                } else {
                    $ext = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
                    $filename = 'video_' . uniqid() . '.' . $ext;
                    if (!is_dir(VIDEO_PATH)) mkdir(VIDEO_PATH, 0755, true);
                    if (move_uploaded_file($_FILES['video_file']['tmp_name'], VIDEO_PATH . $filename)) {
                        $db->insert(
                            "INSERT INTO videos (title, video_type, video_path, description, uploaded_by) VALUES (?, 'file', ?, ?, ?)",
                            [$title, $filename, $description, $userId]
                        );
                        logActivity('video_uploaded', "Uploaded video: $title");
                        $message = 'Video uploaded successfully.';
                    } else {
                        $error = 'Failed to save video file.';
                    }
                }
            }
        }
    } elseif ($action === 'upload_photo') {
        $caption = sanitize($_POST['caption'] ?? '');
        $categoryTitle = sanitize($_POST['category_title'] ?? 'General');
        $categorySlug = $categoryTitle !== '' ? preg_replace('/[^a-z0-9]+/i', '-', strtolower($categoryTitle)) : 'general';

        if (empty($_FILES['gallery_photo']['name'])) {
            $error = 'Please choose a photo to upload.';
        } elseif ($_FILES['gallery_photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Photo upload failed.';
        } elseif ($_FILES['gallery_photo']['size'] > MAX_PHOTO_SIZE) {
            $error = 'Photo too large. Maximum size is 2MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['gallery_photo']['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, ALLOWED_PHOTO_TYPES)) {
                $error = 'Invalid image type. JPG, PNG, GIF only.';
            } else {
                $ext = pathinfo($_FILES['gallery_photo']['name'], PATHINFO_EXTENSION);
                $filename = 'photo_' . uniqid() . '.' . $ext;
                if (!is_dir(GALLERY_PATH)) mkdir(GALLERY_PATH, 0755, true);
                if (move_uploaded_file($_FILES['gallery_photo']['tmp_name'], GALLERY_PATH . $filename)) {
                    $db->insert(
                        "INSERT INTO gallery_photos (category_slug, category_title, photo_path, caption, uploaded_by) VALUES (?, ?, ?, ?, ?)",
                        [$categorySlug, $categoryTitle, $filename, $caption, $userId]
                    );
                    logActivity('photo_uploaded', "Uploaded gallery photo: $caption");
                    $message = 'Photo uploaded successfully.';
                } else {
                    $error = 'Failed to save photo file.';
                }
            }
        }
    } elseif ($action === 'delete_video') {
        $id = (int)($_POST['id'] ?? 0);
        $video = $db->fetchOne("SELECT * FROM videos WHERE id = ?", [$id]);
        if ($video) {
            $path = str_replace(['uploads/videos/', 'uploads/'], '', (string)$video['video_path']);
            if ($video['video_path'] && file_exists(VIDEO_PATH . $path)) {
                unlink(VIDEO_PATH . $path);
            }
            $db->delete("DELETE FROM videos WHERE id = ?", [$id]);
            logActivity('video_deleted', "Deleted video: {$video['title']}");
            $message = 'Video deleted.';
        }
    } elseif ($action === 'delete_photo') {
        $id = (int)($_POST['id'] ?? 0);
        $photo = $db->fetchOne("SELECT * FROM gallery_photos WHERE id = ?", [$id]);
        if ($photo) {
            $path = str_replace(['uploads/gallery/', 'uploads/'], '', (string)$photo['photo_path']);
            if ($photo['photo_path'] && file_exists(GALLERY_PATH . $path)) {
                unlink(GALLERY_PATH . $path);
            }
            $db->delete("DELETE FROM gallery_photos WHERE id = ?", [$id]);
            logActivity('photo_deleted', "Deleted gallery photo: {$photo['caption']}");
            $message = 'Photo deleted.';
        }
    }
}

$videos = $db->fetchAll("SELECT v.*, u.full_name AS uploader FROM videos v LEFT JOIN users u ON v.uploaded_by = u.id ORDER BY v.created_at DESC");
$photos = $db->fetchAll("SELECT p.*, u.full_name AS uploader FROM gallery_photos p LEFT JOIN users u ON p.uploaded_by = u.id ORDER BY p.created_at DESC");

$pageTitle = 'Media Management';
include __DIR__ . '/../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-images me-2"></i>Media Management</h4>
        <p class="text-muted mb-0">Upload and manage videos and photos for the portal.</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i><?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="mediaTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="videos-tab" data-bs-toggle="tab" data-bs-target="#videos-pane" type="button" role="tab">
            <i class="bi bi-play-circle me-1"></i>Videos
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="photos-tab" data-bs-toggle="tab" data-bs-target="#photos-pane" type="button" role="tab">
            <i class="bi bi-images me-1"></i>Photo Gallery
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="videos-pane" role="tabpanel">
        <div class="row g-4">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Upload Video</h6></div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="upload_video">
                            <div class="mb-3">
                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Video Source</label>
                                <select name="video_type" class="form-select" id="videoTypeSelect">
                                    <option value="file">Upload file</option>
                                    <option value="url">Embed URL (YouTube/Vimeo)</option>
                                </select>
                            </div>
                            <div class="mb-3" id="videoFileField">
                                <label class="form-label">Video File</label>
                                <input type="file" name="video_file" class="form-control" accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-msvideo,video/x-matroska">
                                <div class="form-text">MP4, WebM, Ogg, MOV, AVI, MKV. Max 200MB.</div>
                            </div>
                            <div class="mb-3 d-none" id="videoUrlField">
                                <label class="form-label">Video URL</label>
                                <input type="url" name="embed_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-collection-play me-2"></i>Uploaded Videos (<?= count($videos) ?>)</h6></div>
                    <div class="card-body">
                        <?php if (empty($videos)): ?>
                        <p class="text-muted text-center mb-0">No videos uploaded yet.</p>
                        <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($videos as $v): ?>
                            <div class="col-md-6">
                                <div class="border rounded p-2 h-100">
                                    <a href="<?= APP_URL ?>pages/media/play.php?id=<?= $v['id'] ?>" class="text-decoration-none d-block position-relative">
                                        <?php if ($v['video_type'] === 'url'): ?>
                                            <div class="ratio ratio-16x9 rounded overflow-hidden bg-dark" style="pointer-events:none">
                                                <iframe src="<?= sanitize(videoEmbedUrl($v['embed_url'])) ?>" title="<?= sanitize($v['title']) ?>" allowfullscreen loading="lazy"></iframe>
                                            </div>
                                        <?php else: ?>
                                            <video class="w-100 rounded" style="height:190px;object-fit:cover;pointer-events:none" preload="metadata" muted playsinline>
                                                <source src="<?= sanitize(mediaUrl('uploads/videos/', $v['video_path'])) ?>" type="video/mp4">
                                                Your browser does not support video playback.
                                            </video>
                                        <?php endif; ?>
                                        <div class="position-absolute top-50 start-50 translate-middle">
                                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white" style="width:48px;height:48px;background:rgba(0,0,0,0.55);font-size:20px;"><i class="bi bi-play-fill"></i></span>
                                        </div>
                                    </a>
                                    <div class="d-flex justify-content-between align-items-start mt-2">
                                        <div class="min-w-0">
                                            <a href="<?= APP_URL ?>pages/media/play.php?id=<?= $v['id'] ?>" class="text-decoration-none fw-bold small text-dark"><?= sanitize($v['title']) ?></a>
                                            <?php if ($v['description']): ?>
                                            <div class="small text-muted"><?= sanitize($v['description']) ?></div>
                                            <?php endif; ?>
                                            <div class="small text-muted"><?= timeAgo($v['created_at']) ?></div>
                                        </div>
                                        <form method="post" onsubmit="return confirm('Delete this video?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_video">
                                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="photos-pane" role="tabpanel">
        <div class="row g-4">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Upload Photo</h6></div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="upload_photo">
                            <div class="mb-3">
                                <label class="form-label">Photo <span class="text-danger">*</span></label>
                                <input type="file" name="gallery_photo" class="form-control" accept="image/jpeg,image/png,image/gif" required>
                                <div class="form-text">JPG, PNG, GIF. Max 2MB.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <input type="text" name="category_title" class="form-control" placeholder="e.g. Opening Ceremony" value="General">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Caption</label>
                                <input type="text" name="caption" class="form-control" placeholder="Short caption for the photo">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-images me-2"></i>Gallery Photos (<?= count($photos) ?>)</h6></div>
                    <div class="card-body">
                        <?php if (empty($photos)): ?>
                        <p class="text-muted text-center mb-0">No photos uploaded yet.</p>
                        <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($photos as $p): ?>
                            <div class="col-sm-6 col-md-4">
                                <div class="border rounded p-2 h-100 text-center d-flex flex-column">
                                    <a href="<?= sanitize(mediaUrl('uploads/gallery/', $p['photo_path'])) ?>" target="_blank" class="d-block flex-grow-1 overflow-hidden rounded" style="background:#f1f3f5;height:150px;">
                                        <img src="<?= sanitize(mediaUrl('uploads/gallery/', $p['photo_path'])) ?>" alt="<?= sanitize($p['caption']) ?>" class="w-100 h-100" style="object-fit:contain" loading="lazy">
                                    </a>
                                    <div class="small fw-bold mt-2 text-truncate"><?= sanitize($p['category_title']) ?></div>
                                    <?php if ($p['caption']): ?>
                                    <div class="small text-muted text-truncate"><?= sanitize($p['caption']) ?></div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-center mt-2">
                                        <form method="post" onsubmit="return confirm('Delete this photo?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_photo">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('videoTypeSelect').addEventListener('change', function () {
    var isUrl = this.value === 'url';
    document.getElementById('videoFileField').classList.toggle('d-none', isUrl);
    document.getElementById('videoUrlField').classList.toggle('d-none', !isUrl);
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>