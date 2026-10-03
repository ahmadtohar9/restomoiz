<?php defined('BASEPATH') OR exit('No direct script access allowed');
$low = array_filter($rows, function ($r) use ($warn) { return $r['margin'] < $warn; });
$avg = $rows ? array_sum(array_column($rows, 'margin')) / count($rows) : 0;
?>
<div class="row g-3 mb-3">
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Menu dengan resep</div><div class="stat-value"><?= count($rows) ?></div></div></div></div>
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Rata-rata margin</div><div class="stat-value"><?= number_format($avg, 1, ',', '.') ?>%</div></div></div></div>
	<div class="col-6 col-md-3"><div class="card <?= $low ? 'border-danger' : '' ?>"><div class="card-body"><div class="stat-label">Margin &lt; <?= $warn ?>%</div><div class="stat-value <?= $low ? 'text-danger' : '' ?>"><?= count($low) ?></div></div></div></div>
	<div class="col-6 col-md-3"><div class="card <?= $no_recipe ? 'border-warning' : '' ?>"><div class="card-body"><div class="stat-label">Belum ada resep</div><div class="stat-value"><?= count($no_recipe) ?></div></div></div></div>
</div>

<div class="card mb-3">
	<div class="card-header d-flex align-items-center">Margin per menu <span class="small text-muted fw-normal ms-2">(terendah di atas)</span>
		<a class="ms-auto small" href="<?= site_url('menu/analysis?export=1') ?>"><i class="bi bi-download"></i> CSV</a></div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead><tr><th>Menu</th><th class="text-end">Harga</th><th class="text-end">COGS</th><th class="text-end">Laba kotor</th><th class="text-end">Margin</th><th class="text-end">COGS vs 30 hari lalu</th><th class="text-end" title="Harga minimal agar margin = <?= $warn ?>%">Harga utk <?= $warn ?>%</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada menu dengan resep & harga.</td></tr><?php endif; ?>
			<?php foreach ($rows as $r): $is_low = $r['margin'] < $warn; ?>
				<tr class="<?= $is_low ? 'table-danger' : '' ?>">
					<td><a href="<?= site_url('menu/items/show/' . $r['menu']['id'] . '#v' . $r['variant']['id']) ?>"><?= e($r['menu']['name']) ?></a><?= $r['variant']['name'] !== 'Reguler' ? ' <span class="text-muted small">' . e($r['variant']['name']) . '</span>' : '' ?>
						<?= $r['missing_cost'] ? ' <i class="bi bi-exclamation-circle text-warning" title="Ada bahan tanpa harga"></i>' : '' ?></td>
					<td class="text-end"><?= rupiah($r['price']) ?></td>
					<td class="text-end"><?= rupiah($r['cogs']) ?><?= $r['cogs_source'] === 'manual' ? ' <span class="badge text-bg-warning" title="HPP diisi manual">M</span>' : '' ?></td>
					<td class="text-end"><?= rupiah($r['profit']) ?></td>
					<td class="text-end fw-medium <?= $is_low ? 'text-danger' : 'text-success' ?>"><?= number_format($r['margin'], 1, ',', '.') ?>%</td>
					<td class="text-end small <?= $r['cogs_change'] > 5 ? 'text-danger' : '' ?>"><?= $r['cogs_change'] === NULL ? '-' : ($r['cogs_change'] > 0 ? '+' : '') . number_format($r['cogs_change'], 1, ',', '.') . '%' ?></td>
					<td class="text-end small"><?= $is_low && $r['target_price'] ? rupiah($r['target_price']) : '' ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="card-body small text-muted py-2">Rekomendasi untuk menu bermargin rendah: naikkan harga ke kolom terakhir, atau kurangi biaya resep (porsi / bahan alternatif). Batas margin bisa diubah di Pengaturan.</div>
</div>

<?php if ($no_recipe): ?>
<div class="card">
	<div class="card-header">Menu aktif tanpa resep</div>
	<ul class="list-group list-group-flush small">
		<?php foreach ($no_recipe as $n): ?>
			<li class="list-group-item d-flex"><span><?= e($n['menu']['name']) ?> <?= $n['variant']['name'] !== 'Reguler' ? '— ' . e($n['variant']['name']) : '' ?></span>
				<?php if (can('menu.edit')): ?><a class="ms-auto" href="<?= site_url('menu/items/recipe/' . $n['variant']['id']) ?>">isi resep</a><?php endif; ?></li>
		<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>
