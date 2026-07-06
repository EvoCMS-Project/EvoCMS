<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_menu', true);

$cur_elem = ['id' => '', 'parent' => 0, 'name' => '', 'icon' => '', 'link' => '', 'priority' => 0, 'page_name' => null, 'visibility' => 0];
$tab = App::GET('tab', 'list');
$keep_form = false;

if (isset(App::$POST['add_menu'], App::$POST['name']) && !empty(App::$POST['name'])) {
	if (App::$POST['add_menu'] == 0 && Db::Insert('menu', ['parent'=>App::$POST['parent'], 'priority'=>App::$POST['priority'], 'name'=>App::$POST['name'], 'icon'=>App::$POST['icon'], 'link'=>App::$POST['link']?:App::$POST['internal_page'],'visibility'=>App::$POST['visibility']])) {
		App::setSuccess(__('admin/menu.alert_add_success'));
		$tab = 'list';
	} elseif (Db::Update('menu',  ['parent' => App::$POST['parent'], 'priority' => App::$POST['priority'], 'name' => App::$POST['name'], 'icon' => App::$POST['icon'], 'link' => App::$POST['link'] ?: App::$POST['internal_page'], 'visibility' => App::$POST['visibility']], ['id' => App::$POST['add_menu']])) {
		App::setSuccess(__('admin/menu.alert_update_success'));
		$cur_elem = ['id' => '', 'parent' => 0, 'name' => '', 'icon' => '', 'link' => '', 'priority' => 0, 'page_name' => null, 'visibility' => 0];
		$tab = 'list';
	} elseif (Db::$errno != 0) {
		App::setWarning((string)Db::$error);
		$tab = 'form';
		$keep_form = true;
	}
}
elseif (isset(App::$POST['del_menu'])) {
	if (Db::Delete('menu', ['id' => App::$POST['del_menu']])) {
		App::setSuccess(__('admin/menu.alert_del_success'));
	} else {
		App::setWarning(__('admin/menu.alert_exist_warning'));
	}
	$tab = 'list';
}
elseif (isset(App::$POST['menu-editor'])) {
	foreach (App::$POST['menu-editor'] as $priority => $k) {
		if ($k) {
			Db::Update('menu', ['priority' => $priority], ['id' => $k]);
		}
	}
	App::setSuccess(__('admin/menu.alert_create_success'));
	$tab = 'list';
}

$tree = get_menu_tree(true, $items);
$parent_list = admin_menu_build_parent_list($tree);
$total_items = count($items ?? []);
$menu_stats = admin_menu_build_stats($items ?? [], $tree);

if (isset(App::$POST['edit_menu']) && isset($items[App::$POST['edit_menu']])) {
	$cur_elem = $items[App::$POST['edit_menu']];
	$tab = 'form';
} elseif ($tab === 'form' && ($edit_id = (int) App::GET('edit', 0)) && isset($items[$edit_id])) {
	$cur_elem = $items[$edit_id];
}

if (IS_POST && App::POST('edit_menu')) {
	$tab = 'form';
}

if ($tab === 'form' && IS_POST && isset(App::$POST['add_menu']) && $keep_form) {
	$cur_elem = array_merge($cur_elem, [
		'id' => App::POST('add_menu'),
		'parent' => App::POST('parent'),
		'priority' => App::POST('priority'),
		'name' => App::POST('name'),
		'icon' => App::POST('icon'),
		'link' => App::POST('link') ?: App::POST('internal_page'),
		'visibility' => App::POST('visibility'),
		'page_name' => App::POST('link') ? null : ($cur_elem['page_name'] ?? null),
	]);
}

$pages = admin_menu_internal_pages();
$delete_confirm = html_encode(__('admin/menu.ajax_confirm'), ENT_QUOTES);

$menu_nav = [
	'list' => ['label' => __('admin/menu.tab_list'), 'icon' => 'fa-list'],
	'form' => [
		'label' => $cur_elem['id'] ? __('admin/menu.tab_edit') : __('admin/menu.tab_form'),
		'icon' => $cur_elem['id'] ? 'fa-pencil' : 'fa-circle-plus',
	],
];
?>

<div class="admin-dashboard admin-menu">
	<?= admin_stat_grid($menu_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-menu-board">
		<?= admin_menu_nav($menu_nav, $tab) ?>

		<div class="tab-content admin-tabs-board__body admin-menu-board__body admin-tabs-panel admin-menu-board__body--content">
			<?= admin_menu_tab_open('list', $tab === 'list') ?>
				<?php if (!$total_items): ?>
					<?= admin_menu_empty(__('admin/menu.not_found')) ?>
				<?php else: ?>
					<form method="post" id="admin-menu-list-form" class="admin-menu-list-form admin-reports-form" action="#menu-editor">
						<?= admin_csrf_field() ?>
						<?= admin_menu_table($tree, $delete_confirm) ?>
					</form>
				<?php endif; ?>
			<?= admin_menu_tab_close() ?>

			<?= admin_menu_tab_open('form', $tab === 'form') ?>
				<?= admin_menu_form_board($cur_elem, $parent_list, $pages) ?>
			<?= admin_menu_tab_close() ?>
		</div>
	</section>
</div>

<script>
(function () {
	var linkInput = document.getElementById('admin-menu-link');
	var internalSelect = document.getElementById('internal_page');

	if (linkInput && internalSelect) {
		internalSelect.addEventListener('change', function () {
			linkInput.value = '';
		});
	}
})();
</script>
