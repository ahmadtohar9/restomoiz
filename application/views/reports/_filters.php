<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Baris filter tanggal (preset + custom). Butuh $from, $to, $preset, $base (URL). */
$tabs = array('reports' => 'Dashboard', 'reports/sales' => 'Penjualan', 'reports/cashiers' => 'Kasir', 'reports/refunds' => 'Refund');
if (can('report.financial')) { $tabs['reports/pnl'] = 'Laba Rugi'; }
if (can_any(array('menu.view_cogs', 'report.financial'))) { $tabs['menu/analysis'] = 'Margin Menu'; }
if (can('report.inventory')) { $tabs['inventory/reports'] = 'Inventory'; $tabs['purchase/reports'] = 'Pembelian'; }
?>
<ul class="nav nav-pills small mb-3 no-print flex-nowrap overflow-auto">
	<?php foreach ($tabs as $url => $label): ?>
		<li class="nav-item"><a class="nav-link py-1 <?= uri_string() === $url ? 'active' : '' ?>" href="<?= site_url($url) ?>"><?= e($label) ?></a></li>
	<?php endforeach; ?>
</ul>
<?php if (isset($preset)): ?>
<form class="d-flex flex-wrap gap-2 align-items-center mb-3 no-print" method="get" action="<?= site_url($base) ?>">
	<div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Periode">
		<?php foreach (Reports::$presets as $k => $label): ?>
			<a class="btn <?= $preset === $k ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= site_url($base . '?preset=' . $k) ?>"><?= e($label) ?></a>
		<?php endforeach; ?>
	</div>
	<input class="form-control form-control-sm" type="date" name="from" value="<?= e($from) ?>" style="max-width: 150px" aria-label="Dari">
	<input class="form-control form-control-sm" type="date" name="to" value="<?= e($to) ?>" max="<?= date('Y-m-d') ?>" style="max-width: 150px" aria-label="Sampai">
	<button class="btn btn-sm btn-outline-primary">Terapkan</button>
	<span class="small text-muted ms-auto"><?= tgl($from, FALSE) ?><?= $from !== $to ? ' – ' . tgl($to, FALSE) : '' ?></span>
	<button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="Cetak / simpan PDF"><i class="bi bi-printer"></i></button>
</form>
<?php endif; ?>
