<?php defined('EVO') or die('Que fais-tu là?');

use Evo\Models\File;

if (App::GET('id')) {
	$file = File::find(preg_replace('#/.+$#', '',  App::GET('id')), 'web_id');
} elseif (App::GET('path')) {
	$file = File::find(App::GET('path'), 'path');
}

if (empty($file) || !file_exists(ROOT_DIR . $file->path)) {
	throw new \PageNotFound(__('getfile.file_not_found'));
}

$serve_file = $file->path;
$serve_size = $file->size;

if ($size = App::GET('size', App::GET('thumb'))) {
	if (ctype_digit($size) && $size > 10) {
		$m = [$size, $size, $size];
	} elseif (!preg_match('#^([0-9]+)x([0-9]+)$#', $size, $m)) {
		$m = ['150x150', 150, 150];
	}

	if (strpos($file->mime_type, 'image/') !== 0) {
		$ext = pathinfo($file->name, PATHINFO_EXTENSION);
		$thumb = App::getAsset('/img/filetypes/'.$ext.'.png', true) ?:
		         App::getAsset('/img/filetypes/'.$file->type.'.png', true) ?:
				 App::getAsset('/img/filetypes/download.png', true);
		header('Content-Type: image/png');
		readfile($thumb);
		exit;
	}

	if ($path = $file->getThumbnail((int)$m[1], (int)$m[2], (bool)strpos($size, 'x'))) {
		$serve_file = substr($path, strlen(ROOT_DIR));
	}

	$serve_size = filesize(ROOT_DIR . $serve_file);
}
else {
	$file->hits = $file->hits + 1;
	$file->save();
}

header('Cache-Control: max-age=7200');
header('Expires: ' . date('r', time() + 7200));
header('Content-Type: ' . $file->mime_type);
header('Content-Length: ' . $serve_size);
header('Content-Disposition: inline; filename="' . $file->name . '"');

if ($serve_size <= 128*1024) { // If the file is small we serve it directly, the request is already open...
	ob_end_clean();
	readfile(ROOT_DIR . $serve_file);
} else {
	$base = preg_replace('#^(https?://|//)?([^/]*)#', '', App::getLocalURL('/'));
	header('Location: '. App::getLocalURL($serve_file));
	header('X-Accel-Redirect: ' . $base.$serve_file); // Let's save a client redirection if we use nginx :)
}

die;
