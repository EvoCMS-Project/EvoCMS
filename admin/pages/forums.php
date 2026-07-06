<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_forums', true);

$cat_id = (int)App::POST('cat_id', '0');
$cat_name = (string)App::POST('category_name', '');
$edit_mode = (bool)App::POST('edit_mode', '0');

if (App::POST('header_change'))
{
	App::setConfig('forums.name', App::POST('forums_name'));
	App::setConfig('forums.description', App::POST('forums_description'));
	App::setSuccess(__('admin/forums.alert_success_header_change'));
}
elseif (App::POST('new_category') && $cat_name !== '')
{
	Db::Insert('forums_cat', ['name' => $cat_name, 'priority' => 0]);
	App::setSuccess(__('admin/forums.alert_success_new_category'));
	$edit_mode = false;
}
elseif (App::POST('move_category') && $cat_id)
{
	$direction = App::POST('move_category');
	$categories = Db::QueryAll('SELECT id, name, priority FROM {forums_cat} ORDER BY priority ASC');

	$order = array_column($categories, 'id');
	$pos = array_search($cat_id, $order);

	if (($direction == -1 && $pos > 0) || ($direction == 1 && $pos < count($order) -1)) {
		$order[$pos] = $order[$pos + $direction];
		$order[$pos + $direction] = $cat_id;

		foreach($order as $pos => $id) {
			Db::Update('forums_cat', ['priority' => $pos], ['id' => $id]);
		}
	}
	$edit_mode = false;
}
elseif (App::POST('edit_category') && $cat_id && $cat_name !== '')
{
	Db::Update('forums_cat', ['name' => $cat_name], ['id' => $cat_id]);
	App::setSuccess(__('admin/forums.alert_success_edit_category'));
	unset(App::$POST['edit_category']);
	$edit_mode = false;
}
elseif (App::POST('add_forum') !== null && App::POST('name'))
{
	if (App::POST('add_forum')) {
		Db::Update('forums', [
			'cat'         => App::POST('cat'),
			'priority'    => App::POST('priority'),
			'name'        => App::POST('name'),
			'description' => App::POST('description'),
			'icon'        => App::POST('icon'),
			'redirect'    => App::POST('redirect'),
		], ['id' => App::POST('add_forum')]);

		if (Db::$affected_rows) {
			App::setSuccess(__('admin/forums.alert_success_add_forum'));
			$forum_id = App::POST('add_forum');
			$edit_mode = false;
		} else {
			App::setWarning(__('admin/forums.alert_warning_add_forum'));
		}
	} else {
		Db::Insert('forums', array(
			'cat'         => App::POST('cat'),
			'priority'    => App::POST('priority'),
			'name'        => App::POST('name'),
			'description' => App::POST('description'),
			'icon'        => App::POST('icon'),
			'redirect'    => App::POST('redirect')
		));
		if ($forum_id = Db::$insert_id) {
			App::setSuccess(__('admin/forums.alert_success_reorder_forums'));
			App::logEvent(0, 'forum', __('admin/forums.logevent_add_forum').'"' . App::POST('name') . '"');
			$edit_mode = false;
		} else {
			App::setWarning(__('admin/forums.alert_warning_add_forum'));
		}
	}

	if (!empty($forum_id)) {
		$values = $args = array();

		foreach(['read', 'write', 'moderation'] as $perm) {
			if (!empty(App::POST('perms')[$perm])) {
				foreach(App::POST('perms')[$perm] as $group) {
					$values[] = '("forum.'.$perm.'", ?, ?, 1)';
					$args[] = $forum_id;
					$args[] = $group;
				}
			}
		}

		if ($values) {
			Db::Delete('permissions', 'related_id = ? and name like "forum.%"', $forum_id);
			Db::Exec('replace into {permissions} (name, related_id, group_id, value) VALUES '.implode(',', $values), $args);
		}
	}
}
elseif (App::POST('del_forum'))
{
	if (Db::Delete('forums', ['id' => App::POST('del_forum')])) {
		$topics = Db::QueryAll('SELECT * from {forums_topics} WHERE forum_id = ?', App::POST('del_forum'));
		foreach($topics as $topic) {
			Db::Delete('forums_topics', ['id' => $topic['id']]);
			Db::Delete('forums_posts', ['topic_id' => $topic['id']]);
		}
		Db::Delete('permissions', 'related_id = ? and name like "forum.%"', App::POST('del_forum'));
		App::setSuccess(__('admin/forums.alert_success_del_forum'));
		$edit_mode = false;
	} else {
		App::setWarning((string)Db::$error);
	}
}
elseif (App::POST('reorder_forums'))
{
	foreach(App::POST('reorder_forums') as $cat => $forums) {
		foreach($forums as $priority => $k) {
			if ($k) Db::Update('forums', ['priority' => $priority], ['id' => $k]);
		}
	}
	App::setSuccess(__('admin/forums.alert_success_reorder_forums'));
	$edit_mode = false;
}
elseif (App::POST('delete_category'))
{
	if (Db::Get('SELECT * FROM {forums} WHERE cat = ?', $cat_id)) {
		App::setWarning(__('admin/forums.alert_warning_delete_category'));
	} else {
		Db::Delete('forums_cat', ['id' => $cat_id]);
		App::setSuccess(__('admin/forums.alert_success_delete_category'));
	}
	$edit_mode = false;
}

