<?php if (!has_permission()) { ?>
	<div class="bs-callout bs-callout-danger">
		<h4><?= __('errors/401.title') ?></h4>
		<p><?= __('errors/401.message') ?></p>
		<strong><?= html_encode($message) ?></strong>
	</div>
	<?php App::renderTemplate('pages/login.php', ['action' => 'login', 'login' => '', 'password' => '', 'redir' => $_SERVER['REQUEST_URI']]); ?>
<?php } else { ?>
	<div class="bs-callout bs-callout-danger">
		<h4><?= __('errors/403.title') ?></h4>
		<p><?= __('errors/403.message') ?></p>
		<strong><?= html_encode($message) ?></strong>
		<p><a href="#" onclick="window.history.back(-1); return false;"><?= __('errors/back') ?></a>.</p>
	</div>
<?php } ?>