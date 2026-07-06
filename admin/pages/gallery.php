<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_media', true);

$embed = App::REQ('embed');
$not_found = admin_gallery_count_missing();

if ($not_found && App::POST('cleanup')) {
	$removed = admin_gallery_cleanup_missing();
	App::setNotice($removed . ' ' . __('admin/gallery.alert_cleanup'));
	$not_found = 0;
}

gallery_handle_requests(true, 'website');
$filter_raw = trim((string) App::GET('filter', ''));
$files = gallery_fetch_files(true, (bool) $embed);
$delete_confirm = html_encode(__('gallery.dialog_confirm_delete'), ENT_QUOTES);

if ($embed) {
	echo admin_gallery_embed($files);
	return;
}

$gallery_stats = admin_gallery_build_stats($files, $not_found);
?>

<div class="admin-dashboard admin-gallery">
	<?= admin_stat_grid($gallery_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-gallery-board">
		<div class="admin-tabs-board__body admin-gallery-board__body admin-tabs-panel">
			<?php if (!$files && $filter_raw === ''): ?>
				<?= admin_gallery_empty(__('admin/gallery.empty')) ?>
			<?php else: ?>
				<div class="admin-gallery-form">
					<?= admin_gallery_toolbar($not_found, false, $filter_raw) ?>
					<?= admin_gallery_content($files, $delete_confirm) ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>

<?= admin_image_preview_modal() ?>
<?= admin_gallery_scripts(true, true, false) ?>
