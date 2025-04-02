<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_pages', true);

use Evo\Models\File;
use FineDiff\FineDiff;

$page = [
	'id' => 0,
	'page_id' => 0,
	'title' => '',
	'slug' => '',
	'category' => '',
	'redirect' => '',
	'content' => '',
	'image' => '',
	'extra' => [],
	'type' => false,
	'allow_comments' => 1,
	'display_toc' => 0,
	'revision' => 0,
	'revisions' => 0,
	'pub_rev' => 0,
	'pub_date' => 0,
	'views' => 0,
	'comments' => 0,
	'format' => App::getConfig('editor'),
	'sticky' => 0,
	'attached_files' => [],
	'status' => ''
];

if (App::REQ('copy')) {
	App::$POST['title'] .= ' - copy';
	App::$POST['status'] = 'draft';
	$rev_id = 0;
} elseif (App::REQ('page_id')) {
	$rev_id = Db::Get("SELECT MAX(id) FROM {pages_revs} WHERE page_id = ?", App::REQ('page_id'));
} else {
	$rev_id = App::REQ('id');
}

if ($rev_id) {
	if ($revision = Db::Get('SELECT r.*, p.* FROM {pages} AS p JOIN {pages_revs} as r USING(page_id) WHERE r.id = ?', $rev_id)) {
		$page = $revision;
		$page['extra'] = @unserialize($page['extra']);
	} else {
		App::setWarning(__('admin/page_edit.warning_edit_unexist'));
	}
}

if ($upload = reset($_FILES)) {
	try {
		$file = File::create($upload, 'website');
		die(json_encode([$file->name, $file->web_id, $file->web_id, $file->size]));
	} catch (Exception $e) {
		die("Error: {$e->getMessage()}");
	}
}

if (App::POST('delete')) {
	$r = Db::Delete('pages_revs', ['page_id' => $page['page_id']])
	   + Db::Delete('comments', ['page_id' => $page['page_id']])
	   + Db::Delete('pages', ['page_id' => $page['page_id']]);
	App::logEvent(0, 'admin', __('admin/page_edit.logevent_delete',['%pid%' => $page['page_id'], '%ptitle%' => $page['title']]));
	App::redirect(App::getAdminURL('pages'));
} elseif (!App::POST('compare') && App::POST('title') !== null && App::POST('slug') !== null && App::POST('content') !== null) {
	if (App::POST('slug') === '') {
		$page['slug'] = Format::slug(date('Y/m/') . trim(App::POST('title')));
	} else {
		$page['slug'] = Format::slug(App::POST('slug'));
	}

	/* A slug can't be an existing script name, a number, or be already attributed to another article */
	while (ctype_digit($page['slug']) || in_array($page['slug'], INTERNAL_PAGES) || Db::Get('select slug from {pages} where slug = ? and page_id <> ?', $page['slug'], $page['page_id'])) {
		$page['slug'] .= '-1';
	}

	if (!App::POST('autosave')) {
		if (App::POST('status') == 'published') {
			$page['pub_date'] = $page['pub_date'] ?: time();
			$page['pub_rev'] = &$page['revision'];
			Db::Exec("UPDATE {pages_revs} SET status = 'revision' WHERE page_id = ? and status = 'published'", $page['page_id']);
		} else {
			$page['pub_rev'] = 0;
		}
	}

	if (
		$page['status'] !== App::POST('status')
		|| $page['content'] !== App::POST('content')
		|| $page['title'] !== App::POST('title')
		|| $page['slug'] !== App::POST('slug')
		|| $page['revision'] < $page['revisions']
	) {
		$page['revision'] = ++$page['revisions'];
		$new_rev = true;
	}

	Db::Insert('pages', [
		'page_id'        => $page['page_id'] ?: null,
		'revisions'      => $page['revisions'],
		'slug'           => $page['slug'],
		'pub_date'       => strtotime(App::POST('pub_date_text')) ?: $page['pub_date'],
		'pub_rev'        => $page['pub_rev'],
		'type'           => App::POST('type'),
		'display_toc'    => App::POST('display_toc'),
		'allow_comments' => App::POST('allow_comments'),
		'views'          => $page['views'],
		'comments'       => $page['comments'],
		'category'       => App::POST('category'),
		'redirect'       => App::POST('redirect'),
		'image'          => App::POST('image'),
		'sticky'         => App::POST('sticky'),
	], true);

	$page['page_id'] = $page['page_id'] ?: Db::$insert_id;

	if (!empty($new_rev)) {
		Db::Insert('pages_revs', [
			'posted'          => time(),
			'page_id'         => $page['page_id'],
			'revision'        => $page['revisions'],
			'author'          => App::getCurrentUser()->id,
			'slug'            => $page['slug'],
			'title'           => App::POST('title') ?: 'Page sans titre',
			'content'         => App::POST('content'),
			'attached_files'  => serialize([]),
			'status'          => App::POST('autosave') ? 'autosave' : App::POST('status'),
			'format'          => App::POST('format')
		]);
		$page['id'] = Db::$insert_id;
	} else {
		Db::Update('pages_revs', ['status' => App::POST('status')], ['id' => $page['id']]);
	}

	$page = Db::Get('SELECT r.*,p.* FROM {pages} as p JOIN {pages_revs} as r USING(page_id) WHERE r.id = ?', $page['id']);

	App::logEvent(0, 'admin', __('admin/page_edit.logevent_update',['%pid%' => $page['page_id'], '%ptitle%' => $page['title']]));
	App::setSuccess(__('admin/page_edit.success_save'));
}

