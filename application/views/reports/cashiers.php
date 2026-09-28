<?php defined('BASEPATH') OR exit('No direct script access allowed');
$base = 'reports/cashiers';
$this->load->view('reports/_filters', compact('from', 'to', 'preset', 'base'));
?>
<div class="card">
	<div class="card-header d-flex">Kinerja kasir <a class="small ms-auto no-print" href="<?= site_url('reports/cashiers?from=' . $from . '&to=' . $to . '&export=1') ?>"><i class="bi bi-download"></i> CSV</a></div>
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Kasir</th><th class="text-end">Transaksi</th><th class="text-end">Penjualan</th><th class="text-end">Rata-rata bill</th><th class="text-end">Refund</th><th class="text-end">Refund rate</th><th class="text-end">Selisih kas</th><th class="text-center">Shift</th><th class="text-center">Jam tersibuk</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="9" class="text-center text-muted py-4">Belum ada aktivitas kasir di periode ini.</td></tr><?php endif; ?>
			<?php foreach ($rows as $r): $rate = $r['tx'] ? $r['refunds'] / $r['tx'] * 100 : 0; ?>
				<tr>
					<td><?= e($r['name']) ?></td>
					<td class="text-end"><?= (int) $r['tx'] ?></td>
					<td class="text-end"><?= rupiah($r['total']) ?></td>
					<td class="text-end"><?= rupiah($r['tx'] ? $r['total'] / $r['tx'] : 0) ?></td>
					<td class="text-end"><?= (int) $r['refunds'] ?><div class="small text-muted"><?= rupiah($r['refund_total']) ?></div></td>
					<td class="text-end <?= $rate > 5 ? 'text-danger fw-medium' : '' ?>"><?= number_format($rate, 1, ',', '.') ?>%</td>
					<td class="text-end <?= (float) $r['cash_variance'] != 0 ? 'text-danger' : '' ?>"><?= rupiah($r['cash_variance']) ?></td>
					<td class="text-center"><?= (int) $r['shifts'] ?></td>
					<td class="text-center"><?= $r['peak_hour'] !== NULL ? sprintf('%02d:00', $r['peak_hour']) : '-' ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="card-body small text-muted py-2">Refund dihitung dari pengajuan kasir tersebut (yang tidak ditolak). Selisih kas = total selisih shift yang ditutup di periode ini (negatif = kurang).</div>
</div>
