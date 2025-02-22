<?php defined('EVO') or die('Que fais-tu là?');

function queryServer(array $conditions = [], $cache = true)
{
	$begin = microtime(true);

	$where = ['where 1'];
	$values = [];

	foreach($conditions as $k => $v) {
		$where[] = Db::escapeField($k) . ' = ?';
		$values[] = $v;
	}

	$where_str = implode(' and ', $where);

	$server = Db::Get('select * from {servers} ' . $where_str, $values);

	if (!$server) {
		throw new Warning(__('server.not_found'));
	}

	try {
		$server['query'] = \Evo\ServerQuery\Server::query($server['address'], $server['type']);
	} catch(Exception $e) {
		$server['online'] = \Evo\ServerQuery\Server::isOnline($server['address'], $server['type']);
	}

	$server['query_time'] = number_format(microtime(true) - $begin, 4);

	return (object)$server;
}

$server = queryServer(['id' => App::GET('id')]);

if (!empty($server->query)) {
	foreach($server->query as $key => &$value) {
		if ($key == 'favicon' || $key == 'albumArt') {
			$value = str_replace("\n", '', $value);
		} elseif (!is_array($value)) {
			// sanitize?
		}
	}
}

App::renderTemplate('pages/server.php', compact('server'));
