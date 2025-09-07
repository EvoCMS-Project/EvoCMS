<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_media', true);

use Evo\Models\File;

if ($fileID = App::POST('delete')) {
	if ($file = File::find($fileID)) {
		$file->delete();
		App::setSuccess(__('admin/downloads.alert_delete_success'));
	} else {
		App::setWarning(__('admin/downloads.alert_delete_error'));
	}
}
elseif (!empty($_FILES['new_download'])) {
	try {
		$file = File::create('new_download', 'downloads');
		$fileID = $file->id;
		App::setSuccess(__('admin/downloads.alert_file_save_success', ['%file->name%' => $file->name]));
	} catch (Exception $e) {
		App::setWarning(__('admin/downloads.alert_file_upl_failed'). ' ' . $_FILES['new_download']['name']);
	}
}
elseif ($files = App::POST('files')) {
	foreach((array)$files as $fileID => $newValues) {
		if ($file = File::find($fileID)) {
			foreach($newValues as $key => $value) {
				$file->$key = $value;
			}
			$file->save();
			App::setSuccess(__('admin/downloads.alert_save_success'));
		}
	}
}
?>
<legend><?= __('admin/downloads.title') ?></legend>
<div class="accordion" id="accordion">
	<div class="card">
		<div class="card-header">
			<div class="mb-0" data-bs-toggle="collapse" data-bs-target="#new">
				<a href="#"><strong><?= __('admin/downloads.new_download') ?></strong></a>
			</div>
		</div>
		<div id="new" class="card-body collapse <?= empty($fileID) ? 'show' : 'out' ?>" data-bs-parent="#accordion">
			<form class="form-horizontal" method="post" enctype="multipart/form-data">
				<div class="mb-3 row">
					<div class="col-sm-6 col-form-label text-end">
						<input type="file" name="new_download" style="display:inline;">
					</div>
					<div class="col-sm-6">
						<input type="submit" value="<?= __('admin/downloads.btn_send') ?>" class="btn btn-sm btn-primary">
					</div>
				</div>
			</form>
		</div>
	</div>


<?php foreach(File::select('origin = ? order by posted desc', 'downloads') as $i => $file) { ?>
<div class="card">
	<div class="card-header">
		<div class="float-end"><?= Format::today($file->posted)  ?></div>
		<div class="mb-0" data-bs-toggle="collapse" data-bs-target="#file<?= $i;?>">
			<a href="#"><?= html_encode($file->caption); ?></a>
		</div>
	</div>
	<div id="file<?= $i ?>" class="collapse <?= ($i == $fileID) ? 'show' : 'out' ?>" data-bs-parent="#accordion">
		<div class="card-body">
			<form method="post">
				<button type="submit" hidden></button>
				<?php
					echo '  <div class="form-horizontal">';
					echo '     <div class="mb-3 row"><span class="col-sm-2 col-form-label text-end">'. __('admin/downloads.info_title') .' :</span><div class="col-sm-10"><input name="files['.$i.'][caption]" type="text" value="'.html_encode($file->caption).'" class="form-control"></div></div>';
					echo '     <div class="mb-3 row"><span class="col-sm-2 col-form-label text-end">'. __('admin/downloads.info_desc') .' :</span><div class="col-sm-10"><textarea name="files['.$i.'][description]" id="textarea-'.$i.'" class="form-control" style="height: 250px;">'.html_encode($file->description).'</textarea></div></div>';
					echo '     <div class="mb-3 row"><span class="col-sm-2 col-form-label text-end">'. __('admin/downloads.info_fname') .' : </span><div class="col-sm-10"><input name="files['.$i.'][name]" type="text" value="'.html_encode($file->name).'" class="form-control"></div></div>';
					echo '     <div class="mb-3 row"><span class="col-sm-2 col-form-label text-end">'. __('admin/downloads.info_dposted') .' :</span><div class="col-sm-3"><input name="files['.$i.'][posted]" type="text" value="'.date('Y-m-d H:i', $file->posted).'" class="form-control"></div></div>';
					echo '     <div class="mb-3 row"><span class="col-sm-2 col-form-label text-end"></span>';
					echo '         <div class="col-sm-2">'. __('admin/downloads.info_size') .' : <strong>'.Format::size($file->size).'</strong></div>';
					echo '         <div class="col-sm-2">'. __('admin/downloads.info_type') .' : <strong>'.$file->type.'</strong></div>';
					echo '         <div class="col-sm-3">'. __('admin/downloads.info_mime') .' : <strong>'.$file->mime_type.'</strong></div>';
					echo '         <div class="col-sm-2">'. __('admin/downloads.info_hits') .' : <strong>'.$file->hits.'</strong></div>';
					echo '  </div>';
					echo '</div>';
				?>
				<div class="text-center">
				<?php
						echo '     <button value="'.$i.'" class="btn btn-sm btn-primary" type="submit">Enregistrer</button>';
						echo '     <a class="btn btn-sm btn-info" href="'.App::getURL($file->path).'">Ouvrir</a>';
						echo '     <button onclick="return confirm(\'Sur?\');" name="delete" value="'.$i.'" class="btn btn-sm btn-danger">'. __('admin/general.btn_delete') .'</button>';
				?>
				</div>
			</form>
		</div>
	</div>
</div>
<?php } ?>
</div>
<?php include ROOT_DIR . '/includes/Editors/editors.php'; ?>
<script>
$('textarea').each(function() {
	load_editor(this.id, '<?= App::getConfig('editor') ?>');
});
</script>
