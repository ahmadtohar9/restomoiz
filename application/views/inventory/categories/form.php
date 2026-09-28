<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?= form_open($cat ? 'inventory/categories/edit/' . $cat['id'] : 'inventory/categories/create') ?>
<div class="card" style="max-width: 640px">
	<div class="card-body">
		<div class="mb-3">
			<label class="form-label" for="name">Nama kategori</label>
			<input class="form-control" id="name" name="name" value="<?= e($input['name']) ?>" required maxlength="100">
		</div>
		<div class="mb-3">
			<label class="form-label" for="parent_id">Induk kategori</label>
			<select class="form-select" id="parent_id" name="parent_id">
				<option value="">— kategori utama —</option>
				<?php foreach ($parents as $id => $path): ?>
					<option value="<?= $id ?>" <?= (int) $input['parent_id'] === (int) $id ? 'selected' : '' ?>><?= e($path) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="mb-3">
			<label class="form-label" for="description">Deskripsi</label>
			<input class="form-control" id="description" name="description" value="<?= e($input['description']) ?>" maxlength="255">
		</div>
		<div class="form-check">
			<input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $input['is_active'] ? 'checked' : '' ?>>
			<label class="form-check-label" for="is_active">Aktif (muncul di pilihan kategori)</label>
		</div>
	</div>
	<div class="card-footer bg-transparent d-flex gap-2">
		<button class="btn btn-primary" type="submit">Simpan</button>
		<a class="btn btn-outline-secondary" href="<?= site_url('inventory/categories') ?>">Batal</a>
	</div>
</div>
<?= form_close() ?>
