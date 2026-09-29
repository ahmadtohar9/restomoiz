<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'); };
$edit = can('menu.edit');
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
	<p class="text-muted small mb-0 flex-fill" style="max-width: 760px">Tambahan berbayar yang bisa dipilih kasir untuk setiap item (topping, extra shot, dll). Jika dihubungkan ke bahan baku, stok ikut berkurang saat terjual.</p>
	<?php if ($edit): ?>
		<button type="button" class="btn btn-primary" data-modal-template="#tpl-modifier" data-modal-title="Tambah tambahan / topping"><i class="bi bi-plus-lg"></i> Tambah</button>
	<?php endif; ?>
</div>
<div class="card">
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead><tr><th>Tambahan</th><th class="text-end">Harga</th><th>Bahan per porsi</th><th class="text-end">Urutan</th><th>Status</th><?php if ($edit): ?><th></th><?php endif; ?></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="<?= $edit ? 6 : 5 ?>" class="text-center text-muted py-4">Belum ada tambahan.</td></tr><?php endif; ?>
			<?php foreach ($rows as $m): ?>
				<tr class="<?= $m['is_active'] ? '' : 'text-muted' ?>">
					<td class="fw-medium"><?= e($m['name']) ?></td>
					<td class="text-end"><?= rupiah($m['price']) ?></td>
					<td class="small"><?= $m['ingredient_name'] ? e($m['ingredient_name']) . ' · ' . qty($m['qty_std'], 4) . ' ' . e($m['unit']) : '-' ?></td>
					<td class="text-end"><?= (int) $m['sort_order'] ?></td>
					<td><?= $m['is_active'] ? '<span class="badge text-bg-success">aktif</span>' : '<span class="badge text-bg-secondary">nonaktif</span>' ?></td>
					<?php if ($edit): ?>
					<td class="text-end">
						<button type="button" class="btn btn-sm btn-outline-secondary" data-modal-template="#tpl-modifier" data-modal-title="Ubah <?= e($m['name']) ?>"
							data-fill="<?= e(json_encode(array('id' => (int) $m['id'], 'name' => $m['name'], 'price' => $num($m['price']), 'ingredient_id' => $m['ingredient_id'] ? (string) $m['ingredient_id'] : '', 'qty' => $m['qty_std'] ? $num($m['qty_std']) : '', 'unit' => (string) $m['unit'], 'sort_order' => (int) $m['sort_order'], 'is_active' => (int) $m['is_active']))) ?>"><i class="bi bi-pencil"></i> Ubah</button>
					</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($edit): ?>
<template id="tpl-modifier" data-icon="plus-circle">
	<?= form_open('menu/modifiers/save') ?>
		<input type="hidden" name="id" value="0">
		<div class="row g-3">
			<div class="col-sm-8"><label class="form-label" for="mod-name">Nama</label><input class="form-control" id="mod-name" name="name" maxlength="100" required placeholder="mis. Telur ceplok"></div>
			<div class="col-sm-4"><label class="form-label" for="mod-price">Harga</label>
				<div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="number" min="0" step="any" id="mod-price" name="price" <?= can('menu.edit_price') ? '' : 'readonly' ?>></div></div>
			<div class="col-12"><div class="form-section-note"><i class="bi bi-box-seam"></i> Hubungkan ke bahan baku agar stok berkurang otomatis saat terjual (opsional).</div></div>
			<div class="col-sm-7"><label class="form-label" for="mod-ing">Bahan baku</label>
				<select class="form-select mod-ing" id="mod-ing" name="ingredient_id"><option value="">— tanpa bahan —</option>
					<?php foreach ($ingredients as $i): ?><option value="<?= $i['id'] ?>" data-units="<?= e(json_encode($i['units'])) ?>"><?= e($i['name']) ?></option><?php endforeach; ?>
				</select></div>
			<div class="col-sm-5"><label class="form-label" for="mod-qty">Pakai per porsi</label>
				<div class="input-group"><input class="form-control" type="number" min="0" step="any" id="mod-qty" name="qty"><select class="form-select mod-unit" name="unit" style="max-width: 96px"></select></div></div>
			<div class="col-sm-4"><label class="form-label" for="mod-sort">Urutan tampil</label><input class="form-control" type="number" id="mod-sort" name="sort_order" value="0"></div>
			<div class="col-sm-8 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="mod-active" name="is_active" value="1" checked><label class="form-check-label" for="mod-active">Aktif (bisa dipilih kasir)</label></div></div>
		</div>
		<div class="ui-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Simpan</button></div>
	<?= form_close() ?>
</template>
<?php endif; ?>
<script>
(function () {
	// Pilihan satuan mengikuti bahan yang dipilih (juga untuk form di modal).
	function fill(sel) {
		var form = sel.closest('form'), unit = form && form.querySelector('.mod-unit');
		if (!unit) return;
		var opt = sel.selectedOptions[0], units = opt && opt.getAttribute('data-units') ? JSON.parse(opt.getAttribute('data-units')) : [];
		var want = unit.getAttribute('data-selected') || unit.value;
		unit.innerHTML = '';
		units.forEach(function (u) { var o = document.createElement('option'); o.textContent = u.unit; o.selected = u.unit === want; unit.appendChild(o); });
	}
	document.addEventListener('change', function (e) { if (e.target.classList.contains('mod-ing')) fill(e.target); });
	document.addEventListener('ui:fragment', function (e) { e.detail.querySelectorAll('.mod-ing').forEach(fill); });
})();
</script>
