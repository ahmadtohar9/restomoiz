<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === NULL || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
$done = $row && $row['status'] === 'reconciled';
?>
<p><a href="<?= site_url('finance/reconciliations') ?>"><i class="bi bi-arrow-left"></i> Rekonsiliasi bank</a></p>
<div class="row g-3">
	<div class="col-lg-5">
		<div class="card mb-3">
			<div class="card-header">Menurut POS (<?= e($period) ?>)</div>
			<table class="table table-sm mb-0">
				<tr><td>Penjualan tunai</td><td class="text-end"><?= rupiah($exp['cash']) ?></td></tr>
				<tr><td>Penjualan kartu</td><td class="text-end"><?= rupiah($exp['debit'] + $exp['credit']) ?></td></tr>
				<tr><td>Penjualan e-wallet</td><td class="text-end"><?= rupiah($exp['ewallet']) ?></td></tr>
				<tr><td>Refund</td><td class="text-end">−<?= rupiah($exp['refund_cash'] + $exp['refund_card'] + $exp['refund_ewallet']) ?></td></tr>
				<tr class="fw-bold"><td>Seharusnya masuk rekening</td><td class="text-end"><?= rupiah($exp['expected_total']) ?></td></tr>
			</table>
			<div class="card-body small text-muted">Settlement harian: <?= (int) $daily['verified'] ?> dari <?= (int) $daily['days'] ?> hari terverifikasi, total selisih harian <?= rupiah($daily['variance']) ?>.
				<a href="<?= site_url('finance/settlements?period=' . $period) ?>">lihat</a></div>
		</div>
	</div>
	<div class="col-lg-7">
		<?= form_open_multipart('finance/reconciliations/period/' . $period, array('class' => 'card', 'id' => 'recon-form')) ?>
			<div class="card-header">Rekening koran <?php if ($done): ?><span class="badge text-bg-success ms-2">Selesai</span><?php endif; ?></div>
			<div class="card-body row g-3">
				<div class="col-md-6"><label class="form-label small" for="bank_name">Bank / rekening</label><input class="form-control" id="bank_name" name="bank_name" value="<?= e($row['bank_name'] ?? '') ?>" maxlength="50" <?= $done ? 'disabled' : '' ?>></div>
				<div class="col-md-6"><label class="form-label small" for="bank_credits">Total mutasi masuk (kredit)</label><input class="form-control text-end r-in" type="number" step="any" min="0" id="bank_credits" name="bank_credits" value="<?= e($num($row['bank_credits'] ?? NULL)) ?>" <?= $done ? 'disabled' : '' ?>></div>
				<div class="col-md-4"><label class="form-label small" for="in_transit">Dana dalam perjalanan</label><input class="form-control text-end r-in" type="number" step="any" min="0" id="in_transit" name="in_transit" value="<?= e($num($row['in_transit'] ?? 0)) ?>" <?= $done ? 'disabled' : '' ?>><div class="form-text">Settlement akhir bulan yang masuk bulan depan.</div></div>
				<div class="col-md-4"><label class="form-label small" for="fees">Biaya bank / MDR</label><input class="form-control text-end r-in" type="number" step="any" min="0" id="fees" name="fees" value="<?= e($num($row['fees'] ?? 0)) ?>" <?= $done ? 'disabled' : '' ?>></div>
				<div class="col-md-4"><label class="form-label small" for="other_adjustment">Penyesuaian lain (+/−)</label><input class="form-control text-end r-in" type="number" step="any" id="other_adjustment" name="other_adjustment" value="<?= e($num($row['other_adjustment'] ?? 0)) ?>" <?= $done ? 'disabled' : '' ?>></div>
				<div class="col-12 fs-5">Selisih: <strong id="r-var" data-expected="<?= e($exp['expected_total']) ?>"><?= isset($row['variance']) && $row['variance'] !== NULL ? rupiah($row['variance']) : '-' ?></strong></div>
				<div class="col-12"><label class="form-label small" for="notes">Catatan investigasi</label><textarea class="form-control" id="notes" name="notes" rows="3" <?= $done ? 'disabled' : '' ?>><?= e($row['notes'] ?? '') ?></textarea></div>
				<div class="col-12"><label class="form-label small" for="attachment">Rekening koran (PDF/gambar)</label>
					<?php if ( ! empty($row['attachment_path'])): ?><div class="mb-1"><a href="<?= file_url($row['attachment_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> lihat file tersimpan</a></div><?php endif; ?>
					<?php if ( ! $done): ?><input class="form-control" type="file" id="attachment" name="attachment" accept="application/pdf,image/jpeg,image/png,image/webp"><?php endif; ?></div>
			</div>
			<?php if ( ! $done): ?>
				<div class="card-footer bg-transparent d-flex gap-2">
					<button class="btn btn-outline-primary" name="action" value="save">Simpan draft</button>
					<button class="btn btn-success" name="action" value="reconcile" data-confirm-click="Tutup rekonsiliasi <?= e($period) ?>? Data dikunci.">Selesaikan rekonsiliasi</button>
				</div>
			<?php else: ?>
				<div class="card-footer bg-transparent small text-muted">Diselesaikan <?= tgl($row['reconciled_at']) ?>.</div>
			<?php endif; ?>
		<?= form_close() ?>
	</div>
</div>
<script>
(function () {
	var f = document.getElementById('recon-form'), out = document.getElementById('r-var'); if (!f) return;
	var rp = function (n) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n); };
	f.addEventListener('input', function () {
		var sum = 0; f.querySelectorAll('.r-in').forEach(function (i) { sum += parseFloat(i.value) || 0; });
		var v = sum - parseFloat(out.getAttribute('data-expected'));
		out.textContent = rp(v); out.className = Math.abs(v) < 1 ? 'text-success' : 'text-danger';
	});
})();
</script>
