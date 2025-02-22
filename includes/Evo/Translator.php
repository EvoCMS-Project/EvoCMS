<?php
/**
 * Translation
 * Copyright (c) 2015, Evo-CMS
 *
 * Implements Symfony\Component\Translation\TranslatorInterface
 * dictionaries
 * Licensed under the MIT license:
 * 	http://opensource.org/licenses/MIT
 */

namespace Evo;
use Exception;

class Translator
{
	private $dictionaries = [];
	private $locale;
	private $fallbackLocales = [];
	private $locale_dir = [];
	private $defaultDomain = 'messages';

	public function __construct($locale, array $fallbackLocales = [], $locale_dir = [], $defaultDomain = 'messages')
	{
	//	if (empty($locale_dir) || !is_dir($locale_dir[0])) {
	//		throw new exception("Can't find locales: {$locale_dir[0]} does not exist!");
	//	}

		$this->locale_dir = (array)$locale_dir;
		$this->locale = $locale;
		$this->fallbackLocales = $fallbackLocales;
		$this->defaultDomain = $defaultDomain;
		// $this->setLocale($locale);
		// $this->setFallbackLocale($fallbackLocales);
	}


	public function addLocaleDir($locale_dir, $silent = false)
	{
		if (empty($locale_dir) || !is_dir($locale_dir)) {
			if ($silent) {
				return false;
			}
			throw new exception("Can't find locales: {$locale_dir} does not exist!");
		}

		$this->locale_dir[] = $locale_dir;
	}


    public function loadDictionary($domain = null, $locale = null, $reload = true)
    {
		$locale = $locale ?: $this->locale;

		if ($reload === false && isset($this->dictionaries[$locale][$domain])) {
			return false;
		}

		if (empty($this->locale_dir)) {
			throw new exception("Failed to load domain '$domain' for locale '$locale': Path not set !");
		}

		foreach($this->locale_dir as $locale_dir) {

			$path = rtrim($locale_dir, '/') . '/' . $locale . '/' . $domain . '.php';

			if (!is_readable($path)) {
				continue;
				// throw new exception("Failed to load domain '$domain' for locale '$locale': File not found in path '$path' !");
			}

			$messages = include $path;

			if (!is_array($messages)) {
				continue;
				// throw new exception("Failed to load domain '$domain' for locale '$locale': File didn't return an array !");
			}

			$this->addDictionary($messages, $domain, $locale);

			return true;
		}

		return false;
	}


	public function getDictionaries($locale = null, $load_missing = false)
	{
		$locale = $locale ?: $this->locale;

		if ($load_missing === true) {
			foreach($this->locale_dir as $locale_dir) {
				$path = rtrim($locale_dir, '/') . '/' . $locale . '/*.php';

				foreach(glob($path) as $dictionary) {
					$this->loadDictionary(basename($dictionary, '.php'), $locale, false);
				}
			}
		}

		if (!isset($this->dictionaries[$locale])) {
			throw new exception("The locale '$locale' isn't loaded or contains no dictionaries.");
		}

		return $this->dictionaries[$locale];
	}



	public function getAllDictionaries($load_missing = false)
	{
		foreach($this->getLocales(true) as $locale) {
			$this->getDictionaries($locale, $load_missing);
		}

		return $this->dictionaries;
	}



	private function dictionaryPresent($domain, $locale)
	{
		if (!isset($this->dictionaries[$locale][$domain])) {
			$this->dictionaries[$locale][$domain] = false; // To avoid retrying if loadDictionary fails
			$this->loadDictionary($domain, $locale);
		}
	}



	public function addDictionary(array $messages, $domain = null, $locale = null)
	{
		$locale = $locale ?: $this->locale;
		$domain = $domain ?: $this->defaultDomain;

		$this->dictionaries[$locale][$domain] = $this->flatten($messages);

		return true;
	}



    public function exportDictionary($domain = null, $locale = null)
    {
		$locale = $locale ?: $this->locale;
		$domain = $domain ?: $this->defaultDomain;

		$this->dictionaryPresent($domain, $locale);

		return $this->unflatten($this->dictionaries[$locale][$domain]);
	}



    public function saveDictionary($domain = null, $locale = null, $file = null)
    {
		$locale = $locale ?: $this->locale;
		$domain = $domain ?: $this->defaultDomain;

		$messages = $this->exportDictionary($domain, $locale);

		if (empty($this->locale_dir) && $file === null) {
			throw new exception('You must set the locale path or specify a filename to save the locale file!');
		}

		$path = $file ?: $this->locale_dir . '/' . $locale . '/' . $domain . '.php';

		print_r($messages);
	}



	public function getMessages($domain = null, $locale = null)
	{
		$locale = $locale ?: $this->locale;
		$domain = $domain ?: $this->defaultDomain;

		$this->dictionaryPresent($domain, $locale);

		return $this->dictionaries[$locale][$domain];
	}



	public function setMessage($id, $tranlation, $domain = null, $locale = null)
	{
		$locale = $locale ?: $this->locale;
		$domain = $domain ?: $this->defaultDomain;

		$this->dictionaryPresent($domain, $locale);

		$this->dictionaries[$locale][$domain] = $this->flatten($messages);

		return true;
	}



