<?php defined('BASEPATH') OR exit('No direct script access allowed');
$can_price = can('menu.view');
$can_cogs = can('menu.view_cogs');
?>
<form class="card mb-3" method="get" action="<?= site_url('menu/items') ?>">
	<div class="card-body row g-2 align-items-end">
		<div class="col-12 col-md-3">
			<label class="form-label small" for="q">Cari</label>
			<input class="form-control form-control-sm" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama atau kode">
		</div>
		<div class="col-6 col-md-3">
			<label class="form-label small" for="category_id">Kategori</label>
			<select class="form-select form-select-sm" id="category_id" name="category_id">
				<option value="">Semua</option>
				<?php foreach ($categories as $id => $path): ?><option value="<?= $id ?>" <?= (int) $filters['category_id'] === (int) $id ? 'selected' : '' ?>><?= e($path) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="status">Status</label>
			<select class="form-select form-select-sm" id="status" name="status">
				<option value="">Semua</option>
				<?php foreach (Menu_model::$statuses as $k => $v): ?><option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
			</select>
		</div>
		<div class="col-6 col-md-2">
			<label class="form-label small" for="avail">Tampilkan</label>
			<select class="form-select form-select-sm" id="avail" name="avail">
				<option value="">Semua</option>
				<option value="out" <?= $filters['avail'] === 'out' ? 'selected' : '' ?>>Sedang habis</option>
				<option value="low" <?= $filters['avail'] === 'low' ? 'selected' : '' ?>>Stok menipis</option>
				<?php if ($can_cogs): ?><option value="margin" <?= $filters['avail'] === 'margin' ? 'selected' : '' ?>>Margin &lt; <?= $warn ?>%</option><?php endif; ?>
			</select>
		</div>
		<div class="col-6 col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button></div>
	</div>
</form>

<div class="d-flex mb-3">
	<span class="small text-muted align-self-center"><?= count($menus) ?> menu</span>
	<?php if (can('menu.create')): ?><a class="btn btn-primary btn-sm ms-auto" href="<?= site_url('menu/items/create') ?>"><i class="bi bi-plus-lg"></i> Menu Baru</a><?php endif; ?>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead><tr><th>Menu</th><th>Varian</th><?php if ($can_price): ?><th class="text-end">Harga</th><?php endif; ?><?php if ($can_cogs): ?><th class="text-end">COGS</th><th class="text-end">Margin</th><?php endif; ?><th>Ketersediaan</th></tr></thead>
			<tbody>
			<?php if (empty($menus)): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada menu<?= $filters['q'] || $filters['category_id'] || $filters['status'] || $filters['avail'] ? ' yang cocok dengan filter' : '' ?>.</td></tr><?php endif; ?>
			<?php foreach ($menus as $m): $n = max(1, count($m['variants'])); ?>
				<?php foreach ($m['variants'] ?: array(NULL) as $k => $v): $i = $v ? $info[$v['id']] : NULL; ?>
				<tr class="<?= $m['status'] === 'inactive' ? 'text-muted' : '' ?> <?= $k > 0 ? 'border-top-0' : '' ?>">
					<?php if ($k === 0): ?>
						<td rowspan="<?= $n ?>" style="min-width: 220px">
							<div class="d-flex gap-2 align-items-center">
								<?php if ($m['image_path']): ?><img src="<?= base_url($m['image_path']) ?>" alt="" class="rounded" style="width:44px;height:44px;object-fit:cover"><?php endif; ?>
								<div>
									<a class="fw-medium" href="<?= site_url('menu/items/show/' . $m['id']) ?>"><?= e($m['name']) ?></a>
									<div class="small text-muted"><code><?= e($m['code']) ?></code> · <?= e($m['category_name'] ?: 'Tanpa kategori') ?></div>
								</div>
							</div>
						</td>
					<?php endif; ?>
					<td class="small"><?= $v ? e($v['name']) . ($v['is_active'] ? '' : ' <span class="badge text-bg-secondary">nonaktif</span>') : '-' ?></td>
					<?php if ($can_price): ?><td class="text-end"><?= $i && $i['price'] !== NULL ? rupiah($i['price']) : '-' ?>
						<?php if ($i && $i['next_price'] !== NULL): ?><div class="small text-info" title="Berlaku <?= e(tgl($i['next_from'])) ?>">→ <?= rupiah($i['next_price']) ?></div><?php endif; ?></td><?php endif; ?>
					<?php if ($can_cogs): ?>
						<td class="text-end small"><?= $i && $i['has_cogs'] ? rupiah($i['cogs']) . ($i['cogs_source'] === 'manual' ? ' <span class="badge text-bg-warning" title="HPP diisi manual">M</span>' : '') : '<span class="text-muted">belum ada resep</span>' ?><?= $i && $i['missing_cost'] && $i['cogs_source'] === 'resep' ? ' <i class="bi bi-exclamation-circle text-warning" title="Ada bahan tanpa harga"></i>' : '' ?></td>
						<td class="text-end small <?= $i && $i['margin_pct'] !== NULL && $i['margin_pct'] < $warn ? 'text-danger fw-medium' : '' ?>"><?= $i && $i['margin_pct'] !== NULL ? number_format($i['margin_pct'], 1, ',', '.') . '%' : '-' ?></td>
					<?php endif; ?>
					<td><?php if ($v): $a = Menu_service::availability($m['status'], $v['is_active'], $i['portions']); $portions = $i['portions']; include __DIR__ . '/_avail.php'; endif; ?></td>
				</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
