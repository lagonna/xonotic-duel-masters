<?php

include_once(__DIR__ . '/../_intro.php');
if (session_status() === PHP_SESSION_NONE) session_start();

@ini_set('display_errors', '0');
ob_start();
header('Content-Type: application/json; charset=utf-8');

$response = [
    'status' => 'error',
    'fieldErrors' => (object)[],
    'globalError' => '',
    'message' => ''
];

function send_json($payload) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}


function add_field_error(&$resp, $inputId, $msg) {
    if (!isset($resp['fieldErrors']) || !is_array($resp['fieldErrors'])) $resp['fieldErrors'] = [];
    $resp['fieldErrors'][$inputId] = $msg;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;

$raw = file_get_contents('php://input');
$input = [];

if ($isMultipart) {
    $input = $_POST;
} else {
    $input = json_decode($raw, true) ?: [];
}

$csrf_in = $input['csrf_token'] ?? $input['CSRF_TOKKEN'] ?? $input['CSRF'] ?? '';

$session_csrf = $_SESSION['csrftokken'] ?? $_SESSION['csrf_token'] ?? null;

if (!isset($_SESSION['user_id'])) {
    $response['globalError'] = 'Not logged in.';
    send_json($response);
}
$user_id = (int) $_SESSION['user_id'];

if (empty($session_csrf) || empty($csrf_in) || !hash_equals((string)$session_csrf, (string)$csrf_in)) {
    $response['globalError'] = 'Invalid CSRF token.';
    send_json($response);
}

$action = trim($input['action'] ?? ($isMultipart ? ($_POST['action'] ?? '') : ''));
if ($action === '') {
    $response['globalError'] = 'No action specified.';
    send_json($response);
}

$rate_limits = [
    'avatar'   => 10,
    'username' => 5,
    'bio'      => 5,
    'country'  => 3,
    'socials'  => 5,
    'email'    => 3,
    'password' => 7
];
$limit = $rate_limits[$action] ?? 5;

$stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM request_logs WHERE user_id = ? AND type = ? AND created_at > (NOW() - INTERVAL 1 MINUTE)");
if ($stmt) {
    $stmt->bind_param('is', $user_id, $action);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $count = (int)($row['cnt'] ?? 0);
    $stmt->close();
    if ($count >= $limit) {
        send_json(['status' => 'tooMany', 'message' => 'Too many requests. Please wait a minute.'], 429);
    }
}

$logStmt = $mysqli->prepare("INSERT INTO request_logs (user_id, type) VALUES (?, ?)");
if ($logStmt) {
    $logStmt->bind_param('is', $user_id, $action);
    $logStmt->execute();
    $logStmt->close();
}


switch ($action) {

    case 'avatar':
        if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            add_field_error($response, 'fileUpload', 'Please provide an image.');
            send_json($response);
        }

        if (!function_exists('validate_image_upload')) {
            $response['globalError'] = 'Server configuration error: validate_image_upload() not available.';
            send_json($response);
        }

        $uploadDir = realpath(__DIR__ . '/../images/avatar/');
        if ($uploadDir === false) {
            $baseDir = rtrim(BASE_DIRECTORY ?? (__DIR__ . '/..'), '/');
            $uploadDir = $baseDir . '/images/avatar/';
        }

        $result = validate_image_upload($_FILES['image'], $uploadDir, "avatar_");

        if (!is_array($result) || !isset($result['status'])) {
            $response['globalError'] = 'Invalid image upload result from server helper.';
            send_json($response0);
        }

        if ($result['status'] === 'success') {
            $original = $result['original'] ?? null;
            $thumb = $result['thumb'] ?? null;

            $stmt = $mysqli->prepare("SELECT profilepic, avatar FROM {$dbprefix}members WHERE member_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $old = $res && $res->num_rows ? $res->fetch_assoc() : null;
            $stmt->close();

            $stmt = $mysqli->prepare("UPDATE {$dbprefix}members SET profilepic = ?, avatar = ? WHERE member_id = ?");
            $stmt->bind_param('ssi', $original, $thumb, $user_id);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                if ($old) {
                    if (!empty($old['avatar']) && $old['avatar'] !== 'images/thumbs/defaultprofile.png') {
                        @unlink(BASE_DIRECTORY . $old['avatar']);
                    }
                    if (!empty($old['profilepic']) && $old['profilepic'] !== 'images/avatar/defaultprofile.png') {
                        @unlink(BASE_DIRECTORY . $old['profilepic']);
                    }
                }

                $response['status'] = 'success';
                $response['message'] = $result['msg'] ?? 'Avatar updated successfully!';
                if (!empty($original)) $response['new_profilepic'] = $original;
                if (!empty($thumb)) $response['thumb'] = $thumb;
                send_json($response);
            } else {
                $response['globalError'] = 'Database update failed.';
                send_json($response);
            }
        } else {
            $errMsg = $result['msg'] ?? 'Image validation failed.';
            add_field_error($response, 'fileUpload', $errMsg);
            send_json($response);
        }
        break;

    case 'username':
        $username = (string)($input['username'] ?? '');
        $username_style = (string)($input['username_style'] ?? '[]');

        $username = str_replace("\0", '', $username);
        $username = preg_replace("/\r\n?/", "\n", $username);

        $username_len = function_exists('mb_strlen') ? mb_strlen($username, 'UTF-8') : strlen($username);

        if ($username_len < 2) {
            add_field_error($response, 'username', 'Username must be at least 2 characters long.');
            send_json($response);
        }

        if ($username_len > 30) {
            add_field_error($response, 'username', 'Username must be 30 characters or less.');
            send_json($response);
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $username)) {
            add_field_error($response, 'username', 'Username cannot contain control characters.');
            send_json($response);
        }

        $style_arr = json_decode($username_style, true);
        if (!is_array($style_arr)) {
            add_field_error($response, 'username', 'Invalid username style data.');
            send_json($response);
        }

        $allowed_hex = '/^#[0-9a-fA-F]{6}$/';
        
        $concatenated_style_text = ''; 

        foreach ($style_arr as $run) {
            if (!is_array($run)) {
                add_field_error($response, 'username', 'Invalid username style data.');
                send_json($response);
            }

            $text = (string)($run['text'] ?? '');
            $color = (string)($run['color'] ?? '');

            if ($text === '') {
                add_field_error($response, 'username', 'Invalid username style data.');
                send_json($response);
            }

            if (!preg_match($allowed_hex, $color)) {
                add_field_error($response, 'username', 'Invalid color value in username style.');
                send_json($response);
            }

            if (preg_match('/[\x00-\x1F\x7F]/', $text)) {
                add_field_error($response, 'username', 'Username style contains invalid characters.');
                send_json($response);
            }

            $concatenated_style_text .= $text;
        }

        if (!empty($style_arr) && $concatenated_style_text !== $username) {
            add_field_error($response, 'username', 'Styled text must match your actual username exactly.');
            send_json($response);
        }

        $stmt = $mysqli->prepare("SELECT member_id FROM {$dbprefix}members WHERE username = ? AND member_id != ? LIMIT 1");
        $stmt->bind_param('si', $username, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = ($res && $res->num_rows > 0);
        $stmt->close();

        if ($exists) {
            add_field_error($response, 'username', 'This username is already taken.');
            send_json($response);
        }

        $stmt = $mysqli->prepare("UPDATE {$dbprefix}members SET username = ?, username_style = ? WHERE member_id = ?");
        if (!$stmt) {
            $response['globalError'] = 'Database error.';
            send_json($response);
        }

        $style_json = json_encode($style_arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt->bind_param('ssi', $username, $style_json, $user_id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $response['status'] = 'success';
            $response['message'] = 'Username has been updated successfully.';
            send_json($response);
        } else {
            $response['globalError'] = 'Database error.';
            send_json($response);
        }
        break;

    case 'bio':
        $bio = trim($input['bio'] ?? '');
        $max_length = 500;
        $bio_len = function_exists('mb_strlen') ? mb_strlen($bio, 'UTF-8') : strlen($bio);
		if ($bio_len > $max_length) {
            add_field_error($response, 'bio', 'Bio is too long (max ' . $max_length . ' characters).');
            send_json($response);
        }

        $stmt = $mysqli->prepare("UPDATE {$dbprefix}members SET bio = ? WHERE member_id = ?");
        $stmt->bind_param('si', $bio, $user_id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $response['status'] = 'success';
            $response['message'] = 'Bio updated successfully!';
            send_json($response);
        } else {
            add_field_error($response, 'bio', 'Database error. Please try again.');
            send_json($response);
        }
        break;

    case 'country':
        $country = trim($input['country'] ?? '');
        $allowed = [
            '' => 'Not set',
            'US'=>'United States','GB'=>'United Kingdom','CA'=>'Canada','DE'=>'Germany','FR'=>'France',
            'ES'=>'Spain','IT'=>'Italy','NL'=>'Netherlands','SE'=>'Sweden','AU'=>'Australia',
            'IN'=>'India','JP'=>'Japan','CN'=>'China','DZ'=>'Algeria','MA'=>'Morocco','EG'=>'Egypt'
        ];
        if (!array_key_exists($country, $allowed)) {
            add_field_error($response, 'countryInput', 'Invalid country');
            send_json($response);
        }
        $stmt = $mysqli->prepare("UPDATE {$dbprefix}members SET country = ? WHERE member_id = ?");
        $stmt->bind_param('si', $country, $user_id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $response['globalError'] = 'Failed to save country';
            send_json($response);
        }

        $flag_rel_path = 'assets/images/flags/' . $country . '.png';
        $flag_full_path = __DIR__ . '/../' . $flag_rel_path;
        $flag_url = '';
        if ($country !== '' && file_exists($flag_full_path)) {
            $flag_url = (isset($MAIN_ROOT) ? rtrim($MAIN_ROOT, '/') . '/' : '/') . $flag_rel_path;
        }

        $response['status'] = 'success';
        $response['message'] = 'Country updated';
        $response['country'] = $country;
        $response['country_name'] = $allowed[$country] ?? '';
        if ($flag_url) $response['flag_url'] = $flag_url;
        send_json($response);
        break;

    case 'socials':
        $facebook = trim($input['facebook'] ?? '');
        $twitch = trim($input['twitch'] ?? '');
        $youtube = trim($input['youtube'] ?? '');

        $extract_handle = function($s) {
            $s = trim($s);
            if ($s === '') return null;
            if (stripos($s, 'http') === 0 || stripos($s, 'www.') === 0) {
                $u = @parse_url($s);
                if ($u && isset($u['path'])) {
                    $path = trim($u['path'], '/');
                    if ($path === '') return null;
                    $parts = explode('/', $path);
                    return end($parts);
                } else {
                    return null;
                }
            }
            return ltrim($s, '@');
        };

        $facebook_h = $facebook !== '' ? $extract_handle($facebook) : null;
        $twitch_h = $twitch !== '' ? $extract_handle($twitch) : null;
        $youtube_h = $youtube !== '' ? $extract_handle($youtube) : null;

        if ($twitch_h !== null && !preg_match('/^[a-z0-9_]{4,25}$/i', $twitch_h)) {
            add_field_error($response, 'twitch', 'Invalid Twitch username');
            send_json($response);
        }
        if ($facebook_h !== null && !preg_match('/^[A-Za-z0-9\.]{3,100}$/', $facebook_h)) {
            add_field_error($response, 'facebook', 'Invalid Facebook handle');
            send_json($response);
        }
        if ($youtube_h !== null) {
            if (strpos($youtube_h, 'UC') === 0) {
                if (!preg_match('/^UC[0-9A-Za-z_\-]{22}$/', $youtube_h)) {
                    add_field_error($response, 'youtube', 'Invalid YouTube channel ID');
                    send_json($response);
                }
            } else {
                if (!preg_match('/^[A-Za-z0-9_\-]{2,100}$/', $youtube_h)) {
                    add_field_error($response, 'youtube', 'Invalid YouTube handle');
                    send_json($response);
                }
            }
        }

        $facebook_db = $facebook_h !== null ? $facebook_h : null;
        $twitch_db = $twitch_h !== null ? $twitch_h : null;
        $youtube_db = $youtube_h !== null ? $youtube_h : null;

        $stmt = $mysqli->prepare("UPDATE {$dbprefix}members SET facebook = ?, twitch = ?, youtube = ? WHERE member_id = ?");
        $stmt->bind_param('sssi', $facebook_db, $twitch_db, $youtube_db, $user_id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $response['status'] = 'success';
            $response['message'] = 'Social links updated';
            send_json($response);
        } else {
            add_field_error($response, 'facebook', 'Database error');
            send_json($response);
        }
        break;

    case 'password':
        $current_pass = $input['current_pass'] ?? '';
        $new_pass = $input['new_pass'] ?? '';

        if ($current_pass === '') {
            add_field_error($response, 'password', 'Please enter your current password.');
            send_json($response);
        }

        $stmt = $mysqli->prepare("SELECT password FROM {$dbprefix}members WHERE member_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res && $res->num_rows ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            $response['globalError'] = 'Account not found.';
            send_json($response);
        }

        $realPassword = $row['password'];

        if (!password_verify($current_pass, $realPassword)) {
            add_field_error($response, 'password', 'You entered an incorrect current password.');
			send_json($response);
        }

        if ($new_pass === '') {
            add_field_error($response, 'newPassword', 'Please enter a new password.');
            send_json($response);
        }
        if (strlen($new_pass) < 10) {
            add_field_error($response, 'newPassword', 'Password must be at least 10 characters long.');
            send_json($response);
        }
        if (!preg_match('/[A-Z]/', $new_pass)) {
            add_field_error($response, 'newPassword', 'Password must contain at least one uppercase letter (A-Z).');
            send_json($response);
        }
        if (!preg_match('/[a-z]/', $new_pass)) {
            add_field_error($response, 'newPassword', 'Password must contain at least one lowercase letter (a-z).');
            send_json($response);
        }
        if (!preg_match('/[0-9]/', $new_pass)) {
            add_field_error($response, 'newPassword', 'Password must contain at least one number (0-9).');
            send_json($response);
        }
        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $new_pass)) {
            add_field_error($response, 'newPassword', 'Password must contain at least one symbol (! @ # $ % etc.).');
            send_json($response);
        }
        if (strlen($new_pass) > 64) {
            add_field_error($response, 'newPassword', 'Max password length is 64 characters.');
            send_json($response);
        }
		if ($new_pass === $current_pass) {
			add_field_error($response, 'newPassword', 'New password must be different from the current password.');
			send_json($response);
		}

        if (function_exists('encryptPassword')) {
            $passwordInfo = encryptPassword($new_pass);
            $hash = $passwordInfo['password'];
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        }

        $stmt = $mysqli->prepare("UPDATE {$dbprefix}members SET password = ? WHERE member_id = ?");
        $stmt->bind_param('si', $hash, $user_id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $response['status'] = 'success';
            $response['message'] = 'Password was updated with success.';
            send_json($response);
        } else {
            add_field_error($response, 'newPassword', 'Database error updating password.');
            send_json($response);
        }
        break;

    case 'email':
        $email = trim(strtolower($input['email'] ?? ''));

        if ($email === '') {
            add_field_error($response, 'emailInput', 'Please enter an email address.');
            send_json($response);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            add_field_error($response, 'emailInput', 'Please enter a valid e-mail address.');
            send_json($response);
        }

        $stmt = $mysqli->prepare("SELECT member_id FROM {$dbprefix}members WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $rowDup = $res->fetch_assoc();
                if ((int)$rowDup['member_id'] !== (int)$user_id) {
                    add_field_error($response, 'emailInput', 'This email is already used by another account.');
                    send_json($response);
                }
            }
            $stmt->close();
        }

        $stmt = $mysqli->prepare("UPDATE {$dbprefix}members SET email = ? WHERE member_id = ?");
        if (!$stmt) {
            $response['globalError'] = 'Database error.';
            send_json($response);
        }
        $stmt->bind_param('si', $email, $user_id);
        if (!$stmt->execute()) {
            $stmt->close();
            $response['globalError'] = 'Failed to save email.';
            send_json($response);
        }
        $stmt->close();

        $response['status'] = 'success';
        $response['message'] = 'Email saved.';
        send_json($response);
        break;

    default:
        $response['globalError'] = 'Unknown action.';
        send_json($response);
        break;
}

$response['globalError'] = 'Unhandled error';
send_json($response);