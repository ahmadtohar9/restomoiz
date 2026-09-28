<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengaturan aplikasi (tarif pajak, limit refund/PO, dll).
 * Nilai default dibuat oleh `cli seed`; halaman ini hanya mengubah nilai.
 */
class Settings extends MY_Controller {

	/** Key yang harus berupa angka >= 0. */
	protected $numeric = array(
		'tax_rate', 'service_charge_rate', 'refund_auto_limit', 'refund_auto_minutes',
		'refund_owner_limit', 'po_auto_limit', 'po_owner_limit', 'cash_variance_limit',
		'expiry_alert_days', 'slow_moving_days', 'dead_stock_days', 'invoice_match_tolerance', 'po_variance_flag',
	);
	protected $boolean = array('tax_enabled');

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('admin.settings');
	}

	public function index()
	{
		$settings = $this->db->order_by('key')->get('app_settings')->result_array();
		$errors = array();

		if ($this->input->method() === 'post')
		{
			$posted = (array) $this->input->post('settings');
			$changes = array();
			foreach ($settings as &$s)
			{
				$key = $s['key'];
				if (in_array($key, $this->boolean, TRUE))
				{
					$value = ! empty($posted[$key]) ? '1' : '0';
				}
				elseif ( ! array_key_exists($key, $posted))
				{
					continue;
				}
				else
				{
					$value = trim((string) $posted[$key]);
				}

				if (in_array($key, $this->numeric, TRUE) && ( ! is_numeric($value) OR $value < 0))
				{
					$errors[] = "{$s['description']}: harus angka >= 0.";
					continue;
				}
				if ($key === 'tax_rate' && $value > 100)
				{
					$errors[] = 'Tarif PPN tidak boleh lebih dari 100%.';
					continue;
				}
				if ($value !== (string) $s['value'])
				{
					$changes[$key] = array('from' => $s['value'], 'to' => $value);
					$s['value'] = $value;
				}
			}
			unset($s);

			if (empty($errors))
			{
				foreach ($changes as $key => $c)
				{
					$this->db->where('key', $key)->update('app_settings', array('value' => $c['to'], 'updated_by' => $this->current_user['id']));
				}
				if ($changes)
				{
					$this->audit->log('settings_update', array('table_name' => 'app_settings', 'detail' => $changes));
					flash('success', 'Pengaturan disimpan.');
				}
				else
				{
					flash('info', 'Tidak ada perubahan.');
				}
				redirect('admin/settings');
			}
		}

		$this->render('admin/settings/index', array(
			'title'    => 'Pengaturan',
			'settings' => $settings,
			'errors'   => $errors,
			'boolean'  => $this->boolean,
		));
	}
}
