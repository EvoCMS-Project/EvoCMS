<?php defined('EVO') or die('Que fais-tu là?');
has_permission(null, true);

$user_session = App::getCurrentUser();

if (App::POST('new_friend')) {
	$friend = Db::Get('select id, username, email from {users} where username = ? or email = ?', App::POST('new_friend'), App::POST('new_friend'));
	if ($friend) {
		if (Db::Get('select id from {friends} where u_id = ? and f_id = ?', $user_session->id, $friend['id'])) {
			App::setWarning('Vous êtes déjà amis!');
		} else {
			Db::Insert('friends', ['u_id' => $user_session->id, 'f_id' => $friend['id'], 'state' => 0]);
			sendmail_template($friend['email'], 'friends.request', ['friend' => $user_session->username, 'username' => $friend['username']]);
			App::setSuccess(__('friends.new_friend_success'));
		}
	} else {
		App::setWarning(__('friends.new_friend_warning'));
	}
}
elseif (App::POST('del_request')) {
	$req = Db::Delete('friends', ['u_id' => $user_session->id, 'f_id' => (int)App::POST('del_request')])
	     + Db::Delete('friends', ['f_id' => $user_session->id, 'u_id' => (int)App::POST('del_request')]);
	if ($req >= 1) {
		App::setSuccess(__('friends.del_req_success'));
	} else {
		App::setWarning(__('friends.del_req_warning'));
	}
}
elseif (App::POST('accept_request')) {
	$req = Db::Update('friends', ['state' => 1], ['id' => App::POST('accept_request'), 'f_id' => $user_session->id]);
	if ($req >= 1) {
		if ($u_id = Db::Get('select u_id from {friends} where id = ?', App::POST('accept_request'))) {
			Db::Insert('friends', array('u_id'  => $user_session->id, 'f_id'  => $u_id, 'state' => 1), true);
		}
		App::setSuccess(__('friends.acc_req_success'));
	} else {
		App::setWarning(__('friends.acc_req_warning'));
	}
}

$request_out = $friends = array();

$request_in = Db::QueryAll('SELECT f.id as fid, f.state as fstate, acc.activity, acc.username, acc.email, acc.id, g.name as gname, g.color as gcolor
							FROM {friends} AS f JOIN {users} as acc ON f.u_id = acc.id
							LEFT JOIN {groups} as g ON g.id = acc.group_id
							WHERE f.state <> 1 AND f.f_id = ?', $user_session->id, true);

$requests = Db::QueryAll('SELECT f.id as fid, f.state as fstate, acc.activity, acc.username, acc.email,acc.id , g.name as gname, g.color as gcolor
						  FROM {friends} AS f JOIN {users} as acc ON f.f_id = acc.id
						  LEFT JOIN {groups} as g ON g.id = acc.group_id
						  WHERE f.u_id = ?', $user_session->id);

foreach($requests as $row)
{
	if ($row['fstate'] == 1) {
		$friends[$row['fid']] = $row;
	} else {
		$request_out[$row['fid']] = $row;
	}
}

App::renderTemplate('pages/friends.php', [
	'friends'       => $friends,
	'request_in'    => $request_in,
	'request_out'   => $request_out,
	'raf_url'       => App::getURL('register', ['raf' => $user_session->raf_token])
]);
