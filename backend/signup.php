<?php
include_once("../_intro.php");
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
@ini_set('display_errors', '0');
ob_start();
header('Content-Type: application/json; charset=utf-8');

function is_rate_limited($key, $maxAttempts = 5, $windowSeconds = 3600) {
    $filePath = sys_get_temp_dir() . '/rl_' . md5($key) . '.json';
    $now = time();
    $timestamps = [];
    if (file_exists($filePath)) {
        $data = @json_decode(@file_get_contents($filePath), true);
        if (is_array($data)) {
            $timestamps = array_filter($data, function($ts) use ($now, $windowSeconds) {
                return ($now - $ts) < $windowSeconds;
            });
        }
    }
    if (count($timestamps) >= $maxAttempts) return true;
    $timestamps[] = $now;
    @file_put_contents($filePath, json_encode(array_values($timestamps)), LOCK_EX);
    return false;
}

function send_json_local($payload, $status = 200){
    while (ob_get_level()) ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$username = trim($input['username'] ?? '');
$username = str_replace("\0", '', $username);
$username = preg_replace("/\r\n?/", "\n", $username);
$email_raw = trim($input['email'] ?? '');
$password = (string)($input['password'] ?? '');
$csrf_in = $input['csrf_token'] ?? '';
$session_csrf = $_SESSION['csrftokken'] ?? $_SESSION['csrf_token'] ?? null;

$response = ['status'=>'error','fieldErrors'=>[],'globalError'=>''];

if (empty($session_csrf) || empty($csrf_in) || !hash_equals($session_csrf, $csrf_in)) {
    $response['globalError'] = 'Invalid CSRF token.';
    send_json_local($response, 400);
}

$rawIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = trim(explode(',', $rawIp)[0]);
$ip = substr($ip, 0, 255);

if (is_rate_limited("signup_ip_" . $ip, 5, 3600)) {
    $response['globalError'] = 'Too many accounts created from this network. Please try again later.';
    send_json_local($response, 429);
}

if ($username === '') {
    $response['fieldErrors']['signup-username'] = 'Please enter a username.';
} else {
    $charLen = mb_strlen($username, 'UTF-8');
    if ($charLen < 2 || $charLen > 30) {
        $response['fieldErrors']['signup-username'] = 'Username must be between 2 and 30 characters.';
    } elseif (preg_match('/^\s|\s$/u', $username)) {
        $response['fieldErrors']['signup-username'] = 'Username cannot start or end with a space.';
    } elseif (preg_match('/[\x00-\x1F\x7F]/', $username) || strpos($username, '<') !== false || strpos($username, '>') !== false) {
        $response['fieldErrors']['signup-username'] = 'Username contains invalid characters.';
    }
}

if ($email_raw !== '') {
    if (!filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
        $response['fieldErrors']['signup-email'] = 'Please enter a valid email address.';
    } else {
        $stmt = $mysqli->prepare("SELECT 1 FROM {$dbprefix}members WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email_raw);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $response['fieldErrors']['signup-email'] = 'This email is already in use.';
            }
            $stmt->close();
        }
    }
}

if ($password === '') $response['fieldErrors']['signup-password'] = 'Please enter a password.';
elseif (strlen($password) < 10) $response['fieldErrors']['signup-password'] = 'Password must be at least 10 characters long.';
elseif (strlen($password) > 64) $response['fieldErrors']['signup-password'] = 'Max password length is 64 characters.';
elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) $response['fieldErrors']['signup-password'] = 'Password must meet complexity requirements.';

$stmt = $mysqli->prepare("SELECT 1 FROM {$dbprefix}members WHERE username = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('s', $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $response['fieldErrors']['signup-username'] = 'Username already taken.';
    }
    $stmt->close();
}

if (!empty($response['fieldErrors'])) send_json_local($response);

try {
    $mysqli->begin_transaction();
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $newMemRank = 71;
    $gender = 'male';
    $country = '';
    $datejoined = time();
    $profilePic = "images/avatar/defaultprofile.png";
    $thumbsPic  = "images/avatar/thumbs/defaultprofile.png";
    $verification_code = bin2hex(random_bytes(4));
    $verification_expiry = date('Y-m-d H:i:s', time() + 3600*24);
    $wsToken = bin2hex(random_bytes(32));

    if ($email_raw === '') {
        $email_for_db = 'noemail+' . bin2hex(random_bytes(6)) . '@noemail.local';
    } else {
        $email_for_db = $email_raw;
    }

    $cols = [
        "username","password","rank_id","profilepic","avatar","country","email",
        "datejoined","profileviews","ipaddress","ws_token","disabled","verified","verification_code","verification_expiry"
    ];

    $vals = [
        $username, $password_hash, $newMemRank, $profilePic, $thumbsPic, $country,
        $email_for_db, $datejoined, 0, $ip, $wsToken, 0, 1, $verification_code, $verification_expiry
    ];

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $types = '';
    foreach ($vals as $v) {
        if (is_int($v)) $types .= 'i';
        elseif (is_float($v)) $types .= 'd';
        else $types .= 's';
    }

    $sql = "INSERT INTO {$dbprefix}members (" . implode(',', $cols) . ") VALUES ({$placeholders})";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("DB prepare failed: " . $mysqli->error);

    $bindParams = array_merge([$types], $vals);
    $refs = [];
    foreach ($bindParams as $k => $v) $refs[$k] = &$bindParams[$k];
    if (!call_user_func_array([$stmt, 'bind_param'], $refs)) throw new Exception("bind_param failed: " . $stmt->error);
    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

    $insertedId = $stmt->insert_id ?? $mysqli->insert_id;
    $stmt->close();

    $notif_type = "notification";
    $payload = json_encode([
        "subject_html" =>
            "<strong style='color:chartreuse;'>🎉 </strong><strong style='color:chartreuse;'>Account created!</strong><br>
             <strong>You're all set. Feel free to check out the current tournaments whenever you're ready.</strong><br>
            <a href='" . MAIN_ROOT . "competitions/'>See what's running →</a>"
    ]);

    $sql = "INSERT INTO {$dbprefix}notifications (target_user_id, type, payload, seen, created_at) VALUES (?, ?, ?, 0, NOW())";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("iss", $insertedId, $notif_type, $payload);
        $stmt->execute();
        $stmt->close();
    }
    
    $stats_sql = "INSERT INTO {$dbprefix}member_stats (member_id) VALUES (?)";
    if ($stmt = $mysqli->prepare($stats_sql)) {
        $stmt->bind_param("i", $insertedId);
        $stmt->execute();
        $stmt->close();
    }

    $mysqli->commit();
    

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$insertedId;
    $_SESSION['RememberMe'] = 0;
    $_SESSION['ws_token'] = $wsToken;

    send_json_local(['status'=>'success','redirect'=>MAIN_ROOT . '','message'=>'Account created. welcome.']);

} catch (Throwable $e) {
    @$mysqli->rollback();
    error_log('Signup error: ' . $e->getMessage());
    send_json_local(['status'=>'error','globalError'=>'Error creating account. Try again later.'], 500);
}