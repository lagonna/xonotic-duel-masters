<?php
include_once("_intro.php");

define("NOTIFS_PAGE", true);

$prevFolder = "";
$PAGE_NAME = "Notifications - ";

include($prevFolder . "assets/_header.php");

if (!isset($_SESSION['user_id'])) {
	?>
	<main id="app-root" class="container" role="main" tabindex="-1">
		
            <div style="
                border: 1px solid #2a2f2c;
                border-left: 5px solid #ffeb3b; 
                padding: 16px 20px; 
                border-radius: 8px; 
                display: flex; 
                align-items: center; 
                justify-content: space-between;
                max-width: 450px;
                margin: 20px auto;
                color: #ffffff;
            ">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffeb3b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>

                    <div>
                        <p style="color: #ffeb3b; margin: 0; font-weight: 600; font-size: 14px; letter-spacing: 0.5px;">
                            YOU ARE NOT LOGGED IN!
                        </p>
                        <p style="margin: 4px 0 0 0; font-size: 13px; color: #b9bbbe;">
                        	You need to authenticate to access this page.
                        </p>
                    </div>
                </div>
			<div>
		
	</main>
    
	<?php
	include($prevFolder . "assets/_footer.php");
	exit();
}

$user_id = (int) $_SESSION['user_id'];

$batchSize = 5;
$notifications = [];
$sql = "SELECT id, target_user_id, type, payload, seen, created_at
        FROM {$dbprefix}notifications
        WHERE target_user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT ?";
if ($stmt = $mysqli->prepare($sql)) {
	$stmt->bind_param("ii", $user_id, $batchSize);
	$stmt->execute();
	$res = $stmt->get_result();
	while ($row = $res->fetch_assoc()) {
		$notifications[] = $row;
	}
	$res->free();
	$stmt->close();
} else {
	echo "<main id='app-root' class='container' role='main' tabindex='-1'><div><p align='center'>Unable to load notifications.</p></div></main>";
	include($prevFolder . "assets/_footer.php");
	exit();
}

$notifications_count = count($notifications);
$show_load_more = ($notifications_count === $batchSize);

$last_created_at = $notifications_count ? $notifications[$notifications_count - 1]['created_at'] : null;
$last_notif_id   = $notifications_count ? (int)$notifications[$notifications_count - 1]['id'] : null;

$js_last_created_at = json_encode($last_created_at);
$js_last_notif_id   = json_encode($last_notif_id);

?>

