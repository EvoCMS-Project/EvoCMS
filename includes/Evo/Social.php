<?php
namespace Evo;
use \App;

class Social
{
	private static $providers = [
        'facebook' => ['Facebook', 'fa-facebook-f', '#^https://(www\.)?facebook\.com/.+$#i'],
        'twitter'  => ['Twitter',  'fa-twitter',    '#^https://(www\.)?twitter\.com/.+$#i'],
        'instagram'=> ['Instagram','fa-instagram',  '#^https://(www\.)?instagram\.com/.+$#i'],
        'discord'  => ['Discord',  'fa-discord',    '#^https://(www\.)?discord\.gg/.+$#i'],
        'skype'    => ['Skype',    'fa-skype',      '#^skype:[^?;]+$#i'],
        'twitch'   => ['Twitch',   'fa-twitch',     '#^https://(www\.)?twitch\.tv/.+$#i'],
        'youtube'  => ['YouTube',  'fa-youtube',    '#^https://(www\.)?(youtube\.com|youtu\.be)/channel/.+$#i'],
        'reddit'   => ['Reddit',   'fa-reddit',     '#^https://(www\.)?(reddit\.com)/(u|r|user)/.+$#i'],
    ];

	public static function getProviders($user_enabled_only = true)
	{
        $providers = self::$providers;

        if ($user_enabled_only) {
			foreach($providers as $key => $provider) {
				if (!App::getConfig("providers.social.$key", 1)) {
					unset($providers[$key]);
				}
			}
		}

		return $providers;
	}

	public static function addProvider($key, $name, $icon, $validation)
	{
		self::$providers[$key] = [$name, $icon, $validation];
	}

	public static function getProvider($key)
	{
		return self::getProviders(false)[$key] ?? null;
	}
}
