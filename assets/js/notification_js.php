<?php if ($notifications_count > 0): ?>
<script>
(function () {
	const userID = <?php echo $js_user_id; ?>;
	const csrfToken = <?php echo $js_csrf; ?>;
	const MAIN_ROOT = <?php echo $js_main_root; ?>;
	const BATCH_SIZE = <?php echo $js_batch_size; ?>;

	let lastCreatedAt = <?php echo $js_last_created_at; ?>;
	let lastNotifId = <?php echo $js_last_notif_id; ?>;

	const loadMoreBtn = document.getElementById('load-more-btn');
	const notificationsDiv = document.getElementById('notifications-container');

	function loadMoreNotifications() {
		if (!loadMoreBtn) return;
		loadMoreBtn.disabled = true;
		loadMoreBtn.textContent = 'Loading...';

		const payload = {
			limit: BATCH_SIZE,
			csrf_token: csrfToken,
			last_created_at: lastCreatedAt,
			last_notif_id: lastNotifId
		};

		fetch(MAIN_ROOT + 'backend/load_notifications.php', {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(payload)
		}).then(response => {
			if (!response.ok) throw new Error('Network response not ok');
			return response.json();
		}).then(data => {
			loadMoreBtn.disabled = false;
			loadMoreBtn.textContent = 'Load More';

			if (data.error) {
				console.error('Backend error:', data.error);
				return;
			}

			if (data.html && data.html.trim() !== '') {
				notificationsDiv.insertAdjacentHTML('beforeend', data.html);
			}

			if (data.last_created_at) lastCreatedAt = data.last_created_at;
			if (data.last_notif_id) lastNotifId = data.last_notif_id;
			else if (data.last_notification_id) lastNotifId = data.last_notification_id;

			if (!data.has_more) {
				const wrapper = document.querySelector('.load-more-btn-wrapper');
				if (wrapper) wrapper.style.display = 'none';
				const noMoreMsg = document.createElement('div');
				noMoreMsg.className = 'notifications-message';
				noMoreMsg.textContent = "You've reached the end of your notifications.";
				notificationsDiv.appendChild(noMoreMsg);
			}
		}).catch(err => {
			console.error('Fetch error:', err);
			loadMoreBtn.textContent = 'Error loading...';
			loadMoreBtn.disabled = false;
		});
	}

	if (loadMoreBtn) loadMoreBtn.addEventListener('click', loadMoreNotifications);

	const clearBtn = document.getElementById('clear-all-btn');
	if (clearBtn) {
		clearBtn.addEventListener('click', function () {
			showConfirmationModal('Confirm Notifications Deletion', 'Are you sure you want to delete all notifications?', function () {
				const formData = new FormData();
				formData.append('CSRF_TOKKEN', csrfToken);
				fetch(MAIN_ROOT + 'backend/clearnotifications.php', {
					method: 'POST',
					body: formData
				}).then(r => r.json()).then(data => {
					if (data.status === 'success') {
						document.getElementById('notifications-container').innerHTML =
							'<div class="shadedBox" id="shadedBox"><p class="main" align="center"><i>No new notifications.</i></p></div>';
						if (document.getElementById('clear-all-btn')) document.getElementById('clear-all-btn').remove();
						if (document.querySelector('.load-more-btn-wrapper')) document.querySelector('.load-more-btn-wrapper').style.display = 'none';
						if (typeof showNotification === 'function') showNotification(data.msg, 'success');
					} else {
						if (typeof showNotification === 'function') showNotification(data.msg || 'Failed to clear notifications', 'error');
					}
				}).catch(err => {
					console.error('Error clearing:', err);
				});
			});
		});
	}
})();
</script>
<?php endif; ?>


<script>
function maybePrependNotificationCard(data) {
  try {
    if (!data) return null;
    const payload = (data.payload && typeof data.payload === 'object') ? data.payload : data;

    if (typeof payload.subject_html !== 'string' || payload.subject_html.trim() === '') return null;
    const subjectHtml = payload.subject_html;

    const avatarRaw = (typeof payload.avatar_url === 'string' && payload.avatar_url.length) ? payload.avatar_url : 'assets/images/notification.png';

    const siteBase = (window.SITE && window.SITE.base) || (typeof window.MAIN_ROOT !== 'undefined' ? window.MAIN_ROOT : '') || '';
    function resolvePath(p) {
      if (!p) return (siteBase ? (siteBase.replace(/\/+$/, '/') + 'assets/images/notification.png') : 'assets/images/notification.png');
      if (/^https?:\/\//i.test(p)) return p;
      if (!siteBase) return p.replace(/^\/+/, '');
      return siteBase.replace(/\/+$/, '/') + p.replace(/^\/+/, '');
    }

    const container = document.getElementById('notifications-container');
    if (!container) return null;

    const card = document.createElement('div');
    card.className = 'notification-card unseen';

    const avatarWrap = document.createElement('div');
    avatarWrap.className = 'avatar-wrap';
    const img = document.createElement('img');
    img.className = 'notification-avatar';
    img.src = resolvePath(avatarRaw);
    img.alt = 'notification avatar';
    img.loading = 'lazy';
    avatarWrap.appendChild(img);
	
	// let overlayImg = null;
    // const action = payload.action || '';

    // if (action === 'system') {
        // overlayImg = resolvePath('assets/images/settings.png');
    // } else if (action === 'tournament') {
        // overlayImg = resolvePath('assets/images/tournament.png');
    // } else if (action === 'match') {
        // overlayImg = resolvePath('assets/images/tournament_blue.png');
        // // accept avatar_url from payload for main image
        // if (payload.avatar_url) {
            // img.src = resolvePath(payload.avatar_url);
        // } else {
            // img.src = resolvePath('assets/images/match.png');
        // }
    // }

    // if (overlayImg) {
        // const imgOverlay = document.createElement('img');
        // imgOverlay.className = 'avatar-overlay';
        // imgOverlay.src = overlayImg;
        // imgOverlay.alt = '';
        // imgOverlay.loading = 'lazy';
        // avatarWrap.appendChild(imgOverlay);
    // }
	
    card.appendChild(avatarWrap);

    const content = document.createElement('div');
    content.className = 'notification-content';

    const p = document.createElement('p');
    p.innerHTML = subjectHtml;

    const ts = Date.now();
    const tsSpan = document.createElement('div');
    tsSpan.className = 'notif-ts';
    tsSpan.dataset.ts = String(Math.floor(ts / 1000));
    tsSpan.textContent = shortTimeAgo(new Date(ts));
    tsSpan.style.fontSize = '12px';
    tsSpan.style.opacity = '0.8';
    tsSpan.style.marginTop = '6px';

    content.appendChild(p);
    content.appendChild(tsSpan);
    card.appendChild(content);

    const shaded = document.getElementById('shadedBox');
    if (shaded) shaded.remove();

    container.prepend(card);

    return card;

  } catch (err) {
    console.warn('maybePrependNotificationCard (minimal) error:', err);
    return null;
  }

  function shortTimeAgo(d) {
    const diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 10) return 'just now';
    if (diff < 60) return diff + 's';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    return Math.floor(diff / 86400) + 'd';
  }
}
</script>
