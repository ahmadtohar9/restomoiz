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
<?php $ini = strtoupper(implode('', array_map(function ($w) { return mb_substr($w, 0, 1); }, array_slice(preg_split('/\s+/', trim($current_user['name'])), 0, 2)))); ?>
<body class="pos-body">
<script>
(function () {
	// Tinggi layar yang terlihat (tanpa address bar / navigasi browser tablet).
	function fit() { document.documentElement.style.setProperty('--app-h', window.innerHeight + 'px'); document.body.classList.add('has-app-h'); }
	fit();
	window.addEventListener('resize', fit);
	window.addEventListener('orientationchange', function () { setTimeout(fit, 250); });
	if (window.visualViewport) window.visualViewport.addEventListener('resize', fit);
})();
</script>
<header class="pos-top">
	<a class="pos-brand" href="<?= site_url('dashboard') ?>" title="Kembali ke dashboard">
		<span class="brand-logo tw-h-9 tw-w-9 tw-rounded-xl tw-text-base"><i class="bi bi-shop"></i></span>
		<span class="d-none d-sm-block"><?= e($resto) ?><small>Point of Sale</small></span>
	</a>
	<div class="pos-top-mid">
		<div class="pos-open">
			<i class="bi bi-receipt-cutoff"></i>
			<select class="form-select" id="open-orders" aria-label="Pesanan terbuka"><option value="">Pesanan terbuka…</option></select>
		</div>
	</div>
	<div class="pos-top-end">
		<?php if ($can_pay): ?>
			<?php if ($shift): ?>
				<a class="pos-pill is-ok" href="<?= site_url('sales/shifts/show/' . $shift['id']) ?>" title="Shift aktif"><span class="dot"></span> <span class="d-none d-md-inline">Shift</span> <?= e($shift['shift_name'] ?: '#' . $shift['id']) ?></a>
			<?php else: ?>
				<button class="pos-pill is-warn" data-bs-toggle="modal" data-bs-target="#shiftModal"><i class="bi bi-unlock"></i> Buka shift</button>
			<?php endif; ?>
		<?php endif; ?>
		<nav class="pos-nav">
			<a href="<?= site_url('sales/orders') ?>" title="Transaksi"><i class="bi bi-receipt"></i><span>Transaksi</span></a>
			<a href="<?= site_url('sales/kitchen') ?>" title="Dapur"><i class="bi bi-fire"></i><span>Dapur</span></a>
			<a href="<?= site_url('sales/tables') ?>" title="Meja"><i class="bi bi-grid-3x3-gap"></i><span>Meja</span></a>
		</nav>
		<div class="pos-user d-none d-lg-flex">
			<span class="avatar avatar-sm"><?= e($ini ?: '?') ?></span>
			<span class="lh-sm"><b><?= e($current_user['name']) ?></b><small><?= date('d/m/Y') ?> · <span id="clock"><?= date('H:i') ?></span></small></span>
		</div>
	</div>
</header>

