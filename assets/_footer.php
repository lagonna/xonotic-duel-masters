	
	<div style="color: #6c757d; font-size: 13px; padding: 20px; text-align: center; margin-top: 30px;">
        <p style="margin: 0 0 5px 0;">
            &copy; <?php echo date('Y'); ?> <strong>Xonotic Duel Masters</strong>. 
            A fan-made project for the <a href="https://xonotic.org" target="_blank" style="text-decoration: underline;">Xonotic</a> community.
        </p>
        <p style="margin: 0;">
            Contribute or view the code on 
            <a style="">GitHub</a>.
        </p>
    </div>

	<div id="confirmModal" class="modal">
	  <div class="modal-content">
		<h3 id="modalTitle"></h3>
		<p id="modalMessage"></p>
		<div class="modal-buttons">
		  <button id="cancelButton" onclick="closeModal()">Cancel</button>
		  <button id="confirmButton" class="delete-btn">Confirm</button>
		</div>
	  </div>
	</div>

	<div id="notification-container" style="position: fixed; top: 15px; right: 15px; z-index: 9999;"></div>

	<script type='text/javascript'>
		function showConfirmationModal(title, message, confirmCallback) {
			const confirmModal = document.getElementById('confirmModal');
			const modalTitle = document.getElementById('modalTitle');
			const modalMessage = document.getElementById('modalMessage');
			const confirmButton = document.getElementById('confirmButton');

			modalTitle.textContent = title;
			modalMessage.textContent = message;

			const newConfirmButton = confirmButton.cloneNode(true);
			confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);
			
			newConfirmButton.addEventListener('click', () => {
				confirmCallback();
				closeModal();
			});

			confirmModal.style.display = 'flex';
		}

		function closeModal() {
			document.getElementById('confirmModal').style.display = 'none';
		}
		
		function showNotification(messageHtml, type = 'success', duration = 7000) {
			const container = document.getElementById('notification-container');
			if(!container) return console.warn('Notification container not found');

			const existingNotifications = container.querySelectorAll('.notification');
			existingNotifications.forEach(notification => {
				notification.remove();
			});

			const toast = document.createElement('div');
			toast.className = 'notification ' + (type === 'error' ? 'error' : 'success');

			const temp = document.createElement('div');
			temp.innerHTML = messageHtml;
			const plainText = temp.textContent || temp.innerText || '';

			const msg = document.createElement('div');
			msg.className = 'notification-msg';
			msg.textContent = plainText;
			toast.appendChild(msg);

			const closeBtn = document.createElement('button');
			closeBtn.className = 'close-btn';
			closeBtn.innerHTML = '&times;';
			closeBtn.onclick = () => removeToast(toast, timer);
			toast.appendChild(closeBtn);

			const progress = document.createElement('div');
			progress.className = 'progress';
			toast.appendChild(progress);

			container.appendChild(toast);

			setTimeout(() => {
				progress.style.transition = `width ${duration}ms linear`;
				progress.style.width = '0%';
			}, 40);

			const timer = setTimeout(() => removeToast(toast), duration);

			function removeToast(el, t = null) {
				if (t) clearTimeout(t);
				el.style.animation = 'toastOut 220ms ease forwards';
				setTimeout(() => el.remove(), 230);
			}
		}
		
		function updateNotificationBadge() {
			const badge = document.querySelector('#notificationBadge');
			if (badge) {
				let current = parseInt(badge.textContent) || 0;
				badge.textContent = current + 1;
				badge.style.display = 'inline-block'; // ensure visible
			}
		}
		
		// function incrementHeaderMessageBadge() {
			// let badge = document.querySelector('.message-icon'); 
			// if (badge) {
				// let current = parseInt(badge.textContent) || 0;
				// badge.textContent = current + 1;
				// badge.style.display = 'inline-block';
			// }
		// }
		
		function pulseLoginBtn() {
		  const buttons = document.querySelectorAll("#loginBtn");
		  
		  buttons.forEach(btn => btn.classList.add("pulse"));

		  setTimeout(() => {
			buttons.forEach(btn => btn.classList.remove("pulse"));
		  }, 3000);
		}
		
	</script>

	<?php
	// include($prevFolder."_precence_ws.php");
	?>
	
</body>
</html>