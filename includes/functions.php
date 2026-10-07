<?php
require_once __DIR__ . '/db.php';

function getRoleLabel($role) {
    $map = [
        'super_admin' => 'Super Admin',
        'county_coordinator' => 'County Coordinator',
        'group_admin' => 'Group Admin',
        'lfa_administrator' => 'Liberia Football Association Admin',
        'lka_administrator' => 'Liberia Kickball Association Admin',
        'lba_administrator' => 'Liberia Basketball Association Admin',
        'laa_administrator' => 'Liberia Athletics Association Admin',
        'match_commissioner' => 'Match Commissioner',
        'lofa_admin' => 'Lofa Admin',
        'bong_admin' => 'Bong Admin',
        'kru_admin' => 'Grand Kru Admin',
        'gedeh_admin' => 'Grand Gedeh Admin',
        'sports_coord' => 'Sports Coordinator',
    ];
    return $map[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

function isAdminRole() {
    return hasRole('group_admin');
}

function isCoordViewer() {
    return hasRole('county_coordinator');
}

function isCountyAdmin() {
    return hasRole('group_admin');
}

function canManageGames() {
    return hasRole(['super_admin', 'group_admin']);
}

function getAssociationApprovalRoleMap() {
    return [
        'lfa_administrator' => 'LFA',
        'lka_administrator' => 'LKA',
        'lba_administrator' => 'LBA',
        'laa_administrator' => 'LAA',
    ];
}

function getAssociationApprovalRoles() {
    return array_keys(getAssociationApprovalRoleMap());
}

function isAssociationApprovalRole() {
    return hasRole(getAssociationApprovalRoles());
}

function getAssociationCodeForRole($role) {
    $map = getAssociationApprovalRoleMap();
    return $map[$role] ?? null;
}

function getApprovalRoleForAssociationCode($associationCode) {
    return array_search($associationCode, getAssociationApprovalRoleMap(), true) ?: null;
}

function getResolvedAssociationId($role, $associationId = null) {
    $associationCode = getAssociationCodeForRole($role);
    if ($associationCode === null) {
        return $associationId ? (int)$associationId : null;
    }

    $sport = getDb()->fetchOne(
        "SELECT id FROM sports_disciplines WHERE association_code = ? LIMIT 1",
        [$associationCode]
    );

    return $sport ? (int)$sport['id'] : null;
}

function getDb() {
    return Database::getInstance();
}

function ensureUserRoleAssignments() {
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    try {
        $db = getDb();
        $db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS group_label VARCHAR(1)");
        $db->query("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        $db->query("ALTER TABLE approval_workflow DROP CONSTRAINT IF EXISTS approval_workflow_role_at_time_check");
        $db->query("UPDATE users u SET role = 'lfa_administrator', updated_at = NOW() FROM sports_disciplines s WHERE u.association_id = s.id AND u.role IN ('association_admin', 'lfa_administrator') AND s.association_code = 'LFA'");
        $db->query("UPDATE users u SET role = 'lka_administrator', updated_at = NOW() FROM sports_disciplines s WHERE u.association_id = s.id AND u.role IN ('association_admin', 'lfa_administrator') AND s.association_code = 'LKA'");
        $db->query("UPDATE users u SET role = 'lba_administrator', updated_at = NOW() FROM sports_disciplines s WHERE u.association_id = s.id AND u.role IN ('association_admin', 'lfa_administrator') AND s.association_code = 'LBA'");
        $db->query("UPDATE users u SET role = 'laa_administrator', updated_at = NOW() FROM sports_disciplines s WHERE u.association_id = s.id AND u.role IN ('association_admin', 'lfa_administrator') AND s.association_code = 'LAA'");
        $db->query("UPDATE users u SET association_id = s.id, updated_at = NOW() FROM sports_disciplines s WHERE u.role = 'lfa_administrator' AND s.association_code = 'LFA' AND COALESCE(u.association_id, 0) <> s.id");
        $db->query("UPDATE users u SET association_id = s.id, updated_at = NOW() FROM sports_disciplines s WHERE u.role = 'lka_administrator' AND s.association_code = 'LKA' AND COALESCE(u.association_id, 0) <> s.id");
        $db->query("UPDATE users u SET association_id = s.id, updated_at = NOW() FROM sports_disciplines s WHERE u.role = 'lba_administrator' AND s.association_code = 'LBA' AND COALESCE(u.association_id, 0) <> s.id");
        $db->query("UPDATE users u SET association_id = s.id, updated_at = NOW() FROM sports_disciplines s WHERE u.role = 'laa_administrator' AND s.association_code = 'LAA' AND COALESCE(u.association_id, 0) <> s.id");
        $db->query("UPDATE approval_workflow aw SET role_at_time = u.role FROM users u WHERE aw.action_by = u.id AND aw.role_at_time IN ('association_admin', 'lfa_administrator') AND u.role IN ('lfa_administrator', 'lka_administrator', 'lba_administrator', 'laa_administrator')");
        $db->query("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin', 'county_coordinator', 'group_admin', 'lfa_administrator', 'lka_administrator', 'lba_administrator', 'laa_administrator', 'match_commissioner'))");
        $db->query("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_group_label_check");
        $db->query("ALTER TABLE users ADD CONSTRAINT users_group_label_check CHECK (group_label IS NULL OR group_label IN ('A', 'B', 'C', 'D'))");
        $db->query("ALTER TABLE approval_workflow ADD CONSTRAINT approval_workflow_role_at_time_check CHECK (role_at_time IN ('county_coordinator', 'group_admin', 'lfa_administrator', 'lka_administrator', 'lba_administrator', 'laa_administrator', 'super_admin', 'match_commissioner'))");
    } catch (Throwable $e) {
        error_log('Role assignment schema sync failed: ' . $e->getMessage());
    }
}

ensureUserRoleAssignments();

function getAssignableGroups() {
    return ['A', 'B', 'C', 'D'];
}

function canRegisterPlayers() {
    return hasRole(['super_admin', 'group_admin']);
}

function getAssignedCountyId() {
    $countyId = (int)($_SESSION['user_county_id'] ?? 0);
    return $countyId > 0 ? $countyId : null;
}

function getAssignedGroupLabel() {
    $groupLabel = strtoupper(trim((string)($_SESSION['user_group_label'] ?? '')));
    return in_array($groupLabel, getAssignableGroups(), true) ? $groupLabel : null;
}

function userCanAccessCounty($countyId, $groupLabel = null) {
    if (hasRole('super_admin')) {
        return true;
    }

    if (hasRole('county_coordinator')) {
        return (int)$countyId === (int)getAssignedCountyId();
    }

    if (hasRole('group_admin')) {
        $assignedGroup = getAssignedGroupLabel();
        if (!$assignedGroup) {
            return false;
        }

        if ($groupLabel === null || $groupLabel === '') {
            $county = getDb()->fetchOne("SELECT group_label FROM counties WHERE id = ?", [(int)$countyId]);
            $groupLabel = $county['group_label'] ?? null;
        }

        return $groupLabel === $assignedGroup;
    }

    return true;
}

function getScopedCounties() {
    $counties = getCounties();

    if (hasRole('county_coordinator')) {
        $assignedCountyId = getAssignedCountyId();
        return array_values(array_filter($counties, static function ($county) use ($assignedCountyId) {
            return (int)$county['id'] === (int)$assignedCountyId;
        }));
    }

    if (hasRole('group_admin')) {
        $assignedGroup = getAssignedGroupLabel();
        return array_values(array_filter($counties, static function ($county) use ($assignedGroup) {
            return $county['group_label'] === $assignedGroup;
        }));
    }

    return $counties;
}

function ensureContactMessagesTable() {
    $db = getDb();
    $db->query("CREATE TABLE IF NOT EXISTS contact_messages (
        id INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN NOT NULL DEFAULT FALSE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
}

function ensureMatchExtraTimeColumns() {
    $db = getDb();
    $db->query("ALTER TABLE matches ADD COLUMN IF NOT EXISTS extra_time_enabled BOOLEAN NOT NULL DEFAULT FALSE");
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
    if ($storedPath !== '' && preg_match('#^https?://#i', $storedPath)) {
        return $storedPath;
    }
    if ($storedPath === '' || strpos($storedPath, 'uploads/') === 0) {
        return $storedPath === '' ? '' : APP_URL . $storedPath;
    }
    return APP_URL . $basePath . $storedPath;
}

function parseIniSizeToBytes($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $bytes = (float)$value;
    switch ($unit) {
        case 'g':
            $bytes *= 1024;
        case 'm':
            $bytes *= 1024;
        case 'k':
            $bytes *= 1024;
    }

    return (int)$bytes;
}

function requestExceededPostMaxSize() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }

    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxSize = parseIniSizeToBytes(ini_get('post_max_size'));

    return $postMaxSize > 0 && $contentLength > $postMaxSize && empty($_POST) && empty($_FILES);
}

function getPhotoUploadLimitBytes() {
    $serverLimit = parseIniSizeToBytes(ini_get('upload_max_filesize'));
    if ($serverLimit <= 0) {
        $serverLimit = parseIniSizeToBytes(ini_get('post_max_size'));
    }

    if ($serverLimit > 0) {
        return min(MAX_PHOTO_SIZE, $serverLimit);
    }

    return MAX_PHOTO_SIZE;
}

function formatBytesLabel($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1024 * 1024) {
        $value = $bytes / (1024 * 1024);
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . 'MB';
    }
    if ($bytes >= 1024) {
        $value = $bytes / 1024;
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . 'KB';
    }
    return $bytes . 'B';
}

function getUploadErrorMessage($errorCode) {
    switch ((int)$errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The selected photo is too large. Please choose an image up to ' . formatBytesLabel(getPhotoUploadLimitBytes()) . '.';
        case UPLOAD_ERR_PARTIAL:
            return 'The photo upload was interrupted. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No photo was selected.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'The server could not save the uploaded photo. Please try again.';
        default:
            return 'Upload failed.';
    }
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

function isRemoteUrl($value) {
    return is_string($value) && preg_match('#^https?://#i', trim($value)) === 1;
}

function isSupabaseConfigured() {
    return SUPABASE_URL !== '' || SUPABASE_BUCKET !== '' || SUPABASE_SERVICE_ROLE_KEY !== '' || SUPABASE_ANON_KEY !== '';
}

function hasCompleteSupabaseConfig() {
    return SUPABASE_URL !== '' && SUPABASE_BUCKET !== '' && SUPABASE_SERVICE_ROLE_KEY !== '';
}

function getSupabaseConfigError() {
    if (!isSupabaseConfigured()) {
        return '';
    }

    $missing = [];
    if (SUPABASE_URL === '') {
        $missing[] = 'NCSM_SUPABASE_URL';
    }
    if (SUPABASE_BUCKET === '') {
        $missing[] = 'NCSM_SUPABASE_BUCKET';
    }
    if (SUPABASE_SERVICE_ROLE_KEY === '') {
        $missing[] = 'NCSM_SUPABASE_SERVICE_ROLE_KEY';
    }

    if (empty($missing)) {
        return '';
    }

    return 'Supabase Storage is not fully configured. Missing: ' . implode(', ', $missing) . '.';
}

function buildSupabaseStorageObjectPath($folderSegment, $originalName, $prefix) {
    $folderSegment = trim((string)$folderSegment, '/');
    $extension = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));
    $baseName = pathinfo((string)$originalName, PATHINFO_FILENAME);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($baseName));
    $slug = trim((string)$slug, '-');
    if ($slug === '') {
        $slug = 'image';
    }

    $filename = uniqid($prefix, true) . '-' . $slug;
    if ($extension !== '') {
        $filename .= '.' . $extension;
    }

    return ($folderSegment !== '' ? $folderSegment . '/' : '') . $filename;
}

