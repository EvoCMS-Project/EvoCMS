<div id="viewcontrols" class="btn-group float-end" role="group">
	<a style="margin-top: 4px;" href="<?=App::getURL('users', ['view' => $display, 'team' => !$team]) ?>"><?= $team ? __('users.title') : __('users.team') ?></a>
	<a class="btn btn-outline-secondary" aria-label="Left Align" title="<?= __('users.grid') ?>" href="<?=App::getURL('users', ['view' => 'grid', 'team' => $team]) ?>">
	  <i class="fa fa-th fa-lg"></i>
	</a>
	<a class="btn btn-outline-secondary" aria-label="Left Align" title="<?= __('users.list') ?>" href="<?=App::getURL('users', ['view' => 'list', 'team' => $team]) ?>">
	  <i class="fa fa-list fa-lg"></i>
	</a>
</div>
<legend><?= $team ? __('users.team') : __('users.title') ?></legend>
<div style="margin-bottom: 1em;">
	<form role="search" method="post">
		<input id="filter" name="filter" type="text" class="form-control" value="<?=html_encode(App::REQ('filter'))?>" placeholder="<?= __('users.search_placeholder') ?>">
	</form>
</div>
<div class="users-list" id="content">
<?php
	if ($display === 'list')
	{
		echo '<table class="table users-table"><thead><tr><th style="width:16px"></th>';

		foreach($columns as $label => $column) {
			if ($column === $sort) {
				echo '<th><a href="'.App::getURL(App::$REQUEST_PAGE, ['view'=>'list','sort'=>$column.'+desc']).'"><em>' . $label . '</em></th>';
			} else {
				echo '<th><a href="'.App::getURL(App::$REQUEST_PAGE, ['view'=>'list','sort'=>$column]).'">' . $label . '</th>';
			}
		}

		echo '<th style="width:5em;">Actions</th></tr></thead><tbody>';

		foreach($users as $user) {
			echo '<tr><td>'.get_avatar($user, 16).'</td>';

			foreach($columns as $label => $column) {
				switch ($column) {
					case 'username':
						echo '<td><a href="'.App::getURL('user', ['id'=>$user['username']]).'">'.html_encode($user['username']).'</a></td>';
						break;
					case 'gname':
						echo '<td class="group-color-'.$user['color'].'">'.html_encode($user['gname']).'</td>';
						break;
					default:
						echo '<td>' . html_encode($user[$column]) . '</td>';
				}
			}

			echo '<td class="text-end">
				  	<a href="'.App::getURL('mail', ['id' => $user['username']]).'" title="' . __('users.send_message') . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil-alt"></i></a>
				  </td></tr>';
		}

		echo '</tbody></table>';
	}
	else
	{
		echo '<div class="users-block text-center">';

		foreach($users as $user) {
			echo '<div class="user-block">';
			echo '    <p><a title="' . $user['gname'] . '" class="group-color-' . $user['color'] . '" href="'.App::getURL('user', ['id'=>$user['username']]).'">'.get_avatar($user).'<br>'.html_encode($user['username']).'</a>';

			if ($user['country']) {
				echo ' ' . Widgets::countryFlag($user['country']);
			}

			echo '</p>';
			echo $user['cmt'] . ' <i title="' . __('users.comments') . '" class="fa fa-comment"></i> ' . $user['fnd'] . ' <i title="' . __('users.friends') . '" class="fa fa-user"></i>';

			echo '</div>';
		}

		echo '</div>';
	}
?>

<?= $paginator ?>
</div>
