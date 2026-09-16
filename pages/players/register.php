<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
if (!hasRole(['super_admin', 'county_coordinator']) || isCoordViewer()) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: ' . APP_URL . 'pages/dashboard.php');
    exit;
}

$db = getDb();
$user = getCurrentUser();
if (!$user) {
    $_SESSION['error'] = 'Your session has expired. Please log in again.';
    header('Location: ' . APP_URL . 'auth/login.php');
    exit;
}

// Force group filter for county coordinators
if (isAdminRole()) {
    $_GET['group'] = $_SESSION['user_group_label'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $errors = [];

    $full_name = sanitize($_POST['full_name'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $nationality = sanitize($_POST['nationality'] ?? 'Liberian');
    $year_of_nscm = $_POST['year_of_nscm'] ?? '';
    $age = (int)($_POST['age'] ?? 0);
    $city = sanitize($_POST['city'] ?? '');
    $last_club = sanitize($_POST['last_club'] ?? '');
    $current_club = sanitize($_POST['current_club'] ?? '');
    $county_id = (int)($_POST['county_id'] ?? 0);
    $primary_position = sanitize($_POST['primary_position'] ?? '');
    $sport_discipline_id = (int)($_POST['sport_discipline_id'] ?? 0);
    $action = $_POST['action'] ?? 'draft';

    $formData = compact(['full_name', 'date_of_birth', 'gender', 'nationality', 'year_of_nscm', 'age', 'city', 'last_club', 'current_club', 'county_id', 'primary_position', 'sport_discipline_id']);

    if (empty($full_name)) $errors[] = 'Full name is required.';
    if (empty($date_of_birth)) $errors[] = 'Date of birth is required.';
    if (empty($gender)) $errors[] = 'Gender is required.';
    if (empty($nationality)) $errors[] = 'Nationality is required.';
    if (empty($year_of_nscm)) $errors[] = 'Year of NSCM is required.';
    if ($age <= 0) $errors[] = 'Age is required.';
    if (empty($city)) $errors[] = 'City is required.';
    if ($county_id <= 0) $errors[] = 'County is required.';
    if (empty($primary_position)) $errors[] = 'Primary level is required.';
    if ($sport_discipline_id <= 0) $errors[] = 'Sport discipline is required.';

    $photo_path = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = uploadPhoto($_FILES['photo']);
        if (!$upload['success']) {
            $errors[] = $upload['error'];
        } else {
            $photo_path = $upload['filename'];
        }
    }

    if (empty($errors)) {
        $status = ($action === 'submit') ? 'submitted' : 'draft';
        $playerId = $db->insert(
            "INSERT INTO players (full_name, date_of_birth, gender, nationality, year_of_nscm, age, city, last_club, current_club, county_id,
             primary_position, emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
             medical_fitness_status, medical_notes, photo_path,
             sport_discipline_id, registered_by, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$full_name, $date_of_birth, $gender, $nationality, $year_of_nscm, $age, $city, $last_club, $current_club, $county_id,
             $primary_position, '', '', '',
             'pending_review', '', $photo_path,
             $sport_discipline_id, $_SESSION['user_id'], $status]
        );

        $db->insert(
            "INSERT INTO approval_workflow (player_id, action, action_by, role_at_time, comments)
             VALUES (?, ?, ?, ?, ?)",
            [$playerId, $status === 'submitted' ? 'submit' : 'draft', $_SESSION['user_id'],
             $_SESSION['user_role'], 'Initial registration']
        );

        logActivity('register_player', "Registered player: $full_name (Status: $status)");

        $county = $db->fetchOne("SELECT name FROM counties WHERE id = ?", [$county_id]);
        $sport = $db->fetchOne("SELECT name, association_name FROM sports_disciplines WHERE id = ?", [$sport_discipline_id]);

        if ($status === 'submitted') {
            $assocAdmins = $db->fetchAll(
                "SELECT id FROM users WHERE role = 'association_admin' AND association_id = ? AND status = 'active'",
                [$sport_discipline_id]
            );
            foreach ($assocAdmins as $admin) {
                createNotification(
                    $admin['id'],
                    'New Player Registration',
                    "$full_name from {$county['name']} registered for {$sport['name']} is pending your approval.",
                    'info',
                    APP_URL . 'pages/approvals/pending.php'
                );
            }
        }

        // Auto-generate player card
        $cardGenerated = false;
        try { generatePlayerCard($playerId, 'file'); $cardGenerated = true; } catch (Exception $e) { $cardGenerated = false; }

        $msg = "Player <strong>$full_name</strong> registered successfully as <strong>$status</strong>.";
        if ($cardGenerated) {
            $cardUrl = APP_URL . 'uploads/cards/player_' . $playerId . '.pdf';
            $msg .= ' <a href="' . $cardUrl . '" target="_blank" class="alert-link"><i class="bi bi-credit-card"></i> Download Player Card</a>';
        }
        setFlash('success', $msg);
        redirect(APP_URL . 'pages/players/view.php?id=' . $playerId);
    }
}

$counties = getCounties();
$sports = getSports();

$selectedGroup = strtoupper($_GET['group'] ?? '');
$filteredCounties = $selectedGroup && in_array($selectedGroup, ['A','B','C','D'])
    ? array_filter($counties, fn($c) => $c['group_label'] === $selectedGroup)
    : $counties;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $formData = [
        'full_name' => '', 'date_of_birth' => '', 'gender' => '',
        'nationality' => 'Liberian', 'year_of_nscm' => '', 'age' => 0, 'city' => '', 'last_club' => '', 'current_club' => '', 'county_id' => 0,
        'primary_position' => '', 'sport_discipline_id' => 0,
    ];
}

