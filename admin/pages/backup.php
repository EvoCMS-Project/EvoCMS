<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.backup', true);

if (!class_exists('ZipArchive')) {
    die(__('admin/system.backup_phpzip_required'));
}

// Configuration des sauvegardes
$backup_dir = ROOT_DIR . '/backups';
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0755, true)) {
        die(__('admin/system.backup_error_dir_create', ['%dir%' => $backup_dir]));
    }
}

// Vérifier que le répertoire est accessible en écriture
if (!is_writable($backup_dir)) {
    die(__('admin/system.backup_error_dir_writable', ['%dir%' => $backup_dir]));
}

// Vérifier et exécuter les sauvegardes automatiques
checkAndExecuteAutoBackups();

// Téléchargement de sauvegarde spécifique
if ($action = App::GET('action')) {
    if ($action === 'download' && $file = App::GET('file')) {
        $filepath = $backup_dir . '/' . basename($file);
        
        if (file_exists($filepath) && is_file($filepath)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($filepath);
            exit;
        } else {
            die(__('admin/system.backup_error_file_not_found', ['%file%' => htmlspecialchars($file)]));
        }
    }
}

// Fonctions de gestion des sauvegardes
function createBackup($type, $name = '', $compression = 6, $exclude = []) {
    $backup_dir = ROOT_DIR . '/backups';
    
    
    // S'assurer que le répertoire de sauvegarde existe
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0755, true)) {
            throw new Exception(__('admin/system.backup_error_dir_create', ['%dir%' => $backup_dir]));
        }
    }
    
    // Vérifier que le répertoire est accessible en écriture
    if (!is_writable($backup_dir)) {
        throw new Exception(__('admin/system.backup_error_dir_writable', ['%dir%' => $backup_dir]));
    }
    
    // Utiliser le nom personnalisé s'il est fourni, sinon générer automatiquement
    if ($name && !empty(trim($name))) {
        $filename = $name . '.zip';
    } else {
        $typeNames = [
            'web' => 'files',
            'sql' => 'databases', 
            'full' => 'full',
            'config' => 'config'
        ];
        $dateStr = date('Y-m-d_H-i-s');
        $filename = 'backup-' . $typeNames[$type] . '-' . $dateStr . '.zip';
    }
    
    $filepath = $backup_dir . '/' . $filename;
    
    
    $zip = new Evo\BetterZip();
    if (!$zip->open($filepath, Evo\BetterZip::CREATE)) {
        throw new Exception(__('admin/system.backup_error_create_file', ['%path%' => $filepath]));
    }
    
    try {
        switch ($type) {
            case 'web':
                $zip->addDir(ROOT_DIR, ROOT_DIR, $exclude);
                break;
            case 'sql':
                $zip->addFromString('database.sql', Db::Export());
                break;
            case 'full':
                $zip->addDir(ROOT_DIR, ROOT_DIR, $exclude);
                $zip->addFromString('database.sql', Db::Export());
                break;
            case 'config':
                $config_files = ['config.php', 'module.json'];
                foreach ($config_files as $file) {
                    if (file_exists(ROOT_DIR . '/' . $file)) {
                        $zip->addFile(ROOT_DIR . '/' . $file, $file);
                    }
                }
                break;
    }

    $zip->close();

        // Vérifier que le fichier a été créé
        if (!file_exists($filepath)) {
            throw new Exception(__('admin/system.backup_error_not_created', ['%path%' => $filepath]));
        }
        
        
        // Enregistrer dans la table backups
        $backup_data = [
            'filename' => $filename,
            'type' => $type,
            'size' => filesize($filepath),
            'compression_level' => $compression,
            'exclude_files' => implode("\n", $exclude),
            'created_by' => App::getCurrentUser()->id,
            'created_at' => time(),
            'status' => 'completed',
            'description' => 'Sauvegarde créée via l\'interface admin',
            'file_path' => $filepath,
            'checksum' => md5_file($filepath)
        ];
        
        try {
            Db::Insert('backups', [$backup_data]);
            App::logEvent(0, 'admin', 'Sauvegarde enregistrée en DB: ' . $filename);
        } catch (Exception $e) {
            // Log l'erreur mais ne pas faire échouer la sauvegarde
            App::logEvent(0, 'admin', 'Erreur enregistrement backup: ' . $e->getMessage());
        }
        
        App::logEvent(0, 'admin', 'Sauvegarde créée: ' . $filename);
        return $filename;
        
    } catch (Exception $e) {
        $zip->close();
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        throw $e;
    }
}

