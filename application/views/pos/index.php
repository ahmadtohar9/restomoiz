<?php defined('BASEPATH') OR exit('No direct script access allowed');
$CI =& get_instance();
?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>POS · <?= e($resto) ?></title>
	<link rel="stylesheet" href="<?= base_url('assets/vendor/inter/inter.css') ?>">
	<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
	<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
	<link rel="stylesheet" href="<?= asset_v('assets/css/app.css') ?>">
	<link rel="stylesheet" href="<?= asset_v('assets/css/pos.css') ?>">
</head>
<body class="pos-body">
<header class="pos-top">
	<a class="pos-brand" href="<?= site_url('dashboard') ?>" title="Kembali ke dashboard"><span class="brand-logo tw-h-8 tw-w-8 tw-rounded-lg tw-text-base"><i class="bi bi-shop"></i></span> <span><?= e($resto) ?><small class="tw-block tw-text-[.65rem] tw-font-medium tw-uppercase tw-tracking-[.14em] tw-text-slate-400">Point of Sale</small></span></a>
	<div class="d-flex gap-2 align-items-center ms-auto flex-wrap">
		<?php if ($can_pay): ?>
			<?php if ($shift): ?>
				<a class="badge text-bg-success text-decoration-none" href="<?= site_url('sales/shifts/show/' . $shift['id']) ?>"><i class="bi bi-clock"></i> Shift <?= e($shift['shift_name'] ?: '#' . $shift['id']) ?> · <?= e($shift['register_name']) ?></a>
			<?php else: ?>
				<button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#shiftModal"><i class="bi bi-unlock"></i> Buka shift</button>
			<?php endif; ?>
		<?php endif; ?>
		<a class="btn btn-sm btn-outline-light" href="<?= site_url('sales/orders') ?>"><i class="bi bi-receipt"></i> <span class="d-none d-md-inline">Transaksi</span></a>
		<a class="btn btn-sm btn-outline-light" href="<?= site_url('sales/kitchen') ?>"><i class="bi bi-fire"></i> <span class="d-none d-md-inline">Dapur</span></a>
		<a class="btn btn-sm btn-outline-light" href="<?= site_url('sales/tables') ?>"><i class="bi bi-grid-3x3-gap"></i> <span class="d-none d-md-inline">Meja</span></a>
		<span class="text-white-50 small d-none d-lg-inline"><i class="bi bi-person"></i> <?= e($current_user['name']) ?> · <?= date('d/m/Y') ?> <span id="clock"></span></span>
	</div>
</header>