$pageTitle = $selectedGroup ? "Register Player - Group $selectedGroup" : 'Register Player';
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<?php if ($selectedGroup): ?>
<div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3">
    <i class="bi bi-layers"></i>
    <span>Registering player for <strong>Group <?= $selectedGroup ?></strong> counties only. <a href="<?= APP_URL ?>pages/players/register.php" class="alert-link">Register for all groups</a>.</span>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>Please fix the following errors:</strong>
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
<?= csrfField() ?>
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Player Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3 text-center">
                            <label class="form-label d-block">1. Photo</label>
                            <div class="photo-upload">
                                <img src="<?= APP_URL ?>assets/images/default-avatar.svg" class="photo-preview" id="photoPreview" alt="Player Photo">
                                <input type="file" name="photo" class="photo-upload-input d-none" accept="image/jpeg,image/png,image/gif">
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="$('.photo-upload-input').click()">
                                    <i class="bi bi-camera"></i> Upload Photo
                                </button>
                                <small class="d-block text-muted">JPG, PNG, GIF. Max 2MB</small>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">2. Full Legal Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control" placeholder="e.g. John K. Smith" value="<?= sanitize($formData['full_name']) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">4. Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" value="<?= sanitize($formData['date_of_birth']) ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">5. Gender <span class="text-danger">*</span></label>
                                    <select name="gender" class="form-select" required>
                                        <option value="">Select</option>
                                        <option value="male" <?= $formData['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= $formData['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= $formData['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">6. Nationality <span class="text-danger">*</span></label>
                                    <input type="text" name="nationality" class="form-control" placeholder="e.g. Liberian" value="<?= sanitize($formData['nationality']) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">7. Year of NSCM <span class="text-danger">*</span></label>
                                    <select name="year_of_nscm" class="form-select" required>
                                        <option value="">Select Year</option>
                                        <option value="2023" <?= $formData['year_of_nscm'] === '2023' ? 'selected' : '' ?>>2023</option>
                                        <option value="2024" <?= $formData['year_of_nscm'] === '2024' ? 'selected' : '' ?>>2024</option>
                                        <option value="2025" <?= $formData['year_of_nscm'] === '2025' ? 'selected' : '' ?>>2025</option>
                                        <option value="2026" <?= $formData['year_of_nscm'] === '2026' ? 'selected' : '' ?>>2026</option>
                                        <option value="2027" <?= $formData['year_of_nscm'] === '2027' ? 'selected' : '' ?>>2027</option>
                                        <option value="2028" <?= $formData['year_of_nscm'] === '2028' ? 'selected' : '' ?>>2028</option>
                                        <option value="2029" <?= $formData['year_of_nscm'] === '2029' ? 'selected' : '' ?>>2029</option>
                                        <option value="2030" <?= $formData['year_of_nscm'] === '2030' ? 'selected' : '' ?>>2030</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">8. County of Representation <span class="text-danger">*</span></label>
                                    <select name="county_id" class="form-select select2" required>
                                        <option value="">Select County</option>
                                        <?php
                                        $allCounties = getCounties();
                                        $countyGroups = [];
                                        foreach ($allCounties as $county) {
                                            $countyGroups[$county['group_label']][] = $county;
                                        }
                                        foreach (['A', 'B', 'C', 'D'] as $g):
                                            if (!empty($countyGroups[$g])):
                                        ?>
                                        <optgroup label="Group <?= $g ?>">
                                            <?php foreach ($countyGroups[$g] as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $formData['county_id'] == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?> (Group <?= $g ?>)</option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">9. Age <span class="text-danger">*</span></label>
                                    <select name="age" id="age" class="form-select" required>
                                        <option value="">Age</option>
                                        <?php for ($i = 0; $i <= 35; $i++): ?>
                                        <option value="<?= $i ?>" <?= $formData['age'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <script>
                                    document.getElementById('date_of_birth').addEventListener('change', function() {
                                        var dob = new Date(this.value);
                                        var today = new Date();
                                        var age = today.getFullYear() - dob.getFullYear();
                                        var m = today.getMonth() - dob.getMonth();
                                        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
                                        var sel = document.getElementById('age');
                                        if (age >= 0 && age <= 35) {
                                            sel.value = age;
                                        } else if (age > 35) {
                                            sel.value = 35;
                                        } else {
                                            sel.value = '';
                                        }
                                    });
                                    </script>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">10. City/Town <span class="text-danger">*</span></label>
                                    <input type="text" name="city" class="form-control" placeholder="e.g. Monrovia" value="<?= sanitize($formData['city']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">11. Last Club</label>
                                    <input type="text" name="last_club" class="form-control" list="last_club_list" placeholder="e.g. Invincible Eleven" value="<?= sanitize($formData['last_club'] ?? '') ?>">
                                    <datalist id="last_club_list">
                                        <option value="Unattached">
                                        <option value="Invincible Eleven">
                                        <option value="LISCR FC">
                                        <option value="Watanga FC">
                                        <option value="MCSSAfrica">
                                        <option value="LPRC Oilers">
                                        <option value="Nimba FC">
                                        <option value="Mighty Barrolle">
                                        <option value="NPA Anchors">
                                        <option value="Saint Joseph Warriors">
                                    </datalist>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">12. Current Club</label>
                                    <input type="text" name="current_club" class="form-control" list="current_club_list" placeholder="e.g. LISCR FC" value="<?= sanitize($formData['current_club'] ?? '') ?>">
                                    <datalist id="current_club_list">
                                        <option value="Unattached">
                                        <option value="Invincible Eleven">
                                        <option value="LISCR FC">
                                        <option value="Watanga FC">
                                        <option value="MCSSAfrica">
                                        <option value="LPRC Oilers">
                                        <option value="Nimba FC">
                                        <option value="Mighty Barrolle">
                                        <option value="NPA Anchors">
                                        <option value="Saint Joseph Warriors">
                                    </datalist>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Sport & Level</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Sport Discipline <span class="text-danger">*</span></label>
                            <select name="sport_discipline_id" class="form-select select2" required>
                                <option value="">Select Sport</option>
                                <?php foreach ($sports as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $formData['sport_discipline_id'] === $s['id'] ? 'selected' : '' ?>><?= sanitize($s['name']) ?> (<?= sanitize($s['association_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">13. Primary Level <span class="text-danger">*</span></label>
                            <select name="primary_position" class="form-select" required>
                                <option value="">Select Level</option>
                                <option value="1st Division" <?= $formData['primary_position'] === '1st Division' ? 'selected' : '' ?>>1st Division</option>
                                <option value="2nd Division" <?= $formData['primary_position'] === '2nd Division' ? 'selected' : '' ?>>2nd Division</option>
                                <option value="3rd Division" <?= $formData['primary_position'] === '3rd Division' ? 'selected' : '' ?>>3rd Division</option>
                                <option value="4th Division" <?= $formData['primary_position'] === '4th Division' ? 'selected' : '' ?>>4th Division</option>
                                <option value="Mass" <?= $formData['primary_position'] === 'Mass' ? 'selected' : '' ?>>Mass</option>
                                <option value="Virgin" <?= $formData['primary_position'] === 'Virgin' ? 'selected' : '' ?>>Virgin</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted"><span class="text-danger">*</span> Required fields</small>
                    </div>
                    <div>
                        <button type="submit" name="action" value="draft" class="btn btn-secondary me-2">
                            <i class="bi bi-save"></i> Save as Draft
                        </button>
                        <button type="submit" name="action" value="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Submit for Approval
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
