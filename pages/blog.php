<?php defined('EVO') or die('Que fais-tu là?');

$num_art = (int)Db::Get('select count(*) from {pages} where pub_date <= ? AND pub_rev > 0 AND type = ?', time(), 'article');

if (!$num_art) {
	return print '<div style="text-align: center;margin:20px;" class="alert alert-warning">'. __('blog.nothing') .'</div>';
}

$pn = (int)App::GET('pn', 0);
$start = App::getConfig('articles_per_page') * ($pn-1);
$ptotal = ceil($num_art / App::getConfig('articles_per_page'));
$home = true;

if ($start > $num_art || $start < 0) {
	$pn = 1;
	$start = 0;
}

$articles = Db::QueryAll(
	'SELECT r.*, p.*, a.username
     FROM {pages} AS p
     JOIN {pages_revs} as r ON r.page_id = p.page_id AND p.pub_rev = r.revision
     JOIN {users} as a ON a.id = r.author
     WHERE p.pub_date <= ? AND p.`type` = ?
	 ORDER BY sticky>0 desc, sticky asc, pub_date desc LIMIT ?, ?',
	time(), 'article', (int)$start, (int)App::getConfig('articles_per_page'));


echo '<div id="content">';

foreach($articles as $article)
{
	$article['content'] = markdown2html($article['content']);
	$article['abstract'] = preg_match('/^(.*)(<hr[^>]*>)/ms', $article['content'], $m) ? $m[1] : false;
	$article['page_link'] = App::getURL($article['slug'] ?: $article['page_id']);
	$article['author_link'] = App::getURL('user', ['id' => $article['author']]);
	$article['user_can_comment'] = $article['allow_comments'] == 1 && has_permission('user.comment_send');
	App::renderTemplate('pages/page_article.php', compact('article', 'home'));
}

echo Widgets::pager($ptotal, $pn, 10, App::getURL('page/'));
echo '</div>';
