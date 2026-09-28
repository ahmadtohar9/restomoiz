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

		$this->render('dashboard/index', $data);
	}
}
