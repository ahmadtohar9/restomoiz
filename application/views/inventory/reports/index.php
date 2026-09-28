<?php defined('BASEPATH') OR exit('No direct script access allowed');
$can_cost = can('inventory.view_cost');
?>
<div class="row g-3 mb-3">
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Bahan aktif</div><div class="stat-value"><?= (int) $totals['items'] ?></div></div></div></div>
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Stok habis</div><div class="stat-value"><?= (int) $totals['empty'] ?></div></div></div></div>
	<?php if ($can_cost): ?>
		<div class="col-12 col-md-6"><div class="card"><div class="card-body"><div class="stat-label">Total nilai stok (FIFO)</div><div class="stat-value"><?= rupiah($totals['value']) ?></div></div></div></div>
	<?php endif; ?>
</div>

<ul class="nav nav-tabs mb-3">
	<?php foreach ($tabs as $k => $label): ?>
		<li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="<?= site_url('inventory/reports/' . $k) ?>"><?= e($label) ?></a></li>
	<?php endforeach; ?>
	<li class="nav-item ms-auto"><a class="nav-link" href="<?= site_url('inventory/reports/' . $tab . '?export=1') ?>"><i class="bi bi-download"></i> CSV</a></li>
</ul>

<div class="card">
	<div class="table-responsive">
	<?php if ($tab === 'value'): $sum = 0; ?>
		<table class="table align-middle mb-0">
			<thead><tr><th>Kategori</th><th class="text-center">Jumlah bahan</th><th class="text-center">Ada stok</th><?php if ($can_cost): ?><th class="text-end">Nilai stok</th><th style="width: 30%">Porsi</th><?php endif; ?></tr></thead>
			<tbody>
			<?php foreach ($rows as $r) { $sum += $r['value']; } ?>
			<?php if (empty($rows)): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada data.</td></tr><?php endif; ?>
			<?php foreach ($rows as $r): $pct = $sum > 0 ? $r['value'] / $sum * 100 : 0; ?>
				<tr>
					<td><?= e($r['name']) ?></td>
					<td class="text-center"><?= (int) $r['items'] ?></td>
					<td class="text-center"><?= (int) $r['in_stock'] ?></td>
					<?php if ($can_cost): ?>
						<td class="text-end"><?= rupiah($r['value']) ?></td>
						<td><div class="progress" style="height: 8px" title="<?= number_format($pct, 1, ',', '.') ?>%"><div class="progress-bar" style="width: <?= $pct ?>%"></div></div></td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<?php if ($can_cost && $rows): ?><tfoot><tr><th colspan="3">Total</th><th class="text-end"><?= rupiah($sum) ?></th><th></th></tr></tfoot><?php endif; ?>
		</table>

	<?php elseif ($tab === 'aging'): ?>
		<table class="table table-sm align-middle mb-0">
			<thead><tr><th>Bahan</th><th class="text-end">Stok</th>
				<?php if ($can_cost): ?><th class="text-end">0–30 hari</th><th class="text-end">31–60</th><th class="text-end">61–90</th><th class="text-end">&gt; 90</th><th class="text-end">Total</th><?php endif; ?>
				<th>Batch tertua</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">Tidak ada stok.</td></tr><?php endif; ?>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><a href="<?= site_url('inventory/ingredients/show/' . $r['id']) ?>"><?= e($r['name']) ?></a></td>
					<td class="text-end text-nowrap"><?= qty($r['qty']) ?> <span class="small text-muted"><?= e($r['unit']) ?></span></td>
					<?php if ($can_cost): ?>
						<td class="text-end small"><?= $r['v0_30'] > 0 ? rupiah($r['v0_30']) : '-' ?></td>
						<td class="text-end small"><?= $r['v31_60'] > 0 ? rupiah($r['v31_60']) : '-' ?></td>
						<td class="text-end small <?= $r['v61_90'] > 0 ? 'text-warning-emphasis' : '' ?>"><?= $r['v61_90'] > 0 ? rupiah($r['v61_90']) : '-' ?></td>
						<td class="text-end small <?= $r['v90'] > 0 ? 'text-danger' : '' ?>"><?= $r['v90'] > 0 ? rupiah($r['v90']) : '-' ?></td>
						<td class="text-end fw-medium"><?= rupiah($r['total']) ?></td>
					<?php endif; ?>
					<td class="small"><?= tgl($r['oldest'], FALSE) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

	<?php else: ?>
		<div class="card-body small text-muted pb-0">
			<?php if ($tab === 'slow'): ?>
				Bahan yang tidak keluar selama <?= $days ?>–<?= $dead_days - 1 ?> hari. Batas bisa diubah di Pengaturan.
			<?php else: ?>
				Bahan yang tidak keluar selama <?= $days ?> hari atau lebih. Batas bisa diubah di Pengaturan.
			<?php endif; ?>
		</div>
		<table class="table table-sm align-middle mb-0">
			<thead><tr><th>Bahan</th><th class="text-end">Stok</th><?php if ($can_cost): ?><th class="text-end">Nilai tertahan</th><?php endif; ?><th class="text-center">Hari diam</th><th>Keluar terakhir</th><th class="text-end">Pemakaian 90 hari</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted py-4">Tidak ada. 👍</td></tr><?php endif; ?>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><a href="<?= site_url('inventory/ingredients/show/' . $r['id']) ?>"><?= e($r['name']) ?></a></td>
					<td class="text-end text-nowrap"><?= qty($r['qty_on_hand']) ?> <span class="small text-muted"><?= e($r['unit']) ?></span></td>
					<?php if ($can_cost): ?><td class="text-end"><?= rupiah($r['stock_value']) ?></td><?php endif; ?>
					<td class="text-center"><?= (int) $r['idle_days'] ?></td>
					<td class="small"><?= $r['last_out_at'] ? tgl($r['last_out_at'], FALSE) : 'belum pernah' ?></td>
					<td class="text-end small"><?= qty($r['out_90d']) ?> <?= e($r['unit']) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	</div>
</div>
