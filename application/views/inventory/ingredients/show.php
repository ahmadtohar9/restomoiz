<?php defined('BASEPATH') OR exit('No direct script access allowed');
$q = (float) $ing['qty_on_hand'];
$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime("+$expiry_days days"));
?>
<p><a href="<?= site_url('inventory/ingredients') ?>"><i class="bi bi-arrow-left"></i> Daftar bahan baku</a></p>

<div class="row g-3 mb-3">
	<div class="col-lg-8">
		<div class="card h-100">
			<div class="card-body d-flex gap-3">
				<?php if ($ing['image_path']): ?>
					<img src="<?= base_url($ing['image_path']) ?>" alt="" class="rounded border flex-shrink-0" style="width:112px;height:112px;object-fit:cover">
				<?php endif; ?>
				<div class="flex-fill">
					<div class="d-flex flex-wrap align-items-start gap-2">
						<div>
							<h2 class="h5 mb-1"><?= e($ing['name']) ?></h2>
							<div class="text-muted small"><code><?= e($ing['code']) ?></code> · <?= e($ing['category_name'] ?: 'Tanpa kategori') ?>
								<?php if ($ing['status'] !== 'active'): ?><span class="badge text-bg-secondary"><?= e(Ingredient_model::$statuses[$ing['status']]) ?></span><?php endif; ?>
							</div>
						</div>
						<div class="ms-auto d-flex gap-1 flex-wrap">
							<?php if (can('inventory.adjust') && $ing['status'] === 'active'): ?>
								<a class="btn btn-sm btn-outline-success" href="<?= site_url('inventory/stock/in?ingredient_id=' . $ing['id']) ?>"><i class="bi bi-box-arrow-in-down"></i> Masuk</a>
								<a class="btn btn-sm btn-outline-danger" href="<?= site_url('inventory/stock/out?ingredient_id=' . $ing['id']) ?>"><i class="bi bi-box-arrow-up"></i> Keluar</a>
							<?php endif; ?>
							<?php if (can('inventory.edit')): ?>
								<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('inventory/ingredients/edit/' . $ing['id']) ?>"><i class="bi bi-pencil"></i> Edit</a>
							<?php endif; ?>
							<?php if (can('inventory.delete')): ?>
								<?= form_open('inventory/ingredients/delete/' . $ing['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus bahan ' . $ing['name'] . '?')) ?>
									<button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
								<?= form_close() ?>
							<?php endif; ?>
						</div>
					</div>
					<?php if ($ing['description']): ?><p class="mb-2 mt-2"><?= e($ing['description']) ?></p><?php endif; ?>
					<dl class="row small mb-0 mt-2">
						<dt class="col-sm-4">Supplier utama</dt><dd class="col-sm-8"><?= $ing['default_supplier_id'] ? '<a href="' . site_url('inventory/suppliers/show/' . $ing['default_supplier_id']) . '">' . e($ing['supplier_name']) . '</a>' : '-' ?></dd>
						<dt class="col-sm-4">Lokasi</dt><dd class="col-sm-8"><?= e($ing['location'] ?: '-') ?></dd>
						<dt class="col-sm-4">Satuan</dt>
						<dd class="col-sm-8"><?= e($ing['unit']) ?>
							<?php foreach ($units as $u): ?><span class="badge text-bg-light border ms-1">1 <?= e($u['unit']) ?> = <?= qty($u['factor'], 6) ?> <?= e($ing['unit']) ?></span><?php endforeach; ?>
						</dd>
						<dt class="col-sm-4">Min / Max</dt><dd class="col-sm-8"><?= qty($ing['min_stock']) ?> / <?= $ing['max_stock'] > 0 ? qty($ing['max_stock']) : '-' ?></dd>
						<dt class="col-sm-4">Reorder</dt><dd class="col-sm-8">saat stok ≤ <?= qty($ing['reorder_point']) ?>, pesan <?= qty($ing['reorder_qty']) ?> <?= e($ing['unit']) ?></dd>
						<dt class="col-sm-4">Terakhir dihitung</dt><dd class="col-sm-8"><?= tgl($ing['last_counted_at']) ?></dd>
						<?php if ($ing['notes']): ?><dt class="col-sm-4">Catatan</dt><dd class="col-sm-8"><?= nl2br(e($ing['notes'])) ?></dd><?php endif; ?>
					</dl>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-4">
		<div class="card h-100">
			<div class="card-body">
				<div class="stat-label">Stok saat ini</div>
				<div class="stat-value"><?= qty($q) ?> <span class="fs-6 text-muted"><?= e($ing['unit']) ?></span></div>
				<?php if ($q <= 0): ?><span class="badge text-bg-dark">Habis</span>
				<?php elseif ($ing['min_stock'] > 0 && $q < $ing['min_stock']): ?><span class="badge text-bg-danger">Di bawah minimum</span>
				<?php elseif ($ing['reorder_point'] > 0 && $q <= $ing['reorder_point']): ?><span class="badge text-bg-warning">Perlu reorder</span>
				<?php elseif ($ing['max_stock'] > 0 && $q > $ing['max_stock']): ?><span class="badge text-bg-info">Di atas maksimum</span>
				<?php endif; ?>
				<?php if ($can_cost): ?>
					<hr>
					<div class="d-flex justify-content-between small"><span class="text-muted">Nilai stok (FIFO)</span><strong><?= rupiah($ing['stock_value']) ?></strong></div>
					<div class="d-flex justify-content-between small"><span class="text-muted">Harga beli terakhir</span><span><?= rupiah($ing['current_price']) ?> / <?= e($ing['unit']) ?></span></div>
					<div class="d-flex justify-content-between small"><span class="text-muted">Rata-rata nilai</span><span><?= $q > 0 ? rupiah($ing['stock_value'] / $q) : '-' ?> / <?= e($ing['unit']) ?></span></div>
				<?php endif; ?>
				<hr>
				<div class="d-flex justify-content-between small"><span class="text-muted">Masuk terakhir</span><span><?= tgl($ing['last_in_at']) ?></span></div>
				<div class="d-flex justify-content-between small"><span class="text-muted">Keluar terakhir</span><span><?= tgl($ing['last_out_at']) ?></span></div>
			</div>
		</div>
	</div>
