<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {

	/** Maksimal login gagal per username+IP dalam jendela waktu di bawah. */
	const MAX_FAILED = 5;
	const LOCK_MINUTES = 15;

	public function __construct()
	{
		parent::__construct();
		$this->load->library('rbac');
		$this->load->library('audit_logger', NULL, 'audit');
		$this->load->model('User_model');
	}

	public function login()
	{
		if ($this->rbac->current_user())
		{
			redirect('dashboard');
		}

		$data = array('error' => NULL, 'username' => '', 'next' => $this->_safe_next($this->input->get('next')));

		if ($this->input->method() === 'post')
		{
			$username = trim((string) $this->input->post('username'));
			$password = (string) $this->input->post('password');
			$data['username'] = $username;
			$data['next'] = $this->_safe_next($this->input->post('next'));

			if ($username === '' OR $password === '')
			{
				$data['error'] = 'Username dan password wajib diisi.';
			}
			elseif ($this->_is_locked($username))
			{
				$data['error'] = 'Terlalu banyak percobaan login. Coba lagi dalam ' . self::LOCK_MINUTES . ' menit.';
				$this->audit->log('login', array('result' => 'denied', 'user_id' => NULL, 'username' => $username, 'detail' => 'locked'));
			}
			else
			{
				$user = $this->User_model->find_by_username($username);
				if ($user && $user['is_active'] && password_verify($password, $user['password_hash']))
				{
					if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT))
					{
						$this->User_model->set_password($user['id'], $password, (bool) $user['must_change_password']);
					}

					$this->session->sess_regenerate(TRUE);
					$this->session->set_userdata(array(
						'user_id'  => (int) $user['id'],
						'username' => $user['username'],
					));
					$this->User_model->touch_login($user['id']);
					$this->audit->log('login', array('table_name' => 'users', 'record_id' => $user['id']));

					redirect($user['must_change_password'] ? 'profile/password' : ($data['next'] ?: 'dashboard'));
				}

				$this->audit->log('login', array(
					'result'   => 'failed',
					'user_id'  => $user ? $user['id'] : NULL,
					'username' => $username,
					'detail'   => $user && ! $user['is_active'] ? 'user nonaktif' : 'kredensial salah',
				));
				$data['error'] = 'Username atau password salah, atau akun tidak aktif.';
			}
		}

		$this->load->view('auth/login', $data);
	}

	public function logout()
	{
		if ($this->session->userdata('user_id'))
		{
			$this->audit->log('logout');
		}
		$this->session->sess_destroy();
		redirect('login');
	}

	protected function _is_locked($username)
	{
		$since = date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60);
		$failed = $this->db->where('action', 'login')
			->where('result', 'failed')
			->where('username', $username)
			->where('ip_address', $this->input->ip_address())
			->where('created_at >=', $since)
			->count_all_results('audit_log');
		return $failed >= self::MAX_FAILED;
	}

	/** Hanya izinkan redirect ke path internal (cegah open redirect). */
	protected function _safe_next($next)
	{
		$next = trim((string) $next, " \t\n\r\0\x0B");
		if ($next === '' OR ! preg_match('#^[a-zA-Z0-9/_-]+$#', $next) OR strpos($next, '//') !== FALSE)
		{
			return '';
		}
		return ltrim($next, '/');
	}
}
