<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<p class="text-muted small">Centang untuk memberi permission ke role. Kolom Admin selalu memiliki semua permission. Perubahan tercatat di audit log.</p>

<?= form_open('admin/roles/matrix') ?>
<div class="card">
	<div class="table-responsive matrix-wrap">
		<table class="table table-sm table-bordered align-middle mb-0 matrix">
			<thead>
				<tr>
					<th class="matrix-perm">Permission</th>
					<?php foreach ($roles as $r): ?>
						<th class="text-center matrix-role"><?= e($r['name']) ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($groups as $g): ?>
				<tr class="table-light"><th colspan="<?= count($roles) + 1 ?>"><?= e($g['label']) ?></th></tr>
				<?php foreach ($g['permissions'] as $p): ?>
					<tr>
						<td class="matrix-perm">
							<code><?= e($p['name']) ?></code>
							<div class="small text-muted"><?= e($p['description']) ?></div>
						</td>
						<?php foreach ($roles as $r): ?>
							<td class="text-center">
								<?php if ($r['is_super']): ?>
									<i class="bi bi-check-lg text-success" title="Admin: semua akses"></i>
								<?php else: ?>
									<input class="form-check-input" type="checkbox" name="perm[<?= $r['id'] ?>][]" value="<?= $p['id'] ?>"
										aria-label="<?= e($r['name'] . ' - ' . $p['name']) ?>"
										<?= isset($matrix[$r['id']][$p['id']]) ? 'checked' : '' ?>>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<div class="mt-3 matrix-actions">
	<button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan Matrix</button>
</div>
<?= form_close() ?>
