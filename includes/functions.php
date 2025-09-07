<?php
/**
 * Undocumented
 */
function __($string, array $parameters = [], $locale = null)
{
	return Evo\Lang::get($string, $parameters, $locale);
}


/**
 * Undocumented
 */
function __plural($string, $count, array $parameters = [], $locale = null)
{
	return Evo\Lang::choice($string, $count, $parameters, $locale);
}


/**
 * Undocumented
 */
function random_hash($length = 16)
{
	return substr(str_replace(['=', '+', '/'], '', base64_encode(random_bytes($length))), 0, $length);
}


/**
 * Undocumented
 */
function html_encode($string)
{
	if (is_array($string)) {
		return array_map('html_encode', $string);
	}
	return htmlspecialchars((string)$string, ENT_COMPAT, 'utf-8');
}


/**
 * Récupère les informations de la page courante dans l'admin
 * @param string $type 'icon', 'title', 'description', 'both', 'all', ou 'html'
 * @return string|array
 */

 function getCurrentPageInfo($type = 'both')
 {
	 $page = App::GET('page');
	 
	 $pageIcons = [
		 '' => 'fa-info-circle',
		 'index' => 'fa-info-circle',
		 'settings' => 'fa-keyboard',
		 'reports' => 'fa-exclamation-circle',
		 'servers' => 'fa-server',
		 'page_edit' => 'fa-file',
		 'pages' => 'fa-file-alt',
		 'menu' => 'fa-list',
		 'gallery' => 'fa-images',
		 'avatars' => 'fa-grin-squint-tears',
		 'downloads' => 'fa-file-download',
		 'forums' => 'fa-list',
		 'comments' => 'fa-comments',
		 'broadcast' => 'fa-envelope',
		 'users' => 'fa-users',
		 'groups' => 'fa-layer-group',
		 'history' => 'fa-user-secret',
		 'security' => 'fa-user-slash',
		 'modules' => 'fa-cogs',
		 'backup' => 'fa-file-archive',
		 'file_editor' => 'fa-file-code',
	 ];
	 
	 $pageTitles = [
		 '' => __('admin/menu.title_info'),
		 'index' => __('admin/menu.title_info'),
		 'settings' => __('admin/menu.sub_config'),
		 'reports' => __('admin/menu.sub_report'),
		 'servers' => __('admin/menu.sub_servers'),
		 'page_edit' => __('admin/menu.sub_newpage'),
		 'pages' => __('admin/menu.sub_pages'),
		 'menu' => __('admin/menu.sub_menu'),
		 'gallery' => __('admin/menu.sub_lib_media'),
		 'avatars' => __('admin/menu.sub_lib_avatar'),
		 'downloads' => __('admin/menu.sub_download'),
		 'forums' => __('admin/menu.sub_forum'),
		 'comments' => __('admin/menu.sub_comments'),
		 'broadcast' => __('admin/menu.sub_newsletter'),
		 'users' => __('admin/menu.sub_members'),
		 'groups' => __('admin/menu.sub_groups'),
		 'history' => __('admin/menu.sub_log_admin'),
		 'security' => __('admin/menu.sub_security'),
		 'modules' => __('admin/menu.sub_modules'),
		 'backup' => __('admin/menu.sub_backup'),
		 'file_editor' => __('admin/menu.sub_files_editor'),
	 ];
	 
	 $pageDescriptions = [
		 '' => 'Tableau de bord principal avec les statistiques du site',
		 'index' => 'Tableau de bord principal avec les statistiques du site',
		 'settings' => 'Configuration générale du site, paramètres et préférences',
		 'reports' => 'Gestion des signalements et modération du contenu',
		 'servers' => 'Configuration et gestion des serveurs de jeu',
		 'page_edit' => 'Création et édition de nouvelles pages et articles',
		 'pages' => 'Gestion complète des pages, articles et contenu du site',
		 'menu' => 'Éditeur de menu pour personnaliser la navigation',
		 'gallery' => 'Bibliothèque multimédia pour gérer les images et fichiers',
		 'avatars' => 'Gestion des avatars et images de profil des utilisateurs',
		 'downloads' => 'Section de téléchargements et fichiers partagés',
		 'forums' => 'Configuration et modération des forums de discussion',
		 'comments' => 'Modération des commentaires et interactions utilisateurs',
		 'broadcast' => 'Envoi de newsletters et communications de masse',
		 'users' => 'Gestion des membres, profils et comptes utilisateurs',
		 'groups' => 'Configuration des groupes et permissions utilisateurs',
		 'history' => 'Historique des actions et logs d\'administration',
		 'security' => 'Sécurité, bannissements et protection du site',
		 'modules' => 'Gestion des modules et extensions du CMS',
		 'backup' => 'Sauvegarde et restauration des données du site',
		 'file_editor' => 'Éditeur de fichiers pour modifications directes',
	 ];
	 
	 $icon = $pageIcons[$page] ?? 'fa-tachometer-alt';
	 $title = $pageTitles[$page] ?? ucfirst($page ?: 'Dashboard');
	 $description = $pageDescriptions[$page] ?? 'Page d\'administration';
	 
	 switch ($type) {
		 case 'icon':
			 return $icon;
		 case 'title':
			 return $title;
		 case 'description':
			 return $description;
		 case 'both':
			 return ['icon' => $icon, 'title' => $title];
		 case 'all':
			 return ['icon' => $icon, 'title' => $title, 'description' => $description];
		 case 'html':
		 default:
			 return '<i class="fa ' . $icon . ' mr-2"></i>' . $title;
	 }
 } 

