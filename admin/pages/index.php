<?php defined('EVO') or die('Que fais-tu là?');

$load_avg = function_exists('sys_getloadavg') && ($load = sys_getloadavg())
	? number_format($load[0], 2, '.', '')
	: '&mdash;';

$dashboard_stats = [
	['icon' => 'fa-solid fa-file-lines', 'value' => (string) Db::Get('select count(*) from {pages} where type = "article"'), 'label' => __('admin/dashboard.circle_Articles'), 'variant' => 'primary', 'url' => '?page=pages'],
	['icon' => 'fa-solid fa-copy', 'value' => (string) Db::Get('select count(*) from {pages} where type <> "article"'), 'label' => __('admin/dashboard.circle_Pages'), 'variant' => 'info', 'url' => '?page=pages'],
	['icon' => 'fa-solid fa-comments', 'value' => (string) Db::Get('select count(*) from {comments}'), 'label' => __('admin/dashboard.circle_Comments'), 'variant' => 'success', 'url' => '?page=comments'],
	['icon' => 'fa-solid fa-images', 'value' => (string) Db::Get('select count(*) from {files}'), 'label' => __('admin/dashboard.circle_Files'), 'variant' => 'warning', 'url' => '?page=gallery'],
	['icon' => 'fa-solid fa-comment-dots', 'value' => (string) Db::Get('select count(*) from {forums_topics}'), 'label' => __('admin/dashboard.circle_Discuss'), 'variant' => 'secondary', 'url' => '?page=forums'],
	['icon' => 'fa-solid fa-reply-all', 'value' => (string) Db::Get('select count(*) from {forums_posts}'), 'label' => __('admin/dashboard.circle_msg_forum'), 'variant' => 'secondary', 'url' => '?page=forums'],
	['icon' => 'fa-solid fa-users', 'value' => (string) Db::Get('select count(*) from {users} where id <> 0'), 'label' => __('admin/dashboard.circle_Members'), 'variant' => 'danger', 'url' => '?page=users'],
	['icon' => 'fa-solid fa-gears', 'value' => (string) count(App::getModules()), 'label' => __('admin/dashboard.circle_Modules'), 'variant' => 'primary', 'url' => '?page=modules'],
];

$dashboard_panels = [
	[
		'title' => __('admin/dashboard.section_cms'),
		'icon' => 'fa-solid fa-cube',
		'accent' => 'primary',
		'items' => [
			['label' => __('admin/dashboard.info_software'), 'value' => html_encode(EVO_VERSION) . ' <span class="text-muted">(' . date('Y-m-d', strtotime(EVO_RELEASEDATE)) . ')</span>'],
			['label' => __('admin/dashboard.info_commit'), 'value' => html_encode(EVO_BUILD) . ' <span class="text-muted">(' . date('Y-m-d', strtotime(EVO_BUILDDATE)) . ')</span>'],
			['label' => __('admin/dashboard.info_space'), 'value' => html_encode(Format::size(Db::Get('select sum(size) from {files}')))],
		],
	],
	[
		'title' => __('admin/dashboard.section_environment'),
		'icon' => 'fa-solid fa-server',
		'accent' => 'info',
		'items' => [
			['label' => __('admin/dashboard.info_php'), 'value' => '<a href="?page=phpinfo">' . html_encode(preg_replace('/\+.+$/', '', phpversion())) . '</a>'],
			['label' => __('admin/dashboard.info_sql'), 'value' => html_encode(Db::DriverName() . ' ' . Db::ServerVersion())],
			['label' => __('admin/dashboard.info_load'), 'value' => $load_avg],
		],
	],
	[
		'title' => __('admin/dashboard.info_dev'),
		'icon' => 'fa-solid fa-code',
		'accent' => 'amber',
		'items' => [
			['label' => 'Yan Bourgeois', 'value' => 'Designer <span class="text-muted">(Coolternet)</span>'],
			['label' => 'Alex Duchesne', 'value' => 'Développeur <span class="text-muted">(Alexus)</span>'],
			['label' => __('admin/dashboard.info_credits'), 'value' => '<a href="#credits" class="admin-dashboard__credits-link" data-bs-toggle="modal" data-bs-target="#credits">' . html_encode(__('admin/dashboard.view_credits')) . '</a>'],
		],
	],
];
?>

<div class="admin-dashboard">
	<?= admin_stat_grid($dashboard_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>
	<?= admin_info_grid($dashboard_panels, ['variant' => 'modern', 'columns' => 3, 'class' => 'mb-0']) ?>
</div>

<div id="credits" class="modal fade" tabindex="-1" aria-labelledby="credits-title" aria-hidden="true">
	<div class="modal-dialog modal-dialog-scrollable modal-lg" role="document">
		<div class="modal-content border-0 shadow admin-credits-modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="credits-title"><?= __('admin/dashboard.info_credits') ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('messages/form.cancel') ?>"></button>
			</div>
			<div class="modal-body admin-credits-modal">
				<div class="admin-credits-group">
					<h6>PHP</h6>
					<ul class="list-unstyled mb-0">
						<li><a href="http://raymondhill.net/blog/?p=441">FineDiff</a> <span class="text-muted">&mdash; MIT</span></li>
						<li><a href="http://parsedown.org">Parsedown</a> <span class="text-muted">&mdash; MIT</span></li>
						<li><a href="https://github.com/clouddueling/mysqldump-php">MySQLDump</a> <span class="text-muted">&mdash; MIT</span></li>
						<li><a href="http://maxmind.com">GeoIP</a> <span class="text-muted">&mdash; LGPL</span></li>
						<li><a href="http://www.adminer.org/">Adminer</a> <span class="text-muted">&mdash; Apache License</span></li>
					</ul>
				</div>
				<div class="admin-credits-group">
					<h6>Frontend</h6>
					<ul class="list-unstyled mb-0">
						<li><a href="http://jquery.com">jQuery</a> <span class="text-muted">&mdash; MIT</span></li>
						<li><a href="http://getbootstrap.com">Bootstrap</a> <span class="text-muted">&mdash; MIT</span></li>
						<li><a href="http://ckeditor.com/">CKEditor</a> <span class="text-muted">&mdash; MPL</span></li>
						<li><a href="http://fancyapps.com/fancybox">Fancybox</a> <span class="text-muted">&mdash; MIT</span></li>
						<li><a href="http://markitup.jaysalvat.com/">MarkItUp</a> <span class="text-muted">&mdash; MIT</span></li>
					</ul>
				</div>
				<div class="admin-credits-group mb-0">
					<h6>Icons</h6>
					<ul class="list-unstyled mb-0">
						<li><a href="http://fortawesome.github.io/Font-Awesome/">Font Awesome</a> <span class="text-muted">&mdash; SIL OFL 1.1</span></li>
						<li><a href="http://www.famfamfam.com/lab/icons/silk/">famfamfam Silk</a> <span class="text-muted">&mdash; CC BY 2.5</span></li>
						<li>Nomicons <span class="text-muted">&mdash; CC BY 2.5</span></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
