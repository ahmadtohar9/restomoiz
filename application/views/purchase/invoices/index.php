<?php defined('BASEPATH') OR exit('No direct script access allowed');
$pay = array('unpaid' => array('Belum dibayar', 'secondary'), 'partial' => array('Sebagian', 'info'), 'paid' => array('Lunas', 'success'));
?>
<?php if ( ! empty($uninvoiced)): ?>
<div class="card mb-3 border-warning">
	<div class="card-header d-flex flex-wrap align-items-center gap-2 bg-warning-subtle">
		<span><i class="bi bi-hourglass-split text-warning-emphasis"></i> Barang sudah diterima, belum ada invoice</span>
		<span class="badge rounded-pill text-bg-warning"><?= count($uninvoiced) ?></span>
		<span class="small fw-normal text-muted ms-auto">Hutang & jatuh tempo (mis. NET 30) baru dihitung setelah invoice supplier dicatat.</span>
	</div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0" data-dt="false">
			<thead><tr><th>PO</th><th>Supplier</th><th>Termin</th><th>Barang diterima</th><th class="text-end">Nilai diterima</th><th class="text-end">Sudah ditagih</th><th class="text-end">Belum ditagih</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($uninvoiced as $u): ?>
				<tr>
					<td><a href="<?= site_url('purchase/orders/show/' . $u['id']) ?>"><?= e($u['po_number']) ?></a></td>
					<td><?= e($u['supplier_name']) ?></td>
					<td><?= e(isset(Supplier_model::$payment_terms[$u['payment_terms']]) ? Supplier_model::$payment_terms[$u['payment_terms']] : $u['payment_terms']) ?></td>
					<td class="small"><?= tgl($u['last_receipt'], FALSE) ?></td>
					<td class="text-end"><?= rupiah($u['gr_amount']) ?></td>
					<td class="text-end text-muted"><?= (float) $u['invoiced'] > 0 ? rupiah($u['invoiced']) : '-' ?></td>
					<td class="text-end fw-semibold"><?= rupiah($u['uninvoiced']) ?></td>
					<td class="text-end"><a class="btn btn-sm btn-primary" href="<?= site_url('purchase/invoices/create/' . $u['id']) ?>"><i class="bi bi-receipt"></i> Catat invoice</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php endif; ?>
<form class="card mb-3" method="get" action="<?= site_url('purchase/invoices') ?>">
	<div class="card-body row g-2 align-items-end">
		<div class="col-6 col-md-3">
			<label class="form-label small" for="q">Cari</label>
			<input class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="No. invoice / PO">
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="status">Status review</label>
			<select class="form-select form-select-sm" id="status" name="status">
				<option value="">Semua</option>
				<?php foreach (Purchase_service::$invoice_status as $k => $s): ?><option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($s[0]) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="payment">Pembayaran</label>
			<select class="form-select form-select-sm" id="payment" name="payment">
				<option value="">Semua</option>
				<option value="outstanding" <?= $filters['payment'] === 'outstanding' ? 'selected' : '' ?>>Belum lunas (disetujui)</option>
				<?php foreach ($pay as $k => $p): ?><option value="<?= $k ?>" <?= $filters['payment'] === $k ? 'selected' : '' ?>><?= e($p[0]) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-3">
			<label class="form-label small" for="supplier_id">Supplier</label>
			<select class="form-select form-select-sm" id="supplier_id" name="supplier_id">
				<option value="">Semua</option>
				<?php foreach ($suppliers as $id => $name): ?><option value="<?= $id ?>" <?= (int) $filters['supplier_id'] === (int) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-12 col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button></div>
	</div>
</form>
<p class="small text-muted">Invoice dicatat dari halaman PO (tombol <em>Catat Invoice</em>) setelah barang diterima.</p>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>No. invoice</th><th>Supplier</th><th>PO</th><th>Jatuh tempo</th><th class="text-end">Nilai</th><th class="text-end">Sisa</th><th>Cocok</th><th>Status</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">Tidak ada invoice.</td></tr><?php endif; ?>
			<?php foreach ($rows as $v): $is = Purchase_service::$invoice_status[$v['status']];
				$sisa = (float) $v['amount'] - (float) $v['paid_amount'];
				$overdue = $v['status'] === 'approved' && $v['payment_status'] !== 'paid' && $v['due_date'] < date('Y-m-d'); ?>
				<tr>
					<td><a class="fw-medium" href="<?= site_url('purchase/invoices/show/' . $v['id']) ?>"><?= e($v['invoice_number']) ?></a><div class="small text-muted"><?= tgl($v['invoice_date'], FALSE) ?></div></td>
					<td><?= e($v['supplier_name']) ?></td>
					<td class="small"><a href="<?= site_url('purchase/orders/show/' . $v['po_id']) ?>"><?= e($v['po_number']) ?></a></td>
					<td class="small <?= $overdue ? 'text-danger fw-medium' : '' ?>"><?= tgl($v['due_date'], FALSE) ?><?= $overdue ? ' (lewat)' : '' ?></td>
					<td class="text-end"><?= rupiah($v['amount']) ?></td>
					<td class="text-end"><?= $v['status'] === 'rejected' ? '-' : rupiah($sisa) ?>
						<?php if ($v['pending_payment'] > 0): ?><div class="small text-warning-emphasis"><?= rupiah($v['pending_payment']) ?> menunggu verifikasi</div><?php endif; ?></td>
					<td><?= $v['match_status'] === 'matched' ? '<i class="bi bi-check-circle-fill text-success" title="Cocok"></i>' : '<i class="bi bi-exclamation-triangle-fill text-danger" title="Tidak cocok"></i>' ?></td>
					<td><span class="badge text-bg-<?= $is[1] ?>"><?= e($is[0]) ?></span>
						<?php if ($v['status'] === 'approved'): ?><span class="badge text-bg-<?= $pay[$v['payment_status']][1] ?>"><?= e($pay[$v['payment_status']][0]) ?></span><?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
