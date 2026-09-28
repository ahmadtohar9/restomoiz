<?php defined('BASEPATH') OR exit('No direct script access allowed');
$qs = function (array $extra = array()) use ($filters) {
	return http_build_query(array_filter(array_merge($filters, $extra), 'strlen'));
};
?>
<form class="card mb-3" method="get" action="<?= site_url('purchase/orders') ?>">
	<div class="card-body row g-2 align-items-end">
		<div class="col-6 col-md-2">
			<label class="form-label small" for="q">No. PO</label>
			<input class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>">
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="status">Status</label>
			<select class="form-select form-select-sm" id="status" name="status">
				<option value="">Semua</option>
				<option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>>Menunggu barang</option>
				<?php foreach (Purchase_service::$po_status as $k => $s): ?>
					<option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($s[0]) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-12 col-md-3">
			<label class="form-label small" for="supplier_id">Supplier</label>
			<select class="form-select form-select-sm" id="supplier_id" name="supplier_id">
				<option value="">Semua</option>
				<?php foreach ($suppliers as $id => $name): ?><option value="<?= $id ?>" <?= (int) $filters['supplier_id'] === (int) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="from">Dari</label>
			<input class="form-control form-control-sm" type="date" id="from" name="from" value="<?= e($filters['from']) ?>">
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="to">Sampai</label>
			<input class="form-control form-control-sm" type="date" id="to" name="to" value="<?= e($filters['to']) ?>">
		</div>
		<div class="col-12 col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i></button></div>
	</div>
</form>

<div class="d-flex mb-3">
	<span class="small text-muted align-self-center"><?= number_format($total, 0, ',', '.') ?> PO</span>
	<?php if (can('purchase.create')): ?>
		<div class="ms-auto d-flex gap-2">
			<a class="btn btn-outline-primary btn-sm" href="<?= site_url('purchase/orders/create?reorder=1') ?>"><i class="bi bi-magic"></i> Dari saran reorder</a>
			<a class="btn btn-primary btn-sm" href="<?= site_url('purchase/orders/create') ?>"><i class="bi bi-plus-lg"></i> PO Baru</a>
		</div>
	<?php endif; ?>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0" data-dt="true" data-dt-paging="false">
			<thead><tr><th>No. PO</th><th>Tanggal</th><th>Supplier</th><th class="text-end">Total</th><th class="text-end">Diterima</th><th>Kirim</th><th>Status</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada PO.</td></tr><?php endif; ?>
			<?php foreach ($rows as $po): $st = Purchase_service::$po_status[$po['status']];
				$late = in_array($po['status'], array('approved', 'partial'), TRUE) && $po['expected_delivery'] && $po['expected_delivery'] < date('Y-m-d'); ?>
				<tr>
					<td><a class="fw-medium" href="<?= site_url('purchase/orders/show/' . $po['id']) ?>"><?= e($po['po_number']) ?></a>
						<div class="small text-muted"><?= e($po['created_by_name']) ?></div></td>
					<td class="small"><?= tgl($po['po_date'], FALSE) ?></td>
					<td><?= e($po['supplier_name']) ?></td>
					<td class="text-end"><?= rupiah($po['total_amount']) ?></td>
					<td class="text-end small"><?= $po['received_amount'] > 0 ? rupiah($po['received_amount']) : '-' ?></td>
					<td class="small <?= $late ? 'text-danger fw-medium' : '' ?>"><?= $po['expected_delivery'] ? tgl($po['expected_delivery'], FALSE) : '-' ?><?= $late ? ' (terlambat)' : '' ?></td>
					<td><span class="badge text-bg-<?= $st[1] ?> <?= $st[1] === 'light' ? 'border' : '' ?>"><?= e($st[0]) ?></span>
						<?php if ($po['status'] === 'submitted'): ?><div class="small text-muted"><?= $po['approved1_by'] ? 'menunggu Owner' : 'menunggu Manajer' ?></div><?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ($pages > 1): ?>
		<div class="card-footer d-flex justify-content-between align-items-center small">
			<span>Halaman <?= $page ?> dari <?= $pages ?></span>
			<div class="btn-group btn-group-sm">
				<a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= site_url('purchase/orders?' . $qs(array('page' => $page - 1))) ?>">Sebelumnya</a>
				<a class="btn btn-outline-secondary <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= site_url('purchase/orders?' . $qs(array('page' => $page + 1))) ?>">Berikutnya</a>
			</div>
		</div>
	<?php endif; ?>
</div>
