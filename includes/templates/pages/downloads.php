<legend><?= __('downloads.title') ?></legend>
<?php
if (empty($files)) {
	echo '<div class="alert alert-warning text-center">'. __('downloads.none') .'</div>';
	return;
}
?>
<table class="table table-downloads">
<tbody>
<?php foreach($files as $file) {
	echo '<tr>';
		echo '<td class="date">'.date('Y-m-d', $file->posted).'<br><small>'. __('downloads.by') .' <a href="'.App::getURL('user', $file->poster->username).'">'.$file->poster->username.'</a></small>';
		echo '</td>';
		echo '<td>';
		echo ' <label class="caption"><strong>'.html_encode($file->caption).'</strong></label>';
		echo ' <div class="description">'.(markdown2html($file->description) ?: __('downloads.no_description')).'</div>';
		echo ' <div class="link"><strong><a href="'.$file->getLink().'" title="'. __('downloads.file_title') .'"><i class="fa fa-download"></i> '.html_encode($file->name).'</a></strong> <small class="dimmed">('.Format::size($file->size).', '.$file->hits.' hits)</small></div>';
		echo '</td>';
	echo '</tr>';
} ?>
</tbody>
</table>