<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengecekan permission (PRD 3.4).
 *
 * Permission efektif user = gabungan permission semua role-nya
 * + custom permission per user yang belum kedaluwarsa.
 * Role dengan is_super = 1 (Admin) otomatis punya semua permission.
 *
 * Permission dihitung dari database setiap request (bukan disimpan di
 * session), jadi perubahan role/permission oleh admin langsung berlaku.
 */
class Rbac {

	/** @var CI_Controller */
	protected $CI;

	protected $user;
	protected $permissions;
	protected $is_super = FALSE;
	protected $loaded = FALSE;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	/**
	 * User yang sedang login (aktif), atau NULL.
	 */
	public function current_user()
	{
		$this->_load();
		return $this->user;
	}

	public function has_permission($permission)
	{
		$this->_load();
		if ( ! $this->user)
		{
			return FALSE;
		}
		return $this->is_super OR isset($this->permissions[$permission]);
	}

	/** TRUE kalau user punya minimal satu dari permission yang diberikan. */
	public function has_any(array $permissions)
	{
		foreach ($permissions as $permission)
		{
			if ($this->has_permission($permission))
			{
				return TRUE;
			}
		}
		return FALSE;
	}

	public function is_super()
	{
		$this->_load();
		return $this->is_super;
	}

	/** Daftar nama permission efektif (untuk halaman profil). */
	public function permissions()
	{
		$this->_load();
		return array_keys($this->permissions);
	}

	/** Paksa hitung ulang (dipakai setelah login / ubah role user sendiri). */
	public function reset()
	{
		$this->loaded = FALSE;
		$this->user = NULL;
		$this->permissions = array();
		$this->is_super = FALSE;
	}

	protected function _load()
	{
		if ($this->loaded)
		{
			return;
		}
		$this->loaded = TRUE;
		$this->permissions = array();

		$user_id = (int) $this->CI->session->userdata('user_id');
		if ( ! $user_id)
		{
			return;
		}

		$db = $this->CI->db;
		$user = $db->select('id, username, name, email, is_active, must_change_password, last_login')
			->where('id', $user_id)
			->where('is_active', 1)
			->get('users')
			->row_array();
		if ( ! $user)
		{
			return;
		}

		$roles = $db->select('r.id, r.code, r.name, r.is_super, ur.is_primary')
			->from('user_roles ur')
			->join('roles r', 'r.id = ur.role_id')
			->where('ur.user_id', $user_id)
			->order_by('ur.is_primary', 'DESC')
			->order_by('r.name', 'ASC')
			->get()
			->result_array();

		$user['roles'] = $roles;
		$user['role_names'] = array_column($roles, 'name');
		$this->user = $user;

		foreach ($roles as $role)
		{
			if ($role['is_super'])
			{
				$this->is_super = TRUE;
			}
		}

		$rows = $db->query(
			'SELECT p.name FROM permissions p
			 JOIN role_permissions rp ON rp.permission_id = p.id
			 JOIN user_roles ur ON ur.role_id = rp.role_id
			 WHERE ur.user_id = ?
			 UNION
			 SELECT p.name FROM permissions p
			 JOIN user_permissions up ON up.permission_id = p.id
			 WHERE up.user_id = ? AND (up.expiry_date IS NULL OR up.expiry_date >= CURDATE())',
			array($user_id, $user_id)
		)->result_array();

		foreach ($rows as $row)
		{
			$this->permissions[$row['name']] = TRUE;
		}
	}
}
