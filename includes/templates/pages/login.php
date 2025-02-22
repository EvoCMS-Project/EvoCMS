<?php if ($action === 'forget'): ?>
	<form class="form-horizontal" action="<?=App::getURL('login', ['action' => 'forget'])?>" method="post" id="forget">
		<input type="hidden" name="redir" value="user"/>
		<legend><?= __('login.forget_title') ?></legend>
			<div class="form-group row">
				<label class="col-sm-4 col-form-label text-right"><?= __('login.forget_field') ?></label>
				<div class="col-sm-7">
					<input class="form-control" type="text" name="login" value="<?=html_encode($login)?>">
				</div>
			</div>
			<div class="form-group row text-center">
				<button type="submit" class="btn btn-medium btn-warning"><?= __('login.forget_btn') ?></button>
			</div>
	</form>
<?php elseif($action === 'reset'): ?>
	<form class="form-horizontal" method="post" id="forget">
		<input type="hidden" name="redir" value="user"/>
		<legend><?= __('login.reset_title') ?></legend>
			<div class="form-group row">
				<label class="col-sm-4 col-form-label text-right"><?= __('login.reset_username') ?></label>
				<div class="col-sm-7">
					<?=html_encode(App::GET('username'))?>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-4 col-form-label text-right"><?= __('login.reset_password') ?></label>
				<div class="col-sm-6">
					<input class="form-control" type="password" name="new_password" value="<?=html_encode($password)?>">
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-4 col-form-label text-right"><?= __('login.reset_confirmation') ?></label>
				<div class="col-sm-6">
					<input class="form-control" type="password" name="new_password1" value="<?=html_encode($password)?>">
				</div>
			</div>
			<div class="form-group row text-center">
				<button type="submit" class="btn btn-medium btn-warning"><?= __('login.reset_btn') ?></button>
			</div>
	</form>
<?php elseif($action === 'login'): ?>
	<form class="form-horizontal" action="<?=App::getURL('login', ['action' => 'login'])?>" method="post" id="login-form">
		<input type="hidden" name="redir" value="<?=html_encode(App::GET('redir') ?: App::getURL(@$redir ?: 'user'))?>"/>
		<legend><?= __('login.title') ?></legend>
			<div class="form-group row">
				<label class="col-sm-4 col-form-label text-right"><?= __('login.username') ?></label>
				<div class="col-sm-6">
					<input class="form-control" type="text" name="login" value="<?=html_encode($login)?>">
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-4 col-form-label text-right"><?= __('login.password') ?></label>
				<div class="col-sm-6">
					<input class="form-control" type="password" name="pass" value="<?=html_encode($password)?>">
					<label class="col-form-label text-right"><input type="checkbox" name="remember" value="1"> <?= __('login.remember_me') ?></label>
				</div>
			</div>
			<div class="text-center">
				<div class="btn-group">
					<button type="Submit" class="btn btn-medium btn-primary" type="submit" name="connexion"><?= __('login.btn_connect') ?></button>
					<a href="<?=App::getURL('login', ['action' => 'forget'])?>" class="btn btn-medium btn-warning"><?= __('login.btn_forget') ?></a>
				</div>
			</div>
	</form>
<?php endif; ?>
<script>
	$('.alert').removeClass('auto-dismiss');
</script>