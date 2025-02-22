<?php defined('EVO') or die('Que fais-tu là?');

$id = $id ?? App::GET('id');
$page = null;

if ($id) {
	if (App::REQ('rev') && has_permission('admin.manage_pages')) {
		if (App::REQ('rev') === 'last') {
			$rev = 'p.revisions';
		} else {
			$rev = (int)App::REQ('rev');
		}
	} else {
		$rev = 'p.pub_rev';
	}

	$page = Db::Get(
		"SELECT o.*, p.*, a.username
			FROM {pages} AS p
			JOIN {pages_revs} AS o ON o.page_id = p.page_id AND o.revision = $rev
			LEFT JOIN {users} AS a ON a.id = o.author
			WHERE ".(ctype_digit($id) ? 'p.page_id = ? ' : 'p.slug = ?'),
		$id
	);

	if (!$page) {
		$slug = Db::Get(
			'SELECT p.slug, MAX(r.id)
				FROM {pages} AS p
				JOIN {pages_revs} AS r USING(page_id)
				WHERE r.slug = ? OR r.slug = ?
				GROUP BY p.slug',
			$id, basename($id)
		);

		if ($slug && $slug['slug'] !== $id) {
			App::redirect($slug['slug']);
		}
	}
}


if (!is_array($page)) { /* Page not found */
	if ($id && (include 'category.php') === true) {
		return; // A category matched the slug, let's display that!
	}
	throw new PageNotFound(App::getLocalURL($_SERVER['REQUEST_URI']));
}

App::setTitle($page['title']);
$user_can_comment = $page['allow_comments'] == 1 && has_permission('user.comment_send');
$home = false;

$page['user_can_comment'] = $user_can_comment;
$page['page_link'] = App::getURL($page['slug'] ?: $page['page_id']);

/* Hit counter */
if (!isset($_SESSION['pageview'][$page['page_id']])) {
	Db::Update('pages', ['views' => $page['views'] + 1], ['page_id' => $page['page_id']]);
	$_SESSION['pageview'][$page['page_id']] = 1;
}



/* Nouveau commentaire */
if ($user_can_comment && App::POST('new_comment') && App::POST('commentaire')) {
	$username = preg_match('#^[^@\n<>]+$#', App::POST('name')) ? App::POST('name') : App::getCurrentUser()->username;
	$email = preg_match(PREG_EMAIL, App::POST('email')) ? App::POST('email') : App::getCurrentUser()->email;
	$user_id = App::getCurrentUser()->id;

	if (!has_permission() && (!App::POST('verif') || (int)App::POST('verif') !== ($page['id'] * 5))) {
		App::setWarning(__('pageview.warning_code_mismatch'));
	} else {
		Db::Insert('comments', array(
			'page_id'     => $page['page_id'],
			'user_id'     => $user_id,
			'message'     => App::POST('commentaire'),
			'posted'      => time(),
			'poster_name' => $username,
			'poster_email'=> $email,
			'poster_ip'   => $_SERVER['REMOTE_ADDR'],
			'state'       => 0
		));
		Db::Exec('update {pages} set comments = comments + 1 where page_id = ?', $page['page_id']);
		App::setSuccess(__('pageview.success_comment_saved'));
		App::logEvent(0, 'user', __('pageview.logevent_comment') .' #'.$page['page_id']. ': '.substr(App::POST('commentaire'), 0, 32).'.');
	}
}

if (App::POST('com_accept') && change_comment_state(App::POST('com_accept'), 1)) {
	App::setSuccess('Commentaire approuvé!');
}
if (App::POST('com_censure') && change_comment_state(App::POST('com_censure'), 2)) {
	App::setSuccess('Commentaire censuré!');
}
if (App::POST('com_delete') && change_comment_state(App::POST('com_delete'), -1)) {
	App::setSuccess('Commentaire supprimé!');
}


if (App::POST('report')) {
	Db::Insert('reports', array(
		'user_id' => App::getCurrentUser()->id,
		'type'    => 'comment',
		'rel_id'  => App::POST('pid'),
		'reason'  => App::POST('report'),
		'reported'=>  time(),
		'user_ip' => $_SERVER['REMOTE_ADDR'],
	));
	App::logEvent(0, 'user', __('pageview.logevent_comment_flagged') .' : '.$page['title']);
	exit;
}


/* comments */
$comments = Db::QueryAll (
	'SELECT coms.*, g.name as gname, g.color as gcolor, acc.username, acc.avatar, acc.ingame, acc.email
		FROM {comments} AS coms
		LEFT JOIN {users} AS acc ON acc.id = coms.user_id
		LEFT JOIN {groups} AS g ON acc.group_id = g.id
		WHERE coms.page_id = ? ORDER BY coms.posted ASC',
	 $page['page_id']
);
$page['comments'] = count($comments);

foreach($comments as &$comment) {
	$comment['message'] = markdown2html($comment['message'], true, true);
}


/* Page format */
$page['content'] = markdown2html($page['content']);
$page['author_link'] = App::getURL('user', $page['username']);
$page['abstract'] = '';


/* table of contents */
if ($page['display_toc'] && preg_match_all('#<h[1-3][^>]*>(.+)</h[1-3]>#miU', str_replace('&nbsp;', '', $page['content']), $m)) {
	$toc = '<div id="table-of-contents"><p>Contenu</p><ul>';
	$i = 0;

	foreach($m[1] as $j => $h) {
		$h = trim(strip_tags($h));
		if (empty($h)) continue;
		$id = preg_replace('#[^a-zA-Z0-9]#', '', $h);
		$search[] = $m[0][$j];
		$replacement[] = '<a name="' . $id . '"></a>' . $m[0][$j];
		$toc .= '<li><a href="#' . $id . '">' . ++$i . '. ' . $h . '</a></li>';
	}

	$toc .= '</ul></div>';

	if ($i > 0) {
		$page['content'] = $toc . str_replace($search, $replacement, $page['content']);
	}
}


/* bread crumbs */
if ($page['category'])
	App::addCrumb($page['category'], App::getURL('category/'.preg_replace('/[^a-z0-9-]/i', '-', strtolower($page['category']))));
else
	App::addCrumb(ucfirst($page['type']), '');
App::addCrumb($page['title'], App::getURL($page['slug'] ?: $page['page_id']));


App::trigger('page_display', array(&$page));


/* page content display */
switch($page['type']) {
	case 'article':
		App::renderTemplate('pages/page_article.php', ['article' => $page, 'home' => $home]);
		break;
	case 'page-blank':
		App::setTemplate('blank.php');
	case 'page-full':
		App::renderTemplate('pages/page_full.php', ['page' => $page, 'home' => $home]);
		break;
	default:
		App::renderTemplate('pages/page_page.php', ['page' => $page, 'home' => $home]);
		break;
}

App::setBodyClass('page-type-' . preg_replace('/^page-/', '', $page['type']));


if ($page['allow_comments']) {
	$captcha_code = str_pad($page['id'] * 5, 4, '0', STR_PAD_LEFT);
	App::renderTemplate('pages/page_comments.php', compact('page', 'comments', 'captcha_code'));
}
