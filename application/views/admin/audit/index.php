<?php defined('BASEPATH') OR exit('No direct script access allowed');
$qs = function (array $extra = array()) use ($filters) {
	return http_build_query(array_filter(array_merge($filters, $extra), 'strlen'));
};
$badge = array('success' => 'success', 'denied' => 'danger', 'failed' => 'warning');
?>
<form class="card mb-3" method="get" action="<?= site_url('admin/audit') ?>">
	<div class="card-body row g-2 align-items-end">
		<div class="col-6 col-md-2">
			<label class="form-label small" for="from">Dari</label>
			<input class="form-control form-control-sm" type="date" id="from" name="from" value="<?= e($filters['from']) ?>">
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="to">Sampai</label>
			<input class="form-control form-control-sm" type="date" id="to" name="to" value="<?= e($filters['to']) ?>">
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="user">User</label>
			<input class="form-control form-control-sm" id="user" name="user" value="<?= e($filters['user']) ?>" placeholder="username">
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="action">Aksi</label>
			<select class="form-select form-select-sm" id="action" name="action">
				<option value="">Semua</option>
				<?php foreach ($actions as $a): ?>
					<option <?= $filters['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="result">Hasil</label>
			<select class="form-select form-select-sm" id="result" name="result">
				<option value="">Semua</option>
				<?php foreach (array('success' => 'Sukses', 'denied' => 'Ditolak', 'failed' => 'Gagal') as $k => $v): ?>
					<option value="<?= $k ?>" <?= $filters['result'] === $k ? 'selected' : '' ?>><?= $v ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2 d-flex gap-1">
			<button class="btn btn-sm btn-primary flex-fill" type="submit"><i class="bi bi-funnel"></i> Filter</button>
			<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/audit/export?' . $qs()) ?>" title="Export CSV"><i class="bi bi-download"></i></a>
		</div>
	</div>
</form>

<div class="card">
	<div class="card-header small text-muted"><?= number_format($total, 0, ',', '.') ?> entri</div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0" data-dt="true" data-dt-paging="false">
			<thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Hasil</th><th>Detail</th><th>IP</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?>
				<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data untuk filter ini.</td></tr>
			<?php endif; ?>
			<?php foreach ($rows as $row): ?>
				<tr>
					<td class="text-nowrap small"><?= tgl($row['created_at']) ?></td>
					<td><?= e($row['username'] ?: '-') ?></td>
					<td><code><?= e($row['action']) ?></code></td>
					<td><span class="badge text-bg-<?= $badge[$row['result']] ?>"><?= e($row['result']) ?></span></td>
					<td class="small audit-detail"><?= e($row['detail']) ?></td>
					<td class="small text-muted"><?= e($row['ip_address']) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ($pages > 1): ?>
		<div class="card-footer d-flex justify-content-between align-items-center small">
			<span>Halaman <?= $page ?> dari <?= $pages ?></span>
			<div class="btn-group btn-group-sm">
				<a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= site_url('admin/audit?' . $qs(array('page' => $page - 1))) ?>">Sebelumnya</a>
				<a class="btn btn-outline-secondary <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= site_url('admin/audit?' . $qs(array('page' => $page + 1))) ?>">Berikutnya</a>
			</div>
		</div>
	<?php endif; ?>
</div>
