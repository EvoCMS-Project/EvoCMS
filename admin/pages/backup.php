<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.backup', true);

if (!class_exists('ZipArchive')) {
    die(__('admin/system.backup_phpzip_required'));
}

if ($type = App::GET('type')) {
    ignore_user_abort(true);
    set_time_limit(0);
    ob_end_clean();

    $tmp_file = tempnam(sys_get_temp_dir(), 'evo-backup');
    $zip = new Evo\BetterZip();
    $zip->open($tmp_file, Evo\BetterZip::CREATE);

    if ($type === 'web') {
        App::logEvent(0, 'admin', __('admin/system.backup_logevent_web'));
        $zip->addDir(ROOT_DIR, ROOT_DIR);
    } elseif ($type === 'sql') {
        App::logEvent(0, 'admin', __('admin/system.backup_logevent_sql'));
        $zip->addFromString('backup_sql-'.date('Y-m-d_Hi').'.sql', Db::Export());
    }

    $zip->close();

    if (file_exists($tmp_file)) {
        header('Content-disposition: attachment; filename="backup_'.$type.'-'.date('Y-m-d_Hi').'.zip"');
        header('Content-Length: '.filesize($tmp_file));
        header('Content-Transfer-Encoding: Binary');
        header('Content-Type: application/zip');
        readfile($tmp_file);
        unlink($tmp_file);
    }

    die;
}
?>
<legend>Backup</legend>

<a href="?page=backup&type=web" class="btn btn-sm btn-primary"><?= __('admin/system.backup_files') ?></a>
<a href="?page=backup&type=sql" class="btn btn-sm btn-primary"><?= __('admin/system.backup_db') ?></a>
