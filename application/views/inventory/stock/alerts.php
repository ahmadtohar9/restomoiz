<?php defined('BASEPATH') OR exit('No direct script access allowed');
$today = date('Y-m-d');
$sections = array(
	'low'      => array('Di bawah stok minimum', 'danger', 'Segera buat pesanan.'),
	'reorder'  => array('Sudah mencapai reorder point', 'warning', 'Saatnya membuat PO sesuai jumlah reorder.'),
	'expiring' => array("Kedaluwarsa atau dalam $expiry_days hari", 'danger', 'Pakai lebih dulu atau keluarkan dari stok.'),
	'dead'     => array("Dead stock (tidak keluar ≥ $dead_days hari)", 'secondary', 'Pertimbangkan dipakai untuk menu promo atau dikembalikan.'),
	'over'     => array('Di atas stok maksimum', 'info', 'Risiko overstock, tahan pembelian.'),
);
?>
<div class="d-flex flex-wrap gap-2 mb-3">
	<?php foreach ($sections as $k => $s): ?>
		<a class="btn btn-sm btn-outline-<?= $s[1] ?>" href="#<?= $k ?>"><?= e($s[0]) ?> <span class="badge text-bg-<?= $s[1] ?>"><?= count($alerts[$k]) ?></span></a>
	<?php endforeach; ?>
</div>

<?php foreach ($sections as $k => $s): $rows = $alerts[$k]; ?>
<div class="card mb-3" id="<?= $k ?>">
	<div class="card-header d-flex align-items-center">
		<i class="bi bi-exclamation-triangle-fill text-<?= $s[1] ?> me-2"></i> <?= e($s[0]) ?>
		<span class="badge text-bg-<?= $s[1] ?> ms-2"><?= count($rows) ?></span>
		<span class="ms-auto small text-muted fw-normal d-none d-md-inline"><?= e($s[2]) ?></span>
	</div>
	<?php if (empty($rows)): ?>
		<div class="card-body small text-muted py-2">Tidak ada.</div>
	<?php elseif ($k === 'expiring'): ?>
		<div class="table-responsive">
			<table class="table table-sm align-middle mb-0">
				<thead><tr><th>Bahan</th><th>Kedaluwarsa</th><th class="text-end">Sisa batch</th><?php if ($can_cost): ?><th class="text-end">Nilai</th><?php endif; ?><th></th></tr></thead>
				<tbody>
				<?php foreach ($rows as $r): ?>
					<tr>
						<td><a href="<?= site_url('inventory/ingredients/show/' . $r['id']) ?>"><?= e($r['name']) ?></a></td>
						<td><?= tgl($r['expiry_date'], FALSE) ?> <?= $r['expiry_date'] < $today ? '<span class="badge text-bg-danger">lewat</span>' : '' ?></td>
						<td class="text-end"><?= qty($r['qty_remaining']) ?> <?= e($r['unit']) ?></td>
						<?php if ($can_cost): ?><td class="text-end"><?= rupiah($r['qty_remaining'] * $r['unit_cost']) ?></td><?php endif; ?>
						<td class="text-end">
							<?php if (can('inventory.adjust')): ?>
								<a class="btn btn-sm btn-outline-danger" href="<?= site_url('inventory/stock/out?ingredient_id=' . $r['id'] . '&batch_id=' . $r['batch_id']) ?>">Keluarkan</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else: ?>
		<div class="table-responsive">
			<table class="table table-sm align-middle mb-0">
				<thead><tr><th>Bahan</th><th class="text-end">Stok</th>
					<?php if ($k === 'dead'): ?><th>Keluar terakhir</th><?php if ($can_cost): ?><th class="text-end">Nilai tertahan</th><?php endif; ?>
					<?php else: ?><th class="text-end">Min</th><th class="text-end">Reorder point</th><th class="text-end">Max</th><th class="text-end">Saran pesan</th><th>Supplier</th><?php endif; ?>
				</tr></thead>
				<tbody>
				<?php foreach ($rows as $r): ?>
					<tr>
						<td><a href="<?= site_url('inventory/ingredients/show/' . $r['id']) ?>"><?= e($r['name']) ?></a> <span class="small text-muted"><?= e($r['code']) ?></span></td>
						<td class="text-end"><?= qty($r['qty_on_hand']) ?> <?= e($r['unit']) ?></td>
						<?php if ($k === 'dead'): ?>
							<td class="small"><?= $r['last_out_at'] ? tgl($r['last_out_at'], FALSE) : 'belum pernah' ?></td>
							<?php if ($can_cost): ?><td class="text-end"><?= rupiah($r['stock_value']) ?></td><?php endif; ?>
						<?php else: ?>
							<td class="text-end small"><?= qty($r['min_stock']) ?></td>
							<td class="text-end small"><?= qty($r['reorder_point']) ?></td>
							<td class="text-end small"><?= $r['max_stock'] > 0 ? qty($r['max_stock']) : '-' ?></td>
							<td class="text-end small">
								<?php
								// Saran: jumlah reorder, atau sampai stok maksimum kalau diisi.
								$suggest = $r['reorder_qty'] > 0 ? $r['reorder_qty'] : ($r['max_stock'] > 0 ? $r['max_stock'] - $r['qty_on_hand'] : 0);
								echo $k !== 'over' && $suggest > 0 ? qty($suggest) . ' ' . e($r['unit']) : '-';
								?>
							</td>
							<td class="small"><?= e($r['supplier_name'] ?: '-') ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
<?php endforeach; ?>