$empty_elem = [
	'id' => '', 'cat' => 0, 'name' => '', 'icon' => '', 'description' => '', 'priority' => 0, 'redirect' => '',
	'forum.read' => [], 'forum.write' => [], 'forum.moderation' => [],
];

$cur_elem = $empty_elem;
$cat_select = [];

$groups = Db::QueryAll('SELECT id, color, name FROM {groups} ORDER BY priority ASC, id DESC', true);
$forums = Db::QueryAll('SELECT * FROM {forums} ORDER BY priority ASC, id ASC', true);
$categories = Db::QueryAll('SELECT id, name, priority FROM {forums_cat} ORDER BY priority ASC', true);
$perms = Db::QueryAll('SELECT * FROM {permissions} WHERE name LIKE "forum.%"');

foreach($categories as $id => $c) {
	$cat_select[$c['id']] = $c['name'];
	$categories[$id]['forums'] = [];
}

foreach($perms as $p) {
	if (!isset($forums[$p['related_id']])) // Some Cleanup
		Db::Delete('permissions', 'related_id = ? and name like "forum.%"', $p['related_id']);
	elseif ($p['value'])
		$forums[$p['related_id']][$p['name']][] = $p['group_id'];
}

foreach($forums as $forum) {
	$categories[$forum['cat']]['forums'][] = $forum + $empty_elem;
	if (App::POST('edit_forum') == $forum['id'])
		$cur_elem = $forum;
}

$forums_stats = admin_forums_build_stats($categories, $forums);
?>

