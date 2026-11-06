<?php
include_once("../_intro.php");

$prevFolder = "../";
$PAGE_NAME = "Edit - ";

include("../assets/_header.php");

include_once($prevFolder . "classes/game.php");

if (!isset($_SESSION['user_id'])) {
    exit();
}

// if ($_SESSION['user_id'] != 1) {
    // exit();
// }

$error = false;

if(isset($_GET['tID']) AND is_numeric($_GET['tID'])) {
    $tID = $_GET['tID'];
    
    $stmt = $mysqli->prepare("
        SELECT t.*, g.name as game_name, g.imageurl as game_image 
        FROM {$dbprefix}tournaments t 
        LEFT JOIN {$dbprefix}gamesplayed g ON t.gamesplayed_id = g.gamesplayed_id 
        WHERE t.tournament_id = ?
    ");
    $stmt->bind_param("i", $tID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $tournamentInfo = $result->fetch_assoc();
        $selected_game_name = $tournamentInfo['game_name'];
        $selected_game_image = $MAIN_ROOT . $tournamentInfo['game_image'];
    } else {
        exit();
    }
    $stmt->close();
} else {
    exit();
}

?>


<?php
$arrTimezones = DateTimeZone::listIdentifiers();

$currentYear = date("Y");
$maxYear = $currentYear + 5;
$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[str_pad($m, 2, "0", STR_PAD_LEFT)] = date("F", mktime(0, 0, 0, $m, 1));
}

$startTs = !empty($tournamentInfo['startdate']) ? (int)$tournamentInfo['startdate'] : time();

$defaultMonth = date("m", $startTs);
$defaultYear = date("Y", $startTs);
$defaultDay = date("j", $startTs);

$hour24 = (int)date("G", $startTs);
$defaultAMPM = ($hour24 >= 12) ? "PM" : "AM";
$defaultHour = $hour24 % 12;
if ($defaultHour === 0) {
    $defaultHour = 12;
}
$defaultMinute = date("i", $startTs);

$defaultTimezone = "";

$defaultPrizeCurrency = !empty($tournamentInfo['t_prize_currency']) ? $tournamentInfo['t_prize_currency'] : 'USD';
$defaultRegistrationStatus = isset($tournamentInfo['registration_status']) ? (int)$tournamentInfo['registration_status'] : 1;
$defaultTBM = isset($tournamentInfo['time_between_matches']) ? ($tournamentInfo['time_between_matches'] / 3600) : '';
$defaultTaccess = isset($tournamentInfo['tournamentaccess']) ? (string)$tournamentInfo['tournamentaccess'] : '3';
?>


