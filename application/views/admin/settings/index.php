<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?= form_open('admin/settings') ?>
<div class="card" style="max-width: 760px">
	<div class="card-body">
		<?php foreach ($settings as $s): $key = $s['key']; ?>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-6 col-form-label" for="s_<?= e($key) ?>">
					<?= e($s['description']) ?>
					<code class="d-block small text-muted"><?= e($key) ?></code>
				</label>
				<div class="col-sm-6">
					<?php if (in_array($key, $boolean, TRUE)): ?>
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" role="switch" id="s_<?= e($key) ?>" name="settings[<?= e($key) ?>]" value="1" <?= $s['value'] === '1' ? 'checked' : '' ?>>
						</div>
					<?php else: ?>
						<input class="form-control" id="s_<?= e($key) ?>" name="settings[<?= e($key) ?>]" value="<?= e($s['value']) ?>">
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="card-footer bg-transparent">
		<button class="btn btn-primary" type="submit">Simpan Pengaturan</button>
	</div>
</div>
<?= form_close() ?>