function getSupabaseStoragePublicUrl($objectPath) {
    $objectPath = ltrim((string)$objectPath, '/');
    return SUPABASE_URL . '/storage/v1/object/public/' . rawurlencode(SUPABASE_BUCKET) . '/' . str_replace('%2F', '/', rawurlencode($objectPath));
}

function supabaseStorageRequest($method, $objectPath, $body = null, array $headers = []) {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'PHP cURL extension is required for Supabase Storage uploads.'];
    }

    $objectPath = ltrim((string)$objectPath, '/');
    $url = SUPABASE_URL . '/storage/v1/object/' . rawurlencode(SUPABASE_BUCKET) . '/' . str_replace('%2F', '/', rawurlencode($objectPath));
    $serviceKey = SUPABASE_SERVICE_ROLE_KEY;
    $isSecretApiKey = strpos($serviceKey, 'sb_secret_') === 0;
    $requestHeaders = array_merge([
        'apikey: ' . ($isSecretApiKey ? $serviceKey : (SUPABASE_ANON_KEY !== '' ? SUPABASE_ANON_KEY : $serviceKey)),
        'x-upsert: true',
    ], $headers);
    if (!$isSecretApiKey) {
        array_unshift($requestHeaders, 'Authorization: Bearer ' . $serviceKey);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'Supabase Storage request failed: ' . $curlError];
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $decoded = json_decode($response, true);
        $message = '';
        if (is_array($decoded)) {
            $message = $decoded['message'] ?? $decoded['error'] ?? '';
        }
        if ($message === '') {
            $message = 'Supabase Storage request failed with status ' . $statusCode . '.';
        }
        return ['success' => false, 'error' => $message];
    }

    return ['success' => true, 'body' => $response];
}

