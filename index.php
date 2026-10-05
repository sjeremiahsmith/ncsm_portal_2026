<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDb();
$sports = getSports();
$publicGroupsToShow = ['A', 'B', 'C', 'D'];

$pageTitle = 'Home';
include __DIR__ . '/templates/public_header.php';
?>

<!-- Hero Section -->
<section class="hero-section" id="home">
    <div class="hero-bg"></div>
    <div class="hero-particles" id="particles"></div>
    <div class="hero-content">
        <span class="hero-badge">Ministry of Youth & Sports &mdash; Republic of Liberia</span>
        <h1>National County<br><span class="highlight">Sports Meet</span></h1>
        <p>Uniting 15 counties across 4 groups through the power of sports. Football, Kickball, Basketball, and Athletics &mdash; celebrating athletic excellence nationwide.</p>
        <div class="hero-buttons">
            <a href="#standings" class="btn btn-primary-custom">
                <i class="bi bi-bar-chart-line-fill me-2"></i>View Standings
            </a>
            <a href="<?= APP_URL ?>pages/about.php" class="btn btn-primary-custom">
                <i class="bi bi-trophy me-2"></i>Learn About NCSM
            </a>
            <a href="https://www.facebook.com/moysliberia" target="_blank" rel="noopener noreferrer" class="btn btn-primary-custom">
                <i class="bi bi-facebook me-2"></i>Follow MOYS on Facebook
            </a>
        </div>
        <div class="hero-stats">
            <div class="hero-stat">
                <div class="stat-num">15</div>
                <div class="stat-text">Counties</div>
            </div>
            <div class="hero-stat">
                <div class="stat-num">4</div>
                <div class="stat-text">Sports</div>
            </div>
            <div class="hero-stat">
                <div class="stat-num">4</div>
                <div class="stat-text">Groups</div>
            </div>
            <div class="hero-stat">
                <div class="stat-num">2026</div>
                <div class="stat-text">Season</div>
            </div>
        </div>
    </div>
</section>

