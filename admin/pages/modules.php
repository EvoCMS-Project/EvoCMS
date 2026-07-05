<?php
defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_modules', true);

$modules = [];

if ($plugin_name = App::POST('activate_plugin')) {
    try {
        if (App::activateModule($plugin_name)) {
            App::setSuccess(__('admin/modules.alert_enabling_success', ['%plugin_name%' => $plugin_name]));
        }
    } catch (Exception $e) {
        App::setWarning(__('admin/modules.alert_enabling_error', ['%plugin_name%' => $plugin_name]), true);
        App::setWarning('<pre>' . html_encode($e->getMessage()) . '</pre>', true);
    }
}

if ($plugin_name = App::POST('deactivate_plugin')) {
    try {
        if (App::deactivateModule($plugin_name)) {
            App::setSuccess(__('admin/modules.alert_disabling_success', ['%plugin_name%' => $plugin_name]));
        }
    } catch (Exception $e) {
        App::setNotice(__('admin/modules.alert_disabling_error', ['%plugin_name%' => $plugin_name]), true);
        App::setNotice('<pre>' . html_encode($e->getMessage()) . '</pre>', true);
    }
}

if ($plugin_name = App::POST('delete_plugin')) {
    if (App::deleteModule($plugin_name)) {
        App::setSuccess(__('admin/modules.alert_deleted_success', ['%plugin_name%' => $plugin_name]));
    }
}

// Importation de plugin depuis un fichier ZIP
if (isset($_FILES['plugin_file']) && is_uploaded_file($_FILES['plugin_file']['tmp_name'])) {
    $zip = new ZipArchive;
    if ($zip->open($_FILES['plugin_file']['tmp_name']) === true) {
        $tmpdir = sys_get_temp_dir() . '/' . random_hash(8);
        $zip->extractTo($tmpdir);
        $zip->close();

        $manifest = glob($tmpdir . '/{module.json,*/module.json}', GLOB_BRACE)[0] ?? null;

        if ($manifest && $module = Evo\EvoInfo::fromFile($manifest)) {
            $target = ROOT_DIR . '/modules/' . $module->name;
            $source = dirname($manifest);

            if (!file_exists($target) && rename($source, $target)) {
                App::setSuccess(__('admin/modules.alert_import_success'));
            } else {
                App::setWarning(__('admin/modules.alert_import_warning'));
            }
        } else {
            App::setWarning(__('admin/modules.alert_import_warning'));
        }

        rrmdir($tmpdir);
    } else {
        App::setWarning(__('admin/modules.alert_zip_error'));
    }
}

// Gestion des mises à jour des modules
$updates = &$_SESSION['updates'];

foreach (glob(ROOT_DIR . '/modules/*/module.json', GLOB_BRACE) as $filename) {
    if ($module = \Evo\EvoInfo::fromFile($filename)) {
        $key = basename(dirname($filename));
        $modules[$key] = $module;

        if (!isset($updates[$key]['checked']) || $updates[$key]['checked'] < time() - 300) {
            $update = $module->checkForUpdates();
            $updates[$key] = [
                'checked' => time(),
                'content' => $update ? '<a href="' . html_encode($update->download ?: $update->homepage) . '">' . __('admin/modules.version_checker') . ': ' . html_encode($update->version) . '</a>' : ''
            ];
        }
    }
}

// Sauvegarde des paramètres du module actuel
$current_plugin = App::getModule(App::GET('plugin', ''));

if (IS_POST && $current_plugin && $current_plugin->settings) {
    if (settings_save($current_plugin->settings, App::POST())) {
        App::setSuccess(__('admin/modules.alert_config_updated'));
    }
}
?>
<?php if($current_plugin) { ?>
	<?= admin_card_body_open() ?><?= settings_form($current_plugin->settings) ?><?= admin_card_body_close() ?>
<?php return; }  ?>

<?php
$catalog_url = 'https://evolution-network.ca/plugin_checker.json';
$catalog_json = fetch_remote_url($catalog_url);
$data = $catalog_json ? json_decode($catalog_json) : null;

if (!$data || !isset($data->Themes, $data->Modules, $data->Langues)) {
	if ($catalog_json === null) {
		App::setNotice(__('admin/modules.alert_catalog_error'));
	}
	$gui = [];
	$mod = [];
	$lang = [];
} else {
	$gui = $data->Themes;
	$mod = $data->Modules;
	$lang = $data->Langues;
}

