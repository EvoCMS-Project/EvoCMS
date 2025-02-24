<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_pages', true);

$ppp = 10;
$pn = isset($_REQUEST['pn']) && $_REQUEST['pn'] > 0 ? $_REQUEST['pn'] : 1;

if (App::GET('filter')) {
	$filter = '%' . App::GET('filter') . '%';
} else {
	$filter = '%';
}

$status = ['draft' => __('admin/pages.status_draft'), 'published' => __('admin/pages.status_published'), 'archived' => __('admin/pages.status_archived')];
?>
<div class="float-right">
	<form method="post" class="form-inline">
		<input id="filter" name="filter" class="form-control" type="text" value="<?= html_encode(App::GET('filter')) ?>" placeholder="<?= __('admin/pages.btn_search') ?>">
		<button type="submit" hidden></button>
	</form>
</div>

<legend style="padding-top:5px;"><?= __('admin/pages.title') ?></legend>

<ul class="nav nav-tabs">
	<li class="nav-item"><a class="nav-link active" href="#all" data-toggle="tab"><?= __('admin/pages.table_all') ?></a></li>
	<li class="nav-item"><a class="nav-link" href="#published" data-toggle="tab"><?= __('admin/pages.table_published') ?></a></li>
	<li class="nav-item"><a class="nav-link" href="#draft" data-toggle="tab"><?= __('admin/pages.table_draft') ?></a></li>
	<li class="nav-item"><a class="nav-link" href="#archived" data-toggle="tab"><?= __('admin/pages.table_archives') ?></a></li>
	<li class="nav-item ml-auto"><a class="nav-link" href="?page=page_edit" title="<?= __('admin/pages.btn_add_title') ?>"><i class="far fa-lg fa-file"></i> <?= __('admin/pages.btn_add') ?></a></li>
</ul>

<div class="tab-content panel">
	<div class="tab-pane fade active show" id="all" style="padding: 1em;">

<form action="?page=page_edit" method="post">
	<input type="hidden" name="delete" value="1">
	<div id="content">
		<table class="table">
			<thead>
				<th style="width:50%"><?= __('admin/pages.table_page') ?></th>
				<th style="width:0px;"></th>
				<th><?= __('admin/pages.table_status') ?></th>
				<th><?= __('admin/pages.table_comments') ?></th>
				<th><?= __('admin/pages.table_view') ?></th>
				<th><?= __('admin/pages.table_management') ?></th>
			</thead>
			<tbody>
		<?php
			//select where status <> "revision"
			$ptotal = ceil(Db::Get('select count(*) FROM {pages} as p JOIN {pages_revs} as r ON r.page_id = p.page_id AND r.revision IN(p.revisions, p.pub_rev) WHERE r.title LIKE ?', $filter) / $ppp);
			$pages =  Db::QueryAll('SELECT r.*, p.* FROM {pages} as p
									JOIN {pages_revs} as r ON r.page_id = p.page_id AND r.revision IN(p.revisions, p.pub_rev)
									WHERE r.title LIKE ?
									ORDER BY r.status, p.pub_date DESC LIMIT ?, ?',
									$filter, ($pn - 1) * $ppp, $ppp);
			foreach($pages as $page) {
				$a = ($page['pub_rev'] != $page['revision'] ? ['rev' => $page['revision']]:[]);
				echo '<tr'.($page['pub_rev'] != $page['revision'] ? ' class="bg-light"':'').'>';
					echo '<td><a href="?page=page_edit&id='.$page['id'].'">'.html_encode($page['title'] ?: __('admin/pages.table_noname')).'</a>';
					// if ($page['pub_rev'] != $page['revision'])
					// 	echo '<small><em> - Brouillon</em></small>';
					echo '</td>';
					echo '<td><a title="Permalink" href="'.App::getURL($page['slug']?:$page['page_id'], $a).'"><small>'.__('admin/pages.btn_view').'</small></a></td>';
					echo '<td>'.($status[$page['status']] ?? $page['status']).'</td>';
					echo '<td>'.$page['comments'].'</td>';
					echo '<td>'.$page['views'].'</td>';
					echo '<td>';
						echo '<a title="'.__('admin/pages.btn_edit').'" href="?page=page_edit&id='.$page['id'].'" class="btn btn-primary btn-sm"><i class="fa fa-pencil-alt"></i></a> ';
						echo '<button title="'.__('admin/pages.btn_delete').'" class="btn btn-danger btn-sm" name="id" value="'.$page['id'].'" onclick="return confirm(\''.__('admin/pages.btn_sur').'\');"><i class="far fa-trash-alt"></i></button>';
					echo '</td>';
				echo '</tr>';
				};
		?>
			</tbody>
		</table>
		<?= Widgets::pager(count($pages) < $ppp ? $pn : $ptotal, $pn, 10); ?>
	</div>
</form>

</div>
