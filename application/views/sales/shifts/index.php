<?php defined('BASEPATH') OR exit('No direct script access allowed');
$st = array('open' => array('Berjalan', 'success'), 'pending_approval' => array('Menunggu approval', 'warning'), 'closed' => array('Ditutup', 'secondary'));
?>
<div class="row g-3 mb-3">
	<div class="col-lg-5">
		<div class="card h-100">
			<div class="card-header">Shift saya</div>
			<div class="card-body">
				<?php if ($current): ?>
					<p class="mb-2">Shift <strong><?= e($current['shift_name'] ?: '#' . $current['id']) ?></strong> di <?= e($current['register_name']) ?> sejak <?= tgl($current['opened_at']) ?>, modal <?= rupiah($current['opening_balance']) ?>.</p>
					<a class="btn btn-primary" href="<?= site_url('sales/shifts/show/' . $current['id']) ?>"><i class="bi bi-clipboard-data"></i> Ringkasan & tutup shift</a>
					<?php if (can_any(array('sales.process', 'sales.order'))): ?><a class="btn btn-outline-primary" href="<?= site_url('pos') ?>"><i class="bi bi-cash-coin"></i> POS</a><?php endif; ?>
				<?php elseif (can('sales.shift')): ?>
					<?= form_open('sales/shifts/open', array('class' => 'row g-2')) ?>
						<div class="col-12"><label class="form-label" for="opening_balance">Modal awal (uang tunai di laci)</label>
							<input class="form-control" type="number" min="0" step="100" id="opening_balance" name="opening_balance" required></div>
						<div class="col-6"><label class="form-label small" for="shift_name">Shift</label>
							<select class="form-select" id="shift_name" name="shift_name"><?php foreach ($templates as $t): ?><option><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
						<div class="col-6"><label class="form-label small" for="register_name">Kasir / mesin</label><input class="form-control" id="register_name" name="register_name" value="Kasir 1" maxlength="50"></div>
						<div class="col-12"><button class="btn btn-primary">Buka shift</button></div>
					<?= form_close() ?>
				<?php else: ?>
					<p class="text-muted mb-0">Anda tidak bertugas sebagai kasir.</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<div class="col-lg-7">
		<div class="card h-100">
			<div class="card-header d-flex">Jadwal shift <?php if (can('sales.shift_approve')): ?><a class="ms-auto small" href="<?= site_url('sales/shifts/templates') ?>">atur</a><?php endif; ?></div>
			<ul class="list-group list-group-flush">
				<?php foreach ($templates as $t): ?><li class="list-group-item d-flex"><span><?= e($t['name']) ?></span><span class="ms-auto text-muted"><?= substr($t['start_time'], 0, 5) ?>–<?= substr($t['end_time'], 0, 5) ?></span></li><?php endforeach; ?>
			</ul>
		</div>
	</div>
</div>

<div class="card">
	<div class="card-header"><?= $all ? 'Semua shift' : 'Riwayat shift saya' ?></div>
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>#</th><th>Kasir</th><th>Shift</th><th>Buka</th><th>Tutup</th><th class="text-center">Transaksi</th><th class="text-end">Penjualan</th><th class="text-end">Selisih kas</th><th>Status</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="9" class="text-center text-muted py-4">Belum ada shift.</td></tr><?php endif; ?>
			<?php foreach ($rows as $s): ?>
				<tr>
					<td><a href="<?= site_url('sales/shifts/show/' . $s['id']) ?>">#<?= (int) $s['id'] ?></a></td>
					<td><?= e($s['user_name']) ?></td>
					<td class="small"><?= e($s['shift_name']) ?> · <?= e($s['register_name']) ?></td>
					<td class="small text-nowrap"><?= tgl($s['opened_at']) ?></td>
					<td class="small text-nowrap"><?= tgl($s['closed_at']) ?></td>
					<td class="text-center"><?= (int) $s['tx'] ?></td>
					<td class="text-end"><?= rupiah($s['sales']) ?></td>
					<td class="text-end <?= $s['variance'] !== NULL && (float) $s['variance'] != 0 ? 'text-danger' : '' ?>"><?= $s['variance'] !== NULL ? rupiah($s['variance']) : '-' ?></td>
					<td><span class="badge text-bg-<?= $st[$s['status']][1] ?>"><?= $st[$s['status']][0] ?></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
