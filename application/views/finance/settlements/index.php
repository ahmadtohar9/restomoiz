<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<form class="d-flex gap-2 mb-3" method="get">
	<input class="form-control" type="month" name="period" value="<?= e($period) ?>" max="<?= date('Y-m') ?>" style="max-width: 200px">
	<button class="btn btn-outline-secondary">Tampilkan</button>
	<a class="btn btn-outline-primary ms-auto" href="<?= site_url('finance/reconciliations/period/' . $period) ?>"><i class="bi bi-bank"></i> Rekonsiliasi bank <?= e($period) ?></a>
</form>
<p class="small text-muted">Setiap hari berikutnya, cocokkan penjualan per metode bayar dengan setoran tunai ke bank, settlement EDC, dan settlement e-wallet.</p>
<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Tanggal</th><th class="text-center">Transaksi</th><th class="text-end">Penjualan</th><th class="text-end">Selisih</th><th>Status</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($days as $d): $s = $d['settlement']; ?>
				<tr>
					<td><?= tgl($d['date'], FALSE) ?></td>
					<td class="text-center"><?= (int) $d['tx'] ?></td>
					<td class="text-end"><?= rupiah($d['sales']) ?></td>
					<td class="text-end <?= $s && $s['variance'] !== NULL && abs($s['variance']) >= 1 ? 'text-danger' : '' ?>"><?= $s && $s['variance'] !== NULL ? rupiah($s['variance']) : '-' ?></td>
					<td><?php if ( ! $s): ?><span class="badge text-bg-light border"><?= $d['tx'] ? 'Belum' : 'Tidak ada transaksi' ?></span>
						<?php elseif ($s['status'] === 'verified'): ?><span class="badge text-bg-success">Terverifikasi</span>
						<?php else: ?><span class="badge text-bg-warning">Draft</span><?php endif; ?></td>
					<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('finance/settlements/day/' . $d['date']) ?>"><?= $s && $s['status'] === 'verified' ? 'Lihat' : 'Proses' ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
