<?php
define("COMPETITIONS_LINK", true);

include_once("../_intro.php");
$prevFolder = "../";

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function parseTs($raw) {
    if (empty($raw)) return 0;

    if (is_numeric($raw)) {
        $v = intval($raw);
        return ($v > 9999999999) ? intval($v / 1000) : $v;
    }

    return strtotime($raw) ?: 0;
}


function totalRounds($maxTeams) {
    if ($maxTeams < 2) return 0;
    return (int) ceil(log($maxTeams, 2));
}

function formatPrize($amount, $percent) {
    $v = ($percent * floatval($amount)) / 100;
    return '$' . number_format($v, 2, '.', ',');
}

function matchStatus($m, $now) {
    $start   = intval($m['match_start'] ?? 0);
    $outcome = intval($m['outcome']     ?? 0);
    if ($outcome === 1 || $outcome === 2) return ['state' => 'finished', 'label' => 'Finished',      'color' => '#6b7280'];
    if ($start <= 0)                      return ['state' => 'pending',  'label' => 'Not scheduled', 'color' => '#777777'];
    if ($start > $now)                    return ['state' => 'upcoming', 'label' => 'Upcoming',      'color' => '#16a34a'];
    return ['state' => 'live', 'label' => 'Live', 'color' => '#d32f2f'];
}

if (!isset($_GET['tID']) || !is_numeric($_GET['tID'])) echo "
    <script type='text/javascript'>
        window.location = '".$MAIN_ROOT."/competitions/';
    </script>
";

$tID = intval($_GET['tID']);
$now = time();

$res = $mysqli->query("SELECT * FROM {$dbprefix}tournaments WHERE tournament_id = '{$tID}' LIMIT 1");
if (!$res || $res->num_rows === 0) echo "
    <script type='text/javascript'>
        window.location = '".$MAIN_ROOT."/competitions/';
    </script>
";

$t = $res->fetch_assoc();

$start_ts       = parseTs($t['startdate'] ?? '');
$maxTeams       = max(2, intval($t['maxteams']));
$tprHours       = intval($t['TPR'] ?? 0);
$totalRounds    = totalRounds($maxTeams);
$playersPerTeam = intval($t['playersperteam'] ?? 1);

$registrationRaw = $t['registration_status'] ?? 0;

$currentPlayers   = intval($t['participants_count'] ?? 0);
$totalSlots       = $maxTeams * $playersPerTeam;
$registrationOpen = intval($registrationRaw) === 1 && ($totalSlots === 0 || $currentPlayers < $totalSlots);

$wRes       = $mysqli->query("SELECT outcome, team1_id, team2_id FROM {$dbprefix}tournamentmatch WHERE nextmatch_id = '0' AND tournament_id = '{$tID}' LIMIT 1");
$finalMatch = $wRes ? $wRes->fetch_assoc() : null;
$winnerId   = false;
if ($finalMatch) {
    if ($finalMatch['outcome'] == 1) $winnerId = intval($finalMatch['team1_id']);
    if ($finalMatch['outcome'] == 2) $winnerId = intval($finalMatch['team2_id']);
}
$awardsAssigned = isset($t['awards_assigned']) ? intval($t['awards_assigned']) : ($winnerId ? 1 : 0);

if ($awardsAssigned || $winnerId) {
    $statusLabel = 'Finished';
    $statusClass = 'finished';
    $statusColor = '#6b7280';
    $statusSub   = 'Tournament is over';
} elseif ($start_ts > 0 && $now < $start_ts) {
    $statusLabel = 'Upcoming';
    $statusClass = 'upcoming';
    $statusColor = '#ffd78a';
    $statusSub   = 'Starts soon';
} else {
    $statusLabel = 'Live';
    $statusClass = 'ongoing';
    $statusColor = '#ff4d4d';
    $statusSub   = 'Tournament in progress';
}

$regLabel = $registrationOpen ? 'Registration Open' : 'Registration Closed';
$regColor = $registrationOpen ? '#10b981' : '#6b7280';
$regBg    = $registrationOpen ? 'rgba(16,185,129,0.10)' : 'rgba(107,114,128,0.12)';
$regDot   = $registrationOpen ? '#10b981' : '#6b7280';

switch (intval($t['seedtype'] ?? 1)) {
    case 2:  $dispSeedType = 'Random'; break;
    case 3:  $dispSeedType = 'Pools';  break;
    default: $dispSeedType = 'Manual';
}
$dispEliminations  = ($t['eliminations'] == 1) ? 'Single Elimination' : 'Double Elimination';
$prizeTotal        = $t['t_prize'] ?? 0;
$currency          = h($t['t_prize_currency'] ?? 'BTC');
$start_iso         = $start_ts ? (new DateTime('@' . $start_ts))->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM) : '';
$dispPlayerOrTeam  = ($playersPerTeam === 1) ? 'Player' : 'Team';

$creatorId        = intval($t['member_id'] ?? 0);
$creatorUsername  = 'Unknown';
$creatorStyleJson = '';
$creatorProfile   = '';

if ($creatorId > 0) {
    $creatorRes = $mysqli->query("SELECT username, username_style FROM {$dbprefix}members WHERE member_id = '{$creatorId}' LIMIT 1");
    if ($creatorRes && $creatorRes->num_rows > 0) {
        $creatorRow       = $creatorRes->fetch_assoc();
        $creatorUsername  = !empty($creatorRow['username']) ? $creatorRow['username'] : 'Unknown';
        $creatorStyleJson = $creatorRow['username_style'] ?? '';
        $creatorProfile   = $MAIN_ROOT . 'member.php?mID=' . $creatorId;
    }
}

$gameRow = ['imageurl' => '', 'platform' => '', 'game_name' => ''];
$gRes    = $mysqli->query("SELECT imageurl, thumburl, platform, IFNULL(name,'') AS game_name FROM {$dbprefix}gamesplayed WHERE gamesplayed_id = '" . intval($t['gamesplayed_id'] ?? 0) . "' LIMIT 1");
if ($gRes) $gameRow = $gRes->fetch_assoc() ?: $gameRow;

$gameName     = $gameRow['game_name'];
$imageSrcSafe  = !empty($gameRow['imageurl']) ? h($MAIN_ROOT . $gameRow['imageurl']) : ($MAIN_ROOT . 'images/default_cover.png');

$arrPlayerIds = [];

$pRes = $mysqli->query("SELECT member_id FROM {$dbprefix}tournamentplayers WHERE tournament_id = '{$tID}' ORDER BY tournamentplayer_id");
while ($pRow = $pRes->fetch_assoc()) {
    $mid = intval($pRow['member_id']);
    if ($mid > 0) {
        $arrPlayerIds[] = $mid;
    }
}

$arrPlayerIds   = array_values(array_unique($arrPlayerIds));
$currentPlayers = intval($t['participants_count'] ?? count($arrPlayerIds));
$totalSlots     = $maxTeams * $playersPerTeam;

$dispMemberListHtml = '';
if (!empty($arrPlayerIds)) {
    $ids_csv = implode(',', $arrPlayerIds);

    $mRes = $mysqli->query("SELECT member_id, username, username_style, profilepic, avatar FROM {$dbprefix}members WHERE member_id IN ($ids_csv)");
    $membersById = [];
    while ($mRow = $mRes->fetch_assoc()) {
        $membersById[intval($mRow['member_id'])] = $mRow;
    }

    foreach ($arrPlayerIds as $pid) {
        if (!isset($membersById[$pid])) continue;

        $m = $membersById[$pid];

        $profile = '';
        if (!empty($m['avatar'])) {
            $profile = $m['avatar'];
        } else {
            $profile = 'images/avatar/thumbs/defaultprofile.png';
        }

        if (preg_match('~^https?://~i', $profile)) {
            $profileUrl = $profile;
        } else {
            $profileUrl = $MAIN_ROOT . ltrim($profile, '/');
        }

        $styledName = renderStyledUsername($m['username'] ?? 'Unknown', $m['username_style'] ?? '');

        $dispMemberListHtml .= '
            <div class="participant">
                <img src="' . h($profileUrl) . '" alt="' . h($m['username'] ?? 'Unknown') . '">
                <a href="' . $MAIN_ROOT . 'member.php?mID=' . $pid . '">' . $styledName . '</a>
            </div>';
    }
}

