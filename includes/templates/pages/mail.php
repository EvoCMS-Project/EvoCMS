<ul class="nav nav-tabs">
	<li class="nav-item"><a class="nav-link <?=$tab_mail?:'active'?>"href="#inbox" data-bs-toggle="tab"><?= __('mail.tab_inbox') ?> <span class='badge badge-success'><?= count($mail_inbox) ?></span></a></li>
	<li class="nav-item"><a class="nav-link" href="#outbox" data-bs-toggle="tab"><?= __('mail.tab_outbox') ?> <span class='badge badge-info'><?= count($mail_outbox) ?></span></a></li>
	<li class="nav-item"><a class="nav-link" href="#trash" data-bs-toggle="tab"><?= __('mail.tab_trash') ?> <span class='badge badge-info'><?= count($mail_trash) ?></span></a></li>
	<li class="nav-item"><a class="nav-link <?=!$tab_mail?:'active'?>" href="#mail" data-bs-toggle="tab"><i class="fa fa-pencil-alt"></i> <?= __('mail.tab_composer') ?></a></li>
	<li class="nav-item"><a class="nav-link" href="<?=App::getURL('mail');?>"><i class="fa fa-sync"></i></a></li>
</ul>
<div class="tab-content">
<form method="post" class="tab-pane fade <?=$tab_mail?:'active show'?>" id="inbox" action="<?=App::getURL('mail');?>">
	<table class="table">
		<thead>
			<tr>
				<th style="width: 40px;"> </th>
				<th style="width: 130px;"><?= __('mail.inbox_sender') ?></th>
				<th><?= __('mail.inbox_subject') ?></th>
				<th><?= __('mail.inbox_date') ?></th>
				<th style="width: 85px;"> </th>
			</tr>
		</thead>
		<tbody>
			<?php
				foreach($mail_inbox as $mail)
				{
					echo '<tr>';
						echo '<td>';
							if ($mail['viewed']) {
								echo '<i title="'. __('mail.inbox_read') .'" class="fa fa-2x ' . MESSAGE_TYPES[$mail['type']]['icon.viewed'] . '">';
							} else {
								echo '<i title="'. __('mail.inbox_new') .'" class="fa fa-2x ' . MESSAGE_TYPES[$mail['type']]['icon.new'] . '">';
							}
						echo '</td>';
						echo '<td>' . html_encode($mail['username']) . '</td>';
						echo '<td>' . html_encode($mail['sujet']) . '</td>';
						echo '<td style="white-space:nowrap;">' . Format::today($mail['posted'], true) . '</td>';
						echo '<td class="text-right btn-group">';
							echo '<a href="' . App::getURL('mail', ['id' => $mail['id']], '#mail') . '" title="'. __('mail.inbox_view') .'" class="btn btn-primary btn-sm"><i class="far fa-eye fa-1"></i></a> ';
							echo "<button name='del_email' value='{$mail['id']}' title='". __('mail.inbox_delete') ."' class='btn btn-danger btn-sm'><i class='far fa-trash-alt'></i></button> ";
						echo '</td>';
					echo '</tr>';
				}
			?>
		</tbody>
	</table>
</form>
<form method="post" class="tab-pane fade" id="outbox" action="<?=App::getURL('mail')?>">
	<table class="table">
		<thead>
			<tr>
				<th><?= __('mail.outbox_sendto') ?></th>
				<th><?= __('mail.outbox_subject') ?></th>
				<th><?= __('mail.outbox_date_send') ?></th>
				<th><?= __('mail.outbox_date_view') ?></th>
				<th style="width: 81px"> </th>
			</tr>
		</thead>
		<tbody>
			<?php
				foreach($mail_outbox as $mailsent)
				{
					echo "<tr>";
						echo '<td style="white-space:nowrap;">' . html_encode($mailsent['username']) . '</td>';
						echo '<td>' . html_encode($mailsent['sujet']) . '</td>';
						echo '<td>' . Format::today($mailsent['posted']) . '</td>';
						echo '<td>' . Format::today($mailsent['viewed']) . '</td>';
						echo '<td class="btn-group"><a href="'.App::getURL('mail', ['id' => $mailsent['id'], '#mail']).'" title="'. __('mail.outbox_btn_view') .'" class="btn btn-primary btn-sm"><i class="far fa-eye fa-1"></i></a> ';
						echo "<button name='del_email' value='{$mailsent['id']}' title='". __('mail.outbox_btn_delete') ."' class='btn btn-danger btn-sm'><i class='far fa-trash-alt'></i></button></td>";
					echo "</tr>";
				}
			?>
		</tbody>
	</table>
