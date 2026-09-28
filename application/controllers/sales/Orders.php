<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Riwayat & detail pesanan, struk, tiket dapur, pembatalan sebelum bayar
 * (PRD 2.4.3 - 2.4.4 skenario 1).
 */
class Orders extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('sales.process', 'sales.order', 'sales.edit_order', 'report.operational')))
		{
			$this->require_permission('sales.process');
		}
		$this->load->library('pos_service', NULL, 'pos');
	}

	public function index()
	{
		$date = function ($v, $d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : $d; };
		$f = array(
			'from'   => $date($this->input->get('from'), date('Y-m-d')),
			'to'     => $date($this->input->get('to'), date('Y-m-d')),
			'status' => in_array($this->input->get('status'), array('open', 'paid', 'void'), TRUE) ? $this->input->get('status') : '',
			'type'   => isset(Pos_service::$order_types[$this->input->get('type')]) ? $this->input->get('type') : '',
			'method' => array_key_exists($this->input->get('method'), Promo_engine::$payment_methods) ? $this->input->get('method') : '',
			'q'      => trim((string) $this->input->get('q')),
		);
		$this->db->select('o.*, t.name AS table_name, u.name AS created_by_name, p.name AS paid_by_name,
				(SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id AND i.kitchen_status != \'void\') AS item_count', FALSE)
			->from('orders o')
			->join('dining_tables t', 't.id = o.table_id', 'left')
			->join('users u', 'u.id = o.created_by', 'left')
			->join('users p', 'p.id = o.paid_by', 'left');
		if ($f['status'] === 'open')
		{
			// Pesanan terbuka ditampilkan semua, tanpa filter tanggal.
			$this->db->where('o.status', 'open');
		}
		else
		{
			$this->db->where('o.created_at >=', $f['from'] . ' 00:00:00')->where('o.created_at <=', $f['to'] . ' 23:59:59');
			if ($f['status'])
			{
				$this->db->where('o.status', $f['status']);
			}
		}
		if ($f['type'])
		{
			$this->db->where('o.order_type', $f['type']);
		}
		if ($f['method'])
		{
			$this->db->where('o.payment_method', $f['method']);
		}
		if ($f['q'])
		{
			$this->db->group_start()->like('o.order_number', $f['q'])->or_like('o.customer_name', $f['q'])->or_like('o.customer_phone', $f['q'])->group_end();
		}
		$rows = $this->db->order_by('o.id', 'DESC')->limit(500)->get()->result_array();

		$sum = array('paid' => 0, 'total' => 0.0);
		foreach ($rows as $r)
		{
			if ($r['status'] === 'paid')
			{
				$sum['paid']++;
				$sum['total'] += (float) $r['total'];
			}
		}
		$this->render('sales/orders/index', array('title' => 'Transaksi', 'rows' => $rows, 'filters' => $f, 'sum' => $sum));
	}

	public function show($id = NULL)
	{
		$o = $this->_order($id);
		$items = $this->_items($o['id']);
		$quote = NULL;
		if ($o['status'] === 'open' && $items)
		{
			$active = $this->pos->order_items_for_quote($o['id']);
			$quote = $active ? $this->pos->quote($active, array('is_member' => (bool) $o['is_member'], 'customer_id' => $o['customer_id'])) : NULL;
		}
		$this->render('sales/orders/show', array(
			'title'  => 'Pesanan ' . $o['order_number'],
			'o'      => $o,
			'items'  => $items,
			'promos' => $this->db->where('order_id', $o['id'])->get('order_promos')->result_array(),
			'quote'  => $quote,
		));
	}

	/** Struk (thermal 58/80mm). */
	public function receipt($id = NULL)
	{
		$o = $this->_order($id);
		$this->load->view('sales/orders/receipt', array(
			'o'      => $o,
			'items'  => array_filter($this->_items($o['id']), function ($i) { return $i['kitchen_status'] !== 'void'; }),
			'promos' => $this->db->where('order_id', $o['id'])->get('order_promos')->result_array(),
			'paper'  => setting('receipt_paper', '80'),
			'resto'  => array('name' => setting('resto_name', 'Resto Moiz'), 'address' => setting('resto_address', ''), 'phone' => setting('resto_phone', ''),
				'footer' => setting('receipt_footer', ''), 'tax_rate' => (float) setting('tax_rate', 11), 'service_rate' => (float) setting('service_charge_rate', 0)),
		));
	}

	/** Tiket dapur (PRD 2.4.3). ?new=N hanya N item terakhir (tambahan pesanan). */
	public function ticket($id = NULL)
	{
		$o = $this->_order($id);
		$items = array_values(array_filter($this->_items($o['id']), function ($i) { return $i['kitchen_status'] !== 'void'; }));
		$new = (int) $this->input->get('new');
		if ($new > 0 && $new < count($items))
		{
			$items = array_slice($items, -$new);
		}
		$this->load->view('sales/orders/ticket', array('o' => $o, 'items' => $items, 'partial' => $new > 0 && $new < count($this->_items($o['id'])),
			'paper' => setting('receipt_paper', '80')));
	}

	public function void_item($item_id = NULL)
	{
		$this->_require_post();
		$item = $this->db->where('id', (int) $item_id)->get('order_items')->row_array();
		if ( ! $item)
		{
			show_404();
		}
		$this->_check_void_permission(array($item));
		$reason = trim((string) $this->input->post('reason'));
		try
		{
			$returned = $this->pos->run(function ($pos) use ($item, $reason) { return $pos->void_item($item['id'], $reason); });
			$this->audit->log('order_item_void', array('table_name' => 'order_items', 'record_id' => $item['id'],
				'detail' => array('item' => $item['name'], 'qty' => $item['qty'], 'status' => $item['kitchen_status'], 'reason' => $reason, 'stock_returned' => $returned)));
			flash('success', "{$item['name']} dibatalkan. " . ($returned ? 'Stok bahan dikembalikan.' : 'Item sudah dimasak, bahan dicatat terbuang.'));
		}
		catch (Pos_exception $e)
		{
			flash('danger', $e->getMessage());
		}
		redirect('sales/orders/show/' . $item['order_id']);
	}

	public function void_order($id = NULL)
	{
		$this->_require_post();
		$o = $this->_order($id);
		$this->_check_void_permission($this->db->where('order_id', $o['id'])->where('kitchen_status !=', 'void')->get('order_items')->result_array());
		$reason = trim((string) $this->input->post('reason'));
		try
		{
			$waste = $this->pos->run(function ($pos) use ($o, $reason) { return $pos->void_order($o['id'], $reason); });
			$this->audit->log('order_void', array('table_name' => 'orders', 'record_id' => $o['order_number'], 'detail' => array('reason' => $reason, 'wasted_items' => $waste)));
			flash('success', "Pesanan {$o['order_number']} dibatalkan." . ($waste ? " $waste item sudah dimasak, bahannya dicatat terbuang." : ''));
		}
		catch (Pos_exception $e)
		{
			flash('danger', $e->getMessage());
		}
		redirect('sales/orders/show/' . $o['id']);
	}

	public function deliver($id = NULL)
	{
		$this->_require_post();
		if ( ! can_any(array('sales.process', 'sales.order')))
		{
			$this->require_permission('sales.process');
		}
		$o = $this->_order($id);
		try
		{
			$this->pos->run(function ($pos) use ($o) { $pos->mark_delivered($o['id']); });
			$this->audit->log('order_delivered', array('table_name' => 'orders', 'record_id' => $o['order_number']));
			flash('success', "Pesanan {$o['order_number']} ditandai sudah diantar.");
		}
		catch (Pos_exception $e)
		{
			flash('danger', $e->getMessage());
		}
		redirect('sales/orders/show/' . $o['id']);
	}

	/**
	 * Batalkan item yang belum dimasak: kasir (sales.process) boleh.
	 * Item yang sudah dimasak (bahan terbuang): hanya sales.edit_order (manajer).
	 */
	protected function _check_void_permission(array $items)
	{
		$cooked = array_filter($items, function ($i) { return $i['kitchen_status'] !== 'pending' && $i['kitchen_status'] !== 'void'; });
		if ($cooked)
		{
			$this->require_permission('sales.edit_order');
		}
		elseif ( ! can_any(array('sales.process', 'sales.edit_order')))
		{
			$this->require_permission('sales.process');
		}
	}

	protected function _order($id)
	{
		$o = $this->db->select('o.*, t.name AS table_name, u.name AS created_by_name, p.name AS paid_by_name, v.name AS void_by_name')
			->from('orders o')
			->join('dining_tables t', 't.id = o.table_id', 'left')
			->join('users u', 'u.id = o.created_by', 'left')
			->join('users p', 'p.id = o.paid_by', 'left')
			->join('users v', 'v.id = o.void_by', 'left')
			->where('o.id', (int) $id)->get()->row_array();
		if ( ! $o)
		{
			show_404();
		}
		return $o;
	}

	protected function _items($order_id)
	{
		$items = $this->db->select('i.*, u.name AS void_by_name')->from('order_items i')->join('users u', 'u.id = i.void_by', 'left')
			->where('i.order_id', (int) $order_id)->order_by('i.id')->get()->result_array();
		foreach ($items as &$it)
		{
			$it['modifiers'] = $it['modifiers'] ? json_decode($it['modifiers'], TRUE) : array();
		}
		return $items;
	}

	protected function _require_post()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
	}
}
