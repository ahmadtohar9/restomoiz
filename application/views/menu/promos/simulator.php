<?php defined('BASEPATH') OR exit('No direct script access allowed');
$status = array('applied' => array('Dipakai', 'success'), 'not_best' => array('Tidak dipilih', 'secondary'), 'rejected' => array('Tidak berlaku', 'light'));
$at_date = substr($ctx['at'], 0, 10);
$at_time = substr($ctx['at'], 11, 5);
?>
<p class="text-muted small">Uji promo dengan keranjang contoh sebelum dipakai kasir. Hasil di sini memakai perhitungan yang sama dengan POS.</p>
<form method="get" action="<?= site_url('menu/promos/simulator') ?>">
<div class="row g-3">
	<div class="col-lg-6">
		<div class="card h-100">
			<div class="card-header d-flex align-items-center">Keranjang
				<input type="search" class="form-control form-control-sm ms-auto table-filter" data-target="#sim-items" placeholder="Cari menu..." style="max-width: 200px"></div>
			<div class="table-responsive" style="max-height: 420px">
				<table class="table table-sm align-middle mb-0" id="sim-items">
					<tbody>
					<?php if (empty($variants)): ?><tr><td class="text-muted text-center py-3">Belum ada menu aktif.</td></tr><?php endif; ?>
					<?php foreach ($variants as $id => $label): ?>
						<tr class="<?= isset($qtys[$id]) ? 'table-primary' : '' ?>">
							<td><?= e($label) ?></td>
							<td style="width: 90px"><input class="form-control form-control-sm" type="number" min="0" max="999" name="qty[<?= $id ?>]" value="<?= isset($qtys[$id]) ? $qtys[$id] : '' ?>" placeholder="0"></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="card mb-3">
			<div class="card-header">Kondisi</div>
			<div class="card-body row g-2">
				<div class="col-6"><label class="form-label small" for="date">Tanggal</label><input class="form-control form-control-sm" type="date" id="date" name="date" value="<?= e($at_date) ?>"></div>
				<div class="col-6"><label class="form-label small" for="time">Jam</label><input class="form-control form-control-sm" type="time" id="time" name="time" value="<?= e($at_time) ?>"></div>
				<div class="col-6">
					<label class="form-label small" for="payment">Metode bayar</label>
					<select class="form-select form-select-sm" id="payment" name="payment">
						<option value="">— belum dipilih —</option>
						<?php foreach (Promo_engine::$payment_methods as $k => $v): ?><option value="<?= $k ?>" <?= $ctx['payment_method'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
					</select>
				</div>
				<div class="col-6"><label class="form-label small" for="codes">Kode promo</label><input class="form-control form-control-sm text-uppercase" id="codes" name="codes" value="<?= e(implode(',', $ctx['codes'])) ?>" placeholder="pisahkan dengan koma"></div>
				<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="member" name="member" value="1" <?= $ctx['is_member'] ? 'checked' : '' ?>><label class="form-check-label small" for="member">Pelanggan member (harga member & promo member)</label></div></div>
				<div class="col-12"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-calculator"></i> Hitung</button></div>
			</div>
		</div>

		<?php if ($result): ?>
		<div class="card mb-3">
			<div class="card-header">Hasil</div>
			<table class="table table-sm mb-0">
				<?php foreach ($result['lines'] as $idx => $l): ?>
					<tr><td><?= e($l['name']) ?> × <?= (int) $l['qty'] ?></td><td class="text-end"><?= rupiah($l['amount']) ?>
						<?php if ($result['line_discounts'][$idx] > 0): ?><div class="small text-success">−<?= rupiah($result['line_discounts'][$idx]) ?></div><?php endif; ?></td></tr>
				<?php endforeach; ?>
				<tr><td class="text-end">Subtotal</td><td class="text-end"><?= rupiah($result['subtotal']) ?></td></tr>
				<?php foreach ($result['applied'] as $a): ?>
					<tr class="text-success"><td class="text-end"><?= e($a['promo']['name']) ?></td><td class="text-end">−<?= rupiah($a['discount']) ?></td></tr>
				<?php endforeach; ?>
				<tr class="fw-bold"><td class="text-end">Total setelah promo</td><td class="text-end"><?= rupiah($result['total']) ?></td></tr>
			</table>
			<div class="card-body small text-muted py-2">Pajak & service charge dihitung di POS.</div>
		</div>
		<div class="card">
			<div class="card-header">Promo yang diperiksa</div>
			<ul class="list-group list-group-flush small">
				<?php if (empty($result['considered'])): ?><li class="list-group-item text-muted">Tidak ada promo aktif di tanggal ini.</li><?php endif; ?>
				<?php foreach ($result['considered'] as $c): $s = $status[$c['status']]; ?>
					<li class="list-group-item d-flex gap-2">
						<span class="badge text-bg-<?= $s[1] ?> <?= $s[1] === 'light' ? 'border' : '' ?> align-self-start"><?= $s[0] ?></span>
						<span><strong><?= e($c['promo']['name']) ?></strong><?= $c['discount'] > 0 ? ' · ' . rupiah($c['discount']) : '' ?>
							<?php if ($c['reason']): ?><div class="text-muted"><?= e($c['reason']) ?></div><?php endif; ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>
	</div>
</div>
</form>
