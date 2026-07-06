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

$page_id = (int) App::GET('page_id', 0);
$embedded = $page_id > 0 || IS_AJAX;
$page_num = max(1, (int) App::GET('pn', 1));
$start = ($page_num - 1) * 25;
$where_coms = $page_id ? 'where page_id = ' . $page_id : '';
$total = (int) Db::Get('select count(*) from {comments} ' . $where_coms);

$status_labels = [
	0 => __('admin/comments.state_pending'),
	1 => __('admin/comments.state_ok'),
	2 => __('admin/comments.state_censored'),
];

$comments = Db::QueryAll(
	'SELECT coms.*, acc.username FROM {comments} AS coms LEFT JOIN {users} AS acc ON coms.user_id = acc.id '
	. $where_coms . ' ORDER BY state ASC, id DESC LIMIT ' . $start . ', 25'
) ?: [];

$seen = [];

foreach ($comments as $comment) {
	if ((int) ($comment['state'] ?? 1) !== 1) {
		$seen[] = (int) $comment['id'];
	}
}

if ($seen) {
	Db::Exec('UPDATE {comments} SET state = 1 WHERE state = 0 AND id IN(' . implode(',', $seen) . ')');
}

$board = admin_comments_board($comments, $total, $page_num, $page_id, $status_labels);

if ($embedded) {
	echo '<div id="content">' . $board . '</div>';
	return;
}

$pending = (int) Db::Get('select count(*) from {comments} where state = 0');
$censored = (int) Db::Get('select count(*) from {comments} where state = 2');
$comments_stats = admin_comments_build_stats($total, $pending, $censored);
?>

<div class="admin-dashboard admin-comments">
	<?= admin_stat_grid($comments_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-comments-board">
		<div class="admin-tabs-board__body admin-comments-board__body admin-tabs-panel">
			<?= $board ?>
		</div>
	</section>
</div>
