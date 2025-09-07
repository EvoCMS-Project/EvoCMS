<?php defined('EVO') or die('Que fais-tu là?');
/*
 * Evo-CMS: Simple forum.
 *
 */
App::setBodyClass('page-full');

$user_session = App::getCurrentUser();

$post = $forum = $topic = $sub = $mode = null;
$message = $subject = '';
$forum_moderator = false;

$topics_per_page = 25;
$posts_per_page = 10;

$pn = ceil(App::GET('pn', 1)) ?: 1;
$ptotal = 0;

$topic_read = &$_SESSION['forum_new_posts'];
$last_visit = $_SESSION['last_visit'] ?? 0;

if (App::GET('edit')) {
	$post = Db::Get('select * from {forums_posts} where id = ? ', App::GET('edit'));
	if (!$post) return App::setNotice(__('forum.notice_post_unexist'));

	$topic = Db::Get('select * from {forums_topics} where id = ? ', $post['topic_id']);
	if (!$topic) return App::setNotice(__('forum.notice_topic_unexist'));

	$permission = has_permission('forum.moderation', $topic['forum_id']) || has_permission('mod.forum_post_edit');

	if (!has_permission() || (!$permission && $post['poster_id'] != $user_session->id))
		return App::setWarning(__('forum.warning_right_post_edit'));

	if ($topic['closed'] == 1 && !$permission)
		return App::setWarning(__('forum.warning_topic_closed'));

	$message = $post['message'];
	$subject = $topic['subject'];

	$mode = 'Édition';
}
elseif (App::REQ('pid')) {
	$post = Db::Get('select * from {forums_posts} where id = ? ', App::REQ('quote', App::REQ('pid')));
	if (!$post) return App::setNotice(__('forum.notice_post_unexist'));

	$topic = Db::Get('select * from {forums_topics} where id = ? ', $post['topic_id']);
	if (!$topic) return App::setNotice(__('forum.notice_topic_unexist'));

	$pn = ceil (Db::Get('select count(*) from {forums_posts} where topic_id = ? and id <= ?', $post['topic_id'], $post['id']) / $posts_per_page);
	$ptotal = ceil($topic['num_posts'] / $posts_per_page);

	$mode = 'Nouvelle réponse';
}
elseif (App::REQ('topic')) {
	$topic = Db::Get('select * from {forums_topics} where id = ? ', App::REQ('topic'));
	if (!$topic) return App::setNotice(__('forum.notice_topic_unexist'));

	$ptotal = ceil($topic['num_posts'] / $posts_per_page);
	$mode = 'Nouvelle réponse';
}
elseif (App::GET('id')) {
	$forum = Db::Get('select * from {forums} where id = ? ', App::GET('id'));
	if (!$forum) return App::setNotice(__('forum.notice_forum_unexist'));

	$ptotal = ceil($forum['num_topics'] / $topics_per_page);
	$mode = 'Nouvelle discussion';
}


if (App::GET('quote') && $post = Db::Get('select * from {forums_posts} where id = ?', App::GET('quote'))) {
	$message = '[quote][url=?p=forums&pid=' . $post['id'] . '#msg' . $post['id'] . '][b]' . $post['poster'] . "[/b] a dit[/url]:\n"
				. $post['message'] . "[/quote]\n\n";
}


if (isset($topic)) {
	$forum = Db::Get('select * from {forums} where id = ? ', $topic['forum_id']);
	if (!$forum) return App::setNotice(__('forum.notice_forum_unexist'));

	$sub = Db::Get('select count(*) from {subscriptions} where type = "forum.topic" and user_id = ? and rel_id = ?', $user_session->id, $topic['id']);

	if (!isset($topic_read[$topic['id']])) {
		Db::Exec('update {forums_topics} set num_views = num_views + 1 where id = ?', $topic['id']);
	}
}


if (!empty($topic['redirect'])) {
	if (!App::GET('force') && !App::GET('edit')) {
		App::redirect($topic['redirect']);
	} else {
		App::setNotice(__('forum.notice_forum_unexist') .' : '. $topic['redirect']);
	}
}


if (isset($post, App::$POST['report'])) {
	Db::Insert('reports', array(
		'user_id' => $user_session->id,
		'type' => 'forum',
		'rel_id' => $post['id'],
		'reason' => App::POST('report'),
		'reported' => time(),
		'user_ip' => $_SERVER['REMOTE_ADDR']
	));
	App::logEvent(0, 'forum', __('forum.logevent_topic_flagged') .' : '.$topic['subject']);
}


