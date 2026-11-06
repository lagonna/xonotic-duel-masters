<?php
header('Content-Type: application/json; charset=utf-8');

try {
    include_once("../../_intro.php");
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new Exception('Database connection ($mysqli) missing from _intro.php');
    }
    if (!isset($dbprefix)) $dbprefix = '';
    if (!defined('MAIN_ROOT')) define('MAIN_ROOT', '/');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server initialization error']);
    exit;
}

function ok_json($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}
function err_json($msg = 'Error', $code = 400, $extra = null) {
    http_response_code($code);
    $out = ['success' => false, 'message' => $msg];
    if ($extra !== null) $out['extra'] = $extra;
    echo json_encode($out);
    exit;
}
function public_url_for($path) {
    if (empty($path)) return '';
    $path = (string)$path;
    if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) return $path;
    $root = rtrim(MAIN_ROOT, '/');
    $p = ltrim($path, '/');
    return $root . '/' . $p;
}

function is_one_v_one_tournament($mysqli, $dbprefix, $tID) {
    $q = $mysqli->prepare("
        SELECT t.maxteams, t.seedtype,
               (SELECT COUNT(*) FROM {$dbprefix}tournamentmatch m WHERE m.tournament_id = t.tournament_id) AS match_count
        FROM {$dbprefix}tournaments t
        WHERE t.tournament_id = ? LIMIT 1
    ");
    if (!$q) return false;
    $q->bind_param('i', $tID);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$row) return false;

    return max(2, intval($row['maxteams'])) === 2
        && intval($row['seedtype']) !== 3
        && intval($row['match_count']) === 1;
}

