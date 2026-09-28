<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<p><a href="<?= site_url('inventory/suppliers') ?>"><i class="bi bi-arrow-left"></i> Daftar supplier</a></p>

<div class="row g-3 mb-3">
	<div class="col-lg-7">
		<div class="card h-100">
			<div class="card-body">
				<div class="d-flex flex-wrap gap-2 align-items-start">
					<div>
						<h2 class="h5 mb-1"><?= e($s['name']) ?></h2>
						<div class="small text-muted"><code><?= e($s['code']) ?></code> <?= $s['is_active'] ? '' : '<span class="badge text-bg-secondary">nonaktif</span>' ?></div>
					</div>
					<?php if (can('inventory.supplier')): ?>
						<div class="ms-auto d-flex gap-1">
							<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('inventory/suppliers/edit/' . $s['id']) ?>"><i class="bi bi-pencil"></i> Edit</a>
							<?= form_open('inventory/suppliers/delete/' . $s['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus supplier ' . $s['name'] . '?')) ?>
								<button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
							<?= form_close() ?>
						</div>
					<?php endif; ?>
				</div>
				<dl class="row small mb-0 mt-3">
					<dt class="col-sm-4">Contact person</dt><dd class="col-sm-8"><?= e($s['contact_person'] ?: '-') ?></dd>
					<dt class="col-sm-4">Telepon</dt><dd class="col-sm-8"><?= e($s['phone'] ?: '-') ?></dd>
					<dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= $s['email'] ? '<a href="mailto:' . e($s['email']) . '">' . e($s['email']) . '</a>' : '-' ?></dd>
					<dt class="col-sm-4">Alamat</dt><dd class="col-sm-8"><?= e(trim($s['address'] . ', ' . $s['city'], ', ') ?: '-') ?></dd>
					<dt class="col-sm-4">Termin</dt><dd class="col-sm-8"><?= e(isset(Supplier_model::$payment_terms[$s['payment_terms']]) ? Supplier_model::$payment_terms[$s['payment_terms']] : $s['payment_terms']) ?></dd>
					<dt class="col-sm-4">Minimum order</dt><dd class="col-sm-8"><?= $s['min_order_amount'] > 0 ? rupiah($s['min_order_amount']) : '-' ?></dd>
					<dt class="col-sm-4">Lead time</dt><dd class="col-sm-8"><?= (int) $s['lead_time_days'] ?> hari</dd>
					<dt class="col-sm-4">Kualitas</dt><dd class="col-sm-8"><?= $s['quality_score'] ? str_repeat('★', (int) $s['quality_score']) . ' (' . (int) $s['quality_score'] . '/5)' : 'belum dinilai' ?></dd>
					<?php if ($s['notes']): ?><dt class="col-sm-4">Catatan</dt><dd class="col-sm-8"><?= nl2br(e($s['notes'])) ?></dd><?php endif; ?>
				</dl>
			</div>
		</div>
	</div>
	<div class="col-lg-5">
		<div class="card h-100">
			<div class="card-body">
				<div class="stat-label">Penerimaan barang</div>
				<div class="stat-value"><?= (int) $stats['receipts'] ?> <span class="fs-6 text-muted">dokumen</span></div>
				<?php if ($can_cost): ?><div class="small">Total nilai: <strong><?= rupiah($stats['total']) ?></strong></div><?php endif; ?>
				<div class="small text-muted">Terakhir: <?= tgl($stats['last_receipt']) ?></div>
				<?php if (can('inventory.adjust')): ?>
					<a class="btn btn-sm btn-outline-success mt-3" href="<?= site_url('inventory/stock/in?supplier_id=' . $s['id']) ?>"><i class="bi bi-box-arrow-in-down"></i> Catat penerimaan</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<div class="row g-3">
	<div class="col-lg-6">
		<div class="card h-100">
			<div class="card-header">Bahan dengan supplier utama ini</div>
			<ul class="list-group list-group-flush">
				<?php if (empty($ingredients)): ?><li class="list-group-item text-muted small">Belum ada.</li><?php endif; ?>
				<?php foreach ($ingredients as $i): ?>
					<li class="list-group-item d-flex">
						<a href="<?= site_url('inventory/ingredients/show/' . $i['id']) ?>"><?= e($i['name']) ?></a>
						<span class="ms-auto small text-muted"><?= qty($i['qty_on_hand']) ?> <?= e($i['unit']) ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<?php if ($can_cost): ?>
	<div class="col-lg-6">
		<div class="card h-100">
			<div class="card-header">Harga terakhir dari supplier ini</div>
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<thead><tr><th>Bahan</th><th class="text-end">Harga</th><th>Tanggal</th></tr></thead>
					<tbody>
					<?php if (empty($prices)): ?><tr><td colspan="3" class="text-muted small text-center py-3">Belum ada riwayat pembelian.</td></tr><?php endif; ?>
					<?php foreach ($prices as $p): ?>
						<tr>
							<td><a href="<?= site_url('inventory/ingredients/show/' . $p['id']) ?>"><?= e($p['name']) ?></a></td>
							<td class="text-end"><?= rupiah($p['price']) ?> <span class="text-muted small">/ <?= e($p['unit']) ?></span></td>
							<td class="small text-muted"><?= tgl($p['recorded_at'], FALSE) ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>