    public function setLocale($locale)
    {
		$this->loadDictionary($this->defaultDomain, $locale);
		$this->locale = $locale;

		return true;
    }



    public function getLocale()
    {
		return $this->locale;
    }



	public function getLocales($find_all = false, $assoc = false)
	{
		if ($find_all === true) {
			foreach($this->locale_dir as $locale_dir) {
				$path = rtrim($locale_dir, '/') . '/*/';

				foreach(glob($path) as $dictionary) {
					if ($assoc) {
						$pack = @include "$dictionary/index.php";
						$locales[basename($dictionary)] = $pack['native_name'];
					} else {
						$locales[] = basename($dictionary);
					}
				}
			}
			return $locales;
		}

		$locales   = $this->fallbackLocales;
		$locales[] = $this->locale;

		return $locales;
	}



	public function setFallbackLocale($locales)
	{
		foreach((array)$locales as $locale) {
			$this->loadDictionary($this->defaultDomain, $locale);
		}
		$this->fallbackLocales = (array)$locales;
	}



	public function get($id, array $parameters = [], $domain = null, $locale = null)
	{
		// if (($pos = strpos($id, '/')) !== false && $pos < strpos($id, '.')) {
		if ($domain === null && strpos($id, '/') !== false) {
			list($domain, $id) = explode('/', $id, 2);
		}

		$domain = $domain ?: $this->defaultDomain;
		$locale = $locale ?: $this->locale;
		$message = $id;

        $this->dictionaryPresent($domain, $locale);

		if (isset($this->dictionaries[$locale][$domain][$id])) {
			return strtr($this->dictionaries[$locale][$domain][$id], $parameters);
		}

		foreach($this->fallbackLocales as $fallback) {
			$this->dictionaryPresent($domain, $fallback);
			if (isset($this->dictionaries[$fallback][$domain][$id])) {
				$message = $this->dictionaries[$fallback][$domain][$id];
				break;
			}
		}

        return strtr($message, $parameters);
	}



	public function choice($id, $number, array $parameters = [], $domain = null, $locale = null)
	{
		$message = $this->get($id, [], $domain, $locale);
		$parameters['%count%'] = $number;
		$number = (int)$number;

		$parts = explode('|', $message);
		$count = count($parts);

		if ($count < 2) {
			throw new exception("Plural translation '$id' doesn't contain enough choices(requires 2+, contains $count)");
		}

		foreach($parts as $i => $part) {
			if (\preg_match('/^\{([^\}]+)\} (.+)$/msU', $part, $m)) {
				$numbers = array_map('trim', explode(',', $m[1]));
				if (in_array($number, $numbers)) {
					return strtr($m[2], $parameters);
				}
				/** Symfony's doc:
				You can also mix explicit math rules and standard rules.
				In this case, if the count is not matched by a specific interval,
				the standard rules take effect after removing the explicit rules:
				**/
				unset($parts[$i]);
			}
			elseif (preg_match('/^\[([0-9,\s]+(,\s*inf)?)\] (.+)$/msU', $part, $m)) {
				$numbers = array_map('trim', explode(',', $m[1]));

				if (count($numbers) !== 2 || $numbers[0] > $numbers[1]) {
					throw new exception("Plural translation'$id' has malformed bracket syntax []");
				}

				if ($m[2]) {
					$numbers[1] = INF;
				}

				if ($number >= $numbers[0] && $number <= $numbers[1]) {
					return strtr($m[3], $parameters);
				}
				unset($parts[$i]);
			}
		}

		$parts = array_values($parts); // Force reindex
		$count = count($parts);

		if ($count === 2) {
			if ($number == 1) {
				$number = 0;
			}
			if ($number > 1) {
				$number = 1;
			}
		}

		if ($count >= 3 && $number >= ($count - 1)) {
			$number = $count - 1;
		}

		if (!isset($parts[$number])) {
			throw new exception("Can't choose in plural translation'$id' for value '$number'");
		}

		return strtr($parts[$number], $parameters);
	}



	public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
	{
		return $this->choice($id, $number, $parameters, $domain, $locale);
	}



	public function trans($id, array $parameters = [], $domain = null, $locale = null)
	{
		return $this->get($id, $parameters, $domain, $locale);
	}



	public function has($id, $domain = null, $locale = null)
	{
		return $this->get($id, [], $domain, $locale) !== $id;
	}



    private function flatten(array $array, $prefix = null)
    {
		$out = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $out = $out + $this->flatten($value, $prefix === null ? $key : $prefix.'.'.$key);
            } elseif ($prefix !== null) {
                $out[$prefix.'.'.$key] = $value;
            } else {
				$out[$key] = $value;
			}
        }

		return $out;
    }



    private function unflatten(array $array)
    {
		$out = [];
		foreach($array as $k => $v) {
			$parts = explode('.', $k);
			$key = array_shift($parts);
			if (count($parts) === 0) {
				$out[$key] = $v;
			} else {
				if (!isset($out[$key])) {
					$out[$key] = [];
				}
				$out[$key] = $out[$key] + $this->unflatten([implode('.', $parts) => $v]);
			}
		}
		return $out;
    }
}
