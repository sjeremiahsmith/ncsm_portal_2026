<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . 'pages/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $db = getDb();
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$username, $username]
        );

        if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_county_id'] = $user['county_id'];
            $_SESSION['user_association_id'] = $user['association_id'];

            if ($user['county_id']) {
                $county = $db->fetchOne("SELECT name, group_label FROM counties WHERE id = ?", [$user['county_id']]);
                $_SESSION['user_group_label'] = $county ? $county['group_label'] : null;
                $_SESSION['user_county_name'] = $county ? $county['name'] : null;
            } else {
                $_SESSION['user_group_label'] = null;
                $_SESSION['user_county_name'] = null;
            }

            logActivity('login', 'User logged in');

            $redirect = $_SESSION['redirect_after_login'] ?? APP_URL . 'pages/dashboard.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Invalid credentials or account inactive.';
        }
    }
}

$pageTitle = 'Login';
include __DIR__ . '/../templates/public_header.php';
?>

<section class="hero-section" style="min-height:100vh;">
    <div class="hero-bg"></div>
    <div class="hero-particles" id="particles"></div>
    <div class="hero-content" style="max-width:500px;">
        <div class="login-public-card">
            <div class="card-header">
                <img src="<?= APP_URL ?>assets/images/ncsm.png" alt="Logo" style="height:100px;width:100px;object-fit:contain;border-radius:50%;" class="mb-2">
                <h4 class="mb-1 fw-bold">National County Sports Meet</h4>
                <p class="mb-0 small opacity-75">Sign in to access the portal</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-1"></i><?= $error ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Username or Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Enter username or email" required autofocus>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <a href="<?= APP_URL ?>" class="text-muted text-decoration-none small">
                        <i class="bi bi-arrow-left me-1"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>

<script>
(function() {
    const container = document.getElementById('particles');
    if (container) {
        for (let i = 0; i < 25; i++) {
            const p = document.createElement('div');
            p.className = 'hero-particle';
            p.style.left = Math.random() * 100 + '%';
            p.style.animationDuration = (Math.random() * 12 + 10) + 's';
            p.style.animationDelay = (Math.random() * 12) + 's';
            p.style.width = p.style.height = (Math.random() * 3 + 2) + 'px';
            container.appendChild(p);
        }
    }
})();
</script>

<?php include __DIR__ . '/../templates/public_footer.php'; ?>
