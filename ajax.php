<?php
require_once 'includes/app.php';

App::init();

switch (App::REQ('action')) {
	case 'preview':
		if (has_permission()) {
			if (App::REQ('format') === 'bbcode') {
				echo bbcode2html(App::REQ('text'));
			} else {
				echo markdown2html(App::REQ('text'));
			}
		}
		break;
	case 'filebox':
		if (has_permission()) {
			echo Widgets::filebox(App::GET('id'), App::GET('title'), App::GET('size'));
		}
		break;
	case 'servers':
		if ($servers = Db::QueryAll('SELECT * FROM {servers} ORDER BY name ASC')) {
			echo '<div>État des serveurs:</div>';
			echo '<table class="jeux">';
			foreach ($servers as $server) {
				if (\Evo\ServerQuery\Server::isOnline($server['address'], $server['type'])) {
					echo '<tr><td style="width: 190px;"><a href="' . App::getURL('server/' . $server['id']) . '">' . $server['name'] . '</a></td><td style="color: green; font-weight: bold;">' . $server['address'] . '</font></td></tr>';
				} else {
					echo '<tr><td style="width: 190px;">' . $server['name'] . '</td><td><font style="color: red; font-weight: bold;">' . $server['address'] . '</font></td></tr>';
				}
			}
			echo '</table>';
			echo '<script>setTimeout(30000, ServerPoll);</script>';
		}
		break;
	case 'userlist':
		if (!has_permission('user.view_uprofile')) break;

		$json = array();
		$req = Db::QueryAll(
			'SELECT a.username, a.activity, a.avatar, a.email, g.color, g.name as gname
							 FROM {users} as a
							 JOIN {groups} as g ON g.id = a.group_id
							 LEFT JOIN {friends} as f ON f.u_id = ? AND f.f_id = a.id
							 WHERE username LIKE ? OR email = ?
							 ORDER BY LOCATE(username, ?) ASC, f.id is null, a.activity DESC
							 LIMIT 10',
			App::getCurrentUser()->id,
			'%' . App::REQ('query') . '%',
			App::REQ('query'),
			App::REQ('query')
		);

		foreach ($req as $row) {
			$online = $row['activity'] > time() - 120 ? '<span style="font-size:x-small;">* (En ligne)</span>&nbsp;' : '';
			$json[] = array($row['username'], $row['username'] . '&nbsp;&nbsp;&nbsp;<span style="font-size:x-small;color:' . $row['color'] . '">(' . $row['gname'] . ')</span>&nbsp;&nbsp;' . $online, get_avatar($row, 85, true));
		}

		echo json_encode($json);
		break;

	case 'categorylist':
		if (has_permission('admin.manage_pages')) {
			echo json_encode(Db::QueryAll('SELECT DISTINCT category from {pages} WHERE category LIKE ? and category <> ""', '%' . App::REQ('query') . '%', true));
		}
		break;

	default:
		App::trigger('ajax', [App::REQ('action')]);
}
