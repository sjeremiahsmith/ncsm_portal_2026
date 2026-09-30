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

    WHERE m.status IN ('live', 'scheduled', 'completed')
    ORDER BY CASE
        WHEN m.status = 'live' THEN 1
        WHEN m.status = 'scheduled' THEN 2
        WHEN m.status = 'completed' THEN 3
        ELSE 4
    END,
    CASE WHEN m.status = 'completed' THEN m.match_date END DESC,
    CASE WHEN m.status <> 'completed' THEN m.match_date END ASC
");

$reports = [];
foreach ($liveMatches as $m) {
    $r = $db->fetchOne("SELECT * FROM match_reports WHERE match_id = ?", [$m['id']]);
    $reports[$m['id']] = $r;
}

function computeMatchPhasePHP($m) {
    $firstHalf = 45 * 60;
    $halftime = 15 * 60;
    $secondHalfStart = $firstHalf + $halftime;
    $matchEnd = 90 * 60;

    if ($m['status'] === 'scheduled') {
        return ['phase' => 'pre', 'display' => '--:--', 'periodLabel' => 'Scheduled'];
    }

    if ($m['status'] === 'completed') {
        return ['phase' => 'fulltime', 'display' => 'FT', 'periodLabel' => 'Full Time'];
    }

    $kickoff = $m['timer_kickoff'] ?? null;
    $offset = (int)($m['timer_offset'] ?? 0);

    if (!$kickoff) {
        $paused = $offset;
        $displaySeconds = $paused;
        $label = '1st Half (Paused)';
        if ($paused >= $firstHalf && $paused < $secondHalfStart) {
            $displaySeconds = $paused - $firstHalf;
            $label = 'Half Time (Paused)';
        } elseif ($paused >= $secondHalfStart) {
            $displaySeconds = $paused - $halftime;
            $label = $displaySeconds > $matchEnd ? 'Added Time (Paused)' : '2nd Half (Paused)';
        }
        $mins = floor($displaySeconds / 60);
        $secs = $displaySeconds % 60;
        return ['phase' => 'paused', 'display' => str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT), 'periodLabel' => $label];
    }

    $diff = time() - strtotime($kickoff) + $offset;
    if ($diff <= $firstHalf) {
        $mins = floor($diff / 60);
        $secs = $diff % 60;
        return ['phase' => '1st', 'display' => str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT), 'periodLabel' => '1st Half'];
    }

    if ($diff < $secondHalfStart) {
        $elapsed = $diff - $firstHalf;
        $mins = floor($elapsed / 60);
        $secs = $elapsed % 60;
        return ['phase' => 'halftime', 'display' => str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT), 'periodLabel' => 'Half Time'];
    }

    $matchClock = $diff - $halftime;
    $mins = floor($matchClock / 60);
    $secs = $matchClock % 60;
    return [
        'phase' => $matchClock > $matchEnd ? 'added' : '2nd',
        'display' => str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT),
        'periodLabel' => $matchClock > $matchEnd ? 'Added Time' : '2nd Half'
    ];
}

function getTimerClassPHP($phase) {
    if ($phase === 'halftime') return 'match-timer halftime';
    if ($phase === 'paused') return 'match-timer paused';
    if ($phase === 'pre') return 'match-timer pre-match';
    return 'match-timer';
}

