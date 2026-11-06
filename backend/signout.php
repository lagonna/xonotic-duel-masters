<?php

$prevFolder = "../";
include("../_intro.php");


if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}

if (!empty($_COOKIE['rememberme']) && strpos($_COOKIE['rememberme'], ':') !== false) {
    list($selector, $token) = explode(':', $_COOKIE['rememberme'], 2);

    $stmt = $mysqli->prepare("
        DELETE FROM {$dbprefix}remember_me_tokens
        WHERE selector = ?
    ");
    $stmt->bind_param("s", $selector);
    $stmt->execute();

    setcookie("rememberme", "", time() - 3600, '/', '', true, true);
}

$_SESSION['user_id'] = "";
unset($_SESSION['user_id']);
session_destroy();

echo "
    <script type='text/javascript'>
        window.location = '".$MAIN_ROOT."';
    </script>
";
?>
