/* Contact Hub — front-end behaviour. Vanilla JS, no dependencies. */
(function () {
	'use strict';

	var cfg = window.ContactHub || {};

	/* ---------- analytics (works for the widget and the shortcode buttons) ---------- */
	document.addEventListener('click', function (e) {
		if (cfg.analytics === false || !e.target.closest) { return; }
		var a = e.target.closest('a[data-ch-channel]');
		if (!a) { return; }
		var channel = a.getAttribute('data-ch-channel');
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ event: 'contact_hub_click', contact_channel: channel, page_path: location.pathname });
		if (typeof window.gtag === 'function') {
			window.gtag('event', 'contact_click', { method: channel });
		}
		document.dispatchEvent(new CustomEvent('contacthub:click', { detail: { channel: channel } }));
	});

	var root = document.getElementById('contact-hub');
	if (!root) { return; }

	var launch = root.querySelector('.ch-launch');
	var panel = root.querySelector('.ch-panel');
	var bubble = root.querySelector('.ch-bubble');
	var sub = root.querySelector('.ch-sub');
	var toast = root.querySelector('.ch-toast');
	var isOpen = false;

	/* ---------- storage (always guarded: it can be blocked) ---------- */
	var store = {
		get: function (k) { try { return window.localStorage.getItem(k); } catch (_) { return null; } },
		set: function (k) { try { window.localStorage.setItem(k, '1'); } catch (_) { /* ignore */ } }
	};

	/* ---------- open / close ---------- */
	function hideBubble() {
		if (bubble) { bubble.hidden = true; }
	}

	function setOpen(next, focusTarget) {
		isOpen = next;
		root.setAttribute('data-state', next ? 'open' : 'closed');
		launch.setAttribute('aria-expanded', next ? 'true' : 'false');
		panel.inert = !next;
		if (next) {
			hideBubble();
			if (focusTarget === 'panel') {
				var first = panel.querySelector('.ch-item');
				if (first) { first.focus(); }
			}
		} else if (focusTarget === 'launch') {
			launch.focus();
		}
	}

	panel.inert = true;

	launch.addEventListener('click', function (e) {
		// e.detail === 0 means the click came from the keyboard: move focus into the panel.
		setOpen(!isOpen, !isOpen && e.detail === 0 ? 'panel' : null);
	});

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') { return; }
		if (isOpen) { setOpen(false, 'launch'); }
		else if (bubble && !bubble.hidden) { hideBubble(); }
	});

	document.addEventListener('pointerdown', function (e) {
		if (isOpen && !root.contains(e.target)) { setOpen(false); }
	});

	root.addEventListener('focusout', function (e) {
		if (isOpen && e.relatedTarget && !root.contains(e.relatedTarget)) { setOpen(false); }
	});

	/* ---------- welcome bubble: shown once per visitor ---------- */
	if (bubble) {
		var key = cfg.bubbleKey || 'ch_bubble';
		var body = bubble.querySelector('.ch-bubble-body');
		var close = bubble.querySelector('.ch-bubble-x');
		if (body) { body.addEventListener('click', function () { setOpen(true, 'panel'); }); }
		if (close) { close.addEventListener('click', hideBubble); }

		if (cfg.bubbleDelay > 0 && !store.get(key)) {
			window.setTimeout(function () {
				if (!isOpen) {
					bubble.hidden = false;
					store.set(key);
				}
			}, cfg.bubbleDelay * 1000);
		}
	}

	/* ---------- business hours (evaluated in the browser so cached pages stay correct) ---------- */
	var hours = cfg.hours;
	if (hours && hours.days) {
		var DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

		var toMin = function (s) {
			var p = String(s).split(':');
			return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
		};

		var siteNow = function () {
			try {
				var parts = new Intl.DateTimeFormat('en-US', {
					timeZone: hours.tz, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
				}).formatToParts(new Date());
				var get = function (t) {
					for (var i = 0; i < parts.length; i++) { if (parts[i].type === t) { return parts[i].value; } }
					return '0';
				};
				return { day: get('weekday').toLowerCase().slice(0, 3), min: parseInt(get('hour'), 10) * 60 + parseInt(get('minute'), 10) };
			} catch (_) {
				// Timezone given as a UTC offset: fall back to the offset sent by the server.
				var d = new Date(Date.now() + (hours.offset || 0) * 60000);
				return { day: DAYS[d.getUTCDay()], min: d.getUTCHours() * 60 + d.getUTCMinutes() };
			}
		};

		var refresh = function () {
			var n = siteNow();
			var d = hours.days[n.day];
			var online = !!(d && Number(d.open) && n.min >= toMin(d.from) && n.min < toMin(d.to));
			root.setAttribute('data-status', online ? 'online' : 'offline');
			if (sub && cfg.subtitles) { sub.textContent = online ? cfg.subtitles.online : cfg.subtitles.offline; }
		};

		refresh();
		window.setInterval(refresh, 60000);
	}

	/* ---------- copy to clipboard (phone / email, desktop) ---------- */
	var toastTimer;
	function flash(msg) {
		if (!toast) { return; }
		toast.textContent = msg;
		toast.classList.add('is-on');
		window.clearTimeout(toastTimer);
		toastTimer = window.setTimeout(function () { toast.classList.remove('is-on'); }, 1800);
	}

	function legacyCopy(text) {
		var t = document.createElement('textarea');
		t.value = text;
		t.setAttribute('readonly', '');
		t.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
		document.body.appendChild(t);
		t.select();
		try { document.execCommand('copy'); } catch (_) { /* ignore */ }
		document.body.removeChild(t);
	}

	root.addEventListener('click', function (e) {
		var btn = e.target.closest('.ch-copy');
		if (!btn) { return; }
		var text = btn.getAttribute('data-copy');
		var done = function () { flash((cfg.i18n && cfg.i18n.copied) || 'Copied'); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, function () { legacyCopy(text); done(); });
		} else {
			legacyCopy(text);
			done();
		}
	});
})();
