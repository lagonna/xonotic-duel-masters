<?php
include_once("../_intro.php");
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

@ini_set('display_errors', '0');
ob_start(); // capture any stray output so we can return clean JSON

header('Content-Type: application/json; charset=utf-8');

function is_rate_limited($key, $maxAttempts = 5, $windowSeconds = 60) {
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

    if (count($timestamps) >= $maxAttempts) {
        return true;
    }

    $timestamps[] = $now;
    @file_put_contents($filePath, json_encode(array_values($timestamps)), LOCK_EX);
    return false;
}

function send_json($payload, $status = 200){
    while (ob_get_level()) ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$user = trim($input['user'] ?? '');
$pass = (string)($input['pass'] ?? '');
$rememberme = isset($input['rememberme']) && intval($input['rememberme']) === 1 ? 1 : 0;

$session_csrf = $_SESSION['csrftokken'] ?? $_SESSION['csrf_token'] ?? null;
$csrf_in = $input['csrf_token'] ?? '';

$response = ['status' => 'error', 'fieldErrors' => [], 'globalError' => ''];

if (empty($session_csrf) || empty($csrf_in) || !hash_equals($session_csrf, $csrf_in)) {
    $response['globalError'] = 'Invalid CSRF token.';
    send_json($response, 400);
}

if ($user === '') $response['fieldErrors']['login-username'] = 'Please enter your username or email.';
if ($pass === '') $response['fieldErrors']['login-password'] = 'Please enter your password.';
if (!empty($response['fieldErrors'])) send_json($response);

$rawIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = trim(explode(',', $rawIp)[0]);
$ip = substr($ip, 0, 255);
$targetAccount = strtolower($user);

if (is_rate_limited("login_ip_" . $ip, 10, 60)) {
    send_json(['status' => 'tooMany', 'message' => 'Too many login attempts from this IP. Please wait a minute.'], 429);
}

if (!empty($targetAccount) && is_rate_limited("login_user_" . $targetAccount, 5, 60)) {
    send_json(['status' => 'tooMany', 'message' => 'Too many attempts for this account. Please wait a minute.'], 429);
}

$send_invalid_credentials = function() use (&$response) {
    $response['fieldErrors']['login-username'] = 'Invalid username or password.';
    send_json($response);
};

$stmt = $mysqli->prepare("SELECT member_id, username, email, password, verified, disabled, deleted FROM {$dbprefix}members WHERE username = ? OR email = ? LIMIT 1");
if (!$stmt) { $response['globalError'] = 'Database error.'; send_json($response, 500); }
$stmt->bind_param('ss', $user, $user);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    $stmt->close();
    password_verify($pass, '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe105v.321g9y95.4.123456789012345');
    $send_invalid_credentials();
}

$row = $res->fetch_assoc();
$stmt->close();

$ok = false;
if (!empty($row['password'])) {
    if (password_verify($pass, $row['password'])) {
        $ok = true;
        if (password_needs_rehash($row['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($pass, PASSWORD_DEFAULT);
            $u = $mysqli->prepare("UPDATE {$dbprefix}members SET password = ? WHERE member_id = ?");
            if ($u) { $u->bind_param('si', $newHash, $row['member_id']); $u->execute(); $u->close(); }
        }
    } else {
        if (strlen($row['password']) > 3 && crypt($pass, $row['password']) === $row['password']) {
            $ok = true;
            $newHash = password_hash($pass, PASSWORD_DEFAULT);
            $u = $mysqli->prepare("UPDATE {$dbprefix}members SET password = ? WHERE member_id = ?");
            if ($u) { $u->bind_param('si', $newHash, $row['member_id']); $u->execute(); $u->close(); }
        }
    }
}

if (!$ok) {
    $send_invalid_credentials();
}


if ((int)$row['verified'] !== 1) {
    $_SESSION['email'] = $row['email'];
    send_json(['status'=>'verify', 'redirect'=>MAIN_ROOT . 'verify.php']);
}

if ((int)$row['disabled'] === 1) { 
    $response['globalError'] = 'Account disabled.';
    $response['fieldErrors'] = null;
    send_json($response); 
}

if ((int)$row['deleted'] === 1) { 
    $response['globalError'] = 'Account does not exist or has been deleted'; 
    $response['fieldErrors'] = null;
    send_json($response); 
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$row['member_id'];
$_SESSION['RememberMe'] = $rememberme ? 1 : 0;
$wsToken = bin2hex(random_bytes(32));
$_SESSION['ws_token'] = $wsToken;

$u = $mysqli->prepare("UPDATE {$dbprefix}members SET ipaddress = ?, ws_token = ? WHERE member_id = ?");
if ($u) { $u->bind_param('ssi', $ip, $wsToken, $row['member_id']); $u->execute(); $u->close(); }

if ($rememberme === 1) {
    if ($mysqli->query("SHOW TABLES LIKE 'remember_me_tokens'")->num_rows) {
        $selector = bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(33));
        $token_hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 86400 * 30);
        $ins = $mysqli->prepare("INSERT INTO {$dbprefix}remember_me_tokens (selector, token_hash, member_id, expires) VALUES (?, ?, ?, ?)");
        if ($ins) { 
            $ins->bind_param('ssis', $selector, $token_hash, $row['member_id'], $expires); 
            $ins->execute(); 
            $ins->close(); 
            
            $isSecure = (getHTTP() === "https://");
            setcookie('rememberme', $selector . ':' . $token, [
                'expires'  => time() + 86400 * 30,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }
}

send_json(['status'=>'success','redirect'=>MAIN_ROOT]);