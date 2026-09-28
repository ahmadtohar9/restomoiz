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
$row = function ($idx, array $l) use ($opts, $num) {
	ob_start(); ?>
	<tr class="doc-line">
		<td style="min-width: 220px">
			<select class="form-select form-select-sm line-ingredient" name="lines[<?= $idx ?>][ingredient_id]"><?= $opts($l['ingredient_id']) ?></select>
			<div class="small text-muted line-info"></div>
		</td>
		<td style="width: 110px"><input class="form-control form-control-sm line-qty" type="number" step="any" min="0" name="lines[<?= $idx ?>][qty]" value="<?= e($num($l['qty'])) ?>"></td>
		<td style="width: 110px"><select class="form-select form-select-sm line-unit" name="lines[<?= $idx ?>][unit]" data-selected="<?= e($l['unit']) ?>"></select></td>
		<td style="width: 150px"><input class="form-control form-control-sm line-cost" type="number" step="any" min="0" name="lines[<?= $idx ?>][cost]" value="<?= e($num($l['cost'])) ?>" placeholder="per satuan"></td>
		<td style="min-width: 140px"><input class="form-control form-control-sm" name="lines[<?= $idx ?>][notes]" value="<?= e($l['notes']) ?>" maxlength="255" placeholder="opsional"></td>
		<td class="text-end small line-total" style="width: 120px"></td>
		<td style="width: 40px"><button type="button" class="btn btn-sm btn-link text-danger line-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
	</tr>
	<?php return ob_get_clean();
};
?>
<?= form_open($po ? 'purchase/orders/edit/' . $po['id'] : 'purchase/orders/create', array('id' => 'stock-doc', 'data-direction' => 'po')) ?>
<div class="card mb-3">
	<div class="card-body row g-3">
		<div class="col-md-4">
			<label class="form-label" for="supplier_id">Supplier</label>
			<select class="form-select" id="supplier_id" name="supplier_id" required>
				<option value="">— pilih supplier —</option>
				<?php foreach ($suppliers as $id => $name): ?><option value="<?= $id ?>" <?= (int) $header['supplier_id'] === (int) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label" for="po_date">Tanggal PO</label>
			<input class="form-control" type="date" id="po_date" name="po_date" value="<?= e($header['po_date']) ?>" required>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label" for="expected_delivery">Tanggal kirim</label>
			<input class="form-control" type="date" id="expected_delivery" name="expected_delivery" value="<?= e($header['expected_delivery']) ?>">
		</div>
		<div class="col-md-4">
			<label class="form-label" for="payment_terms">Termin pembayaran</label>
			<select class="form-select" id="payment_terms" name="payment_terms">
				<?php foreach ($terms as $k => $v): ?><option value="<?= $k ?>" <?= $header['payment_terms'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-6">
			<label class="form-label" for="delivery_address">Alamat kirim</label>
			<input class="form-control" id="delivery_address" name="delivery_address" value="<?= e($header['delivery_address']) ?>" maxlength="255" placeholder="kosongkan = alamat resto">
		</div>
		<div class="col-md-6">
			<label class="form-label" for="notes">Catatan / permintaan khusus</label>
			<input class="form-control" id="notes" name="notes" value="<?= e($header['notes']) ?>">
		</div>
	</div>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead><tr><th>Bahan</th><th>Jumlah</th><th>Satuan</th><th>Harga / satuan</th><th>Catatan</th><th class="text-end">Subtotal</th><th></th></tr></thead>
			<tbody id="doc-lines">
				<?php foreach ($lines as $i => $l): ?><?= $row($i, $l) ?><?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr><td colspan="5" class="text-end">Subtotal</td><td class="text-end" id="doc-total">-</td><td></td></tr>
				<tr><td colspan="5" class="text-end align-middle">Diskon</td><td><input class="form-control form-control-sm text-end po-adj" type="number" step="any" min="0" name="discount_amount" value="<?= e($num($header['discount_amount'])) ?>"></td><td></td></tr>
				<tr><td colspan="5" class="text-end align-middle">Pajak (PPN)</td><td><input class="form-control form-control-sm text-end po-adj" type="number" step="any" min="0" name="tax_amount" value="<?= e($num($header['tax_amount'])) ?>"></td><td></td></tr>
				<tr><th colspan="5" class="text-end">Total</th><th class="text-end" id="po-grand">-</th><th></th></tr>
			</tfoot>
		</table>
	</div>
	<div class="card-footer bg-transparent d-flex flex-wrap gap-2 align-items-center">
		<button type="button" class="btn btn-sm btn-outline-secondary" id="add-line"><i class="bi bi-plus-lg"></i> Tambah baris</button>
		<span class="small text-muted ms-auto" id="po-approval-hint"
			data-auto="<?= $auto_limit ?>" data-owner="<?= $owner_limit ?>"></span>
	</div>
</div>

<template id="line-template"><?= $row('__i__', array('ingredient_id' => '', 'qty' => '', 'unit' => '', 'cost' => '', 'notes' => '')) ?></template>

<div class="mt-3 d-flex flex-wrap gap-2">
	<button class="btn btn-outline-primary" type="submit" name="action" value="draft"><i class="bi bi-save"></i> Simpan draft</button>
	<button class="btn btn-primary" type="submit" name="action" value="submit"><i class="bi bi-send"></i> Simpan &amp; submit</button>
	<a class="btn btn-outline-secondary" href="<?= site_url($po ? 'purchase/orders/show/' . $po['id'] : 'purchase/orders') ?>">Batal</a>
</div>
<?= form_close() ?>
