/**
 * Lapisan UI global (semua aset lokal, jalan offline):
 *  - UI.loading: progress bar atas + overlay spinner saat pindah halaman, submit form, dan fetch()
 *  - UI.confirm / UI.alert / UI.toast: dialog SweetAlert2 (fallback ke dialog bawaan browser)
 *      <form data-confirm="Yakin?">, <button data-confirm-click="Yakin?">, flash message -> toast
 *  - DataTables otomatis untuk tabel daftar (cari, urut, halaman, responsif di HP)
 *      opt-out: <table data-dt="false">, paksa: data-dt="true", paging server: data-dt-paging="false"
 */
window.UI = (function () {
	'use strict';

	var hasSwal = function () { return typeof window.Swal !== 'undefined'; };
	var css = getComputedStyle(document.documentElement);
	var primary = (css.getPropertyValue('--bs-primary') || '#4f46e5').trim();
	var danger = (css.getPropertyValue('--bs-danger') || '#dc2626').trim();

	/* ---------------- Loading: progress bar + overlay ---------------- */
	var bar = document.createElement('div');
	bar.id = 'ui-progress';
	bar.innerHTML = '<span></span>';
	var overlay = document.createElement('div');
	overlay.id = 'ui-overlay';
	overlay.innerHTML = '<div class="ui-overlay-box"><span class="ui-spinner" aria-hidden="true"></span><span class="ui-overlay-text">Memproses…</span></div>';
	overlay.setAttribute('role', 'status');
	overlay.setAttribute('aria-live', 'polite');
	document.addEventListener('DOMContentLoaded', function () { document.body.appendChild(bar); document.body.appendChild(overlay); });

	var pending = 0, showTimer = null, overlayTimer = null, safety = null;
	function start(opts) {
		opts = opts || {};
		pending++;
		clearTimeout(showTimer);
		// Tunda sedikit: proses cepat (< 250 ms) tidak perlu berkedip.
		showTimer = setTimeout(function () { if (pending > 0) bar.classList.add('is-active'); }, opts.immediate ? 0 : 250);
		if (opts.overlay) {
			overlay.querySelector('.ui-overlay-text').textContent = opts.text || 'Memproses…';
			clearTimeout(overlayTimer);
			overlayTimer = setTimeout(function () { if (pending > 0) overlay.classList.add('is-active'); }, 400);
		}
		// Pengaman: unduhan file / navigasi batal tidak meninggalkan spinner selamanya.
		clearTimeout(safety);
		safety = setTimeout(reset, opts.timeout || 20000);
	}
	function done() {
		pending = Math.max(0, pending - 1);
		if (pending === 0) hide();
	}
	function hide() {
		clearTimeout(showTimer); clearTimeout(overlayTimer);
		bar.classList.remove('is-active'); overlay.classList.remove('is-active');
	}
	function reset() {
		pending = 0; hide();
		document.querySelectorAll('.is-loading').forEach(function (b) {
			b.classList.remove('is-loading'); b.removeAttribute('aria-disabled');
			if (b.dataset.uiHtml !== undefined) { b.innerHTML = b.dataset.uiHtml; delete b.dataset.uiHtml; }
			b.style.minWidth = '';
		});
		document.querySelectorAll('form[data-ui-submitting]').forEach(function (f) { f.removeAttribute('data-ui-submitting'); });
	}
	// Kembali lewat tombol Back (bfcache): bersihkan status loading.
	window.addEventListener('pageshow', reset);

	function buttonLoading(btn, text) {
		if (!btn || btn.classList.contains('is-loading')) return;
		btn.dataset.uiHtml = btn.innerHTML;
		btn.style.minWidth = btn.offsetWidth + 'px';
		btn.classList.add('is-loading');
		btn.setAttribute('aria-disabled', 'true');
		btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' + (text ? ' <span>' + text + '</span>' : '');
	}

	// fetch(): progress bar otomatis. Lewati dengan header 'X-Silent: 1' (mis. polling layar dapur).
	if (window.fetch) {
		var origFetch = window.fetch;
		window.fetch = function (input, init) {
			var silent = false;
			try {
				var h = init && init.headers;
				silent = !!(h && (typeof h.get === 'function' ? h.get('X-Silent') : (h['X-Silent'] || h['x-silent'])));
			} catch (e) { silent = false; }
			if (silent) return origFetch.apply(this, arguments);
			start({ timeout: 60000 });
			return origFetch.apply(this, arguments).then(function (r) { done(); return r; }, function (err) { done(); throw err; });
		};
	}

	// Klik tautan ke halaman lain di aplikasi -> progress bar.
	document.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
		var a = e.target.closest('a[href]');
		if (!a || a.target || a.hasAttribute('download') || a.hasAttribute('data-bs-toggle') || a.hasAttribute('data-no-loader')) return;
		var href = a.getAttribute('href');
		if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) return;
		var url;
		try { url = new URL(a.href, location.href); } catch (err) { return; }
		if (url.origin !== location.origin) return;
		if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
		// Ekspor CSV / file: browser tetap di halaman ini.
		if (/[?&]export=|\/export\b|\/(receipt|ticket|print)\b|\/files\/view\//.test(url.pathname + url.search)) return;
		start({ immediate: true });
	});

	/* ---------------- Dialog: SweetAlert2 ---------------- */
	var swalBase = function () {
		return Swal.mixin({
			customClass: { popup: 'ui-swal', confirmButton: 'btn btn-primary', cancelButton: 'btn btn-light', denyButton: 'btn btn-outline-danger', actions: 'ui-swal-actions' },
			buttonsStyling: false,
			reverseButtons: true,
			focusCancel: false
		});
	};
	function confirmDialog(message, opts) {
		opts = opts || {};
		if (!hasSwal()) return Promise.resolve(window.confirm(message));
		var dangerous = opts.danger !== undefined ? opts.danger : /hapus|batal|tolak|void|nonaktif|kosongkan|reset|hilang/i.test(message);
		return swalBase().fire({
			title: opts.title || (dangerous ? 'Yakin?' : 'Konfirmasi'),
			text: message,
			icon: opts.icon || (dangerous ? 'warning' : 'question'),
			showCancelButton: true,
			confirmButtonText: opts.confirmText || 'Ya, lanjutkan',
			cancelButtonText: opts.cancelText || 'Batal',
			customClass: { popup: 'ui-swal', confirmButton: 'btn ' + (dangerous ? 'btn-danger' : 'btn-primary'), cancelButton: 'btn btn-light', actions: 'ui-swal-actions' }
		}).then(function (r) { return !!r.isConfirmed; });
	}
	function alertDialog(message, icon, title) {
		if (!hasSwal()) { window.alert(message); return Promise.resolve(); }
		return swalBase().fire({ title: title || (icon === 'error' ? 'Gagal' : icon === 'success' ? 'Berhasil' : 'Informasi'), text: message, icon: icon || 'info', confirmButtonText: 'OK' });
	}
	function toast(message, icon, ms) {
		if (!hasSwal()) return;
		Swal.mixin({
			toast: true, position: 'top-end', showConfirmButton: false, showCloseButton: true,
			timer: ms || (icon === 'error' ? 7000 : 4000), timerProgressBar: true,
			customClass: { popup: 'ui-toast' },
			didOpen: function (t) { t.addEventListener('mouseenter', Swal.stopTimer); t.addEventListener('mouseleave', Swal.resumeTimer); }
		}).fire({ icon: icon || 'success', title: message });
	}

	// <form data-confirm="..."> : konfirmasi, lalu submit ulang (spinner tetap jalan).
	document.addEventListener('submit', function (e) {
		var form = e.target;
		var msg = form.getAttribute('data-confirm');
		if (msg && form.dataset.uiConfirmed !== '1') {
			e.preventDefault();
			var submitter = e.submitter;
			confirmDialog(msg).then(function (ok) {
				if (!ok) return;
				form.dataset.uiConfirmed = '1';
				if (form.requestSubmit) form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
				else { onSubmit(form, submitter); form.submit(); }
				delete form.dataset.uiConfirmed;
			});
			return;
		}
	}, true);

	// <button data-confirm-click="..."> : konfirmasi sebelum aksi tombol dijalankan.
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-confirm-click]');
		if (!btn || btn.dataset.uiConfirmed === '1') return;
		e.preventDefault(); e.stopImmediatePropagation();
		confirmDialog(btn.getAttribute('data-confirm-click')).then(function (ok) {
			if (!ok) return;
			btn.dataset.uiConfirmed = '1';
			btn.click();
			delete btn.dataset.uiConfirmed;
		});
	}, true);

	// Submit form -> spinner di tombol + progress bar; cegah submit ganda.
	function onSubmit(form, submitter) {
		if (form.target && form.target !== '_self') return;
		var post = (form.getAttribute('method') || 'get').toLowerCase() === 'post';
		form.setAttribute('data-ui-submitting', '1');
		if (!submitter || submitter.form !== form) submitter = form.querySelector('button[type=submit], button:not([type]), input[type=submit]');
		if (submitter && submitter.tagName === 'BUTTON' && !submitter.hasAttribute('data-no-loader')) buttonLoading(submitter, post ? '' : '');
		start({ immediate: true, overlay: post, text: post ? 'Menyimpan…' : 'Memuat…' });
	}
	document.addEventListener('submit', function (e) {
		if (e.defaultPrevented) return;
		var form = e.target;
		if (form.hasAttribute('data-no-loader')) return;
		if (form.hasAttribute('data-ui-submitting')) { e.preventDefault(); return; }
		onSubmit(form, e.submitter);
	});

	/* ---------------- Flash message -> toast ---------------- */
	document.addEventListener('DOMContentLoaded', function () {
		if (!hasSwal()) return;
		document.querySelectorAll('[data-flash]').forEach(function (el) {
			var type = el.getAttribute('data-flash');
			var icon = { success: 'success', danger: 'error', warning: 'warning', info: 'info' }[type] || 'info';
			toast(el.getAttribute('data-message') || el.textContent.trim(), icon);
			el.remove();
		});
	});

	/* ---------------- DataTables otomatis ---------------- */
	var MONTHS = { jan: 1, feb: 2, mar: 3, apr: 4, mei: 5, may: 5, jun: 6, jul: 7, agu: 8, agt: 8, aug: 8, sep: 9, okt: 10, oct: 10, nov: 11, des: 12, dec: 12 };
	var strip = function (d) { var t = document.createElement('div'); t.innerHTML = d == null ? '' : String(d); return (t.textContent || '').replace(/\s+/g, ' ').trim(); };
	var numRe = /^(?:Rp\.?\s?)?[-−]?(?:Rp\.?\s?)?\d{1,3}(?:\.\d{3})*(?:,\d+)?(?:\s?(?:%|×|x|[a-zA-Z]{1,8}))?$|^[-−]?\d+(?:,\d+)?(?:\s?(?:%|×|x|[a-zA-Z]{1,8}))?$/;
	var dateRe = /^(\d{1,2})[ \/-]([A-Za-z]{3,})[a-z]*[ \/-](\d{4})(?:[ ,]+(\d{1,2}):(\d{2}))?/;
	var dmyRe = /^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:[ ,]+(\d{1,2}):(\d{2}))?/;
	function parseNum(s) {
		var neg = /^[-−]|Rp\.?\s?[-−]/.test(s);
		var m = s.replace(/Rp\.?\s?/, '').replace(/[-−]/, '').match(/[\d.]+(?:,\d+)?/);
		if (!m) return -Infinity;
		var v = parseFloat(m[0].replace(/\./g, '').replace(',', '.'));
		return neg ? -v : v;
	}
	function parseDate(s) {
		var m = s.match(dateRe);
		if (m && MONTHS[m[2].slice(0, 3).toLowerCase()]) return new Date(+m[3], MONTHS[m[2].slice(0, 3).toLowerCase()] - 1, +m[1], +(m[4] || 0), +(m[5] || 0)).getTime();
		m = s.match(dmyRe);
		if (m) return new Date(+m[3], +m[2] - 1, +m[1], +(m[4] || 0), +(m[5] || 0)).getTime();
		return null;
	}
	var isEmpty = function (s) { return s === '' || s === '-' || s === '—'; };

	function registerTypes() {
		var DT = window.DataTable;
		if (!DT || registerTypes.done) return;
		registerTypes.done = true;
		// Tanggal Indonesia: "29 Sep 2026 12:10", "29/09/2026" (baris pertama sel).
		DT.type('tgl-id', {
			// allOf: semua sel cocok (atau kosong); oneOf: minimal satu sel benar-benar tanggal.
			detect: {
				allOf: function (d) { var s = strip(d); return isEmpty(s) || parseDate(s) !== null; },
				oneOf: function (d) { return parseDate(strip(d)) !== null; }
			},
			order: { pre: function (d) { var v = parseDate(strip(d)); return v === null ? -Infinity : v; } }
		});
		// Angka/Rupiah format Indonesia: "Rp 1.250.000", "4,7 kg", "12%".
		DT.type('num-id', {
			detect: {
				allOf: function (d) { var s = strip(d); return isEmpty(s) || numRe.test(s); },
				oneOf: function (d) { return numRe.test(strip(d)); }
			},
			order: { pre: function (d) { var s = strip(d); return isEmpty(s) ? -Infinity : parseNum(s); } },
			className: 'dt-type-numeric'
		});
	}

	var LANG = {
		processing: 'Memproses…', search: '', searchPlaceholder: 'Cari di tabel…',
		lengthMenu: '_MENU_ per halaman', info: '_START_–_END_ dari _TOTAL_ data', infoEmpty: '0 data',
		infoFiltered: '(disaring dari _MAX_)', loadingRecords: 'Memuat…', zeroRecords: 'Tidak ada data yang cocok',
		emptyTable: 'Belum ada data', paginate: { first: '«', previous: '‹', next: '›', last: '»' },
		aria: { orderable: 'Urutkan kolom ini', orderableReverse: 'Balik urutan' }
	};

	function eligible(table, auto) {
		var opt = table.getAttribute('data-dt');
		if (opt === 'false') return false;
		if (table.closest('.modal, details, .viz, .no-dt, .dataTables_wrapper, .dt-container')) return false;
		if (table.matches('.pnl, .heat, .matrix, .table-borderless')) return false;
		var thead = table.tHead;
		if (!thead || thead.rows.length !== 1 || thead.querySelector('[colspan], [rowspan]')) return false;
		if (!table.tBodies.length) return false;
		// Tabel input (form baris dinamis, opname) tidak diubah.
		if (table.querySelector('tbody input:not([type=hidden]):not([type=checkbox]), tbody select, tbody textarea')) return false;
		if (table.querySelector('tbody [rowspan]')) return false;
		// Checkbox di dalam form: paging/pencarian DataTables mengeluarkan baris dari DOM sehingga
		// pilihan di halaman lain tidak ikut terkirim. Biarkan tabel apa adanya.
		if (opt !== 'true' && table.closest('form') && table.querySelector('tbody input[type=checkbox]')) return false;
		var cols = thead.rows[0].cells.length, rows = [];
		for (var b = 0; b < table.tBodies.length; b++) rows = rows.concat([].slice.call(table.tBodies[b].rows));
		var data = 0;
		for (var i = 0; i < rows.length; i++) {
			var r = rows[i];
			if (r.cells.length === 1 && +r.cells[0].getAttribute('colspan') > 1) continue; // baris "belum ada data"
			if (r.cells.length !== cols || r.querySelector('[colspan]')) return false;          // baris grup / subtotal
			data++;
		}
		if (opt === 'true') return true;
		return auto ? true : data > 10;
	}

	function enhance(table) {
		// Satukan tbody jamak & buang baris kosong: DataTables yang menampilkan "Belum ada data".
		[].slice.call(table.tBodies).forEach(function (tb, i) {
			[].slice.call(tb.rows).forEach(function (r) { if (r.cells.length === 1 && +r.cells[0].getAttribute('colspan') > 1) r.remove(); });
			if (i > 0) { while (tb.rows.length) table.tBodies[0].appendChild(tb.rows[0]); tb.remove(); }
		});
		var heads = [].slice.call(table.tHead.rows[0].cells);
		var last = heads.length - 1;
		var defs = [];
		heads.forEach(function (th, i) {
			if (!th.textContent.trim() || th.hasAttribute('data-dt-nosort')) defs.push({ targets: i, orderable: false, searchable: false });
		});
		defs.push({ targets: 0, responsivePriority: 1 });
		if (last > 0) defs.push({ targets: last, responsivePriority: 2 });
		var paging = table.getAttribute('data-dt-paging') !== 'false';
		var rowsN = table.tBodies[0] ? table.tBodies[0].rows.length : 0;
		var wrap = table.closest('.table-responsive');
		if (wrap) wrap.classList.add('dt-host');
		var dt = new window.DataTable(table, {
			language: LANG,
			order: [],
			paging: paging && rowsN > 10,
			info: paging,
			pageLength: +(table.getAttribute('data-dt-length') || 25),
			lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Semua']],
			responsive: { details: { type: 'inline', target: 'tr' } },
			autoWidth: false,
			columnDefs: defs,
			layout: {
				topStart: paging && rowsN > 10 ? 'pageLength' : null,
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: paging && rowsN > 10 ? 'paging' : null
			}
		});
		// Kolom cari cepat lama (.table-filter) diarahkan ke pencarian DataTables.
		if (table.id) {
			document.querySelectorAll('.table-filter[data-target="#' + table.id + '"]').forEach(function (inp) {
				inp.addEventListener('input', function (e) { e.stopImmediatePropagation(); dt.search(inp.value).draw(); }, true);
				inp.hidden = true;
			});
		}
		return dt;
	}

	function initTables(root) {
		if (!window.DataTable) return;
		registerTypes();
		var auto = document.body.getAttribute('data-dt-auto') === '1';
		(root || document).querySelectorAll('.content table.table').forEach(function (t) {
			try { if (eligible(t, auto)) enhance(t); } catch (err) { if (window.console) console.warn('DataTables dilewati:', err); }
		});
	}
	document.addEventListener('DOMContentLoaded', function () { initTables(); });

	/* ---------------- Form: tanda wajib isi ---------------- */
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.content [required][id]').forEach(function (inp) {
			var lb = document.querySelector('label[for="' + inp.id + '"]');
			if (lb && !lb.classList.contains('is-required')) lb.classList.add('is-required');
		});
	});

	return {
		loading: { start: start, done: done, reset: reset, button: buttonLoading },
		confirm: confirmDialog,
		alert: alertDialog,
		toast: toast,
		initTables: initTables
	};
})();
