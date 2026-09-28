<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Laporan & dashboard analitik (PRD 2.5).
 *
 *  reports             dashboard eksekutif (2.5.1)
 *  reports/pnl         laba rugi (2.5.2)            - report.financial
 *  reports/sales       rincian pendapatan (2.5.3 #1)
 *  reports/cashiers    kinerja kasir (2.5.3 #4)
 *  reports/refunds     analisis refund (2.5.3 #5)
 * Margin per menu ada di menu/analysis; inventory & pembelian di modulnya.
 */
class Reports extends MY_Controller {

	public static $presets = array(
		'today'      => 'Hari ini',
		'yesterday'  => 'Kemarin',
		'7d'         => '7 hari',
		'30d'        => '30 hari',
		'this_month' => 'Bulan ini',
		'last_month' => 'Bulan lalu',
	);

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('report.operational', 'report.financial')))
		{
			$this->require_permission('report.operational');
		}
		$this->load->model('Report_model');
		// Label metode bayar, hari, tipe pesanan dipakai di view & ekspor.
		$this->load->library('pos_service', NULL, 'pos');
	}

	public function index()
	{
		list($from, $to, $preset) = $this->_range('7d');
		list($pf, $pt) = Report_model::previous_range($from, $to);
		$this->load->model(array('Stock_model', 'Purchase_model'));
		$alerts = $this->Stock_model->alert_counts();
		$purchase = $this->Purchase_model->pending_counts();

		$this->render('reports/dashboard', array(
			'title'   => 'Dashboard Eksekutif',
			'from'    => $from, 'to' => $to, 'preset' => $preset,
			'cur'     => $this->Report_model->summary($from, $to),
			'prev'    => $this->Report_model->summary($pf, $pt),
			'prev_range' => array($pf, $pt),
			'daily'   => $this->Report_model->daily($from, $to),
			'items'   => $this->Report_model->by_item($from, $to, 8),
			'payment' => $this->Report_model->by_payment($from, $to),
			'alerts'  => array(
				'low_stock' => $alerts['low'] + $alerts['reorder'],
				'expiring'  => $alerts['expiring'],
				'po'        => $purchase['approval_1'] + $purchase['approval_2'],
				'refunds'   => $this->db->where('status', 'pending_approval')->count_all_results('order_refunds'),
				'overdue'   => $purchase['overdue'],
			),
		));
	}

	public function pnl()
	{
		$this->require_permission('report.financial');
		$period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $this->input->get('period')) ? $this->input->get('period') : date('Y-m');
		$from = $period . '-01';
		$to = date('Y-m-t', strtotime($from));
		$prev_period = date('Y-m', strtotime($from . ' -1 month'));
		$pf = $prev_period . '-01';
		$pt = date('Y-m-t', strtotime($pf));
		$cur = $this->Report_model->profit_loss($from, min($to, date('Y-m-d')));
		$prev = $this->Report_model->profit_loss($pf, $pt);

		if ($this->input->get('export'))
		{
			$rows = array();
			$add = function ($label, $a, $b) use (&$rows) { $rows[] = array($label, round($a, 2), round($b, 2)); };
			foreach ($cur['categories'] as $k => $c)
			{
				$add('Penjualan - ' . $c['name'], $c['revenue'], isset($prev['categories'][$k]) ? $prev['categories'][$k]['revenue'] : 0);
			}
			$add('Service charge', $cur['service'], $prev['service']);
			$add('Refund', -$cur['refunds'], -$prev['refunds']);
			$add('Total pendapatan', $cur['revenue'], $prev['revenue']);
			$add('COGS menu terjual', $cur['cogs_items'], $prev['cogs_items']);
			$add('Bahan terbuang & penyesuaian stok', $cur['waste_total'], $prev['waste_total']);
			$add('Total COGS', $cur['cogs_total'], $prev['cogs_total']);
			$add('Laba kotor', $cur['gross_profit'], $prev['gross_profit']);
			foreach ($cur['opex'] as $k => $v)
			{
				$add(Report_model::$expense_categories[$k], $v, $prev['opex'][$k]);
			}
			$add('Total beban operasional', $cur['opex_total'], $prev['opex_total']);
			$add('EBIT (laba operasional)', $cur['ebit'], $prev['ebit']);
			$add('Bunga & beban lain', $cur['other'], $prev['other']);
			$add('Laba bersih', $cur['net'], $prev['net']);
			send_csv('laba-rugi-' . $period . '.csv', array('Pos', $period, $prev_period), $rows);
		}

		$this->render('reports/pnl', array(
			'title'  => 'Laporan Laba Rugi',
			'period' => $period, 'prev_period' => $prev_period,
			'cur'    => $cur, 'prev' => $prev,
			'partial'=> $to > date('Y-m-d'),
		));
	}

	public function sales()
	{
		list($from, $to, $preset) = $this->_range('30d');
		$data = array(
			'items'    => $this->Report_model->by_item($from, $to),
			'category' => $this->Report_model->by_category($from, $to),
			'payment'  => $this->Report_model->by_payment($from, $to),
			'types'    => $this->Report_model->by_order_type($from, $to),
			'daypart'  => $this->Report_model->by_daypart($from, $to),
			'heat'     => $this->Report_model->heatmap($from, $to),
			'promos'   => $this->Report_model->promo_usage($from, $to),
			'summary'  => $this->Report_model->summary($from, $to),
		);
		if ($x = $this->input->get('export'))
		{
			$this->_export_sales($x, $data, $from, $to);
		}
		$this->render('reports/sales', array_merge($data, array('title' => 'Laporan Penjualan', 'from' => $from, 'to' => $to, 'preset' => $preset)));
	}

	public function cashiers()
	{
		list($from, $to, $preset) = $this->_range('30d');
		$rows = $this->Report_model->cashiers($from, $to);
		if ($this->input->get('export'))
		{
			send_csv('kinerja-kasir-' . $from . '_' . $to . '.csv',
				array('Kasir', 'Transaksi', 'Penjualan', 'Rata-rata Bill', 'Refund Diajukan', 'Nilai Refund', 'Refund Rate %', 'Selisih Kas', 'Shift', 'Jam Tersibuk'),
				array_map(function ($r) {
					return array($r['name'], $r['tx'], round($r['total'], 2), $r['tx'] ? round($r['total'] / $r['tx'], 2) : 0, $r['refunds'], round($r['refund_total'], 2),
						$r['tx'] ? round($r['refunds'] / $r['tx'] * 100, 1) : 0, round($r['cash_variance'], 2), $r['shifts'], $r['peak_hour'] !== NULL ? $r['peak_hour'] . ':00' : '');
				}, $rows));
		}
		$this->render('reports/cashiers', array('title' => 'Kinerja Kasir', 'rows' => $rows, 'from' => $from, 'to' => $to, 'preset' => $preset));
	}

	public function refunds()
	{
		list($from, $to, $preset) = $this->_range('30d');
		$data = $this->Report_model->refunds($from, $to);
		$summary = $this->Report_model->summary($from, $to);
		if ($this->input->get('export'))
		{
			$this->load->library('refund_service', NULL, 'refund');
			send_csv('analisis-refund-' . $from . '_' . $to . '.csv', array('Alasan', 'Jumlah Pengajuan', 'Nilai Refund Selesai'),
				array_map(function ($r) { return array(Refund_service::$reasons[$r['reason_code']], $r['n'], round($r['total'], 2)); }, $data['by_reason']));
		}
		$this->load->library('refund_service', NULL, 'refund');
		$this->render('reports/refunds', array_merge($data, array('title' => 'Analisis Refund', 'summary' => $summary, 'from' => $from, 'to' => $to, 'preset' => $preset)));
	}

	protected function _export_sales($what, array $d, $from, $to)
	{
		$name = 'penjualan-' . $what . '-' . $from . '_' . $to . '.csv';
		switch ($what)
		{
			case 'items':
				send_csv($name, array('Menu', 'Qty', 'Penjualan Bersih', 'COGS', 'Laba Kotor', 'Margin %', 'Qty Refund'), array_map(function ($r) {
					return array($r['name'], $r['qty'], round($r['revenue'], 2), round($r['cogs'], 2), round($r['profit'], 2), $r['margin'] !== NULL ? round($r['margin'], 1) : '', $r['refunded_qty']);
				}, $d['items']));
			case 'category':
				send_csv($name, array('Kategori', 'Qty', 'Penjualan Bersih', 'COGS'), array_map(function ($r) {
					return array($r['name'], $r['qty'], round($r['revenue'], 2), round($r['cogs'], 2));
				}, array_values($d['category'])));
			case 'payment':
				send_csv($name, array('Metode', 'Transaksi', 'Total'), array_map(function ($r) {
					return array(isset(Promo_engine::$payment_methods[$r['k']]) ? Promo_engine::$payment_methods[$r['k']] : $r['k'], $r['tx'], round($r['total'], 2));
				}, $d['payment']));
			case 'promos':
				send_csv($name, array('Promo', 'Dipakai', 'Total Diskon', 'Total Transaksi', 'Rata-rata Bill'), array_map(function ($r) {
					return array($r['name'], $r['used'], round($r['discount'], 2), round($r['order_total'], 2), round($r['avg_bill'], 2));
				}, $d['promos']));
			case 'hours':
				$rows = array();
				foreach ($d['heat'] as $dow => $hours)
				{
					foreach ($hours as $h => $v)
					{
						$rows[] = array(Promo_engine::$days[$dow], sprintf('%02d:00', $h), $v['tx'], round($v['total'], 2));
					}
				}
				send_csv($name, array('Hari', 'Jam', 'Transaksi', 'Total'), $rows);
		}
		show_404();
	}

	/** Rentang dari ?preset= atau ?from=&to= (maks. 366 hari). */
	protected function _range($default)
	{
		$preset = (string) $this->input->get('preset');
		$from = (string) $this->input->get('from');
		$to = (string) $this->input->get('to');
		$valid = function ($d) { $x = DateTime::createFromFormat('Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; };
		if ($preset === '' && $valid($from) && $valid($to) && $from <= $to)
		{
			if ((strtotime($to) - strtotime($from)) / 86400 > 366)
			{
				$from = date('Y-m-d', strtotime($to . ' -366 days'));
			}
			return array($from, $to, 'custom');
		}
		$preset = isset(self::$presets[$preset]) ? $preset : $default;
		$today = date('Y-m-d');
		switch ($preset)
		{
			case 'today':      return array($today, $today, $preset);
			case 'yesterday':  $y = date('Y-m-d', strtotime('-1 day')); return array($y, $y, $preset);
			case '7d':         return array(date('Y-m-d', strtotime('-6 days')), $today, $preset);
			case '30d':        return array(date('Y-m-d', strtotime('-29 days')), $today, $preset);
			case 'this_month': return array(date('Y-m-01'), $today, $preset);
			case 'last_month': return array(date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month')), $preset);
		}
	}
}
