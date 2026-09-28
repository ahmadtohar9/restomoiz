<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pencatatan audit log (PRD 3.3 Tab 5 & tabel AUDIT_LOG).
 */
class Audit_logger {

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	/**
	 * @param string $action  contoh: login, user_create, role_permissions_update
	 * @param array  $opts    result, table_name, record_id, detail (string|array),
	 *                        user_id & username (override, mis. saat login gagal)
	 */
	public function log($action, array $opts = array())
	{
		$session = isset($this->CI->session) ? $this->CI->session : NULL;

		$detail = isset($opts['detail']) ? $opts['detail'] : NULL;
		if (is_array($detail))
		{
			$detail = json_encode($detail, JSON_UNESCAPED_UNICODE);
		}

		$this->CI->db->insert('audit_log', array(
			'user_id'    => array_key_exists('user_id', $opts) ? $opts['user_id'] : ($session ? $session->userdata('user_id') : NULL),
			'username'   => array_key_exists('username', $opts) ? $opts['username'] : ($session ? $session->userdata('username') : NULL),
			'action'     => $action,
			'result'     => isset($opts['result']) ? $opts['result'] : 'success',
			'table_name' => isset($opts['table_name']) ? $opts['table_name'] : NULL,
			'record_id'  => isset($opts['record_id']) ? (string) $opts['record_id'] : NULL,
			'detail'     => $detail,
			'ip_address' => $this->CI->input->ip_address(),
		));
	}
}
