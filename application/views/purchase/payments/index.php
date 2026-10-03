<?php defined('BASEPATH') OR exit('No direct script access allowed');
$total = array('current_due' => 0, 'd1_30' => 0, 'd31_60' => 0, 'd61_90' => 0, 'd90' => 0, 'total' => 0);
foreach ($aging as $a) { foreach ($total as $k => $v) { $total[$k] += (float) $a[$k]; } }
?>
<div class="row g-3 mb-3">
	<?php foreach (array('current_due' => array('Belum jatuh tempo', ''), 'd1_30' => array('Lewat 1–30 hari', 'text-warning-emphasis'), 'd31_60' => array('Lewat 31–60 hari', 'text-danger'),
		'd61_90' => array('Lewat 61–90 hari', 'text-danger'), 'd90' => array('Lewat > 90 hari', 'text-danger')) as $k => $l): ?>
		<div class="col-6 col-lg"><div class="card h-100"><div class="card-body py-2">
			<div class="stat-label"><?= $l[0] ?></div><div class="fw-semibold <?= $total[$k] > 0 ? $l[1] : '' ?>"><?= rupiah($total[$k]) ?></div>
		</div></div></div>
	<?php endforeach; ?>
	<div class="col-12 col-lg"><div class="card h-100 border-primary"><div class="card-body py-2">
		<div class="stat-label">Total hutang supplier</div><div class="fw-bold fs-5"><?= rupiah($total['total']) ?></div>
	</div></div></div>
</div>

<?php if ( ! empty($to_review)): ?>
	<div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
		<i class="bi bi-info-circle"></i> <span><strong><?= (int) $to_review ?> invoice</strong> masih menunggu review. Invoice harus disetujui dulu sebelum bisa dibayar.</span>
		<a class="btn btn-sm btn-outline-primary ms-auto" href="<?= site_url('purchase/invoices?status=pending') ?>">Review invoice</a>
	</div>
<?php endif; ?>
<?php if ( ! empty($to_pay)): ?>
<div class="card mb-3 border-primary">
	<div class="card-header d-flex align-items-center gap-2"><i class="bi bi-cash-stack text-primary"></i> Invoice siap dibayar <span class="badge rounded-pill text-bg-primary"><?= count($to_pay) ?></span></div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0" data-dt="false">
			<thead><tr><th>Invoice</th><th>Supplier</th><th>PO</th><th>Jatuh tempo</th><th class="text-end">Sisa tagihan</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($to_pay as $v): $sisa = (float) $v['amount'] - (float) $v['paid_amount']; $late = $v['due_date'] < date('Y-m-d'); $days = (int) floor((strtotime($v['due_date']) - strtotime(date('Y-m-d'))) / 86400); ?>
				<tr>
					<td><a href="<?= site_url('purchase/invoices/show/' . $v['id']) ?>"><?= e($v['invoice_number']) ?></a></td>
					<td><?= e($v['supplier_name']) ?></td>
					<td class="small"><?= e($v['po_number']) ?></td>
					<td class="<?= $late ? 'text-danger fw-semibold' : '' ?>"><?= tgl($v['due_date'], FALSE) ?>
						<div class="small <?= $late ? 'text-danger' : 'text-muted' ?>"><?= $late ? 'lewat ' . abs($days) . ' hari' : ($days === 0 ? 'hari ini' : $days . ' hari lagi') ?></div></td>
					<td class="text-end fw-semibold"><?= rupiah($sisa) ?><?= (float) $v['pending_payment'] > 0 ? '<div class="small text-muted fw-normal">' . rupiah($v['pending_payment']) . ' menunggu verifikasi</div>' : '' ?></td>
					<td class="text-end"><a class="btn btn-sm btn-primary" href="<?= site_url('purchase/payments/create/' . $v['id']) ?>"><i class="bi bi-wallet2"></i> Bayar</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
	<?php foreach (array('pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak', '' => 'Semua') as $k => $label): ?>
		<a class="btn btn-sm <?= $filters['status'] === $k ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= site_url('purchase/payments?status=' . $k) ?>"><?= e($label) ?></a>
	<?php endforeach; ?>
	<a class="btn btn-sm btn-outline-primary ms-auto" href="<?= site_url('purchase/invoices?payment=outstanding') ?>"><i class="bi bi-receipt"></i> Invoice belum lunas</a>
</div>

<div class="card">
	<?php $this->load->view('purchase/payments/_table', array('rows' => $rows, 'back' => 'list')); ?>
</div>
<p class="small text-muted mt-2">Pembayaran baru dicatat dari halaman invoice. Verifikasi harus dilakukan oleh orang yang berbeda dari penginput.</p>