/**
 * Undocumented
 */
function geoip_country_code($hostname)
{
	try {
		static $reader = null;
		$reader = $reader ?? new GeoIp2\Database\Reader(ROOT_DIR.'/includes/lib-data/GeoLite2.mmdb');
		$record = $reader->country($hostname);
		return $record->country->isoCode; // $record->country->name
	} catch(Throwable $e) {
		return null;
	}
}


/**
 *  Verify if current user is granted a permission
 *  If $name is empty, the function will return true if the user is logged in, false otherwise.
 *
 *  @param string $name
 *  @param integer|null $rel_id
 *  @param boolean $redirect redirect to 403 on failure
 *  @return boolean
 */
function has_permission($name = '', $rel_id = null, $redirect = false)  // Si $name est vide alors on test si logged in.
{
	$current_user = App::getCurrentUser();
	$name = (string)$name;

	if (is_bool($rel_id)) {$redirect = $rel_id; $rel_id = null ;} // temp fix

	if (($name === '' && $current_user->id) || App::groupHasPermission($current_user->group_id, $name, $rel_id)) {
		return true;
	} elseif ($redirect == true) {
		throw new PermissionDenied('URL: ' . App::getURL($_SERVER['REQUEST_URI']));
	} else {
		return false;
	}
}


/**
 *  Return avatar URL (gravatar or local)
 *
 *  @param array|int $user user id or array containing avatar email and/or email
 *  @param integer $size the size to return. Optional
 *  @param string $url_only return url instead of img tag
 *  @return string
 */
function get_avatar($user, $size = 85, $url_only = false)
{
	if (is_scalar($user)) {
		$user = App::getUser($user) ?: [];
	}
	if ($user instanceof Evo\Model) {
		$user = $user->toArray();
	}
	return Evo\Avatars::getAvatar($user, $size, $url_only || $size === true);
}


/**
 *  Recursive remove directory. Similar to rm -rf
 *
 *  @param string $dir
 *  @param boolean $empty_only wether to delete the dir or only its contents.
 *  @return bool
 */
function rrmdir($dir, $empty_only = false)
{
	$files = glob($dir . '/*') ?: [];
	foreach($files as $file) {
		is_dir($file) ? rrmdir($file) : unlink($file);
	}
	return $empty_only ?: @rmdir($dir);
}


/**
 *  BBCode parser
 *
 *  @param string $bbcode
 *  @param boolean $safe_subset whether to allow all bbcodes or only a small safer subset
 *  @return string
 */
function bbcode2html($bbcode, $safe_subset = false)
{
	$parser = new \Evo\BBCode();
	$parser->setSafeTags(['b', 'u', 'i', 's', 'sub', 'sup', 'color', 'spoiler', 'tooltip', 'url']);
	$parser->addTag("quote='([-a-z0-9_]+)' pid='([0-9]+)' dateline='([0-9]+)'", '<blockquote><a href="' . App::getURL('forums', ['pid' => '$2']) . '">$1 a dit</a>:<br>$4</blockquote>');
	$parser->addTag('file=([0-9x]+)', function($match) { return trim(Widgets::filebox(preg_replace('#/.+$#', '', $match[2]), '', $match[1])); });
	$parser->addTag('file', function($match) { return trim(Widgets::filebox(preg_replace('#/.+$#', '', $match[1]))); });

	$bbcode = parse_user_tags($bbcode, function($type, $data, $users, $url) {
		if ($type === 'user') {
			$url = App::getURL('user', $data->id);
			return "[url=$url][tooltip={$data->group->name}]@{$data->username}[/tooltip][/url]";
		} elseif ($type === 'group' || $type === 'all' || $type === 'team') {
			$tooltip = __plural('Aucun membre|1 membre|%count% membres', count($users));
			return "[url=$url][tooltip=$tooltip]@{$data['name']}[/tooltip][/url]";
		}
	});

	return rewrite_links($parser->toHTML($bbcode, $safe_subset));
}


