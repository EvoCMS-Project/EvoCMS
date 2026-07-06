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
$per_page = 25;
$page_num = max(1, (int) App::GET('pn', 1));
$start = ($page_num - 1) * $per_page;

$status_labels = [
	0 => __('admin/comments.state_pending'),
	1 => __('admin/comments.state_ok'),
	2 => __('admin/comments.state_censored'),
];

$selected_states = array_map('intval', App::REQ('states', array_keys($status_labels)));
$where = [];
$query_params = [];

if ($page_id) {
	$where[] = 'page_id = ?';
	$query_params[] = $page_id;
}

$total = (int) Db::Get('select count(*) from {comments}' . ($where ? ' where ' . implode(' and ', $where) : ''), ...$query_params);
$total_filtered = 0;
$comments = [];

if ($selected_states) {
	$state_placeholders = implode(', ', array_fill(0, count($selected_states), '?'));
	$filtered_where = array_merge($where, ['state in (' . $state_placeholders . ')']);
	$filtered_params = array_merge($query_params, $selected_states);

	$total_filtered = (int) Db::Get(
		'select count(*) from {comments} where ' . implode(' and ', $filtered_where),
		...$filtered_params
	);

	$comments = Db::QueryAll(
		'SELECT coms.*, acc.username FROM {comments} AS coms LEFT JOIN {users} AS acc ON coms.user_id = acc.id
		WHERE ' . implode(' and ', $filtered_where) . '
		ORDER BY state ASC, id DESC LIMIT ' . $start . ', ' . $per_page,
		...$filtered_params
	) ?: [];
}

$seen = [];

foreach ($comments as $comment) {
	if ((int) ($comment['state'] ?? 1) !== 1) {
		$seen[] = (int) $comment['id'];
	}
}

if ($seen) {
	Db::Exec('UPDATE {comments} SET state = 1 WHERE state = 0 AND id IN(' . implode(',', $seen) . ')');
}

$board = admin_comments_board($comments, $total_filtered, $page_num, $page_id, $status_labels, $selected_states, !$embedded);

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

<script>
(function () {
	var form = document.querySelector('.admin-comments-form');
	if (!form) {
		return;
	}

	form.querySelectorAll('.admin-comments-filter-chip__input').forEach(function (input) {
		input.addEventListener('change', function () {
			form.submit();
		});
	});
})();
</script>
