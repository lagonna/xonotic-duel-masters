<?php
$prevFolder = "";
include($prevFolder . "_intro.php");

$profileId = (int)$_GET['mID'];

$stmt = $mysqli->prepare("SELECT disabled, deleted, username, username_style, bio, profilepic, datejoined, last_online, country, profileviews, member_id, facebook, twitch, youtube FROM {$dbprefix}members WHERE member_id = ?");
$stmt->bind_param("i", $profileId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit();
}

$row = $result->fetch_assoc();
$stmt->close();

if ((int)$row['deleted'] === 1) {
    $username = $row['username'] ?? 'Account';
    $PAGE_NAME = "Account Deleted - ";
    include($prevFolder . "assets/_header.php");
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
            margin: 40px auto;
            color: #ffffff;
            background: #151a21;
        ">
            <div style="display: flex; align-items: center; gap: 12px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffeb3b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div>
                    <p style="color: #ffeb3b; margin: 0; font-weight: 600; font-size: 14px; letter-spacing: 0.5px;">
                        This account does not exist or has been deleted.
                    </p>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #b9bbbe;">
                        The profile you are looking for is no longer available.
                    </p>
                </div>
            </div>
        </div>
    </main>
    <?php
    include($prevFolder . "assets/_footer.php");
    exit();
}

$username = $row['username'];
$username_style = $row['username_style'];
$bio = $row['bio'];
$profilePic = $row['profilepic'] ?: ($MAIN_ROOT . "assets/images/default_avatar.png");
$dateJoined = $row['datejoined'];
$country = $row['country'];
$profileViews = $row['profileviews'];
$memberId = (int)$row['member_id'];
$lastseen = (int)$row['last_online'];

$PAGE_NAME = $username." Profile - ";

include($prevFolder . "assets/_header.php");
?>
<main id="app-root" class="container" role="main" tabindex="-1">
<?php
if (!isset($_GET['mID']) || !is_numeric($_GET['mID'])) {
    exit();
}

if ((int)$row['disabled'] !== 0) {
    http_response_code(404);
    exit();
}

