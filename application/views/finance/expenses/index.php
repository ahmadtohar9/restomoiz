<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
$total = array_sum($by_cat);
?>
<form class="d-flex flex-wrap gap-2 mb-3" method="get">
	<input class="form-control" type="month" name="period" value="<?= e($period) ?>" max="<?= date('Y-m') ?>" style="max-width: 200px">
	<button class="btn btn-outline-secondary">Tampilkan</button>
	<a class="btn btn-outline-secondary ms-auto" href="<?= site_url('finance/expenses?period=' . $period . '&export=1') ?>"><i class="bi bi-download"></i> CSV</a>
	<?php if (can('finance.expense')): ?><button type="button" class="btn btn-primary" id="btn-add-expense" data-modal-template="#tpl-expense" data-modal-title="Catat pengeluaran"><i class="bi bi-plus-lg"></i> Catat pengeluaran</button><?php endif; ?>
	<?php if (can('report.financial')): ?><a class="btn btn-outline-primary" href="<?= site_url('reports/pnl?period=' . $period) ?>"><i class="bi bi-file-earmark-bar-graph"></i> Laba rugi</a><?php endif; ?>
</form>
<p class="small text-muted">Catat beban operasional di sini (gaji, sewa, listrik, marketing, dll.). Pembelian bahan baku <strong>tidak</strong> dicatat di sini karena sudah masuk lewat Pembelian dan menjadi COGS saat terpakai.</p>

<div class="row g-3">
	<div class="col-lg-4 order-lg-2">
		<div class="card mb-3">
			<div class="card-header d-flex">Ringkasan <?= e($period) ?><strong class="ms-auto"><?= rupiah($total) ?></strong></div>
			<ul class="list-group list-group-flush small">
				<?php if ( ! $by_cat): ?><li class="list-group-item text-muted">Belum ada pengeluaran bulan ini.</li><?php endif; ?>
				<?php arsort($by_cat); foreach ($by_cat as $k => $v): ?><li class="list-group-item"><div class="d-flex"><span><?= e(Report_model::$expense_categories[$k]) ?></span><span class="ms-auto fw-medium"><?= rupiah($v) ?></span></div><div class="meter"><span style="width: <?= $total > 0 ? round($v / $total * 100) : 0 ?>%"></span></div></li><?php endforeach; ?>
			</ul>
		</div>
	</div>
	<div class="col-lg-8 order-lg-1">
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
								<button type="button" class="btn btn-sm btn-outline-secondary" data-modal-template="#tpl-expense" data-modal-title="Ubah pengeluaran" title="Ubah"
										data-fill="<?= e(json_encode(array('id' => (int) $r['id'], 'expense_date' => $r['expense_date'], 'category' => $r['category'], 'description' => $r['description'], 'amount' => $num($r['amount']), 'payment_method' => (string) $r['payment_method']))) ?>"><i class="bi bi-pencil"></i></button>
								<?= form_open('finance/expenses/delete/' . $r['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus pengeluaran ini?')) ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button><?= form_close() ?>
							<?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>
	</div>
</div>

<?php if (can('finance.expense')): ?>
<template id="tpl-expense" data-icon="wallet2">
	<?= form_open_multipart('finance/expenses/save') ?>
		<input type="hidden" name="id" value="0">
		<div class="row g-3">
			<div class="col-sm-6"><label class="form-label" for="expense_date">Tanggal</label><input class="form-control" type="date" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
			<div class="col-sm-6"><label class="form-label" for="category">Kategori</label>
				<select class="form-select" id="category" name="category">
					<?php foreach (Report_model::$expense_categories as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
				</select></div>
			<div class="col-12"><label class="form-label" for="description">Keterangan</label><input class="form-control" id="description" name="description" maxlength="255" required placeholder="mis. Listrik PLN September"></div>
			<div class="col-sm-6"><label class="form-label" for="amount">Jumlah</label><div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="number" min="0" step="any" id="amount" name="amount" required></div></div>
			<div class="col-sm-6"><label class="form-label" for="payment_method">Dibayar dengan</label><input class="form-control" id="payment_method" name="payment_method" maxlength="20" placeholder="transfer / tunai / ..." list="pm-list">
				<datalist id="pm-list"><option value="tunai"><option value="transfer"><option value="QRIS"><option value="kartu"></datalist></div>
			<div class="col-12"><label class="form-label" for="attachment">Bukti <span class="text-muted fw-normal">(opsional)</span></label>
				<input class="form-control" type="file" id="attachment" name="attachment" accept="application/pdf,image/jpeg,image/png,image/webp">
				<div class="form-text" data-if-edit>Kosongkan jika bukti tidak diganti.</div></div>
		</div>
		<div class="ui-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Simpan</button></div>
	<?= form_close() ?>
</template>
<?php if ($edit): // tautan lama ?edit=ID (mis. setelah error validasi) membuka modal ubah otomatis ?>
<script>document.addEventListener('DOMContentLoaded', function () { var b = [].find.call(document.querySelectorAll('[data-modal-template="#tpl-expense"][data-fill]'), function (x) { return JSON.parse(x.getAttribute('data-fill')).id === <?= (int) $edit['id'] ?>; }); if (b) b.click(); });</script>
<?php endif; ?>
<?php endif; ?>
