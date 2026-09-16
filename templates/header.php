<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? APP_SHORT_NAME . ' - ' . $pageTitle : APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top border-bottom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="<?= APP_URL ?>pages/dashboard.php">
                <img src="<?= APP_URL ?>assets/images/ncsm.png" alt="Logo" style="height:40px;width:40px;object-fit:contain;border-radius:50%;" class="me-2">
                <span class="d-none d-md-inline" style="overflow:hidden;white-space:nowrap;display:inline-block!important;max-width:400px;"><span style="display:inline-block;animation:scrollText 18s linear infinite;">Ministry of Youth & Sports &nbsp;-&nbsp; <?= APP_SHORT_NAME ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></span>
            </a>
            <style>
            @keyframes scrollText {
                0% { transform: translateX(0); }
                100% { transform: translateX(-50%); }
            }
            </style>
            <button class="navbar-toggler" type="button" id="sidebarToggle" aria-label="Toggle sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php $notifCount = getUnreadNotificationCount($_SESSION['user_id']); ?>
                            <?php if ($notifCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $notifCount ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <?php $notifications = getRecentNotifications($_SESSION['user_id']); ?>
                            <?php if (empty($notifications)): ?>
                                <li><span class="dropdown-item text-muted">No notifications</span></li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                <li>
                                    <a class="dropdown-item <?= !$notif['is_read'] ? 'unread' : '' ?>" href="<?= $notif['link'] ?: '#' ?>">
                                        <div class="d-flex">
                                            <i class="bi bi-<?= $notif['type'] === 'success' ? 'check-circle-fill text-success' : ($notif['type'] === 'danger' ? 'x-circle-fill text-danger' : ($notif['type'] === 'warning' ? 'exclamation-circle-fill text-warning' : 'info-circle-fill text-info')) ?> me-2"></i>
                                            <div>
                                                <small class="fw-bold"><?= sanitize($notif['title']) ?></small>
                                                <br><small class="text-muted"><?= sanitize($notif['message']) ?></small>
                                                <br><small class="text-muted"><?= timeAgo($notif['created_at']) ?></small>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center small" href="#">View all</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= sanitize($_SESSION['user_name'] ?? 'User') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted"><?= getRoleLabel($_SESSION['user_role'] ?? '') ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <?php if (isAdminRole() && isset($_SESSION['user_county_name'])): 
                        $flagUrl = getCountyFlagUrl($_SESSION['user_county_name']);
                    ?>
                    <?php if ($flagUrl): ?>
                    <div class="text-center py-3 border-bottom border-white border-opacity-10 mb-2">
                        <img src="<?= $flagUrl ?>" alt="" style="height:48px;width:48px;object-fit:contain;border-radius:50%;border:2px solid rgba(255,255,255,0.3);">
                        <div class="mt-1 small text-white-50"><?= sanitize($_SESSION['user_county_name']) ?> (Group <?= $_SESSION['user_group_label'] ?>)</div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="<?= APP_URL ?>pages/dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <?php if ((hasRole('super_admin') || isCountyAdmin() || hasRole('county_coordinator')) && !isCoordViewer()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'players') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/players/register.php">
                                <i class="bi bi-person-plus me-2"></i>Register Player
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'list.php' ? 'active' : '' ?>" href="<?= APP_URL ?>pages/players/list.php">
                                <i class="bi bi-people me-2"></i>All Players
                            </a>
                        </li>
                        <?php if (hasRole(['association_admin'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'approvals') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/approvals/pending.php">
                                <i class="bi bi-check2-square me-2"></i>Approvals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= APP_URL ?>pages/approvals/history.php">
                                <i class="bi bi-clock-history me-2"></i>Approval History
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasRole(['super_admin'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'counties') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/counties/manage.php">
                                <i class="bi bi-geo-alt me-2"></i>Counties
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (!isCoordViewer()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/reports/index.php">
                                <i class="bi bi-bar-chart me-2"></i>Reports
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'games/index') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/games/index.php">
                                <i class="bi bi-broadcast me-2"></i>Live Scores
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'games/standings') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/games/standings.php">
                                <i class="bi bi-trophy me-2"></i>Standings
                            </a>
                        </li>
                        <?php if (hasRole(['match_commissioner', 'super_admin'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'games/report') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/games/report.php">
                                <i class="bi bi-clipboard-data me-2"></i>Match Reports
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'documents') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/documents/list.php">
                                <i class="bi bi-folder me-2"></i>Documents
                            </a>
                        </li>
                        <?php if (hasRole(['super_admin'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'messages') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/messages/list.php">
                                <i class="bi bi-envelope me-2"></i>Messages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'media') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/media/index.php">
                                <i class="bi bi-images me-2"></i>Media Management
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasRole('super_admin') || isCountyAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'games/manage') !== false ? 'active' : '' ?>" href="<?= APP_URL ?>pages/games/manage.php">
                                <i class="bi bi-gear me-2"></i>Manage Games
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#countyGroupsCollapse" aria-expanded="true">
                        <span>County Groups</span>
                        <i class="bi bi-chevron-down" id="countyGroupsIcon"></i>
                    </h6>
                    <div class="collapse show" id="countyGroupsCollapse">
                        <ul class="nav flex-column mb-2">
                            <?php foreach (['A', 'B', 'C', 'D'] as $g): ?>
                            <li class="nav-item">
                                <a class="nav-link small py-1" href="<?= APP_URL ?>pages/players/list.php?group=<?= $g ?>">
                                    <i class="bi bi-layers me-2"></i>Group <?= $g ?>
                                </a>
                            </li>
                            <?php if ((hasRole('super_admin') || isCountyAdmin() || hasRole('county_coordinator')) && !isCoordViewer()): ?>
                            <li class="nav-item">
                                <a class="nav-link small py-0 ps-4 text-muted" href="<?= APP_URL ?>pages/players/register.php?group=<?= $g ?>">
                                    <i class="bi bi-plus-circle me-1" style="font-size:0.7rem;"></i>Register
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Account</span>
                    </h6>
                    <ul class="nav flex-column mb-2">
                        <li class="nav-item">
                            <a class="nav-link" href="<?= APP_URL ?>pages/profile.php">
                                <i class="bi bi-person me-2"></i>My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= APP_URL ?>pages/change_password.php">
                                <i class="bi bi-key me-2"></i>Change Password
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="<?= APP_URL ?>auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Log Out
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="pt-4 pb-2 mb-3 border-bottom d-flex justify-content-between align-items-center">
                    <h1 class="h3"><?= $pageTitle ?? 'Dashboard' ?></h1>
                    <?php if (isset($pageActions)): ?>
                        <div><?= $pageActions ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($msg = getFlash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($msg = getFlash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($msg = getFlash('info')): ?>
                    <div class="alert alert-info alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
    <?php else: ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= isset($pageTitle) ? APP_SHORT_NAME . ' - ' . $pageTitle : APP_NAME ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <link href="<?= APP_URL ?>assets/css/style.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <?php endif; ?>