function scheduleBackup($type, $frequency, $time, $retention) {
    // Sauvegarder les paramètres dans la base de données
    App::setConfig('backup.auto.enabled', '1');
    App::setConfig('backup.auto.type', $type);
    App::setConfig('backup.auto.frequency', $frequency);
    App::setConfig('backup.auto.time', $time);
    App::setConfig('backup.auto.retention', $retention);
    
    // Calculer la prochaine exécution basée sur l'heure spécifiée
    $next_run = calculateNextRunTime($frequency, $time);
    App::setConfig('backup.auto.next_run', $next_run);
    
    App::logEvent(0, 'admin', 'Sauvegarde automatique programmée: ' . $frequency . ' à ' . $time);
}

function calculateNextRunTime($frequency, $time) {
    $now = time();
    $today = date('Y-m-d');
    $scheduled_time = strtotime($today . ' ' . $time);
    
    // Si l'heure programmée est déjà passée aujourd'hui, programmer pour demain
    if ($scheduled_time <= $now) {
        switch ($frequency) {
            case 'daily':
                $scheduled_time += 24 * 60 * 60;
                break;
            case 'weekly':
                $scheduled_time += 7 * 24 * 60 * 60;
                break;
            case 'monthly':
                $scheduled_time += 30 * 24 * 60 * 60;
                break;
        }
    } else {
        // L'heure n'est pas encore passée, programmer selon la fréquence
        switch ($frequency) {
            case 'daily':
                // Garder l'heure d'aujourd'hui
                break;
            case 'weekly':
                $scheduled_time += 7 * 24 * 60 * 60;
                break;
            case 'monthly':
                $scheduled_time += 30 * 24 * 60 * 60;
                break;
        }
    }
    
    return $scheduled_time;
}

function checkAndExecuteAutoBackups() {
    // Vérifier si les sauvegardes automatiques sont activées
    if (!App::getConfig('backup.auto.enabled', '0')) {
        return;
    }
    
    $next_run = App::getConfig('backup.auto.next_run', 0);
    $now = time();
    
    // Vérifier si c'est le moment d'exécuter la sauvegarde
    if ($now >= $next_run) {
        $type = App::getConfig('backup.auto.type', 'full');
        $frequency = App::getConfig('backup.auto.frequency', 'daily');
        $time = App::getConfig('backup.auto.time', '02:00');
        $retention = App::getConfig('backup.auto.retention', 30);
        
        try {
            // Créer la sauvegarde automatique
            $filename = createBackup($type, '', 6, []);
            
            // Programmer la prochaine exécution
            $next_run = calculateNextRunTime($frequency, $time);
            App::setConfig('backup.auto.next_run', $next_run);
            
            // Nettoyer les anciennes sauvegardes selon la rétention
            cleanupOldBackups($retention);
            
            App::logEvent(0, 'admin', 'Sauvegarde automatique exécutée avec succès: ' . $filename);
            
        } catch (Exception $e) {
            App::logEvent(0, 'admin', 'Erreur lors de la sauvegarde automatique: ' . $e->getMessage());
            
            // Programmer la prochaine tentative dans 1 heure
            App::setConfig('backup.auto.next_run', $now + 3600);
        }
    }
}

function cleanupOldBackups($retention_days) {
    $backup_dir = ROOT_DIR . '/backups';
    $cutoff_time = time() - ($retention_days * 24 * 60 * 60);
    
    if (is_dir($backup_dir)) {
        $files = glob($backup_dir . '/*.zip');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
                App::logEvent(0, 'admin', 'Ancienne sauvegarde supprimée: ' . basename($file));
            }
        }
    }
}

function restoreBackup($filename, $type) {
    $backup_dir = ROOT_DIR . '/backups';
    
    $filepath = $backup_dir . '/' . $filename;
    if (!file_exists($filepath)) {
        return false;
    }
    
    $zip = new Evo\BetterZip();
    if (!$zip->open($filepath)) {
        return false;
    }
    
    try {
        switch ($type) {
            case 'files':
                $zip->extractTo(ROOT_DIR);
                break;
            case 'database':
                $sql = $zip->getFromName('database.sql');
                if ($sql) {
                    Db::Import($sql);
                }
                break;
            case 'full':
                $zip->extractTo(ROOT_DIR);
                $sql = $zip->getFromName('database.sql');
                if ($sql) {
                    Db::Import($sql);
                }
                break;
        }
        
        $zip->close();
        App::logEvent(0, 'admin', 'Sauvegarde restaurée: ' . $filename);
        return true;
    } catch (Exception $e) {
        $zip->close();
        App::logEvent(0, 'admin', 'Erreur restauration: ' . $e->getMessage());
        return false;
    }
}

