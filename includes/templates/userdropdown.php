<?php
$friend_requests = Db::Get('SELECT COUNT(*) FROM {friends} WHERE state = 0 AND f_id = ?', [App::getCurrentUser()->id]);
$new_messages = Db::Get('SELECT COUNT(*) FROM {mailbox} WHERE viewed IS NULL AND deleted_rcv = 0 AND r_id = ?', [App::getCurrentUser()->id]);
$alerts = $friend_requests + $new_messages;
$extra_menu_items = [];

App::trigger('user_menu', [&$extra_menu_items]);

?><div class="dropdown" id="user-dropdown">
	<a href="<?=App::getURL('user');?>" class="user-dropdown__toggle" data-hover="dropdown" aria-haspopup="true">
		<span class="user-dropdown__label">
			<span class="account"><?= __('userdropdown.account') ?> (</span>
			<span class="user-dropdown__name"><?= html_encode(App::getCurrentUser()->username) ?></span>
			<span class="account">)</span>
		</span>
		<?php if ($alerts): ?>
		<span class="user-dropdown__badge">
			<?= fa_icon_html('fa-bell', 'solid', [], ['aria-hidden' => 'true']) ?>
			<span class="user-dropdown__badge-count"><?= (int) $alerts ?></span>
		</span>
		<?php endif; ?>
		<?= fa_icon_html('fa-chevron-down', 'solid', [], ['aria-hidden' => 'true'], 'user-dropdown__chevron') ?>
	</a>
	<div id="userdropdown" class="dropdown-menu" role="menu" style="margin-top: -4px;">
		<?php
		echo '<a class="dropdown-item" href="'.App::getURL('profile').'">' . fa_icon_html('fa-pencil') . ' '.__('userdropdown.profil').'</a>';

		echo '<a class="dropdown-item" href="'.App::getURL('friends').'">' . fa_icon_html('fa-user') . ' '.__('userdropdown.friends');
		echo ' <span class="badge badge-dark">'. ltrim($friend_requests, '0') . '</span>';
		echo "</a>";

		echo '<a class="dropdown-item" href="'.App::getURL('mail').'">' . fa_icon_html('fa-envelope') . ' '.__('userdropdown.mailbox');
		echo ' <span class="badge badge-dark">'. ltrim($new_messages, '0') . '</span>';
		echo '</a>';

		if (has_permission('user.upload')) {
			echo '<a class="dropdown-item" href="'.App::getURL('gallery').'">' . fa_icon_html('fa-download') . ' '.__('userdropdown.cloud').'</a>';
		}

		if (has_permission('user.invite')) {
			echo '<a class="dropdown-item" href="'.App::getURL('invite').'">' . fa_icon_html('fa-sitemap') . ' '.__('userdropdown.raf').'</a>';
		}

		foreach($extra_menu_items as $key => list($label, $icon, $link)) {
			echo '<a class="dropdown-item" href="'.$link.'">' . fa_icon_html($icon) . ' '.$label.'</a>';
		}

		echo '<div class="dropdown-divider"></div>';

		if (has_permission('moderator')) {
			echo '<a class="dropdown-item" href="' . App::getAdminURL() . '">' . fa_icon_html('fa-gear') . ' '.__('userdropdown.admin').'</a>';
		}
		?>
		<a class="dropdown-item" href="<?=App::getURL('login', ['action'=>'logout'])?>"><?= fa_icon_html('fa-power-off') ?> <?= __('userdropdown.logout') ?></a>
	</div>
</div>
