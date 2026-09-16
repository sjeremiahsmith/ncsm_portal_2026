<?php
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'About';
include __DIR__ . '/../templates/public_header.php';
?>

<!-- Hero -->
<section class="about-hero">
    <div class="hero-bg"></div>
    <div class="container">
        <span class="hero-badge">Our Story</span>
        <h1>About NCSM</h1>
        <p>The history and mission of the National County Sports Meet &mdash; Liberia's premier inter-county sporting event.</p>
    </div>
</section>

<!-- Mission -->
<section class="section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="hero-badge" style="background:rgba(220,53,69,0.1);border-color:rgba(220,53,69,0.2);color:#dc3545;">Our Mission</span>
                <h2 style="font-weight:800;font-size:2.2rem;margin:1rem 0;">Uniting Liberia Through Sports</h2>
                <p style="color:#6c757d;line-height:1.8;font-size:1.05rem;">
                    The National County Sports Meet is the flagship inter-county sporting competition in the Republic of Liberia.
                    Organized under the Ministry of Youth & Sports, it brings together athletes from all 15 counties to compete
                    in football, kickball, basketball, and athletics.
                </p>
                <p style="color:#6c757d;line-height:1.8;font-size:1.05rem;">
                    More than just a sporting event, the NCSM serves as a vehicle for national unity, peacebuilding, and youth
                    development. It provides a platform for young Liberians to showcase their athletic talent while fostering
                    friendly competition and cultural exchange among counties.
                </p>
                <div class="row g-3 mt-4">
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <div class="feature-icon" style="width:45px;height:45px;font-size:1rem;border-radius:10px;min-width:45px;">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ms-3">
                                <div class="fw-bold fs-5">15</div>
                                <small class="text-muted">Counties</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <div class="feature-icon" style="width:45px;height:45px;font-size:1rem;border-radius:10px;min-width:45px;">
                                <i class="bi bi-trophy"></i>
                            </div>
                            <div class="ms-3">
                                <div class="fw-bold fs-5">4</div>
                                <small class="text-muted">Sports</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="<?= APP_URL ?>assets/images/ncsm1.jpg" alt="NCSM Event" class="img-fluid rounded-4 shadow" style="width:100%;object-fit:cover;max-height:450px;">
            </div>
        </div>
    </div>
</section>