function deleteBackup($filename) {
    $backup_dir = ROOT_DIR . '/backups';
    
    $filepath = $backup_dir . '/' . $filename;
    if (file_exists($filepath)) {
        unlink($filepath);
        
        // Supprimer l'entrée de la table backups
        try {
            Db::Delete('backups', ['filename' => $filename]);
        } catch (Exception $e) {
            App::logEvent(0, 'admin', 'Erreur suppression backup DB: ' . $e->getMessage());
        }
        
        App::logEvent(0, 'admin', 'Sauvegarde supprimée: ' . $filename);
        return true;
    }
    return false;
}

$tab = App::GET('tab', 'create');

// Traitement des actions
if ($_POST) {
    
    $action = App::POST('action');
    
    
    switch ($action) {
        case 'create_backup':
            $type = App::POST('backup_type');
            $name = App::POST('backup_name');
            $compression = (int)App::POST('compression_level', 6);
            $exclude = array_filter(explode("\n", App::POST('exclude_files', '')));
            
            
            try {
                $filename = createBackup($type, $name, $compression, $exclude);
                App::setNotice(__('admin/system.backup_alert_created_success', ['%filename%' => $filename]), 'success');
            } catch (Exception $e) {
                App::setNotice(__('admin/system.backup_alert_created_error', ['%error%' => $e->getMessage()]), 'danger');
            }
            $tab = 'create';
            break;
            
        case 'schedule_backup':
            $type = App::POST('schedule_type');
            $frequency = App::POST('schedule_frequency');
            $time = App::POST('schedule_time');
            $retention = (int)App::POST('schedule_retention', 30);
            
            scheduleBackup($type, $frequency, $time, $retention);
            App::setNotice(__('admin/system.backup_alert_scheduled_success'), 'success');
            $tab = 'schedule';
            break;
            
        case 'restore_backup':
            $filename = App::POST('backup_file');
            $restore_type = App::POST('restore_type');
            
            if (restoreBackup($filename, $restore_type)) {
                App::setNotice(__('admin/system.backup_alert_restored_success', ['%filename%' => $filename]), 'success');
            } else {
                App::setNotice(__('admin/system.backup_alert_restore_error', ['%filename%' => $filename]), 'danger');
            }
            $tab = 'list';
            break;
            
        case 'delete_backup':
            $filename = App::POST('backup_file');
            if (deleteBackup($filename)) {
                App::setNotice(__('admin/system.backup_alert_deleted_success', ['%filename%' => $filename]), 'success');
            } else {
                App::setNotice(__('admin/system.backup_alert_delete_error', ['%filename%' => $filename]), 'danger');
            }
            $tab = 'list';
            break;
            
        case 'delete_multiple':
            $files = App::POST('files', []);
            $deleted = 0;
            foreach ($files as $file) {
                if (deleteBackup($file)) {
                    $deleted++;
                }
            }
            App::setNotice(__('admin/system.backup_alert_deleted_multiple', ['%count%' => $deleted]), 'success');
            $tab = 'list';
            break;
            
        case 'cleanup_old':
            $days = (int)App::POST('retention_days', 30);
            $cutoff_time = time() - ($days * 24 * 60 * 60);
            $to_delete = 0;

            foreach (glob($backup_dir . '/*.zip') ?: [] as $file) {
                if (filemtime($file) < $cutoff_time) {
                    $to_delete++;
                }
            }

            cleanupOldBackups($days);
            App::setNotice(__('admin/system.backup_alert_cleanup_done', ['%count%' => $to_delete]), 'success');
            $tab = 'list';
            break;
            
        case 'export_config':
            $config = [
                'backup_dir' => $backup_dir,
                'auto_enabled' => App::getConfig('backup.auto.enabled', '0'),
                'auto_type' => App::getConfig('backup.auto.type', 'full'),
                'auto_frequency' => App::getConfig('backup.auto.frequency', 'daily'),
                'auto_time' => App::getConfig('backup.auto.time', '02:00'),
                'auto_retention' => App::getConfig('backup.auto.retention', '30')
            ];
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="backup_config.json"');
            echo json_encode($config, JSON_PRETTY_PRINT);
            die;
            
        case 'toggle_auto_backup':
            $enabled = App::getConfig('backup.auto.enabled', '0');
            $new_status = $enabled ? '0' : '1';
            App::setConfig('backup.auto.enabled', $new_status);
            App::setNotice($new_status ? __('admin/system.backup_alert_auto_enabled') : __('admin/system.backup_alert_auto_disabled'), 'success');
            $tab = 'schedule';
            break;
            
        case 'test_auto_backup':
            try {
                $type = App::getConfig('backup.auto.type', 'full');
                $filename = createBackup($type, '', 6, []);
                App::setNotice(__('admin/system.backup_alert_auto_test_success', ['%filename%' => $filename]), 'success');
                App::logEvent(0, 'admin', 'Test de sauvegarde automatique exécuté: ' . $filename);
            } catch (Exception $e) {
                App::setNotice(__('admin/system.backup_alert_auto_test_error', ['%error%' => $e->getMessage()]), 'danger');
                App::logEvent(0, 'admin', 'Erreur test sauvegarde automatique: ' . $e->getMessage());
            }
            $tab = 'schedule';
            break;
            
        case 'download':
            $filename = App::POST('file');
            $filepath = $backup_dir . '/' . basename($filename);
            
            if (file_exists($filepath) && is_file($filepath)) {
        header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
                header('Content-Length: ' . filesize($filepath));
                header('Cache-Control: no-cache, must-revalidate');
                readfile($filepath);
                exit;
            } else {
                App::setNotice(__('admin/system.backup_error_file_not_found', ['%file%' => $filename]), 'danger');
            }
            break;
    }
    
}

