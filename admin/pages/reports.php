<?php defined('EVO') or die('Que fais-tu là?');

has_permission('mod.reports', true);

$types = Db::QueryAll('select distinct type from {reports}', true);
$selected_types = App::REQ('types', array_keys($types));

if (App::POST('dismiss')) {
	if ($r = Db::Get('select * from {reports} where id = ?', App::POST('dismiss'))) {
		Db::Update('reports', ['deleted' => time()], ['id' => App::POST('dismiss')]);
		App::logEvent(0, 'admin', __('admin/general.report_log_delete_alert') ."{$r['type']}#{$r['rel_id']}: {$r['reason']}");
	}
}

if ($selected_types) {
	$reports = Db::QueryAll('select r.*, u.username , if(p.message is null, if(c.message is null, up.username, c.message), p.message) as message, c.page_id
									 from {reports} as r
									 left join {users} as u on u.id = r.user_id
									 left join {users} as up on up.id = rel_id and type="profile"
									 left join {forums_posts} as p on p.id = rel_id and type = "forum"
									 left join {comments} as c on c.id = rel_id and type = "comment"
									 where deleted = 0 and `type` in (' . implode(', ', array_fill(0, count($selected_types), '?')) . ')
									 order by reported desc', $selected_types);
} else {
	$reports = array();
}
$ptotal = 0;
?>
<?php if (!$reports) { ?>
	<br><div style="text-align: center;" class="alert alert-warning"><?= __('admin/general.report_none') ?></div>
<?php } else { ?>
	<legend><?= __('admin/general.report_title')?></legend>
	<form method="post" id="content">
		<div class="float-end">
		<?php
			foreach($types as $t) {
				if (empty ($selected_types) || in_array($t['type'], $selected_types))
					echo '<label><input name="types[]" type="checkbox" value="'.$t['type'].'" checked> '.html_encode($t['type']).'</label>&nbsp;&nbsp;&nbsp;';
				else
					echo '<label><input name="types[]" type="checkbox" value="'.$t['type'].'"> '.html_encode($t['type']).'</label>&nbsp;&nbsp;&nbsp;';
			}
		?>
		</div>
		<table class="table table-lists">
			<thead>
				<th><?= __('admin/general.report_member')?></th>
				<th><?= __('admin/general.report_summary')?></th>
				<th><?= __('admin/general.report_reason')?></th>
				<th></th>
			</thead>
			<tbody>
			<?php
				foreach($reports as $r) {
					switch($r['type']){
						case 'forum': $link = App::getURL('forums', ['pid'=>$r['rel_id']], 'alert'.$r['rel_id']); break;
						case 'comment': $link = App::getURL('pageview', $r['page_id'], 'alert'.$r['rel_id']); break;
						case 'profile': $link = App::getURL('user', $r['rel_id']); break;
					}
					echo '<tr><td>' . html_encode($r['username'] ?: $r['user_ip']) . '</td>';
					echo '<td><small>' . html_encode(Format::truncate(strip_tags($r['message']),60)) . '</small></td>';
					echo '<td>' . html_encode($r['reason']) . '</td>';
					echo '<td style="text-align:right;width:auto;"><a class="btn btn-primary btn-sm" style="color:white" href="' . $link . '">Voir</a>&nbsp;&nbsp;<button name="dismiss" value="' . $r['id'] . '" class="btn btn-sm btn-warning" >Ignorer</button></td></tr>';
				}
				?>
			</tbody>
		</table>
		<script>
			$('input[type=checkbox]').on('change', function () {
				$('form').submit();
			});
		</script>
		<?= Widgets::pager($ptotal , App::GET('pn', 1), 10); ?>
	</form>
<?php } ?>