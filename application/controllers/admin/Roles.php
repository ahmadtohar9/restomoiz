<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Manajemen role (PRD 3.3 Tab 2) & permission matrix (Tab 3).
 */
class Roles extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('admin.roles');
		$this->load->model(array('Role_model', 'Permission_model'));
	}

	public function index()
	{
		$this->render('admin/roles/index', array(
			'title' => 'Manajemen Role',
			'roles' => $this->Role_model->all(),
		));
	}

	public function create()
	{
		$this->_form(NULL);
	}

	public function edit($id = NULL)
	{
		$role = $this->Role_model->find($id);
		if ( ! $role)
		{
			show_404();
		}
		$this->_form($role);
	}

	public function clone_role($id = NULL)
	{
		$this->_require_post();
		$role = $this->Role_model->find($id);
		if ( ! $role)
		{
			show_404();
		}
		if ($role['is_super'])
		{
			flash('danger', 'Role Admin tidak bisa di-clone.');
			redirect('admin/roles');
		}
		$name = 'Salinan ' . $role['name'];
		$i = 2;
		while ($this->Role_model->name_exists($name))
		{
			$name = 'Salinan ' . $role['name'] . ' ' . $i++;
		}
		$new_id = $this->Role_model->clone_role($role['id'], $name);
		$this->audit->log('role_clone', array(
			'table_name' => 'roles', 'record_id' => $new_id,
			'detail' => array('from' => $role['name'], 'to' => $name),
		));
		flash('success', "Role \"$name\" dibuat. Silakan ubah nama & permission-nya.");
		redirect('admin/roles/edit/' . $new_id);
	}

	public function delete($id = NULL)
	{
		$this->_require_post();
		$role = $this->Role_model->find($id);
		if ( ! $role)
		{
			show_404();
		}
		if ($role['is_super'])
		{
			flash('danger', 'Role Admin tidak bisa dihapus.');
		}
		elseif ($this->Role_model->user_count($role['id']) > 0)
		{
			flash('danger', "Role \"{$role['name']}\" masih dipakai user. Pindahkan user-nya dulu.");
		}
		else
		{
			$this->Role_model->delete($role['id']);
			$this->audit->log('role_delete', array('table_name' => 'roles', 'record_id' => $role['id'], 'detail' => array('name' => $role['name'])));
			flash('success', "Role \"{$role['name']}\" dihapus.");
		}
		redirect('admin/roles');
	}

	/** Permission matrix: semua role vs semua permission, bisa diedit langsung. */
	public function matrix()
	{
		$roles = $this->Role_model->options();

		if ($this->input->method() === 'post')
		{
			$posted = (array) $this->input->post('perm');
			$changes = array();
			$this->db->trans_start();
			foreach ($roles as $role)
			{
				if ($role['is_super'])
				{
					continue;
				}
				$ids = $this->Permission_model->filter_ids(isset($posted[$role['id']]) ? (array) $posted[$role['id']] : array());
				list($added, $removed) = $this->Role_model->sync_permissions($role['id'], $ids);
				if ($added OR $removed)
				{
					$changes[$role['name']] = array(
						'added'   => $this->Permission_model->names($added),
						'removed' => $this->Permission_model->names($removed),
					);
				}
			}
			$this->db->trans_complete();

			if ($changes)
			{
				$this->audit->log('role_permissions_update', array('table_name' => 'role_permissions', 'detail' => $changes));
				flash('success', 'Permission matrix disimpan (' . count($changes) . ' role berubah).');
			}
			else
			{
				flash('info', 'Tidak ada perubahan.');
			}
			redirect('admin/roles/matrix');
		}

		$this->render('admin/roles/matrix', array(
			'title'  => 'Permission Matrix',
			'roles'  => $roles,
			'groups' => $this->Permission_model->grouped(),
			'matrix' => $this->Role_model->matrix(),
		));
	}

	protected function _form($role)
	{
		$is_edit = (bool) $role;
		$errors = array();
		$input = array(
			'name'        => $is_edit ? $role['name'] : '',
			'description' => $is_edit ? (string) $role['description'] : '',
			'permissions' => $is_edit ? $this->Role_model->permission_ids($role['id']) : array(),
		);

		if ($this->input->method() === 'post')
		{
			$input['name'] = trim((string) $this->input->post('name'));
			$input['description'] = trim((string) $this->input->post('description'));
			$input['permissions'] = $this->Permission_model->filter_ids((array) $this->input->post('permissions'));

			if ($input['name'] === '' OR strlen($input['name']) > 100)
			{
				$errors[] = 'Nama role wajib diisi (maks. 100 karakter).';
			}
			elseif ($this->Role_model->name_exists($input['name'], $is_edit ? $role['id'] : NULL))
			{
				$errors[] = 'Nama role sudah dipakai.';
			}

			if (empty($errors))
			{
				$this->db->trans_start();
				if ($is_edit)
				{
					$this->Role_model->update($role['id'], $input['name'], $input['description']);
					$role_id = (int) $role['id'];
				}
				else
				{
					$role_id = $this->Role_model->create($input['name'], $input['description']);
				}
				$added = $removed = array();
				if ( ! ($is_edit && $role['is_super']))
				{
					list($added, $removed) = $this->Role_model->sync_permissions($role_id, $input['permissions']);
				}
				$this->db->trans_complete();

				$this->audit->log($is_edit ? 'role_update' : 'role_create', array(
					'table_name' => 'roles', 'record_id' => $role_id,
					'detail' => array(
						'name'    => $input['name'],
						'added'   => $this->Permission_model->names($added),
						'removed' => $this->Permission_model->names($removed),
					),
				));
				flash('success', "Role \"{$input['name']}\" disimpan.");
				redirect('admin/roles');
			}
		}

		$this->render('admin/roles/form', array(
			'title'  => $is_edit ? 'Edit Role' : 'Tambah Role',
			'role'   => $role,
			'input'  => $input,
			'groups' => $this->Permission_model->grouped(),
			'errors' => $errors,
		));
	}

	protected function _require_post()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
	}
}
