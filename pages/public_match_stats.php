<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$matchId) {
    header('Location: ' . APP_URL . 'pages/public_livescore.php');
    exit;
}

$match = $db->fetchOne("
    SELECT m.*, s.name as sport_name, c1.name as home_name, c2.name as away_name
    FROM matches m
    JOIN sports_disciplines s ON m.sport_discipline_id = s.id
    JOIN counties c1 ON m.home_county_id = c1.id
    JOIN counties c2 ON m.away_county_id = c2.id
    WHERE m.id = ?
", [$matchId]);

if (!$match) {
    header('Location: ' . APP_URL . 'pages/public_livescore.php');
    exit;
}

$report = $db->fetchOne("SELECT * FROM match_reports WHERE match_id = ?", [$matchId]);
$cards = [];
$squads = ['home' => ['starting' => [], 'substitute' => []], 'away' => ['starting' => [], 'substitute' => []]];
if ($report) {
    $cards = $db->fetchAll("SELECT * FROM match_report_cards WHERE report_id = ?", [$report['id']]);
    $squadRows = $db->fetchAll("SELECT * FROM match_squad_players WHERE report_id = ? ORDER BY team, player_type, jersey_number", [$report['id']]);
    foreach ($squadRows as $s) {
        $squads[$s['team']][$s['player_type']][] = ['jersey' => (int)$s['jersey_number'], 'name' => $s['player_name']];
    }
}
$goals = $db->fetchAll("SELECT * FROM match_goals WHERE match_id = ? ORDER BY minute, team", [$matchId]);
$isLive = $match['status'] === 'live';

$homeYellow = $report ? (int)$report['home_yellow_cards'] : 0;
$homeRed = $report ? (int)$report['home_red_cards'] : 0;
$awayYellow = $report ? (int)$report['away_yellow_cards'] : 0;
$awayRed = $report ? (int)$report['away_red_cards'] : 0;
$homeGoals = count(array_filter($goals, fn($g) => $g['team'] === 'home'));
$awayGoals = count(array_filter($goals, fn($g) => $g['team'] === 'away'));

$pageTitle = sanitize($match['home_name']) . ' vs ' . sanitize($match['away_name']) . ' - Match Stats';
include __DIR__ . '/../templates/public_header.php';
?>

<style>
@keyframes pulse { 0%{opacity:1;} 50%{opacity:0.4;} 100%{opacity:1;} }
.status-live { background:#dc3545; color:#fff; font-size:0.7rem; padding:3px 10px; border-radius:10px; animation:pulse 1.5s infinite; }
.status-completed { background:#198754; color:#fff; font-size:0.7rem; padding:3px 10px; border-radius:10px; }
.stat-bar-home { background:#dc3545; height:100%; border-radius:3px; transition:width 0.5s; }
.stat-bar-away { background:#0d6efd; height:100%; border-radius:3px; transition:width 0.5s; }
.stat-row { display:flex; align-items:center; padding:8px 0; border-bottom:1px solid #f0f0f0; }
.stat-row:last-child { border-bottom:none; }
.stat-val { font-size:0.9rem; font-weight:700; width:40px; text-align:center; }
.stat-label { font-size:0.75rem; color:#6c757d; text-align:center; flex:1; }
.stat-bar-track { flex:2; height:8px; background:#eee; border-radius:4px; overflow:hidden; display:flex; }
.hero-section-sm { background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%); color:#fff; padding:2rem 0 1.5rem; }
.squad-card { border-radius:10px; border:1px solid #eee; }
.squad-card .card-header { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
</style>

<div class="hero-section-sm">
    <div class="container">
        <a href="<?= APP_URL ?>pages/public_livescore.php" class="text-white text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Back to Live Scores</a>
        <h3 class="mt-2 mb-0 fw-bold"><?= sanitize($match['home_name']) ?> vs <?= sanitize($match['away_name']) ?></h3>
        <small class="opacity-75"><?= sanitize($match['sport_name']) ?> &middot; <?= sanitize($match['group_label']) ?> &middot; <?= sanitize($match['round']) ?></small>
    </div>
</div>

<div class="container py-4">
    <!-- Score Banner -->
    <div class="card shadow-sm mb-4" style="border-radius:16px;overflow:hidden;">
        <div class="card-body py-4">
            <div class="row align-items-center text-center">
                <div class="col-4">
                    <?php $homeFlag = getCountyFlagUrl($match['home_name']); ?>
                    <?php if ($homeFlag): ?><img src="<?= $homeFlag ?>" alt="" style="height:32px;width:32px;object-fit:contain;border-radius:50%;display:block;margin:0 auto 6px;"><br><?php endif; ?>
                    <div class="fw-bold" style="font-size:1.1rem;color:#dc3545;"><?= sanitize($match['home_name']) ?></div>
                </div>
                <div class="col-4">
                    <span style="font-size:3rem;font-weight:800;line-height:1;"><?= $match['home_score'] !== null ? (int)$match['home_score'] : '-' ?></span>
                    <span class="mx-2" style="font-size:2rem;color:#aaa;">:</span>
                    <span style="font-size:3rem;font-weight:800;line-height:1;"><?= $match['away_score'] !== null ? (int)$match['away_score'] : '-' ?></span>
                </div>
                <div class="col-4">
                    <?php $awayFlag = getCountyFlagUrl($match['away_name']); ?>
                    <?php if ($awayFlag): ?><img src="<?= $awayFlag ?>" alt="" style="height:32px;width:32px;object-fit:contain;border-radius:50%;display:block;margin:0 auto 6px;"><br><?php endif; ?>
                    <div class="fw-bold" style="font-size:1.1rem;color:#0d6efd;"><?= sanitize($match['away_name']) ?></div>
                </div>
            </div>
            <div class="text-center mt-2">
                <?php if ($isLive): ?>
                <span class="status-live"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>LIVE</span>
                <?php else: ?>
                <span class="status-completed"><i class="bi bi-check-circle me-1"></i>Full Time</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($report): ?>
    <!-- Statistics -->
    <div class="card shadow-sm mb-4" style="border-radius:12px;">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart me-2"></i>Match Statistics</h6>
        </div>
        <div class="card-body py-2">
            <div class="stat-row">
                <span class="stat-val" style="color:#dc3545;"><?= $homeGoals ?></span>
                <span class="stat-label">Goals</span>
                <span class="stat-val" style="color:#0d6efd;"><?= $awayGoals ?></span>
            </div>
            <div class="d-flex px-3 mb-2">
                <div class="stat-bar-track">
                    <div class="stat-bar-home" style="width:<?= $homeGoals + $awayGoals > 0 ? round($homeGoals / ($homeGoals + $awayGoals) * 100) : 50 ?>%;"></div>
                    <div class="stat-bar-away" style="width:<?= $homeGoals + $awayGoals > 0 ? round($awayGoals / ($homeGoals + $awayGoals) * 100) : 50 ?>%;"></div>
                </div>
            </div>

            <div class="stat-row">
                <span class="stat-val text-warning"><?= $homeYellow ?></span>
                <span class="stat-label">Yellow Cards</span>
                <span class="stat-val text-warning"><?= $awayYellow ?></span>
            </div>
            <div class="d-flex px-3 mb-2">
                <div class="stat-bar-track">
                    <div class="stat-bar-home" style="background:#ffc107;width:<?= $homeYellow + $awayYellow > 0 ? round($homeYellow / ($homeYellow + $awayYellow) * 100) : 50 ?>%;"></div>
                    <div class="stat-bar-away" style="background:#ffc107;width:<?= $homeYellow + $awayYellow > 0 ? round($awayYellow / ($homeYellow + $awayYellow) * 100) : 50 ?>%;"></div>
                </div>
            </div>

            <div class="stat-row">
                <span class="stat-val" style="color:#dc3545;"><?= $homeRed ?></span>
                <span class="stat-label">Red Cards</span>
                <span class="stat-val" style="color:#dc3545;"><?= $awayRed ?></span>
            </div>
            <div class="d-flex px-3 mb-2">
                <div class="stat-bar-track">
                    <div class="stat-bar-home" style="background:#dc3545;width:<?= $homeRed + $awayRed > 0 ? round($homeRed / ($homeRed + $awayRed) * 100) : 50 ?>%;"></div>
                    <div class="stat-bar-away" style="background:#dc3545;width:<?= $homeRed + $awayRed > 0 ? round($awayRed / ($homeRed + $awayRed) * 100) : 50 ?>%;"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Goals -->
        <?php if (!empty($goals)): ?>
        <div class="col-md-6">
            <div class="card shadow-sm h-100" style="border-radius:12px;">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0 fw-bold" style="font-size:0.85rem;"><i class="bi bi-circle me-1" style="color:#198754;"></i>Goal Scorers</h6>
                </div>
                <div class="card-body py-2">
                    <div class="row">
                        <div class="col-6">
                            <small class="fw-bold d-block mb-1" style="font-size:0.65rem;color:#dc3545;text-transform:uppercase;"><?= sanitize($match['home_name']) ?></small>
                            <?php foreach ($goals as $g): if ($g['team'] !== 'home') continue; ?>
                            <div class="mb-1" style="font-size:0.82rem;color:#dc3545;">
                                <span style="font-weight:700;">⚽</span>
                                <?php if ($g['minute'] !== null): ?><span style="color:#6c757d;font-size:0.7rem;"><?= (int)$g['minute'] ?>'</span> <?php endif; ?>
                                <strong>#<?= (int)$g['jersey_number'] ?> <?= sanitize($g['player_name']) ?></strong>
                                <?php if ($g['goal_type'] === 'penalty'): ?><span style="font-size:0.55rem;background:#ffc107;color:#212529;padding:1px 4px;border-radius:3px;">PEN</span><?php endif; ?>
                                <?php if ($g['goal_type'] === 'own_goal'): ?><span style="font-size:0.55rem;background:#6c757d;color:#fff;padding:1px 4px;border-radius:3px;">OG</span><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-6">
                            <small class="fw-bold d-block mb-1" style="font-size:0.65rem;color:#0d6efd;text-transform:uppercase;"><?= sanitize($match['away_name']) ?></small>
                            <?php foreach ($goals as $g): if ($g['team'] !== 'away') continue; ?>
                            <div class="mb-1" style="font-size:0.82rem;color:#0d6efd;">
                                <span style="font-weight:700;">⚽</span>
                                <?php if ($g['minute'] !== null): ?><span style="color:#6c757d;font-size:0.7rem;"><?= (int)$g['minute'] ?>'</span> <?php endif; ?>
                                <strong>#<?= (int)$g['jersey_number'] ?> <?= sanitize($g['player_name']) ?></strong>
                                <?php if ($g['goal_type'] === 'penalty'): ?><span style="font-size:0.55rem;background:#ffc107;color:#212529;padding:1px 4px;border-radius:3px;">PEN</span><?php endif; ?>
                                <?php if ($g['goal_type'] === 'own_goal'): ?><span style="font-size:0.55rem;background:#6c757d;color:#fff;padding:1px 4px;border-radius:3px;">OG</span><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cards -->
        <?php if (!empty($cards)): ?>
        <div class="col-md-6">
            <div class="card shadow-sm h-100" style="border-radius:12px;">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0 fw-bold" style="font-size:0.85rem;"><i class="bi bi-exclamation-triangle me-1 text-warning"></i>Card Events</h6>
                </div>
                <div class="card-body py-2">
                    <div class="row">
                        <div class="col-6">
                            <small class="fw-bold d-block mb-1" style="font-size:0.65rem;color:#dc3545;text-transform:uppercase;"><?= sanitize($match['home_name']) ?></small>
                            <?php foreach ($cards as $c): if ($c['team'] !== 'home') continue; ?>
                            <div class="mb-1" style="font-size:0.82rem;">
                                <?php if ($c['card_type'] === 'yellow'): ?><span style="display:inline-block;width:10px;height:14px;background:#ffc107;border-radius:2px;vertical-align:middle;"></span>
                                <?php else: ?><span style="display:inline-block;width:10px;height:14px;background:#dc3545;border-radius:2px;vertical-align:middle;"></span><?php endif; ?>
                                #<?= (int)$c['jersey_number'] ?> <?= sanitize($c['player_name']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-6">
                            <small class="fw-bold d-block mb-1" style="font-size:0.65rem;color:#0d6efd;text-transform:uppercase;"><?= sanitize($match['away_name']) ?></small>
                            <?php foreach ($cards as $c): if ($c['team'] !== 'away') continue; ?>
                            <div class="mb-1" style="font-size:0.82rem;">
                                <?php if ($c['card_type'] === 'yellow'): ?><span style="display:inline-block;width:10px;height:14px;background:#ffc107;border-radius:2px;vertical-align:middle;"></span>
                                <?php else: ?><span style="display:inline-block;width:10px;height:14px;background:#dc3545;border-radius:2px;vertical-align:middle;"></span><?php endif; ?>
                                #<?= (int)$c['jersey_number'] ?> <?= sanitize($c['player_name']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lineups -->
        <?php if (!empty($squads['home']['starting']) || !empty($squads['away']['starting'])): ?>
        <div class="col-12">
            <div class="card shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0 fw-bold" style="font-size:0.85rem;"><i class="bi bi-people me-1"></i>Lineups</h6>
                </div>
                <div class="card-body py-2">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="squad-card">
                                <div class="card-header py-2" style="background:#dc3545;color:#fff;"><?= sanitize($match['home_name']) ?> Starting XI</div>
                                <div class="card-body py-2">
                                    <?php foreach ($squads['home']['starting'] as $p): ?>
                                    <div style="font-size:0.82rem;" class="mb-1"><span style="font-weight:700;color:#dc3545;">#<?= $p['jersey'] ?></span> <?= sanitize($p['name']) ?></div>
                                    <?php endforeach; ?>
                                    <?php if (!empty($squads['home']['substitute'])): ?>
                                    <hr class="my-2">
                                    <small class="text-muted fw-bold" style="font-size:0.6rem;">SUBSTITUTES</small>
                                    <?php foreach ($squads['home']['substitute'] as $p): ?>
                                    <div style="font-size:0.75rem;color:#888;" class="mb-1"><span style="font-weight:600;color:#dc3545;">#<?= $p['jersey'] ?></span> <?= sanitize($p['name']) ?></div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="squad-card">
                                <div class="card-header py-2" style="background:#0d6efd;color:#fff;"><?= sanitize($match['away_name']) ?> Starting XI</div>
                                <div class="card-body py-2">
                                    <?php foreach ($squads['away']['starting'] as $p): ?>
                                    <div style="font-size:0.82rem;" class="mb-1"><span style="font-weight:700;color:#0d6efd;">#<?= $p['jersey'] ?></span> <?= sanitize($p['name']) ?></div>
                                    <?php endforeach; ?>
                                    <?php if (!empty($squads['away']['substitute'])): ?>
                                    <hr class="my-2">
                                    <small class="text-muted fw-bold" style="font-size:0.6rem;">SUBSTITUTES</small>
                                    <?php foreach ($squads['away']['substitute'] as $p): ?>
                                    <div style="font-size:0.75rem;color:#888;" class="mb-1"><span style="font-weight:600;color:#0d6efd;">#<?= $p['jersey'] ?></span> <?= sanitize($p['name']) ?></div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($report && $report['notes']): ?>
    <div class="card shadow-sm mt-4" style="border-radius:12px;">
        <div class="card-body">
            <small class="fw-bold text-muted d-block mb-1" style="font-size:0.7rem;">MATCH NOTES</small>
            <div style="font-size:0.85rem;"><?= nl2br(sanitize($report['notes'])) ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/public_footer.php'; ?>
