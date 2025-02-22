<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_media', true);

use Evo\Models\File;

if ($fileID = App::POST('delete')) {
	if ($file = File::find($fileID)) {
		$file->delete();
		App::setSuccess('Fichier supprimé !');
	} else {
		App::setWarning('Erreur lors de la suppression.');
	}
}
elseif (!empty($_FILES['new_download'])) {
	try {
		$file = File::create('new_download', 'downloads');
		$fileID = $file->id;
		App::setSuccess('Fichier ' . $file->name . ' enregistré !');
	} catch (Exception $e) {
		App::setWarning('Erreur lors de l\'upload de ' . $_FILES['new_download']['name']);
	}
}
elseif ($files = App::POST('files')) {
	foreach((array)$files as $fileID => $newValues) {
		if ($file = File::find($fileID)) {
			foreach($newValues as $key => $value) {
				$file->$key = $value;
			}
			$file->save();
			App::setSuccess('Changements enregistrés.');
		}
	}
}
?>
<legend>Page de téléchargements</legend>
<div class="accordion" id="accordion">
	<div class="card">
		<div class="card-header">
			<div class="mb-0" data-toggle="collapse" data-target="#new">
				<a href="#"><strong>Nouveau téléchargement</strong></a>
			</div>
		</div>
		<div id="new" class="card-body collapse <?= empty($fileID) ? 'show' : 'out' ?>" data-parent="#accordion">
			<form class="form-horizontal" method="post" enctype="multipart/form-data">
				<div class="form-group row">
					<div class="col-sm-6 col-form-label text-right">
						<input type="file" name="new_download" style="display:inline;">
					</div>
					<div class="col-sm-6">
						<input type="submit" value="Envoyer" class="btn btn-sm btn-primary">
					</div>
				</div>
			</form>
		</div>
	</div>


<?php foreach(File::select('origin = ? order by posted desc', 'downloads') as $i => $file) { ?>
<div class="card">
	<div class="card-header">
		<div class="float-right"><?= Format::today($file->posted)  ?></div>
		<div class="mb-0" data-toggle="collapse" data-target="#file<?= $i;?>">
			<a href="#"><?= html_encode($file->caption); ?></a>
		</div>
	</div>
	<div id="file<?= $i ?>" class="collapse <?= ($i == $fileID) ? 'show' : 'out' ?>" data-parent="#accordion">
		<div class="card-body">
			<form method="post">
				<button type="submit" hidden></button>
				<?php
					echo '  <div class="form-horizontal">';
					echo '     <div class="form-group row"><span class="col-sm-2 col-form-label text-right">Title:</span><div class="col-sm-10"><input name="files['.$i.'][caption]" type="text" value="'.html_encode($file->caption).'" class="form-control"></div></div>';
					echo '     <div class="form-group row"><span class="col-sm-2 col-form-label text-right">Description:</span><div class="col-sm-10"><textarea name="files['.$i.'][description]" id="textarea-'.$i.'" class="form-control" style="height: 250px;">'.html_encode($file->description).'</textarea></div></div>';
					echo '     <div class="form-group row"><span class="col-sm-2 col-form-label text-right">File name: </span><div class="col-sm-10"><input name="files['.$i.'][name]" type="text" value="'.html_encode($file->name).'" class="form-control"></div></div>';
					echo '     <div class="form-group row"><span class="col-sm-2 col-form-label text-right">Date posted:</span><div class="col-sm-3"><input name="files['.$i.'][posted]" type="text" value="'.date('Y-m-d H:i', $file->posted).'" class="form-control"></div></div>';
					echo '     <div class="form-group row"><span class="col-sm-2 col-form-label text-right"></span>';
					echo '         <div class="col-sm-2">Taille: <strong>'.Format::size($file->size).'</strong></div>';
					echo '         <div class="col-sm-2">Type: <strong>'.$file->type.'</strong></div>';
					echo '         <div class="col-sm-3">Mime: <strong>'.$file->mime_type.'</strong></div>';
					echo '         <div class="col-sm-2">Hits: <strong>'.$file->hits.'</strong></div>';
					echo '  </div>';
					echo '</div>';
				?>
				<div class="text-center">
				<?php
						echo '     <button value="'.$i.'" class="btn btn-sm btn-primary" type="submit">Enregistrer</button>';
						echo '     <a class="btn btn-sm btn-info" href="'.App::getURL($file->path).'">Ouvrir</a>';
						echo '     <button onclick="return confirm(\'Sur?\');" name="delete" value="'.$i.'" class="btn btn-sm btn-danger">Supprimer</button>';
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
