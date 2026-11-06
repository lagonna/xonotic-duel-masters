<?php
require_once __DIR__ . '/../_intro.php';
header('Content-Type: application/json; charset=utf-8');

function json_out($html, $next_cursor = null) {
    echo json_encode(['html' => $html, 'next_cursor' => $next_cursor], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$member_id   = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$limit       = isset($_GET['limit']) ? max(1,(int)$_GET['limit']) : 10;
$cursor_b64  = isset($_GET['cursor']) ? trim($_GET['cursor']) : '';
$viewer_tz   = isset($_GET['viewer_tz']) ? trim($_GET['viewer_tz']) : '';
if (empty($viewer_tz) || !in_array($viewer_tz, timezone_identifiers_list())) {
    $viewer_tz = date_default_timezone_get();
}
$viewer_tz_obj = new DateTimeZone($viewer_tz);
$now_utc = time();

if ($member_id <= 0) json_out('', null);

$siteRoot = defined('MAIN_ROOT') ? rtrim(MAIN_ROOT, '/') : (rtrim($GLOBALS['MAIN_ROOT'] ?? '/', '/'));
$limit = min($limit, 100);
$fetchLimit = $limit + 1;

$before_start = 0; $before_id = 0;
if ($cursor_b64 !== '') {
    $decoded = @json_decode(@base64_decode($cursor_b64), true);
    if (is_array($decoded)) {
        $before_start = isset($decoded['before_start']) ? (int)$decoded['before_start'] : 0;
        $before_id    = isset($decoded['before_id'])    ? (int)$decoded['before_id']    : 0;
    } else {
        error_log('get_participated_events: invalid cursor received: ' . $cursor_b64);
        $before_start = 0; $before_id = 0;
    }
}

$sql = "
SELECT 
    t.tournament_id AS event_id,
    'tournament' AS event_type,
    COALESCE(NULLIF(t.name, ''), 'Tournament') AS title,
    COALESCE(g.name, '') AS game_name,
    COALESCE(NULLIF(g.imageurl, ''), NULL) AS image,
    COALESCE(t.startdate, 0) AS start_ts,
    NULL AS end_ts,
    t.winner_member_id,
    COALESCE(t.awards_assigned, 0) AS awards_assigned
FROM {$dbprefix}tournamentplayers tp
JOIN {$dbprefix}tournamentteams tt ON tp.team_id = tt.tournamentteam_id
JOIN {$dbprefix}tournaments t ON tt.tournament_id = t.tournament_id
LEFT JOIN {$dbprefix}gamesplayed g ON t.gamesplayed_id = g.gamesplayed_id
WHERE tp.member_id = ?
";

$where_clause = '';
$params = [];
$types = 'i';
$params[] = $member_id;

if ($before_start > 0 || $before_id > 0) {
    $where_clause = " AND (t.startdate < ? OR (t.startdate = ? AND t.tournament_id < ?)) ";
    $params[] = $before_start;
    $params[] = $before_start;
    $params[] = $before_id;
    $types .= 'iii';
}

$sql .= $where_clause . " ORDER BY t.startdate DESC, t.tournament_id DESC LIMIT ?";

$params[] = $fetchLimit;
$types .= 'i';

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    error_log('get_participated_events prepare failed: ' . $mysqli->error);
    json_out('', null);
}

$bind_names = [];
$bind_names[] = $types;
for ($i = 0, $len = count($params); $i < $len; $i++) {
    $name = 'p' . $i;
    $$name = $params[$i];
    $bind_names[] = &$$name;
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);

if (!$stmt->execute()) {
    error_log('get_participated_events execute failed: ' . $stmt->error);
    $stmt->close();
    json_out('', null);
}

$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
    $r['start_ts']        = isset($r['start_ts']) ? (int)$r['start_ts'] : 0;
    $r['end_ts']          = isset($r['end_ts']) ? (int)$r['end_ts'] : 0;
    $r['event_id']        = (int)$r['event_id'];
    $r['winner_member_id'] = isset($r['winner_member_id']) ? (int)$r['winner_member_id'] : 0;
    $r['awards_assigned'] = isset($r['awards_assigned']) ? (int)$r['awards_assigned'] : 0;
    $rows[] = $r;
}
$res->free();
$stmt->close();