function uploadLocalImageToSupabase($sourcePath, $originalName, $folderSegment, $prefix) {
    $configError = getSupabaseConfigError();
    if ($configError !== '') {
        return ['success' => false, 'error' => $configError];
    }

    if (!hasCompleteSupabaseConfig()) {
        return ['success' => false, 'error' => 'Supabase Storage is not configured.'];
    }

    if (!is_file($sourcePath)) {
        return ['success' => false, 'error' => 'The image file to upload was not found.'];
    }

    $contents = file_get_contents($sourcePath);
    if ($contents === false) {
        return ['success' => false, 'error' => 'Failed to read the image file for upload.'];
    }

    $mime = mime_content_type($sourcePath) ?: 'application/octet-stream';
    $objectPath = buildSupabaseStorageObjectPath($folderSegment, $originalName, $prefix);
    $result = supabaseStorageRequest('POST', $objectPath, $contents, [
        'Content-Type: ' . $mime,
        'Cache-Control: max-age=31536000',
    ]);

    if (!$result['success']) {
        return $result;
    }

    return [
        'success' => true,
        'path' => getSupabaseStoragePublicUrl($objectPath),
        'object_path' => $objectPath,
    ];
}

function saveUploadedImageLocally($file, $destinationDir, $relativeDir, $prefix) {
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $filename = uniqid($prefix, true) . ($ext !== '' ? '.' . $ext : '');
    $dest = rtrim($destinationDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true)) {
        return ['success' => false, 'error' => 'Failed to create the upload directory.'];
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Failed to save file.'];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'path' => trim($relativeDir, '/') . '/' . $filename,
    ];
}

