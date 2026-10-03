<?php defined('BASEPATH') OR exit('No direct script access allowed');
$num = function ($v) { return $v === '' || $v === NULL ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.'); };
$can_cogs = can('menu.view_cogs');
$opts = function ($selected) use ($ingredients) {
	$html = '<option value="">— pilih bahan —</option>';
	foreach ($ingredients as $i)
	{
		$html .= '<option value="' . $i['id'] . '" data-unit="' . e($i['unit']) . '" data-units="' . e(json_encode($i['units'])) . '"'
			. ' data-stock="' . e((float) $i['qty_on_hand']) . '" data-price="' . e((float) $i['current_price']) . '" data-supplier="0"'
			. ((int) $selected === (int) $i['id'] ? ' selected' : '') . '>' . e($i['name'] . ' (' . $i['code'] . ')') . '</option>';
	}
	return $html;
};
$row = function ($idx, array $l) use ($opts, $num, $can_cogs) {
	ob_start(); ?>
	<tr class="doc-line">
		<td style="min-width: 240px">
			<select class="form-select form-select-sm line-ingredient" name="lines[<?= $idx ?>][ingredient_id]"><?= $opts($l['ingredient_id']) ?></select>
			<div class="small text-muted line-info"></div>
		</td>
		<td style="width: 120px"><input class="form-control form-control-sm line-qty" type="number" step="any" min="0" name="lines[<?= $idx ?>][qty]" value="<?= e($num($l['qty'])) ?>"></td>
		<td style="width: 120px"><select class="form-select form-select-sm line-unit" name="lines[<?= $idx ?>][unit]" data-selected="<?= e($l['unit']) ?>"></select></td>
		<td><input class="form-control form-control-sm" name="lines[<?= $idx ?>][notes]" value="<?= e($l['notes']) ?>" maxlength="255" placeholder="mis. potong dadu"></td>
		<?php if ($can_cogs): ?><td class="text-end small recipe-cost" style="width: 110px"></td><?php endif; ?>
		<td style="width: 40px"><button type="button" class="btn btn-sm btn-link text-danger line-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
	</tr>
	<?php return ob_get_clean();
};
?>
<p><a href="<?= site_url('menu/items/show/' . $variant['menu_id'] . '#v' . $variant['id']) ?>"><i class="bi bi-arrow-left"></i> Kembali ke menu</a></p>

<?php if ( ! empty($copied)): ?><div class="alert alert-info">Resep disalin dari varian lain. Sesuaikan jumlahnya lalu klik Simpan.</div><?php endif; ?>

<?= form_open('menu/items/recipe/' . $variant['id'], array('id' => 'stock-doc', 'data-direction' => 'recipe', 'data-unit-add' => '1')) ?>
<div class="card">
	<div class="card-header d-flex flex-wrap gap-2 align-items-center">
		<span>Bahan untuk <strong>1 porsi</strong></span>
		<?php if ($siblings): ?>
			<span class="ms-auto small">Salin dari:
				<?php foreach ($siblings as $s): ?><a class="ms-1" href="<?= site_url('menu/items/recipe/' . $variant['id'] . '?copy_from=' . $s['id']) ?>"><?= e($s['name']) ?></a><?php endforeach; ?>
			</span>
		<?php endif; ?>
	</div>
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead><tr><th>Bahan</th><th>Jumlah</th><th>Satuan</th><th>Catatan</th><?php if ($can_cogs): ?><th class="text-end">Biaya</th><?php endif; ?><th></th></tr></thead>
			<tbody id="doc-lines">
				<?php foreach ($lines as $i => $l): ?><?= $row($i, $l) ?><?php endforeach; ?>
			</tbody>
			<?php if ($can_cogs): ?>
				<tfoot>
					<tr><th colspan="4" class="text-end">COGS per porsi</th><th class="text-end" id="recipe-total">-</th><th></th></tr>
					<?php if ($price): ?><tr><td colspan="4" class="text-end small text-muted">Harga jual <?= rupiah($price) ?> · margin</td><td class="text-end small" id="recipe-margin" data-price="<?= e($price) ?>">-</td><td></td></tr><?php endif; ?>
				</tfoot>
			<?php endif; ?>
		</table>
	</div>
	<div class="card-footer bg-transparent">
		<button type="button" class="btn btn-sm btn-outline-secondary" id="add-line"><i class="bi bi-plus-lg"></i> Tambah bahan</button>
		<span class="small text-muted ms-2">Biaya dihitung dari harga beli terakhir bahan. Satuan bisa diganti per bahan (mis. jeruk per <em>buah</em>, minyak per <em>ml</em>); pilih <strong>+ Satuan lain…</strong> bila belum ada.</span>
	</div>
</div>
<template id="line-template"><?= $row('__i__', array('ingredient_id' => '', 'qty' => '', 'unit' => '', 'notes' => '')) ?></template>
<div class="mt-3 d-flex gap-2">
	<button class="btn btn-primary" type="submit">Simpan Resep</button>
	<a class="btn btn-outline-secondary" href="<?= site_url('menu/items/show/' . $variant['menu_id']) ?>">Batal</a>
</div>
<?= form_close() ?>

<?php if ($can_cogs && $cogs_manual !== NULL): ?>
	<div class="alert alert-warning mt-3 mb-0"><i class="bi bi-pencil-square"></i> Menu ini memakai <strong>HPP manual <?= rupiah($cogs_manual) ?></strong> per porsi. HPP dari resep di atas tetap dihitung untuk pembanding, dan stok tetap dipotong sesuai resep.
		<a href="<?= site_url('menu/items/show/' . $variant['menu_id'] . '#v' . $variant['id']) ?>">Ubah HPP</a></div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
	var doc = document.getElementById('stock-doc');
	var addUrl = <?= json_encode(site_url('menu/items/add_unit')) ?>;
	var csrf = { name: <?= json_encode($this->security->get_csrf_token_name()) ?>, value: <?= json_encode($this->security->get_csrf_hash()) ?> };
	var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };

	// Ingat satuan sebelumnya supaya bisa dikembalikan bila batal.
	doc.addEventListener('focusin', function (e) { if (e.target.classList.contains('line-unit') && e.target.value !== '__new__') e.target.dataset.prev = e.target.value; });

	doc.addEventListener('change', function (e) {
		var sel = e.target;
		if (!sel.classList.contains('line-unit') || sel.value !== '__new__') return;
		var row = sel.closest('.doc-line');
		var ingSel = row.querySelector('.line-ingredient'), opt = ingSel.options[ingSel.selectedIndex];
		var std = opt.getAttribute('data-unit'), name = opt.textContent;
		var revert = function () { sel.value = sel.dataset.prev || std; sel.dispatchEvent(new Event('change', { bubbles: true })); };
		var html = '<div class="text-start small text-muted mb-2">' + esc(name) + ' dibeli & distok dalam <strong>' + esc(std) + '</strong>. Tambahkan satuan pakai untuk resep:</div>'
			+ '<div class="row g-2 text-start"><div class="col-5"><label class="form-label small mb-1" for="nu-unit">Nama satuan</label><input id="nu-unit" class="form-control" maxlength="20" placeholder="mis. buah"></div>'
			+ '<div class="col-7"><label class="form-label small mb-1" for="nu-factor">1 satuan = berapa ' + esc(std) + '?</label><div class="input-group"><input id="nu-factor" class="form-control" inputmode="decimal" placeholder="mis. 0,15"><span class="input-group-text">' + esc(std) + '</span></div></div></div>'
			+ '<div class="small text-muted text-start mt-2">Contoh: 1 buah jeruk ≈ 0,15 kg · 1 sdm minyak ≈ 0,015 liter · 1 butir telur ≈ 0,06 kg</div>';
		var ask = window.Swal ? Swal.fire({
			title: 'Satuan baru', html: html, showCancelButton: true, confirmButtonText: 'Simpan satuan', cancelButtonText: 'Batal', focusConfirm: false,
			customClass: { popup: 'ui-swal', confirmButton: 'btn btn-primary', cancelButton: 'btn btn-light', actions: 'ui-swal-actions' }, buttonsStyling: false, reverseButtons: true,
			didOpen: function () { document.getElementById('nu-unit').focus(); },
			preConfirm: function () {
				var unit = document.getElementById('nu-unit').value.trim(), factor = document.getElementById('nu-factor').value.trim().replace(',', '.');
				if (!unit) { Swal.showValidationMessage('Isi nama satuan.'); return false; }
				if (!(parseFloat(factor) > 0)) { Swal.showValidationMessage('Isi konversi lebih dari 0, mis. 0,15.'); return false; }
				var body = new URLSearchParams();
				body.append(csrf.name, csrf.value); body.append('ingredient_id', ingSel.value); body.append('unit', unit); body.append('factor', factor);
				return fetch(addUrl, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (d) { if (!d.ok) { Swal.showValidationMessage(d.message || 'Gagal menyimpan satuan.'); return false; } return d; })
					.catch(function () { Swal.showValidationMessage('Koneksi gagal, coba lagi.'); return false; });
			}
		}).then(function (r) { return r.isConfirmed ? r.value : null; }) : Promise.resolve(null);

		ask.then(function (d) {
			if (!d) { revert(); return; }
			// Perbarui daftar satuan bahan ini di semua baris (dan template baris baru).
			var units = JSON.stringify(d.units);
			doc.querySelectorAll('.line-ingredient option[value="' + ingSel.value + '"]').forEach(function (o) { o.setAttribute('data-units', units); });
			var tpl = document.getElementById('line-template');
			tpl.content.querySelectorAll('.line-ingredient option[value="' + ingSel.value + '"]').forEach(function (o) { o.setAttribute('data-units', units); });
			doc.querySelectorAll('.doc-line').forEach(function (r) {
				var s = r.querySelector('.line-ingredient');
				if (s.value !== ingSel.value) return;
				var u = r.querySelector('.line-unit');
				u.setAttribute('data-selected', r === row ? d.unit : u.value);
				u.value = '';
				doc.fillUnits(r, true);
			});
			sel.dispatchEvent(new Event('change', { bubbles: true }));
			doc.refreshLine(row);
			row.querySelector('.line-qty').focus();
			if (window.UI) UI.toast(d.message, 'success');
		});
	});
});
</script>
