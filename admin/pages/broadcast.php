<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.broadcast', true);
set_time_limit(0);

$subject = App::POST('sujet');
$message = App::POST('message');
$groups  = App::POST('groups');
$cycle   = App::POST('cycle');
$mail_id = 0;
$mail_targets = [];

if (IS_POST && (empty($subject) || empty($message) || empty($groups) || $cycle < 1)) {
	App::setWarning('Un des champs est vide.');
}
elseif (IS_POST && is_array($groups)) {
	$groups = array_map('intval', $groups);

	if ($groups === [0]) {
		$users = Db::QueryAll('select username, email from {users} where newsletter = 1');
	} else {
		$users = Db::QueryAll('select username, email from {users} where group_id in ('.implode(',', $groups).')');
	}

	Db::Insert('newsletter', [
			'author'      => App::getCurrentUser()->id,
			'date_sent'   => time(),
			'groups'      => implode(',', $groups),
			'subject'     => $subject,
			'message'     => $message,
			'mail_sent'   => 0,
			'mail_failed' => 0,
	]);

	$mail_id = Db::$insert_id;
	$mail_failed = $mail_sent = 0;

	$html_message = markdown2html($message);
	$text_message = strip_tags($message);

	App::logEvent(null, 'admin', 'Envoi de la newsletter #'.$mail_id.': '.$subject);

	// We should use bcc if no substitution (%username%) is needed. It will be faster and might
	// Work better if the smtp server limits mails per day

	foreach($users as $user) {
		$html = strtr($html_message, ['%username%' => $user['username']]);
		$text = strtr($text_message, ['%username%' => $user['username']]);

		if (App::sendmail($user['email'], $subject, $text, $html)) {
			$mail_targets[] = 'Mail envoyé à '.$user['username'].' &lt;'.$user['email'].'&gt;';
			$mail_sent++;
		} else {
			$mail_targets[] = 'Mail non envoyé à '.$user['username'].' &lt;'.$user['email'].'&gt; <span style="color:red">Erreur!</span>';
			$mail_failed++;
		}
	}

	if ($mail_failed) {
		App::setWarning("Envoi completé!\nÉchec d'envoi à ".__plural('%count% membre|%count% membres', $mail_failed));
	}

	if ($mail_sent) {
		App::setSuccess("Envoi completé!\nMessage envoyé à ".__plural('%count% membre|%count% membres', $mail_sent));
	}

	Db::Update('newsletter', ['mail_sent' => $mail_sent, 'mail_failed' => $mail_failed], ['id' => $mail_id]);
}

$preset = __('mail/wrapper', [
	'%message%'  => '',
	'%sitename%' => App::getConfig('name'),
	'%siteurl%'  => App::getConfig('url')
]);

$groups = [
	[
		'id' => 0,
		'name' => 'Newsletter',
		'cnt' => Db::Get('select count(*) from {users} where newsletter = 1'),
	]
];

$other_groups = Db::QueryAll('select g.*, count(*) as cnt from {users} join {groups} as g on g.id = group_id group by group_id order by priority asc');
$groups = array_merge($groups, $other_groups);

$gmap = [];
foreach($groups as $group) {
	$gmap[$group['id']] = $group['name'];
}
$letters = Db::QueryAll('select u.username, n.* from {newsletter} as n left join {users} as u on u.id = n.author order by date_sent desc');

$editors = [
	'wysiwyg'  => 'WYSIWYG',
	'markdown' => 'Markdown+',
];
?>

