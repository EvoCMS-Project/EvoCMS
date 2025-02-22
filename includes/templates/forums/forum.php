<div class="card">
	<div class="card-header">
		<div class="float-right"><a href="<?= App::getURL('forums', ['id' => $forum['id'], 'compose' => 1]) ?>"><?= __('forum.new_topic') ?></a></div>
		<?= html_encode($forum['name']) ?>
	</div>
	<?php if ($forum['description']) { ?>
		<div class="card-body"><?= bbcode2html($forum['description']) ?></div>
	<?php } ?>

<?php
if ($topics) {
	echo '<table class="table table-lists forum-topics">';
	echo '<thead><tr><td colspan="2">'. __('forum.table_topic') .'</td><td>'. __('forum.table_message') .'</td><td>'. __('forum.table_views') .'</td><td>'. __('forum.table_lastone') .'</td></tr></thead>';
	echo '<tbody>';

	foreach($topics as $topic) {
		$class = 'topic ';

		if ($topic['last_post'] > $last_visit && (!isset($topic_read[$topic['id']]) || $topic_read[$topic['id']] != $topic['last_post']))
			$class .= 'new ';

		if ($topic['sticky'])
			$class .= 'sticky ';

		if ($topic['closed'])
			$class .= 'closed ';

		echo '<tr class="'.$class.'">';

		echo '<td class="topic-icon">';
			$icons = 0;
			if ($topic['redirect']) {
				$icons++;
				echo'<i class="fas fa-location-arrow secondary" title="'. __('forum.table_btn_redirect') .'"></i> ';
			}
			if ($topic['closed']) {
				$icons++;
				echo '<i class="fas fa-lock secondary" title="'. __('forum.table_btn_closed') .'"></i> ';
			}
			if ($topic['sticky']) {
				$icons++;
				echo'<i class="fas fa-thumbtack secondary" title="'. __('forum.table_btn_sticky') .'"></i> ';
			}

			if (!$icons) echo '<i class="fa fa-angle-right primary"></i> ';
		echo '</td>';

		echo '<td>';

		if ($topic['redirect'] && $can_redirect) {
			echo '<a href="'.App::getURL('forums', ['pid'=>$topic['first_post_id'],'force'=>1]).'" title="'. __('forum.table_ign_redirect') .'"><i class="fa fa-eye" style="color:red;font-size:100%;"></i></a> ';
		}

		if ($topic['sticky'] && ($forum_moderator || has_permission('mod.forum_topic_stick'))) {
			echo '<div style="display:inline-block"><form method="post"><div class="btn-group" style="display:inline-block" title="'. __('forum.edit_order_title') .'">'.
					'<button class="btn btn-sm" name="sticky" value="+1"><i class="fa fa-arrow-up"></i></button>'.
					'<button class="btn btn-sm" name="sticky" value="-1"><i class="fa fa-arrow-down"></i></button>'.
					'<input type="hidden" name="topic" value="' . $topic['id'] . '">'.
					'</div></form></div>';
		}

		$prefix = $topic['redirect'] ? '<em>'. __('forum.link') .' : </em>' : '';
		echo $prefix.' <a href="'.App::getURL('forums', ['id' => $topic['forum_id'], 'topic' => $topic['id']]) . '">' . html_encode($topic['subject']). '</a>';

		echo '<br><small>'. __('forum.by') .' '.forum_user_link($topic['poster_id'], $topic['poster']).'</small></td>';

		echo '<td class="num-posts">' . $topic['num_posts'] . '</td>';
		echo '<td class="num-views">' . $topic['num_views']. '</td>';

		echo '<td class="last-post">';

		if (!$topic['redirect']) {
			echo '<a href="'.App::getURL('forums', ['topic'=>$topic['id'],'pn'=>ceil($topic['num_posts']/$posts_per_page)], '#msg'.$topic['last_post_id']).'">' . Format::today($topic['last_post'], true) . '</a><br>';
			echo '<small>'. __('forum.by') .' '.forum_user_link($topic['last_poster_id'], $topic['last_poster']).'</small>';
		} else {
			echo '---';
		}

		echo '</td></tr>';
	}
	echo '</tbody></table>';
} else {
	echo __('forum.is_empty');
}
?>

</div>
