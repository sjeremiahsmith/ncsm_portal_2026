<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$db = getDb();

$sportFilterId = hasRole('association_admin') ? (int)$_SESSION['user_association_id'] : null;

$filter = $sportFilterId ? " AND m.sport_discipline_id = $sportFilterId" : "";

$liveMatches = $db->fetchAll("
    SELECT m.*, s.name as sport_name, c1.name as home_name, c2.name as away_name
    FROM matches m
    JOIN sports_disciplines s ON m.sport_discipline_id = s.id
    JOIN counties c1 ON m.home_county_id = c1.id
    JOIN counties c2 ON m.away_county_id = c2.id
    WHERE m.status IN ('live','scheduled') $filter
    ORDER BY m.match_date ASC
    LIMIT 50
");

$result = [];
foreach ($liveMatches as $m) {
    $report = $db->fetchOne("SELECT * FROM match_reports WHERE match_id = ?", [$m['id']]);
    $cards = [];
    $squads = ['home' => ['starting' => [], 'substitute' => []], 'away' => ['starting' => [], 'substitute' => []]];
    if ($report) {
        $cards = $db->fetchAll("SELECT * FROM match_report_cards WHERE report_id = ?", [$report['id']]);
        $squadRows = $db->fetchAll("SELECT * FROM match_squad_players WHERE report_id = ? ORDER BY team, player_type, jersey_number", [$report['id']]);
        foreach ($squadRows as $s) {
            $squads[$s['team']][$s['player_type']][] = [
                'jersey' => (int)$s['jersey_number'],
                'name' => $s['player_name']
            ];
        }
    }
    $goals = $db->fetchAll("SELECT * FROM match_goals WHERE match_id = ? ORDER BY minute ASC, team ASC", [$m['id']]);
    $result[] = [
        'id' => (int)$m['id'],
        'sport_name' => $m['sport_name'],
        'group_label' => $m['group_label'],
        'home_name' => $m['home_name'],
        'away_name' => $m['away_name'],
        'home_score' => $m['home_score'] !== null ? (int)$m['home_score'] : null,
        'away_score' => $m['away_score'] !== null ? (int)$m['away_score'] : null,
        'status' => $m['status'],
        'match_date' => $m['match_date'],
        'round' => $m['round'],
        'updated_at' => $m['updated_at'],
        'timer_kickoff' => $m['timer_kickoff'],
        'timer_offset' => (int)($m['timer_offset'] ?? 0),
        'report' => $report ? [
            'home_yellow_cards' => (int)$report['home_yellow_cards'],
            'home_red_cards' => (int)$report['home_red_cards'],
            'away_yellow_cards' => (int)$report['away_yellow_cards'],
            'away_red_cards' => (int)$report['away_red_cards'],
            'notes' => $report['notes']
        ] : null,
        'cards' => array_map(function($c) {
            return [
                'team' => $c['team'],
                'card_type' => $c['card_type'],
                'jersey_number' => (int)$c['jersey_number'],
                'player_name' => $c['player_name']
            ];
        }, $cards),
        'goals' => array_map(function($g) {
            return [
                'team' => $g['team'],
                'jersey_number' => (int)$g['jersey_number'],
                'player_name' => $g['player_name'],
                'minute' => $g['minute'] !== null ? (int)$g['minute'] : null,
                'goal_type' => $g['goal_type']
            ];
        }, $goals),
        'squads' => $squads
    ];
}

echo json_encode(['matches' => $result, 'server_time' => date('Y-m-d H:i:s')]);
