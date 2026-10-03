<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Analisis margin menu (PRD 2.3.2 margin per menu & 2.5.3 Margin Analysis):
 * COGS, margin, perubahan COGS dibanding 30 hari lalu, dan break-even
 * harga untuk mencapai target margin.
 */
class Analysis extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('menu.view_cogs');
		$this->load->model('Menu_model');
		$this->load->library('menu_service');
	}

	public function index()
	{
		$this->menu_service->ensure_daily_snapshot();
		$menus = $this->Menu_model->all(array());
		$ids = array();
		$meta = array();
		foreach ($menus as $m)
		{
			foreach ($m['variants'] as $v)
			{
				$ids[] = $v['id'];
				$meta[$v['id']] = array('menu' => $m, 'variant' => $v);
			}
		}
		$info = $this->menu_service->variant_info($ids);

		// COGS ~30 hari lalu (snapshot terdekat sebelum/tepat tanggal itu).
		$past = array();
		if ($ids)
		{
			$rows = $this->db->query(
				'SELECT h.variant_id, h.cogs FROM menu_cogs_history h
				 JOIN (SELECT variant_id, MAX(recorded_on) AS d FROM menu_cogs_history WHERE recorded_on <= ? AND variant_id IN ? GROUP BY variant_id) x
				   ON x.variant_id = h.variant_id AND x.d = h.recorded_on',
				array(date('Y-m-d', strtotime('-30 days')), $ids)
			)->result_array();
			$past = array_column($rows, 'cogs', 'variant_id');
		}

		$warn = (float) setting('menu_margin_warning', 40);
		$rows = array();
		foreach ($info as $vid => $i)
		{
			if ( ! $i['has_cogs'] OR ! $i['price'])
			{
				continue;
			}
			$old = isset($past[$vid]) ? (float) $past[$vid] : NULL;
			$rows[] = array(
				'menu'       => $meta[$vid]['menu'],
				'variant'    => $meta[$vid]['variant'],
				'price'      => $i['price'],
				'cogs'       => $i['cogs'],
				'profit'     => $i['price'] - $i['cogs'],
				'margin'     => $i['margin_pct'],
				'cogs_change'=> $old !== NULL && $old > 0 ? ($i['cogs'] - $old) / $old * 100 : NULL,
				// Harga minimal agar margin = target: cogs / (1 - target%)
				'target_price' => $warn < 100 ? ceil($i['cogs'] / (1 - $warn / 100) / 100) * 100 : NULL,
				'missing_cost' => $i['cogs_source'] === 'resep' ? $i['missing_cost'] : 0,
				'cogs_source'  => $i['cogs_source'],
			);
		}
		usort($rows, function ($a, $b) { return $a['margin'] <=> $b['margin']; });

		$no_recipe = array();
		foreach ($info as $vid => $i)
		{
			if ( ! $i['has_cogs'] && $meta[$vid]['variant']['is_active'] && $meta[$vid]['menu']['status'] !== 'inactive')
			{
				$no_recipe[] = $meta[$vid];
			}
		}

		if ($this->input->get('export'))
		{
			$csv = array();
			foreach ($rows as $r)
			{
				$csv[] = array($r['menu']['code'], $r['menu']['name'], $r['variant']['name'], $r['price'], $r['cogs'], round($r['profit'], 2),
					$r['margin'], $r['cogs_change'] !== NULL ? round($r['cogs_change'], 1) : '', $r['target_price']);
			}
			send_csv('analisis-margin-menu-' . date('Ymd') . '.csv',
				array('Kode', 'Menu', 'Varian', 'Harga Jual', 'COGS', 'Laba Kotor', 'Margin %', 'Perubahan COGS 30 Hari %', "Harga untuk Margin $warn%"), $csv);
		}

		$this->render('menu/analysis/index', array(
			'title'     => 'Analisis Margin Menu',
			'rows'      => $rows,
			'no_recipe' => $no_recipe,
			'warn'      => $warn,
		));
	}
}
