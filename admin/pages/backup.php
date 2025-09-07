<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.backup', true);

if (!class_exists('ZipArchive')) {
    die(__('admin/admin/system.backup_phpzip_required'));
}

// Configuration des sauvegardes
$backup_dir = ROOT_DIR . '/backups';
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0755, true)) {
        die('Impossible de créer le répertoire de sauvegarde : ' . $backup_dir);
    }
}

// Vérifier que le répertoire est accessible en écriture
if (!is_writable($backup_dir)) {
    die('Le répertoire de sauvegarde n\'est pas accessible en écriture : ' . $backup_dir);
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
            die('Fichier de sauvegarde introuvable : ' . htmlspecialchars($file));
        }
    }
}

// Fonctions de gestion des sauvegardes
function createBackup($type, $name = '', $compression = 6, $exclude = []) {
    $backup_dir = ROOT_DIR . '/backups';
    
    
    // S'assurer que le répertoire de sauvegarde existe
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0755, true)) {
            throw new Exception('Impossible de créer le répertoire de sauvegarde: ' . $backup_dir);
        }
    }
    
    // Vérifier que le répertoire est accessible en écriture
    if (!is_writable($backup_dir)) {
        throw new Exception('Le répertoire de sauvegarde n\'est pas accessible en écriture: ' . $backup_dir);
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
        throw new Exception('Impossible de créer le fichier de sauvegarde: ' . $filepath);
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
            throw new Exception('Le fichier de sauvegarde n\'a pas été créé: ' . $filepath);
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

// Traitement des actions
if ($_POST) {
    
    $action = App::POST('action');
    $message = '';
    $message_type = 'success';
    
    
    switch ($action) {
        case 'create_backup':
            $type = App::POST('backup_type');
            $name = App::POST('backup_name');
            $compression = (int)App::POST('compression_level', 6);
            $exclude = array_filter(explode("\n", App::POST('exclude_files', '')));
            
            
            try {
                $filename = createBackup($type, $name, $compression, $exclude);
                App::setNotice('Sauvegarde créée avec succès : ' . $filename, 'success');
            } catch (Exception $e) {
                App::setNotice('Erreur lors de la création de la sauvegarde : ' . $e->getMessage(), 'danger');
            }
            break;
            
        case 'schedule_backup':
            $type = App::POST('schedule_type');
            $frequency = App::POST('schedule_frequency');
            $time = App::POST('schedule_time');
            $retention = (int)App::POST('schedule_retention', 30);
            
            scheduleBackup($type, $frequency, $time, $retention);
            App::setNotice('Sauvegarde automatique programmée avec succès', 'success');
            break;
            
        case 'restore_backup':
            $filename = App::POST('backup_file');
            $restore_type = App::POST('restore_type');
            
            if (restoreBackup($filename, $restore_type)) {
                $message = 'Sauvegarde restaurée avec succès : ' . $filename;
            } else {
                $message = 'Erreur lors de la restauration de : ' . $filename;
                $message_type = 'danger';
            }
            break;
            
        case 'delete_backup':
            $filename = App::POST('backup_file');
            if (deleteBackup($filename)) {
                $message = 'Sauvegarde supprimée avec succès : ' . $filename;
            } else {
                $message = 'Erreur lors de la suppression de : ' . $filename;
                $message_type = 'danger';
            }
            break;
            
        case 'delete_multiple':
            $files = App::POST('files', []);
            $deleted = 0;
            foreach ($files as $file) {
                if (deleteBackup($file)) {
                    $deleted++;
                }
            }
            $message = $deleted . ' sauvegarde(s) supprimée(s) avec succès';
            break;
            
        case 'cleanup_old':
            $days = (int)App::POST('retention_days', 30);
            $deleted = cleanupOldBackups($days);
            $message = 'Nettoyage terminé : ' . $deleted . ' sauvegarde(s) supprimée(s)';
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
            App::setNotice($new_status ? 'Sauvegardes automatiques activées' : 'Sauvegardes automatiques désactivées', 'success');
            break;
            
        case 'test_auto_backup':
            try {
                $type = App::getConfig('backup.auto.type', 'full');
                $filename = createBackup($type, '', 6, []);
                App::setNotice('Test de sauvegarde automatique réussi : ' . $filename, 'success');
                App::logEvent(0, 'admin', 'Test de sauvegarde automatique exécuté: ' . $filename);
            } catch (Exception $e) {
                App::setNotice('Erreur lors du test de sauvegarde automatique : ' . $e->getMessage(), 'danger');
                App::logEvent(0, 'admin', 'Erreur test sauvegarde automatique: ' . $e->getMessage());
            }
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
                $message = 'Fichier de sauvegarde introuvable : ' . $filename;
                $message_type = 'danger';
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
?>

<div class="container-fluid">
    <div class="container" style="top: -30px !important;position: relative;">
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-<?= $_GET['type'] ?? 'success' ?> alert-dismissible fade show mb-4">
                <i class="fa fa-<?= ($_GET['type'] ?? 'success') === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($_GET['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Configuration automatique -->
        <?php if ($schedule_config): ?>
            <div class="alert alert-success mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small>
                            <i class="fa fa-check-circle me-1"></i> 
                            <strong><?= __('admin/system.backup_auto_enabled') ?></strong>
                        </small>
                        <br>
                        <small class="text-muted">
                            <?= __('admin/system.backup_auto_type') ?>: <?= ucfirst($schedule_config['type']) ?> | 
                            <?= __('admin/system.backup_auto_frequency') ?>: <?= ucfirst($schedule_config['frequency']) ?> | 
                            <?= __('admin/system.backup_auto_time') ?>: <?= $schedule_config['time'] ?> | 
                            <?= __('admin/system.backup_auto_retention') ?>: <?= $schedule_config['retention'] ?> <?= __('admin/system.backup_auto_days') ?>
                        </small>
                    </div>
                    <div>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_auto_backup">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fa fa-pause"></i> <?= __('admin/system.backup_auto_btn_disable') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="alert alert-warning mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small><strong><?= __('admin/system.backup_auto_next_run') ?></strong> <?= $schedule_config['next_run'] ? date('d/m/Y à H:i', $schedule_config['next_run']) : __('admin/system.backup_auto_not_scheduled') ?></small>
                        <?php if ($schedule_config['last_run']): ?>
                            <br><small><strong><?= __('admin/system.backup_auto_last_run') ?></strong> <?= date('d/m/Y H:i', $schedule_config['last_run']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <small>
                        <i class="fa fa-info-circle me-1"></i> 
                        <strong><?= __('admin/system.backup_auto_disabled') ?></strong>
                    </small>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="toggle_auto_backup">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-play"></i> <?= __('admin/system.backup_auto_btn_enable') ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section : Création et Configuration -->
        <div class="row mb-4">
            <!-- Création de sauvegarde -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header blue-grey darken-4 text-white">
                        <h6 class="mb-0"><i class="fa fa-plus-circle me-2"></i> <?= __('admin/system.backup_create_title') ?></h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" id="backupForm">
                            <input type="hidden" name="action" value="create_backup">
                            
                            <div class="mb-3">
                                <label for="backup_type" class="form-label small"><?= __('admin/system.backup_type_label') ?></label>
                                <select name="backup_type" id="backup_type" class="form-select" required>
                                    <option value="web"><?= __('admin/system.backup_type_web') ?></option>
                                    <option value="sql"><?= __('admin/system.backup_type_sql') ?></option>
                                    <option value="full"><?= __('admin/system.backup_type_full') ?></option>
                                    <option value="config"><?= __('admin/system.backup_type_config') ?></option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="backup_name" class="form-label small"><?= __('admin/system.backup_name_label') ?></label>
                                <input type="text" name="backup_name" id="backup_name" 
                                    class="form-control" 
                                    placeholder="<?= __('admin/system.backup_name_placeholder') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="compression_level" class="form-label small"><?= __('admin/system.backup_compression_label') ?></label>
                                <select name="compression_level" id="compression_level" class="form-select">
                                    <option value="0"><?= __('admin/system.backup_compression_none') ?></option>
                                    <option value="1"><?= __('admin/system.backup_compression_low') ?></option>
                                    <option value="3"><?= __('admin/system.backup_compression_medium') ?></option>
                                    <option value="6" selected><?= __('admin/system.backup_compression_high') ?></option>
                                    <option value="9"><?= __('admin/system.backup_compression_max') ?></option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="exclude_files" class="form-label small"><?= __('admin/system.backup_exclude_label') ?></label>
                                <textarea name="exclude_files" id="exclude_files" 
                                        class="form-control" rows="2" 
                                        placeholder="<?= __('admin/system.backup_exclude_placeholder') ?>"></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <button type="submit" class="btn btn-primary btn-sm" title="Créer une nouvelle sauvegarde">
                                    <i class="fa fa-save me-1"></i> <?= __('admin/system.backup_btn_create') ?>
                                </button>
                                <div class="btn-group">
                                    <a href="?page=backup&type=web" class="btn btn-outline-secondary btn-sm" title="Télécharger les fichiers du site">
                                        <i class="fa fa-folder me-1"></i> <?= __('admin/system.backup_btn_files_only') ?>
                                    </a>
                                    <a href="?page=backup&type=sql" class="btn btn-outline-secondary btn-sm" title="Télécharger la base de données">
                                        <i class="fa fa-database me-1"></i> <?= __('admin/system.backup_btn_db_only') ?>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header blue-grey darken-4 text-white">
                        <h6 class="mb-0"><i class="fa-solid fa-timer"></i> <?= __('admin/system.backup_auto_title') ?></h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="schedule_backup">
                            
                            <div class="mb-3">
                                <label for="schedule_type" class="form-label small"><?= __('admin/system.backup_auto_type') ?></label>
                                <select name="schedule_type" id="schedule_type" class="form-select">
                                    <option value="web" <?= App::getConfig('backup.auto.type', 'full') == 'web' ? 'selected' : '' ?>><?= __('admin/system.backup_type_web') ?></option>
                                    <option value="sql" <?= App::getConfig('backup.auto.type', 'full') == 'sql' ? 'selected' : '' ?>><?= __('admin/system.backup_type_sql') ?></option>
                                    <option value="full" <?= App::getConfig('backup.auto.type', 'full') == 'full' ? 'selected' : '' ?>><?= __('admin/system.backup_type_full') ?></option>
                                    <option value="config" <?= App::getConfig('backup.auto.type', 'full') == 'config' ? 'selected' : '' ?>><?= __('admin/system.backup_type_config') ?></option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="schedule_frequency" class="form-label small"><?= __('admin/system.backup_auto_frequency') ?></label>
                                <select name="schedule_frequency" id="schedule_frequency" class="form-select">
                                    <option value="daily" <?= App::getConfig('backup.auto.frequency', 'daily') == 'daily' ? 'selected' : '' ?>><?= __('admin/system.backup_auto_frequency_daily') ?></option>
                                    <option value="weekly" <?= App::getConfig('backup.auto.frequency', 'daily') == 'weekly' ? 'selected' : '' ?>><?= __('admin/system.backup_auto_frequency_weekly') ?></option>
                                    <option value="monthly" <?= App::getConfig('backup.auto.frequency', 'daily') == 'monthly' ? 'selected' : '' ?>><?= __('admin/system.backup_auto_frequency_monthly') ?></option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="schedule_time" class="form-label small"><?= __('admin/system.backup_auto_time') ?></label>
                                <input type="time" name="schedule_time" id="schedule_time" 
                                    class="form-control" value="<?= App::getConfig('backup.auto.time', '02:00') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="schedule_retention" class="form-label small"><?= __('admin/system.backup_auto_retention') ?> (<?= __('admin/system.backup_auto_days') ?>)</label>
                                <input type="number" name="schedule_retention" id="schedule_retention" 
                                    class="form-control" value="<?= App::getConfig('backup.auto.retention', '30') ?>" min="1" max="365">
                            </div>
                            
                            <button type="submit" class="btn btn-secondary btn-sm mt-2" title="Programmer les sauvegardes automatiques">
                                <i class="fa fa-calendar me-1"></i> <?= __('admin/system.backup_auto_btn_schedule') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section : Liste des sauvegardes -->
        <div class="mb-4">
            <div class="card">
                <div class="card-header blue-grey darken-4 text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fa fa-archive me-2"></i> <?= __('admin/system.backup_list_title') ?></h6>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#cleanupModal" title="Nettoyer les anciennes sauvegardes">
                                <i class="fa fa-broom me-1"></i> <?= __('admin/system.backup_btn_cleanup') ?>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteSelected()" id="deleteSelectedBtn" disabled title="Supprimer les sauvegardes sélectionnées">
                                <i class="fa fa-trash me-1"></i> <?= __('admin/system.backup_btn_delete_selected') ?>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($backups)): ?>
                        <div class="text-center py-5">
                            <i class="fa fa-archive fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted"><?= __('admin/system.backup_empty_title') ?></h6>
                            <p class="text-muted"><?= __('admin/system.backup_empty_text') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="backups-table" class="table table-hover table-sm my-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        </th>
                                        <th><?= __('admin/system.backup_table_filename') ?></th>
                                        <th><?= __('admin/system.backup_table_type') ?></th>
                                        <th><?= __('admin/system.backup_table_size') ?></th>
                                        <th><?= __('admin/system.backup_table_date') ?></th>
                                        <th><?= __('admin/system.backup_table_age') ?></th>
                                        <th><?= __('admin/system.backup_table_actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr class="backup-row" data-type="<?= $backup['type'] ?>" data-age="<?= $backup['age_days'] ?>" data-name="<?= strtolower($backup['filename']) ?>">
                                            <td>
                                                <input type="checkbox" class="backup-checkbox" value="<?= $backup['filename'] ?>">
                                            </td>
                                            <td>
                                                <i class="fa-regular fa-file-zipper"></i>
                                                <strong><?= htmlspecialchars($backup['filename']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= strtoupper($backup['type']) ?></span>
                                            </td>
                                            <td><?= formatBytes($backup['size']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($backup['date'])) ?></td>
                                            <td>
                                                <?= $backup['age_days'] ?> jour<?= $backup['age_days'] > 1 ? 's' : '' ?>
                                                <?php if ($backup['age_days'] > 30): ?>
                                                    <span class="badge bg-warning ms-1"><?= __('admin/system.backup_table_old') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?page=backup&action=download&file=<?= urlencode($backup['filename']) ?>" 
                                                    class="btn btn-outline-secondary" title="Télécharger">
                                                        <i class="fa fa-download"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-secondary" 
                                                            onclick="showRestoreModal('<?= $backup['filename'] ?>')" 
                                                            title="Restaurer">
                                                        <i class="fa fa-upload"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteBackup('<?= $backup['filename'] ?>')" 
                                                            title="Supprimer">
                                                        <i class="fa fa-trash"></i>
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
                
                <!-- Footer avec statistiques -->
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <strong><?= count($backups) ?></strong> Sauvegardes
                        </small>
                        <small class="text-muted">
                            <strong><?= formatBytes($total_size) ?></strong> Utilisé
                        </small>
                        <small class="text-muted">
                            <strong><?= formatBytes($free_space) ?></strong> Libre
                        </small>
                        <small class="text-muted">
                            <strong><?= $last_backup ? date('d/m', strtotime($last_backup)) : '-' ?></strong> Dernière
                        </small>
                    </div></div>
        </div></div>

<!-- Modales -->
<!-- Modal de restauration -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Restaurer la sauvegarde</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <span>&times;</span>
                </button>
            </div>
            <form id="restoreForm" method="post">
                <input type="hidden" name="action" value="restore_backup">
                <input type="hidden" name="backup_file" id="restoreFile">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="restore_type" class="form-label small">Type de restauration</label>
                        <select name="restore_type" id="restore_type" class="form-select form-select-sm">
                            <option value="full">Complète (fichiers + base)</option>
                            <option value="files">Fichiers uniquement</option>
                            <option value="database">Base de données uniquement</option>
                        </select>
                    </div>
                    <div class="alert alert-warning">
                        <small><strong>Attention :</strong> Cette action remplacera les données existantes.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="fa fa-upload me-1"></i> Restaurer
                    </button>
                </div>
            </form>
        </div></div>

<!-- Modal de nettoyage -->
<div class="modal fade" id="cleanupModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Nettoyer les anciennes sauvegardes</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <span>&times;</span>
                </button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="cleanup_old">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="retention_days" class="form-label small">Supprimer les sauvegardes plus anciennes que (jours)</label>
                        <input type="number" name="retention_days" id="retention_days" 
                               class="form-control form-control-sm" value="30" min="1" max="365">
                    </div>
                    <div class="alert alert-info">
                        <small><strong>Info :</strong> <?= count($old_backups) ?> sauvegarde(s) de plus de 30 jours trouvée(s).</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fa fa-broom me-1"></i> Nettoyer
                    </button>
                </div>
            </form>
        </div></div>

<!-- Formulaires cachés -->
<form id="deleteForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete_backup">
    <input type="hidden" name="backup_file" id="deleteFile">
</form>

<form id="deleteMultipleForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete_multiple">
    <div id="deleteFiles"></div>
</form>

<script>
// Variables globales
let selectedBackups = [];

// Fonctions de gestion des sauvegardes
function deleteBackup(filename) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette sauvegarde ?\n\nCette action est irréversible.')) {
        document.getElementById('deleteFile').value = filename;
        document.getElementById('deleteForm').submit();
    }
}

function showRestoreModal(filename) {
    document.getElementById('restoreFile').value = filename;
    $('#restoreModal').modal('show');
}

// Gestion de la sélection
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.backup-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
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
        alert('Veuillez sélectionner au moins une sauvegarde à supprimer.');
        return;
    }
    if (confirm(`Êtes-vous sûr de vouloir supprimer les ${selectedBackups.length} sauvegardes sélectionnées ?\n\nCette action est irréversible.`)) {
        const form = document.getElementById('deleteMultipleForm');
        const container = document.getElementById('deleteFiles');
        container.innerHTML = '';
        
        selectedBackups.forEach(filename => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'files[]';
            input.value = filename;
            container.appendChild(input);
        });
        
        form.submit();
    }
}