<div class="pos-main">
	<section class="pos-left">
		<div class="pos-controls">
			<div class="btn-group" role="group" aria-label="Tipe pesanan" id="order-type">
				<input type="radio" class="btn-check" name="otype" id="ot-dine_in" value="dine_in"><label class="btn btn-outline-primary btn-sm" for="ot-dine_in"><i class="bi bi-cup-hot"></i> Dine-in</label>
				<input type="radio" class="btn-check" name="otype" id="ot-takeaway" value="takeaway" checked><label class="btn btn-outline-primary btn-sm" for="ot-takeaway"><i class="bi bi-bag"></i> Takeaway</label>
				<input type="radio" class="btn-check" name="otype" id="ot-delivery" value="delivery"><label class="btn btn-outline-primary btn-sm" for="ot-delivery"><i class="bi bi-scooter"></i> Delivery</label>
			</div>
			<select class="form-select form-select-sm" id="table-select" style="max-width: 170px" aria-label="Meja"></select>
			<div class="position-relative flex-fill" style="min-width: 180px; max-width: 280px">
				<input class="form-control form-control-sm" id="customer-search" placeholder="Pelanggan (nama / HP)" autocomplete="off">
				<div class="list-group position-absolute w-100 shadow-sm pos-dropdown" id="customer-results"></div>
			</div>
			<select class="form-select form-select-sm" id="open-orders" style="max-width: 220px" aria-label="Pesanan terbuka"><option value="">Pesanan terbuka…</option></select>
		</div>
		<div class="pos-controls" id="delivery-fields" hidden>
			<input class="form-control form-control-sm" id="cust-name" placeholder="Nama pelanggan" maxlength="100">
			<input class="form-control form-control-sm" id="cust-phone" placeholder="No. HP" maxlength="30">
			<input class="form-control form-control-sm flex-fill" id="cust-address" placeholder="Alamat antar" maxlength="255">
			<input class="form-control form-control-sm" type="datetime-local" id="delivery-time" title="Waktu antar (kosong = secepatnya)" style="max-width: 200px">
		</div>
		<div class="pos-search">
			<i class="bi bi-upc-scan"></i>
			<input class="form-control" id="search" placeholder="Cari menu atau scan barcode lalu Enter…" autocomplete="off" autofocus>
		</div>
		<div class="pos-cats" id="categories"></div>
		<div class="pos-grid" id="menu-grid"><div class="text-muted p-4">Memuat menu…</div></div>
	</section>

	<aside class="pos-cart">
		<div class="pos-cart-head">
			<div>
				<strong id="cart-title">Pesanan baru</strong>
				<div class="small text-muted" id="cart-sub"></div>
			</div>
			<button class="btn btn-sm btn-outline-secondary ms-auto" id="btn-new" title="Mulai pesanan baru"><i class="bi bi-plus-lg"></i> Baru</button>
		</div>
		<div class="pos-cart-items" id="cart-items"></div>
		<div class="pos-cart-foot">
			<div class="input-group input-group-sm mb-2">
				<span class="input-group-text"><i class="bi bi-ticket-perforated"></i></span>
				<input class="form-control text-uppercase" id="promo-code" placeholder="Kode promo">
				<button class="btn btn-outline-secondary" id="btn-code">Pakai</button>
			</div>
			<div id="codes" class="mb-1"></div>
			<div id="warnings" class="small text-danger mb-1"></div>
			<table class="table table-sm mb-2 pos-totals">
				<tr><td>Subtotal</td><td class="text-end" id="t-sub">Rp 0</td></tr>
				<tbody id="t-promos"></tbody>
				<tr id="row-service"><td>Service</td><td class="text-end" id="t-service">Rp 0</td></tr>
				<tr id="row-tax"><td>PPN</td><td class="text-end" id="t-tax">Rp 0</td></tr>
				<tr class="pos-grand"><td>Total</td><td class="text-end" id="t-total">Rp 0</td></tr>
			</table>
			<input class="form-control form-control-sm mb-2" id="order-notes" placeholder="Catatan pesanan (opsional)" maxlength="255">
			<div class="d-grid gap-2 d-flex">
				<button class="btn btn-outline-primary flex-fill" id="btn-kitchen" disabled><i class="bi bi-send"></i> Kirim ke Dapur</button>
				<?php if ($can_pay): ?><button class="btn btn-success flex-fill btn-lg" id="btn-pay" disabled><i class="bi bi-cash-coin"></i> Bayar</button><?php endif; ?>
			</div>
		</div>
	</aside>
</div>

<!-- Pilih varian / tambahan / catatan -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
	<div class="modal-header"><h5 class="modal-title" id="im-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
	<div class="modal-body">
		<div id="im-variants" class="d-flex flex-wrap gap-2 mb-3"></div>
		<div class="d-flex align-items-center gap-2 mb-3">
			<label class="form-label mb-0">Jumlah</label>
			<div class="input-group" style="max-width: 150px">
				<button class="btn btn-outline-secondary" type="button" id="im-minus">−</button>
				<input class="form-control text-center" type="number" min="1" max="999" id="im-qty" value="1">
				<button class="btn btn-outline-secondary" type="button" id="im-plus">+</button>
			</div>
		</div>
		<div id="im-mods-wrap"><label class="form-label">Tambahan</label><div id="im-mods" class="d-flex flex-wrap gap-2 mb-3"></div></div>
		<label class="form-label" for="im-notes">Catatan</label>
		<div class="d-flex flex-wrap gap-1 mb-2" id="im-chips">
			<?php foreach (array('Level pedas 1', 'Level pedas 3', 'Level pedas 5', 'Tidak pedas', 'Tanpa gula', 'Less sugar', 'Tanpa es', 'Tanpa sayur', 'Dibungkus') as $chip): ?>
				<button type="button" class="btn btn-sm btn-outline-secondary"><?= e($chip) ?></button>
			<?php endforeach; ?>
		</div>
		<input class="form-control" id="im-notes" maxlength="255" placeholder="mis. saus dipisah">
	</div>
	<div class="modal-footer"><span class="me-auto fw-bold" id="im-price"></span><button class="btn btn-primary" id="im-save">Tambah</button></div>
</div></div></div>

