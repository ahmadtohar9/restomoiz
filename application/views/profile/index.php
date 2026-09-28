<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="row g-3">
	<div class="col-lg-5">
		<div class="card h-100">
			<div class="card-header">Akun</div>
			<div class="card-body">
				<dl class="row mb-0">
					<dt class="col-4">Nama</dt><dd class="col-8"><?= e($current_user['name']) ?></dd>
					<dt class="col-4">Username</dt><dd class="col-8"><?= e($current_user['username']) ?></dd>
					<dt class="col-4">Email</dt><dd class="col-8"><?= e($current_user['email'] ?: '-') ?></dd>
					<dt class="col-4">Role</dt>
					<dd class="col-8">
						<?php foreach ($current_user['roles'] as $r): ?>
							<span class="badge <?= $r['is_primary'] ? 'text-bg-primary' : 'text-bg-secondary' ?>"><?= e($r['name']) ?></span>
						<?php endforeach; ?>
					</dd>
					<dt class="col-4">Login terakhir</dt><dd class="col-8"><?= tgl($current_user['last_login']) ?></dd>
				</dl>
			</div>
			<div class="card-footer bg-transparent">
				<a class="btn btn-outline-primary btn-sm" href="<?= site_url('profile/password') ?>"><i class="bi bi-key"></i> Ganti Password</a>
			</div>
		</div>
	</div>
	<div class="col-lg-7">
		<div class="card h-100">
			<div class="card-header">Hak akses saya</div>
			<div class="card-body">
				<?php if ($is_super): ?>
					<p class="mb-0"><i class="bi bi-stars text-warning"></i> Anda Admin: memiliki semua permission.</p>
				<?php elseif (empty($permissions)): ?>
					<p class="text-muted mb-0">Belum ada permission.</p>
				<?php else: ?>
					<?php sort($permissions); foreach ($permissions as $p): ?>
						<code class="perm-chip"><?= e($p) ?></code>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php if ($custom): ?>
					<h6 class="mt-4">Custom permission</h6>
					<ul class="small mb-0">
						<?php foreach ($custom as $c): ?>
							<li><code><?= e($c['permission_name']) ?></code>
								<?= $c['expiry_date'] ? '— berlaku s/d ' . tgl($c['expiry_date'], FALSE) : '— permanen' ?>
								<?= ($c['expiry_date'] && $c['expiry_date'] < date('Y-m-d')) ? '<span class="badge text-bg-secondary">kedaluwarsa</span>' : '' ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
