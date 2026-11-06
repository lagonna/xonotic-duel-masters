<?php

include_once("_intro.php");

$prevFolder = "";

$PAGE_NAME = "Settings - ";

include("assets/_header.php");

if(!isset($_SESSION['user_id'])) {
	echo "<script>window.location = '".$MAIN_ROOT."'</script>";
	exit();
}

$stmt = $mysqli->prepare("SELECT profilepic, username, username_style, bio, country, facebook, twitch, youtube, email FROM {$dbprefix}members WHERE member_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows == 0) {
	echo "<script>window.location = '".$MAIN_ROOT."'</script>";
	exit();
}
$row = $result->fetch_assoc();
$stmt->close();

$initialUsernameColor = '#00FF41';
$decodedUsernameStyle = json_decode($row['username_style'] ?? '', true);
if (is_array($decodedUsernameStyle) && !empty($decodedUsernameStyle)) {
    $lastRun = end($decodedUsernameStyle);
    if (is_array($lastRun) && !empty($lastRun['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $lastRun['color'])) {
        $initialUsernameColor = strtolower($lastRun['color']);
    }
}

?>

<main id="app-root" class="container" role="main" tabindex="-1">

<script>
(function () {
    const CSRF = <?php echo json_encode($_SESSION['csrftokken'] ?? $_SESSION['csrf_token'] ?? ''); ?>;
    const SITE_ROOT = <?php echo json_encode(rtrim($MAIN_ROOT, '/') . '/'); ?>;
    
    const INITIAL_USERNAME_COLOR = <?php echo json_encode($initialUsernameColor); ?>;
    
    const PROFILE_API = SITE_ROOT + 'backend/profile.php';

    const SERVER_MAX_BYTES = <?php
        $ini = ini_get('upload_max_filesize') ?: '2M';
        $limitMB = (int)filter_var($ini, FILTER_SANITIZE_NUMBER_INT);
        if ($limitMB <= 0) $limitMB = 2;
        echo ($limitMB * 1048576);
    ?>;

    const MIN_DIM = 16;
    const MAX_DIM = 8000;

    const ALLOWED_MIMES = ['image/gif', 'image/jpeg', 'image/png'];

    function el(id){ return document.getElementById(id); }
    function qs(sel){ try { return document.querySelector(sel); } catch(e) { return null; } }
    function notify(msg, type){ if (typeof showNotification === 'function') showNotification(msg, type); else console.log((type||'info')+': '+msg); }

    async function safeFetchJSON(url, opts = {}) {
        opts.credentials = opts.credentials || 'same-origin';
        const res = await fetch(url, opts);
        return await res.json();
    }

    function clearField(id) {
        if(!id) return;
        const e1 = el(id + 'Error');
        const e2 = el(id + '-error');
        if (e1) e1.innerHTML = '';
        if (e2) e2.innerHTML = '';
        const i = el(id);
        if (i) i.style.borderColor = '#52545ba1';
    }
    function setFieldError(id, text) {
        if(!id) return;
        const e1 = el(id + 'Error');
        const e2 = el(id + '-error');
        const errorHtml = `<p style="color:#ff6b6b;font-size:12px">${text}</p>`;
        if (e1) e1.innerHTML = errorHtml;
        else if (e2) e2.innerHTML = errorHtml;
        else {
            const input = el(id);
            if (input && !el(id + '__auto_err')) {
                const span = document.createElement('div');
                span.id = id + '__auto_err';
                span.innerHTML = errorHtml;
                span.style.marginTop = '4px';
                input.parentNode && input.parentNode.appendChild(span);
            }
        }
        const i = el(id);
        if (i) i.style.borderColor = '#c15755';
    }
    function applyFieldErrors(errors) {
        if (!errors) return;
        Object.keys(errors).forEach(k => setFieldError(k, errors[k]));
    }

    function validateImageFile(file) {
        return new Promise((resolve, reject) => {
            if (!file) return reject('No file selected.');
            if (!(file instanceof File)) return reject('Invalid file object.');

            const mime = file.type || '';
            if (!mime.startsWith('image/')) {
                return reject('File must be an image.');
            }
            if (!ALLOWED_MIMES.includes(mime)) {
                return reject('Only GIF, JPEG, or PNG images allowed.');
            }

            if (file.size > SERVER_MAX_BYTES) {
                const mb = Math.round(SERVER_MAX_BYTES / 1048576);
                return reject('File too large (max ' + mb + ' MB).');
            }

            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = function() {
                const w = img.naturalWidth || img.width;
                const h = img.naturalHeight || img.height;
                URL.revokeObjectURL(url);
                if (typeof w !== 'number' || typeof h !== 'number') return reject('Unable to determine image dimensions.');
                if (w < MIN_DIM || h < MIN_DIM) return reject('Avatar too small (min ' + MIN_DIM + 'x' + MIN_DIM + ').');
                if (w > MAX_DIM || h > MAX_DIM) return reject('Image dimensions too large (max ' + MAX_DIM + 'x' + MAX_DIM + ').');
                resolve({ width: w, height: h, mime: mime, size: file.size });
            };
            img.onerror = function() {
                URL.revokeObjectURL(url);
                reject('Uploaded file is not a valid image.');
            };
            img.src = url;
        });
    }

    async function postProfileJSON(payload) {
        const res = await safeFetchJSON(PROFILE_API, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        return res;
    }

    function initializeAll() {
        const fileInput = el('fileUpload');
        const avatarPreview = el('avatarPreview');
        const previewBtnWrap = el('avatar-preview-button');
        const changeAvatarBtn = el('changeAvatar');

        if (fileInput && avatarPreview) {
            fileInput.addEventListener('change', function(){
                const file = this.files && this.files[0];
                if (!file) { 
                    if (previewBtnWrap) previewBtnWrap.style.display='none'; 
                    return; 
                }

                if (!file.type || !file.type.startsWith('image/')) {
                    notify('Selected file is not an image.', 'error');
                    fileInput.value = '';
                    if (previewBtnWrap) previewBtnWrap.style.display='none';
                    return;
                }
                if (file.size > SERVER_MAX_BYTES) {
                    const mb = Math.round(SERVER_MAX_BYTES / 1048576);
                    notify('File too large (max ' + mb + ' MB).', 'error');
                    fileInput.value = '';
                    if (previewBtnWrap) previewBtnWrap.style.display='none';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e){ avatarPreview.src = e.target.result; };
                reader.readAsDataURL(file);
                if (previewBtnWrap) previewBtnWrap.style.display = 'flex';
            });
        }

        if (changeAvatarBtn) {
            changeAvatarBtn.addEventListener('click', async function(){
                const selectedFile = fileInput && fileInput.files && fileInput.files[0];
                if (!selectedFile) { 
                    notify('Please select an image first!', 'error'); 
                    return; 
                }

                clearField('fileUpload');
                try {
                    changeAvatarBtn.disabled = true;
                    const btnSpan = changeAvatarBtn.querySelector('.button-text');
                    if (btnSpan) btnSpan.textContent = 'VALIDATING...';
                    await validateImageFile(selectedFile);
                } catch (errMsg) {
                    notify(errMsg || 'Image validation failed.', 'error');
                    changeAvatarBtn.disabled = false;
                    const btnSpan2 = changeAvatarBtn.querySelector('.button-text');
                    if (btnSpan2) btnSpan2.textContent = 'UPDATE';
                    return;
                }

                const fd = new FormData();
                fd.append('image', selectedFile);
                fd.append('csrf_token', CSRF);
                fd.append('action', 'avatar');

                changeAvatarBtn.disabled = true;
                const btnSpan = changeAvatarBtn.querySelector('.button-text');
                if (btnSpan) btnSpan.textContent = 'UPLOADING...';

                try {
                    const res = await fetch(PROFILE_API, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd
                    });
                    const text = await res.text();
                    let data = {};
                    try { data = text ? JSON.parse(text) : {}; } catch(e) { throw new Error('Invalid JSON from server: ' + text); }
                    if (!res.ok) {
                        const err = new Error(data.globalError || data.message || ('HTTP ' + res.status));
                        err.serverData = data;
                        throw err;
                    }

                    if (data.status === 'success') {
                        notify(data.message || data.msg || 'Avatar updated', 'success');
                        if (data.new_profilepic) avatarPreview.src = data.new_profilepic;
                        else if (data.thumb) avatarPreview.src = data.thumb.startsWith('http') ? data.thumb : (SITE_ROOT + data.thumb);
                    } else {
                        if (data.fieldErrors) applyFieldErrors(data.fieldErrors);
                        else if (data.globalError) notify(data.globalError, 'error');
                        else notify(data.message || 'Error uploading avatar', 'error');
                    }
                } catch (err) {
                    console.error('Avatar upload error', err);
                    const msg = (err && err.serverData && err.serverData.globalError) ? err.serverData.globalError : err.message || 'Network error';
                    notify(msg, 'error');
                } finally {
                    changeAvatarBtn.disabled = false;
                    if (btnSpan) btnSpan.textContent = 'UPDATE';
                }
            });
        }

		const editUsernameBtn = el('editUsername');
		const usernameEditor = el('usernameEditor');
		const usernameHidden = el('username');
		const usernameStyleHidden = el('usernameStyle');

		const colorBox = el('usernameColorBox');
		const hueSlider = el('usernameHue');
		const colorPreview = el('usernameColorPreview');
		const hexInput = el('usernameHex');
		const pickerDot = el('usernamePickerDot');

        let currentHue = 0;
		let currentColor = INITIAL_USERNAME_COLOR;

		function rgbToHex(r, g, b) {
			return '#' + [r, g, b].map(x => x.toString(16).padStart(2, '0')).join('');
		}

		function hexToRgb(hex) {
			hex = (hex || '').trim();
			if (!/^#[0-9a-fA-F]{6}$/.test(hex)) return null;
			return {
				r: parseInt(hex.slice(1, 3), 16),
				g: parseInt(hex.slice(3, 5), 16),
				b: parseInt(hex.slice(5, 7), 16)
			};
		}

		function updateCurrentColor(hex) {
			if (!hexInput) return false;

			let value = (hex || '').trim();
			if (!value) return false;

			if (!value.startsWith('#')) value = '#' + value;
			if (!/^#[0-9a-fA-F]{6}$/.test(value)) return false;

			value = value.toLowerCase();
			currentColor = value;

			if (hexInput.value !== value) {
				hexInput.value = value;
			}

			if (colorPreview) {
				colorPreview.style.background = value;
			}

			if (pickerDot) {
				pickerDot.style.background = value;
			}

			const point = findClosestPointInBox(value);
			if (point) {
				const rect = colorBox.getBoundingClientRect();
				movePickerDot(
					(point.x / colorBox.width) * rect.width,
					(point.y / colorBox.height) * rect.height
				);
			}

			return true;
		}
		
		let cachedPixelData = null;

		function drawColorBox() {
			const ctx = colorBox.getContext('2d');
			const w = colorBox.width;
			const h = colorBox.height;

			const hue = ctx.createLinearGradient(0, 0, w, 0);
			hue.addColorStop(0.00, 'rgb(255,0,0)');
			hue.addColorStop(0.17, 'rgb(255,255,0)');
			hue.addColorStop(0.33, 'rgb(0,255,0)');
			hue.addColorStop(0.50, 'rgb(0,255,255)');
			hue.addColorStop(0.67, 'rgb(0,0,255)');
			hue.addColorStop(0.83, 'rgb(255,0,255)');
			hue.addColorStop(1.00, 'rgb(255,0,0)');

			ctx.clearRect(0, 0, w, h);
			ctx.fillStyle = hue;
			ctx.fillRect(0, 0, w, h);

			const white = ctx.createLinearGradient(0, 0, 0, h);
			white.addColorStop(0, 'rgba(255,255,255,1)');
			white.addColorStop(0.45, 'rgba(255,255,255,0.15)');
			white.addColorStop(1, 'rgba(255,255,255,0)');
			ctx.fillStyle = white;
			ctx.fillRect(0, 0, w, h);

			const black = ctx.createLinearGradient(0, h, 0, 0);
			black.addColorStop(0, 'rgba(0,0,0,1)');
			black.addColorStop(0.55, 'rgba(0,0,0,0.10)');
			black.addColorStop(1, 'rgba(0,0,0,0)');
			ctx.fillStyle = black;
			ctx.fillRect(0, 0, w, h);

			cachedPixelData = ctx.getImageData(0, 0, w, h).data;
		}

		function movePickerDot(x, y) {
			if (!pickerDot) return;
			pickerDot.style.left = x + 'px';
			pickerDot.style.top = y + 'px';
		}

		function pickColorFromBox(evt) {
			const rect = colorBox.getBoundingClientRect();
			const x = Math.max(0, Math.min(colorBox.width - 1, Math.round((evt.clientX - rect.left) * (colorBox.width / rect.width))));
			const y = Math.max(0, Math.min(colorBox.height - 1, Math.round((evt.clientY - rect.top) * (colorBox.height / rect.height))));

			const ctx = colorBox.getContext('2d');
			const data = ctx.getImageData(x, y, 1, 1).data;
			const hex = rgbToHex(data[0], data[1], data[2]);

			updateCurrentColor(hex);
			movePickerDot((x / colorBox.width) * rect.width, (y / colorBox.height) * rect.height);
		}

		function findClosestPointInBox(targetHex) {
			const rgb = hexToRgb(targetHex);
			if (!rgb) return null;

			const w = colorBox.width;
			const h = colorBox.height;

			const img = cachedPixelData || colorBox.getContext('2d').getImageData(0, 0, w, h).data;

			let bestIndex = 0;
			let bestDist = Infinity;

			for (let i = 0; i < img.length; i += 4) {
				const dr = img[i]     - rgb.r;
				const dg = img[i + 1] - rgb.g;
				const db = img[i + 2] - rgb.b;
				const dist = dr * dr + dg * dg + db * db;
				if (dist < bestDist) {
					bestDist = dist;
					bestIndex = i;
				}
			}

			const pixelIndex = bestIndex / 4;
			return {
				x: pixelIndex % w,
				y: Math.floor(pixelIndex / w),
				hex: rgbToHex(img[bestIndex], img[bestIndex + 1], img[bestIndex + 2])
			};
		}

		function getEditorText() {
			return usernameEditor ? usernameEditor.innerText.replace(/\n/g, '') : '';
		}

		function syncHiddenFields() {
			const plain = (usernameEditor.textContent || '').replace(/\n/g, '');
			usernameHidden.value = plain;

			const runs = [];
			const walker = document.createTreeWalker(
				usernameEditor,
				NodeFilter.SHOW_TEXT,
				null
			);

			let node;
			while ((node = walker.nextNode())) {
				const text = node.nodeValue || '';
				if (!text) continue;

				const parent = node.parentElement;
				const color =
					parent && parent.dataset && parent.dataset.color
						? parent.dataset.color
						: currentColor;

				const last = runs[runs.length - 1];
				if (last && last.color === color) {
					last.text += text;
				} else {
					runs.push({ text, color });
				}
			}

			usernameStyleHidden.value = JSON.stringify(runs);
		}

		function getCaretRange() {
			const sel = window.getSelection();
			if (!sel || sel.rangeCount === 0) return null;
			return sel.getRangeAt(0);
		}

		function setCaretAfter(node) {
			const sel = window.getSelection();
			const range = document.createRange();
			range.setStartAfter(node);
			range.collapse(true);
			sel.removeAllRanges();
			sel.addRange(range);
		}

		function insertColoredText(text) {
			const range = getCaretRange();
			if (!range) return;

			range.deleteContents();

			const span = document.createElement('span');
			span.dataset.color = currentColor;
			span.style.color = currentColor;
			span.textContent = text;

			range.insertNode(span);
			setCaretAfter(span);
			syncHiddenFields();
		}

		function deleteBackward() {
			const sel = window.getSelection();
			if (!sel || sel.rangeCount === 0) return;

			const range = sel.getRangeAt(0);
			if (!range.collapsed) {
				range.deleteContents();
				syncHiddenFields();
				return;
			}

			const container = range.startContainer;
			const offset = range.startOffset;

			if (container.nodeType === Node.TEXT_NODE) {
				if (offset > 0) {
					container.deleteData(offset - 1, 1);
					syncHiddenFields();
				} else {
					const prev = container.previousSibling;
					if (prev) prev.remove();
					syncHiddenFields();
				}
			} else if (container.nodeType === Node.ELEMENT_NODE) {
				const child = container.childNodes[offset - 1];
				if (child) child.remove();
				syncHiddenFields();
			}
		}

		function normalizeEditorIfNeeded() {
			const nodes = Array.from(usernameEditor.childNodes);
			for (const node of nodes) {
				if (node.nodeType === Node.TEXT_NODE && node.nodeValue) {
					const span = document.createElement('span');
					span.dataset.color = currentColor;
					span.style.color = currentColor;
					span.textContent = node.nodeValue;
					node.replaceWith(span);
				}
			}
			syncHiddenFields();
		}

		function wrapInitialText() {
			const text = getEditorText();
			usernameEditor.innerHTML = '';
			if (text.length) {
				const span = document.createElement('span');
				span.dataset.color = currentColor;
				span.style.color = currentColor;
				span.textContent = text;
				usernameEditor.appendChild(span);
			}
			syncHiddenFields();
		}

		if (usernameEditor && colorBox && hexInput) {
			syncHiddenFields(); 
    		drawColorBox();
        	updateCurrentColor(currentColor);

			const initialPoint = findClosestPointInBox(currentColor);
			if (initialPoint) {
				const rect = colorBox.getBoundingClientRect();
				movePickerDot(
					(initialPoint.x / colorBox.width) * rect.width,
					(initialPoint.y / colorBox.height) * rect.height
				);
			}

			colorBox.addEventListener('mousedown', function(e) {
				pickColorFromBox(e);
				const move = (ev) => pickColorFromBox(ev);
				const up = () => {
					document.removeEventListener('mousemove', move);
					document.removeEventListener('mouseup', up);
				};
				document.addEventListener('mousemove', move);
				document.addEventListener('mouseup', up);
			});

			hexInput.addEventListener('input', function() {
				updateCurrentColor(this.value);
			});

			hexInput.addEventListener('change', function() {
				updateCurrentColor(this.value);
			});

			hexInput.addEventListener('blur', function() {
				updateCurrentColor(this.value);
			});

			hexInput.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					updateCurrentColor(this.value);
					this.blur();
				}
			});
			usernameEditor.addEventListener('beforeinput', function(e) {
				const type = e.inputType || '';

				if (type === 'insertText') {
					e.preventDefault();
					insertColoredText(e.data || '');
				} else if (type === 'insertFromPaste') {
					e.preventDefault();
					const text = (e.clipboardData || window.clipboardData).getData('text') || '';
					insertColoredText(text);
				} else if (type === 'deleteContentBackward') {
					e.preventDefault();
					deleteBackward();
				} else if (type === 'insertParagraph') {
					e.preventDefault();
				}
			});

			usernameEditor.addEventListener('input', function() {
				syncHiddenFields();
			});

			usernameEditor.addEventListener('blur', function() {
				syncHiddenFields();
			});

			if (editUsernameBtn) {
				editUsernameBtn.addEventListener('click', async function() {
					clearField('username');

					const username = getEditorText().trim();
					const usernameStyle = usernameStyleHidden.value || '[]';

					let errorMsg = '';
					if (!username || username.length < 2) {
						errorMsg = 'Username must be at least 2 characters long.';
					} else if (username.length > 30) {
						errorMsg = 'Username must be 30 characters or less.';
					} else if (/[\x00-\x1F\x7F]/.test(username)) {
						errorMsg = 'Username cannot contain control characters.';
					}

					if (errorMsg) {
						setFieldError('username', errorMsg);
						return;
					}

					try {
						editUsernameBtn.disabled = true;
						editUsernameBtn.textContent = 'UPDATING...';

						const data = await postProfileJSON({
							action: 'username',
							username,
							username_style: usernameStyle,
							csrf_token: CSRF
						});

						if (data.status === 'success') {
							notify(data.message || 'Username updated', 'success');
						} else {
							if (data.fieldErrors) applyFieldErrors(data.fieldErrors);
							else if (data.globalError) notify(data.globalError, 'error');
							else notify(data.message || 'Error updating username', 'error');
						}
					} catch (err) {
						console.error('Username update error', err);
						const msg = (err && err.serverData && err.serverData.globalError) ? err.serverData.globalError : err.message || 'Network error';
						notify(msg, 'error');
					} finally {
						editUsernameBtn.disabled = false;
						editUsernameBtn.textContent = 'UPDATE';
					}
				});
			}
		}

        const saveEmailBtn = el('saveEmail');
        const emailInput = el('emailInput');

        if (saveEmailBtn && emailInput) {
            saveEmailBtn.addEventListener('click', async function (e) {
                e.preventDefault();

                clearField('emailInput');
                const emailVal = emailInput.value.trim();

                const originalText = saveEmailBtn.innerHTML;
                saveEmailBtn.disabled = true;
                saveEmailBtn.innerHTML = 'SAVING...';

                const data = await postProfileJSON({
                    action: 'email',
                    email: emailVal,
                    csrf_token: CSRF
                });

                saveEmailBtn.disabled = false;
                saveEmailBtn.innerHTML = originalText;

                if (data.status === 'error') {
                    if (data.globalError) notify(data.globalError, 'error');
                    if (data.fieldErrors) applyFieldErrors(data.fieldErrors);
                    return;
                }

                notify(data.message || 'Email updated successfully!', 'success');
            });
        }

        const saveSocialsBtn = el('saveSocials');
        if (saveSocialsBtn) {
            saveSocialsBtn.addEventListener('click', async function(){
                ['facebook','twitch','youtube'].forEach(clearField);
                const facebook = (el('facebook') && el('facebook').value || '').trim();
                const twitch = (el('twitch') && el('twitch').value || '').trim();
                const youtube = (el('youtube') && el('youtube').value || '').trim();

                function extractHandle(input) {
                    const s = input.trim();
                    if (s === '') return null;
                    if (s.startsWith('http') || s.startsWith('www.')) {
                        try {
                            const url = new URL(s.startsWith('http') ? s : 'https://' + s);
                            const path = url.pathname.replace(/^\/+|\/+$/g, '');
                            if (path === '') return null;
                            const parts = path.split('/');
                            return parts[parts.length - 1];
                        } catch (e) {
                            return null;
                        }
                    }
                    return s.startsWith('@') ? s.substring(1) : s;
                }

                function validFacebook(h) { if (h === null) return true; return /^[A-Za-z0-9.]{3,100}$/.test(h); }
                function validTwitch(h) { if (h === null) return true; return /^[a-z0-9_]{4,25}$/i.test(h); }
                function validYoutube(h) { if (h === null) return true; if (h.startsWith('UC')) return /^UC[0-9A-Za-z_\-]{22}$/.test(h); return /^[A-Za-z0-9_\-]{2,100}$/.test(h); }

                const facebook_h = facebook !== '' ? extractHandle(facebook) : null;
                const twitch_h = twitch !== '' ? extractHandle(twitch) : null;
                const youtube_h = youtube !== '' ? extractHandle(youtube) : null;

                if (!validTwitch(twitch_h)) { setFieldError('twitch', 'Invalid Twitch username'); return; }
                if (!validFacebook(facebook_h)) { setFieldError('facebook', 'Invalid Facebook handle'); return; }
                if (!validYoutube(youtube_h)) { setFieldError('youtube', 'Invalid YouTube handle or channel ID'); return; }

                try {
                    saveSocialsBtn.disabled = true;
                    const prev = saveSocialsBtn.textContent;
                    saveSocialsBtn.textContent = 'SAVING...';
                    const data = await postProfileJSON({ action: 'socials', facebook, twitch, youtube, csrf_token: CSRF });

                    if (data.status === 'success') {
                        notify(data.message || data.msg || 'Social links saved', 'success');
                    } else {
                        if (data.fieldErrors) applyFieldErrors(data.fieldErrors);
                        else if (data.globalError) notify(data.globalError, 'error');
                        else notify(data.message || 'Error saving socials', 'error');
                    }
                } catch (err) {
                    console.error('Socials save error', err);
                    const msg = (err && err.serverData && err.serverData.globalError) ? err.serverData.globalError : err.message || 'Network error';
                    notify(msg, 'error');
                } finally {
                    saveSocialsBtn.disabled = false;
                    saveSocialsBtn.textContent = 'UPDATE';
                }
            });
        }

		const editPasswordBtn = el('editPassword');

		function getNewPasswordErrors(v) {
			const errors = [];

			if (!v || !v.length) {
				errors.push('New password is required.');
				return errors;
			}

			if (v.length < 10) errors.push('At least 10 characters.');
			if (!/[A-Z]/.test(v)) errors.push('At least one uppercase letter (A-Z).');
			if (!/[a-z]/.test(v)) errors.push('At least one lowercase letter (a-z).');
			if (!/[0-9]/.test(v)) errors.push('At least one number (0-9).');
			if (!/[!@#$%^&*()\-_=+{};:,<.>]/.test(v)) errors.push('At least one symbol (!@#$ etc.).');

			return errors;
		}

		if (editPasswordBtn) {
			editPasswordBtn.addEventListener('click', async function () {
				clearField('password');
				clearField('newPassword');

				const passwordEl = el('password');
				const newPasswordEl = el('newPassword');

				const currentPassword = (passwordEl && passwordEl.value) ? passwordEl.value : '';
				const newPassword = (newPasswordEl && newPasswordEl.value) ? newPasswordEl.value : '';

				if (!currentPassword.trim()) {
					setFieldError('password', 'Current password is required.');
					return;
				}

				const newPasswordErrors = getNewPasswordErrors(newPassword);
				if (newPasswordErrors.length) {
					setFieldError('newPassword', 'Password must meet these requirements:<br>• ' + newPasswordErrors.join('<br>• '));
					return;
				}

				if (currentPassword === newPassword) {
					setFieldError('newPassword', 'New password must be different from the current password.');
					return;
				}

				try {
					editPasswordBtn.disabled = true;
					editPasswordBtn.textContent = 'UPDATING...';

					const data = await postProfileJSON({
						action: 'password',
						current_pass: currentPassword,
						new_pass: newPassword,
						csrf_token: CSRF
					});

					if (data.status === 'success') {
						notify(data.message || data.msg || 'Password updated', 'success');
						if (passwordEl) passwordEl.value = '';
						if (newPasswordEl) newPasswordEl.value = '';
					} else {
						if (data.fieldErrors) applyFieldErrors(data.fieldErrors);
						else if (data.globalError) notify(data.globalError, 'error');
						else notify(data.message || 'Error updating password', 'error');
					}
				} catch (err) {
					console.error('Password update error', err);
					const msg = (err && err.serverData && err.serverData.globalError)
						? err.serverData.globalError
						: err.message || 'Network error';
					notify(msg, 'error');
				} finally {
					editPasswordBtn.disabled = false;
					editPasswordBtn.textContent = 'UPDATE';
				}
			});
		}

        const saveCountryBtn = el('saveCountryBtn');
        if (saveCountryBtn) {
            saveCountryBtn.addEventListener('click', async function(){
                clearField('countryInput');
                const country = (el('countryInput') && el('countryInput').value) || '';
                saveCountryBtn.disabled = true;
                const prevText = saveCountryBtn.textContent;
                saveCountryBtn.textContent = 'SAVING...';

                try {
                    const data = await postProfileJSON({ action: 'country', country, csrf_token: CSRF });

                    if (data.status === 'success') {
                        notify(data.message || data.msg || 'Country saved', 'success');
                        if (data.flag_url) {
                            const label = document.querySelector('#country-select .select-btn .label');
                            if (label) {
                                label.innerHTML = (data.flag_url ? ('<img src="'+data.flag_url+'" class="flag" style="vertical-align:middle;"> ') : '') + (data.country_name || label.textContent.trim());
                            }
                        }
                    } else {
                        if (data.fieldErrors) applyFieldErrors(data.fieldErrors);
                        else if (data.globalError) notify(data.globalError, 'error');
                        else notify(data.message || 'Error saving country', 'error');
                    }
                } catch (err) {
                    console.error('Save country error:', err);
                    const msg = (err && err.serverData && err.serverData.globalError) ? err.serverData.globalError : err.message || 'Network error';
                    notify(msg, 'error');
                } finally {
                    saveCountryBtn.disabled = false;
                    saveCountryBtn.textContent = prevText;
                }
            });
        }
		
		const saveBioBtn = el('saveBio');
		const bioInput = el('bio');
		const bioCharCount = el('bioCharCount');

		if (bioInput && bioCharCount) {
			bioInput.addEventListener('input', function() {
				const length = this.value.length;
				bioCharCount.textContent = length;
				
				if (length > 450) {
					bioCharCount.style.color = '#ff6b6b';
				} else {
					bioCharCount.style.color = '#666';
				}
			});
			
			bioCharCount.textContent = bioInput.value.length;
		}

		if (saveBioBtn && bioInput) {
			saveBioBtn.addEventListener('click', async function() {
				clearField('bio');
				const bio = bioInput.value.trim();

				const max_length = 500;
				
				if (bio.length > max_length) {
					const errorMsg = `Bio is too long (max ${max_length} characters).`;
					setFieldError('bio', errorMsg);
					return;
				}

				const disallowedPatterns = [
					/<script\b[^>]*>(.*?)<\/script>/is,
					/javascript:/i,
					/on\w+\s*=/i
				];

				for (const pattern of disallowedPatterns) {
					if (pattern.test(bio)) {
						const errorMsg = 'Bio contains disallowed content.';
						setFieldError('bio', errorMsg);
						return;
					}
				}

				saveBioBtn.disabled = true;
				const btnSpan = saveBioBtn.querySelector('.button-text');
				if (btnSpan) btnSpan.textContent = 'UPDATING...';

				try {
					const data = await postProfileJSON({ action: 'bio', bio, csrf_token: CSRF });

					if (data.status === 'success') {
						notify(data.message || data.msg || 'Bio updated successfully!', 'success');
					} else {
						if (data.fieldErrors) applyFieldErrors(data.fieldErrors);
						else if (data.globalError) notify(data.globalError, 'error');
						else notify(data.message || 'Error updating bio', 'error');
					}
				} catch (err) {
					console.error('Bio update error:', err);
					const msg = (err && err.serverData && err.serverData.globalError) ? err.serverData.globalError : err.message || 'Network error';
					notify(msg, 'error');
				} finally {
					saveBioBtn.disabled = false;
					if (btnSpan) btnSpan.textContent = 'UPDATE BIO';
				}
			});
		}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAll);
    } else {
        setTimeout(initializeAll, 0);
    }

})();
</script>

<style>
.custom-select { position:relative; min-width:220px; }
.custom-select .select-btn { display:flex; align-items:center; justify-content:space-between; padding:12px; border-radius:8px; background:#000; color:#e6eef8; cursor:pointer; border:1px solid #2a2f2c; }
.custom-select .options { position:absolute; top:calc(100% + 8px); left:0; right:0; background:#021124; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.6); z-index:50; display:none; max-height:220px; overflow:auto; }
.custom-select.open .options { display:block; }
.custom-select .options .opt { padding:8px 10px; cursor:pointer; border-bottom:1px solid rgba(255,255,255,0.02); color:#cfe6ff; display:flex; align-items:center; gap:10px; }
.custom-select .flag { width:20px; height:15px; object-fit:cover; border-radius:2px; }
.custom-select .options .opt:hover { background:rgba(255,255,255,0.02); }
.custom-select .arrow { margin-left:12px; transform:rotate(0deg); transition:transform .15s ease; }
.custom-select.open .arrow { transform:rotate(180deg); }
.char-counter { display:flex; align-items:center; justify-content:space-between; text-align:right; font-size:12px; color:#666; margin-top: 0px; }
.char-counter.warning { color:#ff6b6b; }
.username-picker-wrap{ margin: 8px 0 12px 0; display: grid; gap: 10px; max-width: 260px; }
.username-box-stage{ position: relative; width: 260px; height: 180px; }
.username-color-box{ width: 260px; height: 180px; border-radius: 10px; border: 1px solid rgba(255,255,255,.12); cursor: crosshair; display: block; }
.username-picker-dot{ position:absolute; width:14px; height:14px; border-radius:50%; border:2px solid #fff; box-shadow:0 0 0 1px rgba(0,0,0,.65); transform:translate(-50%, -50%); pointer-events:none; left: 0; top: 0; background: #00FF41; }
.username-color-preview{ width: 100%; height: 22px; border-radius: 8px; border: 1px solid rgba(255,255,255,.12); }
.username-editor{ min-height: 44px; padding: 12px 14px; border: 1px solid #2a2f2c; border-radius: 8px; background: #000; color: #fff; outline: none; white-space: pre-wrap; word-break: break-word; caret-color: #fff; font-weight:600; }
#bio { resize:vertical; min-height:80px; font-family:inherit; line-height:1.4; }
#bio.error { border-color:#c15755; color:#c15755; }
</style>


	<div class='form-section'>
		<div class='form-section-form'>
			
			<h2 class="heading-title" style="font-size: 2.4rem; margin:10px 0 50px 0">Edit Profile</h2>
			
			<div class="avatar-upload-container">
				<div class="avatar-preview">
					<img src='<?= $MAIN_ROOT . $row['profilepic'] ?>' id="avatarPreview">
					<label for="fileUpload" class="upload-label">
						<img src="assets/images/picture.png" class="upload-icon">
					</label>
				</div>
				
				<input type="file" id="fileUpload" accept="image/*" hidden>
				
				<div id="avatar-preview-button" style="display: none;">
					<button class="btn" id="changeAvatar" style="padding: 10 20px;">
						<span class="button-text">UPDATE</span>
					</button>
				</div>
				
			</div>
			
			<div class="input-group">
				<label class="input-group-label">Username</label>

                <div
					id="usernameEditor"
					class="username-editor"
					contenteditable="true"
					spellcheck="false"
				><?php echo renderStyledUsername($row['username'], $row['username_style']); ?></div>

				<div class="username-picker-wrap">
					<div class="username-box-stage">
						<canvas id="usernameColorBox" width="260" height="180" class="username-color-box"></canvas>
						<div id="usernamePickerDot" class="username-picker-dot"></div>
					</div>

                    <input id="usernameHex" type="text" class="input-group-input" value="<?php echo htmlspecialchars($initialUsernameColor, ENT_QUOTES); ?>" placeholder="#RRGGBB" maxlength="7">
				</div>

				<input type="hidden" id="username" value="<?php echo htmlspecialchars($row['username'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
				<input type="hidden" id="usernameStyle" value="[]">
				<span id="usernameError" style="position:absolute"></span>
				
				<div style='display: flex; justify-content: flex-end; margin-top:10px;'>
					<button type='button' id='editUsername' class='btn' style='padding: 10px 20px;'>UPDATE</button>
				</div>
				
			</div>
			
			<div style="margin-top: 50px;">
				<div class="input-group" style="margin-bottom:10px;">
					<label class='input-group-label'>Bio</label>
					<textarea 
						id="bio"
						class="textInput formInput" 
						placeholder="Say something in your BIO..." 
						maxlength="500"
						rows="4"
					><?php echo htmlspecialchars($row['bio'] ?? ''); ?></textarea>
					<div class="char-counter">
						<div id="bioError" class="error-message"></div>
						<div><span id="bioCharCount">0</span>/500</div>
					</div>
				</div>
				<div style='display: flex; justify-content: flex-end; margin-top:10px;'>
					<button type='button' id='saveBio' class='btn' style='padding: 10px 20px;'><span class="button-text">UPDATE</span></button>
				</div>
			</div>

            <?php
              $userEmail = $row['email'] ?? '';
              $is_placeholder = preg_match('/^noemail\+.*@noemail\.local$/', $userEmail);
              $has_real_email = !$is_placeholder && filter_var($userEmail, FILTER_VALIDATE_EMAIL);
            ?>

            <div style="margin-top:30px;">
                <label class='input-group-label'>Email</label>
                <div style="margin-top:8px;">
                    <?php if ($has_real_email): ?>
                        <div id="emailDisplay"><span style="color:#4caf50; font-weight:600;"><?php echo htmlspecialchars($userEmail); ?></span> <span style="color:#2e7d32">✔</span></div>
                    <?php else: ?>
                        <div id="emailDisplay"></div>
                        <div style="display:flex; gap:10px; align-items:center; margin-top:12px;">
                            <input type="email" id="emailInput" class="input-group-input" placeholder="Enter your email" value="">
                            <button id="saveEmail" class="btn" style="padding: 10px 18px;">SAVE</button>
                        </div>
                        <div id="emailInputError" style="margin-top:6px;"></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $countries = [
                '' => 'Not set',
                'US'=>'United States','GB'=>'United Kingdom','CA'=>'Canada','DE'=>'Germany','FR'=>'France',
                'ES'=>'Spain','IT'=>'Italy','NL'=>'Netherlands','SE'=>'Sweden','AU'=>'Australia',
                'IN'=>'India','JP'=>'Japan','CN'=>'China','DZ'=>'Algeria','MA'=>'Morocco','EG'=>'Egypt'
            ];

            $currentCountry = $row['country'] ?? '';

            $flagsBaseUrl = rtrim($MAIN_ROOT, '/') . '/assets/images/flags/'; 
            ?>

            <div style="margin-top:50px;">
              <label class='input-group-label' style="display:flex;align-items:center;gap:10px;">
                Country
              </label>
              <div class="custom-select" id="country-select" role="listbox" aria-label="Country selector" tabindex="0">
                <div class="select-btn" tabindex="0">
                  <span class="label" style="display:flex;align-items:center;gap:10px;">
                    <?php
                      $label = $countries[$currentCountry] ?? 'Not set';
                      if ($currentCountry && $currentCountry !== '') {
                          $imgName = strtolower($currentCountry) . ".png";
                          echo '<img src="' . $flagsBaseUrl . $imgName . '" class="flag" style="vertical-align:middle; width:20px;">';
                      }
                      echo htmlspecialchars($label, ENT_QUOTES);
                    ?>
                  </span>
                  <svg class="arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" style="opacity:.9"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="options" role="list">
                    <?php foreach ($countries as $code => $label): 
                        if ($code !== '') {
                            $lowerCode = strtolower($code);
                            $imgHtml = '<img src="'. $flagsBaseUrl . $lowerCode .'.png" class="flag" alt="'.htmlspecialchars($code).'" style="width:20px; margin-right:8px;">';
                        } else {
                            $imgHtml = '';
                        }
                    ?>
                    <div class="opt" data-value="<?php echo htmlspecialchars($code, ENT_QUOTES); ?>" role="option" aria-selected="<?php echo ($code === $currentCountry) ? 'true' : 'false'; ?>" style="display:flex; align-items:center;">
                      <?php echo $imgHtml; ?>
                      <span><?php echo htmlspecialchars($label, ENT_QUOTES); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" id="countryInput" name="country" value="<?php echo htmlspecialchars($currentCountry, ENT_QUOTES); ?>">
              </div>

              <div style="display: flex; justify-content: flex-end; margin-top:10px;">
                <button type="button" id="saveCountryBtn" class="btn" style="padding:10px 20px;">UPDATE</button>
              </div>
            </div>

			<div id='form-socials' style='margin-top: 50px;'>
			  <div class="input-group" style='position: relative;'>
				<div style="display: flex; align-items: center; gap: 10px;  position: relative;">
				  <img src='assets/images/socialmedias/facebook.png' style=''>
				  <input type='text' id='facebook' class='input-group-input' value='<?php echo htmlspecialchars($row['facebook'] ?? '', ENT_QUOTES); ?>' placeholder='username or full URL'>
				</div>
				<span id="facebookError" style='color:#c15755;font-size:12px'></span>
			  </div>

			  <div class="input-group" style='position: relative;'>
				<div style="display: flex; align-items: center; gap: 10px;  position: relative;">
				  <img src='assets/images/socialmedias/twitch.png' style=''>
				  <input type='text' id='twitch' class='input-group-input' value='<?php echo htmlspecialchars($row['twitch'] ?? '', ENT_QUOTES); ?>' placeholder='username or full URL'>
				</div>
				<span id="twitchError" style='color:#c15755;font-size:12px'></span>
			  </div>

			  <div class="input-group" style='position: relative;'>
				<div style="display: flex; align-items: center; gap: 10px;  position: relative;">
				  <img src='assets/images/socialmedias/youtube.png' style=''>
				  <input type='text' id='youtube' class='input-group-input' value='<?php echo htmlspecialchars($row['youtube'] ?? '', ENT_QUOTES); ?>' placeholder='@handle, channel ID or full URL'>
				</div>
				<span id="youtubeError" style='color:#c15755;font-size:12px'></span>
			  </div>

			  <div style='display: flex; justify-content: flex-end; margin-top:10px;'>
				<button type='button' id='saveSocials' class='btn' style='padding:10px 20px;'>UPDATE</button>
			  </div>
			</div>
			
			<div style='margin-top: 50px;'>
				<div class="input-group">
					<label class='input-group-label'>Current Password</label>
					<div style="display: flex; align-items: center; gap: 10px;  position: relative;">
						<input type='password' class='input-group-input' id='password'>
						<img src='assets/images/passwordshow.png' id='eyeIcon' style='width: 35px; position: absolute; right: 1px; top: 5px; z-index: 10; cursor: pointer;'>
					</div>
					<span id="passwordError" style=''></span>
				</div>
				
				<div class="input-group">
					<label class='input-group-label'>New Password</label>
					<div style="display: flex; align-items: center; gap: 10px;  position: relative;">
						<input type='password' class='input-group-input' id='newPassword'>
						<img src='assets/images/passwordshow.png' id='newEyeIcon' style='width: 35px; position: absolute; right: 1px; top: 5px; z-index: 10; cursor: pointer;'>
					</div>
					<span id="newPasswordError" style=''></span>
				</div>
				
				<div style='display: flex; justify-content: flex-end; margin-top:10px;'>
					<button type='button' id='editPassword' class='btn' style='padding: 10px 20px;'><span class="button-text">UPDATE</span></button>
				</div>
				
				<br>
				
			</div>

			
		</div>
	</div>


<script>
    (function(){
        function setupToggle(eyeId, inputId){
            const eye = document.getElementById(eyeId); 
            const input = document.getElementById(inputId);
            if(!eye || !input) return;
            eye.addEventListener('click', function(){
                if (input.type === 'password') { 
                    input.type = 'text'; 
                    eye.src = 'assets/images/passwordhide.png'; 
                } else { 
                    input.type = 'password'; 
                    eye.src = 'assets/images/passwordshow.png'; 
                }
            });
        }
        setupToggle('eyeIcon','password');
        setupToggle('newEyeIcon','newPassword');

        function initializePasswordValidation() {
            const newPasswordInput = document.getElementById('newPassword');
            const newPasswordError = document.getElementById('newPasswordError');
            if (!newPasswordInput || !newPasswordError) return;
            
            const checklist = document.createElement('div');
            checklist.className = 'password-checklist';
            checklist.style.marginTop = '10px';
            checklist.style.fontSize = '12px';
            
            const requirements = [
                { id: 'length', text: 'At least 10 characters' },
                { id: 'uppercase', text: 'Uppercase letter (A-Z)' },
                { id: 'lowercase', text: 'Lowercase letter (a-z)' },
                { id: 'number', text: 'Number (0-9)' },
                { id: 'symbol', text: 'Symbol (!@#$ etc.)' }
            ];
            
            checklist.innerHTML = requirements.map(req => 
                `<div id="new-req-${req.id}" class="requirement-item" style="margin: 4px 0; color: #666;">
                    <span class="requirement-icon" style="margin-right: 6px;">✗</span> 
                    <span class="requirement-text">${req.text}</span>
                </div>`
            ).join('');
            
            newPasswordError.parentNode.insertBefore(checklist, newPasswordError.nextSibling);
            newPasswordInput.addEventListener('input', function() {
                const v = this.value;
                requirements.forEach(req => {
                    const el = document.getElementById('new-req-' + req.id);
                    if (!el) return;
                    let ok = false;
                    if (req.id === 'length') ok = v.length >= 10;
                    if (req.id === 'uppercase') ok = /[A-Z]/.test(v);
                    if (req.id === 'lowercase') ok = /[a-z]/.test(v);
                    if (req.id === 'number') ok = /[0-9]/.test(v);
                    if (req.id === 'symbol') ok = /[!@#$%^&*()\-_=+{};:,<.>]/.test(v);
                    const icon = el.querySelector('.requirement-icon');
                    icon.textContent = ok ? '✓' : '✗';
                    icon.style.color = ok ? '#4CAF50' : '#F44336';
                    el.style.color = ok ? '#4CAF50' : '#666';
                });
            });
        }
        initializePasswordValidation();

        (function setupCountrySelect(){
            const wrapper = document.getElementById('country-select');
            if (!wrapper) return;
            const btn = wrapper.querySelector('.select-btn');
            const opts = Array.from(wrapper.querySelectorAll('.options .opt'));
            const labelSpan = wrapper.querySelector('.label');
            const hiddenInput = document.getElementById('countryInput');

            function open(){ wrapper.classList.add('open'); wrapper.setAttribute('aria-expanded','true'); }
            function close(){ wrapper.classList.remove('open'); wrapper.setAttribute('aria-expanded','false'); }
            function toggle(){ wrapper.classList.toggle('open'); }

            if (btn) btn.addEventListener('click', function(e){ e.stopPropagation(); toggle(); });

            opts.forEach(o => {
                o.setAttribute('tabindex','0');
                o.addEventListener('click', function(){
                    const val = this.dataset.value || '';
                    const txt = this.textContent.trim();
                    const img = this.querySelector('img.flag');
                    if (labelSpan) labelSpan.innerHTML = (img ? img.outerHTML + ' ' : '') + txt;
                    if (hiddenInput) hiddenInput.value = val;
                    opts.forEach(x => x.setAttribute('aria-selected','false'));
                    this.setAttribute('aria-selected','true');
                    close();
                });
            });

            document.addEventListener('click', function(e){ if (!wrapper.contains(e.target)) close(); });

            wrapper.addEventListener('keydown', function(e){
                const KEY = e.key;
                if (KEY === 'Enter' || KEY === ' ') { e.preventDefault(); toggle(); return; }
                if (KEY === 'Escape') { close(); return; }
                if (KEY === 'ArrowDown' || KEY === 'ArrowUp') {
                    e.preventDefault();
                    if (!wrapper.classList.contains('open')) { open(); return; }
                    const focusable = opts;
                    let idx = focusable.indexOf(document.activeElement);
                    if (idx === -1) idx = 0;
                    idx = Math.max(0, Math.min(focusable.length - 1, idx + (KEY === 'ArrowDown' ? 1 : -1)));
                    focusable[idx].focus();
                }
            });
        })();

    })();
</script>

</main>

<?php
include("assets/_footer.php");
?>
