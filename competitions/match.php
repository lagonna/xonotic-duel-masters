<?php
include("../_intro.php");

$prevFolder = "../";
$PAGE_NAME = "Match - ";
include("../assets/_header.php");

if (!isset($_GET['tID']) || !is_numeric($_GET['tID']) || !isset($_GET['mID']) || !is_numeric($_GET['mID'])) {
    echo '<div style="padding:20px">Invalid match or tournament ID.</div>';
    include($prevFolder . "assets/_footer.php");
    exit;
}

$tID = intval($_GET['tID']);
$mID = intval($_GET['mID']);
$csrftok = $_SESSION['csrftokken'] ?? '';
$apiUrl = MAIN_ROOT . "competitions/backend/match_action.php"; // admin-only backend

if (!isset($dbprefix)) $dbprefix = '';
$matchTable = $dbprefix . "tournamentmatch";
$tournamentsTable = $dbprefix . "tournaments";
$playerTable = $dbprefix . "tournamentplayers";
$membersTable = $dbprefix . "members";

function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function fullUrl($maybePath) {
    if (empty($maybePath)) return '';
    if (stripos($maybePath, 'http') === 0) return $maybePath;
    return rtrim(MAIN_ROOT, '/') . '/' . ltrim($maybePath, '/');
}
function getMatchStatus($matchInfo) {
    $now = time();
    $start = isset($matchInfo['match_start']) ? intval($matchInfo['match_start']) : 0;
    $duration = isset($matchInfo['time_per_round']) ? intval($matchInfo['time_per_round']) : 0;
    $end = $start + max(0, $duration);

    $outcome = isset($matchInfo['outcome']) ? intval($matchInfo['outcome']) : 0;
    if ($outcome === 1 || $outcome === 2) {
        return ['state' => 'finished', 'label' => 'Ended', 'color' => '#6b7280'];
    }
    if ($start <= 0) {
        return ['state' => 'pending', 'label' => 'Not scheduled', 'color' => '#777777'];
    }
    if ($start > $now) {
        return ['state' => 'upcoming', 'label' => 'Upcoming', 'color' => '#0a84ff'];
    }
    if ($start <= $now && ($duration <= 0 || $now < $end)) {
        return ['state' => 'live', 'label' => 'Live', 'color' => '#16a34a'];
    }
    return ['state' => 'finished', 'label' => 'Finished', 'color' => '#6b7280'];
}

$payloadError = null;
try {
    $stmt = $mysqli->prepare("SELECT * FROM {$matchTable} WHERE tournamentmatch_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('DB prepare failed (match): ' . $mysqli->error);
    $stmt->bind_param('i', $mID);
    $stmt->execute();
    $r = $stmt->get_result();
    $match = $r ? $r->fetch_assoc() : null;
    $stmt->close();
    if (!$match) throw new Exception('Match not found.');

    $stmt = $mysqli->prepare("SELECT * FROM {$tournamentsTable} WHERE tournament_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('DB prepare failed (tournament): ' . $mysqli->error);
    $stmt->bind_param('i', $tID);
    $stmt->execute();
    $r = $stmt->get_result();
    $tournament = $r ? $r->fetch_assoc() : null;
    $stmt->close();
    if (!$tournament) throw new Exception('Tournament not found.');

    $memberId = intval($_SESSION['user_id'] ?? null);
    $is_manager = (intval($tournament['member_id']) === $memberId);

    $getPlayers = function(int $teamId) use ($mysqli, $playerTable, $membersTable, $tID) {
        $out = [];
        if ($teamId <= 0) return $out;
        $sql = "SELECT tp.tournamentplayer_id, tp.member_id, tp.displayname, m.username, m.username_style, COALESCE(m.avatar,'') AS avatar
        FROM {$playerTable} tp
        LEFT JOIN {$membersTable} m ON m.member_id = tp.member_id
        WHERE tp.team_id = ? AND tp.tournament_id = ?
        ORDER BY tp.tournamentplayer_id";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) return $out;
        $stmt->bind_param('ii', $teamId, $tID);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['avatar']) && stripos($row['avatar'], 'http') !== 0) {
                $row['avatar'] = rtrim(MAIN_ROOT, '/') . '/' . ltrim($row['avatar'], '/');
            }
            $out[] = $row;
        }
        $stmt->close();
        return $out;
    };

    $team1_players = $getPlayers(intval($match['team1_id']));
    $team2_players = $getPlayers(intval($match['team2_id']));

    $match_replay1 = !empty($match['replayteam1url']) ? fullUrl($match['replayteam1url']) : '';
    $match_replay2 = !empty($match['replayteam2url']) ? fullUrl($match['replayteam2url']) : '';

    $tournamentTz = (!empty($tournament['timezone']) && in_array($tournament['timezone'], DateTimeZone::listIdentifiers(), true))
        ? $tournament['timezone']
        : date_default_timezone_get();

    $tzObj          = new DateTimeZone($tournamentTz);
    $tzOffsetHours  = $tzObj->getOffset(new DateTime('now', $tzObj)) / 3600;
    $tzOffsetLabel  = ($tzOffsetHours >= 0 ? '+' : '') . $tzOffsetHours;
    $tzDisplayName  = str_replace('_', ' ', $tournamentTz) . " (UTC{$tzOffsetLabel})";

    $mStartLocalValue = '';
    if (!empty($match['match_start']) && intval($match['match_start']) > 0) {
        $dtDisp = new DateTime('@' . intval($match['match_start']));
        $dtDisp->setTimezone($tzObj);
        $mStartLocalValue = $dtDisp->format('Y-m-d\TH:i');
    }

} catch (Throwable $e) {
    $payloadError = $e->getMessage();
}
?>

