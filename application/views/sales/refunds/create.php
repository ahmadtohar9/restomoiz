<?php defined('BASEPATH') OR exit('No direct script access allowed');
$mins = (int) floor((time() - strtotime($order['paid_at'])) / 60);
?>
<p><a href="<?= site_url('sales/orders/show/' . $order['id']) ?>"><i class="bi bi-arrow-left"></i> Kembali ke <?= e($order['order_number']) ?></a></p>

<?= form_open('sales/refunds/create/' . $order['id'], array('id' => 'refund-form')) ?>
<div class="row g-3">
	<div class="col-lg-7">
		<div class="card">
			<div class="card-header d-flex">Pilih item <a href="#" class="ms-auto small" id="refund-all">refund semua</a></div>
			<div class="table-responsive">
				<table class="table align-middle mb-0">
					<thead><tr><th>Item</th><th class="text-center">Terjual</th><th class="text-center">Bisa direfund</th><th style="width: 120px">Refund</th></tr></thead>
					<tbody>
					<?php foreach ($items as $it): ?>
						<tr class="<?= (int) $it['refundable_qty'] === 0 ? 'text-muted' : '' ?>">
							<td><?= e($it['name']) ?><div class="small text-muted"><?= rupiah($it['unit_price']) ?><?= $it['discount'] > 0 ? ' · diskon ' . rupiah($it['discount']) : '' ?></div></td>
							<td class="text-center"><?= (int) $it['qty'] ?></td>
							<td class="text-center"><?= (int) $it['refundable_qty'] ?></td>
							<td><input class="form-control form-control-sm refund-qty" type="number" min="0" max="<?= (int) $it['refundable_qty'] ?>" name="qty[<?= $it['id'] ?>]"
								value="<?= isset($input['qtys'][$it['id']]) ? (int) $input['qtys'][$it['id']] : 0 ?>" <?= (int) $it['refundable_qty'] === 0 ? 'disabled' : '' ?>></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<div class="col-lg-5">
		<div class="card">
			<div class="card-body">
				<div class="small text-muted mb-2">Dibayar <?= tgl($order['paid_at']) ?> (<?= $mins ?> menit lalu) · <?= e(Promo_engine::$payment_methods[$order['payment_method']]) ?> · total <?= rupiah($order['total']) ?>
					<?= $order['refunded_amount'] > 0 ? '· sudah direfund ' . rupiah($order['refunded_amount']) : '' ?></div>
				<div class="border rounded p-2 mb-3 text-center">
					<div class="small text-muted">Nilai refund (termasuk PPN/service proporsional)</div>
					<div class="fs-3 fw-bold" id="refund-total">Rp 0</div>
					<div class="small" id="refund-level"></div>
				</div>
				<label class="form-label" for="reason_code">Alasan</label>
				<select class="form-select mb-2" id="reason_code" name="reason_code">
					<?php foreach (Refund_service::$reasons as $k => $v): ?><option value="<?= $k ?>" <?= $input['reason_code'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
				</select>
				<input class="form-control mb-3" name="reason" value="<?= e($input['reason']) ?>" required maxlength="255" placeholder="Keterangan, mis. ayam kurang matang">
				<label class="form-label">Pengembalian dana</label>
				<div class="mb-2">
					<div class="form-check"><input class="form-check-input" type="radio" name="method" value="cash" id="m-cash" <?= $input['method'] === 'cash' ? 'checked' : '' ?>><label class="form-check-label" for="m-cash">Tunai dari laci kasir</label></div>
					<?php if ($order['payment_method'] !== 'cash'): ?>
						<div class="form-check"><input class="form-check-input" type="radio" name="method" value="original" id="m-orig" <?= $input['method'] === 'original' ? 'checked' : '' ?>><label class="form-check-label" for="m-orig">Metode asal (void/reversal <?= e(Promo_engine::$payment_methods[$order['payment_method']]) ?>)</label></div>
						<input class="form-control form-control-sm mt-1" name="payment_ref" value="<?= e($input['payment_ref']) ?>" maxlength="100" placeholder="No. referensi reversal (wajib saat dikembalikan)">
					<?php endif; ?>
				</div>
				<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="restock" value="1" id="restock" <?= $input['restock'] ? 'checked' : '' ?>>
					<label class="form-check-label" for="restock">Kembalikan bahan ke stok (reverse COGS)</label>
					<div class="form-text">Hilangkan centang jika makanan sudah dimasak/tidak bisa dipakai lagi: bahan dicatat sebagai kerugian refund.</div></div>
				<button class="btn btn-danger w-100" type="submit">Ajukan refund</button>
				<p class="small text-muted mt-2 mb-0">Kasir langsung memproses jika &lt; <?= rupiah($limits['auto']) ?> dan ≤ <?= $limits['minutes'] ?> menit setelah bayar. Selebihnya butuh Manajer; di atas <?= rupiah($limits['owner']) ?> butuh Owner.</p>
			</div>
		</div>
	</div>
</div>
<?= form_close() ?>
<script>
(function () {
	var form = document.getElementById('refund-form');
	var rp = function (n) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n); };
	var timer;
	var preview = function () {
		clearTimeout(timer);
		timer = setTimeout(function () {
			var fd = new FormData(form); fd.append('preview', '1');
			fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
				.then(function (r) { return r.json(); }).then(function (r) {
					document.getElementById('refund-total').textContent = r.ok ? rp(r.total) : 'Rp 0';
					document.getElementById('refund-level').textContent = r.ok ? ('Diproses oleh: ' + r.level + (r.is_full ? ' · refund penuh' : '')) : r.message;
					document.getElementById('refund-level').className = 'small ' + (r.ok ? (r.level === 'Kasir' ? 'text-success' : 'text-warning-emphasis') : 'text-muted');
				});
		}, 250);
	};
	form.addEventListener('input', preview);
	document.getElementById('refund-all').addEventListener('click', function (e) {
		e.preventDefault();
		form.querySelectorAll('.refund-qty').forEach(function (i) { if (!i.disabled) i.value = i.max; });
		preview();
	});
	preview();
})();
</script>
