<?php defined('BASEPATH') OR exit('No direct script access allowed');
$can_price = can('menu.view');
$can_cogs = can('menu.view_cogs');
$can_recipe = can_any(array('menu.view_recipe', 'menu.view_cogs'));
?>
<p><a href="<?= site_url('menu/items') ?>"><i class="bi bi-arrow-left"></i> Daftar menu</a></p>

<div class="card mb-3">
	<div class="card-body d-flex flex-wrap gap-3">
		<?php if ($m['image_path']): ?><img src="<?= base_url($m['image_path']) ?>" alt="" class="rounded border" style="width:120px;height:120px;object-fit:cover"><?php endif; ?>
		<div class="flex-fill">
			<div class="d-flex flex-wrap gap-2 align-items-start">
				<div>
					<h2 class="h5 mb-1"><?= e($m['name']) ?>
						<?php if ($m['status'] !== 'active'): ?><span class="badge text-bg-<?= $m['status'] === 'inactive' ? 'secondary' : 'danger' ?> align-middle"><?= e(Menu_model::$statuses[$m['status']]) ?></span><?php endif; ?></h2>
					<div class="small text-muted"><code><?= e($m['code']) ?></code> · <?= e($m['category_name'] ?: 'Tanpa kategori') ?></div>
				</div>
				<div class="ms-auto d-flex flex-wrap gap-1">
					<?php if (can('menu.edit') && $m['status'] !== 'inactive'): ?>
						<?= form_open('menu/items/toggle_stock/' . $m['id'], array('class' => 'd-inline')) ?>
							<button class="btn btn-sm <?= $m['status'] === 'out_of_stock' ? 'btn-success' : 'btn-outline-danger' ?>"><?= $m['status'] === 'out_of_stock' ? '<i class="bi bi-check2"></i> Tersedia lagi' : '<i class="bi bi-slash-circle"></i> Tandai habis' ?></button>
						<?= form_close() ?>
					<?php endif; ?>
					<?php if (can('menu.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('menu/items/edit/' . $m['id']) ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
					<?php if (can('menu.delete')): ?>
						<?= form_open('menu/items/delete/' . $m['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus menu ' . $m['name'] . ' beserta resep & riwayat harganya?')) ?><button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button><?= form_close() ?>
					<?php endif; ?>
				</div>
			</div>
			<?php if ($m['description']): ?><p class="mt-2 mb-2"><?= e($m['description']) ?></p><?php endif; ?>
			<div class="small d-flex flex-wrap gap-3 text-muted mt-2">
				<?php if ($m['prep_minutes']): ?><span><i class="bi bi-clock"></i> <?= (int) $m['prep_minutes'] ?> menit</span><?php endif; ?>
				<?php if ($m['spicy_level'] !== NULL): ?><span>🌶 Level <?= (int) $m['spicy_level'] ?>/5</span><?php endif; ?>
				<?php if ($m['allergens']): ?><span><i class="bi bi-exclamation-triangle"></i> Alergen: <?= e($m['allergens']) ?></span><?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php foreach ($m['variants'] as $v): $i = $info[$v['id']]; $d = $details[$v['id']]; ?>
<div class="card mb-3" id="v<?= $v['id'] ?>">
	<div class="card-header d-flex flex-wrap align-items-center gap-2">
		<strong><?= e($v['name']) ?></strong>
		<?php $a = Menu_service::availability($m['status'], $v['is_active'], $i['portions']); $portions = $i['portions']; include __DIR__ . '/_avail.php'; ?>
		<div class="ms-auto d-flex gap-1">
			<?php if (can('menu.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('menu/items/recipe/' . $v['id']) ?>"><i class="bi bi-list-check"></i> Resep</a><?php endif; ?>
			<?php if (can('menu.edit_price')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('menu/items/price/' . $v['id']) ?>"><i class="bi bi-tag"></i> Harga</a><?php endif; ?>
		</div>
	</div>
	<div class="card-body">
		<div class="row g-3">
			<?php if ($can_price OR $can_cogs): ?>
			<div class="col-md-4">
				<?php if ($can_price): ?>
					<div class="stat-label">Harga jual</div>
					<div class="stat-value fs-4"><?= $i['price'] !== NULL ? rupiah($i['price']) : '-' ?></div>
					<?php if ($i['member_price'] !== NULL): ?><div class="small">Member: <?= rupiah($i['member_price']) ?></div><?php endif; ?>
					<?php if ($i['bulk_min_qty']): ?><div class="small">Grosir ≥ <?= (int) $i['bulk_min_qty'] ?>: <?= rupiah($i['bulk_price']) ?></div><?php endif; ?>
					<?php if ($i['next_price'] !== NULL): ?><div class="small text-info">Terjadwal <?= rupiah($i['next_price']) ?> mulai <?= tgl($i['next_from']) ?></div><?php endif; ?>
				<?php endif; ?>
				<?php $can_edit_cogs = $can_cogs && can('menu.edit'); ?>
				<?php if ($can_cogs && ($i['has_cogs'] OR $can_edit_cogs)): ?>
					<hr>
					<div class="d-flex justify-content-between align-items-center small">
						<span>HPP / porsi
							<?php if ($i['cogs_source'] === 'manual'): ?><span class="badge text-bg-warning ms-1" title="Diisi manual">Manual</span>
							<?php elseif ($i['cogs_source'] === 'resep'): ?><span class="badge text-bg-light border ms-1" title="Dihitung dari resep × harga beli terakhir">Resep</span><?php endif; ?>
						</span>
						<strong><?= $i['has_cogs'] ? rupiah($i['cogs']) : '<span class="text-muted fw-normal">belum ada</span>' ?></strong>
					</div>
					<?php if ($i['cogs_source'] === 'manual' && $i['recipe_lines']): ?>
						<div class="d-flex justify-content-between small text-muted"><span>HPP dari resep</span><span><?= rupiah($i['cogs_recipe']) ?></span></div>
					<?php endif; ?>
					<?php if ($i['cogs_source'] === 'manual' && $v['cogs_manual_note']): ?><div class="small text-muted fst-italic"><?= e($v['cogs_manual_note']) ?></div><?php endif; ?>
					<?php if ($i['has_cogs']): ?>
						<div class="d-flex justify-content-between small"><span>Laba kotor</span><span><?= rupiah($i['price'] - $i['cogs']) ?></span></div>
						<div class="d-flex justify-content-between small"><span>Margin</span><strong class="<?= $i['margin_pct'] !== NULL && $i['margin_pct'] < $warn ? 'text-danger' : 'text-success' ?>"><?= $i['margin_pct'] !== NULL ? number_format($i['margin_pct'], 1, ',', '.') . '%' : '-' ?></strong></div>
					<?php endif; ?>
					<?php if ($i['missing_cost'] && $i['cogs_source'] === 'resep'): ?><div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-circle"></i> <?= (int) $i['missing_cost'] ?> bahan belum punya harga, HPP belum lengkap.</div><?php endif; ?>
					<?php if ($can_edit_cogs): ?>
						<button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2" data-modal-template="#tpl-cogs-<?= $v['id'] ?>" data-modal-title="HPP <?= e($m['name'] . ($v['name'] !== 'Reguler' ? ' - ' . $v['name'] : '')) ?>"
							data-fill="<?= e(json_encode(array('mode' => $i['cogs_source'] === 'manual' ? 'manual' : 'resep', 'cogs_manual' => $i['cogs_manual'] !== NULL ? (string) $i['cogs_manual'] : ($i['recipe_lines'] ? (string) $i['cogs_recipe'] : ''), 'note' => (string) $v['cogs_manual_note']))) ?>"><i class="bi bi-pencil-square"></i> Ubah HPP</button>
						<template id="tpl-cogs-<?= $v['id'] ?>" data-icon="calculator">
							<?= form_open('menu/items/cogs/' . $v['id']) ?>
								<div class="form-check p-3 border rounded-3 mb-2">
									<input class="form-check-input ms-0 me-2" type="radio" name="mode" value="resep" id="cm-r-<?= $v['id'] ?>" <?= $i['recipe_lines'] ? '' : 'disabled' ?>>
									<label class="form-check-label" for="cm-r-<?= $v['id'] ?>"><strong>Pakai HPP dari resep</strong>
										<span class="d-block small text-muted"><?= $i['recipe_lines'] ? 'Otomatis dari resep × harga beli terakhir: <strong>' . rupiah($i['cogs_recipe']) . '</strong> per porsi' : 'Resep belum diisi.' ?></span></label>
								</div>
								<div class="form-check p-3 border rounded-3">
									<input class="form-check-input ms-0 me-2" type="radio" name="mode" value="manual" id="cm-m-<?= $v['id'] ?>">
									<label class="form-check-label" for="cm-m-<?= $v['id'] ?>"><strong>Input HPP manual</strong>
										<span class="d-block small text-muted">Dipakai untuk analisis margin & HPP transaksi. Stok tetap dipotong sesuai resep.</span></label>
									<div class="row g-2 mt-1 cm-manual">
										<div class="col-sm-6"><div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="number" min="0" step="any" name="cogs_manual" placeholder="per porsi" aria-label="HPP manual per porsi"></div></div>
										<div class="col-sm-6"><input class="form-control" name="note" maxlength="255" placeholder="Alasan (opsional)" aria-label="Alasan"></div>
									</div>
								</div>
								<div class="ui-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Simpan HPP</button></div>
							<?= form_close() ?>
						</template>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<div class="col-md-<?= ($can_price OR $can_cogs) ? 5 : 9 ?>">
				<?php if ($can_recipe): ?>
					<div class="stat-label mb-1">Resep per porsi</div>
					<?php if (empty($d['recipe'])): ?>
						<p class="small text-muted">Belum ada resep. <?= can('menu.edit') ? '<a href="' . site_url('menu/items/recipe/' . $v['id']) . '">Isi resep</a> untuk menghitung COGS dan ketersediaan.' : '' ?></p>
					<?php else: ?>
						<table class="table table-sm small mb-0">
							<?php foreach ($d['recipe'] as $r): $enough = (float) $r['qty_on_hand'] >= (float) $r['qty_std']; ?>
								<tr>
									<td><a href="<?= site_url('inventory/ingredients/show/' . $r['ingredient_id']) ?>"><?= e($r['name']) ?></a><?= $r['notes'] ? '<div class="text-muted">' . e($r['notes']) . '</div>' : '' ?></td>
									<td class="text-end text-nowrap"><?= qty($r['qty']) ?> <?= e($r['unit']) ?></td>
									<td class="text-end text-nowrap <?= $enough ? 'text-muted' : 'text-danger' ?>" title="Stok bahan">stok <?= qty($r['qty_on_hand']) ?> <?= e($r['std_unit']) ?></td>
									<?php if ($can_cogs): ?><td class="text-end text-nowrap"><?= (float) $r['unit_cost'] > 0 ? rupiah($r['line_cost']) : '<span class="text-warning-emphasis">tanpa harga</span>' ?></td><?php endif; ?>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div class="col-md-3 text-center">
				<?php if ($v['barcode']): ?>
					<div class="barcode-box"><?= $this->barcode->svg($v['barcode'], 1, 40) ?></div>
					<?php if (can('menu.edit')): ?>
						<?= form_open('menu/labels/regenerate/' . $v['id'], array('class' => 'mt-1', 'data-confirm' => 'Ganti barcode dengan kode otomatis? Label lama tidak bisa dipakai lagi.')) ?><button class="btn btn-link btn-sm p-0">buat ulang barcode</button><?= form_close() ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($can_cogs && count($d['cogs']) > 1): $first = $d['cogs'][0]; $last = end($d['cogs']); ?>
			<div class="small text-muted mt-3">COGS 90 hari: <?= rupiah($first['cogs']) ?> (<?= tgl($first['recorded_on'], FALSE) ?>) → <?= rupiah($last['cogs']) ?> (<?= tgl($last['recorded_on'], FALSE) ?>)
				<?php $chg = $first['cogs'] > 0 ? ($last['cogs'] - $first['cogs']) / $first['cogs'] * 100 : 0; ?>
				<span class="<?= $chg > 0 ? 'text-danger' : 'text-success' ?>"><?= ($chg > 0 ? '+' : '') . number_format($chg, 1, ',', '.') ?>%</span></div>
		<?php endif; ?>
		<?php if ($can_price && count($d['prices']) > 1): ?>
			<details class="mt-2 small"><summary>Riwayat harga (<?= count($d['prices']) ?>)</summary>
				<table class="table table-sm mt-2 mb-0">
					<?php foreach ($d['prices'] as $p): ?>
						<tr class="<?= $p['effective_from'] > date('Y-m-d H:i:s') ? 'text-info' : '' ?>"><td class="text-nowrap"><?= tgl($p['effective_from']) ?></td><td class="text-end"><?= rupiah($p['price']) ?></td><td><?= e($p['reason']) ?></td><td class="text-muted"><?= e($p['created_by_name']) ?></td></tr>
					<?php endforeach; ?>
				</table>
			</details>
		<?php endif; ?>
	</div>
</div>
<?php endforeach; ?>

<script>
(function () {
	function sync(form) {
		var manual = form.querySelector('input[name=mode][value=manual]');
		if (!manual) return;
		form.querySelectorAll('.cm-manual input').forEach(function (i) { i.disabled = !manual.checked; });
		if (manual.checked) { var c = form.querySelector('input[name=cogs_manual]'); c.required = true; } else { form.querySelector('input[name=cogs_manual]').required = false; }
	}
	document.addEventListener('ui:fragment', function (e) { var f = e.detail.querySelector('form'); if (f && f.querySelector('input[name=mode]')) sync(f); });
	document.addEventListener('change', function (e) { if (e.target.name === 'mode') sync(e.target.form); });
})();
</script>
