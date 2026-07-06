<?php defined('EVO') or die('Que fais-tu là?');
has_permission('admin.change_group', true);

use \Evo\Models\Group;
global $_permissions;

if (isset(App::$POST['update_group'])) {
	if ($group = App::getGroup(App::$POST['update_group'])) {
		$permissions = [];
		$group->name = App::$POST['group_name'];
		$group->role = App::$POST['group_role'] ?? $group->role;
		$group->color = App::$POST['color'];
		$group->save();
		foreach($_permissions as $perm_group => $perms) {
			foreach(array_filter($perms, 'is_array') as $k => $v) {
				foreach($v as $perm => $tag) {
					$permissions[] = [
						'name' => "$perm_group.$perm",
						'group_id' => $group->id,
						'related_id' => -1,
						'value' => empty(App::$POST['perms']["$perm_group.$perm"]) ? 0 : 1
					];
				}
			}
		}
		if (!empty($permissions)) {
			Db::Insert('permissions', $permissions, true);
		}
		App::setSuccess(__('admin/groups.alert_edit_success'));
		App::logEvent(null, 'admin', __('admin/groups.logevent_edit_success',['%group%' => $group->name]));
	}
}
elseif (!empty(App::$POST['new_group_name'])) {
	Db::Insert('groups', array('name' => App::$POST['new_group_name'], 'color' => '1')) && App::setSuccess(__('admin/groups.alert_grp_add_success'));
	App::logEvent(null, 'admin', __('admin/groups.logevent_create_success',['%group%' => App::$POST['new_group_name']]));
}
elseif (!empty(App::$POST['delete_group'])) {
	if (App::$POST['delete_group'] == App::getCurrentUser()->group_id) {
		App::setWarning(__('admin/groups.alert_del_error_myself'));
	}
	elseif (Db::Get('select id from {groups} where id = ? AND internal is not null', App::$POST['delete_group'])) {
		App::setWarning(__('admin/groups.alert_del_error_global'));
	}
	elseif (Db::Delete('groups', 'id = ? AND internal is null', App::$POST['delete_group'])) {
		$group_id = App::POST('delete_new_group') ?: App::getConfig('default_user_group');
		$new_group = Db::Get('select id from {groups} where id = ?', $group_id);
		Db::Update('users', ['group_id' => $new_group ?: 2], ['group_id' => App::$POST['delete_group']]);
		Db::Delete('permissions', ['group_id' => App::$POST['delete_group']]);
		App::setSuccess(__('admin/groups.alert_del_success'));
		App::logEvent(0, 'admin', __('admin/groups.delete_title',['%group%' => App::$POST['group_name']]));
	} else
		App::setWarning(__('admin/groups.alert_del_error'));
}
elseif (isset(App::$POST['reorder'])) {
	foreach(App::$POST['reorder'] as $priority => $k) {
		Db::Update('groups', ['priority' => $priority], ['id' => $k]);
	}
	App::setSuccess(__('admin/groups.alert_menu_success'));
}

$groups = [];

foreach(Group::select() as $group) {
	$groups[$group->id] = [
		'permissions' => $group->getPermissions(),
		'count'       => Db::Get('select count(*) from {users} where group_id = ?', $group->id),
	] + $group->toArray();
}
uasort($groups, function($a, $b) { return $a['priority'] <=> $b['priority']; });

$cur_id = isset($groups[App::GET('id')]) ? App::GET('id') : key($groups);
$group_tabs = [
	'general' => ['label' => __('admin/groups.tab_general'), 'icon' => 'fa-gear'],
];

foreach ($_permissions as $id => $perms) {
	$group_tabs['perms-' . $id] = ['label' => $perms['label'], 'icon' => 'fa-key'];
}

$groups_stats = admin_groups_build_stats($groups);
?>

<div class="admin-dashboard admin-groups">
	<?= admin_stat_grid($groups_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-groups-board">
		<div class="admin-tabs-board__body admin-groups-board__body admin-tabs-panel">
			<?= admin_groups_create_board() ?>

			<?php if (!$groups): ?>
				<?= admin_groups_empty(__('admin/groups.no_perms')) ?>
			<?php else: ?>
				<form method="post" class="admin-groups-form">
					<?= admin_csrf_field() ?>
					<div class="admin-groups-layout">
						<aside class="admin-groups-layout__sidebar">
							<?= admin_groups_list($groups, (int) $cur_id) ?>
						</aside>

						<div class="admin-groups-layout__content">
							<header class="admin-groups-current">
								<span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--primary"><i class="fa-solid fa-users-gear" aria-hidden="true"></i></span>
								<span><?= html_encode(__('admin/groups.management_title')) ?> : <strong class="group-color-<?= html_encode((string) $groups[$cur_id]['color']) ?>"><?= html_encode($groups[$cur_id]['name']) ?></strong></span>
							</header>

							<?= admin_groups_nav($group_tabs, 'general') ?>

							<div class="tab-content admin-tabs-board__body admin-groups-board__body admin-tabs-panel admin-groups-board__body--content">
								<?= admin_groups_tab_open('general', true) ?>
									<?= admin_groups_general_board($groups[$cur_id], $groups, (int) $cur_id) ?>
								<?= admin_groups_tab_close() ?>

								<?php foreach ($_permissions as $id => $perms): ?>
									<?= admin_groups_tab_open('perms-' . $id, false) ?>
										<?= admin_groups_permissions_board((string) $id, $perms, (int) $cur_id) ?>
									<?= admin_groups_tab_close() ?>
								<?php endforeach; ?>

								<footer class="admin-settings-section__footer">
									<div class="text-center">
										<button type="submit" name="update_group" value="<?= (int) $cur_id ?>" class="btn btn-primary">
											<i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i><?= html_encode(__('admin/groups.save')) ?>
										</button>
									</div>
								</footer>
							</div>
						</div>
					</div>
				</form>
			<?php endif; ?>
		</div>
	</section>
</div>
<script>
(function () {
	$('.check-all').on('click', function() {
		var g = $(this).attr('data-group');
		$('[data-group='+g+']').prop('checked', this.checked);
	})
	.prop('indeterminate', true);
})();
</script>
