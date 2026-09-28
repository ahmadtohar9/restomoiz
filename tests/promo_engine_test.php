<?php
/**
 * Tes regresi mesin promo (tanpa database).
 * Jalankan: php tests/promo_engine_test.php   (exit code 1 kalau ada yang gagal)
 */
if (PHP_SAPI !== 'cli') exit;
define('BASEPATH', 1);
function &get_instance() { static $x = NULL; return $x; }
require __DIR__ . '/../application/helpers/app_helper.php';
require __DIR__ . '/../application/libraries/Promo_engine.php';
$e = new Promo_engine();
function P($o) { return array_merge(array('id'=>rand(1,99999),'name'=>'','promo_code'=>NULL,'type'=>'percent','value'=>0,'buy_qty'=>NULL,'get_qty'=>NULL,'bundle_price'=>NULL,
  'scope'=>'all','min_purchase'=>0,'min_qty'=>0,'payment_methods'=>NULL,'member_only'=>0,'max_discount'=>NULL,'stackable'=>0,'max_usage_total'=>NULL,
  'max_usage_per_customer'=>NULL,'usage_count'=>0,'days_of_week'=>NULL,'time_start'=>NULL,'time_end'=>NULL,'items'=>array()), $o); }
$nasi = array('variant_id'=>1,'menu_id'=>1,'category_ids'=>array(10,1),'qty'=>2,'unit_price'=>25000,'name'=>'Nasi Kuning');
$teh  = array('variant_id'=>2,'menu_id'=>2,'category_ids'=>array(20),'qty'=>1,'unit_price'=>12000,'name'=>'Teh Iced');
$des  = array('variant_id'=>3,'menu_id'=>3,'category_ids'=>array(30),'qty'=>1,'unit_price'=>15000,'name'=>'Dessert');
$cart = array($nasi, $teh, $des); // subtotal 77.000
$ctx = array('at'=>'2026-09-30 15:30:00'); // Rabu
$GLOBALS['fail'] = 0;
function show($t, $r) {
  preg_match('/harap ([0-9]+)/', $t, $m);
  $ok = isset($m[1]) ? (int) $r['total_discount'] === (int) $m[1] : TRUE;
  if ( ! $ok) $GLOBALS['fail']++;
  echo ($ok ? 'OK   ' : 'GAGAL'), ' ', str_pad($t, 52), ' diskon=', $r['total_discount'], "\n";
}
show('1. Fixed 5rb, min belanja 50rb (harap 5000)', $e->evaluate($cart, $ctx, array(P(array('name'=>'A','type'=>'fixed','value'=>5000,'min_purchase'=>50000)))));
show('2. Fixed 5rb, min belanja 100rb (harap 0)', $e->evaluate($cart, $ctx, array(P(array('name'=>'A','type'=>'fixed','value'=>5000,'min_purchase'=>100000)))));
show('3. 20% semua (harap 15400)', $e->evaluate($cart, $ctx, array(P(array('name'=>'B','type'=>'percent','value'=>20)))));
show('4. 20% maks 10rb (harap 10000)', $e->evaluate($cart, $ctx, array(P(array('name'=>'B','type'=>'percent','value'=>20,'max_discount'=>10000)))));
show('5. Kategori induk 1 -10% (harap 5000)', $e->evaluate($cart, $ctx, array(P(array('name'=>'C','type'=>'percent','value'=>10,'scope'=>'category','items'=>array(array('item_type'=>'category','item_id'=>1,'qty'=>1)))))));
$c3 = array(array_merge($nasi, array('qty'=>3)));
show('6. Beli 2 gratis 1, 3 nasi (harap 25000)', $e->evaluate($c3, $ctx, array(P(array('name'=>'D','type'=>'buy_get','buy_qty'=>2,'get_qty'=>1)))));
$mix = array(array_merge($nasi, array('qty'=>2)), $des);
show('7. B2G1 nasi2+dessert (gratis termurah 15000)', $e->evaluate($mix, $ctx, array(P(array('name'=>'D','type'=>'buy_get','buy_qty'=>2,'get_qty'=>1)))));
show('8. Paket N+T+D=35rb (normal 52rb, harap 17000)', $e->evaluate($cart, $ctx, array(P(array('name'=>'E','type'=>'bundle','bundle_price'=>35000,
  'items'=>array(array('item_type'=>'variant','item_id'=>1,'qty'=>1),array('item_type'=>'variant','item_id'=>2,'qty'=>1),array('item_type'=>'variant','item_id'=>3,'qty'=>1)))))));
