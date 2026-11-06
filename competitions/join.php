<?php
include("../_intro.php");
$prevFolder = "../";
$PAGE_NAME = "Join - ";
include("../assets/_header.php");

if (!isset($_SESSION['user_id']) || !defined("LOGGED_IN")) {
    http_response_code(403);
    exit("Forbidden");
}

if (!isset($_GET['tID']) || !is_numeric($_GET['tID'])) {
    include($prevFolder . "assets/_footer.php");
    exit("Invalid competition");
}

$tID = intval($_GET['tID']);
$tournamentObj = new Tournament($mysqli);
if (!$tournamentObj->select($tID)) {
    include($prevFolder . "assets/_footer.php");
    exit("competition not found");
}
$tournamentInfo = $tournamentObj->get_info();

$isClosed = intval($tournamentInfo['registration_status'] ?? 1) === 0;
$playersPerTeam = intval($tournamentInfo['playersperteam'] ?? 1);
$isTeamMode = $playersPerTeam > 1;
?>
<main id="app-root" class="container" role="main">
  <div style="max-width:760px;margin:50px auto;padding:0 16px;">
    <div style="padding:20px;border-radius:14px;border:1px solid #2a2f2c;">
      <div style="margin-bottom:18px;">
        <h2 style="margin:0 0 8px;color:#fff;font-size:26px;line-height:1.1;">
          Join Competition
        </h2>
        <div style="color:#ffeb3b;line-height:1.5;font-size:18px;font-weight:700;">
          <?php echo htmlspecialchars($tournamentInfo['name']); ?>
        </div>
        <div style="margin-top:8px;color:#7f8da3;font-size:14px;line-height:1.5;">
          <?php echo $isTeamMode
            ? 'This is a team based competition. Use an invite code to join a team, or create a new empty team and receive your own invite code.'
            : 'This is a solo competition. Joining is automatic when registration is open.'; ?>
        </div>
      </div>

      <?php if ($isClosed): ?>
        <div style="margin-bottom:12px;padding:12px 14px;border-radius:10px;font-weight:700;background:rgba(239,68,68,0.14);color:#ffb4b4;border:1px solid rgba(239,68,68,0.35);">
          Registration is closed for this competition.
        </div>
      <?php else: ?>

        <?php if ($isTeamMode): ?>
          <div style="display:flex;flex-direction:column;gap:16px;">

            <section style="padding:16px;border-radius:12px;border:1px solid #2a2f2c;background:rgba(255,255,255,0.03);">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px;">
                <div>
                  <h3 style="margin:0;color:#fff;font-size:18px;">1) Join a Team With an Invite Code</h3>
                  <div style="margin-top:6px;color:#9aa3b2;font-size:14px;line-height:1.4;">
                    Enter the code your teammate shared with you.
                  </div>
                </div>
              </div>

              <label style="display:block;margin-bottom:6px;color:#cfd8e3;font-weight:700;">Invite Code</label>
              <input id="inviteCode" type="text" maxlength="32" placeholder="Enter team invite code"
                     style="width:100%;padding:12px;border-radius:10px;border:1px solid #2e3746;background:#000;color:#fff;outline:none;box-sizing:border-box;">

				<div id="inviteMessage" style="display:none;margin-bottom:10px;margin-top:20px;padding:10px 12px;border-radius:8px;font-weight:600;"></div>

              <button id="joinInviteBtn"
                      style="width:100%;margin-top:10px;padding:12px;border-radius:10px;border:none;background:#ffeb3b;color:#000;font-weight:800;cursor:pointer;">
                Join Team
              </button>
            </section>
			
			<div style="display:flex;align-items:center;justify-content:center;">
			  <span style="color:#9aa3b2;line-height:1.5;font-size:18px;font-weight:700;">OR</span>
			</div>
            
			<section style="padding:16px;border-radius:12px;border:1px solid #2a2f2c;background:rgba(255,255,255,0.02);">
              <h3 style="margin:0;color:#fff;font-size:18px;">2) Create a New Team</h3>
              <div style="margin-top:6px;color:#9aa3b2;font-size:14px;line-height:1.4;">
                This creates a new empty team for you and generates a new invite code you can copy and send to others.
              </div>

              <div id="createMessage" style="display:none;margin-top:12px;margin-bottom:10px;padding:10px 12px;border-radius:8px;font-weight:600;"></div>

              <button id="createTeamBtn"
                      style="width:100%;margin-top:6px;padding:12px;border-radius:10px;border:none;background:#ffeb3b;color:#000;font-weight:800;cursor:pointer;">
                Create New Team
              </button>

              <div id="inviteResultWrap" style="display:none;margin-top:12px;padding:12px;border-radius:10px;border:1px solid rgba(34,197,94,0.35);background:rgba(34,197,94,0.10);">
                <div style="color:#b7f5c9;font-weight:800;margin-bottom:8px;">Your team invite code</div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                  <input id="generatedInviteCode" readonly
                         style="flex:1;min-width:220px;padding:11px;border-radius:8px;border:1px solid rgba(255,255,255,0.10);background:#0b1118;color:#fff;font-weight:800;letter-spacing:1px;">
                  <button id="copyInviteBtn"
                          style="padding:11px 14px;border-radius:8px;border:none;background:#22c55e;color:#04130a;font-weight:800;cursor:pointer;">
                    Copy
                  </button>
                </div>
                <div style="margin-top:8px;color:#cfead7;font-size:13px;line-height:1.4;">
                  Share this code with teammates so they can join your team.
                </div>
              </div>
            </section>

          </div>
        <?php else: ?>
          <section style="padding:16px;border-radius:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);">
            <div id="soloMessage" style="display:none;margin-bottom:10px;padding:10px 12px;border-radius:8px;font-weight:600;"></div>
            <button id="joinSoloBtn"
                    style="width:100%;padding:12px;border-radius:10px;border:none;background:#009688;color:#fff;font-weight:800;cursor:pointer;">
              Join Competition
            </button>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<script>
