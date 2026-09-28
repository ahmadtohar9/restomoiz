<?php defined('BASEPATH') OR exit('No direct script access allowed');
$st = array('open' => array('Belum bayar', 'warning'), 'paid' => array('Lunas', 'success'), 'void' => array('Batal', 'secondary'));
?>
<form class="card mb-3" method="get" action="<?= site_url('sales/orders') ?>">
	<div class="card-body row g-2 align-items-end">
		<div class="col-6 col-md-2"><label class="form-label small" for="from">Dari</label><input class="form-control form-control-sm" type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
		<div class="col-6 col-md-2"><label class="form-label small" for="to">Sampai</label><input class="form-control form-control-sm" type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
		<div class="col-6 col-md-2"><label class="form-label small" for="status">Status</label>
			<select class="form-select form-select-sm" id="status" name="status"><option value="">Semua</option>
				<?php foreach ($st as $k => $v): ?><option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $v[0] ?></option><?php endforeach; ?></select></div>
		<div class="col-6 col-md-2"><label class="form-label small" for="type">Tipe</label>
			<select class="form-select form-select-sm" id="type" name="type"><option value="">Semua</option>
				<?php foreach (Pos_service::$order_types as $k => $v): ?><option value="<?= $k ?>" <?= $filters['type'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
		<div class="col-6 col-md-2"><label class="form-label small" for="method">Bayar</label>
			<select class="form-select form-select-sm" id="method" name="method"><option value="">Semua</option>
				<?php foreach (Promo_engine::$payment_methods as $k => $v): ?><option value="<?= $k ?>" <?= $filters['method'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
		<div class="col-6 col-md-2 d-flex gap-1"><input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="No./nama/HP"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i></button></div>
	</div>
</form>

<div class="d-flex flex-wrap gap-3 mb-3 align-items-center">
	<span class="small text-muted"><?= count($rows) ?> pesanan · <?= (int) $sum['paid'] ?> lunas · total <strong><?= rupiah($sum['total']) ?></strong></span>
	<a class="btn btn-sm btn-outline-warning ms-auto" href="<?= site_url('sales/orders?status=open') ?>"><i class="bi bi-hourglass-split"></i> Belum bayar</a>
	<a class="btn btn-sm btn-primary" href="<?= site_url('pos') ?>"><i class="bi bi-cash-coin"></i> Buka POS</a>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>No.</th><th>Waktu</th><th>Tipe</th><th>Pelanggan / meja</th><th class="text-center">Item</th><th class="text-end">Total</th><th>Bayar</th><th>Status</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">Tidak ada transaksi.</td></tr><?php endif; ?>
			<?php foreach ($rows as $o): ?>
				<tr>
					<td><a class="fw-medium" href="<?= site_url('sales/orders/show/' . $o['id']) ?>"><?= e($o['order_number']) ?></a><div class="small text-muted"><?= e($o['created_by_name']) ?></div></td>
					<td class="small text-nowrap"><?= tgl($o['created_at']) ?></td>
					<td class="small"><?= e(Pos_service::$order_types[$o['order_type']]) ?></td>
					<td class="small"><?= $o['table_name'] ? 'Meja ' . e($o['table_name']) : '' ?><?= $o['customer_name'] ? ($o['table_name'] ? ' · ' : '') . e($o['customer_name']) : '' ?></td>
					<td class="text-center"><?= (int) $o['item_count'] ?></td>
					<td class="text-end"><?= rupiah($o['total']) ?></td>
					<td class="small"><?= $o['payment_method'] ? e(Promo_engine::$payment_methods[$o['payment_method']]) : '-' ?></td>
					<td><span class="badge text-bg-<?= $st[$o['status']][1] ?>"><?= $st[$o['status']][0] ?></span>
						<?php if ($o['refund_status'] !== 'none'): ?><span class="badge text-bg-dark"><?= $o['refund_status'] === 'full' ? 'refund penuh' : 'refund sebagian' ?></span><?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