// Récupérer la liste des sauvegardes depuis la table
$backups = [];

try {
    $db_backups = Db::QueryAll('SELECT * FROM {backups} ORDER BY created_at DESC');
    
    foreach ($db_backups as $backup) {
        $filepath = $backup_dir . '/' . $backup['filename'];
        
        if (file_exists($filepath)) {
            $backup_data = [
                'id' => $backup['id'],
                'filename' => $backup['filename'],
                'size' => $backup['size'],
                'date' => date('Y-m-d H:i:s', $backup['created_at']),
                'age_days' => floor((time() - $backup['created_at']) / (24 * 60 * 60)),
                'type' => $backup['type'],
                'status' => $backup['status'],
                'created_by' => $backup['created_by'],
                'description' => $backup['description'],
                'checksum' => $backup['checksum']
            ];
            $backups[] = $backup_data;
        } else {
        }
    }
} catch (Exception $e) {
    
    // Fallback sur les fichiers si la table n'existe pas
    $files = glob($backup_dir . '/*.zip');
    
    foreach ($files as $file) {
        $filename = basename($file);
        $type = 'unknown';
        
        if (preg_match('/backup-(files|databases|full|config)-/', $filename, $matches)) {
            $typeMap = ['files' => 'web', 'databases' => 'sql', 'full' => 'full', 'config' => 'config'];
            $type = $typeMap[$matches[1]];
        } elseif (preg_match('/backup_(web|sql|full|config)_/', $filename, $matches)) {
            $type = $matches[1];
        }
        
        $backup_data = [
            'filename' => $filename,
            'size' => filesize($file),
            'date' => date('Y-m-d H:i:s', filemtime($file)),
            'age_days' => floor((time() - filemtime($file)) / (24 * 60 * 60)),
            'type' => $type,
            'status' => 'completed'
        ];
        $backups[] = $backup_data;
    }
}

// Trier par date (plus récent en premier)
usort($backups, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});


// Configuration de la planification depuis la base de données
$schedule_config = null;
$auto_enabled = App::getConfig('backup.auto.enabled', '0');
if ($auto_enabled) {
    $schedule_config = [
        'type' => App::getConfig('backup.auto.type', 'full'),
        'frequency' => App::getConfig('backup.auto.frequency', 'daily'),
        'time' => App::getConfig('backup.auto.time', '02:00'),
        'retention' => App::getConfig('backup.auto.retention', '30'),
        'last_run' => App::getConfig('backup.auto.last_run', '0'),
        'next_run' => App::getConfig('backup.auto.next_run', '0')
    ];
}

// Fonction utilitaire pour formater les tailles
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

// Calculer les statistiques
$total_size = array_sum(array_column($backups, 'size'));
$free_space = disk_free_space($backup_dir);
$last_backup = !empty($backups) ? $backups[0]['date'] : null;
$old_backups = array_filter($backups, function($b) { return $b['age_days'] > 30; });

if ($quick_type = App::GET('type')) {
    if (in_array($quick_type, ['web', 'sql'], true)) {
        try {
            $filename = createBackup($quick_type);
            header('Location: ?page=backup&action=download&file=' . urlencode($filename));
            exit;
        } catch (Exception $e) {
            App::setNotice(__('admin/system.backup_alert_created_error', ['%error%' => $e->getMessage()]), 'danger');
        }
    }
}

