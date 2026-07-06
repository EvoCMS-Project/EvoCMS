<?php defined('EVO') or die('Que fais-tu là?');

has_permission('mod.reports', true);

$types = Db::QueryAll('select distinct type from {reports} where deleted = 0 or deleted is null', true);
$selected_types = App::REQ('types', array_keys($types));
$per_page = 25;
$page_num = max(1, (int) App::GET('pn', 1));
$offset = ($page_num - 1) * $per_page;

if (App::POST('dismiss')) {
	if ($r = Db::Get('select * from {reports} where id = ?', App::POST('dismiss'))) {
		Db::Update('reports', ['deleted' => time()], ['id' => App::POST('dismiss')]);
		App::logEvent(0, 'admin', __('admin/general.report_log_delete_alert') . "{$r['type']}#{$r['rel_id']}: {$r['reason']}");
		App::setSuccess(__('admin/reports.alert_dismissed'));
	}
}

$type_counts = Db::QueryAll('select type, count(*) as cnt from {reports} where deleted = 0 or deleted is null group by type', true);
$total_pending = (int) Db::Get('select count(*) from {reports} where deleted = 0 or deleted is null');
$reports_stats = admin_reports_build_stats($total_pending, $type_counts);
$total_filtered = 0;
$reports = [];

if ($selected_types) {
	$placeholders = implode(', ', array_fill(0, count($selected_types), '?'));
	$total_filtered = (int) Db::Get(
		'select count(*) from {reports} where (deleted = 0 or deleted is null) and type in (' . $placeholders . ')',
		$selected_types
	);
	$reports = Db::QueryAll(
		'select r.*, u.username, if(p.message is null, if(c.message is null, up.username, c.message), p.message) as message, c.page_id
		 from {reports} as r
		 left join {users} as u on u.id = r.user_id
		 left join {users} as up on up.id = rel_id and type="profile"
		 left join {forums_posts} as p on p.id = rel_id and type = "forum"
		 left join {comments} as c on c.id = rel_id and type = "comment"
		 where (deleted = 0 or deleted is null) and r.type in (' . $placeholders . ')
		 order by reported desc
		 limit ' . $offset . ', ' . $per_page,
		$selected_types
	);
}
?>

<div class="admin-dashboard admin-reports">
	<?= admin_stat_grid($reports_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-reports-board">
		<div class="admin-tabs-board__body admin-reports-board__body admin-tabs-panel">
			<?php if (!$total_pending): ?>
				<?= admin_reports_empty(__('admin/general.report_none')) ?>
			<?php else: ?>
				<form method="post" id="admin-reports-form" class="admin-reports-form">
					<?= admin_reports_filters($types, $selected_types) ?>
					<?= admin_reports_table($reports) ?>

					<?php if ($total_filtered > $per_page): ?>
						<div class="admin-reports-pager">
							<?= Widgets::pager((int) ceil($total_filtered / $per_page), $page_num, 10); ?>
						</div>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		</div>
	</section>
</div>

<script>
(function () {
	var form = document.getElementById('admin-reports-form');
	if (!form) {
		return;
	}

	form.querySelectorAll('.admin-reports-filter-chip__input').forEach(function (input) {
		input.addEventListener('change', function () {
			form.submit();
		});
	});
})();
</script>
