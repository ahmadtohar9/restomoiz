<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
?>
<p><a href="<?= site_url('purchase/invoices/show/' . $inv['id']) ?>"><i class="bi bi-arrow-left"></i> Kembali ke invoice <?= e($inv['invoice_number']) ?></a></p>

<div class="row g-3">
	<div class="col-lg-8">
		<?= form_open_multipart('purchase/payments/create/' . $inv['id'], array('class' => 'card', 'id' => 'payment-form')) ?>
			<div class="card-body row g-3">
				<div class="col-md-4">
					<label class="form-label" for="payment_date">Tanggal bayar</label>
					<input class="form-control" type="date" id="payment_date" name="payment_date" value="<?= e($input['payment_date']) ?>" max="<?= date('Y-m-d') ?>" required>
				</div>
				<div class="col-md-4">
					<label class="form-label" for="amount">Jumlah</label>
					<div class="input-group"><span class="input-group-text">Rp</span>
						<input class="form-control" type="number" step="any" min="0" max="<?= e($num($payable)) ?>" id="amount" name="amount" value="<?= e($num($input['amount'])) ?>" required></div>
					<div class="form-text">Maksimal <?= rupiah($payable) ?>. Boleh dibayar sebagian.</div>
				</div>
				<div class="col-md-4">
					<label class="form-label" for="method">Metode</label>
					<select class="form-select" id="method" name="method">
						<?php foreach (Purchase_service::$methods as $k => $v): ?><option value="<?= $k ?>" <?= $input['method'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
					</select>
				</div>

				<div class="col-md-4 pm pm-transfer pm-check"><label class="form-label" for="bank_name">Bank</label><input class="form-control" id="bank_name" name="bank_name" value="<?= e($input['bank_name']) ?>" maxlength="50"></div>
				<div class="col-md-4 pm pm-transfer"><label class="form-label" for="account_no">Rekening tujuan</label><input class="form-control" id="account_no" name="account_no" value="<?= e($input['account_no']) ?>" maxlength="50"></div>
				<div class="col-md-4 pm pm-transfer"><label class="form-label" for="reference_no">No. referensi transfer</label><input class="form-control" id="reference_no" name="reference_no" value="<?= e($input['reference_no']) ?>" maxlength="100"></div>
				<div class="col-md-4 pm pm-credit_card"><label class="form-label" for="card_last4">4 digit terakhir kartu</label><input class="form-control" id="card_last4" name="card_last4" value="<?= e($input['card_last4']) ?>" maxlength="4" pattern="\d{4}" inputmode="numeric" autocomplete="off"><div class="form-text">Nomor kartu lengkap dan CVV tidak disimpan.</div></div>
				<div class="col-md-4 pm pm-credit_card"><label class="form-label" for="auth_code">Kode otorisasi</label><input class="form-control" id="auth_code" name="auth_code" value="<?= e($input['auth_code']) ?>" maxlength="20" autocomplete="off"></div>
				<div class="col-md-4 pm pm-check"><label class="form-label" for="check_no">No. cek / giro</label><input class="form-control" id="check_no" name="check_no" value="<?= e($input['check_no']) ?>" maxlength="50"></div>
				<div class="col-md-4 pm pm-check"><label class="form-label" for="check_date">Tanggal cek</label><input class="form-control" type="date" id="check_date" name="check_date" value="<?= e($input['check_date']) ?>"></div>

				<div class="col-md-6">
					<label class="form-label" for="proof">Bukti pembayaran <span class="text-muted small pm pm-cash">(opsional untuk tunai)</span></label>
					<input class="form-control" type="file" id="proof" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="notes">Catatan</label>
					<input class="form-control" id="notes" name="notes" value="<?= e($input['notes']) ?>" maxlength="255">
				</div>
			</div>
			<div class="card-footer bg-transparent d-flex gap-2">
				<button class="btn btn-primary" type="submit">Simpan (menunggu verifikasi)</button>
				<a class="btn btn-outline-secondary" href="<?= site_url('purchase/invoices/show/' . $inv['id']) ?>">Batal</a>
			</div>
		<?= form_close() ?>
	</div>
	<div class="col-lg-4">
		<div class="card">
			<ul class="list-group list-group-flush small">
				<li class="list-group-item d-flex"><span>Supplier</span><span class="ms-auto"><?= e($inv['supplier_name']) ?></span></li>
				<li class="list-group-item d-flex"><span>Invoice</span><span class="ms-auto"><?= e($inv['invoice_number']) ?></span></li>
				<li class="list-group-item d-flex"><span>Jatuh tempo</span><span class="ms-auto"><?= tgl($inv['due_date'], FALSE) ?></span></li>
				<li class="list-group-item d-flex"><span>Nilai invoice</span><span class="ms-auto"><?= rupiah($inv['amount']) ?></span></li>
				<li class="list-group-item d-flex"><span>Sudah dibayar</span><span class="ms-auto"><?= rupiah($inv['paid_amount']) ?></span></li>
				<li class="list-group-item d-flex bg-light"><strong>Bisa diajukan</strong><strong class="ms-auto"><?= rupiah($payable) ?></strong></li>
			</ul>
		</div>
	</div>
</div>
