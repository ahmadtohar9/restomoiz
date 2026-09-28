<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Laporan pembelian (PRD 2.2.5).
 */
class Reports extends MY_Controller {

	protected $tabs = array(
		'supplier'    => 'Belanja per Supplier',
		'category'    => 'Belanja per Kategori',
		'price'       => 'Tren Harga',
		'performance' => 'Kinerja Supplier',
		'aging'       => 'Aging Hutang',
	);

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('report.inventory');
		$this->load->model('Purchase_model');
	}

	public function index($tab = 'supplier')
	{
		if ( ! isset($this->tabs[$tab]))
		{
			show_404();
		}
		$date = function ($v, $d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : $d; };
		$from = $date($this->input->get('from'), date('Y-m-01'));
		$to = $date($this->input->get('to'), date('Y-m-d'));

		switch ($tab)
		{
			case 'supplier':    $data = array('rows' => $this->Purchase_model->spend_by_supplier($from, $to)); break;
			case 'category':    $data = array('rows' => $this->Purchase_model->spend_by_category($from, $to)); break;
			case 'price':       $data = $this->Purchase_model->price_trend(6); break;
			case 'performance': $data = array('rows' => $this->Purchase_model->supplier_performance($from, $to)); break;
			case 'aging':       $data = array('rows' => $this->Purchase_model->ap_aging()); break;
		}

		if ($this->input->get('export'))
		{
			$this->_export($tab, $data, $from, $to);
		}

		$this->render('purchase/reports/index', array_merge($data, array(
			'title' => 'Laporan Pembelian',
			'tab'   => $tab,
			'tabs'  => $this->tabs,
			'from'  => $from,
			'to'    => $to,
		)));
	}

	protected function _export($tab, array $data, $from, $to)
	{
		$rows = array();
		switch ($tab)
		{
			case 'supplier':
				$header = array('Supplier', 'Jumlah PO', 'Jumlah Penerimaan', 'Total Belanja');
				foreach ($data['rows'] as $r) { $rows[] = array($r['name'], $r['po_count'], $r['gr_count'], round($r['total'], 2)); }
				break;
			case 'category':
				$header = array('Kategori', 'Jumlah Bahan', 'Total Belanja');
				foreach ($data['rows'] as $r) { $rows[] = array($r['name'], $r['items'], round($r['total'], 2)); }
				break;
			case 'price':
				$header = array_merge(array('Kode', 'Bahan', 'Satuan'), $data['labels'], array('Perubahan %'));
				foreach ($data['items'] as $it)
				{
					$line = array($it['code'], $it['name'], $it['unit']);
					foreach ($data['labels'] as $ym) { $line[] = isset($it['months'][$ym]) ? round($it['months'][$ym], 2) : ''; }
					$line[] = round($it['change'], 1);
					$rows[] = $line;
				}
				break;
			case 'performance':
				$header = array('Supplier', 'Jumlah PO', 'Tepat Waktu %', 'Fill Rate %', 'Lead Time Rata-rata (hari)', 'Skor Kualitas', 'Total PO');
				foreach ($data['rows'] as $r)
				{
					$rows[] = array($r['name'], $r['po_count'], $r['with_date'] ? round($r['on_time'] / $r['with_date'] * 100, 1) : '',
						$r['ordered'] > 0 ? round($r['received'] / $r['ordered'] * 100, 1) : '', round($r['avg_lead'], 1), $r['quality_score'], round($r['total'], 2));
				}
				break;
			case 'aging':
				$header = array('Supplier', 'Belum Jatuh Tempo', '1-30 Hari', '31-60 Hari', '61-90 Hari', '>90 Hari', 'Total', 'Jumlah Invoice');
				foreach ($data['rows'] as $r)
				{
					$rows[] = array($r['name'], round($r['current_due'], 2), round($r['d1_30'], 2), round($r['d31_60'], 2), round($r['d61_90'], 2), round($r['d90'], 2), round($r['total'], 2), $r['invoices']);
				}
				break;
		}
		send_csv('laporan-pembelian-' . $tab . '-' . $from . '_' . $to . '.csv', $header, $rows);
	}
}
