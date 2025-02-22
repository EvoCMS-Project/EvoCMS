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
		App::setWarning('La page que vous tentez d\'éditer n\'existe pas encore!');
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
	App::logEvent(0, 'admin', 'Suppression de la page #' . $page['page_id'] . ': ' . $page['title'] . '.');
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

	App::logEvent(0, 'admin', 'Mise à jour de la page #' . $page['page_id'] . ': ' . $page['title'] . '.');
	App::setSuccess('Page enregistrée!');
}

if ($page['revision'] < $page['revisions']) {
	App::setNotice('Vous êtes en train d\'éditer une révision antérieure. Révision ouverte: ' . $page['revision'] . '. Révision publiée: ' . $page['pub_rev'] . '. Dernière révision: ' . $page['revisions'] . '.');
} elseif ($page['status'] === 'autosave') {
	$last_manual = Db::Get('select max(id) from {pages_revs} where page_id = ? and status <> "autosave"', $page['page_id']);
	App::setNotice('Ceci est une sauvegarde automatique, pour retourner au dernier enregistrement manuel <a href="?page=page_edit&id=' . $last_manual . '">cliquez ici</a>.');
} elseif ($page['revisions'] > $page['pub_rev'] && $page['pub_rev'] != 0) {
	App::setNotice('Vous êtes en train d\'éditer une révision plus récente que celle publiée. Révision ouverte: ' . $page['revision'] . '. Révision publiée: ' . $page['pub_rev'] . '.');
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
	<li class="nav-item"><a class="nav-link active" href="#page_edit" data-toggle="tab">Édition</a></li>
	<?php if ($page['page_id']) { ?>
		<li class="nav-item"><a class="nav-link" href="#page_hist" data-toggle="tab">Historique</a></li>
		<li class="nav-item"><a class="nav-link" href="#page_diff" data-toggle="tab">Diff</a></li>
		<li class="nav-item"><a class="nav-link" href="#page_comments" data-toggle="tab">Commentaires</a></li>
		<li class="nav-item"><a class="nav-link" href="<?= App::getURL($page['page_id'], ['rev' => 'last']) ?>">Voir</a></li>
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
						<label class="col-form-label text-right" for="title">Titre de la page :</label>
						<div class="controls">
							<input class="form-control" name="title" type="text" placeholder="Titre" value="<?php echo html_encode($page['title']); ?>" />
						</div>
					</div>
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="title">Type :</label>
						<div class="controls">
							<select name="type" class="form-control">
								<?php foreach (PAGE_TYPES as $id => $type) echo '<option value="' . $id . '" ' . ($id == $page['type'] ? 'selected' : '') . '>' . $type . '</option>'; ?>
							</select>
						</div>
					</div>
				</div>
				<div class="form-group row">
					<div class="col-sm-9">
						<label class="col-form-label text-right" for="title">URL :</label>
						<div class="controls">
							<div class="input-group">
								<div class="input-group-prepend"><span class="input-group-text"><?= App::getURL('/') ?></span></div>
								<input class="form-control" name="slug" type="text" placeholder="Slug" value="<?= html_encode($page['slug']) ?>" />
							</div>
						</div>
					</div>
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="title">Visibilité :</label>
						<div class="controls">
							<select name="status" class="form-control">
								<option value="published">Publiée <small><?php if ($page['pub_date']) echo '(' . Format::today($page['pub_date']) . ')'; ?></small></option>
								<option value="draft" <?php if (!$page['pub_rev'] || $page['pub_rev'] != $page['revision']) echo 'selected'; ?>>Brouillon</option>
							</select>
						</div>
					</div>
				</div>
			</div>
			<div class="control-group row pt-1">
				<div class="col-sm-12 text-right">
					<a href="" id="extra-option">Plus d'options</a>
				</div>
			</div>
			<div class="control-group">
				<div class="form-group row">
					<div class="col-sm-3 extra-option">
						<label class="col-form-label text-right" for="name">Catégorie:</label>
						<div class="controls">
							<input type="text" name="category" value="<?= html_encode($page['category']) ?>" class="form-control" data-autocomplete="categorylist" data-autocomplete-instant>
						</div>
					</div>
					<div class="col-sm-3 extra-option">
						<label class="col-form-label text-right" for="name">Tags:</label>
						<div class="controls">
							<input type="text" name="category" disabled class="form-control" placeholder="Not implemented">
						</div>
					</div>
					<div class="col-sm-3 extra-option">
						<label class="col-form-label text-right" for="name">Redirection:</label>
						<div class="controls">
							<input type="text" name="redirect" value="<?= html_encode($page['redirect']) ?>" class="form-control">
						</div>
					</div>
					<div class="col-sm-3 extra-option">
						<label class="col-form-label text-right" for="name">Date de publication:</label>
						<div class="controls">
							<input type="text" name="pub_date_text" value="<?= $page['pub_date'] ? date('Y-m-d H:i', $page['pub_date']) : '' ?>" class="form-control">
						</div>
					</div>
				</div>

				<div class="form-group row extra-option pb-3">
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="name"><span title="Bannière et vignette">Image</span> de l'article:</label>
						<div class="controls">
							<?= Widgets::select('image', ['' => 'Automatique'] + $thumbnails, $page['image']) ?>
						</div>
					</div>
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="name">Commentaires:</label>
						<div class="controls">
							<select name="allow_comments" class="form-control">
								<option value="1">Oui</option>
								<option value="0" <?= ($page['allow_comments'] == 0) ? 'selected' : '' ?>>Non</option>
								<option value="2" <?= ($page['allow_comments'] == 2) ? 'selected' : '' ?>>Clôs</option>
							</select>
						</div>
					</div>
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="name">Table des matières:</label>
						<div class="controls">
							<select name="display_toc" class="form-control">
								<option value="1">Oui</option>
								<option value="0" <?= $page['display_toc'] ? '' : 'selected' ?>>Non</option>
							</select>
						</div>
					</div>
					<div class="col-sm-3">
						<label class="col-form-label text-right" for="name">Épingler:</label>
						<div class="controls">
							<select name="sticky" class="form-control" title="Épingler pour que l'article soit toujours présent sur la page d'accueil">
								<option value="0">Ne pas épingler</option>
								<?php
								foreach (range(1, 100) as $sticky) {
									if ($page['sticky'] == $sticky) {
										echo '<option selected="selected" value="' . $sticky . '">Position ' . $sticky . '</option>';
									} else {
										echo '<option value="' . $sticky . '">Position ' . $sticky . '</option>';
									}
								}
								?>
							</select>
						</div>
					</div>
				</div>
			</div>

			<div class="pt-2">
				<textarea class="form-control" id="editor" name="content" placeholder="Contenu" style="height:300px;"><?= html_encode($page['content']) ?></textarea>
				<em id="AutoSaveStatus"></em>
				<div class="float-right">
					<?= Widgets::select('format', ['wysiwyg'  => 'WYSIWYG', 'markdown' => 'Markdown+'], $page['format'], true, '') ?>
				</div>
			</div>
			<div class="clearfix"></div>
			<div class="text-center">
				<button class='btn btn-success'>Enregistrer</button>
				<button class='btn btn-danger' name="delete" value="delete" onclick="return confirm('Supprimer la page? / Delete page?');">Supprimer</button>
				<button class='btn btn-info' name="copy" value="copy" onclick="return confirm('Faire une copie? / Make a copy?');">Faire une copie</button>
			</div>
		</div>

		<div class="tab-pane fade" id="page_hist">
			<table class="table">
				<thead>
					<th width="30px;"><button name="compare" class="btn btn-primary btn-sm" value="1">Comparer</button></th>
					<th>#</th>
					<th>Date</th>
					<th>Status</th>
					<th>Auteur</th>
					<th>Taille</th>
					<th>Attachement</th>
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
						echo '<a title="Ouvrir dans l\'éditeur" href="?page=page_edit&id=' . $row['id'] . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil-alt"></i></button> ';
						echo '<a title="Voir" href="' . App::getURL('pageview', ['id' => $row['page_id'], 'rev' => $row['revision']]) . '" class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a> ';
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
						App::setWarning('Révisions invalides ou identiques.');
					} else {
						$diff = (new FineDiff($rev[$rev2]['content'], $rev[$rev1]['content'], FineDiff::$wordGranularity))->renderDiffToHTML();
						$d1 = '<strong><small>' . Format::today($rev[$rev1]['posted'], true) . '</small></strong>';
						$d2 = '<strong><small>' . Format::today($rev[$rev2]['posted'], true) . '</small></strong>';
						echo '<ins>Vert</ins>: Contenu présent dans ' . $rev1 . ' (' . $d1 . ') mais pas dans ' . $rev2 . ' (' . $d2 . ')<br>';
						echo '<del>Rouge</del>: Contenu présent dans ' . $rev2 . ' (' . $d2 . ') mais pas dans ' . $rev1 . ' (' . $d1 . ')<br>';
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
