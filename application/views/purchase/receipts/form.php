<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.'); };
?>
<p><a href="<?= site_url('purchase/orders/show/' . $po['id']) ?>"><i class="bi bi-arrow-left"></i> Kembali ke <?= e($po['po_number']) ?></a></p>

<?= form_open_multipart('purchase/receipts/create/' . $po['id']) ?>
<div class="card mb-3">
	<div class="card-body row g-3">
		<div class="col-md-3">
			<label class="form-label" for="received_date">Tanggal terima</label>
			<input class="form-control" type="date" id="received_date" name="received_date" value="<?= e($header['received_date']) ?>" max="<?= date('Y-m-d') ?>" required>
		</div>
		<div class="col-md-3">
			<label class="form-label" for="delivery_note_no">No. surat jalan</label>
			<input class="form-control" id="delivery_note_no" name="delivery_note_no" value="<?= e($header['delivery_note_no']) ?>" maxlength="50">
		</div>
		<div class="col-md-6">
			<label class="form-label" for="attachment">Foto / scan surat jalan <span class="text-muted small">(opsional)</span></label>
			<input class="form-control" type="file" id="attachment" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf">
		</div>
		<div class="col-12">
			<label class="form-label" for="notes">Catatan</label>
			<input class="form-control" id="notes" name="notes" value="<?= e($header['notes']) ?>" maxlength="255">
		</div>
		<div class="col-12 small text-muted">Supplier: <strong><?= e($po['supplier_name']) ?></strong>. Stok langsung bertambah saat disimpan, dengan harga sesuai PO<?= $po['discount_amount'] > 0 ? ' (setelah diskon)' : '' ?>.</div>
	</div>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead><tr><th>Bahan</th><th class="text-end">Dipesan</th><th class="text-end">Sudah diterima</th><th style="width: 140px">Diterima sekarang</th><th>Alasan selisih</th><th style="width: 160px">Kedaluwarsa</th></tr></thead>
			<tbody>
			<?php foreach ($items as $it):
				$remaining = max(0, ((float) $it['qty_std'] - (float) $it['qty_received_std']) / (float) $it['factor']);
				$v = isset($input[$it['id']]) ? $input[$it['id']] : array('qty' => $num($remaining), 'variance_reason' => '', 'expiry_date' => '');
				$done = $remaining <= 0.0005; ?>
				<tr class="<?= $done ? 'text-muted' : '' ?> gr-line" data-remaining="<?= e($num($remaining)) ?>">
					<td><?= e($it['name']) ?></td>
					<td class="text-end text-nowrap"><?= qty($it['qty']) ?> <?= e($it['unit']) ?></td>
					<td class="text-end text-nowrap"><?= qty((float) $it['qty_received_std'] / (float) $it['factor']) ?> <?= e($it['unit']) ?></td>
					<td>
						<?php if ($done): ?>
							<span class="small">lengkap</span>
						<?php else: ?>
							<div class="input-group input-group-sm">
								<input class="form-control gr-qty" type="number" step="any" min="0" max="<?= e($num($remaining)) ?>" name="lines[<?= $it['id'] ?>][qty]" value="<?= e($v['qty']) ?>">
								<span class="input-group-text"><?= e($it['unit']) ?></span>
							</div>
						<?php endif; ?>
					</td>
					<td><?php if ( ! $done): ?><input class="form-control form-control-sm gr-reason" name="lines[<?= $it['id'] ?>][variance_reason]" value="<?= e($v['variance_reason']) ?>" maxlength="255" placeholder="wajib jika jumlah beda dari sisa"><?php endif; ?></td>
					<td><?php if ( ! $done): ?><input class="form-control form-control-sm" type="date" name="lines[<?= $it['id'] ?>][expiry_date]" value="<?= e($v['expiry_date']) ?>"><?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="mt-3 d-flex gap-2">
	<button class="btn btn-success" type="submit"><i class="bi bi-box-arrow-in-down"></i> Simpan Penerimaan</button>
	<a class="btn btn-outline-secondary" href="<?= site_url('purchase/orders/show/' . $po['id']) ?>">Batal</a>
</div>
<?= form_close() ?>
