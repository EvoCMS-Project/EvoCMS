<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_security', true);

$types = [
	'username' => __('admin/security.select_username'),
	'email' => __('admin/security.select_email'),
	'ip' => __('admin/security.select_ip'),
	'country' => __('admin/security.table_country'),
];

$tab = App::GET('tab', 'list');
$filter = App::GET('filter', '');

if ($rule = App::POST('rule') ?: App::POST('country')) {
	Db::Insert('{banlist}', [
		'type' => App::POST('type'),
		'rule' => str_replace(['*', '_', '?'], ['%', '\_', '_'], $rule),
		'reason' => App::POST('reason'),
		'created' => time(),
		'expires' => (int) strtotime(App::POST('expires')),
	]);

	$uid = App::POST('type') == 'username' ? (int) Db::Get('select id from {users} where username  = ?', $rule) : 0;
	App::logEvent($uid, 'admin', __('admin/security.logevent_add_rule') . App::POST('type') . ' = ' . $rule);

	App::setSuccess(__('admin/security.alert_rule_add_success'));
	$tab = 'list';
} elseif (App::POST('delete')) {
	$rule = Db::Get('select * from {banlist} where id = ?', App::POST('delete'));
	$uid = $rule['type'] == 'username' ? (int) Db::Get('select id from {users} where username  = ?', $rule['rule']) : 0;

	Db::Delete('banlist', ['id' => App::POST('delete')]);
	App::logEvent($uid, 'admin', __('admin/security.logevent_del_rule') . $rule['type'] . ' = ' . $rule['rule']);

	App::setSuccess(__('admin/security.alert_rule_del_success'));
	$tab = 'list';
}

$filters = preg_split('/\s*,\s*/', trim($filter), -1, PREG_SPLIT_NO_EMPTY);
$where = [];
$args = [];

foreach ($filters as $filter_item) {
	$where[] = 'rule LIKE ?';
	$args[] = "%$filter_item%";
}

if ($where) {
	$banlist = Db::QueryAll('select * from {banlist} where ' . implode(' or ', $where), $args);
} else {
	$banlist = Db::QueryAll('select * from {banlist}');
}

$security_stats = admin_security_build_stats($banlist);
$delete_confirm = html_encode(__('admin/security.delete_confirm'));

$security_nav = [
	'list' => ['label' => __('admin/security.tab_list'), 'icon' => 'fa-list'],
	'add' => ['label' => __('admin/security.tab_add'), 'icon' => 'fa-plus-circle'],
];
?>

<div class="admin-dashboard admin-security">
	<?= admin_stat_grid($security_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-security-board">
		<?= admin_security_nav($security_nav, $tab) ?>

		<div class="tab-content admin-tabs-board__body admin-security-board__body admin-tabs-panel admin-security-board__body--content">
			<?= admin_security_tab_open('list', $tab === 'list') ?>
				<?= admin_security_list_board($banlist, $types, $delete_confirm, $filter) ?>
			<?= admin_security_tab_close() ?>

			<?= admin_security_tab_open('add', $tab === 'add') ?>
				<?= admin_security_form_board($types) ?>
			<?= admin_security_tab_close() ?>
		</div>
	</section>
</div>
