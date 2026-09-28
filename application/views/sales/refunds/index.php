<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
	<a class="btn btn-sm <?= $filters['status'] === 'pending_approval' ? 'btn-warning' : 'btn-outline-warning' ?>" href="<?= site_url('sales/refunds?status=pending_approval') ?>">Menunggu approval <span class="badge text-bg-light"><?= (int) $pending ?></span></a>
	<a class="btn btn-sm <?= $filters['status'] === 'approved' ? 'btn-info' : 'btn-outline-info' ?>" href="<?= site_url('sales/refunds?status=approved') ?>">Disetujui, belum dibayar <span class="badge text-bg-light"><?= (int) $waiting ?></span></a>
	<form class="d-flex gap-1 ms-auto" method="get">
		<input class="form-control form-control-sm" type="date" name="from" value="<?= e($filters['from']) ?>">
		<input class="form-control form-control-sm" type="date" name="to" value="<?= e($filters['to']) ?>">
		<select class="form-select form-select-sm" name="status"><option value="">Semua status</option>
			<?php foreach (Refund_service::$status as $k => $s): ?><option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($s[0]) ?></option><?php endforeach; ?></select>
		<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button>
	</form>
</div>
<p class="small text-muted">Refund diajukan dari halaman detail transaksi (tombol <em>Refund</em>).</p>
<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>No.</th><th>Transaksi</th><th>Diajukan</th><th>Alasan</th><th class="text-end">Nilai</th><th>Approval</th><th>Status</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">Tidak ada refund.</td></tr><?php endif; ?>
			<?php foreach ($rows as $r): $s = Refund_service::$status[$r['status']]; ?>
				<tr>
					<td><a href="<?= site_url('sales/refunds/show/' . $r['id']) ?>"><?= e($r['refund_number']) ?></a><?= $r['is_full'] ? ' <span class="badge text-bg-dark">penuh</span>' : '' ?></td>
					<td class="small"><a href="<?= site_url('sales/orders/show/' . $r['order_id']) ?>"><?= e($r['order_number']) ?></a></td>
					<td class="small"><?= e($r['requested_by_name']) ?><div class="text-muted"><?= tgl($r['requested_at']) ?></div></td>
					<td class="small"><?= e(Refund_service::$reasons[$r['reason_code']]) ?><div class="text-muted"><?= e($r['reason']) ?></div></td>
					<td class="text-end"><?= rupiah($r['refund_amount']) ?><div class="small text-muted"><?= $r['refund_method'] === 'cash' ? 'tunai' : 'metode asal' ?></div></td>
					<td class="small"><?= e(Refund_service::$levels[$r['required_level']]) ?><?= $r['approved_by_name'] ? '<div class="text-muted">' . e($r['approved_by_name']) . '</div>' : '' ?></td>
					<td><span class="badge text-bg-<?= $s[1] ?>"><?= e($s[0]) ?></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
