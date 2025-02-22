<?php
namespace Evo\Models;
use \Db, \App, \UploadException, \Format;

class File extends \Evo\Model
{
	public const TABLE     = 'files';
	public const HAS_ONE   = ['poster' => ['poster', User::class]];
	public const SERIALIZE = ['thumbs'];


	public static function create($file, string $origin = null)
	{
		try {
			$file = is_string($file) ? $_FILES[$file] : $file;

			if (empty($file['tmp_name']) || empty($file['name']) || !file_exists($file['tmp_name'])) {
				throw new UploadException('Invalid input');
			}

			if ($file['size'] > get_effective_upload_max_size()) {
				throw new UploadException("Erreur d'upload: Fichier trop gros!");
			}

			$id = random_hash(6);
			$tmp_name = $file['tmp_name'];
			$size = filesize($tmp_name);
			$hash = md5_file($tmp_name);
			$orig_name = basename($file['name']);
			$ext = pathinfo($orig_name, PATHINFO_EXTENSION);
			$origin = $origin ?? 'unknown';
			$type = 'unknown';

			$name = "$id-" . substr(Format::safeFilename(basename($orig_name, ".$ext")), 0, 16) . ".$ext";
			$name = preg_replace('/\.(php|html?|js|htaccess|)$/i', '.txt', $name);

			$path = "/upload/$origin/$name";
			$realpath = ROOT_DIR . $path;

			foreach (explode("\n", App::getConfig('upload_groups')) as $line) {
				if (strpos($line, $ext)) {
					$type = preg_replace('/\s.+$/', '', $line);
					break;
				}
			}

			if (!in_array($ext, preg_split('/[\s,]+/m', App::getConfig('upload_groups'))) && !has_permission('admin.files')) {
				throw new UploadException("Format '$ext' n'est pas accepté !");
			}

			if (!is_dir(dirname($realpath))) {
				@mkdir(dirname($realpath), 0755, true);
				@touch(dirname($realpath) . '/index.html');
			}

			if (!@move_uploaded_file($file['tmp_name'], $realpath) && !@rename($file['tmp_name'], $realpath)) {
				throw new UploadException('Impossible de déplacer le fichier');
			}

			chmod($realpath, 0755);

			$file = new File([
				'web_id'    => $id,
				'name'      => Format::safeFilename($orig_name),
				'path'      => $path,
				'thumbs'    => [],
				'type'      => $type,
				'mime_type' => static::getMimeFromExt($ext),
				'size'      => $size,
				// 'img_size'  => getimagesize(),
				'md5'       => $hash,
				'poster'    => App::getCurrentUser()->id,
				'posted'    => time(),
				'caption'   => $orig_name,
				'origin'    => $origin,
			]);
			$file->save();

			App::logEvent(0, 'user', "Upload du fichier $path ($origin.$type)");

			return $file;
		}
		finally {
			if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
				@unlink($tmp_name);
			}
		}
	}


	public function attach(string $type, int $rel_id)
	{

	}


	protected function afterDelete()
	{
		foreach ($this->thumbs as $thumb) {
			@unlink(ROOT_DIR . '/' . $thumb);
		}
		@unlink(ROOT_DIR . '/' . $this->path);
	}


	protected function beforeSave(array &$modified)
	{
		if (isset($modified['name'])) {
			$ext = pathinfo($this->name, PATHINFO_EXTENSION);
			$name = trim(preg_replace("/\.$ext$/i", '', Format::safeFilename($modified['name']))) ?: 'untitled';
			$modified['name'] = "$name.$ext";
		}
		if (isset($modified['posted']) && !ctype_digit((string)$modified['posted'])) {
			$modified['posted'] = strtotime($modified['posted']);
		}
	}


	public function getThumbnail(int $width = 150, int $height = 150, bool $crop = true)
	{
		$filename = ROOT_DIR . '/' . $this->path;

		if (!file_exists($filename)) {
			return false;
		}

		$types = [1 => 'gif', 2 => 'jpeg', 3 => 'png'];
		list($fichier_larg, $fichier_haut, $fichier_type, $fichier_attr) = @getimagesize($filename);

		if (!isset($types[$fichier_type])) {
			return false;
		}

		$thumb_size = [max($width, 50), max($height, 50)];

		$tag = sprintf('%dx%dpx-%s', $thumb_size[0], $thumb_size[1], $crop ? 'cropped' : 'scaled');
		$thumb_path = ROOT_DIR . '/upload/thumbs/' . $this->web_id . '.' . $tag . '.' . $types[$fichier_type];

		if (file_exists($thumb_path)) {
			return $thumb_path;
		}

		if ($fichier_larg > $fichier_haut) {
			if ($crop) {
				$new_width = $thumb_size[0] * ($fichier_larg / $fichier_haut);
				$new_height = $thumb_size[1];
			} else {
				if ($thumb_size[1] < floor($thumb_size[0] * ($fichier_haut / $fichier_larg))) {
					sort($thumb_size);
				}

				$thumb_size[0] = $new_width = $thumb_size[0];
				$thumb_size[1] = $new_height = floor($thumb_size[0] * ($fichier_haut / $fichier_larg));
			}
		} else {
			if ($crop) {
				$new_width = $thumb_size[0];
				$new_height = $thumb_size[1] * ($fichier_haut / $fichier_larg);
			} else {
				if ($thumb_size[0] > floor($thumb_size[1] * ($fichier_larg / $fichier_haut))) {
					rsort($thumb_size);
				}
				$thumb_size[0] = $new_width = floor($thumb_size[1] * ($fichier_larg / $fichier_haut));
				$thumb_size[1] = $new_height = $thumb_size[1];
			}
		}

		if (!$fichier_source = call_user_func('imagecreatefrom' . $types[$fichier_type], $filename)) {
			return false;
		}

		$thumbnail = imagecreatetruecolor($thumb_size[0], $thumb_size[1]);
		imagealphablending($fichier_source, true);
		imagealphablending($thumbnail, false);
		imagesavealpha($thumbnail, true);

		@mkdir(dirname($thumb_path), 0755, true);
		imagecopyresampled(
			$thumbnail,
			$fichier_source,
			0 - ($new_width - $thumb_size[0]) / 2, // Center the image horizontally
			0 - ($new_height - $thumb_size[1]) / 2, // Center the image vertically
			0,
			0,
			$new_width,
			$new_height,
			$fichier_larg,
			$fichier_haut
		);

		if (call_user_func('image' . $types[$fichier_type], $thumbnail, $thumb_path) && chmod($thumb_path, 0744)) {
			$this->thumbs = [$tag => substr($thumb_path, strlen(ROOT_DIR))] + $this->thumbs;
			$this->save();
			return $thumb_path;
		}

		return false;
	}


	public function getLink($size = null): ?string
	{
		$args = ['id' => $this->web_id.'/'.$this->name];
		if ($size !== null) {
			$args['size'] = $size;
		}
		return App::getURL('getfile', $args);
	}


	public static function getMimeFromExt($ext)
	{
		static $mime_types = [];

		if (!$mime_types) {
			$lines = preg_split('/[\r\n]+\s*/', file_get_contents(ROOT_DIR . '/includes/lib-data/mime.types'));
			$lines = array_map(function ($v) {
				return trim(explode('#', $v)[0]);
			}, $lines);
			$lines = preg_replace('/\s+/ms', ' ', $lines);

			foreach ($lines as $line) {
				$parts = explode(' ', $line);
				$mime = array_shift($parts);
				foreach ($parts as $_ext) {
					$mime_types[$_ext] = $mime;
				}
			}
		}

		return $mime_types[strtolower($ext)] ?? 'application/octet-stream';
	}
}
