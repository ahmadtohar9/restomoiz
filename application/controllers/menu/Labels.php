<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Barcode & QR menu (PRD 2.3.5): pilih varian, cetak label berisi nama,
 * harga, barcode CODE128, dan QR ke menu online.
 */
class Labels extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('menu.view');
		$this->load->model(array('Menu_model', 'Menu_category_model'));
		$this->load->library(array('menu_service', 'barcode'));
	}

	public function index()
	{
		$menus = $this->Menu_model->all(array());
		$ids = array();
		foreach ($menus as $m)
		{
			$ids = array_merge($ids, array_column($m['variants'], 'id'));
		}
		$this->render('menu/labels/index', array(
			'title' => 'Barcode & Label Menu',
			'menus' => $menus,
			'info'  => $this->menu_service->variant_info($ids),
		));
	}

	/** Halaman cetak label untuk varian terpilih (?v[]=1&v[]=2&copies=2&size=m). */
	public function print_labels()
	{
		$ids = array_filter(array_map('intval', (array) $this->input->get('v')));
		if ( ! $ids)
		{
			flash('warning', 'Pilih minimal satu varian untuk dicetak.');
			redirect('menu/labels');
		}
		$copies = max(1, min(50, (int) $this->input->get('copies')));
		$rows = $this->db->select('v.id, v.name, v.barcode, m.id AS menu_id, m.name AS menu_name')
			->from('menu_variants v')->join('menus m', 'm.id = v.menu_id')
			->where_in('v.id', $ids)->order_by('m.name')->order_by('v.sort_order')
			->get()->result_array();
		$this->load->view('menu/labels/print', array(
			'rows'       => $rows,
			'info'       => $this->menu_service->variant_info($ids),
			'copies'     => $copies,
			'show_price' => (bool) $this->input->get('price'),
			'show_qr'    => (bool) $this->input->get('qr') && setting('public_menu_enabled', '1') === '1',
			'resto'      => setting('resto_name', 'Resto Moiz'),
		));
	}

	/** Buat ulang barcode otomatis (mis. label lama rusak / barcode kustom salah). */
	public function regenerate($variant_id = NULL)
	{
		$this->require_permission('menu.edit');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$v = $this->Menu_model->find_variant($variant_id);
		if ( ! $v)
		{
			show_404();
		}
		$code = $this->barcode->generate($v['id']);
		$owner = $this->Menu_model->barcode_owner($code, $v['id']);
		if ($owner)
		{
			flash('danger', "Barcode $code bentrok dengan {$owner['menu_name']} - {$owner['name']}.");
		}
		else
		{
			$this->db->where('id', $v['id'])->update('menu_variants', array('barcode' => $code));
			$this->audit->log('menu_barcode_regenerate', array('table_name' => 'menu_variants', 'record_id' => $v['id'], 'detail' => array('from' => $v['barcode'], 'to' => $code)));
			flash('success', "Barcode {$v['menu_name']} - {$v['name']} diatur ke $code.");
		}
		redirect('menu/items/show/' . $v['menu_id'] . '#v' . $v['id']);
	}

	/** Gambar barcode SVG (untuk ditampilkan/diunduh). */
	public function svg($variant_id = NULL)
	{
		$v = $this->Menu_model->find_variant($variant_id);
		if ( ! $v OR ! $v['barcode'])
		{
			show_404();
		}
		$this->output->set_content_type('image/svg+xml')->set_output($this->barcode->svg($v['barcode']));
	}
}