<?php if (!$mail_id) { ?>
	<legend>Envoi de mail de masse</legend>
	<form method="post">
	<input type="hidden" name="cycle" value="100">
	<div class="row">
		<div class="form-horizontal text-center col-sm-9">
			<div class="form-group row">
				<label class="col-sm-1 control-label" for="sujet">Sujet:</label>
				<div class="col-sm-12 control">
					<input id="sujet" name="sujet" class="form-control" type="text" maxlength="32" value="<?= $subject ?>">
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-1 control-label" for="id">Message:</label>
				<div class="col-sm-12 control">
					<textarea id="editor" name="message" class="form-control" style="height: 350px" placeholder="Composer un message..."><?= html_encode($message ?: nl2br($preset)) ?></textarea>
				</div>
			</div>
			<button class="btn btn-primary" type="submit">Envoyer le message</button>
		</div>

		<div class="col-sm-3">
			<table class="table table-lists" id="rcpt_groups">
				<thead>
					<th>Groupe</th>
					<th style="width:35%">Membres</th>
					<th style="width:10%"></th>
				</thead>
				<tbody>
				<?php
					foreach($groups as $group) {
						echo '<tr style="cursor: pointer"><td>' . html_encode($group['name']) . '</td><td>' . $group['cnt'] . '</td><td><input name="groups[]" type="checkbox" value="' . $group['id'] .'" id="rcpt_group'.$group['id'].'"></td></tr>';
					}
				?>
				</tbody>
			</table>
		</div>
	</div>
	</form>
<?php } else { ?>

<a href="#" onclick="$('#mailinglist').toggle()">Voir la liste de destinataires</a>
<div id="mailinglist" hidden><?= implode('<br>', $mail_targets) ?></div>

<?php } ?>
<div class="text-right">
	<a id="viewhistory" class="btn btn-info" href="#viewhistory">Voir l'historique</a>
</div>
<hr>

<div class="row" id="history" <?= $mail_id ? '' : 'style="display: none;"' ?>>
	<div class="col-sm-6">
		<div class="list-group" style="overflow-y: scroll; height:45vw">
		<?php
		foreach($letters as $letter) {
			$groups = array_intersect_key($gmap, array_flip(explode(',', $letter['groups'])));
			echo '
				<a href="#message-'.$letter['id'].'" class="list-group-item">
					<span class="badge">'.Format::today($letter['date_sent'], 'H:i').'</span>
					<h4 class="list-group-item-heading">'.html_encode($letter['subject']).'</strong></h4>
					<p class="list-group-item-text">'.
						'Groupes: <em>' . html_encode(implode(', ', $groups)).'</em> &nbsp; '.
						'Auteur: <em>' . html_encode($letter['username']) . '</em> &nbsp; '.
						'Envois: <em>' . (($letter['mail_sent'] - $letter['mail_failed']) . '/' . $letter['mail_sent']) . '</em> '.
					'</p>
				</a>';
		}
		?>
		</div>
	</div>
	<div class="col-sm-6">
	<?php
		foreach($letters as $letter) {
			echo '<div class="panel panel-default message" id="message-' . $letter['id'] . '" style="display: none;">';
			echo '<div class="panel-body">';
			echo markdown2html($letter['message']);
			echo '</div>';
			echo '</div>';
		}
	?>
	</div>
</div>
<?php include ROOT_DIR . '/includes/Editors/editors.php'; ?>
<script>
//<!--
$('.alert').removeClass('auto-dismiss');

$('#viewhistory').click(function() {
	$('#history').toggle();
});

$('#rcpt_groups > tbody tr').click(function() {
	$(this).find('input').click();
});

$('input').click(function(e) {
	if ($('#rcpt_groups input:checked').length == 0) {
		$('#rcpt_groups > tbody tr').show();
	} else if ($('#rcpt_groups input[value="0"]').prop('checked')) {
		$('#rcpt_groups > tbody tr').hide();
		$('#rcpt_groups > tbody tr:first').show();
	} else {
		$('#rcpt_groups > tbody tr').show();
		$('#rcpt_groups > tbody tr:first').hide();
	}
	e.stopPropagation();
});
$('#rcpt_groups > tbody tr:first').click();


$('#history .list-group-item').click(function() {
	$('#history .list-group-item').removeClass('active');
	$(this).addClass('active');
	$('#history .message').hide();
	$($(this).attr('href')).show();
	return false;
});

$('#history [href="#message-<?= $mail_id ?>"]').click();

load_editor('editor', '<?= App::getConfig('editor') ?>');
//-->
</script>
