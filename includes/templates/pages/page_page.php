<div class="page">
	<h2 class="title"><?= html_encode($page['title']) ?>
		<?php if (has_permission('admin.manage_pages')) { ?>
			<a title="Éditer" href="<?= App::getAdminURL('page_edit', ['id' => $page['id']]) ?>"><i class="fa fa-pencil-alt"></i></a>
		<?php } ?>
	</h2>
	<?= $page['content'] ?>
</div>