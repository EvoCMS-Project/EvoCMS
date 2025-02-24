<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_modules', true);

$modules = [];

if ($plugin_name = App::POST('activate_plugin')) {
	try {
		if (App::activateModule($plugin_name)) {
			App::setSuccess(__('admin/modules.alert_enabling_success', ['%plugin_name%' => $plugin_name]));
		}
	} catch (Exception $e) {
		App::setWarning(__('admin/modules.alert_enabling_error'), true);
		App::setWarning('<pre>'.html_encode($e).'</pre>', true);
	}
}

if ($plugin_name = App::POST('deactivate_plugin')) {
	try {
		if (App::deactivateModule($plugin_name)) {
			App::setSuccess(__('admin/modules.alert_disabling_success', ['%plugin_name%' => $plugin_name]));
		}
	} catch (Exception $e) {
		App::setNotice(__('admin/modules.alert_disabling_error'), true);
		App::setNotice("<pre>".html_encode($e).'</pre>', true);
	}
}

if ($plugin_name = App::POST('delete_plugin')) {
	if (App::deleteModule($plugin_name)) {
		App::setSuccess(__('admin/modules.alert_deleted_success', ['%plugin_name%' => $plugin_name]));
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
			App::setSuccess(__('admin/modules.alert_import_success'));
		} else {
			App::setWarning(__('admin/modules.alert_import_warning'));
		}

		rrmdir($tmpdir);
	} else {
		App::setWarning(__('admin/modules.alert_zip_error'));
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
				$updates[$key]['content'] = "<a href=\"$url\">". __('admin/modules.version_checker') ." : $ver</a>";
			} else {
				$updates[$key] = ['checked' => time() , 'content' => ''];
			}
		}
	}
}

$current_plugin = App::getModule(App::GET('plugin', ''));

if (IS_POST && $current_plugin && $current_plugin->settings) {
	if (settings_save($current_plugin->settings, App::POST())) {
		App::setSuccess(__('admin/modules.alert_config_updated'));
	}
}
?>

<?php if (!$current_plugin && class_exists('ZipArchive')) { ?>
	<div class="float-right">
		<form method="post" class="form-horizontal" enctype="multipart/form-data">
				<?= __('admin/modules.header_form') ?> : <input type="file" name="plugin_file" style="display: inline;width:200px;"><button type="submit"><?= __('admin/modules.header_form_btn_upload') ?></button>
		</form>
	</div>
<?php } ?>

<legend><a href="?page=modules"><?= __('admin/modules.main_title') ?></a> <?php if ($current_plugin) echo ':: Configuration de '.html_encode($current_plugin->name) ?></legend>

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
				<th><?= __('admin/modules.table_name') ?></th>
				<th><?= __('admin/modules.table_desc') ?></th>
				<th><?= __('admin/modules.table_author') ?></th>
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
						echo '<a class="btn btn-default btn-sm" href="?page=modules&plugin='.$plugin_id.'"><i class="fa fa-cog"></i> '. __('admin/modules.btn_settings') .'</a> ';
					}
					echo '<button type="submit" name="deactivate_plugin" class="btn btn-default btn-sm btn-danger" value="'.$plugin_id.'">'. __('admin/modules.btn_disabling') .'</button> ';
				} else {
					echo '<button type="submit" name="activate_plugin" class="btn btn-default btn-sm btn-success" value="'.$plugin_id.'">'. __('admin/modules.btn_enabling') .'</button> ';
					echo '<button type="submit" name="delete_plugin" class="btn btn-default btn-sm btn-danger" value="'.$plugin_id.'" onclick="return confirm(\''.__('admin/modules.btn_delete_onclic').'\');">'.__('admin/general.btn_delete').'</button> ';
				}

				echo '</td></tr>';
			}
		?>
		</tbody>
	</table>
</form>