$hasNext = count($rows) > $limit;
if ($hasNext) $rows = array_slice($rows, 0, $limit);

$html = '';
foreach ($rows as $ev) {
    $imgSrc = '';
    if (!empty($ev['image'])) {
        $img = $ev['image'];
        if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) $imgSrc = $img;
        else $imgSrc = $siteRoot . '/' . ltrim($img, '/');
    } else {
        $imgSrc = $siteRoot . '/assets/images/default-event.png';
    }
    $safeTitle = htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8');
    $safeGame = htmlspecialchars($ev['game_name'], ENT_QUOTES, 'UTF-8');

    $safeUrl = htmlspecialchars($siteRoot . '/competitions/competition.php?tID=' . (int)$ev['event_id'], ENT_QUOTES, 'UTF-8');

    $start_ts = (int)$ev['start_ts'];
    if ($start_ts > 0) {
        $start_iso = (new DateTime('@' . $start_ts))->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
        $meta_time = htmlspecialchars($start_iso, ENT_QUOTES, 'UTF-8');
    } else {
        $start_iso = '';
        $meta_time = '—';
    }

    $winner = isset($ev['winner_member_id']) ? (int)$ev['winner_member_id'] : 0;
    $awardsAssigned = isset($ev['awards_assigned']) ? (int)$ev['awards_assigned'] === 1 : false;

    if ($awardsAssigned || $winner > 0) {
        $status_badge = "<span style='padding:6px 10px;border-radius:999px;color:#6b7280;'>✅ Completed</span>";
    } elseif ($start_ts > 0 && $now_utc < $start_ts) {
        $status_badge = "<span style='padding:6px 10px;border-radius:999px;color:#ffd78a;'>📆 Upcoming</span>";
    } else {
        $status_badge = "<span style='padding:6px 10px;border-radius:999px;color:#f59e0b;'>Ongoing</span>";
    }

    $data_start_attr = $start_iso ? " data-start-utc=\"" . htmlspecialchars($start_iso, ENT_QUOTES, 'UTF-8') . "\"" : '';

    /*
	$removed = "
			<div style='width:18%;min-width:72px;'>
                <div style='display:block;overflow:hidden;position:relative;border-radius:8px;'>
                    <img alt='' src='".htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8')."' style='width:100%;height:100%;object-fit:cover;border-radius:8px;'/>
                </div>
            </div>";
	*/
	
	$html .= "
    <div class='p-tournaments-tab' style='width:95%;margin:auto;'>
        <a href='{$safeUrl}' {$data_start_attr} style='display:flex;align-items:center;gap:15px;padding:8px;border-radius:8px;text-decoration:none;background:rgba(255,255,255,0.02);border:1px solid #333840;'>
            <div style='flex:1;padding:4px;'>
                <div style='display:flex;align-items:center;justify-content:space-between;gap:12px;'>
                    <div style='font-size:15px;font-weight:600;'>{$safeGame}</div>
                    <div style='font-size:13px;'>{$status_badge}</div>
                </div>
                <div style='color:#fff;margin-top:6px;font-size:17px;font-weight:600;line-height:1.1;overflow:hidden'>{$safeTitle}</div>
                <div class='meta-time' style='color:#9aa4b2;margin-top:6px;font-size:13px;'>{$meta_time}</div>
            </div>
        </a>
    </div>";
}

$next_cursor = null;
if ($hasNext && !empty($rows)) {
    $last = end($rows);
    $next_cursor = base64_encode(json_encode([
        'before_start' => (int)$last['start_ts'],
        'before_id'    => (int)$last['event_id']
    ]));
}

json_out($html, $next_cursor);
