<?php
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'Media';
include __DIR__ . '/../templates/public_header.php';
?>

<!-- Hero -->
<section class="media-hero">
    <div class="hero-bg"></div>
    <div class="container">
        <span class="hero-badge">Gallery & Videos</span>
        <h1>Media Center</h1>
        <p>Watch highlights, view photos, and relive the best moments from the National County Sports Meet.</p>
    </div>
</section>

<!-- Videos Section -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="overline">Watch</span>
            <h2>Featured Videos</h2>
            <p>Highlights, match recaps, and memorable moments from the games.</p>
        </div>
        <div class="row g-3 g-md-4">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="video-card">
                    <div class="video-thumb">
                        <img src="<?= APP_URL ?>assets/images/ncsm1.jpg" alt="NCSM Highlights">
                        <div class="play-btn"><i class="bi bi-play-fill"></i></div>
                    </div>
                    <div class="video-info">
                        <h6>NCSM 2026 Opening Ceremony</h6>
                        <small><i class="bi bi-clock me-1"></i>Full Event Coverage</small>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="video-card">
                    <div class="video-thumb">
                        <img src="<?= APP_URL ?>assets/images/ncsm.png" alt="Football Highlights" style="object-fit:contain;background:#222;padding:2rem;">
                        <div class="play-btn"><i class="bi bi-play-fill"></i></div>
                    </div>
                    <div class="video-info">
                        <h6>Football Championship Finals</h6>
                        <small><i class="bi bi-clock me-1"></i>Best Goals & Plays</small>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="video-card">
                    <div class="video-thumb">
                        <img src="<?= APP_URL ?>assets/images/ncsm1.jpg" alt="Kickball Finals" style="object-position:top;">
                        <div class="play-btn"><i class="bi bi-play-fill"></i></div>
                    </div>
                    <div class="video-info">
                        <h6>Kickball Tournament Highlights</h6>
                        <small><i class="bi bi-clock me-1"></i>Tournament Recap</small>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="video-card">
                    <div class="video-thumb">
                        <img src="<?= APP_URL ?>assets/images/ncsm.png" alt="Basketball" style="object-fit:contain;background:#222;padding:2rem;">
                        <div class="play-btn"><i class="bi bi-play-fill"></i></div>
                    </div>
                    <div class="video-info">
                        <h6>Basketball Semi-Finals</h6>
                        <small><i class="bi bi-clock me-1"></i>Game Recap</small>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="video-card">
                    <div class="video-thumb">
                        <img src="<?= APP_URL ?>assets/images/ncsm1.jpg" alt="Athletics" style="object-position:bottom;">
                        <div class="play-btn"><i class="bi bi-play-fill"></i></div>
                    </div>
                    <div class="video-info">
                        <h6>Athletics Track & Field</h6>
                        <small><i class="bi bi-clock me-1"></i>Sprint Finals</small>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="video-card">
                    <div class="video-thumb">
                        <img src="<?= APP_URL ?>assets/images/ncsm.png" alt="Awards" style="object-fit:contain;background:#222;padding:2rem;">
                        <div class="play-btn"><i class="bi bi-play-fill"></i></div>
                    </div>
                    <div class="video-info">
                        <h6>Awards & Closing Ceremony</h6>
                        <small><i class="bi bi-clock me-1"></i>Celebration</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Gallery Section -->
