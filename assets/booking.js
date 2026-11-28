(function () {
	'use strict';
	var R = document.getElementById('nbd');
	if (!R || !window.NB) { return; }
	// Trin: 1 tid, 2 oplysninger, 3 betaling, 4 supplerende spørgsmål (efter betaling).
	var step = 1, sel = { slot: null, date: null, time: null }, view = new Date(), slots = {}, busy = false, ref = null, gift = '';
	view.setDate(1);
	var MON = ['januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'];
	function q(s) { return R.querySelector(s); }
	function qa(s) { return R.querySelectorAll(s); }
	function v(n) { var e = R.querySelector('input[name=' + n + ']:checked'); return e ? e.value : ''; }
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
	function err(t) { var e = q('#nbd-err'); e.textContent = t || ''; e.hidden = !t; }
	function long(ds) { var p = ds.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString('da-DK', { weekday: 'long', day: 'numeric', month: 'long' }); }
	function post(path, body) {
		return fetch(NB.api + path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, code: r.status, j: j }; }); });
	}

	function valid() {
		if (step === 1) { return !!sel.slot; }
		if (step === 2) { return q('#nbd-n').value.trim().length > 1 && /\S+@\S+\.\S+/.test(q('#nbd-e').value) && q('#nbd-t').value.replace(/\D/g, '').length >= 8 && /^\d{6}-\d{4}$/.test(q('#nbd-c').value) && q('#nbd-ok').checked; }
		if (step === 4) {
			q('[data-stop=g]').hidden = v('nbd_g') !== 'ja'; q('[data-stop=h]').hidden = v('nbd_h') !== 'ja';
			return !busy && !!v('nbd_f') && !!v('nbd_g') && !!v('nbd_h');
		}
		return !busy;
	}
	function show() {
		qa('.nbd-pane').forEach(function (p) { p.hidden = +p.dataset.p !== step; });
		qa('.nbd-steps li').forEach(function (l) { var s = +l.dataset.s; l.className = s === step ? 'on' : (s < step ? 'done' : ''); });
		q('#nbd-back').hidden = step === 1 || step === 4;
		var n = q('#nbd-next');
		n.textContent = step === 3 ? (gift ? 'Book med gavekort' : 'Gå til betaling') : (step === 4 ? 'Send svar' : 'Videre');
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
	function sum(txt) { q('#nbd-st').textContent = txt || (sel.slot ? long(sel.date) + ' kl. ' + sel.time : 'Ikke valgt'); }

	function pay() {
		busy = true; show(); err('');
		post('hold', { slot_id: sel.slot, page: NB.page, t: NB.t, hp: q('#nbd-hp').value, name: q('#nbd-n').value, email: q('#nbd-e').value, phone: q('#nbd-t').value, cpr: q('#nbd-c').value, gift: gift, accept: q('#nbd-ok').checked, method: (q('input[name=nbpm]:checked') || {}).value || '' })
			.then(function (x) {
				if (x.ok && x.j.url) { window.location.href = x.j.url; return; }
				busy = false;
				err((x.j && x.j.message) || 'Bookingen kunne ikke gennemføres. Prøv igen, eller ring til os.');
				if (x.code === 409) { sel.slot = null; step = 1; load(); sum(); }
				show();
			}).catch(function () { busy = false; err('Forbindelsen fejlede. Prøv igen.'); show(); });
	}
	function sendVisit() {
		busy = true; show(); err('');
		post('visit', { ref: ref, visit: { formaal: v('nbd_f'), gravid: v('nbd_g'), graense: v('nbd_h'), note: q('#nbd-x').value } })
			.then(function (x) {
				busy = false;
				if (x.ok) {
					result('Tak - din booking er modtaget', x.j.flag
						? 'Vi har modtaget dine svar. Ud fra svarene kan scanningen sandsynligvis ikke gennemføres som planlagt. En læge kontakter dig, og kan scanningen ikke udføres, refunderer vi hele beløbet.'
						: 'Vi har modtaget dine svar. En læge gennemgår dem, og du får en bekræftelse på e-mail. Kan scanningen ikke godkendes, refunderer vi hele beløbet.');
					return;
				}
				err((x.j && x.j.message) || 'Svarene kunne ikke sendes. Prøv igen, eller ring til os.'); show();
			}).catch(function () { busy = false; err('Forbindelsen fejlede. Prøv igen.'); show(); });
	}

	R.addEventListener('click', function (e) {
		var b = e.target.closest('button'); if (!b || !R.contains(b)) { return; }
		if (b.dataset.m) { view.setMonth(view.getMonth() + (+b.dataset.m)); sel.date = null; load(); return; }
		if (b.dataset.d) { sel.date = b.dataset.d; cal(); times(); show(); return; }
		if (b.dataset.t) { sel.slot = +b.dataset.t; sel.time = b.textContent; times(); sum(); show(); return; }
		if (b.id === 'nbd-next' && !b.disabled) {
			if (step === 3) { pay(); return; }
			if (step === 4) { sendVisit(); return; }
			step++; err(''); show(); q('#nbd-flow').scrollIntoView({ behavior: 'smooth', block: 'start' }); return;
		}
		if (b.id === 'nbd-back') { step--; err(''); show(); }
	});
	// Fødselsdato: kun tal, bindestreg indsættes automatisk efter 6 cifre (ddmmåå-xxxx).
	q('#nbd-c').addEventListener('input', function (e) { var d = e.target.value.replace(/\D/g, '').slice(0, 10); e.target.value = d.length > 6 ? d.slice(0, 6) + '-' + d.slice(6) : d; });
	// Gavekort: tjek koden, før den bruges.
	q('#nbd-gchk').addEventListener('click', function () {
		var c = q('#nbd-g').value.trim().toUpperCase(), m = q('#nbd-gmsg');
		if (!c) { gift = ''; m.textContent = ''; show(); return; }
		post('gift/check', { code: c }).then(function (x) {
			if (x.ok) { gift = c; m.textContent = 'Gavekortet er gyldigt (til og med ' + x.j.expires + '). Der skal ikke betales mere.'; }
			else { gift = ''; m.textContent = (x.j && x.j.message) || 'Koden kunne ikke bruges.'; }
			show();
		});
	});
	q('#nbd-g').addEventListener('input', function () { if (gift) { gift = ''; q('#nbd-gmsg').textContent = ''; show(); } });
	R.addEventListener('change', show); R.addEventListener('input', show);

	function result(h, t) { q('#nbd-flow').hidden = true; var r = q('#nbd-result'); r.hidden = false; q('#nbd-rh').textContent = h; q('#nbd-rt').textContent = t; r.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
	function toVisit(j, paidNote) { step = 4; sum(j.when); q('#nbd-paid').hidden = !paidNote; show(); }

	// Tilbage fra betaling, eller link fra e-mail til spørgeskemaet.
	var u = new URLSearchParams(location.search), st = u.get('nb');
	ref = u.get('ref');
	if ((st === 'ok' || st === 'visit') && ref) {
		var tries = 0;
		(function poll() {
			fetch(NB.api + 'status?ref=' + encodeURIComponent(ref) + '&_=' + Date.now()).then(function (r) { return r.json(); }).then(function (j) {
				if (j.visit) { result('Tak - vi har dine svar', 'Din tid ' + j.when + ' er registreret. Du får en bekræftelse på e-mail, når lægen har godkendt scanningen.'); return; }
				if (j.status === 'paid') {
					toVisit(j, true);
					if (st === 'ok') { try { if (window.gtag) { window.gtag('event', 'booking_completed'); } } catch (x) {} }
				} else if (j.status === 'hold' && tries++ < 10) { setTimeout(poll, 2000); }
				else if (j.status === 'hold') { toVisit(j, false); }
				else if (j.status === 'expired' && st === 'ok') { result('Betalingen blev ikke gennemført', 'Tiden er frigivet igen. Du kan vælge en ny tid og prøve igen.'); }
				else if (j.status) { result('Bookingen er ikke aktiv', 'Status: ' + j.label + '. Kontakt os, hvis du har spørgsmål.'); }
				else { result('Ukendt booking', 'Linket er ikke gyldigt. Kontakt os, hvis du har spørgsmål.'); }
			}).catch(function () { result('Tak', 'Du får en e-mail, når betalingen er registreret.'); });
		})();
	} else if (st === 'cancel' && ref) {
		post('cancel', { ref: ref }).then(function (x) {
			if (x.j.status === 'paid') { toVisit({ when: '' }, true); return; }
			err('Betalingen blev afbrudt, og tiden er frigivet. Du kan vælge en ny tid.');
		}).catch(function () {});
		if (history.replaceState) { history.replaceState(null, '', location.pathname); }
		load();
	} else {
		load();
	}
	show();
})();
