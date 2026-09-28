<?php defined('BASEPATH') OR exit('No direct script access allowed');
$st = array('open' => array('Berjalan', 'success'), 'pending_approval' => array('Menunggu approval manajer', 'warning'), 'closed' => array('Ditutup', 'secondary'));
$m = function ($k) use ($s) { return isset($s['by_method'][$k]) ? (float) $s['by_method'][$k]['total'] : 0; };
?>
<p><a href="<?= site_url('sales/shifts') ?>"><i class="bi bi-arrow-left"></i> Shift kasir</a></p>
<div class="row g-3">
	<div class="col-lg-7">
		<div class="card">
			<div class="card-header d-flex align-items-center">Ringkasan shift <span class="badge text-bg-<?= $st[$s['status']][1] ?> ms-2"><?= $st[$s['status']][0] ?></span>
				<button class="btn btn-sm btn-outline-secondary ms-auto" onclick="window.print()"><i class="bi bi-printer"></i></button></div>
			<div class="card-body">
				<p class="small text-muted"><?= e($s['shift_name']) ?> · <?= e($s['register_name']) ?> · kasir <strong><?= e($s['user_name']) ?></strong><br>
					<?= tgl($s['opened_at']) ?> – <?= $s['closed_at'] ? tgl($s['closed_at']) : 'sekarang' ?></p>
				<table class="table table-sm">
					<tr><td>Modal awal</td><td class="text-end"><?= rupiah($s['opening_balance']) ?></td></tr>
					<tr><td>Penjualan tunai</td><td class="text-end"><?= rupiah($m('cash')) ?></td></tr>
					<tr><td>Penjualan kartu debit</td><td class="text-end"><?= rupiah($m('debit')) ?></td></tr>
					<tr><td>Penjualan kartu kredit</td><td class="text-end"><?= rupiah($m('credit')) ?></td></tr>
					<tr><td>Penjualan e-wallet / QRIS</td><td class="text-end"><?= rupiah($m('ewallet')) ?></td></tr>
					<tr><td>Refund tunai</td><td class="text-end">−<?= rupiah($s['cash_refunds']) ?></td></tr>
					<tr class="fw-semibold"><td>Kas seharusnya di laci</td><td class="text-end"><?= rupiah($s['status'] === 'open' ? $s['expected_now'] : $s['expected_cash']) ?></td></tr>
					<?php if ($s['status'] !== 'open'): ?>
						<tr><td>Kas dihitung</td><td class="text-end"><?= rupiah($s['closing_balance']) ?></td></tr>
						<tr class="fw-semibold <?= (float) $s['variance'] != 0 ? 'text-danger' : 'text-success' ?>"><td>Selisih</td><td class="text-end"><?= rupiah($s['variance']) ?>
							<?= $s['expected_cash'] > 0 ? ' (' . number_format($s['variance'] / $s['expected_cash'] * 100, 2, ',', '.') . '%)' : '' ?></td></tr>
					<?php endif; ?>
				</table>
				<div class="row g-2 small">
					<div class="col-6 col-md-3"><div class="text-muted">Transaksi</div><strong><?= (int) $s['transactions'] ?></strong></div>
					<div class="col-6 col-md-3"><div class="text-muted">Total penjualan</div><strong><?= rupiah($s['sales_total']) ?></strong></div>
					<div class="col-6 col-md-3"><div class="text-muted">Rata-rata bill</div><strong><?= rupiah($s['avg_bill']) ?></strong></div>
					<div class="col-6 col-md-3"><div class="text-muted">Promo dipakai</div><strong><?= (int) $s['promo_usage'] ?> × (<?= rupiah($s['discount_total']) ?>)</strong></div>
				</div>
				<?php if ($s['top_items']): ?>
					<h6 class="mt-3">Menu terlaris</h6>
					<ol class="small mb-0"><?php foreach ($s['top_items'] as $t): ?><li><?= e($t['name']) ?> — <?= (int) $t['qty'] ?> porsi</li><?php endforeach; ?></ol>
				<?php endif; ?>
				<?php if ($s['close_note']): ?><p class="small mt-3 mb-0"><strong>Catatan kasir:</strong> <?= e($s['close_note']) ?></p><?php endif; ?>
				<?php if ($s['approved_by']): ?><p class="small mb-0"><strong>Disetujui</strong> <?= e($s['approved_by_name']) ?>, <?= tgl($s['approved_at']) ?>: <?= e($s['approval_note']) ?></p><?php endif; ?>
			</div>
		</div>
	</div>
	<div class="col-lg-5">
		<?php if ($can_close): ?>
		<div class="card border-primary mb-3">
			<div class="card-header">Tutup shift</div>
			<div class="card-body">
				<?php if ($open_orders): ?><div class="alert alert-warning small py-2"><?= $open_orders ?> pesanan buatan kasir ini belum dibayar. Pastikan sudah diselesaikan atau dioper.</div><?php endif; ?>
				<?= form_open('sales/shifts/close/' . $s['id'], array('data-confirm' => 'Tutup shift sekarang? Pastikan uang di laci sudah dihitung.')) ?>
					<label class="form-label" for="closing_balance">Uang tunai di laci saat ini</label>
					<input class="form-control form-control-lg mb-2" type="number" min="0" step="100" id="closing_balance" name="closing_balance" required data-expected="<?= e($s['expected_now']) ?>" data-limit="<?= e($limit) ?>">
					<div class="small mb-2" id="variance-preview"></div>
					<label class="form-label small" for="note">Catatan <span class="text-muted">(wajib jika selisih &gt; <?= rupiah($limit) ?>)</span></label>
					<input class="form-control mb-3" id="note" name="note" maxlength="255">
					<button class="btn btn-primary w-100">Tutup shift</button>
				<?= form_close() ?>
			</div>
		</div>
		<?php endif; ?>
		<?php if ($can_approve): ?>
		<div class="card border-warning">
			<div class="card-header">Approval selisih kas</div>
			<div class="card-body">
				<p class="small">Selisih <strong><?= rupiah($s['variance']) ?></strong> melebihi batas <?= rupiah($limit) ?>. Setujui setelah investigasi (cek log POS & transaksi).</p>
				<?= form_open('sales/shifts/approve/' . $s['id']) ?>
					<input class="form-control mb-2" name="note" required maxlength="255" placeholder="Hasil investigasi / keputusan">
					<button class="btn btn-warning w-100">Setujui & tutup shift</button>
				<?= form_close() ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>
<script>
(function () {
	var i = document.getElementById('closing_balance'); if (!i) return;
	var exp = parseFloat(i.getAttribute('data-expected')), lim = parseFloat(i.getAttribute('data-limit'));
	var rp = function (n) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n); };
	i.addEventListener('input', function () {
		if (i.value === '') { document.getElementById('variance-preview').textContent = ''; return; }
		var v = parseFloat(i.value) - exp;
		var el = document.getElementById('variance-preview');
		el.textContent = 'Seharusnya ' + rp(exp) + ' · selisih ' + rp(v) + (Math.abs(v) > lim ? ' (butuh approval manajer)' : '');
		el.className = 'small mb-2 ' + (v === 0 ? 'text-success' : (Math.abs(v) > lim ? 'text-danger' : 'text-warning-emphasis'));
	});
})();
</script>
