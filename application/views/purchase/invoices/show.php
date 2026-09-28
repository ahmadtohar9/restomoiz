<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is = Purchase_service::$invoice_status[$inv['status']];
$pay = array('unpaid' => 'Belum dibayar', 'partial' => 'Dibayar sebagian', 'paid' => 'Lunas');
$overdue = $inv['status'] === 'approved' && $inv['payment_status'] !== 'paid' && $inv['due_date'] < date('Y-m-d');
$expected = $gr_amount - $other_inv;
?>
<p><a href="<?= site_url('purchase/invoices') ?>"><i class="bi bi-arrow-left"></i> Daftar invoice</a></p>

<div class="row g-3 mb-3">
	<div class="col-lg-7">
		<div class="card h-100">
			<div class="card-body">
				<div class="d-flex flex-wrap gap-2 align-items-start">
					<div>
						<h2 class="h5 mb-1"><?= e($inv['invoice_number']) ?> <span class="badge text-bg-<?= $is[1] ?> align-middle"><?= e($is[0]) ?></span></h2>
						<div class="small text-muted"><?= e($inv['supplier_name']) ?> · PO <a href="<?= site_url('purchase/orders/show/' . $inv['po_id']) ?>"><?= e($inv['po_number']) ?></a></div>
					</div>
					<?php if ($inv['attachment_path']): ?>
						<a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= file_url($inv['attachment_path']) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Lihat file invoice</a>
					<?php endif; ?>
				</div>
				<dl class="row small mb-0 mt-3">
					<dt class="col-sm-4">Tanggal invoice</dt><dd class="col-sm-8"><?= tgl($inv['invoice_date'], FALSE) ?></dd>
					<dt class="col-sm-4">Jatuh tempo</dt><dd class="col-sm-8 <?= $overdue ? 'text-danger fw-medium' : '' ?>"><?= tgl($inv['due_date'], FALSE) ?> (<?= e($inv['payment_terms']) ?>)<?= $overdue ? ' — lewat jatuh tempo' : '' ?></dd>
					<dt class="col-sm-4">Nilai</dt><dd class="col-sm-8 fs-6"><strong><?= rupiah($inv['amount']) ?></strong></dd>
					<dt class="col-sm-4">Pembayaran</dt><dd class="col-sm-8"><?= e($pay[$inv['payment_status']]) ?> · dibayar <?= rupiah($inv['paid_amount']) ?>, sisa <?= rupiah($inv['amount'] - $inv['paid_amount']) ?></dd>
					<dt class="col-sm-4">Dicatat</dt><dd class="col-sm-8"><?= e($inv['created_by_name']) ?>, <?= tgl($inv['created_at']) ?></dd>
					<?php if ($inv['reviewed_by']): ?><dt class="col-sm-4">Direview</dt><dd class="col-sm-8"><?= e($inv['reviewed_by_name']) ?>, <?= tgl($inv['reviewed_at']) ?></dd><?php endif; ?>
					<?php if ($inv['review_note']): ?><dt class="col-sm-4">Catatan review</dt><dd class="col-sm-8"><?= e($inv['review_note']) ?></dd><?php endif; ?>
					<?php if ($inv['notes']): ?><dt class="col-sm-4">Catatan</dt><dd class="col-sm-8"><?= e($inv['notes']) ?></dd><?php endif; ?>
				</dl>
			</div>
		</div>
	</div>
	<div class="col-lg-5">
		<div class="card h-100 <?= $match_now === 'matched' ? 'border-success' : 'border-danger' ?>">
			<div class="card-header d-flex">3-way matching
				<span class="ms-auto"><?= $match_now === 'matched' ? '<span class="badge text-bg-success">Cocok</span>' : '<span class="badge text-bg-danger">Tidak cocok</span>' ?></span></div>
			<ul class="list-group list-group-flush small">
				<li class="list-group-item d-flex"><span>PO</span><span class="ms-auto"><?= rupiah($inv['po_total']) ?></span></li>
				<li class="list-group-item d-flex"><span>Barang diterima (GR)</span><span class="ms-auto"><?= rupiah($gr_amount) ?></span></li>
				<li class="list-group-item d-flex"><span>Invoice lain untuk PO ini</span><span class="ms-auto"><?= rupiah($other_inv) ?></span></li>
				<li class="list-group-item d-flex"><span>Seharusnya ditagih</span><strong class="ms-auto"><?= rupiah($expected) ?></strong></li>
				<li class="list-group-item d-flex"><span>Invoice ini</span><strong class="ms-auto"><?= rupiah($inv['amount']) ?></strong></li>
				<li class="list-group-item d-flex <?= $match_now === 'matched' ? '' : 'text-danger' ?>"><span>Selisih</span><strong class="ms-auto"><?= rupiah($inv['amount'] - $expected) ?></strong></li>
			</ul>
			<div class="card-body small text-muted py-2">Toleransi <?= $tolerance ?>%.</div>
		</div>
	</div>
</div>

<?php if (in_array($inv['status'], array('pending', 'on_hold'), TRUE) && can('purchase.invoice')): ?>
<div class="card mb-3 border-warning">
	<div class="card-body">
		<h3 class="h6">Review invoice</h3>
		<?php if ($match_now === 'mismatch'): ?><p class="small text-danger mb-2">Nilai tidak cocok dengan barang diterima. Untuk menyetujui, isi alasannya (mis. ongkos kirim, koreksi harga yang sudah dikonfirmasi).</p><?php endif; ?>
		<?= form_open('purchase/invoices/review/' . $inv['id'], array('class' => 'd-flex flex-wrap gap-2')) ?>
			<input class="form-control form-control-sm" style="max-width: 420px" name="note" maxlength="255" placeholder="Catatan / alasan">
			<button class="btn btn-sm btn-success" name="action" value="approve"><i class="bi bi-check2"></i> Setujui</button>
			<?php if ($inv['status'] === 'pending'): ?><button class="btn btn-sm btn-outline-secondary" name="action" value="hold"><i class="bi bi-pause"></i> Tahan</button><?php endif; ?>
			<button class="btn btn-sm btn-outline-danger" name="action" value="reject" data-confirm-click="Tolak invoice ini?"><i class="bi bi-x"></i> Tolak</button>
		<?= form_close() ?>
	</div>
</div>
<?php endif; ?>

<div class="card">
	<div class="card-header d-flex align-items-center">
		<span>Pembayaran</span>
		<?php if ($inv['status'] === 'approved' && $payable > 0 && can('purchase.payment')): ?>
			<a class="btn btn-sm btn-primary ms-auto" href="<?= site_url('purchase/payments/create/' . $inv['id']) ?>"><i class="bi bi-credit-card"></i> Catat Pembayaran</a>
		<?php endif; ?>
	</div>
	<?php $this->load->view('purchase/payments/_table', array('rows' => $payments, 'back' => 'invoice')); ?>
</div>
