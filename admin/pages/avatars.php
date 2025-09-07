<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_media', true);

$dir = ROOT_DIR . '/upload/avatars/';

if (!IS_POST) {

}
elseif (!preg_match('#^[-a-zA-Z0-9_]+$#', $cat = App::POST('categorie', '')))
{
	App::setWarning(__('admin/avatars.alert_forbiden_chars', ['%cat%' => $cat]));
}
elseif (App::POST('create')) //SI le formulaire est envoyé on éxécute le code
{
	if (file_exists($dir . $cat)) {
		App::setWarning(__('admin/avatars.alert_already_exist', ['%cat%' => $cat]));
	} elseif (@mkdir($dir . $cat, 0755, true)) {
		@touch($dir . $cat . '/index.html');
		App::setSuccess(__('admin/avatars.alert_fcreate_success', ['%cat%' => $cat]));
	} else {
		App::setWarning(__('admin/avatars.alert_fcreate_error', ['%cat%' => $cat]));
	}
}
elseif (App::POST('delete')) //SI le formulaire est envoyé on éxécute le code
{
	if (rrmdir($dir . $cat)) {
		App::setSuccess(__('admin/avatars.alert_fdelete_success', ['%cat%' => $cat]));
	} else {
		App::setWarning(__('admin/avatars.alert_fdelete_error', ['%cat%' => $cat]));
	}
}
elseif(!empty($_FILES['upload']))
{
	$files = $_FILES['upload'];
	foreach($files['name'] as $index => $name) {
		$filename = Format::safeFilename($name);
		$path = $dir . $cat . '/' . $filename;

		if ($filename == '') {
			App::setWarning(__('admin/avatars.alert_empty_name_aconv',['%name%' => $name]), true);
		}
		elseif (!preg_match('/\.(jpg|gif|png)$/', $filename) || !in_array(@getimagesize($files['tmp_name'][$index])[2], [1, 2, 3])) {
			App::setWarning(__('admin/avatars.alert_invalid_format',['%name%' => $name]), true);
		}
		elseif(file_exists($path)) {
			App::setWarning(__('admin/avatars.alert_file_exist',['%path%' => $path]), true);
		}
		elseif (move_uploaded_file($files['tmp_name'][$index], $path)) {
			chmod($path, 0755);
			App::setSuccess(__('admin/avatars.alert_avatar_added',['%name%' => $name]), true);
		}
		else {
			App::setWarning(__('admin/avatars.alert_upload_error',['%name%' => $name]), true);
		}
	}
}
?>
<div class="card">
	<div class="card-header">
		<h4><?= __('admin/avatars.title') ?></h4>
	</div>
	<div class="card-body">
	<form class="form-horizontal" role="form" style="margin-bottom: -13px;" method="post">
	  <div class="mb-3 row">
		<label class="col-sm-3 col-form-label text-right"><?= __('admin/avatars.catname') ?></label>
		<div class="col-sm-6">
		  <input type="text" class="form-control" name="categorie">
		</div>
	  <button type="submit" class="btn btn-success" style="margin-top: 2px;" name="create" value="1"><?= __('admin/avatars.btn_create') ?></button>
	  </div>
	</form>
	</div>
</div>

<?php
if ($files = glob($dir.'/*', GLOB_ONLYDIR)) {
	foreach($files as $cat_dir) {
		$cat = basename($cat_dir);

		echo '<div class="card mt-4">';
		echo '<div class="card-header">';
			echo '<form method="post" enctype="multipart/form-data"><input type="hidden" name="categorie" value="' . $cat . '">';
				echo '<button class="btn btn-danger" style="position:relative;top:-5px;float:right" onclick="return confirm(\''.__('admin/avatars.alert_delete_advise').'\');" name="delete" value="1">'.__('admin/general.btn_delete').'</button>';
				echo '<input type="file" class="float-right" name="upload[]" multiple>';
			echo '</form>';
			echo '<h4>'. __('admin/avatars.category') .' : '.$cat.'</h4>';
		echo '</div>';

		if ($avatars = glob($cat_dir.'/*.{jpg,jpeg,png,gif}', GLOB_BRACE)) {
				echo '<ul class="clearfix">';
				foreach ($avatars as $avatar) {
					$url = App::getAsset(substr($avatar, strlen(ROOT_DIR)));
					echo '<div style="padding:10px;float:left;display:block">';
						echo '<img style="border-radius:5px;margin-right:5px;margin-top:10px" width="64" src="'.$url.'">';
					echo '</div>';
				}
				echo '</ul>';
			}
		echo '</div>';
	}
}
?>

<script>
$('input[type=file]').on('change', function() {
	if ($(this)[0].files[0]) $(this).parent().submit();
});
</script>