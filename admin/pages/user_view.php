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
?>
<ul class="nav nav-tabs">
	<li class="nav-item"><a class="nav-link active" href="#user" data-bs-toggle="tab"><?= __('admin/user_view.tab_profi') ?></a></li>
	<li class="nav-item"><a class="nav-link" href="#profile" data-bs-toggle="tab"><?= __('admin/user_view.tab_edit') ?></a></li>
	<li class="nav-item"><a class="nav-link" href="#messages" data-bs-toggle="tab"><?= __('admin/user_view.tab_mail') ?></a></li>
	<li class="nav-item"><a class="nav-link disabled" href="#files" data-bs-toggle="tab"><?= __('admin/user_view.tab_file') ?></a></li>
	<li class="nav-item"><a class="nav-link" href="#history" data-bs-toggle="tab"><?= __('admin/user_view.tab_logs') ?></a></li>
</ul>
<div class="tab-content">
	<div class="tab-pane fade" id="profile" style="padding: 1em;">
	<?php if (has_permission('admin.edit_uprofile')): ?>
		<?php include ROOT_DIR.'/pages/profile.php'; ?>
	<?php else: ?>
		<?= __('admin/user_view.tab_denied') ?>
	<?php endif; ?>
	</div>

	<div class="tab-pane fade active show" id="user" style="padding: 1em;">
		<?php include ROOT_DIR.'/pages/user.php'; ?>
	</div>

	<div class="tab-pane fade" id="messages" style="padding: 1em;">
	<?php if (has_permission('admin.view_user_messages')): ?>
		<table class="table">
			<thead>
				<th><?= __('admin/user_view.tab_send') ?></th>
				<th><?= __('admin/user_view.tab_from') ?></th>
				<th><?= __('admin/user_view.tab_to') ?></th>
				<th><?= __('admin/user_view.tab_subject') ?></th>
				<th><?= __('admin/user_view.tab_content') ?></th>
			</thead>
			<tbody>
			<?php
				foreach($mails as $data) {
					echo '<tr>';
						echo '<td style="white-space:nowrap;">' . date('Y-m-d H:i', $data['posted']) . '</td>';
						echo '<td>' . html_encode($data['su']) . '</td>';
						echo '<td>' . html_encode($data['ru']) . '</td>';
						echo '<td>' . $data['sujet'] . '</td>';
						echo '<td>' . nl2br(html_encode($data['message'])) . '</td>';
					echo "</tr>";
				}
			?>
			</tbody>
		</table>
	<?php else: ?>
		<?= __('admin/user_view.tab_denied') ?>
	<?php endif; ?>
	</div>

	<div class="tab-pane fade" id="history" style="padding: 1em;">
	<?php if (has_permission('admin.view_user_history')): ?>
		<table class="table">
			<thead>
				<th><?= __('admin/history.date') ?></th>
				<th><?= __('admin/history.username') ?></th>
				<th><?= __('admin/history.affected') ?></th>
				<th><?= __('admin/history.ip') ?></th>
				<th><?= __('admin/history.event') ?></th>
			</thead>
			<tbody>
			<?php
				foreach($history as $data) {
					echo '<tr>';
						echo '<td style="white-space:nowrap;">' . date('Y-m-d H:i', $data['timestamp']) . '</td>';
						echo '<td>' . html_encode($data['username']) . '</td>';
						echo '<td>' . html_encode($data['ausername']) . '</td>';
						echo '<td>' . $data['ip'] . '</td>';
						echo '<td>' . nl2br(html_encode($data['event'])) . '</td>';
					echo "</tr>";
				}
			?>
			</tbody>
		</table>
	<?php else: ?>
		<?= __('admin/user_view.tab_denied') ?>
	<?php endif; ?>
	</div>
</div>