function markdown2html($content, $safe_mode = false, $hard_wrap = false)
{
	$content = $content ?? '';
	$content = (new \Parsedown\ParsedownExtra)
		->setSafeMode($safe_mode)
		->setBreaksEnabled($hard_wrap)
		->parse($content);

	$filebox_rel = random_hash(6);
	$filebox_regex = '/\[file(\s+".*?")?(?:\s+\.(.+?))?(?:\s+([0-9x]+?))?\]\s*(.+?)(\/.+?)?\s*\[\/file\]/i';
	$content = preg_replace_callback($filebox_regex, function($match) use($filebox_rel) {
		list(, $caption, $class, $size, $id) = $match;
		$caption = $caption && !trim($caption, '" ') ? false : trim($caption, '" ');
		return Widgets::filebox($id, $caption, $size, ['rel' => $filebox_rel], strtr($class, '.', ' '));
	}, $content);

	return rewrite_links($content);
}


function rewrite_links($html, $absolute_url = true)
{
	return preg_replace_callback('!\s(href|src|action|poster)="(/?\?p=.*?|\?/.*?|/[^/].*?)"!S', function ($m) {

		list($url, $hash) = explode('#', $m[2].'#');
		list($link, $query) = explode('?', ltrim($url, '/').'?');

		if ($link === 'index.php') $link = '';

		parse_str(html_entity_decode($query), $arr); // Maybe we shouldn't decode at all here...
		$arr = html_encode($arr);

		if (App::getConfig('url_rewriting') && $link === '' && isset($arr['p']) && !defined('EVO_ADMIN')) {
			$link = ltrim($arr['p'], '/');
			unset($arr['p']);
		}

		return ' ' . $m[1] . '="' . App::getURL($link, $arr, $hash) . '"';
	}, $html);
}


function parse_user_tags($content, $callback)
{
	return preg_replace_callback('/(?<tag>@[-a-zÀ-ú0-9_\.\\x{202F}]+)/imu', function($match) use ($callback) { // (?:[^a-z]|^)
		$target = substr(preg_replace('/\\x{202F}/u', ' ', $match['tag']), 1);
		$type = 'none';
		$data = ['id' => 0, 'name' => $target];
		$url = null;
		$users = [];

		if ($target === 'all' || $target === 'everyone') {
			$users = Db::QueryAll('select id, username from {users}');
			$url = App::getURL('users');
			$type = 'all';
		} elseif ($target === 'team') {
			$users = Db::QueryAll('select u.id, u.username from {users} as u join {permissions} as p on p.group_id = u.group_id and p.name ="user.staff" and p.value = 1');
			$url = App::getURL('users', ['team' => 1]);
			$type = 'team';
		} elseif ($data = App::getUser($target)) {
			$users = [$data];
			$url = App::getURL('user', $data->id);
			$type = 'user';
		} elseif ($data = App::getGroup($target)) {
			$users = $data->users;
			$url = App::getURL('users', ['group' => $data->id]);
			$data = $data->toArray();
			$type = 'group';
		}

		$replace = $callback($type, $data, $users, $url);

		return $replace === null ? $match[0] : $replace;
	}, $content);
}


function SendPrivateMessage($to, $subject, $message, $reply_to = 0, $type = 0, $from = null)
{
	$from = $from ?: ($type == 0 ? App::getCurrentUser(): ['id' => 0, 'username' => 'Système', 'group_id' => 1]);

	if ($from instanceof Evo\Model) {
		$from = $from->toArray();
	}

	if (ctype_digit($to)) {
		$to = Db::Get('select id, username, email, group_id from {users} where id = ?', $to);
	} else {
		$to = Db::Get('select id, username, email, group_id from {users} where username = ?', $to);
	}

	if (!$to) {
		return false;
	}

	Db::Insert('mailbox', [
		'reply'   => $reply_to,
		's_id'    => $from['id'],
		'r_id'    => $to['id'],
		'sujet'   => $subject,
		'message' => $message,
		'posted'  => time(),
		'type'    => $type,
	]);

	$variables = ['username' => $to['username'], 'mailfrom' => $from['username'], 'message' => $message];
	sendmail_template($to['email'], 'message.type.'.$type, $variables);

	if ($from['id']) { // Do not log system messages
		App::logEvent($to['id'], 'mail', "Subject: {$subject}\nMessage: {$message}");
	}

	return Db::$insert_id;
}


