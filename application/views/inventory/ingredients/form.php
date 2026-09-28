<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_edit = (bool) $ing;
$num = function ($v) { return $v === '' || $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'); };
?>
<?= form_open_multipart($is_edit ? 'inventory/ingredients/edit/' . $ing['id'] : 'inventory/ingredients/create') ?>
<datalist id="unit-list"><?php foreach (Ingredient_model::$common_units as $u): ?><option value="<?= e($u) ?>"><?php endforeach; ?></datalist>

<div class="row g-3">
	<div class="col-lg-7">
		<div class="card mb-3">
			<div class="card-header">Data bahan</div>
			<div class="card-body row g-3">
				<div class="col-md-8">
					<label class="form-label" for="name">Nama bahan</label>
					<input class="form-control" id="name" name="name" value="<?= e($input['name']) ?>" required maxlength="150">
				</div>
				<div class="col-md-4">
					<label class="form-label" for="code">Kode</label>
					<input class="form-control text-uppercase" id="code" name="code" value="<?= e($input['code']) ?>" maxlength="30" placeholder="otomatis">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="category_id">Kategori</label>
					<select class="form-select" id="category_id" name="category_id">
						<option value="">— tanpa kategori —</option>
						<?php foreach ($categories as $id => $path): ?>
							<option value="<?= $id ?>" <?= (int) $input['category_id'] === (int) $id ? 'selected' : '' ?>><?= e($path) ?></option>
						<?php endforeach; ?>
					</select>
					<?php if (can('inventory.create')): ?><div class="form-text"><a href="<?= site_url('inventory/categories/create') ?>" target="_blank">+ kategori baru</a></div><?php endif; ?>
				</div>
				<div class="col-md-6">
					<label class="form-label" for="default_supplier_id">Supplier utama</label>
					<select class="form-select" id="default_supplier_id" name="default_supplier_id">
						<option value="">— belum ada —</option>
						<?php foreach ($suppliers as $id => $name): ?>
							<option value="<?= $id ?>" <?= (int) $input['default_supplier_id'] === (int) $id ? 'selected' : '' ?>><?= e($name) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-12">
					<label class="form-label" for="description">Deskripsi</label>
					<input class="form-control" id="description" name="description" value="<?= e($input['description']) ?>" maxlength="255">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="location">Lokasi simpan</label>
					<input class="form-control" id="location" name="location" value="<?= e($input['location']) ?>" maxlength="100" placeholder="mis. Chiller 1, Rak B2">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="status">Status</label>
					<select class="form-select" id="status" name="status">
						<?php foreach (Ingredient_model::$statuses as $k => $v): ?>
							<option value="<?= $k ?>" <?= $input['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-12">
					<label class="form-label" for="notes">Catatan</label>
					<textarea class="form-control" id="notes" name="notes" rows="2"><?= e($input['notes']) ?></textarea>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header">Foto</div>
			<div class="card-body d-flex gap-3 align-items-start">
				<?php if ( ! empty($input['image_path'])): ?>
					<img src="<?= base_url($input['image_path']) ?>" alt="" class="rounded border" style="width:96px;height:96px;object-fit:cover">
				<?php endif; ?>
				<div class="flex-fill">
					<input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp">
					<div class="form-text">JPG, PNG, atau WEBP, maksimal 2 MB.</div>
					<?php if ( ! empty($input['image_path'])): ?>
						<div class="form-check mt-1">
							<input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image">
							<label class="form-check-label" for="remove_image">Hapus foto</label>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<div class="col-lg-5">
		<div class="card mb-3">
			<div class="card-header">Satuan</div>
			<div class="card-body">
				<label class="form-label" for="unit">Satuan standar (untuk stok & harga)</label>
				<input class="form-control mb-1" id="unit" name="unit" list="unit-list" value="<?= e($input['unit']) ?>" required maxlength="20" <?= $has_movements ? 'readonly' : '' ?>>
				<div class="form-text mb-3"><?= $has_movements ? 'Tidak bisa diubah karena sudah ada riwayat stok.' : 'Pilih satuan terkecil yang praktis, mis. kg, liter, pcs.' ?></div>

				<label class="form-label">Satuan alternatif</label>
				<div class="form-text mt-0 mb-2">Contoh: satuan standar <em>kg</em>, alternatif <em>gram</em> = 0.001 atau <em>karung</em> = 25.</div>
				<div id="alt-units">
					<?php foreach (array_merge($units, array(array('', ''))) as $u): ?>
						<div class="input-group input-group-sm mb-2 alt-unit-row">
							<span class="input-group-text">1</span>
							<input class="form-control" name="alt_unit[]" list="unit-list" value="<?= e($u[0]) ?>" placeholder="satuan" maxlength="20">
							<span class="input-group-text">=</span>
							<input class="form-control" type="number" step="any" min="0" name="alt_factor[]" value="<?= e($num($u[1])) ?>" placeholder="faktor">
							<span class="input-group-text unit-std-label"><?= e($input['unit'] ?: 'satuan standar') ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="btn btn-link btn-sm p-0" data-add-row="#alt-units" data-row=".alt-unit-row">+ tambah satuan</button>
			</div>
		</div>

		<div class="card">
			<div class="card-header">Level stok <span class="text-muted small fw-normal">(dalam satuan standar, 0 = tidak dipakai)</span></div>
			<div class="card-body row g-3">
				<?php foreach (array('min_stock' => 'Stok minimum', 'max_stock' => 'Stok maksimum', 'reorder_point' => 'Reorder point', 'reorder_qty' => 'Jumlah reorder') as $k => $label): ?>
					<div class="col-6">
						<label class="form-label" for="<?= $k ?>"><?= $label ?></label>
						<input class="form-control" type="number" step="any" min="0" id="<?= $k ?>" name="<?= $k ?>" value="<?= e($num($input[$k])) ?>">
					</div>
				<?php endforeach; ?>
				<?php if ( ! $is_edit && can('inventory.view_cost')): ?>
					<div class="col-12">
						<label class="form-label" for="current_price">Harga acuan per satuan standar <span class="text-muted small">(opsional)</span></label>
						<div class="input-group">
							<span class="input-group-text">Rp</span>
							<input class="form-control" type="number" step="any" min="0" id="current_price" name="current_price" value="<?= e($num($input['current_price'])) ?>">
						</div>
						<div class="form-text">Otomatis diperbarui dari harga pembelian terakhir.</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<div class="mt-3 d-flex gap-2">
	<button class="btn btn-primary" type="submit">Simpan</button>
	<a class="btn btn-outline-secondary" href="<?= site_url($is_edit ? 'inventory/ingredients/show/' . $ing['id'] : 'inventory/ingredients') ?>">Batal</a>
</div>
<?= form_close() ?>
