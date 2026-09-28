<?php defined('BASEPATH') OR exit('No direct script access allowed');
$describe = function ($p) {
	switch ($p['type'])
	{
		case 'fixed':   return 'Potongan ' . rupiah($p['value']) . ($p['scope'] === 'all' ? ' per transaksi' : ' per item');
		case 'percent': return 'Diskon ' . rtrim(rtrim(number_format($p['value'], 2, ',', '.'), '0'), ',') . '%' . ($p['max_discount'] ? ' (maks. ' . rupiah($p['max_discount']) . ')' : '');
		case 'buy_get': return 'Beli ' . (int) $p['buy_qty'] . ' gratis ' . (int) $p['get_qty'];
		case 'bundle':  return 'Paket ' . rupiah($p['bundle_price']);
	}
};
?>
<div class="d-flex flex-wrap gap-2 mb-3">
	<a class="btn btn-outline-secondary btn-sm" href="<?= site_url('menu/promos/simulator') ?>"><i class="bi bi-calculator"></i> Simulator</a>
	<?php if (can('menu.promo')): ?><a class="btn btn-primary btn-sm ms-auto" href="<?= site_url('menu/promos/create') ?>"><i class="bi bi-plus-lg"></i> Promo Baru</a><?php endif; ?>
</div>
<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Promo</th><th>Aturan</th><th>Periode</th><th class="text-center">Dipakai</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada promo.</td></tr><?php endif; ?>
			<?php foreach ($rows as $p): ?>
				<tr>
					<td><strong><?= e($p['name']) ?></strong>
						<?php if ($p['promo_code']): ?><div><code><?= e($p['promo_code']) ?></code></div><?php endif; ?>
						<?php if ($p['description']): ?><div class="small text-muted"><?= e($p['description']) ?></div><?php endif; ?></td>
					<td class="small"><?= e($describe($p)) ?>
						<div class="text-muted">
							<?= $p['min_purchase'] > 0 ? 'min. ' . rupiah($p['min_purchase']) . ' · ' : '' ?>
							<?= $p['days_of_week'] ? implode(',', array_map(function ($d) { return Promo_engine::$days[(int) $d]; }, explode(',', $p['days_of_week']))) . ' · ' : '' ?>
							<?= $p['time_start'] ? substr($p['time_start'], 0, 5) . '–' . substr($p['time_end'], 0, 5) . ' · ' : '' ?>
							<?= $p['member_only'] ? 'member · ' : '' ?><?= $p['stackable'] ? 'bisa digabung' : 'tidak digabung' ?>
						</div></td>
					<td class="small text-nowrap"><?= tgl($p['start_at']) ?><br><?= $p['end_at'] ? 's/d ' . tgl($p['end_at']) : 'tanpa batas' ?></td>
					<td class="text-center"><?= (int) $p['usage_count'] ?><?= $p['max_usage_total'] ? ' / ' . (int) $p['max_usage_total'] : '' ?></td>
					<td><span class="badge text-bg-<?= $p['state'][1] ?>"><?= e($p['state'][0]) ?></span></td>
					<td class="text-end text-nowrap">
						<?php if (can('menu.promo')): ?>
							<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('menu/promos/edit/' . $p['id']) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
							<?= form_open('menu/promos/toggle/' . $p['id'], array('class' => 'd-inline')) ?><button class="btn btn-sm btn-outline-<?= $p['is_active'] ? 'warning' : 'success' ?>" title="<?= $p['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>"><i class="bi bi-<?= $p['is_active'] ? 'pause' : 'play' ?>"></i></button><?= form_close() ?>
							<?php if ( ! $p['usage_count']): ?><?= form_open('menu/promos/delete/' . $p['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus promo ' . $p['name'] . '?')) ?><button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button><?= form_close() ?><?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