$allMatches = [];
$mRes = $mysqli->query("SELECT * FROM {$dbprefix}tournamentmatch WHERE tournament_id = '{$tID}' ORDER BY round, tournamentmatch_id");
while ($row = $mRes->fetch_assoc()) $allMatches[] = $row;

$matchTeamIds = [];
foreach ($allMatches as $m) {
    if (!empty($m['team1_id'])) $matchTeamIds[] = intval($m['team1_id']);
    if (!empty($m['team2_id'])) $matchTeamIds[] = intval($m['team2_id']);
}
$matchTeamIds = array_values(array_unique(array_filter($matchTeamIds)));

$bTeams = [];
if (!empty($matchTeamIds)) {
    $r = $mysqli->query("SELECT * FROM {$dbprefix}tournamentteams WHERE tournamentteam_id IN (" . implode(',', $matchTeamIds) . ")");
    while ($row = $r->fetch_assoc()) $bTeams[intval($row['tournamentteam_id'])] = $row;
}

$bTeamPlayers = [];
if (!empty($matchTeamIds)) {
    $r = $mysqli->query("SELECT * FROM {$dbprefix}tournamentplayers WHERE team_id IN (" . implode(',', $matchTeamIds) . ")");
    while ($row = $r->fetch_assoc()) $bTeamPlayers[intval($row['team_id'])][] = $row;
}

$bMemberIds = [];
foreach ($bTeamPlayers as $players)
    foreach ($players as $p)
        if (!empty($p['member_id'])) $bMemberIds[] = intval($p['member_id']);
$bMemberIds = array_values(array_unique($bMemberIds));

$bMembers = [];
if (!empty($bMemberIds)) {
    $r = $mysqli->query("SELECT member_id, username, username_style, avatar, profilepic FROM {$dbprefix}members WHERE member_id IN (" . implode(',', $bMemberIds) . ")");
    while ($row = $r->fetch_assoc()) $bMembers[intval($row['member_id'])] = $row;
}



$originTeamIds = [];
foreach ($bTeams as $team)
    if (!empty($team['origin_team'])) $originTeamIds[] = intval($team['origin_team']);
$bSquads = [];
if (!empty($originTeamIds)) {
    $r = $mysqli->query("SELECT * FROM {$dbprefix}squads WHERE squad_id IN (" . implode(',', array_unique($originTeamIds)) . ")");
    while ($row = $r->fetch_assoc()) $bSquads[intval($row['squad_id'])] = $row;
}



$matchesByRound = [];
foreach ($allMatches as $m) {
    if (intval($m['team1_id']) > 0 || intval($m['team2_id']) > 0)
        $matchesByRound[intval($m['round'])][] = $m;
}
ksort($matchesByRound);

$isSingleMatchTournament = ($totalRounds === 1)
    && (intval($t['seedtype'] ?? 1) !== 3)
    && (count($allMatches) === 1);
$singleMatch = $isSingleMatchTournament ? $allMatches[0] : null;



function teamDisplay($teamId, $playersPerTeam, $bTeams, $bTeamPlayers, $bMembers, $MAIN_ROOT) {
    $teamId    = intval($teamId);
    $slotCount = max(1, intval($playersPerTeam));
    $players   = ($teamId > 0 && !empty($bTeamPlayers[$teamId])) ? array_values($bTeamPlayers[$teamId]) : [];

    $picParts  = [];
    $nameParts = [];

    for ($i = 0; $i < $slotCount; $i++) {
        $p   = $players[$i] ?? null;
        $mid = intval($p['member_id'] ?? 0);
        $m   = ($mid > 0 && isset($bMembers[$mid])) ? $bMembers[$mid] : null;

        if (!$m) {
            $picParts[]  = "<img src='" . h($MAIN_ROOT) . "assets/images/question_mark.png' width='32' height='32' style='border-radius:6px;object-fit:cover;border:1px solid rgba(255,255,255,0.12);'>";
            $nameParts[] = "<span style='color:#dbeaf6;font-weight:700;display:block;line-height:1.4;'>-</span>";
            continue;
        }

        $username  = $m['username'] ?? '-';
        $styleJson = $m['username_style'] ?? '';
        $avatarUrl = $MAIN_ROOT . 'assets/images/default_profile.png';

        if (!empty($m['avatar'])) {
            $av = trim((string)$m['avatar']);
            $avatarUrl = preg_match('~^https?://~i', $av) ? $av : $MAIN_ROOT . ltrim($av, '/');
        } elseif (!empty($m['profilepic'])) {
            $pp = trim((string)$m['profilepic']);
            $avatarUrl = preg_match('~^https?://~i', $pp) ? $pp : $MAIN_ROOT . ltrim($pp, '/');
        }

        $picParts[]  = "<img src='" . h($avatarUrl) . "' title='" . h($username) . "' width='32' height='32' style='border-radius:6px;object-fit:cover;border:1px solid rgba(255,255,255,0.12);'>";
        $nameParts[] = ($mid > 0)
            ? "<a href='" . h($MAIN_ROOT) . "member.php?mID={$mid}' style='color:#ffeb3b;font-weight:700;text-decoration:none;display:block;line-height:1.4;'>" . renderStyledUsername($username, $styleJson) . "</a>"
            : "<span style='color:#dbeaf6;font-weight:700;display:block;line-height:1.4;'>" . renderStyledUsername($username, $styleJson) . "</span>";
    }

    return [
        'pic'  => "<div style='display:flex;flex-direction:row;gap:6px;align-items:center;'>" . implode('', $picParts) . "</div>",
        'name' => "<div style='display:flex;flex-direction:column;gap:6px;align-items:center;'>" . implode('', $nameParts) . "</div>",
    ];
}

function bracketTeamDisplay($teamId, $playersPerTeam, $bTeamPlayers, $bMembers, $MAIN_ROOT) {
    $teamId = intval($teamId);
    $slotCount = max(1, intval($playersPerTeam));

    $placeholderPic = "<img src='" . h($MAIN_ROOT) . "assets/images/question_mark.png' width='24' height='24' style='border-radius:6px;object-fit:cover;border:1px solid rgba(255,255,255,0.12);'>";
    $placeholderName = "<span style='color:#dbeaf6;font-weight:700;width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:1.05;'>-</span>";

    $players = ($teamId > 0 && !empty($bTeamPlayers[$teamId])) ? array_values($bTeamPlayers[$teamId]) : [];
    $rows = [];

    for ($i = 0; $i < $slotCount; $i++) {
        $p   = $players[$i] ?? null;
        $mid = intval($p['member_id'] ?? 0);
        $m   = ($mid > 0 && isset($bMembers[$mid])) ? $bMembers[$mid] : null;

		// 

        if (!$m || $mid <= 0) {
            $rows[] = "
                <div style='display:flex;align-items:center;gap:6px;min-width:0;'>
                    <div style='flex:0 0 auto;line-height:0;'>{$placeholderPic}</div>
                    {$placeholderName}
                </div>";
            continue;
        }

        $username  = $m['username'] ?? '-';
        $styleJson = $m['username_style'] ?? '';

        $avatarUrl = $MAIN_ROOT . 'assets/images/default_profile.png';
        if (!empty($m['avatar'])) {
            $avatar = trim((string)$m['avatar']);
            $avatarUrl = preg_match('~^https?://~i', $avatar) ? $avatar : $MAIN_ROOT . ltrim($avatar, '/');
        } elseif (!empty($m['profilepic'])) {
            $profilepic = trim((string)$m['profilepic']);
            $avatarUrl = preg_match('~^https?://~i', $profilepic) ? $profilepic : $MAIN_ROOT . ltrim($profilepic, '/');
        }

        $pic = "<img src='" . h($avatarUrl) . "' width='24' height='24' style='border-radius:5px;object-fit:cover;border:1px solid rgba(255,255,255,0.12);flex:0 0 16px;'>";
        $nameHtml = ($mid > 0)
            ? "<a href='" . h($MAIN_ROOT) . "member.php?mID={$mid}' style='color:#ffeb3b;font-weight:700;text-decoration:none;display:block;width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:1.05;'>" . renderStyledUsername($username, $styleJson) . "</a>"
            : "<span style='color:#dbeaf6;font-weight:700;display:block;width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:1.05;'>" . renderStyledUsername($username, $styleJson) . "</span>";

        $rows[] = "
            <div style='display:flex;align-items:center;gap:6px;min-width:0;'>
                <div style='flex:0 0 auto;line-height:0;'>{$pic}</div>
                {$nameHtml}
            </div>";
    }

    return [
        'html' => "<div style='display:flex;flex-direction:column;gap:4px;align-items:flex-start;'>" . implode('', $rows) . "</div>"
    ];
}