</div>

<div class="card mb-3">
	<div class="card-header">Batch tersedia <span class="text-muted small fw-normal">(urutan FIFO: yang atas keluar lebih dulu)</span></div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead><tr><th>Diterima</th><th class="text-end">Sisa</th><th class="text-end">Masuk</th><?php if ($can_cost): ?><th class="text-end">Harga</th><th class="text-end">Nilai sisa</th><?php endif; ?><th>Kedaluwarsa</th><th>Supplier</th><th></th></tr></thead>
			<tbody>
			<?php if (empty($batches)): ?><tr><td colspan="8" class="text-center text-muted py-3">Tidak ada stok.</td></tr><?php endif; ?>
			<?php foreach ($batches as $b): ?>
				<tr>
					<td class="small"><?= tgl($b['received_at']) ?></td>
					<td class="text-end"><?= qty($b['qty_remaining']) ?></td>
					<td class="text-end text-muted small"><?= qty($b['qty_in']) ?></td>
					<?php if ($can_cost): ?>
						<td class="text-end small"><?= rupiah($b['unit_cost']) ?></td>
						<td class="text-end"><?= rupiah($b['qty_remaining'] * $b['unit_cost']) ?></td>
					<?php endif; ?>
					<td>
						<?php if ($b['expiry_date']): ?>
							<?= tgl($b['expiry_date'], FALSE) ?>
							<?php if ($b['expiry_date'] < $today): ?><span class="badge text-bg-danger">lewat</span>
							<?php elseif ($b['expiry_date'] <= $soon): ?><span class="badge text-bg-warning">segera</span><?php endif; ?>
						<?php else: ?>-<?php endif; ?>
					</td>
					<td class="small"><?= e($b['supplier_name'] ?: '-') ?></td>
					<td class="text-end">
						<?php if (can('inventory.adjust') && $b['expiry_date'] && $b['expiry_date'] <= $soon): ?>
							<a class="btn btn-sm btn-outline-danger" href="<?= site_url('inventory/stock/out?ingredient_id=' . $ing['id'] . '&batch_id=' . $b['id']) ?>">Buang</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="card">
	<div class="card-header d-flex flex-wrap align-items-center gap-2">
		<span>Kartu stok</span>
		<form class="ms-auto d-flex gap-1" method="get">
			<input class="form-control form-control-sm" type="date" name="from" value="<?= e($from) ?>">
			<input class="form-control form-control-sm" type="date" name="to" value="<?= e($to) ?>">
			<button class="btn btn-sm btn-outline-secondary">Tampilkan</button>
		</form>
	</div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead><tr><th>Waktu</th><th>Dokumen</th><th>Keterangan</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Saldo</th><?php if ($can_cost): ?><th class="text-end">Nilai</th><?php endif; ?><th>Oleh</th></tr></thead>
			<tbody>
			<?php if (empty($movements)): ?><tr><td colspan="8" class="text-center text-muted py-3">Tidak ada pergerakan di periode ini.</td></tr><?php endif; ?>
			<?php foreach ($movements as $m): ?>
				<tr>
					<td class="small text-nowrap"><?= tgl($m['created_at']) ?></td>
					<td class="small"><code><?= e($m['movement_no']) ?></code></td>
					<td class="small"><?= e(Stock_service::reason_label($m['reason'])) ?>
						<?php if ($m['input_unit'] && $m['input_unit'] !== $ing['unit']): ?><span class="text-muted">(<?= qty($m['input_qty']) ?> <?= e($m['input_unit']) ?>)</span><?php endif; ?>
						<?php if ($m['notes']): ?><div class="text-muted"><?= e($m['notes']) ?></div><?php endif; ?>
					</td>
					<td class="text-end text-success"><?= $m['qty'] > 0 ? qty($m['qty']) : '' ?></td>
					<td class="text-end text-danger"><?= $m['qty'] < 0 ? qty(-$m['qty']) : '' ?></td>
					<td class="text-end fw-medium"><?= qty($m['qty_after']) ?></td>
					<?php if ($can_cost): ?><td class="text-end small"><?= rupiah($m['total_cost']) ?></td><?php endif; ?>
					<td class="small"><?= e($m['user_name'] ?: '-') ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
