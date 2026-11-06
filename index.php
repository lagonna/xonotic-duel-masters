<?php
define("COMPETITIONS_LINK", true);
include_once("_intro.php");
$prevFolder = "";
include_once($prevFolder . "classes/game.php");
$gameObj = new Game($mysqli);
$PAGE_NAME = "Competitions - ";
include($prevFolder . "assets/_header.php");

$LOGIN_FAIL = true;
if (defined("LOGGED_IN")) $LOGIN_FAIL = false;

$filterGameId = isset($_GET['game_id']) && is_numeric($_GET['game_id']) ? intval($_GET['game_id']) : 0;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$gameMap = $gameObj->getAllGames();

$tournaments = [];
$tTable = $dbprefix . 'tournaments';
$sqlT = "
    SELECT t.tournament_id, t.member_id, t.gamesplayed_id, t.name AS tour_name, t.status, t.startdate,
            t.t_prize, t.t_prize_currency, t.playersperteam, t.participants_count,
           t.maxteams AS maxteams, t.awards_assigned, t.registration_status,
           g.imageurl, g.name AS game_name
    FROM {$tTable} AS t
    JOIN {$dbprefix}gamesplayed AS g ON t.gamesplayed_id = g.gamesplayed_id
";

$where = [];
$params = [];
$types = '';

if ($filterGameId > 0) {
    $where[] = 't.gamesplayed_id = ?';
    $types .= 'i';
    $params[] = $filterGameId;
}
if ($q !== '') {
    $where[] = 't.name LIKE ?';
    $types .= 's';
    $params[] = '%' . $q . '%';
}
if (!empty($where)) {
    $sqlT .= ' WHERE ' . implode(' AND ', $where);
}

$sqlT .= " ORDER BY t.startdate DESC";
if ($stmt = $mysqli->prepare($sqlT)) {
    if (!empty($params)) {
        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $tournaments[] = $row;
    $stmt->close();
} else {
    echo "<div class='empty'>Database error (tournaments).</div>";
}

$normalized = [];
foreach ($tournaments as $t) {
    $tid = intval($t['tournament_id']);
    $created_by = intval($t['member_id']);
    $start_ts = intval($t['startdate']);
    $prizeVal = (isset($t['t_prize']) && $t['t_prize'] !== null && $t['t_prize'] !== '') ? '$' . number_format((float)$t['t_prize'], 2, '.', ',') : '';
    $t_prize_c = $t['t_prize_currency'];
    $img = $t['imageurl'] ?? '';
    $awardsAssigned    = intval($t['awards_assigned'] ?? 0) === 1;
	$totalSlots        = intval($t['playersperteam'] ?? 1) * intval($t['maxteams'] ?? 0);
	$currentParts      = intval($t['participants_count'] ?? 0);
	$regStatusDb       = intval($t['registration_status'] ?? 0);
	$regOpen           = ($regStatusDb === 1) && ($totalSlots === 0 || $currentParts < $totalSlots);

    $now = time();
    if ($awardsAssigned) {
		$serverStatus = "finished";
		$customCountColor = "#6b7280";
	} elseif ($start_ts > 0 && $now < $start_ts) {
		$serverStatus = "upcoming";
		$customCountColor = "#ffd78a";
	} else {
		$serverStatus = "ongoing";
		$customCountColor = "#ff4d4d";
	}

    $startIso = ($start_ts > 0) ? (new DateTime('@'.$start_ts))->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM) : '';

    $normalized[] = [
        'id' => $tid,
        'title' => $t['tour_name'] ?? ("Tournament #{$tid}"),
        'game_name' => $t['game_name'] ?? ($gameMap[$t['gamesplayed_id']]['name'] ?? 'Unknown'),
        'creator' => $created_by,
        'imageurl' => $img,
        'prize' => $prizeVal,
        'prize_currency' => $t_prize_c,
        'start_ts' => $start_ts,
        'start_iso' => $startIso,
        'server_status' => $serverStatus,
        'count_color' => $customCountColor,
        'awards_assigned' => $awardsAssigned,
        'url' => MAIN_ROOT . "competitions/competition.php?tID={$tid}",
        'participants' => intval($t['participants_count'] ?? 0),
		'maxteams' => intval($t['maxteams'] ?? 0),
		'playersperteam' => intval($t['playersperteam'] ?? 1),
		'total_slots' => intval($t['maxteams'] ?? 0) * intval($t['playersperteam'] ?? 1),
		'reg_open' => $regOpen,
    ];
}
usort($normalized, function($a, $b){
    $ta = intval($a['start_ts']);
    $tb = intval($b['start_ts']);
    if ($ta === $tb) return intval($b['id']) <=> intval($a['id']);
    return $tb <=> $ta;
});
?>
<main id="app-root" class="container" role="main" tabindex="-1">
<style>

