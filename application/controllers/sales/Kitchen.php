<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Layar dapur / Kitchen Display (PRD 2.4.3 "Kitchen tracking").
 *
 * Dapur (sales.kitchen) menandai item dimasak/siap; pelayan & kasir melihat
 * item yang siap diantar lalu menandainya "diantar". Halaman memuat data
 * lewat JSON dan refresh berkala.
 */
class Kitchen extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('sales.kitchen', 'sales.order', 'sales.process')))
		{
			$this->require_permission('sales.kitchen');
		}
		$this->load->library('pos_service', NULL, 'pos');
	}

	public function index()
	{
		$this->render('sales/kitchen/index', array(
			'title'      => 'Dapur',
			'is_kitchen' => can('sales.kitchen'),
			'refresh'    => max(3, (int) setting('kitchen_refresh_seconds', 10)),
		));
	}

	/** Item yang masih diproses (belum diantar) dari pesanan aktif hari ini & kemarin. */
	public function feed()
	{
		$rows = $this->db->query(
			"SELECT i.id, i.order_id, i.name, i.qty, i.notes, i.modifiers, i.kitchen_status, i.created_at, i.ready_at,
				o.order_number, o.order_type, o.customer_name, o.status AS order_status, t.name AS table_name
			 FROM order_items i
			 JOIN orders o ON o.id = i.order_id
			 LEFT JOIN dining_tables t ON t.id = o.table_id
			 WHERE i.kitchen_status IN ('pending','preparing','ready') AND o.status != 'void'
			   AND i.created_at >= ?
			 ORDER BY i.created_at, i.id",
			array(date('Y-m-d 00:00:00', strtotime('-1 day')))
		)->result_array();

		$orders = array();
		$now = time();
		foreach ($rows as $r)
		{
			$oid = (int) $r['order_id'];
			if ( ! isset($orders[$oid]))
			{
				$orders[$oid] = array('id' => $oid, 'number' => $r['order_number'], 'type' => Pos_service::$order_types[$r['order_type']],
					'table' => $r['table_name'], 'customer' => $r['customer_name'], 'paid' => $r['order_status'] === 'paid',
					'since' => $r['created_at'], 'items' => array());
			}
			$mods = $r['modifiers'] ? array_column(json_decode($r['modifiers'], TRUE), 'name') : array();
			$orders[$oid]['items'][] = array('id' => (int) $r['id'], 'name' => $r['name'], 'qty' => (int) $r['qty'], 'notes' => $r['notes'],
				'modifiers' => $mods, 'status' => $r['kitchen_status'], 'minutes' => (int) floor(($now - strtotime($r['created_at'])) / 60));
		}
		$this->_json(array('orders' => array_values($orders), 'server_time' => date('H:i:s')));
	}

	/** Ubah status satu item atau semua item satu pesanan. */
	public function status()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$status = (string) $this->input->post('status');
		// Memasak & menandai siap = dapur. Menandai diantar = dapur / pelayan / kasir.
		if (in_array($status, array('preparing', 'ready'), TRUE) && ! can('sales.kitchen'))
		{
			$this->output->set_status_header(403);
			return $this->_json(array('ok' => FALSE, 'message' => 'Hanya dapur yang bisa mengubah status masak.'));
		}
		$ids = array();
		if ($this->input->post('order_id'))
		{
			$from = array('preparing' => array('pending'), 'ready' => array('pending', 'preparing'), 'served' => array('ready'));
			$ids = array_column($this->db->select('id')->where('order_id', (int) $this->input->post('order_id'))
				->where_in('kitchen_status', isset($from[$status]) ? $from[$status] : array('__none__'))->get('order_items')->result_array(), 'id');
		}
		elseif ($this->input->post('item_id'))
		{
			$ids = array((int) $this->input->post('item_id'));
		}
		try
		{
			$changed = $this->pos->run(function ($pos) use ($ids, $status) {
				$n = 0;
				foreach ($ids as $id)
				{
					$n += $pos->set_kitchen_status($id, $status) ? 1 : 0;
				}
				return $n;
			});
			$this->_json(array('ok' => TRUE, 'changed' => $changed));
		}
		catch (Pos_exception $e)
		{
			$this->_json(array('ok' => FALSE, 'message' => $e->getMessage()));
		}
	}
}
