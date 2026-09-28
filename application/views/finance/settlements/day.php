<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === NULL || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
$verified = $row && $row['status'] === 'verified';
$st = array('open' => 'Berjalan', 'pending_approval' => 'Menunggu approval', 'closed' => 'Ditutup');
?>
<p><a href="<?= site_url('finance/settlements?period=' . substr($date, 0, 7)) ?>"><i class="bi bi-arrow-left"></i> Settlement harian</a></p>
<?php if ($exp['open_shifts']): ?><div class="alert alert-warning small">Ada <?= (int) $exp['open_shifts'] ?> shift yang belum ditutup/diapprove sampai tanggal ini. Verifikasi setelah semua shift selesai.</div><?php endif; ?>
<div class="row g-3">
	<div class="col-lg-7">
		<?= form_open('finance/settlements/day/' . $date, array('class' => 'card', 'id' => 'settle-form')) ?>
			<div class="card-header d-flex">Penjualan vs uang diterima <?php if ($verified): ?><span class="badge text-bg-success ms-2">Terverifikasi</span><?php endif; ?></div>
			<div class="table-responsive">
				<table class="table align-middle mb-0">
					<thead><tr><th>Kanal</th><th class="text-end">Penjualan</th><th class="text-end">Refund</th><th class="text-end">Seharusnya</th><th style="width: 170px">Aktual diterima</th></tr></thead>
					<tr><td>Tunai (setoran ke bank)</td><td class="text-end"><?= rupiah($exp['cash']) ?></td><td class="text-end">−<?= rupiah($exp['refund_cash']) ?></td><td class="text-end fw-medium"><?= rupiah($exp['expected_cash']) ?></td>
						<td><input class="form-control form-control-sm text-end s-in" type="number" step="any" min="0" name="actual_cash_deposit" value="<?= e($num($row['actual_cash_deposit'] ?? NULL)) ?>" <?= $verified ? 'disabled' : '' ?>></td></tr>
					<tr><td>Kartu debit + kredit (settlement EDC)</td><td class="text-end"><?= rupiah($exp['debit'] + $exp['credit']) ?></td><td class="text-end">−<?= rupiah($exp['refund_card']) ?></td><td class="text-end fw-medium"><?= rupiah($exp['expected_card']) ?></td>
						<td><input class="form-control form-control-sm text-end s-in" type="number" step="any" min="0" name="actual_card" value="<?= e($num($row['actual_card'] ?? NULL)) ?>" <?= $verified ? 'disabled' : '' ?>></td></tr>
					<tr><td>E-wallet / QRIS</td><td class="text-end"><?= rupiah($exp['ewallet']) ?></td><td class="text-end">−<?= rupiah($exp['refund_ewallet']) ?></td><td class="text-end fw-medium"><?= rupiah($exp['expected_ewallet']) ?></td>
						<td><input class="form-control form-control-sm text-end s-in" type="number" step="any" min="0" name="actual_ewallet" value="<?= e($num($row['actual_ewallet'] ?? NULL)) ?>" <?= $verified ? 'disabled' : '' ?>></td></tr>
					<tr><td colspan="4" class="text-end">Biaya MDR / admin yang dipotong</td>
						<td><input class="form-control form-control-sm text-end s-in" type="number" step="any" min="0" name="fees" value="<?= e($num($row['fees'] ?? 0)) ?>" <?= $verified ? 'disabled' : '' ?>></td></tr>
					<tr class="fw-bold"><td colspan="3" class="text-end">Total seharusnya</td><td class="text-end"><?= rupiah($exp['expected_total']) ?></td>
						<td class="text-end" id="s-var" data-expected="<?= e($exp['expected_total']) ?>"><?= isset($row['variance']) && $row['variance'] !== NULL ? 'selisih ' . rupiah($row['variance']) : '' ?></td></tr>
				</table>
			</div>
			<div class="card-body">
				<label class="form-label small" for="notes">Catatan (wajib jika ada selisih)</label>
				<input class="form-control mb-3" id="notes" name="notes" value="<?= e($row['notes'] ?? '') ?>" maxlength="255" <?= $verified ? 'disabled' : '' ?>>
				<?php if ( ! $verified): ?>
					<div class="d-flex gap-2">
						<button class="btn btn-outline-primary" name="action" value="save">Simpan draft</button>
						<button class="btn btn-success" name="action" value="verify" data-confirm-click="Verifikasi settlement <?= e($date) ?>? Data dikunci setelah diverifikasi.">Verifikasi</button>
					</div>
				<?php else: ?>
					<p class="small text-muted mb-0">Diverifikasi <?= tgl($row['verified_at']) ?>.</p>
				<?php endif; ?>
			</div>
		<?= form_close() ?>
	</div>
	<div class="col-lg-5">
		<div class="card">
			<div class="card-header">Shift hari ini</div>
			<ul class="list-group list-group-flush small">
				<?php if (empty($shifts)): ?><li class="list-group-item text-muted">Tidak ada shift.</li><?php endif; ?>
				<?php foreach ($shifts as $s): ?>
					<li class="list-group-item d-flex gap-2">
						<a href="<?= site_url('sales/shifts/show/' . $s['id']) ?>">#<?= (int) $s['id'] ?></a> <?= e($s['user_name']) ?>
						<span class="text-muted"><?= e($st[$s['status']]) ?></span>
						<span class="ms-auto"><?= rupiah($s['sales']) ?><?= $s['variance'] !== NULL && (float) $s['variance'] != 0 ? ' <span class="text-danger">(' . rupiah($s['variance']) . ')</span>' : '' ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="card-body small text-muted">Total selisih kas shift: <?= rupiah($exp['shift_variance']) ?>. Setoran tunai seharusnya sama dengan uang fisik yang disetor dari laci.</div>
		</div>
	</div>
</div>
<script>
(function () {
	var f = document.getElementById('settle-form'), out = document.getElementById('s-var'); if (!f || f.querySelector('.s-in[disabled]')) return;
	var rp = function (n) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n); };
	f.addEventListener('input', function () {
		var sum = 0; f.querySelectorAll('.s-in').forEach(function (i) { sum += parseFloat(i.value) || 0; });
		var v = sum - parseFloat(out.getAttribute('data-expected'));
		out.textContent = 'selisih ' + rp(v); out.className = 'text-end ' + (Math.abs(v) < 1 ? 'text-success' : 'text-danger');
	});
})();
</script>