<div class="pos-main">
	<section class="pos-left">
		<div class="pos-search">
			<i class="bi bi-search"></i>
			<input class="form-control" id="search" placeholder="Cari menu atau scan barcode lalu Enter…" autocomplete="off" autofocus>
			<span class="pos-scan"><i class="bi bi-upc-scan"></i> Scan</span>
		</div>
		<div class="pos-cats" id="categories"></div>
		<div class="pos-grid" id="menu-grid"><div class="pos-empty"><span class="ui-spinner"></span><div>Memuat menu…</div></div></div>
	</section>

	<aside class="pos-cart" id="pos-cart">
		<div class="pos-cart-head">
			<div class="tw-min-w-0">
				<div class="d-flex align-items-center gap-2"><strong id="cart-title">Pesanan baru</strong><span class="pos-count" id="cart-count" hidden>0</span></div>
				<div class="small text-muted text-truncate" id="cart-sub"></div>
			</div>
			<button class="btn btn-sm btn-light ms-auto" id="btn-new" title="Mulai pesanan baru"><i class="bi bi-plus-lg"></i> Baru</button>
		</div>
		<div class="pos-order-opts">
			<div class="pos-seg" role="group" aria-label="Tipe pesanan" id="order-type">
				<input type="radio" class="btn-check" name="otype" id="ot-dine_in" value="dine_in"><label for="ot-dine_in"><i class="bi bi-cup-hot"></i> Dine-in</label>
				<input type="radio" class="btn-check" name="otype" id="ot-takeaway" value="takeaway" checked><label for="ot-takeaway"><i class="bi bi-bag"></i> Takeaway</label>
				<input type="radio" class="btn-check" name="otype" id="ot-delivery" value="delivery"><label for="ot-delivery"><i class="bi bi-scooter"></i> Delivery</label>
			</div>
			<div class="d-flex gap-2 mt-2">
				<select class="form-select form-select-sm" id="table-select" style="max-width: 46%" aria-label="Meja"></select>
				<div class="position-relative flex-fill">
					<i class="bi bi-person pos-in-icon"></i>
					<input class="form-control form-control-sm ps-4" id="customer-search" placeholder="Pelanggan (nama / HP)" autocomplete="off">
					<div class="list-group position-absolute w-100 shadow pos-dropdown" id="customer-results"></div>
				</div>
			</div>
			<div class="pos-delivery mt-2" id="delivery-fields" hidden>
				<input class="form-control form-control-sm" id="cust-name" placeholder="Nama penerima" maxlength="100">
				<input class="form-control form-control-sm" id="cust-phone" placeholder="No. HP" maxlength="30" inputmode="tel">
				<input class="form-control form-control-sm span-2" id="cust-address" placeholder="Alamat antar" maxlength="255">
				<input class="form-control form-control-sm span-2" type="datetime-local" id="delivery-time" title="Waktu antar (kosong = secepatnya)">
			</div>
		</div>
		<div class="pos-cart-items" id="cart-items"></div>
		<div class="pos-cart-foot">
			<button type="button" class="pos-extra-toggle" id="extra-toggle" aria-expanded="false"><i class="bi bi-ticket-perforated"></i> Kode promo &amp; catatan <i class="bi bi-chevron-down"></i></button>
			<div class="pos-extra">
				<div class="input-group input-group-sm">
					<span class="input-group-text"><i class="bi bi-ticket-perforated"></i></span>
					<input class="form-control text-uppercase" id="promo-code" placeholder="Kode promo">
					<button class="btn btn-outline-secondary" id="btn-code">Pakai</button>
				</div>
				<div class="input-group input-group-sm">
					<span class="input-group-text"><i class="bi bi-chat-left-text"></i></span>
					<input class="form-control" id="order-notes" placeholder="Catatan pesanan" maxlength="255">
				</div>
			</div>
			<div id="codes"></div>
			<div id="warnings" class="small text-danger"></div>
			<table class="pos-totals">
				<tr><td>Subtotal</td><td class="text-end" id="t-sub">Rp 0</td></tr>
				<tbody id="t-promos"></tbody>
				<tr id="row-service"><td>Service</td><td class="text-end" id="t-service">Rp 0</td></tr>
				<tr id="row-tax"><td>PPN</td><td class="text-end" id="t-tax">Rp 0</td></tr>
				<tr class="pos-grand"><td>Total</td><td class="text-end" id="t-total">Rp 0</td></tr>
			</table>
			<div class="pos-actions">
				<button class="btn btn-kitchen" id="btn-kitchen" disabled><i class="bi bi-send"></i><span><span class="k-pre">Kirim ke </span>Dapur</span></button>
				<?php if ($can_pay): ?><button class="btn btn-pay" id="btn-pay" disabled><i class="bi bi-wallet2"></i><span>Bayar</span> <b id="btn-pay-amt"></b></button><?php endif; ?>
			</div>
		</div>
	</aside>
</div>

<div class="pos-fab" id="pos-fab" hidden>
	<a href="#pos-cart" class="pos-fab-info" title="Lihat keranjang"><i class="bi bi-basket2"></i> <span id="fab-count">0 item</span><b id="fab-total">Rp 0</b><i class="bi bi-chevron-up"></i></a>
	<?php if ($can_pay): ?>
		<button type="button" class="pos-fab-act" id="fab-pay" disabled><i class="bi bi-wallet2"></i> Bayar</button>
	<?php else: ?>
		<button type="button" class="pos-fab-act is-kitchen" id="fab-kitchen" disabled><i class="bi bi-send"></i> Dapur</button>
	<?php endif; ?>
