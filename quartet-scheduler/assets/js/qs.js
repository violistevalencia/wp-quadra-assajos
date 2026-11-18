/* Programant Haydn — Quadra d'assajos
   Tota la interactivitat (pestanyes, setmanes, dies, desar) funciona sense JS:
   són enllaços i un formulari POST real. Aquest script només pinta de color
   la llista desplegable en canviar-la, com a millora estètica. */
(function () {
	'use strict';

	function paint(sel) {
		var v = sel.value;
		sel.classList.remove('qs-f0', 'qs-f1', 'qs-f2');
		if (v === '0' || v === '1' || v === '2') {
			sel.classList.add('qs-f' + v);
		}
	}

	function paintAll(scope) {
		var sels = (scope || document).querySelectorAll('select.qs-sel');
		Array.prototype.forEach.call(sels, paint);
	}

	document.addEventListener('change', function (ev) {
		if (ev.target && ev.target.matches && ev.target.matches('select.qs-sel')) {
			paint(ev.target);
		}
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			paintAll(document);
		});
	} else {
		paintAll(document);
	}
})();
