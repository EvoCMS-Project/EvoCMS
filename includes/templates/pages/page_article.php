<?php	$mois = array('', 'Janv.', 'F&eacute;v.', 'Mars', 'Avril', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'D&eacute;c.'); ?>
<div class="article">
	<div class="snippet">
		<div class="date"><?= date('d', $article['pub_date']) ?></div>
		<div class="mois"><?= $mois[(int)date('m', $article['pub_date'])] ?></div>
	</div>
	<div class="title"><a href="<?=$article['page_link']?>"><?= html_encode($article['title']) ?></a></div>
	<div class="sender"><?= __('blog.article_author') ?> : <a href="<?=$article['author_link']?>"><?= html_encode($article['username']) ?></a></div>
	<br>
	<div class="message">
		<?= $article['abstract'] ?: $article['content'] ?>
	</div>
	<div class="a-btn clearfix">
		<div style="padding-top: 3px;float:left;"><?= __('blog.article_visits') ?> : <?= $article['views'] ?></div>
		<div class="float-right">
		<?php if ($home) { ?>
			<a href="<?=$article['page_link']?>" class="btn btn-primary"><?= __('blog.article_read') ?></a>
		<?php } ?>
		</div>
	</div>
</div>
