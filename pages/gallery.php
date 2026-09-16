<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = $_GET['cat'] ?? '';
$uploadSuccess = '';
$uploadError = '';

if (empty($slug)) {
    header('Location: ' . APP_URL . 'pages/media.php');
    exit;
}

$db = getDb();

$galleryCategories = [
    'opening-ceremony' => 'Opening Ceremony',
    'champion-2024' => 'Nimba County Champion 2024/2025',
    'bong-county' => 'Bong County Team',
    'grand-gedeh' => 'Grand Gedeh County',
    'river-gee' => 'River Gee County',
    'lofa-county' => 'Lofa County Team',
    'grand-bassa' => 'Grand Bassa County',
    'margibi-county' => 'Margibi County Team',
    'grand-kru' => 'Grand Kru County',
    'river-cess' => 'River Cess County',
    'bomi-county' => 'Bomi County Team',
    'grand-cape-mount' => 'Grand Cape Mount County',
    'montserrado-county' => 'Montserrado County Team',
    'sinoe-county' => 'Sinoe County',
    'gbarpolu-county' => 'Gbarpolu County Team',
    'maryland-county' => 'Maryland County',
];

$categoryTitle = $galleryCategories[$slug] ?? ucwords(str_replace('-', ' ', $slug));

if (hasRole('super_admin') && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    requireCsrfToken();
    $caption = trim($_POST['caption'] ?? '');
    $newSlug = trim($_POST['category_slug'] ?? $slug);
    $newTitle = trim($_POST['category_title'] ?? $categoryTitle);

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
            $uploadError = 'Only JPG, PNG, GIF, and WebP images are allowed.';
        } else {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = 'gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = GALLERY_PATH;
            if (!is_dir($dest)) mkdir($dest, 0777, true);

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest . $filename)) {
                $relPath = 'uploads/gallery/' . $filename;
                $maxOrder = $db->fetchOne("SELECT COALESCE(MAX(sort_order),0) + 1 AS next FROM gallery_photos WHERE category_slug = ?", [$newSlug]);
                $db->insert(
                    "INSERT INTO gallery_photos (category_slug, category_title, photo_path, caption, sort_order, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)",
                    [$newSlug, $newTitle, $relPath, $caption, $maxOrder['next'], $_SESSION['user_id'] ?? null]
                );
                $uploadSuccess = 'Photo uploaded successfully!';
                $slug = $newSlug;
                $categoryTitle = $newTitle;
            } else {
                $uploadError = 'Failed to move uploaded file. Please try again.';
            }
        }
    } else {
        $uploadError = 'Please select a photo to upload.';
    }
}

if (hasRole('super_admin') && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    requireCsrfToken();
    $photoId = (int)($_POST['photo_id'] ?? 0);
    if ($photoId > 0) {
        $photo = $db->fetchOne("SELECT photo_path FROM gallery_photos WHERE id = ?", [$photoId]);
        if ($photo) {
            $barePath = str_replace(['uploads/gallery/', 'uploads/'], '', (string)$photo['photo_path']);
            $fullPath = GALLERY_PATH . $barePath;
            if ($barePath && file_exists($fullPath)) unlink($fullPath);
            $db->delete("DELETE FROM gallery_photos WHERE id = ?", [$photoId]);
            $uploadSuccess = 'Photo deleted.';
        }
    }
}

$photos = $db->fetchAll("SELECT * FROM gallery_photos WHERE category_slug = ? ORDER BY sort_order ASC, id ASC", [$slug]);

$pageTitle = $categoryTitle . ' - Gallery';
include __DIR__ . '/../templates/public_header.php';
?>

<section class="media-hero">
    <div class="hero-bg"></div>
    <div class="container">
        <span class="hero-badge">Photo Gallery</span>
        <h1><?= htmlspecialchars($categoryTitle) ?></h1>
        <p>Browse photos from this category of the National County Sports Meet.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="mb-4">
            <a href="<?= APP_URL ?>pages/media.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Media</a>
        </div>

        <?php if ($uploadSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $uploadSuccess ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($uploadError): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $uploadError ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (hasRole('super_admin')): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-upload me-2"></i>Upload Photo</h6>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="upload_photo" value="1">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category Slug</label>
                            <input type="text" name="category_slug" class="form-control" value="<?= htmlspecialchars($slug) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category Title</label>
                            <input type="text" name="category_title" class="form-control" value="<?= htmlspecialchars($categoryTitle) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Caption</label>
                            <input type="text" name="caption" class="form-control" placeholder="Optional caption...">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($photos)): ?>
            <div class="text-center py-5">
                <i class="bi bi-image text-muted" style="font-size:4rem;"></i>
                <h4 class="mt-3 text-muted">No photos yet</h4>
                <p class="text-muted">Photos for this category will appear here.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($photos as $idx => $photo): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="position-relative rounded overflow-hidden shadow-sm" style="cursor:pointer;" onclick="openLightbox(<?= $idx ?>)">
                        <img src="<?= sanitize(mediaUrl('uploads/gallery/', $photo['photo_path'])) ?>" alt="<?= htmlspecialchars($photo['caption'] ?: $categoryTitle) ?>" class="w-100" style="height:200px;object-fit:cover;">
                        <?php if (!empty($photo['caption'])): ?>
                        <div class="position-absolute bottom-0 start-0 end-0 p-2" style="background:linear-gradient(transparent,rgba(0,0,0,0.7));">
                            <small class="text-white"><?= htmlspecialchars($photo['caption']) ?></small>
                        </div>
                        <?php endif; ?>
                        <?php if (hasRole('super_admin')): ?>
                        <form method="POST" class="position-absolute top-0 end-0 m-1" onsubmit="return confirm('Delete this photo?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_photo" value="1">
                            <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" style="padding:0.2rem 0.4rem;font-size:0.7rem;"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 py-2">
                <h6 class="modal-title text-white" id="lightboxCaption"></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="lightboxImg" src="" class="img-fluid" style="max-height:75vh;" alt="">
            </div>
            <div class="modal-footer border-0 justify-content-center py-2">
                <button class="btn btn-outline-light btn-sm me-2" onclick="navLightbox(-1)"><i class="bi bi-chevron-left"></i></button>
                <span class="text-white-50 small" id="lightboxCount"></span>
                <button class="btn btn-outline-light btn-sm ms-2" onclick="navLightbox(1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
const photos = <?= json_encode(array_map(function($p) use ($categoryTitle) {
    return ['src' => mediaUrl('uploads/gallery/', $p['photo_path']), 'caption' => $p['caption'] ?: $categoryTitle];
}, $photos)) ?>;
let currentIdx = 0;

function openLightbox(idx) {
    currentIdx = idx;
    updateLightbox();
    new bootstrap.Modal(document.getElementById('lightboxModal')).show();
}

function navLightbox(dir) {
    currentIdx = (currentIdx + dir + photos.length) % photos.length;
    updateLightbox();
}

function updateLightbox() {
    const p = photos[currentIdx];
    document.getElementById('lightboxImg').src = p.src;
    document.getElementById('lightboxCaption').textContent = p.caption;
    document.getElementById('lightboxCount').textContent = (currentIdx + 1) + ' / ' + photos.length;
}

document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('lightboxModal');
    if (modal.classList.contains('show')) {
        if (e.key === 'ArrowLeft') navLightbox(-1);
        if (e.key === 'ArrowRight') navLightbox(1);
    }
});
</script>

<?php include __DIR__ . '/../templates/public_footer.php'; ?>
