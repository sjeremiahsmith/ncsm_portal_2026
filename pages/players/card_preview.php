<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('No player specified.');

$db = getDb();
$player = $db->fetchOne("SELECT p.*, c.name as county_name, c.group_label, s.name as sport_name FROM players p JOIN counties c ON p.county_id = c.id JOIN sports_disciplines s ON p.sport_discipline_id = s.id WHERE p.id = ?", [$id]);
if (!$player) die('Player not found.');

$isSuperAdmin = hasRole('super_admin');
$isOwnCounty = isAdminRole() && isset($_SESSION['user_group_label']) && $_SESSION['user_group_label'] === $player['group_label'];
$isSportsCoord = hasRole('sports_coord');

if (!($isSuperAdmin || $isOwnCounty || $isSportsCoord)) die('Access denied.');

// Regenerate card if needed (only if Dompdf is available)
$cardPdfPath = CARD_PATH . 'player_' . $id . '.pdf';
if ((isset($_GET['regenerate']) || !file_exists($cardPdfPath)) && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    try { generatePlayerCard($id, 'file'); } catch (Exception $e) { /* PDF generation skipped */ }
}

$pageTitle = 'Player Card - ' . $player['full_name'];
$frontImgUrl = APP_URL . 'pages/players/card_image.php?id=' . $id . '&side=front&t=' . time();
$backImgUrl = APP_URL . 'pages/players/card_image.php?id=' . $id . '&side=back&t=' . time();
$cardPdfUrl = APP_URL . 'uploads/cards/player_' . $id . '.pdf';
include __DIR__ . '/../../templates/header.php';
?>

<style>
@media print {
    body * { visibility:hidden; }
    .print-area, .print-area * { visibility:visible; }
    .print-area { position:absolute; left:0; top:0; width:100%; }
    .no-print { display:none !important; }
    .card-sheet { page-break-after:always; }
    .card-sheet:last-child { page-break-after:auto; }
}
.card-preview { max-width:340px; width:100%; border-radius:12px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,0.15); }
.card-preview img { width:100%; display:block; }
.card-sheet { width:210mm; min-height:297mm; margin:0 auto; padding:8mm 5mm; }
.card-pair { display:flex; gap:8mm; justify-content:center; margin-bottom:8mm; }
.card-pair .card-wrapper { width:82mm; height:118mm; border-radius:6px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.card-pair .card-wrapper img { width:100%; height:100%; object-fit:contain; display:block; }
@media screen {
    .card-sheet { background:#f5f5f5; border-radius:8px; padding:5mm; margin-bottom:16px; }
}
</style>

<div class="no-print">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-credit-card me-2"></i>Player Card</h4>
        <div class="d-flex gap-2">
            <a href="?id=<?= $id ?>&regenerate=1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</a>
            <a href="card_image.php?id=<?= $id ?>&side=front&format=png" class="btn btn-sm btn-outline-primary" download><i class="bi bi-image me-1"></i>Front PNG</a>
            <a href="card_image.php?id=<?= $id ?>&side=back&format=png" class="btn btn-sm btn-outline-primary" download><i class="bi bi-image me-1"></i>Back PNG</a>
            <a href="<?= $cardPdfUrl ?>" class="btn btn-sm btn-outline-danger" target="_blank"><i class="bi bi-filetype-pdf me-1"></i>PDF</a>
            <button onclick="window.print()" class="btn btn-sm btn-dark"><i class="bi bi-printer me-1"></i>Print</button>
        </div>
    </div>
    <div class="alert alert-info py-2 small no-print">
        <i class="bi bi-info-circle me-1"></i>Front and back of the card are shown below.
    </div>
</div>

<!-- Preview -->
<div class="row g-4 mb-4 no-print">
    <div class="col-md-6">
        <div class="card-preview mx-auto">
            <img src="<?= $frontImgUrl ?>" alt="Card Front">
        </div>
        <p class="text-center text-muted small mt-2">Front</p>
    </div>
    <div class="col-md-6">
        <div class="card-preview mx-auto">
            <img src="<?= $backImgUrl ?>" alt="Card Back">
        </div>
        <p class="text-center text-muted small mt-2">Back</p>
    </div>
</div>

<!-- Print Area: single front/back pair -->
<div class="print-area">
    <div class="card-sheet">
        <div class="card-pair">
            <div class="card-wrapper"><img src="<?= $frontImgUrl ?>" alt="Front"></div>
            <div class="card-wrapper"><img src="<?= $backImgUrl ?>" alt="Back"></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
