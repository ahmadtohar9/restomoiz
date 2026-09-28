<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Supplier management (PRD 2.1.1 Supplier Management).
 */
class Suppliers extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('inventory.view');
		$this->load->model(array('Supplier_model', 'Ingredient_model'));
	}

	public function index()
	{
		$f = array(
			'q'      => trim((string) $this->input->get('q')),
			'active' => in_array($this->input->get('active'), array('0', '1'), TRUE) ? $this->input->get('active') : ($this->input->get('active') === NULL ? '1' : ''),
		);
		$this->render('inventory/suppliers/index', array(
			'title'   => 'Supplier',
			'rows'    => $this->Supplier_model->all($f),
			'filters' => $f,
		));
	}

	public function show($id = NULL)
	{
		$s = $this->Supplier_model->find($id);
		if ( ! $s)
		{
			show_404();
		}
		$this->render('inventory/suppliers/show', array(
			'title'       => $s['name'],
			's'           => $s,
			'prices'      => can('inventory.view_cost') ? $this->Supplier_model->latest_prices($s['id']) : array(),
			'stats'       => $this->Supplier_model->receipt_stats($s['id']),
			'ingredients' => $this->Ingredient_model->all(array('supplier_id' => $s['id'])),
			'can_cost'    => can('inventory.view_cost'),
		));
	}

	public function create()
	{
		$this->require_permission('inventory.supplier');
		$this->_form(NULL);
	}

	public function edit($id = NULL)
	{
		$this->require_permission('inventory.supplier');
		$s = $this->Supplier_model->find($id);
		if ( ! $s)
		{
			show_404();
		}
		$this->_form($s);
	}

	public function delete($id = NULL)
	{
		$this->require_permission('inventory.supplier');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$s = $this->Supplier_model->find($id);
		if ( ! $s)
		{
			show_404();
		}
		if ($this->Supplier_model->is_used($s['id']))
		{
			flash('warning', "{$s['name']} sudah punya riwayat transaksi sehingga tidak bisa dihapus. Nonaktifkan saja.");
			redirect('inventory/suppliers/show/' . $s['id']);
		}
		$this->Supplier_model->delete($s['id']);
		$this->audit->log('supplier_delete', array('table_name' => 'suppliers', 'record_id' => $s['id'], 'detail' => array('code' => $s['code'], 'name' => $s['name'])));
		flash('success', "Supplier {$s['name']} dihapus.");
		redirect('inventory/suppliers');
	}

	protected function _form($s)
	{
		$is_edit = (bool) $s;
		$errors = array();
		$input = $is_edit ? $s : array(
			'name' => '', 'contact_person' => '', 'phone' => '', 'email' => '', 'address' => '', 'city' => '',
			'payment_terms' => 'COD', 'min_order_amount' => 0, 'lead_time_days' => 0, 'quality_score' => '',
			'notes' => '', 'is_active' => 1,
		);

		if ($this->input->method() === 'post')
		{
			foreach (array('name', 'contact_person', 'phone', 'email', 'address', 'city', 'notes') as $k)
			{
				$input[$k] = trim((string) $this->input->post($k));
			}
			$input['email'] = strtolower($input['email']);
			$input['payment_terms'] = (string) $this->input->post('payment_terms');
			$input['min_order_amount'] = num_in($this->input->post('min_order_amount'));
			$input['lead_time_days'] = num_in($this->input->post('lead_time_days'));
			$input['quality_score'] = (int) $this->input->post('quality_score');
			$input['is_active'] = $this->input->post('is_active') ? 1 : 0;

			if ($input['name'] === '' OR mb_strlen($input['name']) > 150)
			{
				$errors[] = 'Nama supplier wajib diisi (maks. 150 karakter).';
			}
			if ($input['email'] !== '' && ! filter_var($input['email'], FILTER_VALIDATE_EMAIL))
			{
				$errors[] = 'Format email tidak valid.';
			}
			if ( ! array_key_exists($input['payment_terms'], Supplier_model::$payment_terms))
			{
				$errors[] = 'Termin pembayaran tidak valid.';
			}
			if (is_nan($input['min_order_amount']) OR $input['min_order_amount'] < 0)
			{
				$errors[] = 'Minimum order harus angka ≥ 0.';
				$input['min_order_amount'] = 0;
			}
			if (is_nan($input['lead_time_days']) OR $input['lead_time_days'] < 0 OR $input['lead_time_days'] > 365 OR floor($input['lead_time_days']) != $input['lead_time_days'])
			{
				$errors[] = 'Lead time harus bilangan bulat 0-365 hari.';
				$input['lead_time_days'] = 0;
			}
			if ($input['quality_score'] && ($input['quality_score'] < 1 OR $input['quality_score'] > 5))
			{
				$errors[] = 'Skor kualitas 1-5.';
			}

			if (empty($errors))
			{
				$input['lead_time_days'] = (int) $input['lead_time_days'];
				$id = $this->Supplier_model->save($input, $is_edit ? $s['id'] : NULL);
				$this->audit->log($is_edit ? 'supplier_update' : 'supplier_create', array(
					'table_name' => 'suppliers', 'record_id' => $id, 'detail' => array('name' => $input['name']),
				));
				flash('success', "Supplier {$input['name']} disimpan.");
				redirect('inventory/suppliers/show/' . $id);
			}
		}

		$this->render('inventory/suppliers/form', array(
			'title'  => $is_edit ? 'Edit Supplier' : 'Tambah Supplier',
			's'      => $s,
			'input'  => $input,
			'terms'  => Supplier_model::$payment_terms,
			'errors' => $errors,
		));
	}
}
