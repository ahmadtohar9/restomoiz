<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<form class="d-flex gap-2 mb-3" onsubmit="location.href='<?= site_url('finance/reconciliations/period') ?>/' + this.period.value; return false;">
	<input class="form-control" type="month" name="period" value="<?= date('Y-m', strtotime('first day of last month')) ?>" max="<?= date('Y-m') ?>" style="max-width: 200px" required>
	<button class="btn btn-primary">Buka periode</button>
</form>
<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Periode</th><th>Bank</th><th class="text-end">Seharusnya masuk</th><th class="text-end">Mutasi bank</th><th class="text-end">Selisih</th><th>Status</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada rekonsiliasi.</td></tr><?php endif; ?>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><a href="<?= site_url('finance/reconciliations/period/' . $r['period']) ?>"><?= e($r['period']) ?></a></td>
					<td class="small"><?= e($r['bank_name'] ?: '-') ?></td>
					<td class="text-end"><?= rupiah($r['expected_deposit']) ?></td>
					<td class="text-end"><?= $r['bank_credits'] !== NULL ? rupiah($r['bank_credits']) : '-' ?></td>
					<td class="text-end <?= $r['variance'] !== NULL && abs($r['variance']) >= 1 ? 'text-danger' : '' ?>"><?= $r['variance'] !== NULL ? rupiah($r['variance']) : '-' ?></td>
					<td><?= $r['status'] === 'reconciled' ? '<span class="badge text-bg-success">Selesai</span> <span class="small text-muted">' . e($r['reconciled_by_name']) . '</span>' : '<span class="badge text-bg-warning">Draft</span>' ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
