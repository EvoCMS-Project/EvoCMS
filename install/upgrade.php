<?php
defined('EVO') or die('Ce fichier ne peut être appellé directement.');

// Pour forcer dé-commenter les deux lignes suivantes:
//App::setConfig('database.version', 0, true);

if (App::getConfig('database.version') >= DATABASE_VERSION) {
	die('Base de données déjà à jour.');
}

// Ne pas oublier de modifier includes/version.php!

switch((int)App::getConfig('database.version')) {
	case 0:
	case 1:
		Db::AddColumnIfNotExists('pages_revs', 'attached_files', 'text');
		Db::AddColumnIfNotExists('forums_posts', 'attached_files', 'text');
		Db::AddColumnIfNotExists('users', 'timezone', 'string');
		Db::AddColumnIfNotExists('users', 'last_user_agent', 'string');
		Db::CreateTable('files_rel', array(
						'file_id' 	=> 'integer',
						'rel_id' 	=> 'integer',
						'rel_type' 	=> 'string|128',
		), true);
		Db::AddIndex('files_rel', 'unique', array('file_id', 'rel_id', 'rel_type'));

		Db::AddColumnIfNotExists('servers', 'query_host', 'string');
		Db::AddColumnIfNotExists('servers', 'query_port', 'integer');
		Db::AddColumnIfNotExists('servers', 'query_password', 'string');
		Db::AddColumnIfNotExists('servers', 'query_extra', 'string');
		Db::AddColumnIfNotExists('servers', 'additional_settings', 'text');
		Db::AddColumnIfNotExists('pages', 'hide_title', 'integer');
	case 2:
		Db::AddColumnIfNotExists('users', 'raf_token', 'string');
	case 3:
		Db::AddColumnIfNotExists('users', 'locked', 'integer', false, false, 0);
	case 4:
		Db::AddColumnIfNotExists('files', 'description', 'text');
	case 5:
		Db::AddColumnIfNotExists('users', 'login_type', 'string', false, false, 'normal');
	case 6:
		Db::AddColumnIfNotExists('forums_topics', 'last_poster_id', 'integer', false, false, 0);
	case 7:
		Db::AddColumnIfNotExists('pages_revs', 'extra', 'text');
	case 8:
		Db::AddColumnIfNotExists('pages', 'image', 'string');
	case 9:
		Db::AddColumnIfNotExists('files', 'web_id', 'string');
		Db::Exec('UPDATE {files} SET web_id = id');
	case 10:
		Db::AddColumnIfNotExists('menu', 'visibility', 'integer');
		Db::AddColumnIfNotExists('settings', 'default_value', 'text');
	case 11:
		Db::AddColumnIfNotExists('users', 'login_key', 'string');
		Db::AddColumnIfNotExists('users', 'extra', 'text');
	case 12:
		Db::AddColumnIfNotExists('users', 'profile_views', 'integer');
	case 13:
		Db::AddColumnIfNotExists('servers', 'status_code', 'integer', false, false, -1);
		Db::AddColumnIfNotExists('servers', 'status_data', 'string', false, false, null);
		Db::AddColumnIfNotExists('servers', 'status_time', 'integer', false, false, 0);
		Db::AddColumnIfNotExists('servers', 'poll_interval', 'integer', false, false, 0);
		Db::AddColumnIfNotExists('groups', 'role', 'string', false, false, null);
		Db::Exec('UPDATE {groups} SET `role` = LOWER(`internal`)');
	case 14:
		Db::AddColumnIfNotExists('servers', 'address', 'string', false, false, '');
		Db::AddColumnIfNotExists('servers', 'password', 'string', false, false, '');
		Db::Exec("UPDATE {servers} SET address = CONCAT(host, ':', port)");
		Db::DropColumn('servers', 'host');
		Db::DropColumn('servers', 'port');
}

App::setConfig('database.version', DATABASE_VERSION);

echo 'Termin&eacute;. Nous vous sugg&eacute;rons de supprimer ou renommer le dossier install/ du cms!';

exit;