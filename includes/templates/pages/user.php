<div class="row">
	<div class="col-sm-2 col-xs-2">
		<div><?= get_avatar($user_info, 100) ?></div>
	</div>
	<div class="col-sm-5 col-xs-3">
		<h4><?=ucfirst($user_info->username) ?></h4>
		<p style="margin: -5px 0 8px; " class="group-color-<?= $user_info->group->color ?>"><?= ucfirst($user_info->group->name) ?></p>
		<?= html_encode($ban_reason) ? '<span class="badge badge-danger">Membre bannis</span>' : ''?>
		<?php if (html_encode($user_info->website)) { ?>
			<a target="_blank" href="<?= html_encode($user_info->website) ?>">
				<span class="fa-stack fa-lg" title="<?= __('user.website') ?>">
					<i class="fa fa-square fa-stack-2x"></i>
					<i class="fa fa-home fa-stack-1x fa-inverse"></i>
				</span>
				<span class="sr-only"><?= __('user.website') ?></span>
			</a>
		<?php } ?>
		<?php foreach(Evo\Social::getProviders() as $network => [$name, $icon]) { ?>
			<?php if (!empty($user_info->social[$network])) { ?>
				<a target="_blank" href="<?= $user_info->social[$network] ?>">
					<span class="fa-stack fa-lg" title="<?= $name ?>">
						<i class="fa fa-square fa-stack-2x"></i>
						<i class="fab <?= $icon ?> fa-stack-1x fa-inverse"></i>
					</span>
					<span class="sr-only"><?= $name ?></span>
				</a>
			<?php } ?>
		<?php } ?>
	</div>
	<div class="col-sm-5 col-xs-3 text-right">
		<?php
		if (has_permission('admin.edit_uprofile')) {
			echo '<a class="btn btn-primary" title="' . __('user.edit') . '" href="'.App::getAdminURL('user_view', ['id' => $user_info->id]).'"><i class="fa fa-pencil-alt fa-2x"></i></a> ';
		}

		if (!$is_mine) {
			echo '<button class="btn btn-danger" title="' . __('user.report') . '" onclick="report(' . $user_info->id . ');"><i class="fa fa-flag fa-2x"></i></button> ';
		}

		if (has_permission('mod.ban_member')) {
			echo '<a class="btn btn-danger" title="' . __('user.ban') . '" href="' . App::getAdminURL('banlist', ['username' => $user_info->username]).'"><i class="fa fa-ban fa-2x"></i></a> ';
		}

		if ($can_mod) {
			echo '<a class="btn btn-warning" title="' . __('user.manage') . '" href="' . App::getAdminURL('users', ['filter' => 'username:'.$user_info->username]) . '"><i class="fa fa-user-secret fa-2x"></i></a> ';
		}
		?>
	</div>
</div>
<hr>
<div class="row text-center">
	<div class="col">
        <a title="<?= __('user.forum_posts') ?>" href="<?=App::getURL('forums', ['search'=>'','poster'=>$user_info->username]) ?>"><strong><?= $user_info->num_posts ?></strong> <i class="fa fa-pencil-alt"></i></a>
    </div>
    <div class="col">
        <a title="<?= __('user.comments') ?>"><strong><?= $num_comments ?></strong> <i class="fa fa-comment"></i></a>
    </div>
    <div class="col">
        <a title="<?= __('user.friends') ?>"><strong><?= $num_friends ?></strong> <i class="fa fa-user"></i></a>
    </div>
    <div class="col">
        <a title="<?= __('user.likes') ?>"><strong>N/A</strong> <i class="fa fa-heart"></i></a>
    </div>
</div>
<?php if (!$is_mine): ?>
	<hr>
	<div class="row">
		<div class="col-md-6">
			<a class="btn btn-success btn-sm btn-block" href="<?= App::getURL('mail/'.$user_info->username) ?>#mail"><i class="fa fa-envelope"></i> <?= __('user.send_message') ?></a>
		 </div>
		 <div class="col-md-6">
			<form method="post" action="<?= App::getURL('friends') ?>">
				<button class="btn btn-warning btn-sm btn-block" name="new_friend" value="<?= $user_info->username ?>"><i class="fa fa-user"></i> <?= __('user.add_friend') ?></button>
			</form>
		 </div>
	</div>
<?php endif; ?>
<br>
<div class="user-profile-content">
	<h6><strong><?= __('user.about_me') ?></strong></h6>
    <p><?= markdown2html($user_info->about, true, true) ?: __('user.info_unavailable') ?></p>
    <hr>
    <div class="row">
        <div class="col-sm-6">
			<h6><strong><i class="fa fa-globe"></i> <?= __('user.country') ?></strong></h6>
			<p style="margin-left: 30px">
			<?php
				if ($user_info->country)
					echo Widgets::countryFlag($user_info->country, true);
				else
					echo __('user.info_unavailable');
			?>
			</p><br>

			<?php if ($user_info->website): ?>
				<h6><strong><i class="fa fa-cloud"></i></strong> <?= __('user.website') ?></h6>
				<p style="margin-left: 30px"><a href="<?= html_encode($user_info->website) ?>"><?= html_encode($user_info->website) ?></a></p><br>
			<?php endif; ?>
        </div>
        <div class="col-sm-6">
			<h6><strong><i class="fa fa-calendar"></i></strong> <?= __('user.member_since') ?></h6>
            <p style="margin-left: 30px"><?= $user_info->registered > 0 ? date('Y-m-d @ H:i', $user_info->registered) : __('user.info_unavailable') ?></p><br>
			<h6><strong><i class="fa fa-clock-o"></i></strong> <?= __('user.last_activity') ?></h6>
            <p style="margin-left: 30px"><?= $user_info->activity > 0 ? date('Y-m-d @ H:i', $user_info->activity) : __('user.info_unavailable') ?></p><br>
        </div>
    </div>
</div>