<div class="admin-dashboard admin-forums">
	<?= admin_stat_grid($forums_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-forums-board">
		<div class="admin-tabs-board__body admin-forums-board__body admin-tabs-panel">
			<?php if (!$edit_mode): ?>
				<div class="admin-forums-panel admin-forums-panel--config">
					<div class="admin-modules-table__toolbar">
						<div class="admin-modules-table__caption">
							<span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--info">
								<i class="fa-solid fa-heading" aria-hidden="true"></i>
							</span>
							<span class="admin-modules-table__caption-text"><?= __('admin/forums.header') ?></span>
						</div>
					</div>

					<form class="form-horizontal admin-forums-config-form" role="form" method="post">
						<?= admin_csrf_field() ?>
						<div class="mb-3 row">
							<label class="col-sm-3 col-form-label text-right"><?= __('admin/forums.table_title') ?></label>
							<div class="col-sm-8">
								<input type="text" class="form-control" name="forums_name" placeholder="<?= html_encode(App::getConfig('name')) ?>" value="<?= html_encode(App::POST('forums_name', App::getConfig('forums.name'))) ?>">
							</div>
						</div>
						<div class="mb-3 row">
							<label class="col-sm-3 col-form-label text-right"><?= __('admin/forums.table_desc') ?></label>
							<div class="col-sm-8">
								<input type="text" class="form-control" name="forums_description" placeholder="<?= html_encode(App::getConfig('description')) ?>" value="<?= html_encode(App::POST('forums_description', App::getConfig('forums.description'))) ?>">
								<small class="form-text text-muted"><?= __('admin/forums.table_desc_tips') ?></small>
							</div>
						</div>
						<div class="admin-forums-form-actions">
							<button type="submit" name="header_change" value="1" class="btn btn-primary"><?= __('admin/general.btn_save') ?></button>
						</div>
					</form>
				</div>

				<?= admin_forums_categories_list($categories, $groups) ?>
			<?php endif; ?>

			<?php if ($categories && !App::POST('edit_category')): ?>
				<div class="admin-forums-panel admin-forums-panel--editor" id="edit-forum">
					<div class="admin-modules-table__toolbar">
						<div class="admin-modules-table__caption">
							<span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--primary">
								<i class="fa-solid fa-comment-dots" aria-hidden="true"></i>
							</span>
							<span class="admin-modules-table__caption-text">
								<?= $cur_elem['id'] ? __('admin/forums.add_forum_title_edit') . ' # ' . (int) $cur_elem['id'] : __('admin/forums.add_forum_title_add') ?>
							</span>
						</div>
					</div>

					<form class="form-horizontal admin-forums-editor-form" method="post" action="#">
						<?= admin_csrf_field() ?>
						<div class="mb-3 row">
							<label class="col-sm-3 col-form-label text-right" for="name"><?= __('admin/forums.table_name') ?> :</label>
							<div class="col-sm-8 controls">
								<input class="form-control" id="name" name="name" type="text" value="<?= html_encode($cur_elem['name']) ?>">
							</div>
						</div>
						<div class="mb-3 row">
							<label class="col-sm-3 col-form-label text-right" for="description"><?= __('admin/forums.table_desc_alt') ?> :</label>
							<div class="col-sm-8 controls">
								<input class="form-control" id="description" name="description" type="text" value="<?= html_encode($cur_elem['description']) ?>">
								<small class="form-text text-muted"><?= __('admin/forums.table_desc_alt_tips') ?></small>
							</div>
						</div>
						<div class="mb-3 row">
							<label class="col-sm-3 col-form-label text-right" for="redirect"><?= __('admin/forums.table_redirect') ?> :</label>
							<div class="col-sm-8 controls">
								<input class="form-control" id="redirect" name="redirect" type="text" placeholder="Exemple: https://google.ca" value="<?= html_encode($cur_elem['redirect']) ?>">
								<small class="form-text text-muted"><?= __('admin/forums.table_redirect_link') ?>.</small>
							</div>
						</div>
						<div class="mb-3 row">
							<label class="col-sm-3 col-form-label text-right"><?= __('admin/forums.table_access') ?> :</label>
							<div class="col-sm-8">
								<div class="admin-forums-permission-selects">
									<div class="admin-forums-permission-select">
										<strong><?= __('admin/forums.table_read') ?></strong>
										<?php
											echo '<select class="form-control" size="' . count($groups) . '" name="perms[read][]" multiple>';
											foreach($groups as $group) {
												$selected = in_array($group['id'], $cur_elem['forum.read'] ?? []) || ($group['id'] != 0 && !$cur_elem['id']);
												echo '<option class="group-color-' . $group['color'] . '" value="' . $group['id'] . '" ' . ($selected ? 'selected="selected"' : '') . '>' .
														html_encode($group['name']) . '</option>';
											}
											echo '</select>';
										?>
									</div>
									<div class="admin-forums-permission-select">
										<strong><?= __('admin/forums.table_forum_write_title') ?></strong>
										<?php
											echo '<select class="form-control" size="' . count($groups) . '" name="perms[write][]" multiple>';
											foreach($groups as $group) {
												$selected = in_array($group['id'], $cur_elem['forum.write'] ?? []) || ($group['id'] != 4 && !$cur_elem['id']);
												echo '<option class="group-color-' . $group['color'] . '" value="' . $group['id'] . '" ' . ($selected ? 'selected="selected"' : '') . '>' .
														html_encode($group['name']) . '</option>';
											}
											echo '</select>';
										?>
									</div>
									<div class="admin-forums-permission-select">
										<strong><?= __('admin/forums.table_forum_mod_title') ?></strong>
										<?php
											echo '<select class="form-control" size="' . count($groups) . '" name="perms[moderation][]" multiple>';
											foreach($groups as $group) {
												$selected = in_array($group['id'], $cur_elem['forum.moderation'] ?? []);
												echo '<option class="group-color-' . $group['color'] . '" value="' . $group['id'] . '" ' . ($selected ? 'selected="selected"' : '') . '>' .
														html_encode($group['name']) . '</option>';
											}
											echo '</select>';
										?>
									</div>
								</div>
							</div>
						</div>
						<div class="mb-3 row">
							<label class="col-sm-3 col-form-label text-right" for="icon"><?= __('admin/forums.table_ico') ?> :</label>
							<div class="col-sm-8 controls">
								<?= Widgets::iconSelect('icon', $cur_elem['icon']) ?>
							</div>
						</div>
						<div class="mb-3 row">
							<label class="col-sm-3 col-form-label text-right"><?= __('admin/forums.table_cat') ?> :</label>
							<div class="col-sm-8 controls admin-forums-icon-select">
								<?= Widgets::select('cat', $cat_select, $cur_elem['cat'], false) ?>
							</div>
						</div>
						<div class="mb-3 row">
							<label class="col-sm-3 col-form-label text-right"><?= __('admin/forums.table_order') ?> :</label>
							<div class="col-sm-8 controls">
								<?= Widgets::select('priority', array_keys(array_fill(0, 100, '')), $cur_elem['priority']) ?>
							</div>
						</div>
						<div class="admin-forums-form-actions">
							<button class="btn btn-primary" name="add_forum" value="<?= html_encode((string) $cur_elem['id']) ?>" type="submit"><?= __('admin/forums.table_btn_save') ?></button>
							<button class="btn btn-outline-secondary"><?= __('admin/menu.btn_cancel') ?></button>
						</div>
					</form>
				</div>
			<?php endif; ?>

			<?php if (!App::POST('add_forum')): ?>
				<div class="admin-forums-panel admin-forums-panel--category-form">
					<div class="admin-modules-table__toolbar">
						<div class="admin-modules-table__caption">
							<span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--success">
								<i class="fa-solid fa-layer-group" aria-hidden="true"></i>
							</span>
							<span class="admin-modules-table__caption-text"><?= $edit_mode ? __('admin/forums.add_forum_title_rename') : __('admin/forums.add_forum_title_create') ?></span>
						</div>
					</div>

					<form class="form-horizontal admin-forums-category-form" role="form" method="post">
						<?= admin_csrf_field() ?>
						<div class="mb-3 row">
							<label class="col-sm-3 col-form-label text-right"><?= __('admin/forums.add_catname') ?></label>
							<div class="col-sm-8">
								<input type="text" class="form-control" name="category_name" value="<?= html_encode($categories[$cat_id]['name'] ?? '') ?>">
							</div>
						</div>
						<div class="admin-forums-form-actions">
							<?php if ($edit_mode): ?>
								<input type="hidden" value="<?= $cat_id ?>" name="cat_id">
								<button type="submit" name="edit_category" value="<?= $cat_id ?>" class="btn btn-primary"><?= __('admin/forums.add_forum_title_rename') ?></button>
								<button type="submit" name="cancel" value="" class="btn btn-outline-secondary"><?= __('admin/menu.btn_cancel') ?></button>
							<?php else: ?>
								<button type="submit" name="new_category" value="1" class="btn btn-primary"><?= __('admin/forums.add_cat_create') ?></button>
							<?php endif; ?>
						</div>
					</form>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
