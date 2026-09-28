<?php defined('BASEPATH') OR exit('No direct script access allowed');
$pct = function ($a, $b) { return $b > 0 ? number_format($a / $b * 100, 1, ',', '.') . '%' : '-'; };
?>
<ul class="nav nav-tabs mb-3">
	<?php foreach ($tabs as $k => $label): ?>
		<li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="<?= site_url('purchase/reports/' . $k . '?from=' . $from . '&to=' . $to) ?>"><?= e($label) ?></a></li>
	<?php endforeach; ?>
</ul>

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
	<?php if (in_array($tab, array('supplier', 'category', 'performance'), TRUE)): ?>
		<form class="d-flex gap-1" method="get">
			<input class="form-control form-control-sm" type="date" name="from" value="<?= e($from) ?>">
			<input class="form-control form-control-sm" type="date" name="to" value="<?= e($to) ?>">
			<button class="btn btn-sm btn-outline-secondary">Tampilkan</button>
		</form>
	<?php elseif ($tab === 'price'): ?>
		<span class="small text-muted">Rata-rata harga beli per satuan standar dari penerimaan barang, 6 bulan terakhir.</span>
	<?php else: ?>
		<span class="small text-muted">Sisa tagihan invoice yang sudah disetujui, per umur keterlambatan dari jatuh tempo.</span>
	<?php endif; ?>
	<a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= site_url('purchase/reports/' . $tab . '?export=1&from=' . $from . '&to=' . $to) ?>"><i class="bi bi-download"></i> CSV</a>
</div>

<div class="card">
	<div class="table-responsive">
	<?php if ($tab === 'supplier' OR $tab === 'category'): $sum = 0; foreach ($rows as $r) { $sum += $r['total']; } ?>
		<table class="table align-middle mb-0">
			<thead><tr><th><?= $tab === 'supplier' ? 'Supplier' : 'Kategori' ?></th>
				<?php if ($tab === 'supplier'): ?><th class="text-center">PO</th><th class="text-center">Penerimaan</th><?php else: ?><th class="text-center">Jenis bahan</th><?php endif; ?>
				<th class="text-end">Total belanja</th><th style="width: 28%">Porsi</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada penerimaan barang di periode ini.</td></tr><?php endif; ?>
			<?php foreach ($rows as $r): $p = $sum > 0 ? $r['total'] / $sum * 100 : 0; ?>
				<tr>
					<td><?= $tab === 'supplier' ? '<a href="' . site_url('inventory/suppliers/show/' . $r['id']) . '">' . e($r['name']) . '</a>' : e($r['name']) ?></td>
					<?php if ($tab === 'supplier'): ?><td class="text-center"><?= (int) $r['po_count'] ?></td><td class="text-center"><?= (int) $r['gr_count'] ?></td>
					<?php else: ?><td class="text-center"><?= (int) $r['items'] ?></td><?php endif; ?>
					<td class="text-end"><?= rupiah($r['total']) ?></td>
					<td><div class="progress" style="height: 8px" title="<?= number_format($p, 1, ',', '.') ?>%"><div class="progress-bar" style="width: <?= $p ?>%"></div></div></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<?php if ($rows): ?><tfoot><tr><th colspan="<?= $tab === 'supplier' ? 3 : 2 ?>">Total</th><th class="text-end"><?= rupiah($sum) ?></th><th></th></tr></tfoot><?php endif; ?>
		</table>

	<?php elseif ($tab === 'price'): ?>
		<table class="table table-sm align-middle mb-0">
			<thead><tr><th>Bahan</th><?php $bln = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
				foreach ($labels as $ym): ?><th class="text-end small"><?= $bln[(int) substr($ym, 5, 2)] . ' ' . substr($ym, 2, 2) ?></th><?php endforeach; ?><th class="text-end">Perubahan</th></tr></thead>
			<tbody>
			<?php if (empty($items)): ?><tr><td colspan="<?= count($labels) + 2 ?>" class="text-center text-muted py-4">Belum ada data pembelian.</td></tr><?php endif; ?>
			<?php foreach ($items as $id => $it): ?>
				<tr>
					<td><a href="<?= site_url('inventory/ingredients/show/' . $id) ?>"><?= e($it['name']) ?></a> <span class="small text-muted">/ <?= e($it['unit']) ?></span></td>
					<?php foreach ($labels as $ym): ?><td class="text-end small"><?= isset($it['months'][$ym]) ? rupiah($it['months'][$ym]) : '<span class="text-muted">-</span>' ?></td><?php endforeach; ?>
					<td class="text-end fw-medium <?= $it['change'] > 5 ? 'text-danger' : ($it['change'] < -5 ? 'text-success' : '') ?>"><?= ($it['change'] > 0 ? '+' : '') . number_format($it['change'], 1, ',', '.') ?>%</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

	<?php elseif ($tab === 'performance'): ?>
		<table class="table align-middle mb-0">
			<thead><tr><th>Supplier</th><th class="text-center">PO diterima</th><th class="text-center">Tepat waktu</th><th class="text-center">Fill rate</th><th class="text-center">Lead time aktual</th><th class="text-center">Kualitas</th><th class="text-end">Nilai PO</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada PO dengan penerimaan di periode ini.</td></tr><?php endif; ?>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><a href="<?= site_url('inventory/suppliers/show/' . $r['id']) ?>"><?= e($r['name']) ?></a></td>
					<td class="text-center"><?= (int) $r['po_count'] ?></td>
					<td class="text-center"><?= $pct($r['on_time'], $r['with_date']) ?></td>
					<td class="text-center"><?= $pct($r['received'], $r['ordered']) ?></td>
					<td class="text-center"><?= $r['avg_lead'] !== NULL ? number_format($r['avg_lead'], 1, ',', '.') . ' hari' : '-' ?></td>
					<td class="text-center"><?= $r['quality_score'] ? str_repeat('★', (int) $r['quality_score']) : '-' ?></td>
					<td class="text-end"><?= rupiah($r['total']) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<div class="card-body small text-muted">Tepat waktu = penerimaan pertama ≤ tanggal kirim di PO. Fill rate = jumlah diterima ÷ jumlah dipesan. Lead time = hari dari PO disetujui sampai barang pertama diterima.</div>

	<?php else: ?>
		<table class="table align-middle mb-0">
			<thead><tr><th>Supplier</th><th class="text-end">Belum jatuh tempo</th><th class="text-end">1–30</th><th class="text-end">31–60</th><th class="text-end">61–90</th><th class="text-end">&gt; 90</th><th class="text-end">Total</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">Tidak ada hutang supplier. 👍</td></tr><?php endif; ?>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td><a href="<?= site_url('purchase/invoices?payment=outstanding&supplier_id=' . $r['id']) ?>"><?= e($r['name']) ?></a> <span class="small text-muted">(<?= (int) $r['invoices'] ?> invoice)</span></td>
					<?php foreach (array('current_due', 'd1_30', 'd31_60', 'd61_90', 'd90') as $i => $k): ?>
						<td class="text-end <?= $i > 0 && $r[$k] > 0 ? 'text-danger' : '' ?>"><?= $r[$k] > 0 ? rupiah($r[$k]) : '-' ?></td>
					<?php endforeach; ?>
					<td class="text-end fw-semibold"><?= rupiah($r['total']) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	</div>
</div>
