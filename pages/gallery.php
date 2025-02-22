<?php defined('EVO') or die('Que fais-tu là?');
use Evo\Models\File;

$mod_view = defined('EVO_ADMIN');
$view = App::GET('view') === 'grid' ? 'grid' : 'list';

if ($mod_view) {
	has_permission('admin.manage_media', true);
	if (App::REQ('embed')) {
		$where = "origin is null or origin in (?, ?, ?)";
		$where_e = ['website', 'admin', ''];
	} else {
		$where = '1';
		$where_e = [];
	}
	$origin = 'website';
} else {
	has_permission('user.upload', true);
	$where = '(origin like ? or origin like ?) and poster = ? ';
	$where_e = ['user%', 'forum%', App::getCurrentUser()->id];
	$origin = 'user';
}


if (App::REQ('filter')) {
	$where.= ' AND (name like ? or path like ?) ';
	$where_e[] = $filter = '%' . App::REQ('filter') . '%';
	$where_e[] = $filter = '%' . App::REQ('filter') . '%';
}


if (isset($_FILES['ajaxup'])) {
	try {
		$file = File::create('ajaxup', $origin);
		die(json_encode(array($file->name, $file->web_id, $file->web_id, $file->size)));
	} catch (UploadException $e) {
		die("Error: {$e->getMessage()}");
	}
}

if (App::POST('delete')) {
	foreach((array)App::POST('delete') as $fileID) {
		if (($file = File::find($fileID, 'web_id')) && ($mod_view || $file->poster->id == App::getCurrentUser()->id)) {
			$file->delete();
			App::setSuccess(__('gallery.success_file_updated'));
		} else {
			App::setWarning(__('gallery.warning_delete_failed'));
		}
	}
}
elseif (App::POST('caption')) {
	foreach(App::POST('caption') as $fileID => $newCaption) {
		if (($file = File::find($fileID, 'web_id')) && ($mod_view || $file->poster->id == App::getCurrentUser()->id)) {
			$file->caption = $newCaption;
			$file->save();
		} else {
			App::setWarning(__('gallery.warning_update_failed'));
		}
	}
}
elseif (App::POST('filename')) {
	foreach(App::POST('filename') as $fileID => $newName) {
		if (($file = File::find($fileID, 'web_id')) && ($mod_view || $file->poster->id == App::getCurrentUser()->id)) {
			$file->name = $newName;
			$file->save();
		} else {
			App::setWarning(__('gallery.warning_update_failed'));
		}
	}
}

$files = File::select("$where order by id desc", ...$where_e);
?>
<div class="float-left btn-group">
	<a data-gallery-view-switch="grid" class="btn btn-default" href="#"><i class="fa fa-th"></i></a>
	<a data-gallery-view-switch="list" class="btn btn-default" href="#"><i class="fa fa-list"></i></a>
	<button id="search" class="btn btn-default"><i class="fa fa-search"></i></button> &nbsp;
</div>
<button id="uploadfile" class="btn btn-info float-left"><i class="fa fa-upload"></i> <?= __('gallery.menu_btn_upload') ?></button>
<div class="float-right form-inline gallery-controls">
	<button id="insertgal" class="btn btn-primary hide"><?= __('gallery.menu_btn_insert_gal') ?></button>
	<button id="insertfile" class="btn btn-primary hide"><?= __('gallery.menu_btn_insert_file') ?></button>
	<button id="insertthumb" class="btn btn-primary hide"><?= __('gallery.menu_btn_insert_thumb') ?></button>
	<select id="gallery-thumbsize" class="form-control hide">
		<option value="100x100"><?= __('gallery.menu_btn_crop_small') ?> (100px)</option>
		<option value="200x200" selected><?= __('gallery.menu_btn_crop_medium') ?> (200px)</option>
		<option value="480x480"><?= __('gallery.menu_btn_crop_large') ?> (480px)</option>
		<option value="100"><?= __('gallery.menu_btn_scale_small') ?> (100px)</option>
		<option value="200"><?= __('gallery.menu_btn_scale_medium') ?> (200px)</option>
		<option value="480"><?= __('gallery.menu_btn_scale_large') ?> (480px)</option>
		<option value="0"><?= __('gallery.menu_btn_full_size') ?></option>
	</select>
	<button id="deletefiles" class="btn  btn-danger hide"><i class="fa fa-times"></i> <?= __('gallery.menu_btn_delete') ?></button>
