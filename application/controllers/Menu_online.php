<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Menu online publik (PRD 2.3.5 QR Integration): halaman responsif tanpa
 * login yang dibuka pelanggan lewat QR. Hanya menampilkan menu aktif di
 * kategori yang tidak disembunyikan, beserta harga yang berlaku.
 */
class Menu_online extends CI_Controller {

	public function index()
	{
		if (setting('public_menu_enabled', '1') !== '1')
		{
			show_404();
		}
		$this->load->model(array('Menu_model', 'Menu_category_model'));
		$this->load->library('menu_service');

		$hidden = $this->Menu_category_model->hidden_ids();
		$categories = array_filter($this->Menu_category_model->tree(TRUE), function ($c) use ($hidden) {
			return ! in_array((int) $c['id'], $hidden, TRUE);
		});
		$menus = array_filter($this->Menu_model->all(array()), function ($m) use ($hidden) {
			return $m['status'] !== 'inactive' && ( ! $m['category_id'] OR ! in_array((int) $m['category_id'], $hidden, TRUE));
		});

		$ids = array();
		foreach ($menus as &$m)
		{
			$m['variants'] = array_values(array_filter($m['variants'], function ($v) { return (int) $v['is_active'] === 1; }));
			$ids = array_merge($ids, array_column($m['variants'], 'id'));
		}
		unset($m);
		$info = $this->menu_service->variant_info($ids);

		$by_cat = array();
		foreach ($menus as $m)
		{
			if ( ! $m['variants'])
			{
				continue;
			}
			$by_cat[(int) $m['category_id']][] = $m;
		}

		$this->output->set_header('Cache-Control: public, max-age=60');
		$this->load->view('menu_online/index', array(
			'resto'      => setting('resto_name', 'Resto Moiz'),
			'categories' => $categories,
			'by_cat'     => $by_cat,
			'info'       => $info,
		));
	}
}
