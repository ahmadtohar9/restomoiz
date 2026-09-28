<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
?>
<?= form_open($s ? 'inventory/suppliers/edit/' . $s['id'] : 'inventory/suppliers/create') ?>
<div class="row g-3">
	<div class="col-lg-7">
		<div class="card h-100">
			<div class="card-header">Identitas & kontak</div>
			<div class="card-body row g-3">
				<div class="col-12">
					<label class="form-label" for="name">Nama supplier</label>
					<input class="form-control" id="name" name="name" value="<?= e($input['name']) ?>" required maxlength="150">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="contact_person">Contact person</label>
					<input class="form-control" id="contact_person" name="contact_person" value="<?= e($input['contact_person']) ?>" maxlength="100">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="phone">Telepon / WA</label>
					<input class="form-control" id="phone" name="phone" value="<?= e($input['phone']) ?>" maxlength="30">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="email">Email</label>
					<input class="form-control" type="email" id="email" name="email" value="<?= e($input['email']) ?>" maxlength="150">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="city">Kota</label>
					<input class="form-control" id="city" name="city" value="<?= e($input['city']) ?>" maxlength="100">
				</div>
				<div class="col-12">
					<label class="form-label" for="address">Alamat</label>
					<input class="form-control" id="address" name="address" value="<?= e($input['address']) ?>" maxlength="255">
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-5">
		<div class="card h-100">
			<div class="card-header">Ketentuan</div>
			<div class="card-body row g-3">
				<div class="col-12">
					<label class="form-label" for="payment_terms">Termin pembayaran</label>
					<select class="form-select" id="payment_terms" name="payment_terms">
						<?php foreach ($terms as $k => $v): ?>
							<option value="<?= $k ?>" <?= $input['payment_terms'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-6">
					<label class="form-label" for="min_order_amount">Minimum order</label>
					<div class="input-group"><span class="input-group-text">Rp</span>
						<input class="form-control" type="number" min="0" step="any" id="min_order_amount" name="min_order_amount" value="<?= e($num($input['min_order_amount'])) ?>"></div>
				</div>
				<div class="col-6">
					<label class="form-label" for="lead_time_days">Lead time</label>
					<div class="input-group"><input class="form-control" type="number" min="0" max="365" step="1" id="lead_time_days" name="lead_time_days" value="<?= (int) $input['lead_time_days'] ?>"><span class="input-group-text">hari</span></div>
				</div>
				<div class="col-12">
					<label class="form-label" for="quality_score">Skor kualitas</label>
					<select class="form-select" id="quality_score" name="quality_score">
						<option value="">— belum dinilai —</option>
						<?php for ($i = 5; $i >= 1; $i--): ?>
							<option value="<?= $i ?>" <?= (int) $input['quality_score'] === $i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?> (<?= $i ?>)</option>
						<?php endfor; ?>
					</select>
					<div class="form-text">Persentase ketepatan kirim dihitung otomatis dari PO (fase Pembelian).</div>
				</div>
				<div class="col-12">
					<label class="form-label" for="notes">Catatan</label>
					<textarea class="form-control" id="notes" name="notes" rows="2"><?= e($input['notes']) ?></textarea>
				</div>
				<div class="col-12 form-check ms-2">
					<input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $input['is_active'] ? 'checked' : '' ?>>
					<label class="form-check-label" for="is_active">Supplier aktif</label>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="mt-3 d-flex gap-2">
	<button class="btn btn-primary" type="submit">Simpan</button>
	<a class="btn btn-outline-secondary" href="<?= site_url($s ? 'inventory/suppliers/show/' . $s['id'] : 'inventory/suppliers') ?>">Batal</a>
</div>
<?= form_close() ?>
