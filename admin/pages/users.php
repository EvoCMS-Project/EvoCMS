<?php defined('EVO') or die('Que fais-tu là?');

has_permission('moderator', true);

$filter_raw = trim((string) App::REQ('filter', ''));

if ($filter_raw !== '') {
	$columns = array_diff(Db::GetColumns('users', true), ['password', 'raf', 'raf_token']);
	$search = build_search_query($filter_raw, preg_replace('/^/', 'a.', $columns));
	$where = $search['where'];
	$args = $search['args'];
} else {
	$where = 'a.id <> 0';
	$args = [];
}

$per_page = 15;
$page_num = max(1, (int) App::GET('pn', 1));
$start = ($page_num - 1) * $per_page;
$count_args = $args;

$args[] = $start;
$args[] = $per_page;

$users = Db::QueryAll("SELECT a.*, g.name as gname, g.color as color, b.reason as ban_reason
					   FROM {users} as a
					   LEFT JOIN {groups} as g ON g.id = a.group_id
					   LEFT JOIN {banlist} as b ON (a.username = b.rule and b.type = 'username') or (a.last_ip = b.rule and b.type = 'ip') or (a.email = b.rule and b.type = 'email')
					   WHERE $where ORDER BY g.priority ASC, g.id DESC, username ASC LIMIT ?,?", $args);

$total_filtered = (int) Db::Get('select count(*) from {users} as a left join {groups} as g on g.id = a.group_id where ' . $where, $count_args);
$total_users = (int) Db::Get('select count(*) from {users}');
$online_users = (int) Db::Get('select count(*) from {users} where activity > ?', time() - 120);
$banned_users = (int) Db::Get("SELECT count(distinct a.id)
	FROM {users} as a
	LEFT JOIN {banlist} as b ON (a.username = b.rule and b.type = 'username') or (a.last_ip = b.rule and b.type = 'ip') or (a.email = b.rule and b.type = 'email')
	WHERE b.reason is not null");
$staff_group_ids = [];

foreach (Db::QueryAll('select id from {groups}') as $group) {
	$group_id = (int) ($group['id'] ?? 0);

	if (
		App::groupHasPermission($group_id, 'admin.backup')
		|| App::groupHasPermission($group_id, 'administrator')
		|| App::groupHasPermission($group_id, 'moderator')
	) {
		$staff_group_ids[] = $group_id;
	}
}

$staff_users = $staff_group_ids
	? (int) Db::Get('select count(*) from {users} where group_id in (' . implode(',', $staff_group_ids) . ')')
	: 0;
$users_stats = admin_users_build_stats([
	'total' => $total_users,
	'online' => $online_users,
	'banned' => $banned_users,
	'staff' => $staff_users,
]);
?>

<div class="admin-dashboard admin-users">
	<?= admin_stat_grid($users_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-users-board">
		<div class="admin-tabs-board__body admin-users-board__body admin-tabs-panel">
			<form role="search" method="get" id="admin-users-filter" class="admin-users-filter-form">
				<input type="hidden" name="page" value="users">
				<?= admin_users_filter($filter_raw) ?>
			</form>

			<form method="post" id="admin-users-list-form" class="admin-users-list-form">
				<?= admin_csrf_field() ?>
				<?= admin_users_table($users) ?>

				<?php if ($total_filtered > $per_page): ?>
					<div class="admin-users-pager">
						<?= Widgets::pager((int) ceil($total_filtered / $per_page), $page_num, 10); ?>
					</div>
				<?php endif; ?>
			</form>
		</div>
	</section>
</div>