<main id="app-root" class="container" role="main" tabindex="-1">
	
	<div style="display:flex; justify-content:center; align-items:center;"></div>

	<?php if ($notifications_count > 0): ?>
		<div class="clear-all-div">
			<h2 style="font-size:1.4rem; margin:0 0 6px 0;">Notifications</h2>
			<button id="clear-all-btn" style="border:none;background:transparent;color:#9ca3af;border-radius:5px;cursor:pointer;font-size:15px;font-weight:bold;width:fit-content;">
				Clear All
			</button>
		</div>
	<?php endif; ?>

	<div id="notifications-container" class="notifications-list">
		<?php
		if ($notifications_count > 0) {
			foreach ($notifications as $notification) {
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
                    $overlay_img = "";
				} elseif ($action === "notification") {
					$avatar_url = $MAIN_ROOT . "assets/images/notification.png";
                    $overlay_img = "";
				} elseif ($action === "tournament") {
					$avatar_url = $MAIN_ROOT . "assets/images/tournament.png";
				} elseif ($action === "match") {
					if (!empty($payload['avatar_url'])) {
						$avatar_url = (strpos($payload['avatar_url'], 'http://') === 0 || strpos($payload['avatar_url'], 'https://') === 0)
							? $payload['avatar_url']
							: $MAIN_ROOT . ltrim($payload['avatar_url'], '/');
						
					}
					
					$overlay_img = $MAIN_ROOT . "assets/images/tournament.png";
					
				} elseif ($action === "join") {
					if (!empty($payload['avatar_url'])) {
						$avatar_url = (strpos($payload['avatar_url'], 'http://') === 0 || strpos($payload['avatar_url'], 'https://') === 0)
							? $payload['avatar_url']
							: $MAIN_ROOT . ltrim($payload['avatar_url'], '/');
						
					}
					
					$overlay_img = $MAIN_ROOT . "assets/images/tournament.png";
					
				}
				
				$subject = $payload['subject_html'] ?? 'Notification';
				
				?>
				<div class="notification-card <?php echo $seen_class; ?>"<?php echo $created_iso ? ' data-created-utc="'.htmlspecialchars($created_iso, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'"' : ''; ?>>
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
		} else {
			echo "
				<div class='clear-all-div'>
					<h2 style='font-size:1.4rem; margin:0 0 6px 0;'>Notifications</h2>
				</div>
				<div class='shadedBox' id='shadedBox'><p class='main' align='center'><i>No new notifications.</i></p></div>
			";
		}
		?>
	</div>

	<?php if ($show_load_more): ?>
		<div class='load-more-btn-wrapper'>
			<button id='load-more-btn' class='load-more-btn'>Load More</button>
		</div>
	<?php endif; ?>


<?php
$js_user_id = json_encode($user_id);
$js_csrf = json_encode($_SESSION['csrf_token'] ?? ($_SESSION['csrftokken'] ?? ''));
$js_main_root = json_encode($MAIN_ROOT);
$js_batch_size = json_encode($batchSize);

$js_last_created_at = $js_last_created_at;
$js_last_notif_id = $js_last_notif_id;
?>

<?php
include($prevFolder . "assets/js/notification_js.php");
?>

<script>
(function () {
    "use strict";

    function timeAgoFromDate(d) {
		if (!d || !(d instanceof Date) || isNaN(d.getTime())) return '';
		
		var now = Date.now();
		var diff = Math.floor((now - d.getTime()) / 1000);

		if (diff < 0) diff = 0; 

		if (diff < 10) return 'just now';
		if (diff < 60) return diff + ' second' + (diff > 1 ? 's' : '') + ' ago';
		if (diff < 3600) {
			var m = Math.floor(diff / 60);
			return m + ' minute' + (m > 1 ? 's' : '') + ' ago';
		}
		if (diff < 86400) {
			var h = Math.floor(diff / 3600);
			return h + ' hour' + (h > 1 ? 's' : '') + ' ago';
		}
		if (diff < 2592000) {
			var days = Math.floor(diff / 86400);
			return days + ' day' + (days > 1 ? 's' : '') + ' ago';
		}
		return d.toLocaleString();
	}

    function formatNotificationTimeAgo(root) {
        try {
            var container = root || document;
            var items = container.querySelectorAll('.notification-card[data-created-utc]');
            if (!items) return;
            items.forEach(function (item) {
                var iso = item.getAttribute('data-created-utc') || '';
                var meta = item.querySelector('.date');
                if (!meta) return;
                if (!iso) {
                    return;
                }
                var d = new Date(iso);
                if (isNaN(d.getTime())) {
                    return;
                }
                meta.textContent = timeAgoFromDate(d);
            });
        } catch (e) {
            console.warn('formatNotificationTimeAgo error', e);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('notifications-container');
        if (container) formatNotificationTimeAgo(container);
    });

    setInterval(function () {
        var container = document.getElementById('notifications-container');
        if (container) formatNotificationTimeAgo(container);
    }, 60000);

    (function observe() {
        var container = document.getElementById('notifications-container');
        if (!container || !window.MutationObserver) return;
        var mo = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (!m.addedNodes) return;
                m.addedNodes.forEach(function (node) {
                    if (!(node instanceof Element)) return;
                    if (node.classList && node.classList.contains('notification-card')) {
                        formatNotificationTimeAgo(node.parentNode || document);
                    } else {
                        formatNotificationTimeAgo(node);
                    }
                });
            });
        });
        mo.observe(container, { childList: true, subtree: true });
    })();

})();
</script>

</main>


<?php
include($prevFolder . "assets/_footer.php");
?>