</div>
<div class="clearfix"></div>
<br>
<input id="filter" name="filter" type="text" class="form-control hide" value="" placeholder="<?= __('gallery.search_placeholder') ?>">
	<div id="gallery-content" class="gallery">
	<div id="content">
	<?php
		if ($view === 'list') {
			echo '<table class="file-list">';
			echo '<thead><tr>';
			echo '<th></th><th>'. __('gallery.table_details') .'</th><th>'. __('gallery.table_date') .'</th><th>'. __('gallery.table_category') .'</th><th>'. __('gallery.table_views') .'</th>';
			echo '</tr></thead>';
			foreach($files as $file) {
				$ext = pathinfo($file->name, PATHINFO_EXTENSION);
				echo '<tr class="file-list-row">';
					echo '<td><img src="'.$file->getLink(128).'" style="max-width:128px; max-height:128px;"><br><a href="'.$file->getLink().'">'. __('gallery.table_file_view').'</a></td>';
					echo '<td style="text-align:left"><table style="width: 100%">';
					echo '<tr><td style="width:80px"><strong>'. __('gallery.table_file_caption') .' :</strong></td><td><input type="text" style="width:100%;background:#fdfdfd;border:1px solid #aaa;" name="caption['.$file->web_id.']" value="'.html_encode($file->caption).'" /></td></tr>';
					echo '<tr><td><strong>'. __('gallery.table_file_name').' :</strong></td><td><input type="text" style="width:100%;background:#fdfdfd;border:1px solid #aaa;" name="filename['.$file->web_id.']" value="'.html_encode($file->name).'" /></td></tr>';
					echo '<tr><td><strong>'. __('gallery.table_file_type').' :</strong></td><td>'.html_encode($file->type).' ('.html_encode($file->mime_type).')</td></tr>';
					echo '<tr><td><strong>'. __('gallery.table_file_size').' :</strong></td><td>'.Format::size($file->size).'</td></tr>';
					if ($mod_view) {
						echo '<tr><td><strong>User:</strong></td><td>'.html_encode($file->poster->username).'</td></tr>';
					}
					echo '</table></td>';
					echo '<td>'.Format::today($file->posted).'</td>';
					echo '<td>'.html_encode($file->origin).'</td>';
					echo '<td>'.$file->hits.'</td>';
					echo '<td><form method="post"><button onclick="return confirm(\''. __('gallery.dialog_confirm_delete') .'\');" name="delete" value="'.$file->web_id.'" class="btn btn-sm btn-danger"><i class="fa fa-times"></i></button></form></td>';
				echo '</tr>';
			}
			echo '</table>';
		} else {
			echo '<div class="gallery-editor">';
			foreach($files as $file) {
				echo '<div class="gallery-container" data-id="' . $file->web_id . '" data-type="' . $file->type . '" data-size="' . $file->size . '" data-caption="'.html_encode($file->caption ?: $file->name).'" data-href="'.$file->getLink().'">';
				echo '<div class="gallery-container-content">';
				echo '<img src="'.App::getURL('getfile', ['id' => $file->web_id, 'size' => '160']).'" style="max-width:160px;max-height:128px;">';
				echo '</div>';
				echo Format::truncate(html_encode($file->caption ?: $file->name), 22);
				echo ' &nbsp;<a href="'.$file->getLink().'"><small><i class="fa fa-external-link-alt"></i></small></a>';
				echo '</div>';
			}
			echo '</div>';
		}
	?>
	</div>
