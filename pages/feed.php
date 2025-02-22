<?php defined('EVO') or die('Que fais-tu là?');

echo  '<?xml version="1.0" encoding="UTF-8"?>'.
		'<?xml-stylesheet type="text/css" href="' . App::getAsset('css/style.css') . '" ?>'.
		'<rss version="2.0"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:wfw="http://wellformedweb.org/CommentAPI/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:atom="http://www.w3.org/2005/Atom"
			xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
			xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
			>'.
			'<channel>'.
				'<title>' . App::getConfig('name') . '</title>'.
				'<link>' . App::getConfig('url') . '</link>'.
				'<description>' . App::getConfig('description') . '</description>'.
				'<lastBuildDate>' . date('r') . '</lastBuildDate>'.
				'<sy:updatePeriod>hourly</sy:updatePeriod>'.
				'<sy:updateFrequency>1</sy:updateFrequency>'.
				'<generator>Evo-CMS</generator>';


$sql = 'SELECT page.*, rev.*, a.username
		FROM {pages} AS page
		JOIN {pages_revs} as rev ON rev.page_id = page.page_id AND page.pub_rev = rev.revision
		JOIN {users} as a ON a.id = rev.author
		WHERE page.pub_date < '.time().'
		ORDER BY page.pub_date DESC LIMIT 0, 20';

foreach(Db::QueryAll($sql) as $page)
{
	echo '<item>'.
			'<title>' . $page['title'] . '</title>'.
			'<link>' . App::getURL($page['slug'] ?: $page['id']) . '</link>'.
			'<pubDate>' . date('r', $page['pub_date']) . '</pubDate>'.
			'<lastBuildDate>' . date('r', $page['posted']) . '</lastBuildDate>'.
			'<dc:creator><![CDATA[' . $page['username'] . ']]></dc:creator>'.

			'<guid isPermaLink="false">' . App::getURL($page['id']) . '</guid>'.
			'<description>' . html_encode('<div class="article message">'.markdown2html($page['content']).'</div>') . '</description>'.
			'<content:encoded>' . html_encode('<div class="article message">'.markdown2html($page['content']).'</div>') . '</content:encoded>'.
		 '</item>';
}

echo	'</channel>'.
	'</rss>';

exit;