<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Stock opname / hitung fisik.
 */
class Opname extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('inventory.view');
		$this->load->model(array('Opname_model', 'Category_model'));
	}

	public function index()
	{
		$this->render('inventory/opname/index', array(
			'title'      => 'Stock Opname',
			'rows'       => $this->Opname_model->all(),
			'categories' => $this->Category_model->options(TRUE),
			'can_cost'   => can('inventory.view_cost'),
		));
	}

	public function create()
	{
		$this->require_permission('inventory.adjust');
		$this->_require_post();

		$date = (string) $this->input->post('opname_date');
		$d = DateTime::createFromFormat('Y-m-d', $date);
		if ( ! $d OR $d->format('Y-m-d') !== $date OR $date > date('Y-m-d'))
		{
			flash('danger', 'Tanggal opname tidak valid atau di masa depan.');
			redirect('inventory/opname');
		}
		$category_id = (int) $this->input->post('category_id');
		$category_ids = array();
		if ($category_id)
		{
			if ( ! $this->Category_model->find($category_id))
			{
				show_404();
			}
			$category_ids = $this->Category_model->descendant_ids($category_id);
		}

		$id = $this->Opname_model->create($date, $category_id, trim((string) $this->input->post('notes')), $category_ids);
		if ( ! $id)
		{
			flash('danger', 'Gagal membuat lembar opname.');
			redirect('inventory/opname');
		}
		$opname = $this->Opname_model->find($id);
		if ( ! $this->Opname_model->items($id))
		{
			$this->Opname_model->set_status($id, 'cancelled');
			flash('warning', 'Tidak ada bahan aktif di kategori itu. Lembar opname dibatalkan.');
			redirect('inventory/opname');
		}
		$this->audit->log('opname_create', array('table_name' => 'stock_opnames', 'record_id' => $opname['opname_no']));
		redirect('inventory/opname/show/' . $id);
	}

	/** Lembar hitung (draft: bisa diisi; posted: hasil selisih). */
	public function show($id = NULL)
	{
		$opname = $this->Opname_model->find($id);
		if ( ! $opname)
		{
			show_404();
		}
		$items = $this->Opname_model->items($opname['id']);

		if ($this->input->method() === 'post')
		{
			$this->require_permission('inventory.adjust');
			if ($opname['status'] !== 'draft')
			{
				flash('warning', 'Opname ini sudah tidak bisa diubah.');
				redirect('inventory/opname/show/' . $opname['id']);
			}

			$posted = (array) $this->input->post('count');
			$notes = (array) $this->input->post('item_notes');
			$counts = array();
			$errors = array();
			foreach ($items as $item)
			{
				$raw = isset($posted[$item['id']]) ? $posted[$item['id']] : '';
				$value = num_in($raw, NULL);
				if ($value !== NULL && (is_nan($value) OR $value < 0))
				{
					$errors[] = "{$item['name']}: jumlah fisik harus angka ≥ 0.";
					continue;
				}
				$counts[$item['id']] = array(
					'qty'   => $value === NULL ? NULL : round($value, 3),
					'notes' => isset($notes[$item['id']]) ? trim((string) $notes[$item['id']]) : '',
				);
			}

			if ($errors)
			{
				flash('danger', implode(' ', $errors));
				redirect('inventory/opname/show/' . $opname['id']);
			}
			$this->Opname_model->save_counts($opname['id'], $counts);

			if ($this->input->post('action') === 'post')
			{
				$this->_post($opname);
			}
			flash('success', 'Hasil hitung tersimpan sebagai draft.');
			redirect('inventory/opname/show/' . $opname['id']);
		}

		$this->render('inventory/opname/show', array(
			'title'    => 'Opname ' . $opname['opname_no'],
			'opname'   => $opname,
			'items'    => $items,
			'can_cost' => can('inventory.view_cost'),
			'can_edit' => $opname['status'] === 'draft' && can('inventory.adjust'),
		));
	}

	public function cancel($id = NULL)
	{
		$this->require_permission('inventory.adjust');
		$this->_require_post();
		$opname = $this->Opname_model->find($id);
		if ( ! $opname OR $opname['status'] !== 'draft')
		{
			show_404();
		}
		$this->Opname_model->set_status($opname['id'], 'cancelled');
		$this->audit->log('opname_cancel', array('table_name' => 'stock_opnames', 'record_id' => $opname['opname_no']));
		flash('success', "Opname {$opname['opname_no']} dibatalkan.");
		redirect('inventory/opname');
	}

	/**
	 * Bukukan selisih semua item yang sudah dihitung. Item yang belum diisi
	 * dilewati (stoknya tidak diubah). Semua dalam satu transaksi.
	 */
	protected function _post(array $opname)
	{
		$this->load->library('stock_service', NULL, 'stock');
		$items = $this->Opname_model->items($opname['id']);
		$counted = array_filter($items, function ($i) { return $i['counted_qty'] !== NULL; });
		if (empty($counted))
		{
			flash('warning', 'Belum ada jumlah fisik yang diisi.');
			redirect('inventory/opname/show/' . $opname['id']);
		}

		try
		{
			$summary = $this->stock->run(function ($stock) use ($opname, $items, $counted) {
				$doc_no = $stock->new_document_no();
				$changed = 0;
				$value = 0.0;
				foreach ($counted as $item)
				{
					$r = $stock->count($item['ingredient_id'], $item['counted_qty'], array(
						'movement_no' => $doc_no,
						'ref_type'    => 'opname',
						'ref_id'      => $opname['opname_no'],
						'notes'       => 'Opname ' . $opname['opname_no'] . ($item['notes'] ? ': ' . $item['notes'] : ''),
					));
					$this->db->where('id', $item['id'])->update('stock_opname_items', array(
						'system_qty'     => $r['system_qty'],
						'variance'       => $r['variance'],
						'variance_value' => $r['variance_value'],
					));
					if ($r['variance'] != 0)
					{
						$changed++;
						$value += $r['variance_value'];
					}
				}
				// Item yang tidak dihitung: catat stok sistem saat posting sebagai referensi.
				foreach ($items as $item)
				{
					if ($item['counted_qty'] === NULL)
					{
						$this->db->where('id', $item['id'])->update('stock_opname_items', array('system_qty' => $item['qty_on_hand']));
					}
				}
				$this->Opname_model->set_status($opname['id'], 'posted', array(
					'posted_by' => $this->current_user['id'],
					'posted_at' => date('Y-m-d H:i:s'),
				));
				return array('changed' => $changed, 'value' => $value, 'counted' => count($counted));
			});
		}
		catch (Stock_exception $e)
		{
			flash('danger', 'Posting gagal, tidak ada stok yang diubah: ' . $e->getMessage());
			redirect('inventory/opname/show/' . $opname['id']);
		}

		$this->audit->log('opname_post', array(
			'table_name' => 'stock_opnames', 'record_id' => $opname['opname_no'],
			'detail' => $summary,
		));
		flash('success', "Opname {$opname['opname_no']} diposting: {$summary['counted']} bahan dihitung, {$summary['changed']} ada selisih.");
		redirect('inventory/opname/show/' . $opname['id']);
	}

	protected function _require_post()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
	}
}
