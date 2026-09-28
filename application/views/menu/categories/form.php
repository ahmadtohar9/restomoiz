<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?= form_open_multipart($cat ? 'menu/categories/edit/' . $cat['id'] : 'menu/categories/create') ?>
<div class="card" style="max-width: 680px">
	<div class="card-body row g-3">
		<div class="col-md-8">
			<label class="form-label" for="name">Nama kategori</label>
			<input class="form-control" id="name" name="name" value="<?= e($input['name']) ?>" required maxlength="100">
		</div>
		<div class="col-md-4">
			<label class="form-label" for="sort_order">Urutan</label>
			<input class="form-control" type="number" id="sort_order" name="sort_order" value="<?= (int) $input['sort_order'] ?>">
		</div>
		<div class="col-12">
			<label class="form-label" for="parent_id">Induk kategori</label>
			<select class="form-select" id="parent_id" name="parent_id">
				<option value="">— kategori utama —</option>
				<?php foreach ($parents as $id => $path): ?><option value="<?= $id ?>" <?= (int) $input['parent_id'] === (int) $id ? 'selected' : '' ?>><?= e($path) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-12">
			<label class="form-label" for="description">Deskripsi</label>
			<input class="form-control" id="description" name="description" value="<?= e($input['description']) ?>" maxlength="255">
		</div>
		<div class="col-12">
			<label class="form-label" for="image">Gambar kategori <span class="text-muted small">(untuk tampilan menu)</span></label>
			<div class="d-flex gap-2 align-items-center">
				<?php if ( ! empty($input['image_path'])): ?><img src="<?= base_url($input['image_path']) ?>" alt="" class="rounded border" style="width:48px;height:48px;object-fit:cover"><?php endif; ?>
				<input class="form-control" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
			</div>
		</div>
		<div class="col-12">
			<div class="form-check"><input class="form-check-input" type="checkbox" id="is_hidden" name="is_hidden" value="1" <?= $input['is_hidden'] ? 'checked' : '' ?>><label class="form-check-label" for="is_hidden">Kategori internal (tidak tampil di menu online pelanggan)</label></div>
			<div class="form-check"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $input['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Aktif</label></div>
		</div>
	</div>
	<div class="card-footer bg-transparent d-flex gap-2">
		<button class="btn btn-primary" type="submit">Simpan</button>
		<a class="btn btn-outline-secondary" href="<?= site_url('menu/categories') ?>">Batal</a>
	</div>
</div>
<?= form_close() ?>
