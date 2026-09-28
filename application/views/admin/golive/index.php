<?php defined('BASEPATH') OR exit('No direct script access allowed');
$ok = 0; $all = 0;
foreach ($groups as $items) { foreach ($items as $i) { $all++; $ok += $i[0] ? 1 : 0; } }
?>
<div class="card mb-3">
	<div class="card-body d-flex flex-wrap align-items-center gap-3">
		<div class="fs-2 fw-bold"><?= $ok ?>/<?= $all ?></div>
		<div class="flex-fill">
			<div class="progress" style="height: 10px" role="progressbar" aria-valuenow="<?= $ok ?>" aria-valuemax="<?= $all ?>"><div class="progress-bar bg-success" style="width: <?= $all ? $ok / $all * 100 : 0 ?>%"></div></div>
			<div class="small text-muted mt-1">Pengecekan otomatis dari data sistem. Item manual di bagian bawah.</div>
		</div>
	</div>
</div>
<div class="row g-3">
	<?php foreach ($groups as $title => $items): ?>
		<div class="col-lg-6">
			<div class="card h-100">
				<div class="card-header"><?= e($title) ?></div>
				<ul class="list-group list-group-flush">
					<?php foreach ($items as $i): ?>
						<li class="list-group-item d-flex gap-2 align-items-start">
							<i class="bi <?= $i[0] ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle-fill text-warning' ?> mt-1" aria-label="<?= $i[0] ? 'Selesai' : 'Belum' ?>"></i>
							<div class="flex-fill"><div><?= e($i[1]) ?> <span class="visually-hidden"><?= $i[0] ? '(selesai)' : '(belum)' ?></span></div><div class="small text-muted"><?= e($i[2]) ?></div></div>
							<?php if ($i[3] && ! $i[0]): ?><a class="btn btn-sm btn-outline-primary" href="<?= site_url($i[3]) ?>">Buka</a><?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endforeach; ?>
	<div class="col-12">
		<div class="card">
			<div class="card-header">Checklist manual (perangkat & tim)</div>
			<ul class="list-group list-group-flush">
				<?php foreach ($manual as $k => $m): ?>
					<li class="list-group-item"><div class="form-check"><input class="form-check-input golive-manual" type="checkbox" id="gm<?= $k ?>" data-k="<?= $k ?>"><label class="form-check-label" for="gm<?= $k ?>"><?= e($m) ?></label></div></li>
				<?php endforeach; ?>
			</ul>
			<div class="card-body small text-muted py-2">Centang manual hanya tersimpan di browser ini (sebagai pengingat).</div>
		</div>
	</div>
</div>
<script>
document.querySelectorAll('.golive-manual').forEach(function (c) {
	var key = 'golive-' + c.getAttribute('data-k');
	try { c.checked = localStorage.getItem(key) === '1'; } catch (e) {}
	c.addEventListener('change', function () { try { localStorage.setItem(key, c.checked ? '1' : '0'); } catch (e) {} });
});
</script>
