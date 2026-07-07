<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_media', true);

$dir = ROOT_DIR . '/upload/avatars/';
$view = App::GET('view') === 'create' ? 'create' : 'library';
$avatars_category_removed = false;
$avatars_ajax = IS_AJAX && IS_POST && (App::POST('delete_avatar') || App::POST('delete_category') || admin_avatars_has_uploads());

if (!IS_POST) {

}
elseif (!preg_match('#^[-a-zA-Z0-9_]+$#', $cat = App::POST('categorie', '')))
{
	App::setWarning(__('admin/avatars.alert_forbiden_chars', ['%cat%' => $cat]));

	if (App::POST('create')) {
		$view = 'create';
	}
}
elseif (App::POST('create'))
{
	if (file_exists($dir . $cat)) {
		App::setWarning(__('admin/avatars.alert_already_exist', ['%cat%' => $cat]));
		$view = 'create';
	} elseif (@mkdir($dir . $cat, 0755, true)) {
		@touch($dir . $cat . '/index.html');
		App::setSuccess(__('admin/avatars.alert_fcreate_success', ['%cat%' => $cat]));
		admin_avatars_process_uploads($dir, $cat);
		$view = 'library';
	} else {
		App::setWarning(__('admin/avatars.alert_fcreate_error', ['%cat%' => $cat]));
		$view = 'create';
	}
}
elseif (App::POST('delete_category'))
{
	if (admin_avatars_delete_category($dir, $cat)) {
		App::setSuccess(__('admin/avatars.alert_fdelete_success', ['%cat%' => $cat]));
		$avatars_category_removed = true;
	} else {
		App::setWarning(__('admin/avatars.alert_fdelete_error', ['%cat%' => $cat]));
	}

	$view = 'library';
}
elseif (App::POST('delete_avatar'))
{
	$avatar_file = (string) App::POST('delete_avatar');

	if (admin_avatars_delete_file($dir, $cat, $avatar_file)) {
		App::setSuccess(__('admin/avatars.alert_avatar_deleted', ['%name%' => basename($avatar_file)]));
	} else {
		App::setWarning(__('admin/avatars.alert_avatar_delete_error', ['%name%' => basename($avatar_file)]));
	}

	$view = 'library';
}
elseif (admin_avatars_has_uploads())
{
	admin_avatars_process_uploads($dir, $cat);
	$view = 'library';
}

$categories = admin_avatars_collect($dir);
$avatars_stats = admin_avatars_build_stats($categories);
$delete_confirm = html_encode(__('admin/avatars.alert_delete_avatar'));

if ($avatars_ajax) {
	$post_cat = App::POST('categorie', '');

	if (preg_match('#^[-a-zA-Z0-9_]+$#', $post_cat)) {
		admin_avatars_json_response($categories, $avatars_stats, $post_cat, $delete_confirm, $avatars_category_removed);
	}

	http_response_code(400);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode([
		'ok' => false,
		'alerts' => App::renderAlertsHtml(),
	], JSON_UNESCAPED_UNICODE);
	exit;
}
?>

<div class="admin-dashboard admin-avatars">
	<?= admin_stat_grid($avatars_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-avatars-board">
		<?= admin_avatars_nav($view) ?>

		<div class="tab-content admin-tabs-board__body admin-avatars-board__body admin-tabs-panel">
			<?= admin_avatars_tab_body($categories, $view, $delete_confirm) ?>
		</div>
	</section>
</div>

<?= admin_image_preview_modal() ?>
