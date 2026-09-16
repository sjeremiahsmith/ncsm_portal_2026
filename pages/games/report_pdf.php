<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole(['match_commissioner', 'super_admin']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('No report specified.');
}

$db = getDb();
$report = $db->fetchOne("
    SELECT r.*, m.match_date, m.home_score, m.away_score,
           c1.name as home_name, c2.name as away_name, s.name as sport_name,
           u.full_name as commissioner_name
    FROM match_reports r
    JOIN matches m ON r.match_id = m.id
    JOIN sports_disciplines s ON m.sport_discipline_id = s.id
    JOIN counties c1 ON m.home_county_id = c1.id
    JOIN counties c2 ON m.away_county_id = c2.id
    JOIN users u ON r.commissioner_id = u.id
    WHERE r.id = ?
", [$id]);

if (!$report) {
    die('Report not found.');
}

$cards = $db->fetchAll("SELECT * FROM match_report_cards WHERE report_id = ? ORDER BY team, card_type, jersey_number", [$id]);
$squad = $db->fetchAll("SELECT * FROM match_squad_players WHERE report_id = ? ORDER BY team, FIELD(player_type,'starting','substitute'), jersey_number", [$id]);

$homeStart = array_filter($squad, fn($s) => $s['team']==='home' && $s['player_type']==='starting');
$homeSub = array_filter($squad, fn($s) => $s['team']==='home' && $s['player_type']==='substitute');
$awayStart = array_filter($squad, fn($s) => $s['team']==='away' && $s['player_type']==='starting');
$awaySub = array_filter($squad, fn($s) => $s['team']==='away' && $s['player_type']==='substitute');
$homeCards = array_filter($cards, fn($c) => $c['team']==='home');
$awayCards = array_filter($cards, fn($c) => $c['team']==='away');

function p($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$html = '<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:DejaVu Sans, sans-serif; font-size:9pt; color:#222; padding:30px; }
h1 { font-size:14pt; text-align:center; margin-bottom:4px; color:#111; }
.subtitle { text-align:center; font-size:8pt; color:#666; margin-bottom:15px; }
.match-info { text-align:center; font-size:10pt; font-weight:bold; margin-bottom:15px; }
.header-bar { background:#1a1a1a; color:#fff; padding:12px 16px; margin-bottom:16px; border-radius:4px; }
.header-bar .hscore { font-size:18pt; font-weight:900; }
.header-bar .hlabel { font-size:8pt; text-transform:uppercase; letter-spacing:0.05em; opacity:0.7; }
.header-bar .vs { font-size:10pt; opacity:0.5; padding:0 6px; }
.header-bar .row { display:flex; align-items:center; }
.header-bar .col { flex:1; }
.header-bar .col.right { text-align:right; }
.header-bar .meta { font-size:7pt; opacity:0.6; margin-top:6px; }
.section-title { font-size:8pt; text-transform:uppercase; letter-spacing:0.08em; font-weight:700; margin:10px 0 4px; padding-bottom:2px; border-bottom:1px solid #ddd; clear:both; }
.columns { display:flex; gap:16px; }
.col { flex:1; }
.team-label { font-size:7pt; text-transform:uppercase; letter-spacing:0.05em; font-weight:700; padding:2px 8px; border-radius:3px; display:inline-block; margin-bottom:6px; color:#fff; }
.team-home { background:#dc3545; }
.team-away { background:#0d6efd; }
.player-row { display:flex; align-items:center; padding:2px 0; border-bottom:1px solid #f0f0f0; font-size:8pt; }
.jersey { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; font-weight:800; font-size:6.5pt; color:#fff; margin-right:6px; flex-shrink:0; }
.jh { background:#dc3545; }
.ja { background:#0d6efd; }
.j-sub { opacity:0.8; }
.dummy { color:#999; font-style:italic; font-size:7pt; padding:4px 0; }
.card-y { display:inline-block; width:8px; height:14px; background:#ffc107; border-radius:1px; vertical-align:middle; }
.card-r { display:inline-block; width:8px; height:14px; background:#dc3545; border-radius:1px; vertical-align:middle; }
.card-row { display:flex; align-items:center; padding:1.5px 0; font-size:7.5pt; }
.footer-note { margin-top:12px; padding-top:6px; border-top:1px solid #ddd; font-size:7.5pt; color:#555; }
</style></head><body>

<h1>NCSM Liberias</h1>
<div class="subtitle">National County Sports Meet &mdash; Match Report</div>
<div class="match-info">' . p($report['home_name']) . ' vs ' . p($report['away_name']) . ' &mdash; ' . p($report['sport_name']) . '</div>

<div class="header-bar">
    <div class="row">
        <div class="col" style="text-align:left;">
            <div class="hlabel">' . p($report['home_name']) . '</div>
            <div class="hscore">' . (int)$report['home_score'] . '</div>
        </div>
        <div class="col" style="text-align:center;">
            <div class="vs">VS</div>
        </div>
        <div class="col right">
            <div class="hlabel">' . p($report['away_name']) . '</div>
            <div class="hscore">' . (int)$report['away_score'] . '</div>
        </div>
    </div>
    <div class="meta">' . formatDate($report['match_date'], 'F d, Y') . ' | Commissioner: ' . p($report['commissioner_name']) . ' | Report ID: #' . $report['id'] . '</div>
</div>

<div class="columns">
    <div class="col">
        <span class="team-label team-home">HOME</span>
        <div class="section-title">Starting XI</div>
        ' . (!empty($homeStart)
            ? implode('', array_map(fn($s) => '<div class="player-row"><span class="jersey jh">' . (int)$s['jersey_number'] . '</span>' . p($s['player_name']) . '</div>', $homeStart))
            : '<div class="dummy">Squad not entered.</div>') . '
        <div class="section-title">Substitutes</div>
        ' . (!empty($homeSub)
            ? implode('', array_map(fn($s) => '<div class="player-row"><span class="jersey jh j-sub" style="background:#b03a3a;">' . (int)$s['jersey_number'] . '</span>' . p($s['player_name']) . '</div>', $homeSub))
            : '<div class="dummy">None.</div>') . '
        ' . (!empty($homeCards) ? '
        <div class="section-title">Cards &mdash; Yellow: ' . (int)$report['home_yellow_cards'] . ', Red: ' . (int)$report['home_red_cards'] . '</div>
        ' . implode('', array_map(fn($c) => '<div class="card-row"><span class="jersey jh" style="width:18px;height:18px;font-size:5.5pt;">' . (int)$c['jersey_number'] . '</span>' . p($c['player_name']) . '<span style="margin-left:auto;font-size:6.5pt;font-weight:700;' . ($c['card_type']==='yellow' ? 'color:#b8860b;' : 'color:#dc3545;') . '">' . strtoupper($c['card_type']) . '</span></span></div>', $homeCards)) . '
        ' : '') . '
    </div>
    <div class="col">
        <span class="team-label team-away">AWAY</span>
        <div class="section-title">Starting XI</div>
        ' . (!empty($awayStart)
            ? implode('', array_map(fn($s) => '<div class="player-row"><span class="jersey ja">' . (int)$s['jersey_number'] . '</span>' . p($s['player_name']) . '</div>', $awayStart))
            : '<div class="dummy">Squad not entered.</div>') . '
        <div class="section-title">Substitutes</div>
        ' . (!empty($awaySub)
            ? implode('', array_map(fn($s) => '<div class="player-row"><span class="jersey ja j-sub" style="background:#5a6fba;">' . (int)$s['jersey_number'] . '</span>' . p($s['player_name']) . '</div>', $awaySub))
            : '<div class="dummy">None.</div>') . '
        ' . (!empty($awayCards) ? '
        <div class="section-title">Cards &mdash; Yellow: ' . (int)$report['away_yellow_cards'] . ', Red: ' . (int)$report['away_red_cards'] . '</div>
        ' . implode('', array_map(fn($c) => '<div class="card-row"><span class="jersey ja" style="width:18px;height:18px;font-size:5.5pt;">' . (int)$c['jersey_number'] . '</span>' . p($c['player_name']) . '<span style="margin-left:auto;font-size:6.5pt;font-weight:700;' . ($c['card_type']==='yellow' ? 'color:#b8860b;' : 'color:#dc3545;') . '">' . strtoupper($c['card_type']) . '</span></span></div>', $awayCards)) . '
        ' : '') . '
    </div>
</div>

' . (!empty($report['notes']) ? '<div class="footer-note"><strong>Notes:</strong> ' . nl2br(p($report['notes'])) . '</div>' : '') . '

<div style="text-align:center;margin-top:20px;font-size:6.5pt;color:#999;">Generated on ' . date('F d, Y \a\t h:i A') . '</div>

</body></html>';

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'match_report_' . $report['id'] . '_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
