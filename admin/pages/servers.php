<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_servers', true);

$fields = [
	'id' => 0,
	'type' => '',
	'name' => '',
	'address' => '',
	'password' => '',
	'poll_interval' => 0,
	'additional_settings' => ''
];

$server_types = [
	'minecraft' => 'Minecraft',
	'diablo3' => 'Diablo 3',
	'wow' => 'World Of Warcraft',
	'trackmania' => 'Trackmania Nation',
	'source' => 'Source Engine',
	'quake3' => 'Quake 3',
	'' => '--------',
	'shoutcast' => 'SHOUTcast',
];


$cur_serv = $fields;
$inserts = array_filter(array_intersect_key(App::POST(), $cur_serv)) + $fields;

if (App::POST('save') && isset($server_types[$inserts['type']])) {

	$inserts['poll_interval'] = (int)$inserts['poll_interval'];
	$inserts['id'] = (int)$inserts['id'];

	if ($inserts['name'] === '' || $inserts['address'] == '') {
		App::setWarning(__('admin/general.server_alert_host_miss'));
	} else if ($inserts['id']) {
		if (Db::Update('servers', $inserts, ['id' => $inserts['id']]))
			App::setSuccess(__('admin/general.server_alert_server_updtd'));
	} else {
		unset($inserts['id']);
		try {
			$success = Db::Insert('servers', $inserts);
		} catch (PDOException $e) {
			// Legacy SQLite still has those non null columns with a unique constraint...
			$success = Db::Insert('servers', $inserts + ['host' => random_hash(), 'port' => rand()]);
		}
		if ($success) {
			App::setSuccess(__('admin/general.server_alert_server_added'));
		}
	}

	if (Db::$error)
		App::setWarning((string)Db::$error);
}
elseif (App::POST('del_serv')) {
	if (Db::Delete('servers', ['id' => App::POST('del_serv')])) {
		App::setSuccess(__('admin/general.server_alert_server_dltd'));
	} else {
		App::setWarning(__('admin/general.server_alert_server_ndltd'));
	}
}
$servers = Db::QueryAll('select * FROM {servers} ORDER BY name ASC', true);

if (isset($servers[App::POST('edit_serv', App::POST('id'))])) {
	$cur_serv = $servers[App::POST('edit_serv', App::POST('id'))];
}
?>
<legend><?= __('admin/general.server_list_title') ?></legend>
<form method="post">
<?php if (!$servers): ?>
	<div style="text-align: center;" class="alert alert-warning"><?= __('admin/general.server_none') ?></div>
<?php else: ?>
	<table class="table">
		<thead>
			<tr>
				<th></th>
				<th><?= __('admin/general.server_name') ?></th>
				<th><?= __('admin/general.server_type') ?></th>
				<th><?= __('admin/general.server_ip') ?></th>
				<th>Polling</th>
				<th> </th>
			</tr>
		</thead>
		<tbody>
		<?php
			foreach ($servers as $serv)
			{
				$type = html_encode($server_types[$serv['type']] ?? $serv['type'].'?');
				echo "<tr>";
					echo '<td><img src="'. App::getAsset('/img/servers/'.$serv['type'].'.png'). '" width="28" title="'.$type.'" /></td>';
					echo '<td><a href="' . App::getURL('server', $serv['id']) . '">' . html_encode($serv['name']).'</a></td>';
					echo '<td>'.$type.'</td>';
					echo '<td>'.html_encode($serv['address']).'</td>';
					echo '<td>'.($serv['poll_interval'] ?: 'off').'</td>';
					echo '<td>
						<button name="edit_serv" value="'.$serv['id'].'" class="btn btn-sm btn-primary" title="'. __('admin/general.server_btn_title_edit') .'"><i class="fa fa-pencil-alt"></i></button>
						<button name="del_serv" value="'.$serv['id'].'" class="btn btn-sm btn-danger" title="'. __('admin/general.server_btn_title_delete') .'" onclick="return confirm(\'Sur?\');"><i class="far fa-trash-alt"></i>
						</button>';
					echo '</td>';
				echo "</tr>";
			}
		?>
		</tbody>
	</table>
<?php endif; ?>
</form>
</br>
<form class="form-horizontal" method="post" id="edit">
<?php
	if ($cur_serv['id'])
		echo '<legend>'. __('admin/general.server_edit_title') .' '.$cur_serv['id'].'</legend>';
	else
		echo '<legend>'. __('admin/general.server_add_title') .'</legend>';
?>
	<div class="mb-3 row">
		<label class="col-sm-3 col-form-label text-end" for="name"><?= __('admin/general.server_name') ?> :</label>
		<div class="col-sm-8 controls">
				<input class="form-control" id="name" name="name" type="text" value="<?=html_encode($cur_serv['name'])?>">
		</div>
	</div>

	<div class="mb-3 row">
		<label class="col-sm-3 col-form-label text-end" for="account"><?= __('admin/general.server_type') ?> :</label>
		<div class="col-sm-8 controls">
			<?= Widgets::select('type', $server_types, $cur_serv['type'], true, 'class="form-control" id="account"') ?>
		</div>
	</div>

	<div class="mb-3 row">
		<label class="col-sm-3 col-form-label text-end" for="address"><?= __('admin/general.server_ip') ?> <i class="fa fa-question-circle" title="<?= __('admin/general.server_title_ph') ?>"></i> :</label>
		<div class="col-sm-8 controls">
			<input class="form-control" id="address" name="address" type="text" value="<?=html_encode($cur_serv['address'])?>">
		</div>
	</div>

	<div class="mb-3 row">
		<label class="col-sm-3 col-form-label text-end" for="password"><?= __('admin/general.server_password') ?> <i class="fa fa-question-circle" title="..."></i> :</label>
		<div class="col-sm-8 controls">
			<input class="form-control" id="password" name="password" type="text" value="<?=html_encode($cur_serv['password'])?>">
		</div>
	</div>

	<div class="mb-3 row">
		<label class="col-sm-3 col-form-label text-end" for="poll_interval">Polling <small>(secondes)</small> :</label>
		<div class="col-sm-8 controls">
			<input class="form-control" id="poll_interval" name="poll_interval" type="text" value="<?=$cur_serv['poll_interval']?>">
		</div>
	</div>

	<div class="text-center">
		<input type="hidden" name="id" value="<?=$cur_serv ? $cur_serv['id'] : 0?>">
		<button class="btn btn-primary" name="save" value="1" type="submit"><?= __('admin/general.server_btn_save') ?></button> <button class="btn btn-danger"><?= __('admin/menu.btn_cancel') ?></button>
	</div>
</form>
