<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Meja & denah (PRD 2.4.3 dine-in, waitstaff "table management").
 * Lihat status: kasir/pelayan. Ubah master meja: sales.edit_order (manajer).
 */
class Tables extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('sales.process', 'sales.order', 'sales.edit_order')))
		{
			$this->require_permission('sales.order');
		}
	}

	public function index()
	{
		$tables = $this->db->query(
			"SELECT t.*, o.id AS order_id, o.order_number, o.subtotal, o.created_at AS order_since, o.guest_count,
				(SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id AND i.kitchen_status = 'ready') AS ready_items
			 FROM dining_tables t LEFT JOIN orders o ON o.table_id = t.id AND o.status = 'open'
			 ORDER BY t.area, t.sort_order, t.name"
		)->result_array();
		$this->render('sales/tables/index', array('title' => 'Meja', 'tables' => $tables));
	}

	public function save()
	{
		$this->require_permission('sales.edit_order');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$id = (int) $this->input->post('id');
		$row = array(
			'name'       => trim((string) $this->input->post('name')),
			'area'       => trim((string) $this->input->post('area')) ?: NULL,
			'capacity'   => max(1, min(99, (int) $this->input->post('capacity'))),
			'sort_order' => (int) $this->input->post('sort_order'),
			'is_active'  => $this->input->post('is_active') ? 1 : 0,
		);
		if ($row['name'] === '' OR mb_strlen($row['name']) > 30)
		{
			flash('danger', 'Nama meja wajib diisi (maks. 30 karakter).');
			redirect('sales/tables');
		}
		$this->db->where('name', $row['name']);
		if ($id)
		{
			$this->db->where('id !=', $id);
		}
		if ($this->db->count_all_results('dining_tables'))
		{
			flash('danger', "Meja {$row['name']} sudah ada.");
			redirect('sales/tables');
		}
		if ($id)
		{
			$this->db->where('id', $id)->update('dining_tables', $row);
		}
		else
		{
			$this->db->insert('dining_tables', $row);
			$id = (int) $this->db->insert_id();
		}
		$this->audit->log('table_save', array('table_name' => 'dining_tables', 'record_id' => $id, 'detail' => $row));
		flash('success', "Meja {$row['name']} disimpan.");
		redirect('sales/tables');
	}

	public function delete($id = NULL)
	{
		$this->require_permission('sales.edit_order');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$t = $this->db->where('id', (int) $id)->get('dining_tables')->row_array();
		if ( ! $t)
		{
			show_404();
		}
		if ($this->db->where('table_id', $t['id'])->count_all_results('orders'))
		{
			$this->db->where('id', $t['id'])->update('dining_tables', array('is_active' => 0));
			flash('info', "Meja {$t['name']} sudah punya riwayat pesanan, jadi dinonaktifkan.");
		}
		else
		{
			$this->db->where('id', $t['id'])->delete('dining_tables');
			flash('success', "Meja {$t['name']} dihapus.");
		}
		$this->audit->log('table_delete', array('table_name' => 'dining_tables', 'record_id' => $t['id'], 'detail' => array('name' => $t['name'])));
		redirect('sales/tables');
	}
}
