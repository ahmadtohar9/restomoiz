<?php defined('BASEPATH') OR exit('No direct script access allowed');
$status = array('draft' => array('Draft', 'warning'), 'posted' => array('Diposting', 'success'), 'cancelled' => array('Dibatalkan', 'secondary'));
?>
<?php if (can('inventory.adjust')): ?>
<?= form_open('inventory/opname/create', array('class' => 'card mb-3')) ?>
	<div class="card-header">Mulai stock opname baru</div>
	<div class="card-body row g-2 align-items-end">
		<div class="col-6 col-md-2">
			<label class="form-label small" for="opname_date">Tanggal</label>
			<input class="form-control form-control-sm" type="date" id="opname_date" name="opname_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
		</div>
		<div class="col-6 col-md-3">
			<label class="form-label small" for="category_id">Cakupan</label>
			<select class="form-select form-select-sm" id="category_id" name="category_id">
				<option value="">Semua bahan aktif</option>
				<?php foreach ($categories as $id => $path): ?><option value="<?= $id ?>"><?= e($path) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-12 col-md-5">
			<label class="form-label small" for="notes">Catatan</label>
			<input class="form-control form-control-sm" id="notes" name="notes" maxlength="255" placeholder="mis. Opname akhir bulan September">
		</div>
		<div class="col-12 col-md-2">
			<button class="btn btn-sm btn-primary w-100"><i class="bi bi-clipboard-plus"></i> Buat lembar hitung</button>
		</div>
	</div>
<?= form_close() ?>
<?php endif; ?>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>No.</th><th>Tanggal</th><th>Cakupan</th><th class="text-center">Dihitung</th><?php if ($can_cost): ?><th class="text-end">Nilai selisih</th><?php endif; ?><th>Status</th><th>Dibuat oleh</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada stock opname.</td></tr><?php endif; ?>
			<?php foreach ($rows as $o): ?>
				<tr>
					<td><a href="<?= site_url('inventory/opname/show/' . $o['id']) ?>"><code><?= e($o['opname_no']) ?></code></a></td>
					<td><?= tgl($o['opname_date'], FALSE) ?></td>
					<td class="small"><?= e($o['category_name'] ?: 'Semua bahan') ?></td>
					<td class="text-center small"><?= (int) $o['counted_count'] ?> / <?= (int) $o['item_count'] ?></td>
					<?php if ($can_cost): ?><td class="text-end <?= $o['variance_value'] < 0 ? 'text-danger' : '' ?>"><?= $o['status'] === 'posted' ? rupiah($o['variance_value']) : '-' ?></td><?php endif; ?>
					<td><span class="badge text-bg-<?= $status[$o['status']][1] ?>"><?= $status[$o['status']][0] ?></span></td>
					<td class="small"><?= e($o['created_by_name']) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