function sendmail_template($to, $template, array $variables = [])
{
	$variables += ['username' => '', 'sitename' => App::getConfig('name')];

	foreach($variables as $key => $value) {
		$_variables["%$key%"] = $value;
	}

	$subject = __("mail/$template.subject", $_variables);
	$message = __("mail/$template.body", $_variables);

	if (Evo\Lang::has('mail/wrapper')) {
		$message = __('mail/wrapper', [
			'%message%'  => $message,
			'%sitename%' => App::getConfig('name'),
			'%siteurl%'  => App::getConfig('url')
		] + $_variables);
	}

	return App::sendmail($to, $subject, $message);
}


function send_activation_email($username)
{
	if ($r = Db::Get('SELECT id,username,locked,activity,email,reset_key FROM {users} where locked = 2 and username = ?', $username)) {
		$url  = App::getURL('login', ['action' => 'activate','key' => $r['reset_key'], 'username' => $r['username']]);
		if (sendmail_template($r['email'], 'account.activation', ['username' => $r['username'], 'activation_url' => $url])) {
			App::logEvent($r['id'], 'user', 'Mail d\'activation envoyé.');
			return true;
		}
	}
	return false;
}


function settings_form(array $settings, $title = null)
{
	foreach($settings as $name => &$description) {
		$description['default'] = $description['default'] ?? App::getDefaultConfig($name);
		$description['value'] = App::getConfig($name);
	}

	return Widgets::formBuilder($title, $settings, true, __('form.save'));
}


function settings_save(array $settings, array $values)
{
	$changes = [];

	foreach ($values as $field => $value)
	{
		$field = (string)str_replace('||', '.', $field); // PHP will eat the . in POST

		if (array_key_exists($field, $settings) && $value != App::getConfig($field)) {
			if (isset($settings[$field]['default']) && $value === $settings[$field]['default']) {
				// Do nothing, default doesn't have to be valid for the type
			}
			elseif (isset($settings[$field]['validate']) && !preg_match($settings[$field]['validate'], $value)) {
				App::logEvent(0, 'admin', 'Tentative modification du paramètre: '.$field.' avec valeure incorrecte.');
				continue;
			}
			elseif ($settings[$field]['type'] === 'enum') {
				$valid = false;
				foreach($settings[$field]['choices'] as $key => $choice) {
					$valid = ($value == $key || $value == $choice || isset($choice[$value]));
					$valid = $valid || ($value instanceof HtmlSelectGroup && in_array($value, $value->getArrayCopy()));
					if ($valid) break;
				}

				if (!$valid) {
					App::logEvent(0, 'admin', 'Tentative modification du paramètre: '.$field.' avec valeure incorrecte.');
					continue;
				}
			}
			elseif ($settings[$field]['type'] === 'bool' && !in_array($value, [0, 1])) {
				App::logEvent(0, 'admin', 'Tentative modification du paramètre: '.$field.' avec valeure incorrecte.');
				continue;
			}
			elseif ($settings[$field]['type'] === 'number' && !ctype_digit($value)) {
				App::logEvent(0, 'admin', 'Tentative modification du paramètre: '.$field.' avec valeure incorrecte.');
				continue;
			}

			if ($field === 'url') { rtrim($value, '/'); }
			App::setConfig($field, $value);
			if (Db::$affected_rows) {
				$changes[] = $field;
				App::logEvent(0, 'admin', 'Modification du paramètre: '.$field.'.');
			}
		}
	}
	return $changes;
}


function change_comment_state($commentID, $newState = 0)
{
	if (has_permission('mod.comment_censure') && $newState >= 0) {
		if (Db::Update('comments', ['state' => $newState], ['id' => $commentID])) {
			return true;
		}
	} elseif (has_permission('mod.comment_delete') && $newState < 0) {
		$page_id = Db::Get('select page_id from {comments} WHERE id = ?', $commentID);
		if ($page_id && Db::Delete('comments', ['id' => $commentID]) !== false) {
			Db::Exec('update {pages} set comments = (select count(*) from {comments} as c where c.page_id = {pages}.page_id) where page_id = ?', $page_id);
			App::logEvent(0, 'admin', 'Commentaire supprimé #'.$commentID);
			return true;
		}
	}
	return false;
}


