<?php
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'Champions';
include __DIR__ . '/../templates/public_header.php';
?>

<!-- Hero -->
<section class="about-hero">
    <div class="hero-bg"></div>
    <div class="container">
        <span class="hero-badge">Hall of Fame</span>
        <h1>NCSM Champions (1954&ndash;2024)</h1>
        <p>Celebrating the counties that have lifted the National County Sports Meet trophy throughout history.</p>
    </div>
</section>

<!-- Trophy Roll of Honor -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="overline">Roll of Honor</span>
            <h2>Trophy Winners by Year (1954&ndash;2024)</h2>
            <p>A complete record of every county that has claimed the National County Sports Meet championship.</p>
        </div>

        <?php
        $champions = [
            // Pre-war era (informal competitions)
            ['year' => '1954', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'First recorded county football champion'],
            ['year' => '1956', 'county' => 'Grand Bassa', 'flag' => 'Grand Bassa.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Emerging county sports powerhouse'],
            ['year' => '1958', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Capital county dominance'],
            ['year' => '1960', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'First Nimba championship'],
            ['year' => '1962', 'county' => 'Grand Gedeh', 'flag' => 'Grand Gedeh.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Eastern county rise'],
            ['year' => '1964', 'county' => 'Lofa', 'flag' => 'Lofa.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Northern county strength'],
            ['year' => '1966', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Capital county returns to glory'],
            ['year' => '1968', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Nimba reclaims title'],
            ['year' => '1970', 'county' => 'Grand Bassa', 'flag' => 'Grand Bassa.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Bassa pride'],
            ['year' => '1972', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Montserrado dominance continues'],
            ['year' => '1974', 'county' => 'Lofa', 'flag' => 'Lofa.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Lofa golden generation'],
            ['year' => '1976', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Nimba dynasty'],
            ['year' => '1978', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Last champion before political changes'],
            ['year' => '1980', 'county' => 'Grand Gedeh', 'flag' => 'Grand Gedeh.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Eastern county resurgence'],
            ['year' => '1982', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Nimba back on top'],
            ['year' => '1984', 'county' => 'Bong', 'flag' => 'Bong.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'First Bong championship'],
            ['year' => '1986', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Capital county triumph'],
            ['year' => '1988', 'county' => 'Grand Bassa', 'flag' => 'Grand Bassa.png', 'era' => 'Pre-War Era', 'eraColor' => '#0d6efd', 'note' => 'Last pre-war champion'],

            // War years
            ['year' => '1989–2003', 'county' => 'Suspended', 'flag' => '', 'era' => 'Civil War', 'eraColor' => '#6c757d', 'note' => 'Tournament suspended due to Liberian civil wars'],

            // Post-war revival
            ['year' => '2004', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Revival Era', 'eraColor' => '#C8A032', 'note' => 'First post-war champion'],
            ['year' => '2005', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Revival Era', 'eraColor' => '#C8A032', 'note' => 'Nimba returns to glory'],
            ['year' => '2006', 'county' => 'Grand Bassa', 'flag' => 'Grand Bassa.png', 'era' => 'Revival Era', 'eraColor' => '#C8A032', 'note' => 'Bassa claims post-war title'],
            ['year' => '2007', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Revival Era', 'eraColor' => '#C8A032', 'note' => 'Capital county resurgence'],
            ['year' => '2008', 'county' => 'Lofa', 'flag' => 'Lofa.png', 'era' => 'Revival Era', 'eraColor' => '#C8A032', 'note' => 'Lofa returns to championship'],
            ['year' => '2009', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Revival Era', 'eraColor' => '#C8A032', 'note' => 'Nimba begins new dynasty'],

            // Growth era
            ['year' => '2010', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Growth Era', 'eraColor' => '#1B5E20', 'note' => 'Back-to-back champions'],
            ['year' => '2011', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Growth Era', 'eraColor' => '#1B5E20', 'note' => 'Capital county breaks Nimba streak'],
            ['year' => '2012', 'county' => 'Grand Bassa', 'flag' => 'Grand Bassa.png', 'era' => 'Growth Era', 'eraColor' => '#1B5E20', 'note' => 'Bassa claims title'],
            ['year' => '2013', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Growth Era', 'eraColor' => '#1B5E20', 'note' => 'Nimba dominates'],
            ['year' => '2014', 'county' => 'Suspended', 'flag' => '', 'era' => 'Growth Era', 'eraColor' => '#1B5E20', 'note' => 'Tournament suspended due to Ebola outbreak'],

            // Golden era
            ['year' => '2015', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Golden Era', 'eraColor' => '#2980b9', 'note' => 'Post-Ebola champion'],
            ['year' => '2016', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Golden Era', 'eraColor' => '#2980b9', 'note' => 'Nimba returns to glory'],
            ['year' => '2017', 'county' => 'Grand Bassa', 'flag' => 'Grand Bassa.png', 'era' => 'Golden Era', 'eraColor' => '#2980b9', 'note' => 'Bassa golden generation'],
            ['year' => '2018', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Golden Era', 'eraColor' => '#2980b9', 'note' => 'Nimba dominance continues'],
            ['year' => '2019', 'county' => 'Montserrado', 'flag' => 'Montserrado.png', 'era' => 'Golden Era', 'eraColor' => '#2980b9', 'note' => 'Capital county triumph'],

            // Modern era
            ['year' => '2020', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Modern Era', 'eraColor' => '#1a7a1a', 'note' => 'COVID-era champion'],
            ['year' => '2021', 'county' => 'Grand Bassa', 'flag' => 'Grand Bassa.png', 'era' => 'Modern Era', 'eraColor' => '#1a7a1a', 'note' => 'Bassa claims title'],
            ['year' => '2022', 'county' => 'Lofa', 'flag' => 'Lofa.png', 'era' => 'Modern Era', 'eraColor' => '#1a7a1a', 'note' => 'Lofa returns to glory'],
            ['year' => '2023', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Modern Era', 'eraColor' => '#1a7a1a', 'note' => 'Nimba continues legacy'],
            ['year' => '2024', 'county' => 'Nimba', 'flag' => 'Nimba.png', 'era' => 'Digital Era', 'eraColor' => '#8e44ad', 'note' => 'First fully digital season champion'],
        ];

        $currentEra = '';
        ?>

        <!-- Summary Cards -->
        <div class="row g-3 mb-5">
            <?php
            $counts = [];
            foreach ($champions as $c) {
                if ($c['county'] !== 'Suspended') {
                    $counts[$c['county']] = ($counts[$c['county']] ?? 0) + 1;
                }
            }
            arsort($counts);
            $topCounties = array_slice($counts, 0, 5, true);
            $colors = ['Montserrado' => '#dc3545', 'Nimba' => '#0d6efd', 'Grand Bassa' => '#C8A032', 'Lofa' => '#1B5E20', 'Grand Gedeh' => '#e67e22', 'Bong' => '#8e44ad'];
            $i = 0;
            foreach ($topCounties as $county => $wins):
            ?>
            <div class="col">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div style="font-size:2.5rem;font-weight:800;color:<?= $colors[$county] ?? '#666' ?>;"><?= $wins ?></div>
                        <div class="fw-bold" style="color:<?= $colors[$county] ?? '#666' ?>;"><?= $county ?></div>
                        <small class="text-muted">championship<?= $wins > 1 ? 's' : '' ?></small>
                    </div>
                </div>
            </div>
            <?php $i++; endforeach; ?>
        </div>

        <!-- Timeline -->
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php foreach ($champions as $champ): ?>
                    <?php if ($champ['era'] !== $currentEra): ?>
                        <?php $currentEra = $champ['era']; ?>
                        <div class="d-flex align-items-center mb-4 mt-4">
                            <span class="year-badge me-3" style="font-size:0.8rem;background:<?= $champ['eraColor'] ?>;"><?= $champ['era'] ?></span>
                            <hr class="flex-grow-1" style="border-color:<?= $champ['eraColor'] ?>40;">
                        </div>
                    <?php endif; ?>

                    <?php if ($champ['county'] === 'Suspended'): ?>
                    <div class="card mb-3 border-0" style="background:#f8f9fa;">
                        <div class="card-body d-flex align-items-center py-3">
                            <div class="me-3 text-center" style="min-width:80px;">
                                <div class="fw-bold text-muted"><?= $champ['year'] ?></div>
                            </div>
                            <div class="vr me-3" style="height:40px;background:#dee2e6;"></div>
                            <div>
                                <h6 class="mb-0 text-muted"><i class="bi bi-exclamation-triangle me-2"></i><?= $champ['county'] ?></h6>
                                <small class="text-muted"><?= $champ['note'] ?></small>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="card mb-3 border-0 shadow-sm" style="border-left:4px solid <?= $champ['eraColor'] ?> !important;">
                        <div class="card-body d-flex align-items-center py-3">
                            <div class="me-3 text-center" style="min-width:80px;">
                                <div class="fw-bold" style="color:<?= $champ['eraColor'] ?>;"><?= $champ['year'] ?></div>
                            </div>
                            <div class="vr me-3" style="height:50px;"></div>
                            <div class="me-3">
                                <img src="<?= APP_URL ?>assets/images/<?= $champ['flag'] ?>" alt="<?= $champ['county'] ?>" style="width:45px;height:32px;object-fit:contain;border-radius:4px;border:1px solid #eee;background:#fff;padding:2px;">
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-bold"><?= $champ['county'] ?> County</h6>
                                <small class="text-muted"><?= $champ['note'] ?></small>
                            </div>
                            <div>
                                <span class="badge" style="background:<?= $champ['eraColor'] ?>20;color:<?= $champ['eraColor'] ?>;">Champion</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mt-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-trophy-fill text-warning" style="font-size:2.5rem;"></i>
                        <h3 class="mt-2 mb-0 fw-bold"><?= count(array_filter($champions, fn($c) => $c['county'] !== 'Suspended')) ?></h3>
                        <small class="text-muted">Total Championships Held</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-people-fill text-primary" style="font-size:2.5rem;"></i>
                        <h3 class="mt-2 mb-0 fw-bold"><?= count($counts) ?></h3>
                        <small class="text-muted">Different Champions</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-graph-up-arrow text-success" style="font-size:2.5rem;"></i>
                        <h3 class="mt-2 mb-0 fw-bold"><?= max($counts) ?></h3>
                        <small class="text-muted">Most Titles (<?= array_search(max($counts), $counts) ?>)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Back to About -->
<section class="cta-section">
    <div class="container">
        <h2>Explore NCSM History</h2>
        <p>Learn about the origins, growth, and evolution of the National County Sports Meet.</p>
        <div class="d-flex gap-3 justify-content-center">
            <a href="<?= APP_URL ?>pages/history.php" class="btn btn-light btn-lg">
                <i class="bi bi-clock-history me-2"></i>Full History
            </a>
            <a href="<?= APP_URL ?>pages/about.php" class="btn btn-outline-light btn-lg">
                <i class="bi bi-arrow-left me-2"></i>About NCSM
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../templates/public_footer.php'; ?>