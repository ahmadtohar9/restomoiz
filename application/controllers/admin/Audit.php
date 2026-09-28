<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Audit log (PRD 3.3 Tab 5): filter tanggal/user/aksi + export CSV.
 */
class Audit extends MY_Controller {

	const PER_PAGE = 50;

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('admin.audit');
	}

	public function index()
	{
		$filters = $this->_filters();
		$page = max(1, (int) $this->input->get('page'));

		$this->_apply($filters);
		$total = $this->db->count_all_results('audit_log');

		$this->_apply($filters);
		$rows = $this->db->order_by('id', 'DESC')
			->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)
			->get('audit_log')
			->result_array();

		$actions = array_column($this->db->distinct()->select('action')->order_by('action')->get('audit_log')->result_array(), 'action');

		$this->render('admin/audit/index', array(
			'title'   => 'Audit Log',
			'rows'    => $rows,
			'filters' => $filters,
			'actions' => $actions,
			'page'    => $page,
			'pages'   => max(1, (int) ceil($total / self::PER_PAGE)),
			'total'   => $total,
		));
	}

	public function export()
	{
		$filters = $this->_filters();
		$this->_apply($filters);
		$query = $this->db->order_by('id', 'DESC')->limit(50000)->get('audit_log');

		$this->audit->log('audit_export', array('detail' => $filters));

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="audit-log-' . date('Ymd-His') . '.csv"');
		$out = fopen('php://output', 'w');
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, array('Waktu', 'User', 'Aksi', 'Hasil', 'Tabel', 'Record', 'Detail', 'IP'));
		while ($row = $query->unbuffered_row('array'))
		{
			fputcsv($out, array_map(array($this, '_csv_safe'), array(
				$row['created_at'], $row['username'], $row['action'], $row['result'],
				$row['table_name'], $row['record_id'], $row['detail'], $row['ip_address'],
			)));
		}
		fclose($out);
		exit;
	}

	/** Cegah CSV/formula injection saat dibuka di Excel. */
	public function _csv_safe($value)
	{
		$value = (string) $value;
		return ($value !== '' && strpos('=+-@', $value[0]) !== FALSE) ? "'" . $value : $value;
	}

	protected function _filters()
	{
		$from = (string) $this->input->get('from');
		$to = (string) $this->input->get('to');
		return array(
			'from'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-d', strtotime('-6 days')),
			'to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d'),
			'user'   => trim((string) $this->input->get('user')),
			'action' => trim((string) $this->input->get('action')),
			'result' => in_array($this->input->get('result'), array('success', 'denied', 'failed'), TRUE) ? $this->input->get('result') : '',
		);
	}

	protected function _apply(array $f)
	{
		$this->db->where('created_at >=', $f['from'] . ' 00:00:00')
			->where('created_at <=', $f['to'] . ' 23:59:59');
		if ($f['user'] !== '')
		{
			$this->db->like('username', $f['user']);
		}
		if ($f['action'] !== '')
		{
			$this->db->where('action', $f['action']);
		}
		if ($f['result'] !== '')
		{
			$this->db->where('result', $f['result']);
		}
	}
}
