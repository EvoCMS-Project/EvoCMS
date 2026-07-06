<?php defined('EVO') or die('Que fais-tu là?');

$menu = [
	__('admin/menu.title_info') => [
		'icon' => 'fa-info',
		'url' => App::getAdminURL(),
	],

	__('admin/menu.title_general') => [
		'icon' => 'fa-desktop',
		'url'  => App::getAdminURL('settings'),
		'dropdown' => [
			[__('admin/menu.sub_config'), 'fa-keyboard', App::getAdminURL('settings'), 'admin.manage_settings'],
			[__('admin/menu.sub_report'), 'fa-circle-exclamation', App::getAdminURL('reports'), 'mod.reports'],
			[__('admin/menu.sub_servers'), 'fa-server', App::getAdminURL('servers'), 'admin.manage_servers'],
		],
	],

	__('admin/menu.title_content') => [
		'icon' => 'fa-pencil',
		'url'  => App::getAdminURL('pages'),
		'dropdown' => [
			[__('admin/menu.sub_pages'), 'fa-file-lines', App::getAdminURL('pages'), 'admin.manage_pages'],
			[__('admin/menu.sub_menu'), 'fa-list', App::getAdminURL('menu'), 'admin.manage_menu'],
			[__('admin/menu.sub_lib_media'), 'fa-images', App::getAdminURL('gallery'), 'admin.manage_media'],
			[__('admin/menu.sub_lib_avatar'), 'fa-face-grin-squint-tears', App::getAdminURL('avatars'), 'admin.manage_media'],
			[__('admin/menu.sub_download'), 'fa-file-arrow-down', App::getAdminURL('downloads'), 'admin.manage_media'],
		],
	],

	__('admin/menu.title_community') => [
		'icon' => 'fa-share-nodes',
		'url'  => App::getAdminURL('forums'),
		'dropdown' => [
			[__('admin/menu.sub_forum'), 'fa-list', App::getAdminURL('forums'), 'admin.manage_forums'],
			[__('admin/menu.sub_comments'), 'fa-comments', App::getAdminURL('comments'), 'admin.comment_censure'],
			[__('admin/menu.sub_newsletter'), 'fa-envelope', App::getAdminURL('broadcast'), 'admin.broadcast'],
		],
	],

	__('admin/menu.title_users') => [
		'icon' => 'fa-child',
		'url'  => App::getAdminURL('users'),
		'dropdown' => [
			[__('admin/menu.sub_members'), 'fa-users', App::getAdminURL('users'), 'moderator'],
			[__('admin/menu.sub_groups'), 'fa-layer-group', App::getAdminURL('groups'), 'admin.change_group'],
		],
	],

	__('admin/menu.title_history') => [
		'icon' => 'fa-clock-rotate-left',
		'url'  => App::getAdminURL('history', ['type'=>'admin']),
		'dropdown' => [
			[__('admin/menu.sub_log_admin'), 'fa-user-secret', App::getAdminURL('history', ['type'=>'admin']), 'admin.log_admin'],
			[__('admin/menu.sub_log_users'), 'fa-users', App::getAdminURL('history', ['type'=>'user']), 'admin.log_user'],
			[__('admin/menu.sub_log_messages'), 'fa-envelope', App::getAdminURL('history', ['type'=>'mail']), 'admin.log_mail'],
			[__('admin/menu.sub_log_forum'), 'fa-list', App::getAdminURL('history', ['type'=>'forum']), 'admin.log_forum'],
			[__('admin/menu.sub_log_system'), 'fa-gears', App::getAdminURL('history', ['type'=>'system']), 'admin.log_system'],
		],
	],

	__('admin/menu.title_advanced') => [
		'icon' => 'fa-gear',
		'dropdown' => [
			[__('admin/menu.sub_security'), 'fa-user-slash', App::getAdminURL('security'), 'admin.manage_security'],
			[__('admin/menu.sub_modules'), 'fa-gears', App::getAdminURL('modules'), 'admin.manage_modules'],
			[__('admin/menu.sub_backup'), 'fa-file-zipper', App::getAdminURL('backup'), 'admin.backup'],
			[__('admin/menu.sub_adminer'), 'fa-database', 'adminer/', 'admin.sql'],
			[__('admin/menu.sub_files_editor'), 'fa-file-code', App::getAdminURL('file_editor'), 'admin.files'],
		],
	],
];

$modules_menu_items = [];
App::trigger('admin_menu', [&$modules_menu_items]);

if (!empty($modules_menu_items)) {
	$menu[__('admin/menu.title_modules')] = [
		'icon' => 'fa-gears',
		'url'  => App::getAdminURL('modules'),
		'dropdown' => $modules_menu_items,
	];
}

echo '<ul id="admin-menu">';

foreach($menu as $label => $item) {
	if (!empty($item['dropdown'])) {
		foreach($item['dropdown'] as $key => $link) {
			if (!empty($link[3]) && !has_permission($link[3])) {
				unset($item['dropdown'][$key]);
			}
		}
	}

	if (!empty($item['url']) && empty($item['dropdown'])) {
		echo '<li><a href="' . html_encode($item['url']) . '">' . fa_icon_html($item['icon'], 'solid', ['fa-fw', 'fa-lg']) . '<span class="sidebar-label">' . $label . '</span></a></li>';
	}
	else {
		echo '<li class="dropdown dropright">'
			.'<a href="'.(@$item['url'] ?: '#').'" class="dropdown-toggle" data-hover="dropdown">'
			. fa_icon_html($item['icon'], 'solid', ['fa-fw', 'fa-lg']) . '<span class="sidebar-label">' . $label . ' &nbsp;</span></a>'
			.'<ul class="dropdown-menu">';

		foreach($item['dropdown'] as list($label, $icon, $link)) {
			echo '<li><a href="' . $link . '">' . fa_icon_html($icon, 'solid', ['fa-fw', 'fa-lg']) . ' ' . $label . '</a></li>';
		}

		echo '</ul>';
		echo '</li>';
	}
}
?>
<script>
	if ('ontouchstart' in document.documentElement) {
		$('.dropdown-toggle').click(function() {
			return false;
		});
	}
</script>
