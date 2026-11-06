<?php

include_once("../_intro.php");

if($_POST['CSRF_TOKKEN'] != $_SESSION['csrftokken']) {
	exit();
}

$LOGIN_FAIL = true;

if(!isset($_SESSION['user_id'])) {
    exit();
}

$data['status'] = 'error';
$data['msg'] = '';

$mysqli->query("DELETE FROM ".$dbprefix."Notifications WHERE target_user_id = '".$_SESSION['user_id']."'");

$data['status'] = 'success';
$data['msg'] = 'Notifications have been deleted.';

echo json_encode($data);

?>