function getPeriodBadgeClassPHP($phase) {
    if ($phase === '1st') return 'period-badge period-1st';
    if ($phase === 'halftime' || $phase === 'paused') return 'period-badge period-halftime';
    if ($phase === '2nd') return 'period-badge period-2nd';
    if ($phase === 'added') return 'period-badge period-added';
    return 'period-badge period-fulltime';
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
.status-scheduled { background: #6c757d; color: #fff; font-size: 0.65rem; padding: 2px 8px; border-radius: 10px; }
.status-completed { background: #198754; color: #fff; font-size: 0.65rem; padding: 2px 8px; border-radius: 10px; }
.match-timer { display:inline-block; min-width:84px; padding:6px 10px; border-radius:999px; background:#dc3545; color:#fff; font-weight:700; letter-spacing:0.04em; font-variant-numeric:tabular-nums; }
.match-timer.halftime, .match-timer.paused { background:#ffc107; color:#212529; }
.match-timer.pre-match { background:#e9ecef; color:#6c757d; }
.period-badge { display:inline-block; margin-top:6px; padding:2px 8px; border-radius:999px; font-size:0.7rem; font-weight:600; }
.period-1st { background:#f8d7da; color:#842029; }
.period-halftime { background:#fff3cd; color:#664d03; }
.period-2nd { background:#cfe2ff; color:#084298; }
.period-added { background:#ffe5d0; color:#9a3412; }
.period-fulltime { background:#d1e7dd; color:#0f5132; }
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
            $timer = computeMatchPhasePHP($m);
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
            <a href="<?= APP_URL ?>pages/public_match_stats.php?id=<?= (int)$m['id'] ?>" class="text-decoration-none">
            <div class="card match-card shadow-sm h-100">
                <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                    <small class="opacity-75"><?= sanitize($m['sport_name']) ?> &middot; <?= sanitize($m['group_label']) ?> &middot; <?= sanitize($m['round']) ?></small>
                    <?php if ($isLive): ?>
                    <span class="status-live"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>LIVE</span>
                    <?php elseif ($m['status'] === 'scheduled'): ?>
                    <span class="status-scheduled"><i class="bi bi-clock me-1"></i>UPCOMING</span>
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

                    <div class="text-center mb-2">
                        <?php if ($m['status'] === 'scheduled'): ?>
                        <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?= formatDate($m['match_date'], 'M d, h:i A') ?></small>
                        <?php else: ?>
                        <div class="<?= getTimerClassPHP($timer['phase']) ?>" id="timer-<?= (int)$m['id'] ?>"><?= $timer['display'] ?></div>
                        <div><span class="<?= getPeriodBadgeClassPHP($timer['phase']) ?>" id="period-<?= (int)$m['id'] ?>"><?= $timer['periodLabel'] ?></span></div>
                        <?php endif; ?>
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
const LIVE_MATCHES = <?= json_encode(array_map(function ($m) {
    return [
        'id' => (int)$m['id'],
        'status' => $m['status'],
        'timer_kickoff' => $m['timer_kickoff'],
        'timer_offset' => (int)($m['timer_offset'] ?? 0),
    ];
}, $liveMatches)) ?>;

const FIRST_HALF_MAX = 45 * 60;
const HALFTIME_MAX = 15 * 60;
const SECOND_HALF_START = FIRST_HALF_MAX + HALFTIME_MAX;
const MATCH_END = 90 * 60;

function computeMatchPhase(match) {
    if (match.status === 'scheduled') return { phase: 'pre', display: '--:--', periodLabel: 'Scheduled' };
    if (match.status === 'completed') return { phase: 'fulltime', display: 'FT', periodLabel: 'Full Time' };

    var kickoff = match.timer_kickoff;
    var offset = match.timer_offset || 0;

    if (!kickoff) {
        var paused = offset;
        var displaySeconds = paused;
        var label = '1st Half (Paused)';
        if (paused >= FIRST_HALF_MAX && paused < SECOND_HALF_START) {
            displaySeconds = paused - FIRST_HALF_MAX;
            label = 'Half Time (Paused)';
        } else if (paused >= SECOND_HALF_START) {
            displaySeconds = paused - HALFTIME_MAX;
            label = displaySeconds > MATCH_END ? 'Added Time (Paused)' : '2nd Half (Paused)';
        }
        var pausedMins = Math.floor(displaySeconds / 60);
        var pausedSecs = displaySeconds % 60;
        return { phase: 'paused', display: String(pausedMins).padStart(2, '0') + ':' + String(pausedSecs).padStart(2, '0'), periodLabel: label };
    }

    var kickoffDate = new Date(kickoff.replace(' ', 'T'));
    var diffSec = Math.floor((new Date() - kickoffDate) / 1000) + offset;

    if (diffSec <= FIRST_HALF_MAX) {
        var firstMins = Math.floor(diffSec / 60);
        var firstSecs = diffSec % 60;
        return { phase: '1st', display: String(firstMins).padStart(2, '0') + ':' + String(firstSecs).padStart(2, '0'), periodLabel: '1st Half' };
    }

    if (diffSec < SECOND_HALF_START) {
        var halfElapsed = diffSec - FIRST_HALF_MAX;
        var halfMins = Math.floor(halfElapsed / 60);
        var halfSecs = halfElapsed % 60;
        return { phase: 'halftime', display: String(halfMins).padStart(2, '0') + ':' + String(halfSecs).padStart(2, '0'), periodLabel: 'Half Time' };
    }

    var matchClock = diffSec - HALFTIME_MAX;
    var secondMins = Math.floor(matchClock / 60);
    var secondSecs = matchClock % 60;
    return {
        phase: matchClock > MATCH_END ? 'added' : '2nd',
        display: String(secondMins).padStart(2, '0') + ':' + String(secondSecs).padStart(2, '0'),
        periodLabel: matchClock > MATCH_END ? 'Added Time' : '2nd Half'
    };
}

function getTimerClass(phase) {
    if (phase === 'halftime') return 'match-timer halftime';
    if (phase === 'paused') return 'match-timer paused';
    if (phase === 'pre') return 'match-timer pre-match';
    return 'match-timer';
}

function getPeriodBadgeClass(phase) {
    if (phase === '1st') return 'period-badge period-1st';
    if (phase === 'halftime' || phase === 'paused') return 'period-badge period-halftime';
    if (phase === 'added') return 'period-badge period-added';
    if (phase === '2nd') return 'period-badge period-2nd';
    return 'period-badge period-fulltime';
}

function refreshTimers() {
    LIVE_MATCHES.forEach(function(match) {
        if (match.status === 'scheduled') return;
        var timer = computeMatchPhase(match);
        var timerEl = document.getElementById('timer-' + match.id);
        var periodEl = document.getElementById('period-' + match.id);
        if (timerEl) {
            timerEl.textContent = timer.display;
            timerEl.className = getTimerClass(timer.phase);
        }
        if (periodEl) {
            periodEl.textContent = timer.periodLabel;
            periodEl.className = getPeriodBadgeClass(timer.phase);
        }
    });
}

refreshTimers();
setInterval(refreshTimers, 1000);
setInterval(function() {
    location.reload();
}, 30000);
</script>

<?php include __DIR__ . '/../templates/public_footer.php'; ?>
