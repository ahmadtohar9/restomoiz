<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?= form_open($c ? 'sales/customers/form/' . $c['id'] : 'sales/customers/form') ?>
<div class="card" style="max-width: 620px">
	<div class="card-body row g-3">
		<div class="col-md-7"><label class="form-label" for="name">Nama</label><input class="form-control" id="name" name="name" value="<?= e($input['name']) ?>" required maxlength="100"></div>
		<div class="col-md-5"><label class="form-label" for="phone">No. HP</label><input class="form-control" id="phone" name="phone" value="<?= e($input['phone']) ?>" maxlength="20" inputmode="tel"></div>
		<div class="col-12"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" value="<?= e($input['email']) ?>" maxlength="150"></div>
		<div class="col-12"><label class="form-label" for="address">Alamat (untuk delivery)</label><input class="form-control" id="address" name="address" value="<?= e($input['address']) ?>" maxlength="255"></div>
		<div class="col-12"><label class="form-label" for="notes">Catatan</label><input class="form-control" id="notes" name="notes" value="<?= e($input['notes']) ?>" maxlength="255" placeholder="mis. alergi kacang"></div>
		<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="is_member" name="is_member" value="1" <?= $input['is_member'] ? 'checked' : '' ?>><label class="form-check-label" for="is_member">Member (dapat harga & promo member)</label></div></div>
	</div>
	<div class="card-footer bg-transparent d-flex gap-2"><button class="btn btn-primary">Simpan</button><a class="btn btn-outline-secondary" href="<?= site_url('sales/customers') ?>">Batal</a></div>
</div>
<?= form_close() ?>
