<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="card mb-3">
	<div class="card-header">PO menunggu barang datang <span class="badge text-bg-primary"><?= count($waiting) ?></span></div>
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>No. PO</th><th>Supplier</th><th>Tanggal kirim</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
			<tbody>
			<?php if (empty($waiting)): ?><tr><td colspan="6" class="text-center text-muted py-3">Tidak ada PO yang menunggu barang.</td></tr><?php endif; ?>
			<?php foreach ($waiting as $po): $late = $po['expected_delivery'] && $po['expected_delivery'] < date('Y-m-d'); ?>
				<tr>
					<td><a href="<?= site_url('purchase/orders/show/' . $po['id']) ?>"><?= e($po['po_number']) ?></a></td>
					<td><?= e($po['supplier_name']) ?></td>
					<td class="<?= $late ? 'text-danger fw-medium' : '' ?>"><?= $po['expected_delivery'] ? tgl($po['expected_delivery'], FALSE) : '-' ?><?= $late ? ' (terlambat)' : '' ?></td>
					<td class="text-end"><?= rupiah($po['total_amount']) ?></td>
					<td><span class="badge text-bg-<?= Purchase_service::$po_status[$po['status']][1] ?>"><?= e(Purchase_service::$po_status[$po['status']][0]) ?></span></td>
					<td class="text-end"><?php if (can('purchase.receive')): ?><a class="btn btn-sm btn-success" href="<?= site_url('purchase/receipts/create/' . $po['id']) ?>"><i class="bi bi-box-arrow-in-down"></i> Terima</a><?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="card">
	<div class="card-header d-flex flex-wrap align-items-center gap-2">
		<span>Riwayat penerimaan</span>
		<form class="ms-auto d-flex gap-1" method="get">
			<input class="form-control form-control-sm" type="date" name="from" value="<?= e($filters['from']) ?>">
			<input class="form-control form-control-sm" type="date" name="to" value="<?= e($filters['to']) ?>">
			<button class="btn btn-sm btn-outline-secondary">Tampilkan</button>
		</form>
	</div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead><tr><th>No. GR</th><th>Tanggal</th><th>PO</th><th>Supplier</th><th>Surat jalan</th><th class="text-end">Nilai</th><th>Diterima oleh</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-3">Belum ada penerimaan di periode ini.</td></tr><?php endif; ?>
			<?php foreach ($rows as $g): ?>
				<tr>
					<td><a href="<?= site_url('purchase/orders/show/' . $g['po_id'] . '#gr-' . $g['id']) ?>"><?= e($g['gr_number']) ?></a></td>
					<td class="small"><?= tgl($g['received_date'], FALSE) ?></td>
					<td class="small"><?= e($g['po_number']) ?></td>
					<td><?= e($g['supplier_name']) ?></td>
					<td class="small"><?= e($g['delivery_note_no'] ?: '-') ?></td>
					<td class="text-end"><?= rupiah($g['amount']) ?></td>
					<td class="small"><?= e($g['received_by_name']) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
