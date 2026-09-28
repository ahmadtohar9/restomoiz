<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pergerakan stok (PRD 2.1.2): stok masuk, stok keluar/penyesuaian,
 * riwayat pergerakan, dan peringatan stok.
 */
class Stock extends MY_Controller {

	/** Alasan yang boleh dipilih di form (sisanya dari modul lain: PO, penjualan, opname). */
	protected $in_reasons = array('manual_receipt', 'opening_balance', 'adjustment_in');
	protected $out_reasons = array('usage', 'waste', 'expired', 'damaged', 'supplier_return', 'adjustment_out');

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('inventory.view');
		$this->load->model(array('Ingredient_model', 'Supplier_model', 'Stock_model'));
		$this->load->library('stock_service', NULL, 'stock');
	}

	public function index()
	{
		redirect('inventory/stock/movements');
	}

	/** Stok masuk manual (saldo awal, pembelian tanpa PO, penyesuaian +). */
	public function in()
	{
		$this->require_permission('inventory.adjust');
		$this->_document('in');
	}

	/** Stok keluar (pemakaian, waste, kedaluwarsa, rusak, retur, penyesuaian -). */
	public function out()
	{
		$this->require_permission('inventory.adjust');
		$this->_document('out');
	}

	public function movements()
	{
		$f = $this->_movement_filters();
		$page = max(1, (int) $this->input->get('page'));
		$per_page = 50;
		$total = $this->Stock_model->count_movements($f);

		$this->render('inventory/stock/movements', array(
			'title'   => 'Riwayat Pergerakan Stok',
			'rows'    => $this->Stock_model->movements($f, $per_page, ($page - 1) * $per_page),
			'filters' => $f,
			'page'    => $page,
			'pages'   => max(1, (int) ceil($total / $per_page)),
			'total'   => $total,
			'reasons' => Stock_service::$reasons,
			'can_cost' => can('inventory.view_cost'),
		));
	}

	public function export()
	{
		$f = $this->_movement_filters();
		$can_cost = can('inventory.view_cost');
		$rows = array();
		foreach ($this->Stock_model->movements($f, 50000) as $m)
		{
			$line = array($m['created_at'], $m['movement_no'], $m['ingredient_code'], $m['ingredient_name'], $m['type'],
				Stock_service::reason_label($m['reason']), $m['qty'], $m['unit'], $m['qty_before'], $m['qty_after'],
				$m['supplier_name'], $m['notes'], $m['user_name']);
			if ($can_cost)
			{
				$line[] = $m['unit_cost'];
				$line[] = $m['total_cost'];
			}
			$rows[] = $line;
		}
		$header = array('Waktu', 'No. Dokumen', 'Kode', 'Bahan', 'Tipe', 'Alasan', 'Qty', 'Satuan', 'Stok Sebelum', 'Stok Sesudah', 'Supplier', 'Catatan', 'Oleh');
		if ($can_cost)
		{
			array_push($header, 'Harga Satuan', 'Total Nilai');
		}
		send_csv('pergerakan-stok-' . $f['from'] . '_' . $f['to'] . '.csv', $header, $rows);
	}

	public function alerts()
	{
		$this->render('inventory/stock/alerts', array(
			'title'       => 'Peringatan Stok',
			'alerts'      => $this->Stock_model->alerts(),
			'expiry_days' => (int) setting('expiry_alert_days', 7),
			'dead_days'   => (int) setting('dead_stock_days', 60),
			'can_cost'    => can('inventory.view_cost'),
		));
	}

	/**
	 * Form dokumen stok multi-baris. Semua baris disimpan dalam satu
	 * transaksi dengan satu nomor dokumen; kalau satu baris gagal
	 * (mis. stok kurang), tidak ada yang tersimpan.
	 */
	protected function _document($direction)
	{
		$is_in = $direction === 'in';
		$allowed = $is_in ? $this->in_reasons : $this->out_reasons;
		$ingredients = $this->Ingredient_model->for_stock_form();
		$can_cost = can('inventory.view_cost');
		$errors = array();
		$lines = array();
		$header = array(
			'reason'      => $is_in ? 'manual_receipt' : 'usage',
			'supplier_id' => (int) $this->input->get('supplier_id'),
			'notes'       => '',
		);
		$preselect = (int) $this->input->get('ingredient_id');
		if ($preselect && isset($ingredients[$preselect]))
		{
			$lines[] = array('ingredient_id' => $preselect, 'qty' => '', 'unit' => $ingredients[$preselect]['unit'], 'cost' => '', 'expiry_date' => '', 'batch_id' => (int) $this->input->get('batch_id'));
		}

		if ($this->input->method() === 'post')
		{
			$header['reason'] = (string) $this->input->post('reason');
			$header['supplier_id'] = (int) $this->input->post('supplier_id');
			$header['notes'] = trim((string) $this->input->post('notes'));
			if ( ! in_array($header['reason'], $allowed, TRUE))
			{
				$errors[] = 'Pilih jenis transaksi.';
			}
			if ($header['supplier_id'] && ! $this->Supplier_model->find($header['supplier_id']))
			{
				$errors[] = 'Supplier tidak ditemukan.';
			}
			if ( ! $is_in && $header['reason'] === 'supplier_return' && ! $header['supplier_id'])
			{
				$errors[] = 'Retur ke supplier wajib memilih supplier.';
			}
			if (in_array($header['reason'], array('adjustment_in', 'adjustment_out'), TRUE) && $header['notes'] === '')
			{
				$errors[] = 'Penyesuaian wajib diberi alasan di kolom catatan.';
			}

			$post = (array) $this->input->post('lines');
			$lines = array();
			$prepared = array();
			foreach ($post as $i => $l)
			{
				$line = array(
					'ingredient_id' => (int) (isset($l['ingredient_id']) ? $l['ingredient_id'] : 0),
					'qty'           => isset($l['qty']) ? trim((string) $l['qty']) : '',
					'unit'          => isset($l['unit']) ? trim((string) $l['unit']) : '',
					'cost'          => isset($l['cost']) ? trim((string) $l['cost']) : '',
					'expiry_date'   => isset($l['expiry_date']) ? trim((string) $l['expiry_date']) : '',
					'batch_id'      => (int) (isset($l['batch_id']) ? $l['batch_id'] : 0),
				);
				if ( ! $line['ingredient_id'] && $line['qty'] === '')
				{
					continue;
				}
				$lines[] = $line;
				$n = count($lines);

				if ( ! isset($ingredients[$line['ingredient_id']]))
				{
					$errors[] = "Baris $n: pilih bahan baku.";
					continue;
				}
				$ing = $ingredients[$line['ingredient_id']];
				$qty = num_in($line['qty']);
				if (is_nan($qty) OR $qty <= 0)
				{
					$errors[] = "Baris $n ({$ing['name']}): jumlah harus lebih dari 0.";
					continue;
				}
				$factor = NULL;
				foreach ($ing['units'] as $u)
				{
					if ($u['unit'] === $line['unit'])
					{
						$factor = $u['factor'];
					}
				}
				if ($factor === NULL)
				{
					$errors[] = "Baris $n ({$ing['name']}): satuan tidak valid.";
					continue;
				}

				$item = array(
					'ingredient' => $ing,
					'qty_std'    => round($qty * $factor, 3),
					'input_qty'  => $qty,
					'input_unit' => $line['unit'],
				);
				if ($item['qty_std'] <= 0)
				{
					$errors[] = "Baris $n ({$ing['name']}): jumlah terlalu kecil.";
					continue;
				}

				if ($is_in)
				{
					// Harga diinput per satuan yang dipilih, disimpan per satuan standar.
					$cost = $can_cost ? num_in($line['cost'], NAN) : (float) $ing['current_price'] * $factor;
					if (is_nan($cost) OR $cost < 0)
					{
						$errors[] = "Baris $n ({$ing['name']}): harga per {$line['unit']} wajib diisi (boleh 0).";
						continue;
					}
					$item['unit_cost'] = $cost / $factor;
					if ($line['expiry_date'] !== '')
					{
						$d = DateTime::createFromFormat('Y-m-d', $line['expiry_date']);
						if ( ! $d OR $d->format('Y-m-d') !== $line['expiry_date'])
						{
							$errors[] = "Baris $n ({$ing['name']}): tanggal kedaluwarsa tidak valid.";
							continue;
						}
					}
					$item['expiry_date'] = $line['expiry_date'];
				}
				else
				{
					$item['batch_id'] = $line['batch_id'];
				}
				$prepared[] = $item;
			}

			if (empty($lines))
			{
				$errors[] = 'Isi minimal satu baris bahan.';
			}

			// Stok keluar: cek total per bahan di seluruh dokumen terhadap stok saat ini,
			// supaya pesan error menyebut angka stok yang sebenarnya.
			if ( ! $is_in && empty($errors))
			{
				$need = array();
				foreach ($prepared as $p)
				{
					$id = $p['ingredient']['id'];
					$need[$id] = (isset($need[$id]) ? $need[$id] : 0) + $p['qty_std'];
				}
				foreach ($need as $id => $total)
				{
					$ing = $ingredients[$id];
					if ($total > (float) $ing['qty_on_hand'] + 0.0005)
					{
						$errors[] = sprintf('Stok %s tidak cukup: tersedia %s %s, total diminta di dokumen ini %s %s.',
							$ing['name'], qty($ing['qty_on_hand']), $ing['unit'], qty($total), $ing['unit']);
					}
				}
			}

			if (empty($errors))
			{
				try
				{
					$doc_no = $this->stock->run(function ($stock) use ($prepared, $header, $is_in) {
						$doc_no = $stock->new_document_no();
						foreach ($prepared as $p)
						{
							$opts = array(
								'reason'      => $header['reason'],
								'movement_no' => $doc_no,
								'supplier_id' => $header['supplier_id'],
								'notes'       => $header['notes'],
								'input_qty'   => $p['input_qty'],
								'input_unit'  => $p['input_unit'],
							);
							if ($is_in)
							{
								$opts['expiry_date'] = $p['expiry_date'];
								$stock->receive($p['ingredient']['id'], $p['qty_std'], $p['unit_cost'], $opts);
							}
							else
							{
								$opts['batch_id'] = $p['batch_id'];
								$stock->issue($p['ingredient']['id'], $p['qty_std'], $opts);
							}
						}
						return $doc_no;
					});

					$this->audit->log($is_in ? 'stock_in' : 'stock_out', array(
						'table_name' => 'stock_movements', 'record_id' => $doc_no,
						'detail' => array('reason' => $header['reason'], 'lines' => count($prepared)),
					));
					flash('success', "Dokumen $doc_no tersimpan (" . count($prepared) . ' baris).');
					redirect('inventory/stock/movements?q=' . rawurlencode($doc_no) . '&from=' . date('Y-m-d') . '&to=' . date('Y-m-d'));
				}
				catch (Stock_exception $e)
				{
					$errors[] = $e->getMessage();
				}
			}
		}

		if (empty($lines))
		{
			$lines[] = array('ingredient_id' => '', 'qty' => '', 'unit' => '', 'cost' => '', 'expiry_date' => '', 'batch_id' => 0);
		}

		$reason_options = array();
		foreach ($allowed as $r)
		{
			$reason_options[$r] = Stock_service::reason_label($r);
		}

		$this->render('inventory/stock/document', array(
			'title'       => $is_in ? 'Stok Masuk' : 'Stok Keluar / Penyesuaian',
			'is_in'       => $is_in,
			'header'      => $header,
			'lines'       => $lines,
			'ingredients' => $ingredients,
			'reasons'     => $reason_options,
			'suppliers'   => $this->Supplier_model->options(),
			'can_cost'    => $can_cost,
			'errors'      => $errors,
		));
	}

	protected function _movement_filters()
	{
		$date = function ($v, $d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : $d; };
		return array(
			'from'          => $date($this->input->get('from'), date('Y-m-d', strtotime('-6 days'))),
			'to'            => $date($this->input->get('to'), date('Y-m-d')),
			'q'             => trim((string) $this->input->get('q')),
			'type'          => in_array($this->input->get('type'), array('IN', 'OUT', 'ADJ'), TRUE) ? $this->input->get('type') : '',
			'reason'        => array_key_exists($this->input->get('reason'), Stock_service::$reasons) ? $this->input->get('reason') : '',
			'ingredient_id' => (int) $this->input->get('ingredient_id'),
		);
	}
}
