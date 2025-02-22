<?php defined('EVO') or die('Que fais-tu là?');
has_permission('user.invite', true);

$raf_url = App::getURL('register', ['raf' => App::getCurrentUser()->getInviteToken((bool)App::POST('renew'))]);
$users = Db::QueryAll(
    'SELECT u.*, g.name as gname, g.color as color
     FROM {users} as u
     LEFT JOIN {groups} as g ON g.id = u.group_id
     WHERE u.raf = ?
     ORDER BY group_id DESC',
     App::getCurrentUser()->username);

App::renderTemplate('pages/invite.php', compact('users', 'raf_url'));