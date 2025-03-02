<div class="float-right">
	<ol class="breadcrumb forum-navbar tools" style="border-radius: 0px">
		<li><a href="<?= App::getURL('forums', ['id'=>@$forum['id'],'search'=>'recent']) ?>" title="<?= __('forum.nav_recent') ?>"><?= __('forum.nav_recent') ?></a>
		<li><a href="<?= App::getURL('forums', ['id'=>@$forum['id'],'search'=>'noreply']) ?>" title="<?= __('forum.nav_noreply') ?>"><?= __('forum.nav_noreply') ?></a>
		<li><a href="<?= App::getURL('forums', ['id'=>@$forum['id'],'search'=>'1']) ?>" title="<?= __('forum.nav_search') ?>"><?= __('forum.nav_search') ?></a>
		<?php if (!has_permission()) { ?>
			<li><a href="<?= App::getURL('login', ['redir'=>$_SERVER['REQUEST_URI']]) ?>" title="<?= __('forum.nav_login') ?>"><?= __('forum.nav_login') ?></a>
		<?php } ?>
	</ol>
	<?php
	if ($ptotal > 1) {
		echo '<div style="text-align:right;margin-top:-19px">Pages: ';
		foreach(Widgets::pagerAsList($ptotal, $pn, 8) as $i => $l) {
			if ($pn == $i)
				echo ' <strong>' . $i . '</strong>';
			else
				echo ' <a href="'.App::getURL('forums', @$forum['id']). (isset($topic) ? '&topic='.$topic['id'] : '') . '&pn='.$i.'">'.$l.'</a> ';
		}
		echo '</div>';
	}
	?>
</div>

<ol class="breadcrumb forum-navbar" style="border-radius: 0px">
<?php
foreach(App::getCrumbs() as $i => $crumb) {
	if (strpos($crumb, 'topic=')) {
		$crumb = "<strong>$crumb</strong>";
	}
	if ($i === count(App::getCrumbs()) - 1) {
		echo '<li class="active">'.$crumb.'</li>';
	} else {
		echo '<li>'.$crumb.'&nbsp;&nbsp;|&nbsp;&nbsp;</li>';
	}
}
?>
</ol>