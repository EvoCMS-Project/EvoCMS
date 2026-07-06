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
$tab = App::GET('tab', 'list');

if (App::POST('save') && isset($server_types[$inserts['type']])) {

	$inserts['poll_interval'] = (int)$inserts['poll_interval'];
	$inserts['id'] = (int)$inserts['id'];

	if ($inserts['name'] === '' || $inserts['address'] == '') {
		App::setWarning(__('admin/general.server_alert_host_miss'));
		$tab = 'form';
	} else if ($inserts['id']) {
		if (Db::Update('servers', $inserts, ['id' => $inserts['id']])) {
			App::setSuccess(__('admin/general.server_alert_server_updtd'));
			$tab = 'list';
		} else {
			$tab = 'form';
		}
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
			$tab = 'list';
		} else {
			$tab = 'form';
		}
	}

	if (Db::$error) {
		App::setWarning((string)Db::$error);
		$tab = 'form';
	}
}
elseif (App::POST('del_serv')) {
	if (Db::Delete('servers', ['id' => App::POST('del_serv')])) {
		App::setSuccess(__('admin/general.server_alert_server_dltd'));
	} else {
		App::setWarning(__('admin/general.server_alert_server_ndltd'));
	}
	$tab = 'list';
}

$servers = Db::QueryAll('select * FROM {servers} ORDER BY name ASC', true);

if (isset($servers[App::POST('edit_serv', App::POST('id'))])) {
	$cur_serv = $servers[App::POST('edit_serv', App::POST('id'))];
	$tab = 'form';
} elseif ($tab === 'form' && App::GET('edit')) {
	$edit_id = (int) App::GET('edit');
	if (isset($servers[$edit_id])) {
		$cur_serv = $servers[$edit_id];
	}
}

if (IS_POST && App::POST('edit_serv')) {
	$tab = 'form';
}

if ($tab === 'form' && IS_POST && App::POST('save')) {
	$cur_serv = array_merge($fields, $inserts);
}

$servers_stats = admin_servers_build_stats($servers, $server_types);
$delete_confirm = html_encode(__('admin/servers.delete_confirm'));

$servers_nav = [
	'list' => ['label' => __('admin/servers.tab_list'), 'icon' => 'fa-list'],
	'form' => [
		'label' => $cur_serv['id'] ? __('admin/servers.tab_edit') : __('admin/servers.tab_form'),
		'icon' => $cur_serv['id'] ? 'fa-pencil-alt' : 'fa-plus-circle',
	],
];
?>

<div class="admin-dashboard admin-servers">
	<?= admin_stat_grid($servers_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-servers-board">
		<?= admin_servers_nav($servers_nav, $tab) ?>

		<div class="tab-content admin-tabs-board__body admin-servers-board__body admin-tabs-panel admin-servers-board__body--content">
			<?= admin_servers_tab_open('list', $tab === 'list') ?>
				<?= admin_servers_list_board($servers, $server_types, $delete_confirm) ?>
			<?= admin_servers_tab_close() ?>

			<?= admin_servers_tab_open('form', $tab === 'form') ?>
				<?= admin_servers_form_board($cur_serv, $server_types) ?>
			<?= admin_servers_tab_close() ?>
		</div>
	</section>
</div>