(function(){
  const CSRF_TOKEN = '<?php echo $_SESSION["csrftokken"] ?? ""; ?>';
  const tID = <?php echo $tID; ?>;
  const isTeamMode = <?php echo $isTeamMode ? 'true' : 'false'; ?>;
  const isClosed = <?php echo $isClosed ? 'true' : 'false'; ?>;

  const apiUrl = '<?php echo $MAIN_ROOT; ?>competitions/backend/join_action.php?tID=' + tID;

  const inviteBtn = document.getElementById('joinInviteBtn');
  const createBtn = document.getElementById('createTeamBtn');
  const soloBtn   = document.getElementById('joinSoloBtn');
  const inviteInp = document.getElementById('inviteCode');
  const inviteMsg = document.getElementById('inviteMessage');
  const createMsg = document.getElementById('createMessage');
  const soloMsg   = document.getElementById('soloMessage');
  const copyBtn   = document.getElementById('copyInviteBtn');
  const inviteOut = document.getElementById('generatedInviteCode');
  const inviteWrap = document.getElementById('inviteResultWrap');

  function showBox(el, text, ok) {
    if (!el) return;
    el.style.display = 'block';
    el.style.color = ok ? '#b8f5c1' : '#ffb4b4';
    el.style.background = ok ? 'rgba(34,197,94,0.10)' : 'rgba(239,68,68,0.14)';
    el.style.border = ok ? '1px solid rgba(34,197,94,0.35)' : '1px solid rgba(239,68,68,0.35)';
    el.innerText = text;
  }

  function hideBox(el) {
    if (!el) return;
    el.style.display = 'none';
    el.innerText = '';
  }

  async function postJoin(action, code = '') {
    const resp = await fetch(apiUrl, {
      method: 'POST',
      headers: {'Content-Type':'application/json','Accept':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({
        csrf_token: CSRF_TOKEN,
        action: action,
        code: code
      })
    });
    return await resp.json();
  }

  if (isClosed) return;

  if (inviteBtn) {
    inviteBtn.addEventListener('click', async function(e){
      e.preventDefault();
      hideBox(inviteMsg);

      const code = (inviteInp.value || '').trim();
      if (!code) {
        showBox(inviteMsg, 'Please enter an invite code.', false);
        return;
      }

      inviteBtn.disabled = true;
      inviteBtn.innerText = 'Joining...';

      try {
        const data = await postJoin('join_invite', code);
        if (data.success) {
          showBox(inviteMsg, data.message || 'Joined the team successfully.', true);
          inviteInp.value = '';
        } else {
          showBox(inviteMsg, data.message || 'Could not join the team.', false);
        }
      } catch (err) {
        showBox(inviteMsg, 'Network error. Please try again.', false);
      } finally {
        inviteBtn.disabled = false;
        inviteBtn.innerText = 'Join Team';
      }
    });
  }

  if (createBtn) {
    createBtn.addEventListener('click', async function(e){
      e.preventDefault();
      hideBox(createMsg);
      if (inviteWrap) inviteWrap.style.display = 'none';

      createBtn.disabled = true;
      createBtn.innerText = 'Creating...';

      try {
        const data = await postJoin('new_team');
        if (data.success) {
          showBox(createMsg, data.message || 'New team created successfully.', true);

          if (data.invite_code) {
            inviteOut.value = data.invite_code;
            inviteWrap.style.display = 'block';
            inviteOut.focus();
            inviteOut.select();
          }
        } else {
          showBox(createMsg, data.message || 'Could not create a team.', false);
        }
      } catch (err) {
        showBox(createMsg, 'Network error. Please try again.', false);
      } finally {
        createBtn.disabled = false;
        createBtn.innerText = 'Create New Team';
      }
    });
  }

  if (copyBtn && inviteOut) {
    copyBtn.addEventListener('click', async function(){
      try {
        await navigator.clipboard.writeText(inviteOut.value || '');
        copyBtn.innerText = 'Copied';
        setTimeout(() => copyBtn.innerText = 'Copy', 1200);
      } catch (e) {
        inviteOut.select();
        document.execCommand('copy');
        copyBtn.innerText = 'Copied';
        setTimeout(() => copyBtn.innerText = 'Copy', 1200);
      }
    });
  }

  if (soloBtn) {
    soloBtn.addEventListener('click', async function(e){
      e.preventDefault();
      hideBox(soloMsg);

      soloBtn.disabled = true;
      soloBtn.innerText = 'Joining...';

      try {
        const data = await postJoin('solo');
        if (data.success) {
          showBox(soloMsg, data.message || 'You joined the tournament.', true);
        } else {
          showBox(soloMsg, data.message || 'Could not join.', false);
        }
      } catch (err) {
        showBox(soloMsg, 'Network error. Please try again.', false);
      } finally {
        soloBtn.disabled = false;
        soloBtn.innerText = 'Join Tournament';
      }
    });
  }
})();
</script>

<?php include($prevFolder . "assets/_footer.php"); ?>