<?php defined('EVO') or die('Que fais-tu là?');
has_permission('user.view_uprofile', true);

$user_info = App::getUser(App::GET('id', App::getCurrentUser()->id));

if (!$user_info) {
	throw new Warning(__('user.not_found'), __('user.not_found'));
}

App::setTitle(__('user.page_title', ['%user%' => $user_info->username]));

if (trim(App::POST('report')) !== '') {
	Db::Insert('reports', array(
		'user_id'  => App::getCurrentUser()->id,
		'type'     => 'profile',
		'rel_id'   => App::POST('pid'),
		'reason'   => App::POST('report'),
		'reported' => time(),
		'user_ip'  => $_SERVER['REMOTE_ADDR'],
	));
	App::logEvent($user_info->id, 'user', __('user.reported', ['%user%' => $user_info->username]));
}

// Update profile_views but count only one visit per day per session
$_SESSION['profiles_visited'] = $_SESSION['profiles_visited'] ?? [];
$visit = &$_SESSION['profiles_visited'][$user_info->username];

if ($visit < time() - 86400) {
	$user_info->profile_views = $user_info->profile_views + 1;
	$user_info->save();
	$visit = time();
}

App::renderTemplate('pages/user.php',  [
	'ban_reason'   => Db::Get('select reason from {banlist} where rule = ? and type = "username"', $user_info->username),
	'num_friends'  => Db::Get('select count(*) from {friends} where u_id = ?', $user_info->id),
	'num_comments' => Db::Get('select count(*) from {comments} where user_id = ?', $user_info->id),
	'can_edit'     => $user_info->id === App::getCurrentUser()->id || has_permission('admin.edit_uprofile'),
	'can_mod'      => has_permission('moderator'),
	'is_mine'      => $user_info->id === App::getCurrentUser()->id,
	'user_info'    => $user_info,
]);