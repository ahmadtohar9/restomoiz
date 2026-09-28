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

/* ---------- Inventory ---------- */
(function () {
	'use strict';

	var fmt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });
	var rp = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

	// Konfirmasi untuk tombol submit tertentu: <button data-confirm-click="Yakin?">
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-confirm-click]');
		if (btn && !window.confirm(btn.getAttribute('data-confirm-click'))) {
			e.preventDefault();
		}
	});

	// Tambah baris dengan menyalin baris terakhir: <button data-add-row="#container" data-row=".row-class">
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-add-row]');
		if (!btn) return;
		var box = document.querySelector(btn.getAttribute('data-add-row'));
		var rows = box.querySelectorAll(btn.getAttribute('data-row'));
		var clone = rows[rows.length - 1].cloneNode(true);
		clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
		box.appendChild(clone);
	});

	// Label satuan standar di baris satuan alternatif mengikuti input satuan.
	var unitInput = document.getElementById('unit');
	if (unitInput) {
		unitInput.addEventListener('input', function () {
			document.querySelectorAll('.unit-std-label').forEach(function (l) {
				l.textContent = unitInput.value || 'satuan standar';
			});
		});
	}

	// ---- Form dokumen stok multi-baris ----
	var doc = document.getElementById('stock-doc');
	if (doc) {
		var isOut = doc.getAttribute('data-direction') === 'out';
		var tbody = document.getElementById('doc-lines');
		var tpl = document.getElementById('line-template');
		var counter = tbody.querySelectorAll('.doc-line').length;
		var supplierSelect = document.getElementById('supplier_id');

		var selectedOpt = function (row) {
			var sel = row.querySelector('.line-ingredient');
			return sel.options[sel.selectedIndex];
		};

		var fillUnits = function (row, keep) {
			var opt = selectedOpt(row);
			var unitSel = row.querySelector('.line-unit');
			var wanted = keep ? (unitSel.value || unitSel.getAttribute('data-selected')) : '';
			unitSel.innerHTML = '';
			if (!opt || !opt.value) return;
			JSON.parse(opt.getAttribute('data-units')).forEach(function (u) {
				var o = document.createElement('option');
				o.value = u.unit;
				o.textContent = u.unit;
				o.setAttribute('data-factor', u.factor);
				if (u.unit === wanted) o.selected = true;
				unitSel.appendChild(o);
			});
		};

		var factor = function (row) {
			var o = row.querySelector('.line-unit').selectedOptions[0];
			return o ? parseFloat(o.getAttribute('data-factor')) : 1;
		};

		var refresh = function (row) {
			var opt = selectedOpt(row);
			var info = row.querySelector('.line-info');
			var qty = parseFloat(row.querySelector('.line-qty').value) || 0;
			info.textContent = '';
			info.classList.remove('text-danger');
			if (opt && opt.value) {
				var stock = parseFloat(opt.getAttribute('data-stock'));
				var unit = opt.getAttribute('data-unit');
				var std = qty * factor(row);
				var text = 'Stok: ' + fmt.format(stock) + ' ' + unit;
				if (qty && factor(row) !== 1) text += ' · = ' + fmt.format(std) + ' ' + unit;
				if (isOut && std > stock) {
					text += ' · stok tidak cukup';
					info.classList.add('text-danger');
				}
				info.textContent = text;
			}
			var costInput = row.querySelector('.line-cost');
			var totalCell = row.querySelector('.line-total');
			if (totalCell) {
				var cost = parseFloat(costInput && costInput.value) || 0;
				totalCell.textContent = qty && cost ? rp.format(qty * cost) : '';
			}
			var docTotal = document.getElementById('doc-total');
			if (docTotal) {
				var sum = 0;
				tbody.querySelectorAll('.doc-line').forEach(function (r) {
					var q = parseFloat(r.querySelector('.line-qty').value) || 0;
					var c = r.querySelector('.line-cost');
					sum += q * ((c && parseFloat(c.value)) || 0);
				});
				docTotal.textContent = sum ? rp.format(sum) : '-';
			}
		};

		var suggestCost = function (row) {
			var opt = selectedOpt(row);
			var costInput = row.querySelector('.line-cost');
			if (!costInput || !opt || !opt.value) return;
			var price = parseFloat(opt.getAttribute('data-price')) || 0;
			costInput.placeholder = price ? 'terakhir ' + fmt.format(Math.round(price * factor(row) * 100) / 100) : 'per satuan';
		};

		var bind = function (row) {
			fillUnits(row, true);
			refresh(row);
			suggestCost(row);
		};

		tbody.querySelectorAll('.doc-line').forEach(bind);

		tbody.addEventListener('change', function (e) {
			var row = e.target.closest('.doc-line');
			if (!row) return;
			if (e.target.classList.contains('line-ingredient')) {
				fillUnits(row, false);
				var sup = selectedOpt(row).getAttribute('data-supplier');
				if (!isOut && supplierSelect && !supplierSelect.value && sup && sup !== '0') supplierSelect.value = sup;
			}
			refresh(row);
			suggestCost(row);
		});
		tbody.addEventListener('input', function (e) {
			var row = e.target.closest('.doc-line');
			if (row) refresh(row);
		});
		tbody.addEventListener('click', function (e) {
			var btn = e.target.closest('.line-remove');
			if (!btn) return;
			if (tbody.querySelectorAll('.doc-line').length > 1) {
				btn.closest('.doc-line').remove();
				refresh(tbody.querySelector('.doc-line'));
			}
		});
		document.getElementById('add-line').addEventListener('click', function () {
			var html = tpl.innerHTML.replace(/__i__/g, 'n' + (counter++));
			tbody.insertAdjacentHTML('beforeend', html);
			var row = tbody.lastElementChild;
			bind(row);
			row.querySelector('.line-ingredient').focus();
		});
	}

	// ---- Selisih opname dihitung langsung ----
	document.querySelectorAll('.opname-count').forEach(function (input) {
		input.addEventListener('input', function () {
			var cell = input.closest('tr').querySelector('.opname-variance');
			if (input.value === '') { cell.textContent = ''; return; }
			var v = parseFloat(input.value) - parseFloat(input.getAttribute('data-system'));
			v = Math.round(v * 1000) / 1000;
			cell.textContent = (v > 0 ? '+' : '') + fmt.format(v);
			cell.className = 'text-end opname-variance ' + (v < 0 ? 'text-danger' : (v > 0 ? 'text-success' : ''));
		});
	});
})();
