<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.files', true);

function is_editable($file) {
		$file = basename($file);
		$allowed_exts = 'txt|php|htm|html|js|json|css|tpl|xml|md|htaccess|htpasswd|conf|ini';
		return preg_match('/\.('.$allowed_exts.')$/i', $file) || strpos($file, '.') === false;
	}

	function file_editor_tree_dir_id($absolute_path) {
		return preg_replace('/[^a-z0-9-]/', '_', substr($absolute_path, strlen(ROOT_DIR)));
	}

	function file_editor_rel_path($absolute_path) {
		$rel = substr($absolute_path, strlen(ROOT_DIR));
		$rel = ltrim($rel, DIRECTORY_SEPARATOR . '/\\');
		return str_replace('\\', '/', $rel);
	}

	function file_editor_breadcrumb_segments($rel_file) {
		$path_parts = array_values(array_filter(explode('/', str_replace('\\', '/', $rel_file)), 'strlen'));
		$segments = [];
		$accumulated = '';

		foreach ($path_parts as $index => $segment) {
			$accumulated = $accumulated === '' ? $segment : $accumulated . '/' . $segment;
			$is_last = ($index === count($path_parts) - 1);
			$folder_abs = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $accumulated);

			$segments[] = [
				'name'     => $segment,
				'path'     => $accumulated,
				'is_file'  => $is_last,
				'tree_dir' => $is_last ? null : file_editor_tree_dir_id($folder_abs),
			];
		}

		return $segments;
	}

	function file_editor_resolve_file($rel_path) {
		if (!is_string($rel_path) || $rel_path === '') {
			return false;
		}

		$rel_path = str_replace('\\', '/', $rel_path);
		$rel_path = ltrim($rel_path, '/\\');
		$file = realpath(ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel_path));

		if (!$file || !is_file($file)) {
			return false;
		}

		$root = rtrim(str_replace('\\', '/', ROOT_DIR), '/');
		$file_normalized = str_replace('\\', '/', $file);

		if (stripos($file_normalized, $root . '/') !== 0 && strcasecmp($file_normalized, $root) !== 0) {
			return false;
		}

		if (!is_editable($file)) {
			return false;
		}

		return [
			'path'     => $file,
			'rel_file' => file_editor_rel_path($file),
			'ext'      => pathinfo($file, PATHINFO_EXTENSION),
		];
	}

	function file_editor_payload($resolved) {
		return [
			'file'       => $resolved['rel_file'],
			'content'    => file_get_contents($resolved['path']),
			'ext'        => $resolved['ext'],
			'writable'   => is_writable($resolved['path']),
			'breadcrumb' => file_editor_breadcrumb_segments($resolved['rel_file']),
		];
	}


	function files_tree($dir, $current = '', $dot_files = true) {
		$current = is_string($current) ? $current : '';
		$data = '<ul class="collapsible">';
		$files = glob($dir . DIRECTORY_SEPARATOR . ($dot_files ? '{,.??}*' : '*'), GLOB_BRACE);

		$_dirs = $_files = [];

		foreach($files as $path) {
			if (is_dir($path)) {
				$_dirs[] = $path;
			} else {
				$_files[] = $path;
			}
		}

		asort($_dirs);
		asort($_files);

		$files = array_merge($_dirs, $_files);

		foreach ($files as $path) {
			$file = basename($path);
			$selected = (substr($current, 0, strlen($path)) === $path);

			if (is_dir($path)) {
				$dir_id = preg_replace('/[^a-z0-9-]/', '_', substr($path, strlen(ROOT_DIR)));
				$data .= '<li class="collapsible-header dir">
							<a data-bs-toggle="collapse" href="#' . $dir_id . '"><i class="fa-solid fa-folder fa-sm folder-icon"></i>' . $file . '</a>
							<div class="collapsible-body ' . ($selected ? 'expand' : 'collapse') . '" id="' . $dir_id . '">' . files_tree($path, $current, $dot_files) . '</div>
						  </li>';
			} else {
				$data .= '<li class="file ' . ($selected ? 'selected' : '') . '"><i class="fa-regular fa-sm fa-file"></i>';
				if (is_editable($file)) {
					$rel_path = file_editor_rel_path($path);
					$file_url = App::getAdminURL('file_editor', ['file' => $rel_path]);
					$data .= '<a href="' . html_encode($file_url) . '" class="file-editor-tree-link" data-file="' . html_encode($rel_path) . '">' . Format::truncate($file, 22) . '</a>';
				} else {
					$data .= $file;
				}
				$data .= '</li>';
			}
		}

		$data .= '</ul>';
		return $data;
	}


	$file = App::GET('file', '');
	$resolved = false;

	if ($file) {
		$resolved = file_editor_resolve_file($file);
	}

	if (IS_AJAX && App::POST('action')) {
		while (ob_get_level()) {
			ob_end_clean();
		}
		header('Content-Type: application/json; charset=utf-8');

		switch (App::POST('action')) {
			case 'load_file':
				$load_path = App::POST('file', '');
				$load_resolved = file_editor_resolve_file($load_path);

				if (!$load_resolved) {
					http_response_code(404);
					die(json_encode(['error' => __('admin/system.editor_load_error')]));
				}

				die(json_encode(file_editor_payload($load_resolved), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

			case 'save_file':
				$save_path = App::POST('file', '');
				$save_resolved = file_editor_resolve_file($save_path);

				if (!$save_resolved) {
					http_response_code(404);
					die(json_encode(['error' => __('admin/system.editor_load_error')]));
				}

				if (!is_writable($save_resolved['path'])) {
					http_response_code(403);
					die(json_encode(['error' => __('admin/system.editor_alert_readonly')]));
				}

				if (App::POST('content') === null) {
					http_response_code(400);
					die(json_encode(['error' => __('admin/system.editor_alert_warning_save')]));
				}

				if (file_put_contents($save_resolved['path'], App::POST('content')) === false) {
					http_response_code(500);
					die(json_encode(['error' => __('admin/system.editor_alert_warning_save')]));
				}

				die(json_encode([
					'success' => true,
					'message' => __('admin/system.editor_alert_success_save'),
				]));
		}
	}
?>
<style>
	#footer { display:none !important; }
	#admin-page > .plugin_header { display:none; }
</style>
<script type="text/javascript" src="<?= App::getAsset('/includes/Editors/ace/ace.js') ?>"></script>
<?php

	if ($resolved) {
		$file = $resolved['path'];
		$ext = $resolved['ext'];
		$rel_file = $resolved['rel_file'];
	}

    if ($resolved && !IS_AJAX && App::POST('save') && App::POST('content') !== null) {
        $updated = file_put_contents($file, App::POST('content'));
		if ($updated) {
            App::setSuccess(__('admin/system.editor_alert_success_save'));
        } else {
        	App::setWarning(__('admin/system.editor_alert_warning_save'));
        }
	}

	if (App::POST('theme')) {
		App::setConfig('file_editor.theme', App::POST('theme'));
	}

	if (App::POST('fontSize')) {
		App::setConfig('file_editor.fontSize', App::POST('fontSize'));
	}

	$editor_page_url = App::getAdminURL('file_editor');
	$editor_page_path = parse_url($editor_page_url, PHP_URL_PATH) ?: '/admin/';
	$editor_page_query = parse_url($editor_page_url, PHP_URL_QUERY) ?: 'page=file_editor';

	$themes = [
		'Bright' => new HtmlSelectGroup([
			'ace/theme/chrome'                     => 'Chrome',
			'ace/theme/clouds'                     => 'Clouds',
			'ace/theme/crimson_editor'             => 'Crimson Editor',
			'ace/theme/dawn'                       => 'Dawn',
			'ace/theme/dreamweaver'                => 'Dreamweaver',
			'ace/theme/eclipse'                    => 'Eclipse',
			'ace/theme/github'                     => 'GitHub',
			'ace/theme/iplastic'                   => 'IPlastic',
			'ace/theme/solarized_light'            => 'Solarized Light',
			'ace/theme/textmate'                   => 'TextMate',
			'ace/theme/tomorrow'                   => 'Tomorrow',
			'ace/theme/xcode'                      => 'XCode',
			'ace/theme/kuroir'                     => 'Kuroir',
			'ace/theme/katzenmilch'                => 'KatzenMilch',
			'ace/theme/sqlserver'                  => 'SQL Server',
		]),
		'Dark' => new HtmlSelectGroup([
			'ace/theme/ambiance'                   => 'Ambiance',
			'ace/theme/chaos'                      => 'Chaos',
			'ace/theme/clouds_midnight'            => 'Clouds Midnight',
			'ace/theme/dracula'                    => 'Dracula',
			'ace/theme/cobalt'                     => 'Cobalt',
			'ace/theme/gruvbox'                    => 'Gruvbox',
			'ace/theme/gob'                        => 'Green on Black',
			'ace/theme/idle_fingers'               => 'idle Fingers',
			'ace/theme/kr_theme'                   => 'krTheme',
			'ace/theme/merbivore'                  => 'Merbivore',
			'ace/theme/merbivore_soft'             => 'Merbivore Soft',
			'ace/theme/mono_industrial'            => 'Mono Industrial',
			'ace/theme/monokai'                    => 'Monokai',
			'ace/theme/pastel_on_dark'             => 'Pastel on dark',
			'ace/theme/solarized_dark'             => 'Solarized Dark',
			'ace/theme/terminal'                   => 'Terminal',
			'ace/theme/tomorrow_night'             => 'Tomorrow Night',
			'ace/theme/tomorrow_night_blue'        => 'Tomorrow Night Blue',
			'ace/theme/tomorrow_night_bright'      => 'Tomorrow Night Bright',
			'ace/theme/tomorrow_night_eighties'    => 'Tomorrow Night 80s',
			'ace/theme/twilight'                   => 'Twilight',
			'ace/theme/vibrant_ink'                => 'Vibrant Ink',
		]),
	];
?>

<div id="file_editor" class="row"
	data-editor-url="<?= html_encode($editor_page_url) ?>"
	data-editor-path="<?= html_encode($editor_page_path) ?>"
	data-editor-query="<?= html_encode($editor_page_query) ?>"
	data-label-root="<?= html_encode(__('admin/system.editor_toolbar_root')) ?>"
	data-label-readonly="<?= html_encode(__('admin/system.editor_alert_readonly')) ?>"
	data-label-load-error="<?= html_encode(__('admin/system.editor_load_error')) ?>"
	data-label-confirm-unsaved="<?= html_encode(__('admin/system.editor_confirm_unsaved')) ?>"
	data-label-save-success="<?= html_encode(__('admin/system.editor_alert_success_save')) ?>"
	data-label-save-error="<?= html_encode(__('admin/system.editor_alert_warning_save')) ?>"
	data-current-file="<?= $resolved ? html_encode($rel_file) : '' ?>">
	<form method="post" class="file-editor-form">
		<?= admin_csrf_field() ?>
		<div class="file-editor-toolbar<?= $resolved ? '' : ' file-editor-toolbar--empty' ?>">
			<div class="file-editor-toolbar__context">
				<ol class="breadcrumb file-editor-toolbar__breadcrumb" aria-label="<?= __('admin/system.editor_toolbar_path') ?>">
					<li class="breadcrumb-item">
						<button type="button" class="file-editor-toolbar__breadcrumb-link file-editor-breadcrumb-root" title="<?= html_encode(__('admin/system.editor_toolbar_root')) ?>">
							<i class="fa-solid fa-house" aria-hidden="true"></i>
							<span><?= __('admin/system.editor_toolbar_root') ?></span>
						</button>
					</li>
					<?php if ($resolved) {
						foreach (file_editor_breadcrumb_segments($rel_file) as $segment) {
							if ($segment['is_file']) { ?>
								<li class="breadcrumb-item active" aria-current="page">
									<span class="file-editor-toolbar__breadcrumb-separator" aria-hidden="true">›</span>
									<i class="fa-regular fa-file" aria-hidden="true"></i>
									<span class="file-editor-toolbar__breadcrumb-current"><?= html_encode($segment['name']) ?></span>
								</li>
							<?php } else { ?>
								<li class="breadcrumb-item">
									<span class="file-editor-toolbar__breadcrumb-separator" aria-hidden="true">›</span>
									<button type="button" class="file-editor-toolbar__breadcrumb-link" data-tree-dir="<?= html_encode($segment['tree_dir']) ?>" title="<?= html_encode($segment['path']) ?>">
										<i class="fa-solid fa-folder folder-icon" aria-hidden="true"></i>
										<span><?= html_encode($segment['name']) ?></span>
									</button>
								</li>
							<?php }
						}
					} ?>
				</ol>
				<span class="file-editor-toolbar__badge file-editor-toolbar__badge--readonly"<?= ($resolved && !is_writable($file)) ? '' : ' hidden' ?>>
					<i class="fa-solid fa-lock" aria-hidden="true"></i>
					<?= __('admin/system.editor_alert_readonly') ?>
				</span>
			</div>

			<div class="file-editor-toolbar__actions">
				<div class="file-editor-toolbar__group file-editor-toolbar__when-file" title="<?= __('admin/system.editor_toolbar_theme') ?>">
					<label class="file-editor-toolbar__group-label" for="theme">
						<i class="fa-solid fa-palette" aria-hidden="true"></i>
						<span class="visually-hidden"><?= __('admin/system.editor_toolbar_theme') ?></span>
					</label>
					<div class="file-editor-toolbar__select-wrap">
						<?= Widgets::select('theme', $themes, App::getConfig('file_editor.theme'), true, 'class="file-editor-toolbar__select"') ?>
					</div>
				</div>

				<div class="file-editor-toolbar__group file-editor-toolbar__group--zoom file-editor-toolbar__when-file" role="group" aria-label="Zoom">
					<button type="button" name="fe-zoom-out" class="file-editor-toolbar__btn file-editor-toolbar__btn--icon" title="<?= __('admin/system.editor_btn_zoomout') ?>">
						<i class="fa-solid fa-minus" aria-hidden="true"></i>
					</button>
					<span name="font-size" class="file-editor-toolbar__zoom-value" aria-live="polite"><?= (int) App::getConfig('file_editor.fontSize') ?: 12 ?></span>
					<button type="button" name="fe-zoom-in" class="file-editor-toolbar__btn file-editor-toolbar__btn--icon" title="<?= __('admin/system.editor_btn_zoomin') ?>">
						<i class="fa-solid fa-plus" aria-hidden="true"></i>
					</button>
				</div>

				<div class="file-editor-toolbar__group file-editor-toolbar__group--actions">
					<button type="button" name="fe-fullscreen" class="file-editor-toolbar__btn file-editor-toolbar__btn--icon" title="<?= __('admin/system.editor_btn_fullscreen') ?>" aria-pressed="false" data-title-enter="<?= html_encode(__('admin/system.editor_btn_fullscreen')) ?>" data-title-exit="<?= html_encode(__('admin/system.editor_btn_fullscreen_exit')) ?>">
						<i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
					</button>
					<button type="button" name="fe-upload-file" class="file-editor-toolbar__btn file-editor-toolbar__btn--icon file-editor-toolbar__when-file" data-bs-toggle="modal" data-bs-target="#upload" title="<?= __('admin/system.editor_btn_upload_title') ?>">
						<i class="fa-solid fa-file-import" aria-hidden="true"></i>
					</button>
					<button type="button" name="save" class="file-editor-toolbar__btn file-editor-toolbar__btn--save file-editor-toolbar__when-file" title="<?= __('admin/system.editor_btn_save_title') ?>"<?= ($resolved && is_writable($file)) ? '' : ' disabled' ?>>
						<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
						<span><?= __('admin/system.editor_btn_save') ?></span>
					</button>
				</div>
			</div>
		</div>
		<div class="file-editor-workspace">
			<div id="files_tree">
				<?= files_tree(ROOT_DIR, $resolved ? $file : ''); ?>
				<div class="files-tree-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner l'arborescence" title="Glisser pour redimensionner" tabindex="0">
					<span class="files-tree-resizer__handle" aria-hidden="true">
						<span class="files-tree-resizer__dot"></span>
						<span class="files-tree-resizer__dot"></span>
						<span class="files-tree-resizer__dot"></span>
						<span class="files-tree-resizer__dot"></span>
						<span class="files-tree-resizer__dot"></span>
						<span class="files-tree-resizer__dot"></span>
					</span>
				</div>
			</div>
			<div id="files_edit">
				<div class="file-editor-empty" role="status"<?= $resolved ? ' hidden' : '' ?>>
					<div class="file-editor-empty__visual" aria-hidden="true">
						<i class="fa-solid fa-file-code"></i>
					</div>
					<h2 class="file-editor-empty__title"><?= __('admin/system.editor_empty_title') ?></h2>
					<p class="file-editor-empty__text"><?= __('admin/system.editor_empty_text') ?></p>
					<ul class="file-editor-empty__hints">
						<li>
							<i class="fa-solid fa-folder-open" aria-hidden="true"></i>
							<span><?= __('admin/system.editor_empty_hint_tree') ?></span>
						</li>
						<li>
							<i class="fa-solid fa-arrow-pointer" aria-hidden="true"></i>
							<span><?= __('admin/system.editor_empty_hint_click') ?></span>
						</li>
					</ul>
				</div>
				<textarea name="content" hidden><?= $resolved ? html_encode(file_get_contents($file)) : '' ?></textarea>
				<div id="code_editor"<?= $resolved ? '' : ' hidden' ?>></div>
			</div>
		</div>
	</form>
</div>
<script type="text/javascript">
(function() {
	var $editorRoot = $('#file_editor');
	var $tree = $('#files_tree');
	var $resizer = $editorRoot.find('.files-tree-resizer');
	var $toolbar = $editorRoot.find('.file-editor-toolbar');
	var $breadcrumb = $editorRoot.find('.file-editor-toolbar__breadcrumb');
	var $readonlyBadge = $editorRoot.find('.file-editor-toolbar__badge--readonly');
	var $saveBtn = $editorRoot.find('button[name="save"]');
	var $emptyState = $editorRoot.find('.file-editor-empty');
	var $textarea = $editorRoot.find('textarea[name="content"]');
	var $codeEditor = $('#code_editor');
	var minWidth = 180;
	var maxWidth = 600;
	var storageKey = 'file_editor.treeWidth';
	var fullscreenStorageKey = 'file_editor.fullscreen';
	var labels = {
		root: $editorRoot.attr('data-label-root'),
		readonly: $editorRoot.attr('data-label-readonly'),
		loadError: $editorRoot.attr('data-label-load-error'),
		confirmUnsaved: $editorRoot.attr('data-label-confirm-unsaved'),
		saveSuccess: $editorRoot.attr('data-label-save-success'),
		saveError: $editorRoot.attr('data-label-save-error')
	};
	var currentFile = $editorRoot.attr('data-current-file') || '';
	var isDirty = false;
	var isLoading = false;
	var isSaving = false;
	var isWritable = false;
	var suppressDirty = false;
	var editor = null;

	var editorBasePath = $editorRoot.attr('data-editor-path') || window.location.pathname;
	var editorBaseQuery = $editorRoot.attr('data-editor-query') || 'page=file_editor';

	function buildEditorUrl(filePath) {
		var url = editorBasePath + '?' + editorBaseQuery;
		if (filePath) {
			url += '&file=' + encodeURIComponent(filePath);
		}
		return url;
	}

	function escapeHtml(value) {
		return $('<div>').text(value == null ? '' : value).html();
	}

	function resizeAceEditor() {
		if (editor && editor.resize) {
			editor.resize(true);
		}
	}

	function refreshAceLayout(aceInstance, resetScroll) {
		if (!aceInstance || !aceInstance.renderer) {
			return;
		}

		if (resetScroll !== false) {
			aceInstance.renderer.setScrollTop(0);
			aceInstance.renderer.setScrollLeft(0);
		}

		aceInstance.resize(true);
		aceInstance.renderer.onResize(true);
	}

	function setAceMode(aceInstance, ext, callback) {
		var modeMap = {
			js: 'javascript',
			htm: 'html',
			md: 'markdown',
			htaccess: 'apache_conf',
			conf: 'ini',
			txt: 'plain_text',
			htpasswd: 'plain_text',
			tpl: 'twig'
		};
		var modeId = modeMap[ext] || ext || 'text';

		aceInstance.session.setMode('ace/mode/' + modeId, function() {
			refreshAceLayout(aceInstance, false);
			if (typeof callback === 'function') {
				callback();
			}
		});
	}

	function setTreeWidth(width, shouldResizeEditor) {
		width = Math.max(minWidth, Math.min(maxWidth, Math.round(width)));
		$editorRoot[0].style.setProperty('--file-editor-tree-width', width + 'px');
		if (shouldResizeEditor !== false) {
			resizeAceEditor();
		}
		return width;
	}

	function initAceEditor() {
		if (editor) {
			return editor;
		}

		var aceScript = document.querySelector('script[src*="ace/ace.js"]');
		if (aceScript && ace.config) {
			ace.config.set('basePath', aceScript.src.replace(/\/ace\.js(\?.*)?$/, ''));
		}

		editor = ace.edit('code_editor');
		editor.setTheme($('select[name="theme"]').val() || '<?= App::getConfig('file_editor.theme') ?>');
		editor.setOption('fontSize', '<?= (int) App::getConfig('file_editor.fontSize') ?: 12 ?>px');
		editor.setOption('showPrintMargin', false);
		editor.setOption('wrap', true);
		editor.setOption('scrollPastEnd', 0);

		editor.getSession().on('change', function() {
			$textarea.val(editor.getSession().getValue());
			if (!suppressDirty) {
				isDirty = true;
			}
		});

		editor.commands.addCommand({
			name: 'save',
			bindKey: {win: 'Ctrl-S', mac: 'Cmd-S'},
			exec: function() {
				$saveBtn.click();
			}
		});

		$('select[name="theme"]').off('change.fileEditor').on('change.fileEditor', function() {
			$.post('', {theme: this.value, csrf: csrf});
			editor.setTheme(this.value);
		});

		$('span[name=font-size]').text(parseInt(editor.getOption('fontSize'), 10));

		$("button[name=fe-zoom-in]").off('click.fileEditor').on('click.fileEditor', function() {
			var fsize = parseInt(editor.getOption('fontSize'), 10) + 1;
			editor.setOption('fontSize', fsize);
			$('span[name=font-size]').text(fsize);
			$.post('', {fontSize: fsize, csrf: csrf});
			resizeAceEditor();
			return false;
		});

		$("button[name=fe-zoom-out]").off('click.fileEditor').on('click.fileEditor', function() {
			var fsize = parseInt(editor.getOption('fontSize'), 10) - 1;
			editor.setOption('fontSize', fsize);
			$('span[name=font-size]').text(fsize);
			$.post('', {fontSize: fsize, csrf: csrf});
			resizeAceEditor();
			return false;
		});

		return editor;
	}

	function setEditorContent(value) {
		var aceInstance = initAceEditor();
		suppressDirty = true;
		aceInstance.setValue(value == null ? '' : value, -1);
		aceInstance.getSession().getUndoManager().reset();
		$textarea.val(aceInstance.getSession().getValue());
		isDirty = false;
		setTimeout(function() {
			suppressDirty = false;
			isDirty = false;
		}, 0);
		return aceInstance;
	}

	function renderBreadcrumb(segments) {
		var html = '<li class="breadcrumb-item">' +
			'<button type="button" class="file-editor-toolbar__breadcrumb-link file-editor-breadcrumb-root" title="' + escapeHtml(labels.root) + '">' +
			'<i class="fa-solid fa-house" aria-hidden="true"></i>' +
			'<span>' + escapeHtml(labels.root) + '</span>' +
			'</button></li>';

		(segments || []).forEach(function(segment) {
			if (segment.is_file) {
				html += '<li class="breadcrumb-item active" aria-current="page">' +
					'<span class="file-editor-toolbar__breadcrumb-separator" aria-hidden="true">›</span>' +
					'<i class="fa-regular fa-file" aria-hidden="true"></i>' +
					'<span class="file-editor-toolbar__breadcrumb-current">' + escapeHtml(segment.name) + '</span>' +
					'</li>';
			} else {
				html += '<li class="breadcrumb-item">' +
					'<span class="file-editor-toolbar__breadcrumb-separator" aria-hidden="true">›</span>' +
					'<button type="button" class="file-editor-toolbar__breadcrumb-link" data-tree-dir="' + escapeHtml(segment.tree_dir) + '" title="' + escapeHtml(segment.path) + '">' +
					'<i class="fa-solid fa-folder folder-icon" aria-hidden="true"></i>' +
					'<span>' + escapeHtml(segment.name) + '</span>' +
					'</button></li>';
			}
		});

		$breadcrumb.html(html);
	}

	function revealTreeDir(dirId) {
		var $target = $('#' + dirId);
		if (!$target.length) {
			return;
		}

		$target.parents('.collapsible-body').addBack().each(function() {
			if (window.bootstrap && bootstrap.Collapse) {
				bootstrap.Collapse.getOrCreateInstance(this, { toggle: false }).show();
			} else {
				$(this).addClass('show expand');
			}
		});

		var scrollTarget = $target.closest('.collapsible-header.dir').get(0) || $target.get(0);
		if (scrollTarget) {
			scrollTarget.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		}
	}

	function expandTreeForBreadcrumb(segments) {
		(segments || []).forEach(function(segment) {
			if (!segment.is_file && segment.tree_dir) {
				revealTreeDir(segment.tree_dir);
			}
		});
	}

	function updateTreeSelection(filePath) {
		$tree.find('.file').removeClass('selected');
		if (!filePath) {
			return;
		}

		$tree.find('.file-editor-tree-link').each(function() {
			if ($(this).attr('data-file') === filePath) {
				$(this).closest('.file').addClass('selected');
			}
		});
	}

	function updateUrl(filePath) {
		if (!window.history || !history.replaceState) {
			return;
		}

		history.replaceState({ file: filePath || '' }, '', buildEditorUrl(filePath));
	}

	function setReadonlyState(writable) {
		isWritable = !!writable;
		if (writable) {
			$readonlyBadge.prop('hidden', true);
			$saveBtn.prop('disabled', false);
		} else {
			$readonlyBadge.prop('hidden', false);
			$saveBtn.prop('disabled', true);
		}
	}

	function showEditorAlert(type, message) {
		var $alerts = $('#admin-page .alerts');
		if (!$alerts.length) {
			return;
		}

		$alerts.find('.file-editor-alert').remove();
		var alertClass = type === 'success' ? 'alert-success auto-dismiss' : 'alert-danger';
		var $alert = $('<div class="alert file-editor-alert alert-dismissible fade show ' + alertClass + '" role="alert">' +
			'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
			'<span>' + escapeHtml(message) + '</span></div>');
		$alerts.append($alert);

		if (type === 'success') {
			setTimeout(function() {
				$alert.fadeOut(300, function() {
					$alert.remove();
				});
			}, 4000);
		}
	}

	function saveFile() {
		if (!currentFile || !isWritable || isSaving) {
			return $.Deferred().reject().promise();
		}

		var content = editor ? editor.getSession().getValue() : $textarea.val();
		isSaving = true;
		$saveBtn.prop('disabled', true);

		return $.ajax({
			url: buildEditorUrl(''),
			type: 'POST',
			dataType: 'json',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			data: {
				action: 'save_file',
				file: currentFile,
				content: content,
				csrf: csrf
			}
		}).done(function(payload) {
			if (payload && payload.success) {
				isDirty = false;
				if (editor && editor.getSession().getUndoManager().markClean) {
					editor.getSession().getUndoManager().markClean();
				}
				showEditorAlert('success', payload.message || labels.saveSuccess);
				return;
			}

			showEditorAlert('error', payload && payload.error ? payload.error : labels.saveError);
		}).fail(function(xhr) {
			var message = labels.saveError;
			if (xhr.responseJSON && xhr.responseJSON.error) {
				message = xhr.responseJSON.error;
			}
			showEditorAlert('error', message);
		}).always(function() {
			isSaving = false;
			if (isWritable) {
				$saveBtn.prop('disabled', false);
			}
		});
	}

	function applyFilePayload(payload) {
		currentFile = payload.file;
		isDirty = false;
		$editorRoot.attr('data-current-file', currentFile);

		renderBreadcrumb(payload.breadcrumb);
		setReadonlyState(payload.writable);
		updateTreeSelection(currentFile);
		expandTreeForBreadcrumb(payload.breadcrumb);
		updateUrl(currentFile);

		$toolbar.removeClass('file-editor-toolbar--empty');
		$emptyState.prop('hidden', true);
		$codeEditor.prop('hidden', false);

		window.requestAnimationFrame(function() {
			var aceInstance = setEditorContent(payload.content);
			setAceMode(aceInstance, payload.ext, function() {
				aceInstance.setReadOnly(!payload.writable);
				refreshAceLayout(aceInstance);
			});
		});
	}

	function clearFileEditor() {
		currentFile = '';
		isDirty = false;
		$editorRoot.attr('data-current-file', '');

		renderBreadcrumb([]);
		setReadonlyState(false);
		updateTreeSelection('');
		updateUrl('');

		$toolbar.addClass('file-editor-toolbar--empty');
		$emptyState.prop('hidden', false);
		$codeEditor.prop('hidden', true);
		$textarea.val('');
	}

	function confirmDiscardChanges() {
		return !isDirty || window.confirm(labels.confirmUnsaved);
	}

	function loadFile(filePath) {
		if (!filePath || isLoading) {
			return $.Deferred().reject().promise();
		}

		if (filePath === currentFile) {
			return $.Deferred().resolve().promise();
		}

		if (!confirmDiscardChanges()) {
			return $.Deferred().reject().promise();
		}

		isLoading = true;
		$editorRoot.addClass('is-loading-file');

		return $.ajax({
			url: buildEditorUrl(''),
			type: 'POST',
			dataType: 'json',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			data: {
				action: 'load_file',
				file: filePath,
				csrf: csrf
			}
		}).done(function(payload) {
			if (!payload || payload.error) {
				window.alert(payload && payload.error ? payload.error : labels.loadError);
				return;
			}
			applyFilePayload(payload);
		}).fail(function(xhr) {
			var message = labels.loadError;
			if (xhr.responseJSON && xhr.responseJSON.error) {
				message = xhr.responseJSON.error;
			}
			window.alert(message);
		}).always(function() {
			isLoading = false;
			$editorRoot.removeClass('is-loading-file');
		});
	}

	var savedWidth = parseInt(localStorage.getItem(storageKey), 10);
	if (!isNaN(savedWidth)) {
		setTreeWidth(savedWidth);
	}

	$resizer.on('pointerdown', function(e) {
		if (e.button !== 0) {
			return;
		}

		e.preventDefault();
		var resizerEl = e.currentTarget;
		var startX = e.clientX;
		var startWidth = $tree[0].getBoundingClientRect().width;
		var currentWidth = startWidth;

		$resizer.addClass('is-dragging');
		$editorRoot.addClass('is-resizing-tree');
		$('body').addClass('file-editor-resizing');
		resizerEl.setPointerCapture(e.pointerId);

		function onMove(moveEvent) {
			currentWidth = setTreeWidth(startWidth + (moveEvent.clientX - startX), false);
		}

		function onUp(upEvent) {
			$resizer.removeClass('is-dragging');
			$editorRoot.removeClass('is-resizing-tree');
			$('body').removeClass('file-editor-resizing');
			$resizer.off('pointermove', onMove).off('pointerup pointercancel', onUp);

			if (resizerEl.hasPointerCapture(upEvent.pointerId)) {
				resizerEl.releasePointerCapture(upEvent.pointerId);
			}

			localStorage.setItem(storageKey, currentWidth);
			resizeAceEditor();
		}

		onMove(e);
		$resizer.on('pointermove', onMove).on('pointerup pointercancel', onUp);
	});

	$(window).on('resize', resizeAceEditor);

	var $fullscreenBtn = $('[name=fe-fullscreen]');

	function isFileEditorFullscreen() {
		return $('body').hasClass('file-editor-fullscreen');
	}

	function updateFullscreenButton(isActive) {
		if (!$fullscreenBtn.length) {
			return;
		}

		$fullscreenBtn.attr('aria-pressed', isActive ? 'true' : 'false');
		$fullscreenBtn.attr('title', isActive ? $fullscreenBtn.data('title-exit') : $fullscreenBtn.data('title-enter'));
		$fullscreenBtn.find('i').toggleClass('fa-expand', !isActive).toggleClass('fa-compress', isActive);
	}

	function setFileEditorFullscreen(isActive) {
		$('body').toggleClass('file-editor-fullscreen', isActive);
		$editorRoot.toggleClass('is-fullscreen', isActive);
		updateFullscreenButton(isActive);

		if (isActive) {
			sessionStorage.setItem(fullscreenStorageKey, '1');
		} else {
			sessionStorage.removeItem(fullscreenStorageKey);
		}

		resizeAceEditor();
	}

	$fullscreenBtn.on('click', function() {
		setFileEditorFullscreen(!isFileEditorFullscreen());
	});

	$(document).on('keydown.fileEditorFullscreen', function(e) {
		if (e.key === 'Escape' && isFileEditorFullscreen()) {
			setFileEditorFullscreen(false);
		}
	});

	if (sessionStorage.getItem(fullscreenStorageKey) === '1') {
		window.requestAnimationFrame(function() {
			setFileEditorFullscreen(true);
		});
	}

	$editorRoot.find('.file-editor-form').on('submit.fileEditor', function(e) {
		e.preventDefault();
	});

	$saveBtn.on('click.fileEditor', function(e) {
		e.preventDefault();
		saveFile();
	});

	$editorRoot.on('click', '.file-editor-tree-link', function(e) {
		if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
			return;
		}

		e.preventDefault();
		loadFile($(this).attr('data-file'));
	});

	$editorRoot.on('click', '.file-editor-breadcrumb-root', function(e) {
		e.preventDefault();
		if (!confirmDiscardChanges()) {
			return;
		}
		clearFileEditor();
	});

	$editorRoot.on('click', '.file-editor-toolbar__breadcrumb-link[data-tree-dir]', function() {
		revealTreeDir($(this).data('tree-dir'));
	});

	$(window).on('popstate.fileEditor', function(e) {
		var filePath = e.originalEvent.state && e.originalEvent.state.file != null
			? e.originalEvent.state.file
			: (new URLSearchParams(window.location.search)).get('file') || '';

		if (filePath === currentFile) {
			return;
		}

		isDirty = false;
		if (filePath) {
			loadFile(filePath);
		} else {
			clearFileEditor();
		}
	});

	if (currentFile) {
		setReadonlyState(<?= ($resolved && is_writable($file)) ? 'true' : 'false' ?>);
		window.requestAnimationFrame(function() {
			var aceInstance = setEditorContent($textarea.val());
			setAceMode(aceInstance, '<?= $resolved ? html_encode($ext) : 'text' ?>', function() {
				aceInstance.setReadOnly(<?= ($resolved && is_writable($file)) ? 'false' : 'true' ?>);
				refreshAceLayout(aceInstance);
			});
		});
	}

	if (window.history && history.replaceState) {
		history.replaceState({ file: currentFile }, '', buildEditorUrl(currentFile));
	}
})();
</script>