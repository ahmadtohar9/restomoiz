<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Query baca modul menu. Harga/COGS/porsi dihitung Menu_service::variant_info().
 */
class Menu_model extends CI_Model {

	public static $statuses = array(
		'active'       => 'Aktif',
		'out_of_stock' => 'Habis (manual)',
		'inactive'     => 'Nonaktif',
	);

	/**
	 * Menu + varian. Filter: q, category_ids, status.
	 * @return array menu rows, masing-masing dengan 'variants'
	 */
	public function all(array $f = array())
	{
		$this->db->select('m.*, c.name AS category_name')
			->from('menus m')
			->join('menu_categories c', 'c.id = m.category_id', 'left')
			->order_by('c.sort_order', 'ASC')->order_by('m.sort_order', 'ASC')->order_by('m.name', 'ASC');
		if ( ! empty($f['q']))
		{
			$this->db->group_start()->like('m.name', $f['q'])->or_like('m.code', $f['q'])->group_end();
		}
		if ( ! empty($f['category_ids']))
		{
			$this->db->where_in('m.category_id', $f['category_ids']);
		}
		if ( ! empty($f['status']))
		{
			$this->db->where('m.status', $f['status']);
		}
		$menus = $this->db->get()->result_array();
		return $this->_attach_variants($menus);
	}

	public function find($id)
	{
		$m = $this->db->select('m.*, c.name AS category_name')
			->from('menus m')
			->join('menu_categories c', 'c.id = m.category_id', 'left')
			->where('m.id', (int) $id)
			->get()->row_array();
		if ( ! $m)
		{
			return NULL;
		}
		$rows = $this->_attach_variants(array($m));
		return $rows[0];
	}

	public function find_variant($variant_id)
	{
		return $this->db->select('v.*, m.name AS menu_name, m.status AS menu_status, m.category_id')
			->from('menu_variants v')->join('menus m', 'm.id = v.menu_id')
			->where('v.id', (int) $variant_id)
			->get()->row_array();
	}

	protected function _attach_variants(array $menus)
	{
		if ( ! $menus)
		{
			return $menus;
		}
		$variants = $this->db->where_in('menu_id', array_column($menus, 'id'))
			->order_by('sort_order')->order_by('id')->get('menu_variants')->result_array();
		$by = array();
		foreach ($variants as $v)
		{
			$by[$v['menu_id']][] = $v;
		}
		foreach ($menus as &$m)
		{
			$m['variants'] = isset($by[$m['id']]) ? $by[$m['id']] : array();
		}
		return $menus;
	}

	/** Resep varian dengan biaya per baris. */
	public function recipe($variant_id)
	{
		$this->load->library('menu_service');
		return $this->db->select('r.*, g.code, g.name, g.unit AS std_unit, g.qty_on_hand, g.status AS ingredient_status,
				(' . Menu_service::cost_sql() . ') AS unit_cost, r.qty_std * (' . Menu_service::cost_sql() . ') AS line_cost', FALSE)
			->from('menu_recipes r')
			->join('ingredients g', 'g.id = r.ingredient_id')
			->where('r.variant_id', (int) $variant_id)
			->order_by('r.id')
			->get()->result_array();
	}

	public function price_history($variant_id, $limit = 30)
	{
		return $this->db->select('p.*, u.name AS created_by_name')
			->from('menu_variant_prices p')->join('users u', 'u.id = p.created_by', 'left')
			->where('p.variant_id', (int) $variant_id)
			->order_by('p.effective_from', 'DESC')->limit($limit)
			->get()->result_array();
	}

	public function cogs_history($variant_id, $days = 90)
	{
		return $this->db->where('variant_id', (int) $variant_id)
			->where('recorded_on >=', date('Y-m-d', strtotime("-$days days")))
			->order_by('recorded_on')->get('menu_cogs_history')->result_array();
	}

	public function code_exists($code, $except_id = NULL)
	{
		$this->db->where('code', $code);
		if ($except_id)
		{
			$this->db->where('id !=', (int) $except_id);
		}
		return $this->db->count_all_results('menus') > 0;
	}

	public function barcode_owner($barcode, $except_variant_id = NULL)
	{
		$this->db->select('v.id, v.name, m.name AS menu_name')->from('menu_variants v')->join('menus m', 'm.id = v.menu_id')->where('v.barcode', $barcode);
		if ($except_variant_id)
		{
			$this->db->where('v.id !=', (int) $except_variant_id);
		}
		return $this->db->get()->row_array();
	}

	/** Menu yang memakai bahan tertentu (untuk detail bahan & dampak harga). */
	public function using_ingredient($ingredient_id)
	{
		return $this->db->select('m.id AS menu_id, m.name AS menu_name, v.id AS variant_id, v.name AS variant_name, r.qty, r.unit')
			->from('menu_recipes r')->join('menu_variants v', 'v.id = r.variant_id')->join('menus m', 'm.id = v.menu_id')
			->where('r.ingredient_id', (int) $ingredient_id)
			->order_by('m.name')->get()->result_array();
	}

	/**
	 * Baris keranjang untuk Promo_engine dari [variant_id => qty].
	 * Menyertakan kategori menu beserta seluruh induknya.
	 */
	public function cart_lines(array $qtys, $at = NULL, $member = FALSE)
	{
		$qtys = array_filter(array_map('intval', $qtys), function ($q) { return $q > 0; });
		if ( ! $qtys)
		{
			return array();
		}
		$this->load->library('menu_service');
		$info = $this->menu_service->variant_info(array_keys($qtys), $at);
		$parents = array_column($this->db->select('id, parent_id')->get('menu_categories')->result_array(), 'parent_id', 'id');
		$rows = $this->db->select('v.id, v.name, v.menu_id, m.name AS menu_name, m.category_id')
			->from('menu_variants v')->join('menus m', 'm.id = v.menu_id')
			->where_in('v.id', array_keys($qtys))->get()->result_array();
		$lines = array();
		foreach ($rows as $r)
		{
			$cats = array();
			$c = $r['category_id'];
			while ($c && ! in_array((int) $c, $cats, TRUE))
			{
				$cats[] = (int) $c;
				$c = isset($parents[$c]) ? $parents[$c] : NULL;
			}
			$i = $info[$r['id']];
			$price = (float) $i['price'];
			if ($member && $i['member_price'] !== NULL)
			{
				$price = min($price, $i['member_price']);
			}
			if ($i['bulk_min_qty'] && $qtys[$r['id']] >= $i['bulk_min_qty'])
			{
				$price = min($price, $i['bulk_price']);
			}
			$lines[] = array(
				'variant_id'   => (int) $r['id'],
				'menu_id'      => (int) $r['menu_id'],
				'category_ids' => $cats,
				'qty'          => $qtys[$r['id']],
				'unit_price'   => $price,
				'name'         => $r['menu_name'] . ($r['name'] !== 'Reguler' ? ' - ' . $r['name'] : ''),
			);
		}
		return $lines;
	}

	/** [variant_id => "Menu - Varian"] untuk dropdown. */
	public function variant_options($only_active = TRUE)
	{
		$this->db->select('v.id, v.name, m.name AS menu_name')->from('menu_variants v')->join('menus m', 'm.id = v.menu_id')
			->order_by('m.name')->order_by('v.sort_order');
		if ($only_active)
		{
			$this->db->where('v.is_active', 1)->where('m.status !=', 'inactive');
		}
		$out = array();
		foreach ($this->db->get()->result_array() as $r)
		{
			$out[$r['id']] = $r['menu_name'] . ($r['name'] !== 'Reguler' ? ' - ' . $r['name'] : '');
		}
		return $out;
	}
}