function standingMembersHtml($teamId, $bTeamPlayers, $bMembers, $MAIN_ROOT) {
    $teamId = intval($teamId);
    if (!$teamId) {
        return "<div style='color:#9fb0bf;font-size:13px;margin-top:7px;'>Not set yet</div>";
    }

    $players = $bTeamPlayers[$teamId] ?? [];
    if (empty($players)) {
        return "<div style='color:#9fb0bf;font-size:13px;'>No members found</div>";
    }

    $html = "<div style='display:flex;flex-direction:column;gap:6px;margin-top:8px;'>";

    foreach ($players as $p) {
        $mid = intval($p['member_id'] ?? 0);
        $m   = ($mid > 0 && isset($bMembers[$mid])) ? $bMembers[$mid] : null;

        $username  = $m['username'] ?? '-';
        $styleJson = $m['username_style'] ?? '';

        $avatarUrl = $MAIN_ROOT . 'images/avatar/thumbs/defaultprofile.png';
        if ($m && !empty($m['avatar'])) {
            $avatar = trim((string)$m['avatar']);
            $avatarUrl = preg_match('~^https?://~i', $avatar) ? $avatar : $MAIN_ROOT . ltrim($avatar, '/');
        }

        $nameHtml = ($mid > 0)
            ? "<a href='" . h($MAIN_ROOT) . "member.php?mID={$mid}' style='color:#ffeb3b;font-weight:700;text-decoration:none;'>" . renderStyledUsername($username, $styleJson) . "</a>"
            : "<span style='color:#dbeaf6;font-weight:700;'>" . renderStyledUsername($username, $styleJson) . "</span>";

        $html .= "
            <div style='display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.03);padding:8px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.05);'>
                <img src='" . h($avatarUrl) . "' alt='" . h($username) . "' width='28' height='28' style='border-radius:6px;object-fit:cover;flex:0 0 28px;'>
                <div style='min-width:0;line-height:1.15;'>" . $nameHtml . "</div>
            </div>";
    }

    $html .= "</div>";
    return $html;
}

function renderMatchCard($match, $now, $playersPerTeam, $bTeams, $bTeamPlayers, $bMembers, $MAIN_ROOT, $tID, $loggedIn, $isTournOwner, $isAdmin) {
    $mId      = intval($match['tournamentmatch_id']);
    $outcome  = intval($match['outcome'] ?? 0);
    $ms       = matchStatus($match, $now);

    $score1 = ($outcome === 1 || $outcome === 2) ? $match['team1score'] : '-';
    $score2 = ($outcome === 1 || $outcome === 2) ? $match['team2score'] : '-';
    $score1 = is_numeric($score1) ? intval($score1) : h((string)$score1);
    $score2 = is_numeric($score2) ? intval($score2) : h((string)$score2);

    $col1 = $col2 = '#ddd';
    if ($outcome === 1) { $col1 = '#0bf115'; $col2 = '#ff6b6b'; }
    if ($outcome === 2) { $col1 = '#ff6b6b'; $col2 = '#0bf115'; }

    $td1 = teamDisplay(intval($match['team1_id']), $playersPerTeam, $bTeams, $bTeamPlayers, $bMembers, $MAIN_ROOT);
    $td2 = teamDisplay(intval($match['team2_id']), $playersPerTeam, $bTeams, $bTeamPlayers, $bMembers, $MAIN_ROOT);

    $matchStart = intval($match['match_start'] ?? 0);

    if ($ms['state'] === 'live') {
        $statusElem = "<div class='live-pill'><span class='ongoing-dot'></span>LIVE</div>";
    } elseif ($matchStart <= 0) {
        $statusElem = "<div style='background:{$ms['color']};color:#fff;padding:6px 12px;border-radius:10px;font-size:12px;font-weight:bold;'>{$ms['label']}</div>";
    } else {
        $statusElem = "<div class='countdown' data-start-ts='{$matchStart}' data-state='{$ms['state']}'></div>";
    }

    $startElem = $matchStart
        ? "<span class='local-dt' data-ts='{$matchStart}'>" . date("M j, Y H:i", $matchStart) . " UTC</span>"
        : "";

    $manageBtn = '';
    if ($loggedIn && ($isTournOwner || $isAdmin)) {
        $manageBtn = "
          <a href='{$MAIN_ROOT}competitions/match.php?tID={$tID}&mID={$mId}' style='text-decoration:none;'>
            <button class='btn btn-manage'>Manage Match</button>
          </a>";
    }

    return "
    <div style='display:flex;justify-content:center;align-items:center;margin-top:40px;'>
      <div style='border:1px solid #2a2f2c;border-radius:20px;padding:20px 26px;box-shadow:0 0 20px rgba(0,0,0,0.6);text-align:center;width:640px;position:relative;'>

        <div style='font-size:13px;color:#888;'>
          {$startElem}
          <div style='display:flex;align-items:center;justify-content:center;margin-top:10px;'>
            {$statusElem}
          </div>
        </div>

        <div style='display:flex;justify-content:space-between;align-items:center;margin-top:15px;'>

          <div style='flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;'>
            {$td1['pic']}
            <div>{$td1['name']}</div>
            <div style='font-size:22px;font-weight:bold;color:{$col1};background:#000;padding:1px 10px;border-radius:8px;border:2px solid #333840;'>
              {$score1}
            </div>
          </div>

          <div style='font-size:26px;font-weight:bold;color:#ffeb3b;margin:0 18px;text-shadow:0 0 8px #ffeb3b;'>VS</div>

          <div style='flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;'>
            {$td2['pic']}
            <div>{$td2['name']}</div>
            <div style='font-size:22px;font-weight:bold;color:{$col2};background:#000;padding:1px 10px;border-radius:8px;border:2px solid #333840;'>
              {$score2}
            </div>
          </div>

        </div>

        <div style='margin-top:14px;display:flex;align-items:center;justify-content:center;gap:12px;'>
          {$manageBtn}
        </div>

      </div>
    </div>";
}

