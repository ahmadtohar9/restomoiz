<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pelanggan & member (untuk harga member, promo member, kuota promo per
 * pelanggan, dan data delivery).
 */
class Customers extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('sales.process', 'sales.order', 'sales.edit_order')))
		{
			$this->require_permission('sales.process');
		}
	}

	public function index()
	{
		$q = trim((string) $this->input->get('q'));
		$this->db->select('c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.status = \'paid\') AS visits,
				(SELECT COALESCE(SUM(total), 0) FROM orders o WHERE o.customer_id = c.id AND o.status = \'paid\') AS spent,
				(SELECT MAX(paid_at) FROM orders o WHERE o.customer_id = c.id AND o.status = \'paid\') AS last_visit', FALSE)
			->from('customers c');
		if ($q !== '')
		{
			$this->db->group_start()->like('c.name', $q)->or_like('c.phone', $q)->group_end();
		}
		$this->render('sales/customers/index', array(
			'title' => 'Pelanggan',
			'rows'  => $this->db->order_by('c.name')->limit(300)->get()->result_array(),
			'q'     => $q,
		));
	}

	public function form($id = NULL)
	{
		$c = $id ? $this->db->where('id', (int) $id)->get('customers')->row_array() : NULL;
		if ($id && ! $c)
		{
			show_404();
		}
		$errors = array();
		$input = $c ?: array('name' => '', 'phone' => '', 'email' => '', 'address' => '', 'is_member' => 0, 'notes' => '');

		if ($this->input->method() === 'post')
		{
			foreach (array('name', 'phone', 'email', 'address', 'notes') as $k)
			{
				$input[$k] = trim((string) $this->input->post($k));
			}
			$input['phone'] = preg_replace('/[^0-9+]/', '', $input['phone']);
			$input['is_member'] = $this->input->post('is_member') ? 1 : 0;
			if ($input['name'] === '' OR mb_strlen($input['name']) > 100)
			{
				$errors[] = 'Nama wajib diisi (maks. 100 karakter).';
			}
			if ($input['phone'] !== '' && ! preg_match('/^\+?\d{8,15}$/', $input['phone']))
			{
				$errors[] = 'Nomor telepon 8-15 digit.';
			}
			elseif ($input['phone'] !== '')
			{
				$this->db->where('phone', $input['phone']);
				if ($c)
				{
					$this->db->where('id !=', $c['id']);
				}
				if ($this->db->count_all_results('customers'))
				{
					$errors[] = 'Nomor telepon sudah terdaftar untuk pelanggan lain.';
				}
			}
			if ($input['email'] !== '' && ! filter_var($input['email'], FILTER_VALIDATE_EMAIL))
			{
				$errors[] = 'Format email tidak valid.';
			}
			if (empty($errors))
			{
				$row = array(
					'name' => $input['name'], 'phone' => $input['phone'] ?: NULL, 'email' => $input['email'] ?: NULL,
					'address' => $input['address'] ? mb_substr($input['address'], 0, 255) : NULL, 'is_member' => $input['is_member'],
					'notes' => $input['notes'] ? mb_substr($input['notes'], 0, 255) : NULL,
				);
				if ($input['is_member'] && ( ! $c OR ! $c['is_member']))
				{
					$row['member_since'] = date('Y-m-d');
				}
				if ($c)
				{
					$this->db->where('id', $c['id'])->update('customers', $row);
					$cid = (int) $c['id'];
				}
				else
				{
					$this->db->insert('customers', $row);
					$cid = (int) $this->db->insert_id();
				}
				$this->audit->log($c ? 'customer_update' : 'customer_create', array('table_name' => 'customers', 'record_id' => $cid, 'detail' => array('name' => $row['name'], 'member' => $row['is_member'])));
				if ($this->input->is_ajax_request())
				{
					return $this->_json(array('ok' => TRUE, 'id' => $cid, 'name' => $row['name'], 'phone' => $row['phone'], 'address' => $row['address'], 'is_member' => $row['is_member']));
				}
				flash('success', "Pelanggan {$row['name']} disimpan.");
				redirect('sales/customers');
			}
			if ($this->input->is_ajax_request())
			{
				return $this->_json(array('ok' => FALSE, 'message' => implode(' ', $errors)));
			}
		}
		$this->render('sales/customers/form', array('title' => $c ? 'Edit Pelanggan' : 'Tambah Pelanggan', 'c' => $c, 'input' => $input, 'errors' => $errors));
	}
}