if (isset($forum)) {
	has_permission('forum.read', $forum['id'], true);
	$forum_moderator = has_permission('forum.moderation', $forum['id']);
}


$edit_mode = isset($mode) && App::GET('compose', App::GET('edit', App::GET('quote')));
$can_redirect = $forum_moderator || has_permission('mod.forum_topic_redirect');


if (isset($forum) && has_permission('forum.write', $forum['id']))
{
	if (isset($_FILES['ajaxup']) && has_permission('user.upload'))
	{
		try {
			$file = Evo\Models\File::create('ajaxup', 'forums');
			die(json_encode(array($file->name, $file->web_id, $file->web_id, $file->size)));
		} catch (UploadException $e) {
			die("Error: {$e->getMessage()}");
		}
	}
	elseif (App::POST('message') === '')
	{
		App::setWarning(__('forum.warning_empty_msg'));
	}
	elseif (App::POST('subject') === '')
	{
		App::setWarning(__('forum.warning_empty_subject'));
	}
	elseif ($post && App::GET('edit') && App::POST('message') !== null) //Edit
	{
		Db::Exec('update {forums_posts} set message = ?, edited = ? where id = ?', App::POST('message'), time(), $post['id']);

		if ($topic['first_post_id'] == $post['id'] && !empty(App::$POST['subject'])) {
			Db::Exec('update {forums_topics} set subject = ? where first_post_id = ?', App::$POST['subject'], $post['id']);
		}

		if ($topic['first_post_id'] == $post['id'] && $can_redirect) {
			Db::Exec('update {forums_topics} set redirect = ? where first_post_id = ?', App::$POST['redirect'], $post['id']);
		}

		$pn = ceil (Db::Get('select count(*) from {forums_posts} where topic_id = ? and id <= ?', $post['topic_id'], $post['id']) / $posts_per_page);

		topic_subscribe($topic['id'], isset(App::$POST['subscribe']));

		App::redirect('forums', ['topic'=>$topic['id'], 'pn'=>$pn], '#msg'.$post['id']);
		App::setSuccess(__('forum.warning_msg_saved'));
	}
	elseif ($topic && App::POST('message')) //Reply
	{
		if ($topic['closed'] && !($forum_moderator || has_permission('mod.forum_topic_close')))
			return App::setWarning(__('forum.warning_post_topic_closed'));

		Db::Insert('forums_posts', array(
			'topic_id' => $topic['id'],
			'poster' => $user_session->username,
			'poster_id' => $user_session->id,
			'poster_ip' => $_SERVER['REMOTE_ADDR'],
			'user_agent' => $_SERVER['HTTP_USER_AGENT'],
			'message' => App::POST('message'),
			'posted' => time(),
			'attached_files' => serialize([]),
		));

		$pid = Db::$insert_id;

		Db::Exec('update {forums_topics} set num_posts = num_posts + 1, last_poster = ?, last_post = ?, last_post_id = ? where id = ?', $user_session->username, time(), $pid, $topic['id']);
		Db::Exec('update {forums} set num_posts = num_posts + 1, last_topic_id = ? where id = ?', $topic['id'], $topic['forum_id']);
		Db::Exec('update {users} set num_posts = num_posts + 1 where id = ?', $user_session->id);

		$url = App::getURL('forums', ['topic'=>$topic['id'], 'pn'=>ceil(($topic['num_posts']+1) / $posts_per_page)], '#msg'.$pid);

		message_notify_usertags(App::POST('message'), $pid);
		topic_subscribe($topic['id'], isset(App::$POST['subscribe']));
		notify_subscribers('forum.topic', $topic['id'], ['subject' => $topic['subject'], 'url' => $url]);

		App::trigger('forum_post_created', array($pid));
		App::setSuccess(__('forum.success_msg_saved'));
		App::redirect($url);  // compute last page!!
	}
	elseif ($forum && App::POST('message')) //Topic
	{
		$redirect = $can_redirect ? App::$POST['redirect'] : '';

		Db::Insert('forums_topics', array(
			'forum_id'       => $forum['id'],
			'poster_id'      => $user_session->id,
			'poster'         => $user_session->username,
			'subject'        => App::$POST['subject'],
			'first_post'     => time(),
			'last_post'      => time(),
			'last_poster'    => $user_session->username,
			'last_poster_id' => $user_session->id,
			'num_posts'      => 1,
			'first_post_id'  => 0,
			'last_post_id'   => 0,
			'redirect'       => $redirect
		));
		$tid = Db::$insert_id;

		Db::Insert('forums_posts', array(
			'topic_id' => $tid,
			'poster' => $user_session->username,
			'poster_id' => $user_session->id,
			'poster_ip' => $_SERVER['REMOTE_ADDR'],
			'user_agent' => $_SERVER['HTTP_USER_AGENT'],
			'message' => App::POST('message'),
			'posted' => time(),
			'attached_files' => serialize([]),
		));
		$pid = Db::$insert_id;

		Db::Exec('update {forums_topics} set first_post_id = ?, last_post_id = ? where id = ?', $pid, $pid, $tid);
		Db::Exec('update {forums} set num_posts = num_posts + 1, num_topics = num_topics + 1, last_topic_id = ? where id = ?', $tid, $forum['id']);
		Db::Exec('update {users} set num_posts = num_posts + 1 where id = ?', $user_session->id);

		message_notify_usertags(App::POST('message'), $pid);
		topic_subscribe($tid, isset(App::$POST['subscribe']));

		App::trigger('forum_topic_created', array($tid));
		App::setSuccess(__('forum.success_msg_saved'));
		App::redirect('forums', ['topic' => $tid]);
	}

	if (isset($topic))
	{
		if (App::POST('move-topic'))
		{
			if (!$forum_moderator && !has_permission('mod.forum_topic_move'))
				return App::setWarning(__('forum.warning_post_topic_closed'));

			if ($topic['forum_id'] == App::$POST['move-topic']) {
				App::setNotice(__('forum.warning_topic_move_prohibit') .'<strong>' . $forum['name'] . '</strong>.');
			} elseif (Db::Exec('update {forums_topics} set forum_id = ? where id = ?', App::$POST['move-topic'], $topic['id'])) {
				forum_refresh(App::$POST['move-topic']);
				forum_refresh($topic['forum_id']);

				$forum = Db::Get('select * from {forums} where id = ?', App::$POST['move-topic']);

				App::logEvent(0, 'forum', __('forum.logevent_topic_move'));
				App::setSuccess(__('forum.success_topic_move'));
			} else {
				App::setWarning(__('forum.warning_topic_move_fail'));
			}
		}
		elseif (App::POST('delete-topic'))
		{
			if (!($forum_moderator || has_permission('mod.forum_topic_delete')))
				return App::setWarning(__('forum.warning_topic_del_prohibit'));

			if (!Db::Delete('forums_topics', ['id' => $topic['id']]))
				return App::setWarning(__('forum.warning_topic_del_fail'));

			$u = Db::QueryAll('select poster_id, count(*) as cnt from {forums_posts} where topic_id = ? group by poster_id', $topic['id']);
			foreach($u as $count) {
				Db::Exec('update {users} set num_posts = num_posts - ? where id = ?', $count['cnt'], $count['poster_id']);
			}
			Db::Delete('forums_posts', ['topic_id' => $topic['id']]);

			forum_refresh($topic['forum_id']);
			unsubscribe('forum.topic', $topic['id']);

			App::logEvent(0, 'forum', __('forum.logevent_topic_delete'));
			App::trigger('forum_topic_deleted', array($topic));
			App::redirect('forums', $topic['forum_id']);
		}
		elseif (App::GET('delete-post'))
		{
			$post = Db::Get('select * from {forums_posts} where id = ? and topic_id = ?', App::GET('delete-post'), $topic['id']);

			if (!$post || !has_permission() || (!($forum_moderator || has_permission('mod.forum_post_delete')) && $post['poster_id'] != $user_session->id))
				return App::setWarning(__('forum.warning_post_del_prohibit'));

			Db::Exec('update {users} set num_posts = num_posts -1 where id = ?', $post['poster_id']);
			Db::Delete('forums_posts', ['id' => App::GET('delete-post')]);

			if (Db::Get('select count(*) from {forums_posts} where topic_id = ?', $topic['id']) == 0) {
				Db::Delete('forums_topics', ['id' => $topic['id']]);

				forum_refresh($topic['forum_id']);
				unsubscribe('forum.topic', $topic['id']);

				App::logEvent(0, 'forum', __('forum.logevent_post_delete'));
				App::trigger('forum_topic_deleted', array($topic));
				App::redirect('forums', $topic['forum_id']);
			}
			else {
				topic_refresh($topic['id']);
				forum_refresh($topic['forum_id']);
				unsubscribe('forum.topic', $topic['id'], $post['poster_id']);

				App::trigger('forum_post_deleted', array($post));
				App::setSuccess(__('forum.success_post_del'));
			}
		}
		elseif (isset(App::$POST['sticky']) && ($forum_moderator || has_permission('mod.forum_topic_stick'))) {
			$sticky = ((int)App::$POST['sticky'] === 0) ? 0 : $topic['sticky'] + (int)App::$POST['sticky'];
			$topic['sticky'] = $sticky;

			Db::Exec('update {forums_topics} set sticky = ? where id = ?', $sticky, $topic['id']);

			App::trigger('forum_topic_updated', array($topic, ['sticky' => $sticky]));
			App::setSuccess(__('forum.success_sticky'));
		}
		elseif (isset(App::$POST['closed']) && ($forum_moderator || has_permission('mod.forum_topic_close'))) {
			$closed = (int)App::$POST['closed'];
			$topic['closed'] = $closed;

			Db::Exec('update {forums_topics} set closed = ? where id = ?', $closed, $_REQUEST['topic']);

			App::trigger('forum_topic_updated', array($topic, ['closed' => $closed]));
			App::setSuccess(__('forum.success_sticky'));
		}

		if (!App::GET('topic') && !App::REQ('pid') && !App::REQ('edit')) {
			$topic = null;
		}
	}
}

