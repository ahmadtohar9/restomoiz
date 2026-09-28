<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Tabel pembayaran (dipakai di daftar pembayaran & detail invoice). $rows, $back = list|invoice */
$uid = (int) $this->session->userdata('user_id');
$super = $this->rbac->is_super();
?>
<div class="table-responsive">
	<table class="table table-sm align-middle mb-0">
		<thead><tr><th>No.</th><th>Tanggal</th><?php if ($back === 'list'): ?><th>Supplier / invoice</th><?php endif; ?><th>Metode</th><th class="text-end">Jumlah</th><th>Bukti</th><th>Status</th><th></th></tr></thead>
		<tbody>
		<?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-3">Belum ada pembayaran.</td></tr><?php endif; ?>
		<?php foreach ($rows as $p): $ps = Purchase_service::$payment_status[$p['status']]; ?>
			<tr>
				<td class="small"><?= e($p['payment_number']) ?><div class="text-muted">oleh <?= e($p['created_by_name']) ?></div></td>
				<td class="small"><?= tgl($p['payment_date'], FALSE) ?></td>
				<?php if ($back === 'list'): ?>
					<td class="small"><?= e($p['supplier_name']) ?><div><a href="<?= site_url('purchase/invoices/show/' . $p['invoice_id']) ?>"><?= e($p['invoice_number']) ?></a></div></td>
				<?php endif; ?>
				<td class="small"><?= e(Purchase_service::$methods[$p['method']]) ?>
					<div class="text-muted">
						<?php if ($p['method'] === 'transfer'): ?><?= e($p['bank_name']) ?> <?= e($p['account_no']) ?> · ref <?= e($p['reference_no']) ?>
						<?php elseif ($p['method'] === 'credit_card'): ?>•••• <?= e($p['card_last4']) ?> · auth <?= e($p['auth_code']) ?>
						<?php elseif ($p['method'] === 'check'): ?>No. <?= e($p['check_no']) ?> · <?= tgl($p['check_date'], FALSE) ?><?php endif; ?>
					</div></td>
				<td class="text-end"><?= rupiah($p['amount']) ?></td>
				<td><?php if ($p['proof_path']): ?><a href="<?= file_url($p['proof_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> lihat</a><?php else: ?>-<?php endif; ?></td>
				<td><span class="badge text-bg-<?= $ps[1] ?>"><?= e($ps[0]) ?></span>
					<?php if ($p['verified_by_name']): ?><div class="small text-muted"><?= e($p['verified_by_name']) ?></div><?php endif; ?>
					<?php if ($p['reject_reason']): ?><div class="small text-danger"><?= e($p['reject_reason']) ?></div><?php endif; ?></td>
				<td class="text-end">
					<?php if ($p['status'] === 'pending' && can('purchase.payment')): ?>
						<?php if ($super OR (int) $p['created_by'] !== $uid): ?>
							<?= form_open('purchase/payments/process/' . $p['id'], array('class' => 'd-flex gap-1 justify-content-end')) ?>
								<input type="hidden" name="back" value="<?= e($back) ?>">
								<input class="form-control form-control-sm" style="max-width: 150px" name="reason" placeholder="alasan tolak">
								<button class="btn btn-sm btn-success" name="action" value="verify" data-confirm-click="Verifikasi pembayaran <?= e($p['payment_number']) ?>? Pastikan dana sudah keluar sesuai bukti."><i class="bi bi-check2"></i></button>
								<button class="btn btn-sm btn-outline-danger" name="action" value="reject"><i class="bi bi-x"></i></button>
							<?= form_close() ?>
						<?php else: ?>
							<span class="small text-muted">diverifikasi orang lain</span>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
