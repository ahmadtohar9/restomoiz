<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Penomoran dokumen yang aman untuk request bersamaan
 * (INSERT ... ON DUPLICATE KEY UPDATE + LAST_INSERT_ID, tanpa race condition).
 *
 *   $this->sequence->next('MV', date('ymd'), 4)  -> MV-260929-0001
 *   $this->sequence->next('BB', '', 5)          -> BB-00001
 */
class Sequence {

	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function next($prefix, $period = '', $pad = 4)
	{
		$this->CI->db->query(
			'INSERT INTO doc_sequences (seq_key, period, last_no) VALUES (?, ?, LAST_INSERT_ID(1))
			 ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1)',
			array($prefix, (string) $period)
		);
		$no = (int) $this->CI->db->query('SELECT LAST_INSERT_ID() AS n')->row()->n;
		return $prefix . '-' . ($period !== '' ? $period . '-' : '') . str_pad($no, $pad, '0', STR_PAD_LEFT);
	}
}
