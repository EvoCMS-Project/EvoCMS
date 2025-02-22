<?php
/**
 * Translation
 * Copyright (c) 2015, Evo-CMS
 *
 * Pretty static façade for Translator
 *
 * Licensed under the MIT license:
 * 	http://opensource.org/licenses/MIT
 */

namespace Evo;
use Exception;

class Lang
{
	private static $translator;


	public static function setTranslator(Translator $translator)
	{
		self::$translator = $translator;
	}


	public static function getTranslator()
	{
		return self::$translator;
	}


	public static function __callStatic($name, array $args)
	{
		if (!isset(self::$translator)) {
			throw new exception('You must first set the translator with setTranslator');
		}
		return call_user_func_array([self::$translator, $name], $args);
	}
}
