<?php
require_once __DIR__ . '/db.php';

function getRoleLabel($role) {
    $map = [
        'county_coordinator' => 'Administration',
        'lofa_admin' => 'Lofa Admin',
        'bong_admin' => 'Bong Admin',
        'kru_admin' => 'Grand Kru Admin',
        'gedeh_admin' => 'Grand Gedeh Admin',
        'sports_coord' => 'Sports Coordinator',
    ];
    return $map[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

function isAdminRole() {
    return hasRole(['county_coordinator', 'lofa_admin', 'bong_admin', 'kru_admin', 'gedeh_admin']);
}

function isCoordViewer() {
    if (!hasRole('county_coordinator')) return false;
    return !in_array($_SESSION['username'] ?? '', ['gedeh_admin', 'bong_admin', 'lofa_admin', 'kru_admin']);
}

function isCountyAdmin() {
    if (!hasRole('county_coordinator')) return false;
    return in_array($_SESSION['username'] ?? '', ['gedeh_admin', 'bong_admin', 'lofa_admin', 'kru_admin']);
}

function getDb() {
    return Database::getInstance();
}

function ensureContactMessagesTable() {
    $db = getDb();
    $db->query("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT(11) NOT NULL AUTO_INCREMENT,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn() || !getCurrentUser()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . 'auth/login.php');
        exit;
    }
}

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    $roles = is_array($roles) ? $roles : [$roles];
    return in_array($_SESSION['user_role'] ?? '', $roles);
}

function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        $_SESSION['error'] = 'You do not have permission to access this page.';
        header('Location: ' . APP_URL . 'pages/dashboard.php');
        exit;
    }
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function requireCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('The form has expired. Please go back and try again.');
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;

    $user = getDb()->fetchOne(
        "SELECT u.*, c.name as county_name, s.name as sport_name, s.association_name
         FROM users u
         LEFT JOIN counties c ON u.county_id = c.id
         LEFT JOIN sports_disciplines s ON u.association_id = s.id
         WHERE u.id = ?",
        [$_SESSION['user_id']]
    );

    if (!$user) {
        $_SESSION = [];
        session_unset();
        session_destroy();
        return null;
    }

    return $user;
}

function mediaUrl($basePath, $storedPath) {
    $storedPath = (string)$storedPath;
    if ($storedPath === '' || strpos($storedPath, 'uploads/') === 0) {
        return $storedPath === '' ? '' : APP_URL . $storedPath;
    }
    return APP_URL . $basePath . $storedPath;
}

function videoEmbedUrl($url) {
    $url = trim($url);
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('/(?:vimeo\.com\/)(\d+)', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return $url;
}

function uploadPhoto($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'error' => 'Upload failed.'];

    if ($file['size'] > MAX_PHOTO_SIZE) return ['success' => false, 'error' => 'File too large. Max 2MB.'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_PHOTO_TYPES)) return ['success' => false, 'error' => 'Invalid file type. JPG, PNG, GIF only.'];

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('photo_') . '.' . $ext;
    $dest = PHOTO_PATH . $filename;

    if (!is_dir(PHOTO_PATH)) mkdir(PHOTO_PATH, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => true, 'filename' => $filename];
    }
    return ['success' => false, 'error' => 'Failed to save file.'];
}

function uploadDocument($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'error' => 'Upload failed.'];
    if ($file['size'] > MAX_DOCUMENT_SIZE) return ['success' => false, 'error' => 'File too large. Max 10MB.'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ALLOWED_DOCUMENT_TYPES)) return ['success' => false, 'error' => 'Invalid file type.'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('doc_') . '.' . $ext;
    $dest = DOCUMENT_PATH . $filename;
    if (!is_dir(DOCUMENT_PATH)) mkdir(DOCUMENT_PATH, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => true, 'filename' => $filename, 'original_name' => $file['name'], 'mime' => $mime, 'size' => $file['size']];
    }
    return ['success' => false, 'error' => 'Failed to save file.'];
}

function logActivity($action, $description = '') {
    $db = getDb();
    $db->insert(
        "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)",
        [$_SESSION['user_id'], $action, $description, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']
    );
}

function createNotification($userId, $title, $message, $type = 'info', $link = '') {
    return getDb()->insert(
        "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)",
        [$userId, $title, $message, $type, $link]
    );
}

function getUnreadNotificationCount($userId) {
    return getDb()->fetchOne(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
        [$userId]
    )['count'];
}

function getRecentNotifications($userId, $limit = 5) {
    return getDb()->fetchAll(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
        [$userId, $limit]
    );
}

function getCountyGroupLabel($groupId) {
    $labels = ['A' => 'Group A', 'B' => 'Group B', 'C' => 'Group C', 'D' => 'Group D'];
    return $labels[$groupId] ?? 'Unknown';
}

function getGroupCounties($groupLabel) {
    return getDb()->fetchAll(
        "SELECT * FROM counties WHERE group_label = ? ORDER BY name",
        [$groupLabel]
    );
}

function getAllGroups() {
    $groups = [];
    foreach (['A', 'B', 'C', 'D'] as $label) {
        $groups[$label] = getGroupCounties($label);
    }
    return $groups;
}

function getPlayerCountByStatus($status = null, $sportId = null) {
    $sql = "SELECT COUNT(*) as count FROM players";
    $conditions = [];
    $params = [];
    if ($status) {
        $conditions[] = "status = ?";
        $params[] = $status;
    }
    if ($sportId) {
        $conditions[] = "sport_discipline_id = ?";
        $params[] = $sportId;
    }
    if ($conditions) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    return getDb()->fetchOne($sql, $params)['count'];
}

function getPlayerCountByCounty($countyId) {
    return getDb()->fetchOne(
        "SELECT COUNT(*) as count FROM players WHERE county_id = ?",
        [$countyId]
    )['count'];
}

function getPlayerCountBySport($sportId) {
    return getDb()->fetchOne(
        "SELECT COUNT(*) as count FROM players WHERE sport_discipline_id = ?",
        [$sportId]
    )['count'];
}

function getCounties() {
    $db = getDb();
    $counties = $db->fetchAll("SELECT * FROM counties ORDER BY group_label, name");

    if (!empty($counties)) {
        return $counties;
    }

    $defaultCounties = [
        ['Bomi', 'D', 'BM'],
        ['Bong', 'B', 'BG'],
        ['Gbarpolu', 'A', 'GP'],
        ['Grand Bassa', 'C', 'GB'],
        ['Grand Cape Mount', 'B', 'GM'],
        ['Grand Gedeh', 'A', 'GG'],
        ['Grand Kru', 'D', 'GK'],
        ['Lofa', 'C', 'LF'],
        ['Margibi', 'D', 'MR'],
        ['Maryland', 'B', 'ML'],
        ['Montserrado', 'C', 'MG'],
        ['Nimba', 'A', 'NM'],
        ['River Cess', 'B', 'RC'],
        ['River Gee', 'A', 'RG'],
        ['Sinoe', 'C', 'SN'],
    ];

    foreach ($defaultCounties as $county) {
        $db->insert(
            "INSERT INTO counties (name, group_label, code) VALUES (?, ?, ?)",
            $county
        );
    }

    return $db->fetchAll("SELECT * FROM counties ORDER BY group_label, name");
}

function getSports() {
    $db = getDb();
    $sports = $db->fetchAll("SELECT * FROM sports_disciplines WHERE status = 'active' ORDER BY name");

    if (!empty($sports)) {
        return $sports;
    }

    $defaultSports = [
        ['Football', 'Liberia Football Association', 'LFA', 'Football discipline'],
        ['Kickball', 'Liberia Kickball Association', 'LKA', 'Kickball discipline'],
        ['Basketball', 'Liberia Basketball Association', 'LBA', 'Basketball discipline'],
        ['Athletics', 'Liberia Athletics Association', 'LAA', 'Athletics discipline'],
    ];

    foreach ($defaultSports as $sport) {
        $db->insert(
            "INSERT INTO sports_disciplines (name, association_name, association_code, description) VALUES (?, ?, ?, ?)",
            $sport
        );
    }

    return $db->fetchAll("SELECT * FROM sports_disciplines WHERE status = 'active' ORDER BY name");
}

function getAssociations() {
    return getDb()->fetchAll("SELECT DISTINCT association_name, association_code FROM sports_disciplines WHERE status = 'active'");
}

function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year(s) ago';
    if ($diff->m > 0) return $diff->m . ' month(s) ago';
    if ($diff->d > 0) return $diff->d . ' day(s) ago';
    if ($diff->h > 0) return $diff->h . ' hour(s) ago';
    if ($diff->i > 0) return $diff->i . ' minute(s) ago';
    return 'just now';
}

