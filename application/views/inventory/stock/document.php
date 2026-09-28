<?php defined('BASEPATH') OR exit('No direct script access allowed');
$opts = function ($selected) use ($ingredients) {
	$html = '<option value="">— pilih bahan —</option>';
	foreach ($ingredients as $i)
	{
		$html .= '<option value="' . $i['id'] . '"'
			. ' data-unit="' . e($i['unit']) . '"'
			. ' data-units="' . e(json_encode($i['units'])) . '"'
			. ' data-stock="' . e((float) $i['qty_on_hand']) . '"'
			. ' data-price="' . e((float) $i['current_price']) . '"'
			. ' data-supplier="' . (int) $i['default_supplier_id'] . '"'
			. ((int) $selected === (int) $i['id'] ? ' selected' : '') . '>'
			. e($i['name'] . ' (' . $i['code'] . ')') . '</option>';
	}
	return $html;
};
$row = function ($idx, array $l) use ($opts, $is_in, $can_cost) {
	ob_start(); ?>
	<tr class="doc-line">
		<td style="min-width: 220px">
			<select class="form-select form-select-sm line-ingredient" name="lines[<?= $idx ?>][ingredient_id]"><?= $opts($l['ingredient_id']) ?></select>
			<input type="hidden" name="lines[<?= $idx ?>][batch_id]" value="<?= (int) $l['batch_id'] ?>">
			<div class="small text-muted line-info"></div>
		</td>
		<td style="width: 120px"><input class="form-control form-control-sm line-qty" type="number" step="any" min="0" name="lines[<?= $idx ?>][qty]" value="<?= e($l['qty']) ?>"></td>
		<td style="width: 120px"><select class="form-select form-select-sm line-unit" name="lines[<?= $idx ?>][unit]" data-selected="<?= e($l['unit']) ?>"></select></td>
		<?php if ($is_in): ?>
			<?php if ($can_cost): ?>
				<td style="width: 150px"><input class="form-control form-control-sm line-cost" type="number" step="any" min="0" name="lines[<?= $idx ?>][cost]" value="<?= e($l['cost']) ?>" placeholder="per satuan"></td>
			<?php endif; ?>
			<td style="width: 150px"><input class="form-control form-control-sm" type="date" name="lines[<?= $idx ?>][expiry_date]" value="<?= e($l['expiry_date']) ?>"></td>
		<?php endif; ?>
		<?php if ($can_cost && $is_in): ?><td class="text-end small line-total" style="width: 120px"></td><?php endif; ?>
		<td style="width: 40px"><button type="button" class="btn btn-sm btn-link text-danger line-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
	</tr>
	<?php return ob_get_clean();
};
?>
<?= form_open($is_in ? 'inventory/stock/in' : 'inventory/stock/out', array('id' => 'stock-doc', 'data-direction' => $is_in ? 'in' : 'out')) ?>
<div class="card mb-3">
	<div class="card-body row g-3">
		<div class="col-md-4">
			<label class="form-label" for="reason">Jenis transaksi</label>
			<select class="form-select" id="reason" name="reason">
				<?php foreach ($reasons as $k => $v): ?>
					<option value="<?= $k ?>" <?= $header['reason'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-4">
			<label class="form-label" for="supplier_id">Supplier <span class="text-muted small"><?= $is_in ? '(opsional)' : '(wajib untuk retur)' ?></span></label>
			<select class="form-select" id="supplier_id" name="supplier_id">
				<option value="">— tidak ada —</option>
				<?php foreach ($suppliers as $id => $name): ?>
					<option value="<?= $id ?>" <?= (int) $header['supplier_id'] === (int) $id ? 'selected' : '' ?>><?= e($name) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-4">
			<label class="form-label" for="notes">Catatan <span class="text-muted small">(wajib untuk penyesuaian)</span></label>
			<input class="form-control" id="notes" name="notes" value="<?= e($header['notes']) ?>" maxlength="255" placeholder="<?= $is_in ? 'mis. No. nota supplier' : 'mis. Tumpah saat persiapan' ?>">
		</div>
		<?php if ($is_in): ?>
			<div class="col-12 small text-muted">Penerimaan barang dari Purchase Order akan tersedia di modul Pembelian (fase 3). Form ini untuk saldo awal, pembelian langsung tanpa PO, dan penyesuaian.</div>
		<?php else: ?>
			<div class="col-12 small text-muted">Stok dikeluarkan dari batch paling lama lebih dulu (FIFO). Pemakaian dari penjualan akan tercatat otomatis setelah modul POS aktif.</div>
		<?php endif; ?>
	</div>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead>
				<tr>
					<th>Bahan</th><th>Jumlah</th><th>Satuan</th>
					<?php if ($is_in): ?><?php if ($can_cost): ?><th>Harga / satuan</th><?php endif; ?><th>Kedaluwarsa</th><?php endif; ?>
					<?php if ($can_cost && $is_in): ?><th class="text-end">Subtotal</th><?php endif; ?>
					<th></th>
				</tr>
			</thead>
			<tbody id="doc-lines">
				<?php foreach ($lines as $i => $l): ?><?= $row($i, $l) ?><?php endforeach; ?>
			</tbody>
			<?php if ($can_cost && $is_in): ?>
				<tfoot><tr><th colspan="5" class="text-end">Total</th><th class="text-end" id="doc-total">-</th><th></th></tr></tfoot>
			<?php endif; ?>
		</table>
	</div>
	<div class="card-footer bg-transparent">
		<button type="button" class="btn btn-sm btn-outline-secondary" id="add-line"><i class="bi bi-plus-lg"></i> Tambah baris</button>
	</div>
</div>

<template id="line-template"><?= $row('__i__', array('ingredient_id' => '', 'qty' => '', 'unit' => '', 'cost' => '', 'expiry_date' => '', 'batch_id' => 0)) ?></template>

<div class="mt-3 d-flex gap-2">
	<button class="btn <?= $is_in ? 'btn-success' : 'btn-danger' ?>" type="submit"><i class="bi bi-save"></i> Simpan <?= $is_in ? 'Stok Masuk' : 'Stok Keluar' ?></button>
	<a class="btn btn-outline-secondary" href="<?= site_url('inventory/ingredients') ?>">Batal</a>
</div>
<?= form_close() ?>