$backup_type_labels = [
    'web' => __('admin/system.backup_type_web'),
    'sql' => __('admin/system.backup_type_sql'),
    'full' => __('admin/system.backup_type_full'),
    'config' => __('admin/system.backup_type_config'),
    'unknown' => __('admin/system.backup_table_type'),
];

$backup_nav = [
    'create' => ['label' => __('admin/system.backup_tab_create'), 'icon' => 'fa-solid fa-circle-plus'],
    'schedule' => ['label' => __('admin/system.backup_tab_schedule'), 'icon' => 'fa-solid fa-clock'],
    'list' => [
        'label' => __('admin/system.backup_tab_list'),
        'icon' => 'fa-solid fa-box-archive',
        'badge' => '<span class="badge bg-secondary ms-1">' . count($backups) . '</span>',
    ],
];

$backup_stats = [
    [
        'icon' => 'fa-solid fa-archive',
        'value' => (string) count($backups),
        'label' => __('admin/system.backup_stats_count'),
        'variant' => 'primary',
    ],
    [
        'icon' => 'fa-solid fa-hard-drive',
        'value' => formatBytes($total_size),
        'label' => __('admin/system.backup_stats_used'),
        'variant' => 'warning',
    ],
    [
        'icon' => 'fa-solid fa-database',
        'value' => formatBytes($free_space),
        'label' => __('admin/system.backup_stats_free'),
        'variant' => 'success',
    ],
    [
        'icon' => 'fa-solid fa-clock',
        'value' => $last_backup ? date('d/m/Y H:i', strtotime($last_backup)) : '&mdash;',
        'label' => __('admin/system.backup_stats_last'),
        'variant' => 'info',
    ],
];
?>

