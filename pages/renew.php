<?php
/**
 * Renew page — extend the update subscription of an existing license.
 * Served by layout.php (header/footer/nav come from there).
 * Self-contained styles + script: no dependency on the landing styles.
 */
?>
<section class="pvr-renew">
<style>
  .pvr-renew { max-width: 620px; margin: 0 auto; padding: 130px 16px 80px; text-align: center; }
  .pvr-renew h1 { font-size: 2rem; margin-bottom: 8px; }
  .pvr-renew__subtitle { color: #64748b; margin-bottom: 32px; line-height: 1.6; }
  .pvr-renew__card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; margin-bottom: 16px; text-align: left; }
  .pvr-renew__label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px; }
  .pvr-renew__input { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 15px; box-sizing: border-box; }
  .pvr-renew__input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
  .pvr-renew__btn { display: inline-block; padding: 12px 20px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; font-size: 14px; margin-top: 12px; width: 100%; }
  .pvr-renew__btn--primary { background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; }
  .pvr-renew__btn--secondary { background: #6366f1; color: #fff; }
  .pvr-renew__btn--secondary:hover { background: #4f46e5; }
  .pvr-renew__btn--link { background: none; color: #6366f1; text-decoration: underline; font-weight: 500; }
  .pvr-renew__btn:disabled { opacity: .6; cursor: wait; }
  .pvr-renew__error { color: #dc2626; font-size: 13px; margin: 8px 0 0; }
  .pvr-renew__info-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #64748b; }
  .pvr-renew__info-row:last-child { border-bottom: none; }
  .pvr-renew__green { color: #10b981; }
  .pvr-renew__note { margin-top: 12px; padding: 10px 12px; background: #fef3c7; color: #b45309; border-radius: 8px; font-size: 13px; line-height: 1.5; text-align: left; }
  .pvr-renew__message { margin-top: 16px; font-size: 14px; color: #10b981; }
  .pvr-renew__message--error { color: #dc2626; }
</style>

<h1>Renew Your License</h1>
<p class="pvr-renew__subtitle">
	Enter your license key or domain to look up your license.
	Renewing extends <strong>your Pro license and updates</strong> for another year.
</p>

<form id="pvrRenewLookupForm" class="pvr-renew__card">
	<label for="pvrRenewQuery" class="pvr-renew__label">License key or domain</label>
	<input type="text" id="pvrRenewQuery" class="pvr-renew__input" placeholder="XXXX-XXXXXXXX-… or example.com" autocomplete="off">
	<p id="pvrRenewError" class="pvr-renew__error" hidden></p>
	<button type="submit" id="pvrRenewLookupBtn" class="pvr-renew__btn pvr-renew__btn--secondary"><span>Find my license</span></button>
	<button type="button" id="pvrRenewResendBtn" class="pvr-renew__btn pvr-renew__btn--link" hidden>Lost your key? Send it to my email</button>
</form>

<div id="pvrRenewLicenseCard" class="pvr-renew__card" hidden>
	<div>
		<div class="pvr-renew__info-row"><span>Domain</span><strong id="pvrRenewDomain">—</strong></div>
		<div class="pvr-renew__info-row"><span>License key</span><strong id="pvrRenewKeyMask">—</strong></div>
		<div class="pvr-renew__info-row"><span>Valid until</span><strong id="pvrRenewUpdatesUntil" class="pvr-renew__green">—</strong></div>
		<p id="pvrRenewExpiredNote" class="pvr-renew__note" hidden>
			Your license has expired — the plugin keeps working in free mode until you renew.
		</p>
	</div>
	<button type="button" id="pvrRenewBuyBtn" class="pvr-renew__btn pvr-renew__btn--primary"><span>Renew for 1 year</span></button>
</div>

<p id="pvrRenewMessage" class="pvr-renew__message" hidden></p>

<script>
(function () {
	'use strict';
	var lookupForm = document.getElementById('pvrRenewLookupForm');
	var lookupInput = document.getElementById('pvrRenewQuery');
	var lookupBtn = document.getElementById('pvrRenewLookupBtn');
	var lookupError = document.getElementById('pvrRenewError');
	var resendBtn = document.getElementById('pvrRenewResendBtn');
	var licenseCard = document.getElementById('pvrRenewLicenseCard');
	var buyBtn = document.getElementById('pvrRenewBuyBtn');
	var messageEl = document.getElementById('pvrRenewMessage');
	var current = null;

	function showError(t) { lookupError.textContent = t; lookupError.hidden = false; }
	function clearError() { lookupError.textContent = ''; lookupError.hidden = true; }
	function setMessage(t, isErr) { messageEl.textContent = t; messageEl.hidden = false; messageEl.classList.toggle('pvr-renew__message--error', !!isErr); }
	function setBusy(btn, busy, busyText, normalText) { btn.disabled = busy; btn.querySelector('span').textContent = busy ? busyText : normalText; }
	async function post(path, payload) {
		var response = await fetch(path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
		return { ok: response.ok, data: await response.json() };
	}

	function renderLicense(data) {
		current = data;
		document.getElementById('pvrRenewDomain').textContent = data.domain || '-';
		document.getElementById('pvrRenewKeyMask').textContent = data.key_mask || '-';
		var untilEl = document.getElementById('pvrRenewUpdatesUntil');
		var expiredNote = document.getElementById('pvrRenewExpiredNote');
		if (data.renewable === false) {
			// Вечная лицензия: продлевать нечего — обновления включены навсегда.
			untilEl.textContent = 'Lifetime — updates included';
			untilEl.classList.add('pvr-renew__green');
			expiredNote.hidden = true;
			buyBtn.hidden = true;
		} else {
			untilEl.textContent = data.updates_until || '-';
			untilEl.classList.toggle('pvr-renew__green', !data.updates_expired);
			expiredNote.hidden = !data.updates_expired;
			buyBtn.hidden = false;
		}
		licenseCard.hidden = false;
		resendBtn.hidden = !(data.type === 'domain' && data.can_resend);
	}

	// Deep link from the plugin License tab: /renew?license=KEY.
	// IMPORTANT: runs AFTER all event listeners are attached — requestSubmit
	// before the submit handler exists does a native submit/reload instead.
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

	resendBtn.addEventListener('click', async function () {
		if (!current || !current.domain) return;
		setBusy(resendBtn, true, 'Sending...', 'Send');
		try {
			var res = await post('/api/renew/resend_key.php', { domain: current.domain });
			setBusy(resendBtn, false, '', 'Send');
			setMessage(res.data && res.data.message ? res.data.message : 'Done.', !res.ok);
		} catch (err) {
			setBusy(resendBtn, false, '', 'Send');
			setMessage('Network error. Please try again.', true);
		}
	});

	buyBtn.addEventListener('click', async function () {
		if (!current) return;
		clearError();
		setBusy(buyBtn, true, 'Redirecting...', 'Renew for 1 year');
		setMessage('', false);
		try {
			var res = await post('/api/renew/create.php', { query: lookupInput.value.trim() });
			if (res.ok && res.data.checkout_url) { window.location.href = res.data.checkout_url; return; }
			setBusy(buyBtn, false, '', 'Renew for 1 year');
			setMessage(res.data.error || 'Failed to create checkout. Please try again.', true);
		} catch (err) {
			setBusy(buyBtn, false, '', 'Renew for 1 year');
			setMessage('Network error. Please try again.', true);
		}
	});

	// Deep link auto-lookup: runs last so every handler above is attached.
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
})();
</script>
</section>
