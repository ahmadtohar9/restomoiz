<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Menu · <?= e($resto) ?></title>
	<meta name="description" content="Daftar menu dan harga <?= e($resto) ?>">
	<style>
		:root { --bg: #faf7f2; --card: #fff; --ink: #1f2937; --muted: #6b7280; --accent: #d97706; --line: #ece7df; --out: #b91c1c; }
		@media (prefers-color-scheme: dark) { :root { --bg: #111418; --card: #1a1f25; --ink: #eef0f2; --muted: #9aa3ad; --accent: #f59e0b; --line: #2a3139; --out: #f87171; } }
		* { box-sizing: border-box; }
		body { margin: 0; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--ink); }
		header { padding: 28px 16px 12px; text-align: center; }
		header h1 { margin: 0; font-size: 26px; letter-spacing: .01em; }
		header p { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
		nav { position: sticky; top: 0; z-index: 5; background: var(--bg); border-bottom: 1px solid var(--line); overflow-x: auto; white-space: nowrap; padding: 8px 12px; }
		nav a { display: inline-block; padding: 6px 12px; margin-right: 4px; border-radius: 999px; color: var(--ink); text-decoration: none; font-size: 14px; border: 1px solid var(--line); background: var(--card); }
		main { max-width: 760px; margin: 0 auto; padding: 8px 16px 48px; }
		section h2 { font-size: 18px; margin: 24px 0 10px; padding-bottom: 6px; border-bottom: 2px solid var(--accent); display: inline-block; scroll-margin-top: 60px; }
		.item { display: flex; gap: 12px; background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 12px; margin-bottom: 10px; scroll-margin-top: 60px; }
		.item:target { outline: 2px solid var(--accent); }
		.item img { width: 84px; height: 84px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
		.item h3 { margin: 0; font-size: 16px; }
		.desc { color: var(--muted); font-size: 13px; margin: 2px 0 6px; }
		.meta { font-size: 12px; color: var(--muted); display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 4px; }
		.prices { display: flex; flex-wrap: wrap; gap: 6px 14px; }
		.price { font-weight: 700; }
		.price small { font-weight: 400; color: var(--muted); }
		.out { color: var(--out); font-size: 12px; font-weight: 600; }
		.is-out { opacity: .6; }
		footer { text-align: center; color: var(--muted); font-size: 12px; padding: 24px; }
	</style>
</head>
<body>
<header>
	<h1><?= e($resto) ?></h1>
	<p>Harga dapat berubah sewaktu-waktu. Pajak &amp; service charge mengikuti ketentuan resto.</p>
</header>
<?php
$sections = array();
foreach ($categories as $c) { if ( ! empty($by_cat[(int) $c['id']])) $sections[] = array('id' => (int) $c['id'], 'name' => $c['path']); }
if ( ! empty($by_cat[0])) $sections[] = array('id' => 0, 'name' => 'Lainnya');
?>
<?php if (count($sections) > 1): ?>
<nav aria-label="Kategori"><?php foreach ($sections as $s): ?><a href="#c<?= $s['id'] ?>"><?= e($s['name']) ?></a><?php endforeach; ?></nav>
<?php endif; ?>
<main>
	<?php if (empty($sections)): ?><p style="text-align:center;color:var(--muted)">Menu belum tersedia.</p><?php endif; ?>
	<?php foreach ($sections as $s): ?>
		<section id="c<?= $s['id'] ?>">
			<h2><?= e($s['name']) ?></h2>
			<?php foreach ($by_cat[$s['id']] as $m):
				$all_out = TRUE;
				foreach ($m['variants'] as $v) { $a = Menu_service::availability($m['status'], $v['is_active'], $info[$v['id']]['portions']); if ( ! in_array($a, array('out', 'manual_out', 'inactive'), TRUE)) $all_out = FALSE; }
			?>
				<article class="item <?= $all_out ? 'is-out' : '' ?>" id="m<?= $m['id'] ?>">
					<?php if ($m['image_path']): ?><img src="<?= base_url($m['image_path']) ?>" alt="<?= e($m['name']) ?>" loading="lazy"><?php endif; ?>
					<div>
						<h3><?= e($m['name']) ?></h3>
						<?php if ($m['description']): ?><p class="desc"><?= e($m['description']) ?></p><?php endif; ?>
						<div class="meta">
							<?php if ($m['spicy_level']): ?><span><?= str_repeat('🌶', (int) $m['spicy_level']) ?></span><?php endif; ?>
							<?php if ($m['prep_minutes']): ?><span>± <?= (int) $m['prep_minutes'] ?> menit</span><?php endif; ?>
							<?php if ($m['allergens']): ?><span>Mengandung: <?= e($m['allergens']) ?></span><?php endif; ?>
						</div>
						<div class="prices">
							<?php foreach ($m['variants'] as $v): $i = $info[$v['id']]; if ($i['price'] === NULL) continue;
								$a = Menu_service::availability($m['status'], $v['is_active'], $i['portions']); ?>
								<span class="price"><?php if (count($m['variants']) > 1 OR $v['name'] !== 'Reguler'): ?><small><?= e($v['name']) ?></small> <?php endif; ?><?= rupiah($i['price']) ?>
									<?php if (in_array($a, array('out', 'manual_out'), TRUE)): ?><span class="out">habis</span><?php endif; ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endforeach; ?>
</main>
<footer><?= e($resto) ?> · diperbarui <?= date('d/m/Y H:i') ?></footer>
</body>
</html>
