<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengeluaran operasional (gaji, sewa, listrik, marketing, dll.) untuk
 * Laporan Laba Rugi (PRD 2.5.2). Pembelian bahan baku TIDAK dicatat di
 * sini: sudah masuk lewat modul Pembelian & COGS.
 */
class Expenses extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('finance.expense', 'report.financial')))
		{
			$this->require_permission('finance.expense');
		}
		$this->load->model('Report_model');
	}

	public function index()
	{
		$period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $this->input->get('period')) ? $this->input->get('period') : date('Y-m');
		$from = $period . '-01';
		$to = date('Y-m-t', strtotime($from));
		$rows = $this->db->select('e.*, u.name AS created_by_name')->from('expenses e')->join('users u', 'u.id = e.created_by', 'left')
			->where('e.expense_date >=', $from)->where('e.expense_date <=', $to)->order_by('e.expense_date', 'DESC')->order_by('e.id', 'DESC')
			->get()->result_array();
		$by_cat = array();
		foreach ($rows as $r)
		{
			$by_cat[$r['category']] = (isset($by_cat[$r['category']]) ? $by_cat[$r['category']] : 0) + (float) $r['amount'];
		}
		if ($this->input->get('export'))
		{
			send_csv('pengeluaran-' . $period . '.csv', array('Tanggal', 'Kategori', 'Keterangan', 'Jumlah', 'Metode', 'Dicatat oleh'), array_map(function ($r) {
				return array($r['expense_date'], Report_model::$expense_categories[$r['category']], $r['description'], $r['amount'], $r['payment_method'], $r['created_by_name']);
			}, $rows));
		}
		$this->render('finance/expenses/index', array('title' => 'Pengeluaran Operasional', 'rows' => $rows, 'period' => $period, 'by_cat' => $by_cat,
			'edit' => $this->_edit_row()));
	}

	/** Simpan baru / ubah (id). */
	public function save()
	{
		$this->require_permission('finance.expense');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$id = (int) $this->input->post('id');
		$old = $id ? $this->db->where('id', $id)->get('expenses')->row_array() : NULL;
		if ($id && ! $old)
		{
			show_404();
		}
		$date = (string) $this->input->post('expense_date');
		$d = DateTime::createFromFormat('Y-m-d', $date);
		$amount = num_in($this->input->post('amount'), NAN);
		$cat = (string) $this->input->post('category');
		$desc = trim((string) $this->input->post('description'));
		$errors = array();
		if ( ! $d OR $d->format('Y-m-d') !== $date OR $date > date('Y-m-d'))
		{
			$errors[] = 'Tanggal tidak valid atau di masa depan.';
		}
		if ( ! isset(Report_model::$expense_categories[$cat]))
		{
			$errors[] = 'Pilih kategori.';
		}
		if ($desc === '' OR mb_strlen($desc) > 255)
		{
			$errors[] = 'Keterangan wajib diisi (maks. 255 karakter).';
		}
		if (is_nan($amount) OR $amount <= 0)
		{
			$errors[] = 'Jumlah harus lebih dari 0.';
		}
		$this->load->library('private_upload');
		$file = empty($errors) ? $this->private_upload->save('attachment', 'expenses', $errors) : NULL;
		if ($errors)
		{
			flash('danger', implode(' ', $errors));
			redirect('finance/expenses?period=' . substr($date ?: date('Y-m'), 0, 7) . ($id ? '&edit=' . $id : ''));
		}
		$row = array('expense_date' => $date, 'category' => $cat, 'description' => $desc, 'amount' => round($amount, 2),
			'payment_method' => mb_substr(trim((string) $this->input->post('payment_method')), 0, 20) ?: NULL);
		if ($file)
		{
			$row['attachment_path'] = $file;
		}
		if ($old)
		{
			$this->db->where('id', $id)->update('expenses', $row);
			if ($file && $old['attachment_path'])
			{
				$this->private_upload->delete($old['attachment_path']);
			}
		}
		else
		{
			$row['created_by'] = $this->current_user['id'];
			$this->db->insert('expenses', $row);
			$id = (int) $this->db->insert_id();
		}
		$this->audit->log($old ? 'expense_update' : 'expense_create', array('table_name' => 'expenses', 'record_id' => $id, 'detail' => $row));
		flash('success', 'Pengeluaran ' . rupiah($row['amount']) . ' disimpan.');
		redirect('finance/expenses?period=' . substr($date, 0, 7));
	}

	public function delete($id = NULL)
	{
		$this->require_permission('finance.expense');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$e = $this->db->where('id', (int) $id)->get('expenses')->row_array();
		if ( ! $e)
		{
			show_404();
		}
		$this->db->where('id', $e['id'])->delete('expenses');
		if ($e['attachment_path'])
		{
			$this->load->library('private_upload');
			$this->private_upload->delete($e['attachment_path']);
		}
		$this->audit->log('expense_delete', array('table_name' => 'expenses', 'record_id' => $e['id'], 'detail' => $e));
		flash('success', 'Pengeluaran dihapus.');
		redirect('finance/expenses?period=' . substr($e['expense_date'], 0, 7));
	}

	protected function _edit_row()
	{
		$id = (int) $this->input->get('edit');
		return $id && can('finance.expense') ? $this->db->where('id', $id)->get('expenses')->row_array() : NULL;
	}
}