$mod_delete_confirm = html_encode(__('admin/modules.btn_delete_onclic'));
?>

<form method="post">
	<?= admin_card_header('<ul class="nav nav-tabs card-header-tabs" id="tab" role="tablist">
			<li class="nav-item" role="tab"><a class="nav-link active" id="installed-tab" data-bs-toggle="tab" href="#installed" role="tab" aria-controls="installed" aria-selected="true">' . __('admin/modules.tab_installed') . '</a></li>
			<li class="nav-item" role="tab"><a class="nav-link" id="themes-tab" data-bs-toggle="tab" href="#themes" role="tab" aria-controls="themes" aria-selected="false">' . __('admin/modules.tab_themes') . '</a></li>
			<li class="nav-item" role="tab"><a class="nav-link" id="modules-tab" data-bs-toggle="tab" href="#modules" role="tab" aria-controls="modules" aria-selected="false">' . __('admin/modules.tab_modules') . '</a></li>
			<li class="nav-item" role="tab"><a class="nav-link" id="lang-tab" data-bs-toggle="tab" href="#lang" role="tab" aria-controls="lang" aria-selected="false">' . __('admin/modules.tab_languages') . '</a></li>
			<li class="nav-item" role="tab"><a class="nav-link" id="import-tab" data-bs-toggle="tab" href="#import" role="tab" aria-controls="import" aria-selected="false">' . __('admin/modules.tab_import') . '</a></li>
			<li class="nav-item" role="tab"><a class="nav-link" id="settings-tab" data-bs-toggle="tab" href="#settings" role="tab" aria-controls="settings" aria-selected="false">' . __('admin/modules.tab_settings') . '</a></li>
			</ul>') ?>
	<div class="card-body tab-content" id="TabContent">
			<div class="tab-pane fade show active" id="installed" role="tabpanel" aria-labelledby="installed-tab">
				<div class="card-body">
					<h5 class="card-title"><?= __('admin/modules.section_themes') ?></h5>
					<p class="card-text">
						<table class="table table-borderless table-sm table-responsive-lg small">
							<thead class="table-dark">
								<th><?= __('admin/modules.table_name') ?></th>
								<th><?= __('admin/modules.table_desc') ?></th>
								<th><?= __('admin/modules.table_author') ?></th>
								<th><?= __('admin/modules.table_version_installed') ?></th>
								<th><?= __('admin/modules.table_version_available') ?></th>
								<th class="center"><?= __('admin/modules.table_action') ?></th>
							</thead>
							<tbody>
								<?php
                                    foreach ($modules as $plugin_id => $module) {
                                        if ($module->exports[0] == "theme") { 
                                            echo "<tr>
                                            <td><a href='" . html_encode($module->homepage) . "' target='_blank'>" . html_encode($module->name) . "</a></td>
                                            <td>" . html_encode($module->description) . "</td>
                                            <td>" . implode("\n", array_map('html_encode', $module->getAuthors())) . "</td>
                                            <td>1.3.x</td>
                                            <td>" . html_encode($module->version) . "</td>
                                            <td class='right'>";

                                            if (App::getModule($plugin_id)) {
                                                if ($module->settings) {
                                                    echo '<a href="?page=modules&plugin=' . $plugin_id . '" class="btn btn-sm btn-outline-primary">' . __('admin/modules.btn_settings') . '</a> ';
                                                }
                                                echo '<button type="submit" name="deactivate_plugin" class="btn btn-sm btn-outline-warning" value="' . $plugin_id . '">' . __('admin/modules.btn_disabling') . '</button> ';
                                            } else {
                                                echo '<button type="submit" name="activate_plugin" class="btn btn-sm btn-outline-success" value="' . $plugin_id . '">' . __('admin/modules.btn_enabling') . '</button> ';
                                                echo '<button type="submit" name="delete_plugin" class="btn btn-sm btn-outline-danger" value="' . $plugin_id . '" onclick="return confirm(\'' . $mod_delete_confirm . '\');">' . __('admin/modules.btn_delete_') . '</button> ';
                                            }

                                            echo "</td>
                                            </tr>";
                                        }
                                    }
								?>
							</tbody>
						</table>
					</p>
				</div>
				<div class="card-body">
					<h5 class="card-title"><?= __('admin/modules.section_modules') ?></h5>
					<p class="card-text">
						<table class="table table-borderless table-sm table-responsive-lg small">
							<thead class="table-dark">
								<th><?= __('admin/modules.table_name') ?></th>
								<th><?= __('admin/modules.table_desc') ?></th>
								<th><?= __('admin/modules.table_author') ?></th>
								<th><?= __('admin/modules.table_version_installed') ?></th>
								<th><?= __('admin/modules.table_version_available') ?></th>
								<th class="center"><?= __('admin/modules.table_action') ?></th>
							</thead>
							<tbody>
								<?php
                                    foreach ($modules as $plugin_id => $module) {
                                        if ($module->exports[0] == "plugin") { 
                                            echo "<tr>
                                            <td><a href='" . html_encode($module->homepage) . "' target='_blank'>" . html_encode($module->name) . "</a></td>
                                            <td>" . html_encode($module->description) . "</td>
                                            <td>" . implode("\n", array_map('html_encode', $module->getAuthors())) . "</td>
                                            <td>1.3.x</td>
                                            <td>" . html_encode($module->version) . "</td>
                                            <td class='right'>";

                                            if (App::getModule($plugin_id)) {
                                                if ($module->settings) {
                                                    echo '<a href="?page=modules&plugin=' . $plugin_id . '" class="btn btn-sm btn-outline-primary">' . __('admin/modules.btn_settings') . '</a> ';
                                                }
                                                echo '<button type="submit" name="deactivate_plugin" class="btn btn-sm btn-outline-warning" value="' . $plugin_id . '">' . __('admin/modules.btn_disabling') . '</button> ';
                                            } else {
                                                echo '<button type="submit" name="activate_plugin" class="btn btn-sm btn-outline-success" value="' . $plugin_id . '">' . __('admin/modules.btn_enabling') . '</button> ';
                                                echo '<button type="submit" name="delete_plugin" class="btn btn-sm btn-outline-danger" value="' . $plugin_id . '" onclick="return confirm(\'' . $mod_delete_confirm . '\');">' . __('admin/modules.btn_delete_') . '</button> ';
                                            }

                                            echo "</td>
                                            </tr>";
                                        }
                                    }
								?>
							</tbody>
						</table>
					</p>
				</div>
			</div>
			<div class="tab-pane fade" id="themes" role="tabpanel" aria-labelledby="themes-tab">
				<table class="table table-borderless table-sm table-responsive-lg small">
					<thead class="table-dark">
						<th><?= __('admin/modules.table_name') ?></th>
						<th><?= __('admin/modules.table_desc') ?></th>
						<th><?= __('admin/modules.table_author') ?></th>
						<th><?= __('admin/modules.table_cms_version') ?></th>
						<th><?= __('admin/modules.table_plugin_version') ?></th>
						<th></th>
					</thead>
					<tbody class="table-hover">
						<?php foreach ($gui as $key => $value) : ?>						
							<tr>
								<td class="text-muted"><?= $value->name ?></td>
								<td class="text-muted"><?= $value->description ?></td>
								<td class="text-muted"><?= $value->author ?></td>
								<td class="text-muted"><?= $value->cms_version ?></td>
								<td class="text-muted"><?= $value->plugin_version ?></td>
								<td style="text-align: right">
									<?php if($value->download) : ?><a href="<?= $value->download ?>" target="_blank" class="btn btn-sm"><i class="fas fa-lg fa-download"></i> <?= __('admin/modules.btn_download') ?></a> <?php endif; ?>
									<?php if($value->download) : ?><a href="#" target="_blank" class="btn btn-sm"><i class="fas fa-lg fa-microchip"></i> <?= __('admin/modules.btn_install') ?></a> <?php endif; ?>
									<?php if($value->preview) : ?><a href="<?= $value->preview ?>" target="_blank" class="btn btn-sm"><i class="far fa-lg fa-images"></i> <?= __('admin/modules.btn_preview') ?></a> <?php endif; ?>
									<?php if($value->website) : ?><a href="<?= $value->website ?>" target="_blank" class="btn btn-sm"><i class="fas fa-lg fa-globe-americas"></i> <?= __('admin/modules.btn_website') ?></a> <?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="tab-pane fade" id="modules" role="tabpanel" aria-labelledby="modules-tab">
				<table class="table table-borderless table-sm table-responsive-lg small">
					<thead class="table-dark">
						<th><?= __('admin/modules.table_name') ?></th>
						<th><?= __('admin/modules.table_desc') ?></th>
						<th><?= __('admin/modules.table_author') ?></th>
						<th><?= __('admin/modules.table_cms_version') ?></th>
						<th><?= __('admin/modules.table_plugin_version') ?></th>
						<th></th>
					</thead>
					<tbody class="table-hover">
						<?php foreach ($mod as $key => $value) : ?>						
							<tr>
								<td class="text-muted"><?= $value->name ?></td>
								<td class="text-muted"><?= $value->description ?></td>
								<td class="text-muted"><?= $value->author ?></td>
								<td class="text-muted"><?= $value->cms_version ?></td>
								<td class="text-muted"><?= $value->plugin_version ?></td>
								<td style="text-align: right">
									<?php if($value->download) : ?><a href="<?= $value->download ?>" target="_blank" class="btn btn-sm"><i class="fas fa-lg fa-download"></i> <?= __('admin/modules.btn_download') ?></a> <?php endif; ?>
									<?php if($value->download) : ?><a href="#" target="_blank" class="btn btn-sm"><i class="fas fa-lg fa-microchip"></i> <?= __('admin/modules.btn_install') ?></a> <?php endif; ?>
									<?php if($value->preview) : ?><a href="<?= $value->preview ?>" target="_blank" class="btn btn-sm"><i class="far fa-lg fa-images"></i> <?= __('admin/modules.btn_preview') ?></a> <?php endif; ?>
									<?php if($value->website) : ?><a href="<?= $value->website ?>" target="_blank" class="btn btn-sm"><i class="fas fa-lg fa-globe-americas"></i> <?= __('admin/modules.btn_website') ?></a> <?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="tab-pane fade" id="lang" role="tabpanel" aria-labelledby="lang-tab">
				<table class="table table-borderless table-sm table-responsive-lg small">
					<thead class="table-dark">
						<th><?= __('admin/modules.table_name') ?></th>
						<th><?= __('admin/modules.table_author') ?></th>
						<th><?= __('admin/modules.table_progress') ?></th>
						<th><?= __('admin/modules.table_cms_version') ?></th>
						<th></th>
					</thead>
					<tbody class="table-hover">
						<?php foreach ($lang as $key => $value) : ?>						
							<tr>
								<td class="text-muted"><?= flag_icon_html($value->flag, @COUNTRIES[$value->flag] ?? $value->name) ?> <?= html_encode($value->name) ?></td>
								<td class="text-muted"><?= $value->author ?></td>
								<td>
									<div class="progress" style="margin-top: 5px;">
										<div class="progress-bar progress-bar-striped <?php if($value->progress === '100'){ echo 'bg-success'; }else{ echo 'bg-warning'; } ?> progress-bar-animated" role="progressbar" aria-valuenow="<?= $value->progress ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?= $value->progress ?>%"></div>
									</div>
								</td>
								<td class="text-muted"><?= $value->cms_version ?></td>
								<td style="text-align: right">
									<?php if($value->download) : ?><a href="<?= $value->download ?>" target="_blank" class="btn btn-sm"><i class="fas fa-lg fa-download"></i> <?= __('admin/modules.btn_download') ?></a> <?php endif; ?>
									<?php if($value->download) : ?><a href="#" target="_blank" class="btn btn-sm"><i class="fas fa-lg fa-microchip"></i> <?= __('admin/modules.btn_install') ?></a> <?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="tab-pane fade" id="import" role="tabpanel" aria-labelledby="import-tab">
				<?php if (!$current_plugin && class_exists('ZipArchive')) { ?>
					<div class="float-right">
						<form method="post" class="form-horizontal" enctype="multipart/form-data">
								<?= __('admin/modules.header_form') ?>: <input type="file" name="plugin_file" style="display: inline;width:200px;"><button type="submit"><?= __('admin/modules.header_form_btn_upload') ?></button>
						</form>
					</div>
				<?php } ?>
			</div>
			<div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">...</div>
	</div>
</form>