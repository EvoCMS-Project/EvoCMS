<?php
/*
 * Evo-CMS Installer
 */
if (!version_compare(PHP_VERSION, '7.1.0', '>=')) {
	die('EVO-CMS requires PHP 7.1 or greater. Installed: ' . PHP_VERSION);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
date_default_timezone_set('UTC');

require_once '../includes/definitions.php';
require_once '../includes/Database/database.php';
require_once '../includes/Evo/Lang.php';
require_once '../includes/Evo/Translator.php';
require_once '../includes/functions.php';
require_once '../includes/app.php';

function post_e($key, $default = null) {
	if (isset($_POST[$key])) {
		return htmlentities($_POST[$key]);
	}
	return $default;
}

Evo\Lang::setTranslator(
	new Evo\Translator(post_e('language', 'french'), ['english'], ROOT_DIR . '/includes/languages', 'install')
);

const STEP_LANGUAGE = 0;
const STEP_SYSCHECK = 1;
const STEP_DATABASE = 2;
const STEP_CONFIG   = 3;
const STEP_INSTALL  = 4;
const STEP_CLEANUP  = 5;
const STEP_ABORT    = -1;

$steps = [
	STEP_LANGUAGE => __('steps.language'),
	STEP_SYSCHECK => __('steps.checks'),
	STEP_DATABASE => __('steps.database'),
	STEP_CONFIG   => __('steps.config'),
	STEP_INSTALL  => __('steps.install'),
	STEP_INSTALL  => __('steps.finished'),
];

$next_step = $cur_step = isset($_POST['step']) ? (int)$_POST['step'] : 0;
$from_step = isset($_POST['from_step']) ? (int)$_POST['from_step'] : 0;
$payload = isset($_POST['payload']) ? $_POST['payload'] : '';
$warning = $failed = '';

$available_drivers = Database::AvailableDrivers();
$db_types = array_intersect_key(['sqlite' => 'SQLite3', 'mysql' => 'MySQL'], array_flip($available_drivers));

// Fallback si aucun driver n'est détecté
if (empty($db_types)) {
    $db_types = ['sqlite' => 'SQLite3'];
}
$locales = Evo\Lang::getLocales(true, true);

if (file_exists('../config.php') && $cur_step != STEP_CLEANUP) {
	$warning = __('already_installed');
	$hide_nav = true;
	$cur_step = -1;
}

try {
switch($cur_step) {
	case STEP_LANGUAGE:
		$next_step = STEP_SYSCHECK;
		break;

	case STEP_SYSCHECK:
		$checks[] = [__('checks.min_php', ['%version%' => 7.1]), $ok[] = version_compare(PHP_VERSION, '7.1.0', '>=')];
		$checks[] = [__('checks.writable_root'), $ok[] = is_writable('../')];
		$checks[] = [__('checks.writable_upload'), $ok[] = is_writable('../upload/')];
		$checks[] = [__('checks.pdo_available'), $ok[] = !empty($db_types)];
		$checks[] = [__('checks.sessions_available'), $ok[] = session_start()];

		/* Le cms peut fonctionner de façon limitée sans ces conditions: */
		$checks[] = [__('checks.ext_gd'), function_exists('imagecreatetruecolor')];
		$checks[] = [__('checks.ext_zip'), class_exists('ZipArchive')];

		// Toujours afficher l'étape, ne pas passer automatiquement
		$hide_nav = in_array(false, $ok);
		$next_step = STEP_DATABASE;
		break;

	case STEP_DATABASE:
		
		// Forcer une valeur par défaut si db_type n'est pas défini
		if (!isset($_POST['db_type']) || empty($_POST['db_type'])) {
			if (isset($_POST['db_type_backup']) && !empty($_POST['db_type_backup'])) {
				$_POST['db_type'] = $_POST['db_type_backup'];
			} else {
				$_POST['db_type'] = 'sqlite';
			}
		}
		
		if (!isset($db_types[$_POST['db_type']])) {
			$warning = "Type de base de données invalide: " . $_POST['db_type'];
			break;
		}
		
		// Validation des champs requis
		$db_type = strtolower(trim($_POST['db_type']));
		if ($db_type === 'mysql') {
			if (empty($_POST['db_host'])) {
				$warning = "L'hôte MySQL est requis";
				break;
			}
			if (empty($_POST['db_user'])) {
				$warning = "L'utilisateur MySQL est requis";
				break;
			}
			if (empty($_POST['db_name'])) {
				$warning = "Le nom de la base de données MySQL est requis";
				break;
			}
		} else if ($db_type === 'sqlite') {
			if (empty($_POST['db_name'])) {
				$warning = "Le nom de la base de données SQLite est requis (valeur reçue: '" . (isset($_POST['db_name']) ? $_POST['db_name'] : 'NOT SET') . "')";
				break;
			}
		}

		$payload = [$_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name'], $_POST['db_prefix'], $_POST['db_type']];

		try {
			require '../includes/Database/db.'.strtolower($_POST['db_type']).'.php';
			$db_type = strtolower(trim($_POST['db_type']));
			
			if (empty($db_type)) {
				throw new Exception("Type de base de données non spécifié");
			}
			
			$db_file = '../includes/Database/db.' . $db_type . '.php';
			
			if (!file_exists($db_file)) {
				throw new Exception("Fichier de base de données non trouvé: " . $db_file);
			}
			
			require $db_file;
			
			Db::Connect($_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name'], $_POST['db_prefix']);

			if (Db::TableExists('users')) {
				$warning = __('database.not_empty');
			}
			$next_step = STEP_CONFIG;
		} catch (Exception $e) {
			$warning = "Erreur de connexion à la base de données: " . $e->getMessage();
		}
		break;

	case STEP_CONFIG:
		
		if (isset($_POST['email'], $_POST['admin'], $_POST['admin_pass'], $_POST['url'], $_POST['name'], $_POST['payload'])) {
			try {
				if (!preg_match('#https?://.+#', $_POST['url']))
					$warning .= __('config.bad_url') . '<br>';
				if (!preg_match('#^.+@.+\..+$#', $_POST['email']))
					$warning .= __('config.bad_email') . '<br>';
				if (empty($_POST['admin']))
					$warning .= __('config.bad_username') . '<br>';
				if (empty($_POST['admin_pass']) || empty($_POST['admin_pass_confirm']))
					$warning .= __('config.bad_password1') . '<br>';
				elseif ($_POST['admin_pass_confirm'] !== $_POST['admin_pass'])
					$warning .= __('config.bad_password2') . '<br>';

				if ($warning) break;
			} catch (Exception $e) {
				$warning = "Erreur de validation: " . $e->getMessage();
				break;
			}

			$db = unserialize(base64_decode($_POST['payload']));
			$_POST['url'] = trim($_POST['url'], '/');
			try {
				require '../includes/Database/db.'.strtolower($db[5]).'.php';

				Db::Connect($db[0], $db[1], $db[2], $db[3], $db[4]);

				$cur_step = STEP_INSTALL;
				$hide_nav = true;

				$db_version = 1;

				Db::CreateTable('banlist', [
								'id' 				=> 'increment',
								'type' 				=> 'string|16',
								'rule' 				=> 'string|128',
								'reason' 			=> 'string',
								'created'			=> 'integer',
								'expires'			=> ['integer', 0],
				], false, true);
				Db::AddIndex('banlist', 'index', ['type', 'rule']);
				Db::AddIndex('banlist', 'index', ['expires']);



				Db::CreateTable('comments', [
								'id' 				=> 'increment',
								'page_id' 			=> 'integer',
								'user_id' 			=> 'integer',
								'message' 			=> 'text',
								'posted' 			=> 'integer',
								'poster_ip' 		=> 'string',
								'poster_name' 		=> ['string', null],
								'poster_email' 		=> ['string', null],
								'state' 			=> ['integer', 0],
				], false, true);



				Db::CreateTable('files', [
								'id' 				=> 'increment',
								'web_id'			=> 'string|8',
								'name' 				=> 'string|128',
								'caption' 			=> 'string',
								'description'       => ['text', null],
								'path' 				=> 'string|191',
								'thumbs' 			=> ['text', null],
								'type' 				=> 'string',
								'mime_type' 		=> 'string',
								'size' 				=> 'integer',
								'md5' 				=> 'string',
								'poster' 			=> 'integer',
								'posted' 			=> 'integer',
								'origin' 			=> ['string', null],
								'hits' 				=> ['integer', 0],
				], false, true);
				Db::AddIndex('files', 'index', ['web_id']);
				Db::AddIndex('files', 'index', ['path']);



				Db::CreateTable('files_rel', [
								'file_id' 			=> 'integer',
								'rel_id' 			=> 'integer',
								'rel_type' 			=> 'string|128',
				], false, true);
				Db::AddIndex('files_rel', 'unique', ['file_id', 'rel_id', 'rel_type']);



				Db::CreateTable('forums', [
								'id' 				=> 'increment',
								'cat' 				=> 'integer',
								'priority' 			=> 'integer',
								'name' 				=> 'string',
								'description' 		=> 'string',
								'icon' 				=> 'string',
								'num_topics' 		=> ['integer', 0],
								'num_posts' 		=> ['integer', 0],
								'last_topic_id' 	=> ['integer', null],
								'redirect' 			=> ['string', null],
				], false, true);



				Db::CreateTable('forums_cat', [
								'id' 				=> 'increment',
								'name' 				=> 'string',
								'priority' 			=> 'integer',
				], false, true);



				Db::CreateTable('forums_posts', [
								'id' 				=> 'increment',
								'topic_id' 			=> 'integer',
								'poster_id' 		=> 'integer',
								'poster' 			=> 'string',
								'poster_ip' 		=> 'string',
								'message' 			=> 'longtext',
								'posted' 			=> 'integer',
								'edited' 			=> ['integer', 0],
								'user_agent' 		=> 'string',
								'attached_files'	=> ['text', null],
				], false, true);
				Db::AddIndex('forums_posts', 'index', ['topic_id']);



				Db::CreateTable('forums_topics', [
								'id' 				=> 'increment',
								'forum_id' 			=> 'integer',
								'poster_id' 		=> 'integer',
								'poster' 			=> 'string',
								'subject' 			=> 'string',
								'first_post_id' 	=> 'integer',
								'first_post' 		=> 'integer',
								'last_post_id' 		=> 'integer',
								'last_post' 		=> 'integer',
								'last_poster' 		=> 'string',
								'last_poster_id'	=> 'integer',
								'num_posts' 		=> ['integer', 0],
								'num_views' 		=> ['integer', 0],
								'sticky' 			=> ['integer', 0],
								'closed' 			=> ['integer', 0],
								'redirect' 			=> ['string', null],
				], false, true);
				Db::AddIndex('forums_topics', 'index', ['forum_id']);



				Db::CreateTable('friends', [
								'id' 				=> 'increment',
								'u_id' 				=> 'integer',
								'f_id' 				=> 'integer',
								'state' 			=> ['integer', 0]
				], false, true);
				Db::AddIndex('friends', 'unique', ['u_id', 'f_id']);



				Db::CreateTable('groups', [
								'id' 				=> 'increment',
								'name' 				=> 'string',
								'role'	 			=> ['string', null],
								'internal'	 		=> ['string', null],
								'color' 			=> 'string',
								'priority' 			=> ['integer', 100]
				], false, true);



				Db::CreateTable('history', [
								'id' 				=> 'increment',
								'e_uid' 			=> 'integer',
								'a_uid' 			=> 'integer',
								'ip' 				=> 'string',
								'type' 				=> 'string',
								'timestamp'		 	=> 'integer',
								'event' 			=> 'text',
				], false, true);



				Db::CreateTable('mailbox', [
								'id' 				=> 'increment',
								'reply' 			=> 'integer',
								's_id' 				=> 'integer',
								'r_id' 				=> 'integer',
								'type' 				=> 'tinyint',
								'sujet' 			=> 'string',
								'message' 			=> 'text',
								'posted' 			=> 'integer',
								'viewed' 			=> ['integer', null],
								'deleted_rcv' 		=> ['integer', 0],
								'deleted_snd' 		=> ['integer', 0],
				], false, true);



				Db::CreateTable('menu', [
								'id' 				=> 'increment',
								'parent' 			=> 'integer',
								'priority' 			=> 'integer',
								'name' 				=> 'string',
								'icon' 				=> 'string',
								'link' 				=> 'string',
								'visibility'		=> ['integer', 0],
				], false, true);



				Db::CreateTable('newsletter', [
								'id' 				=> 'increment',
								'author' 			=> 'integer',
								'groups' 			=> 'string',
								'subject' 			=> 'string',
								'message' 			=> 'text',
								'date_sent'			=> 'integer',
								'mail_sent'			=> ['integer', 0],
								'mail_failed'		=> ['integer', 0],
				], false, true);



				Db::CreateTable('pages', [
								'page_id' 			=> 'increment',
								'type' 				=> 'string|64',
								'slug' 				=> 'string|128',
								'image' 			=> 'string',
								'redirect'			=> ['string', ''],
								'category'			=> ['string|128', ''],
								'pub_date' 			=> 'integer',
								'pub_rev' 			=> 'integer',
								'display_toc' 		=> 'tinyint',
								'allow_comments' 	=> 'tinyint',
								'revisions' 		=> 'integer',
								'comments' 			=> ['integer', 0],
								'views' 			=> ['integer', 0],
								'sticky' 			=> ['integer', 0],
				], false, true);
				Db::AddIndex('pages', 'index', ['type']);
				Db::AddIndex('pages', 'index', ['slug']);
				Db::AddIndex('pages', 'index', ['category']);
				Db::AddIndex('pages', 'index', ['sticky']);



				Db::CreateTable('pages_revs', [
								'id' 				=> 'increment',
								'page_id' 			=> 'integer',
								'revision' 			=> 'integer',
								'posted' 			=> 'integer',
								'author' 			=> 'integer',
								'status' 			=> 'string|64',
								'title' 			=> 'string',
								'slug' 				=> 'string|128',
								'content'	 		=> 'text',
								'format'			=> ['string|64', 'html'],
								'extra'				=> ['text', null],
								'attached_files'	=> ['text', null],
				], false, true);
				Db::AddIndex('pages_revs', 'index', ['page_id', 'revision']);
				Db::AddIndex('pages_revs', 'index', ['slug']);



				Db::CreateTable('permissions', [
								'name' 				=> 'string|128',
								'group_id' 			=> 'integer',
								'related_id' 		=> ['integer', -1],
								'value' 			=> 'integer',
				], false, true);
				Db::AddIndex('permissions', 'primary key', ['name', 'group_id', 'related_id']);
				Db::AddIndex('permissions', 'index', ['group_id']);


				Db::CreateTable('reports', [
								'id' 				=> 'increment',
								'user_id' 			=> 'integer',
								'type' 				=> 'string',
								'rel_id' 			=> 'integer',
								'reason' 			=> 'text',
								'reported' 			=> 'integer',
								'deleted' 			=> ['integer', 0],
								'user_ip' 			=> 'string',
				], false, true);



				Db::CreateTable('servers', [
								'id' 				=> 'increment',
								'type' 				=> 'string|32',
								'name' 				=> 'string|96',
								'address' 		    => 'string|255',
								'password' 		    => 'string|255',
								'status_code' 		=> ['integer', 0],
								'status_data' 		=> ['string', null],
								'status_time' 		=> ['integer', 0],
								'poll_interval' 	=> ['integer', 0],
								'additional_settings'=>'text',
				], false, true);



				Db::CreateTable('settings', [
								'name' 				=> ['string|128', null, Db::PRIMARY],
								'value' 			=> ['text', null],
								'default_value'		=> ['text', null],
				], false, true);



				Db::CreateTable('subscriptions', [
								'user_id' 			=> 'integer',
								'type' 				=> 'string|128',
								'rel_id' 			=> 'integer',
								'email' 			=> 'string',
				], false, true);
				Db::AddIndex('subscriptions', 'primary key', ['user_id', 'type', 'rel_id']);



				Db::CreateTable('users', [
								'id' 				=> 'increment',
								'group_id' 			=> 'integer',
								'username' 			=> 'string|128',
								'email' 			=> 'string|128',
								'password' 			=> 'string',
								'login_type'			=> ['string', 'normal'],
								'locked' 			=> ['integer', 0],
								'newsletter' 			=> ['integer', 1],
								'discuss' 			=> ['integer', 0],
								'registered' 			=> 'integer',
								'activity' 			=> ['integer', 0],
								'timezone' 			=> ['string', null],
								'login_key' 			=> ['string', null],
								'reset_key' 			=> ['string', null],
								'raf' 				=> ['string', null],
								'raf_token' 			=> ['string', null],
								'registration_ip'		=> ['string', null],
								'last_ip'			=> ['string', null],
								'last_user_agent'		=> ['string', null],
								'country' 			=> ['string', null],
								'avatar' 			=> ['string', null],
								'ingame' 			=> ['string', null],
								'website' 			=> ['string', null],
								'social' 			=> ['text'  , null],
								'about' 			=> ['text'  , null],
								'extra' 			=> ['text'  , null],
								'num_posts' 			=> ['integer', 0],
								'num_thanks'			=> ['integer', 0],
								'profile_views'			=> ['integer', 0],
				], false, true);
				Db::AddIndex('users', 'unique', ['username']);
				Db::AddIndex('users', 'unique', ['email']);

				// ========================================
				// SYSTÈME DE SAUVEGARDES EVO-CMS
				// ========================================
				// Cette table gère toutes les sauvegardes du système :
				// - Sauvegardes manuelles créées via l'interface admin
				// - Sauvegardes automatiques programmées
				// - Métadonnées complètes pour chaque sauvegarde
				// - Suivi des utilisateurs et des dates
				// - Vérification d'intégrité via checksums
				// ========================================
				Db::CreateTable('backups', [
					'id' 				=> 'increment',					// ID unique auto-incrémenté
					'filename' 			=> 'string|255',				// Nom du fichier de sauvegarde
					'type' 				=> 'string|32',					// Type: web, sql, full, config
					'size' 				=> 'integer',					// Taille du fichier en octets
					'compression_level'		=> ['integer', 6],			// Niveau de compression (0-9)
					'exclude_files'		=> ['text', null],				// Fichiers exclus (séparés par \n)
					'created_by'		=> 'integer',					// ID de l'utilisateur créateur
					'created_at'		=> 'integer',					// Timestamp de création
					'status'			=> ['string|32', 'completed'],	// Statut: completed, failed, in_progress
					'description'		=> ['text', null],				// Description optionnelle
					'file_path'			=> 'string|255',				// Chemin complet du fichier
					'checksum'			=> ['string|64', null],			// Checksum MD5 du fichier
				], false, true);
				
				// Index pour optimiser les requêtes
				Db::AddIndex('backups', 'index', ['type']);			// Recherche par type
				Db::AddIndex('backups', 'index', ['created_at']);		// Tri par date
				Db::AddIndex('backups', 'index', ['created_by']);		// Recherche par utilisateur
				Db::AddIndex('backups', 'index', ['status']);			// Filtrage par statut
				Db::AddIndex('backups', 'index', ['filename']);		// Recherche par nom de fichier

				Db::Insert('settings', [
					['name' => 'name', 'value' => post_e('name', '')],
					['name' => 'email', 'value' => post_e('email', '')],
					['name' => 'url', 'value' => post_e('url', '/')],
					['name' => 'language', 'value' => post_e('language', 'french')],
					['name' => 'cookie.name', 'value' => 'evo_'.random_hash(8)],
					['name' => 'database.version', 'value' => DATABASE_VERSION],
					['name' => 'install.version', 'value' => EVO_VERSION],
					['name' => 'install.time', 'value' => time()],
					
					// ========================================
					// PARAMÈTRES DE SAUVEGARDES AUTOMATIQUES
					// ========================================
					// Configuration du système de sauvegardes automatiques
					// Ces paramètres permettent de programmer des sauvegardes
					// récurrentes sans intervention manuelle
					// ========================================
					['name' => 'backup.auto.enabled', 'value' => '0'],					// Activation (0=désactivé, 1=activé)
					['name' => 'backup.auto.type', 'value' => 'full'],					// Type: web, sql, full, config
					['name' => 'backup.auto.frequency', 'value' => 'daily'],				// Fréquence: daily, weekly, monthly
					['name' => 'backup.auto.time', 'value' => '02:00'],					// Heure d'exécution (HH:MM)
					['name' => 'backup.auto.retention', 'value' => '30'],				// Rétention en jours
					['name' => 'backup.auto.compression', 'value' => '6'],				// Niveau compression (0-9)
					['name' => 'backup.auto.exclude', 'value' => '*.log,cache/*,temp/*,backups/*'],	// Fichiers à exclure
					['name' => 'backup.auto.last_run', 'value' => '0'],					// Timestamp dernière exécution
					['name' => 'backup.auto.next_run', 'value' => '0'],					// Timestamp prochaine exécution
					['name' => 'backup.auto.max_size', 'value' => '1073741824'],			// Taille max (1GB en octets)
					['name' => 'backup.auto.email_notifications', 'value' => '1'],		// Notifications email (0=non, 1=oui)
					['name' => 'backup.auto.email_on_success', 'value' => '0'],			// Email en cas de succès
					['name' => 'backup.auto.email_on_failure', 'value' => '1'],			// Email en cas d'échec
				]);

				Db::Insert('menu', [
					['parent' => 0, 'priority' => 0, 'name' => 'Navigation', 'icon' => '', 'link' => ''],
					['parent' => 1, 'priority' => 0, 'name' => 'Accueil', 'icon' => 'fas fa-home', 'link' => 'index'],
					['parent' => 1, 'priority' => 0, 'name' => 'Forums', 'icon' => 'fas fa-list-ul', 'link' => 'forums'],
					['parent' => 1, 'priority' => 0, 'name' => 'Membres', 'icon' => 'fas fa-users', 'link' => 'users'],
					['parent' => 1, 'priority' => 0, 'name' => 'Téléchargements', 'icon' => 'fas fa-download', 'link' => 'downloads'],
					['parent' => 1, 'priority' => 0, 'name' => 'Contact', 'icon' => 'fas fa-envelope', 'link' => 'contact'],
				]);

				Db::Insert('groups', [
					['id' => 1, 'name' => 'Administrateur', 'internal' => 'Administrator', 'role' => 'administrator', 'color' => '3', 'priority' => 1],
					['id' => 2, 'name' => 'Modérateur', 'internal' => 'Moderator', 'role' => 'moderator', 'color' => '2', 'priority' => 2],
					['id' => 3, 'name' => 'Membre', 'internal' => 'Member', 'role' => 'member', 'color' => '1', 'priority' => 3],
					['id' => 4, 'name' => 'Invité', 'internal' => 'Guest', 'role' => 'guest', 'color' => '0', 'priority' => 4],
				]);

				$groups = [
					'admin' => ['id' => 1],
					'mod'   => ['id' => 2],
					'user'  => ['id' => 3, 'ignore' => ['user.staff']],
					'guest' => ['id' => 4, 'force' => ['comment_send']],
				];

				// Définir les permissions par défaut si elles n'existent pas
				if (!isset($_permissions)) {
					$_permissions = [];
				}
				
				foreach($_permissions as $group => $sections) {
					foreach(array_filter($sections, 'is_array') as $section) {
						foreach(array_keys($section) as $priv) {
							$key = $group.'.'.$priv;
							foreach($groups as $g) {
								if ($g['id'] <= $groups[$group]['id'] && (empty($g['ignore']) || !in_array($key, $g['ignore']))) {
									$inserts[] = ['name' => $key, 'group_id' => $g['id'], 'value' => 1];
								}
							}
						}
					}
				}

				foreach($groups as $g) {
					if (!empty($g['force'])) {
						foreach($g['force'] as $perm) {
							$inserts[] = ['name' => $perm, 'group_id' => $g['id'], 'value' => 1];
						}
					}
				}

				if ($inserts) {
					Db::Insert('permissions', $inserts);
				}

				Db::Insert('users', [
					[
						'id' => 1,
						'username' => $_POST['admin'],
						'group_id' => 1,
						'password' => password_hash($_POST['admin_pass'], PASSWORD_DEFAULT),
						'email' => $_POST['email'],
						'locked' => 0,
						'registered' => time()
					],
					[
						'id' => 0,
						'username' => 'guest',
						'group_id' => 4,
						'password' => '',
						'email' => '',
						'locked' => 1,
						'registered' => time()
					],
				]);
				Db::Update('users', ['id' => 0], ['username' => 'guest']); // For MySQL


				foreach(glob('updates/*.php') as $migration) { // Applying incremental updates
					if ((include $migration) === false) {
						throw new exception('Migration ' . $migration . ' failed');
					}
				}

				$db = array_map('addslashes', $db);

				$config = "<?php\n".
							"\$db_host = '{$db[0]}'; \n".
							"\$db_user = '{$db[1]}'; \n".
							"\$db_pass = '{$db[2]}'; \n".
							"\$db_name = '{$db[3]}'; \n".
							"\$db_prefix = '{$db[4]}'; \n".
							"\$db_type = '{$db[5]}'; \n".
							"\n".
							"// Debug mode active les options de dévelopement.\n".
							"\$debug_mode = false; \n".
							"\n".
							"// Préserve les erreurs PHP dans un fichier log.\n".
							"\$error_log = false; \n".
							"\n".
							"// Safe mode permets de désactiver tous les plugins et SSL.\n".
							"\$safe_mode = false; \n";

				file_put_contents('../config.php', $config);

				$done = true;
			} catch (Exception $e) {
				$failed  = 'Erreur SQL: ' . $e->getMessage() . '<br>';
				$failed .= 'Requete: '. end(Db::$queries)['query'];
			}

			if (isset($_POST['report']) && EVO_REPORT_EMAIL) {
				$status = isset($done) ? 'Réussie' : 'Échouée:';
				$report = "Rapport d'installation du " . date('Y-m-d H:i:s') . ":\n\n".
						  "Status:      $status $failed\n".
						  "Database:    ". Db::DriverName() . ' ' . Db::ServerVersion() . "\n" .
						  "Version CMS: " . EVO_VERSION . " - " . EVO_BUILD . "\n" .
						  "Version PHP: " . PHP_VERSION . "\n" .
						  "Serveur Web: " . $_SERVER['SERVER_SOFTWARE'] . "\n" .
						  "\n" .
						  "URL du CMS:  " . $_POST['url'] . "\n" .
						  "Email admin: " . $_POST['email'] . "\n" .
						  "User Agent:  " . $_SERVER['HTTP_USER_AGENT'];

				@mail(EVO_REPORT_EMAIL, 'Rapport d\'installation', mb_convert_encoding($report, 'ISO-8859-1', 'UTF-8'));
			}
		}
		break;

	case STEP_CLEANUP:
		App::init();
		App::sessionStart(1);
		header('Location: ../admin');
		@rename(__DIR__, __DIR__.'.'.random_hash(8));
		exit;
}

} catch (Exception $e) {
	$warning = "Erreur lors de l'installation: " . $e->getMessage();
	$cur_step = STEP_CONFIG; // Revenir à l'étape de configuration
}

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Evo-CMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <script src="../assets/js/vendor.js"></script>
</head>
<body>
    <div class="installer">
        <div class="installer-container">
            <!-- Header -->
            <div class="installer-header">
                <h1 class="installer-title">Evo-CMS</h1>
                <p class="installer-subtitle">Configuration et installation</p>
            </div>
            
            <!-- Progress Indicator -->
            <div class="progress-indicator">
                <div class="progress-steps">
                    <?php
                    foreach($steps as $step => $tag) {
                        $isActive = $cur_step == $step;
                        $isCompleted = $cur_step > $step;
                        $hasError = $isActive && !empty($warning);
                        
                        $stepClass = 'step-circle';
                        if ($isActive) $stepClass .= ' active';
                        elseif ($isCompleted) $stepClass .= ' completed';
                        else $stepClass .= ' pending';
                        
                        $stepNumber = $step + 1;
                        
                        echo '<div class="progress-step">';
                        echo '<div class="' . $stepClass . '">';
                        if ($isCompleted) {
                            echo '✓';
                        } elseif ($hasError) {
                            echo '✗';
                        } else {
                            echo $stepNumber;
                        }
                        echo '</div>';
                        echo '<div class="step-label">' . $tag . '</div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="row installer-content">
                
                <!-- Main Panel -->
                <div class="col-12 installer-main">
                    <form method="post" autocomplete="off" id="form-content">
                        <?php if (!empty($warning)): ?>
                            <div class="alert alert-error">
                                <?= $warning ?>
                            </div>
                        <?php endif; ?>
                        
                        <input type="hidden" name="language" value="<?= post_e('language', 'french') ?>">
                        
                        <?php if ($cur_step == STEP_LANGUAGE): ?>
                            <div class="step-content">
                                <div class="step-header">
                                    <h2 class="step-title">Sélection de la langue</h2>
                                    <p class="step-description">Choisissez la langue d'interface pour l'installation</p>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="language">Langue</label>
                                    <select class="form-select" id="language" name="language">
                                        <?php
                                        foreach($locales as $locale => $name) {
                                            echo '<option value="'.$locale.'" '.($locale === 'french' ? 'selected' : '').'>'.$name.'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        <?php elseif ($cur_step == STEP_SYSCHECK): ?>
                            <div class="step-content">
                                <div class="step-header">
                                    <h2 class="step-title">Vérifications système</h2>
                                    <p class="step-description">Vérification des prérequis pour l'installation</p>
                                </div>
                                
                                <div class="checks-list">
                                    <?php
                                    foreach ($checks as $check) {
                                        $isSuccess = $check[1];
                                        $checkClass = $isSuccess ? 'success' : 'error';
                                        $statusText = $isSuccess ? 'OK' : 'Erreur';
                                        
                                        echo '<div class="check-item ' . $checkClass . '">';
                                        echo '<div class="check-icon">' . ($isSuccess ? '✓' : '✗') . '</div>';
                                        echo '<div class="check-text">' . htmlentities($check[0], ENT_COMPAT, 'UTF-8') . '</div>';
                                        echo '<div class="check-status">' . $statusText . '</div>';
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php elseif ($cur_step == STEP_DATABASE): ?>
                            <div class="step-content">
                                <div class="step-header">
                                    <h2 class="step-title">Configuration de la base de données</h2>
                                    <p class="step-description">Configurez la connexion à votre base de données</p>
                                </div>
                                
                                <div class="alert alert-info mb-6 db-alert">
                                    <?= __('database.sqlite_legend') ?>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="type">Type de base de données</label>
                                    <select class="form-select" id="type" name="db_type" required>
                                        <?php
                                        if (empty($db_types)) {
                                            echo '<option value="sqlite">SQLite3 (par défaut)</option>';
                                        } else {
                                            $defaultType = @$_POST['db_type'] ?: 'sqlite';
                                            foreach ($db_types as $type => $label) {
                                                $selected = ($type == $defaultType) ? ' selected="selected"' : '';
                                                echo '<option value="' . $type . '"' . $selected . '>' . $label . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                    <input type="hidden" id="db_type_backup" name="db_type_backup" value="<?= @$_POST['db_type'] ?: 'sqlite' ?>">
                                </div>
                                
                                <div class="row db-fields-container">
                                    <div class="col-md-6 mysql db-field form-group">
                                        <label class="form-label" for="host"><?= __('database.host') ?></label>
                                        <input type="text" class="form-control" id="host" name="db_host" value="<?= post_e('db_host', 'localhost') ?>">
                                    </div>
                                    
                                    <div class="col-md-6 sqlite mysql db-field form-group">
                                        <label class="form-label" for="dbname"><?= __('database.name') ?></label>
                                        <input type="text" class="form-control" id="dbname" name="db_name" value="<?= post_e('db_name', 'db-' . substr(md5(uniqid()), 0, 6) . '.sqlite') ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mysql db-field form-group">
                                        <label class="form-label" for="username"><?= __('database.username') ?></label>
                                        <input type="text" class="form-control" id="username" name="db_user" value="<?= post_e('db_user') ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mysql db-field form-group">
                                        <label class="form-label" for="password"><?= __('database.password') ?></label>
                                        <input type="password" class="form-control" id="password" name="db_pass" value="<?= post_e('db_pass') ?>">
                                    </div>
                                    
                                    <div class="col-md-6 sqlite mysql db-field form-group">
                                        <label class="form-label" for="prefixe"><?= __('database.prefix') ?></label>
                                        <input type="text" class="form-control" id="prefixe" name="db_prefix" value="<?= post_e('db_prefix', 'evo_') ?>">
                                    </div>
                                </div>
                            </div>
                                
							<script>
								$(function() {
									function updateFormFields() {
										var selectedType = $('#type').val();
										var container = $('.db-fields-container');
										var alert = $('.db-alert');
										
										// Synchroniser le champ caché
										$('#db_type_backup').val(selectedType);
										
										// Cacher tous les champs de base de données
										$('.db-field').hide();
										$('.' + selectedType).show();
										
										// Gérer le layout des colonnes
										if (selectedType == 'mysql') {
											container.addClass('mysql-layout');
											alert.hide(); // Masquer l'alerte pour MySQL
										} else {
											container.removeClass('mysql-layout');
											alert.show(); // Afficher l'alerte pour SQLite
										}
										
										if (selectedType == 'sqlite') {
											// Générer un nom de base de données unique côté client seulement si vide
											if (!$('#dbname').val()) {
												var randomId = Math.random().toString(36).substr(2, 6);
												$('#dbname').val('db-' + randomId + '.sqlite');
											}
											$('#prefixe').val('');
										} else {
											if (selectedType == 'mysql') {
												$('#dbname').val('');
												$('#prefixe').val('evo_');
											}
										}
									}
									
									// Intercepter la soumission du formulaire
									$('#form-content').on('submit', function(e) {
										var selectedType = $('#type').val();
										
										// S'assurer que le nom de base de données est généré pour SQLite
										if (selectedType == 'sqlite' && !$('#dbname').val()) {
											var randomId = Math.random().toString(36).substr(2, 6);
											$('#dbname').val('db-' + randomId + '.sqlite');
										}
										
										// Validation des champs requis
										if (selectedType == 'mysql') {
											var host = $('#host').val().trim();
											var user = $('#username').val().trim();
											var dbname = $('#dbname').val().trim();
											
											if (!host || !user || !dbname) {
												e.preventDefault();
												alert('Veuillez remplir tous les champs requis pour MySQL (Host, Utilisateur, Nom de la base de données)');
												return false;
											}
										} else if (selectedType == 'sqlite') {
											var dbname = $('#dbname').val().trim();
											console.log('SQLite dbname value:', dbname);
											if (!dbname) {
												e.preventDefault();
												alert('Veuillez remplir le nom de la base de données SQLite');
												return false;
											}
										}
										
										// S'assurer que tous les champs nécessaires sont visibles avant soumission
										$('.db-field').hide();
										$('.' + selectedType).show();
										
										// Forcer la visibilité des champs requis
										if (selectedType == 'mysql') {
											$('.mysql').show();
										} else {
											$('.sqlite').show();
										}
										
										// Synchroniser le champ caché
										$('#db_type_backup').val(selectedType);
										
										console.log('Form submitted with type:', selectedType);
										console.log('Visible fields:', $('.db-field:visible').length);
									});
									
									$('#type').bind('change blur keyup', updateFormFields);
									
									// Initialiser les champs au chargement de la page
									updateFormFields();
								});
							</script>
                        <?php elseif ($cur_step == STEP_CONFIG): ?>
                            <?php
								$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
								$url = $scheme.'://'.$_SERVER['HTTP_HOST'];
								$dir = rtrim(strstr($_SERVER['REQUEST_URI'].'?', '?', true), '/');
								$url .= substr($dir, 0, strrpos($dir, '/'));
                            ?>
                            <div class="step-content row">
                                <div class="step-header">
                                    <h2 class="step-title">Configuration du site</h2>
                                    <p class="step-description">Configurez les paramètres de votre site et l'administrateur</p>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="sitename"><?= __('config.sitename') ?></label>
                                            <input type="text" class="form-control" id="sitename" name="name" value="<?= post_e('name', 'Evo-CMS '.EVO_VERSION) ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label" for="siteurl"><?= __('config.siteurl') ?></label>
                                            <input type="text" class="form-control" id="siteurl" name="url" value="<?= post_e('url', $url) ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label" for="sitemail"><?= __('config.siteemail') ?></label>
                                            <input type="email" class="form-control" id="sitemail" name="email" placeholder="example@domain.com" value="<?= post_e('email') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="sitelogin"><?= __('config.username') ?></label>
                                            <input type="text" class="form-control" id="sitelogin" name="admin" value="<?= post_e('admin', 'admin') ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label" for="sitepass"><?= __('config.password') ?></label>
                                            <input type="password" class="form-control" id="sitepass" name="admin_pass" value="<?= post_e('admin_pass') ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label" for="sitepass2">Confirmation du mot de passe</label>
                                            <input type="password" class="form-control" id="sitepass2" name="admin_pass_confirm" value="<?= post_e('admin_pass_confirm') ?>" placeholder="Confirmez le mot de passe">
                                        </div>
                                    </div>
                                </div>

                                <?php if (EVO_REPORT_EMAIL): ?>
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="report" id="report" value="1" checked>
                                        <?= __('config.report') ?>
                                    </label>
                                </div>
                                <?php endif ?>
                            </div>
                        <?php elseif ($cur_step == STEP_INSTALL): ?>
                            <div class="step-content">                                
                                <?php if ($failed): ?>
                                    <div class="alert alert-error">
                                        <h6><?= __('install.failed') ?></h6>
                                        <span><?= __('install.failed_legend') ?></span>
                                        <p><?= $failed ?></p>
                                    </div>
                                <?php elseif ($done): ?>
                                    <div class="alert alert-success">
                                        <h6><?= __('install.success') ?></h6>
                                        <span><?= __('install.success_legend') ?></span>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label"><?= __('config.siteurl') ?></label>
                                                <div class="form-control" style="background: var(--system-background-secondary); color: var(--system-label);">
                                                    <?= $_POST['url'] ?>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label"><?= __('config.adminurl') ?></label>
                                                <div class="form-control" style="background: var(--system-background-secondary); color: var(--system-label);">
                                                    <?= $_POST['url'] ?>/admin
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label"><?= __('config.username') ?></label>
                                                <div class="form-control" style="background: var(--system-background-secondary); color: var(--system-label);">
                                                    <?= $_POST['admin'] ?>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label"><?= __('config.password') ?></label>
                                                <div class="form-control" style="background: var(--system-background-secondary); color: var(--system-label);">
                                                    <?= $_POST['admin_pass'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

						<!-- Navigation -->
						<div class="container step-navigation">
							<input type="hidden" name="from_step" value="<?= $cur_step ?>">
							<input type="hidden" name="payload" value="<?= is_array($payload) ? base64_encode(serialize($payload)) : $payload ?>">
							
							<?php if (empty($hide_nav)): ?>
								<?php if ($cur_step > 0): ?>
									<button type="button" onclick="$('#step').val(<?= ($cur_step-1) ?>).click();" class="btn btn-secondary">
										<?= __('buttons.previous') ?>
									</button>
								<?php else: ?>
								<?php endif; ?>
								
								<?php if ($next_step <= max(array_keys($steps))): ?>
									<button id="step" type="submit" name="step" value="<?= $next_step ?>" class="btn btn-primary" onclick="<?= ($next_step >= STEP_CONFIG ? '$(\'#form-content,#progressbar\').toggle();' : '') ?>">
										<?= __('buttons.next') ?>
									</button>
								<?php endif; ?>
							<?php elseif (isset($done) && $done): ?>
								<div class="text-center">
									<button type="submit" name="step" value="<?= STEP_CLEANUP ?>" class="btn btn-success btn-lg">
										<?= __('install.complete') ?>
									</button>
								</div>
							<?php endif; ?>
						</div>
					</form>
                </div>
            
				<!-- Footer -->
				<div class="installer-footer">
					<div>Evo-CMS <?= EVO_VERSION ?></div>
					<div>Evolution-Network</div>
				</div>
			</div>
    	</div>
	</div>
    <script>
        $(function() {
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>