<div class="page">
	<h2 class="title"><?=html_encode($category)?></h2>
	<div class="pagelist">
	<?php foreach($pages as $page) { ?>
		<div class="pagelist-item">
			<?php if ($page['image']) { ?>
				<div class="pagelist-image"><a href="<?=$page['link']?>"><img src="<?=$page['image']?>" title="<?=html_encode($page['title'])?>" alt=""></a></div>
			<?php } ?>
			<div class="pagelist-title"><a href="<?=$page['link']?>"><?=html_encode($page['title'])?></a></div>
			<div class="pagelist-date">- Added <?=Format::today($page['pub_date'])?></div>
			<?= Format::truncate(strip_tags($page['content']), 250) ?>
		</div>
	<?php } ?>
	</div>
</div>