<?php defined('BASEPATH') OR exit('No direct script access allowed');
$stock_badge = function ($r) {
	$q = (float) $r['qty_on_hand'];
	if ($q <= 0) return '<span class="badge text-bg-dark">Habis</span>';
	if ($r['min_stock'] > 0 && $q < $r['min_stock']) return '<span class="badge text-bg-danger">Di bawah min</span>';
	if ($r['reorder_point'] > 0 && $q <= $r['reorder_point']) return '<span class="badge text-bg-warning">Reorder</span>';
	if ($r['max_stock'] > 0 && $q > $r['max_stock']) return '<span class="badge text-bg-info">Di atas max</span>';
	return '';
};
$export_qs = http_build_query(array_filter(array('q' => $filters['q'], 'category_id' => $filters['category_id'], 'status' => $filters['status'])));
?>
<form class="card mb-3" method="get" action="<?= site_url('inventory/ingredients') ?>">
	<div class="card-body row g-2 align-items-end">
		<div class="col-12 col-md-3">
			<label class="form-label small" for="q">Cari</label>
			<input class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama atau kode">
		</div>
		<div class="col-6 col-md-3">
			<label class="form-label small" for="category_id">Kategori</label>
			<select class="form-select form-select-sm" id="category_id" name="category_id">
				<option value="">Semua</option>
				<?php foreach ($categories as $id => $path): ?>
					<option value="<?= $id ?>" <?= (int) $filters['category_id'] === (int) $id ? 'selected' : '' ?>><?= e($path) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="status">Status</label>
			<select class="form-select form-select-sm" id="status" name="status">
				<option value="">Semua</option>
				<?php foreach (Ingredient_model::$statuses as $k => $v): ?>
					<option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="stock">Kondisi stok</label>
			<select class="form-select form-select-sm" id="stock" name="stock">
				<option value="">Semua</option>
				<?php foreach (array('low' => 'Di bawah minimum', 'reorder' => 'Perlu reorder', 'over' => 'Di atas maksimum', 'empty' => 'Habis') as $k => $v): ?>
					<option value="<?= $k ?>" <?= $filters['stock'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2 d-flex gap-1">
			<button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
			<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('inventory/ingredients/export?' . $export_qs) ?>" title="Export CSV"><i class="bi bi-download"></i></a>
		</div>
	</div>
</form>

<div class="d-flex flex-wrap gap-2 mb-3">
	<span class="text-muted small align-self-center"><?= count($rows) ?> bahan</span>
	<div class="ms-auto d-flex gap-2">
		<?php if (can('inventory.adjust')): ?>
			<a class="btn btn-outline-success btn-sm" href="<?= site_url('inventory/stock/in') ?>"><i class="bi bi-box-arrow-in-down"></i> Stok Masuk</a>
			<a class="btn btn-outline-danger btn-sm" href="<?= site_url('inventory/stock/out') ?>"><i class="bi bi-box-arrow-up"></i> Stok Keluar</a>
		<?php endif; ?>
		<?php if (can('inventory.create')): ?>
			<a class="btn btn-primary btn-sm" href="<?= site_url('inventory/ingredients/create') ?>"><i class="bi bi-plus-lg"></i> Bahan Baru</a>
		<?php endif; ?>
	</div>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead>
				<tr>
					<th>Bahan</th><th>Kategori</th><th class="text-end">Stok</th><th class="text-end d-none d-lg-table-cell">Min / Reorder</th>
					<?php if ($can_cost): ?><th class="text-end">Harga / satuan</th><th class="text-end">Nilai</th><?php endif; ?>
					<th class="d-none d-xl-table-cell">Supplier</th>
				</tr>
			</thead>
			<tbody>
			<?php if (empty($rows)): ?>
				<tr><td colspan="7" class="text-center text-muted py-4">Belum ada bahan baku<?= $filters['q'] || $filters['category_id'] || $filters['stock'] ? ' yang cocok dengan filter' : '' ?>.</td></tr>
			<?php endif; ?>
			<?php foreach ($rows as $r): ?>
				<tr class="<?= $r['status'] !== 'active' ? 'text-muted' : '' ?>">
					<td>
						<a class="fw-medium" href="<?= site_url('inventory/ingredients/show/' . $r['id']) ?>"><?= e($r['name']) ?></a>
						<div class="small text-muted"><code><?= e($r['code']) ?></code>
							<?php if ($r['status'] !== 'active'): ?><span class="badge text-bg-secondary"><?= e(Ingredient_model::$statuses[$r['status']]) ?></span><?php endif; ?>
						</div>
					</td>
					<td class="small"><?= e($r['category_name'] ?: '-') ?></td>
					<td class="text-end text-nowrap">
						<strong><?= qty($r['qty_on_hand']) ?></strong> <span class="text-muted small"><?= e($r['unit']) ?></span>
						<div><?= $stock_badge($r) ?></div>
					</td>
					<td class="text-end small text-muted d-none d-lg-table-cell"><?= qty($r['min_stock']) ?> / <?= qty($r['reorder_point']) ?></td>
					<?php if ($can_cost): ?>
						<td class="text-end small"><?= rupiah($r['current_price']) ?></td>
						<td class="text-end"><?= rupiah($r['stock_value']) ?></td>
					<?php endif; ?>
					<td class="small d-none d-xl-table-cell"><?= e($r['supplier_name'] ?: '-') ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
