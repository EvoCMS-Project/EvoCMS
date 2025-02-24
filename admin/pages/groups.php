<?php defined('EVO') or die('Que fais-tu là?');
has_permission('admin.change_group', true);

use \Evo\Models\Group;
global $_permissions;

if (isset(App::$POST['update_group'])) {
	if ($group = App::getGroup(App::$POST['update_group'])) {
		$group->name = App::$POST['group_name'];
		$group->role = App::$POST['group_role'];
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

foreach(Group::select() as $group) {
	$groups[$group->id] = [
		'permissions' => $group->getPermissions(),
		'count'       => Db::Get('select count(*) from {users} where group_id = ?', $group->id),
	] + $group->toArray();
}
uasort($groups, function($a, $b) { return $a['priority'] <=> $b['priority']; });

$cur_id = isset($groups[App::GET('id')]) ? App::GET('id') : key($groups);
?>
<div class="card mb-4">
	<div class="card-header p-2"><h4><?= __('admin/groups.creation_title') ?></h4></div>
	<div class="card-body">
		<form class="form-horizontal" role="form" method="post">
			<div class="form-group row">
				<label class="col-sm-3 col-form-label text-right"><?= __('admin/groups.creation_name') ?></label>
				<div class="col-sm-6">
					<input type="text" class="form-control" name="new_group_name">
				</div>
				<button type="submit" class="btn btn-success" style="margin-top: 2px;"><?= __('admin/groups.creation_btn') ?></button>
			</div>
		</form>
	</div>
</div>

<div class="card">
	<div class="card-header p-2">
		<h4 class="panel-title"><?= __('admin/groups.management_title') ?> : <em><?=html_encode($groups[$cur_id]['name']) ?></em></h4>
	</div>
	<div class="card-body">
	<form method="post">
		<div class="form-group row">

			<div class="col-md-3 col-md-pull-9">
				<table id="reorder" class="sortable" cellspacing="0" cellpadding="2" style="width:100%;">
				<?php
					foreach($groups as $id => $group) {
						if ($cur_id == $id) {
							echo '<tr id="'.$group['id'].'"><td class="group-color-'.$group['color'].'"><strong>'.$group['name'].'</strong></td><td></td><td><small>'.$group['count'].' '. __('admin/groups.management_users') .'</small></td></tr>';
						} else {
							echo '<tr id="'.$group['id'].'"><td><a href="?page=groups&id='.$id.'" style="';
							echo '" class="group-color-'.$group['color'].'">'.$group['name'].'</a></td><td></td><td><small>'.$group['count'].' '. __('admin/groups.management_users') .'</small></td></tr>';
						}
					}
				?>
				</table>
			</div>

		  <div class="col-md-9 col-md-push-3"  style="border-left:1px solid #ddd;">
			<ul class="nav nav-tabs">
			  <li class="nav-item"><a class="nav-link active" href="#general" data-toggle="tab"><?= __('admin/groups.tab_general') ?></a></li>
				<?php
				foreach($_permissions as $id => $perms) {
					echo '<li class="nav-item"><a class="nav-link" href="#perms-'.$id.'" data-toggle="tab">'.$perms['label'].'</a></li>';
				}
				?>
			</ul>
			<div class="tab-content panel">
				<div class="tab-pane fade active show p-3" id="general">
					<legend><?= __('admin/groups.config_title') ?> </legend>
					<div class="form-group row" style="height: 30px;">
						<label class="col-sm-5 col_gm col-form-label text-right"><?= __('admin/groups.config_gname') ?></label>
						<div class="col-sm-6">
							<input type="text" class="form-control" name="group_name" value="<?= $groups[$cur_id]['name']?>">
						</div>
					</div>
					<div class="form-group row" style="height: 30px;">
						<label class="col-sm-5 col_gm col-form-label text-right"><?= __('admin/groups.config_grole') ?></label>
						<div class="col-sm-6">
						<?php if ($groups[$cur_id]['internal']) { ?>
							<input class="form-control" disabled value="<?= $groups[$cur_id]['role'] ?>">
						<?php } else { ?>
							<?= Widgets::select('group_role', [''=>''] + array_combine(GROUP_ROLES, GROUP_ROLES), $groups[$cur_id]['role']) ?>
						<?php } ?>
						</div>
					</div>
					<div class="form-group row" style="height: 30px;">
						<label for="`color`" class="col-sm-5 col_gm col-form-label text-right" ><?= __('admin/groups.config_cname') ?></label>
						<div class="col-sm-6" style="margin-top:4px">
							<select class="form-control group-color-<?= $groups[$cur_id]['color'] ?>" name="color"
								onchange="this.className = 'form-control ' + $(this).find(':selected')[0].className;">
							<?php
								for ($i = 0; $i < 16; $i++) {
									echo '<option '. ($i == $groups[$cur_id]['color'] ? 'selected="selected"' : '').
										  ' value="'.$i.'" class="group-color-'.$i.'">'.$i.' ██████████</option>';
								}
								?>
							</select>
						</div>
					</div>
					<input type="submit" name="update_group" value="<?php echo $cur_id?>" hidden>

					<legend><?= __('admin/groups.delete_title') ?></legend>
					<?php if ($groups[$cur_id]['internal']) { ?>
						<em><?= __('admin/groups.delete_violation',['%gid%' => $groups[$cur_id]['internal']]) ?></em>
					<?php } else { ?>
					<div class="form-group row text-center" style="display: block">
						<button type="submit" name="delete_group" class="btn btn-danger" onclick="return confirm('Sur?');" value="<?php echo $cur_id?>"><?= __('admin/groups.delete_btn') ?></button>
						<?= __('admin/groups.delete_move') ?> :
						<?php
							foreach($groups as $_group) {
								$_options[$_group['id']] = $_group['name'];
							}
							echo Widgets::select('delete_new_group', $_options, App::getConfig('default_user_group'), true, '');
						?>
					</div>
					<?php } ?>
				</div>
				<?php
					foreach($_permissions as $id => $perms) {
						echo '<div class="tab-pane fade p-3" id="perms-'.$id.'">';
						echo '<label class="float-right">'. __('admin/groups.config_check_all') .' <input type="checkbox" class="check-all" data-group="'.$id.'"></label>';
							$permissions_count = 0;
							foreach($perms as $title => $permissions) {
								if (is_array($permissions)) {
									echo '<legend>'.$title.'</legend>';
									foreach($permissions as $pname => $ptag) {
										$permissions_count++;
										echo '<div class="checkbox">
													<label><input type="checkbox" data-group="'.$id.'" autocomplete="off" name="perms['.$id.'.'.$pname.']" '.(App::groupHasPermission($cur_id, "$id.$pname") ? 'checked="checked"' : '').' value="1">'.$ptag.'</label>
												</div>';
									}
								}
							}

							if ($permissions_count === 0) {
								echo '<em>'. __('admin/groups.groups.no_perms') .'</em>';
							}
						echo '</div>';
					}
				?>
				<div class="form-group row text-center" style="display:block">
					<button type="submit" name="update_group" value="<?php echo $cur_id?>" class="btn btn-success"><?= __('admin/groups.save') ?></button>
				</div>
			</div>
		</div>
	</form>
	</div>
</div>
<script>
	$('.check-all').click(function() {
		var g = $(this).attr('data-group');
		$('[data-group='+g+']').prop('checked', this.checked);
	})
	.prop('indeterminate', true);
</script>