<?php
$prevFolder = "";
include("_intro.php");
if (session_status() === PHP_SESSION_NONE) session_start();
$PAGE_NAME = "Authenticate - ";
include("assets/_header.php");

if(isset($_SESSION['user_id'])) {
    $result = $mysqli->query("SELECT member_id FROM members WHERE member_id = '".$_SESSION['user_id']."'");
    if($result && $result->num_rows > 0) {
        echo "<script>window.location = '".$MAIN_ROOT."'</script>";
        exit();
    }
}

if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('MAIN_ROOT')) {
    // best-effort fallback
    $MAIN_ROOT = $MAIN_ROOT ?? '/';
}

$SITE_ROOT = rtrim($MAIN_ROOT, '/') . '/';
$CSRF_TOKEN = $_SESSION['csrftokken'] ?? $_SESSION['csrf_token'] ?? '';
?>

<style>
#auth-core-root .auth-tabs{display:flex;margin-bottom:15px;border-bottom:1px solid #2a2f2c}
#auth-core-root .auth-tab{flex:1;padding:12px;background:none;border:none;color:#fff;font-weight:700;cursor:pointer}
#auth-core-root .auth-tab.active{border-bottom:3px solid #ffeb3b;color:#ffeb3b;font-weight:900}
#auth-core-root .pwwrap{position:relative}
#auth-core-root .pwwrap .eye-icon{width:32px;position:absolute;right:6px;top:6px;cursor:pointer}
#auth-core-root .error-message{display:block;margin-top:6px}
#auth-core-root #auth-message{display:none;font-weight:600;padding:10px;border-radius:8px}
#auth-core-root .form-row{display:flex;justify-content:space-between;align-items:center}
#auth-core-root .password-checklist div{margin:4px 0;color:#666}
</style>

<main id="app-root" class="container" role="main" tabindex="-1">
	<div id="auth-core-root" style="width:100%;">
	  <div class="form-section">
		<div class="form-section-form">
		  <div class="auth-tabs" role="tablist">
			<button class="auth-tab active" data-tab="login" type="button">Log In</button>
			<button class="auth-tab" data-tab="signup" type="button">Sign Up</button>
		  </div>

		  <div id="auth-message" style="margin:18px 0;display:none;font-weight:600;padding:10px;border-radius:8px;"></div>

		  <section id="login-form" class="auth-form active" aria-hidden="false">
			<div class="input-group">
			  <label for="login-username" class="input-group-label">Username</label>
			  <input id="login-username" class="input-group-input" autocomplete="username email" />
			  <span id="login-username-error" class="error-message"></span>
			</div>

			<div class="input-group">
			  <label class="input-group-label" for="login-password">Password</label>
			  <div class="pwwrap">
				<input id="login-password" type="password" class="input-group-input" style="padding:12px 44px 12px 12px;" />
				<img src="<?php echo htmlspecialchars($SITE_ROOT); ?>assets/images/passwordshow.png" class="eye-icon" data-target="login-password" alt="toggle password" />
			  </div>
			  <span id="login-password-error" class="error-message"></span>
			</div>

			<div class="form-row small-gap" style="margin-bottom:18px;">
			  <label class="checkbox-inline"><input id="rememberMe" type="checkbox" checked> Remember me</label>
			  
			</div>

			<div><button id="login-btn" class="btn" type="button" style="width:100%;">LOG IN</button></div>
		  </section>

		  <section id="signup-form" class="auth-form" aria-hidden="true" style="display:none;">
			<div class="input-group">
			  <label class="input-group-label" for="signup-username">Username</label>
			  <input id="signup-username" class="input-group-input" />
			  <span id="signup-username-error" class="error-message"></span>
			</div>

            <div style="font-size:13px;color:#bfc7cc;margin:6px 0 12px;">
              Email is optional — you can add it later in your account settings to secure your account and enable password recovery.
            </div>

			<div class="input-group">
			  <label class="input-group-label" for="signup-password">Password</label>
			  <div class="pwwrap">
				<input id="signup-password" type="password" class="input-group-input" style="padding:12px 44px 12px 12px;" />
				<img src="<?php echo htmlspecialchars($SITE_ROOT); ?>assets/images/passwordshow.png" class="eye-icon" data-target="signup-password" alt="toggle password" />
			  </div>
			  <span id="signup-password-error" class="error-message"></span>
			  <div id="password-checklist" class="password-checklist" style="margin-top:10px; font-size:12px; display:none;"></div>
			</div>

			<div style="margin-top:12px;"><button id="signup-btn" class="btn" type="button" style="width:100%;">SIGN UP</button></div>
		  </section>

		</div>
	  </div>
	</div>
