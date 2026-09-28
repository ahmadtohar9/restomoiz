<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === '' || $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
$now = date('Y-m-d H:i:s');
?>
<p><a href="<?= site_url('menu/items/show/' . $variant['menu_id'] . '#v' . $variant['id']) ?>"><i class="bi bi-arrow-left"></i> Kembali ke menu</a></p>

<div class="row g-3">
	<div class="col-lg-6">
		<?= form_open('menu/items/price/' . $variant['id'], array('class' => 'card')) ?>
			<div class="card-body row g-3">
				<div class="col-12 small text-muted">Harga saat ini: <strong><?= $cur['price'] !== NULL ? rupiah($cur['price']) : '-' ?></strong>
					<?php if (can('menu.view_cogs') && $cur['recipe_lines']): ?> · COGS <?= rupiah($cur['cogs']) ?> · margin <?= $cur['margin_pct'] !== NULL ? number_format($cur['margin_pct'], 1, ',', '.') . '%' : '-' ?><?php endif; ?></div>
				<div class="col-md-6">
					<label class="form-label" for="price">Harga normal</label>
					<div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="number" min="0" step="any" id="price" name="price" value="<?= e($num($input['price'])) ?>" required
						data-cogs="<?= can('menu.view_cogs') ? e($cur['cogs']) : '' ?>"></div>
					<div class="form-text" id="price-margin"></div>
				</div>
				<div class="col-md-6">
					<label class="form-label" for="member_price">Harga member <span class="text-muted small">(opsional)</span></label>
					<div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="number" min="0" step="any" id="member_price" name="member_price" value="<?= e($num($input['member_price'])) ?>"></div>
				</div>
				<div class="col-md-6">
					<label class="form-label" for="bulk_min_qty">Harga grosir mulai qty <span class="text-muted small">(opsional)</span></label>
					<input class="form-control" type="number" min="2" step="1" id="bulk_min_qty" name="bulk_min_qty" value="<?= e($input['bulk_min_qty']) ?>">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="bulk_price">Harga grosir per porsi</label>
					<div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="number" min="0" step="any" id="bulk_price" name="bulk_price" value="<?= e($num($input['bulk_price'])) ?>"></div>
				</div>
				<div class="col-12">
					<label class="form-label">Berlaku</label>
					<div class="d-flex flex-wrap gap-3 align-items-center">
						<div class="form-check"><input class="form-check-input" type="radio" name="when" value="now" id="when_now" <?= $input['when'] !== 'schedule' ? 'checked' : '' ?>><label class="form-check-label" for="when_now">Sekarang</label></div>
						<div class="form-check"><input class="form-check-input" type="radio" name="when" value="schedule" id="when_sched" <?= $input['when'] === 'schedule' ? 'checked' : '' ?>><label class="form-check-label" for="when_sched">Terjadwal mulai</label></div>
						<input class="form-control form-control-sm" style="max-width: 160px" type="date" name="effective_date" value="<?= e($input['effective_date']) ?>" min="<?= date('Y-m-d') ?>">
						<input class="form-control form-control-sm" style="max-width: 110px" type="time" name="effective_time" value="<?= e($input['effective_time']) ?>">
					</div>
				</div>
				<div class="col-12">
					<label class="form-label" for="reason">Alasan perubahan</label>
					<input class="form-control" id="reason" name="reason" value="<?= e($input['reason']) ?>" maxlength="255" required placeholder="mis. harga ayam naik, penyesuaian kompetitor">
				</div>
			</div>
			<div class="card-footer bg-transparent"><button class="btn btn-primary" type="submit">Simpan Harga</button></div>
		<?= form_close() ?>
	</div>
	<div class="col-lg-6">
		<div class="card">
			<div class="card-header">Riwayat & jadwal harga</div>
			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<thead><tr><th>Berlaku</th><th class="text-end">Harga</th><th>Alasan</th><th></th></tr></thead>
					<tbody>
					<?php foreach ($history as $h): $future = $h['effective_from'] > $now; ?>
						<tr class="<?= $future ? 'table-info' : '' ?>">
							<td class="small text-nowrap"><?= tgl($h['effective_from']) ?><?= $future ? ' <span class="badge text-bg-info">terjadwal</span>' : '' ?></td>
							<td class="text-end"><?= rupiah($h['price']) ?>
								<?php if ($h['member_price'] !== NULL): ?><div class="small text-muted">member <?= rupiah($h['member_price']) ?></div><?php endif; ?>
								<?php if ($h['bulk_min_qty']): ?><div class="small text-muted">≥<?= (int) $h['bulk_min_qty'] ?>: <?= rupiah($h['bulk_price']) ?></div><?php endif; ?></td>
							<td class="small"><?= e($h['reason']) ?><div class="text-muted"><?= e($h['created_by_name']) ?></div></td>
							<td class="text-end"><?php if ($future): ?>
								<?= form_open('menu/items/cancel_price/' . $h['id'], array('data-confirm' => 'Batalkan perubahan harga terjadwal ini?')) ?><button class="btn btn-sm btn-outline-danger">Batal</button><?= form_close() ?>
							<?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
