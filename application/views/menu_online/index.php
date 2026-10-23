<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Menu · <?= e($resto) ?></title>
	<meta name="description" content="Daftar menu dan harga <?= e($resto) ?>">
	<meta name="theme-color" content="#0a192f">
	<link rel="icon" type="image/svg+xml" href="<?= base_url('favicon.svg') ?>">
	<style>
		:root { --bg: #f8fafc; --card: #ffffff; --ink: #0a192f; --muted: #64748b; --accent: #059669; --line: #e2e8f0; --out: #e11d48; }
		@media (prefers-color-scheme: dark) { :root { --bg: #0a192f; --card: #112240; --ink: #f8fafc; --muted: #94a3b8; --accent: #10b981; --line: rgba(16, 185, 129, 0.2); --out: #fb7185; } }
		* { box-sizing: border-box; }
		body { margin: 0; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--ink); }
		header { padding: 32px 16px 16px; text-align: center; background: linear-gradient(135deg, #0a192f 0%, #112240 60%, #064e3b 100%); color: #ffffff; }
		header h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -.02em; }
		header p { margin: 6px 0 0; color: #6ee7b7; font-size: 13px; font-weight: 500; }
		nav { position: sticky; top: 0; z-index: 5; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); border-bottom: 1px solid var(--line); overflow-x: auto; white-space: nowrap; padding: 10px 14px; }
		@media (prefers-color-scheme: dark) { nav { background: rgba(10, 25, 47, 0.9); } }
		nav a { display: inline-block; padding: 6px 14px; margin-right: 6px; border-radius: 999px; color: var(--ink); text-decoration: none; font-size: 13px; font-weight: 600; border: 1.5px solid var(--line); background: var(--card); box-shadow: 0 2px 4px rgba(0,0,0,0.03); transition: all 0.15s; }
		nav a:hover { border-color: var(--accent); color: var(--accent); }
		main { max-width: 760px; margin: 0 auto; padding: 16px 16px 48px; }
		section h2 { font-size: 18px; font-weight: 800; margin: 28px 0 12px; padding-bottom: 6px; border-bottom: 2.5px solid var(--accent); display: inline-block; scroll-margin-top: 60px; }
		.item { display: flex; gap: 14px; background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 14px; margin-bottom: 12px; box-shadow: 0 4px 12px -2px rgba(10, 25, 47, 0.05); transition: transform 0.2s, box-shadow 0.2s; scroll-margin-top: 60px; }
		.item:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -4px rgba(10, 25, 47, 0.1); }
		.item:target { outline: 2.5px solid var(--accent); }
		.item img { width: 90px; height: 90px; object-fit: cover; border-radius: 12px; flex-shrink: 0; }
		.item h3 { margin: 0; font-size: 16px; font-weight: 700; }
		.desc { color: var(--muted); font-size: 13px; margin: 4px 0 8px; }
		.meta { font-size: 12px; color: var(--muted); display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 6px; }
		.prices { display: flex; flex-wrap: wrap; gap: 8px 16px; }
		.price { font-weight: 800; color: var(--accent); font-size: 16px; }
		.price small { font-weight: 600; color: var(--muted); font-size: 13px; }
		.out { color: var(--out); font-size: 12px; font-weight: 700; text-transform: uppercase; margin-left: 4px; }
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
