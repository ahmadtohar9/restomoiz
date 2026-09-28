<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Profile extends MY_Controller {

	protected $allow_during_password_change = TRUE;

	public function __construct()
	{
		parent::__construct();
		$this->load->model(array('User_model', 'Permission_model'));
	}

	public function index()
	{
		if ($this->current_user['must_change_password'])
		{
			redirect('profile/password');
		}
		$this->render('profile/index', array(
			'title'       => 'Profil Saya',
			'permissions' => $this->rbac->permissions(),
			'is_super'    => $this->rbac->is_super(),
			'custom'      => $this->User_model->custom_permissions($this->current_user['id']),
		));
	}

	public function password()
	{
		$forced = (bool) $this->current_user['must_change_password'];
		$errors = array();

		if ($this->input->method() === 'post')
		{
			$current = (string) $this->input->post('current_password');
			$new = (string) $this->input->post('new_password');
			$confirm = (string) $this->input->post('confirm_password');

			$user = $this->User_model->find_by_username($this->current_user['username']);
			if ( ! password_verify($current, $user['password_hash']))
			{
				$errors[] = 'Password saat ini salah.';
			}
			$errors = array_merge($errors, validate_password($new, $confirm, $current));

			if (empty($errors))
			{
				$this->User_model->set_password($user['id'], $new, FALSE);
				$this->session->sess_regenerate(TRUE);
				$this->audit->log('password_change', array('table_name' => 'users', 'record_id' => $user['id']));
				flash('success', 'Password berhasil diganti.');
				redirect($forced ? 'dashboard' : 'profile');
			}
		}

		$this->render('profile/password', array(
			'title'  => 'Ganti Password',
			'forced' => $forced,
			'errors' => $errors,
		));
	}
}
