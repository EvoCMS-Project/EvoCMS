<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_pages', true);

use Evo\Models\File;

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

$rev1 = (int)App::REQ('rev1');
$rev2 = (int)App::REQ('rev2');

$thumbnails = array_column(Db::QueryAll('select id, name from {files} where mime_type like ? and origin = ?', 'image/%', 'website') ?: [], 'name', 'id');
$page_stats = admin_page_edit_build_stats($page);
$delete_confirm = html_encode(__('admin/page_edit.delete_confirm'));
$copy_confirm = html_encode(__('admin/page_edit.make_copy_confirm'));

$page_nav = [
	'edit' => ['label' => __('admin/page_edit.nav_edit'), 'icon' => 'fa-pencil-alt'],
];

if ($page['page_id']) {
	$page_nav['history'] = ['label' => __('admin/page_edit.nav_history'), 'icon' => 'fa-history'];
	$page_nav['diff'] = ['label' => __('admin/page_edit.nav_diff'), 'icon' => 'fa-not-equal'];
	$page_nav['comments'] = ['label' => __('admin/page_edit.nav_comments'), 'icon' => 'fa-comments'];
	$page_nav['view'] = [
		'label' => __('admin/page_edit.nav_view'),
		'icon' => 'fa-external-link-alt',
		'href' => App::getURL($page['page_id'], ['rev' => 'last']),
		'external' => true,
		'ms_auto' => true,
	];
}
?>

<div class="admin-dashboard admin-page-edit">
	<?= admin_stat_grid($page_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-page-edit-board">
		<form method="post" id="admin-page-edit-form" class="admin-page-edit-form">
			<?= admin_csrf_field() ?>
			<input type="hidden" id="id" name="id" value="<?= (int) $page['id'] ?>">
			<input type="hidden" id="page_id" name="page_id" value="<?= (int) $page['page_id'] ?>">

			<?= admin_page_edit_nav($page_nav, 'edit') ?>

			<div class="tab-content admin-tabs-board__body admin-page-edit-board__body admin-tabs-panel admin-page-edit-board__body--content">
				<?= admin_page_edit_tab_open('edit', true) ?>
					<?= admin_page_edit_form_board($page, $thumbnails, $delete_confirm, $copy_confirm) ?>
				<?= admin_page_edit_tab_close() ?>

				<?php if ($page['page_id']): ?>
					<?= admin_page_edit_tab_open('history', false) ?>
						<?= admin_page_edit_history_board($page) ?>
					<?= admin_page_edit_tab_close() ?>

					<?= admin_page_edit_tab_open('comments', false) ?>
					<?= admin_page_edit_tab_close() ?>

					<?= admin_page_edit_tab_open('diff', false) ?>
						<?= admin_page_edit_diff_board($page, $rev1, $rev2) ?>
					<?= admin_page_edit_tab_close() ?>
				<?php endif; ?>
			</div>
		</form>
	</section>
</div>
<?php include ROOT_DIR . '/includes/Editors/editors.php'; ?>
<script>
(function () {
	var form = document.getElementById('admin-page-edit-form');
	if (!form) {
		return;
	}

	load_editor('editor', $('#format').val());
	$('#format').change(function () {
		load_editor('editor', $('#format').val(), true);
	});

	var editor_content = null;
	setTimeout(function () {
		editor_content = window._editor.getContent();
	}, 3000);

	setInterval(function () {
		var current_content = window._editor.getContent();
		if (editor_content !== null && editor_content !== current_content) {
			$.ajax({
				url: '',
				type: 'POST',
				data: $(form).serialize() + '&autosave=1' + ($('#BtnDraft').length ? '&draft=1' : ''),
				success: function (data) {
					$('#AutoSaveStatus').html('Saved at ' + new Date().timeNow());
					$('#id').val($('#id', data).val());
					$('#page_id').val($('#page_id').val());
					if ('replaceState' in history) {
						history.replaceState(null, null, '?page=page_edit&id=' + $('#id').val());
					}
				}
			});
		}
		editor_content = current_content;
	}, 30000);

	$('#admin-page-edit-extra-toggle').on('click', function () {
		$('.admin-page-edit-extra').toggleClass('d-none');
		return false;
	});

	$('[href="#comments"]').on('click', function () {
		$.get('?page=comments&page_id=<?= (int) $page['page_id'] ?>', function (data) {
			$('#comments').html($(data).filter('#content'));
		});
	});

	<?php if ($page['id']): ?>
	if ('replaceState' in history) {
		history.replaceState(null, null, '?page=page_edit&id=<?= (int) $page['id'] ?>');
	}
	<?php endif; ?>
})();
</script>
