<?php
try {
	define('EVO_ADMIN', 1);
	require_once '../includes/app.php';

	App::init();

	if (has_permission('moderator')) {
		App::route('/(?<page>[^/]+)', function($e) { return 'pages/'.$e['page'].'.php'; });
		App::route('/'              , function($e) { return 'pages/index.php'; });
		App::run();

		App::setVariables([
			'reports_nbr'      => Db::Get('select count(*) from {reports} where deleted = 0 or deleted is null'),
			'comments_nbr'     => Db::Get('select count(*) from {comments} where state = 0'),
			'update_available' => false,
		]);
		App::render(IS_AJAX ? 'ajax.php' : 'admin.php');
	} else {
		App::showError(new PermissionDenied());
		App::render('minimal.php');
	}
}
catch(Throwable $e) {
	App::render('error.php', compact('e'), 500);
}