<!-- History Timeline -->
<section class="section" style="background:#f0f0f0;">
    <div class="container">
        <div class="section-header">
            <span class="overline">Our Journey</span>
            <h2>History of the NCSM</h2>
            <p>Decades of bringing Liberia together through competitive sports.</p>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="history-timeline">
                    <div class="history-item">
                        <a href="<?= APP_URL ?>pages/history.php#origins" class="year-badge" style="text-decoration:none;">Origins</a>
                        <h5>Inception of County Sports</h5>
                        <p>The concept of inter-county sports competitions in Liberia dates back to the pre-war era, where informal county-based matches helped build community pride and unity among the diverse ethnic groups of Liberia.</p>
                    </div>

                    <div class="history-item">
                        <a href="<?= APP_URL ?>pages/history.php#revival" class="year-badge" style="text-decoration:none;">Revival Era</a>
                        <h5>Post-Conflict Revival</h5>
                        <p>Following the Liberian civil wars, the National County Sports Meet was revived as part of the nation's healing process. Sports became a powerful tool for reconciliation, bringing together people from regions once divided by conflict.</p>
                    </div>

                    <div class="history-item">
                        <a href="<?= APP_URL ?>pages/history.php#growth" class="year-badge" style="text-decoration:none;">Growth</a>
                        <h5>Expanding to Four Sports</h5>
                        <p>The tournament expanded from football-only to include kickball, basketball, and athletics. Each sport is governed by its respective national association: LFA (Football), LKA (Kickball), LBA (Basketball), and LAA (Athletics).</p>
                    </div>

                    <div class="history-item">
                        <a href="<?= APP_URL ?>pages/history.php#structure" class="year-badge" style="text-decoration:none;">Structure</a>
                        <h5>County Grouping System</h5>
                        <p>To ensure balanced competition and reduce travel costs, the 15 counties were organized into four groups (A, B, C, D). Each group contains 3-4 geographically proximate counties, enabling more accessible matchups during the group stage.</p>
                    </div>

                    <div class="history-item">
                        <a href="<?= APP_URL ?>pages/history.php#digital" class="year-badge" style="text-decoration:none;">Digital Era</a>
                        <h5>Modernization & Digitization</h5>
                        <p>The Ministry of Youth & Sports introduced digital systems for player registration, approval workflows, live scoring, and standings management. The National County Sports System was developed to bring efficiency and transparency to the entire process.</p>
                    </div>

                    <div class="history-item">
                        <a href="<?= APP_URL ?>pages/history.php#season2026" class="year-badge" style="text-decoration:none;">2026</a>
                        <h5>Current Season</h5>
                        <p>The 2026 National County Sports Meet continues to be the premier sporting event in Liberia, bringing together thousands of athletes and millions of fans across the nation. The event showcases the best of Liberian athletic talent and county pride.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- County Groupings Detail -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="overline">Competition Format</span>
            <h2>County Groupings</h2>
            <p>The 15 counties are organized into four competitive groups for the tournament.</p>
        </div>
        <div class="row g-3 g-md-4">
            <?php
            $groups = [
                'A' => [
                    'color' => '#dc3545',
                    'counties' => ['Nimba', 'Grand Gedeh (Host)', 'River Gee', 'Gbarpolu'],
                    'desc' => 'Eastern and central counties with strong competitive spirit.'
                ],
                'B' => [
                    'color' => '#0d6efd',
                    'counties' => ['Grand Cape Mount', 'Bong (Host)', 'Maryland', 'River Cess'],
                    'desc' => 'Western and central counties bringing fierce competition.'
                ],
                'C' => [
                    'color' => '#C8A032',
                    'counties' => ['Grand Bassa', 'Lofa (Host)', 'Montserrado', 'Sinoe'],
                    'desc' => 'The most populous group featuring the capital county Montserrado.'
                ],
                'D' => [
                    'color' => '#1B5E20',
                    'counties' => ['Margibi', 'Grand Kru (Host)', 'Bomi'],
                    'desc' => 'Southern and coastal counties with passionate fan bases.'
                ],
            ];
            foreach ($groups as $label => $group):
            ?>
            <div class="col-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="group-badge group-<?= $label ?>" style="width:45px;height:45px;line-height:45px;font-size:1.1rem;"><?= $label ?></span>
                            <div class="ms-3">
                                <h5 class="mb-0 fw-bold">Group <?= $label ?></h5>
                            </div>
                        </div>
                        <p class="text-muted small mb-3"><?= $group['desc'] ?></p>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($group['counties'] as $county): ?>
                            <li class="py-2 border-bottom d-flex align-items-center">
                                <i class="bi bi-geo-alt-fill me-2" style="color:<?= $group['color'] ?>;"></i>
                                <?= $county ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Sports Detail -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header">
            <span class="overline">Sports</span>
            <h2>Disciplines & Associations</h2>
            <p>Four national sports associations govern the disciplines featured in the NCSM.</p>
        </div>
        <div class="row g-3 g-md-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon" style="background:linear-gradient(135deg, #1a7a1a, #2d9e2d);">
                        <i class="bi bi-circle-fill"></i>
                    </div>
                    <h5>Football</h5>
                    <p>Governed by the Liberia Football Association (LFA). The most popular sport with the highest number of registered players across all 15 counties.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon" style="background:linear-gradient(135deg, #dc3545, #ff4d5e);">
                        <i class="bi bi-circle-fill"></i>
                    </div>
                    <h5>Kickball</h5>
                    <p>Governed by the Liberia Kickball Association (LKA). A beloved sport especially popular among female athletes, promoting gender inclusion.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon" style="background:linear-gradient(135deg, #e67e22, #f39c12);">
                        <i class="bi bi-circle-fill"></i>
                    </div>
                    <h5>Basketball</h5>
                    <p>Governed by the Liberia Basketball Association (LBA). Growing in popularity across counties with competitive league structures.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon" style="background:linear-gradient(135deg, #2980b9, #3498db);">
                        <i class="bi bi-circle-fill"></i>
                    </div>
                    <h5>Athletics</h5>
                    <p>Governed by the Liberia Athletics Association (LAA). Track and field events showcasing the raw speed and endurance of Liberian athletes.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <h2>Support Your County</h2>
        <p>Login to the portal to register players, follow live scores, and view standings for the 2026 National County Sports Meet.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="<?= APP_URL ?>pages/champions.php" class="btn btn-warning btn-lg">
                <i class="bi bi-trophy me-2"></i>Champions 1954-2024
            </a>
            <a href="<?= APP_URL ?>auth/login.php" class="btn btn-light btn-lg">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login to Portal
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../templates/public_footer.php'; ?>
