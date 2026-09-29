<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === '' || $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.'); };
$opts = function ($selected) use ($ingredients) {
	$html = '<option value="">— pilih bahan —</option>';
	foreach ($ingredients as $i)
	{
		$html .= '<option value="' . $i['id'] . '" data-unit="' . e($i['unit']) . '" data-units="' . e(json_encode($i['units'])) . '"'
			. ' data-stock="' . e((float) $i['qty_on_hand']) . '" data-price="' . e((float) $i['current_price']) . '" data-supplier="' . (int) $i['default_supplier_id'] . '"'
			. ((int) $selected === (int) $i['id'] ? ' selected' : '') . '>' . e($i['name'] . ' (' . $i['code'] . ')') . '</option>';
	}
	return $html;
};
// Satu baris bahan: nama (+ info stok & catatan), jumlah + satuan, harga, subtotal, aksi.
$row = function ($idx, array $l) use ($opts, $num) {
	ob_start(); ?>
	<tr class="doc-line">
		<td class="c-no"></td>
		<td class="c-item">
			<select class="form-select line-ingredient" name="lines[<?= $idx ?>][ingredient_id]" aria-label="Bahan"><?= $opts($l['ingredient_id']) ?></select>
			<div class="small text-muted line-info"></div>
			<input class="form-control form-control-sm line-note mt-1" name="lines[<?= $idx ?>][notes]" value="<?= e($l['notes']) ?>" maxlength="255" placeholder="Catatan untuk supplier (mis. merek, ukuran)" <?= trim((string) $l['notes']) === '' ? 'hidden' : '' ?>>
		</td>
		<td class="c-qty">
			<div class="input-group">
				<input class="form-control line-qty text-end" type="number" step="any" min="0" name="lines[<?= $idx ?>][qty]" value="<?= e($num($l['qty'])) ?>" placeholder="0" aria-label="Jumlah">
				<select class="form-select line-unit" name="lines[<?= $idx ?>][unit]" data-selected="<?= e($l['unit']) ?>" aria-label="Satuan"></select>
			</div>
		</td>
		<td class="c-cost">
			<input class="form-control line-cost text-end" type="number" step="any" min="0" name="lines[<?= $idx ?>][cost]" value="<?= e($num($l['cost'])) ?>" placeholder="0" aria-label="Harga per satuan (Rp)">
		</td>
		<td class="c-total text-end line-total"></td>
		<td class="c-act text-end">
			<button type="button" class="btn btn-sm btn-icon line-note-toggle" title="Catatan baris"><i class="bi bi-chat-left-text"></i></button>
			<button type="button" class="btn btn-sm btn-icon text-danger line-remove" title="Hapus baris"><i class="bi bi-trash"></i></button>
		</td>
	</tr>
	<?php return ob_get_clean();
};
$more_open = trim((string) $header['delivery_address']) !== '' || trim((string) $header['notes']) !== '';
?>
<?= form_open($po ? 'purchase/orders/edit/' . $po['id'] : 'purchase/orders/create', array('id' => 'stock-doc', 'data-direction' => 'po', 'data-layout-fixed' => '1', 'class' => 'po-form')) ?>
<div class="po-layout">
	<!-- Detail PO -->
	<div class="po-detail card">
		<div class="card-header d-flex align-items-center gap-2"><i class="bi bi-truck"></i> Supplier &amp; pengiriman</div>
		<div class="card-body">
			<label class="form-label" for="supplier_id">Supplier</label>
			<select class="form-select mb-3" id="supplier_id" name="supplier_id" required>
				<option value="">— pilih supplier —</option>
				<?php foreach ($suppliers as $id => $name): ?><option value="<?= $id ?>" <?= (int) $header['supplier_id'] === (int) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
			</select>
			<div class="row g-2 mb-3">
				<div class="col-6"><label class="form-label" for="po_date">Tanggal PO</label><input class="form-control" type="date" id="po_date" name="po_date" value="<?= e($header['po_date']) ?>" required></div>
				<div class="col-6"><label class="form-label" for="expected_delivery">Tanggal kirim</label><input class="form-control" type="date" id="expected_delivery" name="expected_delivery" value="<?= e($header['expected_delivery']) ?>"></div>
			</div>
			<label class="form-label" for="payment_terms">Termin pembayaran</label>
			<select class="form-select" id="payment_terms" name="payment_terms">
				<?php foreach ($terms as $k => $v): ?><option value="<?= $k ?>" <?= $header['payment_terms'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
			</select>
			<div class="form-text" id="supplier-hint"></div>
			<details class="po-more mt-3" <?= $more_open ? 'open' : '' ?>>
				<summary>Alamat kirim &amp; catatan <span class="text-muted fw-normal">(opsional)</span></summary>
				<div class="pt-2">
					<label class="form-label" for="delivery_address">Alamat kirim</label>
					<input class="form-control mb-2" id="delivery_address" name="delivery_address" value="<?= e($header['delivery_address']) ?>" maxlength="255" placeholder="kosongkan = alamat resto">
					<label class="form-label" for="notes">Catatan / permintaan khusus</label>
					<textarea class="form-control" id="notes" name="notes" rows="2"><?= e($header['notes']) ?></textarea>
				</div>
			</details>
		</div>
	</div>

	<!-- Daftar bahan -->
	<div class="po-items card">
		<div class="card-header d-flex flex-wrap align-items-center gap-2">
			<span><i class="bi bi-basket"></i> Bahan yang dipesan</span>
			<span class="badge rounded-pill text-bg-light border" id="po-count">0 item</span>
			<div class="ms-auto d-flex gap-2">
				<?php if ($reorder): ?>
					<button type="button" class="btn btn-sm btn-outline-warning" id="po-reorder" title="Tambahkan bahan yang stoknya sudah di titik reorder / di bawah minimum">
						<i class="bi bi-lightning-charge"></i> Saran reorder <span class="badge text-bg-warning ms-1" id="po-reorder-n"><?= count($reorder) ?></span>
					</button>
				<?php endif; ?>
				<button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="bi bi-plus-lg"></i> Tambah baris</button>
			</div>
		</div>
		<div class="table-responsive">
			<table class="table align-middle mb-0 po-lines" data-dt="false">
				<thead><tr><th class="c-no">#</th><th>Bahan</th><th>Jumlah</th><th>Harga / satuan (Rp)</th><th class="text-end">Subtotal</th><th></th></tr></thead>
				<tbody id="doc-lines">
					<?php foreach ($lines as $i => $l): ?><?= $row($i, $l) ?><?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="card-footer small text-muted"><i class="bi bi-info-circle"></i> Harga otomatis diisi dari harga beli terakhir; ubah bila berbeda. <kbd>Enter</kbd> di kolom harga menambah baris baru.</div>
	</div>

	<!-- Ringkasan & simpan -->
	<div class="po-summary card">
		<div class="card-header d-flex align-items-center gap-2"><i class="bi bi-receipt"></i> Ringkasan</div>
		<div class="card-body">
			<div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><span class="fw-medium" id="doc-total">-</span></div>
			<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
				<label class="text-muted mb-0" for="discount_amount">Diskon</label>
				<div class="input-group input-group-sm" style="max-width: 170px"><span class="input-group-text">Rp</span><input class="form-control text-end po-adj" type="number" step="any" min="0" id="discount_amount" name="discount_amount" value="<?= e($num($header['discount_amount'])) ?>"></div>
			</div>
			<div class="d-flex align-items-center justify-content-between gap-2 mb-3">
				<label class="text-muted mb-0" for="tax_amount">Pajak (PPN)
					<?php if ($tax_rate > 0): ?><button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" id="po-tax-calc" title="Hitung PPN dari subtotal setelah diskon"><?= rtrim(rtrim(number_format($tax_rate, 2, ',', ''), '0'), ',') ?>%</button><?php endif; ?>
				</label>
				<div class="input-group input-group-sm" style="max-width: 170px"><span class="input-group-text">Rp</span><input class="form-control text-end po-adj" type="number" step="any" min="0" id="tax_amount" name="tax_amount" value="<?= e($num($header['tax_amount'])) ?>"></div>
			</div>
			<div class="po-grand-box">
				<span>Total</span>
				<strong id="po-grand">-</strong>
			</div>
			<div class="po-approval" id="po-approval-hint" data-auto="<?= $auto_limit ?>" data-owner="<?= $owner_limit ?>"></div>
			<div class="d-grid gap-2 mt-3">
				<button class="btn btn-primary" type="submit" name="action" value="submit"><i class="bi bi-send"></i> Simpan &amp; submit</button>
				<div class="d-flex gap-2">
					<button class="btn btn-outline-primary flex-fill" type="submit" name="action" value="draft"><i class="bi bi-save"></i> Simpan draft</button>
					<a class="btn btn-light flex-fill" href="<?= site_url($po ? 'purchase/orders/show/' . $po['id'] : 'purchase/orders') ?>">Batal</a>
				</div>
			</div>
		</div>
	</div>
</div>

<template id="line-template"><?= $row('__i__', array('ingredient_id' => '', 'qty' => '', 'unit' => '', 'cost' => '', 'notes' => '')) ?></template>
<?= form_close() ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
	'use strict';
	var form = document.getElementById('stock-doc');
	if (!form) return;
	var tbody = document.getElementById('doc-lines');
	var supplier = document.getElementById('supplier_id');
	var meta = <?= json_encode($supplier_meta) ?>;
	var reorder = <?= json_encode($reorder) ?>;
	var taxRate = <?= json_encode($tax_rate) ?>;
	var fmt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

	var factorOf = function (row) { var o = row.querySelector('.line-unit').selectedOptions[0]; return o ? parseFloat(o.getAttribute('data-factor')) || 1 : 1; };
	var optOf = function (row) { var s = row.querySelector('.line-ingredient'); return s.options[s.selectedIndex]; };

	// Harga otomatis dari harga beli terakhir (per satuan terpilih), selama belum diketik manual.
	function autoCost(row) {
		var cost = row.querySelector('.line-cost'), opt = optOf(row);
		if (!opt || !opt.value) return;
		if (cost.value !== '' && cost.dataset.auto !== '1') return;
		var p = parseFloat(opt.getAttribute('data-price')) || 0;
		if (!p) return;
		cost.value = Math.round(p * factorOf(row) * 100) / 100;
		cost.dataset.auto = '1';
		cost.dispatchEvent(new Event('input', { bubbles: true }));
	}
	tbody.addEventListener('change', function (e) {
		var row = e.target.closest('.doc-line'); if (!row) return;
		if (e.target.classList.contains('line-ingredient') || e.target.classList.contains('line-unit')) {
			if (e.target.classList.contains('line-ingredient')) row.querySelector('.line-cost').dataset.auto = '1';
			autoCost(row);
			if (e.target.classList.contains('line-ingredient') && e.target.value) row.querySelector('.line-qty').focus();
		}
		sync();
	});
	tbody.addEventListener('input', function (e) { if (e.target.classList.contains('line-cost')) { if (e.isTrusted) delete e.target.dataset.auto; } sync(); });

	// Catatan per baris disembunyikan sampai dibutuhkan.
	tbody.addEventListener('click', function (e) {
		var b = e.target.closest('.line-note-toggle'); if (!b) return;
		var n = b.closest('.doc-line').querySelector('.line-note');
		n.hidden = !n.hidden; if (!n.hidden) n.focus();
		setTimeout(sync, 0);
	});
	// Enter di kolom harga baris terakhir -> baris baru.
	tbody.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' || !e.target.classList.contains('line-cost')) return;
		e.preventDefault();
		var row = e.target.closest('.doc-line');
		if (row === tbody.lastElementChild) document.getElementById('add-line').click();
		else row.nextElementSibling.querySelector('.line-ingredient').focus();
	});
	// Baris terakhir yang dihapus: kosongkan saja.
	tbody.addEventListener('click', function (e) {
		if (!e.target.closest('.line-remove') || tbody.querySelectorAll('.doc-line').length > 1) return;
		var row = tbody.querySelector('.doc-line');
		row.querySelector('.line-ingredient').value = ''; row.querySelector('.line-qty').value = ''; row.querySelector('.line-cost').value = '';
		row.querySelector('.line-ingredient').dispatchEvent(new Event('change', { bubbles: true }));
	});
	document.getElementById('add-line').addEventListener('click', function () { setTimeout(sync, 0); });

	function emptyRow() {
		var rows = tbody.querySelectorAll('.doc-line'), last = rows[rows.length - 1];
		if (last && !last.querySelector('.line-ingredient').value) return last;
		document.getElementById('add-line').click();
		return tbody.lastElementChild;
	}
	function addLine(it) {
		var row = emptyRow(), sel = row.querySelector('.line-ingredient');
		sel.value = String(it.id);
		sel.dispatchEvent(new Event('change', { bubbles: true }));
		var unit = row.querySelector('.line-unit'); unit.value = it.unit; unit.dispatchEvent(new Event('change', { bubbles: true }));
		row.querySelector('.line-qty').value = it.qty;
		var cost = row.querySelector('.line-cost');
		if (it.cost) { cost.value = it.cost; cost.dataset.auto = '1'; }
		cost.dispatchEvent(new Event('input', { bubbles: true }));
	}

	// Saran reorder: bahan di titik reorder / di bawah minimum (sesuai supplier terpilih bila ada).
	var reorderBtn = document.getElementById('po-reorder');
	function suggestions() {
		var sup = parseInt(supplier.value, 10) || 0;
		var have = [].map.call(tbody.querySelectorAll('.line-ingredient'), function (s) { return s.value; });
		return reorder.filter(function (r) { return (!sup || r.supplier === sup) && have.indexOf(String(r.id)) === -1; });
	}
	if (reorderBtn) {
		reorderBtn.addEventListener('click', function () {
			var list = suggestions();
			if (!list.length) { if (window.UI) UI.toast('Tidak ada saran reorder' + (supplier.value ? ' untuk supplier ini.' : '.'), 'info'); return; }
			var sups = list.map(function (r) { return r.supplier; }).filter(function (v, i, a) { return v && a.indexOf(v) === i; });
			if (!supplier.value && sups.length === 1) { supplier.value = String(sups[0]); supplier.dispatchEvent(new Event('change', { bubbles: true })); }
			list.forEach(addLine);
			if (window.UI) UI.toast(list.length + ' bahan ditambahkan dari saran reorder.', 'success');
		});
	}

	// Pilih supplier: termin & tanggal kirim mengikuti data supplier; bahan supplier ini tampil di atas.
	function groupOptions(sel, sup) {
		var cur = sel.value, opts = [].slice.call(sel.querySelectorAll('option')).filter(function (o) { return o.value; });
		sel.querySelectorAll('optgroup').forEach(function (g) { g.remove(); });
		if (!sup) { opts.forEach(function (o) { sel.appendChild(o); }); sel.value = cur; return; }
		var mine = document.createElement('optgroup'), other = document.createElement('optgroup');
		mine.label = 'Bahan dari supplier ini'; other.label = 'Bahan lain';
		opts.forEach(function (o) { (o.getAttribute('data-supplier') === String(sup) ? mine : other).appendChild(o); });
		if (mine.children.length) sel.appendChild(mine);
		sel.appendChild(other);
		sel.value = cur;
	}
	function onSupplier(initial) {
		var sup = supplier.value, m = meta[sup], hint = document.getElementById('supplier-hint');
		if (m && !initial) {
			document.getElementById('payment_terms').value = m.terms;
			var d = new Date(document.getElementById('po_date').value || Date.now());
			d.setDate(d.getDate() + Math.max(1, m.lead));
			document.getElementById('expected_delivery').value = d.toISOString().slice(0, 10);
		}
		hint.textContent = m ? 'Lead time supplier ' + Math.max(1, m.lead) + ' hari.' : '';
		tbody.querySelectorAll('.line-ingredient').forEach(function (s) { groupOptions(s, sup); });
		var tpl = document.getElementById('line-template');
		groupOptions(tpl.content.querySelector('.line-ingredient'), sup);
		sync();
	}
	supplier.addEventListener('change', function () { onSupplier(false); });

	// PPN cepat
	var taxBtn = document.getElementById('po-tax-calc');
	if (taxBtn) taxBtn.addEventListener('click', function () {
		var sub = 0;
		tbody.querySelectorAll('.doc-line').forEach(function (r) { sub += (parseFloat(r.querySelector('.line-qty').value) || 0) * (parseFloat(r.querySelector('.line-cost').value) || 0); });
		var disc = parseFloat(document.getElementById('discount_amount').value) || 0;
		var tax = document.getElementById('tax_amount');
		tax.value = Math.max(0, Math.round((sub - disc) * taxRate / 100));
		tax.dispatchEvent(new Event('input', { bubbles: true }));
	});

	function sync() {
		var n = [].filter.call(tbody.querySelectorAll('.line-ingredient'), function (s) { return s.value; }).length;
		document.getElementById('po-count').textContent = n + ' item';
		tbody.querySelectorAll('.doc-line').forEach(function (r) {
			var note = r.querySelector('.line-note'), t = r.querySelector('.line-note-toggle');
			t.classList.toggle('is-on', !note.hidden || note.value !== '');
		});
		var badge = document.getElementById('po-reorder-n');
		if (badge) { var k = suggestions().length; badge.textContent = k; reorderBtn.hidden = !k; }
	}
	onSupplier(true);
});
</script>
