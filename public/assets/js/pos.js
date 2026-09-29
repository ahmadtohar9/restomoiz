/**
 * Layar POS. Keranjang dikelola di browser; harga, promo, pajak, dan total
 * yang sah selalu dari server (/pos/quote & /pos/submit).
 */
(function () {
	'use strict';

	var P = window.POS;
	var $ = function (id) { return document.getElementById(id); };
	var rp = function (n) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0); };
	var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

	var state = {
		catalog: null, byBarcode: {}, variants: {}, cat: 0, search: '',
		cart: [], orderId: 0, order: null, existing: [],
		type: 'takeaway', tableId: '', customer: null, codes: [], quote: null, method: 'cash'
	};
	var modals = {};
	var quoteTimer = null;
	var quoteSeq = 0;

	// ---------------- util ----------------
	function post(url, data) {
		var body = new URLSearchParams();
		body.append(P.csrf.name, P.csrf.value);
		body.append('payload', JSON.stringify(data));
		return fetch(url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
			.then(function (r) {
				if (r.status === 401) { location.href = P.urls.catalog.replace(/pos\/catalog$/, 'login'); }
				return r.json();
			});
	}
	function get(url) {
		return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	// Konfirmasi (SweetAlert2 bila tersedia). msg kosong/false = tidak perlu tanya.
	function ask(msg) {
		if (!msg) return Promise.resolve(true);
		return window.UI ? UI.confirm(msg) : Promise.resolve(window.confirm(msg));
	}
	function toast(msg, type) {
		if (window.UI && window.Swal) { UI.toast(msg, { danger: 'error', success: 'success', warning: 'warning' }[type] || 'info', 3500); return; }
		var t = $('toast');
		t.className = 'toast align-items-center border-0 text-bg-' + (type || 'dark');
		$('toast-body').textContent = msg;
		bootstrap.Toast.getOrCreateInstance(t, { delay: 3500 }).show();
	}
	function beep() {
		try {
			var ctx = new (window.AudioContext || window.webkitAudioContext)();
			var o = ctx.createOscillator(); var g = ctx.createGain();
			o.frequency.value = 880; g.gain.value = 0.05; o.connect(g); g.connect(ctx.destination);
			o.start(); o.stop(ctx.currentTime + 0.08);
		} catch (e) { /* abaikan */ }
	}

	// ---------------- katalog ----------------
	function loadCatalog() {
		return get(P.urls.catalog).then(function (c) {
			state.catalog = c;
			state.byBarcode = {};
			state.variants = {};
			c.menus.forEach(function (m) {
				m.variants.forEach(function (v) {
					state.variants[v.id] = { menu: m, variant: v };
					if (v.barcode) state.byBarcode[v.barcode] = v.id;
				});
			});
			$('row-service').hidden = !(c.settings.service_rate > 0);
			$('row-tax').hidden = !c.settings.tax_enabled;
			renderCats(); renderGrid(); renderTables(); renderOpenOrders(); renderPayMethods();
		});
	}

	function catDescendants(id) {
		var ids = [id]; var changed = true;
		while (changed) {
			changed = false;
			state.catalog.categories.forEach(function (c) {
				if (ids.indexOf(c.parent_id) > -1 && ids.indexOf(c.id) === -1) { ids.push(c.id); changed = true; }
			});
		}
		return ids;
	}

	function renderCats() {
		var count = function (id) { var ids = catDescendants(id); return state.catalog.menus.filter(function (m) { return ids.indexOf(m.category_id) > -1; }).length; };
		var html = '<button type="button" class="pos-chip' + (state.cat === 0 ? ' is-active' : '') + '" data-cat="0"><i class="bi bi-grid"></i> Semua <span class="pos-chip-n">' + state.catalog.menus.length + '</span></button>';
		state.catalog.categories.forEach(function (c) {
			if (c.depth > 1) return;
			var n = count(c.id);
			if (!n) return;
			html += '<button type="button" class="pos-chip' + (c.depth ? ' is-sub' : '') + (state.cat === c.id ? ' is-active' : '') + '" data-cat="' + c.id + '">' + esc(c.name) + ' <span class="pos-chip-n">' + n + '</span></button>';
		});
		$('categories').innerHTML = html;
	}

	function effPrice(v, qtyTotal) {
		var p = v.price;
		if (state.customer && state.customer.is_member && v.member_price != null) p = Math.min(p, v.member_price);
		if (v.bulk_min_qty && qtyTotal >= v.bulk_min_qty) p = Math.min(p, v.bulk_price);
		return p;
	}

	function renderGrid() {
		var q = state.search.toLowerCase();
		var cats = state.cat ? catDescendants(state.cat) : null;
		var html = '';
		state.catalog.menus.forEach(function (m) {
			if (cats && cats.indexOf(m.category_id) === -1) return;
			if (q && m.name.toLowerCase().indexOf(q) === -1 && m.code.toLowerCase().indexOf(q) === -1) return;
			var prices = m.variants.map(function (v) { return effPrice(v, 1); });
			var min = Math.min.apply(null, prices), max = Math.max.apply(null, prices);
			var allOut = m.variants.every(function (v) { return v.avail === 'out' || v.avail === 'manual_out'; });
			var low = m.variants.some(function (v) { return v.avail === 'low'; });
			var hue = (m.category_id * 47 + 210) % 360;
			var initials = m.name.split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toUpperCase();
			html += '<button type="button" class="menu-card' + (allOut ? ' is-out' : '') + '" data-menu="' + m.id + '"' + (allOut ? ' disabled' : '') + ' style="--hue:' + hue + '">'
				+ '<span class="mc-media">' + (m.image ? '<img src="' + esc(m.image) + '" alt="" loading="lazy">' : '<span class="mc-initials">' + esc(initials) + '</span>')
				+ (allOut ? '<span class="mc-flag is-out">Habis</span>' : (low ? '<span class="mc-flag is-low">Stok tipis</span>' : ''))
				+ '<span class="mc-count" hidden></span></span>'
				+ '<span class="mc-body"><span class="mc-name">' + esc(m.name) + '</span>'
				+ '<span class="mc-meta">' + (m.variants.length > 1 ? m.variants.length + ' varian' : '&nbsp;') + '</span>'
				+ '<span class="mc-price">' + rp(min) + (max > min ? '<small> – ' + rp(max) + '</small>' : '') + '</span></span>'
				+ '</button>';
		});
		$('menu-grid').innerHTML = html || '<div class="pos-empty"><i class="bi bi-search"></i><div>Tidak ada menu yang cocok.</div></div>';
		cardCounts();
	}

	// Jumlah porsi di keranjang per menu -> lencana di kartu menu.
	function cardCounts() {
		var per = {};
		state.cart.forEach(function (c) { var r = state.variants[c.variant_id]; if (r) per[r.menu.id] = (per[r.menu.id] || 0) + c.qty; });
		document.querySelectorAll('#menu-grid [data-menu]').forEach(function (b) {
			var n = per[b.getAttribute('data-menu')] || 0, el = b.querySelector('.mc-count');
			if (!el) return;
			el.hidden = !n; el.textContent = n;
			b.classList.toggle('in-cart', !!n);
		});
	}

	function renderTables() {
		var sel = $('table-select');
		var html = '<option value="">— Pilih meja —</option>';
		state.catalog.tables.forEach(function (t) {
			var busy = t.order_id && (!state.orderId || +t.order_id !== state.orderId);
			html += '<option value="' + t.id + '"' + (busy ? ' data-order="' + t.order_id + '"' : '') + (String(state.tableId) === String(t.id) ? ' selected' : '') + '>'
				+ esc(t.name) + (t.area ? ' · ' + esc(t.area) : '') + (busy ? ' (terisi ' + esc(t.order_number) + ')' : '') + '</option>';
		});
		sel.innerHTML = html;
	}

	function renderOpenOrders() {
		var html = '<option value="">Pesanan terbuka (' + state.catalog.open_orders.length + ')…</option>';
		state.catalog.open_orders.forEach(function (o) {
			html += '<option value="' + o.id + '"' + (+o.id === state.orderId ? ' selected' : '') + '>' + esc(o.order_number) + ' · '
				+ (o.table_name ? 'Meja ' + esc(o.table_name) : esc(o.customer_name || o.order_type)) + ' · ' + rp(o.subtotal) + '</option>';
		});
		$('open-orders').innerHTML = html;
	}

	// ---------------- keranjang ----------------
	function variantLabel(ref) {
		return ref.menu.name + (ref.variant.name !== 'Reguler' ? ' - ' + ref.variant.name : '');
	}

	function addToCart(variantId, qty, mods, notes) {
		var ref = state.variants[variantId];
		if (!ref) return;
		if (ref.variant.avail === 'out' || ref.variant.avail === 'manual_out') { toast(variantLabel(ref) + ' sedang habis.', 'danger'); return; }
		mods = (mods || []).slice().sort();
		notes = notes || '';
		var same = state.cart.find(function (c) { return c.variant_id === variantId && c.notes === notes && c.modifiers.join(',') === mods.join(','); });
		if (same) { same.qty += qty; } else { state.cart.push({ variant_id: variantId, qty: qty, modifiers: mods, notes: notes }); }
		beep();
		renderCart(); scheduleQuote();
	}

	function lineUnitPrice(c) {
		var ref = state.variants[c.variant_id];
		var total = state.cart.filter(function (x) { return x.variant_id === c.variant_id; }).reduce(function (s, x) { return s + x.qty; }, 0);
		var modSum = c.modifiers.reduce(function (s, id) {
			var m = state.catalog.modifiers.find(function (x) { return x.id === id; });
			return s + (m ? m.price : 0);
		}, 0);
		return effPrice(ref.variant, total) + modSum;
	}

	function renderCart() {
		var html = '';
		state.existing.forEach(function (it) {
			var st = { pending: 'Baru', preparing: 'Dimasak', ready: 'Siap', served: 'Diantar', void: 'Batal' }[it.kitchen_status];
			html += '<div class="cart-line existing' + (it.kitchen_status === 'void' ? ' is-void' : '') + '"><div class="cl-main"><div class="cl-name">' + esc(it.name) + '</div>'
				+ '<div class="cl-sub">' + (it.modifiers || []).map(function (m) { return '+ ' + esc(m.name); }).join(', ') + (it.notes ? ' · ' + esc(it.notes) : '')
				+ ' <span class="cl-status st-' + it.kitchen_status + '">' + st + '</span></div></div>'
				+ '<div class="cl-qty"><span class="cl-qty-fixed">' + it.qty + '×</span></div><div class="cl-total">' + rp(it.line_total) + '</div></div>';
		});
		if (state.existing.length) html = '<div class="cart-sep">Sudah dipesan</div>' + html;
		if (state.existing.length && state.cart.length) html += '<div class="cart-sep">Tambahan baru</div>';
		var disc = state.quote && state.quote.cart_discounts ? state.quote.cart_discounts : {};
		state.cart.forEach(function (c, i) {
			var ref = state.variants[c.variant_id];
			var unit = lineUnitPrice(c);
			var mods = c.modifiers.map(function (id) { var m = state.catalog.modifiers.find(function (x) { return x.id === id; }); return m ? '+ ' + esc(m.name) : ''; }).join(', ');
			html += '<div class="cart-line"><div class="cl-main"><a href="#" class="cl-name" data-edit="' + i + '" title="Ubah varian / tambahan / catatan">' + esc(variantLabel(ref)) + ' <i class="bi bi-pencil"></i></a>'
				+ '<div class="cl-sub">' + rp(unit) + (mods ? ' · ' + mods : '') + (c.notes ? ' · <em>' + esc(c.notes) + '</em>' : '') + '</div>'
				+ (disc[i] ? '<div class="cl-sub cl-promo">promo −' + rp(disc[i]) + '</div>' : '') + '</div>'
				+ '<div class="cl-qty"><button type="button" class="cl-step" data-dec="' + i + '" aria-label="Kurangi"><i class="bi ' + (c.qty > 1 ? 'bi-dash' : 'bi-trash3') + '"></i></button><span class="cl-n">' + c.qty
				+ '</span><button type="button" class="cl-step" data-inc="' + i + '" aria-label="Tambah"><i class="bi bi-plus"></i></button></div>'
				+ '<div class="cl-total">' + rp(unit * c.qty) + '</div></div>';
		});
		$('cart-items').innerHTML = html || '<div class="pos-empty"><i class="bi bi-basket2"></i><div class="fw-semibold">Keranjang masih kosong</div><div class="small">Ketuk menu atau scan barcode untuk menambah.</div></div>';
		cardCounts();
		var n = state.cart.reduce(function (s, c) { return s + c.qty; }, 0) + state.existing.filter(function (i) { return i.kitchen_status !== 'void'; }).reduce(function (s, i) { return s + (+i.qty); }, 0);
		if ($('cart-count')) { $('cart-count').textContent = n; $('cart-count').hidden = !n; }
		if ($('fab-count')) $('fab-count').textContent = n + ' item';
		if ($('pos-fab')) $('pos-fab').hidden = !n;
		$('btn-kitchen').disabled = state.cart.length === 0;
		if ($('btn-pay')) $('btn-pay').disabled = state.cart.length === 0 && state.existing.filter(function (i) { return i.kitchen_status !== 'void'; }).length === 0;
		$('cart-title').textContent = state.order ? state.order.order_number : 'Pesanan baru';
		$('cart-sub').textContent = state.order ? ({ dine_in: 'Dine-in', takeaway: 'Takeaway', delivery: 'Delivery' }[state.order.order_type]
			+ (state.order.table_name ? ' · Meja ' + state.order.table_name : '') + (state.order.customer_name ? ' · ' + state.order.customer_name : '')) : (state.customer ? state.customer.name + (state.customer.is_member ? ' (member)' : '') : '');
		$('codes').innerHTML = state.codes.map(function (c, i) { return '<span class="pos-code"><i class="bi bi-ticket-perforated"></i> ' + esc(c) + ' <a href="#" data-uncode="' + i + '" aria-label="Hapus kode">×</a></span>'; }).join('');
		if (!state.cart.length && !state.existing.length) renderTotals(null);
	}

	function renderTotals(q) {
		$('t-sub').textContent = rp(q ? q.subtotal : 0);
		$('t-service').textContent = rp(q ? q.service_charge : 0);
		$('t-tax').textContent = rp(q ? q.tax : 0);
		$('t-total').textContent = rp(q ? q.total : 0);
		if ($('btn-pay-amt')) $('btn-pay-amt').textContent = q && q.total ? rp(q.total) : '';
		if ($('fab-total')) $('fab-total').textContent = rp(q ? q.total : 0);
		$('t-promos').innerHTML = q ? q.applied.map(function (a) { return '<tr class="t-promo"><td><i class="bi bi-tag"></i> ' + esc(a.name) + '</td><td class="text-end">−' + rp(a.discount) + '</td></tr>'; }).join('') : '';
		$('warnings').innerHTML = q && q.warnings ? q.warnings.map(esc).join('<br>') : '';
	}

	function payload(extra) {
		var d = {
			order_id: state.orderId || null,
			header: {
				order_type: state.type, table_id: state.tableId || null,
				customer_id: state.customer ? state.customer.id : null,
				customer_name: $('cust-name').value || (state.customer ? state.customer.name : ''),
				customer_phone: $('cust-phone').value || (state.customer ? state.customer.phone || '' : ''),
				delivery_address: $('cust-address').value, delivery_time: $('delivery-time').value || null,
				notes: $('order-notes').value
			},
			cart: state.cart.map(function (c) { return { variant_id: c.variant_id, qty: c.qty, modifiers: c.modifiers, notes: c.notes }; }),
			codes: state.codes
		};
		for (var k in extra) d[k] = extra[k];
		return d;
	}

	function scheduleQuote(method) {
		clearTimeout(quoteTimer);
		quoteTimer = setTimeout(function () { requestQuote(method); }, 250);
	}

	function requestQuote(method) {
		if (!state.cart.length && !state.existing.length) { state.quote = null; renderTotals(null); return Promise.resolve(null); }
		var seq = ++quoteSeq;
		return post(P.urls.quote, payload({ payment: { method: method || null } })).then(function (q) {
			if (seq !== quoteSeq) return null; // respons lama, abaikan
			if (!q.ok) { toast(q.message, 'danger'); return null; }
			state.quote = q.empty ? null : q;
			renderTotals(state.quote); renderCart();
			return state.quote;
		});
	}

	// ---------------- modal item ----------------
	var im = { menu: null, variant: null, editIndex: -1 };
	function openItem(menu, editIndex) {
		im.menu = menu; im.editIndex = editIndex;
		var c = editIndex > -1 ? state.cart[editIndex] : null;
		im.variant = c ? c.variant_id : (menu.variants.find(function (v) { return v.avail !== 'out' && v.avail !== 'manual_out'; }) || menu.variants[0]).id;
		$('im-title').textContent = menu.name;
		$('im-variants').innerHTML = menu.variants.length > 1 ? menu.variants.map(function (v) {
			var out = v.avail === 'out' || v.avail === 'manual_out';
			return '<button type="button" class="im-opt' + (v.id === im.variant ? ' is-active' : '') + '" data-variant="' + v.id + '"' + (out ? ' disabled' : '') + '>'
				+ '<span class="im-opt-name">' + esc(v.name) + '</span><span class="im-opt-price">' + rp(effPrice(v, 1)) + (out ? ' · habis' : '') + '</span></button>';
		}).join('') : '';
		$('im-qty').value = c ? c.qty : 1;
		$('im-notes').value = c ? c.notes : '';
		var mods = state.catalog.modifiers;
		$('im-mods-wrap').hidden = !mods.length;
		$('im-mods').innerHTML = mods.map(function (m) {
			var on = c && c.modifiers.indexOf(m.id) > -1;
			return '<input type="checkbox" class="btn-check" id="mod' + m.id + '" value="' + m.id + '"' + (on ? ' checked' : '') + '>'
				+ '<label class="im-chip" for="mod' + m.id + '"><i class="bi bi-plus-circle"></i> ' + esc(m.name) + (m.price ? ' <b>+' + rp(m.price) + '</b>' : '') + '</label>';
		}).join('');
		$('im-save').textContent = editIndex > -1 ? 'Simpan' : 'Tambah';
		updateItemPrice();
		modals.item.show();
	}
	function selectedMods() {
		return Array.prototype.map.call($('im-mods').querySelectorAll('input:checked'), function (i) { return +i.value; });
	}
	function updateItemPrice() {
		var ref = state.variants[im.variant];
		var mods = selectedMods().reduce(function (s, id) { var m = state.catalog.modifiers.find(function (x) { return x.id === id; }); return s + (m ? m.price : 0); }, 0);
		$('im-price').textContent = rp((effPrice(ref.variant, 1) + mods) * (parseInt($('im-qty').value, 10) || 1));
	}

	// ---------------- pesanan terbuka ----------------
	function loadOrder(id) {
		return get(P.urls.order + '/' + id).then(function (r) {
			if (!r.order) { toast(r.message || 'Pesanan tidak ditemukan.', 'danger'); return; }
			if (r.order.status !== 'open') { location.href = P.urls.orderPage + '/' + id; return; }
			state.orderId = +r.order.id; state.order = r.order; state.existing = r.items;
			state.type = r.order.order_type; state.tableId = r.order.table_id || '';
			document.querySelector('#ot-' + state.type).checked = true;
			syncTypeFields(); renderTables(); renderOpenOrders(); renderCart(); requestQuote();
		});
	}

	function resetOrder() {
		state.cart = []; state.orderId = 0; state.order = null; state.existing = []; state.codes = []; state.quote = null;
		state.customer = null; state.tableId = '';
		['cust-name', 'cust-phone', 'cust-address', 'delivery-time', 'order-notes', 'customer-search', 'promo-code'].forEach(function (id) { $(id).value = ''; });
		renderTables(); renderOpenOrders(); renderGrid(); renderCart(); renderTotals(null);
		history.replaceState(null, '', location.pathname);
		$('search').focus();
	}

	function syncTypeFields() {
		var locked = !!state.orderId;
		$('table-select').hidden = state.type !== 'dine_in';
		$('delivery-fields').hidden = state.type !== 'delivery';
		document.querySelectorAll('#order-type input').forEach(function (i) { i.disabled = locked; });
		$('table-select').disabled = locked;
	}

	// ---------------- pembayaran ----------------
	function renderPayMethods() {
		var html = '';
		Object.keys(state.catalog.payment_methods).forEach(function (k) {
			var icon = { cash: 'cash-stack', debit: 'credit-card', credit: 'credit-card-2-front', ewallet: 'qr-code-scan' }[k] || 'wallet2';
			html += '<input type="radio" class="btn-check" name="pm" id="pm-' + k + '" value="' + k + '"' + (k === state.method ? ' checked' : '') + '>'
				+ '<label class="pm-tile" for="pm-' + k + '"><i class="bi bi-' + icon + '"></i><span>' + esc(state.catalog.payment_methods[k]) + '</span></label>';
		});
		$('pay-methods').innerHTML = html;
		$('card-type').innerHTML = state.catalog.card_types.map(function (t) { return '<option>' + esc(t) + '</option>'; }).join('');
	}
	function syncPayFields() {
		document.querySelectorAll('#payModal [data-method]').forEach(function (el) {
			el.hidden = el.getAttribute('data-method').split(' ').indexOf(state.method) === -1;
		});
	}
	function cashQuick(total) {
		var opts = [total];
		[5000, 10000, 20000, 50000, 100000].forEach(function (step) {
			var v = Math.ceil(total / step) * step;
			if (opts.indexOf(v) === -1 && v > total) opts.push(v);
		});
		$('cash-quick').innerHTML = opts.slice(0, 5).map(function (v, i) {
			return '<button type="button" class="cash-chip' + (i === 0 ? ' is-exact' : '') + '" data-cash="' + v + '">' + (i === 0 ? 'Uang pas' : rp(v)) + '</button>';
		}).join('');
	}
	function updateChange() {
		var total = state.quote ? state.quote.total : 0;
		var paid = parseFloat($('pay-cash').value) || 0;
		$('pay-change').textContent = paid >= total ? rp(paid - total) : 'kurang ' + rp(total - paid);
		$('pay-change').className = paid >= total ? '' : 'text-danger';
	}
	function openPay() {
		if (!P.hasShift) { toast('Buka shift kasir dulu sebelum menerima pembayaran.', 'warning'); return; }
		$('pay-error').hidden = true;
		requestQuote(state.method).then(function (q) {
			if (!q) return;
			$('pay-total').textContent = rp(q.total);
			$('pay-note').textContent = q.applied.length ? 'Termasuk promo: ' + q.applied.map(function (a) { return a.name; }).join(', ') : '';
			$('pay-cash').value = '';
			cashQuick(q.total); updateChange(); syncPayFields();
			modals.pay.show();
			setTimeout(function () { (state.method === 'cash' ? $('pay-cash') : $('card-last4')).focus(); }, 300);
		});
	}

	function submit(action) {
		var btns = [$('btn-kitchen'), $('btn-pay'), $('pay-confirm')].filter(Boolean);
		btns.forEach(function (b) { b.disabled = true; });
		var extra = { action: action };
		if (action === 'pay') {
			extra.payment = { method: state.method, paid_amount: parseFloat($('pay-cash').value) || 0, card_type: $('card-type').value,
				card_last4: $('card-last4').value, approval_code: $('approval-code').value, payment_ref: $('payment-ref').value };
		}
		var busy = window.UI ? UI.loading : null;
		if (busy) busy.start({ overlay: true, immediate: true, text: action === 'pay' ? 'Memproses pembayaran…' : 'Mengirim pesanan…' });
		var finish = function () { if (busy) busy.done(); };
		return post(P.urls.submit, payload(extra)).then(function (r) { finish(); return r; }, function (err) { finish(); throw err; }).then(function (r) {
			btns.forEach(function (b) { b.disabled = false; });
			if (!r.ok) {
				if (action === 'pay') { $('pay-error').textContent = r.message; $('pay-error').hidden = false; } else { toast(r.message, 'danger'); }
				renderCart();
				return;
			}
			if (modals.pay) modals.pay.hide();
			$('done-title').textContent = r.message;
			$('done-change').textContent = r.change != null && r.status === 'paid' && state.method === 'cash' ? 'Kembalian ' + rp(r.change) : '';
			$('done-receipt').href = r.receipt_url; $('done-receipt').hidden = r.status !== 'paid';
			$('done-ticket').href = r.ticket_url;
			['card-last4', 'approval-code', 'payment-ref'].forEach(function (id) { $(id).value = ''; });
			modals.done.show();
			state.cart = [];
			loadCatalog();
		}).catch(function () {
			btns.forEach(function (b) { b.disabled = false; });
			toast('Koneksi gagal. Periksa jaringan lalu coba lagi.', 'danger');
		});
	}

	// ---------------- event ----------------
	document.addEventListener('DOMContentLoaded', function () {
		modals.item = new bootstrap.Modal($('itemModal'));
		modals.pay = new bootstrap.Modal($('payModal'));
		modals.done = new bootstrap.Modal($('doneModal'));
		if (P.flash) toast(P.flash.message, P.flash.type === 'danger' ? 'danger' : 'success');
		setInterval(function () { $('clock').textContent = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }); }, 1000);

		loadCatalog().then(function () {
			syncTypeFields();
			if (P.orderId) loadOrder(P.orderId); else renderCart();
		});

		$('categories').addEventListener('click', function (e) {
			var b = e.target.closest('[data-cat]'); if (!b) return;
			state.cat = +b.getAttribute('data-cat'); renderCats(); renderGrid();
		});
		$('menu-grid').addEventListener('click', function (e) {
			var b = e.target.closest('[data-menu]'); if (!b || b.disabled) return;
			var m = state.catalog.menus.find(function (x) { return x.id === +b.getAttribute('data-menu'); });
			if (m.variants.length === 1 && !state.catalog.modifiers.length) addToCart(m.variants[0].id, 1, [], '');
			else openItem(m, -1);
		});
		$('search').addEventListener('input', function (e) { state.search = e.target.value.trim(); renderGrid(); });
		$('search').addEventListener('keydown', function (e) {
			if (e.key !== 'Enter') return;
			e.preventDefault();
			var code = e.target.value.trim();
			if (state.byBarcode[code]) { addToCart(state.byBarcode[code], 1, [], ''); }
			else {
				var hits = state.catalog.menus.filter(function (m) { return m.name.toLowerCase().indexOf(code.toLowerCase()) > -1; });
				if (hits.length === 1 && hits[0].variants.length === 1) addToCart(hits[0].variants[0].id, 1, [], '');
				else if (hits.length === 1) openItem(hits[0], -1);
				else if (code) { toast('Barcode / menu "' + code + '" tidak ditemukan.', 'warning'); }
			}
			e.target.value = ''; state.search = ''; renderGrid();
		});

		$('cart-items').addEventListener('click', function (e) {
			var t = e.target.closest('[data-inc],[data-dec],[data-edit]'); if (!t) return;
			e.preventDefault();
			if (t.hasAttribute('data-inc')) state.cart[+t.getAttribute('data-inc')].qty++;
			if (t.hasAttribute('data-dec')) {
				var i = +t.getAttribute('data-dec');
				if (--state.cart[i].qty < 1) state.cart.splice(i, 1);
			}
			if (t.hasAttribute('data-edit')) {
				var idx = +t.getAttribute('data-edit');
				openItem(state.variants[state.cart[idx].variant_id].menu, idx);
				return;
			}
			renderCart(); scheduleQuote();
		});

		$('im-variants').addEventListener('click', function (e) {
			var b = e.target.closest('[data-variant]'); if (!b) return;
			im.variant = +b.getAttribute('data-variant');
			$('im-variants').querySelectorAll('[data-variant]').forEach(function (x) { x.classList.toggle('is-active', +x.getAttribute('data-variant') === im.variant); });
			updateItemPrice();
		});
		$('im-minus').addEventListener('click', function () { $('im-qty').value = Math.max(1, (parseInt($('im-qty').value, 10) || 1) - 1); updateItemPrice(); });
		$('im-plus').addEventListener('click', function () { $('im-qty').value = Math.min(999, (parseInt($('im-qty').value, 10) || 1) + 1); updateItemPrice(); });
		$('im-qty').addEventListener('input', updateItemPrice);
		$('im-mods').addEventListener('change', updateItemPrice);
		$('im-chips').addEventListener('click', function (e) {
			var b = e.target.closest('button'); if (!b) return;
			var n = $('im-notes'); n.value = n.value ? n.value + ', ' + b.textContent : b.textContent;
		});
		$('im-save').addEventListener('click', function () {
			var qty = Math.max(1, Math.min(999, parseInt($('im-qty').value, 10) || 1));
			var mods = selectedMods().sort(); var notes = $('im-notes').value.trim();
			if (im.editIndex > -1) {
				state.cart[im.editIndex] = { variant_id: im.variant, qty: qty, modifiers: mods, notes: notes };
				renderCart(); scheduleQuote();
			} else {
				addToCart(im.variant, qty, mods, notes);
			}
			modals.item.hide();
			$('search').focus();
		});

		document.querySelectorAll('#order-type input').forEach(function (i) {
			i.addEventListener('change', function () { state.type = i.value; syncTypeFields(); });
		});
		$('table-select').addEventListener('change', function (e) {
			var opt = e.target.selectedOptions[0];
			if (opt && opt.getAttribute('data-order')) {
				var sel = e.target;
				ask(state.cart.length && 'Meja ini masih ada pesanan. Buka pesanan tersebut? Keranjang saat ini akan dipindahkan ke pesanan itu.').then(function (ok) {
					if (!ok) { sel.value = state.tableId; return; }
					var cart = state.cart;
					loadOrder(opt.getAttribute('data-order')).then(function () { state.cart = cart; renderCart(); requestQuote(); });
				});
				return;
			}
			state.tableId = e.target.value;
		});
		$('open-orders').addEventListener('change', function (e) {
			var sel = e.target, id = sel.value;
			if (!id) return;
			ask(state.cart.length && 'Keranjang saat ini akan ditambahkan ke pesanan yang dipilih. Lanjutkan?').then(function (ok) {
				if (!ok) { sel.value = ''; return; }
				var cart = state.cart;
				loadOrder(id).then(function () { state.cart = cart; renderCart(); requestQuote(); });
			});
		});
		$('btn-new').addEventListener('click', function () {
			ask((state.cart.length || state.orderId) && 'Kosongkan layar dan mulai pesanan baru? Item yang belum dikirim akan hilang.').then(function (ok) { if (ok) resetOrder(); });
		});

		// Pelanggan
		var custTimer;
		$('customer-search').addEventListener('input', function (e) {
			clearTimeout(custTimer);
			var q = e.target.value.trim();
			if (q.length < 2) { $('customer-results').innerHTML = ''; return; }
			custTimer = setTimeout(function () {
				get(P.urls.customers + '?q=' + encodeURIComponent(q)).then(function (rows) {
					$('customer-results').innerHTML = rows.length ? rows.map(function (c) {
						return '<button type="button" class="list-group-item list-group-item-action py-1" data-cust=\'' + esc(JSON.stringify(c)).replace(/'/g, '&#39;') + '\'>'
							+ esc(c.name) + ' <small class="text-muted">' + esc(c.phone || '') + '</small>' + (+c.is_member ? ' <span class="badge text-bg-warning">member</span>' : '') + '</button>';
					}).join('') : '<div class="list-group-item small text-muted">Tidak ditemukan. Isi nama/HP di kolom delivery atau tambah di menu Pelanggan.</div>';
				});
			}, 250);
		});
		$('customer-results').addEventListener('click', function (e) {
			var b = e.target.closest('[data-cust]'); if (!b) return;
			var c = JSON.parse(b.getAttribute('data-cust'));
			c.id = +c.id; c.is_member = +c.is_member === 1;
			state.customer = c;
			$('customer-search').value = c.name + (c.is_member ? ' ★' : '');
			$('cust-name').value = c.name; $('cust-phone').value = c.phone || ''; $('cust-address').value = c.address || '';
			$('customer-results').innerHTML = '';
			renderGrid(); renderCart(); scheduleQuote();
		});
		document.addEventListener('click', function (e) { if (!e.target.closest('#customer-results, #customer-search')) $('customer-results').innerHTML = ''; });

		// Kode promo
		var addCode = function () {
			var c = $('promo-code').value.trim().toUpperCase();
			if (c && state.codes.indexOf(c) === -1) state.codes.push(c);
			$('promo-code').value = '';
			renderCart();
			requestQuote().then(function (q) {
				if (!q) return;
				var used = q.applied.some(function (a) { return true; }) && q.considered.some(function (x) { return x.code && x.code.toUpperCase() === c && x.status === 'applied'; });
				var info = q.considered.find(function (x) { return x.code && x.code.toUpperCase() === c; });
				if (c && !info) toast('Kode ' + c + ' tidak dikenal atau promonya tidak aktif.', 'warning');
				else if (c && !used) toast('Kode ' + c + ': ' + (info.reason || 'belum memenuhi syarat'), 'warning');
			});
		};
		$('btn-code').addEventListener('click', addCode);
		$('promo-code').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addCode(); } });
		$('codes').addEventListener('click', function (e) {
			var a = e.target.closest('[data-uncode]'); if (!a) return;
			e.preventDefault(); state.codes.splice(+a.getAttribute('data-uncode'), 1); renderCart(); scheduleQuote();
		});

		$('btn-kitchen').addEventListener('click', function () {
			if (state.type === 'dine_in' && !state.orderId && !state.tableId) { toast('Pilih meja untuk pesanan dine-in.', 'warning'); return; }
			submit('kitchen');
		});
		if ($('btn-pay')) $('btn-pay').addEventListener('click', function () {
			if (state.type === 'dine_in' && !state.orderId && !state.tableId) { toast('Pilih meja untuk pesanan dine-in.', 'warning'); return; }
			openPay();
		});
		$('pay-methods').addEventListener('change', function (e) {
			state.method = e.target.value; syncPayFields();
			requestQuote(state.method).then(function (q) { if (q) { $('pay-total').textContent = rp(q.total); cashQuick(q.total); updateChange(); } });
		});
		$('pay-cash').addEventListener('input', updateChange);
		$('cash-quick').addEventListener('click', function (e) {
			var b = e.target.closest('[data-cash]'); if (!b) return;
			$('pay-cash').value = b.getAttribute('data-cash'); updateChange();
		});
		$('pay-confirm').addEventListener('click', function () { submit('pay'); });
		$('payModal').addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); submit('pay'); } });
		$('done-new').addEventListener('click', function () { modals.done.hide(); resetOrder(); });
	});
})();