<style>
.match-wrap{max-width:980px;margin:0px auto;}
.card{border:1px solid rgba(0,255,65,0.2);border-radius:10px;padding:12px;margin-bottom:28px;color:#e6eef6;}
.h{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.h .title{font-size:1.05rem;color:#00ff41;}
.muted{color:#9aa3b2;font-size:0.9rem}
.teams{display:flex;align-items: center;gap:18px;flex-wrap:wrap;margin-top:10px;border:none;}
.team{flex:1;min-width:240px;padding:18px;border-radius:10px;border:1px solid rgba(0,255,65,0.2);position:relative}
.player{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.player img{width:48px;height:48px;border-radius:8px;object-fit:cover;border:1px solid #1b2630}
.player .name{font-weight:700}
.player a { text-decoration: none; font-weight:700; }
.player a:hover { text-decoration: underline; }
.gamer_id { display:block; font-size:0.85rem; color:#9aa3b2; margin-top:2px; }

.submitted-row { margin-top:12px; display:flex; align-items:center; gap:10px; }
.submitted-label { color:#9aa3b2; font-weight:600; font-size:0.95rem; }
.submitted-badge { display:inline-block; padding:10px 14px; border-radius:12px; font-weight:800; min-width:64px; text-align:center; background: rgba(255,255,255,0.02); color:#e6eef6; border:1px solid rgb(255 255 255 / 30%); box-shadow: inset 0 -8px 18px rgba(0,0,0,0.3); }

.submitted-winner { color: #0bf115; border-color: rgba(11,241,21,0.40); background: rgba(11,241,21,0.15); }
.submitted-loser { color: #ff6b6b; border-color: rgba(255,107,107,0.30); background: rgba(255,107,107,0.12); }
.submitted-neutral { color: #e6eef6; }

.winner-header {
    position: absolute;
    top: 5px;
    left: 50%;
    transform: translateX(-50%);
    background: #0bf115;
    color: #022;
    padding: 6px 12px;
    border-radius: 999px;
    font-weight: 800;
    font-size: 12px;
    box-shadow: 0 6px 18px rgba(11,241,21,0.12);
}

.input{width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,255,65,0.2);background:#000;color:#fff}
.row{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
.col{flex:1;min-width:140px}
.preview{max-width:160px;border-radius:8px;border:1px solid #21303a;margin-top:8px}
.notice{padding:10px;border-radius:8px;margin-top:10px}
.notice.success{background:rgba(2,128,0,0.08);color:#b6f7c1}
.notice.error{background:rgba(255,70,70,0.08);color:#ffc9c9;border:1px solid #6a3e3e;text-align:center;}
.small{font-size:0.9rem;color:#9aa3b2}
#formMessage{margin-top:20px;display:none;}
@media (max-width:740px){ .teams{flex-direction:column} .h{align-items:flex-start} .team{padding:12px} .player img{width:40px;height:40px} }


.live-pill { padding:6px 12px; border-radius:999px; background:linear-gradient(90deg,#ff4d4d,#ff7043);
  color:#fff; font-weight:800; box-shadow:0 0 12px rgba(255,80,80,0.25);
  animation:livePulse 1.2s infinite ease-in-out; font-size:14px; display:flex; align-items:center; gap:6px; }
@keyframes livePulse {
  0%   { transform:scale(1);    box-shadow:0 0 10px rgba(255,80,80,0.18); }
  50%  { transform:scale(1.03); box-shadow:0 0 18px rgba(255,80,80,0.28); }
  100% { transform:scale(1);    box-shadow:0 0 10px rgba(255,80,80,0.18); }
}
.ongoing-dot {
  display:inline-block; width:10px; height:10px; border-radius:50%; background:#fff;
  animation:pulseFade 1.5s ease-in-out infinite; box-shadow:0 0 8px rgba(255,255,255,0.2);
}
@keyframes pulseFade { 0%{opacity:0.2} 50%{opacity:1} 100%{opacity:0.2} }
.countdown-glow { font-weight:900; font-size:18px; color:#ffd78a; }

.manager-form{display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-top:10px}
.manager-form .mform-field{flex:1;min-width:220px;display:flex;flex-direction:column;gap:6px}
.btn-apply{flex:0 0 auto;padding:11px 24px;border-radius:8px;background:#00ff41;color:#000;font-weight:800;border:none;cursor:pointer}
.btn-apply:hover{filter:brightness(0.95)}
@media (max-width:600px){ .manager-form{flex-direction:column;align-items:stretch} .btn-apply{width:100%} }
</style>

<main id="app-root" class="container" role="main" tabindex="-1">
<div id="match-root" class="match-wrap">
  <?php if ($payloadError): ?>
    <div class="card notice error"><?= esc($payloadError) ?></div>
  <?php else: ?>
    <div class="card h" aria-hidden="false">
      <div>
        <div class="title"><?= esc($tournament['name'] ?? 'Tournament') ?></div>
        <div class="small muted">Match #<?= esc($match['tournamentmatch_id']) ?></div>
      </div>
      <div style="text-align:right">
        <?php
          $status  = getMatchStatus($match);
          $startTs = intval($match['match_start'] ?? 0);
          $tpr     = intval($tournament['TPR'] ?? 0) * 3600;
          $endTs   = $startTs + max(0, $tpr);

          if ($status['state'] === 'live') {
              $statusElem = "<div class='live-pill'><span class='ongoing-dot'></span>LIVE</div>";
          } elseif ($startTs <= 0) {
              $statusElem = "<div style='background:{$status['color']};color:#fff;padding:6px 12px;border-radius:10px;font-size:12px;font-weight:bold;'>{$status['label']}</div>";
          } else {
              $statusElem = "<div class='countdown' data-start-ts='{$startTs}' data-end-ts='{$endTs}' data-state='{$status['state']}'></div>";
          }
        ?>
        <div class="muted">
          Start:
          <span class="local-dt" data-ts="<?= $startTs ?>">
            <?= $startTs ? esc(date("M j, Y H:i", $startTs) . " UTC") : "Not set" ?>
          </span>
        </div>
        <div id="status-wrap" aria-live="polite" style="display:inline-flex;align-items:center;gap:12px;margin-left:8px;margin-top:6px;">
          <?= $statusElem ?>
        </div>
      </div>
    </div>

    <?php
      $ppt = intval($tournament['playersperteam'] ?? 1);
      $entityLabel = ($ppt > 1) ? 'Team' : 'Player';
      $outcome = intval($match['outcome']);
      $team1OutcomeClass = 'submitted-neutral'; $team2OutcomeClass = 'submitted-neutral';
      if ($outcome === 1) { $team1OutcomeClass = 'submitted-winner'; $team2OutcomeClass = 'submitted-loser'; }
      elseif ($outcome === 2) { $team2OutcomeClass = 'submitted-winner'; $team1OutcomeClass = 'submitted-loser'; }
    ?>

    <div class="card teams" role="region" aria-label="Match teams">
      <div class="team" id="team-1">
        <?php if ($outcome === 1): ?><div class="winner-header">WINNER</div><?php endif; ?>
        <h4 style="margin: 6px 0;"><?= esc($entityLabel) ?> 1</h4>
        <?php if (empty($team1_players)): ?><div class="small muted">No players</div>
        <?php else: foreach ($team1_players as $p): ?>
          <div class="player">
            <img src="<?= esc($p['avatar'] ?: (MAIN_ROOT . 'images/thumbs/default.png')) ?>" alt="avatar">
            <div>
              <div class="name"><a href="<?= esc(rtrim(MAIN_ROOT,'/') . '/member.php?mID=' . intval($p['member_id'])) ?>"><?= renderStyledUsername($p['username'], $p['username_style']) ?: esc($p['displayname']) ?: 'Unknown' ?></a></div>
            </div>
          </div>
        <?php endforeach; endif; ?>

        <div class="submitted-row">
          <div class="submitted-badge <?= esc($team1OutcomeClass) ?>" id="submitted-1"><?= esc($match['team1score'] !== null && $match['team1score'] !== '' ? $match['team1score'] : ($match['team1score0'] ?? '-')) ?></div>
        </div>

        <?php if ($is_manager): ?>
          <div style="margin-top:12px">
            <label class="small muted"><?= esc($entityLabel) ?> 1 score</label>
            <input id="m_team1" type="text" class="input" value="<?= esc($match['team1score'] ?? $match['team1score0'] ?? '') ?>">
          </div>
        <?php endif; ?>

        <?php if ($match_replay1): ?>
          <div style="margin-top:12px"><a href="<?= esc($match_replay1) ?>" target="_blank" rel="noopener"><img class="preview" src="<?= esc($match_replay1) ?>" alt="screenshot"></a></div>
        <?php endif; ?>
      </div>

      <div style="font-size:26px;font-weight:bold;color:#00ff41;text-shadow:0 0 8px #00ff41;">VS</div>

      <div class="team" id="team-2">
        <?php if ($outcome === 2): ?><div class="winner-header">WINNER</div><?php endif; ?>
        <h4 style="margin: 6px 0;"><?= esc($entityLabel) ?> 2</h4>
        <?php if (empty($team2_players)): ?><div class="small muted">No players</div>
        <?php else: foreach ($team2_players as $p): ?>
          <div class="player">
            <img src="<?= esc($p['avatar'] ?: (MAIN_ROOT . 'images/thumbs/default.png')) ?>" alt="avatar">
            <div>
              <div class="name"><a href="<?= esc(rtrim(MAIN_ROOT,'/') . '/member.php?mID=' . intval($p['member_id'])) ?>"><?= renderStyledUsername($p['username'], $p['username_style']) ?: esc($p['displayname']) ?: 'Unknown' ?></a></div>
            </div>
          </div>
        <?php endforeach; endif; ?>

        <div class="submitted-row">
          <div class="submitted-badge <?= esc($team2OutcomeClass) ?>" id="submitted-2"><?= esc($match['team2score'] !== null && $match['team2score'] !== '' ? $match['team2score'] : ($match['team2score0'] ?? '-')) ?></div>
        </div>

        <?php if ($is_manager): ?>
          <div style="margin-top:12px">
            <label class="small muted"><?= esc($entityLabel) ?> 2 score</label>
            <input id="m_team2" type="text" class="input" value="<?= esc($match['team2score'] ?? $match['team2score0'] ?? '') ?>">
          </div>
        <?php endif; ?>

        <?php if ($match_replay2): ?>
          <div style="margin-top:12px"><a href="<?= esc($match_replay2) ?>" target="_blank" rel="noopener"><img class="preview" src="<?= esc($match_replay2) ?>" alt="screenshot"></a></div>
        <?php endif; ?>
      </div>
    </div>

    <div id="formMessage" style="margin-top:20px;margin-bottom:20px;display:none;"></div>

    <?php if ($is_manager): ?>
      <div class="card" id="submission-card">
        <div class="manager-form">

          <div class="mform-field">
            <label class="small muted">Set winner</label>
            <div class="custom-simple-select" data-name="winner" style="position:relative; display:inline-block; width:100%;">
              <div class="selected-option" style="padding:10px 12px; background:#000; border:1px solid rgba(0,255,65,0.2); color:#fff; border-radius:6px; display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                <div class="selected-text"><?= $outcome === 1 ? esc($entityLabel . ' 1') : ($outcome === 2 ? esc($entityLabel . ' 2') : 'None') ?></div>
                <div class="arrow" style="font-size:12px; color:#fff; pointer-events:none;">▼</div>
              </div>
              <div class="options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#000; border:1px solid rgba(0,255,65,0.2); max-height:160px; overflow:auto; z-index:40;">
                <div class="option" data-value="0" style="padding:10px 12px; cursor:pointer;">None</div>
                <div class="option" data-value="1" style="padding:10px 12px; cursor:pointer;"><?= esc($entityLabel) ?> 1</div>
                <div class="option" data-value="2" style="padding:10px 12px; cursor:pointer;"><?= esc($entityLabel) ?> 2</div>
              </div>
              <input type="hidden" id="m_winner" value="<?= $outcome ?>">
            </div>
          </div>

          <div class="mform-field">
            <label class="small muted" for="m_start">Match start — <?= esc($tzDisplayName) ?></label>
            <input id="m_start" type="datetime-local" class="input" value="<?= esc($mStartLocalValue) ?>">
            <!--<span class="small muted" style="font-size:0.78rem;">Entered in the tournament's own timezone, not your browser's.</span>-->
          </div>

          <button type="button" id="mgrBtn" class="btn btn-apply">Apply Winner &amp; Save</button>
        </div>
      </div>
    <?php else: ?>
      <div class="card notice error">Only the tournament manager can modify match results on this page.</div>
    <?php endif; ?>

  <?php endif; ?>
</div>
</main>

<script>
(function(){
  const API = <?= json_encode($apiUrl) ?>;
  const tID = <?= json_encode($tID) ?>;
  const mID = <?= json_encode($mID) ?>;
  const CSRFTOK = <?= json_encode($csrftok) ?>;

  function showFormMsg(text, success = true) {
    const fm = document.getElementById('formMessage');
    if (fm) {
      fm.style.display = 'block';
      fm.style.color = success ? '#00ff26' : '#b1190e';
      fm.style.background = success ? 'rgb(0 255 0 / 12%)' : 'rgb(239 179 179)';
      fm.style.fontWeight = '600';
      fm.style.padding = '10px';
      fm.style.borderRadius = '3px';
      fm.innerText = text;
      setTimeout(()=> { fm.style.display = 'none'; }, 6000);
      return;
    }
    alert(text);
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
      selected.addEventListener('click', function () {
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
        });
      });
    }

    document.addEventListener('click', function(e){
      if (optionsBox && optionsBox.style.display === 'block' && !container.contains(e.target)) {
        optionsBox.style.display = 'none';
        if (arrow) arrow.style.transform = 'rotate(0deg)';
      }
    });
  }
  document.querySelectorAll('.custom-simple-select').forEach(initCustomSelect);

  const mgrBtn = document.getElementById('mgrBtn');

  function prettyScore(v) { if (v === null || v === undefined) return '-'; v = String(v).trim(); return v === '' ? '-' : v; }
  function updateSubmittedDisplay(newT1, newT2) {
    const b1 = document.getElementById('submitted-1');
    const b2 = document.getElementById('submitted-2');
    if (b1) b1.textContent = prettyScore(newT1);
    if (b2) b2.textContent = prettyScore(newT2);
  }

  if (mgrBtn) {
    mgrBtn.addEventListener('click', async function(e){
      e.preventDefault();
      const btn = this;
      const origText = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Applying...';

      const m_team1 = document.getElementById('m_team1') ? document.getElementById('m_team1').value.trim() : '';
      const m_team2 = document.getElementById('m_team2') ? document.getElementById('m_team2').value.trim() : '';
      const winner = document.getElementById('m_winner') ? document.getElementById('m_winner').value : '0';

      const fd = new FormData();
      fd.append('action', 'manager');
      fd.append('csrftokken', CSRFTOK);
      fd.append('team1score', m_team1);
      fd.append('team2score', m_team2);
      fd.append('winner', winner);

      const m_start_input = document.getElementById('m_start');
      if (m_start_input) {
        const v = (m_start_input.value || '').trim();
        if (v !== '') fd.append('match_start', v);
      }

      try {
        const resp = await fetch(API + '?tID=' + encodeURIComponent(tID) + '&mID=' + encodeURIComponent(mID), {
          method: 'POST',
          credentials: 'same-origin',
          body: fd
        });

        const text = await resp.text();
        let j = {};
        try { j = JSON.parse(text); } catch(e) { showFormMsg('Invalid JSON from server (manager)', false); console.error(e); return; }

        if (j.success) {
          if (document.getElementById('m_team1')) document.getElementById('m_team1').value = j.team1score ?? m_team1;
          if (document.getElementById('m_team2')) document.getElementById('m_team2').value = j.team2score ?? m_team2;
          updateSubmittedDisplay(j.team1score ?? m_team1 ?? '-', j.team2score ?? m_team2 ?? '-');

          if (j.winner !== undefined) {
            const w = String(j.winner);
            const b1 = document.getElementById('submitted-1');
            const b2 = document.getElementById('submitted-2');
            if (b1 && b2) {
              b1.classList.remove('submitted-winner','submitted-loser','submitted-neutral');
              b2.classList.remove('submitted-winner','submitted-loser','submitted-neutral');
              if (w === '1') { b1.classList.add('submitted-winner'); b2.classList.add('submitted-loser'); }
              else if (w === '2') { b2.classList.add('submitted-winner'); b1.classList.add('submitted-loser'); }
              else { b1.classList.add('submitted-neutral'); b2.classList.add('submitted-neutral'); }
            }
            const t1 = document.getElementById('team-1');
            const t2 = document.getElementById('team-2');
            if (t1) { const existing = t1.querySelector('.winner-header'); if (existing) existing.remove(); }
            if (t2) { const existing = t2.querySelector('.winner-header'); if (existing) existing.remove(); }
            if (w === '1' && t1) { const wh = document.createElement('div'); wh.className = 'winner-header'; wh.textContent = 'WINNER'; t1.prepend(wh); }
            else if (w === '2' && t2) { const wh = document.createElement('div'); wh.className = 'winner-header'; wh.textContent = 'WINNER'; t2.prepend(wh); }
          }

          if (j.match_start) {
            const newStartTs = parseInt(j.match_start, 10);
            if (!isNaN(newStartTs)) {
              const startEl = document.querySelector('#match-root .local-dt[data-ts]');
              if (startEl) {
                startEl.setAttribute('data-ts', newStartTs);
                startEl.textContent = new Date(newStartTs * 1000).toLocaleString(undefined, {year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit',hour12:false});
              }
              const countdownEl = document.querySelector('#status-wrap .countdown');
              if (countdownEl) countdownEl.setAttribute('data-start-ts', newStartTs);
            }
          }

          showFormMsg(j.message || 'Manager action applied', true);
        } else {
          showFormMsg(j.message || 'Manager action failed', false);
        }

      } catch (err) {
        console.error('Network error (manager):', err);
        showFormMsg('Network error (manager)', false);
      } finally {
        btn.disabled = false;
        btn.textContent = origText;
      }
    });
  }

  (function(){
    try {
      var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      document.querySelectorAll('#match-root .local-dt[data-ts]').forEach(function(el){
        var ts = Number(el.getAttribute('data-ts')) * 1000;
        if (!ts) return;
        var fmt = new Intl.DateTimeFormat(undefined, {year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit',hour12:false,timeZone:tz,timeZoneName:'short'});
        el.textContent = fmt.format(new Date(ts)).replace('GMT','UTC');
      });
    } catch(e){}
  })();


  (function(){
    function pad(n){ return String(n).padStart(2,'0'); }
    function bd(ms){ if(ms<=0) return {d:0,h:0,m:0,s:0}; var s=Math.floor(ms/1000); return {d:Math.floor(s/86400),h:Math.floor((s%86400)/3600),m:Math.floor((s%3600)/60),s:s%60}; }
    function fmt(b){ var p=[]; if(b.d) p.push(b.d+'d'); p.push(pad(b.h)+'h',pad(b.m)+'m',pad(b.s)+'s'); return p.join(' '); }
    function toMs(v){ v=Number(v)||0; return v>1e11?v:v*1000; }

    function update(){
      var now = Date.now();
      document.querySelectorAll('#status-wrap .countdown').forEach(function(wrap){
        var startMs = toMs(wrap.dataset.startTs || wrap.getAttribute('data-start-ts') || 0);
        var endMs   = toMs(wrap.dataset.endTs   || wrap.getAttribute('data-end-ts')   || 0);
        var state   = (wrap.dataset.state || wrap.getAttribute('data-state') || '').toLowerCase();

        if (!startMs) { wrap.innerHTML = "<span style='color:#777'>Not scheduled</span>"; return; }

        if (state === 'upcoming' && now < startMs) {
          wrap.innerHTML = "<span class='countdown-glow'>" + fmt(bd(startMs - now)) + "</span>";
          return;
        }
        if (state === 'finished' || (endMs > 0 && now >= endMs)) {
          wrap.innerHTML = "<span style='color:#6b7280;font-weight:700'>Finished</span>";
          return;
        }
        wrap.innerHTML = "<div class='live-pill'><span class='ongoing-dot'></span>LIVE</div>";
      });
    }
    update();
    setInterval(update, 1000);
  })();

})();
</script>

<?php
include($prevFolder . "assets/_footer.php");
?>