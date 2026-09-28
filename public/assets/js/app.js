(function () {
	'use strict';

	// Konfirmasi sebelum submit form berbahaya: <form data-confirm="Yakin?">
	document.addEventListener('submit', function (e) {
		var msg = e.target.getAttribute('data-confirm');
		if (msg && !window.confirm(msg)) {
			e.preventDefault();
		}
	});

	document.addEventListener('click', function (e) {
		var el = e.target.closest('[data-check-all],[data-uncheck-all],[data-toggle-group]');
		if (!el) return;
		e.preventDefault();

		if (el.hasAttribute('data-toggle-group')) {
			var boxes = document.querySelectorAll('.perm-box[data-group="' + el.getAttribute('data-toggle-group') + '"]');
			var allChecked = Array.prototype.every.call(boxes, function (b) { return b.checked; });
			boxes.forEach(function (b) { b.checked = !allChecked; });
			return;
		}
		var check = el.hasAttribute('data-check-all');
		var selector = el.getAttribute(check ? 'data-check-all' : 'data-uncheck-all');
		document.querySelectorAll(selector).forEach(function (b) { b.checked = check; });
	});

	// Filter cepat baris tabel: <input class="table-filter" data-target="#tabel">
	document.querySelectorAll('.table-filter').forEach(function (input) {
		input.addEventListener('input', function () {
			var q = input.value.toLowerCase();
			document.querySelectorAll(input.getAttribute('data-target') + ' tbody tr').forEach(function (tr) {
				tr.style.display = tr.textContent.toLowerCase().indexOf(q) === -1 ? 'none' : '';
			});
		});
	});
})();
