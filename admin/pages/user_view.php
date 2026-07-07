<?php defined('EVO') or die('Que fais-tu là?');

has_permission('moderator', true);

$user_info = App::getUser(App::GET('id'));

$history = Db::QueryAll ('SELECT h.*, a.username as username, b.username as ausername
					      FROM {history} as h
					      LEFT JOIN {users} as a ON a.id = h.e_uid
					      LEFT JOIN {users} as b ON b.id = h.a_uid
					      WHERE a.username = ? or b.username = ?
						  ORDER BY timestamp DESC', [$user_info->username, $user_info->username]);

$mails  = Db::QueryAll(
	'SELECT m.sujet, m.message, m.posted, m.id, m.viewed, m.deleted_rcv, m.deleted_snd, a.username as ru, b.username as su
	 FROM {mailbox} as m
	 LEFT JOIN {users} as a ON m.r_id = a.id
	 LEFT JOIN {users} as b ON m.s_id = b.id
	 WHERE r_id = ? OR s_id = ?
	 ORDER BY posted desc',
	 $user_info->id,
	 $user_info->id
);

$user_tabs = [
	'user' => ['label' => __('admin/user_view.tab_profi'), 'icon' => 'fa-user'],
	'profile' => ['label' => __('admin/user_view.tab_edit'), 'icon' => 'fa-pencil'],
	'messages' => ['label' => __('admin/user_view.tab_mail'), 'icon' => 'fa-envelope'],
	'files' => ['label' => __('admin/user_view.tab_file'), 'icon' => 'fa-folder', 'disabled' => true],
	'history' => ['label' => __('admin/user_view.tab_logs'), 'icon' => 'fa-clock-rotate-left'],
];

$user_stats = admin_user_view_build_stats($user_info, $mails, $history);
?>

<div class="admin-dashboard admin-user-view">
	<?= admin_stat_grid($user_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-user-view-board">
		<?= admin_user_view_nav($user_tabs, 'user') ?>

		<div class="tab-content admin-tabs-board__body admin-user-view-board__body admin-tabs-panel admin-user-view-board__body--content">
			<?= admin_user_view_tab_open('user', true) ?>
				<?= admin_user_view_profile_board($user_info) ?>
			<?= admin_user_view_tab_close() ?>

			<?= admin_user_view_tab_open('profile', false) ?>
				<?php if (has_permission('admin.edit_uprofile')): ?>
					<?= admin_user_view_edit_board() ?>
				<?php else: ?>
					<?= admin_user_view_denied() ?>
				<?php endif; ?>
			<?= admin_user_view_tab_close() ?>

			<?= admin_user_view_tab_open('messages', false) ?>
				<?php if (has_permission('admin.view_user_messages')): ?>
					<?= admin_user_view_messages_table($mails) ?>
				<?php else: ?>
					<?= admin_user_view_denied() ?>
				<?php endif; ?>
			<?= admin_user_view_tab_close() ?>

			<?= admin_user_view_tab_open('history', false) ?>
				<?php if (has_permission('admin.view_user_history')): ?>
					<?= admin_user_view_history_table($history) ?>
				<?php else: ?>
					<?= admin_user_view_denied() ?>
				<?php endif; ?>
			<?= admin_user_view_tab_close() ?>
		</div>
	</section>
</div>
