<a name="comments"></a>
<div class="commentaires">
	<?php if ($comments) { ?>
		<legend><?= __('blog.comment_title') ?></legend>
		<form method="post">

		<?php foreach($comments as $comment) { ?>
			<div class="commentaire" id="msg<?=$comment['id']?>">
				<div class="avatar"><?=get_avatar($comment['user_id'] ? $comment : array('avatar' => 'gravatar', 'email' => $comment['email']))?></div>
				<div class="cadre_message">
					<div class="auteur">
						<div class="float-right date text-right">
							<small><a href="#msg<?=$comment['id']?>" style="color:inherit"><?=Format::today($comment['posted'], 'H:i')?></a><br></small>
							<div class="flag btn-group">
								<button class="btn btn-sm btn-danger" onclick="return report(<?=$comment['id']?>);" title="<?= __('blog.comment_report') ?>"><i class="fa fa-flag"></i></button>
							<?php if (has_permission('mod.comment_censure')) { ?>
								<?php if ($comment['state'] == 2) {?>
									<button class="btn btn-sm btn-success" name="com_accept" onclick="return confirm('Sur?');" value="<?=$comment['id']?>" title="<?= __('blog.comment_accept') ?>"><i class="fa fa-check"></i></button>
								<?php } else { ?>
									<button class="btn btn-sm btn-warning" name="com_censure" onclick="return confirm('Sur?');" value="<?=$comment['id']?>" title="<?= __('blog.comment_censor') ?>"><i class="fa fa-ban"></i></button>
								<?php } ?>
							<?php } ?>
							<?php if (has_permission('mod.comment_delete')) { ?>
								<button class="btn btn-sm btn-danger" name="com_delete" onclick="return confirm('Sur?');" value="<?=$comment['id']?>" title="<?= __('blog.comment_delete') ?>"><i class="fa fa-times"></i></button>
							<?php } ?>
							</div>
						</div>

						<?php if ($comment['username']) { ?>
							<a href="<?=App::getURL('user', ['id' => $comment['user_id']])?>"><strong><?=$comment['username']?></strong></a>
						<?php } else { ?>
							<strong><?=$comment['poster_name']?></strong>
						<?php } ?>
						<br>
						<span style="color:<?=$comment['gcolor']?>;"><?=$comment['gname']?></span>
					</div>
					<div class="comment">
						<?php if($comment['state'] == 2) { ?>
							<div style='text-align: center;' class='alert alert-danger'><?= __('blog.comment_alert') ?></div>
						<?php } else { ?>
							<?= $comment['message'] ?>
						<?php } ?>
					</div>
				</div>
			</div>
		<?php } ?>
		</form>
	<?php } ?>

	<?php if ($page['user_can_comment']) { ?>
		<form method="post" action="#">
		<?php if (has_permission()) { ?>
			<legend><?= __('blog.comment_new') ?></legend>
			<textarea id="editor" class="form-control" name="commentaire" placeholder="<?= __('blog.comment_field_ph') ?>" maxlength="1024" rows="3"></textarea>
			<div style="text-align:right;margin-top: 10px;">
				<input class="btn btn-success" name="new_comment" type="submit" value="<?= __('blog.comment_btn_send') ?>">
			</div>
		<?php } else { ?>
			<legend><a href="<?=App::getURL('login', ['redir' => $page['page_id']])?>"><?= __('blog.comment_login') ?> : </legend>
			<div class="input-group">
				<div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-user"></i></span><span class="visually-hidden"><?= __('blog.comment_name') ?></span></div>
				<input type="text" name="name" class="form-control" placeholder="Votre nom (optionnel)">
				<div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-envelope"></i></span><span class="visually-hidden"><?= __('blog.comment_email') ?></span></div>
				<input type="text" name="email" class="form-control" placeholder="Votre email (optionnel)">
			</div>
			<br>
			<textarea id="editor" class="form-control" name="commentaire" placeholder="<?= __('blog.comment_field_ph') ?>" maxlength="1024" rows="3"></textarea>
			<br>
			<div class="input-group mb-3">
				<div class="input-group-prepend"><span class="input-group-text"><?= __('blog.comment_captcha') ?> : <strong><?=$captcha_code?></strong></span></div>
				<input class="form-control" type="text" name="verif" maxlength="8" value="">
			</div>
			<div class="text-center">
				<input class="btn btn-success" name="new_comment" type="submit" value="<?= __('blog.comment_btn_send') ?>">
			</div>
		<?php } ?>
		</form>
		<?php include ROOT_DIR . '/includes/Editors/editors.php'; ?>
		<script>load_editor('editor', 'markdown');</script>
	<?php } ?>
</div>