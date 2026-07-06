<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_media', true);

use Evo\Models\File;

$view = App::GET('view') === 'add' ? 'add' : 'list';
$edit_id = max(0, (int) App::GET('edit', 0));
$filter_raw = trim((string) App::GET('filter', ''));

if (admin_csrf_valid()) {
	if ($fileID = App::POST('delete')) {
		if ($file = File::find($fileID)) {
			$file->delete();
			App::setSuccess(__('admin/downloads.alert_delete_success'));
			$view = 'list';
			$edit_id = 0;
		} else {
			App::setWarning(__('admin/downloads.alert_delete_error'));
		}
	} elseif (App::POST('save_download')) {
		$file_data = (array) App::POST('file', []);
		$file_id = (int) App::POST('file_id');
		$file = $file_id > 0 ? File::find($file_id) : null;

		if ($file) {
			foreach ($file_data as $key => $value) {
				if (in_array($key, ['caption', 'description', 'name', 'posted'], true)) {
					$file->$key = $value;
				}
			}

			$file->save();
			$view = 'list';
			$edit_id = 0;
			App::setSuccess(__('admin/downloads.alert_save_success'));
		} elseif (!empty($_FILES['new_download']['tmp_name'])) {
			try {
				$file = File::create('new_download', 'downloads');

				foreach ($file_data as $key => $value) {
					if (in_array($key, ['caption', 'description', 'name', 'posted'], true)) {
						$file->$key = $value;
					}
				}

				$file->save();
				$view = 'list';
				$edit_id = 0;
				App::setSuccess(__('admin/downloads.alert_file_save_success', ['%file->name%' => $file->name]));
			} catch (Exception $e) {
				App::setWarning(__('admin/downloads.alert_file_upl_failed') . ' ' . $_FILES['new_download']['name']);
				$view = 'add';
			}
		} else {
			App::setWarning(__('admin/downloads.alert_file_required'));
			$view = 'add';
		}
	}
}

if ($edit_id > 0) {
	$view = 'add';
}

$all_files = iterator_to_array(File::select('origin = ? order by posted desc', 'downloads'));
$total_all = count($all_files);
$files = $all_files;
$downloads_stats = admin_downloads_build_stats($all_files);
$delete_confirm = html_encode(__('admin/downloads.btn_delete_confirm'), ENT_QUOTES);
$edit_file = $edit_id > 0 ? File::find($edit_id) : null;

if ($edit_id > 0 && !$edit_file) {
	$edit_id = 0;
	$view = 'list';
}
?>

<div class="admin-dashboard admin-downloads">
	<?= admin_stat_grid($downloads_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-downloads-board">
		<div class="admin-tabs-board__body admin-downloads-board__body admin-tabs-panel">
			<?php if ($view === 'add'): ?>
				<?= admin_downloads_add_board($edit_file) ?>
			<?php elseif (!$all_files): ?>
				<form method="get" id="admin-downloads-form" class="admin-downloads-form" data-admin-toolbar-search>
					<input type="hidden" name="page" value="downloads">
					<input type="hidden" name="view" value="list">
					<?= admin_downloads_filters($filter_raw) ?>
				</form>
				<?= admin_downloads_empty(__('admin/downloads.empty_none')) ?>
			<?php else: ?>
				<form method="get" id="admin-downloads-form" class="admin-downloads-form" data-admin-toolbar-search data-admin-toolbar-search-mode="live" data-admin-toolbar-search-target="#admin-downloads-list-form tbody" data-admin-toolbar-search-empty="[data-admin-downloads-search-empty]" data-admin-toolbar-search-count=".admin-downloads-table-wrap .admin-modules-table__count">
					<input type="hidden" name="page" value="downloads">
					<input type="hidden" name="view" value="list">
					<?= admin_downloads_filters($filter_raw) ?>
				</form>

				<form method="post" id="admin-downloads-list-form" class="admin-downloads-list-form">
					<?= admin_csrf_field() ?>
					<?= admin_downloads_table($files, $delete_confirm) ?>
					<div class="d-none" data-admin-downloads-search-empty>
						<?= admin_downloads_empty(__('admin/downloads.empty_filtered'), 'fa-search') ?>
					</div>
				</form>
			<?php endif; ?>
		</div>
	</section>
</div>

<?php if ($view === 'add'): ?>
	<?php include ROOT_DIR . '/includes/Editors/editors.php'; ?>
	<script>
	(function () {
		var textarea = document.getElementById('download-description-<?= $edit_file ? (int) $edit_file->id : 'new' ?>');

		if (textarea) {
			load_editor(textarea.id, '<?= App::getConfig('editor') ?>');
		}
	})();
	</script>
<?php endif; ?>
