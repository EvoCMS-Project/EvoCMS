<?php defined('EVO') or die('Que fais-tu là?');

// has_permission('mod.ban_member', true);
has_permission('admin.manage_security', true);

$filters = preg_split('/\s*,\s*/', App::REQ('filter', ''));
$where = [];
$args = [];

foreach($filters as $filter) {
	$where[] = 'rule LIKE ?';
	$args[] = "%$filter%";
}

$types = array(
	'username' => __('admin/security.select_username'),
	'email' => __('admin/security.select_email'),
	'ip' => __('admin/security.select_ip'),
	'country' => __('admin/security.table_country'),
);

if ($rule = App::POST('rule') ?: App::POST('country')) {
	Db::Insert('{banlist}', array(
		'type' => App::POST('type'),
		'rule' => str_replace(array('*', '_', '?'), array('%', '\_', '_'), $rule),
		'reason' => App::POST('reason'),
		'created' => time(),
		'expires' => (int)strtotime(App::POST('expires'))
	));

	$uid = App::POST('type') == 'username' ? (int)Db::Get('select id from {users} where username  = ?', $rule) : 0;
	App::logEvent($uid, 'admin', __('admin/security.logevent_add_rule') .App::POST('type').' = '.$rule);

	App::setSuccess('Règle ajoutée !');
} elseif (App::POST('delete')) {
	$rule = Db::Get('select * from {banlist} where id = ?', App::POST('delete'));
	$uid = $rule['type'] == 'username' ? (int)Db::Get('select id from {users} where username  = ?', $rule['rule']) : 0;

	Db::Delete('banlist', ['id' => App::POST('delete')]);
	App::logEvent($uid, 'admin', __('admin/security.logevent_del_rule') . $rule['type'] . ' = '. $rule['rule']);

	App::setSuccess(__('admin/security.alert_rule_del_success'));
}

if ($where) {
	$banlist = Db::QueryAll('select * from {banlist} where ' . implode(' or ', $where), $args);
} else {
	$banlist = Db::QueryAll('select * from {banlist}');
}
?>
<div class="banlist">
	<legend><a onclick="$('#banlist').toggle('slow'); return false;" href="#">Sécurité</a></legend>
	<?php if (!$banlist): ?>
		<div class="text-center alert alert-warning"><?= __('admin/security.alert_notfound') ?> !</div>
	<?php else: ?>
			<form method="post" <?php if (App::GET('hide')) echo 'hidden'; ?> id="banlist" action="?page=security">
				<table class="table">
					<thead>
						<tr>
							<th><?= __('admin/security.table_rule') ?></th>
							<th><?= __('admin/security.table_reason') ?></th>
							<th><?= __('admin/security.table_expiration') ?></th>
							<th style="width:90px;"> </th>
						</tr>
					</thead>
					<?php
						foreach($banlist as $ban) {
							echo '<tr ' . ($ban['rule'] == App::GET('username') || $ban['rule'] == App::GET('ip') ? 'class="danger"' : '') .  '>' .
								'<td>' . $types[$ban['type']] . ' = <strong>'. html_encode(str_replace(array('%', '\_'), array('*', '_'), $ban['rule'])) . '</strong></td>'.
								'<td>' . html_encode($ban['reason']) . '</td>'.
								'<td>' . Format::today($ban['expires']) . '</td>'.
								'<td><button class="btn btn-danger btn-sm" name="delete" value="'.$ban['id'].'"><i class="fa fa-times"></i></button></td>'.
								'</tr>';
						}
					?>
				</table>
			</form>
	<?php endif; ?>

	<br>
	<form class="form-horizontal" method="post" action="?page=security">
		<legend><?= __('admin/security.add_title') ?></legend>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="type"><?= __('admin/security.table_type') ?> :</label>
			<div class="col-sm-8 controls">
				<?= Widgets::select('type', $types); ?>
				<small><?= __('admin/security.small_type') ?></small>
			</div>
		</div>
		<div class="row ban ban-username ban-email ban-ip">
			<label class="col-sm-3 col-form-label text-right" for="rule"><?= __('admin/security.table_rule') ?> :</label>
			<div class="col-sm-8 controls">
				<input class="form-control" data-autocomplete="userlist" name="rule" id="rule" type="text" <?php if (App::GET('username')) echo 'value="' . html_encode(App::GET('username')) .'" style="background-color:pink;"'; ?>>
				<small><?= __('admin/security.small_rule') ?></small>
			</div>
		</div>
		<div class="row ban ban-country">
			<label class="col-sm-3 col-form-label text-right"><?= __('admin/security.table_country') ?> :</label>
			<div class="col-sm-8">
				<?= Widgets::select('country', COUNTRIES); ?>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="reason"><?= __('admin/security.table_reason') ?> :</label>
			<div class="col-sm-8 controls">
				<input class="form-control" name="reason" type="text" value="">
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="expires"><?= __('admin/security.table_expiration') ?> :</label>
			<div class="col-sm-8 controls">
				<input class="form-control" name="expires" type="text" value="+1 week">
				<small><?= __('admin/security.small_expiration') ?> : <a href="http://php.net/manual/fr/function.strtotime.php">strtotime</a>.</small>
			</div>
		</div>
		<div class="text-center">
			<button class="btn btn-primary" name="add_menu" value="" type="submit"><?= __('admin/general.btn_save') ?></button>
		</div>
	</form>
</div>
<script>
$('#type').change(function(e){
	switch(this.value) {
		case 'ip':
			$('#rule').val('<?php echo addslashes(App::GET('ip'))?>').removeAttr('data-autocomplete');
			break;
		case 'username':
			$('#rule').val('<?php echo addslashes(App::GET('username'))?>').attr('data-autocomplete', 'userlist');
			break;
		case 'email':
			$('#rule').val('<?php echo addslashes(App::GET('email'))?>').removeAttr('data-autocomplete');
			break;
	}

	$('.ban').hide().val('');
	$('.ban.ban-' + this.value).show();
}).change();
</script>