function award_tournament_winner(&$mysqli, $dbprefix, $tID, $finalMatchId, $outcome, $managerMemberId, $gameThumbRel) {
    $tsql = "SELECT tournament_id, name, t_prize, awards_assigned";
    $tsql .= ", t_prize_currency";
    $tsql .= " FROM {$dbprefix}tournaments WHERE tournament_id = ? FOR UPDATE";
    $tstmt = $mysqli->prepare($tsql);
    if (!$tstmt) throw new Exception('Prepare tournaments failed: ' . $mysqli->error);
    $tstmt->bind_param('i', $tID);
    $tstmt->execute();
    $trow = $tstmt->get_result()->fetch_assoc();
    $tstmt->close();
    if (!$trow) throw new Exception('Tournament not found');

    if (intval($trow['awards_assigned']) === 1) {
        return ['ok' => false, 'msg' => 'Awards already assigned'];
    }

    $msql = "SELECT tournamentmatch_id, team1_id, team2_id, nextmatch_id FROM {$dbprefix}tournamentmatch WHERE tournamentmatch_id = ? FOR UPDATE";
    $mstmt = $mysqli->prepare($msql);
    if (!$mstmt) throw new Exception('Prepare match failed: ' . $mysqli->error);
    $mstmt->bind_param('i', $finalMatchId);
    $mstmt->execute();
    $mrow = $mstmt->get_result()->fetch_assoc();
    $mstmt->close();
    if (!$mrow) throw new Exception('Final match not found');
    if (intval($mrow['nextmatch_id']) !== 0) throw new Exception('Match is not final');

    $winnerTeamId = ($outcome === 1) ? intval($mrow['team1_id']) : intval($mrow['team2_id']);
    if ($winnerTeamId <= 0) throw new Exception('Invalid winner team id');

    $psql = "SELECT member_id FROM {$dbprefix}tournamentplayers WHERE tournament_id = ? AND team_id = ?";
    $pstmt = $mysqli->prepare($psql);
    if (!$pstmt) throw new Exception('Prepare players failed: ' . $mysqli->error);
    $pstmt->bind_param('ii', $tID, $winnerTeamId);
    $pstmt->execute();
    $pres = $pstmt->get_result();
    $winnerMembers = [];
    while ($prow = $pres->fetch_assoc()) $winnerMembers[] = intval($prow['member_id']);
    $pstmt->close();

    if (count($winnerMembers) === 0) {
        $now = date('Y-m-d H:i:s');
        $u = $mysqli->prepare("UPDATE {$dbprefix}tournaments SET awards_assigned = 1, awards_assigned_at = ?, awards_assigned_by = ? WHERE tournament_id = ? LIMIT 1");
        if (!$u) throw new Exception('Prepare update tournaments failed: ' . $mysqli->error);
        $u->bind_param('sii', $now, $managerMemberId, $tID);
        $u->execute();
        $u->close();
        return ['ok' => false, 'msg' => 'No players in winner team; marked awards_assigned'];
    }

    $walletResults = [];

    $isDuel = is_one_v_one_tournament($mysqli, $dbprefix, $tID);

    $totalPrize = (float) $trow['t_prize'];
    if ($totalPrize > 0.0) {
        $scale = 100;
        if ($isDuel) {
            $firstInt  = (int) round($totalPrize * $scale);
            $secondInt = 0;
        } else {
            $firstInt  = (int) round($totalPrize * 0.60 * $scale);
            $secondInt = (int) round($totalPrize * 0.40 * $scale);
        }

        $team1 = intval($mrow['team1_id']);
        $team2 = intval($mrow['team2_id']);
        $loserTeamId = ($winnerTeamId === $team1) ? $team2 : $team1;

        $loserMembers = [];
        if ($loserTeamId > 0) {
            $q = $mysqli->prepare("SELECT member_id FROM {$dbprefix}tournamentplayers WHERE tournament_id = ? AND team_id = ?");
            if ($q) {
                $q->bind_param('ii', $tID, $loserTeamId);
                $q->execute();
                $r = $q->get_result();
                while ($rr = $r->fetch_assoc()) $loserMembers[] = intval($rr['member_id']);
                $q->close();
            }
        }

        $splitAmong = function(int $amountInt, array $members) {
            $out = [];
            $n = count($members);
            if ($n === 0) return $out;
            $base = intdiv($amountInt, $n);
            $rem = $amountInt - ($base * $n);
            for ($i = 0; $i < $n; $i++) {
                $add = ($i < $rem) ? 1 : 0;
                $out[$members[$i]] = $base + $add;
            }
            return $out;
        };

        $firstSplit = $splitAmong($firstInt, $winnerMembers);
        $secondSplit = $isDuel ? [] : $splitAmong($secondInt, $loserMembers);

        $hasAutoInc = false;
        $colRes = $mysqli->query("SHOW COLUMNS FROM {$dbprefix}wallet_transactions LIKE 'transaction_id'");
        if ($colRes) {
            if ($col = $colRes->fetch_assoc()) {
                $extra = isset($col['Extra']) ? $col['Extra'] : '';
                $hasAutoInc = (stripos($extra, 'auto_increment') !== false);
            }
            $colRes->close();
        }

        if ($hasAutoInc) {
            $insTx = $mysqli->prepare("INSERT INTO {$dbprefix}wallet_transactions (member_id, transaction_type, amount, currency, status, description) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$insTx) throw new Exception('Prepare wallet_transactions insert failed: ' . $mysqli->error);
        } else {
            $nidRes = $mysqli->query("SELECT COALESCE(MAX(transaction_id),0)+1 AS nid FROM {$dbprefix}wallet_transactions");
            if (!$nidRes) throw new Exception('Failed determining next transaction_id: ' . $mysqli->error);
            $nidRow = $nidRes->fetch_assoc();
            $nextId = intval($nidRow['nid']);
            $nidRes->close();
            $insTx = $mysqli->prepare("INSERT INTO {$dbprefix}wallet_transactions (transaction_id, member_id, transaction_type, amount, currency, status, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$insTx) throw new Exception('Prepare wallet_transactions insert failed: ' . $mysqli->error);
        }

        $insAct = $mysqli->prepare("INSERT INTO {$dbprefix}user_achievements (member_id, achievement_type, related_id, description, image_url, extra, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if (!$insAct) throw new Exception('Prepare user_achievements failed: ' . $mysqli->error);

        $insNotif = $mysqli->prepare("INSERT INTO {$dbprefix}notifications (`type`,`target_user_id`,`payload`,`notif_sig`) VALUES (?, ?, ?, ?)");
        if (!$insNotif) throw new Exception('Prepare notifications insert failed: ' . $mysqli->error);

        $prizeCurrency = (!empty($trow['t_prize_currency']) ? $trow['t_prize_currency'] : 'USD');
        $txType = 'prize';
        $status = 'completed';
        $tournamentNameEsc = $trow['name'];

        $fmt = function(int $cents) {
            return sprintf("%.2f", $cents / 100);
        };

        $goldImgUrl = '/assets/images/goldmedal1st.png';
        $silverImgUrl = '/assets/images/silvermedal2nd.png';

        $winnerDescTemplate = "<strong style='color:gold'>Won</strong> the <a href='competitions/competition.php?tID=%d'><strong>%s</strong></a>";
        $runnerDescTemplate = "Placed <strong style='color:gold'>2nd </strong> on the <a href='competitions/competition.php?tID=%d'><strong>%s</strong></a>";

        $notifType = 'match';
        $notifAvatar = !empty($gameThumbRel) ? '/' . ltrim($gameThumbRel, '/') : '/assets/images/notification.png';

        foreach ($firstSplit as $memId => $cents) {
            $amountStr = $fmt($cents);
            $txDesc = "Tournament prize (1st) - {$tournamentNameEsc}";

            if ($hasAutoInc) {
                $insTx->bind_param('isssss', $memId, $txType, $amountStr, $prizeCurrency, $status, $txDesc);
            } else {
                $curId = $nextId++;
                $insTx->bind_param('iisssss', $curId, $memId, $txType, $amountStr, $prizeCurrency, $status, $txDesc);
            }
            if (!$insTx->execute()) {
                throw new Exception('Failed inserting wallet transaction (1st): ' . $insTx->error);
            }
            $walletResults[$memId] = $amountStr;

            $atype = 'tournament_prize';
            $activityDesc = sprintf($winnerDescTemplate, (int)$tID, htmlspecialchars($tournamentNameEsc, ENT_QUOTES | ENT_SUBSTITUTE));
            $extraJson = json_encode(['amount' => $amountStr, 'currency' => $prizeCurrency, 'position' => '1st'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $insAct->bind_param('isisss', $memId, $atype, $tID, $activityDesc, $goldImgUrl, $extraJson);
            if (!$insAct->execute()) {
                error_log('user_achievements insert failed for payout (1st): ' . $insAct->error);
            }

            $subjectHtml = "<strong style='color:#4caf50'>Congratulations</strong> — you <strong style='color:gold'>Won</strong> the <a href='competitions/competition.php?tID={$tID}'><strong>" . htmlspecialchars($tournamentNameEsc, ENT_QUOTES | ENT_SUBSTITUTE) . "</strong></a>";
            $payloadObj = ['subject_html' => $subjectHtml, 'avatar_url' => $notifAvatar];
            $payloadJson = json_encode($payloadObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $notif_sig = hash('sha256', implode('|', ['match_award', $tID, 'member:'.$memId]));

            $chk = $mysqli->prepare("SELECT id FROM {$dbprefix}notifications WHERE notif_sig = ? LIMIT 1");
            $already = false;
            if ($chk) {
                $chk->bind_param('s', $notif_sig);
                $chk->execute();
                $cres = $chk->get_result();
                $already = ($cres && $cres->num_rows > 0);
                $chk->close();
            }
            if (!$already) {
                $insNotif->bind_param("siss", $notifType, $memId, $payloadJson, $notif_sig);
                if (!$insNotif->execute()) {
                    if ($mysqli->errno !== 1062) error_log("Failed inserting award notification: (" . $mysqli->errno . ") " . $mysqli->error);
                }
            }
        }

        foreach ($secondSplit as $memId => $cents) {
            $amountStr = $fmt($cents);
            $txDesc = "Tournament prize (2nd) - {$tournamentNameEsc}";

            if ($hasAutoInc) {
                $insTx->bind_param('isssss', $memId, $txType, $amountStr, $prizeCurrency, $status, $txDesc);
            } else {
                $curId = $nextId++;
                $insTx->bind_param('iisssss', $curId, $memId, $txType, $amountStr, $prizeCurrency, $status, $txDesc);
            }
            if (!$insTx->execute()) {
                throw new Exception('Failed inserting wallet transaction (2nd): ' . $insTx->error);
            }
            $walletResults[$memId] = $amountStr;

            $atype = 'tournament_prize';
            $activityDesc = sprintf($runnerDescTemplate, (int)$tID, htmlspecialchars($tournamentNameEsc, ENT_QUOTES | ENT_SUBSTITUTE));
            $extraJson = json_encode(['amount' => $amountStr, 'currency' => $prizeCurrency, 'position' => '2nd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $insAct->bind_param('isisss', $memId, $atype, $tID, $activityDesc, $silverImgUrl, $extraJson);
            if (!$insAct->execute()) {
                error_log('user_achievements insert failed for payout (2nd): ' . $insAct->error);
            }

            $subjectHtml = "<strong style='color:#4caf50'>Congratulations</strong> — you placed <strong style='color:gold'>2nd</strong> in the <a href='competitions/competition.php?tID={$tID}'><strong>" . htmlspecialchars($tournamentNameEsc, ENT_QUOTES | ENT_SUBSTITUTE) . "</strong></a>";
            $payloadObj = ['subject_html' => $subjectHtml, 'avatar_url' => $notifAvatar];
            $payloadJson = json_encode($payloadObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $notif_sig = hash('sha256', implode('|', ['match_award', $tID, 'member:'.$memId]));
            $chk = $mysqli->prepare("SELECT id FROM {$dbprefix}notifications WHERE notif_sig = ? LIMIT 1");
            $already = false;
            if ($chk) {
                $chk->bind_param('s', $notif_sig);
                $chk->execute();
                $cres = $chk->get_result();
                $already = ($cres && $cres->num_rows > 0);
                $chk->close();
            }
            if (!$already) {
                $insNotif->bind_param("siss", $notifType, $memId, $payloadJson, $notif_sig);
                if (!$insNotif->execute()) {
                    if ($mysqli->errno !== 1062) error_log("Failed inserting award notification: (" . $mysqli->errno . ") " . $mysqli->error);
                }
            }
        }

        $insTx->close();
        $insAct->close();
        $insNotif->close();
    }

    $now = date('Y-m-d H:i:s');
    $u2 = $mysqli->prepare("UPDATE {$dbprefix}tournaments SET awards_assigned = 1, awards_assigned_at = ?, awards_assigned_by = ? WHERE tournament_id = ? LIMIT 1");
    if (!$u2) throw new Exception('Prepare update tournaments failed: ' . $mysqli->error);
    $u2->bind_param('sii', $now, $managerMemberId, $tID);
    if (!$u2->execute()) throw new Exception('Failed update tournaments awards_assigned: ' . $u2->error);
    $u2->close();

    $ret = ['ok' => true, 'msg' => 'Awards assigned to winner team'];
    if (!empty($walletResults)) $ret['wallet_prizes'] = $walletResults;
    return $ret;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    err_json('Invalid HTTP method', 405);
}
if (!isset($_SESSION['user_id'])) err_json('Authentication required', 403);
$memberId = intval($_SESSION['user_id']);

$tID = isset($_REQUEST['tID']) && is_numeric($_REQUEST['tID']) ? intval($_REQUEST['tID']) : 0;
$mID = isset($_REQUEST['mID']) && is_numeric($_REQUEST['mID']) ? intval($_REQUEST['mID']) : 0;
if ($tID <= 0 || $mID <= 0) err_json('Missing tID or mID', 400);

$csrf_sent = $_POST['csrftokken'] ?? $_REQUEST['csrftokken'] ?? '';
if (empty($_SESSION['csrftokken']) || !hash_equals((string)$_SESSION['csrftokken'], (string)$csrf_sent)) {
    err_json('Invalid CSRF token', 403);
}

$matchTable = $dbprefix . "tournamentmatch";
$tournamentsTable = $dbprefix . "tournaments";
$playerTable = $dbprefix . "tournamentplayers";

$action = $_POST['action'] ?? ($_REQUEST['action'] ?? '');
if ($action !== 'manager') err_json('Only manager/admin action allowed', 400);

$txStarted = false;
if (method_exists($mysqli, 'begin_transaction')) {
    $mysqli->begin_transaction();
    $txStarted = true;
}

try {
    $stmt = $mysqli->prepare("SELECT * FROM {$matchTable} WHERE tournamentmatch_id = ? FOR UPDATE");
    if (!$stmt) throw new Exception('DB prepare failed (match): ' . $mysqli->error);
    $stmt->bind_param('i', $mID);
    $stmt->execute();
    $matchRes = $stmt->get_result();
    $match = $matchRes ? $matchRes->fetch_assoc() : null;
    $stmt->close();
    if (!$match) throw new Exception('Match not found');

    $stmt = $mysqli->prepare("SELECT * FROM {$tournamentsTable} WHERE tournament_id = ? FOR UPDATE");
    if (!$stmt) throw new Exception('DB prepare failed (tournament): ' . $mysqli->error);
    $stmt->bind_param('i', $tID);
    $stmt->execute();
    $tRes = $stmt->get_result();
    $tournament = $tRes ? $tRes->fetch_assoc() : null;
    $stmt->close();
    if (!$tournament) throw new Exception('Tournament not found');

    $gameThumbRel = null;
    if (!empty($tournament['gamesplayed_id'])) {
        $gstmt = $mysqli->prepare("SELECT thumburl FROM {$dbprefix}gamesplayed WHERE gamesplayed_id = ? LIMIT 1");
        if ($gstmt) {
            $gstmt->bind_param("i", $tournament['gamesplayed_id']);
            $gstmt->execute();
            $gres = $gstmt->get_result();
            if ($grow = $gres->fetch_assoc()) {
                $gameThumbRel = trim($grow['thumburl']) ?: null;
            }
            $gstmt->close();
        } else {
            error_log("match_action_admin prepare failed (fetch gamesplayed.thumburl): " . $mysqli->error);
        }
    }

    $is_manager = (intval($tournament['member_id']) === $memberId);
    if (!$is_manager) throw new Exception('Only tournament manager can perform this action');

    $winnerProvided = array_key_exists('winner', $_POST);
    $winner = $winnerProvided ? intval($_POST['winner']) : intval($match['outcome']);
    if (!in_array($winner, [0,1,2], true)) $winner = intval($match['outcome']);

    $team1score = array_key_exists('team1score', $_POST) ? trim((string)$_POST['team1score']) : null;
    $team2score = array_key_exists('team2score', $_POST) ? trim((string)$_POST['team2score']) : null;

    $match_start = null;
    if (array_key_exists('match_start', $_POST)) {
        $msent = trim((string)$_POST['match_start']);
        if ($msent !== '') {
            if (ctype_digit($msent)) {
                $match_start = intval($msent);
            } else {
                $tournamentTzName = (!empty($tournament['timezone']) && in_array($tournament['timezone'], DateTimeZone::listIdentifiers(), true))
                    ? $tournament['timezone']
                    : date_default_timezone_get();
                $tzObj = new DateTimeZone($tournamentTzName);

                $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $msent, $tzObj);
                if ($dt === false) {
                    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $msent, $tzObj);
                }
                if ($dt === false) {
                    try { $dt = new DateTime($msent, $tzObj); } catch (Throwable $e) { $dt = false; }
                }
                $match_start = ($dt !== false) ? $dt->getTimestamp() : null;
            }
        } else {
            $match_start = null;
        }
    }

    $sets = []; $params = [];
    $sets[] = "outcome = ?"; $params[] = $winner;
    if ($team1score !== null) { $sets[] = "team1score = ?"; $params[] = $team1score; }
    if ($team2score !== null) { $sets[] = "team2score = ?"; $params[] = $team2score; }
    if ($match_start !== null) { $sets[] = "match_start = ?"; $params[] = $match_start; }
    $sets[] = "team1approve = ?"; $params[] = 1;
    $sets[] = "team2approve = ?"; $params[] = 1;

    $params[] = $mID;
    $sql = "UPDATE {$matchTable} SET " . implode(", ", $sets) . " WHERE tournamentmatch_id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception('DB prepare failed (manager update): ' . $mysqli->error);

    $types = '';
    foreach ($params as $p) $types .= is_int($p) ? 'i' : 's';
    $bind = [];
    $bind[] = $types;
    foreach ($params as &$pv) $bind[] = &$pv;
    call_user_func_array([$stmt, 'bind_param'], $bind);

    if (!$stmt->execute()) {
        $err = $stmt->error ?: $mysqli->error;
        $stmt->close();
        throw new Exception('DB execute failed (manager): ' . $err);
    }
    $stmt->close();

    $payload = [];

    try {
        $nextMatchId = isset($match['nextmatch_id']) ? intval($match['nextmatch_id']) : 0;
        if ($nextMatchId > 0 && in_array($winner, [1,2], true)) {
            $winnerTeamId = ($winner === 1) ? intval($match['team1_id']) : intval($match['team2_id']);
            if ($winnerTeamId <= 0) {
                $payload['next_match_skipped'] = 'winner team id missing';
            } else {
                $sqlSibling = "SELECT * FROM {$matchTable} WHERE tournamentmatch_id != ? AND nextmatch_id = ? ORDER BY tournamentmatch_id LIMIT 1 FOR UPDATE";
                $s = $mysqli->prepare($sqlSibling);
                if (!$s) throw new Exception('Prepare failed (sibling select): ' . $mysqli->error);
                $s->bind_param('ii', $mID, $nextMatchId);
                if (!$s->execute()) { $err = $s->error ?: $mysqli->error; $s->close(); throw new Exception('Execute failed (sibling select): ' . $err); }
                $sres = $s->get_result();
                $srow = $sres ? $sres->fetch_assoc() : null;
                $s->close();

                if ($srow) {
                    if (intval($srow['outcome']) === 1) $preferredCol = 'team1_id';
                    elseif (intval($srow['outcome']) === 2) $preferredCol = 'team2_id';
                    else $preferredCol = (intval($srow['tournamentmatch_id']) > intval($mID)) ? 'team1_id' : 'team2_id';
                } else {
                    $preferredCol = 'team1_id';
                }
                $oppositeCol = ($preferredCol === 'team1_id') ? 'team2_id' : 'team1_id';

                $sqlLockNext = "SELECT team1_id, team2_id FROM {$matchTable} WHERE tournamentmatch_id = ? LIMIT 1 FOR UPDATE";
                $lk = $mysqli->prepare($sqlLockNext);
                if (!$lk) throw new Exception('Prepare failed (lock next): ' . $mysqli->error);
                $lk->bind_param('i', $nextMatchId);
                if (!$lk->execute()) { $err = $lk->error ?: $mysqli->error; $lk->close(); throw new Exception('Execute failed (lock next): ' . $err); }
                $lres = $lk->get_result();
                $lrow = $lres ? $lres->fetch_assoc() : null;
                $lk->close();

                if (!$lrow) {
                    $payload['next_match_skipped'] = 'next match row not found';
                } else {
                    $slotPreferredVal = isset($lrow[$preferredCol]) ? intval($lrow[$preferredCol]) : 0;
                    $slotOppositeVal  = isset($lrow[$oppositeCol])  ? intval($lrow[$oppositeCol])  : 0;

                    if ($slotPreferredVal === $winnerTeamId || $slotOppositeVal === $winnerTeamId) {
                        $payload['next_match_skipped'] = 'winner already present in next match';
                    } else {
                        $filled = false;
                        if (empty($slotPreferredVal)) {
                            $updSql = "UPDATE {$matchTable} SET {$preferredCol} = ? WHERE tournamentmatch_id = ? LIMIT 1";
                            $up = $mysqli->prepare($updSql);
                            if (!$up) throw new Exception('Prepare failed (update next match preferred): ' . $mysqli->error);
                            $up->bind_param('ii', $winnerTeamId, $nextMatchId);
                            if (!$up->execute()) { $err = $up->error ?: $mysqli->error; $up->close(); throw new Exception('Failed to write preferred slot: ' . $err); }
                            $up->close();
                            $filled = true;
                            $payload['next_match_filled'] = ['nextmatch_id' => $nextMatchId, 'col' => $preferredCol, 'team_id' => $winnerTeamId];
                        } elseif (empty($slotOppositeVal)) {
                            $updSql2 = "UPDATE {$matchTable} SET {$oppositeCol} = ? WHERE tournamentmatch_id = ? LIMIT 1";
                            $up2 = $mysqli->prepare($updSql2);
                            if (!$up2) throw new Exception('Prepare failed (update next match opposite): ' . $mysqli->error);
                            $up2->bind_param('ii', $winnerTeamId, $nextMatchId);
                            if (!$up2->execute()) { $err = $up2->error ?: $mysqli->error; $up2->close(); throw new Exception('Failed to write opposite slot: ' . $err); }
                            $up2->close();
                            $filled = true;
                            $payload['next_match_filled'] = ['nextmatch_id' => $nextMatchId, 'col' => $oppositeCol, 'team_id' => $winnerTeamId];
                        } else {
                            $payload['next_match_skipped'] = 'both slots populated';
                        }
                    }
                }
            }
        }
    } catch (Throwable $propErr) {
        error_log('[match_action_admin][propagation] ' . $propErr->getMessage());
        throw new Exception('Propagation error: ' . $propErr->getMessage());
    }

    try {
        $maybeOutcome = $winner;
        if (in_array(intval($maybeOutcome), [1,2], true) && isset($match['nextmatch_id']) && intval($match['nextmatch_id']) === 0) {
            $awardRes = award_tournament_winner($mysqli, $dbprefix, $tID, $mID, intval($maybeOutcome), $memberId, $gameThumbRel);
            if (empty($awardRes) || empty($awardRes['ok'])) {
                if (!empty($awardRes['msg']) && $awardRes['msg'] === 'Awards already assigned') {
                    $payload['award_msg'] = $awardRes['msg'];
                } else {
                    throw new Exception('Awarding failed: ' . ($awardRes['msg'] ?? 'unknown'));
                }
            } else {
                $payload['award_map'] = $awardRes['award_map'] ?? null;
                $payload['award_msg'] = $awardRes['msg'] ?? 'awarded';
            }
        }
    } catch (Throwable $awardEx) {
        throw new Exception('Awarding error: ' . $awardEx->getMessage());
    }

    if ($txStarted) $mysqli->commit();

    $stmt = $mysqli->prepare("SELECT * FROM {$matchTable} WHERE tournamentmatch_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $mID);
        $stmt->execute();
        $res = $stmt->get_result();
        $newMatch = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    } else {
        $newMatch = null;
    }

    $payload['message'] = 'Manager/admin action applied';
    if ($newMatch) {
        $payload['team1score'] = $newMatch['team1score'] ?? null;
        $payload['team2score'] = $newMatch['team2score'] ?? null;
        $payload['team1score0'] = $newMatch['team1score0'] ?? null;
        $payload['team2score0'] = $newMatch['team2score0'] ?? null;
        $payload['winner'] = $newMatch['outcome'] ?? '0';
        $payload['replayteam1url'] = !empty($newMatch['replayteam1url']) ? public_url_for($newMatch['replayteam1url']) : '';
        $payload['replayteam2url'] = !empty($newMatch['replayteam2url']) ? public_url_for($newMatch['replayteam2url']) : '';
        $payload['match_start'] = isset($newMatch['match_start']) ? intval($newMatch['match_start']) : null;
        $payload['outcome_label'] = intval($newMatch['outcome']) === 1 ? 'Team1' : (intval($newMatch['outcome']) === 2 ? 'Team2' : 'None');
    }

    try {
        $actorUsername = 'Manager';
        $actorAvatar = '';
        $mstmt = $mysqli->prepare("SELECT username, avatar FROM {$dbprefix}members WHERE member_id = ? LIMIT 1");
        if ($mstmt) {
            $mstmt->bind_param("i", $memberId);
            $mstmt->execute();
            $mres = $mstmt->get_result();
            if ($mrow = $mres->fetch_assoc()) {
                $actorUsername = $mrow['username'] ?? $actorUsername;
                $actorAvatar = $mrow['avatar'] ?? '';
            }
            $mstmt->close();
        }

        if (!empty($actorAvatar)) {
            if (stripos($actorAvatar, 'http://') === 0 || stripos($actorAvatar, 'https://') === 0) {
                $avatar_url = $actorAvatar;
            } else {
                $avatar_url = '/' . ltrim($actorAvatar, '/');
            }
        } else {
            $avatar_url = '/assets/images/notification.png';
        }

        $team1 = intval($match['team1_id']);
        $team2 = intval($match['team2_id']);
        $players_sql = "SELECT member_id, team_id FROM {$playerTable} WHERE tournament_id = ? AND team_id IN (?, ?)";
        $ps = $mysqli->prepare($players_sql);
        $targets = [];
        if ($ps) {
            $ps->bind_param('iii', $tID, $team1, $team2);
            $ps->execute();
            $pres = $ps->get_result();
            while ($row = $pres->fetch_assoc()) {
                $mid = intval($row['member_id']);
                $tid = intval($row['team_id']);
                if ($mid <= 0) continue;
                if ($mid === $memberId) continue;
                $targets[$mid] = $tid;
            }
            $ps->close();
        }

        $isFinalMatch = isset($match['nextmatch_id']) && intval($match['nextmatch_id']) === 0;
        $isDuel = $isFinalMatch && is_one_v_one_tournament($mysqli, $dbprefix, $tID);
        $notif_avatar = !empty($gameThumbRel) ? '/' . ltrim($gameThumbRel, '/') : $avatar_url;
        $tournamentLink = "<a href='competitions/competition.php?tID={$tID}'><strong>" . htmlspecialchars($tournament['name'], ENT_QUOTES | ENT_SUBSTITUTE) . "</strong></a>";

        try {
            $prevOutcome = intval($match['outcome']);

            if ($prevOutcome !== $winner) {
                $team1 = intval($match['team1_id']);
                $team2 = intval($match['team2_id']);

                $prevWinnerTeamId = (in_array($prevOutcome, [1,2], true)) ? (($prevOutcome === 1) ? $team1 : $team2) : 0;
                $newWinnerTeamId  = (in_array($winner, [1,2], true)) ? (($winner === 1) ? $team1 : $team2) : 0;

                $prevLoserTeamId = ($prevWinnerTeamId === $team1) ? $team2 : (($prevWinnerTeamId === $team2) ? $team1 : 0);
                $newLoserTeamId  = ($newWinnerTeamId === $team1) ? $team2 : (($newWinnerTeamId === $team2) ? $team1 : 0);

                $ps2 = $mysqli->prepare("SELECT member_id, team_id FROM {$playerTable} WHERE tournament_id = ? AND team_id IN (?, ?)");
                if ($ps2) {
                    $ps2->bind_param('iii', $tID, $team1, $team2);
                    $ps2->execute();
                    $r2 = $ps2->get_result();

                    $upGlobal = $mysqli->prepare(
                        "INSERT INTO {$dbprefix}member_stats
                            (member_id, tournaments_won, match_wins, match_losses, second_place, last_updated)
                         VALUES (?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE
                            tournaments_won = tournaments_won + VALUES(tournaments_won),
                            match_wins = match_wins + VALUES(match_wins),
                            match_losses = match_losses + VALUES(match_losses),
                            second_place = second_place + VALUES(second_place),
                            last_updated = NOW()"
                    );

                    $upGame = $mysqli->prepare(
                        "INSERT INTO {$dbprefix}member_game_stats
                            (member_id, gamesplayed_id, tournaments_won, match_wins, match_losses, second_place, last_updated)
                         VALUES (?, ?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE
                            tournaments_won = tournaments_won + VALUES(tournaments_won),
                            match_wins = match_wins + VALUES(match_wins),
                            match_losses = match_losses + VALUES(match_losses),
                            second_place = second_place + VALUES(second_place),
                            last_updated = NOW()"
                    );

                    if (!$upGlobal) {
                        throw new Exception('Prepare failed (member_stats upsert): ' . $mysqli->error);
                    }
                    if (!$upGame) {
                        throw new Exception('Prepare failed (member_game_stats upsert): ' . $mysqli->error);
                    }

                    while ($prow = $r2->fetch_assoc()) {
                        $mid = intval($prow['member_id']);
                        $tid = intval($prow['team_id']);

                        if ($prevWinnerTeamId && $tid === $prevWinnerTeamId) {
                            $prevStatus = 'win';
                        } elseif (in_array($prevOutcome, [1,2], true)) {
                            $prevStatus = 'loss';
                        } else {
                            $prevStatus = 'none';
                        }

                        if ($newWinnerTeamId && $tid === $newWinnerTeamId) {
                            $newStatus = 'win';
                        } elseif (in_array($winner, [1,2], true)) {
                            $newStatus = 'loss';
                        } else {
                            $newStatus = 'none';
                        }

                        $winsDelta = 0;
                        $lossesDelta = 0;
                        $tWinDelta = 0;
                        $secondDelta = 0;

                        if ($isFinalMatch && !$isDuel) {
                            if ($prevLoserTeamId > 0 && $tid === $prevLoserTeamId) {
                                $secondDelta -= 1;
                            }
                            if ($newLoserTeamId > 0 && $tid === $newLoserTeamId) {
                                $secondDelta += 1;
                            }
                        }

                        if ($prevStatus === 'win' && $newStatus !== 'win') {
                            $winsDelta -= 1;
                            if ($newStatus === 'loss') $lossesDelta += 1;
                            if ($isFinalMatch) $tWinDelta -= 1;
                        }

                        if ($prevStatus === 'loss' && $newStatus === 'win') {
                            $lossesDelta -= 1;
                            $winsDelta += 1;
                            if ($isFinalMatch) $tWinDelta += 1;
                        }

                        if ($prevStatus === 'none' && $newStatus === 'win') {
                            $winsDelta += 1;
                            if ($isFinalMatch) $tWinDelta += 1;
                        }

                        if ($prevStatus === 'none' && $newStatus === 'loss') {
                            $lossesDelta += 1;
                        }

                        if ($winsDelta === 0 && $lossesDelta === 0 && $tWinDelta === 0 && $secondDelta === 0) {
                            continue;
                        }

                        $upGlobal->bind_param('iiiii', $mid, $tWinDelta, $winsDelta, $lossesDelta, $secondDelta);
                        if (!$upGlobal->execute()) {
                            error_log("[match_action_admin] member_stats upsert failed for member {$mid}: " . $upGlobal->error);
                        }

                        $gameId = intval($tournament['gamesplayed_id']);

                        $upGame->bind_param('iiiiii', $mid, $gameId, $tWinDelta, $winsDelta, $lossesDelta, $secondDelta);
                        if (!$upGame->execute()) {
                            error_log("[match_action_admin] member_game_stats upsert failed for member {$mid}: " . $upGame->error);
                        }

                        $corrGlobal = $mysqli->prepare(
                            "UPDATE {$dbprefix}member_stats
                             SET match_wins = GREATEST(match_wins,0),
                                 match_losses = GREATEST(match_losses,0),
                                 tournaments_won = GREATEST(tournaments_won,0),
                                 second_place = GREATEST(second_place,0)
                             WHERE member_id = ?"
                        );
                        if ($corrGlobal) {
                            $corrGlobal->bind_param('i', $mid);
                            $corrGlobal->execute();
                            $corrGlobal->close();
                        }

                        $corrGame = $mysqli->prepare(
                            "UPDATE {$dbprefix}member_game_stats
                             SET match_wins = GREATEST(match_wins,0),
                                 match_losses = GREATEST(match_losses,0),
                                 tournaments_won = GREATEST(tournaments_won,0),
                                 second_place = GREATEST(second_place,0)
                             WHERE member_id = ? AND gamesplayed_id = ?"
                        );
                        if ($corrGame) {
                            $corrGame->bind_param('ii', $mid, $gameId);
                            $corrGame->execute();
                            $corrGame->close();
                        }
                    }

                    $upGlobal->close();
                    $upGame->close();
                    $ps2->close();
                }
            }
        } catch (Throwable $statErr) {
            error_log("[match_action_admin] stats update error: " . $statErr->getMessage());
        }

        if (in_array($winner, [1,2], true)) {
            foreach ($targets as $targ => $targTeamId) {
                $winningTeamId = ($winner === 1) ? intval($match['team1_id']) : intval($match['team2_id']);
                $isTargetWinner = ($targTeamId === $winningTeamId);

                if ($isFinalMatch && !$isTargetWinner && !$isDuel) {
                    continue;
                }

                if ($isTargetWinner) {
                    if ($isFinalMatch) {
                        $subjectToSend = "<strong style='color:#4caf50'>Congratulations</strong> — you <strong style='color:gold'>Won</strong> the {$tournamentLink}";
                    } else {
                        $subjectToSend = "You <strong style='color:gold'>Won</strong> the match in {$tournamentLink}";
                    }
                } elseif ($isDuel) {
                    $subjectToSend = "You <strong style='color:#c62828'>Lost</strong> the {$tournamentLink}";
                } else {
                    $subjectToSend = "You <strong style='color:#c62828'>Lost</strong> the {$tournamentLink}";
                }

                $notifPayload = ['subject_html' => $subjectToSend, 'avatar_url' => $notif_avatar];

                $notif_sig = hash('sha256', implode('|', ['match', $mID, 'from:'.$memberId, 'to:'.$targ]));
                $already = false;
                $chk = $mysqli->prepare("SELECT id FROM {$dbprefix}notifications WHERE notif_sig = ? LIMIT 1");
                if ($chk) {
                    $chk->bind_param("s", $notif_sig);
                    $chk->execute();
                    $cres = $chk->get_result();
                    $already = ($cres && $cres->num_rows > 0);
                    $chk->close();
                }
                if ($already) continue;

                $payloadJson = json_encode($notifPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $actionType = 'match';
                $insn = $mysqli->prepare("INSERT INTO {$dbprefix}notifications (`type`,`target_user_id`,`payload`,`notif_sig`) VALUES (?, ?, ?, ?)");
                if ($insn) {
                    $insn->bind_param("siss", $actionType, $targ, $payloadJson, $notif_sig);
                    if (!$insn->execute()) {
                        if ($mysqli->errno !== 1062) error_log("Failed inserting notification: (" . $mysqli->errno . ") " . $mysqli->error);
                    }
                    $insn->close();
                } else {
                    error_log("notifications insert prepare failed: " . $mysqli->error);
                }
            }
        }

    } catch (Throwable $notifErr) {
        error_log("[match_action_admin][manager] notification error: " . $notifErr->getMessage());
    }

    ok_json($payload);

} catch (Throwable $e) {
    if ($txStarted && method_exists($mysqli, 'rollback')) $mysqli->rollback();
    error_log('[match_action_admin] ' . $e->getMessage());
    err_json($e->getMessage(), 400);
}