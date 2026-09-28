<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller {

	public function index()
	{
		$data = array('title' => 'Dashboard');

		if (can('admin.users'))
		{
			$data['stats'] = array(
				'users_active' => $this->db->where('is_active', 1)->count_all_results('users'),
				'roles'        => $this->db->count_all('roles'),
				'logins_today' => $this->db->where('action', 'login')->where('result', 'success')
					->where('created_at >=', date('Y-m-d 00:00:00'))->count_all_results('audit_log'),
				'denied_today' => $this->db->where_in('result', array('denied', 'failed'))
					->where('created_at >=', date('Y-m-d 00:00:00'))->count_all_results('audit_log'),
			);
		}

		if (can('inventory.view'))
		{
			$this->load->model('Stock_model');
			$data['stock_alerts'] = $this->Stock_model->alert_counts();
			$data['stock_totals'] = $this->Stock_model->totals();
		}

		if (can('purchase.view'))
		{
			$this->load->model('Purchase_model');
			$data['purchase'] = $this->Purchase_model->pending_counts();
		}

		if (can_any(array('sales.process', 'report.operational', 'report.financial')))
		{
			$today = date('Y-m-d');
			$data['sales_today'] = $this->db->query(
				"SELECT COUNT(*) AS tx, COALESCE(SUM(total), 0) AS revenue, COALESCE(SUM(cogs_total), 0) AS cogs
				 FROM orders WHERE status = 'paid' AND paid_at >= ? AND paid_at <= ?",
				array($today . ' 00:00:00', $today . ' 23:59:59')
			)->row_array();
			$data['sales_today']['open'] = $this->db->where('status', 'open')->count_all_results('orders');
			$data['sales_today']['pending_shifts'] = can('sales.shift_approve') ? $this->db->where('status', 'pending_approval')->count_all_results('shifts') : 0;
			$data['top_today'] = $this->db->query(
				"SELECT oi.name, SUM(oi.qty) AS qty FROM order_items oi JOIN orders o ON o.id = oi.order_id
				 WHERE o.status = 'paid' AND o.paid_at >= ? AND oi.kitchen_status != 'void' GROUP BY oi.name ORDER BY qty DESC LIMIT 5",
				array($today . ' 00:00:00')
			)->result_array();
		}

		$this->render('dashboard/index', $data);
	}
}