</form>
<form method="post" class="tab-pane fade" id="trash" action="<?=App::getURL('mail')?>">
	<table class="table">
		<thead>
			<tr>
				<th> </th>
				<th><?= __('mail.trash_sendto') ?></th>
				<th><?= __('mail.trash_subject') ?></th>
				<th><?= __('mail.trash_date_send') ?></th>
				<th><?= __('mail.trash_date_view') ?></th>
				<th style="width: 81px"></th>
			</tr>
		</thead>
		<tbody>
			<?php
				foreach($mail_trash as $mail)
				{
					echo '<tr>';
						echo '<td style="width: 40px;">';
						if ($mail['viewed']) {
							echo '<i title="'. __('mail.trash_read') .'" class="far fa-2x fa-eye">';
						} else {
							echo '<i title="'. __('mail.trash_unread') .'" class="fa fa-2x fa-envelope">';
						}
						echo '</td>';
						echo '<td style="width:110px;">' . html_encode($mail['ru'] === App::getCurrentUser()->username ? $mail['su'] : $mail['ru']) . '</td>';
						echo '<td>' . html_encode($mail['sujet']) . '</td>';
						echo '<td>' . Format::today($mail['posted']) . '</td>';
						echo '<td>' . Format::today($mail['viewed']) . '</td>';
						echo '<td class="text-right">';
							echo "<button name='restore_email' value='{$mail['id']}' title='". __('mail.trash_restore') ."' class='btn btn-success btn-sm'><i class='fa fa-save'></i></button> ";
						echo '</td>';
					echo '</tr>';
				}
			?>
		</tbody>
	</table>
</form>
<div class="tab-pane fade <?=!$tab_mail?:'active show'?>" id="mail">

<?php if ($mails) { ?>

	<form action="<?=$action?>" method="post">
		<legend style="margin-bottom:5px;"><?= __('mail.party_subject') ?> : <i><?=html_encode($mails[0]['sujet'])?></i></legend>
		<div class="commentaires">
		<?php if (count($participants) > 1) {?>
			<span style="color:gray;"><strong><?= __('mail.party_members') ?> :</strong> <small><?=implode(', ', $participants)?></small></span>
		<?php } ?>
		<br>

		<?php foreach($mails as $message) { ?>
			<div class="commentaire<?=($highlight == $message['id'] ? ' highlight':'')?>" id="msg<?=$message['id']?>">
				<div class="avatar"><?=get_avatar($message)?></div>
				<div class="cadre_message">
					<div class="flag">
						<a style="color:#aaa;" href="<?=App::getURL('mail', ['id' => $message['id']], '#mail')?>">#<?=$message['id']?></a><br>
						<span class="badge badge-<?=MESSAGE_TYPES[$message['type']]['class']?>"><?=ucwords(MESSAGE_TYPES[$message['type']]['label'])?></span>
						<button type="submit" onclick="return confirm('Sur?');" name="del_email" value="<?=$message['id']?>" class="btn btn-sm btn-danger" title="Supprimer"><i class="far fa-trash-alt"></i></button>
					</div>
					<div class="auteur">
						<strong><a href="<?=App::getURL('user', ['id' => $message['s_id']])?>"><?=html_encode($message['username'])?></a></strong>
						(<span class="group-color-<?=$message['color']?>"><?=$message['gname']?></span>)
						 a dit <b><?=Format::today($message['posted'], true)?>:</b><br>@<?=$message['rcpt']?>
						<small> <b><?=$message['viewed'] ? 'l\'a lu ' . Format::today($message['viewed'], true) : 'ne l\'a pas lu'?></b></small>
					</div>
					<div class="message"><?= markdown2html($message['message'], true, true)?></div><hr>
				</div>
			</div>
		<?php } ?>

		</div>
	</form>

	<form method="post" action="<?=App::getURL('mail')?>">
		<div class="commentaires text-center">
		<textarea class="form-control" rows="5" id="editor" name="message" placeholder="<?= __('mail.party_reply') ?>..."></textarea><br>
		<button class="btn btn-success" name="id" value="<?=$reply?>" type="submit"><?= __('mail.party_send') ?></button>
		<a class="btn btn-danger" href="<?=App::getURL('mail')?>#mail"><?= __('mail.party_cancel') ?></a>
		</div>
	</form>

<?php } else { ?>

	<form method="post" action="<?=App::getURL('mail')?>">
		<legend><?= __('mail.composer_title') ?></legend>
		<div class="form-horizontal text-center">
		<div class="form-group row">
			<label class="col-sm-2 control-label" for="query"><?= __('mail.composer_to') ?> :</label>
			<div class="col-sm-8 control">
				<input id="query" name="username" class="form-control"  data-autocomplete="userlist" type="text" value="<?=html_encode(App::POST('username') ?: ((string)(int)App::REQ('id') === App::REQ('id') ? '' : App::REQ('id'))) ?>">
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-2 control-label" for="sujet"><?= __('mail.composer_subject') ?> :</label>
			<div class="col-sm-8 control">
				<input name="sujet" class="form-control" type="text" maxlength="32" value="<?=html_encode(App::POST('sujet'))?>">
			</div>
		</div>
		<textarea id="editor" class="form-control" name="message" rows="5" placeholder="<?= __('mail.composer_txt_ph') ?>..."><?=html_encode(App::POST('message'))?></textarea><br>
		<button class="btn btn-primary" type="submit" name="id" value=""><?= __('mail.composer_btn_send') ?></button>
		</div>
	</form>

<?php } ?>

</div>
</div>
<?php include ROOT_DIR . '/includes/Editors/editors.php'; ?>
<script>
load_editor('editor', 'markdown');
$('form a').click(function() {
	if (location.href == this.href && window.location.hash == this.hash) {
		// Bootstrap 5 tab activation
		var tabElement = document.querySelector('[data-bs-toggle="tab"][href="' + window.location.hash + '"]');
		if (tabElement) {
			var tab = new bootstrap.Tab(tabElement);
			tab.show();
		}
	}
});
</script>
