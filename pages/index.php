<?php defined('EVO') or die('Que fais-tu là?');

$home_page = App::getConfig('frontpage');

if (!file_exists(__DIR__ . "/$home_page.php")) {
	$id = $home_page;
	$home_page = 'pageview';
}

App::setBodyClass('page-'.$home_page);
require __DIR__ . "/$home_page.php";