if ($page['revision'] < $page['revisions']) {
	App::setNotice(__('admin/page_edit.notice_older',['%revision%' => $page['revision'],'%pub_rev%' => $page['pub_rev'],'%revisions%' => $page['revisions']]));
} elseif ($page['status'] === 'autosave') {
	$last_manual = Db::Get('select max(id) from {pages_revs} where page_id = ? and status <> "autosave"', $page['page_id']);
	App::setNotice(__('admin/page_edit.notice_autosave',['%last_manual%' => $last_manual]));
} elseif ($page['revisions'] > $page['pub_rev'] && $page['pub_rev'] != 0) {
	App::setNotice(__('admin/page_edit.notice_edit_newer',['%revision%' => $page['revision'],'%pub_rev%' => $page['pub_rev']]));
}

if ($page['page_id']) {
	echo '<legend><a href="?page=pages">Pages</a> / <a href="?page=page_edit&page_id=' . $page['page_id'] . '">' . html_encode(trim($page['title'])) . '</a></legend>';
} else {
	echo '<legend><a href="?page=pages">Pages</a> / Nouvelle page</legend>';
}

$rev1 = (int)App::REQ('rev1');
$rev2 = (int)App::REQ('rev2');

// To do: Use fancy box gallery to choose image instead
$thumbnails = array_column(Db::QueryAll('select id, name from {files} where mime_type like ? and origin = ?', 'image/%', 'website') ?: [], 'name', 'id');
?>
<ul class="nav nav-tabs">
	<li class="nav-item"><a class="nav-link active" href="#page_edit" data-toggle="tab"><?= __('admin/page_edit.nav_edit') ?></a></li>
	<?php if ($page['page_id']) { ?>
		<li class="nav-item"><a class="nav-link" href="#page_hist" data-toggle="tab"><?= __('admin/page_edit.nav_history') ?></a></li>
		<li class="nav-item"><a class="nav-link" href="#page_diff" data-toggle="tab"><?= __('admin/page_edit.nav_diff') ?></a></li>
		<li class="nav-item"><a class="nav-link" href="#page_comments" data-toggle="tab"><?= __('admin/page_edit.nav_comments') ?></a></li>
		<li class="nav-item"><a class="nav-link" href="<?= App::getURL($page['page_id'], ['rev' => 'last']) ?>"><?= __('admin/page_edit.nav_view') ?></a></li>
	<?php } ?>
