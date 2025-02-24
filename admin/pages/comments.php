<?php defined('EVO') or die('Que fais-tu là?');

has_permission('mod.reports', true);

if (App::POST('com_accept') && change_comment_state(App::POST('com_accept'), 1)) {
	App::setSuccess(__('admin/comments.alert_accepted'));
}
if (App::POST('com_censure') && change_comment_state(App::POST('com_censure'), 2)) {
	App::setSuccess(__('admin/comments.alert_censor'));
}
if (App::POST('com_delete') && change_comment_state(App::POST('com_delete'), -1)) {
	App::setSuccess(__('admin/comments.alert_deleted'));
}

$where_coms = App::GET('page_id') ? 'where page_id = '.(int)App::GET('page_id') : '';
$start = abs(App::REQ('pn', 1) - 1) * 25;
$total = Db::Get('select count(*) from {comments} ' . $where_coms);

$comment_status = [0 => 'Ok',  1=> 'Ok', 2 => __('admin/comments.state_censored')];
$comments = Db::QueryAll('SELECT coms.*, acc.username FROM {comments} AS coms LEFT JOIN {users} AS acc ON coms.user_id = acc.id '.$where_coms.' ORDER BY state ASC, id DESC LIMIT '.$start.', 25');

if (!$comments) {
	return print '<div id="content"><div style="text-align: center;" class="alert alert-warning">'. __('admin/comments.no_comment') .'</div></div>';
}
?>
<legend><?= __('admin/comments.title') ?></legend>
<div id="content">
	<form method="post" action="?page=comments">
		<table class="table">
			<thead>
				<tr>
					<th><?= __('admin/comments.table_msg') ?></th>
					<th><?= __('admin/comments.table_user') ?></th>
					<th><?= __('admin/comments.table_state') ?></th>
					<th style="width:110px;"> </th>
				</tr>
			</thead>
			<tbody>
				<?php
					foreach($comments as $comment) {
						if ($comment['state'] != 1) {
							$seen[] = $comment['id'];
						}
						echo '<tr>';
							echo '<td>' . html_encode($comment['message']) . '</td>';
							echo '<td style="white-space:nowrap">'.($comment['username'] ?: $comment['poster_name']) . '</td>';
							echo '<td>' . $comment_status[$comment['state']] . '</td>';
							echo '<td>';
								if ($comment['state'] == 2)
									echo '<button class="btn btn-sm btn-success" name="com_accept" value="'.$comment['id'].'" title="'.__('admin/comments.btn_accept').'"><i class="fa fa-check"></i></button> ';
								if (has_permission('mod.comment_censure') && $comment['state'] != 2)
									echo '<button name="com_censure" value="'.$comment['id'].'" title="'.__('admin/comments.btn_censor').'" class="btn btn-sm btn-danger"><i class="fa fa-ban"></i></button> ';
								if (has_permission('mod.comment_delete'))
									echo '<button name="com_delete" value="'.$comment['id'].'" title="'.__('admin/comments.btn_delete').'" class="btn btn-sm btn-danger"><i class="far fa-trash-alt"></i></button> ';
								echo '<a href="'.App::getURL($comment['page_id'], [], '#msg'.$comment['id']).'" title="'.__('admin/comments.btn_view').'" class="btn btn-sm btn-primary"><i class="fa fa-eye"></i></a>';
							echo '</td>';
						echo '</tr>';
					}

					if (isset($seen)) {
						Db::Exec('UPDATE {comments} SET state = 1 WHERE STATE = 0 AND id IN('.implode(',', $seen).')');
					}
				?>
			</tbody>
		</table>
	</form>
<?= Widgets::pager(ceil($total / 25), App::GET('pn') ?: 1, 10, null, App::GET('prevpn')); ?>
</div>