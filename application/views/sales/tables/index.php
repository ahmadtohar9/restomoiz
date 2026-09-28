<?php defined('BASEPATH') OR exit('No direct script access allowed');
$areas = array();
foreach ($tables as $t) { $areas[$t['area'] ?: 'Umum'][] = $t; }
$edit = can('sales.edit_order');
?>
<?php if (empty($tables)): ?>
	<div class="alert alert-info">Belum ada meja. <?= $edit ? 'Tambahkan di bawah.' : 'Minta manajer menambahkan meja.' ?></div>
<?php endif; ?>

<?php foreach ($areas as $area => $list): ?>
	<h2 class="h6 text-muted mt-3"><?= e($area) ?></h2>
	<div class="row g-2 mb-2">
		<?php foreach ($list as $t): $busy = (bool) $t['order_id']; ?>
			<div class="col-6 col-md-3 col-xl-2">
				<div class="card h-100 <?= ! $t['is_active'] ? 'opacity-50' : ($busy ? 'border-warning' : 'border-success') ?>">
					<div class="card-body p-2 text-center">
						<div class="fs-4 fw-bold"><?= e($t['name']) ?></div>
						<div class="small text-muted"><i class="bi bi-people"></i> <?= (int) $t['capacity'] ?></div>
						<?php if ($busy): ?>
							<div class="small mt-1"><a href="<?= site_url('pos?order=' . $t['order_id']) ?>"><?= e($t['order_number']) ?></a></div>
							<div class="small"><?= rupiah($t['subtotal']) ?> · <?= (int) floor((time() - strtotime($t['order_since'])) / 60) ?> mnt</div>
							<?php if ($t['ready_items']): ?><span class="badge text-bg-info"><?= (int) $t['ready_items'] ?> siap antar</span><?php endif; ?>
						<?php elseif ($t['is_active']): ?>
							<span class="badge text-bg-success mt-1">kosong</span>
						<?php else: ?>
							<span class="badge text-bg-secondary mt-1">nonaktif</span>
						<?php endif; ?>
						<?php if ($edit): ?>
							<div class="mt-1"><a href="#" class="small" data-bs-toggle="collapse" data-bs-target="#edit-<?= $t['id'] ?>">ubah</a></div>
							<div class="collapse text-start mt-2" id="edit-<?= $t['id'] ?>">
								<?= form_open('sales/tables/save') ?>
									<input type="hidden" name="id" value="<?= $t['id'] ?>">
									<input class="form-control form-control-sm mb-1" name="name" value="<?= e($t['name']) ?>" maxlength="30" required>
									<input class="form-control form-control-sm mb-1" name="area" value="<?= e($t['area']) ?>" placeholder="Area">
									<div class="d-flex gap-1 mb-1"><input class="form-control form-control-sm" type="number" name="capacity" value="<?= (int) $t['capacity'] ?>" min="1" max="99" title="Kapasitas"><input class="form-control form-control-sm" type="number" name="sort_order" value="<?= (int) $t['sort_order'] ?>" title="Urutan"></div>
									<div class="form-check small"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ta<?= $t['id'] ?>" <?= $t['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="ta<?= $t['id'] ?>">aktif</label></div>
									<button class="btn btn-sm btn-primary w-100">Simpan</button>
								<?= form_close() ?>
								<?php if ( ! $busy): ?>
									<?= form_open('sales/tables/delete/' . $t['id'], array('class' => 'mt-1', 'data-confirm' => 'Hapus meja ' . $t['name'] . '?')) ?><button class="btn btn-sm btn-link text-danger p-0">hapus</button><?= form_close() ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endforeach; ?>

<?php if ($edit): ?>
<div class="card mt-4" style="max-width: 640px">
	<div class="card-header">Tambah meja</div>
	<div class="card-body">
		<?= form_open('sales/tables/save', array('class' => 'row g-2 align-items-end')) ?>
			<div class="col-6 col-md-3"><label class="form-label small" for="t-name">Nama / no.</label><input class="form-control form-control-sm" id="t-name" name="name" maxlength="30" required placeholder="mis. 5"></div>
			<div class="col-6 col-md-3"><label class="form-label small" for="t-area">Area</label><input class="form-control form-control-sm" id="t-area" name="area" maxlength="50" placeholder="Indoor"></div>
			<div class="col-4 col-md-2"><label class="form-label small" for="t-cap">Kursi</label><input class="form-control form-control-sm" type="number" id="t-cap" name="capacity" value="4" min="1" max="99"></div>
			<div class="col-4 col-md-2"><label class="form-label small" for="t-sort">Urutan</label><input class="form-control form-control-sm" type="number" id="t-sort" name="sort_order" value="0"></div>
			<input type="hidden" name="is_active" value="1">
			<div class="col-4 col-md-2"><button class="btn btn-sm btn-primary w-100">Tambah</button></div>
		<?= form_close() ?>
	</div>
</div>
<?php endif; ?>
