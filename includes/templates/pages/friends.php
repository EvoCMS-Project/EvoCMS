<legend><?= __('friend.add_title') ?></legend>
<form role="form" class="form-horizontal" method="post" style="margin: 20px;">
	<div class="form-group row">
		<label class="col-sm-3 control-label" for="query"><?= __('friend.add_uname') ?></label>
		<div class="col-sm-8 control">
			<input id="query" name="new_friend" class="form-control" type="text" data-autocomplete="userlist" placeholder="<?= __('friend.add_uname_ph') ?>">
		</div>
	</div>
	<div class="form-group row" hidden>
		<label class="col-sm-3 control-label" for="query"><?= __('friend.add_invite') ?></label>
		<div class="col-sm-8 control">
			<input name="invite_friend" class="form-control" type="text" data-autocomplete="userlist" placeholder="<?= __('friend.add_invite_ph') ?>">
		</div>
	</div>
	<div class="text-center">
		<button class="btn btn-medium btn-primary" type="submit"><?= __('friend.add_btn') ?></button>
	</div>
</form>

<?php if ($request_in): ?>
	<legend><?= __('friend.reqin_title') ?></legend>
	<table class="table friend_table">
		<form method='post'>
			<?php
				foreach($request_in as $id => $friend) {
					echo  '<tr>'.
							'<td><a class="ico-' . ($friend['activity'] > time() - 120 ? 'online" title="'. __('friend.reqin_online') .'"' : 'offline" title="'. __('friend.reqin_offline') .'"') . '></a></td>'.
							'<td class="text-left"><a href="' . App::getURL('user', $friend['id']) . '">' . $friend['username'] . '</a></td>'.
							'<td style=";color:' . $friend['gcolor'] . '">' . $friend['gname'] . '</td>'.
							'<td class="text-right">'.
								"<button name='accept_request' value='{$id}' title='". __('friend.btn_accept') ."' class='btn btn-success btn-sm'><i class='fa fa-check'></i></button> ".
								"<button name='del_request' value='{$friend['id']}' title='". __('friend.btn_denied') ."' class='btn btn-danger btn-sm'><i class='fa fa-times'></i></button ".
							'</td>'.
						'</tr>';
				}
			?>
		</form>
	</table>
<?php endif; ?>

<?php if ($friends): ?>
	<legend><?= __('friend.list_title') ?></legend>
	<table class="table friend_table">
		<form method='post'>
			<?php
				foreach($friends as $id => $friend) {
					$friend['username'] = html_encode($friend['username']);
					echo  '<tr>'.
							'<td><a class="ico-' . ($friend['activity'] > time() - 120 ? 'online" title="'. __('friend.reqin_online') .'"' : 'offline" title="'. __('friend.reqin_offline') .'"') . '></a></td>'.
							'<td class="text-left"><a href="' . App::getURL('user', $friend['id']) . '">' . $friend['username'] . '</a></td>'.
							'<td style="color:' . $friend['gcolor'] . '">' . $friend['gname'] . '</td>'.
							'<td class="text-right">'.
								'<a href="' . App::getURL('mail', $friend['username']) . '" title="'. __('friend.list_btn_msg') .'" class="btn btn-primary btn-sm"><i class="fa fa-pencil-alt"></i></a> '.
								'<button name="del_request" value="' . $friend["id"] .'" title="'. __('friend.list_btn_remove') .'" class="btn btn-danger btn-sm"><i class="far fa-trash-alt"></i></button>'.
							'</td>'.
						'</tr>';
				}
			?>
		</form>
	</table>
<?php else: ?>
	<div class="alert alert-info"><?= __('friend.none_friend') ?></div>
<?php endif; ?>


<?php if ($request_out): ?>
	<legend><?= __('friend.reqout_title') ?></legend>
	<table class="table friend_table">
		<form method='post'>
			<?php
				foreach($request_out as $id => $friend) {
					echo  "<tr>".
							'<td style="min-width: 25%;"><a href="' . App::getURL('user', $friend['id']) . '">' . $friend['username'] . '</a></td>'.
							'<td style="min-width:25%;color:' . $friend['gcolor'] . '">' . $friend['gname'] . '</td>'.
							'<td class="text-right">'.
								'<button name="del_request" value="' . $friend['id'] . '" title="'. __('friend.reqout_btn_denied') .'" class="btn btn-danger btn-sm"><i class="fa fa-times"></i></button>'.
							'</td>'.
						'</tr>';
				}
			?>
		</form>
	</table>
<?php else: ?>
	<div class="alert alert-info"><?= __('friend.none_reqout') ?></div>
<?php endif; ?>

