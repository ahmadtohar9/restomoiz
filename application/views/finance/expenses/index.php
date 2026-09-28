<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
$total = array_sum($by_cat);
?>
<form class="d-flex flex-wrap gap-2 mb-3" method="get">
	<input class="form-control" type="month" name="period" value="<?= e($period) ?>" max="<?= date('Y-m') ?>" style="max-width: 200px">
	<button class="btn btn-outline-secondary">Tampilkan</button>
	<a class="btn btn-outline-secondary ms-auto" href="<?= site_url('finance/expenses?period=' . $period . '&export=1') ?>"><i class="bi bi-download"></i> CSV</a>
	<?php if (can('report.financial')): ?><a class="btn btn-outline-primary" href="<?= site_url('reports/pnl?period=' . $period) ?>"><i class="bi bi-file-earmark-bar-graph"></i> Laba rugi</a><?php endif; ?>
</form>
<p class="small text-muted">Catat beban operasional di sini (gaji, sewa, listrik, marketing, dll.). Pembelian bahan baku <strong>tidak</strong> dicatat di sini karena sudah masuk lewat Pembelian dan menjadi COGS saat terpakai.</p>

<div class="row g-3">
	<?php if (can('finance.expense')): ?>
	<div class="col-lg-4">
		<?= form_open_multipart('finance/expenses/save', array('class' => 'card')) ?>
			<div class="card-header"><?= $edit ? 'Ubah pengeluaran' : 'Catat pengeluaran' ?></div>
			<div class="card-body">
				<input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
				<label class="form-label small" for="expense_date">Tanggal</label>
				<input class="form-control mb-2" type="date" id="expense_date" name="expense_date" value="<?= e($edit ? $edit['expense_date'] : date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
				<label class="form-label small" for="category">Kategori</label>
				<select class="form-select mb-2" id="category" name="category">
					<?php foreach (Report_model::$expense_categories as $k => $v): ?><option value="<?= $k ?>" <?= $edit && $edit['category'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
				</select>
				<label class="form-label small" for="description">Keterangan</label>
				<input class="form-control mb-2" id="description" name="description" value="<?= e($edit ? $edit['description'] : '') ?>" maxlength="255" required placeholder="mis. Listrik PLN September">
				<label class="form-label small" for="amount">Jumlah</label>
				<div class="input-group mb-2"><span class="input-group-text">Rp</span><input class="form-control" type="number" min="0" step="any" id="amount" name="amount" value="<?= e($edit ? $num($edit['amount']) : '') ?>" required></div>
				<label class="form-label small" for="payment_method">Dibayar dengan</label>
				<input class="form-control mb-2" id="payment_method" name="payment_method" value="<?= e($edit ? $edit['payment_method'] : '') ?>" maxlength="20" placeholder="transfer / tunai / ...">
				<label class="form-label small" for="attachment">Bukti (opsional)</label>
				<input class="form-control mb-3" type="file" id="attachment" name="attachment" accept="application/pdf,image/jpeg,image/png,image/webp">
				<button class="btn btn-primary w-100"><?= $edit ? 'Simpan perubahan' : 'Simpan' ?></button>
				<?php if ($edit): ?><a class="btn btn-link w-100" href="<?= site_url('finance/expenses?period=' . $period) ?>">Batal</a><?php endif; ?>
			</div>
		<?= form_close() ?>
	</div>
	<?php endif; ?>
	<div class="col-lg-<?= can('finance.expense') ? 8 : 12 ?>">
		<div class="card mb-3">
			<div class="card-header d-flex">Ringkasan <?= e($period) ?><strong class="ms-auto"><?= rupiah($total) ?></strong></div>
			<ul class="list-group list-group-flush small">
				<?php if ( ! $by_cat): ?><li class="list-group-item text-muted">Belum ada pengeluaran bulan ini.</li><?php endif; ?>
				<?php arsort($by_cat); foreach ($by_cat as $k => $v): ?><li class="list-group-item d-flex"><span><?= e(Report_model::$expense_categories[$k]) ?></span><span class="ms-auto"><?= rupiah($v) ?></span></li><?php endforeach; ?>
			</ul>
		</div>
		<div class="card">
			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th class="text-end">Jumlah</th><th></th></tr></thead>
					<?php foreach ($rows as $r): ?>
						<tr>
							<td class="small text-nowrap"><?= tgl($r['expense_date'], FALSE) ?></td>
							<td class="small"><?= e(Report_model::$expense_categories[$r['category']]) ?></td>
							<td class="small"><?= e($r['description']) ?><?= $r['attachment_path'] ? ' <a href="' . file_url($r['attachment_path']) . '" target="_blank"><i class="bi bi-paperclip"></i></a>' : '' ?>
								<div class="text-muted"><?= e($r['payment_method']) ?> · <?= e($r['created_by_name']) ?></div></td>
							<td class="text-end"><?= rupiah($r['amount']) ?></td>
							<td class="text-end text-nowrap"><?php if (can('finance.expense')): ?>
								<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('finance/expenses?period=' . $period . '&edit=' . $r['id']) ?>"><i class="bi bi-pencil"></i></a>
								<?= form_open('finance/expenses/delete/' . $r['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus pengeluaran ini?')) ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button><?= form_close() ?>
							<?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>
	</div>
</div>