function getStatusBadge($status) {
    $map = [
        'draft' => 'secondary',
        'submitted' => 'info',
        'approved' => 'success',
        'rejected' => 'danger',
        'pending_review' => 'warning',
        'fit' => 'success',
        'unfit' => 'danger',
        'active' => 'success',
        'inactive' => 'secondary',
    ];
    $class = $map[$status] ?? 'secondary';
    return "<span class='badge bg-{$class}'>{$status}</span>";
}

function paginate($total, $page, $perPage = 20) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'totalPages' => $totalPages,
        'offset' => $offset,
    ];
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getPlayerPhotoUrl($photoPath) {
    if ($photoPath && file_exists(PHOTO_PATH . $photoPath)) {
        return APP_URL . 'uploads/photos/' . $photoPath;
    }
    return APP_URL . 'assets/images/default-avatar.svg';
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function setFlash($key, $message) {
    $_SESSION['flash'][$key] = $message;
}

function getCountyFlagUrl($countyName) {
    $map = [
        'Bomi' => 'Bomi.png',
        'Bong' => 'Bong.png',
        'Gbarpolu' => 'Gbarpolu.png',
        'Grand Bassa' => 'Grand Bassa.png',
        'Grand Cape Mount' => 'Grand Cape Mount.png',
        'Grand Gedeh' => 'Grand Gedeh.png',
        'Grand Kru' => 'Grand Kru.png',
        'Lofa' => 'Lofa.png',
        'Margibi' => 'Margibi.png',
        'Maryland' => 'Maryland.png',
        'Montserrado' => 'Montserrado.png',
        'Nimba' => 'Nimba.png',
        'River Cess' => 'Rivercess.jpg',
        'River Gee' => 'River Gee.png',
        'Sinoe' => 'Sinoe.png',
    ];
    $file = $map[$countyName] ?? null;
    if ($file && file_exists(__DIR__ . '/../assets/images/' . $file)) {
        return APP_URL . 'assets/images/' . $file;
    }
    return null;
}

function getFlash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function getCardDownloadUrl($playerId) {
    $path = 'uploads/cards/player_' . (int)$playerId . '.pdf';
    if (file_exists(CARD_PATH . 'player_' . (int)$playerId . '.pdf')) {
        return APP_URL . $path;
    }
    return null;
}

function generatePlayerCard($playerId, $mode = 'file') {
    $db = getDb();
    $player = $db->fetchOne("
        SELECT p.*, c.name as county_name, c.group_label,
               s.name as sport_name, s.association_name,
               u.full_name as registered_by_name
        FROM players p
        JOIN counties c ON p.county_id = c.id
        JOIN sports_disciplines s ON p.sport_discipline_id = s.id
        JOIN users u ON p.registered_by = u.id
        WHERE p.id = ?
    ", [$playerId]);

    if (!$player) return $mode === 'file' ? false : null;

    $photoUrl = getPlayerPhotoUrl($player['photo_path']);
    $flagUrl = getCountyFlagUrl($player['county_name']);

    $base = __DIR__ . '/..';
    $logoPath = $base . '/assets/images/ncsm.png';
    $photoPath = $player['photo_path'] && file_exists($base . '/uploads/photos/' . $player['photo_path'])
        ? $base . '/uploads/photos/' . $player['photo_path']
        : $base . '/assets/images/default-avatar.svg';
    $flagPath = $flagUrl ? $base . '/assets/images/' . basename(parse_url($flagUrl, PHP_URL_PATH)) : '';

    $dob = $player['date_of_birth'] ? date('M d, Y', strtotime($player['date_of_birth'])) : 'N/A';
    $cardId = 'NCSM-' . str_pad($player['id'], 5, '0', STR_PAD_LEFT);

    $p_ = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };

    // Generate QR code pointing to player profile (Google Charts API)
    $profileUrl = APP_URL . 'pages/players/view.php?id=' . $player['id'];
    $qrImgTag = '';
    $qrApiUrl = 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($profileUrl) . '&choe=UTF-8';
    $qrImgTag = '<img src="' . $qrApiUrl . '" alt="QR" style="width:95px;height:95px;border:3px solid #fff;border-radius:8px;">';

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
@page { margin: 0; padding: 0; size: A4 portrait; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:DejaVu Sans, sans-serif; padding:20px; }
.page { width:210mm; min-height:297mm; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:24px; padding:20mm 10mm; }
.card-front {
    width:380px; height:610px; border-radius:18px; overflow:hidden;
    background: linear-gradient(145deg, #fafafa, #e8e8e8);
    box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    position:relative;
}
.card-front::before {
    content:""; position:absolute; top:0; left:0; right:0; height:165px;
    background: linear-gradient(135deg, #1a1a1a 0%, #333 50%, #1a1a1a 100%);
    border-radius:0 0 24px 24px;
}
.card-front .watermark {
    position:absolute; bottom:40px; right:-10px; opacity:0.04;
    font-size:160px; font-weight:900; color:#000; line-height:1;
    transform:rotate(-15deg); pointer-events:none;
}
.top-bar { position:relative; display:flex; align-items:center; padding:20px 22px 14px; z-index:1; }
.top-bar .logo { width:70px; height:70px; border-radius:50%; border:2px solid rgba(255,255,255,0.3); object-fit:cover; }
.top-bar .org { margin-left:12px; color:#fff; }
.top-bar .org .name { font-size:18px; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; line-height:1.2; }
.top-bar .org .sub { font-size:9px; opacity:0.65; letter-spacing:0.06em; text-transform:uppercase; }
.top-bar .flag { margin-left:auto; width:80px; height:55px; border-radius:6px; object-fit:cover; border:2px solid rgba(255,255,255,0.2); }
.photo-row { position:relative; display:flex; padding:0 24px; margin-top:-8px; z-index:1; }
.photo-frame { width:100px; height:125px; border-radius:10px; border:3px solid #fff; box-shadow:0 4px 12px rgba(0,0,0,0.15); overflow:hidden; flex-shrink:0; background:#fff; }
.photo-frame img { width:100%; height:100%; object-fit:cover; display:block; }
.details { margin-left:16px; padding-top:6px; flex:1; min-width:0; }
.details .name { font-size:15px; font-weight:800; color:#1a1a1a; line-height:1.2; word-break:break-word; }
.details .country { font-size:8px; color:#888; margin-top:1px; }
.details .card-id { display:inline-block; margin-top:4px; background:#1a1a1a; color:#fff; font-size:10px; font-weight:700; padding:3px 12px; border-radius:10px; letter-spacing:0.04em; }
.info-grid { position:relative; margin:14px 24px 0; z-index:1; }
.info-row { display:flex; align-items:baseline; padding:3px 0; border-bottom:1px solid #e0e0e0; }
.info-row:last-child { border-bottom:none; }
.info-row .label { font-size:7px; text-transform:uppercase; color:#888; letter-spacing:0.04em; width:85px; flex-shrink:0; font-weight:600; }
.info-row .value { font-size:8.5px; color:#222; font-weight:500; }
.badge-group { display:inline-block; margin-top:3px; padding:2px 8px; border-radius:8px; font-size:7px; font-weight:700; text-transform:uppercase; }
.badge-a { background:#e74c3c; color:#fff; }
.badge-b { background:#3498db; color:#fff; }
.badge-c { background:#C8A032; color:#fff; }
.badge-d { background:#1B5E20; color:#fff; }
.bottom-strip { position:absolute; bottom:0; left:0; right:0; background:linear-gradient(135deg,#1a1a1a,#333); padding:14px 24px; text-align:center; }
.bottom-strip .iss { font-size:10px; color:rgba(255,255,255,0.5); letter-spacing:0.05em; text-transform:uppercase; }
.bottom-strip .iss strong { color:#fff; font-weight:700; }
.card-back {
    width:380px; height:610px; border-radius:18px; overflow:hidden;
    background: linear-gradient(145deg, #1a1a1a, #2d2d2d);
    box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    color:#fff; position:relative;
}
.card-back .back-header { text-align:center; padding:18px 22px 10px; border-bottom:1px solid rgba(255,255,255,0.08); }
.card-back .back-header .title { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; }
.card-back .back-header .sub { font-size:6.5px; opacity:0.5; letter-spacing:0.04em; }
.card-back .back-body { padding:12px 24px; }
.back-section { margin-bottom:10px; }
.back-section .sec-title { font-size:7px; text-transform:uppercase; letter-spacing:0.05em; color:rgba(255,255,255,0.4); font-weight:700; margin-bottom:3px; padding-bottom:2px; border-bottom:1px solid rgba(255,255,255,0.06); }
.back-row { display:flex; padding:2px 0; font-size:7.5px; }
.back-row .bl { width:100px; color:rgba(255,255,255,0.4); flex-shrink:0; }
.back-row .bv { color:rgba(255,255,255,0.85); font-weight:500; }
.card-back .barcode-line { text-align:center; padding:10px 0; border-top:1px solid rgba(255,255,255,0.06); }
.card-back .barcode-line .bar { display:inline-block; width:2px; height:30px; background:rgba(255,255,255,0.3); margin:0 1.5px; border-radius:1px; }
.card-back .barcode-line .id-num { font-size:7px; letter-spacing:0.15em; margin-top:4px; opacity:0.4; }
.card-back .watermark-back { position:absolute; bottom:30px; right:10px; opacity:0.03; font-size:120px; font-weight:900; color:#fff; transform:rotate(-15deg); pointer-events:none; }
.seal { position:absolute; bottom:50px; left:50%; transform:translateX(-50%); width:60px; height:60px; border-radius:50%; border:2px solid rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size:7px; text-transform:uppercase; letter-spacing:0.1em; color:rgba(255,255,255,0.15); text-align:center; line-height:1.2; }
</style></head><body>

<div class="page">
    <div class="card-front">
        <div class="watermark">NCSM</div>
        <div class="top-bar">
            <img class="logo" src="' . $logoPath . '" alt="NCSM">
            <div class="org">
                <div class="name">National County<br>Sports Meet</div>
                <div class="sub">Republic of Liberia</div>
            </div>
            ' . ($flagPath ? '<img class="flag" src="' . $flagPath . '" alt="' . $p_($player['county_name']) . '">' : '') . '
        </div>
        <div class="photo-row">
            <div class="photo-frame"><img src="' . $photoPath . '" alt="Photo"></div>
            <div class="details">
                <div class="name">' . $p_($player['full_name']) . '</div>
                <div class="country">' . $p_($player['county_name']) . ' County</div>
                <div class="card-id">' . $cardId . '</div>
            </div>
        </div>
        <div class="info-grid">
            <div class="info-row"><span class="label">Sport</span><span class="value">' . $p_($player['sport_name']) . ' (' . $p_($player['association_name']) . ')</span></div>
            <div class="info-row"><span class="label">Position</span><span class="value">' . $p_($player['primary_position']) . '</span></div>
            <div class="info-row"><span class="label">DOB / Age</span><span class="value">' . $dob . ' / ' . (int)$player['age'] . ' yrs</span></div>
            <div class="info-row"><span class="label">Gender</span><span class="value">' . ucfirst($p_($player['gender'])) . '</span></div>
            <div class="info-row"><span class="label">NSCM Year</span><span class="value">' . $p_($player['year_of_nscm']) . '</span></div>
            <div class="info-row"><span class="label">County Group</span><span class="value"><span class="badge-group badge-' . strtolower($p_($player['group_label'])) . '">Group ' . $p_($player['group_label']) . '</span></span></div>
            <div class="info-row"><span class="label">Fitness</span><span class="value">' . ucfirst(str_replace('_', ' ', $p_($player['medical_fitness_status']))) . '</span></div>
            <div class="info-row"><span class="label">Club</span><span class="value">' . ($p_($player['current_club']) ?: 'N/A') . '</span></div>
        </div>
        <div class="bottom-strip">
            <div class="iss">Issued by <strong>NCSM Liberia</strong> &middot; ' . date('Y') . '</div>
        </div>
    </div>

    <div class="card-back">
        <div class="watermark-back">NCSM</div>
        <div class="back-header">
            <div class="title">National County Sports Meet</div>
            <div class="sub">Republic of Liberia &middot; Player Identification Card</div>
        </div>
        <div class="back-body">
            <div class="back-section">
                <div class="sec-title">Medical Information</div>
                <div class="back-row"><span class="bl">Fitness Status</span><span class="bv">' . ucfirst(str_replace('_', ' ', $p_($player['medical_fitness_status']))) . '</span></div>
                ' . (!empty($player['medical_notes']) ? '<div class="back-row"><span class="bl">Notes</span><span class="bv">' . $p_($player['medical_notes']) . '</span></div>' : '') . '
            </div>
            <div class="back-section">
                <div class="sec-title">Registration</div>
                <div class="back-row"><span class="bl">By</span><span class="bv">' . $p_($player['registered_by_name']) . '</span></div>
                <div class="back-row"><span class="bl">Date</span><span class="bv">' . formatDate($player['created_at'], 'M d, Y') . '</span></div>
                <div class="back-row"><span class="bl">Status</span><span class="bv">' . ucfirst($p_($player['status'])) . '</span></div>
            </div>
            <div class="back-section">
                <div class="sec-title">Player Details</div>
                <div class="back-row"><span class="bl">City</span><span class="bv">' . ($p_($player['city']) ?: 'N/A') . '</span></div>
                <div class="back-row"><span class="bl">Last Club</span><span class="bv">' . ($p_($player['last_club']) ?: 'N/A') . '</span></div>
            </div>
        </div>
        <div class="seal">NCSM<br>Liberia</div>
        ' . ($qrImgTag ? '<div style="text-align:center;padding:6px 0;"><div style="display:inline-block;background:#fff;padding:4px;border-radius:8px;">' . $qrImgTag . '</div><div style="font-size:6px;color:rgba(255,255,255,0.4);margin-top:3px;letter-spacing:0.04em;">Scan to view player profile</div></div>' : '') . '
        <div class="barcode-line">
            ' . implode('', array_map(function($i) { $h = 15 + ($i % 3) * 5; return "<span class=\"bar\" style=\"height:{$h}px;\"></span>"; }, range(0, 39))) . '
            <div class="id-num">' . $cardId . '</div>
        </div>
    </div>
</div>

</body></html>';

    require_once __DIR__ . '/../vendor/autoload.php';

    $dompdf = new Dompdf\Dompdf();
    $dompdf->getOptions()->setIsRemoteEnabled(true);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    if ($mode === 'stream') {
        $filename = 'player_card_' . $player['id'] . '_' . date('Y-m-d') . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    $outPath = CARD_PATH . 'player_' . $player['id'] . '.pdf';
    if (!is_dir(CARD_PATH)) mkdir(CARD_PATH, 0755, true);
    file_put_contents($outPath, $dompdf->output());
    return true;
}

function imageroundrect($img, $x1, $y1, $x2, $y2, $radius, $color) {
    imagefilledrectangle($img, (int)($x1 + $radius), (int)$y1, (int)($x2 - $radius), (int)$y2, $color);
    imagefilledrectangle($img, (int)$x1, (int)($y1 + $radius), (int)$x2, (int)($y2 - $radius), $color);
    imagefilledellipse($img, (int)($x1 + $radius), (int)($y1 + $radius), (int)($radius * 2), (int)($radius * 2), $color);
    imagefilledellipse($img, (int)($x2 - $radius), (int)($y1 + $radius), (int)($radius * 2), (int)($radius * 2), $color);
    imagefilledellipse($img, (int)($x1 + $radius), (int)($y2 - $radius), (int)($radius * 2), (int)($radius * 2), $color);
    imagefilledellipse($img, (int)($x2 - $radius), (int)($y2 - $radius), (int)($radius * 2), (int)($radius * 2), $color);
}

function cardCoord($value) {
    return (int)round((float)$value);
}

function generatePlayerCardImage($playerId, $format = 'png') {
    if (!function_exists('imagecreatetruecolor')) {
        error_log('card_image.php: GD library not available');
        return false;
    }

    $db = getDb();
    $player = $db->fetchOne("
        SELECT p.*, c.name as county_name, c.group_label,
               s.name as sport_name, s.association_name
        FROM players p
        JOIN counties c ON p.county_id = c.id
        JOIN sports_disciplines s ON p.sport_discipline_id = s.id
        WHERE p.id = ?
    ", [$playerId]);

    if (!$player) return false;

    $cardW = 700;
    $cardH = 1050;

    $baseDir = __DIR__ . '/..';
    $fontPaths = [
        $baseDir . '/assets/fonts/arial.ttf',
        'C:/Windows/Fonts/arial.ttf',
        'C:\\Windows\\Fonts\\arial.ttf',
        '/usr/share/fonts/truetype/msttcorefonts/arial.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/TTF/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
    ];
    $fontBdPaths = [
        $baseDir . '/assets/fonts/arialbd.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
        'C:\\Windows\\Fonts\\arialbd.ttf',
        '/usr/share/fonts/truetype/msttcorefonts/arial_bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
    ];
    $font = null;
    $fontBd = null;
    foreach ($fontPaths as $fp) { if (file_exists($fp)) { $font = $fp; break; } }
    foreach ($fontBdPaths as $fp) { if (file_exists($fp)) { $fontBd = $fp; break; } }
    if (!$fontBd) $fontBd = $font;
    if (!$font) {
        error_log('card_image.php: No TTF font found on server');
        return false;
    }

    $cardId = 'NCSM-' . str_pad($player['id'], 5, '0', STR_PAD_LEFT);

    $img = imagecreatetruecolor($cardW, $cardH);
    imageantialias($img, true);

    $dark  = imagecolorallocate($img, 18, 18, 30);
    $white = imagecolorallocate($img, 255, 255, 255);
    $gold  = imagecolorallocate($img, 212, 175, 55);
    $goldLight = imagecolorallocate($img, 245, 226, 150);
    $silver = imagecolorallocate($img, 200, 200, 210);
    $textDark = imagecolorallocate($img, 30, 30, 40);
    $textGray = imagecolorallocate($img, 120, 120, 135);
    $textDim = imagecolorallocate($img, 160, 160, 175);
    $bgLight = imagecolorallocate($img, 245, 245, 250);
    $bgCard = imagecolorallocate($img, 250, 250, 255);
    $border = imagecolorallocate($img, 220, 220, 230);

    $skyBlue = imagecolorallocate($img, 135, 206, 250);
    imagefill($img, 0, 0, $skyBlue);

    // === WATERMARK: 15 faint NCSM logos across background ===
    $wmPath = $baseDir . '/assets/images/ncsm.png';
    if (file_exists($wmPath)) {
        $wmSrc = @imagecreatefrompng($wmPath);
        if ($wmSrc) {
            $wmSizeW = 100;
            $wmSizeH = 100;
            $wmCols = 5;
            $wmRows = 3;
            $wmOpacity = 15;
            $wmSpacingX = $cardW / $wmCols;
            $wmSpacingY = $cardH / $wmRows;
            $wmTmp = imagecreatetruecolor($wmSizeW, $wmSizeH);
            imagecopyresampled($wmTmp, $wmSrc, 0, 0, 0, 0, $wmSizeW, $wmSizeH, imagesx($wmSrc), imagesy($wmSrc));
            imagedestroy($wmSrc);
            for ($wr = 0; $wr < $wmRows; $wr++) {
                for ($wc = 0; $wc < $wmCols; $wc++) {
                    $wmx = (int)($wc * $wmSpacingX + ($wmSpacingX - $wmSizeW) / 2);
                    $wmy = (int)($wr * $wmSpacingY + ($wmSpacingY - $wmSizeH) / 2);
                    imagecopymerge($img, $wmTmp, $wmx, $wmy, 0, 0, $wmSizeW, $wmSizeH, $wmOpacity);
                }
            }
            imagedestroy($wmTmp);
        }
    }

    // === PREMIUM HEADER: deeper blue gradient with gold accent ===
    for ($y = 0; $y < 180; $y++) {
        $r = (int)(60 + ($y / 180) * 40);
        $g = (int)(130 + ($y / 180) * 50);
        $b = (int)(200 + ($y / 180) * 35);
        $lineColor = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $cardW, $y, $lineColor);
    }

    // Gold accent stripe
    imagefilledrectangle($img, 0, 175, $cardW, 182, $gold);
    // Thin gold line below
    imagefilledrectangle($img, 0, 183, $cardW, 184, $goldLight);

    // NCSM logo
    $logoPath = $baseDir . '/assets/images/ncsm.png';
    if (file_exists($logoPath)) {
        $logo = @imagecreatefrompng($logoPath);
        if ($logo) {
            $shadow = imagecolorallocatealpha($img, 0, 0, 0, 60);
            imagecopyresampled($img, $logo, 26, 24, 0, 0, 70, 70, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        }
    }

    // Title text
    imagettftext($img, 26, 0, 105, 60, $white, $fontBd, '2026 NATIONAL COUNTY');
    imagettftext($img, 26, 0, 105, 92, $white, $fontBd, 'SPORTS MEET');
    imagettftext($img, 13, 0, 105, 114, $dark, $font, 'Republic of Liberia');
    imagettftext($img, 12, 0, 105, 132, $dark, $font, 'Player Identification Card');

    // Player county flag (top right)
    $flagUrl = getCountyFlagUrl($player['county_name']);
    $flagPath = $flagUrl ? $baseDir . '/assets/images/' . basename(parse_url($flagUrl, PHP_URL_PATH)) : '';
    if ($flagPath && file_exists($flagPath)) {
        $ext = strtolower(pathinfo($flagPath, PATHINFO_EXTENSION));
        $flag = ($ext === 'png') ? @imagecreatefrompng($flagPath) : @imagecreatefromjpeg($flagPath);
        if ($flag) {
            $fx = $cardW - 115;
            $fy = 30;
            imageroundrect($img, $fx - 3, $fy - 3, $fx + 96, $fy + 63, 8, $gold);
            imagecopyresampled($img, $flag, $fx, $fy, 0, 0, 90, 56, imagesx($flag), imagesy($flag));
            imagedestroy($flag);
        }
    }

    // Card ID badge (top right corner)
    $cidBox = imagettfbbox(13, 0, $fontBd, $cardId);
    $cidW = $cidBox[2] - $cidBox[0];
    $cidX = $cardW - $cidW - 30;
    imagefilledrectangle($img, $cidX - 8, 145, $cidX + $cidW + 8, 168, $gold);
    imagettftext($img, 13, 0, $cidX, 163, $dark, $fontBd, $cardId);

    // === PHOTO SECTION ===
    $photoW = 180;
    $photoH = 220;
    $photoX = cardCoord(($cardW - $photoW) / 2);
    $photoY = 200;

    // Photo border (gold double frame)
    imagerectangle($img, $photoX - 6, $photoY - 6, $photoX + $photoW + 6, $photoY + $photoH + 6, $gold);
    imagerectangle($img, $photoX - 4, $photoY - 4, $photoX + $photoW + 4, $photoY + $photoH + 4, $goldLight);

    $photoPath2 = $player['photo_path'] && file_exists($baseDir . '/uploads/photos/' . $player['photo_path'])
        ? $baseDir . '/uploads/photos/' . $player['photo_path']
        : '';
    $srcImg = false;
    if ($photoPath2) {
        $pExt = strtolower(pathinfo($photoPath2, PATHINFO_EXTENSION));
        if ($pExt === 'jpg' || $pExt === 'jpeg') $srcImg = @imagecreatefromjpeg($photoPath2);
        elseif ($pExt === 'png') $srcImg = @imagecreatefrompng($photoPath2);
    }

    if ($srcImg) {
        imagecopyresampled($img, $srcImg, $photoX, $photoY, 0, 0, $photoW, $photoH, imagesx($srcImg), imagesy($srcImg));
        imagedestroy($srcImg);
    } else {
        imagefilledrectangle($img, $photoX, $photoY, $photoX + $photoW, $photoY + $photoH, $bgLight);
        imagettftext($img, 15, 0, $photoX + 50, $photoY + 120, $dark, $font, 'No Photo');
    }

    // === PLAYER NAME ===
    $name = html_entity_decode($player['full_name'], ENT_QUOTES, 'UTF-8');
    $nameBox = imagettfbbox(26, 0, $fontBd, $name);
    $nameW = $nameBox[2] - $nameBox[0];
    $nameY = $photoY + $photoH + 28;
    $nameX = cardCoord(($cardW - $nameW) / 2);
    imagettftext($img, 26, 0, $nameX, $nameY, $dark, $fontBd, $name);

    // Gold underline beneath name
    $ulW = min($nameW + 20, 400);
    $ulLeft = cardCoord(($cardW - $ulW) / 2);
    $ulRight = cardCoord(($cardW + $ulW) / 2);
    imagefilledrectangle($img, $ulLeft, $nameY + 6, $ulRight, $nameY + 8, $gold);

    // County name
    $countyText = $player['county_name'] . ' County';
    $ctBox = imagettfbbox(18, 0, $fontBd, $countyText);
    $ctW = $ctBox[2] - $ctBox[0];
    $ctX = cardCoord(($cardW - $ctW) / 2);
    imagettftext($img, 18, 0, $ctX, $nameY + 30, $dark, $fontBd, $countyText);

    // === GROUP BADGE ===
    $groupColors = ['A' => [220, 53, 69], 'B' => [13, 110, 253], 'C' => [200, 160, 50], 'D' => [27, 94, 32]];
    $gc = $groupColors[$player['group_label']] ?? [100, 100, 100];
    $grpColor = imagecolorallocate($img, $gc[0], $gc[1], $gc[2]);
    $groupText = 'Group ' . $player['group_label'];
    $gtBox = imagettfbbox(15, 0, $fontBd, $groupText);
    $gtW = $gtBox[2] - $gtBox[0];
    $gtX = cardCoord(($cardW - $gtW) / 2);
    $badgeY = $nameY + 62;
    imagefilledrectangle($img, $gtX - 16, $badgeY - 2, $gtX + $gtW + 16, $badgeY + 22, $grpColor);
    imagettftext($img, 15, 0, $gtX, $badgeY + 15, $white, $fontBd, $groupText);

    // === INFO GRID with rounded card style ===
    $infoY = $badgeY + 40;
    $infoItems = [
        ['Sport', $player['sport_name'] . ' (' . $player['association_name'] . ')'],
        ['Position', $player['primary_position']],
        ['DOB / Age', date('M d, Y', strtotime($player['date_of_birth'])) . ' / ' . (int)$player['age'] . ' yrs'],
        ['Gender', ucfirst($player['gender'])],
        ['NSCM Year', $player['year_of_nscm']],
        ['Club', $player['current_club'] ?: 'N/A'],
        ['Fitness', ucfirst(str_replace('_', ' ', $player['medical_fitness_status']))],
    ];

    $rowH = 36;
    $col1X = 75;
    $col2X = 240;
    $gridLeft = 50;
    $gridRight = $cardW - 50;

    // Grid background card
    imageroundrect($img, $gridLeft, $infoY - 5, $gridRight, $infoY + count($infoItems) * $rowH + 10, 10, $bgCard);
    imagerectangle($img, $gridLeft, $infoY - 5, $gridRight, $infoY + count($infoItems) * $rowH + 10, $border);

    foreach ($infoItems as $i => $item) {
        $y = $infoY + $i * $rowH;
        if ($i % 2 === 0) {
            imagefilledrectangle($img, $gridLeft + 1, $y, $gridRight - 1, $y + $rowH, $bgLight);
        }
        imagettftext($img, 13, 0, $col1X, $y + 23, $dark, $font, $item[0]);
        imagettftext($img, 14, 0, $col2X, $y + 23, $dark, $fontBd, $item[1]);
    }

    // === STATUS BADGE ===
    $statusY = $infoY + count($infoItems) * $rowH + 20;
    $status = $player['status'];
    $statusColors = ['draft' => [108, 117, 125], 'submitted' => [13, 202, 240], 'approved' => [25, 135, 84], 'rejected' => [220, 53, 69]];
    $sc = $statusColors[$status] ?? [108, 117, 125];
    $statusColor = imagecolorallocate($img, $sc[0], $sc[1], $sc[2]);
    $statusText = strtoupper($status);
    $stBox = imagettfbbox(14, 0, $fontBd, $statusText);
    $stW = $stBox[2] - $stBox[0];
    $stX = cardCoord(($cardW - $stW) / 2);
    imageroundrect($img, $stX - 18, $statusY - 4, $stX + $stW + 18, $statusY + 24, 12, $statusColor);
    imagettftext($img, 14, 0, $stX, $statusY + 15, $white, $fontBd, $statusText);

    // === MOTTO ===
    $mottoY = $statusY + 42;
    $motto = 'I am a proud supporter of the NCSM 2026';
    $mottoBox = imagettfbbox(14, 0, $fontBd, $motto);
    $mottoW = $mottoBox[2] - $mottoBox[0];
    $mottoX = cardCoord(($cardW - $mottoW) / 2);
    imagettftext($img, 14, 0, $mottoX, $mottoY, $dark, $fontBd, $motto);

    // === 15 COUNTY FLAGS in a decorative grid ===
    $allCounties = $db->fetchAll("SELECT name FROM counties ORDER BY name");
    $flagY = $mottoY + 18;
    $flagSizeW = 95;
    $flagSizeH = 48;
    $flagsPerRow = 5;
    $totalFlags = count($allCounties);
    $numRows = ceil($totalFlags / $flagsPerRow);
    $rowSpacing = 54;

    foreach ($allCounties as $fi => $fc) {
        $row = intdiv($fi, $flagsPerRow);
        $col = $fi % $flagsPerRow;
        $rowWidth = min($flagsPerRow, $totalFlags - $row * $flagsPerRow) * ($flagSizeW + 8) - 8;
        $startX = cardCoord(($cardW - $rowWidth) / 2);
        $fx = cardCoord($startX + $col * ($flagSizeW + 8));
        $fy = cardCoord($flagY + $row * $rowSpacing);
        $flUrl = getCountyFlagUrl($fc['name']);
        $flPath = $flUrl ? $baseDir . '/assets/images/' . basename(parse_url($flUrl, PHP_URL_PATH)) : '';
        if ($flPath && file_exists($flPath)) {
            $fExt = strtolower(pathinfo($flPath, PATHINFO_EXTENSION));
            $fImg = ($fExt === 'png') ? @imagecreatefrompng($flPath) : @imagecreatefromjpeg($flPath);
            if ($fImg) {
                // Gold border around each flag
                imagerectangle($img, $fx - 2, $fy - 2, $fx + $flagSizeW + 2, $fy + $flagSizeH + 2, $gold);
                imagecopyresampled($img, $fImg, $fx, $fy, 0, 0, $flagSizeW, $flagSizeH, imagesx($fImg), imagesy($fImg));
                imagedestroy($fImg);
            }
        }
    }

    // === PREMIUM BOTTOM BAR: deeper blue ===
    $bottomY = $cardH - 70;
    for ($y = $bottomY; $y < $cardH; $y++) {
        $t = ($y - $bottomY) / 70;
        $r = (int)(60 + $t * 15);
        $g = (int)(130 + $t * 10);
        $b = (int)(200 + $t * 15);
        $lineColor = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $cardW, $y, $lineColor);
    }
    // Gold stripe on top of bottom bar
    imagefilledrectangle($img, 0, $bottomY, $cardW, $bottomY + 3, $gold);

    imagettftext($img, 13, 0, 30, $bottomY + 28, $dark, $fontBd, 'Card ID: ' . $cardId);
    imagettftext($img, 12, 0, 30, $bottomY + 46, $dark, $font, 'Issued: ' . date('F d, Y'));
    imagettftext($img, 17, 0, $cardW - 190, $bottomY + 30, $dark, $fontBd, 'NCSM Liberia');
    imagettftext($img, 12, 0, $cardW - 190, $bottomY + 48, $dark, $font, 'Official Player Card');

    // Output
    if ($format === 'jpg' || $format === 'jpeg') {
        ob_start();
        imagejpeg($img, null, 95);
        $data = ob_get_clean();
    } else {
        ob_start();
        imagepng($img);
        $data = ob_get_clean();
    }
    imagedestroy($img);
    return $data;
}

function generatePlayerCardBackImage($playerId, $format = 'png') {
    if (!function_exists('imagecreatetruecolor')) {
        error_log('card_image.php: GD library not available');
        return false;
    }

    $db = getDb();
    $player = $db->fetchOne("
        SELECT p.*, c.name as county_name,
               u.full_name as registered_by_name
        FROM players p
        JOIN counties c ON p.county_id = c.id
        JOIN users u ON p.registered_by = u.id
        WHERE p.id = ?
    ", [$playerId]);

    if (!$player) return false;

    $cardW = 700;
    $cardH = 1050;

    $baseDir = __DIR__ . '/..';
    $fontPaths = [
        $baseDir . '/assets/fonts/arial.ttf',
        'C:/Windows/Fonts/arial.ttf',
        'C:\\Windows\\Fonts\\arial.ttf',
        '/usr/share/fonts/truetype/msttcorefonts/arial.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/TTF/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
    ];
    $fontBdPaths = [
        $baseDir . '/assets/fonts/arialbd.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
        'C:\\Windows\\Fonts\\arialbd.ttf',
        '/usr/share/fonts/truetype/msttcorefonts/arial_bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
    ];
    $font = null;
    $fontBd = null;
    foreach ($fontPaths as $fp) { if (file_exists($fp)) { $font = $fp; break; } }
    foreach ($fontBdPaths as $fp) { if (file_exists($fp)) { $fontBd = $fp; break; } }
    if (!$fontBd) $fontBd = $font;
    if (!$font) {
        error_log('card_image.php: No TTF font found on server');
        return false;
    }

    $img = imagecreatetruecolor($cardW, $cardH);
    imageantialias($img, true);

    $dark    = imagecolorallocate($img, 18, 18, 30);
    $white   = imagecolorallocate($img, 255, 255, 255);
    $gold    = imagecolorallocate($img, 212, 175, 55);
    $goldLight = imagecolorallocate($img, 245, 226, 150);
    $silver  = imagecolorallocate($img, 200, 200, 210);
    $textDark = imagecolorallocate($img, 30, 30, 40);
    $textGray = imagecolorallocate($img, 120, 120, 135);
    $textDim  = imagecolorallocate($img, 160, 160, 175);
    $bgLight  = imagecolorallocate($img, 245, 245, 250);
    $bgCard   = imagecolorallocate($img, 250, 250, 255);
    $border   = imagecolorallocate($img, 220, 220, 230);

    $cardId = 'NCSM-' . str_pad($player['id'], 5, '0', STR_PAD_LEFT);

    $skyBlue = imagecolorallocate($img, 135, 206, 250);
    imagefill($img, 0, 0, $skyBlue);

    // === WATERMARK: 15 faint NCSM logos across background ===
    $wmPath = $baseDir . '/assets/images/ncsm.png';
    if (file_exists($wmPath)) {
        $wmSrc = @imagecreatefrompng($wmPath);
        if ($wmSrc) {
            $wmSizeW = 100;
            $wmSizeH = 100;
            $wmCols = 5;
            $wmRows = 3;
            $wmOpacity = 15;
            $wmSpacingX = $cardW / $wmCols;
            $wmSpacingY = $cardH / $wmRows;
            $wmTmp = imagecreatetruecolor($wmSizeW, $wmSizeH);
            imagecopyresampled($wmTmp, $wmSrc, 0, 0, 0, 0, $wmSizeW, $wmSizeH, imagesx($wmSrc), imagesy($wmSrc));
            imagedestroy($wmSrc);
            for ($wr = 0; $wr < $wmRows; $wr++) {
                for ($wc = 0; $wc < $wmCols; $wc++) {
                    $wmx = (int)($wc * $wmSpacingX + ($wmSpacingX - $wmSizeW) / 2);
                    $wmy = (int)($wr * $wmSpacingY + ($wmSpacingY - $wmSizeH) / 2);
                    imagecopymerge($img, $wmTmp, $wmx, $wmy, 0, 0, $wmSizeW, $wmSizeH, $wmOpacity);
                }
            }
            imagedestroy($wmTmp);
        }
    }

    // === PREMIUM HEADER (matching front) ===
    for ($y = 0; $y < 95; $y++) {
        $r = (int)(60 + ($y / 95) * 40);
        $g = (int)(130 + ($y / 95) * 50);
        $b = (int)(200 + ($y / 95) * 35);
        $lineColor = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $cardW, $y, $lineColor);
    }
    imagefilledrectangle($img, 0, 90, $cardW, 97, $gold);
    imagefilledrectangle($img, 0, 98, $cardW, 99, $goldLight);

// Title
    imagettftext($img, 16, 0, 30, 42, $white, $fontBd, 'NATIONAL COUNTY SPORTS MEET');
    imagettftext($img, 9, 0, 32, 62, $silver, $font, 'Ministry of Youth & Sports  |  Republic of Liberia');
    imagettftext($img, 9, 0, 32, 78, $textDim, $font, 'Player Identification Card  |  Card ID: ' . $cardId);

    // === SECTIONS ===
    $sections = [
        [
            'title' => 'MEDICAL INFORMATION',
            'icon' => '+',
            'rows' => array_merge(
                [['Fitness Status', ucfirst(str_replace('_', ' ', $player['medical_fitness_status']))]],
                !empty($player['medical_notes']) ? [['Notes', $player['medical_notes']]] : []
            )
        ],
        [
            'title' => 'REGISTRATION DETAILS',
            'icon' => '#',
            'rows' => [
                ['Registered By', $player['registered_by_name']],
                ['Date', formatDate($player['created_at'], 'M d, Y')],
                ['Status', ucfirst($player['status'])],
            ]
        ],
        [
            'title' => 'PLAYER DETAILS',
            'icon' => '@',
            'rows' => [
                ['City', $player['city'] ?: 'N/A'],
                ['Last Club', $player['last_club'] ?: 'N/A'],
                ['Current Club', $player['current_club'] ?: 'N/A'],
            ]
        ],
    ];

    $startY = 115;
    $cardPad = 28;
    $sectionH = 95;

    foreach ($sections as $si => $sec) {
        $sy = $startY + $si * ($sectionH + 10);

        // Section card background
        imageroundrect($img, $cardPad, $sy, $cardW - $cardPad, $sy + $sectionH, 8, $bgCard);
        imagerectangle($img, $cardPad, $sy, $cardW - $cardPad, $sy + $sectionH, $border);

        // Gold left accent bar
        imagefilledrectangle($img, $cardPad, $sy + 4, $cardPad + 4, $sy + $sectionH - 4, $gold);

        // Section title
        imagettftext($img, 9, 0, $cardPad + 16, $sy + 18, $textDark, $fontBd, $sec['title']);

        // Divider line
        imageline($img, $cardPad + 16, $sy + 25, $cardW - $cardPad - 16, $sy + 25, $border);

        // Rows
        $ry = $sy + 38;
        foreach ($sec['rows'] as $row) {
            imagettftext($img, 9, 0, $cardPad + 20, $ry, $textDim, $font, $row[0]);
            imagettftext($img, 10, 0, $cardPad + 160, $ry, $textDark, $fontBd, html_entity_decode($row[1], ENT_QUOTES, 'UTF-8'));
            $ry += 19;
        }
    }

    // === ACCREDITATION HEADING + WORDING ===
    $accreditText = 'The bearer of this card is accredited to the 2026 NCSM within the four venues, depending on his/her Access Category. This card is valid from beginning of the NCSM to end 2026.';
    $accreditPad = 55;
    $accreditFontSize = 16;
    $accreditLineH = 24;
    $accreditLines = wordwrap($accreditText, 48, "\n", true);
    $accreditLineArr = explode("\n", $accreditLines);
    $headingSize = 30;
    $red = imagecolorallocate($img, 220, 53, 69);
    $headingLineH = 28;
    $accreditTotalH = $headingLineH + count($accreditLineArr) * $accreditLineH + 10;
    $accreditStartY = $cardH - 310 - $accreditTotalH - 16;

    // ACCREDITATION heading
    $headingText = 'ACCREDITATION';
    $htBox = imagettfbbox($headingSize, 0, $fontBd, $headingText);
    $htW = $htBox[2] - $htBox[0];
    $headingX = cardCoord(($cardW - $htW) / 2);
    imagettftext($img, $headingSize, 0, $headingX, $accreditStartY + 18, $red, $fontBd, $headingText);
    // Gold underline beneath heading
    $htUlW = min($htW + 20, 200);
    $htUlLeft = cardCoord(($cardW - $htUlW) / 2);
    $htUlRight = cardCoord(($cardW + $htUlW) / 2);
    imagefilledrectangle($img, $htUlLeft, $accreditStartY + 22, $htUlRight, $accreditStartY + 24, $gold);

    foreach ($accreditLineArr as $li => $line) {
        $lineTrimmed = trim($line);
        $lineBox = imagettfbbox($accreditFontSize, 0, $font, $lineTrimmed);
        $lineW = $lineBox[2] - $lineBox[0];
        $lineX = cardCoord(($cardW - $lineW) / 2);
        imagettftext($img, $accreditFontSize, 0, $lineX, $accreditStartY + $headingLineH + $li * $accreditLineH + 12, $dark, $font, $lineTrimmed);
    }

    // === QR CODE from static qr.png ===
    $qrFile = $baseDir . '/uploads/photos/qr.png';
    $qrImg = file_exists($qrFile) ? @imagecreatefrompng($qrFile) : false;
    $qrSize = 160;
    $qrX = cardCoord(($cardW - $qrSize) / 2);
    $qrY = $cardH - 310;

    // QR background card
    imageroundrect($img, $qrX - 20, $qrY - 20, $qrX + $qrSize + 20, $qrY + $qrSize + 55, 10, $bgCard);
    imagerectangle($img, $qrX - 20, $qrY - 20, $qrX + $qrSize + 20, $qrY + $qrSize + 55, $border);
    // Gold top accent
    imagefilledrectangle($img, $qrX - 20, $qrY - 20, $qrX + $qrSize + 20, $qrY - 16, $gold);

    if ($qrImg) {
        imagecopyresampled($img, $qrImg, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qrImg), imagesy($qrImg));
        imagedestroy($qrImg);
        // Gold border around QR
        imagerectangle($img, $qrX - 3, $qrY - 3, $qrX + $qrSize + 3, $qrY + $qrSize + 3, $gold);
    } else {
        imagefilledrectangle($img, $qrX, $qrY, $qrX + $qrSize, $qrY + $qrSize, $bgLight);
        imagettftext($img, 11, 0, $qrX + 45, $qrY + 90, $textDim, $font, 'QR Code');
    }

    // QR labels
    $labelY = $qrY + $qrSize + 16;
    $labelX = cardCoord(($cardW - 120) / 2);
    imagettftext($img, 9, 0, $labelX, $labelY, $textDim, $font, 'Scan to view player profile');
    imagettftext($img, 10, 0, $labelX, $labelY + 16, $gold, $fontBd, 'moys.gov.lr');

    // === PREMIUM BOTTOM BAR: deeper blue ===
    $bottomY = $cardH - 65;
    for ($y = $bottomY; $y < $cardH; $y++) {
        $t = ($y - $bottomY) / 65;
        $r = (int)(60 + $t * 15);
        $g = (int)(130 + $t * 10);
        $b = (int)(200 + $t * 15);
        $lineColor = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $cardW, $y, $lineColor);
    }
    imagefilledrectangle($img, 0, $bottomY, $cardW, $bottomY + 3, $gold);

    imagettftext($img, 8, 0, 30, $bottomY + 22, $silver, $font, 'Ministry of Youth & Sports  |  National County Sports Meet  |  Liberia');
    imagettftext($img, 8, 0, 30, $bottomY + 38, $textDim, $font, 'This card is official property of NCSM. Unauthorized reproduction is prohibited.');
    imagettftext($img, 9, 0, $cardW - 150, $bottomY + 28, $gold, $fontBd, $cardId);

    // Output
    if ($format === 'jpg' || $format === 'jpeg') {
        ob_start();
        imagejpeg($img, null, 95);
        $data = ob_get_clean();
    } else {
        ob_start();
        imagepng($img);
        $data = ob_get_clean();
    }
    imagedestroy($img);
    return $data;
}
