<?php
include("../../_intro.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

if (!isset($_GET['tID']) || !is_numeric($_GET['tID'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid tournament.']);
    exit;
}

$tID = intval($_GET['tID']);
$memberId = intval($_SESSION['user_id']);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
$csrf_token = $input['csrf_token'] ?? '';
$action = trim((string)($input['action'] ?? ''));
$provided_code = trim((string)($input['code'] ?? ''));

if (empty($_SESSION['csrftokken']) || empty($csrf_token) || !hash_equals($_SESSION['csrftokken'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

function makeInviteCode(): string {
    return strtoupper(bin2hex(random_bytes(4)));
}

function detect_player_table($mysqli, $dbprefix) {
    $cand = [$dbprefix . "tournamentplayers", $dbprefix . "tournamentplayer"];
    foreach ($cand as $c) {
        $esc = $mysqli->real_escape_string($c);
        $q = $mysqli->query("SHOW TABLES LIKE '{$esc}'");
        if ($q && $q->num_rows > 0) return $c;
    }
    return $dbprefix . "tournamentplayers";
}

if (!function_exists('renderStyledUsername')) {
    function renderStyledUsername($username, $styleJson) {
        $username = (string)$username;
        $styleArr = json_decode((string)$styleJson, true);
        if (!is_array($styleArr) || empty($styleArr)) {
            return htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $out = '';
        $concatenatedText = '';
        foreach ($styleArr as $run) {
            if (!is_array($run)) continue;
            $text = (string)($run['text'] ?? '');
            $color = (string)($run['color'] ?? '');
            if ($text === '') continue;
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#ffffff';
            }
            $out .= '<span style="color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . ';">'
                 . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                 . '</span>';

            $concatenatedText .= $text;
        }
        if ($concatenatedText !== $username) {
            return htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return $out !== '' ? $out : htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function send_tournament_join_notifications($mysqli, $dbprefix, $tID, $memberId, $tInfo, $playerTable, $notificationsTable) {
    try {
        // actor info
        $actorUsername = 'Someone';
        $actorAvatar = '';
        $actorStyle = null;
        $mstmt = $mysqli->prepare("SELECT username, avatar, username_style FROM {$dbprefix}members WHERE member_id = ? LIMIT 1");
        if ($mstmt) {
            $mstmt->bind_param("i", $memberId);
            $mstmt->execute();
            $mres = $mstmt->get_result();
            if ($mrow = $mres->fetch_assoc()) {
                $actorUsername = $mrow['username'] ?? $actorUsername;
                $actorAvatar = $mrow['avatar'] ?? '';
                $actorStyle = $mrow['username_style'] ?? null;
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

        $participants = [];
        $pstmt = $mysqli->prepare("SELECT DISTINCT member_id FROM {$playerTable} WHERE tournament_id = ? AND member_id <> ?");
        if ($pstmt) {
            $pstmt->bind_param('ii', $tID, $memberId);
            $pstmt->execute();
            $pres = $pstmt->get_result();
            while ($prow = $pres->fetch_assoc()) {
                $participants[] = intval($prow['member_id']);
            }
            $pstmt->close();
        }

        $othersCount = count($participants);

        $group_sig = hash('sha256', 'tournament_join_group|' . $tID);
        $groupExists = false;
        $chk = $mysqli->prepare("SELECT id FROM {$notificationsTable} WHERE notif_sig = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param('s', $group_sig);
            $chk->execute();
            $cres = $chk->get_result();
            $groupExists = ($cres && $cres->num_rows > 0);
            $chk->close();
        }

        $safeTournamentName = htmlspecialchars($tInfo['name'] ?? 'Tournament', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $styledActorName = renderStyledUsername($actorUsername, $actorStyle);

        $tournamentUrlRaw = '<a href="tournaments/tournament.php?tID=' . $tID . '"><strong>' . $safeTournamentName . '</strong></a>';
        $memberUrlRaw = '<a href="member.php?mID=' . $memberId . '"><strong>' . $styledActorName . '</strong></a>';

        if (!$groupExists) {
            $subject_html = $memberUrlRaw . ' has joined the ' . $tournamentUrlRaw;
            $payload = ['subject_html' => $subject_html, 'avatar_url' => $avatar_url];
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $ins = $mysqli->prepare("INSERT INTO {$notificationsTable} (`type`,`target_user_id`,`payload`,`notif_sig`) VALUES (?, ?, ?, ?)");
            if ($ins) {
                $type = 'tournament';
                $target_zero = 0;
                $ins->bind_param('siss', $type, $target_zero, $payloadJson, $group_sig);
                if (!$ins->execute()) {
                    if ($mysqli->errno !== 1062) error_log("Failed inserting group notif: ({$mysqli->errno}) {$mysqli->error}");
                }
                $ins->close();
            } else {
                error_log("notifications insert prepare failed (group): " . $mysqli->error);
            }
        } else {
            if ($othersCount === 1) {
                $nText = '<strong style="color:#ffeb3b;">1 other</strong>';
            } else {
                $nText = '<strong style="color:#ffeb3b;">' . intval($othersCount) . ' others</strong>';
            }

            $subject_html = $memberUrlRaw . ' and ' . $nText . ' have joined the ' . $tournamentUrlRaw;
            $payload = ['subject_html' => $subject_html, 'avatar_url' => $avatar_url];
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $up = $mysqli->prepare("UPDATE {$notificationsTable} SET payload = ? WHERE notif_sig = ? LIMIT 1");
            if ($up) {
                $up->bind_param('ss', $payloadJson, $group_sig);
                $up->execute();
                $up->close();
            }
        }

        foreach ($participants as $targ) {
            $notif_sig_personal = hash('sha256', implode('|', ['tournament_join_personal', $tID, 'to:'.$targ]));

            $payloadPersonal = ['subject_html' => $subject_html, 'avatar_url' => $avatar_url];
            $payloadJsonPersonal = json_encode($payloadPersonal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $upd = $mysqli->prepare("UPDATE {$notificationsTable} SET payload = ?, created_at = CURRENT_TIMESTAMP, seen = 0 WHERE notif_sig = ? AND target_user_id = ? LIMIT 1");
            $affected = 0;
            if ($upd) {
                $upd->bind_param('ssi', $payloadJsonPersonal, $notif_sig_personal, $targ);
                $upd->execute();
                $affected = $mysqli->affected_rows;
                $upd->close();
            }

            if ($affected <= 0) {
                $typePersonal = 'tournament';
                $insp = $mysqli->prepare("INSERT INTO {$notificationsTable} (`type`,`target_user_id`,`payload`,`notif_sig`) VALUES (?, ?, ?, ?)");
                if ($insp) {
                    $insp->bind_param('siss', $typePersonal, $targ, $payloadJsonPersonal, $notif_sig_personal);
                    if (!$insp->execute()) {
                        if ($mysqli->errno !== 1062) error_log("Failed inserting personal notification: ({$mysqli->errno}) {$mysqli->error}");
                    }
                    $insp->close();
                } else {
                    error_log("notifications insert prepare failed (personal): " . $mysqli->error);
                }
            }
        }
    } catch (Throwable $notifErr) {
        error_log("[join_action][notification] error: " . $notifErr->getMessage());
    }
}

$playerTable = detect_player_table($mysqli, $dbprefix);
$tournamentsTable = $dbprefix . "tournaments";
$teamsTable = $dbprefix . "tournamentteams";
$invitesTable = $dbprefix . "tournament_team_invites";
$statsTable = $dbprefix . 'member_stats';
$notificationsTable = $dbprefix . "notifications";

if (!method_exists($mysqli, 'begin_transaction')) $mysqli->autocommit(false);
else $mysqli->begin_transaction();

try {
    $stmt = $mysqli->prepare("SELECT * FROM {$tournamentsTable} WHERE tournament_id = ? FOR UPDATE");
    if (!$stmt) throw new Exception('prepare_failed');
    $stmt->bind_param('i', $tID);
    $stmt->execute();
    $res = $stmt->get_result();
    $tInfo = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$tInfo) {
        $mysqli->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Competition not found.']);
        exit;
    }

    if (intval($tInfo['registration_status'] ?? 1) === 0) {
        $mysqli->rollback();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Registration is closed for this competition.']);
        exit;
    }

    $playersPerTeam = intval($tInfo['playersperteam'] ?? 1);
    $maxTeams = intval($tInfo['maxteams'] ?? 0);
    $maxPlayers = $maxTeams * max(1, $playersPerTeam);

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM {$playerTable} WHERE member_id = ? AND tournament_id = ?");
    if (!$stmt) throw new Exception('prepare_failed_dupcheck');
    $stmt->bind_param('ii', $memberId, $tID);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $already = ($row && intval($row['cnt']) > 0);
    $stmt->close();

    if ($already) {
        $mysqli->rollback();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'You are already in this competition.']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM {$playerTable} WHERE tournament_id = ?");
    if (!$stmt) throw new Exception('prepare_failed_participants');
    $stmt->bind_param('i', $tID);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $participants_count = intval($row['cnt'] ?? 0);
    $stmt->close();

    if ($maxPlayers > 0 && $participants_count >= $maxPlayers) {
        $mysqli->rollback();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This competition is currently full.']);
        exit;
    }

    $game_id = intval($tInfo['gamesplayed_id'] ?? 0);

    if ($playersPerTeam === 1) {
        $stmt = $mysqli->prepare("INSERT INTO {$playerTable} (member_id, tournament_id, game_id) VALUES (?, ?, ?)");
        if (!$stmt) throw new Exception('insert_player_failed');
        $stmt->bind_param('iii', $memberId, $tID, $game_id);
        if (!$stmt->execute()) {
            $stmt->close();
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to add you to the competition.']);
            exit;
        }
        $insertId = $mysqli->insert_id;
        $stmt->close();

        $q = "
            SELECT tt.tournamentteam_id
            FROM {$teamsTable} tt
            LEFT JOIN {$playerTable} tp ON tp.team_id = tt.tournamentteam_id
            WHERE tt.tournament_id = ?
            GROUP BY tt.tournamentteam_id
            HAVING COUNT(tp.tournamentplayer_id) < 1
            ORDER BY tt.tournamentteam_id
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $mysqli->prepare($q);
        if (!$stmt) throw new Exception('find_team_failed');
        $stmt->bind_param('i', $tID);
        $stmt->execute();
        $res = $stmt->get_result();
        $teamRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$teamRow) {
            $mysqli->rollback();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'No open team slot available.']);
            exit;
        }

        $teamId = intval($teamRow['tournamentteam_id']);

        $upd = $mysqli->prepare("UPDATE {$playerTable} SET team_id = ? WHERE tournamentplayer_id = ? LIMIT 1");
        if (!$upd) throw new Exception('update_team_failed');
        $upd->bind_param('ii', $teamId, $insertId);
        $upd->execute();
        $upd->close();

        $updT = $mysqli->prepare("UPDATE {$tournamentsTable} SET participants_count = participants_count + 1 WHERE tournament_id = ? LIMIT 1");
        if (!$updT) throw new Exception('update_participants_failed');
        $updT->bind_param('i', $tID);
        $updT->execute();
        $updT->close();

        $upsert = $mysqli->prepare(
            "INSERT INTO {$statsTable} (member_id, tournaments_participated)
             VALUES (?, 1)
             ON DUPLICATE KEY UPDATE tournaments_participated = tournaments_participated + 1"
        );
        if ($upsert) {
            $upsert->bind_param('i', $memberId);
            $upsert->execute();
            $upsert->close();
        }

        $mysqli->commit();
        send_tournament_join_notifications($mysqli, $dbprefix, $tID, $memberId, $tInfo, $playerTable, $notificationsTable);
        echo json_encode(['success' => true, 'message' => 'You have joined the competition. Good luck!']);
        exit;
    }

    if ($action === 'new_team') {
        $q = "
            SELECT tt.tournamentteam_id
            FROM {$teamsTable} tt
            LEFT JOIN {$playerTable} tp ON tp.team_id = tt.tournamentteam_id
            WHERE tt.tournament_id = ?
            GROUP BY tt.tournamentteam_id
            HAVING COUNT(tp.tournamentplayer_id) = 0
            ORDER BY tt.tournamentteam_id
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $mysqli->prepare($q);
        if (!$stmt) throw new Exception('find_empty_team_failed');
        $stmt->bind_param('i', $tID);
        $stmt->execute();
        $res = $stmt->get_result();
        $teamRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$teamRow) {
            $mysqli->rollback();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'No empty team slots left.']);
            exit;
        }

        $teamId = intval($teamRow['tournamentteam_id']);
        $inviteCode = null;

        for ($i = 0; $i < 5; $i++) {
            $try = makeInviteCode();

            $check = $mysqli->prepare("SELECT 1 FROM {$invitesTable} WHERE invite_code = ? LIMIT 1");
            if (!$check) throw new Exception('invite_check_failed');
            $check->bind_param('s', $try);
            $check->execute();
            $r = $check->get_result();
            $exists = ($r && $r->num_rows > 0);
            $check->close();

            if (!$exists) {
                $inviteCode = $try;
                break;
            }
        }

        if (!$inviteCode) {
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not generate invite code.']);
            exit;
        }

        $stmt = $mysqli->prepare("INSERT INTO {$playerTable} (member_id, tournament_id, game_id, team_id) VALUES (?, ?, ?, ?)");
        if (!$stmt) throw new Exception('insert_player_failed_team');
        $stmt->bind_param('iiii', $memberId, $tID, $game_id, $teamId);
        if (!$stmt->execute()) {
            $stmt->close();
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to join new team.']);
            exit;
        }
        $stmt->close();

        $stmt = $mysqli->prepare(
            "INSERT INTO {$invitesTable} (tournamentteam_id, tournament_id, invite_code, created_by, created_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               tournament_id = VALUES(tournament_id),
               invite_code = VALUES(invite_code),
               created_by = VALUES(created_by),
               created_at = VALUES(created_at)"
        );
        if (!$stmt) throw new Exception('invite_upsert_failed');
        $now = time();
        $stmt->bind_param('iisii', $teamId, $tID, $inviteCode, $memberId, $now);
        if (!$stmt->execute()) {
            $stmt->close();
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create invite code.']);
            exit;
        }
        $stmt->close();

        $updT = $mysqli->prepare("UPDATE {$tournamentsTable} SET participants_count = participants_count + 1 WHERE tournament_id = ? LIMIT 1");
        if (!$updT) throw new Exception('update_participants_failed');
        $updT->bind_param('i', $tID);
        $updT->execute();
        $updT->close();

        $upsert = $mysqli->prepare(
            "INSERT INTO {$statsTable} (member_id, tournaments_participated)
             VALUES (?, 1)
             ON DUPLICATE KEY UPDATE tournaments_participated = tournaments_participated + 1"
        );
        if ($upsert) {
            $upsert->bind_param('i', $memberId);
            $upsert->execute();
            $upsert->close();
        }

        $mysqli->commit();
        send_tournament_join_notifications($mysqli, $dbprefix, $tID, $memberId, $tInfo, $playerTable, $notificationsTable);
        echo json_encode([
            'success' => true,
            'message' => 'Fresh team created successfully.',
            'invite_code' => $inviteCode
        ]);
        exit;
    }

    if ($action === 'join_invite') {
        if ($provided_code === '') {
            $mysqli->rollback();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invite code is required.']);
            exit;
        }

        $stmt = $mysqli->prepare("
            SELECT ti.tournamentteam_id, ti.tournament_id, ti.invite_code
            FROM {$invitesTable} ti
            WHERE ti.invite_code = ? AND ti.tournament_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        if (!$stmt) throw new Exception('invite_lookup_failed');
        $stmt->bind_param('si', $provided_code, $tID);
        $stmt->execute();
        $res = $stmt->get_result();
        $inviteRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$inviteRow) {
            $mysqli->rollback();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Invalid invite code.']);
            exit;
        }

        $teamId = intval($inviteRow['tournamentteam_id']);

        $stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM {$playerTable} WHERE tournament_id = ? AND team_id = ?");
        if (!$stmt) throw new Exception('team_count_failed');
        $stmt->bind_param('ii', $tID, $teamId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $teamCount = intval($row['cnt'] ?? 0);
        $stmt->close();

        if ($teamCount >= $playersPerTeam) {
            $mysqli->rollback();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'That team is already full.']);
            exit;
        }

        $stmt = $mysqli->prepare("INSERT INTO {$playerTable} (member_id, tournament_id, game_id, team_id) VALUES (?, ?, ?, ?)");
        if (!$stmt) throw new Exception('insert_player_failed_invite');
        $stmt->bind_param('iiii', $memberId, $tID, $game_id, $teamId);
        if (!$stmt->execute()) {
            $stmt->close();
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to join team.']);
            exit;
        }
        $stmt->close();

        $updT = $mysqli->prepare("UPDATE {$tournamentsTable} SET participants_count = participants_count + 1 WHERE tournament_id = ? LIMIT 1");
        if (!$updT) throw new Exception('update_participants_failed');
        $updT->bind_param('i', $tID);
        $updT->execute();
        $updT->close();

        $upsert = $mysqli->prepare(
            "INSERT INTO {$statsTable} (member_id, tournaments_participated)
             VALUES (?, 1)
             ON DUPLICATE KEY UPDATE tournaments_participated = tournaments_participated + 1"
        );
        if ($upsert) {
            $upsert->bind_param('i', $memberId);
            $upsert->execute();
            $upsert->close();
        }

        $mysqli->commit();
        send_tournament_join_notifications($mysqli, $dbprefix, $tID, $memberId, $tInfo, $playerTable, $notificationsTable);
        echo json_encode(['success' => true, 'message' => 'You joined the team successfully.']);
        exit;
    }

    $mysqli->rollback();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $playersPerTeam > 1
            ? 'Choose either Create Fresh Team or Join with Invite Code.'
            : 'Invalid request.'
    ]);
    exit;

} catch (Exception $e) {
    if (method_exists($mysqli, 'rollback')) $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}
?>