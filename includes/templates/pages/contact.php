<?php if ($mail_status === 'yes') { ?>
	<div class="bs-callout bs-callout-success"><?= __('contact.confirm') ?></div>
<?php } else { ?>
	<?php if($mail_status === 'error') { ?>
		<div class="bs-callout bs-callout-danger"><?= __('contact.error') ?></div>
	<?php } elseif ($mail_status === 'incomplete') { ?>
		<div class="bs-callout bs-callout-danger"><?= __('contact.alert') ?></div>
	<?php } ?>
	<form method="post" class="form-horizontal">
		<legend><?= __('contact.title') ?></legend>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="username"><?= __('contact.name') ?> :</label>
			<div class="col-sm-6">
				<input class="form-control" name="username" value="<?= html_encode(App::POST('username', App::getCurrentUser()->username)) ?>" type="text">
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right"  for="mail"><?= __('contact.email') ?> :</label>
			<div class="col-sm-6">
				<input class="form-control" name="email" value="<?= html_encode(App::POST('email', App::getCurrentUser()->email)) ?>" type="text">
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right"><?= __('contact.subject') ?> :</label>
			<div class="col-sm-6">
				<input class="form-control" name="sujet" type="text" value="<?= html_encode(App::POST('sujet', '')) ?>" />
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right"><?= __('contact.message') ?> :</label>
			<div class="col-sm-8">
				<textarea class="form-control" rows="10" name="message" type="text"><?= html_encode(App::POST('message', '')) ?></textarea>
			</div>
		</div>
		<div class="form-group row">
			<div class="col-sm-4"></div>
			<div class="col-sm-4">
				<input class="btn btn-medium btn-block btn-primary" type="submit" name="envoi" value="<?= __('form.send') ?>">
			</div>
			<div class="col-sm-4"></div>
		</div>
	</form>
<?php } ?>