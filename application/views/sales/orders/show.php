<?php defined('BASEPATH') OR exit('No direct script access allowed');
$st = array('open' => array('Belum bayar', 'warning'), 'paid' => array('Lunas', 'success'), 'void' => array('Dibatalkan', 'secondary'));
$ks = array('pending' => 'secondary', 'preparing' => 'warning', 'ready' => 'info', 'served' => 'success', 'void' => 'dark');
$can_cogs = can('menu.view_cogs');
?>
<p><a href="<?= site_url('sales/orders') ?>"><i class="bi bi-arrow-left"></i> Transaksi</a></p>

<div class="row g-3 mb-3">
	<div class="col-lg-8">
		<div class="card h-100">
			<div class="card-body">
				<div class="d-flex flex-wrap gap-2 align-items-start">
					<div>
						<h2 class="h5 mb-1"><?= e($o['order_number']) ?> <span class="badge text-bg-<?= $st[$o['status']][1] ?> align-middle"><?= $st[$o['status']][0] ?></span></h2>
						<div class="small text-muted"><?= e(Pos_service::$order_types[$o['order_type']]) ?><?= $o['table_name'] ? ' · Meja ' . e($o['table_name']) : '' ?><?= $o['guest_count'] ? ' · ' . (int) $o['guest_count'] . ' tamu' : '' ?> · dibuat <?= e($o['created_by_name']) ?>, <?= tgl($o['created_at']) ?></div>
					</div>
					<div class="ms-auto d-flex flex-wrap gap-1">
						<?php if ($o['status'] === 'open'): ?>
							<a class="btn btn-sm btn-primary" href="<?= site_url('pos?order=' . $o['id']) ?>"><i class="bi bi-cash-coin"></i> Buka di POS</a>
						<?php endif; ?>
						<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('sales/orders/ticket/' . $o['id']) ?>" target="_blank"><i class="bi bi-fire"></i> Tiket dapur</a>
						<?php if ($o['status'] === 'paid'): ?><a class="btn btn-sm btn-outline-primary" href="<?= site_url('sales/orders/receipt/' . $o['id']) ?>" target="_blank"><i class="bi bi-printer"></i> Struk</a><?php endif; ?>
						<?php if ($o['status'] === 'paid' && $o['refund_status'] !== 'full' && can_any(array('sales.refund', 'sales.refund_approve', 'sales.refund_owner'))): ?>
							<a class="btn btn-sm btn-outline-danger" href="<?= site_url('sales/refunds/create/' . $o['id']) ?>"><i class="bi bi-arrow-counterclockwise"></i> Refund</a>
						<?php endif; ?>
						<?php if ($o['order_type'] === 'delivery' && ! $o['delivered_at'] && $o['status'] !== 'void' && can_any(array('sales.process', 'sales.order'))): ?>
							<?= form_open('sales/orders/deliver/' . $o['id'], array('class' => 'd-inline')) ?><button class="btn btn-sm btn-outline-success"><i class="bi bi-check2-all"></i> Sudah diantar</button><?= form_close() ?>
						<?php endif; ?>
					</div>
				</div>
				<?php if ($o['customer_name'] OR $o['customer_phone'] OR $o['delivery_address']): ?>
					<div class="small mt-2"><i class="bi bi-person"></i> <?= e($o['customer_name']) ?> <?= e($o['customer_phone']) ?><?= $o['is_member'] ? ' <span class="badge text-bg-warning">member</span>' : '' ?>
						<?php if ($o['delivery_address']): ?><div><i class="bi bi-geo-alt"></i> <?= e($o['delivery_address']) ?><?= $o['delivery_time'] ? ' · antar ' . tgl($o['delivery_time']) : '' ?><?= $o['delivered_at'] ? ' · <span class="text-success">diantar ' . tgl($o['delivered_at']) . '</span>' : '' ?></div><?php endif; ?></div>
				<?php endif; ?>
				<?php if ($o['notes']): ?><div class="small mt-1"><i class="bi bi-chat-left-text"></i> <?= e($o['notes']) ?></div><?php endif; ?>
				<?php if ($o['status'] === 'void'): ?><div class="alert alert-secondary small mt-2 mb-0">Dibatalkan <?= e($o['void_by_name']) ?>, <?= tgl($o['void_at']) ?>: <?= e($o['void_reason']) ?></div><?php endif; ?>
			</div>
		</div>
	</div>
	<div class="col-lg-4">
		<div class="card h-100">
			<div class="card-body">
				<?php $t = $o['status'] === 'open' && $quote ? array('subtotal' => $quote['subtotal'], 'discount' => $quote['discount'], 'service' => $quote['service_charge'], 'tax' => $quote['tax'], 'total' => $quote['total'])
					: array('subtotal' => $o['subtotal'], 'discount' => $o['discount_total'], 'service' => $o['service_charge'], 'tax' => $o['tax'], 'total' => $o['total']); ?>
				<table class="table table-sm mb-0">
					<tr><td>Subtotal</td><td class="text-end"><?= rupiah($t['subtotal']) ?></td></tr>
					<?php if ($o['status'] === 'paid'): foreach ($promos as $p): ?><tr class="text-success"><td><?= e($p['name']) ?></td><td class="text-end">−<?= rupiah($p['discount']) ?></td></tr><?php endforeach; ?>
					<?php elseif ($t['discount'] > 0): ?><tr class="text-success"><td>Promo (perkiraan)</td><td class="text-end">−<?= rupiah($t['discount']) ?></td></tr><?php endif; ?>
					<?php if ($t['service'] > 0): ?><tr><td>Service</td><td class="text-end"><?= rupiah($t['service']) ?></td></tr><?php endif; ?>
					<?php if ($t['tax'] > 0): ?><tr><td>PPN</td><td class="text-end"><?= rupiah($t['tax']) ?></td></tr><?php endif; ?>
					<tr class="fw-bold fs-5"><td>Total<?= $o['status'] === 'open' ? ' <span class="small fw-normal text-muted">(perkiraan)</span>' : '' ?></td><td class="text-end"><?= rupiah($t['total']) ?></td></tr>
					<?php if ($o['status'] === 'paid'): ?>
						<tr class="small"><td>Dibayar (<?= e(Promo_engine::$payment_methods[$o['payment_method']]) ?>)</td><td class="text-end"><?= rupiah($o['paid_amount']) ?></td></tr>
						<?php if ($o['change_amount'] > 0): ?><tr class="small"><td>Kembalian</td><td class="text-end"><?= rupiah($o['change_amount']) ?></td></tr><?php endif; ?>
						<?php if ($o['card_last4']): ?><tr class="small text-muted"><td colspan="2"><?= e($o['card_type']) ?> •••• <?= e($o['card_last4']) ?> · approval <?= e($o['approval_code']) ?></td></tr><?php endif; ?>
						<?php if ($o['payment_ref']): ?><tr class="small text-muted"><td colspan="2">Ref <?= e($o['payment_ref']) ?></td></tr><?php endif; ?>
						<tr class="small text-muted"><td colspan="2">Kasir <?= e($o['paid_by_name']) ?>, <?= tgl($o['paid_at']) ?><?= $o['shift_id'] ? ' · <a href="' . site_url('sales/shifts/show/' . $o['shift_id']) . '">shift #' . (int) $o['shift_id'] . '</a>' : '' ?></td></tr>
						<?php if ($can_cogs): ?><tr class="small text-muted"><td>COGS</td><td class="text-end"><?= rupiah($o['cogs_total']) ?></td></tr><?php endif; ?>
					<?php endif; ?>
				</table>
			</div>
		</div>
	</div>
