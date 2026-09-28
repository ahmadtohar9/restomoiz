<?php defined('BASEPATH') OR exit('No direct script access allowed');
// Kelompok tampilan; kunci yang belum terdaftar otomatis masuk "Lainnya".
$groups = array(
	array('Profil resto & struk', 'shop', 'Tampil di header aplikasi dan struk pelanggan.', array('resto_name', 'resto_address', 'resto_phone', 'receipt_footer', 'receipt_paper')),
	array('Pajak & biaya layanan', 'percent', 'Dipakai saat menghitung total di POS.', array('tax_enabled', 'tax_rate', 'service_charge_rate')),
	array('Kasir, shift & refund', 'cash-coin', 'Batas yang boleh dilakukan kasir tanpa approval.', array('cash_variance_limit', 'refund_auto_limit', 'refund_auto_minutes', 'refund_owner_limit', 'pos_block_insufficient_stock', 'kitchen_refresh_seconds')),
	array('Menu', 'journal-richtext', 'Ketersediaan & peringatan margin menu.', array('menu_auto_oos', 'menu_margin_warning', 'public_menu_enabled')),
	array('Inventory', 'box-seam', 'Ambang peringatan stok.', array('dead_stock_days', 'slow_moving_days', 'expiry_alert_days')),
	array('Pembelian', 'cart3', 'Approval PO & pencocokan invoice.', array('po_auto_limit', 'po_owner_limit', 'po_variance_flag', 'invoice_match_tolerance')),
	array('Sistem', 'hdd-stack', 'Diisi otomatis oleh sistem.', array('last_backup_at')),
);
$by_key = array_column($settings, NULL, 'key');
$listed = array();
foreach ($groups as $g) { $listed = array_merge($listed, $g[3]); }
$other = array_values(array_diff(array_keys($by_key), $listed));
if ($other) { $groups[] = array('Lainnya', 'sliders', '', $other); }

// Satuan input berdasarkan nama kunci.
$unit = function ($key) {
	if (preg_match('/_(limit)$/', $key)) return array('Rp', 'prefix');
	if (preg_match('/_(rate|tolerance|flag|warning)$/', $key)) return array('%', 'suffix');
	if (preg_match('/_days$/', $key)) return array('hari', 'suffix');
	if (preg_match('/_minutes$/', $key)) return array('menit', 'suffix');
	if (preg_match('/_seconds$/', $key)) return array('detik', 'suffix');
	if ($key === 'receipt_paper') return array('mm', 'suffix');
	return NULL;
};
?>
<?= form_open('admin/settings') ?>
<p class="text-muted small mb-3">Perubahan berlaku langsung untuk semua user setelah disimpan dan tercatat di Audit Log.</p>
<div class="row g-3">
	<?php foreach ($groups as $g): $keys = array_values(array_filter($g[3], function ($k) use ($by_key) { return isset($by_key[$k]); })); if ( ! $keys) continue; ?>
	<div class="col-xl-6">
		<div class="card h-100">
			<div class="card-header d-flex align-items-center gap-2">
				<span class="stat-icon tw-h-8 tw-w-8 tw-rounded-lg tw-bg-indigo-50 tw-text-sm tw-text-indigo-600"><i class="bi bi-<?= $g[1] ?>"></i></span>
				<div class="lh-sm"><div><?= e($g[0]) ?></div><?php if ($g[2]): ?><div class="small text-muted fw-normal"><?= e($g[2]) ?></div><?php endif; ?></div>
			</div>
			<div class="card-body">
				<?php foreach ($keys as $i => $key): $s = $by_key[$key]; $id = 's_' . $key; ?>
					<div class="<?= $i ? 'pt-3 mt-3 border-top' : '' ?>">
						<?php if (in_array($key, $boolean, TRUE)): ?>
							<div class="d-flex align-items-start gap-3">
								<label class="flex-fill mb-0" for="<?= e($id) ?>">
									<span class="d-block fw-medium tw-text-[.9rem]"><?= e(preg_replace('/\s*\((?:0|1) = [^)]*\)/', '', $s['description'])) ?></span>
									<code class="small text-muted"><?= e($key) ?></code>
								</label>
								<div class="form-check form-switch m-0">
									<input class="form-check-input" type="checkbox" role="switch" id="<?= e($id) ?>" name="settings[<?= e($key) ?>]" value="1" <?= $s['value'] === '1' ? 'checked' : '' ?>>
								</div>
							</div>
						<?php else: $u = $unit($key); ?>
							<label class="form-label d-block" for="<?= e($id) ?>"><?= e($s['description']) ?> <code class="small text-muted fw-normal ms-1"><?= e($key) ?></code></label>
							<?php if ($u): ?><div class="input-group"><?php if ($u[1] === 'prefix'): ?><span class="input-group-text"><?= e($u[0]) ?></span><?php endif; ?><?php endif; ?>
							<input class="form-control" id="<?= e($id) ?>" name="settings[<?= e($key) ?>]" value="<?= e($s['value']) ?>"<?= $key === 'last_backup_at' ? ' readonly' : '' ?><?= $u && $u[1] === 'prefix' ? ' inputmode="numeric"' : '' ?>>
							<?php if ($u): ?><?php if ($u[1] === 'suffix'): ?><span class="input-group-text"><?= e($u[0]) ?></span><?php endif; ?></div><?php endif; ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
</div>
<div class="form-actions">
	<span class="small text-muted d-none d-sm-inline"><i class="bi bi-info-circle"></i> Periksa kembali sebelum menyimpan.</span>
	<button class="btn btn-primary ms-auto" type="submit"><i class="bi bi-check2"></i> Simpan Pengaturan</button>
</div>
<?= form_close() ?>
