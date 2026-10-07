<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = getDb();
ensureMatchExtraTimeColumns();

$sportFilterId = isAssociationApprovalRole() ? (int)$_SESSION['user_association_id'] : null;

$sports = $sportFilterId
    ? [$db->fetchOne("SELECT * FROM sports_disciplines WHERE id = ?", [$sportFilterId])]
    : getSports();

function getStandings($db, $sportId = null, $groupLabel = null) {
    $where = ["m.status = 'completed'"];
    $params = [];
    if ($sportId) { $where[] = "m.sport_discipline_id = ?"; $params[] = $sportId; }
    if ($groupLabel) { $where[] = "m.group_label = ?"; $params[] = $groupLabel; }
    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    $rows = $db->fetchAll(
        "SELECT m.home_county_id, m.away_county_id, m.home_score, m.away_score, m.group_label, m.sport_discipline_id,
                c1.name as home_name, c2.name as away_name
         FROM matches m
         JOIN counties c1 ON m.home_county_id = c1.id
         JOIN counties c2 ON m.away_county_id = c2.id
         $whereSql
         ORDER BY m.group_label, m.match_date",
        $params
    );

    $teams = [];
    $teamInfo = [];
    foreach ($rows as $r) {
        foreach ([
            ['id' => $r['home_county_id'], 'name' => $r['home_name'], 'score' => $r['home_score'], 'opp_score' => $r['away_score']],
            ['id' => $r['away_county_id'], 'name' => $r['away_name'], 'score' => $r['away_score'], 'opp_score' => $r['home_score']]
        ] as $t) {
            $tid = $t['id'];
            $teamInfo[$tid] = ['name' => $t['name'], 'group' => $r['group_label']];
            if (!isset($teams[$tid])) {
                $teams[$tid] = ['played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'gf' => 0, 'ga' => 0, 'gd' => 0, 'pts' => 0];
            }
            $teams[$tid]['played']++;
            $teams[$tid]['gf'] += (int)$t['score'];
            $teams[$tid]['ga'] += (int)$t['opp_score'];
            $teams[$tid]['gd'] = $teams[$tid]['gf'] - $teams[$tid]['ga'];
            if ((int)$t['score'] > (int)$t['opp_score']) { $teams[$tid]['wins']++; $teams[$tid]['pts'] += 3; }
            elseif ((int)$t['score'] === (int)$t['opp_score']) { $teams[$tid]['draws']++; $teams[$tid]['pts'] += 1; }
            else { $teams[$tid]['losses']++; }
        }
    }
    uksort($teams, function($a, $b) use ($teams) {
        if ($teams[$a]['pts'] !== $teams[$b]['pts']) return $teams[$b]['pts'] - $teams[$a]['pts'];
        if ($teams[$a]['gd'] !== $teams[$b]['gd']) return $teams[$b]['gd'] - $teams[$a]['gd'];
        return $teams[$b]['gf'] - $teams[$a]['gf'];
    });
    return [$teams, $teamInfo];
}

$liveFilter = $sportFilterId ? " AND m.sport_discipline_id = $sportFilterId" : "";
$liveMatches = $db->fetchAll("
    SELECT m.*, s.name as sport_name, c1.name as home_name, c2.name as away_name
    FROM matches m
    JOIN sports_disciplines s ON m.sport_discipline_id = s.id
    JOIN counties c1 ON m.home_county_id = c1.id
    JOIN counties c2 ON m.away_county_id = c2.id
    WHERE m.status IN ('live','scheduled') $liveFilter
    ORDER BY m.match_date ASC
    LIMIT 50
");

$completedMatches = $db->fetchAll("
    SELECT m.*, s.name as sport_name, c1.name as home_name, c2.name as away_name
    FROM matches m
    JOIN sports_disciplines s ON m.sport_discipline_id = s.id
    JOIN counties c1 ON m.home_county_id = c1.id
    JOIN counties c2 ON m.away_county_id = c2.id
    WHERE m.status = 'completed' $liveFilter
    ORDER BY m.updated_at DESC
    LIMIT 20
");

$standingsByGroup = [];
foreach ($sports as $sport) {
    $sid = $sport['id'];
    $sname = $sport['name'];
    $isKB = ($sid == 2);
    foreach (['A', 'B', 'C', 'D'] as $g) {
        list($teams, $teamInfo) = getStandings($db, $sid, $g);
        if (!empty($teams)) {
            $standingsByGroup[$sid][$g] = ['teams' => $teams, 'info' => $teamInfo, 'sport_name' => $sname, 'is_kickball' => $isKB];
        }
    }
}

$liveMatchData = [];
foreach ($liveMatches as $m) {
    $report = $db->fetchOne("SELECT * FROM match_reports WHERE match_id = ?", [$m['id']]);
    $cards = [];
    $squads = ['home' => ['starting' => [], 'substitute' => []], 'away' => ['starting' => [], 'substitute' => []]];
    if ($report) {
        $cards = $db->fetchAll("SELECT * FROM match_report_cards WHERE report_id = ?", [$report['id']]);
    }
    $squads = mergeCoachLineupsIntoSquads($m['id'], $m['home_county_id'], $m['away_county_id'], $squads);
    $substitutions = getCoachLineupSubstitutions($m['id']);
    $goals = $db->fetchAll("SELECT * FROM match_goals WHERE match_id = ? ORDER BY minute ASC, team ASC", [$m['id']]);
    $liveMatchData[] = [
        'id' => (int)$m['id'],
        'sport_name' => $m['sport_name'],
        'group_label' => $m['group_label'],
        'home_name' => $m['home_name'],
        'away_name' => $m['away_name'],
        'home_score' => $m['home_score'] !== null ? (int)$m['home_score'] : null,
        'away_score' => $m['away_score'] !== null ? (int)$m['away_score'] : null,
        'status' => $m['status'],
        'match_date' => $m['match_date'],
        'updated_at' => $m['updated_at'],
        'round' => $m['round'],
        'timer_kickoff' => $m['timer_kickoff'],
        'timer_offset' => (int)($m['timer_offset'] ?? 0),
        'extra_time_enabled' => !empty($m['extra_time_enabled']),
        'report' => $report ? [
            'home_yellow' => (int)$report['home_yellow_cards'],
            'home_red' => (int)$report['home_red_cards'],
            'away_yellow' => (int)$report['away_yellow_cards'],
            'away_red' => (int)$report['away_red_cards']
        ] : null,
        'cards' => array_map(function($c) {
            return ['team' => $c['team'], 'card_type' => $c['card_type'], 'jersey' => (int)$c['jersey_number'], 'name' => $c['player_name']];
        }, $cards),
        'goals' => array_map(function($g) {
            return ['team' => $g['team'], 'jersey' => (int)$g['jersey_number'], 'name' => $g['player_name'], 'minute' => $g['minute'] !== null ? (int)$g['minute'] : null, 'type' => $g['goal_type']];
        }, $goals),
        'substitutions' => $substitutions,
        'squads' => $squads
    ];
}

$pageTitle = 'Games & Live Scores';

function computeMatchPhasePHP($m) {
    $FIRST_HALF = 45 * 60;
    $HALFTIME = 15 * 60;
    $SECOND_HALF_START = $FIRST_HALF + $HALFTIME;
    $MATCH_END = 90 * 60;
    $EXTRA_FIRST_END = 105 * 60;
    $EXTRA_SECOND_END = 120 * 60;
    $extraTimeEnabled = !empty($m['extra_time_enabled']);
    if ($m['status'] === 'scheduled') return ['phase' => 'pre', 'display' => '--:--', 'periodLabel' => 'Scheduled'];
    if ($m['status'] === 'completed') return ['phase' => 'fulltime', 'display' => 'FT', 'periodLabel' => 'Full Time'];
    $kickoff = $m['timer_kickoff'] ?? null;
    $offset = (int)($m['timer_offset'] ?? 0);
    if (!$kickoff) {
        $paused = $offset;
        $displaySeconds = $paused;
        $label = '1st Half (Paused)';
        if ($paused >= $FIRST_HALF && $paused < $SECOND_HALF_START) {
            $displaySeconds = $paused - $FIRST_HALF;
            $label = 'Half Time (Paused)';
        } elseif ($paused >= $SECOND_HALF_START) {
            $displaySeconds = $paused - $HALFTIME;
            if ($extraTimeEnabled && $displaySeconds >= $MATCH_END && $displaySeconds <= $EXTRA_FIRST_END) {
                $label = 'Extra Time 1st Half (Paused)';
            } elseif ($extraTimeEnabled && $displaySeconds > $EXTRA_FIRST_END && $displaySeconds <= $EXTRA_SECOND_END) {
                $label = 'Extra Time 2nd Half (Paused)';
            } elseif ($extraTimeEnabled && $displaySeconds > $EXTRA_SECOND_END) {
                $label = 'Extra Time Added (Paused)';
            } else {
                $label = $displaySeconds > $MATCH_END ? 'Added Time (Paused)' : '2nd Half (Paused)';
            }
        }
        $mins = floor($displaySeconds / 60);
        $secs = $displaySeconds % 60;
        return ['phase' => 'paused', 'display' => str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT), 'periodLabel' => $label];
    }
    $diff = time() - strtotime($kickoff) + $offset;
    if ($diff <= $FIRST_HALF) {
        $mins = floor($diff / 60);
        $secs = $diff % 60;
        return ['phase' => '1st', 'display' => str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT), 'periodLabel' => '1st Half'];
    }
    if ($diff < $SECOND_HALF_START) {
        $ht = $diff - $FIRST_HALF;
        $mins = floor($ht / 60);
        $secs = $ht % 60;
        return ['phase' => 'halftime', 'display' => str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT), 'periodLabel' => 'Half Time'];
    }
    $matchClock = $diff - $HALFTIME;
    $mins = floor($matchClock / 60);
    $secs = $matchClock % 60;
    if ($extraTimeEnabled && $matchClock >= $MATCH_END && $matchClock <= $EXTRA_FIRST_END) {
        return [
            'phase' => 'et1',
            'display' => str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT),
            'periodLabel' => 'Extra Time 1st Half'
        ];
    }
    if ($extraTimeEnabled && $matchClock > $EXTRA_FIRST_END && $matchClock <= $EXTRA_SECOND_END) {
        return [
            'phase' => 'et2',
            'display' => str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT),
            'periodLabel' => 'Extra Time 2nd Half'
        ];
    }
    return [
        'phase' => $extraTimeEnabled && $matchClock > $EXTRA_SECOND_END ? 'et_added' : ($matchClock > $MATCH_END ? 'added' : '2nd'),
        'display' => str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT),
        'periodLabel' => $extraTimeEnabled && $matchClock > $EXTRA_SECOND_END ? 'Extra Time Added' : ($matchClock > $MATCH_END ? 'Added Time' : '2nd Half')
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
    if ($phase === 'halftime') return 'period-badge period-halftime';
    if ($phase === 'paused') return 'period-badge period-halftime';
    if ($phase === '2nd') return 'period-badge period-2nd';
    if ($phase === 'et1') return 'period-badge period-et1';
    if ($phase === 'et2') return 'period-badge period-et2';
    if ($phase === 'et_added') return 'period-badge period-et-added';
    if ($phase === 'added') return 'period-badge period-added';
    return 'period-badge period-fulltime';
}

