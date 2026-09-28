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

/* ---------- Pembelian ---------- */
(function () {
	'use strict';

	var rp = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

	// Total PO + info level approval
	var hint = document.getElementById('po-approval-hint');
	if (hint) {
		var form = hint.closest('form');
		var auto = parseFloat(hint.getAttribute('data-auto'));
		var owner = parseFloat(hint.getAttribute('data-owner'));
		var update = function () {
			var sub = 0;
			form.querySelectorAll('.doc-line').forEach(function (r) {
				var q = parseFloat(r.querySelector('.line-qty').value) || 0;
				var c = parseFloat(r.querySelector('.line-cost').value) || 0;
				sub += q * c;
			});
			var disc = parseFloat(form.querySelector('[name=discount_amount]').value) || 0;
			var tax = parseFloat(form.querySelector('[name=tax_amount]').value) || 0;
			var total = sub - disc + tax;
			document.getElementById('po-grand').textContent = total ? rp.format(total) : '-';
			hint.textContent = !total ? '' : (total < auto ? 'Otomatis disetujui saat submit'
				: (total > owner ? 'Butuh approval Manajer + Owner' : 'Butuh approval Manajer'));
		};
		form.addEventListener('input', update);
		form.addEventListener('change', update);
		update();
	}

	// Form pembayaran: tampilkan field sesuai metode
	var method = document.getElementById('method');
	if (method && document.getElementById('payment-form')) {
		var toggle = function () {
			document.querySelectorAll('#payment-form .pm').forEach(function (el) {
				el.style.display = el.classList.contains('pm-' + method.value) ? '' : 'none';
			});
		};
		method.addEventListener('change', toggle);
		toggle();
	}

	// Form invoice: cek kecocokan nilai secara langsung
	var amount = document.getElementById('amount');
	var check = document.getElementById('amount-check');
	if (amount && check && amount.hasAttribute('data-expected')) {
		var exp = parseFloat(amount.getAttribute('data-expected'));
		var tol = parseFloat(amount.getAttribute('data-tolerance')) / 100;
		var run = function () {
			var v = parseFloat(amount.value) || 0;
			var diff = v - exp;
			var ok = Math.abs(diff) <= Math.max(1, exp * tol);
			check.textContent = ok ? 'Cocok dengan barang yang diterima.' : 'Selisih ' + rp.format(diff) + ' dari nilai barang diterima.';
			check.className = 'form-text ' + (ok ? 'text-success' : 'text-danger');
		};
		amount.addEventListener('input', run);
		run();
	}

	// Form terima barang: tandai alasan selisih wajib saat jumlah beda dari sisa
	document.querySelectorAll('.gr-line').forEach(function (row) {
		var q = row.querySelector('.gr-qty');
		var reason = row.querySelector('.gr-reason');
		if (!q || !reason) return;
		var rem = parseFloat(row.getAttribute('data-remaining'));
		var run = function () {
			var v = parseFloat(q.value);
			var differs = q.value !== '' && v > 0 && Math.abs(v - rem) > 0.0005;
			reason.required = differs;
			reason.classList.toggle('is-invalid', differs && reason.value.trim() === '');
		};
		q.addEventListener('input', run);
		reason.addEventListener('input', run);
		run();
	});
})();

