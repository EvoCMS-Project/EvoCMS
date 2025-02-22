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
		App::setWarning('Votre serveur doit avoir un nom et adresse!');
	} else if ($inserts['id']) {
		if (Db::Update('servers', $inserts, ['id' => $inserts['id']]))
			App::setSuccess('Serveur mis à jour!');
	} else {
		unset($inserts['id']);
		try {
			$success = Db::Insert('servers', $inserts);
		} catch (PDOException $e) {
			// Legacy SQLite still has those non null columns with a unique constraint...
			$success = Db::Insert('servers', $inserts + ['host' => random_hash(), 'port' => rand()]);
		}
		if ($success) {
			App::setSuccess('Serveur ajouté!');
		}
	}

	if (Db::$error)
		App::setWarning((string)Db::$error);
}
elseif (App::POST('del_serv')) {
	if (Db::Delete('servers', ['id' => App::POST('del_serv')])) {
		App::setSuccess('Serveur supprimé!');
	} else {
		App::setWarning('Aucun serveur supprimé!');
	}
}
$servers = Db::QueryAll('select * FROM {servers} ORDER BY name ASC', true);

if (isset($servers[App::POST('edit_serv', App::POST('id'))])) {
	$cur_serv = $servers[App::POST('edit_serv', App::POST('id'))];
}
?>
<legend>Liste des serveurs</legend>
<form method="post">
<?php if (!$servers): ?>
	<div style="text-align: center;" class="alert alert-warning">Aucun serveur trouvé!</div>
<?php else: ?>
	<table class="table">
		<thead>
			<tr>
				<th></th>
				<th>Nom</th>
				<th>Type</th>
				<th>Address</th>
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
						<button name="edit_serv" value="'.$serv['id'].'" class="btn btn-sm btn-primary" title="Éditer ce serveur"><i class="fa fa-pencil-alt"></i></button>
						<button name="del_serv" value="'.$serv['id'].'" class="btn btn-sm btn-danger" title="Supprimer ce serveur" onclick="return confirm(\'Sur?\');"><i class="far fa-trash-alt"></i>
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
		echo '<legend>Modifier le serveur #'.$cur_serv['id'].'</legend>';
	else
		echo '<legend>Ajouter un serveur</legend>';
?>
	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="name">Nom :</label>
		<div class="col-sm-8 controls">
				<input class="form-control" id="name" name="name" type="text" value="<?=html_encode($cur_serv['name'])?>">
		</div>
	</div>

	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="account">Type :</label>
		<div class="col-sm-8 controls">
			<?= Widgets::select('type', $server_types, $cur_serv['type'], true, 'class="form-control" id="account"') ?>
		</div>
	</div>

	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="address">Adresse <i class="fa fa-question-circle" title="Selon le serveur, le format peut etre IP:PORT ou scheme://url/to/server"></i> :</label>
		<div class="col-sm-8 controls">
			<input class="form-control" id="address" name="address" type="text" value="<?=html_encode($cur_serv['address'])?>">
		</div>
	</div>

	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="password">Password <i class="fa fa-question-circle" title="..."></i> :</label>
		<div class="col-sm-8 controls">
			<input class="form-control" id="password" name="password" type="text" value="<?=html_encode($cur_serv['password'])?>">
		</div>
	</div>

	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="poll_interval">Polling <small>(secondes)</small> :</label>
		<div class="col-sm-8 controls">
			<input class="form-control" id="poll_interval" name="poll_interval" type="text" value="<?=$cur_serv['poll_interval']?>">
		</div>
	</div>

	<div class="text-center">
		<input type="hidden" name="id" value="<?=$cur_serv ? $cur_serv['id'] : 0?>">
		<button class="btn btn-medium btn-primary" name="save" value="1" type="submit">Enregistrer le serveur</button> <button class="btn btn-danger">Annuler</button>
	</div>
</form>
