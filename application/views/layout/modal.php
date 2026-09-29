<?php defined('BASEPATH') OR exit('No direct script access allowed');
// Isi halaman untuk modal (lihat MY_Controller::render dan ui.js). Pesan error validasi ikut tampil.
?>
<div class="ui-modal-content">
	<?php if ( ! empty($errors)): ?>
		<div class="alert alert-danger">
			<div class="fw-semibold mb-1"><i class="bi bi-exclamation-circle"></i> Periksa kembali isian berikut:</div>
			<ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
		</div>
	<?php endif; ?>
	<?php $this->load->view($content_view); ?>
</div>
