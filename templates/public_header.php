<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#1a1a1a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>National County Sports Meet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="<?= APP_URL ?>assets/css/style.css" rel="stylesheet">
    <link href="<?= APP_URL ?>assets/css/public.css" rel="stylesheet">
</head>
<body class="public-body">

<nav class="navbar navbar-expand-lg navbar-dark public-navbar fixed-top" id="mainNav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= APP_URL ?>">
            <img src="<?= APP_URL ?>assets/images/ncsm.png" alt="NCSM Logo" style="height:38px;width:38px;object-fit:contain;border-radius:50%;margin-right:8px;">
            <span class="fw-bold">NCSM</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' || basename($_SERVER['PHP_SELF']) === '' ? 'active' : '' ?>" href="<?= APP_URL ?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'media.php' ? 'active' : '' ?>" href="<?= APP_URL ?>pages/media.php">Media</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>" href="<?= APP_URL ?>pages/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : '' ?>" href="<?= APP_URL ?>pages/contact.php">Contact Us</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning fw-bold <?= basename($_SERVER['PHP_SELF']) === 'public_livescore.php' ? 'active' : '' ?>" href="<?= APP_URL ?>pages/public_livescore.php">
                        <i class="bi bi-broadcast me-1"></i>LiveScore
                    </a>
                </li>
                <li class="nav-item mt-2 mt-lg-0">
                    <a class="btn btn-light btn-sm px-3 fw-semibold w-100 w-lg-auto" href="<?= APP_URL ?>auth/login.php">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Login
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