.events { padding:0px; }
.top-row { display:flex; gap:12px; align-items:center; margin-bottom:30px; flex-wrap:nowrap; width:100%; }

.top-left { display:flex; gap:15px; align-items:center; flex-wrap:nowrap%; }

.search-form { flex: 0 0 50%; min-width:100%; display:block; }
.search-input-wrap { position:relative; width:100%; }
.search-input-wrap input[type="search"] {
    width:100%;
    padding:8px 12px;
    border-radius:8px; background:transparent; border:1px solid #2a2f2c; color:#e6eef8;
    font-size:15px; line-height:1.5;
    box-sizing:border-box;
}
.search-input-wrap input[type="search"]:focus {
    border-color:#ffeb3b;
    outline:none;
}
.search-input-wrap input[type="search"]::-webkit-search-cancel-button,
.search-input-wrap input[type="search"]::-webkit-search-decoration {
    -webkit-appearance: none; appearance: none; display: none;
}
.search-input-wrap input[type="search"]::-ms-clear,
.search-input-wrap input[type="search"]::-ms-reveal {
    display:none; width:0; height:0;
}

.custom-select { position:relative; min-width:220px; max-width:230px;  flex: 0 0 auto; }
.custom-select .select-btn {
    display:flex; align-items:center; justify-content:space-between;
    padding:8px 12px; border-radius:8px; background:transparent; border: 1px solid #2a2f2c; color:#e6eef8; cursor:pointer;
}
.custom-select .options {
    position:absolute; top:calc(100% + 8px); left:0; right:0; background:#0d1116; border-radius:8px;
    box-shadow:0 8px 24px rgba(0,0,0,0.6); z-index:50; display:none; max-height:220px; overflow:auto;
}
.custom-select.open .options { display:block; }
.custom-select .options .opt { padding:10px 12px; cursor:pointer; border-bottom:1px solid rgba(255,255,255,0.02); color:#cfe6ff; }
.custom-select .options .opt:last-child { border-bottom:0; }
.custom-select .options .opt:hover { background:rgba(255,255,255,0.02); }
.custom-select .arrow { margin-left:12px; transform:rotate(0deg); transition:transform .15s ease; }
.custom-select.open .arrow { transform:rotate(180deg); }

.clear-link { color:#9fb0c8; text-decoration:underline; font-size:0.95rem; margin-left:6px; display:inline-block; white-space:nowrap; }

.top-right { display:flex; gap:10px; align-items:center; margin-left:auto; }

.events-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(320px,1fr)); gap:30px; margin-top:12px; }
.event-card {
  position:relative;
  border: 1px solid #2a2f2c;
  border-radius: 3px;
  overflow: hidden;
  transition: transform 0.14s ease, box-shadow 0.14s ease;
  text-decoration:none; color:inherit; display:block;
}
.event-card:hover { transform: translateY(-6px); box-shadow: 0 18px 40px rgba(0,0,0,0.6); }
.cover { width:100%; height:160px; object-fit:cover; }
.meta-compact .prize-inline {
  padding:6px 8px;
  font-size:0.88rem;
  line-height:1;
  display:flex;
  flex-direction: column;
  align-items:center;
  justify-content:center;
  min-width:62px;
  text-align:center;
  color: #ffeb3b;
}
.card-body { padding:14px; }
.card-title { display:flex; justify-content:space-between; gap:10px; margin-bottom: 15px; }
.card-title .left { display:flex; flex-direction:column; gap:4px; }
.card-title .left .title-main { color:#dbefff; font-weight:800; font-size:1.05rem; margin:0; }
.card-title .left .subtitle { color:#ffeb3b; font-size:0.92rem; margin-top:2px; }

.count-area { margin-top:15px; display:flex; align-items:center; flex-direction:column; gap:8px; }
.status-text { 
    font-size: 0.9rem; 
    text-align: center; 
    width: 100%;
    margin-bottom: 0px;
    font-weight: 600;
}
.countbox {
  background: rgba(255,255,255,0.03);
  border-radius:10px;
  padding:12px 10px;
  min-width:130px;
  text-align:center;
  display:flex;
  flex-direction:row;
  align-items:center;
  justify-content:center;
  gap:10px;
  width:90%;
  box-shadow: inset 0 -6px 12px rgba(0,0,0,0.25);
}
.count-num { font-weight:900; font-size:1.15rem; letter-spacing:0.5px; }
.countbox.upcoming { border: 1px solid rgba(255,215,138,0.25); }
.countbox.ongoing {
  border: 1px solid rgba(255,110,60,0.25);
  background: linear-gradient(90deg,#ff4d4d26,#ff704326);
}
.ongoing-dot {
  display:inline-block;
  width:10px;
  height:10px;
  border-radius:50%;
  background:#ff4d4d;
  animation:pulseFade 1.5s ease-in-out infinite;
  margin-right:8px;
}
@keyframes pulseFade { 0%{opacity:0.2}50%{opacity:1}100%{opacity:0.2} }
.countbox.finished { opacity:0.7; }

@media (max-width:992px) {
  .events-grid { grid-template-columns: 1fr; }
  .cover { height:140px; }
  .top-row { flex-direction:column; align-items:stretch; gap:12px; }
  .top-left { width:100%; flex-direction:column; align-items:stretch; gap:15px; }
  .search-form { flex: none; width:100%; min-width:0; max-width:none; }
  .custom-select { width:100%; min-width:0; }
  .top-right { width:100%; justify-content:flex-start; margin-left:0; }
  .clear-link { margin-left:0; }
}
</style>

<div class="events">
  <div class="top-row">
    <div class="top-left">
      <form method="get" id="search-form" class="search-form" style="margin:0;">
        <div class="search-input-wrap">
          <input type="search" name="q" placeholder="Search competitions..." value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>" aria-label="Search competitions">
        </div>
        <input type="hidden" name="game_id" value="<?php echo intval($filterGameId); ?>">
      </form>

      <form method="get" id="game-filter-form" style="margin:0; display:flex; align-items:center; gap:6px;">
        <div class="custom-select" id="game-select" role="listbox" aria-label="Game selector">
          <div class="select-btn" tabindex="0" aria-haspopup="listbox" aria-expanded="false">
            <span class="label"><?php echo ($filterGameId === 0) ? 'All games' : htmlspecialchars($gameMap[$filterGameId]['name'] ?? 'All games'); ?></span>
            <svg class="arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" style="opacity:.9">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="options" role="list">
            <div class="opt" data-value="0" tabindex="0">All games</div>
            <?php foreach ($gameMap as $gid => $info): ?>
            <div class="opt" data-value="<?php echo intval($gid); ?>" tabindex="0"><?php echo htmlspecialchars($info['name'] ?? ('Game #' . $gid)); ?></div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="game_id" value="<?php echo intval($filterGameId); ?>">
          <input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>">
        </div>

        <?php if ($filterGameId > 0 || $q !== ''): ?>
          <a class="clear-link" href="<?php echo htmlspecialchars(MAIN_ROOT . 'competitions/'); ?>">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="top-right">
      <?php // if ($LOGIN_FAIL === false && isset($_SESSION['user_id'])): ?>
      <a class="btn" href="<?php echo htmlspecialchars(MAIN_ROOT . 'competitions/create.php'); ?>">Create Tournament</a>
      <?php // endif; ?>
    </div>
  </div>

<?php
if (empty($normalized)) {
  echo "<div class='empty'>No events found.</div>";
} else {
  echo "<div class='events-grid' id='events-grid'>";
  foreach ($normalized as $item) {
    $title = htmlspecialchars($item['title']);
    $game_name = htmlspecialchars($item['game_name'] ?? 'Unknown');
    $prize = ($item['prize'] !== '') ? htmlspecialchars($item['prize']) : '';
    $prize_currency = ($item['prize_currency'] !== '') ? htmlspecialchars($item['prize_currency']) : '';
    $img = htmlspecialchars($item['imageurl'] ?? '');
    $startIso = htmlspecialchars($item['start_iso'] ?? '');
    $serverStatus = htmlspecialchars($item['server_status'] ?? 'upcoming');
    $countColor = htmlspecialchars($item['count_color'] ?? '#e6f8ff');
    $url = htmlspecialchars($item['url'] ?? '#');
    $awardsAssigned = $item['awards_assigned'] ?? false;
	$regOpen        = $item['reg_open'] ?? false;
	

    echo "<a class='event-card' href='{$url}' data-start-utc='{$startIso}' data-server-status='{$serverStatus}' data-count-color='{$countColor}' data-awards-assigned='" . ($awardsAssigned ? '1' : '0') . "'>";
    if ($img) {
      $imgSrc = (strpos($img, 'http') === 0) ? $img : MAIN_ROOT . ltrim($img, '/');
      // echo "<img class='cover' src='" . htmlspecialchars($imgSrc) . "' alt='" . $title . "'>";
    } else {
      // echo "<div class='cover' style='height:160px;display:flex;align-items:center;justify-content:center;background:#061428;color:#9fb0c8;font-weight:700;'>No image</div>";
    }

    echo "<div class='card-body'>";
    echo "<div class='card-title'>";
    echo "<div class='left'><div class='subtitle'>{$game_name}</div><div class='title-main'>{$title}</div></div>";
    echo "</div>";
    $parts = intval($item['participants'] ?? 0);
	$max   = intval($item['maxteams'] ?? 0);
	echo "<div style='display:flex; align-items:center; justify-content:space-between;'>";
	
	$parts = intval($item['participants'] ?? 0);
	$max   = intval($item['total_slots'] ?? 0);
	echo "<div class='meta-compact'><div class='prize-inline'><span style='color:#9e9e9e; margin-bottom:7px;'>Players</span>{$parts}/{$max}</div></div>";
	
	echo "<div class='meta-compact'><div class='prize-inline'><span style='color:#9e9e9e; margin-bottom:7px;'>Prize</span><span style='color:#ffeb3b;'>" . ($prize !== '' ? "{$prize} {$prize_currency}" : '') . "</span></div></div>";
	echo "</div>";
	
	$regLabel = $regOpen ? 'Registration Open' : 'Registration Closed';
	$regColor = $regOpen ? '#10b981' : '#6b7280';
	$regBg    = $regOpen ? 'rgba(16,185,129,0.10)' : 'rgba(107,114,128,0.12)';
	$regDot   = $regOpen ? '#10b981' : '#6b7280';
	
	echo "<div style='margin-top:15px; display:flex; align-items:center; justify-content:center;'>";
	echo "<div style='display:inline-flex; align-items:center; gap:6px; padding:5px 14px; border-radius:20px; background:{$regBg}; border:1px solid {$regColor}33; font-size:0.82rem; font-weight:600; color:{$regColor};'>";
	echo "<span style='display:inline-block; width:7px; height:7px; border-radius:50%; background:{$regDot};'></span>";
	echo htmlspecialchars($regLabel);
	echo "</div></div>";
	
    echo "<div class='count-area'>";
    echo "<div class='status-text' data-status-text style='color:{$countColor};'></div>";
    echo "<div class='countbox' data-countbox role='status' aria-live='polite'>";
    echo "<div class='count-num' data-count-num style='color:{$countColor};'>—</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</a>";
  }
  echo "</div>";
}
?>
</div>

<script>
(function() {
    "use strict";

    (function initGameSelect(){
        var cs = document.getElementById('game-select');
        if (!cs) return;
        var form = cs.closest('form');
        var btn = cs.querySelector('.select-btn');
        var opts = cs.querySelector('.options');
        var hidden = cs.querySelector('input[name="game_id"]');
        var preservedQ = cs.querySelector('input[name="q"]');

        function closeAll() {
            document.querySelectorAll('.custom-select.open').forEach(function(c){
                c.classList.remove('open');
                var sbtn = c.querySelector('.select-btn');
                if (sbtn) sbtn.setAttribute('aria-expanded','false');
            });
        }

        btn.addEventListener('click', function(e){
            e.stopPropagation();
            var open = cs.classList.toggle('open');
            btn.setAttribute('aria-expanded', !!open);
        });

        btn.addEventListener('keydown', function(e){
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault(); cs.classList.toggle('open');
            } else if (e.key === 'Escape') { cs.classList.remove('open'); }
        });

        opts.querySelectorAll('.opt').forEach(function(o){
            o.addEventListener('click', function(ev){
                var val = this.getAttribute('data-value') || '';
                var text = this.textContent || '';
                if (hidden) hidden.value = val;
                var label = cs.querySelector('.label');
                if (label) label.textContent = text;
                cs.classList.remove('open');
                if (form) form.submit();
            });
            o.addEventListener('keydown', function(e){
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault(); this.click();
                }
            });
        });

        document.addEventListener('click', function(){
            closeAll();
        });
    })();

    function pad(n){ return n < 10 ? '0' + n : '' + n; }
    function breakdown(ms) {
        if (!ms || ms <= 0) return { d:0, h:0, m:0, s:0 };
        var total = Math.floor(ms / 1000);
        var d = Math.floor(total / 86400);
        var h = Math.floor((total % 86400) / 3600);
        var m = Math.floor((total % 3600) / 60);
        var s = total % 60;
        return { d:d, h:h, m:m, s:s };
    }
	
    function updateCard(card) {
		if (!card) return;

		var startIso = card.getAttribute('data-start-utc') || '';
		var countColor = card.getAttribute('data-count-color') || '#f59e0b';
		var awardsAssigned = card.getAttribute('data-awards-assigned') === '1';

		var startDate = startIso ? new Date(startIso) : null;
		var now = new Date();

		var countbox = card.querySelector('[data-countbox]');
		var countNum = card.querySelector('[data-count-num]');
		var statusText = card.querySelector('[data-status-text]');

		if (countbox) countbox.style.display = '';
		if (statusText) statusText.style.color = countColor;

		var startMs = startDate ? startDate.getTime() : 0;
		var diff = startMs - now.getTime();

		if (awardsAssigned) {
			if (startDate && countNum) {
				try {
					var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
					var fmt = new Intl.DateTimeFormat(undefined, {
						year: 'numeric',
						month: 'short',
						day: 'numeric',
						hour: '2-digit',
						minute: '2-digit',
						hour12: false,
						timeZone: tz,
						timeZoneName: 'short'
					});
					countNum.textContent = fmt.format(startDate).replace('GMT', 'UTC');
				} catch (e) {
					countNum.textContent = startDate.toString();
				}
				countNum.style.color = '#6b7280';
			} else if (countNum) {
				countNum.textContent = 'Finished';
				countNum.style.color = '#6b7280';
			}

			if (statusText) statusText.textContent = '✅ Completed';
			if (countbox) {
				countbox.classList.remove('upcoming', 'ongoing');
				countbox.classList.add('finished');
			}
			if (card) card.style.opacity = '0.7';
			return;
		}

		if (startDate && diff <= 0) {
			if (countNum) {
				countNum.innerHTML = '<span class="ongoing-dot"></span>LIVE';
				countNum.style.color = '#ff4d4d';
			}
			if (statusText) statusText.textContent = 'In Progress';
			if (countbox) {
				countbox.classList.remove('upcoming', 'finished');
				countbox.classList.add('ongoing');
			}
			return;
		}

		if (countbox) {
			countbox.classList.remove('ongoing', 'finished');
			countbox.classList.add('upcoming');
		}

		if (startDate) {
			var b = breakdown(diff);
			if (statusText) statusText.textContent = '📆 Upcoming';
			if (countNum) {
				countNum.textContent =
					(b.d > 0 ? b.d + 'd ' : '') +
					pad(b.h) + 'h ' +
					pad(b.m) + 'm ' +
					pad(b.s) + 's';
				countNum.style.color = '#ffd78a';
			}
		} else {
			if (countNum) {
				countNum.textContent = 'Upcoming';
				countNum.style.color = '#ffd78a';
			}
			if (statusText) statusText.textContent = 'Status';
		}
	}
    var cards = Array.prototype.slice.call(document.querySelectorAll('.event-card'));
    function tickAll() { cards.forEach(function(c){ updateCard(c); }); }
    tickAll();
    setInterval(tickAll, 1000);
})();
</script>

</main>

<?php
include($prevFolder . "assets/_footer.php");
?>