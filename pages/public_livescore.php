<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();

$liveMatches = $db->fetchAll("
    SELECT m.*, s.name as sport_name, c1.name as home_name, c2.name as away_name,
           c1.id as home_county_id, c2.id as away_county_id
    FROM matches m
    JOIN sports_disciplines s ON m.sport_discipline_id = s.id
    JOIN counties c1 ON m.home_county_id = c1.id
    JOIN counties c2 ON m.away_county_id = c2.id
    WHERE m.status IN ('live', 'completed')
    ORDER BY FIELD(m.status, 'live', 'completed'), m.match_date DESC
");

$reports = [];
foreach ($liveMatches as $m) {
    $r = $db->fetchOne("SELECT * FROM match_reports WHERE match_id = ?", [$m['id']]);
    $reports[$m['id']] = $r;
}

$pageTitle = 'Live Scores';
include __DIR__ . '/../templates/public_header.php';
?>

<style>
.live-badge { animation: pulse 1.5s infinite; }
@keyframes pulse { 0% { opacity:1; } 50% { opacity:0.4; } 100% { opacity:1; } }
.score-display { font-size: 2rem; font-weight: 700; line-height: 1; }
.match-card { border-radius: 12px; overflow: hidden; transition: transform 0.2s; cursor: pointer; }
.match-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.15) !important; }
.match-card .click-hint { font-size: 0.6rem; color: #6c757d; }
.modal-stat-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f0f0f0; }
.modal-stat-row:last-child { border-bottom:none; }
.modal-stat-label { font-size:0.75rem; color:#6c757d; text-align:center; flex:1; }
.modal-stat-val { font-size:0.85rem; font-weight:700; width:40px; text-align:center; }
.status-live { background: #dc3545; color: #fff; font-size: 0.65rem; padding: 2px 8px; border-radius: 10px; animation: pulse 1.5s infinite; }
.status-completed { background: #198754; color: #fff; font-size: 0.65rem; padding: 2px 8px; border-radius: 10px; }
.team-name { font-size: 0.9rem; font-weight: 600; }
.goal-scorer { font-size: 0.72rem; padding: 1px 0; }
.card-event { font-size: 0.72rem; padding: 1px 0; }
.squad-label { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6c757d; font-weight: 700; }
.squad-player { font-size: 0.72rem; padding: 1px 0; }
.hero-section-sm { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); color: #fff; padding: 2rem 0 1.5rem; text-align: center; }
</style>

<div class="hero-section-sm">
    <div class="container">
        <h2 class="mb-1 fw-bold"><i class="bi bi-broadcast me-2"></i>Live Scores</h2>
        <p class="mb-0 small opacity-75">Follow matches in real-time</p>
    </div>
</div>

<div class="container py-4">
    <?php if (empty($liveMatches)): ?>
    <div class="text-center py-5">
        <i class="bi bi-calendar-x display-1 text-muted"></i>
        <h5 class="mt-3 text-muted">No matches available</h5>
        <p class="text-muted small">Check back later for live scores</p>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($liveMatches as $m):
            $report = $reports[$m['id']] ?? null;
            $cards = [];
            $squads = ['home' => ['starting' => [], 'substitute' => []], 'away' => ['starting' => [], 'substitute' => []]];
            if ($report) {
                $cards = $db->fetchAll("SELECT * FROM match_report_cards WHERE report_id = ?", [$report['id']]);
                $squadRows = $db->fetchAll("SELECT * FROM match_squad_players WHERE report_id = ? ORDER BY team, player_type, jersey_number", [$report['id']]);
                foreach ($squadRows as $s) {
                    $squads[$s['team']][$s['player_type']][] = ['jersey' => (int)$s['jersey_number'], 'name' => $s['player_name']];
                }
            }
            $goals = $db->fetchAll("SELECT * FROM match_goals WHERE match_id = ? ORDER BY minute, team", [$m['id']]);
            $isLive = $m['status'] === 'live';
        ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= APP_URL ?>pages/public_match_stats.php?=<?= $m['id'] ?>" class="text-decoration-none">
            <div class="card match-card shadow-sm h-100">
                <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                    <small class="opacity-75"><?= sanitize($m['sport_name']) ?> &middot; <?= sanitize($m['group_label']) ?> &middot; <?= sanitize($m['round']) ?></small>
                    <?php if ($isLive): ?>
                    <span class="status-live"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>LIVE</span>
                    <?php else: ?>
                    <span class="status-completed"><i class="bi bi-check-circle me-1"></i>FT</span>
                    <?php endif; ?>
                </div>
                <div class="card-body py-3">
                    <div class="row align-items-center text-center mb-2">
                        <div class="col-4">
                            <?php $homeFlag = getCountyFlagUrl($m['home_name']); ?>
                            <?php if ($homeFlag): ?><img src="<?= $homeFlag ?>" alt="" style="height:24px;width:24px;object-fit:contain;border-radius:50%;margin-right:4px;vertical-align:middle;"><?php endif; ?>
                            <span class="team-name text-danger"><?= sanitize($m['home_name']) ?></span>
                        </div>
                        <div class="col-4">
                            <span class="score-display"><?= $m['home_score'] !== null ? (int)$m['home_score'] : '-' ?></span>
                            <span class="mx-1">-</span>
                            <span class="score-display"><?= $m['away_score'] !== null ? (int)$m['away_score'] : '-' ?></span>
                        </div>
                        <div class="col-4">
                            <?php $awayFlag = getCountyFlagUrl($m['away_name']); ?>
                            <span class="team-name text-primary"><?= sanitize($m['away_name']) ?></span>
                            <?php if ($awayFlag): ?><img src="<?= $awayFlag ?>" alt="" style="height:24px;width:24px;object-fit:contain;border-radius:50%;margin-left:4px;vertical-align:middle;"><?php endif; ?>
                        </div>
                    </div>

                    <?php if ($report): ?>
                    <?php
                        $homeYellow = (int)$report['home_yellow_cards'];
                        $homeRed = (int)$report['home_red_cards'];
                        $awayYellow = (int)$report['away_yellow_cards'];
                        $awayRed = (int)$report['away_red_cards'];
                        $homeGoals = count(array_filter($goals, fn($g) => $g['team'] === 'home'));
                        $awayGoals = count(array_filter($goals, fn($g) => $g['team'] === 'away'));
                    ?>
                    <div class="stats-bar-section mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="fw-bold text-muted" style="font-size:0.65rem;">STATISTICS</small>
                        </div>
                        <div class="mb-1">
                            <div class="d-flex justify-content-between" style="font-size:0.7rem;"><span class="text-danger fw-bold"><?= $homeGoals ?></span><small class="text-muted">Goals</small><span class="text-primary fw-bold"><?= $awayGoals ?></span></div>
                            <div class="d-flex align-items-center gap-1">
                                <div class="flex-grow-1" style="height:4px;background:#eee;border-radius:2px;overflow:hidden;">
                                    <div style="height:100%;background:#dc3545;width:<?= $homeGoals + $awayGoals > 0 ? round($homeGoals / ($homeGoals + $awayGoals) * 100) : 50 ?>%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-1">
                            <div class="d-flex justify-content-between" style="font-size:0.7rem;"><span class="text-warning fw-bold"><?= $homeYellow ?></span><small class="text-muted">Yellow Cards</small><span class="text-warning fw-bold"><?= $awayYellow ?></span></div>
                            <div class="d-flex align-items-center gap-1">
                                <div class="flex-grow-1" style="height:4px;background:#eee;border-radius:2px;overflow:hidden;">
                                    <div style="height:100%;background:#ffc107;width:<?= $homeYellow + $awayYellow > 0 ? round($homeYellow / ($homeYellow + $awayYellow) * 100) : 50 ?>%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-1">
                            <div class="d-flex justify-content-between" style="font-size:0.7rem;"><span class="text-danger fw-bold"><?= $homeRed ?></span><small class="text-muted">Red Cards</small><span class="text-danger fw-bold"><?= $awayRed ?></span></div>
                            <div class="d-flex align-items-center gap-1">
                                <div class="flex-grow-1" style="height:4px;background:#eee;border-radius:2px;overflow:hidden;">
                                    <div style="height:100%;background:#dc3545;width:<?= $homeRed + $awayRed > 0 ? round($homeRed / ($homeRed + $awayRed) * 100) : 50 ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($goals)): ?>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-6">
                            <small class="squad-label"><?= sanitize($m['home_name']) ?></small>
                            <?php foreach ($goals as $g): if ($g['team'] !== 'home') continue; ?>
                            <div class="goal-scorer" style="color:#dc3545;">
                                <span style="font-weight:700;">⚽</span>
                                <?php if ($g['minute'] !== null): ?><span style="color:#6c757d;font-size:0.65rem;"><?= (int)$g['minute'] ?>'</span> <?php endif; ?>
                                <strong>#<?= (int)$g['jersey_number'] ?> <?= sanitize($g['player_name']) ?></strong>
                                <?php if ($g['goal_type'] === 'penalty'): ?><span style="font-size:0.55rem;background:#ffc107;color:#212529;padding:1px 3px;border-radius:2px;">PEN</span><?php endif; ?>
                                <?php if ($g['goal_type'] === 'own_goal'): ?><span style="font-size:0.55rem;background:#6c757d;color:#fff;padding:1px 3px;border-radius:2px;">OG</span><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-6">
                            <small class="squad-label"><?= sanitize($m['away_name']) ?></small>
                            <?php foreach ($goals as $g): if ($g['team'] !== 'away') continue; ?>
                            <div class="goal-scorer" style="color:#0d6efd;">
                                <span style="font-weight:700;">⚽</span>
                                <?php if ($g['minute'] !== null): ?><span style="color:#6c757d;font-size:0.65rem;"><?= (int)$g['minute'] ?>'</span> <?php endif; ?>
                                <strong>#<?= (int)$g['jersey_number'] ?> <?= sanitize($g['player_name']) ?></strong>
                                <?php if ($g['goal_type'] === 'penalty'): ?><span style="font-size:0.55rem;background:#ffc107;color:#212529;padding:1px 3px;border-radius:2px;">PEN</span><?php endif; ?>
                                <?php if ($g['goal_type'] === 'own_goal'): ?><span style="font-size:0.55rem;background:#6c757d;color:#fff;padding:1px 3px;border-radius:2px;">OG</span><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($cards)): ?>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-6">
                            <small class="squad-label">Cards</small>
                            <?php foreach ($cards as $c): if ($c['team'] !== 'home') continue; ?>
                            <div class="card-event">
                                <?php if ($c['card_type'] === 'yellow'): ?><span style="display:inline-block;width:8px;height:12px;background:#ffc107;border-radius:1px;vertical-align:middle;"></span>
                                <?php else: ?><span style="display:inline-block;width:8px;height:12px;background:#dc3545;border-radius:1px;vertical-align:middle;"></span><?php endif; ?>
                                #<?= (int)$c['jersey_number'] ?> <?= sanitize($c['player_name']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-6">
                            <small class="squad-label">Cards</small>
                            <?php foreach ($cards as $c): if ($c['team'] !== 'away') continue; ?>
                            <div class="card-event">
                                <?php if ($c['card_type'] === 'yellow'): ?><span style="display:inline-block;width:8px;height:12px;background:#ffc107;border-radius:1px;vertical-align:middle;"></span>
                                <?php else: ?><span style="display:inline-block;width:8px;height:12px;background:#dc3545;border-radius:1px;vertical-align:middle;"></span><?php endif; ?>
                                #<?= (int)$c['jersey_number'] ?> <?= sanitize($c['player_name']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($squads['home']['starting']) || !empty($squads['away']['starting'])): ?>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-6">
                            <small class="squad-label"><?= sanitize($m['home_name']) ?> XI</small>
                            <?php foreach ($squads['home']['starting'] as $p): ?>
                            <div class="squad-player"><span style="font-weight:600;color:#dc3545;">#<?= $p['jersey'] ?></span> <?= sanitize($p['name']) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-6">
                            <small class="squad-label"><?= sanitize($m['away_name']) ?> XI</small>
                            <?php foreach ($squads['away']['starting'] as $p): ?>
                            <div class="squad-player"><span style="font-weight:600;color:#0d6efd;">#<?= $p['jersey'] ?></span> <?= sanitize($p['name']) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
setInterval(function() {
    location.reload();
}, 30000);
</script>

<?php include __DIR__ . '/../templates/public_footer.php'; ?>
