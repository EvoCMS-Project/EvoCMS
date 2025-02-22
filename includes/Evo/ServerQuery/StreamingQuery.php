<?php
namespace Evo\ServerQuery;

class StreamingQuery
{
	public static function querySHOUTcast(string $address)
	{
		$keys = [
			'currentListeners',
			'streamStatus',
			'peakListeners',
			'maxListerners',
			'uniqueListeners',
			'bitrate',
			'songTitle'
		];

		if (!preg_match('#^https?://#i', $address)) {
			return false;
		}

		$address = preg_replace('#(/.+\.html|/stream|/)$#i', '', $address);

		if ($buffer = @file_get_contents("$address/7.html", 0, self::HttpContext())) {
			$stats = explode(',', trim(strip_tags($buffer)), 7);
			$return = [];

			while ($stats) {
				$return[array_shift($keys)] = array_shift($stats);
			}

			// if (isset($return['songTitle'])) {
			$return['songTitle'] = str_replace('_', ' ', $return['songTitle']);
			// $return['songMeta']  = self::SongQuery($return['songTitle']);

			// if (!empty($return['songMeta']['thumb'])) {
			// $return['albumArt'] = $return['songMeta']['thumb'];
			// }
			// }
			return $return;
		}
		return false;
	}



	public static function SongQuery(string $title, bool $cached_only = false)
	{
		if ($buffer = @file_get_contents('http://www.discogs.com/search/ac?q=' . urlencode($title) . '&type=a_m_r_13', 0, self::HttpContext())) {
			return reset(json_decode($buffer, true));
		}
		// if ($itunes = @file_get_contents('https://itunes.apple.com/search?media=music&limit=1&term=' . urlencode($return['songTitle']))) {
		// $json = json_decode($itunes, true);
		// if (!empty($json['results'][0]['artworkUrl100'])) {
		// $return['albumArt'] = $json['results'][0]['artworkUrl100'];
		// $return['songMeta'] = $json['results'][0];
		// }
		// }
	}


	public static function HttpContext()
	{
		$opts = array(
			'http' => array(
				"method" => "GET",
				"header" => "Accept-language: en\r\n" .
					"User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13)\r\n" .
					"HTTP_X_REQUESTED_WITH: xmlhttprequest\r\n",
				'timeout' => 15,
			),
			'ssl' => array(
				'verify_peer' => false,
				'allow_self_signed' => true
			)
		);

		return stream_context_create($opts);
	}
}
