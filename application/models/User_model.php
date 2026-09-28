<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {

	/**
	 * Daftar user beserta nama role-nya (role utama di depan).
	 */
	public function all()
	{
		$users = $this->db->order_by('name', 'ASC')->get('users')->result_array();
		$roles = $this->_roles_by_user(array_column($users, 'id'));
		foreach ($users as &$user)
		{
			$user['roles'] = isset($roles[$user['id']]) ? $roles[$user['id']] : array();
		}
		return $users;
	}

	public function find($id)
	{
		$user = $this->db->where('id', (int) $id)->get('users')->row_array();
		if ($user)
		{
			$roles = $this->_roles_by_user(array($user['id']));
			$user['roles'] = isset($roles[$user['id']]) ? $roles[$user['id']] : array();
		}
		return $user;
	}

	public function find_by_username($username)
	{
		return $this->db->where('username', $username)->get('users')->row_array();
	}

	public function username_exists($username, $except_id = NULL)
	{
		$this->db->where('username', $username);
		if ($except_id)
		{
			$this->db->where('id !=', (int) $except_id);
		}
		return $this->db->count_all_results('users') > 0;
	}

	public function email_exists($email, $except_id = NULL)
	{
		if ($email === '' OR $email === NULL)
		{
			return FALSE;
		}
		$this->db->where('email', $email);
		if ($except_id)
		{
			$this->db->where('id !=', (int) $except_id);
		}
		return $this->db->count_all_results('users') > 0;
	}

	/**
	 * @param array $data     username, name, email, password, is_active, must_change_password
	 * @param int   $primary_role_id
	 * @param int[] $secondary_role_ids
	 * @return int id user baru
	 */
	public function create(array $data, $primary_role_id, array $secondary_role_ids = array())
	{
		$this->db->trans_start();
		$this->db->insert('users', array(
			'username'             => $data['username'],
			'name'                 => $data['name'],
			'email'                => $data['email'] !== '' ? $data['email'] : NULL,
			'password_hash'        => password_hash($data['password'], PASSWORD_DEFAULT),
			'is_active'            => ! empty($data['is_active']) ? 1 : 0,
			'must_change_password' => ! empty($data['must_change_password']) ? 1 : 0,
		));
		$id = (int) $this->db->insert_id();
		$this->set_roles($id, $primary_role_id, $secondary_role_ids);
		$this->db->trans_complete();
		return $id;
	}

	public function update($id, array $data)
	{
		$row = array(
			'name'      => $data['name'],
			'email'     => $data['email'] !== '' ? $data['email'] : NULL,
			'is_active' => ! empty($data['is_active']) ? 1 : 0,
		);
		if (isset($data['username']))
		{
			$row['username'] = $data['username'];
		}
		if ( ! empty($data['password']))
		{
			$row['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
			$row['must_change_password'] = ! empty($data['must_change_password']) ? 1 : 0;
		}
		return $this->db->where('id', (int) $id)->update('users', $row);
	}

	public function set_password($id, $password, $must_change = FALSE)
	{
		return $this->db->where('id', (int) $id)->update('users', array(
			'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
			'must_change_password' => $must_change ? 1 : 0,
		));
	}

	public function set_roles($user_id, $primary_role_id, array $secondary_role_ids = array())
	{
		$user_id = (int) $user_id;
		$primary_role_id = (int) $primary_role_id;
		$this->db->where('user_id', $user_id)->delete('user_roles');
		$this->db->insert('user_roles', array('user_id' => $user_id, 'role_id' => $primary_role_id, 'is_primary' => 1));
		foreach (array_unique(array_map('intval', $secondary_role_ids)) as $role_id)
		{
			if ($role_id && $role_id !== $primary_role_id)
			{
				$this->db->insert('user_roles', array('user_id' => $user_id, 'role_id' => $role_id, 'is_primary' => 0));
			}
		}
	}

	public function delete($id)
	{
		return $this->db->where('id', (int) $id)->delete('users');
	}

	public function touch_login($id)
	{
		$this->db->where('id', (int) $id)->update('users', array('last_login' => date('Y-m-d H:i:s')));
	}

	/**
	 * Jumlah admin (role super) aktif, opsional mengecualikan satu user.
	 * Dipakai untuk mencegah sistem kehilangan admin terakhir.
	 */
	public function count_active_super_admins($except_user_id = NULL)
	{
		$this->db->select('COUNT(DISTINCT u.id) AS total')
			->from('users u')
			->join('user_roles ur', 'ur.user_id = u.id')
			->join('roles r', 'r.id = ur.role_id')
			->where('u.is_active', 1)
			->where('r.is_super', 1);
		if ($except_user_id)
		{
			$this->db->where('u.id !=', (int) $except_user_id);
		}
		return (int) $this->db->get()->row()->total;
	}

	public function is_super_admin($user_id)
	{
		return $this->db->from('user_roles ur')
			->join('roles r', 'r.id = ur.role_id')
			->where('ur.user_id', (int) $user_id)
			->where('r.is_super', 1)
			->count_all_results() > 0;
	}

	// ---- Custom permission per user (PRD 3.3 Tab 4) ----

	public function custom_permissions($user_id)
	{
		return $this->db->select('up.*, p.name AS permission_name, p.description AS permission_description, g.name AS granted_by_name')
			->from('user_permissions up')
			->join('permissions p', 'p.id = up.permission_id')
			->join('users g', 'g.id = up.granted_by', 'left')
			->where('up.user_id', (int) $user_id)
			->order_by('p.name', 'ASC')
			->get()
			->result_array();
	}

	public function grant_permission($user_id, $permission_id, $expiry_date, $reason, $granted_by)
	{
		$sql = 'INSERT INTO user_permissions (user_id, permission_id, expiry_date, reason, granted_by)
				VALUES (?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE expiry_date = VALUES(expiry_date), reason = VALUES(reason),
					granted_by = VALUES(granted_by), created_at = CURRENT_TIMESTAMP';
		return $this->db->query($sql, array((int) $user_id, (int) $permission_id, $expiry_date ?: NULL, $reason, (int) $granted_by));
	}

	public function revoke_permission($user_id, $user_permission_id)
	{
		return $this->db->where('id', (int) $user_permission_id)
			->where('user_id', (int) $user_id)
			->delete('user_permissions');
	}

	protected function _roles_by_user(array $user_ids)
	{
		if (empty($user_ids))
		{
			return array();
		}
		$rows = $this->db->select('ur.user_id, ur.is_primary, r.id, r.name, r.is_super')
			->from('user_roles ur')
			->join('roles r', 'r.id = ur.role_id')
			->where_in('ur.user_id', $user_ids)
			->order_by('ur.is_primary', 'DESC')
			->order_by('r.name', 'ASC')
			->get()
			->result_array();
		$out = array();
		foreach ($rows as $row)
		{
			$out[$row['user_id']][] = $row;
		}
		return $out;
	}
}
