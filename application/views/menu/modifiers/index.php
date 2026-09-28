<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'); };
$edit = can('menu.edit');
$form = function ($m) use ($ingredients, $num) {
	ob_start(); ?>
	<?= form_open('menu/modifiers/save', array('class' => 'row g-2 align-items-end')) ?>
		<input type="hidden" name="id" value="<?= $m ? (int) $m['id'] : 0 ?>">
		<div class="col-md-3"><label class="form-label small">Nama</label><input class="form-control form-control-sm" name="name" value="<?= e($m ? $m['name'] : '') ?>" maxlength="100" required placeholder="mis. Telur ceplok"></div>
		<div class="col-md-2"><label class="form-label small">Harga</label><input class="form-control form-control-sm" type="number" min="0" step="any" name="price" value="<?= e($m ? $num($m['price']) : '') ?>" <?= can('menu.edit_price') ? '' : 'readonly' ?>></div>
		<div class="col-md-3"><label class="form-label small">Bahan (opsional)</label>
			<select class="form-select form-select-sm mod-ing" name="ingredient_id"><option value="">— tanpa bahan —</option>
				<?php foreach ($ingredients as $i): ?><option value="<?= $i['id'] ?>" data-units="<?= e(json_encode($i['units'])) ?>" <?= $m && (int) $m['ingredient_id'] === (int) $i['id'] ? 'selected' : '' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
			</select></div>
		<div class="col-md-2"><label class="form-label small">Pakai per porsi</label>
			<div class="input-group input-group-sm"><input class="form-control" type="number" min="0" step="any" name="qty" value="<?= e($m && $m['qty_std'] ? $num($m['qty_std']) : '') ?>">
				<select class="form-select mod-unit" name="unit" data-selected="<?= e($m ? (string) $m['unit'] : '') ?>" style="max-width: 80px"></select></div></div>
		<div class="col-md-1"><label class="form-label small">Urutan</label><input class="form-control form-control-sm" type="number" name="sort_order" value="<?= $m ? (int) $m['sort_order'] : 0 ?>"></div>
		<div class="col-md-1"><div class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ! $m || $m['is_active'] ? 'checked' : '' ?>> aktif</div><button class="btn btn-sm btn-primary w-100"><?= $m ? 'Simpan' : 'Tambah' ?></button></div>
	<?= form_close() ?>
	<?php return ob_get_clean();
};
?>
<p class="text-muted small">Tambahan berbayar yang bisa dipilih kasir untuk setiap item (topping, extra shot, dll). Jika dihubungkan ke bahan baku, stok ikut berkurang saat terjual. Jumlah pemakaian dalam satuan standar bahan.</p>
<div class="card mb-3">
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead><tr><th>Tambahan</th><th class="text-end">Harga</th><th>Bahan</th><th></th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="4" class="text-center text-muted py-3">Belum ada tambahan.</td></tr><?php endif; ?>
			<?php foreach ($rows as $m): ?>
				<tr class="<?= $m['is_active'] ? '' : 'text-muted' ?>">
					<td><?= e($m['name']) ?> <?= $m['is_active'] ? '' : '<span class="badge text-bg-secondary">nonaktif</span>' ?></td>
					<td class="text-end"><?= rupiah($m['price']) ?></td>
					<td class="small"><?= $m['ingredient_name'] ? e($m['ingredient_name']) . ' ' . qty($m['qty_std'], 4) . ' ' . e($m['unit']) : '-' ?></td>
					<td class="text-end"><?php if ($edit): ?><a href="#" class="small" data-bs-toggle="collapse" data-bs-target="#m<?= $m['id'] ?>">ubah</a><?php endif; ?></td>
				</tr>
				<?php if ($edit): ?><tr class="collapse" id="m<?= $m['id'] ?>"><td colspan="4" class="bg-light"><?= $form($m) ?></td></tr><?php endif; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php if ($edit): ?><div class="card"><div class="card-header">Tambah</div><div class="card-body"><?= $form(NULL) ?></div></div><?php endif; ?>
<script>
document.querySelectorAll('.mod-ing').forEach(function (sel) {
	var form = sel.closest('form'), unit = form.querySelector('.mod-unit');
	var fill = function () {
		var opt = sel.selectedOptions[0], units = opt && opt.getAttribute('data-units') ? JSON.parse(opt.getAttribute('data-units')) : [];
		var want = unit.getAttribute('data-selected');
		unit.innerHTML = units.map(function (u) { return '<option' + (u.unit === want ? ' selected' : '') + '>' + u.unit + '</option>'; }).join('');
	};
	sel.addEventListener('change', fill); fill();
});
</script>
