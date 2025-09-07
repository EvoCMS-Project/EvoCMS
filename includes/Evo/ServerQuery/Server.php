<?php
namespace Evo\ServerQuery;

use \Exception;

class Server
{
	public static function __callStatic(string $call, array $args)
	{
		$args[] = $call;
		return call_user_func_array('self::query', $args);
	}


	public static function query(string $address, string $type)
	{
		if (method_exists('Evo\ServerQuery\GameServerQuery', 'query' . $type)) {
			[$host, $port] = explode(':', "$address:0");
			return GameServerQuery::{'query' . $type}($host, $port);
		} elseif (method_exists('Evo\ServerQuery\StreamingQuery', 'query' . $type)) {
			return StreamingQuery::{'query' . $type}($address);
		} else {
			throw new Exception('Server type unsupported!');
		}
	}


	public static function isOnline(string $address, string $type)
	{
		if ($type === 'minecraft' || !method_exists('Evo\ServerQuery\GameServerQuery', 'query' . $type)) { // No need for the full ping
			if ($parts = parse_url($address)) {
				if ($sock = @fsockopen($parts['host'] ?? $address, $parts['port'] ?? 80, $err, $errstr, 2)) {
					@fclose($sock);
					return true;
				}
			}
			return false;
		}

		return self::query($address, $type);
	}
}
