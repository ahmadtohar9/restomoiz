<?php defined('BASEPATH') OR exit('No direct script access allowed');
$expected = max(0, $amounts['gr_amount'] - $amounts['invoiced']);
?>
<p><a href="<?= site_url('purchase/orders/show/' . $po['id']) ?>"><i class="bi bi-arrow-left"></i> Kembali ke <?= e($po['po_number']) ?></a></p>

<div class="row g-3">
	<div class="col-lg-7">
		<?= form_open_multipart('purchase/invoices/create/' . $po['id'], array('class' => 'card')) ?>
			<div class="card-body row g-3">
				<div class="col-md-6">
					<label class="form-label" for="invoice_number">No. invoice supplier</label>
					<input class="form-control" id="invoice_number" name="invoice_number" value="<?= e($input['invoice_number']) ?>" required maxlength="50">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="invoice_date">Tanggal invoice</label>
					<input class="form-control" type="date" id="invoice_date" name="invoice_date" value="<?= e($input['invoice_date']) ?>" required>
					<div class="form-text">Jatuh tempo dihitung dari termin <?= e($po['payment_terms']) ?> (<?= terms_days($po['payment_terms']) ?> hari).</div>
				</div>
				<div class="col-md-6">
					<label class="form-label" for="amount">Nilai invoice</label>
					<div class="input-group"><span class="input-group-text">Rp</span>
						<input class="form-control" type="number" step="any" min="0" id="amount" name="amount" value="<?= e(rtrim(rtrim(number_format((float) $input['amount'], 2, '.', ''), '0'), '.')) ?>" required
							data-expected="<?= e($expected) ?>" data-tolerance="<?= e($tolerance) ?>"></div>
					<div class="form-text" id="amount-check"></div>
				</div>
				<div class="col-md-6">
					<label class="form-label" for="attachment">File invoice <span class="text-muted small">(PDF / foto)</span></label>
					<input class="form-control" type="file" id="attachment" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf">
				</div>
				<div class="col-12">
					<label class="form-label" for="notes">Catatan</label>
					<input class="form-control" id="notes" name="notes" value="<?= e($input['notes']) ?>" maxlength="255">
				</div>
			</div>
			<div class="card-footer bg-transparent d-flex gap-2">
				<button class="btn btn-primary" type="submit">Simpan Invoice</button>
				<a class="btn btn-outline-secondary" href="<?= site_url('purchase/orders/show/' . $po['id']) ?>">Batal</a>
			</div>
		<?= form_close() ?>
	</div>
	<div class="col-lg-5">
		<div class="card">
			<div class="card-header">3-way matching</div>
			<ul class="list-group list-group-flush">
				<li class="list-group-item d-flex"><span>Nilai PO</span><strong class="ms-auto"><?= rupiah($po['total_amount']) ?></strong></li>
				<li class="list-group-item d-flex"><span>Nilai barang diterima (GR)</span><strong class="ms-auto"><?= rupiah($amounts['gr_amount']) ?></strong></li>
				<li class="list-group-item d-flex"><span>Sudah ditagih (invoice lain)</span><span class="ms-auto"><?= rupiah($amounts['invoiced']) ?></span></li>
				<li class="list-group-item d-flex bg-light"><span>Seharusnya ditagih</span><strong class="ms-auto"><?= rupiah($expected) ?></strong></li>
			</ul>
			<div class="card-body small text-muted">Invoice dianggap cocok jika total tagihan untuk PO ini selisih maksimal <?= $tolerance ?>% dari nilai barang yang diterima. Invoice yang tidak cocok tetap bisa dicatat, tapi perlu alasan saat disetujui.</div>
		</div>
	</div>
</div>