/* ---------- Menu ---------- */
(function () {
	'use strict';

	var rp = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

	// Salin baris terakhir dengan indeks name[...] baru (varian menu, isi paket promo)
	var cloneRow = function (box, rowSel) {
		var rows = box.querySelectorAll(rowSel);
		var last = rows[rows.length - 1];
		var clone = last.cloneNode(true);
		var next = rows.length + 100 + Math.floor(Math.random() * 1000);
		clone.querySelectorAll('[name]').forEach(function (el) {
			el.name = el.name.replace(/\[\d+\]/, '[' + next + ']');
			if (el.type === 'checkbox') { el.checked = true; }
			else if (el.name.indexOf('[qty]') > -1) { el.value = 1; }
			else if (el.type !== 'hidden') { el.value = ''; }
			else { el.value = 0; }
			if (el.id) { el.id = el.id + next; }
		});
		clone.querySelectorAll('label[for]').forEach(function (l) { l.setAttribute('for', l.getAttribute('for') + next); });
		clone.querySelectorAll('.bg-light').forEach(function (s) {
			var i = document.createElement('input');
			i.className = 'form-control form-control-sm';
			i.type = 'number'; i.min = 0; i.step = 'any'; i.placeholder = 'Harga';
			i.name = clone.querySelector('[name$="[name]"]').name.replace('[name]', '[price]');
			s.replaceWith(i);
		});
		box.appendChild(clone);
	};
	var addVariant = document.getElementById('add-variant');
	if (addVariant) addVariant.addEventListener('click', function () { cloneRow(document.getElementById('variant-rows'), '.variant-row'); });
	var addBundle = document.getElementById('add-bundle');
	if (addBundle) addBundle.addEventListener('click', function () { cloneRow(document.getElementById('bundle-rows'), '.bundle-row'); });

	// Form promo: tampilkan field sesuai jenis & cakupan
	var promo = document.getElementById('promo-form');
	if (promo) {
		var sync = function () {
			var type = promo.querySelector('#type').value;
			promo.querySelectorAll('.pt').forEach(function (el) { el.style.display = el.classList.contains('pt-' + type) ? '' : 'none'; });
			var scope = (promo.querySelector('[name=scope]:checked') || {}).value;
			promo.querySelectorAll('.scope').forEach(function (el) { el.style.display = el.classList.contains('scope-' + scope) ? '' : 'none'; });
		};
		promo.addEventListener('change', sync);
		sync();
	}

	// Resep: biaya per baris & COGS total (harga beli terakhir x qty x faktor)
	var doc = document.getElementById('stock-doc');
	if (doc && doc.getAttribute('data-direction') === 'recipe' && document.getElementById('recipe-total')) {
		var calc = function () {
			var total = 0;
			doc.querySelectorAll('.doc-line').forEach(function (row) {
				var sel = row.querySelector('.line-ingredient');
				var opt = sel.options[sel.selectedIndex];
				var unit = row.querySelector('.line-unit').selectedOptions[0];
				var cell = row.querySelector('.recipe-cost');
				var q = parseFloat(row.querySelector('.line-qty').value) || 0;
				if (!opt || !opt.value || !unit) { cell.textContent = ''; return; }
				var cost = q * parseFloat(unit.getAttribute('data-factor')) * (parseFloat(opt.getAttribute('data-price')) || 0);
				total += cost;
				cell.textContent = cost ? rp.format(cost) : (q ? 'tanpa harga' : '');
			});
			document.getElementById('recipe-total').textContent = total ? rp.format(total) : '-';
			var m = document.getElementById('recipe-margin');
			if (m) {
				var price = parseFloat(m.getAttribute('data-price'));
				m.textContent = price && total ? ((price - total) / price * 100).toFixed(1).replace('.', ',') + '%' : '-';
			}
		};
		doc.addEventListener('input', calc);
		doc.addEventListener('change', function () { setTimeout(calc, 0); });
		document.getElementById('add-line').addEventListener('click', function () { setTimeout(calc, 0); });
		setTimeout(calc, 0);
	}

	// Form harga: preview margin
	var priceInput = document.getElementById('price');
	var marginHint = document.getElementById('price-margin');
	if (priceInput && marginHint && priceInput.getAttribute('data-cogs')) {
		var cogs = parseFloat(priceInput.getAttribute('data-cogs'));
		var show = function () {
			var p = parseFloat(priceInput.value) || 0;
			marginHint.textContent = p && cogs ? 'Margin ' + ((p - cogs) / p * 100).toFixed(1).replace('.', ',') + '% (COGS ' + rp.format(cogs) + ')' : '';
		};
		priceInput.addEventListener('input', show);
		show();
	}
})();

