<?php defined('BASEPATH') OR exit('No direct script access allowed');
$posted = $opname['status'] === 'posted';
$status = array('draft' => array('Draft', 'warning'), 'posted' => array('Diposting', 'success'), 'cancelled' => array('Dibatalkan', 'secondary'));
$num = function ($v) { return $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.'); };
$total_value = 0;
foreach ($items as $it) { $total_value += (float) $it['variance_value']; }
?>
<p><a href="<?= site_url('inventory/opname') ?>"><i class="bi bi-arrow-left"></i> Daftar stock opname</a></p>

<div class="card mb-3">
	<div class="card-body d-flex flex-wrap gap-4 align-items-center">
		<div><div class="stat-label">No.</div><code class="fs-6"><?= e($opname['opname_no']) ?></code></div>
		<div><div class="stat-label">Tanggal</div><?= tgl($opname['opname_date'], FALSE) ?></div>
		<div><div class="stat-label">Cakupan</div><?= e($opname['category_name'] ?: 'Semua bahan aktif') ?></div>
		<div><div class="stat-label">Status</div><span class="badge text-bg-<?= $status[$opname['status']][1] ?>"><?= $status[$opname['status']][0] ?></span></div>
		<?php if ($posted): ?>
			<div><div class="stat-label">Diposting</div><?= e($opname['posted_by_name']) ?>, <?= tgl($opname['posted_at']) ?></div>
			<?php if ($can_cost): ?><div><div class="stat-label">Nilai selisih</div><strong class="<?= $total_value < 0 ? 'text-danger' : 'text-success' ?>"><?= rupiah($total_value) ?></strong></div><?php endif; ?>
		<?php endif; ?>
		<?php if ($opname['notes']): ?><div class="w-100 small text-muted"><?= e($opname['notes']) ?></div><?php endif; ?>
	</div>
</div>

<?php if ($can_edit): ?>
	<div class="alert alert-info small">
		Isi jumlah fisik hasil hitung (satuan standar). Kolom yang dikosongkan tidak akan mengubah stok.
		Klik <strong>Simpan draft</strong> untuk menyimpan sementara, lalu <strong>Posting</strong> untuk membukukan selisih.
		Stok sistem dibandingkan pada saat posting.
	</div>
<?php endif; ?>

<?= $can_edit ? form_open('inventory/opname/show/' . $opname['id']) : '' ?>
<div class="card">
	<div class="card-header d-flex align-items-center">
		<span><?= count($items) ?> bahan</span>
		<input type="search" class="form-control form-control-sm ms-auto table-filter" data-target="#opname-table" placeholder="Cari bahan..." style="max-width: 240px">
	</div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0" id="opname-table">
			<thead>
				<tr><th>Bahan</th><th>Lokasi</th>
					<th class="text-end"><?= $posted ? 'Stok sistem' : 'Stok sistem saat ini' ?></th>
					<th style="width: 140px">Hitung fisik</th>
					<th class="text-end">Selisih</th>
					<?php if ($posted && $can_cost): ?><th class="text-end">Nilai selisih</th><?php endif; ?>
					<th>Catatan</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($items as $it):
				$system = $posted ? (float) $it['system_qty'] : (float) $it['qty_on_hand'];
				$var = $posted ? $it['variance'] : ($it['counted_qty'] !== NULL ? $it['counted_qty'] - $system : NULL);
			?>
				<tr>
					<td><?= e($it['name']) ?> <span class="small text-muted"><?= e($it['code']) ?></span></td>
					<td class="small text-muted"><?= e($it['location'] ?: '-') ?></td>
					<td class="text-end text-nowrap"><?= qty($system) ?> <span class="small text-muted"><?= e($it['unit']) ?></span></td>
					<td>
						<?php if ($can_edit): ?>
							<input class="form-control form-control-sm opname-count" type="number" step="any" min="0" name="count[<?= $it['id'] ?>]" value="<?= e($num($it['counted_qty'])) ?>" data-system="<?= e($system) ?>">
						<?php else: ?>
							<?= $it['counted_qty'] !== NULL ? qty($it['counted_qty']) : '<span class="text-muted small">tidak dihitung</span>' ?>
						<?php endif; ?>
					</td>
					<td class="text-end opname-variance <?= $var !== NULL && $var < 0 ? 'text-danger' : ($var > 0 ? 'text-success' : '') ?>"><?= $var === NULL ? '' : ($var > 0 ? '+' : '') . qty($var) ?></td>
					<?php if ($posted && $can_cost): ?><td class="text-end small"><?= $it['variance_value'] !== NULL && (float) $it['variance_value'] != 0 ? rupiah($it['variance_value']) : '' ?></td><?php endif; ?>
					<td>
						<?php if ($can_edit): ?>
							<input class="form-control form-control-sm" name="item_notes[<?= $it['id'] ?>]" value="<?= e($it['notes']) ?>" maxlength="255">
						<?php else: ?>
							<span class="small"><?= e($it['notes']) ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($can_edit): ?>
	<div class="mt-3 d-flex flex-wrap gap-2 matrix-actions">
		<button class="btn btn-outline-primary" type="submit" name="action" value="save"><i class="bi bi-save"></i> Simpan draft</button>
		<button class="btn btn-success" type="submit" name="action" value="post" data-confirm-click="Posting opname? Selisih akan dibukukan ke stok dan tidak bisa dibatalkan."><i class="bi bi-check2-circle"></i> Posting</button>
	</div>
	<?= form_close() ?>
	<?= form_open('inventory/opname/cancel/' . $opname['id'], array('class' => 'mt-2', 'data-confirm' => 'Batalkan lembar opname ini?')) ?>
		<button class="btn btn-link btn-sm text-danger p-0">Batalkan opname ini</button>
	<?= form_close() ?>
<?php endif; ?>
