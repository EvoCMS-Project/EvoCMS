<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_modules', true);

$modules = [];

if ($plugin_name = App::POST('activate_plugin')) {
	try {
		if (App::activateModule($plugin_name)) {
			App::setSuccess("Module <strong>$plugin_name</strong> activé!");
		}
	} catch (Exception $e) {
		App::setWarning("Impossible d'activer <strong>$plugin_name</strong>!", true);
		App::setWarning('<pre>'.html_encode($e).'</pre>', true);
	}
}

if ($plugin_name = App::POST('deactivate_plugin')) {
	try {
		if (App::deactivateModule($plugin_name)) {
			App::setSuccess("Module <strong>$plugin_name</strong> activé!");
		}
	} catch (Exception $e) {
		App::setNotice("Le module <strong>$plugin_name</strong> a été désactivé cependant il a produit une erreur:", true);
		App::setNotice("<pre>".html_encode($e).'</pre>', true);
	}
}

if ($plugin_name = App::POST('delete_plugin')) {
	if (App::deleteModule($plugin_name)) {
		App::setSuccess("Module <strong>$plugin_name</strong> supprimé!");
	}
}

// Plugin import from zip
if (isset($_FILES['plugin_file']) && is_uploaded_file($_FILES['plugin_file']['tmp_name'])) { /* Importation de theme */
	if (($zip = new ZipArchive)->open($_FILES['plugin_file']['tmp_name']) === true) {
		$tmpdir = sys_get_temp_dir() . '/' . random_hash(8);
		$zip->extractTo($tmpdir);
		$zip->close();

		$manifest = glob($tmpdir . '/{module.json,*/module.json}',  GLOB_BRACE)[0] ?? null;

		if ($manifest && $module = Evo\EvoInfo::fromFile($manifest)) {
			$target = ROOT_DIR . '/modules/' . $module->name;
			$source = dirname($manifest);
			rename($source, $target);
			App::setSuccess('Module importé. Vous pouvez maintenant l\'activer.');
		} else {
			App::setWarning('Ce module est invalide, référez vous à la documentation ou importer le manuellement via ftp.');
		}

		rrmdir($tmpdir);
	} else {
		App::setWarning('Zip invalide !');
	}
}

$updates = &$_SESSION['updates'];

foreach(glob(ROOT_DIR . '/modules/*/module.json', GLOB_BRACE) as $filename) {
	if ($module = \Evo\EvoInfo::fromFile($filename)) {
		$key = basename(dirname($filename));
		$modules[$key] = $module;
		if (empty($updates[$key]['checked']) || $updates[$key]['checked'] < time() - 300) {
			if ($update = $module->checkForUpdates()) {
				$url = html_encode($update->download ?: $update->homepage);
				$ver = html_encode($update->version);
				$updates[$key]['content'] = "<a href=\"$url\">Nouvelle version: $ver</a>";
			} else {
				$updates[$key] = ['checked' => time() , 'content' => ''];
			}
		}
	}
}

$current_plugin = App::getModule(App::GET('plugin', ''));

if (IS_POST && $current_plugin && $current_plugin->settings) {
	if (settings_save($current_plugin->settings, App::POST())) {
		App::setSuccess('Configuration mise à jour!');
	}
}
?>

<?php if (!$current_plugin && class_exists('ZipArchive')) { ?>
	<div class="float-right">
		<form method="post" class="form-horizontal" enctype="multipart/form-data">
				Installer un module: <input type="file" name="plugin_file" style="display: inline;width:200px;"><button type="submit">Upload</button>
		</form>
	</div>
<?php } ?>

<legend><a href="?page=modules">Modules additionnels</a> <?php if ($current_plugin) echo ':: Configuration de '.html_encode($current_plugin->name) ?></legend>

<?php
if ($current_plugin) {
	echo '<div class="card card-body">'.settings_form($current_plugin->settings).'</div>';
	return;
}
?>
<form method="post">
	<table class="table table-striped">
		<thead>
			<tr>
				<th>Nom du plugin</th>
				<th>Description</th>
				<th>Auteur</th>
				<th style="width: 195px"></th>
			</tr>
		</thead>
		<tbody>
		<?php
			foreach($modules as $plugin_id => $module) {
				echo '<tr>';
				echo '<td><div><strong>' . html_encode($module->name) . '</strong> '.html_encode($module->version) .'</div><small>Type: '.implode(', ', $module->exports).'</small><br><small>'.($updates[$plugin_id]['content'] ?? '').'</small></td>';
				echo '<td><p>' . html_encode($module->description) . '</p></td>';
				echo '<td>' . html_encode($module->author) . '</td>'; // <div><small>' . implode("\n", html_encode($module->contributors)) . '</small></div>
				echo '<td class="text-right">';

				if (App::getModule($plugin_id)) {
					if ($module->settings) {
						echo '<a class="btn btn-default btn-sm" href="?page=modules&plugin='.$plugin_id.'"><i class="fa fa-cog"></i> Settings</a> ';
					}
					echo '<button type="submit" name="deactivate_plugin" class="btn btn-default btn-sm btn-danger" value="'.$plugin_id.'">Deactivate</button> ';
				} else {
					echo '<button type="submit" name="activate_plugin" class="btn btn-default btn-sm btn-success" value="'.$plugin_id.'">Activate</button> ';
					echo '<button type="submit" name="delete_plugin" class="btn btn-default btn-sm btn-danger" value="'.$plugin_id.'" onclick="return confirm(\'Le module et tous ses fichiers seront supprimés. Continuer?\');">Delete</button> ';
				}

				echo '</td></tr>';
			}
		?>
		</tbody>
	</table>
</form>