$standings = [];
$sRes = $mysqli->query("
    SELECT place_num, tournamentteam_id
    FROM {$dbprefix}tournamentstandings
    WHERE tournament_id = '{$tID}'
    ORDER BY place_num ASC
");

if ($sRes) {
    while ($row = $sRes->fetch_assoc()) {
        $placeNum = intval($row['place_num']);
        $teamId   = intval($row['tournamentteam_id']);
        if ($placeNum > 0 && $teamId > 0) {
            $standings[$placeNum] = $teamId;
        }
    }
}

$PAGE_NAME = h($t['name']) . " - ";
include($prevFolder . "assets/_header.php");

$loggedIn     = isset($_SESSION['user_id']) && defined("LOGGED_IN");
$loggedUserId = $loggedIn ? intval($_SESSION['user_id']) : 0;
$isAdmin      = $loggedIn && ($loggedUserId === 1);
$isTournOwner = $loggedIn && ($loggedUserId === intval($t['member_id'] ?? 0));
$isJoined     = $loggedIn && in_array($loggedUserId, $arrPlayerIds, true);
$showJoin     = ($statusLabel !== 'Finished') && $registrationOpen;

?>
<style>
:root {
  --bg: #071523;
  --card: rgba(255,255,255,0.03);
  --muted: #ddd;
  --accent: #ffeb3b;
  --finished: #6b7280;
  --closed: #8b5e34;
  --radius: 12px;
  color-scheme: dark;
}

.header-card { display:flex; align-items: center; gap:30px; padding:15px 20px; border-radius:var(--radius); border:1px solid #2a2f2c; }
.cover { overflow:hidden; width: 180px; }
@media(max-width:880px) { .cover { width: 0; } } 
.cover img { width:100%; height:100%; object-fit:cover; display:block; }
.info { display:flex; flex-direction:column; gap:10px; }
.game-name { color:#ffeb3b; font-weight:600; font-size:18px; line-height:1; }
.title-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.title { font-weight:800; font-size:1.5rem; line-height:1; margin:0; color:#fff; overflow:hidden; text-overflow:ellipsis; }
.meta { color:var(--muted); font-size:14px; display:flex; gap:12px; align-items:center; flex-wrap:wrap; }

.badge {
  padding:3px 12px;
  border-radius:999px;
  font-weight:700;
  color:#fff;
  font-size:15px;
  display:inline-flex;
  align-items:center;
  gap:6px;
}

.badge.upcoming { background:#ffd78a; color:#1a1a1a; }
.badge.ongoing  { background:#ff4d4d; }
.badge.finished { background:#6b7280; }

.ongoing-dot {
  display:inline-block;
  width:10px;
  height:10px;
  border-radius:50%;
  background:#fff;
  animation:pulseFade 1.5s ease-in-out infinite;
  box-shadow:0 0 8px rgba(255,255,255,0.2);
}

@keyframes pulseFade { 0%{opacity:0.2} 50%{opacity:1} 100%{opacity:0.2} }

.btn-edit    { background:linear-gradient(135deg,#2563eb,#1e40af); color:#fff; }
.btn-delete  { background:linear-gradient(135deg,#dc2626,#991b1b); color:#fff; }
.btn-join    { background:#ffeb3b; color:#000; padding:9px 24px; border-radius:8px; display:inline-block; text-decoration:none; }
.btn-join:hover { filter:brightness(0.95); }
.btn-joined  { background:#6b7280; color:#fff; cursor:not-allowed; opacity:0.9; padding:9px 24px; border-radius:8px; border:none; }

.status-card-top {
  display:flex;
  flex-direction:column;
  align-items:flex-start;
  gap:10px;
}
.status-main {
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:10px;
}
.status-subtitle {
  color:#9fb0bf;
  font-weight:800;
  font-size:15px;
}
.status-countdown {
  color:#ffd78a;
  font-weight:900;
  font-size:18px;
}
.action-buttons {
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}

.overview-grid { display:grid; gap:30px; grid-template-columns:1fr; }
.overview-grid.single-match-overview { grid-template-columns: minmax(0, 1fr); }
.overview-grid.single-match-overview > .left-column { min-width: 0; }
@media(min-width:880px) { .overview-grid { grid-template-columns:1fr 360px; } }

.cards { display:grid; gap:10px; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); margin-bottom:30px; }
.card   { background:var(--card); padding:10px; border-radius:10px; border:1px solid #2a2f2c; height: fit-content; }
.card .k { color:var(--accent); font-weight:700; font-size:12px; text-transform:uppercase; }
.card .v { margin-top:6px; color:#dbeaf6; font-weight:700; }

.part-box  { border-radius:10px; padding:10px; border:1px solid #2a2f2c; }
.part-row  { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.part-grid { display:flex; gap:8px; overflow:auto; padding-top:8px; flex-direction:column; }
.part-grid.collapsed .participant             { display:none; }
.part-grid.collapsed .participant:nth-child(-n+8) { display:flex; }
.participant     { display:flex; gap:8px; align-items:center; background:rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius:8px; padding:8px; text-decoration:none; color:inherit; }
.participant img { width:40px; height:40px; border-radius:8px; object-fit:cover; flex:0 0 40px; }
.participant a   { font-size:13px; font-weight:700; color:#ffeb3b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:250px; text-decoration:none; }

.desc-text { color:var(--muted); line-height:1.45; }
.desc-text.clamped { display:-webkit-box; -webkit-line-clamp:12; -webkit-box-orient:vertical; overflow:hidden; }
.desc-toggle { background:#2196f32e; border:1px solid rgba(255,255,255,0.04); padding:8px 10px; border-radius:8px; color:var(--accent); cursor:pointer; font-weight:800; margin-top:20px; margin-bottom:10px; }

.btn-manage { border-radius:22px; cursor:pointer; }

.countdown-glow { font-weight:900; font-size:18px; color:#ffd78a; }
.live-pill { padding:6px 12px; border-radius:999px; background:linear-gradient(90deg,#ff4d4d,#ff7043);
  color:#fff; font-weight:800; box-shadow:0 0 12px rgba(255,80,80,0.25);
  animation:livePulse 1.2s infinite ease-in-out; font-size:14px; display:flex; align-items:center; gap:6px; }
@keyframes livePulse {
  0%   { transform:scale(1);    box-shadow:0 0 10px rgba(255,80,80,0.18); }
  50%  { transform:scale(1.03); box-shadow:0 0 18px rgba(255,80,80,0.28); }
  100% { transform:scale(1);    box-shadow:0 0 10px rgba(255,80,80,0.18); }
}

#bracketWrap  { width:99%; margin:auto; height:80vh; position:relative; overflow:hidden; border-radius:3px; background-color: #000; border:1px solid #2a2f2c; }
#bracketInner { position:absolute; left:0; top:0; transform-origin:0 0; will-change:transform; cursor:grab; touch-action:none; }

@media(max-width:420px) { .cover { height:0px; } .title { font-size:1.25rem; } }
</style>

<main id="app-root" class="container" role="main">

<div class="header-card" <?php if ($isSingleMatchTournament) { ?> style="margin-bottom: 30px;" <?php } ?>>
    	
		<div class="cover" <?php if ($awardsAssigned || $winnerId) { echo 'style="width:140px;"'; } ?> >
		  <img src="<?php echo h($imageSrcSafe); ?>">
		</div>
		
 
        <div class="info">
          <?php if (!empty($gameName)): ?>
            <div class="game-name"><?php echo h($gameName); ?></div>
          <?php endif; ?>
          <div class="title-row">
            <h1 class="title"><?php echo h($t['name']); ?></h1>
          </div>

		  <?php if ($awardsAssigned || $winnerId): ?>
          <div style="color:#9fb0bf; font-size:14px; font-weight:700;">
            Created By
            <?php if ($creatorId > 0): ?>
              <a href="<?php echo h($creatorProfile); ?>" style="color:#ffeb3b; text-decoration:none; font-weight:800;">
                <?php echo renderStyledUsername($creatorUsername, $creatorStyleJson); ?>
              </a>
            <?php endif; ?>
          </div>
		  <?php endif; ?>

          <div class="meta" style="">
            <div>
              <span class="local-dt" data-ts="<?php echo intval($start_ts); ?>">
                <?php echo $start_ts ? date("M j, Y H:i", $start_ts) . " UTC" : "—"; ?>
              </span>
            </div>
          </div>
		  
		  <div class="status-card">
		    <div class="status-card-top">
			  
			  <div class="status-main">
			    <div class="badge <?php echo $statusClass; ?>" id="statusBadge"><?php echo h($statusLabel); ?></div>
			    <div class="status-subtitle" id="statusSubtitle"><?php echo h($statusSub); ?></div>
			  </div>

			  <div class="status-countdown" id="startCountdownWrap" style="<?php echo ($start_ts > 0 && $now < $start_ts && $awardsAssigned == 0) ? '' : 'display:none;'; ?>">
			    <span id="startCountdownText">--</span>
			  </div>

			  <div style="display:inline-flex; align-items:center; gap:6px; padding:5px 14px; border-radius:20px; background:<?php echo $regBg; ?>; border:1px solid <?php echo $regColor; ?>33; font-size:0.82rem; font-weight:600; color:<?php echo $regColor; ?>;">
			    <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:<?php echo $regDot; ?>;"></span>
			    <?php echo h($regLabel); ?>
			  </div>
			  
		    </div>
		  </div>
          
          <?php if ($showJoin): ?>
		  <div class="action-buttons">
			<?php if (!$loggedIn): ?>
			<button class="btn btn-join join-action" data-href="../auth.php">Join</button>
			<?php elseif ($isJoined): ?>
			<button class="btn btn-joined" disabled>Joined</button>
			<?php else: ?>
			<button class="btn btn-join join-action" data-href="competitions/join.php?tID=<?php echo $tID; ?>">Join</button>
			<?php endif; ?>
			<?php if ($isTournOwner): ?>
            <div style="display:flex; flex-wrap:wrap; gap:8px">
              <button class="btn btn-edit manage-action" data-href="competitions/edit.php?tID=<?php echo $tID; ?>">Manage</button>
              <button id="deleteButton" class="btn btn-delete">Delete</button>
            </div>
            <?php endif; ?>  
		  </div>
          <?php endif; ?>

          <script>
            document.querySelectorAll(".join-action").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    window.location.href = this.getAttribute("data-href");
                });
            });

            document.querySelectorAll(".manage-action").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    window.location.href = this.getAttribute("data-href");
                });
            });
          </script>
		  
        </div>
		
    
</div>

<div class="terms_wrap">
  <?php if (!$isSingleMatchTournament): ?>
  <nav class="tabs_list">
    <ul>
      <li data-tc="tab_item_1" class="active2">OVERVIEW</li>
      <li data-tc="tab_item_2">BRACKETS</li>
      <li data-tc="tab_item_3">MATCHES</li>
    </ul>
  </nav>
  <?php endif; ?>

  <div class="tabs_content">
    <div class="tab_body">

<div class="tab_item tab_item_1 active">
  <div class="overview-grid<?php echo $isSingleMatchTournament ? ' single-match-overview' : ''; ?>" style="margin-top:0;">

    <div class="left-column">
      <div class="cards">
        <div class="card"><div class="k">Server</div><div class="v"><span style=""><?php echo h($t['server']); ?></span></div></div>
		<div class="card"><div class="k">Players</div><div class="v"><?php echo $currentPlayers . ' / ' . $totalSlots; ?></div></div>
		<div class="card"><div class="k">Players Per Team</div><div class="v"><?php echo h($playersPerTeam); ?></div></div>
		
		<?php if ($isSingleMatchTournament): ?>
		  <div class="card"><div class="k">Prize</div><div class="v"><?php echo formatPrize($prizeTotal, 100) . ' ' . $currency; ?></div></div>
		<?php else: ?>
		  <div class="card"><div class="k">Bracket Type</div><div class="v"><?php echo h($dispEliminations); ?></div></div>
		<?php endif; ?>
        
		<!-- <div class="card"><div class="k">Seed Type</div><div class="v"><?php // echo h($dispSeedType); ?></div></div> -->
      </div>
      
      <?php if ($isSingleMatchTournament): ?>
        <?php
        echo renderMatchCard(
            $singleMatch,
            $now,
            $playersPerTeam,
            $bTeams,
            $bTeamPlayers,
            $bMembers,
            $MAIN_ROOT,
            $tID,
            $loggedIn,
            $isTournOwner,
            $isAdmin
        );
        ?>
    <?php endif; ?>

     <?php if (!$isSingleMatchTournament): ?>
      
      <div style="margin-bottom:0px; padding:12px; border-radius:10px; border:1px solid #2a2f2c;">
		<h3 style="margin:0 0 10px; padding:0px; font-size:18px; color:#fff;">Description</h3>
		
		<div class="desc-text clamped" id="descText">
            
			<?php 
			if (!empty($t['description'])) {
				
				$lines = explode("\n", str_replace("\r", "", trim($t['description'])));
				
				echo '<ul style="list-style:none; padding:0; margin:0;">';
				
                if (!$awardsAssigned && !$winnerId && $creatorId > 0):
                	echo '<li style="position:relative; padding-left:18px; margin-bottom:8px; color:#dbeafe; font-weight:600; font-size:14px;">';
					echo '<span style="position:absolute; left:0; top:0; color:#22c55e; font-size:18px; line-height:1;">•</span>';
					
                    echo '<div style="color:#9fb0bf; font-size:14px; font-weight:700;">
                      Created By
                    ';
                	echo '<a href="' . h($creatorProfile) . '" style="color:#ffeb3b; text-decoration:none; font-weight:800;">
                      ' . renderStyledUsername($creatorUsername, $creatorStyleJson) . '
                    </a>';
                	echo '</div>';
                	echo '</li>';
                endif;
                
                if ($playersPerTeam > 1):
                	echo '<li style="position:relative; padding-left:18px; margin-bottom:8px; color:#dbeafe; font-weight:600; font-size:14px;">';
					echo '<span style="position:absolute; left:0; top:0; color:#22c55e; font-size:18px; line-height:1;">•</span>';
                	echo '<div style="margin: 10px 0; line-height: 1.5;">
    					<strong style="color: #f59e0b;">Note:</strong> This is a team-based tournament. You can create your own team to get an invite code for your teammates, or use an invite code provided by a teammate to join an existing team.
						</div>';
                	echo '</li>';
                endif;
                
				$lines = array_values(array_filter($lines, function($line) {
					return trim($line) !== '';
				}));

				$lastIndex = count($lines) - 1;

				foreach ($lines as $index => $line) {
					$marginBottom = ($index === $lastIndex) ? '0' : '8px';

					echo '<li style="position:relative; padding-left:18px; margin-bottom:' . $marginBottom . '; color:#dbeafe; font-weight:600; font-size:14px;">';
					echo '<span style="position:absolute; left:0; top:0; color:#22c55e; font-size:18px; line-height:1;">•</span>';
					echo htmlspecialchars($line);
					echo '</li>';
				}
                
                if ($playersPerTeam > 1):
                	echo '<li style="position:relative; padding-left:18px; margin-bottom:8px; color:#dbeafe; font-weight:600; font-size:14px;">';
					echo '<span style="position:absolute; left:0; top:0; color:#22c55e; font-size:18px; line-height:1;">•</span>';
                	echo '<div style="margin: 10px 0; line-height: 1.5;">
    					<strong style="color: #f44336;">Teams have a 30-minute grace period after their scheduled match time. If no players from a team show up within 30 minutes, the team will be disqualified</strong>
						</div>';
                	echo '</li>';
                endif;
                
                echo '<li style="position:relative; padding-left:18px; margin-bottom:8px; color:#dbeafe; font-weight:600; font-size:14px;">';
				echo '<span style="position:absolute; left:0; top:0; color:#22c55e; font-size:18px; line-height:1;">•</span>';
                echo '<div style="margin: 10px 0; line-height: 1.5;"><b style="color: #ffeb3b;">Good Luck Have Fun</b></div>';
                echo '</li>';
				
				echo '</ul>';
			} else {
				echo 'None';
			}
			?>
		</div>

		<div style="text-align:right;">
			<button id="descToggle" class="desc-toggle" aria-expanded="false">Read more</button>
		</div>
	  </div>
	<?php endif; ?> 
        
    </div>

    <div class="right-column">

      <?php if (!$isSingleMatchTournament): ?>
      <div style="padding:12px;border-radius:10px;border:1px solid #2a2f2c;margin-bottom:30px;">
		<h3 style="margin:0 0 10px;padding:0px;font-size:18px;color:#fff;">Prize / Standings</h3>

		<div style="display:flex;gap:15px;flex-direction:column;">
		  <?php
		  $prizes = [
			1 => ['label' => '1st Place', 'color' => '#f1b132', 'dot' => '#f1b132', 'pct' => 60],
			2 => ['label' => '2nd Place', 'color' => '#d6d1d5', 'dot' => '#d6d1d5', 'pct' => 40],
		  ];

		  foreach ($prizes as $placeNum => $p):
			$teamId = intval($standings[$placeNum] ?? 0);
		  ?>
			<div style="font-weight:700;">
			  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
				<div style="display:flex;align-items:center;gap:8px;">
				  <div style="width:16px;height:16px;border-radius:50%;background:<?php echo $p['dot']; ?>;"></div>
				  <span style="color:<?php echo $p['color']; ?>"><?php echo $p['label']; ?></span>
				</div>

				<div style="color:#ffeb3b;font-weight:800;font-size:15px;">
				  <?php echo formatPrize($prizeTotal, $p['pct']); ?> <?php echo $currency; ?>
				</div>
			  </div>

			  <div style="margin-top:5px;border-top:1px solid rgba(255,255,255,0.06);">
				<?php echo standingMembersHtml($teamId, $bTeamPlayers, $bMembers, $MAIN_ROOT); ?>
			  </div>
			</div>
		  <?php endforeach; ?>
		</div>
	  </div>
      <?php endif; ?>  

     <?php if (!$isSingleMatchTournament): ?> 
      <div class="part-box">
        <div class="part-row">
          <div style="font-weight:800;color:#fff;font-size:18px;">Participants</div>
		  <div style="color:var(--muted);font-weight:bold;"><?php echo $currentPlayers; ?> / <?php echo $totalSlots; ?></div>
        </div>
        <div class="part-grid collapsed" id="participantGrid">
          <?php echo $dispMemberListHtml ?: '<div style="color:var(--muted);padding:6px">No participants yet.</div>'; ?>
        </div>
        <div style="display:flex;justify-content:flex-end;">
          <button id="participantsToggle" class="desc-toggle" style="display:none;" aria-expanded="false">Show more</button>
        </div>
      </div>
     <?php endif; ?>  

    </div>
  </div>
</div>

<?php if (!$isSingleMatchTournament): ?>

<div class="tab_item tab_item_2" style="display:none;">
  <div class="tournamentBracket" style="position:relative;min-height:500px;">
  <?php
  if (intval($t['seedtype']) === 3):
    echo "<div style='color:var(--muted);padding:20px'>Bracket not available for pool-seeded tournaments.</div>";
  elseif (empty($allMatches)):
    echo "<div style='color:var(--muted);padding:20px'>No matches created yet.</div>";
  else:
    $bLeft             = 50;
    $bTop              = 80;
    $bTopOriginal      = $bTop;
    $bSlotWidth        = 200;
    $bSlotHeight       = 45 + (($playersPerTeam - 1) * 28);
    $bTeamSep          = 40;
    $bMatchDist        = ($bSlotHeight * 2) + $bTeamSep;
    $bWidthMakeUp      = 6;
    $bCurrentRound     = -1;
    $bMatchCount       = 0;
    $bConnectorTop     = 0;

    foreach ($allMatches as $match):
      $mRound   = intval($match['round']);
      $mOutcome = intval($match['outcome'] ?? 0);

      if ($mRound !== $bCurrentRound):
        $bMatchCount   = 0;
        if ($bCurrentRound !== -1) $bLeft += $bSlotWidth + ($bTeamSep * 4);
        $bCurrentRound = $mRound;
        if ($mRound === 1) {
          $bTop = $bTopOriginal;
        } else {
          $multiplier = pow(2, $mRound - 1) - 1;
          $bTop = $bTopOriginal + ($bMatchDist * $multiplier);
        }
      endif;
      $bMatchCount++;

      echo "<div style='position:absolute;top:20px;left:" . ($bLeft + ($bSlotWidth / 3)) . "px;height:{$bSlotHeight}px'>";
      echo "<p style='font-size:13px;background:#607d8b;padding:3px 10px;border-radius:5px;display:inline-block;width:100px;text-align:center;'>Round {$mRound}</p>";
      echo "</div>";

      for ($slot = 1; $slot <= 2; $slot++):
        $teamIdCol  = "team{$slot}_id";
        $teamId     = intval($match[$teamIdCol] ?? 0);

        $td         = bracketTeamDisplay($teamId, $playersPerTeam, $bTeamPlayers, $bMembers, $MAIN_ROOT);
        $score      = ($mOutcome === 1 || $mOutcome === 2) ? $match["team{$slot}score"] : '';
        $scoreVal   = is_numeric($score) ? intval($score) : h((string)$score);
        $scoreColor = '';
        if ($mOutcome === 1) $scoreColor = ($slot === 1) ? '#0bf115' : '#ff6b6b';
        if ($mOutcome === 2) $scoreColor = ($slot === 2) ? '#0bf115' : '#ff6b6b';
        $scoreStyle = $scoreColor ? "font-size:15px;font-weight:bold;color:{$scoreColor};" : '';

        echo "
        <div class='bracket' style='width:{$bSlotWidth}px;height:{$bSlotHeight}px;line-height:{$bSlotHeight}px;left:{$bLeft}px;top:{$bTop}px;border-radius:5px;'>
          <div style='display:flex;align-items:center;padding:4px 6px;box-sizing:border-box;width:100%;height:100%;overflow:hidden;'>
            {$td['html']}
          </div>
        </div>
        <div class='score' style='{$scoreStyle}line-height:{$bSlotHeight}px;height:{$bSlotHeight}px;left:" . ($bLeft + $bSlotWidth + $bWidthMakeUp - 30) . "px;top:" . ($bTop + 4) . "px'>
          {$scoreVal}
        </div>";

        if ($slot === 1):
          $dashTop = ($bMatchCount % 2 == 0)
            ? $bTop + $bSlotHeight + ($bTeamSep / 2)
            : $bTop + $bSlotHeight + ($bTeamSep / 2) + 2;

          echo "<div class='bracketConnector2' style='left:" . ($bLeft + $bSlotWidth + 8) . "px;top:" . ($bTop + ($bSlotHeight / 2) + 2) . "px;width:57px;height:" . ($bSlotHeight + $bTeamSep) . "px;'></div>";

          if ($bMatchCount % 2 != 0) $bConnectorTop = $bTop + $bSlotHeight + ($bTeamSep / 2);

          $bTop += $bTeamSep + $bSlotHeight;
        endif;
      endfor;

      $bConnLeft = $bLeft + $bSlotWidth + $bWidthMakeUp + 60;
      switch ($mRound) {
        case 1: $bTop += $bSlotHeight + $bMatchDist;           $bConnH = ($bSlotHeight * 2) + $bMatchDist + $bTeamSep;           break;
        case 2: $bTop += $bSlotHeight + ($bMatchDist * 3);     $bConnH = ($bSlotHeight * 2) + ($bMatchDist * 3) + $bTeamSep;     break;
        case 3: $bTop += $bSlotHeight + ($bMatchDist * 7);     $bConnH = ($bSlotHeight * 2) + ($bMatchDist * 7) + $bTeamSep;     break;
        case 4: $bTop += $bSlotHeight + ($bMatchDist * 15);    $bConnH = ($bSlotHeight * 2) + ($bMatchDist * 15) + $bTeamSep;    break;
        case 5: $bTop += $bSlotHeight + ($bMatchDist * 31);    $bConnH = ($bSlotHeight * 2) + ($bMatchDist * 31) + $bTeamSep;    break;
        default: $bTop += $bSlotHeight + $bMatchDist;          $bConnH = ($bSlotHeight * 2) + $bMatchDist + $bTeamSep;
      }

      if ($bMatchCount % 2 == 0):
        echo "<div class='bracketConnector' style='left:{$bConnLeft}px; top:" . ($bConnectorTop + 3) . "px; width:50px; height:" . ($bConnH) . "px'></div>";
        echo "<div class='bracketConnectorDash' style='left:" . ($bConnLeft + 50) . "px;top:" . ($bConnectorTop + ($bConnH / 2) + 4) . "px'></div>";
      endif;

    endforeach;

    $bLeft += $bSlotWidth + ($bTeamSep * 3);
    $bTop   = $bTopOriginal + ($bSlotHeight * 2) + $bTeamSep + ($bSlotHeight / 2) + ($bTeamSep / 2) - 2;
    switch ($bCurrentRound) {
      case 3: $bTop += $bMatchDist * 2;  break;
      case 4: $bTop += $bMatchDist * 6;  break;
      case 5: $bTop += $bMatchDist * 14; break;
      case 6: $bTop += $bMatchDist * 30; break;
    }

    $champDisplay = ['html' => "<div style='display:flex;align-items:center;gap:6px;color:#dbeaf6;font-weight:700;'>-</div>"];
	if ($winnerId) {
	  $champDisplay = bracketTeamDisplay($winnerId, $playersPerTeam, $bTeamPlayers, $bMembers, $MAIN_ROOT);
	}

	echo "
	<div class='bracketConnectorDash' style='left: " . ($bLeft - 53) . "px; top: " . ($dashTop) . "px'></div>
	<div class='bracket' style='width:{$bSlotWidth}px;height:{$bSlotHeight}px;left:{$bLeft}px;top:{$bTop}px;border-radius:5px;'>
	  <div style='display:flex;align-items:center;padding:4px 6px;box-sizing:border-box;width:100%;height:100%;overflow:hidden;'>
		{$champDisplay['html']}
	  </div>
	</div>";
  endif; ?>
  </div>
</div>

<script>
(function(){
  var original = document.querySelector('.tournamentBracket');
  if (!original) return;
  var bracketWrap = document.createElement('div'); bracketWrap.id = 'bracketWrap';
  var bracketInner = document.createElement('div'); bracketInner.id = 'bracketInner';
  original.parentElement.replaceChild(bracketWrap, original);
  bracketWrap.appendChild(bracketInner);
  bracketInner.appendChild(original);
  bracketWrap.setAttribute('tabindex','0');
  bracketWrap.style.outline = 'none';

  var scale = 1, tx = 0, ty = 0;
  function applyT() { bracketInner.style.transform = 'translate('+tx+'px,'+ty+'px) scale('+scale+')'; }
  function clamp(s) { return Math.min(5, Math.max(0.2, s)); }

  var pointers = new Map(), isPanning = false, lastX = 0, lastY = 0;
  var pinchDist0 = 0, pinchScale0 = 1;

  bracketWrap.addEventListener('pointerdown', function(e){
    if (e.target.closest('a,button,input')) return;
    bracketWrap.setPointerCapture && bracketWrap.setPointerCapture(e.pointerId);
    pointers.set(e.pointerId, {x:e.clientX, y:e.clientY});
    if (pointers.size === 1) { isPanning = true; lastX = e.clientX; lastY = e.clientY; bracketWrap.style.cursor = 'grabbing'; }
    else if (pointers.size === 2) {
      var it = pointers.values(), p1 = it.next().value, p2 = it.next().value;
      pinchDist0  = Math.hypot(p2.x-p1.x, p2.y-p1.y);
      pinchScale0 = scale;
    }
  });

  bracketWrap.addEventListener('pointermove', function(e){
    if (!pointers.has(e.pointerId)) return;
    pointers.set(e.pointerId, {x:e.clientX, y:e.clientY});
    if (pointers.size === 1 && isPanning) {
      tx += e.clientX - lastX; ty += e.clientY - lastY;
      lastX = e.clientX; lastY = e.clientY; applyT();
    } else if (pointers.size === 2) {
      var arr = Array.from(pointers.values());
      var d   = Math.hypot(arr[1].x-arr[0].x, arr[1].y-arr[0].y);
      if (!pinchDist0) return;
      var ns  = clamp(pinchScale0 * d / pinchDist0);
      var rect = bracketWrap.getBoundingClientRect();
      var mx  = (arr[0].x + arr[1].x) / 2 - rect.left;
      var my  = (arr[0].y + arr[1].y) / 2 - rect.top;
      tx = mx - ((mx - tx) / scale) * ns;
      ty = my - ((my - ty) / scale) * ns;
      scale = ns; applyT();
    }
  });

  function ptrUp(e) {
    pointers.delete(e.pointerId);
    if (pointers.size === 0) { isPanning = false; bracketWrap.style.cursor = 'grab'; pinchDist0 = 0; }
    else if (pointers.size === 1) { var r = pointers.values().next().value; lastX = r.x; lastY = r.y; isPanning = true; }
    bracketWrap.releasePointerCapture && bracketWrap.releasePointerCapture(e.pointerId);
  }
  ['pointerup','pointercancel','pointerleave'].forEach(function(ev){ bracketWrap.addEventListener(ev, ptrUp); });

  bracketWrap.addEventListener('wheel', function(e){
    e.preventDefault();
    var rect = bracketWrap.getBoundingClientRect();
    var mx = e.clientX - rect.left, my = e.clientY - rect.top;
    var cx = (mx - tx) / scale, cy = (my - ty) / scale;
    var ns = clamp(scale * (e.deltaY > 0 ? 0.9 : 1.1));
    tx = mx - cx * ns; ty = my - cy * ns; scale = ns; applyT();
  }, {passive:false});

  window.addEventListener('keydown', function(e){
    if (document.activeElement !== bracketWrap) return;
    if (e.key === 'ArrowLeft')  { tx += 80; applyT(); e.preventDefault(); }
    if (e.key === 'ArrowRight') { tx -= 80; applyT(); e.preventDefault(); }
    if (e.key === 'ArrowUp')    { ty += 80; applyT(); e.preventDefault(); }
    if (e.key === 'ArrowDown')  { ty -= 80; applyT(); e.preventDefault(); }
    if (e.key === '0') { scale = 1; tx = 0; ty = 0; applyT(); e.preventDefault(); }
  });

  applyT();
})();
</script>

<div class="tab_item tab_item_3" style="display:none;">
<?php if (intval($t['seedtype']) === 3): ?>
  <div style="color:var(--muted);padding:20px;">Matches are not available for pool-seeded tournaments.</div>
<?php elseif (empty($matchesByRound)): ?>
  <div style="color:var(--muted);padding:20px;">No matches scheduled yet.</div>
<?php else: ?>

<div style="padding:5px;"><div class="roundsDiv">

<?php foreach ($matchesByRound as $roundNum => $roundMatches): ?>

  <div style="margin-bottom:70px;" class="matchsDiv">
    <div style="position:relative;display:flex;align-items:center;justify-content:center;height:50px;margin:25px 0;">
      <div style="position:absolute;top:50%;transform:translateY(-50%);border-top:2px solid #607d8b;width:100%;"></div>
      <div style="position:relative;z-index:2;background:linear-gradient(135deg,#607d8b,#607d8b);padding:8px 20px;border-radius:12px;border:2px solid rgba(255,255,255,0.2);">
        <p style="font-size:18px;font-weight:700;color:#fff;margin:0;">Round <?php echo $roundNum; ?></p>
      </div>
    </div>

    <?php foreach ($roundMatches as $match): ?>
      <?php echo renderMatchCard($match, $now, $playersPerTeam, $bTeams, $bTeamPlayers, $bMembers, $MAIN_ROOT, $tID, $loggedIn, $isTournOwner, $isAdmin); ?>
    <?php endforeach; ?>
  </div>

<?php endforeach; ?>

</div></div>

<?php endif; ?>
</div>

<?php endif; ?>

    </div>
  </div>
</div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

  (function(){
    var lists = document.querySelectorAll('.tabs_list ul li');
    var items = document.querySelectorAll('.tab_item');
    if (!lists.length) return;
    lists.forEach(function(li){
      li.addEventListener('click', function(){
        var tc = li.getAttribute('data-tc');
        lists.forEach(function(l){ l.classList.remove('active2'); });
        li.classList.add('active2');
        items.forEach(function(item){
          item.style.display = item.classList.contains(tc) ? 'block' : 'none';
          item.classList.toggle('active', item.classList.contains(tc));
        });
      });
    });
    var active = document.querySelector('.tabs_list ul li.active2') || lists[0];
    if (active) active.click();
  })();

  (function(){
    try {
      var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      document.querySelectorAll('.local-dt[data-ts]').forEach(function(el){
        var ts = Number(el.getAttribute('data-ts')) * 1000;
        if (!ts) return;
        var fmt = new Intl.DateTimeFormat(undefined, {year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit',hour12:false,timeZone:tz,timeZoneName:'short'});
        el.textContent = fmt.format(new Date(ts)).replace('GMT','UTC');
      });
    } catch(e){}
  })();

  (function(){
    function pad(n){ return String(n).padStart(2,'0'); }
    function bd(ms){ if(ms<=0) return {d:0,h:0,m:0,s:0}; var s=Math.floor(ms/1000); return {d:Math.floor(s/86400),h:Math.floor((s%86400)/3600),m:Math.floor((s%3600)/60),s:s%60}; }
    function fmt(b){ var p=[]; if(b.d) p.push(b.d+'d'); p.push(pad(b.h)+'h',pad(b.m)+'m',pad(b.s)+'s'); return p.join(' '); }
    function toMs(v){ v=Number(v)||0; return v>1e11?v:v*1000; }

    function update(){
      var now = Date.now();
      document.querySelectorAll('.countdown').forEach(function(wrap){
        var startMs = toMs(wrap.dataset.startTs || wrap.getAttribute('data-start-ts') || 0);
        var state   = (wrap.dataset.state || wrap.getAttribute('data-state') || '').toLowerCase();
        if (!startMs) { wrap.innerHTML = "<span style='color:#777'>Not scheduled</span>"; return; }

        if (state === 'upcoming' && now < startMs) {
          wrap.innerHTML = "<span class='countdown-glow'>" + fmt(bd(startMs - now)) + "</span>";
          return;
        }
        if ((state === 'live' || now >= startMs) && state !== 'finished') {
		  wrap.innerHTML = "<div class='live-pill'><span class='ongoing-dot'></span>LIVE</div>";
		  return;
		}
        if (state === 'finished') { wrap.innerHTML = "<span style='color:#6b7280;font-weight:700'>Finished</span>"; }
      });
    }
    update();
    setInterval(update, 1000);
  })();

  (function(){
    var btn = document.getElementById('deleteButton');
    if (!btn) return;
    btn.addEventListener('click', function(){
      if (!confirm('Confirm tournament deletion?')) return;
      var fd = new FormData();
      fd.append('tID', '<?php echo $tID; ?>');
      fd.append('CSRF_TOKKEN', <?php echo json_encode($_SESSION['csrftokken'] ?? ''); ?>);
      fetch('<?php echo $MAIN_ROOT; ?>competitions/backend/delete_tournament.php', {method:'POST',body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d){ if(d.status==='success') location.href='<?php echo $MAIN_ROOT; ?>competitions/'; else alert('Error: '+(d.message||'unknown')); })
        .catch(function(){ alert('Network error'); });
    });
  })();

  (function(){
    var descText   = document.getElementById('descText');
    var descToggle = document.getElementById('descToggle');
    if (descText && descToggle) {
      if (descText.scrollHeight <= descText.clientHeight + 12) {
        descToggle.style.display = 'none';
      } else {
        descToggle.addEventListener('click', function(){
          var c = descText.classList.toggle('clamped');
          descToggle.textContent     = c ? 'Read more' : 'Show less';
          descToggle.setAttribute('aria-expanded', c ? 'false' : 'true');
        });
      }
    }

    var pGrid   = document.getElementById('participantGrid');
    var pToggle = document.getElementById('participantsToggle');
    if (pGrid && pToggle && pGrid.querySelectorAll('.participant').length > 8) {
      pToggle.style.display = 'inline-flex';
      pToggle.textContent   = 'Show all';
      pToggle.addEventListener('click', function(){
        var c = pGrid.classList.toggle('collapsed');
        pToggle.textContent = c ? 'Show all' : 'Show less';
        pToggle.setAttribute('aria-expanded', c ? 'false' : 'true');
      });
    }
  })();

});
</script>

<script>
(function(){
  var startIso    = <?php echo json_encode($start_iso); ?>;
  var initStatus  = <?php echo json_encode(strtolower($statusLabel)); ?>;
  var statusTitle = document.getElementById('statusTitle');
  var statusSub   = document.getElementById('statusSubtitle');
  var statusBadge = document.getElementById('statusBadge');
  var countdownWrap = document.getElementById('startCountdownWrap');
  var countdownText = document.getElementById('startCountdownText');

  var awards           = <?php echo intval($awardsAssigned); ?>;
  var startDate        = startIso ? new Date(startIso) : null;

  function pad(n){ return n < 10 ? '0' + n : '' + n; }
  function diffBits(ms){
    if (!ms || ms <= 0) return {d:0,h:0,m:0,s:0};
    var s = Math.floor(ms / 1000);
    return {
      d: Math.floor(s / 86400),
      h: Math.floor((s % 86400) / 3600),
      m: Math.floor((s % 3600) / 60),
      s: s % 60
    };
  }
  function diffText(ms){
    var b = diffBits(ms);
    var out = [];
    if (b.d) out.push(b.d + 'd');
    out.push(pad(b.h) + 'h');
    out.push(pad(b.m) + 'm');
    out.push(pad(b.s) + 's');
    return out.join(' ');
  }
  function setStatus(label, cls, sub) {
    if (statusSub) statusSub.textContent = sub;
    if (statusBadge) {
      statusBadge.className = 'badge ' + cls;
      if (cls === 'ongoing') {
        statusBadge.innerHTML = '<span class="ongoing-dot"></span><span>' + label + '</span>';
      } else {
        statusBadge.textContent = label;
      }
    }
  }
  function tick(){
    var now = new Date();

    if (awards) {
      setStatus('Finished', 'finished', 'Tournament is over');
      if (countdownWrap) countdownWrap.style.display = 'none';
      return;
    }

    if (startDate && now < startDate) {
      setStatus('Upcoming', 'upcoming', 'Starts soon');
      if (countdownWrap && countdownText) {
        countdownWrap.style.display = 'inline-block';
        countdownText.textContent = diffText(startDate - now);
      }
      return;
    }

    setStatus('Live', 'ongoing', 'Tournament in progress');
    if (countdownWrap) countdownWrap.style.display = 'none';
  }

  tick();
  setInterval(tick, 1000);
})();
</script>

<?php include($prevFolder . "assets/_footer.php"); ?>