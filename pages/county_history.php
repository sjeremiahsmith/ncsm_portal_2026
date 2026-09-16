<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$countyName = $_GET['county'] ?? '';
if (empty($countyName)) {
    header('Location: ' . APP_URL . 'pages/media.php');
    exit;
}

$countyName = ucfirst(htmlspecialchars_decode(urldecode($countyName)));

$countiesList = [
    'Bomi', 'Bong', 'Gbarpolu', 'Grand Bassa', 'Grand Cape Mount',
    'Grand Gedeh', 'Grand Kru', 'Lofa', 'Margibi', 'Maryland',
    'Montserrado', 'Nimba', 'River Cess', 'River Gee', 'Sinoe'
];

if (!in_array($countyName, $countiesList)) {
    header('Location: ' . APP_URL . 'pages/media.php');
    exit;
}

$flagFile = $countyName . '.png';
if ($countyName === 'River Cess') $flagFile = 'Rivercess.jpg';

$countyData = [
    'Bomi' => [
        'capital' => 'Buchanan City',
        'group' => 'D',
        'host' => false,
        'motto' => 'The Land of Diverse Resources',
        'description' => 'Bomi County, though one of Liberia\'s smaller counties, has consistently shown passion and commitment in the National County Sports Meet. Located on the western coast, Bomi has produced talented footballers and kickball players who have represented the county with distinction.',
        'highlights' => [
            'Consistent participant in all editions of the NCSM since the 2004 revival',
            'Competes in Group D alongside Margibi and Grand Kru',
            'Known for producing technically skilled footballers',
            'Active participation in all four sports disciplines',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Bong' => [
        'capital' => 'Gbarnga',
        'group' => 'B',
        'host' => true,
        'motto' => 'Land of Iron Ore',
        'description' => 'Bong County, centrally located in Liberia, is one of the most passionate counties in the National County Sports Meet. With its capital Gbarnga, the county has a rich sporting tradition and has produced numerous athletes who have gone on to represent Liberia internationally. As a host county in Group B for the 2026 season, Bong has been instrumental in promoting sports development.',
        'highlights' => [
            'Host county for Group B in the 2026 NCSM season',
            'Has produced multiple national team players in football',
            'Strong tradition in kickball and athletics',
            'Gbarnga serves as a major hub for sporting activities in central Liberia',
            'Known for passionate fan support at national tournaments',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Gbarpolu' => [
        'capital' => 'Bopolu',
        'group' => 'A',
        'host' => false,
        'motto' => 'Land of the Pioneers',
        'description' => 'Gbarpolu County, one of Liberia\'s newest counties created in 2001, has quickly established itself as a competitive force in the National County Sports Meet. Despite being a relatively young county, its passion for sports runs deep among its people.',
        'highlights' => [
            'Competes in Group A alongside Nimba, Grand Gedeh, and River Gee',
            'Rapid improvement in NCSM performance since first participation',
            'Active in all four sports disciplines',
            'Producing emerging talent in football and athletics',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Grand Bassa' => [
        'capital' => 'Buchanan',
        'group' => 'C',
        'host' => false,
        'motto' => 'The Gateway to the Central Republic',
        'description' => 'Grand Bassa County, home to the second-largest city Buchanan, has a storied history in the National County Sports Meet. The county has been one of the traditional powerhouses, particularly in football, and has produced many players who have gone on to play professionally and represent Liberia internationally.',
        'highlights' => [
            'Competes in Group C alongside Lofa, Montserrado, and Sinoe',
            'Multiple strong showings in the NCSM knockout stages',
            'Rich tradition in football dating back to the pre-war era',
            'Produces technically gifted players known for their ball control',
            'Buchanan has hosted several regional sporting events',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Grand Cape Mount' => [
        'capital' => 'Robertsport',
        'group' => 'B',
        'host' => false,
        'motto' => 'The Land of Small Huts',
        'description' => 'Grand Cape Mount County, bordering Sierra Leone, brings a unique flavor to the National County Sports Meet. The county has surprised many with competitive performances and continues to develop its sporting infrastructure.',
        'highlights' => [
            'Competes in Group B alongside Bong, Maryland, and River Cess',
            'Surprised many with competitive displays in the 2024/2025 season',
            'Growing investment in youth sports development',
            'Robertsport is known as a surfing and beach sports destination',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Grand Gedeh' => [
        'capital' => 'Zwedru',
        'group' => 'A',
        'host' => true,
        'motto' => 'The Gateway to the Southeast',
        'description' => 'Grand Gedeh County, located in southeastern Liberia, has been a consistent performer in the National County Sports Meet. As a host county for Group A in 2026, the county has invested significantly in sports infrastructure and has a passionate fan base.',
        'highlights' => [
            'Host county for Group A in the 2026 NCSM season',
            'Has hosted several memorable group stage matches',
            'Strong football tradition with several knockout stage appearances',
            'Produces athletic players known for their speed and endurance',
            'Active supporter of all four sports disciplines',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Grand Kru' => [
        'capital' => 'Barclayville',
        'group' => 'D',
        'host' => true,
        'motto' => 'The Land of the Purest Hand',
        'description' => 'Grand Kru County, situated along the southeastern coast of Liberia, may be small in size but is big in passion for the National County Sports Meet. As a host county for Group D in 2026, Grand Kru has shown the nation that great sports can come from any corner of the country.',
        'highlights' => [
            'Host county for Group D in the 2026 NCSM season',
            'Has showcased the passion of southern county fans',
            'Growing reputation in kickball and athletics',
            'Committed to developing youth sports infrastructure',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Lofa' => [
        'capital' => 'Voinjama',
        'group' => 'C',
        'host' => true,
        'motto' => 'The Land of Rice',
        'description' => 'Lofa County, Liberia\'s northernmost county bordering Guinea and Sierra Leone, is one of the most formidable counties in the National County Sports Meet. With a rich sporting tradition and a large population base, Lofa has consistently been among the top-performing counties.',
        'highlights' => [
            'Host county for Group C in the 2026 NCSM season',
            'Multiple championship contention appearances',
            'Known for producing physically dominant footballers',
            'Strong tradition in all four sports disciplines',
            'Has hosted group matches that drew massive crowds',
            'Historically one of the most successful counties in NCSM history',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Margibi' => [
        'capital' => 'Kakata',
        'group' => 'D',
        'host' => false,
        'motto' => 'The Gateway to the West',
        'description' => 'Margibi County, strategically located between Montserrado and the interior, is home to the Roberts International Airport and is a hub of economic and sporting activity. The county has been a consistent competitor in the NCSM.',
        'highlights' => [
            'Competes in Group D alongside Grand Kru and Bomi',
            'Benefits from proximity to Monrovia for training and preparation',
            'Produces well-rounded athletes across multiple disciplines',
            'Known for organized county sports administration',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Maryland' => [
        'capital' => 'Harper',
        'group' => 'B',
        'host' => false,
        'motto' => 'The Land of the Masters',
        'description' => 'Maryland County, located in the far southeastern corner of Liberia and bordering Côte d\'Ivoire, brings unique sporting traditions to the National County Sports Meet. The county has a growing reputation in multiple sports.',
        'highlights' => [
            'Competes in Group B alongside Grand Cape Mount, Bong, and River Cess',
            'Cross-border sporting influences from Côte d\'Ivoire',
            'Growing participation in basketball and athletics',
            'Harper serves as a center for sports in the southeast',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Montserrado' => [
        'capital' => 'Monrovia',
        'group' => 'C',
        'host' => false,
        'motto' => 'The Land of Liberty',
        'description' => 'Montserrado County, home to the capital city Monrovia and the majority of Liberia\'s population, is the most successful county in the history of the National County Sports Meet. With access to the best facilities, largest talent pool, and most resources, Montserrado has set the standard for county sports.',
        'highlights' => [
            'Most successful county in NCSM history with multiple championships',
            'Home to the Samuel Kanyon Doe Sports Complex, the main NCSM venue',
            'Largest talent pool with the highest number of registered players',
            'Intense rivalry with Nimba County is the highlight of every NCSM',
            'Produces players who regularly go on to professional careers',
            'Competes in Group C alongside Grand Bassa, Lofa, and Sinoe',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Nimba' => [
        'capital' => 'Sanniquellie',
        'group' => 'A',
        'host' => false,
        'motto' => 'The Land of Iron',
        'description' => 'Nimba County, the second most populous county in Liberia, is Montserrado\'s greatest rival in the National County Sports Meet. With a passionate fan base, rich sporting talent, and a fierce competitive spirit, Nimba has been a dominant force in the tournament.',
        'highlights' => [
            'Champions of the 2024/2025 NCSM season',
            'Montserrado\'s fiercest rival — their matchups are the most anticipated events',
            'Rich tradition of producing elite footballers and athletes',
            'Competes in Group A alongside Grand Gedeh, River Gee, and Gbarpolu',
            'Known for incredible fan support that travels across the country',
            'One of the most decorated counties in NCSM history',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'River Cess' => [
        'capital' => 'Cestos City',
        'group' => 'B',
        'host' => false,
        'motto' => 'Land of the Purest Hand',
        'description' => 'River Cess County, situated along Liberia\'s central coast, has been steadily growing its presence in the National County Sports Meet. The county is committed to developing sports at the grassroots level.',
        'highlights' => [
            'Competes in Group B alongside Grand Cape Mount, Bong, and Maryland',
            'Steady improvement in NCSM performances over recent editions',
            'Growing investment in youth sports programs',
            'Active participation across all four sports disciplines',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'River Gee' => [
        'capital' => 'Fish Town',
        'group' => 'A',
        'host' => false,
        'motto' => 'The Land of Natural Resources',
        'description' => 'River Gee County, located in southeastern Liberia, has been making its mark in the National County Sports Meet. Despite its smaller size, the county brings determination and passion to every competition.',
        'highlights' => [
            'Competes in Group A alongside Nimba, Grand Gedeh, and Gbarpolu',
            'Has shown improvement in recent NCSM editions',
            'Producing emerging talent in football and kickball',
            'Committed to sports development at the county level',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
    'Sinoe' => [
        'capital' => 'Greenville',
        'group' => 'C',
        'host' => false,
        'motto' => 'Land of the Purest Hand',
        'description' => 'Sinoe County, one of the original counties of Liberia, has a long history in the National County Sports Meet. The county continues to develop its sporting capabilities and contribute to the rich tapestry of the tournament.',
        'highlights' => [
            'Competes in Group C alongside Grand Bassa, Lofa, and Montserrado',
            'One of the original counties of Liberia with a long sporting tradition',
            'Growing participation in kickball and athletics',
            'Greenville serves as the sporting hub for the county',
        ],
        'sports' => 'Football, Kickball, Basketball, Athletics',
    ],
];

$county = $countyData[$countyName] ?? null;

$pageTitle = $countyName . ' County - History';
include __DIR__ . '/../templates/public_header.php';
?>

<!-- Hero -->
<section class="media-hero county-hero" style="min-height:320px;">
    <div class="hero-bg" style="background:url('<?= APP_URL ?>assets/images/<?= $flagFile ?>') center center no-repeat;background-size:cover;background-color:rgba(0,40,80,0.95);animation:none;opacity:0.4;"></div>
    <div class="container text-center" style="position:relative;z-index:2;">
        <span class="hero-badge"><?= $county['group'] ?? '' ?> County</span>
        <h1><?= $countyName ?> County</h1>
        <p><?= $county['motto'] ?? '' ?></p>
    </div>
</section>

<!-- Quick Nav -->
<section class="py-3 bg-white border-bottom">
    <div class="container">
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="#overview" class="btn btn-sm btn-outline-primary">Overview</a>
            <a href="#ncsm" class="btn btn-sm btn-outline-danger">NCSM History</a>
            <a href="#highlights" class="btn btn-sm" style="background:#C8A032;color:#fff;">Highlights</a>
            <a href="#sports" class="btn btn-sm btn-outline-success">Sports</a>
        </div>
    </div>
</section>

<!-- County Info -->
<section class="section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Overview -->
                <div id="overview" class="mb-5">
                    <div class="d-flex align-items-center mb-3">
                        <span class="year-badge me-3" style="font-size:0.9rem;background:#0d6efd;">Overview</span>
                        <h2 class="mb-0 fw-bold">About <?= $countyName ?> County</h2>
                    </div>
                    <div class="ps-4" style="border-left:3px solid #0d6efd;">
                        <p style="color:#6c757d;line-height:1.9;font-size:1.05rem;">
                            <?= $county['description'] ?? '' ?>
                        </p>
                    </div>
                    <?php if ($county): ?>
                    <div class="row g-3 mt-3">
                        <div class="col-md-4">
                            <div class="p-3 rounded bg-light text-center">
                                <small class="text-muted d-block">Capital</small>
                                <strong><?= $county['capital'] ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded bg-light text-center">
                                <small class="text-muted d-block">Group</small>
                                <strong>Group <?= $county['group'] ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded bg-light text-center">
                                <small class="text-muted d-block">Host 2026</small>
                                <strong><?= $county['host'] ? 'Yes' : 'No' ?></strong>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- NCSM History -->
                <div id="ncsm" class="mb-5">
                    <div class="d-flex align-items-center mb-3">
                        <span class="year-badge me-3" style="font-size:0.9rem;background:#dc3545;">NCSM</span>
                        <h2 class="mb-0 fw-bold"><?= $countyName ?> in the NCSM</h2>
                    </div>
                    <div class="ps-4" style="border-left:3px solid #dc3545;">
                        <p style="color:#6c757d;line-height:1.9;font-size:1.05rem;">
                            <?= $countyName ?> has been a participant in the National County Sports Meet since its inception. The county has competed across all eras — from the informal pre-war competitions, through the post-conflict revival in 2004, to the modern digital era of the tournament.
                        </p>
                        <p style="color:#6c757d;line-height:1.9;font-size:1.05rem;">
                            <?php if ($county['host']): ?>
                                As a <strong>host county for Group <?= $county['group'] ?> in 2026</strong>, <?= $countyName ?> plays a pivotal role in organizing and hosting group stage matches, welcoming teams and fans from across the region.
                            <?php else: ?>
                                Competing in <strong>Group <?= $county['group'] ?></strong>, <?= $countyName ?> faces tough competition from its group rivals in every edition of the tournament.
                            <?php endif; ?>
                        </p>
                        <p style="color:#6c757d;line-height:1.9;font-size:1.05rem;">
                            The county's journey in the NCSM reflects its resilience, passion for sports, and commitment to youth development through athletics.
                        </p>
                    </div>
                </div>

                <!-- Highlights -->
                <div id="highlights" class="mb-5">
                    <div class="d-flex align-items-center mb-3">
                        <span class="year-badge me-3" style="font-size:0.9rem;background:#C8A032;">Highlights</span>
                        <h2 class="mb-0 fw-bold">Key Highlights</h2>
                    </div>
                    <div class="ps-4" style="border-left:3px solid #C8A032;">
                        <?php if (!empty($county['highlights'])): ?>
                        <ul style="color:#6c757d;line-height:2;font-size:1.05rem;">
                            <?php foreach ($county['highlights'] as $h): ?>
                            <li><?= $h ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sports -->
                <div id="sports" class="mb-5">
                    <div class="d-flex align-items-center mb-3">
                        <span class="year-badge me-3" style="font-size:0.9rem;background:#1B5E20;">Sports</span>
                        <h2 class="mb-0 fw-bold">Sports Disciplines</h2>
                    </div>
                    <div class="ps-4" style="border-left:3px solid #1B5E20;">
                        <p style="color:#6c757d;line-height:1.9;font-size:1.05rem;">
                            <?= $countyName ?> competes in the following sports disciplines at the National County Sports Meet:
                        </p>
                        <div class="row g-3 mt-2">
                            <?php
                            $sportIcons = [
                                'Football' => ['icon' => 'bi-circle-fill', 'color' => 'success', 'desc' => 'The flagship sport with massive fan following across the county.'],
                                'Kickball' => ['icon' => 'bi-circle-fill', 'color' => 'danger', 'desc' => 'A beloved sport especially popular among female athletes in the county.'],
                                'Basketball' => ['icon' => 'bi-circle-fill', 'color' => 'warning', 'desc' => 'Growing in popularity with competitive teams representing the county.'],
                                'Athletics' => ['icon' => 'bi-circle-fill', 'color' => 'primary', 'desc' => 'Track and field events showcasing speed and endurance.'],
                            ];
                            $sports = explode(', ', $county['sports'] ?? '');
                            foreach ($sports as $s):
                                $info = $sportIcons[$s] ?? ['icon' => 'bi-circle-fill', 'color' => 'secondary', 'desc' => ''];
                            ?>
                            <div class="col-md-6">
                                <div class="p-3 rounded bg-<?= $info['color'] ?> bg-opacity-10">
                                    <h6 class="text-<?= $info['color'] ?> fw-bold"><i class="bi <?= $info['icon'] ?> me-2" style="font-size:0.5rem;"></i><?= $s ?></h6>
                                    <small class="text-muted"><?= $info['desc'] ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- Back CTA -->
<section class="cta-section">
    <div class="container">
        <h2>Explore More Counties</h2>
        <p>Go back to the Media Center to explore all 15 counties represented in the National County Sports Meet.</p>
        <a href="<?= APP_URL ?>pages/media.php" class="btn btn-light btn-lg">
            <i class="bi bi-arrow-left me-2"></i>Back to Media Center
        </a>
    </div>
</section>

<?php include __DIR__ . '/../templates/public_footer.php'; ?>
