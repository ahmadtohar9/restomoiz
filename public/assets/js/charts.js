/**
 * Grafik SVG ringan untuk laporan (tanpa library). Satu seri per grafik:
 *  - Charts.line(el, {labels, values, format, name})  garis + area, crosshair & tooltip
 *  - Charts.bars(el, {items:[{label, value, note}], format})  batang horizontal + label nilai
 *  - Charts.cells(el)  tooltip untuk sel heatmap (td[data-tip])
 * Teks dimasukkan dengan textContent (label berasal dari data).
 * Responsif: SVG digambar seukuran lebar kontainer (1 unit = 1 px) sehingga teks tetap
 * terbaca di HP, dan digambar ulang saat lebar berubah (rotasi layar, sidebar diciutkan).
 */
window.Charts = (function () {
	'use strict';
	var NS = 'http://www.w3.org/2000/svg';
	var fmt = {
		rp: function (v) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v); },
		num: function (v) { return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(v); },
		pct: function (v) { return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(v) + '%'; }
	};
	var compact = function (v, f) {
		if (f !== 'rp') return fmt.num(v);
		var a = Math.abs(v);
		if (a >= 1e9) return 'Rp' + fmt.num(v / 1e9) + 'M';
		if (a >= 1e6) return 'Rp' + fmt.num(v / 1e6) + 'jt';
		if (a >= 1e3) return 'Rp' + fmt.num(v / 1e3) + 'rb';
		return 'Rp' + fmt.num(v);
	};
	function el(tag, attrs, parent) {
		var e = document.createElementNS(NS, tag);
		for (var k in attrs) e.setAttribute(k, attrs[k]);
		if (parent) parent.appendChild(e);
		return e;
	}
	function niceMax(v) {
		if (v <= 0) return 1;
		var p = Math.pow(10, Math.floor(Math.log10(v))), n = v / p;
		return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p;
	}
	function tip(host) {
		var t = document.createElement('div'); t.className = 'viz-tip'; t.hidden = true; host.appendChild(t);
		// Di layar sentuh tooltip tetap tampil setelah diketuk; ketuk di luar grafik untuk menutup.
		document.addEventListener('pointerdown', function (e) { if (!host.contains(e.target)) t.hidden = true; });
		return {
			show: function (x, y, value, label) {
				t.textContent = '';
				var s = document.createElement('strong'); s.textContent = value;
				var k = document.createElement('span'); var key = document.createElement('i'); key.className = 'key';
				k.appendChild(key); k.appendChild(document.createTextNode(label));
				t.appendChild(s); t.appendChild(k);
				t.hidden = false;
				// Jaga tooltip tetap di dalam kontainer (penting di layar sempit).
				var half = t.offsetWidth / 2, maxX = host.clientWidth - half;
				t.style.left = Math.max(half, Math.min(maxX, x)) + 'px';
				t.style.top = Math.max(t.offsetHeight + 10, y) + 'px';
			},
			hide: function () { t.hidden = true; }
		};
	}

	function width(host) { return Math.max(260, Math.round(host.clientWidth || 720)); }
	// Gambar ulang saat lebar kontainer berubah.
	function responsive(host, draw) {
		host._draw = draw; host._w = width(host);
		if (host._ro || !window.ResizeObserver) return;
		var timer;
		host._ro = new ResizeObserver(function () {
			clearTimeout(timer);
			timer = setTimeout(function () { if (Math.abs(width(host) - host._w) > 4) { host._w = width(host); host._draw(); } }, 120);
		});
		host._ro.observe(host);
	}

	function line(host, o) {
		host.classList.add('viz'); host.textContent = '';
		responsive(host, function () { line(host, o); });
		var f = o.format || 'rp', vals = o.values, n = vals.length;
		if (!n) { host.textContent = 'Tidak ada data.'; return; }
		var max = niceMax(Math.max.apply(null, vals));
		var narrow = host._w < 480;
		var W = host._w, H = o.height ? (narrow ? Math.max(o.height, 200) : o.height) : Math.round(Math.min(300, Math.max(200, W * 0.33)));
		var longest = 0;
		for (var gi = 0; gi <= 4; gi++) longest = Math.max(longest, compact(max * gi / 4, f).length);
		var L = Math.round(longest * 6.2) + 12, R = narrow ? 8 : 12, T = 12, B = 26;
		var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': o.name || 'Grafik' }, host);
		var x = function (i) { return L + (n === 1 ? (W - L - R) / 2 : i * (W - L - R) / (n - 1)); };
		var y = function (v) { return T + (H - T - B) * (1 - v / max); };
		for (var g = 0; g <= 4; g++) {
			var gv = max * g / 4, gy = y(gv);
			el('line', { x1: L, x2: W - R, y1: gy, y2: gy, 'class': 'v-grid' }, svg);
			var tx = el('text', { x: L - 8, y: gy + 4, 'text-anchor': 'end', 'class': 'v-axis' }, svg); tx.textContent = compact(gv, f);
		}
		// Jumlah label sumbu-x menyesuaikan lebar (±1 label per 56 px), label terakhir selalu tampil.
		var fit = Math.max(2, Math.floor((W - L - R) / 56)), step = Math.max(1, Math.ceil(n / fit));
		o.labels.forEach(function (lb, i) {
			if (i === n - 1 ? false : (i % step || (n - 1 - i) < step * 0.6)) return;
			var t = el('text', { x: x(i), y: H - 8, 'text-anchor': 'middle', 'class': 'v-axis' }, svg); t.textContent = lb;
		});
		var d = vals.map(function (v, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1); }).join(' ');
		el('path', { d: d + ' L' + x(n - 1) + ' ' + y(0) + ' L' + x(0) + ' ' + y(0) + ' Z', 'class': 'v-area' }, svg);
		el('path', { d: d, 'class': 'v-line' }, svg);
		var cross = el('line', { y1: T, y2: H - B, 'class': 'v-cross', visibility: 'hidden' }, svg);
		var dot = el('circle', { r: 5, 'class': 'v-dot', visibility: 'hidden' }, svg);
		var hit = el('rect', { x: L, y: T, width: W - L - R, height: H - T - B, fill: 'transparent', tabindex: 0, style: 'touch-action: pan-y' }, svg);
		var tp = tip(host);
		var at = function (i) {
			var px = x(i), py = y(vals[i]), scale = host.clientWidth / W;
			cross.setAttribute('x1', px); cross.setAttribute('x2', px); cross.setAttribute('visibility', 'visible');
			dot.setAttribute('cx', px); dot.setAttribute('cy', py); dot.setAttribute('visibility', 'visible');
			tp.show(px * scale, py * scale, (fmt[f] || fmt.num)(vals[i]), o.labels[i] + (o.name ? ' · ' + o.name : ''));
		};
		var cur = n - 1;
		hit.addEventListener('pointerdown', function (e) {
			var r = hit.getBoundingClientRect(), rel = (e.clientX - r.left) / r.width;
			cur = Math.max(0, Math.min(n - 1, Math.round(rel * (n - 1)))); at(cur);
		});
		hit.addEventListener('pointermove', function (e) {
			var r = hit.getBoundingClientRect(), rel = (e.clientX - r.left) / r.width;
			cur = Math.max(0, Math.min(n - 1, Math.round(rel * (n - 1)))); at(cur);
		});
		hit.addEventListener('pointerleave', function (e) { if (e.pointerType === 'touch') return; tp.hide(); cross.setAttribute('visibility', 'hidden'); dot.setAttribute('visibility', 'hidden'); });
		hit.addEventListener('focus', function () { at(cur); });
		hit.addEventListener('blur', function () { tp.hide(); });
		hit.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { cur = Math.min(n - 1, cur + 1); at(cur); e.preventDefault(); }
			if (e.key === 'ArrowLeft') { cur = Math.max(0, cur - 1); at(cur); e.preventDefault(); }
		});
	}

	function bars(host, o) {
		var items = o.items, f = o.format || 'rp';
		host.classList.add('viz'); host.textContent = '';
		responsive(host, function () { bars(host, o); });
		if (!items.length) { host.textContent = 'Tidak ada data.'; return; }
		var W = host._w;
		// Layar sempit: label di atas batang (batang memakai lebar penuh).
		var stacked = W < 480;
		var LW = stacked ? 0 : Math.min(190, Math.round(W * 0.3)), VW = stacked ? 64 : 84;
		var row = stacked ? 44 : 30, barH = stacked ? 12 : 14, H = items.length * row + 4;
		var maxChars = stacked ? Math.floor(W / 7) : Math.floor(LW / 7);
		var max = Math.max.apply(null, items.map(function (i) { return i.value; })) || 1;
		var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': o.name || 'Grafik batang' }, host);
		var tp = tip(host);
		items.forEach(function (it, i) {
			var yc = stacked ? i * row + 30 : i * row + row / 2, w = Math.max(2, (W - LW - VW) * it.value / max);
			var lb = stacked ? el('text', { x: 0, y: i * row + 15, 'class': 'v-label' }, svg) : el('text', { x: LW - 10, y: yc + 4, 'text-anchor': 'end', 'class': 'v-label' }, svg);
			lb.textContent = it.label.length > maxChars ? it.label.slice(0, maxChars - 1) + '…' : it.label;
			var bar = el('rect', { x: LW, y: yc - barH / 2, width: w, height: barH, rx: 4, 'class': 'v-bar' }, svg);
			// Ujung kiri rata ke baseline: tutup lengkung kiri dengan persegi kecil.
			el('rect', { x: LW, y: yc - barH / 2, width: Math.min(4, w), height: barH, 'class': 'v-bar' }, svg);
			var val = el('text', { x: LW + w + 8, y: yc + 4, 'class': 'v-value' }, svg); val.textContent = compact(it.value, f);
			var hit = el('rect', { x: 0, y: i * row, width: W, height: row, fill: 'transparent', tabindex: 0 }, svg);
			var show = function () {
				bar.classList.add('is-hover');
				var scale = host.clientWidth / W;
				tp.show((LW + w / 2) * scale, (yc - barH / 2) * scale, (fmt[f] || fmt.num)(it.value) + (it.note ? ' · ' + it.note : ''), it.label);
			};
			var hide = function () { bar.classList.remove('is-hover'); tp.hide(); };
			hit.addEventListener('pointerenter', show); hit.addEventListener('pointerleave', hide);
			hit.addEventListener('focus', show); hit.addEventListener('blur', hide);
		});
	}

	function cells(host) {
		host.style.position = 'relative';
		var tp = tip(host);
		host.querySelectorAll('td[data-tip]').forEach(function (td) {
			var show = function () {
				var hr = host.getBoundingClientRect(), r = td.getBoundingClientRect();
				tp.show(r.left - hr.left + r.width / 2, r.top - hr.top, td.getAttribute('data-v'), td.getAttribute('data-tip'));
			};
			td.tabIndex = 0;
			td.addEventListener('pointerenter', show); td.addEventListener('focus', show);
			td.addEventListener('pointerleave', tp.hide); td.addEventListener('blur', tp.hide);
		});
	}

	return { line: line, bars: bars, cells: cells, fmt: fmt };
})();