</div>

<!-- Pilih varian / tambahan / catatan -->
<div class="modal fade pos-modal" id="itemModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
	<div class="modal-header"><h5 class="modal-title" id="im-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
	<div class="modal-body">
		<div id="im-variants" class="im-opts mb-3"></div>
		<div class="d-flex align-items-center gap-2 mb-3">
			<label class="form-label mb-0">Jumlah</label>
			<div class="im-stepper">
				<button type="button" id="im-minus" aria-label="Kurangi"><i class="bi bi-dash-lg"></i></button>
				<input class="form-control text-center" type="number" min="1" max="999" id="im-qty" value="1" aria-label="Jumlah">
				<button type="button" id="im-plus" aria-label="Tambah"><i class="bi bi-plus-lg"></i></button>
			</div>
		</div>
		<div id="im-mods-wrap"><label class="form-label">Tambahan</label><div id="im-mods" class="im-chips mb-3"></div></div>
		<label class="form-label" for="im-notes">Catatan</label>
		<div class="im-chips im-chips-note mb-2" id="im-chips">
			<?php foreach (array('Level pedas 1', 'Level pedas 3', 'Level pedas 5', 'Tidak pedas', 'Tanpa gula', 'Less sugar', 'Tanpa es', 'Tanpa sayur', 'Dibungkus') as $chip): ?>
				<button type="button" class="im-chip"><?= e($chip) ?></button>
			<?php endforeach; ?>
		</div>
		<input class="form-control" id="im-notes" maxlength="255" placeholder="mis. saus dipisah">
	</div>
	<div class="modal-footer"><div class="me-auto lh-sm"><small class="text-muted d-block">Total</small><span class="fs-5 fw-bold" id="im-price"></span></div><button class="btn btn-primary btn-lg px-4" id="im-save">Tambah</button></div>
</div></div></div>

<!-- Pembayaran -->
<div class="modal fade pos-modal" id="payModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
	<div class="modal-header"><h5 class="modal-title">Pembayaran</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
	<div class="modal-body">
		<div class="pay-total-box"><div class="small">Total tagihan</div><div class="pay-total" id="pay-total">Rp 0</div><div class="small" id="pay-note"></div></div>
		<div class="pm-tiles mb-3" role="group" aria-label="Metode bayar" id="pay-methods"></div>
		<div data-method="cash">
			<label class="form-label" for="pay-cash">Uang diterima</label>
			<input class="form-control form-control-lg text-end" type="number" min="0" step="100" id="pay-cash" inputmode="numeric">
			<div class="cash-chips mt-2" id="cash-quick"></div>
			<div class="pay-change-box"><span>Kembalian</span><strong id="pay-change">Rp 0</strong></div>
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
	<div class="modal-footer"><button class="btn btn-pay btn-lg w-100" id="pay-confirm"><i class="bi bi-check2-circle"></i> Konfirmasi Pembayaran</button></div>
</div></div></div>

<!-- Berhasil -->
<div class="modal fade pos-modal" id="doneModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center">
	<div class="modal-body py-4">
		<div class="done-check"><i class="bi bi-check-lg"></i></div>
		<h5 class="mt-2" id="done-title"></h5>
		<div class="done-change" id="done-change"></div>
		<div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
			<a class="btn btn-outline-primary" id="done-receipt" target="_blank"><i class="bi bi-printer"></i> Struk</a>
			<a class="btn btn-outline-secondary" id="done-ticket" target="_blank"><i class="bi bi-fire"></i> Tiket dapur</a>
			<button class="btn btn-primary" id="done-new"><i class="bi bi-plus-lg"></i> Pesanan baru</button>
		</div>
	</div>
</div></div></div>

<!-- Buka shift -->
<?php if ($can_pay && ! $shift): ?>
<div class="modal fade pos-modal" id="shiftModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
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
<script src="<?= asset_v('assets/vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset_v('assets/js/ui.js') ?>"></script>
<script src="<?= asset_v('assets/js/pos.js') ?>"></script>
</body>
</html>