function uploadManagedImage($file, $maxSize, $allowedTypes, $destinationDir, $relativeDir, $folderSegment, $prefix, $sizeLabel, $invalidTypeMessage) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE)];
    }

    if (($file['size'] ?? 0) > $maxSize) {
        return ['success' => false, 'error' => $sizeLabel . ' too large. Max ' . formatBytesLabel($maxSize) . '.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes, true)) {
        return ['success' => false, 'error' => $invalidTypeMessage];
    }

    if (hasCompleteSupabaseConfig()) {
        return uploadLocalImageToSupabase($file['tmp_name'], $file['name'], $folderSegment, $prefix);
    }

    $configError = getSupabaseConfigError();
    if ($configError !== '') {
        return ['success' => false, 'error' => $configError];
    }

    return saveUploadedImageLocally($file, $destinationDir, $relativeDir, $prefix);
}

function uploadPhoto($file) {
    return uploadManagedImage($file, MAX_PHOTO_SIZE, ALLOWED_PHOTO_TYPES, PHOTO_PATH, 'uploads/photos', 'players', 'photo_', 'Photo', 'Invalid file type. JPG, PNG, GIF only.');
}

function uploadGalleryPhoto($file) {
    return uploadManagedImage($file, MAX_GALLERY_SIZE, ALLOWED_GALLERY_TYPES, GALLERY_PATH, 'uploads/gallery', 'gallery', 'gallery_', 'Photo', 'Only JPG, PNG, GIF, and WebP images are allowed.');
}

