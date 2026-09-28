<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Angka yang seharusnya diterima (menurut POS) untuk settlement harian
 * dan rekonsiliasi bank bulanan (PRD 2.4.5).
 *
 * Refund tunai mengurangi kas; refund "metode asal" mengurangi kanal
 * pembayaran transaksi aslinya (kartu / e-wallet).
 */
class Settlement_model extends CI_Model {

	/** @return array cash, debit, credit, ewallet, refund_cash, refund_card, refund_ewallet, expected_*, tx, shift_variance */
	public function expected($from, $to)
	{
		$f = $from . ' 00:00:00';
		$t = $to . ' 23:59:59';
		$sales = array_column($this->db->query(
			"SELECT payment_method, SUM(total) AS total, COUNT(*) AS n FROM orders
			 WHERE status = 'paid' AND paid_at BETWEEN ? AND ? GROUP BY payment_method",
			array($f, $t)
		)->result_array(), NULL, 'payment_method');

		$refunds = $this->db->query(
			"SELECT r.refund_method, o.payment_method, SUM(r.refund_amount) AS total FROM order_refunds r JOIN orders o ON o.id = r.order_id
			 WHERE r.status = 'completed' AND r.completed_at BETWEEN ? AND ? GROUP BY r.refund_method, o.payment_method",
			array($f, $t)
		)->result_array();

		$v = function ($m) use ($sales) { return isset($sales[$m]) ? (float) $sales[$m]['total'] : 0.0; };
		$out = array('cash' => $v('cash'), 'debit' => $v('debit'), 'credit' => $v('credit'), 'ewallet' => $v('ewallet'),
			'refund_cash' => 0.0, 'refund_card' => 0.0, 'refund_ewallet' => 0.0);
		foreach ($refunds as $r)
		{
			if ($r['refund_method'] === 'cash')
			{
				$out['refund_cash'] += (float) $r['total'];
			}
			elseif ($r['payment_method'] === 'ewallet')
			{
				$out['refund_ewallet'] += (float) $r['total'];
			}
			else
			{
				$out['refund_card'] += (float) $r['total'];
			}
		}
		$out['tx'] = array_sum(array_map(function ($s) { return (int) $s['n']; }, $sales));
		$out['expected_cash'] = round($out['cash'] - $out['refund_cash'], 2);
		$out['expected_card'] = round($out['debit'] + $out['credit'] - $out['refund_card'], 2);
		$out['expected_ewallet'] = round($out['ewallet'] - $out['refund_ewallet'], 2);
		$out['expected_total'] = $out['expected_cash'] + $out['expected_card'] + $out['expected_ewallet'];
		$out['shift_variance'] = (float) $this->db->query(
			"SELECT COALESCE(SUM(variance), 0) AS v FROM shifts WHERE status = 'closed' AND closed_at BETWEEN ? AND ?", array($f, $t)
		)->row()->v;
		$out['open_shifts'] = (int) $this->db->query(
			"SELECT COUNT(*) AS n FROM shifts WHERE status != 'closed' AND opened_at <= ?", array($t)
		)->row()->n;
		return $out;
	}

	/** Shift yang ditutup pada tanggal itu (untuk review settlement). */
	public function shifts_on($date)
	{
		return $this->db->select('s.*, u.name AS user_name,
				(SELECT COALESCE(SUM(total), 0) FROM orders o WHERE o.shift_id = s.id AND o.status = \'paid\') AS sales', FALSE)
			->from('shifts s')->join('users u', 'u.id = s.user_id')
			->group_start()->where('DATE(s.opened_at)', $date)->or_where('DATE(s.closed_at)', $date)->group_end()
			->order_by('s.opened_at')->get()->result_array();
	}

	/** Settlement harian dalam satu bulan beserta hari yang belum dibuat. */
	public function month_days($period)
	{
		$start = $period . '-01';
		$end = date('Y-m-t', strtotime($start));
		$rows = array_column($this->db->where('settle_date >=', $start)->where('settle_date <=', $end)->get('daily_settlements')->result_array(), NULL, 'settle_date');
		$sales = array_column($this->db->query(
			"SELECT DATE(paid_at) AS d, COUNT(*) AS n, SUM(total) AS total FROM orders WHERE status = 'paid' AND paid_at BETWEEN ? AND ? GROUP BY DATE(paid_at)",
			array($start . ' 00:00:00', $end . ' 23:59:59')
		)->result_array(), NULL, 'd');
		$days = array();
		for ($d = $start; $d <= $end && $d <= date('Y-m-d'); $d = date('Y-m-d', strtotime($d . ' +1 day')))
		{
			$days[$d] = array(
				'date'       => $d,
				'settlement' => isset($rows[$d]) ? $rows[$d] : NULL,
				'tx'         => isset($sales[$d]) ? (int) $sales[$d]['n'] : 0,
				'sales'      => isset($sales[$d]) ? (float) $sales[$d]['total'] : 0.0,
			);
		}
		return array_reverse($days, TRUE);
	}
}
