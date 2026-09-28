<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<p><a href="<?= site_url('sales/shifts') ?>"><i class="bi bi-arrow-left"></i> Shift kasir</a></p>
<?= form_open('sales/shifts/templates') ?>
<div class="card" style="max-width: 560px">
	<div class="card-body">
		<p class="small text-muted">Nama shift yang bisa dipilih kasir saat membuka shift. Kosongkan nama untuk menghapus.</p>
		<div id="tpl-rows">
			<?php foreach (array_merge($rows, array(array('name' => '', 'start_time' => '', 'end_time' => ''))) as $r): ?>
				<div class="input-group mb-2 tpl-row">
					<input class="form-control" name="name[]" value="<?= e($r['name']) ?>" placeholder="Nama shift" maxlength="50">
					<input class="form-control" type="time" name="start_time[]" value="<?= e(substr($r['start_time'], 0, 5)) ?>">
					<input class="form-control" type="time" name="end_time[]" value="<?= e(substr($r['end_time'], 0, 5)) ?>">
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="btn btn-link btn-sm p-0" data-add-row="#tpl-rows" data-row=".tpl-row">+ tambah shift</button>
	</div>
	<div class="card-footer bg-transparent"><button class="btn btn-primary">Simpan</button></div>
</div>
<?= form_close() ?>
