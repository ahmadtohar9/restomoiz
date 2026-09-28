<?php defined('BASEPATH') OR exit('No direct script access allowed');
$base = 'reports/refunds';
$this->load->view('reports/_filters', compact('from', 'to', 'preset', 'base'));
$total_req = array_sum(array_map('intval', $status));
?>
<div class="row g-3 mb-3">
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Refund selesai</div><div class="stat-value fs-4"><?= rupiah($summary['refunds']) ?></div><div class="small text-muted"><?= (int) $summary['refund_count'] ?> refund</div></div></div></div>
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Refund rate</div><div class="stat-value fs-4"><?= number_format($summary['refund_rate'], 1, ',', '.') ?>%</div><div class="small text-muted">dari <?= (int) $summary['tx'] ?> transaksi</div></div></div></div>
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Pengajuan</div><div class="stat-value fs-4"><?= $total_req ?></div><div class="small text-muted"><?= (int) (isset($status['pending_approval']) ? $status['pending_approval'] : 0) ?> menunggu</div></div></div></div>
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Approval rate</div><div class="stat-value fs-4"><?= $approval_rate !== NULL ? number_format($approval_rate, 0, ',', '.') . '%' : '-' ?></div><div class="small text-muted"><?= (int) (isset($status['rejected']) ? $status['rejected'] : 0) ?> ditolak</div></div></div></div>
</div>
<div class="row g-3">
	<div class="col-lg-6">
		<div class="card h-100"><div class="card-header d-flex">Alasan refund <a class="small ms-auto no-print" href="<?= site_url('reports/refunds?from=' . $from . '&to=' . $to . '&export=1') ?>"><i class="bi bi-download"></i> CSV</a></div>
			<div class="card-body"><div id="c-reason"></div>
				<table class="table table-sm small mt-2 mb-0"><?php foreach ($by_reason as $r): ?><tr><td><?= e(Refund_service::$reasons[$r['reason_code']]) ?></td><td class="text-end"><?= (int) $r['n'] ?>×</td><td class="text-end"><?= rupiah($r['total']) ?></td></tr><?php endforeach; ?></table></div></div>
	</div>
	<div class="col-lg-6">
		<div class="card h-100"><div class="card-header">Menu dengan refund tertinggi <span class="small text-muted fw-normal">(indikasi masalah kualitas)</span></div>
			<div class="table-responsive"><table class="table table-sm mb-0">
				<thead><tr><th>Menu</th><th class="text-end">Terjual</th><th class="text-end">Direfund</th><th class="text-end">Rate</th></tr></thead>
				<?php if (empty($items)): ?><tr><td colspan="4" class="text-muted text-center py-3">Tidak ada refund. 👍</td></tr><?php endif; ?>
				<?php foreach (array_slice($items, 0, 10) as $it): ?><tr><td><?= e($it['name']) ?></td><td class="text-end"><?= (int) $it['qty'] ?></td><td class="text-end"><?= (int) $it['refunded_qty'] ?></td>
					<td class="text-end <?= $it['refund_rate'] > 5 ? 'text-danger fw-medium' : '' ?>"><?= number_format($it['refund_rate'], 1, ',', '.') ?>%</td></tr><?php endforeach; ?>
			</table></div></div>
	</div>
</div>
<?php if (count($trend) > 1): ?>
<div class="card mt-3"><div class="card-header">Tren nilai refund</div><div class="card-body"><div id="c-trend"></div></div></div>
<?php endif; ?>
<script src="<?= base_url('assets/js/charts.js') ?>"></script>
<script>
(function () {
	Charts.bars(document.getElementById('c-reason'), { format: 'num', name: 'Alasan refund', items: <?= json_encode(array_map(function ($r) { return array('label' => Refund_service::$reasons[$r['reason_code']], 'value' => (int) $r['n'], 'note' => 'pengajuan'); }, $by_reason)) ?> });
	<?php if (count($trend) > 1): ?>
	var t = <?= json_encode(array_map(function ($r) { return array(date('j/n', strtotime($r['d'])), (float) $r['total']); }, $trend)) ?>;
	Charts.line(document.getElementById('c-trend'), { labels: t.map(function (x) { return x[0]; }), values: t.map(function (x) { return x[1]; }), format: 'rp', name: 'Nilai refund' });
	<?php endif; ?>
})();
</script>
