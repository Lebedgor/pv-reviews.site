/**
 * PVR Media Reviews — Renewal page logic.
 * Lookup by key/domain → show status → checkout for the renewal product.
 * Accepts ?license=<key> (deep link from the plugin License tab) and
 * pre-fills the lookup form. Eternal licenses show "Lifetime" with no
 * buy button; dated licenses show the expiry and a renew button.
 */
(function () {
	'use strict';

	var lookupForm = document.getElementById('renewLookupForm');
	var lookupInput = document.getElementById('renewQuery');
	var lookupBtn = document.getElementById('renewLookupBtn');
	var lookupError = document.getElementById('renewLookupError');
	var resendBtn = document.getElementById('renewResendBtn');
	var licenseCard = document.getElementById('renewLicenseCard');
	var renewBuyBtn = document.getElementById('renewBuyBtn');
	var renewMessage = document.getElementById('renewMessage');

	var currentLicense = null;

	function showError(text) {
		lookupError.textContent = text;
		lookupError.hidden = false;
	}

	function clearError() {
		lookupError.textContent = '';
		lookupError.hidden = true;
	}

	function setMessage(text, isError) {
		renewMessage.textContent = text;
		renewMessage.hidden = false;
		renewMessage.classList.toggle('renew-message--error', !!isError);
	}

	function setBusy(btn, busy, busyText, normalText) {
		btn.disabled = busy;
		btn.querySelector('.renew-btn__text').textContent = busy ? busyText : normalText;
	}

	async function post(path, payload) {
		var response = await fetch(path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		});
		var data = await response.json();
		return { ok: response.ok, data: data };
	}

	function renderLicense(data) {
		currentLicense = data;
		document.getElementById('renewDomain').textContent = data.domain || '—';
		document.getElementById('renewKeyMask').textContent = data.key_mask || '—';

		var untilEl = document.getElementById('renewUpdatesUntil');
		var expiredNote = document.getElementById('renewExpiredNote');

		if (data.renewable === false) {
			// Вечная лицензия: продлевать нечего — обновления включены навсегда.
			untilEl.textContent = 'Lifetime — updates included';
			untilEl.classList.add('renew-green');
			expiredNote.hidden = true;
			renewBuyBtn.hidden = true;
		} else {
			untilEl.textContent = data.updates_until || '—';
			untilEl.classList.toggle('renew-green', !data.updates_expired);
			expiredNote.hidden = !data.updates_expired;
			renewBuyBtn.hidden = false;
		}

		licenseCard.hidden = false;
		resendBtn.hidden = !(data.type === 'domain' && data.can_resend);
	}

	// --- Lookup -------------------------------------------------------------
	lookupForm.addEventListener('submit', async function (e) {
		e.preventDefault();
		clearError();
		setBusy(lookupBtn, true, 'Searching...', 'Find my license');
		licenseCard.hidden = true;
		resendBtn.hidden = true;

		try {
			var res = await post('/api/renew/lookup.php', { query: lookupInput.value.trim() });
			setBusy(lookupBtn, false, '', 'Find my license');

			if (!res.ok || res.data.status !== 'found') {
				showError('License not found. Check the key or domain and try again.');
				return;
			}

			renderLicense(res.data);
		} catch (err) {
			setBusy(lookupBtn, false, '', 'Find my license');
			showError('Network error. Please try again.');
		}
	});

	// --- Deep link from the plugin: /renew?license=KEY ------------------------
	(function prefillFromQuery() {
		var params = new URLSearchParams(window.location.search);
		var license = (params.get('license') || '').trim();
		if (license === '') return;
		lookupInput.value = license;
		if (typeof lookupForm.requestSubmit === 'function') {
			lookupForm.requestSubmit();
		} else {
			lookupForm.dispatchEvent(new Event('submit', { cancelable: true }));
		}
	})();

	// --- Resend key by email -------------------------------------------------
	resendBtn.addEventListener('click', async function () {
		if (!currentLicense || !currentLicense.domain) return;
		setBusy(resendBtn, true, 'Sending...', 'Send');
		try {
			var res = await post('/api/renew/resend_key.php', { domain: currentLicense.domain });
			setBusy(resendBtn, false, '', 'Send');
			setMessage(res.data && res.data.message ? res.data.message : 'Done.', !res.ok);
		} catch (err) {
			setBusy(resendBtn, false, '', 'Send');
			setMessage('Network error. Please try again.', true);
		}
	});

	// --- Renewal checkout -----------------------------------------------------
	renewBuyBtn.addEventListener('click', async function () {
		if (!currentLicense) return;
		clearError();
		setBusy(renewBuyBtn, true, 'Redirecting...', 'Renew for 1 year');
		setMessage('', false);

		try {
			var res = await post('/api/renew/create.php', { query: lookupInput.value.trim() });
			if (res.ok && res.data.checkout_url) {
				window.location.href = res.data.checkout_url;
				return;
			}
			setBusy(renewBuyBtn, false, '', 'Renew for 1 year');
			setMessage(res.data.error || 'Failed to create checkout. Please try again.', true);
		} catch (err) {
			setBusy(renewBuyBtn, false, '', 'Renew for 1 year');
			setMessage('Network error. Please try again.', true);
		}
	});
})();
