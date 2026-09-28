<?php defined('BASEPATH') OR exit('No direct script access allowed');
$qs = function (array $extra = array()) use ($filters) {
	return http_build_query(array_filter(array_merge($filters, $extra), 'strlen'));
};
$type_badge = array('IN' => 'success', 'OUT' => 'danger', 'ADJ' => 'warning');
?>
<form class="card mb-3" method="get" action="<?= site_url('inventory/stock/movements') ?>">
	<div class="card-body row g-2 align-items-end">
		<div class="col-6 col-md-2">
			<label class="form-label small" for="from">Dari</label>
			<input class="form-control form-control-sm" type="date" id="from" name="from" value="<?= e($filters['from']) ?>">
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="to">Sampai</label>
			<input class="form-control form-control-sm" type="date" id="to" name="to" value="<?= e($filters['to']) ?>">
		</div>
		<div class="col-12 col-md-3">
			<label class="form-label small" for="q">Cari</label>
			<input class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="No. dokumen / bahan">
		</div>
		<div class="col-6 col-md-1">
			<label class="form-label small" for="type">Tipe</label>
			<select class="form-select form-select-sm" id="type" name="type">
				<option value="">Semua</option>
				<?php foreach (array('IN', 'OUT', 'ADJ') as $t): ?><option <?= $filters['type'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="reason">Alasan</label>
			<select class="form-select form-select-sm" id="reason" name="reason">
				<option value="">Semua</option>
				<?php foreach ($reasons as $k => $r): ?><option value="<?= $k ?>" <?= $filters['reason'] === $k ? 'selected' : '' ?>><?= e($r[0]) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-12 col-md-2 d-flex gap-1">
			<?php if ($filters['ingredient_id']): ?><input type="hidden" name="ingredient_id" value="<?= (int) $filters['ingredient_id'] ?>"><?php endif; ?>
			<button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
			<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('inventory/stock/export?' . $qs()) ?>" title="Export CSV"><i class="bi bi-download"></i></a>
		</div>
	</div>
</form>

<div class="card">
	<div class="card-header small text-muted"><?= number_format($total, 0, ',', '.') ?> pergerakan</div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0" data-dt="true" data-dt-paging="false">
			<thead><tr><th>Waktu</th><th>Dokumen</th><th>Bahan</th><th>Tipe</th><th class="text-end">Qty</th><th class="text-end">Saldo</th><?php if ($can_cost): ?><th class="text-end">Nilai</th><?php endif; ?><th>Keterangan</th><th>Oleh</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="9" class="text-center text-muted py-4">Tidak ada pergerakan untuk filter ini.</td></tr><?php endif; ?>
			<?php foreach ($rows as $m): ?>
				<tr>
					<td class="small text-nowrap"><?= tgl($m['created_at']) ?></td>
					<td class="small"><a href="<?= site_url('inventory/stock/movements?' . http_build_query(array('q' => $m['movement_no'], 'from' => substr($m['created_at'], 0, 10), 'to' => substr($m['created_at'], 0, 10)))) ?>"><code><?= e($m['movement_no']) ?></code></a></td>
					<td><a href="<?= site_url('inventory/ingredients/show/' . $m['ingredient_id']) ?>"><?= e($m['ingredient_name']) ?></a></td>
					<td><span class="badge text-bg-<?= $type_badge[$m['type']] ?>"><?= $m['type'] ?></span></td>
					<td class="text-end text-nowrap <?= $m['qty'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $m['qty'] > 0 ? '+' : '−' ?><?= qty(abs($m['qty'])) ?> <span class="text-muted small"><?= e($m['unit']) ?></span></td>
					<td class="text-end small"><?= qty($m['qty_after']) ?></td>
					<?php if ($can_cost): ?><td class="text-end small"><?= rupiah($m['total_cost']) ?></td><?php endif; ?>
					<td class="small"><?= e(Stock_service::reason_label($m['reason'])) ?>
						<?php if ($m['supplier_name']): ?><span class="text-muted">· <?= e($m['supplier_name']) ?></span><?php endif; ?>
						<?php if ($m['notes']): ?><div class="text-muted"><?= e($m['notes']) ?></div><?php endif; ?>
					</td>
					<td class="small"><?= e($m['user_name'] ?: '-') ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ($pages > 1): ?>
		<div class="card-footer d-flex justify-content-between align-items-center small">
			<span>Halaman <?= $page ?> dari <?= $pages ?></span>
			<div class="btn-group btn-group-sm">
				<a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= site_url('inventory/stock/movements?' . $qs(array('page' => $page - 1))) ?>">Sebelumnya</a>
				<a class="btn btn-outline-secondary <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= site_url('inventory/stock/movements?' . $qs(array('page' => $page + 1))) ?>">Berikutnya</a>
			</div>
		</div>
	<?php endif; ?>
</div>