</ul>
<form method="post">
	<input type="hidden" id="id" name="id" value="<?= $page['id'] ?>">
	<input type="hidden" id="page_id" name="page_id" value="<?= $page['page_id'] ?>">
	<div class="tab-content panel">
		<div class="tab-pane fade show active" id="page_edit">
			<div class="control-group">
				<div class="form-group row">
					<div class="col-sm-9">
						<label class="col-form-label text-right" for="title"><?= __('admin/page_edit.title') ?> :</label>
						<div class="controls">
							<input class="form-control" name="title" type="text" placeholder="<?= __('admin/page_edit.title_ph') ?>" value="<?php echo html_encode($page['title']); ?>" />
						</div>
					</div>
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="title"><?= __('admin/page_edit.type') ?> :</label>
						<div class="controls">
							<select name="type" class="form-control">
								<?php foreach (PAGE_TYPES as $id => $type) echo '<option value="' . $id . '" ' . ($id == $page['type'] ? 'selected' : '') . '>' . $type . '</option>'; ?>
							</select>
						</div>
					</div>
				</div>
				<div class="form-group row">
					<div class="col-sm-9">
						<label class="col-form-label text-right" for="title"><?= __('admin/page_edit.url') ?> :</label>
						<div class="controls">
							<div class="input-group">
								<div class="input-group-prepend"><span class="input-group-text"><?= App::getURL('/') ?></span></div>
								<input class="form-control" name="slug" type="text" placeholder="Slug" value="<?= html_encode($page['slug']) ?>" />
							</div>
						</div>
					</div>
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="title"><?= __('admin/page_edit.visibility') ?> :</label>
						<div class="controls">
							<select name="status" class="form-control">
								<option value="published"><?= __('admin/page_edit.status_published') ?> <small><?php if ($page['pub_date']) echo '(' . Format::today($page['pub_date']) . ')'; ?></small></option>
								<option value="draft" <?php if (!$page['pub_rev'] || $page['pub_rev'] != $page['revision']) echo 'selected'; ?>><?= __('admin/page_edit.status_draft') ?></option>
							</select>
						</div>
					</div>
				</div>
			</div>
			<div class="control-group row pt-1">
				<div class="col-sm-12 text-right">
					<a href="" id="extra-option"><?= __('admin/page_edit.more_options') ?></a>
				</div>
			</div>
			<div class="control-group">
				<div class="form-group row">
					<div class="col-sm-3 extra-option">
						<label class="col-form-label text-right" for="name"><?= __('admin/page_edit.category') ?> :</label>
						<div class="controls">
							<input type="text" name="category" value="<?= html_encode($page['category']) ?>" class="form-control" data-autocomplete="categorylist" data-autocomplete-instant>
						</div>
					</div>
					<div class="col-sm-3 extra-option">
						<label class="col-form-label text-right" for="name"><?= __('admin/page_edit.tags') ?> :</label>
						<div class="controls">
							<input type="text" name="category" disabled class="form-control" placeholder="Not implemented">
						</div>
					</div>
					<div class="col-sm-3 extra-option">
						<label class="col-form-label text-right" for="name"><?= __('admin/page_edit.redirect') ?> :</label>
						<div class="controls">
							<input type="text" name="redirect" value="<?= html_encode($page['redirect']) ?>" class="form-control">
						</div>
					</div>
					<div class="col-sm-3 extra-option">
						<label class="col-form-label text-right" for="name"><?= __('admin/page_edit.date_on') ?> :</label>
						<div class="controls">
							<input type="text" name="pub_date_text" value="<?= $page['pub_date'] ? date('Y-m-d H:i', $page['pub_date']) : '' ?>" class="form-control">
						</div>
					</div>
				</div>

				<div class="form-group row extra-option pb-3">
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="name"><span title="<?= __('admin/page_edit.option_thumbnails_title') ?>"><?= __('admin/page_edit.option_thumbnail') ?></span> :</label>
						<div class="controls">
							<?= Widgets::select('image', ['' => 'Automatique'] + $thumbnails, $page['image']) ?>
						</div>
					</div>
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="name"><?= __('admin/page_edit.nav_comments') ?> :</label>
						<div class="controls">
							<select name="allow_comments" class="form-control">
								<option value="1">Oui</option>
								<option value="0" <?= ($page['allow_comments'] == 0) ? 'selected' : '' ?>><?= __('admin/general.no') ?></option>
								<option value="2" <?= ($page['allow_comments'] == 2) ? 'selected' : '' ?>><?= __('admin/page_edit.option_closing') ?></option>
							</select>
						</div>
					</div>
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="name"><?= __('admin/page_edit.option_summary') ?> :</label>
						<div class="controls">
							<select name="display_toc" class="form-control">
								<option value="1">Oui</option>
								<option value="0" <?= $page['display_toc'] ? '' : 'selected' ?>><?= __('admin/general.no') ?></option>
							</select>
						</div>
					</div>
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="name"><?= __('admin/page_edit.option_sticky') ?> :</label>
						<div class="controls">
							<select name="sticky" class="form-control" title="<?= __('admin/page_edit.option_sticky_help') ?>">
								<option value="0"><?= __('admin/page_edit.option_dont_sticky') ?></option>
								<?php
								foreach (range(1, 100) as $sticky) {
									if ($page['sticky'] == $sticky) {
										echo '<option selected="selected" value="' . $sticky . '">'.__('admin/page_edit.position').' ' . $sticky . '</option>';
									} else {
										echo '<option value="' . $sticky . '">'.__('admin/page_edit.position').' ' . $sticky . '</option>';
									}
								}
								?>
							</select>
						</div>
					</div>
				</div>
			</div>

			<div class="pt-2">
				<textarea class="form-control" id="editor" name="content" placeholder="<?= __('admin/page_edit.content_ph') ?>" style="height:300px;"><?= html_encode($page['content']) ?></textarea>
				<em id="AutoSaveStatus"></em>
				<div class="float-right">
					<?= Widgets::select('format', ['wysiwyg'  => 'WYSIWYG', 'markdown' => 'Markdown+'], $page['format'], true, '') ?>
				</div>
			</div>
			<div class="clearfix"></div>
			<div class="text-center">
				<button class='btn btn-success'><?= __('admin/general.btn_save') ?></button>
				<button class='btn btn-danger' name="delete" value="delete" onclick="return confirm('<?= __('admin/page_edit.delete_confirm') ?>');"><?= __('admin/general.btn_save') ?></button>
				<button class='btn btn-info' name="copy" value="copy" onclick="return confirm('<?= __('admin/page_edit.make_copy_confirm') ?>');"><?= __('admin/page_edit.make_copy') ?></button>
			</div>
		</div>

		<div class="tab-pane fade" id="page_hist">
			<table class="table">
				<thead>
					<th width="30px;"><button name="compare" class="btn btn-primary btn-sm" value="1"><?= __('admin/page_edit.btn_compare') ?></button></th>
					<th>#</th>
					<th><?= __('admin/page_edit.table_date') ?></th>
					<th><?= __('admin/page_edit.table_eta') ?></th>
					<th><?= __('admin/page_edit.table_author') ?></th>
					<th><?= __('admin/page_edit.table_size') ?></th>
					<th><?= __('admin/page_edit.table_attch') ?></th>
					<th style="width:120px;"></th>
				</thead>
				<tbody>
					<?php
					$q = Db::QueryAll('SELECT r.*, p.*, a.username, LENGTH(r.content) as size
								   FROM {pages} as p
								   JOIN {pages_revs} as r ON r.page_id = p.page_id
								   LEFT JOIN {users} as a ON author = a.id
								   WHERE p.page_id = ?
								   ORDER by revision DESC', $page['page_id']);
					$count = count($q);

					foreach($q as $i => $row) {
						echo '<tr ';
						if ($page['pub_rev'] == $row['revision']) echo 'class="success"';
						if ($page['revision'] == $row['revision']) echo 'class="info"';

						echo '><td class="text-center;">';
						echo '<input type="radio" name="rev1" value="' . $row['revision'] . '"' . ($i + 1 == $count ? 'disabled' : '') . '> ';
						echo '<input type="radio" name="rev2" value="' . $row['revision'] . '"' . ($i == 0 ? 'disabled' : '') . '> ';
						echo '</td><td>' . $row['revision'] . '</td><td>' . Format::today($row['posted']) . '</td>';
						echo '<td>' . $row['status'] . '</td><td>' . $row['username'] . '</td><td>' . $row['size'] . '</td><td>' . implode('<br>', (array) @unserialize($row['attached_files'])) . '</td><td class="btn-group">';
						echo '<a title="'.__('admin/page_edit.open_editor').'" href="?page=page_edit&id=' . $row['id'] . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil-alt"></i></button> ';
						echo '<a title="'.__('admin/general.see').'" href="' . App::getURL('pageview', ['id' => $row['page_id'], 'rev' => $row['revision']]) . '" class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a> ';
						echo '</td></tr>';
					}
					?>
				</tbody>
			</table>
		</div>

		<div class="tab-pane fade" id="page_comments">
		</div>

		<div class="tab-pane fade p-2" id="page_diff">
			<div id="diffbox">
				<?php
				if ($rev1 && $rev2) {
					$rev = Db::QueryAll('SELECT revision, content, posted FROM {pages_revs} WHERE page_id = ? AND (revision = ? OR revision = ?)', $page['page_id'], $rev1, $rev2, true);
					if (count($rev) != 2) {
						App::setWarning(__('admin/page_edit.warning_rev_invalid'));
					} else {
						$diff = (new FineDiff($rev[$rev2]['content'], $rev[$rev1]['content'], FineDiff::$wordGranularity))->renderDiffToHTML();
						$d1 = '<strong><small>' . Format::today($rev[$rev1]['posted'], true) . '</small></strong>';
						$d2 = '<strong><small>' . Format::today($rev[$rev2]['posted'], true) . '</small></strong>';
						echo '<ins>'.__('admin/page_edit.red').'</ins> : '.__('admin/page_edit.present_in').' ' . $rev1 . ' (' . $d1 . ') '.__('admin/page_edit.but_not_in').' ' . $rev2 . ' (' . $d2 . ')<br>';
						echo '<del>'.__('admin/page_edit.green').'</del> : '.__('admin/page_edit.present_in').' ' . $rev2 . ' (' . $d2 . ') '.__('admin/page_edit.but_not_in').' ' . $rev1 . ' (' . $d1 . ')<br>';
						echo '<div class="pane diff" style="white-space:pre-wrap">' . $diff . '</div>';
					}
					echo '<script>$(\'[href="#page_diff"]\').click();</script>';
				} else {
					echo '<script>$(\'[href="#page_diff"]\').hide();</script>';
				}
				?>
			</div>
		</div>
	</div>
</form>
<?php include ROOT_DIR . '/includes/Editors/editors.php'; ?>
<script>// <!--
	load_editor('editor', $('#format').val());
	$('#format').change(function() {
		load_editor('editor', $('#format').val(), true);
	});

	var editor_content = null;
	setTimeout(function() {
		editor_content = window._editor.getContent();
	}, 3000);
	setInterval(function() {
		var current_content = window._editor.getContent();
		if (editor_content !== null && editor_content !== current_content) {
			$.ajax({
				url: '',
				type: 'POST',
				data: $('form').serialize() + '&autosave=1' + ($('#BtnDraft').length ? '&draft=1' : ''),
				success: function(data) {
					$('#AutoSaveStatus').html('Saved at ' + new Date().timeNow());
					$('#id').val($('#id', data).val());
					$('#page_id').val($('#page_id').val());
					if ("replaceState" in history) {
						history.replaceState(null, null, '?page=page_edit&id=' + $('#id').val());
					}
				}
			});
		}
		editor_content = current_content;
	}, 30000);

	$('#extra-option').click(function() {
		$('.extra-option').toggle();
		return false;
	});
	$('.extra-option').hide();

	$('[href="#page_comments"').click(function() {
		$.get('?page=comments&page_id=<?= $page['page_id'] ?>',
			data => $('#page_comments').html($(data).filter('#content'))
		);
	});

	<?php if ($page['id']) echo 'if ("replaceState" in history)  { history.replaceState(null, null, "?page=page_edit&id=' . $page['id'] . '");}' ?>
	// -->
</script>