/* ---------- Sidebar: grup buka/tutup, ciutkan (desktop), cari menu ---------- */
(function () {
	'use strict';

	var nav = document.getElementById('sidebar-nav');
	if (!nav) return;
	var root = document.documentElement;
	var store = {
		get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
		set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
	};

	// Grup yang ditutup user diingat; grup berisi halaman aktif selalu terbuka.
	var closed = [];
	try { closed = JSON.parse(store.get('rm.navClosed') || '[]') || []; } catch (e) { closed = []; }
	nav.querySelectorAll('.nav-group').forEach(function (g) {
		if (closed.indexOf(g.getAttribute('data-group')) !== -1 && !g.hasAttribute('data-has-active')) setOpen(g, false);
	});
	function setOpen(g, open) {
		g.classList.toggle('is-closed', !open);
		var btn = g.querySelector('.nav-group-toggle');
		if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
	}
	nav.addEventListener('click', function (e) {
		var btn = e.target.closest('.nav-group-toggle');
		if (!btn) return;
		var g = btn.closest('.nav-group');
		var open = g.classList.contains('is-closed');
		setOpen(g, open);
		var key = g.getAttribute('data-group');
		closed = closed.filter(function (k) { return k !== key; });
		if (!open) closed.push(key);
		store.set('rm.navClosed', JSON.stringify(closed));
	});

	// Ciutkan sidebar jadi ikon saja (desktop); label jadi tooltip.
	function syncTitles() {
		var collapsed = root.classList.contains('sb-collapsed');
		nav.querySelectorAll('.nav-link').forEach(function (a) {
			if (collapsed) a.setAttribute('title', a.getAttribute('data-label'));
			else a.removeAttribute('title');
		});
	}
	var toggle = document.getElementById('sb-toggle');
	if (toggle) {
		toggle.addEventListener('click', function () {
			var collapsed = root.classList.toggle('sb-collapsed');
			store.set('rm.sbCollapsed', collapsed ? '1' : '0');
			syncTitles();
		});
	}
	syncTitles();

	// Cari menu: saring item, buka semua grup yang cocok.
	var search = document.getElementById('nav-search');
	var empty = document.getElementById('nav-empty');
	if (search) {
		search.addEventListener('input', function () {
			var q = search.value.trim().toLowerCase();
			var any = false;
			nav.querySelectorAll('.nav-group').forEach(function (g) {
				var hits = 0;
				g.querySelectorAll('.nav-link').forEach(function (a) {
					var match = !q || a.getAttribute('data-label').toLowerCase().indexOf(q) !== -1;
					a.classList.toggle('is-hidden', !match);
					if (match) hits++;
				});
				g.classList.toggle('is-hidden', hits === 0);
				g.classList.toggle('is-searching', !!q);
				if (q && hits) g.classList.remove('is-closed');
				else if (!q) setOpen(g, closed.indexOf(g.getAttribute('data-group')) === -1 || g.hasAttribute('data-has-active'));
				if (hits) any = true;
			});
			empty.style.display = any ? '' : 'block';
		});
		search.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				var first = nav.querySelector('.nav-link:not(.is-hidden)');
				if (first) { e.preventDefault(); first.click(); }
			} else if (e.key === 'Escape') {
				search.value = '';
				search.dispatchEvent(new Event('input'));
				search.blur();
			}
		});
		// Tekan "/" di mana saja (bukan saat mengetik) untuk fokus ke pencarian menu.
		document.addEventListener('keydown', function (e) {
			if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
			var t = e.target;
			if (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) return;
			e.preventDefault();
			if (root.classList.contains('sb-collapsed')) { root.classList.remove('sb-collapsed'); store.set('rm.sbCollapsed', '0'); syncTitles(); }
			if (window.innerWidth < 992 && window.bootstrap) bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('sidebar')).show();
			search.focus();
		});
	}

	// Pastikan item aktif terlihat tanpa scroll manual.
	var active = nav.querySelector('.nav-link.active');
	if (active && active.offsetTop > nav.clientHeight - 60) nav.scrollTop = active.offsetTop - nav.clientHeight / 2;
})();
