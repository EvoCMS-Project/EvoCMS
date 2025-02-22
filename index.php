<?php
try {
	require_once 'includes/app.php';

	App::init();
	App::route('/page/(?<pn>[0-9]+)'           , function($e) { return 'pages/blog.php'; });
	App::route('/category/(?<id>.+)'           , function($e) { return 'pages/category.php'; });
	App::route('(?<path>/upload/.+)'           , function($e) { return 'pages/getfile.php'; });
	App::route('/(?<page>[^/]+)(?:/(?<id>.+))?', function($e) { return 'pages/'.$e['page'].'.php'; });
	App::route('/(?<id>.+)'                    , function($e) { return 'pages/pageview.php'; });
	App::route('/'                             , function($e) { return 'pages/index.php'; });
	App::run();
	App::render(IS_AJAX ? 'ajax.php' : '');
}
catch(Throwable $e) {
	App::render('error.php', compact('e'), 500);
}
