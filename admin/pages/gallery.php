<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_media', true);

use Evo\Models\File;

$not_found = 0;

foreach(File::select() as $file) {
	if (!file_exists(ROOT_DIR.'/'.$file->path)) {
		$not_found++;
		if (App::POST('cleanup')) {
			$file->delete();
		}
	}
}

if ($not_found && App::POST('cleanup')) {
	App::setNotice("$not_found records have been removed from the database.");
	$not_found = 0;
}
?>

<?php if ($not_found) { ?>
<div class="float-right" style="padding-left:1em">
	<form method="post">
		<button name="cleanup" value="1" class="btn btn-default">Enlever les fichiers introuvables (<?= $not_found ?>)</button>
	</form>
</div>
<?php } ?>


<?php require ROOT_DIR.'/pages/gallery.php'; ?>