function renderCardEventsPHP($cards) {
    if (empty($cards)) return '<span class="text-muted" style="font-size:0.7rem;">None</span>';
    $html = '';
    foreach ($cards as $c) {
        $cls = $c['card_type'] === 'yellow' ? 'card-event yellow' : 'card-event red';
        $icon = $c['card_type'] === 'yellow' ? '&#9899;' : '&#9898;';
        $html .= '<span class="' . $cls . '">' . $icon . ' #' . $c['jersey'] . ' ' . $c['name'] . '</span>';
    }
    return $html;
}

function renderSquadPHP($squad) {
    $formationRows = [
        ['Left Winger', 'Striker', 'Right Winger'],
        ['Left Central Midfielder', 'Defensive Midfielder', 'Right Central Midfielder'],
        ['Left Back', 'Left Centre-Back', 'Right Centre-Back', 'Right Back'],
        ['Goalkeeper'],
    ];
    $playersByPosition = [];
    foreach ($squad['starting'] ?? [] as $player) {
        if (!empty($player['position'])) {
            $playersByPosition[$player['position']] = $player;
        }
    }
    if (empty($playersByPosition)) {
        if (empty($squad['starting']) && empty($squad['substitute'])) {
            return '<span class="text-muted" style="font-size:0.7rem;">No squad data</span>';
        }
        $html = '<div class="squad-list"><strong class="d-block mb-1">' . htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8') . ' Lineup</strong>';
        foreach ($squad['starting'] ?? [] as $player) {
            $html .= '<div class="player-item">#' . (int)$player['jersey'] . ' ' . htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') . '</div>';
        }
        foreach ($squad['substitute'] ?? [] as $player) {
            $html .= '<div class="player-item">#' . (int)$player['jersey'] . ' ' . htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') . ' <small>(Sub)</small></div>';
        }
        return $html . '</div>';
    }
    $html = '<div class="live-formation-pitch">';
    foreach ($formationRows as $index => $row) {
        $rowClass = ['attack', 'midfield', 'defense', 'goalkeeper'][$index];
        $html .= '<div class="live-formation-row ' . $rowClass . '">';
        foreach ($row as $position) {
            $player = $playersByPosition[$position] ?? null;
            $html .= '<div class="live-formation-slot"><small>' . htmlspecialchars($position, ENT_QUOTES, 'UTF-8') . '</small>';
            $html .= $player
                ? '<strong>#' . (int)$player['jersey'] . ' ' . htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') . '</strong>'
                : '<span>No player</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    if (!empty($squad['substitute'])) {
        $html .= '<div class="live-formation-subs"><strong>SUBSTITUTES</strong><div>';
        foreach ($squad['substitute'] as $player) {
            $html .= '<span>#' . (int)$player['jersey'] . ' ' . htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $html .= '</div></div>';
    }
    return $html;
}

function renderGoalsPHP($goals, $team = null) {
    if (empty($goals)) return '';
    $filtered = $team ? array_filter($goals, fn($g) => $g['team'] === $team) : $goals;
    if (empty($filtered)) return '';
    $html = '';
    foreach ($filtered as $g) {
        $color = $g['team'] === 'home' ? '#dc3545' : '#0d6efd';
        $typeIcon = '';
        if ($g['type'] === 'penalty') $typeIcon = ' <span style="font-size:0.6rem;background:#ffc107;color:#212529;padding:1px 4px;border-radius:3px;">PEN</span>';
        if ($g['type'] === 'own_goal') $typeIcon = ' <span style="font-size:0.6rem;background:#6c757d;color:#fff;padding:1px 4px;border-radius:3px;">OG</span>';
        $min = $g['minute'] !== null ? $g['minute'] . '\'' : '';
        $html .= '<div style="font-size:0.75rem;padding:2px 0;color:' . $color . ';">';
        $html .= '<span style="font-weight:700;">⚽</span> ';
        if ($min) $html .= '<span style="color:#6c757d;font-size:0.65rem;">' . $min . '</span> ';
        $html .= '<strong>#' . $g['jersey'] . ' ' . htmlspecialchars($g['name']) . '</strong>';
        $html .= $typeIcon;
        $html .= '</div>';
    }
    return $html;
}
?>
<?php include __DIR__ . '/../../templates/header.php'; ?>

<style>
.score-display { font-size: 2.5rem; font-weight: 700; line-height: 1; }
.live-badge { animation: pulse 1.5s infinite; }
@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
.match-card { transition: box-shadow 0.2s; }
.match-card:hover { box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,0.08); }
.team-name-cell { max-width: 180px; }
.standing-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
.standing-table td { vertical-align: middle; }
.pos-1 { background: rgba(40,167,69,0.08); }
.pos-2 { background: rgba(0,123,255,0.05); }

.match-timer {
    font-family: 'Courier New', monospace;
    font-size: 1.8rem;
    font-weight: 700;
    color: #dc3545;
    background: #1a1a2e;
    color: #00ff88;
    padding: 6px 16px;
    border-radius: 6px;
    display: inline-block;
    letter-spacing: 2px;
    min-width: 90px;
    text-align: center;
}
.match-timer.halftime {
    color: #ffc107;
    animation: blink 1s infinite;
}
.match-timer.pre-match {
    color: #6c757d;
    background: #f8f9fa;
}
.match-timer.paused {
    color: #ffc107;
    background: #1a1a2e;
    animation: blink 1.5s infinite;
}
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

.period-badge {
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.period-1st { background: #28a745; color: #fff; }
.period-halftime { background: #ffc107; color: #212529; }
.period-2nd { background: #007bff; color: #fff; }
.period-et1 { background: #6610f2; color: #fff; }
.period-et2 { background: #6f42c1; color: #fff; }
.period-et-added { background: #b02a37; color: #fff; }
.period-added { background: #fd7e14; color: #fff; }
.period-fulltime { background: #6c757d; color: #fff; }

.stats-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    font-size: 0.8rem;
}
.stats-row .stat-label {
    color: #6c757d;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.stats-row .stat-value {
    font-weight: 700;
    font-size: 0.85rem;
}
.stats-bar {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
    margin: 2px 0;
}
.stats-bar-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.5s ease;
}
.stats-bar-fill.home { background: #007bff; }
.stats-bar-fill.away { background: #dc3545; }

.card-event {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
    margin: 1px;
    display: inline-block;
}
.card-event.yellow { background: #ffc107; color: #212529; }
.card-event.red { background: #dc3545; color: #fff; }

.squad-list {
    font-size: 0.7rem;
    line-height: 1.4;
}
.squad-list .player-item {
    padding: 1px 0;
}
.squad-list .jersey-num {
    display: inline-block;
    width: 18px;
    text-align: center;
    font-weight: 700;
    color: #495057;
}
.live-formation-pitch { display: grid; gap: 4px; padding: 6px; border: 2px solid #e4f2e7; border-radius: 5px; background: repeating-linear-gradient(0deg, #277d48, #277d48 28px, #2c8750 28px, #2c8750 56px); }
.live-formation-row { display: grid; gap: 4px; }
.live-formation-row.attack, .live-formation-row.midfield { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.live-formation-row.defense { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.live-formation-row.goalkeeper { grid-template-columns: minmax(0, 1fr); width: 30%; margin: 0 auto; }
.live-formation-slot { display: flex; min-width: 0; min-height: 45px; flex-direction: column; justify-content: center; align-items: center; padding: 3px 2px; border: 1px solid rgba(255,255,255,.55); border-radius: 4px; background: rgba(9,55,29,.48); color: #fff; text-align: center; overflow-wrap: anywhere; }
.live-formation-slot small { font-size: .48rem; font-weight: 700; }
.live-formation-slot strong, .live-formation-slot span { font-size: .58rem; }
.live-formation-subs { margin-top: 4px; font-size: .6rem; }
.live-formation-subs > strong { display: block; margin-bottom: 2px; }
.live-formation-subs > div { display: flex; flex-wrap: wrap; gap: 3px; }
.live-formation-subs span { padding: 2px 4px; border: 1px solid #dee2e6; border-radius: 3px; }
.live-substitution-events { padding: 4px 0; font-size: .65rem; }
.scorelineup-match-pitch { display: grid; padding: 8px; border: 2px solid #e6f4e9; border-radius: 6px; background: repeating-linear-gradient(0deg, #27804a, #27804a 34px, #2d8952 34px, #2d8952 68px); color: #fff; }
.scorelineup-half { padding: 5px 0; }
.scorelineup-half h6 { margin: 0 0 4px; font-size: .7rem; font-weight: 700; text-align: center; }
.scorelineup-half.away { border-bottom: 1px solid rgba(255,255,255,.75); }
.scorelineup-half.home { border-top: 1px solid rgba(255,255,255,.75); }
.scorelineup-row { display: grid; gap: 4px; margin: 4px 0; }
.scorelineup-row.attack, .scorelineup-row.midfield { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.scorelineup-row.defense { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.scorelineup-row.goalkeeper { grid-template-columns: minmax(0, 1fr); width: 26%; margin-right: auto; margin-left: auto; }
.scorelineup-slot { display: flex; min-width: 0; min-height: 42px; flex-direction: column; justify-content: center; align-items: center; padding: 3px; border: 1px solid rgba(255,255,255,.55); border-radius: 4px; background: rgba(10,55,29,.48); text-align: center; overflow-wrap: anywhere; }
.scorelineup-position { font-size: .5rem; font-weight: 700; }
.scorelineup-slot strong, .scorelineup-empty { font-size: .58rem; }
.scorelineup-halfway { padding: 2px 0; border-top: 1px dashed rgba(255,255,255,.7); border-bottom: 1px dashed rgba(255,255,255,.7); text-align: center; font-size: .48rem; font-weight: 700; }
.scorelineup-legacy { display: flex; flex-wrap: wrap; justify-content: center; gap: 3px; }
.scorelineup-legacy small, .scorelineup-subs small { padding: 2px 4px; border: 1px solid rgba(255,255,255,.45); border-radius: 3px; font-size: .56rem; }
.scorelineup-subs { margin-top: 4px; text-align: center; font-size: .58rem; }
.scorelineup-subs > span { display: block; margin-bottom: 2px; font-weight: 700; }
.scorelineup-subs > div { display: flex; flex-wrap: wrap; justify-content: center; gap: 3px; }
.score-substitution-events { margin-top: 6px; padding: 5px; border: 1px solid #dee2e6; border-radius: 4px; background: #fff; }
.score-substitution-events h6 { margin-bottom: 3px; font-size: .65rem; font-weight: 700; }
.score-substitution-event { padding: 2px 0; font-size: .62rem; }
</style>

<script>
const LIVE_MATCHES = <?= json_encode($liveMatchData) ?>;
const API_URL = '<?= APP_URL ?>pages/games/api_live.php';
const REFRESH_INTERVAL = 10000;

// Match timer: 1st half 45min, halftime 15min, 2nd half starts at 45 and can continue into added time
const FIRST_HALF_MAX = 45 * 60;
const HALFTIME_MAX = 15 * 60;
const SECOND_HALF_START = FIRST_HALF_MAX + HALFTIME_MAX;
const MATCH_END = 90 * 60;
const EXTRA_FIRST_END = 105 * 60;
const EXTRA_SECOND_END = 120 * 60;

function computeMatchPhase(match) {
    if (match.status === 'scheduled') return { phase: 'pre', elapsed: 0, display: '--:--', periodLabel: 'Scheduled' };
    if (match.status === 'completed') return { phase: 'fulltime', elapsed: 0, display: 'FT', periodLabel: 'Full Time' };

    // Live match - use server timer state
    var kickoff = match.timer_kickoff;
    var offset = match.timer_offset || 0;
    var extraTimeEnabled = !!match.extra_time_enabled;
    var now = new Date();

    // Timer is paused (kickoff is null but status is live)
    if (!kickoff) {
        var paused = offset;
        var displaySeconds = paused;
        var label = '1st Half (Paused)';
        if (paused >= FIRST_HALF_MAX && paused < SECOND_HALF_START) {
            displaySeconds = paused - FIRST_HALF_MAX;
            label = 'Half Time (Paused)';
        } else if (paused >= SECOND_HALF_START) {
            displaySeconds = paused - HALFTIME_MAX;
            if (extraTimeEnabled && displaySeconds >= MATCH_END && displaySeconds <= EXTRA_FIRST_END) {
                label = 'Extra Time 1st Half (Paused)';
            } else if (extraTimeEnabled && displaySeconds > EXTRA_FIRST_END && displaySeconds <= EXTRA_SECOND_END) {
                label = 'Extra Time 2nd Half (Paused)';
            } else if (extraTimeEnabled && displaySeconds > EXTRA_SECOND_END) {
                label = 'Extra Time Added (Paused)';
            } else {
                label = displaySeconds > MATCH_END ? 'Added Time (Paused)' : '2nd Half (Paused)';
            }
        }
        var pausedMins = Math.floor(displaySeconds / 60);
        var pausedSecs = displaySeconds % 60;
        return { phase: 'paused', elapsed: paused, display: String(pausedMins).padStart(2,'0') + ':' + String(pausedSecs).padStart(2,'0'), periodLabel: label };
    }

    // Timer is running (kickoff is set)
    var kickoffDate = new Date(kickoff.replace(' ', 'T'));
    var diffSec = Math.floor((now - kickoffDate) / 1000) + offset;

    if (diffSec <= FIRST_HALF_MAX) {
        const mins = Math.floor(diffSec / 60);
        const secs = diffSec % 60;
        return { phase: '1st', elapsed: diffSec, display: String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0'), periodLabel: '1st Half' };
    }

    if (diffSec < SECOND_HALF_START) {
        const htElapsed = diffSec - FIRST_HALF_MAX;
        const mins = Math.floor(htElapsed / 60);
        const secs = htElapsed % 60;
        return { phase: 'halftime', elapsed: diffSec, display: String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0'), periodLabel: 'Half Time' };
    }

    const matchClock = diffSec - HALFTIME_MAX;
    const mins = Math.floor(matchClock / 60);
    const secs = matchClock % 60;
    if (extraTimeEnabled && matchClock >= MATCH_END && matchClock <= EXTRA_FIRST_END) {
        return {
            phase: 'et1',
            elapsed: diffSec,
            display: String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0'),
            periodLabel: 'Extra Time 1st Half'
        };
    }
    if (extraTimeEnabled && matchClock > EXTRA_FIRST_END && matchClock <= EXTRA_SECOND_END) {
        return {
            phase: 'et2',
            elapsed: diffSec,
            display: String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0'),
            periodLabel: 'Extra Time 2nd Half'
        };
    }
    return {
        phase: extraTimeEnabled && matchClock > EXTRA_SECOND_END ? 'et_added' : (matchClock > MATCH_END ? 'added' : '2nd'),
        elapsed: diffSec,
        display: String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0'),
        periodLabel: extraTimeEnabled && matchClock > EXTRA_SECOND_END ? 'Extra Time Added' : (matchClock > MATCH_END ? 'Added Time' : '2nd Half')
    };
}

function getTimerClass(phase) {
    if (phase === 'halftime') return 'match-timer halftime';
    if (phase === 'paused') return 'match-timer halftime';
    if (phase === 'pre') return 'match-timer pre-match';
    return 'match-timer';
}

function getPeriodBadgeClass(phase) {
    if (phase === '1st') return 'period-badge period-1st';
    if (phase === 'halftime') return 'period-badge period-halftime';
    if (phase === 'et1') return 'period-badge period-et1';
    if (phase === 'et2') return 'period-badge period-et2';
    if (phase === 'et_added') return 'period-badge period-et-added';
    if (phase === 'added') return 'period-badge period-added';
    if (phase === '2nd') return 'period-badge period-2nd';
    return 'period-badge period-fulltime';
}

function renderCardEvents(cards) {
    if (!cards || cards.length === 0) return '<span class="text-muted" style="font-size:0.7rem;">None</span>';
    let html = '';
    cards.forEach(function(c) {
        const cls = c.card_type === 'yellow' ? 'card-event yellow' : 'card-event red';
        const icon = c.card_type === 'yellow' ? '&#9899;' : '&#9898;';
        html += '<span class="' + cls + '">' + icon + ' #' + c.jersey + ' ' + c.name + '</span>';
    });
    return html;
}

function escapeLiveHtml(value) {
    return String(value).replace(/[&<>"']/g, function(character) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
    });
}

function renderSubstitutionEvents(substitutions) {
    if (!substitutions || substitutions.length === 0) return '';
    let html = '<div class="mt-2"><small class="text-muted d-block mb-1" style="font-size:0.65rem;">SUBSTITUTIONS</small>';
    substitutions.forEach(function(substitution) {
        html += '<div class="small mb-1">' + escapeLiveHtml(substitution.county_name) + ': ';
        html += '<span class="text-danger"><i class="bi bi-arrow-down-circle-fill"></i> #' + substitution.outgoing_jersey_number + ' ' + escapeLiveHtml(substitution.outgoing_player_name) + '</span> ';
        html += '<i class="bi bi-arrow-right text-muted mx-1"></i> ';
        html += '<span class="text-success"><i class="bi bi-arrow-up-circle-fill"></i> #' + substitution.incoming_jersey_number + ' ' + escapeLiveHtml(substitution.incoming_player_name) + '</span>';
        if (substitution.position) html += ' <small class="text-muted">(' + escapeLiveHtml(substitution.position) + ')</small>';
        html += '</div>';
    });
    return html + '</div>';
}

function renderGoals(goals, team) {
    if (!goals || goals.length === 0) return '';
    var filtered = team ? goals.filter(function(g) { return g.team === team; }) : goals;
    if (filtered.length === 0) return '';
    let html = '';
    filtered.forEach(function(g) {
        const color = g.team === 'home' ? '#dc3545' : '#0d6efd';
        let typeIcon = '';
        if (g.type === 'penalty') typeIcon = ' <span style="font-size:0.6rem;background:#ffc107;color:#212529;padding:1px 4px;border-radius:3px;">PEN</span>';
        if (g.type === 'own_goal') typeIcon = ' <span style="font-size:0.6rem;background:#6c757d;color:#fff;padding:1px 4px;border-radius:3px;">OG</span>';
        const min = g.minute !== null ? g.minute + '\'' : '';
        html += '<div style="font-size:0.75rem;padding:2px 0;color:' + color + ';">';
        html += '<span style="font-weight:700;">⚽</span> ';
        if (min) html += '<span style="color:#6c757d;font-size:0.65rem;">' + min + '</span> ';
        html += '<strong>#' + g.jersey + ' ' + g.name + '</strong>';
        html += typeIcon;
        html += '</div>';
    });
    return html;
}

function renderSquad(squad, teamName) {
    if (!squad) return '<span class="text-muted" style="font-size:0.7rem;">No squad data</span>';
    var formationRows = [
        [['Left Winger', 'Left Winger'], ['Striker', 'Striker'], ['Right Winger', 'Right Winger']],
        [['Left Central Midfielder', 'Left Central Midfielder'], ['Defensive Midfielder', 'Defensive Midfielder'], ['Right Central Midfielder', 'Right Central Midfielder']],
        [['Left Back', 'Left Back'], ['Left Centre-Back', 'Left Centre-Back'], ['Right Centre-Back', 'Right Centre-Back'], ['Right Back', 'Right Back']],
        [['Goalkeeper', 'Goalkeeper']]
    ];
    var positionedPlayers = (squad.starting || []).filter(function(player) { return !!player.position; });
    var html = '';
    if (positionedPlayers.length > 0) {
        var playersByPosition = {};
        positionedPlayers.forEach(function(player) { playersByPosition[player.position] = player; });
        html += '<section class="live-formation"><strong class="d-block mb-1">' + escapeLiveHtml(teamName) + ' Starting XI</strong><div class="live-formation-pitch">';
        formationRows.forEach(function(row, rowIndex) {
            html += '<div class="live-formation-row ' + ['attack', 'midfield', 'defense', 'goalkeeper'][rowIndex] + '">';
            row.forEach(function(position) {
                var player = playersByPosition[position[0]];
                html += '<div class="live-formation-slot"><small>' + escapeLiveHtml(position[1]) + '</small>';
                html += player ? '<strong>#' + player.jersey + ' ' + escapeLiveHtml(player.name) + '</strong>' : '<span>No player</span>';
                html += '</div>';
            });
            html += '</div>';
        });
        html += '</div></section>';
    } else if (squad.starting && squad.starting.length > 0) {
        html += '<div class="squad-list"><strong class="d-block mb-1">' + escapeLiveHtml(teamName) + ' Starting XI</strong>';
        squad.starting.forEach(function(player) {
            html += '<div class="player-item">#' + player.jersey + ' ' + escapeLiveHtml(player.name) + '</div>';
        });
        html += '</div>';
    }
    if (squad.substitute && squad.substitute.length > 0) {
        html += '<div class="live-formation-subs"><strong>SUBSTITUTES</strong><div>';
        squad.substitute.forEach(function(p) {
            html += '<span>#' + p.jersey + ' ' + escapeLiveHtml(p.name) + '</span>';
        });
        html += '</div></div>';
    }
    return html;
}

function renderCoachMatchDiagram(homeSquad, homeName, awaySquad, awayName) {
    var attack = ['Left Winger', 'Striker', 'Right Winger'];
    var midfield = ['Left Central Midfielder', 'Defensive Midfielder', 'Right Central Midfielder'];
    var defense = ['Left Back', 'Left Centre-Back', 'Right Centre-Back', 'Right Back'];
    var goalkeeper = ['Goalkeeper'];

    function renderHalf(squad, name, side, rows) {
        var playersByPosition = {};
        (squad.starting || []).forEach(function(player) {
            if (player.position) playersByPosition[player.position] = player;
        });
        var html = '<section class="scorelineup-half ' + side + '"><h6>' + escapeLiveHtml(name) + '</h6>';
        if (Object.keys(playersByPosition).length === 0) {
            html += '<div class="scorelineup-legacy">';
            (squad.starting || []).forEach(function(player) {
                html += '<small>#' + player.jersey + ' ' + escapeLiveHtml(player.name) + '</small>';
            });
            html += '</div>';
        } else {
            rows.forEach(function(row) {
                html += '<div class="scorelineup-row ' + row.kind + '">';
                row.positions.forEach(function(position) {
                    var player = playersByPosition[position];
                    html += '<div class="scorelineup-slot"><span class="scorelineup-position">' + escapeLiveHtml(position) + '</span>';
                    html += player ? '<strong>#' + player.jersey + ' ' + escapeLiveHtml(player.name) + '</strong>' : '<span class="scorelineup-empty">No player</span>';
                    html += '</div>';
                });
                html += '</div>';
            });
        }
        if (squad.substitute && squad.substitute.length > 0) {
            html += '<div class="scorelineup-subs"><span>Substitutes</span><div>';
            squad.substitute.forEach(function(player) {
                html += '<small>#' + player.jersey + ' ' + escapeLiveHtml(player.name) + '</small>';
            });
            html += '</div></div>';
        }
        return html + '</section>';
    }

    return '<div class="scorelineup-match-pitch">'
        + renderHalf(awaySquad, awayName, 'away', [
            {kind: 'goalkeeper', positions: goalkeeper},
            {kind: 'defense', positions: defense},
            {kind: 'midfield', positions: midfield},
            {kind: 'attack', positions: attack}
        ])
        + '<div class="scorelineup-halfway">HALFWAY</div>'
        + renderHalf(homeSquad, homeName, 'home', [
            {kind: 'attack', positions: attack},
            {kind: 'midfield', positions: midfield},
            {kind: 'defense', positions: defense},
            {kind: 'goalkeeper', positions: goalkeeper}
        ])
        + '</div>';
}

function renderLiveMatch(match) {
    const timer = computeMatchPhase(match);
    const report = match.report || {};
    const homeYellow = report.home_yellow || 0;
    const homeRed = report.home_red || 0;
    const awayYellow = report.away_yellow || 0;
    const awayRed = report.away_red || 0;
    const homeScore = match.home_score !== null ? match.home_score : '-';
    const awayScore = match.away_score !== null ? match.away_score : '-';
    const isLive = match.status === 'live';

    let yellowBarPct = 0, redBarPct = 0;
    const totalYellow = homeYellow + awayYellow;
    const totalRed = homeRed + awayRed;
    if (totalYellow > 0) { yellowBarPct = (homeYellow / totalYellow) * 100; }
    if (totalRed > 0) { redBarPct = (homeRed / totalRed) * 100; }

    let html = '<div class="col-lg-6 col-xl-4 mb-3">';
    html += '<div class="card match-card border-' + (isLive ? 'danger' : 'secondary') + '">';
    html += '<div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">';
    html += '<small class="text-muted">' + match.sport_name + ' &middot; Group ' + match.group_label + '</small>';
    if (isLive) {
        html += '<span class="badge bg-danger live-badge"><i class="bi bi-broadcast me-1"></i>LIVE</span>';
    } else if (match.status === 'scheduled') {
        html += '<small class="text-muted">' + match.round + '</small>';
    } else {
        html += '<span class="badge bg-success">FT</span>';
    }
    html += '</div>';
    html += '<div class="card-body py-2">';

    // Timer
    if (isLive || match.status === 'completed') {
        html += '<div class="text-center mb-2">';
        html += '<div class="' + getTimerClass(timer.phase) + '" id="timer-' + match.id + '">' + timer.display + '</div><br>';
        html += '<span class="' + getPeriodBadgeClass(timer.phase) + '" id="period-' + match.id + '">' + timer.periodLabel + '</span>';
        html += '</div>';
    }

    // Score
    html += '<div class="row align-items-center mb-2">';
    html += '<div class="col-4 text-truncate"><strong>' + match.home_name + '</strong></div>';
    html += '<div class="col-4 text-center"><span class="score-display">' + homeScore + '</span><span class="mx-1 text-muted" style="font-size:1.5rem;">-</span><span class="score-display">' + awayScore + '</span></div>';
    html += '<div class="col-4 text-truncate text-end"><strong>' + match.away_name + '</strong></div>';
    html += '</div>';

    // Stats section (only for live matches with report)
    const hasSquads = match.squads && (
        (match.squads.home && (match.squads.home.starting.length > 0 || match.squads.home.substitute.length > 0)) ||
        (match.squads.away && (match.squads.away.starting.length > 0 || match.squads.away.substitute.length > 0))
    );
    if (isLive && (match.report || hasSquads || (match.substitutions && match.substitutions.length > 0))) {
        html += '<hr class="my-1" style="border-color:#e9ecef;">';

        // Yellow Cards
        html += '<div class="stats-row"><span class="stat-label">Yellow Cards</span></div>';
        html += '<div class="stats-row"><span class="stat-value text-warning">' + homeYellow + '</span>';
        html += '<div class="stats-bar flex-grow-1 mx-2"><div class="stats-bar-fill home" style="width:' + (totalYellow > 0 ? yellowBarPct : 50) + '%;"></div></div>';
        html += '<span class="stat-value text-warning">' + awayYellow + '</span></div>';

        // Red Cards
        html += '<div class="stats-row"><span class="stat-label">Red Cards</span></div>';
        html += '<div class="stats-row"><span class="stat-value text-danger">' + homeRed + '</span>';
        html += '<div class="stats-bar flex-grow-1 mx-2"><div class="stats-bar-fill away" style="width:' + (totalRed > 0 ? redBarPct : 50) + '%;"></div></div>';
        html += '<span class="stat-value text-danger">' + awayRed + '</span></div>';

        // Card Events
        if (match.cards && match.cards.length > 0) {
            html += '<div class="mt-1"><small class="text-muted d-block mb-1" style="font-size:0.65rem;">CARD EVENTS</small>';
            html += renderCardEvents(match.cards);
            html += '</div>';
        }

        // Squads with Goals under each team
        html += '<hr class="my-1" style="border-color:#e9ecef;">';
        html += '<div class="row"><div class="col-6"><small class="text-muted d-block mb-1" style="font-size:0.65rem;">' + escapeLiveHtml(match.home_name) + '</small>';
        if (match.goals && match.goals.length > 0) { html += '<div class="mb-1">' + renderGoals(match.goals, 'home') + '</div>'; }
        html += '</div><div class="col-6"><small class="text-muted d-block mb-1" style="font-size:0.65rem;">' + escapeLiveHtml(match.away_name) + '</small>';
        if (match.goals && match.goals.length > 0) { html += '<div class="mb-1">' + renderGoals(match.goals, 'away') + '</div>'; }
        html += '</div></div>';
        html += renderCoachMatchDiagram(match.squads.home, match.home_name, match.squads.away, match.away_name);
        html += renderSubstitutionEvents(match.substitutions);
    }

    html += '</div></div></div>';
    return html;
}

function refreshLiveMatches() {
    fetch(API_URL)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var container = document.getElementById('live-matches-container');
            if (!container) return;
            if (!data.matches || data.matches.length === 0) {
                container.innerHTML = '<div class="col-12"><div class="alert alert-info text-center"><i class="bi bi-info-circle me-1"></i>No live or upcoming matches at this time.</div></div>';
                return;
            }
            var html = '';
            data.matches.forEach(function(m) { html += renderLiveMatch(m); });
            container.innerHTML = html;
        })
        .catch(function(e) { console.log('Live update error:', e); });
}

function tickTimers() {
    LIVE_MATCHES.forEach(function(match) {
        if (match.status !== 'live') return;
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

document.addEventListener('DOMContentLoaded', function() {
    setInterval(tickTimers, 1000);
    setInterval(refreshLiveMatches, REFRESH_INTERVAL);
});
</script>

<div class="row mb-3">
    <div class="col">
        <h4 class="mb-0"><i class="bi bi-broadcast me-2"></i>Live Scores</h4>
    </div>
    <div class="col text-end">
        <a href="<?= APP_URL ?>pages/games/standings.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-trophy me-1"></i>Standings</a>
        <?php if (canManageGames()): ?>
        <a href="<?= APP_URL ?>pages/games/manage.php" class="btn btn-primary btn-sm"><i class="bi bi-gear me-1"></i>Manage Games</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4" id="live-matches-container">
<?php if (!empty($liveMatchData)): ?>
<?php foreach ($liveMatchData as $m):
    $timer = computeMatchPhasePHP($m);
    $isLive = $m['status'] === 'live';
    $report = $m['report'] ?: [];
    $homeYellow = $report['home_yellow'] ?? 0;
    $homeRed = $report['home_red'] ?? 0;
    $awayYellow = $report['away_yellow'] ?? 0;
    $awayRed = $report['away_red'] ?? 0;
    $totalYellow = $homeYellow + $awayYellow;
    $totalRed = $homeRed + $awayRed;
    $homeScore = $m['home_score'] !== null ? $m['home_score'] : '-';
    $awayScore = $m['away_score'] !== null ? $m['away_score'] : '-';
?>
<div class="col-lg-6 col-xl-4 mb-3">
    <div class="card match-card border-<?= $isLive ? 'danger' : 'secondary' ?>">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <small class="text-muted"><?= sanitize($m['sport_name']) ?> &middot; Group <?= $m['group_label'] ?></small>
            <?php if ($isLive): ?>
            <span class="badge bg-danger live-badge"><i class="bi bi-broadcast me-1"></i>LIVE</span>
            <?php elseif ($m['status'] === 'scheduled'): ?>
            <small class="text-muted"><?= sanitize($m['round']) ?></small>
            <?php else: ?>
            <span class="badge bg-success">FT</span>
            <?php endif; ?>
        </div>
        <div class="card-body py-2">
            <?php if ($isLive || $m['status'] === 'completed'): ?>
            <div class="text-center mb-2">
                <div class="<?= getTimerClassPHP($timer['phase']) ?>" id="timer-<?= $m['id'] ?>"><?= $timer['display'] ?></div><br>
                <span class="<?= getPeriodBadgeClassPHP($timer['phase']) ?>" id="period-<?= $m['id'] ?>"><?= $timer['periodLabel'] ?></span>
            </div>
            <?php endif; ?>

            <div class="row align-items-center mb-2">
                <div class="col-4 text-truncate"><strong><?= sanitize($m['home_name']) ?></strong></div>
                <div class="col-4 text-center">
                    <span class="score-display"><?= $homeScore ?></span>
                    <span class="mx-1 text-muted" style="font-size:1.5rem;">-</span>
                    <span class="score-display"><?= $awayScore ?></span>
                </div>
                <div class="col-4 text-truncate text-end"><strong><?= sanitize($m['away_name']) ?></strong></div>
            </div>

            <?php if ($isLive && ($m['report'] || !empty($m['squads']['home']['starting']) || !empty($m['squads']['home']['substitute']) || !empty($m['squads']['away']['starting']) || !empty($m['squads']['away']['substitute']) || !empty($m['substitutions']))): ?>
            <hr class="my-1" style="border-color:#e9ecef;">
            <div class="stats-row"><span class="stat-label">Yellow Cards</span></div>
            <div class="stats-row">
                <span class="stat-value text-warning"><?= $homeYellow ?></span>
                <div class="stats-bar flex-grow-1 mx-2"><div class="stats-bar-fill home" style="width:<?= $totalYellow > 0 ? round($homeYellow/$totalYellow*100) : 50 ?>%;"></div></div>
                <span class="stat-value text-warning"><?= $awayYellow ?></span>
            </div>
            <div class="stats-row"><span class="stat-label">Red Cards</span></div>
            <div class="stats-row">
                <span class="stat-value text-danger"><?= $homeRed ?></span>
                <div class="stats-bar flex-grow-1 mx-2"><div class="stats-bar-fill away" style="width:<?= $totalRed > 0 ? round($homeRed/$totalRed*100) : 50 ?>%;"></div></div>
                <span class="stat-value text-danger"><?= $awayRed ?></span>
            </div>
            <hr class="my-1" style="border-color:#e9ecef;">
            <div class="row">
                <div class="col-6">
                    <small class="text-muted d-block mb-1" style="font-size:0.65rem;"><?= sanitize($m['home_name']) ?></small>
                    <?php if (!empty($m['goals'])): ?>
                    <div class="mb-1"><?= renderGoalsPHP($m['goals'], 'home') ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block mb-1" style="font-size:0.65rem;"><?= sanitize($m['away_name']) ?></small>
                    <?php if (!empty($m['goals'])): ?>
                    <div class="mb-1"><?= renderGoalsPHP($m['goals'], 'away') ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?= renderCoachMatchLineupDiagram($m['squads']['home'], $m['home_name'], $m['squads']['away'], $m['away_name']) ?>
            <?= renderCoachSubstitutionEvents($m['substitutions'] ?? []) ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="col-12">
    <div class="alert alert-info text-center"><i class="bi bi-info-circle me-1"></i>No live or upcoming matches at this time.</div>
</div>
<?php endif; ?>
</div>

<?php if (!empty($completedMatches)): ?>
<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Results</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Sport</th>
                        <th>Group</th>
                        <th>Home</th>
                        <th class="text-center">Score</th>
                        <th>Away</th>
                        <th>Round</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedMatches as $m): ?>
                    <tr>
                        <td><small><?= sanitize($m['sport_name']) ?></small></td>
                        <td><span class="group-badge group-<?= $m['group_label'] ?>"><?= $m['group_label'] ?></span></td>
                        <td class="team-name-cell">
                            <?php $flagUrl = getCountyFlagUrl($m['home_name']); ?>
                            <?php if ($flagUrl): ?><img src="<?= $flagUrl ?>" alt="" style="width:16px;height:16px;object-fit:contain;border-radius:50%;margin-right:4px;vertical-align:middle;"><?php endif; ?>
                            <?= sanitize($m['home_name']) ?>
                        </td>
                        <td class="text-center">
                            <strong class="fs-5"><?= (int)$m['home_score'] ?></strong>
                            <span class="mx-1 text-muted">-</span>
                            <strong class="fs-5"><?= (int)$m['away_score'] ?></strong>
                        </td>
                        <td class="team-name-cell">
                            <?= sanitize($m['away_name']) ?>
                            <?php $flagUrl = getCountyFlagUrl($m['away_name']); ?>
                            <?php if ($flagUrl): ?><img src="<?= $flagUrl ?>" alt="" style="width:16px;height:16px;object-fit:contain;border-radius:50%;margin-left:4px;vertical-align:middle;"><?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= sanitize($m['round']) ?></small></td>
                        <td><small class="text-muted"><?= formatDate($m['match_date']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($standingsByGroup)): ?>
<?php foreach ($standingsByGroup as $sportId => $groups): ?>
<?php $firstGroup = reset($groups); ?>
<div class="mb-4">
    <div class="d-flex align-items-center mb-2">
        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $firstGroup['is_kickball'] ? '#dc3545' : '#1a237e' ?>;margin-right:8px;"></span>
        <h5 class="mb-0 fw-bold" style="color:<?= $firstGroup['is_kickball'] ? '#dc3545' : '#1a237e' ?>;"><?= sanitize($firstGroup['sport_name']) ?> Standings</h5>
    </div>
    <?php foreach ([['A','B'], ['C','D']] as $rowGroups): ?>
    <div class="row g-3 mb-3">
        <?php foreach ($rowGroups as $g): ?>
        <?php if (isset($groups[$g])): ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white py-2"><h6 class="mb-0">Group <?= $g ?></h6></div>
                <div class="card-body p-0">
                    <table class="table table-sm standing-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th><th>Team</th>
                                <?php if ($firstGroup['is_kickball']): ?>
                                <th class="text-center">GP</th><th class="text-center">W</th><th class="text-center">L</th><th class="text-center">D</th><th class="text-center">HRF</th><th class="text-center">HRA</th><th class="text-center">HRD</th>
                                <?php else: ?>
                                <th class="text-center">P</th><th class="text-center">W</th><th class="text-center">D</th><th class="text-center">L</th><th class="text-center">GF</th><th class="text-center">GA</th><th class="text-center">GD</th>
                                <?php endif; ?>
                                <th class="text-center">PTS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 0; foreach ($groups[$g]['teams'] as $tid => $s): $rank++; ?>
                            <tr class="<?= $rank <= 2 ? ($rank === 1 ? 'pos-1' : 'pos-2') : '' ?>">
                                <td class="text-center"><?= $rank ?></td>
                                <td class="team-name-cell"><small><?= sanitize($groups[$g]['info'][$tid]['name']) ?></small></td>
                                <?php if ($firstGroup['is_kickball']): ?>
                                <td class="text-center"><?= $s['played'] ?></td><td class="text-center"><?= $s['wins'] ?></td><td class="text-center"><?= $s['losses'] ?></td><td class="text-center"><?= $s['draws'] ?></td><td class="text-center"><?= $s['gf'] ?></td><td class="text-center"><?= $s['ga'] ?></td><td class="text-center"><?= $s['gd'] > 0 ? '+' : '' ?><?= $s['gd'] ?></td>
                                <?php else: ?>
                                <td class="text-center"><?= $s['played'] ?></td><td class="text-center"><?= $s['wins'] ?></td><td class="text-center"><?= $s['draws'] ?></td><td class="text-center"><?= $s['losses'] ?></td><td class="text-center"><?= $s['gf'] ?></td><td class="text-center"><?= $s['ga'] ?></td><td class="text-center"><?= $s['gd'] > 0 ? '+' : '' ?><?= $s['gd'] ?></td>
                                <?php endif; ?>
                                <td class="text-center"><strong><?= $s['pts'] ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white py-2"><h6 class="mb-0">Group <?= $g ?></h6></div>
                <div class="card-body text-center text-muted py-4"><small>No matches played yet</small></div>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
