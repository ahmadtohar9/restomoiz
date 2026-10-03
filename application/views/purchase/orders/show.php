<?php defined('BASEPATH') OR exit('No direct script access allowed');
$st = Purchase_service::$po_status[$po['status']];
$history_labels = array(
	'created' => 'PO dibuat', 'submitted' => 'Disubmit', 'auto_approved' => 'Disetujui otomatis', 'approved_1' => 'Disetujui (level 1)',
	'approved_2' => 'Disetujui Owner (level 2)', 'rejected' => 'Ditolak, kembali ke draft', 'cancelled' => 'Dibatalkan',
	'closed' => 'Ditutup', 'received' => 'Barang diterima', 'invoice' => 'Invoice dicatat',
);
$level_text = array(0 => 'otomatis', 1 => 'Manajer', 2 => 'Manajer + Owner');
?>
<p><a href="<?= site_url('purchase/orders') ?>"><i class="bi bi-arrow-left"></i> Daftar PO</a></p>

<div class="card mb-3">
	<div class="card-body">
		<div class="d-flex flex-wrap gap-2 align-items-start">
			<div>
				<h2 class="h5 mb-1"><?= e($po['po_number']) ?> <span class="badge text-bg-<?= $st[1] ?> <?= $st[1] === 'light' ? 'border' : '' ?> align-middle"><?= e($st[0]) ?></span></h2>
				<div class="text-muted small">Supplier <a href="<?= site_url('inventory/suppliers/show/' . $po['supplier_id']) ?>"><?= e($po['supplier_name']) ?></a> · dibuat <?= e($po['created_by_name']) ?></div>
			</div>
			<div class="ms-auto d-flex flex-wrap gap-1">
				<?php if ($actions['edit']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('purchase/orders/edit/' . $po['id']) ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
				<?php if ($actions['submit']): ?>
					<?= form_open('purchase/orders/action/' . $po['id'] . '/submit', array('class' => 'd-inline')) ?><button class="btn btn-sm btn-primary"><i class="bi bi-send"></i> Submit</button><?= form_close() ?>
				<?php endif; ?>
				<?php if ($actions['receive']): ?><a class="btn btn-sm btn-success" href="<?= site_url('purchase/receipts/create/' . $po['id']) ?>"><i class="bi bi-box-arrow-in-down"></i> Terima Barang</a><?php endif; ?>
				<?php if ($actions['invoice']): ?><a class="btn btn-sm btn-outline-primary" href="<?= site_url('purchase/invoices/create/' . $po['id']) ?>"><i class="bi bi-receipt"></i> Catat Invoice</a><?php endif; ?>
				<?php if ($actions['print']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('purchase/orders/print/' . $po['id']) ?>" target="_blank"><i class="bi bi-printer"></i> Cetak</a><?php endif; ?>
				<?php if ($actions['delete']): ?>
					<?= form_open('purchase/orders/delete/' . $po['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus PO draft ini?')) ?><button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button><?= form_close() ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="row small mt-3 g-2">
			<div class="col-6 col-md-3"><span class="text-muted d-block">Tanggal PO</span><?= tgl($po['po_date'], FALSE) ?></div>
			<div class="col-6 col-md-3"><span class="text-muted d-block">Tanggal kirim</span><?= $po['expected_delivery'] ? tgl($po['expected_delivery'], FALSE) : '-' ?></div>
			<div class="col-6 col-md-3"><span class="text-muted d-block">Termin</span><?= e($po['payment_terms']) ?></div>
			<div class="col-6 col-md-3"><span class="text-muted d-block">Approval</span><?= $po['submitted_at'] ? $level_text[(int) $po['approval_level']] : '-' ?></div>
			<?php if ($po['delivery_address']): ?><div class="col-12"><span class="text-muted">Alamat kirim:</span> <?= e($po['delivery_address']) ?></div><?php endif; ?>
			<?php if ($po['notes']): ?><div class="col-12"><span class="text-muted">Catatan:</span> <?= e($po['notes']) ?></div><?php endif; ?>
			<?php if ($po['close_reason']): ?><div class="col-12"><span class="text-muted"><?= $po['status'] === 'cancelled' ? 'Alasan batal' : 'Alasan tutup' ?>:</span> <?= e($po['close_reason']) ?></div><?php endif; ?>
		</div>
	</div>
</div>

<?php if ($actions['approve'] OR $actions['reject']): ?>
<div class="card mb-3 border-warning">
	<div class="card-body">
		<h3 class="h6"><i class="bi bi-hourglass-split text-warning"></i> Menunggu approval <?= $po['approved1_by'] ? 'Owner (level 2)' : 'Manajer (level 1)' ?></h3>
		<?php if ($po['approved1_by']): ?><p class="small text-muted mb-2">Level 1 disetujui <?= e($po['approved1_name']) ?> pada <?= tgl($po['approved1_at']) ?>.</p><?php endif; ?>
		<?= form_open('purchase/orders/action/' . $po['id'] . '/approve', array('class' => 'd-flex flex-wrap gap-2', 'id' => 'approval-form')) ?>
			<input class="form-control form-control-sm" style="max-width: 420px" name="note" maxlength="255" placeholder="Catatan (wajib jika menolak)">
			<?php if ($actions['approve']): ?><button class="btn btn-sm btn-success" data-confirm-click="Setujui PO <?= e($po['po_number']) ?> senilai <?= rupiah($po['total_amount']) ?>?"><i class="bi bi-check2"></i> Setujui</button><?php endif; ?>
			<?php if ($actions['reject']): ?>
				<button class="btn btn-sm btn-outline-danger" formaction="<?= site_url('purchase/orders/action/' . $po['id'] . '/reject') ?>" data-confirm-click="Tolak PO <?= e($po['po_number']) ?> dan kembalikan ke draft?"><i class="bi bi-x"></i> Tolak</button>
			<?php endif; ?>
		<?= form_close() ?>
	</div>
</div>
<?php endif; ?>

<div class="card mb-3">
	<div class="card-header">Item</div>
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead><tr><th>Bahan</th><th class="text-end">Dipesan</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th><th class="text-end">Diterima</th><th style="width: 140px"></th></tr></thead>
			<tbody>
			<?php foreach ($items as $it):
				$recv = (float) $it['qty_received_std'] / (float) $it['factor'];
				$pct = (float) $it['qty_std'] > 0 ? min(100, $it['qty_received_std'] / $it['qty_std'] * 100) : 0; ?>
				<tr>
					<td><a href="<?= site_url('inventory/ingredients/show/' . $it['ingredient_id']) ?>"><?= e($it['name']) ?></a>
						<?php if ($it['notes']): ?><div class="small text-muted"><?= e($it['notes']) ?></div><?php endif; ?></td>
					<td class="text-end text-nowrap"><?= qty($it['qty']) ?> <?= e($it['unit']) ?>
						<?php if ($it['unit'] !== $it['std_unit']): ?><div class="small text-muted">= <?= qty($it['qty_std']) ?> <?= e($it['std_unit']) ?></div><?php endif; ?></td>
					<td class="text-end small"><?= rupiah($it['unit_price']) ?></td>
					<td class="text-end"><?= rupiah($it['subtotal']) ?></td>
					<td class="text-end text-nowrap"><?= qty($recv) ?> <?= e($it['unit']) ?></td>
					<td><div class="progress" style="height: 6px"><div class="progress-bar <?= $pct >= 100 ? 'bg-success' : '' ?>" style="width: <?= $pct ?>%"></div></div></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot class="small">
				<tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end"><?= rupiah($po['subtotal']) ?></td><td colspan="2"></td></tr>
				<?php if ($po['discount_amount'] > 0): ?><tr><td colspan="3" class="text-end">Diskon</td><td class="text-end">−<?= rupiah($po['discount_amount']) ?></td><td colspan="2"></td></tr><?php endif; ?>
				<?php if ($po['tax_amount'] > 0): ?><tr><td colspan="3" class="text-end">Pajak</td><td class="text-end"><?= rupiah($po['tax_amount']) ?></td><td colspan="2"></td></tr><?php endif; ?>
				<tr class="fs-6"><th colspan="3" class="text-end">Total PO</th><th class="text-end"><?= rupiah($po['total_amount']) ?></th>
					<td colspan="2" class="text-end">
						<?php if ($received_amount > 0): ?>Diterima: <strong><?= rupiah($received_amount) ?></strong>
							<?php if ($variance_pct !== NULL && abs($variance_pct) > $variance_flag): ?>
								<span class="badge text-bg-danger" title="Selisih PO vs aktual melebihi <?= $variance_flag ?>%"><?= ($variance_pct > 0 ? '+' : '') . number_format($variance_pct, 1, ',', '.') ?>%</span>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			</tfoot>
		</table>
	</div>
</div>

<?php if ($receipts): ?>
<div class="card mb-3">
	<div class="card-header">Penerimaan barang</div>
	<?php foreach ($receipts as $gr): ?>
		<div class="card-body border-bottom" id="gr-<?= $gr['id'] ?>">
			<div class="d-flex flex-wrap gap-2 small mb-2">
				<strong><?= e($gr['gr_number']) ?></strong>
				<span><?= tgl($gr['received_date'], FALSE) ?></span>
				<span class="text-muted">oleh <?= e($gr['received_by_name']) ?></span>
				<?php if ($gr['delivery_note_no']): ?><span class="text-muted">· surat jalan <?= e($gr['delivery_note_no']) ?></span><?php endif; ?>
				<?php if ($gr['attachment_path']): ?><a href="<?= file_url($gr['attachment_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> lampiran</a><?php endif; ?>
				<a class="ms-auto" href="<?= site_url('inventory/stock/movements?' . http_build_query(array('q' => $gr['movement_no'], 'from' => $gr['received_date'], 'to' => date('Y-m-d')))) ?>">stok: <?= e($gr['movement_no']) ?></a>
				<strong><?= rupiah($gr['amount']) ?></strong>
			</div>
			<table class="table table-sm mb-0 small">
				<?php foreach ($gr['items'] as $r): $var = (float) $r['qty_received_std'] - (float) $r['qty_expected_std']; ?>
					<tr>
						<td><?= e($r['name']) ?></td>
						<td class="text-end"><?= qty($r['qty_received']) ?> <?= e($r['unit']) ?></td>
						<td class="text-end <?= abs($var) > 0.0005 ? 'text-warning-emphasis' : 'text-muted' ?>"><?= abs($var) > 0.0005 ? 'selisih ' . qty($var) : 'sesuai' ?></td>
						<td><?= e($r['variance_reason']) ?></td>
						<td class="text-muted"><?= $r['expiry_date'] ? 'exp ' . tgl($r['expiry_date'], FALSE) : '' ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($invoices): ?>
<div class="card mb-3">
	<div class="card-header">Invoice</div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead><tr><th>No. invoice</th><th>Tanggal</th><th>Jatuh tempo</th><th class="text-end">Nilai</th><th>Pencocokan</th><th>Status</th><th>Bayar</th></tr></thead>
			<tbody>
			<?php foreach ($invoices as $v): $is = Purchase_service::$invoice_status[$v['status']]; ?>
				<tr>
					<td><a href="<?= site_url('purchase/invoices/show/' . $v['id']) ?>"><?= e($v['invoice_number']) ?></a></td>
					<td class="small"><?= tgl($v['invoice_date'], FALSE) ?></td>
					<td class="small"><?= tgl($v['due_date'], FALSE) ?></td>
					<td class="text-end"><?= rupiah($v['amount']) ?></td>
					<td><?= $v['match_status'] === 'matched' ? '<span class="badge text-bg-success">cocok</span>' : '<span class="badge text-bg-danger">tidak cocok</span>' ?></td>
					<td><span class="badge text-bg-<?= $is[1] ?>"><?= e($is[0]) ?></span></td>
					<td class="small"><?= array('unpaid' => 'Belum', 'partial' => 'Sebagian', 'paid' => 'Lunas')[$v['payment_status']] ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php endif; ?>

<div class="row g-3">
	<div class="col-lg-7">
		<div class="card h-100">
			<div class="card-header">Riwayat</div>
			<ul class="list-group list-group-flush small">
				<?php foreach ($history as $h): ?>
					<li class="list-group-item d-flex gap-2">
						<span class="text-muted text-nowrap"><?= tgl($h['created_at']) ?></span>
						<span><strong><?= e(isset($history_labels[$h['action']]) ? $history_labels[$h['action']] : $h['action']) ?></strong>
							<?= $h['user_name'] ? '· ' . e($h['user_name']) : '' ?>
							<?php if ($h['note']): ?><div class="text-muted"><?= e($h['note']) ?></div><?php endif; ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<?php if ($actions['cancel'] OR $actions['close']): ?>
	<div class="col-lg-5">
		<div class="card h-100">
			<div class="card-header"><?= $actions['close'] ? 'Tutup PO' : 'Batalkan PO' ?></div>
			<div class="card-body">
				<p class="small text-muted"><?= $actions['close']
					? 'Tutup PO jika sisa barang tidak akan dikirim, atau untuk mengarsipkan PO yang sudah lengkap.'
					: 'Batalkan PO yang tidak jadi dipesan. PO yang sudah ada penerimaan barang tidak bisa dibatalkan.' ?></p>
				<?= form_open('purchase/orders/action/' . $po['id'] . '/' . ($actions['close'] ? 'close' : 'cancel'), array('data-confirm' => $actions['close'] ? 'Tutup PO ini?' : 'Batalkan PO ini?')) ?>
					<input class="form-control form-control-sm mb-2" name="note" maxlength="255" placeholder="Alasan" <?= $actions['close'] && $po['status'] === 'received' ? '' : 'required' ?>>
					<button class="btn btn-sm btn-outline-danger"><?= $actions['close'] ? 'Tutup PO' : 'Batalkan PO' ?></button>
				<?= form_close() ?>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>
