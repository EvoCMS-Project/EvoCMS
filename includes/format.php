<?php
class Format
{
	public static function size($size, $format = '%1.2f %s')
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$unit = 0;
		while ($size > 1024) {
			$unit++;
			$size /= 1024;
		}
		return sprintf($format, $size, $units[$unit]);
	}


	public static function truncate($string, $length)
	{
		if (function_exists('mb_substr') && mb_strlen($string) > $length) {
			$string = mb_substr($string, 0, $length - 3, 'UTF-8') . '...';
		} elseif (strlen($string) > $length) {
			$string = substr($string, 0, $length - 3) . '...';
		}

		return $string;
	}


	public static function stripAccents($string)
	{
		$a = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
		$b = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
		//$string = strtr(utf8_decode((string)$string), utf8_decode($a), $b);
		//return utf8_encode($string);
		$string = strtr(mb_convert_encoding((string)$string, "ISO-8859-1", "UTF-8"), mb_convert_encoding($a, "ISO-8859-1", "UTF-8"), $b);
		return mb_convert_encoding($string, "UTF-8", "ISO-8859-1");
	}


	public static function slug($title)
	{
		$title = self::stripAccents($title);
		$title = trim(strtolower($title));
		$title = preg_replace('#[^a-z0-9\\-/]#i', '-', $title);
		return trim(preg_replace('/-+/', '-', $title), '-/');
	}


	public static function safeFilename($filename)
	{
		$filename = self::stripAccents($filename);
		$filename = preg_replace('@[^-a-z0-9_/\.]@i', '_', $filename);
		$filename = preg_replace('@/\.+/@', '/', $filename);
		$filename = preg_replace('/([-\._\/])\\1+/', '$1', $filename);
		return trim($filename, '-_');
	}


	public static function today($timestamp, $showtime = false)
	{
		if (date('Ymd') === date('Ymd', $timestamp))
			$date = __('time.today');
		elseif (date('Ymd', time() - 24 * 3600) === date('Ymd', $timestamp))
			$date = __('time.yesterday');
		elseif ($timestamp == 0)
			$date = __('time.never');
		else
			$date = date('Y-m-d', $timestamp);

		if ($showtime)
			return __('time.at', ['%date%' => $date, '%time%' => date('H:i', $timestamp)]);
		else
			return $date;
	}
}
