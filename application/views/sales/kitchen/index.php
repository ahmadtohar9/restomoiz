<?php defined('BASEPATH') OR exit('No direct script access allowed'); $CI =& get_instance(); ?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
	<div class="btn-group btn-group-sm" role="group" id="k-filter">
		<input type="radio" class="btn-check" name="kf" id="kf-all" value="all" <?= $is_kitchen ? 'checked' : '' ?>><label class="btn btn-outline-secondary" for="kf-all">Semua</label>
		<input type="radio" class="btn-check" name="kf" id="kf-cook" value="cook"><label class="btn btn-outline-secondary" for="kf-cook">Perlu dimasak</label>
		<input type="radio" class="btn-check" name="kf" id="kf-ready" value="ready" <?= $is_kitchen ? '' : 'checked' ?>><label class="btn btn-outline-secondary" for="kf-ready">Siap diantar</label>
	</div>
	<div class="form-check form-switch ms-2"><input class="form-check-input" type="checkbox" id="k-sound" checked><label class="form-check-label small" for="k-sound">Bunyi notifikasi</label></div>
	<span class="ms-auto small text-muted">Diperbarui <span id="k-time">-</span> · otomatis tiap <?= (int) $refresh ?> detik</span>
</div>
<div id="k-board" class="kitchen-board"><div class="text-muted">Memuat…</div></div>

<style>
	.kitchen-board { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: .75rem; align-items: start; }
	.k-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .6rem; overflow: hidden; }
	.k-card .k-head { padding: .5rem .75rem; display: flex; gap: .5rem; align-items: center; background: #f3f4f6; }
	.k-card.late .k-head { background: #fee2e2; }
	.k-item { padding: .45rem .75rem; border-top: 1px solid #f1f1f1; display: flex; gap: .5rem; align-items: flex-start; }
	.k-item.is-ready { background: #ecfeff; }
	.k-item .k-name { font-weight: 600; }
	.k-item .k-sub { font-size: .8rem; color: #6b7280; }
	.k-new { animation: kflash 1.2s ease 2; }
	@keyframes kflash { 50% { background: #fef3c7; } }
</style>
<script>
(function () {
	var feedUrl = <?= json_encode(site_url('sales/kitchen/feed')) ?>;
	var statusUrl = <?= json_encode(site_url('sales/kitchen/status')) ?>;
	var csrf = { name: <?= json_encode($CI->security->get_csrf_token_name()) ?>, value: <?= json_encode($CI->security->get_csrf_hash()) ?> };
	var isKitchen = <?= $is_kitchen ? 'true' : 'false' ?>;
	var refresh = <?= (int) $refresh ?> * 1000;
	var seen = null, seenReady = null, data = [];
	var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
	var filter = function () { return document.querySelector('#k-filter input:checked').value; };
	var labels = { pending: 'Baru', preparing: 'Dimasak', ready: 'Siap' };
	var colors = { pending: 'secondary', preparing: 'warning', ready: 'info' };

	function ding(freq) {
		if (!document.getElementById('k-sound').checked) return;
		try {
			var ctx = new (window.AudioContext || window.webkitAudioContext)();
			[0, 0.18].forEach(function (t) {
				var o = ctx.createOscillator(), g = ctx.createGain();
				o.frequency.value = freq; g.gain.value = 0.08; o.connect(g); g.connect(ctx.destination);
				o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.12);
			});
		} catch (e) {}
	}

	function render() {
		var f = filter(), html = '';
		data.forEach(function (o) {
			var items = o.items.filter(function (i) { return f === 'all' || (f === 'cook' ? i.status !== 'ready' : i.status === 'ready'); });
			if (!items.length) return;
			var oldest = Math.max.apply(null, o.items.map(function (i) { return i.minutes; }));
			html += '<div class="k-card' + (oldest >= 20 && f !== 'ready' ? ' late' : '') + '"><div class="k-head"><strong>' + (o.table ? 'Meja ' + esc(o.table) : esc(o.type)) + '</strong>'
				+ '<span class="small text-muted">' + esc(o.number) + (o.customer ? ' · ' + esc(o.customer) : '') + '</span>'
				+ '<span class="ms-auto small ' + (oldest >= 20 ? 'text-danger fw-semibold' : '') + '">' + oldest + ' mnt</span></div>';
			items.forEach(function (i) {
				var isNew = seen && !seen[i.id];
				html += '<div class="k-item' + (i.status === 'ready' ? ' is-ready' : '') + (isNew ? ' k-new' : '') + '"><div class="flex-fill"><div class="k-name">' + i.qty + '× ' + esc(i.name) + '</div>'
					+ (i.modifiers.length ? '<div class="k-sub">+ ' + i.modifiers.map(esc).join(', ') + '</div>' : '')
					+ (i.notes ? '<div class="k-sub fst-italic">' + esc(i.notes) + '</div>' : '')
					+ '<span class="badge text-bg-' + colors[i.status] + '">' + labels[i.status] + '</span></div><div class="d-flex flex-column gap-1">';
				if (isKitchen && i.status === 'pending') html += '<button class="btn btn-sm btn-outline-warning" data-item="' + i.id + '" data-st="preparing">Masak</button>';
				if (isKitchen && i.status !== 'ready') html += '<button class="btn btn-sm btn-info" data-item="' + i.id + '" data-st="ready">Siap</button>';
				if (i.status === 'ready') html += '<button class="btn btn-sm btn-success" data-item="' + i.id + '" data-st="served">Diantar</button>';
				html += '</div></div>';
			});
			if (isKitchen && f !== 'ready' && items.some(function (i) { return i.status !== 'ready'; })) {
				html += '<div class="k-item"><button class="btn btn-sm btn-outline-info w-100" data-order="' + o.id + '" data-st="ready">Semua siap</button></div>';
			}
			html += '</div>';
		});
		document.getElementById('k-board').innerHTML = html || '<div class="text-muted">Tidak ada pesanan untuk ditampilkan.</div>';
	}

	function load() {
		fetch(feedUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
			var ids = {}, readyIds = {}, hasNew = false, hasReady = false;
			d.orders.forEach(function (o) { o.items.forEach(function (i) {
				ids[i.id] = true; if (i.status === 'ready') readyIds[i.id] = true;
				if (seen && !seen[i.id]) hasNew = true;
				if (seenReady && i.status === 'ready' && !seenReady[i.id]) hasReady = true;
			}); });
			if (hasNew && isKitchen) ding(880);
			if (hasReady && !isKitchen) ding(660);
			data = d.orders; render();
			seen = ids; seenReady = readyIds;
			document.getElementById('k-time').textContent = d.server_time;
		}).catch(function () { document.getElementById('k-time').textContent = 'gagal memuat'; });
	}

	document.getElementById('k-board').addEventListener('click', function (e) {
		var b = e.target.closest('[data-st]'); if (!b) return;
		b.disabled = true;
		var body = new URLSearchParams();
		body.append(csrf.name, csrf.value); body.append('status', b.getAttribute('data-st'));
		if (b.hasAttribute('data-item')) body.append('item_id', b.getAttribute('data-item'));
		if (b.hasAttribute('data-order')) body.append('order_id', b.getAttribute('data-order'));
		fetch(statusUrl, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
			.then(function (r) { return r.json(); }).then(function (r) { if (!r.ok) alert(r.message); load(); });
	});
	document.getElementById('k-filter').addEventListener('change', render);
	load(); setInterval(load, refresh);
})();
</script>