</div>

<div class="card mb-3">
	<div class="card-header">Item</div>
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead><tr><th>Menu</th><th class="text-center">Qty</th><th class="text-end">Harga</th><th class="text-end">Jumlah</th><?php if ($o['status'] === 'paid'): ?><th class="text-end">Diskon</th><?php endif; ?><?php if ($can_cogs): ?><th class="text-end">COGS</th><?php endif; ?><th>Dapur</th><?php if ($o['status'] === 'open'): ?><th></th><?php endif; ?></tr></thead>
			<tbody>
			<?php foreach ($items as $it): $void = $it['kitchen_status'] === 'void'; ?>
				<tr class="<?= $void ? 'text-muted' : '' ?>">
					<td class="<?= $void ? 'text-decoration-line-through' : '' ?>"><?= e($it['name']) ?>
						<?php if ($it['modifiers']): ?><div class="small text-muted"><?= e(implode(', ', array_map(function ($m) { return '+ ' . $m['name']; }, $it['modifiers']))) ?></div><?php endif; ?>
						<?php if ($it['notes']): ?><div class="small fst-italic"><?= e($it['notes']) ?></div><?php endif; ?>
						<?php if ($it['stock_shortage']): ?><div class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> stok bahan di sistem kurang saat dijual</div><?php endif; ?>
						<?php if ($void): ?><div class="small">Batal: <?= e($it['void_reason']) ?> (<?= e($it['void_by_name']) ?>) · <?= $it['stock_returned'] ? 'stok dikembalikan' : 'bahan terbuang' ?></div><?php endif; ?></td>
					<td class="text-center"><?= (int) $it['qty'] ?></td>
					<td class="text-end small"><?= rupiah($it['unit_price']) ?></td>
					<td class="text-end"><?= rupiah($it['line_total']) ?></td>
					<?php if ($o['status'] === 'paid'): ?><td class="text-end small text-success"><?= $it['discount'] > 0 ? '−' . rupiah($it['discount']) : '' ?></td><?php endif; ?>
					<?php if ($can_cogs): ?><td class="text-end small"><?= rupiah($it['cogs']) ?></td><?php endif; ?>
					<td><span class="badge text-bg-<?= $ks[$it['kitchen_status']] ?>"><?= e(Pos_service::$kitchen_flow[$it['kitchen_status']]) ?></span></td>
					<?php if ($o['status'] === 'open'): ?>
						<td class="text-end">
							<?php if ( ! $void && (can('sales.edit_order') OR ($it['kitchen_status'] === 'pending' && can('sales.process')))): ?>
								<?= form_open('sales/orders/void_item/' . $it['id'], array('class' => 'd-flex gap-1 justify-content-end')) ?>
									<input class="form-control form-control-sm" style="max-width: 150px" name="reason" placeholder="alasan" required maxlength="255">
									<button class="btn btn-sm btn-outline-danger" title="Batalkan item" data-confirm-click="<?= $it['kitchen_status'] === 'pending' ? 'Batalkan item ini? Stok bahan dikembalikan.' : 'Item sudah dimasak. Batalkan? Bahan dicatat terbuang.' ?>"><i class="bi bi-x-lg"></i></button>
								<?= form_close() ?>
							<?php endif; ?>
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($refunds): ?>
<div class="card mb-3">
	<div class="card-header">Refund <?= $o['refund_status'] === 'full' ? '<span class="badge text-bg-dark">penuh</span>' : ($o['refund_status'] === 'partial' ? '<span class="badge text-bg-warning">sebagian</span>' : '') ?>
		<span class="small text-muted fw-normal ms-2">sudah dikembalikan <?= rupiah($o['refunded_amount']) ?></span></div>
	<ul class="list-group list-group-flush small">
		<?php foreach ($refunds as $rf): $rs = Refund_service::$status[$rf['status']]; ?>
			<li class="list-group-item d-flex gap-2"><a href="<?= site_url('sales/refunds/show/' . $rf['id']) ?>"><?= e($rf['refund_number']) ?></a>
				<span><?= e($rf['reason']) ?></span><span class="ms-auto"><?= rupiah($rf['refund_amount']) ?></span><span class="badge text-bg-<?= $rs[1] ?>"><?= e($rs[0]) ?></span></li>
		<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>

<?php if ($o['status'] === 'open' && can_any(array('sales.process', 'sales.edit_order'))): ?>
<div class="card border-danger-subtle">
	<div class="card-body">
		<h3 class="h6">Batalkan pesanan</h3>
		<p class="small text-muted mb-2">Item yang belum dimasak dikembalikan ke stok. Item yang sudah dimasak dicatat sebagai bahan terbuang (butuh akses manajer).</p>
		<?= form_open('sales/orders/void_order/' . $o['id'], array('class' => 'd-flex flex-wrap gap-2', 'data-confirm' => 'Batalkan seluruh pesanan ' . $o['order_number'] . '?')) ?>
			<input class="form-control form-control-sm" style="max-width: 360px" name="reason" required maxlength="255" placeholder="Alasan pembatalan">
			<button class="btn btn-sm btn-danger">Batalkan pesanan</button>
		<?= form_close() ?>
	</div>
</div>
<?php endif; ?>
