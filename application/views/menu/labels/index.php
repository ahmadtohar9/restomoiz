<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<form method="get" action="<?= site_url('menu/labels/print') ?>" target="_blank">
<div class="card mb-3">
	<div class="card-body d-flex flex-wrap gap-3 align-items-end">
		<div><label class="form-label small" for="copies">Salinan per item</label><input class="form-control form-control-sm" type="number" min="1" max="50" id="copies" name="copies" value="1" style="width: 100px"></div>
		<div class="form-check"><input class="form-check-input" type="checkbox" id="price" name="price" value="1" checked><label class="form-check-label small" for="price">Tampilkan harga</label></div>
		<?php if (setting('public_menu_enabled', '1') === '1'): ?>
			<div class="form-check"><input class="form-check-input" type="checkbox" id="qr" name="qr" value="1"><label class="form-check-label small" for="qr">Sertakan QR ke menu online</label></div>
		<?php endif; ?>
		<div class="ms-auto d-flex gap-2">
			<a class="small align-self-center" href="#" data-check-all=".lbl-box">pilih semua</a>
			<button class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Cetak label terpilih</button>
		</div>
	</div>
</div>
<div class="card">
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead><tr><th style="width: 40px"></th><th>Menu</th><th>Varian</th><th>Barcode</th><th class="text-end">Harga</th></tr></thead>
			<tbody>
			<?php if (empty($menus)): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada menu.</td></tr><?php endif; ?>
			<?php foreach ($menus as $m): foreach ($m['variants'] as $v): ?>
				<tr class="<?= ($m['status'] === 'inactive' || ! $v['is_active']) ? 'text-muted' : '' ?>">
					<td><input class="form-check-input lbl-box" type="checkbox" name="v[]" value="<?= $v['id'] ?>" aria-label="Pilih <?= e($m['name'] . ' ' . $v['name']) ?>"></td>
					<td><a href="<?= site_url('menu/items/show/' . $m['id']) ?>"><?= e($m['name']) ?></a></td>
					<td class="small"><?= e($v['name']) ?></td>
					<td><?php if ($v['barcode']): ?><code><?= e($v['barcode']) ?></code> <a class="small" href="<?= site_url('menu/labels/svg/' . $v['id']) ?>" target="_blank">svg</a><?php else: ?><span class="small text-muted">belum ada barcode</span><?php endif; ?></td>
					<td class="text-end small"><?= $info[$v['id']]['price'] !== NULL ? rupiah($info[$v['id']]['price']) : '-' ?></td>
				</tr>
			<?php endforeach; endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
</form>