</main>

<script>
(function () {
  const root = document.getElementById('auth-core-root');
  if (!root) return;
  if (root.dataset.authCoreInit === '1') return;
  root.dataset.authCoreInit = '1';

  const SITE_ROOT = <?php echo json_encode($SITE_ROOT); ?>;
  const CSRF_TOKEN = <?php echo json_encode($CSRF_TOKEN); ?>;
  const LOGIN_URL = SITE_ROOT + 'backend/login.php';
  const SIGNUP_URL = SITE_ROOT + 'backend/signup.php';

  const $ = id => document.getElementById(id);
  const msg = $('auth-message');

  function showBanner(text, ok = true) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.style.color = ok ? '#b8f5c1' : '#bd0d00';
    msg.style.background = ok ? 'rgba(40,110,40,0.12)' : 'rgb(245 172 172)';
    msg.innerText = text;
  }
  function hideBanner() { if (msg) { msg.style.display = 'none'; msg.innerText = ''; } }

  function clearField(id) {
    const e = $(id + '-error'); if (e) e.innerHTML = '';
    const i = $(id); if (i) i.style.borderColor = '#2a2f2c';
  }
  function setFieldError(id, text) {
    const e = $(id + '-error'); if (e) e.innerHTML = `<p style="color:#ff6b6b;font-size:12px">${text}</p>`;
    const i = $(id); if (i) i.style.borderColor = '#ff6b6b';
  }
  function applyFieldErrors(errors) {
    if (!errors) return;
    Object.keys(errors).forEach(k => setFieldError(k, errors[k]));
  }

  function switchTab(tab) {
    Array.from(root.querySelectorAll('.auth-tab')).forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    $('login-form').style.display = tab === 'login' ? 'block' : 'none';
    $('signup-form').style.display = tab === 'signup' ? 'block' : 'none';
    hideBanner();
    ['login-username','login-password','signup-username','signup-password'].forEach(clearField);
    if (tab === 'signup') {
      const pc = $('password-checklist');
      if (pc) pc.style.display = 'block';
    }
  }

  root.querySelectorAll('input').forEach(input => {
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const loginVisible = $('login-form').style.display !== 'none';
        loginVisible ? $('login-btn').click() : $('signup-btn').click();
      }
    });
  });

  root.querySelectorAll('.eye-icon').forEach(icon => icon.addEventListener('click', function () {
    const t = document.getElementById(this.dataset.target);
    if (!t) return;
    if (t.type === 'password') { t.type = 'text'; this.src = SITE_ROOT + 'assets/images/passwordhide.png'; }
    else { t.type = 'password'; this.src = SITE_ROOT + 'assets/images/passwordshow.png'; }
  }));

  function initChecklist() {
    const pc = $('password-checklist');
    if (!pc) return;
    if (!pc.innerHTML.trim()) {
      const items = [
        {id:'length', text:'At least 10 characters'},
        {id:'uppercase', text:'Uppercase letter (A-Z)'},
        {id:'lowercase', text:'Lowercase letter (a-z)'},
        {id:'number', text:'Number (0-9)'},
        {id:'symbol', text:'Symbol (!@#$ etc.)'}
      ];
      pc.innerHTML = items.map(it => `<div id="req-${it.id}"><span class="req-icon" style="margin-right:6px">✗</span>${it.text}</div>`).join('');
    }
  }
  initChecklist();
  const signupPassword = $('signup-password');
  if (signupPassword) {
    signupPassword.addEventListener('input', function () {
      const v = this.value;
      const rules = {
        length: v.length >= 10,
        uppercase: /[A-Z]/.test(v),
        lowercase: /[a-z]/.test(v),
        number: /[0-9]/.test(v),
        symbol: /[!@#$%^&*()\-_=+{};:,<.>]/.test(v)
      };
      Object.keys(rules).forEach(k => {
        const el = $('req-' + k);
        if (!el) return;
        const ok = rules[k];
        el.querySelector('.req-icon').textContent = ok ? '✓' : '✗';
        el.style.color = ok ? '#4CAF50' : '#666';
        el.querySelector('.req-icon').style.color = ok ? '#4CAF50' : '#F44336';
      });
    });
  }

  async function postJson(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    return res.json();
  }

  $('login-btn').addEventListener('click', async function () {
    ['login-username','login-password'].forEach(clearField); hideBanner();
    const user = ($('login-username').value || '').trim();
    const pass = $('login-password').value || '';
    const remember = $('rememberMe').checked ? 1 : 0;
    if (!user) { setFieldError('login-username', 'Please enter your username or email.'); return; }
    if (!pass) { setFieldError('login-password', 'Please enter your password.'); return; }

    const btn = this; btn.disabled = true; const prev = btn.textContent; btn.textContent = 'Logging in...';
    try {
      
      const data = await postJson(LOGIN_URL, { user, pass, rememberme: remember, csrf_token: CSRF_TOKEN });
      if (data.status === 'success') { window.location.href = data.redirect || SITE_ROOT; return; }
      if (data.status === 'verify')  { window.location.href = data.redirect || (SITE_ROOT + 'verify.php'); return; }
      
      if (data.globalError) { 
          showBanner(data.globalError, false); 
      } else if (data.fieldErrors && Object.keys(data.fieldErrors).length > 0) { 
          applyFieldErrors(data.fieldErrors); 
      } else if (data.message) { 
          showBanner(data.message, data.status === 'success'); 
      }
      
    } catch (err) {
      console.error('Login error', err); showBanner('Network error. Please try again.', false);
    } finally { btn.disabled = false; btn.textContent = prev; }
  });

  $('signup-btn').addEventListener('click', async function () {
    ['signup-username','signup-password'].forEach(clearField); hideBanner();
    const username = ($('signup-username').value || '').trim();
    const password = $('signup-password').value || '';

    if (!username) { setFieldError('signup-username', 'Please enter a username.'); return; }
    if ([...username].length < 2 || [...username].length > 30) { setFieldError('signup-username', 'Username must be between 2 and 30 characters.'); return; }
	if (/^\s|\s$/.test(username)) { setFieldError('signup-username', 'Username cannot start or end with a space.'); return; }
    if (!password) { setFieldError('signup-password', 'Please enter a password.'); return; }
    if (password.length < 10) { setFieldError('signup-password', 'Password must be at least 10 characters long.'); return; }

    const btn = this; btn.disabled = true; const prev = btn.textContent; btn.textContent = 'Signing Up...';
    try {
      const data = await postJson(SIGNUP_URL, { username, password, csrf_token: CSRF_TOKEN });
      if (data.status === 'verify') { window.location.href = data.redirect || (SITE_ROOT + 'verify.php'); return; }
      if (data.status === 'success') { window.location.href = data.redirect || (SITE_ROOT + 'login.php'); return; }
      if (data.fieldErrors) applyFieldErrors(data.fieldErrors);
      else if (data.globalError) showBanner(data.globalError, false);
      else if (data.message) showBanner(data.message, data.status === 'success');
    } catch (err) {
      console.error('Signup error', err); showBanner('Network error. Please try again.', false);
    } finally { btn.disabled = false; btn.textContent = prev; }
  });

  Array.from(root.querySelectorAll('.auth-tab')).forEach(btn => {
    btn.removeEventListener('click', btn._authClick || (() => {}));
    const handler = function () { switchTab(this.dataset.tab || 'login'); };
    btn.addEventListener('click', handler);
    btn._authClick = handler;
  });

})();
</script>


<?php
include("assets/_footer.php");
?>
