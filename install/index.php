<?php
/*
 * Evo-CMS Installer
 */
if (!version_compare(PHP_VERSION, '7.1.0', '>=')) {
	die('EVO-CMS requires PHP 7.1 or greater. Installed: ' . PHP_VERSION);
}

error_reporting(E_ALL & ~E_STRICT);
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
$db_types = array_intersect_key(['sqlite' => 'SQLite3', 'mysql' => 'MySQL'], array_flip(Database::AvailableDrivers()));
$locales = Evo\Lang::getLocales(true, true);

if (file_exists('../config.php') && $cur_step != STEP_CLEANUP) {
	$warning = __('already_installed');
	$hide_nav = true;
	$cur_step = -1;
}

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

		if ($from_step < $cur_step && !in_array(false, $ok) && !in_array(false, $checks)) {
			$cur_step = $next_step = STEP_DATABASE;
		} else {
			$hide_nav = in_array(false, $ok);
			$next_step = STEP_DATABASE;
		}
		break;

	case STEP_DATABASE:
		if (!isset($_POST['db_type']) || !isset($db_types[$_POST['db_type']])) break;

		$payload = [$_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name'], $_POST['db_prefix'], $_POST['db_type']];

		try {
			require '../includes/Database/db.'.strtolower($_POST['db_type']).'.php';

			Db::Connect($_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name'], $_POST['db_prefix']);

			if (Db::TableExists('users')) {
				$warning = __('database.not_empty');
			}
			$next_step = $cur_step = STEP_CONFIG;
		} catch (Exception $e) {
			$warning = $e->getMessage();
		}
		break;

	case STEP_CONFIG:
		if (isset($_POST['email'], $_POST['admin'], $_POST['admin_pass'], $_POST['url'], $_POST['name'], $_POST['payload'])) {
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




				Db::Insert('settings', [
					['name' => 'name', 'value' => post_e('name', '')],
					['name' => 'email', 'value' => post_e('email', '')],
					['name' => 'url', 'value' => post_e('url', '/')],
					['name' => 'language', 'value' => post_e('language', 'french')],
					['name' => 'cookie.name', 'value' => 'evo_'.random_hash(8)],
					['name' => 'database.version', 'value' => DATABASE_VERSION],
					['name' => 'install.version', 'value' => EVO_VERSION],
					['name' => 'install.time', 'value' => time()],
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

				@mail(EVO_REPORT_EMAIL, 'Rapport d\'installation', utf8_decode($report));
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

?>
<!doctype html>
<html>
	<head>
		<meta charset="utf-8">
		<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
		<script src="../assets/js/vendor.js"></script>
		<script src="../assets/js/bootstrap.bundle.min.js"></script>
		<link href="assets/style.css" rel="stylesheet">
		<script>
		$(function() {
			// Bootstrap 5 tooltips initialization
			var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
			var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
				return new bootstrap.Tooltip(tooltipTriggerEl, { placement: 'bottom' });
			});
		});
		</script>
	</head>
	<body>
		<div class="row" id="header">
			<div class="float-start left"><h1>Evo-CMS</h1></div>
			<div class="float-end right">Installation</div>
		</div>
		<div id="content">
			<div class="bg-light p-5 rounded-3">
				<div class="row">
					<div class="col-md-3" id="progression">
					<?php
						foreach($steps as $step => $tag) {
							if ($cur_step == $step)
								echo '<p class="actif '.(empty($warning) ? '' : 'error').'">' . $tag . '</p>';
							elseif($cur_step > $step)
								echo '<p class="pass">' . $tag . '</p>';
							else
								echo '<p>' . $tag . '</p>';
						}
					?>
					</div>
					<div class="col-md-9">
						<form class="form-horizontal" method="post" autocomplete="off" id="form-content">
						<?php
							if (!empty($warning)) {
								echo '<div class="alert alert-danger">'.$warning.'</div>';
							}
						?>
						<input type="hidden" name="language" value="<?= post_e('language', 'french') ?>">
<?php if ($cur_step == STEP_LANGUAGE): ?>
			<h2>Veuillez choisir votre langue<br>Please choose your language</h2>
			<div class="form-group row">
				<div class="col-sm-12">
					<select class="form-control" id="language" name="language">
						<?php
							foreach($locales as $locale => $name) {
								echo '<option value="'.$locale.'" '.($locale === 'french' ? 'selected' : '').'>'.$name.'</option>';
							}
						?>
					</select>
				</div>
			</div>
<?php elseif ($cur_step == STEP_SYSCHECK): ?>
			<h2><?= __('steps.checks') ?></h2>
			<legend><?= __('checks.legend') ?></legend>
			<?php
			echo '<div class="requis_align">';
			foreach ($checks as $check) {
				echo '<div class="row requis">'.
					'<div class="col-md-9 info">' . htmlentities($check[0], ENT_COMPAT, 'UTF-8') . '</div>';
					if (!$check[1]) {
						echo '<div class="col-md-3 error"><i class="glyphicon glyphicon-remove"></i> Erreur</div>';
					} else {
						echo '<div class="col-md-3 ok"><i class="glyphicon glyphicon-ok"></i> Ok</div>';
					}
				echo '</div>';
			}
			echo '</div>';
			?>
<?php elseif ($cur_step == STEP_DATABASE): ?>
			<legend><?= __('steps.database') ?></legend>
			<p><?= __('database.legend') ?></p>
			<div class="sqlite form-group row bs-callout bs-callout-danger">
				<?= __('database.sqlite_legend') ?>
			</div>
			<div class="sqlite mysql form-group row" data-bs-toggle="tooltip">
				<label for="type" class="col-sm-4 col-form-label text-end">Type</label>
				<div class="col-sm-6">
					<select class="form-control" id="type" name="db_type">
					<?php
						foreach ($db_types as $type => $label) {
								echo '<option value="' . $type . '"'  .
										($type == @$_POST['db_type'] ? ' selected="selected"':'') . '>' . $label . '</option>';
						}
					?>
					</select>
				</div>
				<script>
					$(function() {$('#type').bind('change blur keyup', function () {
						$('.form-group').hide();
						$('.'+$(this).val()).show();
						if ($(this).val() == 'sqlite') {
							$('#dbname').val('db-<?= random_hash(6) ?>.sqlite');
							$('#prefixe').val('');
						} else {
							$('#dbname').val('');
							$('#prefixe').val('evo_');
						}
						}).blur();
					});
				</script>
			</div>
			<div class="mysql form-group row">
				<label for="host" class="col-sm-4 col-form-label text-end"><?= __('database.host') ?></label>
				<div class="col-sm-6">
					<input type="text" class="form-control" id="host" name="db_host" value="<?= post_e('db_host', 'localhost') ?>">
				</div>
			</div>
			<div class="sqlite mysql form-group row">
				<label for="dbname" class="col-sm-4 col-form-label text-end"><?= __('database.name') ?></label>
				<div class="col-sm-6">
					<input type="text" class="form-control" id="dbname" name="db_name" value="<?= post_e('db_name') ?>">
				</div>
			</div>
			<div class="mysql form-group row">
				<label for="username" class="col-sm-4 col-form-label text-end"><?= __('database.username') ?></label>
				<div class="col-sm-6">
					<input type="text" class="form-control" id="username" name="db_user" value="<?= post_e('db_user') ?>">
				</div>
			</div>
			<div class="mysql form-group row">
				<label for="password" class="col-sm-4 col-form-label text-end"><?= __('database.password') ?></label>
				<div class="col-sm-6">
					<input type="password" class="form-control" id="password" name="db_pass" value="<?= post_e('db_pass') ?>">
				</div>
			</div>
			<div class="sqlite mysql form-group row" data-bs-toggle="tooltip" title="<?= __('database.prefix_legend') ?>">
				<label for="inputPassword3" class="col-sm-4 col-form-label text-end"><?= __('database.prefix') ?></label>
				<div class="col-sm-6">
					<input type="text" class="form-control" id="prefixe" name="db_prefix" value="<?= post_e('db_prefix', 'evo_') ?>">
				</div>
			</div>
<?php elseif ($cur_step == STEP_CONFIG): ?>
			<?php
			$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
			$url = $scheme.'://'.$_SERVER['HTTP_HOST'];

			$dir = rtrim(strstr($_SERVER['REQUEST_URI'].'?', '?', true), '/');

			$url .= substr($dir, 0, strrpos($dir, '/'));
			?>
			<div>
				<legend><?= __('steps.config') ?></legend>
				<p><?= __('config.legend') ?></p>
					<div class="form-group row">
						<label for="sitename" class="col-sm-3 col-form-label text-end"><?= __('config.sitename') ?></label>
						<div class="col-sm-9">
							<input type="text" class="form-control" id="sitename" name="name" value="<?= post_e('name', 'Evo-CMS '.EVO_VERSION) ?>">
						</div>
					</div>
					<div class="form-group row">
						<label for="siteurl" class="col-sm-3 col-form-label text-end"><?= __('config.siteurl') ?></label>
						<div class="col-sm-9">
							<input type="text" class="form-control" id="siteurl" name="url" value="<?= post_e('url', $url) ?>">
						</div>
					</div>
					<div class="form-group row">
						<label for="sitemail" class="col-sm-3 col-form-label text-end"><?= __('config.siteemail') ?></label>
						<div class="col-sm-9">
							<input type="text" class="form-control" id="sitemail" name="email" placeholder="example@domain.com" value="<?= post_e('email') ?>">
						</div>
					</div>
					<div class="form-group row">
						<label for="sitelogin" class="col-sm-3 col-form-label text-end"><?= __('config.username') ?></label>
						<div class="col-sm-9">
							<input type="text" class="form-control" id="sitelogin" name="admin" value="admin" value="<?= post_e('admin') ?>">
						</div>
					</div>
					<div class="form-group row">
						<label for="sitepass" class="col-sm-3 col-form-label text-end"><?= __('config.password') ?><br><small><?= __('config.password2') ?></small></label>
						<div class="col-sm-9">
							<input type="password" class="form-control" id="sitepass" name="admin_pass" value="<?= post_e('admin_pass') ?>">
							<input type="password" class="form-control" id="sitepass2" name="admin_pass_confirm" value="<?= post_e('admin_pass_confirm') ?>" placeholder="Confirmation">
						</div>
					</div>

					<?php if (EVO_REPORT_EMAIL): ?>
					<div class="form-group row"  data-bs-toggle="tooltip" title="<?= __('config.report_legend') ?>">
						<label class="col-sm-3 col-form-label text-end"></label>
						<div class="col-sm-9">
						<input type="checkbox" name="report" id="report" value="1" checked> <label for="report"><?= __('config.report') ?></label>
						</div>
					</div>
					<?php endif ?>
				</div>
<?php elseif ($cur_step == STEP_INSTALL): ?>
			<legend><?= __('steps.finished') ?></legend>
			<?php if ($failed) { ?>
				<div class="bs-callout bs-callout-danger">
				<p><?= __('install.failed_legend') ?></p>
				<h4><?= __('install.failed') ?></h4>
				<p><?= $failed ?></p>
				</div>
			<?php } elseif ($done) { ?>
				<div class="bs-callout bs-callout-success">
					<h4><?= __('install.success') ?></h4>
					<p><?= __('install.success_legend') ?></p>
				</div>
				<div class="form-group row">
					<label class="col-sm-4 col-form-label text-end"><?= __('config.siteurl') ?> : </label>
					<div class="col-sm-8">
						<div class="form-control"><?= $_POST['url'] ?></div>
					</div>
				</div>
				<div class="form-group row">
					<label class="col-sm-4 col-form-label text-end"><?= __('config.adminurl') ?> : </label>
					<div class="col-sm-8">
						<div class="form-control"><?= $_POST['url'] ?>/admin</div>
					</div>
				</div>
				<div class="form-group row">
					<label class="col-sm-4 col-form-label text-end"><?= __('config.username') ?> : </label>
					<div class="col-sm-8">
						<div class="form-control"><?= $_POST['admin'] ?></div>
					</div>
				</div>
				<div class="form-group row">
					<label class="col-sm-4 col-form-label text-end"><?= __('config.password') ?> : </label>
					<div class="col-sm-8">
						<div class="form-control"><?= $_POST['admin_pass'] ?></div>
					</div>
				</div>
				<div class="text-center">
					<button type="submit" name="step" value="<?= STEP_CLEANUP ?>" class="btn btn-success"><?= __('install.complete') ?></button>
				</div>
			<?php } ?>
<?php endif; ?>
							<br>
							<p class="navbtn text-center">
							<input type="hidden" name="from_step" value="<?= $cur_step ?>">
							<?php
								if (empty($hide_nav)) {
									if ($cur_step > 0)
										echo '<a onclick="$(\'#step\').val(',($cur_step-1).').click();" class="btn btn-primary btn-md" role="submit">'.__('buttons.previous').'</a> ';
									if ($next_step < max(array_keys($steps)))
										echo '<button id="step" type="submit" name="step" value="' . $next_step . '" class="btn btn-primary btn-md" onclick="'. ($next_step >= STEP_CONFIG ? '$(\'#form-content,#progressbar\').toggle();' : '').'" role="submit">'.__('buttons.next').'</button>';
								}
							?>
							</p>
							<input type="hidden" name="payload" value="<?= is_array($payload) ? base64_encode(serialize($payload)) : $payload ?>">
						</form>
						<div id="progressbar" style="display:none;">
							<legend><?= __('install.please_wait') ?>...</legend>
							<div class=" progress progress-striped active">
							  <div class="progress-bar"  role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
								 <span class="sr-only">Endless progressbar</span>
							  </div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row" id="footer">
				<div class="float-start">Evo-CMS <?=EVO_VERSION?></div>
				<div class="float-end">© Evolution-Network</div>
			</div>
		</div>
	</body>
</html>