show('9. Happy hour 15-17 (harap 7700)', $e->evaluate($cart, $ctx, array(P(array('name'=>'F','type'=>'percent','value'=>10,'time_start'=>'15:00:00','time_end'=>'17:00:00')))));
show('10. Happy hour 18-20 (harap 0)', $e->evaluate($cart, $ctx, array(P(array('name'=>'F','type'=>'percent','value'=>10,'time_start'=>'18:00:00','time_end'=>'20:00:00')))));
show('11. Malam 22-02 jam 01:00 (harap 7700)', $e->evaluate($cart, array('at'=>'2026-09-30 01:00:00'), array(P(array('name'=>'F','type'=>'percent','value'=>10,'time_start'=>'22:00:00','time_end'=>'02:00:00')))));
show('12. Weekend saja, Rabu (harap 0)', $e->evaluate($cart, $ctx, array(P(array('name'=>'G','type'=>'percent','value'=>10,'days_of_week'=>'6,7')))));
show('13. Khusus debit, bayar cash (harap 0)', $e->evaluate($cart, $ctx + array('payment_method'=>'cash'), array(P(array('name'=>'H','type'=>'percent','value'=>10,'payment_methods'=>'debit')))));
show('14. Kode PROMO10 tanpa kode (harap 0)', $e->evaluate($cart, $ctx, array(P(array('name'=>'I','type'=>'percent','value'=>10,'promo_code'=>'PROMO10')))));
show('15. Kode promo10 diinput (harap 7700)', $e->evaluate($cart, $ctx + array('codes'=>array('promo10')), array(P(array('name'=>'I','type'=>'percent','value'=>10,'promo_code'=>'PROMO10')))));
show('16. Stack 10%+5rb vs solo 20% (harap 15400 solo)', $e->evaluate($cart, $ctx, array(
  P(array('name'=>'S1','type'=>'percent','value'=>10,'stackable'=>1)), P(array('name'=>'S2','type'=>'fixed','value'=>5000,'stackable'=>1)), P(array('name'=>'Solo','type'=>'percent','value'=>20)))));
show('17. Stack 15%+5rb vs solo 20% (harap 16550 stack)', $e->evaluate($cart, $ctx, array(
  P(array('name'=>'S1','type'=>'percent','value'=>15,'stackable'=>1)), P(array('name'=>'S2','type'=>'fixed','value'=>5000,'stackable'=>1)), P(array('name'=>'Solo','type'=>'percent','value'=>20)))));
show('18. Kuota habis (harap 0)', $e->evaluate($cart, $ctx, array(P(array('name'=>'J','type'=>'percent','value'=>10,'max_usage_total'=>5,'usage_count'=>5)))));
show('19. Member only non-member (harap 0)', $e->evaluate($cart, $ctx, array(P(array('name'=>'K','type'=>'percent','value'=>10,'member_only'=>1)))));
show('20. Fixed 5rb per porsi menu 1 (2 nasi, harap 10000)', $e->evaluate($cart, $ctx, array(P(array('name'=>'L','type'=>'fixed','value'=>5000,'scope'=>'menu','items'=>array(array('item_type'=>'menu','item_id'=>1,'qty'=>1)))))));
$r = $e->evaluate($cart, $ctx, array(P(array('name'=>'M','type'=>'fixed','value'=>5000))));
show('21. Pembulatan 5rb dibagi 3 baris (harap 5000)', $r);
show('22. Min 3 item kategori 1, cuma 2 (harap 0)', $e->evaluate($cart, $ctx, array(P(array('name'=>'N','type'=>'percent','value'=>10,'scope'=>'category','min_qty'=>3,'items'=>array(array('item_type'=>'category','item_id'=>1,'qty'=>1)))))));
show('23. 100% + fixed stack tidak melebihi total (harap 77000)', $e->evaluate($cart, $ctx, array(P(array('name'=>'O','type'=>'percent','value'=>100,'stackable'=>1)), P(array('name'=>'P','type'=>'fixed','value'=>5000,'stackable'=>1)))));

echo $GLOBALS['fail'] ? "\n{$GLOBALS['fail']} tes GAGAL\n" : "\nSemua tes lulus\n";
exit($GLOBALS['fail'] ? 1 : 0);
