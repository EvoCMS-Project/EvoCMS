<?php
namespace Evo;
use \App;

class Avatars
{
	private static $providers = [];

	public static function getAvatar(array $attributes, int $size = 85, bool $url_only = false)
	{
		$providers = self::getProviders();

		[$provider, $param] = explode(':', $attributes['avatar'] ?? 'default', 2) + ['', ''];

		if (isset($providers[$provider])) {
			$url = $providers[$provider]($attributes, $param, is_int($size) ? $size : 85);
		}

		if (empty($url)) {
			$url = App::getAsset('/img/avatar.png');
		}

		if ($url_only) {
			return $url;
		}

		return '<img src="' . $url . '" alt="avatar" class="avatar" height="'.$size.'" width="'.$size.'">';
	}

	public static function getProviders($enabled_only = true)
	{
		$providers = self::$providers + [
			'file' => function($user, $param, $size = 85) {
				if (is_file(ROOT_DIR . $param)) {
					return App::getAsset($param);
				}
			},
			'user' => function($user, $param, $size = 85) {
				if ($param) {
					return App::getURL('getfile', ['id' => $param, 'size' => $size]);
				}
			},
			'gravatar' => function($user, $param, $size = 85) {
				if (!empty($user['email'])) {
					return '//www.gravatar.com/avatar/' . md5($user['email']) . '?s=' . $size;
				}
			},
			'minecraft' => function($user, $param, $size = 85) {
				if (!empty($user['ingame'])) {
					return 'https://minotar.net/avatar/' . urlencode($user['ingame']) . '/' . $size . '.png';
				}
			}
		];

		if ($enabled_only) {
			foreach($providers as $key => $provider) {
				if (App::getConfig("providers.avatar.$key", true) == 0) {
					unset($providers[$key]);
				}
			}
		}

		return $providers;
	}

	public static function addProvider($key, callable $callback)
	{
		self::$providers[$key] = $callback;
	}

	public static function getProvider($key)
	{
		$providers = self::getProviders();
		return $providers[$key];
	}
}
