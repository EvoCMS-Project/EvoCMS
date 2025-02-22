<?php defined('EVO') or die('Que fais-tu là?');
has_permission(null, true);

App::setTitle('Messagerie');

$current_user_id = App::getCurrentUser()->id;
$reply = $to = $mails = $highlight = 0;

$tab_mail = App::GET('id') !== null;

/* Supprimer un message */
if (App::POST('del_email')) {
	if (Db::Update('mailbox', ['deleted_snd' => 1], ['id' => App::POST('del_email'), 's_id' => $current_user_id])
		+ Db::Update('mailbox', ['deleted_rcv' => 1], ['id' => App::POST('del_email'), 'r_id' => $current_user_id]))
			App::setSuccess(__('mail.delete_success'));
	else
		App::setWarning(__('mail.delete_warning'));
}

/* Restorer un message */
if (App::POST('restore_email')) {
	if (Db::Update('mailbox', ['deleted_snd' => 0], ['id' => App::POST('restore_email'), 's_id' => $current_user_id])
		+ Db::Update('mailbox', ['deleted_rcv' => 0], ['id' => App::POST('restore_email'), 'r_id' => $current_user_id]))
		App::setSuccess(__('mail.restore_success'));
	else
		App::setWarning(__('mail.restore_warning'));
}

/* Envoyer un message */
if (App::POST('message')) {
	if (App::POST('id') > 0 && $mail = Db::Get('select * from {mailbox} where id = ?', App::POST('id'))) {
		$reply = $mail['reply'] ?: $mail['id'];
		$subject = strncasecmp($mails[0]['sujet'], 're :', 4) ? 'Re :'.$mail['sujet'] : $mail['sujet'];
		$to = $mail['s_id'] == $current_user_id ? $mail['r_id'] : $mail['s_id'];
	} else {
		$subject = App::POST('sujet');
		$to = App::POST('username');
	}

	if ($highlight = SendPrivateMessage($to, $subject, App::POST('message'), $reply)) {
		App::setSuccess(__('mail.send_success'));
	} else {
		App::setWarning(__('mail.send_warning'));
		$tab_mail = true;
	}
}

/* Ouvrir un message/discussion */
if (App::GET('id') && ctype_digit(App::GET('id'))) {
	if (App::getCurrentUser()->discuss) {
		$reply = Db::Get('select if(reply > 0, reply, id) from {mailbox} where id = ?', App::GET('id'));
	}

	$reply = $reply ?: -1;

	$mails = Db::QueryAll('SELECT mb.*, a.username, b.username as rcpt, a.avatar, a.ingame, a.email, g.color, g.name as gname
							FROM {mailbox} AS mb
							LEFT JOIN {users} AS a ON s_id = a.id
							LEFT JOIN {users} AS b ON r_id = b.id
							LEFT JOIN {groups} as g ON g.id = a.group_id
							WHERE (mb.id = ? or ? IN(mb.reply, mb.id)) AND ((mb.r_id = ? AND deleted_rcv =0) OR (mb.s_id = ? AND deleted_snd =0))
							ORDER BY mb.id ASC', App::GET('id'), $reply, $current_user_id, $current_user_id);

	Db::Exec('UPDATE {mailbox} SET viewed = ? WHERE (id = ? or reply = ?) and r_id = ? and (viewed = 0 or viewed is null)', time(), App::GET('id'), $reply, $current_user_id);

	if ($mails) {
		App::setTitle('Message de ' . $mails[0]['username']);
	}
} elseif (App::GET('id')) {
	echo '<script>$(function() { window.location.hash = "mail"; });</script>';
}

if (rand(0, 5) == 2) {
	Db::Exec('DELETE FROM {mailbox}
				WHERE posted < ? AND (
				(deleted_rcv = 1 AND deleted_snd = 1)
					OR (deleted_rcv = 1 AND s_id = r_id)
					OR (deleted_rcv = 1 AND (SELECT username from {users} WHERE id = s_id) IS NULL)
				)
	', time() - 14 * 24 * 3600);
}

$mail_inbox  = Db::QueryAll(
	'SELECT m.sujet, m.posted, m.id, m.viewed, m.type, a.username
		FROM {mailbox} as m
		LEFT JOIN {users} as a ON m.s_id = a.id
		WHERE deleted_rcv = 0  AND r_id = ?
		ORDER by m.id desc',
		$current_user_id
);
$mail_outbox = Db::QueryAll(
	'SELECT m.sujet, m.posted, m.id, m.viewed, a.username
		FROM {mailbox} as m
		LEFT JOIN {users} as a ON m.r_id = a.id
		WHERE deleted_snd = 0 AND s_id = ?
		ORDER by m.id desc',
		$current_user_id
);
$mail_trash  = Db::QueryAll(
	'SELECT m.sujet, m.posted, m.id, m.viewed, a.username as ru, b.username as su
		FROM {mailbox} as m
		LEFT JOIN {users} as a ON m.r_id = a.id
		LEFT JOIN {users} as b ON m.s_id = b.id
		WHERE (deleted_rcv = 1 AND r_id = ?) OR (deleted_snd = 1 AND s_id = ? )
		ORDER BY m.id desc',
		$current_user_id,
		$current_user_id
);

$participants = [];
$action = '';

if ($mails) {
	foreach($mails as $mail) {
		$participants[$mail['username']] = $mail['username'];
	}

	$reply = $mails[0]['reply'] ?: $mails[0]['id'];
	$highlight = count($mails) > 1 ? ($highlight ?: App::GET('id')) : 0;
	$action = count($mails) > 1 ? App::getURL('mail', [], '#mail') : App::getURL('mail');
}

App::renderTemplate('pages/mail.php', compact(
	'tab_mail',
	'participants',
	'reply',
	'highlight',
	'mail_inbox',
	'mail_outbox',
	'mail_trash',
	'mails',
	'action'
));
