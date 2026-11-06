<?php
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

$stmt = $mysqli->prepare("INSERT IGNORE INTO site_visits (ip_address, visit_date, user_agent) VALUES (?, CURDATE(), ?)");
$stmt->bind_param("ss", $ip, $ua);
$stmt->execute();


if (!isset($_SESSION['user_id']) && !empty($_COOKIE['rememberme'])) {
	
	if (strpos($_COOKIE['rememberme'], ':') === false) {
		setcookie('rememberme', '', time() - 3600, '/');
		return;
	}
	
	list($selector, $token) = explode(':', $_COOKIE['rememberme'], 2);
	
    $stmt = $mysqli->prepare("SELECT member_id, token_hash, expires FROM {$dbprefix}remember_me_tokens WHERE selector = ? LIMIT 1");
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($member_id, $token_hash, $expires);
        $stmt->fetch();

        if (strtotime($expires) >= time()) {

            if (hash_equals($token_hash, hash('sha256', $token))) {

                $_SESSION['user_id'] = $member_id;

                $new_token = bin2hex(random_bytes(33));
                $new_token_hash = hash('sha256', $new_token);
                $new_expires = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

                $stmt_update = $mysqli->prepare("
                    UPDATE {$dbprefix}remember_me_tokens
                    SET token_hash = ?, expires = ?
                    WHERE selector = ?
                ");
                $stmt_update->bind_param("sss", $new_token_hash, $new_expires, $selector);
                $stmt_update->execute();

                setcookie(
                    'rememberme',
                    $selector . ':' . $new_token,
                    time() + (86400 * 30),
                    '/',
                    '',
                    true,
                    true
                );
            } else {
                setcookie('rememberme', '', time() - 3600, '/');
				$stmt_delete = $mysqli->prepare("DELETE FROM {$dbprefix}remember_me_tokens WHERE selector = ?");
				$stmt_delete->bind_param("s", $selector);
				$stmt_delete->execute();
				setcookie('rememberme', '', time() - 3600, '/');
            }
			
        } else {
            $stmt_delete = $mysqli->prepare("DELETE FROM {$dbprefix}remember_me_tokens WHERE selector = ?");
            $stmt_delete->bind_param("s", $selector);
            $stmt_delete->execute();

            setcookie('rememberme', '', time() - 3600, '/');
        }
    }
}




