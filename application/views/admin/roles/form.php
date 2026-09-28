<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_edit = (bool) $role;
$is_super = $is_edit && $role['is_super'];
?>
<?= form_open($is_edit ? 'admin/roles/edit/' . $role['id'] : 'admin/roles/create') ?>
<div class="card mb-3">
	<div class="card-body row g-3">
		<div class="col-md-5">
			<label class="form-label" for="name">Nama role</label>
			<input class="form-control" id="name" name="name" value="<?= e($input['name']) ?>" required maxlength="100">
		</div>
		<div class="col-md-7">
			<label class="form-label" for="description">Deskripsi</label>
			<input class="form-control" id="description" name="description" value="<?= e($input['description']) ?>" maxlength="255">
		</div>
	</div>
</div>

<?php if ($is_super): ?>
	<div class="alert alert-warning"><i class="bi bi-stars"></i> Role ini otomatis memiliki <strong>semua permission</strong>, termasuk permission modul yang ditambahkan nanti.</div>
<?php else: ?>
	<div class="d-flex align-items-center mb-2">
		<h2 class="h6 mb-0">Permission</h2>
		<div class="ms-auto small">
			<a href="#" data-check-all=".perm-box">Pilih semua</a> · <a href="#" data-uncheck-all=".perm-box">Kosongkan</a>
		</div>
	</div>
	<div class="row g-3">
		<?php foreach ($groups as $key => $g): ?>
			<div class="col-md-6 col-xl-4">
				<div class="card h-100">
					<div class="card-header d-flex">
						<?= e($g['label']) ?>
						<a class="ms-auto small" href="#" data-toggle-group="<?= e($key) ?>">semua</a>
					</div>
					<div class="card-body">
						<?php foreach ($g['permissions'] as $p): ?>
							<div class="form-check">
								<input class="form-check-input perm-box" type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" id="p<?= $p['id'] ?>" data-group="<?= e($key) ?>"
									<?= in_array((int) $p['id'], $input['permissions'], TRUE) ? 'checked' : '' ?>>
								<label class="form-check-label" for="p<?= $p['id'] ?>"><?= e($p['description']) ?>
									<code class="small text-muted d-block"><?= e($p['name']) ?></code></label>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<div class="mt-3 d-flex gap-2">
	<button class="btn btn-primary" type="submit">Simpan</button>
	<a class="btn btn-outline-secondary" href="<?= site_url('admin/roles') ?>">Batal</a>
</div>
<?= form_close() ?>
