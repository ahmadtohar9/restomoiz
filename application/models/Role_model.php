<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Role_model extends CI_Model {

	/** Semua role beserta jumlah user-nya. */
	public function all()
	{
		return $this->db->select('r.*, COUNT(ur.user_id) AS user_count')
			->from('roles r')
			->join('user_roles ur', 'ur.role_id = r.id', 'left')
			->group_by('r.id')
			->order_by('r.is_super', 'DESC')
			->order_by('r.name', 'ASC')
			->get()
			->result_array();
	}

	public function options()
	{
		return $this->db->order_by('is_super', 'DESC')->order_by('name', 'ASC')->get('roles')->result_array();
	}

	public function find($id)
	{
		return $this->db->where('id', (int) $id)->get('roles')->row_array();
	}

	public function name_exists($name, $except_id = NULL)
	{
		$this->db->where('name', $name);
		if ($except_id)
		{
			$this->db->where('id !=', (int) $except_id);
		}
		return $this->db->count_all_results('roles') > 0;
	}

	public function create($name, $description)
	{
		$this->db->insert('roles', array(
			'code'        => $this->_unique_code($name),
			'name'        => $name,
			'description' => $description,
		));
		return (int) $this->db->insert_id();
	}

	public function update($id, $name, $description)
	{
		return $this->db->where('id', (int) $id)->update('roles', array(
			'name'        => $name,
			'description' => $description,
		));
	}

	/** Duplikat role beserta permission-nya (PRD: Clone Role). */
	public function clone_role($id, $new_name)
	{
		$source = $this->find($id);
		$this->db->trans_start();
		$new_id = $this->create($new_name, $source ? $source['description'] : NULL);
		$this->db->query(
			'INSERT INTO role_permissions (role_id, permission_id)
			 SELECT ?, permission_id FROM role_permissions WHERE role_id = ?',
			array($new_id, (int) $id)
		);
		$this->db->trans_complete();
		return $new_id;
	}

	public function user_count($id)
	{
		return $this->db->where('role_id', (int) $id)->count_all_results('user_roles');
	}

	public function delete($id)
	{
		return $this->db->where('id', (int) $id)->delete('roles');
	}

	/** @return int[] permission id milik role */
	public function permission_ids($role_id)
	{
		$rows = $this->db->select('permission_id')->where('role_id', (int) $role_id)->get('role_permissions')->result_array();
		return array_map('intval', array_column($rows, 'permission_id'));
	}

	/** Ganti seluruh permission role. Mengembalikan array(ditambah[], dicabut[]) berupa id. */
	public function sync_permissions($role_id, array $permission_ids)
	{
		$role_id = (int) $role_id;
		$new = array_values(array_unique(array_map('intval', $permission_ids)));
		$old = $this->permission_ids($role_id);
		$added = array_values(array_diff($new, $old));
		$removed = array_values(array_diff($old, $new));

		$this->db->trans_start();
		if ($removed)
		{
			$this->db->where('role_id', $role_id)->where_in('permission_id', $removed)->delete('role_permissions');
		}
		foreach ($added as $pid)
		{
			$this->db->insert('role_permissions', array('role_id' => $role_id, 'permission_id' => $pid));
		}
		$this->db->trans_complete();

		return array($added, $removed);
	}

	/** Seluruh pasangan role-permission: [role_id][permission_id] = TRUE */
	public function matrix()
	{
		$out = array();
		foreach ($this->db->get('role_permissions')->result_array() as $row)
		{
			$out[$row['role_id']][$row['permission_id']] = TRUE;
		}
		return $out;
	}

	protected function _unique_code($name)
	{
		$base = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_') ?: 'role';
		$base = substr($base, 0, 40);
		$code = $base;
		$i = 2;
		while ($this->db->where('code', $code)->count_all_results('roles') > 0)
		{
			$code = $base . '_' . $i++;
		}
		return $code;
	}
}
