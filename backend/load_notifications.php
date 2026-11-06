<?php
header('Content-Type: application/json');
include_once("../_intro.php");

$raw = file_get_contents('php://input');
$input = [];
if ($raw !== false && strlen(trim($raw)) > 0) {
    $decoded = json_decode($raw, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => 'bad_request', 'msg' => 'invalid_json']);
        exit;
    }
    $input = (array)$decoded;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$userID = (int) $_SESSION['user_id'];

$limit = (int)($input['limit'] ?? 5);
$last_created_at = $input['last_created_at'] ?? null;
$last_notif_id = isset($input['last_notif_id']) ? (int)$input['last_notif_id'] : null;

if ($limit <= 0 || $limit > 100) $limit = 20;

$sql = "SELECT id, target_user_id, type, payload, seen, created_at
        FROM {$dbprefix}notifications
        WHERE target_user_id = ? ";
$params = [$userID];
$types = 'i';

if (!empty($last_created_at) && $last_notif_id !== null) {
    $sql .= " AND (created_at < ? OR (created_at = ? AND id < ?)) ";
    $params[] = $last_created_at;
    $params[] = $last_created_at;
    $params[] = $last_notif_id;
    $types .= 'ssi';
}

$sql .= " ORDER BY created_at DESC, id DESC LIMIT ? ";
$params[] = $limit + 1;
$types .= 'i';

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'prepare_failed', 'details' => $mysqli->error]);
    exit;
}

$bind_names = [];
$bind_names[] = $types;
for ($i = 0; $i < count($params); $i++) {
    $bind = 'bind' . $i;
    $$bind = $params[$i];
    $bind_names[] = &$$bind;
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);

$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

$has_more = false;
if (count($rows) > $limit) {
    $has_more = true;
    array_pop($rows);
}

ob_start();
foreach ($rows as $notification) {
    $payload = json_decode($notification['payload'], true);
    if (!is_array($payload)) $payload = [];

    $seen = (int)$notification['seen'] === 1;
    $seen_class = $seen ? '' : 'unseen';

    $created_at = strtotime($notification['created_at']);
    $time_ago = function_exists('gettimeago') ? gettimeago($created_at) : date('Y-m-d H:i', $created_at);

    
    $created_iso = '';
    if (!empty($notification['created_at'])) {
        $ts = strtotime($notification['created_at']);
        if ($ts !== false) {
            $created_iso = date('c', $ts); 
        }
    }

    $avatar_url = $MAIN_ROOT . 'assets/images/notification.png';
    $overlay_img = $MAIN_ROOT . 'assets/images/notification.png';

    $action = $notification['type'] ?? 'system';
    if ($action === "system") {
        $avatar_url = $MAIN_ROOT . "assets/images/settings.png";
    } elseif ($action === "tournament") {
        $avatar_url = $MAIN_ROOT . "assets/images/tournament.png";
    } elseif ($action === "match") {
        if (!empty($payload['avatar_url'])) {
            $avatar_url = (strpos($payload['avatar_url'], 'http://') === 0 || strpos($payload['avatar_url'], 'https://') === 0)
                ? $payload['avatar_url']
                : $MAIN_ROOT . ltrim($payload['avatar_url'], '/');
        }
        $overlay_img = $MAIN_ROOT . "assets/images/tournament_blue.png";
    } else {
        $avatar_url = $MAIN_ROOT . 'assets/images/notification.png';
    }

    $subject = $payload['subject_html'] ?? 'Notification';

    $data_attr = $created_iso ? ' data-created-utc="' . htmlspecialchars($created_iso, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '"' : '';

    ?>
    <div class="notification-card <?php echo $seen_class; ?>"<?php echo $data_attr; ?>>
        <div class="avatar-wrap">
            <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="avatar" class="notification-avatar" loading="lazy">
            <?php if (!empty($overlay_img)): ?>
                <img src="<?php echo htmlspecialchars($overlay_img); ?>" alt="" class="avatar-overlay" loading="lazy">
            <?php endif; ?>
        </div>
        <div class="notification-content">
            <p><?php echo $subject; ?></p>
            <span class="date"><?php echo htmlspecialchars($time_ago); ?></span>
        </div>
    </div>
    <?php
}
$html = ob_get_clean();

$last_item = end($rows);
$last_created_at_out = $last_item ? $last_item['created_at'] : null;
$last_notif_id_out = $last_item ? (int)$last_item['id'] : null;

echo json_encode([
    'html' => $html,
    'count' => count($rows),
    'has_more' => $has_more,
    'last_created_at' => $last_created_at_out,
    'last_notif_id' => $last_notif_id_out
]);
exit;
