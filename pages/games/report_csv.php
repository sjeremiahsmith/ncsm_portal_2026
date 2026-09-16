<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['match_commissioner', 'super_admin']);

$db = getDb();

$reports = $db->fetchAll("
    SELECT r.id, r.match_id, r.home_yellow_cards, r.home_red_cards, r.away_yellow_cards, r.away_red_cards, r.notes, r.created_at,
           m.match_date, m.home_score, m.away_score,
           c1.name as home_name, c2.name as away_name, s.name as sport_name,
           u.full_name as commissioner_name
    FROM match_reports r
    JOIN matches m ON r.match_id = m.id
    JOIN sports_disciplines s ON m.sport_discipline_id = s.id
    JOIN counties c1 ON m.home_county_id = c1.id
    JOIN counties c2 ON m.away_county_id = c2.id
    JOIN users u ON r.commissioner_id = u.id
    ORDER BY r.created_at DESC
");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="match_reports_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF");

// ===== SECTION 1: Report Summary =====
fputcsv($out, ['=== MATCH REPORT SUMMARY ===']);
fputcsv($out, ['ID', 'Sport', 'Home', 'Score', 'Away', 'Date', 'Commissioner', 'Home Yellow', 'Home Red', 'Away Yellow', 'Away Red', 'Notes', 'Submitted']);

foreach ($reports as $r) {
    fputcsv($out, [
        $r['id'],
        $r['sport_name'],
        $r['home_name'],
        ($r['home_score'] !== null) ? ($r['home_score'] . '-' . $r['away_score']) : '',
        $r['away_name'],
        $r['match_date'],
        $r['commissioner_name'],
        (int)$r['home_yellow_cards'],
        (int)$r['home_red_cards'],
        (int)$r['away_yellow_cards'],
        (int)$r['away_red_cards'],
        $r['notes'],
        $r['created_at'],
    ]);
}

// ===== SECTION 2: Carded Players Detail =====
fputcsv($out, []);
fputcsv($out, ['=== CARDED PLAYERS ===']);
fputcsv($out, ['Report ID', 'Match', 'Team', 'Jersey No.', 'Player Name', 'Card Type']);

foreach ($reports as $r) {
    $cards = $db->fetchAll(
        "SELECT * FROM match_report_cards WHERE report_id = ? ORDER BY team, card_type, jersey_number",
        [$r['id']]
    );
    if (empty($cards)) continue;

    $label = $r['home_name'] . ' vs ' . $r['away_name'] . ' (' . $r['sport_name'] . ')';
    foreach ($cards as $c) {
        fputcsv($out, [
            $r['id'],
            $label,
            ucfirst($c['team']),
            (int)$c['jersey_number'],
            $c['player_name'],
            ucfirst($c['card_type']),
        ]);
    }
}

fclose($out);
exit;