function s($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$memberSince = '';
if ($dateJoined) {
    if (is_numeric($dateJoined)) {
        $memberSince = date('M j, Y', (int)$dateJoined);
    } else {
        try {
            $dt = new DateTime($dateJoined);
            $memberSince = $dt->format('M j, Y');
        } catch (Exception $e) {
            $memberSince = s($dateJoined);
        }
    }
}

$isActive = ($lastseen && (time() - $lastseen) < 300);
if ($lastseen == 0) {
    $dispLastSeen = "Never Logged In";
} elseif ($isActive) {
    $dispLastSeen = '<span style="color: #2ecc71; font-weight: 700;">Active</span>';
} else {
    $dispLastSeen = '<span class="lastseen" data-ts="' . (int)$lastseen . '">' . gettimeago($lastseen) . '</span>';
}

$countries = [
  '' => 'Not set',
  'US'=>'United States','GB'=>'United Kingdom','CA'=>'Canada','DE'=>'Germany','FR'=>'France',
  'ES'=>'Spain','IT'=>'Italy','NL'=>'Netherlands','SE'=>'Sweden','AU'=>'Australia',
  'IN'=>'India','JP'=>'Japan','CN'=>'China','DZ'=>'Algeria','MA'=>'Morocco','EG'=>'Egypt'
];

$currentCountry = $country ?? '';
$flagsBaseUrl = $MAIN_ROOT . 'assets/images/flags/';
$country_image = '';
$country_name  = '';

if ($currentCountry && isset($countries[$currentCountry])) {

    $lowerCode = strtolower($currentCountry);

    $flagDisk = $_SERVER['DOCUMENT_ROOT'] . "/assets/images/flags/{$lowerCode}.png";

    $flagUrl  = $MAIN_ROOT . "assets/images/flags/{$lowerCode}.png";

    if (file_exists($flagDisk)) {
        $country_image = "<img src='{$flagUrl}' style='width:20px;height:15px;'>";
    }
    
    $country_name = $countries[$currentCountry];
}

function social_url($provider, $handle) {
    global $MAIN_ROOT;
    if (!$handle) return null;
    $handle = trim($handle);
    $handle = ltrim($handle, '@');
    $enc = rawurlencode($handle);
    $icon_base = $MAIN_ROOT . "assets/images/socialmedias/";

    if ($provider === 'twitch') {
        $url = "https://www.twitch.tv/" . $enc;
        $img = $icon_base . "twitch.png";
    } elseif ($provider === 'facebook') {
        $url = "https://www.facebook.com/" . $enc;
        $img = $icon_base . "facebook.png";
    } elseif ($provider === 'youtube') {
        if (strpos($handle, 'UC') === 0) {
            $url = "https://www.youtube.com/channel/" . $enc;
        } else {
            $url = "https://www.youtube.com/@" . $enc;
        }
        $img = $icon_base . "youtube.png";
    } else {
        return null;
    }

    $safe_url = s($url);
    $safe_img = s($img);
    $safe_alt = s(ucfirst($provider));

    return "<a href='{$safe_url}' target='_blank' rel='noopener noreferrer nofollow' aria-label='{$safe_alt}'>
                <img src='{$safe_img}' alt='{$safe_alt}' style='margin-right:5px;width:32px;height:32px;'>
            </a>";
}

$fb = $row['facebook']; $tw = $row['twitch']; $yt = $row['youtube'];
$links = [];
if (!empty($fb))  $links[] = social_url('facebook', $fb);
if (!empty($tw))  $links[] = social_url('twitch',   $tw);
if (!empty($yt))  $links[] = social_url('youtube',  $yt);


$achievements_count = 0;
$events_count = 0;

if ($stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM {$dbprefix}user_achievements WHERE member_id = ?")) {
    $stmt->bind_param('i', $memberId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $r = $res->fetch_assoc();
        $achievements_count = (int)($r['c'] ?? 0);
        $res->free();
    } else {
        error_log('member.php achievements_count execute failed: ' . $stmt->error);
    }
    $stmt->close();
} else {
    error_log('member.php achievements_count prepare failed: ' . $mysqli->error);
}

$events_count = 0;
$events_sql = "
SELECT COUNT(*) AS c
FROM {$dbprefix}tournamentplayers tp
JOIN {$dbprefix}tournamentteams tt ON tp.team_id = tt.tournamentteam_id
JOIN {$dbprefix}tournaments t ON tt.tournament_id = t.tournament_id
WHERE tp.member_id = ?
";
if ($stmt = $mysqli->prepare($events_sql)) {
    $stmt->bind_param('i', $memberId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $r = $res->fetch_assoc();
        $events_count = (int)($r['c'] ?? 0);
        $res->free();
    } else {
        error_log('member.php events_count execute failed: ' . $stmt->error);
    }
    $stmt->close();
} else {
    error_log('member.php events_count prepare failed: ' . $mysqli->error);
}



$member_stats = [
  'tournaments_participated' => 0,
  'tournaments_won' => 0,
  'match_wins' => 0,
  'match_losses' => 0
];

$stats_table = 'member_stats';

if ($stmt = $mysqli->prepare("SELECT tournaments_participated, tournaments_won, match_wins, match_losses FROM {$stats_table} WHERE member_id = ? LIMIT 1")) {
    $stmt->bind_param('i', $memberId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($rowStats = $res->fetch_assoc()) {
            $member_stats['tournaments_participated'] = (int)$rowStats['tournaments_participated'];
            $member_stats['tournaments_won'] = (int)$rowStats['tournaments_won'];
            $member_stats['match_wins'] = (int)$rowStats['match_wins'];
            $member_stats['match_losses'] = (int)$rowStats['match_losses'];
        }
        $res->free();
    } else {
        error_log('member.php stats execute failed: ' . $stmt->error);
    }
    $stmt->close();
} else {
    error_log('member.php stats prepare failed: ' . $mysqli->error);
}


?>

<style>
.profile-page { display:flex; gap:20px; padding:20px; color:#e8eef6; font-family:Inter,Arial,sans-serif; }
.tabs { display:flex; gap:8px; margin-bottom:30px; border-bottom:1px solid #2a2f2c; }
.tabs button { padding:8px 12px; border:none; background:none; color:#9aa4b2; font-size:16px; font-weight:bold; cursor:pointer; }
.tabs button.active { color:#ffeb3b;border-bottom:2px solid #ffeb3b; }
.participated-events { display:flex; flex-direction:column; gap:12px; }
.timeline { border-left:3px solid rgba(255,255,255,0.00); }
.timeline-item { margin-bottom:24px; padding-left:12px; display:flex; gap:12px; align-items:center; }
.timeline-item img { width:56px; height:56px; border-radius:10px; object-fit:cover; }
.timeline-item .meta-time { color:#9aa4b2; font-size:13px; margin-bottom:6px; }
.timeline-item .desc { margin-top:4px; line-height:1.3; font-size:15px; color:#e6eef6; }
.btn.ghost { padding:8px 12px; border-radius:8px; background:transparent; border:1px solid rgba(255,255,255,0.4); color:#9aa4b2; cursor:pointer; }
.tab-badge { display: inline-block; min-width: 22px; padding: 2px 1px; border-radius: 999px; background: #2a2f2c; font-weight:800; font-size: 13px; line-height: 1; text-align: center; margin-left: 5px; }
.tab-badge.zero { opacity: 0.35; }
nav.tabs { align-items:center; }
nav.tabs button { display:inline-flex; align-items:center; gap:1px; }

.profile-card {
  background: none;
  border-radius: 14px;
  padding: 0px;
  width: 100%;
  margin: 0px 0px 30px 0px;
  color: #e8eef6;
  padding: 0px 0px;
}
.profile-avatar {
  position: relative;
  width: 140px;
  height: 140px;
  margin: 0 auto;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 6px 18px rgba(0,0,0,0.35);
  background: #0f1720;
}
.profile-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
.profile-name-row {
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  margin-top:30px;
  text-align:center;
}
.profile-username {
  font-size: clamp(18px, 3.6vw, 22px);
  font-weight: 700;
  color: #ffeb3b;
  word-break: break-word;
}
.profile-sub {
  color: #9aa4b2;
  font-size: 13px;
  margin-top:15px;
  text-align:center;
}
.presence-box {
  display:flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  margin-top:0px;
  color:#9aa4b2;
  font-size:13px;
}
.presence-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
.presence-dot.online { background: #2ecc71; box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7); border-radius: 50%; animation: pulse-green 2s infinite;}
.presence-dot.offline { background:#6b7280; }

@keyframes pulse-green {
	0% {
		transform: scale(0.95);
		box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7);
	}
	70% {
		transform: scale(1);
		box-shadow: 0 0 0 10px rgba(22, 163, 74, 0);
	}
	100% {
		transform: scale(0.95);
		box-shadow: 0 0 0 0 rgba(22, 163, 74, 0);
	}
}

.level-card {
  margin:30px 18px;
  border: 1px solid #2a2f2c;
  border-radius: 1px;
  padding: 12px;
  text-align:left;
}
.level-row { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.xp-stats { font-size:13px; color:#9aa4b2; display:flex; gap:8px; align-items:center; }
.progress-track { height:10px; background:hsl(228deg 5% 34% / 63%); border-radius:999px; overflow:hidden; margin-top:8px; }
.progress-fill { height:100%; border-radius:999px; background:linear-gradient(90deg,#00d6ff,#00a3ff); }

.summary-grid {
  display:flex;
  gap:12px;
  justify-content:space-around;
  align-items:center;
}
.summary-item { text-align:center; flex:1; }
.summary-item .num { font-weight:700; color:#e6eef6; display:block; font-size:16px; }
.summary-item .label { font-size:12px; color:#9aa4b2; }

.social-row { display:flex; gap:8px; justify-content:center; margin-top:30px; flex-wrap:wrap; }
.social-row a img { width:34px; height:34px; border-radius:6px; box-shadow:0 6px 12px rgba(0,0,0,0.4); }
.action-links { display:flex; gap:8px; justify-content:center; margin-top:30px; flex-wrap:wrap; }
.btn-link { background:transparent; border:1px solid rgba(255,255,255,0.06); padding:8px 10px; border-radius:8px; color:#9aa4b2; text-decoration:none; font-size:13px; }
</style>

<div class='userProfileSecs' data-title="<?= s(trim($PAGE_NAME) . ' ' . $DOMAIN_NAME) ?>">

    <div class="profileLeftSide" aria-labelledby="profile-name">

	  <div class="profile-card" role="region" aria-label="<?= s($username) ?> profile summary">
		<div class="profile-avatar" aria-hidden="false">
		  <img src="<?= s($profilePic) ?>" alt="<?= s($username) ?> avatar" loading="lazy">
		  
		</div>

		<div class="profile-name-row" id="profile-name">
		  <div>
			<div class="profile-username"><?php echo renderStyledUsername($username, $username_style); ?></div>
			
			<?php if (!empty($bio)): ?>
			<div class="bio-section" style="text-align:center; margin:0px 0px; padding:7px; border-radius:25px;">
				<p class="bio-content" style="margin:5px 0px; font-size:14px; line-height:1.5; color:#9aa4b2; max-width:600px;">
					<?php echo nl2br(htmlspecialchars($bio)); ?>
				</p>
			</div>
			<?php endif; ?>
			
			<!-- 
			<div id="member-status" data-user-id="<?php // (int)$memberId ?>" class="presence-box" aria-live="polite" aria-atomic="true" style="margin-bottom:6px;">
			  <span id="presence-dot" class="presence-dot <?php // $isActive ? 'online' : 'offline' ?>" aria-hidden="true"></span>
			  <span id="presence-text" class="presence-text"><?php // $dispLastSeen ?></span>
			</div>
			-->
			
		  </div>
		</div>

		<div class="presence-box" aria-live="polite" aria-atomic="true" style="margin-bottom:6px;">
		  <?php if ($country_name): ?>
			<div class="profile-sub" style="display: flex; text-align: center; align-items: center; justify-content: center; gap: 10px;">
				<?php echo $country_image; ?> 
				<?php echo $country_name; ?>
			</div>
		  <?php endif; ?>
		</div>

		<div class="level-card" aria-hidden="false">

		  <div class="summary-grid" style="">
			<div class="summary-item">
			  <span class="label">Tournaments <br> participated</span>
              <div style="display: flex; align-items: center; justify-content: center; margin-top: 5px;">
                <span style="color:#9aa4b2; margin-right:6px; font-size:18px;">🏆</span>
			    <span class="num"><?= s(number_format($member_stats['tournaments_participated'])) ?></span>
              </div>
			</div>

			<div class="summary-item">
			  <span class="label">Tournaments <br> won</span>
              <div style="display: flex; align-items: center; justify-content: center; margin-top: 5px;">
                <span style="color:#fbbf24; margin-right:6px; font-size:18px;">🏆</span>
			    <span class="num" style="color:gold;"><?= s(number_format($member_stats['tournaments_won'])) ?></span>
              </div>
			</div>
		  </div>

		  <div class="summary-grid" style="margin-top:16px;">
			<div class="summary-item">
			  <span class="label">Wins</span>
              <div style="display: flex; align-items: center; justify-content: center; margin-top: 5px;">
                <span class="num" style="color:#0bf115; margin-right:6px; font-size:18px;">⚔️</span>
			    <span class="num" style="color:#0bf115;"><?= s(number_format($member_stats['match_wins'])) ?></span>
              </div>
			</div>

			<div class="summary-item">
			  <span class="label">Losses</span>
			  <div style="display: flex; align-items: center; justify-content: center; margin-top: 5px;">  
                <span class="num" style="color:#ff6b6b; margin-right:6px; font-size:18px;">⚔️</span>
                <span class="num" style="color:#ff6b6b;"><?= s(number_format($member_stats['match_losses'])) ?></span>
              </div>
			</div>
		  </div>
		  
		  <?php
		  $total_matches = $member_stats['match_wins'] + $member_stats['match_losses'];
		  $win_ratio = $total_matches > 0 ? round(($member_stats['match_wins'] / $total_matches) * 100) : 0;
		  $loss_ratio = $total_matches > 0 ? (100 - $win_ratio) : 0;
		  ?>
		  <div style="margin-top:18px; padding:0 4px;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
			  <span style="font-size:12px; color:#0bf115; font-weight:600;"><?= $win_ratio ?>% Wins</span>
			  <span style="font-size:11px; color:#9aa4b2;"><?= $total_matches ?> matches</span>
			  <span style="font-size:12px; color:#ff6b6b; font-weight:600;"><?= $loss_ratio ?>% Losses</span>
			</div>
			<div style="height:8px; border-radius:999px; overflow:hidden; background:#2a2f3a; display:flex;">
			  <?php if ($total_matches > 0): ?>
				<div style="width:<?= $win_ratio ?>%; background:linear-gradient(90deg,#0bf115,#00c853); border-radius:999px 0 0 999px; transition:width 0.4s ease;"></div>
				<div style="width:<?= $loss_ratio ?>%; background:linear-gradient(90deg,#ff4444,#ff6b6b); border-radius:0 999px 999px 0; transition:width 0.4s ease;"></div>
			  <?php else: ?>
				<div style="width:100%; background:#2a2f3a; border-radius:999px;"></div>
			  <?php endif; ?>
			</div>
			<?php if ($total_matches === 0): ?>
			  <div style="text-align:center; font-size:11px; color:#9aa4b2; margin-top:4px;">No matches yet</div>
			<?php endif; ?>
		  </div>
		  
		</div>

		<?php if (count($links) > 0): ?>
		  <div class="social-row" aria-label="social links">
			<?php foreach ($links as $html) echo $html; ?>
		  </div>
		<?php endif; ?>

	  </div>
	</div>


    <div class='profileRightSide'>
		
		<nav class="tabs" role="tablist" aria-label="Profile tabs">
		  <button data-tab="achievements" class="active" role="tab" aria-selected="true">
			Achievements
			<span class="tab-badge <?= $achievements_count === 0 ? 'zero' : '' ?>" ...><?= s(number_format($achievements_count)) ?></span>
		  </button>

		  <button data-tab="events" role="tab" aria-selected="false">
			Tournaments
			<span class="tab-badge <?= $events_count === 0 ? 'zero' : '' ?>" aria-hidden="false"><?= s(number_format($events_count)) ?></span>
		  </button>
		</nav>


        <section id="tab-events" class="tab-content" style="display:none;">
            <div id="participated-events-container" class="participated-events"></div>
            <div style="text-align:center; margin-top:12px;">
                <button id="load-more-events" class="btn ghost">Load more tournaments</button>
            </div>
        </section>

        <section id="tab-achievements" class="tab-content" style="display:block;">
            <div id="achievements-timeline" class="timeline">Loading achievements...</div>
            <div style="text-align:center; margin-top:12px;">
                <button id="load-more-achievements" class="btn ghost">Load more</button>
            </div>
        </section>
    </div>

</div>

<script>
(function () {
    const PROFILE_MEMBER_ID = <?= (int)$memberId ?>;
    const SITE_ROOT = <?= json_encode($MAIN_ROOT) ?>.replace(/\/$/, '') + '/';

    const EVENTS_HTML_URL  = SITE_ROOT + 'backend/get_participated_events.php';
    const ACHIEVEMENTS_HTML_URL= SITE_ROOT + 'backend/get_user_achievements.php';

    const VIEWER_TZ = (Intl && Intl.DateTimeFormat) ? (Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC') : 'UTC';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            setTimeout(fn, 0);
        }
    }

    onReady(function () {
        const eventsContainer = document.getElementById('participated-events-container');
        const loadMoreEventsBtn = document.getElementById('load-more-events');
        const achievementsContainer = document.getElementById('achievements-timeline');
        const loadMoreAchievementsBtn = document.getElementById('load-more-achievements');
        const tabsButtons = document.querySelectorAll('.tabs button');

		function formatParticipatedEventTimes(container) {
			try {
				const root = container || document;
				const anchors = root.querySelectorAll('a[data-start-utc]');
				if (!anchors || !anchors.length) return;

				const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

				anchors.forEach(function(a) {
					const iso = a.getAttribute('data-start-utc') || '';
					const meta = a.querySelector('.meta-time');
					if (!meta) return;
					if (!iso) {
						meta.textContent = '—';
						return;
					}

					const d = new Date(iso);
					if (isNaN(d.getTime())) {
						meta.textContent = iso;
						return;
					}

					const month = months[d.getMonth()] || '';
					const day = d.getDate();
					const year = d.getFullYear();
					const hh = String(d.getHours()).padStart(2, '0');
					const mm = String(d.getMinutes()).padStart(2, '0');

					const offsetMin = -d.getTimezoneOffset();
					const sign = offsetMin >= 0 ? '+' : '-';
					const absMin = Math.abs(offsetMin);
					const oh = Math.floor(absMin / 60);
					const om = absMin % 60;

					let offsetStr = 'UTC' + sign + String(oh);
					if (om !== 0) offsetStr += ':' + String(om).padStart(2, '0');

					meta.textContent = `${month} ${day}, ${year} ${hh}:${mm} ${offsetStr}`;
				});
			} catch (e) {
				console.warn('formatParticipatedEventTimes error', e);
			}
		}

		function formatAchievementsTimes(container) {
			try {
				const root = container || document;
				const items = root.querySelectorAll('.timeline-item[data-created-utc]');
				if (!items || !items.length) return;
				const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

				items.forEach(function(item) {
					const iso = item.getAttribute('data-created-utc') || '';
					const meta = item.querySelector('.meta-time');
					if (!meta) return;
					if (!iso) {
						meta.textContent = '—';
						return;
					}
					const d = new Date(iso);
					if (isNaN(d.getTime())) {
						meta.textContent = iso;
						return;
					}

					const month = months[d.getMonth()] || '';
					const day = d.getDate();
					const year = d.getFullYear();
					const hh = String(d.getHours()).padStart(2, '0');
					const mm = String(d.getMinutes()).padStart(2, '0');

					const offsetMin = -d.getTimezoneOffset();
					const sign = offsetMin >= 0 ? '+' : '-';
					const absMin = Math.abs(offsetMin);
					const oh = Math.floor(absMin / 60);
					const om = absMin % 60;

					let offsetStr = 'UTC' + sign + String(oh);
					if (om !== 0) offsetStr += ':' + String(om).padStart(2, '0');

					meta.textContent = `${month} ${day}, ${year} ${hh}:${mm} ${offsetStr}`;
				});
			} catch (e) {
				console.warn('formatAchievementsTimes error', e);
			}
		}

        let eventsLoadedOnce = false;
        const EVENTS_BATCH = 6;
        let eventsCursor = null;

        async function loadParticipatedEvents(initial = false) {
            if (!eventsContainer || !loadMoreEventsBtn) return;
            try {
                if (initial) {
                    eventsCursor = null;
                    eventsContainer.innerHTML = '';
                }
                loadMoreEventsBtn.disabled = true;
                loadMoreEventsBtn.textContent = 'Loading...';

                let url = `${EVENTS_HTML_URL}?member_id=${encodeURIComponent(PROFILE_MEMBER_ID)}&limit=${EVENTS_BATCH}&viewer_tz=${encodeURIComponent(VIEWER_TZ)}`;
                if (eventsCursor && typeof eventsCursor === 'object' && eventsCursor.before_start !== undefined && eventsCursor.before_id !== undefined) {
                    url += `&cursor=${encodeURIComponent(btoa(JSON.stringify(eventsCursor)))}`;
                }

                const res = await fetch(url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                const html = data.html || '';
                const nextCursorB64 = data.next_cursor || null;

                if (!html || html.trim() === '') {
                    if (!eventsCursor) eventsContainer.innerHTML = '<div style="color:#9aa4b2">No tournaments found.</div>';
                    loadMoreEventsBtn.style.display = 'none';
                    loadMoreEventsBtn.disabled = false;
                    loadMoreEventsBtn.textContent = 'Load more tournaments';
                    eventsLoadedOnce = true;
                    return;
                }

                const temp = document.createElement('div');
                temp.innerHTML = html;
                while (temp.firstChild) eventsContainer.appendChild(temp.firstChild);
				
				formatParticipatedEventTimes(eventsContainer);

                if (nextCursorB64) {
                    try {
                        const parsed = JSON.parse(atob(nextCursorB64));
                        if (parsed && parsed.before_start !== undefined && parsed.before_id !== undefined) {
                            eventsCursor = {
                                before_start: parsed.before_start,
                                before_id: parsed.before_id,
                                before_type: parsed.before_type || 2
                            };
                            loadMoreEventsBtn.style.display = 'inline-block';
                            loadMoreEventsBtn.disabled = false;
                            loadMoreEventsBtn.textContent = 'Load more tournaments';
                        } else {
                            eventsCursor = null;
                            loadMoreEventsBtn.style.display = 'none';
                        }
                    } catch (err) {
                        console.warn('Failed to parse events next_cursor', err);
                        eventsCursor = null;
                        loadMoreEventsBtn.style.display = 'none';
                    }
                } else {
                    eventsCursor = null;
                    loadMoreEventsBtn.style.display = 'none';
                }

                eventsLoadedOnce = true;

            } catch (err) {
                console.error('loadParticipatedEvents failed', err);
                loadMoreEventsBtn.disabled = false;
                loadMoreEventsBtn.textContent = 'Load more tournaments';
            }
        }

        if (loadMoreEventsBtn) {
            loadMoreEventsBtn.addEventListener('click', () => loadParticipatedEvents(false));
        }
        
		let achievementsCursor = null;
        const ACHIEVEMENTS_BATCH = 10;

        async function loadAchievements(initial = false) {
            if (!achievementsContainer || !loadMoreAchievementsBtn) return;
            try {
                if (initial) {
                    achievementsCursor = null;
                    achievementsContainer.innerHTML = '';
                }
                loadMoreAchievementsBtn.disabled = true;
                loadMoreAchievementsBtn.textContent = 'Loading...';

                let url = `${ACHIEVEMENTS_HTML_URL}?member_id=${encodeURIComponent(PROFILE_MEMBER_ID)}&limit=${ACHIEVEMENTS_BATCH}`;
                if (achievementsCursor && achievementsCursor.before_created_at && achievementsCursor.before_id) {
                    url += `&before_created_at=${encodeURIComponent(achievementsCursor.before_created_at)}&before_id=${encodeURIComponent(achievementsCursor.before_id)}`;
                }

                const res = await fetch(url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();

                const html = data.html || '';
                const nextCursorB64 = data.next_cursor || null;

                if (!html || html.trim() === '') {
                    if (!achievementsCursor) achievementsContainer.innerHTML = '<div style="color:#9aa4b2">No achievements yet.</div>';
                    loadMoreAchievementsBtn.style.display = 'none';
                    loadMoreAchievementsBtn.disabled = false;
                    loadMoreAchievementsBtn.textContent = 'Load more';
                    return;
                }

                const temp = document.createElement('div');
                temp.innerHTML = html;
                while (temp.firstChild) achievementsContainer.appendChild(temp.firstChild);

				formatAchievementsTimes(achievementsContainer);

                if (nextCursorB64) {
                    try {
                        const parsed = JSON.parse(atob(nextCursorB64));
                        if (parsed && parsed.before_created_at && parsed.before_id) {
                            achievementsCursor = {
                                before_created_at: parsed.before_created_at,
                                before_id: parsed.before_id
                            };
                            loadMoreAchievementsBtn.style.display = 'inline-block';
                            loadMoreAchievementsBtn.disabled = false;
                            loadMoreAchievementsBtn.textContent = 'Load more';
                        } else {
                            achievementsCursor = null;
                            loadMoreAchievementsBtn.style.display = 'none';
                        }
                    } catch (err) {
                        console.warn('Failed to parse next_cursor', err);
                        achievementsCursor = null;
                        loadMoreAchievementsBtn.style.display = 'none';
                    }
                } else {
                    achievementsCursor = null;
                    loadMoreAchievementsBtn.style.display = 'none';
                }

            } catch (err) {
                console.error('loadAchievements failed', err);
                if (loadMoreAchievementsBtn) {
                    loadMoreAchievementsBtn.disabled = false;
                    loadMoreAchievementsBtn.textContent = 'Load more';
                }
            }
        }

        if (loadMoreAchievementsBtn) {
            loadMoreAchievementsBtn.addEventListener('click', () => loadAchievements(false));
        }

        if (tabsButtons && tabsButtons.length) {
            tabsButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    tabsButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    const tab = btn.dataset.tab;
                    const tabAchievements = document.getElementById('tab-achievements');
                    const tabEvents   = document.getElementById('tab-events');
                    if (tabAchievements) tabAchievements.style.display = (tab === 'achievements') ? 'block' : 'none';
                    if (tabEvents)   tabEvents.style.display = (tab === 'events') ? 'block' : 'none';
                    if (tab === 'events' && !eventsLoadedOnce) loadParticipatedEvents(true);
                });
            });
        }

        loadAchievements(true);

        document.querySelectorAll('.lastseen').forEach(function (el) {
            const ts = Number(el.dataset.ts) || 0;
            if (!ts) { el.textContent = 'Never Logged In'; return; }
            el.title = new Date(ts * 1000).toLocaleString();
            const text = (el.textContent || '').trim();
            const looksLikeAbsolute = /\d{4}|:/.test(text);

            function relText() {
                const diff = Math.floor(Date.now() / 1000) - ts;
                if (diff < 10) return 'just now';
                if (diff < 60) return diff + ' second' + (diff > 1 ? 's' : '') + ' ago';
                if (diff < 3600) return Math.floor(diff / 60) + ' minute' + (Math.floor(diff / 60) > 1 ? 's' : '') + ' ago';
                if (diff < 86400) return Math.floor(diff / 3600) + ' hour' + (Math.floor(diff / 3600) > 1 ? 's' : '') + ' ago';
                if (diff < 2592000) return Math.floor(diff / 86400) + ' day' + (Math.floor(diff / 86400) > 1 ? 's' : '') + ' ago';
                return new Date(ts * 1000).toLocaleString();
            }

            if (looksLikeAbsolute) {
                el.textContent = new Date(ts * 1000).toLocaleString();
            } else {
                el.textContent = relText();
                setInterval(function () {
                    el.textContent = relText();
                }, 60000);
            }
        });
    });
})();
</script>

</main>


<?php
include($prevFolder . "assets/_footer.php");
?>