// Génération automatique des noms de sauvegarde
function generateBackupName() {
    const backupType = document.getElementById('backup_type').value;
    const backupNameInput = document.getElementById('backup_name');
    const now = new Date();
    
    // Format: YYYY-MM-DD_HH-MM
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}_${hours}-${minutes}`;
    
    const typeNames = {
        'web': 'files',
        'sql': 'databases',
        'full': 'full',
        'config': 'config'
    };
    
    const generatedName = `backup-${typeNames[backupType]}-${dateStr}`;
    backupNameInput.value = generatedName;
}

// Événements
document.addEventListener('DOMContentLoaded', function() {
    // Génération automatique des noms
    const backupTypeSelect = document.getElementById('backup_type');
    if (backupTypeSelect) {
        backupTypeSelect.addEventListener('change', generateBackupName);
        generateBackupName();
    }

    // Sélection
    document.querySelectorAll('.backup-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    updateSelectedCount();
    
    // Gestion des lignes du tableau
    document.querySelectorAll('.backup-row').forEach(row => {
        // Clic sur la ligne (sauf sur les boutons et checkboxes)
        row.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox' || e.target.closest('.btn-group')) {
                return; // Ne pas sélectionner si on clique sur checkbox ou bouton
            }
            
            // Toggle de la sélection
            this.classList.toggle('selected');
            
            // Cocher/décocher la checkbox
            const checkbox = this.querySelector('.backup-checkbox');
            if (checkbox) {
                checkbox.checked = this.classList.contains('selected');
                updateSelectedCount();
            }
        });
        
        // Double-clic pour télécharger
        row.addEventListener('dblclick', function(e) {
            if (e.target.type === 'checkbox' || e.target.closest('.btn-group')) {
                return;
            }
            
            const downloadBtn = this.querySelector('a[title="Télécharger"]');
            if (downloadBtn) {
                downloadBtn.click();
            }
        });
    });
    
    // Amélioration des checkboxes
    document.querySelectorAll('.backup-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
            updateSelectedCount();
        });
    });
    
    // Amélioration du "Sélectionner tout"
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.backup-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
                const row = checkbox.closest('tr');
                if (this.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
            updateSelectedCount();
        });
    }
});
</script>
