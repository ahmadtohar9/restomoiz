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
				<?php if ($can_cogs && $i['recipe_lines']): ?>
					<hr>
					<div class="d-flex justify-content-between small"><span>COGS / porsi</span><strong><?= rupiah($i['cogs']) ?></strong></div>
					<div class="d-flex justify-content-between small"><span>Laba kotor</span><span><?= rupiah($i['price'] - $i['cogs']) ?></span></div>
					<div class="d-flex justify-content-between small"><span>Margin</span><strong class="<?= $i['margin_pct'] !== NULL && $i['margin_pct'] < $warn ? 'text-danger' : 'text-success' ?>"><?= $i['margin_pct'] !== NULL ? number_format($i['margin_pct'], 1, ',', '.') . '%' : '-' ?></strong></div>
					<?php if ($i['missing_cost']): ?><div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-circle"></i> <?= (int) $i['missing_cost'] ?> bahan belum punya harga, COGS belum lengkap.</div><?php endif; ?>
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
