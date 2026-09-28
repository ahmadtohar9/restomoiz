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

		$this->render('dashboard/index', $data);
	}
}