if(isset($_SESSION['user_id'])) {
	
	$stmt = $mysqli->prepare("SELECT * FROM {$dbprefix}members WHERE member_id = ?");
	$stmt->bind_param("i", $_SESSION['user_id']);
	$stmt->execute();
	$result = $stmt->get_result();
	
	if($result->num_rows > 0) {
		
		define("LOGGED_IN", true);
		
		$row = $result->fetch_assoc();
		$stmt->close();
		
		$memberUsername = renderStyledUsername($row['username'], $row['username_style']);
		$row_member_id = $row['member_id'];
		$row_avatar = $row['avatar'];
		$memberRank = $row['rank_id'];
		
		$memberAvatar = '
			<img src="'.MAIN_ROOT.$row_avatar.'" width="26px" height="26px" style="border-radius: 25px;">
		';
		
		$memberAvatar2 = '
			<img src="'.MAIN_ROOT.$row_avatar.'" width="24px" height="24px" style="border-radius: 25px;">
		';
		
		$dropdown_arrow = '
			<img src="'.MAIN_ROOT.'assets/images/menu.png" style="width: 12px; height: 12px;">
		';
		
		if($row['disabled'] == 1 OR $row['disabled'] == 2) {
			if(isset($_COOKIE['rememberme'])) {
				setcookie("rememberme", "", time()-(86400 * 30), $MAIN_ROOT);
			}
			unset($_SESSION['user_id']);
			if(isset($_SESSION['RememberMe'])) {
				unset($_SESSION['RememberMe']);
			}
		}
		
		$totalNewNTs = $mysqli->query("SELECT COUNT(*) FROM ".$dbprefix."notifications WHERE target_user_id = '$row_member_id' AND seen = 0")->fetch_row()[0];
		
		if($totalNewNTs > 0 and !defined("NOTIFS_PAGE") == true){
			$dispNTCount = $totalNewNTs;
			$badge_style = '';
		}else {
			$badge_style = ' display: none;';
			$dispNTCount = '';
		}
			
		
		$displayLogin = '
			
			<div class="headerRightItemsDiv">
				
                <a href="'.MAIN_ROOT.'wallet.php" style="position: relative;" onclick="event.preventDefault(); if (window.fetchFragment) window.fetchFragment(\'' . htmlspecialchars(MAIN_ROOT . 'fragment/wallet.php') . '\', true); else location.href=\'' . htmlspecialchars(MAIN_ROOT . 'wallet.php') . '\';">
					<img width="28px" height="28px" src="'.MAIN_ROOT.'assets/images/wallet.png">
				</a>
                
				<a data-async href="'.MAIN_ROOT.'notifications.php" style="position: relative;" onclick="event.preventDefault(); if (window.fetchFragment) window.fetchFragment(\'' . htmlspecialchars(MAIN_ROOT . 'fragment/notifications.php') . '\', true); else location.href=\'' . htmlspecialchars(MAIN_ROOT . 'notifications.php') . '\';">
					<img style="width: 28px; height: 28px;" src="'.MAIN_ROOT.'assets/images/notification.png">
					<b style="position: absolute; top: -3px; right: -3px; background-color: #cc0000; color: white; border-radius: 10px; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 500; padding: 0 6px; box-sizing: border-box; border: 2px solid white;'.$badge_style.'"  id="notificationBadge">
						'.$dispNTCount.'
					</b>
				</a>
				
				<div style="position: relative;">
					<button onclick="toggleUserDropdown(this)" class="dropdown-button" style="position: relative;">
						'.$dropdown_arrow.$memberAvatar2.'
					</button>
					
					<div id="userDropdown" class="dropdown-menu" style="display: none; right: -20px; top: 45px; width: 200px;">
						<a data-async href="'.MAIN_ROOT.'member.php?mID='.$row_member_id.'" class="dropdown-item" style="border-bottom: 1px solid rgba(0,0,0,0.1);" onclick="event.preventDefault(); if (window.fetchFragment) window.fetchFragment(\'' . htmlspecialchars(MAIN_ROOT . 'fragment/member.php?mID='.$row_member_id.'') . '\', true); else location.href=\'' . htmlspecialchars(MAIN_ROOT . 'member.php?mID='.$row_member_id.'') . '\';">
							<div style="display: flex; align-items: center; gap: 10px;">
								'.$memberAvatar.'
								<span>'.$memberUsername.'</span>
							</div>
						</a>
						
						<a href="'.MAIN_ROOT.'settings.php" class="dropdown-item" style="border-bottom: 1px solid rgba(0,0,0,0.1);">
							<div style="display: flex; align-items: center; gap: 10px;">
								<img width="20" height="20" src="'.MAIN_ROOT.'assets/images/edit.png" alt="Settings">
								<span>Settings</span>
							</div>
						</a>
						
						<a href="'.MAIN_ROOT.'backend/signout.php" class="dropdown-item dropdown-item-danger">
							<div style="display: flex; align-items: center; gap: 10px;">
								<img width="20" height="20" src="'.MAIN_ROOT.'assets/images/signout.png" alt="Sign Out">
								<span>Sign Out</span>
							</div>
						</a>
					</div>
				</div>
			</div>
			
			<script>
				function toggleUserDropdown(buttonElement) {
					const dropdown = buttonElement.nextElementSibling;
					const isVisible = dropdown.style.display === "block";
					dropdown.style.display = isVisible ? "none" : "block";
					if (!isVisible) {
						const clickHandler = function(e) {
							if (!dropdown.contains(e.target) && !e.target.closest(".dropdown-button")) {
								dropdown.style.display = "none";
								document.removeEventListener("click", clickHandler);
							}
						};
						setTimeout(() => document.addEventListener("click", clickHandler), 10);
					}
				}
				document.querySelectorAll(".dropdown-item").forEach(item => {
					item.addEventListener("click", function() {
						this.closest(".dropdown-menu").style.display = "none";
					});
				});
			</script>
			
		';
	}
}