<!-- Sports Disciplines -->
<section class="section" id="sports">
    <div class="container">
        <div class="section-header">
            <span class="overline">Our Sports</span>
            <h2>Sports Disciplines</h2>
            <p>Four competitive disciplines bring together athletes from across all 15 counties of Liberia.</p>
        </div>
        <div class="row g-3 g-md-4">
            <div class="col-6 col-lg-3">
                <div class="sport-card sport-football">
                    <div class="sport-overlay">
                        <div style="font-size:4rem;margin-bottom:0.5rem;">⚽</div>
                        <h5>Football</h5>
                        <small>Liberia Football Association (LFA)</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="sport-card sport-kickball">
                    <div class="sport-overlay">
                        <div style="font-size:4rem;margin-bottom:0.5rem;">🥎</div>
                        <h5>Kickball</h5>
                        <small>Liberia Kickball Association (LKA)</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="sport-card sport-basketball">
                    <div class="sport-overlay">
                        <div style="font-size:4rem;margin-bottom:0.5rem;">🏀</div>
                        <h5>Basketball</h5>
                        <small>Liberia Basketball Association (LBA)</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="sport-card sport-athletics">
                    <div class="sport-overlay">
                        <div style="font-size:4rem;margin-bottom:0.5rem;">🏃</div>
                        <h5>Athletics</h5>
                        <small>Liberia Athletics Association (LAA)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header">
            <span class="overline">Portal Features</span>
            <h2>Built for Sports Management</h2>
            <p>A comprehensive digital platform designed to streamline the National County Sports Meet operations.</p>
        </div>
        <div class="row g-3 g-md-4">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h5>Player Registration</h5>
                    <p>Register players from all 15 counties with complete profiles, photos, and documentation.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <h5>Approval Workflow</h5>
                    <p>Streamlined review process where LFA, LKA, LBA, and LAA administrators verify and approve player registrations.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-broadcast"></i>
                    </div>
                    <h5>Live Scores</h5>
                    <p>Real-time match scores and results with auto-refreshing live updates every 30 seconds.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-trophy"></i>
                    </div>
                    <h5>League Standings</h5>
                    <p>Auto-calculated standings with points, goal difference, and win/draw/loss records per group.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <h5>Reports & Analytics</h5>
                    <p>Comprehensive reports by county, sport, and group with CSV export capabilities.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h5>Role-Based Access</h5>
                    <p>Secure system with Super Admin, County Coordinator, Group Admin, and discipline administrator roles.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- County Groups -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="overline">County Groups</span>
            <h2>15 Counties, 4 Groups</h2>
            <p>All 15 Liberian counties are organized into four competitive groups for the sports meet.</p>
        </div>
        <div class="row g-3 g-md-4">
            <?php
            $groups = [
                'A' => ['color' => '#dc3545', 'counties' => [
                    ['name' => 'Nimba', 'host' => false],
                    ['name' => 'Grand Gedeh', 'host' => true],
                    ['name' => 'River Gee', 'host' => false],
                    ['name' => 'Gbarpolu', 'host' => false],
                ]],
                'B' => ['color' => '#0d6efd', 'counties' => [
                    ['name' => 'Grand Cape Mount', 'host' => false],
                    ['name' => 'Bong', 'host' => true],
                    ['name' => 'Maryland', 'host' => false],
                    ['name' => 'River Cess', 'host' => false],
                ]],
                'C' => ['color' => '#C8A032', 'counties' => [
                    ['name' => 'Grand Bassa', 'host' => false],
                    ['name' => 'Lofa', 'host' => true],
                    ['name' => 'Montserrado', 'host' => false],
                    ['name' => 'Sinoe', 'host' => false],
                ]],
                'D' => ['color' => '#1B5E20', 'counties' => [
                    ['name' => 'Margibi', 'host' => false],
                    ['name' => 'Grand Kru', 'host' => true],
                    ['name' => 'Bomi', 'host' => false],
                ]],
            ];
            foreach ($groups as $label => $group):
            ?>
            <div class="col-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="group-badge group-<?= $label ?>" style="width:40px;height:40px;line-height:40px;font-size:1rem;"><?= $label ?></span>
                            <h5 class="ms-2 mb-0 fw-bold">Group <?= $label ?></h5>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($group['counties'] as $county): ?>
                            <div class="county-grid-item">
                                <?php
                                $flagFile = '';
                                $flagMap = [
                                    'Bomi' => 'Bomi.png', 'Bong' => 'Bong.png', 'Gbarpolu' => 'Gbarpolu.png',
                                    'Grand Bassa' => 'Grand Bassa.png', 'Grand Cape Mount' => 'Grand Cape Mount.png',
                                    'Grand Gedeh' => 'Grand Gedeh.png', 'Grand Kru' => 'Grand Kru.png',
                                    'Lofa' => 'Lofa.png', 'Margibi' => 'Margibi.png', 'Maryland' => 'Maryland.png',
                                    'Montserrado' => 'Montserrado.png', 'Nimba' => 'Nimba.png',
                                    'River Cess' => 'Rivercess.jpg', 'River Gee' => 'River Gee.png', 'Sinoe' => 'Sinoe.png',
                                ];
                                $flagFile = $flagMap[$county['name']] ?? '';
                                ?>
                                <?php if ($flagFile): ?>
                                <img src="<?= APP_URL ?>assets/images/<?= $flagFile ?>" alt="<?= $county['name'] ?>">
                                <?php endif; ?>
                                <div>
                                    <div class="county-name"><?= $county['name'] ?> <?php if ($county['host']): ?><span class="badge bg-warning text-dark ms-1" style="font-size:0.55rem;">HOST</span><?php endif; ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Group Standings -->
