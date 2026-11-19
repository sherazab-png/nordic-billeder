(function () {
	'use strict';
	var R = document.getElementById('nbd');
	if (!R || !window.NB) { return; }
	var step = 1, sel = { slot: null, date: null, time: null }, view = new Date(), slots = {}, busy = false;
	view.setDate(1);
	var MON = ['januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'];
	function q(s) { return R.querySelector(s); }
	function qa(s) { return R.querySelectorAll(s); }
	function v(n) { var e = R.querySelector('input[name=' + n + ']:checked'); return e ? e.value : ''; }
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
	function err(t) { var e = q('#nbd-err'); e.textContent = t || ''; e.hidden = !t; }
	function long(ds) { var p = ds.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString('da-DK', { weekday: 'long', day: 'numeric', month: 'long' }); }

	function valid() {
		if (step === 1) {
			var g = v('nbd_g'), h = v('nbd_h');
			q('[data-stop=g]').hidden = g !== 'ja'; q('[data-stop=h]').hidden = h !== 'ja';
			return !!v('nbd_f') && g === 'nej' && h === 'nej';
		}
		if (step === 2) { return !!sel.slot; }
		if (step === 3) { return q('#nbd-n').value.trim().length > 1 && /\S+@\S+\.\S+/.test(q('#nbd-e').value) && q('#nbd-t').value.replace(/\D/g, '').length >= 8 && q('#nbd-ok').checked; }
		return !busy;
	}
	function show() {
		qa('.nbd-pane').forEach(function (p) { p.hidden = +p.dataset.p !== step; });
		qa('.nbd-steps li').forEach(function (l) { var s = +l.dataset.s; l.className = s === step ? 'on' : (s < step ? 'done' : ''); });
		q('#nbd-back').hidden = step === 1;
		var n = q('#nbd-next');
		n.textContent = step === 4 ? 'Gå til betaling' : 'Videre';
		n.disabled = !valid();
	}
	function load() {
		var y = view.getFullYear(), m = view.getMonth();
		var from = iso(new Date(y, m, 1)), to = iso(new Date(y, m + 1, 0));
		q('#nbd-mon').textContent = MON[m] + ' ' + y;
		q('#nbd-days').classList.add('nbd-load');
		fetch(NB.api + 'slots?from=' + from + '&to=' + to + '&_=' + Date.now(), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
			slots = {};
			(j.slots || []).forEach(function (s) { (slots[s.date] = slots[s.date] || []).push(s); });
			cal(); times();
			q('#nbd-none').hidden = Object.keys(slots).length > 0;
		}).catch(function () { err('Ledige tider kunne ikke hentes. Prøv igen, eller ring til os.'); })
			.then(function () { q('#nbd-days').classList.remove('nbd-load'); });
	}
	function cal() {
		var y = view.getFullYear(), m = view.getMonth();
		var first = (new Date(y, m, 1).getDay() + 6) % 7, days = new Date(y, m + 1, 0).getDate(), h = '<tr>';
		for (var i = 0; i < first; i++) { h += '<td></td>'; }
		for (var d = 1; d <= days; d++) {
			var k = iso(new Date(y, m, d)), ok = !!slots[k];
			h += '<td><button type="button" class="d' + (sel.date === k ? ' sel' : '') + '" data-d="' + k + '"' + (ok ? '' : ' disabled') + ' aria-label="' + long(k) + (ok ? '' : ', ingen ledige tider') + '">' + d + '</button></td>';
			if ((first + d) % 7 === 0 && d < days) { h += '</tr><tr>'; }
		}
		q('#nbd-days').innerHTML = h + '</tr>';
	}
	function times() {
		var box = q('#nbd-times');
		if (!sel.date || !slots[sel.date]) { box.innerHTML = ''; q('#nbd-dlab').textContent = 'Vælg en dag'; return; }
		q('#nbd-dlab').textContent = 'Ledige tider ' + long(sel.date);
		box.innerHTML = slots[sel.date].map(function (s) { return '<button type="button" data-t="' + s.id + '"' + (sel.slot === s.id ? ' class="sel"' : '') + '>' + s.time + '</button>'; }).join('');
	}
	function sum() { q('#nbd-st').textContent = sel.slot ? long(sel.date) + ' kl. ' + sel.time : 'Ikke valgt'; }

	function pay() {
		busy = true; show(); err('');
		var body = {
			slot_id: sel.slot, page: NB.page, t: NB.t, hp: q('#nbd-hp').value,
			visit: { formaal: v('nbd_f'), gravid: v('nbd_g'), graense: v('nbd_h'), note: q('#nbd-x').value },
			name: q('#nbd-n').value, email: q('#nbd-e').value, phone: q('#nbd-t').value, accept: q('#nbd-ok').checked
		};
		fetch(NB.api + 'hold', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, code: r.status, j: j }; }); })
			.then(function (x) {
				if (x.ok && x.j.url) { window.location.href = x.j.url; return; }
				busy = false;
				err((x.j && x.j.message) || 'Bookingen kunne ikke gennemføres. Prøv igen, eller ring til os.');
				if (x.code === 409) { sel.slot = null; step = 2; load(); sum(); }
				show();
			}).catch(function () { busy = false; err('Forbindelsen fejlede. Prøv igen.'); show(); });
	}

	R.addEventListener('click', function (e) {
		var b = e.target.closest('button'); if (!b || !R.contains(b)) { return; }
		if (b.dataset.m) { view.setMonth(view.getMonth() + (+b.dataset.m)); sel.date = null; load(); return; }
		if (b.dataset.d) { sel.date = b.dataset.d; cal(); times(); show(); return; }
		if (b.dataset.t) { sel.slot = +b.dataset.t; sel.time = b.textContent; times(); sum(); show(); return; }
		if (b.id === 'nbd-next' && !b.disabled) { if (step === 4) { pay(); return; } step++; err(''); if (step === 2) { load(); } show(); q('#nbd-flow').scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
		if (b.id === 'nbd-back') { step--; err(''); show(); }
	});
	R.addEventListener('change', show); R.addEventListener('input', show);

	// Tilbage fra betaling.
	var u = new URLSearchParams(location.search), st = u.get('nb'), ref = u.get('ref');
	function result(h, t) { q('#nbd-flow').hidden = true; var r = q('#nbd-result'); r.hidden = false; q('#nbd-rh').textContent = h; q('#nbd-rt').textContent = t; }
	if (st === 'ok' && ref) {
		result('Tak - vi behandler din betaling', 'Et øjeblik ...');
		var tries = 0;
		(function poll() {
			fetch(NB.api + 'status?ref=' + encodeURIComponent(ref) + '&_=' + Date.now()).then(function (r) { return r.json(); }).then(function (j) {
				if (j.status === 'paid' || j.status === 'approved') {
					result('Tak - din booking er modtaget', 'Din tid ' + j.when + ' er betalt. En læge gennemgår nu din visitation, og du får en bekræftelse på e-mail. Kan scanningen ikke godkendes, refunderer vi hele beløbet.');
					try { if (window.gtag) { window.gtag('event', 'booking_completed'); } } catch (x) {}
				} else if (j.status === 'hold' && tries++ < 10) { setTimeout(poll, 2000); }
				else if (j.status === 'hold') { result('Betalingen behandles', 'Vi har endnu ikke fået bekræftelse fra betalingen. Du får en e-mail, så snart den er registreret. Kontakt os, hvis du ikke hører fra os.'); }
				else { result('Bookingen kunne ikke gennemføres', 'Status: ' + j.label + '. Kontakt os, hvis du har spørgsmål.'); }
			}).catch(function () { result('Tak', 'Du får en e-mail, når betalingen er registreret.'); });
		})();
	} else if (st === 'cancel' && ref) {
		fetch(NB.api + 'cancel', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ref: ref }) }).then(function (r) { return r.json(); }).then(function (j) {
			if (j.status === 'paid') { result('Tak - din booking er modtaget', 'Betalingen blev gennemført. Du får en e-mail.'); return; }
			err('Betalingen blev afbrudt, og tiden er frigivet. Du kan vælge en ny tid.');
		}).catch(function () {});
		if (history.replaceState) { history.replaceState(null, '', location.pathname); }
	}
	show();
})();