<section class="section" style="background:#f0f0f0;">
    <div class="container">
        <div class="section-header">
            <span class="overline">Gallery</span>
            <h2>Photo Gallery</h2>
            <p>Browse through photos from the National County Sports Meet events.</p>
        </div>
        <div class="row g-2 g-md-3">
            <a href="<?= APP_URL ?>pages/gallery.php?cat=opening-ceremony" class="col-6 col-lg-4 text-decoration-none">
                <div class="gallery-item">
                    <img src="<?= APP_URL ?>assets/images/open.jpg" alt="Opening Ceremony">
                    <div class="gallery-overlay">
                        <h6 class="mb-0">Opening Ceremony</h6>
                        <small>National Stadium, Monrovia</small>
                    </div>
                </div>
            </a>
            <a href="<?= APP_URL ?>pages/gallery.php?cat=champion-2024" class="col-6 col-lg-4 text-decoration-none">
                <div class="gallery-item">
                    <img src="<?= APP_URL ?>assets/images/champ.jpg" alt="Nimba County Champion" style="object-fit:cover;">
                    <img src="<?= APP_URL ?>assets/images/nimba1.jpg" alt="Nimba County Champion" style="object-fit:cover;">
                    <img src="<?= APP_URL ?>assets/images/nimba5.jpg" alt="Nimba County Champion" style="object-fit:cover;">
                    <img src="<?= APP_URL ?>assets/images/nimba2.jpg" alt="Nimba County Champion" style="object-fit:cover;">
                    <img src="<?= APP_URL ?>assets/images/nimba3.jpg" alt="Nimba County Champion" style="object-fit:cover;">
                    <img src="<?= APP_URL ?>assets/images/nimba4.jpg" alt="Nimba County Champion" style="object-fit:cover;">
                    <div class="gallery-overlay">
                        <h6 class="mb-0">Nimba County Champion 2024/2025</h6>
                    </div>
                </div>
            </a>
            <a href="<?= APP_URL ?>pages/gallery.php?cat=bong-county" class="col-6 col-lg-4 text-decoration-none">
                <div class="gallery-item">
                    <img src="<?= APP_URL ?>assets/images/Bong.jpg" alt="Bong County" style="object-fit:cover;">
                    <div class="gallery-overlay">
                        <h6 class="mb-0">Bong County Team</h6>
                    </div>
                </div>
            </a>
            <a href="<?= APP_URL ?>pages/gallery.php?cat=grand-gedeh" class="col-6 col-lg-4 text-decoration-none">
                <div class="gallery-item">
                    <img src="<?= APP_URL ?>assets/images/gedeh.jpg" alt="Grand Gedeh" style="object-fit:cover;">
                    <div class="gallery-overlay">
                        <h6 class="mb-0">Grand Gedeh County</h6>
                    </div>
                </div>
            </a>
            <a href="<?= APP_URL ?>pages/gallery.php?cat=river-gee" class="col-6 col-lg-4 text-decoration-none">
                <div class="gallery-item">
                    <img src="<?= APP_URL ?>assets/images/gee.jpg" alt="River Gee" style="object-fit:cover;">
                    <div class="gallery-overlay">
                        <h6 class="mb-0">River Gee County</h6>
                    </div>
                </div>
            </a>
            <a href="<?= APP_URL ?>pages/gallery.php?cat=lofa-county" class="col-6 col-lg-4 text-decoration-none">
                <div class="gallery-item">
                    <img src="<?= APP_URL ?>assets/images/lofa.jpg" alt="Lofa County" style="object-fit:cover;">
                    <div class="gallery-overlay">
                        <h6 class="mb-0">Lofa County Team</h6>
                    </div>
                </div>
            </a>
            <a href="<?= APP_URL ?>pages/gallery.php?cat=grand-bassa" class="col-6 col-lg-4 text-decoration-none">
                <div class="gallery-item">
                    <img src="<?= APP_URL ?>assets/images/bassa.jpg" alt="Grand Bassa" style="object-fit:cover;">
                    <div class="gallery-overlay">
                        <h6 class="mb-0">Grand Bassa County</h6>
                    </div>
                </div>
            </a>
            <a href="<?= APP_URL ?>pages/gallery.php?cat=margibi-county" class="col-6 col-lg-4 text-decoration-none">
                <div class="gallery-item">
                    <img src="<?= APP_URL ?>assets/images/margibi.jpg" alt="Margibi County" style="object-fit:cover;">
                    <div class="gallery-overlay">
                        <h6 class="mb-0">Margibi County Team</h6>
                    </div>
                </div>
            </a>
            <a href="<?= APP_URL ?>pages/gallery.php?cat=grand-kru" class="col-6 col-lg-4 text-decoration-none">
                <div class="gallery-item">
                    <img src="<?= APP_URL ?>assets/images/kru.jpg" alt="Grand Kru" style="object-fit:cover;">
                    <div class="gallery-overlay">
                        <h6 class="mb-0">Grand Kru County</h6>
                    </div>
                </div>
            </a>
        </div>
    </div>
</section>

<!-- County Flags Section -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="overline">Our Counties</span>
            <h2>15 Counties Represented</h2>
            <p>Every county in Liberia proudly represented through their flags and team colors.</p>
        </div>
        <div class="row g-3 justify-content-center">
            <?php
            $allCounties = [
                ['name' => 'Bomi', 'file' => 'Bomi.png'],
                ['name' => 'Bong', 'file' => 'Bong.png'],
                ['name' => 'Gbarpolu', 'file' => 'Gbarpolu.png'],
                ['name' => 'Grand Bassa', 'file' => 'Grand Bassa.png'],
                ['name' => 'Grand Cape Mount', 'file' => 'Grand Cape Mount.png'],
                ['name' => 'Grand Gedeh', 'file' => 'Grand Gedeh.png'],
                ['name' => 'Grand Kru', 'file' => 'Grand Kru.png'],
                ['name' => 'Lofa', 'file' => 'Lofa.png'],
                ['name' => 'Margibi', 'file' => 'Margibi.png'],
                ['name' => 'Maryland', 'file' => 'Maryland.png'],
                ['name' => 'Montserrado', 'file' => 'Montserrado.png'],
                ['name' => 'Nimba', 'file' => 'Nimba.png'],
                ['name' => 'River Cess', 'file' => 'Rivercess.jpg'],
                ['name' => 'River Gee', 'file' => 'River Gee.png'],
                ['name' => 'Sinoe', 'file' => 'Sinoe.png'],
            ];
            foreach ($allCounties as $c):
            ?>
            <div class="col-4 col-sm-3 col-lg-2">
                <a href="<?= APP_URL ?>pages/county_history.php?county=<?= urlencode($c['name']) ?>" class="text-decoration-none" style="display:block;">
                    <div class="text-center p-2 p-md-3 bg-white rounded-3 shadow-sm border" style="transition:transform 0.2s,box-shadow 0.2s;cursor:pointer;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
                        <img src="<?= APP_URL ?>assets/images/<?= $c['file'] ?>" alt="<?= $c['name'] ?>" style="width:60px;height:42px;object-fit:contain;border-radius:4px;margin-bottom:0.4rem;">
                        <div class="fw-semibold" style="font-size:0.7rem;"><?= $c['name'] ?></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../templates/public_footer.php'; ?>
