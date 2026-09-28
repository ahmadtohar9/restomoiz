<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Laporan inventory (PRD 2.1.3 Reports & 2.5.3 Inventory Report).
 */
class Reports extends MY_Controller {

	protected $tabs = array(
		'value'  => 'Nilai Stok per Kategori',
		'aging'  => 'Aging Stok',
		'slow'   => 'Slow-moving',
		'dead'   => 'Dead Stock',
	);

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('report.inventory');
		$this->load->model('Stock_model');
	}

	public function index($tab = 'value')
	{
		if ( ! isset($this->tabs[$tab]))
		{
			show_404();
		}
		$data = $this->_data($tab);

		if ($this->input->get('export'))
		{
			$this->_export($tab, $data);
		}

		$this->render('inventory/reports/index', array_merge($data, array(
			'title' => 'Laporan Inventory',
			'tab'   => $tab,
			'tabs'  => $this->tabs,
			'totals' => $this->Stock_model->totals(),
		)));
	}

	protected function _data($tab)
	{
		switch ($tab)
		{
			case 'value':
				return array('rows' => $this->Stock_model->value_by_category());
			case 'aging':
				return array('rows' => $this->Stock_model->aging());
			case 'slow':
				$days = (int) setting('slow_moving_days', 30);
				$dead = (int) setting('dead_stock_days', 60);
				$rows = array_filter($this->Stock_model->not_moving($days), function ($r) use ($dead) {
					return (int) $r['idle_days'] < $dead;
				});
				return array('rows' => $rows, 'days' => $days, 'dead_days' => $dead);
			case 'dead':
				$days = (int) setting('dead_stock_days', 60);
				return array('rows' => $this->Stock_model->not_moving($days), 'days' => $days);
		}
	}

	protected function _export($tab, array $data)
	{
		$rows = array();
		switch ($tab)
		{
			case 'value':
				$header = array('Kategori', 'Jumlah Bahan', 'Ada Stok', 'Nilai Stok');
				foreach ($data['rows'] as $r)
				{
					$rows[] = array($r['name'], $r['items'], $r['in_stock'], round($r['value'], 2));
				}
				break;
			case 'aging':
				$header = array('Kode', 'Bahan', 'Qty', 'Satuan', '0-30 hari', '31-60 hari', '61-90 hari', '>90 hari', 'Total', 'Batch Tertua');
				foreach ($data['rows'] as $r)
				{
					$rows[] = array($r['code'], $r['name'], $r['qty'], $r['unit'], round($r['v0_30'], 2), round($r['v31_60'], 2), round($r['v61_90'], 2), round($r['v90'], 2), round($r['total'], 2), $r['oldest']);
				}
				break;
			default:
				$header = array('Kode', 'Bahan', 'Stok', 'Satuan', 'Nilai Stok', 'Hari Tidak Bergerak', 'Keluar Terakhir', 'Pemakaian 90 Hari');
				foreach ($data['rows'] as $r)
				{
					$rows[] = array($r['code'], $r['name'], $r['qty_on_hand'], $r['unit'], $r['stock_value'], $r['idle_days'], $r['last_out_at'], $r['out_90d']);
				}
		}
		send_csv('laporan-inventory-' . $tab . '-' . date('Ymd') . '.csv', $header, $rows);
	}
}