<section class="section" id="standings">
    <div class="container">
        <div class="section-header">
            <span class="overline">Standings</span>
            <h2>Game Standings by Group</h2>
            <p>Visitors can follow the latest standings for each sport across Groups A, B, C, and D.</p>
        </div>

        <style>
        .home-standing-table {
            border-collapse: separate;
            border-spacing: 0 2px;
        }
        .home-standing-table thead th {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6c757d;
            border: none;
            padding: 0.75rem 0.45rem;
            background: transparent;
        }
        .home-standing-table tbody td {
            vertical-align: middle;
            padding: 0.6rem 0.45rem;
            border: none;
            background: #fff;
        }
        .home-standing-table tbody tr td:first-child { border-radius: 8px 0 0 8px; }
        .home-standing-table tbody tr td:last-child { border-radius: 0 8px 8px 0; }
        .home-standing-table tbody tr { box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .home-standing-table .team-col { min-width: 180px; font-weight: 600; font-size: 0.9rem; }
        .home-standing-table .stat-col { width: 36px; font-size: 0.8rem; color: #495057; }
        .home-standing-header {
            color: #fff;
            border-radius: 12px 12px 0 0;
            padding: 1rem 1.25rem;
        }
        .home-standing-header-default { background: linear-gradient(135deg, #1a237e, #283593); }
        .home-standing-header-kickball { background: linear-gradient(135deg, #dc3545, #c82333); }
        .home-standing-sport-title { font-weight: 700; }
        .home-position-indicator {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 0.75rem;
            font-weight: 700;
            background: #e9ecef;
            color: #6c757d;
        }
        .home-standing-table .pos-1 .home-position-indicator { background: linear-gradient(135deg, #ffd700, #ffb300); color: #5c4100; }
        .home-standing-table .pos-2 .home-position-indicator { background: linear-gradient(135deg, #e0e0e0, #bdbdbd); color: #424242; }
        .home-standing-table .pos-3 .home-position-indicator { background: linear-gradient(135deg, #cd7f32, #b8712a); color: #fff; }
        .home-team-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 0.6rem;
            font-weight: 700;
            color: #fff;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .home-gd-positive { color: #28a745; font-weight: 600; }
        .home-gd-negative { color: #dc3545; font-weight: 600; }
        .home-gd-zero { color: #6c757d; }
        .home-pts-cell { font-size: 1.05rem; font-weight: 800; }
        .home-pts-cell-default { color: #1a237e; }
        .home-pts-cell-kickball { color: #dc3545; }
        </style>

        <?php
        $anyPublicStandings = false;
        foreach ($sports as $sport):
            $sportId = (int)$sport['id'];
            $sportIsKickball = strtoupper((string)$sport['association_code']) === 'LKA';
            $hasSportStandings = false;
            foreach ($publicGroupsToShow as $groupLabel) {
                if (!empty(getStandingsData($db, $sportId, $groupLabel))) {
                    $hasSportStandings = true;
                    break;
                }
            }
            if (!$hasSportStandings) {
                continue;
            }
            $anyPublicStandings = true;
        ?>
        <div class="mb-5">
            <div class="d-flex align-items-center mb-3">
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $sportIsKickball ? '#dc3545' : '#1a237e' ?>;margin-right:8px;"></span>
                <h4 class="mb-0 home-standing-sport-title" style="color:<?= $sportIsKickball ? '#dc3545' : '#1a237e' ?>;"><?= sanitize($sport['name']) ?> Standings</h4>
            </div>
            <div class="row g-4">
                <?php foreach ($publicGroupsToShow as $groupLabel): ?>
                    <?php
                    $standings = getStandingsData($db, $sportId, $groupLabel);
                    if (empty($standings)) {
                        continue;
                    }
                    $rank = 0;
                    $totalTeams = count($standings);
                    ?>
                    <div class="col-12 col-xl-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="home-standing-header <?= $sportIsKickball ? 'home-standing-header-kickball' : 'home-standing-header-default' ?> d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><span class="badge rounded-pill bg-white text-dark me-2" style="font-size:0.7rem;">Group</span> Group <?= $groupLabel ?></h5>
                                <small><?= $totalTeams ?> teams</small>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table home-standing-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:36px;">#</th>
                                                <th class="team-col">Team</th>
                                                <th class="text-center stat-col"><?= $sportIsKickball ? 'GP' : 'P' ?></th>
                                                <th class="text-center stat-col">W</th>
                                                <th class="text-center stat-col">D</th>
                                                <th class="text-center stat-col">L</th>
                                                <th class="text-center stat-col"><?= $sportIsKickball ? 'HRF' : 'F' ?></th>
                                                <th class="text-center stat-col"><?= $sportIsKickball ? 'HRA' : 'A' ?></th>
                                                <th class="text-center" style="width:44px;"><?= $sportIsKickball ? 'HRD' : 'GD' ?></th>
                                                <th class="text-center" style="width:56px;">PTS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($standings as $standing): $rank++; ?>
                                            <tr class="<?= $rank <= 3 ? 'pos-' . $rank : '' ?>">
                                                <td class="text-center"><span class="home-position-indicator"><?= $rank ?></span></td>
                                                <td class="team-col">
                                                    <span class="home-team-badge group-<?= $standing['group'] ?>"><?= $standing['group'] ?></span>
                                                    <?php $flagUrl = getCountyFlagUrl($standing['name']); ?>
                                                    <?php if ($flagUrl): ?>
                                                        <img src="<?= $flagUrl ?>" alt="" style="width:20px;height:20px;object-fit:contain;border-radius:50%;margin-right:6px;vertical-align:middle;">
                                                    <?php endif; ?>
                                                    <?= sanitize($standing['name']) ?>
                                                </td>
                                                <td class="text-center stat-col"><?= $standing['played'] ?></td>
                                                <td class="text-center stat-col"><?= $standing['wins'] ?></td>
                                                <td class="text-center stat-col"><?= $standing['draws'] ?></td>
                                                <td class="text-center stat-col"><?= $standing['losses'] ?></td>
                                                <td class="text-center stat-col"><strong><?= $standing['gf'] ?></strong></td>
                                                <td class="text-center stat-col"><?= $standing['ga'] ?></td>
                                                <td class="text-center <?= $standing['gd'] > 0 ? 'home-gd-positive' : ($standing['gd'] < 0 ? 'home-gd-negative' : 'home-gd-zero') ?>"><?= $standing['gd'] > 0 ? '+' : '' ?><?= $standing['gd'] ?></td>
                                                <td class="text-center home-pts-cell <?= $sportIsKickball ? 'home-pts-cell-kickball' : 'home-pts-cell-default' ?>"><?= $standing['pts'] ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!$anyPublicStandings): ?>
            <div class="text-center py-4">
                <div style="font-size:4rem;color:#dee2e6;"><i class="bi bi-trophy"></i></div>
                <h5 class="mt-3 text-muted">No standings yet</h5>
                <p class="text-muted small mb-0">Standings will appear here once completed match scores are entered.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- MOYS Link -->
<section class="section" style="background:linear-gradient(135deg,#0a3d0a,#1a7a1a);color:#fff;">
    <div class="container text-center">
        <h3 class="fw-bold mb-2">Ministry of Youth & Sports</h3>
        <p class="mb-3" style="color:rgba(255,255,255,0.8);">The National County Sports Meet is organized under the auspices of the Ministry of Youth & Sports of Liberia.</p>
        <a href="https://moys.gov.lr" target="_blank" rel="noopener" class="btn btn-light btn-lg fw-semibold">
            <i class="bi bi-globe me-2"></i>Visit MOYS Website
        </a>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <h2>Ready to Get Started?</h2>
        <p>Access the portal to register players, manage matches, and view live scores for the National County Sports Meet.</p>
        <a href="<?= APP_URL ?>auth/login.php" class="btn btn-light btn-lg">
            <i class="bi bi-box-arrow-in-right me-2"></i>Login to Portal
        </a>
    </div>
</section>

<?php include __DIR__ . '/templates/public_footer.php'; ?>

<script>
(function() {
    const container = document.getElementById('particles');
    for (let i = 0; i < 25; i++) {
        const p = document.createElement('div');
        p.className = 'hero-particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDuration = (Math.random() * 12 + 10) + 's';
        p.style.animationDelay = (Math.random() * 12) + 's';
        p.style.width = p.style.height = (Math.random() * 3 + 2) + 'px';
        container.appendChild(p);
    }
})();
</script>
