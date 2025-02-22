<legend><?= __('raf.your_link') ?></legend>
<form class="form-horizontal" method="post">
	<div class="input-group" style="margin: 1em;">
		<input class="form-control" value="<?= html_encode($raf_url) ?>" onclick="this.setSelectionRange(0, this.value.length)" readonly style="cursor: pointer;">
		<div class="input-group-btn">
			<button
				class="btn btn-primary"
				type="submit"
				style="font-size: initial"
				name="renew"
				onclick="return confirm('<?=addslashes(__('raf.renew_confirm')) ?>');"
				value="1"><i class="fa fa-sync"></i> <?= __('raf.renew') ?></button>
		</div>
	</div>
</form>

<div>&nbsp;</div>

<legend><?= __('raf.your_recruits') ?></legend>
<form class="form-horizontal" method="post">
<table class=" table friend_table">
	<thead>
		<th></th>
		<th><?= __('raf.username') ?></th>
		<th><?= __('raf.rank') ?></th>
		<th><?= __('raf.joined') ?></th>
	</thead>
		<tbody>
			<?php
				foreach($users as $data) {
					echo '<tr>';
						echo '<td><a class="ico-' . ($data['activity'] > time() - 120 ? 'online" title="'. __('friend.reqin_online') .'"' : 'offline" title="'. __('friend.reqin_offline') .'"') . '></a></td>';
						echo '<td><a href="'.App::getURL('user', ['id' => $data['id']]).'">'.html_encode($data['username']).'</a></td>';
						echo "<td><span class='group-color-{$data['color']}'>{$data['gname']}</span></td>";
						echo '<td>'.Format::today($data['registered']).'</td>';
					echo '</tr>';
				}
			?>
		</tbody>
</table>
</form>