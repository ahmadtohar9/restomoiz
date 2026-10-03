<?php defined('BASEPATH') OR exit('No direct script access allowed');
$can_pos = can_any(array('sales.process', 'sales.order'));
$areas = array();
foreach ($tables as $t) { $areas[$t['area'] ?: 'Umum'][] = $t; }
$edit = can('sales.edit_order');
$busy_n = count(array_filter($tables, function ($t) { return (bool) $t['order_id']; }));
$active_n = count(array_filter($tables, function ($t) { return (bool) $t['is_active']; }));
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
	<div class="d-flex flex-wrap gap-2 small">
		<span class="topbar-chip tw-inline-flex"><span class="tw-h-2 tw-w-2 tw-rounded-full tw-bg-emerald-500"></span> <?= $active_n - $busy_n ?> kosong</span>
		<span class="topbar-chip tw-inline-flex"><span class="tw-h-2 tw-w-2 tw-rounded-full tw-bg-amber-500"></span> <?= $busy_n ?> terisi</span>
		<span class="topbar-chip tw-inline-flex"><i class="bi bi-grid-3x3-gap"></i> <?= count($tables) ?> meja</span>
	</div>
	<?php if ($edit): ?>
		<button type="button" class="btn btn-primary ms-auto" data-modal-template="#tpl-table" data-modal-title="Tambah meja"><i class="bi bi-plus-lg"></i> Tambah meja</button>
	<?php endif; ?>
</div>

<?php if (empty($tables)): ?>
	<div class="card"><div class="card-body text-center py-5">
		<div class="stat-icon tw-mx-auto tw-mb-3 tw-h-14 tw-w-14 tw-rounded-2xl tw-bg-slate-100 tw-text-2xl tw-text-slate-500"><i class="bi bi-grid-3x3-gap"></i></div>
		<div class="fw-semibold">Belum ada meja</div>
		<div class="small text-muted"><?= $edit ? 'Klik "Tambah meja" untuk mulai.' : 'Minta manajer menambahkan meja.' ?></div>
	</div></div>
<?php endif; ?>

<?php foreach ($areas as $area => $list): ?>
	<h2 class="tw-mb-3 tw-mt-5 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[.12em] tw-text-slate-500 first:tw-mt-0"><?= e($area) ?> <span class="fw-normal">· <?= count($list) ?> meja</span></h2>
	<div class="row g-3 mb-2">
		<?php foreach ($list as $t): $busy = (bool) $t['order_id']; $state = ! $t['is_active'] ? 'off' : ($busy ? 'busy' : 'free'); ?>
			<div class="col-6 col-md-4 col-lg-3 col-xxl-2">
				<div class="card table-card is-<?= $state ?> h-100">
					<div class="card-body">
						<div class="d-flex align-items-start">
							<div>
								<div class="table-no"><?= e($t['name']) ?></div>
								<div class="small text-muted"><i class="bi bi-people"></i> <?= (int) $t['capacity'] ?> kursi</div>
							</div>
							<span class="table-state ms-auto"><?= $state === 'busy' ? 'Terisi' : ($state === 'free' ? 'Kosong' : 'Nonaktif') ?></span>
						</div>
						<?php if ($busy): ?>
							<div class="tw-mt-3 tw-rounded-lg tw-bg-amber-50 tw-p-2 small">
								<a class="fw-semibold" href="<?= site_url($can_pos ? 'pos?order=' . $t['order_id'] : 'sales/orders/show/' . $t['order_id']) ?>"><?= e($t['order_number']) ?></a>
								<div class="text-muted"><?= rupiah($t['subtotal']) ?> · <?= (int) floor((time() - strtotime($t['order_since'])) / 60) ?> mnt</div>
								<?php if ($t['ready_items']): ?><span class="badge text-bg-info mt-1"><?= (int) $t['ready_items'] ?> siap antar</span><?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
					<?php if ($edit): ?>
						<div class="card-footer d-flex gap-1">
							<?php if ($busy && $can_pos): ?><a class="btn btn-sm btn-light flex-fill" href="<?= site_url('pos?order=' . $t['order_id']) ?>"><i class="bi bi-cash-coin"></i> Buka</a><?php endif; ?>
							<button type="button" class="btn btn-sm btn-light flex-fill" data-modal-template="#tpl-table" data-modal-title="Ubah meja <?= e($t['name']) ?>"
								<?= $busy ? '' : 'data-delete-url="' . site_url('sales/tables/delete/' . $t['id']) . '"' ?>
								data-fill="<?= e(json_encode(array('id' => (int) $t['id'], 'name' => $t['name'], 'area' => (string) $t['area'], 'capacity' => (int) $t['capacity'], 'sort_order' => (int) $t['sort_order'], 'is_active' => (int) $t['is_active']))) ?>"><i class="bi bi-pencil"></i> Ubah</button>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endforeach; ?>

<?php if ($edit): ?>
<template id="tpl-table" data-icon="grid-3x3-gap">
	<?= form_open('sales/tables/save') ?>
		<input type="hidden" name="id" value="0">
		<div class="row g-3">
			<div class="col-sm-6"><label class="form-label" for="t-name">Nama / nomor meja</label><input class="form-control" id="t-name" name="name" maxlength="30" required placeholder="mis. 5 atau VIP-1"></div>
			<div class="col-sm-6"><label class="form-label" for="t-area">Area</label><input class="form-control" id="t-area" name="area" maxlength="50" placeholder="mis. Indoor, Teras" list="t-areas">
				<datalist id="t-areas"><?php foreach (array_keys($areas) as $a): if ($a === 'Umum') continue; ?><option value="<?= e($a) ?>"><?php endforeach; ?></datalist></div>
			<div class="col-6"><label class="form-label" for="t-cap">Jumlah kursi</label><input class="form-control" type="number" id="t-cap" name="capacity" value="4" min="1" max="99"></div>
			<div class="col-6"><label class="form-label" for="t-sort">Urutan tampil</label><input class="form-control" type="number" id="t-sort" name="sort_order" value="0"></div>
			<div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="t-active" name="is_active" value="1" checked><label class="form-check-label" for="t-active">Aktif (muncul di POS)</label></div></div>
		</div>
		<div class="ui-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Simpan</button></div>
	<?= form_close() ?>
	<?= form_open('', array('data-delete-form' => '1', 'data-confirm' => 'Hapus meja ini? Meja yang punya riwayat pesanan akan dinonaktifkan.')) ?>
		<button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Hapus</button>
	<?= form_close() ?>
</template>
<?php endif; ?>
