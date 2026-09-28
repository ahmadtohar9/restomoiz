<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('reports/_filters');
$pct = function ($v, $base) { return $base > 0 ? number_format($v / $base * 100, 0, ',', '.') . '%' : ''; };
$chg = function ($a, $b) {
	if ( ! $b) return '';
	$p = ($a - $b) / abs($b) * 100;
	return '<span class="' . ($p >= 0 ? 'text-success' : 'text-danger') . '">' . ($p >= 0 ? '+' : '') . number_format($p, 1, ',', '.') . '%</span>';
};
$row = function ($label, $a, $b, $base, $cls = '', $neg = FALSE) use ($pct) {
	return '<tr class="' . $cls . '"><td>' . e($label) . '</td><td class="text-end">' . ($neg ? '−' : '') . rupiah(abs($a)) . '</td><td class="text-end small text-muted">' . $pct(abs($a), $base)
		. '</td><td class="text-end">' . ($neg ? '−' : '') . rupiah(abs($b)) . '</td></tr>';
};
$bln = array(1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
$name = function ($p) use ($bln) { return $bln[(int) substr($p, 5, 2)] . ' ' . substr($p, 0, 4); };
$R = $cur['revenue'];
$wl = array('waste' => 'Terbuang', 'expired' => 'Kedaluwarsa', 'damaged' => 'Rusak', 'adjustment_out' => 'Penyesuaian (−)', 'adjustment_in' => 'Penyesuaian (+)', 'opname' => 'Selisih opname');
?>
<form class="d-flex flex-wrap gap-2 align-items-center mb-3 no-print" method="get">
	<input class="form-control form-control-sm" type="month" name="period" value="<?= e($period) ?>" max="<?= date('Y-m') ?>" style="max-width: 180px">
	<button class="btn btn-sm btn-outline-primary">Tampilkan</button>
	<a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= site_url('reports/pnl?period=' . $period . '&export=1') ?>"><i class="bi bi-download"></i> CSV</a>
	<button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Cetak / PDF</button>
	<?php if (can('finance.expense')): ?><a class="btn btn-sm btn-primary" href="<?= site_url('finance/expenses?period=' . $period) ?>"><i class="bi bi-plus-lg"></i> Catat pengeluaran</a><?php endif; ?>
</form>

<div class="card" style="max-width: 860px">
	<div class="card-body">
		<div class="text-center mb-3">
			<h2 class="h5 mb-0">LAPORAN LABA RUGI</h2>
			<div class="text-muted"><?= e(setting('resto_name', 'Resto Moiz')) ?> · <?= $name($period) ?><?= $partial ? ' (berjalan, s/d ' . tgl(date('Y-m-d'), FALSE) . ')' : '' ?></div>
		</div>
		<table class="table table-borderless pnl mb-0">
			<thead class="small text-muted"><tr><th></th><th class="text-end"><?= $name($period) ?></th><th class="text-end">%</th><th class="text-end"><?= $name($prev_period) ?></th></tr></thead>
			<tr><td colspan="4" class="fw-bold pt-2">PENDAPATAN</td></tr>
			<?php foreach ($cur['categories'] as $k => $c): ?>
				<?= $row('Penjualan ' . $c['name'], $c['revenue'], isset($prev['categories'][$k]) ? $prev['categories'][$k]['revenue'] : 0, $R, 'pnl-indent') ?>
			<?php endforeach; ?>
			<?php if ($cur['service'] > 0 OR $prev['service'] > 0): ?><?= $row('Service charge', $cur['service'], $prev['service'], $R, 'pnl-indent') ?><?php endif; ?>
			<?php if ($cur['refunds'] > 0 OR $prev['refunds'] > 0): ?><?= $row('Refund', $cur['refunds'], $prev['refunds'], $R, 'pnl-indent', TRUE) ?><?php endif; ?>
			<?= $row('Total pendapatan', $R, $prev['revenue'], $R, 'pnl-sub') ?>

			<tr><td colspan="4" class="fw-bold pt-3">HARGA POKOK PENJUALAN (COGS)</td></tr>
			<?= $row('Bahan baku menu terjual', $cur['cogs_items'], $prev['cogs_items'], $R, 'pnl-indent') ?>
			<?php foreach ($wl as $k => $label): if (empty($cur['waste'][$k]) && empty($prev['waste'][$k])) continue; ?>
				<?= $row($label, isset($cur['waste'][$k]) ? $cur['waste'][$k] : 0, isset($prev['waste'][$k]) ? $prev['waste'][$k] : 0, $R, 'pnl-indent') ?>
			<?php endforeach; ?>
			<?= $row('Total COGS', $cur['cogs_total'], $prev['cogs_total'], $R, 'pnl-sub') ?>
			<?= $row('LABA KOTOR', $cur['gross_profit'], $prev['gross_profit'], $R, 'pnl-total') ?>

			<tr><td colspan="4" class="fw-bold pt-3">BEBAN OPERASIONAL</td></tr>
			<?php foreach ($cur['opex'] as $k => $v): if ( ! $v && ! $prev['opex'][$k]) continue; ?>
				<?= $row(Report_model::$expense_categories[$k], $v, $prev['opex'][$k], $R, 'pnl-indent') ?>
			<?php endforeach; ?>
			<?php if ( ! $cur['opex_total'] && ! $prev['opex_total']): ?><tr class="pnl-indent"><td colspan="4" class="small text-muted">Belum ada pengeluaran operasional yang dicatat.</td></tr><?php endif; ?>
			<?= $row('Total beban operasional', $cur['opex_total'], $prev['opex_total'], $R, 'pnl-sub') ?>
			<?= $row('EBIT (laba operasional)', $cur['ebit'], $prev['ebit'], $R, 'pnl-total') ?>
			<?php if ($cur['other'] OR $prev['other']): ?><?= $row('Bunga & beban non-operasional', $cur['other'], $prev['other'], $R, 'pnl-indent') ?><?php endif; ?>
			<tr class="pnl-total fs-5"><td>LABA BERSIH</td><td class="text-end <?= $cur['net'] < 0 ? 'text-danger' : '' ?>"><?= rupiah($cur['net']) ?></td><td class="text-end small text-muted"><?= $pct($cur['net'], $R) ?></td><td class="text-end"><?= rupiah($prev['net']) ?></td></tr>
		</table>

		<h3 class="h6 mt-4">Analisis</h3>
		<ul class="small mb-0">
			<li>Margin kotor <?= $R > 0 ? number_format($cur['gross_profit'] / $R * 100, 1, ',', '.') . '%' : '-' ?> <?= $R > 0 ? ($cur['gross_profit'] / $R * 100 >= 50 ? '(sehat, target umum resto 50–65%)' : '(di bawah 50%: cek harga jual & porsi di Analisis Margin)') : '' ?>.</li>
			<li>COGS <?= $pct($cur['cogs_total'], $R) ?> dari pendapatan<?= $cur['waste_total'] > 0 ? ', termasuk bahan terbuang/penyesuaian ' . rupiah($cur['waste_total']) : '' ?>.</li>
			<li>Laba bersih dibanding <?= $name($prev_period) ?>: <?= $chg($cur['net'], $prev['net']) ?: 'belum ada pembanding' ?>.</li>
			<li>Diskon promo yang diberikan <?= rupiah($cur['discount']) ?> dari <?= (int) $cur['tx'] ?> transaksi (sudah mengurangi pendapatan).</li>
			<li>PPN dipungut <?= rupiah($cur['tax_collected']) ?> — titipan pajak, tidak termasuk pendapatan.</li>
		</ul>
		<p class="small text-muted mt-3 mb-0">Pendapatan dicatat saat dibayar, refund saat dana dikembalikan. Pembelian bahan tidak menjadi beban langsung: nilainya masuk ke persediaan dan menjadi COGS saat terpakai (FIFO).</p>
	</div>
</div>
