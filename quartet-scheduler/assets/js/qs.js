/* Programant Haydn — Quadra d'assajos
   La interactivitat de base (pestanyes, setmanes, dies, desar) funciona sense
   JS: són enllaços i un formulari POST real. Aquest script afegeix:
   1. Colors a les llistes desplegables en triar.
   2. Memòria temporal (localStorage): nom, instrument i totes les tries
      es conserven en navegar entre setmanes i mesos, i el botó de desar
      envia tot de cop (camp ocult qs_draft amb el mapa complet). */
(function () {
	'use strict';

	var KEY = 'qs_qa_draft_v1';
	var D = null;

	function loadDraft() {
		try {
			var raw = localStorage.getItem(KEY);
			var d = raw ? JSON.parse(raw) : null;
			return (d && typeof d === 'object' && !Array.isArray(d)) ? d : {};
		} catch (e) {
			return {};
		}
	}

	function persist() {
		try {
			localStorage.setItem(KEY, JSON.stringify(D));
		} catch (e) { /* privacitat o quota: seguim sense memòria */ }
	}

	function parseName(name) {
		// format name="qs_a[2027-02-27][20:00]"
		var m = /^qs_a\[([0-9]{4}-[0-9]{2}-[0-9]{2})\]\[([0-9]{1,2}:[0-9]{2})\]$/.exec(name || '');
		return m ? { date: m[1], slot: m[2] } : null;
	}

	function paint(sel) {
		var v = sel.value;
		sel.classList.remove('qs-f0', 'qs-f1', 'qs-f2');
		if (v === '0' || v === '1' || v === '2') {
			sel.classList.add('qs-f' + v);
		}
	}

	function remember(id, value) {
		if (!D) return;
		D[id] = value;
		persist();
	}

	function adoptAndApply() {
		var app = document.getElementById('qs-app');
		if (!app) return;

		var rev = app.getAttribute('data-rev') || '';
		D = loadDraft();
		if (D.rev && D.rev !== rev) {
			D = {};
		}
		D.rev = rev;
		D.avail = (D.avail && typeof D.avail === 'object') ? D.avail : {};

		var nameEl = document.getElementById('qs-name');
		var instEl = document.getElementById('qs-instrument');

		if (nameEl && !nameEl.value && D.name) {
			nameEl.value = D.name;
		}
		if (instEl && !instEl.value && D.instrument) {
			instEl.value = D.instrument;
		}
		D.name = nameEl ? nameEl.value : (D.name || '');
		D.instrument = instEl ? instEl.value : (D.instrument || '');

		var sels = document.querySelectorAll('select.qs-sel');
		Array.prototype.forEach.call(sels, function (sel) {
			var p = parseName(sel.getAttribute('name'));
			if (!p) return;
			if (
				D.avail[p.date] &&
				Object.prototype.hasOwnProperty.call(D.avail[p.date], p.slot)
			) {
				sel.value = D.avail[p.date][p.slot];
			} else {
				if (!D.avail[p.date]) {
					D.avail[p.date] = {};
				}
				D.avail[p.date][p.slot] = sel.value;
			}
			paint(sel);
		});
		persist();

		var form = document.querySelector('.qs-save-form');
		if (form) {
			form.addEventListener('submit', function () {
				if (!D) {
					D = { avail: {} };
				}
				if (nameEl) {
					D.name = nameEl.value;
				}
				if (instEl) {
					D.instrument = instEl.value;
				}
				var sels = form.querySelectorAll('select.qs-sel');
				Array.prototype.forEach.call(sels, function (sel) {
					var p = parseName(sel.getAttribute('name'));
					if (!p) return;
					if (!D.avail[p.date]) {
						D.avail[p.date] = {};
					}
					D.avail[p.date][p.slot] = sel.value;
				});
				var h = form.querySelector('input[name="qs_draft"]');
				if (!h) {
					h = document.createElement('input');
					h.type = 'hidden';
					h.name = 'qs_draft';
					form.appendChild(h);
				}
				h.value = JSON.stringify(D.avail);
				persist();
			});
		}
	}

	document.addEventListener('change', function (ev) {
		var sel = ev.target;
		if (!sel || !sel.matches || !sel.matches('select.qs-sel')) {
			return;
		}
		paint(sel);
		var p = parseName(sel.getAttribute('name'));
		if (p && D) {
			if (!D.avail[p.date]) {
				D.avail[p.date] = {};
			}
			D.avail[p.date][p.slot] = sel.value;
			persist();
		}
	});

	document.addEventListener('input', function (ev) {
		var t = ev.target;
		if (!t) return;
		if (t.id === 'qs-name') {
			remember('name', t.value);
		} else if (t.id === 'qs-instrument') {
			remember('instrument', t.value);
		}
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', adoptAndApply);
	} else {
		adoptAndApply();
	}
})();