if(!defined("LOGGED_IN")) {
	
	$displayLogin = '
		<div class="headerRightItemsDiv">
        	
			<a href="'.MAIN_ROOT.'notifications.php" style="position: relative;">
				<img style="width: 24px; height: 24px;" src="'.MAIN_ROOT.'assets/images/notification.png">
				<b class="nt_badge" style="display: none;" id="notificationBadge"></b>
			</a>
			
            <button id="loginBtn" class="btn" style="padding: 6px 14px; background: transparent; color: #f8f9fa; border: 2px solid #2a2f2c; border-radius: 3px; font-family: Orbitron">
            	LOG IN
            </button>
            
		</div>
		
		<script>
		document.getElementById("loginBtn").addEventListener("click", function() {
			window.location.href = "'.MAIN_ROOT.'auth.php";
		});
		</script>
	';
	
}
// ~2% chance per page load
if (rand(1, 50) === 1) {
    $mysqli->query("DELETE FROM {$dbprefix}request_logs WHERE created_at < (NOW() - INTERVAL 1 DAY)");
	$mysqli->query("DELETE FROM {$dbprefix}remember_me_tokens WHERE expires < NOW()");
}

?>

<html>

<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
	
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    
	<meta name="description" content="Open-source tournament management platform for Xonotic. Join or create community events, compete in ongoing tournaments, and follow brackets, schedules, and results">
    <meta name="keywords" content="Xonotic, tournament organizer, FPS duels, open source gaming, arena shooter, gaming brackets">
    
	<base href="<?php echo htmlspecialchars($MAIN_ROOT); ?>">
	
	<title><?php echo $PAGE_NAME.$DOMAIN_NAME; ?></title>
	
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://duelmasters.gamer.gd/">
    <meta property="og:title" content="Duel Masters: Xonotic Tournament Platform">
    <meta property="og:description" content="The community-driven hub for Xonotic competitive play. View brackets and join the upcoming tournaments.">
    
    <link rel="preload" href="<?php echo $MAIN_ROOT;?>assets/fonts/Orbitron.woff2" as="font" type="font/woff2" crossorigin>
	<link rel="stylesheet" type="text/css" href="<?php echo $MAIN_ROOT;?>assets/css/style.css">
    
</head>

<body class='body' id='body'>
	
    <div class='afterheader'></div>
	
	<div class='header'>
		<div class='headerDiv'>
			
			<div class='headerItemsDiv'>
				
				<a href="<?= htmlspecialchars($MAIN_ROOT . '') ?>" style="font-size: 20px;">
					<img src="<?php echo htmlspecialchars($MAIN_ROOT); ?>assets/images/logo.png" width="42" height="42" />
				</a>
				
				<a href="<?= htmlspecialchars($MAIN_ROOT . 'competitions/') ?>" <?php if(defined("COMPETITIONS_LINK")) { echo "class='link_active'"; }?> style="color: #dbefff">
					<div class='headerItem' style='padding: 10px 15px;'><b>Competitions</b></div>
				</a>
                
                <a href="<?= htmlspecialchars($MAIN_ROOT . 'leaderboard.php') ?>" <?php if(defined("LEADERBOARD_LINK")) { echo "class='link_active'"; }?> style="color: #dbefff">
					<div class='headerItem' style='padding: 10px 15px;'><b>Leaderboard</b></div>
				</a>
				
			</div>
			
			<?php echo $displayLogin; ?>
			
		</div>
	</div>