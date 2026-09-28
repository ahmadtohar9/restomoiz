<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Manajemen user (PRD 3.3 Tab 1) + custom permission per user (Tab 4).
 */
class Users extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('admin.users');
		$this->load->model(array('User_model', 'Role_model', 'Permission_model'));
	}

	public function index()
	{
		$this->render('admin/users/index', array(
			'title' => 'Manajemen User',
			'users' => $this->User_model->all(),
		));
	}

	public function create()
	{
		$this->_form(NULL);
	}

	public function edit($id = NULL)
	{
		$user = $this->User_model->find($id);
		if ( ! $user)
		{
			show_404();
		}
		$this->_form($user);
	}

	public function delete($id = NULL)
	{
		$this->_require_post();
		$user = $this->User_model->find($id);
		if ( ! $user)
		{
			show_404();
		}

		if ((int) $user['id'] === (int) $this->current_user['id'])
		{
			flash('danger', 'Tidak bisa menghapus akun sendiri.');
			redirect('admin/users');
		}
		if ($this->User_model->is_super_admin($user['id']) && ! $this->rbac->is_super())
		{
			flash('danger', 'Hanya Admin yang boleh menghapus akun Admin.');
			redirect('admin/users');
		}
		if ($this->User_model->is_super_admin($user['id']) && $this->User_model->count_active_super_admins($user['id']) < 1)
		{
			flash('danger', 'Tidak bisa menghapus Admin terakhir.');
			redirect('admin/users');
		}

		$this->User_model->delete($user['id']);
		$this->audit->log('user_delete', array(
			'table_name' => 'users', 'record_id' => $user['id'],
			'detail' => array('username' => $user['username'], 'name' => $user['name']),
		));
		flash('success', "User {$user['username']} dihapus.");
		redirect('admin/users');
	}

	/** Custom permission sementara/permanen untuk satu user. */
	public function permissions($id = NULL)
	{
		$user = $this->User_model->find($id);
		if ( ! $user)
		{
			show_404();
		}
		$this->require_permission('admin.roles');

		$errors = array();
		if ($this->input->method() === 'post')
		{
			$permission = $this->Permission_model->find($this->input->post('permission_id'));
			$expiry = trim((string) $this->input->post('expiry_date'));
			$reason = trim((string) $this->input->post('reason'));

			if ( ! $permission)
			{
				$errors[] = 'Pilih permission.';
			}
			if ($expiry !== '' && ( ! $this->_valid_date($expiry) OR $expiry < date('Y-m-d')))
			{
				$errors[] = 'Tanggal kedaluwarsa tidak valid atau sudah lewat.';
			}
			if ($reason === '')
			{
				$errors[] = 'Alasan wajib diisi (tercatat di audit log).';
			}
			if ($permission && strpos($permission['name'], 'admin.') === 0 && ! $this->rbac->is_super())
			{
				$errors[] = 'Hanya Admin yang boleh memberi permission administrasi.';
			}

			if (empty($errors))
			{
				$this->User_model->grant_permission($user['id'], $permission['id'], $expiry, $reason, $this->current_user['id']);
				$this->audit->log('user_permission_grant', array(
					'table_name' => 'user_permissions', 'record_id' => $user['id'],
					'detail' => array('user' => $user['username'], 'permission' => $permission['name'], 'expiry' => $expiry ?: 'permanen', 'reason' => $reason),
				));
				flash('success', "Permission {$permission['name']} diberikan ke {$user['username']}.");
				redirect('admin/users/permissions/' . $user['id']);
			}
		}

		$this->render('admin/users/permissions', array(
			'title'   => 'Custom Permission: ' . $user['name'],
			'user'    => $user,
			'custom'  => $this->User_model->custom_permissions($user['id']),
			'groups'  => $this->Permission_model->grouped(),
			'errors'  => $errors,
		));
	}

	public function revoke($user_id = NULL, $user_permission_id = NULL)
	{
		$this->_require_post();
		$this->require_permission('admin.roles');
		$user = $this->User_model->find($user_id);
		if ( ! $user)
		{
			show_404();
		}
		$row = $this->db->select('up.id, p.name')
			->from('user_permissions up')->join('permissions p', 'p.id = up.permission_id')
			->where('up.id', (int) $user_permission_id)->where('up.user_id', (int) $user['id'])
			->get()->row_array();
		if ($row)
		{
			$this->User_model->revoke_permission($user['id'], $row['id']);
			$this->audit->log('user_permission_revoke', array(
				'table_name' => 'user_permissions', 'record_id' => $user['id'],
				'detail' => array('user' => $user['username'], 'permission' => $row['name']),
			));
			flash('success', "Permission {$row['name']} dicabut.");
		}
		redirect('admin/users/permissions/' . $user['id']);
	}

	protected function _form($user)
	{
		$is_edit = (bool) $user;
		$roles = $this->Role_model->options();
		$errors = array();

		$input = array(
			'username'  => $is_edit ? $user['username'] : '',
			'name'      => $is_edit ? $user['name'] : '',
			'email'     => $is_edit ? (string) $user['email'] : '',
			'is_active' => $is_edit ? (int) $user['is_active'] : 1,
			'must_change_password' => 1,
			'primary_role'    => 0,
			'secondary_roles' => array(),
		);
		if ($is_edit)
		{
			foreach ($user['roles'] as $role)
			{
				if ($role['is_primary'])
				{
					$input['primary_role'] = (int) $role['id'];
				}
				else
				{
					$input['secondary_roles'][] = (int) $role['id'];
				}
			}
		}

		if ($this->input->method() === 'post')
		{
			$input['username'] = trim((string) $this->input->post('username'));
			$input['name'] = trim((string) $this->input->post('name'));
			$input['email'] = strtolower(trim((string) $this->input->post('email')));
			$input['is_active'] = $this->input->post('is_active') ? 1 : 0;
			$input['must_change_password'] = $this->input->post('must_change_password') ? 1 : 0;
			$input['primary_role'] = (int) $this->input->post('primary_role');
			$input['secondary_roles'] = array_map('intval', (array) $this->input->post('secondary_roles'));
			$password = (string) $this->input->post('password');
			$confirm = (string) $this->input->post('password_confirm');

			$role_ids = array_map('intval', array_column($roles, 'id'));
			$super_ids = array();
			foreach ($roles as $r)
			{
				if ($r['is_super'])
				{
					$super_ids[] = (int) $r['id'];
				}
			}
			$input['secondary_roles'] = array_values(array_intersect($input['secondary_roles'], $role_ids));
			$all_selected = array_merge(array($input['primary_role']), $input['secondary_roles']);
			$will_be_super = (bool) array_intersect($all_selected, $super_ids);

			if ( ! preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $input['username']))
			{
				$errors[] = 'Username 3-50 karakter (huruf, angka, titik, strip, underscore).';
			}
			elseif ($this->User_model->username_exists($input['username'], $is_edit ? $user['id'] : NULL))
			{
				$errors[] = 'Username sudah dipakai.';
			}
			if ($input['name'] === '')
			{
				$errors[] = 'Nama wajib diisi.';
			}
			if ($input['email'] !== '' && ! filter_var($input['email'], FILTER_VALIDATE_EMAIL))
			{
				$errors[] = 'Format email tidak valid.';
			}
			elseif ($this->User_model->email_exists($input['email'], $is_edit ? $user['id'] : NULL))
			{
				$errors[] = 'Email sudah dipakai user lain.';
			}
			if ( ! in_array($input['primary_role'], $role_ids, TRUE))
			{
				$errors[] = 'Pilih role utama.';
			}
			if ( ! $is_edit OR $password !== '')
			{
				$errors = array_merge($errors, validate_password($password, $confirm));
			}

			// Hanya Admin yang boleh membuat/mengubah akun Admin.
			$target_is_super = $is_edit && $this->User_model->is_super_admin($user['id']);
			if (($will_be_super OR $target_is_super) && ! $this->rbac->is_super())
			{
				$errors[] = 'Hanya Admin yang boleh memberi role Admin atau mengubah akun Admin.';
			}
			// Jangan sampai sistem kehilangan admin aktif terakhir.
			if ($target_is_super && ( ! $will_be_super OR ! $input['is_active'])
				&& $this->User_model->count_active_super_admins($user['id']) < 1)
			{
				$errors[] = 'User ini Admin aktif terakhir: role Admin dan status aktifnya tidak bisa dilepas.';
			}

			if (empty($errors))
			{
				$payload = array(
					'username' => $input['username'],
					'name'     => $input['name'],
					'email'    => $input['email'],
					'is_active' => $input['is_active'],
					'password' => $password,
					'must_change_password' => $input['must_change_password'],
				);
				$role_names = array();
				foreach ($roles as $r)
				{
					if (in_array((int) $r['id'], $all_selected, TRUE))
					{
						$role_names[] = $r['name'];
					}
				}

				if ($is_edit)
				{
					$this->db->trans_start();
					$this->User_model->update($user['id'], $payload);
					$this->User_model->set_roles($user['id'], $input['primary_role'], $input['secondary_roles']);
					$this->db->trans_complete();
					$this->audit->log('user_update', array(
						'table_name' => 'users', 'record_id' => $user['id'],
						'detail' => array(
							'username' => $input['username'], 'roles' => $role_names,
							'is_active' => $input['is_active'], 'password_reset' => $password !== '',
						),
					));
					flash('success', "User {$input['username']} diperbarui.");
				}
				else
				{
					$id = $this->User_model->create($payload, $input['primary_role'], $input['secondary_roles']);
					$this->audit->log('user_create', array(
						'table_name' => 'users', 'record_id' => $id,
						'detail' => array('username' => $input['username'], 'roles' => $role_names),
					));
					flash('success', "User {$input['username']} dibuat.");
				}
				redirect('admin/users');
			}
		}

		$this->render('admin/users/form', array(
			'title'   => $is_edit ? 'Edit User' : 'Tambah User',
			'user'    => $user,
			'input'   => $input,
			'roles'   => $roles,
			'errors'  => $errors,
		));
	}

	protected function _require_post()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
	}

	protected function _valid_date($date)
	{
		$d = DateTime::createFromFormat('Y-m-d', $date);
		return $d && $d->format('Y-m-d') === $date;
	}
}
