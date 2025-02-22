<?php defined('EVO') or die('Que fais-tu là?');

$team = App::REQ('team') ? 1 : 0;

if ($team) {
	$groups = Db::QueryAll('select distinct group_id from {permissions} where name = "user.staff" and value = 1');
	$where = 'group_id IN (' . implode(', ', array_column($groups ?: [], 'group_id')) . ')';
	$q = [];

	$columns = [
		__('users.username') => 'username',
		__('users.group')    => 'gname',
		__('users.ingame')   => 'ingame',
	];
} else {
	has_permission('user.view_uprofile', true);
	if (App::REQ('filter')) {
		$q = ['%'.App::REQ('filter').'%', App::REQ('filter')];
		$where = '(a.id <> 0) and (a.username like ? or a.email = ?)'; // We want the exact to be a perfect match, in case it's private.
	} else {
		$where = '(a.id <> 0)';
		$q = [];
	}

	if (App::REQ('group')) {
		$q[] = App::REQ('group');
		$where .= ' and group_id = ?'; // We want the exact to be a perfect match, in case it's private.
	}

	$columns = [
		__('users.username') => 'username',
		__('users.group')    => 'gname',
		__('users.ingame')   => 'ingame',
		__('users.comments') => 'cmt',
		__('users.friends')  => 'fnd',
	];
}

if (!isset($display)) {
	$display = App::GET('view', 'grid');
}

$num_users = Db::Get('select count(*) from {users} as a where '.$where, $q);
$default_sort = 'gpriority asc, activity desc, registered desc';

$perpage = $display === 'grid' ? 12 : 15;

$sort = App::GET('sort', $default_sort);

$pn = App::GET('pn', 1) ?: 1;
$start = $perpage * ($pn-1);
$ptotal = ceil($num_users / $perpage);

$sort = in_array(strstr($sort.' ', ' ', true), $columns) ? $sort : $default_sort;

$users = Db::QueryAll('select *, g.color as color, g.name as gname, g.priority as gpriority,
							  (select count(*) from {comments} where user_id = a.id) as cmt,
							  (select count(*) from {friends} where u_id = a.id and state = 1) as fnd
						from {users} as a left join {groups} as g on g.id = a.group_id
						where '.$where.'
						order by '.$sort.'
						limit '. $start.','.$perpage, $q);

$paginator = $ptotal > 1 ? Widgets::pager($ptotal, $pn) : '';

App::renderTemplate('pages/users.php', compact('users', 'columns', 'display', 'sort', 'paginator', 'team'));
