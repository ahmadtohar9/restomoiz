<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Unduh lampiran privat dari storage/ dengan cek permission.
 *   files/view?path=invoices/2026/09/<hash>.pdf
 */
class Files extends MY_Controller {

	public function view()
	{
		$this->load->library('private_upload');
		$path = (string) $this->input->get('path');
		$full = $this->private_upload->resolve($path);
		if ( ! $full)
		{
			show_404();
		}
		$folder = strtok($path, '/');
		$this->require_permission(Private_upload::$folders[$folder]);

		$ext = pathinfo($full, PATHINFO_EXTENSION);
		header('Content-Type: ' . Private_upload::$mimes[$ext]);
		header('Content-Length: ' . filesize($full));
		header('Content-Disposition: inline; filename="' . $folder . '-' . basename($full) . '"');
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: private, max-age=0');
		readfile($full);
		exit;
	}
}
