<?php
$friend_requests = Db::Get('SELECT COUNT(*) FROM {friends} WHERE state = 0 AND f_id = ?', [App::getCurrentUser()->id]);
$new_messages = Db::Get('SELECT COUNT(*) FROM {mailbox} WHERE viewed IS NULL AND deleted_rcv = 0 AND r_id = ?', [App::getCurrentUser()->id]);
$alerts = $friend_requests + $new_messages;
$extra_menu_items = [];

App::trigger('user_menu', [&$extra_menu_items]);

?><div class="dropdown" id="user-dropdown">
	<a href="<?=App::getURL('user');?>" class="btn btn-default" data-hover="dropdown"><span class="account"><?= __('userdropdown.account') ?> (</span><strong><?= App::getCurrentUser()->username ?></strong><span class="account">)</span>
	<?php if ($alerts) echo ' <i class="fa fa-bell"></i> '. $alerts ?></a>
	<button type="button" class="btn btn-default dropdown-toggle dropdown-toggle-split account" data-hover="dropdown">
		<span class="sr-only">Toggle Dropdown</span>
	</button>
	<div id="userdropdown" class="dropdown-menu" role="menu" style="margin-top: -4px;">
		<?php
		echo '<a class="dropdown-item" href="'.App::getURL('profile').'"><i class="fa fa-pencil-alt"></i> '.__('userdropdown.profil').'</a>';

		echo '<a class="dropdown-item" href="'.App::getURL('friends').'"><i class="fa fa-user"></i> '.__('userdropdown.friends');
		echo ' <span class="badge badge-dark">'. ltrim($friend_requests, '0') . '</span>';
		echo "</a>";

		echo '<a class="dropdown-item" href="'.App::getURL('mail').'"><i class="fa fa-envelope"></i> '.__('userdropdown.mailbox');
		echo ' <span class="badge badge-dark">'. ltrim($new_messages, '0') . '</span>';
		echo '</a>';

		if (has_permission('user.upload')) {
			echo '<a class="dropdown-item" href="'.App::getURL('gallery').'"><i class="fa fa-download"></i> '.__('userdropdown.cloud').'</a>';
		}

		if (has_permission('user.invite')) {
			echo '<a class="dropdown-item" href="'.App::getURL('invite').'"><i class="fa fa-sitemap"></i> '.__('userdropdown.raf').'</a>';
		}

		foreach($extra_menu_items as $key => list($label, $icon, $link)) {
			if (!preg_match('/^fa[rsb] /', $icon)) {
				$icon = "fa $icon";
			}
			echo '<a class="dropdown-item" href="'.$link.'"><i class="fa '.$icon.'"></i> '.$label.'</a>';
		}

		echo '<div class="dropdown-divider"></div>';

		if (has_permission('moderator')) {
			echo '<a class="dropdown-item" href="' . App::getAdminURL() . '"><i class="fa fa-cog"></i> '.__('userdropdown.admin').'</a>';
		}
		?>
		<a class="dropdown-item" href="<?=App::getURL('login', ['action'=>'logout'])?>"><i class="fa fa-power-off"></i> <?= __('userdropdown.logout') ?></a>
	</div>
</div>