<?php defined('EVO') or die('Que fais-tu là?');

use Evo\Models\File;

$pages = Db::QueryAll(
	'SELECT r.*, p.* FROM {pages} AS p
	 JOIN {pages_revs} AS r ON r.page_id = p.page_id AND r.revision = p.pub_rev
	 WHERE pub_date > 0 AND category LIKE ?
	 ORDER BY p.pub_date DESC',
	str_replace('-', '_', basename(App::$REQUEST_PAGE))
);

if ($pages) {
	$category = $pages[0]['category'];
	App::setTitle(ucwords($pages[0]['category']));
	App::setBodyClass('page-pagelist');

	foreach ($pages as &$page) {
		$page['content'] = markdown2html($page['content']);
		$page['link'] = $page['redirect'] ?: App::getURL($page['slug']);
		if ($page['image'] && $image = File::find($page['image'])) {
			$page['image'] = $image->getLink(250);
		} elseif ((preg_match('/<img[^>]+src="([^">]+)"/', $page['content'], $m)
			|| preg_match('/<video[^>]+poster="([^">]+)"/', $page['content'], $m))) {
			$page['image'] = $m[1];
		}
	}

	$output = App::renderTemplate('pages/category.php', compact('pages', 'category', 'page'), true);
	echo rewrite_links($output);
	return true;
}