<div style="max-width:980px; margin:25px auto;">
	<div class="formDiv" style="margin-top: 8px;">
		<h2 class="heading-title">Update Tournament</h2>
		<div style="padding:10px; width:95%; margin:0 auto;">

			<?php
			if($tournamentInfo['startdate'] < time()) {
			?>
			
			<div style="background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); padding: 10px; text-align: center; border-left: 5px solid #ff6b6b;">
				<div style="font-size: 48px; color: #ff6b6b; margin-bottom: 10px;">⏰</div>
				<h2 style="color: #333; margin-bottom: 15px; font-weight: 600;">This tournament has already started. You are not able to update it.</h2>
			</div>
			
			<?php
			}
			?>
			
			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Tournament Name:</label>
				<input type="text" class="input-group-input" id="tournamentname" value="<?php echo $tournamentInfo['name']; ?>" style="padding:10px; width:100%; box-sizing:border-box;" onfocus="remove();">
				<span id="tournamentname-error"></span>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Game:</label>

				<div class="custom-select" style="position: relative; width:100%; max-width:360px; background: #000; border:1px solid #2a2f2c; border-radius:4px; cursor: pointer; font-size: 14px; color: #fff;">
					<div class="selected-option" style="padding:10px 12px; display:flex; align-items:center; justify-content:space-between;">
						<div class="selected-text" style="display:flex; align-items:center; gap:8px;">
							<span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo $selected_game_name; ?></span>
						</div>
						<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
					</div>

					<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:300px; overflow-y:auto; z-index:50;">
						<?php
						$res = $mysqli->query("SELECT * FROM {$dbprefix}gamesplayed ORDER BY gamesplayed_id DESC");
						while ($row = $res->fetch_assoc()):
							$gameId = (int)$row['gamesplayed_id'];
							$gameName = htmlspecialchars($row['name']);
							$imageUrl = htmlspecialchars($row['imageurl']);
							$imgSrc = (strpos($imageUrl, 'http') === 0) ? $imageUrl : ($MAIN_ROOT . $imageUrl);
						?>
							<div class="option" data-value="<?= $gameId ?>" style="padding:10px 12px; display:flex; align-items:center; gap:10px; cursor:pointer; border-bottom:1px solid rgba(255,255,255,0.03);">
								<span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= $gameName ?></span>
							</div>
						<?php endwhile; ?>
					</div>

					<input type="hidden" id="selectedGame" value="<?php echo $tournamentInfo['gamesplayed_id']; ?>">
				</div>
                <span id="game-error"></span>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Description:</label>
				<textarea class="input-group-input" rows="5" cols="35" id="extrainfo" style="width:100%; box-sizing:border-box; padding:10px;" onfocus="remove();"><?php echo $tournamentInfo['description']; ?></textarea>
				<span id="extrainfo-error"></span>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Tournament Prize:</label>
				<div style="position:relative; display:flex; align-items:center; gap:10px;">
					<input type="number" step="any" class="input-group-input" id="t_prize" value="<?php echo $tournamentInfo['t_prize']; ?>" style="width: 150px; padding:10px; box-sizing:border-box;" onfocus="remove();">
					<div class="custom-simple-select" data-name="t_prize_currency" style="position:relative; display:inline-block;">
						<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; gap:20px;">
							<div class="selected-text">USD</div>
							<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
						</div>
						<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:160px; overflow:auto; z-index:40;">
							<div class="option" data-value="USD" style="padding:10px 12px; cursor:pointer;">USD</div>
						</div>
						<input type="hidden" id="t_prize_currency" value="USD">
					</div>
				</div>
				<span id="t_prize-error"></span>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Server:</label>
				<input type="text" class="input-group-input" id="server" value="<?php echo $tournamentInfo['server']; ?>" style="padding:10px; width:100%; box-sizing:border-box;" onfocus="remove();">
                <span id="server-error"></span>
			</div>

			<div style="color:#eee; border: 1px solid #2a2f2c; padding:10px 25px; border-radius:8px; max-width:760px; margin-bottom:30px;">
				<div class="input-group" style="position: relative; margin-top:15px; margin-bottom:30px;">
					
					<div style="font-weight:700; margin-bottom:15px;">Pick Tournament Start Date & Time</div>
					
					<label class="input-group-label" style="min-width:115px;">Start Date:</label>

					<div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
						<div class="custom-simple-select" data-name="month" style="position:relative; display:inline-block;">
							<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:140px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
								<div class="selected-text">Month</div>
								<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
							</div>
							<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:220px; overflow:auto; z-index:40;">
								<?php foreach ($months as $key => $name): ?>
									<div class="option" data-value="<?= $key ?>" style="padding:10px 12px; cursor:pointer;"><?= $name ?></div>
								<?php endforeach; ?>
							</div>
							<input type="hidden" id="month_input" value="<?= htmlspecialchars($defaultMonth) ?>">
						</div>

						<div class="custom-simple-select" data-name="day" style="position:relative; display:inline-block;">
							<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:80px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
								<div class="selected-text">Day</div>
								<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
							</div>
							<div class="options" id="dayOptions" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:220px; overflow:auto; z-index:40;">
								
							</div>
							<input type="hidden" id="day_input" value="<?= htmlspecialchars($defaultDay) ?>">
						</div>

						<div class="custom-simple-select" data-name="year" style="position:relative; display:inline-block;">
							<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:100px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
								<div class="selected-text">Year</div>
								<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
							</div>
							<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:220px; overflow:auto; z-index:40;">
								<?php for ($y = $currentYear; $y <= $maxYear; $y++): ?>
									<div class="option" data-value="<?= $y ?>" style="padding:10px 12px; cursor:pointer;"><?= $y ?></div>
								<?php endfor; ?>
							</div>
							<input type="hidden" id="year_input" value="<?= htmlspecialchars($defaultYear) ?>">
						</div>
					</div>
				</div>

				<div class="input-group" style="position: relative; margin-top:30px; margin-bottom:15px;">
					<label class="input-group-label" style="min-width:115px;">Start Time:</label>

					<div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
						<div class="custom-simple-select" data-name="starthour" style="position:relative; display:inline-block;">
							<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:70px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
								<div class="selected-text"><?= htmlspecialchars($defaultHour) ?></div>
								<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
							</div>
							<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:220px; overflow:auto; z-index:40;">
								<div class="option" data-value="12" style="padding:10px 12px; cursor:pointer;">12</div>
								<?php for ($i = 1; $i <= 11; $i++): ?>
									<div class="option" data-value="<?= $i ?>" style="padding:10px 12px; cursor:pointer;"><?= $i ?></div>
								<?php endfor; ?>
							</div>
							<input type="hidden" id="starthour_input" value="<?= htmlspecialchars($defaultHour) ?>">
						</div>

						<div class="custom-simple-select" data-name="startminute" style="position:relative; display:inline-block;">
							<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:70px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
								<div class="selected-text"><?= htmlspecialchars($defaultMinute) ?></div>
								<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
							</div>
							<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:220px; overflow:auto; z-index:40;">
								<?php for ($i = 0; $i <= 59; $i++):
									$disp = $i < 10 ? "0{$i}" : (string)$i;
								?>
									<div class="option" data-value="<?= $disp ?>" style="padding:10px 12px; cursor:pointer;"><?= $disp ?></div>
								<?php endfor; ?>
							</div>
							<input type="hidden" id="startminute_input" value="<?= htmlspecialchars($defaultMinute) ?>">
						</div>

						<div class="custom-simple-select" data-name="startampm" style="position:relative; display:inline-block;">
							<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:70px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
								<div class="selected-text"><?= htmlspecialchars($defaultAMPM) ?></div>
								<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
							</div>
							<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:120px; overflow:auto; z-index:40;">
								<div class="option" data-value="AM" style="padding:10px 12px; cursor:pointer;">AM</div>
								<div class="option" data-value="PM" style="padding:10px 12px; cursor:pointer;">PM</div>
							</div>
							<input type="hidden" id="startampm_input" value="<?= htmlspecialchars($defaultAMPM) ?>">
						</div>

						<div class="custom-simple-select" data-name="startimezone" style="position:relative; display:inline-block; min-width:220px;">
							<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:220px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
								<div class="selected-text">[Use Default]</div>
								<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
							</div>
							<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:300px; overflow:auto; z-index:40;">
								<div class="option" data-value="" style="padding:10px 12px; cursor:pointer;">[Use Default]</div>
								<?php
								foreach ($arrTimezones as $timeZone) {
									$tz = new DateTimeZone($timeZone);
									$dispOffset = (($tz->getOffset(new DateTime("now", $tz))) / 60 / 60);
									$dispSign = ($dispOffset < 0) ? "" : "+";
									$label = str_replace("_", " ", $timeZone) . " (UTC{$dispSign}{$dispOffset})";
								?>
									<div class="option" data-value="<?= htmlspecialchars($timeZone) ?>" style="padding:10px 12px; cursor:pointer;"><?= htmlspecialchars($label) ?></div>
								<?php } ?>
							</div>
							<input type="hidden" id="startimezone_input" value="<?= htmlspecialchars($defaultTimezone) ?>">
						</div>
					</div>
				</div>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Time between Rounds:</label>
				<div style="position:relative; display:flex; align-items:center; gap:10px;">
					<input type="number" class="input-group-input" id="TPR" value="<?php echo htmlspecialchars(($tournamentInfo['TPR'] / 3600) ?? ''); ?>" style="width: 150px; padding:10px; box-sizing:border-box;" onfocus="remove();">
					<div class="custom-simple-select" data-name="TPRA" style="position:relative; display:inline-block;">
						<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; gap:20px;">
							<div class="selected-text">Hours</div>
							<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
						</div>
						<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:160px; overflow:auto; z-index:40;">
							<div class="option" data-value="hours" style="padding:10px 12px; cursor:pointer;">Hours</div>
						</div>
						<input type="hidden" id="TPRA" value="hours">
					</div>
				</div>
				<span id="TPR-error"></span>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Time between Each Match:</label>
				<div style="position:relative; display:flex; align-items:center; gap:10px;">
					<input type="number" class="input-group-input" id="TBM" value="<?= htmlspecialchars($defaultTBM) ?>" style="width: 150px; padding:10px; box-sizing:border-box;" onfocus="remove();">
					<div class="custom-simple-select" data-name="TBMA" style="position:relative; display:inline-block;">
						<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; gap:20px;">
							<div class="selected-text">Hours</div>
							<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
						</div>
						<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:160px; overflow:auto; z-index:40;">
							<div class="option" data-value="hours" style="padding:10px 12px; cursor:pointer;">Hours</div>
						</div>
						<input type="hidden" id="TBMA" value="hours">
					</div>
				</div>
				<span id="TBM-error"></span>
			</div>

            <div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Registration Status:</label>
				<div class="custom-simple-select" data-name="registrationstatus" style="position:relative; display:inline-block;">
					<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:160px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
						<div class="selected-text"><?= $defaultRegistrationStatus ? 'Open' : 'Closed' ?></div>
						<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
					</div>
					<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:160px; overflow:auto; z-index:40;">
						<div class="option" data-value="1" style="padding:10px 12px; cursor:pointer;">Open</div>
						<div class="option" data-value="0" style="padding:10px 12px; cursor:pointer;">Closed</div>
					</div>
					<input type="hidden" id="registrationstatus_input" value="<?= (int)$defaultRegistrationStatus ?>">
				</div>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Eliminations:</label>
				<div class="custom-simple-select" data-name="eliminations" style="position:relative; display:inline-block;">
					<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:200px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
						<div class="selected-text">Single Elimination</div>
						<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
					</div>
					<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:160px; overflow:auto; z-index:40;">
						<div class="option" data-value="1" style="padding:10px 12px; cursor:pointer;">Single Elimination</div>
						<!-- <div class="option" data-value="2" style="padding:10px 12px; cursor:pointer;">Double Elimination</div> -->
					</div>
					<input type="hidden" id="eliminations_input" value="1">
				</div>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Seed Type:</label>
				<div class="custom-simple-select" data-name="seedtype" style="position:relative; display:inline-block;">
					<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:150px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
						<div class="selected-text">Random</div>
						<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
					</div>
					<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:160px; overflow:auto; z-index:40;">
						<div class="option" data-value="2" style="padding:10px 12px; cursor:pointer;">Random</div>
						<div class="option" data-value="3" style="padding:10px 12px; cursor:pointer;">Sequential</div>
					</div>
					<input type="hidden" id="seedtype_input" value="2">
				</div>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Total Teams/Players:</label>
				<div class="custom-simple-select" data-name="totalteams" style="position:relative; display:inline-block;">
					<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:120px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
						<div class="selected-text"><?php echo $tournamentInfo['maxteams']; ?></div>
						<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
					</div>
					<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:200px; overflow:auto; z-index:40;">
						<div class="option" data-value="2" style="padding:10px 12px; cursor:pointer;">2</div>
						<div class="option" data-value="4" style="padding:10px 12px; cursor:pointer;">4</div>
						<div class="option" data-value="8" style="padding:10px 12px; cursor:pointer;">8</div>
						<div class="option" data-value="16" style="padding:10px 12px; cursor:pointer;">16</div>
						<div class="option" data-value="32" style="padding:10px 12px; cursor:pointer;">32</div>
						<div class="option" data-value="64" style="padding:10px 12px; cursor:pointer;">64</div>
						<div class="option" data-value="128" style="padding:10px 12px; cursor:pointer;">128</div>
					</div>
					<input type="hidden" id="totalteams_input" value="<?php echo $tournamentInfo['maxteams']; ?>">
				</div>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Players Per Team:</label>
				<div class="custom-simple-select" data-name="playersperteam" style="position:relative; display:inline-block;">
					<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:120px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
						<div class="selected-text"><?php echo $tournamentInfo['playersperteam']; ?></div>
						<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
					</div>
					<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:220px; overflow:auto; z-index:40;">
						<?php for ($i = 1; $i <= 4; $i++): ?>
							<div class="option" data-value="<?= $i ?>" style="padding:10px 12px; cursor:pointer;"><?= $i ?></div>
						<?php endfor; ?>
					</div>
					<input type="hidden" id="playersperteam_input" value="<?php echo $tournamentInfo['playersperteam']; ?>">
				</div>
			</div>

			<div class="input-group" style="position: relative; margin-bottom:30px;">
				<label class="input-group-label" style="display:block; margin-bottom:6px;">Access:</label>
				<div class="custom-simple-select" data-name="tournamentaccess" style="position:relative; display:inline-block;">
					<div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid #2a2f2c; color:#fff; border-radius:6px; min-width:160px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
						<div class="selected-text"><?= ((string)$defaultTaccess === '1') ? 'Private' : 'Public' ?></div>
						<div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
					</div>
					<div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid #2a2f2c; max-height:160px; overflow:auto; z-index:40;">
						<div class="option" data-value="1" style="padding:10px 12px; cursor:pointer;">Private</div>
						<div class="option" data-value="3" style="padding:10px 12px; cursor:pointer;">Public</div>
					</div>
					<input type="hidden" id="tournamentaccess_input" value="<?= htmlspecialchars($defaultTaccess) ?>">
				</div>
			</div>

			<div class="input-group" style="position: relative; margin-top:50px;">
				<input id="submit" value="Update Tournament" class="btn" style="width:100%;">
			</div>

		</div>
	</div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

  
  function addHoverEffects(optionsBox) {
    if (!optionsBox) return;
    optionsBox.querySelectorAll('.option').forEach(function(opt) {
      if (opt._hoverBound) return;
      opt._hoverBound = true;
      opt.addEventListener('mouseenter', function() { this._oldBg = this.style.background || ''; this.style.background = 'rgb(34 34 34)'; });
      opt.addEventListener('mouseleave', function() { this.style.background = this._oldBg || ''; });
    });
  }

  function initCustomSelect(container) {
    if (!container) return;
    var selected = container.querySelector('.selected-option');
    var selectedText = selected ? selected.querySelector('.selected-text') : null;
    var optionsBox = container.querySelector('.options');
    var hidden = container.querySelector('input[type=hidden]');
    var arrow = selected ? selected.querySelector('.arrow') : null;

    if (selected && !selected._bound) {
      selected._bound = true;
      selected.addEventListener('click', function (e) {
        document.querySelectorAll('.custom-simple-select .options').forEach(function(op) {
          if (op !== optionsBox) op.style.display = 'none';
        });
        document.querySelectorAll('.custom-simple-select .arrow').forEach(function(a) {
          if (a !== arrow) a.style.transform = 'rotate(0deg)';
        });

        var isOpen = optionsBox.style.display === 'block';
        optionsBox.style.display = isOpen ? 'none' : 'block';
        if (arrow) arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
      });
    }

    if (optionsBox) {
      optionsBox.querySelectorAll('.option').forEach(function(opt) {
        if (opt._bound) return;
        opt._bound = true;
        opt.addEventListener('click', function () {
          var val = this.getAttribute('data-value');
          var label = this.textContent.trim();
          if (hidden) hidden.value = val;
          if (selectedText) selectedText.textContent = label;
          optionsBox.style.display = 'none';
          if (arrow) arrow.style.transform = 'rotate(0deg)';

          var cName = container.getAttribute('data-name');
          if (cName === 'month' || cName === 'year') {
            setTimeout(function(){ if (typeof populateDaysFromSelected === 'function') populateDaysFromSelected(); }, 0);
          }
        });
      });

      addHoverEffects(optionsBox);
    }
  }

  function populateDaysFromSelected() {
    var monthValEl = document.getElementById('month_input');
    var yearValEl  = document.getElementById('year_input');
    if (!monthValEl || !yearValEl) return;

    var monthVal = monthValEl.value;
    var yearVal = yearValEl.value;
    if (!monthVal || !yearVal) return;

    var monthInt = parseInt(monthVal, 10);
    var yearInt = parseInt(yearVal, 10) || new Date().getFullYear();
    var daysInMonth = new Date(yearInt, monthInt, 0).getDate();

    var dayOptionsContainer = document.getElementById('dayOptions');
    if (!dayOptionsContainer) return;

    dayOptionsContainer.innerHTML = '';

    for (var d = 1; d <= daysInMonth; d++) {
      var div = document.createElement('div');
      div.className = 'option';
      div.setAttribute('data-value', d);
      div.style.padding = '10px';
      div.style.cursor = 'pointer';
      div.textContent = d;
      dayOptionsContainer.appendChild(div);
    }

    var dayContainer = document.querySelector('.custom-simple-select[data-name="day"]');
    if (dayContainer) {
      dayContainer.querySelectorAll('.options .option').forEach(function(o){ o._bound = o._hoverBound = false; });

      initCustomSelect(dayContainer);

      var hiddenDay = document.getElementById('day_input');
      var hiddenDayVal = hiddenDay && hiddenDay.value ? parseInt(hiddenDay.value, 10) : null;

      var today = new Date();
      var chooseDay = 1;
      if (hiddenDayVal && hiddenDayVal <= daysInMonth) {
        chooseDay = hiddenDayVal;
      } else if (today.getFullYear() === yearInt && (today.getMonth()+1) === monthInt && today.getDate() <= daysInMonth) {
        chooseDay = today.getDate();
      } else {
        chooseDay = Math.min(daysInMonth, hiddenDayVal || 1);
      }

      if (hiddenDay) hiddenDay.value = chooseDay;
      var selText = dayContainer.querySelector('.selected-text');
      if (selText) selText.textContent = String(chooseDay);
    }
  }

  document.querySelectorAll('.custom-simple-select').forEach(function(container) {
    initCustomSelect(container);
  });

  document.querySelectorAll('.custom-simple-select').forEach(function(container) {
    var hidden = container.querySelector('input[type=hidden]');
    var selText = container.querySelector('.selected-text');
    if (hidden && hidden.value && selText) {
      var val = hidden.value;
      var match = container.querySelector('.options .option[data-value="' + val + '"]');
      if (match) selText.textContent = match.textContent.trim();
    }
  });

  populateDaysFromSelected();

  (function () {
    var selectBox = document.querySelector('.custom-select');
    if (!selectBox) return;
    var selected = selectBox.querySelector('.selected-option');
    var selectedTextContainer = selected.querySelector('.selected-text');
    var optionsContainer = selectBox.querySelector('.options');
    var hiddenInput = document.getElementById('selectedGame');
    var arrow = selected.querySelector('.arrow');

    if (!selected._boundGame) {
      selected._boundGame = true;
      selected.addEventListener('click', function () {
        var isOpen = optionsContainer.style.display === 'block';
        optionsContainer.style.display = isOpen ? 'none' : 'block';
        arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
        arrow.style.transition = 'transform 0.15s';
      });
    }

    optionsContainer.querySelectorAll('.option').forEach(function (option) {
      if (option._boundGame) return;
      option._boundGame = true;
      option.addEventListener('mouseenter', function(){ this.style.background = 'rgb(34 34 34)'; });
      option.addEventListener('mouseleave', function(){ this.style.background = ''; });

      option.addEventListener('click', function () {
        var value = this.getAttribute('data-value');
        var imgEl = this.querySelector('img');
        var textEl = this.querySelector('span');
        var text = textEl ? textEl.textContent.trim() : (this.textContent || '').trim();

        if (hiddenInput) hiddenInput.value = value;
        if (imgEl) {
          selectedTextContainer.innerHTML = '<img src="' + imgEl.src + '" alt="' + text + '" style="width:30px;height:30px;border-radius:4px;object-fit:cover;margin-right:8px;"><span>' + text + '</span>';
        } else {
          selectedTextContainer.textContent = text;
        }
        optionsContainer.style.display = 'none';
        arrow.style.transform = 'rotate(0deg)';
      });
    });

    document.addEventListener('click', function (e) {
      if (!selectBox.contains(e.target)) {
        optionsContainer.style.display = 'none';
        if (arrow) arrow.style.transform = 'rotate(0deg)';
      }
    });
  })();

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.custom-simple-select') && !e.target.closest('.custom-select')) {
      document.querySelectorAll('.custom-simple-select .options').forEach(function(op) { op.style.display = 'none'; });
      document.querySelectorAll('.custom-simple-select .arrow').forEach(function(a) { a.style.transform = 'rotate(0deg)'; });
    }
  });
  
  const submitButton = document.getElementById('submit');
  submitButton.addEventListener('click', function() {
	let tournamentName = document.getElementById('tournamentname').value;
	let game = document.getElementById('selectedGame').value;
	let extraInfo = document.getElementById('extrainfo').value;
	let tPrize = document.getElementById('t_prize').value;
	let tPrizeCurrency = document.getElementById('t_prize_currency').value;
	let TPR = document.getElementById('TPR').value;
	let TPRA = document.getElementById('TPRA').value;
	let eliminationsInput = document.getElementById('eliminations_input').value;
	let seedtypeInput = document.getElementById('seedtype_input').value;
	let totalteamsInput = document.getElementById('totalteams_input').value;
	let playersperteamInput = document.getElementById('playersperteam_input').value;
	let server = document.getElementById('server').value;
	let tournamentAccessInput = document.getElementById('tournamentaccess_input').value;

	let month = document.getElementById('month_input').value;
	let day = document.getElementById('day_input').value;
	let year = document.getElementById('year_input').value;
	let starthour = document.getElementById('starthour_input').value;
	let startminute = document.getElementById('startminute_input').value;
	let startampm = document.getElementById('startampm_input').value;
	let startimezone = document.getElementById('startimezone_input').value;

	let registrationStatus = document.getElementById('registrationstatus_input').value;
	let timeBetweenMatches = document.getElementById('TBM').value;
	let timeBetweenMatchesUnit = document.getElementById('TBMA').value;

	let csrfToken = '<?php echo $_SESSION['csrftokken']; ?>';

	let payload = {
		t_id: <?php echo (int)$tID; ?>,
		tournamentname: tournamentName,
		game: game,
		extrainfo: extraInfo,
		t_prize: tPrize,
		tPrizeCurrency: tPrizeCurrency,
		TPR: TPR,
		TPRA: TPRA,
		eliminations_input: eliminationsInput,
		seedtype_input: seedtypeInput,
		totalteams_input: totalteamsInput,
		playersperteam_input: playersperteamInput,
		server: server,
		tournamentaccess_input: tournamentAccessInput,
		month: month,
		day: day,
		year: year,
		starthour: starthour,
		startminute: startminute,
		startampm: startampm,
		startimezone: startimezone,
		registration_status: registrationStatus,
		time_between_matches: timeBetweenMatches,
		time_between_matches_unit: timeBetweenMatchesUnit,
		csrf_token: csrfToken
	};

	let params = {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json'
		},
		body: JSON.stringify(payload)
	};

	fetch('<?php echo $MAIN_ROOT; ?>competitions/backend/edit_tournament.php', params)
		.then((response) => response.json())
		.then(function (data) {
			document.getElementById('tournamentname').style.color = '#ffffff';
			document.getElementById('tournamentname').style.borderColor = '#ffeb3b33';
			document.getElementById('tournamentname-error').innerHTML = '';

        	document.getElementById('game-error').innerHTML = '';

        	document.getElementById('extrainfo').style.color = '#ffffff';
			document.getElementById('extrainfo').style.borderColor = '#ffeb3b33';
			document.getElementById('extrainfo-error').innerHTML = '';

			document.getElementById('server').style.borderColor = '#ffeb3b33';
        	document.getElementById('server-error').innerHTML = '';

			document.getElementById('t_prize').style.color = '#ffffff';
			document.getElementById('t_prize').style.borderColor = '#ffeb3b33';
			document.getElementById('t_prize-error').innerHTML = '';

			document.getElementById('TPR').style.color = '#ffffff';
			document.getElementById('TPR').style.borderColor = '#ffeb3b33';
			document.getElementById('TPR-error').innerHTML = '';

			document.getElementById('TBM').style.color = '#ffffff';
			document.getElementById('TBM').style.borderColor = '#ffeb3b33';
			document.getElementById('TBM-error').innerHTML = '';

			if (data.status == 'success') {
				showNotification(data.msg, 'success');
			} else if (data.status == 'error') {
				if (data.msg) showNotification(data.msg, 'error');
				if (data.bor) document.querySelector(data.plc).innerHTML = data.msg;
				if (data.plc) document.querySelector(data.bor).style.borderColor = '#c15755';
				if (data.inp) document.querySelector(data.inp).style.color = '#c15755';
			} else {
				showNotification(data.msg, 'error');
			}
		});
  });
  
});
</script>

<script>
function remove() {
	document.getElementById('tournamentname').style.color = '#ffffff';
	document.getElementById('extrainfo').style.color = '#ffffff';
	document.getElementById('t_prize').style.color = '#ffffff';
	document.getElementById('TPR').style.color = '#ffffff';
	document.getElementById('TBM').style.color = '#ffffff';
}
</script>

<?php
include("../assets/_footer.php");
?>