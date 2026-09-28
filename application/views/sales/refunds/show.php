<?php defined('BASEPATH') OR exit('No direct script access allowed');
$s = Refund_service::$status[$r['status']];
?>
<p><a href="<?= site_url('sales/refunds') ?>"><i class="bi bi-arrow-left"></i> Daftar refund</a></p>
<div class="row g-3">
	<div class="col-lg-7">
		<div class="card mb-3">
			<div class="card-body">
				<div class="d-flex flex-wrap gap-2 align-items-start">
					<div>
						<h2 class="h5 mb-1"><?= e($r['refund_number']) ?> <span class="badge text-bg-<?= $s[1] ?> align-middle"><?= e($s[0]) ?></span></h2>
						<div class="small text-muted">Transaksi <a href="<?= site_url('sales/orders/show/' . $r['order_id']) ?>"><?= e($r['order_number']) ?></a> · <?= $r['is_full'] ? 'refund penuh' : 'refund sebagian' ?></div>
					</div>
					<?php if ($r['status'] === 'completed'): ?><a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= site_url('sales/refunds/receipt/' . $r['id']) ?>" target="_blank"><i class="bi bi-printer"></i> Bukti refund</a><?php endif; ?>
				</div>
				<dl class="row small mb-0 mt-3">
					<dt class="col-sm-4">Alasan</dt><dd class="col-sm-8"><?= e(Refund_service::$reasons[$r['reason_code']]) ?> — <?= e($r['reason']) ?></dd>
					<dt class="col-sm-4">Pengembalian</dt><dd class="col-sm-8"><?= $r['refund_method'] === 'cash' ? 'Tunai' : 'Metode asal (' . e(Promo_engine::$payment_methods[$r['payment_method']]) . ')' ?><?= $r['payment_ref'] ? ' · ref ' . e($r['payment_ref']) : '' ?></dd>
					<dt class="col-sm-4">Stok bahan</dt><dd class="col-sm-8"><?= $r['restock'] ? 'Dikembalikan ke stok' : 'Dicatat sebagai kerugian' ?><?= $r['status'] === 'completed' && $r['restock'] ? ' (' . rupiah($r['cogs_reversed']) . ')' : '' ?></dd>
					<dt class="col-sm-4">Diajukan</dt><dd class="col-sm-8"><?= e($r['requested_by_name']) ?>, <?= tgl($r['requested_at']) ?></dd>
					<dt class="col-sm-4">Butuh approval</dt><dd class="col-sm-8"><?= e(Refund_service::$levels[$r['required_level']]) ?></dd>
					<?php if ($r['approved_by']): ?><dt class="col-sm-4"><?= $r['status'] === 'rejected' ? 'Ditolak' : 'Disetujui' ?></dt><dd class="col-sm-8"><?= e($r['approved_by_name']) ?>, <?= tgl($r['approved_at']) ?><?= $r['approval_note'] ? ' — ' . e($r['approval_note']) : '' ?></dd><?php endif; ?>
					<?php if ($r['completed_by']): ?><dt class="col-sm-4">Uang dikembalikan</dt><dd class="col-sm-8"><?= e($r['completed_by_name']) ?>, <?= tgl($r['completed_at']) ?><?= $r['shift_id'] ? ' · <a href="' . site_url('sales/shifts/show/' . $r['shift_id']) . '">shift #' . (int) $r['shift_id'] . '</a>' : '' ?></dd><?php endif; ?>
				</dl>
			</div>
		</div>
		<div class="card">
			<table class="table mb-0">
				<thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Nilai</th></tr></thead>
				<?php foreach ($items as $it): ?><tr><td><?= e($it['name']) ?></td><td class="text-center"><?= (int) $it['qty'] ?> / <?= (int) $it['sold_qty'] ?></td><td class="text-end"><?= rupiah($it['amount']) ?></td></tr><?php endforeach; ?>
				<?php if ($r['service_amount'] > 0): ?><tr class="small"><td colspan="2">Service</td><td class="text-end"><?= rupiah($r['service_amount']) ?></td></tr><?php endif; ?>
				<?php if ($r['tax_amount'] > 0): ?><tr class="small"><td colspan="2">PPN</td><td class="text-end"><?= rupiah($r['tax_amount']) ?></td></tr><?php endif; ?>
				<tr class="fw-bold fs-5"><td colspan="2">Total refund</td><td class="text-end"><?= rupiah($r['refund_amount']) ?></td></tr>
			</table>
		</div>
	</div>
	<div class="col-lg-5">
		<?php if ($can_approve OR $can_reject): ?>
		<div class="card border-warning mb-3">
			<div class="card-header">Approval</div>
			<div class="card-body">
				<?= form_open('sales/refunds/action/' . $r['id'] . '/approve', array('class' => 'd-flex flex-column gap-2')) ?>
					<input class="form-control" name="note" maxlength="255" placeholder="Catatan (wajib jika menolak)">
					<div class="d-flex gap-2">
						<?php if ($can_approve): ?><button class="btn btn-success flex-fill">Setujui</button><?php endif; ?>
						<?php if ($can_reject): ?><button class="btn btn-outline-danger flex-fill" formaction="<?= site_url('sales/refunds/action/' . $r['id'] . '/reject') ?>">Tolak</button><?php endif; ?>
					</div>
				<?= form_close() ?>
			</div>
		</div>
		<?php elseif ($r['status'] === 'pending_approval'): ?>
			<div class="alert alert-warning">Menunggu approval <?= e(Refund_service::$levels[$r['required_level']]) ?>. Tahan uang pelanggan sampai disetujui.</div>
		<?php endif; ?>
		<?php if ($can_complete): ?>
		<div class="card border-success">
			<div class="card-header">Kembalikan dana</div>
			<div class="card-body">
				<?= form_open('sales/refunds/action/' . $r['id'] . '/complete', array('data-confirm' => 'Konfirmasi: ' . rupiah($r['refund_amount']) . ' sudah dikembalikan ke pelanggan?')) ?>
					<?php if ($r['refund_method'] === 'cash'): ?>
						<p class="small">Keluarkan <strong><?= rupiah($r['refund_amount']) ?></strong> dari laci. Tercatat di shift kasir Anda yang sedang buka.</p>
					<?php else: ?>
						<input class="form-control mb-2" name="payment_ref" maxlength="100" required placeholder="No. referensi void / reversal">
					<?php endif; ?>
					<button class="btn btn-success w-100">Dana sudah dikembalikan</button>
				<?= form_close() ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>
