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

$current_plugin = App::getModule(App::GET('plugin', ''));

if (IS_POST && $current_plugin && $current_plugin->settings) {
    if (settings_save($current_plugin->settings, App::POST())) {
        App::setSuccess(__('admin/modules.alert_config_updated'));
    }
}

$tab = App::GET('tab', 'installed');

if (IS_POST) {
    if (App::POST('activate_plugin') || App::POST('deactivate_plugin') || App::POST('delete_plugin')) {
        $tab = 'installed';
    } elseif (isset($_FILES['plugin_file'])) {
        $tab = 'import';
    }
}

$installed_themes = [];
$installed_plugins = [];

foreach ($modules as $plugin_id => $module) {
    $item = admin_modules_installed_item($plugin_id, $module, $updates);

    if (($module->exports[0] ?? '') === 'theme') {
        $installed_themes[] = $item;
    } else {
        $installed_plugins[] = $item;
    }
}

$catalog_url = 'https://evolution-network.ca/plugin_checker.json';
$catalog_json = fetch_remote_url($catalog_url);
$data = $catalog_json ? json_decode($catalog_json) : null;
$catalog_unavailable = ($catalog_json === null);

if (!$data || !isset($data->Themes, $data->Modules, $data->Langues)) {
    $gui = [];
    $mod = [];
    $lang = [];
} else {
    $gui = $data->Themes;
    $mod = $data->Modules;
    $lang = $data->Langues;
}

$mod_delete_confirm = html_encode(__('admin/modules.btn_delete_onclic'));

$modules_nav = [
    'installed' => ['label' => __('admin/modules.tab_installed'), 'icon' => 'fa-box-open'],
    'themes' => ['label' => __('admin/modules.tab_themes'), 'icon' => 'fa-palette'],
    'modules' => ['label' => __('admin/modules.tab_modules'), 'icon' => 'fa-puzzle-piece'],
    'lang' => ['label' => __('admin/modules.tab_languages'), 'icon' => 'fa-language'],
    'import' => ['label' => __('admin/modules.tab_import'), 'icon' => 'fa-file-import'],
];

$modules_stats = [
    ['icon' => 'fa fa-box', 'value' => (string) count($modules), 'label' => __('admin/modules.stats_installed'), 'variant' => 'primary'],
    ['icon' => 'fa fa-check-circle', 'value' => (string) count(App::getModules()), 'label' => __('admin/modules.stats_active'), 'variant' => 'success'],
    ['icon' => 'fa fa-palette', 'value' => (string) count($installed_themes), 'label' => __('admin/modules.stats_themes'), 'variant' => 'info'],
    ['icon' => 'fa fa-puzzle-piece', 'value' => (string) count($installed_plugins), 'label' => __('admin/modules.stats_modules'), 'variant' => 'warning'],
];
?>

<?php if ($current_plugin): ?>
<div class="admin-dashboard admin-modules admin-modules--config">
    <section class="admin-tabs-board admin-modules-board">
        <div class="admin-tabs-board__body admin-modules-board__body admin-tabs-panel admin-modules-board__body--config">
            <?= admin_modules_config_board($current_plugin) ?>
        </div>
    </section>
</div>
<?php return; endif; ?>

<div class="admin-dashboard admin-modules">
    <?= admin_stat_grid($modules_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

    <section class="admin-tabs-board admin-modules-board">
        <?= admin_modules_nav($modules_nav, $tab) ?>

        <div class="tab-content admin-tabs-board__body admin-modules-board__body admin-tabs-panel admin-modules-board__body--content">
            <?= admin_modules_tab_open('installed', $tab === 'installed') ?>
                <?= admin_modules_installed_board($installed_themes, $installed_plugins, $mod_delete_confirm) ?>
            <?= admin_modules_tab_close() ?>

            <?= admin_modules_tab_open('themes', $tab === 'themes') ?>
                <?= admin_modules_catalog_notice($catalog_unavailable) ?>
                <?= admin_modules_catalog_board($gui, [
                    'title' => __('admin/modules.tab_themes'),
                    'icon' => 'fa fa-palette',
                    'accent' => 'info',
                    'empty' => __('admin/modules.empty_catalog'),
                ]) ?>
            <?= admin_modules_tab_close() ?>

            <?= admin_modules_tab_open('modules', $tab === 'modules') ?>
                <?= admin_modules_catalog_notice($catalog_unavailable) ?>
                <?= admin_modules_catalog_board($mod, [
                    'title' => __('admin/modules.tab_modules'),
                    'icon' => 'fa fa-puzzle-piece',
                    'accent' => 'primary',
                    'empty' => __('admin/modules.empty_catalog'),
                ]) ?>
            <?= admin_modules_tab_close() ?>

            <?= admin_modules_tab_open('lang', $tab === 'lang') ?>
                <?= admin_modules_catalog_notice($catalog_unavailable) ?>
                <?= admin_modules_lang_board($lang) ?>
            <?= admin_modules_tab_close() ?>

            <?= admin_modules_tab_open('import', $tab === 'import') ?>
                <?= admin_modules_import_board() ?>
            <?= admin_modules_tab_close() ?>
        </div>
    </section>
</div>