function getSupabaseObjectPathFromStoredImage($storedPath) {
    $storedPath = trim((string)$storedPath);
    if ($storedPath === '' || !isRemoteUrl($storedPath) || SUPABASE_URL === '' || SUPABASE_BUCKET === '') {
        return '';
    }

    $publicPrefix = SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/';
    if (strpos($storedPath, $publicPrefix) !== 0) {
        return '';
    }

    $objectPath = substr($storedPath, strlen($publicPrefix));
    return ltrim(rawurldecode($objectPath), '/');
}

function deleteSupabaseImage($storedPath) {
    $objectPath = getSupabaseObjectPathFromStoredImage($storedPath);
    if ($objectPath === '') {
        return false;
    }

    $configError = getSupabaseConfigError();
    if ($configError !== '') {
        error_log($configError);
        return false;
    }

    $result = supabaseStorageRequest('DELETE', $objectPath, null, ['x-upsert: false']);
    if (!$result['success']) {
        error_log('Supabase Storage delete failed for ' . $objectPath . ': ' . $result['error']);
        return false;
    }

    return true;
}

function deleteManagedImage($storedPath) {
    $storedPath = trim((string)$storedPath);
    if ($storedPath === '') {
        return true;
    }

    if (isRemoteUrl($storedPath)) {
        return deleteSupabaseImage($storedPath);
    }

    $candidates = [];
    if (strpos($storedPath, 'uploads/') === 0) {
        $candidates[] = $storedPath;
    } else {
        $basename = basename($storedPath);
        $candidates[] = 'uploads/photos/' . $basename;
        $candidates[] = 'uploads/gallery/' . $basename;
        $candidates[] = $storedPath;
    }

    foreach (array_values(array_unique($candidates)) as $candidate) {
        $fullPath = resolveUploadCandidateFilePath($candidate);
        if ($fullPath !== '' && file_exists($fullPath)) {
            return unlink($fullPath);
        }
    }

    return false;
}

function downloadRemoteFileToTemp($url, $prefix = 'ncsm-file-') {
    if (!isRemoteUrl($url)) {
        return '';
    }

    $pathPart = (string)parse_url($url, PHP_URL_PATH);
    $extension = strtolower(pathinfo($pathPart, PATHINFO_EXTENSION));
    if ($extension === '') {
        $extension = 'tmp';
    }

    $tempPath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . $prefix . md5($url) . '.' . $extension;
    if (file_exists($tempPath) && filesize($tempPath) > 0) {
        return $tempPath;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $contents = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($contents !== false && $statusCode >= 200 && $statusCode < 300) {
            file_put_contents($tempPath, $contents);
            return $tempPath;
        }
    }

    $context = stream_context_create(['http' => ['timeout' => 30]]);
    $contents = @file_get_contents($url, false, $context);
    if ($contents === false) {
        return '';
    }

    file_put_contents($tempPath, $contents);
    return $tempPath;
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
    try {
        $db = getDb();
        $db->insert(
            "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)",
            [$_SESSION['user_id'], $action, $description, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']
        );
    } catch (Throwable $e) {
        error_log('Activity log write failed: ' . $e->getMessage());
    }
}

function createNotification($userId, $title, $message, $type = 'info', $link = '') {
    try {
        return getDb()->insert(
            "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)",
            [$userId, $title, $message, $type, $link]
        );
    } catch (Throwable $e) {
        error_log('Notification write failed: ' . $e->getMessage());
        return false;
    }
}

function getUnreadNotificationCount($userId) {
    try {
        $row = getDb()->fetchOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE",
            [$userId]
        );
        return (int)($row['count'] ?? 0);
    } catch (Throwable $e) {
        error_log('Notification count query failed: ' . $e->getMessage());
        return 0;
    }
}

