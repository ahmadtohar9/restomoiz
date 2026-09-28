<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === '' || $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.'); };
$can_cogs = can('menu.view_cogs');
$opts = function ($selected) use ($ingredients) {
	$html = '<option value="">— pilih bahan —</option>';
	foreach ($ingredients as $i)
	{
		$html .= '<option value="' . $i['id'] . '" data-unit="' . e($i['unit']) . '" data-units="' . e(json_encode($i['units'])) . '"'
			. ' data-stock="' . e((float) $i['qty_on_hand']) . '" data-price="' . e((float) $i['current_price']) . '" data-supplier="0"'
			. ((int) $selected === (int) $i['id'] ? ' selected' : '') . '>' . e($i['name'] . ' (' . $i['code'] . ')') . '</option>';
	}
	return $html;
};
$row = function ($idx, array $l) use ($opts, $num, $can_cogs) {
	ob_start(); ?>
	<tr class="doc-line">
		<td style="min-width: 240px">
			<select class="form-select form-select-sm line-ingredient" name="lines[<?= $idx ?>][ingredient_id]"><?= $opts($l['ingredient_id']) ?></select>
			<div class="small text-muted line-info"></div>
		</td>
		<td style="width: 120px"><input class="form-control form-control-sm line-qty" type="number" step="any" min="0" name="lines[<?= $idx ?>][qty]" value="<?= e($num($l['qty'])) ?>"></td>
		<td style="width: 120px"><select class="form-select form-select-sm line-unit" name="lines[<?= $idx ?>][unit]" data-selected="<?= e($l['unit']) ?>"></select></td>
		<td><input class="form-control form-control-sm" name="lines[<?= $idx ?>][notes]" value="<?= e($l['notes']) ?>" maxlength="255" placeholder="mis. potong dadu"></td>
		<?php if ($can_cogs): ?><td class="text-end small recipe-cost" style="width: 110px"></td><?php endif; ?>
		<td style="width: 40px"><button type="button" class="btn btn-sm btn-link text-danger line-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
	</tr>
	<?php return ob_get_clean();
};
?>
<p><a href="<?= site_url('menu/items/show/' . $variant['menu_id'] . '#v' . $variant['id']) ?>"><i class="bi bi-arrow-left"></i> Kembali ke menu</a></p>

<?php if ( ! empty($copied)): ?><div class="alert alert-info">Resep disalin dari varian lain. Sesuaikan jumlahnya lalu klik Simpan.</div><?php endif; ?>

<?= form_open('menu/items/recipe/' . $variant['id'], array('id' => 'stock-doc', 'data-direction' => 'recipe')) ?>
<div class="card">
	<div class="card-header d-flex flex-wrap gap-2 align-items-center">
		<span>Bahan untuk <strong>1 porsi</strong></span>
		<?php if ($siblings): ?>
			<span class="ms-auto small">Salin dari:
				<?php foreach ($siblings as $s): ?><a class="ms-1" href="<?= site_url('menu/items/recipe/' . $variant['id'] . '?copy_from=' . $s['id']) ?>"><?= e($s['name']) ?></a><?php endforeach; ?>
			</span>
		<?php endif; ?>
	</div>
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead><tr><th>Bahan</th><th>Jumlah</th><th>Satuan</th><th>Catatan</th><?php if ($can_cogs): ?><th class="text-end">Biaya</th><?php endif; ?><th></th></tr></thead>
			<tbody id="doc-lines">
				<?php foreach ($lines as $i => $l): ?><?= $row($i, $l) ?><?php endforeach; ?>
			</tbody>
			<?php if ($can_cogs): ?>
				<tfoot>
					<tr><th colspan="4" class="text-end">COGS per porsi</th><th class="text-end" id="recipe-total">-</th><th></th></tr>
					<?php if ($price): ?><tr><td colspan="4" class="text-end small text-muted">Harga jual <?= rupiah($price) ?> · margin</td><td class="text-end small" id="recipe-margin" data-price="<?= e($price) ?>">-</td><td></td></tr><?php endif; ?>
				</tfoot>
			<?php endif; ?>
		</table>
	</div>
	<div class="card-footer bg-transparent">
		<button type="button" class="btn btn-sm btn-outline-secondary" id="add-line"><i class="bi bi-plus-lg"></i> Tambah bahan</button>
		<span class="small text-muted ms-2">Biaya dihitung dari harga beli terakhir bahan.</span>
	</div>
</div>
<template id="line-template"><?= $row('__i__', array('ingredient_id' => '', 'qty' => '', 'unit' => '', 'notes' => '')) ?></template>
<div class="mt-3 d-flex gap-2">
	<button class="btn btn-primary" type="submit">Simpan Resep</button>
	<a class="btn btn-outline-secondary" href="<?= site_url('menu/items/show/' . $variant['menu_id']) ?>">Batal</a>
</div>
<?= form_close() ?>