function get_menu_tree($extended = false, &$items = null)
{
	$tree = [];

	if ($extended)
		$items = Db::QueryAll('SELECT m.*, r.title AS page_name, r.slug, p.redirect
							   FROM {menu} AS m
							   LEFT JOIN {pages} AS p ON p.page_id = m.link
							   LEFT JOIN {pages_revs} AS r ON r.page_id = p.page_id AND r.revision = p.revisions
							   ORDER BY priority, m.id ASC', true);
	else
		$items = Db::QueryAll('SELECT m.*, p.slug, p.redirect FROM {menu} AS m
							   LEFT JOIN {pages} AS p ON p.page_id = m.link
							   ORDER BY priority, id ASC', true);

	foreach($items as $item) {
		if (!isset($items[$item['parent']]) || $item['parent'] == $item['id'])
			$item['parent'] = 0;

		$tree[$item['parent']][$item['id']] = $item;
	}

	return $tree;
}


function human_unit_to_bytes($size, $fallback = 'B')
{
	$number = intval(trim($size));
	$unit = strtoupper(preg_replace('/^[\d\s]+/', '', $size.$fallback))[0];

	switch ($unit) {
		case 'K': return $number * 1024;
		case 'M': return $number * 1024 * 1024;
		case 'G': return $number * 1024 * 1024 * 1024;
		default: return $number;
	}
 }


function get_effective_upload_max_size($ignore_cms = false)
{
	$max_cms = human_unit_to_bytes(App::getConfig('upload_max_size', 0).'M') ?: PHP_INT_MAX;
	$max_server = min(
		human_unit_to_bytes(ini_get('post_max_size')) ?: PHP_INT_MAX,
		human_unit_to_bytes(ini_get('upload_max_filesize')) ?: PHP_INT_MAX
	);

	return $ignore_cms ? $max_server : min($max_cms, $max_server);
}


function generate_tz_list()
{
	foreach(DateTimeZone::listIdentifiers() as $tz) {
		$dt = new DateTime('now', $tz ? new DateTimeZone($tz) : null);
		$offset = $dt->getOffset();
		$desc = '(GMT' . ($offset >= 0 ? '+' : '-') . gmdate('H:i', abs($offset)) . ', '.$dt->format('H:i').')  ' . $tz;
		$times[$tz] = $desc;
	}
	asort($times);
	return ['0' => 'Default (' . date('H:i') . ')'] + $times;
}


function build_search_query($query, $columns = ['a-z0-9_-\.'])
{
	$where = [];
	$args  = [];
	$link  = ' or ';
	$joined_cols = implode('|', array_merge(preg_replace('/^[a-z0-9_-]+\./', '', $columns), $columns));

	$filter = preg_replace_callback(
		'/('.$joined_cols.'):\s*([^\s]+)/ims',
		function($m) use (&$where, &$args, &$link) {
			$operator = strpos($m[2], '*') !== false || strpos($m[2], '%') !== false ? 'LIKE' : '=';
			$link = ' and ';
			$where[] = "a.{$m[1]} $operator ?";
			$args[] = str_replace('*', '%', $m[2]);
			return '';
		},
		$query
	);

	if ($filter = trim($filter)) {
		foreach($columns as $column) {
			$args[] = '%' . $filter . '%';
			$where[] = $column . ' like ? ';
		}
	}

	return ['where' => implode($link, $where), 'args' => $args];
}


function subscribe($type, $rel_id, $user_id, $email = '')
{
	return Db::Exec('replace into {subscriptions} (type, user_id, rel_id, email) values (?, ?, ?, ?)',
		$type, $user_id, $rel_id, $email);
}


function unsubscribe($type, $rel_id, $user_id = null, $email = null)
{
	if (!$user_id && !$email) {
		return Db::Delete('subscriptions', ['type' => $type, 'rel_id' => $user_id]);
	}

	return Db::Delete('subscriptions', ['type' => $type, 'rel_id' => $user_id, 'user_id' => $rel_id])
	     + Db::Delete('subscriptions', ['type' => $type, 'rel_id' => $user_id, 'email' => $email]);
}


function notify_subscribers($type, $rel_id, array $object = [])
{
	$subscribers = Db::QueryAll('select s.user_id, u.username, COALESCE(u.email, s.email) as email from {subscriptions} as s
		left join {users} as u on u.id = s.user_id where type = ? and rel_id = ?', $type, $rel_id);

	foreach($subscribers as $subscriber) {
		if (App::getCurrentUser()->id == $subscriber['user_id']) {
			continue; // don't notify current user for actions triggered by them
		}
		sendmail_template($subscriber['email'], $type, (array)$subscriber + $object);
	}
}

class HtmlSelectGroup extends \ArrayObject {}