<div class="admin-dashboard admin-backup">
    <?= admin_stat_grid($backup_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

    <section class="admin-tabs-board admin-backup-board">
        <?= admin_backup_nav($backup_nav, $tab) ?>

        <div class="admin-tabs-board__body admin-backup-board__body admin-tabs-panel admin-backup-board__body--content">
            <div class="admin-backup-board__notice">
                <?php if ($schedule_config): ?>
                    <?= admin_status_bar(
                        'success',
                        '<i class="fa-solid fa-circle-check me-1"></i> ' . __('admin/system.backup_auto_enabled'),
                        __('admin/system.backup_auto_type') . ': ' . ($backup_type_labels[$schedule_config['type']] ?? ucfirst($schedule_config['type']))
                            . ' &middot; ' . __('admin/system.backup_auto_frequency') . ': ' . ucfirst($schedule_config['frequency'])
                            . ' &middot; ' . __('admin/system.backup_auto_time') . ': ' . $schedule_config['time']
                            . ' &middot; ' . __('admin/system.backup_auto_retention') . ': ' . $schedule_config['retention'] . ' ' . __('admin/system.backup_auto_days')
                            . '<br><strong>' . __('admin/system.backup_auto_next_run') . '</strong> '
                            . ($schedule_config['next_run'] ? date('d/m/Y H:i', $schedule_config['next_run']) : __('admin/system.backup_auto_not_scheduled'))
                            . ($schedule_config['last_run'] ? '<br><strong>' . __('admin/system.backup_auto_last_run') . '</strong> ' . date('d/m/Y H:i', $schedule_config['last_run']) : ''),
                        [
                            'action' => 'toggle_auto_backup',
                            'label' => __('admin/system.backup_auto_btn_disable'),
                            'icon' => 'fa-solid fa-pause',
                            'variant' => 'danger',
                        ]
                    ) ?>
                <?php else: ?>
                    <?= admin_status_bar(
                        'info',
                        '<i class="fa-solid fa-circle-info me-1"></i> ' . __('admin/system.backup_auto_disabled'),
                        '',
                        [
                            'action' => 'toggle_auto_backup',
                            'label' => __('admin/system.backup_auto_btn_enable'),
                            'icon' => 'fa-solid fa-play',
                            'variant' => 'primary',
                        ]
                    ) ?>
                <?php endif; ?>
            </div>

            <?php if ($tab === 'create'): ?>
                <div class="admin-backup-board__pane">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <?php ob_start(); ?>
                            <form method="post" id="backupForm">
                                <?= admin_csrf_field() ?>
                                <input type="hidden" name="action" value="create_backup">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="backup_type" class="form-label small"><?= __('admin/system.backup_type_label') ?></label>
                                        <select name="backup_type" id="backup_type" class="form-select" required>
                                            <option value="web"><?= __('admin/system.backup_type_web') ?></option>
                                            <option value="sql"><?= __('admin/system.backup_type_sql') ?></option>
                                            <option value="full"><?= __('admin/system.backup_type_full') ?></option>
                                            <option value="config"><?= __('admin/system.backup_type_config') ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="compression_level" class="form-label small"><?= __('admin/system.backup_compression_label') ?></label>
                                        <select name="compression_level" id="compression_level" class="form-select">
                                            <option value="0"><?= __('admin/system.backup_compression_none') ?></option>
                                            <option value="1"><?= __('admin/system.backup_compression_low') ?></option>
                                            <option value="3"><?= __('admin/system.backup_compression_medium') ?></option>
                                            <option value="6" selected><?= __('admin/system.backup_compression_high') ?></option>
                                            <option value="9"><?= __('admin/system.backup_compression_max') ?></option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label for="backup_name" class="form-label small"><?= __('admin/system.backup_name_label') ?></label>
                                        <input type="text" name="backup_name" id="backup_name" class="form-control" placeholder="<?= __('admin/system.backup_name_placeholder') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label for="exclude_files" class="form-label small"><?= __('admin/system.backup_exclude_label') ?></label>
                                        <textarea name="exclude_files" id="exclude_files" class="form-control" rows="3" placeholder="<?= __('admin/system.backup_exclude_placeholder') ?>"></textarea>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-floppy-disk me-1"></i><?= __('admin/system.backup_btn_create') ?>
                                    </button>
                                </div>
                            </form>
                            <?php
                            echo admin_settings_section(__('admin/system.backup_create_title'), ob_get_clean(), ['icon' => 'fa-solid fa-circle-plus']);
                            ?>
                        </div>
                        <div class="col-lg-4">
                            <?php ob_start(); ?>
                            <div class="d-grid gap-2">
                                <a href="?page=backup&type=web" class="btn btn-outline-secondary btn-sm">
                                    <i class="fa-solid fa-folder me-1"></i><?= __('admin/system.backup_btn_files_only') ?>
                                </a>
                                <a href="?page=backup&type=sql" class="btn btn-outline-secondary btn-sm">
                                    <i class="fa-solid fa-database me-1"></i><?= __('admin/system.backup_btn_db_only') ?>
                                </a>
                            </div>
                            <?php
                            echo admin_settings_section(
                                __('admin/system.backup_quick_download'),
                                ob_get_clean(),
                                [
                                    'icon' => 'fa-solid fa-bolt',
                                    'description' => __('admin/system.backup_quick_download_help'),
                                ]
                            );
                            ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'schedule'): ?>
                <div class="admin-backup-board__pane">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <?php ob_start(); ?>
                            <form method="post">
                                <?= admin_csrf_field() ?>
                                <input type="hidden" name="action" value="schedule_backup">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="schedule_type" class="form-label small"><?= __('admin/system.backup_auto_type') ?></label>
                                        <select name="schedule_type" id="schedule_type" class="form-select">
                                            <option value="web" <?= App::getConfig('backup.auto.type', 'full') == 'web' ? 'selected' : '' ?>><?= __('admin/system.backup_type_web') ?></option>
                                            <option value="sql" <?= App::getConfig('backup.auto.type', 'full') == 'sql' ? 'selected' : '' ?>><?= __('admin/system.backup_type_sql') ?></option>
                                            <option value="full" <?= App::getConfig('backup.auto.type', 'full') == 'full' ? 'selected' : '' ?>><?= __('admin/system.backup_type_full') ?></option>
                                            <option value="config" <?= App::getConfig('backup.auto.type', 'full') == 'config' ? 'selected' : '' ?>><?= __('admin/system.backup_type_config') ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="schedule_frequency" class="form-label small"><?= __('admin/system.backup_auto_frequency') ?></label>
                                        <select name="schedule_frequency" id="schedule_frequency" class="form-select">
                                            <option value="daily" <?= App::getConfig('backup.auto.frequency', 'daily') == 'daily' ? 'selected' : '' ?>><?= __('admin/system.backup_auto_frequency_daily') ?></option>
                                            <option value="weekly" <?= App::getConfig('backup.auto.frequency', 'daily') == 'weekly' ? 'selected' : '' ?>><?= __('admin/system.backup_auto_frequency_weekly') ?></option>
                                            <option value="monthly" <?= App::getConfig('backup.auto.frequency', 'daily') == 'monthly' ? 'selected' : '' ?>><?= __('admin/system.backup_auto_frequency_monthly') ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="schedule_time" class="form-label small"><?= __('admin/system.backup_auto_time') ?></label>
                                        <input type="time" name="schedule_time" id="schedule_time" class="form-control" value="<?= App::getConfig('backup.auto.time', '02:00') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="schedule_retention" class="form-label small"><?= __('admin/system.backup_auto_retention') ?> (<?= __('admin/system.backup_auto_days') ?>)</label>
                                        <input type="number" name="schedule_retention" id="schedule_retention" class="form-control" value="<?= App::getConfig('backup.auto.retention', '30') ?>" min="1" max="365">
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <i class="fa-solid fa-calendar-days me-1"></i><?= __('admin/system.backup_auto_btn_schedule') ?>
                                    </button>
                                </div>
                            </form>
                            <?php
                            echo admin_settings_section(__('admin/system.backup_auto_title'), ob_get_clean(), ['icon' => 'fa-solid fa-clock']);
                            ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'list'): ?>
                <div class="admin-modules-table-wrap">
                    <div class="admin-modules-table__toolbar">
                        <div class="admin-modules-table__caption">
                            <span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--primary">
                                <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                            </span>
                            <span class="admin-modules-table__caption-text"><?= __('admin/system.backup_list_title') ?></span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="admin-modules-table__count"><?= count($backups) ?></span>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#cleanupModal">
                                    <i class="fa-solid fa-broom me-1"></i><?= __('admin/system.backup_btn_cleanup') ?>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteSelected()" id="deleteSelectedBtn" disabled>
                                    <i class="fa-solid fa-trash-can me-1"></i><?= __('admin/system.backup_btn_delete_selected') ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($backups)): ?>
                        <?= admin_settings_empty(__('admin/system.backup_empty_text'), 'fa-solid fa-box-archive') ?>
                    <?php else: ?>
                        <div class="table-responsive admin-modules-table-scroll">
                            <table id="backups-table" class="table admin-modules-table admin-backup-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()" aria-label="<?= __('admin/general.btn_delete') ?>"></th>
                                        <th scope="col"><?= __('admin/system.backup_table_filename') ?></th>
                                        <th scope="col"><?= __('admin/system.backup_table_type') ?></th>
                                        <th scope="col"><?= __('admin/system.backup_table_size') ?></th>
                                        <th scope="col"><?= __('admin/system.backup_table_date') ?></th>
                                        <th scope="col"><?= __('admin/system.backup_table_age') ?></th>
                                        <th scope="col"><?= __('admin/system.backup_table_actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr class="backup-row" data-type="<?= html_encode($backup['type']) ?>" data-age="<?= (int) $backup['age_days'] ?>" data-name="<?= html_encode(strtolower($backup['filename'])) ?>">
                                            <td><input type="checkbox" class="backup-checkbox" value="<?= html_encode($backup['filename']) ?>"></td>
                                            <td>
                                                <i class="fa-regular fa-file-zipper me-1" aria-hidden="true"></i>
                                                <strong><?= html_encode($backup['filename']) ?></strong>
                                            </td>
                                            <td><span class="badge bg-secondary admin-backup-type"><?= html_encode(strtoupper($backup['type'])) ?></span></td>
                                            <td><?= formatBytes($backup['size']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($backup['date'])) ?></td>
                                            <td>
                                                <?= __plural('admin/system.backup_age_days', $backup['age_days'], ['%count%' => $backup['age_days']]) ?>
                                                <?php if ($backup['age_days'] > 30): ?>
                                                    <span class="badge bg-warning ms-1"><?= __('admin/system.backup_table_old') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?page=backup&action=download&file=<?= urlencode($backup['filename']) ?>" class="btn btn-outline-secondary" title="<?= __('admin/system.backup_btn_download') ?>">
                                                        <i class="fa-solid fa-download"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-secondary" onclick="showRestoreModal(<?= json_encode($backup['filename']) ?>)" title="<?= __('admin/system.backup_btn_restore') ?>">
                                                        <i class="fa-solid fa-upload"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" onclick="deleteBackup(<?= json_encode($backup['filename']) ?>)" title="<?= __('admin/general.btn_delete') ?>">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="restoreModalLabel"><?= __('admin/system.backup_modal_restore_title') ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('messages/form.cancel') ?>"></button>
            </div>
            <form id="restoreForm" method="post">
                <?= admin_csrf_field() ?>
                <input type="hidden" name="action" value="restore_backup">
                <input type="hidden" name="backup_file" id="restoreFile">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="restore_type" class="form-label small"><?= __('admin/system.backup_modal_restore_type_label') ?></label>
                        <select name="restore_type" id="restore_type" class="form-select form-select-sm">
                            <option value="full"><?= __('admin/system.backup_modal_restore_type_full') ?></option>
                            <option value="files"><?= __('admin/system.backup_modal_restore_type_files') ?></option>
                            <option value="database"><?= __('admin/system.backup_modal_restore_type_database') ?></option>
                        </select>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <small><?= __('admin/system.backup_modal_restore_warning') ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= __('messages/form.cancel') ?></button>
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="fa-solid fa-upload me-1"></i><?= __('admin/system.backup_btn_restore') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="cleanupModal" tabindex="-1" aria-labelledby="cleanupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="cleanupModalLabel"><?= __('admin/system.backup_modal_cleanup_title') ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('messages/form.cancel') ?>"></button>
            </div>
            <form method="post">
                <?= admin_csrf_field() ?>
                <input type="hidden" name="action" value="cleanup_old">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="retention_days" class="form-label small"><?= __('admin/system.backup_modal_cleanup_label') ?></label>
                        <input type="number" name="retention_days" id="retention_days" class="form-control form-control-sm" value="30" min="1" max="365">
                    </div>
                    <div class="alert alert-info mb-0">
                        <small><?= __('admin/system.backup_modal_cleanup_info', ['%count%' => count($old_backups)]) ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= __('messages/form.cancel') ?></button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-broom me-1"></i><?= __('admin/system.backup_btn_cleanup_confirm') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteForm" method="post" class="d-none">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="delete_backup">
    <input type="hidden" name="backup_file" id="deleteFile">
</form>

<form id="deleteMultipleForm" method="post" class="d-none">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="delete_multiple">
    <div id="deleteFiles"></div>
</form>

<script>
const backupI18n = <?= json_encode([
    'confirmDelete' => __('admin/system.backup_js_confirm_delete'),
    'confirmDeleteMultiple' => __('admin/system.backup_js_confirm_delete_multiple'),
    'selectAtLeastOne' => __('admin/system.backup_js_select_at_least_one'),
    'downloadTitle' => __('admin/system.backup_btn_download'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

let selectedBackups = [];

function deleteBackup(filename) {
    if (confirm(backupI18n.confirmDelete)) {
        document.getElementById('deleteFile').value = filename;
        document.getElementById('deleteForm').submit();
    }
}

function showRestoreModal(filename) {
    document.getElementById('restoreFile').value = filename;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('restoreModal')).show();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    document.querySelectorAll('.backup-checkbox').forEach(checkbox => {
        checkbox.checked = selectAll.checked;
        checkbox.closest('tr')?.classList.toggle('selected', selectAll.checked);
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    selectedBackups = Array.from(document.querySelectorAll('.backup-checkbox:checked')).map(cb => cb.value);
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    if (deleteSelectedBtn) {
        deleteSelectedBtn.disabled = selectedBackups.length === 0;
    }
}

function deleteSelected() {
    if (selectedBackups.length === 0) {
        alert(backupI18n.selectAtLeastOne);
        return;
    }
    if (confirm(backupI18n.confirmDeleteMultiple.replace('%count%', selectedBackups.length))) {
        const container = document.getElementById('deleteFiles');
        container.innerHTML = '';
        selectedBackups.forEach(filename => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'files[]';
            input.value = filename;
            container.appendChild(input);
        });
        document.getElementById('deleteMultipleForm').submit();
    }
}

function generateBackupName() {
    const backupType = document.getElementById('backup_type').value;
    const backupNameInput = document.getElementById('backup_name');
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}_${hours}-${minutes}`;
    const typeNames = { web: 'files', sql: 'databases', full: 'full', config: 'config' };
    backupNameInput.value = `backup-${typeNames[backupType]}-${dateStr}`;
}

document.addEventListener('DOMContentLoaded', function() {
    const backupTypeSelect = document.getElementById('backup_type');
    if (backupTypeSelect) {
        backupTypeSelect.addEventListener('change', generateBackupName);
        generateBackupName();
    }

    document.querySelectorAll('.backup-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            this.closest('tr')?.classList.toggle('selected', this.checked);
            updateSelectedCount();
        });
    });

    document.querySelectorAll('.backup-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox' || e.target.closest('.btn-group')) {
                return;
            }
            this.classList.toggle('selected');
            const checkbox = this.querySelector('.backup-checkbox');
            if (checkbox) {
                checkbox.checked = this.classList.contains('selected');
                updateSelectedCount();
            }
        });

        row.addEventListener('dblclick', function(e) {
            if (e.target.type === 'checkbox' || e.target.closest('.btn-group')) {
                return;
            }
            this.querySelector('a[title="' + backupI18n.downloadTitle + '"]')?.click();
        });
    });

    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
    }

    updateSelectedCount();
});
</script>