</div>
<script>
	var view_mode = '<?= $view ?>';
	var gallery_url = site_url + '<?= $mod_view ? '/admin/' : '/' ?>?page=gallery&view=';
	var gallery_pos = 0;

	$('.gallery').on('change keyup', '.file-list input', function(e) {
		var post = {'csrf': csrf};
		post[$(this).attr('name')] = $(this).val();
		$.post('',  post);
	});


	$('.gallery').on('click', '.gallery-container', function() {
		if ($(this).hasClass('active')) {
			$(this).removeClass('active');
		} else {
			$(this).addClass('active');
			$(this).attr('data-pos', gallery_pos++);
		}

		if ($('.gallery .active').length == 0) {
			$('#insertgal, #insertfile, #insertthumb, #deletefiles, #gallery-thumbsize').addClass('hide');
		} else if (!$.fancybox.isOpen) {
			$('#deletefiles').removeClass('hide');
		} else if ($('.gallery .active').length > 1) {
			$('#insertgal, #deletefiles, #gallery-thumbsize').removeClass('hide');
			$('#insertfile, #insertthumb').addClass('hide');
		} else {
			$('#insertfile, #insertthumb, #deletefiles, #gallery-thumbsize').removeClass('hide');
			$('#insertgal').addClass('hide');
		}
	});


	$('#deletefiles').click(function() {
		var files = [], captions = [];

		$('.gallery .active').each(function() {
			files.push($(this).attr('data-id'));
			captions.push($(this).attr('data-caption'));
		});

		if (confirm('<?=addslashes(__('gallery.dialog_confirm_delete'))?>\n' + captions.join("\n"))) {
			$('#gallery-content').load(gallery_url + view_mode + ' #gallery-content  > *', {'delete[]': files, csrf:csrf});
		}
	});


	$('#insertthumb, #insertfile').click(function() {
		var e = $('.gallery .active').first();
		if (e.length != 0) {
			window._editor.insertFiles([{
				link: e.attr('data-href'),
				name: e.attr('data-caption'),
				type: '',
				size: e.attr('data-size'),
				thumb: ($(this).attr('id') == 'insertthumb') ? $('#gallery-thumbsize').val() : 0,
				id: e.attr('data-id')
			}]);
		}
		$.fancybox.close();
	});


	$('#insertgal').click(function() {
		let files = [];
		$('.gallery .active').sort(function (a, b) {
			var contentA = parseInt( $(a).attr('data-pos'));
			var contentB = parseInt( $(b).attr('data-pos'));
			return (contentA < contentB) ? -1 : (contentA > contentB) ? 1 : 0;
		}).each(function() {
			var e = $(this);
			files.push({
				link: e.attr('data-href'),
				name: e.attr('data-caption'),
				type: 'thumb',
				size: e.attr('data-size'),
				thumb: $('#gallery-thumbsize').val(),
				id: e.attr('data-id')
			});
		});
		window._editor.insertFiles(files);
		$.fancybox.close();
	});


	$('#uploadfile').click(function() {
		ajaxupload(function() {
			$('#gallery-content').load(gallery_url + view_mode + ' #gallery-content > *')
		});
		return false;
	});


	$('#search').click(function() {
		if ($.fancybox.isOpen) {
			alert('La recherche ne fonctionne pas encore en mode fancybox!');
			return false;
		}
		$('#filter').toggleClass('hide').focus();
		$(this).toggleClass('active');
	});


	$('[data-gallery-view-switch]').click(function(){
		view_mode = $(this).attr('data-gallery-view-switch');
		var url = gallery_url + view_mode;
		$('#gallery-content').load(url  + ' #gallery-content > *');
		if (!$.fancybox.isOpen) {
			history.replaceState(null, null, url);
		}
		if (view_mode == 'list') {
			$('.gallery-controls button:not(#uploadfile)').addClass('hide');
		}
		$('[data-gallery-view-switch]').removeClass('active');
		$(this).addClass('active');
		return false;
	});


	$('[data-gallery-view-switch='+view_mode+']').addClass('active');


	if (!$('textarea').length) {
		$('#gallery-thumbsize').addClass('hide');
	}
</script>