<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_pages', true);

$per_page = 10;
$page_num = max(1, (int) App::GET('pn', 1));
$offset = ($page_num - 1) * $per_page;
$filter_raw = trim((string) App::GET('filter', ''));
$filter = $filter_raw !== '' ? '%' . $filter_raw . '%' : '%';

$status_labels = [
	'draft' => __('admin/pages.status_draft'),
	'published' => __('admin/pages.status_published'),
	'archived' => __('admin/pages.status_archived'),
];

$status_keys = array_keys($status_labels);
$selected_statuses = App::REQ('statuses', $status_keys);

$base_join = 'FROM {pages} as p
	JOIN {pages_revs} as r ON r.page_id = p.page_id AND r.revision IN(p.revisions, p.pub_rev)';

$total_all = (int) Db::Get('SELECT count(*) ' . $base_join);

$status_counts = Db::QueryAll(
	'SELECT r.status, count(*) as cnt ' . $base_join . ' GROUP BY r.status'
);

$counts = [];

foreach ($status_counts as $row) {
	$counts[(string) ($row['status'] ?? '')] = (int) ($row['cnt'] ?? 0);
}

$pages_stats = admin_pages_build_stats($counts);
$delete_confirm = html_encode(__('admin/pages.btn_sur'));
$total_filtered = 0;
$pages = [];

if ($selected_statuses) {
	$placeholders = implode(', ', array_fill(0, count($selected_statuses), '?'));
	$query_params = array_merge([$filter], $selected_statuses);
	$where = 'WHERE r.title LIKE ? AND r.status IN (' . $placeholders . ')';

	$total_filtered = (int) Db::Get('SELECT count(*) ' . $base_join . ' ' . $where, ...$query_params);

	$pages = Db::QueryAll(
		'SELECT r.*, p.* ' . $base_join . ' ' . $where . '
		ORDER BY r.status, p.pub_date DESC
		LIMIT ' . $offset . ', ' . $per_page,
		...$query_params
	);
}
?>

<div class="admin-dashboard admin-pages">
	<?= admin_stat_grid($pages_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-pages-board">
		<div class="admin-tabs-board__body admin-pages-board__body admin-tabs-panel">
			<?php if (!$total_all): ?>
				<?= admin_pages_empty(__('admin/pages.empty_none')) ?>
			<?php else: ?>
				<form method="get" id="admin-pages-form" class="admin-pages-form">
					<input type="hidden" name="page" value="pages">
					<?= admin_pages_filters($status_labels, $selected_statuses, $filter_raw) ?>
				</form>

				<form action="?page=page_edit" method="post" class="admin-pages-list-form">
					<?= admin_csrf_field() ?>
					<input type="hidden" name="delete" value="1">
					<?= admin_pages_table($pages, $status_labels, $delete_confirm) ?>

					<?php if ($total_filtered > $per_page): ?>
						<div class="admin-pages-pager">
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
	var form = document.getElementById('admin-pages-form');
	if (!form) {
		return;
	}

	form.querySelectorAll('.admin-filter-chip__input').forEach(function (input) {
		input.addEventListener('change', function () {
			form.submit();
		});
	});
})();
</script>
