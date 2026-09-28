<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_edit = (bool) $m;
?>
<?= form_open_multipart($is_edit ? 'menu/items/edit/' . $m['id'] : 'menu/items/create') ?>
<div class="row g-3">
	<div class="col-lg-7">
		<div class="card h-100">
			<div class="card-header">Data menu</div>
			<div class="card-body row g-3">
				<div class="col-md-8">
					<label class="form-label" for="name">Nama menu</label>
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
						<?php foreach ($categories as $id => $path): ?><option value="<?= $id ?>" <?= (int) $input['category_id'] === (int) $id ? 'selected' : '' ?>><?= e($path) ?></option><?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-6">
					<label class="form-label" for="status">Status</label>
					<select class="form-select" id="status" name="status">
						<?php foreach (Menu_model::$statuses as $k => $v): ?><option value="<?= $k ?>" <?= $input['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
					</select>
				</div>
				<div class="col-12">
					<label class="form-label" for="description">Deskripsi <span class="text-muted small">(tampil di menu online)</span></label>
					<textarea class="form-control" id="description" name="description" rows="2" maxlength="500"><?= e($input['description']) ?></textarea>
				</div>
				<div class="col-6 col-md-3">
					<label class="form-label" for="prep_minutes">Waktu masak</label>
					<div class="input-group"><input class="form-control" type="number" min="0" max="600" id="prep_minutes" name="prep_minutes" value="<?= e($input['prep_minutes']) ?>"><span class="input-group-text">mnt</span></div>
				</div>
				<div class="col-6 col-md-3">
					<label class="form-label" for="spicy_level">Level pedas</label>
					<select class="form-select" id="spicy_level" name="spicy_level">
						<option value="">—</option>
						<?php for ($i = 0; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= $input['spicy_level'] !== '' && $input['spicy_level'] !== NULL && (int) $input['spicy_level'] === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?>
					</select>
				</div>
				<div class="col-6 col-md-3">
					<label class="form-label" for="sort_order">Urutan</label>
					<input class="form-control" type="number" id="sort_order" name="sort_order" value="<?= (int) $input['sort_order'] ?>">
				</div>
				<div class="col-12">
					<label class="form-label" for="allergens">Info alergen</label>
					<input class="form-control" id="allergens" name="allergens" value="<?= e($input['allergens']) ?>" maxlength="255" placeholder="mis. kacang, susu, gluten">
				</div>
				<div class="col-12">
					<label class="form-label" for="image">Foto</label>
					<div class="d-flex gap-2 align-items-start">
						<?php if ( ! empty($input['image_path'])): ?><img src="<?= base_url($input['image_path']) ?>" alt="" class="rounded border" style="width:64px;height:64px;object-fit:cover"><?php endif; ?>
						<div class="flex-fill">
							<input class="form-control" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
							<?php if ( ! empty($input['image_path'])): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image"><label class="form-check-label" for="remove_image">Hapus foto</label></div><?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-lg-5">
		<div class="card h-100">
			<div class="card-header">Varian</div>
			<div class="card-body">
				<p class="small text-muted">Menu tanpa pilihan cukup satu varian "Reguler". Contoh varian: Panas / Es, Porsi Kecil / Besar. Harga, resep, dan barcode diatur per varian.</p>
				<div id="variant-rows">
					<?php foreach (array_merge($variants, array(array('id' => 0, 'name' => '', 'barcode' => '', 'is_active' => 1, 'price' => ''))) as $k => $v): ?>
						<div class="border rounded p-2 mb-2 variant-row">
							<input type="hidden" name="variants[<?= $k ?>][id]" value="<?= (int) $v['id'] ?>">
							<div class="row g-2">
								<div class="col-7"><input class="form-control form-control-sm" name="variants[<?= $k ?>][name]" value="<?= e($v['name']) ?>" placeholder="Nama varian" maxlength="100"></div>
								<div class="col-5">
									<?php if ($v['id']): ?>
										<span class="form-control form-control-sm bg-light text-muted" title="Ubah lewat tombol Harga di halaman menu">harga: menu</span>
									<?php else: ?>
										<input class="form-control form-control-sm" type="number" min="0" step="any" name="variants[<?= $k ?>][price]" value="<?= e($v['price']) ?>" placeholder="Harga" <?= $can_price ? '' : 'disabled' ?>>
									<?php endif; ?>
								</div>
								<div class="col-7"><input class="form-control form-control-sm" name="variants[<?= $k ?>][barcode]" value="<?= e($v['barcode']) ?>" placeholder="Barcode (kosong = otomatis)" maxlength="40"></div>
								<div class="col-5 d-flex align-items-center"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="variants[<?= $k ?>][is_active]" value="1" id="va<?= $k ?>" <?= $v['is_active'] ? 'checked' : '' ?>><label class="form-check-label small" for="va<?= $k ?>">aktif</label></div></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="btn btn-link btn-sm p-0" id="add-variant">+ tambah varian</button>
				<?php if ($is_edit): ?><p class="small text-muted mt-2 mb-0">Mengosongkan nama varian lama akan menghapusnya (atau menonaktifkan jika sudah pernah terjual).</p><?php endif; ?>
			</div>
		</div>
	</div>
</div>
<div class="mt-3 d-flex gap-2">
	<button class="btn btn-primary" type="submit">Simpan</button>
	<a class="btn btn-outline-secondary" href="<?= site_url($is_edit ? 'menu/items/show/' . $m['id'] : 'menu/items') ?>">Batal</a>
</div>
<?= form_close() ?>
