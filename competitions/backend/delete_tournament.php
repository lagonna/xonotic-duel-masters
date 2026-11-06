<?php
include_once("../../_intro.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'not_logged_in']);
    exit();
}

if (!isset($_POST['tID']) || !is_numeric($_POST['tID'])) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_tID']);
    exit();
}

$tID = (int)$_POST['tID'];

try {
    $memberStmt = $mysqli->prepare("SELECT member_id FROM {$dbprefix}members WHERE member_id = ? LIMIT 1");
    $memberStmt->bind_param("i", $_SESSION['user_id']);
    $memberStmt->execute();
    $memberResult = $memberStmt->get_result();

    if ($memberResult->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'member_not_found']);
        exit();
    }

    $memberRow = $memberResult->fetch_assoc();

    $tournamentStmt = $mysqli->prepare("SELECT tournament_id, member_id FROM {$dbprefix}tournaments WHERE tournament_id = ? LIMIT 1");
    $tournamentStmt->bind_param("i", $tID);
    $tournamentStmt->execute();
    $tournamentResult = $tournamentStmt->get_result();

    if ($tournamentResult->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'tournament_not_found']);
        exit();
    }

    $tournamentRow = $tournamentResult->fetch_assoc();

    $isOwner = ((int)$memberRow['member_id'] === (int)$tournamentRow['member_id']);
    $isAdmin = ((int)$memberRow['member_id'] === 1);

    if (!$isOwner && !$isAdmin) {
        echo json_encode(['status' => 'error', 'message' => 'not_authorized']);
        exit();
    }

    $mysqli->begin_transaction();

    $tables = [
        "{$dbprefix}tournamentplayers",
        "{$dbprefix}tournamentmatch",
        "{$dbprefix}tournamentteams",
        "{$dbprefix}tournaments"
    ];

    foreach ($tables as $table) {
        $stmt = $mysqli->prepare("DELETE FROM {$table} WHERE tournament_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for $table: " . $mysqli->error);
        }

        $stmt->bind_param("i", $tID);

        if (!$stmt->execute()) {
            throw new Exception("Delete failed for $table: " . $stmt->error);
        }

        $stmt->close();
    }

    $mysqli->commit();
    echo json_encode(['status' => 'success']);
    exit();

} catch (Throwable $e) {
    if ($mysqli && $mysqli->errno === 0) {
        $mysqli->rollback();
    } else {
        $mysqli->rollback();
    }

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit();
}