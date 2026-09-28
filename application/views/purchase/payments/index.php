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