$tpl_vars = compact('topic', 'forum', 'post', 'forum_moderator', 'posts_per_page', 'topics_per_page', 'last_visit', 'topic_read');



function forums_list() {
	$forums = Db::QueryAll('select f.id, f.name, f.*, t.last_post, t.subject, t.last_post_id, t.redirect as tredirect, ulp.id as last_poster_id, ulp.username as last_poster
							from {forums} as f
							join {permissions} as perm on perm.name = "forum.read" and perm.related_id = f.id and perm.group_id = ? and perm.value = 1
							left join {forums_topics} as t on t.id = f.last_topic_id
							left join {forums_posts} as p on p.id = t.last_post_id
							left join {users} as ulp on ulp.id = p.poster_id
							order by f.priority, f.id asc', App::getCurrentUser()->group_id, true);

	$categories = Db::QueryAll('select * from {forums_cat} order by priority,id asc', true);

	foreach($forums as $forum) {
		$categories[$forum['cat']]['forums'][] = $forum;
	}

	foreach($categories as $id => $cat) {
		if (empty($cat['forums'])) unset($categories[$id]);
	}

	return $categories;
}

function forum_refresh($id) {
	Db::Exec('update {forums} set num_topics = coalesce((select count(*) from {forums_topics} where forum_id = {forums}.id), 0) where id = ?', $id);
	Db::Exec('update {forums} set num_posts = coalesce((select sum(num_posts) from {forums_topics} where forum_id = {forums}.id), 0) where id = ?', $id);
	Db::Exec('update {forums} set last_topic_id = (select id from {forums_topics} where forum_id = {forums}.id order by last_post desc limit 1) where id = ?', $id);
}

function topic_refresh($id) {
	if ($last_post = Db::Get('select * from {forums_posts} where id = (select max(id) from {forums_posts} where topic_id = ?)', $id)) {
		Db::Exec(
			'update {forums_topics}
			    set num_posts = coalesce((select count(*) from {forums_posts} where topic_id = ?), 0),
				    last_post_id = ?, last_post = ?, last_poster = ?, last_poster_id = ?
			    where id = ?',
			$id, $last_post['id'], $last_post['posted'], $last_post['poster'], $last_post['poster_id'], $id
		);
	}
}

function topic_subscribe($id, $sub = true) {
	if (has_permission()) {
		if ($sub) {
			subscribe('forum.topic', $id, App::getCurrentUser()->id);
		} else {
			unsubscribe('forum.topic', $id, App::getCurrentUser()->id);
		}
	}
}

function message_notify_usertags($message, $pid) {
	$notified = [];

	return parse_user_tags($message, function($type, $data, $users, $url) use ($pid, &$notified) {
		if (($type === 'user' && has_permission('user.forum_tag_user')) || has_permission('user.forum_tag_group')) {
			foreach($users as $user) {
				if (!in_array($user['id'], $notified)) {
					SendPrivateMessage(
						$user['id'],
						__('forum.message_notify_usertags'),
						App::getURL('forums', ['pid' => $pid], '#msg' . $pid),
						0,
						MSG_NOTIFICATION,
						App::getCurrentUser()
					);
				}
				$notified[] = $user['id'];
			}
		}
	});
}

function forum_user_link($user_id, $user_name)
{
	if ($user_id == 0) {
		return '<i>'. __('forum.user_link_guest') .'</i>';
	} elseif(!$user_name) {
		return '<i>'. __('forum.user_link_deleted') .'</i>';
	} else {
		return '<a href="'.App::getURL('user', ['id' => $user_id]) . '">' . html_encode($user_name) .'</a>';
	}
}


App::addCrumb((App::getConfig('forums.name') ?: 'Forums'), App::getURL('forums'));

if ($forum) App::addCrumb($forum['name'], App::getURL('forums', $forum['id']));
if ($topic) App::addCrumb($topic['subject'], App::getURL('forums', ['topic' => $topic['id']]));
if ($post)  App::addCrumb('Post #' . $post['id'], App::getURL('forums', ['pid' => $post['id']], 'msg'.$post['id']));

if ($edit_mode)                          App::addCrumb($mode);
elseif (App::GET('search') == 'recent')  App::addCrumb(__('forum.crumb_recent'));
elseif (App::GET('search') == 'noreply') App::addCrumb(__('forum.crumb_no_answer'));
elseif (App::GET('search'))              App::addCrumb(__('forum.crumb_search'));

App::setTitle(implode(' / ', array_reverse(App::getCrumbs(true))));


echo '<div class="forum-wrapper">';

echo '<div id="content">';

App::renderTemplate('forums/navbar.php', $tpl_vars + compact('ptotal', 'pn'));

echo '<div class="forum-main">';


if ($edit_mode) {
	if (!has_permission('forum.write', $forum['id'])) {
		App::setWarning(__('forum.warning_write_permit'));
		if (!has_permission()) {
			App::setWarning(__('forum.warning_write_guest0').'<a href="'.App::getURL('login', ['redir'=>$_SERVER['REQUEST_URI']]).'">'. __('forum.warning_write_guest1') .'</a> ?', true);
		}
	} elseif(isset($topic) && $topic['closed']) {
		App::setWarning(__('forum.warning_topic_closed'));
	}
}
elseif (App::GET('search', '') !== '') {
	echo '<div class="card mb-4">
			<div class="card-header">'. __('forum.search_title') .'</div>
			<form method="get">
			<input type="hidden" name="p" value="forums">
			<div class="card-body">
				<div class="col-sm-8 control-label">'. __('forum.search_by_sentences') .' :<br>
					  <input type="text" class="form-control" name="text" value="' . html_encode(App::GET('text')) . '">';

	if ($forum) {
		echo '<label><input type="checkbox" name="forum_only" value="'.$forum['id'].'" ' . (App::GET('forum_only') ? 'checked':'') . '> '. __('forum.search_only_into') .' <i>' . $forum['name'] . '</i></label>';
	}

	echo			'<br>
					  <button type="submit" name="search" value="1" class="btn btn-sm btn-primary">'. __('forum.search_btn_launch') .'</button>
					</div>
					<div class="col-sm-4 control-label">'. __('forum.search_by_author') .' :<br>
					  <input type="text" class="form-control" data-autocomplete="userlist" name="poster" value="' . html_encode(App::GET('poster')) . '">
					</div>
				</div>
			  </form>
		</div>';

	//Todo: build a word list instead of doing full text search...
	$text = trim(App::GET('text'));
	$poster = trim(App::GET('poster'));

	$forums = Db::QueryAll('select * from {forums}');

	$query = array(1);

	if (App::GET('forum_only'))
		$query[] = 'forum_id = '. (int) App::GET('forum_only');

	if ($poster)
		$query[] = 'username = "' . Db::Escape($poster) . '"';

	if (App::GET('search') == 'recent')
		$query[] = 'p.posted >= ' . (time()-48*3600);

	if (App::GET('search') == 'noreply')
		$query[] = 't.num_posts = 1';

	if (count($query) > 1 || $text) {

		$posts = Db::QueryAll('select p.*, t.subject, a.username, f.name as fname
							   from {forums_posts} as p
							   left join {forums_topics} as t on t.id = p.topic_id
							   left join {users} as a on a.id = p.poster_id
							   join {forums} as f on f.id = forum_id
							   join {permissions} as perm on perm.name = "forum.read" and perm.related_id = f.id and perm.group_id = ? and perm.value = 1
							   where message like ? and '.str_replace('?', '', implode(' and ', $query)).'
							   order by p.id desc LIMIT ?, ?', $user_session->group_id, '%'.str_replace(' ', '%', $text).'%', $posts_per_page * ($pn-1), $posts_per_page, true);

		$topics = Db::QueryAll('select p.*, t.subject, a.username, f.name as fname
								from {forums_topics} as t
								left join {forums_posts} as p on t.first_post_id = p.id
								left join {users} as a on a.id = p.poster_id
								join {forums} as f on f.id = forum_id
								join {permissions} as perm on perm.name = "forum.read" and perm.related_id = f.id and perm.group_id = ? and perm.value = 1
								where subject like ? and '.str_replace('?', '', implode(' and ', $query)).'
								order by p.id desc LIMIT ?, ?', $user_session->group_id, '%'.$text.'%', $posts_per_page * ($pn-1), $posts_per_page, true);

		$search = $posts + $topics;

		if ($search) {
			echo '<ul class="list-group forum">';

			foreach($search as $post) {
				if ($text) {
					$post['message'] = preg_replace('#'.str_replace(' ', '.*', preg_replace('![^a-z0-9_ 	]!i', '\\\\$0', $text)).'#mUi', '[bgcolor=yellow]$0[/bgcolor]', $post['message']);
					$post['subject'] = str_ireplace($text, '<span style="background-color:yellow">' . html_encode($text). '</span>', Format::truncate($post['subject'], 40));
				}

				$r = '<span class="badge">'. __('forum.search_posted') .' '. Format::today($post['posted']) .' '. __('forum.search_by'). ' '. ($poster ? '<span style="color:yellow">'.html_encode($post['poster']).'</span>' : html_encode($post['poster'])) . '</span>';

				$r .= '<legend><small>'.$post['fname'].'</small> → <a href="'.App::getURL('forums', ['pid'=>$post['id']], '#msg'.$post['id']) . '">' . $post['subject'] . '</a></legend>';
				$r .=  bbcode2html($post['message']).'<br>';
				echo '<li class="list-group-item">' . $r . '</li>';
			}

			echo '</ul>';
			if (count($posts) == $posts_per_page || count($topics) == $posts_per_page)
				$pptotal = $pn + 1;
			else
				$pptotal = $pn;

			if ($pn >= 1) {
				unset(App::$GET['pn']);
				echo Widgets::pager($pptotal , $pn, 10, '/?'.implode('&', array_map(function (&$v, $k) { return $v = $k.'='.urlencode($v);}, App::GET(), array_keys(App::GET()))).'&pn=');
			}
		} else {
			App::setNotice(__('forum.search_notfound'));
		}
	}
}
elseif (isset($topic)) {

	$topic_read[$topic['id']] = $topic['last_post'];

	$posts = Db::QueryAll('select p.*, a.avatar, a.username, a.ingame, g.name as gname, g.color, a.registered, a.num_posts, a.country, a.email, b.reason as ban_reason
	                       from {forums_posts} as p
	                       left join {users} as a on a.id = p.poster_id
	                       left join {groups} as g on g.id = a.group_id
	                       left join {banlist} as b on a.username like b.rule and b.type = "username"
	                       where topic_id = ? order by id asc LIMIT ?, ?', $topic['id'], $posts_per_page * ($pn-1), $posts_per_page, true);

	App::trigger('forum_before_posts_loop', array(&$posts));

	if ($forum_moderator || has_permission('mod.forum_topic_move')) {
		echo '<div id="move-topic-container" style="display:none;"><form method="post"><div class="mb-3 row">';
			echo '<div class="col-md-9">';
				foreach(forums_list() as $c) {
					if ($c['forums']) {
						$c['name'] = strip_tags(bbcode2html($c['name']));
						foreach($c['forums'] as $f) {
							if (!isset($cats[$c['name']])) {
								$cats[$c['name']] = new htmlSelectGroup();
							}
							$cats[$c['name']][$f['id']] = $f['name'];
						}
					}
				}
				echo Widgets::select('move-topic', $cats, $topic['forum_id']);
			echo '</div>';
			echo '<div class="col-md-3"><button class="btn btn-primary" name="topic" value="' . $topic['id'] . '">'. __('forum.btn_move_topic') .'</button></div>';
		echo '</div></form></div>';
	}

	App::renderTemplate('forums/topic.php', $tpl_vars + compact('posts'));
}
elseif (isset($forum)) {
	$topics = Db::QueryAll('select t.*, up.username as poster, ulp.id as last_poster_id, ulp.username as last_poster
							from {forums_topics} as t
							left join {forums_posts} as p on p.id = t.last_post_id
							left join {users} as ulp on ulp.id = p.poster_id
							left join {users} as up on up.id = t.poster_id
							where forum_id = ?
							order by sticky desc, last_post desc
							LIMIT ?, ?', $forum['id'], $topics_per_page * ($pn-1), $topics_per_page, true);
	App::trigger('forum_before_topics_loop', array(&$topics));
	App::renderTemplate('forums/forum.php', $tpl_vars + compact('can_redirect', 'topics'));
}
else {
	$forums = forums_list();
	App::trigger('forum_before_forums_loop', array(&$forums));
	App::renderTemplate('forums/index.php', $tpl_vars + compact('can_redirect', 'forums'));
}



if ($ptotal > 1 && !$edit_mode) {
	$params = ['id' => $forum['id']];
	if (isset($topic)) {
		$params['topic'] = $topic['id'];
	}
	if ($ptotal > 1)
		echo Widgets::pager($ptotal, $pn, 10, App::getURL('forums', $params + ['pn' => '']));
	else
		echo '<br>';
}


echo '</div>'; //<div class="forum-main">
echo '</div>'; //<div id="content">


if (isset($forum) && has_permission('forum.write', $forum['id']) && ($edit_mode || (isset($topic) && !$topic['closed'])) )
{
	echo '<form method="post">';
	echo '<div class="forum-editbox card" id="message">
			<div class="card-header">' . ucfirst($mode) . '</div>
			<div class="card-body form-horizontal">';

	if (App::GET('compose') || (App::GET('edit') && isset($post) && $topic['first_post_id'] == $post['id'])) {
		echo '<div class="mb-3 row">
				<label class="col-sm-auto col-form-label" for="subject">'. __('forum.subject') .' :</label>
				<div class="col control">
					<input name="subject" class="form-control" type="text" maxlength="60" value="'.html_encode(App::POST('subject', $subject)).'">
				</div>
			  </div>';
	}

	echo '<textarea class="form-control" id="editor" name="message" style="width:100%; height:'.($post || App::GET('compose') ? '325px' : '250px').';" placeholder="'. __('forum.textarea_ph') .'">'.html_encode(App::POST('message', $message)).'</textarea>';

	echo '<div style="padding:5px">
				<label><input type="checkbox" name="subscribe" value="1" '.($sub?'checked':'').'> '. __('forum.subscribe_topic') .'</label>
		  </div>';

	echo '<div class="text-center">
			<button class="btn btn-primary" type="submit" name="compose" value="1">'. __('forum.btn_send') .'</button>';
	echo '</div>';

	if ($can_redirect && ($edit_mode && $post && $topic['first_post_id'] == $post['id'])) {
		echo '
		<div class="text-end">
			<button class="btn btn-secondary" type="button"
				  onclick="$(this).button(\'toggle\');$(\'#redirectForm\').toggle().find(\'input\').val(\'\');"
				  >'. __('forum.redirect') .'...</button>
		</div>
		<div class="mb-3" id="redirectForm" style="display: none;">
			<label class="col-sm-2 control-label" for="redirect">'. __('forum.redirect') .' :</label>
			<div class="col-sm-10 control">
				<input name="redirect" class="form-control" placeholder="'. __('forum.exemple') .' : https://google.ca" type="text" maxlength="255" value="'.html_encode(App::POST('redirect', $topic['redirect'])).'">
				'. __('forum.redirect_tips') .'
			</div>
		</div>';
	}

	echo '</div></div>';
	echo '</form>';
	include ROOT_DIR . '/includes/Editors/editors.php';
	echo '<script>load_editor("editor", "bbcode");</script>';
}

echo '</div>'; //<div class="forum-wrapper">

