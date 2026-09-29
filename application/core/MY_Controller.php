<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base controller untuk semua halaman yang butuh login.
 *
 * - Redirect ke login kalau belum login atau user sudah dinonaktifkan.
 * - Paksa ganti password kalau must_change_password = 1.
 * - $this->require_permission() untuk proteksi per aksi.
 */
class MY_Controller extends CI_Controller {

	/** @var array|null */
	protected $current_user;

	/** Halaman yang tetap boleh diakses saat user wajib ganti password. */
	protected $allow_during_password_change = FALSE;

	public function __construct()
	{
		parent::__construct();
		$this->load->library('rbac');
		$this->load->library('audit_logger', NULL, 'audit');

		$this->current_user = $this->rbac->current_user();
		if ( ! $this->current_user)
		{
			if ($this->session->userdata('user_id'))
			{
				// User dihapus/dinonaktifkan saat sesi masih aktif.
				$this->session->sess_destroy();
			}
			if ($this->input->is_ajax_request())
			{
				$this->output->set_status_header(401);
				$this->_json(array('message' => 'Sesi berakhir, silakan login ulang.'));
				exit;
			}
			redirect('login?next=' . rawurlencode(uri_string()));
		}

		if ($this->current_user['must_change_password'] && ! $this->allow_during_password_change)
		{
			redirect('profile/password');
		}

		$this->load->vars(array('current_user' => $this->current_user));
	}

	/**
	 * Hentikan request dengan 403 kalau user tidak punya permission.
	 * Percobaan akses ditolak dicatat di audit log (PRD 3.3 Tab 5).
	 */
	protected function require_permission($permission)
	{
		if ($this->rbac->has_permission($permission))
		{
			return;
		}

		$this->audit->log('access_denied', array(
			'result' => 'denied',
			'detail' => $permission . ' @ ' . uri_string(),
		));

		$this->output->set_status_header(403);
		if ($this->input->is_ajax_request())
		{
			$this->_json(array('message' => 'Anda tidak memiliki akses untuk aksi ini.'));
		}
		else
		{
			$this->render('errors_app/denied', array('title' => 'Akses Ditolak', 'permission' => $permission));
		}
		$this->output->_display();
		exit;
	}

	/** Render view di dalam layout utama. */
	protected function render($view, array $data = array())
	{
		$data['content_view'] = $view;
		// Dibuka di modal (ui.js mengirim header X-Modal): hanya isi halaman, tanpa sidebar/topbar.
		if ($this->input->get_request_header('X-Modal') === '1')
		{
			$data['is_modal'] = TRUE;
			$this->output->set_header('X-Page-Title: ' . rawurlencode(isset($data['title']) ? $data['title'] : ''));
			$this->output->set_header('Cache-Control: no-store');
			$this->load->view('layout/modal', $data);
			return;
		}
		$data['is_modal'] = FALSE;
		$this->load->view('layout/main', $data);
	}

	protected function _json($data)
	{
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($data));
	}
}
