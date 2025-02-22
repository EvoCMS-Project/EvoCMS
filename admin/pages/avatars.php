<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_media', true);

$dir = ROOT_DIR . '/upload/avatars/';

if (!IS_POST) {

}
elseif (!preg_match('#^[-a-zA-Z0-9_]+$#', $cat = App::POST('categorie', '')))
{
	App::setWarning('Le nom de dossier contient des caractères interdits ou est vide '.$cat);
}
elseif (App::POST('create')) //SI le formulaire est envoyé on éxécute le code
{
	if (file_exists($dir . $cat)) {
		App::setWarning('Un dossier nommé '.$cat.' existe déjà!');
	} elseif (@mkdir($dir . $cat, 0755, true)) {
		@touch($dir . $cat . '/index.html');
		App::setSuccess('Dossier '.$cat.' est créé avec succès');
	} else {
		App::setWarning('Une erreur est survenu durant la création du dossier '.$cat);
	}
}
elseif (App::POST('delete')) //SI le formulaire est envoyé on éxécute le code
{
	if (rrmdir($dir . $cat)) {
		App::setSuccess('Le dossier '.$cat.' a bien été supprimé');
	} else {
		App::setWarning('Une erreur est survenu durant la suppression du dossier '.$cat);
	}
}
elseif(!empty($_FILES['upload']))
{
	$files = $_FILES['upload'];
	foreach($files['name'] as $index => $name) {
		$filename = Format::safeFilename($name);
		$path = $dir . $cat . '/' . $filename;

		if ($filename == '') {
			App::setWarning("Le nom du fichier {$name} après conversion est vide!", true);
		}
		elseif (!preg_match('/\.(jpg|gif|png)$/', $filename) || !in_array(@getimagesize($files['tmp_name'][$index])[2], [1, 2, 3])) {
			App::setWarning("Le format de {$name} n'est pas supporté\n", true);
		}
		elseif(file_exists($path)) {
			App::setWarning("Le fichier {$path} existe déjà!\n", true);
		}
		elseif (move_uploaded_file($files['tmp_name'][$index], $path)) {
			chmod($path, 0755);
			App::setSuccess("Avatar {$name} ajouté!\n", true);
		}
		else {
			App::setWarning("Erreur d'upload pour {$name}!\n", true);
		}
	}
}
?>
<div class="card">
	<div class="card-header">
		<h4>Créer une catégorie d'avatars</h4>
	</div>
	<div class="card-body">
	<form class="form-horizontal" role="form" style="margin-bottom: -13px;" method="post">
	  <div class="form-group row">
		<label class="col-sm-3 col-form-label text-right">Nom de la catégorie</label>
		<div class="col-sm-6">
		  <input type="text" class="form-control" name="categorie">
		</div>
	  <button type="submit" class="btn btn-success" style="margin-top: 2px;" name="create" value="1">Créer la catégorie</button>
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
				echo '<button class="btn btn-danger" style="position:relative;top:-5px;float:right" onclick="return confirm(\'Les fichiers seront supprimés. Continuer?\');" name="delete" value="1">Supprimer</button>';
				echo '<input type="file" class="float-right" name="upload[]" multiple>';
			echo '</form>';
			echo '<h4>Catégorie : '.$cat.'</h4>';
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