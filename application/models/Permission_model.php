<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Permission_model extends CI_Model {

	public function all()
	{
		return $this->db->order_by('module', 'ASC')->order_by('name', 'ASC')->get('permissions')->result_array();
	}

	/**
	 * Permission dikelompokkan per modul, urut sesuai config rbac_modules.
	 * @return array [module_key => ['label' => ..., 'permissions' => [...]]]
	 */
	public function grouped()
	{
		$this->config->load('rbac', TRUE);
		$modules = $this->config->item('rbac_modules', 'rbac');

		$out = array();
		foreach ($modules as $key => $label)
		{
			$out[$key] = array('label' => $label, 'permissions' => array());
		}
		foreach ($this->all() as $perm)
		{
			if ( ! isset($out[$perm['module']]))
			{
				$out[$perm['module']] = array('label' => ucfirst($perm['module']), 'permissions' => array());
			}
			$out[$perm['module']]['permissions'][] = $perm;
		}
		return array_filter($out, function ($group) {
			return ! empty($group['permissions']);
		});
	}

	public function find($id)
	{
		return $this->db->where('id', (int) $id)->get('permissions')->row_array();
	}

	/** @return int[] id yang valid dari daftar input */
	public function filter_ids(array $ids)
	{
		$ids = array_filter(array_map('intval', $ids));
		if (empty($ids))
		{
			return array();
		}
		$rows = $this->db->select('id')->where_in('id', $ids)->get('permissions')->result_array();
		return array_map('intval', array_column($rows, 'id'));
	}

	/** @return string[] nama permission untuk daftar id (untuk audit log) */
	public function names(array $ids)
	{
		if (empty($ids))
		{
			return array();
		}
		$rows = $this->db->select('name')->where_in('id', $ids)->order_by('name')->get('permissions')->result_array();
		return array_column($rows, 'name');
	}
}
