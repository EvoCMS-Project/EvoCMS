<?php defined('EVO') or die('Que fais-tu là?');

has_permission('moderator', true);

if (isset($_REQUEST['filter'])) {
	$columns = array_diff(Db::GetColumns('users', true), ['password', 'raf', 'raf_token']);
	$search = build_search_query($_REQUEST['filter'], preg_replace('/^/', 'a.', $columns));
	$where = $search['where'];
	$args = $search['args'];
} else {
	$where = 'a.id <> 0';
	$args = [];
}

$upp = 15;
$start = isset($_REQUEST['pn']) ? ($_REQUEST['pn']-1) * $upp: 0;

$args[] = $start;
$args[] = $upp;

$users = Db::QueryAll("SELECT a.*, g.name as gname, g.color as color, b.reason as ban_reason
					   FROM {users} as a
					   LEFT JOIN {groups} as g ON g.id = a.group_id
					   LEFT JOIN {banlist} as b ON (a.username = b.rule and b.type = 'username') or (a.last_ip = b.rule and b.type = 'ip') or (a.email = b.rule and b.type = 'email')
					   WHERE $where ORDER BY g.priority ASC, g.id DESC, username ASC LIMIT ?,?", $args);

// NOTE: found_rows not available with sqlite...
$ptotal = ceil(Db::Get('select count(*) from {users} as a left join {groups} as g on g.id = a.group_id where ' . $where, array_slice($args, 0, -2)) / $upp);
?>
<form role="search" class="well" style="background:transparent" method="post">
	<input id="filter" name="filter" type="text" class="form-control" value="<?php echo isset($_REQUEST['filter']) ? html_encode($_REQUEST['filter']) : '';?>" placeholder="<?= __('admin/users.search_placeholder') ?>">
</form>
<form method="post">
<div id="content">
	<?php if (!$users): ?>
		<div style="text-align: center;" class="alert alert-warning"><?= __('admin/users.alert_not_found') ?></div>
	<?php else: ?>
	<table class="table table-users">
		<thead>
			<th style="width:115px"> </th>
			<th><?= __('admin/users.table_username') ?></th>
			<th><?= __('admin/users.table_email') ?></th>
			<th><?= __('admin/users.table_rank') ?></th>
			<th><?= __('admin/users.table_country') ?></th>
			<th><?= __('admin/users.table_management') ?></th>
		</thead>
		<tbody>
		<?php
		foreach($users as $member)
			{
				$vie = __('admin/users.result_life') .' : ' . Format::today($member['activity'], 'H:i').'<br>'. __('admin/users.result_last_ip') .' : '.$member['last_ip'].' (' . @COUNTRIES[geoip_country_code($member['last_ip'])] .')';

				echo '<tr class="'.($member['ban_reason'] !== null ? 'danger':'').'">';
					echo '<td>'.($member['activity'] > time() - 120 ? '<a class="ico-online" title="'. __('admin/users.result_online') .' </br>'.$vie.'"></a>' : '<a class="ico-offline" title="'. __('admin/users.result_offline') .' <br>'.$vie.'"></a>' ).' '.Widgets::userAgentIcons($member['last_user_agent']).'</td>';
					echo '<td><a href="'.App::getAdminURL('user_view', ['id' => $member['id']]).'">'.html_encode($member['username']).'</a></td>';
					echo "<td>".html_encode($member['email'])."</td>";
					echo '<td><a class="group-color-'.$member['color'].'" href="?page=users&filter=group_id:%20'.$member['group_id'].'">'.$member['gname'].'</a></td>';
					echo '<td>'.Widgets::countryFlag($member['country']).'</td>';
					echo '<td>';

					if (has_permission('admin.edit_uprofile'))
						echo '<a href="?page=user_view&id='.$member['id'].'" class="btn btn-primary btn-sm" title="'. __('admin/users.result_edit') .'"><i class="fa fa-pencil-alt"></i></a> ';

					if (has_permission('admin.del_member'))
						echo '<a href="?page=user_delete&id='.$member['id'].'" class="btn btn-danger btn-sm" title="'. __('admin/users.result_delete') .'"><i class="far fa-trash-alt"></i></a> ';

					if (has_permission('mod.ban_member')) {
						if ($member['ban_reason'] !== null)
							echo '<a href="?page=banlist&filter='.$member['username'].','.$member['last_ip'].','.$member['email'].'" class="btn btn-info btn-sm" title="'. __('admin/users.result_unban') .'" fancybox-title="'. __('admin/users.result_unban') .'"><i class="fa fa-unlock"></i></a> ';
						else
							echo '<a href="?page=banlist&hide&username='.$member['username'].'&ip='.$member['last_ip'].'&email='.$member['email'].'" class="btn btn-info btn-sm" title="'. __('admin/users.result_ban') .'" fancybox-title="'. __('admin/users.result_ban') .'"><i class="fa fa-lock"></i></a> ';
					}

					if (App::groupHasPermission($member['group_id'], 'admin.backup'))
						echo '<button class="btn btn-warning btn-sm" title="'. __('admin/users.result_btn_title_sadm') .'"><i class="fa fa-star"> </i></button> ';

					elseif (App::groupHasPermission($member['group_id'], 'administrator'))
						echo '<button class="btn btn-warning btn-sm" title="'. __('admin/users.result_btn_title_adm') .'"><i class="fa fa-star-half-o"> </i></button> ';

					elseif (App::groupHasPermission($member['group_id'], 'moderator'))
						echo '<button class="btn btn-warning btn-sm" title="'. __('admin/users.result_btn_title_mod') .'"><i class="fa fa-star-o"></i></button> ';

					echo '</td>';
				echo '</tr>';
			};
		?>
		</tbody>
	</table>
	<?php endif; ?>
<?= Widgets::pager(count($users) < $upp ? App::GET('pn', 1) : $ptotal, App::GET('pn', 1), 10, null, App::GET('prevpn')); ?>
</div>
</form>