function getRecentNotifications($userId, $limit = 5) {
    try {
        $limit = max(1, (int)$limit);
        return getDb()->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}",
            [$userId]
        );
    } catch (Throwable $e) {
        error_log('Recent notifications query failed: ' . $e->getMessage());
        return [];
    }
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
    $sql = "SELECT COUNT(*) as count FROM players p";
    $conditions = [];
    $params = [];
    if ($status) {
        $conditions[] = "p.status = ?";
        $params[] = $status;
    }
    if ($sportId) {
        $conditions[] = "p.sport_discipline_id = ?";
        $params[] = $sportId;
    }
    if (hasRole('county_coordinator')) {
        $conditions[] = "p.county_id = ?";
        $params[] = getAssignedCountyId();
    } elseif (hasRole('group_admin')) {
        $sql .= " JOIN counties c ON p.county_id = c.id";
        $conditions[] = "c.group_label = ?";
        $params[] = getAssignedGroupLabel();
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

function getStandingsData($db, $sportId = null, $groupLabel = null) {
    $where = ["m.status = 'completed'"];
    $params = [];
    if ($sportId) {
        $where[] = "m.sport_discipline_id = ?";
        $params[] = $sportId;
    }
    if ($groupLabel) {
        $where[] = "m.group_label = ?";
        $params[] = $groupLabel;
    }
    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    $rows = $db->fetchAll(
        "SELECT m.home_county_id, m.away_county_id, m.home_score, m.away_score, m.group_label, m.sport_discipline_id,
                c1.name as home_name, c2.name as away_name
         FROM matches m
         JOIN counties c1 ON m.home_county_id = c1.id
         JOIN counties c2 ON m.away_county_id = c2.id
         $whereSql
         ORDER BY m.group_label, m.match_date",
        $params
    );

    $teams = [];
    foreach ($rows as $r) {
        foreach ([
            ['id' => $r['home_county_id'], 'name' => $r['home_name'], 'group' => $r['group_label'], 'gf' => (int)$r['home_score'], 'ga' => (int)$r['away_score']],
            ['id' => $r['away_county_id'], 'name' => $r['away_name'], 'group' => $r['group_label'], 'gf' => (int)$r['away_score'], 'ga' => (int)$r['home_score']]
        ] as $t) {
            $tid = $t['id'];
            if (!isset($teams[$tid])) {
                $teams[$tid] = ['name' => $t['name'], 'group' => $t['group'], 'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'gf' => 0, 'ga' => 0, 'gd' => 0, 'pts' => 0];
            }
            $teams[$tid]['played']++;
            $teams[$tid]['gf'] += $t['gf'];
            $teams[$tid]['ga'] += $t['ga'];
            $teams[$tid]['gd'] = $teams[$tid]['gf'] - $teams[$tid]['ga'];
            if ($t['gf'] > $t['ga']) {
                $teams[$tid]['wins']++;
                $teams[$tid]['pts'] += 3;
            } elseif ($t['gf'] === $t['ga']) {
                $teams[$tid]['draws']++;
                $teams[$tid]['pts'] += 1;
            } else {
                $teams[$tid]['losses']++;
            }
        }
    }

    uksort($teams, function($a, $b) use ($teams) {
        if ($teams[$a]['pts'] !== $teams[$b]['pts']) {
            return $teams[$b]['pts'] - $teams[$a]['pts'];
        }
        if ($teams[$a]['gd'] !== $teams[$b]['gd']) {
            return $teams[$b]['gd'] - $teams[$a]['gd'];
        }
        return $teams[$b]['gf'] - $teams[$a]['gf'];
    });

    return $teams;
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

function getStatusBadge($status, $reason = null) {
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
    $badge = "<span class='badge bg-{$class}'>{$status}</span>";

    if ($status === 'rejected' && trim((string)$reason) !== '') {
        $escapedReason = htmlspecialchars((string)$reason, ENT_QUOTES, 'UTF-8');
        $badge .= " <button type='button' class='btn btn-link btn-sm p-0 ms-1 text-danger align-baseline' data-bs-toggle='popover' data-bs-trigger='focus' data-bs-placement='top' data-bs-title='Rejection Reason' data-bs-content='{$escapedReason}' aria-label='View rejection reason' title='View rejection reason'><i class='bi bi-info-circle-fill'></i></button>";
    }

    return $badge;
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

function normalizePlayerPhotoPath($photoPath) {
    $photoPath = trim((string)$photoPath);
    if ($photoPath === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $photoPath)) {
        return $photoPath;
    }

    $photoPath = str_replace('\\', '/', $photoPath);
    $photoPath = preg_replace('#^\./+#', '', $photoPath);
    $photoPath = ltrim($photoPath, '/');

    if (strpos($photoPath, 'uploads/photos/') === 0) {
        return $photoPath;
    }

    if (strpos($photoPath, 'uploads/') === 0) {
        return $photoPath;
    }

    if (strpos($photoPath, 'photos/') === 0) {
        return 'uploads/' . $photoPath;
    }

    return 'uploads/photos/' . basename($photoPath);
}

function getPlayerPhotoPathCandidates($photoPath) {
    $normalizedPath = normalizePlayerPhotoPath($photoPath);
    if ($normalizedPath === '' || isRemoteUrl($normalizedPath)) {
        return [];
    }

    $candidates = [$normalizedPath];
    $basename = basename($normalizedPath);

    if (strpos($normalizedPath, 'uploads/photos/') !== 0) {
        $candidates[] = 'uploads/photos/' . $basename;
    }
    if (strpos($normalizedPath, 'uploads/') !== 0) {
        $candidates[] = 'uploads/' . $basename;
    }

    return array_values(array_unique($candidates));
}

function resolveUploadCandidateFilePath($candidate) {
    $candidate = str_replace('\\', '/', (string)$candidate);
    $uploadRoot = rtrim(str_replace('\\', '/', UPLOAD_PATH), '/');

    if (strpos($candidate, 'uploads/') === 0) {
        $relative = substr($candidate, strlen('uploads/'));
        $uploadPath = $uploadRoot . '/' . ltrim($relative, '/');
        if (file_exists($uploadPath)) {
            return $uploadPath;
        }
    }

    $projectPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
    if (file_exists($projectPath)) {
        return $projectPath;
    }

    return '';
}

function getPlayerPhotoFilePath($photoPath) {
    $normalizedPath = normalizePlayerPhotoPath($photoPath);
    if ($normalizedPath !== '' && isRemoteUrl($normalizedPath)) {
        return downloadRemoteFileToTemp($normalizedPath, 'ncsm-photo-');
    }

    foreach (getPlayerPhotoPathCandidates($photoPath) as $candidate) {
        $fullPath = resolveUploadCandidateFilePath($candidate);
        if ($fullPath !== '') {
            return $fullPath;
        }
    }

    return '';
}

function getPlayerPhotoUrl($photoPath) {
    $normalizedPath = normalizePlayerPhotoPath($photoPath);
    if ($normalizedPath !== '' && isRemoteUrl($normalizedPath)) {
        return $normalizedPath;
    }
    if ($normalizedPath !== '' && getPlayerPhotoFilePath($normalizedPath)) {
        return APP_URL . 'pages/players/photo.php?path=' . rawurlencode($normalizedPath);
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
    $photoPath = getPlayerPhotoFilePath($player['photo_path']) ?: $base . '/assets/images/default-avatar.svg';
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

    $photoPath2 = getPlayerPhotoFilePath($player['photo_path']);
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
