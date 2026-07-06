<?php defined('EVO') or die('Que fais-tu là?');

$mod_view = defined('EVO_ADMIN');
$view = App::GET('view') === 'grid' ? 'grid' : 'list';

if ($mod_view) {
	has_permission('admin.manage_media', true);
	$origin = 'website';
} else {
	has_permission('user.upload', true);
	$origin = 'user';
}

gallery_handle_requests($mod_view, $origin);
$files = gallery_fetch_files($mod_view, (bool) App::REQ('embed'));
?>
<div class="float-start btn-group">
	<a data-gallery-view-switch="grid" class="btn btn-outline-secondary" href="#"><i class="fa-solid fa-table-cells"></i></a>
	<a data-gallery-view-switch="list" class="btn btn-outline-secondary" href="#"><i class="fa-solid fa-list"></i></a>
	<button id="search" class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button> &nbsp;
</div>
<button id="uploadfile" class="btn btn-info float-start"><i class="fa-solid fa-upload"></i> <?= __('gallery.menu_btn_upload') ?></button>
<div class="float-end form-inline gallery-controls">
	<button id="insertgal" class="btn btn-primary d-none"><?= __('gallery.menu_btn_insert_gal') ?></button>
	<button id="insertfile" class="btn btn-primary d-none"><?= __('gallery.menu_btn_insert_file') ?></button>
	<button id="insertthumb" class="btn btn-primary d-none"><?= __('gallery.menu_btn_insert_thumb') ?></button>
	<select id="gallery-thumbsize" class="form-control d-none">
		<option value="100x100"><?= __('gallery.menu_btn_crop_small') ?> (100px)</option>
		<option value="200x200" selected><?= __('gallery.menu_btn_crop_medium') ?> (200px)</option>
		<option value="480x480"><?= __('gallery.menu_btn_crop_large') ?> (480px)</option>
		<option value="100"><?= __('gallery.menu_btn_scale_small') ?> (100px)</option>
		<option value="200"><?= __('gallery.menu_btn_scale_medium') ?> (200px)</option>
		<option value="480"><?= __('gallery.menu_btn_scale_large') ?> (480px)</option>
		<option value="0"><?= __('gallery.menu_btn_full_size') ?></option>
	</select>
	<button id="deletefiles" class="btn  btn-danger d-none"><i class="fa-solid fa-xmark"></i> <?= __('gallery.menu_btn_delete') ?></button>
</div>
<div class="clearfix"></div>
<br>
<input id="filter" name="filter" type="text" class="form-control d-none" value="" placeholder="<?= __('gallery.search_placeholder') ?>">
<div id="gallery-content" class="gallery">
	<div id="content">
	<?php
		if ($view === 'list') {
			echo '<table class="file-list">';
			echo '<thead><tr>';
			echo '<th></th><th>'. __('gallery.table_details') .'</th><th>'. __('gallery.table_date') .'</th><th>'. __('gallery.table_category') .'</th><th>'. __('gallery.table_views') .'</th>';
			echo '</tr></thead>';
			foreach($files as $file) {
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
					echo '<td><form method="post"><button onclick="return confirm(\''. __('gallery.dialog_confirm_delete') .'\');" name="delete" value="'.$file->web_id.'" class="btn btn-sm btn-danger"><i class="fa-solid fa-xmark"></i></button></form></td>';
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
				echo ' &nbsp;<a href="'.$file->getLink().'"><small><i class="fa-solid fa-up-right-from-square"></i></small></a>';
				echo '</div>';
			}
			echo '</div>';
		}
	?>
	</div>
</div>
<?= admin_gallery_scripts($mod_view, true, false) ?>