<!-- Pembayaran -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
	<div class="modal-header"><h5 class="modal-title">Pembayaran</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
	<div class="modal-body">
		<div class="text-center mb-3"><div class="text-muted small">Total</div><div class="display-6 fw-bold" id="pay-total">Rp 0</div><div class="small text-muted" id="pay-note"></div></div>
		<div class="btn-group w-100 mb-3" role="group" id="pay-methods"></div>
		<div data-method="cash">
			<label class="form-label" for="pay-cash">Uang diterima</label>
			<input class="form-control form-control-lg text-end" type="number" min="0" step="100" id="pay-cash" inputmode="numeric">
			<div class="d-flex flex-wrap gap-1 mt-2" id="cash-quick"></div>
			<div class="d-flex justify-content-between fs-5 mt-3"><span>Kembalian</span><strong id="pay-change">Rp 0</strong></div>
		</div>
		<div data-method="debit credit" hidden>
			<div class="row g-2">
				<div class="col-5"><label class="form-label small" for="card-type">Jenis kartu</label><select class="form-select" id="card-type"></select></div>
				<div class="col-3"><label class="form-label small" for="card-last4">4 digit akhir</label><input class="form-control" id="card-last4" maxlength="4" inputmode="numeric" autocomplete="off"></div>
				<div class="col-4"><label class="form-label small" for="approval-code">Kode approval</label><input class="form-control" id="approval-code" maxlength="30" autocomplete="off"></div>
			</div>
			<div class="form-text">Proses kartu di mesin EDC dulu. Nomor kartu lengkap & CVV tidak disimpan.</div>
		</div>
		<div data-method="ewallet" hidden>
			<label class="form-label" for="payment-ref">No. referensi transaksi</label>
			<input class="form-control" id="payment-ref" maxlength="100" autocomplete="off">
		</div>
		<div class="alert alert-danger mt-3 mb-0 py-2" id="pay-error" hidden></div>
	</div>
	<div class="modal-footer"><button class="btn btn-success btn-lg w-100" id="pay-confirm"><i class="bi bi-check2-circle"></i> Konfirmasi Pembayaran</button></div>
</div></div></div>

<!-- Berhasil -->
<div class="modal fade" id="doneModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center">
	<div class="modal-body py-4">
		<i class="bi bi-check-circle-fill text-success display-4"></i>
		<h5 class="mt-2" id="done-title"></h5>
		<div class="fs-4 fw-bold" id="done-change"></div>
		<div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
			<a class="btn btn-outline-primary" id="done-receipt" target="_blank"><i class="bi bi-printer"></i> Struk</a>
			<a class="btn btn-outline-secondary" id="done-ticket" target="_blank"><i class="bi bi-fire"></i> Tiket dapur</a>
			<button class="btn btn-primary" id="done-new"><i class="bi bi-plus-lg"></i> Pesanan baru</button>
		</div>
	</div>
</div></div></div>

<!-- Buka shift -->
<?php if ($can_pay && ! $shift): ?>
<div class="modal fade" id="shiftModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
	<?= form_open('sales/shifts/open') ?>
	<input type="hidden" name="back" value="pos">
	<div class="modal-header"><h5 class="modal-title">Buka shift kasir</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
	<div class="modal-body">
		<label class="form-label" for="opening_balance">Modal awal (uang tunai di laci)</label>
		<input class="form-control form-control-lg mb-3" type="number" min="0" step="100" id="opening_balance" name="opening_balance" required>
		<div class="row g-2">
			<div class="col-6"><label class="form-label small" for="shift_name">Shift</label>
				<select class="form-select" id="shift_name" name="shift_name">
					<?php foreach ($CI->db->order_by('start_time')->get('shift_templates')->result_array() as $t): ?>
						<option><?= e($t['name']) ?></option>
					<?php endforeach; ?>
				</select></div>
			<div class="col-6"><label class="form-label small" for="register_name">Kasir / mesin</label><input class="form-control" id="register_name" name="register_name" value="Kasir 1" maxlength="50"></div>
		</div>
	</div>
	<div class="modal-footer"><button class="btn btn-primary w-100">Buka shift</button></div>
	<?= form_close() ?>
</div></div></div>
<?php endif; ?>

<div class="toast-container position-fixed bottom-0 start-0 p-3"><div class="toast align-items-center border-0" id="toast" role="status"><div class="d-flex"><div class="toast-body" id="toast-body"></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button></div></div></div>

<script>
window.POS = {
	urls: {
		catalog: <?= json_encode(site_url('pos/catalog')) ?>,
		quote: <?= json_encode(site_url('pos/quote')) ?>,
		submit: <?= json_encode(site_url('pos/submit')) ?>,
		order: <?= json_encode(site_url('pos/order')) ?>,
		customers: <?= json_encode(site_url('pos/customers')) ?>,
		orderPage: <?= json_encode(site_url('sales/orders/show')) ?>
	},
	csrf: { name: <?= json_encode($CI->security->get_csrf_token_name()) ?>, value: <?= json_encode($CI->security->get_csrf_hash()) ?> },
	canPay: <?= $can_pay ? 'true' : 'false' ?>,
	hasShift: <?= $shift ? 'true' : 'false' ?>,
	flash: <?= json_encode($CI->session->flashdata('flash')) ?>,
	orderId: <?= (int) $order_id ?>
};
</script>
<script src="<?= base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset_v('assets/js/pos.js') ?>"></script>
</body>
</html>
