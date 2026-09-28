<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === '' || $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
$dtl = function ($v) { return $v ? str_replace(' ', 'T', substr($v, 0, 16)) : ''; };
$days = $input['days_of_week'] ? array_map('intval', explode(',', $input['days_of_week'])) : array();
$pms = $input['payment_methods'] ? explode(',', $input['payment_methods']) : array();
$bundle = $sel['bundle'] ?: array(array('variant_id' => '', 'qty' => 1), array('variant_id' => '', 'qty' => 1));
?>
<?= form_open($promo ? 'menu/promos/edit/' . $promo['id'] : 'menu/promos/create', array('id' => 'promo-form')) ?>
<div class="row g-3">
	<div class="col-lg-7">
		<div class="card mb-3">
			<div class="card-header">Promo</div>
			<div class="card-body row g-3">
				<div class="col-md-8">
					<label class="form-label" for="name">Nama promo</label>
					<input class="form-control" id="name" name="name" value="<?= e($input['name']) ?>" required maxlength="150" placeholder="mis. Happy Hour Minuman">
				</div>
				<div class="col-md-4">
					<label class="form-label" for="promo_code">Kode promo <span class="text-muted small">(opsional)</span></label>
					<input class="form-control text-uppercase" id="promo_code" name="promo_code" value="<?= e($input['promo_code']) ?>" maxlength="30" placeholder="otomatis">
				</div>
				<div class="col-12">
					<label class="form-label" for="description">Keterangan</label>
					<input class="form-control" id="description" name="description" value="<?= e($input['description']) ?>" maxlength="255">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="type">Jenis</label>
					<select class="form-select" id="type" name="type">
						<?php foreach (Promo_engine::$types as $k => $v): ?><option value="<?= $k ?>" <?= $input['type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-6 pt pt-fixed pt-percent">
					<label class="form-label" for="value">Nilai</label>
					<div class="input-group"><span class="input-group-text pt pt-fixed">Rp</span><input class="form-control" type="number" min="0" step="any" id="value" name="value" value="<?= e($num($input['value'])) ?>"><span class="input-group-text pt pt-percent">%</span></div>
				</div>
				<div class="col-6 col-md-3 pt pt-buy_get">
					<label class="form-label" for="buy_qty">Beli</label>
					<input class="form-control" type="number" min="1" step="1" id="buy_qty" name="buy_qty" value="<?= e($input['buy_qty']) ?>">
				</div>
				<div class="col-6 col-md-3 pt pt-buy_get">
					<label class="form-label" for="get_qty">Gratis</label>
					<input class="form-control" type="number" min="1" step="1" id="get_qty" name="get_qty" value="<?= e($input['get_qty']) ?>">
				</div>
				<div class="col-md-6 pt pt-bundle">
					<label class="form-label" for="bundle_price">Harga paket</label>
					<div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="number" min="0" step="any" id="bundle_price" name="bundle_price" value="<?= e($num($input['bundle_price'])) ?>"></div>
				</div>
				<div class="col-12 pt pt-bundle">
					<label class="form-label">Isi paket</label>
					<div id="bundle-rows">
						<?php foreach ($bundle as $k => $b): ?>
							<div class="input-group input-group-sm mb-2 bundle-row">
								<select class="form-select" name="bundle[<?= $k ?>][variant_id]">
									<option value="">— pilih menu —</option>
									<?php foreach ($variants as $id => $label): ?><option value="<?= $id ?>" <?= (int) $b['variant_id'] === (int) $id ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
								</select>
								<span class="input-group-text">×</span>
								<input class="form-control" style="max-width: 80px" type="number" min="1" name="bundle[<?= $k ?>][qty]" value="<?= (int) $b['qty'] ?>">
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="btn btn-link btn-sm p-0" id="add-bundle">+ tambah item</button>
				</div>

				<div class="col-12 pt pt-fixed pt-percent pt-buy_get">
					<label class="form-label">Berlaku untuk</label>
					<div class="d-flex flex-wrap gap-3">
						<?php foreach (array('all' => 'Semua menu', 'category' => 'Kategori tertentu', 'menu' => 'Menu tertentu') as $k => $v): ?>
							<div class="form-check"><input class="form-check-input" type="radio" name="scope" value="<?= $k ?>" id="scope_<?= $k ?>" <?= $input['scope'] === $k ? 'checked' : '' ?>><label class="form-check-label" for="scope_<?= $k ?>"><?= $v ?></label></div>
						<?php endforeach; ?>
					</div>
					<div class="scope scope-category mt-2">
						<select class="form-select" name="categories[]" multiple size="6">
							<?php foreach ($categories as $id => $path): ?><option value="<?= $id ?>" <?= in_array((int) $id, $sel['category'], TRUE) ? 'selected' : '' ?>><?= e($path) ?></option><?php endforeach; ?>
						</select>
						<div class="form-text">Termasuk semua sub-kategorinya. Tahan Ctrl/Cmd untuk memilih lebih dari satu.</div>
					</div>
					<div class="scope scope-menu mt-2">
						<select class="form-select" name="menus[]" multiple size="8">
							<?php foreach ($menus as $id => $name): ?><option value="<?= $id ?>" <?= in_array((int) $id, $sel['menu'], TRUE) ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-lg-5">
		<div class="card mb-3">
			<div class="card-header">Waktu berlaku</div>
			<div class="card-body row g-3">
				<div class="col-6"><label class="form-label small" for="start_at">Mulai</label><input class="form-control form-control-sm" type="datetime-local" id="start_at" name="start_at" value="<?= e($dtl($input['start_at'])) ?>" required></div>
				<div class="col-6"><label class="form-label small" for="end_at">Berakhir <span class="text-muted">(opsional)</span></label><input class="form-control form-control-sm" type="datetime-local" id="end_at" name="end_at" value="<?= e($dtl($input['end_at'])) ?>"></div>
				<div class="col-12">
					<label class="form-label small">Hari <span class="text-muted">(kosong = setiap hari)</span></label>
					<div class="d-flex flex-wrap gap-2">
						<?php foreach (Promo_engine::$days as $d => $label): ?>
							<input type="checkbox" class="btn-check" name="days_of_week[]" value="<?= $d ?>" id="d<?= $d ?>" <?= in_array($d, $days, TRUE) ? 'checked' : '' ?>><label class="btn btn-sm btn-outline-primary" for="d<?= $d ?>"><?= $label ?></label>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="col-6"><label class="form-label small" for="time_start">Jam mulai</label><input class="form-control form-control-sm" type="time" id="time_start" name="time_start" value="<?= e($input['time_start'] ? substr($input['time_start'], 0, 5) : '') ?>"></div>
				<div class="col-6"><label class="form-label small" for="time_end">Jam selesai</label><input class="form-control form-control-sm" type="time" id="time_end" name="time_end" value="<?= e($input['time_end'] ? substr($input['time_end'], 0, 5) : '') ?>"></div>
				<div class="col-12 form-text mt-0">Contoh happy hour 15:00–17:00. Jam selesai lebih kecil dari jam mulai berarti melewati tengah malam.</div>
			</div>
		</div>
		<div class="card">
			<div class="card-header">Syarat & batasan</div>
			<div class="card-body row g-3">
				<div class="col-6"><label class="form-label small" for="min_purchase">Minimal belanja</label><input class="form-control form-control-sm" type="number" min="0" step="any" id="min_purchase" name="min_purchase" value="<?= e($num($input['min_purchase'])) ?>"></div>
				<div class="col-6"><label class="form-label small" for="min_qty">Minimal jumlah item</label><input class="form-control form-control-sm" type="number" min="0" step="1" id="min_qty" name="min_qty" value="<?= (int) $input['min_qty'] ?>"></div>
				<div class="col-12 pt pt-percent pt-fixed pt-buy_get pt-bundle"><label class="form-label small" for="max_discount">Maks. diskon per transaksi</label><input class="form-control form-control-sm" type="number" min="0" step="any" id="max_discount" name="max_discount" value="<?= e($num($input['max_discount'])) ?>" placeholder="tanpa batas"></div>
				<div class="col-12">
					<label class="form-label small">Metode bayar <span class="text-muted">(kosong = semua)</span></label>
					<div class="d-flex flex-wrap gap-3">
						<?php foreach (Promo_engine::$payment_methods as $k => $v): ?>
							<div class="form-check"><input class="form-check-input" type="checkbox" name="payment_methods[]" value="<?= $k ?>" id="pm_<?= $k ?>" <?= in_array($k, $pms, TRUE) ? 'checked' : '' ?>><label class="form-check-label small" for="pm_<?= $k ?>"><?= e($v) ?></label></div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="col-6"><label class="form-label small" for="max_usage_total">Kuota total</label><input class="form-control form-control-sm" type="number" min="0" step="1" id="max_usage_total" name="max_usage_total" value="<?= e($input['max_usage_total']) ?>" placeholder="tanpa batas"></div>
				<div class="col-6"><label class="form-label small" for="max_usage_per_customer">Kuota per pelanggan</label><input class="form-control form-control-sm" type="number" min="0" step="1" id="max_usage_per_customer" name="max_usage_per_customer" value="<?= e($input['max_usage_per_customer']) ?>" placeholder="tanpa batas"></div>
				<div class="col-12">
					<div class="form-check"><input class="form-check-input" type="checkbox" id="member_only" name="member_only" value="1" <?= $input['member_only'] ? 'checked' : '' ?>><label class="form-check-label" for="member_only">Khusus member</label></div>
					<div class="form-check"><input class="form-check-input" type="checkbox" id="stackable" name="stackable" value="1" <?= $input['stackable'] ? 'checked' : '' ?>><label class="form-check-label" for="stackable">Boleh digabung dengan promo lain yang juga bisa digabung</label></div>
					<div class="form-check"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $input['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Aktif</label></div>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="mt-3 d-flex gap-2">
	<button class="btn btn-primary" type="submit">Simpan Promo</button>
	<a class="btn btn-outline-secondary" href="<?= site_url('menu/promos') ?>">Batal</a>
</div>
<?= form_close() ?>
